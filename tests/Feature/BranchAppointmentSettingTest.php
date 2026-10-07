<?php

use App\Models\Branch;
use App\Models\BranchAppointmentSetting;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createBranchAppointmentSettingData(): array
{
    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $user = User::factory()->create();

    BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'role' => 'business_owner',
        'is_active' => true,
    ]);

    $branch = Branch::factory()->create([
        'business_id' => $business->id,
    ]);

    return [
        'business' => $business,
        'user' => $user,
        'branch' => $branch,
    ];
}

function appointmentSettingPayload(array $overrides = []): array
{
    return array_merge([
        'slot_interval_minutes' => 30,
        'minimum_booking_notice_minutes' => 60,
        'maximum_booking_days' => 90,
        'same_day_booking_enabled' => true,
        'online_booking_enabled' => true,
        'cancellation_enabled' => true,
        'cancellation_before_minutes' => 120,
        'rescheduling_enabled' => true,
        'rescheduling_before_minutes' => 120,
        'default_appointment_status' => 'pending',
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'is_active' => true,
    ], $overrides);
}

test('business user can view branch appointment settings', function () {
    $data = createBranchAppointmentSettingData();
    
    BranchAppointmentSetting::create(
        array_merge(
            [
                'branch_id' => $data['branch']->id,
            ],
            appointmentSettingPayload()
        )
    );

    $response = $this
        ->actingAs($data['user'])
        ->getJson(
            "/api/branches/{$data['branch']->id}/appointment-settings"
        );

    $response->assertOk();

    $response->assertJson([
        'success' => true,
        'data' => [
            'branch_id' => $data['branch']->id,
            'settings' => [
                'slot_interval_minutes' => 30,
                'minimum_booking_notice_minutes' => 60,
                'maximum_booking_days' => 90,
                'default_appointment_status' => 'pending',
            ],
        ],
    ]);
});

test('view returns default settings when branch has no appointment settings', function () {
    $data = createBranchAppointmentSettingData();

    $response = $this
        ->actingAs($data['user'])
        ->getJson(
            "/api/branches/{$data['branch']->id}/appointment-settings"
        );

    $response->assertOk();

    $response->assertJson([
        'success' => true,
        'data' => [
            'branch_id' => $data['branch']->id,
            'settings' => [
                'branch_id' => $data['branch']->id,
                'slot_interval_minutes' => 30,
                'minimum_booking_notice_minutes' => 0,
                'maximum_booking_days' => 90,
                'same_day_booking_enabled' => true,
                'online_booking_enabled' => true,
                'cancellation_enabled' => true,
                'cancellation_before_minutes' => 120,
                'rescheduling_enabled' => true,
                'rescheduling_before_minutes' => 120,
                'default_appointment_status' => 'pending',
                'buffer_before_minutes' => 0,
                'buffer_after_minutes' => 0,
                'is_active' => true,
            ],
        ],
    ]);
});

test('business user can create branch appointment settings', function () {
    $data = createBranchAppointmentSettingData();

    $payload = appointmentSettingPayload([
        'slot_interval_minutes' => 15,
        'maximum_booking_days' => 180,
        'default_appointment_status' => 'confirmed',
    ]);

    $response = $this
        ->actingAs($data['user'])
        ->postJson(
            "/api/branches/{$data['branch']->id}/appointment-settings",
            $payload
        );

    $response->assertCreated();

    $response->assertJson([
        'success' => true,
        'message' => 'Şube randevu ayarları başarıyla oluşturuldu.',
        'data' => [
            'branch_id' => $data['branch']->id,
            'slot_interval_minutes' => 15,
            'maximum_booking_days' => 180,
            'default_appointment_status' => 'confirmed',
        ],
    ]);

    $this->assertDatabaseHas('branch_appointment_settings', [
        'branch_id' => $data['branch']->id,
        'slot_interval_minutes' => 15,
        'maximum_booking_days' => 180,
        'default_appointment_status' => 'confirmed',
    ]);
});

test('business user cannot create duplicate branch appointment settings', function () {
    $data = createBranchAppointmentSettingData();

    BranchAppointmentSetting::create(
        array_merge(
            [
                'branch_id' => $data['branch']->id,
            ],
            appointmentSettingPayload()
        )
    );

    $response = $this
        ->actingAs($data['user'])
        ->postJson(
            "/api/branches/{$data['branch']->id}/appointment-settings",
            appointmentSettingPayload()
        );

    $response->assertStatus(409);

    $response->assertJson([
        'success' => false,
        'message' => 'Bu şube için randevu ayarları zaten mevcut.',
    ]);
});

