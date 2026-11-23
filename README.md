<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Medloby proje yol haritası

Uygulama mimarisi, tamamlanan işler, eksikler ve geliştirme sırası proje yol haritasında tutulur. [Proje yol haritası](PROJE-YOL-HARITASI.md)

## Kurulum gereksinimleri

Medloby ve kilitli PHP paketleri için en düşük sürüm PHP 8.4.1’dir. GitHub Actions, PHP 8.4 serisinin güncel sürümü ve Node.js 24 ile derleme ve test yapar.

## Medloby yayına çıkmadan önce

- [ ] **İşletmeci ve iletişim bilgilerini doğrula.** Resmî unvanı ve tebligata esas posta adresini `MEDLOBY_OPERATOR_LEGAL_NAME` ve `MEDLOBY_OPERATOR_POSTAL_ADDRESS` ayarlarına gir. KVKK başvuru kanalını belirle. Kullanılacak e-posta ve KEP adreslerini `MEDLOBY_PRIVACY_EMAIL` ve `MEDLOBY_PRIVACY_KEP` ayarlarına gir. Destek ve içerik editörü adreslerini `MEDLOBY_SUPPORT_EMAIL` ve `MEDLOBY_EDITOR_EMAIL` ayarlarına gir.
- [ ] **Veri saklama ve işleme kurallarını onayla.** Kayıt gruplarının kesin saklama sürelerini ve sağlık verilerinin hangi şartlarla işleneceğini belirle. [Veri işleme ve saklama planı](resources/contracts/medloby-veri-isleme-ve-saklama-taslagi.md)
- [ ] **Onaylı KVKK metinlerini ve klinik üyelik sözleşmesini yayımla.** Veri işleme ve saklama planı doğrulanıp metinler onaylandığında `MEDLOBY_PRIVACY_PUBLISHED` ayarı hasta ve klinik aydınlatma sayfalarını açar. Klinik üyelik sözleşmesi yönetim ekranından kendi sürümü ve yürürlük tarihiyle yayımlanır. [Klinik üyelik taslağı](resources/contracts/medloby-klinik-uyelik-taslak.txt)
- [ ] **Üretim yedeklemesini kur ve geri yüklemeyi doğrula.** Seçilen veritabanı ve dosya depolaması için şifreli yedek konumunu, çalışma sıklığını, erişim yetkilerini ve saklama süresini belirle. Geri yükleme, şema değişikliği ve geri dönüşü üretimden ayrı bir kopyada prova et. [SQLite provası](#yerel-sqlite-yedekleme-ve-geri-yükleme) · [PostgreSQL provası](#postgresql-ve-özel-dosya-yedekleme-provası)
- [ ] **İlk platform yöneticisini oluştur.** Şema kurulduktan ve yedekleme provası doğrulandıktan sonra yetkili işletmeci hesabını terminalden oluştur ve yönetim ekranına girişini dene. [İlk yönetici kurulumu](#ilk-platform-yöneticisi)
- [ ] **Canlı e-posta gönderimini aç.** Site geliştirmesi tamamlandığında yetkili gönderici adresini ve destek adresini belirle. Canlı ortamda SMTP sunucusunu ve portunu yapılandır, gerekiyorsa erişim bilgilerini gizli ayarlara ekle. `APP_URL` değerini Medloby’nin HTTPS adresine ayarla. Geçici SMTP hatalarında yeniden deneme için kuyruk işçisinin deneme sayısını ve bekleme süresini ayarla. Başarısız işleri `php artisan queue:failed` ile kontrol et. Klinik başvurusunun onay ve ret e-postalarını, hasta doğrulamasını, şifre yenilemeyi ve yeni randevu e-postalarını gerçek bir test gelen kutusunda doğrula. [Klinik başvuru testleri](tests/Feature/ClinicAccountOnboardingTest.php) · [Hasta kaydı testleri](tests/Feature/PatientRegistrationTest.php) · [Randevu e-postası provası](tests/Feature/AppointmentNotificationTest.php) · [Şifre yenileme testleri](tests/Feature/AccountPasswordResetTest.php)
- [ ] **Yayın koşullarında ilk klinik ve hasta akışını prova et.** HTTPS, seçilen veritabanı, dosya depolaması ve bildirim kuyruğu çalışırken deneme hesaplarıyla klinik başvurusunu, onay sonrası geçici şifreyle CRM’e girişi, personel hesabı oluşturmayı, özel teklifi ve her seans için ayrı randevu almayı doğrula. Klinik ve hasta aynı hekimin aynı saatini eşzamanlı seçtiğinde tek kayıt oluştuğunu ve diğer hesabın saat uygunluğu uyarısını aldığını seçilen veritabanıyla sınayıp doğrula. [Klinik hesapları provası](tests/Feature/ClinicAccountOnboardingTest.php) · [Teklif ve randevu provası](tests/Feature/LaunchWorkflowTest.php) · [PostgreSQL randevu provası](#postgresql-randevu-provası)
- [ ] **Uygulama ve veritabanı erişimini izle.** HTTPS üzerinden `GET /up` adresini kontrol et. `Accept: application/json` başlığıyla uygulama erişilebiliyorsa ve veritabanı sorgusu başarılıysa `200` ve `{"status":"up"}` döner. Veritabanı bağlantı hatasında `500` ve `{"status":"down"}` döner. Ayrıntılar uygulama kayıtlarında incelenebilir. Yayın ortamında `APP_DEBUG=false` ayarını ve kullanılan izleme aracının kesintiyi algıladığını doğrula. [Erişim kontrolü testi](tests/Feature/ApplicationHealthTest.php)
- [ ] **CRM ve randevu hatırlatma zamanlayıcısını çalıştır.** Üretim veritabanı şemasını güncelledikten sonra `php artisan schedule:run` komutunu Medloby dizininden her dakika çalıştır. Saati gelen deneme takibinin yetkili personele, son 24 saate giren onaylı randevunun ilgili hastaya bildirim gönderdiğini doğrula. İki bildirimin de kendi kaydını açtığını ve art arda zamanlayıcı çalıştırıldığında birer kez oluştuğunu kontrol et. Aynı senaryo yerel SQLite ve veritabanı önbelleğiyle otomatik olarak sınanıyor. [Zamanlayıcı provası](tests/Feature/ReminderScheduleTest.php)
- [ ] **Bekleme listesi bildirimini prova et.** Deneme hasta hesabıyla dolu bir gün için bildirim iste. Uygun bir saat açıldığında beş dakikada bir çalışan zamanlayıcının tek bildirim gönderdiğini ve bildirimin seçilen günü açtığını doğrula. [Bekleme listesi testi](tests/Feature/AppointmentWaitingListTest.php)

### Yayın öncesi teknik kontrol

Dağıtım ortamında uygulamayı yapılandırdıktan ve ön yüzü derledikten sonra çalıştır.

```bash
php artisan medloby:release-check --no-interaction
```

Komut mevcut ayarları, veritabanı bağlantısını ve migration kayıtlarını, yayın dosyalarını, yönetici hesabını ve yürürlükteki klinik üyelik sözleşmesini salt okunur biçimde kontrol eder. Her madde için **Hazır**, **Eksik** veya **Bekliyor** gösterir. Tüm maddeler hazırsa çıkış kodu `0`, eksik veya bekleyen madde varsa `1` olur.

Canlı HTTPS erişimini, SMTP teslimatını, kuyruk işçisini, zamanlayıcıyı ve yedekten geri yüklemeyi yukarıdaki adımlarla prova et. [Teknik kontrol testleri](tests/Feature/ReleaseReadinessTest.php)

### İlk platform yöneticisi

Uygulamanın veritabanı şemasını kurduktan sonra sunucudaki etkileşimli terminalde çalıştır.

```bash
php artisan medloby:admin:bootstrap
```

Yetkili işletmecinin adını ve doğruladığı e-posta adresini gir. Şifre ve tekrarı terminalde gizli olarak alınır. Komut ilk yeni yönetici hesabını oluşturur ve işletmecinin girdiği adresi doğrulanmış olarak kaydeder. Kurulumdan sonra yönetici bu e-posta ve şifreyle giriş yapabilir. [Kurulum testi](tests/Feature/BootstrapAdministratorTest.php)

### Yerel SQLite yedekleme ve geri yükleme

Komutlar, yapılandırılmış SQLite dosyasını ve yerel özel dosyaları birlikte yedekler. Aynı ana ait kayıt ve dosya içeriği için yedek alırken uygulama ile arka plan işçilerinin yazma işlemlerini durdur. Test verileriyle hazırlanmış bir kurulumda aşağıdaki komutlarla yeni bir yedek ve ayrı bir geri yükleme dizini oluşturabilirsin.

```bash
backup_root=$(mktemp -d)
php artisan medloby:backup "$backup_root/backup" --no-interaction
php artisan medloby:restore "$backup_root/backup" "$backup_root/restored" --no-interaction
```

Yedek dizininde `database.sqlite`, `storage/app/private` ve SHA-256 doğrulama toplamlarını taşıyan `manifest.json` bulunur. Geri yükleme sonucu doğrulandığında test uygulamasının `DB_DATABASE` değerini geri yüklenen SQLite dosyasına, `LARAVEL_STORAGE_PATH` değerini geri yüklenen `storage` dizinine yönelt. Uygulama anahtarı ve ortam sırlarını kendi güvenli saklama yerlerinden kullan. Yedekleri erişimi sınırlandırılmış, şifreli bir depoda sakla.

Otomatik provada geri yüklenen kopyada son migration geri alınıp yeniden uygulanır. Örnek görüşme eki, yetkili hasta hesabıyla açılır.

```bash
php artisan test --compact tests/Feature/LocalBackupTest.php
```

### PostgreSQL ve özel dosya yedekleme provası

Uygulamanın etkin bağlantısını Medloby’ye ayrılmış PostgreSQL veritabanına yönelt. Aynı ana sürümdeki `pg_dump` ve `pg_restore` araçları terminalin `PATH` ayarında bulunmalı. `encrypted_backup_root` erişimi kısıtlı, şifreli bir dizin olmalı. Veritabanı ve özel dosyaların aynı durumu yansıtması için yedek boyunca uygulamanın, kuyruk işçilerinin ve zamanlayıcının yazmalarını durdur.

```bash
backup_root=$(mktemp -d "$encrypted_backup_root/medloby.XXXXXX")
php artisan medloby:backup "$backup_root/backup" --no-interaction
php artisan medloby:restore "$backup_root/backup" "$backup_root/restored" --no-interaction
```

Yedek `database.dump`, `storage/app/private` ve SHA-256 toplamlarını içeren `manifest.json` dosyasını içerir. Komut, görüşmelere bağlı özel dosyaların bulunduğunu kontrol eder. `medloby:restore`, doğrulanan veritabanı yedeği ve özel dosyaları yeni dizine çıkarır.

Prova için ayrı ve boş bir PostgreSQL veritabanı oluştur. Yetkili test hesabının bağlantı ayarlarını kullan ve `restore_db` değerini bu test veritabanına yönelt. Ardından doğrulanmış yedeği içe aktar.

```bash
pg_restore --exit-on-error --single-transaction --no-owner --no-privileges \
  --dbname="$restore_db" "$backup_root/restored/database.dump"
```

Test uygulamasında `DB_DATABASE` değerini bu veritabanına, `LARAVEL_STORAGE_PATH` değerini `$backup_root/restored/storage` dizinine yönelt. Kaynak uygulamanın anahtarını güvenli saklama yerinden kullan. `php artisan migrate:status` ile şemayı kontrol et. Bir deneme görüşmesi ekini yetkili hasta hesabıyla aç ve başka bir hesabın aynı dosyaya erişiminin reddedildiğini doğrula. Geri yüklenen kopyada son migration’ı geri al, yeniden uygula ve dosyayı tekrar aç. [PostgreSQL yedekleme testleri](tests/Feature/PostgresBackupTest.php)

### PostgreSQL randevu provası

GitHub Actions, `main` dalına gönderimlerde ve bu dalı hedefleyen pull request’lerde tam test paketini SQLite ve geçici bir PostgreSQL 17 veritabanıyla çalıştırır. [CI iş akışı](.github/workflows/ci.yml)

PHP’de `pdo_pgsql` etkin olan bir ortamda, veritabanı bağlantı ayarlarını ayrı bir PostgreSQL test veritabanına yönelt. Test hesabı şema oluşturabilmeli. Aşağıdaki test, kendi açtığı geçici şemada migration’ları uygular ve iki işlemden aynı saati rezerve etmeyi dener. Test sonunda geçici şemayı kaldırır.

```bash
DB_CONNECTION=pgsql php artisan test --compact tests/Feature/AppointmentConcurrencyTest.php
```

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
