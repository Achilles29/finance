# Review Selaras Dokumen vs Implementasi HR Absensi Payroll
**Tanggal:** 2026-05-08  
**Status:** AKTIF — hasil review lintas dokumen `2026-05-07a/c/d/g` + roadmap

---

## 1. Dokumen yang Direview
1. `docs/2026-05-07a_audit_core_hr_attendance_payroll.md`
2. `docs/2026-05-07c_konsep_ulang_alur_hr_absensi_payroll_user.md`
3. `docs/2026-05-07d_mapping_adopsi_core_ke_db_finance_hr_absensi.md`
4. `docs/2026-05-07g_gap_halaman_absensi.md`
5. `docs/2026-05-01d_roadmap_pengembangan.md`

---

## 2. Keselarasan Saat Ini (Ringkas)

### 2.1 Sudah Selaras
1. Prinsip single source rekap absensi (`att_daily`) tetap konsisten dengan audit.
2. Fondasi master HR/absensi/payroll sudah ada dan tabel target lebih ringkas dari core.
3. Data payroll master dari core sudah diimpor ke struktur finance (`pay_salary_component`, `pay_salary_profile`, `pay_salary_profile_line`, `pay_salary_assignment`).
4. Konsep kontrak operasional (generate, snapshot, status, approval) sudah berjalan terpisah dari master CRUD.
5. Pola list modul payroll master sudah diseragamkan dengan pola `users/material` (filter, search, pagination, aksi ikon).

### 2.2 Parsial Selaras
1. Absensi policy dan halaman admin utama sudah ada, tetapi approval center penuh + anomaly monitoring masih backlog.
2. Portal employee sudah ada fondasi, tetapi halaman personal lengkap (kontrak detail, pengajuan, slip) belum final.
3. Relasi payroll assignment ke engine payroll period masih fondasi, belum full closing bulanan.

### 2.3 Belum Selaras
1. Integrasi otomatis employee -> role (RBAC mapping by position) belum tuntas.
2. Guardrail overlap periode untuk standard basic salary / objective override belum dikunci di level service khusus (saat ini masih CRUD generik).

---

## 3. Audit Adopsi `core/payroll-standards` ke Finance

| Core Page | Status di Finance | Catatan |
|---|---|---|
| `/payroll-standards/components` | **Sudah diadopsi** | Lewat `/master/pay-component`, data sudah import dari core |
| `/payroll-standards/profiles` | **Sudah diadopsi** | Lewat `/master/pay-profile` + `/master/pay-profile-line` |
| `/payroll-standards/assignments` | **Sudah diadopsi (fondasi)** | Lewat `/master/pay-assignment`, belum ada assistant/recommendation flow |
| `/payroll-standards/basic-salary` | **Sudah diadopsi (fondasi)** | Tabel + CRUD: `pay_basic_salary_standard` lewat `/master/pay-basic-salary` |
| `/payroll-standards/objective-overrides` | **Sudah diadopsi (fondasi)** | Tabel + CRUD: `pay_objective_override` lewat `/master/pay-objective-override` |
| `/payroll-standards/preview-thp` | **Sudah diadopsi (read-only)** | Halaman `/payroll/preview-thp`, sumber dari assignment + kontrak aktif |

---

## 4. Keputusan Efisiensi (Supaya Tidak Overlap)

1. Tetap pertahankan 4 master payroll inti yang sudah ada:
   - `pay_salary_component`
   - `pay_salary_profile`
   - `pay_salary_profile_line`
   - `pay_salary_assignment`
2. `basic-salary` di finance dibuat sebagai rule base salary terpisah, bukan ditaruh ulang di banyak tabel.
3. `objective-overrides` tetap ada, tapi dibatasi hanya untuk override pegawai by periode efektif (tanpa duplikasi field ke banyak modul).
4. `preview-thp` dibuat read-only sebagai simulasi, bukan sumber data final payroll.
5. Sumber final payroll tetap dari hasil generate periode payroll (immutable result), bukan dari halaman preview.

---

## 5. Rencana Lanjut yang Masih Relevan

### 5.1 Sprint A — Lengkapi standar payroll yang belum
Status 2026-05-08: selesai fondasi.
1. Schema + CRUD `pay_basic_salary_standard` sudah ada.
2. Schema + CRUD `pay_objective_override` sudah ada.
3. Halaman `preview THP` read-only sudah ada.

### 5.2 Sprint B — Integrasi proses bisnis
1. Assignment payroll diberi rekomendasi nominal dari standar (opsional edit manual).
2. Sinkronisasi ke snapshot kontrak saat generate kontrak.
3. Guardrail overlap periode assignment/override agar tidak miss.

### 5.3 Sprint C — Payroll period engine
1. Generate period payroll dari `att_daily` + assignment + contract snapshot.
2. Lock period + audit trail perubahan.
3. Final output slip + disbursement.

---

## 6. Catatan Perubahan Roadmap
Roadmap tetap relevan, tetapi urutan detail Tahap 5 perlu dipertegas:
1. **Sebelum** payroll period engine: selesaikan dulu standar payroll (`basic salary`, `objective override`, `preview THP`).
2. Setelah standar stabil, baru lanjut finalisasi generate payroll bulanan dan disbursement.

Dengan urutan ini, adopsi dari core tetap terjaga, tetapi modul yang rawan overlap/bug dipangkas sejak awal.
