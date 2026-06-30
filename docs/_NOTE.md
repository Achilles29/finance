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
- halaman item yang sering dibeli


- buatkan modul generate stok opname dan stok awal Gudang, divisi, component. siapkan dulu database stok opaname. lalu buatkan modul generate dan tambahkan tombolnya di semua halaman stok (modul harus sama). ketika klik generate maka menggenerate sesuai stok pada montly_stock masing masing sampai dengan profile (line terkecil), lalu menggenerate stok opening untuk bulan berikutnya. untuk stok opening hanya ambil cukup ambil yang stok akhir / stok awal bulan berikutnya tidak sama dengan 0. genertae stok awal berarti menggenerate data di tabel opening dan tabel monthly_stock bulan berikutnya.
dan jangan lupa buatkan halaman stok opname dan masukkan tab bertingkat semua halaman yang serumpun dan masukkan sidebar sesuai rumpun





===============



setelah join, maka terjadi gap.
- data yang ditampilkan (gambar 2) tidak ada selisih kolom stock dan kolom movement, tapi gambar 1 ada seliish gap movement.
- pada kondisi seperti ini sumber bagaimana saya bisa memilih sumber kebenaran dan melakukan penyesuaian untuk yang lain. misal yang benar adalah stock, dan saya ingin movement log menyesuakan stock. buatkan modulnya



/inventory/stock/daily-recon/division, /inventory-material-daily, /inventory/stock/adjustment/division, /inventory/stock/division/reconcile
4 halaman itu ada modul adjustmen. 
ketika stok minus kemudian di adjustmen menjadi 0 atau lebih dari 0, lot nya juga ikut naik sejumlah kenaikan stock sehingga ada perbedaan antara stock dan lot.
Periksa apakah kondisi saat ini sesuai dengan analisaku>?

nah seharusnya ada guarding adjustmen di ke 4 halaman itu, jika adjustmen dari minus, maka menyesuaikan kenaikan mulai dari 0 saja. misal JERUK NIPIS stock -5, di adj jadi 0, maka lot tidak ikut bertambah. jika JERUK NIPIS stock -5 di adj jadi 3, maka lot hanya naik 3.
lakukan penyesuaian jika analisaku benar. bantah jika tidak tepat


sekarang halaman /inventory/stock/division/reconcile produk yang tidak ada stock tapi ada lot aktif, tetap dimunculkan dengan stock 0 dan lot ada. sehingga dapat dilakukan adj lot
nah sekarang jadi kelihatan dan lebih lebar lagi missmatchnya. ini penting karena miss lot ini juga harus di repair.
perbaiki:
- kolom pencarian belum berfungsi
- button "repair lot semua" seharusnya juga langsung me repair lot yang aktif tapi stok nya 0


sekarang pindah ke component. apakah 4 halamana adjustmen component /production/component-daily-recon, /production/component-daily, /production/component-reconcile, dan /production/component-adjustments juga ketika stok minus kemudian di adjustmen menjadi 0 atau lebih dari 0, lot nya juga ikut naik sejumlah kenaikan stock sehingga ada perbedaan antara stock dan lot?
Periksa, kalau iya maka lakukan penyesuaian seperti bahan baku, guarding adjustmen di ke 4 halaman itu, jika adjustmen dari minus, maka menyesuaikan kenaikan mulai dari 0 saja.

lalu di /production/component-reconcile component yang tidak ada stock tapi ada lot aktif, tetap dimunculkan dengan stock 0 dan lot ada. sehingga dapat dilakukan adj lot. buatkan repair lot per child dan repair lot semmua untuk kasus serupa



lakukan pengecekan di halaman adjustmen seperti daily matrix, daily recon, reconcile, adjustment , yang mungkin bisa mempengaruhi bahan baku juga

buat halaman cost berdasarkan stok component, bukan resep, karena beda, kalau ini untuk cost produk


/master/product divisi, klasifikasi dan kategori jadikan 1 kolom, mode stok dan status  jadikan 1 kolom, % hpp dan estimasi profit jadikan  1 kolom, icon kolom aksi jadikan 2 baris




cek backup git
cek ganti ip
cek server

finalkan generate stok gudang, bahan baku. component. pastikan cutoff dan membuat data baru stok dan lot nya sesuai
finalkan generate keuangan




/attendance/estimate perbaiki tampilan:
- berikan card ringkasan di atas
- buat tabelnya scrollable dan freeze di atas

/attendance/pending-requests perbaiki tampilan:
- buat tabelnya scrollable dan freeze di atas
- sembunyikan NIP, DIVIS, JABATAN
- perlebar kolom level agar tidak terlalu tinggi ukuran kolomnya
- sebelah  kanan JENIS tambahkan kolom yang menampilkan record absen yang sudah ada dari pegawai sesuai shift yang diajukan , kalau tidak ada tulis belum ada record.


/attendance/daily perbaiki tampilan:
- berikan card ringkasan di atas
- buat tabelnya scrollable dan freeze di atas



/payroll/cash-advances
/production/component-reconcile