# SanaFarma (PharmaERP)

ERP farmasi untuk apotek retail + PBF (distribusi) Indonesia. Dokumentasi & UI Bahasa Indonesia.

## Mana yang aktif, mana yang bukan

| Folder | Status | Keterangan |
|--------|--------|------------|
| **`erp-ci3/`** | ✅ **APLIKASI AKTIF** | PHP 7.4 · CodeIgniter 3 · MySQL 8. Semua kode nyata ada di sini. |
| `backend/` | ⚪ stub, **tidak dipakai** | Scaffold FastAPI bawaan platform. Dibiarkan, tidak di-install. |
| `frontend/` | ⚪ stub, **tidak dipakai** | Scaffold React (CRA) bawaan platform. Dibiarkan, tidak di-install. |
| `.emergent/`, `memory/`, `test_reports/`, `tests/`, `test_result.md` | ⚪ noise platform | Artefak tooling; bukan bagian aplikasi. |

> ⚠️ Aplikasi ini **tidak bisa di-preview di hosting Emergent** (butuh PHP/MySQL, bukan React/FastAPI/Mongo).
> Jalankan di Docker/server sendiri. Ini keputusan arsitektur yang disengaja: ledger immutable,
> `FOR UPDATE` row-lock, transaksi multi-tabel, dan period closing menuntut MySQL InnoDB — bukan MongoDB.

> ⚠️ **Catatan kepatuhan:** PHP 7.4 sudah End-of-Life sejak November 2022 (tidak ada patch keamanan).
> Untuk ERP yang menyimpan data pasien/resep ini adalah risiko yang harus tercatat — bukan diabaikan.

## Cara menjalankan (Docker)

Detail lengkap: `erp-ci3/docs/03-deployment-backup-dr.md`.

```bash
cd erp-ci3
cp .env.example .env            # sesuaikan DB_*, JWT_SECRET, ADMIN_*
docker compose up -d --build    # php7.4-fpm + nginx + mysql8
docker compose exec app php index.php cli/migrate latest   # migrasi 001–004
docker compose exec app php index.php cli/seed run         # data demo idempoten
# buka http://localhost:8080  (default admin dari ADMIN_EMAIL/ADMIN_PASSWORD)
```

Test (butuh binary PHP; integration butuh DB yang sudah migrate+seed):

```bash
docker compose exec app vendor/bin/phpunit                 # semua
docker compose exec app vendor/bin/phpunit --testsuite Unit
```

## Status fitur

- **Phase 0–2 (selesai):** platform/RBAC/audit, master data, inventory (batch, FEFO, ledger immutable, opname/transfer/adjustment + workflow).
- **Phase 3 Procurement (baru):** supplier master + katalog & riwayat harga, PR → PO → GR (batch capture, inspeksi, put-away), retur pembelian, dan AP invoice foundation. Migrasi `004_procurement.php`. Lihat `erp-ci3/docs/90-phase-report.md`.

## Struktur `erp-ci3/`

Thin controller → Service (`Db::transaction`, State_machine, Workflow, Audit, Numbering) → Repository (Query Builder, `FOR UPDATE`, kolom eksplisit) → MySQL InnoDB. Reflection DI container. Lihat `erp-ci3/docs/00-architecture.md`.
