# Konsep Ulang Alur HR, Absensi, Payroll, Bonus (Bahasa User)
**Tanggal:** 2026-05-07  
**Status:** DRAFT REVIEW USER + PARTIAL IMPLEMENTED (2026-05-07)

---

## 1. Tujuan
Dokumen ini jadi acuan sebelum eksekusi coding lanjutan untuk:
1. HR (data pegawai)
2. Absensi
3. Payroll
4. Bonus

Prinsip: adopsi dari `core`, buang legacy yang tidak perlu, dan pastikan hasil absensi/payroll konsisten (tidak dobel, tidak miss).

---

## 2. Keputusan Inti (Lock)

### 2.1 Adopsi data dari core
Diambil dari `core`:
1. `org_division`
2. `org_position`
3. `org_employee`
4. `att_shift`
5. `att_shift_schedule`
6. Master/pengaturan absensi lain yang relevan

Tidak diambil:
1. Semua kolom `org_employee` yang mengandung `abs` (legacy dashboard)
2. `is_kasir` (kasir ditentukan dari role akses)

Penyesuaian nama komponen gaji:
1. `gaji_pokok` -> tetap
2. `tunjangan` -> `tunjangan_jabatan`
3. `tambahan_lain` -> `tunjangan_lain`

### 2.2 Apakah `gaji_per_jam` masih diperlukan?
**Keputusan:** tetap diperlukan, tapi sebagai **nilai turunan/operasional**, bukan komponen utama kontrak.

Aturan:
1. Komponen utama tetap: `gaji_pokok`, `tunjangan_jabatan`, `tunjangan_lain`.
2. `gaji_per_jam` dipakai untuk kasus:
   - mode absen harian
   - potongan/jam tidak masuk
   - lembur berbasis tarif jam
3. Jika tidak diisi manual, sistem bisa auto-derive dari rumus setting:
   - `gaji_per_jam = gaji_pokok / hari_kerja_bulanan / jam_kerja_harian`

### 2.3 PH dirapikan jadi 1 konsep
Di `core` ada kode shift `PH` dan `PHB`.

**Keputusan:** disederhanakan jadi 1 konsep **PH**.

Aturan PH:
1. PH valid jika tanggal ada di tabel hari libur publik.
2. Pegawai dapat jatah PH jika memenuhi rule (masuk & pulang valid, atau auto hadir penuh untuk shift PH sesuai kebijakan).
3. Shift PH tidak perlu absen manual; saat buka halaman absen, sistem auto-create hadir 1 hari penuh (sesuai policy).
4. Ada pengaturan `expired PH` jika tidak dipakai.
5. Ada toggle apakah PH mendapatkan:
   - uang makan
   - bonus

### 2.4 Laporan absensi hanya satu sumber
**Keputusan:** hanya 1 laporan absensi final dari tabel rekap harian tunggal (`att_daily`).

Dampak:
1. Tidak ada lagi laporan ganda
2. Tidak ada angka beda antar halaman
3. Payroll hanya membaca sumber yang sama

---

## 3. Pengaturan yang Wajib Ada (Satu Halaman Setting)
Semua pengaturan inti dijadikan 1 halaman di dashboard finance.

### 3.1 Mode perhitungan absensi
1. Mode harian (berdasarkan hadir/jam kerja aktual)
2. Mode bulanan (gaji penuh lalu dikurangi absen/telat)

### 3.2 Parameter hari kerja
1. Hari kerja per bulan
2. Jam kerja per hari (untuk derive tarif jam)

### 3.3 Aturan keterlambatan
1. Potong dari THP total
2. Potong dari gaji pokok saja

### 3.4 Aturan tunjangan saat terlambat
1. Tunjangan tetap penuh saat hadir
2. Tunjangan ikut berkurang jika terlambat

