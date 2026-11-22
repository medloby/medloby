<section id="clinic-crm" class="mt-5 grid gap-5" aria-labelledby="clinic-crm-name" hidden>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <h2 id="clinic-crm-name" class="break-words text-xl font-semibold"></h2>
            <p class="text-sm text-slate-600 dark:text-slate-300" data-crm-plan hidden></p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="#gorusmeler" class="offer-button offer-button-secondary" data-crm-return>Başvurulara dön</a>
            <button type="button" class="offer-button offer-button-secondary" data-crm-refresh>Yenile</button>
        </div>
    </div>
    <p class="text-sm text-slate-600 dark:text-slate-300" data-crm-status role="status" hidden></p>
    @include('clinic-branch-management')
    @include('clinic-consent-templates')
    <label class="offer-field max-w-sm" data-crm-branch-field hidden>
        Şube
        <select class="offer-control" data-crm-branch></select>
    </label>
    @include('clinic-profile')
    <div class="grid gap-5" data-crm-summary hidden>
        <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" data-crm-metrics></dl>
        <section class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="clinic-crm-pipeline">
            <h3 id="clinic-crm-pipeline" class="font-semibold">Takip aşamaları</h3>
            <ul class="mt-3 divide-y divide-slate-200 text-sm dark:divide-slate-700" data-crm-pipeline></ul>
            <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-crm-empty-pipeline hidden>Henüz takip aşaması yok.</p>
            <details class="mt-3" data-crm-stage-editor>
                <summary class="offer-button offer-button-secondary cursor-pointer">Aşama ekle</summary>
                <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-crm-stage-status role="status" hidden></p>
                <form class="mt-3" data-crm-stage-form>
                    <fieldset class="flex min-w-0 flex-wrap items-end gap-3">
                        <label class="offer-field w-full sm:max-w-xs">
                            Aşama adı
                            <input class="offer-control" name="name" required maxlength="255">
                        </label>
                        <button type="submit" class="offer-button">Aşamayı kaydet</button>
                    </fieldset>
                </form>
            </details>
        </section>
    </div>

    @include('clinic-operations')
    @include('clinic-social-links')
    @include('clinic-treatment-prices')
    @include('clinic-calendar')
    @include('crm-leads')

    <template data-crm-metric>
        <div class="grid gap-1 rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900">
            <dt class="text-sm text-slate-600 dark:text-slate-300"></dt>
            <dd class="text-2xl font-semibold tabular-nums"></dd>
        </div>
    </template>
    <template data-crm-stage>
        <li class="flex items-baseline justify-between gap-3 py-2">
            <span class="min-w-0 break-words"></span>
            <strong class="tabular-nums"></strong>
        </li>
    </template>
</section>
