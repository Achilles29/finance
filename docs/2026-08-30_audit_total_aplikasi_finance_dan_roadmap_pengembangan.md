# Audit Total Aplikasi Finance dan Roadmap Pengembangan

**Tanggal audit:** 2026-08-30  
**Sifat audit:** Pemeriksaan baca-saja terhadap kode, struktur RBAC, sidebar,
writer transaksi, dan snapshot database lokal.  
**Status dokumen:** Pegangan perbaikan teknis. Dokumen ini tidak mengubah
kode, database, saldo, stok, HPP, payroll, maupun hak akses.  
**Dokumen terkait:**
`docs/2026-08-28_roadmap_komersialisasi_finance_dan_lisensi.md` tetap menjadi
pegangan arah produk dan lisensi. Dokumen ini menjelaskan kesiapan teknis yang
harus dicapai sebelum arah komersialisasi tersebut dijalankan.

## 1. Ringkasan untuk Pemilik Aplikasi

Finance sudah memiliki cakupan modul yang sangat lengkap. POS, purchase order,
store request, gudang, persediaan divisi, produksi, HPP, keuangan, absensi,
payroll, aset, reservasi, printer, loyalty, dan audit operasional sudah saling
terhubung. Fondasi bisnisnya layak dilanjutkan menjadi produk komersial.

Namun, aplikasi **belum aman langsung dijual atau dipasang ke customer baru**.
Masalah terpenting bukan kekurangan fitur, melainkan beberapa pintu perubahan
data belum dijaga secara konsisten, hak akses database sudah melebar akibat
seed yang bersifat menambah, dan sebagian writer nilai persediaan belum memakai
sumber biaya yang sama dengan lot FIFO.

Kesimpulan utama:

1. **Keamanan dan RBAC adalah prioritas pertama.** Ada endpoint master, resep,
   bundle, dan API POS mobile yang dapat lolos hanya dengan login/token tanpa
   pemeriksaan izin per aksi yang memadai.
2. **Stok minus sudah berhasil diganti dengan defisit.** Tidak ditemukan lot
   material atau component aktif yang negatif pada snapshot audit.
3. **Defisit tetap tampil di dashboard dan memang harus tampil.** Defisit tidak
   sama dengan mismatch. Defisit berarti barang sudah dipakai tetapi sumber
   lotnya belum cukup; mismatch berarti dua catatan stok yang seharusnya sama
   ternyata berbeda.
4. **Mismatch nilai component dan gudang masih dapat muncul.** Penyebabnya
   bukan konsep defisit, tetapi writer nilai bulanan dan writer lot belum selalu
   memakai biaya atau sumber lot yang sama.
5. **Keuangan berjalan saat ini seimbang**, tetapi urutan riwayat saldo dapat
   membingungkan bila transaksi bertanggal mundur dimasukkan belakangan.
6. **PH saat ini sudah sehat secara saldo dan guarding**, dengan sedikit utang
   audit data lama mengenai tanggal eligibility.
7. **Belum ada test otomatis proyek.** Kondisi ini terlalu berisiko untuk
   updater otomatis dan distribusi ke banyak customer.
8. **Migration deployment belum terkelola.** SQL masih dijalankan manual dan
   belum ada satu registry yang membuktikan versi schema setiap instalasi.

Prioritas yang disarankan adalah menghentikan sementara penambahan fitur besar,
menyelesaikan Fase 0 sampai Fase 3 pada roadmap di dokumen ini, lalu melanjutkan
fitur komersialisasi dan APK.

## 2. Cara Membaca Status Temuan

| Status | Arti untuk pengguna |
| --- | --- |
| Kritis | Dapat membuka akses yang tidak semestinya, mengubah data penting, atau menghambat rilis komersial. |
| Tinggi | Dapat menghasilkan perbedaan stok, nilai, HPP, atau proses operasional baru walaupun server normal. |
| Sedang | Data utama masih berjalan, tetapi laporan, audit trail, atau operasional dapat membingungkan. |
| Historis | Berasal dari data atau writer lama. Tidak boleh dibetulkan massal sebelum sumber dan periode dipastikan. |
| Sehat | Pemeriksaan saat ini tidak menemukan anomali material pada area tersebut. |

Angka database pada dokumen ini adalah snapshot audit lokal. Angka dapat berubah
setelah transaksi baru, SQL migrasi, atau penarikan ulang database server.
Karena itu angka digunakan sebagai bukti pola, bukan sebagai perintah repair
langsung.

## 3. Kondisi yang Sudah Sehat

### 3.1 Stok, lot, dan defisit

- Tidak ditemukan lot bahan baku aktif yang negatif.
- Tidak ditemukan lot component aktif yang negatif.
- Kekurangan sumber stok sudah dicatat sebagai defisit, bukan memaksa saldo lot
  menjadi minus.
- Dashboard masih membaca defisit berstatus `OPEN` dengan sisa lebih dari nol.
  Pada snapshot terdapat 8 defisit terbuka: 4 bahan baku dan 4 component.
- Status defisit pada snapshot: 8 `OPEN`, 62 `SETTLED`, 6 `VOID`, dan 6
  `WRITTEN_OFF`.
