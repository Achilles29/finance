# Roadmap Komersialisasi Finance POS

**Status:** Keputusan produk dan arsitektur lisensi untuk menjadi acuan
implementasi. Diperbarui 31 Agustus 2026 dengan keputusan satu codebase,
entitlement per paket, dan productization/branding. Dokumen ini belum mengubah
kode, database, lisensi, atau perilaku aplikasi yang sedang dipakai.

## Keputusan Final Singkat

1. Finance dijual sebagai **lisensi perpetual per organisasi dan instalasi**,
   bukan penjualan source code.
2. Semua paket memakai **satu codebase, satu skema database, dan satu jalur
   update**. Kita tidak membuat source code Starter, Operations, dan Control
   secara terpisah.
3. Paket Starter dimulai dari **1 outlet dan 1 terminal POS aktif**. Paket
   Operations menjadi titik awal yang disarankan untuk 3 terminal. Outlet,
   terminal, APK POS, dan modul tertentu dapat ditambah sebagai add-on.
4. Fitur berbayar hanya dapat diaktifkan oleh entitlement bertanda tangan dari
   License Hub. Pengaturan lokal customer tidak boleh menaikkan paket sendiri.
5. Customer tetap dapat memakai versi yang telah dibeli setelah masa support
   berakhir. Yang berhenti adalah update dan dukungan baru, bukan akses data
   atau transaksi kasir.
6. Masa update dan dukungan standar adalah **12 bulan** sejak aktivasi.
   Perpanjangan maintenance membuka kembali akses release dan support tanpa
   mengubah hak pakai perpetual yang sudah dimiliki.
7. Lisensi menentukan paket dan kapasitas customer. RBAC tetap menentukan
   pegawai mana di customer tersebut yang boleh memakai fitur.
8. Setiap customer pada tahap awal memakai database dan instalasi sendiri.
   Multi-tenant cloud bukan bagian dari rilis komersial pertama.
9. Update dilakukan melalui paket rilis bertanda tangan dan migration yang
   diaudit, bukan melalui `git pull` di server customer.
10. Aplikasi tidak boleh mati mendadak ketika internet putus. Lisensi memakai
   cache bertanda tangan dengan grace period offline 30 hari dan jalur aktivasi
   offline untuk lokasi tanpa internet stabil.
11. Nama usaha, logo, ikon, warna, alamat, tautan publik, dan identitas dokumen
    menjadi pengaturan customer. Tidak boleh ada identitas Namua, MPP, Pemkab,
    domain, atau alamat tertentu yang menjadi hardcode runtime.

### Bentuk Produk yang Dijual

Customer menerima installer atau deployment package resmi, dokumentasi,
lisensi, dan akses support sesuai paketnya. Repository Git, private key
lisensi, dan source code pengembangan tetap milik produk Finance. Bila suatu
customer membutuhkan source escrow, hal itu hanya tersedia sebagai kontrak
Enterprise khusus dan bukan bagian dari jual beli putus standar.

## 1. Tujuan Produk

Finance akan dijual sebagai aplikasi operasional dan kasir yang lengkap, dengan
POS web dan APK POS sebagai satu ekosistem. Customer membeli hak pakai aplikasi
untuk organisasinya, bukan kepemilikan repository atau hak menjual ulang.

Target awal yang disarankan adalah **lisensi perpetual per instalasi**:

- Customer tetap dapat memakai versi yang telah dibeli tanpa batas waktu.
- Lisensi menentukan jumlah outlet, terminal POS, dan fitur yang boleh dipakai.
- Update fitur dan dukungan dapat diberikan selama masa maintenance tertentu,
  misalnya 12 bulan, lalu diperpanjang secara terpisah.
- Data customer tetap milik customer dan selalu dapat diekspor. Lisensi tidak
  boleh menjadi alat untuk menyandera data operasional.

## 2. Batas Penting: Source Code Tidak Bisa Dikunci Penuh

Jika customer menerima source code PHP, akses Git, atau salinan server yang
lengkap, tidak ada mekanisme teknis yang dapat menjamin source code tidak akan
dipakai ulang atau diperjualbelikan. Obfuscation, Docker, dan penguncian MAC
hanya membuat penyalinan lebih sulit, bukan mustahil.

Karena itu model yang sehat adalah:

1. Customer menerima **aplikasi yang sudah dipaketkan untuk dipasang**, bukan
   repository Git dan bukan kredensial pengembangan.
2. Hak penggunaan, larangan menyalin, larangan memindahtangankan, dan batas
   support ditulis jelas dalam perjanjian lisensi. Dokumen ini perlu ditinjau
   konsultan hukum sebelum dipakai komersial.
3. Lisensi digital, aktivasi perangkat, tanda tangan update, dan audit
   instalasi menjadi penguat teknis dari perjanjian tersebut.
4. Bila suatu saat ada penjualan source code atau source escrow, itu menjadi
   paket Enterprise terpisah dengan harga dan kontrak yang jauh berbeda.

