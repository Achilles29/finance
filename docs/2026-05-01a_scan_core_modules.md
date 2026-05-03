# Scan Modul `core` — Inventarisasi Lengkap
**Tanggal:** 2026-05-01  
**Tujuan:** Mendokumentasikan semua yang sudah ada di direktori `core` sebagai referensi pembangunan `finance`

---

## Struktur Direktori `core`

```
core/
├── application/
│   ├── config/
│   ├── controllers/   (80+ controller)
│   ├── models/        (75+ model)
│   ├── views/         (50+ folder view)
│   ├── helpers/
│   ├── libraries/
│   └── hooks/
├── sql/               (200+ file migration)
└── assets/
```

---

## Modul & Controller

### 1. Auth & User Management
| Controller | Fungsi |
|---|---|
| `Auth.php` | Login, logout, session |
| `Users.php` | CRUD user sistem |
| `Access_control.php` | Role matrix, RBAC per halaman, override per user |
| `Sidebar_preferences.php` | Favorit sidebar (server sync) |

**Masalah di `core`:**
- Role matrix tumbuh organik, banyak patch permission via SQL migration
- Role dan position mapping masih ada inkonsistensi

---

### 2. Master Data

| Controller | Entitas | Catatan |
|---|---|---|
| `Master_uom.php` | Satuan ukur | Baik |
| `Master_uom_conversions.php` | Konversi satuan | Baik |
| `Master_items.php` | Item (semua barang beli) | Tabel `m_item` |
| `Master_materials.php` | Bahan baku + kategori | Tabel `m_material` |
| `Master_material_categories.php` | Kategori bahan baku | |
| `Master_components.php` | Bahan setengah jadi | Tabel `m_component` |
| `Master_component_categories.php` | Kategori komponen | |
| `Master_component_formulas.php` | Formula/resep komponen | |
| `Master_products.php` | Produk jual + kategori | Tabel `m_product` |
| `Master_product_categories.php` | Kategori produk | |
| `Master_product_classifications.php` | Klasifikasi produk | |
| `Master_product_divisions.php` | Divisi produk | |
| `Master_product_recipes.php` | Resep produk | |
| `Master_vendors.php` | Vendor/supplier | |
| `Master_bank_accounts.php` | Rekening bank | |
| `Master_divisions.php` | Divisi/departemen | |
| `Master_counterparties.php` | Rekanan (vendor+customer) | Konsep hybrid, agak rancu |
| `Master_posting_types.php` | Tipe posting akuntansi | |
| `Master_purchase_types.php` | Tipe pembelian | |
| `Master_purchase_references.php` | Referensi belanja | |

**Masalah di `core`:**
- `m_item`, `m_material`, `m_component` overlapping secara konsep (akan dibahas di dokumen tersendiri)
- `m_counterparty` adalah konsep gabungan vendor+customer yang rancu
- Prefix `m_` terlalu generik

---

### 3. HR & Organisasi

| Controller | Fungsi |
|---|---|
| `Hr_contracts.php` | Kontrak karyawan + QR verify |
| `Hr_contract_verify.php` | Verifikasi kontrak via QR |
| `Employee_dashboard.php` | Dashboard info karyawan |
| `Employee_finance.php` | Info keuangan karyawan |
| `Employee_self.php` | Self-portal: slip gaji, absensi, uang makan |

**Tabel utama:** `org_employee` (NIP otomatis, gender, lahir, alamat, join date, jabatan)

**Masalah di `core`:**
- Legacy identity columns sempat ada, lalu di-drop
- `org_employee` punya banyak field yang ditambah bertahap (kurang terencana)

---

### 4. Attendance (Absensi)

| Controller | Fungsi |
|---|---|
| `Attendance.php` | Manajemen absensi umum |
| `Attendance_employee.php` | Absensi per karyawan |
| `Attendance_masters.php` | Master: aturan absensi, holiday calendar |

**Tabel utama:**
- `pay_attendance_rule` — aturan potongan terlambat/alpha
- `pay_attendance_device` — device GPS/faceprint
- `pay_attendance_raw_log` — raw log dari device
- `pay_attendance_daily` — rekap harian (baru/clean)
- `att_daily_recap` — rekap harian (legacy dari kasir lama)
- `att_holiday_calendar` — kalender hari libur

**Masalah di `core`:** ⚠️
- **HYBRID**: ada 2 tabel rekap harian (`att_daily_recap` legacy vs `pay_attendance_daily` baru)
- Workflow approval pending masih belum sepenuhnya terintegrasi
- Prefix campur aduk: `pay_attendance_*` dan `att_*`

---

### 5. Payroll & Penggajian

