<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $key          = $this->throttleKey($request);
        $maxAttempts  = 5;
        $decaySeconds = 120;

        // ① すでにロックアウト中（6回目以降）は認証処理の前にブロック
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors([
                'email' => "試行回数が多すぎます。{$seconds}秒後に再試行してください。",
            ])->onlyInput('email');
        }

        // ② 認証試行
        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            RateLimiter::clear($key);

            $user = Auth::user();
            // ここは権限認証とまったく関係ない。本認証はRoute+Policyに行っている。
            // ここでは、ユーザーロールを取得してredirect先を決めているだけ。
            if ($user->hasRole(['admin', 'super_admin'])) {
                return redirect()->route('admin.dashboard');
            } else {
                return redirect()->route('welcome');
            }
        }

        // ③ 失敗: カウンタをインクリメントしてから超過判定
        RateLimiter::hit($key, $decaySeconds);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors([
                'email' => "試行回数が多すぎます。{$seconds}秒後に再試行してください。",
            ])->onlyInput('email');
        }

        return back()->withErrors([
            'email' => 'メールアドレスまたはパスワードが正しくありません。',
        ])->onlyInput('email');
    }

    private function throttleKey(Request $request): string
    {
        return hash('sha256', strtolower($request->input('email', '')) . '|' . $request->ip());
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
