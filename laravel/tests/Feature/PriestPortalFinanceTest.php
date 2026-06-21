<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Church;
use App\Models\Priest;
use App\Models\FinancePriestPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PriestPortalFinanceTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $priest;
    protected $church;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->church = Church::first();

        // Create user for the priest
        $this->user = User::factory()->create([
            'email' => 'priest.test@msoc-europe.org',
        ]);
        $this->user->assignRole('Priest / Vicar');

        // Link user to priest profile
        $this->priest = Priest::create([
            'diocese_id' => 1,
            'user_id' => $this->user->id,
            'display_name' => 'Rev. Fr. Test Koch',
            'clergy_type' => 'priest',
            'status' => 'active',
        ]);
    }

    public function test_priest_can_list_own_payments(): void
    {
        FinancePriestPayment::create([
            'church_id' => $this->church->id,
            'priest_id' => $this->priest->id,
            'payment_date' => now()->toDateString(),
            'amount' => 150.00,
            'status' => 'confirmed',
            'type' => 'stipend',
            'description' => 'Monthly stipend payment',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/priest/finance');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $this->assertEquals(150.00, $response->json('data.0.amount'));
    }

    public function test_priest_cannot_see_other_priest_payments(): void
    {
        $otherUser = User::factory()->create();
        $otherPriest = Priest::create([
            'diocese_id' => 1,
            'user_id' => $otherUser->id,
            'display_name' => 'Rev. Fr. Other Koch',
            'clergy_type' => 'priest',
            'status' => 'active',
        ]);

        FinancePriestPayment::create([
            'church_id' => $this->church->id,
            'priest_id' => $otherPriest->id,
            'payment_date' => now()->toDateString(),
            'amount' => 250.00,
            'status' => 'confirmed',
            'type' => 'stipend',
            'description' => 'Other priest stipend',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/priest/finance');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    public function test_priest_can_download_own_advice_pdf(): void
    {
        Storage::fake('local');
        $filePath = 'private/priest_payments/ADV-12345.pdf';
        Storage::put($filePath, 'PDF Content');

        $payment = FinancePriestPayment::create([
            'church_id' => $this->church->id,
            'priest_id' => $this->priest->id,
            'payment_date' => now()->toDateString(),
            'amount' => 150.00,
            'status' => 'confirmed',
            'type' => 'stipend',
            'description' => "Monthly stipend payment | Advice PDF: private/priest_payments/ADV-12345.pdf",
        ]);

        $this->user->update(['two_factor_enabled' => true]);

        \Laravel\Sanctum\Sanctum::actingAs($this->user, ['2fa_verified']);
        $response = $this->getJson("/api/v1/priest/finance/{$payment->id}/advice");

        $response->assertStatus(200);
    }
}
