<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\Doctor;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->branch = Branch::factory()->for($this->business)->create();
    $this->second = Branch::factory()->for($this->business)->create();
    $this->owner = User::factory()->create();
    $this->business->memberships()->create([
        'user_id' => $this->owner->id, 'role' => 'business_owner', 'is_active' => true,
    ]);
    $this->operations = "/api/branches/{$this->branch->id}/operations";
});

test('an owner can grant and revoke only the selected branch of a verified staff account', function () {
    $person = User::factory()->create(['email' => 'personel@example.test']);
    $unverified = User::factory()->unverified()->create();
    $crm = "/api/businesses/{$this->business->id}/crm";
    $staffWorkspaces = '/api/clinic-crm/staff-workspaces';

    $this->getJson($this->operations)->assertUnauthorized();
    $this->actingAs($this->owner, 'sanctum')
        ->postJson($this->operations.'/staff', ['email' => $unverified->email])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->postJson($this->operations.'/staff', ['email' => 'PERSONEL@example.test', 'role' => 'business_owner'])->assertOk();
    $this->postJson($this->operations.'/staff', ['email' => $person->email])->assertOk();
    $membership = $person->businessMemberships()->sole();
    expect($membership->role)->toBe('staff');
    expect($membership->branches()->count())->toBe(1);

    $this->getJson($this->operations)->assertOk()
        ->assertJsonPath('data.staff.0.branch_access', true)
        ->assertHeader('Cache-Control', 'no-store, private');
    $this->actingAs($person, 'sanctum')->getJson($staffWorkspaces)
        ->assertOk()->assertJsonPath('data.0.id', $this->business->id);
    $this->getJson($crm)->assertOk()->assertJsonPath('data.can_manage_operations', false)
        ->assertJsonCount(1, 'data.branches')->assertJsonPath('data.branches.0.id', $this->branch->id);
    $this->getJson("/api/branches/{$this->branch->id}/crm/dashboard")->assertOk();
    $this->getJson("/api/branches/{$this->second->id}/crm/dashboard")->assertForbidden();
    $this->getJson($this->operations)->assertForbidden();
    $this->postJson($this->operations.'/staff', ['email' => $unverified->email])->assertForbidden();

    $this->actingAs($this->owner, 'sanctum')
        ->putJson($this->operations.'/staff/'.$membership->id, ['is_active' => false])->assertOk();
    $this->assertDatabaseHas('business_user_branch', [
        'business_user_id' => $membership->id, 'branch_id' => $this->branch->id, 'is_active' => false,
    ]);
    $this->actingAs($person, 'sanctum')->getJson($staffWorkspaces)->assertOk()->assertJsonCount(0, 'data');
    $this->getJson($crm)->assertForbidden();
});

test('a clinic cannot modify staff from another clinic or reactivate a suspended membership', function () {
    $foreign = Business::factory()->create()->memberships()->create([
        'user_id' => User::factory()->create()->id, 'role' => 'staff', 'is_active' => true,
    ]);
    $inactive = $this->business->memberships()->create([
        'user_id' => User::factory()->create()->id, 'role' => 'staff', 'is_active' => false,
    ]);

    $this->actingAs($this->owner, 'sanctum')
        ->putJson($this->operations.'/staff/'.$foreign->id, ['is_active' => true])->assertNotFound();
    $this->putJson($this->operations.'/staff/'.$foreign->id.'/permissions', ['permissions' => ['appointments.create']])->assertNotFound();
    $this->postJson($this->operations.'/staff', ['email' => $inactive->user->email])->assertUnprocessable();
    expect($inactive->fresh()->is_active)->toBeFalse();
    $this->business->update(['status' => 'suspended']);
    $this->getJson($this->operations)->assertForbidden();
});

