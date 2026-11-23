@php($financeReportId = $financeReportId ?? 'branch-finance')
<details class="mt-4 min-w-0 rounded-xl border border-slate-200 p-3 sm:p-4 dark:border-slate-700" data-finance-reports hidden>
    <summary class="cursor-pointer font-semibold">{{ ($clinicWide ?? false) ? 'Finans karşılaştırmaları' : 'Finans raporları' }}</summary>
    <form class="mt-4 flex flex-wrap items-end gap-3" data-finance-form>
        @unless($clinicWide ?? false)
            <label class="offer-field">Şube kapsamı
                <select class="offer-control" name="scope">
                    <option value="branch">Seçili şube</option>
                    <option value="all">Yetkili olduğum tüm şubeler</option>
                </select>
            </label>
        @endunless
        <label class="offer-field">Finans dönemi
            <select class="offer-control" name="period">
                <option value="month">Aylık</option>
                <option value="year">Yıllık</option>
                <option value="all">Tüm dönemler</option>
            </select>
        </label>
        <label class="offer-field" data-finance-month>Rapor ayı
            <input class="offer-control" type="month" name="month" min="0001-01" max="9999-12">
        </label>
        <label class="offer-field" data-finance-year hidden>Rapor yılı
            <input class="offer-control" type="number" name="year" min="1" max="9999" step="1" disabled>
        </label>
        <button type="submit" class="offer-button" data-finance-submit>Raporu göster</button>
    </form>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-finance-status role="status" hidden></p>
    <div class="mt-4 grid gap-5" data-finance-result hidden>
        <p class="text-sm text-slate-600 dark:text-slate-300" data-finance-period></p>
        <section aria-labelledby="{{ $financeReportId }}-comparison-title" data-finance-comparison hidden>
            <h4 id="{{ $financeReportId }}-comparison-title" class="font-semibold">Önceki dönemle karşılaştırma</h4>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300" data-finance-previous-period></p>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300" data-finance-comparison-empty hidden>Her iki dönemde de aktif gelir veya gider kaydı yok.</p>
            <div class="mt-3 grid gap-3" data-finance-comparison-charts></div>
        </section>
        <section aria-labelledby="{{ $financeReportId }}-treatment-title">
            <h4 id="{{ $financeReportId }}-treatment-title" class="font-semibold">Tedavi bazında gelir</h4>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Randevuya bağlı tahsilatlar, ilgili tedaviye tahsilat tarihinde yazılır.</p>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300" data-finance-treatment-empty hidden>Bu dönemde tedaviye bağlı tahsilat kaydı yok.</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2" data-finance-treatment-charts></div>
        </section>
        <section aria-labelledby="{{ $financeReportId }}-summary-title">
            <h4 id="{{ $financeReportId }}-summary-title" class="font-semibold">Gelir, gider ve net fark</h4>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300" data-finance-cash-empty hidden>Bu dönemde aktif gelir veya gider kaydı yok.</p>
            <div class="mt-2 overflow-x-auto" data-finance-cash>
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-b border-slate-200 dark:border-slate-700">
                        <th scope="col" class="px-2 py-2">Para birimi</th><th scope="col" class="px-2 py-2">Gelir</th><th scope="col" class="px-2 py-2">Gider</th><th scope="col" class="px-2 py-2">Net fark</th><th scope="col" class="px-2 py-2">Hekime bağlanmamış gelir</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700" data-finance-totals></tbody>
                </table>
            </div>
        </section>
        <section aria-labelledby="{{ $financeReportId }}-branch-title" data-finance-branches hidden>
            <h4 id="{{ $financeReportId }}-branch-title" class="font-semibold">Şube bazında toplamlar</h4>
            <div class="mt-2 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-b border-slate-200 dark:border-slate-700">
                        <th scope="col" class="px-2 py-2">Şube</th><th scope="col" class="px-2 py-2">Para birimi</th><th scope="col" class="px-2 py-2">Gelir</th><th scope="col" class="px-2 py-2">Gider</th><th scope="col" class="px-2 py-2">Net fark</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700" data-finance-branch-totals></tbody>
                </table>
            </div>
        </section>
        <section aria-labelledby="{{ $financeReportId }}-months-title" data-finance-months hidden>
            <h4 id="{{ $financeReportId }}-months-title" class="font-semibold">Aylara göre dağılım</h4>
            <div class="mt-2 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-b border-slate-200 dark:border-slate-700">
                        <th scope="col" class="px-2 py-2">Ay</th><th scope="col" class="px-2 py-2">Para birimi</th><th scope="col" class="px-2 py-2">Gelir</th><th scope="col" class="px-2 py-2">Gider</th><th scope="col" class="px-2 py-2">Net fark</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700" data-finance-month-totals></tbody>
                </table>
            </div>
        </section>
        <section aria-labelledby="{{ $financeReportId }}-doctors-title">
            <h4 id="{{ $financeReportId }}-doctors-title" class="font-semibold">Hekim raporları</h4>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Randevular seans tarihine, gelir ve giderler kayıt tarihine göre sayılır. Hekim geliri, randevuya bağlı tahsilatlardan oluşur.</p>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300" data-finance-doctors-empty hidden>Bu dönemde hekime bağlı randevu veya tahsilat yok.</p>
            <ul class="mt-3 grid gap-3" data-finance-doctors></ul>
        </section>
    </div>
    <template data-finance-doctor>
        <li class="rounded-xl border border-slate-200 p-3 sm:p-4 dark:border-slate-700">
            <h5 class="break-words font-semibold" data-finance-doctor-name></h5>
            <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-3 lg:grid-cols-5">
                <div><dt>Toplam randevu</dt><dd class="font-semibold tabular-nums" data-finance-visit-total></dd></div>
                <div><dt>Tamamlanan</dt><dd class="font-semibold tabular-nums" data-finance-visit-completed></dd></div>
                <div><dt>Planlanan</dt><dd class="font-semibold tabular-nums" data-finance-visit-scheduled></dd></div>
                <div><dt>İptal</dt><dd class="font-semibold tabular-nums" data-finance-visit-cancelled></dd></div>
                <div><dt>Hasta gelmedi</dt><dd class="font-semibold tabular-nums" data-finance-visit-no_show></dd></div>
            </dl>
            <p class="mt-3 text-sm" data-finance-doctor-income></p>
            <ul class="mt-2 grid gap-1 text-sm text-slate-600 dark:text-slate-300" aria-label="Tedavilere göre randevular" data-finance-treatments></ul>
        </li>
    </template>
</details>
