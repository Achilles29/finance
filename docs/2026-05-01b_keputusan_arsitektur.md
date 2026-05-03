# Keputusan Arsitektur `finance`
**Tanggal:** 2026-05-01  
**Status:** FINAL — dasar semua pengembangan

---

## 1. Database

| Keputusan | Detail |
|---|---|
| **Database terpisah** | `finance` menggunakan database sendiri, tidak share dengan `core` |
| **Nama database** | `db_finance` (TBD, sesuaikan di config) |
| **Kompatibilitas data `core`** | Skema harus mampu menerima data migrasi dari `core` via import script |
| **Migration script** | Disimpan di `finance/sql/` dengan prefix tanggal + huruf |

**Alasan:** Database `core` terlalu banyak tabel, banyak tidak terpakai, dan tumpang tindih akibat pengembangan paralel. Membuat database baru memungkinkan kita merancang skema yang bersih dari awal.

---

## 2. Konvensi Nama File SQL

Format: `YYYY-MM-DD[a/b/c/...]_deskripsi_singkat.sql`

Contoh:
- `2026-05-01a_initial_schema_auth.sql`
- `2026-05-01b_initial_schema_master.sql`
- `2026-05-02a_initial_schema_hr.sql`

Huruf suffix (`a`, `b`, `c`, ...) digunakan untuk urutan eksekusi pada hari yang sama.

---

## 3. Prefix Tabel

Setiap tabel menggunakan prefix domain agar mudah dikenali. Prefix yang disepakati:

| Prefix | Domain | Contoh |
|---|---|---|
| `sys_` | Sistem: menu, konfigurasi, audit log | `sys_menu`, `sys_config` |
| `auth_` | Autentikasi & RBAC: user, role, permission | `auth_user`, `auth_role` |
| `org_` | Organisasi & HR: karyawan, jabatan, departemen | `org_employee`, `org_position` |
| `att_` | Absensi (terpadu, tidak hybrid) | `att_daily`, `att_device` |
| `pay_` | Payroll & pencairan | `pay_salary_profile`, `pay_disbursement` |
| `mst_` | Master data lintas domain | `mst_uom`, `mst_item`, `mst_vendor` |
| `pur_` | Pembelian (purchase) | `pur_order`, `pur_order_line` |
| `inv_` | Inventori & gudang | `inv_warehouse`, `inv_material_stock` |
| `prd_` | Produksi | `prd_batch`, `prd_component_lot` |
| `pos_` | Point of Sale | `pos_order`, `pos_shift` |
| `crm_` | Customer & loyalty | `crm_customer`, `crm_member` |
| `fin_` | Keuangan & akuntansi | `fin_bank_txn`, `fin_ap_doc` |
| `rpt_` | View/tabel laporan (bukan view transaksi) | `rpt_daily_sales` |

**Catatan:** Prefix `m_` (dari `core`) dihapus, diganti `mst_`. Prefix `rsp_` (dari `core`) dihapus, konteksnya dimasukkan ke `inv_`.

---

## 4. Konvensi Nama Kolom

| Pola | Konvensi | Contoh |
|---|---|---|
| Primary key | `id` BIGINT UNSIGNED AUTO_INCREMENT | Semua tabel |
| Foreign key | `{entitas}_id` | `employee_id`, `outlet_id` |
| Nomor dokumen | `{entitas}_no` | `order_no`, `po_no` |
| Kode unik | `{entitas}_code` | `employee_code`, `material_code` |
| Nama | `{entitas}_name` | `employee_name`, `product_name` |
| Status enum | `status` | `DRAFT`, `ACTIVE`, `VOID` |
| Boolean | `is_{kondisi}` TINYINT(1) | `is_active`, `is_locked` |
| Snapshot | `{field}_snapshot` | `employee_name_snapshot` |
| Timestamp | `created_at`, `updated_at` | Semua tabel |
| Dibuat oleh | `created_by` (FK ke `auth_user.id`) | |
| Amount | DECIMAL(18,2) | `amount`, `total_amount` |
| Quantity | DECIMAL(18,6) untuk bahan, DECIMAL(10,2) untuk orang | |

---

## 5. Konvensi Kode / Autonumber

