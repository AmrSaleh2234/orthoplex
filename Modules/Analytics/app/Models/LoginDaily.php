<?php

namespace Modules\Analytics\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Tenant\app\Models\Tenant;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class LoginDaily extends Model
{
    use CentralConnection;

    protected $table = 'login_daily';

    protected $fillable = [
        'date',
        'login_count',
        'successful_logins',
        'failed_logins',
        'unique_users',
        'two_factor_logins',
        'magic_link_logins',
        'password_logins',
    ];

    protected $casts = [
        'date' => 'date',
        'login_count' => 'integer',
        'successful_logins' => 'integer',
        'failed_logins' => 'integer',
        'unique_users' => 'integer',
        'two_factor_logins' => 'integer',
        'magic_link_logins' => 'integer',
        'password_logins' => 'integer',
    ];

    /**
     * Get the tenant for this daily summary
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope to filter by tenant
     */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Get success rate for the day
     */
    public function getSuccessRateAttribute(): float
    {
        if ($this->login_count === 0) {
            return 0;
        }

        return round(($this->successful_logins / $this->login_count) * 100, 2);
    }

    /**
     * Get 2FA usage rate for the day
     */
    public function getTwoFactorRateAttribute(): float
    {
        if ($this->successful_logins === 0) {
            return 0;
        }

        return round(($this->two_factor_logins / $this->successful_logins) * 100, 2);
    }
}
