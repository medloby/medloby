<!DOCTYPE html>
<html lang="tr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Medloby’de klinik arayın, şubeleri ve tedavileri inceleyin, uygun şubeden randevu alın. Tedavi paketlerini ve size gönderilen teklifleri keşfedin.">
        <title>Medloby | Tedavi paketleri ve klinik bilgileri</title>
        @fonts
        @vite('resources/css/app.css')
    </head>
    <body class="min-h-screen bg-[#f7f7f1] font-sans text-[#172b26] antialiased dark:bg-slate-950 dark:text-slate-100">
        <a href="#icerik" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-10 focus:rounded-lg focus:bg-white focus:p-3 focus:text-slate-950">İçeriğe geç</a>

        <header class="border-b border-[#dbe3dc] dark:border-slate-800">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-x-8 gap-y-3 px-5 py-4 sm:px-8 lg:px-12">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-3 rounded-md text-2xl font-semibold tracking-tight focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700" aria-current="page">
                    <span class="grid size-9 place-items-center rounded-lg bg-[#164a40] text-base font-bold text-white dark:bg-teal-300 dark:text-slate-950" aria-hidden="true">M</span>
                    Medloby
                </a>
                <nav aria-label="Ana menü" class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm font-medium">
                    <a href="#klinikler" class="rounded-sm underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700">Klinik ara</a>
                    <a href="{{ route('package-offers') }}#katalog" class="rounded-sm underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700">Tedavi paketleri</a>
                    <a href="{{ route('package-offers') }}#clinic-registration" class="rounded-sm underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700">Klinik başvurusu</a>
                    <a href="{{ route('package-offers') }}#gorusmeler" class="offer-button offer-button-secondary">Hesabıma giriş</a>
                </nav>
            </div>
        </header>

        <main id="icerik">
            <section class="mx-auto grid max-w-7xl items-center gap-8 px-5 py-10 sm:px-8 sm:py-14 lg:grid-cols-[1fr_1.02fr] lg:gap-16 lg:px-12 lg:py-20" aria-labelledby="welcome-title">
                <div class="min-w-0">
                    <h1 id="welcome-title" class="max-w-2xl text-[clamp(2.65rem,6vw,4.65rem)] leading-[1.03] font-semibold tracking-[-0.055em]">Tedavi paketleri ve klinik bilgileri</h1>
                    <p class="mt-6 max-w-lg text-lg leading-relaxed text-[#42594f] dark:text-slate-300">Paketlerde yer alan tedavi seanslarını ve ek hizmetleri inceleyin. Görüşmelerinizi, size iletilen teklifleri ve paket randevularınızı hesabınızdan takip edin.</p>

                    <form action="{{ route('package-offers') }}#katalog" method="get" role="search" aria-label="Tedavi paketi arama" class="mt-8 grid max-w-xl gap-2.5">
                        <label for="welcome-query" class="text-sm font-semibold">Tedavi paketlerinde ara</label>
                        <div class="flex flex-wrap items-stretch gap-2 rounded-2xl border border-[#cad8cd] bg-white p-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                            <input id="welcome-query" name="q" type="search" maxlength="100" placeholder="Paket, klinik veya tedavi adı" class="min-h-11 min-w-0 flex-[1_0_11rem] rounded-lg px-3 text-sm placeholder:text-slate-500 focus-visible:outline-2 focus-visible:outline-teal-700 dark:placeholder:text-slate-400 dark:focus-visible:outline-teal-300">
                            <button class="offer-button min-h-11" type="submit">Katalogda ara</button>
                        </div>
                    </form>
                    <a href="{{ route('package-offers') }}#katalog" class="mt-4 inline-block rounded-sm text-sm font-semibold text-[#1b5e50] underline decoration-current/45 underline-offset-4 hover:decoration-current focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700 dark:text-teal-300">Tüm paketleri görüntüle</a>
                </div>

                <img src="{{ asset('images/medloby-catalog.webp') }}" alt="" width="1320" height="990" fetchpriority="high" class="aspect-[4/3] w-full rounded-[1.75rem] bg-[#d8e2d7] object-cover">
            </section>

            @include('clinic-directory.search')

            <section class="border-t border-[#dbe3dc] bg-[#edf1e8] dark:border-slate-800 dark:bg-slate-900" aria-labelledby="account-entry-title">
                <div class="mx-auto grid max-w-7xl gap-6 px-5 py-10 sm:px-8 sm:py-12 lg:grid-cols-[minmax(12rem,0.7fr)_1fr_1fr] lg:gap-9 lg:px-12">
                    <h2 id="account-entry-title" class="self-start text-3xl leading-tight font-semibold tracking-tight">Hasta ve klinik işlemleri</h2>
                    <article class="grid content-start gap-3 rounded-2xl border border-[#d6e0d5] bg-white p-5 sm:p-6 dark:border-slate-700 dark:bg-slate-950">
                        <h3 class="text-xl font-semibold tracking-tight">Görüşmeler ve teklifler</h3>
                        <p class="text-sm leading-relaxed text-[#42594f] dark:text-slate-300">Klinik mesajlarını ve size gönderilen paket tekliflerini hesabınızda görüntüleyin. Kabul ettiğiniz paketin seans randevularını takip edin.</p>
                        <a href="{{ route('package-offers') }}#gorusmeler" class="offer-button offer-button-secondary mt-2 justify-self-start">Görüşmelerime git</a>
                    </article>
                    <article class="grid content-start gap-3 rounded-2xl border border-[#d6e0d5] bg-white p-5 sm:p-6 dark:border-slate-700 dark:bg-slate-950">
                        <h3 class="text-xl font-semibold tracking-tight">Klinik üyeliği</h3>
                        <p class="text-sm leading-relaxed text-[#42594f] dark:text-slate-300">Başvurunuzun sonucunu e-postayla öğrenin. Başvurunuz onaylandığında e-postada gelen giriş bilgileriyle kliniğinizin çalışma alanına geçebilirsiniz.</p>
                        <a href="{{ route('package-offers') }}#clinic-registration" class="offer-button offer-button-secondary mt-2 justify-self-start">Klinik başvurusu yap</a>
                    </article>
                </div>
            </section>
        </main>

        <footer class="border-t border-[#dbe3dc] dark:border-slate-800">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-5 py-6 text-sm text-[#42594f] sm:px-8 lg:px-12 dark:text-slate-300">
                <p class="font-semibold">Medloby</p>
                @include('privacy.footer-links')
                <p>Son içerik güncellemesi <time datetime="2026-11-22">22 Kasım 2026</time></p>
            </div>
        </footer>
    </body>
</html>
