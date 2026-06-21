<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Models\Course;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Course::query();

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        } else {
            $query->where('status', '!=', 'archived');
        }

        if ($request->has('course_type')) {
            $query->where('course_type', $request->input('course_type'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $courses = $query->orderBy('name')->get();

        return $this->successResponse($courses, 'Courses retrieved successfully');
    }

    public function store(Request $request)
    {
        // Only Diocese Admin / Super Admin can manage courses definitions
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin'])) {
            return $this->errorResponse('Unauthorized to create courses', 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'course_type' => 'required|string|in:pre_marriage,post_marriage,syriac_language,bible_course,liturgical_course,altar_assistants,other',
            'description' => 'nullable|string',
            'eligibility' => 'nullable|string',
            'default_fee_amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'certificate_enabled' => 'nullable|boolean',
            'certificate_template_id' => 'nullable|integer|exists:certificate_templates,id',
            'feedback_required' => 'nullable|boolean',
            'attendance_required_percentage' => 'nullable|integer|min:0|max:100',
            'show_on_portal' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $slug = Str::slug($request->input('name'));
        if (Course::where('slug', $slug)->exists()) {
            $slug .= '-' . Str::random(5);
        }

        $course = Course::create(array_merge($validator->validated(), [
            'diocese_id' => $request->user()->default_diocese_id ?? 1,
            'slug' => $slug,
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]));

        AuditLogService::log(
            'courses',
            'course_created',
            "Course {$course->name} created",
            null,
            $course->toArray(),
            $course,
            null,
            $course->diocese_id
        );

        return $this->successResponse($course, 'Course created successfully', 201);
    }

    public function show($id)
    {
        $course = Course::findOrFail($id);
        return $this->successResponse($course, 'Course retrieved successfully');
    }

    public function update(Request $request, $id)
    {
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin'])) {
            return $this->errorResponse('Unauthorized to update courses', 403);
        }

        $course = Course::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'eligibility' => 'nullable|string',
            'default_fee_amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'certificate_enabled' => 'nullable|boolean',
            'certificate_template_id' => 'nullable|integer|exists:certificate_templates,id',
            'feedback_required' => 'nullable|boolean',
            'attendance_required_percentage' => 'nullable|integer|min:0|max:100',
            'show_on_portal' => 'nullable|boolean',
            'status' => 'nullable|string|in:active,inactive,archived',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $oldValues = $course->toArray();

        $slug = Str::slug($request->input('name'));
        if ($slug !== $course->slug && Course::where('slug', $slug)->where('id', '!=', $course->id)->exists()) {
            $slug .= '-' . Str::random(5);
        }

        $course->update(array_merge($validator->validated(), [
            'slug' => $slug,
            'updated_by' => $request->user()->id,
        ]));

        AuditLogService::log(
            'courses',
            'course_updated',
            "Course {$course->name} updated",
            $oldValues,
            $course->toArray(),
            $course,
            null,
            $course->diocese_id
        );

        return $this->successResponse($course, 'Course updated successfully');
    }

    public function activate(Request $request, $id)
    {
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin'])) {
            return $this->errorResponse('Unauthorized to activate courses', 403);
        }

        $course = Course::findOrFail($id);
        $oldValues = $course->toArray();
        $course->update(['status' => 'active', 'updated_by' => $request->user()->id]);

        AuditLogService::log(
            'courses',
            'course_activated',
            "Course {$course->name} activated",
            $oldValues,
            $course->toArray(),
            $course,
            null,
            $course->diocese_id
        );

        return $this->successResponse($course, 'Course activated successfully');
    }

    public function deactivate(Request $request, $id)
    {
        if (!$request->user()->hasAnyRole(['Super Admin', 'Diocese Admin'])) {
            return $this->errorResponse('Unauthorized to deactivate courses', 403);
        }

        $course = Course::findOrFail($id);
        $oldValues = $course->toArray();
        $course->update(['status' => 'inactive', 'updated_by' => $request->user()->id]);

        AuditLogService::log(
            'courses',
            'course_deactivated',
            "Course {$course->name} deactivated",
            $oldValues,
            $course->toArray(),
            $course,
            null,
            $course->diocese_id
        );

        return $this->successResponse($course, 'Course deactivated successfully');
    }
}
