<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Diocese;
use App\Services\ReceiptNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptSequenceTest extends TestCase
{
    use RefreshDatabase;

    protected $diocese;
    protected $vienna;
    protected $rome;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->diocese = Diocese::first();
        $this->vienna = Church::where('short_name', 'Vienna')->first();
        $this->rome = Church::where('short_name', 'Rome')->first();
    }

    public function test_receipt_number_generation_and_consecutive_increment(): void
    {
        // Generate first number for Vienna
        $num1 = ReceiptNumberService::generateNextNumber($this->diocese->id, $this->vienna->id, 2026);
        // Membership code prefix for Vienna is usually VIE or Vienna shortname prefix
        $this->assertStringStartsWith('VIE-', $num1);
        $this->assertStringContainsString('-2026-000001', $num1);

        // Generate second number for Vienna
        $num2 = ReceiptNumberService::generateNextNumber($this->diocese->id, $this->vienna->id, 2026);
        $this->assertStringContainsString('-2026-000002', $num2);
    }

    public function test_receipt_number_generation_for_diocese(): void
    {
        // Diocese-level generation (church_id is null)
        $num = ReceiptNumberService::generateNextNumber($this->diocese->id, null, 2026);
        $this->assertEquals('DIO-2026-000001', $num);

        $num2 = ReceiptNumberService::generateNextNumber($this->diocese->id, null, 2026);
        $this->assertEquals('DIO-2026-000002', $num2);
    }

    public function test_sequence_isolation_between_parishes_and_years(): void
    {
        // Vienna 2026
        $vienna2026 = ReceiptNumberService::generateNextNumber($this->diocese->id, $this->vienna->id, 2026);
        $this->assertStringContainsString('-2026-000001', $vienna2026);

        // Rome 2026
        $rome2026 = ReceiptNumberService::generateNextNumber($this->diocese->id, $this->rome->id, 2026);
        $this->assertStringContainsString('-2026-000001', $rome2026);

        // Vienna 2027
        $vienna2027 = ReceiptNumberService::generateNextNumber($this->diocese->id, $this->vienna->id, 2027);
        $this->assertStringContainsString('-2027-000001', $vienna2027);
    }
}
