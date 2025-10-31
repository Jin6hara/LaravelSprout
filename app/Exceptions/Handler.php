<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Auth\Access\AuthorizationException; // Laravel 標準の Gate/Policy 権限チェック用の例外
use Spatie\Permission\Exceptions\UnauthorizedException; // Spatie Permission パッケージの権限チェック用の例外

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $exception)
    {
        if ($exception instanceof TokenMismatchException) {
            if (Auth::check()) {
                // 既にログイン済み → ダブルログインでトークン無効になったケース
                return redirect()->route('welcome')->with('status', 'すでにログインしています。');
            } else {
                // 通常のセッション切れ
                return redirect()->route('welcome')->with('status', 'すでにログアウトしています。');
            }
        }

        if ($exception instanceof AuthorizationException) {
            // もともとCheckRoleで対応できたので不要になった。今はSpatieで対応なのでstill不要。
            // ファイル名に余計な'.'が入っているためpolicy呼び出せなかったときもこれが表示される。
            return redirect()
                ->route('welcome')
                ->with('status', '403 This action is unauthorized.');
        }

        if ($exception instanceof UnauthorizedException) {
            return redirect()
                ->route('welcome')
                ->with('status', '指定されたページにアクセスする権限がありません。');
        }

        return parent::render($request, $exception);
    }
}
