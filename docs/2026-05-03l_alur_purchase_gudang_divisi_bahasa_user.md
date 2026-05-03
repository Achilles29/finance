# Alur Purchase ke Gudang dan Divisi (Bahasa User)
Tanggal: 2026-05-03
Status: Reviewed (siap jadi pegangan implementasi)

---

## Tujuan alur ini

Alur ini dibuat supaya:
1. Purchase bisa masuk ke Gudang atau langsung ke Divisi.
2. Stok live selalu update dan konsisten.
3. Tampilan daily bulanan bisa cepat tanpa hitung ulang data mentah terus-menerus.
4. Kalau ada miss, data bisa rebuild dengan aman.

---

## Prinsip data (sederhana tapi aman)

1. Sumber kebenaran utama:
- Log mutasi stok (`inv_stock_movement_log`)

2. Posisi saat ini (cepat dibaca):
- Saldo live Gudang (`inv_warehouse_stock_balance`)
- Saldo live Divisi (`inv_division_stock_balance`)

3. Proyeksi tampilan harian bulanan:
- Stok awal bulan (`inv_stock_opening_snapshot`)
- Ringkasan harian gudang (`inv_warehouse_daily_rollup`)
- Ringkasan harian divisi (`inv_division_daily_rollup`)

Jadi bukan 3 versi logika berbeda, tapi 1 logika + 2 tampilan turunan terkontrol.

---

## Tabel purchase utama

## 1) Header & line purchase
- `pur_purchase_order`
- `pur_purchase_order_line`

Fungsi:
- Menyimpan dokumen PO, line item, qty beli, qty isi, harga, brand, keterangan, profile key.

## 2) Receipt purchase (tujuan masuk)
- `pur_purchase_receipt`
- `pur_purchase_receipt_line`

Fungsi:
- Menentukan barang diterima ke mana:
  - `destination_type = GUDANG` atau
  - `destination_type = BAR/KITCHEN/...` (divisi)

---

## Tabel stok terdampak purchase

## A. Stok live

1. `inv_warehouse_stock_balance`
- Saldo live stok gudang per profile.
- Simpan qty pack dan qty isi.
- Simpan rata-rata biaya isi (`avg_cost_per_content`).
- Simpan profil eksplisit (`profile_name`, `profile_brand`, `profile_content_per_buy`) selain `profile_key`.
- Simpan profil eksplisit lengkap selain `profile_key`:
  - `profile_name`, `profile_brand`, `profile_description`
  - `profile_content_per_buy`, `profile_buy_uom_code`, `profile_content_uom_code`

2. `inv_division_stock_balance`
- Saldo live stok divisi per profile.
- Simpan qty pack dan qty isi.
- Simpan rata-rata biaya isi (`avg_cost_per_content`).
- Simpan profil eksplisit (`profile_name`, `profile_brand`, `profile_content_per_buy`) selain `profile_key`.
- Simpan profil eksplisit lengkap selain `profile_key`:
  - `profile_name`, `profile_brand`, `profile_description`
  - `profile_content_per_buy`, `profile_buy_uom_code`, `profile_content_uom_code`

## B. Log mutasi utama

3. `inv_stock_movement_log`
- Jejak semua mutasi.
- Tipe mutasi:
  - `PURCHASE_IN`
  - `TRANSFER_IN`, `TRANSFER_OUT`
  - `USAGE_OUT`
  - `DISCARDED_OUT`, `SPOIL_OUT`, `WASTE_OUT`
  - `ADJUSTMENT`

Kolom penting:
- `movement_scope` (WAREHOUSE/DIVISION)
- `division_id`
- `item_id` / `material_id`
- `qty_buy_delta`, `qty_content_delta`
- `qty_buy_after`, `qty_content_after`
- `unit_cost`, `profile_key`
- `profile_name`, `profile_brand`, `profile_content_per_buy`
- `profile_name`, `profile_brand`, `profile_description`
- `profile_content_per_buy`, `profile_buy_uom_code`, `profile_content_uom_code`
- referensi transaksi (`ref_table`, `ref_id`, `receipt_id`, `receipt_line_id`)

