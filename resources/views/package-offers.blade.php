<!DOCTYPE html>
<html lang="tr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Paket Teklifleri | Medloby</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-50 font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <div id="offer-workspace" data-today="{{ today()->toDateString() }}">
            <header class="border-b border-slate-800 bg-slate-950 text-white">
                <div class="mx-auto flex min-h-18 max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-8">
                    <a href="{{ route('package-offers') }}" class="inline-flex items-center gap-3 text-lg font-semibold tracking-tight">
                        <span class="grid size-9 place-items-center rounded-lg bg-teal-300 text-sm font-bold text-slate-950" aria-hidden="true">M</span>
                        Medloby
                    </a>
                    <nav class="flex flex-wrap items-center gap-2 text-sm" aria-label="Medloby">
                        <a href="#gorusmeler" id="offers-link" class="rounded-lg px-3 py-2 hover:bg-slate-800 aria-[current=page]:bg-slate-800">Paket teklifleri</a>
                        <a href="#katalog" id="discovery-link" class="rounded-lg px-3 py-2 hover:bg-slate-800 aria-[current=page]:bg-slate-800">Tedavi paketleri</a>
                    </nav>
                    <div id="account" class="flex flex-wrap items-center gap-3" hidden>
                        <span id="account-name" class="text-sm text-slate-200"></span>
                        <button id="notifications-button" type="button" class="inline-flex items-center gap-2 rounded-lg border border-slate-600 px-3 py-2 text-sm hover:bg-slate-800" hidden>
                            Bildirimler
                            <span id="notifications-count" class="rounded-full bg-teal-300 px-1.5 text-xs font-semibold text-slate-950" aria-hidden="true" hidden></span>
                        </button>
                        <button id="catalog-button" type="button" class="rounded-lg border border-slate-600 px-3 py-2 text-sm hover:bg-slate-800" hidden>Paketleri yönet</button>
                        <button id="clinic-review-button" type="button" class="rounded-lg border border-slate-600 px-3 py-2 text-sm hover:bg-slate-800" hidden>Klinik başvuruları</button>
                        <button id="clinic-contracts-button" type="button" class="rounded-lg border border-slate-600 px-3 py-2 text-sm hover:bg-slate-800" hidden>Üyelik sözleşmeleri</button>
                        <button id="logout" type="button" class="rounded-lg border border-slate-600 px-3 py-2 text-sm hover:bg-slate-800">Çıkış yap</button>
                    </div>
                </div>
            </header>

            <main class="mx-auto max-w-7xl px-4 py-8 sm:px-8 sm:py-12">
                <h1 id="page-title" class="text-3xl font-semibold tracking-tight sm:text-4xl">Paket teklifleri</h1>
                <p id="page-message" class="mt-4 max-w-2xl text-sm text-slate-700 dark:text-slate-200" role="status" hidden></p>
                @include('clinic-applications')

                @include('package-discovery')

                <section id="sign-in" class="mt-6 max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="sign-in-title">
                    <h2 id="sign-in-title" class="text-xl font-semibold">Hesabınıza giriş yapın</h2>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Klinik veya hasta hesabınızla görüşmelerinizdeki paket tekliflerine erişin.</p>
                    <form id="sign-in-form" method="post" class="mt-6 grid gap-4">
                        <label class="offer-field">
                            E-posta
                            <input class="offer-control" type="email" name="email" autocomplete="username" required>
                        </label>
                        <label class="offer-field">
                            Şifre
                            <input class="offer-control" type="password" name="password" autocomplete="current-password" required>
                        </label>
                        <button type="submit" class="offer-button mt-1">Giriş yap</button>
                    </form>
                    <div class="mt-5 grid gap-2 border-t border-slate-200 pt-5 sm:grid-cols-2 dark:border-slate-700">
                        <button id="patient-registration" type="button" class="offer-button offer-button-secondary">Hasta hesabı oluştur</button>
                        <button id="clinic-registration" type="button" class="offer-button offer-button-secondary">Klinik başvurusu yap</button>
                    </div>
                </section>

                <section id="email-verification" class="mt-6 max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-labelledby="verification-title" hidden>
                    <h2 id="verification-title" class="text-xl font-semibold">E-posta adresinizi doğrulayın</h2>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                        <strong id="verification-address" class="break-all"></strong> adresinize gelen doğrulama bağlantısını açın.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button id="check-verification" type="button" class="offer-button">Doğrulamayı kontrol et</button>
                        <button id="resend-verification" type="button" class="offer-button offer-button-secondary">E-postayı yeniden gönder</button>
                    </div>
                </section>

                <div id="workspace" class="mt-7 grid items-start gap-5 lg:grid-cols-[minmax(16rem,18rem)_minmax(0,1fr)]" hidden>
                    <aside class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-labelledby="conversations-title">
                        <h2 id="conversations-title" class="px-2 py-1 font-semibold">Görüşmeler</h2>
                        <div id="conversation-list" class="mt-3 grid gap-1.5"></div>
                        <nav id="conversation-pages" class="mt-4 flex justify-between gap-2" aria-label="Görüşme sayfaları" hidden>
                            <button id="previous-page" type="button" class="offer-button offer-button-secondary">Önceki</button>
                            <button id="next-page" type="button" class="offer-button offer-button-secondary">Sonraki</button>
                        </nav>
                    </aside>

                    <section class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="conversation-title">
                        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-5 dark:border-slate-800">
                            <div class="min-w-0">
                                <h2 id="conversation-title" tabindex="-1" class="break-words text-2xl font-semibold tracking-tight">Görüşme seçin</h2>
                                <p id="conversation-context" class="mt-1 break-words text-sm text-slate-600 dark:text-slate-300"></p>
                            </div>
                            <button id="new-offer" type="button" class="offer-button" hidden>Paket teklifi oluştur</button>
                        </div>
                        <div id="offer-list" class="mt-5 grid gap-4"></div>
                        @include('package-conversation')
                    </section>
                </div>
            </main>

            @include('workspace-registration')
            @include('package-catalog-dialog')
            @include('workspace-notifications')
            @include('clinic-review-dialog')
            @include('clinic-contracts-dialog')

            <dialog id="offer-dialog" class="offer-dialog" aria-labelledby="offer-dialog-title">
                <div class="flex items-start justify-between gap-4">
                    <h2 id="offer-dialog-title" class="text-2xl font-semibold tracking-tight">Paket teklifi oluştur</h2>
                    <button type="button" class="offer-button offer-button-secondary" data-close>Kapat</button>
                </div>
                <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-status role="status" hidden></p>
                <form id="offer-form" class="mt-5 grid gap-4" hidden>
                    <label class="offer-field">
                        Tedavi paketi
                        <select class="offer-control" name="package" required></select>
                    </label>
                    <label class="offer-field">
                        Teklif başlığı
                        <input class="offer-control" name="title" maxlength="255" required>
                    </label>
                    <div class="grid gap-4 sm:grid-cols-[2fr_1fr]">
                        <label class="offer-field">
                            Teklif tutarı
                            <input class="offer-control" type="number" name="amount" step="0.01" min="0" required>
                        </label>
                        <label class="offer-field">
                            Para birimi
                            <input class="offer-control" name="currency" minlength="3" maxlength="3" pattern="[A-Za-z]{3}" required>
                        </label>
                    </div>
                    <label class="offer-field">
                        Hastaya not (isteğe bağlı)
                        <textarea class="offer-control" name="description" rows="3" maxlength="10000"></textarea>
                    </label>
                    <label class="offer-field">
                        Geçerlilik sonu (isteğe bağlı)
                        <input class="offer-control" type="datetime-local" name="valid_until">
                        <span class="text-xs font-normal text-slate-600 dark:text-slate-300">Cihazınızın saat dilimi kullanılır.</span>
                    </label>
                    <button type="submit" class="offer-button justify-self-start">Teklifi gönder</button>
                </form>
            </dialog>

            <dialog id="reject-dialog" class="offer-dialog" aria-labelledby="reject-dialog-title">
                <div class="flex items-start justify-between gap-4">
                    <h2 id="reject-dialog-title" class="text-2xl font-semibold tracking-tight">Teklifi reddet</h2>
                    <button type="button" class="offer-button offer-button-secondary" data-close>Vazgeç</button>
                </div>
                <p id="reject-offer-title" class="mt-2 break-words text-sm text-slate-600 dark:text-slate-300"></p>
                <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-status role="status" hidden></p>
                <form id="reject-form" method="post" class="mt-5 grid gap-4">
                    <label class="offer-field">
                        Ret nedeni (isteğe bağlı)
                        <textarea class="offer-control" name="rejection_reason" rows="3" maxlength="2000"></textarea>
                    </label>
                    <button type="submit" class="offer-button justify-self-start bg-rose-700 hover:bg-rose-800 dark:bg-rose-700 dark:hover:bg-rose-800">
                        Reddetmeyi onayla
                    </button>
                </form>
            </dialog>

            <dialog id="booking-dialog" class="offer-dialog" aria-labelledby="booking-dialog-title">
                <div class="flex items-start justify-between gap-4">
                    <h2 id="booking-dialog-title" class="text-2xl font-semibold tracking-tight">Seansları planla</h2>
                    <button type="button" class="offer-button offer-button-secondary" data-close>Kapat</button>
                </div>
                <p id="booking-offer-title" class="mt-2 text-sm text-slate-600 dark:text-slate-300"></p>
                <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-status role="status" hidden></p>
                <form id="booking-form" class="mt-5 grid gap-4" hidden>
                    <label class="offer-field">
                        Şube
                        <select class="offer-control" name="branch" required></select>
                    </label>
                    <div id="booking-sessions" class="grid gap-4"></div>
                    <button type="submit" class="offer-button justify-self-start">Randevuları oluştur</button>
                </form>
            </dialog>

            <dialog id="appointment-action-dialog" class="offer-dialog" aria-labelledby="appointment-action-title">
                <div class="flex items-start justify-between gap-4">
                    <h2 id="appointment-action-title" class="text-2xl font-semibold tracking-tight"></h2>
                    <button type="button" class="offer-button offer-button-secondary" data-close>Vazgeç</button>
                </div>
                <p id="appointment-action-context" class="mt-2 text-sm text-slate-600 dark:text-slate-300"></p>
                <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-status role="status" hidden></p>
                <form id="appointment-action-form" method="post" class="mt-5 grid gap-4">
                    <label class="offer-field" data-action-start hidden>
                        Yeni tarih ve saat
                        <input class="offer-control" type="datetime-local" name="starts_at">
                    </label>
                    <label class="offer-field" data-action-reason hidden>
                        <span data-action-reason-label></span>
                        <textarea class="offer-control" name="reason" rows="3" maxlength="1000"></textarea>
                    </label>
                    <button type="submit" class="offer-button justify-self-start">Onayla</button>
                </form>
            </dialog>

            <template id="conversation-template">
                <button type="button" class="grid w-full min-w-0 gap-0.5 rounded-xl px-3 py-2.5 text-left text-sm text-slate-700
                    hover:bg-slate-100 aria-[current=true]:bg-teal-50 aria-[current=true]:text-teal-900 dark:text-slate-200
                    dark:hover:bg-slate-800 dark:aria-[current=true]:bg-teal-950 dark:aria-[current=true]:text-teal-100">
                    <span class="break-words font-semibold" data-subject></span>
                    <span class="break-words text-xs opacity-80" data-context></span>
                </button>
            </template>

            <template id="offer-template">
                <article class="grid gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:p-5 dark:border-slate-700 dark:bg-slate-950">
                    <header class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="break-words text-lg font-semibold" data-title></h3>
                            <p class="mt-1 text-sm font-semibold text-teal-900 dark:text-teal-300" data-price></p>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300" data-valid-until hidden></p>
                        </div>
                        <span class="rounded-full bg-slate-200 px-2.5 py-1 text-xs font-medium text-slate-800 dark:bg-slate-800 dark:text-slate-200" data-offer-status></span>
                    </header>
                    <p class="whitespace-pre-wrap break-words text-sm text-slate-600 dark:text-slate-300" data-description hidden></p>
                    <div class="grid gap-1 text-sm text-slate-700 dark:text-slate-300" data-rejection hidden>
                        <span class="font-semibold">Ret nedeni</span>
                        <p class="whitespace-pre-wrap break-words" data-rejection-reason></p>
                    </div>
                    <div class="grid gap-3 text-sm sm:grid-cols-2" data-contents></div>
                    <div class="flex flex-wrap gap-2" data-actions></div>
                    <details class="border-t border-slate-200 pt-3 dark:border-slate-700" data-schedule hidden>
                        <summary class="cursor-pointer rounded-sm text-sm font-semibold text-teal-800 focus-visible:outline-2
                            focus-visible:outline-offset-4 dark:text-teal-300">Randevuları görüntüle</summary>
                        <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-schedule-status role="status" hidden></p>
                        <ul class="mt-3 grid gap-2" data-schedule-entries></ul>
                    </details>
                </article>
            </template>

            <template id="session-template">
                <fieldset class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <legend class="max-w-full break-words px-2 text-sm font-semibold" data-session-name></legend>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <label class="offer-field">
                            Tarih
                            <input class="offer-control" type="date" data-date required>
                        </label>
                        <label class="offer-field">
                            Doktor
                            <select class="offer-control" data-doctor required disabled></select>
                        </label>
                        <label class="offer-field">
                            Saat
                            <select class="offer-control" data-time required disabled></select>
                        </label>
                    </div>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300" data-session-status role="status" hidden></p>
                </fieldset>
            </template>
        </div>
    </body>
</html>
