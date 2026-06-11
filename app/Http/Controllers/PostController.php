<?php

/**
 * 投稿の作成・送信を担当するコントローラ（管理者向け）。
 */
namespace App\Http\Controllers;

use App\Enums\PostType;
use App\Http\Requests\Post\PostSendRequest;
use App\Models\Post;
use App\Services\Post\PostSendService;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function __construct(private PostSendService $postSendService) {}

    /**
     * 投稿作成フォーム表示（admin用）
     * GET /posts/create
     */
    public function create(Request $r)
    {
        $this->authorize('create', Post::class);
        return view('posts.adminCreate');
    }

    /**
     * 投稿送信（宛先・添付ファイルを含む）
     * POST /posts/send
     */
    public function send(PostSendRequest $req)
    {
        $user = $req->user();
        $type = PostType::from($req->input('type'));

        $allowReplies = $req->has('allow_replies')
            ? (bool) $req->boolean('allow_replies')
            : $type->defaultAllowReplies();

        $data = array_merge($req->validated(), [
            'type'          => $type,
            'allow_replies' => $allowReplies,
        ]);

        $recipientIds = collect($req->input('recipients', []))->unique()->values()->all();
        $files        = $req->hasFile('attachments') ? $req->file('attachments') : [];

        $this->postSendService->send($user, $data, $recipientIds, $files);

        return redirect()
            ->route('posts.adminCreate')
            ->with('toast', 'メッセージを送信しました。');
    }
}
