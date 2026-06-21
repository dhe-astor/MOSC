<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\NotificationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_list_templates(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();
        
        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/communications/templates');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_non_admin_cannot_list_templates(): void
    {
        $priest = User::where('email', 'priest@msoc-europe.org')->first();
        
        $response = $this->actingAs($priest, 'sanctum')
            ->getJson('/api/v1/communications/templates');

        $response->assertStatus(403);
    }

    public function test_admin_can_create_template(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();
        
        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/communications/templates', [
                'template_key' => 'custom_welcome',
                'name' => 'Custom Welcome Mail',
                'channel' => 'email',
                'subject' => 'Welcome to our Parish, {{name}}!',
                'body' => 'Hello {{name}}, we are glad to have you.',
                'variables' => ['name']
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('notification_templates', [
            'template_key' => 'custom_welcome',
            'channel' => 'email'
        ]);
    }

    public function test_cannot_delete_system_templates(): void
    {
        $admin = User::where('email', 'admin@msoc-europe.org')->first();
        
        // Find a system template
        $template = NotificationTemplate::where('is_system', true)->first();
        $this->assertNotNull($template);

        // Attempting to archive or delete
        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/communications/templates/{$template->id}/archive");

        $response->assertStatus(400); // Bad request because system template cannot be archived/deleted
    }
}
