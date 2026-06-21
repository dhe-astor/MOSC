<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\MediaGallery;
use App\Models\MediaItem;
use App\Models\Member;
use App\Models\Family;
use App\Services\MediaGalleryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Carbon\Carbon;

class MediaGalleryTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $vienna;
    protected $family;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('public');

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();

        $this->family = Family::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_code' => 'FAM-TEST-01',
            'family_name' => 'Test Family',
            'primary_phone' => '+43 664 123456',
            'address_line_1' => 'Vienna St 1',
            'city' => 'Vienna',
            'created_by' => $this->superAdmin->id,
            'gdpr_consent' => true
        ]);
    }

    public function test_create_gallery_and_upload_items(): void
    {
        $gallery = MediaGallery::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'title' => 'Parish Picnic 2026',
            'slug' => 'parish-picnic-2026',
            'gallery_type' => 'mixed',
            'status' => 'draft',
            'created_by' => $this->viennaAdmin->id
        ]);

        // 1. Upload private draft image item
        $file = UploadedFile::fake()->image('picnic_photo.jpg');
        $itemResponse = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson("/api/v1/cms/galleries/{$gallery->id}/items", [
                'media_type' => 'image',
                'file' => $file,
                'title' => 'Picnic group shot',
                'sort_order' => 1
            ]);

        $itemResponse->assertStatus(200);
        $itemId = $itemResponse->json('data.id');

        $this->assertDatabaseHas('media_items', [
            'id' => $itemId,
            'media_type' => 'image'
        ]);

        // 2. Add YouTube video item
        $videoResponse = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson("/api/v1/cms/galleries/{$gallery->id}/items", [
                'media_type' => 'video',
                'external_video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'title' => 'Picnic drone video'
            ]);

        $videoResponse->assertStatus(200);

        $this->assertDatabaseHas('media_items', [
            'media_type' => 'video',
            'external_video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ'
        ]);
    }

    public function test_child_photo_privacy_rules(): void
    {
        // 1. Create a child member under 18 with NO photo consent
        $childNoConsent = Member::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_id' => $this->family->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'full_name' => 'John Doe',
            'relationship_to_head' => 'son',
            'date_of_birth' => Carbon::now()->subYears(10)->toDateString(), // 10 years old (child)
            'photo_publication_consent' => false,
            'created_by' => $this->superAdmin->id
        ]);

        // 2. Create a child member under 18 WITH photo consent
        $childWithConsent = Member::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_id' => $this->family->id,
            'first_name' => 'Sarah',
            'last_name' => 'Doe',
            'full_name' => 'Sarah Doe',
            'relationship_to_head' => 'daughter',
            'date_of_birth' => Carbon::now()->subYears(8)->toDateString(), // 8 years old
            'photo_publication_consent' => true,
            'created_by' => $this->superAdmin->id
        ]);

        // 3. Create an adult member
        $adult = Member::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'family_id' => $this->family->id,
            'first_name' => 'James',
            'last_name' => 'Doe',
            'full_name' => 'James Doe',
            'relationship_to_head' => 'head',
            'date_of_birth' => Carbon::now()->subYears(40)->toDateString(), // 40 years old
            'photo_publication_consent' => false, // adults consent doesn't block age rules unless child
            'created_by' => $this->superAdmin->id
        ]);

        $gallery = MediaGallery::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'title' => 'Camp 2026',
            'slug' => 'camp-2026',
            'gallery_type' => 'photo',
            'status' => 'draft',
            'created_by' => $this->viennaAdmin->id
        ]);

        // Item 1: Tagged with child no consent
        $item1 = MediaItem::create([
            'media_gallery_id' => $gallery->id,
            'media_type' => 'image',
            'created_by' => $this->superAdmin->id
        ]);
        $item1->taggedMembers()->attach($childNoConsent->id);

        // Item 2: Tagged with child with consent
        $item2 = MediaItem::create([
            'media_gallery_id' => $gallery->id,
            'media_type' => 'image',
            'created_by' => $this->superAdmin->id
        ]);
        $item2->taggedMembers()->attach($childWithConsent->id);

        // Item 3: Tagged with adult
        $item3 = MediaItem::create([
            'media_gallery_id' => $gallery->id,
            'media_type' => 'image',
            'created_by' => $this->superAdmin->id
        ]);
        $item3->taggedMembers()->attach($adult->id);

        // Verify rules
        $this->assertFalse(MediaGalleryService::passesChildPrivacyCheck($item1->fresh()));
        $this->assertTrue(MediaGalleryService::passesChildPrivacyCheck($item2->fresh()));
        $this->assertTrue(MediaGalleryService::passesChildPrivacyCheck($item3->fresh()));
    }
}
