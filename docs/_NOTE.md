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
- db replication belum sinkron

- halaman hutang piutang
- halaman laporan keuangan


- buatkan modul generate stok opname dan stok awal Gudang, divisi, component. siapkan dulu database stok opaname. lalu buatkan modul generate dan tambahkan tombolnya di semua halaman stok (modul harus sama). ketika klik generate maka menggenerate sesuai stok pada montly_stock masing masing sampai dengan profile (line terkecil), lalu menggenerate stok opening untuk bulan berikutnya. untuk stok opening hanya ambil cukup ambil yang stok akhir / stok awal bulan berikutnya tidak sama dengan 0. genertae stok awal berarti menggenerate data di tabel opening dan tabel monthly_stock bulan berikutnya.
dan jangan lupa buatkan halaman stok opname dan masukkan tab bertingkat semua halaman yang serumpun dan masukkan sidebar sesuai rumpun



buatkan halaman untuk menampilkan stok awal hasil generate ini dengan nama yang relevan. masukkan ke database sidebar dan role matrix, serta tab rumpun inventory.  di tab bertingkat taruh setelah mutasi Bahan baku


"Opname fisik harian — pencatatan qty fisik per tanggal dari Daily Recon (halaman /inventory/stock/daily-recon/division). User input manual qty fisik → disimpan ke sini, lalu jika ada selisih dibuat adjustment" memang masih ada seperti ini? bukannya kita langsung ke adjustment?



Gagal: Generate ditolak karena masih ada stok minus. Perbaiki dulu data minus sebelum generate opname.


Gagal: Generate ditolak — 4 profil masih minus. Perbaiki via Adjustment atau Repair dulu. Contoh: AIR MINERAL GALON (BAR · BAR) → -760.0000 (tgl 2026-06-02); KANI STICK (KITCHEN · KITCHEN) → -17.0000 (tgl 2026-06-11); MINYAK IKAN (KITCHEN · KITCHEN) → -55.9980 (tgl 2026-06-14)


kita ambil contoh AIR MINERAL GALON di inv_division_monthly_stock tidak ada stock minus. coba cek



sekarang logika d pos. kita kan membuat skema cutoff bulanan untuk stok divisi dan component.
1. apakah logika lot masing masing sudah sinkron dengan skema ini?
2. apakah pos saat simpan transaksi/void/refund sudah mengintervensi stok bahan baku dan component pada bulan bersangkutan?


1. seharusnya sebelum generate stok awal dipastikan tidak ada miss dan tidak ada commit tertunda, jadi tidak ada  transaksi POS tanggal 31 Mei yang baru di-commit 2 Juni.
2. apakah saat generate stok awal bulan berikutnya logika lot masing masing sudah sinkron dengan stok awal nya?
3. tambahkan guarding poin 1


pindah ke component. lakukan pengecekan