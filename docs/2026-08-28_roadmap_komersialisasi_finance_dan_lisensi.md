# Roadmap Komersialisasi Finance POS

**Status:** Keputusan produk dan urutan implementasi menuju siap jual.
Diperbarui 31 Agustus 2026 berdasarkan
`docs/2026-08-30_audit_total_aplikasi_finance_dan_roadmap_pengembangan.md`.
Dokumen ini belum mengubah kode, database, lisensi, atau perilaku aplikasi yang
sedang dipakai.

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
12. Implementasi dimulai dari remediasi keamanan, RBAC, integritas transaksi,
    pembersihan artefak rilis, dan automated test. License Hub tidak boleh
    dibangun untuk menutupi fondasi aplikasi yang belum lolos audit.
13. License Hub dikembangkan sebagai bagian dari aplikasi vendor terpisah
    bernama sementara **Product Control Center**. Control plane ini menangani
    banyak produk: katalog, repository privat, build, artefak, generator
    instalasi, paket, lisensi, update, monitoring, approval, dan audit.
14. Source tetap berada di Git privat dan artefak berada di object/artifact
    storage. Database Product Control Center hanya menyimpan metadata,
    referensi commit/tag immutable, manifest, checksum, lokasi artefak, dan
    audit; source tidak disimpan sebagai blob database biasa.
15. Database customer dibuat dari migration dan seed kanonis. Dump produksi,
    backup, log, upload, transaksi, pegawai, customer, stok, payroll, credential,
    dan konfigurasi device tidak pernah menjadi data awal paket.

### Prasyarat Sebelum Menjual Lisensi Pertama

Finance saat ini merupakan aplikasi internal yang kaya fitur, tetapi belum
boleh dipaketkan langsung untuk customer. Repository masih membawa backup,
upload, konfigurasi instalasi, dan identitas bisnis lama; beberapa endpoint
mutasi belum mempunyai guard per aksi; production security, Printer Agent,
migration, dependency, dan test juga belum memenuhi standar produk.

Karena itu roadmap ini mempunyai dua jalur yang berurutan:

1. **Product readiness:** membersihkan artefak, menutup bug dan akses, menguji
   transaksi, membuat pengaturan usaha, installer, updater, serta support SOP.
2. **Commercial control:** memetakan paket, menerapkan FeatureGate, membangun
   License Hub, aktivasi device, signed update, dan monitoring lisensi.

Jalur kedua baru boleh enforcement setelah jalur pertama lulus. License runtime
tidak boleh dibuat sebagai satu file yang bila rusak mematikan seluruh sistem;
kasir dan akses ekspor data harus tetap mempunyai kegagalan yang aman.

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

Peta awal modul dalam bahasa user:

| Yang dikerjakan customer | Modul yang diterima | Paket minimum kandidat |
| --- | --- | --- |
| Menata usaha dan pegawai aplikasi | Profil usaha, outlet, user, role, metode bayar, rekening dasar | Starter POS |
| Melayani penjualan di kasir | Katalog, produk, extra, bundle, draft, pembayaran, void dasar, printer kasir | Starter POS |
| Melihat hasil penjualan | Penjualan harian, transaksi, produk/extra, metode bayar, tutup kasir | Starter POS |
| Mengelola pelanggan dan promosi | Member, point, stamp, promo, voucher, pemakaian voucher, review | Operations |
| Menerima pesanan selain kasir | Self order, reservasi, online order dasar sesuai add-on | Operations |
| Membeli dan meminta barang | Purchase Order, penerimaan, Store Request, fulfillment | Operations |
| Menjaga stok operasional | Gudang, stok divisi, adjustment, lot, stok harian, recon dasar | Operations |
| Membuat bahan prepare/component | Resep, formula, batch produksi, trace pemakaian | Control |
| Mengendalikan biaya dan HPP | HPP produk, HPP penjualan, cost control, defisit, Stock Health | Control |
| Menutup dan mengaudit periode | Period lock, audit commit, audit penjualan/HPP, koreksi berjejak | Control |
| Mengelola uang dan laporan | Kas/bank, mutasi, hutang/piutang, laporan keuangan lanjutan | Control |
| Mengelola kehadiran dan gaji | Jadwal, presensi, PH, pengajuan, payroll | Control atau add-on HR |
| Mengelola aset | Pendataan, lock aset, laporan kejadian, perubahan berapproval | Control atau add-on Asset |
| Memakai perangkat/mobile tambahan | APK POS, API, pairing device, terminal tambahan | Add-on/Enterprise |
| Memakai automasi/integrasi khusus | WhatsApp, SSO, custom report, marketplace/perangkat | Add-on/Enterprise |

