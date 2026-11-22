<dialog id="offer-travel-dialog" class="offer-dialog" aria-labelledby="offer-travel-title">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 id="offer-travel-title" class="text-2xl font-semibold">Seyahat planı</h2>
            <p class="mt-1 break-words text-sm text-slate-600 dark:text-slate-300" data-travel-offer></p>
        </div>
        <button type="button" class="offer-button offer-button-secondary" data-travel-close>Kapat</button>
    </div>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-travel-status role="status" hidden></p>
    <button type="button" class="offer-button offer-button-secondary mt-3" data-travel-refresh>Planı yenile</button>

    <div class="mt-4 grid gap-4" data-travel-preview hidden>
        <section class="grid gap-2" data-travel-preview-hotel hidden>
            <h3 class="font-semibold">Konaklama</h3>
            <p class="whitespace-pre-line break-words" data-travel-hotel-text></p>
        </section>
        <section class="grid gap-2" data-travel-preview-transfer hidden>
            <h3 class="font-semibold">Transferler</h3>
            <ul class="grid gap-2" data-travel-transfer-list></ul>
            <p class="text-sm text-slate-600 dark:text-slate-300" data-travel-no-transfers hidden>Klinik transfer bilgilerini hazırlıyor.</p>
        </section>
    </div>

    <form method="post" class="mt-4" data-travel-form hidden>
        <fieldset class="grid min-w-0 gap-4" data-travel-controls>
            <section class="grid gap-2 rounded-xl border border-slate-200 p-4 dark:border-slate-700" data-travel-edit-hotel hidden>
                <h3 class="font-semibold">Konaklama</h3>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="hotel_ready" class="size-4 accent-teal-800">
                    Konaklama ayrıntısı hazır
                </label>
                <fieldset class="grid min-w-0 gap-3 sm:grid-cols-2" data-travel-hotel-fields>
                    <label class="offer-field">Otel veya tesis adı
                        <input class="offer-control" name="name" maxlength="180" required>
                    </label>
                    <label class="offer-field">Adres
                        <input class="offer-control" name="address" maxlength="500">
                    </label>
                    <label class="offer-field">Giriş tarihi
                        <input class="offer-control" name="check_in" type="date" required>
                    </label>
                    <label class="offer-field">Çıkış tarihi
                        <input class="offer-control" name="check_out" type="date" required>
                    </label>
                </fieldset>
            </section>
            <section class="grid gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700" data-travel-edit-transfer hidden>
                <h3 class="font-semibold">Transferler</h3>
                <p class="text-sm text-slate-600 dark:text-slate-300">Saatleri Türkiye saatiyle girin.</p>
                <ol class="grid gap-3" data-travel-rows></ol>
                <button type="button" class="offer-button offer-button-secondary justify-self-start" data-travel-add>Transfer ekle</button>
            </section>
            <button type="submit" class="offer-button justify-self-start">Seyahat planını kaydet</button>
        </fieldset>
    </form>

    <template data-travel-row>
        <li class="grid min-w-0 gap-3 rounded-lg border border-slate-200 p-3 dark:border-slate-700">
            <label class="offer-field">Transfer tarihi ve saati
                <input class="offer-control" type="datetime-local" required data-transfer="starts_at">
            </label>
            <div class="grid min-w-0 gap-3 sm:grid-cols-2">
                <label class="offer-field">Alış yeri
                    <input class="offer-control" maxlength="240" required data-transfer="from">
                </label>
                <label class="offer-field">Varış yeri
                    <input class="offer-control" maxlength="240" required data-transfer="to">
                </label>
            </div>
            <button type="button" class="offer-button offer-button-secondary justify-self-start" data-travel-remove>Transferi kaldır</button>
        </li>
    </template>
</dialog>