- Writer POS sudah menggunakan FIFO lintas lot/profil saat stok tersedia dan
  memakai HPP sementara penuh saat sumber lot tidak cukup.

Makna bagi user: aplikasi tidak lagi menyembunyikan kekurangan dengan stok
minus. Operator melihat kekurangan itu di antrean defisit sampai ditutup melalui
sumber barang yang tepat, recon fisik, atau write-off administratif.

### 3.2 POS runtime dan antrean availability

- Tidak ditemukan job stock commit POS yang tertahan dalam status aktif pada
  snapshot audit.
- Snapshot job terdiri dari 2.387 `SUCCESS` dan 3 `CANCELLED`.
- Antrean availability yang diperiksa berada dalam status `SUCCESS`.
- Pengaman void/refund sudah mencegah job terminal diproses ulang.

### 3.3 Keuangan saat ini

- Seluruh saldo rekening aktif yang diperiksa sama dengan saldo awal ditambah
  mutasi bersih.
- Tidak ditemukan perbedaan saldo rekening berjalan pada snapshot.
- Reversal dan perhitungan saldo akhir saat ini konsisten.

### 3.4 PH, absensi, dan guarding jadwal

- Tidak ditemukan saldo PH negatif setelah rekonsiliasi V3.
- Tidak ditemukan penggunaan PH sebelum cutover 1 Juni 2026 yang masih aktif.
- Tidak ditemukan penggunaan PH ganda pada sumber harian yang sama.
- Kandidat grant hari libur, penggunaan PH, expiry, batas saldo, dan guarding
  jadwal sudah memiliki jalur layanan yang jelas di `Attendance_model`.
- Contoh saldo aktif hasil audit: Fairuz 1, Eko 3, Pak Fajar 4, dan Nayla 3.

### 3.5 Relasi database

- Pemeriksaan foreign key tidak menemukan orphan pada schema aktif.
- Halaman aset dan reservasi yang diperiksa telah menggunakan guard halaman
  dan aksi yang lebih baik daripada master generik.
- Verifikasi reservasi ke POS menghitung ulang harga dan HPP pada saat kasir
  menerima reservasi, bukan mempercayai angka lama dari browser.

## 4. Temuan Kritis

### K-01. CRUD master generik belum dijaga per aksi

**Bukti kode:** `application/controllers/Master.php`, terutama method `index`,
`create`, `store`, `edit`, `update`, `toggle`, dan `reorder`.

Controller tersebut menangani banyak data utama seperti UOM, rekening bank,
pegawai, produk, bahan baku, vendor, dan beberapa master payroll. Sebagian besar
method generiknya belum memanggil `require_permission()` untuk page dan aksi
yang sesuai.

**Dampak untuk user:** pegawai yang berhasil login dan mengetahui URL tertentu
berpotensi membuka atau mengirim perubahan data master di luar menu yang
ditampilkan kepadanya. Menyembunyikan sidebar saja tidak cukup karena URL masih
dapat dipanggil langsung.

**Perbaikan:**

1. Buat registry entity ke `page_code` yang tunggal.
2. Wajibkan `view`, `create`, `edit`, atau `delete` pada awal setiap endpoint.
3. Tolak entity yang tidak terdaftar, jangan memakai fallback izin umum.
4. Tambahkan test direct URL untuk setiap role operasional.

### K-02. Writer resep, formula, extra, dan bundle belum konsisten memakai RBAC

**Bukti kode:** `application/controllers/Master_relation.php`, termasuk
`product_recipe_bulk_save`, `product_recipe_store/update/delete`,
`component_formula_store/update/delete`, writer extra, dan writer bundle.

Halaman workspace extra memiliki guard baca, tetapi banyak endpoint mutasinya
tidak memeriksa izin aksi secara eksplisit.

**Dampak untuk user:** perubahan resep atau formula dapat mengubah HPP,
ketersediaan POS, kebutuhan produksi, dan pemakaian stok. Ini lebih berbahaya
daripada sekadar tampilan menu yang salah.

**Perbaikan:** semua endpoint relasi harus memakai page code kanonis dan izin
per aksi. Perubahan formula juga harus mencatat siapa, kapan, nilai sebelum,
nilai sesudah, dan alasan perubahan.

### K-03. API POS mobile belum memeriksa hak aksi secara rinci

**Bukti kode:** `application/controllers/Pos_mobile.php`.

- Token berlaku 30 hari.
- Endpoint seperti `order_save`, `payment_save`, `cashier_open`, dan
  `cashier_close` memanggil `authorize_mobile()`.
- `authorize_mobile()` terutama membuktikan token/API key/sesi, tetapi belum
  membuktikan izin RBAC khusus untuk aksi yang sedang dijalankan.

**Dampak untuk user:** akun yang pernah memperoleh token POS berpotensi tetap
memanggil aksi sensitif walaupun role atau haknya sudah berubah. Risiko semakin
besar ketika APK mulai didistribusikan ke banyak perangkat.

**Perbaikan:**

1. Petakan setiap endpoint ke `page_code` dan aksi.
2. Muat ulang role aktif saat request, bukan hanya saat token dibuat.
3. Tambahkan token version/revocation per user dan terminal.
4. Pendekkan access token, gunakan refresh token terikat terminal.
5. Cabut token otomatis saat user nonaktif, password berubah, role berubah,
   atau terminal dicabut.

