<details class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900" data-crm-operations hidden>
    <summary class="cursor-pointer text-lg font-semibold">Klinik operasyonları</summary>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-operations-status role="status" hidden></p>
    <fieldset class="mt-4 grid min-w-0 gap-3" data-operations-content hidden>
        <details class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
            <summary class="cursor-pointer font-semibold">Personel ve şube yetkileri</summary>
            <form class="mt-4 grid gap-3" data-operations-staff-form>
                <p class="text-sm text-slate-600 dark:text-slate-300">Doğrulanmış bir Medloby hesabına bu şubenin CRM erişimini verin.</p>
                <label class="offer-field max-w-sm">
                    Personelin e-postası
                    <input class="offer-control" name="email" type="email" maxlength="255" required>
                </label>
                <button type="submit" class="offer-button justify-self-start">Personele şube yetkisi ver</button>
            </form>
            <p class="mt-4 text-sm text-slate-600 dark:text-slate-300" data-operations-empty-staff hidden>Bu kliniğe kayıtlı personel yok.</p>
            <ul class="mt-4 grid gap-2" data-operations-staff></ul>
        </details>

        <details class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
            <summary class="cursor-pointer font-semibold">Şube tedavileri</summary>
            <form class="mt-4 grid gap-3" data-operations-treatment-form>
                <label class="offer-field max-w-sm">
                    Katalog tedavisi
                    <select class="offer-control" name="treatment_id" required></select>
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_online_bookable">
                    Çevrimiçi randevuya aç
                </label>
                <button type="submit" class="offer-button justify-self-start">Tedaviyi şubeye ekle</button>
            </form>
            <p class="mt-4 text-sm text-slate-600 dark:text-slate-300" data-operations-empty-treatments hidden>Bu şubede henüz tedavi yok.</p>
            <p class="mt-4 text-sm text-slate-600 dark:text-slate-300" data-operations-empty-catalog hidden>Katalogda eklenebilecek başka aktif tedavi yok.</p>
            <ul class="mt-4 grid gap-2" data-operations-treatments></ul>
        </details>

        <details class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
            <summary class="cursor-pointer font-semibold">Hekimler ve tedavileri</summary>
            <form class="mt-4 grid gap-3" data-operations-doctor-form>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="offer-field">
                        Hekimin adı
                        <input class="offer-control" name="first_name" maxlength="255" required>
                    </label>
                    <label class="offer-field">
                        Hekimin soyadı
                        <input class="offer-control" name="last_name" maxlength="255" required>
                    </label>
                    <label class="offer-field">
                        Uzmanlık (isteğe bağlı)
                        <input class="offer-control" name="specialty" maxlength="255">
                    </label>
                    <label class="offer-field">
                        Ruhsat numarası (isteğe bağlı)
                        <input class="offer-control" name="license_number" maxlength="255">
                    </label>
                </div>
                <button type="submit" class="offer-button justify-self-start">Hekimi şubeye kaydet</button>
            </form>

            <div class="mt-5 grid gap-3 border-t border-slate-200 pt-4 dark:border-slate-700" data-operations-doctor-picker hidden>
                <label class="offer-field max-w-sm">
                    Mevcut hekim
                    <select class="offer-control" data-operations-doctors></select>
                </label>
                <div class="grid gap-3" data-operations-doctor-detail hidden>
                    <p class="break-words text-sm text-slate-600 dark:text-slate-300" data-operations-doctor-meta></p>
                    <button type="button" class="offer-button offer-button-secondary justify-self-start" data-operations-doctor-branch></button>
                    <div class="grid gap-2">
                        <button type="button" class="offer-button offer-button-secondary justify-self-start" aria-describedby="doctor-online-booking-scope" data-operations-doctor-online-booking></button>
                        <p id="doctor-online-booking-scope" class="text-sm text-slate-600 dark:text-slate-300">Hekimin çevrimiçi randevu ayarı bağlı olduğu tüm şubelerde geçerlidir.</p>
                    </div>
                    <div class="grid gap-2 text-sm" data-operations-doctor-links hidden>
                        <h4 class="font-semibold">Hekim–tedavi bağlantıları</h4>
                        <p class="text-slate-600 dark:text-slate-300">Hekimin tedavi bağlantısı, kliniğin bu tedaviyi sunduğu diğer şubelerde de geçerlidir.</p>
                        <p class="text-slate-600 dark:text-slate-300" data-operations-doctor-no-treatments hidden>Önce şubeye bir tedavi ekleyin.</p>
                        <ul class="grid gap-2" data-operations-doctor-treatments></ul>
                    </div>
                </div>
            </div>
        </details>
    </fieldset>

    <template data-operations-row>
        <li class="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-slate-200 p-3 dark:border-slate-700">
            <div class="min-w-0">
                <p class="break-words font-medium" data-operations-name></p>
                <p class="mt-1 break-words text-sm text-slate-600 dark:text-slate-300" data-operations-meta></p>
            </div>
            <div class="flex flex-wrap gap-2" data-operations-actions></div>
        </li>
    </template>
    <template data-operations-treatment-duration>
        <form class="mt-2 flex flex-wrap items-end gap-2">
            <label class="offer-field">
                Şubedeki randevu süresi (dakika)
                <input class="offer-control" name="duration_minutes" type="number" min="1" step="1" inputmode="numeric">
            </label>
            <button type="submit" class="offer-button offer-button-secondary">Süreyi kaydet</button>
        </form>
    </template>
</details>
