# Audit Total Aplikasi Finance dan Roadmap Pengembangan

**Tanggal audit awal:** 2026-08-30
**Pembaruan kesiapan jual:** 2026-08-31
**Sifat audit:** Pemeriksaan baca-saja terhadap kode, struktur RBAC, sidebar,
writer transaksi, snapshot database lokal, konfigurasi sistem, artefak rilis,
integrasi lokal, dan kebutuhan operasional.
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
9. **Repository saat ini tidak boleh dijadikan paket customer.** Backup
   database, upload pelanggan, log, PID, konfigurasi perangkat, dan kredensial
   instalasi masih bercampur dengan source pengembangan.
10. **Pengaturan sistem belum menjadi pusat identitas produk.** Nama aplikasi,
    nama usaha, logo, alamat, domain, warna, identitas dokumen, dan URL publik
    masih tersebar di banyak file atau tabel khusus modul.
11. **Printer Agent dan layanan pendamping belum siap didistribusikan.** API
    lokal cetak belum memiliki autentikasi request, bootstrap server dapat
    fail-open, dan proses masih memakai development server Flask.
12. **Kebutuhan runtime belum dinyatakan dengan benar.** Kode memakai fitur
    PHP 8, tetapi `composer.json` masih menyatakan PHP 5.3.7; dependency PHP
    dan Python juga belum dikunci untuk build yang dapat direproduksi.

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

### K-08. Repository membawa data operasional dan artefak instalasi

Snapshot Git pada audit ini menunjukkan:

- 950 file di `backup/` dengan ukuran working tree sekitar 3,47 GB;
- 321 file di `uploads/` dengan ukuran sekitar 339,54 MB;
- 582 file di `assets/` dengan ukuran sekitar 550,30 MB;
- ukuran pack Git sekitar 6,73 GB;
- `application/config/database.php`, konfigurasi Printer Agent, log agent,
  cache Python, log WhatsApp, dan PID layanan masih dilacak Git.

Backup database dan upload bukan aset program. Isinya dapat membawa transaksi,
pegawai, customer, nomor telepon, kredensial, foto bukti, dan data usaha milik
instalasi saat ini.

**Klarifikasi keputusan:** dump, log, upload, dan konfigurasi lokal memang
tidak akan menjadi isi paket penjualan. Temuan ini tidak berarti backup aktif
harus dihapus dari laptop/server operasional sekarang. Temuan ini berarti paket
tidak boleh dibuat dengan ZIP/copy workspace dan proses build wajib membuktikan
semua jalur runtime tersebut sudah dikeluarkan.

**Dampak untuk bisnis:** menyalin repository atau membuat ZIP dari workspace
dapat membocorkan data customer lama ke customer baru. Menghapus file dari
working tree saja juga belum menghapusnya dari riwayat Git.

**Perbaikan wajib:**

1. Jangan menjadikan repository/workspace sekarang sebagai ZIP customer.
2. Generator memakai clean checkout dari tag/commit produk yang immutable,
   bukan folder aplikasi yang sedang berjalan.
3. `backup/`, `uploads/`, log, PID, cache, dump, `.git`, konfigurasi device,
   database config, dan file probe masuk daftar larangan global.
4. Build paket menggunakan allowlist dan fail bila path terlarang, secret,
   data pribadi, atau identitas instalasi terdeteksi pada staging artifact.
5. Runtime directory dibuat installer di lokasi/volume terpisah dan tidak
   pernah dimasukkan kembali ke update package.
6. Source tetap berada di private Git; generator menyimpan referensi commit dan
   artefak hasil build, bukan menyalin source mentah ke database dashboard.
7. Putuskan terpisah apakah riwayat Git lama perlu dibersihkan dan rotasi semua
   secret yang pernah masuk Git sebelum distribusi eksternal.

### K-09. Printer Agent masih fail-open dan perintah cetak lokal tidak diautentikasi

**Bukti kode:** `application/controllers/Pos_printer_agent.php` dan
`tools/pos_printer_agent/agent.py`.

- Jika `POS_PRINTER_BOOTSTRAP_KEY` kosong, endpoint bootstrap tetap dibuka.
- Key dapat dikirim lewat query string selain header.
- Local Agent memakai `CORS(app)` tanpa pembatasan origin.
- Endpoint `POST /cetak` menerima payload cetak tanpa token, signature,
  timestamp, nonce, atau pemeriksaan origin.
- Satu development server Flask dibuat per printer dan `/health` mengekspos
  identitas perangkat lokal.

