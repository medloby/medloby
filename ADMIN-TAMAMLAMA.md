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

Platform yönetimi tek ekran olarak `/yonetim` adresinde açılır. Üyelik sözleşmesi, klinik başvurusu, klinik ve ortak katalog yönetimi ile yönetici destek kuyruğu bu adreste toplanır. Paket ve Teklifler sayfasındaki yönetici bağlantısı ile başvuru ve destek bildirimleri aynı adrese gider. Hasta ve klinik hesabının kendi destek talepleri Paket ve Teklifler sayfasından açılabilir. [Tek paneli doğrulayan test](tests/Feature/AdminDashboardTest.php)

## ADMIN-G2 — Yönetici rolleri

Kritik yetki geçişi onay bekliyor. Önerilen görev ayrımında tam yönetici platform işlemlerini kullanır. Destek görevlisi kendisine atanan talepleri görür ve yanıtlar. Atama, klinik hesabı, sözleşme ve finans işlemleri tam yöneticiye açık kalır. Onaylanan görevler API düzeyinde ve klinik üyeliği rollerinden bağımsız uygulanacak. Geçiş planı mevcut yöneticileri eşler, son tam yöneticiyi korur ve yetki değişikliklerini işlem geçmişine kaydeder.

**İşlem geçmişi** başvuru kararlarını, klinik durum değişikliklerini, sözleşme yayımlama ve yayından kaldırma işlemlerini, destek yanıtlarını ve atamaları tek listede gösterir. Sözleşmeyi yayımlayan ve yayından kaldıran yöneticiler ilgili işlem anında mevcut sözleşme kaydına yazılır. Eski yayımlama kayıtlarında tarih korunur ve işlemi yapan hesap kayıtlı değilse ekranda bu bilgi gösterilir. Ayrıntılı notlar ve mesajlar kendi yetkili ekranlarında kalır. Rol ve izin değişikliklerinin ortak denetimi yetki geçişiyle planlanır. [İşlem geçmişi sorgusu](app/Services/PlatformAdminAudit.php)

## ADMIN-G3 — Klinik ve hasta hesabı yönetimi

**PARTIAL.** Klinik dizinine il ve ücretsiz üyelik planı filtreleri ile kayıtlı şube sayıları eklendi. Yeni hasta hesabı ekranında hesap numarası ve adı, profil durumu, e-posta doğrulaması ve kayıt tarihi görünür. E-posta ile tam eşleşme üzerinden arama yapılabilir. Liste e-posta adresi, telefon ve sağlık alanlarını döndürmez. Hesaba bağlı destek geçmişi mevcut destek ekranında açılır. Klinik ruhsatı ve doğrulama ölçütleri ile işletme bazlı destek ilişkilendirmesi için ürün kararı açık. [Klinik yönetimi](app/Http/Controllers/AdminBusinessController.php) · [Hasta hesabı](app/Http/Controllers/AdminPatientAccountController.php) · [Gizlilik ve erişim testleri](tests/Feature/AdminPatientAccountTest.php)

Klinik ayrıntısından **Başvuru geçmişi** ile mevcut başvuru ekranı açılır. Klinik ve şubelerin güncel durumları, kayıtlı karar, karar tarihi, inceleyen yönetici ve varsa gerekçeler gösterilir. Kararlar tarih ve kayıt sırasıyla listelenir. Tarihi olmayan eski kararlar sonda görünür. [Başvuru ekranı](resources/views/clinic-review-dialog.blade.php) · [Başvuru testleri](tests/Feature/AdminBusinessTest.php)

## ADMIN-G4 — Üyelik planları

**PARTIAL.** Mevcut üç ücretsiz ve isteğe bağlı planın dağılımı ve klinik filtreleri modeldeki plan tanımlarını kullanır. Klinik plan seçimi klinik ve başvuru yönetiminde görülebilir. Planları değiştirme yetkisi ve bundan doğacak değişiklik geçmişi tanımlanmayı bekler. [Plan tanımları](app/Models/Business.php) · [Yönetim özeti testi](tests/Feature/AdminDashboardTest.php)

## ADMIN-G5 — Platform analitiği ve finans

