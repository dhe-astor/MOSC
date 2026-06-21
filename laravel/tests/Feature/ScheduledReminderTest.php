<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ScheduledReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ScheduledReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_schedule_custom_reminder(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/communications/reminders', [
                'reminder_type' => 'custom',
                'title' => 'Custom reminder',
                'body' => 'Remember to check reports.',
                'scheduled_at' => now()->addMinutes(10)->format('Y-m-d H:i:s'),
                'channel' => 'in_app'
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('scheduled_reminders', [
            'title' => 'Custom reminder',
            'status' => 'scheduled'
        ]);
    }

    public function test_artisan_reminder_command_processes_due_reminders(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();

        // Create a reminder that is due
        $reminder = ScheduledReminder::create([
            'diocese_id' => 1,
            'reminder_type' => 'custom',
            'title' => 'Due reminder',
            'body' => 'Needs sending.',
            'scheduled_at' => now()->subMinutes(5),
            'channel' => 'in_app',
            'status' => 'scheduled',
            'created_by' => $admin->id
        ]);

        // Run artisan command
        $exitCode = Artisan::call('communications:process-reminders');

        $this->assertEquals(0, $exitCode);
        $this->assertEquals('sent', $reminder->fresh()->status);
    }
}
