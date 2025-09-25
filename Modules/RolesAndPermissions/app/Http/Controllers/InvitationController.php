<?php

namespace Modules\RolesAndPermissions\app\Http\Controllers;

use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\RolesAndPermissions\app\Http\Requests\AcceptInvitationRequest;
use Modules\RolesAndPermissions\app\Http\Requests\InviteUserRequest;
use Modules\RolesAndPermissions\app\Models\Role;
use Modules\RolesAndPermissions\app\Services\InvitationService;

class InvitationController extends Controller
{
    use ApiResponse;

    protected $invitationService;

    public function __construct(InvitationService $invitationService)
    {
        $this->invitationService = $invitationService;
    }

    public function invite(InviteUserRequest $request): JsonResponse
    {
        $role = Role::findById($request->role_id);

        $this->invitationService->createAndSendInvitation($request->email, $role);

        return $this->successResponse(null, 'Invitation sent successfully.');
    }

    public function accept(AcceptInvitationRequest $request): JsonResponse
    {
        $user = $this->invitationService->acceptInvitation($request->token, $request->safe()->only(['name', 'password']));

        if (! $user) {
            return $this->errorResponse('Invalid or expired invitation token.', 422);
        }

        return $this->successResponse($user, 'Account created successfully. You can now log in.');
    }
}