**Dampak untuk user:** halaman web berbahaya yang dibuka di komputer kasir
berpotensi mengirim perintah ke port localhost. Konfigurasi printer juga dapat
diambil tanpa key bila environment belum disiapkan dengan benar.

**Perbaikan wajib:** bootstrap harus fail-closed, key tidak boleh berada di
URL, pairing menghasilkan secret per instalasi/agent, setiap request cetak
ditandatangani dan dilindungi dari replay, CORS dibatasi atau dihapus, payload
dan jumlah copy dibatasi, dan agent dijalankan sebagai satu service produksi
dengan audit serta health check.

### K-10. Rahasia dan operasi server bercampur dengan pengaturan aplikasi

`System_tools` saat ini menyimpan `backup.db_pass` dan `repl.repl_pass` di
`sys_app_config`. Form tes koneksi juga mengirim password melalui query string.
Halaman yang sama dapat menjalankan backup, sinkronisasi, konfigurasi MySQL,
replikasi, tunnel, dan failover dengan izin CRUD generik.

**Dampak:** secret dapat ikut backup database, terlihat di browser history,
access log, reverse proxy, atau diagnostic capture. Izin `edit` biasa menjadi
terlalu kuat untuk operasi server yang dapat menimpa data atau mengubah arah
replikasi.

**Perbaikan wajib:**

1. Secret deployment disimpan di environment/secret store dengan encryption
   key di luar database; UI hanya menampilkan status dan nilai tersamarkan.
2. Semua tes koneksi memakai POST dan tidak pernah mengembalikan DSN/password.
3. Pisahkan hak `manage_secret`, `run_backup`, `restore_backup`,
   `configure_replication`, `initial_sync`, dan `failover`.
4. Operasi berbahaya memerlukan re-authentication, konfirmasi bernama,
   approval dua pihak untuk produksi, dan audit before/after.
5. Halaman biasa untuk pemilik usaha tidak boleh memperoleh shell access.

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

### T-04. Kontrak runtime dan dependency tidak sesuai dengan kode aktual

Pemeriksaan menemukan aplikasi memakai `match`, `str_contains`, dan
`str_ends_with`, sehingga baseline aktual minimal PHP 8.0. Namun,
`composer.json` masih merupakan metadata framework CodeIgniter dan menyatakan
PHP `>=5.3.7`. `composer.lock` juga diabaikan Git. Dependency Printer Agent
memakai rentang versi luas dan WhatsApp Engine memakai dependency kandidat
rilis.

**Dampak:** installer dapat menyatakan server kompatibel padahal aplikasi akan
fatal error. Dua customer dengan versi dependency berbeda juga dapat menerima
perilaku berbeda dari source yang sama.

**Perbaikan:** tetapkan dan uji support matrix resmi, misalnya PHP 8.0/8.1,
MariaDB minimum yang disepakati, extension wajib, Node LTS, serta Python yang
didukung. Gunakan lockfile/checksum, dependency scan, dan preflight installer.
Build harus gagal bila versi atau extension tidak memenuhi kontrak.

### T-05. Pengaturan sistem dan identitas bisnis masih terfragmentasi

`Settings` saat ini pada dasarnya hanya mengelola akun dan password user.
Identitas usaha sebagian berada di outlet dan pengaturan printer, sementara
nama `NAMUA`, `MPP`, `Pemkab`, alamat Magnolia/Kabongan/Rembang, domain, logo,
dan warna masih muncul langsung di template, laporan, review, aset, menu book,
landing page, serta fallback service printer.

**Dampak untuk customer:** mengganti nama dan logo pada satu layar belum tentu
mengubah login, sidebar, dokumen, QR review, kontrak HR, label aset, atau hasil
cetak. Hal ini menimbulkan risiko identitas customer lama tampil pada instalasi
customer baru.

**Perbaikan:** bangun satu `System > Profil Usaha & Tampilan` dengan aturan
precedence yang jelas: override outlet/dokumen, lalu profil usaha pusat, lalu
default produk netral. Semua pembaca identitas wajib memakai service yang sama,
bukan fallback hardcode masing-masing.

### T-06. Belum ada produk installer, release manifest, dan jalur rollback resmi

Workspace masih memuat file probe di root, 67 SQL aktif dan 386 SQL di
`sql/_old`, tanpa manifest yang menjelaskan SQL install, migration, seed,
audit, repair, atau retired. Tidak ditemukan version endpoint, schema registry,
CI pipeline, atau test proyek.

