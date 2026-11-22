<details class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900" data-crm-social-links hidden>
    <summary class="cursor-pointer text-lg font-semibold">Sosyal medya bağlantıları</summary>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-social-status role="status" hidden></p>
    <button type="button" class="offer-button offer-button-secondary mt-3" data-social-retry hidden>Bağlantıları yeniden yükle</button>
    <form method="post" class="mt-4" data-social-form hidden>
        <fieldset class="grid min-w-0 gap-3">
            <label class="offer-field">
                Bağlantı kaydı
                <select class="offer-control" data-social-record></select>
            </label>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="offer-field">Platform
                    <input class="offer-control" name="platform" maxlength="50" placeholder="Örn. Instagram" required>
                </label>
                <label class="offer-field">Kullanıcı adı (isteğe bağlı)
                    <input class="offer-control" name="username" maxlength="255">
                </label>
                <label class="offer-field">Görünen ad (isteğe bağlı)
                    <input class="offer-control" name="page_name" maxlength="255">
                </label>
                <label class="offer-field">Sıra
                    <input class="offer-control" type="number" name="sort_order" min="0" max="4294967295" step="1" value="0" required>
                </label>
            </div>
            <label class="offer-field">Profil bağlantısı
                <input class="offer-control" name="profile_url" type="url" maxlength="2048" inputmode="url" required>
            </label>
            <label class="flex items-center gap-2 text-sm" data-social-new-publication>
                <input class="size-4 accent-teal-800" name="publish" type="checkbox" checked>
                Klinik sayfasında yayımla
            </label>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="offer-button">Bağlantıyı kaydet</button>
                <button type="button" class="offer-button offer-button-secondary" data-social-publication hidden></button>
            </div>
        </fieldset>
    </form>
</details>
