<section>
    <h2>Haklarınız</h2>
    <p>6698 sayılı Kanunun 11 inci maddesi uyarınca veri sorumlusuna başvurarak şu hakları kullanabilirsiniz.</p>
    <ul class="mt-3 list-disc space-y-2 pl-5 leading-relaxed">
        <li>Kişisel verilerinizin işlenip işlenmediğini öğrenme ve işlenmişse bilgi isteme.</li>
        <li>İşleme amacını ve verilerin bu amaca uygun kullanılıp kullanılmadığını öğrenme.</li>
        <li>Verilerin aktarıldığı yurt içindeki veya yurt dışındaki üçüncü kişileri bilme.</li>
        <li>Eksik veya yanlış verilerin düzeltilmesini isteme.</li>
        <li>Kanunun 7 nci maddesindeki şartlarla verilerin silinmesini veya yok edilmesini isteme.</li>
        <li>Düzeltme, silme veya yok etme işlemlerinin verilerin aktarıldığı üçüncü kişilere bildirilmesini isteme.</li>
        <li>Verilerin yalnızca otomatik sistemlerle incelenmesi sonucunda aleyhinize ortaya çıkan sonuca itiraz etme.</li>
        <li>Kanuna aykırı işlem sebebiyle zarara uğradıysanız zararın giderilmesini isteme.</li>
    </ul>
</section>
<section>
    <h2>Nasıl başvurabilirsiniz?</h2>
    <p>İmzalı yazılı başvurunuzu veri sorumlusunun yukarıdaki posta adresine iletebilirsiniz.</p>
    @if ($kep = $publication->email('privacy_kep'))
        <p class="mt-2">KEP hesabınızdan <span class="break-all font-medium">{{ $kep }}</span> adresine başvurabilirsiniz.</p>
    @endif
    @if ($email = $publication->email('privacy_email'))
        <p class="mt-2">Daha önce Medloby’ye bildirdiğiniz ve sistemde kayıtlı e-posta adresinizden <a class="break-all underline underline-offset-4" href="mailto:{{ $email }}">{{ $email }}</a> adresine başvurabilirsiniz.</p>
    @endif
    <p class="mt-3">Başvurunuzda ad ve soyadınız, tebligata esas adresiniz ve talebiniz yer almalıdır. Türkiye Cumhuriyeti vatandaşları T.C. kimlik numarasını, yabancılar uyruğunu ve pasaport numarasını veya varsa kimlik numarasını belirtir. Varsa bildirim için kullanılan e-posta, telefon ve faks numarasını ekleyin. Yazılı başvuruyu imzalayın ve talebin değerlendirilmesi için gerekli belgeleri ekleyin.</p>
    <p class="mt-3">Başvurunuz en kısa sürede ve en geç 30 gün içinde sonuçlandırılır. İşlemin ayrıca maliyet gerektirmesi hâlinde Kurulun belirlediği tarifedeki ücret alınabilir.</p>
</section>
