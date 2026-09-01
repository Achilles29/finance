# Audit Total Aplikasi Finance dan Roadmap Pengembangan

**Tanggal audit awal:** 2026-08-30

**Pembaruan menyeluruh:** 2026-09-01

**Sifat audit:** Pemeriksaan baca-saja terhadap source code, konfigurasi, route,
sidebar, RBAC, struktur database, kesehatan data aktif, writer transaksi,
artefak operasional, dan konsistensi antarmuka.

**Status dokumen:** Baseline utama perbaikan aplikasi sebelum pemaketan,
lisensi, installer, updater, dan penjualan.

**Dokumen terkait:** docs/2026-08-28_roadmap_komersialisasi_finance_dan_lisensi.md
tetap menjadi pegangan konsep produk dan lisensi. Dokumen ini menjadi daftar
utang teknis dan urutan implementasi yang harus diselesaikan.

Dokumen ini menggantikan status temuan pada versi 30 Agustus yang sudah tidak
sesuai dengan kondisi sekarang. Angka di bawah adalah snapshot lokal pada
1 September 2026 dan dapat berubah setelah transaksi atau sinkronisasi database
server berikutnya.

## 1. Kesimpulan untuk Pemilik Aplikasi

Finance sudah memiliki fondasi bisnis yang jauh lebih lengkap daripada aplikasi
kasir biasa. Di dalam satu sistem sudah tersedia POS, reservasi, self order,
printer per divisi, loyalty, purchase order, store request, gudang, persediaan
divisi, produksi, HPP, keuangan, absensi, PH, payroll, aset, laporan, dan audit
operasional.

Aplikasi ini layak dikembangkan menjadi produk komersial, tetapi **belum aman
langsung dipaketkan dan dijual**. Hambatan utama sekarang bukan kekurangan
fitur. Hambatan utamanya adalah pengamanan aksi, konsistensi nilai data lama,
navigasi yang tumbuh tanpa satu sumber tunggal, tampilan yang belum seragam,
serta proses deployment dan backup yang masih bercampur dengan repository
source.

Kesimpulan terpenting dari scan ulang:

1. Stok bahan baku aktif saat ini sehat: tidak ada mismatch material, tidak ada
   lot negatif, dan tidak ada defisit terbuka.
2. Enam component masih mismatch nilai walaupun kuantitasnya sama. Selisih
   absolutnya sekitar Rp1.418.765.823,55 dan berasal dari nilai historis negatif
   yang terbawa ke bulan aktif.
3. Dashboard component hanya memeriksa kuantitas sehingga dapat menampilkan
   angka mismatch 0, padahal halaman Stock Health menemukan enam mismatch
   nilai. Ini adalah bug informasi yang masih aktif.
4. Guard writer component sekarang sudah menolak unit cost negatif. Jadi masalah
   component tersebut adalah utang data historis yang harus direpair terarah,
   bukan bukti bahwa writer baru masih bebas membuat nilai negatif.
5. Endpoint master, resep, bundle, formula, dan POS mobile belum seluruhnya
   memeriksa izin per aksi. Menyembunyikan menu belum cukup untuk mengamankan
   URL.
6. Role operasional masih menerima hak yang terlalu luas. Kasir dan Barista
   dapat memperoleh hak mutasi pada area yang tidak sesuai tugasnya.
7. Sidebar saat ini mempunyai dua sumber kebenaran: tabel sys_menu dan
   penyusunan ulang secara hardcode di view sidebar. Kondisi ini membuat urutan,
   izin, ikon, dan grouping mudah berbeda.
8. Backup otomatis saat ini melakukan commit, merge, dan push dump/log ke
   origin/main setiap 30 menit. Ini berisiko mencampur backup customer dengan
   source, mengubah main tanpa proses review, dan menyebabkan konflik saat
   developer pull atau push.
9. Keamanan produksi belum layak untuk distribusi customer: secret dan kredensial
   masih berada di konfigurasi source, CSRF dan secure cookie belum aktif,
   session terlalu panjang, dan belum ada throttling login.
10. Fondasi UI bersama sebenarnya sudah ada, tetapi pemakaiannya belum merata.
    Ratusan view masih membawa style, script, modal, alert, filter, tabel, dan
    pagination sendiri-sendiri.
11. Test otomatis sudah ada, tetapi baru satu smoke suite inventory dengan
    sembilan skenario. Belum ada regression suite lintas POS, purchase,
    inventory, keuangan, attendance, payroll, asset, printer, dan RBAC.
12. Schema deployment masih mengandalkan ratusan SQL manual. Belum ada registry
    versi schema dan migrasi deterministik untuk instalasi atau update customer.
13. Branding dan pengaturan tenant belum terpusat. Identitas Namua masih
    hardcode di banyak file dan belum dapat diubah aman oleh customer.
14. Repository pengembangan terlalu besar karena backup, upload, log, dan
    artefak operasional. Git pack lokal sudah sekitar 7,66 GiB.
15. Sebelum menambah fitur besar, prioritas harus dialihkan ke pengamanan,
    perbaikan nilai component, penyederhanaan navigasi, design system, test,
    installer, dan release pipeline.

## 2. Cara Audit Dilakukan

Pemeriksaan ini dilakukan tanpa menulis data bisnis dan tanpa menjalankan SQL
repair. Area yang diperiksa:

- 444 file PHP diperiksa dengan PHP lint; hasilnya 444 lulus dan 0 gagal.
- Smoke test inventory period guard dijalankan; 9 skenario lulus.
- Registry sys_page, sys_menu, auth_role_permission, controller page code, dan
  route dibandingkan.
- URL menu internal diuji tanpa login untuk memastikan tidak 404 atau 500.
- Hak setiap role dihitung dan dibandingkan dengan pekerjaan role tersebut.
- Controller mutasi penting dibaca untuk memastikan guard tidak hanya berada di
  tampilan.
- Kesehatan lot, ledger bulanan, defisit, runtime job, availability queue,
  rekening, PH, payroll, purchase, asset, dan relasi foreign key diperiksa.
- Inventory_control_model dipanggil untuk membandingkan ledger bulanan dengan
  lot aktual pada material dan component.
- View, CSS, JavaScript, modal, filter, tabel, pagination, ikon, warna, dan
  branding dipetakan secara statis.
- Isi repository, backup, upload, log, migration, dependency, dan script layanan
  pendamping diperiksa.

Batas audit:

- Audit ini bukan penetration test eksternal.
- Audit visual dilakukan dari struktur view dan pola komponen, bukan smoke test
  browser pada seluruh ratusan halaman.
