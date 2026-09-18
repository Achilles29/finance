# Admin: paket satu folder / FINANCE_SINGLE_FOLDER_V1

## Batas dukungan

Target PHP 8.1 (64 bit), MariaDB 10.11, ekstensi PDO MySQL, mysqli, sodium, curl, mbstring, openssl, ZIP, XML, session, JSON. `fileinfo` mengikuti kontrak runtime sebagai dependensi fitur MIME WhatsApp; pasang sebelum mengaktifkan fitur tersebut. Linux x86-64; adaptor Windows x64 memakai MachineGuid, ACL dan Task Scheduler, **masih memerlukan acceptance di Windows nyata**. HTTPS wajib. Tidak bergantung pada panel hosting. Subdirektori URL belum didukung.

```text
finance/
  public/       # satu-satunya document root; index.php, assets, uploads
  application/  # kode aplikasi, bukan document root
  system/
  config/customer.json
  storage/      # cache, log, session; license cache read-only untuk web
  private/      # agent key, izin Control, journal, credential: tidak terbaca web
  installer/layout.json
  tools/install/portable/
```

Pemetaan terjadi saat build. Struktur sumber/master tidak dipindahkan. Tidak ada data development atau private key Control di arsip kode. Release dan profil lama tidak ditimpa.

## Linux: persiapan satu kali

Gunakan dua akun OS yang sudah disediakan administrator: contoh `finance-installer` (pemilik paket dan tugas pendamping), `finance-web` (PHP-FPM). Keduanya bukan root. Direktori induk harus tidak dapat ditulis akun lain; jangan memakai `/tmp`. Jangan menjalankan PHP web sebagai pemilik kode.

Untuk **folder paket baru saja**, administrator menjalankan:

```sh
sh /srv/finance/tools/install/portable/prepare-linux.sh /srv/finance finance-installer finance-web finance-web
```

Script hanya menata izin folder paket yang dipilih. Tidak membuat akun, mengedit layanan global, atau mengakses database. `storage/inbox` adalah pengecualian tulis grup yang sempit: hanya pesan terenkripsi, tidak dieksekusi sebagai PHP. Private agent berada di `private/agent` (0700/0600); konfigurasi/cache publik lisensi 0640. Jangan chmod 777.

Atur nginx sesuai `tools/install/portable/nginx.conf.example`, atau Apache dengan document root `public/`, `AllowOverride` sesuai `.htaccess`, dan PHP yang tidak menjalankan skrip upload. IIS menggunakan `public/web.config` dengan URL Rewrite. Template bukan pengganti validasi konfigurasi virtual host lokal. Tidak boleh membuat alias ke `config`, `storage`, `private`, atau seluruh folder induk.

Salin berkas pengiriman **terverifikasi dari Control** ke `private/delivery/`: `package.tar` (TAR kode asli), `release.json`, `release.sig.json`, `release-trust.json` (public key diverifikasi lewat jalur admin), `permit.json`, dan `credentials.json`. Semuanya 0600. Bukti TAR tetap berada di folder Finance yang sama, bukan lokasi eksternal.

Control harus menerbitkan envelope izin setup baru; jangan membuat sendiri, menggunakan signing key fixture/test, atau mengaku endpoint penerbitan otomatis sudah ada. Lihat handoff versi ini.

Jalankan sebagai pemilik paket (bukan root):

```sh
php /srv/finance/tools/install/portable/finance_setup.php check
php /srv/finance/tools/install/portable/finance_setup.php prepare
```

Pasang dua jadwal pada crontab akun pendamping, sesuaikan lokasi PHP:

```cron
* * * * * /usr/bin/php /srv/finance/tools/install/portable/finance_setup.php run >/dev/null 2>&1
*/5 * * * * /usr/bin/php /srv/finance/tools/install/portable/finance_setup.php sync >/dev/null 2>&1
```

`run` memasang dan menutup setup. `sync` meminta lease lisensi bertanda tangan lalu mengirim heartbeat monitoring secara terpisah. Heartbeat bukan aktivasi dan tidak membuka lisensi. Journal/status tersimpan tanpa password pada respons UI/terminal. Pantau status ack pada `private/heartbeat.json`; scheduler gagal tidak boleh dianggap berhasil otomatis.

## Windows: persiapan satu kali (belum acceptance)

Siapkan akun lokal non-Administrator terpisah untuk pendamping dan aplikasi IIS/Apache, PHP CLI yang sama dengan versi web, dan HTTPS. Jalankan `prepare-windows.ps1 -Root C:\Finance -InstallerAccount ... -WebAccount ...` sekali sebagai administrator untuk ACL. Sesudahnya jalankan `php C:\Finance\tools\install\portable\finance_setup.php prepare` menggunakan akun pendamping **non-admin**. `schedule-windows.ps1` mendaftarkan tugas dengan RunLevel Limited; password akun diminta secara interaktif, bukan ditulis di konfigurasi.

ACL/reparse point/hardlink diperiksa melalui PowerShell; pemeriksaan gagal harus memblokir, bukan dilewati dengan chmod. Jangan menganggap Windows sudah didukung hanya karena PHP dapat mem-parsing script. Uji nyata wajib: IIS FastCGI, URL Rewrite, PHP sodium, ACL lintas akun, MachineGuid stabil, scheduler non-interaktif, atomic replace dan file locking.

## Konfigurasi dan pemulihan

`config/customer.json` menggunakan schema 1, sama seperti template, tetapi runtime untuk layout v6 harus `storage`. Nilai environment/file eksternal yang bertentangan tetap ditolak; tidak ada fallback diam-diam ke database staging. Tidak mengubah credential produksi.

Sebelum SQL: koneksi, versi DB, database kosong, signature, inventory, hash TAR/manifest/profil, izin deployment, identitas instalasi dan aktivasi harus lolos. Hanya baseline + migrasi `clean_install` yang terdaftar dijalankan. SQL dijalankan melalui PDO, bukan import semua folder.

Journal `private/database.json`: `READY` dapat dilanjutkan pada batas langkah selesai; ledger setiap migrasi dibandingkan checksum; `RUNNING` berarti hasil DDL belum pasti dan berhenti untuk review. `COMPLETE` dapat di-health-check dan mengulang pengiriman receipt, tidak mengulang SQL. Jangan menghapus journal atau mengganti DB sesudah attempt SQL. Tidak ada rollback DROP otomatis.

Jika salah input sebelum SQL dimulai, admin menjalankan `php tools/install/portable/finance_setup.php retry-input` sebagai akun pendamping. Kiriman sebelumnya diarsipkan, identitas lisensi tidak direset, lalu formulir boleh dikirim lagi. Perintah ditolak begitu journal database ada. Jangan menebak ulang password atau menyimpan credential pada argumen shell.

Izin setup pengganti harus terikat instance/deployment/plan/release/cutoff/profil yang sama. Arsip percobaan lama tetap disimpan. Penerbitan token baru dilakukan operator Control, bukan endpoint yang dikarang installer. Aktivasi timeout memakai recovery beridentitas sama; kuota ditolak tidak mengubah instalasi server lain.

Pengamanan PHP di mesin yang sepenuhnya dikuasai customer tidak dapat dijanjikan mustahil dibypass. Model ini menjaga signature, integritas terverifikasi, aktivasi dan kuota dengan trust boundary yang jelas, bukan klaim DRM absolut.