Peta ini masih kandidat komersial. Finalisasi dilakukan setelah dependency
fitur dan biaya support diuji; pemindahan satu modul antar paket cukup mengubah
entitlement, bukan membuat source code baru.

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

### Product Control Center multi-produk

Dashboard vendor tidak dibatasi sebagai `Finance License Hub`. Ia dibuat
sebagai control plane yang dapat melayani Finance, APK POS, dan aplikasi lain
di masa depan. Source code tetap dikelola oleh Git privat yang terintegrasi;
Product Control Center menyimpan metadata repository dan memerintahkan build
runner terisolasi untuk mengambil commit/tag yang telah disetujui.

| Modul pusat | Fungsi pengguna |
| --- | --- |
| Product Catalog | Mendaftarkan produk, versi, requirement, lifecycle, serta adapter build/install. |
| Source Integration | Menghubungkan repository privat, branch policy, commit/tag immutable, webhook, dan approval. |
| Build Orchestrator | Membuat clean checkout, menjalankan test/scan, dan menghasilkan build yang dapat diulang. |
| Artifact Registry | Menyimpan metadata paket, checksum, SBOM, signature, channel, serta lokasi artifact storage. |
| Installation Generator | Menggabungkan artefak generik dengan profil OS, migration, seed, onboarding, dan lisensi customer. |
| Package & Feature Catalog | Mengelola paket jual, add-on, dependency fitur, kapasitas, dan versi komersial. |
| License Hub | Mengelola customer, instalasi, entitlement, maintenance, aktivasi online/offline, device, revoke, dan transfer. |
| Release & Update Center | Mengelola pilot/stable/critical-fix, compatibility, rollout, rollback, dan status update. |
| Installation Monitor | Menampilkan versi, health minimal, penggunaan slot, heartbeat, dan hasil update berdasarkan persetujuan. |
| Signing & Secret Service | Menjaga private key dan secret build agar tidak masuk repository atau server customer. |
| Approval & Audit | Mencatat pembuat build, reviewer, penerbit lisensi, downloader, perubahan paket, dan operasi sensitif. |

Database pusat menyimpan konsep seperti `prd_product`, `prd_repository`,
`prd_build_definition`, `rel_release`, `rel_artifact`, `ins_profile`,
`ins_seed_manifest`, `pkg_feature`, `pkg_plan`, `lic_license`,
`lic_device_activation`, `ops_installation`, `ops_health_event`, dan
`audit_event`. Nama final ditetapkan saat desain schema; batas datanya sudah
ditetapkan sejak awal.

Repository privat, artifact storage, database control plane, build runner, dan
signing service harus dipisahkan secara akses. Build runner memakai credential
jangka pendek, tidak menerima database produksi customer, dan dibuang setelah
build. Private key signing tidak pernah dikirim ke runner umum atau aplikasi
customer.

Monitoring normal hanya memerlukan installation ID pseudonim, produk/versi,
status lisensi, jumlah slot terpakai, health service, dan hasil update. Data
transaksi, omzet, stok, resep, payroll, identitas pegawai/customer, dan dokumen
customer tidak dikirim secara default. Diagnostic support yang lebih rinci
harus dibuat terpisah, disamarkan, dibatasi waktu, dan disetujui admin customer.

### Generator instalasi dan database awal

Satu produk dan satu versi hanya menghasilkan satu artefak kode generik. Paket
Starter, Operations, Control, atau Enterprise tidak dibuat dengan menyalin dan
menghapus source secara manual. Generator memilih artefak yang sama, lalu
menambahkan install profile, seed profile, onboarding profile, dan signed
entitlement sesuai pesanan customer.

Klasifikasi database instalasi baru:

| Kelas | Contoh | Aturan |
| --- | --- | --- |
| Schema | Tabel, index, foreign key, view, registry migration | Selalu dari migration terurut dan ber-checksum. |
| Seed platform wajib | Page/menu kanonis, action permission, status sistem | Netral, idempotent, deklaratif, dan dapat mencabut default lama yang salah. |
| Seed referensi produk | Satuan, alasan transaksi, tipe dokumen, role/template default | Hanya dipasang bila dependency produk/fitur terpenuhi. |
| Entitlement | Feature code, batas outlet/terminal, maintenance | Berasal dari lisensi bertanda tangan, bukan toggle/seed lokal. |
| Onboarding | Profil usaha, owner, outlet, timezone, rekening, metode bayar | Diisi saat instalasi; secret dan password dibuat saat itu. |
| Demo opsional | Data fiktif untuk demonstrasi | Artefak terpisah dan dapat dihapus penuh. |
| Runtime | Queue, session, cache, log, upload, audit | Dibuat setelah instalasi; folder disiapkan kosong dengan permission yang tepat. |
| Terlarang | Dump, backup, log lama, upload, transaksi, member/customer, pegawai, presensi, payroll, stok/lot/HPP, mutasi rekening, token, secret, PID, dan config device | Scanner menggagalkan build bila data ini ditemukan. |

Setiap migration dan seed mempunyai ID stabil, versi produk, dependency,
checksum, mode `insert/update/reconcile`, serta perilaku clean install dan
upgrade. SQL audit/repair historis berada di jalur support terpisah dan tidak
ikut update umum. Tidak ada seed yang menyalin isi database aktif Finance saat
ini melalui dump atau `INSERT ... SELECT`.

Alur generator:

1. Operator memilih produk, versi, release channel, customer, paket, target OS,
   mode online/offline, dan preset yang diizinkan.
2. Sistem mengunci commit/tag sumber dan membuat clean checkout pada runner.
3. Quality gate menjalankan test, lint, dependency scan, secret scan, data/PII
   scan, serta pemeriksaan file terlarang.
4. Sistem membangun artefak aplikasi generik dan memverifikasi reproducibility.
5. Registry menyelesaikan dependency migration, seed, service, cron, Printer
   Agent, WhatsApp, dan requirement lainnya.
6. Sistem membuat `dry-run report` berisi seluruh file/data yang masuk dan
   keluar, requirement, warning, serta alasan penolakan.
7. Setelah approval, generator membuat profil onboarding dan entitlement
   customer bertanda tangan tanpa menaruh private key di paket.
8. Generator menyusun installer online atau bundle offline, SBOM, checksum,
   signature, changelog, compatibility matrix, serta petunjuk instalasi.
9. Artefak disimpan di registry, seluruh langkah diaudit, dan link download
   dibatasi waktu serta customer.
10. Installer menjalankan preflight, membuat database dari schema/seed kanonis,
    meminta data onboarding, memasang service/cron, lalu mengirim hasil health
    check minimum ke Product Control Center bila customer menyetujuinya.

Hasil clean install, upgrade, dan repair/support adalah tiga jenis artefak
berbeda. Build tidak boleh mengambil workspace developer yang sedang kotor.
Ia wajib berasal dari tag/commit immutable dan gagal bila dump, log, upload,
secret, identitas customer lama, atau file di luar allowlist masuk ke paket.

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

### Di Product Control Center milik vendor

1. Katalog seluruh produk, repository, build definition, versi, requirement,
   dan lifecycle.
2. Generator clean install/upgrade berdasarkan package, customer, target OS,
   migration manifest, dan seed profile.
3. Pengelolaan customer, lisensi, paket, add-on, device, dan masa maintenance.
4. Penerbitan aktivasi, reset device, revoke darurat, dan riwayat audit.
5. Artifact registry, release approval, signature, rollout, serta rollback.
6. Dashboard versi dan health minimum agar support mengetahui instalasi yang
   tertinggal migration atau memiliki backend/APK yang tidak kompatibel.

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

`Settings` yang ada sekarang terutama mengelola akun/password, sedangkan
`System Tools` mengelola backup dan replikasi. Keduanya belum menjadi pusat
konfigurasi produk. Tambahkan **System > Profil Usaha & Tampilan** dengan bagian
berikut:

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

Tambahkan pula kelompok System berikut agar customer tidak perlu mengubah
source code:

- **Lokalisasi & Dokumen:** timezone, locale, mata uang, format angka/tanggal,
  prefix dokumen, penomoran, rounding, dan override per outlet.
- **Pajak & Service:** tarif, inklusif/eksklusif, service, tanggal berlaku,
  akun tujuan, dan audit perubahan.
- **Keamanan:** password policy, idle/absolute session timeout, MFA, session
  device, re-authentication, dan audit login. Minimum vendor tidak boleh dapat
  diturunkan oleh customer.
- **Integrasi:** printer, WhatsApp, email, member/self order, payment gateway,
  storage, dan URL publik. Secret bersifat write-only dan disimpan di secret
  store, bukan tabel pengaturan bisnis.
