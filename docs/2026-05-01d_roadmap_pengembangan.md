# Roadmap Pengembangan `finance`
**Tanggal:** 2026-05-01  
**Status:** AKTIF — diperbarui setiap ada progress

---

## Prinsip Pengembangan

- **Schema-first**: tabel dirancang dan didokumentasikan sebelum koding
- **Alur bisnis dulu**: setiap modul baru didokumentasikan dalam bahasa user sebelum masuk kode
- **Modul per modul**: satu modul selesai end-to-end sebelum pindah ke berikutnya
- **Setiap perubahan dicatat**: file SQL di `finance/sql/`, dokumen di `finance/docs/`
- **Data `core` bisa dimigrasi**: setiap tabel baru harus ada rencana migrasi dari `core`
- **Mobile-friendly**: semua halaman harus responsif dan nyaman di layar HP

---

## Keputusan Desain Penting

### Sidebar — Dua Zona
Di `core` pembagian sidebar kurang tepat. Rancangan baru:

| Sidebar | Untuk Siapa | Isi |
|---|---|---|
| **Sidebar Utama** | Staff yang punya akses | Semua modul operasional perusahaan |
| **Sidebar Pribadi (My)** | Semua karyawan login | Data diri sendiri: slip gaji, absensi, kasbon, profil |

Sidebar Utama = semua yang berhubungan dengan perusahaan (HR, keuangan, POS, gudang, dll.)  
Sidebar Pribadi = hanya data personal karyawan tersebut, tidak bisa lihat data orang lain.

### RBAC — Sederhana & Efektif
Tidak dibuat serumit `core`. Konsep dasarnya:

1. **Halaman (Page)** — setiap URL/fitur didaftarkan
2. **Role** — kumpulan izin halaman (contoh: Kasir, Manajer, Admin, CEO)
3. **Employee → Role** — karyawan dikaitkan ke role
4. **Override per karyawan** — jika ada karyawan yang perlu akses tambahan/dikurangi dari rolenya

Tidak ada role scope berlapis-lapis seperti di `core`. Cukup: role → halaman, karyawan → role.

### Skema Gaji — Disederhanakan
Komponen gaji di `finance`:

| Komponen | Keterangan |
|---|---|
| `gaji_pokok` | Gaji dasar sesuai jabatan |
| `tunjangan_jabatan` | Tunjangan berdasarkan posisi/jabatan (menggantikan "tunjangan") |
| `tunjangan_objektif` | Tunjangan berdasarkan pencapaian/KPI (menggantikan "tambahan lain") |
| Komponen lain | Bisa ditambah: transport, makan, dll. tapi tidak wajib |

Potongan: keterlambatan, alpha, kasbon cicilan.  
Tambahan: lembur, bonus omzet.

### Inventori — Tetap Dipisah
- **Gudang** (`inv_warehouse_*`) = stok item fisik dari pembelian
- **Stok Material** (`inv_material_*`) = bahan baku yang sudah masuk ke divisi/dapur/bar
- **Stok Komponen** (`prd_component_*`) = base/prepare yang sudah diproduksi

Pemisahan ini disengaja agar tidak membingungkan user. Detail lihat: `2026-05-01c_konsep_inventori_fnb.md`

---

## Status Pengembangan

| Tahap | Nama | Status | Mulai | Selesai |
|---|---|---|---|---|
| 0 | Fondasi & Keputusan Arsitektur | ✅ SELESAI | 2026-05-01 | 2026-05-01 |
| 1 | Auth, RBAC & Sidebar | 🟡 Sedang Berjalan | 2026-05-01 | - |
| 2 | Master Data | 🟢 Gate Closed (siap lanjut) | 2026-05-02 | 2026-05-03 |
| 3 | HR & Organisasi | 🟡 Paralel Terencana | | |
| 4 | Absensi (Terpadu) | 🔲 Belum | | |
| 5 | Payroll & Penggajian | 🔲 Belum | | |
| 6 | Pembelian (Purchase) | 🟡 Sedang Berjalan | 2026-05-03 | - |
| 7 | Inventori & Gudang | 🟡 Fondasi Berjalan | 2026-05-06 | - |
| 8 | Produksi & COGS | 🔲 Belum | | |
| 9 | POS (Point of Sale) | 🔲 Belum | | |
| 10 | Keuangan & Akuntansi | 🔲 Belum | | |
| 11 | Reports & Dashboard | 🔲 Belum | | |

