<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\PlatformContract;
use App\Models\User;
use App\Notifications\ClinicOwnerCredentialsNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Queue\Events\JobQueueing;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

uses(RefreshDatabase::class);

function submitClinicAccountApplication(TestCase $test, string $email): User
{
    $contract = PlatformContract::create([
        'contract_type' => 'clinic_membership', 'title' => 'Test sözleşmesi', 'version' => 'pilot',
        'content' => 'Test sözleşmesi metni.', 'status' => 'published', 'is_required' => true, 'published_at' => now(),
    ]);
    $test->postJson('/api/register/business/application', [
        'owner_email' => $email, 'business_name' => 'Örnek Klinik', 'business_phone' => '+90 312 555 01 01',
        'platform_contract_id' => $contract->id, 'contract_accepted' => true,
    ])->assertCreated()->assertJsonPath('data.approval_status', 'pending')->assertJsonMissingPath('data.token');

    return User::query()->where('email', $email)->sole();
}

test('the clinic owner gets credentials only after approval and completes the clinic profile in CRM', function () {
    config(['app.url' => 'https://medloby.example.test', 'mail.default' => 'array', 'queue.default' => 'database']);
    $admin = User::factory()->create(['is_admin' => true]);
    $email = 'yeni-klinik@example.test';
    $owner = submitClinicAccountApplication($this, $email);
    $business = $owner->businessMemberships()->sole()->business;
    $branch = $business->branches()->sole();
    expect($owner->login_code)->toBeNull();
    expect($owner->tokens()->count())->toBe(0);
    $this->assertDatabaseCount('jobs', 0);
    $this->post(route('password.email'), ['email' => $email])->assertRedirectToRoute('password.request');
    $this->assertDatabaseCount('jobs', 0);

    $this->actingAs($admin, 'sanctum')->postJson("/api/admin/businesses/{$business->id}/approve")
        ->assertOk()->assertJsonMissingPath('data.password');
    $this->postJson("/api/admin/businesses/{$business->id}/approve")->assertUnprocessable();
    $loginCode = 'klinik-'.$business->id;
    $job = json_decode(DB::table('jobs')->sole()->payload, true, flags: JSON_THROW_ON_ERROR);
    expect(decrypt($job['data']['command']))->toContain(ClinicOwnerCredentialsNotification::class);
    Exceptions::fake();
    $transportUnavailable = true;
    Event::listen(MessageSending::class, function () use (&$transportUnavailable): void {
        if ($transportUnavailable) {
            throw new TransportException('Test mail transport unavailable');
        }
    });
    $workerOptions = ['--once' => true, '--tries' => 2, '--backoff' => 0];
    $this->artisan('queue:work', $workerOptions)->assertSuccessful();
    Exceptions::assertReported(TransportException::class);
    $this->assertDatabaseHas('jobs', ['attempts' => 1]);

    $transportUnavailable = false;
    $this->artisan('queue:work', $workerOptions)->assertSuccessful();
    $this->assertDatabaseCount('jobs', 0);
    $mail = Mail::mailer('array')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
    expect($mail->getTo()[0]->getAddress())->toBe($email);
    expect($mail->getTextBody())->toContain($loginCode);
    expect(preg_match('/Geçici şifreniz:\s+([A-Za-z0-9]{24})/u', $mail->getTextBody(), $match))->toBe(1);
    $temporaryPassword = $match[1];
    expect($owner->fresh()->password)->not->toBe($temporaryPassword);
    expect($owner->fresh()->hasVerifiedEmail())->toBeFalse();

    Auth::forgetGuards();
    $credentials = ['identifier' => $loginCode, 'password' => $temporaryPassword];
    $this->postJson('/api/login', $credentials)->assertOk()
        ->assertJsonPath('data.password_change_required', true)->assertJsonMissingPath('data.token');
    $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $owner->id]);
    $newPassword = ' sahibin-yeni-sifresi ';
    $token = $this->postJson('/api/login', $credentials + [
        'new_password' => $newPassword, 'new_password_confirmation' => $newPassword,
    ])->assertOk()->json('data.token');
    expect($owner->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->postJson('/api/login', $credentials)->assertUnprocessable();

    Auth::forgetGuards();
    $this->withToken($token)->getJson('/api/me')->assertOk()
        ->assertJsonPath('data.user.owner_crm_business_id', $business->id);
    $this->getJson("/api/businesses/{$business->id}/crm")->assertOk()
        ->assertJsonPath('data.can_manage_operations', true);
    $profile = "/api/businesses/{$business->id}/crm/profile";
    $this->getJson($profile.'?branch_id='.$branch->id)->assertOk()
        ->assertJsonPath('data.clinic.phone', '+90 312 555 01 01');
    $this->putJson($profile, [
        'branch_id' => $branch->id, 'owner_name' => 'Klinik Sahibi',
        'clinic' => ['name' => 'Örnek Ağız Sağlığı', 'country_code' => 'TR', 'city' => 'Ankara', 'description' => 'Klinik özellikleri.'],
        'branch' => ['name' => 'Merkez Şube', 'country_code' => 'TR', 'address' => 'Klinik adresi.'],
    ])->assertOk()->assertJsonPath('data.clinic.description', 'Klinik özellikleri.')
        ->assertJsonPath('data.branch.address', 'Klinik adresi.');
    $this->assertDatabaseHas('businesses', ['id' => $business->id, 'name' => 'Örnek Ağız Sağlığı', 'status' => 'active']);
    $this->assertDatabaseHas('branches', ['id' => $branch->id, 'name' => 'Merkez Şube']);
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'name' => 'Klinik Sahibi', 'email' => $email]);

    $this->postJson('/api/login', ['identifier' => $loginCode, 'password' => $newPassword])
        ->assertOk()->assertJsonPath('data.user.id', $owner->id);
});

