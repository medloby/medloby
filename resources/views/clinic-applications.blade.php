<section id="clinic-applications" class="mt-5 max-w-3xl rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="clinic-applications-title" hidden>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 id="clinic-applications-title" class="font-semibold">Klinik başvurularım</h2>
        <button type="button" class="offer-button offer-button-secondary" data-application-refresh hidden>Durumu yenile</button>
    </div>
    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300" data-application-message role="status" hidden></p>
    <ul class="mt-3 grid gap-2" data-application-list></ul>

    <template data-application-row>
        <li class="rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-700">
            <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                <h3 class="min-w-0 break-words font-semibold" data-application-name></h3>
                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-900
                    data-[state=approved]:bg-teal-100 data-[state=approved]:text-teal-900
                    data-[state=rejected]:bg-rose-100 data-[state=rejected]:text-rose-900
                    dark:bg-amber-950 dark:text-amber-200
                    dark:data-[state=approved]:bg-teal-950 dark:data-[state=approved]:text-teal-200
                    dark:data-[state=rejected]:bg-rose-950 dark:data-[state=rejected]:text-rose-200" data-application-status></span>
            </div>
            <time class="mt-1 block text-xs text-slate-600 dark:text-slate-300" data-application-date hidden></time>
            <div class="mt-3 border-t border-slate-200 pt-3 text-sm dark:border-slate-700" data-application-explanation hidden>
                <p class="font-medium">Ret açıklaması</p>
                <p class="mt-1 whitespace-pre-wrap break-words text-slate-700 dark:text-slate-200" data-application-reason></p>
            </div>
        </li>
    </template>
</section>
