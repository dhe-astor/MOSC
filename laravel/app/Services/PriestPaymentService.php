<?php

namespace App\Services;

use App\Models\FinancePriestPayment;
use App\Models\FinanceExpenseHeader;
use App\Models\FinanceExpenseLine;
use App\Models\User;
use App\Services\ExpenseEntryService;
use App\Services\AuditLogService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Exception;

class PriestPaymentService
{
    /**
     * Calculate mileage travel claim amount.
     */
    public static function calculateTravelAmount(float $distanceKm, float $ratePerKm): float
    {
        return (float)($distanceKm * $ratePerKm);
    }

    /**
     * Create a priest payment claim.
     */
    public static function createPaymentClaim(array $data, User $user): FinancePriestPayment
    {
        $data['status'] = 'draft';
        
        if ($data['type'] === 'travel') {
            $data['amount'] = self::calculateTravelAmount(
                (float)($data['travel_distance_km'] ?? 0.0),
                (float)($data['travel_rate_per_km'] ?? 0.30)
            );
        }

        $payment = FinancePriestPayment::create($data);

        AuditLogService::log(
            'Finance',
            'Priest Payment Claim Created',
            "Created priest payment claim type {$payment->type} amount {$payment->amount}",
            null,
            $payment->toArray(),
            $payment,
            $payment->church_id,
            $user->default_diocese_id ?? 1
        );

        return $payment;
    }

    /**
     * Confirm a priest payment claim, generate advice PDF, and create matching expense voucher.
     */
    public static function confirmPaymentClaim(int $id, User $user): FinancePriestPayment
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($id, $user) {
            $payment = FinancePriestPayment::findOrFail($id);

            if ($payment->status !== 'draft') {
                throw new Exception("Only draft payment claims can be confirmed.");
            }

            $payment->loadMissing(['priest', 'church']);
            $priestName = $payment->priest ? $payment->priest->full_name : 'Priest';

            // 1. Generate payment advice PDF and save privately
            $adviceNumber = 'ADV-' . $payment->id . '-' . time();
            $pdfData = [
                'advice_number' => $adviceNumber,
                'payment_date' => $payment->payment_date,
                'priest_name' => $priestName,
                'type' => strtoupper($payment->type),
                'amount' => $payment->amount,
                'description' => $payment->description ?: "Priest payment advice for {$payment->type}",
                'travel_distance_km' => $payment->travel_distance_km,
                'travel_rate_per_km' => $payment->travel_rate_per_km,
                'church_name' => $payment->church ? $payment->church->name : 'Consolidated',
                'issued_by' => $user->name,
            ];

            // Render simple advice PDF view (or reuse receipt layout styled for priest advice)
            $pdf = Pdf::loadView('pdf.receipt', [
                'diocese_name' => 'MSOC Europe Diocese',
                'church_name' => $payment->church ? $payment->church->name : 'Consolidated',
                'church_address' => $payment->church ? $payment->church->address_line_1 : 'Europe',
                'receipt_number' => $adviceNumber,
                'receipt_date' => $payment->payment_date,
                'payer_name' => $priestName,
                'payer_email' => null,
                'payer_phone' => null,
                'description' => $payment->description ?: "Priest Payment: " . strtoupper($payment->type),
                'payment_reference' => "Priest ID: {$payment->priest_id}",
                'currency' => 'EUR',
                'amount' => $payment->amount,
                'payment_method' => 'bank_transfer',
                'issued_by' => $user->name,
            ]);

            $pdfPath = "private/priest_payments/{$adviceNumber}.pdf";
            Storage::put($pdfPath, $pdf->output());

            // 2. Find matching expense head
            $expenseHeadCode = 'EXP-001'; // Default stipend
            if ($payment->type === 'travel') {
                $expenseHeadCode = 'EXP-002';
            } elseif ($payment->type === 'allowance') {
                $expenseHeadCode = 'EXP-003';
            }
            $expenseHead = \App\Models\FinanceExpenseHead::where('code', $expenseHeadCode)->first();
            $fundClass = \App\Models\FinanceFundClass::where('code', 'PRI')->first() 
                ?: \App\Models\FinanceFundClass::where('code', 'GEN')->first();

            // Find default church money account (preferably bank)
            $moneyAccount = \App\Models\FinanceMoneyAccount::where('church_id', $payment->church_id)
                ->where('type', 'bank')
                ->where('is_active', true)
                ->first()
                ?: \App\Models\FinanceMoneyAccount::where('church_id', $payment->church_id)
                ->where('is_active', true)
                ->first();

            if (!$moneyAccount) {
                throw new Exception("No active money account configured for this church.");
            }

            // 3. Create matching expense voucher
            $expenseHeader = ExpenseEntryService::createExpense(
                [
                    'church_id' => $payment->church_id,
                    'expense_date' => $payment->payment_date,
                    'money_account_id' => $moneyAccount->id,
                    'voucher_number' => 'VOUCH-PRIEST-' . $payment->id . '-' . time(),
                    'payee_name' => $priestName,
                    'remarks' => $payment->description ?: "Auto-generated voucher for priest {$payment->type} payment",
                ],
                [
                    [
                        'expense_head_id' => $expenseHead ? $expenseHead->id : 1,
                        'fund_class_id' => $fundClass ? $fundClass->id : 1,
                        'amount' => $payment->amount,
                        'remarks' => "Priest {$payment->type} reimbursement",
                    ]
                ],
                null,
                $user
            );

            // 4. Link claim to expense voucher
            $payment->update([
                'status' => 'confirmed',
                'expense_header_id' => $expenseHeader->id,
                'description' => $payment->description . " | Linked Voucher: " . $expenseHeader->voucher_number . " | Advice PDF: " . $pdfPath,
            ]);

            AuditLogService::log(
                'Finance',
                'Priest Payment Claim Confirmed',
                "Confirmed priest payment ID {$payment->id} and generated voucher {$expenseHeader->voucher_number}",
                null,
                $payment->toArray(),
                $payment,
                $payment->church_id,
                $user->default_diocese_id ?? 1
            );

            return $payment;
        });
    }
}
