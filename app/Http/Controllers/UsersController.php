<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\User;


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
    public function updateField(Request $request)
    {
        $user = auth()->user();

        $field = $request->input('field');
        $value = $request->input('value');

        $allowedFields = ['email', 'phone_number', 'address', 'self_introduction'];

        if (!in_array($field, $allowedFields)) {
            return response()->json(['error' => '更新できません'], 400);
        }

        $user->$field = $value;
        $user->save();

        return response()->json(['message' => '更新完了']);
    }
}
