# Medloby Master Completion Report

## 1. Yönetim özeti

| Alan | Durum |
| --- | --- |
| İnceleme tarihi | 23 Kasım 2026, Türkiye saati |
| Depo ve çalışma dalı | `medloby/medloby`, yerel `work` |
| Başlangıç ve uzak ana dal | Yerel `25e3f70ddb1fc8eb281530902cfe5508f441a2d8`, GitHub `main` `2991bb17a6c772a19459de864067d44d20459572`. Başlangıç çalışma ağacı temiz. |
| İnceleme kapsamı | Laravel uygulaması, rota ve yetki grupları, modeller ve migration’lar, Blade ve JS tüketicileri, CI ve kilitli paketler, SMTP ve kuyruk, özel dosyalar, yedek ve geri dönüş ile mevcut testlerin modül eşlemesi. |

Ayrıntılı mevcut sözleşmeler ve eski doğrulama kanıtları ilgili bölümde tutulur. [Proje yol haritası](PROJE-YOL-HARITASI.md) · [Yönetim envanteri](ADMIN-TAMAMLAMA.md) · [Yayın güvenliği](GUVENLIK-YAYIN-ENVANTERI.md)

## 2. Doğrulanan kod hataları

| ID, önem ve konum | Belirti, kök neden ve etkilenen alan | Düzeltme, test ve durum |
| --- | --- | --- |
| MC-D01 · Yüksek · Ortak katalog | Personel e-posta muafiyeti olan ve aynı anda eski yönetici işareti taşıyan sentetik, doğrulanmamış hesap ortak hizmet yönetiminde `200` alıyordu. Katalog servisi yönetici işaretini doğrudan okuyordu. | Ortak katalog, merkezi doğrulanmış platform yöneticisi kontrolünü kullanır. Aynı senaryoda okuma ve yazma `403`, normal katalog regresyonu başarılı. `COMPLETE`. [Yetki](app/Services/CatalogManagementAccess.php) · [Test](tests/Feature/CatalogManagementAccessTest.php) |
| MC-D02 · Yüksek · Ertelenmiş klinik taslakları | Arayüzden çıkarılmış klinik onam taslaklarının API’si onaylı klinik sahibine açık kalmıştı. Önizleme kapalı senaryosu `200` dönüyordu. | Açık yerel önizleme ayarı gerekir. Üretim ortamında taslak uçları `404` döner. Önizlemede sahiplik, şube/klinik ayrımı, revizyon ve kayıt bütünlüğü regresyonu geçer. `COMPLETE`. [Denetleyici](app/Http/Controllers/ClinicConsentTemplateController.php) · [Test](tests/Feature/ClinicConsentTemplateTest.php) |
| MC-D03 · Yüksek · Tahsilat ve stok | Ayrı iki süreç aynı şubeye eşzamanlı yazınca SQLite, `FOR UPDATE` ile satır kilitlemediği için ikinci istek `database is locked` ve `500` dönüyordu. Aynı fişin tekrarı, kalan bakiyeyi aşan ikinci ödeme ve stoğu aşan ikinci çıkışla yeniden üretildi. | Şube işlemleri okumadan önce SQLite yazma kilidini alır. PostgreSQL şube satır kilidini kullanır. Aynı fiş tek kez kaydolur ve tekrar `200` döner. Fazla tahsilat ve stok çıkışı `422` ile reddedilir. SQLite grubu 32 test ve 353 doğrulama. [Kilit](app/Models/Branch.php) · [Eşzamanlı test](tests/Feature/ClinicOperationsConcurrencyTest.php) |
| MC-D04 · Orta · Giriş süresi | Geçersiz girişler aynı gövdeyi dönüyordu. Kullanıcı bulunmadığında pahalı parola karşılaştırması atlanıyor ve iki yol için ortak asgari süre uygulanmıyordu. | Giriş Laravel’in `auth.timebox_duration` ayarını ve varsayılan 200 ms tabanını kullanır. Bekleme transaction tamamlandıktan sonra yapılır. Test, bilinen ve bilinmeyen hesapların aynı hatayı vermesini ve iki başarısızlığın da zaman penceresine girmesini doğrular. Tabanı aşan ağ veya parola doğrulama süreleri için canlı ölçüm gerekir. [Kimlik doğrulama](app/Http/Controllers/AuthController.php) · [Test](tests/Feature/AuthControllerTest.php) |

