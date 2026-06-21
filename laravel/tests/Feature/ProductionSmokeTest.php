<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductionSmokeTest extends TestCase
{
    public function test_production_smoke_health_check_returns_healthy(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
        ]);

        $response = $this->get('https://localhost/api/health');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'OK'
        ]);
        $response->assertHeader('Strict-Transport-Security');
    }
}
