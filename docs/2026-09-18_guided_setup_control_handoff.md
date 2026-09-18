# Handoff Control — guided customer setup alpha.18 / v7

18 September 2026, Asia/Jakarta. **IMPLEMENTASI FINANCE; BUKAN IZIN PUBLISH/AKTIVASI LIVE**.
Cutoff final dan hasil acceptance dicatat setelah pengujian selesai di bagian bukti bawah.

## 1. Penyebab alpha.17 dan perubahan produk

Alpha.17 hanya mengetahui apakah `storage/setup/browser.json` sudah dibuat melalui persiapan pendamping. ZIP lengkap saja belum membuat akun terpisah, permission privat, key unik atau jadwal. Pesan tersebut tidak selalu kegagalan lisensi. Jalur lama meminta permission helper, `check`, `prepare`, dan cron manual, sementara panduan masih meminta menyalin delivery yang sekarang sudah ada dalam ZIP Control.

Revisi menyediakan satu entry point Linux, wizard empat tahap, pemeriksaan kesiapan, uji DB metadata-only melalui pendamping, ringkasan, password toggle, progres/resume dan login. Root hanya menyiapkan layanan setelah konfirmasi; website dan installer selalu akun non-root terpisah. Persiapan memasang tiga jadwal dan menunggu bukti scheduler, bukan memanggil worker sendiri lalu mengklaim cron sehat.

## 2. Versi, profil, batas

- Baru: `0.1.0-alpha.18`, `CUSTOMER_CLEAN` **7**, `FINANCE_GUIDED_SETUP_V1`.
- Tetap: layout `FINANCE_SINGLE_FOLDER_V1`, config `FINANCE_CUSTOMER_LOCAL_V1` schema 1, permit `NAMUA_FINANCE_SETUP_V1`, browser bootstrap `FINANCE_SETUP_BROWSER_V1`.
- DB schema `finance-20260907`, baseline `clean-install-20260909`, baseline + 20 migrasi terdaftar **tidak berubah**. Tidak ada SQL baru untuk database development/produksi.
- SHA-256 app-manifest: `7bd0b4936adce76f5179a5dd1851ae70193371e5107fa213494b0e0ead8a479f`.
- SHA-256 customer_clean_profile: `75814d13059c3c448e24f0cfe22e925ff6a8c4814d06ee30cc5a38e1c80e5898`.
- Jangan menimpa alpha.17/v6 atau menyisipkan installer baru ke paket signed lama. Tidak memasang ke core2, menerbitkan deployment, memakai credential live, atau mengubah source/database Control.
- Parent awal repository `7e25749b5a6b7f981fae4427c78846eb19f63c69`; perubahan pengguna di config inti tetap dipertahankan.

## 3. Struktur dan jalur customer

ZIP tetap satu folder `finance/`: `public/`, `application/`, `system/`, `config/`, `storage/`, `private/`, `installer/`, `tools/install/portable/`, docs. Document root **public**, bukan parent. `private/delivery` sudah disertakan Control; customer tidak membuat file claim/permit/credential sendiri.

Admin dari folder hasil ekstrak menjalankan:

```sh
sudo sh tools/install/portable/prepare.sh
```

Perintah meminta akun PHP web jika ambigu, URL opsional, lalu konfirmasi `SIAP`. Default akun pendamping unik per path, dibuat hanya jika disetujui; akun existing harus terpisah dan sesuai grup. Hanya folder instalasi yang disentuh. Parent unsafe ditolak, bukan di-chown rekursif. Tidak menginstal PHP/MariaDB, mengganti vhost/global services atau mengakses DB.

Customer kemudian `/setup` → kode setup dari ZIP → DB/URL/admin → Uji koneksi & database kosong → ringkasan/konfirmasi → Pasang dan aktifkan → progres → login. URL awal ditawarkan dari origin browser HTTPS; authoritative URL tetap isian lokal tervalidasi, bukan domain registry atau HTTP_HOST server. `localhost` ambigu ditolak dengan petunjuk TCP `127.0.0.1`.

