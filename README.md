# WhatsApp Blast Campaign

Aplikasi blast campaign WhatsApp berbasis **Laravel 12** dan **Filament 5**. Mengirim pesan ke group customer melalui WhatsApp gateway API (text, button, media) dengan delay/timer, queue per pesan, penjadwalan, dan pelacakan status per nomor.

## Fitur

- **Master Data Sender** — nomor WhatsApp pengirim
- **Master Data Customer** — data penerima
- **Customer Group** — grouping customer + assign customer ke group
- **Campaign** — blast ke group dengan opsi:
  - Sender tetap atau rotating random
  - Tipe pesan: Text, Button, Image/File
  - Delay per pesan atau per batch
  - Langsung kirim atau terjadwal
  - Status: Running, Paused, Stopped, Completed
- **Detail Campaign** — log per nomor (status, response API, error)
- **Settings** — konfigurasi URL & API Key gateway (dapat diubah dari admin)

## Persyaratan Server

| Komponen | Versi minimum |
|----------|----------------|
| PHP | 8.2+ |
| Composer | 2.x |
| Node.js & npm | 18+ (untuk build assets) |
| Database | MySQL 8 / MariaDB 10.3+ (production) atau SQLite (development) |
| Ekstensi PHP | `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml` |

> **Penting:** Aplikasi ini membutuhkan **queue worker** dan **scheduler** agar campaign berjalan. Tanpa keduanya, pesan tidak akan terkirim.

---

## Instalasi Lokal (Development)

### 1. Clone & install dependency

```bash
git clone <repository-url> appwei
cd appwei

composer install
cp .env.example .env
php artisan key:generate
```

### 2. Konfigurasi `.env`

```env
APP_NAME="WhatsApp Blast"
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
# Atau MySQL:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=appwei
# DB_USERNAME=root
# DB_PASSWORD=

QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database
FILESYSTEM_DISK=public
```

Jika pakai SQLite, pastikan file database ada:

```bash
touch database/database.sqlite
```

### 3. Migrasi & storage link

```bash
php artisan migrate
php artisan storage:link
php artisan db:seed   # opsional: user demo + sample data
```

### 4. Build frontend assets

```bash
npm install
npm run build
```

### 5. Jalankan aplikasi

Terminal 1 — web server:

```bash
php artisan serve
```

Terminal 2 — queue worker (wajib):

```bash
php artisan queue:work
```

Terminal 3 — scheduler (wajib untuk campaign terjadwal):

```bash
php artisan schedule:work
```

Atau jalankan sekaligus dengan:

```bash
composer dev
```

### 6. Login admin panel

- URL: `http://localhost:8000/admin`
- Email: `test@example.com`
- Password: `password`

Lalu buka menu **Settings** dan isi **API Base URL** serta **API Key** WhatsApp gateway Anda.

---

## Konfigurasi WhatsApp Gateway

Di admin panel → **Settings**:

| Field | Contoh |
|-------|--------|
| API Base URL | `https://wa.forfunforlife.com` |
| API Key | API key dari provider |

Endpoint yang digunakan aplikasi:

| Tipe pesan | Endpoint |
|------------|----------|
| Text | `POST /send-message` |
| Button | `POST /send-button` |
| Media | `POST /send-media` |

Parameter umum: `api_key`, `sender`, `number`, plus field sesuai tipe pesan.

### URL media (Image/File)

File di-upload ke `storage/app/public/campaign-media/`. Gateway membutuhkan **URL publik langsung**.

Pastikan:

1. `php artisan storage:link` sudah dijalankan
2. `APP_URL` di `.env` sesuai domain production (mis. `https://blast.domain.com`)
3. Folder `public/storage` dapat diakses dari internet

---

## Struktur Menu Admin

| Menu | Path |
|------|------|
| Senders | `/admin/senders` |
| Customers | `/admin/customers` |
| Customer Groups | `/admin/customer-groups` |
| Campaigns | `/admin/campaigns` |
| Settings | `/admin/whats-app-settings` |

---

## Alur Campaign

