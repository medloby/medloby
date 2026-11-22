<!DOCTYPE html>
<html lang="tr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
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
            <h1 class="break-words text-4xl font-semibold tracking-tight">{{ $clinic->name }}</h1>
            <section class="grid gap-4" aria-labelledby="clinic-branches-title">
                <h2 id="clinic-branches-title" class="text-2xl font-semibold tracking-tight">Şubeler</h2>
                <div class="grid items-start gap-4 sm:grid-cols-2">
                    @foreach ($branches as $branch)
                        <article class="grid min-w-0 gap-4 rounded-2xl border border-[#d6e0d5] bg-white p-5 sm:p-6 dark:border-slate-800 dark:bg-slate-900">
                            <div class="grid gap-1 break-words">
                                <h3 class="text-xl font-semibold tracking-tight">{{ $branch->name }}</h3>
                                @if ($branch->district || $branch->city)
                                    <p class="text-sm text-slate-600 dark:text-slate-300">{{ implode(' / ', array_filter([$branch->district, $branch->city])) }}</p>
                                @endif
                                @if ($branch->address)
                                    <p class="text-sm text-slate-600 dark:text-slate-300">{{ $branch->address }}</p>
                                @endif
                            </div>
                            @if ($branch->treatments->isNotEmpty())
                                <div class="grid gap-1 break-words">
                                    <h4 class="text-sm font-semibold">Tedaviler</h4>
                                    <p class="text-sm text-slate-600 dark:text-slate-300">{{ $branch->treatments->pluck('name')->join(', ') }}</p>
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
