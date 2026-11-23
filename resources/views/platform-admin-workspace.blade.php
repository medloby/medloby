<a href="#admin-overview" class="sr-only focus:not-sr-only focus:fixed focus:z-10 focus:rounded-lg focus:bg-white focus:p-3 focus:text-slate-950">Genel bakışa geç</a>
<div class="mx-auto grid min-h-screen max-w-[100rem] lg:grid-cols-[15rem_minmax(0,1fr)]">
    <aside class="grid content-start gap-5 bg-slate-950 px-4 py-5 text-slate-100 sm:px-6 lg:min-h-screen lg:py-8">
        <a href="{{ route('home') }}" class="inline-flex w-fit items-center gap-3 font-semibold">
            <span class="grid size-9 place-items-center rounded-lg bg-teal-300 text-sm font-bold text-slate-950" aria-hidden="true">M</span>
            Medloby yönetim
        </a>
        <nav class="flex flex-wrap gap-2 text-sm lg:grid lg:gap-1" aria-label="Platform yönetimi">
            <a href="#admin-overview" class="rounded-lg bg-slate-800 px-3 py-2.5 font-semibold text-teal-200" aria-current="page">Genel bakış</a>
            <a href="#admin-analytics" class="rounded-lg px-3 py-2.5 hover:bg-slate-800" data-admin-analytics-link>İşlem analitiği</a>
            <a href="#admin-operations" class="rounded-lg px-3 py-2.5 hover:bg-slate-800" data-admin-operations-link>Sistem durumu</a>
            <a href="#admin-audit" class="rounded-lg px-3 py-2.5 hover:bg-slate-800" data-admin-audit-link>İşlem geçmişi</a>
            @foreach ([
                'clinic-review' => 'Klinik başvuruları',
                'clinic-administration' => 'Klinik yönetimi',
                'patient-accounts' => 'Hasta hesapları',
                'treatment-administration' => 'Tedavi kataloğu',
                'shared-services' => 'Ortak ek hizmetler',
                'support-tickets' => 'Destek talepleri',
                'clinic-contracts' => 'Üyelik sözleşmeleri',
            ] as $action => $label)
                <button type="button" class="rounded-lg px-3 py-2.5 text-left hover:bg-slate-800 focus-visible:ring-2 focus-visible:ring-teal-300" data-admin-action="{{ $action }}">{{ $label }}</button>
            @endforeach
        </nav>
    </aside>
    <div class="min-w-0">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-4 sm:px-8 dark:border-slate-800 dark:bg-slate-900">
            <p class="min-w-0 break-words text-sm font-medium" data-admin-account></p>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="offer-button offer-button-secondary inline-flex items-center gap-2" data-admin-notifications hidden>
                    Bildirimler
                    <span class="rounded-full bg-teal-100 px-1.5 text-xs font-semibold text-teal-950" data-admin-notification-count aria-hidden="true" hidden></span>
                </button>
                <button type="button" class="offer-button offer-button-secondary" data-admin-logout>Çıkış yap</button>
            </div>
        </header>
        <main id="admin-overview" tabindex="-1" class="grid min-w-0 gap-6 px-4 py-6 sm:px-8 sm:py-8" aria-labelledby="admin-overview-title">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="grid gap-1">
                    <h1 id="admin-overview-title" class="text-3xl font-semibold tracking-tight">Genel bakış</h1>
                    <p class="text-sm text-slate-600 dark:text-slate-300" data-admin-updated hidden></p>
                </div>
                <button type="button" class="offer-button offer-button-secondary" data-admin-refresh>Yenile</button>
            </div>
            <p role="status" class="text-sm text-slate-700 dark:text-slate-200" data-admin-dashboard-status hidden></p>
            <details class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <summary class="cursor-pointer font-semibold">Destek görevlisi yetkisi</summary>
                <form class="mt-4 flex flex-wrap items-end gap-3" data-admin-support-role>
                    <label class="offer-field">Hesap numarası
                        <input class="offer-control" type="number" name="user_id" min="1" required>
                    </label>
                    <label class="offer-field">İşlem
                        <select class="offer-control" name="active" required>
                            <option value="">İşlem seçin</option>
                            <option value="1">Yetki ver</option>
                            <option value="0">Yetkiyi kaldır</option>
                        </select>
                    </label>
                    <button type="submit" class="offer-button">Kaydet</button>
                </form>
                <p role="status" class="mt-2 text-sm" data-admin-support-role-status hidden></p>
            </details>
            <div class="grid min-w-0 gap-6" data-admin-dashboard-results hidden>
                <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Platform sayımları">
                    @foreach ([
                        'clinics' => ['Kayıtlı klinik', ['active_clinics' => 'Aktif', 'suspended_clinics' => 'Askıda', 'pending_applications' => 'Başvuru bekliyor', 'active_branches' => 'Aktif şube']],
                        'patient_accounts' => ['Hasta hesabı', ['new_today' => 'Bugün yeni kullanıcı', 'new_month' => 'Bu ay yeni kullanıcı']],
                        'visits_today' => ['Bugün randevu', ['visits_week' => 'Bu takvim haftası', 'visits_month' => 'Bu takvim ayı']],
                        'open_tickets' => ['Açık destek talebi', ['awaiting_support_reply' => 'Destek yanıtı bekleyen', 'pending_offers' => 'Yanıt bekleyen teklif']],
                    ] as $metric => [$label, $details])
                        <article class="grid min-w-0 content-start gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900">
                            <h2 class="text-sm font-medium text-slate-600 dark:text-slate-300">{{ $label }}</h2>
                            <p class="break-words text-3xl font-semibold tabular-nums" data-admin-metric="{{ $metric }}"></p>
                            <dl class="grid gap-1 border-t border-slate-100 pt-3 text-sm dark:border-slate-800">
                                @foreach ($details as $key => $detail)
                                    <div class="flex flex-wrap justify-between gap-x-2">
                                        <dt>{{ $detail }}</dt>
                                        <dd class="font-medium tabular-nums" data-admin-metric="{{ $key }}"></dd>
                                    </div>
                                @endforeach
                            </dl>
                        </article>
                    @endforeach
                </section>
                <section class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="admin-alerts-title">
                    <h2 id="admin-alerts-title" class="font-semibold">Dikkat gerektiren işlemler</h2>
                    <ul class="grid gap-2" data-admin-alerts></ul>
                </section>
                <div class="grid min-w-0 gap-4 xl:grid-cols-2">
                    @foreach ([
                        'clinic-trend' => 'Klinik kayıt eğilimi',
                        'visit-trend' => 'Randevu hacmi',
                    ] as $chart => $label)
                        <section class="grid min-w-0 content-start gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="{{ $chart }}-title" data-admin-chart="{{ $chart }}">
                            <h2 id="{{ $chart }}-title" class="font-semibold">{{ $label }}</h2>
                            <p class="text-xs text-slate-600 dark:text-slate-300" data-chart-period></p>
                            <p class="text-sm text-slate-600 dark:text-slate-300" data-chart-empty hidden>Bu dönemde kayıt yok.</p>
                            <div class="grid h-28 grid-cols-[repeat(30,minmax(0,1fr))] items-end gap-px border-b border-slate-200 sm:gap-1 dark:border-slate-700" aria-hidden="true" data-chart-bars></div>
                            <details class="text-sm" data-chart-table>
                                <summary class="w-fit cursor-pointer font-medium text-teal-800 dark:text-teal-300">Günlük değerleri göster</summary>
                                <table class="mt-3 w-full text-sm" aria-labelledby="{{ $chart }}-title">
                                    <thead>
                                        <tr class="border-b border-slate-200 dark:border-slate-700">
                                            <th scope="col" class="py-1 text-left">Tarih</th>
                                            <th scope="col" class="py-1 text-right">Kayıt</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800" data-chart-rows></tbody>
                                </table>
                            </details>
                        </section>
                    @endforeach
                </div>
                <div class="grid min-w-0 gap-4 sm:grid-cols-2">
                    @foreach ([
                        'clinic-statuses' => 'Klinik başvuru durumları',
                        'appointment-statuses' => 'Randevu durumları',
                        'plans' => 'Üyelik planı dağılımı',
                        'support' => 'Destek talepleri',
                    ] as $chart => $label)
                        <section class="grid min-w-0 content-start gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="{{ $chart }}-title" data-admin-chart="{{ $chart }}">
                            <h2 id="{{ $chart }}-title" class="font-semibold">{{ $label }}</h2>
                            @if ($chart === 'appointment-statuses')
                                <p class="text-xs text-slate-600 dark:text-slate-300">Son 30 gün</p>
                            @elseif ($chart === 'plans')
                                <p class="text-xs text-slate-600 dark:text-slate-300">Ücretsiz ve isteğe bağlı planlar</p>
                            @endif
                            <ul class="grid gap-2 text-sm" data-chart-bars></ul>
                        </section>
                    @endforeach
                </div>
            </div>
            @include('platform-admin-analytics')
            @include('platform-admin-operations')
            @include('platform-admin-audit')
        </main>
    </div>
</div>

@include('clinic-review-dialog')
@include('clinic-administration-dialog')
@include('treatment-administration-dialog')
@include('clinic-contracts-dialog')
@include('package-service-dialog')
@include('support-tickets-dialog')
@include('platform-admin-patients-dialog')
@include('workspace-notifications')
