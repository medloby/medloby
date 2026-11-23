<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchBalance;
use App\Models\BranchLedgerEntry;
use App\Models\PatientProfile;
use App\Rules\PriceAmount;
use App\Services\BranchPatientSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClinicBalanceController extends Controller
{
    private const array RULES = [
        'direction' => ['required', 'in:receivable,payable'],
        'patient_profile_id' => ['nullable', 'integer', 'min:1', 'prohibits:counterparty'],
        'counterparty' => ['required_without:patient_profile_id', 'nullable', 'string', 'max:120'],
        'description' => ['required', 'string', 'max:255'],
        'amount' => ['required', ...PriceAmount::RULES, 'decimal:0,2', 'min:0.01'],
        'currency' => ['required', 'string', 'alpha:ascii', 'size:3', 'uppercase'],
        'due_on' => ['nullable', 'date_format:Y-m-d', 'regex:/\A(?!0000)\d{4}-\d{2}-\d{2}\z/'],
    ];

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $permissions = $this->permissions($request, $branch);
        $status = $request->validate(['status' => ['sometimes', 'required', 'in:active,voided']])['status'] ?? 'active';
        $balances = $this->balances($branch)->withSum('activeEntries as settled_minor', 'amount_minor');
        $status === 'voided' ? $balances->whereNotNull('voided_at') : $balances->whereNull('voided_at');
        $page = $balances->latest('id')->paginate(20)->through($this->summary(...));

        return $this->respond(['can_manage' => in_array('finance.manage', $permissions, true), 'balances' => $page]);
    }

    public function patients(Request $request, Branch $branch, BranchPatientSearch $patients): JsonResponse
    {
        $this->authorizeWrite($request, $branch);
        $query = $request->validate(['q' => ['required', 'string', 'min:2', 'max:80']])['q'];

        return $this->respond($patients->search($branch, $query));
    }

    public function show(Request $request, Branch $branch, string $balance): JsonResponse
    {
        $this->permissions($request, $branch);
        $record = $this->findBalance($branch, $balance);
        $movements = $record->entries()->where('branch_id', $branch->id)->latest('entry_date')->latest('id')->paginate(20)
            ->through(fn (BranchLedgerEntry $entry): array => [
                'entry_date' => $entry->entry_date->toDateString(),
                'description' => $entry->description,
                'amount' => BranchLedgerEntry::formatAmount($entry->amount_minor),
                'channel' => $entry->payment_channel,
                'voided' => $entry->voided_at !== null,
            ]);

        return $this->respond($this->summary($record) + ['movements' => $movements]);
    }

    public function store(Request $request, Branch $branch): JsonResponse
    {
        $this->authorizeWrite($request, $branch);
        $fields = $request->validate(self::RULES + ['request_key' => ['required', 'string', 'regex:/\A[a-f0-9]{32}\z/']]);

        return DB::transaction(function () use ($request, $branch, $fields): JsonResponse {
            $branch = $this->lockBranch($request, $branch);
            $values = $this->values($branch, $fields);
            $existing = $this->balances($branch)->where('request_key', $fields['request_key'])->first();
            if ($existing !== null) {
                abort_if($existing->fill($values)->isDirty(), 409, 'Bu borç veya alacak daha önce kaydedildi.');

                return $this->respond($this->summary($existing));
            }

            $saved = $this->balances($branch)->create($values + ['branch_id' => $branch->id, 'request_key' => $fields['request_key']]);

            return $this->respond($this->summary($saved), 201);
        }, attempts: 3);
    }

    public function update(Request $request, Branch $branch, string $balance): JsonResponse
    {
        $this->authorizeWrite($request, $branch);
        $fields = $request->validate(self::RULES + ['revision' => ['required', 'integer', 'min:1']]);

        return DB::transaction(function () use ($request, $branch, $balance, $fields): JsonResponse {
            $branch = $this->lockBranch($request, $branch);
            $record = $this->findBalance($branch, $balance);
            abort_if($record->voided_at !== null, 409, 'İptal edilen kaydı düzenlemek için yeniden etkinleştirin.');
            $record->fill($this->values($branch, $fields));
            if ($record->isDirty()) {
                abort_if($record->entries()->exists() && $record->isDirty(['currency', 'direction', 'patient_profile_id']), 409,
                    'Hareketi bulunan kaydın türü, hastası ve para birimi değiştirilemez.');
                abort_if($record->amount_minor < (int) $record->activeEntries()->sum('amount_minor'), 422, 'Tutar işlenen hareketlerin toplamından küçük olamaz.');
                $this->saveRevision($record, (int) $fields['revision']);
            }

            return $this->respond($this->summary($record));
        }, attempts: 3);
    }

    public function updateStatus(Request $request, Branch $branch, string $balance): JsonResponse
    {
        $this->authorizeWrite($request, $branch);
        $fields = $request->validate(['revision' => ['required', 'integer', 'min:1'], 'status' => ['required', 'in:active,voided']]);

        return DB::transaction(function () use ($request, $branch, $balance, $fields): JsonResponse {
            $record = $this->findBalance($this->lockBranch($request, $branch), $balance);
            $voiding = $fields['status'] === 'voided';
            if ($voiding !== ($record->voided_at !== null)) {
                abort_if($voiding && $record->activeEntries()->exists(), 409, 'Kaydı iptal etmek için önce bağlı aktif hareketleri iptal edin.');
                $record->voided_at = $voiding ? now() : null;
                $this->saveRevision($record, (int) $fields['revision']);
            }

            return $this->respond($this->summary($record));
        }, attempts: 3);
    }

    private function values(Branch $branch, array $fields): array
    {
        $patient = isset($fields['patient_profile_id']) ? (int) $fields['patient_profile_id'] : null;
        if ($patient !== null && ($fields['direction'] !== 'receivable' || ! PatientProfile::query()->knownToBranch($branch)->whereKey($patient)->exists())) {
            throw ValidationException::withMessages(['patient_profile_id' => 'Alacak için şubeyle ilişkisi doğrulanmış bir hasta seçin.']);
        }

        return [
            'direction' => $fields['direction'], 'patient_profile_id' => $patient, 'counterparty' => $fields['counterparty'] ?? null,
            'description' => $fields['description'], 'amount_minor' => BranchLedgerEntry::parseAmount((string) $fields['amount']),
            'currency' => $fields['currency'], 'due_on' => $fields['due_on'] ?? null,
        ];
    }

    /** @return Builder<BranchBalance> */
    private function balances(Branch $branch): Builder
    {
        return BranchBalance::query()->where('branch_id', $branch->id)
            ->with(['patient' => fn ($query) => $query->knownToBranch($branch)->select('id', 'first_name', 'last_name')]);
    }

    private function findBalance(Branch $branch, string $value): BranchBalance
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        abort_unless($id !== false && $id > 0, 404);

        return $this->balances($branch)->findOrFail($id);
    }

    private function summary(BranchBalance $balance): array
    {
        $patient = $balance->patient;
        $settled = (int) ($balance->settled_minor ?? $balance->activeEntries()->sum('amount_minor'));
        $remaining = $balance->amount_minor - $settled;

        return $balance->only(['id', 'direction', 'counterparty', 'description', 'currency', 'revision']) + [
            'patient' => $patient ? ['id' => $patient->id, 'name' => trim($patient->first_name.' '.$patient->last_name)] : null,
            'due_on' => $balance->due_on?->toDateString(),
            'amount' => BranchLedgerEntry::formatAmount($balance->amount_minor),
            'settled' => BranchLedgerEntry::formatAmount($settled),
            'remaining' => BranchLedgerEntry::formatAmount($remaining),
            'status' => $balance->voided_at !== null ? 'voided' : ($remaining === 0 ? 'settled' : 'open'),
        ];
    }

    private function permissions(Request $request, Branch $branch): array
    {
        $permissions = $request->user()->branchFinancePermissions($branch);
        abort_if($permissions === [], 403);

        return $permissions;
    }

    private function authorizeWrite(Request $request, Branch $branch): void
    {
        abort_unless(in_array('finance.manage', $this->permissions($request, $branch), true), 403);
    }

    private function lockBranch(Request $request, Branch $branch): Branch
    {
        $branch = Branch::query()->lockForUpdate()->findOrFail($branch->id);
        abort_unless($branch->status === 'active' && $branch->business?->allowsCrmAccess(), 403);
        $this->authorizeWrite($request, $branch);

        return $branch;
    }

    private function saveRevision(BranchBalance $balance, int $revision): void
    {
        abort_unless($balance->revision === $revision, 409, 'Kayıt başka bir oturumda değişti.');
        $balance->revision++;
        $balance->save();
    }

    private function respond(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status)->header('Cache-Control', 'private, no-store');
    }
}
