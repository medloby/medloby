<details class="mt-4 rounded-xl border border-slate-200 p-3 sm:p-4 dark:border-slate-700" data-ledger-balances>
    <summary class="cursor-pointer font-semibold">Borç, alacak ve hasta ödemeleri</summary>
    <div class="mt-3 flex flex-wrap items-end gap-2">
        <label class="offer-field">Kayıt durumu
            <select class="offer-control" data-balances-filter>
                <option value="active">Aktif borç ve alacaklar</option>
                <option value="overdue">Vadesi geçenler</option>
                <option value="voided">İptal edilenler</option>
            </select>
        </label>
        <button type="button" class="offer-button offer-button-secondary" data-balances-refresh>Yenile</button>
        <button type="button" class="offer-button" data-balances-new hidden>Yeni borç veya alacak</button>
    </div>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" role="status" data-balances-status hidden></p>
    <form class="mt-3 grid gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700" data-balances-editor hidden>
        <fieldset class="grid min-w-0 gap-3 sm:grid-cols-2">
            <legend class="font-semibold" data-balances-editor-title></legend>
            <label class="offer-field">Tür
                <select class="offer-control" name="direction"><option value="receivable">Alacak</option><option value="payable">Borç</option></select>
            </label>
            <label class="offer-field">Hesap
                <select class="offer-control" data-balances-party><option value="named">Kişi veya firma</option><option value="patient">Kayıtlı hasta</option></select>
            </label>
            <label class="offer-field sm:col-span-2" data-balances-counterparty>Kişi veya firma adı
                <input class="offer-control" name="counterparty" maxlength="120">
            </label>
            <div class="grid gap-2 sm:col-span-2" data-balances-patient hidden>
                <div class="flex flex-wrap items-end gap-2">
                    <label class="offer-field">Hasta adı
                        <input class="offer-control" type="search" maxlength="80" data-balances-query>
                    </label>
                    <button type="button" class="offer-button offer-button-secondary" data-balances-search>Hasta ara</button>
                </div>
                <label class="offer-field">Hasta seçin
                    <select class="offer-control" name="patient_profile_id"></select>
                </label>
            </div>
            <label class="offer-field">Tutar
                <input class="offer-control" type="number" name="amount" min=".01" max="9999999999.99" step=".01" required>
            </label>
            <label class="offer-field">Para birimi
                <input class="offer-control" name="currency" pattern="[A-Za-z]{3}" value="TRY" maxlength="3" required>
            </label>
            <label class="offer-field">Vade
                <input class="offer-control" type="date" name="due_on">
            </label>
            <label class="offer-field">Açıklama
                <input class="offer-control" name="description" maxlength="255" required>
            </label>
        </fieldset>
        <div class="flex flex-wrap gap-2">
            <button type="submit" class="offer-button">Kaydet</button>
            <button type="button" class="offer-button offer-button-secondary" data-balances-close>Kapat</button>
        </div>
    </form>
    <ul class="mt-3 grid gap-3" data-balances-list></ul>
    @include('clinic-expansion-pagination', ['label' => 'Borç ve alacak sayfaları', 'hook' => 'data-balances-pages'])
    <section class="mt-4 grid gap-2" data-balances-history hidden>
        <h3 class="font-semibold" data-balances-history-title></h3>
        <ul class="divide-y divide-slate-200 dark:divide-slate-700" data-balances-history-list></ul>
        @include('clinic-expansion-pagination', ['label' => 'Ödeme hareketi sayfaları', 'hook' => 'data-balances-history-pages'])
    </section>
</details>
