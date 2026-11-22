<div class="grid min-w-0 gap-3" data-calendar-booking hidden>
    <details class="rounded-xl border border-slate-200 p-4 dark:border-slate-700" data-booking-individual>
        <summary class="cursor-pointer font-semibold">Hasta randevusu oluştur</summary>
        <form class="mt-4 grid gap-4" data-booking-form>
            <fieldset class="grid min-w-0 gap-3">
                <label class="offer-field">
                    Hasta kaydı
                    <select class="offer-control" data-booking-patient-mode>
                        <option value="manual">Hasta adını gir</option>
                        <option value="profile">Şubede kayıtlı hasta seç</option>
                    </select>
                </label>
                <label class="offer-field" data-booking-manual>
                    Hastanın adı ve soyadı
                    <input class="offer-control" name="patient_name" maxlength="255" required autocomplete="off">
                </label>
                <div class="grid gap-3" data-booking-patient-search hidden>
                    <div class="flex flex-wrap items-end gap-2">
                        <label class="offer-field min-w-0 flex-1">
                            Hasta adıyla ara
                            <input class="offer-control" type="search" data-booking-patient-query minlength="2" maxlength="80" autocomplete="off">
                        </label>
                        <button type="button" class="offer-button offer-button-secondary" data-booking-find-patient>Hasta ara</button>
                    </div>
                    <label class="offer-field">
                        Hasta profili
                        <select class="offer-control" name="patient_profile_id" disabled></select>
                    </label>
                    <p class="text-sm text-slate-600 dark:text-slate-300" role="status" data-booking-patient-status hidden></p>
                </div>
                <label class="offer-field">
                    Randevu tedavisi
                    <select class="offer-control" name="treatment_id" required disabled></select>
                </label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="offer-field">
                        Randevu hekimi
                        <select class="offer-control" name="doctor_id" required disabled></select>
                    </label>
                    <label class="offer-field">
                        Randevu saati
                        <select class="offer-control" name="starts_at" required disabled></select>
                    </label>
                </div>
                <p class="text-sm text-slate-600 dark:text-slate-300" data-booking-slot-status role="status" hidden></p>
                <div>
                    <button type="submit" class="offer-button" data-booking-save disabled>Hasta randevusunu kaydet</button>
                </div>
            </fieldset>
            <p class="text-sm text-slate-600 dark:text-slate-300" role="status" data-booking-status hidden></p>
        </form>
    </details>

    <details class="rounded-xl border border-slate-200 p-4 dark:border-slate-700" data-booking-packages>
        <summary class="cursor-pointer font-semibold">Paket randevularını oluştur</summary>
        <form class="mt-4 grid gap-3" data-booking-package-search>
            <div class="flex flex-wrap items-end gap-2">
                <label class="offer-field min-w-0 flex-1">
                    Teklif başlığıyla ara
                    <input class="offer-control" type="search" name="search" maxlength="100" autocomplete="off">
                </label>
                <button type="submit" class="offer-button offer-button-secondary">Teklifleri getir</button>
            </div>
            <p class="text-sm text-slate-600 dark:text-slate-300" role="status" data-booking-package-status hidden></p>
        </form>
        <ul class="mt-3 grid gap-2" data-booking-package-list></ul>
    </details>
</div>
