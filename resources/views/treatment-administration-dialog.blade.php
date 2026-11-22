<dialog id="treatment-administration-dialog" class="offer-dialog sm:max-w-3xl" aria-labelledby="treatment-administration-title">
    <div class="flex items-start justify-between gap-4">
        <h2 id="treatment-administration-title" class="text-2xl font-semibold tracking-tight">Tedavi kataloğu yönetimi</h2>
        <button type="button" class="offer-button offer-button-secondary" data-treatment-close>Kapat</button>
    </div>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-treatment-status role="status" hidden></p>
    <button type="button" class="offer-button offer-button-secondary mt-4" data-treatment-retry hidden>Yeniden yükle</button>

    <fieldset class="mt-5 grid min-w-0 gap-4" data-treatment-controls hidden>
        <form method="post" class="grid min-w-0 gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700" data-treatment-category-form>
            <h3 class="text-lg font-semibold">Kategoriler</h3>
            <label class="offer-field">
                Kategori kaydı
                <select class="offer-control" data-treatment-category-record></select>
            </label>
            <label class="offer-field">
                Kategori adı
                <input class="offer-control" name="name" maxlength="255" required>
            </label>
            <label class="offer-field" data-treatment-new-slug>
                Kategori adres adı (isteğe bağlı)
                <input class="offer-control" name="slug" maxlength="255" autocomplete="off">
            </label>
            <label class="offer-field">
                Kategori açıklaması
                <textarea class="offer-control" name="description" rows="2"></textarea>
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input class="size-4 accent-teal-800" name="is_active" type="checkbox">
                Kategori aktif
            </label>
            <button type="submit" class="offer-button justify-self-start">Kategoriyi kaydet</button>
        </form>

        <form method="post" class="grid min-w-0 gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700" data-treatment-definition-form>
            <h3 class="text-lg font-semibold">Tedaviler</h3>
            <label class="offer-field">
                Tedavi kaydı
                <select class="offer-control" data-treatment-definition-record></select>
            </label>
            <label class="offer-field">
                Bağlı kategori
                <select class="offer-control" name="treatment_category_id" required></select>
            </label>
            <label class="offer-field">
                Tedavi adı
                <input class="offer-control" name="name" maxlength="255" required>
            </label>
            <label class="offer-field" data-treatment-new-slug>
                Tedavi adres adı (isteğe bağlı)
                <input class="offer-control" name="slug" maxlength="255" autocomplete="off">
            </label>
            <label class="offer-field">
                Varsayılan seans süresi (dakika)
                <input class="offer-control" name="duration_minutes" type="number" min="1" max="4294967295" step="1">
            </label>
            <label class="offer-field">
                Tedavi açıklaması
                <textarea class="offer-control" name="description" rows="2"></textarea>
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input class="size-4 accent-teal-800" name="is_active" type="checkbox">
                Tedavi aktif
            </label>
            <button type="submit" class="offer-button justify-self-start">Tedaviyi kaydet</button>
        </form>
    </fieldset>
</dialog>
