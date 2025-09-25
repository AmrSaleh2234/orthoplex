<?php

namespace Modules\RolesAndPermissions\Database\Seeders;

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Seeder;
use Modules\RolesAndPermissions\app\Models\Permission;
use Modules\RolesAndPermissions\app\Models\Role;

class RolesAndPermissionsDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        // create roles and assign existing permissions
        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'api']);
        $ownerRole->givePermissionTo(Permission::all());

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $adminRole->givePermissionTo([
            'users.read',
            'users.update',
            'users.invite',
            'analytics.read',
            'roles.manage',
            'users.restore',
            'gdpr.delete.request',
            'gdpr.delete.manage',
            'webhooks.manage',
        ]);

        $memberRole = Role::firstOrCreate(['name' => 'member', 'guard_name' => 'api']);
        $memberRole->givePermissionTo([
            'analytics.read',
            'gdpr.delete.request',
        ]);

        $auditorRole = Role::firstOrCreate(['name' => 'auditor', 'guard_name' => 'api']);
        $auditorRole->givePermissionTo([
            'users.read',
            'analytics.read',
            'gdpr.delete.request',
        ]);
    }
}
