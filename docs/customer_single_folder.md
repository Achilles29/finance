# Memasang Finance — panduan singkat customer

Untuk **paket baru alpha.18 / CUSTOMER_CLEAN v7** setelah tersedia di Control. Jangan menambahkan file revisi ini ke ZIP alpha.17 lama. Linux diuji; Windows belum disetujui.

1. **Unduh seluruh ZIP pengiriman** dari Control. Di dalamnya sudah ada folder `finance`, berkas pengiriman, dan `private/delivery/KODE-SETUP.txt`. Tidak perlu membuat berkas pengiriman lagi.
2. **Ekstrak ke folder baru**, bukan menimpa Finance yang sedang dipakai. Minta admin mengarahkan website HTTPS ke **`finance/public`**. Hosting harus menyediakan PHP 8.1, MariaDB 10.11, dan fasilitas scheduler; tidak harus aaPanel.
3. **Admin menyiapkan sekali.** Dari dalam folder `finance`, jalankan:

   ```sh
   sudo sh tools/install/portable/prepare.sh
   ```

   Jika terminal sudah root, hilangkan `sudo`. Ikuti pertanyaan singkat dan setujui ringkasan target. Tunggu “Persiapan berhasil”. Tidak perlu menyalin cron atau menjalankan perintah installer lain.
4. **Buka `https://alamat-finance-anda/setup`.** Masukkan kode setup dari ZIP. Kode akses halaman unduh Control, kode setup, dan password admin Finance adalah **tiga hal berbeda**.
5. **Isi alamat aplikasi dan database:** host, port, nama database kosong, username, password. Jika database belum ada, buat dahulu melalui panel/database manager dan beri akses user ke database itu. Tidak perlu memberikan password root database.
6. Klik **Uji koneksi & database kosong**, isi username/password admin pertama (email boleh kosong), lalu **Periksa ringkasan**. Setelah benar, centang persetujuan dan klik **Pasang dan aktifkan**. Paket/lisensi otomatis mengikuti pengiriman penjual.
7. Tunggu indikator selesai, lalu klik **Masuk ke Finance**. Gunakan akun admin yang baru dibuat. Setup otomatis terkunci dan sinkronisasi terjadwal tetap berjalan.

Pengaturan tersimpan otomatis di `finance/config/customer.json`. Tidak perlu mengedit kode atau mengatur environment PHP-FPM. Jangan membagikan file itu atau kode setup.

**Jika gagal:** baca alasan di layar. Password database salah → perbaiki dan uji lagi; database berisi → pilih database baru, jangan hapus yang lama. Koneksi terputus → **Lanjutkan / periksa status**, bukan mengulang pemasangan. Izin sementara kedaluwarsa → minta pengganti dari penjual; hak membeli/mulai memasang tidak hilang. Admin memasang pengganti yang sah pada folder yang sama, bukan menimpa instalasi atau menghapus data. Jika diminta pemeriksaan admin, kirim kode pemeriksaannya saja, tanpa password.

Detail khusus admin: [panduan admin](customer_single_folder_admin.md).
