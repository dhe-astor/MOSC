<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Member;
use App\Models\Family;
use App\Models\FinanceReceipt;
use App\Models\MemberPortalAccess;
use App\Services\MemberPortalFinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberPortalReceiptVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_only_see_own_receipts(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $family = Family::create([
            'diocese_id' => 1,
            'church_id' => 1,
            'family_name' => 'Test Family',
            'family_code' => 'FAM-TEST-99',
            'primary_phone' => '+431234567',
            'address_line_1' => 'Vienna Street 10',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'status' => 'active',
            'created_by' => 1
        ]);

        $member = Member::create([
            'diocese_id' => 1,
            'church_id' => 1,
            'family_id' => $family->id,
            'user_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Portal',
            'full_name' => 'John Portal',
            'gender' => 'male',
            'relationship_to_head' => 'head',
            'membership_status' => 'active',
            'created_by' => 1
        ]);

        MemberPortalAccess::create([
            'diocese_id' => $family->diocese_id,
            'church_id' => $family->church_id,
            'family_id' => $family->id,
            'member_id' => $member->id,
            'user_id' => $user->id,
            'access_type' => 'family_head',
            'status' => 'active'
        ]);

        // Create V2 receipt for this member
        $receipt = FinanceReceipt::create([
            'income_header_id' => null,
            'receipt_number' => 'VIE-2026-999999',
            'receipt_date' => now()->toDateString(),
            'received_from' => 'John Portal',
            'member_id' => $member->id,
            'payment_method' => 'cash',
            'total_amount' => 100.00,
            'status' => 'active'
        ]);

        // Create V2 receipt for another member in another family
        $otherFamily = Family::create([
            'diocese_id' => 1,
            'church_id' => 1,
            'family_name' => 'Other Family',
            'family_code' => 'FAM-TEST-88',
            'primary_phone' => '+431234568',
            'address_line_1' => 'Vienna Street 12',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'status' => 'active',
            'created_by' => 1
        ]);

        $otherUser = User::factory()->create();
        $otherMember = Member::create([
            'diocese_id' => 1,
            'church_id' => 1,
            'family_id' => $otherFamily->id,
            'user_id' => $otherUser->id,
            'first_name' => 'Jane',
            'last_name' => 'Portal',
            'full_name' => 'Jane Portal',
            'gender' => 'female',
            'relationship_to_head' => 'head',
            'membership_status' => 'active',
            'created_by' => 1
        ]);

        $otherReceipt = FinanceReceipt::create([
            'income_header_id' => null,
            'receipt_number' => 'VIE-2026-888888',
            'receipt_date' => now()->toDateString(),
            'received_from' => 'Someone Else',
            'member_id' => $otherMember->id,
            'payment_method' => 'cash',
            'total_amount' => 200.00,
            'status' => 'active'
        ]);

        $receipts = MemberPortalFinanceService::getReceipts($user);

        $receiptNumbers = collect($receipts)->pluck('receipt_number')->toArray();

        $this->assertContains('VIE-2026-999999', $receiptNumbers);
        $this->assertNotContains('VIE-2026-888888', $receiptNumbers);
    }
}
