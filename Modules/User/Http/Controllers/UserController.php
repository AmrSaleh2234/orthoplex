<?php

namespace Modules\User\Http\Controllers;

use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\User\Models\User;
use Modules\User\Services\UserService;
use Modules\User\Http\Requests\CreateUserRequest;
use Modules\User\Http\Requests\UpdateUserRequest;
use Modules\User\Http\Requests\BulkUserOperationRequest;

/**
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     required={"id", "name", "email", "global_id", "status"},
 *     @OA\Property(property="id", type="integer", description="User ID"),
 *     @OA\Property(property="name", type="string", description="User full name"),
 *     @OA\Property(property="email", type="string", format="email", description="User email address"),
 *     @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true, description="Email verification timestamp"),
 *     @OA\Property(property="status", type="string", enum={"active", "inactive", "suspended"}, description="User status"),
 *     @OA\Property(property="global_id", type="string", format="uuid", description="Global identifier for synced resources"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Last update timestamp"),
 *     @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, description="Soft deletion timestamp"),
 *     @OA\Property(
 *         property="roles",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="id", type="integer"),
 *             @OA\Property(property="name", type="string"),
 *             @OA\Property(property="guard_name", type="string")
 *         ),
 *         description="User roles"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="UserPagination",
 *     type="object",
 *     @OA\Property(
 *         property="data",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/User")
 *     ),
 *     @OA\Property(property="path", type="string"),
 *     @OA\Property(property="per_page", type="integer"),
 *     @OA\Property(property="next_cursor", type="string", nullable=true),
 *     @OA\Property(property="prev_cursor", type="string", nullable=true),
 *     @OA\Property(property="next_page_url", type="string", nullable=true),
 *     @OA\Property(property="prev_page_url", type="string", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="ApiResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", description="Request success status"),
 *     @OA\Property(property="message", type="string", description="Response message"),
 *     @OA\Property(property="data", description="Response data")
 * )
 *
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", description="Error message"),
 *     @OA\Property(property="errors", type="object", description="Validation errors")
 * )
 */

class UserController extends Controller
{
    use ApiResponse;

    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/users",
     *     operationId="getUsersList",
     *     tags={"Users"},
     *     summary="Get list of users",
     *     description="Retrieve a paginated list of users with optional filtering, sorting, and includes",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="filter[name]",
     *         in="query",
     *         description="Filter by user name",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="filter[email]",
     *         in="query",
     *         description="Filter by user email",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="filter[status]",
     *         in="query",
     *         description="Filter by user status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"active", "inactive", "suspended"})
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort by field (name, email, created_at)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="include",
     *         in="query",
     *         description="Include relations (roles)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="cursor",
     *         in="query",
     *         description="Cursor for pagination",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Users retrieved successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/UserPagination")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $filters = ['name', 'email', 'status'];
        $sorts = ['name', 'email', 'created_at'];
        $includes = ['roles'];
        
        $users = $this->userService->getAllUsers($filters, $sorts, $includes);