Katalog erişimi ve ertelenmiş taslak grubunda 48 hedefli test ve 355 doğrulama geçti.

## 3. Eksik özellikler

| ID, modül ve öncelik | Gereksinim ve uygulama | Kanıt, durum ve bağımlılık |
| --- | --- | --- |
| MC-F01 · Platform desteği · Yüksek | Mevcut atama ve destek kuyruğuna doğrulanmış destek görevlisi rolü eklendi. Aynı yönetim paneli ve aynı talep kayıtları kullanılır. Tam yönetici yetki verir ve geri alır. İşlem, oturum iptali ve denetim kaydıyla tek transaction içinde tamamlanır. | 35 hedefli API testi ve 432 doğrulama. Chromium’da masaüstü tam yöneticiyle yetki verme ve atama, mobil görevliyle listede doğru talebi bulma ve yanıt gönderme çalıştı. `COMPLETE` yerel kabul. Canlı atamaların sorumlusu ve geçişi yayın kararına bağlı. [Yetki testi](tests/Feature/PlatformSupportAccessTest.php) · [Yönetim envanteri](ADMIN-TAMAMLAMA.md#admin-g2--yönetici-rolleri) |
| MC-F02 · Yönetim rolleri · Yüksek | Destek görevlisi ve mevcut tam yönetici sınırı doğrulandı. Operasyon, finans, uyum ve teknik işletim görevleri için erişim matrisi ve tam yönetici geçişi açık. | `PARTIAL`. Karar MC-E03. [Rol politikası](ADMIN-TAMAMLAMA.md#admin-g2--yönetici-rolleri) |
| MC-F03 · Platform finansı · İlk sürüm dışı | Üç ücretsiz plan seçimi, klinik finans kayıtları ve platform plan dağılımı var. Platform tahsilatı, komisyon, fatura ve iade sözleşmesi tanımlandıktan sonra geliştirilebilir. | `NOT IMPLEMENTED`, MC-E04. [Mevcut finans sınırı](ADMIN-TAMAMLAMA.md#admin-g5--platform-analitiği-ve-finans) |
| MC-F04 · Kamuya açık tedavi içeriği · Orta | Aktif tedavi/kategori kataloğu ve klinik sayfaları çalışıyor. Ayrı indekslenebilir tedavi içerik sayfaları için yayın kapsamı ve tanıtım metinleri bekliyor. | `EXTERNAL_BLOCKER`, MC-E02 ve MC-E05. [Katalog testleri](tests/Feature/CatalogVisibilityTest.php) |
| MC-F05 · Clinic AI Premium | Yol haritasındaki modül daha sonraki ürün kararına bağlı. | `DEFERRED / OWNER DECISION`. İlk yayın engeli yok. [Yol haritası](PROJE-YOL-HARITASI.md#6-yeni-modüller) |

## 4. Mimari uyumluluk ve modül kabulü

| ID ve bileşenler | Sözleşme ve risk | Çözüm ve kanıt |
| --- | --- | --- |
| MC-I01 · Şube verisi ve veritabanı | PostgreSQL satır kilidi ve SQLite işlem yükseltmesi eşdeğer değildi. Hızlı tekrarlar hata veriyordu. | MC-D03. Şube finans, bakiye ve stok işlemleri aynı yazma kilidini alır. Eşzamanlı randevu ve finans testleri aynı izolasyon düzeniyle gerçek ayrı süreçler kullanır. |
| MC-I02 · Randevu ve rapor takvimi | Randevu `starts_at` ve `ends_at` alanları İstanbul duvar saati sözleşmesiyle saklanıyor. Genel olay zamanları UTC ve defter kayıtları takvim günü esaslı. Bütün zaman alanlarının aynı biçimde yorumlanması tarih sınırlarında yanlış sonuç doğurabilir. | Mevcut randevu sözleşmesi kod ve testle belgelendi. Raporlar İstanbul’da yıl değişimi, önceki ay ve yıllık sınırlarla sınandı. UTC’ye veri dönüşümü ayrı uyumluluk ve veri taşıma kararıdır. `PARTIAL` UTC hedefi. [Sözleşme](app/Models/Appointment.php) · [Yıl sınırı testi](tests/Feature/ClinicFinanceReportTest.php) |
| MC-I03 · Destek rolü, panel ve API | Eski panelin ve talep izinlerinin giriş koşulu `is_admin` doğrulamasıydı. Kısıtlı görevlinin genel yönetim alanına alınması fazla yetki verirdi. | Tek yönetim girişi, sunucudan seçilen görevli görünümü ve atanan kayıt filtresi. Güncel atama, yanıt transaction’ında ikinci kez doğrulanır. Atama ve rol değişiklikleri mevcut işlem geçmişinde görünür. [Uçtan uca yetki](tests/Feature/PlatformSupportAccessTest.php) |

| Modül | Son durum ve test kanıtı | Yayın bağımlılığı |
| --- | --- | --- |
| Yönetim | `PARTIAL`. Başvuru, klinikler, hesap özeti, sözleşme, ölçümler, destek rolü ve işlem geçmişi regresyonda. [Panel](tests/Feature/AdminDashboardTest.php) · [Klinik kararları](tests/Feature/AdminBusinessTest.php) | MC-E01, MC-E03 |
| CRM ve hasta adayları | `RELEASE_PENDING`. Şube erişimi, aday, aşama, görev, takip, aktivite ve görüşme bağlantıları regresyonda. [Şube erişimi](tests/Feature/ClinicCrmAccessTest.php) · [Aday](tests/Feature/CrmLeadControllerTest.php) · [Takip](tests/Feature/CrmFollowUpControllerTest.php) | İlk yetkili klinik ve personel provası |
| Hasta ve hasta yolculuğu | `RELEASE_PENDING`. Kayıt, e-posta doğrulama, şifre kurtarma, randevu ve zaman çizelgesi. [Yolculuk](tests/Feature/PatientJourneyTest.php) · [Yayın akışı](tests/Feature/LaunchWorkflowTest.php) | Canlı SMTP ve ilk deneme hesabı |
| Randevular ve hekimler | `RELEASE_PENDING`. Tekil/paket seansları, doktor ve hasta çakışması, izin, saat blokları ve durum geçişleri. [Rezervasyon](tests/Feature/AppointmentBookingTest.php) · [Çakışma](tests/Feature/AppointmentConcurrencyTest.php) · [Takvim](tests/Feature/ClinicCalendarTest.php) | Üretim veritabanı provası, MC-I02 |
| Personel ve şubeler | `RELEASE_PENDING`. E-postasız personel girişi, rol/şube sınırları, takvim, katalog, şube açma ve pasife alma. [Operasyonlar](tests/Feature/ClinicBranchOperationsTest.php) · [Şubeler](tests/Feature/BranchControllerTest.php) | İlk yetkili personel ve farklı şubelerle negatif prova |
| Teklifler, görüşmeler ve ekler | `RELEASE_PENDING`. Teklifin anlık paket içeriği, kabul/ret, mesaj geçmişi, özel depolama ve güncel indirme yetkisi. [Teklif](tests/Feature/OfferCreationTest.php) · [Görüşme](tests/Feature/ConversationTest.php) · [Dosya erişimi](tests/Feature/MessageAttachmentTest.php) | Gerçek web kökü, yükleme ve saklama |
| Finans, bakiyeler ve stok | `RELEASE_PENDING`. Para birimine göre hesaplar, gerçek tahsilat ve kalan bakiye, hekim/tedavi/şube raporu, revizyon ve eşzamanlı hareketler. [Finans](tests/Feature/ClinicFinanceReportTest.php) · [Bakiye ve stok](tests/Feature/ClinicOperationsExpansionTest.php) · [Eşzamanlı işlemler](tests/Feature/ClinicOperationsConcurrencyTest.php) | Klinik kabulü ve saklama kuralları. Platform tahsilatı MC-F03 |
| Bildirim ve destek | `RELEASE_PENDING`. Atama, destek yanıtı, randevu ve CRM hatırlatmaları, bekleme listesi, teklif süresi ve kuyruktan yeniden deneme. [Destek](tests/Feature/SupportTicketTest.php) · [Zamanlayıcı](tests/Feature/ReminderScheduleTest.php) · [Bekleme](tests/Feature/AppointmentWaitingListTest.php) | Gerçek gelen kutusu, zamanlayıcı ve işçi |
| Katalog | `RELEASE_PENDING`. Ortak ve klinik kataloğu, görünürlük, fiyat aralığı, paket ve seanslar. [Katalog](tests/Feature/CatalogManagementAccessTest.php) · [Klinik dizini](tests/Feature/ClinicDirectoryTest.php) | Klinik ruhsatı ve içerik onayı |
| Gizlilik ve politika sayfaları | `EXTERNAL_BLOCKER`. Taslaklar, sözleşme sürümü ve kabul kaydı, ayrı önizleme ve yayın ayarları testli. [Politikalar](tests/Feature/PolicyPagesTest.php) · [KVKK](tests/Feature/PrivacyNoticeTest.php) | MC-E02 |
| SEO | `RELEASE_PENDING`. Robots, sitemap, canonical, anonim klinik sayfası ve özel alanlar için noindex. [SEO ve başlıklar](tests/Feature/ReleaseHardeningTest.php) | MC-F04, MC-E05 |

## 5. Güvenlik kabulü

| Kontrol | Yerel kanıt ve kalan doğrulama |
| --- | --- |
| Sırlar ve CI | Gitleaks ile Git geçmişi, izlenen kaynaklar, derlenmiş dosyalar ve yerel uygulama logu maskeli taranır. `.env` ve alt türleri izleme dışı, `.env.example` izleniyor. CI aynı Git ve derleme taramasını içerir. |
| Kimlik ve yetki | Olay yanıtı, token iptali, klinik sahibi, e-postasız personel, hasta ve destek görevlisi için çapraz erişim senaryoları var. Ortak katalog MC-D01. Atanan destek kuyruğunun olumsuz rol testleri MC-F01. Yönetici MFA ve canlı alarm MC-E03. |
| İstek sınırı ve önbellek | 429, `Retry-After`, vekil başlığı ve ortak sayaç testli. Klinik, hasta ve finans API’leri özel önbellek davranışıyla sınanıyor. Yeni uç kotaları ve gerçek trafik ölçümü MC-E06. |
| Dosya, log ve yedek | Özel disk, 10 MB sınırı, MIME ve uzantı, çapraz erişim, güvenli dosya adı ve veritabanı hatası maskelemesi regresyonda. SQLite ve PostgreSQL yedeği sentetik dosyalarıyla geri yüklenir. Canlı disk ve tarama MC-E01 ve MC-E06. |
| Denetim ve olay müdahalesi | Klinik, sözleşme ve destek olaylarıyla rol değişiklikleri ortak denetim sorgusuna bağlı. Platform erişimi mevcut yetkiyle doğrulanır. Olay sorumlusu ve alarm kanalı MC-E03. [Müdahale planı](GUVENLIK-YAYIN-ENVANTERI.md#rs-12-olay-müdahalesi) |

## 6. Test ve araç sonuçları

PHP 8.4.28 ve Node.js 24.22.3. 428 PHP dosyası ve JavaScript sözdizimi doğrulandı. Blade, rota önbelleği ve Vite üretim derlemesi çalıştı. Önbellekte 301 rota var, 286 tanesi API. JS 192,25 kB, CSS 62,19 kB ve WOFF2 88,78 kB. Vite gzip tahminleri canlı sunucu ölçümü kapsamına girmez.

`npm audit` toplam 0 bulgu. Composer’ın Packagist güvenlik uç noktasında HTTP tünelinde 403 alındı. GitHub Advisory Database üzerinden kilitli 133 Composer sürümü kontrol edildi ve eşleşme çıkmadı. Kilit dosyalarındaki MIT, BSD, Apache, ISC, MPL ve diğer lisans beyanları incelendi. Yeni bağımlılık eklenmedi. Paket dağıtım bildirimleri gerçek dağıtım biçimiyle birlikte korunur.

Gitleaks 8.24.3 ile başlangıç Git geçmişinde, son kaynak ve derleme kopyasında ve son yerel uygulama günlüğünde 0 sır bulgusu çıktı. Tarama, seçilen yerel içerikle sınırlıdır. Canlı sır deposu ve hosted CI arşivi MC-E01 ve MC-E07 kapsamında.

Hedefli Semgrep taraması 268 kaynakta çalıştı. 34 ham SQL eşleşmesi ve bir HTML ataması incelemeye alındı, altı dosyada kısmi ayrıştırma uyarısı vardı. Kaynak kontrolü PHP sözdizimi, SQL bağları, sabit sorgular ve yetkili Blade yanıtıyla birlikte değerlendirildi. Test kapsamı güvenlik envanterine bağlandı.

İlk regresyonda iki test uyumsuzluğu yakalandı. Genel giriş sayfası testi yeni platform hesabı etiketine uyarlandı. Klinik taslak testinin geçici üretim ortamı ve Symfony güvenilen alan adı listesi test sonunda geri yüklendi. Giriş, taslak ve HTTPS doğrulama grubu birlikte yeniden çalıştırıldı ve 42 test geçti. Chromium’da açık destek oturumunun rol kaldırıldıktan sonra kapandığı da doğrulandı.

Son regresyonda SQLite üzerinde **1.142 test, 7.160 doğrulama**, PostgreSQL 17 üzerinde **1.145 test, 7.183 doğrulama** geçti. SQLite’ta atlanan üç yedek senaryosu PostgreSQL’de çalıştı. İki randevu ve üç klinik tahsilat/stok eşzamanlılık senaryosu her iki veritabanında geçti. Dört Node testi de geçti. Pint, ele alınan PHP dosyalarında çalıştırıldı. `routes/api.php` için başlangıç sürümüyle aynı beş biçim kurallı uyarı görüldü. [Ortak eşzamanlılık düzeni](tests/TestCase.php)

Temiz SQLite kabul veritabanında `migrate` ve şema kontrolü çalıştı. Sağlıklı test yapılandırması `release-check` testinde `0` döndürür. Yerel deneme ayarlarında komut `1` döndürdü ve şema, özel disk, kuyruk ve başarısız iş deposunu hazır gösterdi. HTTPS, güvenli çerez, API süresi, ilk yönetici, yayımdaki sözleşme, KVKK/politika ve SMTP alanları eksik göründü. Zamanlayıcı dört mevcut görevi listeliyor. [Yayın kontrolü testleri](tests/Feature/ReleaseReadinessTest.php)

## 7. Gizlilik ve hukuk

İşletmeci ve KVKK başvuru kanalları, geçerli sözleşme sürümü, hasta ve klinik aydınlatmaları, özel nitelikli veri işleme şartı, aktarım, saklama ve imha, çerez tercihi ve ilk klinik içeriği MC-E02’de. Klinik onam önizlemesi MC-D02 sınırında. Depodaki tıbbi kayıt modelleri için ilk yayın iş akışı açılmadı. [Veri işleme taslağı](resources/contracts/medloby-veri-isleme-ve-saklama-taslagi.md)

Önceki kabulde 6698 sayılı Kanunun bildirim hükmü ve sağlık tanıtımı için resmî mevzuat kaynakları kaydedildi. Bu son kontrolde KVKK bildirim sayfaları 503 döndürdü, Mevzuat ve Resmî Gazete isteklerinin süresi doldu. Kurul usulü, güncel süreler, ilk klinik içeriği ve metinlerin yürürlük durumu hukuk onayıyla tekrar doğrulanacak. [6698 sayılı Kanun](https://www.mevzuat.gov.tr/mevzuatmetin/1.5.6698.pdf) · [KVKK ihlal bildirimi](https://www.kvkk.gov.tr/Icerik/5362/Veri-Ihlali-Bildirimi) · [Sağlık tanıtımı düzenlemesi](https://www.resmigazete.gov.tr/eskiler/2025/11/12/20251112-2.htm)

## 8. Yayın ve dış bağımlılıklar

| ID ve tür | Eksik erişim veya karar | Kabul ve yayın etkisi |
| --- | --- | --- |
| MC-E01 · `EXTERNAL_BLOCKER` · Dağıtım | Gerçek domain ve DNS, HTTPS/TLS, vekil, PHP ve DB, sır deposu, özel depolama, SMTP/gelen kutusu, kuyruk, zamanlayıcı, alarm, şifreli yedek geri dönüşü ve ilk hesabın provası için yetkili deneme ortamı ve dağıtım onayı gerekli. | `RELEASE_PENDING`. Teknik kontrol yerelde çalıştı. HTTPS, canlı DB yetkileri, gerçek teslimat, Gzip/Brotli ve canlı duman testleri sunucu provasında. [Yayın listesi](README.md#medloby-yayına-çıkmadan-önce) |
| MC-E02 · `EXTERNAL_BLOCKER` · Hukuk ve içerik | İşletmecinin gerçek kimliği ve adresi, KVKK kanalları, sağlık verisi ve aktarım koşulları, saklama/imha, çerez ve hukuk onaylı metinler. İlk klinik ruhsat ve ilan doğrulaması da işletmeciye bağlı. | `LEGAL_APPROVAL_REQUIRED`. Resmî yayın ve ilk klinik kabulünü etkiler. |
| MC-E03 · `EXTERNAL_BLOCKER` · Erişim ve işletim | Platform operasyon, finans, uyum ve teknik işletim matrisleri, tam yönetici geçişi, MFA yöntemi, destek atama sorumlusu, olay yetkilisi ve alarm kanalı. | Destek rolü yerelde hazır. Daha geniş platform rolleri ve gerçek hesap geçişi işletim kararına bağlı. |
| MC-E04 · `EXTERNAL_BLOCKER` · Platform geliri | Ücretlendirme, ödeme sağlayıcısı, komisyon, vergi/fatura ve iade kararları. | Ücretsiz planlı ilk yayının kapsamı dışında. |
| MC-E05 · `EXTERNAL_BLOCKER` · SEO ve analitik | Domain sahipliği, Search Console hesabı, varsa GA4 kimliği, tercih ve veri aktarım onayı. | Sitemap ve robots kodu hazır. İndeksleme ve ölçüm yayına bağlanmayı bekliyor. |
| MC-E06 · `EXTERNAL_BLOCKER` · İşletim güvencesi | Üretim trafik ölçümü, çok düğümlü ortak sayaç, dosya karantinası seçimi, saklama kararları ve gerçek günlüklere yetkili erişim. | Sunucu kapasitesi ve operasyon kabulünü etkiler. |
| MC-E07 · `EXTERNAL_BLOCKER` · GitHub teslimi | Uzak gönderim için yetkili onay. | Hosted CI, yerel commit’lerin GitHub’a gönderiminden sonra doğrulanabilir. |

## 9. Git teslimi

| Yerel commit | Kod, test ve kapsam |
| --- | --- |
| `bb4c6bc` | Ortak katalog için doğrulanmış yönetici ve ertelenmiş taslak API sınırı, MC-D01 ve MC-D02. |
| `0f0f912` | Atanan destek rolü, tek panel, atama filtreleri, yeni migration ve ortak rol denetimi, MC-F01 ve MC-I03. |
| `e7593dd` | SQLite eşzamanlı işlemleri, PostgreSQL uyumu, yıl sınırı ve test süreçleri, MC-D03, MC-I01 ve MC-I02. |
| `a735254` | Laravel giriş zaman penceresi ve hesap bazında eşdeğer hata regresyonu, MC-D04. |

Başlangıçtan itibaren 39 dosyada değişiklik var. Yeni migration doğrulanmış destek görevlisi rolü alanı ve rol olayı kaydını ekler. Canlı veritabanında uygulanması yetkili dağıtım ve yedek provası sırasında yapılır. Başlangıçta ve kabul sonrası okunan GitHub `main` aynı SHA’dadır. Uzak gönderim bekliyor. Bu belgenin ve test izolasyon düzeltmelerinin son commit’i yerel geçmişte yukarıdaki kod commit’lerini izler.

## 10. Son karar

| Soru | Kabul |
| --- | --- |
| Mühendislik | Bu rapordaki dört doğrulanmış kod bulgusu ve atanmış destek rolü yerelde teslim edildi. Modül teslim kapsamı ve açık ürün işleri bölüm 3 ve 4’te. |
| Güvenlik | Yerel kod, olumsuz erişim testleri, eşzamanlı işlem provası ve dosya sınırları doğrulandı. Canlı TLS, MFA, alarm ve güvenlik altyapısı kabulü `RELEASE_PENDING`. |
| Gizlilik | `LEGAL_APPROVAL_REQUIRED`. |
| Canlı yayın | `RELEASE_PENDING`. Yetkili deneme ortamı, işletmeci/hukuk ve canlı işletim onayları bekliyor. |

İlk yayın için sıradaki kabul yetkili bir deneme ortamında HTTPS, gerçek SMTP, işçi ve zamanlayıcı, özel dosya, şifreli yedek geri dönüşü ve ilk kullanıcı akışlarının birlikte doğrulanmasıdır. İşletmeci ve hukuk onayı ile GitHub teslim yetkisi ayrıca kaydedilir. [Yayın öncesi kontrol listesi](README.md#medloby-yayına-çıkmadan-önce)
