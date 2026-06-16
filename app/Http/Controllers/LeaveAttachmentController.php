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

        // ③ パスが有効か確認（R2アップロード失敗時に '0' 等が入るケースを除外）
        $path = $attachment->path;
        if (empty($path) || $path === '0') {
            abort(404);
        }

        // ④ R2 から署名付き一時URLを生成してリダイレクト（5分間有効）
        $disk = Storage::disk('s3');

        try {
            if (! $disk->exists($path)) {
                abort(404);
            }
            $url = $disk->temporaryUrl($path, now()->addMinutes(5));
        } catch (\League\Flysystem\UnableToRetrieveMetadata|\League\Flysystem\UnableToCheckFileExistence $e) {
            abort(404);
        }

        return redirect($url);
    }
}
