<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchBalance;
use App\Models\BranchLedgerEntry;
use App\Models\Offer;
use Illuminate\Validation\ValidationException;

final class ClinicBalances
{
    public function validateSettlement(Branch $branch, BranchLedgerEntry $entry): void
    {
        if ($entry->branch_balance_id === null || $entry->voided_at !== null) {
            return;
        }

        $balance = BranchBalance::query()->where('branch_id', $branch->id)
            ->whereNull('voided_at')->find($entry->branch_balance_id);
        if ($balance === null || $entry->currency !== $balance->currency || $entry->kind !== $this->kind($balance)) {
            throw ValidationException::withMessages(['branch_balance_id' => 'Şubeye, para birimine ve işlem türüne uygun bir borç veya alacak seçin.']);
        }

        if ($balance->patient_profile_id !== null && ! $this->matchesPatient($entry, $balance)) {
            throw ValidationException::withMessages(['branch_balance_id' => 'Tahsilat bağlantısı aynı hastaya ait olmalıdır.']);
        }

        $otherEntries = $balance->activeEntries()->where('branch_id', $branch->id)
            ->when($entry->exists, fn ($query) => $query->whereKeyNot($entry->id));
        $remaining = $balance->amount_minor - (int) $otherEntries->sum('amount_minor');
        if ($entry->amount_minor > $remaining) {
            throw ValidationException::withMessages(['amount' => 'İşlem tutarı kalan bakiyeyi aşıyor.']);
        }
    }

    private function kind(BranchBalance $balance): string
    {
        return $balance->direction === 'receivable' ? 'income' : 'expense';
    }

    private function matchesPatient(BranchLedgerEntry $entry, BranchBalance $balance): bool
    {
        if ($entry->appointment_id !== null) {
            return Appointment::query()->whereKey($entry->appointment_id)
                ->where('patient_profile_id', $balance->patient_profile_id)->exists();
        }

        return $entry->offer_id === null || Offer::query()->whereKey($entry->offer_id)
            ->where('patient_profile_id', $balance->patient_profile_id)->exists();
    }
}
