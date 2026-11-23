<?php

namespace App\Http\Controllers;

use App\Services\AdminDashboardMetrics;
use App\Services\PlatformAdminAudit;
use App\Services\PrivacyPublication;
use App\Services\ReleaseConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

final class AdminDashboardController extends Controller
{
    public function workspace(Request $request): Response
    {
        $view = $request->user()->isVerifiedPlatformAdmin() ? 'platform-admin-workspace' : 'platform-support-workspace';

        return response()->view($view)->header('Cache-Control', 'private, no-store');
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

    public function audit(Request $request, PlatformAdminAudit $audit): JsonResponse
    {
        $filters = $request->validate([
            'source' => ['sometimes', 'required', Rule::in(PlatformAdminAudit::SOURCES)],
            'page' => ['sometimes', 'required', 'integer', 'min:1'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $audit->recent($filters['source'] ?? null),
        ])->header('Cache-Control', 'private, no-store');
    }
}
