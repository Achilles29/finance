# Roadmap Pengembangan — Finance App
**Terakhir diperbarui:** 2026-06-01 (POS cashier/payment/report/shift-close hardening + snapshot rekening tutup shift + printer workspace parity review)  
**Target selesai:** 31 Mei 2026 (stabilisasi)  
**Target live:** 1 Juni 2026

> Dokumen ini adalah **satu-satunya sumber kebenaran roadmap**. Roadmap lama di file lain sudah diarsipkan. Semua update progress ditulis di sini.

---

## Prinsip Pengembangan

| Prinsip | Penjelasan |
|---|---|
| **Schema-first** | Tabel dirancang dan didokumentasikan sebelum coding |
| **Alur bisnis dulu** | Setiap modul baru didokumentasikan dalam bahasa user sebelum masuk kode |
| **Modul per modul** | Satu modul selesai end-to-end sebelum pindah ke berikutnya |
| **Setiap perubahan dicatat** | SQL di `finance/sql/`, dokumen di `finance/docs/` |
| **Data `core` bisa dimigrasi** | Setiap tabel baru harus ada rencana migrasi dari `core` |
| **Mobile-friendly** | Semua halaman harus responsif dan nyaman di layar HP |
| **Tidak ada ALTER tanpa file SQL** | Perubahan tabel = file SQL baru bernomor |

---

## Status Overview

```
Tahap 0  — Fondasi & Arsitektur         ✅ SELESAI
Tahap 1  — Auth, RBAC & Sidebar         🟡 BERJALAN (90%)
Tahap 2  — Master Data                  ✅ GATE CLOSED
Tahap 3  — HR & Organisasi              🟡 BERJALAN (80%)
Tahap 4  — Absensi                      🟡 BERJALAN (95%)
Tahap 5  — Payroll & Penggajian         🟡 BERJALAN (85%)
Tahap 6  — Pembelian (Purchase)         🟡 BERJALAN (93%)
Tahap 7  — Inventori & Gudang           🟡 BERJALAN (82%)
Tahap 8  — Produksi & COGS              🟡 BERJALAN (66%)
Tahap 9  — POS                          🟡 BERJALAN (78%)
Tahap 10 — Keuangan & Akuntansi         🟠 FONDASI DIMULAI
Tahap 11 — Reports & Dashboard          🔲 BELUM MULAI
```

---

## Jalur Eksekusi (Prioritas)

```
Jalur A — Operasional Barang:   Tahap 6 → Tahap 7 → Tahap 8 → Tahap 9
Jalur B — SDM Inti:             Tahap 3 → Tahap 4 → Tahap 5
Jalur C — Payroll Operasional:  Tahap 5 (hardening)
Jalur D — Landasan POS+Finance: Tahap 9 (desain) → Tahap 10 (fondasi)
```

**Paralel terkontrol** — Jalur A dan B berjalan bersamaan, tapi output wajib:
- Satu sumber data absensi/payroll (`att_daily` + payroll immutable)
- Satu sumber profile inventory (`mst_purchase_catalog`)
- Satu pola posting saldo rekening (`fin_company_account` + `fin_account_mutation_log`)

---

## Detail Per Tahap

---

### TAHAP 0 — Fondasi & Keputusan Arsitektur ✅

**Status:** SELESAI (2026-05-01)

**Output:**
- [x] Scan modul `core` → `2026-05-01a_scan_core_modules.md`
- [x] Keputusan arsitektur → `2026-05-01b_keputusan_arsitektur.md`
- [x] Konsep inventori FnB → `2026-05-01c_konsep_inventori_fnb.md`
- [x] Roadmap ini (versi baru: `ROADMAP.md`)
- [x] Alur bisnis user → `2026-05-01e_alur_bisnis_user.md`
- [x] Konsep HR & Payroll → `2026-05-01f_konsep_hr_payroll.md`

---

### TAHAP 1 — Auth, RBAC & Sidebar 🟡

**Status:** 90% berjalan (belum: dokumen penutup tahap, finalisasi RBAC employee-role sync)

**Tujuan:** Fondasi login, manajemen user, permission, dan navigasi sidebar yang bersih.

**Yang sudah berjalan:**
- [x] Login / logout dengan bcrypt
- [x] Sidebar dinamis + favorit user
- [x] Modul manajemen sidebar admin
- [x] RBAC: role, permission, halaman
- [x] Session logging (login/logout)
- [x] Dual portal: Company ↔ Employee (My)
- [x] Permission per halaman, cek `can()` di controller dan view

