<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasswordSetupToken extends Model
{
    public const PURPOSE_INVITE = 'invite';
    public const PURPOSE_RESET = 'reset';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'token_hash',
        'purpose',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
