<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\Doctor;
use App\Models\Person;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('public contact links omit malformed values and contacts from inactive branches', function () {
    $clinic = Business::factory()->create([
        'website' => 'ftp://example.test/klinik', 'public_contact_fields' => ['website'],
    ]);
    Branch::factory()->for($clinic)->create([
        'phone' => 'Santralden sorunuz', 'public_contact_fields' => ['phone'],
    ]);
    Branch::factory()->for($clinic)->create([
        'status' => 'inactive', 'email' => 'pasif-sube@example.test', 'public_contact_fields' => ['email'],
    ]);

    $this->get(route('clinics.show', $clinic->slug))->assertOk()
        ->assertDontSee('ftp://example.test/klinik')->assertDontSeeText('Santralden sorunuz')->assertDontSeeText('pasif-sube@example.test');
});

test('the public clinic page links only to published web profiles of active branches', function () {
    $clinic = Business::factory()->create();
    $branch = Branch::factory()->for($clinic)->create(['name' => 'Merkez']);
    $inactiveBranch = Branch::factory()->for($clinic)->create(['status' => 'inactive']);
    $branch->socialMediaAccounts()->create([
        'platform' => 'instagram', 'page_name' => 'Merkez Instagram', 'profile_url' => 'https://example.test/merkez',
    ]);
    $branch->socialMediaAccounts()->create([
        'platform' => 'facebook', 'page_name' => 'Pasif bağlantı', 'profile_url' => 'https://example.test/pasif', 'is_active' => false,
    ]);
    $branch->socialMediaAccounts()->create([
        'platform' => 'legacy', 'page_name' => 'Eski kayıt', 'profile_url' => 'ftp://example.test/eski',
    ]);
    $inactiveBranch->socialMediaAccounts()->create([
        'platform' => 'instagram', 'page_name' => 'Pasif şube bağlantısı', 'profile_url' => 'https://example.test/pasif-sube',
    ]);

    $this->get(route('clinics.show', $clinic->slug))->assertOk()
        ->assertSeeText('Merkez Instagram')->assertSee('https://example.test/merkez')->assertSee('ugc noopener noreferrer')
        ->assertDontSeeText('Pasif bağlantı')->assertDontSeeText('Eski kayıt')->assertDontSeeText('Pasif şube bağlantısı');
});

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

test('treatment and location searches match the same active branch and tolerate Turkish keyboard spelling', function () {
    $clinic = Business::factory()->create(['name' => 'Örnek Sağlık']);
    $branch = Branch::factory()->for($clinic)->create(['name' => 'Merkez Şubesi', 'city' => 'İstanbul', 'district' => 'Şişli']);
    $otherBranch = Branch::factory()->for($clinic)->create(['name' => 'Avrupa Şubesi', 'city' => 'İstanbul', 'district' => 'Bakırköy']);
    $elsewhere = Business::factory()->create(['name' => 'Başka Konum Sağlık']);
    Branch::factory()->for($elsewhere)->create(['city' => 'İstanbul', 'district' => 'Şişli']);
    $wrongLocation = Branch::factory()->for($elsewhere)->create(['city' => 'İstanbul', 'district' => 'Bakırköy']);
    $closed = Business::factory()->create(['name' => 'Kaldırılan Tedavili Klinik']);
    $closedBranch = Branch::factory()->for($closed)->create(['city' => 'İstanbul', 'district' => 'Şişli']);
    $category = TreatmentCategory::factory()->create();
    $cleaning = Treatment::create([
        'treatment_category_id' => $category->id, 'name' => 'Diş Temizliği', 'slug' => 'dis-temizligi', 'is_active' => true,
    ]);
    $inactive = Treatment::create([
        'treatment_category_id' => $category->id, 'name' => 'Diş Temizliği Arşiv', 'slug' => 'dis-temizligi-arsiv', 'is_active' => false,
    ]);
    foreach ([$branch, $otherBranch, $wrongLocation] as $offering) {
        $offering->treatments()->attach($cleaning->id, ['is_active' => true]);
    }
    $closedBranch->treatments()->attach($cleaning->id, ['is_active' => false]);
    $closedBranch->treatments()->attach($inactive->id, ['is_active' => true]);
    $detail = route('clinics.show', $clinic->slug);

    $this->get(route('home', ['konum' => 'sisli', 'tedavi' => 'DIS TEMIZLIGI']))->assertOk()
        ->assertSeeText($clinic->name)->assertSeeText('Şişli / İstanbul')->assertSeeText($cleaning->name)
        ->assertSee($detail.'#sube-'.$branch->id)->assertDontSee($detail.'#sube-'.$otherBranch->id)
        ->assertDontSeeText($elsewhere->name)->assertDontSeeText($closed->name)->assertDontSeeText($inactive->name);
    $this->get(route('home', ['klinik' => 'ORNEK SAGLIK', 'konum' => 'istanbul', 'tedavi' => 'diş temizliği']))
        ->assertOk()->assertSee($detail.'#sube-'.$branch->id)->assertSee($detail.'#sube-'.$otherBranch->id);
    $this->get(route('home', ['tedavi' => '%']))->assertOk()->assertSeeText('Aramanıza uygun klinik bulunamadı.');
    $this->get($detail)->assertOk()->assertSee('id="sube-'.$branch->id.'"', false);
});

