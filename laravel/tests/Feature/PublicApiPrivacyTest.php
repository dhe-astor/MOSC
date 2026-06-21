<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Member;
use App\Models\Family;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicApiPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $church;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->church = Church::first();

        // 1. Create family
        $family = Family::create([
            'diocese_id' => $this->church->diocese_id,
            'church_id' => $this->church->id,
            'family_name' => 'Secret Family',
            'primary_phone' => '+436640003333',
            'address_line_1' => 'Secret St',
            'city' => 'Secret City',
            'membership_status' => 'active',
            'created_by' => $this->admin->id
        ]);

        // 2. Create private member record
        Member::create([
            'diocese_id' => $this->church->diocese_id,
            'church_id' => $this->church->id,
            'family_id' => $family->id,
            'first_name' => 'SecretFirst',
            'last_name' => 'SecretLast',
            'full_name' => 'SecretFirst SecretLast',
            'email' => 'secret@member.com',
            'phone' => '+491234567890',
            'relationship_to_head' => 'head',
            'membership_status' => 'active',
            'gdpr_consent' => false,
            'created_by' => $this->admin->id
        ]);
    }

    public function test_public_pages_do_not_leak_private_data(): void
    {
        // Query public pages / parishes / news
        $response1 = $this->getJson('/api/v1/public/home');
        $response1->assertStatus(200);
        $response1->assertJsonMissing(['SecretFirst']);
        $response1->assertJsonMissing(['secret@member.com']);

        $response2 = $this->getJson('/api/v1/public/parishes');
        $response2->assertStatus(200);
        $response2->assertJsonMissing(['SecretFirst']);
        $response2->assertJsonMissing(['+436640003333']);
    }
}
