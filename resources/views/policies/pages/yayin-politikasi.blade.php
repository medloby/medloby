<section>
    <h2>Klinik ve tedavi bilgilerinin yayını</h2>
    <p>Klinik adı, konumu, şubeler, yetki kapsamındaki tedaviler ve ilgili paket içeriği güncel, doğrulanabilir ve bilgilendirme amaçlı olmalıdır. Klinik, yayımlanmasını istediği bilgilerin doğruluğunu ve gerekli yayın yetkilerini kontrol eder. Özel teklif kapsamı ve ücret ilgili hastaya kapalı hesap alanında iletilir.</p>
</section>
<section>
    <h2>Sağlık tanıtımı ve mahremiyet</h2>
    <p>Yayınlarda hasta kimliği ve özel görüşme bilgilerine yer verilmez. Sağlık hizmetinde üstünlük iddiası, sonuç garantisi, yanıltıcı karşılaştırma, kamuya açık indirim veya kampanya ilanı ve reklam mahiyetinde hasta yorumu yayına uygun değildir. İçerikler Sağlık Hizmetlerinde Tanıtım ve Bilgilendirme Faaliyetleri Hakkında Yönetmelik ve ilgili kurallar kapsamında değerlendirilir.</p>
</section>
<section>
    <h2>Düzeltme bildirme</h2>
    <p>Yanlış veya güncelliğini yitirmiş bilgiyi sayfanın adresi ve düzeltilmesini istediğiniz bilgiyle iletebilirsiniz.
        @if ($editor = $publication->email('editor_email'))
            <a href="mailto:{{ $editor }}">İçerik editörüne yazın.</a>
        @else
            <a href="{{ route('package-offers') }}#gorusmeler">Destek alanını açın.</a>
        @endif
    </p>
</section>
