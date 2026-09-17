# Deployment, Operations, Backup & Disaster Recovery

## Environments
| Env | `APP_ENV` | display_errors | db_debug | Catatan |
|---|---|---|---|---|
| development | development | on (tanpa E_DEPRECATED) | on | `php -S` atau docker compose |
| staging | staging | off | off | mirror production, data anonim |
| production | production | off | off | HSTS aktif, cookie secure |
Semua kredensial via `.env` (tidak di-commit). Template: `.env.example`. Wajib diganti: `APP_KEY`, `JWT_SECRET` (≥32 char, `openssl rand -hex 32`), `DB_*`, `ADMIN_PASSWORD`.

## Deploy (Docker)
```bash
cp .env.example .env && edit .env
docker compose up -d --build
docker compose exec app composer install --no-dev --optimize-autoloader
docker compose exec app php index.php cli/migrate latest
docker compose exec app php index.php cli/seed run      # hanya non-production / initial reference data
docker compose exec app php index.php cli/health        # exit 0 = sehat
```
Nginx memblokir akses ke `application/`, `vendor/`, `docs/`, `tests/`, `storage/`, dan file `.env|.md|.json|.yml|.xml`. PHP-FPM `expose_php=Off`. Ganti `fastcgi_param CI_ENV` sesuai env.

## Deploy (bare metal Ubuntu/RHEL)
PHP 7.4-fpm (ext: mysqli, pdo_mysql, mbstring, intl, json, openssl, sodium), nginx, MySQL 8. Document root = folder proyek; hanya `index.php` + `assets/` yang perlu dilayani. `chown www-data application/logs application/cache storage`. Cron: `*/5 * * * * php /path/index.php cli/health || alert`.

## Observability
- Application log: `application/logs/log-YYYY-MM-DD.php` (threshold `LOG_THRESHOLD`; 1=error, 2=debug(+info), 4=all). Setiap error membawa `request_id` yang juga dikembalikan ke klien API.
- Security log: tabel `login_history`, `login_attempts`, `audit_logs` (module `auth`).
- Audit log: `audit_logs` (append-only). Error DB: log + halaman 500 generik di production.
- DB health: `cli/health` (koneksi, versi skema, log writable, JWT secret). MySQL slow log aktif (`docker/my.cnf`).
- Failed job/event log: Phase 7 (notification/outbox table).

## Backup Strategy
| Item | Metode | Frekuensi | Retensi |
|---|---|---|---|
| Database | `mysqldump --single-transaction --routines --triggers pharmaerp \| gzip` (konsisten InnoDB) + binlog (PITR, `binlog_expire_logs_seconds=7d`) | Full harian 02:00 WIB; binlog kontinu | 30 hari harian, 12 bulan bulanan, 7 tahun tahunan (dokumen keuangan/farmasi: **REGULATORY VERIFICATION REQUIRED** untuk masa retensi resmi) |
| File upload (`storage/`) | rsync/snapshot object storage versioned | Harian | sama dengan DB |
| Konfigurasi `.env`, nginx | vault/secret manager, backup terenkripsi | saat berubah | 5 versi |
Backup dienkripsi (age/gpg), disalin off-site (region berbeda). **Verifikasi**: restore otomatis mingguan ke instance staging + `cli/health` + checksum jumlah baris `stock_ledger`, `audit_logs`, `stock_movements`.

## Restore Procedure
1. Hentikan app (`docker compose stop app web`).
2. `gunzip < backup.sql.gz | mysql pharmaerp` (DB kosong / baru).
3. PITR: `mysqlbinlog --start-datetime=… mysql-bin.* | mysql pharmaerp` sampai sebelum insiden.
4. Restore `storage/`. 5. `php index.php cli/migrate latest` (idempoten). 6. `cli/health` → start app. 7. Catat insiden di audit (module `system`, action `restore`).

## Disaster Recovery
- **RPO**: ≤ 5 menit (binlog dikirim ke replika/objek storage tiap 5 menit); harian jika binlog tidak tersedia.
- **RTO**: ≤ 2 jam (restore full + binlog + smoke test). Untuk HA: MySQL replica async + failover manual terdokumentasi; app stateless (session di DB) sehingga bisa di-scale horizontal di belakang load balancer.
- DR drill per kuartal, hasil dicatat (tanggal, durasi, RPO aktual).

## Hardening checklist (Phase 9)
DB user aplikasi tanpa `DELETE` pada tabel append-only; `SUPER`/`FILE` tidak diberikan · TLS 1.2+ · WAF/rate limit di edge · rotasi `JWT_SECRET` dengan grace period · pemantauan `login_history` FAILED/LOCKED · pemisahan akun DBA · backup restore test otomatis.
