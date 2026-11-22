<details class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900" data-crm-branch-management hidden>
    <summary class="cursor-pointer text-lg font-semibold">Şubeleri yönet</summary>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-branches-status role="status" hidden></p>
    <button type="button" class="offer-button offer-button-secondary mt-3" data-branches-retry hidden>Şubeleri yeniden yükle</button>
    <fieldset class="mt-4 grid min-w-0 gap-4" data-branches-content hidden>
        <p class="text-sm text-slate-600 dark:text-slate-300">Pasif şubeler klinik aramasından ve şube seçiminden çıkar. Çevrimiçi randevuya kapanır.</p>
        <ul class="grid gap-2" data-branches-list></ul>
        <p class="text-sm text-slate-600 dark:text-slate-300" data-branches-empty hidden>Bu kliniğin henüz şubesi yok.</p>
        <details class="rounded-xl border border-slate-200 p-4 dark:border-slate-700" data-branches-create>
            <summary class="cursor-pointer font-semibold">Yeni şube ekle</summary>
            <form method="post" class="mt-3 grid gap-3" data-branches-form>
                <label class="offer-field">Yeni şubenin adı
                    <input class="offer-control" name="name" maxlength="255" required>
                </label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="offer-field">İl
                        <input class="offer-control" name="city" maxlength="100" autocomplete="address-level1">
                    </label>
                    <label class="offer-field">İlçe
                        <input class="offer-control" name="district" maxlength="100" autocomplete="address-level2">
                    </label>
                </div>
                <label class="offer-field">Şube adresi
                    <textarea class="offer-control" name="address" rows="2" maxlength="2000" autocomplete="street-address"></textarea>
                </label>
                <button type="submit" class="offer-button justify-self-start">Şubeyi ekle</button>
            </form>
        </details>
    </fieldset>
    <template data-branches-row>
        <li class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 p-3 dark:border-slate-700">
            <div class="min-w-0">
                <p class="break-words font-medium" data-branches-name></p>
                <p class="mt-1 break-words text-sm text-slate-600 dark:text-slate-300" data-branches-meta></p>
            </div>
            <button class="offer-button offer-button-secondary" type="button" data-branches-action></button>
        </li>
    </template>
</details>
