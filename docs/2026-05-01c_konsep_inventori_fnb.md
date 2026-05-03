# Konsep Inventori FnB — Material, Component, Item, Product
**Tanggal:** 2026-05-01  
**Diperbarui:** 2026-05-01 (revisi keputusan material vs item, tambah hierarki component)  
**Status:** FINAL — berlaku untuk semua pengembangan di `finance`

## Update Sinkronisasi (2026-05-03)

1. `source_division_id` pada `mst_product_recipe` ditetapkan tetap mengacu ke `mst_operational_division`.
  Alasan: jalur konsumsi stok bahan baku harus konsisten dengan lokasi operasional (bar/kitchen/operasional), bukan divisi produk.

2. Skema relasi Extra dipastikan berpusat di Group Extra.
  Implementasi UI sekarang menggunakan checklist produk di halaman Group Extra, sehingga mapping massal lebih efisien.

3. Standar tampilan angka desimal ditetapkan 2 digit untuk UI.
  Presisi data di database tetap mengikuti kebutuhan kalkulasi (lebih dari 2 digit jika diperlukan).

4. Penegasan model UOM pembelian: UOM BELI dan UOM ISI dipisah secara fungsional.
  - UOM BELI = satuan transaksi pembelian (contoh: BOTOL, DUS, PACK).
  - UOM ISI = satuan dasar konsumsi resep/stok (contoh: ML, GR, PCS).
  - Perubahan kemasan vendor tidak boleh mengubah identitas master item/material.

5. Acuan dari `core`: profil pembelian disimpan di katalog purchase, bukan hanya di master item.
  - Ditemukan tabel `pur_purchase_catalog` dan proses upsert dari transaksi purchase order.
  - Variasi merk + isi per unit + satuan isi + harga terakhir disimpan sebagai profil historis pembelian.

---

## Latar Belakang

Di `core`, ada 3 entitas yang sering membingungkan: `m_item`, `m_material`, `m_component`. Dokumen ini mengklarifikasi konsep masing-masing, memvalidasi pemahaman bisnis FnB-nya, dan menetapkan rancangan final untuk `finance`.

---

## Konsep di `core` (Existing)

| Entitas | Definisi | Tabel |
|---|---|---|
| **Item** | Semua barang yang bisa dibeli (superset) | `m_item` |
| **Material** | Bahan baku (subset dari item, digunakan dalam resep) | `m_material` |
| **Component** | Bahan setengah jadi — base/prepare (dibuat dari material, digunakan dalam resep produk) | `m_component` |
| **Product** | Produk yang dijual ke pelanggan | `m_product` |

**Hierarki di `core`:**
```
Item (semua yang dibeli)
  └── Material (bahan baku) ─── digunakan dalam ──► Formula Component
                                                           │
Component (prepare/base) ◄──────────────────────────────────
  └── digunakan dalam ──► Recipe Product
                                │
Product (dijual ke customer) ◄──┘
```

---

## Validasi Konsep (Perspektif FnB)

Konsep di `core` **sudah benar secara bisnis**. Ini adalah standar hierarki produksi FnB:

1. **Item** = procurement item — apapun yang dibeli dari vendor (bahan baku, peralatan, biaya non-inventory)
2. **Material/Raw Material** = ingredient — subset item yang masuk ke dapur/produksi
3. **Component/Semi-finished** = base/prepare — dibuat di dapur dari beberapa material (contoh: espresso base, simple syrup, roti tawar untuk sandwich)
4. **Product/Menu Item** = produk jual — dibuat dari material dan/atau component

**Contoh nyata:**
- Beli `Susu UHT 1L` (Item) → dipakai di resep → ini juga `Material`
- Beli `Lap Meja` (Item) → bukan material, tidak masuk produksi
- Buat `Espresso 30ml` dari biji kopi → ini `Component` (prepare)
- `Cappuccino` terdiri dari: Espresso (Component) + Susu (Material) → ini `Product`

---

## Masalah di `core`

Meskipun konsepnya benar, **implementasinya bermasalah**:

1. `m_item` dan `m_material` adalah **2 tabel terpisah** yang tidak terhubung FK sama sekali
   - Bahan baku harus diinput 2 kali (sebagai item untuk PO, sebagai material untuk resep)
   - Tidak ada jaminan satu-ke-satu antara item dan material

2. Stok item dan stok material dikelola dengan mekanisme berbeda dan terpisah:
   - Item: `inv_warehouse_*`
   - Material: `rsp_material_stock_*` (prefix `rsp_` tidak intuitif sama sekali)

3. Tidak ada mekanisme yang menghubungkan "item apa yang bisa digunakan sebagai material tertentu"

---

## Keputusan Final — `mst_item` dan `mst_material` TETAP DIPISAH ✅

### Alasan (kasus nyata dari operasional)

> Di resep, bahan baku yang digunakan adalah **"BEBEK"**. Tapi saat belanja, yang dibeli bisa **"BEBEK PEKING"** atau **"ITIK"** — dua item berbeda dengan harga dan supplier berbeda.

Jika `m_item` dan `m_material` digabung, maka:
- "BEBEK PEKING" dan "ITIK" akan punya ID berbeda di tabel yang sama
- Resep akan ambigu: pakai ID mana?
- Stok bahan baku untuk "BEBEK" tersebar di dua ID item berbeda

**Solusi: tabel penghubung `mst_material_item_source`**

```
mst_material "BEBEK" (id=5)
    ├── bisa dipasok oleh mst_item "BEBEK PEKING" (id=10)  ← default
    └── bisa dipasok oleh mst_item "ITIK"         (id=11)
```

### Konsekuensi desain

- **Resep** selalu mengacu ke `mst_material.id` — stabil, tidak berubah walau supplier berganti
- **Pembelian (PO)** mengacu ke `mst_item.id` — spesifik sesuai barang yang dibeli
- **Saat distribusi** dari gudang ke dapur: item X masuk sebagai material Y (via mapping source)
- **Laporan stok bahan baku** menampilkan "BEBEK" — bukan "BEBEK PEKING" vs "ITIK" terpisah

---

## Rancangan Final Hierarki `finance`

### Entitas dan Fungsinya

| Entitas | Tabel | Definisi | Sumber |
|---|---|---|---|
| **Item** | `mst_item` | Semua barang yang dibeli dari vendor | Dari PO |
| **Material** | `mst_material` | Bahan baku dalam resep (nama generik) | Diinput manual |
| **Component** | `mst_component` | Bahan setengah jadi (BASE/PREPARE) | Diproduksi |
| **Product** | `mst_product` | Produk yang dijual ke pelanggan | Dijual via POS |

### Tabel Penghubung Kunci

```
mst_material_item_source
  ├── material_id  → mst_material.id
  ├── item_id      → mst_item.id
  └── is_default   → item mana yang jadi pilihan utama
```

### Hierarki Produksi Lengkap

```
mst_item (dibeli dari vendor)
  └── via mst_material_item_source
         ↓
mst_material (bahan baku, nama generik untuk resep)
  ├── digunakan di formula mst_component (BASE)
  ├── digunakan di formula mst_component (PREPARE)
  └── digunakan langsung di recipe mst_product

mst_component TYPE=BASE (base/dasar)
  ├── dibuat dari: mst_material, mst_component(BASE) lain
  ├── digunakan di formula mst_component(BASE) lain
  ├── digunakan di formula mst_component(PREPARE)
  └── digunakan langsung di recipe mst_product

mst_component TYPE=PREPARE (siap pakai/semi-finished)
  ├── dibuat dari: mst_material, mst_component(BASE), mst_component(PREPARE) lain
  ├── digunakan di formula mst_component(PREPARE) lain
  └── digunakan langsung di recipe mst_product

mst_product (dijual ke customer via POS)
  └── recipe terdiri dari: mst_material, mst_component(BASE), mst_component(PREPARE)
```

### Contoh Nyata Kafe

