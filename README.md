# 3D Model Arşivi

### Free & Open Source STL Archive Manager

A modern web application for organizing, browsing and managing STL and other 3D model files with optional Google Drive synchronization.

Perfect for makers who want a clean, searchable and visual library for their growing STL collection.

![Home](screenshots/home.png)

---

## Why?

As my personal STL collection grew, finding the right model in Google Drive became increasingly difficult.

I wanted a fast, visual and easy-to-use archive where I could browse models with preview images, organize them into categories and keep everything synchronized with Google Drive.

This project was created to solve that problem and is released completely free and open source for anyone who might find it useful.

---

## Features

- 🖼️ Visual gallery with image previews
- 📁 Category management
- 🔎 Instant search
- ☁️ Optional Google Drive synchronization
- 🖼️ Multiple images for each model
- ⭐ Cover image selection
- ↕️ Drag & Drop image ordering
- 📊 Storage usage information
- 🌙 Modern dark interface
- 💾 Self-hosted
- 🆓 Free & Open Source

---

## Screenshots

### Home

Browse your STL collection with categories, search, statistics and storage information.

![Home](screenshots/home.png)

---

### Model Details

View model information, preview images and download files.

![Model Details](screenshots/details.png)

---

### Edit Model

Manage multiple images, choose a cover image and edit model details.

![Edit Model](screenshots/edit.png)

---

### Google Drive Integration

Configure Google Drive OAuth and synchronize your archive directly from Drive.

![Google Drive Integration](screenshots/google-drive-settings.png)

---

### Category Management

Create, edit and organize model categories.

![Category Management](screenshots/categories.png)

---

### Add New Model

Add new models with multiple preview images and file information.

![Add New Model](screenshots/new-record.png)

---

## Requirements

## Gereksinimler

Uygulamayı çalıştırmak için aşağıdakiler gerekir:

- PHP
  - Önerilen: PHP 8.0+
- MySQL veya MariaDB
- Web sunucusu
  - Apache, Nginx veya WAMP/XAMPP benzeri bir ortam
- Composer bağımlılıkları
  - `vendor/` klasörü mevcut olmalı
- Google API PHP Client
  - Composer bağımlılıkları içinde gelir
- `upload/` klasörü için yazma izni

## Dosyaları Sunucuya Kopyalama

1. Proje klasörünü web root altına kopyalayın.
   - Örnek: `C:\wamp64\www\3d-release`
2. `upload/` klasörü dağıtımda boş gelir.
   - Bu normaldir.
   - Uygulama yüklenen görselleri burada saklar.
3. `includes/db.php` dosyası dağıtıma dahil edilmez.
   - Bu dosya kurulum sırasında `install.php` tarafından oluşturulur.

## Kurulum

1. Tarayıcıdan `install.php` dosyasını açın.
   - Örnek:
   - `http://localhost/3d-release/install.php`
2. Veritabanı bilgilerini girin:
   - DB Host
   - DB Port
   - DB Adı
   - DB Kullanıcı
   - DB Şifre
3. Gerekirse `DB yoksa oluşturmayı dene` seçeneğini işaretleyin.
   - Bu seçenek uygunsa veritabanını otomatik oluşturmaya çalışır.
4. Google alanlarını isterseniz bu aşamada boş bırakabilirsiniz.
   - Kurulum yine tamamlanır.
   - Google Drive bağlantısı daha sonra Ayarlar ekranından yapılabilir.
5. Kurulum tamamlandıktan sonra:
   - `includes/db.php` oluşturulur
   - `install.lock` oluşturulur
   - Kurulum ekranı tekrar açılmaz

## Google Drive Ayarları

Google Drive entegrasyonu panel tabanlı çalışır. `credentials.json` veya `token.json` kullanılmaz.

Kurulumdan sonra:

1. Ayarlar sayfasını açın.
2. `Google Drive Entegrasyonu` bölümüne gidin.
3. Google Cloud Console üzerinden oluşturduğunuz OAuth bilgilerini girin:
   - OAuth Client ID
   - OAuth Client Secret
   - Redirect URI
   - Drive Klasör ID
4. `Google Drive'a Bağlan` butonuna basın.
5. Google izin ekranını tamamlayın.
6. Bağlantı durumu `Bağlı` olduktan sonra ana sayfadaki:
   - `Google Drive’dan Güncelle` butonunu kullanarak dosyaları çekebilirsiniz.