1. Buat **Sender**, **Customer**, dan assign customer ke **Customer Group**
2. **Create Campaign** — pilih group, tipe pesan, delay, jadwal
3. Jika tidak dijadwalkan → campaign langsung **Running** dan job masuk queue
4. Buka **View Campaign** untuk melihat progress dan detail per nomor
5. Gunakan tombol **Pause / Resume / Stop** sesuai kebutuhan

---

## Deployment

### Checklist sebelum deploy

```bash
# Jalankan di mesin build / lokal sebelum upload (opsional)
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Di server production, set minimal:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.com

QUEUE_CONNECTION=database
```

---

## 1. Deployment di cPanel / Shared Hosting

cPanel umumnya tidak mendukung proses background permanen (Supervisor). Gunakan **Cron Job** untuk scheduler dan queue.

### 1.1 Upload project

**Opsi A — Upload via Git (jika SSH tersedia):**

```bash
cd ~/appwei
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

**Opsi B — Upload ZIP:**

1. Build assets di komputer lokal: `npm run build`
2. Upload seluruh folder project ke `~/appwei` (di luar `public_html`)
3. Jangan upload folder `node_modules/`

### 1.2 Atur document root

Di cPanel → **Domains** → **Document Root**:

Arahkan ke folder `public` aplikasi:

```
/home/username/appwei/public
```

Jika domain harus tetap di `public_html`, ada dua cara:

**Cara 1 (disarankan):** ubah document root subdomain ke `appwei/public`

**Cara 2:** pindahkan isi `public/` ke `public_html/` dan edit `public_html/index.php`:

```php
require __DIR__.'/../appwei/vendor/autoload.php';
$app = require_once __DIR__.'/../appwei/bootstrap/app.php';
```

(sesuaikan path folder `appwei`)

### 1.3 File `.env` di server

Buat `.env` di root project (`~/appwei/.env`):

```env
APP_NAME="WhatsApp Blast"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=nama_database_cpanel
DB_USERNAME=user_database_cpanel
DB_PASSWORD=password_database

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=public

LOG_CHANNEL=single
LOG_LEVEL=error
```

Generate key lewat **Terminal cPanel** atau **Setup Laravel App** (jika ada):

```bash
cd ~/appwei
php artisan key:generate
```

### 1.4 Database

1. Buat database & user di cPanel → **MySQL Databases**
2. Assign user ke database (All Privileges)
3. Jalankan migrasi via Terminal:

```bash
cd ~/appwei
php artisan migrate --force
php artisan storage:link
php artisan db:seed --force   # opsional
```

### 1.5 Permission folder

```bash
cd ~/appwei
chmod -R 775 storage bootstrap/cache
```

Jika hosting punya user khusus (mis. `nobody`), sesuaikan owner:

```bash
chown -R username:username storage bootstrap/cache
```

### 1.6 Cron Job (wajib)

Di cPanel → **Cron Jobs**, tambahkan **2 entri**:

**Scheduler** (setiap menit):

```cron
* * * * * cd /home/username/appwei && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Ganti `/usr/local/bin/php` dengan path PHP di server (cek di cPanel → **Select PHP Version** → **php path**, biasanya `/usr/bin/php` atau `/opt/cpanel/ea-php82/root/usr/bin/php`).

**Queue worker** (setiap menit, proses job yang menunggu):

```cron
* * * * * cd /home/username/appwei && /usr/local/bin/php artisan queue:work database --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

> `--stop-when-empty --max-time=55` mencegah cron overlap dan cocok untuk shared hosting.

### 1.7 Optimasi (via Terminal)

```bash
cd ~/appwei
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize
```

### 1.8 SSL

Aktifkan **AutoSSL** atau **Let's Encrypt** di cPanel untuk domain Anda. Pastikan `APP_URL` memakai `https://`.

### 1.9 Troubleshooting cPanel

| Masalah | Solusi |
|---------|--------|
| 500 Internal Server Error | Cek `storage/logs/laravel.log`, permission `storage/` |
| CSS/JS tidak load | Jalankan `npm run build`, pastikan `public/build` ter-upload |
| Campaign tidak kirim | Pastikan 2 cron job aktif, cek tabel `jobs` & `failed_jobs` |
| Media tidak terkirim | `APP_URL` harus HTTPS domain benar, `storage:link` sudah jalan |
| `composer` tidak ada | Install via SSH atau upload folder `vendor/` dari lokal |

