<?php

namespace Modules\Tenant\Services;

use Modules\User\Models\CentralUser;
use Modules\User\Models\User;
use Modules\Tenant\app\Models\Tenant;
use Modules\RolesAndPermissions\Services\HybridRbacService;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Facades\Tenancy;

class UserAttachmentService
{
    protected HybridRbacService $rbacService;

    public function __construct(HybridRbacService $rbacService)
    {
        $this->rbacService = $rbacService;
    }

    /**
     * Attach central user to tenant with default role
     */
    public function attachUserToTenant(CentralUser $centralUser, Tenant $tenant, string $role = 'member'): array
    {
        try {
            // Check if user is already attached to tenant
            if ($tenant->users()->where('central_users.id', $centralUser->id)->exists()) {
                return [
                    'status' => 'error',
                    'message' => 'User is already attached to this tenant'
                ];
            }

            // Attach user to tenant via pivot table
            $tenant->users()->attach($centralUser->id, [
                'attached_at' => now(),
                'invited_by' => auth()->id() ?? null,
            ]);

            // Switch to tenant context to create tenant user and assign role
            Tenancy::initialize($tenant);

            // Check if tenant user exists (should be created by sync)
            $tenantUser = User::where('global_id', $centralUser->global_id)->first();

            if (!$tenantUser) {
                // Create tenant user if sync hasn't happened yet
                $tenantUser = User::create([
                    'name' => $centralUser->name,
                    'email' => $centralUser->email,
                    'global_id' => $centralUser->global_id,
                    'email_verified_at' => $centralUser->email_verified_at,
                    'status' => $centralUser->status,
                    'password' => $centralUser->password, // Password not used in tenant context
                ]);
            }

            // Assign default role in tenant
            $this->rbacService->assignRoleToUser($tenantUser, $role);

            Tenancy::end();

            return [
                'status' => 'success',
                'message' => 'User successfully attached to tenant',
                'data' => [
                    'tenant_id' => $tenant->id,
                    'user_id' => $centralUser->id,
                    'role' => $role
                ]
            ];

        } catch (\Exception $e) {
            // Ensure we end tenancy context in case of error
            if (Tenancy::initialized()) {
                Tenancy::end();
            }

            return [
                'status' => 'error',
                'message' => 'Failed to attach user to tenant: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Detach user from tenant
     */
    public function detachUserFromTenant(CentralUser $centralUser, Tenant $tenant): array
    {
        try {
            // Check if user is attached to tenant
            if (!$tenant->users()->where('central_users.id', $centralUser->id)->exists()) {
                return [
                    'status' => 'error',
                    'message' => 'User is not attached to this tenant'
                ];
            }

            // Switch to tenant context to remove roles
            Tenancy::initialize($tenant);

            $tenantUser = User::where('global_id', $centralUser->global_id)->first();
            if ($tenantUser) {
                // Remove all roles from user in this tenant
                $tenantUser->roles()->detach();
                
                // Optionally soft delete the tenant user
                $tenantUser->delete();
            }

            Tenancy::end();

            // Detach from pivot table
            $tenant->users()->detach($centralUser->id);

            return [
                'status' => 'success',
                'message' => 'User successfully detached from tenant'
            ];

        } catch (\Exception $e) {
            if (Tenancy::initialized()) {
                Tenancy::end();
            }

            return [
                'status' => 'error',
                'message' => 'Failed to detach user from tenant: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get user's tenants
     */
    public function getUserTenants(CentralUser $centralUser)
    {
        return $centralUser->tenants()->get();
    }

    /**
     * Get tenant's users with their roles
     */
    public function getTenantUsers(Tenant $tenant)
    {
        $users = $tenant->users()->get();
        $tenantUsers = collect();

        // Switch to tenant context to get roles
        Tenancy::initialize($tenant);

        foreach ($users as $centralUser) {
            $tenantUser = User::where('global_id', $centralUser->global_id)->first();
            
            $userData = [
                'id' => $centralUser->id,
                'global_id' => $centralUser->global_id,
                'name' => $centralUser->name,
                'email' => $centralUser->email,
                'status' => $centralUser->status,
                'attached_at' => $centralUser->pivot->attached_at,
                'roles' => $tenantUser ? $tenantUser->roles->pluck('name') : [],
            ];

            $tenantUsers->push($userData);
        }

        Tenancy::end();

        return $tenantUsers;
    }

    /**
     * Invite user to tenant by email
     */
    public function inviteUserToTenant(string $email, Tenant $tenant, string $role = 'member'): array
    {
        try {
            // Find central user by email
            $centralUser = CentralUser::where('email', $email)->first();

            if (!$centralUser) {
                return [
                    'status' => 'error',
                    'message' => 'User not found in the system'
                ];
            }

            // Check if user email is verified
            if (!$centralUser->hasVerifiedEmail()) {
                return [
                    'status' => 'error',
                    'message' => 'User email is not verified'
                ];
            }

            // Attach user to tenant
            return $this->attachUserToTenant($centralUser, $tenant, $role);

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Failed to invite user: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update user role in tenant
     */
    public function updateUserRole(CentralUser $centralUser, Tenant $tenant, string $newRole): array
    {
        try {
            // Check if user is attached to tenant
            if (!$tenant->users()->where('central_users.id', $centralUser->id)->exists()) {
                return [
                    'status' => 'error',
                    'message' => 'User is not attached to this tenant'
                ];
            }

            // Switch to tenant context
            Tenancy::initialize($tenant);

            $tenantUser = User::where('global_id', $centralUser->global_id)->first();
            
            if (!$tenantUser) {
                Tenancy::end();
                return [
                    'status' => 'error',
                    'message' => 'Tenant user not found'
                ];
            }

            // Remove existing roles and assign new role
            $tenantUser->roles()->detach();
            $this->rbacService->assignRoleToUser($tenantUser, $newRole);

            Tenancy::end();

            return [
                'status' => 'success',
                'message' => 'User role updated successfully',
                'data' => [
                    'user_id' => $centralUser->id,
                    'tenant_id' => $tenant->id,
                    'new_role' => $newRole
                ]
            ];

        } catch (\Exception $e) {
            if (Tenancy::initialized()) {
                Tenancy::end();
            }

            return [
                'status' => 'error',
                'message' => 'Failed to update user role: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check if user can access tenant
     */
    public function userCanAccessTenant(CentralUser $centralUser, Tenant $tenant): bool
    {
        return $tenant->users()->where('central_users.id', $centralUser->id)->exists();
    }

    /**
     * Get user's role in specific tenant
     */
    public function getUserRoleInTenant(CentralUser $centralUser, Tenant $tenant): ?string
    {
        try {
            if (!$this->userCanAccessTenant($centralUser, $tenant)) {
                return null;
            }

            Tenancy::initialize($tenant);

            $tenantUser = User::where('global_id', $centralUser->global_id)->first();
            $role = $tenantUser?->roles()->first()?->name;

            Tenancy::end();

            return $role;
        } catch (\Exception $e) {
            if (Tenancy::initialized()) {
                Tenancy::end();
            }
            return null;
        }
    }
}
