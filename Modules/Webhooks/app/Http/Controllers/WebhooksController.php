<?php

namespace Modules\Webhooks\app\Http\Controllers;

use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Webhooks\app\Http\Requests\ProvisionTenantRequest;
use Modules\Webhooks\app\Jobs\ProvisionTenantJob;

class WebhooksController extends Controller
{
    use ApiResponse;

    public function provisionTenant(ProvisionTenantRequest $request): JsonResponse
    {
        ProvisionTenantJob::dispatch($request->validated());

        return $this->successResponse(null, 'Tenant provisioning has been initiated.', 202);
    }
}
