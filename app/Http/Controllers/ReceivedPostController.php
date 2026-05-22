<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReceivedPost\StoreCommentRequest;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Services\CurrentScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReceivedPostController extends Controller
{
    public function __construct(private CurrentScopeService $scopeService) {}

    /** admin/super_admin なら employee_code で代理閲覧。なければ自分 */
    private function resolveTarget(Request $r): User
    {
        $me = $r->user();
        $canViewAnyUser = $me->can('viewAny', User::class);
        $code = trim((string) $r->query('employee_code', ''));

        if ($canViewAnyUser && $code !== '') {
            $u = $this->scopeService->targetUserQuery()->where('employee_code', $code)->first();
            if ($u) return $u;
        }
        return $me;
    }

    /**
     * 受信投稿一覧
     * GET /posts/received — employee_code クエリで admin による代理閲覧も可
     */
    public function index(Request $r)
    {
        $target = $this->resolveTarget($r);
        $this->authorize('view', $target);

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

        // Unconfirmed count（受信者pivot confirmed_atがNULLの件数）
        $unconfirmedCount = $target->inboxUnconfirmedCount();

        return view('posts.received.index', [
            'posts'  => $posts,
            'target' => $target,
            'isProxy' => $target->id !== $r->user()->id,
            'unconfirmedCount' => $unconfirmedCount,
        ]);
    }

    /**
     * 受信投稿詳細（コメントツリー付き）
     * 管理者は全コメントを表示、一般ユーザーは自分が投稿した親コメントのみ表示
     */
    public function show(Request $r, Post $post)
    {
        $me = $r->user();
        $this->authorize('view', $post);

        // 代理切替はそのまま（閲覧権限の判定も現状通り）
        $target = $me;
        $isProxy = false;
        if ($me->can('viewAny', User::class) && $r->filled('employee_code')) {
            $target = $this->scopeService->targetUserQuery()->where('employee_code', $r->query('employee_code'))->firstOrFail();
            if ($target->id !== $me->id) $isProxy = true;
        }
        $isAdmin = $me->can('viewAny', User::class);

        // === 可視ロジック ===
        if ($isAdmin) {
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

    /**
     * コメント投稿（返信対応）
     * 返信不可の投稿・スコープ外の parent_id は拒否
     */
    public function storeComment(StoreCommentRequest $r, Post $post)
    {
        $me = $r->user();
        $this->authorize('comment', $post);

        // 返信許可（期限含む）
        if (method_exists($post, 'allowsReplies') && !$post->allowsReplies()) {
            return back()->with('toast_errors', ['Reply is not allowed for this post.']);
        }

        $data = $r->validated();

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
        $this->authorize('confirm', $post);

        // 代理確認は不可。自分宛でない、または代理切替が行われている場合は弾く
        if ($r->filled('employee_code')) {
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

        // ② 認可
        $this->authorize('view', $post);

        // ③ ファイルパス取得（local ディスク）
        $disk = Storage::disk('local');

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
