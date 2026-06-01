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
- error pengajuan PO SR
- update menu
- update gudang, bahan baku, component
- update hak akses
- update data sif
- tata ulang sidebar
- tampilan daily matrix
- tampilan halaman stok
- urutan tab masing masing halaman stok
- laporan keuangan
- finalisasi printer
- input kasbon dan hutang
- skema bonus, target harian dan bulanan




cetak ulang printer


catatan order