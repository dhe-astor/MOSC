<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Models\Church;
use App\Services\ChurchAccessService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ChurchController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user->hasAnyPermission(['view_churches', 'manage_churches'])) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $query = Church::query();
        $query = ChurchAccessService::scopeQuery($user, $query);

        // Filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('short_name', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($request->has('country_id')) {
            $query->where('country_id', $request->input('country_id'));
        }

        if ($request->has('canonical_status')) {
            $query->where('canonical_status', $request->input('canonical_status'));
        }

        if ($request->has('church_type')) {
            $query->where('church_type', $request->input('church_type'));
        }

        $perPage = $request->input('per_page', 50);
        $churches = $query->orderBy('sort_order')->orderBy('name')->paginate($perPage);

        return $this->paginatedResponse($churches, 'Churches retrieved successfully');
    }

    public function show(Request $request, $id)
    {
        $church = Church::with(['countryRelation', 'assignments.priest'])->find($id);

        if (!$church) {
            return $this->errorResponse('Church not found', 404);
        }

        if ($request->user()->cannot('view', $church)) {
            return $this->errorResponse('You do not have access to this church', 403);
        }

        return $this->successResponse($church, 'Church details retrieved successfully');
    }

    public function store(Request $request)
    {
        if ($request->user()->cannot('create', Church::class)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $validator = Validator::make($request->all(), [
            'diocese_id' => 'required|exists:dioceses,id',
            'country_id' => 'required|exists:countries,id',
            'name' => 'required|string|max:255',
            'short_name' => 'required|string|max:255',
            'church_type' => 'required|in:church,parish,congregation,service_centre,community',
            'patron_saint' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'state_region' => 'nullable|string|max:255',
            'country' => 'required|string|max:255',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'public_email' => 'nullable|email|max:255',
            'public_phone' => 'nullable|string|max:50',
            'website_url' => 'nullable|url|max:255',
            'facebook_url' => 'nullable|url|max:255',
            'instagram_url' => 'nullable|url|max:255',
            'google_map_url' => 'nullable|url|max:500',
            'established_date' => 'nullable|date',
            'canonical_status' => 'required|in:active,inactive,upcoming,closed,draft,merged',
            'membership_code_prefix' => 'nullable|string|max:10',
            'slug' => 'sometimes|required|string|max:255|unique:churches,slug',
            'public_page_slug' => 'required|string|max:255|unique:churches,public_page_slug',
            'description' => 'nullable|string',
            'history' => 'nullable|string',
            'qurbana_timing' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'show_on_website' => 'boolean',
            'source_url' => 'nullable|url',
            'source_raw_name' => 'nullable|string',
            'source_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $data = $validator->validated();
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['short_name'] ?? $data['name']);
        }
        $data['created_by'] = $request->user()->id;

        $church = Church::create($data);

        AuditLogService::log(
            'churches',
            'church_created',
            "Church '{$church->name}' created successfully",
            null,
            $church->toArray(),
            $church,
            $church->id
        );

        return $this->successResponse($church, 'Church created successfully', 201);
    }

    public function update(Request $request, $id)
    {
        $church = Church::find($id);

        if (!$church) {
            return $this->errorResponse('Church not found', 404);
        }

        if ($request->user()->cannot('update', $church)) {
            return $this->errorResponse('You do not have access to this church', 403);
        }

        $validator = Validator::make($request->all(), [
            'country_id' => 'sometimes|required|exists:countries,id',
            'name' => 'sometimes|required|string|max:255',
            'short_name' => 'sometimes|required|string|max:255',
            'church_type' => 'sometimes|required|in:church,parish,congregation,service_centre,community',
            'patron_saint' => 'nullable|string|max:255',
            'city' => 'sometimes|required|string|max:255',
            'state_region' => 'nullable|string|max:255',
            'country' => 'sometimes|required|string|max:255',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'public_email' => 'nullable|email|max:255',
            'public_phone' => 'nullable|string|max:50',
            'website_url' => 'nullable|url|max:255',
            'facebook_url' => 'nullable|url|max:255',
            'instagram_url' => 'nullable|url|max:255',
            'google_map_url' => 'nullable|url|max:500',
            'established_date' => 'nullable|date',
            'canonical_status' => 'sometimes|required|in:active,inactive,upcoming,closed,draft,merged',
            'membership_code_prefix' => 'nullable|string|max:10',
            'slug' => 'sometimes|required|string|max:255|unique:churches,slug,' . $id,
            'public_page_slug' => 'sometimes|required|string|max:255|unique:churches,public_page_slug,' . $id,
            'description' => 'nullable|string',
            'history' => 'nullable|string',
            'qurbana_timing' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'show_on_website' => 'boolean',
            'source_url' => 'nullable|url',
            'source_raw_name' => 'nullable|string',
            'source_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $oldValues = $church->toArray();
        
        $data = $validator->validated();
        $data['updated_by'] = $request->user()->id;

        $church->update($data);

        AuditLogService::log(
            'churches',
            'church_updated',
            "Church '{$church->name}' updated successfully",
            $oldValues,
            $church->toArray(),
            $church,
            $church->id
        );

        return $this->successResponse($church, 'Church updated successfully');
    }

    public function destroy(Request $request, $id)
    {
        $church = Church::find($id);

        if (!$church) {
            return $this->errorResponse('Church not found', 404);
        }

        if ($request->user()->cannot('delete', $church)) {
            return $this->errorResponse('You do not have access to this church', 403);
        }

        $oldValues = $church->toArray();

        // Update status to inactive rather than hard delete, then soft delete
        $church->canonical_status = 'inactive';
        $church->save();
        
        $church->delete();

        AuditLogService::log(
            'churches',
            'church_deactivated',
            "Church '{$church->name}' deactivated and soft-deleted successfully",
            $oldValues,
            $church->toArray(),
            $church,
            $church->id
        );

        return $this->successResponse([], 'Church deactivated and soft-deleted successfully');
    }
}
