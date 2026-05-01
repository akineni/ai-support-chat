<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_login_with_valid_credentials(): void
    {
        $agent = User::factory()->create([
            'email'    => 'agent@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'agent@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['uuid', 'name', 'email'],
                ],
            ])
            ->assertJsonPath('status', 'success');
    }

    public function test_agent_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'agent@example.com']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'agent@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('status', 'error');
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422);
    }

    public function test_agent_can_logout(): void
    {
        $agent = User::factory()->create();

        $response = $this->actingAs($agent)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
    }
}