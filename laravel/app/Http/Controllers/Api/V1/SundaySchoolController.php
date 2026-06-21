<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Models\SundaySchoolAcademicYear;
use App\Models\SundaySchoolLevel;
use App\Models\SundaySchoolClass;
use App\Models\SundaySchoolTeacher;
use App\Models\SundaySchoolStudent;
use App\Models\SundaySchoolAttendance;
use App\Models\SundaySchoolExam;
use App\Models\SundaySchoolMark;
use App\Models\SundaySchoolProgressReport;
use App\Models\SundaySchoolCertificate;
use App\Models\Member;
use App\Models\User;
use App\Services\SundaySchoolEnrollmentService;
use App\Services\SundaySchoolAttendanceService;
use App\Services\SundaySchoolExamService;
use App\Services\SundaySchoolProgressReportService;
use App\Services\SundaySchoolPromotionService;
use App\Services\SundaySchoolCertificateService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Exception;

class SundaySchoolController extends Controller
{
    use ApiResponse;

    protected SundaySchoolEnrollmentService $enrollmentService;
    protected SundaySchoolAttendanceService $attendanceService;
    protected SundaySchoolExamService $examService;
    protected SundaySchoolProgressReportService $progressReportService;
    protected SundaySchoolPromotionService $promotionService;
    protected SundaySchoolCertificateService $certificateService;

    public function __construct(
        SundaySchoolEnrollmentService $enrollmentService,
        SundaySchoolAttendanceService $attendanceService,
        SundaySchoolExamService $examService,
        SundaySchoolProgressReportService $progressReportService,
        SundaySchoolPromotionService $promotionService,
        SundaySchoolCertificateService $certificateService
    ) {
        $this->enrollmentService = $enrollmentService;
        $this->attendanceService = $attendanceService;
        $this->examService = $examService;
        $this->progressReportService = $progressReportService;
        $this->promotionService = $promotionService;
        $this->certificateService = $certificateService;
    }

    // ==========================================
    // 0. Dashboard Stats
    // ==========================================
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $classIds = $this->getAccessibleClassIds($user);

        $classesQuery = SundaySchoolClass::query();
        $studentsQuery = SundaySchoolStudent::query();
        $teachersQuery = SundaySchoolTeacher::query();

        if ($classIds !== null) {
            $classesQuery->whereIn('id', $classIds);
            $studentsQuery->whereIn('class_id', $classIds);
            // Scope teachers query if applicable
            if ($user->hasRole(['Parish Admin', 'Priest / Vicar', 'Parish Secretary'])) {
                $userChurchId = $user->active_church_id ?? $user->default_church_id;
                $teachersQuery->where('church_id', $userChurchId);
            } else {
                $teacher = SundaySchoolTeacher::where('user_id', $user->id)->first();
                if ($teacher) {
                    $teachersQuery->where('id', $teacher->id);
                } else {
                    $teachersQuery->whereRaw('1 = 0');
                }
            }
        }

        $stats = [
            'total_classes' => $classesQuery->count(),
            'total_students' => $studentsQuery->whereIn('enrollment_status', ['active', 'pending'])->count(),
            'total_teachers' => $teachersQuery->count(),
            'active_academic_year' => SundaySchoolAcademicYear::where('is_current', true)->first(),
        ];