**Yang belum selesai:**
- [x] Integrasi role mapping otomatis employee → role saat employee ditambahkan (`Users.php` — `_merge_position_default_role()`)
- [x] RBAC UI: user count per role clickable, division scope pada role (`auth_role.division_scope_id`), view daftar user per role
- [ ] Audit log akses terintegrasi di `sys_audit_log`

**Tabel kunci:**
`auth_user`, `auth_session_log`, `auth_role`, `auth_permission`, `auth_role_permission`, `auth_user_role`, `auth_user_permission_override`, `sys_menu`, `sys_menu_permission`, `sys_sidebar_favorite`, `sys_audit_log`

---

### TAHAP 2 — Master Data ✅ GATE CLOSED

**Status:** SELESAI (Gate ditutup 2026-05-03)

**Yang sudah berjalan:**
- [x] UOM (satuan ukur) + konversi satuan
- [x] Item master + UOM pack config
- [x] Material master + kategori
- [x] Material-item source map (`mst_material_item_source`)
- [x] Purchase catalog (`mst_purchase_catalog`) — profil pembelian historis
- [x] Component master (BASE / PREPARE) + formula
- [x] Product master + resep
- [x] Extra group + mapping produk
- [x] Vendor master
- [x] Rekening bank perusahaan (`fin_company_account`)
- [x] Divisi operasional + divisi produk
- [x] Jabatan / posisi

**Keputusan desain kunci (tidak boleh diubah):**
- `mst_item` dan `mst_material` tetap dipisah (lihat `2026-05-01c_konsep_inventori_fnb.md`)
- UOM BELI ≠ UOM ISI (kemasan vendor tidak mengubah identitas item)
- Profil variasi beli disimpan di `mst_purchase_catalog`, bukan di master item
- Component type: BASE (paling dasar) → PREPARE (siap pakai)

**Dokumen gate:** `2026-05-03e_penutupan_gate_tahap2_schema_snapshot.md`

---

### TAHAP 3 — HR & Organisasi 🟡

**Status:** 85% (master operasional berjalan, masih perlu: RBAC sync otomatis)

**Yang sudah berjalan:**
- [x] CRUD employee (nama, jabatan, divisi, NIP, rekening bank, status)
- [x] CRUD jabatan (posisi) dan divisi
- [x] Contract master: template, generate, lifecycle (DRAFT→APPROVE→SIGN)
- [x] Contract PDF + QR verification
- [x] Adopsi data pegawai dari `core` berjalan

**Yang belum selesai:**
- [x] Integrasi role mapping otomatis employee → role (jabatan → `org_position.default_role_id` → `auth_role`)
- [ ] Halaman riwayat kontrak per karyawan yang rapi

---

### TAHAP 4 — Absensi 🟡

**Status:** 95% (hampir semua fitur berjalan, hanya export yang tersisa)

**Yang sudah berjalan:**
- [x] Tabel absensi terpadu `att_daily` (single source of truth)
- [x] Admin: input manual absensi harian
- [x] Admin: halaman `settings`, `daily`, `logs`, `schedules`, `pending-requests`, `anomalies`, `master-health`, `estimate`
- [x] Employee: clock in/out dari portal My (`My::attendance_mark`)
- [x] Master: shift, lokasi, hari libur (holiday)
- [x] Policy lock per hari/per period aktif (snapshot policy mode/rate tersimpan di `att_daily`)
- [x] Holiday grant: shift `PH`/`PHB`, anti-duplikat, insert idempotent
- [x] Rekap harian dari `att_presence`
- [x] Workflow approval izin/sakit/lembur: `pending_request_action` + `pending_request_bulk_action` di admin
- [x] Employee portal pengajuan izin/sakit/koreksi (`My::leave_requests` POST handler aktif)
- [x] Employee portal pembatalan pengajuan (`My::leave_request_cancel`)
- [x] PH balance + ledger admin (`Attendance::ph_ledger`, `ph_assignments`, `ph_recap`)
- [x] PH ledger employee (`My::ph_ledger`)
- [x] Overtime entries admin + employee portal (`My::overtime`)
- [x] Meal calendar dan meal ledger employee

**Yang belum selesai:**
- [ ] Laporan absensi export (CSV/XLS)

---

