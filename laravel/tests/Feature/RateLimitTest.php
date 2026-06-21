<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    public function test_rate_limiters_are_registered(): void
    {
        $this->assertTrue(RateLimiter::remaining('login:test-ip', 5) <= 5);
        $this->assertTrue(RateLimiter::remaining('api:test-id', 60) <= 60);
        $this->assertTrue(RateLimiter::remaining('2fa_verify:test-id', 5) <= 5);
    }
}
