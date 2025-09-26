<?php

namespace Modules\RolesAndPermissions\Services;

use Modules\RolesAndPermissions\app\Models\Permission;
use Modules\RolesAndPermissions\app\Models\Role;
use Modules\User\Models\User;
use Modules\User\Models\CentralUser;
use Illuminate\Support\Collection;

class HybridRbacService
{
    /**
     * Check if user has permission (hybrid: central permissions + tenant roles)
     */
    public function userHasPermission(User $tenantUser, string $permissionName): bool
    {
        // Get user's roles from tenant database
        $userRoles = $tenantUser->roles;
        
        if ($userRoles->isEmpty()) {
            return false;
        }

        // Get permission from central database
        $permission = Permission::where('name', $permissionName)->first();
        
        if (!$permission) {
            return false;
        }

        // Check if any of the user's roles have this permission
        foreach ($userRoles as $role) {
            if ($role->hasPermissionTo($permissionName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has any of the given permissions
     */
    public function userHasAnyPermission(User $tenantUser, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->userHasPermission($tenantUser, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all of the given permissions
     */
    public function userHasAllPermissions(User $tenantUser, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->userHasPermission($tenantUser, $permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if user has role in current tenant
     */
    public function userHasRole(User $tenantUser, string $roleName): bool
    {
        return $tenantUser->hasRole($roleName);
    }

    /**
     * Check if user has any of the given roles
     */
    public function userHasAnyRole(User $tenantUser, array $roles): bool
    {
        return $tenantUser->hasAnyRole($roles);
    }

    /**
     * Assign role to user in current tenant
     */
    public function assignRoleToUser(User $tenantUser, string $roleName): bool
    {
        try {
            $role = Role::findByName($roleName);
            if (!$role) {
                return false;
            }

            $tenantUser->assignRole($role);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Remove role from user in current tenant
     */
    public function removeRoleFromUser(User $tenantUser, string $roleName): bool
    {
        try {
            $tenantUser->removeRole($roleName);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get all permissions available in central database
     */
    public function getAllPermissions(): Collection
    {
        return Permission::all();
    }

    /**
     * Get permissions by module
     */
    public function getPermissionsByModule(string $module): Collection
    {
        return Permission::forModule($module)->get();
    }

    /**
     * Get user's effective permissions (from tenant roles)
     */
    public function getUserPermissions(User $tenantUser): Collection
    {
        $permissions = collect();
        
        foreach ($tenantUser->roles as $role) {
            $rolePermissions = $role->permissions;
            $permissions = $permissions->merge($rolePermissions);
        }

        return $permissions->unique('id');
    }

    /**
     * Create role in tenant database with central permissions
     */
    public function createRoleWithPermissions(string $roleName, array $permissionNames, ?string $description = null): ?Role
    {
        try {
            // Create role in tenant database
            $role = Role::create([
                'name' => $roleName,
                'guard_name' => 'api',
                'description' => $description,
            ]);

            // Validate permissions exist in central database
            $validPermissions = Permission::whereIn('name', $permissionNames)->get();
            
            if ($validPermissions->count() !== count($permissionNames)) {
                $role->delete();
                return null;
            }

            // Assign permissions to role (permissions are referenced by name from central DB)
            $role->givePermissionTo($permissionNames);

            return $role;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Update role permissions
     */
    public function updateRolePermissions(string $roleName, array $permissionNames): bool
    {
        try {
            $role = Role::findByName($roleName);
            if (!$role) {
                return false;
            }

            // Validate permissions exist in central database
            $validPermissions = Permission::whereIn('name', $permissionNames)->get();
            
            if ($validPermissions->count() !== count($permissionNames)) {
                return false;
            }

            // Sync permissions (removes old ones and adds new ones)
            $role->syncPermissions($permissionNames);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if user is owner of the tenant
     */
    public function isOwner(User $tenantUser): bool
    {
        return $this->userHasRole($tenantUser, 'owner');
    }

    /**
     * Check if user is admin of the tenant
     */
    public function isAdmin(User $tenantUser): bool
    {
        return $this->userHasAnyRole($tenantUser, ['owner', 'admin']);
    }

    /**
     * Check if user can manage other users
     */
    public function canManageUsers(User $tenantUser): bool
    {
        return $this->userHasAnyPermission($tenantUser, [
            'Users.users.create',
            'Users.users.update',
            'Users.users.delete',
            'Users.users.invite'
        ]);
    }

    /**
     * Check if user can view analytics
     */
    public function canViewAnalytics(User $tenantUser): bool
    {
        return $this->userHasAnyPermission($tenantUser, [
            'Analytics.login_analytics.read',
            'Analytics.user_analytics.read',
            'Analytics.tenant_analytics.read'
        ]);
    }

    /**
     * Check if user can manage webhooks
     */
    public function canManageWebhooks(User $tenantUser): bool
    {
        return $this->userHasAnyPermission($tenantUser, [
            'Webhooks.webhooks.create',
            'Webhooks.webhooks.update',
            'Webhooks.webhooks.delete'
        ]);
    }

    /**
     * Get default role for new users
     */
    public function getDefaultRole(): string
    {
        return 'member';
    }

    /**
     * Get all available roles for the tenant
     */
    public function getTenantRoles(): Collection
    {
        return Role::all();
    }
}