| Controller | Fungsi |
|---|---|
| `Payroll_standards.php` | Standar gaji: basic, komponen, profil, assignment |
| `Payroll_thr.php` | THR otomatis |
| `Payroll_bonus.php` | Bonus omzet |
| `Salary_disbursements.php` | Pencairan gaji |
| `Meal_disbursements.php` | Pencairan uang makan mingguan |
| `Cash_advances.php` | Kasbon karyawan + cicilan |

**Tabel utama:**
- `pay_salary_component` — komponen gaji
- `pay_salary_profile` — profil gaji
- `pay_salary_assignment` — assignment profil ke karyawan
- `pay_attendance_payroll_result` — hasil kalkulasi payroll bulanan
- `pay_meal_disbursement` + `pay_meal_disbursement_line`
- `fin_cash_advance` + instalmen
- `pay_thr_*` — tabel THR
- `pay_bonus_omzet_*` — bonus omzet

**Masalah di `core`:**
- Standarisasi payroll di-refactor beberapa kali (ada stage1, stage2, cleanup)
- Uang makan sempat ada di modul attendance lalu dipindah ke finance

---

### 6. Pembelian (Purchase)

| Controller | Fungsi |
|---|---|
| `Purchase_orders.php` | PO: buat, detail, approval |
| `Purchase_logs.php` | Log/riwayat pembelian |
| `Purchase_reports.php` | Laporan pembelian, price pulse |
| `Store_requests.php` | Permintaan barang per divisi |
| `Division_requests.php` | Request barang dari divisi ke gudang/purchase |

**Tabel utama:**
- `pur_purchase_order` + `pur_purchase_order_line`
- `pur_purchase_catalog` — katalog harga supplier
- `rsp_store_request` + `rsp_store_request_line`
- `m_purchase_type`, `m_purchase_reference` — konfigurasi PO

**Masalah di `core`:**
- `rsp_` prefix tidak jelas
- Catalog tipe filter pernah di-refactor beberapa kali

---

### 7. Inventory / Gudang

| Controller | Fungsi |
|---|---|
| `Inventory.php` | Stok gudang (item) |
| `Inventory_adjustments.php` | Penyesuaian stok |
| `Warehouse_openings.php` | Opening balance gudang |
| `Material_openings.php` | Opening balance material |
| `Material_mutations.php` | Mutasi material |
| `Material_adjustments.php` | Penyesuaian material |

**Tabel utama:**
- `inv_warehouse_balance` — saldo gudang item
- `inv_warehouse_ledger` — ledger item
- `inv_warehouse_opening` — opening balance
- `rsp_material_stock_*` — stok material (bahan baku)
- `rsp_material_opening`, `rsp_material_mutation`, `rsp_material_adjustment`
- `rsp_material_lot` — lot tracking bahan baku

**Masalah di `core`:**
- Item inventory (`inv_`) dan material inventory (`rsp_`) terpisah dengan mekanisme berbeda
- `rsp_` prefix sangat tidak intuitif untuk inventory material

---

### 8. Produksi

| Controller | Fungsi |
|---|---|
| `Production.php` | Batch produksi produk & komponen |
| `Component_openings.php` | Opening balance komponen |
| `Component_mutations.php` | Mutasi komponen |
| `Component_adjustments.php` | Penyesuaian komponen |
| `Cogs_settings.php` | Pengaturan HPP |

**Tabel utama:**
- `prd_product_batch` — batch produksi produk
- `prd_component_batch` — batch produksi komponen/prepare
- `prd_component_lot` — lot tracking komponen
- `prd_product_monthly_availability` — ketersediaan produk bulanan

**Masalah di `core`:**
- Lot tracking component masih belum sempurna

---

### 9. POS (Point of Sale)

| Controller | Fungsi |
|---|---|
| `Pos_outlets.php` | Master outlet |
| `Pos_terminals.php` | Master terminal kasir |
| `Pos_shifts.php` | Sesi shift kasir |
| `Pos_cashier.php` | Antarmuka kasir utama |
| `Pos_orders.php` | Manajemen order |
| `Pos_order_monitor.php` | Monitor order real-time (KDS) |
| `Pos_payments.php` | Pembayaran order |
| `Pos_payment_methods.php` | Metode pembayaran |
| `Pos_refunds.php` | Refund |
| `Pos_deposits.php` | Deposit pelanggan |
| `Pos_extras.php` + `Pos_extra_groups.php` | Add-on / extra produk |
| `Pos_product_bundles.php` | Bundle produk |
| `Pos_customers.php` | Pelanggan |
| `Pos_voucher_campaigns.php` | Voucher |
| `Pos_voucher_issue_campaigns.php` | Kampanye penerbitan voucher |
| `Pos_voucher_wallets.php` | Wallet voucher pelanggan |
| `Pos_stamp_campaigns.php` | Kampanye stamp |
| `Pos_point_rules.php` | Aturan poin loyalty |
| `Pos_redeem_settings.php` | Pengaturan redeem poin |
| `Pos_void_reports.php` | Void & laporan void |
| `Pos_reports.php` | Laporan penjualan |
| `Pos_printers.php` | Master printer |
| `Pos_printer_agent_api.php` | API agent printer |
| `Pos_printer_jobs.php` | Antrian cetak |
| `Pos_printer_routes.php` | Routing printer ke produk |
| `Pos_printer_templates.php` | Template struk |
| `Pos_android_api.php` | API untuk Android POS |

