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




/self-order/orders kolom detail belum bisa
/self-order/orders buat bisa di expand masing masing orderan untuk melihat rinciannya, jadi tidak wajib masuk halaman detail
/self-order/orders verifikasi tidak muncul spinner, dan tidak berhasil (tidak terjadi apapun)






- /procurement/division-po-sr/edit/ saat verifikasi muter2 terus (gambar 1)
- saya coba PO selain item, coba listrik, tidak berhasil   menabahkan baris "Catalog ini belum terhubung ke item/bahan master yang valid. Pilih ulang dari hasil pencarian catalog.". jika barang bukan item harunya sembunyikan kolom pemakaian (gambar 3)
- PO setelah di simpan (tapi belum PAID), metode pembayaran tidak bisa diubah. harusnya metode pembayaran tetap bisa diubah, apalagi misal status REVEIVED, barang sudah datang, stok nambah, tapi belum dibayar, saat mau bayar tidak bisa karena dikunci metode pembayarannya.
- tipe purchase selain STOK, semua form nya dikunci selain harga (gambar 6). HARUSNYA tetap bisa diinput walaupun tidak menambah stok bahan gudang atau divisi, agar tau misal kita beli buku itu berapa pcs

perbaiki diatas dulu baru nanti tawarkan lanjut commit



sekarang modifikasi /master/material:

1. perbaiki ukuran icon di kolom sesuai coding standar
2. sembunyikan kolom KODE
3. Tambahkan kolom HPP live setelah hpp standar (cek rumusnya sesuai /inventory-material-daily atau /inventory/stock/division atau dipenggunaan resep)
4. tambahkan divisi (sesuai resep, divisi mana yang menggunakan, jika ada 2 divisi  maka buat atas bawah) lalu tambahkan stok tersedia setelah (stok isi dan (enter) stok pack atau stok beli) sesuai divisinya. jika stok belum tercatat berarti tulis 0
5. tambahkan kategori sebelum nama
6. tambahkan button yang menuju halaman penggunaan, halaman yang menunjukkan component Base / prepare / produk apa saja yang sesuai resep menggunakannya, sekaligus qty nya






- pagination ketika di filter masih belum sempurna. ketika saya filter misal divisi BAR, jumlah baris 25, maka data yang ditampilkan adalah bar dengan data pada halaman pertama awal saja bukan 25 baris
- tambahkan form filter urutan sortir kolom, dengan default kategori (sesuai id) - divisi (sesuai id) - nama
- perbesar ukuran icon seperti pada halaman purchase
- rapikan lagi kolom divisi dan stok. dan upah UOM kedua bukan kode satuan tapi ganti fix jadi PACK, jadi contoh oatmilk ini 6.120,00 MILLILITER , 70,95 PACK . perbaiki juga warning kecil tidak ada resep nya itu, faktanya ada, kemungkinan dia hanya mengambil comopnent, seharusnya produk juga. lalu ganti juga warnanya agar lebih jelas
- sesuaikan data dari hpp live menjadi hiperlink halaman yang menunjukan naik turunya harga barang tersebut berdasarkan data N orderan terakhir. dengan bagian atas menampilkan grafik berdasarkan data yang difilter (jumlah transaksi terakhir, opsional antara hpp atau harga beli), lalu bagian bawah datatabel transaksi x terakhir dari purchase. buatkan dulu halaman globalnya, lalu hiperlinkan per bahan nya





- kok saya ngrasa kurang pas ya kalau price-history diambil dari controller master / material. hehe. harusnya lebih ke purchase tapi menarik data dari mst item
- koreksi juga, data belum tampil, grafik belum tampil
- untuk pemilihan item pakai search ajax dengan preview, bukan dengan dropdown, dan tidka usah tampilkan kode nya










- perbaiki adjustmen di daily matrix baik gudang, bahan baku divisi , maupun component. alur adj adalah memilih line sesuai tanggal kemudian di klik adj tombol icon pencil. jadi data yang diubah harusnya data profile line tersebut, baik waste ,spol, maupun plus minus. jadi untuk hpp juga semestinya tidak perlu input manual tapi mengikuti hpp profile tersebut. betul kan? beda lagi kalau kita adj manual misal component melalui production/component-adjustments untuk component yang memang sebelumnya belum ada di stok memang kita perlu input harganya. betul tidak logikaku?
perbaiki alur adj melalui daily matrix, jangan langsung tampilkan semua opsi, tapi buat guarding pilih salai satu dulu antara spoil / waste / minus / plus, baru tampilkan alasannya