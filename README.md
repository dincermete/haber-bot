# Haber Bot

RSS kaynaklarından haber toplayan, OpenAI ile özetleyen, kapak + şablon + başlık birleştirerek görsel üreten ve Telegram'a bildiren **Laravel + Filament 5** admin paneli.

## Gereksinimler

- PHP 8.3+
- Composer 2
- Node.js 20+ (Vite / Filament asset derlemesi için)
- PHP eklentileri: `gd` veya `imagick`, `sqlite3` (veya MySQL)
- Windows / Linux / macOS

## Kurulum

```bash
git clone https://github.com/KULLANICI/haber-bot.git
cd haber-bot

composer install
cp .env.example .env   # Windows: copy .env.example .env
php artisan key:generate

touch database/database.sqlite   # Windows: New-Item database/database.sqlite -ItemType File
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan filament:assets

npm install
npm run build
```

### İlk giriş

- URL: `http://127.0.0.1:8000/admin`
- E-posta: `admin@haberbot.local`
- Şifre: `password`

İlk girişten sonra şifreyi değiştirin.

### Görsel üretimi için

1. `storage/app/fonts/Urbanist-Black.ttf` dosyasını ekleyin (veya admin → Görsel Şablonları'nda font yolunu güncelleyin).
2. Admin → **Görsel Şablonları** üzerinden PNG şablon yükleyin.
3. Admin → **Ayarlar** bölümünden OpenAI ve Telegram bilgilerini girin.

## Çalıştırma (geliştirme)

Üç terminal:

```bash
# Web
php artisan serve

# Kuyruk (görsel üretimi, Telegram)
php artisan queue:work --tries=3

# RSS zamanlayıcı (5 dk)
php artisan schedule:work
```

Manuel RSS taraması:

```bash
php artisan rss:check
```

## Production notları

- `APP_ENV=production`, `APP_DEBUG=false`
- `php artisan config:cache`, `route:cache`, `view:cache`
- Queue worker ve cron: `* * * * * php artisan schedule:run`
- `.env` dosyasını asla repoya eklemeyin
- `OPENAI_API_KEY`, Telegram bot token ve chat ID gizli tutulmalı

## Özellikler

- RSS feed yönetimi ve otomatik tarama
- Anahtar kelime filtreleri (beyaz / kara liste)
- Haber havuzu: düzenleme, AI özet, kapak seçimi
- Görsel üretimi: kapak + şablon + başlık → tek PNG
- Koordinat editörü (Filament + Livewire)
- Telegram yeni haber bildirimi
- Onaylı haber arşivi

## Lisans

MIT
