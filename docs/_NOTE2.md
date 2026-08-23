## INI FILE CATATAN SAYA. ABAIKAN.

Directory finance (C:\xampp\htdocs\finance).
ini adalah pengembangan dan penyempurnaan dari repo core (C:\xampp\htdocs\core). baca README.md dan seluruh dokumen terkait. temukan polanya. dan catat yang perlu dicatat sesuai ketentuan. kita kerjakan secara paralel 
===============================
<!-- 4. Loyalty ke kasir
Setelah payment stabil, baru sambungkan:

redeem voucher
earn point
earn stamp
issue voucher -->
<!-- Kenapa belakangan:

loyalty paling sensitif ke aturan bisnis
kalau dipasang sebelum payment mapan, biasanya kita bolak-balik
Jawaban singkatnya
Kalau ditanya “mulai dari mana dulu?”:

Extra
Kasir baca cache DB
Payment + DP
Loyalty
Kenapa bukan loyalty dulu
Karena loyalty lebih aman dipasang di atas transaksi yang sudah matang.
Sedangkan extra dan cache availability itu fondasi langsung untuk input order kasir.

Rekomendasi saya
Saya sarankan kita lanjut persis ini:

finalkan extra di kasir
sambungkan availability cache DB ke katalog kasir
lanjut payment/DP
baru loyalty
Kalau kamu setuju, saya mulai dari:

klik produk di kasir -> modal/panel extra
cart menghitung extra
produk di kasir baca availability dari cache DB/override operasional -->
=========================


3. Payment + DP
Sebelum loyalty, kita butuh payment flow stabil dulu:
bayar penuh
DP
pelunasan
split nanti kalau perlu
receipt print
Kenapa sebelum loyalty:
point/stamp/voucher idealnya nempel di momen payment final
DP juga perlu aturan jelas: earn sekarang atau saat lunas

apakah cetak printer sudah benar2 sesuai dengan pengaturan printer? preview, ukuran, tampilan dll

extra!
- Perbaiki input grup extra
- perbaiki add master extra add
- cek list produk extra ke grup extra dari grup
- cek list produk ke extra grup dari grup 
- extra yang bahan baku 


halaman reservasi dengan DP yang terambung ke keuangan
cost produksi
belanja untuk bahan baku
laporan penggunaan bahan baku , batch produksi, spoil waste dan lainnya, pos

kejutkan saya
- halaman rekon keuangan
- halaman generate qris,  dari pos, muncul ke halaman khusus qris 

  buat halaman cost berdasarkan stok component, bukan resep, karena beda, kalau ini untuk cost produk
- finalisasi printer
- POS event

  cron untuk semua halaman yang butuh cron. kamu cek semua
  
  cek semua halaman yang dobel
