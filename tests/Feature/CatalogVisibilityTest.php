<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\PackageService;
use App\Models\PatientProfile;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\TreatmentPackageItem;
use App\Models\TreatmentPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->branch = Branch::factory()->for($this->business)->create();
    $this->treatment = Treatment::create([
        'treatment_category_id' => TreatmentCategory::factory()->create()->id,
        'name' => 'Kontrol seansı',
        'slug' => 'kontrol-seansi',
        'is_active' => true,
    ]);
    $this->branch->treatments()->attach($this->treatment->id, ['is_active' => true]);
});

test('public package and service reads only show published catalog data', function () {
    $package = $this->business->treatmentPackages()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Bakım paketi',
        'slug' => 'bakim-paketi',
        'description' => 'Kliniğin serbest metin alanı',
        'price' => 25000,
        'currency' => 'TRY',
        'included_services' => 'Klinik içi hizmet notu',
    ]);
    $treatmentItem = TreatmentPackageItem::create([
        'treatment_package_id' => $package->id,
        'treatment_id' => $this->treatment->id,
        'quantity' => 2,
        'notes' => 'Klinik içi tedavi notu',
    ]);
    $service = PackageService::create(['name' => 'Transfer', 'slug' => 'transfer', 'service_type' => 'transfer']);
    $package->services()->attach($service->id, ['notes' => 'Klinik içi transfer notu']);

    $expired = $this->business->treatmentPackages()->create([
        'name' => 'Süresi dolan paket',
        'slug' => 'suresi-dolan-paket',
        'valid_until' => today()->subDay(),
    ]);
    $expired->services()->attach($service->id);
    $pending = Business::factory()->create(['status' => 'pending', 'is_verified' => false]);
    $draft = $pending->treatmentPackages()->create(['name' => 'Taslak paket', 'slug' => 'taslak-paket']);
    $draft->services()->attach($service->id);
    $draftService = $pending->packageServices()->create([
        'name' => 'Kliniğe ait taslak hizmet',
        'slug' => 'taslak-hizmet',
        'service_type' => 'transfer',
    ]);
    $this->getJson('/api/package-catalog')
        ->assertOk()->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.id', $package->id)
        ->assertJsonPath('data.data.0.treatments.0', ['name' => $this->treatment->name, 'quantity' => 2])
        ->assertJsonMissingPath('data.data.0.description')
        ->assertJsonMissingPath('data.data.0.price')
        ->assertJsonMissingPath('data.data.0.currency');

    $this->getJson('/api/treatment-packages')
        ->assertOk()->assertJsonCount(1, 'data.packages')
        ->assertJsonMissingPath('data.packages.0.price');

    $packageUrl = "/api/treatment-packages/{$package->id}";
    $this->getJson($packageUrl)
        ->assertOk()
        ->assertJsonMissingPath('data.price')
        ->assertJsonMissingPath('data.description')
        ->assertJsonMissingPath('data.included_services')
        ->assertJsonMissingPath('data.business.email')
        ->assertJsonMissingPath('data.branch.email');
    $this->getJson($packageUrl.'/items/'.$treatmentItem->id)
        ->assertOk()->assertJsonMissingPath('data.notes');
    $this->getJson($packageUrl.'/service-items/'.$package->serviceItems()->firstOrFail()->id)
        ->assertOk()->assertJsonMissingPath('data.notes');
    $package->services()->attach($draftService->id);
    $this->getJson($packageUrl)->assertOk()->assertJsonCount(1, 'data.services');
    $this->getJson($packageUrl.'/service-items')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/treatment-packages/{$expired->id}/items")->assertNotFound();
    $this->getJson("/api/treatment-packages/{$draft->id}/service-items")->assertNotFound();

    $this->getJson('/api/package-services')->assertOk()->assertJsonCount(1, 'data.services');
    $this->getJson("/api/package-services/{$draftService->id}")->assertNotFound();
    $this->getJson("/api/package-services/{$service->id}")
        ->assertOk()->assertJsonCount(1, 'data.packages')
        ->assertJsonPath('data.packages.0.id', $package->id)
        ->assertJsonMissingPath('data.packages.0.price')
        ->assertJsonMissingPath('data.packages.0.pivot');
});

