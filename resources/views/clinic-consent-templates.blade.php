<details class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900" data-crm-consent-templates hidden>
    <summary class="cursor-pointer text-lg font-semibold">Onam taslakları</summary>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">Kliniğinizin genel onam metinlerini hazırlayın. Hasta adı ve diğer kişisel bilgileri girmeyin.</p>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-consents-status role="status" hidden></p>
    <button type="button" class="offer-button offer-button-secondary mt-3" data-consents-retry hidden>Listeyi yeniden yükle</button>

    <fieldset class="mt-4 grid min-w-0 gap-4" data-consents-controls hidden>
        <label class="offer-field">
            Taslak seçimi
            <select class="offer-control" data-consents-picker></select>
        </label>
        <nav class="flex flex-wrap items-center justify-between gap-2" aria-label="Onam taslağı sayfaları" data-consents-pages hidden>
            <button type="button" class="offer-button offer-button-secondary" data-consents-previous>Önceki</button>
            <span class="text-sm text-slate-600 dark:text-slate-300" data-consents-page></span>
            <button type="button" class="offer-button offer-button-secondary" data-consents-next>Sonraki</button>
        </nav>
        <form method="post" class="grid min-w-0 gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700" data-consents-form>
            <label class="offer-field">
                Taslak başlığı
                <input class="offer-control" name="title" maxlength="255" required>
            </label>
            <label class="offer-field">
                Onam metni
                <textarea class="offer-control" name="content" rows="7" maxlength="20000" required></textarea>
            </label>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="offer-button">Taslağı kaydet</button>
                <button type="button" class="offer-button offer-button-secondary" data-consents-reload hidden>Taslağı yeniden yükle</button>
            </div>
        </form>
        <div class="grid justify-items-start gap-2" data-consents-preparation hidden>
            <p class="text-sm text-slate-600 dark:text-slate-300" data-consents-prepared-label></p>
            <button type="button" class="offer-button offer-button-secondary" data-consents-prepare>Kaydedilen metni sürüm olarak hazırla</button>
        </div>
        <details class="min-w-0 rounded-xl border border-slate-200 p-4 dark:border-slate-700" data-consents-history hidden>
            <summary class="cursor-pointer font-semibold">Hazırlanan sürümler</summary>
            <div class="mt-3 grid gap-3">
                <label class="offer-field" data-consents-version-field hidden>
                    Hazırlanan sürüm
                    <select class="offer-control" data-consents-version-picker></select>
                </label>
                <nav class="flex flex-wrap items-center justify-between gap-2" aria-label="Onam sürümü sayfaları" data-consents-version-pages hidden>
                    <button type="button" class="offer-button offer-button-secondary" data-consents-version-previous>Önceki</button>
                    <span class="text-sm text-slate-600 dark:text-slate-300" data-consents-version-page></span>
                    <button type="button" class="offer-button offer-button-secondary" data-consents-version-next>Sonraki</button>
                </nav>
                <article class="min-w-0 rounded-lg bg-slate-50 p-3 dark:bg-slate-800" data-consents-version-preview hidden>
                    <h3 class="break-words font-semibold" data-consents-version-title></h3>
                    <p class="mt-2 whitespace-pre-wrap break-words text-sm" data-consents-version-content></p>
                </article>
            </div>
        </details>
    </fieldset>
</details>
