# Integrasi notifikasi modul — 23 September 2026

## Untuk pengguna

1. Buka **WhatsApp → Pengaturan** atau **Telegram → Pengaturan**. Panel **Notifikasi dari modul Finance** berada di atas pengaturan koneksi bot.
2. Daftarkan grup WA melalui menu Grup WA; daftarkan chat pribadi/grup Telegram melalui daftar tujuan/Asisten Setup. Bot harus sudah terhubung. Master switch Telegram harus aktif.
3. Aktifkan **Self order masuk**, **Order online masuk**, dan/atau **Pengajuan PO / SR divisi**. Pilih penerima untuk masing-masing kejadian, lalu simpan. Maksimal 10 tujuan per kejadian. Awalnya semuanya nonaktif; tidak memakai grup internal coding secara otomatis.
4. Self order/online order baru diperiksa oleh jadwal bot, umumnya setiap menit. Tidak perlu membuka kasir. Pesan berisi nomor, outlet/meja, waktu, status, total, ringkasan item, dan tautan aplikasi yang tetap memerlukan login. Pesan bukan bukti pembayaran. Order lama sebelum aktivasi tidak dikirim; mengganti tujuan atau mengaktifkan ulang juga tidak menyiarkan riwayat lama.
5. Setelah divisi menyimpan pengajuan, halaman detail menampilkan **Kirim WA / Kirim Telegram** sesuai kanal aktif. Tombol juga tersedia pada daftar dokumen pengajuan. Klik untuk mengantrekan ringkasan pengajuan ke tujuan yang sudah diatur admin. Tidak membuka pilihan nomor bebas kepada pengaju. Pengajuan VOID/REJECTED/DRAFT tidak dapat dikirim.
6. Buka **Status 30 notifikasi terakhir** pada pengaturan kanal. `Menunggu jadwal bot` bukan berarti terkirim. `Gagal` dapat dicoba kembali oleh pengelola pengaturan; `Belum pasti` tidak dicoba ulang otomatis karena pesan mungkin sudah diterima. Periksa chat tujuan terlebih dahulu.

**Batas WA pribadi:** aplikasi lama sengaja mengunci pengiriman WA personal untuk melindungi akun yang dibatasi (`Whatsapp::PERSONAL_OUTBOUND_ENABLED=false`), dan engine memiliki pengunci sendiri. Integrasi tidak melewati keduanya. Nomor dapat didaftarkan saat integrasi nonaktif, tetapi aktivasi dengan nomor ditolak secara jelas. Grup WA dan chat pribadi/grup Telegram didukung. Pembukaan WA personal/kanal resmi merupakan keputusan terpisah, bukan mengganti credential atau env diam-diam.

## Pemasangan / administrator

- Migrasi baru: `sql/2026-09-23a_module_notifications.sql`, klasifikasi schema, kebijakan **clean_install + upgrade**, bergantung pada foundation Telegram. Membuat dua tabel InnoDB kosong: `app_notification_rule`, `app_notification_queue`. Tidak membawa seed penerima, pesan, nomor, identitas usaha, atau credential.
- Status database aktif: **BELUM DIJALANKAN** pada saat implementasi/uji; konfirmasi operator diperlukan. Pengujian hanya menggunakan MariaDB disposable. Halaman bot lama tetap berfungsi tanpa migrasi dan menjelaskan migrasi yang diperlukan.
- Untuk database staging yang sudah dipastikan bernama `db_finance`, admin dapat menjalankan berikut (password diminta interaktif, tidak diletakkan dalam command):

```sh
mysql -u root -p db_finance < /www/wwwroot/finance/sql/2026-09-23a_module_notifications.sql
```

- Untuk customer gunakan jalur migrasi resmi upgrade/clean-install, bukan import seluruh folder SQL atau clean installer ulang.
- Memakai worker **yang sudah ada**: `php index.php whatsapp api_schedule_run` dan `php index.php telegram run_due` (`telegram process_queue` juga memproses integrasi). Tidak menambah cron ganda bila jadwal tersebut sudah berjalan. Jika belum, admin menjadwalkan tiap menit dengan PHP/runtime/user layanan Finance yang sesuai pada panduan bot. Thread ini tidak mengubah cron atau layanan host.
- Pemeriksaan read-only root crontab, `/etc/cron.d`, `/var/spool/cron`, dan `/www/server/cron` pada 23 September **tidak menemukan referensi langsung** worker WA/Telegram tersebut. Wrapper/jadwal eksternal belum diverifikasi. Karena itu aktivasi live perlu memastikan jadwal worker; bukan cukup apply SQL lalu menganggap pengiriman otomatis sudah berjalan. Cron internal bridge coding bukan pengganti worker aplikasi.
- Waktu worker terakhir tampil di panel; belum terdeteksi / lebih dari 5 menit ditandai peringatan. Telegram master switch OFF menghentikan pengiriman, bukan menghapus antrean/bukti.
- Tidak ada perubahan website publik, session APK, stok, transaksi, saldo, credential, Telegram bridge, atau Control. Tidak ada push/publish/deploy.

## Kontrak dan pengamanan

