<details id="admin-analytics" class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900" data-admin-analytics>
    <summary class="cursor-pointer font-semibold">İşlem analitiği</summary>
    <p role="status" class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-report-status hidden></p>
    <div class="mt-4 grid gap-4" data-report-result hidden>
        <p data-analytics-period class="text-sm text-slate-600 dark:text-slate-300"></p>
        <dl class="grid gap-3 text-sm sm:grid-cols-3">
            @foreach ([
                'offers_created' => 'Oluşturulan teklif',
                'offers_converted' => 'Randevu oluşan kabul edilmiş teklif',
                'clinics_with_appointments' => 'Randevusu bulunan klinik',
            ] as $key => $label)
                <div>
                    <dt>{{ $label }}</dt>
                    <dd class="mt-1 text-xl font-semibold tabular-nums" data-analytics-metric="{{ $key }}"></dd>
                </div>
            @endforeach
        </dl>
        <div class="grid gap-4 sm:grid-cols-2">
            <section class="grid content-start gap-2" aria-labelledby="admin-offer-outcomes-title" data-analytics-outcomes>
                <h2 id="admin-offer-outcomes-title" class="font-semibold">Tekliflerin kayıtlı durumu</h2>
                <ul class="grid gap-2 text-sm" data-chart-bars></ul>
            </section>
            <section class="grid content-start gap-2" aria-labelledby="admin-city-distribution-title" data-analytics-cities>
                <h2 id="admin-city-distribution-title" class="font-semibold">Klinik kayıtlarında öne çıkan iller</h2>
                <ul class="grid gap-2 text-sm" data-chart-bars></ul>
            </section>
        </div>
        <button type="button" class="offer-button offer-button-secondary w-fit" data-report-refresh>Analitiği yenile</button>
    </div>
</details>