- Tidak semua kombinasi transaksi dapat diuji hanya dengan snapshot database.
- Temuan historis tidak boleh langsung direpair massal tanpa preview,
  preflight, transaksi, audit trail, dan validasi sesudah apply.

## 3. Snapshot Kondisi Saat Ini

### 3.1 Source dan database

| Area | Hasil |
| --- | ---: |
| File PHP yang dilint | 444 |
| Kegagalan lint | 0 |
| Smoke test inventory | 9 lulus |
| Tabel aktif | 317 |
| View database | 0 |
| Foreign key | 663 |
| Engine tabel aktif | Seluruhnya InnoDB |
| sys_page | 201 total, 195 aktif |
| sys_menu | 234 total, 226 aktif |
| Role aktif | 12 |
| Baris auth_role_permission | 1.333 |
| SQL utama di folder sql | 74 |
| SQL arsip di sql/_old | 386 |

### 3.2 Kesehatan persediaan dan antrean

| Pemeriksaan | Hasil |
| --- | ---: |
| Lot material negatif | 0 |
| Lot component negatif | 0 |
| Mismatch kuantitas material | 0 |
| Mismatch nilai material | 0 |
| Mismatch kuantitas component | 0 |
| Mismatch nilai component | 6 |
| Defisit OPEN | 0 |
| Defisit SETTLED | 75 |
| Defisit VOID | 7 |
| Defisit WRITTEN_OFF | 9 |
| Adjustment material/component bertanggal masa depan | 0 |
| Runtime job SUCCESS | 2.447 |
| Runtime job CANCELLED | 3 |
| Runtime job aktif terminal atau gagal | 0 |
| Availability queue SUCCESS | 253 |
| Availability rebuild log | 885.829 baris, sekitar 304,30 MiB |

### 3.3 Kondisi sehat yang perlu dipertahankan

- Ledger rekening aktif sama dengan saldo awal ditambah seluruh mutasi.
- Hanya ada satu rekening default dari tujuh rekening aktif.
- Tidak ditemukan orphan pada PO line, receipt, SR line, salary assignment,
  schedule, asset change request, dan finance mutation account.
- Tidak ada saldo PH mentah negatif.
- Tidak ada USE PH sebelum cutover 1 Juni 2026 yang masih aktif.
- PH memakai FIFO dan expiry disinkronkan sebelum ringkasan saldo ditampilkan.
- Guard batas PH dan jumlah hari jadwal sudah tersedia.
- Fairuz memiliki saldo aktif yang diharapkan, yaitu 1 PH dari hak terbaru.
- Pengaman periode inventory menolak backdate, future date, reopen, dan gap lot
  pada smoke suite yang tersedia.
- Kasus TAHU PONG dan KENTANG tidak lagi menjadi anomali aktif setelah repair
  terarah dan pengaman periode.
- Writer component saat ini menolak unit cost negatif dan rollover produksi
  menolak valuation negatif.
- Reservasi menghitung ulang harga dan HPP saat diverifikasi kasir ke POS.
- Halaman asset management yang baru memakai guard aksi lebih baik daripada
  pola controller master lama.

Makna penting: temuan yang sudah sehat di atas tidak boleh dihapus dari test.
Ia harus diubah menjadi invariant permanen agar bug lama tidak kembali.

## 4. Temuan Prioritas 0: Harus Diselesaikan Sebelum Pilot Customer

### P0-01. Endpoint master generik belum deny-by-default

**Bukti utama:** application/controllers/Master.php.

Store, update, toggle, stock mode, dan beberapa endpoint generik belum seluruhnya
memanggil guard page dan aksi yang kanonis.

**Risiko untuk user:** akun yang dapat login berpotensi memanggil URL perubahan
langsung walaupun menu disembunyikan.

**Perbaikan wajib:**

1. Buat registry satu entity ke satu page code.
2. Semua endpoint view, create, edit, delete, export, approve, post, void, dan
   reopen harus memanggil guard server.
3. Entity yang tidak terdaftar harus ditolak, bukan memakai fallback.
4. Tambahkan audit log untuk perubahan master sensitif.
5. Tambahkan negative test direct URL untuk setiap role.

### P0-02. Writer resep, extra, bundle, dan formula belum konsisten memakai RBAC

**Bukti utama:** application/controllers/Master_relation.php.

Sebagian workspace sudah dijaga, tetapi banyak endpoint mutasi recipe, formula,
extra, dan bundle belum menggunakan izin aksi yang eksplisit.

**Risiko untuk user:** perubahan formula dapat mengubah HPP, kebutuhan bahan,
stok POS, dan produksi tanpa hak yang semestinya.

**Perbaikan wajib:**

- Gunakan page code kanonis untuk setiap kelompok writer.
- Pisahkan hak melihat, mengubah, menyetujui, dan mempublikasikan formula.
- Simpan before/after, alasan, aktor, waktu, dan versi formula.
- Formula yang sudah dipakai transaksi tidak boleh diubah tanpa versioning.

### P0-03. POS mobile memakai token, tetapi belum memakai izin per aksi

**Bukti utama:** application/controllers/Pos_mobile.php.

Authorize_mobile memeriksa token dan konteks pengguna, tetapi aksi seperti save,
confirm, void, refund, payment, buka/tutup kasir, dan test printer belum
seluruhnya dipetakan ke permission bisnis.

**Risiko untuk user:** token valid dapat memiliki kemampuan lebih luas daripada
menu atau role pemilik token.

**Perbaikan wajib:**

- Token harus membawa user, employee, outlet, terminal, device, role, dan
  daftar entitlement.
- Setiap endpoint mobile memakai permission yang sama dengan web.
- Gunakan masa token pendek, refresh token, revoke device, dan rotasi token.
- Aksi void, refund, reprint, reopen, serta adjustment membutuhkan step-up
  approval.
- Jangan membuat kebijakan terpisah antara web POS dan APK POS.

### P0-04. Matrix role operasional terlalu luas

Jumlah izin saat ini:

| Role | View | Create | Edit | Delete | Export |
| --- | ---: | ---: | ---: | ---: | ---: |
| SUPERADMIN | 195 | 184 | 185 | 181 | 194 |
| CEO | 183 | 106 | 108 | 83 | 153 |
| MGR | 181 | 147 | 147 | 148 | 158 |
| ADMIN | 153 | 94 | 97 | 65 | 121 |
| ADM_GDG | 117 | 81 | 80 | 55 | 100 |
| ADM_FIN | 98 | 43 | 41 | 24 | 83 |
| KASIR | 107 | 85 | 85 | 75 | 80 |
| BARISTA | 117 | 87 | 85 | 81 | 85 |
| CHEF | 79 | 46 | 44 | 42 | 45 |
| ADM_HR | 44 | 27 | 26 | 26 | 32 |
| HOD | 33 | 11 | 7 | 6 | 18 |
| STAFF | 16 | 2 | 0 | 0 | 0 |

