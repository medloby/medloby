<details class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900" data-crm-prices hidden>
    <summary class="cursor-pointer text-lg font-semibold">Tedavi fiyatları</summary>
    <div class="mt-4 grid min-w-0 gap-3">
        <label class="offer-field max-w-sm">
            Fiyat kapsamı
            <select class="offer-control" data-prices-scope>
                <option value="branch">Seçili şube</option>
                <option value="clinic">Klinik geneli</option>
            </select>
        </label>
        <p class="text-sm text-slate-600 dark:text-slate-300" data-prices-status role="status" hidden></p>
        <button type="button" class="offer-button offer-button-secondary justify-self-start" data-prices-retry hidden>Fiyatları yeniden yükle</button>
        <div class="grid min-w-0 gap-3" data-prices-content hidden>
            <label class="offer-field">
                Fiyat kaydı
                <select class="offer-control" data-prices-record></select>
            </label>
            <p class="text-sm text-slate-600 dark:text-slate-300" data-prices-empty hidden></p>
            <form method="post" data-prices-form hidden>
                <fieldset class="grid min-w-0 gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <div class="grid min-w-0 gap-3 sm:grid-cols-2">
                        <label class="offer-field">Tedavi
                            <select class="offer-control" name="treatment_id" required></select>
                        </label>
                        <label class="offer-field">Hekim
                            <select class="offer-control" name="doctor_id"></select>
                        </label>
                        <label class="offer-field">Fiyat türü
                            <select class="offer-control" name="price_type"></select>
                        </label>
                        <label class="offer-field">Para birimi
                            <input class="offer-control" name="currency" minlength="3" maxlength="3" pattern="[A-Za-z]{3}" autocapitalize="characters" required>
                        </label>
                        <label class="offer-field">Tutar
                            <input class="offer-control" type="number" name="price" min="0" max="9999999999.99" step="0.01">
                        </label>
                        <label class="offer-field">En düşük tutar
                            <input class="offer-control" type="number" name="min_price" min="0" max="9999999999.99" step="0.01">
                        </label>
                        <label class="offer-field">En yüksek tutar
                            <input class="offer-control" type="number" name="max_price" min="0" max="9999999999.99" step="0.01">
                        </label>
                        <label class="offer-field">Geçerlilik başlangıcı
                            <input class="offer-control" type="date" name="valid_from">
                        </label>
                        <label class="offer-field">Geçerlilik sonu
                            <input class="offer-control" type="date" name="valid_until">
                        </label>
                    </div>
                    <div class="flex flex-wrap items-center gap-2" data-prices-actions hidden>
                        <button type="submit" class="offer-button">Fiyatı kaydet</button>
                        <button type="button" class="offer-button offer-button-secondary" data-prices-toggle hidden></button>
                    </div>
                </fieldset>
            </form>
        </div>
    </div>
</details>
