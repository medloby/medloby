<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchLedgerEntry;
use App\Models\Offer;
use App\Rules\PriceAmount;
use App\Services\ClinicBalances;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClinicLedgerController extends Controller
{
    private const array ENTRY_RULES = [
        'kind' => ['required', 'in:income,expense'],
        'entry_date' => ['required', 'date_format:Y-m-d', 'regex:/\A(?!0000)\d{4}-\d{2}-\d{2}\z/'],
        'amount' => ['required', ...PriceAmount::RULES, 'decimal:0,2', 'min:0.01'],
        'currency' => ['required', 'string', 'alpha:ascii', 'size:3', 'uppercase'],
        'description' => ['required', 'string', 'max:255'],
        'appointment_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'prohibits:offer_id'],
        'offer_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        'branch_balance_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        'expense_category' => ['sometimes', 'nullable', 'string', 'max:80'],
        'payment_channel' => ['sometimes', 'nullable', 'in:cash,bank'],
    ];

    private const array REVISION_RULES = ['revision' => ['required', 'integer', 'min:1']];

    public function __construct(private readonly ClinicBalances $balances) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $permissions = $request->user()->branchFinancePermissions($branch);
        abort_if($permissions === [], 403, 'Bu şubenin gelir ve gider kayıtlarına erişim yetkiniz yok.');

        $filters = $request->validate([
            'month' => ['sometimes', 'required', 'date_format:Y-m', 'regex:/\A(?!0000)\d{4}-\d{2}\z/'],
            'status' => ['sometimes', 'required', 'in:active,voided'],
            'channel' => ['sometimes', 'required', 'in:cash,bank,unassigned'],
        ]);
        $today = CarbonImmutable::today('Europe/Istanbul');
        $month = CarbonImmutable::createFromFormat('!Y-m', $filters['month'] ?? $today->format('Y-m'));
        $entries = $branch->ledgerEntries()->recordedBetween($month, $month->endOfMonth());
        $totals = (clone $entries)->whereNull('voided_at')->withAmountTotals()->orderBy('currency')
            ->get()->map(fn (BranchLedgerEntry $summary): array => $summary->amountTotals());

        $page = ($filters['status'] ?? 'active') === 'voided' ? $entries->whereNotNull('voided_at') : $entries->whereNull('voided_at');
        if (isset($filters['channel'])) {
            $page->where('payment_channel', $filters['channel'] === 'unassigned' ? null : $filters['channel']);
        }
        $page = $page->latest('entry_date')->latest('id')->paginate(20)->through($this->entry(...));
        $active = $branch->ledgerEntries()->whereNull('voided_at');
        $categories = (clone $active)->recordedBetween($month, $month->endOfMonth())->where('kind', 'expense')
            ->withAmountTotals(['expense_category', 'currency'])->orderBy('expense_category')->orderBy('currency')->get()
            ->map(fn (BranchLedgerEntry $row): array => [
                'category' => $row->expense_category, 'currency' => $row->currency, 'amount' => BranchLedgerEntry::formatAmount((int) $row->expense),
            ]);
        $accounts = $active->whereNotNull('payment_channel')->where('entry_date', '<=', $month->endOfMonth()->format('Y-m-d H:i:s.u'))
            ->withAmountTotals(['payment_channel', 'currency'])->orderBy('payment_channel')->orderBy('currency')->get()
            ->map(fn (BranchLedgerEntry $row): array => $row->amountTotals() + ['channel' => $row->payment_channel]);

        return $this->respond([
            'month' => $month->format('Y-m'),
            'today' => $today->toDateString(),
            'can_manage' => in_array('finance.manage', $permissions, true),
            'totals' => $totals->all(),
            'expense_categories' => $categories->all(),
            'accounts' => $accounts->all(),
            'entries' => $page->items(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function references(Request $request, Branch $branch): JsonResponse
    {
        $this->authorizeWrite($request, $branch);
        $filters = $request->validate([
            'type' => ['required', 'in:appointment,package'],
            'id' => ['sometimes', 'required', 'integer', 'min:1'],
        ]);
        $id = $filters['id'] ?? null;

        if ($filters['type'] === 'appointment') {
            $references = $this->branchAppointments($branch)
                ->when($id === null, fn (Builder $appointments) => $appointments->where('status', '!=', 'rescheduled'))
                ->when($id !== null, fn (Builder $appointments) => $appointments->whereKey($id))
                ->latest('starts_at')->latest('id')->limit(25)->get(['id', 'starts_at', 'treatment_id'])
                ->load('treatment:id,name')->map(fn (Appointment $visit): array => [
                    'id' => $visit->id,
                    'label' => trim('Randevu #'.$visit->id.' · '.$visit->starts_at->format('d.m.Y H:i').($visit->treatment ? ' · '.$visit->treatment->name : '')),
                ]);
        } else {
            $references = $this->branchPackages($branch)->when($id !== null, fn (Builder $offers) => $offers->whereKey($id))
                ->latest('id')->limit(25)->get(['id', 'created_at'])
                ->map(fn (Offer $offer): array => [
                    'id' => $offer->id,
                    'label' => 'Paket teklifi #'.$offer->id.($offer->created_at ? ' · '.$offer->created_at->format('d.m.Y') : ''),
                ]);
        }

        return $this->respond($references->all());
    }

    public function store(Request $request, Branch $branch): JsonResponse
    {
        $this->authorizeWrite($request, $branch);
        $fields = $request->validate(self::ENTRY_RULES + ['request_key' => ['required', 'string', 'regex:/\A[a-f0-9]{32}\z/']]);
        $values = $this->entryValues($fields);

        return DB::transaction(function () use ($request, $branch, $fields, $values): JsonResponse {
            $branch = $this->lockBranch($request, $branch);
            $values += $this->incomeReferences($branch, $fields);
            $existing = $branch->ledgerEntries()->where('request_key', $fields['request_key'])->first();
            if ($existing) {
                abort_if($existing->fill($values)->isDirty(), 409, 'Bu giriş daha önce kaydedildi. Kayıt listesini yenileyin.');

                return $this->respond($this->entry($existing));
            }

            $saved = $branch->ledgerEntries()->make($values + [
                'request_key' => $fields['request_key'],
                'created_by_user_id' => $request->user()->id,
                'updated_by_user_id' => $request->user()->id,
            ]);
            $this->balances->validateSettlement($branch, $saved);
            $saved->save();

            return $this->respond($this->entry($saved), 201);
        }, attempts: 3);
    }

    public function update(Request $request, Branch $branch, string $entry): JsonResponse
    {
        $this->authorizeWrite($request, $branch);
        $fields = $request->validate(self::ENTRY_RULES + self::REVISION_RULES);

        return DB::transaction(function () use ($request, $branch, $entry, $fields): JsonResponse {
            $branch = $this->lockBranch($request, $branch);
            $saved = $this->findEntry($branch, $entry);
            abort_if($saved->voided_at !== null, 409, 'İptal edilen kaydı düzenlemek için yeniden etkinleştirin.');
            $values = $this->entryValues($fields);
            if ($fields['kind'] === 'expense' || array_key_exists('appointment_id', $fields) || array_key_exists('offer_id', $fields)) {
                $values += $this->incomeReferences($branch, $fields);
            }
            if ($saved->fill($values)->isDirty()) {
                $this->saveChange($request, $branch, $saved, (int) $fields['revision']);
            }

            return $this->respond($this->entry($saved));
        }, attempts: 3);
    }

    /** @return Builder<Appointment> */
    private function branchAppointments(Branch $branch): Builder
    {
        return Appointment::query()->where('branch_id', $branch->id)->where('business_id', $branch->business_id);
    }

    /** @return Builder<Offer> */
    private function branchPackages(Branch $branch): Builder
    {
        return Offer::query()->where('offers.business_id', $branch->business_id)->where('status', 'accepted')
            ->whereNotNull('package_snapshot')
            ->where(fn (Builder $offer) => $offer->whereNull('branch_id')->orWhere('branch_id', $branch->id))
            ->where(fn (Builder $offer) => $offer->whereNull('package_snapshot->branch_id')->orWhere('package_snapshot->branch_id', $branch->id))
            ->whereHas('conversation', fn (Builder $conversation) => $conversation->whereColumn('conversations.business_id', 'offers.business_id')
                ->whereColumn('conversations.patient_profile_id', 'offers.patient_profile_id')
                ->where(fn (Builder $scope) => $scope->whereNull('branch_id')->orWhere('branch_id', $branch->id)))
            ->where(fn (Builder $offer) => $offer->where('branch_id', $branch->id)->orWhere('package_snapshot->branch_id', $branch->id)
                ->orWhereHas('conversation', fn (Builder $conversation) => $conversation->where('branch_id', $branch->id))
                ->orWhereHas('appointments', fn (Builder $appointments) => $appointments->where('branch_id', $branch->id)
                    ->where('business_id', $branch->business_id)));
    }

    /** @return array{appointment_id: ?int, offer_id: ?int} */
    private function incomeReferences(Branch $branch, array $fields): array
    {
        $appointment = isset($fields['appointment_id']) ? (int) $fields['appointment_id'] : null;
        $offer = isset($fields['offer_id']) ? (int) $fields['offer_id'] : null;
        if ($appointment !== null && ($fields['kind'] !== 'income' || ! $this->branchAppointments($branch)->whereKey($appointment)->exists())) {
            throw ValidationException::withMessages(['appointment_id' => 'Gelir kaydı için bu şubeye ait bir randevu seçin.']);
        }
        if ($offer !== null && ($fields['kind'] !== 'income' || ! $this->branchPackages($branch)->whereKey($offer)->exists())) {
            throw ValidationException::withMessages(['offer_id' => 'Gelir kaydı için bu şubeye ait kabul edilmiş bir paket seçin.']);
        }

        return ['appointment_id' => $appointment, 'offer_id' => $offer];
    }

    public function updateStatus(Request $request, Branch $branch, string $entry): JsonResponse
    {
        $this->authorizeWrite($request, $branch);
        $fields = $request->validate(self::REVISION_RULES + ['status' => ['required', 'in:active,voided']]);

        return DB::transaction(function () use ($request, $branch, $entry, $fields): JsonResponse {
            $branch = $this->lockBranch($request, $branch);
            $saved = $this->findEntry($branch, $entry);
            $voiding = $fields['status'] === 'voided';
            if ($voiding !== ($saved->voided_at !== null)) {
                $saved->voided_at = $voiding ? now() : null;
                $this->saveChange($request, $branch, $saved, (int) $fields['revision']);
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

    private function saveChange(Request $request, Branch $branch, BranchLedgerEntry $entry, int $revision): void
    {
        abort_unless($entry->revision === $revision, 409, 'Kayıt başka bir oturumda değişti. Kaydı yeniden yükleyin.');
        $this->balances->validateSettlement($branch, $entry);
        $entry->revision++;
        $entry->updated_by_user_id = $request->user()->id;
        $entry->save();
    }

    private function entryValues(array $fields): array
    {
        $values = [
            'kind' => $fields['kind'],
            'entry_date' => $fields['entry_date'],
            'amount_minor' => BranchLedgerEntry::parseAmount((string) $fields['amount']),
            'currency' => $fields['currency'],
            'description' => $fields['description'],
        ];
        if (array_key_exists('branch_balance_id', $fields)) {
            $values['branch_balance_id'] = isset($fields['branch_balance_id']) ? (int) $fields['branch_balance_id'] : null;
        }
        if (array_key_exists('payment_channel', $fields)) {
            $values['payment_channel'] = $fields['payment_channel'];
        }
        if ($fields['kind'] === 'income' || array_key_exists('expense_category', $fields)) {
            $values['expense_category'] = $fields['kind'] === 'expense' ? ($fields['expense_category'] ?? null) : null;
        }

        return $values;
    }

    private function entry(BranchLedgerEntry $entry): array
    {
        return $entry->only(['id', 'kind', 'description', 'currency', 'revision']) + [
            'entry_date' => $entry->entry_date->toDateString(),
            'amount' => BranchLedgerEntry::formatAmount($entry->amount_minor),
            'status' => $entry->voided_at === null ? 'active' : 'voided',
            'appointment_id' => $entry->appointment_id,
            'offer_id' => $entry->offer_id,
            'branch_balance_id' => $entry->branch_balance_id,
            'expense_category' => $entry->expense_category,
            'payment_channel' => $entry->payment_channel,
        ];
    }

    private function respond(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status)->header('Cache-Control', 'private, no-store');
    }
}
