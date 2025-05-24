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

        $user = User::create([
            'name'              => $request->name,
            'email'             => $request->email,
            'password'          => Hash::make($request->password),
            'gender'            => $request->gender,
            'role'              => 'general',
            'profile_picture'   => $defaultPictures[$request->gender],
            'self_introduction' => 'こんにちは、' . $request->name . 'です。',
        ]);

        Auth::login($user);

        return redirect()->route('welcome');
    }
}
