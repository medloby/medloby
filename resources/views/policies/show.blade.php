<!DOCTYPE html>
<html lang="tr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @if ($draft)
            <meta name="robots" content="noindex">
        @endif
        <title>{{ $title }} | Medloby</title>
        @fonts
        @vite('resources/css/app.css')
    </head>
    <body class="min-h-screen bg-[#f7f7f1] font-sans text-[#172b26] antialiased dark:bg-slate-950 dark:text-slate-100">
        <header class="border-b border-[#dbe3dc] dark:border-slate-800">
            <nav class="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-3 px-5 py-4" aria-label="Medloby">
                <a href="{{ route('home') }}" class="text-xl font-semibold">Medloby</a>
                <a href="{{ route('home') }}#politikalar" class="offer-button offer-button-secondary">Tüm politikalar</a>
            </nav>
        </header>
        <main class="mx-auto grid w-full max-w-3xl gap-6 px-5 py-8 sm:py-12" aria-labelledby="policy-title">
            <h1 id="policy-title" class="text-3xl font-semibold tracking-tight sm:text-4xl">{{ $title }}</h1>
            @if ($draft)
                <p class="rounded-lg border border-[#d6e0d5] bg-[#edf1e8] px-4 py-3 text-sm dark:border-slate-800 dark:bg-slate-900">Yayın öncesi taslak. Metin, işletmeci bilgileri ve geçerli koşullar doğrulandıktan sonra yürürlüğe alınır.</p>
            @endif
            <article class="grid gap-6 leading-relaxed [&_a]:break-words [&_a]:underline [&_a]:underline-offset-4 [&_h2]:mb-2 [&_h2]:text-xl [&_h2]:font-semibold [&_li]:mt-1 [&_ul]:list-disc [&_ul]:pl-5">
                @include($content)
            </article>
        </main>
    </body>
</html>
