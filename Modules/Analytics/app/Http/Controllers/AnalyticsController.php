<?php

namespace Modules\Analytics\app\Http\Controllers;

use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Analytics\app\Models\LoginDaily;

class AnalyticsController extends Controller
{
    use ApiResponse;

    public function getDailyLogins(): JsonResponse
    {
        $dailyLogins = LoginDaily::orderBy('date', 'desc')->get();

        return $this->successResponse($dailyLogins, 'Daily login analytics retrieved successfully.');
    }
}