test('catalog search matches package and treatment names and uses the location of the listed branch', function () {
    $this->business->update(['name' => 'Deneme Klinik', 'city' => 'Ankara']);
    $this->branch->update(['name' => 'Sahil Şubesi', 'city' => 'İzmir', 'district' => 'Konak']);

    $branchPackage = $this->business->treatmentPackages()->create([
        'branch_id' => $this->branch->id, 'name' => 'Yıllık bakım', 'slug' => 'yillik-bakim',
    ]);
    $branchPackage->treatments()->attach($this->treatment->id);
    $clinicPackage = $this->business->treatmentPackages()->create([
        'name' => 'Genel bakım', 'slug' => 'genel-bakim',
    ]);
    $clinicPackage->treatments()->attach($this->treatment->id);
    $pendingClinic = Business::factory()->create(['status' => 'pending', 'is_verified' => false]);
    $pending = $pendingClinic->treatmentPackages()->create(['name' => 'Yıllık bakım', 'slug' => 'yillik-bakim']);
    $pending->treatments()->attach($this->treatment->id);

    $url = '/api/package-catalog?';
    $this->getJson($url.http_build_query(['q' => 'Yıllık bakım']))->assertOk()
        ->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', $branchPackage->id);
    $this->getJson($url.http_build_query(['q' => 'Deneme Klinik']))->assertOk()->assertJsonPath('data.total', 2);
    $this->getJson($url.http_build_query(['q' => 'Sahil Şubesi']))->assertOk()->assertJsonPath('data.total', 1);
    $this->getJson($url.http_build_query(['q' => 'Kontrol', 'city' => 'İzmir']))->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.data.0.id', $branchPackage->id)
        ->assertJsonPath('data.data.0.location', ['city' => 'İzmir', 'district' => 'Konak']);
    $this->getJson($url.http_build_query(['city' => 'Ankara']))->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.data.0.id', $clinicPackage->id)
        ->assertJsonPath('data.data.0.location', ['city' => 'Ankara', 'district' => null]);
    $this->getJson($url.'city[]=Ankara')->assertUnprocessable()->assertJsonValidationErrors('city');
});

test('the clinic page opens an exact public catalog scope and the scope survives search and pagination', function () {
    $this->business->update(['name' => 'Deneme Klinik', 'slug' => 'deneme-klinik']);
    $this->branch->update(['city' => 'İzmir']);
    $other = Business::factory()->create(['name' => $this->business->name, 'slug' => 'baska-deneme-klinik']);
    $otherBranch = Branch::factory()->for($other)->create(['city' => 'İzmir']);
    $otherBranch->treatments()->attach($this->treatment->id, ['is_active' => true]);
    $otherPackage = $other->treatmentPackages()->create([
        'name' => 'Bakım 01', 'slug' => 'baska-bakim', 'branch_id' => $otherBranch->id,
    ]);
    $otherPackage->treatments()->attach($this->treatment->id);

    foreach (range(1, 13) as $number) {
        $package = $this->business->treatmentPackages()->create([
            'name' => sprintf('Bakım %02d', $number), 'slug' => 'bakim-'.$number, 'branch_id' => $this->branch->id,
            'price' => 4500, 'currency' => 'TRY',
        ]);
        $package->treatments()->attach($this->treatment->id, ['quantity' => 2]);
    }
    $expired = $this->business->treatmentPackages()->create([
        'name' => 'Bakım süresi bitti', 'slug' => 'bakim-suresi-bitti', 'valid_until' => today()->subDay(),
    ]);
    $expired->treatments()->attach($this->treatment->id);
    $link = route('package-offers', ['clinic' => $this->business->slug]).'#katalog';
    $this->get(route('clinics.show', $this->business->slug))->assertOk()->assertSee($link);

    $filters = ['clinic' => $this->business->slug, 'q' => 'Bakım', 'city' => 'İzmir'];
    $catalog = '/api/package-catalog?';
    $firstPage = $this->getJson($catalog.http_build_query($filters))->assertOk()
        ->assertJsonPath('data.clinic', ['name' => $this->business->name, 'profile_url' => '/klinikler/deneme-klinik'])
        ->assertJsonPath('data.total', 13)->assertJsonCount(12, 'data.data')
        ->assertJsonPath('data.data.0.business_id', $this->business->id)
        ->assertJsonPath('data.data.0.treatments.0.quantity', 2)->assertJsonMissingPath('data.data.0.price');
    expect($firstPage->json('data.data.*.id'))->not->toContain($otherPackage->id)->not->toContain($expired->id);
    parse_str((string) parse_url($firstPage->json('data.next_page_url'), PHP_URL_QUERY), $nextFilters);
    expect($nextFilters)->toMatchArray([...$filters, 'page' => '2']);
    $this->getJson($catalog.http_build_query([...$filters, 'page' => 2]))->assertOk()
        ->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.name', 'Bakım 13');

    $this->getJson($catalog.http_build_query(['q' => 'Bakım', 'city' => 'İzmir']))->assertOk()
        ->assertJsonPath('data.total', 14)->assertJsonMissingPath('data.clinic');
    $this->business->update(['status' => 'suspended']);
    $this->getJson($catalog.http_build_query($filters))->assertNotFound();
});

test('clinic pages show a catalog link when a package can be discussed in the account', function () {
    $package = $this->business->treatmentPackages()->create([
        'name' => 'Taslak bakım', 'slug' => 'taslak-bakim', 'is_offer_enabled' => false,
    ]);
    $package->treatments()->attach($this->treatment->id);
    $profile = route('clinics.show', $this->business->slug);

    $this->get($profile)->assertOk()->assertDontSeeText('Kliniğin paketlerini incele');
    $package->update(['is_offer_enabled' => true]);
    $this->get($profile)->assertOk()->assertSeeText('Kliniğin paketlerini incele');
});

