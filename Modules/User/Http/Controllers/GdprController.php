<?php

namespace Modules\User\Http\Controllers;

use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\User\app\Jobs\GdprDeleteJob;
use Modules\User\app\Jobs\GdprExportJob;
use Modules\User\Models\GdprDeleteRequest;

class GdprController extends Controller
{
    use ApiResponse;

    public function requestExport(Request $request): JsonResponse
    {
        GdprExportJob::dispatch($request->user());

        return $this->successResponse(null, 'Your data export has been requested. You will receive an email shortly.');
    }

    public function requestDeletion(Request $request): JsonResponse
    {
        $existingRequest = GdprDeleteRequest::where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->first();

        if ($existingRequest) {
            return $this->errorResponse('You already have a pending delete request.', 409);
        }

        GdprDeleteRequest::create([
            'user_id' => $request->user()->id,
        ]);

        return $this->successResponse(null, 'Your delete request has been submitted for approval.');
    }

    public function getDeleteRequests(): JsonResponse
    {
        $requests = GdprDeleteRequest::with('user')->where('status', 'pending')->get();

        return $this->successResponse($requests, 'Pending delete requests retrieved.');
    }

    public function approveDeleteRequest(GdprDeleteRequest $request): JsonResponse
    {
        if ($request->status !== 'pending') {
            return $this->errorResponse('This request has already been processed.', 409);
        }

        $request->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
        ]);

        GdprDeleteJob::dispatch($request->user);

        return $this->successResponse(null, 'Delete request approved. The user account will be deleted shortly.');
    }

    public function denyDeleteRequest(GdprDeleteRequest $request): JsonResponse
    {
        if ($request->status !== 'pending') {
            return $this->errorResponse('This request has already been processed.', 409);
        }

        $request->update([
            'status' => 'denied',
            'approved_by' => auth()->id(),
        ]);

        return $this->successResponse(null, 'Delete request denied.');
    }
}