test('clinic approval can be retried if its database queue cannot accept the credentials job', function () {
    config(['mail.default' => 'array', 'queue.default' => 'database']);
    $owner = submitClinicAccountApplication($this, 'bekleyen-klinik@example.test');
    $business = $owner->businessMemberships()->sole()->business;
    $admin = User::factory()->create(['is_admin' => true]);
    Exceptions::fake();
    $queueAvailable = false;
    Event::listen(JobQueueing::class, function () use (&$queueAvailable): void {
        if (! $queueAvailable) {
            throw new RuntimeException('Test database queue unavailable');
        }
    });

    $url = "/api/admin/businesses/{$business->id}/approve";
    $this->actingAs($admin, 'sanctum')->postJson($url)->assertServerError();

    expect($owner->refresh()->clinic_onboarding_pending)->toBeTrue()->and($owner->login_code)->toBeNull();
    expect($business->refresh()->status)->toBe('pending');
    $this->assertDatabaseCount('business_reviews', 0);
    $this->assertDatabaseCount('notifications', 0);
    $this->assertDatabaseCount('jobs', 0);

    $queueAvailable = true;
    $this->postJson($url)->assertOk();
    $this->assertDatabaseCount('business_reviews', 1);
    $this->assertDatabaseCount('notifications', 1);
    $this->assertDatabaseCount('jobs', 1);
});

test('a queued clinic credential message checks the owners current access before sending', function (array $businessChanges, array $membershipChanges) {
    config(['mail.default' => 'array', 'queue.default' => 'database']);
    $owner = submitClinicAccountApplication($this, 'bekleyen-bildirim@example.test');
    $membership = $owner->businessMemberships()->sole();
    $business = $membership->business;
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin, 'sanctum')->postJson("/api/admin/businesses/{$business->id}/approve")->assertOk();
    $this->assertDatabaseCount('jobs', 1);

    $business->update($businessChanges);
    $membership->update($membershipChanges);
    Business::factory()->create()->memberships()->create([
        'user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true,
    ]);

    $this->artisan('queue:work', ['--once' => true])->assertSuccessful();
    $this->assertDatabaseCount('jobs', 0);
    expect(Mail::mailer('array')->getSymfonyTransport()->messages())->toBeEmpty();
})->with([
    'the clinic was suspended' => [['status' => 'suspended'], []],
    'the ownership was deactivated' => [[], ['is_active' => false]],
    'the owner became staff' => [[], ['role' => 'staff']],
]);