**Dampak:** teknisi dapat menjalankan SQL yang salah atau urutannya keliru;
customer A dan B dapat mempunyai schema berbeda walaupun mengaku memakai versi
aplikasi yang sama.

**Perbaikan:** buat installer database kosong, migration registry ber-checksum,
manifest release bertanda tangan, preflight, backup, maintenance mode, health
check, rollback, dan build bersih yang tidak mengambil file di luar allowlist.

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

### S-09. Upload dan data publik memerlukan kebijakan keamanan yang seragam

Upload produk dan aset sudah membatasi tipe gambar, mengacak nama file, dan
membatasi ukuran. Itu merupakan fondasi yang baik. Namun kebijakan belum
terpusat, SVG mempunyai jalur sanitasi tersendiri, dan file runtime masih
berada di bawah web root serta ikut repository.

**Perbaikan:** buat satu upload service yang memeriksa MIME dari isi file,
decode/re-encode gambar raster, menolak polyglot, menonaktifkan eksekusi script
di folder upload, menyimpan evidence privat di luar public web root, serta
memberi signed download untuk bukti yang sensitif. Tambahkan quota, retensi,
antivirus opsional, dan audit penghapusan.

### S-10. Worker, cron, Printer Agent, dan WhatsApp belum memiliki lifecycle produk

POS sudah mempunyai endpoint CLI untuk runtime job dan availability queue,
tetapi pemasangan cron masih bergantung pada salin perintah. Printer Agent dan
WhatsApp juga membawa config, log, serta cara start/stop yang belum seragam.

**Perbaikan:** installer membuat service/Task Scheduler dan cron secara aman,
halaman aplikasi hanya memonitor tanpa shell umum, serta health center
menampilkan heartbeat, backlog, versi worker, error terakhir, dan tombol retry
yang terotorisasi. Uninstall, upgrade, rotasi log, dan recovery service harus
didokumentasikan untuk Windows dan Linux.

### S-11. Kebijakan security account belum cukup untuk produk multi-customer

Password minimum masih enam karakter, sesi dapat bertahan satu tahun, cookie
tidak secure/HttpOnly, tidak ada login throttling, dan belum ada MFA untuk akun
berisiko tinggi. Header keamanan web juga belum mempunyai baseline produksi.

**Perbaikan:** tetapkan password policy modern, idle timeout dan absolute
timeout terpisah, logout semua device, session version setelah perubahan role,
MFA/re-authentication untuk Superadmin dan operasi berbahaya, allowed-host
validation, trusted proxy, CSP bertahap, HSTS, frame/referrer policy, serta
audit login dan perubahan credential.

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

## 9. Audit Kesiapan Produk dan Pengaturan Sistem

### 9.1 Snapshot teknis yang sudah diverifikasi

Pembaruan 31 Agustus 2026 menghasilkan bukti berikut:

- 442 file PHP di `application/` lolos lint; tidak ada syntax error PHP.
- Tidak ditemukan folder test proyek atau pipeline CI.
- Terdapat 946 deklarasi route dan 6 key route ganda untuk outlet/terminal.
- Terdapat 453 file SQL: 67 di root aktif dan 386 di `sql/_old`.
- Migration CodeIgniter masih nonaktif dan versi schema masih 0.
- Semua page aktif pada snapshot mempunyai minimal satu permission dan tidak
  ada menu aktif yang menunjuk page hilang.
- Masih ada dua menu aktif yang menuju `/pos/reports/sales`.
- Hak database beberapa role terlalu luas untuk tugasnya. Contoh snapshot:
  KASIR memiliki 106 view, 84 create, 84 edit, dan 74 delete; BARISTA memiliki
  117 view, 87 create, 85 edit, dan 81 delete. Angka ini bukan bukti semua izin
  salah, tetapi cukup untuk mewajibkan reset berbasis matrix tugas.

Lint yang bersih adalah hasil positif, tetapi belum membuktikan transaksi,
otorisasi, rollback, atau laporan benar. Status komersial tetap mengikuti
acceptance test per modul.

### 9.2 Tiga lapisan konfigurasi yang tidak boleh dicampur

| Lapisan | Contoh | Siapa yang boleh mengubah | Penyimpanan yang benar |
| --- | --- | --- | --- |
| Pengaturan usaha | Nama usaha, logo, warna, alamat, pajak, nomor dokumen, locale | Owner/Management sesuai RBAC | Database dengan audit trail |
| Konfigurasi deployment | Database, encryption key, SMTP/API secret, backup, tunnel, private storage | Installer/teknisi berotorisasi | Environment atau secret store di luar database bisnis |
| Entitlement lisensi | Paket, fitur, batas outlet, terminal, maintenance | License Hub milik vendor | Dokumen/cache bertanda tangan; lokal hanya dapat membaca dan mengaktifkan |

