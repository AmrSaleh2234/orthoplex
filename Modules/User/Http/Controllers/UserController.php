<?php

namespace Modules\User\Http\Controllers;

use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\User\Models\User;
use Modules\Webhooks\app\Events\UserDeleted;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $users = QueryBuilder::for(User::class)
            ->allowedFilters(['name', 'email'])
            ->allowedSorts(['name', 'email'])
            ->allowedIncludes(['roles'])
            ->cursorPaginate();

        return $this->successResponse($users, 'Users retrieved successfully.');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('user::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('user::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('user::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id) {}

    /**
     * Soft-delete a user.
     */
    public function destroy(User $user): JsonResponse
    {
        $userId = $user->id;
        $user->delete();

        event(new UserDeleted($userId));

        return $this->successResponse(null, 'User deleted successfully.');
    }

    /**
     * Restore a soft-deleted user.
     */
    public function restore($id): JsonResponse
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();

        return $this->successResponse($user, 'User restored successfully.');
    }
}