### 3.5 Batas waktu absen normal
1. Batas check-in sebelum jam masuk (`checkin_open_minutes_before`) dihitung dari jadwal shift pegawai, bukan dari jam operasional global.
2. Batas check-out sesudah jam pulang (`checkout_close_minutes_after`) dihitung dari jam selesai shift masing-masing.

### 3.6 Pengaturan shift malam + jam operasional
Tambahan khusus yang Anda minta:
1. Set `jam_operasional` (contoh 08:00-23:00)
2. Set `early_checkout_credit_minutes` untuk shift malam

Contoh perilaku:
- Jam operasional tutup 23:00
- Pegawai shift malam checkout jam 22:05
- Jika melewati batas minimum yang diset (mis. setelah 22:00), sistem boleh credit checkout sebagai 23:00 (tidak perlu tunggu close area)

### 3.7 Pengaturan uang makan
1. Mode bulanan
2. Mode custom (mingguan/per periode)
3. Rule apakah PH dapat uang makan

### 3.8 Pengaturan PH
1. Rule jatah PH
2. Expired PH (berapa bulan)
3. Mode kehadiran PH harus pilih salah satu (mutually exclusive):
   - `AUTO_PRESENT` (auto-create hadir saat buka halaman absen)
   - `MANUAL_CLOCK` (tetap wajib check-in/check-out)
4. Rule PH dapat bonus atau tidak

### 3.9 Pengajuan & approval koreksi absen
1. Scope pengajuan jadi 3 opsi:
   - `SELF_ONLY`
   - `POSITION_ONLY`
   - `SELF_AND_POSITION`
2. Approval pending dibuat sampai 3 level (Level 1, Level 2, Level 3) berbasis mapping jabatan.

---

## 4. Alur User End-to-End

## 4.1 Tahap A - Setup awal
1. Admin buka dashboard finance -> Pengaturan HR/Absensi/Payroll
2. Isi semua setting inti (mode absen, hari kerja, telat, tunjangan, PH, uang makan, shift malam)
3. Simpan sebagai policy aktif

Hasil:
- Mesin absensi dan payroll punya rule yang jelas sebelum transaksi berjalan

## 4.2 Tahap B - Adopsi data dari core
1. Admin jalankan adopsi master: divisi, jabatan, pegawai, shift, jadwal
2. Sistem skip kolom legacy `abs*` dan `is_kasir`
3. Sistem map field gaji ke nama baru
4. Admin review hasil dan koreksi data invalid

Hasil:
- Master HR bersih dan siap pakai

## 4.3 Tahap C - Operasional absensi harian
1. Pegawai check-in/check-out
2. Untuk shift PH: cukup buka halaman absen -> auto tercatat hadir penuh sesuai policy
3. Untuk shift malam: checkout lebih awal yang valid bisa dikredit sampai jam operasional tutup
4. Admin proses approval izin/sakit/lembur
5. Sistem tulis rekap tunggal ke `att_daily`

Hasil:
- Rekap harian konsisten, tanpa sumber ganda

## 4.4 Tahap D - Closing absensi
1. Admin pilih periode closing
2. Sistem validasi data pending
3. Admin bereskan pending
4. Admin lock periode absensi

Hasil:
- Data absensi siap payroll

## 4.5 Tahap E - Proses payroll
1. Admin generate periode payroll
2. Sistem tarik data dari `att_daily`, komponen gaji, kasbon, policy aktif
3. Sistem hitung gaji dan potongan
4. Admin review dan koreksi berjejak (audit)
5. Finalisasi slip

Hasil:
- Net pay final per pegawai

## 4.6 Tahap F - Pencairan gaji
1. Admin buat batch transfer
2. Pilih rekening sumber
3. Generate daftar transfer
4. Konfirmasi PAID

Hasil:
- Payroll selesai administratif dan finansial

## 4.7 Tahap G - Bonus
1. Admin pilih skema bonus
2. Sistem hitung bonus berdasar policy + data absensi/payroll
3. Admin review & finalisasi
4. Bonus dibayar (gabung payroll atau batch terpisah)

