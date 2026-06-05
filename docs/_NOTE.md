## INI FILE CATATAN SAYA. ABAIKAN.

Directory finance (C:\xampp\htdocs\finance).
ini adalah pengembangan dan penyempurnaan dari repo core (C:\xampp\htdocs\core). baca README.md dan seluruh dokumen terkait. temukan polanya. dan catat yang perlu dicatat sesuai ketentuan. kita kerjakan secara paralel 
======================


======================= 

skema bonus dan penilaian


===================================
- PENGATURAN TEMPLATE CETAK


======================================================================= 
setelah ini kita mulai ke POS. tapi sebelum itu harus kita siapkan dulu database utama dan database penunjangnya. antara lain:

- member dan promo (poin , voucher, stamp)
- metode pembayaran
- void
- refund
- shift kasir
- produk paket
- pengaturan printer - bisa adopsi dari core, tapi perlu beberapa penyesuaian di seting printer dan tampilan printernya. karena nantinya kita juga akan membangun aplikasi mobile untuk POS nya, jadi seting tampilan printerdiatur di database umum (dipakai baik di desktop maupun mobile), sementar setting printernya berbeda antara desktop dan mobile, namun untuk setting printer mobile kita lakukan nanti saja kalau sudah mau develop mobile
- dan database lain yang dapat kamu baca dari core.

secara umum kita bisa adopsi dari core yang sudah terbukti berjalan, degan beberapa penyesuaian agar lebih efisien namun tetap profesional.

yang perlu diperhatikan di POS ini nanti terhubung dengan stok tersedia berdasarkan resep produk, dan dengan pengaturan yang memungkinkan override produk




================

- Monitor dapur, bar, dan checker seperti surface Pos_order_monitor di core, karena ini masih gap operasional paling nyata.
- Reprint order dan receipt plus histori order yang lebih matang seperti Pos_orders di core, supaya audit kasir tidak hanya bergantung pada modal/detail report saat ini.
- Loyalty native di konteks POS, terutama point, stamp, voucher wallet, dan redeem flow yang benar-benar menyatu ke cashier.
- Mobile API atau customer-display surface seperti Pos_android_api di core, kalau nanti POS Finance mau dipakai di non-desktop.
- Printer routing dan job monitoring parity penuh seperti Pos_printer_routes dan Pos_printer_jobs di core; Finance sudah punya template, profile, device, dan direct print, tetapi belum selengkap board routing dan monitoring job-nya.


v update pegawai
v cetak ulang printer
v catatan order
v update gudang, bahan baku, component
v update data sif
v laporan daily sales seperti core /pos-reports/daily-sales , kemudian cetak 

- update resep extra nasi
- update menu
- update hak akses
- tata ulang sidebar
- tampilan daily matrix
- tampilan halaman stok
- urutan tab masing masing halaman stok
- laporan keuangan
- finalisasi printer
- input kasbon dan hutang
- skema bonus, target harian dan bulanan
- redeem poin dll
- link bahan baku dan component yang digunakan
- estimasi keuangan
- estimasi uang makan
- cek po SR gudang air mineral galon
- catalog purchase jangan cari yang tidak aktif
- resep produk lintas lokasi
- resep component lintas
- POS event
- master bahan baku relasi ke stok
- superadmin kunci tanggal libur dan PH
- skema duplikasi database
- akses stok (ketiganya) belum memperhatikan scope divisi
- metode pembayaran PO tidak bisa diedit padahal belum PAID
- mst_item / mst_material / purchase catalog yang statusnya tidak aktif harusnya tidak muncul di pencarian PO







Kalau kita lanjut lagi ke tema ini, urutan paling sehat menurut saya:

bersihkan reader layer dulu
Purchase_model
Purchase.php
Inventory_tools.php
Master_relation.php
lalu rapikan master/search/UI
jangan lagi jadikan line_kind keputusan aktif di form dan pencarian
baru setelah itu desain migrasi unique key storage
ini langkah besar, tapi baru aman dilakukan kalau read/write layer sudah stabil
Kalau kamu mau, next saya bisa bantu bikin peta yang lebih tajam lagi:

daftar file yang masih hybrid
tandai mana yang writer, reader, dan schema
lalu kita pilih lane mana yang dibersihkan duluan tanpa ganggu operasional.






- perbaiki adjustmen di daily matrix baik gudang, bahan baku divisi , maupun component. alur adj adalah memilih line sesuai tanggal kemudian di klik adj tombol icon pencil. jadi data yang diubah harusnya data profile line tersebut, baik waste ,spol, maupun plus minus. jadi untuk hpp juga semestinya tidak perlu input manual tapi mengikuti hpp profile tersebut. betul kan? beda lagi kalau kita adj manual misal component melalui production/component-adjustments untuk component yang memang sebelumnya belum ada di stok memang kita perlu input harganya. betul tidak logikaku?
perbaiki alur adj melalui daily matrix, jangan langsung tampilkan semua opsi, tapi buat guarding pilih salai satu dulu antara spoil / waste / minus / plus, baru tampilkan alasannya






sekarang saya butuh pengaman database untuk berjaga jaga jika ada listrik mati.
saya butuh 2 skema.
1. backup db dan push berkala ke github ketika ada perubahan data dari tabel tabel tertentu, tabel kunci yang penting untuk melakukan update database. misal ketika ada transaksi.
2. backup berkala di 2 device berbeda, misal 1 device utama yang running di server dengan alaman url tertentu (diatur di halaman pengaturan). 2 device backup di server yang berbeda , atau url yang berbeda, atau di localhost laptop sebagai slave. untuk aplikasinya sama identik, yang membedakan di pengaturan yang 1 di seting sebagai Master yang 1 slave. dan database selalu update. mendeteksi jika salah 1 server down atau disconect. jika server utama down maka user menggunakan server kedua / atau laptop lokal, dan putuskan sementara koneksi keduanya, lalu ketika server utama sudah online baru ada pemindahan data dari server kedua / lokal dengan pengecekan data terlebih dahulu untuk mencegah terjadinya bentrok. setelah itu baru koneksi diaktifkan kembali di pengaturan

ada gambaran?


oke tambahan skema:
SKEMA 1:
1. buatkan folder backup serta folder berisi script terkait (misal cron, service dan lain lain) di directory finance, jangan masukan di luar directory finance, tujuannya agar ketika migrasi nanti file nya ikut semua.
2. buatkan halaman panduan khusus skema yang kita buat
3. untuk SQL Dump ya boleh kalau kamu buat partial, tapi saya tetap ingin setiap berapa menit (misal 30 menit)ada scipt push semua tabel dan data di dump karena ini untuk backup db juga, hanya mungkin dengan catatan ada penghapusan file yang sudah lama, misal data yang tersimpan di folder backup hanya data 3 hari terakhir.
4. tetap pakai github karena saya sudah menjalakan itu dan aman


SKEMA :
1. failover ketika setelah server 1 down jangan otomatis. jadi ketika server 1 down kita tetap pakai server 2, baru setelah server 1 online, kita melakukan pengecekan perbandingan db server 2 dan server 1, jika aman baru di sinkronkan, jika ada perbedaan baru di cek.  karean semua tabel mempunyai kolom id, created_at dan updated_at jadi seharusnya lebih mempermudah kan?
2. perjelas skemanya itu server 1 push data ke server 2 atau sebaliknya? atau bagaimana?


setelah ini langsung bantu implementasi


- sql sudah saya jalankan. hasilnya halaman tidak bisa dibuka 404 semua.
- tambahan catatan : tadi kan kamu bilang alurnya dari server 1 push ke server 2 (CMIIW). nah bagaimana kalau server 2 itu laptop lokal, baik windows ataupun ubuntu, yang tidak punya ip publik dan tidak punya domain. apakah bisa push dari serve 1? atau server 2 yang nge pull? nah saya butuh skema itu juga untuk ditambahkan di opsi