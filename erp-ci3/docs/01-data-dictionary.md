# Data Dictionary & ERD Detail (Phase 1–2)

Konvensi: PK `id` (INT/BIGINT UNSIGNED AI), FK bernama `fk_<tabel>_<kolom>`, UNIQUE `uq_*`, index `idx_*`. Semua tabel InnoDB utf8mb4_unicode_ci. Sumber kebenaran skema = `application/migrations/00{1,2,3}_*.php`.

## Core (001)
| Tabel | Kolom kunci | Relasi | Catatan |
|---|---|---|---|
| companies | code UQ, name, npwp | 1─* branches, users, roles… | |
| branches | (company_id, code) UQ, branch_type ENUM(HQ,RETAIL,DISTRIBUTION,WAREHOUSE), license_no | *─1 companies | license_no = konfigurasi (bukan validasi hukum) |
| users | username UQ, email UQ, password_hash, is_superadmin, is_active, must_change_password, password_changed_at, mfa_enabled, mfa_secret, current_session_id, deleted_at | *─1 companies, branches; *─* roles via user_roles | soft delete |
| roles | (company_id, code) UQ, is_system | *─* permissions via role_permissions | |
| permissions | code UQ = module.resource.action | | seeded oleh `Demo_seeder::seedPermissions` |
| role_permissions / user_roles | composite PK | many-to-many | user_roles.branch_id NULL = semua cabang |
| ci_sessions | id PK, timestamp idx | | CI session DB driver |
| login_history | user_id, status ENUM(SUCCESS,FAILED,LOCKED,LOGOUT,INACTIVE), channel, ip, ua | *─1 users | append-only |
| login_attempts | identifier PK (ip:username), attempts, locked_until | | brute-force |
| password_reset_tokens | token_hash UQ (sha256), expires_at, used_at | *─1 users | |
| api_tokens | jti UQ, expires_at, revoked_at | *─1 users | refresh token rotation |
| rate_limits | bucket PK, window_start, hits | | fixed window |
| system_settings | (company_id, setting_key) UQ, value_type ENUM(string,int,decimal,bool,json), is_editable | company_id NULL = global | override per perusahaan |
| document_sequences | (company_id, branch_id, doc_type, period_key) UQ, pattern, last_number | | dikunci FOR UPDATE |
| audit_logs | module, action, entity, entity_id, reference_no, old_values JSON, new_values JSON, reason, ip, ua, channel, request_id, created_at(3) | polymorphic | append-only; idx (entity,entity_id), (user_id,created_at), (created_at) |
| workflow_instances | (doc_type, doc_id) UQ, current_state, is_final | 1─* workflow_actions | |
| workflow_actions | instance_id, action, from_state, to_state, actor_id, notes, acted_at | *─1 users | riwayat approval |

## Master (002)
| Tabel | Kolom kunci | Relasi | Catatan |
|---|---|---|---|
| product_categories | (company_id, code) UQ, parent_id | self 1─* | kategori/subkategori |
| uoms | code UQ | | |
| drug_classifications | code UQ, requires_prescription, is_controlled | 1─* products | golongan obat: **REGULATORY VERIFICATION REQUIRED** untuk kewajiban per golongan |
| products | (company_id, sku) UQ, barcode idx, name idx, generic_name idx, base_uom_id FK, harga, margin, tax_code, stok min/max/ROP/safety, lead time, flag batch/expiry/serial/Rx/cold/controlled, suhu, status ENUM, deleted_at; CHECK harga ≥ 0 | *─1 categories(2x), classifications, uoms | |
| product_units | (product_id, uom_id) UQ, conversion_factor CHECK > 0, barcode, is_purchase/sales_uom, selling_price | *─1 products (CASCADE) | konversi UOM |
| warehouses | (company_id, code) UQ, warehouse_type ENUM(MAIN,RETAIL,TRANSIT,QUARANTINE,RETURN,COLD), is_cold_chain, allow_negative_stock | *─1 branches | |
| locations | (warehouse_id, code) UQ, location_type ENUM(ZONE,RACK,BIN), parent_id, is_default, is_quarantine | *─1 warehouses; self | |
| reason_codes | (company_id, reason_type, code) UQ, requires_note | | ADJUSTMENT/RETURN/CANCELLATION/QUARANTINE/REVERSAL/OPNAME |

