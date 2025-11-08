<?php
// admin用コントローラー
namespace App\Http\Controllers;

use App\Http\Requests\PostSendRequest;
use App\Models\{Post, Attachment, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Enums\PostType;

class PostController extends Controller
{
    public function create(Request $r)
    {
        $this->authorize('create', Post::class);
        return view('posts.adminCreate');
    }

    public function send(PostSendRequest $req)
    {
        $user = $req->user();

        $type = PostType::from($req->input('type'));

        $post = DB::transaction(function () use ($req, $user, $type) {
            $allowReplies = $req->has('allow_replies')
                ? (bool) $req->boolean('allow_replies')
                : $type->defaultAllowReplies(); // ←タイプ既定

            $post = Post::create([
                'user_id'       => $user->id,
                'type'          => $type,
                'title'         => $req->input('title'),
                'body'          => $req->input('body'),
                'expires_at'    => $req->input('expires_at'),
                'allow_replies' => $allowReplies,
            ]);

            // 2) 宛先付与（既読は未設定＝NULL）
            $recipientIds = collect($req->input('recipients', []))
                ->unique()->values()->all();

            // 自分自身には可視化不要（自分の投稿は常に見える）→ 付けたいなら外す
            $recipientIds = array_values(array_diff($recipientIds, [$user->id]));

            if ($recipientIds) {
                $post->viewers()->attach($recipientIds, ['confirmed_at' => null]);
            }

            // 3) 添付ファイル保存（polymorphic）
            if ($req->hasFile('attachments')) {
                $folder = 'attachments/' . now()->format('Y/m');
                foreach ($req->file('attachments') as $file) {
                    $path = $file->store($folder); // disk=default
                    $post->attachments()->create([
                        'path'          => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'size'          => $file->getSize(),
                    ]);
                }
            }

            return $post;
        });

        return redirect()
            ->route('posts.adminCreate')
            ->with('toast', 'メッセージを送信しました。');
    }
}
