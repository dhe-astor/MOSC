<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Priest;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_church_creation_creates_audit_log(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();
        $germany = Church::where('short_name', 'Herne')->first(); // uses country DE

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/churches', [
            'diocese_id' => $germany->diocese_id,
            'country_id' => $germany->country_id,
            'name' => 'St. Mary\'s Test Parish',
            'short_name' => 'Test Parish',
            'church_type' => 'parish',
            'city' => 'Düsseldorf',
            'country' => 'Germany',
            'canonical_status' => 'active',
            'public_page_slug' => 'test-parish'
        ]);

        $response->assertStatus(201);
        $newChurchId = $response->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'churches',
            'action' => 'church_created',
            'auditable_id' => $newChurchId,
            'user_id' => $admin->id
        ]);
    }

    public function test_priest_assignment_creates_audit_log(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();
        $priest = Priest::first();
        $rome = Church::where('short_name', 'Rome')->first();

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/priests/{$priest->id}/assignments", [
            'church_id' => $rome->id,
            'role' => 'visiting_priest',
            'assignment_start_date' => '2026-06-01'
        ]);

        $response->assertStatus(201);
        $assignmentId = $response->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'assignments',
            'action' => 'priest_assignment_created',
            'auditable_id' => $assignmentId,
            'user_id' => $admin->id
        ]);
    }

    public function test_active_church_switch_creates_audit_log(): void
    {
        $user = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $vienna = Church::where('short_name', 'Vienna')->first();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/auth/active-church', [
            'church_id' => $vienna->id
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'auth',
            'action' => 'active_church_changed',
            'user_id' => $user->id
        ]);
    }

    public function test_sensitive_fields_are_masked_in_audit_logs(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/users', [
            'name' => 'John Doe',
            'email' => 'johndoe@msoc-europe.org',
            'password' => 'SuperSecretPassword123!',
            'default_diocese_id' => $admin->default_diocese_id,
            'role' => 'Parish Admin'
        ]);

        $response->assertStatus(201);
        $newUserId = $response->json('data.id');

        $log = AuditLog::where('module', 'users')
            ->where('action', 'user_created')
            ->where('auditable_id', $newUserId)
            ->first();

        $this->assertNotNull($log);
        $newValues = $log->new_values;

        // Check password field is masked
        $this->assertEquals('[MASKED]', $newValues['password'] ?? null);
    }
}