## Inventory (003)
| Tabel | Kolom kunci | Relasi | Catatan |
|---|---|---|---|
| batches | (product_id, batch_no) UQ, manufacture_date, expiry_date idx, supplier_id (FK Phase 3), source_ref_type/id/no, received_at, unit_cost, status ENUM(ACTIVE,QUARANTINE,RECALLED,EXPIRED,DEPLETED) | *─1 products | traceability sumber |
| stock_balances | (warehouse_id, location_id, product_id, batch_id, condition_code) UQ, qty_on_hand, qty_reserved CHECK ≥0, qty_in_transit, avg_cost, version | *─1 warehouses, locations, products, batches | baris dikunci FOR UPDATE |
| stock_movements | movement_no UQ, movement_type, ref_type/id/no idx, status ENUM(POSTED,REVERSED), reversal_of_id FK self, reversed_by_id, reason_code_id, posted_by, posted_at(3) | 1─* items | immutable header |
| stock_movement_items | (movement_id, line_no) UQ, qty signed, unit_cost, condition | *─1 movements, products, batches | |
| stock_ledger | movement_item_id, qty_in, qty_out, balance_after, unit_cost, total_cost, ref_*, posted_at(3); idx (product_id, warehouse_id, posted_at, id), (batch_id, posted_at), (ref_type, ref_id) | 1─1 items | kartu stok append-only; kandidat partisi |
| stock_reservations | balance_id, qty CHECK > 0, ref_type/id, status ENUM(ACTIVE,CONSUMED,RELEASED), expires_at | *─1 balances | untuk SO/POS (Phase 4–5) |
| stock_adjustments (+_items) | adjustment_no UQ, status ENUM, reason_code_id FK, movement_id FK, version; items qty_change CHECK ≠ 0 | | |
| stock_transfers (+_items) | transfer_no UQ, from/to warehouse & branch, CHECK from≠to, status ENUM, out/in_movement_id, version; items qty_requested/shipped/received | | |
| stock_opnames (+_items) | opname_no UQ, opname_type ENUM(FULL,CYCLE), status ENUM, movement_id, version; items (opname_id, product, batch, location, condition) UQ, qty_system/counted/variance | | |

## Relationship types
- 1-to-1: `stock_movement_items` ↔ `stock_ledger` (satu baris ledger per item), `workflow_instances` ↔ dokumen (per doc_type/doc_id).
- 1-to-many: companies→branches→warehouses→locations; products→batches→stock_balances; documents→items; movements→items.
- many-to-many: users↔roles (`user_roles`), roles↔permissions (`role_permissions`).

## Indexing strategy (pola query nyata)
- Kartu stok per produk/gudang/periode → `idx_sl_product_wh_time`.
- Trace batch (recall) → `idx_sl_batch_time`, `idx_batches_source`, `idx_sm_ref`.
- Saldo per gudang/produk → `uq_sb_key` + `idx_sb_product_wh`.
- Audit per record → `idx_audit_entity`; per user/periode → `idx_audit_user_created`.
- Daftar dokumen per status → `idx_sa_status`, `idx_st_status`, `idx_so_status`.
- Pencarian produk POS → `idx_products_barcode` (exact), `idx_products_name`/`generic` (prefix LIKE).

## Phase 3 — Procurement (migrasi `004_procurement.php`)
Total menjadi 39 + 13 = **52 tabel** + `schema_migrations`. Semua InnoDB, utf8mb4.

- **suppliers** — master supplier/PBF. `uq_suppliers_company_code`. `supplier_type` ENUM(DISTRIBUTOR|MANUFACTURER|PBF|IMPORTER|OTHER), `payment_term_days`, `license_no` (izin PBF, konfigurasi), soft-delete. FK `batches.supplier_id → suppliers.id` ditambahkan di migrasi ini.
- **supplier_products** — katalog per supplier: `last_price`, `min_order_qty`, `lead_time_days`, `is_preferred`. `uq_sp_supplier_product`.
- **supplier_price_history** — append-only riwayat harga beli; `po_price` disimpan untuk deteksi varian GR≠PO. Index `idx_sph_supplier_product`.
- **purchase_requests / _items** — PR. Status DRAFT→SUBMITTED→APPROVED→CLOSED (+REJECTED/CANCELLED). `total_estimated`, versioned.
- **purchase_orders / _items** — PO. Status DRAFT→SUBMITTED→APPROVED→ORDERED→(PARTIAL|RECEIVED)→CLOSED. Header menyimpan `subtotal/discount_total/tax_total/grand_total`; item `qty_received` dilacak per baris. FK `pr_id`, `supplier_id`, `warehouse_id`.
- **goods_receipts / _items** — GR. Status DRAFT→SUBMITTED→APPROVED→POSTED→REVERSED. Item: `batch_no`+`expiry_date`+`manufacture_date` (capture), `location_id` (put-away), `condition_code`, `inspection_result` (ACCEPTED|QUARANTINE|REJECTED), `batch_id` diisi setelah posting. `movement_id`/`reversal_movement_id` → `stock_movements`.
- **purchase_returns / _items** — retur ke supplier via `ISSUE` + `reason_type=RETURN`. Status DRAFT→SUBMITTED→APPROVED→POSTED. Item pakai `batch_id` + `condition_code`.
- **ap_invoices / _items** — AP foundation, dibuat dari GR POSTED. Status DRAFT|OPEN|PARTIAL|PAID|CANCELLED. Pembayaran & posting GL → Phase 6.

### Indexing Phase 3
- Daftar dokumen per status → `idx_pr_status`, `idx_po_status`, `idx_gr_status`, `idx_prt_status`, `idx_ap_status`.
- Penerimaan per PO → `idx_gri_poitem`, `idx_gr_po`. Trace batch dari GR → `batches.source_ref_type/id` (`goods_receipts`).
- Riwayat harga per supplier/produk → `idx_sph_supplier_product`. AP jatuh tempo → `idx_ap_due`.
