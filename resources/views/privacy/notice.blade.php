<!DOCTYPE html>
<html lang="tr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }} | Medloby</title>
        @fonts
        @vite('resources/css/app.css')
    </head>
    <body class="min-h-screen bg-slate-50 font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <header class="border-b border-slate-200 dark:border-slate-800">
            <nav class="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-4 px-5 py-4" aria-label="Medloby">
                <a href="{{ route('home') }}" class="text-xl font-semibold">Medloby</a>
                <a href="{{ route('package-offers') }}#gorusmeler" class="offer-button offer-button-secondary">Hesap ve görüşmeler</a>
            </nav>
        </header>
        <main class="mx-auto grid max-w-3xl gap-7 px-5 py-8 sm:py-12 [&_h2]:mb-3 [&_h2]:text-xl [&_h2]:font-semibold [&_p]:leading-relaxed">
            <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">{{ $title }}</h1>
            <section>
                <h2>Veri sorumlusu</h2>
                <p>{{ $publication->operator('legal_name') }}</p>
                <p class="whitespace-pre-line">{{ $publication->operator('postal_address') }}</p>
            </section>

            @include($clinic ? 'privacy.clinic' : 'privacy.patient')

            <section>
                <h2>Sağlık bilgileri</h2>
                <p>Tedavi, görüşme ve randevu bilgileri kişinin sağlık durumuna ilişkin bilgi içerebilir. Klinik, sağlık hizmeti için yürüttüğü işlemlerin amacını ve hukuki sebebini kendi aydınlatma metninde açıklar. Medloby, bir klinik adına işlem yaptığında kliniğin yetkili talimatlarıyla ve ilgili işleme şartıyla sınırlı hareket eder. Sağlık verilerinde Kanunun 6 ncı maddesindeki ilgili şart ve Kurulun belirlediği tedbirler uygulanır. Açık rıza gereken işlemlerde ayrı bir beyan alınır.</p>
            </section>
            <section>
                <h2>Veriler kimlerle ve neden paylaşılır?</h2>
                <p>İşleme amacı için gereken bilgiler erişim yetkisi bulunan Medloby çalışanlarıyla paylaşılır. Barındırma, güvenli depolama, teknik destek veya işlemsel e-posta hizmeti kullanıldığında ilgili sağlayıcıya hizmetin gerektirdiği ölçüde veri aktarılır. Kanuni yükümlülük veya geçerli bir talep kapsamında yetkili makamlara, hakların kullanılması veya korunması gerektiğinde ilgili mercilere veri iletilebilir. Yurt dışı aktarımı söz konusu olduğunda Kanunun 9 uncu maddesindeki şartlar ve güvenceler uygulanır.</p>
            </section>
            <section>
                <h2>Veriler ne kadar saklanır?</h2>
                <p>Veriler işleme amacının gerektirdiği ve ilgili mevzuatın zorunlu kıldığı sürelerle sınırlı tutulur. Hesabın veya başvurunun kapanması, devam eden randevu ve talepler, uyuşmazlıklar ve kanuni kayıt süreleri saklama değerlendirmesinde dikkate alınır. İşlemeyi gerektiren sebepler ortadan kalkınca ilgili kayıtlar Kanunun 7 nci maddesine göre silinir, yok edilir veya anonim hâle getirilir.</p>
            </section>
            @include('privacy.rights')
        </main>
    </body>
</html>
