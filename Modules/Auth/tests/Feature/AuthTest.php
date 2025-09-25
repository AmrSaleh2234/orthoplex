<?php

namespace Modules\Auth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\tests\Traits\CreatesTenants;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase, CreatesTenants;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling(); // See the actual exceptions
    }

    public function test_user_can_login_with_valid_credentials()
    {
        [, $user] = $this->createTenantAndUser();

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in']);
    }

    public function test_user_cannot_login_with_invalid_credentials()
    {
        [, $user] = $this->createTenantAndUser();

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_protected_route()
    {
        $this->createTenantAndUser();

        $response = $this->getJson('/api/users');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_with_permission_can_access_protected_route()
    {
        [, $user] = $this->createTenantAndUser('owner'); // Owners have all permissions

        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/users');

        $response->assertStatus(200);
    }

    public function test_authenticated_user_without_permission_cannot_access_protected_route()
    {
        [, $user] = $this->createTenantAndUser('member'); // Members cannot read users

        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/users');

        $response->assertStatus(403);
    }
}
