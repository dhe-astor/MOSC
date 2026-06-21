<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\Church;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseBatchTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $viennaChurch;
    protected $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->viennaChurch = Church::where('short_name', 'Vienna')->first();
        $this->course = Course::first();
    }

    public function test_diocese_admin_can_create_diocesan_batch(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/course-batches', [
                'course_id' => $this->course->id,
                'church_id' => null,
                'batch_name' => 'Diocese Syriac Language 2026',
                'start_datetime' => '2026-07-01 09:00:00',
                'end_datetime' => '2026-08-01 17:00:00',
                'mode' => 'online',
                'max_participants' => 100,
                'fee_amount' => 30.00,
                'currency' => 'EUR',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('course_batches', [
            'batch_name' => 'Diocese Syriac Language 2026',
            'church_id' => null
        ]);
    }

    public function test_parish_admin_cannot_create_diocesan_batch(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/course-batches', [
                'course_id' => $this->course->id,
                'church_id' => null,
                'batch_name' => 'Invalid Diocesan Batch',
                'start_datetime' => '2026-07-01 09:00:00',
                'end_datetime' => '2026-08-01 17:00:00',
                'mode' => 'online',
            ]);

        $response->assertStatus(403);
    }

    public function test_parish_admin_can_create_parish_batch(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/course-batches', [
                'course_id' => $this->course->id,
                'church_id' => $this->viennaChurch->id,
                'batch_name' => 'Vienna Syriac Batch 1',
                'start_datetime' => '2026-07-01 09:00:00',
                'end_datetime' => '2026-08-01 17:00:00',
                'mode' => 'offline',
                'venue' => 'Vienna Parish Hall',
                'max_participants' => 30,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('course_batches', [
            'batch_name' => 'Vienna Syriac Batch 1',
            'church_id' => $this->viennaChurch->id
        ]);
    }

    public function test_unique_batch_code_generation(): void
    {
        // Create first batch
        $response1 = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/course-batches', [
                'course_id' => $this->course->id,
                'church_id' => null,
                'batch_name' => 'Syriac 2026 A',
                'start_datetime' => '2026-07-01 09:00:00',
                'end_datetime' => '2026-08-01 17:00:00',
                'mode' => 'online',
            ]);

        $code1 = $response1->json('data.batch_code');

        // Create second batch
        $response2 = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/course-batches', [
                'course_id' => $this->course->id,
                'church_id' => null,
                'batch_name' => 'Syriac 2026 B',
                'start_datetime' => '2026-07-01 09:00:00',
                'end_datetime' => '2026-08-01 17:00:00',
                'mode' => 'online',
            ]);

        $code2 = $response2->json('data.batch_code');

        $this->assertNotEmpty($code1);
        $this->assertNotEmpty($code2);
        $this->assertNotEquals($code1, $code2);
    }

    public function test_can_add_sessions_to_batch(): void
    {
        $batch = CourseBatch::create([
            'course_id' => $this->course->id,
            'diocese_id' => $this->course->diocese_id,
            'church_id' => $this->viennaChurch->id,
            'batch_name' => 'Test Batch',
            'batch_code' => 'MSOC-COURSE-TEST-2026-0001',
            'start_datetime' => '2026-07-01 09:00:00',
            'end_datetime' => '2026-08-01 17:00:00',
            'mode' => 'online',
            'status' => 'draft',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson("/api/v1/course-batches/{$batch->id}/sessions", [
                'title' => 'Intro to Syriac Alphabets',
                'session_date' => '2026-07-05',
                'start_time' => '10:00',
                'end_time' => '12:00',
                'session_order' => 1,
                'attendance_required' => true,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('course_sessions', [
            'course_batch_id' => $batch->id,
            'title' => 'Intro to Syriac Alphabets'
        ]);
    }
}
