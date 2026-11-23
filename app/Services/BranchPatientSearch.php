<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\PatientProfile;
use Illuminate\Database\Eloquent\Builder;

class BranchPatientSearch
{
    /** @return list<array{id: int, name: string}> */
    public function search(Branch $branch, string $search): array
    {
        $patients = PatientProfile::query()->knownToBranch($branch);

        foreach (preg_split('/\s+/u', $search, flags: PREG_SPLIT_NO_EMPTY) as $word) {
            $patients->where(fn (Builder $name) => $name
                ->whereLike('first_name', '%'.$word.'%')->orWhereLike('last_name', '%'.$word.'%'));
        }

        return $patients->orderBy('first_name')->orderBy('last_name')->orderBy('id')->limit(25)
            ->get(['id', 'first_name', 'last_name'])->map(fn (PatientProfile $patient) => [
                'id' => $patient->id, 'name' => trim($patient->first_name.' '.$patient->last_name),
            ])->all();
    }
}
