<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\Doctor;
use App\Models\Person;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests can search the home page by clinic or branch name and location', function () {
    $clinic = Business::factory()->create(['name' => 'Medloby Örnek Klinik']);
    $primary = Branch::factory()->for($clinic)->create(['name' => 'Merkez Şubesi', 'city' => 'İstanbul', 'district' => 'Kadıköy']);
    Branch::factory()->for($clinic)->create(['name' => 'Avrupa Şubesi', 'city' => 'İstanbul', 'district' => 'Bakırköy']);
    $suspended = Business::factory()->create(['name' => 'Askıdaki Klinik', 'status' => 'suspended']);
    $unverified = Business::factory()->create(['name' => 'Onaysız Klinik', 'is_verified' => false]);
    $inactive = Business::factory()->create(['name' => 'Gizli Şubeli Klinik']);
    Branch::factory()->for($suspended)->create(['city' => $primary->city]);
    Branch::factory()->for($unverified)->create(['city' => $primary->city]);
    Branch::factory()->for($inactive)->create(['city' => $primary->city, 'status' => 'inactive']);

    $this->get(route('home', ['konum' => 'İstanbul']))->assertOk()
        ->assertSee(route('clinics.show', $clinic->slug))->assertSeeText('Kadıköy / İstanbul')
        ->assertDontSeeText($suspended->name)->assertDontSeeText($unverified->name)->assertDontSeeText($inactive->name);
    $this->get(route('home', ['klinik' => 'Merkez', 'konum' => 'Kadıköy']))->assertOk()->assertSeeText($clinic->name);
    $this->get(route('home', ['klinik' => 'Merkez', 'konum' => 'Bakırköy']))->assertOk()->assertDontSeeText($clinic->name);
    $this->get(route('clinics.show', $suspended->slug))->assertNotFound();
    $this->get(route('clinics.show', $unverified->slug))->assertNotFound();
    $this->get(route('clinics.show', $inactive->slug))->assertNotFound();
});

test('the chosen clinic shows active branch details and links bookings to the bookable branch only', function () {
    $clinic = Business::factory()->create(['name' => 'Deneme Sağlık', 'email' => 'private@example.test']);
    $branch = Branch::factory()->for($clinic)->create(['name' => 'Anadolu Şubesi', 'address' => 'Deneme Sokak 1']);
    $offline = Branch::factory()->for($clinic)->create(['name' => 'Avrupa Şubesi']);
    $inactive = Branch::factory()->for($clinic)->create(['name' => 'Gizli Şube', 'status' => 'inactive']);
    $doctor = Doctor::factory()->for(Person::factory()->for($clinic)->for($branch))->create(['is_public' => true]);
    $treatment = Treatment::create([
        'treatment_category_id' => TreatmentCategory::factory()->create()->id,
        'name' => 'Ağız ve Diş Muayenesi', 'slug' => 'agiz-ve-dis-muayenesi', 'is_active' => true, 'duration_minutes' => 30,
    ]);
    $doctor->treatments()->attach($treatment->id, ['is_active' => true]);
    foreach ([$branch, $offline] as $clinicBranch) {
        $doctor->branches()->attach($clinicBranch->id, ['status' => 'active']);
        $clinicBranch->treatments()->attach($treatment->id, ['is_active' => true, 'is_online_bookable' => true]);
    }
    $offline->appointmentSetting()->create(['is_active' => true, 'online_booking_enabled' => false]);

    $this->get(route('clinics.show', $clinic->slug))->assertOk()
        ->assertSeeText($clinic->name)->assertSeeText($branch->name)->assertSeeText($branch->address)
        ->assertSeeText($treatment->name)->assertSeeText($offline->name)->assertDontSeeText($inactive->name)
        ->assertDontSeeText($clinic->email)
        ->assertSee(route('package-offers').'#randevu-al/'.$branch->id)
        ->assertDontSee(route('package-offers').'#randevu-al/'.$offline->id);
    $this->get(route('package-offers'))->assertOk()->assertDontSee('patient-booking-button');
});
