<dialog id="clinic-administration-dialog" class="offer-dialog max-w-3xl" aria-labelledby="clinic-administration-title">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <h2 id="clinic-administration-title" class="text-2xl font-semibold tracking-tight">Klinik yönetimi</h2>
        <button type="button" class="offer-button offer-button-secondary" data-admin-clinic-close>Kapat</button>
    </header>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-admin-clinic-status role="status" hidden></p>

    <section class="mt-4 grid gap-4" data-admin-clinic-directory>
        <form class="grid items-end gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,12rem)_auto]" data-admin-clinic-filters>
            <label class="offer-field">Klinik adı
                <input type="search" name="search" class="offer-control" maxlength="120">
            </label>
            <label class="offer-field">Durum
                <select name="status" class="offer-control">
                    <option value="">Tüm durumlar</option>
                    <option value="active">Aktif</option>
                    <option value="suspended">Askıda</option>
                    <option value="pending">İncelemede</option>
                    <option value="rejected">Reddedildi</option>
                </select>
            </label>
            <button type="submit" class="offer-button">Ara</button>
        </form>
        <div data-admin-clinic-directory-list>
            @include('clinic-administration-list', ['paginationLabel' => 'Klinik sayfaları'])
        </div>
    </section>

    <section class="mt-4 grid gap-4" data-admin-clinic-detail hidden>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h3 class="break-words text-xl font-semibold" data-admin-clinic-name tabindex="-1"></h3>
                <p class="mt-1 break-words text-sm text-slate-600 dark:text-slate-300" data-admin-clinic-context></p>
            </div>
            <button type="button" class="offer-button offer-button-secondary" data-admin-clinic-to-list>Klinik listesine dön</button>
        </div>
        <button type="button" class="offer-button justify-self-start" data-admin-clinic-review hidden>Başvuruyu incele</button>
        <form class="rounded-xl border border-slate-200 p-4 dark:border-slate-700" data-admin-clinic-transition hidden>
            <fieldset class="grid gap-3">
                <label class="offer-field">Durum değişikliği gerekçesi
                    <textarea class="offer-control" name="reason" rows="2" minlength="3" maxlength="2000" required></textarea>
                </label>
                <button type="submit" class="offer-button offer-button-secondary justify-self-start"></button>
            </fieldset>
        </form>
        <details class="rounded-xl border border-slate-200 p-4 dark:border-slate-700" data-admin-clinic-history>
            <summary class="cursor-pointer font-semibold">Durum geçmişi</summary>
            <div class="mt-3" data-admin-clinic-history-list>
                @include('clinic-administration-list', ['paginationLabel' => 'Durum geçmişi sayfaları'])
            </div>
        </details>
        <section aria-labelledby="admin-clinic-members-title">
            <h4 id="admin-clinic-members-title" class="mb-2 font-semibold">Klinik üyeleri</h4>
            <div data-admin-clinic-members>
                @include('clinic-administration-list', ['paginationLabel' => 'Üye sayfaları'])
            </div>
        </section>
    </section>

    <section class="mt-4 grid gap-4" data-admin-clinic-member hidden>
        <button type="button" class="offer-button offer-button-secondary justify-self-start" data-admin-clinic-to-members>Üyelere dön</button>
        <div data-admin-clinic-member-content hidden>
            <h3 class="break-words text-xl font-semibold" data-admin-clinic-member-name tabindex="-1"></h3>
            <p class="mt-1 break-words text-sm text-slate-600 dark:text-slate-300" data-admin-clinic-member-account></p>
            <p class="mt-2 break-words text-sm" data-admin-clinic-member-branches></p>
            <form class="mt-4" data-admin-clinic-member-role>
                <fieldset class="flex flex-wrap items-end gap-2">
                    <label class="offer-field">Üye rolü
                        <select class="offer-control" name="role">
                            <option value="business_owner">Klinik sahibi</option>
                            <option value="manager">Yönetici</option>
                            <option value="staff">Personel</option>
                        </select>
                    </label>
                    <button type="submit" class="offer-button offer-button-secondary">Rolü kaydet</button>
                    <button type="button" class="offer-button offer-button-secondary" data-admin-clinic-member-state></button>
                </fieldset>
            </form>
            <section class="mt-5 border-t border-slate-200 pt-4 dark:border-slate-800" aria-labelledby="admin-clinic-permissions-title">
                <h4 id="admin-clinic-permissions-title" class="mb-2 font-semibold">Üyeye özel izinler</h4>
                <ul class="grid gap-2" data-admin-clinic-permissions></ul>
                <p class="text-sm text-slate-600 dark:text-slate-300" data-admin-clinic-no-permissions hidden>Listelenecek izin bulunamadı.</p>
            </section>
        </div>
    </section>

    <template data-admin-clinic-selectable-row>
        <li>
            <button type="button" class="grid w-full min-w-0 gap-1 rounded-xl border border-slate-200 p-3 text-start hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
                <strong class="break-words" data-row-title></strong>
                <span class="break-words text-sm text-slate-600 dark:text-slate-300" data-row-summary></span>
            </button>
        </li>
    </template>
    <template data-admin-clinic-history-row>
        <li class="grid gap-1 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
            <strong class="break-words" data-row-title></strong>
            <span class="break-words text-sm text-slate-600 dark:text-slate-300" data-row-summary></span>
            <p class="whitespace-pre-wrap break-words text-sm" data-row-reason></p>
        </li>
    </template>
    <template data-admin-clinic-permission-row>
        <li class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
            <form>
                <fieldset class="flex flex-wrap items-end gap-2">
                    <label class="offer-field w-full min-w-0 flex-1 basis-48">
                        <span class="break-words" data-permission-name></span>
                        <select class="offer-control" name="setting">
                            <option value="default">Varsayılan</option>
                            <option value="allow">İzin ver</option>
                            <option value="deny">İzni kapat</option>
                        </select>
                    </label>
                    <button type="submit" class="offer-button offer-button-secondary">İzni kaydet</button>
                </fieldset>
            </form>
        </li>
    </template>
</dialog>
