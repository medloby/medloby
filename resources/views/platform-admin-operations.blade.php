<details id="admin-operations" class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900" data-admin-operations>
    <summary class="cursor-pointer font-semibold">Sistem durumu</summary>
    <p role="status" class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-report-status hidden></p>
    <div class="mt-4 grid gap-4" data-report-result hidden>
        <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
            @foreach ([
                'application' => 'Uygulama',
                'database' => 'Veritabanı erişimi',
                'migrations' => 'Veritabanı şeması',
                'queue_configuration' => 'Kuyruk yapılandırması',
                'queue_worker' => 'Kuyruk işçisi',
                'scheduler' => 'Zamanlayıcı',
                'smtp_configuration' => 'E-posta yapılandırması',
                'email_delivery' => 'Canlı e-posta teslimatı',
                'backup_verification' => 'Son başarılı yedek doğrulaması',
                'privacy_publication' => 'KVKK aydınlatma yayını ayarı',
                'release_version' => 'Uygulama sürümü',
            ] as $key => $label)
                <div class="flex flex-wrap justify-between gap-x-2 border-b border-slate-100 py-2 dark:border-slate-800">
                    <dt>{{ $label }}</dt>
                    <dd class="break-all font-medium" data-operation="{{ $key }}"></dd>
                </div>
            @endforeach
        </dl>
        <button type="button" class="offer-button offer-button-secondary w-fit" data-report-refresh>Durumu yenile</button>
    </div>
</details>
