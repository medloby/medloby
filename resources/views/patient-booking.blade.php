<dialog id="patient-booking-dialog" class="offer-dialog" aria-labelledby="patient-booking-title">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <h2 id="patient-booking-title" class="text-2xl font-semibold tracking-tight">Yeni randevu al</h2>
        <button type="button" class="offer-button offer-button-secondary" data-close>Vazgeç</button>
    </div>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-status role="status" hidden></p>
    <form method="post" class="mt-4" hidden>
        <fieldset class="grid min-w-0 gap-3 sm:grid-cols-2">
            <label class="offer-field sm:col-span-2">
                Klinik ve şube
                <select class="offer-control" name="branch" required></select>
            </label>
            <label class="offer-field">
                Tedavi
                <select class="offer-control" name="treatment" required></select>
            </label>
            <label class="offer-field">
                Hekim
                <select class="offer-control" name="doctor" required></select>
            </label>
            <label class="offer-field">
                Randevu günü
                <input class="offer-control" type="date" name="date" required>
            </label>
            <label class="offer-field">
                Uygun saat (Türkiye saati)
                <select class="offer-control" name="time" required></select>
            </label>
            <label class="offer-field sm:col-span-2">
                Randevu açıklaması (isteğe bağlı)
                <textarea class="offer-control" name="notes" rows="3" maxlength="2000"></textarea>
            </label>
            <button type="submit" class="offer-button justify-self-start" disabled>Randevuyu kaydet</button>
        </fieldset>
    </form>
</dialog>