### K-04. Hak akses database sudah melebar akibat seed aditif

Snapshot RBAC menunjukkan pola yang tidak sesuai prinsip least privilege:

| Role | Halaman terlihat | Create | Edit | Delete | Export |
| --- | ---: | ---: | ---: | ---: | ---: |
| KASIR | 106 | 84 | 84 | 74 | 79 |
| BARISTA | 117 | 87 | 85 | 81 | 85 |

Contoh yang perlu dicabut atau ditinjau:

- BARISTA memiliki hak penuh pada kontrol periode stok, defisit, kesehatan
  stok, master material/vendor, dan adjustment component.
- KASIR memiliki hak penuh pada pengaturan absensi, rekening keuangan, master
  material/vendor, dan purchase order.
- User dengan banyak role mendapatkan gabungan izin melalui operasi OR pada
  `application/models/Auth_model.php`.
- Scope divisi dapat menjadi `null` saat user mempunyai lebih dari satu scope,
  sehingga filter pembatas berisiko berubah menjadi tidak terbatas.

Seed SQL saat ini umumnya memakai `INSERT ... ON DUPLICATE KEY UPDATE`, tetapi
tidak mencabut grant lama yang sudah tidak sesuai. Akibatnya menjalankan seed
baru tidak otomatis membersihkan hak akses lama.

**Perbaikan:** buat satu policy RBAC kanonis yang:

1. Menetapkan baseline per role dan per modul.
2. Menghapus/reset baris yang dikelola policy sebelum memberi grant baru.
3. Memisahkan `view`, `create`, `edit`, `delete`, `export`, `approve`, `post`,
   `void`, dan `close_period` bila risikonya berbeda.
4. Menetapkan scope outlet/divisi/lokasi secara eksplisit.
5. Menyediakan preview diff hak akses sebelum apply.

### K-05. Bug penghapusan role

**Bukti kode:** `application/models/Role_model.php`, method `delete()`.

Penghapusan permission memakai kondisi `where('id', $id)` pada tabel
`auth_role_permission`. Yang seharusnya dipakai adalah `role_id`.

**Dampak:** permission milik role lain yang kebetulan mempunyai ID sama dapat
terhapus, permission role target dapat tertinggal, dan penghapusan role dapat
gagal karena foreign key. Method juga berisiko tetap melaporkan berhasil.

**Perbaikan:** gunakan transaksi, hapus berdasarkan `role_id`, periksa hasil
query, rollback jika gagal, dan buat test bahwa role yang tidak dipilih tidak
berubah.

### K-06. Konfigurasi keamanan belum layak produksi/komersial

Temuan konfigurasi:

- Encryption key disimpan langsung di repository.
- Kredensial database disimpan langsung di file konfigurasi yang dilacak Git.
- `cookie_secure` masih `FALSE`.
- `cookie_httponly` masih `FALSE`.
- Perlindungan CSRF masih `FALSE`.
- Environment default masih `development`.
- Login belum memiliki rate limit atau penundaan adaptif.

**Dampak:** salinan source membawa rahasia instalasi, cookie lebih mudah
disalahgunakan, request perubahan data tidak memiliki proteksi CSRF, dan
percobaan login berulang tidak dibatasi.

**Perbaikan:** pindahkan semua rahasia ke environment per instalasi, rotasi
rahasia yang sudah pernah masuk Git, wajibkan HTTPS di produksi, aktifkan
`Secure`, `HttpOnly`, dan `SameSite`, hidupkan CSRF dengan pengecualian API yang
memakai bearer token, serta tambahkan rate limit login. Password user sudah
memakai bcrypt dan bagian itu tetap dipertahankan.

### K-07. Deployment schema belum deterministik

`application/config/migration.php` masih menonaktifkan migration dan versi
schema berada di 0. Perubahan struktur saat ini bergantung pada SQL manual.

**Dampak untuk user:** lokal dan server dapat memiliki kombinasi kolom, menu,
dan permission yang berbeda. Inilah salah satu sumber respons non-JSON atau
error setelah kode baru dipasang tetapi SQL pendukung belum dijalankan.

**Perbaikan:** bangun registry migration yang mencatat nama, checksum, waktu,
hasil, dan versi aplikasi. Updater harus menjalankan preflight, backup,
migration terurut, smoke test, lalu menandai release berhasil.

## 5. Temuan Tinggi

### T-01. Nilai bulanan component dapat berbeda dari nilai lot FIFO

Snapshot audit menemukan 68 identitas component dengan kuantitas sama tetapi
nilai stok bulanan berbeda dari total nilai lot. Total selisih absolut sekitar
Rp528.301,83.

Contoh terbesar:

- `QUARTER CHICKEN FRIED`: kuantitas 5, nilai bulanan sekitar
  Rp-403.230,11, nilai lot sekitar Rp52.013,84, selisih sekitar Rp-455.243,95.
- `BUMBU IRENG`: selisih nilai sekitar Rp32.022,87.

