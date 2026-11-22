<details class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900" data-crm-calendar hidden>
    <summary class="cursor-pointer text-lg font-semibold">Klinik takvimi</summary>
    <div class="mt-4 grid gap-4" data-calendar-content>
        <div class="flex flex-wrap items-end gap-2">
            <label class="offer-field w-full sm:w-auto">
                Takvim günü
                <input class="offer-control" type="date" data-calendar-day>
            </label>
            <button type="button" class="offer-button offer-button-secondary" data-calendar-previous>Önceki gün</button>
            <button type="button" class="offer-button offer-button-secondary" data-calendar-today>Bugün</button>
            <button type="button" class="offer-button offer-button-secondary" data-calendar-next>Sonraki gün</button>
        </div>
        <p class="text-sm text-slate-600 dark:text-slate-300" data-calendar-status role="status" hidden></p>
        <section class="grid gap-3" data-calendar-agenda hidden>
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h3 class="font-semibold">Günün randevuları</h3>
                <p class="rounded-lg bg-teal-50 px-2.5 py-1 text-sm text-teal-950 dark:bg-teal-950 dark:text-teal-100" data-calendar-day-rule></p>
            </div>
            <p class="text-sm text-slate-600 dark:text-slate-300" data-calendar-empty-appointments hidden>Bu gün için kayıtlı randevu yok.</p>
            <ol class="grid gap-2" data-calendar-appointments></ol>
        </section>

        <section class="grid gap-2" data-calendar-day-blocks hidden>
            <h3 class="font-semibold">Saat engelleri</h3>
            <p class="text-sm text-slate-600 dark:text-slate-300" data-calendar-empty-blocks hidden>Bu günde saat engeli yok.</p>
            <ul class="grid gap-2" data-calendar-blocks></ul>
        </section>

        <fieldset class="grid min-w-0 gap-3" data-calendar-management hidden>
            <details class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                <summary class="cursor-pointer font-semibold">Açık ve kapalı günler</summary>
                <form class="mt-4 flex flex-wrap items-end gap-3" data-calendar-rule-form>
                    <label class="offer-field w-full sm:max-w-xs">
                        Varsayılan gün kuralı
                        <select class="offer-control" name="default_status">
                            <option value="open">Çevrimiçi randevuya açık</option>
                            <option value="closed">Çevrimiçi randevuya kapalı</option>
                        </select>
                    </label>
                    <button type="submit" class="offer-button">Gün kuralını kaydet</button>
                </form>
                <form class="mt-4 grid gap-3 border-t border-slate-200 pt-4 dark:border-slate-700" data-calendar-form="overrides">
                    <p class="text-sm font-semibold" data-calendar-form-title>Yeni tarih istisnası</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="offer-field">
                            İstisna başlangıcı
                            <input class="offer-control" name="start_date" type="date" required>
                        </label>
                        <label class="offer-field">
                            İstisna bitişi
                            <input class="offer-control" name="end_date" type="date" required>
                        </label>
                    </div>
                    <label class="offer-field">
                        Tarih aralığının durumu
                        <select class="offer-control" name="status">
                            <option value="closed">Kapalı</option>
                            <option value="open">Açık</option>
                        </select>
                    </label>
                    <label class="offer-field">
                        İstisna notu (isteğe bağlı)
                        <input class="offer-control" name="reason" maxlength="1000">
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="offer-button" data-calendar-save>İstisnayı ekle</button>
                        <button type="button" class="offer-button offer-button-secondary" data-calendar-discard hidden>Vazgeç</button>
                    </div>
                </form>
                <ul class="mt-4 grid gap-2" data-calendar-overrides></ul>
            </details>

            <details class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                <summary class="cursor-pointer font-semibold">Hekim çalışma saatleri ve izinleri</summary>
                <label class="offer-field mt-4 max-w-sm">
                    Takvim hekimi
                    <select class="offer-control" data-calendar-doctor></select>
                </label>
                <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-calendar-no-doctors hidden>Klinik operasyonları ekranından şubeye hekim ekleyin.</p>
                <div class="mt-4 grid gap-5" data-calendar-doctor-schedule hidden>
                    <div class="grid gap-3">
                        <h4 class="font-semibold">Haftalık çalışma saatleri</h4>
                        <form class="grid gap-3" data-calendar-form="hours">
                            <p class="text-sm font-semibold" data-calendar-form-title>Yeni çalışma aralığı</p>
                            <label class="offer-field">
                                Haftanın günü
                                <select class="offer-control" name="day_of_week"></select>
                            </label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="offer-field">
                                    Çalışma başlangıcı
                                    <input class="offer-control" name="start_time" type="time" required>
                                </label>
                                <label class="offer-field">
                                    Çalışma bitişi
                                    <input class="offer-control" name="end_time" type="time" required>
                                </label>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" class="offer-button" data-calendar-save>Çalışma aralığını ekle</button>
                                <button type="button" class="offer-button offer-button-secondary" data-calendar-discard hidden>Vazgeç</button>
                            </div>
                        </form>
                        <ul class="grid gap-2" data-calendar-hours></ul>
                    </div>
                    <div class="grid gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                        <h4 class="font-semibold">Hekim izinleri</h4>
                        <form class="grid gap-3" data-calendar-form="leaves">
                            <p class="text-sm font-semibold" data-calendar-form-title>Yeni izin</p>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="offer-field">
                                    İzin başlangıcı
                                    <input class="offer-control" name="start_date" type="date" required>
                                </label>
                                <label class="offer-field">
                                    İzin bitişi
                                    <input class="offer-control" name="end_date" type="date" required>
                                </label>
                            </div>
                            <label class="offer-field">
                                İzin notu (isteğe bağlı)
                                <input class="offer-control" name="reason">
                            </label>
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" class="offer-button" data-calendar-save>İzin ekle</button>
                                <button type="button" class="offer-button offer-button-secondary" data-calendar-discard hidden>Vazgeç</button>
                            </div>
                        </form>
                        <ul class="grid gap-2" data-calendar-leaves></ul>
                    </div>
                </div>
            </details>

            <details class="rounded-xl border border-slate-200 p-4 dark:border-slate-700" data-calendar-block-editor>
                <summary class="cursor-pointer font-semibold">Saat engeli oluştur</summary>
                <form class="mt-4 grid gap-3" data-calendar-form="blocks">
                    <p class="text-sm font-semibold" data-calendar-form-title hidden></p>
                    <label class="offer-field">
                        Engelin kapsamı
                        <select class="offer-control" name="doctor_id"></select>
                    </label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="offer-field">
                            Engel başlangıcı
                            <input class="offer-control" name="starts_at" type="datetime-local" required>
                        </label>
                        <label class="offer-field">
                            Engel bitişi
                            <input class="offer-control" name="ends_at" type="datetime-local" required>
                        </label>
                    </div>
                    <label class="offer-field">
                        Engel başlığı (isteğe bağlı)
                        <input class="offer-control" name="title" maxlength="255">
                    </label>
                    <label class="offer-field">
                        Engel notu (isteğe bağlı)
                        <input class="offer-control" name="reason">
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="offer-button" data-calendar-save>Saat engelini ekle</button>
                        <button type="button" class="offer-button offer-button-secondary" data-calendar-discard hidden>Vazgeç</button>
                    </div>
                </form>
            </details>
        </fieldset>
    </div>
    <template data-calendar-row>
        <li class="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-slate-200 p-3 text-sm dark:border-slate-700">
            <div class="min-w-0">
                <p class="break-words font-medium" data-calendar-name></p>
                <p class="mt-1 break-words text-slate-600 dark:text-slate-300" data-calendar-meta></p>
            </div>
            <div class="flex flex-wrap gap-2" data-calendar-actions></div>
        </li>
    </template>
</details>
