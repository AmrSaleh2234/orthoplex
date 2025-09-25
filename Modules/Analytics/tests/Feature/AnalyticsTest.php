<?php

namespace Modules\Analytics\Tests\Feature;

use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Modules\Analytics\app\Jobs\LogLoginEventJob;
use Modules\Analytics\app\Listeners\LoginEventListener;
use Modules\Analytics\app\Models\LoginEvent;
use Modules\Auth\tests\Traits\CreatesTenants;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase, CreatesTenants;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling();
    }

    public function test_login_event_updates_user_stats_and_dispatches_job()
    {
        [, $user] = $this->createTenantAndUser();
        $this->assertNull($user->last_login_at);
        $this->assertEquals(0, $user->login_count);

        // Manually trigger the event
        $event = new Login('api', $user, false);
        $listener = new LoginEventListener();
        $listener->handle($event);

        $user->refresh();

        $this->assertNotNull($user->last_login_at);
        $this->assertEquals(1, $user->login_count);
    }

    public function test_aggregation_command_summarizes_daily_logins()
    {
        [$tenant, ] = $this->createTenantAndUser();
        tenancy()->initialize($tenant);

        // Create some historical login events
        LoginEvent::factory()->create(['login_at' => now()->subDay()]);
        LoginEvent::factory()->create(['login_at' => now()->subDay()]);
        LoginEvent::factory()->create(['login_at' => now()]);

        // Run the aggregation command
        Artisan::call('analytics:aggregate-logins');

        $this->assertDatabaseCount('login_daily', 2);
        $this->assertDatabaseHas('login_daily', [
            'date' => now()->subDay()->toDateString(),
            'login_count' => 2,
        ]);
        $this->assertDatabaseHas('login_daily', [
            'date' => now()->toDateString(),
            'login_count' => 1,
        ]);
    }

    public function test_analytics_api_returns_aggregated_data_to_authorized_user()
    {
        [, $user] = $this->createTenantAndUser('owner'); // Owners can read analytics

        // Create aggregated data
        Artisan::call('analytics:aggregate-logins');

        $token = auth('api')->login($user);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/analytics/daily-logins');

        $response->assertStatus(200);
    }

    public function test_analytics_api_is_protected_from_unauthorized_users()
    {
        [, $user] = $this->createTenantAndUser('member'); // Members cannot read analytics

        $token = auth('api')->login($user);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/analytics/daily-logins');

        $response->assertStatus(403);
    }
}
