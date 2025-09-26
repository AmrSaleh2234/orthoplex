<?php

namespace Modules\RolesAndPermissions\app\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Permission extends SpatiePermission
{
    use CentralConnection;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'guard_name',
        'module',
        'resource',
        'action',
        'description',
        'is_global',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'is_global' => 'boolean',
        ];
    }

    /**
     * Scope for global permissions
     */
    public function scopeGlobal($query)
    {
        return $query->where('is_global', true);
    }

    /**
     * Scope for specific module
     */
    public function scopeForModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    /**
     * Scope for specific resource
     */
    public function scopeForResource($query, string $resource)
    {
        return $query->where('resource', $resource);
    }

    /**
     * Scope for specific action
     */
    public function scopeForAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Get permission by module, resource, and action
     */
    public static function findByMRA(string $module, string $resource, string $action): ?self
    {
        return static::where('module', $module)
                    ->where('resource', $resource)
                    ->where('action', $action)
                    ->first();
    }

    /**
     * Create permission with module, resource, action pattern
     */
    public static function createMRA(string $module, string $resource, string $action, ?string $description = null, string $guardName = 'web'): self
    {
        $name = "{$module}.{$resource}.{$action}";
        
        return static::create([
            'name' => $name,
            'guard_name' => $guardName,
            'module' => $module,
            'resource' => $resource,
            'action' => $action,
            'description' => $description,
            'is_global' => true,
        ]);
    }
}
