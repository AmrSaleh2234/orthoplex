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
        // Check if tenant already exists to avoid database creation conflicts
        $tenant = Tenant::query()->where("id", "default")->first();
        
        if (!$tenant) {
            try {
                // Create tenant only if it doesn't exist
                $tenant = Tenant::create([
                    'name' => 'Default Tenant',
                    'id' => 'default'
                ]);
                
                // Create domain if tenant was created successfully
                if (!$tenant->domains()->where('domain', 'default.localhost')->exists()) {
                    $tenant->domains()->create(['domain' => 'default.localhost']);
                }
                
                $this->command->info('Created default tenant successfully.');
            } catch (\Exception $e) {
                // If tenant creation fails due to existing database, just log and continue
                $this->command->warn('Tenant creation skipped: ' . $e->getMessage());
            }
        } else {
            // Tenant already exists, ensure domain exists
            if (!$tenant->domains()->where('domain', 'default.localhost')->exists()) {
                $tenant->domains()->create(['domain' => 'default.localhost']);
            }
            $this->command->info('Default tenant already exists, skipping creation.');
        }
    }
}