### TAHAP 5 — Payroll & Penggajian 🟡

**Status:** 85% (period/batch/slip aktif, kasbon, meal disbursement — semua inti berjalan. Sisa: THR dan export)

**Yang sudah berjalan:**
- [x] Profil gaji karyawan (gaji pokok, tunjangan jabatan, tunjangan objektif)
- [x] Period payroll management
- [x] Engine kalkulasi bulanan dari `att_daily` (potongan terlambat, alpha, kasbon)
- [x] Generate batch gaji → Mark PAID → VOID dengan guard status
- [x] Slip gaji (cetak admin + employee portal My)
- [x] Kasbon: tenor opsional, metode CASH/TRANSFER/SALARY_CUT
- [x] Meal disbursement: status PAID konsisten ke mutasi rekening
- [x] Lock period immutable: manual adjustment / overtime tidak bisa diubah jika period sudah punya batch aktif
- [x] Audit checker payroll period (UI + CLI `tools/payroll_audit_checker.php`)
- [x] Guard dobel kandidat disbursement (NOT EXISTS + unique per payroll_result)
- [x] Tab gaji final employee pakai snapshot line disbursement agar histori tidak drift
- [x] Employee portal: payroll, payroll_slip, cash_advance, manual_adjustments

**Yang belum selesai:**
- [ ] THR / bonus period terintegrasi ke hasil payroll
- [ ] Laporan payroll export

---

### TAHAP 6 — Pembelian (Purchase) 🟡

**Status:** 93% (PO/SR utama sudah berjalan, Division PO SR aktif, reader procurement aktif sudah mulai pindah ke stock bulanan; sisa hardening utama pindah ke expiry/lot, compatibility cleanup, dan dokumentasi user)

**Yang sudah berjalan:**
- [x] Purchase Order CRUD + status flow
- [x] Katalog purchase (search profile + fallback master item)
- [x] Upsert catalog profile saat simpan PO line
- [x] Payment plan purchase (`pur_purchase_payment_plan`)
- [x] Receipt masuk gudang / divisi
- [x] Potong saldo akun perusahaan via payment channel
- [x] Stok terdampak purchase + log audit
- [x] Opening stok gudang / divisi (split halaman)
- [x] Opname bulanan + opening bulan berikutnya
- [x] Komponen penyesuaian stok: WASTE/SPOILAGE/PROCESS_LOSS/VARIANCE/ADJUSTMENT_PLUS
- [x] Remap key catalog untuk DIVISION dan WAREHOUSE
- [x] Permission + menu RBAC purchase
- [x] UOM BELI / UOM ISI (`buy_uom_id` + `content_uom_id`) ada di controller dan data layer
- [x] Store Request penuh: CRUD, SUBMIT/APPROVE/REJECT, FULFILL, VOID (`Procurement` controller)
- [x] Store Request → generate PO otomatis (`store_request_generate_po`)
- [x] Division PO SR: workbench penghubung SR ke PO
- [x] Pengajuan divisi: verify per line, fallback gudang → katalog purchase, PDF server-side, dan UOM PACK/ISI
- [x] Helper procurement aktif untuk profile gudang, unit cost SR, dan stok tersedia gudang mulai diputus dari `inv_warehouse_stock_balance` dan diarahkan ke stock bulanan

**Yang belum selesai:**
- [ ] UI form PO: tampilkan konteks pack profile (nama brand, isi/kemasan) saat memilih item (UX enhancement)
- [ ] Dokumentasi alur user Purchase + Store Request final
- [ ] Hardening rule split Division Request → SR/PO supaya guard route dan rollback dokumen turunannya makin tegas
- [ ] Bersihkan sisa jalur compatibility procurement yang masih menyentuh tabel legacy saat rebuild/repair dan costing fallback non-aktif

---

### TAHAP 7 — Inventori & Gudang 🟡

**Status:** 82% (opening/opname/receipt/views berjalan, reader aktif gudang/divisi mulai pindah ke stock bulanan dan daily movement-first; repair/rebuild purchase yang disentuh juga makin monthly-only. Sisa berat ada di lot/expiry-aware ledger, compatibility cleanup, dan distribusi otomatis)

