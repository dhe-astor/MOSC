<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Family;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    protected $viennaAdmin;
    protected $dioceseAdmin;
    protected $vienna;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->dioceseAdmin = User::where('email', 'admin@msoc-europe.org')->first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();
    }

    public function test_parish_admin_import_creates_pending_families(): void
    {
        $csvContent = "Family Name,Primary Phone,Address Line 1,City,Preferred Language,GDPR Consent,Communication Consent\n" .
                     "John Import Family,+436648880001,Hauptstrasse 1,Vienna,en,1,1\n" .
                     "Mary Import Family,+436648880002,Hauptstrasse 2,Vienna,en,true,true\n";

        $file = UploadedFile::fake()->createWithContent('families.csv', $csvContent);

        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/imports/families', [
                'file' => $file,
                'church_id' => $this->vienna->id,
                'dry_run' => false
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.imported_count', 2);

        // Verify inserted but pending
        $this->assertDatabaseHas('families', [
            'family_name' => 'John Import Family',
            'church_id' => $this->vienna->id,
            'membership_status' => 'pending',
            'family_code' => null
        ]);
    }

    public function test_diocese_admin_import_auto_approves_and_generates_codes(): void
    {
        $csvContent = "Family Name,Primary Phone,Address Line 1,City,Preferred Language,GDPR Consent,Communication Consent\n" .
                     "Auto Approved Family,+436648880003,Hauptstrasse 3,Vienna,en,1,1\n";

        $file = UploadedFile::fake()->createWithContent('families.csv', $csvContent);

        $response = $this->actingAs($this->dioceseAdmin, 'sanctum')
            ->postJson('/api/v1/imports/families', [
                'file' => $file,
                'church_id' => $this->vienna->id,
                'dry_run' => false
            ]);

        $response->assertStatus(200);

        // Verify inserted and approved immediately
        $family = Family::where('family_name', 'Auto Approved Family')->first();
        $this->assertNotNull($family);
        $this->assertEquals('active', $family->membership_status);
        $this->assertNotNull($family->family_code);
    }

    public function test_dry_run_does_not_insert_database_records(): void
    {
        $csvContent = "Family Name,Primary Phone,Address Line 1,City,Preferred Language,GDPR Consent,Communication Consent\n" .
                     "Dry Run Family,+436648880004,Hauptstrasse 4,Vienna,en,1,1\n";

        $file = UploadedFile::fake()->createWithContent('families.csv', $csvContent);

        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/imports/families', [
                'file' => $file,
                'church_id' => $this->vienna->id,
                'dry_run' => true
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.success', true);

        // Verify NOT in database
        $this->assertDatabaseMissing('families', [
            'family_name' => 'Dry Run Family'
        ]);
    }

    public function test_duplicate_detection(): void
    {
        // Setup existing family
        Family::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_name' => 'Duplicate Family',
            'primary_phone' => '+436649991111',
            'address_line_1' => 'Street 1',
            'city' => 'Vienna',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        $csvContent = "Family Name,Primary Phone,Address Line 1,City,Preferred Language,GDPR Consent,Communication Consent\n" .
                     "Duplicate Family,+436649991111,Street 1,Vienna,en,1,1\n";

        $file = UploadedFile::fake()->createWithContent('families.csv', $csvContent);

        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/imports/families', [
                'file' => $file,
                'church_id' => $this->vienna->id,
                'dry_run' => false
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.success', false)
            ->assertJsonStructure(['errors' => ['duplicates']]);
    }

    public function test_member_import_binds_to_family(): void
    {
        // 1. Create a family first
        $family = Family::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_name' => 'Existing Family',
            'primary_phone' => '+436649992222',
            'address_line_1' => 'Street 2',
            'city' => 'Vienna',
            'family_code' => 'MSOC-VIE-F-000001',
            'membership_status' => 'active',
            'created_by' => $this->viennaAdmin->id
        ]);

        // 2. Import members for this family code
        $csvContent = "Family Code,First Name,Middle Name,Last Name,Baptism Name,Gender,Date of Birth,Relationship to Head,Phone,WhatsApp Phone,Email,Occupation,Employer/School,Student Status,Marital Status,GDPR Consent\n" .
                     "MSOC-VIE-F-000001,John,,Doe,John,male,1980-05-10,head,+436641112222,,john.doe@example.com,Engineer,,0,married,1\n";

        $file = UploadedFile::fake()->createWithContent('members.csv', $csvContent);

        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/imports/members', [
                'file' => $file,
                'church_id' => $this->vienna->id,
                'dry_run' => false
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.success', true);

        // Verify member is imported and linked to family
        $this->assertDatabaseHas('members', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'family_id' => $family->id,
            'membership_status' => 'pending'
        ]);

        // Family head_member_id should be set to this member
        $member = Member::where('first_name', 'John')->first();
        $this->assertEquals($member->id, $family->refresh()->head_member_id);
    }
}
