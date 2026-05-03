# Tahap 2 - Master Data (Data Induk)
Tanggal: 2026-05-02
Status: DRAFT FINAL (siap implementasi)
Referensi: 2026-05-01c_konsep_inventori_fnb.md, 2026-05-01d_roadmap_pengembangan.md

---

## Tujuan Tahap 2

Membangun fondasi data induk untuk modul Inventory, POS, HR, dan Keuangan,
dengan desain yang mengikuti pola operasional di core namun dirapikan agar lebih
konsisten, mudah dikembangkan, dan aman terhadap perubahan harga bahan.

---

## Keputusan Kunci (hasil pembahasan)

1. Seed master UOM ambil dari core, bukan seed baru manual.
2. Kategori tidak digabung jadi satu tabel besar lintas rumpun.
3. Kategori Component dan Product wajib dipisah karena domain berbeda.
4. Untuk Product wajib ada master Division dan master Classification di atas Category.
5. Item adalah entitas induk; Material adalah subset detail dari Item.
6. Relasi Item-Material adalah many-to-one: beberapa item bisa merujuk ke satu material.
7. Istilah divisi dipisah tegas: product_division (BEVERAGE/FOOD) dan operational_division (BAR/KITCHEN/OFFICE).
7. Material perlu kolom hpp_standard sebagai acuan harga awal.
8. Component perlu division_id, hpp_standard, variable cost mode.
9. Product perlu description, hpp_standard, variable cost mode, mode stok, dan visibility multi-channel.
10. Upload foto produk disimpan lokal di uploads/products/ dan support JPG, JPEG, PNG, HEIC.
11. Konsep extra/add-on dipakai (menggantikan varian untuk saat ini), dengan master tabel sendiri.
12. Semua halaman wajib mobile-friendly, tabel striped + responsive, pagination size default 25, dan pencarian ajax.

---

## Lingkup Tahap 2

- 2A: UOM (master satuan + konversi)
- 2B: Kategori terpisah per rumpun + product division/classification
- 2C: Item (barang beli)
- 2D: Material (detail tambahan untuk item yang dipakai di resep)
- 2E: Component (base/prepare)
- 2F: Product (menu jual)
- 2G: Vendor
- 2H: Product Extra / Add-on

---

## 2A - UOM (Satuan Ukur)

### Keputusan
Seed data UOM diambil dari core agar kompatibel dengan data historis dan migrasi.

### Tabel

```sql
CREATE TABLE mst_uom (
    id          SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(10) NOT NULL UNIQUE,
    name        VARCHAR(50) NOT NULL,
    description VARCHAR(100) NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE mst_uom_conversion (
    id          SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_uom_id SMALLINT UNSIGNED NOT NULL,
    to_uom_id   SMALLINT UNSIGNED NOT NULL,
    factor      DECIMAL(12,6) NOT NULL,
    UNIQUE KEY uq_uom_conv (from_uom_id, to_uom_id),
    FOREIGN KEY (from_uom_id) REFERENCES mst_uom(id),
    FOREIGN KEY (to_uom_id) REFERENCES mst_uom(id)
);
```

### Sumber seed
- Ambil daftar UOM aktif dari core.
- Mapping code harus dipertahankan sama agar migrasi tidak pecah.

---

## 2B - Kategori dan Klasifikasi (dipisah per rumpun)

### Keputusan struktur

Karena domain berbeda, tabel kategori dipisah:

1. Kategori Item/Material: boleh satu rumpun (disarankan satu tabel) karena material adalah subset item.
2. Kategori Component: tabel sendiri.
3. Kategori Product: tabel sendiri.
4. Product wajib punya Division dan Classification sebagai level di atas kategori.

### Tabel kategori item/material

```sql
CREATE TABLE mst_item_category (
    id          SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(20) NOT NULL UNIQUE,
    name        VARCHAR(100) NOT NULL,
    parent_id   SMALLINT UNSIGNED NULL,
    sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (parent_id) REFERENCES mst_item_category(id)
);
```