Owner customer tidak boleh dapat menaikkan paket melalui toggle lokal. Sebaliknya,
vendor lisensi juga tidak boleh menerima transaksi, payroll, atau detail stok
customer hanya untuk memeriksa lisensi.

### 9.3 Halaman System Settings yang perlu dibangun

| Halaman | Isi utama untuk user | Catatan implementasi |
| --- | --- | --- |
| Profil Usaha | Nama aplikasi/customer, badan usaha, NPWP, alamat, telepon, email, sosial | Menjadi sumber identitas pusat |
| Branding & Tema | Logo light/dark, favicon, warna utama/aksen, login, sidebar | Preview desktop, mobile, dan print |
| Outlet & Dokumen | Identitas per outlet, prefix nota, invoice, label, footer, tanda tangan | Override outlet di atas profil pusat |
| Lokalisasi | Zona waktu, locale, mata uang, format tanggal/angka, bahasa | Tidak boleh hardcode WIB/Rupiah bila produk diperluas |
| Pajak, Service & Pembulatan | Tarif, inklusif/eksklusif, service, rounding, tanggal berlaku | Perubahan bertanggal dan berjejak |
| Keamanan Akun | Password policy, idle timeout, MFA, device/session, IP policy opsional | Batas minimum vendor tidak boleh diturunkan |
| Integrasi | Printer, WhatsApp, self-order, online order, payment gateway, SMTP | Secret write-only; tampilkan status, bukan nilainya |
| Data & Privasi | Retensi log, bukti, review, data member, ekspor dan penghapusan | Sesuai consent dan kontrak customer |
| Job & Kesehatan | Queue POS, cron, worker, Printer Agent, WA, storage, DB | Monitor dan retry terotorisasi, bukan shell umum |
| Backup & Recovery | Jadwal, retensi, hasil verifikasi restore | Hak sangat terbatas dan re-authentication |
| Update | Versi aplikasi/schema, channel, preflight, backup, rollback | Paket dan manifest wajib bertanda tangan |
| Lisensi & Aktivasi | Paket, fitur aktif, kuota outlet/terminal, masa support, status offline | Read-only dari entitlement kecuali aktivasi |
| Onboarding | Checklist profil, outlet, rekening, role, printer, backup, tes transaksi | Menandai instalasi layak go-live |

Struktur data kandidat adalah `sys_business_profile`, `sys_branding_setting`,
`sys_brand_asset`, `sys_localization_setting`, `sys_document_setting`,
`sys_public_url`, `sys_security_policy`, dan `sys_config_audit`. Nama tabel final
ditentukan setelah dependency scan; jangan membuat tabel baru bila data kanonis
sudah tersedia di outlet atau pengaturan modul.

Aturan pembacaan identitas:

1. override dokumen/outlet bila ada;
2. profil usaha pusat;
3. default produk yang netral, bukan identitas Namua atau MPP.

### 9.4 Matriks kesiapan modul

| Area user | Kondisi saat audit | Gerbang sebelum dijual |
| --- | --- | --- |
| Login, user, role, sidebar | Berfungsi, tetapi permission melebar dan security policy lemah | Guard per aksi, reset matrix, login throttling, session aman, audit role |
| Master barang/pegawai/vendor/rekening | Fitur lengkap, tetapi CRUD generik belum fail-closed | Registry entity-page dan direct URL/API test semua role |
| Resep, formula, extra, bundle | Berfungsi dan berdampak langsung ke HPP | Guard writer, versioning formula, audit before/after |
| Purchase Order, SR, gudang | Alur operasional tersedia | Test receipt parsial/penuh/void, fulfillment, lot, nilai, rekening, RBAC |
| Inventory, produksi, adjustment | Defisit dan lot negatif sudah lebih sehat | Satu writer kuantitas/nilai/lot, hentikan mismatch baru, test reversal |
| POS, voucher, DP, reservasi | Cakupan transaksi sangat lengkap | Test reguler/extra/bundle/DP/voucher/refund/void dan queue pada server lambat |
| HPP dan laporan penjualan | Audit dan laporan sudah tersedia | Rekonsiliasi order-item-HPP-stock-finance serta correction workflow |
| Keuangan | Saldo snapshot seimbang | Urutan posted/effective date, closing period, audit backdate, export |
| Attendance dan PH | Saldo/guard terakhir sehat | Test cutover, grant/use/expiry, schedule guard, timezone, payroll link |
| Payroll | Perhitungan ada | Slip transparan, approval/lock, makan terpisah, reversal dan export |
| Aset | Lock master dan change request tersedia | Test permission, approval, evidence privat, rekonsiliasi bulanan |
| Member, promo, review | Loyalty dan QR tersedia | Consent, anti-spam, dedupe nomor, privacy/retention, moderation |
| Printer | Konfigurasi terpusat mulai terbentuk | Secure pairing, signed request, service installer, retry/audit, fail-closed |
| WhatsApp | Engine dan UI tersedia | Secret production, service lifecycle, dependency stabil, consent dan opt-out |
| Landing/menu/roastery | Kaya konten tetapi sangat khusus Namua | Jadikan template/preset opsional atau keluarkan dari paket generik |
| APK/POS mobile API | Token tersedia | RBAC per endpoint, device key, revocation, version compatibility, rate limit |
| Backup/replikasi/system tools | Fitur teknis sangat kuat | Pisahkan secret dan hak berbahaya, test restore, approval, audit operator |
| Installer/update | Belum tersedia sebagai produk | Clean install, upgrade, checksum, signature, preflight, rollback, health check |

