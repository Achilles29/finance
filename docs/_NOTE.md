## INI FILE CATATAN SAYA. ABAIKAN.

Directory finance (C:\xampp\htdocs\finance).
ini adalah pengembangan dan penyempurnaan dari repo core (C:\xampp\htdocs\core). baca README.md dan seluruh dokumen terkait. temukan polanya. dan catat yang perlu dicatat sesuai ketentuan. kita kerjakan secara paralel 
======================
======================


sekarang aku ingin kamu lakukan smoketest untk PO SR dan Gudang.
apakah masih ada miss atau bug atau error pola PO ke gudang, PO ke stok divisi, SR ke stok Divisi, PO dan SR selain ke stok Divis, PO Operasional, service dan lai lain. kalau sudah clear kita lanjut 
======================
======================

Kamu cek dan bandingkan dengan yang ada di core. apa yang masih perlu di kembangkan dari modul PO SR Gudang

======================

kita masuk ke procurement/division-po-sr atau PO/SR Divisi. halaman ini digunakan oleh pegawai masing masing divisi. kemudian Purchase melakukan verifikasi atas pengajuannya, bisa melakukan penyesuaian atas barang yang diajukan. lalu masuk ke database sesuai dengan hasil verifikasi purchase.



=======================
- pencarian produk (create dan edit) saat fallback ke catalog, cukup tampilkan data terupdate saja. karena bisa jadi 1 produk banyak datanya
- procurement/division-po-sr/detail/ jangan tampilkan id profile agar tidak kepanjangan
- /procurement/division-po-sr tambahkan tab per nota dan tab detail rincian







- ralat : revisi tampilan store-requests dan /xpurchase filter default adalah hari ini
- /store-requests sesuaikan lagi tampilan tabel, tidak usah tampilkan yang tidak penting, agar kolom aksi muat tanpa perlu scroll kanan kiri
- verifikasi pengajuan divisi ketika PO masuknya kenapa ke gudang? harusnya ke divisi dan lokasi tujuan sesuai pengajuannya
- SR dan PO jangan kasih keterangan atau note saat verifikasi. bukankah itu nanti jadi profile baru? jadi kalau barang identik jangan tambahkan identitas yang membuat verifikasi SR PO divisi jadi profile baru. paham maksud saya?



====================
- di atas sudah ada Belanja Stok Bar , kitchen, bar event dan kitchen event, operasional ,kenapa dibawha malah kamu tambahi Beban Operasional, inv kitchen inv bar? hapus saja. ganti logika verifikasinya menuju tipe purchase yang sudah ada saja 













========================

buatkan modul penyesuaian stok gudang(waste, spoil dll sesuai konsep yang sudah disiapkan).  yang berpeengaruh terhadap setok gudang dan stok harian gudang.


buatkan modul penyesuaian stok bahan baku (waste, spoil dll sesuai konsep yang sudah disiapkan) . yang berpeengaruh terhadap setok gudang dan stok harian gudang.

