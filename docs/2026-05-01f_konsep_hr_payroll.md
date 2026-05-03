# Konsep HR & Payroll — NIP, Jabatan, Skema Gaji
**Tanggal:** 2026-05-01  
**Status:** FINAL — berlaku untuk pengembangan Tahap 3 & 5

---

## 1. NIP (Nomor Induk Pegawai)

### Konteks dari `core`
Di `core`, NIP mengadopsi format PNS: mengkodekan tanggal lahir, tanggal bergabung, dan jenis kelamin dalam satu string 18 digit. Tujuannya agar admin bisa langsung baca info karyawan dari NIP tanpa buka database.

### Evaluasi
**Yang baik dari pendekatan ini:**
- NIP bisa "berbicara" — langsung tau konteks karyawan
- Sudah familiar bagi admin yang terbiasa dengan sistem PNS

**Masalahnya:**
- 18 digit panjang, susah diingat dan diketik
- Semua info (lahir, bergabung, gender) sudah tersimpan di kolom terpisah di database
- Perlu logika generate yang lebih kompleks
- Privasi: tanggal lahir ter-expose di NIP

### Rekomendasi Format NIP untuk `finance`

**Format:** `{YYYY_JOIN}{MM_JOIN}{KODE_GENDER}{SEQ:03d}`

| Bagian | Keterangan | Contoh |
|---|---|---|
| `YYYY_JOIN` | Tahun bergabung (4 digit) | `2026` |
| `MM_JOIN` | Bulan bergabung (2 digit) | `03` |
| `KODE_GENDER` | L = Laki-laki, P = Perempuan | `L` |
| `SEQ:03d` | Nomor urut 3 digit per periode tersebut | `001` |

**Contoh:** `202603L001` = Bergabung Maret 2026, Laki-laki, urutan ke-1

**Kenapa ini lebih baik:**
- 10 karakter, mudah diingat
- Masih mengandung info bermakna (join year/month + gender)
- Tanggal lahir tidak di-expose (ada di kolom `birth_date`)
- Sequence di-reset per bulan bergabung, bukan global
- Auto-generate mudah

**Catatan:** Jika pemilik tetap ingin encode tanggal lahir, opsi alternatif:  
`{YYYY_BIRTH}{MM_BIRTH}{YYYY_JOIN}{MM_JOIN}{GENDER_CODE}{SEQ:3}`  
→ Contoh: `199501202603L001` (16 karakter) — lebih panjang tapi mengikuti gaya PNS

Keputusan final: **Format 10 karakter** (`202603L001`) kecuali ada alasan kuat untuk berbeda.

---

## 2. Struktur Jabatan & Organisasi

### Entitas

```
org_position (jabatan)
  └── position_name, position_code, division_id, role_id (default RBAC role)

org_division (divisi/departemen)
  └── division_name, division_code

org_employee (karyawan)
  └── FK: position_id, division_id
```

### Contoh Jabatan di Kafe

| Jabatan | Kode | Divisi | Default Role |
|---|---|---|---|
| CEO / Pemilik | CEO | Manajemen | CEO |
| Manajer Operasional | MGR-OPS | Operasional | Manajer |
| Admin Keuangan | ADM-FIN | Keuangan | Admin Keuangan |
| Kasir | KSR | Operasional | Kasir |
| Barista | BRS | Bar | Barista |
| Chef | CHF | Dapur | Chef |
| Admin Gudang | ADM-GDG | Gudang | Admin Gudang |
| Staff HR | STF-HR | HRD | Staff HR |

---

## 3. Skema Gaji

### Komponen Gaji (FINAL)

| Komponen | Tipe | Keterangan |
|---|---|---|
| **Gaji Pokok** | Tetap + | Gaji dasar, tidak berubah kecuali ada revisi |
| **Tunjangan Jabatan** | Tetap + | Berdasarkan jabatan/posisi, bukan personal |
| **Tunjangan Objektif** | Variabel + | Berdasarkan pencapaian/KPI, bisa 0 |
| Komponen tambahan | Opsional + | Contoh: tunjangan transport, tunjangan khusus |
| **Potongan BPJS** | Tetap - | Jika berlaku |
| **Potongan Kasbon** | Variabel - | Cicilan kasbon bulan ini |
| **Potongan Terlambat** | Variabel - | Rp X × total menit terlambat bulan ini |
| **Potongan Alpha** | Variabel - | Rp Y × hari tidak hadir tanpa keterangan |
| **Lembur** | Variabel + | Jika ada kebijakan lembur berbayar |
| **Bonus Omzet** | Variabel + | Berdasarkan capaian omzet periode |

### Formula Kalkulasi Gaji

```
GAJI KOTOR = Gaji Pokok + Tunjangan Jabatan + Tunjangan Objektif + Lembur + Bonus Omzet
           + (komponen tambahan lainnya)

TOTAL POTONGAN = Potongan BPJS + Potongan Kasbon + Potongan Terlambat + Potongan Alpha

GAJI BERSIH = GAJI KOTOR - TOTAL POTONGAN
```

### Profil Gaji vs Assignment

Di `finance`, konsep profil gaji dipertahankan dari `core` karena sangat berguna:

- **Profil Gaji** = template yang berisi komponen-komponen gaji (bisa dipakai banyak karyawan dengan jabatan sama)
- **Assignment** = karyawan X menggunakan profil gaji Y, dengan nilai override jika berbeda

