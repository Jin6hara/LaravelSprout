<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function showForm()
    {
        //ログイン済みの場合はログイン画面表示させない設計
        //if (Auth::check()) {
            //return redirect()->route('welcome')->with('status', 'すでにログイン中です');
        //}

        return view('auth.login');
    }

    public function login(Request $request)
    {
        //ログアウトしてから再認証
        //Auth::logout();
        //$request->session()->invalidate();
        //$request->session()->regenerateToken();

        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            $user = Auth::user();
            // ここは権限認証とまったく関係ない。本認証はRoute+Policyに行っている。
            // ここでは、ユーザーロールを取得してredirect先を決めているだけ。
            if ($user->hasRole(['admin', 'super_admin'])) { 
                return redirect()->route('admin.dashboard');//role check required
            } else {
                return redirect()->route('welcome');//role check none required
            }
        }

        return back()->withErrors([
            'email' => 'メールアドレスまたはパスワードが正しくありません。',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
