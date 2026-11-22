<section id="patient-journey" class="mt-5 max-w-3xl" aria-labelledby="page-title" hidden>
    <fieldset class="grid min-w-0 gap-4" data-journey-content>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <label class="offer-field w-full max-w-xs">
                Kayıt türü
                <select class="offer-control" data-journey-category>
                    <option value="">Tümü</option>
                    <option value="conversation">Görüşmeler</option>
                    <option value="offer">Teklifler</option>
                    <option value="appointment">Randevular</option>
                </select>
            </label>
            <button type="button" class="offer-button offer-button-secondary" data-journey-refresh>Yenile</button>
        </div>
        <p class="text-sm text-slate-600 dark:text-slate-300" data-journey-status role="status" hidden></p>
        <ol class="grid min-w-0 gap-3" data-journey-events></ol>
        <nav class="flex flex-wrap items-center justify-between gap-2" aria-label="Tedavi süreci sayfaları" data-journey-pages hidden>
            <button type="button" class="offer-button offer-button-secondary" data-journey-previous>Önceki</button>
            <span class="text-sm text-slate-600 dark:text-slate-300" data-journey-page></span>
            <button type="button" class="offer-button offer-button-secondary" data-journey-next>Sonraki</button>
        </nav>
    </fieldset>
    <template data-journey-event>
        <li class="flex min-w-0 flex-wrap items-start justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <div class="grid min-w-0 gap-1 break-words">
                <h2 class="font-semibold" data-journey-label></h2>
                <p class="text-sm" data-journey-title hidden></p>
                <p class="text-sm text-slate-600 dark:text-slate-300" data-journey-meta></p>
                <p class="text-sm text-slate-600 dark:text-slate-300" data-journey-starts hidden></p>
            </div>
            <button type="button" class="offer-button offer-button-secondary" data-journey-open></button>
        </li>
    </template>
</section>
