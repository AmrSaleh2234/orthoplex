<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\RolesAndPermissions\Database\Seeders\RolesAndPermissionsDatabaseSeeder;

class TenantDatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsDatabaseSeeder::class,
        ]);
    }
}
