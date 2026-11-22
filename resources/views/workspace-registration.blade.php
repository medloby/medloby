@inject('registrationPrivacy', 'App\Services\PrivacyPublication')

<dialog id="registration-dialog" class="offer-dialog" aria-labelledby="registration-title">
    <div class="flex items-start justify-between gap-4">
        <h2 id="registration-title" class="text-2xl font-semibold tracking-tight"></h2>
        <button type="button" class="offer-button offer-button-secondary" data-registration-close>Vazgeç</button>
    </div>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-registration-status role="status" hidden></p>

    <form method="post" class="mt-5 grid gap-4" data-registration-patient hidden>
        @if ($registrationPrivacy->isPublished())
            <p class="text-sm"><a class="font-semibold underline underline-offset-4" href="{{ route('privacy.notice', 'hasta') }}" target="_blank" rel="noopener">Hasta hesabı KVKK aydınlatma metni</a></p>
        @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="offer-field">
                Ad
                <input class="offer-control" name="first_name" autocomplete="given-name" maxlength="100" required>
            </label>
            <label class="offer-field">
                Soyad
                <input class="offer-control" name="last_name" autocomplete="family-name" maxlength="100" required>
            </label>
        </div>
        <label class="offer-field">
            E-posta
            <input class="offer-control" type="email" name="email" autocomplete="email" maxlength="255" required>
        </label>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="offer-field">
                Şifre
                <input class="offer-control" type="password" name="password" autocomplete="new-password" minlength="8" required>
            </label>
            <label class="offer-field">
                Şifre tekrar
                <input class="offer-control" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
            </label>
        </div>
        <button type="submit" class="offer-button justify-self-start">Hasta hesabını oluştur</button>
    </form>

    <form method="post" class="mt-5 grid gap-4" data-registration-business hidden>
        @if ($registrationPrivacy->isPublished())
            <p class="text-sm"><a class="font-semibold underline underline-offset-4" href="{{ route('privacy.notice', 'klinik') }}" target="_blank" rel="noopener">Klinik başvurusu KVKK aydınlatma metni</a></p>
        @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="offer-field">
                E-posta
                <input class="offer-control" type="email" name="owner_email" autocomplete="email" maxlength="255" required>
            </label>
            <label class="offer-field">
                Klinik adı
                <input class="offer-control" name="business_name" autocomplete="organization" maxlength="255" required>
            </label>
            <label class="offer-field">
                Telefon
                <input class="offer-control" type="tel" name="business_phone" autocomplete="tel" minlength="7" maxlength="30" required>
            </label>
            <label class="offer-field">
                Üyelik planı
                <select class="offer-control" name="membership_plan">
                    <option value="">Plan seçin (isteğe bağlı)</option>
                    @foreach (\App\Models\Business::MEMBERSHIP_PLANS as $code => $label)
                        <option value="{{ $code }}">{{ $label }} · Ücretsiz</option>
                    @endforeach
                </select>
            </label>
        </div>
        <details class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
            <summary class="cursor-pointer text-sm font-semibold text-teal-800 dark:text-teal-300" data-contract-heading></summary>
            <p class="mt-3 whitespace-pre-wrap break-words text-sm text-slate-700 dark:text-slate-200" data-contract-content></p>
        </details>
        <label class="flex items-start gap-2 text-sm">
            <input class="mt-0.5 size-4 accent-teal-800" type="checkbox" name="contract_accepted" required>
            Gösterilen klinik üyelik sözleşmesini okudum ve kabul ediyorum.
        </label>
        <button type="submit" class="offer-button justify-self-start">Klinik başvurusunu gönder</button>
    </form>
</dialog>
