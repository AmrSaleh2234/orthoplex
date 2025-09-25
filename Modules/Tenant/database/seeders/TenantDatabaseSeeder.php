<?php

namespace Modules\Tenant\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Tenant\Models\Tenant;

class TenantDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['id' => 'default'],
            ['name' => 'Default Tenant']
        );

        if ($tenant->wasRecentlyCreated) {
            $tenant->domains()->create([
                'domain' => 'default.localhost',
            ]);
        }
    }
}
