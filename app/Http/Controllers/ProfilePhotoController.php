<?php

/**
 * プロフィール写真のアップロード・削除を担当するコントローラ。
 */
namespace App\Http\Controllers;

use App\Http\Requests\ProfilePhoto\ApplyProfilePhotoRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ProfilePhotoController extends Controller
{
    /**
     * 「保存」ボタンで確定：アップロード or 削除（NULLにしてデフォルト表示）を適用
     *
     * 受け取る可能性のあるパラメータ:
     * - photo: file|null（仮アップロードの確定）
     * - delete: '1' or '0'（今の写真を削除してNULLにする）
     */
    public function apply(ApplyProfilePhotoRequest $request, User $user): JsonResponse
    {
        $hasFile = $request->hasFile('photo');
        $wantDelete = filter_var($request->input('delete'), FILTER_VALIDATE_BOOLEAN);

        // どちらも指定されていないのはNG
        if (!$hasFile && !$wantDelete) {
            return response()->json(['message' => '画像を選択するか、削除を選んでください。'], 422);
        }

        $targetUser = $user;
        $this->authorize('update', $targetUser);
        $old  = $targetUser->profile_picture;

        if ($wantDelete && !$hasFile) {
            // 削除確定（DBをNULLにし、旧ファイルがあれば物理削除）
            $targetUser->profile_picture = null;
            $targetUser->save();

            // 旧物理ファイル（カスタムのみ）削除
            if ($old) {
                $oldPath = public_path('image/' . $old);
                if (File::exists($oldPath)) {
                    @File::delete($oldPath);
                }
            }

            // 返却URL：この時点で表示されるデフォルト画像
            $defaultPictures = config('user.default_profile_pictures');
            $fallback = asset('image/' . $defaultPictures[$targetUser->gender]);

            return response()->json([
                'message' => 'プロフィール写真をデフォルトに戻しました。',
                'url'     => $fallback,
                'is_default' => true,
            ]);
        }

        // ここからはアップロード確定
        $file = $request->file('photo'); // nullではない
        $ext  = $file->getClientOriginalExtension();
        $filename = 'user_' . $targetUser->id . '_' . Str::random(10) . '.' . $ext;

        // public/image に保存
        $file->move(public_path('image'), $filename);

        // DB更新
        $targetUser->profile_picture = $filename;
        $targetUser->save();

        // 旧物理ファイル（カスタムのみ）削除
        if ($old) {
            $oldPath = public_path('image/' . $old);
            if (File::exists($oldPath)) {
                @File::delete($oldPath);
            }
        }

        return response()->json([
            'message' => 'プロフィール写真を更新しました。',
            'url'     => asset('image/' . $filename),
            'is_default' => false,
        ]);
    }
}
