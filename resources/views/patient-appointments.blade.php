<section id="patient-appointments" class="mt-6 grid max-w-4xl gap-4" aria-labelledby="page-title" hidden>
    <button id="patient-booking-button" type="button" class="offer-button justify-self-start">Yeni randevu al</button>
    <div class="flex flex-wrap items-center gap-2" data-appointment-views role="group" aria-label="Randevu listesi">
        <button type="button" data-appointment-view="upcoming" aria-pressed="true" class="offer-button offer-button-secondary aria-pressed:border-teal-700 aria-pressed:bg-teal-50 aria-pressed:text-teal-900 dark:aria-pressed:bg-teal-950 dark:aria-pressed:text-teal-100">Yaklaşan</button>
        <button type="button" data-appointment-view="history" aria-pressed="false" class="offer-button offer-button-secondary aria-pressed:border-teal-700 aria-pressed:bg-teal-50 aria-pressed:text-teal-900 dark:aria-pressed:bg-teal-950 dark:aria-pressed:text-teal-100">Geçmiş ve iptaller</button>
    </div>
    <a href="#randevular" class="offer-button offer-button-secondary justify-self-start" data-appointment-back hidden>Tüm randevulara dön</a>
    <p class="text-sm text-slate-600 dark:text-slate-300" data-appointment-list-status role="status" hidden></p>
    <ul class="grid gap-3" data-patient-appointment-entries></ul>
    <nav class="flex flex-wrap items-center justify-between gap-2" aria-label="Randevu sayfaları" data-appointment-pages hidden>
        <button type="button" class="offer-button offer-button-secondary" data-appointment-previous>Önceki</button>
        <span class="text-sm text-slate-600 dark:text-slate-300" data-appointment-page></span>
        <button type="button" class="offer-button offer-button-secondary" data-appointment-next>Sonraki</button>
    </nav>
</section>
