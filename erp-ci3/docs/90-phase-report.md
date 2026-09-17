# Laporan Phase 0 · 1 · 2

## Phase 0 — Discovery & Architecture ✅
Deliverables: `docs/00-architecture.md` (exec summary, assumptions, domain/module map, arsitektur ASCII, folder structure, DB architecture, ERD, permission matrix, workflow, compliance, API, security, testing, roadmap), `01-data-dictionary.md`, `02-api-spec.md`, `03-deployment-backup-dr.md`, `04-compliance-matrix.md`, `05-testing-uat.md`.
Ambiguitas yang diidentifikasi & keputusan: lihat Assumptions A1–A7. Kebutuhan verifikasi regulasi: 1 sumber terverifikasi (PerBPOM 20/2025), 6 area RVR.

## Phase 1 — Foundation ✅
**Objective**: platform aman & auditable yang menjadi dasar semua modul.
**Files**: `index.php`, `composer.json`, `.env.example`, `application/config/*`, `core/MY_Controller.php`, `core/MY_Exceptions.php`, `hooks/*`, `support/{Env,Autoloader,Container,Db,Request_context,Paginator,State_machine,Password_policy,Jwt}`, `exceptions/*`, `validators/{Base,User}`, `repositories/{Base,User,Role,Auth,Audit,Setting,Sequence,Workflow,Company}`, `services/{Auth,User,Role,Audit,Setting,Numbering,Rate_limit,Workflow}`, `controllers/{Auth,Dashboard,Users,Roles,Settings,Audit_logs,Errors}`, `controllers/api/v1/{Health,Auth}`, `controllers/cli/{Migrate,Seed,Health,Noop}`, `support/Demo_seeder.php`, views `layouts/partials/auth/dashboard/users/roles/settings/audit/errors`, `assets/css/erp.css`, `assets/js/erp.js`, `helpers/erp_helper.php`, `language/indonesian/erp_lang.php`.
**Database**: migrasi `001_core_platform` (18 tabel).
**Routes**: `login, logout, password/forgot, password/reset/:token, profile/password, system/users*, system/roles*, system/settings*, system/login-history, audit*, api/v1/health, api/v1/auth/{login,refresh,me}, cli/*`.
**Tests**: Unit SecurityPrimitivesTest, StateMachineTest; Feature via curl (login, lockout, 403, CSRF).
**Known limitations**: email reset password belum dikirim (dicatat di log, Phase 7); MFA hanya skema; approval threshold/parallel approver belum (skema siap); UI single-company.

## Phase 2 — Inventory Core ✅
**Objective**: engine stok transaksional dengan batch/expiry, FEFO, ledger immutable, dokumen penyesuaian/transfer/opname.
**Files**: `validators/{Product,Stock_document}`, `repositories/{Product,Master,Warehouse,Batch,Stock,Stock_document}`, `services/{Product,Master,Inventory,Stock_adjustment,Stock_transfer,Stock_opname,Dashboard}`, `support/Fefo_allocator.php`, `controllers/{Products,Product_categories,Uoms,Reason_codes,Warehouses,Batches,Stock,Stock_adjustments,Stock_transfers,Stock_opnames}`, `controllers/api/v1/{Products,Stock}`, views `products/*`, `master/*`, `inventory/*`.
**Database**: `002_master_data` (8 tabel), `003_inventory_core` (13 tabel). Total 39 tabel + `schema_migrations`.
**Routes**: `master/{products,categories,uoms,warehouses,reason-codes}*`, `inventory/{stock,stock/card/:id,stock/ledger,stock/expiry,stock/fefo,movements,batches,adjustments,transfers,opnames}*`.
**API**: `GET products, products/:id, stock/balances, stock/fefo, stock/ledger, stock/adjustments; POST stock/adjustments, stock/adjustments/:id/:action`.
**Tests**: FefoAllocatorTest (5), InventoryPostingTest (5, DB); Feature end-to-end (lihat `05-testing-uat.md`). Hasil: **21/21 OK**.
**Known limitations**:
- Transfer: lokasi tujuan = default location gudang tujuan (pemilihan bin tujuan → Phase 5 put-away). Selisih qty diterima dicatat di `qty_received` namun stok masuk tetap sesuai qty dikirim (perlakuan selisih → deviasi Phase 7).
- Costing AVERAGE dihitung per baris saldo (gudang+batch); rata-rata per produk/gudang tersedia via `productAvgCost()`; laporan valuasi rinci → Phase 8.
- Stock reservations: tabel siap, konsumsi oleh SO/POS di Phase 4–5.
- Export CSV/XLSX/PDF, print etiket → Phase 8.
- Lint & run dilakukan di PHP 8.2 CLI (deprecation CI3 disembunyikan di development); jalankan di PHP 7.4 untuk paritas produksi.

