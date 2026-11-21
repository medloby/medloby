<section id="package-discovery" class="mt-6 grid gap-4" aria-labelledby="page-title" hidden>
    <p class="text-sm text-slate-600 dark:text-slate-300" data-discovery-status role="status" hidden></p>
    <div class="grid items-start gap-4 sm:grid-cols-2 xl:grid-cols-3" data-discovery-results></div>
    <nav class="flex flex-wrap items-center justify-center gap-3" aria-label="Katalog sayfaları" data-discovery-pages hidden>
        <button type="button" class="offer-button offer-button-secondary" data-discovery-previous>Önceki</button>
        <span class="text-sm text-slate-600 dark:text-slate-300" data-discovery-page></span>
        <button type="button" class="offer-button offer-button-secondary" data-discovery-next>Sonraki</button>
    </nav>

    <template data-discovery-card>
        <article class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="min-w-0">
                <p class="break-words text-sm text-slate-600 dark:text-slate-300" data-discovery-clinic></p>
                <h2 class="mt-1 break-words text-xl font-semibold tracking-tight" data-discovery-name></h2>
            </div>
            <p class="whitespace-pre-wrap break-words text-sm text-slate-700 dark:text-slate-300" data-discovery-description hidden></p>
            <div class="grid gap-3 text-sm" data-discovery-contents></div>
            <button type="button" class="offer-button justify-self-start" data-discovery-start>Klinikle görüşme başlat</button>
        </article>
    </template>
</section>
