<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Doctor;
use App\Models\Treatment;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ClinicDirectoryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'klinik' => ['nullable', 'string', 'max:100'],
            'konum' => ['nullable', 'string', 'max:100'],
        ]);
        $name = $filters['klinik'] ?? '';
        $location = $filters['konum'] ?? '';
        $clinics = null;

        if ($request->hasAny(['klinik', 'konum', 'klinik_sayfa'])) {
            $clinics = $this->publicClinics()
                ->when($name !== '' || $location !== '', fn (Builder $query) => $query->whereHas('branches',
                    fn (Builder $branches) => $branches->where('status', 'active')
                        ->when($name !== '', fn (Builder $matching) => $matching->where(fn (Builder $names) => $names
                            ->whereLike('branches.name', '%'.$name.'%')->orWhereLike('businesses.name', '%'.$name.'%')))
                        ->when($location !== '', fn (Builder $matching) => $matching->where(fn (Builder $locations) => $locations
                            ->whereLike('branches.city', '%'.$location.'%')->orWhereLike('branches.district', '%'.$location.'%')))))
                ->with(['branches' => fn (HasMany $branches) => $branches->where('status', 'active')
                    ->orderBy('city')->orderBy('district')->select('id', 'business_id', 'city', 'district')])
                ->orderBy('name')->orderBy('id')->paginate(9, ['id', 'name', 'slug'], 'klinik_sayfa')
                ->appends($filters)->fragment('klinikler');
        }

        return view('welcome', compact('name', 'location', 'clinics'));
    }

    public function show(string $clinic): View
    {
        $clinic = $this->publicClinics()->where('slug', $clinic)->firstOrFail(['id', 'name', 'slug']);
        $today = Appointment::clinicLocalNow()->toDateString();
        $branches = $clinic->branches()->where('status', 'active')
            ->with([
                'treatments' => fn (BelongsToMany $treatments) => $treatments->wherePivot('is_active', true)
                    ->where('treatments.is_active', true)->orderBy('treatments.name')->select('treatments.id', 'treatments.name'),
                'doctors' => fn (BelongsToMany $doctors) => $doctors->wherePivot('status', 'active')
                    ->where('doctors.status', 'active')->where('doctors.is_public', true)
                    ->whereHas('person', fn (Builder $person) => $person->where('business_id', $clinic->id)->where('status', 'active'))
                    ->where(fn (Builder $assignment) => $assignment->whereNull('doctor_branch.start_date')->orWhere('doctor_branch.start_date', '<=', $today))
                    ->where(fn (Builder $assignment) => $assignment->whereNull('doctor_branch.end_date')->orWhere('doctor_branch.end_date', '>=', $today))
                    ->select('doctors.id', 'doctors.person_id', 'doctors.specialty')
                    ->with(['person:id,first_name,last_name,title,specialty', 'treatments' => fn (BelongsToMany $treatments) => $treatments
                        ->wherePivot('is_active', true)->where('treatments.is_active', true)->select('treatments.id')]),
            ])
            ->orderBy('name')->orderBy('id')->get(['id', 'name', 'city', 'district', 'address']);
        $bookable = $clinic->branches()->bookableOnline()->pluck('branches.id');
        $roster = $branches->mapWithKeys(fn (Branch $branch): array => [$branch->id => $this->doctorList($branch, $bookable->contains($branch->id))]);

        return view('clinic-directory.show', compact('clinic', 'branches', 'bookable', 'roster'));
    }

    /** @return Collection<int, array{id: int, name: string, specialty: ?string, treatments: string, bookable: bool}> */
    private function doctorList(Branch $branch, bool $branchBookable): Collection
    {
        return $branch->doctors->map(function (Doctor $doctor) use ($branch, $branchBookable): array {
            $person = $doctor->person;
            $treatments = $branch->treatments->whereInStrict('id', $doctor->treatments->modelKeys());

            return [
                'id' => $doctor->id,
                'name' => implode(' ', array_filter([$person->title, $person->first_name, $person->last_name])),
                'specialty' => $doctor->specialty ?? $person->specialty,
                'treatments' => $treatments->pluck('name')->join(', '),
                'bookable' => $branchBookable && $treatments->contains(fn (Treatment $treatment): bool => (bool) $treatment->pivot->is_online_bookable),
            ];
        })->sortBy('name')->values();
    }

    private function publicClinics(): Builder
    {
        return Business::query()->where('status', 'active')->where('is_verified', true)
            ->whereHas('branches', fn (Builder $branches) => $branches->where('status', 'active'));
    }
}