## Next dependencies (Phase 3 — Procurement)
Supplier master (+ FK `batches.supplier_id`), PR/PO/GR dengan `Inventory_service::post('RECEIPT')` + batch capture (sudah teruji), inspeksi/karantina saat terima, purchase return via `ISSUE` + reason RETURN, AP foundation (`vendor_invoices`, `ap_ledger`), numbering `PR/PO/GR/PRT`, permission `purchasing.*`, state machines PO/GR di `config/erp.php`.

## Phase 3 — Procurement ✅
**Objective**: alur pengadaan lengkap dari permintaan sampai penerimaan stok & fondasi hutang, mengikuti pola Phase 2 (thin controller → service → repository, `Db::transaction`, State_machine, Workflow, Audit, Numbering) tanpa menulis ulang engine stok.
**Files**: `migrations/004_procurement.php`; `repositories/{Supplier,Supplier_product,Purchase_document,Ap_invoice}_repository.php`; `validators/Purchase_document_validator.php`; `services/{Supplier,Purchase_request,Purchase_order,Goods_receipt,Purchase_return,Ap_invoice}_service.php`; `controllers/{Suppliers,Purchase_requests,Purchase_orders,Goods_receipts,Purchase_returns,Ap_invoices}.php`; `controllers/api/v1/{Suppliers,Purchase_orders,Goods_receipts}.php`; views `purchasing/{suppliers,requests,orders,receipts,returns,ap_invoices}/*` + `_doc_list.php`.
**Database**: `004_procurement` (13 tabel: suppliers, supplier_products, supplier_price_history, purchase_requests + items, purchase_orders + items, goods_receipts + items, purchase_returns + items, ap_invoices + items) + FK `batches.supplier_id`. Total **52 tabel** + `schema_migrations`. Migrasi version = 4.
**State machines** (config/erp.php): `purchase_request`, `purchase_order`, `goods_receipt`, `purchase_return`. Numbering: `PURCHASE_REQ/PURCHASE_ORDER/GOODS_RECEIPT/PURCHASE_RETURN/AP_INVOICE`.
**RBAC**: 22 permission baru `purchasing.{supplier,pr,po,gr,return,ap}.*` (idempoten di seeder). Grant: PURCHASING (buat/ubah), WAREHOUSE (GR post), MANAGEMENT (approve/cancel/post), FINANCE (AP). ADMIN semua.
**Routes**: `purchasing/{suppliers,requests,orders,receipts,returns,ap-invoices}*`; API `api/v1/{suppliers,purchase-orders,goods-receipts}*`.
**Aturan bisnis kunci**:
- GR posting → `Inventory_service::post('RECEIPT')`: batch capture (batch_no/expiry/manufacture), put-away ke `location_id`, kondisi per hasil inspeksi (ACCEPTED→GOOD, QUARANTINE→QUARANTINE, REJECTED→tidak masuk stok). **Produk batch/expiry-tracked WAJIB batch+expiry — divalidasi di service layer**, bukan hanya form.
- Harga beli GR yang berbeda dari PO dicatat sebagai **varian** di `supplier_price_history` (`po_price` disimpan), tidak menimpa PO.
- GR posting menyinkronkan `qty_received` PO dan status PO (PARTIAL/RECEIVED).
- **Reversal GR ditolak `Conflict_exception` (409)** bila stok yang diterима sudah terpakai (FEFO sudah alokasi keluar) — bukan stok negatif.
- Purchase return → `ISSUE` + `reason_type=RETURN`; negative-stock dicegah engine.
- AP invoice foundation dibuat dari GR POSTED (idempoten, 1 GR = 1 AP). Pembayaran & posting GL → Phase 6.
**Tests**: `Unit/ProcurementStateMachineTest` (4), `Integration/GoodsReceiptTest` (3: posting+ledger+harga, wajib-batch farmasi, reversal ditolak saat stok terpakai). **Ditulis tetapi belum dijalankan di lingkungan ini** (tanpa install per instruksi); semua file lulus `php -l` (PHP 8.2 CLI). Jalankan `docker compose exec app vendor/bin/phpunit`.
**Known limitations**:
- AP belum ada pembayaran/aging/posting GL (Phase 6). `ap_invoices.amount_paid` disiapkan, belum dipakai.
- PO belum multi-currency nyata (kolom `currency` disimpan, konversi belum).
- Put-away GR menerima `location_id` manual (belum saran lokasi otomatis).
- Approval threshold/parallel approver masih single-step (skema workflow sudah siap).
