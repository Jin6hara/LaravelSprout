<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;

class PostAttachmentController extends Controller
{
    public function show(Post $post, Attachment $attachment)
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