**PARTIAL.** Panelde son 30 günde oluşturulan tekliflerin kayıtlı sonuçları, Türkiye takvimine göre randevular, randevusu bulunan klinik sayısı ve il dağılımı gösterilir. Bir kabul edilmiş teklifin aynı işletme ve hastaya ait birden fazla seansı tek dönüşüm sayılır. Teklif döneminde oluşturulma tarihi, randevu döneminde başlangıç tarihi kullanılır. [Analitik sorguları](app/Services/AdminDashboardMetrics.php) · [Dönüşüm testi](tests/Feature/AdminDashboardTest.php)

Platform finansı **BLOCKED**. İleride finansal hareketler platformun kendi ödeme veya fatura kaynağına bağlanmalı. İşlem kaydı sağlayıcıdan gelen doğrulanabilir bir referans, tarih, tutar, para birimi ve işlem türü taşımalı. Şube gelir defteri klinik kapsamındadır. Uygulama kaynakları fiyatlandırma, faturalama ve hesaplama sözleşmesi onaylandığında bağlanacak. [Klinik defteri](app/Models/BranchLedgerEntry.php)

## ADMIN-G6 — Destek, katalog ve uyum

Mevcut destek kuyruğunda hesap bağlantısı, ilk yanıt ve son kapanışa kadar geçen süre gösterilir. Süreler talep ve mesaj zamanlarından hesaplanır. Panel aktif tedavisi olan pasif kategorileri ve yayımdaki zorunlu klinik sözleşmesinin bitiş tarihini bildirir. İşlemler mevcut katalog ve sözleşme ekranlarında yapılır. [Destek](app/Http/Controllers/SupportTicketController.php) · [Panel uyarıları](app/Services/AdminDashboardMetrics.php)

**Yanıt bekliyor** filtresi açık ve son mesajı başvurandan gelen talepleri gösterir. Genel bakıştaki sayaç aynı kuyruğu açar. Yönetici talep numarası veya konuyla arayabilir. Hesaba bağlı destek geçmişinde arama ilgili hesabın kayıtlarıyla sınırlıdır. [Kuyruk testleri](tests/Feature/SupportTicketTest.php)

Doğrulanmış platform yöneticisi açık bir talebe doğrulanmış yönetici atayabilir ve atamayı kaldırabilir. **Bana atananlar** ve **Sorumlusuz** filtreleri durum ve arama filtreleriyle birlikte çalışır. Sorumlunun platform erişimi kaldırıldığında talep tekrar sorumlusuz kuyruğunda görünür. Atanan diğer yönetici uygulama içi bildirim alır. Atamalar mevcut işlem geçmişine girer ve yalnızca yönetim ekranında görünür. [Atama ve yetki testleri](tests/Feature/SupportTicketTest.php)

## ADMIN-G7 — Sistem durumu

**PENDING PRODUCTION ACCEPTANCE.** Sistem ekranı uygulama erişimini, veritabanı ve şema durumunu, kuyruk ve e-posta yapılandırmasını ve varsa belirtilen yayın sürümünü gösterir. Yayın komutuyla aynı teknik denetimleri kullanır. Kuyruk işçisinin tamamladığı son işi, son başarılı zamanlanmış görevi, SMTP’nin kabul ettiği son gönderimi ve doğrulanmış son yedek oluşturma işlemini paylaşılan veritabanı önbelleğinden gösterir. Önbellekte ölçüm bulunmadığında **Bilinmiyor** görünür. Sunucu kabulünden sonraki gelen kutusu teslimatı ve geri yükleme canlı yayın provasında sınanır. Veritabanı kesintisi yönetici oturumunun doğrulanmasını da engelliyorsa dış erişim kontrolü `/up` üzerinden yapılır. [Sistem ölçümleri](app/Services/PlatformOperationActivity.php) · [Yayın kontrolü](app/Console/Commands/CheckReleaseReadiness.php)

