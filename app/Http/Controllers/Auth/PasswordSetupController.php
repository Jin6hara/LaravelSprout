<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordSetupToken;
use App\Models\User;
use App\Notifications\PasswordSetupNotification;
use App\Services\Auth\PasswordSetupTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PasswordSetupController extends Controller
{
    public function __construct(
        private PasswordSetupTokenService $tokens
    ) {}

    public function requestReset(): View
    {
        return view('auth.password_request');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
        ], [
            'login.required' => 'Please enter your email address or employee code.',
            'login.string' => 'Please enter a valid email address or employee code.',
        ]);

        $user = $this->findUserByLogin($validated['login']);

        if ($user !== null && filled($user->email)) {
            $issued = $this->tokens->issue($user, PasswordSetupToken::PURPOSE_RESET);
            $user->notify(new PasswordSetupNotification(
                $issued['plain'],
                PasswordSetupToken::PURPOSE_RESET,
                $issued['token']->expires_at,
            ));
        }

        return back()->with('toast', 'If a matching account is found, a password reset email will be sent.');
    }

    public function sendInvite(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        if (blank($user->email)) {
            return back()->with('toast_errors', ['This user does not have an email address, so an invitation email cannot be sent.']);
        }

        $issued = $this->tokens->issue($user, PasswordSetupToken::PURPOSE_INVITE);
        $user->notify(new PasswordSetupNotification(
            $issued['plain'],
            PasswordSetupToken::PURPOSE_INVITE,
            $issued['token']->expires_at,
        ));

        return back()->with('toast', 'Invitation email sent.');
    }

    public function show(Request $request): View|RedirectResponse
    {
        $plainToken = (string) $request->query('token', '');
        $setupToken = $this->tokens->findValid($plainToken);

        if ($setupToken === null) {
            return redirect()
                ->route('login')
                ->with('toast_errors', ['This link is invalid or has expired.']);
        }

        return view('auth.password_setup', [
            'token' => $plainToken,
            'setupToken' => $setupToken,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'token.required' => 'The token is missing. Please open the link from your email again.',
            'token.string' => 'The token format is invalid.',
            'password.required' => 'Please enter a new password.',
            'password.string' => 'Please enter a valid password.',
            'password.min' => 'The new password must be at least 8 characters.',
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        $user = DB::transaction(function () use ($validated) {
            $setupToken = PasswordSetupToken::query()
                ->where('token_hash', PasswordSetupToken::hashToken($validated['token']))
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if ($setupToken === null) {
                return null;
            }

            $user = $setupToken->user;

            if ($user === null) {
                return null;
            }

            $user->forceFill([
                'password' => Hash::make($validated['password']),
            ])->save();

            $setupToken->forceFill([
                'used_at' => now(),
            ])->save();

            PasswordSetupToken::query()
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->delete();

            Log::info('password_reset_completed', [
                'user_id' => $user->id,
                'purpose' => $setupToken->purpose,
            ]);

            return $user;
        });

        if ($user === null) {
            return redirect()
                ->route('login')
                ->with('toast_errors', ['This link is invalid or has expired.']);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('welcome')->with('toast', 'Your password has been updated.');
    }

    private function findUserByLogin(string $login): ?User
    {
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'employee_code';

        return User::query()->where($field, $login)->first();
    }
}
