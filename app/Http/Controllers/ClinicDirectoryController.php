<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

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
        $branches = $clinic->branches()->where('status', 'active')
            ->with(['treatments' => fn (BelongsToMany $treatments) => $treatments->wherePivot('is_active', true)
                ->where('treatments.is_active', true)->orderBy('treatments.name')->select('treatments.id', 'treatments.name')])
            ->orderBy('name')->orderBy('id')->get(['id', 'name', 'city', 'district', 'address']);
        $bookable = $clinic->branches()->bookableOnline()->pluck('branches.id');

        return view('clinic-directory.show', compact('clinic', 'branches', 'bookable'));
    }

    private function publicClinics(): Builder
    {
        return Business::query()->where('status', 'active')->where('is_verified', true)
            ->whereHas('branches', fn (Builder $branches) => $branches->where('status', 'active'));
    }
}
