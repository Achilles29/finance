# Notifikasi WhatsApp dan Telegram

1. Daftarkan grup WhatsApp di **Grup WA**, atau chat pribadi/grup Telegram melalui **Telegram → Asisten Setup / Tujuan**. Hubungkan bot terlebih dahulu; master switch Telegram harus aktif.
2. Buka **WA → Pengaturan → tab Notifikasi** (atau pengaturan Telegram). Pada **Notifikasi dari modul Finance**, aktifkan kejadian, centang satu atau beberapa grup penerima, lalu simpan. Pilihan setiap modul terpisah. Semua integrasi awalnya nonaktif. Maksimal 10 tujuan per kejadian.
3. **Self order / order online** baru dikirim otomatis oleh jadwal bot. Tidak perlu membuka kasir. Normalnya diperiksa tiap menit; banyaknya antrean dapat menambah waktu. Order lama sebelum aktivasi tidak dikirim. Mengganti tujuan / mengaktifkan ulang tidak menyiarkan ulang order lama.
4. **Pengajuan PO/SR divisi**: simpan pengajuan, lalu gunakan **Kirim WA / Kirim Telegram** di daftar atau detail. Penerima mengikuti pengaturan admin. Klik ulang untuk isi yang sama tidak menggandakan kiriman; revisi pengajuan dapat dibagikan kembali.
5. Periksa **Status 30 notifikasi terakhir** di pengaturan. *Menunggu* berarti belum terkirim; *Gagal* dapat dicoba kembali; *Belum pasti* perlu diperiksa di chat tujuan dan tidak dicoba ulang otomatis.

Notifikasi berisi ringkasan dan tautan aplikasi yang tetap membutuhkan login, bukan bukti pembayaran. Hak pengguna dan lisensi modul tetap berlaku. Mematikan integrasi tidak menarik pesan yang sudah terkirim.

**Grup WA aktif/nonaktif:** status di menu Grup WA mengatur bot membalas chat masuk, bukan penerima notifikasi modul. Semua grup terdaftar ditampilkan pada checklist notifikasi; grup nonaktif tetap dapat menerima jika dicentang dan modul diaktifkan. Hapus centang atau matikan modul untuk menghentikan notifikasi. Grup tanpa ID valid tetap terlihat dengan keterangan perbaikan, tetapi belum bisa dipilih. Bot tetap harus menjadi anggota grup dan dapat mengirim pesan.

**Tab pengaturan WA:** Notifikasi untuk penerima/modul/riwayat; Koneksi & QR untuk menyambungkan akun; Pengujian untuk cek koneksi; Teknis & pemulihan untuk admin mengelola proses bot. Tab yang terakhir dibuka dipertahankan setelah menyimpan.

**WA pribadi:** pengiriman pribadi masih dikunci oleh perlindungan akun yang sudah ada. Gunakan grup WA, atau chat pribadi/grup Telegram. Nomor WA dapat disimpan ketika integrasi nonaktif, tetapi belum dapat diaktifkan untuk kirim pribadi. Jangan melewati pengunci aplikasi/engine atau mengganti credential sendiri.

## Jika belum terkirim

- WA: pastikan grup dicentang dan modul notifikasi aktif; tidak perlu mengaktifkan balasan chat grup. Telegram: tujuan dan master switch harus aktif. Pastikan bot terhubung dan izin mengirim di grup sesuai.
- Lihat indikator *Jadwal bot terakhir berjalan*. Bila belum terdeteksi atau sudah lama, minta admin memeriksa worker WA / Telegram sesuai panduan kanal.
- Admin: integrasi memakai worker existing `php index.php whatsapp api_schedule_run` dan `php index.php telegram run_due`, biasanya tiap menit. Tidak perlu jadwal duplikat. Gunakan PHP dan akun layanan aplikasi yang benar.
- Jika panel menyebut migrasi belum terpasang, jalankan migrasi resmi `2026-09-23a_module_notifications.sql` pada target instalasi yang tepat. Jangan mengimpor semua SQL, mereset data, atau mengulang clean installer.
- Jangan menyimpulkan pesan diterima hanya karena masuk antrean. Untuk hasil ambigu, periksa chat agar tidak mengirim duplikat.
