<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="Orthoplex Multi-Tenant SaaS API",
 *     version="1.0.0",
 *     description="Comprehensive multi-tenant SaaS backend with hybrid authentication, RBAC, and analytics. Built with Laravel, Stancl Tenancy, and JWT authentication.",
 *     @OA\Contact(
 *         email="support@orthoplex.com",
 *         name="Orthoplex Support Team"
 *     ),
 *     @OA\License(
 *         name="MIT License",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="JWT Authorization header using the Bearer scheme. Example: 'Bearer {token}'"
 * )
 * 
 * @OA\Tag(
 *     name="Authentication",
 *     description="User authentication endpoints including login, registration, 2FA, and magic links"
 * )
 * 
 * @OA\Tag(
 *     name="Users",
 *     description="User management operations including CRUD, role management, and bulk operations"
 * )
 * 
 * @OA\Tag(
 *     name="Roles & Permissions",
 *     description="Role and permission management for tenant-specific RBAC"
 * )
 * 
 * @OA\Tag(
 *     name="Tenants",
 *     description="Multi-tenant management operations"
 * )
 * 
 * @OA\Tag(
 *     name="Analytics",
 *     description="Login analytics and reporting endpoints"
 * )
 * 
 * @OA\Tag(
 *     name="GDPR",
 *     description="GDPR compliance endpoints for data export and deletion requests"
 * )
 * 
 * @OA\Tag(
 *     name="Webhooks",
 *     description="Webhook management and event handling"
 * )
 */
class SwaggerController extends Controller
{
    // This controller exists purely for OpenAPI documentation
}
