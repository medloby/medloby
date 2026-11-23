<section>
    <h2>Çerezler ve tarayıcı depolaması</h2>
    <p>Medloby web sayfalarının oturum bütünlüğünü sağlamak ve form isteklerini korumak için kendi alan adındaki zorunlu çerezleri kullanır. Paket kataloğundan görüşmeye geçişte seçtiğiniz paketi tarayıcı sekmesinde geçici olarak hatırlayabilir.</p>
</section>
<section>
    <h2>Kullanılan kayıtlar</h2>
    <ul>
        <li><strong>{{ config('session.cookie') }}</strong> web oturumunu sürdürür. Oturumun hareketsizlik süresi site ayarına göre {{ config('session.lifetime') }} dakikadır. @if (config('session.expire_on_close')) Çerez tarayıcı oturumu sonunda sona erer. @endif</li>
        <li><strong>XSRF-TOKEN</strong> isteklerin ilgili oturumdan gelmesini doğrulamaya yardımcı olan güvenlik çerezidir. Süresi web oturumu için yapılandırılan süreyle hesaplanır.</li>
        <li><strong>medloby-pending-package</strong> sekmenin oturum depolamasında seçilen paketin kimliğini ve adını geçici olarak tutabilir. Bu kayıt tarayıcı sekmesinin oturumu sona erdiğinde silinir.</li>
    </ul>
</section>
<section>
    <h2>Tercihler ve kişisel veriler</h2>
    <p>Tarayıcı ayarlarından çerezleri inceleyebilir, silebilir veya engelleyebilirsiniz. Zorunlu çerezlerin engellenmesi bazı form ve oturum işlemlerini etkileyebilir. Bu sürümde reklam ve ziyaretçi takip çerezleri kapalıdır. Kişisel verilere ilişkin bilgi ve haklar için <a href="{{ route('policies.show', 'kvkk') }}">KVKK sayfasına</a> bakabilirsiniz.</p>
</section>