### Tabel kategori component

```sql
CREATE TABLE mst_component_category (
    id          SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(20) NOT NULL UNIQUE,
    name        VARCHAR(100) NOT NULL,
    parent_id   SMALLINT UNSIGNED NULL,
    sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (parent_id) REFERENCES mst_component_category(id)
);
```

### Tabel product division, classification, category

```sql
CREATE TABLE mst_product_division (
    id          SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(20) NOT NULL UNIQUE,
    name        VARCHAR(100) NOT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE mst_product_classification (
    id          SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(20) NOT NULL UNIQUE,
    name        VARCHAR(100) NOT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE mst_product_category (
    id                SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code              VARCHAR(20) NOT NULL UNIQUE,
    name              VARCHAR(100) NOT NULL,
    division_id       SMALLINT UNSIGNED NOT NULL,
    classification_id SMALLINT UNSIGNED NOT NULL,
    parent_id         SMALLINT UNSIGNED NULL,
    sort_order        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    is_active         TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (division_id) REFERENCES mst_product_division(id),
    FOREIGN KEY (classification_id) REFERENCES mst_product_classification(id),
    FOREIGN KEY (parent_id) REFERENCES mst_product_category(id)
);

CREATE TABLE mst_operational_division (
    id          SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(20) NOT NULL UNIQUE, -- BAR, KITCHEN, OFFICE
    name        VARCHAR(100) NOT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1
);
```

### Aturan anti-ambigu divisi

- product_division: klasifikasi bisnis produk (contoh: BEVERAGE, FOOD).
- operational_division: unit operasional yang produksi/simpan stok (contoh: BAR, KITCHEN, OFFICE).
- Semua FK yang menyangkut stok, produksi, resep sumber bahan harus merujuk ke mst_operational_division.

### Sumber seed
- Master category, division, classification diambil dari core (sesuai tabel asal di core).
- Master operational division (BAR/KITCHEN/OFFICE/dll) disiapkan di tahap ini agar bisa dipakai lintas modul, termasuk HR/absensi di tahap berikutnya.

### Catatan pendapat
- Kategori item dan material bisa dianggap sama, dan ini paling efisien untuk operasional.
- Jika nanti butuh grouping khusus resep, cukup tambah field/tag di mst_material tanpa memecah kategori dasar.

---

## 2C - Item (induk barang beli)

### Konsep
Item adalah semua barang yang dibeli.
Material pasti item, item belum tentu material.
Beberapa item boleh merujuk ke material yang sama.

### Dua satuan pembelian yang wajib
1. Satuan packaging/beli (contoh: DUS, BOTOL, PACK)
2. Satuan isi/resep (contoh: PCS, ML, GRAM)

### Tabel

```sql
CREATE TABLE mst_item (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code               VARCHAR(20) NOT NULL UNIQUE,
    name               VARCHAR(150) NOT NULL,
    item_category_id   SMALLINT UNSIGNED NOT NULL,

    -- Satuan beli/packaging
    buy_uom_id         SMALLINT UNSIGNED NOT NULL,

    -- Satuan isi/resep
    content_uom_id     SMALLINT UNSIGNED NOT NULL,

    -- 1 buy_uom = berapa content_uom
    content_per_buy    DECIMAL(12,4) NOT NULL DEFAULT 1,

    min_stock_content  DECIMAL(12,2) NOT NULL DEFAULT 0,
    last_buy_price     DECIMAL(12,2) NULL,

    -- penanda item ini dipakai sebagai material
    is_material        TINYINT(1) NOT NULL DEFAULT 0,

    -- nullable: item bisa merujuk material generik yang sama
    material_id        INT UNSIGNED NULL,

    notes              TEXT NULL,
    is_active          TINYINT(1) NOT NULL DEFAULT 1,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (item_category_id) REFERENCES mst_item_category(id),
    FOREIGN KEY (buy_uom_id) REFERENCES mst_uom(id),
        FOREIGN KEY (content_uom_id) REFERENCES mst_uom(id)
);
```

