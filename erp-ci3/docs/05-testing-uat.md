# Testing Strategy, Results & UAT Checklist

## Strategi
| Lapisan | Alat | Cakupan | Perintah |
|---|---|---|---|
| Unit | PHPUnit 9 (tanpa DB) | FEFO, state machine, JWT, password policy, numbering, paginator | `vendor/bin/phpunit --testsuite Unit` |
| Integration/Database | PHPUnit + CI boot (`tests/bootstrap.php::ci_boot`) + DB testing | posting, negative stock, FEFO issue, reversal, numbering | `vendor/bin/phpunit --testsuite Integration` (butuh DB termigrasi+seed) |
| Feature/API/Security | curl / Playwright (skrip di bawah) | login, lockout, RBAC 403, CSRF, validasi 422, transisi 409, workflow end-to-end | manual/CI job |
| Permission | seed roles + `authorize()` | setiap controller action dilindungi | tercakup Feature |
| Regression | seluruh suite | sebelum merge & sebelum rilis | `composer test` |

## Hasil eksekusi (17-06-2026, PHP 8.2 CLI + MariaDB lokal; target produksi PHP 7.4 + MySQL 8)
- `php -l` seluruh file: **OK**. Migrasi 001–003: **OK** (39 tabel). Seed: **OK** (52 permission, 12 role, 9 user, 4 gudang, 10 produk, 15 baris saldo awal).
- PHPUnit: **OK (21 tests, 47 assertions)**.
- Feature (HTTP): semua halaman web 200 tanpa PHP/DB error; API health, login, refresh token, `me`; adjustment create→submit→approve→post (stok 5000→4950); transisi ulang → 409; negative stock → 422 `INSUFFICIENT_STOCK`; kasir POST adjustment → 403; validasi → 422 detail per field; lockout setelah 5 gagal; transfer 1500 AMX FEFO memilih batch ED terdekat (1200 dari AMX2403X, 300 dari AMX2410Y) → ship → receive (saldo tujuan bertambah, batch identitas dipertahankan); opname snapshot→count→post (3 baris OPNAME_OUT −2) → reversal mengembalikan saldo dan menandai movement REVERSED; audit_logs & workflow_actions bertambah.

## Acceptance scenario mapping (spek §28)
| # | Skenario | Status |
|---|---|---|
| 1 | Create product | ✅ UI/API |
| 2 | Create supplier | ⏭ Phase 3 |
| 3–5 | PO, receive goods, capture batch & expiry | ⏭ Phase 3 (engine `RECEIPT` + batch capture sudah teruji di integration test) |
| 6 | Stock increases | ✅ |
| 7–9 | Sell product, FEFO picks correct batch, stock decreases | ✅ FEFO+ISSUE di engine & transfer; POS ⏭ Phase 4 |
| 10–12 | Accounting event, invoice, payment | ⏭ Phase 4–6 |
| 13–14 | Customer return, stock reversal | ✅ reversal engine; return docs ⏭ Phase 4 |
| 15–16 | Recall batch, affected customers/invoices | ⏭ Phase 7 (karantina batch ✅) |
| 17 | Audit trail available | ✅ |

## UAT Checklist (Phase 1–2)
- [ ] Login benar/salah, lockout, logout, sesi timeout, single-device.
- [ ] Ganti password mengikuti kebijakan di Parameter.
- [ ] Buat user + role; login sebagai user tersebut; menu & aksi sesuai permission; aksi terlarang → 403 / pesan.
- [ ] Buat produk (batch+expiry), kategori, UOM, konversi; cari via nama/SKU/barcode.
- [ ] Buat gudang + lokasi; default location.
- [ ] Penyesuaian: draft → ajukan → setujui (oleh user berbeda bila segregasi aktif) → posting; cek saldo, kartu stok, mutasi, audit.
- [ ] Penyesuaian minus melebihi stok → ditolak dengan pesan jelas.
- [ ] Transfer tanpa batch → FEFO memilih ED terdekat saat kirim; terima di tujuan; saldo kedua gudang benar.
- [ ] Opname: mulai hitung (snapshot), isi hitung, ajukan, setujui, posting selisih; ubah stok di tengah → posting ditolak (stale).
- [ ] Balik mutasi → mutasi asli REVERSED, saldo kembali, alasan tercatat.
- [ ] Karantina batch → saldo pindah ke kondisi QUARANTINE, tidak masuk FEFO; lepas kembali.
- [ ] Dashboard KPI (nilai persediaan, ED, mendekati ED, pending) konsisten dengan data.
- [ ] Audit trail: filter modul/entity/tanggal; detail old/new; login history.
- [ ] API: login/refresh/me; balances; fefo; adjustments create+actions; envelope & kode error.

## Skrip Playwright (contoh untuk QA)
```python
await page.goto(URL+"/login"); await page.fill('[data-testid=login-identifier]','admin'); await page.fill('[data-testid=login-password]',PWD); await page.click('[data-testid=login-submit]')
await page.goto(URL+"/inventory/adjustments/create"); await page.select_option('[data-testid=adj-warehouse]','1'); await page.select_option('[data-testid=adj-reason]',{'index':1})
await page.fill('[data-testid=adj-item-product-0]','para'); await page.keyboard.press('Enter'); await page.fill('[data-testid=adj-item-qty-0]','-10'); await page.click('[data-testid=adj-save]')
await page.click('[data-testid=wf-action-submit]'); await page.click('[data-testid=wf-action-approve]'); await page.click('[data-testid=wf-action-post]'); await page.click('[data-testid=confirm-ok]')
```
Semua elemen interaktif memiliki `data-testid` (lihat views).
