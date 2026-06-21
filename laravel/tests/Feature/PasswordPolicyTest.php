<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'superadmin@msoc-europe.org')->first();
    }

    public function test_weak_passwords_are_rejected(): void
    {
        // 1. Weak password (too short)
        $response1 = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/users', [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'password' => 'Short1!',
                'password_confirmation' => 'Short1!',
                'is_active' => true,
                'default_diocese_id' => $this->admin->default_diocese_id ?: 1,
                'role' => 'Parish Admin',
            ]);

        $response1->assertStatus(422);
        $response1->assertJsonValidationErrors(['password']);

        // 2. Weak password (no special char)
        $response2 = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/users', [
                'name' => 'John Doe',
                'email' => 'john.no_special@example.com',
                'password' => 'NoSpecialChar1234',
                'password_confirmation' => 'NoSpecialChar1234',
                'is_active' => true,
                'default_diocese_id' => $this->admin->default_diocese_id ?: 1,
                'role' => 'Parish Admin',
            ]);

        $response2->assertStatus(422);
        $response2->assertJsonValidationErrors(['password']);

        // 3. Weak password (no number)
        $response3 = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/users', [
                'name' => 'John Doe',
                'email' => 'john.no_numbers@example.com',
                'password' => 'NoNumbersHere!',
                'password_confirmation' => 'NoNumbersHere!',
                'is_active' => true,
                'default_diocese_id' => $this->admin->default_diocese_id ?: 1,
                'role' => 'Parish Admin',
            ]);

        $response3->assertStatus(422);
        $response3->assertJsonValidationErrors(['password']);
    }

    public function test_strong_password_is_accepted(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/users', [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'password' => 'StrongPass@2026',
                'password_confirmation' => 'StrongPass@2026',
                'is_active' => true,
                'default_diocese_id' => $this->admin->default_diocese_id ?: 1,
                'role' => 'Parish Admin',
            ]);

        $response->assertStatus(201);
    }
}
