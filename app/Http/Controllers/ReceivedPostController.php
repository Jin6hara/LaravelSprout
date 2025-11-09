<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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
        $viewer = $r->user();
        $target = $this->resolveTarget($r);

        $isRecipient = $post->viewers()->where('users.id', $target->id)->exists();
        $isAdmin = $viewer->hasAnyRole(['admin', 'super_admin']);

        // ✅ 管理者は観覧可 / 受信者本人も観覧可 / それ以外は403
        abort_unless($isRecipient || $isAdmin, 403);

        // pivot: 「targetに対して」読み込む（管理者が見ても pivot は target のもの）
        $post->load([
            'author:id,first_name,family_name,employee_code',
            'viewers' => fn($q) => $q->where('users.id', $target->id)
                ->select('users.id')->withPivot(['confirmed_at']),
        ]);

        // admin用 全宛先 + 確認状況
        $recipients = $isAdmin
            ? $post->viewers()
            ->withPivot(['confirmed_at'])
            ->select('users.id', 'first_name', 'family_name', 'employee_code', 'email')
            ->orderBy('employee_code')
            ->get()
            : null;

        return view('posts.received.show', [
            'post'       => $post,
            'target'     => $target,
            'isProxy'    => $target->id !== $viewer->id,
            'recipients' => $recipients,
        ]);
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
