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
        $tenant =Tenant::query()->where("id","default")->first();
        if (!$tenant) {
            $tenant = Tenant::create(
                ['name' => 'Default Tenant',"id"=>"default"]
            );
            $tenant->domains()->create(['domain' => 'default.localhost']);
        }
    }
}
