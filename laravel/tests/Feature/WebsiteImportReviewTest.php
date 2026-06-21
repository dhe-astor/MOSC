<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Member;
use App\Models\WebsiteImportSource;
use App\Models\WebsiteImportRun;
use App\Models\WebsiteImportRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteImportReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_accept_and_ignore_import_record(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();

        $source = WebsiteImportSource::create([
            'diocese_id' => $admin->default_diocese_id,
            'source_type' => 'priests',
            'source_url' => 'https://msoc-europe.com/priests.php',
            'status' => 'active'
        ]);

        $run = WebsiteImportRun::create([
            'diocese_id' => $source->diocese_id,
            'source_id' => $source->id,
            'run_type' => 'manual',
            'status' => 'review_required'
        ]);

        // Create unmatched priest import record
        $record = WebsiteImportRecord::create([
            'import_run_id' => $run->id,
            'record_type' => 'priest',
            'external_key' => 'scraped_priest_99',
            'raw_name' => 'Rev. Fr. Newly Imported',
            'normalized_name' => 'Newly Imported',
            'raw_payload' => [
                'title' => 'Rev. Fr.',
                'ordination_name' => 'Newly Imported',
                'email' => 'new.imported@demo.msoc.test',
                'phone' => '+49 176 9999999',
                'clergy_type' => 'priest',
                'bio' => 'Draft bio'
            ],
            'match_status' => 'unmatched'
        ]);

        // Link record to a member manually
        $member = Member::create([
            'diocese_id' => $admin->default_diocese_id,
            'church_id' => 1,
            'first_name' => 'Newly',
            'last_name' => 'Imported',
            'full_name' => 'Newly Imported',
            'gender' => 'male',
            'relationship_to_head' => 'other',
            'membership_status' => 'active',
            'created_by' => $admin->id
        ]);

        $linkResponse = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/clergy/import-records/{$record->id}/link-member", [
            'member_id' => $member->id
        ]);
        $linkResponse->assertStatus(200);

        $record->refresh();
        $this->assertEquals('matched', $record->match_status);
        $this->assertEquals($member->id, $record->matched_member_id);

        // Accept the record to finalize it
        $acceptResponse = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/clergy/import-records/{$record->id}/accept");
        $acceptResponse->assertStatus(200);

        $record->refresh();
        $this->assertEquals('imported', $record->match_status);

        // Verify priest profile created
        $this->assertDatabaseHas('priest_profiles', [
            'member_id' => $member->id,
            'display_name' => 'Rev. Fr. Newly Imported'
        ]);

        // Create another record to test ignore
        $record2 = WebsiteImportRecord::create([
            'import_run_id' => $run->id,
            'record_type' => 'priest',
            'external_key' => 'scraped_priest_100',
            'raw_name' => 'Rev. Fr. Ignored Priest',
            'normalized_name' => 'Ignored Priest',
            'raw_payload' => [],
            'match_status' => 'unmatched'
        ]);

        $ignoreResponse = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/clergy/import-records/{$record2->id}/ignore");
        $ignoreResponse->assertStatus(200);

        $record2->refresh();
        $this->assertEquals('ignored', $record2->match_status);
    }
}