Rekomendasi: jangan mulai dari obfuscation. Mulai dari release yang rapi,
paket instalasi, lisensi bertanda tangan, dan proses support yang jelas. PHP
encoder dapat dipertimbangkan setelah build dan updater stabil, tetapi tidak
boleh menjadi satu-satunya perlindungan.

## 3. Model Lisensi yang Direkomendasikan

### Lisensi utama

Satu lisensi mewakili satu organisasi dan satu instalasi Finance. Isi lisensi
minimal:

- `license_id` dan kode aktivasi.
- identitas customer dan nama usaha.
- edisi produk dan daftar fitur yang dibeli.
- batas outlet, terminal POS, dan akun aktif bila diperlukan.
- masa maintenance atau hak menerima update.
- tanggal terbit, status, serta tanda tangan digital.

Lisensi ditandatangani oleh server penerbit milik kita. Aplikasi Finance hanya
menyimpan public key untuk memverifikasi tanda tangan. Dengan pola ini aplikasi
tetap dapat memeriksa keaslian lisensi saat offline tanpa menyimpan rahasia
penerbit di server customer.

### Terminal adalah device, bukan akun

Jumlah slot mengikuti paket atau add-on. Starter memiliki satu slot terminal,
sedangkan Operations direkomendasikan memiliki tiga. Definisinya:

- maksimal sejumlah **terminal POS aktif** yang tertulis pada lisensi;
- satu terminal dapat memiliki beberapa akun kasir sesuai RBAC;
- akun web admin tidak otomatis dihitung sebagai terminal POS;
- customer dapat menonaktifkan device lama dan mengaktifkan device pengganti
  melalui halaman berjejak, dengan batas dan persetujuan yang jelas.

Jangan memakai MAC address sebagai satu-satunya identitas device. MAC dapat
berubah, mudah dipalsukan, dan tidak cocok untuk browser. Untuk POS web atau
APK, gunakan pasangan kunci perangkat saat aktivasi. APK dapat menyimpan kunci
di Android Keystore. POS web dapat memakai device certificate yang dipasangkan
melalui Local Agent atau aplikasi terminal, bukan hanya cookie browser.

### Perlakuan saat lisensi bermasalah

- Lisensi perpetual yang sah tetap membuka fitur yang telah dibeli pada versi
  yang sudah terpasang.
- Masa maintenance berakhir hanya menghentikan akses update dan support baru,
  bukan menutup transaksi atau data lama.
- Koneksi ke server lisensi putus tidak boleh langsung mematikan kasir.
  Gunakan grace period offline yang wajar dan status peringatan di admin.
- Jika terjadi indikasi lisensi tidak sah, sistem masuk mode pembatasan yang
  aman dan memberi waktu perbaikan. Laporan dan ekspor data tetap tersedia.

## 4. Level Fitur dan Paket Produk

RBAC dan lisensi mempunyai tugas berbeda:

- **RBAC** menjawab: siapa yang boleh memakai fitur.
- **Entitlement lisensi** menjawab: fitur apa yang dibeli instalasi tersebut.

Menyembunyikan menu saja tidak cukup. Endpoint, controller, job CLI, dan APK
harus melalui satu `FeatureGate` di server. Jika fitur tidak dibeli, endpoint
ditolak dengan pesan yang jelas; bukan hanya tombolnya yang hilang.

Paket komersial awal yang dipakai sebagai standar:

| Paket | Cakupan kandidat |
| --- | --- |
| Starter POS | Kasir, katalog, produk, pembayaran, printer dasar, laporan penjualan dasar, user/role dasar, 1 outlet, dan 1 terminal POS. |
| Operations | Semua Starter ditambah gudang, purchase order, store request, adjustment, stok harian, member, promo, voucher, self order, reservasi, 1 outlet, dan sampai 3 terminal POS. |
| Control | Semua Operations ditambah recipe, produksi component, HPP, defisit stok, period lock, stock health, audit penjualan/HPP, laporan keuangan lanjutan, attendance, dan payroll. |
| Enterprise | Semua Control ditambah APK POS, integrasi API, online order, automasi khusus, SSO, custom report, serta source escrow hanya bila disepakati secara terpisah. |

### Hipotesis harga awal untuk pilot

Harga berikut adalah pegangan pengujian pasar, bukan harga permanen di dalam
source code. Nilainya wajib dievaluasi kembali setelah dua atau tiga customer
pilot dan setelah biaya instalasi/support nyata diketahui.

| Paket | Harga pengenalan kandidat | Arah harga normal kandidat |
| --- | ---: | ---: |
| Starter POS | Mulai Rp1.000.000 | Rp1.500.000 |
| Operations | Rp3.500.000 | Rp4.500.000 |
| Control | Rp7.500.000 | Rp9.500.000 |
| Enterprise | Mulai Rp15.000.000 | Berdasarkan kebutuhan dan kontrak |

