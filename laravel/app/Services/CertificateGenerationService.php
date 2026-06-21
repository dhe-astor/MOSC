<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateRequest;
use App\Models\CertificateTemplate;
use App\Models\PriestAssignment;
use App\Services\CertificateNumberService;
use App\Services\CertificateVerificationService;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class CertificateGenerationService
{
    protected CertificateNumberService $numberService;
    protected CertificateVerificationService $verificationService;

    public function __construct(
        CertificateNumberService $numberService,
        CertificateVerificationService $verificationService
    ) {
        $this->numberService = $numberService;
        $this->verificationService = $verificationService;
    }

    public function generate(CertificateRequest $request, int $templateId): Certificate
    {
        // 1. Verify request is approved
        if ($request->status !== 'approved') {
            throw new \InvalidArgumentException("Certificate can be generated only for approved certificate requests.");
        }

        // 2. Check if request already has an issued certificate
        if (Certificate::where('certificate_request_id', $request->id)->where('status', 'active')->exists()) {
            throw new \InvalidArgumentException("A certificate has already been issued for this request.");
        }

        // 3. Load template
        $template = CertificateTemplate::findOrFail($templateId);
        if (!$template->is_active) {
            throw new \InvalidArgumentException("The selected certificate template is inactive.");
        }

        $user = Auth::user();

        // 4. Generate unique identifiers
        $certNumber = $this->numberService->generate($request->diocese_id, $request->certificate_type);
        $verifyCode = $this->verificationService->generateUniqueCode();

        // 5. Replace placeholders
        $html = $template->html_template;

        // Fetch priest name
        $priestName = '';
        $assignment = PriestAssignment::with('priest')
            ->where('church_id', $request->church_id)
            ->where('is_primary', true)
            ->where('status', 'active')
            ->first();
        if ($assignment && $assignment->priest) {
            $priestName = trim(($assignment->priest->title ?? '') . ' ' . $assignment->priest->full_name);
        }

        $request->loadMissing(['member', 'family', 'church']);

        $placeholders = [
            '{{member_full_name}}' => $request->member?->full_name ?? '',
            '{{baptism_name}}' => $request->member?->baptism_name ?? '',
            '{{family_name}}' => $request->family?->family_name ?? '',
            '{{church_name}}' => $request->church?->name ?? '',
            '{{priest_name}}' => $priestName,
            '{{certificate_number}}' => $certNumber,
            '{{issued_date}}' => date('Y-m-d'),
            '{{verification_code}}' => $verifyCode,
        ];

        foreach ($placeholders as $placeholder => $value) {
            $html = str_replace($placeholder, $value, $html);
        }

        // 6. Generate PDF via Dompdf
        $pdf = Pdf::loadHTML($html);
        $pdfContent = $pdf->output();

        // 7. Store PDF securely in private storage
        $pdfPath = "private/certificates/{$verifyCode}.pdf";
        Storage::put($pdfPath, $pdfContent);

        // 8. Create certificate record
        $certificate = Certificate::create([
            'certificate_request_id' => $request->id,
            'diocese_id' => $request->diocese_id,
            'church_id' => $request->church_id,
            'member_id' => $request->member_id,
            'family_id' => $request->family_id,
            'sacrament_id' => $request->sacrament_id,
            'certificate_template_id' => $template->id,
            'certificate_number' => $certNumber,
            'certificate_type' => $request->certificate_type,
            'issued_date' => now(),
            'issued_by' => $user->id,
            'approved_by' => $request->priest_approved_by ?? $request->diocese_approved_by ?? $user->id,
            'pdf_path' => $pdfPath,
            'verification_code' => $verifyCode,
            'public_verification_enabled' => true,
            'status' => 'active',
            'metadata' => [
                'template_name' => $template->name,
                'placeholders_used' => array_keys($placeholders),
            ],
        ]);

        // 9. Link certificate back to request and mark as issued
        $request->update([
            'status' => 'issued',
            'certificate_id' => $certificate->id,
        ]);

        AuditLogService::log(
            'certificates',
            'certificate_issued',
            "Certificate {$certificate->certificate_number} issued for request ID: {$request->id}",
            null,
            $certificate->toArray(),
            $certificate,
            $certificate->church_id,
            $certificate->diocese_id
        );

        return $certificate;
    }
}
