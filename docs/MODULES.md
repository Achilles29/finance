# Panduan Modul — Finance App
**Terakhir diperbarui:** 2026-05-18

> Dokumen ini menjelaskan setiap modul: tujuan, tabel kunci, alur bisnis, controller/model/view yang terlibat, dan catatan penting. Dibaca saat mulai mengerjakan atau melanjutkan modul tertentu.

---

## DAFTAR ISI

1. [Auth & RBAC](#1-auth--rbac)
2. [Master Data](#2-master-data)
3. [HR & Organisasi](#3-hr--organisasi)
4. [Absensi](#4-absensi)
5. [Payroll & Penggajian](#5-payroll--penggajian)
6. [Pembelian (Purchase)](#6-pembelian-purchase)
7. [Inventori & Gudang](#7-inventori--gudang)
8. [Produksi & COGS](#8-produksi--cogs)
9. [POS](#9-pos)
10. [Keuangan & Akuntansi](#10-keuangan--akuntansi)

---

## 1. Auth & RBAC

### Tujuan
Fondasi login, manajemen user, permission, dan navigasi sidebar.

### File Kunci
| Tipe | File |
|---|---|
| Controller | `application/controllers/Auth.php` |
| Controller | `application/controllers/Users.php` |
| Controller | `application/controllers/Roles.php` |
| Controller | `application/controllers/Sidebar.php` |
| Model | `application/models/Auth_model.php` |
| Model | `application/models/Menu_model.php` |
| Core | `application/core/MY_Controller.php` |
| View | `application/views/auth/login.php` |
| View | `application/views/layout/sidebar.php` |

### Tabel Kunci
```
auth_user                  — akun login (username, password_hash, employee_id)
auth_session_log           — log kapan user login/logout
auth_role                  — role (Admin, Kasir, Manajer, dll.)
auth_permission            — daftar halaman yang bisa diizinkan
auth_role_permission       — mapping role → permission + aksi (view/create/edit/delete)
auth_user_role             — mapping user → role
auth_user_permission_override — override per user (tambah/kurangi dari role)
sys_menu                   — pohon menu sidebar
sys_menu_permission        — menu mana yang butuh permission apa
sys_sidebar_favorite       — item favorit per user
sys_audit_log              — log akses (opsional, ditambahkan bertahap)
```

### Alur Login
1. User POST username + password → `Auth::login()`
2. `Auth_model::attempt_login()` → query `auth_user`, verifikasi bcrypt
3. Jika berhasil: set session (`user_id`, `username`, `role_ids`, `perms`)
4. Load permission user → simpan ke session cache
5. Redirect ke dashboard

### Alur Permission (MY_Controller)
1. Setiap request → `MY_Controller::__construct()` → `_check_auth()` → cek session
2. Controller method panggil `require_permission('kode.halaman', 'aksi')`
3. MY_Controller cek `$this->user_perms` (dari session)
4. Jika tidak ada izin → redirect 403 atau `jsonError(403)`

### Sidebar Dual Portal
- **Company Portal** (`sidebar_main`): menu operasional, hanya tampil jika ada izin
- **Employee Portal** (`sidebar_my`): menu pribadi, semua karyawan bisa akses
- Switch portal via link di bagian bawah sidebar
- `active_menu` di controller menentukan menu mana yang di-highlight

### Catatan Penting
- Permission di-cache 300 detik di session (MD5 hash user+perms). Jika ada perubahan role, user harus logout-login.
- `is_superadmin()` bypass semua cek permission — hanya untuk akun admin sistem.
- Kode permission format: `'{modul}.{sub}'` atau `'{modul}.{sub}.{aksi}'` (lihat CODING_STANDARDS.md)

---

## 2. Master Data

### Tujuan
Semua data referensi yang dipakai modul lain: barang, bahan baku, produk, vendor, satuan, dll.

### File Kunci
| Tipe | File |
|---|---|
| Controller | `application/controllers/Master.php` |
| Controller | `application/controllers/Master_relation.php` |
| Model | `application/models/Master_model.php` |
| View | `application/views/master/` (folder) |

### Tabel Kunci
```
mst_uom                    — satuan ukur (kg, liter, pcs, ml, dll.)
mst_uom_conversion         — konversi antar satuan
mst_item                   — semua barang yang dibeli dari vendor
mst_item_uom_pack          — konfigurasi kemasan (1 DUS = 12 PCS)
mst_material               — bahan baku (nama generik untuk resep)
mst_material_category      — kategori bahan baku
mst_material_item_source   — mapping: material ← item (sumber pengadaan)
mst_purchase_catalog       — profil pembelian historis per item/vendor/kemasan
mst_component              — bahan setengah jadi (type: BASE / PREPARE)
mst_component_category     — kategori komponen
mst_component_formula      — formula komponen (bahan apa saja)
mst_product                — produk yang dijual di POS
mst_product_category       — kategori produk
mst_product_recipe         — resep produk (bahan apa saja)
mst_product_extra_group    — grup extra/topping
mst_product_extra_item     — item extra dalam grup
mst_vendor                 — vendor / supplier
mst_bank                   — master bank (BCA, Mandiri, dll.)
mst_payment_method         — metode pembayaran (tunai, QRIS, transfer, dll.)
mst_operational_division   — divisi operasional (dapur, bar, kasir, dll.)
org_position               — jabatan karyawan
```

### Hierarki Barang (WAJIB DIPAHAMI)

```
Vendor → PO menggunakan mst_item
mst_item → dikirim ke mst_material via mst_material_item_source
mst_material → dipakai di formula mst_component
mst_component → dipakai di resep mst_product
mst_product → dijual di POS
```

**Prinsip utama:**
- `mst_item` = identitas beli (spesifik: merk, kemasan)
- `mst_material` = identitas resep (generik: "Susu", "Kopi")
- Satu material bisa punya banyak item sumber (misal: "Susu" bisa dari "Susu Ultramilk" atau "Susu Greenfields")
- Resep selalu pakai `material_id` — tidak pernah `item_id` langsung

**UOM BELI vs UOM ISI:**
- UOM BELI = satuan transaksi (BOTOL, DUS)
- UOM ISI = satuan resep/stok (ML, GR, PCS)
- `mst_purchase_catalog` menyimpan konversi: 1 BOTOL = 750 ML

### Catatan Penting
- Controller `Master.php` menggunakan pola polymorphic — satu controller untuk semua jenis master data via URL `master/{type}`
- Jangan gabung `mst_item` dan `mst_material` — ini keputusan desain yang disengaja (lihat `2026-05-01c_konsep_inventori_fnb.md`)
- `mst_purchase_catalog` diperbarui otomatis saat ada PO disimpan (upsert)

---

## 3. HR & Organisasi

### Tujuan
Manajemen data karyawan, jabatan, divisi, dan kontrak kerja.

### File Kunci
| Tipe | File |
|---|---|
| Controller | `application/controllers/Master.php` (untuk employee, divisi, jabatan) |
| Controller | `application/controllers/Hr_contracts.php` |
| Controller | `application/controllers/Hr_contract_verify.php` |
| Model | `application/models/Hr_contract_model.php` |
| View | `application/views/master/org-employee*.php` |
| View | `application/views/hr_contract/` |

### Tabel Kunci
```
org_employee               — data karyawan (NIP, nama, jabatan, divisi, status)
org_position               — jabatan (posisi)
org_division               — divisi / departemen
org_employee_bank_account  — rekening bank karyawan (untuk transfer gaji)
org_contract_template      — template kontrak kerja
org_contract               — kontrak aktual per karyawan
org_contract_log           — log perubahan status kontrak
```

### Format NIP
`EMP-{YYYY}{MM}{NNN}` → contoh: `EMP-2026050001`  
NIP di-generate otomatis saat karyawan dibuat.

### Lifecycle Kontrak
```
DRAFT → APPROVED → SIGNED → EXPIRED / TERMINATED
```
- Template kontrak menggunakan placeholder yang diganti dengan data karyawan
- Kontrak bisa dicetak PDF + QR code untuk verifikasi keaslian
- Verifikasi QR: `hr-contract/verify/{token}` → endpoint publik (tanpa login)

### Catatan Penting
- Data karyawan adalah anchor untuk hampir semua modul: absensi, payroll, kontrak, portal My
- Jika karyawan di-nonaktifkan (`status = 'INACTIVE'`), mereka tidak bisa login tapi data historis tetap ada
- Divisi di HR (`org_division`) berbeda dari divisi operasional inventory (`mst_operational_division`)

---

## 4. Absensi

### Tujuan
Pencatatan dan rekapitulasi absensi harian karyawan.

### File Kunci
| Tipe | File |
|---|---|
| Controller | `application/controllers/Attendance.php` |
| Model | `application/models/Attendance_model.php` |
| View | `application/views/attendance/` |

### Tabel Kunci
```
att_shift              — definisi shift kerja (jam masuk, jam keluar, toleransi)
att_shift_assignment   — penugasan shift per karyawan per periode
att_holiday            — hari libur nasional + cuti bersama
att_location           — lokasi absensi (dengan koordinat GPS jika ada)
att_presence           — raw log clock in / clock out dari device/manual
att_daily              — REKAPITULASI HARIAN (single source of truth payroll)
att_request            — pengajuan izin/sakit/cuti dari karyawan
att_request_approval   — approval per level
att_ph_balance         — saldo hak PH (Public Holiday) per karyawan
att_ph_ledger          — transaksi masuk/keluar saldo PH
```

### `att_daily` — Single Source of Truth

Ini tabel terpenting di modul absensi. **Semua kalkulasi payroll membaca dari sini.**

| Kolom | Isi |
|---|---|
| `employee_id` | Karyawan |
| `att_date` | Tanggal |
| `status` | HADIR / TERLAMBAT / SAKIT / IZIN / CUTI / ALPHA / LIBUR / PH / PHB |
| `late_minutes` | Menit terlambat |
| `policy_mode_snapshot` | Snapshot mode policy saat hitung (HARIAN/BULANAN/CUSTOM) |
| `meal_rate_snapshot` | Snapshot rate uang makan saat hitung |
| `is_locked` | Jika 1, tidak bisa diubah (period sudah punya batch gaji aktif) |

### Alur Absensi Harian
```
att_presence (raw log) 
    → service generate_daily_recap 
        → att_daily (rekap)
            → Payroll engine membaca att_daily
```

### Policy Lock
Jika payroll period sudah punya batch aktif (`pay_payroll_batch.status != 'VOID'`), maka `att_daily` untuk tanggal dalam period tersebut di-lock → tidak bisa diedit.

### Catatan Penting
- Status absensi di `att_daily` adalah **enum tetap** — jangan tambah status baru tanpa koordinasi dengan payroll engine
- `late_minutes` = 0 jika tidak terlambat, > 0 jika terlambat
- PH (Public Holiday) = karyawan masuk di hari libur (dapat kompensasi); PHB = PHBonus
- Selalu generate `att_daily` dari `att_presence` — jangan isi manual kecuali via fitur admin

---

## 5. Payroll & Penggajian

### Tujuan
Kalkulasi dan pencairan gaji bulanan karyawan.

### File Kunci
| Tipe | File |
|---|---|
| Controller | `application/controllers/Payroll.php` |
| Model | `application/models/Payroll_model.php` |
| Model | `application/models/Payroll_preview_model.php` |
| View | `application/views/payroll/` |
| Tool | `tools/payroll_audit_checker.php` |

### Tabel Kunci
```
pay_salary_profile         — profil gaji per karyawan (pokok, tunjangan jabatan, tunjangan objektif)
pay_salary_component       — komponen gaji tambahan (transport, makan, dll.)
pay_payroll_period         — periode gaji (bulan/tahun)
pay_payroll_result         — hasil kalkulasi per karyawan per period
pay_payroll_result_line    — detail komponen kalkulasi (potongan, tambahan)
pay_payroll_batch          — batch pencairan gaji
pay_payroll_batch_line     — detail karyawan dalam batch + snapshot
pay_salary_disbursement    — record pencairan per karyawan
pay_cash_advance           — kasbon karyawan
pay_cash_advance_installment — cicilan kasbon per bulan
pay_meal_disbursement      — pencairan uang makan mingguan
```

### Komponen Gaji

| Komponen | Sifat | Keterangan |
|---|---|---|
| Gaji Pokok | Tetap | Dari `pay_salary_profile` |
| Tunjangan Jabatan | Tetap | Dari `pay_salary_profile` |
| Tunjangan Objektif | Tetap/Variabel | Bisa override per bulan |
| Lembur | Variabel | Dari `att_daily` yang approved |
| Tambahan Manual | Variabel | Manual adjustment |
| Potongan Terlambat | Variabel | `late_minutes` × rate |
| Potongan Alpha | Variabel | Hari alpha × rate |
| Potongan Kasbon | Variabel | Cicilan yang jatuh tempo bulan ini |

### Alur Penggajian Bulanan
```
1. Buat Period       → pay_payroll_period (status: OPEN)
2. Generate Result   → pay_payroll_result per karyawan (kalkulasi dari att_daily)
3. Review & Edit     → manual adjustment jika perlu
4. Generate Batch    → pay_payroll_batch + pay_payroll_batch_line
5. Mark PAID         → status batch = PAID, mutasi rekening di fin_account_mutation_log
6. (Opsional) VOID   → batalkan batch, status kembali ke OPEN, bisa generate ulang
```

### Immutability Rules
- Setelah batch aktif (non-VOID) ada untuk suatu period:
  - `att_daily` untuk tanggal dalam period → locked
  - Manual adjustment dan overtime untuk tanggal dalam period → tidak bisa diubah
  - `pay_payroll_result` → tidak bisa dihapus tanpa void batch dulu

### Estimasi vs Batch — Kenapa Angka Bisa Beda

**Ini penting dan sering membingungkan operator.**

| | Estimasi (halaman `attendance/estimate`) | Batch Gaji |
|---|---|---|
| Sumber data | `att_daily` realtime (kondisi terkini) | Snapshot saat `Generate/Refresh Period` dijalankan |
| Kapan dihitung | Setiap halaman dibuka | Saat generate period, tersimpan di `pay_payroll_result` |
| Jika ada koreksi absensi | Langsung berubah | Tidak berubah sampai period di-refresh ulang |

**Solusi jika angka batch tidak cocok dengan estimasi:**
1. Buka `payroll/salary-disbursements`
2. Jalankan ulang **Generate/Refresh Payroll Period** untuk period yang sama
3. Cek detail result period
4. Baru generate batch pencairan

### Kasbon
```
Karyawan ajukan → DRAFT
Admin approve    → APPROVED
Admin cairkan    → DISBURSED (mutasi rekening)
Cicilan berjalan → PARTIAL_SETTLED (per bulan dipotong dari gaji)
Lunas            → SETTLED
```

**Tenor kasbon bersifat opsional:**
- Tenor `> 0` → sistem buat jadwal cicilan bulanan otomatis
- Tenor `0` → tidak ada jadwal cicilan; pembayaran dicatat sesuai realisasi

**Metode pembayaran kasbon:**
| Metode | Cara Kerja |
|---|---|
| `CASH` | Tunai dari rekening sumber perusahaan, saldo berkurang |
| `TRANSFER` | Transfer bank, saldo rekening berkurang |
| `SALARY_CUT` | Dipotong dari gaji; **tidak** mengurangi saldo rekening saat posting kasbon, tapi otomatis masuk sebagai potongan di payroll |

### Mutasi Rekening saat Mark Paid Batch Gaji

Jika batch gaji mengisi `company_account_id` (rekening sumber):
- Saat **Mark Paid** → saldo `fin_company_account` dikurangi
- Log `OUT` ditulis ke `fin_account_mutation_log`

Jika rekening tidak diisi → status PAID tetap bisa diproses tapi **tidak ada posting mutasi rekening**.

### Catatan Penting
- **Snapshot wajib** — `pay_payroll_batch_line` menyimpan snapshot nama, komponen, dan total agar histori tidak berubah jika master gaji diupdate
- Tab slip gaji di portal My hanya boleh baca dari snapshot line — bukan dari kalkulasi ulang
- `payroll_audit_checker.php` bisa dijalankan kapanpun untuk validasi konsistensi `att_daily` vs `payroll_result` vs disbursement

---

## 6. Pembelian (Purchase)

### Tujuan
Pengelolaan pembelian barang dari vendor ke gudang perusahaan.

### File Kunci
| Tipe | File |
|---|---|
| Controller | `application/controllers/Purchase.php` |
| Model | `application/models/Purchase_model.php` (~4000 baris) |
| View | `application/views/purchase/` |
| Tool | `tools/remap_warehouse_profile_keys_to_catalog.php` |
| Tool | `tools/remap_division_profile_keys_to_catalog.php` |

### Tabel Kunci
```
pur_purchase_order         — header PO (vendor, tanggal, status)
pur_purchase_order_line    — detail item dalam PO
pur_purchase_payment_plan  — rencana pembayaran PO
pur_purchase_receipt       — penerimaan barang
pur_purchase_receipt_line  — detail item yang diterima
pur_payment_channel        — channel pembayaran yang dipakai di PO
fin_company_account        — rekening perusahaan (BANK/EWALLET/CASH)
fin_account_mutation_log   — log mutasi saldo rekening
inv_warehouse_stock_balance — saldo stok gudang per item
inv_division_stock_balance  — saldo stok divisi per item
inv_stock_movement_log     — log pergerakan stok
aud_transaction_log        — audit log transaksi
```

### Status Flow PO
```
DRAFT → APPROVED → ORDERED → PARTIALLY_RECEIVED → RECEIVED → CLOSED
                                                             ↓
                                                           VOID
```

### Alur Lengkap Purchase
```
1. Buat PO          — pilih vendor, tambah item + qty + harga
2. Approve PO       — atasan setujui
3. Terima Barang    — input receipt: qty aktual yang datang
4. Receipt posting  → inv_warehouse_stock_balance bertambah
5. Bayar            — potong saldo fin_company_account
6. Close PO
```

### Profile Key (PENTING)

Purchase catalog menggunakan `profile_key` sebagai canonical key untuk identifikasi barang masuk gudang. Format key diambil dari `mst_purchase_catalog`.

**Jangan** pakai key yang dibuat sendiri (non-catalog) — selalu lookup ke catalog dulu.  
Script remap tersedia di `tools/remap_*_profile_keys_to_catalog.php` jika ada data lama dengan key non-catalog.

### Store Request (Belum Selesai)
Rencana alur:
```
Kepala divisi ajukan SR → Admin purchase review → Buat PO dari SR
```
Tabel: `pur_store_request`, `pur_store_request_line`, `pur_store_request_item`

---

## 7. Inventori & Gudang

### Tujuan
Tracking stok barang di gudang dan stok bahan baku di divisi.

### File Kunci
| Tipe | File |
|---|---|
| Controller | `application/controllers/Inventory_flow.php` |
| Model | (bagian dari `Purchase_model.php` dan `Inventory_model.php`) |
| Library | `application/libraries/InventoryLedger.php` |
| View | `application/views/purchase/stock_warehouse_index.php` |
| View | `application/views/purchase/stock_division_index.php` |

### 3 Layer Stok (KEPUTUSAN DESAIN — TIDAK BERUBAH)

| Layer | Tabel Prefix | Isi | Yang Melihat |
|---|---|---|---|
| **Gudang** | `inv_warehouse_*` | Item dari PO, belum terdistribusi | Admin gudang |
| **Bahan Baku (Divisi)** | `inv_material_*` | Bahan di dapur/bar setelah distribusi | Chef, Barista |
| **Komponen** | `prd_component_*` | Base/Prepare yang sudah diproduksi | Chef, Barista |

### Alur Stok
```
PO Receipt → inv_warehouse (Gudang)
                    ↓ distribusi ke divisi (via mst_material_item_source)
             inv_material (Stok Bahan Baku per Divisi)
                    ↓ dipakai batch produksi komponen
             prd_component (Stok BASE / PREPARE)
                    ↓ dipakai saat order POS
             [Terjual / Terpakai]
```

### Tabel Kunci
```
inv_warehouse_opening       — opening balance gudang per periode
inv_warehouse_stock_balance — saldo stok gudang real-time per item
inv_warehouse_daily_snapshot — snapshot harian gudang
inv_division_stock_balance  — saldo stok divisi per item
inv_stock_movement_log      — semua pergerakan stok (masuk/keluar/transfer/adjustment)
inv_monthly_opname          — opname bulanan gudang
inv_monthly_opname_line     — detail opname per item
```

### Komponen Adjustment
Saat opname atau koreksi stok, gunakan tipe adjustment yang sudah disepakati:
- `WASTE` — terbuang/rusak
- `SPOILAGE` — busuk/expired
- `PROCESS_LOSS` — susut saat produksi
- `VARIANCE` — selisih opname
- `ADJUSTMENT_PLUS` — penambahan manual

### Catatan Penting
- `InventoryLedger.php` adalah library untuk semua operasi tulis ke ledger — selalu pakai ini, jangan query langsung
- Opname bulanan harus selesai sebelum tanggal 5 bulan berikutnya (opening bulan baru dibuat dari opname)
- Distribusi item → material selalu via `mst_material_item_source` — tidak pernah langsung ke material tanpa mapping

---

## 8. Produksi & COGS

### Tujuan
Batch produksi komponen (BASE/PREPARE) dan kalkulasi biaya produksi (COGS).

### Status
**BELUM MULAI** — akan dikembangkan setelah Tahap 7 stabil.

### Rencana Tabel
```
prd_production_batch       — batch produksi (tanggal, komponen, qty target)
prd_production_batch_line  — konsumsi material/komponen per batch
prd_component_stock        — saldo stok komponen
prd_component_ledger       — ledger transaksi stok komponen
prd_cogs_calculation       — hasil kalkulasi HPP per batch
```

### Aturan Formula Komponen (Sudah Ditentukan)
| Tipe | Boleh memakai |
|---|---|
| BASE | Material, BASE lain |
| PREPARE | Material, BASE, PREPARE lain |
| PRODUCT (resep) | Material, BASE, PREPARE |

---

## 9. POS

### Tujuan
Point of Sale — transaksi penjualan ke pelanggan.

### Status
**PERSIAPAN DESAIN** — akan mulai coding setelah Tahap 7 cukup stabil.

### Target MVP
```
pos_order          — order pelanggan (meja, take away, delivery)
pos_order_item     — item dalam order
pos_order_extra    — extra/topping per item
pos_payment        — pembayaran order
pos_shift          — shift kasir (buka/tutup)
pos_shift_summary  — ringkasan kas saat tutup shift
```

### Integrasi Wajib dengan Modul Lain
- Saat order di-checkout: kurangi stok material/komponen dari divisi yang relevan
- Pembayaran: tambah saldo `fin_company_account` sesuai metode bayar
- Loyalty: tambah poin ke `crm_member_account`

---

## 10. Keuangan & Akuntansi

### Tujuan
Mutasi rekening perusahaan, AR/AP, cash flow, laporan keuangan.

### File Kunci
| Tipe | File |
|---|---|
| Controller | `application/controllers/Finance.php` *(akan dibuat)* |
| View | `application/views/purchase/finance_mutation_index.php` *(sementara di purchase)* |

### Tabel Kunci (Yang Sudah Ada)
```
fin_company_account        — rekening perusahaan (BANK/EWALLET/CASH)
fin_account_mutation_log   — semua mutasi saldo (masuk/keluar, dari modul mana)
```

### Tabel Kunci (Yang Akan Dibuat)
```
fin_ar_document            — dokumen piutang (AR)
fin_ap_document            — dokumen hutang (AP)
fin_bank_statement         — rekening koran / mutasi dari bank
fin_cash_flow              — rekap arus kas bulanan
fin_chart_of_accounts      — bagan akun (untuk laporan akuntansi)
fin_journal_entry          — jurnal umum
fin_journal_line           — detail jurnal (debit/kredit)
```

### Pola Mutasi Rekening (Sudah Berjalan)

Setiap transaksi yang memengaruhi saldo rekening perusahaan wajib insert ke `fin_account_mutation_log`:

```php
// Di model, saat ada transaksi yang mempengaruhi rekening
$this->db->insert('fin_account_mutation_log', [
    'account_id'    => $account_id,
    'mutation_type' => 'DEBIT',   // atau 'CREDIT'
    'amount'        => $amount,
    'balance_after' => $new_balance,
    'source_module' => 'purchase',   // atau 'payroll', 'pos', dll.
    'source_id'     => $po_id,
    'notes'         => 'Pembayaran PO-202605-001',
    'created_by'    => $user_id,
    'created_at'    => date('Y-m-d H:i:s'),
]);
```

**Prinsip:** Satu sumber saldo per rekening — selalu baca dari `fin_company_account.current_balance`, selalu update via log transaksi.

---

## Catatan Lintas Modul

### Data Dependencies

```
org_employee
    ← auth_user (akun login)
    ← att_daily (absensi)
    ← pay_payroll_result (gaji)
    ← pay_cash_advance (kasbon)
    ← org_contract (kontrak)

mst_purchase_catalog
    ← pur_purchase_order_line (PO)
    ← inv_warehouse_stock_balance (saldo)
    ← inv_stock_movement_log (log)

fin_company_account
    ← pur_payment_channel (bayar PO)
    ← pay_payroll_batch (bayar gaji)
    ← pay_meal_disbursement (bayar makan)
    ← pay_cash_advance (kasbon)
    ← pos_payment (penerimaan POS)
```

### Tabel "Tidak Boleh Dimodifikasi Tanpa Koordinasi"

| Tabel | Alasan |
|---|---|
| `att_daily` | Single source of truth absensi + payroll lock |
| `pay_payroll_batch_line` | Snapshot immutable untuk histori slip gaji |
| `fin_account_mutation_log` | Audit trail keuangan |
| `inv_stock_movement_log` | Audit trail inventori |
| `mst_purchase_catalog` | Canonical profile key untuk seluruh inventori |
