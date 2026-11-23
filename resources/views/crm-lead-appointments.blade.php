<details class="mt-4 rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-700" data-leads-appointments hidden>
    <summary class="cursor-pointer font-semibold">Şube randevuları</summary>
    <div class="mt-3 grid gap-3">
        <p class="text-sm text-slate-600 dark:text-slate-300" data-lead-appointment-status role="status" hidden></p>
        <button type="button" class="offer-button offer-button-secondary justify-self-start" data-lead-appointment-link hidden>Hasta hesabını bağla</button>
        <button type="button" class="offer-button offer-button-secondary justify-self-start" data-lead-appointment-book hidden>Bu hasta için randevu planla</button>
        <ul class="grid gap-2" data-lead-appointment-list></ul>
        <nav class="flex flex-wrap items-center justify-between gap-2" aria-label="Aday randevusu sayfaları" data-lead-appointment-pages hidden>
            <button type="button" class="offer-button offer-button-secondary" data-lead-appointment-previous>Önceki</button>
            <span class="text-sm text-slate-600 dark:text-slate-300" data-lead-appointment-page></span>
            <button type="button" class="offer-button offer-button-secondary" data-lead-appointment-next>Sonraki</button>
        </nav>
    </div>
    <template data-lead-appointment-row>
        <li class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 p-3 text-sm dark:border-slate-700">
            <div class="min-w-0">
                <p class="break-words font-semibold" data-lead-appointment-time></p>
                <p class="break-words text-slate-600 dark:text-slate-300" data-lead-appointment-summary></p>
            </div>
            <button type="button" class="offer-button offer-button-secondary">Takvimde aç</button>
        </li>
    </template>
</details>
