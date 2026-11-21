<details id="package-conversation" class="mt-5 rounded-xl border border-slate-200 p-4 dark:border-slate-700" hidden>
    <summary class="cursor-pointer text-sm font-semibold text-teal-800 focus-visible:outline-2 focus-visible:outline-offset-4 dark:text-teal-300">Görüşme mesajları</summary>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-thread-status role="status" hidden></p>
    <ul class="mt-4 grid gap-2" data-thread-messages></ul>
    <nav class="mt-3 flex flex-wrap gap-2" aria-label="Mesaj sayfaları" data-thread-pages hidden>
        <button type="button" class="offer-button offer-button-secondary" data-thread-newer>Daha yeni mesajlar</button>
        <button type="button" class="offer-button offer-button-secondary" data-thread-older>Daha eski mesajlar</button>
    </nav>
    <form method="post" enctype="multipart/form-data" class="mt-4 grid gap-3 border-t border-slate-200 pt-4 dark:border-slate-700" data-thread-form hidden>
        <label class="offer-field">
            Mesaj
            <textarea class="offer-control" name="body" rows="3" maxlength="10000"></textarea>
        </label>
        <label class="offer-field">
            Dosya (isteğe bağlı)
            <input class="offer-control" type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf">
        </label>
        <button type="submit" class="offer-button justify-self-start">Mesaj gönder</button>
    </form>

    <template data-thread-message>
        <li class="grid gap-1 rounded-lg border border-slate-200 p-3 text-sm dark:border-slate-700">
            <div class="flex flex-wrap gap-x-3 gap-y-1">
                <strong class="break-words" data-thread-sender></strong>
                <time class="text-slate-600 dark:text-slate-300" data-thread-time></time>
            </div>
            <p class="whitespace-pre-wrap break-words" data-thread-body></p>
            <div class="flex flex-wrap gap-2" data-thread-files></div>
        </li>
    </template>
</details>