Biaya implementasi, migrasi data, perangkat, perjalanan, integrasi khusus,
terminal tambahan, outlet tambahan, dan support di luar cakupan tidak otomatis
masuk harga lisensi. Semua harus terlihat terpisah pada penawaran agar margin
support tidak hilang.

### Keputusan pemisahan fitur: jangan memisahkan source code

Kita tidak membuat folder, branch, atau build manual yang berbeda untuk setiap
paket. Pola itu akan menyebabkan perbaikan bug harus disalin berkali-kali,
migration mudah berbeda, dan customer lama berisiko tertinggal.

Pola implementasi yang disepakati:

1. Semua customer menerima artefak release yang sama sesuai versi produknya.
2. License Hub menerbitkan daftar entitlement dan limit yang ditandatangani.
3. Finance menyimpan cache lisensi yang terverifikasi untuk operasi offline.
4. Satu `FeatureGate` memeriksa lisensi pada menu, route/controller, API/APK,
   export, laporan, cron, worker, dan proses latar belakang.
5. RBAC diperiksa setelah FeatureGate: lisensi menjawab apakah perusahaan
   membeli modul, RBAC menjawab pegawai mana yang boleh menggunakannya.
6. Pengaturan lokal hanya menampilkan status paket dan menerima activation
   file bertanda tangan. Superadmin customer tidak dapat mencentang sendiri
   fitur berbayar yang belum dibeli.
7. Feature flag teknis untuk pilot/rollback dipisahkan dari entitlement
   komersial dan tidak tersedia sebagai bypass pada UI customer.

Jika paket diturunkan atau add-on berakhir, data modul tidak dihapus. Modul
masuk keadaan `READ_ONLY` bila diperlukan agar riwayat dapat dibaca/diekspor,
tetapi transaksi baru ditolak. Saat paket dinaikkan, modul aktif kembali tanpa
mengganti source code atau memindahkan database.

Setiap fitur juga mempunyai dependency eksplisit. Contoh: produksi resep tidak
boleh aktif tanpa inventory dan recipe; payroll yang memakai kehadiran tidak
boleh aktif tanpa HR dan attendance. License Hub menolak kombinasi paket yang
tidak valid sebelum lisensi diterbitkan.

Fitur yang cocok menjadi add-on terpisah:

- APK POS dan jumlah terminal tambahan.
- Multi outlet tambahan.
- Online order atau integrasi marketplace.
- Attendance, payroll, dan HR.
- Asset management.
- Integrasi akuntansi, WhatsApp, pembayaran, atau perangkat khusus.

Harga tidak disimpan di source code; harga adalah kebijakan komersial yang dapat
berubah. Yang dibuat stabil adalah kode fitur, misalnya `pos.cashier`,
`inventory.production`, `hr.payroll`, dan `mobile.pos`, sehingga isi paket dapat
diatur dari portal lisensi tanpa memecah aplikasi customer.

### Aturan add-on yang final

| Add-on | Satuan lisensi |
| --- | --- |
| Terminal POS tambahan | Per terminal aktif. |
| APK POS | Per device Android aktif; dihitung sebagai terminal POS. |
| Outlet tambahan | Per outlet aktif. |
| Online order / self order eksternal | Per organisasi atau per outlet sesuai integrasi. |
| Attendance dan payroll | Per organisasi. |
| Integrasi API / perangkat khusus | Per integrasi. |
| Source escrow | Kontrak Enterprise satuan proyek, bukan fitur biasa. |

## 5. Arsitektur Teknis Lisensi

Tahap awal tidak perlu mengubah seluruh tabel bisnis menjadi multi-tenant.
Setiap customer tetap memakai database dan instalasi sendiri. Multi-tenant
cloud adalah proyek berbeda yang baru layak dikerjakan saat memang dibutuhkan.

Fondasi database yang disarankan pada instalasi customer:

| Tabel konsep | Fungsi |
| --- | --- |
| `lic_installation` | Identitas unik instalasi, public key perangkat/server, versi aplikasi, dan status aktivasi. |
| `lic_license_cache` | Salinan payload lisensi dan signature terakhir yang terverifikasi, edisi, batas pemakaian, masa maintenance, grace period, dan status lokal. |
| `lic_feature` | Daftar kode fitur resmi aplikasi. |
| `lic_feature_cache` | Fitur, mode akses, dependency, dan limit yang aktif pada lisensi customer. |
| `lic_device_activation` | Terminal POS terdaftar, public key device, outlet, status, waktu aktivasi, dan jejak penggantian. |
| `lic_activation_audit` | Jejak aktivasi, deaktifasi, reset, serta alasan. |
| `lic_heartbeat_queue` | Antrean metadata kesehatan lisensi minimum saat koneksi License Hub sedang putus. |
| `lic_runtime_audit` | Jejak hasil verifikasi, penolakan feature gate, perubahan mode, dan kejadian grace period. |
| `lic_update_history` | Riwayat pemeriksaan, download, backup, apply, dan rollback release. |

