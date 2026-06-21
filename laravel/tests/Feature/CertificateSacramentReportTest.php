<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Family;
use App\Models\Member;
use App\Models\Sacrament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateSacramentReportTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $vienna;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();
    }

    public function test_sacramental_report_excludes_matrimony(): void
    {
        $family = Family::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_name' => 'Vienna Family',
            'primary_phone' => '+436640000001',
            'address_line_1' => 'Vienna St 1',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        $member = Member::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_id' => $family->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'full_name' => 'John Doe',
            'gender' => 'male',
            'email' => 'john.doe@example.com',
            'phone' => '+491234567890',
            'relationship_to_head' => 'head',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        // Create a baptism and marriage sacrament
        Sacrament::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'member_id' => $member->id,
            'sacrament_type' => 'baptism',
            'sacrament_date' => '2026-01-01',
            'place' => 'Vienna Church',
            'status' => 'approved',
            'created_by' => $this->viennaAdmin->id,
        ]);

        Sacrament::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'member_id' => $member->id,
            'sacrament_type' => 'marriage',
            'sacrament_date' => '2026-02-02',
            'place' => 'Vienna Church',
            'status' => 'approved',
            'created_by' => $this->viennaAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/reports/run', [
                'report_key' => 'sacramental_records'
            ]);

        $response->assertStatus(200);
        $records = $response->json('data.data');
        
        // Assert only baptism is returned
        $this->assertCount(1, $records);
        $this->assertEquals('Baptism', $records[0]['Sacrament Type']);
    }
}