Jumlah besar tidak otomatis salah, tetapi sampling menemukan overgrant nyata:

- KASIR dapat memperoleh mutasi attendance settings, schedule, PH, finance
  account, mutation, payroll setup, PO, receipt, opening stock, master item,
  material, product, recipe, dan vendor.
- BARISTA dapat memperoleh mutasi inventory health, deficit, period control,
  value correction, opname, master item/material/vendor, recipe, bundle,
  component master, transfer, adjustment, dan WA settings.

Masalah tambahan:

- Auth_model menggabungkan seluruh role dengan pola OR.
- get_division_scope mengembalikan null saat scope kosong atau lebih dari satu.
- Null kemudian dibaca sebagai tidak terbatas. Multi-role dapat memperluas
  scope tanpa keputusan eksplisit.

**Perbaikan wajib:**

1. Definisikan role dari tugas user, bukan menyalin role luas lalu menambah.
2. Seed harus bersifat konvergen: menambah hak yang benar dan mencabut hak yang
   sudah tidak benar.
3. Multi-role harus memakai union permission tetapi scope outlet/divisi harus
   eksplisit, tidak boleh null berarti bebas.
4. Pisahkan permission operasional, approval, correction, system, dan audit.
5. Sediakan halaman simulasi: pilih user lalu lihat menu, URL, dan scope efektif.
6. Sediakan report permission drift antara baseline paket dan database customer.

### P0-05. Penghapusan role salah kolom

**Bukti utama:** application/models/Role_model.php.

Delete auth_role_permission masih menggunakan kolom id, padahal relasi role
berada pada role_id. Operasi juga belum dibungkus transaksi dan validasi
ketergantungan.

**Risiko:** permission role dapat tertinggal atau baris yang salah terhapus.

**Perbaikan wajib:** perbaiki kondisi ke role_id, gunakan transaksi, tolak role
sistem, periksa user-role, dan lakukan post-delete assertion.

### P0-06. Konfigurasi keamanan belum layak produksi

Temuan aktif:

- Encryption key berada di source.
- Kredensial database masih menjadi perubahan tracked pada database.php.
- CSRF belum aktif.
- Cookie secure dan httponly belum aman.
- Environment default masih development.
- Session dapat hidup satu tahun dan regenerasi terlalu jarang.
- Belum ada login throttling, lockout bertahap, dan audit gagal login yang
  memadai.
- Secret printer, WhatsApp, tunnel, backup, dan integrasi belum memakai secret
  store per instalasi.

**Perbaikan wajib:**

- Pindahkan secret ke environment di luar web root.
- Buat config template tanpa secret.
- Aktifkan HTTPS-only cookie, httponly, samesite, CSRF, dan session pendek.
- Tambahkan rate limit login dan endpoint publik.
- Buat pemeriksaan startup yang gagal tertutup bila production config belum
  lengkap.
- Jangan pernah memasukkan database.php customer ke paket update.

### P0-07. Backup otomatis mengubah origin/main

**Bukti utama:**

- scripts/backup/backup_full.sh men-stage backup/dumps dan backup/logs, commit,
  fetch, merge, lalu push ke branch main.
- scripts/backup/backup_full.bat melakukan pola yang sama.
- Log backup 1 September mencatat Git push berhasil ke origin/main.

**Risiko untuk user dan developer:**

- Backup customer bercampur dengan source product.
- Main dapat berubah setiap 30 menit tanpa review.
- Pull developer dapat membawa dump dan log operasional.
- Backup job dapat memerge source saat working tree sedang dipakai.
- Konflik source, kebocoran data, ukuran repository, dan rollback menjadi jauh
  lebih sulit.

**Perbaikan wajib:**

1. Hentikan Git sebagai media backup database.
2. Backup ke storage terpisah: object storage, SFTP, NAS, atau repository backup
   khusus yang terenkripsi.
3. Branch source hanya berubah melalui commit developer dan release pipeline.
4. Enkripsi backup, buat checksum, retention, restore drill, dan alert gagal.
5. Tambahkan backup/dumps, backup/logs, upload customer, PID, cache, dan runtime
   artifact ke ignore serta package exclude.
6. Bersihkan riwayat Git dengan prosedur terencana setelah backup eksternal
   terverifikasi. Jangan menjalankan history rewrite langsung di server aktif.

### P0-08. Deployment schema belum deterministik

Kondisi saat ini:

- Migration CodeIgniter nonaktif.
- Versi migration 0 dan folder migration kosong.
- Terdapat 74 SQL utama dan 386 SQL arsip yang dijalankan manual.
- Tidak ada schema version registry yang membuktikan urutan apply per customer.

**Risiko:** source baru dapat berjalan di schema lama, SQL terlewat, SQL
terulang, atau customer berbeda mempunyai struktur berbeda.

**Perbaikan wajib:**

- Tetapkan satu migration runner.
- Setiap migration mempunyai version, checksum, dependency, preflight, apply,
  verify, dan bila aman rollback.
- Updater harus menolak build bila versi schema tidak cocok.
- Installer memakai baseline schema bersih, bukan dump database operasional.
- Simpan migration history per instalasi dan tampilkan di dashboard system.

### P0-09. Printer Agent dan layanan lokal belum memakai trust contract produksi

Printer Agent telah jauh lebih rapi, tetapi masih ada risiko produk:

- Local HTTP service belum memakai autentikasi request yang kuat.
- Bootstrap dapat fail-open pada konfigurasi tertentu.
- Development Flask server belum layak menjadi service customer.
- Secret, device identity, pairing, retry, dan version compatibility belum
  menjadi kontrak rilis yang seragam.

**Perbaikan wajib:** signed request, nonce, timestamp, device pairing,
certificate/token rotation, service manager Windows/Linux, health endpoint,
version negotiation, installer, auto-start, log rotation, dan update rollback.

## 5. Temuan Prioritas 1: Stabilitas Data, Navigasi, dan Pengalaman User

### P1-01. Enam component mismatch nilai masih aktif

Hasil Inventory_control_model per 1 September:

| Component | Qty ledger vs lot | Selisih nilai |
| --- | --- | ---: |
| SAUCE BANGKOK | 785 = 785 | Rp -1.416.006.811,46 |
| CHICKEN CUBE 40 | 25 = 25 | Rp -2.731.669,34 |
| CHICKEN SLICE SUSHI | 4 = 4 | Rp -27.197,02 |
| SAMBAL BAWANG GEPREK | sama | Rp -89,46 |
| SAMBAL DABU-DABU | sama | Rp -50,03 |
| NASI PUTIH | sama | Rp -6,24 |

Total selisih absolut sekitar Rp1.418.765.823,55. Tiga nilai terbesar sudah
muncul sejak ledger historis Juni/Juli lalu dibawa saat rollover. Opening lot
September bernilai nol, sementara ledger bulanan membawa nilai negatif.

**Kesimpulan:** ini bukan stok fisik minus dan bukan defisit. Ini adalah nilai
historis rusak yang terbawa ke periode aktif.

**Perbaikan:**

1. Buat preview per component dari sumber nilai pertama kali menjadi negatif.
2. Pastikan formula produksi, unit conversion, qty hasil, total input cost,
   waste, dan allocation denominator pada transaksi asal.
3. Repair hanya ledger/lot yang terbukti, tanpa menebak harga.
4. Simpan before/after dan alasan.
5. Jalankan ulang health check sampai qty gap dan value gap nol.
6. Tambahkan assertion unit cost, total value, dan average cost tidak negatif
   di seluruh jalan masuk component.
7. Tambahkan regression test rollover bulan dan production posting.

### P1-02. Dashboard component menampilkan clear walaupun mismatch nilai ada

Fungsi dashboard component reconcile saat ini membandingkan kuantitas monthly,
movement, dan lot, tetapi tidak memasukkan monthly_lot_value_gap.

**Dampak:** pemilik melihat 0 mismatch pada dashboard, sedangkan Stock Health
menemukan enam mismatch nilai besar.

**Perbaikan:**

- Dashboard harus menghitung mismatch qty dan mismatch nilai.
- Card harus memisahkan “Qty berbeda” dan “Nilai FIFO berbeda”.
- Tampilkan nilai absolut, divisi, component, bulan, dan link ke detail health.
- Status clear hanya boleh muncul bila kedua gap berada dalam tolerance.
- Tambahkan test dengan qty sama tetapi nilai berbeda.

### P1-03. Sidebar mempunyai dua sumber kebenaran

Registry database saat ini relatif sehat:

- Tidak ada menu aktif dengan parent hilang.
- Tidak ada menu aktif yang menunjuk page nonaktif.
- Tidak ada page aktif tanpa permission.
- Probe URL tidak menemukan 404 atau 500.

Namun application/views/layout/sidebar.php berukuran sekitar 46 KB dan masih:

- Mengganti ikon dari map hardcode.
- Memindahkan grouping menu.
- Menyisipkan workspace sintetis.
- Menggabungkan master, inventory, availability, component, dan POS saat
  runtime.
- Dapat menghasilkan item sintetis tanpa page_id.

**Dampak:** perubahan sys_menu belum tentu sama dengan sidebar yang terlihat.
Audit database dapat lulus tetapi hasil user berbeda.

**Perbaikan:**

1. sys_menu menjadi satu-satunya sumber struktur, urutan, label, ikon, parent,
   route, dan page.
2. View sidebar hanya merender tree yang sudah diotorisasi.
3. Hapus injection dan regrouping hardcode.
4. Menu tanpa page_id hanya boleh berupa group yang tidak dapat memanggil aksi.
5. Tambahkan validator registry pada CI dan halaman System Health.

### P1-04. Favorites dan menu tanpa page berpotensi melewati filter izin

Menu_model memfilter page-linked menu berdasarkan can_view. Namun:

- Menu tanpa page_id selalu dapat tampil.
- Favorites memeriksa aktif/nonaktif, tetapi belum selalu memverifikasi izin
  page efektif.
- Pin menu dapat menerima ID menu tanpa validasi permission.

**Perbaikan:** query favorites dan pin harus memakai resolver permission yang
sama dengan sidebar. Saat izin dicabut, favorite otomatis hilang.

### P1-05. Registry masih mempunyai duplikasi dan alias implisit

Temuan aktif:

- Dua menu aktif menuju /pos/reports/sales:
  Penjualan & Margin POS dan Laporan Penjualan POS.
- Lima menu aktif tidak mempunyai ikon:
  opname divisi bulanan, opname gudang bulanan, daily recon component,
  opening component bulanan, dan opname component bulanan.
- Ada collision sort pada root, POS, finance, loyalty, POS report, asset,
  inventory control, dan menu personal.
- Enam key route terminal/outlet didefinisikan ganda.
- Sepuluh page code controller tidak terdaftar, sebagian memang alias:
  schedules v2, my schedule, stock commit audit, availability, component lot,
  component reconcile, purchase account, division lot, opening, dan warehouse
  lot.
- My schedule menjadi fail-open karena guard hanya bekerja bila page ditemukan.

**Perbaikan:**

- Setiap endpoint memiliki page sendiri atau alias eksplisit di registry.
- Alias tidak boleh bergantung pada “kalau page tidak ada maka lanjut”.
- Tambahkan unique rule konseptual untuk URL kanonis dan sort sibling.
- Gabungkan dua laporan sales menjadi satu workspace bertab.
- Lengkapi ikon dan accessibility label.

### P1-06. Struktur menu perlu disederhanakan berdasarkan pekerjaan user

Usulan susunan utama:

1. Dashboard
2. POS & Customer
3. Purchase & Supplier
4. Inventory & Production
5. Finance
6. People: Attendance & Payroll
7. Asset
8. Reports & Audit
9. Master Data
10. System
11. My Workspace

Prinsip penyederhanaan:

- User operasional melihat pekerjaan harian lebih dulu.
- Halaman setup, rebuild, repair, integrity, dan reclassify masuk System atau
  Diagnostics, bukan berdampingan dengan transaksi harian.
- Satu objek bisnis memakai satu workspace dengan tab, bukan banyak menu yang
  mengulang filter dan tabel serupa.
- Halaman personal tetap terpisah dari halaman administrasi.

Usulan penggabungan:

| Rumpun saat ini | Workspace target |
| --- | --- |
| Live stock, daily matrix, daily recon, monthly stock | Kontrol Stok Harian dengan tab Live, Matrix, Recon, Riwayat |
| Opening manual dan generated | Saldo Awal dengan sumber dan status jelas |
| Component opening, opname, recon, lot | Kontrol Component dengan tab |
| Sales dan sales margin | Penjualan & Margin |
| PH eligibility, ledger, calendar, expiry | Public Holiday dengan tab Hak, Kalender, Saldo, Audit |
| Schedule legacy dan schedules-v2 | Pertahankan V2, arsipkan legacy setelah parity |
| Payroll setup yang tersebar | Setup Payroll |
| Payroll input/approval | Input & Persetujuan |
| Payroll run/disbursement | Proses & Pembayaran |
| Asset master/change/recon/incident | Siklus Aset dengan tab |
| Purchase rebuild/reclassify | System Diagnostics, hak khusus |
| Printer setting dan customer review | Tetap terpisah di sidebar; integrasi QR berada di setting printer |

### P1-07. UI belum memakai design system tunggal

Snapshot statis:

| Pola | Jumlah |
| --- | ---: |
| View PHP | 336 |
| View dengan style inline | 282 |
| View dengan script inline | 167 |
| View memakai Remix icon | 180 |
| File terkait modal | 79 |
| Pemanggilan alert native | 62 |
| Pemanggilan confirm native | 79 |
| Halaman dengan spinner/loading | 61 |
| Table responsive | 154 |
| Empty state | 98 |
| Penanda pagination | 56 |
| Filter dengan clear/reset eksplisit | 9 |

FinanceUI di assets/js/app.js sudah menyediakan alert, confirm, prompt, toast,
dan loading button. Masalahnya adalah adopsinya belum menyeluruh.

Dampak yang dirasakan user:

- Search dan clear filter berbeda antarhalaman.
- Tombol aksi kadang teks, kadang kotak kosong, kadang ikon tanpa tooltip.
- Warna merah dipakai untuk aksi utama maupun bahaya.
- Tab, card, badge, tabel, modal, pagination, dan empty state berbeda.
- Loading dan error tidak selalu terlihat.
- Lebar tabel dan modal tidak konsisten di layar kecil.
- Tampilan satu rumpun terasa dibuat oleh aplikasi berbeda.

**Perbaikan:** bangun Finance Design System, bukan memperbaiki halaman satu per
satu tanpa pola.

Komponen minimum:

- App shell dan page header.
- Breadcrumb dan action bar.
- Primary/secondary/danger button.
- Icon action dengan tooltip dan aria-label.
- Tab utama dan subtab.
- Filter bar responsive dengan apply dan clear.
- KPI/card ringkasan.
- Data table, sticky column, empty state, skeleton, error state.
- Pagination tunggal.
- CRUD modal, drawer, dan confirm dialog.
- Form field, validation, help text, date/currency/quantity input.
- Toast dan job progress.
- Print/preview container.
- Mobile table strategy.

Aturan visual:

- Satu set token warna, typography, radius, spacing, shadow, dan z-index.
- Merah solid hanya untuk brand primary atau danger yang jelas; jangan
  menggunakan satu warna untuk dua arti pada konteks yang sama.
- Ikon harus berasal dari satu keluarga dan selalu terlihat.
- Tombol ikon wajib mempunyai title, aria-label, focus state, dan ukuran klik
  minimum.
- Apply filter dapat berupa ikon search; clear dapat berupa ikon reset, tetapi
  maknanya harus muncul pada tooltip dan pembaca layar.
- Modal create/edit pada satu rumpun memakai susunan dan footer yang sama.
- Tabel memakai header, alignment angka, badge status, action column, dan
  pagination yang sama.
- Halaman tidak boleh menambahkan token warna baru tanpa alasan desain.

### P1-08. Branding dan pengaturan tenant belum terpusat

Ditemukan referensi hardcode Namua, alamat Magnolia/Kabongan, dan identitas
usaha di sekitar 49 file, termasuk login, printer, review, dokumen HR, menu book,
asset, landing, dan roastery.

sys_app_config saat ini lebih banyak berisi backup, dashboard, POS, replication,
dan tunnel. Belum ada profil aplikasi/tenant lengkap.

Pengaturan yang harus dapat dikelola:

- Nama aplikasi dan nama badan usaha.
- Nama outlet, alamat, kontak, domain, timezone, locale, dan mata uang.
- Logo utama, logo dokumen, favicon, watermark, dan warna brand.
- Header/footer dokumen, invoice, receipt, QR, dan kontrak.
- Identitas WhatsApp/email.
- Format nomor transaksi.
- Kebijakan fiskal, service, tax, rounding, dan accounting date.
- Tema UI yang terbatas pada preset aman.
- Feature entitlement sesuai paket.

Jangan menyimpan password, token, private key, atau DB credential di halaman
pengaturan biasa.

### P1-09. Kontrak runtime dan dependency tidak sesuai kode aktual

- composer.json masih menyatakan PHP minimal 5.3.7.
- Source sudah memakai sintaks dan kemampuan PHP 8.
- Composer CLI tidak tersedia pada mesin audit.
- composer.lock tidak menjadi artefak rilis yang dapat direproduksi.
- Python dependency Printer Agent dan service pendamping perlu dikunci.

**Perbaikan:** tetapkan PHP 8.x yang didukung, extension wajib, MariaDB minimum,
Node/browser bila diperlukan, Python version, lockfile, health check installer,
dan compatibility matrix per release.

### P1-10. Test otomatis belum cukup untuk updater

Satu smoke suite inventory dengan sembilan skenario adalah kemajuan dan harus
dipertahankan. Namun belum ada suite otomatis untuk:

- Permission setiap endpoint dan role.
- PO, SR, receipt, fulfillment, reversal, dan cancel.
- Produksi, lot, HPP, rollover, deficit, adjustment, recon, dan void.
- POS order, bundle, extra, DP, payment, refund, void, reservation, dan printer.
- Finance mutation, backdate, reversal, dan reconciliation.
- Attendance, PH, schedule, payroll, dan disbursement.
- Asset lock/change/incident/recon.
- Installer, migration, update, rollback, dan license entitlement.
- Browser regression pada komponen UI utama.

Updater tidak boleh diaktifkan untuk customer sebelum critical path mempunyai
test otomatis.

### P1-11. Repository belum dapat menjadi paket customer

Snapshot working tree:

- Folder backup sekitar 706 MiB.
- Upload sekitar 339,77 MiB.
- Sekitar 837 file menyerupai log masih tracked.
- Sekitar 13 file tmp/fix/probe masih tracked.
- Git pack lokal sekitar 7,66 GiB.
- Ada 12 tabel backup tanpa primary key di schema aktif.
- Collation masih campur: 262 general_ci dan 55 unicode_ci.

**Perbaikan:**