test('business user can update existing branch appointment settings', function () {
    $data = createBranchAppointmentSettingData();

    BranchAppointmentSetting::create(
        array_merge(
            [
                'branch_id' => $data['branch']->id,
            ],
            appointmentSettingPayload()
        )
    );

    $response = $this
        ->actingAs($data['user'])
        ->putJson(
            "/api/branches/{$data['branch']->id}/appointment-settings",
            [
                'slot_interval_minutes' => 60,
                'online_booking_enabled' => false,
                'default_appointment_status' => 'confirmed',
            ]
        );

    $response->assertOk();

    $response->assertJson([
        'success' => true,
        'message' => 'Şube randevu ayarları başarıyla güncellendi.',
        'data' => [
            'slot_interval_minutes' => 60,
            'online_booking_enabled' => false,
            'default_appointment_status' => 'confirmed',
        ],
    ]);

    $this->assertDatabaseHas('branch_appointment_settings', [
        'branch_id' => $data['branch']->id,
        'slot_interval_minutes' => 60,
        'online_booking_enabled' => false,
        'default_appointment_status' => 'confirmed',
    ]);
});

test('partial update does not overwrite omitted settings', function () {
    $data = createBranchAppointmentSettingData();

    BranchAppointmentSetting::create(
        array_merge(
            [
                'branch_id' => $data['branch']->id,
            ],
            appointmentSettingPayload([
                'slot_interval_minutes' => 45,
                'maximum_booking_days' => 120,
            ])
        )
    );

    $response = $this
        ->actingAs($data['user'])
        ->putJson(
            "/api/branches/{$data['branch']->id}/appointment-settings",
            [
                'slot_interval_minutes' => 60,
            ]
        );

    $response->assertOk();

    $settings = BranchAppointmentSetting::where(
        'branch_id',
        $data['branch']->id
    )->first();

    expect($settings->slot_interval_minutes)->toBe(60);
    expect($settings->maximum_booking_days)->toBe(120);
});

test('update creates settings with defaults when settings do not exist', function () {
    $data = createBranchAppointmentSettingData();

    $response = $this
        ->actingAs($data['user'])
        ->putJson(
            "/api/branches/{$data['branch']->id}/appointment-settings",
            [
                'slot_interval_minutes' => 60,
            ]
        );

    $response->assertOk();

    $response->assertJson([
        'success' => true,
        'data' => [
            'branch_id' => $data['branch']->id,
            'slot_interval_minutes' => 60,
            'minimum_booking_notice_minutes' => 0,
            'maximum_booking_days' => 90,
            'same_day_booking_enabled' => true,
            'online_booking_enabled' => true,
            'default_appointment_status' => 'pending',
        ],
    ]);

    $this->assertDatabaseHas('branch_appointment_settings', [
        'branch_id' => $data['branch']->id,
        'slot_interval_minutes' => 60,
        'maximum_booking_days' => 90,
    ]);
});

test('business user can reset existing appointment settings to defaults', function () {
    $data = createBranchAppointmentSettingData();

    BranchAppointmentSetting::create(
        array_merge(
            [
                'branch_id' => $data['branch']->id,
            ],
            appointmentSettingPayload([
                'slot_interval_minutes' => 60,
                'minimum_booking_notice_minutes' => 500,
                'maximum_booking_days' => 365,
                'online_booking_enabled' => false,
                'default_appointment_status' => 'confirmed',
                'buffer_before_minutes' => 30,
                'buffer_after_minutes' => 30,
                'is_active' => false,
            ])
        )
    );

    $response = $this
        ->actingAs($data['user'])
        ->postJson(
            "/api/branches/{$data['branch']->id}/appointment-settings/reset"
        );

    $response->assertOk();

    $response->assertJson([
        'success' => true,
        'message' => 'Şube randevu ayarları varsayılan değerlere döndürüldü.',
        'data' => [
            'branch_id' => $data['branch']->id,
            'slot_interval_minutes' => 30,
            'minimum_booking_notice_minutes' => 0,
            'maximum_booking_days' => 90,
            'same_day_booking_enabled' => true,
            'online_booking_enabled' => true,
            'cancellation_enabled' => true,
            'cancellation_before_minutes' => 120,
            'rescheduling_enabled' => true,
            'rescheduling_before_minutes' => 120,
            'default_appointment_status' => 'pending',
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'is_active' => true,
        ],
    ]);
});

