<?php

namespace Tests\Feature;

use App\Services\AuditLogService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_keys_are_masked_in_audit_logs(): void
    {
        $this->seed();
        $admin = User::where('email', 'superadmin@msoc-europe.org')->first();
        
        $oldValues = [
            'password' => 'SuperSecretPassword@1',
            'email' => 'admin@example.com',
            'two_factor_secret' => 'A1B2C3D4'
        ];

        $newValues = [
            'password' => 'AnotherSecretPassword!2',
            'email' => 'admin@example.com',
            'two_factor_secret' => 'E5F6G7H8'
        ];

        $this->actingAs($admin);

        $log = AuditLogService::log(
            'security',
            'test_masking',
            'Testing audit log sensitive field masking',
            $oldValues,
            $newValues
        );

        $this->assertEquals('[MASKED]', $log->old_values['password']);
        $this->assertEquals('[MASKED]', $log->new_values['password']);
        $this->assertEquals('[MASKED]', $log->old_values['two_factor_secret']);
        $this->assertEquals('[MASKED]', $log->new_values['two_factor_secret']);
        $this->assertEquals('admin@example.com', $log->old_values['email']);
    }
}
