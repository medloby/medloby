<section id="patient-booking" class="mt-6 grid max-w-2xl gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="page-title" hidden>
    <div class="grid gap-1" data-booking-context hidden>
        <h2 class="break-words text-xl font-semibold tracking-tight" data-booking-clinic></h2>
        <p class="break-words text-sm text-slate-600 dark:text-slate-300" data-booking-branch></p>
    </div>
    <p class="text-sm text-slate-600 dark:text-slate-300" data-status role="status" hidden></p>
    <form method="post" hidden>
        <fieldset class="grid min-w-0 gap-3 sm:grid-cols-2">
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
</section>