**Yang sudah berjalan:**
- [x] Opening stok gudang dan divisi
- [x] Opname bulanan gudang dan divisi
- [x] Ledger pergerakan stok (log)
- [x] Balance stok gudang dan divisi
- [x] Komponen adjustment (waste, variance, dll.)
- [x] Receipt purchase → posting stok gudang (`receipt_store` aktif)
- [x] View daily matrix gudang dan material
- [x] View movement gudang dan divisi
- [x] Flow item → material (`Inventory_flow::item_material` + `inv_item_material_source_map`)
- [x] Landasan audit lot/expiry untuk procurement sudah dipetakan di dokumen desain dan audit 2026-05-22 / 2026-05-24
- [x] Dashboard, opening search, stock list, dan helper saldo aktif purchase/procurement mulai membaca `inv_warehouse_monthly_stock` / `inv_division_monthly_stock` atau movement log, bukan `inv_*_stock_balance`
- [x] Wording UI stok yang disentuh mulai memakai istilah `stok bulanan` / `proyeksi harian`, bukan `stock balance` / `daily rollup`
- [x] InventoryLedger aktif untuk gudang/divisi sudah menulis movement log + monthly stock tanpa mutasi aktif ke `inv_warehouse_stock_balance` / `inv_division_stock_balance`
- [x] Bootstrap FIFO gudang yang disentuh sudah membaca saldo agregat dari `inv_warehouse_monthly_stock`, bukan `inv_warehouse_stock_balance`
- [x] Rebuild histori opening yang disentuh sudah sinkron langsung ke monthly stock tanpa write aktif ke `inv_*_daily_rollup` / `inv_*_stock_balance`
- [x] Repair reconcile bahan divisi yang disentuh sudah membangun ulang histori dari movement log langsung ke monthly stock tanpa rewrite aktif ke `inv_division_daily_rollup` / `inv_division_stock_balance`
- [x] Verifikasi pasca-rebuild dan guard opening divisi yang disentuh sekarang mengikuti monthly stock, bukan lagi menerima `stock_balance` sebagai jalur aktif
- [x] Fallback stock list/current-balance yang disentuh di `Purchase_model` sudah dibersihkan dari query aktif ke `inv_warehouse_stock_balance` / `inv_division_stock_balance`
- [x] Registrasi target rebuild saat VOID PO yang disentuh sekarang mengambil identity terkait dari `inv_stock_movement_log.receipt_line_id`, bukan dari `stock_balance`
- [x] Opening stok yang disentuh sekarang merepost saldo opening final ke movement log setelah replace/update snapshot, jadi daily/movement/reconcile tidak lagi kehilangan opening saat mode `set saldo live persis`
- [x] Resolver opening yang disentuh sekarang ikut mempertimbangkan harga saat memilih/membuat `profile_key`, sehingga perubahan harga profile tidak lagi diam-diam reuse profile lama
- [x] Resolver opening dari catalog yang disentuh sekarang membandingkan harga profile dalam basis harga beli catalog (`avg_cost_per_content x content_per_buy`), jadi pilih catalog harga `300.000` tidak lagi salah membuat profile baru berharga `300`
- [x] Reconcile bahan divisi yang disentuh sekarang mengambil closing `Material Daily` terakhir per identity pada tanggal audit, bukan menjumlah seluruh closing harian dalam rentang bulan yang sama

**Yang belum selesai:**
- [ ] Distribusi otomatis gudang item → stok material via `mst_material_item_source` (trigger dari receipt/transfer)
- [ ] Receipt PO dan fulfill SR harus menyimpan/memakai lot aktual end-to-end
- [ ] Rekey profile procurement agar expiry tidak lagi menjadi bagian identity catalog/profile
- [ ] Hardening ledger: konsistensi balance setelah setiap transaksi diaudit end-to-end
- [ ] Bersihkan sisa helper report/reconcile/repair compatibility yang masih membaca atau menghapus `inv_warehouse_daily_rollup`, `inv_division_daily_rollup`, `inv_warehouse_stock_balance`, atau `inv_division_stock_balance`

---

### TAHAP 8 — Produksi & COGS 🟡

**Status:** 66% (surface component master/formula/usage dan editor operasional sudah lebih utuh; reader aktif component mulai pindah ke stock bulanan/proyeksi movement; repair/reconcile component yang disentuh juga makin monthly-only. COGS dan integrasi lintas modul masih tahap berikutnya)

