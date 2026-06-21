<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Member;
use App\Models\Family;
use App\Models\Sacrament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SacramentRecordTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $herneAdmin;
    protected $priest;
    protected $vienna;
    protected $herne;
    protected $member;
    protected $family;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->herneAdmin = User::where('email', 'herne.admin@msoc-europe.org')->first();
        $this->priest = User::where('email', 'priest@msoc-europe.org')->first();

        $this->vienna = Church::where('short_name', 'Vienna')->first();
        $this->herne = Church::where('short_name', 'Herne')->first();

        $this->family = Family::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_name' => 'Test Family',
            'primary_phone' => '+436640001111',
            'address_line_1' => 'Street 1',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        $this->member = Member::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_id' => $this->family->id,
            'first_name' => 'Vienna',
            'last_name' => 'Member',
            'full_name' => 'Vienna Member',
            'relationship_to_head' => 'head',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);
    }

    public function test_parish_admin_can_create_sacrament_in_own_church(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/sacraments', [
                'diocese_id' => $this->vienna->diocese_id,
                'church_id' => $this->vienna->id,
                'member_id' => $this->member->id,
                'family_id' => $this->family->id,
                'sacrament_type' => 'baptism',
                'sacrament_date' => '2026-06-01',
                'place' => 'St. Mary\'s Vienna',
                'status' => 'draft'
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('sacraments', [
            'member_id' => $this->member->id,
            'sacrament_type' => 'baptism',
            'status' => 'draft'
        ]);
    }

    public function test_parish_admin_cannot_create_sacrament_in_another_church(): void
    {
        // Vienna admin tries to create sacrament in Herne
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/sacraments', [
                'diocese_id' => $this->herne->diocese_id,
                'church_id' => $this->herne->id,
                'member_id' => $this->member->id,
                'sacrament_type' => 'baptism',
                'sacrament_date' => '2026-06-01',
                'place' => 'Herne'
            ]);

        $response->assertStatus(403);
    }

    public function test_priest_can_verify_and_approve_sacrament_in_assigned_church(): void
    {
        // Create draft sacrament in Vienna
        $sacrament = Sacrament::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'member_id' => $this->member->id,
            'sacrament_type' => 'holy_communion',
            'sacrament_date' => '2026-06-01',
            'place' => 'Vienna',
            'status' => 'draft',
            'created_by' => $this->viennaAdmin->id
        ]);

        // Submit first
        $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson("/api/v1/sacraments/{$sacrament->id}/submit")
            ->assertStatus(200);

        // Verify (Priest assigned to Vienna and Herne)
        $this->actingAs($this->priest, 'sanctum')
            ->postJson("/api/v1/sacraments/{$sacrament->id}/verify")
            ->assertStatus(200);

        $this->assertEquals('verified', $sacrament->refresh()->status);

        // Approve
        $this->actingAs($this->priest, 'sanctum')
            ->postJson("/api/v1/sacraments/{$sacrament->id}/approve")
            ->assertStatus(200);

        $this->assertEquals('approved', $sacrament->refresh()->status);
    }

    public function test_sacrament_records_are_soft_deleted(): void
    {
        $sacrament = Sacrament::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'member_id' => $this->member->id,
            'sacrament_type' => 'confirmation',
            'sacrament_date' => '2026-06-01',
            'place' => 'Vienna',
            'status' => 'draft',
            'created_by' => $this->viennaAdmin->id
        ]);

        $response = $this->actingAs($this->priest, 'sanctum')
            ->deleteJson("/api/v1/sacraments/{$sacrament->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('sacraments', [
            'id' => $sacrament->id
        ]);
    }
}
