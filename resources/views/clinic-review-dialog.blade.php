<dialog id="clinic-review-dialog" class="offer-dialog" aria-labelledby="clinic-review-title">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <h2 id="clinic-review-title" class="text-2xl font-semibold tracking-tight">Klinik başvuruları</h2>
        <button type="button" class="offer-button offer-button-secondary" data-clinic-close>Kapat</button>
    </div>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-clinic-status role="status" hidden></p>

    <section class="mt-4 grid gap-4" data-clinic-overview aria-label="Bekleyen başvurular">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="font-semibold">Bekleyen başvurular</h3>
            <button type="button" class="offer-button offer-button-secondary" data-clinic-refresh>Yenile</button>
        </div>
        <ul class="grid gap-2" data-clinic-list></ul>
        <nav class="flex flex-wrap items-center justify-between gap-2" aria-label="Başvuru sayfaları" data-clinic-pages hidden>
            <button type="button" class="offer-button offer-button-secondary" data-clinic-previous>Önceki</button>
            <span class="text-sm text-slate-600 dark:text-slate-300" data-clinic-page></span>
            <button type="button" class="offer-button offer-button-secondary" data-clinic-next>Sonraki</button>
        </nav>
    </section>

    <section class="mt-4 grid gap-4" data-clinic-detail aria-labelledby="clinic-application-name" hidden>
        <button type="button" class="offer-button offer-button-secondary justify-self-start" data-clinic-back>Başvurulara dön</button>
        <div class="grid gap-4" data-clinic-information hidden>
            <div>
                <h3 id="clinic-application-name" class="break-words text-xl font-semibold" tabindex="-1"></h3>
                <p class="mt-1 break-words text-sm text-slate-600 dark:text-slate-300" data-clinic-date></p>
            </div>
            <dl class="grid gap-3 rounded-xl border border-slate-200 p-4 text-sm sm:grid-cols-2 dark:border-slate-700">
                <div class="min-w-0">
                    <dt class="font-semibold">Başvuran</dt>
                    <dd class="whitespace-pre-line break-words" data-clinic-owners></dd>
                </div>
                <div class="min-w-0">
                    <dt class="font-semibold">Klinik iletişim bilgileri</dt>
                    <dd class="whitespace-pre-line break-words" data-clinic-contact></dd>
                </div>
                <div class="min-w-0">
                    <dt class="font-semibold">Adres</dt>
                    <dd class="whitespace-pre-line break-words" data-clinic-address></dd>
                </div>
                <div class="min-w-0">
                    <dt class="font-semibold">Şubeler</dt>
                    <dd class="whitespace-pre-line break-words" data-clinic-branches></dd>
                </div>
            </dl>
            <p class="whitespace-pre-wrap break-words text-sm text-slate-700 dark:text-slate-200" data-clinic-description hidden></p>

            <div class="grid gap-2">
                <h4 class="font-semibold">Sözleşme kabulü</h4>
                <p class="text-sm text-slate-600 dark:text-slate-300" data-clinic-no-acceptance hidden>Sözleşme kabulü bulunamadı.</p>
                <div class="grid gap-2" data-clinic-acceptances></div>
            </div>

            <form class="grid gap-3 border-t border-slate-200 pt-4 dark:border-slate-700" data-clinic-decision hidden>
                <fieldset class="grid gap-3">
                    <button type="button" class="offer-button justify-self-start" data-clinic-approve>Başvuruyu onayla</button>
                    <details data-clinic-rejection>
                        <summary class="w-fit cursor-pointer text-sm font-semibold text-rose-700 dark:text-rose-300">Başvuruyu reddet</summary>
                        <label class="offer-field mt-3">
                            Ret nedeni
                            <textarea name="rejection_reason" class="offer-control" rows="3" required minlength="10" maxlength="5000"></textarea>
                        </label>
                        <button type="submit" class="offer-button mt-3 bg-rose-700 hover:bg-rose-800 dark:bg-rose-700 dark:hover:bg-rose-600">Reddi kaydet</button>
                    </details>
                </fieldset>
            </form>
        </div>
    </section>

    <template data-clinic-row>
        <li>
            <button type="button" class="grid w-full min-w-0 gap-1 rounded-xl border border-slate-200 p-3 text-start hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-teal-700 dark:border-slate-700 dark:hover:bg-slate-800 dark:focus-visible:outline-teal-400">
                <strong class="break-words" data-clinic-row-name></strong>
                <span class="break-words text-sm text-slate-600 dark:text-slate-300" data-clinic-row-context></span>
            </button>
        </li>
    </template>

    <template data-clinic-acceptance>
        <details class="min-w-0 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
            <summary class="cursor-pointer break-words text-sm font-semibold" data-clinic-contract-title></summary>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300" data-clinic-accepted-date></p>
            <p class="mt-3 max-h-64 overflow-y-auto whitespace-pre-wrap break-words text-sm leading-relaxed" tabindex="0" data-clinic-contract-content></p>
        </details>
    </template>
</dialog>
