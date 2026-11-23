<dialog class="offer-dialog max-w-4xl" data-admin-patients aria-labelledby="admin-patients-title">
    <header class="flex flex-wrap items-center justify-between gap-3">
        <h2 id="admin-patients-title" class="text-2xl font-semibold tracking-tight">Hasta hesapları</h2>
        <button type="button" class="offer-button offer-button-secondary" data-patients-close>Kapat</button>
    </header>
    <form class="mt-4 flex flex-wrap items-end gap-3" data-patients-filters>
        <label class="offer-field">Ad, e-posta veya hesap numarası
            <input type="search" name="search" maxlength="120" class="offer-control">
        </label>
        <label class="offer-field">E-posta doğrulaması
            <select name="verification" class="offer-control">
                <option value="">Tümü</option>
                <option value="verified">Doğrulanmış</option>
                <option value="pending">Doğrulama bekliyor</option>
            </select>
        </label>
        <button class="offer-button" type="submit">Ara</button>
    </form>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-patients-status role="status" hidden></p>
    <ul class="mt-4 grid gap-2" data-patients-list></ul>
    <nav class="mt-4 flex flex-wrap items-center gap-3" data-patients-pages aria-label="Hasta hesabı sayfaları" hidden>
        <button type="button" class="offer-button offer-button-secondary" data-page-previous>Önceki</button>
        <span class="text-sm" data-page-number></span>
        <button type="button" class="offer-button offer-button-secondary" data-page-next>Sonraki</button>
    </nav>
</dialog>
