# Compliance Matrix (CDOB / BPOM / Kefarmasian / Pajak)

Prinsip: sistem **tidak mengarang** ketentuan hukum. Setiap baris diklasifikasikan: **R** = regulatory requirement (terverifikasi sumber resmi, tanggal dicatat), **P** = business policy (configurable), **S** = SOP internal (configurable). Item belum terverifikasi = `REGULATORY VERIFICATION REQUIRED (RVR)` dan diimplementasikan sebagai konfigurasi, bukan hard-coded legal truth.

## Sumber terverifikasi
| Ref | Sumber | Verifikasi | Catatan |
|---|---|---|---|
| CDOB-2025 | Peraturan BPOM No. 20 Tahun 2025 tentang Standar Cara Distribusi Obat yang Baik (mencabut PerBPOM 9/2019 & 6/2020). JDIH BPOM: https://jdih.pom.go.id ; siaran pers https://www.pom.go.id/siaran-pers/bpom-tetapkan-peraturan-terbaru-tentang-standar-cara-distribusi-obat-yang-baik ; subsite https://sertifikasicdob.pom.go.id | 17-06-2026 (web, sumber resmi BPOM) | Isi lampiran (12 bab) **belum dipetakan pasal-per-pasal** dalam dokumen ini → detail kewajiban = RVR sampai dibaca langsung dari teks lampiran. |

## Matrix kemampuan sistem ↔ aspek kepatuhan
| Aspek | Klas | Implementasi Phase 1–2 | Konfigurasi | Phase lanjut |
|---|---|---|---|---|
| Traceability batch (maju/mundur) | R (CDOB-2025, umum) / detail RVR | `batches.source_ref_*`, `stock_ledger.ref_*`, mutasi immutable, reversal | – | P3 GR→batch; P5 invoice→batch; P7 recall tracing UI/report |
| Batch & expiry control, FEFO | R umum / RVR detail | Flag produk, `Fefo_allocator`, blok pengeluaran batch ED (kecuali izin eksplisit) | `inventory.fefo_allow_expired`, `inventory.near_expiry_days` | P4/5 FEFO di POS/picking |
| Karantina / pemisahan produk | R umum | Kondisi `QUARANTINE` per saldo, gudang tipe QUARANTINE, lokasi karantina, `setBatchCondition` | reason codes QUARANTINE | P7 release decision & CAPA |
| Recall | R umum | Status batch `RECALLED` (skema), trace via ledger | – | P7 recall campaign, scope, affected customers/invoices/GR |
| Receiving records, supplier records | R umum | Skema `batches.supplier_id`, `received_at` | – | P3 supplier master, GR, inspeksi |
| Customer records (PBF ke sarana berizin) | RVR | – | – | P5 customer master dengan field izin (configurable, RVR) |
| Document control & approval | R umum | Workflow engine, state machine, `workflow_actions` | `erp_state_machines`, `workflow.enforce_segregation` | P7 controlled documents (versi, checksum) |
| Audit trail & user accountability | R umum | `audit_logs` append-only, `login_history`, IP/UA, reason | – | P9 DB privilege lock |
| Cold chain / suhu | R umum / RVR ambang | Flag `is_cold_chain`, suhu min/maks produk, gudang COLD | per produk | P7 temperature_logs, excursion, investigation |
| Deviasi / CAPA | R umum | Guard opname (stale snapshot) → conflict | – | P7 deviations, capa_actions |
| Complaint handling | R umum | – | – | P5/P7 complaints |
| Obat keras / OOT / psikotropika / narkotika | **RVR** (pelaporan, penyimpanan khusus, resep) | `drug_classifications` (requires_prescription, is_controlled) sebagai atribut; UI badge Rx/CTL | tabel referensi editable | P4 prescription validation rule engine (informational alert, tanpa keputusan klinis); P8 laporan per golongan (format RVR) |
| Resep & penyerahan oleh apoteker | RVR | Role PHARMACIST, permission granular | – | P4 pharmacist verification & double-check |
| Retensi dokumen | RVR | Tidak ada penghapusan histori; soft delete master | – | P9 kebijakan arsip sesuai masa retensi resmi (RVR) |
| Pajak (PPN, faktur) | RVR (tarif/aturan dapat berubah) | `products.tax_code`, `is_taxable`; tidak ada tarif hard-coded | – | P6 tax engine configurable, siap integrasi API resmi |

## Proses pembaruan regulasi
1. Identifikasi perubahan → catat sumber & tanggal di tabel "Sumber terverifikasi".
2. Petakan ke baris matrix: R/P/S. 3. Jika perlu perubahan perilaku → ubah **konfigurasi** (settings, reason codes, classification, state machine) — bukan core code. 4. Bila core code perlu berubah, buat ADR (`docs/adr/`) + test regresi.
