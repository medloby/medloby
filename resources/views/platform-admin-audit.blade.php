<details id="admin-audit" class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900" data-admin-audit>
    <summary class="cursor-pointer font-semibold">İşlem geçmişi</summary>
    <div class="mt-4 flex flex-wrap items-end gap-3">
        <label class="offer-field">İşlem türü
            <select class="offer-control" data-audit-source>
                <option value="">Tümü</option>
                <option value="clinic_application">Klinik başvuru kararları</option>
                <option value="clinic_status">Klinik durum değişiklikleri</option>
                <option value="contract">Sözleşme yayımlama</option>
                <option value="support">Destek yanıtları</option>
            </select>
        </label>
        <button type="button" class="offer-button offer-button-secondary" data-report-refresh>Yenile</button>
    </div>
    <p role="status" class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-report-status hidden></p>
    <div class="mt-4 grid gap-3" data-report-result hidden>
        <ul class="grid gap-2" data-audit-rows></ul>
        <nav class="flex flex-wrap items-center gap-3" aria-label="İşlem geçmişi sayfaları" data-audit-pages hidden>
            <button type="button" class="offer-button offer-button-secondary" data-page-previous>Önceki</button>
            <span class="text-sm" data-page-number></span>
            <button type="button" class="offer-button offer-button-secondary" data-page-next>Sonraki</button>
        </nav>
    </div>
</details>