### Revisi Urutan Eksekusi (2026-05-03)

Nomor tahap tetap dipertahankan untuk konsistensi dokumen, tetapi urutan eksekusi diprioritaskan ulang berdasarkan dependensi `core` dan target kesiapan POS:

1. Jalur Operasional (prioritas lebih dulu): Tahap 2 -> Tahap 6 -> Tahap 7 -> Tahap 8 -> Tahap 9
2. Jalur SDM (paralel terkontrol): Tahap 3 -> Tahap 4 -> Tahap 5
3. Tahap 10 dan Tahap 11 mengikuti setelah fondasi operasional dan SDM stabil.

Penetapan eksekusi saat ini:
- Posisi proyek saat ini: gate Tahap 2 sudah ditutup (dokumen penutupan tersedia).
- Tahap 6 sudah resmi dimulai dengan fondasi schema purchase (DDL + seed awal).
- Tahap 3 tetap berjalan paralel untuk kebutuhan data SDM dasar, tanpa memblokir jalur operasional.

Alasan:
- POS di `core` sangat bergantung pada recipe, stock, material/component, adjustment, return-to-stock, dan pelaporan margin terkait purchase.
- Purchase + inventory + production yang matang lebih kritikal untuk operasi FnB harian sebelum ekspansi payroll penuh.
- Modul SDM tetap penting, tetapi tidak menjadi blocker utama untuk menyalakan alur transaksi barang-penjualan.

### Checklist Global Per Tahap (Master Progress Board)

#### Gate Pindah Tahap
- Gate Tahap 2 -> Tahap 6: seluruh checklist kritis Tahap 2 harus selesai.
- Setelah Gate Tahap 2 terpenuhi, eksekusi berpindah resmi ke Tahap 6.

#### Tahap 0 — Fondasi
- [x] Scan core modules selesai.
- [x] Keputusan arsitektur awal selesai.
- [x] Dokumen roadmap baseline selesai.

#### Tahap 1 — Auth, RBAC, Sidebar
- [x] Fondasi sidebar dinamis + favorit user berjalan.
- [x] Modul manajemen sidebar admin tersedia.
- [ ] Finalisasi dokumen status Tahap 1 agar tidak lagi bertuliskan "belum mulai coding".
- [ ] Tutup checklist auth/rbac inti sesuai dokumen Tahap 1.

#### Tahap 2 — Master Data (Gate Closed)
- [x] Keputusan model item-material final (pisah + mapping) ditetapkan.
- [x] Keputusan UOM BELI vs UOM ISI ditetapkan.
- [x] Validasi pola `core` untuk purchase catalog profile selesai.
- [x] Fondasi relasi recipe/formula dan extra group berjalan.
- [x] Finalisasi SQL schema Tahap 2 (single migration final).
- [x] Finalisasi kontrak snapshot profile untuk ledger inventori.
- [x] Dokumen penutupan gate Tahap 2 dibuat: `docs/2026-05-03e_penutupan_gate_tahap2_schema_snapshot.md`.

#### Tahap 3 — HR & Organisasi (Paralel)
- [ ] Finalisasi SQL schema HR.
- [ ] Implement CRUD employee/position/division dasar.
- [ ] Integrasi role mapping employee untuk RBAC.

#### Tahap 4 — Absensi
- [ ] Desain tabel absensi terpadu final.
- [ ] Implement rekap harian tunggal (`att_daily`).
- [ ] Workflow approval izin/sakit/lembur.

#### Tahap 5 — Payroll
- [ ] Implement komponen gaji + profile + assignment.
- [ ] Kalkulasi payroll bulanan dari absensi terpadu.
- [ ] Disbursement gaji + kasbon + THR/bonus.

