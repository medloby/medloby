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
    private const array TURKISH_LETTERS = [
        'Ç' => 'c', 'ç' => 'c', 'Ğ' => 'g', 'ğ' => 'g', 'İ' => 'i', 'I' => 'i', 'ı' => 'i', "\u{0307}" => '',
        'Ö' => 'o', 'ö' => 'o', 'Ş' => 's', 'ş' => 's', 'Ü' => 'u', 'ü' => 'u',
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'klinik' => ['nullable', 'string', 'max:100'],
            'konum' => ['nullable', 'string', 'max:100'],
            'tedavi' => ['nullable', 'string', 'max:100'],
        ]);
        $name = $filters['klinik'] ?? '';
        $location = $filters['konum'] ?? '';
        $treatment = $filters['tedavi'] ?? '';
        $clinics = null;

        if ($request->hasAny(['klinik', 'konum', 'tedavi', 'klinik_sayfa'])) {
            $clinics = $this->publicClinics()
                ->whereHas('branches', fn (Builder $branches) => $this->matchingBranches($branches, $name, $location, $treatment))
                ->with(['branches' => fn (HasMany $branches) => $this->matchingBranches($branches->getQuery(), $name, $location, $treatment)
                    ->when($treatment !== '', fn (Builder $query) => $query->with(['treatments' => fn (BelongsToMany $treatments) => $this->matchingTreatments($treatments->getQuery(), $treatment)
                        ->orderBy('treatments.name')->select('treatments.id', 'treatments.name')]))
                    ->orderBy('city')->orderBy('district')->orderBy('name')->orderBy('id')->select('id', 'business_id', 'name', 'city', 'district')])
                ->orderBy('name')->orderBy('id')->paginate(9, ['id', 'name', 'slug'], 'klinik_sayfa')
                ->appends($filters)->fragment('klinikler');
        }

        return view('welcome', compact('name', 'location', 'treatment', 'clinics'));
    }

    public function show(string $clinic): View
    {
        $clinic = $this->publicClinics()->where('slug', $clinic)
            ->whereHas('branches', fn (Builder $branches) => $branches->where('status', 'active'))
            ->firstOrFail(['id', 'name', 'slug']);
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
        $hasPackages = $clinic->treatmentPackages()->availableForEnquiries()->exists();

        return view('clinic-directory.show', compact('clinic', 'branches', 'bookable', 'roster', 'hasPackages'));
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

    private function matchingBranches(Builder $branches, string $name, string $location, string $treatment): Builder
    {
        $branches->where('branches.status', 'active');

        if ($name !== '') {
            $branches->where(function (Builder $matching) use ($name): void {
                $this->contains($matching, $name, 'branches.name');
                $matching->orWhereHas('business', fn (Builder $business) => $this->contains($business, $name, 'businesses.name'));
            });
        }

        if ($location !== '') {
            $this->contains($branches, $location, 'branches.city', 'branches.district');
        }

        if ($treatment !== '') {
            $branches->whereHas('treatments', fn (Builder $treatments) => $this->matchingTreatments($treatments, $treatment));
        }

        return $branches;
    }

    private function matchingTreatments(Builder $treatments, string $name): Builder
    {
        return $this->contains(
            $treatments->where('treatments.is_active', true)->where('branch_treatment.is_active', true),
            $name,
            'treatments.name',
        );
    }

    private function contains(Builder $query, string $term, string ...$columns): Builder
    {
        $text = mb_strtolower(strtr($term, self::TURKISH_LETTERS));
        $pattern = '%'.strtr($text, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
        $grammar = $query->getQuery()->getGrammar();

        return $query->where(function (Builder $matches) use ($columns, $grammar, $pattern): void {
            foreach ($columns as $column) {
                $expression = $grammar->wrap($column);
                $bindings = [];
                foreach (self::TURKISH_LETTERS as $letter => $ascii) {
                    $expression = 'REPLACE('.$expression.', ?, ?)';
                    $bindings[] = $letter;
                    $bindings[] = $ascii;
                }
                $matches->orWhereRaw("LOWER({$expression}) LIKE ? ESCAPE '!'", [...$bindings, $pattern]);
            }
        });
    }

    private function publicClinics(): Builder
    {
        return Business::query()->where('status', 'active')->where('is_verified', true);
    }
}
