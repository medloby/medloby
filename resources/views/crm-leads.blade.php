<section class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900" data-crm-leads aria-labelledby="crm-leads-title" hidden>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 id="crm-leads-title" class="text-lg font-semibold">Hasta adayları</h3>
        <button type="button" class="offer-button" data-leads-new>Yeni hasta adayı</button>
    </div>
    <label class="offer-field w-full max-w-xs">
        Duruma göre filtrele
        <select class="offer-control" data-leads-filter>
            <option value="">Tüm durumlar</option>
        </select>
    </label>
    <p class="text-sm text-slate-600 dark:text-slate-300" data-leads-status role="status" hidden></p>
    <ul class="grid gap-2" data-leads-list></ul>
    <nav class="flex flex-wrap items-center justify-between gap-2" aria-label="Hasta adayı sayfaları" data-leads-pages hidden>
        <button type="button" class="offer-button offer-button-secondary" data-leads-previous>Önceki</button>
        <span class="text-sm text-slate-600 dark:text-slate-300" data-leads-page></span>
        <button type="button" class="offer-button offer-button-secondary" data-leads-next>Sonraki</button>
    </nav>

    <template data-leads-row>
        <li class="flex flex-wrap justify-between gap-x-4 gap-y-2 rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-700">
            <div class="min-w-0">
                <h4 class="break-words font-semibold" data-leads-name></h4>
                <p class="mt-1 break-words text-sm text-slate-600 dark:text-slate-300" data-leads-contact hidden></p>
            </div>
            <div class="flex flex-wrap items-start gap-2 text-sm">
                <span class="break-words rounded-lg bg-teal-50 px-2.5 py-1 text-teal-950 dark:bg-teal-950 dark:text-teal-100" data-leads-row-status></span>
                <span class="break-words rounded-lg bg-slate-100 px-2.5 py-1 text-slate-700 dark:bg-slate-800 dark:text-slate-200" data-leads-stage hidden></span>
                <button type="button" class="offer-button offer-button-secondary" data-leads-details aria-haspopup="dialog">Ayrıntı</button>
            </div>
        </li>
    </template>

    <dialog class="offer-dialog" aria-labelledby="crm-lead-form-title" data-leads-editor>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <h3 id="crm-lead-form-title" class="min-w-0 break-words text-xl font-semibold" data-leads-editor-title>Yeni hasta adayı</h3>
            <button type="button" class="offer-button offer-button-secondary" data-leads-close>Vazgeç</button>
        </div>
        <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-leads-form-status role="status" hidden></p>
        <div class="mt-4 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-slate-700 dark:bg-slate-800" data-leads-summary hidden>
            <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <div class="min-w-0 break-words">
                    <dt class="text-slate-600 dark:text-slate-300">Durum</dt>
                    <dd class="mt-1 font-semibold" data-leads-detail-status></dd>
                </div>
                <div class="min-w-0 break-words">
                    <dt class="text-slate-600 dark:text-slate-300">Takip aşaması</dt>
                    <dd class="mt-1 font-semibold" data-leads-detail-stage></dd>
                </div>
                <div>
                    <dt class="text-slate-600 dark:text-slate-300">Kayıt tarihi</dt>
                    <dd class="mt-1 font-semibold" data-leads-detail-date></dd>
                </div>
            </dl>
            <div data-leads-notes hidden>
                <h4 class="font-semibold">Not</h4>
                <p class="mt-1 break-words whitespace-pre-wrap text-slate-700 dark:text-slate-200" data-leads-note-text></p>
            </div>
            <div data-leads-result hidden>
                <h4 class="font-semibold">Sonuç</h4>
                <p class="mt-1 break-words whitespace-pre-wrap text-slate-700 dark:text-slate-200" data-leads-result-text></p>
            </div>
        </div>
        <details class="mt-4 rounded-xl border border-slate-200 px-4 py-3 text-sm dark:border-slate-700" data-leads-history hidden>
            <summary class="cursor-pointer font-semibold">Aşama geçmişi</summary>
            <p class="mt-3 text-slate-600 dark:text-slate-300" data-leads-no-history hidden>Henüz aşama kaydı yok.</p>
            <ol class="mt-3 grid gap-3" data-leads-history-list></ol>
        </details>
        <template data-leads-history-row>
            <li class="grid gap-1 border-s-2 border-teal-700 ps-3 dark:border-teal-400">
                <p class="break-words font-medium" data-leads-history-transition></p>
                <p class="break-words text-slate-600 dark:text-slate-300" data-leads-history-by></p>
                <p class="break-words whitespace-pre-wrap text-slate-700 dark:text-slate-200" data-leads-history-note hidden></p>
            </li>
        </template>
        <form class="mt-5" data-leads-form>
            <fieldset class="grid min-w-0 gap-4">
                <legend class="mb-3 font-semibold">Aday bilgileri</legend>
                <div class="grid grid-cols-2 gap-3">
                    <label class="offer-field">
                        Ad
                        <input class="offer-control" name="first_name" maxlength="255">
                    </label>
                    <label class="offer-field">
                        Soyad
                        <input class="offer-control" name="last_name" maxlength="255">
                    </label>
                    <label class="offer-field col-span-2 sm:col-span-1">
                        Telefon
                        <input class="offer-control" name="phone" type="tel" maxlength="50">
                    </label>
                    <label class="offer-field col-span-2 sm:col-span-1">
                        E-posta
                        <input class="offer-control" name="email" type="email" maxlength="255">
                    </label>
                </div>
                <label class="offer-field" data-leads-assignment hidden>
                    Sorumlu personel
                    <select class="offer-control" name="assigned_business_user_id" disabled></select>
                </label>
                <label class="offer-field" data-leads-stage-field hidden>
                    Takip aşamasını değiştir
                    <select class="offer-control" name="pipeline_stage_id" disabled></select>
                </label>
                <label class="offer-field" data-leads-stage-note-field hidden>
                    Aşama değişikliği notu (isteğe bağlı)
                    <textarea class="offer-control" name="pipeline_stage_note" maxlength="2000" rows="2" disabled></textarea>
                </label>
                <button type="submit" class="offer-button justify-self-start" data-leads-save>Hasta adayını kaydet</button>
            </fieldset>
        </form>
        <details class="mt-5 rounded-xl border border-slate-200 px-4 py-3 text-sm dark:border-slate-700" data-leads-outcome hidden>
            <summary class="cursor-pointer font-semibold">Adayı sonuçlandır</summary>
            <p class="mt-3 text-slate-600 dark:text-slate-300" data-leads-outcome-status role="status" hidden></p>
            <form class="mt-3" data-leads-outcome-form>
                <fieldset class="grid min-w-0 gap-3">
                    <label class="offer-field">
                        Sonuç türü
                        <select class="offer-control" name="outcome" required>
                            <option value="">Sonuç seçin</option>
                            <option value="convert">Başarılı</option>
                            <option value="lost">Sonuçsuz</option>
                        </select>
                    </label>
                    <label class="offer-field" data-leads-lost-reason-field hidden>
                        Sonuçsuz kalma nedeni
                        <textarea class="offer-control" name="lost_reason" maxlength="1000" rows="2" disabled></textarea>
                    </label>
                    <button type="submit" class="offer-button justify-self-start">Sonucu kaydet</button>
                </fieldset>
            </form>
        </details>
        @include('crm-lead-work')
        @include('crm-lead-appointments')
        @include('crm-lead-care')
    </dialog>
</section>