        return $this->successResponse($stats, 'Sunday School dashboard stats retrieved successfully.');
    }

    // ==========================================
    // 1. Academic Years
    // ==========================================
    public function listAcademicYears(Request $request)
    {
        $years = SundaySchoolAcademicYear::orderBy('start_date', 'desc')->get();
        return $this->successResponse($years, 'Academic years retrieved successfully.');
    }

    public function storeAcademicYear(Request $request)
    {
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Sunday School Admin'])) {
            return $this->errorResponse('Access Denied', 403);
        }

        $validator = Validator::make($request->all(), [
            'diocese_id' => 'required|exists:dioceses,id',
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'nullable|string|in:draft,active,completed,archived',
            'is_current' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $ay = DB::transaction(function () use ($request, $validator) {
            $isCurrent = $request->input('is_current', false);
            $dioceseId = $request->input('diocese_id');

            if ($isCurrent) {
                SundaySchoolAcademicYear::where('diocese_id', $dioceseId)->update(['is_current' => false]);
            }

            return SundaySchoolAcademicYear::create(array_merge($validator->validated(), [
                'is_current' => $isCurrent,
                'created_by' => $request->user()->id,
            ]));
        });

        return $this->successResponse($ay, 'Academic year created successfully.', 201);
    }

    public function activateAcademicYear(Request $request, $id)
    {
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Sunday School Admin'])) {
            return $this->errorResponse('Access Denied', 403);
        }

        $ay = SundaySchoolAcademicYear::findOrFail($id);

        DB::transaction(function () use ($ay, $request) {
            SundaySchoolAcademicYear::where('diocese_id', $ay->diocese_id)
                ->where('id', '!=', $ay->id)
                ->update(['is_current' => false]);

            $ay->update([
                'is_current' => true,
                'status' => 'active',
                'updated_by' => $request->user()->id,
            ]);

            AuditLogService::log(
                'sunday_school',
                'academic_year_activated',
                "Activated academic year {$ay->name}",
                null,
                $ay->toArray(),
                $ay,
                null,
                $ay->diocese_id
            );
        });

        return $this->successResponse($ay, 'Academic year activated successfully.');
    }

    public function completeAcademicYear(Request $request, $id)
    {
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Sunday School Admin'])) {
            return $this->errorResponse('Access Denied', 403);
        }

        $ay = SundaySchoolAcademicYear::findOrFail($id);
        
        $ay->update([
            'status' => 'completed',
            'is_current' => false,
            'updated_by' => $request->user()->id,
        ]);

        AuditLogService::log(
            'sunday_school',
            'academic_year_completed',
            "Completed academic year {$ay->name}",
            null,
            $ay->toArray(),
            $ay,
            null,
            $ay->diocese_id
        );

        return $this->successResponse($ay, 'Academic year completed successfully.');
    }

    // ==========================================
    // 2. Levels
    // ==========================================
    public function listLevels(Request $request)
    {
        $levels = SundaySchoolLevel::orderBy('sort_order')->get();
        return $this->successResponse($levels, 'Levels retrieved successfully.');
    }

    public function storeLevel(Request $request)
    {
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Sunday School Admin'])) {
            return $this->errorResponse('Access Denied', 403);
        }

        $validator = Validator::make($request->all(), [
            'diocese_id' => 'required|exists:dioceses,id',
            'level_name' => 'required|string|max:255',
            'level_code' => 'required|string|max:50',
            'sort_order' => 'required|integer',
            'minimum_age' => 'nullable|integer',
            'maximum_age' => 'nullable|integer',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:active,inactive,archived',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        // Check diocese level_code uniqueness
        $exists = SundaySchoolLevel::where('diocese_id', $request->input('diocese_id'))
            ->where('level_code', $request->input('level_code'))
            ->exists();

        if ($exists) {
            return $this->errorResponse('Level code already exists for this diocese.', 422);
        }

        $level = SundaySchoolLevel::create($validator->validated());

        return $this->successResponse($level, 'Level created successfully.', 201);
    }

    public function archiveLevel(Request $request, $id)
    {
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Sunday School Admin'])) {
            return $this->errorResponse('Access Denied', 403);
        }

        $level = SundaySchoolLevel::findOrFail($id);
        $level->update(['status' => 'archived']);

        return $this->successResponse($level, 'Level archived successfully.');
    }

    // ==========================================
    // 3. Classes
    // ==========================================
    public function listClasses(Request $request)
    {
        $user = $request->user();
        $classIds = $this->getAccessibleClassIds($user);

        $query = SundaySchoolClass::with(['level', 'academicYear', 'primaryTeacher', 'assistantTeacher']);

        if ($classIds !== null) {
            $query->whereIn('id', $classIds);
        }

        if ($request->has('academic_year_id')) {
            $query->where('academic_year_id', $request->input('academic_year_id'));
        }

        $classes = $query->get();
        return $this->successResponse($classes, 'Classes retrieved successfully.');
    }

    public function storeClass(Request $request)
    {
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Sunday School Admin', 'Priest / Vicar', 'Parish Admin'])) {
            return $this->errorResponse('Access Denied', 403);
        }

        $validator = Validator::make($request->all(), [
            'diocese_id' => 'required|exists:dioceses,id',
            'church_id' => 'nullable|exists:churches,id',
            'academic_year_id' => 'required|exists:sunday_school_academic_years,id',
            'level_id' => 'required|exists:sunday_school_levels,id',
            'class_name' => 'required|string|max:255',
            'mode' => 'required|string|in:online,offline,hybrid',
            'meeting_link' => 'nullable|url',
            'recording_folder_link' => 'nullable|url',
            'class_day' => 'nullable|string',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'timezone' => 'nullable|string',
            'primary_teacher_id' => 'nullable|exists:sunday_school_teachers,id',
            'assistant_teacher_id' => 'nullable|exists:sunday_school_teachers,id',
            'max_students' => 'nullable|integer|min:1',
            'status' => 'nullable|string|in:draft,active,completed,cancelled,archived',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        // Parish scoping validation
        if ($request->filled('church_id')) {
            $userChurchId = $request->user()->active_church_id ?? $request->user()->default_church_id;
            if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Sunday School Admin']) && $request->input('church_id') !== $userChurchId) {
                return $this->errorResponse('Access Denied: Cannot create class for another parish.', 403);
            }
        }

        $class = SundaySchoolClass::create(array_merge($validator->validated(), [
            'created_by' => $request->user()->id,
        ]));

        // Auto-assign primary teacher if provided
        if ($class->primary_teacher_id) {
            DB::table('sunday_school_class_teacher_assignments')->insert([
                'class_id' => $class->id,
                'teacher_id' => $class->primary_teacher_id,
                'role' => 'primary',
                'assigned_from' => Carbon::today(),
                'status' => 'active',
                'created_by' => $request->user()->id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        AuditLogService::log(
            'sunday_school',
            'class_created',
            "Created class {$class->class_name}",
            null,
            $class->toArray(),
            $class,
            $class->church_id,
            $class->diocese_id
        );

        return $this->successResponse($class, 'Class created successfully.', 201);
    }

    public function showClass(Request $request, $id)
    {
        $user = $request->user();
        $class = SundaySchoolClass::with(['level', 'academicYear', 'primaryTeacher', 'assistantTeacher'])->findOrFail($id);

        if (!$this->attendanceService->checkTeacherAccess($user, $class)) {
            return $this->errorResponse('Access Denied: You do not have permission to view this class.', 403);
        }

        return $this->successResponse($class, 'Class details retrieved.');
    }

    public function classStudents(Request $request, $id)
    {
        $user = $request->user();
        $class = SundaySchoolClass::findOrFail($id);

        if (!$this->attendanceService->checkTeacherAccess($user, $class)) {
            return $this->errorResponse('Access Denied: You do not have permission to view this class.', 403);
        }

        $students = SundaySchoolStudent::where('class_id', $class->id)
            ->with(['member'])
            ->get();

        return $this->successResponse($students, 'Class students list retrieved.');
    }

    public function classTeachers(Request $request, $id)
    {
        $user = $request->user();
        $class = SundaySchoolClass::findOrFail($id);

        if (!$this->attendanceService->checkTeacherAccess($user, $class)) {
            return $this->errorResponse('Access Denied.', 403);
        }

        $assignments = DB::table('sunday_school_class_teacher_assignments')
            ->join('sunday_school_teachers', 'sunday_school_class_teacher_assignments.teacher_id', '=', 'sunday_school_teachers.id')
            ->where('sunday_school_class_teacher_assignments.class_id', $class->id)
            ->where('sunday_school_class_teacher_assignments.status', 'active')
            ->select('sunday_school_teachers.*', 'sunday_school_class_teacher_assignments.role')
            ->get();

        return $this->successResponse($assignments, 'Class teachers list retrieved.');
    }

    // ==========================================
    // 4. Teachers
    // ==========================================
    public function listTeachers(Request $request)
    {
        $user = $request->user();
        $query = SundaySchoolTeacher::with(['member', 'user']);

        if (!$user->hasAnyRole(['Super Admin', 'Diocese Admin', 'Sunday School Admin'])) {
            $userChurchId = $user->active_church_id ?? $user->default_church_id;
            $query->where('church_id', $userChurchId);
        }

        $teachers = $query->get();
        return $this->successResponse($teachers, 'Teachers list retrieved successfully.');
    }

    public function storeTeacher(Request $request)
    {
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Sunday School Admin', 'Priest / Vicar', 'Parish Admin'])) {
            return $this->errorResponse('Access Denied', 403);
        }

        $validator = Validator::make($request->all(), [
            'diocese_id' => 'required|exists:dioceses,id',
            'church_id' => 'nullable|exists:churches,id',
            'member_id' => 'nullable|exists:members,id',
            'user_id' => 'nullable|exists:users,id',
            'full_name' => 'required|string|max:255',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'qualification' => 'nullable|string',
            'experience_notes' => 'nullable|string',
            'status' => 'nullable|string|in:active,inactive,archived',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        // Scoping check
        if ($request->filled('church_id')) {
            $userChurchId = $request->user()->active_church_id ?? $request->user()->default_church_id;
            if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Sunday School Admin']) && $request->input('church_id') !== $userChurchId) {
                return $this->errorResponse('Access Denied', 403);
            }
        }

        $teacher = SundaySchoolTeacher::create(array_merge($validator->validated(), [
            'created_by' => $request->user()->id,
        ]));

        return $this->successResponse($teacher, 'Teacher profile created.', 201);
    }

    public function teacherClasses(Request $request, $id)
    {
        $teacher = SundaySchoolTeacher::findOrFail($id);
        $classes = $teacher->classes()->wherePivot('status', 'active')->get();
        return $this->successResponse($classes, 'Teacher classes list retrieved.');
    }

    public function assignClassTeacher(Request $request, $id)
    {
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Sunday School Admin', 'Priest / Vicar', 'Parish Admin'])) {
            return $this->errorResponse('Access Denied', 403);
        }

        $teacher = SundaySchoolTeacher::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'class_id' => 'required|exists:sunday_school_classes,id',
            'role' => 'required|string|in:primary,assistant,substitute',
            'assigned_from' => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $classId = $request->input('class_id');
        $class = SundaySchoolClass::findOrFail($classId);

        // Scope check
        $userChurchId = $request->user()->active_church_id ?? $request->user()->default_church_id;
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Sunday School Admin']) && $class->church_id !== $userChurchId) {
            return $this->errorResponse('Access Denied: Cannot assign teacher to class in another parish.', 403);
        }

        DB::table('sunday_school_class_teacher_assignments')->insert([
            'class_id' => $classId,
            'teacher_id' => $teacher->id,
            'role' => $request->input('role'),
            'assigned_from' => $request->input('assigned_from'),
            'status' => 'active',
            'created_by' => $request->user()->id,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return $this->successResponse(null, 'Teacher assigned to class successfully.');
    }

    public function endClassTeacherAssignment(Request $request, $id)
    {
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Sunday School Admin', 'Priest / Vicar', 'Parish Admin'])) {
            return $this->errorResponse('Access Denied', 403);
        }

        $validator = Validator::make($request->all(), [
            'class_id' => 'required|exists:sunday_school_classes,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $teacher = SundaySchoolTeacher::findOrFail($id);
        $classId = $request->input('class_id');

        DB::table('sunday_school_class_teacher_assignments')
            ->where('class_id', $classId)
            ->where('teacher_id', $teacher->id)
            ->where('status', 'active')
            ->update([
                'status' => 'ended',
                'assigned_to' => Carbon::today(),
                'updated_at' => Carbon::now(),
            ]);

        return $this->successResponse(null, 'Teacher assignment ended.');
    }

    // ==========================================
    // 5. Students & Enrollment
    // ==========================================
    public function listStudents(Request $request)
    {
        $user = $request->user();
        $classIds = $this->getAccessibleClassIds($user);

        $query = SundaySchoolStudent::with(['member', 'class', 'academicYear']);

        if ($classIds !== null) {
            $query->whereIn('class_id', $classIds);
        }

        $students = $query->get();
        return $this->successResponse($students, 'Student enrollments list retrieved.');
    }

    public function storeStudentEnrollment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|exists:members,id',
            'class_id' => 'required|exists:sunday_school_classes,id',
            'academic_year_id' => 'required|exists:sunday_school_academic_years,id',
            'parent_member_id' => 'nullable|exists:members,id',
            'enrollment_date' => 'nullable|date',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        try {
            $student = $this->enrollmentService->enroll($request->all(), $request->user());
            return $this->successResponse($student, 'Student enrolled successfully as pending.', 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function approveStudentEnrollment(Request $request, $id)
    {
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Sunday School Admin', 'Priest / Vicar', 'Parish Admin'])) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $student = $this->enrollmentService->approve($id, $request->user());
            \App\Services\NotificationTriggerService::triggerSundaySchoolEnrollmentApproved($student);
            return $this->successResponse($student, 'Student enrollment approved.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function discontinueStudentEnrollment(Request $request, $id)
    {
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Sunday School Admin', 'Priest / Vicar', 'Parish Admin'])) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $student = $this->enrollmentService->discontinue($id, $request->user(), $request->input('remarks'));
            return $this->successResponse($student, 'Student enrollment discontinued.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    // ==========================================
    // 6. Attendance Marking
    // ==========================================
    public function markAttendance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'class_id' => 'required|exists:sunday_school_classes,id',
            'attendance_date' => 'required|date',
            'records' => 'required|array|min:1',
            'records.*.student_id' => 'required|exists:sunday_school_students,id',
            'records.*.status' => 'required|string|in:present,absent,late,excused',
            'records.*.remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        try {
            $results = $this->attendanceService->markAttendance(
                $request->input('class_id'),
                $request->input('records'),
                $request->input('attendance_date'),
                $request->user()
            );
            return $this->successResponse($results, 'Attendance marked successfully.');
        } catch (Exception $e) {
            $code = str_contains($e->getMessage(), 'Access Denied') ? 403 : 400;
            return $this->errorResponse($e->getMessage(), $code);
        }
    }

    public function classAttendance(Request $request, $id)
    {
        $user = $request->user();
        $class = SundaySchoolClass::findOrFail($id);

        if (!$this->attendanceService->checkTeacherAccess($user, $class)) {
            return $this->errorResponse('Access Denied', 403);
        }

        $records = SundaySchoolAttendance::where('class_id', $class->id)
            ->with(['student.member'])
            ->orderBy('attendance_date', 'desc')
            ->get();

        return $this->successResponse($records, 'Class attendance records retrieved.');
    }

    public function studentAttendance(Request $request, $id)
    {
        $student = SundaySchoolStudent::findOrFail($id);
        $records = SundaySchoolAttendance::where('student_id', $student->id)
            ->orderBy('attendance_date', 'desc')
            ->get();

        return $this->successResponse($records, 'Student attendance records retrieved.');
    }

    // ==========================================
    // 7. Exams & Marks
    // ==========================================
    public function listExams(Request $request)
    {
        $user = $request->user();
        $classIds = $this->getAccessibleClassIds($user);

        $query = SundaySchoolExam::with(['class', 'academicYear']);

        if ($classIds !== null) {
            $query->whereIn('class_id', $classIds);
        }

        $exams = $query->get();
        return $this->successResponse($exams, 'Exams list retrieved.');
    }

    public function storeExam(Request $request)
    {
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Sunday School Admin', 'Priest / Vicar', 'Parish Admin'])) {
            return $this->errorResponse('Access Denied', 403);
        }

        $validator = Validator::make($request->all(), [
            'diocese_id' => 'required|exists:dioceses,id',
            'church_id' => 'nullable|exists:churches,id',
            'academic_year_id' => 'required|exists:sunday_school_academic_years,id',
            'class_id' => 'required|exists:sunday_school_classes,id',
            'exam_name' => 'required|string|max:255',
            'exam_type' => 'required|string|in:weekly_test,midterm,final,oral,written,assignment,project,other',
            'exam_date' => 'required|date',
            'max_marks' => 'required|integer|min:1',
            'pass_marks' => 'nullable|integer|min:0|lte:max_marks',
            'status' => 'nullable|string|in:draft,published,completed,archived',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $exam = SundaySchoolExam::create(array_merge($validator->validated(), [
            'created_by' => $request->user()->id,
        ]));

        return $this->successResponse($exam, 'Exam scheduled successfully.', 201);
    }

    public function storeMarks(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'exam_id' => 'required|exists:sunday_school_exams,id',
            'records' => 'required|array|min:1',
            'records.*.student_id' => 'required|exists:sunday_school_students,id',
            'records.*.marks_obtained' => 'required|numeric|min:0',
            'records.*.remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        try {
            $results = $this->examService->enterMarks(
                $request->input('exam_id'),
                $request->input('records'),
                $request->user()
            );
            return $this->successResponse($results, 'Marks logged successfully.');
        } catch (Exception $e) {
            $code = str_contains($e->getMessage(), 'Access Denied') ? 403 : 400;
            return $this->errorResponse($e->getMessage(), $code);
        }
    }

    public function verifyMarks(Request $request, $id)
    {
        try {
            $this->examService->verifyMarks($id, $request->user());
            return $this->successResponse(null, 'Marks verified and locked.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    // ==========================================
    // 8. Progress Reports & Promotion
    // ==========================================
    public function generateProgressReport(Request $request, $id)
    {
        try {
            $report = $this->progressReportService->generateReport($id, $request->user());
            $student = \App\Models\SundaySchoolStudent::find($id);
            if ($student) {
                \App\Services\NotificationTriggerService::triggerSundaySchoolProgressReportReady($student);
            }
            return $this->successResponse($report, 'Progress report generated successfully.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function promoteStudent(Request $request, $id)
    {
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Sunday School Admin', 'Priest / Vicar', 'Parish Admin'])) {
            return $this->errorResponse('Access Denied', 403);
        }

        $validator = Validator::make($request->all(), [
            'target_class_id' => 'required|exists:sunday_school_classes,id',
            'target_academic_year_id' => 'required|exists:sunday_school_academic_years,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        try {
            $newStudent = $this->promotionService->promote(
                $id,
                $request->input('target_class_id'),
                $request->input('target_academic_year_id'),
                $request->user()
            );
            return $this->successResponse($newStudent, 'Student promoted successfully.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function studentProgressReports(Request $request, $id)
    {
        $reports = SundaySchoolProgressReport::where('student_id', $id)
            ->orderBy('generated_at', 'desc')
            ->get();

        return $this->successResponse($reports, 'Progress reports history retrieved.');
    }

    // ==========================================
    // 9. Certificates
    // ==========================================
    public function issueCertificate(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'certificate_type' => 'required|string|in:completion,participation,merit,promotion',
            'certificate_template_id' => 'required|exists:certificate_templates,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        try {
            $cert = $this->certificateService->issue(
                array_merge($validator->validated(), ['student_id' => $id]),
                $request->user()
            );
            return $this->successResponse($cert, 'Certificate issued successfully.', 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function studentCertificates(Request $request, $id)
    {
        $certs = SundaySchoolCertificate::where('student_id', $id)
            ->with(['certificate'])
            ->get();

        return $this->successResponse($certs, 'Student certificates history retrieved.');
    }

    // ==========================================
    // 10. Parent Views
    // ==========================================
    public function myChildren(Request $request)
    {
        $user = $request->user();
        $parentMember = Member::where('user_id', $user->id)->first();
        if (!$parentMember) {
            return $this->successResponse([], 'No children found (no member profile linked to user).');
        }

        $childrenQuery = SundaySchoolStudent::with(['member', 'class', 'academicYear'])
            ->where(function ($q) use ($parentMember) {
                $q->where('parent_member_id', $parentMember->id)
                  ->orWhere(function ($sq) use ($parentMember) {
                      $sq->where('family_id', $parentMember->family_id)
                         ->whereHas('member', function ($mq) {
                             $mq->whereIn('relationship_to_head', ['son', 'daughter', 'child']);
                         });
                  });
            });

        if (!in_array(strtolower($parentMember->relationship_to_head), ['head', 'spouse', 'mother', 'father', 'guardian'])) {
            $childrenQuery = SundaySchoolStudent::with(['member', 'class', 'academicYear'])
                ->where('parent_member_id', $parentMember->id);
        }

        $children = $childrenQuery->get();
        return $this->successResponse($children, 'Children retrieved successfully.');
    }

    public function childDetails(Request $request, $student_id)
    {
        $user = $request->user();
        $student = SundaySchoolStudent::with(['member', 'class', 'academicYear', 'attendance', 'marks.exam'])->findOrFail($student_id);

        $parentMember = Member::where('user_id', $user->id)->first();
        if (!$parentMember) {
            return $this->errorResponse('Access Denied: You are not authorized to view this child.', 403);
        }

        if (!$this->enrollmentService->verifyParentChildRelationship($parentMember, $student->member)) {
            return $this->errorResponse('Access Denied: Parent-child relationship is not verified.', 403);
        }

        return $this->successResponse($student, 'Child details retrieved successfully.');
    }

    // ==========================================
    // 11. Exports (Child Privacy Protected)
    // ==========================================
    public function exportStudents(Request $request)
    {
        if (!$request->user()->hasPermissionTo('export_sunday_school_child_data')) {
            return $this->errorResponse('Access Denied: You do not have permission to export Sunday School child data.', 403);
        }

        $students = SundaySchoolStudent::with(['member', 'class', 'academicYear'])->get();

        $csvData = "ID,Student Name,Member Code,Class,Academic Year,Status\n";
        foreach ($students as $s) {
            $csvData .= "{$s->id},\"{$s->member->full_name}\",\"{$s->member->member_code}\",\"{$s->class->class_name}\",\"{$s->academicYear->name}\",\"{$s->enrollment_status}\"\n";
        }

        return response($csvData)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="sunday_school_students.csv"');
    }

    public function exportAttendance(Request $request)
    {
        if (!$request->user()->hasPermissionTo('export_sunday_school_child_data')) {
            return $this->errorResponse('Access Denied: You do not have permission to export Sunday School child data.', 403);
        }

        $attendance = SundaySchoolAttendance::with(['student.member', 'class'])->get();

        $csvData = "ID,Student Name,Class,Date,Status,Remarks\n";
        foreach ($attendance as $a) {
            $csvData .= "{$a->id},\"{$a->student->member->full_name}\",\"{$a->class->class_name}\",{$a->attendance_date->toDateString()},{$a->status},\"{$a->remarks}\"\n";
        }

        return response($csvData)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="sunday_school_attendance.csv"');
    }

    public function exportExamResults(Request $request)
    {
        if (!$request->user()->hasPermissionTo('export_sunday_school_child_data')) {
            return $this->errorResponse('Access Denied: You do not have permission to export Sunday School child data.', 403);
        }

        $marks = SundaySchoolMark::with(['student.member', 'exam'])->get();

        $csvData = "ID,Student Name,Exam,Marks Obtained,Max Marks,Grade,Status\n";
        foreach ($marks as $m) {
            $csvData .= "{$m->id},\"{$m->student->member->full_name}\",\"{$m->exam->exam_name}\",{$m->marks_obtained},{$m->exam->max_marks},{$m->grade},{$m->result_status}\n";
        }

        return response($csvData)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="sunday_school_exam_results.csv"');
    }

    public function activateClass(Request $request, $id)
    {
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Sunday School Admin', 'Priest / Vicar', 'Parish Admin'])) {
            return $this->errorResponse('Access Denied', 403);
        }
        $class = SundaySchoolClass::findOrFail($id);
        $class->update(['status' => 'active', 'updated_by' => $request->user()->id]);
        return $this->successResponse($class, 'Class activated successfully.');
    }

    public function completeClass(Request $request, $id)
    {
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Sunday School Admin', 'Priest / Vicar', 'Parish Admin'])) {
            return $this->errorResponse('Access Denied', 403);
        }
        $class = SundaySchoolClass::findOrFail($id);
        $class->update(['status' => 'completed', 'updated_by' => $request->user()->id]);
        return $this->successResponse($class, 'Class completed successfully.');
    }

    public function cancelClass(Request $request, $id)
    {
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Sunday School Admin', 'Priest / Vicar', 'Parish Admin'])) {
            return $this->errorResponse('Access Denied', 403);
        }
        $class = SundaySchoolClass::findOrFail($id);
        $class->update(['status' => 'cancelled', 'updated_by' => $request->user()->id]);
        return $this->successResponse($class, 'Class cancelled successfully.');
    }

    public function publishExam(Request $request, $id)
    {
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Sunday School Admin', 'Priest / Vicar', 'Parish Admin'])) {
            return $this->errorResponse('Access Denied', 403);
        }
        $exam = SundaySchoolExam::findOrFail($id);
        $exam->update(['status' => 'published', 'updated_by' => $request->user()->id]);
        \App\Services\NotificationTriggerService::triggerSundaySchoolExamPublished($exam);
        return $this->successResponse($exam, 'Exam results published successfully.');
    }

    public function completeExam(Request $request, $id)
    {
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin', 'Sunday School Admin', 'Priest / Vicar', 'Parish Admin'])) {
            return $this->errorResponse('Access Denied', 403);
        }
        $exam = SundaySchoolExam::findOrFail($id);
        $exam->update(['status' => 'completed', 'updated_by' => $request->user()->id]);
        return $this->successResponse($exam, 'Exam completed successfully.');
    }

    // ==========================================
    // Scoping Helpers
    // ==========================================
    protected function getAccessibleClassIds(User $user): ?array
    {
        if ($user->hasRole(['Super Admin', 'Diocese Admin', 'Sunday School Admin'])) {
            return null; // All classes
        }

        if ($user->hasRole(['Parish Admin', 'Priest / Vicar', 'Parish Secretary'])) {
            $userChurchId = $user->active_church_id ?? $user->default_church_id;
            return SundaySchoolClass::where('church_id', $userChurchId)->pluck('id')->toArray();
        }

        $teacher = SundaySchoolTeacher::where('user_id', $user->id)->first();
        if (!$teacher) {
            return []; // No access
        }

        return DB::table('sunday_school_class_teacher_assignments')
            ->where('teacher_id', $teacher->id)
            ->where('status', 'active')
            ->pluck('class_id')
            ->toArray();
    }
}
