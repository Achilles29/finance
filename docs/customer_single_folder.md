# Memasang Finance — panduan customer satu halaman

Paket baru memakai satu folder **finance**. Hanya subfolder **public** yang menjadi root website. Tidak memerlukan aaPanel. Paket ini memerlukan server yang dikelola admin; tidak semua shared hosting sesuai.

## Sebelum mulai

Minta admin menyediakan PHP 8.1 beserta ekstensi yang diminta pemasang, MariaDB 10.11, database kosong, HTTPS, dan dua akun layanan terpisah. Admin menyiapkan pendamping pemasangan sekali saja. Panduan teknis: `docs/customer_single_folder_admin.md`.

1. Unduh paket dan berkas pengiriman dari Control. Simpan kode setup dengan aman.
2. Ekstrak ke satu folder, misalnya `finance`. Jangan menaruh isinya di instalasi lama.
3. Arahkan root website ke `finance/public`, bukan `finance`.
4. Buka `https://alamat-aplikasi-anda/setup`. Jika muncul “Satu langkah dari administrator”, pendamping belum disiapkan.
5. Masukkan kode setup, host/port/nama/user/password database, URL HTTPS aplikasi, serta username dan password admin pertama. Jangan memilih database yang sudah dipakai aplikasi lain.
6. Klik **Pasang dan aktifkan**. Indikator menunjukkan pemeriksaan aktivasi, database, halaman login, dan pengiriman hasil ke Control. Pendamping berjalan terjadwal; tunggu pembaruan status.
7. Setelah selesai, klik **Masuk ke Finance**. Setup terkunci. Simpan akun admin dan minta admin memastikan jadwal sinkronisasi lisensi/heartbeat tetap berjalan.

Database dan URL disimpan otomatis di `finance/config/customer.json`. Anda tidak perlu mengubah `constants.php`, kode inti, atau environment PHP-FPM. Jangan membagikan file tersebut. Kunci unik dibuat pada server Anda; kunci penandatangan milik Control tidak disertakan.

## Jika belum berhasil

- **Izin kedaluwarsa:** minta izin pengganti dari Control. Ini bukan batas waktu untuk mulai menggunakan pembelian Anda. Admin memasang pengganti tanpa membuang bukti percobaan.
- **Kuota server habis:** periksa jumlah instalasi di Control. Jangan mengganti identitas mesin agar lolos.
- **Database tidak kosong:** gunakan database baru. Pemasang tidak menghapus data Anda.
- **Proses terputus:** klik **Cek status**. Pendamping melanjutkan langkah yang aman; SQL yang hasilnya belum pasti memerlukan pemeriksaan admin. Jangan impor seluruh folder SQL.
- **Database selesai tetapi login belum lolos:** periksa HTTPS dan root `public/`; database tidak perlu diulang.

Status distribusi: kontrak paket alpha.17 / CUSTOMER_CLEAN v6 memerlukan penyesuaian pengiriman Control. Dukungan Windows belum dinyatakan lolos sampai diuji di Windows nyata. Tidak ada aktivasi/publikasi live yang dilakukan oleh pengembangan ini.
