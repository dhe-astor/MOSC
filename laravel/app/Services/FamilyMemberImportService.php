<?php

namespace App\Services;

use App\Models\Family;
use App\Models\Member;
use App\Models\Church;
use App\Models\User;
use App\Services\FamilyApprovalService;
use App\Services\MemberApprovalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;

class FamilyMemberImportService
{
    protected $familyApprovalService;
    protected $memberApprovalService;

    public function __construct(
        FamilyApprovalService $familyApprovalService,
        MemberApprovalService $memberApprovalService
    ) {
        $this->familyApprovalService = $familyApprovalService;
        $this->memberApprovalService = $memberApprovalService;
    }

    /**
     * Import families from a CSV file.
     */
    public function importFamilies(string $csvPath, int $churchId, User $user, bool $dryRun = false): array
    {
        $church = Church::findOrFail($churchId);
        $dioceseId = $church->diocese_id;

        $results = [
            'success' => true,
            'total_rows' => 0,
            'imported_count' => 0,
            'errors' => [],
            'duplicates' => []
        ];

        if (!file_exists($csvPath)) {
            $results['success'] = false;
            $results['errors'][] = ['row' => 0, 'family_name' => 'File', 'errors' => ['CSV file not found.']];
            return $results;
        }

        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            $results['success'] = false;
            $results['errors'][] = ['row' => 0, 'family_name' => 'File', 'errors' => ['Cannot open CSV file.']];
            return $results;
        }

