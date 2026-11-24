# Medloby Master Completion Report

## 1. Yönetim özeti

GitHub teslimi ve Actions sonucu 12. bölümde, son yerel provalar ve canlı kabul için kalan işler 13. bölümde yer alır. Bu belgedeki önceki Git ve test sayıları ilgili inceleme adımının kayıtlarıdır.

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
| MC-E07 · GitHub teslimi | Yetkili fast-forward gönderimi ve aynı SHA’nın GitHub Actions sonucu. | [Güncel teslim kaydı](#12-github-teslimi-ve-actions-kabulü) |

## 9. Git teslimi

| Yerel commit | Kod, test ve kapsam |
| --- | --- |
| `bb4c6bc` | Ortak katalog için doğrulanmış yönetici ve ertelenmiş taslak API sınırı, MC-D01 ve MC-D02. |
| `0f0f912` | Atanan destek rolü, tek panel, atama filtreleri, yeni migration ve ortak rol denetimi, MC-F01 ve MC-I03. |
| `e7593dd` | SQLite eşzamanlı işlemleri, PostgreSQL uyumu, yıl sınırı ve test süreçleri, MC-D03, MC-I01 ve MC-I02. |
| `a735254` | Laravel giriş zaman penceresi ve hesap bazında eşdeğer hata regresyonu, MC-D04. |

Bu aşamada 39 dosya değişti. Doğrulanmış destek görevlisi rolü ve rol olayları için migration eklendi. Canlı veritabanı uygulaması yetkili dağıtım ve yedek provasında yapılır. Aşama sonunda okunan GitHub `main` başlangıç SHA’sındaydı.

## 10. Son karar

| Soru | Kabul |
| --- | --- |
| Mühendislik | Bu rapordaki dört doğrulanmış kod bulgusu ve atanmış destek rolü yerelde teslim edildi. Modül teslim kapsamı ve açık ürün işleri bölüm 3 ve 4’te. |
| Güvenlik | Yerel kod, olumsuz erişim testleri, eşzamanlı işlem provası ve dosya sınırları doğrulandı. Canlı TLS, MFA, alarm ve güvenlik altyapısı kabulü `RELEASE_PENDING`. |
| Gizlilik | `LEGAL_APPROVAL_REQUIRED`. |
| Canlı yayın | `RELEASE_PENDING`. Yetkili deneme ortamı, işletmeci/hukuk ve canlı işletim onayları bekliyor. |

İlk yayın için sıradaki kabul yetkili bir deneme ortamında HTTPS, gerçek SMTP, işçi ve zamanlayıcı, özel dosya, şifreli yedek geri dönüşü ve ilk kullanıcı akışlarının birlikte doğrulanmasıdır. İşletmeci ve hukuk onayı ile GitHub teslim yetkisi ayrıca kaydedilir. [Yayın öncesi kontrol listesi](README.md#medloby-yayına-çıkmadan-önce)

## 11. 24 Kasım 2026 Git uzlaştırması ve yeniden kabul

| Git noktası | Doğrulanan değer |
| --- | --- |
| Yerel `work` dalının oluşturulduğu commit | `174f87a6a8eee0449653b5128f73623886b38437` |
| GitHub `main` ve ortak ata | `2991bb17a6c772a19459de864067d44d20459572` |
| Önceki Master çalışmasının başlangıcı | `25e3f70ddb1fc8eb281530902cfe5508f441a2d8` |
| Bu uzlaştırmanın başlangıç HEAD’i ve önceki son dokümantasyon commit’i | `17b5572a5ffdff386f285ac467580981dd931580` |

`work` reflog kaydı dalın `FETCH_HEAD` üzerinden oluşturulduğu commit’i gösteriyor. Bu aşamada okunan `main` bu commit’in 144 commit ilerisinde ve hem `25e3f70` hem `17b5572` için doğrudan ataydı. `git merge-base` iki karşılaştırmada da `2991bb17` döndürdü. Önceki başlangıç `main` dalından 19 commit, bu uzlaştırmanın başlangıcı 24 commit ilerideydi. Uzak tarafta ilave commit yoktu ve çalışma ağacı temizdi.

### Bu uzlaştırmada tamamlananlar

| Konu | Değişiklik ve kanıt |
| --- | --- |
| Klinik üyelik işlem geçmişi | Platform yöneticisinin klinik üyesi rolü, etkinliği ve özel izin değişiklikleri aynı veritabanı işlemi içinde kaydediliyor. Aynı değer ikinci bir olay üretmiyor. Mevcut yönetim panelinin işlem geçmişi klinik üyeliği filtresi, işlemi yapan kişi ve önceki/yeni değerleri gösteriyor. Yanıt e-posta, kod ve hesap sırrı içermiyor. Mobil Chromium’da rol ve durum değişikliği aynı panelden yapıldı ve tarihçe görüntülendi. [Yönetim testleri](tests/Feature/AdminDashboardTest.php) |
| Yayın oturumu | `medloby:release-check` kalıcı oturum deposu gerekliliğini, veritabanı sürücüsünün sütunlarını ve dosya deposunun erişim sınırını doğruluyor. Yedi yeni senaryoda önceki commit eksik yapılandırmaya hazır diyordu. Bu vakalar güncel komutta hata veriyor. [Yayın testi](tests/Feature/ReleaseReadinessTest.php) |
| SQLite ve PostgreSQL migration provası | Destek rolü migration’ı iki motorun ayrı test veritabanlarında uygulandı, geri alındı ve yeniden uygulandı. Eski yönetici, parola özeti, erişim anahtarı, destek talebi ve atama korunuyor. Klinik üyelik geçmişi eklemeli bir tabloyla başlıyor. Yerel geliştirme SQLite dosyası yedeklendi, iki bekleyen migration uygulandı ve ilişkiler doğrulandı. Uygulanan migration sayısı 83, bekleyen sayı 0. [Migration testi](tests/Feature/PlatformSupportMigrationTest.php) |

### Bağımsız yeniden doğrulama

| Önceki kayıt | Güncel kaynakta ayrı olarak çalıştırılan senaryo |
| --- | --- |
| MC-D01 | Ortak katalogda yanlış doğrulama muafiyeti, 1 test ve 3 doğrulama. |
| MC-D02 | Onam taslağının ortam, yapılandırma ve sürüm sınırı, 13 test ve 130 doğrulama. |
| MC-D03 | Finans, stok ve randevuda bağımsız eşzamanlı süreçler, 5 test ve 36 doğrulama. |
| MC-D04 | Yetkisiz girişte Laravel zaman penceresi, 1 test ve 4 doğrulama. |
| MC-F01 | Atama, yanıt, rolün geri alınması ve panel/API kapsamı, 21 test ve 366 doğrulama. |

Yıl sınırında randevu takvimi ve finans raporu mevcut İstanbul iş günü sözleşmesine göre yeniden test edildi. Genel işlem zamanı UTC, defter kayıtları tarih alanı, randevu başlangıç ve bitişi İstanbul yerel saati olarak ele alınıyor. Tarihsel randevuları UTC’ye dönüştürme gelecekteki veri geçişi kararıdır. [Rapor sınır testleri](tests/Feature/ClinicFinanceReportTest.php)

### Kapsamı yerel doğrulamayla sınırlı kabul

Güvenlik ve gizlilik denetimi mevcut yönetici, atanmış destek görevlisi, klinik sahibi ve şube personeli, hasta ve anonim ziyaretçi sınırlarına göre yapıldı. MFA için mevcut tek yönetim girişi ve doğrulanmış yönetici koşulu kullanılabilir. Faktör türü, zorunluluk, ilk kayıt, kurtarma ve mevcut yönetici hesaplarının geçişi onay bekliyor. [Güvenlik ve yayın envanteri](GUVENLIK-YAYIN-ENVANTERI.md)

Canlı kabul için gerçek sunucu erişimi, DNS/TLS, yönlendirme ve özel disk, SMTP teslimatı, kuyruk ve zamanlayıcı, izleme, şifreli üretim yedeğinden geri dönüş ve ilk hesap provası bekliyor. Hukuk kabulü işletmeci bilgileri, KVKK kanalları, işleme ve saklama koşulları ile yürürlükteki sözleşme ve politikaları kapsıyor. Ücretsiz ilk yayının dışındaki platform ücretlendirmesi ve Clinic AI Premium ertelenmiş ürün işleri.

### Son regresyon ve yayın kontrolü

| Denetim | 24 Kasım sonucu |
| --- | --- |
| SQLite | 1.151 test geçti, 7.234 doğrulama. Yalnız PostgreSQL’de çalışan üç yedekleme senaryosu atlandı. |
| PostgreSQL 17.12 | 1.154 test geçti, 7.257 doğrulama. Migration, yedek geri yükleme ve eşzamanlı yazma senaryoları dahil. |
| Ön yüz ve derleme | Vite üretim çıktısı, Blade ve rota önbellekleri, dört Node oturum testi ve mobil Chromium’da panelin gerçek işlem akışı geçti. |
| Sözdizimi ve güvenlik | 431 PHP ve 46 JS dosyasında sözdizimi denetimi geçti. 56 odak testinde 362 doğrulama. Semgrep 1.186.0 ile 352 hedefte ayrıştırma hatası 0. İncelenen 35 ham SQL ve bir HTML atamasında doğrulanmış açık görülmedi. |
| Bağımlılık ve sır taraması | npm danışma kayıtlarında 0 bulgu. Composer platform gereksinimleri geçti. Packagist güvenlik uç noktası bu ortamdan 403 döndürdü. GitHub Advisory Database, kilitli 133 Composer sürümünde 0 eşleşme gösterdi. Maskeli Gitleaks taraması Git geçmişi, izlenen kaynak, derlenmiş arayüz ve yerel uygulama günlüğünde 0 bulgu. |
| Yayın komutu | Yapılandırılmış sentetik ortamda çıkış kodu 0. Çalışma alanında çıkış kodu 1. Şema, oturum deposu, arayüz, özel disk ve kuyruk hazır. Üretim ayarları, HTTPS, güvenli çerez, API süresi, ilk doğrulanmış yönetici, yürürlükteki sözleşme, KVKK/politika yayını ve SMTP bekliyor. |

Bu uzlaştırmada açık kalan doğrulanmış bir kod hatası saptanmadı. Üç kod ve test commit’i ile uzlaştırma raporu dahil dal, gönderim öncesi GitHub `main` dalının 28 commit ilerisindeydi. 137 dosya değişmişti.

### Yerel commit ve dosya envanteri

Dört eklemeli migration destek atama geçmişini, sözleşme yayın denetimini, atanmış destek rolünü ve klinik üyelik işlem geçmişini içeriyor. Uzlaştırma aşamasındaki ilk 27 commit aşağıda. Bu envanteri kaydeden `33e9b4e` commit’i listeyi izler.

<details>
<summary>GitHub main üstündeki yerel commit’ler</summary>

```text
4045328 Build the Medloby platform administration dashboard
7575533 Extend platform administration with account, analytics and operations views
a081a63 Record platform administration verification and remaining decisions
de38f62 Centralize platform administration in one workspace
ecfcd44 Show recorded clinic application decisions in administration
8e597ea Add the platform support reply queue and ticket search
8467f94 Connect platform operations to observed activity and add audit history
2e87142 Assign support tickets to verified platform administrators
efd0075 Record contract publication actions and verify privacy contacts
e2e559d Document Medloby first launch policies and support role decision
fb96429 Add Medloby public policy pages and homepage navigation
cf9863c Enable local policy previews with fictional operator details
42e2659 Harden Medloby access, response security and technical SEO
7a4143e Strengthen Medloby release acceptance and private data safeguards
8ddf290 Protect queue failure diagnostics and offer notification payloads
c3fee00 Keep private storage behind authorized attachment routes
7a84897 Reject exposed backup destinations and broken SQLite relationships
485289a Validate queue failure storage during release checks
25e3f70 Discard browser responses from ended Medloby sessions
bb4c6bc Enforce platform catalog verification and defer consent draft endpoints
0f0f912 Add assigned-only platform support with audited access changes
e7593dd Serialize concurrent clinic finance and inventory operations on SQLite
a735254 Apply the Laravel authentication timebox to rejected logins
17b5572 Record master release acceptance and isolate regression fixtures
dc0086a Validate production session storage in release checks
be192f3 Audit clinic member role status and permission changes
3ae9f33 Verify support role migration on existing records
```

</details>

<details>
<summary>GitHub main ile karşılaştırılan bütün değişen dosyalar</summary>

```text
M	.env.example
M	.github/workflows/ci.yml
M	.gitignore
A	ADMIN-TAMAMLAMA.md
A	GUVENLIK-YAYIN-ENVANTERI.md
A	MEDLOBY-TAMAMLAMA-ENVANTERI.md
M	PROJE-YOL-HARITASI.md
M	README.md
M	app/Console/Commands/CheckReleaseReadiness.php
A	app/Console/PrivateQueueWorkCommand.php
M	app/Http/Controllers/AdminBusinessController.php
M	app/Http/Controllers/AdminBusinessMemberPermissionController.php
M	app/Http/Controllers/AdminBusinessMemberRoleController.php
M	app/Http/Controllers/AdminBusinessMemberStatusController.php
A	app/Http/Controllers/AdminDashboardController.php
A	app/Http/Controllers/AdminPatientAccountController.php
M	app/Http/Controllers/AdminPlatformContractController.php
A	app/Http/Controllers/AdminPlatformSupportRoleController.php
M	app/Http/Controllers/AuthController.php
M	app/Http/Controllers/ClinicBalanceController.php
M	app/Http/Controllers/ClinicConsentTemplateController.php
M	app/Http/Controllers/ClinicDirectoryController.php
M	app/Http/Controllers/ClinicInventoryController.php
M	app/Http/Controllers/ClinicLedgerController.php
M	app/Http/Controllers/ConversationController.php
A	app/Http/Controllers/PolicyPageController.php
M	app/Http/Controllers/PrivacyNoticeController.php
A	app/Http/Controllers/SearchIndexController.php
M	app/Http/Controllers/SupportTicketController.php
M	app/Http/Controllers/TreatmentCategoryController.php
M	app/Http/Controllers/TreatmentController.php
M	app/Http/Middleware/AdminMiddleware.php
A	app/Http/Middleware/ApplyResponseSecurity.php
M	app/Models/Branch.php
M	app/Models/Business.php
M	app/Models/PlatformContract.php
M	app/Models/SupportTicket.php
A	app/Models/SupportTicketAssignment.php
M	app/Models/TreatmentPackage.php
M	app/Models/User.php
M	app/Notifications/NewOfferNotification.php
M	app/Providers/AppServiceProvider.php
A	app/Services/AdminDashboardMetrics.php
M	app/Services/CatalogManagementAccess.php
A	app/Services/ClinicMembershipAudit.php
M	app/Services/LocalBackup.php
A	app/Services/PlatformAdminAudit.php
A	app/Services/PlatformOperationActivity.php
M	app/Services/PrivacyPublication.php
A	app/Services/PublicFileExposure.php
A	app/Services/PublicWebsite.php
A	app/Services/ReleaseConfiguration.php
M	bootstrap/app.php
M	config/cache.php
M	config/database.php
M	config/filesystems.php
M	config/medloby.php
M	config/sanctum.php
M	config/trustedproxy.php
A	database/migrations/2026_11_23_172955_create_support_ticket_assignments_table.php
A	database/migrations/2026_11_23_174126_add_publication_audit_to_platform_contracts_table.php
A	database/migrations/2026_11_23_210007_add_platform_support_role_and_changes.php
A	database/migrations/2026_11_23_213234_create_clinic_membership_changes_table.php
D	public/robots.txt
M	resources/contracts/medloby-veri-isleme-ve-saklama-taslagi.md
M	resources/js/app.js
M	resources/js/clinic-administration.js
M	resources/js/clinic-records.js
M	resources/js/clinic-review.js
M	resources/js/package-conversation.js
M	resources/js/package-offers.js
A	resources/js/platform-admin-patients.js
A	resources/js/platform-admin.js
M	resources/js/support-tickets.js
A	resources/js/workspace-api.js
M	resources/js/workspace-notifications.js
M	resources/views/clinic-administration-dialog.blade.php
M	resources/views/clinic-directory/show.blade.php
M	resources/views/clinic-review-dialog.blade.php
M	resources/views/package-offers.blade.php
A	resources/views/platform-admin-analytics.blade.php
A	resources/views/platform-admin-audit.blade.php
A	resources/views/platform-admin-operations.blade.php
A	resources/views/platform-admin-patients-dialog.blade.php
A	resources/views/platform-admin-workspace.blade.php
A	resources/views/platform-admin.blade.php
A	resources/views/platform-support-workspace.blade.php
A	resources/views/policies/pages/cerez-politikasi.blade.php
A	resources/views/policies/pages/finans-politikasi.blade.php
A	resources/views/policies/pages/gizlilik-bildirimi.blade.php
A	resources/views/policies/pages/hasta-haklari.blade.php
A	resources/views/policies/pages/hizmet-sartlari.blade.php
A	resources/views/policies/pages/inceleme-ve-yorum-politikasi.blade.php
A	resources/views/policies/pages/kullanim-sartlari-ve-kosullari.blade.php
A	resources/views/policies/pages/kvkk.blade.php
A	resources/views/policies/pages/odeme-ve-depozito-sartlari.blade.php
A	resources/views/policies/pages/tibbi-yasal-uyari.blade.php
A	resources/views/policies/pages/yayin-politikasi.blade.php
A	resources/views/policies/show.blade.php
M	resources/views/privacy/clinic.blade.php
M	resources/views/privacy/footer-links.blade.php
M	resources/views/privacy/notice.blade.php
M	resources/views/privacy/rights.blade.php
M	resources/views/support-tickets-dialog.blade.php
M	resources/views/welcome.blade.php
M	resources/views/workspace-registration.blade.php
M	routes/api.php
M	routes/console.php
M	routes/web.php
M	tests/Feature/AdminBusinessTest.php
A	tests/Feature/AdminDashboardTest.php
A	tests/Feature/AdminPatientAccountTest.php
M	tests/Feature/AdminPlatformContractTest.php
M	tests/Feature/AppointmentConcurrencyTest.php
M	tests/Feature/AuthControllerTest.php
M	tests/Feature/CatalogManagementAccessTest.php
M	tests/Feature/CatalogVisibilityTest.php
M	tests/Feature/ClinicConsentTemplateTest.php
M	tests/Feature/ClinicConsentTemplateVersionTest.php
M	tests/Feature/ClinicFinanceReportTest.php
A	tests/Feature/ClinicOperationsConcurrencyTest.php
M	tests/Feature/ConversationTest.php
M	tests/Feature/LaunchWorkflowTest.php
M	tests/Feature/LocalBackupTest.php
M	tests/Feature/MessageAttachmentTest.php
M	tests/Feature/OfferCreationTest.php
M	tests/Feature/PatientRegistrationTest.php
A	tests/Feature/PlatformSupportAccessTest.php
A	tests/Feature/PlatformSupportMigrationTest.php
A	tests/Feature/PolicyPagesTest.php
A	tests/Feature/QueueFailurePrivacyTest.php
A	tests/Feature/ReleaseHardeningTest.php
M	tests/Feature/ReleaseReadinessTest.php
M	tests/Feature/ReminderScheduleTest.php
M	tests/Feature/SupportTicketTest.php
M	tests/TestCase.php
A	tests/workspace-api.test.js
```

</details>

## 12. GitHub teslimi ve Actions kabulü

24 Kasım 2026 teslim kapsamı, `2991bb17a6c772a19459de864067d44d20459572` üzerindeki `33e9b4e1cbd831092021a84a6404e4ce1480932c` uzlaştırmasını ve güncel dokümantasyonu kapsar. Önceki uzlaştırma 28 commit ve 137 değişen dosya içeriyordu. [Canlı GitHub ana dalı](https://github.com/medloby/medloby/commits/main/)

Hosted CI kabulü için `main` üzerindeki teslim SHA’sına ait **Medloby CI** çalışmasında SQLite, PostgreSQL 17, ön yüz derlemesi, Node testleri ve güvenlik adımları başarılı olmalıdır. GitHub çalışma kaydı commit kimliğini, iş adımlarını ve sonucu birlikte gösterir. [Main dalı için Medloby CI](https://github.com/medloby/medloby/actions/workflows/ci.yml?query=branch%3Amain)

İlk teslimin 96 numaralı çalışması test ortamı hazırlanırken durdu. Temiz kurulumda boş `CACHE_LIMITER` değeri, Laravel’in adı boş bir önbellek deposu aramasına ve Artisan’ın açılmadan durmasına yol açtı. Boş değer uygulamanın varsayılan deposuna yönlendirildi. Bu davranış ayrı bir Artisan süreciyle sınanıyor. [İlk çalışma](https://github.com/medloby/medloby/actions/runs/45276241232) · [Regresyon testi](tests/Feature/ReleaseHardeningTest.php)

Composer ve npm denetimleri ayrı GitHub Actions adımlarında izleniyor. 98 numaralı çalışmada Composer beş Symfony bileşeninde sekiz güvenlik duyurusu buldu. Resmî Packagist paket kayıtları eşleşmeleri doğruladı. `composer.lock` içinde HttpFoundation, HttpKernel, Routing ve Yaml 8.1.11’e, Mime 8.1.10’a güncellendi. [Güvenlik denetimi çalışması](https://github.com/medloby/medloby/actions/runs/45278158307)

| Kapsam | Durum |
| --- | --- |
| Üretim kabulü | `PRODUCTION_ACCEPTANCE_PENDING`. Yetkili deneme ortamı, DNS/TLS, gerçek e-posta, üretim yedek geri dönüşü ve işletim onayları bekliyor. |
| Hukuk ve gizlilik | `LEGAL_APPROVAL_REQUIRED`. İşletmeci, KVKK kanalları, veri saklama ve yayımlanacak metinler doğrulanacak. |
| Hostinger satın alma ve kurulum | `DEFERRED BY OWNER`. |
| Clinic AI Premium | `DEFERRED`. |

## 13. Toplu yayın hazırlığı ve kabul

24 Kasım 2026’da yayın listesi, mevcut kod ve önceki prova sonuçları birlikte karşılaştırıldı. Yayın kontrolündeki iki eksik giderildi. İstek sınırlandırma deposunun şeması doğrulanıyor. Zamanlayıcı ve işletim göstergeleri için kalıcı önbellek ile kilit tablosu kontrol ediliyor. [Yayın kontrolü](app/Console/Commands/CheckReleaseReadiness.php) · [Dört yeni test senaryosu](tests/Feature/ReleaseReadinessTest.php)

| Yayın konusu | Yerel kabul sonucu |
| --- | --- |
| Temiz kurulum | Kilitli üretim PHP bağımlılıkları, temiz Vite derlemesi, PostgreSQL 17 migration’ları, Laravel önbellekleri ve HTTPS çalıştı. Sentetik hazır yapılandırma yayın kontrolünden geçti. |
| Hesap ve e-posta | Önceki klinik kabul ve test SMTP kuyruk kurtarma provalarına şifre yenileme eklendi. HTTPS formu, test SMTP teslimatı, tek kullanımlık bağlantı ve önceki oturum iptali doğrulandı. [E-posta ve kuyruk provası](GUVENLIK-YAYIN-ENVANTERI.md#24-kasım-2026-smtp-ve-kuyruk-kurtarma-provası) |
| Dosya ve kurtarma | Önceki 10 MB sınırı, yetkili erişim, ayrı PostgreSQL veritabanına geri dönüş ve eski oturumları kapatma provası kayda alındı. [Dosya ve geri yükleme kanıtı](GUVENLIK-YAYIN-ENVANTERI.md#24-kasım-2026-özel-dosya-ve-geri-yükleme-provası) |
| Klinik ve hasta işlemleri | Önceki hatırlatma, bekleme listesi, teklif süresi, iki seanslı paket, aynı saat yarışı ve veritabanı kesintisi sonuçları geçerli. [Zamanlayıcı provası](GUVENLIK-YAYIN-ENVANTERI.md#24-kasım-2026-zamanlayıcı-ve-bekleme-listesi-provası) · [Randevu ve sağlık kontrolü](GUVENLIK-YAYIN-ENVANTERI.md#24-kasım-2026-paket-randevusu-ve-sağlık-kontrolü-provası) |
| Politika ve SEO | Yerel HTTPS’te indeksleme kapalıyken `robots.txt`, taslak politikalar ve kapalı KVKK aydınlatması doğrulandı. Sentetik yayın koşulunda HTTPS canonical URL’ler ve site haritası sınandı. Askıdaki klinik ve yönetim alanı haritaya girmedi. |
| Son regresyon | SQLite 1.156 başarılı test ve 7.250 doğrulama. PostgreSQL 17, üç ek yedekleme senaryosuyla 1.159 başarılı test ve 7.273 doğrulama. Dört Node testi, Vite, PHP ve JS sözdizimi geçti. npm ve 133 kilitli Composer sürümünde güvenlik duyurusu eşleşmesi bulunmadı. Gitleaks geçmiş, kaynak ve derlemede bulgu üretmedi. |

| Canlı kabul için gereken girdi | Devam eden iş |
| --- | --- |
| Yetkili sunucu ve alan adı | DNS/TLS, gerçek web kökü ve vekil başlıkları, CDN ve sıkıştırma, canlı DB/özel disk, ilk yönetici, yedek şifreleme ve ayrı hedefe geri yükleme. |
| Gerçek SMTP ve işletim | Yetkili gönderen, alıcıya gerçek teslimat, çalışan kuyruk ve dakika tetiklemesi, izleme ve olay alıcıları. |
| Hukuk ve işletmeci bilgileri | Resmî bilgiler, KVKK kanalları, veri işleme ve saklama, yayımlanacak sözleşmeler, klinik ve içerik onayı. |
| Yönetici ve yayın kararları | MFA ve hesap geçişi, genişletilmiş rol matrisi, izleme sahipliği ve seçilirse arama konsolu veya ölçüm ayarları. |

Canlı kabul `PRODUCTION_ACCEPTANCE_PENDING`. GitHub Actions #99, önceki GitHub teslim sürümü `5258e5a` için başarılı. Bu toplu çalışmanın uygulama değişiklikleri ve kanıtları yerel olarak doğrulandı. [GitHub Actions](https://github.com/medloby/medloby/actions/runs/45278960253)
