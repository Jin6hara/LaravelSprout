<?php

/**
 * 休暇の事後報告（理由・対応種別・添付ファイルの登録）を担うサービス。
 */
namespace App\Services\Leave;

use App\Models\Leave;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReportLeaveService
{
    public function handle(Leave $leave, array $validated, ?UploadedFile $file): void
    {
        $leave->update([
            'reason'      => $validated['reason'],
            'handle_type' => $validated['handle_type'],
        ]);

        if ($file) {
            DB::transaction(function () use ($file, $leave) {
                if ($leave->attachment) {
                    Storage::disk('s3')->delete($leave->attachment->path);
                    $leave->attachment->delete();
                }
                $path = $file->store('attachments/' . now()->format('Y/m'), 's3');
                if ($path === false) {
                    throw new \RuntimeException('ファイルのアップロードに失敗しました。');
                }
                $leave->attachment()->create([
                    'path'          => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'size'          => $file->getSize(),
                ]);
            });
        }
    }
}