POS sudah mengambil kuantitas dan biaya lot secara FIFO pada
`application/libraries/PosOrderStockService.php`. Namun, writer aggregate
component masih dapat mengurangi nilai bulanan memakai rata-rata bulanan,
bukan total biaya lot yang benar-benar dikeluarkan.

**Makna untuk user:** jumlah barang dapat terlihat sama dan tidak minus, tetapi
nilai rupiah pada Stock Health masih berbeda. Defisit tidak menyelesaikan
masalah ini karena defisit mengurus kekurangan kuantitas, bukan perbedaan metode
penilaian.

**Perbaikan:** setiap issue component harus mengirim total biaya aktual dari
baris FIFO ke movement dan monthly stock. HPP sementara POS tetap dihitung penuh
sesuai katalog/biaya live agar kasir tidak berat dan HPP tidak menjadi nol.

### T-02. Gudang masih memiliki dua kelompok sumber lot

Snapshot audit menemukan 17 identitas gudang yang berbeda antara stok bulanan
dan lot aktif. Total selisih absolut sekitar 117.953 unit dan Rp1.924.613,25.

Penyebab yang ditemukan:

1. Lot opening/receipt lama masih `OPEN`, sementara writer baru juga membuat
   lot aggregate `WAREHOUSE_PROFILE`. Stock Health kemudian menjumlahkan semua
   lot terbuka.
2. Store request operasional tertentu mengurangi monthly stock gudang, tetapi
   tidak selalu mempunyai `material_id` kanonis dan tidak mengurangi lot FIFO
   yang sepadan.

**Makna untuk user:** kasus ini masih dapat membuat mismatch baru walaupun
server normal. Server lambat bukan satu-satunya penyebab.

**Keputusan desain yang diperlukan:**

- Jika pengeluaran operasional benar-benar barang stok, wajib terhubung ke
  material/profile kanonis dan mengurangi lot FIFO.
- Jika pengeluaran tersebut bukan persediaan, pisahkan ke domain nonstock dan
  jangan bandingkan dengan Stock Health persediaan.

### T-03. Sumber nilai dan kuantitas harus menjadi satu writer

Konsep yang harus berlaku untuk semua modul:

| Sumber transaksi | Kuantitas | Nilai | Lot | Movement | Dokumen bisnis |
| --- | --- | --- | --- | --- | --- |
| PO receipt | Satu writer | Satu writer | Wajib | Wajib | Wajib |
| SR fulfillment | Satu writer | Satu writer | Wajib untuk stok | Wajib | Wajib |
| Batch produksi | Satu writer | Biaya input aktual | Wajib | Wajib | Wajib |
| POS | FIFO lintas lot/profil | Biaya issue FIFO + HPP sementara kekurangan | Wajib/defisit | Wajib | Wajib |
| Adjustment/recon | Satu writer | Cost lot/katalog terpilih | Wajib | Wajib | Wajib |
| Void/refund | Membalik bukti asal | Membalik biaya asal | Pasangan lot asal | Reversal | Dokumen reversal |

Tidak boleh ada controller yang hanya mengubah monthly stock, hanya mengubah
lot, atau hanya membuat movement tanpa pasangan lainnya.

## 6. Temuan Sedang

### S-01. Rincian payroll belum menjelaskan pembayaran makan terpisah

Snapshot audit menemukan 18 hasil payroll dengan selisih antara jumlah baris
earning dikurangi deduction dan `net_pay`. Contoh selisih: Pak Fajar Juli
Rp248.000, Bagas Juli Rp224.000, dan Fairuz Juli sekitar Rp216.000.

Polanya sama dengan `meal_total`. Header payroll diduga benar karena sistem
mengenali makan sudah dibayar terpisah, tetapi rincian slip masih menampilkan
tunjangan makan sebagai earning tanpa baris pengurang/penjelasan pembayaran
terpisah.

**Perbaikan:** jangan mengubah net pay lama. Tambahkan baris informatif
`Tunjangan makan telah dibayar terpisah` atau deduction teknis yang membuat
rekonsiliasi slip menjadi transparan.

### S-02. Riwayat saldo rekening membingungkan untuk transaksi backdate

Saldo rekening sekarang benar. Namun, 51 mutasi pada snapshot tidak membentuk
rantai before/after yang urut bila laporan diurutkan berdasarkan tanggal bisnis.
Writer menghitung saldo berdasarkan urutan input, sementara layar dapat
menampilkan berdasarkan `mutation_date`.

**Perbaikan:** simpan dua urutan dengan jelas:

- `posted_at/sequence_id` sebagai urutan perubahan saldo yang sebenarnya.
- `effective_date` sebagai tanggal bisnis transaksi.

Layar riwayat harus menjelaskan bila transaksi dimasukkan mundur dan tidak
boleh menampilkan `balance_before/after` seolah-olah dihitung ulang secara
kronologis.

### S-03. QR ulasan publik belum memiliki perlindungan spam

Halaman station review dapat membuat member dan review tanpa CAPTCHA, rate
limit, cooldown nomor telepon, atau pencegahan duplikasi yang memadai.

**Perbaikan:** tambahkan rate limit per IP/nomor/station, challenge ringan,
idempotency token, status moderasi, dan batas pembuatan member. Review dari QR
nota dapat memakai token order satu kali agar lebih terpercaya.