### Google Cloud Console’da OAuth Client Oluşturma

Genel akış:

1. Google Cloud Console’a gidin.
2. Bir proje seçin veya yeni proje oluşturun.
3. Google Drive API’yi etkinleştirin.
4. OAuth consent screen ayarlarını tamamlayın.
5. OAuth Client oluşturun.
   - Uygulama türü: Web application
6. `Authorized redirect URI` alanına uygulamanın Ayarlar ekranında gösterdiği `Redirect URI` değerini ekleyin.

## Google Drive Klasör ID Nasıl Bulunur?

Google Drive klasör bağlantısı genellikle şu yapıya benzer:

```text
https://drive.google.com/drive/folders/XXXXXXXXXXXXXXXXXXXX
```

Buradaki `/folders/` sonrasındaki değer klasör ID’sidir.

Örnek:

```text
https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOpQrStUv
```

Bu durumda klasör ID:

```text
1AbCdEfGhIjKlMnOpQrStUv
```

## Güvenlik Notları

- `credentials.json` ve `token.json` artık kullanılmaz.
- Google bağlantı bilgileri panel üzerinden girilir ve veritabanında saklanır.
- `includes/db.php` dosyası paylaşılmamalıdır.
- `install.lock` dosyası paylaşılmamalıdır.
- `.gitignore` bu dosyaları dışarıda bırakacak şekilde ayarlanmıştır.
- `install.lock` mevcutsa kurulum ekranı tekrar açılmaz.

## Kurulum Sonrası Kontrol

Kurulumdan sonra aşağıdaki kontrolleri yapın:

1. Ana sayfa açılıyor mu?
2. Kategoriler görünüyor mu?
3. Ayarlar ekranı açılıyor mu?
4. Google Drive bağlantı durumu doğru görünüyor mu?
5. Ana sayfadaki depolama kartı veri çekebiliyor mu?
6. `Google Drive’dan Güncelle` butonu çalışıyor mu?

## Sorun Giderme

### `install.php` açılmıyor

- Proje klasörünün doğru web root altında olduğundan emin olun.
- PHP’nin çalıştığını kontrol edin.
- Web sunucusunun ilgili klasöre erişebildiğini doğrulayın.

### Veritabanı bağlantı hatası

- Host, port, kullanıcı adı ve şifrenin doğru olduğunu kontrol edin.
- MySQL/MariaDB servisinin çalıştığından emin olun.
- Kullanıcının veritabanı oluşturma veya bağlanma yetkisini kontrol edin.

### `upload/` yazma izni hatası

- `upload/` klasörünün mevcut olduğundan emin olun.
- Web sunucusu kullanıcısının bu klasöre yazma izni olmalıdır.

### `redirect_uri_mismatch` hatası

- Google Cloud Console’daki `Authorized redirect URI` ile
- uygulamadaki `Redirect URI` birebir aynı olmalıdır.
- Protokol, domain, port ve klasör yolu dahil tamamen eşleşmelidir.

### `Google Drive bağlantısı yapılmamış` hatası

- Ayarlar ekranında Google bilgilerini kaydettiğinizden emin olun.
- `Google Drive’a Bağlan` adımını tamamlayın.
- Bağlantı durumu `Bağlı` değilse yeniden yetkilendirme gerekebilir.

### `vendor/autoload.php bulunamadı` hatası

- Composer bağımlılıkları eksik olabilir.
- Proje kökünde aşağıdaki komutu çalıştırın:

```bash
composer install
```

## Geliştirici Notları

- `schema.sql`
  - Sıfırdan kurulum içindir.
  - Gerekli tabloları ve varsayılan kategorileri oluşturur.
- `reset_google_drive_settings_for_distribution.sql`
  - Google Drive kişisel bağlantı bilgilerini temizlemek için kullanılır.
- `includes/db.example.php`
  - Örnek veritabanı bağlantı dosyasıdır.
  - Gerçek bağlantı bilgisi içermez.

## Özet

Kurulum sırası kısaca şöyledir:

1. Dosyaları sunucuya kopyalayın
2. `install.php` açın
3. Veritabanı bilgilerini girin
4. Kurulumu tamamlayın
5. Ayarlar ekranından Google Drive bilgilerini girin
6. Google Drive’a bağlanın
7. Ana sayfadan senkronizasyonu başlatın

