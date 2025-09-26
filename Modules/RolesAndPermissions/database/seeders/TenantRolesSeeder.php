<?php

namespace Modules\RolesAndPermissions\database\seeders;

use Illuminate\Database\Seeder;
use Modules\RolesAndPermissions\Services\HybridRbacService;
use Modules\RolesAndPermissions\app\Models\Role;

class TenantRolesSeeder extends Seeder
{
    protected HybridRbacService $rbacService;

    public function __construct(HybridRbacService $rbacService)
    {
        $this->rbacService = $rbacService;
    }

    /**
     * Run the database seeds for tenant roles.
     */
    public function run(): void
    {
        // Disable Spatie permission caching to avoid tenant database access during seeding
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        
        $roles = [
            [
                'name' => 'owner',
                'description' => 'Organization owner with full administrative access',
                'permissions' => [
                    // System permissions - full access
                    'System.system.manage_all',
                    'System.audit_logs.read',
                    
                    // User management permissions
                    'Users.users.create',
                    'Users.users.read',
                    'Users.users.update',
                    'Users.users.delete',
                    'Users.users.restore',
                    'Users.users.invite',
                    'Users.users.suspend',
                    'Users.users.reactivate',
                    
                    // GDPR permissions
                    'Users.gdpr.export_data',
                    'Users.gdpr.request_deletion',
                    'Users.gdpr.approve_deletion',
                    'Users.gdpr.manage_requests',
                    
                    // Role and permission management
                    'RolesAndPermissions.roles.read',
                    'RolesAndPermissions.roles.create',
                    'RolesAndPermissions.roles.update',
                    'RolesAndPermissions.roles.delete',
                    'RolesAndPermissions.roles.assign',
                    'RolesAndPermissions.permissions.read',
                    'RolesAndPermissions.permissions.manage',
                    
                    // Analytics permissions
                    'Analytics.login_analytics.read',
                    'Analytics.user_analytics.read',
                    'Analytics.tenant_analytics.read',
                    'Analytics.reports.export',
                    
                    // Tenant management
                    'Tenant.tenants.read',
                    'Tenant.tenants.update',
                    'Tenant.tenants.manage',
                    
                    // Webhooks management
                    'Webhooks.webhooks.read',
                    'Webhooks.webhooks.create',
                    'Webhooks.webhooks.update',
                    'Webhooks.webhooks.delete',
                    'Webhooks.webhooks.test',
                ]
            ],
            [
                'name' => 'admin',
                'description' => 'Administrator with extensive management privileges',
                'permissions' => [
                    // User management permissions
                    'Users.users.create',
                    'Users.users.read',
                    'Users.users.update',
                    'Users.users.delete',
                    'Users.users.invite',
                    'Users.users.suspend',
                    'Users.users.reactivate',
                    
                    // Limited GDPR permissions
                    'Users.gdpr.export_data',
                    'Users.gdpr.request_deletion',
                    
                    // Role assignment (but not creation/deletion)
                    'RolesAndPermissions.roles.read',
                    'RolesAndPermissions.roles.assign',
                    'RolesAndPermissions.permissions.read',
                    
                    // Analytics permissions
                    'Analytics.login_analytics.read',
                    'Analytics.user_analytics.read',
                    'Analytics.tenant_analytics.read',
                    'Analytics.reports.export',
                    
                    // Tenant read access
                    'Tenant.tenants.read',
                    'Tenant.tenants.update',
                    
                    // Webhooks management
                    'Webhooks.webhooks.read',
                    'Webhooks.webhooks.create',
                    'Webhooks.webhooks.update',
                    'Webhooks.webhooks.delete',
                    'Webhooks.webhooks.test',
                ]
            ],
            [
                'name' => 'member',
                'description' => 'Regular team member with standard access',
                'permissions' => [
                    // Limited user permissions
                    'Users.users.read',
                    'Users.users.update', // Own profile only (enforced in controller)
                    
                    // GDPR self-service
                    'Users.gdpr.export_data', // Own data only
                    'Users.gdpr.request_deletion', // Own data only
                    
                    // Read-only role access
                    'RolesAndPermissions.roles.read',
                    'RolesAndPermissions.permissions.read',
                    
                    // Limited analytics
                    'Analytics.login_analytics.read', // Own data only
                    
                    // Tenant read access
                    'Tenant.tenants.read',
                    
                    // Webhook read access
                    'Webhooks.webhooks.read',
                ]
            ],
            [
                'name' => 'auditor',
                'description' => 'Read-only access for compliance and auditing',
                'permissions' => [
                    // Read-only user access
                    'Users.users.read',
                    
                    // GDPR read access
                    'Users.gdpr.export_data',
                    
                    // Read-only role and permission access
                    'RolesAndPermissions.roles.read',
                    'RolesAndPermissions.permissions.read',
                    
                    // Full analytics access (read-only)
                    'Analytics.login_analytics.read',
                    'Analytics.user_analytics.read',
                    'Analytics.tenant_analytics.read',
                    'Analytics.reports.export',
                    
                    // System audit logs
                    'System.audit_logs.read',
                    
                    // Tenant read access
                    'Tenant.tenants.read',
                    
                    // Webhook read access
                    'Webhooks.webhooks.read',
                ]
            ]
        ];

        foreach ($roles as $roleData) {
            $this->createRoleWithPermissions(
                $roleData['name'],
                $roleData['description'],
                $roleData['permissions']
            );
        }

        $this->command->info('Created ' . count($roles) . ' tenant roles with appropriate permissions.');
    }

    /**
     * Create a role with its permissions
     */
    protected function createRoleWithPermissions(string $name, string $description, array $permissions): void
    {
        // Check if role already exists
        $existingRole = Role::where('name', $name)->first();
        
        if ($existingRole) {
            $this->command->info("Role '{$name}' already exists. Updating permissions...");
            
            // Update permissions
            $this->rbacService->updateRolePermissions($name, $permissions);
            
            // Update description
            $existingRole->update(['description' => $description]);
        } else {
            // Create new role with permissions
            $role = $this->rbacService->createRoleWithPermissions($name, $permissions, $description);
            
            if ($role) {
                $this->command->info("Created role '{$name}' with " . count($permissions) . " permissions.");
            } else {
                $this->command->error("Failed to create role '{$name}'.");
            }
        }
    }
}
