# Medloby veri işleme ve saklama planı

22 Kasım 2026 tarihli işletim taslağı. Hasta hesabı ile klinik başvurusu ve hesabı için kullanılan aydınlatma metinleri uygulamada ayrı hazırlanmıştır. [Hasta metni](../views/privacy/patient.blade.php) · [Klinik metni](../views/privacy/clinic.blade.php) · [Ortak haklar ve başvuru](../views/privacy/rights.blade.php)

## Kayıtlar ve erişim yetkileri

| Kayıt | Kaynak ve kullanım | Erişim |
| --- | --- | --- |
| Hesap, iletişim ve profil | Kayıt ve profil formları, doğrulama ve oturum işlemleri. Hesap açma, e-postayı doğrulama, erişim sağlama ve hesap güvenliği | Hesap sahibi. Destek veya güvenlik görevi için gerekli hallerde yetkilendirilen platform personeli |
| Klinik başvurusu ve sözleşme kabulü | Başvuru ve inceleme kaydı, kabul sürümü, zaman, yöntem, IP adresi ve tarayıcı bilgisi. Başvuruyu değerlendirme ve elektronik kabulü ispatlama | Başvuru sahibi kendi başvuru özetine ulaşır. İnceleme ve sözleşme kayıtlarına görevli platform yöneticileri erişir |
| Şube üyeliği ve CRM | Yetkili kliniğin girdiği üyelik, personel rolü, şube erişimi, aday iletişim ve takip bilgileri. Şube işlerinin yürütülmesi | Aktif ve onaylı kliniğin ilgili şubesinde görevli ve yetkili hesaplar |
| Görüşme, dosya, teklif, randevu ve seyahat planı | Hasta ve klinik işlemleri. Paket ve seans planı, teklif geçmişi, randevu takibi, kabul edilen paket içindeki konaklama ve transfer bilgilerinin iletilmesi | İlgili hasta ve işlem kapsamındaki yetkili klinik hesapları. Dosya erişimi görüşme yetkisiyle doğrulanır |
| Bildirim ve güvenlik kaydı | E-posta doğrulama, uygulama içi bildirimler ve güvenlik için gereken işlem kayıtları | Bildirimin alıcısı ve işlem için görevlendirilmiş kişiler. E-posta iletisinde hesap bağlantısı kullanılır |
| Destek talebi ve yazışması | Hesap sahibi tarafından girilen konu ve açıklama, platformun yanıtları, yazışma tarihleri ve talebin durumu. Destek isteğini yanıtlamak ve sonucunu takip etmek | Talep sahibi ve yetkili platform yöneticileri |
| Klinik gelir ve gider kayıtları | Yetkili klinik hesabının girdiği işlem tarihi, tutar, para birimi, açıklama ve kayıt durumu. Şubenin kendi gelir ve gider takibi | Klinik sahibi ve ilgili şubede gelir ve gider yetkisi verilen hesaplar |
| KVKK başvurusu ve imha kaydı | İlgili kişinin ilettiği başvuru, cevap, gerektiğinde üçüncü tarafa bildirimin ve uygulanan işlemin kanıtı | Başvuruyu değerlendiren ve imha işlemini yürüten görevli kişiler |