#### Tahap 6 — Purchase (Tahap Berikutnya Setelah Gate Tahap 2)
- [x] SQL fondasi purchase schema (`pur_purchase_order`, `pur_purchase_order_line`, `mst_purchase_catalog`) di `sql/2026-05-03f_purchase_schema_foundation.sql`.
- [x] Endpoint katalog purchase (search profile + fallback master item) via `application/controllers/Purchase.php`.
- [x] Upsert purchase catalog profile saat simpan line PO.
- [x] Fondasi payment purchase (`mst_payment_method`, `pur_purchase_payment_plan`) di `sql/2026-05-03h_purchase_payment_receipt_foundation.sql`.
- [x] Fondasi receipt tujuan masuk gudang/divisi (`pur_purchase_receipt`, `pur_purchase_receipt_line`) di `sql/2026-05-03h_purchase_payment_receipt_foundation.sql`.
- [x] Fondasi akun perusahaan (BANK/EWALLET/CASH) + payment channel purchase (`fin_company_account`, `pur_payment_channel`) di `sql/2026-05-03i_purchase_affected_finance_inventory_audit_foundation.sql`.
- [x] Endpoint uji potong saldo payment purchase via channel akun di `application/controllers/Purchase.php`.
- [x] Fondasi stok terdampak purchase + log audit (`inv_warehouse_stock_balance`, `inv_division_stock_balance`, `inv_stock_movement_log`, `aud_transaction_log`) di `sql/2026-05-03i_purchase_affected_finance_inventory_audit_foundation.sql`.
- [x] Split halaman Opening Stok Gudang/Divisi + seed menu permission (`sql/2026-05-06e_purchase_stock_opening_split_menu_seed.sql`).
- [x] Split tabel opening snapshot gudang/divisi (`sql/2026-05-06f_inventory_opening_snapshot_split_tables.sql`).
- [x] Fondasi flow generate opname bulanan + opening bulan berikutnya (`sql/2026-05-06c_inventory_monthly_opname_and_opening_flow.sql`).
- [x] Penyesuaian stok 5 komponen (WASTE/SPOILAGE/PROCESS_LOSS/VARIANCE/ADJUSTMENT_PLUS) di daily rollup gudang/divisi (`sql/2026-05-06b_inventory_adjustment_components.sql`).
- [x] Hardening opening profile: catalog-first fallback saat pencarian + canonical key dari katalog saat simpan opening (`application/models/Purchase_model.php`).
- [x] Remap historis key non-catalog -> key catalog untuk scope DIVISION berbasis exact identity, dalam 1 transaksi DB, aman dan idempotent (`tools/remap_division_profile_keys_to_catalog.php`).
- [ ] Remap historis key non-catalog -> key catalog untuk scope WAREHOUSE belum dieksekusi (backlog terukur terakhir: opening=2, balance=0, daily=5, movement=6).
- [ ] Integrasi UOM BELI/UOM ISI di form PO.
- [x] Seed permission + menu Purchase (RBAC) via `sql/2026-05-03g_purchase_menu_seed.sql`.
- [ ] Dokumentasi alur user Purchase final.

#### Tahap 7 — Inventori
- [ ] Posting dari purchase ke gudang item siap.
- [ ] Distribusi gudang item -> material via `mst_material_item_source` siap.
- [ ] Ledger, balance, adjustment, lot tracking stabil.

#### Tahap 8 — Produksi & COGS
- [ ] Produksi component mode resep/acuan bahan siap.
- [ ] COGS calculation dan settings siap.
- [ ] Integrasi konsumsi stok material/component siap.

#### Tahap 9 — POS
- [ ] POS core flow (order-payment-void-refund) siap.
- [ ] Integrasi stok + return-to-stock siap.
- [ ] Loyalty, extra, printer, report POS siap minimum operasional.

