<section id="klinikler" class="scroll-mt-4 border-t border-[#dbe3dc] dark:border-slate-800" aria-labelledby="clinic-search-title">
    <div class="mx-auto grid max-w-7xl gap-6 px-5 py-10 sm:px-8 sm:py-12 lg:px-12">
        <h2 id="clinic-search-title" class="text-3xl leading-tight font-semibold tracking-tight">Klinik ara</h2>
        <form action="{{ route('home') }}#klinikler" method="get" role="search" aria-label="Klinik arama" class="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_auto]">
            <label class="offer-field">
                Klinik veya şube adı
                <input name="klinik" type="search" class="offer-control" maxlength="100" value="{{ $name }}">
            </label>
            <label class="offer-field">
                İl veya ilçe
                <input name="konum" type="search" class="offer-control" maxlength="100" value="{{ $location }}">
            </label>
            <label class="offer-field">
                Tedavi adı
                <input name="tedavi" type="search" class="offer-control" maxlength="100" value="{{ $treatment }}" placeholder="Örneğin diş temizliği">
            </label>
            <button type="submit" class="offer-button min-h-11">Klinikleri ara</button>
        </form>
        @if ($errors->hasAny(['klinik', 'konum', 'tedavi']))
            <p role="alert" class="text-sm text-rose-800 dark:text-rose-300">Her arama alanına en fazla 100 karakter girin.</p>
        @endif

        @if ($clinics !== null)
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($clinics as $clinic)
                    <article class="grid min-w-0 content-start gap-3 rounded-2xl border border-[#d6e0d5] bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
                        <h3 class="break-words text-xl font-semibold tracking-tight">
                            <a href="{{ route('clinics.show', $clinic->slug) }}" class="rounded-sm underline decoration-current/30 underline-offset-4 hover:decoration-current focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700">{{ $clinic->name }}</a>
                        </h3>
                        <ul class="grid divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach ($clinic->branches as $branch)
                                <li class="grid gap-1 py-2 text-sm break-words">
                                    <a href="{{ route('clinics.show', $clinic->slug) }}#sube-{{ $branch->id }}" class="justify-self-start rounded-sm font-semibold text-teal-800 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700 dark:text-teal-300">{{ $branch->name }}</a>
                                    @if ($branch->district || $branch->city)
                                        <p class="text-slate-600 dark:text-slate-300">{{ implode(' / ', array_filter([$branch->district, $branch->city])) }}</p>
                                    @endif
                                    @if ($treatment !== '' && $branch->treatments->isNotEmpty())
                                        <p class="text-slate-600 dark:text-slate-300">{{ $branch->treatments->pluck('name')->join(', ') }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </article>
                @empty
                    <p class="text-sm text-slate-600 sm:col-span-2 dark:text-slate-300">Aramanıza uygun klinik bulunamadı.</p>
                @endforelse
            </div>
            @if ($clinics->hasPages())
                {{ $clinics->links() }}
            @endif
        @endif
    </div>
</section>