- **Data & Privasi:** retention log/review/bukti, consent, ekspor data, purge
  terkontrol, telemetry opt-in, dan support bundle anonim.
- **Job & Kesehatan:** database, queue, cron, worker POS, Printer Agent,
  WhatsApp, storage, backup, serta error terakhir.
- **Update:** versi aplikasi/schema, channel, preflight, backup, migration,
  rollback, dan riwayat update.
- **Lisensi & Aktivasi:** paket, entitlement, kuota, terminal, maintenance,
  status offline, dan aktivasi. Entitlement hanya dapat dibaca dari payload
  bertanda tangan.
- **Onboarding:** checklist profil, outlet, akun owner, role, rekening,
  printer, backup/restore, dan transaksi uji sebelum go-live.

Konfigurasi deployment seperti password database, encryption key, private key,
tunnel, dan credential replikasi tidak masuk halaman owner biasa. Teknisi hanya
melihat status tersamarkan dan tindakan sensitif memerlukan hak khusus,
re-authentication, serta audit.

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

### Fase 0 - Baseline Bersih dan Pengamanan Data

Hasil wajib:

- repository/build context produk yang tidak membawa backup, upload, log, PID,
  cache, dump, config device, data Namua, atau file probe;
- secret yang pernah masuk Git dirotasi dan konfigurasi berpindah ke
  environment/secret store;
- branch/tag baseline internal, daftar dependency, dan inventaris schema;
- backup database aktif diuji restore ke lingkungan terpisah;
- keputusan tertulis mana aset customer yang menjadi preset opsional dan mana
  yang tidak ikut produk.

**Gerbang:** build allowlist menghasilkan paket netral dan secret/data scan
lulus. Sebelum gerbang ini lulus, paket tidak boleh diberikan kepada pilot.

### Fase 1 - Tutup Temuan Kritis Keamanan dan RBAC

Hasil wajib:

- seluruh writer `Master`, resep, formula, extra, bundle, mobile API, export,
  cron, dan endpoint aksi memakai izin kanonis serta fail-closed;
- bug delete role diperbaiki dan role matrix di-reset secara deklaratif;
- scope outlet/divisi/lokasi diuji untuk setiap role operasional;
- production config memakai HTTPS, secure cookie, CSRF/API exception yang
  benar, login rate limit, session policy, MFA/re-authentication;
- System Tools memisahkan hak secret, backup, restore, replication, sync, dan
  failover;
- Printer Agent memakai secure pairing dan authenticated request, bukan CORS
  terbuka atau bootstrap tanpa key.

**Gerbang:** direct URL, API, APK, dan action POST yang tidak berhak selalu 403
dan tidak mengubah data. Security smoke test serta audit role lulus.

### Fase 2 - Kunci Integritas Transaksi dan Data

Hasil wajib:

- PO/SR/gudang, batch, adjustment/recon, POS, DP, voucher, reservasi,
  void/refund, dan reversal memakai satu writer kuantitas/nilai/lot/movement;
- transaksi normal baru tidak menghasilkan mismatch Stock Health;
- defisit tetap bekerja tanpa stok lot negatif dan HPP tetap penuh;
- laporan penjualan, HPP, rekening, payroll, PH, aset, serta dokumen sumber
  dapat direkonsiliasi;
- backdate, period lock, approval, idempotency, retry, dan server lambat diuji;
- repair historis dipisahkan dari migration produk dan selalu preview-first.

**Gerbang:** satu periode simulasi transaksi lulus invariant tanpa repair
manual. Seluruh reversal mengembalikan efek bisnis tanpa menghapus audit trail.

### Fase 3 - Automated Test dan Quality Gate

Hasil wajib:

- unit test untuk permission, FeatureGate, perhitungan biaya, saldo, dan helper;
- integration test untuk alur transaksi utama serta rollback gagal;
- contract test backend dengan POS web, APK, Printer Agent, dan WhatsApp;
- migration test pada database kosong dan minimal dua snapshot versi lama;
- lint/static analysis PHP, JavaScript, Python, SQL preflight, dependency scan,
  secret scan, dan build reproducibility di CI;
- smoke test browser untuk role utama dan UI kritis.

**Gerbang:** release tidak dapat dibuat bila test, scan, migration dry-run, atau
restore verification gagal.

### Fase 4 - Productization dan System Settings

Hasil wajib:

