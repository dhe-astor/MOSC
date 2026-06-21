<?php

namespace App\Services;

use App\Models\FinanceReceipt;
use App\Models\FinanceReceiptLine;
use App\Models\FinanceIncomeHeader;
use App\Models\FinanceIncomeLine;
use App\Models\User;
use App\Services\ReceiptNumberService;
use App\Services\AuditLogService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Exception;

class ReceiptService
{
    /**
     * Generate a receipt for a confirmed income line.
     */
    public static function generateReceiptForIncomeLine(FinanceIncomeHeader $header, FinanceIncomeLine $line, User $user): FinanceReceipt
    {
        $dioceseId = $header->church?->diocese_id ?? $user->default_diocese_id ?? 1;
        $year = (int)date('Y', strtotime($header->income_date));

        // 1. Generate unique sequence receipt number
        $receiptNumber = ReceiptNumberService::generateNextNumber($dioceseId, $header->church_id, $year);

        // 2. Determine payer details
        $payerName = '';
        $payerEmail = null;
        $payerPhone = null;

        if ($line->member_id) {
            $line->loadMissing('member');
            $payerName = $line->member->full_name;
            $payerEmail = $line->member->email;
            $payerPhone = $line->member->primary_phone;
        } else {
            $payerName = $line->donor_name ?: 'General Offering';
        }

        // 3. Determine payment method from money account type
        $header->loadMissing('moneyAccount');
        $paymentMethod = $header->moneyAccount ? ($header->moneyAccount->type === 'bank' ? 'bank_transfer' : 'cash') : 'cash';

        $description = $line->remarks ?: ($line->incomeHead ? $line->incomeHead->name : 'Income Contribution');

        // 4. Save Receipt PDF privately
        $pdfData = [
            'diocese_name' => $header->church?->diocese?->name ?? 'MSOC Europe Diocese',
            'church_name' => $header->church ? $header->church->name : 'MSOC Europe Consolidated',
            'church_address' => $header->church ? $header->church->address_line_1 : 'Vienna, Austria',
            'receipt_number' => $receiptNumber,
            'receipt_date' => $header->income_date,
            'payer_name' => $payerName,
            'payer_email' => $payerEmail,
            'payer_phone' => $payerPhone,
            'description' => $description,
            'payment_reference' => $header->reference_no,
            'currency' => $header->moneyAccount?->currency ?? 'EUR',
            'amount' => $line->amount,
            'payment_method' => $paymentMethod,
            'issued_by' => $user->name,
            'lines' => [
                [
                    'description' => $description,
                    'amount' => $line->amount,
                ]
            ],
        ];

        $pdf = Pdf::loadView('pdf.receipt', $pdfData);
        $pdfPath = "private/receipts/{$receiptNumber}.pdf";
        Storage::put($pdfPath, $pdf->output());

        // 5. Create FinanceReceipt
        $receipt = FinanceReceipt::create([
            'income_header_id' => $header->id,
            'receipt_number' => $receiptNumber,
            'receipt_date' => $header->income_date,
            'received_from' => $payerName,
            'member_id' => $line->member_id,
            'payment_method' => $paymentMethod,
            'total_amount' => $line->amount,
            'status' => 'active',
            'pdf_path' => $pdfPath,
            'issued_by' => $user->id,
        ]);

        // 6. Create FinanceReceiptLine
        FinanceReceiptLine::create([
            'receipt_id' => $receipt->id,
            'income_line_id' => $line->id,
            'income_head_id' => $line->income_head_id,
            'amount' => $line->amount,
            'description' => $description,
        ]);

        // 7. Audit Log
        AuditLogService::log(
            'Finance',
            'Receipt Generated',
            "Generated receipt {$receiptNumber} for amount {$line->amount}",
            null,
            $receipt->toArray(),
            $receipt,
            $header->church_id,
            $dioceseId
        );

        // 8. Trigger notification if applicable
        try {
            \App\Services\NotificationTriggerService::triggerReceiptGenerated($receipt);
        } catch (\Throwable $e) {
            // Ignore if trigger not fully adapted yet
        }

        return $receipt;
    }
}
