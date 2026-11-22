<dialog id="package-service-dialog" class="offer-dialog" aria-labelledby="package-service-title">
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <h2 id="package-service-title" class="text-2xl font-semibold tracking-tight">Ek hizmetleri yönet</h2>
            <p class="mt-1 break-words text-sm text-slate-600 dark:text-slate-300" data-service-scope></p>
        </div>
        <button type="button" class="offer-button offer-button-secondary" data-service-close>Kapat</button>
    </div>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-service-status role="status" hidden></p>
    <button type="button" class="offer-button offer-button-secondary mt-3" data-service-retry hidden>Yeniden yükle</button>

    <form method="post" class="mt-5" data-service-form hidden>
        <fieldset class="grid min-w-0 gap-3">
            <label class="offer-field">
                Hizmet kaydı
                <select class="offer-control" data-service-record></select>
            </label>
            <label class="offer-field">
                Hizmet adı
                <input class="offer-control" name="name" maxlength="255" required>
            </label>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="offer-field">
                    Hizmet türü
                    <input class="offer-control" name="service_type" maxlength="100" required>
                </label>
                <label class="offer-field">
                    Birim (isteğe bağlı)
                    <input class="offer-control" name="unit" maxlength="100">
                </label>
            </div>
            <label class="offer-field">
                Herkese açık açıklama (isteğe bağlı)
                <textarea class="offer-control" name="description" rows="3"></textarea>
            </label>
            <div class="grid gap-1">
                <label class="flex items-center gap-2 text-sm">
                    <input class="size-4 accent-teal-800" name="is_active" type="checkbox" aria-describedby="package-service-active-help" checked>
                    Hizmet aktif
                </label>
                <p id="package-service-active-help" class="text-sm text-slate-600 dark:text-slate-300">Pasif hizmet içeren paketler yeni görüşmelere kapanır.</p>
            </div>
            <button type="submit" class="offer-button justify-self-start">Hizmeti kaydet</button>
        </fieldset>
    </form>
</dialog>
