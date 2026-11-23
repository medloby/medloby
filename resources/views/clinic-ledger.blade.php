<details class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900" data-crm-ledger hidden>
    <summary class="cursor-pointer text-lg font-semibold">Finans</summary>
    @include('clinic-finance-reports')

    <h3 class="mt-5 font-semibold">Gelir ve gider kayıtları</h3>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-ledger-status role="status" hidden></p>
    <button type="button" class="offer-button offer-button-secondary mt-3" data-ledger-retry hidden>Listeyi yeniden yükle</button>

    <fieldset class="mt-4 grid min-w-0 gap-4" data-ledger-controls hidden>
        <div class="flex flex-wrap items-end gap-3">
            <label class="offer-field">Ay
                <input class="offer-control" type="month" data-ledger-month required>
            </label>
            <label class="offer-field">Kayıt durumu
                <select class="offer-control" data-ledger-filter>
                    <option value="active">Aktif kayıtlar</option>
                    <option value="voided">İptal edilenler</option>
                </select>
            </label>
            <button type="button" class="offer-button offer-button-secondary" data-ledger-refresh>Yenile</button>
            <button type="button" class="offer-button" data-ledger-new hidden>Yeni kayıt</button>
        </div>

        <ul class="grid gap-2 sm:grid-cols-2" aria-label="Aya ait toplamlar" data-ledger-totals></ul>
        <ul class="grid gap-2" aria-label="Gelir ve gider kayıtları" data-ledger-list></ul>
        <p class="text-sm text-slate-600 dark:text-slate-300" data-ledger-empty hidden>Bu ay için seçilen durumda kayıt yok.</p>
        <nav class="flex flex-wrap items-center justify-between gap-2" aria-label="Kayıt sayfaları" data-ledger-pages hidden>
            <button type="button" class="offer-button offer-button-secondary" data-ledger-previous>Önceki</button>
            <span class="text-sm text-slate-600 dark:text-slate-300" data-ledger-page></span>
            <button type="button" class="offer-button offer-button-secondary" data-ledger-next>Sonraki</button>
        </nav>

        <form method="post" class="grid min-w-0 gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700" data-ledger-editor hidden>
            <h3 class="font-semibold" data-ledger-editor-title></h3>
            <fieldset class="grid min-w-0 gap-3 sm:grid-cols-2" data-ledger-fields>
                <label class="offer-field">Tür
                    <select class="offer-control" name="kind">
                        <option value="income">Gelir</option>
                        <option value="expense">Gider</option>
                    </select>
                </label>
                <label class="offer-field">İşlem tarihi
                    <input class="offer-control" type="date" name="entry_date" required>
                </label>
                <label class="offer-field">Tutar
                    <input class="offer-control" type="number" name="amount" min="0.01" max="9999999999.99" step="0.01" required>
                </label>
                <label class="offer-field">Para birimi
                    <input class="offer-control" name="currency" pattern="[A-Za-z]{3}" minlength="3" maxlength="3" autocapitalize="characters" required>
                </label>
                <label class="offer-field sm:col-span-2">Açıklama
                    <input class="offer-control" name="description" maxlength="255" required>
                </label>
                <div class="grid gap-3 sm:col-span-2" data-ledger-income-reference>
                    <label class="offer-field">Gelir bağlantısı
                        <select class="offer-control" data-ledger-reference-type>
                            <option value="">Genel şube geliri</option>
                            <option value="appointment">Randevu veya paket seansı</option>
                            <option value="package">Paket toplamı</option>
                        </select>
                    </label>
                    <div class="grid gap-2" data-ledger-reference-fields hidden>
                        <div class="flex flex-wrap items-end gap-2">
                            <label class="offer-field">Kayıt numarasına göre bul
                                <input class="offer-control" type="number" min="1" step="1" data-ledger-reference-id>
                            </label>
                            <button type="button" class="offer-button offer-button-secondary" data-ledger-reference-search>Bul</button>
                        </div>
                        <label class="offer-field">Bağlanacak kayıt
                            <select class="offer-control" data-ledger-reference-choice></select>
                        </label>
                        <p class="text-sm text-slate-600 dark:text-slate-300" data-ledger-reference-status role="status" hidden></p>
                    </div>
                </div>
            </fieldset>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="offer-button" data-ledger-save>Kaydet</button>
                <button type="button" class="offer-button offer-button-secondary" data-ledger-change-status hidden></button>
                <button type="button" class="offer-button offer-button-secondary" data-ledger-close-editor>Kapat</button>
            </div>
        </form>
    </fieldset>

    <template data-ledger-total>
        <li class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
            <h3 class="font-semibold" data-ledger-currency></h3>
            <dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                <dt>Gelir</dt><dd class="text-end tabular-nums" data-ledger-income></dd>
                <dt>Gider</dt><dd class="text-end tabular-nums" data-ledger-expense></dd>
                <dt>Fark</dt><dd class="text-end font-semibold tabular-nums" data-ledger-net></dd>
            </dl>
        </li>
    </template>
    <template data-ledger-entry>
        <li class="flex min-w-0 flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
            <div class="min-w-0 break-words">
                <strong data-ledger-description></strong>
                <p class="text-sm text-slate-600 dark:text-slate-300" data-ledger-meta></p>
            </div>
            <button type="button" class="offer-button offer-button-secondary" data-ledger-open>Kaydı aç</button>
        </li>
    </template>
</details>
