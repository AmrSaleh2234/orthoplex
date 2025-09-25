<?php

namespace Modules\Tenant\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenant\Models\Tenant;
use Tests\TestCase;

class TenantManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // We are testing central routes, so we don't initialize a tenant context.
        $this->withoutExceptionHandling();
    }

    public function test_can_create_tenant()
    {
        $tenantData = [
            'name' => 'New Corp',
            'domain' => 'newcorp.localhost',
        ];

        $response = $this->postJson('/api/tenants', $tenantData);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'New Corp']);

        $this->assertDatabaseHas('tenants', ['name' => 'New Corp'], 'central');
        $this->assertDatabaseHas('domains', ['domain' => 'newcorp.localhost'], 'central');
    }

    public function test_can_update_tenant()
    {
        $tenant = Tenant::create(['name' => 'Old Name']);
        $tenant->domains()->create(['domain' => 'old.localhost']);

        $updateData = [
            'name' => 'New Name',
            'version' => 1, // Initial version is 1
        ];

        $response = $this->putJson("/api/tenants/{$tenant->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'New Name']);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'New Name',
            'version' => 2, // Version should be incremented
        ], 'central');
    }

    public function test_optimistic_locking_prevents_concurrent_updates()
    {
        $tenant = Tenant::create(['name' => 'Original Name']);
        $tenant->domains()->create(['domain' => 'original.localhost']);

        // Simulate a concurrent update by incrementing the version in the database first
        $tenant->increment('version');

        $staleUpdateData = [
            'name' => 'Stale Update Name',
            'version' => 1, // Sending the old version number
        ];

        $response = $this->putJson("/api/tenants/{$tenant->id}", $staleUpdateData);

        $response->assertStatus(409); // Assert a conflict response

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'Original Name', // Name should not have changed
            'version' => 2, // Version remains at 2
        ], 'central');
    }
}