test('reset creates default settings when settings do not exist', function () {
    $data = createBranchAppointmentSettingData();

    $response = $this
        ->actingAs($data['user'])
        ->postJson(
            "/api/branches/{$data['branch']->id}/appointment-settings/reset"
        );

    $response->assertOk();

    $response->assertJson([
        'success' => true,
        'data' => [
            'branch_id' => $data['branch']->id,
            'slot_interval_minutes' => 30,
            'minimum_booking_notice_minutes' => 0,
            'maximum_booking_days' => 90,
            'default_appointment_status' => 'pending',
        ],
    ]);

    $this->assertDatabaseHas('branch_appointment_settings', [
        'branch_id' => $data['branch']->id,
        'slot_interval_minutes' => 30,
        'maximum_booking_days' => 90,
        'default_appointment_status' => 'pending',
    ]);
});

test('user from another business cannot access branch appointment settings', function () {
    $data = createBranchAppointmentSettingData();

    $otherBusiness = Business::factory()->create();

    $otherUser = User::factory()->create();

    BusinessUser::create([
        'business_id' => $otherBusiness->id,
        'user_id' => $otherUser->id,
        'role' => 'business_owner',
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($otherUser)
        ->getJson(
            "/api/branches/{$data['branch']->id}/appointment-settings"
        );

    $response->assertForbidden();
});

test('inactive business membership cannot access branch appointment settings', function () {
    $data = createBranchAppointmentSettingData();

    $data['user']
        ->businessMemberships()
        ->update([
            'is_active' => false,
        ]);

    $response = $this
        ->actingAs($data['user'])
        ->getJson(
            "/api/branches/{$data['branch']->id}/appointment-settings"
        );

    $response->assertForbidden();
});

test('unrelated authenticated user cannot access branch appointment settings', function () {
    $data = createBranchAppointmentSettingData();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson(
            "/api/branches/{$data['branch']->id}/appointment-settings"
        );

    $response->assertForbidden();
});

test('unauthenticated user cannot access branch appointment settings', function () {
    $data = createBranchAppointmentSettingData();

    $this
        ->getJson(
            "/api/branches/{$data['branch']->id}/appointment-settings"
        )
        ->assertUnauthorized();
});

test('business user without branch access cannot access appointment settings', function () {
    $data = createBranchAppointmentSettingData();

    $anotherBranch = Branch::factory()->create([
        'business_id' => $data['business']->id,
    ]);

    $restrictedUser = User::factory()->create();

$restrictedBusinessUser = BusinessUser::create([
    'business_id' => $data['business']->id,
    'user_id' => $restrictedUser->id,
    'role' => 'staff',
    'is_active' => true,
]);

$restrictedBusinessUser->branches()->attach(
    $data['branch']->id,
    [
        'is_active' => true,
    ]
);

    $response = $this
    ->actingAs($restrictedUser)
        ->getJson(
            "/api/branches/{$anotherBranch->id}/appointment-settings"
        );

    $response->assertForbidden();
});

test('store validation rejects invalid slot interval', function () {
    $data = createBranchAppointmentSettingData();

    $response = $this
        ->actingAs($data['user'])
        ->postJson(
            "/api/branches/{$data['branch']->id}/appointment-settings",
            appointmentSettingPayload([
                'slot_interval_minutes' => 0,
            ])
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'slot_interval_minutes',
    ]);
});

test('store validation rejects slot interval above maximum', function () {
    $data = createBranchAppointmentSettingData();

    $response = $this
        ->actingAs($data['user'])
        ->postJson(
            "/api/branches/{$data['branch']->id}/appointment-settings",
            appointmentSettingPayload([
                'slot_interval_minutes' => 1441,
            ])
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'slot_interval_minutes',
    ]);
});

test('store validation rejects invalid maximum booking days', function () {
    $data = createBranchAppointmentSettingData();

    $response = $this
        ->actingAs($data['user'])
        ->postJson(
            "/api/branches/{$data['branch']->id}/appointment-settings",
            appointmentSettingPayload([
                'maximum_booking_days' => 0,
            ])
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'maximum_booking_days',
    ]);
});

