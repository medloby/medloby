<details class="rounded-xl border border-slate-200 p-4 dark:border-slate-700" data-calendar-appointment-settings hidden>
    <summary class="cursor-pointer font-semibold">Çevrimiçi randevu ayarları</summary>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" role="status" data-appointment-settings-status hidden></p>
    <form class="mt-4" data-appointment-settings-form hidden>
        <fieldset class="grid min-w-0 gap-4">
            <p class="text-sm text-slate-600 dark:text-slate-300" data-appointment-settings-inactive hidden>Bu şubenin kayıtlı ayarları pasif. Kaydettiğinizde etkinleşir.</p>
            <label class="flex items-start gap-2 text-sm font-medium">
                <input class="mt-0.5 h-4 w-4 accent-teal-700" type="checkbox" name="online_booking_enabled">
                Şubede çevrimiçi randevu açık
            </label>
            <label class="flex items-start gap-2 text-sm font-medium">
                <input class="mt-0.5 h-4 w-4 accent-teal-700" type="checkbox" name="same_day_booking_enabled">
                Aynı gün randevu alınabilsin
            </label>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="offer-field">
                    En az kaç dakika önce randevu alınabilir?
                    <input class="offer-control" type="number" name="minimum_booking_notice_minutes" min="0" max="525600" required>
                </label>
                <label class="offer-field">
                    Kaç gün ileriye randevu açılır?
                    <input class="offer-control" type="number" name="maximum_booking_days" min="1" max="3650" required>
                </label>
                <div class="grid gap-1.5">
                    <label class="offer-field">
                        Saat seçenekleri arası (dakika)
                        <input class="offer-control" type="number" name="slot_interval_minutes" min="1" max="1440" aria-describedby="slot-interval-help" required>
                    </label>
                    <p id="slot-interval-help" class="text-xs text-slate-600 dark:text-slate-300">Örneğin 15 dakika ile 09.00, 09.15 ve 09.30 gibi başlangıç saatleri gösterilir. Tedavi süresi kendi ayarından alınır.</p>
                </div>
            </div>
            <div class="grid gap-2">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="offer-field">
                        Randevu öncesi ayrılan süre (dakika)
                        <input class="offer-control" type="number" name="buffer_before_minutes" min="0" max="{{ \App\Models\BranchAppointmentSetting::MAX_BUFFER_MINUTES }}" aria-describedby="appointment-buffer-help" required>
                    </label>
                    <label class="offer-field">
                        Randevu sonrası ayrılan süre (dakika)
                        <input class="offer-control" type="number" name="buffer_after_minutes" min="0" max="{{ \App\Models\BranchAppointmentSetting::MAX_BUFFER_MINUTES }}" aria-describedby="appointment-buffer-help" required>
                    </label>
                </div>
                <p id="appointment-buffer-help" class="text-xs text-slate-600 dark:text-slate-300">Hazırlık ve temizlik için bu süreler hekimin takviminde ayrılır. Hasta kendi tedavisinin başlangıç ve bitiş saatini görür.</p>
            </div>
            <button type="submit" class="offer-button justify-self-start" data-appointment-settings-save>Ayarları kaydet</button>
        </fieldset>
    </form>
</details>