### S-04. Log availability tumbuh tanpa retensi

Log rebuild availability pada snapshot telah mencapai sekitar 287,22 MB dan
867 ribu baris. Writer selalu menambah log, sedangkan pembaca hanya memerlukan
status terbaru per produk.

**Perbaikan:** simpan status terakhir di tabel ringkas, pertahankan detail untuk
masa audit tertentu, lalu prune atau partisi data lama melalui job terjadwal.

### S-05. Beberapa endpoint kecil masih fail-open

Contoh yang perlu ditutup:

- Halaman lot gudang hanya memanggil `require_permission()` bila `can()` lebih
  dahulu bernilai benar. Jika page code tidak ada, endpoint justru dapat lolos.
- Writer kategori live dashboard belum memiliki guard mutasi yang jelas.
- Page code `purchase.stock.warehouse.lot.index` dan `my.schedule.index` belum
  konsisten terdaftar pada snapshot RBAC.

Prinsip perbaikannya adalah **fail closed**: page code tidak ditemukan harus
berarti ditolak dan dicatat ke audit log, bukan diizinkan.

### S-06. Struktur menu, route, dan module masih memiliki duplikasi

Temuan struktur:

- Dua menu aktif mengarah ke `/pos/reports/sales`.
- Permission untuk page procurement lama masih tersisa walaupun page tidak
  aktif.
- Nama module terpecah seperti `PRODUCTION/PRODUKSI`, `SYSTEM/SISTEM`, dan
  `WA/WHATSAPP`.
- Route outlet/terminal dideklarasikan dua kali.

**Perbaikan:** buat registry menu/page/route kanonis, migrasikan alias lama,
nonaktifkan duplikasi, lalu audit permission yatim.

### S-07. Tabel backup berada di schema aktif

Terdapat sedikitnya 12 tabel backup/legacy tanpa primary key di schema aktif,
antara lain kelompok `backup_inv_wh_*` dan `zz_bak_*`.

Tabel tersebut tidak boleh langsung dihapus. Pindahkan ke database arsip
read-only setelah dependency scan membuktikan tidak ada kode, view, trigger,
atau laporan yang membacanya.

### S-08. Belum ada automated test proyek

Seluruh file PHP yang diperiksa lolos lint sintaks, tetapi tidak ditemukan test
unit/integrasi proyek. Lint hanya membuktikan PHP dapat diparse, bukan
membuktikan hak akses, transaksi, rollback, HPP, atau saldo benar.

Ini menjadi penghambat utama updater otomatis karena setiap update customer
harus dapat membuktikan perilaku penting tetap sama.

## 7. Utang Data Historis

### 7.1 Eligibility PH lama

Terdapat 6 jadwal PH milik Fadilla yang tanggalnya lebih awal daripada
`effective_date` eligibility 17 Agustus 2026. Tanggal yang terdeteksi meliputi
6 April, 10 Juni, 6 Juli, 30 Juli, 5 Agustus, dan 11 Agustus.

Ini perlu ditentukan sebagai salah satu dari dua keadaan:

1. Eligibility sebenarnya berlaku sejak sebelum tanggal tersebut dan master
   effective date perlu dikoreksi dengan dokumen keputusan.
2. Jadwal PH lama salah dan harus dinetralkan melalui koreksi berjejak.

Jangan mengubah saldo PH massal sebelum keputusan administrasinya jelas.

### 7.2 Receipt purchase lama

Audit sebelumnya menemukan 13 header purchase `PAID` yang receipt-nya belum
lengkap, 31 receipt line posted tanpa material kanonis, dan 6 tanpa pasangan
lot. Seluruh contoh berasal dari Juni; Juli dan Agustus tidak menunjukkan pola
yang sama.

Artinya writer terbaru terlihat sudah lebih baik, sedangkan data Juni perlu
dipisahkan sebagai antrean historis. Repair hanya dilakukan setelah nomor PO,
barang fisik, nilai, dan lot sumber diverifikasi.

### 7.3 Mismatch lama bukan alasan repair massal

Data sebelum perubahan writer, sebelum cut-off, atau saat server gagal tidak
boleh dipukul rata dengan data aktif. Untuk setiap repair harus tersedia:

1. SQL preview baca-saja.
2. Daftar kandidat dan alasan per baris.
3. Assertion bahwa writer baru sudah diperbaiki.
4. SQL apply idempotent dalam transaksi.
5. Audit before/after.
6. SQL verifikasi akhir dan jalur rollback/reversal.

## 8. Penjelasan Defisit dan Mismatch

Defisit dan mismatch menyelesaikan masalah yang berbeda.

### Defisit

Contoh: resep membutuhkan NORI 10 GR, tetapi lot yang tersedia hanya 4 GR.
Sistem mengeluarkan 4 GR dari lot, tetap menghitung HPP penuh 10 GR memakai
biaya sementara yang wajar, lalu mencatat kekurangan 6 GR sebagai defisit.
Saldo lot tidak menjadi minus.

Defisit tetap muncul di dashboard sampai:

- barang masuk dengan identitas yang tepat;
- recon fisik membuktikan barang sebenarnya tersedia;
- adjustment tambah yang sah menutup kekurangan; atau
- kekurangan ditutup administratif dengan alasan dan persetujuan.

