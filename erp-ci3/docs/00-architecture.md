# PharmaERP — Arsitektur Sistem (Phase 0)

> Stack tetap: PHP 7.4 · CodeIgniter 3.1.13 · MySQL 8 (InnoDB) · Monolithic Modular MVC · Repository · Service Layer · DI (reflection container).
> Status dokumen: v0.2 (Phase 0–2 delivered). Verifikasi regulasi: lihat `04-compliance-matrix.md`.

## 1. Executive Summary
PharmaERP adalah ERP + Inventory + Pharmacy Management untuk apotek retail dan perusahaan gabungan retail + distribusi (PBF) di Indonesia. Inti sistem adalah **inventory engine** yang transaksional, batch/expiry-aware, FEFO-by-default, dengan ledger immutable dan audit trail terpusat — fondasi untuk traceability Supplier → PO → GR → Batch → Gudang → Mutasi → SO → Invoice → Customer dan reverse-trace saat recall. Delivery bertahap (Phase 0–9); phase ini menghasilkan fondasi platform (auth, RBAC, audit, numbering, settings, workflow) dan inventory core yang **berjalan dan teruji** (21 automated tests, skenario end-to-end via HTTP).

## 2. Assumptions
| # | Asumsi | Dampak |
|---|---|---|
| A1 | Satu instalasi = satu `company` (multi-branch). Multi-company disiapkan di skema (`company_id` di semua tabel utama) namun UI single-company. | Konteks `Request_context.company_id` selalu dari user login. |
| A2 | Satuan stok = **satuan terkecil** (`base_uom`). Konversi UOM hanya di layer transaksi. | Tidak ada pembulatan stok di ledger. |
| A3 | Semua obat batch-tracked + expiry-tracked (default flag `1`); alkes non-obat bisa `0`. | FEFO otomatis hanya untuk produk batch-tracked. |
| A4 | Costing per perusahaan: `FIFO` (default, layer = batch) atau `AVERAGE` (moving average per baris saldo). Dikonfigurasi di `system_settings`. | Perubahan metode berlaku prospektif. |
| A5 | Pengiriman email/notifikasi = Phase 7 (notification engine). Reset password saat ini mencatat link ke log aplikasi (admin-assisted). | Tidak ada dependency SMTP di Phase 1. |
| A6 | Ketentuan regulasi (BPOM/CDOB/Kemenkes/pajak) **tidak** di-hard-code; diimplementasikan sebagai konfigurasi + flag; item yang belum diverifikasi diberi label `REGULATORY VERIFICATION REQUIRED`. | Lihat compliance matrix. |
| A7 | Waktu server WIB (`Asia/Jakarta`), disimpan `DATETIME` lokal; timezone dikonfigurasi. | Multi-zona (WITA/WIT) → Phase 9 hardening (opsional konversi UTC). |

## 3. Business Domain Map
```
┌──────────── PLATFORM ────────────┐  ┌────────── MASTER ──────────┐
│ auth · rbac · audit · settings   │  │ product · uom · category   │
│ numbering · workflow · notif     │  │ warehouse/location · party │
└───────────────┬──────────────────┘  └──────────────┬─────────────┘
                │                                     │
┌───────────────▼─────────────────────────────────────▼─────────────┐
│                     INVENTORY ENGINE (core)                        │
│ batches · balances · ledger(immutable) · movements · reservations  │
│ FEFO · costing · adjustment · transfer · opname · quarantine       │
└───┬───────────────┬────────────────┬────────────────┬──────────────┘
    │               │                │                │
┌───▼────┐   ┌──────▼─────┐   ┌──────▼──────┐  ┌──────▼───────┐
│PURCHASE│   │ SALES/POS  │   │ PHARMACY    │  │ DISTRIBUTION │
│PR·PO·GR│   │ cart·pay   │   │ Rx·dispense │  │ SO·pick·DO   │
│ret·AP  │   │ ret·shift  │   │ racikan     │  │ POD·AR       │
└───┬────┘   └──────┬─────┘   └──────┬──────┘  └──────┬───────┘
    └───────────────┴────────┬───────┴─────────────────┘
                     ┌───────▼────────┐   ┌──────────────────────┐
                     │ FINANCE/ACCTG  │   │ COMPLIANCE/QUALITY   │
                     │ GL·AP·AR·tax   │   │ recall·quarantine    │
                     │ journals·close │   │ temp log·CAPA·docs   │
                     └────────────────┘   └──────────────────────┘
                              REPORTING / BI · API · AUDIT
```

