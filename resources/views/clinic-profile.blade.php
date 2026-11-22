<details class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900" data-crm-profile hidden>
    <summary class="cursor-pointer text-lg font-semibold">Klinik ve şube bilgileri</summary>
    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" data-profile-status role="status" hidden></p>
    <form class="mt-4" hidden>
        <fieldset class="grid gap-5">
            <label class="offer-field max-w-lg">Klinik sahibinin adı ve soyadı
                <input class="offer-control" name="owner_name" autocomplete="name" maxlength="255" required>
            </label>

            @foreach (['clinic' => 'Klinik', 'branch' => 'Seçili şube'] as $section => $heading)
                <fieldset class="min-w-0 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <legend class="px-2 font-semibold">{{ $heading }}</legend>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ([
                            'name' => ['Adı', 'text', 255],
                            'phone' => ['Telefon', 'tel', 30],
                            'email' => ['İletişim e-postası', 'email', 255],
                            'country_code' => ['Ülke kodu', 'text', 2],
                            'city' => ['Şehir', 'text', 100],
                            'district' => ['İlçe', 'text', 100],
                            'postal_code' => ['Posta kodu', 'text', 20],
                        ] as $field => [$label, $type, $length])
                            <label class="offer-field">{{ $label }}
                                <input class="offer-control" name="{{ $section }}.{{ $field }}" type="{{ $type }}" maxlength="{{ $length }}"
                                    @required(in_array($field, ['name', 'country_code'], true))>
                            </label>
                        @endforeach
                        @if ($section === 'clinic')
                            <label class="offer-field">Klinik türü
                                <input class="offer-control" name="clinic.type" maxlength="50">
                            </label>
                            <label class="offer-field">Web sitesi
                                <input class="offer-control" name="clinic.website" type="url" maxlength="255">
                            </label>
                        @endif
                        <label class="offer-field sm:col-span-2">Açık adres
                            <textarea class="offer-control" name="{{ $section }}.address" rows="2" maxlength="2000"></textarea>
                        </label>
                        @if ($section === 'clinic')
                            <label class="offer-field sm:col-span-2">Klinik açıklaması ve özellikleri
                                <textarea class="offer-control" name="clinic.description" rows="4" maxlength="5000"></textarea>
                            </label>
                        @endif
                        <label class="offer-field">Enlem
                            <input class="offer-control" name="{{ $section }}.latitude" type="number" min="-90" max="90" step="any">
                        </label>
                        <label class="offer-field">Boylam
                            <input class="offer-control" name="{{ $section }}.longitude" type="number" min="-180" max="180" step="any">
                        </label>
                    </div>
                </fieldset>
            @endforeach
            <button type="submit" class="offer-button justify-self-start">Klinik ve şube bilgilerini kaydet</button>
        </fieldset>
    </form>
</details>
