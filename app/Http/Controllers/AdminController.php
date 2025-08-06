<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminController extends Controller
{
    public function dashboard()
    {
        return view('admin.dashboard');
    }

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
            'role'              => 'general',//登録時の誤設定を防ぐ目的で、権限は後から変更できる仕様とする。
            'profile_picture'   => $defaultPictures[$request->gender],
            'self_introduction' => 'こんにちは、' . $request->name . 'です。',
        ]);

        return redirect()->route('admin.dashboard')->with("status", "登録成功");
    }
}
