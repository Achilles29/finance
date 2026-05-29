## INI FILE CATATAN SAYA. ABAIKAN.

Directory finance (C:\xampp\htdocs\finance).
ini adalah pengembangan dan penyempurnaan dari repo core (C:\xampp\htdocs\core). baca README.md dan seluruh dokumen terkait. temukan polanya. dan catat yang perlu dicatat sesuai ketentuan. kita kerjakan secara paralel 
======================
======================

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

===============================



DP

sehubungan dengan stock cache yang sudah kita rencanakan, perlu halaman stok live time berdasarkan transaksi yang mempengaruhi stok. petakan dulu yang terpengaruh refractur, jang lupa void  juga berpengaruh. di ui nya sandingkan antara db dan kalkulasi live agar kelhatan ketika ada miss 






- Master Extra belum terhubung dengan resep. kalau kamu lihat di core, extra itu kan juga mengurangi bahan baku. bisa menambah atau menggantikan bahan baku terntu. sumber extra bisa bahan baku, component atau produk lain.

- loyalty/point-rules, loyalty/stamp-campaigns, loyalty/voucher-campaigns, percantik tampilan jangan kaku,  GUNAKAN BAHASA USER. produk wajib pakai pencarian ajax jangan dropdown, tampilkan nama saja tidak usah kode

- Voucher ini kita ada 2 skema, voucher biasa yang diinput untuk bisa dipakai siapapaun, dan voucher promo ketika ada promo transaksi tertentu dapat voucher dan menggenerate voucher ke modul voucher yang 1 nya saat payment. yang sudah dibuat model mana? paham maksude saya kan?


===================
buat ui dan alur bisnis halaman extra seperti di core, lebih jelas alurnya dan terhubung antar modul extra (tambahkan tab penghubung)


pos/cashier hapus saja text : 
"Tengah adalah area kasir utama: cari cepat, filter divisi, lalu tap kartu produk untuk masuk ke keranjang."
"Tutup shift saat transaksi selesai dan sistem akan hitung ringkasan kasir."
"Lanjutkan draft, buka order yang baru confirmed, atau siapkan proses void dan refund."
"Area kanan untuk member, jenis layanan, catatan order, dan keranjang draft yang akan disimpan atau dikonfirmasi."
customer tidak perlu tampilkan kode member, cukup nama dan nomor hp, di preview pencariannya juga
order aktif default nya semua
Bundel taruh di sebelah divisi terakhir saja (setelah eveet)


kalau di core ada skema event, yang juga digunakan untuk penjualan selain FnB, bagaimana menurutmu?

=========================



Jawaban soal skema event di core
Menurut saya itu bagus dan layak kita adopsi, tapi posisinya jangan dibuat sebagai POS terpisah.

Arah yang paling sehat:

event jadi scope penjualan
sejajar dengan regular
bukan modul kasir lain yang berdiri sendiri
engine transaksi tetap satu
member
promo
DP
printer
stok
void/refund
semua tetap satu mesin
yang dibedakan per scope:
sumber katalog
harga/rule promo
routing printer
template receipt/KOT
aturan stok bila perlu
approval flow tertentu
Jadi bentuk yang saya sarankan:

nanti di kasir ada scope order
Regular
Event