#### Tahap 10 — Keuangan
- [ ] Bank txn + opening + AR/AP siap.
- [ ] Cash flow dan monthly summary siap.
- [ ] Integrasi transaksi lintas modul (purchase/POS/payroll).

#### Tahap 11 — Reports & Dashboard
- [ ] Reports hub lintas modul siap.
- [ ] Dashboard KPI real-time siap.
- [ ] Paket laporan manajemen bulanan siap.

---

## Tahap 0 — Fondasi & Keputusan Arsitektur ✅

**Output:**
- [x] Scan lengkap modul `core` → `2026-05-01a_scan_core_modules.md`
- [x] Keputusan arsitektur → `2026-05-01b_keputusan_arsitektur.md`
- [x] Konsep inventori FnB → `2026-05-01c_konsep_inventori_fnb.md`
- [x] Roadmap ini → `2026-05-01d_roadmap_pengembangan.md`

---

## Tahap 1 — Auth, RBAC & Sidebar

**Tujuan:** Fondasi login, manajemen user, permission, dan navigasi sidebar yang bersih.

**Modul:**
- Login / logout
- Manajemen user (CRUD)
- Role management (CRUD role, assign permission)
- Position → Role mapping default
- Override permission per user
- Sidebar menu (tree, favorit, server sync)
- Audit log akses

**Tabel yang dibuat:**
```
auth_user
auth_session_log
auth_role
auth_permission
auth_role_permission
auth_user_role
auth_user_permission_override
org_position          ← dibutuhkan untuk role mapping
sys_menu
sys_menu_permission
sys_sidebar_favorite
sys_audit_log
```

**Perbaikan dari `core`:**
- Permission dikelola dari UI, bukan SQL manual
- Role matrix lebih clean: role → permission (bukan patch via migration)
- Audit log akses terintegrasi dari awal

**Output yang diharapkan:**
- [ ] SQL schema: `finance/sql/2026-05-XX_auth_rbac_schema.sql`
- [ ] Controller: `Auth`, `Users`, `Access_control`
- [ ] View: login, user list, role matrix

---

## Tahap 2 — Master Data

**Tujuan:** Semua master data yang dibutuhkan modul-modul berikutnya.

**Modul:**
- UOM (satuan ukur) + konversi satuan
- Item master (semua barang beli)
- Material master (bahan baku resep)
- Material-item source map (jembatan item pemasok ke material resep)
- Component master (bahan setengah jadi)
- Component formula (resep komponen)
- Product master (produk jual)
- Product recipe (resep produk)
- Vendor / supplier
- Rekening bank
- Divisi / departemen
- Tipe pembelian & referensi belanja
- Tipe posting akuntansi

**Perbaikan dari `core`:**
- `mst_item` dan `mst_material` tetap dipisah sesuai konsep FnB, diikat oleh mapping `mst_material_item_source`
- Tidak ada tabel `m_counterparty` yang hybrid — vendor tetap vendor, customer tetap customer
- Prefix `mst_` yang konsisten

**Output yang diharapkan:**
- [ ] SQL schema: `finance/sql/2026-05-XX_master_data_schema.sql`
- [ ] Controllers: `Master_uom`, `Master_items`, `Master_components`, `Master_products`, `Master_vendors`, dll.
- [ ] Migration script dari `core`: `finance/sql/migrate_master_from_core.sql`

### Progress Update (2026-05-03)

- Master generik CRUD + RBAC berbasis menu sudah berjalan.
- Modul relasi recipe/formula sudah berjalan.
- Mapping Product Extra kini sudah ada mode berpusat di Group Extra (checklist produk per group).
- Pengaturan default variable cost sudah berjalan.
- UI standar global sedang dipoles (sidebar kontras, tabel, icon aksi, responsif).
- Sinkronisasi Extra dari core sudah dijalankan untuk group, group item, dan group-produk.
- Fondasi flow transaksi item -> material sudah dimulai (schema map + ledger transaksi + halaman input manual).
- Keputusan UOM pembelian sudah ditegaskan: UOM BELI (kemasan transaksi) dipisah dari UOM ISI (satuan dasar konsumsi).
- Acuan core untuk profil pembelian (purchase catalog) sudah diverifikasi sebagai baseline desain profesional.

