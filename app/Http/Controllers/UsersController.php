<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Validation\ValidationException;


class UsersController extends Controller
{
    public function showProfile(?User $user = null)
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
    public function updateField(Request $request, ?User $user = null)
    {
        $currentUser = Auth::user();

        // userがnull → 自分のプロフィール（一般ユーザー）
        $targetUser = $user ?? $currentUser;

        // 権限チェック
        $this->authorize('view', $targetUser);

        $field = $request->input('field');

        $allowedFields = ['email', 'phone_number', 'address', 'self_introduction'];

        if (!in_array($field, $allowedFields)) {
            return response()->json(['error' => '更新できません'], 400);
        }

        $rules = match ($field) {
            'email' => ['value' => "required|email|max:255|unique:users,email,{$targetUser->id}"],
            'phone_number' => ['value' => 'nullable|numeric|digits_between:10,11'],
            'address' => ['value' => 'nullable|string|max:255'],
            'self_introduction' => ['value' => 'nullable|string|max:1000'],
        };

        // 以前はバリデーションせずに直接取得していた：$value = $request->input('value');
        // → バリデーションエラーをJSON形式で返すよう、try-catch + validate() に変更
        try {
            $validated = $request->validate($rules);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => $e->errors()['value'][0] ?? '不正な値です'
            ], 422);
        }

        // 以前は $targetUser->$field = $value; のように使っていたが、
        // validate() で取得した値を使用するため $validated['value'] に変更
        $targetUser->$field = $validated['value']; // ※ $validated は ['value' => 入力値] の形
        $targetUser->save();

        return response()->json(['message' => '更新完了']);
    }
}
