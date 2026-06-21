<?php

namespace App\Services;

use App\Models\SundaySchoolStudent;
use App\Models\SundaySchoolCertificate;
use App\Models\CertificateRequest;
use App\Models\CertificateTemplate;
use App\Models\User;
use App\Services\CertificateGenerationService;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class SundaySchoolCertificateService
{
    protected CertificateGenerationService $generationService;

    public function __construct(CertificateGenerationService $generationService)
    {
        $this->generationService = $generationService;
    }

    /**
     * Issue a Sunday School certificate.
     */
    public function issue(array $data, User $issuer): SundaySchoolCertificate
    {
        $studentId = $data['student_id'];
        $certificateType = $data['certificate_type']; // completion, participation, merit, promotion
        $templateId = $data['certificate_template_id'];

        $student = SundaySchoolStudent::findOrFail($studentId);

        // 1. Validate user has permission
        if (!$issuer->hasRole(['Super Admin', 'Diocese Admin', 'Sunday School Admin'])) {
            throw new Exception('Access Denied: You do not have permission to issue Sunday School certificates.');
        }

        // 2. Validate student enrollment is active/completed/promoted
        if (!in_array($student->enrollment_status, ['active', 'completed', 'promoted'])) {
            throw new Exception('Sunday School certificates can only be issued for active, completed, or promoted students.');
        }

        // 3. Validate template exists
        $template = CertificateTemplate::findOrFail($templateId);
        if (!$template->is_active) {
            throw new Exception('The selected certificate template is inactive.');
        }

        // 4. Prevent duplicate certificates for the same student + academic year + certificate type
        $exists = SundaySchoolCertificate::where('student_id', $studentId)
            ->where('academic_year_id', $student->academic_year_id)
            ->where('certificate_type', $certificateType)
            ->exists();

        if ($exists) {
            throw new Exception('A certificate of this type has already been issued for this student and academic year.');
        }

        return DB::transaction(function () use ($student, $certificateType, $templateId, $issuer) {
            // Create a certificate request using the Phase 3 schema
            $request = CertificateRequest::create([
                'diocese_id' => $student->diocese_id,
                'church_id' => $student->church_id,
                'member_id' => $student->member_id,
                'family_id' => $student->family_id,
                'certificate_type' => 'course_completion', // Map to valid Phase 3 certificate type
                'status' => 'approved',
                'reason' => "Sunday School {$certificateType} certificate for AY {$student->academic_year_id}",
                'created_by' => $issuer->id,
                'requested_by' => $issuer->id,
                'purpose' => 'Sunday School Certificate',
                'priest_approved_by' => $issuer->id,
                'priest_approved_at' => Carbon::now(),
            ]);

            // Generate the certificate using the template
            $certificate = $this->generationService->generate($request, $templateId);

            // Record inside Sunday School certificates table
            $ssCertificate = SundaySchoolCertificate::create([
                'student_id' => $student->id,
                'academic_year_id' => $student->academic_year_id,
                'class_id' => $student->class_id,
                'certificate_id' => $certificate->id,
                'certificate_type' => $certificateType,
                'issued_by' => $issuer->id,
                'issued_at' => Carbon::now(),
            ]);

            AuditLogService::log(
                'sunday_school',
                'sunday_school_certificate_issued',
                "Issued Sunday School {$certificateType} certificate for student ID {$student->id}",
                null,
                $ssCertificate->toArray(),
                $ssCertificate,
                $student->church_id,
                $student->diocese_id
            );

            return $ssCertificate;
        });
    }
}