| Entitas | Format | Contoh |
|---|---|---|
| Karyawan NIP | `EMP-{YYYY}{MM}{XXX}` | `EMP-2026050001` |
| Purchase Order | `PO-{YYYY}{MM}-{XXX}` | `PO-202605-001` |
| Store Request | `SR-{YYYY}{MM}-{XXX}` | `SR-202605-001` |
| POS Order | `ORD-{YYYYMMDD}-{XXX}` | `ORD-20260501-001` |
| POS Shift | `SHF-{YYYYMMDD}-{outlet}-{XXX}` | |
| Disbursement Gaji | `SLD-{YYYY}{MM}-{XXX}` | `SLD-202605-001` |
| Disbursement Uang Makan | `UM-{YYYY}W{WW}-{XXX}` | `UM-2026W18-001` |
| AP/AR | `AP-{YYYY}{MM}-{XXX}` / `AR-{YYYY}{MM}-{XXX}` | |
| Kasbon | `KSB-{YYYY}{MM}-{XXX}` | `KSB-202605-001` |

---

## 6. Struktur Direktori `finance`

```
finance/
├── application/
│   ├── config/
│   ├── controllers/       — satu controller per modul
│   ├── models/            — satu model per entitas utama
│   ├── views/             — folder per controller
│   │   └── layout/        — template layout bersama
│   ├── helpers/
│   ├── libraries/
│   └── hooks/
├── assets/
│   ├── css/
│   ├── js/
│   └── img/
├── docs/                  — semua dokumentasi (prefix tanggal)
│   └── YYYY-MM-DD[a]_judul.md
└── sql/                   — semua migration script (prefix tanggal)
    └── YYYY-MM-DD[a]_deskripsi.sql
```

---

## 7. Framework & Stack

| Komponen | Keputusan |
|---|---|
| Framework | CodeIgniter 3.1.x (sama dengan `core`) |
| PHP | 7.4+ (XAMPP lokal, server online sama) |
| Database | MySQL 5.7+ / MariaDB |
| Frontend | Bootstrap 4/5 (TBD — lihat `core` untuk konsistensi) |
| Datatable | DataTables.js (AJAX server-side) |
| AJAX | jQuery AJAX (konsisten dengan `core`) |

---

## 8. Strategi Migrasi Data dari `core`

1. Modul diselesaikan di `finance` terlebih dahulu
2. Setelah modul siap, buat migration script di `finance/sql/` yang membaca data dari database `core` dan mengimpornya ke `db_finance`
3. Data yang dimigrasi harus di-transform sesuai skema baru (rename kolom, clean up null, dll.)
4. Script migrasi bersifat idempotent (bisa dijalankan ulang tanpa duplikat data)

**Urutan migrasi data yang direncanakan:**
1. Karyawan (`org_employee`)
2. Pelanggan (`crm_customer`, `crm_member_account`)
3. Master barang (`m_material`, `m_component`, `m_product`, `m_vendor`)
4. Absensi historis
5. Payroll historis
6. Transaksi POS historis
7. Mutasi keuangan historis

---

## 9. RBAC — Rancangan Baru

Kelemahan RBAC `core`: permission di-seed via SQL, tidak ada UI yang clean untuk manage dari awal.

**Rancangan baru `finance`:**
- Role-based permission: setiap role punya list allowed pages/actions
- Position → Role mapping (jabatan mendapat role default)
- Override per user (boleh tambah atau kurangi dari role default)
- Permission dikelola dari UI, bukan SQL manual
- Tabel: `auth_role`, `auth_permission`, `auth_role_permission`, `auth_user_role`, `auth_user_permission_override`

---

## 10. Prinsip Pengembangan

1. **Schema-first**: rancang tabel dulu, baru koding
2. **Module-by-module**: selesaikan satu modul lengkap sebelum pindah ke berikutnya
3. **Dokumentasi setiap perubahan**: setiap schema change wajib ada file SQL di `finance/sql/`
4. **Tidak ada alter tanpa migration file**: perubahan tabel = file SQL baru
5. **Data yang ada di `core` harus bisa diimpor** ke `finance` tanpa kehilangan integritas