---

## 2. Deployment di VPS (Ubuntu/Debian)

Contoh stack: **Nginx + PHP 8.2-FPM + MySQL + Supervisor**.

### 2.1 Persiapan server

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server php8.2-fpm php8.2-cli php8.2-mysql \
  php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath php8.2-intl \
  git unzip supervisor
```

Install Composer & Node:

```bash
# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node.js 20 LTS
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

### 2.2 Clone project

```bash
cd /var/www
sudo git clone <repository-url> appwei
sudo chown -R $USER:www-data appwei
cd appwei
```

### 2.3 Install & build

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate

npm ci
npm run build
```

### 2.4 Konfigurasi `.env`

```bash
nano .env
```

```env
APP_NAME="WhatsApp Blast"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://blast.domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=appwei
DB_USERNAME=appwei
DB_PASSWORD=password_kuat

QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database
FILESYSTEM_DISK=public
```

Buat database:

```bash
sudo mysql -e "CREATE DATABASE appwei CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'appwei'@'localhost' IDENTIFIED BY 'password_kuat';"
sudo mysql -e "GRANT ALL PRIVILEGES ON appwei.* TO 'appwei'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"
```

Migrasi:

```bash
php artisan migrate --force
php artisan storage:link
php artisan db:seed --force   # opsional
```

Permission:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

Cache:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize
```

### 2.5 Konfigurasi Nginx

Buat file `/etc/nginx/sites-available/appwei`:

```nginx
server {
    listen 80;
    server_name blast.domain.com;
    root /var/www/appwei/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    client_max_body_size 20M;
}
```

Aktifkan site:

```bash
sudo ln -s /etc/nginx/sites-available/appwei /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 2.6 SSL (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d blast.domain.com
```

Update `APP_URL=https://blast.domain.com` lalu:

```bash
php artisan config:cache
```

### 2.7 Supervisor — Queue Worker

Buat `/etc/supervisor/conf.d/appwei-worker.conf`:

```ini
[program:appwei-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/appwei/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/appwei/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start appwei-worker:*
sudo supervisorctl status
```

### 2.8 Cron — Scheduler

```bash
sudo crontab -u www-data -e
```

Tambahkan:

```cron
* * * * * cd /var/www/appwei && php artisan schedule:run >> /dev/null 2>&1
```

Scheduler menjalankan command `campaigns:process-scheduled` setiap menit (didefinisikan di `routes/console.php`).

### 2.9 Deploy update (setelah ada perubahan code)

```bash
cd /var/www/appwei
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize
sudo supervisorctl restart appwei-worker:*
```

### 2.10 Monitoring VPS

```bash
# Log aplikasi
tail -f /var/www/appwei/storage/logs/laravel.log

# Log queue worker
tail -f /var/www/appwei/storage/logs/worker.log

# Job gagal
php artisan queue:failed
php artisan queue:retry all
```

---

## Perintah Artisan Penting

| Perintah | Fungsi |
|----------|--------|
| `php artisan migrate` | Jalankan migrasi database |
| `php artisan db:seed` | Seed data demo |
| `php artisan storage:link` | Symlink storage publik (media campaign) |
| `php artisan queue:work` | Jalankan queue worker |
| `php artisan schedule:work` | Jalankan scheduler (development) |
| `php artisan campaigns:process-scheduled` | Proses campaign terjadwal (manual) |
| `php artisan queue:failed` | Lihat job gagal |
| `php artisan optimize:clear` | Bersihkan cache (saat debug) |

---

## Struktur Kode Utama

```
app/
├── Enums/              # Status campaign, tipe pesan, dll.
├── Filament/           # Admin panel (Resources, Pages)
├── Jobs/               # SendCampaignMessageJob
├── Models/             # Sender, Customer, Campaign, ...
├── Services/           # WhatsAppGatewayService, CampaignService
└── Console/Commands/   # ProcessScheduledCampaigns
```

---

## Lisensi

Proyek ini menggunakan [Laravel](https://laravel.com) yang dilisensikan di bawah [MIT license](https://opensource.org/licenses/MIT).
