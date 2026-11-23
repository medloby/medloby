<!DOCTYPE html>
<html lang="tr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>Platform yönetimi | Medloby</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-50 font-sans text-slate-950 dark:bg-slate-950 dark:text-slate-100">
        <div data-platform-admin>
            <main class="mx-auto grid min-h-screen max-w-md content-center gap-6 px-5 py-10" data-admin-sign-in>
                <a href="{{ route('home') }}" class="inline-flex w-fit items-center gap-3 text-lg font-semibold tracking-tight">
                    <span class="grid size-9 place-items-center rounded-lg bg-teal-300 text-sm font-bold text-slate-950" aria-hidden="true">M</span>
                    Medloby
                </a>
                <section class="grid gap-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="admin-login-title">
                    <h1 id="admin-login-title" class="text-2xl font-semibold tracking-tight">Platform yönetimi</h1>
                    <form method="post" class="grid gap-4" data-admin-login>
                        <label class="offer-field">Yönetici e-postası
                            <input class="offer-control" type="email" name="email" autocomplete="username" maxlength="255" required>
                        </label>
                        <label class="offer-field">Şifre
                            <input class="offer-control" type="password" name="password" autocomplete="current-password" required>
                        </label>
                        <fieldset class="grid gap-3" data-admin-new-password hidden disabled>
                            <legend class="mb-2 font-semibold">Yeni şifrenizi belirleyin</legend>
                            <label class="offer-field">Yeni şifre
                                <input class="offer-control" type="password" name="new_password" autocomplete="new-password" required>
                            </label>
                            <label class="offer-field">Yeni şifreyi doğrulayın
                                <input class="offer-control" type="password" name="new_password_confirmation" autocomplete="new-password" required>
                            </label>
                        </fieldset>
                        <button class="offer-button" type="submit">Giriş yap</button>
                        <p role="status" class="text-sm text-slate-700 dark:text-slate-200" data-admin-login-status hidden></p>
                    </form>
                    <a href="{{ route('password.request') }}" class="w-fit text-sm font-medium text-teal-800 underline underline-offset-4 dark:text-teal-300">Şifremi unuttum</a>
                </section>
            </main>
            <div data-admin-workspace hidden></div>
        </div>
    </body>
</html>
