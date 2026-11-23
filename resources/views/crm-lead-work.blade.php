<details class="mt-4 rounded-xl border border-slate-200 px-4 py-3 text-sm dark:border-slate-700" data-leads-work hidden>
    <summary class="cursor-pointer font-semibold">Görev, takip ve görüşmeler</summary>
    <p class="mt-3 text-slate-600 dark:text-slate-300" data-work-status role="status" hidden></p>
    <div class="mt-4 grid gap-5" data-work-content hidden>
        <form class="grid gap-3" data-work-form>
            <fieldset class="grid min-w-0 gap-3">
                <legend class="mb-3 font-semibold" data-work-edit-title>Yeni kayıt</legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="offer-field">
                        Kayıt türü
                        <select class="offer-control" name="kind">
                            <option value="tasks">Görev</option>
                            <option value="follow-ups">Takip</option>
                            <option value="activities">Aktivite</option>
                        </select>
                    </label>
                    <label class="offer-field">
                        İletişim türü
                        <select class="offer-control" name="type"></select>
                    </label>
                </div>
                <label class="offer-field">
                    Başlık
                    <input class="offer-control" name="title" required maxlength="255">
                </label>
                <label class="offer-field">
                    <span data-work-time-label></span>
                    <input class="offer-control" name="when" type="datetime-local">
                </label>
                <label class="offer-field" data-work-assignee hidden>
                    <span data-work-assignee-label></span>
                    <select class="offer-control" name="assigned_business_user_id" disabled></select>
                </label>
                <label class="offer-field">
                    Açıklama
                    <textarea class="offer-control" name="description" rows="2"></textarea>
                </label>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="offer-button" data-work-save>Kaydı ekle</button>
                    <button type="button" class="offer-button offer-button-secondary" data-work-discard hidden>Düzenlemeyi bırak</button>
                </div>
            </fieldset>
        </form>

        @foreach (['tasks' => ['Görevler', 'Henüz görev yok.'], 'follow-ups' => ['Takipler', 'Henüz takip yok.'], 'activities' => ['Aktiviteler', 'Henüz aktivite yok.']] as $group => [$title, $empty])
            <section class="grid gap-2 border-t border-slate-200 pt-4 dark:border-slate-700" data-work-group="{{ $group }}">
                <h4 class="font-semibold">{{ $title }}</h4>
                <p class="text-slate-600 dark:text-slate-300" data-work-empty>{{ $empty }}</p>
                <ul class="grid gap-2" data-work-list></ul>
            </section>
        @endforeach

        <section class="grid gap-2 border-t border-slate-200 pt-4 dark:border-slate-700">
            <h4 class="font-semibold">Bağlı görüşmeler</h4>
            <p class="text-slate-600 dark:text-slate-300" data-work-no-conversations>Bu adaya bağlı görüşme yok.</p>
            <ul class="grid gap-2" data-work-conversations></ul>
            <form class="mt-2" data-work-link>
                <fieldset class="grid min-w-0 gap-2">
                    <label class="offer-field">
                        Şubedeki görüşme
                        <select class="offer-control" name="conversation" required></select>
                    </label>
                    <button type="submit" class="offer-button offer-button-secondary justify-self-start" data-work-link-button>Görüşmeyi bağla</button>
                </fieldset>
            </form>
            <p class="text-slate-600 dark:text-slate-300" data-work-no-options hidden>Bağlanabilecek görüşme yok.</p>
            <button type="button" class="offer-button offer-button-secondary justify-self-start" data-work-clear-patient hidden>Hasta bağlantısını kaldır</button>
        </section>
    </div>

    <template data-work-row>
        <li class="grid gap-2 rounded-lg border border-slate-200 p-3 dark:border-slate-700">
            <div>
                <h5 class="break-words font-semibold" data-work-row-title></h5>
                <p class="mt-1 break-words text-slate-600 dark:text-slate-300" data-work-row-meta></p>
                <p class="mt-1 break-words whitespace-pre-wrap" data-work-row-description hidden></p>
            </div>
            <div class="flex flex-wrap gap-2" data-work-row-actions></div>
        </li>
    </template>
</details>