Server lisensi pusat hanya perlu menyimpan data minimum: customer, lisensi,
status aktivasi, daftar device, versi aplikasi, dan metadata update. Jangan
mengirim transaksi, nama customer kasir, atau data keuangan customer ke server
lisensi kecuali mereka memberi persetujuan eksplisit untuk support bundle.

### Runtime lisensi yang dilindungi

Komponen verifikasi lisensi boleh dipaketkan dengan PHP encoder setelah alur
release stabil. Ia memverifikasi signature, instalasi, device, entitlement,
masa maintenance, dan grace period. Bagian penting aplikasi memanggil kontrak
runtime yang sama sehingga menghapus satu file membuat health check gagal.

Namun dependensi tidak boleh dibuat sebagai satu titik kegagalan yang langsung
mematikan kasir. Runtime harus mempunyai cache offline sah, pesan diagnostik,
grace period, dan mode pemulihan. Encoder adalah hambatan teknis untuk pengguna
awam, bukan jaminan bahwa programmer tidak dapat menulis ulang sistem. Kontrak,
distribusi release, private repository, signature, dan License Hub tetap
menjadi lapisan perlindungan utama.

## 6. Halaman yang Perlu Ada

### Di aplikasi Finance customer

1. **System > Lisensi & Aktivasi**
   - status lisensi, edisi, fitur aktif, batas outlet dan terminal;
   - masa update/support;
   - aktivasi offline bila instalasi tidak punya internet;
   - peringatan yang jelas, tanpa menghambat operasi mendadak.

2. **System > Device POS**
   - daftar terminal terdaftar dan outletnya;
   - pairing device baru, nonaktifkan device lama, dan alasan penggantian;
   - akses dibatasi Superadmin/owner customer.

3. **System > Pembaruan Aplikasi**
   - versi terpasang, release tersedia, changelog, dampak migration;
   - preflight, backup, jadwal maintenance, apply, dan hasil health check;
   - hanya akun berwenang yang dapat menerapkan update.

4. **System > Paket Dukungan**
   - membuat support bundle yang telah disamarkan;
   - memuat versi, schema, health check, error code, dan konfigurasi teknis;
   - tidak mengirim data bisnis penuh tanpa persetujuan admin.

### Di portal pemilik produk

1. Pengelolaan customer, lisensi, paket, add-on, dan masa maintenance.
2. Penerbitan aktivasi, reset device, revoke darurat, dan riwayat audit.
3. Penerbitan manifest release serta paket update yang ditandatangani.
4. Dashboard versi customer agar support mengetahui siapa yang tertinggal
   migration atau memiliki versi API APK yang tidak kompatibel.

## 7. Pola Update yang Aman

Update tidak boleh berupa instruksi `git pull` ke server customer. Setiap rilis
harus berupa paket yang jelas dan bertanda tangan.

Alur update yang disarankan:

1. Kita membuat build rilis dengan nomor versi, catatan perubahan, checksum,
   signature, daftar migration SQL, dan kebutuhan minimum PHP/MySQL.
2. Aplikasi customer memeriksa manifest update dan menampilkan dampaknya.
3. Admin memilih jadwal maintenance. Sistem melakukan preflight schema,
   ruang disk, permission folder, worker/cron, dan versi APK yang kompatibel.
4. Sistem membuat backup database dan file konfigurasi yang dapat diverifikasi.
5. Aplikasi masuk maintenance mode, menerapkan paket kode dan migration secara
   berurutan, lalu menjalankan health check otomatis.
6. Bila health check gagal, rollback dilakukan dari backup yang sama. Rollback
   database tidak boleh sekadar mengganti kode lama.
7. Riwayat update, hasil, dan error disimpan di `lic_update_history`.

Untuk APK POS, manifest juga membawa `minimum_backend_version` dan
`minimum_apk_version`. Backend tidak boleh menerima APK yang skemanya sudah
tidak kompatibel tanpa pesan pembaruan yang jelas.

## 8. Keamanan dan Operasional Minimum Sebelum Dijual

- Simpan password database, API key, dan private key di environment secret,
  bukan di Git dan bukan di file yang ikut paket customer.
- Gunakan HTTPS untuk aktivasi dan update.
- Tanda tangani lisensi dan paket update dengan asymmetric signature. Jangan
  memakai checksum biasa sebagai satu-satunya validasi.
- Pisahkan environment development, staging, dan production.
- Siapkan build release yang dapat diulang. Server customer tidak boleh menjadi
  tempat coding langsung.
- Setiap release wajib mempunyai migration manifest, backup plan, rollback
  plan, changelog, dan smoke test yang terdokumentasi.
- Audit log lisensi dan device tidak boleh bisa dihapus melalui UI biasa.
- Buat kebijakan reset device agar customer yang ganti HP, PC, atau printer
  tidak terkunci dari kasir saat jam operasional.

## 9. Productization dan Penghapusan Hardcode Identitas