- Pisahkan source, runtime data, upload, backup, log, cache, config, dan secret.
- Buat manifest file yang boleh masuk paket.
- Buat manifest tabel seed awal dan tabel yang harus kosong.
- Pindahkan zz_bak dan backup table keluar schema aktif setelah arsip aman.
- Normalisasi collation secara bertahap.
- Buat cleanup history repository hanya setelah backup dan clone verification.

## 6. Temuan Prioritas 2 dan Utang Historis

### P2-01. Payroll belum menjelaskan uang makan yang dibayar terpisah

Ditemukan 30 detail payroll yang nilai rincian dan net pay berbeda. Selisih
mengikuti meal_total pada contoh yang diperiksa.

Ini belum tentu salah hitung, tetapi UI dan slip harus menjelaskan:

- Gaji bersih payroll.
- Uang makan terpisah.
- Total hak pegawai.
- Total yang sudah dibayar per rekening/tanggal.
- Sisa kewajiban.

Tambahkan assertion agar selisih selain komponen yang memang dibayar terpisah
ditolak.

### P2-02. Riwayat saldo rekening membingungkan pada transaksi backdate

Saldo akhir akun saat ini benar dan chain berdasarkan urutan posting ID tidak
putus. Namun jika diurutkan berdasarkan tanggal bisnis, ditemukan 62
diskontinuitas before/after.

Perbaikan:

- Bedakan tanggal transaksi, waktu posting, dan accounting period.
- Riwayat utama memakai urutan posting untuk running balance.
- Tanggal bisnis tetap dapat difilter, tetapi diberi penjelasan backdate.
- Setelah periode ditutup, backdate membutuhkan reopen/approval.
- Sediakan rebuild saldo yang idempotent dan ter-audit.

### P2-03. Enam jadwal PH lama mendahului eligibility

Fadilla memiliki enam jadwal PH dari April sampai Agustus yang lebih awal dari
effective date eligibility 17 Agustus 2026.

Data ini harus direview sebagai data historis. Jangan mengubah otomatis tanpa
dokumen kebijakan dan bukti hak pada periode tersebut.

### P2-04. Receipt purchase lama belum lengkap

Pada transaksi posted awal Juni masih ditemukan:

- 30 receipt line tanpa material_id.
- 6 receipt line tanpa lot_id.

Writer baru tidak boleh meniru pola ini. Buat audit detail per receipt untuk
menentukan apakah line adalah non-stock, mapping material lama, atau lot yang
hilang. Repair hanya kasus yang dapat dibuktikan.

### P2-05. Status terminal beberapa order POS belum dinormalisasi

Contoh:

- Order id 3469, MSO-20260822114341-AA80, berstatus PAID/PENDING tanpa commit
  dan job.
- Beberapa order VOID masih mempunyai stock_commit_status FAILED atau PENDING,
  termasuk id 3549, 3551, 3553, 3653, dan 3887.

Sebagian dapat merupakan jejak kegagalan server lama, bukan bug writer aktif.
Buat status normalization audit yang membedakan:

- Order selesai secara bisnis.
- Commit stok selesai/reversed.
- Job terminal cancelled/success.
- Data yang benar-benar perlu replay.
- Data yang hanya perlu dinormalisasi status.

### P2-06. Public customer review memerlukan anti-spam

Receipt token sekali pakai adalah fondasi baik, tetapi station QR publik dapat
menerima input tanpa rate limit atau CAPTCHA.

Tambahkan rate limit IP/device, honeypot, cooldown, moderation, duplicate
detection, dan audit member creation.

### P2-07. Availability rebuild log memerlukan retensi

885.829 baris dan sekitar 304,30 MiB menunjukkan log tumbuh terus.

Terapkan:

- Ringkasan harian untuk dashboard.
- Retensi detail terbatas.
- Archive/export bila dibutuhkan audit.
- Index sesuai query.
- Alert hanya pada kegagalan bermakna, bukan setiap event sukses.

### P2-08. Upload dan layanan pendamping memerlukan lifecycle produk

Setiap upload harus mempunyai allowlist MIME, ukuran, random path, antivirus bila
tersedia, access policy, retention, dan delete audit.

Worker, cron, Printer Agent, WhatsApp, tunnel, dan backup harus mempunyai:

- Install/uninstall.
- Start/stop/restart.
- Health check.
- Log rotation.
- Version.
- Recovery.
- Least privilege.
- Dokumentasi Windows dan Linux.

## 7. Target Arsitektur Sidebar, Page, Route, dan RBAC

### 7.1 Satu registry kanonis

Buat satu resolver dengan alur:

1. Route dikenali.
2. Route menunjuk page code.
3. Page menunjuk module dan capability.
4. Role/user memberi action permission.
5. Outlet/division scope dihitung eksplisit.
6. Menu hanya tampil bila can_view.
7. Endpoint mengulangi pemeriksaan action di server.
8. Audit log menyimpan permission dan scope efektif.

Tidak boleh ada tiga definisi terpisah di route, controller, dan sidebar tanpa
validator.

### 7.2 Permission tidak cukup hanya CRUD

Tambahkan capability bisnis bila diperlukan:

- view
- create
- edit
- delete
- export
- approve
- post
- void
- refund
- reopen
- reconcile
- adjust
- reclassify
- rebuild
- manage_settings
- manage_access
- impersonate
- download_sensitive

Jika schema permission belum diperluas, buat action policy layer di atas CRUD
sebagai transisi. Jangan menyamakan edit dengan approve atau post.

### 7.3 Role baseline yang lebih aman

- Superadmin: system dan emergency access, semua aksi tercatat.
- Management: laporan lintas divisi dan approval, bukan writer teknis otomatis.
- Finance: rekening, settlement, reconciliation, payroll payment; bukan recipe.
- Warehouse: PO/SR/receipt/warehouse stock; bukan payroll atau finance account.
- HOD: jadwal dan operasi divisinya; tidak lintas scope.
- Cashier: POS, payment, reservation verification, reprint terbatas.
- Barista/Chef: order produksi divisinya, stock view/recon yang ditugaskan.
- HR: employee, attendance, PH, payroll input sesuai tugas.
- Staff: self service dan tugas eksplisit.
- Auditor: read/export dengan data sensitif yang disamarkan sesuai kebijakan.

### 7.4 Validator otomatis registry

Build harus gagal bila ditemukan:

- Route mutasi tanpa policy.
- Controller page code tidak terdaftar dan tidak menjadi alias eksplisit.
- Menu aktif tanpa ikon atau label.
- Menu action tanpa page_id.
- URL kanonis ganda.
- Sort sibling ganda.
- Page aktif tanpa owner/module/permission baseline.
- Favorite menuju page yang tidak lagi diizinkan.
- Role package drift dari baseline.

