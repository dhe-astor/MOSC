<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class QueueSchedulerHealthTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'superadmin@msoc-europe.org')->first();
    }

    public function test_scheduler_status_endpoint_returns_active_when_recently_run(): void
    {
        // Set last run timestamp
        Cache::put('scheduler_last_run', time());

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/system/scheduler-status');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'status' => 'active'
            ]
        ]);
    }

    public function test_queue_status_endpoint_returns_metrics(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/system/queue-status');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'driver',
                'failed_jobs_count',
                'status'
            ]
        ]);
    }
}
