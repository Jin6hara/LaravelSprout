<?php

namespace Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Handler.php の例外 → リダイレクト動作テスト
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * カバーするシナリオ
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *   1. AuthorizationException（Laravel標準）→ welcome へリダイレクト（302）
 *   2. UnauthorizedException（Spatie）→ welcome へリダイレクト（302）
 *   3. 一般的な例外（RuntimeException）→ Handler がリダイレクトしない（500）
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * テストのセットアップ方針
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * - 各テスト専用のダミールートを登録し、そこで例外を意図的に投げる。
 * - Handler.php::render() の独自ロジックを直接テストするため、
 *   特定のコントローラーに依存しない最小構成にする。
 * - Laravel アップグレード（v10→v11→v12）で Handler の登録方式が
 *   bootstrap/app.php に移行しても、このテストが通ることで
 *   独自リダイレクトロジックの生存を確認できる。
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * 重要な仕様メモ
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * - AuthorizationException  → Handler が welcome へリダイレクト
 *   with('status', '403 This action is unauthorized.')
 * - UnauthorizedException   → Handler が welcome へリダイレクト
 *   with('status', '指定されたページにアクセスする権限がありません。')
 * - それ以外の Throwable    → parent::render() に委譲（Handler はリダイレクトしない）
 */
class ExceptionHandlerTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // 1. AuthorizationException → welcome へリダイレクト
    // =========================================================================

    /**
     * シナリオ 1: AuthorizationException が投げられると welcome へリダイレクトされる
     *
     * - Laravel 標準の Gate/Policy 権限チェックが失敗したときに投げられる例外。
     * - Handler.php が独自にキャッチして redirect()->route('welcome') を返す。
     * - アップグレード後も Handler の登録が正しく機能していることを確認する。
     */
    public function test_authorization_exception_redirects_to_welcome(): void
    {
        Route::get('/_test/authz', function () {
            throw new AuthorizationException('forbidden');
        });

        $this->get('/_test/authz')
            ->assertRedirect(route('welcome'));
    }

    // =========================================================================
    // 2. UnauthorizedException（Spatie）→ welcome へリダイレクト
    // =========================================================================

    /**
     * シナリオ 2: UnauthorizedException（Spatie）が投げられると welcome へリダイレクトされる
     *
     * - Spatie Permission の 'role:xxx' ミドルウェアが権限不足を検出したときに投げる例外。
     * - Handler.php が独自にキャッチして redirect()->route('welcome') を返す。
     * - アップグレード後も Handler の登録が正しく機能していることを確認する。
     */
    public function test_spatie_unauthorized_exception_redirects_to_welcome(): void
    {
        Route::get('/_test/spatie', function () {
            throw UnauthorizedException::forRoles(['admin']);
        });

        $this->get('/_test/spatie')
            ->assertRedirect(route('welcome'));
    }

    // =========================================================================
    // 3. 一般的な例外 → Handler がリダイレクトしない
    // =========================================================================

    /**
     * シナリオ 3: 一般的な例外（RuntimeException）は Handler がリダイレクトせず 500 を返す
     *
     * - Handler の独自ロジックは AuthorizationException / UnauthorizedException のみを対象とする。
     * - それ以外の例外は parent::render() に委譲されるため 500 が返る。
     * - このシナリオにより「例外を何でも握りつぶしていないこと」を確認する。
     */
    public function test_generic_exception_is_not_redirected(): void
    {
        Route::get('/_test/runtime', function () {
            throw new \RuntimeException('something went wrong');
        });

        $this->get('/_test/runtime')
            ->assertStatus(500);
    }
}