## 8. Target Finance Design System

### 8.1 Artefak yang perlu dibuat

- Halaman component gallery internal.
- File token CSS tunggal.
- Library komponen view/JS.
- Panduan penggunaan dan contoh benar/salah.
- Checklist visual QA desktop, tablet, dan mobile.
- Screenshot regression untuk halaman penting.

### 8.2 Pola halaman standar

Urutan halaman:

1. Page header: judul, penjelasan singkat, primary action.
2. Ringkasan/KPI bila memang membantu keputusan.
3. Tab dan subtab bila satu objek mempunyai beberapa sudut pandang.
4. Filter bar.
5. Tabel/card data.
6. Pagination dan total data.
7. Empty/error/loading state.
8. Modal atau drawer untuk CRUD sederhana.
9. Halaman khusus untuk editor kompleks dan live preview.

### 8.3 Migrasi UI bertahap

Urutan yang disarankan:

1. Shared button, icon action, alert, confirm, loading, dan form validation.
2. Filter, table, pagination, empty state.
3. Sidebar, page header, tabs, dan cards.
4. POS dan reservasi.
5. Inventory dan production.
6. Purchase dan finance.
7. Attendance, payroll, asset.
8. Master, reports, system.
9. Hapus style/script duplikat setelah setiap rumpun selesai.

Jangan melakukan big-bang CSS rewrite. Migrasi per rumpun dengan visual
regression agar halaman produksi tidak rusak.

## 9. Pengaturan Sistem untuk Produk Siap Jual

Pisahkan tiga jenis konfigurasi.

### 9.1 Product/build configuration

Dikendalikan vendor:

- Product ID dan release version.
- Schema version.
- Build channel.
- Feature catalog.
- License public key.
- Compatibility matrix.
- Update manifest.

Customer tidak boleh mengedit bagian ini.

### 9.2 Tenant/business configuration

Dapat dikelola customer sesuai izin:

- Profil usaha dan outlet.
- Branding.
- Locale, timezone, currency.
- Format dokumen.
- Kebijakan bisnis.
- Default account dan mapping.
- Printer layout.
- Feature entitlement yang diterima dari license server.

### 9.3 Secret dan machine configuration

Berada di luar source dan database setting biasa:

- DB credential.
- Encryption key.
- License device key.
- API token.
- Printer Agent secret.
- SMTP/WhatsApp secret.
- Backup credential.
- Tunnel credential.

### 9.4 Generator instalasi

Product Control Center terpisah harus dapat menghasilkan:

- Build aplikasi berdasarkan versi.
- Paket fitur berdasarkan entitlement, tanpa fork source.
- Baseline schema.
- Seed referensi wajib.
- Akun bootstrap satu kali.
- Config template.
- Service installer.
- Migration bundle.
- Checksum dan signature.
- SBOM/dependency manifest.
- Backup/restore tool.
- Upgrade dan rollback instructions.

Data yang tidak boleh masuk installer:

- Transaksi Namua.
- Data pegawai/customer/vendor nyata.
- Upload bukti.
- Database dump.
- Log.
- Token.
- Password.
- Device ID.
- Backup.
- Cache.
- PID.

## 10. Roadmap Pengembangan Sampai Siap Jual

### Fase 0. Bekukan baseline dan pisahkan operasi dari source

Target:

- Hentikan auto-push backup ke main.
- Pindahkan backup ke storage terpisah.
- Tetapkan branch/release policy.
- Lengkapi ignore dan package manifest.
- Dokumentasikan runtime dan service.
- Ambil baseline health dan permission.
- Larang penambahan fitur besar kecuali bug produksi.

Lulus bila source main hanya berubah melalui proses developer dan backup restore
sudah diuji dari storage terpisah.

### Fase 1. Tutup seluruh celah RBAC

Target:

- Guard Master dan Master_relation.
- Policy per endpoint Pos_mobile.
- Perbaiki Role_model.
- Rebuild role baseline.
- Perbaiki multi-role scope.
- Guard favorites, pin, dashboard config, rebuild, dan reclassify.
- Validator route-page-menu-permission.
- Negative permission tests.

Lulus bila setiap aksi sensitif ditolak dari direct URL/API untuk role yang
tidak berhak.

### Fase 2. Luruskan nilai component dan observability stok

Target:

- Preview dan repair enam mismatch component.
- Dashboard membaca qty gap dan value gap.
- Tambahkan invariant nilai non-negatif.
- Test production, rollover, FIFO, deficit, reversal, recon, dan adjustment.
- Audit terminal state POS lama.

Lulus bila material dan component sama dengan lot dalam kuantitas dan nilai,
serta dashboard tidak dapat menampilkan clear palsu.

### Fase 3. Sederhanakan navigasi dan bangun design system

Target:

- Hapus runtime menu injection.
- Tetapkan information architecture.
- Gabungkan halaman tumpang tindih.
- Bersihkan URL, route, sort, alias, dan ikon.
- Bangun komponen UI bersama.
- Migrasi tiga rumpun paling sering dipakai lebih dulu.

Lulus bila user role utama dapat menyelesaikan tugas harian tanpa melihat menu
teknis dan pola UI konsisten.

### Fase 4. Perluas automated regression

Target:

- Unit test policy dan service.
- Integration test transaksi utama.
- Database invariant test.
- Browser smoke role-based.
- Visual regression design system.
- Test upgrade dan rollback.
- CI wajib lint, test, registry validation, dan package scan.

Lulus bila build gagal otomatis ketika permission, stok, HPP, saldo, PH,
payroll, atau registry rusak.

### Fase 5. Pusatkan setting, branding, dan tenant profile

Target:

- System Settings terpusat.
- Hilangkan hardcode identitas Namua.
- Media asset manager.
- Template dokumen.
- Config/secret separation.
- Tenant bootstrap wizard.

Lulus bila instalasi baru dapat berganti nama, logo, alamat, warna preset,
outlet, dan dokumen tanpa edit source.

### Fase 6. Migration, installer, updater, dan observability

Target:

- Schema version registry.
- Migration runner.
- Signed release.
- Installer Windows/Linux.
- Service manager.
- Backup/restore.
- Health dashboard.
- Update channel dan rollback.
- Log/metric retention.

Lulus bila instalasi kosong dan upgrade satu versi dapat dilakukan berulang
dengan hasil yang sama.

### Fase 7. Paket fitur, license, dan Product Control Center

