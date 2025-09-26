<?php

namespace Modules\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\User\Models\CentralUser;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Http\Traits\ApiResponse;

class JwtAuthMiddleware
{
    use ApiResponse;

    /**
     * Handle an incoming request with JWT authentication for central database
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            // Get JWT token from request
            if (!$token = $request->bearerToken()) {
                return $this->errorResponse('Token not provided.', 401);
            }

            // Set the token for JWT Auth
            JWTAuth::setToken($token);

            // Validate and decode the token to get central user
            $payload = JWTAuth::getPayload();
            $userId = $payload->get('sub');

            // Get central user
            $centralUser = CentralUser::find($userId);

            if (!$centralUser) {
                return $this->errorResponse('User not found.', 401);
            }

            if (!$centralUser->isActive()) {
                return $this->errorResponse('Account not active.', 401);
            }

            // Set authenticated user for the request
            auth()->setUser($centralUser);

            // Add central user to request for controllers
            $request->merge(['central_user' => $centralUser]);

            return $next($request);

        } catch (JWTException $e) {
            return $this->errorResponse('Token is invalid.', 401);
        } catch (\Exception $e) {
            return $this->errorResponse('Authentication failed.', 401);
        }
    }
}
