<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchInventoryItem;
use App\Models\BranchInventoryMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClinicInventoryController extends Controller
{
    private const array FIELDS = [
        'code' => ['required', 'string', 'max:60'],
        'name' => ['required', 'string', 'max:160'],
        'unit' => ['required', 'string', 'max:24'],
        'minimum_stock' => ['required', 'integer', 'between:0,2147483647'],
    ];

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $permissions = $this->authorizeView($request, $branch);
        $filters = $request->validate(['low' => ['sometimes', 'boolean'], 'q' => ['nullable', 'string', 'max:80']]);
        $items = $this->items($branch);
        $low = (clone $items)->lowStock()->count();
        if ($filters['low'] ?? false) {
            $items->lowStock();
        }
        if (isset($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $items->where(fn (Builder $query) => $query->whereLike('name', $term)->orWhereLike('code', $term));
        }
        $page = $items->orderBy('name')->orderBy('id')->paginate(20)->through($this->item(...));

        return $this->respond(['items' => $page, 'low_stock_count' => $low, 'can_manage' => in_array('inventory.manage', $permissions, true)]);
    }

    public function store(Request $request, Branch $branch): JsonResponse
    {
        $this->authorizeWrite($request, $branch);
        $values = $request->validate(self::FIELDS);

        return DB::transaction(function () use ($request, $branch, $values): JsonResponse {
            $branch = $this->lockBranch($request, $branch);
            $existing = $this->items($branch)->where('code', $values['code'])->first();
            if ($existing !== null) {
                abort_if($existing->fill($values)->isDirty(), 409, 'Bu malzeme kodu şubede zaten kullanılıyor.');

                return $this->respond($this->item($existing));
            }

            return $this->respond($this->item($this->items($branch)->create($values + ['branch_id' => $branch->id])), 201);
        }, attempts: 3);
    }

    public function update(Request $request, Branch $branch, string $item): JsonResponse
    {
        $this->authorizeWrite($request, $branch);
        $fields = $request->validate(self::FIELDS + ['revision' => ['required', 'integer', 'min:1']]);

        return DB::transaction(function () use ($request, $branch, $item, $fields): JsonResponse {
            $branch = $this->lockBranch($request, $branch);
            $record = $this->findItem($branch, $item);
            $record->fill(array_intersect_key($fields, self::FIELDS));
            if ($record->isDirty()) {
                abort_unless($record->revision === (int) $fields['revision'], 409, 'Stok kaydı değişti. Listeyi yenileyin.');
                abort_if($this->items($branch)->whereKeyNot($record->id)->where('code', $fields['code'])->exists(), 409, 'Bu malzeme kodu şubede zaten kullanılıyor.');
                abort_if($record->isDirty('unit') && $record->movements()->exists(), 409, 'Stok hareketi bulunan malzemenin birimi değiştirilemez.');
                $record->revision++;
                $record->save();
            }

            return $this->respond($this->item($record));
        }, attempts: 3);
    }

    public function movements(Request $request, Branch $branch, string $item): JsonResponse
    {
        $this->authorizeView($request, $branch);
        $record = $this->findItem($branch, $item);
        $page = $record->movements()->latest('id')->paginate(20)->through(fn (BranchInventoryMovement $row): array => [
            ...$row->only(['id', 'direction', 'quantity', 'balance_after', 'description']),
            'created_at' => $row->created_at?->toIso8601String(),
        ]);

        return $this->respond(['item' => $this->item($record), 'movements' => $page]);
    }

    public function move(Request $request, Branch $branch, string $item): JsonResponse
    {
        $this->authorizeWrite($request, $branch);
        $fields = $request->validate([
            'direction' => ['required', 'in:in,out'],
            'quantity' => ['required', 'integer', 'between:1,2147483647'],
            'description' => ['required', 'string', 'max:255'],
            'request_key' => ['required', 'string', 'regex:/\A[a-f0-9]{32}\z/'],
        ]);

        return DB::transaction(function () use ($request, $branch, $item, $fields): JsonResponse {
            $record = $this->findItem($this->lockBranch($request, $branch), $item);
            $prior = $record->movements()->where('request_key', $fields['request_key'])->first();
            if ($prior !== null) {
                abort_if($prior->fill($fields)->isDirty(), 409, 'Bu hareket daha önce farklı bilgilerle kaydedildi.');

                return $this->respond($this->item($record));
            }

            $quantity = (int) $fields['quantity'];
            $balance = $record->on_hand + ($fields['direction'] === 'in' ? $quantity : -$quantity);
            abort_if($balance < 0, 422, 'Çıkış miktarı mevcut stoğu aşıyor.');
            abort_if($balance > 2147483647, 422, 'Stok miktarı kayıt sınırını aşıyor.');
            $record->on_hand = $balance;
            $record->revision++;
            $record->save();
            $record->movements()->create($fields + ['balance_after' => $balance, 'created_by_user_id' => $request->user()->id]);

            return $this->respond($this->item($record), 201);
        }, attempts: 3);
    }

    /** @return Builder<BranchInventoryItem> */
    private function items(Branch $branch): Builder
    {
        return BranchInventoryItem::query()->where('branch_id', $branch->id);
    }

    private function findItem(Branch $branch, string $value): BranchInventoryItem
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        abort_unless($id !== false && $id > 0, 404);

        return $this->items($branch)->findOrFail($id);
    }

    private function item(BranchInventoryItem $item): array
    {
        return $item->only(['id', 'code', 'name', 'unit', 'on_hand', 'minimum_stock', 'revision']) + [
            'low_stock' => $item->on_hand <= $item->minimum_stock,
        ];
    }

    private function authorizeView(Request $request, Branch $branch): array
    {
        $permissions = $request->user()->branchInventoryPermissions($branch);
        abort_if($permissions === [], 403);

        return $permissions;
    }

    private function authorizeWrite(Request $request, Branch $branch): void
    {
        abort_unless(in_array('inventory.manage', $this->authorizeView($request, $branch), true), 403);
    }

    private function lockBranch(Request $request, Branch $branch): Branch
    {
        $branch = Branch::lockForOperationsWrite($branch->id);
        abort_unless($branch->status === 'active' && $branch->business?->allowsCrmAccess(), 403);
        $this->authorizeWrite($request, $branch);

        return $branch;
    }

    private function respond(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status)->header('Cache-Control', 'private, no-store');
    }
}
