# Roadmap Komersialisasi Finance POS

**Status:** Rancangan awal untuk pegangan produk dan teknis. Belum mengubah
kode, database, lisensi, atau perilaku aplikasi yang sedang dipakai.

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

### Tiga device bukan tiga akun

Untuk contoh lisensi tiga device, definisinya sebaiknya:

- maksimal tiga **terminal POS aktif** yang terdaftar pada satu instalasi;
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

Contoh paket awal yang dapat kita diskusikan:

| Paket | Cakupan kandidat |
| --- | --- |
| Essential POS | Kasir, katalog, produk, pembayaran, printer dasar, laporan penjualan dasar, user dan role dasar. |
| Operations | Semua Essential ditambah multi outlet, gudang, purchase order, store request, adjustment, stok harian, member, promo, voucher, self order, dan reservasi. |
| Control | Semua Operations ditambah recipe, produksi component, HPP, defisit stok, period lock, stock health, audit penjualan/HPP, dan laporan keuangan lanjutan. |
| Enterprise | Semua Control ditambah APK POS, integrasi API, online order, automasi khusus, SSO, custom report, dan source escrow bila disepakati. |

Fitur yang cocok menjadi add-on terpisah:

- APK POS dan jumlah terminal tambahan.
- Multi outlet tambahan.
- Online order atau integrasi marketplace.
- Attendance, payroll, dan HR.
- Asset management.
- Integrasi akuntansi, WhatsApp, pembayaran, atau perangkat khusus.

Nama paket, isi, dan harga belum perlu diputuskan sekarang. Yang penting
arsitektur memakai kode fitur stabil, misalnya `pos.cashier`,
`inventory.production`, `hr.payroll`, dan `mobile.pos`, sehingga isi paket
dapat diubah tanpa memecah kode aplikasi.

## 5. Arsitektur Teknis Lisensi

Tahap awal tidak perlu mengubah seluruh tabel bisnis menjadi multi-tenant.
Setiap customer tetap memakai database dan instalasi sendiri. Multi-tenant
cloud adalah proyek berbeda yang baru layak dikerjakan saat memang dibutuhkan.

Fondasi database yang disarankan pada instalasi customer:

| Tabel konsep | Fungsi |
| --- | --- |
| `lic_installation` | Identitas unik instalasi, public key perangkat/server, versi aplikasi, dan status aktivasi. |
| `lic_license` | Salinan lisensi terverifikasi, edisi, batas pemakaian, masa maintenance, dan status lokal. |
| `lic_feature` | Daftar kode fitur resmi aplikasi. |
| `lic_license_feature` | Fitur yang aktif pada lisensi customer. |
| `lic_device_activation` | Terminal POS terdaftar, public key device, outlet, status, waktu aktivasi, dan jejak penggantian. |
| `lic_activation_audit` | Jejak aktivasi, deaktifasi, reset, serta alasan. |
| `lic_update_history` | Riwayat pemeriksaan, download, backup, apply, dan rollback release. |

Server lisensi pusat hanya perlu menyimpan data minimum: customer, lisensi,
status aktivasi, daftar device, versi aplikasi, dan metadata update. Jangan
mengirim transaksi, nama customer kasir, atau data keuangan customer ke server
lisensi kecuali mereka memberi persetujuan eksplisit untuk support bundle.

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

## 9. Roadmap Implementasi

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

## 10. Hal yang Sebaiknya Tidak Dilakukan

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

## 11. Urutan Keputusan Sebelum Mulai Coding

1. Tentukan apakah penjualan awal fokus on-premise, managed server, atau
   keduanya.
2. Putuskan satuan lisensi: per organisasi, outlet, terminal POS, dan add-on.
3. Tentukan jumlah device default, prosedur penggantian, serta grace period
   offline.
4. Tentukan paket fitur awal berdasarkan nilai bisnis, bukan banyaknya menu.
5. Tetapkan masa maintenance dan jenis update yang tetap diberikan setelahnya,
   misalnya security fix versus fitur baru.
6. Siapkan kontrak lisensi dan SOP support bersama pihak hukum.
7. Baru implementasikan Fase 1, lalu Fase 2. Jangan melompat langsung ke
   obfuscation atau APK locking.

## 12. Rekomendasi Keputusan Awal

Untuk versi pertama yang akan dijual, rekomendasi saya adalah:

- jual **perpetual on-premise per organisasi** dengan 1 outlet dan 3 terminal
  POS sebagai paket awal;
- customer memperoleh versi saat pembelian dan 12 bulan update/support;
- update berikutnya setelah masa itu melalui renewal maintenance, tanpa
  mematikan aplikasi yang sudah dibeli;
- source repository tidak diserahkan;
- gunakan lisensi offline-signed ditambah aktivasi terminal online/offline;
- jadikan APK POS sebagai add-on per terminal;
- mulai feature tier dari paket yang tidak terlalu banyak agar support tetap
  sederhana, lalu perluas setelah data penggunaan nyata terkumpul.

Dengan urutan ini, Finance dapat dijual secara profesional tanpa mengganggu
fokus utama saat ini: kestabilan transaksi, stok, HPP, audit, dan pengalaman
operator.
