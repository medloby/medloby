<dialog id="notifications-dialog" class="offer-dialog" aria-labelledby="notifications-title">
    <div class="flex items-start justify-between gap-4">
        <h2 id="notifications-title" class="text-2xl font-semibold tracking-tight">Bildirimler</h2>
        <button type="button" class="offer-button offer-button-secondary" data-notifications-close>Kapat</button>
    </div>
    <div class="mt-4 flex flex-wrap gap-2">
        <button type="button" class="offer-button offer-button-secondary" data-notifications-refresh>Yenile</button>
        <button type="button" class="offer-button offer-button-secondary" data-notifications-read-all>Tümünü okundu işaretle</button>
    </div>
    <details class="mt-4 rounded-xl border border-slate-200 p-3 dark:border-slate-700" data-notifications-preferences hidden>
        <summary class="cursor-pointer text-sm font-semibold text-teal-800 dark:text-teal-300">Klinik bildirim ayarları</summary>
        <div class="mt-3 grid gap-3">
            <label class="offer-field">
                İşletme
                <select class="offer-control" data-notifications-business></select>
            </label>
            <div class="grid gap-2" data-notifications-preference-list></div>
            <p class="text-sm text-slate-600 dark:text-slate-300" data-notifications-preference-status role="status" hidden></p>
        </div>
    </details>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-notifications-status role="status" hidden></p>
    <ul class="mt-4 grid gap-2" data-notifications-list></ul>
    <nav class="mt-4 flex flex-wrap items-center justify-between gap-2" aria-label="Bildirim sayfaları" data-notifications-pages hidden>
        <button type="button" class="offer-button offer-button-secondary" data-notifications-previous>Önceki</button>
        <span class="text-sm text-slate-600 dark:text-slate-300" data-notifications-page></span>
        <button type="button" class="offer-button offer-button-secondary" data-notifications-next>Sonraki</button>
    </nav>

    <template data-notification-row>
        <li class="grid gap-2 rounded-xl border border-slate-200 bg-white p-4 data-[unread=true]:border-teal-300 data-[unread=true]:bg-teal-50 dark:border-slate-700 dark:bg-slate-950 dark:data-[unread=true]:border-teal-700 dark:data-[unread=true]:bg-teal-950">
            <div class="flex flex-wrap items-start justify-between gap-1">
                <strong class="break-words" data-notification-title></strong>
                <time class="text-xs text-slate-600 dark:text-slate-300" data-notification-time></time>
            </div>
            <p class="whitespace-pre-wrap break-words text-sm text-slate-700 dark:text-slate-200" data-notification-message></p>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="offer-button" data-notification-open hidden>Görüntüle</button>
                <button type="button" class="offer-button offer-button-secondary" data-notification-read hidden>Okundu işaretle</button>
            </div>
        </li>
    </template>
</dialog>