## 4. Full Module Map (kode permission = `module.resource.action`)
| Modul | Resource (Phase) | Catatan |
|---|---|---|
| system | dashboard, user, role, setting (P1) | RBAC, session, password policy, MFA-ready (`users.mfa_*`) |
| master | product, category, uom, warehouse, location, reason_code (P1/2); supplier, customer, doctor, pharmacist, price_list, promo… (P3–5) | Soft delete untuk master dengan referensi historis |
| inventory | stock, movement, batch, adjustment, transfer, opname (P2); reservation (P4/5) | Engine tunggal `Inventory_service::post()` |
| purchasing | pr, rfq, po, gr, purchase_return, vendor_invoice (P3) | |
| sales / pharmacy | pos, shift, customer, prescription, dispensing, compounding, sales_return (P4) | |
| distribution | so, allocation, picking, packing, do, shipment, pod, invoice (P5) | |
| finance / accounting / tax | cash_bank, ap, ar, journal, period, coa, tax_config (P6) | Accounting event dari transaksi operasional |
| compliance / quality | recall, quarantine, temperature_log, deviation, capa, document (P7) | Configurable rules |
| reporting | dashboards, reports, exports (P8) | server-side filter/paginate |
| audit | log, login_history (P1) | append-only |

## 5. Architecture (runtime)
```
Browser/POS ──HTTPS──▶ nginx ──▶ php-fpm 7.4 (CodeIgniter 3)
                                  │  hooks: Bootstrap(.env, autoload, tz) · SecurityHeaders
                                  │  routes → Controller (thin)
                                  │     ├─ Web_Controller  : session auth · CSRF · authorize() · handle()
                                  │     └─ Api_Controller  : JWT · rate-limit · envelope · error std
                                  ▼
                        Container (reflection DI) ──▶ Services (business rules, transactions)
                                                          │  Validators (centralized)
                                                          │  Workflow_service + State_machine (config)
                                                          │  Audit_service (append-only)
                                                          │  Numbering_service (FOR UPDATE)
                                                          ▼
                                                    Repositories (Query Builder, explicit columns, row locks)
                                                          ▼
                                                     MySQL 8 InnoDB (FK, UNIQUE, CHECK, indexes)
Cron/CLI: php index.php cli/{migrate,seed,health}   Logs: application/logs · nginx · mysql slow log
```
Prinsip: controller tidak memuat business logic dan tidak menyentuh DB; service tidak menulis SQL; repository tidak memutuskan aturan bisnis; view hanya menampilkan (escape via `e()`).

## 6. Folder Structure
```
application/
├── config/        config.php database.php routes.php hooks.php autoload.php migration.php erp.php (state machines, numbering, movement types)
├── core/          MY_Controller.php (MY_Controller · Web_Controller · Api_Controller) MY_Exceptions.php
├── hooks/         Bootstrap_hook.php Security_headers_hook.php
├── support/       Env Autoloader Container Db Request_context Paginator State_machine Fefo_allocator Password_policy Jwt Demo_seeder
├── exceptions/    Domain · Validation · Authorization · Authentication · Not_found · Conflict · Invalid_transition · Insufficient_stock · Persistence · Rate_limit
├── validators/    Base_validator User_validator Product_validator Stock_document_validator
├── repositories/  Base · User · Role · Auth · Audit · Setting · Sequence · Workflow · Company · Product · Master · Warehouse · Batch · Stock · Stock_document
├── services/      Auth · User · Role · Audit · Setting · Numbering · Rate_limit · Workflow · Product · Master · Inventory · Stock_adjustment · Stock_transfer · Stock_opname · Dashboard
├── controllers/   Auth Dashboard Users Roles Settings Audit_logs Products Product_categories Uoms Reason_codes Warehouses Batches Stock Stock_adjustments Stock_transfers Stock_opnames Errors
│   ├── api/v1/    Health Auth Products Stock
│   └── cli/       Migrate Seed Health Noop
├── migrations/    001_core_platform 002_master_data 003_inventory_core
├── helpers/       erp_helper.php (e, fmt_*, status_badge, can, csrf_field, old, sort_link)
├── language/indonesian/ erp_lang.php (+ CI system lang)
└── views/         layouts · partials · auth · dashboard · users · roles · settings · audit · products · master · inventory · errors
assets/css/erp.css · assets/js/erp.js · docker/ · docs/ · tests/{Unit,Integration}
```
Pemetaan ke "modules/" yang diminta dilakukan secara **logis melalui prefix nama kelas & permission** (mis. `Stock_*`, `inventory.*`) agar tetap kompatibel dengan autoloader CI3 tanpa HMVC pihak ketiga.

