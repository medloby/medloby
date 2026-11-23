# Medloby Admin Completion

## ADMIN-G0 — Mevcut depo incelemesi

İnceleme `2991bb17a6c772a19459de864067d44d20459572` sürümünde yapıldı. `git fetch origin` sonrasında `origin/main` aynı commit’i gösterdi. Çalışma ağacı başlangıçta temizdi.

**Sonuç** COMPLETE. Başlangıç regresyonunda SQLite 1.071 ve PostgreSQL 17 1.074 test geçti. SQLite aşamasında üç PostgreSQL yedekleme testi atlandı ve PostgreSQL aşamasında çalıştı. Bu grup için değişiklik envanterle sınırlı. Yayın kabulü canlı kurulumda yapılacak.

| Alan | İlk durum | Kod kanıtı ve açık iş |
| --- | --- | --- |
| Yönetim merkezi | NOT IMPLEMENTED | Eski yönetim diyalogları Paket ve Teklifler çalışma alanında açılıyor. Bağımsız yönetim rotası ve platform metrikleri eksik. [Web rotaları](routes/web.php) · [Mevcut çalışma alanı](resources/views/package-offers.blade.php) |
| Klinik başvurusu, onay ve ret | COMPLETE | Sözleşme kabulü, geçiş kuralları, başvuru kararı ve bildirim aynı akışta. [Denetleyici](app/Http/Controllers/AdminBusinessController.php) · [Test](tests/Feature/AdminBusinessTest.php) |
| Klinik dizini ve işletme üyelikleri | PARTIAL | Ad ve durum filtresi, plan etiketi, şube ve üye ayrıntıları, askıya alma ve yeniden açma hazır. Destek bağlantısı ve genişletilmiş inceleme ölçütleri açık. [Yönetim arayüzü](resources/js/clinic-administration.js) · [Durum geçmişi](app/Http/Controllers/AdminBusinessStatusHistoryController.php) · [Üye testi](tests/Feature/AdminBusinessMembershipDetailTest.php) |
| Hasta hesabı yönetimi | NOT IMPLEMENTED | Hasta hesabı ve ayrı profil modeli mevcut. Platform düzeyinde sınırlı liste, filtre ve destek bağlantısı açık. [Hasta modeli](app/Models/PatientProfile.php) |
| Platform yetkileri ve denetim | PARTIAL | Doğrulanmış yönetici, mevcut `is_admin` işareti ve Sanctum kullanılıyor. Klinik üye yetkileri ayrı ve şube kapsamlı. Başvuru ve klinik durum geçmişi mevcut. Platform rol matrisi, atama denetimi ve ortak olay kapsamı açık. [Yetki sınırı](app/Http/Middleware/AdminMiddleware.php) · [Klinik üyeliği](app/Models/BusinessUser.php) · [Yönetici testleri](tests/Feature/AdminMiddlewareTest.php) |
| Ücretsiz üyelik planları | PARTIAL | Üç isteğe bağlı plan mevcut. Başvuru ve klinik listesi seçimi gösteriyor. Platform dağılımı ve değişiklik geçmişi açık. [Plan tanımları](app/Models/Business.php) · [Başvuru testi](tests/Feature/ClinicAccountOnboardingTest.php) |
| Platform finansı | PRODUCT DECISION REQUIRED | Şube gelir ve gider defteri kliniğe ait. Platform tahsilatı, fatura ve iade sistemi için ürün ve mevzuat kararları açık. [Klinik defteri](app/Models/BranchLedgerEntry.php) |
| Platform analitiği | NOT IMPLEMENTED | Klinik raporları klinik ve şube kapsamlı. Platform genelinde kimliksiz randevu, teklif, dönüşüm ve büyüme ölçümleri açık. [Klinik yönetim özeti](app/Http/Controllers/ClinicManagementController.php) |
| Destek | PARTIAL | Kendi taleplerini görme, yönetici kuyruğu, yanıt, kapatma ve yeniden açma hazır. Kategori, öncelik, atama ve yanıt süreleri açık. [Akış](app/Http/Controllers/SupportTicketController.php) · [Test](tests/Feature/SupportTicketTest.php) |
| Katalog | PARTIAL | Tedavi, kategori ve ortak ek hizmet yönetimi mevcut. İçerik ve katalog bütünlüğü uyarıları açık. [Tedavi yönetimi](resources/js/treatment-administration.js) · [Ek hizmet yönetimi](resources/js/package-service-administration.js) |
| Sözleşme ve KVKK | PENDING PRODUCTION ACCEPTANCE | Sürüm, yayın, yürürlük, yayından kaldırma ve kabul geçmişi hazır. Canlı işletmeci bilgileri ve onay gereken metinler yayın listesinde. [Sözleşme testi](tests/Feature/AdminPlatformContractTest.php) · [Yayın kontrolü](README.md#medloby-yayına-çıkmadan-önce) |
| Sistem ve operasyon | PARTIAL | `/up`, yayın kontrolü, ilk yönetici kurulumu, zamanlayıcı ve yedekleme mevcut. İşçi ve zamanlayıcı çalıştığına dair kalıcı canlı ölçüm ile yönetim ekranı açık. [Sağlık testi](tests/Feature/ApplicationHealthTest.php) · [Yayın kontrolü](app/Console/Commands/CheckReleaseReadiness.php) · [Yedekleme](app/Services/LocalBackup.php) |
| Clinic AI Premium | DEFERRED | [Proje yol haritası](PROJE-YOL-HARITASI.md#6-yeni-modüller) |

Mimari karar, platform yönetimini ayrı sayfada açmak ve mevcut yönetim diyaloglarıyla API işlemlerini kullanmak. Yeni özet uçları veritabanından sayım alır ve platform yöneticisiyle sınırlandırılır. Doğrudan kimlik bilgisi, klinik finansı, sağlık kaydı ve kullanıcı yazışması özet veri modeline girmez.

## ADMIN-G1 — Yönetim merkezi

| Teslim alanı | Doğrulanan sonuç |
| --- | --- |
| GROUP ve GIT HEAD | ADMIN-G1. Başlangıç `2991bb1`. Başlangıç çalışma ağacı temizdi. |
| INITIAL STATE, CONFIRMED GAPS ve ROOT CAUSE | Yönetim işlemleri Paket ve Teklifler sayfasındaki diyaloglarda bulunuyordu. Bağımsız giriş, platform özeti ve toplam grafikler eksikti. |
| IMPLEMENTED ve CHANGED FILES | `/yonetim` sayfası, toplu platform ölçümleri, 30 günlük grafikler ve işlem uyarıları eklendi. Mevcut klinik, katalog, destek ve sözleşme diyalogları açılıyor. Aynı istek işleyicisi iki sayfada kullanılıyor. [Yönetim sayfası](resources/views/platform-admin.blade.php) · [Panel](resources/views/platform-admin-workspace.blade.php) · [Arayüz](resources/js/platform-admin.js) · [Ölçümler](app/Services/AdminDashboardMetrics.php) |
| AUTHORIZATION, DATA PRIVACY ve API COMPATIBILITY | Panel ve sayım uçları Sanctum ile doğrulanmış platform yöneticisine açık. Oturum belirteci tarayıcı belleğinde kalır. API özeti sayım, durum etiketi ve tarih döndürür. Mevcut uçlar ve davranışları korunur. |
| MIGRATIONS | Şema değişikliği ve yeni paket kurulumu gerekmiyor. |
| FOCUSED TEST ve INTEGRATION TEST | Kimlik, rol, Türkiye saati sınırı, süre dolumu, veri gizliliği ve boş durumlar test edildi. Klinik, sözleşme, destek ve ilk kurulumla birlikte 124 ilgili test geçti. |
| FULL REGRESSION ve FRONTEND BUILD | SQLite 1.075, PostgreSQL 17 1.078 test geçti. Üç yedekleme testi PostgreSQL üzerinde çalıştı. Vite ve Blade derlemeleri geçti. Chromium’da masaüstü ve mobil giriş, altı yönetim diyalogu, grafik tablosu ve yetkisiz giriş doğrulandı. [Yönetim testleri](tests/Feature/AdminDashboardTest.php) |
| RESULT ve REMAINING RISKS | COMPLETE. Canlı ortam kabulü ve genişletilmiş rol yetkileri sonraki aşamada. |
| COMMIT, PUSH STATUS ve NEXT GROUP | Yerel `4045328`. GitHub gönderimi onay bekliyor. Sonraki güvenli işler klinik ve hasta yönetimiyle devam ediyor. |

## ADMIN-G2 — Yönetici rolleri

Durum **BLOCKED**. Kritik yetki geçişi onay gerektiriyor. Platform rolleri işletme üyeliği rollerinden bağımsız olacak. Yetkilendirme uçta yapılacak, yeni rollere açık izin dışındaki işlemler kapalı olacak. Mevcut ilk yönetici kurulumu korunacak. Geçiş planı mevcut yöneticilerin eşlenmesini, son üst yöneticiyi koruyan işlem kilidini ve atama/geri alma denetimini içerecek.

## ADMIN-G3 — Klinik ve hasta hesabı yönetimi

**PARTIAL.** Klinik dizinine il ve ücretsiz üyelik planı filtreleri ile kayıtlı şube sayıları eklendi. Yeni hasta hesabı ekranında hesap numarası ve adı, profil durumu, e-posta doğrulaması ve kayıt tarihi görünür. E-posta ile tam eşleşme üzerinden arama yapılabilir. Liste e-posta adresi, telefon ve sağlık alanlarını döndürmez. Hesaba bağlı destek geçmişi mevcut destek ekranında açılır. Klinik ruhsatı ve doğrulama ölçütleri ile işletme bazlı destek ilişkilendirmesi için ürün kararı açık. [Klinik yönetimi](app/Http/Controllers/AdminBusinessController.php) · [Hasta hesabı](app/Http/Controllers/AdminPatientAccountController.php) · [Gizlilik ve erişim testleri](tests/Feature/AdminPatientAccountTest.php)

## ADMIN-G4 — Üyelik planları

**PARTIAL.** Mevcut üç ücretsiz ve isteğe bağlı planın dağılımı ve klinik filtreleri modeldeki plan tanımlarını kullanır. Klinik plan seçimi klinik ve başvuru yönetiminde görülebilir. Planları değiştirme yetkisi ve bundan doğacak değişiklik geçmişi tanımlanmayı bekler. [Plan tanımları](app/Models/Business.php) · [Yönetim özeti testi](tests/Feature/AdminDashboardTest.php)

## ADMIN-G5 — Platform analitiği ve finans

**PARTIAL.** Panelde son 30 günde oluşturulan tekliflerin kayıtlı sonuçları, Türkiye takvimine göre randevular, randevusu bulunan klinik sayısı ve il dağılımı gösterilir. Bir kabul edilmiş teklifin aynı işletme ve hastaya ait birden fazla seansı tek dönüşüm sayılır. Teklif döneminde oluşturulma tarihi, randevu döneminde başlangıç tarihi kullanılır. [Analitik sorguları](app/Services/AdminDashboardMetrics.php) · [Dönüşüm testi](tests/Feature/AdminDashboardTest.php)

Platform finansı **BLOCKED**. İleride finansal hareketler platformun kendi ödeme veya fatura kaynağına bağlanmalı. İşlem kaydı sağlayıcıdan gelen doğrulanabilir bir referans, tarih, tutar, para birimi ve işlem türü taşımalı. Şube gelir defteri klinik kapsamındadır. Uygulama kaynakları fiyatlandırma, faturalama ve hesaplama sözleşmesi onaylandığında bağlanacak. [Klinik defteri](app/Models/BranchLedgerEntry.php)

## ADMIN-G6 — Destek, katalog ve uyum

**PARTIAL.** Mevcut destek kuyruğunda hesap bağlantısı, ilk yanıt ve son kapanışa kadar geçen süre gösterilir. Süreler talep ve mesaj zamanlarından hesaplanır. Panel aktif tedavisi olan pasif kategorileri ve yayımdaki zorunlu klinik sözleşmesinin bitiş tarihini bildirir. İşlemler mevcut katalog ve sözleşme ekranlarında yapılır. Destek kategorileri, öncelikleri, atama ve denetim süreleri açık. Hukuki içerik onayı yayımdan önce yapılacak. [Destek](app/Http/Controllers/SupportTicketController.php) · [Panel uyarıları](app/Services/AdminDashboardMetrics.php)

## ADMIN-G7 — Sistem durumu

**PARTIAL.** Sistem ekranı uygulama erişimini, veritabanı ve şema durumunu, kuyruk ve e-posta yapılandırmasını ve varsa belirtilen yayın sürümünü gösterir. Yayın komutuyla aynı teknik denetimleri kullanır. İşçi, zamanlayıcı, teslimat ve son yedek doğrulaması için canlı ölçüm bulunmadığında **Bilinmiyor** gösterilir. [Sistem ölçümleri](app/Services/ReleaseConfiguration.php) · [Yayın kontrolü](app/Console/Commands/CheckReleaseReadiness.php)

## ADMIN-G8 — Kabul

Yerel kabul sonuçları son test çalıştırmasından sonra kaydedilir. Canlı ortam kabulü ve GitHub gönderimi kullanıcı onayı bekliyor.

## PRODUCT_DECISIONS_REQUIRED

- Platform rol matrisi, kritik yetki atama ve geri alma, son üst yöneticinin korunması ve geçiş onayı.
- Abonelik fiyatı ve para birimi, ödeme sağlayıcısı, komisyon, iade, vergi, fatura türü ve zorunlu ücretli plan kararı.
- Sağlık verisinin işlenmesi, KVKK kabulü, kayıt saklama ve imha süreleri, hesap durdurma, silme ve dışa aktarma yetkisi.
- İlk klinik ruhsat doğrulamasında aranacak belgeler ve destek talebinin işletmeyle nasıl ilişkilendirileceği.
- Destek önceliği, atama, SLA süresi, otomatik kapanış ve denetim saklama politikası.