test('the operations workspace lets branch catalog editors manage treatments with limited data and actions', function () {
    $staff = User::factory()->create();
    $member = $this->business->memberships()->create(['user_id' => $staff->id, 'role' => 'staff', 'is_active' => true]);
    $member->branches()->attach([$this->branch->id, $this->second->id]);
    $doctor = Doctor::factory()->create();
    $treatment = Treatment::create([
        'treatment_category_id' => TreatmentCategory::factory()->create()->id,
        'name' => 'Şube tedavisi', 'slug' => 'sube-tedavisi', 'is_active' => true, 'duration_minutes' => 30,
    ]);
    $permissions = $this->operations.'/staff/'.$member->id.'/permissions';
    $dashboard = "/api/branches/{$this->branch->id}/crm/dashboard";
    $secondOperations = "/api/branches/{$this->second->id}/operations";

    $this->actingAs($staff, 'sanctum')->getJson($dashboard)->assertOk()->assertJsonPath('data.can_manage_branch_catalog', false);
    $this->actingAs($this->owner, 'sanctum')->putJson($permissions, ['permissions' => ['branches.manage']])->assertOk();

    $this->actingAs($staff, 'sanctum')->getJson($dashboard)->assertOk()->assertJsonPath('data.can_manage_branch_catalog', true);
    $this->getJson("/api/branches/{$this->second->id}/crm/dashboard")->assertOk()->assertJsonPath('data.can_manage_branch_catalog', false);
    $this->getJson($this->operations)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.can_manage_operations', false)
        ->assertJsonMissingPath('data.staff')->assertJsonMissingPath('data.doctors')->assertJsonMissingPath('data.branch_permission_options')
        ->assertJsonPath('data.catalog_treatments', [['id' => $treatment->id, 'name' => $treatment->name]]);
    $this->getJson($secondOperations)->assertForbidden();
    $this->postJson($secondOperations.'/treatments', ['treatment_id' => $treatment->id])->assertForbidden();
    $this->postJson($this->operations.'/staff/accounts', [])->assertForbidden();
    $this->putJson($permissions, ['permissions' => []])->assertForbidden();
    $this->putJson($this->operations.'/doctors/'.$doctor->id, ['is_active' => true])->assertForbidden();

    $this->postJson($this->operations.'/treatments', ['treatment_id' => $treatment->id])->assertCreated();
    $path = $this->operations.'/treatments/'.$treatment->id;
    $this->putJson($path, ['duration_minutes' => 45, 'is_online_bookable' => true])->assertOk();
    $this->getJson($this->operations)->assertOk()->assertJsonPath('data.branch_treatments.0.duration_minutes', 45)
        ->assertJsonPath('data.branch_treatments.0.is_online_bookable', true);

    $this->actingAs($this->owner, 'sanctum')->putJson($permissions, ['permissions' => []])->assertOk();
    $this->actingAs($staff, 'sanctum')->getJson($dashboard)->assertOk()->assertJsonPath('data.can_manage_branch_catalog', false);
    $this->getJson($this->operations)->assertForbidden();
    $this->putJson($path, ['duration_minutes' => 90])->assertForbidden();
    $this->assertDatabaseHas('branch_treatment', ['branch_id' => $this->branch->id, 'treatment_id' => $treatment->id, 'duration_minutes' => 45]);
    $this->assertDatabaseMissing('branch_treatment', ['branch_id' => $this->second->id, 'treatment_id' => $treatment->id]);
});

test('an owner can register a private doctor and connect active branch treatments', function () {
    $this->actingAs($this->owner, 'sanctum');
    $profile = [
        'first_name' => 'Deneme',
        'last_name' => 'Hekim',
        'specialty' => 'Diş hekimliği',
        'license_number' => 'TEST-42',
    ];
    $created = $this->postJson($this->operations.'/doctors', $profile)->assertCreated();
    $doctor = Doctor::findOrFail($created->json('data.id'));
    expect($doctor->is_public)->toBeFalse();
    expect($doctor->person->business_id)->toBe($this->business->id);
    $this->putJson("/api/branches/{$this->second->id}/operations/doctors/{$doctor->id}/online-booking", ['is_public' => true])
        ->assertUnprocessable();
    $this->postJson("/api/branches/{$this->second->id}/operations/doctors", $profile)
        ->assertUnprocessable()->assertJsonValidationErrors('license_number');

    $treatment = Treatment::create([
        'treatment_category_id' => TreatmentCategory::factory()->create()->id,
        'name' => 'Muayene', 'slug' => 'muayene-operasyon', 'is_active' => true,
    ]);
    $doctorPath = $this->operations.'/doctors/'.$doctor->id;
    $link = $doctorPath.'/treatments/'.$treatment->id;
    $this->putJson($link, ['is_active' => true])->assertUnprocessable();
    $this->postJson($this->operations.'/treatments', [
        'treatment_id' => $treatment->id, 'is_online_bookable' => true,
    ])->assertCreated();
    $this->putJson($this->operations.'/treatments/'.$treatment->id, ['duration_minutes' => 30])->assertOk();

    $this->putJson($link, ['is_active' => true])->assertOk();
    $this->getJson($this->operations)->assertOk()
        ->assertJsonPath('data.doctors.0.in_branch', true)
        ->assertJsonPath('data.doctors.0.treatment_ids', [$treatment->id])
        ->assertJsonPath('data.branch_treatments.0.is_online_bookable', true)
        ->assertJsonPath('data.branch_treatments.0.duration_minutes', 30);

    $this->putJson($link, ['is_active' => false])->assertOk();
    $this->assertDatabaseHas('doctor_treatment', ['doctor_id' => $doctor->id, 'treatment_id' => $treatment->id, 'is_active' => false]);
    $this->putJson($doctorPath, ['is_active' => false])->assertOk();
    $this->putJson($link, ['is_active' => true])->assertUnprocessable();
    $this->putJson("/api/branches/{$this->second->id}/operations/doctors/{$doctor->id}", ['is_active' => true])->assertOk();
    $this->assertDatabaseHas('doctor_branch', ['doctor_id' => $doctor->id, 'branch_id' => $this->branch->id, 'status' => 'inactive']);
    $this->assertDatabaseHas('doctor_branch', ['doctor_id' => $doctor->id, 'branch_id' => $this->second->id, 'status' => 'active']);

    $foreignBranch = Branch::factory()->create();
    $foreignOwner = User::factory()->create();
    $foreignBranch->business->memberships()->create([
        'user_id' => $foreignOwner->id, 'role' => 'business_owner', 'is_active' => true,
    ]);
    $this->actingAs($foreignOwner, 'sanctum')
        ->putJson("/api/branches/{$foreignBranch->id}/operations/doctors/{$doctor->id}", ['is_active' => true])->assertNotFound();
    $this->putJson("/api/branches/{$foreignBranch->id}/operations/doctors/{$doctor->id}/online-booking", ['is_public' => true])
        ->assertNotFound();
});
