<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_security_headers_are_present(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertHeader('Strict-Transport-Security');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Content-Security-Policy');
        $response->assertHeader('Permissions-Policy');
    }

    public function test_https_redirection_in_production(): void
    {
        config(['app.env' => 'production']);

        $response = $this->get('/api/health');
        
        $response->assertRedirect();
        $this->assertStringStartsWith('https://', $response->headers->get('Location'));
    }
}
