<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchAppointmentSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchAppointmentSettingController extends Controller
{
    /**
     * Şubenin randevu ayarlarını getirir.
     *
     * Ayar kaydı yoksa varsayılan ayarları döndürür.
     */
    public function show(
        Request $request,
        Branch $branch
    ): JsonResponse {
        $this->ensureBusinessBranchAccess(
            $request,
            $branch
        );

        $settings = $branch->appointmentSetting;

        if (! $settings) {
            $settings = $this->defaultSettings($branch);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'branch_id' => $branch->id,
                'settings' => $settings,
            ],
        ]);
    }

    /**
     * Şube için ilk randevu ayarlarını oluşturur.
     */
    public function store(
        Request $request,
        Branch $branch
    ): JsonResponse {
        $this->ensureBusinessBranchAccess(
            $request,
            $branch
        );

        if ($branch->appointmentSetting) {
            return response()->json([
                'success' => false,
                'message' => 'Bu şube için randevu ayarları zaten mevcut.',
            ], 409);
        }

        $validated = $this->validateSettings($request);

        $settings = BranchAppointmentSetting::create([
            'branch_id' => $branch->id,
            ...$validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Şube randevu ayarları başarıyla oluşturuldu.',
            'data' => $settings,
        ], 201);
    }

    /**
     * Şube randevu ayarlarını günceller.
     */
    public function update(
        Request $request,
        Branch $branch
    ): JsonResponse {
        $this->ensureBusinessBranchAccess(
            $request,
            $branch
        );

        $validated = $this->validateSettings(
            $request,
            true
        );

        $settings = $branch->appointmentSetting;

        if (! $settings) {
            $settings = BranchAppointmentSetting::create([
                'branch_id' => $branch->id,
                ...$this->defaultSettings($branch, false),
                ...$validated,
            ]);
        } else {
            $settings->update($validated);
        }

        return response()->json([
            'success' => true,
            'message' => 'Şube randevu ayarları başarıyla güncellendi.',
            'data' => $settings->fresh(),
        ]);
    }

    /**
     * Ayarları sistem varsayılanlarına döndürür.
     */
    public function reset(
        Request $request,
        Branch $branch
    ): JsonResponse {
        $this->ensureBusinessBranchAccess(
            $request,
            $branch
        );

        $defaults = $this->defaultSettings(
            $branch,
            false
        );

        $settings = $branch->appointmentSetting;

        if (! $settings) {
            $settings = BranchAppointmentSetting::create([
                'branch_id' => $branch->id,
                ...$defaults,
            ]);
        } else {
            $settings->update($defaults);
        }

        return response()->json([
            'success' => true,
            'message' => 'Şube randevu ayarları varsayılan değerlere döndürüldü.',
            'data' => $settings->fresh(),
        ]);
    }

    /**
     * Randevu ayarlarını doğrular.
     */
    private function validateSettings(
        Request $request,
        bool $partial = false
    ): array {
        $prefix = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'slot_interval_minutes' => [
                $prefix,
                'integer',
                'min:1',
                'max:1440',
            ],

            'minimum_booking_notice_minutes' => [
                $prefix,
                'integer',
                'min:0',
                'max:525600',
            ],

            'maximum_booking_days' => [
                $prefix,
                'integer',
                'min:1',
                'max:3650',
            ],

            'same_day_booking_enabled' => [
                $prefix,
                'boolean',
            ],

            'online_booking_enabled' => [
                $prefix,
                'boolean',
            ],

            'cancellation_enabled' => [
                $prefix,
                'boolean',
            ],

            'cancellation_before_minutes' => [
                $prefix,
                'integer',
                'min:0',
                'max:525600',
            ],

            'rescheduling_enabled' => [
                $prefix,
                'boolean',
            ],

            'rescheduling_before_minutes' => [
                $prefix,
                'integer',
                'min:0',
                'max:525600',
            ],

            'default_appointment_status' => [
                $prefix,
                'string',
                Rule::in([
                    'pending',
                    'confirmed',
                ]),
            ],

            'buffer_before_minutes' => [
                $prefix,
                'integer',
                'min:0',
                'max:1440',
            ],

            'buffer_after_minutes' => [
                $prefix,
                'integer',
                'min:0',
                'max:1440',
            ],

            'is_active' => [
                $prefix,
                'boolean',
            ],
        ]);
    }

    /**
     * Varsayılan şube randevu ayarlarını döndürür.
     */
    private function defaultSettings(
        Branch $branch,
        bool $asModel = true
    ): BranchAppointmentSetting|array {
        $defaults = [
            'branch_id' => $branch->id,
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
        ];

        if (! $asModel) {
            unset($defaults['branch_id']);
            return $defaults;
        }

        return new BranchAppointmentSetting($defaults);
    }

    /**
     * Kullanıcının işletme ve şube erişimini kontrol eder.
     */
    private function ensureBusinessBranchAccess(
        Request $request,
        Branch $branch
    ): void {
        $user = $request->user();

        $isBusinessUser = $user->businessMemberships()
            ->where('business_id', $branch->business_id)
            ->where('is_active', true)
            ->exists();

        if (! $isBusinessUser) {
            abort(
                403,
                'Bu işletmeye erişim yetkiniz yok.'
            );
        }

        if (! $user->hasBusinessBranchAccess(
            (int) $branch->business_id,
            (int) $branch->id
        )) {
            abort(
                403,
                'Bu şubeye erişim yetkiniz yok.'
            );
        }
    }
}