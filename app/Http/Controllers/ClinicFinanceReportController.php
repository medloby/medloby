<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Business;
use App\Services\ClinicFinanceReports;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClinicFinanceReportController extends Controller
{
    public function __invoke(Request $request, Business $business, ClinicFinanceReports $reports): JsonResponse
    {
        abort_unless($business->allowsCrmAccess(), 403, 'Finans raporları için klinik onayı gereklidir.');
        $user = $request->user();
        $filters = $request->validate([
            'branch_id' => ['sometimes', 'required', 'integer', 'min:1'],
            'period' => ['sometimes', 'required', 'in:month,year,all'],
            'month' => ['sometimes', 'required', 'date_format:Y-m', 'regex:/\A(?!0000)\d{4}-\d{2}\z/'],
            'year' => ['sometimes', 'required', 'regex:/\A(?!0000)\d{4}\z/'],
        ]);
        $branchId = isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;
        $branches = $business->branches()->where('status', 'active')->whereIn('id', $user->accessibleBranchIds($business->id))
            ->when($branchId !== null, fn (Builder $builder) => $builder->whereKey($branchId))
            ->orderBy('name')->orderBy('id')->get(['id', 'business_id', 'name', 'status'])
            ->filter(fn (Branch $branch): bool => $user->branchFinancePermissions($branch->setRelation('business', $business)) !== [])
            ->values();
        abort_if($branches->isEmpty(), 403, 'Bu kapsamdaki finans raporlarına erişim yetkiniz yok.');

        $today = CarbonImmutable::today('Europe/Istanbul');
        $period = $filters['period'] ?? 'month';
        $start = match ($period) {
            'year' => CarbonImmutable::createFromFormat('!Y', (string) ($filters['year'] ?? $today->format('Y'))),
            'all' => null,
            default => CarbonImmutable::createFromFormat('!Y-m', $filters['month'] ?? $today->format('Y-m')),
        };
        $end = $period === 'year' ? $start->endOfYear() : $start?->endOfMonth();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'starts_on' => $start?->toDateString(),
                'ends_on' => $end?->toDateString(),
                'today' => $today->toDateString(),
                ...$reports->build($business->id, $branches, $start, $end, $period !== 'month'),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }
}
