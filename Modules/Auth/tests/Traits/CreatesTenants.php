<?php

namespace Modules\Auth\tests\Traits;

use Modules\Tenant\Models\Tenant;
use Modules\User\Models\User;

trait CreatesTenants
{
    protected function createTenantAndUser(string $role = 'owner'): array
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
        ]);

        $tenant->domains()->create([
            'domain' => 'test.localhost',
        ]);

        tenancy()->initialize($tenant);

        // Seed the database with roles and permissions
        $this->seed('Database\Seeders\TenantDatabaseSeeder');

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $user->assignRole($role);

        return [$tenant, $user];
    }
}