        return $this->successResponse($users, 'Users retrieved successfully.');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/users",
     *     operationId="createUser",
     *     tags={"Users"},
     *     summary="Create a new user",
     *     description="Create a new user in the current tenant",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="User data",
     *         @OA\JsonContent(
     *             required={"name", "email", "password"},
     *             @OA\Property(property="name", type="string", description="User full name", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", description="User email address", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", description="User password (min 8 chars)", example="password123"),
     *             @OA\Property(property="status", type="string", enum={"active", "inactive", "suspended"}, description="User status", example="active")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User created successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/User")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        try {
            $user = $this->userService->createUser($request->validated());
            return $this->successResponse($user, 'User created successfully.', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/users/{id}",
     *     operationId="getUserById",
     *     tags={"Users"},
     *     summary="Get user by ID",
     *     description="Retrieve a specific user by their ID including roles",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User retrieved successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/User")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = $this->userService->findWithRoles($id);
            
            if (!$user) {
                return $this->errorResponse('User not found.', 404);
            }
            
            return $this->successResponse($user, 'User retrieved successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/v1/users/{id}",
     *     operationId="updateUser",
     *     tags={"Users"},
     *     summary="Update user",
     *     description="Update an existing user's information",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="User data to update",
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", description="User full name", example="Jane Doe"),
     *             @OA\Property(property="email", type="string", format="email", description="User email address", example="jane@example.com"),
     *             @OA\Property(property="password", type="string", format="password", description="New password (min 8 chars)", example="newpassword123"),
     *             @OA\Property(property="status", type="string", enum={"active", "inactive", "suspended"}, description="User status", example="active"),
     *             @OA\Property(property="email_verified_at", type="string", format="date-time", description="Email verification timestamp")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/User")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        try {
            $user = $this->userService->updateUser($id, $request->validated());
            return $this->successResponse($user, 'User updated successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/users/{id}",
     *     operationId="deleteUser",
     *     tags={"Users"},
     *     summary="Delete user",
     *     description="Soft delete a user (can be restored later)",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User deleted successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", type="null")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unable to delete user",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->userService->deleteUser($id);
            
            if ($result) {
                return $this->successResponse(null, 'User deleted successfully.');
            }
            
            return $this->errorResponse('Failed to delete user.', 500);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Restore a soft-deleted user.
     */
    public function restore(int $id): JsonResponse
    {
        try {
            $result = $this->userService->restoreUser($id);
            
            if ($result) {
                $user = $this->userService->findById($id);
                return $this->successResponse($user, 'User restored successfully.');
            }
            
            return $this->errorResponse('Failed to restore user.', 500);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Search users by name or email.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'term' => ['required', 'string', 'min:2', 'max:255']
        ]);

        try {
            $users = $this->userService->searchUsers($request->input('term'));
            return $this->successResponse($users, 'Search completed successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Get users by status.
     */
    public function getByStatus(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:active,inactive,suspended']
        ]);

        try {
            $users = $this->userService->getUsersByStatus($request->input('status'));
            return $this->successResponse($users, 'Users retrieved by status successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Get trashed users.
     */
    public function trashed(): JsonResponse
    {
        try {
            $users = $this->userService->getTrashedUsers();
            return $this->successResponse($users, 'Trashed users retrieved successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Update user status.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:active,inactive,suspended']
        ]);

        try {
            $user = $this->userService->updateStatus($id, $request->input('status'));
            return $this->successResponse($user, 'User status updated successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Assign role to user.
     */
    public function assignRole(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'role' => ['required', 'string', 'exists:roles,name']
        ]);

        try {
            $user = $this->userService->assignRole($id, $request->input('role'));
            return $this->successResponse($user, 'Role assigned successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Remove role from user.
     */
    public function removeRole(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'role' => ['required', 'string']
        ]);

        try {
            $user = $this->userService->removeRole($id, $request->input('role'));
            return $this->successResponse($user, 'Role removed successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/users/bulk",
     *     operationId="bulkUserOperation",
     *     tags={"Users"},
     *     summary="Bulk user operations",
     *     description="Perform bulk operations on multiple users (delete, activate, deactivate, suspend)",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Bulk operation data",
     *         @OA\JsonContent(
     *             required={"user_ids", "action"},
     *             @OA\Property(
     *                 property="user_ids",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 description="Array of user IDs",
     *                 example={1, 2, 3}
     *             ),
     *             @OA\Property(
     *                 property="action",
     *                 type="string",
     *                 enum={"delete", "activate", "deactivate", "suspend"},
     *                 description="Bulk action to perform",
     *                 example="activate"
     *             ),
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 enum={"active", "inactive", "suspended"},
     *                 description="New status for status change operations",
     *                 example="active"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bulk operation completed",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         type="object",
     *                         description="Results of bulk operation",
     *                         example={"1": true, "2": true, "3": false}
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function bulkOperation(BulkUserOperationRequest $request): JsonResponse
    {
        try {
            $userIds = $request->validated()['user_ids'];
            $action = $request->validated()['action'];
            
            $results = [];
            
            switch ($action) {
                case 'delete':
                    $results = $this->userService->bulkDelete($userIds);
                    break;
                    
                case 'activate':
                case 'deactivate':
                case 'suspend':
                    $status = $action === 'activate' ? 'active' : 
                             ($action === 'deactivate' ? 'inactive' : 'suspended');
                    $results = $this->userService->bulkUpdateStatus($userIds, $status);
                    break;
                    
                default:
                    return $this->errorResponse('Invalid bulk action.', 422);
            }
            
            return $this->successResponse($results, "Bulk {$action} operation completed.");
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}
