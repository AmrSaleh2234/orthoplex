<?php

namespace Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwoFactorAuthentication extends Model
{
    protected $table = 'two_factor_authentications';

    protected $fillable = [
        'user_id',
        'google2fa_secret',
        'google2fa_recovery_codes',
        'google2fa_enabled',
    ];

    protected $casts = [
        'google2fa_secret' => 'encrypted',
        'google2fa_recovery_codes' => 'encrypted',
        'google2fa_enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
