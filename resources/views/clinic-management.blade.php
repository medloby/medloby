<details class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900" data-crm-management hidden>
    <summary class="cursor-pointer text-lg font-semibold">Yönetim özeti</summary>
    <form class="mt-4 flex flex-wrap items-end gap-3" data-management-form>
        <label class="offer-field">Rapor ayı
            <input class="offer-control" type="month" name="month" min="0001-01" max="9999-12">
        </label>
        <button type="submit" class="offer-button offer-button-secondary">Göster</button>
    </form>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" role="status" data-management-status hidden></p>
    <div class="mt-4 grid min-w-0 gap-6" data-management-results hidden>
        <section aria-labelledby="management-branches-title">
            <h3 id="management-branches-title" class="font-semibold">Şubeler ve hasta dönüşümü</h3>
            <ul class="mt-2 text-sm" data-management-summary></ul>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Teklif ve randevu sayıları, seçilen ayda açılan adaylarla bağlantılı ve aday kaydından sonra oluşturulan kayıtları gösterir.</p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300" data-management-alert-date hidden></p>
            <ul class="mt-3 grid gap-3 sm:grid-cols-2" data-management-branches></ul>
        </section>
        <section class="min-w-0" aria-labelledby="management-performance-title">
            <h3 id="management-performance-title" class="font-semibold">Randevu performansı</h3>
            <p class="mt-2 text-sm" data-management-performance></p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Kapasite, kayıtlı çalışma saatlerinden izin ve takvim engelleri çıkarılarak bulunur. Doluluk bu süredeki planlanan ve tamamlanan randevulardan hesaplanır. Gelmeme oranı tamamlanan ve gelinmeyen randevular üzerinden hesaplanır.</p>
            <p class="mt-2 text-sm" data-management-doctors-empty hidden>Bu dönemde aktif hekim görevi bulunmuyor.</p>
            <div class="mt-2 overflow-x-auto" data-management-doctors-table>
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-b border-slate-200 dark:border-slate-700">
                        <th scope="col" class="px-2 py-2">Şube ve hekim</th><th scope="col" class="px-2 py-2">Kapasite</th>
                        <th scope="col" class="px-2 py-2">Dolu süre</th><th scope="col" class="px-2 py-2">Doluluk</th>
                        <th scope="col" class="px-2 py-2">İptal</th><th scope="col" class="px-2 py-2">Gelmeme</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700" data-management-doctors></tbody>
                </table>
            </div>
        </section>
        <section class="grid gap-2" aria-labelledby="management-visits-title">
            <h3 id="management-visits-title" class="font-semibold">Randevular</h3>
            <ul class="divide-y divide-slate-200 dark:divide-slate-700" data-management-visits></ul>
            @include('clinic-expansion-pagination', ['label' => 'Yönetim randevu sayfaları', 'hook' => 'data-management-visit-pages'])
        </section>
        <section class="grid gap-2" aria-labelledby="management-tasks-title">
            <h3 id="management-tasks-title" class="font-semibold">Şube görevleri</h3>
            <ul class="divide-y divide-slate-200 dark:divide-slate-700" data-management-tasks></ul>
            @include('clinic-expansion-pagination', ['label' => 'Yönetim görev sayfaları', 'hook' => 'data-management-task-pages'])
        </section>
    </div>
</details>
