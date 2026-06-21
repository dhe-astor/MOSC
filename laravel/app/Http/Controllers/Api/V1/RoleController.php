<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    use ApiResponse;

    public function roles(Request $request)
    {
        if (!$request->user()->hasAnyPermission(['manage_users', 'manage_roles'])) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $roles = Role::orderBy('name')->get(['id', 'name']);
        return $this->successResponse($roles, 'Roles retrieved successfully');
    }

    public function permissions(Request $request)
    {
        if (!$request->user()->hasAnyPermission(['manage_users', 'manage_roles'])) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $permissions = Permission::orderBy('name')->get(['id', 'name']);
        return $this->successResponse($permissions, 'Permissions retrieved successfully');
    }
}