### Mismatch

Contoh: monthly stock mencatat 10 GR dan Rp10.000, tetapi total lot aktif
mencatat 10 GR dan Rp12.000. Tidak ada stok minus dan belum tentu ada defisit,
tetapi nilai sumber persediaan berbeda Rp2.000.

Karena itu implementasi defisit tidak otomatis menghapus mismatch. Mismatch
baru berhenti ketika seluruh writer memakai lot, movement, monthly stock, dan
nilai biaya yang sama dalam satu transaksi database.

## 9. Roadmap Pengembangan

Roadmap ini memakai gerbang hasil, bukan sekadar urutan tanggal. Fase berikutnya
baru dimulai setelah acceptance criteria fase sebelumnya terpenuhi.

### Fase 0. Bekukan baseline dan siapkan pengaman

**Tujuan:** memastikan kita tahu versi kode dan schema yang sedang diuji.

Pekerjaan:

1. Tag commit baseline stabil.
2. Catat checksum seluruh SQL yang pernah dijalankan di lokal dan server.
3. Buat backup database dan uji restore ke database terpisah.
4. Buat halaman/version endpoint yang menampilkan versi aplikasi, schema, dan
   migration terakhir tanpa membuka rahasia.
5. Pisahkan credential lokal, staging, dan produksi.

**Selesai bila:** clone bersih dapat dipasang ke database kosong, migration
berjalan terurut, login berhasil, dan versi schema dapat dibuktikan.

### Fase 1. Pengamanan RBAC dan autentikasi

**Prioritas:** P0, wajib sebelum komersialisasi atau distribusi APK.

Pekerjaan:

1. Guard seluruh endpoint `Master` dan `Master_relation`.
2. Guard API mobile per endpoint dan aksi.
3. Perbaiki penghapusan role.
4. Buat canonical RBAC policy dan seed reset-terkontrol.
5. Perbaiki scope outlet/divisi untuk user multi-role.
6. Daftarkan page code yang hilang dan ubah seluruh guard menjadi fail-closed.
7. Pindahkan secret ke environment, aktifkan keamanan cookie, CSRF, HTTPS, dan
   rate limit login.
8. Tambahkan audit log untuk perubahan role, permission, scope, dan token.

**Selesai bila:** setiap role hanya dapat membuka dan memposting aksi yang
tercantum pada matrix; direct URL dan API menghasilkan 403; perubahan role
langsung mencabut kemampuan token lama.

### Fase 2. Satukan writer persediaan dan nilai

**Prioritas:** P0 untuk akurasi persediaan dan HPP.

Pekerjaan:

1. Ubah aggregate component agar memakai total cost issue FIFO aktual.
2. Audit semua caller batch, POS, void, refund, adjustment, dan recon.
3. Tetapkan penanganan SR operasional sebagai stock atau nonstock.
4. Normalisasi writer gudang ke satu jenis lot aktif.
5. Buat invariant checker setelah transaksi: monthly qty = lot qty dan
   monthly value = lot value dalam toleransi pembulatan.
6. Hentikan posting dan rollback transaksi bila invariant baru gagal.
7. Setelah writer aman, buat preview/apply untuk 68 component dan 17 identitas
   gudang pada snapshot aktual, bukan memakai angka statis dokumen ini.

**Selesai bila:** rangkaian PO, SR, batch, POS, adjustment, void, dan refund
tidak menambah antrean mismatch baru; HPP penuh tetap tercatat saat defisit.

### Fase 3. Test integrasi transaksi utama

**Prioritas:** P0 untuk updater dan rilis customer.

Bangun fixture dan test untuk:

1. PO receipt ke gudang dan langsung ke divisi.
2. SR gudang ke divisi, termasuk lintas lot/profil.
3. Batch produksi component dan void batch.
4. Adjustment plus/minus, recon fisik, deficit settlement, dan write-off.
5. POS reguler, extra, bundle, reservasi, self order, dan online order.
6. Voucher lebih besar dari tagihan.
7. Payment bertahap, DP, refund sebagian/penuh, dan void.
8. Printer event, mode otomatis/tanya dulu/tidak cetak.
9. PH grant/use/expiry dan guarding jadwal bulanan.
10. Payroll, mutasi rekening, dan jurnal penutupan.
11. Uji kegagalan di tengah transaksi untuk memastikan rollback dan retry
    idempotent.

**Selesai bila:** test berjalan otomatis di satu perintah, database test selalu
kembali bersih, dan seluruh jalur kritis lulus sebelum paket release dibuat.

### Fase 4. Transparansi payroll dan keuangan

**Prioritas:** P1.

Pekerjaan:

1. Rekonsiliasi rincian payroll dengan pembayaran makan terpisah.
2. Bedakan tanggal efektif dan urutan posting mutasi rekening.
3. Tambahkan laporan audit backdate.
4. Pastikan void tidak dihitung sebagai belanja, produksi, spoil, omzet, HPP,
   atau kas operasional; reversal tetap terlihat di audit.
5. Tambahkan closing check untuk rekening, payroll, penjualan, HPP, dan stok.

