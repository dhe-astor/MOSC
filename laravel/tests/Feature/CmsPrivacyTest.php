<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_private_endpoints_block_guests(): void
    {
        // 1. Authenticated routes must block guest requests with 401
        $this->getJson('/api/v1/cms/pages')->assertStatus(401);
        $this->getJson('/api/v1/cms/news')->assertStatus(401);
        $this->getJson('/api/v1/cms/settings')->assertStatus(401);
        $this->getJson('/api/v1/cms/approvals')->assertStatus(401);
        
        // 2. Members, families, certificates, and finance must be protected
        $this->getJson('/api/v1/members')->assertStatus(401);
        $this->getJson('/api/v1/families')->assertStatus(401);
        $this->getJson('/api/v1/finance/donations')->assertStatus(401);
        $this->getJson('/api/v1/finance/expenses')->assertStatus(401);
        $this->getJson('/api/v1/certificates')->assertStatus(401);
    }
}
