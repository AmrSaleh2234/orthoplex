<?php

namespace Modules\Analytics\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\User\Models\CentralUser;
use Modules\Tenant\app\Models\Tenant;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class LoginEvent extends Model
{
    use CentralConnection;

    public $timestamps = false;

    protected $fillable = [
        'central_user_id',
        'tenant_id',
        'global_user_id',
        'ip_address',
        'user_agent',
        'login_method',
        'two_factor_used',
        'session_duration',
        'login_at',
        'logout_at',
        'device_info',
        'location_info',
        'success',
        'failure_reason',
    ];

    protected $casts = [
        'login_at' => 'datetime',
        'logout_at' => 'datetime',
        'two_factor_used' => 'boolean',
        'success' => 'boolean',
        'device_info' => 'array',
        'location_info' => 'array',
        'session_duration' => 'integer',
    ];

    /**
     * Get the central user that performed this login
     */
    public function centralUser(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'central_user_id');
    }

    /**
     * Get the tenant where this login occurred
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
     * Scope to filter by user
     */
    public function scopeForUser($query, string $centralUserId)
    {
        return $query->where('central_user_id', $centralUserId);
    }

    /**
     * Scope to filter successful logins
     */
    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    /**
     * Scope to filter failed logins
     */
    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('login_at', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by login method
     */
    public function scopeByMethod($query, string $method)
    {
        return $query->where('login_method', $method);
    }
}
