<?php

namespace App\Http\Controllers;

use App\Services\AdminDashboardMetrics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class AdminDashboardController extends Controller
{
    public function workspace(): Response
    {
        return response()->view('platform-admin-workspace')->header('Cache-Control', 'private, no-store');
    }

    public function show(AdminDashboardMetrics $metrics): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $metrics->overview()])->header('Cache-Control', 'private, no-store');
    }
}
