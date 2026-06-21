<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\CourseBatch;
use App\Models\CourseRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseEventIncomeLinkTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $vienna;
    protected $batch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();
        $course = \App\Models\Course::first();
        $this->batch = CourseBatch::create([
            'course_id' => $course->id,
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'batch_code' => 'BATCH-TEST-2026',
            'batch_name' => 'Test Batch',
            'start_datetime' => '2026-06-15 00:00:00',
            'end_datetime' => '2026-07-15 00:00:00',
            'mode' => 'online',
            'status' => 'active',
            'created_by' => $this->superAdmin->id,
        ]);
    }

    public function test_link_course_payment_to_income(): void
    {
        // 1. Create a course registration marked as paid
        $registration = CourseRegistration::create([
            'course_batch_id' => $this->batch->id,
            'diocese_id' => $this->vienna->diocese_id,
            'church_id' => $this->vienna->id,
            'external_name' => 'John Fee Payer',
            'external_email' => 'john@fee.com',
            'registration_type' => 'external',
            'payment_status' => 'paid',
            'payment_reference' => 'TXN-9988',
            'registration_status' => 'confirmed',
        ]);

        // 2. Link payment via finance route
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/finance/link-registration', [
                'source_type' => 'course_registration',
                'source_id' => $registration->id,
                'amount' => 50.00,
                'payment_method' => 'card',
                'payment_reference' => 'TXN-9988'
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.source_type', 'course_registration')
            ->assertJsonPath('data.source_id', $registration->id)
            ->assertJsonPath('data.status', 'received');

        // 3. Confirm receipt generated
        $this->assertNotNull($response->json('data.receipt_id'));
    }
}