- service profil usaha/branding terpusat dan tema produk netral;
- halaman lokalisasi, dokumen, pajak/service, keamanan, integrasi, privasi,
  health, backup, update, lisensi, dan onboarding sesuai batas kewenangannya;
- seluruh hardcode identitas runtime diganti melalui urutan outlet -> profil
  usaha -> default netral;
- demo/preset Namua terpisah dan dapat dihapus tanpa merusak aplikasi;
- error production aman, mempunyai correlation ID, dan tidak memunculkan SQL
  atau stack trace kepada user;
- upload/evidence mempunyai storage, MIME validation, access, quota, dan
  retensi yang jelas.

**Gerbang:** instalasi baru dapat di-branding dan dikonfigurasi tanpa edit
source code, lalu semua preview/login/sidebar/dokumen/struk/QR ikut berubah.

### Fase 5 - Installer, Migration, dan Release Engineering

Hasil wajib:

- support matrix resmi PHP/MariaDB/extension/Node/Python dan dependency lock;
- nomor versi aplikasi dan schema registry ber-checksum;
- registry seed kanonis yang membedakan platform wajib, referensi produk,
  onboarding, demo opsional, runtime, dan data terlarang;
- generator instalasi dengan clean checkout, allowlist, `dry-run`, data/secret
  scan, manifest dependency, approval, provenance, dan mode online/offline;
- installer Windows/Linux untuk database, storage, cron/service, Printer Agent,
  WhatsApp, akun owner, outlet pertama, dan preflight;
- migration runner dengan lock, backup, maintenance mode, health check, resume,
  serta rollback;
- release manifest/SBOM/checksum/signature dan channel `pilot`, `stable`, serta
  `critical-fix`;
- server customer tidak memerlukan Git atau alat pengembangan.

**Gerbang:** clean install dan upgrade dua versi lama menghasilkan schema,
seed, dan perilaku yang sama; paket tidak membawa data/secret terlarang;
provenance dapat menunjuk commit sumber; backup/restore drill lulus.

### Fase 6 - Definisi Paket dan Katalog Fitur

Hasil wajib:

- daftar modul dalam bahasa user, feature code stabil, dependency fitur, dan
  owner tiap fitur;
- paket Starter, Operations, Control, Enterprise, add-on outlet/terminal/APK,
  serta perilaku upgrade/downgrade;
- harga pilot, biaya implementasi, maintenance, support, migrasi data, dan
  perangkat dipisahkan pada penawaran;
- EULA, kontrak lisensi, kebijakan data, SLA/support boundary, dan kebijakan
  penggantian device ditinjau pihak hukum;
- FeatureGate berjalan dalam mode audit untuk menemukan jalur yang terlewat
  sebelum enforcement.

**Gerbang:** satu katalog machine-readable menjadi sumber License Hub, aplikasi,
dokumentasi, dan penawaran. Tidak ada paket yang dibuat lewat branch berbeda.

### Fase 7 - Product Control Center dan Signed Entitlement

Hasil wajib:

- aplikasi terpisah milik vendor untuk banyak produk, repository integration,
  build runner, artifact registry, installation profile, customer, paket,
  lisensi, add-on, maintenance, device, release, monitoring, dan audit;
- source tetap di Git privat, artefak di storage terlindungi, dan database
  pusat hanya menyimpan metadata serta referensi immutable;
- private key hanya berada di License Hub/signing service;
- Finance menyimpan installation identity, public key, signed entitlement,
  cache offline, dan audit status;
- aktivasi online/offline, grace period 30 hari, renewal, revoke, transfer, dan
  recovery terdokumentasi;
- telemetry minimal dan consent-based; tidak mengirim transaksi, payroll, stok,
  atau data pribadi untuk pemeriksaan lisensi biasa.

**Gerbang:** modifikasi database lokal tidak dapat menaikkan paket, produk baru
dapat memakai pipeline melalui manifest/adapter, dan gangguan Product Control
Center tidak mematikan kasir atau akses ekspor data.

### Fase 8 - FeatureGate, Terminal, dan APK

Hasil wajib:

- FeatureGate server dipakai oleh menu, URL, controller, API, export, cron,
  worker, dan APK;
- RBAC tetap diperiksa setelah entitlement; keduanya tidak saling menggantikan;
- terminal POS/APK dipasangkan memakai key pair/device certificate, bukan MAC
  atau cookie sebagai identitas tunggal;
- owner dapat melihat, menonaktifkan, dan mengganti device dengan audit;
- compatibility matrix backend/APK dan minimum supported version diterapkan;
- downgrade menutup pembuatan transaksi fitur, tetapi data lama tetap dapat
  dibaca/diekspor sesuai kebijakan.

