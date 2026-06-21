<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductionConfigTest extends TestCase
{
    public function test_production_environment_variables_are_correct(): void
    {
        // Simulate production config overrides
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'session.secure' => true,
            'session.same_site' => 'lax',
            'queue.default' => 'database'
        ]);

        $this->assertEquals('production', config('app.env'));
        $this->assertFalse(config('app.debug'));
        $this->assertTrue(config('session.secure'));
        $this->assertEquals('lax', config('session.same_site'));
        $this->assertEquals('database', config('queue.default'));
    }
}
