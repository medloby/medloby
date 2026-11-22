<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchLedgerEntry;
use App\Rules\PriceAmount;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClinicLedgerController extends Controller
{
    private const array ENTRY_RULES = [
        'kind' => ['required', 'in:income,expense'],
        'entry_date' => ['required', 'date_format:Y-m-d', 'regex:/\A(?!0000)\d{4}-\d{2}-\d{2}\z/'],
        'amount' => ['required', ...PriceAmount::RULES, 'decimal:0,2', 'min:0.01'],
        'currency' => ['required', 'string', 'alpha:ascii', 'size:3', 'uppercase'],
        'description' => ['required', 'string', 'max:255'],
    ];

    private const array REVISION_RULES = ['revision' => ['required', 'integer', 'min:1']];

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $permissions = $request->user()->branchFinancePermissions($branch);
        abort_if($permissions === [], 403, 'Bu şubenin gelir ve gider kayıtlarına erişim yetkiniz yok.');

        $filters = $request->validate([
            'month' => ['sometimes', 'required', 'date_format:Y-m', 'regex:/\A(?!0000)\d{4}-\d{2}\z/'],
            'status' => ['sometimes', 'required', 'in:active,voided'],
        ]);
        $today = CarbonImmutable::today('Europe/Istanbul');
        $month = CarbonImmutable::createFromFormat('!Y-m', $filters['month'] ?? $today->format('Y-m'));
        $entries = $branch->ledgerEntries()->whereBetween('entry_date', [$month->toDateString(), $month->endOfMonth()->toDateString()]);
        $totals = (clone $entries)->whereNull('voided_at')->groupBy('currency')->orderBy('currency')
            ->selectRaw("currency, SUM(CASE WHEN kind = 'income' THEN amount_minor ELSE 0 END) AS income,
                SUM(CASE WHEN kind = 'expense' THEN amount_minor ELSE 0 END) AS expense")
            ->get()->map(fn (BranchLedgerEntry $summary): array => [
                'currency' => $summary->currency,
                'income' => $this->amount((int) $summary->income),
                'expense' => $this->amount((int) $summary->expense),
                'net' => $this->amount((int) $summary->income - (int) $summary->expense),
            ]);

        $page = ($filters['status'] ?? 'active') === 'voided' ? $entries->whereNotNull('voided_at') : $entries->whereNull('voided_at');
        $page = $page->latest('entry_date')->latest('id')->paginate(20)->through($this->entry(...));

        return $this->respond([
            'month' => $month->format('Y-m'),
            'today' => $today->toDateString(),
            'can_manage' => in_array('finance.manage', $permissions, true),
            'totals' => $totals->all(),
            'entries' => $page->items(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function store(Request $request, Branch $branch): JsonResponse
    {
        $this->authorizeWrite($request, $branch);
        $fields = $request->validate(self::ENTRY_RULES + ['request_key' => ['required', 'string', 'regex:/\A[a-f0-9]{32}\z/']]);
        $values = $this->entryValues($fields);

        return DB::transaction(function () use ($request, $branch, $fields, $values): JsonResponse {
            $branch = $this->lockBranch($request, $branch);
            $existing = $branch->ledgerEntries()->where('request_key', $fields['request_key'])->first();
            if ($existing) {
                abort_if($existing->fill($values)->isDirty(), 409, 'Bu giriş daha önce kaydedildi. Kayıt listesini yenileyin.');

                return $this->respond($this->entry($existing));
            }

            $saved = $branch->ledgerEntries()->create($values + [
                'request_key' => $fields['request_key'],
                'created_by_user_id' => $request->user()->id,
                'updated_by_user_id' => $request->user()->id,
            ]);

            return $this->respond($this->entry($saved), 201);
        }, attempts: 3);
    }

    public function update(Request $request, Branch $branch, string $entry): JsonResponse
    {
        $this->authorizeWrite($request, $branch);
        $fields = $request->validate(self::ENTRY_RULES + self::REVISION_RULES);

        return DB::transaction(function () use ($request, $branch, $entry, $fields): JsonResponse {
            $saved = $this->findEntry($this->lockBranch($request, $branch), $entry);
            abort_if($saved->voided_at !== null, 409, 'İptal edilen kaydı düzenlemek için yeniden etkinleştirin.');
            if ($saved->fill($this->entryValues($fields))->isDirty()) {
                $this->saveChange($request, $saved, (int) $fields['revision']);
            }

            return $this->respond($this->entry($saved));
        }, attempts: 3);
    }

    public function updateStatus(Request $request, Branch $branch, string $entry): JsonResponse
    {
        $this->authorizeWrite($request, $branch);
        $fields = $request->validate(self::REVISION_RULES + ['status' => ['required', 'in:active,voided']]);

        return DB::transaction(function () use ($request, $branch, $entry, $fields): JsonResponse {
            $saved = $this->findEntry($this->lockBranch($request, $branch), $entry);
            $voiding = $fields['status'] === 'voided';
            if ($voiding !== ($saved->voided_at !== null)) {
                $saved->voided_at = $voiding ? now() : null;
                $this->saveChange($request, $saved, (int) $fields['revision']);
            }

            return $this->respond($this->entry($saved));
        }, attempts: 3);
    }

    private function lockBranch(Request $request, Branch $branch): Branch
    {
        $branch = Branch::query()->lockForUpdate()->findOrFail($branch->id);
        abort_unless($branch->status === 'active' && $branch->business?->allowsCrmAccess(), 403);
        $this->authorizeWrite($request, $branch);

        return $branch;
    }

    private function authorizeWrite(Request $request, Branch $branch): void
    {
        abort_unless(in_array('finance.manage', $request->user()->branchFinancePermissions($branch), true),
            403, 'Bu şubenin gelir ve gider kayıtlarını düzenleme yetkiniz yok.');
    }

    private function findEntry(Branch $branch, string $value): BranchLedgerEntry
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        abort_unless($id !== false && $id > 0, 404);

        return $branch->ledgerEntries()->findOrFail($id);
    }

    private function saveChange(Request $request, BranchLedgerEntry $entry, int $revision): void
    {
        abort_unless($entry->revision === $revision, 409, 'Kayıt başka bir oturumda değişti. Kaydı yeniden yükleyin.');
        $entry->revision++;
        $entry->updated_by_user_id = $request->user()->id;
        $entry->save();
    }

    private function entryValues(array $fields): array
    {
        [$whole, $fraction] = array_pad(explode('.', (string) $fields['amount'], 2), 2, '');

        return [
            'kind' => $fields['kind'],
            'entry_date' => $fields['entry_date'],
            'amount_minor' => (int) $whole * 100 + (int) str_pad($fraction, 2, '0'),
            'currency' => $fields['currency'],
            'description' => $fields['description'],
        ];
    }

    private function entry(BranchLedgerEntry $entry): array
    {
        return $entry->only(['id', 'kind', 'description', 'currency', 'revision']) + [
            'entry_date' => $entry->entry_date->toDateString(),
            'amount' => $this->amount($entry->amount_minor),
            'status' => $entry->voided_at === null ? 'active' : 'voided',
        ];
    }

    private function amount(int $minor): string
    {
        return ($minor < 0 ? '-' : '').intdiv(abs($minor), 100).'.'.str_pad((string) (abs($minor) % 100), 2, '0', STR_PAD_LEFT);
    }

    private function respond(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status)->header('Cache-Control', 'private, no-store');
    }
}
