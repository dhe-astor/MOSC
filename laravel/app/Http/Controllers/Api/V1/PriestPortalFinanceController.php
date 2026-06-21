<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Models\FinancePriestPayment;
use App\Models\Priest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Exception;

class PriestPortalFinanceController extends Controller
{
    use ApiResponse;

    /**
     * Helper to get authenticated priest record.
     */
    protected function getPriest(Request $request): Priest
    {
        $user = $request->user();
        $priest = Priest::where('user_id', $user->id)->first();
        if (!$priest) {
            throw new Exception("Authenticated user is not linked to a priest profile.", 404);
        }
        return $priest;
    }

    /**
     * List stipend and travel payments for the authenticated priest.
     */
    public function listPayments(Request $request)
    {
        try {
            $priest = $this->getPriest($request);
            
            $payments = FinancePriestPayment::where('priest_id', $priest->id)
                ->orderBy('payment_date', 'desc')
                ->get();

            return $this->successResponse($payments, 'Priest payments list retrieved.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Download payment advice PDF.
     */
    public function downloadAdvice(Request $request, $id)
    {
        try {
            $priest = $this->getPriest($request);
            
            $payment = FinancePriestPayment::findOrFail($id);
            if ($payment->priest_id !== $priest->id) {
                return $this->errorResponse('Unauthorized access to payment advice.', 403);
            }

            // Extract advice filename/path from description/url
            // Description format: "... | Advice PDF: private/priest_payments/ADV-..."
            $pdfPath = null;
            if (preg_match('/Advice PDF:\s*(private\/priest_payments\/[^\s|]+)/', $payment->description, $matches)) {
                $pdfPath = $matches[1];
            }

            if (!$pdfPath || !Storage::exists($pdfPath)) {
                return $this->errorResponse('Payment advice PDF file not found.', 404);
            }

            return Storage::download($pdfPath);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