Finance belum boleh dijual sebagai template customer umum selama identitas
Namua, MPP, Pemkab, alamat, domain, warna, atau path agent tertentu masih
menjadi keputusan hardcode runtime. Satu perubahan identitas harus berlaku
konsisten pada login, sidebar, browser title, favicon, laporan, dokumen, struk,
QR ulasan, member/self order, landing page, email/WhatsApp, dan APK.

### Tiga kelompok konfigurasi yang tidak boleh dicampur

| Kelompok | Contoh | Siapa yang boleh mengubah |
| --- | --- | --- |
| Profil usaha dan branding | Nama usaha, nama aplikasi yang tampil, logo, favicon, warna, alamat, kontak, tagline, footer, tautan publik. | Owner/Superadmin customer melalui UI dan audit trail. |
| Deployment dan secret | Database, encryption key, URL internal, storage, mail/WhatsApp credential, cron/worker, backup path, printer agent host/port. | Installer atau operator server melalui environment/config deployment, bukan UI umum. |
| Paket dan entitlement | Modul aktif, batas outlet, terminal, APK, maintenance, mode read-only. | License Hub melalui payload bertanda tangan; UI customer hanya membaca status. |

Dengan pemisahan ini, mengganti logo tidak membutuhkan developer, tetapi
customer juga tidak dapat mengaktifkan payroll atau multi-outlet hanya dengan
mengubah baris database pengaturan biasa.

### Halaman pengaturan yang perlu dibangun

Tambahkan halaman **System > Profil Usaha & Tampilan** dengan bagian berikut:

- Identitas usaha: nama legal, nama dagang, nama singkat, NPWP bila dipakai,
  alamat, telepon, email, website, zona waktu, bahasa, dan mata uang.
- Logo dan ikon: logo terang/gelap, favicon, gambar login, serta aset dokumen.
- Warna dan tampilan: warna utama, warna aksen, latar, sidebar, serta pilihan
  kontras yang tetap menjaga keterbacaan.
- Tautan publik: member, self order, reservasi, ulasan, landing page, bantuan,
  kebijakan privasi, dan WhatsApp.
- Dokumen dan cetak: identitas default nota/laporan, header/footer, tanda tangan,
  serta fallback yang kemudian dapat dioverride per outlet atau layout printer.
- Preview: login, sidebar, struk 58/80 mm, QR ulasan, laporan, dan halaman publik
  sebelum perubahan diterapkan.
- Riwayat perubahan dan tombol kembali ke tema netral bawaan produk.

Konsep tabel yang perlu divalidasi terhadap schema yang sudah ada:

| Tabel konsep | Fungsi |
| --- | --- |
| `sys_business_profile` | Identitas legal dan operasional organisasi. |
| `sys_branding_setting` | Nama aplikasi yang tampil, tema, footer, dan pilihan white-label. |
| `sys_brand_asset` | Logo, favicon, gambar login, versi file, serta status aktif. |
| `sys_external_url` | URL publik member, self order, reservasi, review, support, dan privasi. |
| `sys_branding_audit` | Siapa mengubah identitas, nilai sebelum/sesudah, dan waktu perubahan. |
| `pos_outlet` | Tetap menjadi sumber override nama, alamat, kontak, dan identitas per outlet. |

Jangan membuat data ganda bila struktur yang setara sudah ada. Sebagai contoh,
`pos_outlet` dan `pos_print_general_setting` sudah menyediakan sebagian data
outlet/struk. Keduanya perlu dihubungkan ke profil usaha sebagai fallback,
bukan diganti secara serampangan.

Urutan sumber identitas harus selalu jelas:

1. override outlet atau dokumen tertentu;
2. profil usaha customer;
3. default produk yang netral.

Default terakhir tidak boleh berisi nama Namua, alamat Rembang, domain customer,
atau identitas proyek lama. Nama vendor seperti `Finance POS` dapat tetap
ditampilkan sebagai `Powered by Finance POS`. Opsi white-label penuh dapat
menjadi entitlement Enterprise, bukan hardcode per customer.

### Temuan hardcode yang sudah terpetakan