| Bahan | Tipe | Dibuat dari | Dipakai di |
|---|---|---|---|
| Biji Kopi Arabica | `mst_item` | — (dibeli) | — |
| Biji Kopi Robusta | `mst_item` | — (dibeli) | — |
| Kopi (generic) | `mst_material` | source: Arabica atau Robusta | Formula Espresso |
| Susu UHT | `mst_item` & `mst_material` | dibeli, source: diri sendiri | Recipe langsung |
| Simple Syrup | `mst_component` BASE | Gula + Air | Banyak formula |
| Espresso 30ml | `mst_component` BASE | Kopi + Air | Banyak recipe |
| Matcha Latte Base | `mst_component` PREPARE | Matcha + Susu + Simple Syrup | Recipe produk |
| Iced Matcha Latte | `mst_product` | Matcha Latte Base + Es Batu + Susu | POS |

---

## Hierarki Component: BASE vs PREPARE

### BASE
- Komponen paling dasar yang dibuat di dapur
- **Dibuat dari:** Material + BASE lain
- **Digunakan di:** BASE lain, PREPARE, atau langsung di resep produk
- Contoh: Espresso Shot, Simple Syrup, Brown Sugar Syrup, Santan Kental

### PREPARE
- Komponen siap pakai yang sudah "setengah jadi" untuk produk tertentu
- **Dibuat dari:** Material + BASE + PREPARE lain
- **Digunakan di:** PREPARE lain atau langsung di resep produk
- Contoh: Matcha Latte Base, Taro Mix, Smoothie Base Mangga

### Aturan validasi formula (di aplikasi)

| Parent | Boleh memakai |
|---|---|
| BASE | Material, BASE |
| PREPARE | Material, BASE, PREPARE |
| PRODUCT (recipe) | Material, BASE, PREPARE |

---

## Inventori — 3 Layer Stok (Tetap Dipisah)

Pemisahan ini **disengaja** agar tidak membingungkan:

| Layer | Nama | Prefix Tabel | Isi | Yang Melihat |
|---|---|---|---|---|
| **Layer 1** | Gudang | `inv_warehouse_*` | Stok item dari PO, belum terdistribusi | Admin gudang |
| **Layer 2** | Stok Bahan Baku | `inv_material_*` | Bahan baku yang sudah di divisi (dapur/bar) | Chef, Barista, Admin produksi |
| **Layer 3** | Stok Komponen | `prd_component_*` | Base/Prepare yang sudah dibuat | Chef, Barista |

### Alur Stok

```
[Vendor] → PO diterima → inv_warehouse (Gudang)
                               ↓ distribusi ke divisi
                         inv_material (Stok Bahan Baku di Dapur/Bar)
                               ↓ digunakan untuk produksi komponen
                         prd_component (Stok Base/Prepare)
                               ↓ digunakan untuk order POS
                         [Terjual / Terpakai]
```

**Catatan penting:** Saat distribusi dari gudang ke dapur, item X dikonversi ke material Y menggunakan mapping `mst_material_item_source`. Jika distribusi "ITIK 5kg" ke dapur, maka stok material "BEBEK" +5kg.

---

## Tabel yang Perlu Dibuat (Ringkasan Final)