**Gerbang:** seluruh bypass UI/API/device test gagal dengan aman dan batas
outlet/terminal selalu konsisten pada kondisi online maupun offline.

### Fase 9 - Update Center dan Pilot Berbayar Terbatas

Hasil wajib:

- update terbantu dengan signature, preflight, backup, migration, health check,
  rollback, dan log;
- dua instalasi pilot mewakili Starter dan Operations/Control;
- uji internet putus, disk penuh, service mati, device ganti, restore, upgrade,
  downgrade, maintenance habis, refund/void, dan periode sibuk;
- onboarding, knowledge base, support bundle aman, eskalasi, dan incident runbook;
- feedback user, waktu instalasi, biaya support, serta kecocokan harga dicatat.

**Gerbang:** pilot beroperasi stabil pada periode yang disepakati dan seluruh
insiden P0/P1 ditutup sebelum customer umum.

### Fase 10 - Siap Jual dan Operasi Produk

Hasil wajib:

- website/penawaran, demo netral, kontrak, invoice lisensi, dan channel support;
- release stable pertama, installer, update channel, License Hub monitoring,
  backup/recovery SOP, dan status page internal;
- ownership produk, jadwal patch security, support hours, EOL policy, serta
  proses disclosure kerentanan;
- metrik yang dipantau: aktivasi, versi, health anonim, update success,
  incident, support load, renewal, dan churn tanpa mengambil data bisnis;
- evaluasi berkala harga/paket setelah data pilot, bukan mengubah codebase.

**Gerbang:** seluruh kriteria siap jual pada bagian 12 telah dibuktikan dan
ditandatangani oleh product owner, engineering, support, dan reviewer bisnis.

## 11. Urutan Pengerjaan yang Harus Kita Jalankan

Urutan ini adalah antrean kerja, bukan pekerjaan paralel tanpa gerbang. Lisensi
yang bagus tidak akan membantu jika aplikasi, data, permission, pemasangan,
migration, dan pemulihan update belum aman.

### Langkah 1 - Pisahkan produk dari data instalasi saat ini

Buat clean product repository/build context berbasis allowlist. Backup,
uploads, log, PID, cache, konfigurasi device, database config, SQL repair
customer, dan identitas Namua tidak ikut. Rotasi secret yang pernah masuk Git,
pisahkan data demo, dan uji bahwa paket baru dapat diekstrak tanpa membawa data
pegawai/customer lama.

### Langkah 2 - Tutup keamanan dan RBAC P0

Guard Master/Master Relation/API/cron/export, perbaiki delete role, reset role
matrix deklaratif, aktifkan production security, amankan System Tools, login,
session, dan Printer Agent. Hasilnya dibuktikan melalui direct URL dan API test,
bukan hanya menu yang tersembunyi.

### Langkah 3 - Stabilkan writer dan laporan lintas modul

Uji dan perbaiki PO, SR, gudang, batch, adjustment, POS, HPP, keuangan,
void/refund, PH, payroll, aset, reservasi, printer, dan queue sampai transaksi
baru tidak menghasilkan mismatch atau saldo yang tidak dapat ditelusuri.

### Langkah 4 - Bangun automated quality gate

Tambahkan test unit, integration, contract, migration, restore, RBAC, browser
smoke, dependency/secret scan, dan CI. Release harus gagal otomatis bila satu
gerbang gagal.

### Langkah 5 - Jadikan identitas dan aturan umum sebagai pengaturan

Bangun profil usaha, branding, lokalisasi, dokumen, pajak/service, security,
integrasi, privasi, health, update, lisensi, dan onboarding. Semua pembaca
identitas memakai service pusat dan fallback netral.

### Langkah 6 - Buat registry data awal dan generator instalasi

Tetapkan runtime support, lock dependency, app/schema version, migration
registry, seed registry, klasifikasi data awal, installer Windows/Linux,
service/cron setup, backup, rollback, health check, dan release signature.
Generator wajib mempunyai dry-run, allowlist, secret/data scan, provenance,
serta output online/offline. Clean install dan upgrade snapshot lama harus
menghasilkan schema serta seed kanonis yang sama.

### Langkah 7 - Kunci produk yang dijual

Sebelum coding lisensi, buat satu dokumen komersial yang berisi harga, paket,
add-on, jumlah outlet, slot terminal tiap paket, durasi maintenance, dan SOP
ganti device. Dokumen ini juga menjadi bahan kontrak/EULA. Hasilnya: tim sales,
support, dan developer memakai definisi produk yang sama.

