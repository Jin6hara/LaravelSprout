<?php

namespace App\Http\Controllers;

use App\Models\Leave;
use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;

class LeaveAttachmentController extends Controller
{
    /**
     * 休暇申請の添付ファイルをインライン表示
     * GET /leaves/{leave}/attachments/{attachment} — polymorphic 整合性を検証してローカルストレージから表示
     */
    public function show(Leave $leave, Attachment $attachment)
    {
        // ① ちゃんとこの Leave の添付かチェック（polymorphic 保護）
        if (
            $attachment->attachable_type !== Leave::class ||
            $attachment->attachable_id !== $leave->id
        ) {
            abort(404);
        }

        // ② 認可
        $this->authorize('view', $leave);

        // ③ ファイルパス取得（local ディスク）
        $disk = Storage::disk('local');

        if (! $disk->exists($attachment->path)) {
            abort(404);
        }

        $fullPath = $disk->path($attachment->path);

        // inline 表示（PDF/画像ならブラウザでそのまま開く）
        return response()->file($fullPath);
        // ダウンロードさせたいなら ↓
        // return response()->download($fullPath, $attachment->original_name ?? basename($fullPath));
    }
}