Config tersimpan `config/customer.json`; tidak ada pengisian ulang file inti/FPM. Resolver preview, installer, CLI dan aplikasi sama; konflik env/file eksternal lama ditolak. Admin password/DB password tidak masuk argv, URL, log publik, ringkasan, Control, atau paket Git.

## 4. Keamanan dan pemulihan

- Root preflight khusus read-only memeriksa signed release/permit/TAR/inventory sebelum perbaikan ownership; pengecualian ini hanya CLI root, tidak melemahkan CustomerPlatform global. Verifikasi diulang sebagai worker non-root sesudahnya.
- Queue web hanya ciphertext sodium, id unik terikat payload dan permit; publish atomik, maksimum 8 antrean/4 per tick. Probe berhasil terikat digest config+permit selama 15 menit; SQL memeriksa DB kosong lagi di bawah lock.
- Akun web tidak dapat membaca private agent/credential atau menulis code/license cache. Pengarsipan queue membuat inode milik pendamping 0600, bukan chmod inode milik web.
- Root helper idempotent, tiga cron ditandai per folder, jadwal lain dipertahankan. `tick`, `license-sync`, `heartbeat` terpisah; kedua sinkronisasi dibatasi 300 detik masing-masing. Heartbeat tidak mengaktifkan lisensi.
- Setup complete terkunci. Hilangnya local config/context tidak menjadi legacy/development. Signature, core/profile/release, plan/deployment/instance/credential binding, fingerprint, kuota, nonce dan kunci agent tetap diperiksa.
- Izin sementara expired menolak langkah baru, bukan mengakhiri hak memasang. Control menerbitkan pengganti terikat percobaan yang sama. Bukti lama diarsipkan; tidak mengganti identity/key atau DB. Tidak ada endpoint auto-renew baru yang diasumsikan.
- Receipt/health dapat dilanjutkan dari journal yang sama setelah koneksi terputus. `RUNNING` DDL yang belum pasti berhenti untuk review; tidak otomatis DROP/TRUNCATE/ulang SQL. Pengaturan hanya bisa dikoreksi lewat UI sebelum journal SQL ada.
- Pengecualian mutable tetap exact `config/customer.json`, runtime storage/private, serta upload di prefix yang telah ditetapkan sesudah complete. Bukan pengecualian semua kode/folder aplikasi. Jangan menjanjikan PHP di server customer mustahil dibypass.

## 5. Perubahan wajib di Control (belum dilakukan thread Finance)

Audit read-only menemukan `Finance_setup_packet::payload()` saat ini **hardcode profile 6**. `Finance_setup_packet::bundle()` sudah mampu membuat ZIP yang diperlukan dan dipakai langsung dalam fixture tanpa mengubah Control. Permit tetap ditandatangani dengan context yang sama; tidak ada field/signing scheme/endpoints baru.

1. Review cutoff alpha.18, manifest/profile baru, tambahan `setup_contract`, dan pin hash toolchain sebelum mengizinkan v7. Jangan melonggarkan trust secara generik untuk semua versi.
2. Issuer/verifier permit menggunakan profile **dari release terverifikasi yang sama** (v7 untuk alpha.18), bukan konstanta 6. Tetap kompatibel delivery lama v6 menurut kebijakan Control. Credential hash, expiry dan seluruh binding harus persis.
3. Portal/delivery text `MULAI-DI-SINI.html` dan petunjuk unduh mengganti prosedur manual dengan satu perintah + `/setup`; pertahankan `KODE-SETUP.txt` dan enam berkas delivery. Kode akses pengiriman, setup dan admin password harus dibedakan.
4. Regresi reissue menggunakan workflow Control yang sudah ada, tidak menimpa ZIP ke instalasi parsial. UI Control harus menjelaskan berkas izin/credential pengganti yang tepat. Jangan membuat deadline berdasarkan pembelian/build/release/instance.
5. Dari checkout cutoff bersih, scan/import metadata baru, build **release baru**, jalankan delapan gate/verifier pinned serta ZIP actual; lakukan walkthrough customer dan tes real-Control di lingkungan trial sesuai persetujuan. Jangan publish otomatis dari thread Finance.