```sql
-- Master Satuan
mst_uom                      -- satuan ukur (kg, liter, pcs, dll.)
mst_uom_conversion           -- konversi antar satuan

-- Master Barang & Bahan
mst_item                     -- semua barang yang dibeli (item_type: INVENTORY/NON_INV/ASSET/SERVICE)
mst_item_uom_pack            -- konfigurasi satuan pack (1 dus = 12 pcs)
mst_material                 -- bahan baku (nama generik untuk resep)
mst_material_category        -- kategori bahan baku
mst_material_item_source     -- mapping: material ← item (satu material bisa dari beberapa item)
mst_purchase_catalog         -- katalog profil pembelian (snapshot kemasan/harga/referensi)

-- Master Komponen & Produk
mst_component                -- bahan setengah jadi (component_type: BASE/PREPARE)
mst_component_category       -- kategori komponen
mst_component_formula        -- formula komponen (line: material_id atau component_id)
mst_product                  -- produk jual
mst_product_category         -- kategori produk
mst_product_recipe           -- resep produk (line: material_id atau component_id)

-- Master Lainnya
mst_vendor                   -- vendor/supplier
mst_bank_account             -- rekening bank perusahaan

-- Inventori Gudang
inv_warehouse_opening        -- opening balance gudang per periode
inv_warehouse_ledger         -- ledger transaksi gudang (masuk/keluar/transfer)
inv_warehouse_balance        -- saldo gudang (summary per item)

-- Inventori Material (Stok Bahan Baku di Divisi)
inv_material_opening         -- opening balance bahan baku per divisi
inv_material_txn             -- transaksi stok bahan baku
inv_material_balance         -- saldo bahan baku (summary per material per divisi)
inv_material_lot             -- lot tracking bahan baku

-- Inventori Komponen (Stok Base/Prepare)
prd_component_opening        -- opening balance komponen
prd_component_txn            -- transaksi stok komponen
prd_component_balance        -- saldo komponen
prd_component_lot            -- lot tracking komponen
prd_product_batch            -- batch produksi produk
```

---

## Keputusan UOM Beli vs UOM Isi (Final)

### Definisi

| Istilah | Fungsi | Contoh |
|---|---|---|
| UOM BELI | Satuan transaksi beli | BOTOL, DUS, PACK |
| QTY BELI | Jumlah unit UOM BELI | 3 DUS |
| ISI PER UNIT | Konversi 1 UOM BELI ke satuan isi | 1 BOTOL = 1000 |
| UOM ISI | Satuan dasar konsumsi resep/stok | ML, GR, PCS |

Contoh: beli 4 BOTOL sirup, tiap botol 750 ML.
- QTY BELI = 4
- UOM BELI = BOTOL
- ISI PER UNIT = 750
- UOM ISI = ML
- Total isi dasar = 4 x 750 = 3000 ML

### Jika UOM BELI Berubah

Kasus: vendor sebelumnya jual per BOTOL, lalu berubah jadi DUS isi 12 BOTOL.

Keputusan:
1. Master item/material tetap sama (tidak ganti entitas).
2. Profil beli baru disimpan sebagai entri katalog purchase baru.
3. Histori harga dan histori kemasan tetap terlacak per profil.
4. Konversi ke UOM ISI tetap menjadi sumber kebenaran untuk stok konsumsi resep.

### Implikasi Desain Profesional (Finance)

1. Master item menyimpan identitas barang inti, bukan snapshot semua variasi beli.
2. Variasi pembelian dikelola di katalog purchase (per profil transaksi).
3. Ledger inventori menyimpan snapshot profil pack agar audit historis tidak berubah saat master diperbarui.
4. UI purchase wajib menampilkan konteks pack profile (merk, isi per unit, satuan isi, harga terakhir).

---

## Catatan Migrasi dari `core`

| Tabel `core` | Target `finance` | Transformasi |
|---|---|---|
| `m_item` | `mst_item` | Tambah `item_type` field |
| `m_material` | `mst_material` | Rename prefix, clean data |
| `m_material_category` | `mst_material_category` | Rename prefix |
| Relasi item-material di `core` (implicit) | `mst_material_item_source` | Buat mapping berdasarkan nama/kode yang sama |
| `m_component` | `mst_component` | Rename prefix, tambah `component_type` (BASE/PREPARE) |
| `m_component_formula` | `mst_component_formula` | Rename prefix, ubah FK ke `mst_material` |
| `m_product` | `mst_product` | Rename prefix |
| `m_product_recipe` | `mst_product_recipe` | Rename prefix, ubah FK ke `mst_material` |
| `rsp_material_stock_*` | `inv_material_*` | Rename prefix (rsp_ → inv_), bersihkan data |
| `inv_warehouse_*` | `inv_warehouse_*` | Strukturnya sudah cukup baik, sesuaikan kolom |