**Yang sudah berjalan:**
- [x] Stok Base/Prepare
- [x] Mutasi Base/Prepare
- [x] Opening Base/Prepare dengan editor baris, tanpa JSON mentah
- [x] Adjustment Base/Prepare dengan editor baris, tanpa JSON mentah
- [x] Daily matrix Base/Prepare
- [x] Batch produksi component (BASE/PREPARE) dengan editor input material/component
- [x] Workbench navigasi component: master, formula, variable cost, dan operasional sudah terhubung konsisten
- [x] Monthly carry-forward component ke monthly opname + opening bulan berikutnya mulai membaca proyeksi harian movement-first
- [x] AJAX picker component/material menggantikan dropdown statis di surface operasional component
- [x] Usage tracking component tampil di master dan formula, dengan halaman usage detail terpisah
- [x] HPP live component master diselaraskan dengan formula summary + cache request-level untuk list yang lebih ringan
- [x] Action icon component distandarkan lintas halaman dan dicatat di coding standards
- [x] Reader aktif component untuk daily/monthly/reconcile/stock utama mulai membaca `inv_component_monthly_stock` + `inv_component_movement_log`, bukan `inv_component_daily_rollup` / `inv_component_stock_balance`
- [x] Writer/helper component dan POS yang disentuh mulai mengambil snapshot saldo awal dari stock bulanan component, bukan balance legacy
- [x] Writer aktif component yang disentuh sudah menulis movement log + monthly stock tanpa dual-write aktif ke `inv_component_stock_balance` / `inv_component_daily_rollup`
- [x] Repair rebuild component per identity yang disentuh sekarang menyinkronkan ulang histori dari `inv_component_movement_log` langsung ke `inv_component_monthly_stock`, tanpa rewrite aktif ke `inv_component_daily_rollup` / `inv_component_stock_balance`
- [x] Posting/void opening-adjustment component tidak lagi memicu rebuild aktif ke `inv_component_daily_rollup`; reader proyeksi harian juga tidak lagi double-count movement `OPENING` yang sudah masuk seed `inv_component_monthly_stock`
- [x] Rollback/void movement component yang disentuh tidak lagi update `inv_component_stock_balance`; sesudah reverse log dibuat, monthly stock identity terkait direbuild ulang dari `inv_component_movement_log`
- [x] Utility `Purchase_model` yang memilih profile key kanonik sekarang memberi bobot utama ke stock bulanan gudang/divisi, bukan lagi ke `stock_balance` / `daily_rollup` legacy

**Yang belum selesai:**
- [ ] COGS calculation: HPP aktual dari batch
- [ ] Integrasi: konsumsi stok dari POS saat order
- [ ] Hardening carry-forward component: audit conflict manual opening dan review UX posting ke dokumen operasional
- [ ] Satukan detail/edit formula dan halaman turunan component lain ke pola workbench yang sama sampai benar-benar terasa satu modul
- [ ] Browser smoke test visual untuk seluruh halaman component setelah hardening UI terakhir
- [ ] Bersihkan sisa helper compatibility component yang masih menyentuh `inv_component_daily_rollup` dan `inv_component_stock_balance` pada jalur rebuild, repair, dan recovery historis

---

### TAHAP 9 — POS (Point of Sale) 🟡

**Status:** 78% (cashier/order draft/paid orders, payment final, void/refund, printer workspace, laporan POS, dan tutup shift dengan snapshot kas/rekening sudah berjalan; gap utama yang tersisa adalah loyalty native di cashier, monitor dapur/checker, reprint/audit order yang lebih matang, dan mobile/customer-display surface)

**Target minimum viable POS (MVP):**
- [x] Order draft/cashier: tambah item, extra, review, confirm cepat
- [x] Payment: tunai, QRIS, kartu, split
- [x] Void / refund order (fondasi + preview + snapshot reversal)
- [x] Stock deduction otomatis saat order via queue background + retry audit
- [x] Shift management dasar (buka/tutup sesi kasir + preview tutup shift)
- [x] Printer KOT/direct print saat confirm order
- [ ] Loyalty (minimal: poin bertambah saat bayar)

