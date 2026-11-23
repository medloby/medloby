<section>
    <h2>Medloby’nin sunduğu hizmet</h2>
    <p>Medloby, klinik ve tedavi paketlerini incelemeyi, seçtiğiniz klinikle iletişim kurmayı, kliniğin özel teklifini değerlendirmeyi ve uygunluk üzerinden randevu planlamayı sağlar. Muayene, teşhis, tedavi, tıbbi kayıt ve takip ilgili sağlık kuruluşu ile sağlık meslek mensupları tarafından yürütülür.</p>
</section>
<section>
    <h2>Teklif ve randevu</h2>
    <p>Katalog paket içeriğini tanıtır. Sağlık hizmetinin kapsamını, seansları, fiyatı ve geçerlilik süresini klinik size özel teklifte açıklar. Teklif kabulünden sonraki seanslar ayrı ayrı planlanır. Randevu durumunu hesabınızdan izleyebilirsiniz. Tarih değişikliği veya iptal koşullarını klinikle doğrulayın ve dışarıdan ulaşım ya da konaklama ayarlamadan önce randevu durumunu kontrol edin.</p>
</section>
<section>
    <h2>Klinik üyeliği ve başvurular</h2>
    <p>Klinik başvurusu yönetici incelemesinden geçer. Klinik hesabı onaydan sonra etkinleşir. Kliniklere uygulanan üyelik, şube ve personel koşulları başvuru sırasında sunulan yürürlükteki klinik üyelik sözleşmesinde yer alır.</p>
</section>
<section>
    <h2>Destek ve haklar</h2>
    <p>Hesap, bildirim veya platform işlemleriyle ilgili sorunları <a href="{{ route('package-offers') }}#gorusmeler">hesabınızdaki destek alanından</a> iletebilirsiniz. Sağlık hizmeti ve tedaviye ilişkin başvurular ilgili sağlık kuruluşuna yapılabilir. Kanundan doğan başvuru, şikâyet ve tüketici hakları saklıdır.</p>
    @if ($publication->isPublished())
        <p class="mt-2">Platform işletmecisi {{ $publication->operator('legal_name') }}.</p>
        <p class="whitespace-pre-line">{{ $publication->operator('postal_address') }}</p>
    @endif
</section>
