<?php

namespace Modules\Tenant\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\TenantPivot;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\User\Models\CentralUser;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    /**
     * Users relationship for synced resources
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            CentralUser::class,
            'tenant_users',
            'tenant_id',
            'global_user_id',
            'id',
            'global_id'
        )->using(TenantPivot::class);
    }
}
