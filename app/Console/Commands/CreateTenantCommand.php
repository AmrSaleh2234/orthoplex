<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Tenant\Models\Tenant;

class CreateTenantCommand extends Command
{
    protected $signature = 'tenant:create-test';
    protected $description = 'Create a test tenant to debug database creation';

    public function handle()
    {
        $this->info('Attempting to create a test tenant...');
        try {
            $tenant = Tenant::create([
                'id' => 'testtenant',
                'name' => 'Test Tenant',
            ]);

            $tenant->domains()->create(['domain' => 'test.localhost']);

            $this->info('Tenant record created successfully.');
            $this->info('Check if the `tenanttesttenant` database now exists.');
        } catch (\Exception $e) {
            $this->error('An error occurred:');
            $this->error($e->getMessage());
            $this->error($e->getTraceAsString());
        }
    }
}
