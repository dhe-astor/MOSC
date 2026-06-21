<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Models\PriestAssignment;
use App\Models\Priest;
use App\Models\Church;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;

class PriestAssignmentController extends Controller
{
    use ApiResponse;

    public function store(Request $request, $priestId)
    {
        if ($request->user()->cannot('create', PriestAssignment::class)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $priest = Priest::find($priestId);
        if (!$priest) {
            return $this->errorResponse('Priest not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'church_id' => 'required|exists:churches,id',
            'role' => 'required|in:vicar,assistant_vicar,in_charge,visiting_priest',
            'assignment_start_date' => 'required|date',
            'assignment_end_date' => 'nullable|date|after_or_equal:assignment_start_date',
            'is_primary' => 'boolean',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $churchId = $request->input('church_id');
        $isPrimary = $request->boolean('is_primary', false);
        $startDate = Carbon::parse($request->input('assignment_start_date'));

        if ($isPrimary) {
            // Check if there is already an active primary vicar for this church
            $existingPrimary = PriestAssignment::where('church_id', $churchId)
                ->where('is_primary', true)
                ->where('status', 'active')
                ->first();

            if ($existingPrimary) {
                // End the previous primary assignment
                $existingPrimary->assignment_end_date = $startDate->copy()->subDay();
                $existingPrimary->status = 'ended';
                $existingPrimary->save();

                AuditLogService::log(
                    'assignments',
                    'priest_assignment_ended',
                    "Automatically ended previous primary assignment of Priest ID {$existingPrimary->priest_id} at Church ID {$churchId} to assign new primary vicar.",
                    null,
                    $existingPrimary->toArray(),
                    $existingPrimary,
                    $churchId
                );
            }
        }

        $data = $validator->validated();
        $data['priest_id'] = $priest->id;
        $data['status'] = 'active';
        $data['created_by'] = $request->user()->id;

        $assignment = PriestAssignment::create($data);

        AuditLogService::log(
            'assignments',
            'priest_assignment_created',
            "Assigned Priest '{$priest->full_name}' to Church ID {$churchId} as '{$assignment->role}'",
            null,
            $assignment->toArray(),
            $assignment,
            $churchId
        );

        return $this->successResponse($assignment, 'Priest assigned successfully', 201);
    }

    public function update(Request $request, $id)
    {
        $assignment = PriestAssignment::find($id);
        if (!$assignment) {
            return $this->errorResponse('Assignment not found', 404);
        }

        if ($request->user()->cannot('update', $assignment)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $validator = Validator::make($request->all(), [
            'role' => 'sometimes|required|in:vicar,assistant_vicar,in_charge,visiting_priest',
            'assignment_start_date' => 'sometimes|required|date',
            'assignment_end_date' => 'nullable|date|after_or_equal:assignment_start_date',
            'is_primary' => 'boolean',
            'remarks' => 'nullable|string',
            'status' => 'sometimes|required|in:active,ended,temporary,cancelled',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $oldValues = $assignment->toArray();
        $data = $validator->validated();

        if ($request->boolean('is_primary', false) && !$assignment->is_primary) {
            // Check if there is already an active primary vicar for this church
            $existingPrimary = PriestAssignment::where('church_id', $assignment->church_id)
                ->where('is_primary', true)
                ->where('status', 'active')
                ->where('id', '!=', $assignment->id)
                ->first();

            if ($existingPrimary) {
                // End the previous primary assignment
                $startDate = Carbon::parse($request->input('assignment_start_date', $assignment->assignment_start_date));
                $existingPrimary->assignment_end_date = $startDate->copy()->subDay();
                $existingPrimary->status = 'ended';
                $existingPrimary->save();
            }
        }

        $assignment->update($data);

        AuditLogService::log(
            'assignments',
            'priest_assignment_updated',
            "Updated priest assignment ID {$assignment->id}",
            $oldValues,
            $assignment->toArray(),
            $assignment,
            $assignment->church_id
        );

        return $this->successResponse($assignment, 'Assignment updated successfully');
    }

    public function destroy(Request $request, $id)
    {
        $assignment = PriestAssignment::find($id);
        if (!$assignment) {
            return $this->errorResponse('Assignment not found', 404);
        }

        if ($request->user()->cannot('delete', $assignment)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $oldValues = $assignment->toArray();

        // End the assignment rather than hard delete
        $assignment->assignment_end_date = Carbon::today();
        $assignment->status = 'ended';
        $assignment->save();

        // Soft delete the record
        $assignment->delete();

        AuditLogService::log(
            'assignments',
            'priest_assignment_ended',
            "Ended and soft-deleted priest assignment ID {$assignment->id}",
            $oldValues,
            $assignment->toArray(),
            $assignment,
            $assignment->church_id
        );

        return $this->successResponse([], 'Assignment ended and soft-deleted successfully');
    }
}
