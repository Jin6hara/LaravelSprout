<?php

namespace App\Services\Auth;

use App\Models\PasswordSetupToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PasswordSetupTokenService
{
    private const INVITE_EXPIRES_HOURS = 72;
    private const RESET_EXPIRES_MINUTES = 60;

    /**
     * @return array{token: PasswordSetupToken, plain: string}
     */
    public function issue(User $user, string $purpose): array
    {
        $expiresAt = match ($purpose) {
            PasswordSetupToken::PURPOSE_INVITE => now()->addHours(self::INVITE_EXPIRES_HOURS),
            PasswordSetupToken::PURPOSE_RESET => now()->addMinutes(self::RESET_EXPIRES_MINUTES),
            default => throw new \InvalidArgumentException('Invalid password setup token purpose.'),
        };

        return DB::transaction(function () use ($user, $purpose, $expiresAt) {
            PasswordSetupToken::query()
                ->where('user_id', $user->id)
                ->where('purpose', $purpose)
                ->whereNull('used_at')
                ->delete();

            do {
                $plain = Str::random(64);
                $hash = PasswordSetupToken::hashToken($plain);
            } while (PasswordSetupToken::query()->where('token_hash', $hash)->exists());

            $token = PasswordSetupToken::create([
                'user_id' => $user->id,
                'token_hash' => $hash,
                'purpose' => $purpose,
                'expires_at' => $expiresAt,
            ]);

            return [
                'token' => $token,
                'plain' => $plain,
            ];
        });
    }

    public function findValid(string $plainToken): ?PasswordSetupToken
    {
        return PasswordSetupToken::query()
            ->with('user')
            ->where('token_hash', PasswordSetupToken::hashToken($plainToken))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }
}