test('treatment prices require a verified clinic member and respect branch assignments', function () {
    $otherClinic = Business::factory()->create();
    $otherBranch = Branch::factory()->for($this->business)->create();
    $otherClinicBranch = Branch::factory()->for($otherClinic)->create();
    $prices = [];
    foreach ([
        'clinic' => [$this->business->id, null],
        'assigned' => [$this->business->id, $this->branch->id],
        'unassigned' => [$this->business->id, $otherBranch->id],
        'other clinic' => [$otherClinic->id, null],
        'mismatched branch' => [$this->business->id, $otherClinicBranch->id],
    ] as $scope => [$businessId, $branchId]) {
        $prices[$scope] = TreatmentPrice::create([
            'business_id' => $businessId,
            'branch_id' => $branchId,
            'treatment_id' => $this->treatment->id,
            'price_type' => 'fixed',
            'price' => 1500,
            'currency' => 'TRY',
        ]);
    }

    $url = '/api/treatment-prices';
    $this->getJson($url)->assertUnauthorized();
    $this->getJson($url.'/'.$prices['assigned']->id)->assertUnauthorized();

    $staff = User::factory()->unverified()->create();
    $membership = $this->business->memberships()->create([
        'user_id' => $staff->id, 'role' => 'staff', 'is_active' => true,
    ]);
    $membership->branches()->attach($this->branch->id, ['is_active' => true]);
    $membership->branches()->attach($otherBranch->id, ['is_active' => false]);
    $this->withToken($staff->createToken('catalog-test')->plainTextToken)->getJson($url)->assertForbidden();

    $staff->markEmailAsVerified();
    $this->actingAs($staff, 'sanctum')->getJson($url)
        ->assertOk()->assertJsonCount(2, 'data.prices')
        ->assertJsonPath('data.prices.0.id', $prices['assigned']->id)
        ->assertJsonPath('data.prices.1.id', $prices['clinic']->id);
    $this->getJson($url.'/'.$prices['assigned']->id)->assertOk()->assertJsonPath('data.price', '1500.00');
    $this->getJson($url.'/'.$prices['unassigned']->id)->assertForbidden();
    $this->getJson($url.'/'.$prices['other clinic']->id)->assertForbidden();
    $this->getJson($url.'?business_id='.$otherClinic->id)->assertOk()->assertJsonCount(0, 'data.prices');
    $membership->update(['is_active' => false]);
    $this->getJson($url)->assertOk()->assertJsonCount(0, 'data.prices');

    $owner = User::factory()->create();
    $this->business->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);
    $this->actingAs($owner, 'sanctum')->getJson($url)->assertOk()->assertJsonCount(3, 'data.prices');
    $this->getJson($url.'/'.$prices['unassigned']->id)->assertOk();
    $this->getJson($url.'/'.$prices['mismatched branch']->id)->assertForbidden();
});

test('only assigned clinic staff can use the private package price for a branch conversation', function () {
    $package = $this->business->treatmentPackages()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Bakım paketi',
        'slug' => 'bakim-paketi',
        'price' => 25000,
        'currency' => 'TRY',
    ]);
    $package->treatments()->attach($this->treatment->id, ['quantity' => 2]);
    $this->business->treatmentPackages()->create(['name' => 'Boş paket', 'slug' => 'bos-paket']);
    $patient = User::factory()->create();
    $profile = PatientProfile::create(['user_id' => $patient->id, 'first_name' => 'Test', 'last_name' => 'Hasta']);
    $conversation = Conversation::create([
        'business_id' => $this->business->id,
        'branch_id' => $this->branch->id,
        'patient_profile_id' => $profile->id,
        'treatment_package_id' => $package->id,
        'subject' => 'Paket teklifi',
        'status' => 'open',
    ]);
    $offersUrl = "/api/conversations/{$conversation->id}/offers";
    $optionsUrl = $offersUrl.'/package-options';
    $payload = [
        'treatment_package_id' => $package->id,
        'title' => $package->name,
        'amount' => 23000,
        'currency' => 'TRY',
    ];

    $this->getJson($optionsUrl)->assertUnauthorized();
    $this->actingAs($patient, 'sanctum')->getJson($optionsUrl)->assertForbidden();
    $staff = User::factory()->create();
    $membership = $this->business->memberships()->create(['user_id' => $staff->id, 'role' => 'staff', 'is_active' => true]);
    $this->actingAs($staff, 'sanctum')->getJson($optionsUrl)->assertForbidden();
    $this->postJson($offersUrl, $payload)->assertForbidden();

    $membership->branches()->attach($this->branch->id, ['is_active' => true]);
    $this->getJson($optionsUrl)
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.packages', [[
            'id' => $package->id,
            'name' => $package->name,
            'price' => '25000.00',
            'currency' => 'TRY',
        ]]);
    $this->postJson($offersUrl, $payload)
        ->assertCreated()
        ->assertJsonPath('data.amount', '23000.00')
        ->assertJsonPath('data.package_snapshot.treatments.0.quantity', 2);
    $this->actingAs($patient, 'sanctum')->getJson($offersUrl)
        ->assertOk()->assertJsonPath('data.0.amount', '23000.00');
});