**Tabel utama:**
- `pos_outlet`, `pos_terminal`, `pos_shift`
- `pos_order`, `pos_order_line`, `pos_order_payment`
- `pos_payment_method`
- `pos_deposit`, `pos_deposit_ledger`
- `pos_extra`, `pos_extra_group`
- `pos_product_bundle`, `pos_product_bundle_line`
- `crm_customer`, `crm_member_account`
- `crm_loyalty_point_bucket`, `crm_stamp_card`
- `pos_voucher_campaign`, `pos_voucher_wallet`
- `pos_void`, `pos_void_ledger`
- `pos_printer`, `pos_printer_job`, `pos_printer_template`, `pos_printer_route`

---

### 10. Keuangan (Finance)

| Controller | Fungsi |
|---|---|
| `Banking.php` | Transaksi bank, transfer |
| `Ap_documents.php` | Dokumen hutang (AP) |
| `Ar_documents.php` | Dokumen piutang (AR) |
| `Finance_arap_reports.php` | Laporan AR/AP |
| `Finance_reports.php` | Laporan keuangan umum |
| `Finance_public.php` | Rekap rekening harian (publik) |

**Tabel utama:**
- `fin_bank_txn` — transaksi bank
- `fin_bank_monthly_opening` — opening saldo bank bulanan
- `fin_ap_document` + `fin_ap_line`
- `fin_ar_document` + `fin_ar_line`
- `fin_cash_advance` + `fin_cash_advance_installment`
- `fin_monthly_management_summary`

---

### 11. Reports Hub

| Controller | Fungsi |
|---|---|
| `Reports_hub.php` | Hub laporan: produk, promo, operasional |

---

### 12. Infrastruktur & Tools

| Controller | Fungsi |
|---|---|
| `Replication_agent.php` + `Db_replication.php` | Sinkronisasi DB remote |
| `Panduan.php` | Halaman panduan/help |
| `Dashboard.php` | Dashboard utama |
| CLI Controllers | Rebuild material, resync stock, rebuild payroll, dll. |

---

## Ringkasan Masalah di `core`

| # | Masalah | Dampak |
|---|---|---|
| 1 | 200+ file migration harian, tumbuh organik | Sulit trace schema, banyak patch |
| 2 | Prefix tabel tidak konsisten (`m_`, `org_`, `pay_`, `att_`, `rsp_`, `prd_`, dll.) | `rsp_` tidak intuitif |
| 3 | Absensi hybrid: 2 tabel rekap (`att_daily_recap` vs `pay_attendance_daily`) | Data ganda, alur tidak jelas |
| 4 | `m_item`, `m_material`, `m_component` konsepnya tumpang tindih | Bingung di mana bahan baku ada |
| 5 | Duplikasi modul (meal di attendance lalu pindah ke finance) | Data legacy tersebar |
| 6 | `m_counterparty` konsep hybrid vendor+customer | Rancu, sulit di-maintain |
| 7 | Legacy columns/tables yang belum dihapus bersih | Schema kotor |
| 8 | Role matrix di-patch via SQL, bukan by design | Sulit audit permission |

---

## Data yang Perlu Dimigrasi ke `finance`

Prioritas data yang perlu diakomodir dari database `core`:

1. `org_employee` → karyawan
2. `crm_customer` + `crm_member_account` → pelanggan
3. `m_material` → bahan baku
4. `m_component` → komponen/prepare
5. `m_product` → produk jual
6. `m_vendor` → vendor
7. `m_bank_account` → rekening bank
8. `pos_outlet` → outlet
9. `att_daily_recap` / `pay_attendance_daily` → data absensi historis
10. `pay_attendance_payroll_result` → data payroll historis
11. `pos_order` → data transaksi POS historis
12. `fin_bank_txn` → data mutasi bank historis