test('store validation rejects maximum booking days above maximum', function () {
    $data = createBranchAppointmentSettingData();

    $response = $this
        ->actingAs($data['user'])
        ->postJson(
            "/api/branches/{$data['branch']->id}/appointment-settings",
            appointmentSettingPayload([
                'maximum_booking_days' => 3651,
            ])
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'maximum_booking_days',
    ]);
});

test('store validation rejects invalid default appointment status', function () {
    $data = createBranchAppointmentSettingData();

    $response = $this
        ->actingAs($data['user'])
        ->postJson(
            "/api/branches/{$data['branch']->id}/appointment-settings",
            appointmentSettingPayload([
                'default_appointment_status' => 'cancelled',
            ])
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'default_appointment_status',
    ]);
});

test('store validation rejects missing required settings', function () {
    $data = createBranchAppointmentSettingData();

    $response = $this
        ->actingAs($data['user'])
        ->postJson(
            "/api/branches/{$data['branch']->id}/appointment-settings",
            []
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'slot_interval_minutes',
        'minimum_booking_notice_minutes',
        'maximum_booking_days',
        'same_day_booking_enabled',
        'online_booking_enabled',
        'cancellation_enabled',
        'cancellation_before_minutes',
        'rescheduling_enabled',
        'rescheduling_before_minutes',
        'default_appointment_status',
        'buffer_before_minutes',
        'buffer_after_minutes',
        'is_active',
    ]);
});

test('update accepts partial settings payload', function () {
    $data = createBranchAppointmentSettingData();

    BranchAppointmentSetting::create(
        array_merge(
            [
                'branch_id' => $data['branch']->id,
            ],
            appointmentSettingPayload()
        )
    );

    $response = $this
        ->actingAs($data['user'])
        ->putJson(
            "/api/branches/{$data['branch']->id}/appointment-settings",
            [
                'cancellation_enabled' => false,
            ]
        );

    $response->assertOk();

    $this->assertDatabaseHas('branch_appointment_settings', [
        'branch_id' => $data['branch']->id,
        'cancellation_enabled' => false,
    ]);
});

test('boolean settings reject invalid boolean values', function () {
    $data = createBranchAppointmentSettingData();

    $response = $this
        ->actingAs($data['user'])
        ->postJson(
            "/api/branches/{$data['branch']->id}/appointment-settings",
            appointmentSettingPayload([
                'online_booking_enabled' => 'not-a-boolean',
            ])
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'online_booking_enabled',
    ]);
});

test('minimum booking notice cannot exceed maximum allowed value', function () {
    $data = createBranchAppointmentSettingData();

    $response = $this
        ->actingAs($data['user'])
        ->postJson(
            "/api/branches/{$data['branch']->id}/appointment-settings",
            appointmentSettingPayload([
                'minimum_booking_notice_minutes' => 525601,
            ])
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'minimum_booking_notice_minutes',
    ]);
});

test('cancellation before minutes cannot exceed maximum allowed value', function () {
    $data = createBranchAppointmentSettingData();

    $response = $this
        ->actingAs($data['user'])
        ->postJson(
            "/api/branches/{$data['branch']->id}/appointment-settings",
            appointmentSettingPayload([
                'cancellation_before_minutes' => 525601,
            ])
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'cancellation_before_minutes',
    ]);
});

test('rescheduling before minutes cannot exceed maximum allowed value', function () {
    $data = createBranchAppointmentSettingData();

    $response = $this
        ->actingAs($data['user'])
        ->postJson(
            "/api/branches/{$data['branch']->id}/appointment-settings",
            appointmentSettingPayload([
                'rescheduling_before_minutes' => 525601,
            ])
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'rescheduling_before_minutes',
    ]);
});

test('buffer before minutes cannot exceed maximum allowed value', function () {
    $data = createBranchAppointmentSettingData();

    $response = $this
        ->actingAs($data['user'])
        ->postJson(
            "/api/branches/{$data['branch']->id}/appointment-settings",
            appointmentSettingPayload([
                'buffer_before_minutes' => 1441,
            ])
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'buffer_before_minutes',
    ]);
});

test('buffer after minutes cannot exceed maximum allowed value', function () {
    $data = createBranchAppointmentSettingData();

    $response = $this
        ->actingAs($data['user'])
        ->postJson(
            "/api/branches/{$data['branch']->id}/appointment-settings",
            appointmentSettingPayload([
                'buffer_after_minutes' => 1441,
            ])
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'buffer_after_minutes',
    ]);
});