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
}
