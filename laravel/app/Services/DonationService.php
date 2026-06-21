<?php

namespace App\Services;

use App\Models\Donation;
use App\Models\User;
use App\Services\ReceiptGenerationService;
use App\Services\AuditLogService;
use Exception;
use Illuminate\Support\Facades\DB;

class DonationService
{
    /**
     * Create a new donation record.
     */
    public static function createDonation(array $data, User $user): Donation
    {
        return DB::transaction(function () use ($data, $user) {
            $data['created_by'] = $user->id;
            
            if (!isset($data['status'])) {
                $data['status'] = 'pending';
            }

            $donation = Donation::create($data);

            AuditLogService::log(
                'Finance',
                'Donation Created',
                "Created donation for {$donation->donor_name} of amount {$donation->amount}",
                null,
                $donation->toArray(),
                $donation,
                $donation->church_id,
                $donation->diocese_id
            );

            // If directly created as received, trigger receipt generation
            if ($donation->status === 'received') {
                ReceiptGenerationService::generateReceipt($donation, $user);
            }

            return $donation;
        });
    }

    /**
     * Mark donation as received and generate receipt.
     */
    public static function markReceived(Donation $donation, User $user): Donation
    {
        if ($donation->status !== 'pending') {
            throw new Exception("Only pending donations can be marked as received.");
        }

        return DB::transaction(function () use ($donation, $user) {
            $oldValues = $donation->toArray();

            $donation->update([
                'status' => 'received',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            AuditLogService::log(
                'Finance',
                'Donation Received',
                "Marked donation ID {$donation->id} as received",
                $oldValues,
                $donation->toArray(),
                $donation,
                $donation->church_id,
                $donation->diocese_id
            );

            // Generate Receipt
            ReceiptGenerationService::generateReceipt($donation, $user);

            return $donation;
        });
    }

    /**
     * Cancel/refund donation and handle receipt cancellation if generated.
     */
    public static function cancelDonation(Donation $donation, string $reason, User $user): Donation
    {
        if (in_array($donation->status, ['cancelled', 'refunded'])) {
            throw new Exception("Donation is already cancelled or refunded.");
        }

        return DB::transaction(function () use ($donation, $reason, $user) {
            $oldValues = $donation->toArray();
            
            $newStatus = ($donation->status === 'received') ? 'refunded' : 'cancelled';

            $donation->update([
                'status' => $newStatus,
            ]);

            // Handle receipt cancellation if it exists
            $donation->loadMissing('receipt');
            if ($donation->receipt && $donation->receipt->status !== 'cancelled') {
                $donation->receipt->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => $reason,
                    'cancelled_by' => $user->id,
                    'cancelled_at' => now()
                ]);

                AuditLogService::log(
                    'Finance',
                    'Receipt Cancelled',
                    "Cancelled receipt {$donation->receipt->receipt_number} due to donation cancellation",
                    null,
                    $donation->receipt->toArray(),
                    $donation->receipt,
                    $donation->church_id,
                    $donation->diocese_id
                );
            }

            AuditLogService::log(
                'Finance',
                'Donation Cancelled',
                "Cancelled/refunded donation ID {$donation->id}",
                $oldValues,
                $donation->toArray(),
                $donation,
                $donation->church_id,
                $donation->diocese_id
            );

            return $donation;
        });
    }
}
