<?php

namespace App\Http\Controllers;

use App\Services\AdminDashboardMetrics;
use App\Services\PrivacyPublication;
use App\Services\ReleaseConfiguration;
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

    public function analytics(AdminDashboardMetrics $metrics): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $metrics->analytics()])->header('Cache-Control', 'private, no-store');
    }

    public function operations(ReleaseConfiguration $release, PrivacyPublication $privacy): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $release->operationSummary(app('migrator'), $privacy),
        ])->header('Cache-Control', 'private, no-store');
    }
}
