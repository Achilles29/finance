# Gap Halaman Absensi (Setelah Update 2026-05-07)

## Sudah tersedia (admin)
1. `/attendance/settings` - Pengaturan absensi/payroll scope
2. `/attendance/daily` - Rekap absensi harian (filter, search, pagination)
3. `/attendance/logs` - Log presensi checkin/checkout (filter, search, pagination)
4. `/attendance/schedules` - Monitoring jadwal shift pegawai (filter, search, pagination)
   - plus CRUD single schedule + bulk schedule (model `schedules-v2` sederhana)
5. `/attendance/pending-requests` - Monitoring + aksi approve/reject/cancel pengajuan absensi
6. `/attendance/anomalies` - Monitoring data anomali absensi (single source `att_daily`)
7. `/attendance/master-health` - Audit kesehatan data master HR (pegawai-user-jadwal-kontrak)
8. `/attendance/estimate` - Estimasi gaji harian/bulanan berbasis absensi (read-only)
9. `/master/att-shift` - Master shift
10. `/master/att-location` - Master lokasi (termasuk `is_default` untuk lokasi absen mandiri)
11. `/master/att-holiday` - Master hari libur

## Belum tersedia (prioritas berikutnya)
1. Halaman absen pegawai (clock in/out) di sisi employee
2. Halaman pengajuan koreksi absen / izin / sakit / lembur (employee) - belum final submit workflow
3. Halaman approval center detail per-level + history timeline (saat ini sudah ada approve/reject/cancel, tetapi UI timeline per-level belum final)
4. Halaman PH balance pegawai dan riwayat ledger PH
5. Halaman laporan absensi export (CSV/XLS) dengan agregasi
6. Halaman bulk scheduler jadwal shift (upload/spreadsheet) - saat ini sudah ada bulk form, belum ada import template

## Catatan PH
1. Shift `PH` sudah dijadikan general (`division_id = NULL`), tidak lagi terkunci ke Kitchen.
2. Kode `PHB` tidak dipakai di target (dinormalisasi ke `PH`).

## Standar data table yang dipakai
Untuk seluruh halaman list absensi:
1. Filter relevan per domain
2. Search keyword lintas kolom penting
3. Pagination server-side (10/25/50/100)
4. Rentang tanggal default bulan berjalan