```sql
-- Ditambahkan setelah mst_material dibuat
ALTER TABLE mst_item
    ADD CONSTRAINT fk_item_material
    FOREIGN KEY (material_id) REFERENCES mst_material(id);
```

---

## 2D - Material (master bahan baku generik)

### Keputusan relasi

Material menjadi master generik untuk kebutuhan resep/hpp.
Item adalah representasi barang beli; beberapa item bisa menunjuk ke 1 material yang sama.

Contoh:
- Material: GARAM
- Item: GARAM HALUS 500G, GARAM KASAR 1KG (keduanya material_id = GARAM)

### Tabel

```sql
CREATE TABLE mst_material (
    id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- optional grouping tambahan kalau nanti diperlukan
    material_code          VARCHAR(20) NOT NULL UNIQUE,
    material_name          VARCHAR(150) NOT NULL,
    item_category_id       SMALLINT UNSIGNED NULL,
    content_uom_id         SMALLINT UNSIGNED NOT NULL,

    -- HPP acuan awal (manual)
    hpp_standard           DECIMAL(12,2) NOT NULL DEFAULT 0,

    -- sifat material
    shelf_life_days        SMALLINT UNSIGNED NULL,
    reorder_level_content  DECIMAL(12,2) NULL,

    notes                  TEXT NULL,
    is_active              TINYINT(1) NOT NULL DEFAULT 1,
    created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (item_category_id) REFERENCES mst_item_category(id),
    FOREIGN KEY (content_uom_id) REFERENCES mst_uom(id)
);
```

### Guarding UI (wajib)

Pada form Item:
- Ada switch: Item ini material?
- Jika ON saat simpan:
    - Simpan ke mst_item
    - Wajib memilih material_id (existing) atau membuat material baru
    - is_material otomatis = 1
- Jika OFF:
  - Simpan ke mst_item saja
    - material_id wajib NULL
    - Jika sebelumnya sudah dipakai recipe/transaksi, blok perubahan atau minta proses migrasi data

### Jawaban kategori item vs material
- Karena material subset item, kategori dasar bisa sama lewat mst_item_category.
- Jika butuh klasifikasi khusus dapur/resep, tambah field atau tabel kecil tambahan khusus material, bukan pecah ulang kategori utama.

### Jawaban bridge vs nullable material_id
- Untuk kebutuhan many-item ke satu material, kolom material_id nullable di mst_item adalah skema paling sederhana dan cukup.
- Bridge table baru diperlukan hanya jika nanti 1 item perlu merujuk ke banyak material sekaligus (saat ini tidak diperlukan).

---

## 2E - Component (BASE/PREPARE)

### Keputusan
Component harus punya divisi pemilik produksi.
Component bisa dipakai lintas divisi di resep, tetapi divisi pembuatnya tetap satu.

### Tabel

```sql
CREATE TABLE mst_component (
    id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code                   VARCHAR(20) NOT NULL UNIQUE,
    name                   VARCHAR(150) NOT NULL,
    component_type         ENUM('BASE','PREPARE') NOT NULL,

    -- divisi operasional yang memproduksi
    operational_division_id SMALLINT UNSIGNED NOT NULL,

    component_category_id  SMALLINT UNSIGNED NOT NULL,
    uom_id                 SMALLINT UNSIGNED NOT NULL,

    -- output standar 1 batch resep
    yield_qty              DECIMAL(12,4) NOT NULL DEFAULT 1,

    -- biaya
    hpp_standard           DECIMAL(12,2) NOT NULL DEFAULT 0,
    variable_cost_mode     ENUM('DEFAULT','CUSTOM','NONE') NOT NULL DEFAULT 'DEFAULT',
    variable_cost_pct      DECIMAL(5,2) NULL,

    min_stock              DECIMAL(12,2) NOT NULL DEFAULT 0,
    description            TEXT NULL,
    is_active              TINYINT(1) NOT NULL DEFAULT 1,
    created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (operational_division_id) REFERENCES mst_operational_division(id),
    FOREIGN KEY (component_category_id) REFERENCES mst_component_category(id),
    FOREIGN KEY (uom_id) REFERENCES mst_uom(id)
);
```

