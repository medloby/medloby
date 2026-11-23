<!DOCTYPE html>
<html lang="tr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ $clinic->name }} klinik ve şube bilgilerini, tedavileri ve randevu seçeneklerini Medloby’de inceleyin.">
        <link rel="canonical" href="{{ app(\App\Services\PublicWebsite::class)->url('clinics.show', ['clinic' => $clinic->slug]) }}">
        <title>{{ $clinic->name }} | Medloby</title>
        @fonts
        @vite('resources/css/app.css')
    </head>
    <body class="min-h-screen bg-[#f7f7f1] font-sans text-[#172b26] antialiased dark:bg-slate-950 dark:text-slate-100">
        <header class="border-b border-[#dbe3dc] dark:border-slate-800">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-4 sm:px-8 lg:px-12">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-3 rounded-md text-2xl font-semibold tracking-tight focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700">
                    <span class="grid size-9 place-items-center rounded-lg bg-[#164a40] text-base font-bold text-white dark:bg-teal-300 dark:text-slate-950" aria-hidden="true">M</span>
                    Medloby
                </a>
                <a href="{{ route('home') }}#klinikler" class="offer-button offer-button-secondary">Klinik aramasına dön</a>
            </div>
        </header>
        <main class="mx-auto grid max-w-7xl gap-6 px-5 py-10 sm:px-8 sm:py-14 lg:px-12">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h1 class="min-w-0 break-words text-4xl font-semibold tracking-tight">{{ $clinic->name }}</h1>
                @if ($hasPackages)
                    <a href="{{ route('package-offers', ['clinic' => $clinic->slug]) }}#katalog" class="offer-button offer-button-secondary">Kliniğin paketlerini incele</a>
                @endif
            </div>
            @include('clinic-directory.contact-links', ['links' => $clinicContact, 'label' => 'Klinik iletişimi'])
            <section class="grid gap-4" aria-labelledby="clinic-branches-title">
                <h2 id="clinic-branches-title" class="text-2xl font-semibold tracking-tight">Şubeler</h2>
                <div class="grid items-start gap-4 sm:grid-cols-2">
                    @foreach ($branches as $branch)
                        <article id="sube-{{ $branch->id }}" tabindex="-1" class="grid min-w-0 scroll-mt-6 gap-4 rounded-2xl border border-[#d6e0d5] bg-white p-5 target:border-teal-700 sm:p-6 dark:border-slate-800 dark:bg-slate-900 dark:target:border-teal-400">
                            <div class="grid gap-1 break-words">
                                <h3 class="text-xl font-semibold tracking-tight">{{ $branch->name }}</h3>
                                @if ($branch->district || $branch->city)
                                    <p class="text-sm text-slate-600 dark:text-slate-300">{{ implode(' / ', array_filter([$branch->district, $branch->city])) }}</p>
                                @endif
                                @if ($branch->address)
                                    <p class="text-sm text-slate-600 dark:text-slate-300">{{ $branch->address }}</p>
                                @endif
                            </div>
                            @include('clinic-directory.contact-links', ['links' => $branchContacts[$branch->id], 'label' => $branch->name.' iletişimi'])
                            @if ($branch->treatments->isNotEmpty())
                                <div class="grid gap-1 break-words">
                                    <h4 class="text-sm font-semibold">Tedaviler</h4>
                                    <p class="text-sm text-slate-600 dark:text-slate-300">{{ $branch->treatments->pluck('name')->join(', ') }}</p>
                                </div>
                            @endif
                            @if ($roster[$branch->id]->isNotEmpty())
                                <div class="grid gap-2">
                                    <h4 class="text-sm font-semibold">Hekimler</h4>
                                    <ul class="grid divide-y divide-slate-200 rounded-xl border border-slate-200 px-3 dark:divide-slate-700 dark:border-slate-700">
                                        @foreach ($roster[$branch->id] as $doctor)
                                            <li class="grid min-w-0 gap-2 py-3">
                                                <div class="grid gap-1 break-words text-sm">
                                                    <p class="font-semibold">{{ $doctor['name'] }}</p>
                                                    @if ($doctor['specialty'])
                                                        <p class="text-slate-600 dark:text-slate-300">{{ $doctor['specialty'] }}</p>
                                                    @endif
                                                    @if ($doctor['treatments'] !== '')
                                                        <p class="text-slate-600 dark:text-slate-300">{{ $doctor['treatments'] }}</p>
                                                    @endif
                                                </div>
                                                @if ($doctor['bookable'])
                                                    <a href="{{ route('package-offers') }}#randevu-al/{{ $branch->id }}/{{ $doctor['id'] }}" aria-label="{{ $branch->name }} · {{ $doctor['name'] }} için randevu al" class="offer-button offer-button-secondary justify-self-start">Bu hekimden randevu al</a>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            @if ($socialLinks[$branch->id]->isNotEmpty())
                                <div class="grid gap-2">
                                    <h4 class="text-sm font-semibold">Sosyal medya</h4>
                                    <ul class="flex min-w-0 flex-wrap gap-x-4 gap-y-2 text-sm">
                                        @foreach ($socialLinks[$branch->id] as $account)
                                            <li class="min-w-0 break-words">
                                                <a href="{{ $account->profile_url }}" target="_blank" rel="ugc noopener noreferrer" class="font-semibold text-teal-800 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-4 dark:text-teal-300">{{ $account->page_name ?: ucfirst($account->platform) }}</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            @if ($bookable->contains($branch->id))
                                <a href="{{ route('package-offers') }}#randevu-al/{{ $branch->id }}" aria-label="{{ $branch->name }} için randevu al" class="offer-button justify-self-start">Randevu al</a>
                            @else
                                <p class="text-sm text-slate-600 dark:text-slate-300">Çevrimiçi randevu kapalı.</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        </main>
        <footer class="border-t border-[#dbe3dc] dark:border-slate-800">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-5 py-6 text-sm text-slate-600 sm:px-8 lg:px-12 dark:text-slate-300">
                <p class="font-semibold">Medloby</p>
                @include('privacy.footer-links')
            </div>
        </footer>
    </body>
</html>