### Langkah 8 - Finalisasi release engineering Finance

Buat nomor versi resmi, changelog, migration manifest, backup database,
rollback plan, dan smoke test. Buat release package dari branch/tag yang bersih
di CI atau mesin build khusus. Server customer tidak lagi diupdate lewat Git.
Hasilnya: satu release bisa dipasang ulang dan dipulihkan dengan cara yang sama
di semua customer. Artefak generik ini menjadi input generator, bukan hasil
copy workspace dan bukan source variant untuk masing-masing paket.

### Langkah 9 - Bangun Product Control Center multi-produk

Buat aplikasi/layanan internal terpisah untuk mengelola banyak produk,
repository integration, build, artefak, generator instalasi, customer, paket,
lisensi, activation code, terminal, maintenance, release, monitoring, approval,
dan audit. Product Control Center tidak menyimpan transaksi atau data keuangan
customer. Ia mengatur source melalui referensi Git privat, menyimpan artefak di
storage terlindungi, dan menerbitkan lisensi bertanda tangan.

Kelompok tabel minimum di Product Control Center:

| Kelompok | Isi utama |
| --- | --- |
| `prd_product`, `prd_repository`, `prd_build_definition` | Produk, adapter, repository, commit policy, requirement, dan perintah build. |
| `rel_release`, `rel_artifact`, `rel_build_run` | Versi, channel, build provenance, hasil test/scan, checksum, signature, dan lokasi artefak. |
| `ins_profile`, `ins_migration_manifest`, `ins_seed_manifest` | Target OS/runtime, dependency migration/seed, onboarding, service, dan preset. |
| `lic_customer` | Identitas organisasi customer dan kontak pemilik. |
| `lic_plan` | Starter POS, Operations, Control, Enterprise. |
| `lic_feature` dan `lic_plan_feature` | Katalog fitur serta fitur milik setiap paket. |
| `lic_license` | Nomor lisensi, status, batas outlet/terminal, dan masa maintenance. |
| `lic_license_entitlement` | Add-on dan limit yang spesifik untuk satu customer. |
| `lic_device_activation` | Device/terminal aktif, outlet, public key, dan statusnya. |
| `lic_activation_audit` | Jejak aktivasi, reset, penggantian, dan alasan. |
| `ops_installation`, `ops_health_event`, `ops_update_run` | Versi instalasi, health minimum, hasil update, dan consent telemetry. |
| `audit_event`, `approval_request` | Jejak tindakan sensitif dan persetujuan release/lisensi/build. |

### Langkah 10 - Integrasikan lisensi ke Finance tanpa mengganggu operasi

Tambahkan halaman `System > Lisensi & Aktivasi`, cache lisensi bertanda tangan,
dan pemeriksaan status yang tidak langsung mematikan kasir. Tambahkan satu
`FeatureGate` di backend; menu, URL, controller, API, cron, dan endpoint APK
memakai gate yang sama. Saat belum ada lisensi pada masa migrasi, gunakan mode
internal/development yang dicatat jelas dan hanya dapat dipakai oleh instalasi
milik kita.

### Langkah 11 - Aktifkan batas terminal dan APK POS

Saat POS web terminal atau APK pertama kali dipasang, device melakukan pairing
dengan kode aktivasi. Setiap device menyimpan pasangan kunci, bukan hanya MAC
address atau cookie. Owner dapat menonaktifkan device lama lalu memasangkan
pengganti; semua tindakan masuk audit. APK POS harus memakai entitlement yang
sama dan menghitung satu slot terminal.

### Langkah 12 - Buat Update Center secara bertahap

Versi pertama cukup berupa update terbantu: admin melihat release, sistem
melakukan preflight dan backup, lalu admin menerapkan paket yang sudah
ditandatangani. Setelah proses itu stabil, baru aktifkan download dan update
otomatis terjadwal. Setiap update wajib membuat backup, menjalankan migration,
health check, dan menyediakan rollback yang diuji.

### Langkah 13 - Paketkan dan lindungi release production

Pisahkan repository private dari artefak pelanggan. Rilis production berisi
kode yang diperlukan aplikasi, konfigurasi template, installer, dan checksum;
tidak berisi Git, secret, private key, atau alat pengembangan. Evaluasi PHP
encoder setelah installer/updater stabil. Encoder memperkuat hambatan menyalin,
tetapi kontrak lisensi dan proses distribusi tetap menjadi perlindungan utama.

