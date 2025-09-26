<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\RolesAndPermissions\app\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure we're running in central database context
        \Stancl\Tenancy\Facades\Tenancy::end();
        
        // Disable Spatie permission caching to avoid tenant database access during seeding
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        
        // Force central connection for permissions
        config(['permission.database_connection' => 'central']);
        
        // Define permissions by module as per challenge requirements
        $permissions = [
            // Auth Module Permissions
            ['module' => 'Auth', 'resource' => 'users', 'action' => 'register', 'description' => 'Register new users'],
            ['module' => 'Auth', 'resource' => 'users', 'action' => 'login', 'description' => 'Login users'],
            ['module' => 'Auth', 'resource' => 'users', 'action' => 'logout', 'description' => 'Logout users'],
            ['module' => 'Auth', 'resource' => 'users', 'action' => 'verify_email', 'description' => 'Verify user email addresses'],
            ['module' => 'Auth', 'resource' => 'users', 'action' => 'resend_verification', 'description' => 'Resend email verification'],
            ['module' => 'Auth', 'resource' => 'users', 'action' => 'reset_password', 'description' => 'Reset user passwords'],
            ['module' => 'Auth', 'resource' => 'users', 'action' => 'magic_link', 'description' => 'Use passwordless magic links'],
            ['module' => 'Auth', 'resource' => 'users', 'action' => 'two_factor', 'description' => 'Manage 2FA authentication'],

            // Users Module Permissions
            ['module' => 'Users', 'resource' => 'users', 'action' => 'read', 'description' => 'View user profiles'],
            ['module' => 'Users', 'resource' => 'users', 'action' => 'create', 'description' => 'Create new users'],
            ['module' => 'Users', 'resource' => 'users', 'action' => 'update', 'description' => 'Update user profiles'],
            ['module' => 'Users', 'resource' => 'users', 'action' => 'delete', 'description' => 'Delete users'],
            ['module' => 'Users', 'resource' => 'users', 'action' => 'restore', 'description' => 'Restore soft-deleted users'],
            ['module' => 'Users', 'resource' => 'users', 'action' => 'invite', 'description' => 'Invite users to organization'],
            ['module' => 'Users', 'resource' => 'users', 'action' => 'suspend', 'description' => 'Suspend user accounts'],
            ['module' => 'Users', 'resource' => 'users', 'action' => 'reactivate', 'description' => 'Reactivate suspended accounts'],

            // RolesAndPermissions Module Permissions
            ['module' => 'RolesAndPermissions', 'resource' => 'roles', 'action' => 'read', 'description' => 'View roles'],
            ['module' => 'RolesAndPermissions', 'resource' => 'roles', 'action' => 'create', 'description' => 'Create new roles'],
            ['module' => 'RolesAndPermissions', 'resource' => 'roles', 'action' => 'update', 'description' => 'Update existing roles'],
            ['module' => 'RolesAndPermissions', 'resource' => 'roles', 'action' => 'delete', 'description' => 'Delete roles'],
            ['module' => 'RolesAndPermissions', 'resource' => 'roles', 'action' => 'assign', 'description' => 'Assign roles to users'],
            ['module' => 'RolesAndPermissions', 'resource' => 'permissions', 'action' => 'read', 'description' => 'View permissions'],
            ['module' => 'RolesAndPermissions', 'resource' => 'permissions', 'action' => 'manage', 'description' => 'Manage role permissions'],

            // Analytics Module Permissions
            ['module' => 'Analytics', 'resource' => 'login_analytics', 'action' => 'read', 'description' => 'View login analytics'],
            ['module' => 'Analytics', 'resource' => 'user_analytics', 'action' => 'read', 'description' => 'View user analytics'],
            ['module' => 'Analytics', 'resource' => 'tenant_analytics', 'action' => 'read', 'description' => 'View tenant-specific analytics'],
            ['module' => 'Analytics', 'resource' => 'reports', 'action' => 'export', 'description' => 'Export analytics reports'],

            // Tenant Module Permissions
            ['module' => 'Tenant', 'resource' => 'tenants', 'action' => 'read', 'description' => 'View tenant information'],
            ['module' => 'Tenant', 'resource' => 'tenants', 'action' => 'update', 'description' => 'Update tenant settings'],
            ['module' => 'Tenant', 'resource' => 'tenants', 'action' => 'manage', 'description' => 'Manage tenant configuration'],

            // Webhooks Module Permissions
            ['module' => 'Webhooks', 'resource' => 'webhooks', 'action' => 'read', 'description' => 'View webhooks'],
            ['module' => 'Webhooks', 'resource' => 'webhooks', 'action' => 'create', 'description' => 'Create new webhooks'],
            ['module' => 'Webhooks', 'resource' => 'webhooks', 'action' => 'update', 'description' => 'Update existing webhooks'],
            ['module' => 'Webhooks', 'resource' => 'webhooks', 'action' => 'delete', 'description' => 'Delete webhooks'],
            ['module' => 'Webhooks', 'resource' => 'webhooks', 'action' => 'test', 'description' => 'Test webhook endpoints'],

            // GDPR Compliance Permissions
            ['module' => 'Users', 'resource' => 'gdpr', 'action' => 'export_data', 'description' => 'Export user data for GDPR'],
            ['module' => 'Users', 'resource' => 'gdpr', 'action' => 'request_deletion', 'description' => 'Request GDPR data deletion'],
            ['module' => 'Users', 'resource' => 'gdpr', 'action' => 'approve_deletion', 'description' => 'Approve GDPR deletion requests'],
            ['module' => 'Users', 'resource' => 'gdpr', 'action' => 'manage_requests', 'description' => 'Manage GDPR requests'],

            // Admin/Owner specific permissions
            ['module' => 'System', 'resource' => 'system', 'action' => 'manage_all', 'description' => 'Full system management access'],
            ['module' => 'System', 'resource' => 'audit_logs', 'action' => 'read', 'description' => 'View audit logs'],
        ];

        foreach ($permissions as $permission) {
            $name = "{$permission['module']}.{$permission['resource']}.{$permission['action']}";
            
            // Create permission directly to avoid role relationship queries
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'api'],
                [
                    'module' => $permission['module'],
                    'resource' => $permission['resource'],
                    'action' => $permission['action'],
                    'description' => $permission['description'],
                    'is_global' => true,
                ]
            );
        }

        $this->command->info('Created ' . count($permissions) . ' permissions for the backend challenge requirements.');
    }
}