test('treatment and location filters stay in clinic search pagination', function () {
    $treatment = Treatment::create([
        'treatment_category_id' => TreatmentCategory::factory()->create()->id,
        'name' => 'Muayene', 'slug' => 'muayene', 'is_active' => true,
    ]);
    foreach (range(1, 10) as $number) {
        $clinic = Business::factory()->create(['name' => 'Sağlık Merkezi '.str_pad((string) $number, 2, '0', STR_PAD_LEFT)]);
        Branch::factory()->for($clinic)->create(['city' => 'İzmir'])->treatments()->attach($treatment->id, ['is_active' => true]);
    }

    $this->get(route('home', ['konum' => 'izmir', 'tedavi' => 'muayene']))->assertOk()->assertSeeText('Sağlık Merkezi 09')
        ->assertDontSeeText('Sağlık Merkezi 10')->assertSee('konum=izmir&amp;tedavi=muayene&amp;klinik_sayfa=2#klinikler', false);
    $this->get(route('home', ['konum' => 'izmir', 'tedavi' => 'muayene', 'klinik_sayfa' => 2]))
        ->assertOk()->assertSeeText('Sağlık Merkezi 10')->assertDontSeeText('Sağlık Merkezi 09');
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

test('the clinic directory lists only currently assigned public doctors and their treatments at that branch', function () {
    $this->travelTo(Carbon::parse('2026-12-04 22:00:00', 'UTC'));
    $clinic = Business::factory()->create();
    $branch = Branch::factory()->for($clinic)->create();
    $addDoctor = function (string $name, array $details = [], array $assignment = []) use ($clinic, $branch): Doctor {
        $person = Person::factory()->for($clinic)->for($branch)->create([
            'first_name' => $name, 'last_name' => 'Hekim', 'title' => 'Dr.', 'email' => strtolower($name).'@example.test',
        ]);
        $doctor = Doctor::factory()->for($person)->create(['is_public' => true, ...$details]);
        $doctor->branches()->attach($branch->id, ['status' => 'active', ...$assignment]);

        return $doctor;
    };
    $bookableDoctor = $addDoctor('Merkez', ['specialty' => 'Ortodonti', 'license_number' => 'KLINIK-RUHSAT-TEST-01'], [
        'start_date' => '2026-12-05', 'end_date' => '2026-12-05',
    ]);
    $offlineDoctor = $addDoctor('Danisma');
    $addDoctor('Gizli', ['is_public' => false]);
    $addDoctor('GoreviBitmis', [], ['end_date' => '2026-12-04']);
    $addDoctor('GoreviBaslamamis', [], ['start_date' => '2026-12-06']);
    $addDoctor('Pasif', ['status' => 'inactive']);
    $category = TreatmentCategory::factory()->create();
    $checkup = Treatment::create([
        'treatment_category_id' => $category->id, 'name' => 'Ortodonti muayenesi', 'slug' => 'ortodonti-muayenesi', 'is_active' => true,
    ]);
    $consultation = Treatment::create([
        'treatment_category_id' => $category->id, 'name' => 'Klinik danışmanlığı', 'slug' => 'klinik-danismanligi', 'is_active' => true,
    ]);
    $unrelated = Treatment::create([
        'treatment_category_id' => $category->id, 'name' => 'Şube dışında tedavi', 'slug' => 'sube-disinda-tedavi', 'is_active' => true,
    ]);
    $branch->treatments()->attach($checkup->id, ['is_active' => true, 'is_online_bookable' => true]);
    $branch->treatments()->attach($consultation->id, ['is_active' => true, 'is_online_bookable' => false]);
    $bookableDoctor->treatments()->attach([$checkup->id, $unrelated->id], ['is_active' => true]);
    $offlineDoctor->treatments()->attach($consultation->id, ['is_active' => true]);
    $bookingLink = route('package-offers').'#randevu-al/'.$branch->id;

    $this->get(route('clinics.show', $clinic->slug))->assertOk()
        ->assertSeeText('Dr. Merkez Hekim')->assertSeeText('Ortodonti')->assertSeeText($checkup->name)
        ->assertSeeText('Dr. Danisma Hekim')->assertDontSeeText($unrelated->name)
        ->assertDontSeeText('Gizli Hekim')->assertDontSeeText('GoreviBitmis Hekim')->assertDontSeeText('GoreviBaslamamis Hekim')->assertDontSeeText('Pasif Hekim')
        ->assertDontSeeText('KLINIK-RUHSAT-TEST-01')->assertDontSeeText('merkez@example.test')
        ->assertSee($bookingLink.'/'.$bookableDoctor->id)->assertDontSee($bookingLink.'/'.$offlineDoctor->id);
});

test('the clinic page hides the booking link when no public doctor has a remaining assignment', function () {
    $this->travelTo(Carbon::parse('2026-12-04 22:00:00', 'UTC'));
    $branch = Branch::factory()->create();
    $treatment = Treatment::create([
        'treatment_category_id' => TreatmentCategory::factory()->create()->id,
        'name' => 'Şube Muayenesi', 'slug' => 'sube-muayenesi', 'is_active' => true,
    ]);
    $branch->treatments()->attach($treatment->id, ['is_active' => true, 'is_online_bookable' => true]);
    $person = Person::factory()->for($branch->business)->for($branch)->create();
    $retiredDoctor = Doctor::factory()->for($person)->create();
    $retiredDoctor->branches()->attach($branch->id, ['status' => 'active', 'end_date' => '2026-12-04']);
    $retiredDoctor->treatments()->attach($treatment->id, ['is_active' => true]);

    $this->get(route('clinics.show', $branch->business->slug))->assertOk()
        ->assertSeeText('Çevrimiçi randevu kapalı.')
        ->assertDontSee(route('package-offers').'#randevu-al/'.$branch->id);
});
