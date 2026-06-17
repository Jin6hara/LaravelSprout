<?php

/**
 * 送信済み投稿の一覧表示を担当するコントローラ。
 */
namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;

class SentPostController extends Controller
{
    /**
     * 送信済み投稿一覧
     * GET /posts/sent — 管理者は admin/super_admin が送信した全投稿、一般ユーザーは自分の投稿のみを表示
     */
    public function index(Request $request)
    {
        $me = $request->user();

        $isAdmin = $me->can('viewAny', Post::class);

        $posts = Post::query()
            ->with(['author:id,first_name,family_name,employee_code']) // authorリレーション前提（下に補足あり）
            ->when($isAdmin, function ($q) {
                // admin以上：admin以上が投稿したpostのみ一覧
                $q->whereHas('author', fn($uq) => $uq->role(['admin', 'super_admin']));
            }, function ($q) use ($me) {
                // general：自分が送信したpostのみ
                $q->where('user_id', $me->id);
            })
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('posts.sent', compact('posts', 'isAdmin'));
    }
}