```sql
CREATE TABLE mst_component_formula (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    component_id     INT UNSIGNED NOT NULL,
    line_type        ENUM('MATERIAL','COMPONENT') NOT NULL,
    material_item_id INT UNSIGNED NULL,
    sub_component_id INT UNSIGNED NULL,
    qty              DECIMAL(12,4) NOT NULL,
    uom_id           SMALLINT UNSIGNED NOT NULL,
    sort_order       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    notes            VARCHAR(200) NULL,
    FOREIGN KEY (component_id) REFERENCES mst_component(id),
    FOREIGN KEY (material_item_id) REFERENCES mst_item(id),
    FOREIGN KEY (sub_component_id) REFERENCES mst_component(id),
    FOREIGN KEY (uom_id) REFERENCES mst_uom(id)
);
```

### Skema variable cost

Default persen global disimpan di pengaturan (misal 20%).
Setiap component punya 3 mode:
- DEFAULT: pakai nilai global dari pengaturan
- CUSTOM: pakai variable_cost_pct di master component
- NONE: tanpa variable cost

### Skema produksi component (yang akan diadopsi dari core)

1. Mode SESUAI_RESEP:
   - User input jumlah batch (boleh pecahan)
   - Konsumsi bahan = formula x batch
   - Hasil jadi = yield_qty x batch

2. Mode ACUAN_BAHAN:
   - User pilih 1 bahan acuan dari line formula
   - User isi qty bahan acuan aktual
   - Sistem hitung multiplier terhadap qty resep bahan acuan
   - Semua line formula mengikuti multiplier
   - Hasil jadi ikut multiplier

Contoh:
- Resep 1 batch: 2 ekor bebek -> hasil 8 pack
- Input acuan bahan: 3 ekor
- Multiplier 1.5
- Hasil jadi = 12 pack, konsumsi line lain = 1.5x

---

## 2F - Product (menu jual)

### Keputusan

1. description wajib di master product.
2. hpp_standard wajib sebagai acuan awal.
3. variable cost mode wajib.
4. ketersediaan stok punya 3 mode.
5. visibility channel dipisah per channel.
6. image upload support JPG/JPEG/PNG/HEIC.

### Tabel

```sql
CREATE TABLE mst_product (
    id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code                   VARCHAR(20) NOT NULL UNIQUE,
    name                   VARCHAR(150) NOT NULL,

    -- divisi bisnis produk (Beverage/Food/dll)
    product_division_id    SMALLINT UNSIGNED NOT NULL,

    -- default divisi operasional sumber bahan untuk recipe
    default_operational_division_id SMALLINT UNSIGNED NOT NULL,

    classification_id      SMALLINT UNSIGNED NOT NULL,
    product_category_id    SMALLINT UNSIGNED NOT NULL,

    uom_id                 SMALLINT UNSIGNED NOT NULL,
    price                  DECIMAL(12,2) NOT NULL DEFAULT 0,

    description            TEXT NULL,

    hpp_standard           DECIMAL(12,2) NOT NULL DEFAULT 0,

    variable_cost_mode     ENUM('DEFAULT','CUSTOM','NONE') NOT NULL DEFAULT 'DEFAULT',
    variable_cost_pct      DECIMAL(5,2) NULL,

    stock_mode             ENUM('MANUAL_AVAILABLE','MANUAL_OUT','AUTO') NOT NULL DEFAULT 'AUTO',

    show_pos               TINYINT(1) NOT NULL DEFAULT 1,
    show_member            TINYINT(1) NOT NULL DEFAULT 0,
    show_landing           TINYINT(1) NOT NULL DEFAULT 0,

    image_path             VARCHAR(255) NULL,
    image_mime             VARCHAR(30) NULL,

    is_active              TINYINT(1) NOT NULL DEFAULT 1,
    created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (product_division_id) REFERENCES mst_product_division(id),
    FOREIGN KEY (default_operational_division_id) REFERENCES mst_operational_division(id),
    FOREIGN KEY (classification_id) REFERENCES mst_product_classification(id),
    FOREIGN KEY (product_category_id) REFERENCES mst_product_category(id),
    FOREIGN KEY (uom_id) REFERENCES mst_uom(id)
);
```

