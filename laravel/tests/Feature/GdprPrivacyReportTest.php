<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Family;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GdprPrivacyReportTest extends TestCase
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

    public function test_gdpr_report_permission_denied(): void
    {
        // Vienna admin lacks GDPR permissions
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/reports/run', [
                'report_key' => 'gdpr_privacy_audit'
            ]);

        $response->assertStatus(403);
    }

    public function test_gdpr_report_success(): void
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

        Member::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_id' => $family->id,
            'first_name' => 'Kid',
            'last_name' => 'One',
            'full_name' => 'Kid One',
            'gender' => 'male',
            'relationship_to_head' => 'son',
            'membership_status' => 'active',
            'gdpr_consent' => false,
            'created_by' => $this->viennaAdmin->id
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/reports/run', [
                'report_key' => 'gdpr_privacy_audit'
            ]);

        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertNotEmpty($data);
        $this->assertGreaterThanOrEqual(1, $data[0]['GDPR Missing Count']);
    }
}
