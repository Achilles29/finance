# Tahap 6 - Purchase Foundation (PO + Katalog Profil Beli)
Tanggal: 2026-05-03
Status: IMPLEMENTATION READY (start execution)
Masuk Tahap: Tahap 6 - Pembelian (Purchase)
Referensi:
- docs/2026-05-01d_roadmap_pengembangan.md
- docs/2026-05-03e_penutupan_gate_tahap2_schema_snapshot.md
- sql/2026-05-03f_purchase_schema_foundation.sql

---

## Kenapa struktur ini dibuat

Tujuan tahap ini adalah membuat alur pembelian yang aman untuk operasi harian:
1. Purchase Order jelas dari request sampai penerimaan.
2. Perubahan kemasan vendor tidak merusak master item/material.
3. Histori harga dan profil beli bisa dipakai ulang sebagai katalog.
4. Data transaksi tetap audit-safe lewat snapshot profile.

---

## Ringkasan alur user (bahasa operasional)

1. Admin purchase buat PO, pilih tipe purchase, vendor, tujuan masuk.
2. Admin isi baris item dengan qty beli + satuan beli + isi per beli.
3. Sistem hitung qty isi dasar dan nilai subtotal.
4. Saat simpan baris, sistem menyimpan snapshot profile transaksi.
5. Sistem juga upsert katalog profile beli untuk referensi transaksi berikutnya.

Contoh:
- Hari ini: Sirup X dibeli 1 BOTOL = 750 ML.
- Bulan depan: jadi 1 BOTOL = 700 ML.
- Transaksi lama tetap 750 ML (histori aman), transaksi baru pakai 700 ML.

---

## Struktur tabel utama

## 1) mst_posting_type
Master dampak posting transaksi pembelian.

Fungsi:
- Menjadi kamus posting profesional (inventori/jasa/aset/payroll/beban).
- Menghindari hardcode ENUM dampak posting di banyak tempat aplikasi.

Kolom penting:
- type_code: kode posting (INVENTORY, SERVICE, ASSET, PAYROLL, EXPENSE)
- affects_*: penanda domain dampak

Masuk tahap: Tahap 6

---

## 2) mst_purchase_type
Master jenis purchase.

Fungsi:
- Menentukan posting type lewat relasi ke `mst_posting_type`.
- Menentukan apakah tujuan masuk wajib dipilih.

Kolom penting:
- type_code: kode jenis (contoh: INV_STOK, JASA, ASET)
- posting_type_id: relasi ke master dampak posting
- destination_behavior: REQUIRED atau NONE
- default_destination: tujuan default untuk inventory

Masuk tahap: Tahap 6

---

## 3) pur_purchase_order
Header dokumen PO.

Fungsi:
- Menyimpan identitas dokumen PO dan status proses.
- Menyimpan konteks tujuan masuk dan vendor.

Kolom penting:
- po_no
- request_date
- purchase_type_code
- destination_type, destination_division_id
- vendor_id
- status
- subtotal, tax_amount, grand_total

Masuk tahap: Tahap 6

---

## 4) pur_purchase_order_line
Detail item/baris PO.

Fungsi:
- Menyimpan transaksi actual per baris.
- Menyimpan snapshot profile agar histori tidak berubah.

Kolom penting:
- line_kind: ITEM/MATERIAL/SERVICE/ASSET
- item_id, material_id
- brand_name, line_description, notes
- qty_buy, buy_uom_id
- content_per_buy, qty_content, content_uom_id
- conversion_factor_to_content
- unit_price, line_subtotal
- snapshot_* (nama item/material, brand, keterangan, kode satuan)

Masuk tahap: Tahap 6

---

## 5) mst_purchase_catalog
Katalog profil beli hasil transaksi historis.

Fungsi:
- Menjadi referensi cepat saat input PO berikutnya.
- Menyimpan harga terakhir, brand, dan profil isi/kemasan.

Kolom penting:
- profile_key (unique hash)
- item_id, material_id, vendor_id
- buy_uom_id, content_uom_id, content_per_buy
- brand_name, line_description
- last_unit_price, last_purchase_date
- last_purchase_order_id, last_purchase_line_id

Masuk tahap: Tahap 6

---

## Kontrak snapshot (yang dibekukan di line)

Wajib dibekukan per baris transaksi:
1. item_id/material_id
2. qty_buy + buy_uom_id
3. content_per_buy + qty_content + content_uom_id
4. conversion_factor_to_content
5. unit_price + line_subtotal
6. brand_name + line_description (snapshot)
7. snapshot_item_name/snapshot_material_name
8. snapshot_buy_uom_code/snapshot_content_uom_code

Catatan penggunaan conversion_factor_to_content:
- Kasus standar: bisa sama dengan `content_per_buy` (qty_content = qty_buy x content_per_buy).
- Tetap disimpan eksplisit agar aman untuk kasus konversi non-standar, pembulatan, atau strategi migrasi/normalisasi lintas sumber data.

Aturan:
- Histori line tidak diubah langsung.
- Koreksi pakai reversal/new txn.
- Master update hanya berlaku untuk transaksi baru.

---

## Checklist implementasi Tahap 6 (batch foundation ini)

- [x] SQL fondasi purchase schema dibuat.
- [x] Master posting type + purchase type disiapkan dengan seed awal.
- [x] Header + line PO dengan snapshot profile disiapkan.
- [x] Katalog profile beli disiapkan.
- [x] Endpoint katalog purchase (search + ranking + fallback master) pada controller/model.
- [x] Upsert katalog otomatis dari proses simpan PO di aplikasi.
- [x] Fondasi payment purchase (`mst_payment_method`, `pur_purchase_payment_plan`) disiapkan.
- [x] Fondasi receipt PO untuk tujuan masuk gudang/divisi (`pur_purchase_receipt`, `pur_purchase_receipt_line`) disiapkan.
- [x] Fondasi akun perusahaan (termasuk CASH) + payment channel disiapkan (`fin_company_account`, `pur_payment_channel`).
- [x] Endpoint uji pembayaran purchase (potong saldo akun) disiapkan (`/purchase/payment/apply`).
- [x] Fondasi stok terdampak purchase disiapkan (`inv_warehouse_stock_balance`, `inv_division_stock_balance`).
- [x] Fondasi log transaksi stok + audit trail disiapkan (`inv_stock_movement_log`, `aud_transaction_log`).
- [ ] Integrasi posting ke inventori tahap 7.

---

## Batasan batch ini

Batch ini fokus di fondasi data (DDL + seed awal).
Belum termasuk:
- UI form purchase
- endpoint AJAX catalog
- business flow status engine di controller
- posting ledger inventori final
