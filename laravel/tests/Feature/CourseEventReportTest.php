<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseEventReportTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $vienna;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();
    }

    public function test_courses_and_events_reports(): void
    {
        // Seed course
        $course = Course::create([
            'diocese_id' => $this->vienna->diocese_id,
            'name' => 'Intro to Sacraments',
            'slug' => 'intro-to-sacraments',
            'course_type' => 'catechism',
            'description' => 'Course description',
            'duration_hours' => 10,
            'status' => 'active',
            'created_by' => $this->superAdmin->id
        ]);

        CourseBatch::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'course_id' => $course->id,
            'batch_code' => 'BATCH-001',
            'batch_name' => 'Batch 1',
            'start_datetime' => '2026-07-01 10:00:00',
            'end_datetime' => '2026-07-30 12:00:00',
            'mode' => 'online',
            'status' => 'open',
            'created_by' => $this->viennaAdmin->id
        ]);

        Event::create([
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'title' => 'Parish Feast',
            'slug' => 'parish-feast',
            'event_type' => 'feast',
            'description' => 'Feast description',
            'start_datetime' => '2026-08-15 09:00:00',
            'end_datetime' => '2026-08-15 18:00:00',
            'mode' => 'in_person',
            'location_name' => 'Vienna Church Hall',
            'status' => 'published',
            'created_by' => $this->viennaAdmin->id
        ]);

        $coursesResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/reports/run', [
                'report_key' => 'courses_summary'
            ]);

        $coursesResponse->assertStatus(200);

        $eventsResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/reports/run', [
                'report_key' => 'events_summary'
            ]);

        $eventsResponse->assertStatus(200);
    }
}
