<?php

namespace Tests\Feature;

use App\Models\PasswordSetupToken;
use App\Models\Department;
use App\Models\District;
use App\Models\User;
use App\Models\UserManagementScope;
use App\Notifications\PasswordSetupNotification;
use App\Services\Auth\PasswordSetupTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_send_invite_password_setup_link(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $district = District::factory()->create();
        $department = Department::factory()->create();
        $user = User::factory()->create([
            'district_id' => $district->id,
            'department_id' => $department->id,
        ]);
        UserManagementScope::factory()->create([
            'user_id' => $admin->id,
            'district_id' => $district->id,
            'department_id' => $department->id,
        ]);

        $this->actingAs($admin)
            ->post(route('password.invite.send', $user))
            ->assertRedirect()
            ->assertSessionHas('toast', 'Invitation email sent.');

        $this->assertDatabaseHas('password_setup_tokens', [
            'user_id' => $user->id,
            'purpose' => PasswordSetupToken::PURPOSE_INVITE,
            'used_at' => null,
        ]);

        Notification::assertSentTo($user, PasswordSetupNotification::class);
    }

    public function test_reset_request_sends_password_reset_link_without_revealing_user_existence(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.reset.email'), [
            'login' => $user->email,
        ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->assertDatabaseHas('password_setup_tokens', [
            'user_id' => $user->id,
            'purpose' => PasswordSetupToken::PURPOSE_RESET,
            'used_at' => null,
        ]);

        Notification::assertSentTo($user, PasswordSetupNotification::class);

        $this->post(route('password.reset.email'), [
            'login' => 'nobody@example.com',
        ])
            ->assertRedirect()
            ->assertSessionHas('toast');
    }

    public function test_password_setup_post_updates_password_and_consumes_token(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('old-password'),
        ]);

        $issued = app(PasswordSetupTokenService::class)->issue($user, PasswordSetupToken::PURPOSE_RESET);

        $this->get(route('password.setup.show', ['token' => $issued['plain']]))
            ->assertOk()
            ->assertSee('Reset Password');

        $this->post(route('password.setup.store'), [
            'token' => $issued['plain'],
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertRedirect(route('welcome'))
            ->assertSessionHas('toast', 'Your password has been updated.');

        $user->refresh();
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseMissing('password_setup_tokens', [
            'id' => $issued['token']->id,
            'used_at' => null,
        ]);

        $this->post(route('password.setup.store'), [
            'token' => $issued['plain'],
            'password' => 'another-password',
            'password_confirmation' => 'another-password',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('toast_errors');
    }
}