### Tahap Berikutnya (Prioritas)

1. Sinkronisasi data Extra dari `core`:
	- Status: sudah dijalankan via `2026-05-03a_sync_extra_group_from_core.sql`.
2. Finalisasi governance UI global:
	- pastikan semua modul existing memakai pola tabel/aksi/icon konsisten.
3. Mulai desain transaksi inventori:
	- status awal: fondasi map + transaksi manual siap dipakai.
	- lanjutkan ke posting otomatis distribusi gudang item -> stok material dan validasi alur stok lintas divisi.

4. Finalisasi Purchase Catalog Profile (wajib sebelum lanjut tahap besar berikutnya):
	- pisahkan identitas master item dari profil transaksi pembelian (merk, isi per unit, satuan isi, harga).
	- pastikan perubahan kemasan vendor tidak memaksa ubah master item/material.
	- definisikan snapshot profile ke ledger agar audit historis stabil.

### Keputusan Jalur Tahap

- Belum pindah tahap mayor.
- Fokus penyempurnaan tahap berjalan dulu pada 2 titik kritis:
  1. desain final purchase catalog profile,
  2. penyempurnaan pola stock profile yang di core masih partial.
- Setelah dua titik ini stabil, baru lanjut eksekusi tahap berikutnya secara penuh.
- Urutan eksekusi mengikuti revisi dependensi POS: Purchase/Inventory/Production didahulukan sebelum implementasi POS penuh.

---

## Tahap 3 — HR & Organisasi

**Catatan Eksekusi:** berjalan paralel (non-blocking) selama jalur operasional Tahap 6 -> 7 -> 8 -> 9 berlangsung.

**Tujuan:** Data karyawan yang bersih dan terstruktur.

**Modul:**
- Karyawan (CRUD, NIP otomatis)
- Jabatan / posisi
- Departemen / divisi
- Kontrak karyawan (+ QR verify)
- Data personal karyawan (biodata, rekening bank pribadi)

**Perbaikan dari `core`:**
- `org_employee` dirancang lengkap dari awal (tidak tambal sulam)
- Jabatan dan departemen terhubung proper ke RBAC (tahap 1)
- Kontrak lebih terstruktur

**Output yang diharapkan:**
- [ ] SQL schema: `finance/sql/2026-05-XX_hr_schema.sql`
- [ ] Controllers: `Employees`, `Hr_contracts`, `Org_positions`
- [ ] Migration script dari `core`: `finance/sql/migrate_employees_from_core.sql`

---

## Tahap 4 — Absensi (Terpadu)

**Tujuan:** Sistem absensi terpadu — tidak hybrid, satu alur yang jelas.

**Modul:**
- Aturan absensi (potongan terlambat, alpha, jam kerja)
- Kalender hari libur / hari besar
- Device absensi (GPS, faceprint, manual)
- Raw log absensi (dari device)
- Rekap harian (satu tabel, bukan dua)
- Input manual absensi
- Workflow approval (pengajuan sakit, izin, lembur)
- Laporan harian & bulanan
- Self-portal karyawan: lihat rekap absensi sendiri

**Perbaikan dari `core`:** ⭐ Prioritas utama
- **Hapus hybrid**: hanya ada `att_daily` (satu tabel rekap harian, bukan `att_daily_recap` vs `pay_attendance_daily`)
- Workflow approval yang jelas: submit → review → approve/reject
- Device management yang clean
- Raw log → rekap harian bisa diproses otomatis (cron/CLI)

**Tabel yang dibuat:**
```
att_rule               ← aturan absensi
att_schedule           ← jadwal kerja (shift pagi/sore/malam)
att_holiday            ← kalender hari libur
att_device             ← device absensi
att_raw_log            ← raw log dari device
att_daily              ← rekap harian (satu tabel, TERPADU)
att_request            ← pengajuan izin/sakit/lembur
att_request_approval   ← workflow approval
```

