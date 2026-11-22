<dialog id="catalog-dialog" class="offer-dialog sm:max-w-3xl" aria-labelledby="catalog-title">
    <div class="flex items-start justify-between gap-4">
        <h2 id="catalog-title" class="text-2xl font-semibold tracking-tight">Paket kataloğu</h2>
        <button type="button" class="offer-button offer-button-secondary" data-catalog-close>Kapat</button>
    </div>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-catalog-status role="status" hidden></p>

    <fieldset class="mt-5 grid gap-5" data-catalog-controls>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="offer-field">
                İşletme
                <select class="offer-control" data-catalog-business></select>
            </label>
            <label class="offer-field">
                Paket
                <select class="offer-control" data-catalog-package></select>
            </label>
        </div>

        <button type="button" class="offer-button offer-button-secondary justify-self-start" data-catalog-manage-services hidden>Ek hizmetleri yönet</button>

        <form method="post" class="grid gap-4 rounded-xl border border-slate-200 p-4 dark:border-slate-700" data-catalog-metadata hidden>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="offer-field">
                    Paket adı
                    <input class="offer-control" name="name" maxlength="255" required>
                </label>
                <label class="offer-field">
                    Şube
                    <select class="offer-control" name="branch_id"></select>
                </label>
                <label class="offer-field">
                    Paket fiyatı
                    <input class="offer-control" type="number" name="price" min="0" step="0.01">
                </label>
                <label class="offer-field">
                    Para birimi
                    <input class="offer-control" name="currency" minlength="3" maxlength="3" pattern="[A-Za-z]{3}" required>
                </label>
                <label class="offer-field">
                    Geçerlilik başlangıcı
                    <input class="offer-control" type="date" name="valid_from">
                </label>
                <label class="offer-field">
                    Geçerlilik sonu
                    <input class="offer-control" type="date" name="valid_until">
                </label>
            </div>
            <label class="offer-field">
                Paket açıklaması
                <textarea class="offer-control" name="description" rows="2"></textarea>
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input class="size-4 accent-teal-800" type="checkbox" name="is_offer_enabled">
                Tekliflerde kullanılabilir
            </label>
            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="offer-button" data-catalog-save>Taslak oluştur</button>
                <button type="button" class="offer-button offer-button-secondary" data-catalog-publish hidden></button>
            </div>
        </form>

        <div class="grid gap-4" data-catalog-contents hidden>
            @foreach (['items' => ['Tedaviler', 'Tedavi'], 'service-items' => ['Ek hizmetler', 'Ek hizmet']] as $itemType => [$heading, $itemLabel])
                <section class="grid gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700" data-catalog-items="{{ $itemType }}">
                    <h3 class="text-lg font-semibold">{{ $heading }}</h3>
                    <ul class="grid gap-2" data-catalog-rows></ul>
                    <form method="post" class="grid items-end gap-2 sm:grid-cols-[minmax(0,1fr)_6rem_auto]" data-catalog-add-item>
                        <label class="offer-field">{{ $itemLabel }}
                            <select class="offer-control" name="item_id" required></select>
                        </label>
                        <label class="offer-field">Adet
                            <input class="offer-control" type="number" name="quantity" min="1" step="1" value="1" required>
                        </label>
                        <button class="offer-button" type="submit">Ekle</button>
                    </form>
                </section>
            @endforeach

        </div>
    </fieldset>

    <template data-catalog-row>
        <li class="grid gap-2 rounded-lg bg-slate-50 p-3 text-sm dark:bg-slate-950">
            <strong class="break-words" data-item-name></strong>
            <form method="post" class="flex flex-wrap items-end gap-2">
                <label class="offer-field w-20">Adet <input name="quantity" class="offer-control" type="number" min="1" step="1" required></label>
                <label class="offer-field w-20">Sıra <input name="sort_order" class="offer-control" type="number" min="0" step="1" required></label>
                <button type="submit" class="offer-button offer-button-secondary">Güncelle</button>
                <button type="button" class="offer-button offer-button-secondary" data-item-remove>Kaldır</button>
            </form>
        </li>
    </template>
</dialog>