### 9.5 Matrix RBAC yang harus menjadi sumber tunggal

RBAC final harus disusun dari pekerjaan user, bukan dari menyalin hak role lain.
Setiap baris matrix minimal mempunyai:

- `page_code` kanonis;
- aksi `view/create/edit/delete/export/approve/post/void` sesuai kebutuhan;
- scope organisasi, outlet, divisi, lokasi, atau data milik sendiri;
- apakah aksi memerlukan re-authentication atau approval;
- feature code lisensi yang menjadi prasyarat;
- skenario direct URL/API yang membuktikan penolakan.

Seed instalasi harus bersifat deklaratif: hasil akhir role sama pada database
kosong maupun database lama. Seed aditif `ON DUPLICATE KEY UPDATE` saja tidak
dapat mencabut izin lama yang sudah tidak semestinya.

### 9.6 Artefak rilis customer yang benar

Paket customer hanya boleh berisi kode runtime, aset generik, migration yang
masih berlaku, dependency lock, installer, public key lisensi/update, dan
dokumentasi. Paket tidak boleh berisi:

- folder `.git`, backup, upload, log, PID, cache, dump, atau file probe;
- database config, API key, private key, token, atau konfigurasi printer lokal;
- SQL audit/repair customer lain dan seluruh arsip `sql/_old`;
- materi brand Namua kecuali dipilih sebagai demo/preset terpisah;
- alat internal yang dapat menjalankan repair massal tanpa otorisasi.

Setiap build menghasilkan SBOM/dependency list, checksum, signature, versi
aplikasi, versi schema minimum/maksimum, changelog, migration manifest, dan
hasil automated test.

### 9.7 Batas audit ini

Audit ini menggabungkan pemeriksaan kode, konfigurasi, struktur repository,
lint PHP, dan snapshot database lokal. Audit belum menggantikan penetration
test, review hukum lisensi/privasi, load test, restore drill, atau regression
test otomatis karena fasilitas tersebut belum tersedia. Karena itu istilah
"sehat" pada dokumen ini berarti tidak ditemukan anomali pada pemeriksaan yang
dilakukan, bukan jaminan bahwa modul bebas bug.

### 9.8 Data awal database dan generator instalasi

Paket customer tidak boleh dibuat dari dump database instalasi Namua. Database
baru harus dibangun dari migration dan seed kanonis yang mempunyai versi,
dependency, checksum, serta aturan idempotensi. Data dibagi sebagai berikut:

| Kelas data | Contoh | Perlakuan saat instalasi |
| --- | --- | --- |
| Struktur schema | Tabel, index, foreign key, view, dan registry migration | Dibuat oleh migration terurut; tidak berasal dari dump produksi. |
| Seed platform wajib | Katalog aksi permission, page/menu kanonis, status sistem, dan konfigurasi minimum | Selalu dipasang sesuai versi produk dan dapat direkonsiliasi secara deklaratif. |
| Seed referensi produk | Satuan, alasan transaksi, tipe dokumen, template default, dan role bawaan | Dipasang bila dependency fiturnya aktif; harus netral dan tidak memuat identitas Namua. |
| Entitlement paket | Edisi, feature code, batas outlet/terminal, dan masa maintenance | Tidak menjadi seed biasa; berasal dari lisensi bertanda tangan milik customer. |
| Data onboarding | Nama usaha, logo, timezone, mata uang, outlet pertama, owner, rekening, dan metode bayar | Diisi customer saat wizard instalasi; password/secret dibuat saat itu, bukan default di paket. |
| Data demo opsional | Produk, resep, pegawai, transaksi, dan contoh laporan fiktif | Artefak terpisah, berlabel demo, dan dapat dihapus penuh tanpa merusak instalasi. |
| Data runtime | Queue, session, audit, cache, log, file upload, dan hasil worker | Dibuat aplikasi setelah instalasi; direktori runtime dibuat kosong dengan izin yang tepat. |
| Data terlarang | Transaksi asli, customer/member, pegawai, presensi, payroll, stok, lot, HPP, mutasi rekening, credential, token, backup, dump, log, upload, PID, dan config device | Build harus gagal bila scanner menemukan data ini di artefak customer. |

Setiap seed wajib memiliki pemilik modul, versi mulai/berakhir, dependency
fitur, mode `insert/update/reconcile`, checksum, dan perilaku saat upgrade.
Seed RBAC harus mampu mencabut grant bawaan yang sudah tidak sah, bukan hanya
menambah baris melalui `ON DUPLICATE KEY UPDATE`. Tidak boleh ada seed customer
yang dibentuk melalui `INSERT ... SELECT` dari database operasional saat ini.

Generator instalasi harus menyediakan mode `dry-run` sebelum build. Laporannya
menjelaskan versi sumber, paket, fitur, migration, seed, file yang disertakan,
file yang dilarang/dikeluarkan, requirement runtime, serta alasan bila build
ditolak. Hasil generator minimum adalah:

1. artefak aplikasi generik untuk satu produk dan versi;
2. install manifest dan requirement matrix;
3. migration manifest dan seed manifest ber-checksum;
4. template bootstrap/environment tanpa secret;
5. profil onboarding dan entitlement customer bertanda tangan;
6. checksum, SBOM, release signature, changelog, dan dokumentasi;
7. installer online atau bundle offline yang dapat diverifikasi tanpa Git.

Artefak `clean install`, `upgrade`, dan `repair/support` harus dipisahkan.
SQL audit atau repair historis tidak boleh ikut jalur update umum. Clean install
dan upgrade minimal dua versi lama wajib menghasilkan schema serta seed
kanonis yang sama.

### 9.9 Product Control Center multi-produk

Karena produk yang akan dijual tidak hanya Finance, dashboard lisensi sebaiknya
dikembangkan menjadi aplikasi vendor terpisah bernama sementara **Product
Control Center**. Aplikasi ini menjadi control plane seluruh produk, bukan
bagian yang dipasang di server customer.

| Komponen | Tanggung jawab |
| --- | --- |
| Product Catalog | Produk, versi, edisi, feature code, dependency, requirement, dan lifecycle. |
| Repository Integration | Referensi repository privat, branch policy, commit/tag immutable, dan webhook; source tidak disimpan sebagai blob database. |
| Build Orchestrator | Menjalankan clean checkout dan build pada runner terisolasi dengan test, secret scan, data scan, serta approval. |
| Artifact Registry | Menyimpan metadata artefak, checksum, SBOM, signature, channel, dan lokasi object/artifact storage terlindungi. |
| Installation Profile | Manifest schema/seed, preset netral, onboarding, target OS, service, cron, dan dependency per produk. |
| Package & Feature Catalog | Paket jual, add-on, kapasitas, dependency fitur, serta harga/versi komersial. |
| License Hub | Customer, instalasi, outlet, terminal, entitlement, maintenance, aktivasi online/offline, revoke, dan transfer. |
| Release & Update Center | Channel pilot/stable/critical-fix, compatibility, rollout, rollback, dan status update. |
| Installation Monitor | Versi, kesehatan minimal, hasil update, kapasitas lisensi, dan heartbeat berbasis persetujuan. |
| Signing & Secret Service | Menjaga private key dan secret build di luar source serta di luar server customer. |
| Audit & Approval | Jejak siapa membuat build, memilih paket, menerbitkan lisensi, menyetujui release, dan mengunduh artefak. |

Metadata Product Control Center berada di database pusat, source berada di Git
privat, dan binary/package berada di artifact storage. Pemisahan ini mencegah
dashboard menjadi gudang source tunggal yang berisiko bocor. Build runner tidak
boleh memperoleh secret produksi customer dan harus dibuang setelah pekerjaan
selesai.

