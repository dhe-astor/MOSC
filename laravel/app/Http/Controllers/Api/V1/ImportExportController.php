<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Services\FamilyMemberImportService;
use App\Services\ChurchAccessService;
use App\Models\Family;
use App\Models\Member;
use App\Models\Church;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportExportController extends Controller
{
    use ApiResponse;

    protected $importService;

    public function __construct(FamilyMemberImportService $importService)
    {
        $this->importService = $importService;
    }

    public function importFamilies(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:5120',
            'church_id' => 'required|integer|exists:churches,id',
            'dry_run' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $churchId = (int)$request->input('church_id');
        if (!ChurchAccessService::canAccessChurch($request->user(), $churchId)) {
            return $this->errorResponse('You do not have access to this parish for imports', 403);
        }

        if (!$request->user()->hasPermissionTo('import_data')) {
            return $this->errorResponse('You do not have permission to import data', 403);
        }

        $file = $request->file('file');
        $dryRun = filter_var($request->input('dry_run', false), FILTER_VALIDATE_BOOLEAN);

        $results = $this->importService->importFamilies(
            $file->getRealPath(),
            $churchId,
            $request->user(),
            $dryRun
        );

        if (!$results['success']) {
            return $this->errorResponse(
                $dryRun ? 'Dry-run validation failed' : 'Import failed due to validation or duplicate issues',
                422,
                $results
            );
        }

        return $this->successResponse(
            $results,
            $dryRun ? 'Dry-run validation passed successfully' : 'Families imported successfully'
        );
    }

    public function importMembers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:5120',
            'church_id' => 'required|integer|exists:churches,id',
            'dry_run' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $churchId = (int)$request->input('church_id');
        if (!ChurchAccessService::canAccessChurch($request->user(), $churchId)) {
            return $this->errorResponse('You do not have access to this parish for imports', 403);
        }

        if (!$request->user()->hasPermissionTo('import_data')) {
            return $this->errorResponse('You do not have permission to import data', 403);
        }

        $file = $request->file('file');
        $dryRun = filter_var($request->input('dry_run', false), FILTER_VALIDATE_BOOLEAN);

        $results = $this->importService->importMembers(
            $file->getRealPath(),
            $churchId,
            $request->user(),
            $dryRun
        );

        if (!$results['success']) {
            return $this->errorResponse(
                $dryRun ? 'Dry-run validation failed' : 'Import failed due to validation or duplicate issues',
                422,
                $results
            );
        }

        return $this->successResponse(
            $results,
            $dryRun ? 'Dry-run validation passed successfully' : 'Members imported successfully'
        );
    }

    public function exportFamilies(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'church_id' => 'required|integer|exists:churches,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $churchId = (int)$request->input('church_id');
        if (!ChurchAccessService::canAccessChurch($request->user(), $churchId)) {
            return $this->errorResponse('You do not have access to this parish for exports', 403);
        }

        if (!$request->user()->hasPermissionTo('export_data')) {
            return $this->errorResponse('You do not have permission to export data', 403);
        }

        $families = Family::where('church_id', $churchId)->orderBy('family_name')->get();
        $church = Church::find($churchId);

        $filename = 'families-' . strtolower(str_replace(' ', '-', $church->name)) . '-' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function() use ($families) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'Family Code', 'Family Name', 'Primary Phone', 'WhatsApp Phone',
                'Primary Email', 'Address Line 1', 'Address Line 2', 'City',
                'State/Region', 'Postal Code', 'Preferred Language', 'Membership Status'
            ]);

            foreach ($families as $f) {
                fputcsv($file, [
                    $f->family_code,
                    $f->family_name,
                    $f->primary_phone,
                    $f->whatsapp_phone,
                    $f->primary_email,
                    $f->address_line_1,
                    $f->address_line_2,
                    $f->city,
                    $f->state_region,
                    $f->postal_code,
                    $f->preferred_language,
                    $f->membership_status
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportMembers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'church_id' => 'required|integer|exists:churches,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $churchId = (int)$request->input('church_id');
        if (!ChurchAccessService::canAccessChurch($request->user(), $churchId)) {
            return $this->errorResponse('You do not have access to this parish for exports', 403);
        }

        if (!$request->user()->hasPermissionTo('export_data')) {
            return $this->errorResponse('You do not have permission to export data', 403);
        }

        $members = Member::with('family')->where('church_id', $churchId)->orderBy('last_name')->orderBy('first_name')->get();
        $church = Church::find($churchId);

        $filename = 'members-' . strtolower(str_replace(' ', '-', $church->name)) . '-' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function() use ($members) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'Family Code', 'Member Code', 'First Name', 'Middle Name', 'Last Name',
                'Baptism Name', 'Gender', 'Date of Birth', 'Relationship to Head',
                'Phone', 'WhatsApp Phone', 'Email', 'Occupation', 'Employer/School',
                'Student Status', 'Marital Status', 'Membership Status'
            ]);

            foreach ($members as $m) {
                fputcsv($file, [
                    $m->family ? $m->family->family_code : '',
                    $m->member_code,
                    $m->first_name,
                    $m->middle_name,
                    $m->last_name,
                    $m->baptism_name,
                    $m->gender,
                    $m->date_of_birth ? $m->date_of_birth->format('Y-m-d') : '',
                    $m->relationship_to_head,
                    $m->phone,
                    $m->whatsapp_phone,
                    $m->email,
                    $m->occupation,
                    $m->employer_or_school,
                    $m->student_status ? 'Yes' : 'No',
                    $m->marital_status,
                    $m->membership_status
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportCourseRegistrations(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'course_batch_id' => 'required|integer|exists:course_batches,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $batchId = (int)$request->input('course_batch_id');
        $batch = \App\Models\CourseBatch::findOrFail($batchId);

        if ($batch->church_id !== null && !ChurchAccessService::canAccessChurch($request->user(), $batch->church_id)) {
            return $this->errorResponse('You do not have access to this parish for exports', 403);
        }

        if (!$request->user()->hasPermissionTo('export_data')) {
            return $this->errorResponse('You do not have permission to export data', 403);
        }

        $registrations = \App\Models\CourseRegistration::with(['member', 'family'])
            ->where('course_batch_id', $batchId)
            ->get();

        $filename = 'course-registrations-' . strtolower(str_replace(' ', '-', $batch->batch_code ?? $batch->batch_name)) . '-' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function() use ($registrations) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'Registration ID', 'Registration Type', 'Name', 'Email', 'Phone',
                'Participant Count', 'Payment Status', 'Registration Status',
                'QR Code', 'Feedback Completed', 'Certificate Issued'
            ]);

            foreach ($registrations as $r) {
                $name = $r->registration_type === 'external' 
                    ? $r->external_name 
                    : ($r->member ? $r->member->full_name : ($r->family ? $r->family->family_name . ' Family' : ''));
                $email = $r->registration_type === 'external' ? $r->external_email : ($r->member ? $r->member->email : ($r->family ? $r->family->primary_email : ''));
                $phone = $r->registration_type === 'external' ? $r->external_phone : ($r->member ? $r->member->phone : ($r->family ? $r->family->primary_phone : ''));

                fputcsv($file, [
                    $r->id,
                    $r->registration_type,
                    $name,
                    $email,
                    $phone,
                    $r->participant_count,
                    $r->payment_status,
                    $r->registration_status,
                    $r->qr_code,
                    $r->feedback_completed ? 'Yes' : 'No',
                    $r->certificate_issued ? 'Yes' : 'No',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportEventRegistrations(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|integer|exists:events,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $eventId = (int)$request->input('event_id');
        $event = \App\Models\Event::findOrFail($eventId);

        if ($event->church_id !== null && !ChurchAccessService::canAccessChurch($request->user(), $event->church_id)) {
            return $this->errorResponse('You do not have access to this parish for exports', 403);
        }

        if (!$request->user()->hasPermissionTo('export_data')) {
            return $this->errorResponse('You do not have permission to export data', 403);
        }

        $registrations = \App\Models\EventRegistration::with(['member', 'family'])
            ->where('event_id', $eventId)
            ->get();

        $filename = 'event-registrations-' . strtolower(str_replace(' ', '-', $event->slug ?? $event->title)) . '-' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function() use ($registrations) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'Registration ID', 'Registration Type', 'Name', 'Email', 'Phone',
                'Participant Count', 'Payment Status', 'Registration Status',
                'QR Code', 'Checked In At'
            ]);

            foreach ($registrations as $r) {
                $name = $r->registration_type === 'external' 
                    ? $r->external_name 
                    : ($r->member ? $r->member->full_name : ($r->family ? $r->family->family_name . ' Family' : ''));
                $email = $r->registration_type === 'external' ? $r->external_email : ($r->member ? $r->member->email : ($r->family ? $r->family->primary_email : ''));
                $phone = $r->registration_type === 'external' ? $r->external_phone : ($r->member ? $r->member->phone : ($r->family ? $r->family->primary_phone : ''));

                fputcsv($file, [
                    $r->id,
                    $r->registration_type,
                    $name,
                    $email,
                    $phone,
                    $r->participant_count,
                    $r->payment_status,
                    $r->registration_status,
                    $r->qr_code,
                    $r->checked_in_at ? $r->checked_in_at->toDateTimeString() : 'No',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportCourseAttendance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'course_batch_id' => 'required|integer|exists:course_batches,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $batchId = (int)$request->input('course_batch_id');
        $batch = \App\Models\CourseBatch::findOrFail($batchId);

        if ($batch->church_id !== null && !ChurchAccessService::canAccessChurch($request->user(), $batch->church_id)) {
            return $this->errorResponse('You do not have access to this parish for exports', 403);
        }

        if (!$request->user()->hasPermissionTo('export_data')) {
            return $this->errorResponse('You do not have permission to export data', 403);
        }

        $attendance = \App\Models\CourseAttendance::with(['session', 'registration.member'])
            ->where('course_batch_id', $batchId)
            ->get();

        $filename = 'course-attendance-' . strtolower(str_replace(' ', '-', $batch->batch_code ?? $batch->batch_name)) . '-' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function() use ($attendance) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'Attendance ID', 'Session Title', 'Session Date', 'Participant Name',
                'Attendance Status', 'Marked At', 'Remarks'
            ]);

            foreach ($attendance as $a) {
                $name = $a->registration->registration_type === 'external'
                    ? $a->registration->external_name
                    : ($a->registration->member ? $a->registration->member->full_name : ($a->registration->family ? $a->registration->family->family_name . ' Family' : ''));

                fputcsv($file, [
                    $a->id,
                    $a->session->title,
                    $a->session->session_date->toDateString(),
                    $name,
                    $a->status,
                    $a->marked_at ? $a->marked_at->toDateTimeString() : '',
                    $a->remarks,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportEventAttendance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|integer|exists:events,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $eventId = (int)$request->input('event_id');
        $event = \App\Models\Event::findOrFail($eventId);

        if ($event->church_id !== null && !ChurchAccessService::canAccessChurch($request->user(), $event->church_id)) {
            return $this->errorResponse('You do not have access to this parish for exports', 403);
        }

        if (!$request->user()->hasPermissionTo('export_data')) {
            return $this->errorResponse('You do not have permission to export data', 403);
        }

        $attendance = \App\Models\EventAttendance::with(['registration.member'])
            ->where('event_id', $eventId)
            ->get();

        $filename = 'event-attendance-' . strtolower(str_replace(' ', '-', $event->slug ?? $event->title)) . '-' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function() use ($attendance) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'Attendance ID', 'Participant Name', 'Status', 'Marked At', 'Remarks'
            ]);

            foreach ($attendance as $a) {
                $name = $a->registration->registration_type === 'external'
                    ? $a->registration->external_name
                    : ($a->registration->member ? $a->registration->member->full_name : ($a->registration->family ? $a->registration->family->family_name . ' Family' : ''));

                fputcsv($file, [
                    $a->id,
                    $name,
                    $a->status,
                    $a->marked_at ? $a->marked_at->toDateTimeString() : '',
                    $a->remarks,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