## 7. Database Architecture
- InnoDB, utf8mb4, FK pada semua relasi, UNIQUE untuk natural key, CHECK untuk domain nilai (MySQL 8 menegakkan CHECK).
- PK numeric auto-increment (BIGINT untuk tabel volume tinggi: ledger, movements, audit, batches, dokumen).
- Timestamps standar `created_at/updated_at`; `deleted_at` hanya di master yang direferensikan histori (users, products).
- **Immutable**: `stock_movements`, `stock_movement_items`, `stock_ledger`, `audit_logs`, `workflow_actions`, `login_history` — tidak ada path UPDATE/DELETE di aplikasi (kecuali flag `status=REVERSED` pada header movement). Rekomendasi: DB user aplikasi tanpa privilege DELETE pada tabel ini.
- Konkurensi: `SELECT … FOR UPDATE` pada `stock_balances` (urutan lock deterministik: warehouse→product→batch) dan `document_sequences`; optimistic lock `version` pada dokumen; UNIQUE `movement_no`, `adjustment_no`, dsb. mencegah double posting.
- Skala: indeks komposit sesuai pola query (`stock_ledger(product_id, warehouse_id, posted_at, id)`, `audit_logs(entity, entity_id)`, `(created_at)`); partisi RANGE by bulan untuk `stock_ledger`/`audit_logs` saat > 50 juta baris; arsip tahunan ke tabel `_archive`.

## 8. ERD (Phase 1–2, ringkas; detail di `01-data-dictionary.md`)
```
companies 1─* branches 1─* users *─* roles *─* permissions
users 1─* login_history, api_tokens, password_reset_tokens
companies 1─* system_settings, document_sequences, product_categories, products, warehouses, reason_codes, batches, stock_* docs
products *─1 uoms(base) ; products 1─* product_units *─1 uoms ; products *─1 drug_classifications
warehouses 1─* locations (self-ref parent) ; branches 1─* warehouses
products 1─* batches ; batches 1─* stock_balances *─1 warehouses/locations
stock_movements 1─* stock_movement_items 1─1 stock_ledger ; stock_movements 0..1─1 stock_movements (reversal_of)
stock_adjustments 1─* stock_adjustment_items ; stock_transfers 1─* stock_transfer_items ; stock_opnames 1─* stock_opname_items
stock_balances 1─* stock_reservations
workflow_instances 1─* workflow_actions ; (doc_type, doc_id) polymorphic → dokumen
audit_logs (entity, entity_id) polymorphic → semua entitas
```

## 9. Role & Permission Matrix (seed)
| Role | Ringkasan hak (Phase 1–2) |
|---|---|
| ADMIN (superadmin) | Semua |
| PHARMACIST | Semua *view*; karantina batch; buat/ubah penyesuaian & opname |
| CASHIER | Dashboard, lihat produk & stok |
| PURCHASING | Semua *view*; buat/ubah produk |
| WAREHOUSE | Semua *view*; buat/ubah penyesuaian, transfer (+kirim/terima), opname; karantina |
| SALES / DISTRIBUTION | View produk/stok; distribusi: kirim/terima transfer |
| FINANCE / ACCOUNTING / AUDITOR | Semua *view* + export |
| MANAGEMENT | Semua *view*; approve/post/cancel penyesuaian, transfer, opname; balik mutasi |
| COMPLIANCE | Semua *view*; karantina; balik mutasi |
Aturan tambahan: `workflow.enforce_segregation=1` → pembuat dokumen tidak boleh approve dokumennya sendiri (kecuali superadmin).

