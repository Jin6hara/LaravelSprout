<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

use Illuminate\Session\TokenMismatchException;
use Illuminate\Auth\Access\AuthorizationException;

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
            // セッション切れなどでCSRFエラーが出た場合はホームにリダイレクト
            return redirect()->route('welcome')->with('status', 'すでにログアウトしています。');
        }
        
        if ($exception instanceof AuthorizationException) {
            // もともとroleチェックに使う予定だった。とりあえず残しておく。
            return redirect()->route('home')->with('message', 'アクセス権限がありません。');
        }

        return parent::render($request, $exception);
    }
}
