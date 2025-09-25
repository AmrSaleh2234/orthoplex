<?php

namespace Modules\RolesAndPermissions\app\Http\Controllers;

use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\RolesAndPermissions\app\Http\Requests\CreateRoleRequest;
use Modules\RolesAndPermissions\app\Http\Requests\SyncPermissionsRequest;
use Modules\RolesAndPermissions\app\Models\Role;

class RoleController extends Controller
{
    use ApiResponse;

    // We will implement the methods for creating roles and syncing permissions here.

    public function create(CreateRoleRequest $request): JsonResponse
    {
        $role = Role::create(['name' => $request->name, 'guard_name' => 'api']);

        return $this->successResponse($role, 'Role created successfully.', 201);
    }

    public function syncPermissions(SyncPermissionsRequest $request, Role $role): JsonResponse
    {
        $role->syncPermissions($request->permission_ids);

        return $this->successResponse($role->load('permissions'), 'Permissions synced successfully.');
    }
}
