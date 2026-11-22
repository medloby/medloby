<dialog id="support-tickets-dialog" class="offer-dialog" aria-labelledby="support-tickets-title">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <h2 id="support-tickets-title" class="text-2xl font-semibold tracking-tight" data-support-heading>Destek talepleri</h2>
        <button type="button" class="offer-button offer-button-secondary" data-support-close>Kapat</button>
    </div>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" role="status" data-support-status hidden></p>

    <fieldset class="mt-4 min-w-0" data-support-workspace>
        <section class="grid gap-3" data-support-overview aria-label="Destek talebi listesi">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <label class="offer-field">Durum
                    <select class="offer-control" data-support-filter>
                        <option value="">Tümü</option>
                        <option value="open">Açık</option>
                        <option value="closed">Kapalı</option>
                    </select>
                </label>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="offer-button offer-button-secondary" data-support-refresh-list>Yenile</button>
                    <button type="button" class="offer-button" data-support-create>Yeni talep</button>
                </div>
            </div>
            <ul class="grid gap-2" data-support-list></ul>
            <nav class="flex flex-wrap items-center justify-between gap-2" aria-label="Talep sayfaları" data-support-list-pages hidden>
                <button type="button" class="offer-button offer-button-secondary" data-support-list-previous>Önceki</button>
                <span class="text-sm text-slate-600 dark:text-slate-300" data-support-list-page></span>
                <button type="button" class="offer-button offer-button-secondary" data-support-list-next>Sonraki</button>
            </nav>
        </section>

        <form method="post" class="grid gap-3" data-support-compose hidden>
            <button type="button" class="offer-button offer-button-secondary justify-self-start" data-support-back>Taleplere dön</button>
            <p class="text-sm text-slate-600 dark:text-slate-300">Medloby ile ilgili sorununuzu açıklayın. Şifre veya sağlık bilgisi paylaşmayın.</p>
            <label class="offer-field">Konu
                <input class="offer-control" name="subject" maxlength="120" required>
            </label>
            <label class="offer-field">Açıklama
                <textarea class="offer-control" name="body" rows="5" maxlength="4000" required></textarea>
            </label>
            <button type="submit" class="offer-button justify-self-start">Talebi gönder</button>
        </form>

        <section class="grid min-w-0 gap-3" data-support-thread aria-labelledby="support-thread-subject" hidden>
            <div class="flex flex-wrap justify-between gap-2">
                <button type="button" class="offer-button offer-button-secondary" data-support-back>Taleplere dön</button>
                <button type="button" class="offer-button offer-button-secondary" data-support-refresh-thread>Talebi yenile</button>
            </div>
            <div>
                <h3 id="support-thread-subject" class="break-words text-xl font-semibold" tabindex="-1" data-support-subject></h3>
                <p class="text-sm text-slate-600 dark:text-slate-300" data-support-summary></p>
            </div>
            <ol class="grid min-w-0 gap-2" data-support-messages></ol>
            <nav class="flex flex-wrap items-center justify-between gap-2" aria-label="Destek mesajı sayfaları" data-support-thread-pages hidden>
                <button type="button" class="offer-button offer-button-secondary" data-support-thread-next>Daha eski mesajlar</button>
                <span class="text-sm text-slate-600 dark:text-slate-300" data-support-thread-page></span>
                <button type="button" class="offer-button offer-button-secondary" data-support-thread-previous>Daha yeni mesajlar</button>
            </nav>
            <form method="post" class="grid gap-2" data-support-reply>
                <label class="offer-field">Yanıt
                    <textarea class="offer-control" name="body" rows="3" maxlength="4000" required></textarea>
                </label>
                <button type="submit" class="offer-button justify-self-start">Yanıtı gönder</button>
            </form>
            <button type="button" class="offer-button offer-button-secondary justify-self-start" data-support-set-status hidden></button>
        </section>
    </fieldset>

    <template data-support-ticket-row>
        <li>
            <button type="button" class="grid w-full min-w-0 gap-1 rounded-lg border border-slate-200 p-3 text-start hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
                <strong class="break-words" data-support-row-subject></strong>
                <span class="break-words text-sm text-slate-600 dark:text-slate-300" data-support-row-summary></span>
            </button>
        </li>
    </template>
    <template data-support-message-row>
        <li class="min-w-0 rounded-lg border border-slate-200 p-3 dark:border-slate-700">
            <p class="text-sm text-slate-600 dark:text-slate-300" data-support-message-by></p>
            <p class="mt-1 whitespace-pre-wrap break-words" data-support-message-body></p>
        </li>
    </template>
</dialog>