| Area | Kondisi yang ditemukan | Target perbaikan |
| --- | --- | --- |
| Login dan autentikasi | `Finance App`, `NAMUA COFFEE & EATERY`, logo statis, dan title masih tersebar. | Baca nama aplikasi, nama usaha, logo, favicon, dan gambar login dari branding service. |
| Header, sidebar, dan footer | Masih ada fallback `MPP`, `Pemkab Rembang`, serta tautan DPMPTSP. | Gunakan profil usaha dan footer produk netral. Hapus template/demo yang tidak dipakai setelah dependency scan. |
| Tema visual | Warna merah/krem utama masih tetap di CSS. | Hasilkan CSS variables dari setting yang tervalidasi, dengan tema netral sebagai fallback. |
| Struk dan preview printer | Fallback nama/alamat Namua dan logo file lokal masih ada walaupun setting printer sudah tersedia. | Printer memakai outlet -> profil usaha -> default netral; layout hanya menentukan data mana yang ditampilkan. |
| Landing, member, self order, reservasi, dan ulasan | Nama Namua, domain, SEO, nomor kontak, dan URL publik masih tersebar. | Semua URL dan identitas memakai `sys_external_url` dan profil usaha. |
| Printer agent | Nama folder, hostname, base URL, dan dokumentasi masih berorientasi Namua. | Buat agent produk generik, installer, config hasil pairing, serta panduan Windows/Linux yang netral. |
| Dokumen HR, roastery, label, email/WhatsApp | Sebagian template membawa identitas Namua langsung. | Pisahkan isi bisnis customer dari template produk; sediakan preset yang dapat diedit. |
| Konfigurasi server | Beberapa secret dan mode keamanan masih berada di file konfigurasi proyek. | Pindahkan secret ke environment, sediakan installer/preflight, dan jangan tampilkan nilainya di UI. |

### Kesiapan jual di luar branding

Productization tidak cukup dengan mengganti logo. Sebelum pilot berbayar,
Finance juga harus memenuhi hal berikut:

- Temuan kritis audit transaksi, stok, HPP, void/refund, PH, payroll, RBAC, dan
  migration sudah ditutup atau dinyatakan sebagai batas produk secara jujur.
- RBAC seed, role matrix, menu, page code, endpoint, export, cron, dan worker
  mempunyai pemeriksaan konsistensi otomatis.
- Secret database/API/encryption tidak ikut Git atau paket customer; CSRF,
  secure cookie, error page production, dan environment production aktif.
- Installer meminta profil usaha, outlet pertama, akun owner, zona waktu,
  mata uang, storage, backup, printer, dan worker tanpa mengedit source code.
- Tersedia data demo opsional yang terpisah dari data customer dan dapat
  dihapus bersih.
- Backup, restore, migration, rollback, health check, dan smoke test dapat
  dijalankan berulang pada Windows dan Linux.
- Format tanggal, angka, mata uang, timezone, pajak/service, penomoran dokumen,
  serta aturan lokal tidak bergantung pada satu usaha.
- Semua error teknis memiliki correlation ID dan pesan user yang aman; stack
  trace/SQL tidak tampil kepada operator production.
- Dokumentasi onboarding, SOP support, batas paket, privasi telemetry, EULA,
  dan kepemilikan data tersedia sebelum penjualan pertama.

### Urutan implementasi productization yang disepakati

1. Buat katalog fitur dan dependency dalam dokumen/machine-readable registry;
   belum langsung menutup menu agar aplikasi aktif tidak terganggu.
2. Bangun profil usaha dan branding service terpusat beserta tema netral.
3. Ganti hardcode runtime per area dan tambahkan regression test untuk login,
   sidebar, struk, laporan, QR, halaman publik, serta printer agent.
4. Pasang `FeatureGate` dalam mode audit untuk merekam semua jalur penggunaan
   fitur sebelum enforcement dinyalakan.
5. Bangun License Hub MVP, signed entitlement, cache offline, dan activation
   file; setelah hasil audit bersih baru aktifkan enforcement paket.
6. Stabilkan installer/updater dan baru kemudian lindungi runtime inti dengan
   encoder pada artefak production.
7. Uji Starter, Operations, Control, upgrade, downgrade read-only, internet
   putus, ganti terminal, restore backup, dan rollback update pada pilot.

## 10. Roadmap Implementasi

### Fase 0 - Keputusan Produk dan Kontrak

Hasil yang harus ada:

- definisi paket, add-on, batas outlet, batas terminal, dan masa maintenance;
- kebijakan penggantian device dan kondisi offline;
- perjanjian lisensi, EULA, SOP support, dan kebijakan data;
- keputusan apakah customer mendapat server managed, on-premise package, atau
  pilihan keduanya.

### Fase 1 - Siapkan Release Engineering

Hasil yang harus ada:

- nomor versi aplikasi resmi;
- release manifest untuk seluruh SQL migration;
- environment config dan secret yang tidak masuk Git;
- build package Windows/Linux atau container yang terdokumentasi;
- checklist upgrade, rollback, backup, dan smoke test POS.

Ini adalah prioritas sebelum membuat pengunci lisensi. Tanpa proses rilis yang
rapi, modul updater justru dapat merusak data customer.

### Fase 2 - Lisensi Dasar Per Instalasi

Hasil yang harus ada:

- identitas instalasi dan lisensi bertanda tangan;
- halaman status lisensi dan mode offline;
- batas outlet/terminal dapat diperiksa dari server;
- portal internal sederhana untuk menerbitkan lisensi dan activation file;
- audit aktivasi tanpa mengirim data bisnis customer.

### Fase 3 - Aktivasi Terminal POS dan APK

Hasil yang harus ada:

- pairing terminal POS dengan batas lisensi;
- reset device yang aman dan berjejak;
- APK memakai identitas perangkat yang sama dengan lisensi instalasi;
- dukungan aktivasi offline untuk lokasi dengan internet terbatas;
- pengujian penggantian perangkat, kehilangan perangkat, dan koneksi putus.

### Fase 4 - Feature Gate dan Paket Produk

Hasil yang harus ada:

- katalog kode fitur yang stabil;
- guard di server untuk menu, route, controller, API, cron, dan APK;
- paket lisensi mengaktifkan fitur tanpa mengubah source code customer;
- halaman admin yang menjelaskan fitur mana yang aktif atau belum dibeli;
- regression test untuk memastikan fitur yang dibatasi tidak dapat dipanggil
  langsung melalui URL/API.

### Fase 5 - Update Center

Hasil yang harus ada:

- manifest rilis bertanda tangan;
- preflight, backup otomatis, maintenance mode, migration runner, dan health
  check;
- log update serta SOP rollback;
- kompatibilitas versi backend dan APK;
- channel rilis `stable`, `pilot`, dan `critical fix`.

### Fase 6 - Operasi Produk dan Scale Up

Hasil yang harus ada:

- portal customer/licensing yang lebih matang;
- support bundle aman;
- telemetry kesehatan opsional dan consent-based;
- dokumentasi installer, onboarding, dan knowledge base;
- evaluasi apakah perlu SaaS multi-tenant atau tetap on-premise.

## 11. Urutan Pengerjaan yang Harus Kita Jalankan

Urutan ini sengaja dimulai dari fondasi rilis. Lisensi yang bagus tidak akan
membantu jika pemasangan, migration, dan pemulihan update belum aman.

### Langkah 1 - Kunci produk yang dijual

Sebelum coding lisensi, buat satu dokumen komersial yang berisi harga, paket,
add-on, jumlah outlet, slot terminal tiap paket, durasi maintenance, dan SOP
ganti device. Dokumen ini juga menjadi bahan kontrak/EULA. Hasilnya: tim sales,
support, dan developer memakai definisi produk yang sama.

### Langkah 2 - Rapikan release engineering Finance

Buat nomor versi resmi, changelog, migration manifest, backup database,
rollback plan, dan smoke test. Buat release package dari branch/tag yang bersih
di CI atau mesin build khusus. Server customer tidak lagi diupdate lewat Git.
Hasilnya: satu release bisa dipasang ulang dan dipulihkan dengan cara yang sama
di semua customer.

### Langkah 3 - Bangun Finance License Hub

Buat aplikasi/layanan internal terpisah untuk mengelola customer, paket,
lisensi, activation code, terminal, masa maintenance, dan release. License Hub
tidak menyimpan transaksi atau data keuangan customer. Ia hanya menjadi lemari
kunci yang menerbitkan lisensi bertanda tangan.

Tabel minimum di License Hub:

| Tabel | Isi utama |
| --- | --- |
| `lic_customer` | Identitas organisasi customer dan kontak pemilik. |
| `lic_plan` | Starter POS, Operations, Control, Enterprise. |
| `lic_feature` dan `lic_plan_feature` | Katalog fitur serta fitur milik setiap paket. |
| `lic_license` | Nomor lisensi, status, batas outlet/terminal, dan masa maintenance. |
| `lic_license_entitlement` | Add-on dan limit yang spesifik untuk satu customer. |
| `lic_device_activation` | Device/terminal aktif, outlet, public key, dan statusnya. |
| `lic_activation_audit` | Jejak aktivasi, reset, penggantian, dan alasan. |
| `lic_release` | Manifest release, signature, versi minimum, dan channel rilis. |

### Langkah 4 - Integrasikan lisensi ke Finance tanpa mengganggu operasi

Tambahkan halaman `System > Lisensi & Aktivasi`, cache lisensi bertanda tangan,
dan pemeriksaan status yang tidak langsung mematikan kasir. Tambahkan satu
`FeatureGate` di backend; menu, URL, controller, API, cron, dan endpoint APK
memakai gate yang sama. Saat belum ada lisensi pada masa migrasi, gunakan mode
internal/development yang dicatat jelas dan hanya dapat dipakai oleh instalasi
milik kita.

### Langkah 5 - Aktifkan batas terminal dan APK POS

Saat POS web terminal atau APK pertama kali dipasang, device melakukan pairing
dengan kode aktivasi. Setiap device menyimpan pasangan kunci, bukan hanya MAC
address atau cookie. Owner dapat menonaktifkan device lama lalu memasangkan
pengganti; semua tindakan masuk audit. APK POS harus memakai entitlement yang
sama dan menghitung satu slot terminal.

### Langkah 6 - Buat Update Center secara bertahap

Versi pertama cukup berupa update terbantu: admin melihat release, sistem
melakukan preflight dan backup, lalu admin menerapkan paket yang sudah
ditandatangani. Setelah proses itu stabil, baru aktifkan download dan update
otomatis terjadwal. Setiap update wajib membuat backup, menjalankan migration,
health check, dan menyediakan rollback yang diuji.