**Selesai bila:** setiap angka ringkasan dapat dijelaskan oleh baris detail dan
setiap reversal mengecualikan transaksi batal dari analisis operasional.

### Fase 5. Bersihkan data dan struktur historis

**Prioritas:** P1 setelah writer baru stabil.

Pekerjaan:

1. Putuskan eligibility PH Fadilla berdasarkan dokumen administrasi.
2. Audit dan selesaikan receipt Juni satu per satu.
3. Pindahkan tabel backup ke schema arsip read-only.
4. Rapikan module, route, page, menu, dan permission duplikat.
5. Tambahkan retensi log availability dan runtime job.

**Selesai bila:** schema aktif hanya memuat tabel operasional, tidak ada menu
ganda, tidak ada permission yatim, dan audit historis mempunyai status selesai
atau keputusan tertulis.

### Fase 6. Deployment, updater, dan observability

**Prioritas:** P1 untuk komersialisasi.

Pekerjaan:

1. Buat migration runner dengan checksum dan lock.
2. Buat release manifest bertanda tangan.
3. Jalankan preflight requirement PHP, MariaDB, extension, printer agent, dan
   kapasitas disk.
4. Tambahkan health check database, queue, cron, printer agent, dan storage.
5. Buat backup otomatis sebelum update dan rollback release.
6. Tampilkan status update dengan bahasa user, bukan raw error HTML/PHP.
7. Tambahkan alert untuk queue gagal, mismatch baru, saldo negatif, dan log
   yang membesar.

**Selesai bila:** update dapat diuji di clone database, berhenti aman bila
preflight gagal, dan tidak meninggalkan schema setengah terpasang.

### Fase 7. Kesiapan komersialisasi dan leveling fitur

**Prioritas:** P2 setelah Fase 0-6 lulus.

Pekerjaan mengikuti
`docs/2026-08-28_roadmap_komersialisasi_finance_dan_lisensi.md`:

1. Feature entitlement terpisah dari RBAC.
2. Lisensi organisasi, outlet, dan terminal POS.
3. Aktivasi online/offline dan pencabutan terminal.
4. Paket fitur bertingkat tanpa menghapus data customer.
5. Installer, updater, dokumentasi, support bundle, dan diagnostic export.
6. Penandatanganan release dan pemisahan private key dari aplikasi customer.
7. Uji upgrade dari minimal dua versi lama ke versi terbaru.

**Selesai bila:** customer dapat memasang, mengaktifkan, memperbarui, membuat
backup, dan mengekspor datanya tanpa akses repository pengembangan.

## 10. Urutan Implementasi yang Disarankan

| Urutan | Pekerjaan | Alasan |
| ---: | --- | --- |
| 1 | Guard Master dan Master Relation | Menutup perubahan data tanpa izin. |
| 2 | Perbaiki Role delete dan reset RBAC | Menghentikan overgrant dan kerusakan permission. |
| 3 | Guard API mobile dan token lifecycle | Wajib sebelum APK digunakan luas. |
| 4 | Security production config | Wajib sebelum instalasi customer. |
| 5 | Writer nilai component | Menghentikan mismatch nilai baru. |
| 6 | Writer lot gudang dan SR operasional | Menghentikan mismatch gudang baru. |
| 7 | Test integrasi transaksi | Membuktikan perbaikan tidak merusak modul lain. |
| 8 | Payroll dan riwayat backdate | Membuat laporan mudah direkonsiliasi. |
| 9 | Migration/updater | Membuat deploy lokal, server, dan customer seragam. |
| 10 | Cleanup historis dan komersialisasi | Dilakukan setelah writer dan test stabil. |

## 11. Matrix Pengujian Manual oleh Pemilik

Pengujian manual dilakukan setelah test internal otomatis lulus. Gunakan data
uji yang mudah dikenali dan catat nomor dokumennya.

### RBAC

1. Login sebagai Superadmin, Management, HOD, Purchase, Gudang, Finance,
   Barista, Kasir, dan Staff.
2. Pastikan menu sesuai tugas masing-masing.
3. Tempel direct URL halaman yang tidak berhak dibuka; hasil harus 403.
4. Kirim form/API aksi yang tidak berhak; tidak boleh hanya mengandalkan menu.
5. Cabut satu role saat token APK aktif; request berikutnya harus ditolak.

### Purchase, SR, dan gudang

1. Buat PO, receipt sebagian, receipt selesai, dan void.
2. Pastikan nilai PO hanya menghitung bagian yang tidak void.
3. Buat SR dan fulfillment lintas lot/profil.
4. Pastikan gudang berkurang, divisi bertambah, lot/movement/nilai sama.
5. Jalankan Stock Health dan pastikan transaksi baru tidak menambah mismatch.

### Produksi dan inventory

1. Buat batch dengan beberapa bahan dan cost berbeda.
2. Void batch dan pastikan produksi batal tidak masuk analisis produksi.
3. Buat adjustment plus/minus dan void adjustment.
4. Buat penjualan saat stok sebagian atau nol; transaksi harus berhasil dan
   kekurangan muncul sebagai defisit.
5. Terima barang/recon yang sesuai dan pastikan settlement defisit berjejak.

### POS, HPP, dan keuangan