**Output yang diharapkan:**
- [ ] SQL schema: `finance/sql/2026-05-XX_attendance_schema.sql`
- [ ] Controllers: `Attendance`, `Attendance_masters`, `Attendance_self`
- [ ] CLI: rebuild rekap harian dari raw log
- [ ] Migration script dari `core`: data `att_daily_recap` + `pay_attendance_daily` → `att_daily`

---

## Tahap 5 — Payroll & Penggajian

**Tujuan:** Sistem penggajian yang terintegrasi penuh dengan absensi.

**Modul:**
- Komponen gaji (tunjangan tetap, potongan, dll.)
- Profil gaji & assignment ke karyawan
- Kalkulasi gaji bulanan (dari absensi + profil)
- Pencairan gaji (salary disbursement)
- Uang makan mingguan (dari absensi)
- Kasbon karyawan + cicilan
- THR
- Bonus omzet
- Self-portal: slip gaji, history kasbon

**Perbaikan dari `core`:**
- Kalkulasi gaji otomatis terpadu dari `att_daily` (bukan dari 2 sumber)
- Uang makan tidak berpindah modul
- Kasbon dengan cicilan yang lebih trackable

**Output yang diharapkan:**
- [ ] SQL schema: `finance/sql/2026-05-XX_payroll_schema.sql`
- [ ] Controllers: `Payroll_standards`, `Salary_disbursements`, `Meal_disbursements`, `Cash_advances`, `Payroll_thr`, `Payroll_bonus`
- [ ] Migration script dari `core`

---

## Tahap 6 — Pembelian (Purchase)

**Tujuan:** Alur pembelian yang terstruktur dari request → PO → penerimaan.

**Modul:**
- Purchase Order (PO): buat, approve, terima barang
- Katalog harga supplier
- Store request (permintaan barang dari divisi)
- Division request ke gudang
- Laporan pembelian
- Price pulse (tracking harga supplier over time)

**Perbaikan dari `core`:**
- `pur_` prefix konsisten (tidak ada `rsp_store_request`)
- Alur request → PO lebih eksplisit
- Katalog harga lebih terstruktur
- Snapshot profile transaksi dibekukan di level line agar histori aman

**Output yang diharapkan:**
- [x] SQL schema: `finance/sql/2026-05-03f_purchase_schema_foundation.sql`
- [ ] Controllers: `Purchase_orders`, `Store_requests`, `Purchase_reports`
- [ ] Migration script dari `core`
- [x] Dokumen struktur Tahap 6: `docs/2026-05-03f_tahap6_purchase_foundation.md`

**Checklist Implementasi Tahap 6:**
- [ ] `pur_purchase_order` + `pur_purchase_order_line` siap CRUD dan validasi status.
- [ ] `mst_purchase_catalog` siap untuk profile historis (merk, isi per unit, satuan isi, harga, referensi).
- [ ] Auto-create/update profile katalog dari transaksi PO.
- [ ] Guarding perubahan kemasan: perubahan profile tidak mengubah identitas master item/material.
- [ ] Endpoint AJAX katalog pembelian untuk percepat input PO.
- [ ] Laporan `price pulse` berbasis histori profile katalog.
- [ ] Integrasi awal ke posting inventori (hook ke tahap 7).

---

## Tahap 7 — Inventori & Gudang

**Tujuan:** Manajemen stok yang bersih dengan 3 layer (gudang, material, komponen).

**Modul:**
- Gudang item: opening, ledger, balance, adjustment
- Stok material (bahan baku): opening, mutasi, penyesuaian, lot tracking
- Stok komponen: opening, mutasi, penyesuaian, lot tracking
- Transfer gudang → material (distribusi ke bar/kitchen)
- Stale stock alert
- Laporan stok harian & bulanan

**Perbaikan dari `core`:**
- Mekanisme stok item dan material tetap dipisah (gudang vs material), dengan jembatan `mst_material_item_source`
- `inv_` prefix konsisten (tidak ada `rsp_material_*`)
- Lot tracking lebih terintegrasi

