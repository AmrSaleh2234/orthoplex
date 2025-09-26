<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthController;
use Modules\Webhooks\app\Http\Controllers\WebhooksController;
use Modules\Auth\Http\Middleware\HybridAuthMiddleware;

/*
|--------------------------------------------------------------------------
| Authentication Routes (Central Database)
|--------------------------------------------------------------------------
*/

// Public authentication routes
Route::prefix('auth')->group(function () {
    // Registration and login
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    
    // Email verification
    Route::get('/verify-email', [AuthController::class, 'verifyEmail'])->name('verification.verify');
    Route::post('/resend-verification', [AuthController::class, 'resendVerification']);
    
    // Two-Factor Authentication
    Route::post('/verify-2fa', [AuthController::class, 'verifyTwoFactor']);
    
    // Magic Link (Passwordless) Authentication
    Route::post('/request-magic-link', [AuthController::class, 'requestMagicLink']);
    Route::post('/magic-link-login', [AuthController::class, 'magicLinkLogin']);
    
    // Token management
    Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
    
    // Protected routes (require JWT authentication)
    Route::middleware('jwt.auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

/*
|--------------------------------------------------------------------------
| Tenant-Specific Routes (Require Tenant Context)
|--------------------------------------------------------------------------
*/

Route::prefix('tenant/{tenant}')->middleware([HybridAuthMiddleware::class])->group(function () {
    
    // User Management Routes
    Route::prefix('users')->group(function () {
        // Basic user operations
        Route::get('/', function () {
            // List users with appropriate permissions
            return response()->json(['message' => 'List users endpoint']);
        })->middleware(HybridAuthMiddleware::class . ':Users.users.read');
        
        Route::post('/', function () {
            // Create new user
            return response()->json(['message' => 'Create user endpoint']);
        })->middleware(HybridAuthMiddleware::class . ':Users.users.create');
        
        Route::get('/{user}', function ($tenant, $user) {
            // Get specific user
            return response()->json(['message' => 'Get user details endpoint']);
        })->middleware(HybridAuthMiddleware::class . ':Users.users.read');
        
        Route::put('/{user}', function ($tenant, $user) {
            // Update user
            return response()->json(['message' => 'Update user endpoint']);
        })->middleware(HybridAuthMiddleware::class . ':Users.users.update');
        
        Route::delete('/{user}', function ($tenant, $user) {
            // Delete user
            return response()->json(['message' => 'Delete user endpoint']);
        })->middleware(HybridAuthMiddleware::class . ':Users.users.delete');
        
        // User invitations
        Route::post('/invite', function () {
            // Invite user to tenant
            return response()->json(['message' => 'Invite user endpoint']);
        })->middleware(HybridAuthMiddleware::class . ':Users.users.invite');
        
        // User role management
        Route::post('/{user}/roles', function ($tenant, $user) {
            // Assign role to user
            return response()->json(['message' => 'Assign role endpoint']);
        })->middleware(HybridAuthMiddleware::class . ':RolesAndPermissions.roles.assign');
        
        Route::delete('/{user}/roles/{role}', function ($tenant, $user, $role) {
            // Remove role from user
            return response()->json(['message' => 'Remove role endpoint']);
        })->middleware(HybridAuthMiddleware::class . ':RolesAndPermissions.roles.assign');
    });
    
    // Role and Permission Management
    Route::prefix('roles')->middleware(HybridAuthMiddleware::class . ':RolesAndPermissions.roles.read')->group(function () {
        Route::get('/', function () {
            // List roles
            return response()->json(['message' => 'List roles endpoint']);
        });
        
        Route::post('/', function () {
            // Create role
            return response()->json(['message' => 'Create role endpoint']);
        })->middleware(HybridAuthMiddleware::class . ':RolesAndPermissions.roles.create');
        
        Route::get('/{role}', function ($tenant, $role) {
            // Get role details
            return response()->json(['message' => 'Get role endpoint']);
        });
        
        Route::put('/{role}', function ($tenant, $role) {
            // Update role
            return response()->json(['message' => 'Update role endpoint']);
        })->middleware(HybridAuthMiddleware::class . ':RolesAndPermissions.roles.update');
        
        Route::delete('/{role}', function ($tenant, $role) {
            // Delete role
            return response()->json(['message' => 'Delete role endpoint']);
        })->middleware(HybridAuthMiddleware::class . ':RolesAndPermissions.roles.delete');
    });
    
    // Analytics Routes
    Route::prefix('analytics')->middleware(HybridAuthMiddleware::analyticsAccess())->group(function () {
        Route::get('/login-events', function () {
            // Get login analytics
            return response()->json(['message' => 'Login analytics endpoint']);
        });
        
        Route::get('/user-stats', function () {
            // Get user statistics
            return response()->json(['message' => 'User statistics endpoint']);
        });
        
        Route::get('/dashboard', function () {
            // Get dashboard analytics
            return response()->json(['message' => 'Dashboard analytics endpoint']);
        });
        
        Route::post('/export', function () {
            // Export analytics report
            return response()->json(['message' => 'Export analytics endpoint']);
        })->middleware(HybridAuthMiddleware::class . ':Analytics.reports.export');
    });
    
    // Webhook Management
    Route::prefix('webhooks')->middleware(HybridAuthMiddleware::webhookManager())->group(function () {
        Route::get('/', function () {
            // List webhooks
            return response()->json(['message' => 'List webhooks endpoint']);
        })->middleware(HybridAuthMiddleware::class . ':Webhooks.webhooks.read');
        
        Route::post('/', function () {
            // Create webhook
            return response()->json(['message' => 'Create webhook endpoint']);
        })->middleware(HybridAuthMiddleware::class . ':Webhooks.webhooks.create');
        
        Route::get('/{webhook}', function ($tenant, $webhook) {
            // Get webhook details
            return response()->json(['message' => 'Get webhook endpoint']);
        })->middleware(HybridAuthMiddleware::class . ':Webhooks.webhooks.read');
        
        Route::put('/{webhook}', function ($tenant, $webhook) {
            // Update webhook
            return response()->json(['message' => 'Update webhook endpoint']);
        })->middleware(HybridAuthMiddleware::class . ':Webhooks.webhooks.update');
        
        Route::delete('/{webhook}', function ($tenant, $webhook) {
            // Delete webhook
            return response()->json(['message' => 'Delete webhook endpoint']);
        })->middleware(HybridAuthMiddleware::class . ':Webhooks.webhooks.delete');
        
        Route::post('/{webhook}/test', function ($tenant, $webhook) {
            // Test webhook
            return response()->json(['message' => 'Test webhook endpoint']);
        })->middleware(HybridAuthMiddleware::class . ':Webhooks.webhooks.test');
    });
    
    // Tenant Settings (Owner/Admin only)
    Route::prefix('settings')->middleware(HybridAuthMiddleware::admin())->group(function () {
        Route::get('/', function () {
            // Get tenant settings
            return response()->json(['message' => 'Get tenant settings endpoint']);
        });
        
        Route::put('/', function () {
            // Update tenant settings
            return response()->json(['message' => 'Update tenant settings endpoint']);
        })->middleware(HybridAuthMiddleware::class . ':Tenant.tenants.update');
    });
    
    // GDPR Compliance Routes
    Route::prefix('gdpr')->group(function () {
        Route::post('/export-data', function () {
            // Export user data
            return response()->json(['message' => 'Export GDPR data endpoint']);
        })->middleware(HybridAuthMiddleware::class . ':Users.gdpr.export_data');
        
        Route::post('/request-deletion', function () {
            // Request data deletion
            return response()->json(['message' => 'Request GDPR deletion endpoint']);
        })->middleware(HybridAuthMiddleware::class . ':Users.gdpr.request_deletion');
        
        Route::get('/deletion-requests', function () {
            // List deletion requests (admin only)
            return response()->json(['message' => 'List GDPR deletion requests endpoint']);
        })->middleware(HybridAuthMiddleware::class . ':Users.gdpr.manage_requests');
        
        Route::post('/deletion-requests/{request}/approve', function ($tenant, $request) {
            // Approve deletion request (admin only)
            return response()->json(['message' => 'Approve GDPR deletion endpoint']);
        })->middleware(HybridAuthMiddleware::class . ':Users.gdpr.approve_deletion');
    });
});

/*
|--------------------------------------------------------------------------
| Legacy Routes
|--------------------------------------------------------------------------
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/webhooks/provision-tenant', [WebhooksController::class, 'provisionTenant'])
    ->name('webhooks.provision-tenant');
