<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Models\User;
use App\Models\UserChurchAccess;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        if ($request->user()->cannot('viewAny', User::class)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $query = User::query()->with(['roles', 'defaultChurch', 'activeChurch']);

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->has('role')) {
            $query->role($request->input('role'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = $request->input('per_page', 15);
        $users = $query->paginate($perPage);

        return $this->paginatedResponse($users, 'Users retrieved successfully');
    }

    public function show(Request $request, $id)
    {
        $userModel = User::with(['roles', 'permissions', 'defaultChurch', 'activeChurch', 'churchAccess.church'])->find($id);

        if (!$userModel) {
            return $this->errorResponse('User not found', 404);
        }

        if ($request->user()->cannot('view', $userModel)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        return $this->successResponse($userModel, 'User details retrieved successfully');
    }

    public function store(Request $request)
    {
        if ($request->user()->cannot('create', User::class)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:50',
            'password' => ['required', 'string', \Illuminate\Validation\Rules\Password::defaults()],
            'default_diocese_id' => 'required|exists:dioceses,id',
            'default_church_id' => 'nullable|exists:churches,id',
            'role' => 'required|string|exists:roles,name',
            'preferred_language' => 'nullable|in:en,ml,de',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $roleName = $data['role'];
        unset($data['role']);

        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = true;

        $newUser = User::create($data);
        $newUser->assignRole($roleName);

        $newValues = array_merge($newUser->toArray(), $request->only(['password', 'password_confirmation']));
        AuditLogService::log(
            'users',
            'user_created',
            "Created user account for '{$newUser->email}' with role '{$roleName}'",
            null,
            $newValues,
            $newUser
        );

        return $this->successResponse($newUser, 'User created successfully', 201);
    }

    public function update(Request $request, $id)
    {
        $userModel = User::find($id);
        if (!$userModel) {
            return $this->errorResponse('User not found', 404);
        }

        if ($request->user()->cannot('update', $userModel)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'password' => ['nullable', 'string', \Illuminate\Validation\Rules\Password::defaults()],
            'default_diocese_id' => 'sometimes|required|exists:dioceses,id',
            'default_church_id' => 'nullable|exists:churches,id',
            'role' => 'sometimes|required|string|exists:roles,name',
            'preferred_language' => 'nullable|in:en,ml,de',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $oldValues = $userModel->toArray();
        $data = $validator->validated();

        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $roleName = $data['role'] ?? null;
        unset($data['role']);

        $userModel->update($data);

        if ($roleName && $request->user()->can('manageAccess', User::class)) {
            $userModel->syncRoles([$roleName]);
        }

        $newValues = array_merge($userModel->toArray(), $request->only(['password', 'password_confirmation']));
        AuditLogService::log(
            'users',
            'user_updated',
            "Updated user account details for '{$userModel->email}'",
            $oldValues,
            $newValues,
            $userModel
        );

        return $this->successResponse($userModel, 'User updated successfully');
    }

    public function destroy(Request $request, $id)
    {
        $userModel = User::find($id);
        if (!$userModel) {
            return $this->errorResponse('User not found', 404);
        }

        if ($request->user()->cannot('delete', $userModel)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $oldValues = $userModel->toArray();
        $userModel->is_active = false;
        $userModel->save();

        AuditLogService::log(
            'users',
            'user_deactivated',
            "Deactivated user account '{$userModel->email}'",
            $oldValues,
            $userModel->toArray(),
            $userModel
        );

        return $this->successResponse([], 'User deactivated successfully');
    }

    public function storeAccess(Request $request, $id)
    {
        $userModel = User::find($id);
        if (!$userModel) {
            return $this->errorResponse('User not found', 404);
        }

        if ($request->user()->cannot('manageAccess', User::class)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $validator = Validator::make($request->all(), [
            'diocese_id' => 'required|exists:dioceses,id',
            'church_id' => 'nullable|exists:churches,id',
            'access_scope' => 'required|in:diocese_all,church_specific,ministry_specific',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $data['user_id'] = $userModel->id;
        $data['status'] = 'active';
        $data['created_by'] = $request->user()->id;

        $access = UserChurchAccess::create($data);

        AuditLogService::log(
            'users',
            'user_access_created',
            "Granted access to Church ID " . ($access->church_id ?? 'all') . " for user '{$userModel->email}'",
            null,
            $access->toArray(),
            $userModel,
            $access->church_id
        );

        return $this->successResponse($access, 'Church access mapping added successfully', 201);
    }

    public function destroyAccess(Request $request, $id, $accessId)
    {
        $userModel = User::find($id);
        if (!$userModel) {
            return $this->errorResponse('User not found', 404);
        }

        if ($request->user()->cannot('manageAccess', User::class)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $access = UserChurchAccess::where('user_id', $userModel->id)->find($accessId);
        if (!$access) {
            return $this->errorResponse('Church access mapping not found', 404);
        }

        $oldValues = $access->toArray();
        $access->status = 'inactive';
        $access->save();
        $access->delete();

        AuditLogService::log(
            'users',
            'user_access_deleted',
            "Revoked access to Church ID " . ($access->church_id ?? 'all') . " for user '{$userModel->email}'",
            $oldValues,
            $access->toArray(),
            $userModel,
            $access->church_id
        );

        return $this->successResponse([], 'Church access mapping revoked successfully');
    }
}