```sql
CREATE TABLE mst_product_recipe (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id       INT UNSIGNED NOT NULL,
    line_type        ENUM('MATERIAL','COMPONENT') NOT NULL,
    material_item_id INT UNSIGNED NULL,
    component_id     INT UNSIGNED NULL,
    source_division_id SMALLINT UNSIGNED NULL, -- FK ke operational division
    qty              DECIMAL(12,4) NOT NULL,
    uom_id           SMALLINT UNSIGNED NOT NULL,
    sort_order       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    notes            VARCHAR(200) NULL,
    FOREIGN KEY (product_id) REFERENCES mst_product(id),
    FOREIGN KEY (material_item_id) REFERENCES mst_item(id),
    FOREIGN KEY (component_id) REFERENCES mst_component(id),
    FOREIGN KEY (source_division_id) REFERENCES mst_operational_division(id),
    FOREIGN KEY (uom_id) REFERENCES mst_uom(id)
);
```

Aturan source_division_id:
- Berlaku untuk line_type MATERIAL maupun COMPONENT.
- Jika source_division_id kosong, sistem default ke mst_product.default_operational_division_id.
- Untuk COMPONENT, jika source_division_id diisi maka dianggap override sumber komponen lintas divisi.
- Untuk MATERIAL, source_division_id menentukan dari divisi mana FIFO layer material diambil.

### HPP live vs kolom hpp (event-driven, FIFO aware)

Rekomendasi:
- Jangan hitung ulang massal saat halaman dibuka.
- Simpan hpp_standard sebagai baseline manual.
- HPP live dihitung ulang di background saat ada transaksi stok/material/component yang memengaruhi biaya.
- Perhitungan HPP live harus memakai FIFO layer yang masih tersisa (remaining layer), bukan rata-rata sederhana.

Skema yang disarankan:

```sql
ALTER TABLE mst_product
    ADD hpp_live_cache DECIMAL(12,2) NULL,
    ADD hpp_live_at DATETIME NULL,
    ADD hpp_dirty TINYINT(1) NOT NULL DEFAULT 1;
```

```sql
CREATE TABLE cost_recalc_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    reason VARCHAR(50) NOT NULL,
    status ENUM('PENDING','PROCESSING','DONE','FAILED') NOT NULL DEFAULT 'PENDING',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    UNIQUE KEY uq_product_pending (product_id, status)
);
```

Event invalidasi (set hpp_dirty=1 + enqueue ke cost_recalc_queue):
1. Product recipe berubah.
2. Component formula berubah.
3. Transaksi stok material/component ter-posting dan mengubah FIFO layer aktif.
4. Penyesuaian harga yang memengaruhi valuation layer aktif.
5. Konfigurasi variable cost default berubah.

Flow background (lebih efisien):
1. Event transaksi menandai produk terdampak (bukan semua produk).
2. Worker background memproses antrean bertahap (batch kecil).
3. Worker hitung HPP live dari FIFO remaining layers sesuai source_division_id.
4. Simpan ke hpp_live_cache + hpp_live_at, lalu hpp_dirty=0.

Flow baca UI/POS:
1. Baca hpp_live_cache.
2. Jika masih dirty dan belum diproses, tetap tampilkan cache terakhir + indikator "updating".
3. Pada endpoint detail tertentu boleh ada fallback hitung sinkron untuk 1 produk jika dibutuhkan.