- Source order: `pos_order.order_channel=SELF_ORDER` atau `DELIVERY`, dibaca dari database aplikasi seperti reader POS saat ini. Tidak mengubah aplikasi member terpisah. Polling hanya membaca order tersimpan yang mempunyai line aktif; bukan jalur simpan/pembayaran.
- Switch per channel/per event dan pemeriksaan penerima aktif dilakukan kembali di server. Penerima yang JID/chat ID-nya diganti tidak menerima payload lama. Menonaktifkan integrasi tidak dapat menarik pesan yang sudah terkirim.
- `AUTOMATION_MESSAGING` dan hak modul asal (`SELF_ORDER`, `ONLINE_ORDER`, `PROCUREMENT`) diperiksa; upgrade/downgrade lisensi tidak dilewati oleh SUPERADMIN. RBAC/settings edit dan CSRF existing tetap digunakan. Endpoint kirim memeriksa scope divisi sebelum membaca item pengajuan. Pengguna hanya punya view tidak mendapat kewenangan konfigurasi.
- Unique delivery key mencegah pengiriman ganda akibat polling/klik ulang. Revisi ringkasan pengajuan membentuk key baru. Worker menggunakan advisory lock per database/kanal; save settings menolak jika pengiriman sedang berjalan. Proses yang terputus setelah claim ditandai UNKNOWN, bukan langsung diulang.
- Tidak menjanjikan exactly-once pada jaringan eksternal. Timeout WA/Telegram tetap ambigu. Retry otomatis UNKNOWN sengaja dilarang. Hanya penolakan pasti boleh diproses ulang dari UI. Tidak menyalin error provider/secret mentah ke log.
- WA pribadi belum dibuka. Rate limit provider, liveness cron host, dan penerimaan oleh bot/grup nyata masih memerlukan UAT operator. Latensi bukan real-time push; normal mengikuti interval jadwal dan jumlah antrean (maksimal 10 delivery per run).

## Checklist uji manual

- [ ] Sesudah migrasi, pengaturan tampil normal; semua integrasi default OFF.
- [ ] Daftarkan/pilih tujuan uji milik sendiri dan hidupkan hanya kanal yang ingin diuji.
- [ ] Buat self order baru dan order online baru; nomor/item/outlet benar, pesan sekali per tujuan. Order kasir biasa dan order lama tidak ikut dikirim.
- [ ] Kirim pengajuan dari divisi yang sesuai melalui daftar/detail. Klik ulang tidak menggandakan; revisi pengajuan dapat dikirim lagi.
- [ ] Akun divisi lain tidak bisa mengirim pengajuan di luar scope melalui URL/API.
- [ ] Matikan switch, nonaktifkan target, atau batalkan pengajuan sebelum worker berjalan: tidak terkirim.
- [ ] Putuskan koneksi bot: pengajuan/order tetap tersimpan. Periksa status gagal/ambigu; jangan menyimpulkan antrean berarti terkirim.
- [ ] Pastikan jadwal worker terakhir terus diperbarui dan penerimaan nyata di chat sesuai.

## Bukti validasi / paket

- `module_notifications_smoke.php --disposable`: 51 pemeriksaan lulus, CI query builder dan **MariaDB 10.11.10 nyata** dengan baseline asli, termasuk penolakan schema tanpa unique index deduplikasi. Pengiriman dan entitlement pada fixture sintetis; bukan pengiriman bot asli. Dua tabel bisnis order/line serta pengajuan mempertahankan checksum setelah worker berjalan. Bukti akhir: `/var/lib/finance-notification-test-1e9abdfccf46a15a` (database sementara dihentikan).
- `module_notifications_ui_smoke.php`: 50 pemeriksaan HTML/DOM; client JS 15 pemeriksaan dengan network/DOM sintetis. Pengamanan CSRF/role dan daftar route juga diuji regresi yang sudah ada.
- SQL didaftarkan dengan checksum dalam katalog, bukan pengecualian legacy. File library/model/view/JS/panduan ditambahkan ke allowlist customer, SQL ke `sql_sha256`; hash rules pada manifest diperbarui. Kontrak layout/profil tidak diubah: source kandidat tetap alpha.23 / CUSTOMER_CLEAN v11, dengan **cutoff/hash baru yang perlu direview Control**. Bukan perubahan artefak terbit.
- Belum commit/push; parent source saat mulai `077a35fc873b60880cce78196f822c38781c9bbb`. Tidak mengubah commit installer lokal atau konfigurasi `database.php`/`.user.ini`.
- Build resmi, review cutoff Control dan UAT penerimaan chat bukan bagian bukti sintetis ini.
- Quality gate `parallel`: **136/136 PASS** (130 required, 4 development, 1 release-contract, 1 preflight), exit 0. Pemeriksaan runtime/browser seluruh aplikasi, security dependency dan static release tidak dijalankan oleh profil parallel; ini bukan klaim full release-ready. Build terkurasi sintetis pada tes `c3_customer_clean_release_smoke.php` lulus termasuk allowlist/integritas dan penolakan kontaminasi; build resmi Control tidak dijalankan.
- PHP lint seluruh 32 file PHP baru/berubah dan `git diff --check` lulus. Tidak ada perubahan `application/config/database.php` atau `.user.ini`. Composer/dependency tidak diubah.
- `managed_migration_database_smoke.php --disposable`: PASS/exit 0 pada MariaDB 10.11.10, mencakup baseline + katalog baru, upgrade, replay, adopsi schema lama, serta penolakan drift/partial tanpa mutasi data aktif.

Hash untuk review cutoff (working tree, belum commit):

| File | SHA-256 |
|---|---|
| `app-manifest.json` | `a0acb6e5c5c84a9070eadc59e154b3fde69aaaf6fa6945f785c189fa8617c398` |
| `tools/release/customer_clean_profile.json` | `d16d7ee76a9ff2db2b72047f6a62d060950c37a07601fba62560364b477f2e38` |
| `tools/db/migration_catalog.json` | `ea73ff711b8a47943da0d4869635dd657ebd33bcf07b5679cc470a4fb74eca8c` |
| `sql/2026-09-23a_module_notifications.sql` | `4eda52e6ade8be12aeb22a8a3036f3084588394f48acaadb59fab4499639cd7f` |
