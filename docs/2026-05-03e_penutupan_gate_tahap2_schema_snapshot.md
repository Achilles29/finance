# Penutupan Gate Tahap 2 — Final Struktur Schema + Kontrak Snapshot Ledger
Tanggal: 2026-05-03
Status: FINAL (Dokumen Gate Tahap 2)
Masuk Tahap: Tahap 2 - Master Data (gate sebelum start Tahap 6 Purchase)
Referensi:
- docs/2026-05-02a_tahap2_master_data.md
- sql/2026-05-02a_master_data_schema.sql
- sql/2026-05-03b_inventory_item_to_material_flow_foundation.sql

---

## Tujuan Dokumen

Dokumen ini menutup 2 item gate Tahap 2:
1. Finalisasi struktur schema Tahap 2 sebagai fondasi tunggal.
2. Finalisasi kontrak snapshot profile untuk ledger inventori agar histori transaksi tidak berubah saat master berubah.

Dokumen ini bukan tahap baru. Ini bagian penutupan Tahap 2.

---

## A. Final Struktur Schema Tahap 2 (User-Friendly)

Struktur dibagi per rumpun agar mudah dipahami tim operasional dan tim teknis.

### A1. Master satuan
- mst_uom: daftar satuan (PCS, ML, GR, BOTOL, DUS, dll)
- mst_uom_conversion: konversi antar satuan

Fungsi bisnis:
- Menjadi dasar konversi UOM BELI ke UOM ISI.
- Menjaga konsistensi hitung qty isi, resep, dan stok.

### A2. Master kategori/divisi
- mst_item_category
- mst_component_category
- mst_product_division
- mst_product_classification
- mst_product_category
- mst_operational_division

Fungsi bisnis:
- Pisahkan domain produk (business) dan domain operasional (BAR/KITCHEN/OFFICE).
- Hindari ambigu saat posting stok dan pemakaian resep.

### A3. Master item-material
- mst_material: bahan baku generik untuk resep
- mst_item: barang yang dibeli dari vendor

Aturan inti:
- Item dan material tetap dipisah.
- Relasi many-to-one: banyak item bisa mengarah ke 1 material (via material_id di item).
- Buy UOM dan Content UOM disimpan di item.

### A4. Master component dan formula
- mst_component
- mst_component_formula

Fungsi bisnis:
- Menyimpan BASE/PREPARE beserta komposisi bahan.
- Menjaga struktur biaya dan stok komponen.

### A5. Master product dan recipe
- mst_product
- mst_product_recipe

Fungsi bisnis:
- Menyimpan produk jual, mode stok, mode variable cost, visibility channel.
- Menyimpan resep pemakaian material/component per produk.

### A6. Vendor
- mst_vendor
- mst_vendor_item

Fungsi bisnis:
- Menyimpan relasi supplier ke item dan histori harga supplier per item.

### A7. Extra/Add-on
- mst_extra
- mst_extra_group
- mst_extra_group_item
- mst_product_extra_map

Fungsi bisnis:
- Menyimpan opsi add-on terpusat per group dan mapping ke produk.

### A8. Fondasi alur item -> material
- inv_item_material_source_map
- inv_item_material_txn

Fungsi bisnis:
- Menjembatani distribusi/konversi stok dari item beli ke material resep.

---

## B. Kontrak Snapshot Profile Ledger Inventori (FINAL)

Prinsip utama:
- Setiap transaksi wajib menyimpan salinan nilai profile saat transaksi dibuat.
- Histori tidak boleh dihitung ulang dari master terbaru.
- Perubahan master setelah transaksi tidak boleh mengubah catatan lama.

### B1. Snapshot minimal yang wajib dibekukan

Field snapshot minimal (kontrak data):
1. item_id
2. material_id (jika ada)
3. buy_uom_id
4. content_uom_id
5. content_per_buy
6. qty_item (qty dalam buy uom)
7. qty_material atau qty_content (hasil konversi)
8. conversion_factor
9. unit_price_buy
10. hpp_per_content (jika relevan)
11. brand_name atau supplier profile label (jika dipakai)
12. ref_type
13. ref_id
14. trx_date
15. trx_no

Catatan:
- Jika ada kolom kemasan/packaging dan isi per pack pada transaksi, nilai itu juga wajib dibekukan sebagai snapshot.

### B2. Aturan konsistensi snapshot

1. Immutable transaction profile
- Nilai snapshot di baris transaksi tidak diubah setelah posting final, kecuali reversal/correction dengan jejak audit.

2. Reversal by new entry
- Koreksi histori dilakukan lewat transaksi pembalik, bukan edit langsung transaksi lama.

3. Derived value traceable
- qty_material dan conversion_factor harus bisa ditelusuri ke source map yang digunakan saat transaksi dibuat.

4. Master update safe
- Perubahan buy_uom/content_uom/content_per_buy di master hanya berlaku untuk transaksi baru.

### B3. Penerapan ke tabel saat ini

Sudah tersedia fondasi:
- inv_item_material_txn menyimpan qty_item, qty_material, conversion_factor, ref_type, ref_id, trx_date, trx_no.

Arah lanjutan Tahap 6-7:
- Tambah snapshot profile di transaksi purchase/warehouse bila belum lengkap.
- Samakan naming dan tipe agar lintas tabel mudah direkonsiliasi.

---

## C. Keputusan Stage

1. Dokumen ini masuk Tahap 2 (penutupan gate), bukan Tahap 6.
2. Setelah dua gate ini selesai, Tahap 6 boleh start eksekusi.
3. Tahap 3 tetap dapat berjalan paralel sesuai roadmap.

---

## D. Definisi Done Gate Tahap 2

Gate Tahap 2 dianggap selesai jika:
1. Struktur schema final terdokumentasi dan disepakati (dokumen ini).
2. Kontrak snapshot profile ledger terdokumentasi dan disepakati (dokumen ini).
3. File SQL acuan untuk Tahap 2 dan fondasi item-material sudah tersedia.

Jika ketiga poin di atas terpenuhi, status roadmap dapat dipindah menjadi:
- Tahap 2: Gate Closed
- Tahap 6: Start Ready/Running
