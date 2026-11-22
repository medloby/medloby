<dialog id="clinic-contracts-dialog" class="offer-dialog max-w-3xl" aria-labelledby="clinic-contracts-title">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <h2 id="clinic-contracts-title" class="text-2xl font-semibold tracking-tight">Klinik üyelik sözleşmeleri</h2>
        <button type="button" class="offer-button offer-button-secondary" data-contract-close>Kapat</button>
    </div>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-contract-status role="status" hidden></p>

    <section class="mt-4 grid gap-4" data-contract-list-view aria-label="Sözleşme sürümleri">
        <div class="flex flex-wrap gap-2">
            <button type="button" class="offer-button" data-contract-new>Yeni taslak</button>
            <button type="button" class="offer-button offer-button-secondary" data-contract-refresh>Yenile</button>
        </div>
        <ul class="grid gap-2" data-contract-list></ul>
        <nav class="flex flex-wrap items-center justify-between gap-2" aria-label="Sözleşme sayfaları" data-contract-pages hidden>
            <button type="button" class="offer-button offer-button-secondary" data-contract-prev>Önceki</button>
            <span class="text-sm text-slate-600 dark:text-slate-300" data-contract-page></span>
            <button type="button" class="offer-button offer-button-secondary" data-contract-next>Sonraki</button>
        </nav>
    </section>

    <section class="mt-4 grid gap-4" data-contract-detail aria-label="Sözleşme ayrıntıları" hidden>
        <button type="button" class="offer-button offer-button-secondary justify-self-start" data-contract-back>Sürümlere dön</button>
        <form data-contract-form hidden>
            <fieldset class="grid gap-4 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200" data-contract-state tabindex="-1"></p>
                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_9rem]">
                    <label class="offer-field">
                        Başlık
                        <input class="offer-control read-only:bg-slate-50 dark:read-only:bg-slate-950" name="title" maxlength="255" required>
                    </label>
                    <label class="offer-field">
                        Sürüm
                        <input class="offer-control read-only:bg-slate-50 dark:read-only:bg-slate-950" name="version" maxlength="30" required>
                    </label>
                </div>
                <label class="offer-field">
                    Sözleşme metni
                    <textarea class="offer-control min-h-64 resize-y read-only:bg-slate-50 dark:read-only:bg-slate-950" name="content" rows="12" required></textarea>
                </label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="offer-field">
                        Yürürlük tarihi ve saati
                        <input class="offer-control" name="effective_at" type="datetime-local" step="1" aria-describedby="contract-date-help">
                    </label>
                    <label class="offer-field">
                        Bitiş tarihi ve saati
                        <input class="offer-control" name="expires_at" type="datetime-local" step="1" aria-describedby="contract-date-help">
                    </label>
                </div>
                <p id="contract-date-help" class="text-sm text-slate-600 dark:text-slate-300" data-contract-date-help>Alanlar cihazınızın yerel saatini gösterir. Yürürlük boş bırakılırsa sürüm yayımlandığı anda yürürlüğe girer. Bitiş tarihi isteğe bağlıdır.</p>
                <dl class="grid gap-2 text-sm sm:grid-cols-2" data-contract-metadata hidden>
                    <div>
                        <dt class="font-medium">Yayımlanma zamanı</dt>
                        <dd data-contract-published-at></dd>
                    </div>
                    <div>
                        <dt class="font-medium">Son işlem</dt>
                        <dd data-contract-updated-at></dd>
                    </div>
                </dl>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="offer-button" data-contract-save>Taslağı kaydet</button>
                    <button type="button" class="offer-button" data-contract-publish hidden>Sürümü yayımla</button>
                    <button type="button" class="offer-button offer-button-secondary" data-contract-deactivate hidden>Yayından kaldır</button>
                </div>
            </fieldset>
        </form>

        <details class="rounded-xl border border-slate-200 p-4 dark:border-slate-700" data-contract-acceptances hidden>
            <summary class="cursor-pointer font-semibold">Bu sürümün kabul kayıtları</summary>
            <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-contract-acceptance-status role="status" hidden></p>
            <ul class="mt-3 grid gap-2" data-contract-acceptance-list></ul>
            <nav class="mt-3 flex flex-wrap items-center justify-between gap-2" aria-label="Kabul kaydı sayfaları" data-contract-acceptance-pages hidden>
                <button type="button" class="offer-button offer-button-secondary" data-contract-acceptance-prev>Önceki</button>
                <span class="text-sm text-slate-600 dark:text-slate-300" data-contract-acceptance-page></span>
                <button type="button" class="offer-button offer-button-secondary" data-contract-acceptance-next>Sonraki</button>
            </nav>
        </details>
    </section>

    <template data-contract-acceptance-row>
        <li class="grid gap-1 rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-950">
            <strong class="break-words" data-contract-acceptance-clinic></strong>
            <span class="break-words text-slate-600 dark:text-slate-300" data-contract-acceptance-detail></span>
        </li>
    </template>

    <template data-contract-row>
        <li>
            <button type="button" class="grid w-full min-w-0 gap-1 rounded-xl border border-slate-200 p-3 text-start hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-teal-700 dark:border-slate-700 dark:hover:bg-slate-800 dark:focus-visible:outline-teal-400">
                <strong class="break-words" data-contract-row-title></strong>
                <span class="break-words text-sm text-slate-600 dark:text-slate-300" data-contract-row-state></span>
            </button>
        </li>
    </template>
</dialog>
