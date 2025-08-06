<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class UsersController extends Controller
{
    public function showProfile()
    {
        $user = Auth::user(); // ログインユーザーを取得

        $defaultPictures = config('user.default_profile_pictures');
        $gender = $user->gender;
        $user->profile_picture = $user->profile_picture ?? $defaultPictures[$gender];

        return view('user.profile', compact('user'));
    }
}