Endpoints lama tidak berubah: `/api/v1/license-activations`, `/status` dan `/recover` di bawah prefix yang sama, `/api/v1/deployment-receipts`, `/api/v1/heartbeats`. Tidak ada customer DB/password atau transaksi dikirim.

## 6. File/dependensi yang perlu direview

Runtime berubah: `CustomerLocalConfig`, `DeploymentConfig`, `Control_license_cache`, `ControlDelivery`, `CustomerReleaseProfile`, `PortablePackage/Installer/Database/Store`, `SetupUi`, `finance_setup.php`, `setup.php`, `layout.json`, manifest/profil. Baru dan **allowlisted**: `LinuxPreparation.php`, `SetupService.php`, `prepare.php`, `prepare.sh`, `setup.js`, `setup.css`. Customer/admin docs ikut paket.

Test baru `customer_guided_contract_smoke.php`, `customer_guided_acceptance.php`, `customer_guided_browser.cjs`, `customer_guided_control_fixture.php`; fixture/test issuer **tidak ikut paket**. Portable/config/clean-release dan quality-gate tests disesuaikan; guided/portable contract masuk gate required.

Tidak mengganti dependency/lockfile/global runtime. Runtime lokal: PHP CLI/FPM 8.1.32, MariaDB 10.11.10, nginx, sodium/cURL/PDO, shell POSIX, runuser, useradd, crontab. Harness tambahan: BusyBox crond dengan spool root-owned terisolasi, OpenSSL/TAR/unzip, Google Chrome 152, Node 20 melalui CDP pipe tanpa paket npm tambahan, akun existing `namua-build` dan `www`; lokasi binary dalam harness adalah spesifik lab, bukan hardcode paket customer. PHP 8.4 hanya adapter build, bukan runtime web.

## 7. Bukti dan keterbatasan

Putaran working-tree 18 September 2026, 19:31 WIB: **47 guided acceptance PASS**, **61 portable acceptance PASS**. Build awal delapan gate + verifier independen PASS: 1.170 file, 306 tabel, 20 migrasi, 735 referensi, 0 customer/dummy/secret; backup–restore checksum cocok. Angka awal ini bukan bukti cutoff final; pengulangan checkout bersih dicatat tersendiri di bawah. Bedakan tiga lapisan:

- Uji source/fixture fungsi dan config, tidak membuktikan layanan OS atau Control live.
- Linux disposable: ZIP dari class bundler Control aktual, scheduler benar-benar berjalan, nginx/FPM/DB/Chrome nyata; issuer/HTTP Control **sintetis**, bukan aktivasi/publish live.
- Release trial di Control dan customer asli **belum dilakukan**. Windows nyata/Apache/IIS belum acceptance. Pembuatan akun OS baru dan crontab sistem host tidak diuji dengan mengubah host bersama; fixture memakai akun existing dan scheduler terisolasi. Walkthrough manusia nonprogrammer masih diperlukan.

Perintah lab (bukan perintah untuk server customer/produksi):

```sh
php tools/tests/customer_guided_contract_smoke.php
php tools/tests/customer_local_config_smoke.php
php tools/tests/customer_portable_contract_smoke.php
php tools/tests/customer_portable_acceptance.php --disposable
php tools/tests/customer_guided_acceptance.php --disposable
php tools/tests/c3_customer_clean_release_smoke.php
php tools/tests/c3_control_build_runtime_smoke.php --isolated
```

Jangan menandai “siap praktik pada alpha.17”. Finance v7 perlu review/build pengiriman Control baru, tanpa langkah terminal tersembunyi dalam jalur normal.