        // Read headers
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            $results['success'] = false;
            $results['errors'][] = ['row' => 0, 'family_name' => 'Headers', 'errors' => ['CSV file is empty.']];
            return $results;
        }

        // Clean headers (trim whitespace, lowercase, replace spaces/dashes with underscores)
        $headers = array_map(function($h) {
            $h = strtolower(trim($h));
            $h = str_replace(' ', '_', $h);
            return str_replace('-', '_', $h);
        }, $headers);

        $rowNum = 1;
        $rowsData = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (count($headers) !== count($row)) {
                if (count($row) < count($headers)) {
                    $row = array_pad($row, count($headers), '');
                } else {
                    $row = array_slice($row, 0, count($headers));
                }
            }

            $data = array_combine($headers, $row);
            $data = array_map('trim', $data);
            $data['_row_num'] = $rowNum;
            $rowsData[] = $data;
        }
        fclose($handle);

        $results['total_rows'] = count($rowsData);

        $rules = [
            'family_name' => 'required|string|max:255',
            'primary_phone' => 'required|string|max:50',
            'whatsapp_phone' => 'nullable|string|max:50',
            'primary_email' => 'nullable|email|max:255',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'state_region' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country_id' => 'nullable|integer|exists:countries,id',
            'preferred_language' => 'nullable|string|in:en,ml,de',
            'gdpr_consent' => 'nullable',
            'communication_consent' => 'nullable',
            'notes' => 'nullable|string',
        ];

        $canAutoApprove = $user->hasPermissionTo('import_and_auto_approve_members');

        DB::beginTransaction();

        $familiesToInsert = [];

        foreach ($rowsData as $data) {
            $currentRowNum = $data['_row_num'];
            $familyName = $data['family_name'] ?? 'Row ' . $currentRowNum;

            $validator = Validator::make($data, $rules);

            $rowErrors = [];
            if ($validator->fails()) {
                $rowErrors = array_merge($rowErrors, $validator->errors()->all());
            }

            // Duplicate Detection
            if (!empty($data['family_name']) && !empty($data['primary_phone'])) {
                $dupCheck1 = Family::where('church_id', $churchId)
                    ->where('family_name', $data['family_name'])
                    ->where('primary_phone', $data['primary_phone'])
                    ->exists();

                if ($dupCheck1) {
                    $results['duplicates'][] = [
                        'row' => $currentRowNum,
                        'message' => "Duplicate family found: '{$data['family_name']}' with phone '{$data['primary_phone']}' already exists in this parish."
                    ];
                }
            }

            if (!empty($data['primary_email'])) {
                $dupCheck2 = Family::where('primary_email', $data['primary_email'])->exists();
                if ($dupCheck2) {
                    $results['duplicates'][] = [
                        'row' => $currentRowNum,
                        'message' => "Duplicate family email: Email '{$data['primary_email']}' is already registered."
                    ];
                }
            }

            if (!empty($rowErrors)) {
                $results['errors'][] = [
                    'row' => $currentRowNum,
                    'family_name' => $familyName,
                    'errors' => $rowErrors
                ];
            } else {
                $familiesToInsert[] = $data;
            }
        }

        // If there are errors or duplicates, or if it is a dry run, abort saving
        if (!empty($results['errors']) || !empty($results['duplicates']) || $dryRun) {
            DB::rollBack();
            $results['success'] = empty($results['errors']) && empty($results['duplicates']);
            return $results;
        }

        try {
            foreach ($familiesToInsert as $data) {
                $gdpr = in_array(strtolower($data['gdpr_consent'] ?? ''), ['1', 'true', 'yes', 'on']);
                $comm = in_array(strtolower($data['communication_consent'] ?? ''), ['1', 'true', 'yes', 'on']);

                $family = Family::create([
                    'diocese_id' => $dioceseId,
                    'church_id' => $churchId,
                    'family_name' => $data['family_name'],
                    'primary_phone' => $data['primary_phone'],
                    'whatsapp_phone' => $data['whatsapp_phone'] ?? null,
                    'primary_email' => $data['primary_email'] ?? null,
                    'address_line_1' => $data['address_line_1'],
                    'address_line_2' => $data['address_line_2'] ?? null,
                    'city' => $data['city'],
                    'state_region' => $data['state_region'] ?? null,
                    'postal_code' => $data['postal_code'] ?? null,
                    'country_id' => !empty($data['country_id']) ? $data['country_id'] : null,
                    'preferred_language' => $data['preferred_language'] ?? 'en',
                    'membership_status' => 'pending',
                    'gdpr_consent' => $gdpr,
                    'gdpr_consent_at' => $gdpr ? Carbon::now() : null,
                    'communication_consent' => $comm,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $user->id,
                ]);

                if ($canAutoApprove) {
                    $this->familyApprovalService->approve($family, $user);
                }

                $results['imported_count']++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $results['success'] = false;
            $results['errors'][] = ['row' => 0, 'family_name' => 'Database', 'errors' => ['Import failed: ' . $e->getMessage()]];
        }

        return $results;
    }

    /**
     * Import members from a CSV file.
     */
    public function importMembers(string $csvPath, int $churchId, User $user, bool $dryRun = false): array
    {
        $church = Church::findOrFail($churchId);
        $dioceseId = $church->diocese_id;

        $results = [
            'success' => true,
            'total_rows' => 0,
            'imported_count' => 0,
            'errors' => [],
            'duplicates' => []
        ];

        if (!file_exists($csvPath)) {
            $results['success'] = false;
            $results['errors'][] = ['row' => 0, 'family_name' => 'File', 'errors' => ['CSV file not found.']];
            return $results;
        }

        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            $results['success'] = false;
            $results['errors'][] = ['row' => 0, 'family_name' => 'File', 'errors' => ['Cannot open CSV file.']];
            return $results;
        }

        // Read headers
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            $results['success'] = false;
            $results['errors'][] = ['row' => 0, 'family_name' => 'Headers', 'errors' => ['CSV file is empty.']];
            return $results;
        }

        $headers = array_map(function($h) {
            $h = strtolower(trim($h));
            $h = str_replace(' ', '_', $h);
            return str_replace('-', '_', $h);
        }, $headers);

        $rowNum = 1;
        $rowsData = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (count($headers) !== count($row)) {
                if (count($row) < count($headers)) {
                    $row = array_pad($row, count($headers), '');
                } else {
                    $row = array_slice($row, 0, count($headers));
                }
            }

            $data = array_combine($headers, $row);
            $data = array_map('trim', $data);
            $data['_row_num'] = $rowNum;
            $rowsData[] = $data;
        }
        fclose($handle);

        $results['total_rows'] = count($rowsData);

        $rules = [
            'family_code' => 'required|string',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'baptism_name' => 'nullable|string|max:255',
            'gender' => 'nullable|string|in:male,female,other,prefer_not_to_say',
            'date_of_birth' => 'nullable|date_format:Y-m-d',
            'relationship_to_head' => 'required|string|in:head,spouse,son,daughter,father,mother,brother,sister,relative,other',
            'phone' => 'nullable|string|max:50',
            'whatsapp_phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'occupation' => 'nullable|string|max:255',
            'employer_or_school' => 'nullable|string|max:255',
            'student_status' => 'nullable',
            'marital_status' => 'nullable|string|in:single,married,widowed,divorced,separated,not_applicable',
            'gdpr_consent' => 'nullable',
            'communication_consent' => 'nullable',
            'show_in_directory' => 'nullable',
        ];

        $canAutoApprove = $user->hasPermissionTo('import_and_auto_approve_members');

        DB::beginTransaction();

        $membersToInsert = [];

        foreach ($rowsData as $data) {
            $currentRowNum = $data['_row_num'];
            $memberName = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
            if ($memberName === '') {
                $memberName = 'Row ' . $currentRowNum;
            }

            $validator = Validator::make($data, $rules);

            $rowErrors = [];
            if ($validator->fails()) {
                $rowErrors = array_merge($rowErrors, $validator->errors()->all());
            }

            // Look up family
            $family = null;
            if (!empty($data['family_code'])) {
                if (is_numeric($data['family_code'])) {
                    $family = Family::where('church_id', $churchId)->find($data['family_code']);
                } else {
                    $family = Family::where('church_id', $churchId)->where('family_code', $data['family_code'])->first();
                }

                if (!$family) {
                    $rowErrors[] = "Family '{$data['family_code']}' not found in this church.";
                }
            }

            // Duplicate Detection
            if (!empty($data['first_name']) && !empty($data['last_name']) && !empty($data['phone'])) {
                $dupCheck1 = Member::where('church_id', $churchId)
                    ->where('first_name', $data['first_name'])
                    ->where('last_name', $data['last_name'])
                    ->where('phone', $data['phone'])
                    ->exists();

                if ($dupCheck1) {
                    $results['duplicates'][] = [
                        'row' => $currentRowNum,
                        'message' => "Duplicate member found: '{$memberName}' with phone '{$data['phone']}' already exists in this parish."
                    ];
                }
            }

            if (!empty($data['email'])) {
                $dupCheck2 = Member::where('email', $data['email'])->exists();
                if ($dupCheck2) {
                    $results['duplicates'][] = [
                        'row' => $currentRowNum,
                        'message' => "Duplicate member email: Email '{$data['email']}' is already registered."
                    ];
                }
            }

            if (!empty($rowErrors)) {
                $results['errors'][] = [
                    'row' => $currentRowNum,
                    'family_name' => $memberName,
                    'errors' => $rowErrors
                ];
            } else {
                $data['_family_id'] = $family ? $family->id : null;
                $membersToInsert[] = $data;
            }
        }

        // If there are errors or duplicates, or if it is a dry run, abort saving
        if (!empty($results['errors']) || !empty($results['duplicates']) || $dryRun) {
            DB::rollBack();
            $results['success'] = empty($results['errors']) && empty($results['duplicates']);
            return $results;
        }

        try {
            foreach ($membersToInsert as $data) {
                $gdpr = in_array(strtolower($data['gdpr_consent'] ?? ''), ['1', 'true', 'yes', 'on']);
                $comm = in_array(strtolower($data['communication_consent'] ?? ''), ['1', 'true', 'yes', 'on']);
                $showDir = in_array(strtolower($data['show_in_directory'] ?? ''), ['1', 'true', 'yes', 'on']);
                $student = in_array(strtolower($data['student_status'] ?? ''), ['1', 'true', 'yes', 'on']);

                $fullName = trim($data['first_name'] . ' ' . ($data['middle_name'] ?? '') . ' ' . $data['last_name']);
                $fullName = preg_replace('/\s+/', ' ', $fullName);

                $member = Member::create([
                    'diocese_id' => $dioceseId,
                    'church_id' => $churchId,
                    'family_id' => $data['_family_id'],
                    'first_name' => $data['first_name'],
                    'middle_name' => $data['middle_name'] ?? null,
                    'last_name' => $data['last_name'],
                    'full_name' => $fullName,
                    'baptism_name' => $data['baptism_name'] ?? null,
                    'gender' => $data['gender'] ?? 'prefer_not_to_say',
                    'date_of_birth' => !empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
                    'relationship_to_head' => $data['relationship_to_head'],
                    'phone' => $data['phone'] ?? null,
                    'whatsapp_phone' => $data['whatsapp_phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'occupation' => $data['occupation'] ?? null,
                    'employer_or_school' => $data['employer_or_school'] ?? null,
                    'student_status' => $student,
                    'marital_status' => $data['marital_status'] ?? 'single',
                    'address_same_as_family' => true,
                    'membership_status' => 'pending',
                    'gdpr_consent' => $gdpr,
                    'communication_consent' => $comm,
                    'show_in_directory' => $showDir,
                    'created_by' => $user->id,
                ]);

                // If relationship is head, update head_member_id in families
                if ($data['relationship_to_head'] === 'head') {
                    $family = Family::find($data['_family_id']);
                    if ($family && !$family->head_member_id) {
                        $family->head_member_id = $member->id;
                        $family->save();
                    }
                }

                if ($canAutoApprove) {
                    $this->memberApprovalService->approve($member, $user);
                }

                $results['imported_count']++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $results['success'] = false;
            $results['errors'][] = ['row' => 0, 'family_name' => 'Database', 'errors' => ['Import failed: ' . $e->getMessage()]];
        }

        return $results;
    }
}
