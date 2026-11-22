<!DOCTYPE html>
<html lang="tr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>{{ $link ? 'Yeni şifre oluştur' : 'Şifremi unuttum' }} | Medloby</title>
        @fonts
        @vite('resources/css/app.css')
    </head>
    <body class="min-h-screen bg-slate-50 font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <header class="border-b border-slate-200 dark:border-slate-800">
            <nav class="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-4 px-5 py-4" aria-label="Medloby">
                <a href="{{ route('home') }}" class="text-xl font-semibold">Medloby</a>
                <a href="{{ route('package-offers') }}#gorusmeler" class="offer-button offer-button-secondary">Girişe dön</a>
            </nav>
        </header>
        <main class="mx-auto max-w-lg px-5 py-8 sm:py-12">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-900">
                <h1 class="text-2xl font-semibold tracking-tight">{{ $link ? 'Yeni şifre oluştur' : 'Şifremi unuttum' }}</h1>
                @if ($message)
                    <p class="mt-4 text-sm font-medium text-red-800 dark:text-red-300" role="alert">{{ $message }}</p>
                @elseif (session('status'))
                    <p class="mt-4 text-sm text-slate-700 dark:text-slate-200" role="status">{{ session('status') }}</p>
                @endif

                @if ($link)
                    <p class="mt-2 break-words text-sm text-slate-600 dark:text-slate-300">{{ $link['email'] }}</p>
                    <form method="post" action="{{ route('password.update') }}" class="mt-5 grid gap-4">
                        @csrf
                        <input type="hidden" name="token" value="{{ $link['token'] }}">
                        <input type="hidden" name="email" value="{{ $link['email'] }}" autocomplete="username">
                        <label class="offer-field">
                            Yeni şifre
                            <input type="password" name="password" class="offer-control" autocomplete="new-password" minlength="8" maxlength="72" required>
                        </label>
                        <label class="offer-field">
                            Yeni şifre tekrar
                            <input type="password" name="password_confirmation" class="offer-control" autocomplete="new-password" minlength="8" maxlength="72" required>
                        </label>
                        <button type="submit" class="offer-button">Şifremi yenile</button>
                    </form>
                @elseif (! session('status'))
                    <form method="post" action="{{ route('password.email') }}" class="mt-5 grid gap-4">
                        @csrf
                        <label class="offer-field">
                            Hesabınızın e-posta adresi
                            <input type="email" name="email" value="{{ old('email') }}" class="offer-control" autocomplete="email" maxlength="255" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                        </label>
                        @error('email')
                            <p id="email-error" class="text-sm font-medium text-red-800 dark:text-red-300" role="alert">{{ $message }}</p>
                        @enderror
                        <button type="submit" class="offer-button">Yenileme bağlantısı gönder</button>
                    </form>
                @else
                    <a href="{{ route('password.request') }}" class="mt-4 inline-block text-sm font-semibold text-teal-800 underline underline-offset-4 dark:text-teal-300">Yeniden bağlantı iste</a>
                @endif
            </section>
        </main>
    </body>
</html>
