# Audit Core untuk Acuan Modul Pegawai, Absensi, Payroll (Finance)
**Tanggal:** 2026-05-07  
**Status:** AKTIF — dipakai sebagai baseline implementasi Tahap 3/4/5

---

## Ringkasan Temuan Utama dari `core`

### 1) Controller terlalu besar (rawan bug regressi)
- `Attendance.php` ~1155 baris
- `Attendance_masters.php` ~1408 baris
- `Attendance_employee.php` ~706 baris
- `Employee_self.php` ~1001 baris

Dampak:
- Sulit dites per fitur
- Perubahan kecil berisiko mematahkan alur lain
- Review kode makin mahal

Keputusan di `finance`:
- Hindari “god controller”
- Master data CRUD dipusatkan ke pola generik (`Master`) untuk fitur yang benar-benar CRUD
- Logika proses (generate payroll, rebuild att_daily, approval) dipisah ke service/model terfokus

---

### 2) Hybrid rekap absensi di `core` (dua sumber harian)
Di `core` ada fallback/duplikasi antara:
- `att_daily_recap` (legacy)
- `pay_attendance_daily` / tabel baru

Dampak:
- Rekap dan payroll bisa beda hasil tergantung sumber data
- Kompleksitas query tinggi

Keputusan di `finance`:
- Satu sumber harian saja: `att_daily`
- Semua kalkulasi payroll bulanan wajib dari `att_daily` + master payroll

---

### 3) Scope bisnis bercampur di layer absensi
Beberapa concern lintas domain (bonus/punishment, PH khusus, pending approval bertingkat) bercampur di modul absensi inti.

Dampak:
- Sulit menjaga batas domain
- Perubahan kebijakan bonus berdampak ke absensi

Keputusan di `finance`:
- Attendance fokus pada: shift, presence, recap harian, overtime, pending request
- Bonus/insentif diproses sebagai domain payroll terpisah

---

### 4) Dependency payroll ke field legacy employee
Di `core`, sebagian kalkulasi payroll masih bertumpu ke field lama karyawan (`tunjangan`, `tambahan_lain`, dll) sambil berjalan bersama skema kontrak/snapshot.

Dampak:
- Sulit audit asal nilai gaji
- Potensi mismatch antara kontrak, profil gaji, dan hasil payroll

Keputusan di `finance`:
- Struktur payroll eksplisit:
  - `pay_salary_component`
  - `pay_salary_profile`
  - `pay_salary_profile_line`
  - `pay_salary_assignment`
  - `pay_payroll_period`
  - `pay_payroll_result` + line
- Snapshot nilai hasil payroll disimpan di tabel result (immutable per periode)

---

## Yang Dipertahankan dari `core`

- Konsep shift schedule harian per pegawai
- Geofence lokasi absen
- Approval overtime
- Konsep profile + assignment payroll
- Disbursement gaji dipisah dari kalkulasi payroll

---

## Yang Disederhanakan di `finance`

- Tidak ada dual-table attendance recap
- Tidak ada fallback lintas tabel legacy saat generate payroll
- Tahap awal tidak memasukkan semua varian approval bertingkat
- Fokus ke jalur minimum stabil dulu (stabil > lengkap)

---

## Output Implementasi Awal (2026-05-07)

1. SQL foundation baru:
- `sql/2026-05-07a_hr_attendance_payroll_foundation.sql`

2. Seed page/menu/permission awal:
- `sql/2026-05-07b_hr_attendance_payroll_menu_seed.sql`

3. CRUD awal aktif via modul `Master`:
- `org-division`
- `org-position`
- `org-employee`
- `att-shift`
- `att-location`
- `att-holiday`
- `pay-component`
- `pay-profile`
- `pay-profile-line`
- `pay-assignment`

---

## Catatan Lanjutan

Tahap berikutnya tetap diperlukan untuk modul proses:
- Generate `att_daily` otomatis dari `att_presence`
- Approval pending request + overtime
- Generate payroll period + disbursement workflow

Dokumen ini jadi guardrail agar pengembangan paralel tetap konsisten, tidak kembali ke pola kompleks legacy.
