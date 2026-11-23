<details class="mt-4 rounded-xl border border-slate-200 p-3 dark:border-slate-700" data-leads-care hidden>
    <summary class="cursor-pointer font-semibold">Hasta süreci</summary>
    <p class="mt-3 text-sm" role="status" data-care-status hidden></p>
    <div class="mt-3 grid gap-3 sm:grid-cols-2" data-care-steps></div>
    <template data-care-template>
        <form class="grid min-w-0 gap-2 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
            <fieldset class="grid min-w-0 gap-2">
                <legend class="font-semibold" data-care-title></legend>
                <label class="offer-field">Durum
                    <select class="offer-control" name="status">
                        <option value="pending">Bekliyor</option><option value="planned">Planlandı</option>
                        <option value="completed">Tamamlandı</option><option value="skipped">Atlandı</option>
                    </select>
                </label>
                <label class="offer-field">Planlanan tarih
                    <input class="offer-control" type="date" name="planned_on">
                </label>
                <label class="offer-field">Bağlı randevu
                    <select class="offer-control" name="appointment_id"></select>
                </label>
                <p class="text-sm text-slate-600 dark:text-slate-300" data-care-visit hidden></p>
                <button type="submit" class="offer-button offer-button-secondary justify-self-start">Aşamayı kaydet</button>
            </fieldset>
        </form>
    </template>
</details>