Satu versi produk menghasilkan satu artefak kode generik. Generator kemudian
menggabungkannya dengan install profile, seed profile, dan signed entitlement
customer. Paket Starter, Operations, atau Control tidak dibuat dengan
menghapus file source secara manual; perbedaannya ditegakkan oleh FeatureGate
dan lisensi. Untuk produk lain, Product Control Center memakai adapter/manifest
produk sehingga pipeline, lisensi, audit, dan updater dapat digunakan ulang.

Monitoring lisensi hanya boleh mengirim installation ID pseudonim, versi,
status lisensi, jumlah device terpakai, health service, dan hasil update yang
telah disetujui. Transaksi, omzet, stok, payroll, identitas pegawai/customer,
dan isi dokumen tidak boleh dikirim secara default.

## 10. Roadmap Pengembangan

Roadmap ini memakai gerbang hasil, bukan sekadar urutan tanggal. Fase berikutnya
baru dimulai setelah acceptance criteria fase sebelumnya terpenuhi.

### Fase 0. Bekukan baseline dan siapkan pengaman

**Tujuan:** memastikan kita tahu versi kode dan schema yang sedang diuji.

Pekerjaan:

1. Bekukan baseline internal dan jangan membuat paket customer dari repository
   yang membawa backup/upload saat ini.
2. Buat clean product repository atau build context berbasis allowlist, lalu
   rotasi secret yang pernah masuk Git.
3. Catat checksum seluruh SQL yang pernah dijalankan di lokal dan server.
4. Buat backup database dan uji restore ke database terpisah.
5. Buat halaman/version endpoint yang menampilkan versi aplikasi, schema, dan
   migration terakhir tanpa membuka rahasia.
6. Pisahkan credential lokal, staging, produksi, dan data demo.

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
9. Ubah Printer Agent menjadi pairing fail-closed dan autentikasi setiap
   request cetak.
10. Pisahkan hak backup/restore/replikasi/failover dari izin edit biasa.

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
2. Buat registry schema/seed kanonis serta generator instalasi dengan
   `dry-run`, allowlist, data scan, secret scan, dan manifest dependency.
3. Buat clean build dari commit/tag immutable; simpan artefak, SBOM, checksum,
   provenance, dan release manifest bertanda tangan.
4. Betulkan kontrak runtime dan lock dependency PHP, Node, serta Python.
5. Jalankan preflight requirement PHP, MariaDB, extension, printer agent, dan
   kapasitas disk.
6. Tambahkan health check database, queue, cron, printer agent, WA, dan storage.
7. Buat backup otomatis sebelum update dan rollback release.
8. Tampilkan status update dengan bahasa user, bukan raw error HTML/PHP.
9. Tambahkan alert untuk queue gagal, mismatch baru, saldo negatif, dan log
   yang membesar.

**Selesai bila:** generator menghasilkan clean install dan upgrade yang sama
dari sumber immutable, menolak data/secret terlarang, update dapat diuji di
clone database, berhenti aman bila preflight gagal, dan tidak meninggalkan
schema setengah terpasang.

### Fase 7. Kesiapan komersialisasi dan leveling fitur

**Prioritas:** P2 setelah Fase 0-6 lulus.

Pekerjaan mengikuti
`docs/2026-08-28_roadmap_komersialisasi_finance_dan_lisensi.md`:

1. Profil usaha, branding, lokalisasi, dokumen, dan onboarding menjadi
   pengaturan customer, bukan hardcode.
2. Pisahkan konfigurasi usaha, deployment secret, dan entitlement lisensi.
3. Feature entitlement terpisah dari RBAC.
4. Lisensi organisasi, outlet, dan terminal POS.
5. Aktivasi online/offline dan pencabutan terminal.
6. Paket fitur bertingkat tanpa menghapus data customer.
7. Bangun Product Control Center multi-produk untuk katalog produk, integrasi
   repository, build runner, artifact registry, generator instalasi, lisensi,
   release, monitoring, approval, dan audit.
8. Installer, updater, dokumentasi, support bundle, dan diagnostic export.
9. Penandatanganan release dan pemisahan private key dari aplikasi customer.
10. Uji upgrade dari minimal dua versi lama ke versi terbaru.

**Selesai bila:** customer dapat memasang, mengaktifkan, memperbarui, membuat
backup, dan mengekspor datanya tanpa akses repository pengembangan; produk baru
dapat ditambahkan ke Product Control Center melalui manifest/adapter tanpa
membangun ulang sistem lisensi dan generator dari nol.