Contoh: Semua Barista menggunakan "Profil Kasir-Bar" (gaji pokok 3jt, tunjangan jabatan 500rb), tapi Barista A punya tunjangan objektif 200rb karena pencapaian khusus.

---

## 4. Tunjangan Jabatan vs Tunjangan Objektif

### Tunjangan Jabatan
- Ditentukan berdasarkan **jabatan**, bukan personal
- Nilainya sama untuk semua orang dengan jabatan yang sama
- Berubah hanya jika ada kenaikan jabatan atau revisi struktur gaji

### Tunjangan Objektif
- Ditentukan berdasarkan **pencapaian** (KPI, target, penilaian)
- Nilainya bisa berbeda antar karyawan walaupun jabatan sama
- Di-review periodik (bulanan atau per kuartal)
- Bisa 0 jika tidak ada target atau tidak tercapai

### Perbedaan dengan `core`
Di `core`, komponen ini bernama "tunjangan" dan "tambahan lain" — kurang deskriptif dan sering membingungkan. Di `finance`, nama lebih eksplisit: `tunjangan_jabatan` dan `tunjangan_objektif`.

---

## 5. Alur Payroll Lengkap

```
[Awal Bulan]
Admin buka menu Payroll → Generate Periode

      ↓
Sistem tarik data:
- Hari kerja efektif bulan ini
- Rekap absensi semua karyawan (dari att_daily)
- Profil gaji masing-masing karyawan
- Kasbon aktif yang punya cicilan jatuh tempo

      ↓
Sistem kalkulasi per karyawan:
- Hitung gaji kotor (profil × hari kerja)
- Hitung potongan (terlambat, alpha, kasbon)
- Hitung tambahan (lembur, bonus jika ada)
- Hasilkan: gaji bersih per karyawan

      ↓
Admin review hasil kalkulasi
- Bisa lihat detail per karyawan
- Bisa edit manual jika ada koreksi luar biasa

      ↓
Admin Finalisasi
- Slip gaji terkunci (tidak bisa edit lagi)
- Karyawan bisa lihat slip di My/portal mereka

      ↓
Admin buat Pencairan Gaji
- Pilih rekening bank sumber
- Pilih tanggal transfer
- Generate daftar transfer (bisa download untuk internet banking)

      ↓
Admin konfirmasi PAID
- Setiap karyawan ditandai PAID
- Cicilan kasbon otomatis ter-settle
- Mutasi bank tercatat di modul keuangan
```

---

## 6. Uang Makan — Alur Detail

```
[Setiap Akhir Minggu atau Awal Minggu Berikutnya]
Admin buka Uang Makan → Generate Minggu [Tanggal]

      ↓
Sistem hitung per karyawan:
- Ambil rekap absensi minggu tersebut (att_daily)
- Hitung hari hadir VALID (Hadir + Terlambat)
- EXCLUDE: hari libur nasional (dari att_holiday)
- EXCLUDE: karyawan dengan uang_makan = 0 (tidak dapat uang makan)
- Jumlah = hari_valid × rate_uang_makan karyawan

      ↓
Admin review → Finalisasi

      ↓
Admin buat Pencairan
- Rekening sumber, tanggal transfer
- Generate daftar transfer

      ↓
Admin konfirmasi PAID
```

---

## 7. Kasbon Karyawan — Alur Detail

```
[Karyawan atau Admin]
Ajukan Kasbon → isi: jumlah, tujuan, rencana cicilan

      ↓
Atasan/Admin review → Approve atau Reject

      ↓ (jika Approve)
Admin buat Pencairan Kasbon
- Pilih rekening bank, tanggal transfer
- Status: DISBURSED

      ↓
Cicilan otomatis terpotong saat proses gaji bulanan
- Admin bisa lihat sisa tagihan kasbon per karyawan

      ↓ (saat semua cicilan lunas)
Status kasbon: SETTLED
```

---

## 8. Tabel yang Perlu Dibuat (HR & Payroll)

```sql
-- Organisasi
org_division         -- divisi/departemen
org_position         -- jabatan
org_employee         -- karyawan (NIP auto, biodata lengkap)
org_employee_bank    -- rekening bank karyawan (bisa lebih dari 1)

-- Kontrak
hr_contract          -- kontrak karyawan (PKWT/PKWTT/Magang)

-- Payroll Master
pay_salary_component   -- daftar komponen gaji (gaji_pokok, tunj_jabatan, dst)
pay_salary_profile     -- template profil gaji
pay_salary_profile_line -- nilai tiap komponen dalam profil
pay_salary_assignment  -- karyawan → profil gaji + nilai override

-- Payroll Proses
pay_payroll_period     -- periode penggajian (bulan/tahun)
pay_payroll_result     -- hasil kalkulasi per karyawan per periode
pay_payroll_result_line -- detail per komponen gaji
pay_salary_disbursement      -- pencairan gaji (batch)
pay_salary_disbursement_line -- detail per karyawan

-- Uang Makan
pay_meal_policy             -- kebijakan uang makan (rate, kriteria hadir)
pay_meal_disbursement       -- pencairan uang makan per minggu
pay_meal_disbursement_line  -- detail per karyawan

-- Kasbon
pay_cash_advance              -- kasbon karyawan
pay_cash_advance_installment  -- cicilan kasbon

-- THR
pay_thr_period    -- periode THR
pay_thr_line      -- THR per karyawan

-- Bonus
pay_bonus_period  -- periode bonus
pay_bonus_line    -- bonus per karyawan (bisa per omzet atau per KPI)
```