Klinik, tedavi, tıbbi değerlendirme ve kendi kayıtlarının veri işleme şartlarını belirler. Medloby kendi üyelik, güvenlik ve platform işletimi amaçlarını belirlediği süreçlerde veri sorumlusudur. Klinik adına yürütülen işlemlerde roller, talimatlar, veri kapsamı ve alt hizmet sağlayıcılar yazılı olarak belirlenir. Sağlık verisi içerebilecek tedavi, randevu, görüşme ve dosyalar tıbbi kayıt işlevi olarak genişletilmeden önce işleme şartı, aydınlatma, erişim ve güvenli aktarım her kullanım amacı için doğrulanır. [6698 sayılı Kanun, m. 4–10](https://www.mevzuat.gov.tr/mevzuatmetin/1.5.6698.pdf)

## Başvuru işlemi

1. İlgili kişi imzalı yazılı başvurusunu veri sorumlusunun posta adresine iletir. Kuruluşta kullanılan kanallar arasından KEP veya kişinin daha önce bildirdiği kayıtlı e-posta adresinden başvuru seçenekleri de aydınlatma metninde gösterilir.
2. Görevli kişi başvuru tarihini, talebin kapsamını ve cevap kanalını kaydeder. Kimliği ve varsa temsil yetkisini talep için gerekli ölçüde doğrular. Talep klinik kayıtlarına da uzanıyorsa yetkili klinikle gerekli kısım üzerinden güvenli biçimde iletişim kurar.
3. Veri sorumlusu talebi en kısa sürede ve en geç 30 gün içinde sonuçlandırır. Kararı ve gerekçesini yazılı veya elektronik olarak bildirir. Düzeltme, silme veya yok etme uygulanmışsa kanuni şartlara göre gerekli üçüncü taraf bildirimlerini ve işlem kaydını tamamlar.

## Saklama ve imha

| Kayıt grubu | Saklama ihtiyacını gözden geçirme olayı | İşlem |
| --- | --- | --- |
| Hesap, profil ve erişim | Hesabın kapanması veya personel görevinin bitmesi | Şube erişimini kaldır, ilgili oturum ve belirteçleri sona erdir, hesap ve profil kayıtlarının kalan hukuki amaçlarını kontrol et |
| Klinik başvurusu ve sözleşme | Başvurunun sonuçlanması veya üyeliğin sona ermesi | İtiraz, ispat ve uygulanacak kanuni süreleri kontrol et. Sözleşme sürümünü ve kabul kanıtını bağlantısıyla koru |
| CRM, görüşme, teklif, ek, randevu ve seyahat planı | Takibin kapanması, teklifin, randevunun veya seyahat planının sonuçlanması | Devam eden talepleri, ilgili kliniğin kayıt yükümlülüklerini ve varsa uyuşmazlığı kontrol et. İmha kararı ilgili dosyayı, kayıt bağlantısını ve arşiv kopyalarını kapsar |
| Güvenlik, bildirim ve yedek | İnceleme ihtiyacının veya yedek döngüsünün tamamlanması | Süresi dolan kayıt veya yedeği uygulanan yöntemle imha et. Geri yükleme halinde daha önce uygulanan imha kararlarını yeniden işle |
| Destek talebi ve yazışması | Destek talebinin kapanması | Kalan işlem ve kanuni saklama ihtiyacını kontrol et. Saklama şartı sona erdiğinde talep, yazışma ve varsa yedek kopyalarına imha sürecini uygula |
| Klinik gelir ve gider kayıtları | Kayıt döneminin veya klinik üyeliğinin sona ermesi | Kliniğin yükümlülükleri ve yetkili erişim süresini doğrula. Saklama süresi sona erdiğinde kayıt ve yedekler için imha sürecini uygula |
| İlgili kişi başvurusu ve imha kanıtı | Başvuru ve ilgili itiraz sürecinin kapanması | Kanuni saklama şartlarına göre yalnızca gerekli başvuru ve işlem kanıtını koru |

Saklama sebebi sona erdiğinde yetkili kişi silme, yok etme veya anonimleştirme yöntemini seçer. Tarih, kapsam, yöntem ve işlemi yapan görevli kaydedilir. Somut bir uyuşmazlık veya kanuni saklama sebebi olan kayıtlar ilgili amaç ve yetkiyle sınırlı tutulur. Azami süreler, periyodik imha takvimi ve yedek döngüsü gerçek işletmeci ve dağıtım ortamı belirlendiğinde devreye alınır. [6698 sayılı Kanun, m. 4, 7, 11 ve 13](https://www.mevzuat.gov.tr/mevzuatmetin/1.5.6698.pdf)

## Yayına alınacak bilgiler

- İşletmecinin tam unvanı, tebligat ve KVKK posta adresi, kullanılacak elektronik başvuru kanalları, destek ve içerik editörü adresleri.
- Barındırma, yedekleme, depolama ve e-posta sağlayıcıları, erişim yetkilileri, sağlayıcıların işlem yaptığı ülkeler ve uygulanacak aktarım şartları.
- Her kayıt grubunun kesin azami süresi, başvuruyu karşılayacak görevli, klinik ile platform arasındaki sağlık verisi rollerinin ve işleme şartlarının eşlemesi.

Aydınlatma ve başvuru usulleri 10 Mart 2018 tarihli ve 30356 sayılı Resmî Gazete’de yayımlanan ilgili tebliğler kapsamında değerlendirilir. [Resmî Gazete](https://www.resmigazete.gov.tr/eskiler/2018/03/20180310.htm)
