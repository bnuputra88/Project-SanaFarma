# PRD — PharmaERP (ERP + Inventory + Pharmacy Management, Indonesia)

## Original problem statement (ringkas)
Bangun ERP farmasi enterprise-grade untuk apotek retail & perusahaan retail+distribusi (PBF) Indonesia dengan stack wajib PHP 7.4 · CodeIgniter 3 · MySQL 8 · Monolithic Modular MVC · Repository · Service Layer. Modul: platform/RBAC/audit, master data, inventory (batch/expiry/FEFO/ledger immutable), purchasing, sales/POS, pharmacy/dispensing, distribusi, returns, finance, accounting, tax (configurable), CRM, reporting, compliance CDOB/BPOM (tanpa mengarang regulasi), quality/cold chain, document mgmt, workflow engine, API, security, testing, seed, ops/DR, dokumentasi enterprise. Delivery phased (0–9). Dokumentasi & UI Bahasa Indonesia.

## User choices
- Stack tetap PHP 7.4 + CI3 + MySQL; kode di `/app/erp-ci3/` (tidak dapat di-preview di hosting Emergent; dijalankan di server sendiri / Docker).
- Delivery pertama: Phase 0 + 1 + 2. UI Bahasa Indonesia. Auth: JWT custom (API) + session (web), password policy, login history. Admin: bnuputra@gmail.com.

## Architecture (done)
Front controller → hooks (env, autoload, security headers) → thin controllers (Web/Api) → reflection DI Container → Services (transaksi via `Db::transaction`, State_machine, Workflow, Audit, Numbering) → Repositories (Query Builder, FOR UPDATE, explicit columns) → MySQL InnoDB. Views server-rendered (Bootstrap 5, IBM Plex, vanilla JS progressive enhancement, autocomplete produk/barcode, FEFO batch loader). Dokumen: `/app/erp-ci3/docs/`.

## Personas
Admin, Apoteker, Kasir, Purchasing, Gudang, Sales, Distribusi, Finance, Accounting, Manajemen, Auditor, Compliance/Quality (12 role seeded).

## Core requirements (static)
Correctness > Data Integrity > Security > Auditability > Regulatory Configurability > Maintainability > Performance > UI Polish. DB transaction untuk semua proses kritikal; tidak ada penghapusan histori; status transition eksplisit; tidak hard-code aturan regulasi/pajak.

## Implemented (2026-06-17) — Phase 0, 1, 2
- Platform: .env, DI container, exceptions, Web/Api base controllers, CSRF, security headers/CSP, session DB + regenerate + timeout + single-device, Argon2id/bcrypt, lockout, login history, reset token (hash), JWT access/refresh rotasi, rate limit, RBAC 52 permission × 12 role, audit append-only, settings typed, numbering FOR UPDATE, workflow engine + state machines config, migrasi CLI, seeder idempoten via service layer, health check CLI/API.
- Master: produk (flag farmasi, konversi UOM, harga/margin, stok param), kategori, UOM, golongan obat, gudang/lokasi (rak/bin/karantina), reason codes.
- Inventory: batches, balances (kondisi GOOD/DAMAGED/QUARANTINE/EXPIRED), movements immutable + ledger, reversal compensating, negative-stock prevention, FIFO/AVERAGE costing configurable, FEFO allocator (+API/AJAX preview), penyesuaian/transfer/opname dengan workflow + segregasi, karantina batch, kartu stok, buku besar, ED/near-ED, dashboard KPI.
- API v1: health, auth, products, stock (balances, fefo, ledger, adjustments + actions).
- Tests: 21 PHPUnit (unit+integration) OK; feature end-to-end via HTTP terverifikasi (lihat docs/05).
- Docs: arsitektur, data dictionary/ERD, API spec, deployment/backup/DR, compliance matrix (PerBPOM 20/2025 terverifikasi; area lain RVR), testing/UAT, phase report. Docker compose (php7.4-fpm, nginx, mysql8).

## Implemented (2026-06 add) — Phase 3 Procurement
- Migrasi `004_procurement` (13 tabel + FK `batches.supplier_id`; total 52 tabel). Version = 4.
- Supplier master + katalog (`supplier_products`) + riwayat harga append-only (`supplier_price_history`, deteksi varian GR≠PO).
- PR → PO → GR → Purchase Return, masing-masing dokumen dengan state machine + workflow + audit + numbering. GR posting lewat `Inventory_service::post('RECEIPT')` (batch capture, put-away lokasi, inspeksi GOOD/QUARANTINE/REJECTED). Aturan farmasi (batch+expiry wajib) ditegakkan di service layer. Reversal GR ditolak `Conflict_exception` bila stok sudah terpakai. Retur via `ISSUE`+reason RETURN.
- AP invoice foundation dari GR POSTED (pembayaran/GL → Phase 6).
- 22 permission `purchasing.*` (idempoten), grant PURCHASING/WAREHOUSE/MANAGEMENT/FINANCE. Web + API v1 (`suppliers`, `purchase-orders`, `goods-receipts`), sidebar "Pembelian", UI Bahasa Indonesia.
- Tests ditulis (Unit state machine + Integration GR) **belum dijalankan di sini**; semua file lulus `php -l`.

## Backlog (prioritized)
- ~~P0 (Phase 3): supplier master, PR→PO→GR, purchase return, AP foundation, supplier price history.~~ ✅ **SELESAI 2026-06 (lihat docs/90).**
- P0 (Phase 4): POS + shift kasir, customer/pasien, resep, dispensing, racikan, etiket, sales return, payment.
- P1 (Phase 5–6): SO/alokasi/picking/DO/POD/AR; GL/AP/AR/jurnal otomatis/period closing/tax engine.
- P1 (Phase 7): recall campaign & tracing, temperature logs, deviasi/CAPA, dokumen (object storage, checksum), notifikasi/email.
- P2 (Phase 8–9): laporan & export CSV/XLSX/PDF, dashboard per peran, partisi/arsip, security & performance audit, UAT, hardening DB privileges.
- Tech debt: jalankan test suite di PHP 7.4 nyata (saat ini dieksekusi di PHP 8.2 CLI); approval threshold/parallel approver; put-away location saat terima transfer; MFA implementasi (TOTP).

## Next tasks
1. Phase 4 POS/Dispensing (kasir, resep, dispensing, racikan, etiket, sales return, payment).
2. Jalankan test suite Phase 3 di PHP 7.4 nyata via Docker (`vendor/bin/phpunit`); wire AP ke pembayaran (Phase 6).
3. Tambah Feature test otomatis (Playwright/PHP HTTP) ke CI.
