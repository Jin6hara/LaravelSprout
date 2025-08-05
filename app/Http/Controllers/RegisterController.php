<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\UserRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\User;



class RegisterController extends Controller
{
    public function showForm()
    {
        return view('auth.register');
    }

    public function register(UserRequest $request)
    {

        $defaultPictures = config('user.default_profile_pictures');

        User::create([
            'name'              => $request->name,
            'email'             => $request->email,
            'employee_code'     => $request->employee_code,
            'password'          => Hash::make($request->password),
            'gender'            => $request->gender,
            'role'              => 'general',//登録時の誤設定を防ぐ目的で、権限は後から変更できる仕様としています。
            'profile_picture'   => $defaultPictures[$request->gender],
            'self_introduction' => 'こんにちは、' . $request->name . 'です。',
        ]);

        return redirect()->route('admin.dashboard')->with("status", "登録成功");
    }
}
