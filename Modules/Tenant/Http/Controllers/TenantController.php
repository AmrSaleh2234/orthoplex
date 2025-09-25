<?php

namespace Modules\Tenant\Http\Controllers;

use App\Http\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Tenant\App\Exceptions\ConcurrencyException;
use Modules\Tenant\Http\Requests\CreateTenantRequest;
use Modules\Tenant\Http\Requests\UpdateTenantRequest;
use Modules\Tenant\Services\TenantService;

class TenantController extends Controller
{
    use ApiResponse;

    protected $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    public function store(CreateTenantRequest $request): JsonResponse
    {
        $tenant = $this->tenantService->create($request->validated());

        return $this->successResponse($tenant, 'Tenant created successfully.', 201);
    }

    public function show(string $id): JsonResponse
    {
        $tenant = $this->tenantService->find($id);

        if (!$tenant) {
            return $this->errorResponse('Tenant not found.', 404);
        }

        return $this->successResponse($tenant, 'Tenant retrieved successfully.');
    }

    public function update(UpdateTenantRequest $request, string $id): JsonResponse
    {
        try {
            $tenant = $this->tenantService->update($id, $request->validated());
            return $this->successResponse($tenant, 'Tenant updated successfully.');
        } catch (ConcurrencyException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Tenant not found.', 404);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->tenantService->delete($id);
            return $this->successResponse(null, 'Tenant deleted successfully.', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Tenant not found.', 404);
        }
    }
}