test('a clinic owner creates and manages a branch-scoped staff login without an email address', function () {
    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->for($branch->business)->create();
    $foreignBranch = Branch::factory()->create();
    $owner = User::factory()->create();
    $branch->business->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);
    $foreignBranch->business->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);
    $operations = "/api/branches/{$branch->id}/operations";
    $account = ['name' => 'Deneme Personeli', 'login_code' => 'Merkez_Personel', 'password' => 'ilk-personel-sifresi'];

    $this->actingAs($owner, 'sanctum')->postJson($operations.'/staff/accounts', $account)->assertCreated();
    $this->postJson($operations.'/staff/accounts', $account)->assertUnprocessable()->assertJsonValidationErrors('login_code');
    $this->postJson($operations.'/staff/accounts', array_replace($account, ['login_code' => 'klinik-123']))
        ->assertUnprocessable()->assertJsonValidationErrors('login_code');
    $staff = User::query()->where('login_code', 'merkez_personel')->sole();
    expect($staff->email)->toBeNull();
    expect($staff->patientProfile()->exists())->toBeFalse();
    expect(Hash::check($account['password'], $staff->password))->toBeTrue();
    $membership = $staff->businessMemberships()->sole();
    $this->getJson($operations)->assertOk()->assertJsonPath('data.staff.0.login_code', 'merkez_personel');
    $this->putJson("/api/branches/{$foreignBranch->id}/operations/staff/{$membership->id}/password", ['password' => 'baska-klinik-sifresi'])
        ->assertNotFound();

    Auth::forgetGuards();
    $credentials = ['identifier' => 'Merkez_Personel', 'password' => $account['password']];
    $token = $this->postJson('/api/login', $credentials)->assertOk()->json('data.token');
    Auth::forgetGuards();
    $this->withToken($token)->getJson("/api/businesses/{$branch->business_id}/crm")
        ->assertOk()->assertJsonPath('data.can_manage_operations', false)->assertJsonCount(1, 'data.branches')
        ->assertJsonPath('data.branches.0.id', $branch->id);
    $this->getJson("/api/branches/{$otherBranch->id}/crm/dashboard")->assertForbidden();
    $this->getJson("/api/businesses/{$branch->business_id}/crm/profile?branch_id={$branch->id}")->assertForbidden();
    $this->postJson($operations.'/staff/accounts', $account)->assertForbidden();

    $this->actingAs($owner, 'sanctum')->putJson($operations.'/staff/'.$membership->id.'/password', ['password' => 'yenilenen-personel-sifresi'])
        ->assertOk();
    $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $staff->id]);
    $this->postJson('/api/login', $credentials)->assertUnprocessable();
    $this->postJson('/api/login', array_replace($credentials, ['password' => 'yenilenen-personel-sifresi']))->assertOk();
    $this->putJson($operations.'/staff/'.$membership->id, ['is_active' => false])->assertOk();
    $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $staff->id]);
    $this->postJson('/api/login', array_replace($credentials, ['password' => 'yenilenen-personel-sifresi']))->assertForbidden();
});

test('an expired clinic password can be replaced through verified email recovery', function () {
    $owner = User::factory()->unverified()->create([
        'login_code' => 'klinik-300', 'password' => 'gecici-test-sifresi',
        'temporary_password_expires_at' => now()->subMinute(),
    ]);

    $this->postJson('/api/login', ['identifier' => $owner->login_code, 'password' => 'gecici-test-sifresi'])
        ->assertUnprocessable()->assertJsonPath('errors.identifier.0', 'Geçici şifrenizin süresi doldu. Şifremi unuttum bağlantısını kullanın.');
    $this->post(route('password.update'), [
        'email' => $owner->email, 'token' => Password::createToken($owner),
        'password' => 'yeni-sifre-123', 'password_confirmation' => 'yeni-sifre-123',
    ])->assertRedirect(route('package-offers').'#gorusmeler');
    $this->assertDatabaseHas('users', ['id' => $owner->id, 'temporary_password_expires_at' => null]);
    expect($owner->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->postJson('/api/login', ['identifier' => $owner->login_code, 'password' => 'yeni-sifre-123'])->assertOk();
});