**Output yang diharapkan:**
- [ ] SQL schema: `finance/sql/2026-05-XX_inventory_schema.sql`
- [ ] Controllers: `Inventory`, `Material_stock`, `Component_stock`
- [ ] Migration script dari `core`

### Progress Update Tahap 7 (2026-05-07)

- Dokumen ringkas handoff lintas perangkat dibuat di `docs/2026-05-07_handoff_progress_purchase_inventory.md`.
- Fondasi inventori yang dipakai alur opening sudah berjalan untuk dua scope (gudang & divisi): opening snapshot split, monthly opname, dan generate opening bulan berikut.
- Struktur daily rollup untuk gudang/divisi sudah mengakomodir kategori adjustment profesional (5 komponen) termasuk nilai rupiah per kategori.
- Konsistensi profile identity menuju katalog sudah dikuatkan di layer aplikasi (catalog-first fallback + canonicalisasi saat simpan opening).
- Remap historis exact identity ke canonical catalog sudah selesai untuk DIVISION dan sudah diverifikasi residual = 0.
- Scope WAREHOUSE belum diremap; sudah ada baseline hitungan kandidat remap untuk menjaga eksekusi berikutnya terukur.

### Rencana Lanjutan Terdekat (Handoff Friendly)

1. Eksekusi remap WAREHOUSE (exact identity, single transaction, idempotent), lalu simpan output before/after ke dokumen log.
2. Lakukan verifikasi residual pasca-remap WAREHOUSE sampai 0 untuk opening/balance/daily/movement.
3. Jalankan smoke test UI opening gudang + divisi untuk pastikan profile_key baru tetap konsisten di pencarian, simpan, dan tampilan riwayat.
4. Rapikan governance duplikasi identity di `mst_purchase_catalog` (opsional tapi disarankan) agar remap/rematch berikutnya makin deterministik.
5. Setelah remap gudang clear, lanjutkan checklist Tahap 7: posting/distribusi gudang -> material dan stabilisasi ledger/balance/adjustment.

---

## Tahap 8 — Produksi & COGS

**Tujuan:** Pencatatan produksi dan kalkulasi HPP otomatis.

**Modul:**
- Batch produksi komponen (prepare/base)
- Batch produksi produk
- Lot tracking produksi
- Kalkulasi HPP (COGS) per produk
- COGS settings
- Laporan biaya produksi harian
- Kapasitas produk bulanan

**Output yang diharapkan:**
- [ ] SQL schema: `finance/sql/2026-05-XX_production_schema.sql`
- [ ] Controllers: `Production`, `Component_batches`

---

## Tahap 9 — POS (Point of Sale)

**Tujuan:** Sistem kasir yang lengkap dan terintegrasi dengan inventory.

**Modul:**
- Master: outlet, terminal, metode bayar
- Shift kasir
- Order: buat, modifikasi, void
- Split payment
- Refund
- Deposit pelanggan
- Extra / add-on
- Product bundle
- Loyalty: poin, stamp, voucher, redeem
- CRM: customer, member
- Order monitor (KDS - Kitchen Display System)
- Printer agent (struk, kitchen ticket)
- Laporan penjualan
- Android API
- Event scope (POS untuk event khusus)

**Perbaikan dari `core`:**
- Void langsung terintegrasi ke inventory (return to stock)
- Loyalty lebih clean (point + stamp + voucher dalam satu sistem)
- Printer routing lebih terstruktur

**Output yang diharapkan:**
- [ ] SQL schema: `finance/sql/2026-05-XX_pos_schema.sql`
- [ ] Controllers: semua `Pos_*` dan `Crm_*`
- [ ] Migration script dari `core`

---

## Tahap 10 — Keuangan & Akuntansi

**Tujuan:** Manajemen keuangan lengkap: bank, hutang, piutang, laporan.

