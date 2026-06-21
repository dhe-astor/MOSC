<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\Event;
use App\Models\Church;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseEventScopingTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $herneAdmin;
    protected $viennaChurch;
    protected $herneChurch;
    protected $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->herneAdmin = User::where('email', 'herne.admin@msoc-europe.org')->first();
        
        $this->viennaChurch = Church::where('short_name', 'Vienna')->first();
        $this->herneChurch = Church::where('short_name', 'Herne')->first();
        
        $this->course = Course::first();
    }

    public function test_parish_admin_cannot_view_other_parish_batches(): void
    {
        // Create batch in Herne
        $herneBatch = CourseBatch::create([
            'course_id' => $this->course->id,
            'diocese_id' => $this->course->diocese_id,
            'church_id' => $this->herneChurch->id,
            'batch_name' => 'Herne Syriac Batch',
            'batch_code' => 'MSOC-COURSE-SYR-2026-1001',
            'start_datetime' => '2026-07-01 09:00:00',
            'end_datetime' => '2026-08-01 17:00:00',
            'mode' => 'offline',
            'status' => 'open',
            'created_by' => $this->superAdmin->id,
        ]);

        // Vienna Admin listing batches should not see Herne batch
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->getJson('/api/v1/course-batches');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($herneBatch->id, $ids);
    }

    public function test_parish_admin_can_view_diocesan_batches(): void
    {
        // Create diocese batch (church_id = null)
        $dioceseBatch = CourseBatch::create([
            'course_id' => $this->course->id,
            'diocese_id' => $this->course->diocese_id,
            'church_id' => null,
            'batch_name' => 'Diocese Syriac Batch',
            'batch_code' => 'MSOC-COURSE-SYR-2026-2001',
            'start_datetime' => '2026-07-01 09:00:00',
            'end_datetime' => '2026-08-01 17:00:00',
            'mode' => 'online',
            'status' => 'open',
            'created_by' => $this->superAdmin->id,
        ]);

        // Vienna Admin listing batches should see the diocesan batch
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->getJson('/api/v1/course-batches');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($dioceseBatch->id, $ids);
    }

    public function test_parish_admin_cannot_edit_diocesan_batches(): void
    {
        // Create diocese batch (church_id = null)
        $dioceseBatch = CourseBatch::create([
            'course_id' => $this->course->id,
            'diocese_id' => $this->course->diocese_id,
            'church_id' => null,
            'batch_name' => 'Diocese Syriac Batch',
            'batch_code' => 'MSOC-COURSE-SYR-2026-2002',
            'start_datetime' => '2026-07-01 09:00:00',
            'end_datetime' => '2026-08-01 17:00:00',
            'mode' => 'online',
            'status' => 'open',
            'created_by' => $this->superAdmin->id,
        ]);

        // Vienna Admin tries to update it
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->putJson("/api/v1/course-batches/{$dioceseBatch->id}", [
                'batch_name' => 'Hacked Name',
                'start_datetime' => '2026-07-01 09:00:00',
                'end_datetime' => '2026-08-01 17:00:00',
                'mode' => 'online',
            ]);

        $response->assertStatus(403);
    }
}
