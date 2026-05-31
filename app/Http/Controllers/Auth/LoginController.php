<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    /**
     * ログインフォーム表示
     * GET /login — 未ログインユーザー向けのログイン画面を返す
     */
    public function showForm()
    {
        //ログイン済みの場合はログイン画面表示させない設計
        //if (Auth::check()) {
            //return redirect()->route('welcome')->with('status', 'すでにログイン中です');
        //}

        return view('auth.login');
    }

    /**
     * ログイン処理
     * POST /login — レートリミット（5回/120秒）を検証した上で認証し、ロールに応じてリダイレクト
     */
    public function login(Request $request)
    {
        $request->merge([
            'login' => $request->input('login', $request->input('email')),
        ]);

        $validated = $request->validate([
            'login'    => ['required', 'string'],
            'password' => ['required'],
        ]);

        $loginField = filter_var($validated['login'], FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'employee_code';

        $credentials = [
            $loginField => $validated['login'],
            'password' => $validated['password'],
        ];

        $key          = $this->throttleKey($request);
        $maxAttempts  = 5;
        $decaySeconds = 120;

        $ip = $request->ip();

        // ① すでにロックアウト中（6回目以降）は認証処理の前にブロック
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            Log::warning('auth.lockout_blocked', [
                'login' => $validated['login'],
                'ip'    => $ip,
                'retry_after_seconds' => $seconds,
            ]);
            return back()->withErrors([
                'login' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ])->onlyInput('login');
        }

        // ② 認証試行
        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            RateLimiter::clear($key);

            $user = Auth::user();
            // ここは権限認証とまったく関係ない。本認証はRoute+Policyに行っている。
            // ここでは、ユーザーロールを取得してredirect先を決めているだけ。
            if ($user->isAdmin()) {
                return redirect()->route('calendar.forecast');
            } else {
                return redirect()->route('welcome');
            }
        }

        // ③ 失敗: カウンタをインクリメントしてから超過判定
        RateLimiter::hit($key, $decaySeconds);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            Log::warning('auth.lockout', [
                'login' => $validated['login'],
                'ip'    => $ip,
                'retry_after_seconds' => $seconds,
            ]);
            return back()->withErrors([
                'login' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ])->onlyInput('login');
        }

        return back()->withErrors([
            'login' => 'The email, employee code, or password is incorrect.',
        ])->onlyInput('login');
    }

    /**
     * レートリミット用のキーを生成
     * ログインID（小文字）と IP アドレスの組み合わせをハッシュ化して返す
     */
    private function throttleKey(Request $request): string
    {
        $login = $request->input('login', $request->input('email', ''));

        return hash('sha256', strtolower($login) . '|' . $request->ip());
    }

    /**
     * ログアウト処理
     * POST /logout — セッションを無効化・トークン再生成した上でログイン画面へリダイレクト
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
