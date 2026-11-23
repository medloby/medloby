<details class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900" data-crm-inventory hidden>
    <summary class="cursor-pointer text-lg font-semibold">Stok ve malzeme</summary>
    <form class="mt-3 flex flex-wrap items-end gap-3" data-inventory-filter>
        <label class="offer-field">Malzeme veya kod
            <input class="offer-control" name="q" type="search" maxlength="80">
        </label>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="low" value="1"> Yalnızca düşük stok
        </label>
        <button type="submit" class="offer-button offer-button-secondary">Göster</button>
        <button type="button" class="offer-button" data-inventory-new hidden>Malzeme ekle</button>
    </form>
    <p class="mt-3 text-sm" role="status" data-inventory-status hidden></p>
    <p class="mt-3 rounded-lg bg-amber-50 p-2 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-100" data-inventory-warning hidden></p>
    <form class="mt-3 grid gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700" data-inventory-editor hidden>
        <fieldset class="grid min-w-0 gap-3 sm:grid-cols-2">
            <legend class="font-semibold" data-inventory-editor-title>Malzeme</legend>
            <label class="offer-field">Malzeme kodu
                <input class="offer-control" name="code" maxlength="60" required>
            </label>
            <label class="offer-field">Malzeme adı
                <input class="offer-control" name="name" maxlength="160" required>
            </label>
            <label class="offer-field">Birim
                <input class="offer-control" name="unit" maxlength="24" value="Adet" required>
            </label>
            <label class="offer-field">Uyarı eşiği
                <input class="offer-control" type="number" name="minimum_stock" min="0" max="2147483647" value="0" required>
            </label>
        </fieldset>
        <div class="flex flex-wrap gap-2">
            <button type="submit" class="offer-button">Malzemeyi kaydet</button>
            <button type="button" class="offer-button offer-button-secondary" data-inventory-close-editor>Kapat</button>
        </div>
    </form>
    <form class="mt-3 grid gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700" data-inventory-movement hidden>
        <fieldset class="grid min-w-0 gap-3 sm:grid-cols-2">
            <legend class="font-semibold" data-inventory-movement-title></legend>
            <label class="offer-field">Stok hareketi
                <select class="offer-control" name="direction"><option value="in">Giriş</option><option value="out">Çıkış</option></select>
            </label>
            <label class="offer-field">Miktar
                <input class="offer-control" type="number" name="quantity" min="1" max="2147483647" required>
            </label>
            <label class="offer-field sm:col-span-2">Açıklama
                <input class="offer-control" name="description" maxlength="255" required>
            </label>
        </fieldset>
        <div class="flex flex-wrap gap-2">
            <button type="submit" class="offer-button">Hareketi kaydet</button>
            <button type="button" class="offer-button offer-button-secondary" data-inventory-close-movement>Kapat</button>
        </div>
    </form>
    <ul class="mt-3 grid gap-3" data-inventory-list></ul>
    @include('clinic-expansion-pagination', ['label' => 'Stok sayfaları', 'hook' => 'data-inventory-pages'])
    <section class="mt-4 grid gap-2" data-inventory-history hidden>
        <h3 class="font-semibold" data-inventory-history-title></h3>
        <ul class="divide-y divide-slate-200 dark:divide-slate-700" data-inventory-history-list></ul>
        @include('clinic-expansion-pagination', ['label' => 'Stok hareketi sayfaları', 'hook' => 'data-inventory-history-pages'])
    </section>
</details>
