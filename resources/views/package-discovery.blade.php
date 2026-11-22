<section id="package-discovery" class="mt-6 grid gap-5" aria-labelledby="page-title" hidden>
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900" data-discovery-scope hidden>
        <h2 class="min-w-0 break-words text-xl font-semibold" data-discovery-scope-name></h2>
        <div class="flex min-w-0 flex-wrap gap-2">
            <a class="offer-button offer-button-secondary" data-discovery-profile hidden>Klinik sayfası</a>
            <button type="button" class="offer-button offer-button-secondary" data-discovery-all-clinics>Tüm kliniklerin paketleri</button>
        </div>
    </div>
    <form action="{{ route('package-offers') }}#katalog" method="get" role="search" aria-label="Paket kataloğu arama" class="grid items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_auto] sm:p-5 dark:border-slate-800 dark:bg-slate-900" data-discovery-search>
        <label class="offer-field">
            Paket, klinik veya tedavi
            <input class="offer-control" type="search" name="q" maxlength="100" placeholder="Ad ile ara">
        </label>
        <label class="offer-field">
            İl
            <input class="offer-control" type="search" name="city" maxlength="80" placeholder="Tüm iller">
        </label>
        <button type="submit" class="offer-button min-h-11">Ara</button>
    </form>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-600 dark:text-slate-300" data-discovery-status role="status"></p>
        <button type="button" class="text-sm font-semibold text-teal-800 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-4 dark:text-teal-300" data-discovery-clear hidden>Filtreleri temizle</button>
    </div>
    <div class="grid items-start gap-4 sm:grid-cols-2 xl:grid-cols-3" data-discovery-results tabindex="-1"></div>
    <nav class="flex flex-wrap items-center justify-center gap-3" aria-label="Katalog sayfaları" data-discovery-pages hidden>
        <button type="button" class="offer-button offer-button-secondary" data-discovery-previous>Önceki</button>
        <span class="text-sm text-slate-600 dark:text-slate-300" data-discovery-page></span>
        <button type="button" class="offer-button offer-button-secondary" data-discovery-next>Sonraki</button>
    </nav>

    <template data-discovery-card>
        <article class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="min-w-0">
                <h2 class="break-words text-xl font-semibold tracking-tight" data-discovery-name></h2>
                <p class="mt-1 break-words text-sm font-medium text-slate-700 dark:text-slate-200" data-discovery-clinic></p>
                <p class="mt-1 break-words text-sm text-slate-600 dark:text-slate-300" data-discovery-location hidden></p>
            </div>
            <details class="border-t border-slate-200 pt-3 dark:border-slate-700">
                <summary class="cursor-pointer rounded-sm text-sm font-semibold text-teal-800 focus-visible:outline-2 focus-visible:outline-offset-4 dark:text-teal-300">Paket içeriği</summary>
                <div class="mt-3 grid gap-3 text-sm" data-discovery-contents></div>
                <button type="button" class="offer-button offer-button-secondary mt-4" data-discovery-start>Hesapta devam et</button>
            </details>
        </article>
    </template>
</section>
