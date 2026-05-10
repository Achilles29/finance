# Lock Mapping Adopsi `core` -> `db_finance` (HR & Absensi)
**Tanggal:** 2026-05-07  
**Status:** LOCK DRAFT (siap eksekusi SQL adopsi tahap 1)

---

## 1. Ruang Lingkup Adopsi Tahap 1

Tabel yang diadopsi:
1. `org_division`
2. `org_position`
3. `org_employee`
4. `att_location`
5. `att_shift`
6. `att_shift_schedule`
7. `att_holiday_calendar`
8. `att_attendance_policy`

Catatan:
- Fokus tahap ini: master + policy absensi.
- Tidak termasuk rekap/transaksi absensi lama (`att_daily_recap`, `att_presence`, dll).

---

## 2. Aturan Transformasi Utama

## 2.1 Kolom yang dibuang dari `core.org_employee`
Tidak diadopsi:
1. Semua kolom legacy `*abs*` (`divisi_abs_id`, `jabatan1_abs_id`, `jabatan2_abs_id`)
2. `is_kasir`
3. `username`, `password_hash`, `avatar` (tetap domain auth, bukan master payroll)
4. `device_*`, `last_login_*` (bukan data master kepegawaian payroll)

## 2.2 Mapping komponen gaji
1. `core.gaji_pokok` -> `db_finance.basic_salary`
2. `core.tunjangan` -> `db_finance.position_allowance` (konsep: `tunjangan_jabatan`)
3. `core.tambahan_lain` -> `db_finance.objective_allowance` (sementara; semantik bisnis: `tunjangan_lain`)
4. `core.uang_makan` -> `db_finance.meal_rate`
5. `core.gaji_per_jam` -> `db_finance.overtime_rate` fallback jika belum ada tarif lembur khusus

## 2.3 Mapping gender
1. `core.jenis_kelamin = 1` -> `L`
2. `core.jenis_kelamin = 2` -> `P`
3. selain itu -> `NULL`

## 2.4 Mapping status kerja
Aturan default adopsi:
1. `is_active = 0` -> `RESIGNED`
2. `is_active = 1` dan `tanggal_kontrak_akhir` kosong -> `PERMANENT`
3. `is_active = 1` dan `tanggal_kontrak_akhir` terisi -> `CONTRACT`

## 2.5 Mapping PH (rapikan PH/PHB)
Aturan normalisasi shift:
1. `shift_code` = `PH` atau `PHB` atau `is_ph_shift=1` -> target `shift_code='PH'`
2. `shift_name` dinormalisasi ke `PUBLIC HOLIDAY`
3. jika ada duplikasi hasil normalisasi, digabung jadi satu row shift target per divisi+kode

## 2.6 Mapping holiday type
1. `NATIONAL` -> `NATIONAL`
2. `COMPANY` -> `COMPANY`
3. `CUTI_BERSAMA` -> `SPECIAL`

---

## 3. Mapping Tabel Detail

## 3.1 `org_division`
- Key mapping: `division_code` (upsert)
- Sumber utama: `division_code`, `division_name`, `is_active`

## 3.2 `org_position`
- Key mapping: `position_code` (upsert)
- Relasi divisi: berdasarkan `division_code`
- `default_role_id`: `NULL` dulu (diisi di tahap RBAC mapping employee-role)

## 3.3 `org_employee`
- Key mapping: `employee_code` (upsert)
- Relasi divisi/jabatan: via kode, bukan id mentah
- NIP: sementara dari `employee_code` bila `employee_nip` kosong

## 3.4 `att_location`
- Key mapping: `location_code` (upsert)

## 3.5 `att_shift`
- Key mapping: `shift_code` + divisi (dengan normalisasi PH)
- `PHB` dilebur ke `PH`

## 3.6 `att_shift_schedule`
- Key mapping: `employee_id + schedule_date` (upsert)
- Mapping pegawai via `employee_code`
- Mapping shift via hasil normalisasi kode shift + divisi

## 3.7 `att_holiday_calendar`
- Key mapping: `holiday_date + holiday_name` (upsert)

## 3.8 `att_attendance_policy`
- Diadopsi hanya policy aktif terbaru dari `core`
- Jika belum ada di target, insert default `CORE_IMPORT_01`

---

## 4. Guardrail Eksekusi
1. Proses upsert, bukan truncate-dulu.
2. Relasi divisi/jabatan/pegawai selalu pakai kode bisnis, bukan id source langsung.
3. Adopsi schedule hanya untuk pegawai & shift yang berhasil termap.
4. Shift PH/PHB dinormalisasi sebelum insert schedule.

---

## 5. Output Teknis Tahap Ini
1. Dokumen mapping ini
2. SQL adopsi: `sql/2026-05-07e_adopt_core_hr_absensi_master_to_db_finance.sql`
