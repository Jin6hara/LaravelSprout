<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Models\Comment;
use Illuminate\Support\Facades\DB;

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

        // 代理閲覧対象（管理者のみ employee_code で切替）
        $target = $me;
        $isProxy = false;
        if ($me->hasAnyRole(['admin', 'super_admin']) && $r->filled('employee_code')) {
            $target = User::where('employee_code', $r->query('employee_code'))->firstOrFail();
            if ($target->id !== $me->id) $isProxy = true;
        }

        // 閲覧権限：投稿者 or 宛先 or 管理者（代理）
        $isAuthor   = $post->user_id === $me->id;
        $isViewerMe = $post->viewers()->where('users.id', $me->id)->exists();
        abort_unless($isAuthor || $isViewerMe || $me->hasAnyRole(['admin', 'super_admin']), 403);

        // コメントの取得
        // 仕様：一般ユーザー→「自分のコメント＋自分コメントへの返信のみ」
        //       管理者代理→全件
        if ($isProxy) {
            $comments = Comment::query()
                ->where('post_id', $post->id)
                ->whereNull('parent_id') // 親だけ
                ->with([
                    'author',
                    'childrenRecursive.author', // 再帰で全階層
                ])
                ->orderBy('created_at')
                ->get();
        } else {
            // 一般ユーザーは「自分の親コメント＋その全返信ツリー」
            $comments = Comment::query()
                ->where('post_id', $post->id)
                ->whereNull('parent_id')
                ->where('user_id', $target->id)
                ->with([
                    'author',
                    'childrenRecursive.author',
                ])
                ->orderBy('created_at')
                ->get();
        }

        // pivot（ターゲット視点）の取得
        $pivot = $post->viewers()->where('users.id', $target->id)->first()?->pivot;

        return view('posts.received.show', [
            'post'     => $post->load(['author', 'attachments']),
            'target'   => $target,
            'isProxy'  => $isProxy,
            'comments' => $comments,
            'pivot'    => $pivot, // 確認バッジで利用
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

        Comment::create([
            'post_id'   => $post->id,
            'user_id'   => $me->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body'      => $data['body'],
        ]);

        return back()->with('toast', 'Reply posted.');
    }

    /** 自分宛のポストを「確認済み」にする（代理は確認不可） */
    public function confirm(Request $r, Post $post)
    {
        $me = $r->user();

        // 自分宛かチェック
        $isRecipient = $post->viewers()->where('users.id', $me->id)->exists();

        // 代理確認しようとした → toast_errors 表示 [app.blade.phpに合わし、配列で返す]
        return back()->with('toast_errors', [
            'You cannot confirm on behalf of others.'
        ]);

        // 自分宛 → confirmed_at 更新
        $post->viewers()->updateExistingPivot($me->id, [
            'confirmed_at' => now()
        ]);

        return back()->with('toast', 'Thank you for the confirmation.');
    }
}
