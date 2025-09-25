<?php

namespace Modules\RolesAndPermissions\app\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    /**
     * The connection name for the model.
     *
     * @var string|null
     */
    protected $connection = 'central';
}