**Yang sudah berjalan:**
- [x] Cashier workbench + order draft
- [x] Extra per produk, konfigurasi modal cepat, dan direct print KOT
- [x] Workspace paid orders dengan detail pembayaran, refund, dan void per order
- [x] Payment final POS dengan split method, voucher lookup, update metode pembayaran transaksi, dan posting mutasi rekening perusahaan
- [x] Void/refund POS dengan direct print slip, refresh availability, dan laporan detail
- [x] Laporan POS: sales summary, sales detail per produk, sales transaction audit, payment, refund, dan void
- [x] Printer workspace Finance-native: template, output profile, device printer, live preview, guide, bootstrap, dan test print
- [x] Tutup shift kasir dengan preview pendapatan, input pecahan cash, direct print, snapshot pecahan, dan snapshot rekap rekening per shift
- [x] Snapshot stock commit POS (`pos_stock_commit`)
- [x] Queue runtime stock commit (`pos_runtime_job`) dengan status `QUEUED/PROCESSING/FAILED`
- [x] Audit retry job gagal di Stock Live POS dan Reconcile Stok Divisi
- [x] Worker CLI untuk pemrosesan background
- [x] Reader live availability POS yang disentuh mulai membaca snapshot material/component dari stock bulanan, bukan balance legacy aktif
- [x] Writer stok component dari POS yang disentuh sudah menulis movement log + monthly stock tanpa dual-write aktif ke tabel component legacy

**Yang belum selesai:**
- [ ] Loyalty point/stamp/voucher native di flow cashier dan histori customer POS
- [ ] Monitor dapur/bar/checker untuk ack-ready-check per order seperti surface `core`
- [ ] Reprint order/receipt dan halaman histori order yang lebih matang untuk audit operasional
- [ ] Printer routing/job monitoring parity penuh seperti `core` (`routes` + job board), bukan hanya direct print + device/profile/template workspace
- [ ] Mobile API / customer display surface untuk POS non-desktop
- [ ] Dashboard operasional kasir dan monitoring throughput order
- [ ] Rapikan kontrak rebuild availability agar seluruh writer stok non-POS menandai affected product tanpa bergantung ke tabel balance legacy

**COGS dan laporan margin bisa menyusul** — jangan block POS karena Tahap 8 belum selesai.

---

### TAHAP 10 — Keuangan & Akuntansi 🟠

**Status:** FONDASI DIMULAI (2026-05-13)

**Yang sudah berjalan:**
- [x] Rekening perusahaan (`fin_company_account`) aktif
- [x] Mutation log rekening dipakai oleh purchase dan payroll
- [x] Payment/deposit/refund/void correction POS yang relevan sudah memakai posting mutasi rekening perusahaan
- [x] Tutup shift POS sekarang menyimpan snapshot rekap rekening per shift (`pos_shift_account_summary`) untuk audit kasir

**Yang belum selesai:**
- [ ] Halaman mutasi rekening (filter bulan berjalan, ringkasan saldo)
- [ ] Bank transaction: opening + AR/AP
- [ ] Cash flow dan monthly summary
- [ ] Integrasi posting transaksi lintas modul (purchase/POS/payroll) tuntas dan diaudit

---

### TAHAP 11 — Reports & Dashboard 🔲

**Status:** BELUM MULAI

**Rencana:**
- [ ] Reports hub lintas modul
- [ ] Dashboard KPI real-time (sales, stok, payroll)
- [ ] Paket laporan manajemen bulanan (PDF/Excel)

---

## Checklist Gate Pindah Tahap

| Dari → Ke | Gate Condition |
|---|---|
| Tahap 2 → Tahap 6 | ✅ Terpenuhi (gate ditutup 2026-05-03) |
| Tahap 6 → Tahap 7 | Inventory ledger posting dari purchase berjalan |
| Tahap 7 → Tahap 8 | Balance stok gudang dan material konsisten dan teraudit |
| Tahap 8 → Tahap 9 | COGS dari batch produksi terhitung (minimal 1 component type) |
| Semua → Tahap 11 | Fondasi operasional (POS minimal + payroll + purchase) stabil |

---

## Target Timeline

| Tanggal | Target |
|---|---|
| 20 Mei 2026 | Tahap 4 selesai: export absensi CSV/XLS |
| 22 Mei 2026 | Tahap 5: THR/bonus terintegrasi ke payroll |
| 24 Mei 2026 | Tahap 3: auto role mapping employee → role |
| 26 Mei 2026 | Tahap 6: UX pack profile context di form PO (polish) |
| 28 Mei 2026 | Tahap 7: distribusi otomatis item → material via `mst_material_item_source` |
| 31 Mei 2026 | Stabilisasi — semua modul yang ada bebas bug kritis |
| 1 Jun 2026 | Go-live: modul HR/Payroll/Purchase/Inventory aktif |
| 30 Jun 2026 | POS minimum viable berjalan |
