<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use Modules\User\Models\CentralUser;

class JwtRefreshToken extends Model
{
    use HasFactory, CentralConnection;

    /**
     * The table associated with the model.
     */
    protected $table = 'jwt_refresh_tokens';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'token_id',
        'jti',
        'expires_at',
        'last_used_at',
        'ip_address',
        'user_agent',
        'is_revoked',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'is_revoked' => 'boolean',
        ];
    }

    /**
     * User relationship
     */
    public function user()
    {
        return $this->belongsTo(CentralUser::class);
    }

    /**
     * Check if token is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if token is valid (not revoked and not expired)
     */
    public function isValid(): bool
    {
        return !$this->is_revoked && !$this->isExpired();
    }

    /**
     * Revoke the token
     */
    public function revoke(): bool
    {
        return $this->update(['is_revoked' => true]);
    }

    /**
     * Update last used timestamp
     */
    public function markAsUsed(?string $ipAddress = null, ?string $userAgent = null): bool
    {
        $data = ['last_used_at' => now()];
        
        if ($ipAddress) {
            $data['ip_address'] = $ipAddress;
        }
        
        if ($userAgent) {
            $data['user_agent'] = $userAgent;
        }

        return $this->update($data);
    }

    /**
     * Scope for valid tokens
     */
    public function scopeValid($query)
    {
        return $query->where('is_revoked', false)
                    ->where('expires_at', '>', now());
    }

    /**
     * Scope for expired tokens
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope for user tokens
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Generate unique token ID
     */
    public static function generateTokenId(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Clean up expired tokens
     */
    public static function cleanupExpired(): int
    {
        return static::expired()->delete();
    }

    /**
     * Revoke all tokens for a user
     */
    public static function revokeAllForUser(int $userId): int
    {
        return static::forUser($userId)->update(['is_revoked' => true]);
    }
}
