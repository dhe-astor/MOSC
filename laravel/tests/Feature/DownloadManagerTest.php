<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\WebsiteDownload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DownloadManagerTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $vienna;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();
    }

    public function test_upload_and_download_flow(): void
    {
        $file = UploadedFile::fake()->create('sunday_school_curriculum.pdf', 500, 'application/pdf');

        // 1. Upload downloadable file
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/cms/downloads', [
                'title' => 'Sunday School Syllabus 2026',
                'church_id' => $this->vienna->id,
                'description' => 'Official curriculum pdf',
                'download_type' => 'sunday_school_book',
                'file' => $file,
                'visibility' => 'public'
            ]);

        $response->assertStatus(210);
        $downloadId = $response->json('data.id');

        $this->assertDatabaseHas('website_downloads', [
            'id' => $downloadId,
            'title' => 'Sunday School Syllabus 2026',
            'file_name' => 'sunday_school_curriculum.pdf'
        ]);

        // Mock approve and publish to make downloadable
        $download = WebsiteDownload::find($downloadId);
        $download->update(['status' => 'published']);

        // 2. Perform download via authenticated route
        $downloadResponse = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->get("/api/v1/cms/downloads/{$downloadId}/download");

        $downloadResponse->assertStatus(200);
        
        $this->assertEquals(1, $download->fresh()->download_count);
    }
}
