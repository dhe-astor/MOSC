<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Models\Priest;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PriestController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        if ($request->user()->cannot('viewAny', Priest::class)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $query = Priest::query();

        // Filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('baptism_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('clergy_rank')) {
            $query->where('clergy_rank', $request->input('clergy_rank'));
        }

        $perPage = $request->input('per_page', 15);
        $priests = $query->orderBy('sort_order')->orderBy('full_name')->paginate($perPage);

        return $this->paginatedResponse($priests, 'Priests retrieved successfully');
    }

    public function show(Request $request, $id)
    {
        $priest = Priest::with(['diocese', 'user', 'assignments.church'])->find($id);

        if (!$priest) {
            return $this->errorResponse('Priest not found', 404);
        }

        if ($request->user()->cannot('view', $priest)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        return $this->successResponse($priest, 'Priest details retrieved successfully');
    }

    public function store(Request $request)
    {
        if ($request->user()->cannot('create', Priest::class)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $validator = Validator::make($request->all(), [
            'diocese_id' => 'required|exists:dioceses,id',
            'user_id' => 'nullable|exists:users,id|unique:priests,user_id',
            'title' => 'nullable|string|max:50',
            'full_name' => 'required|string|max:255',
            'baptism_name' => 'nullable|string|max:255',
            'clergy_rank' => 'required|in:metropolitan,priest,ramban,deacon,assistant_vicar',
            'ordination_date' => 'nullable|date',
            'date_of_birth' => 'nullable|date',
            'primary_phone' => 'required|string|max:50',
            'whatsapp_phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'photo_path' => 'nullable|string|max:255',
            'biography' => 'nullable|string',
            'status' => 'required|in:active,transferred,retired,inactive,deceased',
            'show_on_website' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $priest = Priest::create($validator->validated());

        AuditLogService::log(
            'priests',
            'priest_created',
            "Priest profile created for '{$priest->full_name}'",
            null,
            $priest->toArray(),
            $priest
        );

        return $this->successResponse($priest, 'Priest profile created successfully', 201);
    }

    public function update(Request $request, $id)
    {
        $priest = Priest::find($id);

        if (!$priest) {
            return $this->errorResponse('Priest not found', 404);
        }

        if ($request->user()->cannot('update', $priest)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|exists:users,id|unique:priests,user_id,' . $id,
            'title' => 'nullable|string|max:50',
            'full_name' => 'sometimes|required|string|max:255',
            'baptism_name' => 'nullable|string|max:255',
            'clergy_rank' => 'sometimes|required|in:metropolitan,priest,ramban,deacon,assistant_vicar',
            'ordination_date' => 'nullable|date',
            'date_of_birth' => 'nullable|date',
            'primary_phone' => 'sometimes|required|string|max:50',
            'whatsapp_phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'photo_path' => 'nullable|string|max:255',
            'biography' => 'nullable|string',
            'status' => 'sometimes|required|in:active,transferred,retired,inactive,deceased',
            'show_on_website' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $oldValues = $priest->toArray();
        $priest->update($validator->validated());

        AuditLogService::log(
            'priests',
            'priest_updated',
            "Priest profile updated for '{$priest->full_name}'",
            $oldValues,
            $priest->toArray(),
            $priest
        );

        return $this->successResponse($priest, 'Priest profile updated successfully');
    }

    public function destroy(Request $request, $id)
    {
        $priest = Priest::find($id);

        if (!$priest) {
            return $this->errorResponse('Priest not found', 404);
        }

        if ($request->user()->cannot('delete', $priest)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $oldValues = $priest->toArray();

        $priest->status = 'inactive';
        $priest->save();
        $priest->delete();

        AuditLogService::log(
            'priests',
            'priest_deactivated',
            "Priest profile for '{$priest->full_name}' deactivated and soft-deleted",
            $oldValues,
            $priest->toArray(),
            $priest
        );

        return $this->successResponse([], 'Priest profile deactivated and soft-deleted successfully');
    }
}
