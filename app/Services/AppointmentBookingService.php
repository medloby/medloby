<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\BranchAppointmentSetting;
use App\Models\Doctor;
use App\Models\Offer;
use App\Models\PatientProfile;
use App\Models\Treatment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AppointmentBookingService
{
    public function __construct(
        protected AppointmentAvailabilityService $availabilityService,
        protected AppointmentNotificationService $notificationService
    ) {}

    /**
     * Yeni randevu oluşturur.
     */
    public function book(
        int $businessId,
        int $branchId,
        PatientProfile $patient,
        Doctor $doctor,
        Treatment $treatment,
        Carbon $startsAt,
        string $source = 'medloby',
        bool $requireOnlineBookable = true,
        ?string $notes = null,
        ?Offer $offer = null,
        ?int $changedBy = null,
    ): Appointment {
        return $this->availabilityService->runBookingTransaction(function () use (
            $businessId, $branchId, $patient, $doctor, $treatment,
            $startsAt, $source, $requireOnlineBookable, $notes, $offer, $changedBy
        ) {
            if ($offer) {
                $offer = Offer::query()->lockForUpdate()->findOrFail($offer->id);
            }
            $doctor = Doctor::query()->lockForUpdate()->findOrFail($doctor->id);
            if ($offer) {
                if ($offer->package_snapshot !== null) {
                    throw new RuntimeException('Paket randevuları birlikte oluşturulmalıdır.');
                }
                if ($offer->appointments()->exists()) {
                    throw new RuntimeException('Bu teklif daha önce randevuya dönüştürülmüş.');
                }
            }

            $status = $requireOnlineBookable ? BranchAppointmentSetting::initialStatusForOnlineBooking($branchId) : 'pending';

            return $this->createBooking(
                $businessId, $branchId, $patient, $doctor, $treatment, $startsAt, $status,
                source: $source, requireOnlineBookable: $requireOnlineBookable, notes: $notes, offer: $offer, changedBy: $changedBy,
            );
        });
    }

    private function createBooking(
        int $businessId,
        int $branchId,
        PatientProfile $patient,
        Doctor $doctor,
        Treatment $treatment,
        Carbon $startsAt,
        string $status,
        string $source = 'medloby',
        bool $requireOnlineBookable = true,
        ?string $notes = null,
        ?Offer $offer = null,
        ?int $changedBy = null
    ): Appointment {
        if ($offer) {
            $this->validateOfferContext($offer, $businessId, $branchId, $patient, $treatment);
        }

        $this->validateBookingContext(
            $businessId,
            $branchId,
            $doctor,
            $treatment,
            $requireOnlineBookable
        );

        $startsAt = $startsAt->copy();

        $durationMinutes = $this->availabilityService
            ->getDurationMinutes(
                $doctor,
                $branchId,
                $treatment
            );

        if ($durationMinutes === null || $durationMinutes <= 0) {
            throw new RuntimeException(
                'Bu tedavi için geçerli bir süre tanımlanmamış.'
            );
        }

        $endsAt = $startsAt->copy()->addMinutes($durationMinutes);

        $isAvailable = $this->availabilityService->isAvailable(
            $doctor,
            $branchId,
            $treatment,
            $startsAt,
            null,
            $requireOnlineBookable
        );

        if (! $isAvailable) {
            throw new RuntimeException(
                'Seçilen tarih ve saat için randevu müsait değil.'
            );
        }

        $appointment = Appointment::create([
            'business_id' => $businessId,
            'branch_id' => $branchId,
            'patient_profile_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'treatment_id' => $treatment->id,
            'offer_id' => $offer?->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => $status,
            'source' => $source,
            'patient_name' => trim(
                $patient->first_name.' '.$patient->last_name
            ),
            'patient_phone' => $patient->phone,
            'patient_email' => $patient->user?->email,
            'notes' => $notes,
        ]);

        AppointmentStatusHistory::create([
            'appointment_id' => $appointment->id,
            'changed_by_user_id' => $changedBy,
            'old_status' => null,
            'new_status' => $appointment->status,
            'reason' => 'Randevu oluşturuldu.',
            'changed_at' => now(),
        ]);

        DB::afterCommit(function () use ($appointment) {
            $this->notificationService->notifyNewAppointment(
                $appointment->fresh([
                    'business',
                    'branch',
                    'patientProfile',
                    'doctor.person',
                    'treatment',
                    'offer',
                ])
            );
        });

        return $appointment->fresh([
            'business',
            'branch',
            'patientProfile',
            'doctor',
            'treatment',
            'offer',
            'statusHistories',
        ]);
    }

    public function bookPackage(Offer $offer, int $branchId, array $sessions, ?int $clinicUserId = null): array
    {
        return $this->availabilityService->runBookingTransaction(function () use ($offer, $branchId, $sessions, $clinicUserId) {
            $offer = Offer::query()->lockForUpdate()->findOrFail($offer->id);
            $doctors = Doctor::query()->whereKey(array_column($sessions, 'doctor_id'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($offer->appointments()->exists()) {
                throw new RuntimeException('Bu teklif daha önce randevuya dönüştürülmüş.');
            }

            $required = collect($offer->package_snapshot['treatments'] ?? [])
                ->pluck('quantity', 'treatment_id')->sortKeys()->all();
            $requested = collect($sessions)->countBy(fn (array $session) => (int) $session['treatment_id'])
                ->sortKeys()->all();

            if ($required === [] || $requested !== $required) {
                throw new RuntimeException('Paket teklifindeki her tedavi seansı için bir randevu seçilmelidir.');
            }

            $patient = $offer->patientProfile;
            if (! $patient) {
                throw new RuntimeException('Teklife bağlı hasta profili bulunamadı.');
            }

            $status = $clinicUserId === null ? BranchAppointmentSetting::initialStatusForOnlineBooking($branchId) : 'pending';
            $appointments = [];
            foreach ($sessions as $session) {
                $appointment = $this->createBooking(
                    $offer->business_id,
                    $branchId,
                    $patient,
                    $doctors->get($session['doctor_id']) ?? throw new RuntimeException('Geçerli bir doktor seçilmelidir.'),
                    Treatment::findOrFail($session['treatment_id']),
                    Carbon::parse($session['starts_at']),
                    $status,
                    source: $clinicUserId ? 'clinic' : 'medloby',
                    requireOnlineBookable: $clinicUserId === null,
                    notes: $session['notes'] ?? null,
                    offer: $offer,
                    changedBy: $clinicUserId,
                );

                foreach ($appointments as $previous) {
                    if ($appointment->starts_at->lt($previous->ends_at)
                        && $appointment->ends_at->gt($previous->starts_at)) {
                        throw new RuntimeException('Paket randevularının saatleri çakışamaz.');
                    }
                }

                $appointments[] = $appointment;
            }

            return $appointments;
        });
    }

    /**
     * Teklifin randevu oluşturma bağlamıyla uyumlu olduğunu doğrular.
     */
    protected function validateOfferContext(
        Offer $offer,
        int $businessId,
        int $branchId,
        PatientProfile $patient,
        Treatment $treatment
    ): void {
        if ($offer->status !== 'accepted') {
            throw new RuntimeException(
                'Randevu oluşturmak için teklifin kabul edilmiş olması gerekir.'
            );
        }

        if (! $offer->hasActiveClinic()) {
            throw new RuntimeException('Bu sağlık merkezi şu anda randevuya açık değil.');
        }

        if (
            $offer->valid_until &&
            $offer->valid_until->isPast()
        ) {
            throw new RuntimeException(
                'Bu teklifin geçerlilik süresi dolmuştur.'
            );
        }

        if ($offer->business_id !== $businessId) {
            throw new RuntimeException(
                'Teklif farklı bir işletmeye aittir.'
            );
        }

        $offerBranchId = $offer->package_snapshot['branch_id'] ?? $offer->branch_id;
        if ($offerBranchId !== null && $offerBranchId !== $branchId) {
            throw new RuntimeException(
                'Teklif farklı bir şubeye aittir.'
            );
        }

        if ($offer->patient_profile_id !== $patient->id) {
            throw new RuntimeException(
                'Teklif farklı bir hastaya aittir.'
            );
        }

        if (
            $offer->treatment_id !== null &&
            $offer->treatment_id !== $treatment->id
        ) {
            throw new RuntimeException(
                'Teklifteki tedavi ile seçilen tedavi uyuşmuyor.'
            );
        }

    }

    /**
     * Randevunun işletme, şube, doktor ve tedavi
     * bağlamının birbiriyle uyumlu olduğunu doğrular.
     */
    protected function validateBookingContext(
        int $businessId,
        int $branchId,
        Doctor $doctor,
        Treatment $treatment,
        bool $requireOnlineBookable
    ): void {
        $branch = DB::table('branches')
            ->join('businesses', 'businesses.id', '=', 'branches.business_id')
            ->where('branches.id', $branchId)
            ->where('branches.business_id', $businessId)
            ->first(['branches.status', 'businesses.status as business_status', 'businesses.is_verified']);

        if (! $branch) {
            throw new RuntimeException(
                'Seçilen şube bu işletmeye ait değil.'
            );
        }

        if ($branch->status !== 'active' || $branch->business_status !== 'active' || ! $branch->is_verified) {
            throw new RuntimeException($requireOnlineBookable
                ? 'Seçilen şube şu anda çevrimiçi randevuya açık değil.'
                : 'Seçilen şube şu anda randevuya açık değil.');
        }

        if ($requireOnlineBookable && ! $doctor->is_public) {
            throw new RuntimeException('Seçilen doktor çevrimiçi randevuya açık değil.');
        }

        $doctorBelongsToBusiness = DB::table('doctors')
            ->join(
                'people',
                'people.id',
                '=',
                'doctors.person_id'
            )
            ->where('doctors.id', $doctor->id)
            ->where('people.business_id', $businessId)
            ->exists();

        if (! $doctorBelongsToBusiness) {
            throw new RuntimeException(
                'Seçilen doktor bu işletmeye ait değil.'
            );
        }

        $doctorBelongsToBranch = DB::table('doctor_branch')
            ->where('doctor_id', $doctor->id)
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->exists();

        if (! $doctorBelongsToBranch) {
            throw new RuntimeException(
                'Seçilen doktor bu şubede aktif olarak çalışmıyor.'
            );
        }

        if (! $treatment->is_active) {
            throw new RuntimeException(
                'Seçilen tedavi aktif değil.'
            );
        }

        $branchTreatment = DB::table('branch_treatment')
            ->where('branch_id', $branchId)
            ->where('treatment_id', $treatment->id)
            ->first();

        if (! $branchTreatment) {
            throw new RuntimeException(
                'Seçilen tedavi bu şubede bulunmuyor.'
            );
        }

        if (! (bool) $branchTreatment->is_active) {
            throw new RuntimeException(
                'Seçilen tedavi bu şubede aktif değil.'
            );
        }

        if (
            $requireOnlineBookable &&
            ! (bool) $branchTreatment->is_online_bookable
        ) {
            throw new RuntimeException(
                'Seçilen tedavi bu şubede online randevuya açık değil.'
            );
        }
    }
}
