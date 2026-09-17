# PharmaERP — ERP + Inventory + Pharmacy Management System (Indonesia)

Stack: **PHP 7.4 · CodeIgniter 3.1.13 · MySQL 8 (InnoDB)** · Monolithic Modular MVC · Repository Pattern · Service Layer · Constructor DI (reflection container).

Delivered so far: **Phase 0 (Architecture)**, **Phase 1 (Foundation)**, **Phase 2 (Inventory Core)**.
Lihat `docs/` untuk dokumentasi lengkap dan `docs/90-phase-report.md` untuk laporan per phase.

## Quick start (Docker)

```bash
cp .env.example .env            # isi APP_KEY, JWT_SECRET, DB_PASSWORD
docker compose up -d --build     # php-fpm 7.4 + nginx + mysql 8
docker compose exec app composer install
docker compose exec app php index.php cli/migrate latest
docker compose exec app php index.php cli/seed run
# buka http://localhost:8080  (admin: bnuputra@gmail.com / lihat seed output / .env ADMIN_PASSWORD)
```

## Quick start (tanpa Docker)

Butuh PHP 7.4 (ext: pdo_mysql, mbstring, json, openssl, sodium), Composer, MySQL 8.

```bash
composer install
cp .env.example .env
php index.php cli/migrate latest
php index.php cli/seed run
php -S 0.0.0.0:8080 -t . index.php      # dev only
```

## Perintah CLI

| Perintah | Fungsi |
|---|---|
| `php index.php cli/migrate latest` | Jalankan migrasi ke versi terbaru |
| `php index.php cli/migrate version 2` | Migrasi ke versi tertentu |
| `php index.php cli/seed run` | Seed data demo (idempotent) |
| `php index.php cli/health` | Health-check DB & aplikasi |
| `vendor/bin/phpunit` | Jalankan test suite |

## Struktur

```
erp-ci3/
├── application/          # kode aplikasi (lihat docs/00-architecture.md §6)
├── assets/               # css/js (progressive enhancement, vanilla JS)
├── docker/               # Dockerfile, nginx.conf, my.cnf
├── docs/                 # dokumentasi enterprise
├── tests/                # PHPUnit: Unit, Integration, Feature/API
├── index.php             # front controller (menunjuk vendor/codeigniter/framework/system)
└── composer.json
```
