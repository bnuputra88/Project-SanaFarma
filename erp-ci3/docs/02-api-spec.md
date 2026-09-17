# API Specification v1

Base: `/api/v1`. Auth: `Authorization: Bearer <access_token>` (HS256, TTL `JWT_ACCESS_TTL`). Rate limit: `API_RATE_LIMIT_PER_MINUTE` per IP (HTTP 429). Semua respons JSON UTF-8.

## Envelope
```json
{ "success": true, "data": ..., "meta": { "total": 120, "page": 1, "per_page": 25, "pages": 5 }, "request_id": "a1b2c3d4e5f60718" }
{ "success": false, "error": { "code": "VALIDATION|AUTHENTICATION|AUTHORIZATION|NOT_FOUND|CONFLICT|INVALID_TRANSITION|INSUFFICIENT_STOCK|RATE_LIMIT|INTERNAL_ERROR", "message": "…", "details": {"field":"pesan"} }, "request_id": "…" }
```
HTTP: 200/201 sukses · 401 auth · 403 izin · 404 · 409 konflik/transisi · 422 validasi/bisnis · 429 · 500.

## Endpoints
| Method | Path | Auth | Permission | Deskripsi |
|---|---|---|---|---|
| GET | /health | – | – | status app + DB (503 bila DB down) |
| POST | /auth/login | – | – | body `{identifier, password}` → `{user, access_token, refresh_token, token_type, expires_in}`; lockout 5x/15m |
| POST | /auth/refresh | – | – | body `{refresh_token}` → token baru (rotasi, token lama dicabut) |
| GET | /auth/me | Bearer | – | profil + permissions |
| GET | /products | Bearer | master.product.view | filter `q, category_id, status, requires_prescription, is_controlled, is_cold_chain`; `page, per_page, sort(sku|name|selling_price|qty_on_hand), dir` |
| GET | /products/{id} | Bearer | master.product.view | detail + `units` |
| GET | /stock/balances | Bearer | inventory.stock.view | filter `q, warehouse_id, product_id, condition_code, expiring_days, include_zero` |
| GET | /stock/fefo?product_id&warehouse_id&qty | Bearer | inventory.stock.view | `{allocations:[{batch_id,batch_no,location_id,qty,unit_cost,expiry_date,balance_id}], shortage}` |
| GET | /stock/ledger | Bearer | inventory.stock.view | filter `product_id, warehouse_id, batch_id, date_from, date_to` |
| GET | /stock/adjustments | Bearer | inventory.adjustment.view | daftar dokumen |
| POST | /stock/adjustments | Bearer | inventory.adjustment.create | body: `{warehouse_id, adjustment_date, reason_code_id, notes, items:[{product_id, batch_id?, location_id?, condition_code?, qty_change, unit_cost?, notes?}]}` → 201 dokumen DRAFT |
| POST | /stock/adjustments/{id}/{submit\|approve\|reject\|cancel\|post} | Bearer | per aksi (edit/approve/cancel/post) | body `{notes?}` (wajib untuk reject) → `{id, status}` |

## Endpoint Phase 3 — Procurement
Semua butuh Bearer token + permission `purchasing.*`.

| Method | Path | Permission | Keterangan |
|--------|------|------------|------------|
| GET | `/suppliers` | `purchasing.supplier.view` | List + filter `q`, `supplier_type`, `is_active`, paginasi. |
| POST | `/suppliers/create` | `purchasing.supplier.create` | Buat/simpan supplier (body JSON). |
| GET | `/suppliers/{id}` | `purchasing.supplier.view` | Detail + katalog + riwayat harga. |
| GET | `/suppliers/{id}/price-history` | `purchasing.supplier.view` | Riwayat harga (opsional `?product_id=`). |
| GET | `/purchase-orders` | `purchasing.po.view` | List PO. |
| POST | `/purchase-orders` | `purchasing.po.create` | Buat PO (DRAFT). Body: `supplier_id, warehouse_id, order_date, items[]`. |
| GET | `/purchase-orders/{id}` | `purchasing.po.view` | Detail PO + item + total. |
| POST | `/purchase-orders/{id}/{action}` | per aksi | `submit\|approve\|reject\|cancel\|order\|close`. |
| GET | `/goods-receipts` | `purchasing.gr.view` | List GR. |
| POST | `/goods-receipts` | `purchasing.gr.create` | Buat GR (DRAFT). Body: `supplier_id, warehouse_id, receipt_date, po_id?, items[]` (batch_no/expiry wajib untuk produk farmasi). |
| GET | `/goods-receipts/{id}` | `purchasing.gr.view` | Detail GR. |
| POST | `/goods-receipts/{id}/{action}` | per aksi | `submit\|approve\|reject\|cancel\|post\|reverse`. `post` → `RECEIPT` ke ledger; `reverse` ditolak (409 CONFLICT) bila stok sudah terpakai. |

## Contoh
```bash
curl -s -X POST $URL/api/v1/auth/login -H 'Content-Type: application/json' -d '{"identifier":"admin","password":"..."}'
curl -s $URL/api/v1/stock/fefo?product_id=2&warehouse_id=1&qty=1500 -H "Authorization: Bearer $TOKEN"
```

## Roadmap API
Phase 3: `/suppliers`, `/purchase-orders`, `/goods-receipts`; Phase 4: `/customers`, `/sales`, `/prescriptions` (+ `Idempotency-Key`); Phase 5: `/transfers`, `/deliveries`; Phase 6: `/invoices`, `/payments`; Phase 7: `/recalls`, `/compliance`; Phase 8: `/reports`. Versi baru → `/api/v2` tanpa memutus v1.
