<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\User;
//use Illuminate\Validation\ValidationException;(try catch 用)
use App\Http\Requests\UpdateUserFieldRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;


class UsersController extends Controller
{
    public function showProfile(?User $user = null): View
    {
        $currentUser = Auth::user();

        // userがnull → 自分のプロフィール（一般ユーザー）
        $targetUser = $user ?? $currentUser;

        // 権限チェック
        $this->authorize('view', $targetUser);

        // プロフィール画像取得ロジック
        $defaultPictures = config('user.default_profile_pictures');
        $gender = $targetUser->gender;
        $targetUser->profile_picture = $targetUser->profile_picture ?? $defaultPictures[$gender];

        return view('user.profile', ['user' => $targetUser]);
    }

    /**
     * @property int $id
     * @property string $name
     * @method bool save()
     */
    public function updateField(UpdateUserFieldRequest $request): JsonResponse
    {
        $targetUser = Auth::user();

        // 権限チェック
        $this->authorize('update', $targetUser);

        $field = $request->input('field');
        $validated = $request->validated(); // バリデーション通過後のデータ

        $allowedFields = ['email', 'phone_number', 'address', 'self_introduction'];

        if (!in_array($field, $allowedFields)) {
            return response()->json(['error' => '更新できません'], 400);
        }

        $targetUser->$field = $validated['value']; // ※ $validated は ['value' => 入力値] の形
        $targetUser->save();

        return response()->json(['message' => '更新完了']);
    }
}