**Modul:**
- Transaksi bank (masuk/keluar/transfer)
- Opening saldo bank bulanan
- Account Payable (hutang ke vendor)
- Account Receivable (piutang dari customer)
- Cash advance keuangan
- Rekap rekening harian
- Laporan arus kas
- Monthly management summary
- Financial estimation

**Output yang diharapkan:**
- [ ] SQL schema: `finance/sql/2026-05-XX_finance_schema.sql`
- [ ] Controllers: `Banking`, `Ap_documents`, `Ar_documents`, `Finance_reports`
- [ ] Migration script dari `core`

---

## Tahap 11 — Reports & Dashboard

**Tujuan:** Centralized reporting dan dashboard eksekutif.

**Modul:**
- Reports hub (laporan produk, promo, operasional)
- Dashboard utama (KPI real-time)
- Laporan manajemen bulanan

---

## Catatan Perubahan Roadmap

| Tanggal | Perubahan | Alasan |
|---|---|---|
| 2026-05-01 | Roadmap pertama dibuat | Kickoff project |
| 2026-05-01 | Tambah prinsip mobile-friendly | Kebutuhan operasional |
| 2026-05-01 | Revisi RBAC: lebih sederhana | Hindari over-engineering seperti di `core` |
| 2026-05-01 | Revisi sidebar: utama vs pribadi | Pembagian di `core` kurang tepat |
| 2026-05-01 | Revisi skema gaji: gaji_pokok, tunjangan_jabatan, tunjangan_objektif | Lebih relevan dengan struktur gaji nyata |
| 2026-05-01 | Konfirmasi inventori tetap dipisah (gudang vs material) | Sesuai kebutuhan operasional divisi |
| 2026-05-01 | Revisi konsep material: tetap pisah dari item, tambah mst_material_item_source | Kasus BEBEK vs ITIK/BEBEK PEKING |
| 2026-05-03 | Revisi urutan eksekusi tahap | Dependensi POS pada purchase/inventory/produksi lebih kritikal |
| 2026-05-03 | Penetapan tahap next eksekusi = Tahap 6 (Purchase) | Menjaga alur operasional FnB tetap maju dengan checklist terukur |
| 2026-05-03 | Tata ulang roadmap global + master checklist 0-11 + gate perpindahan tahap | Progress antar tahap lebih terukur dan keputusan "masih Tahap 2 atau lanjut" menjadi eksplisit |
| 2026-05-03 | Penutupan gate Tahap 2 didokumentasikan | Struktur final schema + kontrak snapshot ledger ditegaskan dalam dokumen khusus tahap 2 |
| 2026-05-03 | Start implementasi Tahap 6 dengan foundation DDL purchase + dokumen struktur | Transisi gate Tahap 2 -> Tahap 6 dieksekusi dan dibuktikan artefak SQL + docs |
| 2026-05-03 | Tambah fondasi payment purchase + receipt gudang/divisi | Menjawab kebutuhan metode pembayaran dan tujuan masuk PO tanpa menunggu modul keuangan penuh |
| 2026-05-03 | Tambah fondasi rekening, kas, stok terdampak purchase, dan audit trail | Praktik purchase bisa langsung diuji pengurangan saldo dan mutasi stok dengan jejak audit |
| 2026-05-06 | Mulai fondasi Tahap 7: split opening gudang/divisi, monthly opname + generate opening, dan adjustment 5 komponen | Menutup gap proses data awal stok dan kesiapan rollup harian sesuai kebutuhan operasional |
| 2026-05-07 | Hardening konsistensi profile opening berbasis catalog + remap historis DIVISION selesai | Menetapkan catalog sebagai source of truth profile_key dan membersihkan mismatch historis exact identity di scope divisi |
| 2026-05-07 | Baseline remap WAREHOUSE dicatat (pending eksekusi) | Menjaga kesinambungan kerja lintas perangkat: status backlog terukur sebelum apply berikutnya |
| 2026-05-07 | Tambah dokumen handoff progress lintas perangkat | Memudahkan lanjut kerja dari laptop lain dengan snapshot status, backlog, dan rencana eksekusi terdekat |
