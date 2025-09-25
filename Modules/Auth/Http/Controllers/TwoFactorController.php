<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Auth\Services\TwoFactorService;

class TwoFactorController extends Controller
{
    use ApiResponse;

    protected $twoFactorService;

    public function __construct(TwoFactorService $twoFactorService)
    {
        $this->twoFactorService = $twoFactorService;
    }

    public function generateSecret(Request $request): JsonResponse
    {
        $result = $this->twoFactorService->generateSecret($request->user());

        return $this->successResponse($result, '2FA secret generated successfully.');
    }

    public function enableTwoFactor(Request $request): JsonResponse
    {
        $request->validate(['otp' => 'required|string']);

        if ($this->twoFactorService->enable($request->user(), $request->otp)) {
            return $this->successResponse(null, '2FA enabled successfully.');
        }

        return $this->errorResponse('Invalid 2FA code.', 401);
    }

    public function disableTwoFactor(Request $request): JsonResponse
    {
        $this->twoFactorService->disable($request->user());

        return $this->successResponse(null, '2FA disabled successfully.');
    }
}
