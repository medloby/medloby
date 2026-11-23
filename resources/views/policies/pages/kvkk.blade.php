<section>
    <h2>Kişisel verilerin korunması</h2>
    <p>Hasta ve klinik hesaplarının kullandığı bilgiler farklı olduğu için Medloby her hesap türü için ayrı aydınlatma metni sunar. Sağlık verisi içeren kayıtların işleme şartları amaç ve sorumluluk kapsamına göre değerlendirilir. Klinikteki tedavi ve hasta kayıtlarına ilişkin aydınlatmayı ilgili sağlık kuruluşundan isteyebilirsiniz.</p>
</section>
@if ($publication->isPublished())
    <section>
        <h2>Aydınlatma metinleri</h2>
        <ul>
            <li><a href="{{ route('privacy.notice', 'hasta') }}">Hasta hesabı KVKK aydınlatma metni</a></li>
            <li><a href="{{ route('privacy.notice', 'klinik') }}">Klinik başvurusu ve hesabı KVKK aydınlatma metni</a></li>
        </ul>
    </section>
    <section>
        <h2>Veri sorumlusu</h2>
        <p>{{ $publication->operator('legal_name') }}</p>
        <p class="whitespace-pre-line">{{ $publication->operator('postal_address') }}</p>
    </section>
    @include('privacy.rights')
@else
    <section>
        <h2>Başvuru hakları</h2>
        <p>6698 sayılı Kanunun 11 inci maddesi, kişisel verileriniz hakkında bilgi isteme, yanlış bilgilerin düzeltilmesini talep etme ve kanuni şartlar oluştuğunda silme veya yok etme isteme haklarını düzenler. Resmî başvuru adresleri ve aydınlatma metinleri işletmeci bilgileri doğrulandıktan sonra bu sayfada gösterilir.</p>
    </section>
@endif
<p><a href="https://www.mevzuat.gov.tr/mevzuatmetin/1.5.6698.pdf">6698 sayılı Kişisel Verilerin Korunması Kanunu</a></p>