## C. Tabel periodik minimal-v1

4. `inv_stock_opening_snapshot`
- Stok awal bulan.
- Bisa dari input manual, opname, atau hasil rebuild.

5. `inv_warehouse_daily_rollup`
- Ringkasan per hari per profile untuk gudang:
  - awal
  - masuk
  - keluar
  - terbuang/spoil/waste
  - penyesuaian
  - akhir
  - hpp rata-rata + nilai total

6. `inv_division_daily_rollup`
- Ringkasan per hari per profile untuk divisi:
  - awal
  - masuk
  - keluar
  - terbuang/spoil/waste
  - penyesuaian
  - akhir
  - hpp rata-rata + nilai total

Kedua tabel ini jadi sumber cepat untuk UI daily bulanan.

Aturan profile:
1. Jika profile baru, buat baris baru.
2. Jika profile sama, update baris yang sama (upsert), bukan tambah baris.

---

## Alur proses: Purchase -> Gudang

1. User buat dan approve PO.
2. User terima barang via receipt dengan `destination_type = GUDANG`.
3. Sistem simpan `pur_purchase_receipt` dan `pur_purchase_receipt_line`.
4. Sistem posting ke `inv_warehouse_stock_balance` (qty & cost update).
5. Sistem tulis `inv_stock_movement_log` dengan `movement_type = PURCHASE_IN` dan scope WAREHOUSE.
6. Sistem update/siapkan data `inv_warehouse_daily_rollup` (langsung atau via rebuild batch).

Hasil yang user lihat:
- Stok akhir gudang naik
- HPP rata-rata ter-update
- Nilai total stok ter-update
- Jejak audit mutasi tersedia

---

## Alur proses: Purchase -> Divisi

1. User buat receipt dengan tujuan divisi (`destination_type = BAR/KITCHEN/...`, `destination_division_id` terisi).
2. Sistem simpan receipt + receipt line.
3. Sistem posting ke `inv_division_stock_balance` untuk divisi tujuan.
4. Sistem tulis `inv_stock_movement_log` dengan scope DIVISION dan `movement_type = PURCHASE_IN`.
5. Sistem update/siapkan `inv_division_daily_rollup`.

Hasil yang user lihat:
- Stok divisi tujuan naik
- HPP dan nilai stok divisi ikut ter-update
- Jejak mutasi tetap auditable

---

## Rebuild kalau ada miss (wajib ada)

Skema rebuild yang aman:
1. Pilih bulan + scope (Gudang/Divisi).
2. Ambil opening snapshot bulan tersebut.
3. Replay `inv_stock_movement_log` per hari, per profile.
4. Tulis ulang tabel daily sesuai scope:
  - gudang -> `inv_warehouse_daily_rollup`
  - divisi -> `inv_division_daily_rollup`
5. Rekonsiliasi ke saldo live bila diperlukan.

Dengan ini, kalau ada data miss atau perbaikan histori, layar bisa distabilkan lagi tanpa bongkar total data.

---

## Kaitan dengan akun pembayaran

Agar sejalan purchase end-to-end:
1. Akun perusahaan berasal dari:
- import core (`core.m_bank_account`, `core.pos_payment_method`) ke `fin_company_account`.

2. Payment channel purchase:
- `pur_payment_channel` mengarah ke `fin_company_account` (termasuk akun type CASH).

3. Apply payment:
- kurangi saldo akun di `fin_company_account`
- tulis jejak audit di `aud_transaction_log`

---

## Ringkas keputusan

Untuk kebutuhan Anda sekarang, model ini sudah efisien:
1. Tidak terlalu banyak tabel turunan.
2. Tetap aman audit.
3. Tetap bisa rebuild.
4. Siap untuk tampilan daily bulanan seperti UI core, tapi dengan risiko inkonsistensi yang lebih rendah.
