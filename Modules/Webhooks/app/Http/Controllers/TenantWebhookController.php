<?php

namespace Modules\Webhooks\app\Http\Controllers;

use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\Webhooks\app\Http\Requests\CreateWebhookRequest;
use Modules\Webhooks\app\Models\Webhook;

class TenantWebhookController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $webhooks = Webhook::all();
        return $this->successResponse($webhooks, 'Webhooks retrieved successfully.');
    }

    public function store(CreateWebhookRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['secret'] = Str::random(32);

        $webhook = Webhook::create($data);

        return $this->successResponse($webhook, 'Webhook created successfully.', 201);
    }

    public function destroy(Webhook $webhook): JsonResponse
    {
        $webhook->delete();
        return $this->successResponse(null, 'Webhook deleted successfully.');
    }
}
