<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Course;
use App\Models\Diocese;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $viennaAdmin;
    protected $diocese;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->viennaAdmin = User::where('email', 'vienna.admin@msoc-europe.org')->first();
        $this->diocese = Diocese::first();
    }

    public function test_diocese_admin_can_create_course(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/courses', [
                'name' => 'Syriac Language Basic Course',
                'course_type' => 'syriac_language',
                'description' => 'A basic course in Syriac language.',
                'eligibility' => 'All parish members',
                'default_fee_amount' => 50,
                'currency' => 'EUR',
                'certificate_enabled' => true,
                'feedback_required' => true,
                'attendance_required_percentage' => 75,
                'show_on_portal' => true
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('courses', [
            'name' => 'Syriac Language Basic Course',
            'course_type' => 'syriac_language'
        ]);
    }

    public function test_parish_admin_cannot_create_course(): void
    {
        $response = $this->actingAs($this->viennaAdmin, 'sanctum')
            ->postJson('/api/v1/courses', [
                'name' => 'Liturgical Music Course',
                'course_type' => 'liturgical_course',
            ]);

        $response->assertStatus(403);
    }

    public function test_diocese_admin_can_update_course(): void
    {
        $course = Course::first();

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson("/api/v1/courses/{$course->id}", [
                'name' => 'Updated Course Name',
                'description' => 'Updated Description',
                'status' => 'active'
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'name' => 'Updated Course Name'
        ]);
    }

    public function test_diocese_admin_can_activate_and_deactivate_course(): void
    {
        $course = Course::first();

        // Deactivate
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/courses/{$course->id}/deactivate");

        $response->assertStatus(200);
        $this->assertEquals('inactive', $course->fresh()->status);

        // Activate
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/courses/{$course->id}/activate");

        $response->assertStatus(200);
        $this->assertEquals('active', $course->fresh()->status);
    }
}
