# Roadmap Pengembangan — Finance App
**Terakhir diperbarui:** 2026-05-18  
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
Tahap 4  — Absensi                      🟡 BERJALAN (70%)
Tahap 5  — Payroll & Penggajian         🟡 BERJALAN (80%)
Tahap 6  — Pembelian (Purchase)         🟡 BERJALAN (75%)
Tahap 7  — Inventori & Gudang           🟡 BERJALAN (40%)
Tahap 8  — Produksi & COGS              🔲 BELUM MULAI
Tahap 9  — POS                          🟠 PERSIAPAN DESAIN
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
- [ ] Integrasi role mapping otomatis employee → role saat employee ditambahkan
- [ ] Finalisasi dokumen status Tahap 1
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

**Status:** 80% (master operasional berjalan, masih perlu: RBAC sync otomatis)

**Yang sudah berjalan:**
- [x] CRUD employee (nama, jabatan, divisi, NIP, rekening bank, status)
- [x] CRUD jabatan (posisi) dan divisi
- [x] Contract master: template, generate, lifecycle (DRAFT→APPROVE→SIGN)
- [x] Contract PDF + QR verification
- [x] Adopsi data pegawai dari `core` berjalan

**Yang belum selesai:**
- [ ] Integrasi role mapping otomatis employee → role (jabatan → role default)
- [ ] Halaman riwayat kontrak per karyawan yang rapi

---

### TAHAP 4 — Absensi 🟡

**Status:** 70% (alur admin + employee aktif, masih perlu: approval flow final, PH ledger)

**Yang sudah berjalan:**
- [x] Tabel absensi terpadu `att_daily` (single source of truth)
- [x] Admin: input manual absensi harian
- [x] Admin: halaman `settings`, `daily`, `logs`, `schedules`, `pending-requests`, `anomalies`, `master-health`, `estimate`
- [x] Employee: clock in/out dari portal My
- [x] Master: shift, lokasi, hari libur (holiday)
- [x] Policy lock per hari/per period aktif (snapshot policy mode/rate tersimpan di `att_daily`)
- [x] Holiday grant: shift `PH`/`PHB`, anti-duplikat, insert idempotent
- [x] Rekap harian dari `att_presence`

**Yang belum selesai:**
- [ ] Workflow approval izin/sakit/lembur final (timeline per-level + history)
- [ ] Halaman employee clock in/out + pengajuan koreksi/izin/sakit/lembur end-to-end
- [ ] Halaman PH balance + ledger PH pegawai
- [ ] Laporan absensi export (CSV/XLS)

---

### TAHAP 5 — Payroll & Penggajian 🟡

**Status:** 80% (period/batch/slip aktif, masih perlu: THR, beberapa edge case)

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

**Yang belum selesai:**
- [ ] THR / bonus period terintegrasi ke hasil payroll
- [ ] Sinkronisasi detail breakdown payroll lintas halaman (final check semua angka match)
- [ ] Laporan payroll export

---

### TAHAP 6 — Pembelian (Purchase) 🟡

**Status:** 75% (fondasi berjalan, masih perlu: UOM BELI/ISI di form PO, store request)

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
- [x] Remap key catalog untuk DIVISION (script idempotent tersedia)
- [x] Remap key catalog untuk WAREHOUSE (script tersedia, konflik parsial menunggu merge)
- [x] Permission + menu RBAC purchase

**Yang belum selesai:**
- [ ] Integrasi UOM BELI / UOM ISI di form PO (tampil konteks pack profile)
- [ ] Store Request: permintaan barang dari divisi → PO
- [ ] Dokumentasi alur user Purchase final

---

### TAHAP 7 — Inventori & Gudang 🟡

**Status:** 40% (opening/opname berjalan, posting dari purchase belum)

**Yang sudah berjalan:**
- [x] Opening stok gudang
- [x] Opname bulanan gudang dan divisi
- [x] Ledger pergerakan stok (log)
- [x] Balance stok gudang dan divisi
- [x] Komponen adjustment (waste, variance, dll.)

**Yang belum selesai:**
- [ ] Posting dari receipt purchase ke gudang item (flow lengkap)
- [ ] Distribusi gudang item → stok material via `mst_material_item_source`
- [ ] Lot tracking bahan baku stabil
- [ ] Hardening ledger: konsistensi balance setelah setiap transaksi

---

### TAHAP 8 — Produksi & COGS 🔲

**Status:** BELUM MULAI

**Rencana:**
- [ ] Batch produksi component (BASE dan PREPARE)
- [ ] Konsumsi stok material/component saat batch produksi
- [ ] COGS calculation: HPP aktual dari batch
- [ ] Integrasi: konsumsi stok dari POS saat order

---

### TAHAP 9 — POS (Point of Sale) 🟠

**Status:** PERSIAPAN DESAIN (2026-05-13)

**Target minimum viable POS (MVP):**
- [ ] Order: tambah item, extra, diskon
- [ ] Payment: tunai, QRIS, kartu, split
- [ ] Void / refund order
- [ ] Stock deduction otomatis saat order
- [ ] Shift management (buka/tutup shift)
- [ ] Printer receipt
- [ ] Loyalty (minimal: poin bertambah saat bayar)

**COGS dan laporan margin bisa menyusul** — jangan block POS karena Tahap 8 belum selesai.

---

### TAHAP 10 — Keuangan & Akuntansi 🟠

**Status:** FONDASI DIMULAI (2026-05-13)

**Yang sudah berjalan:**
- [x] Rekening perusahaan (`fin_company_account`) aktif
- [x] Mutation log rekening dipakai oleh purchase dan payroll

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
| 25 Mei 2026 | Tahap 3/4/5 checklist selesai semua |
| 28 Mei 2026 | Store Request + UOM BELI/ISI di PO berjalan |
| 31 Mei 2026 | Stabilisasi — semua modul yang ada bebas bug kritis |
| 1 Jun 2026 | Go-live: modul HR/Payroll/Purchase/Inventory aktif |
| 30 Jun 2026 | POS minimum viable berjalan |