## 10. Core Workflows (state machine di `config/erp.php`)
- Penyesuaian: `DRAFT →submit→ SUBMITTED →approve→ APPROVED →post→ POSTED` (| reject → REJECTED | cancel → CANCELLED). Post = mutasi `ADJUSTMENT_IN/OUT`.
- Transfer: `DRAFT → SUBMITTED → APPROVED →ship→ IN_TRANSIT →receive→ RECEIVED`. Ship = `TRANSFER_OUT` (FEFO bila batch kosong); Receive = `TRANSFER_IN` dengan identitas batch dipertahankan.
- Opname: `DRAFT →start→ COUNTING →submit→ SUBMITTED →approve→ APPROVED →post→ POSTED`. Start = snapshot; post = `OPNAME_IN/OUT` selisih; guard: saldo sistem tak berubah sejak snapshot.
- Reversal: mutasi POSTED → compensating `REVERSAL` (+flag `REVERSED`), asli tidak diubah.
- Karantina batch: `QUARANTINE_IN/OUT` memindahkan kondisi GOOD↔QUARANTINE; batch.status ikut.

## 11. Compliance Architecture
Layer: (1) **Regulatory requirement** — hanya yang terverifikasi sumber resmi (lihat matrix); (2) **Business policy** — parameter `system_settings` (mis. FEFO allow expired = 0, near-expiry days); (3) **SOP internal** — reason codes, mandatory notes, segregation. Semua flag produk (Rx, controlled, cold-chain, golongan) adalah **atribut konfigurasi**, bukan keputusan klinis. Traceability tersedia melalui `stock_ledger.ref_type/ref_id/ref_no` + `batches.source_ref_*`.

## 12. API Architecture
`/api/v1/*`, JWT HS256 (access 15m, refresh 7d dengan rotasi & revokasi di `api_tokens`), rate limit per IP (`rate_limits`), envelope `{success, data, meta, request_id}` / `{success:false, error:{code,message,details}, request_id}`. Detail: `02-api-spec.md`. Idempotency-ready: dokumen memiliki UNIQUE number + `version`; header `Idempotency-Key` disiapkan untuk Phase 4 (POS).

## 13. Security Architecture
Argon2id/bcrypt hashing · lockout 5x/15m per IP+user · session DB driver, regenerate saat login, single-device (opsional), timeout · CSRF (web) · output escaping `e()` · Query Builder parameterized, tanpa `SELECT *` · authorization terpusat `authorize()` + `Workflow_service` per aksi · security headers + CSP · error tanpa stack trace di production (`MY_Exceptions`) · secrets di `.env` (tidak di repo) · audit append-only · API JWT + rate-limit. File upload (Phase 7 document management) akan memakai whitelist MIME + rename acak + penyimpanan di luar webroot (`storage/`).

## 14. Testing Architecture
Unit (pure: FEFO, state machine, JWT, password policy, numbering, paginator) · Integration (DB nyata: posting, negative stock, FEFO issue, reversal, numbering) · Feature/HTTP (skenario via curl/Playwright — didokumentasikan di `05-testing-uat.md`) · Security (lockout, permission denial, CSRF, invalid transition) · Regression: seluruh suite di CI sebelum merge.

## 15. Development Roadmap
| Phase | Scope | Status |
|---|---|---|
| 0 | Arsitektur, ERD, permission matrix, roadmap | ✅ |
| 1 | Struktur, env, auth, RBAC, audit, settings, numbering, base UI, workflow foundation | ✅ |
| 2 | Produk, gudang/lokasi, batch, expiry, ledger, mutasi, penyesuaian, transfer, opname, FEFO, karantina | ✅ |
| 3 | Supplier, PR, PO, GR (batch capture), purchase return, AP foundation | ⏭ next |
| 4 | POS, customer, resep, dispensing, racikan, sales return, payment | |
| 5 | SO, alokasi/reservasi, picking, packing, DO, POD, AR | |
| 6 | GL, AP, AR, kas/bank, jurnal otomatis, laporan keuangan, tax engine | |
| 7 | Recall & tracing, karantina lanjut, suhu, deviasi, CAPA, dokumen, notifikasi | |
| 8 | Dashboard per peran, laporan operasional/manajemen, export CSV/XLSX/PDF | |
| 9 | Security & performance audit, partisi/arsip, coverage, UAT, production readiness | |