Catatan FIFO:
- Jika bahan/komponen yang sama punya banyak layer harga, perhitungan harus konsumsi layer tertua yang masih ada.
- Karena itu event utama untuk recalc adalah perubahan pada tabel layer stok (bukan sekadar master harga).

---

## 2G - Vendor

Tetap seperti rancangan awal, dengan seed/mapping dari core.

```sql
CREATE TABLE mst_vendor (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code          VARCHAR(20) NOT NULL UNIQUE,
    name          VARCHAR(150) NOT NULL,
    contact_name  VARCHAR(100) NULL,
    phone         VARCHAR(20) NULL,
    email         VARCHAR(100) NULL,
    address       TEXT NULL,
    city          VARCHAR(100) NULL,
    npwp          VARCHAR(30) NULL,
    payment_terms TINYINT UNSIGNED NOT NULL DEFAULT 0,
    notes         TEXT NULL,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

## 2H - Product Extra / Add-on (master wajib)

Karena operasional core memakai extra group, tahap 2 harus menyiapkan masternya.

### Tabel

```sql
CREATE TABLE mst_extra_group (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(20) NOT NULL UNIQUE,
    name            VARCHAR(100) NOT NULL,
    min_select      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_select      TINYINT UNSIGNED NOT NULL DEFAULT 1,
    is_required     TINYINT(1) NOT NULL DEFAULT 0,
    is_active       TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE mst_extra_option (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id        INT UNSIGNED NOT NULL,
    code            VARCHAR(20) NOT NULL,
    name            VARCHAR(100) NOT NULL,
    add_price       DECIMAL(12,2) NOT NULL DEFAULT 0,
    add_hpp         DECIMAL(12,2) NOT NULL DEFAULT 0,
    sort_order      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_group_code (group_id, code),
    FOREIGN KEY (group_id) REFERENCES mst_extra_group(id)
);

CREATE TABLE mst_product_extra_map (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id       INT UNSIGNED NOT NULL,
    extra_group_id   INT UNSIGNED NOT NULL,
    sort_order       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_product_group (product_id, extra_group_id),
    FOREIGN KEY (product_id) REFERENCES mst_product(id),
    FOREIGN KEY (extra_group_id) REFERENCES mst_extra_group(id)
);
```

### Catatan
Skema ini lebih efisien daripada membuat varian per produk satu per satu,
karena satu group extra bisa dipakai ulang di banyak produk.

---

## Standar UI/UX Wajib (semua modul Tahap 2)

1. Mobile-first responsive layout.
2. Tabel wajib striped dan responsive.
3. Pagination size selector:
   - default 25
   - opsi: 10, 25, 50, 100, ALL
4. Search ajax (server-side query), bukan full reload.
5. Form wajib punya validasi inline yang jelas.
6. Aksi penting pakai konfirmasi modal.
7. Semua halaman konsisten dengan tema Materio + warna merah/cream.

---

## Urutan Implementasi Teknis

1. Scan seed dan struktur master terkait dari core.
2. Finalisasi SQL schema Tahap 2 (single migration file).
3. Buat seed migrasi dari core untuk:
   - uom
   - item/material category
   - component category
   - product division/classification/category
4. Implement modul 2A -> 2H.
5. Tambah menu sidebar dan permission page.
6. Uji mobile layout dan performa search/pagination.

---

## Catatan Implementasi Penting

- Materi biaya (hpp_standard, variable cost) disimpan sebagai acuan bisnis.
- HPP live tidak perlu cron; hitung saat dibutuhkan dengan mekanisme cache ringan.
- Validasi konsistensi wajib:
  - item.is_material = 1 harus punya row di mst_material
  - item.is_material = 0 tidak boleh dipakai di recipe
- Support upload gambar produk:
  - ekstensi: .jpg, .jpeg, .png, .heic
  - simpan ke: uploads/products/

---

Setelah dokumen ini disetujui, langkah berikutnya adalah scan seed dari core
lalu membuat file SQL tahap 2 sesuai struktur final di atas.