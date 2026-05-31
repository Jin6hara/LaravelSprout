<?php

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
                    Storage::disk('local')->delete($leave->attachment->path);
                    $leave->attachment->delete();
                }
                $path = $file->store('attachments/' . now()->format('Y/m'), 'local');
                $leave->attachment()->create([
                    'path'          => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'size'          => $file->getSize(),
                ]);
            });
        }
    }
}