1. Jual produk reguler, extra, dan bundle.
2. Gunakan voucher bernilai lebih besar dari tagihan; pembayaran tambahan nol.
3. Bayar DP dan lunasi dengan metode berbeda.
4. Lakukan refund sebagian, refund penuh, dan void.
5. Pastikan penjualan bersih, HPP, rekening, stok, lot, dan laporan voucher
   mengikuti transaksi yang benar.
6. Pastikan transaksi void tidak masuk omzet/HPP operasional.

### Attendance dan payroll

1. Jadwalkan PH saat saldo tersedia dan saat saldo nol.
2. Uji batas jumlah jadwal bulanan dan pengecualian yang disetujui.
3. Uji grant pada hari libur nasional dengan presensi sah.
4. Uji expiry tepat setelah tanggal berlaku berakhir.
5. Generate payroll dan cocokkan setiap baris earning/deduction dengan net pay.

### Deployment

1. Pasang aplikasi pada database kosong.
2. Upgrade clone database produksi dari versi sebelumnya.
3. Putuskan proses di tengah migration dan pastikan dapat dilanjutkan atau
   rollback tanpa schema setengah jadi.
4. Uji backup dan restore sebelum rilis dinyatakan siap.

## 12. Aturan Kerja untuk Fase Berikutnya

Setiap fase pengembangan harus ditutup dengan format yang sama:

1. **Sebelum:** jelaskan masalah yang dialami user dan risiko bisnisnya.
2. **Perbaikan:** jelaskan halaman dan proses yang berubah dengan bahasa user.
3. **Sesudah:** jelaskan perilaku baru yang dapat diamati.
4. **Test internal:** sebutkan lint, test otomatis, query invariant, dan smoke
   test yang sudah dijalankan oleh developer.
5. **Yang harus dijalankan user:** sebutkan SQL/migration secara urut dan
   apakah perlu dijalankan di lokal serta server.
6. **Test manual user:** berikan skenario, angka awal, tindakan, dan hasil yang
   diharapkan.
7. **Rollback:** jelaskan cara membatalkan perubahan bila hasil tidak sesuai.
8. **Status:** nyatakan `CLEAR`, `PERLU TEST MANUAL`, atau `BLOCKED`; jangan
   menyebut selesai bila acceptance criteria belum terpenuhi.

## 13. Keputusan yang Tidak Boleh Diubah Diam-diam

1. POS tetap boleh menjual saat stok sistem kurang; kekurangan menjadi defisit,
   bukan stok minus.
2. HPP transaksi tetap dihitung penuh dengan biaya live/katalog yang wajar,
   walaupun sebagian kebutuhan masuk defisit.
3. Pajak dan service tetap diperlakukan sebagai pendapatan sesuai keputusan
   bisnis saat ini.
4. Void dan refund tidak menghapus audit trail, tetapi transaksi batal tidak
   dihitung sebagai aktivitas keuangan/operasional bersih.
5. Reconcile stok fisik dan penyelesaian defisit adalah dua keputusan yang
   berbeda dan harus jelas bagi operator.
6. Data historis tidak diperbaiki otomatis hanya agar dashboard menjadi hijau.
7. Feature license tidak menggantikan RBAC. Lisensi menentukan fitur milik
   customer; RBAC menentukan pegawai yang boleh menggunakannya.
8. Setiap perubahan schema wajib memperbarui seluruh UI, writer, report, API,
   permission, seed, dan test yang terdampak.

## 14. Definisi Siap Dijual

Finance baru dinyatakan siap dipasang ke customer berbayar bila seluruh syarat
berikut terpenuhi:

- Tidak ada endpoint mutasi tanpa permission per aksi.
- Tidak ada secret customer atau private key produk di repository/package.
- Instalasi bersih dan upgrade lama lulus migration otomatis.
- Test transaksi kritis berjalan otomatis dan lulus.
- Tidak ada mismatch baru dari transaksi normal pada periode uji.
- Backup dan restore telah diuji, bukan hanya dijadwalkan.
- Log dan queue mempunyai retensi serta alert.
- APK dapat dicabut per terminal dan mengikuti perubahan RBAC.
- Laporan penjualan, HPP, stok, rekening, payroll, void, dan refund dapat
  direkonsiliasi sampai dokumen sumber.
- Customer memiliki panduan instalasi, update, backup, recovery, dan diagnostic
  export dengan bahasa nonteknis.

## 15. Kesimpulan Akhir

Finance tidak perlu dibangun ulang. Arsitektur bisnis utamanya sudah bernilai
dan sebagian besar modul telah bekerja. Fokus berikutnya adalah mengubah
aplikasi dari **sistem internal yang kaya fitur** menjadi **produk yang aman,
deterministik, dapat diuji, dan dapat dipelihara di banyak instalasi**.

Langkah paling tepat setelah dokumen ini adalah memulai Fase 1 dari guard CRUD
master dan canonical RBAC. Setelah pintu akses aman, lanjutkan writer nilai
component dan lot gudang, lalu kunci semuanya dengan test integrasi. Fitur
lisensi, updater customer, dan leveling paket baru dibangun di atas fondasi
tersebut agar tidak memperbanyak instalasi yang mempunyai bug atau schema
berbeda.
