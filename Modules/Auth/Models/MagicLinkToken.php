<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class MagicLinkToken extends Model
{
    use HasFactory, CentralConnection;

    /**
     * The table associated with the model.
     */
    protected $table = 'magic_link_tokens';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'email',
        'token',
        'type',
        'expires_at',
        'used_at',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'metadata' => 'json',
        ];
    }

    /**
     * Check if token is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if token is used
     */
    public function isUsed(): bool
    {
        return !is_null($this->used_at);
    }

    /**
     * Check if token is valid (not used and not expired)
     */
    public function isValid(): bool
    {
        return !$this->isUsed() && !$this->isExpired();
    }

    /**
     * Mark token as used
     */
    public function markAsUsed(?string $ipAddress = null, ?string $userAgent = null): bool
    {
        $data = ['used_at' => now()];
        
        if ($ipAddress) {
            $data['ip_address'] = $ipAddress;
        }
        
        if ($userAgent) {
            $data['user_agent'] = $userAgent;
        }

        return $this->update($data);
    }

    /**
     * Generate a secure token
     */
    public static function generateToken(): string
    {
        return hash('sha256', Str::random(60) . microtime());
    }

    /**
     * Create a magic link token
     */
    public static function createToken(
        string $email,
        string $type = 'login',
        int $expiresInMinutes = 15,
        array $metadata = []
    ): self {
        // Revoke existing unused tokens of the same type for this email
        static::where('email', $email)
              ->where('type', $type)
              ->whereNull('used_at')
              ->delete();

        return static::create([
            'email' => $email,
            'token' => static::generateToken(),
            'type' => $type,
            'expires_at' => now()->addMinutes($expiresInMinutes),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Find valid token
     */
    public static function findValidToken(string $token, string $type = 'login'): ?self
    {
        return static::where('token', $token)
                    ->where('type', $type)
                    ->whereNull('used_at')
                    ->where('expires_at', '>', now())
                    ->first();
    }

    /**
     * Scope for valid tokens
     */
    public function scopeValid($query)
    {
        return $query->whereNull('used_at')
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
     * Scope for specific type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for specific email
     */
    public function scopeForEmail($query, string $email)
    {
        return $query->where('email', $email);
    }

    /**
     * Clean up expired tokens
     */
    public static function cleanupExpired(): int
    {
        return static::expired()->delete();
    }

    /**
     * Clean up used tokens older than specified days
     */
    public static function cleanupUsed(int $daysOld = 7): int
    {
        return static::whereNotNull('used_at')
                    ->where('used_at', '<', now()->subDays($daysOld))
                    ->delete();
    }

    /**
     * Get metadata value
     */
    public function getMetadata(string $key, $default = null)
    {
        return data_get($this->metadata, $key, $default);
    }

    /**
     * Set metadata value
     */
    public function setMetadata(string $key, $value): bool
    {
        $metadata = $this->metadata ?? [];
        data_set($metadata, $key, $value);
        
        return $this->update(['metadata' => $metadata]);
    }
}
