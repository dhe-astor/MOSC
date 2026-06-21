<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Member;
use App\Models\Family;
use App\Models\MemberPortalAccess;
use App\Models\Donation;
use App\Models\Receipt;
use App\Models\FinanceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MemberPortalFinanceTest extends TestCase
{
    use RefreshDatabase;

    protected $viennaAdmin;
    protected $portalUser;
    protected $member;
    protected $family;
    protected $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');

        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->portalUser = User::create([
            'name' => 'Jane Member',
            'email' => 'jane.member@example.com',
            'password' => bcrypt('password'),
            'default_diocese_id' => $this->viennaAdmin->default_diocese_id,
            'default_church_id' => $this->viennaAdmin->default_church_id,
        ]);

        $this->family = Family::create([
            'diocese_id' => $this->viennaAdmin->default_diocese_id,
            'church_id' => $this->viennaAdmin->default_church_id,
            'family_code' => 'FAM-PORTAL-2',
            'family_name' => 'Jane Family',
            'primary_phone' => '+43660111223',
            'address_line_1' => 'Vienna St 1',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        $this->member = Member::create([
            'diocese_id' => $this->viennaAdmin->default_diocese_id,
            'church_id' => $this->viennaAdmin->default_church_id,
            'family_id' => $this->family->id,
            'member_code' => 'MEM-PORTAL-2',
            'first_name' => 'Jane',
            'last_name' => 'Member',
            'full_name' => 'Jane Member',
            'email' => 'jane.member@example.com',
            'phone' => '+43660111223',
            'relationship_to_head' => 'head',
            'membership_status' => 'active',
            'gender' => 'female',
            'date_of_birth' => '1992-05-10',
            'created_by' => $this->viennaAdmin->id
        ]);

        MemberPortalAccess::create([
            'diocese_id' => $this->family->diocese_id,
            'church_id' => $this->family->church_id,
            'family_id' => $this->family->id,
            'member_id' => $this->member->id,
            'user_id' => $this->portalUser->id,
            'access_type' => 'family_head',
            'status' => 'active'
        ]);

        $this->category = FinanceCategory::create([
            'diocese_id' => $this->viennaAdmin->default_diocese_id,
            'church_id' => $this->viennaAdmin->default_church_id,
            'name' => 'General Offertory',
            'slug' => 'general-offertory',
            'category_type' => 'income',
            'status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);
    }

    public function test_can_view_own_donations_and_receipts(): void
    {
        $donation = Donation::create([
            'diocese_id' => $this->family->diocese_id,
            'church_id' => $this->family->church_id,
            'member_id' => $this->member->id,
            'family_id' => $this->family->id,
            'finance_category_id' => $this->category->id,
            'donor_name' => 'Jane Member',
            'donation_type' => 'general',
            'amount' => 100.00,
            'payment_method' => 'bank_transfer',
            'received_date' => now()->toDateString(),
            'status' => 'approved',
            'created_by' => $this->viennaAdmin->id
        ]);

        $pdfPath = 'receipts/rec-001.pdf';
        Storage::put($pdfPath, 'RECEIPT CONTENT');

        $receipt = Receipt::create([
            'diocese_id' => $this->family->diocese_id,
            'church_id' => $this->family->church_id,
            'member_id' => $this->member->id,
            'family_id' => $this->family->id,
            'receipt_number' => 'REC-2026-001',
            'receipt_type' => 'donation',
            'payer_name' => 'Jane Member',
            'amount' => 100.00,
            'payment_method' => 'bank_transfer',
            'receipt_date' => now()->toDateString(),
            'pdf_path' => $pdfPath,
            'status' => 'issued',
            'issued_by' => $this->viennaAdmin->id,
            'created_by' => $this->viennaAdmin->id
        ]);

        $response = $this->actingAs($this->portalUser, 'sanctum')
            ->getJson('/api/v1/member-portal/receipts');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.receipt_number', 'REC-2026-001');
    }
}
