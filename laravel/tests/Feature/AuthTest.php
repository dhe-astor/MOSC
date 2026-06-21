<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Diocese;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed standard roles/permissions/dioceses/countries/churches/users
        $this->seed();
    }

    public function test_valid_user_can_login(): void
    {
        $user = User::create([
            'name' => 'Regular Member',
            'email' => 'member@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('Password123!'),
            'default_diocese_id' => 1,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'member@example.com',
            'password' => 'Password123!'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'access_token',
                    'token_type',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'roles',
                        'permissions'
                    ]
                ]
            ]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::where('email', 'admin@msoc-europe.org')->first();
        $user->is_active = false;
        $user->save();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@msoc-europe.org',
            'password' => 'Password123!'
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Your account is deactivated. Please contact support.'
            ]);
    }

    public function test_user_can_fetch_me(): void
    {
        $user = User::where('email', 'admin@msoc-europe.org')->first();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.email', 'admin@msoc-europe.org');
    }

    public function test_user_can_logout(): void
    {
        $user = User::where('email', 'admin@msoc-europe.org')->first();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);
    }
}
