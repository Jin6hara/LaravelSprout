<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Models\Comment;
use Illuminate\Support\Facades\DB;
use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;

class ReceivedPostController extends Controller
{
    /** admin/super_admin なら employee_code で代理閲覧。なければ自分 */
    private function resolveTarget(Request $r): User
    {
        $me = $r->user();
        $isAdmin = $me->hasAnyRole(['admin', 'super_admin']);
        $code = trim((string) $r->query('employee_code', ''));

        if ($isAdmin && $code !== '') {
            $u = User::where('employee_code', $code)->first();
            if ($u) return $u;
        }
        return $me;
    }

    public function index(Request $r)
    {
        $target = $this->resolveTarget($r);

        // target宛の受信のみ
        $posts = Post::query()
            ->whereHas('viewers', fn($q) => $q->where('users.id', $target->id))
            // 投稿者・対象pivotのみをロード
            ->with([
                'author:id,first_name,family_name,employee_code',
                'viewers' => fn($q) => $q->where('users.id', $target->id)->select('users.id'),
            ])
            ->latest('posts.created_at')
            ->paginate(20)
            ->withQueryString();

        return view('posts.received.index', [
            'posts'  => $posts,
            'target' => $target,
            'isProxy' => $target->id !== $r->user()->id,
        ]);
    }

    public function show(Request $r, Post $post)
    {
        $me = $r->user();

        // 代理切替はそのまま（閲覧権限の判定も現状通り）
        $target = $me;
        $isProxy = false;
        if ($me->isAdmin() && $r->filled('employee_code')) {
            $target = User::where('employee_code', $r->query('employee_code'))->firstOrFail();
            if ($target->id !== $me->id) $isProxy = true;
        }

        $isAuthor   = $post->user_id === $me->id;
        $isViewerMe = $post->viewers()->where('users.id', $me->id)->exists();
        abort_unless($isAuthor || $isViewerMe || $me->isAdmin(), 403);
        $isAdmin = $me->isAdmin();

        // === 可視ロジック ===
        if ($me->isAdmin()) {
            // 管理者：全件（親だけrootにして全部ツリー表示）
            $comments = Comment::query()
                ->where('post_id', $post->id)
                ->whereNull('parent_id')
                ->with(['author', 'childrenRecursive.author'])
                ->orderBy('created_at')
                ->get();
        } else {
            // 一般ユーザー：
            // 1) 自分のコメント（親・子問わず）を取得
            // 2) その配下の返信ツリーを表示（＝自分宛の返信が見える）
            // 3) ただし、自分のコメントの“子”を個別に root 表示すると重複するため、
            //    「親が自分のコメントである」コメントは root から除外する
            $comments = Comment::query()
                ->where('post_id', $post->id)
                ->whereNull('parent_id')          // ← 親だけ
                ->where('user_id', $me->id)       // ← 自分が書いた親のみ
                ->with(['author', 'childrenRecursive.author'])
                ->orderBy('created_at')
                ->get();
        }

        $pivot = $post->viewers()->where('users.id', $target->id)->first()?->pivot;

        // admin用 全宛先 + 確認状況
        $recipients = $isAdmin
            ? $post->viewers()
            ->withPivot(['confirmed_at'])
            ->select('users.id', 'first_name', 'family_name', 'employee_code', 'email')
            ->orderBy('employee_code')
            ->get()
            : null;

        return view('posts.received.show', [
            'post'     => $post->load(['author', 'attachments']),
            'target'   => $target,
            'isProxy'  => $isProxy,
            'comments' => $comments,
            'pivot'    => $pivot,
            'me'       => $me, // Bladeでの判定用
            'recipients' => $recipients,
        ]);
    }

    public function storeComment(Request $r, Post $post)
    {
        $me = $r->user();

        // 閲覧可能者のみコメント可（投稿者 / 宛先 / 管理者）
        $isAuthor   = $post->user_id === $me->id;
        $isViewerMe = $post->viewers()->where('users.id', $me->id)->exists();
        abort_unless($isAuthor || $isViewerMe || $me->hasAnyRole(['admin', 'super_admin']), 403);

        // 返信許可（期限含む）
        if (method_exists($post, 'allowsReplies') && !$post->allowsReplies()) {
            return back()->with('toast_errors', ['Reply is not allowed for this post.']);
        }

        $data = $r->validate([
            'body'      => ['required', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
        ]);

        // parent_id があるときは同一 post に属することを保証
        if (!empty($data['parent_id'])) {
            $ok = Comment::whereKey($data['parent_id'])->where('post_id', $post->id)->exists();
            if (!$ok) return back()->with('toast_errors', ['Invalid parent comment.']);
        }

        $new = Comment::create([
            'post_id'   => $post->id,
            'user_id'   => $me->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body'      => $data['body'],
        ]);

        $url = url()->previous() . '#comment-' . $new->id;

        return redirect($url)->with('toast', 'Reply posted.');
    }

    /** 自分宛のポストを「確認済み」にする（代理は確認不可） */
    public function confirm(Request $r, Post $post)
    {
        $me = $r->user();

        // 自分宛かチェック
        $isRecipient = $post->viewers()->where('users.id', $me->id)->exists();

        // 代理確認は不可。自分宛でない、または代理切替が行われている場合は弾く
        if (!$isRecipient || $r->filled('employee_code')) {
            return back()->with('toast_errors', [
                'You cannot confirm on behalf of others.'
            ]);
        }

        // 自分宛 → confirmed_at 更新
        $post->viewers()->updateExistingPivot($me->id, [
            'confirmed_at' => now()
        ]);

        return back()->with('toast', 'Thank you for the confirmation.');
    }

    public function download(Post $post, Attachment $attachment)
    {
        // ① ちゃんとこの Post の添付かチェック（polymorphic 保護）
        if (
            $attachment->attachable_type !== Post::class ||
            $attachment->attachable_id !== $post->id
        ) {
            abort(404);
        }

        // ② 認可（必要なら）
        // $this->authorize('view', $post);

        // ③ ファイルパス取得（public ディスク前提）
        $disk = Storage::disk('public');

        if (! $disk->exists($attachment->path)) {
            abort(404);
        }

        // ④ ローカルパスに解決してブラウザに表示
        $fullPath = $disk->path($attachment->path);

        // inline 表示（PDF/画像ならブラウザでそのまま開く）
        return response()->file($fullPath);
        // ダウンロードさせたいなら ↓
        // return response()->download($fullPath, $attachment->original_name ?? basename($fullPath));
    }
}