Hasil:
- Bonus tercatat, terkontrol, dan bisa diaudit

---

## 5. Konsep Halaman yang Akan Dibuat

## 5.1 Finance Dashboard (admin/manager)
1. Ringkasan kehadiran hari ini
2. Pending approval (izin/sakit/lembur)
3. Status periode payroll aktif
4. Alert data miss (rekap belum lock, payroll belum final, dll)

## 5.2 Halaman Pengaturan Terpusat HR/Absensi/Payroll
1. Tab Mode Absen & Hari Kerja
2. Tab Potongan/Tunjangan
3. Tab Shift Malam & Jam Operasional
4. Tab PH & Uang Makan
5. Tab Formula Turunan (termasuk derive `gaji_per_jam`)

## 5.3 Master Data HR
1. Divisi
2. Jabatan
3. Pegawai
4. Shift
5. Jadwal shift
6. Hari libur

## 5.4 Operasional Absensi
1. Halaman absen pegawai (dibuat lebih dulu di employee dashboard)
2. Rekap harian tunggal
3. Approval center (izin/sakit/lembur)
4. Laporan absensi tunggal (filter lengkap)

## 5.5 Payroll & Bonus
1. Generate payroll period
2. Review payroll detail per pegawai
3. Finalisasi slip
4. Batch pencairan
5. Bonus period & posting

## 5.6 Employee Dashboard (template personal pegawai)
Tujuan: satu dashboard personal untuk kebutuhan pegawai.

Isi yang direncanakan:
1. Absen sekarang
2. Riwayat absensi saya
3. Jadwal shift saya (harian/mingguan/bulanan)
4. Profil saya (data pribadi, kontak, rekening)
5. Data kepegawaian saya (divisi, jabatan, status kerja, tanggal bergabung)
6. Data kontrak saya (aktif & riwayat)
7. Tanda tangan kontrak (melihat status tanda tangan + proses tanda tangan jika belum)
8. Dokumen kontrak (lihat/unduh versi final)
9. Slip gaji saya
10. Bonus saya
11. PH balance saya
12. Pengajuan saya (izin/sakit/lembur/koreksi absen) + status approval
13. Notifikasi personal (jadwal, kontrak hampir habis, slip terbit, approval)

---

## 6. Tahap Eksekusi Setelah Konsep Disetujui
Eksekusi bisa 2 cara:
1. Bertahap (direkomendasikan): lebih aman untuk stabilitas dan validasi tiap fase
2. Langsung paralel: lebih cepat, tapi risiko konflik dan bug integrasi lebih tinggi

Urutan bertahap yang direkomendasikan:
1. Lock mapping adopsi `core -> finance`
2. Lock kontrak policy setting (termasuk shift malam & PH)
3. Implement migrasi adopsi data
4. Implement rekap absensi tunggal
5. Implement engine payroll sesuai mode
6. Implement bonus processor
7. Finalisasi dashboard finance + dashboard employee personal (termasuk kontrak & tanda tangan)

Dokumen ini disiapkan untuk review Anda dulu sebelum eksekusi coding tahap berikutnya.

---

## 7. Update Implementasi 2026-05-07 (Tahap 1-3)
Yang sudah dieksekusi di `finance`:
1. Pengaturan absensi diperluas:
   - check-in relatif shift
   - batas tutup check-out
   - mode PH tunggal (auto/manual)
   - scope pengajuan 3 opsi
   - approval hingga 3 level
   - toggle potongan telat/alpha + scope prorata
2. Halaman admin absensi ditambah:
   - pengaturan policy (rapi per section)
   - monitoring pengajuan absensi dengan filter/search/pagination
3. Halaman employee portal diprioritaskan:
   - `my/attendance` (check-in/check-out + riwayat absen + filter/pagination)
   - halaman placeholder untuk profil/kontrak/jadwal/slip/pengajuan agar alur portal sudah utuh.
