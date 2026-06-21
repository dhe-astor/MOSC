<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Diocese;
use App\Models\CertificateTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $diocese;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->diocese = Diocese::first();
    }

    public function test_diocese_admin_can_create_template(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/certificate-templates', [
                'diocese_id' => $this->diocese->id,
                'name' => 'Custom Membership Template',
                'certificate_type' => 'membership',
                'language' => 'en',
                'html_template' => '<h1>Certificate of Membership</h1>',
                'seal_required' => true,
                'signature_required' => true
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('certificate_templates', [
            'name' => 'Custom Membership Template'
        ]);
    }

    public function test_parish_admin_cannot_create_template(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/certificate-templates', [
                'diocese_id' => $this->diocese->id,
                'name' => 'Vienna Template',
                'certificate_type' => 'membership',
                'language' => 'en',
                'html_template' => '<h1>V</h1>'
            ]);

        $response->assertStatus(403);
    }
}