## 11. Urutan Implementasi yang Disarankan

| Urutan | Pekerjaan | Alasan |
| ---: | --- | --- |
| 1 | Karantina data dari artefak produk dan rotasi secret | Mencegah data instalasi Namua ikut ke customer. |
| 2 | Guard Master dan Master Relation | Menutup perubahan data tanpa izin. |
| 3 | Perbaiki Role delete dan reset RBAC | Menghentikan overgrant dan kerusakan permission. |
| 4 | Guard API mobile dan token lifecycle | Wajib sebelum APK digunakan luas. |
| 5 | Security production, System Tools, dan Printer Agent | Menutup akses web/local service yang berbahaya. |
| 6 | Writer nilai component, gudang, dan SR | Menghentikan mismatch baru pada server normal. |
| 7 | Automated test transaksi dan RBAC | Membuktikan perbaikan tidak merusak modul lain. |
| 8 | Payroll dan riwayat backdate | Membuat laporan mudah direkonsiliasi. |
| 9 | Profil usaha, branding, lokalisasi, dan onboarding | Menghapus identitas hardcode sebelum pilot. |
| 10 | Runtime contract, registry schema/seed, Install Generator, dan updater | Membuat setiap instalasi dapat direproduksi dan versinya dapat dibuktikan. |
| 11 | Katalog fitur, paket, dan FeatureGate | Satu codebase dapat melayani paket berbeda. |
| 12 | Product Control Center, License Hub, artifact registry, device activation, dan signed update | Menjadi control plane aman untuk Finance dan produk berikutnya tanpa menyandera data. |
| 13 | Pilot, restore drill, support SOP, dan legal review | Gerbang akhir sebelum penjualan luas. |

## 12. Matrix Pengujian Manual oleh Pemilik

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

## 13. Aturan Kerja untuk Fase Berikutnya

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

## 14. Keputusan yang Tidak Boleh Diubah Diam-diam

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

## 15. Definisi Siap Dijual

Finance baru dinyatakan siap dipasang ke customer berbayar bila seluruh syarat
berikut terpenuhi:

- Tidak ada endpoint mutasi tanpa permission per aksi.
- Tidak ada secret customer atau private key produk di repository/package.
- Tidak ada backup, upload, log, PID, cache, data Namua, atau konfigurasi
  perangkat lokal di artefak customer.
- Nama usaha, logo, tema, locale, identitas dokumen, dan URL publik dapat
  diatur tanpa mengubah source code.
- Instalasi bersih dan upgrade lama lulus migration otomatis.
- Test transaksi kritis berjalan otomatis dan lulus.
- Tidak ada mismatch baru dari transaksi normal pada periode uji.
- Backup dan restore telah diuji, bukan hanya dijadwalkan.
- Log dan queue mempunyai retensi serta alert.
- Printer Agent dan layanan pendamping memakai pairing/authentication, service
  manager produksi, rotasi log, dan health check.
- APK dapat dicabut per terminal dan mengikuti perubahan RBAC.
- Laporan penjualan, HPP, stok, rekening, payroll, void, dan refund dapat
  direkonsiliasi sampai dokumen sumber.
- FeatureGate menolak fitur di luar paket pada UI, URL, API, job, dan APK tanpa
  menghapus data customer.
- Versi aplikasi, schema, dependency, dan release signature dapat dibuktikan
  dari halaman System tanpa membuka secret.
- Customer memiliki panduan instalasi, update, backup, recovery, dan diagnostic
  export dengan bahasa nonteknis.

## 16. Kesimpulan Akhir

Finance tidak perlu dibangun ulang. Arsitektur bisnis utamanya sudah bernilai
dan sebagian besar modul telah bekerja. Fokus berikutnya adalah mengubah
aplikasi dari **sistem internal yang kaya fitur** menjadi **produk yang aman,
deterministik, dapat diuji, dan dapat dipelihara di banyak instalasi**.

Langkah paling tepat setelah dokumen ini adalah menyiapkan baseline produk yang
tidak membawa data/secret instalasi, lalu memulai guard CRUD master dan
canonical RBAC. Setelah pintu akses aman, lanjutkan writer nilai component dan
lot gudang, lalu kunci semuanya dengan test integrasi. Profil usaha dan
installer diselesaikan sebelum pilot; fitur lisensi, updater customer, dan
leveling paket baru dibangun di atas fondasi tersebut agar tidak memperbanyak
instalasi yang mempunyai bug, identitas hardcode, atau schema berbeda.
