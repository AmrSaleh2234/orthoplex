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
        $permissions = [
            'users.read',
            'users.update',
            'users.delete',
            'users.invite',
            'analytics.read',
            'roles.manage',
            'users.restore',
            'gdpr.delete.request',
            'gdpr.delete.manage',
            'webhooks.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }
    }
}