### Langkah 14 - Pilot sebelum dijual luas

Pilih satu sampai dua instalasi pilot. Uji pembelian lisensi Starter satu
terminal dan Operations tiga terminal, ganti HP/PC, internet putus,
maintenance habis, feature gate, update, rollback, dan ekspor data. Hanya
setelah seluruh skenario ini lolos, paket dijual ke customer berikutnya.

## 12. Kriteria Siap Jual Versi Pertama

Produk dianggap siap dijual ketika seluruh poin berikut telah terbukti pada
instalasi pilot:

- artefak customer tidak membawa backup, upload, log, secret, config device,
  identitas customer lama, Git, atau alat repair internal;
- generator menunjukkan dry-run file/data, provenance commit, dependency
  migration/seed, checksum, signature, dan hasil seluruh quality gate;
- database kosong terbentuk dari migration serta seed kanonis tanpa dump
  produksi, akun/password default, atau data operasional instalasi lain;
- customer bisa memasang aplikasi tanpa akses Git atau source code;
- nama usaha, logo, tema, locale, dokumen, URL publik, outlet, rekening, dan
  aturan pajak/service dapat disiapkan melalui onboarding tanpa edit source;
- runtime/dependency, versi aplikasi, versi schema, checksum migration, dan
  signature release dapat dibuktikan;
- lisensi membatasi outlet dan terminal sesuai paket/kontrak, termasuk APK;
- owner dapat melihat dan mengganti device dengan audit trail;
- RBAC dan FeatureGate sama-sama menolak akses yang tidak berhak;
- Printer Agent, WhatsApp, cron, worker, upload, dan endpoint publik telah
  melalui security/abuse test serta mempunyai health/retention;
- POS tetap beroperasi saat koneksi License Hub terputus dalam grace period;
- masa maintenance habis tidak mengunci transaksi maupun data;
- update memiliki preflight, backup, migration, health check, dan rollback;
- automated test transaksi, rollback, RBAC, migration, restore, dan browser
  smoke lulus pada release candidate yang sama dengan paket customer;
- support dapat membaca status versi/aktivasi tanpa menerima data transaksi
  customer secara default;
- Product Control Center dapat menghasilkan ulang artefak customer yang sama
  dari source immutable dan menambahkan produk baru melalui manifest/adapter;
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
- Jangan menyimpan repository source sebagai blob biasa di database Product
  Control Center; gunakan Git privat dan artifact storage dengan akses terpisah.
- Jangan membuat dump database aktif sebagai seed customer baru atau
  mencampurkan clean install, upgrade, dan repair historis dalam satu paket.

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
7. Pengerjaan dimulai dari Langkah 1 sampai 6: pemisahan data, keamanan/RBAC,
   integritas transaksi, automated test, productization, dan installer. Lisensi
   dasar baru dimulai setelah gerbang fondasi tersebut lulus. Obfuscation dan
   APK locking tidak boleh mendahului release engineering.
8. Aplikasi pusat final adalah Product Control Center multi-produk. Finance
   menjadi produk pertama; produk berikutnya masuk lewat katalog, adapter,
   manifest build/install, dan registry fitur tanpa membuat control plane baru.
9. Satu artefak kode digunakan untuk seluruh paket pada versi yang sama.
   Perbedaan customer dihasilkan melalui install profile, onboarding, dan
   signed entitlement, bukan modifikasi manual source.

## 15. Ringkasan Arah Produk

Untuk versi pertama yang akan dijual, arah finalnya adalah:

- selesaikan gerbang audit, clean product build, automated test, pengaturan
  sistem, dan installer sebelum mengaktifkan enforcement lisensi;
- jual **perpetual on-premise per organisasi**; Starter membawa 1 outlet dan
  1 terminal POS, sedangkan Operations menjadi pilihan awal untuk 3 terminal;
- customer memperoleh versi saat pembelian dan 12 bulan update/support;
- update berikutnya setelah masa itu melalui renewal maintenance, tanpa
  mematikan aplikasi yang sudah dibeli;
- source repository tidak diserahkan;
- kelola source di Git privat dan seluruh build, artefak, generator instalasi,
  lisensi, update, monitoring, approval, serta audit melalui Product Control
  Center multi-produk;
- bentuk database customer dari migration dan seed kanonis; exclude dump,
  backup, log, upload, data bisnis, secret, dan konfigurasi device;
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
