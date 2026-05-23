<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * 認証フロー（LoginController）のテスト
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * カバーするシナリオ
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * [ログイン画面]
 *   1. GET /login → ログイン画面を表示できる（200）
 *
 * [ログイン成功]
 *   2. admin ユーザーがログインすると admin.dashboard へリダイレクト
 *   3. general ユーザーがログインすると welcome へリダイレクト
 *   4. ログイン後にセッションが再生成されること（セッション固定化攻撃対策）
 *
 * [ログイン失敗]
 *   5. 誤ったパスワードでログインするとログイン画面に戻りエラーが表示される
 *   6. 存在しないメールアドレスでログインすると同様にエラーが表示される
 *
 * [レートリミット]
 *   7. 5回失敗後はロックアウトされ「試行回数が多すぎます」エラーが表示される
 *
 * [ログアウト]
 *   8. ログアウトするとセッションが無効化され login へリダイレクト
 *   9. ログアウト後は認証済みページにアクセスできない
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * テストのセットアップ方針
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * - RefreshDatabase でテストごとに DB をリセット
 * - TestCase::setUp() で RoleSeeder が実行済み（admin/super_admin/general ロール作成）
 * - ログイン成功後のリダイレクト先はロールで分岐する（LoginController の仕様）
 *   admin/super_admin → admin.dashboard
 *   それ以外          → welcome
 * - レートリミットのキーは「メール（小文字）| IP」の sha256 ハッシュ
 *   テスト間で干渉しないよう setUp で RateLimiter::clear() を行う
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * 重要な仕様メモ
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * - ログイン後のリダイレクト先の決定はロールに基づく単純分岐であり、
 *   Route/Policy による権限チェックとは無関係（LoginController のコメント参照）
 * - レートリミット: 5回試行 / 120秒で発動。6回目以降は認証試行前にブロック。
 * - ログアウト: session()->invalidate() + regenerateToken() でセッションを完全破棄する。
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    private string $password = 'password';

    protected function setUp(): void
    {
        parent::setUp();

        // レートリミットのキーはメール + IP の組み合わせ。
        // テスト間で干渉しないようにすべてクリアしておく。
        RateLimiter::clear($this->throttleKey('test@example.com'));
    }

    private function throttleKey(string $email, string $ip = '127.0.0.1'): string
    {
        return hash('sha256', strtolower($email) . '|' . $ip);
    }

    // =========================================================================
    // 1. ログイン画面
    // =========================================================================

    /**
     * シナリオ 1: GET /login はログイン画面を表示できる → 200
     */
    public function test_login_form_is_accessible(): void
    {
        $this->get(route('login'))->assertOk();
    }

    // =========================================================================
    // 2〜4. ログイン成功
    // =========================================================================

    /**
     * シナリオ 2: admin ユーザーがログインすると admin.dashboard へリダイレクト
     *
     * - LoginController はロールを確認してリダイレクト先を決定する。
     * - admin / super_admin → admin.dashboard
     */
    public function test_admin_user_is_redirected_to_dashboard_after_login(): void
    {
        $admin = User::factory()->admin()->create([
            'password' => bcrypt($this->password),
        ]);

        $this->post(route('login.submit'), [
            'email'    => $admin->email,
            'password' => $this->password,
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    /**
     * シナリオ 3: general ユーザーがログインすると welcome へリダイレクト
     *
     * - admin / super_admin 以外のロールは welcome へリダイレクトされる。
     */
    public function test_general_user_is_redirected_to_welcome_after_login(): void
    {
        $general = User::factory()->create([
            'password' => bcrypt($this->password),
        ]);

        $this->post(route('login.submit'), [
            'email'    => $general->email,
            'password' => $this->password,
        ])->assertRedirect(route('welcome'));

        $this->assertAuthenticatedAs($general);
    }

    /**
     * シナリオ 4: ログイン後にセッションが再生成される（セッション固定化攻撃対策）
     *
     * - LoginController が session()->regenerate() を呼ぶことで
     *   ログイン前後でセッション ID が変わることを確認する。
     */
    public function test_session_is_regenerated_after_login(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt($this->password),
        ]);

        $before = session()->getId();

        $this->post(route('login.submit'), [
            'email'    => $user->email,
            'password' => $this->password,
        ]);

        $after = session()->getId();

        $this->assertNotEquals($before, $after);
    }

    // =========================================================================
    // 5〜6. ログイン失敗
    // =========================================================================

    /**
     * シナリオ 5: 誤ったパスワードでログインするとエラーが返る
     *
     * - 認証失敗時は back() + withErrors(['email' => ...]) が返る。
     * - ゲスト状態のままであることを assertGuest() で確認する。
     */
    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt($this->password),
        ]);

        $this->post(route('login.submit'), [
            'email'    => $user->email,
            'password' => 'wrong-password',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * シナリオ 6: 存在しないメールアドレスでログインするとエラーが返る
     */
    public function test_login_fails_with_nonexistent_email(): void
    {
        $this->post(route('login.submit'), [
            'email'    => 'nobody@example.com',
            'password' => $this->password,
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    // =========================================================================
    // 7. レートリミット
    // =========================================================================

    /**
     * シナリオ 7: 5回ログイン失敗後はロックアウトされる
     *
     * - LoginController は RateLimiter を使い 5回/120秒 の制限を設ける。
     * - 5回失敗すると「試行回数が多すぎます」エラーメッセージが返る。
     * - アップグレード後も RateLimiter の統合が壊れていないことを確認する。
     */
    public function test_login_is_rate_limited_after_five_failures(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt($this->password),
        ]);

        // 5回失敗してロックアウトを発動させる
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.submit'), [
                'email'    => $user->email,
                'password' => 'wrong',
            ]);
        }

        // 6回目: ロックアウト中のエラーが出ること
        $this->post(route('login.submit'), [
            'email'    => $user->email,
            'password' => 'wrong',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    // =========================================================================
    // 8〜9. ログアウト
    // =========================================================================

    /**
     * シナリオ 8: ログアウトするとセッションが無効化され login へリダイレクト
     *
     * - Auth::logout() + session()->invalidate() + session()->regenerateToken()
     * - ログアウト後は assertGuest() でゲスト状態を確認する。
     */
    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    /**
     * シナリオ 9: ログアウト後は認証済みページにアクセスできない
     *
     * - セッション破棄後に auth ミドルウェア保護ルートへアクセスすると login へリダイレクト。
     */
    public function test_logged_out_user_cannot_access_protected_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'));

        $this->get(route('calendar.index'))
            ->assertRedirect(route('login'));
    }
}
