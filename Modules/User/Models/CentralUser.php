<?php

namespace Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use Stancl\Tenancy\Database\Concerns\ResourceSyncing;
use Stancl\Tenancy\Database\Contracts\SyncMaster;
use Stancl\Tenancy\Database\Models\TenantPivot;
use Modules\Tenant\Models\Tenant;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Support\Str;

class CentralUser extends Authenticatable implements SyncMaster, JWTSubject
{
    use ResourceSyncing, CentralConnection, Notifiable;

    /**
     * Boot the model and set up event listeners.
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($user) {
            if (empty($user->global_id)) {
                $user->global_id = (string) Str::uuid();
            }
        });
    }

    /**
     * The table associated with the model.
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_enabled',
        'last_login_at',
        'login_count',
        'status',
        'preferences',
        'gdpr_deletion_requested_at',
        'gdpr_deletion_approved',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'gdpr_deletion_requested_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'gdpr_deletion_approved' => 'boolean',
            'preferences' => 'json',
            'two_factor_recovery_codes' => 'json',
            'password' => 'hashed',
        ];
    }

    /**
     * Relationship to tenants (for synced resources)
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(
            Tenant::class,
            'tenant_users',
            'global_user_id',
            'tenant_id',
            'global_id'
        )->using(TenantPivot::class);
    }

    /**
     * Get the tenant model name
     */
    public function getTenantModelName(): string
    {
        return User::class;
    }

    /**
     * Get the global identifier key
     */
    public function getGlobalIdentifierKey()
    {
        return $this->getAttribute($this->getGlobalIdentifierKeyName());
    }

    /**
     * Get the global identifier key name
     */
    public function getGlobalIdentifierKeyName(): string
    {
        return 'global_id';
    }

    /**
     * Get the central model name
     */
    public function getCentralModelName(): string
    {
        return static::class;
    }

    /**
     * Get the synced attribute names (only sync basic user info, not auth-specific data)
     */
    public function getSyncedAttributeNames(): array
    {
        return [
            'name',
            'email',
            'email_verified_at',
            'status', // Allow status sync for account suspension
        ];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     */
    public function getJWTCustomClaims()
    {
        return [
            'email' => $this->email,
            'name' => $this->name,
            'status' => $this->status,
            'two_factor_enabled' => $this->two_factor_enabled,
        ];
    }

    /**
     * Check if user has verified their email
     */
    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Mark email as verified
     */
    public function markEmailAsVerified(): bool
    {
        return $this->update([
            'email_verified_at' => now(),
            'status' => 'active'
        ]);
    }

    /**
     * Check if user is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if user is suspended
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Update login statistics
     */
    public function updateLoginStats(?string $ipAddress = null, ?string $userAgent = null): void
    {
        $this->update([
            'last_login_at' => now(),
            'login_count' => $this->login_count + 1,
        ]);
    }

    /**
     * Generate 2FA secret
     */
    public function generateTwoFactorSecret(): string
    {
        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $secret = $google2fa->generateSecretKey();
        
        $this->update([
            'two_factor_secret' => encrypt($secret)
        ]);

        return $secret;
    }

    /**
     * Enable 2FA
     */
    public function enableTwoFactor(array $recoveryCodes): bool
    {
        return $this->update([
            'two_factor_enabled' => true,
            'two_factor_recovery_codes' => encrypt($recoveryCodes)
        ]);
    }

    /**
     * Verify 2FA code
     */
    public function verifyTwoFactorCode(string $code): bool
    {
        if (!$this->two_factor_enabled || !$this->two_factor_secret) {
            return false;
        }

        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $secret = decrypt($this->two_factor_secret);

        return $google2fa->verifyKey($secret, $code, 2); // 2 = tolerance window
    }
}
