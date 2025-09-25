<?php

namespace Modules\User\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Auth\tests\Traits\CreatesTenants;
use Modules\User\app\Jobs\GdprDeleteJob;
use Modules\User\Models\GdprDeleteRequest;
use Modules\User\Models\User;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase, CreatesTenants;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling();
    }

    public function test_can_filter_users_by_name()
    {
        [, $owner] = $this->createTenantAndUser('owner');
        User::factory()->create(['name' => 'Jane Doe']);

        $token = auth('api')->login($owner);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/users?filter[name]=Jane Doe');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Jane Doe']);
    }

    public function test_can_soft_delete_and_restore_a_user()
    {
        [, $owner] = $this->createTenantAndUser('owner');
        $member = User::factory()->create();

        $token = auth('api')->login($owner);

        // Delete the user
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson("/api/users/{$member->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('users', ['id' => $member->id]);

        // Restore the user
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/users/{$member->id}/restore")
            ->assertStatus(200);

        $this->assertNotSoftDeleted('users', ['id' => $member->id]);
    }

    public function test_user_can_request_gdpr_deletion()
    {
        [, $user] = $this->createTenantAndUser('member');
        $token = auth('api')->login($user);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/gdpr/delete-request')
            ->assertStatus(200);

        $this->assertDatabaseHas('gdpr_delete_requests', [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
    }

    public function test_admin_can_approve_gdpr_deletion_request()
    {
        Queue::fake();
        [, $admin] = $this->createTenantAndUser('admin');
        $member = User::factory()->create();

        $request = GdprDeleteRequest::create(['user_id' => $member->id]);

        $token = auth('api')->login($admin);
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/gdpr/delete-request/{$request->id}/approve")
            ->assertStatus(200);

        $this->assertDatabaseHas('gdpr_delete_requests', [
            'id' => $request->id,
            'status' => 'approved',
        ]);

        Queue::assertPushed(GdprDeleteJob::class, function ($job) use ($member) {
            return $job->user->id === $member->id;
        });
    }
}