Target:

- Satu source untuk seluruh paket.
- Feature flag dikendalikan entitlement.
- Device activation.
- Offline grace period.
- Signed license response.
- Dashboard customer/device/version/health.
- Build generator multi-produk.
- Revocation dan transfer device.
- Audit penggunaan license.

Lulus bila paket murah dan mahal memakai build pipeline sama tanpa fork manual.

### Fase 8. Pilot dan kesiapan penjualan

Target:

- Instalasi pilot non-Namua.
- Uji satu bulan operasional.
- Support playbook.
- SLA dan escalation.
- Dokumentasi user.
- Training role.
- Data migration tool.
- Security review.
- Restore drill.
- Pricing dan kontrak license.

Lulus bila customer pilot dapat install, operate, backup, update, dan rollback
tanpa intervensi edit source.

## 11. Urutan Backlog yang Disarankan

### Minggu pertama

1. Nonaktifkan Git push dari backup.
2. Perbaiki Role_model.
3. Guard endpoint Master dan Master_relation.
4. Petakan policy Pos_mobile.
5. Perbaiki dashboard mismatch component.
6. Buat preview repair enam component.
7. Simpan baseline permission dan navigation registry.

### Gelombang berikutnya

1. Rebuild role matrix dan division scope.
2. Registry validator.
3. Canonical sidebar.
4. Shared filter/table/pagination/modal components.
5. Test POS, purchase, inventory, finance, PH, dan payroll.
6. Central settings dan branding.
7. Migration runner.
8. Installer dan release pipeline.
9. License/control center.
10. Pilot customer.

## 12. Matrix Pengujian Wajib

### RBAC

- Setiap role membuka seluruh menu yang diizinkan.
- Direct URL create/edit/delete/post/void ditolak bila tidak berhak.
- APK memakai policy yang sama.
- Multi-role tidak memperluas scope secara diam-diam.
- Favorite tidak membocorkan menu.
- Permission seed dapat mencabut hak lama.

### POS dan printer

- Draft, confirm, payment, DP, reservation, bundle, extra.
- Void sebelum/sesudah stock commit.
- Refund partial/full.
- Reprint dan tanyakan cetak.
- Printer offline, timeout, duplicate response.
- QR review.
- HPP dan stock commit idempotent.

### Purchase, SR, dan gudang

- PO create/approve/receive/return/cancel.
- SR request/approve/fulfill/partial/reject.
- Lot dan account mutation.
- Reversal dan period close.
- Non-stock line.
- Direct URL role test.

### Inventory dan production

- Material/component receive, transfer, production, sale, void.
- Adjustment plus/minus dan recon.
- Deficit create/settle/write-off.
- Month rollover.
- Qty and value reconciliation.
- Backdate/future/reopen.
- Concurrent writer.

### Finance

- Mutation, payment, reversal, backdate.
- Account balance and chain.
- Daily/gate reconciliation.
- Closed period.
- Export and sensitive field masking.

### Attendance, PH, dan payroll

- Schedule normal/PH/OFF.
- Attendance manual/location/request.
- Eligibility, grant, FIFO use, expiry.
- Monthly day limit and exception approval.
- Payroll input, run, meal payment, disbursement, reversal.
- Employee scope and privacy.

### Asset

- Open data entry.
- Bulk lock.
- Change request.
- Incident, repair, lost, retire.
- Monthly recon.
- Audit history.

### Installer dan update

- Fresh install.
- Seed repeat.
- Upgrade N ke N+1.
- Failed migration rollback.
- Backup and restore.
- License offline/online.
- Feature entitlement.
- Customer data preservation.
- Source package excludes secret, dump, upload, and log.

## 13. Keputusan Arsitektur yang Tidak Boleh Berubah Diam-diam

1. Satu source code untuk seluruh paket.
2. Paket fitur memakai entitlement, bukan source fork manual.
3. Server selalu mengulang pemeriksaan permission.
4. Menu bukan mekanisme keamanan.
5. sys_menu/page registry menjadi sumber navigasi tunggal.
6. Lot/FIFO menjadi sumber kuantitas dan nilai persediaan.
7. Defisit tidak sama dengan mismatch.
8. Repair historis harus preview, preflight, transaksi, audit, dan post-check.
9. Backup tidak boleh berada di repository source.
10. Secret tidak boleh berada di source atau paket customer.
11. Update harus signed, versioned, tested, dan dapat rollback.
12. Branding customer tidak boleh membutuhkan edit source.
13. Data awal installer bukan dump database operasional.
14. Scope multi-role harus eksplisit.
15. Tampilan baru harus memakai design system.

## 14. Definisi Siap Dijual

Finance baru dapat disebut siap dijual bila:

- Seluruh endpoint sensitif deny-by-default.
- Role matrix dan scope lulus negative test.
- Tidak ada mismatch qty/nilai aktif tanpa penjelasan dan workflow.
- Dashboard tidak menyembunyikan anomaly.
- Backup, upload, log, secret, dan runtime data terpisah dari source.
- Fresh install dan update dapat direproduksi.
- Critical path mempunyai automated regression.
- UI rumpun utama konsisten dan responsif.
- Branding dan tenant setting dapat diubah dari pengaturan aman.
- Printer Agent dan service pendamping mempunyai installer serta autentikasi.
- License tidak merusak operasi saat internet sementara putus.
- Restore drill berhasil.
- Pilot customer non-Namua lulus satu siklus operasional dan tutup periode.
- Dokumentasi user, admin, deployment, backup, dan support tersedia.
- Tidak ada kredensial atau data customer di artefak rilis.

## 15. Penutup

Aplikasi ini tidak perlu dipecah menjadi banyak source untuk menjadi produk.
Yang diperlukan adalah mengubahnya dari aplikasi internal yang kaya fitur
menjadi platform yang mempunyai batas akses, sumber data, navigasi, tampilan,
konfigurasi, deployment, dan lifecycle yang tegas.

Prioritas paling aman adalah:

1. Pisahkan backup dari Git dan amankan source.
2. Tutup RBAC endpoint serta role overgrant.
3. Repair nilai component dan perbaiki dashboard.
4. Satukan sidebar dan design system.
5. Bangun test, migration, installer, dan updater.
6. Pusatkan branding/settings.
7. Baru aktifkan paket fitur, license, Product Control Center, dan pilot.

Dengan urutan tersebut, pengembangan tidak hanya menambah fitur, tetapi
menghasilkan produk yang dapat dipasang, diaudit, diperbarui, dipulihkan, dan
dipercaya oleh customer baru.
