<dialog class="offer-dialog" aria-labelledby="clinic-appointment-card-title" data-calendar-appointment-card>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 id="clinic-appointment-card-title" class="text-xl font-semibold">Randevu kartı</h3>
        <form method="dialog">
            <button type="submit" class="offer-button offer-button-secondary" autofocus>Kapat</button>
        </form>
    </div>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" role="status" data-card-status hidden></p>
    <div class="mt-4 grid gap-4" data-card-content hidden>
        <div class="flex flex-wrap items-start justify-between gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
            <div class="min-w-0">
                <p class="break-words font-semibold" data-card-clinic></p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300" data-card-number></p>
            </div>
            <p class="rounded-lg bg-teal-50 px-2.5 py-1 text-sm font-semibold text-teal-950 dark:bg-teal-950 dark:text-teal-100" data-card-appointment-status></p>
        </div>
        <dl class="grid min-w-0 gap-x-6 gap-y-4 text-sm sm:grid-cols-2" data-card-fields></dl>
        <div class="min-w-0 rounded-xl bg-slate-50 p-4 text-sm dark:bg-slate-800" data-card-notes-section hidden>
            <h4 class="font-semibold">Randevu açıklaması</h4>
            <p class="mt-1 whitespace-pre-wrap break-words" data-card-notes></p>
        </div>
        <section class="grid gap-2 border-t border-slate-200 pt-3 dark:border-slate-700" aria-labelledby="appointment-card-leads-title" data-card-leads hidden>
            <h4 class="text-sm font-semibold" id="appointment-card-leads-title">Bağlı CRM adayları</h4>
            <ul class="grid gap-2" data-card-lead-list></ul>
        </section>
        <button type="button" class="offer-button justify-self-start" data-card-download>PDF indir</button>
    </div>
    <template data-card-field>
        <div class="min-w-0">
            <dt class="text-slate-600 dark:text-slate-300"></dt>
            <dd class="break-words font-medium"></dd>
        </div>
    </template>
    <template data-card-lead>
        <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
            <div class="min-w-0 break-words">
                <p class="font-semibold" data-card-lead-name></p>
                <p class="text-slate-600 dark:text-slate-300" data-card-lead-meta></p>
            </div>
            <button type="button" class="offer-button offer-button-secondary">Adayı aç</button>
        </li>
    </template>
</dialog>
