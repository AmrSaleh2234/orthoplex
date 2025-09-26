<?php

namespace Modules\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\User\Models\CentralUser;
use Modules\User\Models\User;
use Modules\Tenant\app\Models\Tenant;
use Modules\RolesAndPermissions\Services\HybridRbacService;
use Modules\Tenant\Services\UserAttachmentService;
use Stancl\Tenancy\Facades\Tenancy;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Http\Traits\ApiResponse;

class HybridAuthMiddleware
{
    use ApiResponse;

    protected HybridRbacService $rbacService;
    protected UserAttachmentService $attachmentService;

    public function __construct(
        HybridRbacService $rbacService,
        UserAttachmentService $attachmentService
    ) {
        $this->rbacService = $rbacService;
        $this->attachmentService = $attachmentService;
    }

    /**
     * Handle an incoming request with hybrid authentication flow:
     * 1. Authentication (JWT validation against central DB)
     * 2. Tenant Resolution (determine and initialize tenant)
     * 3. Authorization (check permissions in tenant context)
     */
    public function handle(Request $request, Closure $next, ...$permissions)
    {
        // Step 1: Authentication - Validate JWT and get central user
        $centralUser = $this->authenticateUser($request);
        if (!$centralUser) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        // Step 2: Tenant Resolution - Determine tenant from request
        $tenant = $this->resolveTenant($request);
        if (!$tenant) {
            return $this->errorResponse('Tenant not found or invalid.', 404);
        }

        // Step 3: User-Tenant Association Check
        if (!$this->attachmentService->userCanAccessTenant($centralUser, $tenant)) {
            return $this->errorResponse('User does not have access to this tenant.', 403);
        }

        // Step 4: Initialize Tenant Context
        Tenancy::initialize($tenant);

        // Step 5: Get Tenant User for Authorization
        $tenantUser = User::where('global_id', $centralUser->global_id)->first();
        if (!$tenantUser) {
            Tenancy::end();
            return $this->errorResponse('User not synchronized in tenant database.', 403);
        }

        // Step 6: Authorization - Check permissions if specified
        if (!empty($permissions)) {
            $hasPermission = $this->checkPermissions($tenantUser, $permissions);
            if (!$hasPermission) {
                Tenancy::end();
                return $this->errorResponse('Insufficient permissions.', 403);
            }
        }

        // Step 7: Set authenticated user in tenant context
        auth()->setUser($tenantUser);

        // Step 8: Add user and tenant info to request for controllers
        $request->merge([
            'central_user' => $centralUser,
            'tenant_user' => $tenantUser,
            'current_tenant' => $tenant,
        ]);

        try {
            // Continue to next middleware/controller
            $response = $next($request);

            // Ensure tenancy is ended after request processing
            if (Tenancy::initialized()) {
                Tenancy::end();
            }

            return $response;
        } catch (\Exception $e) {
            // Ensure tenancy is ended on exception
            if (Tenancy::initialized()) {
                Tenancy::end();
            }
            throw $e;
        }
    }

    /**
     * Authenticate user via JWT and return central user
     */
    protected function authenticateUser(Request $request): ?CentralUser
    {
        try {
            // Get JWT token from request
            if (!$token = $request->bearerToken()) {
                return null;
            }

            // Set the token for JWT Auth
            JWTAuth::setToken($token);

            // Validate and decode the token to get central user
            $payload = JWTAuth::getPayload();
            $userId = $payload->get('sub');

            // Get central user
            $centralUser = CentralUser::find($userId);

            if (!$centralUser || !$centralUser->isActive()) {
                return null;
            }

            return $centralUser;

        } catch (JWTException $e) {
            return null;
        }
    }

    /**
     * Resolve tenant from request
     */
    protected function resolveTenant(Request $request): ?Tenant
    {
        // Try different methods to resolve tenant
        $tenantId = $this->getTenantFromRequest($request);

        if (!$tenantId) {
            return null;
        }

        return Tenant::find($tenantId);
    }

    /**
     * Get tenant ID from various request sources
     */
    protected function getTenantFromRequest(Request $request): ?string
    {
        // 1. Check route parameter
        if ($request->route('tenant')) {
            return $request->route('tenant');
        }

        // 2. Check query parameter
        if ($request->query('tenant')) {
            return $request->query('tenant');
        }

        // 3. Check header
        if ($request->header('X-Tenant-ID')) {
            return $request->header('X-Tenant-ID');
        }

        // 4. Check subdomain (if using subdomain-based tenancy)
        $host = $request->getHost();
        if (preg_match('/^([a-zA-Z0-9\-]+)\./', $host, $matches)) {
            $subdomain = $matches[1];
            // Skip common subdomains
            if (!in_array($subdomain, ['www', 'api', 'admin'])) {
                $tenant = Tenant::where('id', $subdomain)->first();
                return $tenant?->id;
            }
        }

        return null;
    }

    /**
     * Check if tenant user has required permissions
     */
    protected function checkPermissions(User $tenantUser, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (str_contains($permission, '|')) {
                // OR logic: user needs any one of these permissions
                $orPermissions = explode('|', $permission);
                if ($this->rbacService->userHasAnyPermission($tenantUser, $orPermissions)) {
                    continue;
                }
                return false;
            } else {
                // AND logic: user needs this specific permission
                if (!$this->rbacService->userHasPermission($tenantUser, $permission)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Middleware alias for owner-only access
     */
    public static function owner(): string
    {
        return static::class . ':System.system.manage_all';
    }

    /**
     * Middleware alias for admin access (owner or admin)
     */
    public static function admin(): string
    {
        return static::class . ':Users.users.update|RolesAndPermissions.roles.assign';
    }

    /**
     * Middleware alias for user management
     */
    public static function userManager(): string
    {
        return static::class . ':Users.users.create,Users.users.update,Users.users.delete';
    }

    /**
     * Middleware alias for analytics access
     */
    public static function analyticsAccess(): string
    {
        return static::class . ':Analytics.login_analytics.read|Analytics.user_analytics.read';
    }

    /**
     * Middleware alias for webhook management
     */
    public static function webhookManager(): string
    {
        return static::class . ':Webhooks.webhooks.create,Webhooks.webhooks.update,Webhooks.webhooks.delete';
    }
}
