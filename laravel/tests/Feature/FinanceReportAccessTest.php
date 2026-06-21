<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Donation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceReportAccessTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $vienna;
    protected $treasurerUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();

        // Create a treasurer user
        $this->treasurerUser = User::create([
            'name' => 'Vienna Treasurer',
            'email' => 'treasurer@vienna.com',
            'password' => bcrypt('password'),
            'default_diocese_id' => $this->vienna->diocese_id,
            'default_church_id' => $this->vienna->id,
            'active_church_id' => $this->vienna->id,
            'is_active' => true,
        ]);
        $this->treasurerUser->assignRole('Parish Treasurer');
    }

    public function test_finance_report_requires_permission(): void
    {
        // Try accessing without permission/role
        $nonPermittedUser = User::create([
            'name' => 'Simple User',
            'email' => 'simple@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($nonPermittedUser, 'sanctum')
            ->postJson('/api/v1/reports/run', [
                'report_key' => 'finance_statement'
            ]);

        $response->assertStatus(403);
    }

    public function test_treasurer_scoped_to_active_church(): void
    {
        // Add donation
        Donation::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'donor_name' => 'Vienna Donor',
            'donation_type' => 'tithe',
            'received_date' => '2026-05-01',
            'amount' => 500,
            'payment_method' => 'bank_transfer',
            'status' => 'received',
            'created_by' => $this->treasurerUser->id,
        ]);

        $response = $this->actingAs($this->treasurerUser, 'sanctum')
            ->postJson('/api/v1/reports/run', [
                'report_key' => 'finance_statement'
            ]);

        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertNotEmpty($data);
        $this->assertEquals(500, $data[0]['Amount (EUR)']);
    }
}