Yerel provada veritabanı kuyruğundaki hasta doğrulama e-postası test SMTP alıcısına ulaştı. E-postadaki imzalı bağlantı hesabı doğruladı ve yönetim ekranındaki kuyruk ve SMTP ölçümleri güncellendi. SQLite ve PostgreSQL 17 yedekleri özel dosyalarıyla ayrı test ortamına geri yüklendi. [SQLite geri yükleme testi](tests/Feature/LocalBackupTest.php) · [PostgreSQL geri yükleme testi](tests/Feature/PostgresBackupTest.php) · [Canlı yayın provası](README.md#medloby-yayına-çıkmadan-önce)

**Sistem durumu** KVKK aydınlatma yayını ayarı ile iletişim adreslerinin teknik durumunu gösterir. Yayın komutu da destek ve editör adreslerini ve girilmiş KVKK e-posta ve KEP adreslerini aynı kuralla doğrular. Yazılı başvuru kanalı işletmecinin posta adresidir. [Yayın kontrolü testleri](tests/Feature/ReleaseReadinessTest.php)

## ADMIN-G8 — Kabul

| Teslim alanı | Doğrulanan sonuç |
| --- | --- |
| GROUP, GIT HEAD ve WORKTREE STATUS | ADMIN-G8. Test edilen kod `7575533`. Yerel özellik commit’leri çalışma ağacına kaydedildi. |
| INITIAL STATE, CONFIRMED GAPS ve ROOT CAUSE | Yönetim merkezi ve yeni salt okunur görünümler eklendi. Canlı ölçüm kaynakları ve kritik veri politikaları henüz tanımlı değil. |
| IMPLEMENTED, CHANGED FILES, NEW FILES ve MIGRATIONS | Yerel kabul, yönetim paneli ve genişletmeler üzerinde çalıştırıldı. Dosyalar ilgili grup kayıtlarına bağlı. Şema değişikliği yok. |
| AUTHORIZATION, DATA PRIVACY ve API COMPATIBILITY | Doğrulanmamış, hasta ve klinik personeli hesapları yeni yönetim uçlarından reddedilir. Yönetici işareti kaldırılan hesabın eski oturumuyla yeni sayım isteği reddedilir. Hasta hesap listesi ve özetler sınırlı alan döndürür. Mevcut destek, klinik ve sözleşme uçları regresyonda geçti. |
| FOCUSED TEST ve INTEGRATION TEST | Son odak grubunda 49 test ve 369 doğrulama geçti. Chromium’da masaüstü ve mobil panel, mevcut yönetim diyalogları, hastaya göre destek filtresi, grafikler, analitik ve sistem durumu doğrulandı. |
| FULL REGRESSION ve FRONTEND BUILD | SQLite **1.080** test ve 6.537 doğrulama. PostgreSQL 17 **1.083** test ve 6.562 doğrulama. SQLite’ta atlanan üç PostgreSQL yedekleme testi PostgreSQL’de geçti. Vite derlemesi, Blade ve rota önbelleği hazırlandı. |
| RESULT, REMAINING RISKS ve DECISIONS REQUIRED | Yerel kabul **COMPLETE**. Canlı kabul **PENDING PRODUCTION ACCEPTANCE**. Rol geçişi, platform finansı ve hukuki politikalar aşağıda listelenmiştir. |
| COMMIT, PUSH STATUS ve NEXT GROUP | Özellik commit’leri `4045328` ve `7575533`. Son doğrulanan `origin/main` `2991bb1`. Gönderim onayı ve ürün kararları bekleniyor. |

Destek ataması eklendikten sonra SQLite’ta **1.087**, PostgreSQL 17’de **1.090** test geçti. SQLite’ta atlanan üç PostgreSQL yedekleme testi PostgreSQL’de çalıştı. Vite ve Blade derlendi. Atama, kuyruk filtreleri ve hasta erişim sınırı masaüstü ve mobil Chromium’da doğrulandı.

Sözleşme işlem geçmişi ve iletişim kontrollerinden sonra SQLite’ta **1.091**, PostgreSQL 17’de **1.094** test geçti. Nullable denetim alanlarını ekleyen migration iki veritabanında sınandı. Vite ve Blade derlendi. İşlem geçmişi ve iletişim durumu masaüstü ve mobil Chromium’da doğrulandı.

## PRODUCT_DECISIONS_REQUIRED

- Platform rol matrisi, kritik yetki atama ve geri alma, son üst yöneticinin korunması ve geçiş onayı.
- İlk yayın için seçilebilen üç klinik planı ücretsizdir. Ücretli abonelik kararı alındığında fiyat, para birimi, ödeme sağlayıcısı, komisyon, iade, vergi ve fatura türü ayrıca belirlenecek.
- Sağlık verisinin işlenmesi, KVKK kabulü, kayıt saklama ve imha süreleri, hesap durdurma, silme ve dışa aktarma yetkisi.
- İlk klinik ruhsat doğrulamasında aranacak belgeler ve destek talebinin işletmeyle nasıl ilişkilendirileceği.
- Destek kategorileri ve öncelikleri, otomatik atama, hedef yanıt süresi, otomatik kapanış ve işlem geçmişinin saklama süresi.
