<?php

namespace App\Services;

use App\Models\Donation;
use App\Models\Receipt;
use App\Models\FinanceReceipt;
use App\Models\FinanceIncomeLine;
use App\Models\MemberPortalActivityLog;
use Illuminate\Support\Facades\Storage;
use Exception;

class MemberPortalFinanceService
{
    public static function getDonations($user)
    {
        $memberIds = MemberPortalSecurity::getAuthorizedMemberIds($user);
        $familyIds = MemberPortalSecurity::getAuthorizedFamilyIds($user);

        // Fetch legacy donations
        $legacy = Donation::whereIn('member_id', $memberIds)
            ->orWhereIn('family_id', $familyIds)
            ->with(['member', 'family', 'category'])
            ->get()
            ->map(function ($d) {
                return [
                    'id' => $d->id,
                    'type' => 'legacy',
                    'amount' => $d->amount,
                    'currency' => $d->currency ?? 'EUR',
                    'received_date' => $d->received_date ? $d->received_date->toDateString() : null,
                    'payment_method' => $d->payment_method,
                    'status' => $d->status,
                    'category_name' => $d->category ? $d->category->name : 'General Donation',
                ];
            });

        // Fetch new double-entry income lines (only posted headers)
        $newLines = FinanceIncomeLine::whereIn('member_id', $memberIds)
            ->whereHas('header', function ($query) {
                $query->where('status', 'posted');
            })
            ->with(['incomeHead', 'header'])
            ->get()
            ->map(function ($l) {
                return [
                    'id' => $l->id,
                    'type' => 'accounting',
                    'amount' => $l->amount,
                    'currency' => 'EUR',
                    'received_date' => $l->header->income_date,
                    'payment_method' => $l->header->receipt ? $l->header->receipt->payment_method : 'other',
                    'status' => 'received',
                    'category_name' => $l->incomeHead ? $l->incomeHead->name : 'General Contribution',
                ];
            });

        return $legacy->concat($newLines)->sortByDesc('received_date')->values()->toArray();
    }

    public static function getReceipts($user)
    {
        $memberIds = MemberPortalSecurity::getAuthorizedMemberIds($user);

        // Fetch legacy receipts
        $legacy = Receipt::whereIn('member_id', $memberIds)
            ->orWhereIn('family_id', MemberPortalSecurity::getAuthorizedFamilyIds($user))
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'type' => 'legacy',
                    'receipt_number' => $r->receipt_number,
                    'amount' => $r->amount,
                    'currency' => $r->currency ?? 'EUR',
                    'receipt_date' => $r->receipt_date ? $r->receipt_date->toDateString() : null,
                    'payment_method' => $r->payment_method,
                ];
            });

        // Fetch new double-entry receipts
        $newReceipts = FinanceReceipt::whereIn('member_id', $memberIds)
            ->where('status', 'active')
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'type' => 'accounting',
                    'receipt_number' => $r->receipt_number,
                    'amount' => $r->total_amount,
                    'currency' => 'EUR',
                    'receipt_date' => $r->receipt_date,
                    'payment_method' => $r->payment_method,
                ];
            });

        return $legacy->concat($newReceipts)->sortByDesc('receipt_date')->values()->toArray();
    }

    public static function downloadReceipt($type, $id, $user)
    {
        if ($type === 'legacy') {
            $receipt = Receipt::findOrFail($id);
            if (!MemberPortalSecurity::validateReceiptAccess($user, $receipt->id)) {
                throw new Exception("Access Denied: You are not authorized to download this receipt.");
            }
            $pdfPath = $receipt->pdf_path;
            $number = $receipt->receipt_number;
            $dioceseId = $receipt->diocese_id;
            $churchId = $receipt->church_id;
            $familyId = $receipt->family_id;
            $memberId = $receipt->member_id;
        } else {
            $receipt = FinanceReceipt::findOrFail($id);
            // Verify membership of the member
            $memberIds = MemberPortalSecurity::getAuthorizedMemberIds($user);
            if (!in_array($receipt->member_id, $memberIds)) {
                throw new Exception("Access Denied: You are not authorized to download this receipt.");
            }
            // For accounting receipt, look at stored path: private/receipts/{number}.pdf
            $pdfPath = "private/receipts/{$receipt->receipt_number}.pdf";
            $number = $receipt->receipt_number;
            $dioceseId = $user->default_diocese_id;
            $churchId = $receipt->incomeHeader ? $receipt->incomeHeader->church_id : $user->default_church_id;
            $familyId = null;
            $memberId = $receipt->member_id;
        }

        if (!$pdfPath || !Storage::exists($pdfPath)) {
            throw new Exception("Receipt file not found: " . $pdfPath);
        }

        self::logActivity($dioceseId, $churchId, $user->id, $familyId, $memberId, 'receipt_downloaded', "Downloaded receipt: {$number}");

        return Storage::download($pdfPath, "{$number}.pdf");
    }

    private static function logActivity($dioceseId, $churchId, $userId, $familyId, $memberId, string $action, string $description)
    {
        MemberPortalActivityLog::create([
            'diocese_id' => $dioceseId,
            'church_id' => $churchId,
            'user_id' => $userId,
            'family_id' => $familyId,
            'member_id' => $memberId,
            'action' => $action,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }
}
