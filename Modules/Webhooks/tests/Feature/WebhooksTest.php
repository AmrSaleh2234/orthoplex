<?php

namespace Modules\Webhooks\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Auth\tests\Traits\CreatesTenants;
use Modules\Webhooks\app\Events\UserCreated;
use Modules\Webhooks\app\Jobs\ProvisionTenantJob;
use Modules\Webhooks\app\Jobs\SendWebhookJob;
use Modules\Webhooks\app\Models\Webhook;
use Tests\TestCase;

class WebhooksTest extends TestCase
{
    use RefreshDatabase, CreatesTenants;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling();
    }

    // Test Inbound Provisioning Webhook
    public function test_provisioning_webhook_dispatches_job_with_valid_data()
    {
        Queue::fake();

        $provisionData = [
            'name' => 'New Tenant',
            'domain' => 'newtenant.localhost',
            'owner_name' => 'John Doe',
            'owner_email' => 'john@example.com',
            'owner_password' => 'password',
        ];

        $response = $this->postJson('/api/webhooks/provision-tenant', $provisionData);

        $response->assertStatus(202);
        Queue::assertPushed(ProvisionTenantJob::class);
    }

    public function test_provisioning_webhook_fails_with_invalid_data()
    {
        Queue::fake();

        $response = $this->postJson('/api/webhooks/provision-tenant', ['name' => 'New Tenant']);

        $response->assertStatus(422); // Validation error
        Queue::assertNotPushed(ProvisionTenantJob::class);
    }

    // Test Outbound Webhook Management
    public function test_authorized_user_can_create_a_webhook()
    {
        [, $user] = $this->createTenantAndUser('owner');
        $token = auth('api')->login($user);

        $webhookData = [
            'url' => 'https://example.com/webhook',
            'events' => ['user.created', 'user.deleted'],
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/webhooks', $webhookData);

        $response->assertStatus(201)->assertJsonFragment(['url' => 'https://example.com/webhook']);
        $this->assertDatabaseHas('webhooks', ['url' => 'https://example.com/webhook']);
    }

    public function test_unauthorized_user_cannot_create_a_webhook()
    {
        [, $user] = $this->createTenantAndUser('member');
        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/webhooks', ['url' => 'https://example.com/webhook', 'events' => ['user.created']]);

        $response->assertStatus(403);
    }

    // Test Outbound Webhook Triggering
    public function test_user_created_event_triggers_webhook_job()
    {
        Queue::fake();
        [$tenant, $user] = $this->createTenantAndUser('owner');
        tenancy()->initialize($tenant);

        Webhook::create([
            'url' => 'https://example.com/user-created',
            'secret' => 'secret',
            'events' => ['user.created'],
        ]);

        event(new UserCreated($user));

        Queue::assertPushed(SendWebhookJob::class, function ($job) use ($user) {
            return $job->payload['data']['id'] === $user->id;
        });
    }
}
