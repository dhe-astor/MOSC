<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\WebsitePage;
use App\Models\UserChurchAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsScopingTest extends TestCase
{
    use RefreshDatabase;

    protected $viennaAdmin;
    protected $vienna;
    protected $munich;
    protected $munichAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();

        // Setup second church and admin
        $this->munich = Church::create([
            'diocese_id' => $this->vienna->diocese_id,
            'name' => 'St. Mary Munich',
            'slug' => 'st-mary-munich',
            'public_page_slug' => 'st-mary-munich-public',
            'short_name' => 'Munich',
            'church_type' => 'parish',
            'city' => 'Munich',
            'country' => 'Germany',
            'country_id' => $this->vienna->country_id,
            'created_by' => $this->viennaAdmin->id
        ]);

        $this->munichAdmin = User::create([
            'name' => 'Munich Admin',
            'email' => 'munich.admin@msoc-europe.org',
            'password' => bcrypt('password'),
            'default_diocese_id' => $this->vienna->diocese_id,
            'default_church_id' => $this->munich->id,
        ]);
        $this->munichAdmin->assignRole('Parish Admin');

        UserChurchAccess::create([
            'user_id' => $this->munichAdmin->id,
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->munich->id,
            'access_scope' => 'church_scoped',
            'status' => 'active'
        ]);
    }

    public function test_parish_admin_scoping_limits(): void
    {
        // 1. Create page for Munich church
        $munichPage = WebsitePage::create([
            'diocese_id' => $this->munich->diocese_id,
            'church_id' => $this->munich->id,
            'title' => 'Munich Main Page',
            'slug' => 'munich-main-page',
            'page_type' => 'custom',
            'content' => 'Details',
            'status' => 'draft',
            'created_by' => $this->munichAdmin->id
        ]);

        // 2. Vienna admin tries to update Munich page -> should fail with 403
        $response1 = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->putJson("/api/v1/cms/pages/{$munichPage->id}", [
                'title' => 'Vienna Hacked Title'
            ]);
        $response1->assertStatus(403);

        // 3. Munich admin can update Munich page -> should pass
        $response2 = $this->actingAs($this->munichAdmin, 'sanctum')
            ->putJson("/api/v1/cms/pages/{$munichPage->id}", [
                'title' => 'Munich Approved Title'
            ]);
        $response2->assertStatus(200);
    }
}