### Langkah 7 - Paketkan dan lindungi release production

Pisahkan repository private dari artefak pelanggan. Rilis production berisi
kode yang diperlukan aplikasi, konfigurasi template, installer, dan checksum;
tidak berisi Git, secret, private key, atau alat pengembangan. Evaluasi PHP
encoder setelah installer/updater stabil. Encoder memperkuat hambatan menyalin,
tetapi kontrak lisensi dan proses distribusi tetap menjadi perlindungan utama.

### Langkah 8 - Pilot sebelum dijual luas

Pilih satu sampai dua instalasi pilot. Uji pembelian lisensi Starter satu
terminal dan Operations tiga terminal, ganti HP/PC, internet putus,
maintenance habis, feature gate, update, rollback, dan ekspor data. Hanya
setelah seluruh skenario ini lolos, paket dijual ke customer berikutnya.

## 12. Kriteria Siap Jual Versi Pertama

Produk dianggap siap dijual ketika seluruh poin berikut telah terbukti pada
instalasi pilot:

- customer bisa memasang aplikasi tanpa akses Git atau source code;
- lisensi membatasi outlet dan terminal sesuai paket/kontrak, termasuk APK;
- owner dapat melihat dan mengganti device dengan audit trail;
- RBAC dan FeatureGate sama-sama menolak akses yang tidak berhak;
- POS tetap beroperasi saat koneksi License Hub terputus dalam grace period;
- masa maintenance habis tidak mengunci transaksi maupun data;
- update memiliki preflight, backup, migration, health check, dan rollback;
- support dapat membaca status versi/aktivasi tanpa menerima data transaksi
  customer secara default;
- kontrak lisensi, SOP instalasi, SOP support, dan kebijakan privasi siap
  dipakai.

## 13. Hal yang Sebaiknya Tidak Dilakukan

- Jangan menjanjikan source code "tidak mungkin disalin".
- Jangan menjadikan MAC address, IP, atau cookie browser sebagai satu-satunya
  identitas terminal.
- Jangan mematikan kasir segera ketika internet putus atau maintenance habis.
- Jangan membatasi fitur hanya dari tampilan menu.
- Jangan melakukan update otomatis tanpa backup, health check, dan audit.
- Jangan memasukkan seluruh customer ke arsitektur multi-tenant sebelum model
  bisnis cloud benar-benar dibutuhkan.
- Jangan mengirim data transaksi customer ke server lisensi demi pemeriksaan
  aktivasi biasa.

## 14. Keputusan yang Sudah Ditetapkan Sebelum Mulai Coding

1. Penjualan awal adalah on-premise per organisasi; managed server dapat
   ditawarkan sebagai layanan instalasi/support, bukan perubahan model produk.
2. Lisensi dihitung per organisasi, outlet, terminal POS, dan add-on.
3. Starter mencakup 1 outlet dan 1 terminal aktif; Operations menjadi paket
   awal dengan sampai 3 terminal. Semua memakai grace period offline 30 hari
   serta jalur penggantian device berjejak.
4. Paket awal adalah Starter POS, Operations, Control, dan Enterprise.
5. Maintenance standar 12 bulan; aplikasi perpetual tetap berjalan setelahnya,
   sedangkan update/support baru memerlukan perpanjangan.
6. Kontrak lisensi, EULA, dan SOP support harus ditinjau pihak hukum sebelum
   penjualan pertama.
7. Pengerjaan dimulai dari Langkah 1 dan 2, lalu lisensi dasar. Obfuscation dan
   APK locking tidak boleh mendahului release engineering.

## 15. Ringkasan Arah Produk

Untuk versi pertama yang akan dijual, arah finalnya adalah:

- jual **perpetual on-premise per organisasi**; Starter membawa 1 outlet dan
  1 terminal POS, sedangkan Operations menjadi pilihan awal untuk 3 terminal;
- customer memperoleh versi saat pembelian dan 12 bulan update/support;
- update berikutnya setelah masa itu melalui renewal maintenance, tanpa
  mematikan aplikasi yang sudah dibeli;
- source repository tidak diserahkan;
- gunakan lisensi offline-signed ditambah aktivasi terminal online/offline;
- jadikan APK POS sebagai add-on per terminal;
- pertahankan satu codebase dan aktifkan paket melalui entitlement yang
  ditandatangani, bukan melalui source code berbeda atau toggle lokal;
- selesaikan profil usaha/branding terpusat dan penghapusan hardcode identitas
  sebelum instalasi pilot customer;
- mulai feature tier dari paket yang tidak terlalu banyak agar support tetap
  sederhana, lalu perluas setelah data penggunaan nyata terkumpul.

Dengan urutan ini, Finance dapat dijual secara profesional tanpa mengganggu
fokus utama saat ini: kestabilan transaksi, stok, HPP, audit, dan pengalaman
operator.
