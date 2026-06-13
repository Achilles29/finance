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



- seragamkan tab halaman divisi dan gudang
- generate stock opname timpa jika profil sama



kita pindah ke component.
saya melakukan penyesuaian di /production/component-daily-recon ADU RAMU awalnya 150 saya sesuaikan menjadi 140. artinya seharusnya ada adjusment 10. prinsipnya sama seperti adjsutment di /production/component-daily dan /production/component-adjustments
yang terjadi setelah saya posting:
- inv_component_adjustment_line selected_lot_id null. apakah seharusnya ada terinput atau bagimana? (saya tanya)
- di /production/component-daily tidak masuk dalam adj tapi malah masuk di OUT.


===========
perbaiki tampilan halaman:
1. production/component-batches.
   -- sesuaikan button tab bertingkat sesuai coding_standars (seperti pada /purchase) teramasuk pada bentuk dan warna masing masing tingkat nya
   -- ubah skema tampilan jadi: form batch muncul sebagai modal saat di klik tambah batch. jadi tampilan utama hanya tabel daftar batch
   -- tambahkan card card ringkasan yang diperlukan, seperti hasil batch divisi, nilainya, jumlahnya dll yang penting sebagai bahan analisa. kejutkan saya!
   -- tambahkan filter divisi dan lokasi.
   tambahkan kolm pencarian ajax
   -- tambahkan filter baris dan pagination. defaultnya 25
   -- tambahkan range filter hari. defautlnya hari ini
   -- buat kolomnya jadi scrollabel atas bawah

    ---- freeze di judul kolom 

    -- maaf warnanya tab atas kembalikan seperti semula
    --- tambahkan tab tipe seperti halmaan lainnya (Semua, base, prepare) jadikan template saja untuk halaman lainnya juga yang serumpun

2. /production/component-daily
    -- di google chrome tidak ter freeze barus judul (atas ) dan kolom ringkasan ketika di scroll ke atas dan ke bawah. coba contoh dari /inventory-material-daily untuk freeze nya. kalau untuk scrollabelnya sudah sesuai
    -- tambahkan button modul generate stok opname dan stok awal yang sudah dibuat. taruh di samping tab filter jenis component. beri warna mencolok. (boleh jadikan template untuk halaman lain yang serumpun)
    -- hapus button Lot FIFO di pojok kanan atas
    -- sedikit jarak antara form TIPE dan Form bawahnya (cari dll)


    --- generate sok opname dan stok awal harusnya jadi1 bukan terpisah. artinya setiap generate stok opname otomatis generate stok awal bulan berikutnya
    --- gabungkan divisi dan komponen agar efisien
    --- baris judul tabel belum ter freeze ketika di scroll ke bawah 
    --- tombol filter dan  clear keluar area. perbaiki!
    --- tambahkan filter baris dan pagination, default 25 baris
    --- kolom ringkasan masih belum ter freeze di google chrome




3. /production/component-daily-recon
    -- tambahkan button modul generate stok opname dan stok awal yang sudah dibuat. taruh di samping tab filter jenis component. beri warna mencolok.



/production/component-daily-recon 
- hilangkan tulisan 118 item dibawah aksi cepat
- tambahkan filter lokasi dan tampilan lokasi di bawah divisi
- tambahkan clear filter di kanan tampilkan
- tampilkan expand collapse untuk menampilkan child compontent yang punya lebih dari 1 lot. dan adjustmen dilakukan di child nya

adjustmen jenis dan reason seragam dan sesuai database. 


4. /production/component-stock

    -- buat halamannya scrollabel atas bawah seperti pada /production/component-monthly tapi di freeze di judul tabelnya
    -- tambahkan filter divisi sebelum lokasi
    -- tambahkan filter baris, default 25
    -- tambahkan button modul generate stok opname dan stok awal yang sudah dibuat. taruh di samping tab filter jenis component. beri warna mencolok.
    -- hapus button Lot FIFO di pojok kanan atas
    -- ringkas tampilan tabel agar tinggi baris tidak terlalu tinggi. kejutkan saya (Divisi , lokasi dan TIPE bisa dijadikan 1 kolom agar tidak terlalau banyak kolom)
    -- Cost lot aktif masih seragam. bisa digeser agar tidak mempertinggi baris
    -- tambahkan card card ringkasan dari data yang berguna untuk analisa

------- lot aktif taruh samping






5. /production/component-monthly
    --  halamannya sudah  scrollabel atas bawah tapi belum di freeze di judul tabelnya. perbaiki agar freeze ketika di scroll ke bawah
    -- tambahkan filter baris, default 25
    -- hapus button generate stok opname dan Lot FIFO di pojok kanan atas
    -- Divisi , lokasi dan TIPE bisa dijadikan 1 kolom seperti pada /production/component-stock
    -- Cost lot aktif masih seragam. bisa digeser agar tidak mempertinggi baris
    -- sesuaikan lebar tabel agar tidak perlu di scroll kanan kiri. karena banyak kolom yang ukurannya terlalu lebar sehingga tidak efisien

6. /production/component-adjustments

   -- ubah skema tampilan jadi: form adjustmen muncul sebagai modal saat di klik tambah adjustmen. jadi tampilan utama hanya tabel daftar adjustmen (tab Per Nota dan Per Rincian)
   -- sesuai card card ringkasan yang diperlukan, seperti hasil adjustmen divisi, nilainya, jumlahnya dll yang penting sebagai bahan analisa. kejutkan saya!
   -- tambahkan filter divisi dan lokasi.
   tambahkan kolm pencarian ajax
   -- tambahkan filter baris dan pagination. defaultnya 25
   -- tambahkan range filter hari. defautlnya bulan ini
   -- buat kolomnya jadi scrollabel atas bawah dan freeze di judul kolom 


seusaikan:
- rapikan lagi tampilan card ringkasan
- card ringkasan hanya untuk yang posted ya, yang draft dan void jangan tampilkan

Per Nota:
- Tanggal dan Lokasi masih menyebrang
- catatan terlalu lebar. perkecil!
- tambahkan button detail di aksi
- pagination belum muncul (hanya 1 halaman)

Per Rincian
- pagination belum muncul (hanya 1 halaman)
- Tanggal dan Lokasi masih menyebrang
- jangan tampilkan kolom Spoil 	Waste 	Plus 	Hrg Plus 	Nilai 	Minus , cukup nilai yang di adj, lalu sampingnya kolom jenis nya (Spoil 	Waste 	Plus 	Hrg Plus 	Nilai 	Minus) dan alasannya. agar lebih efisien

apakah adj mengintervensi LOT? karena saya cek kolom LOT kosong semua


7. /production/component-openings

    -- sesuaikan button tab bertingkat sesuai coding_standars (seperti pada /purchase) teramasuk pada bentuk dan warna masing masing tingkat nya
   -- ubah skema tampilan jadi: form input opening sebagai modal saat di klik tambah opening. jadi tampilan utama hanya tabel daftar opening sesuai tab yang sudah dibuat
   - form export import tetap.
   -- tambahkan card card ringkasan yang diperlukan, seperti hasil opening divisi, nilainya, jumlahnya dll yang penting sebagai bahan analisa. kejutkan saya!
   -- tambahkan filter baris dan pagination. defaultnya 25
   -- tambahkan range filter hari. defautlnya bulan ini
   -- buat kolomnya jadi scrollabel atas bawah dan freeze di judul kolom 

8. /production/component-movements
    -- sesuaikan button tab bertingkat sesuai coding_standars (seperti pada /purchase) teramasuk pada bentuk dan warna masing masing tingkat nya
   -- tambahkan card card ringkasan yang diperlukan, seperti hasil adjustmen divisi, nilainya, jumlahnya dll yang penting sebagai bahan analisa. kejutkan saya!
   -- tambahkan filter baris dan pagination. defaultnya 25
   -- tambahkan range filter hari. defautlnya bulan ini
   -- buat kolomnya jadi scrollabel atas bawah dan freeze di judul kolom 

9. /production/component-lots
    -- sesuaikan button tab bertingkat sesuai coding_standars (seperti pada /purchase) teramasuk pada bentuk dan warna masing masing tingkat nya
   -- tambahkan card card ringkasan yang diperlukan yang penting sebagai bahan analisa. kejutkan saya!
   -- tambahkan filter baris dan pagination. defaultnya 25
   -- tambahkan range filter hari. defautlnya bulan ini
   -- buat kolomnya jadi scrollabel atas bawah dan freeze di judul kolom 
    -- sesuaikan lebar tabel agar tidak perlu di scroll kanan kiri. karena banyak kolom yang ukurannya terlalu lebar sehingga tidak efisien

10. /production/component-reconcile
    -- sesuaikan button tab bertingkat sesuai coding_standars (seperti pada /purchase) teramasuk pada bentuk dan warna masing masing tingkat nya
    -- ubah posisi dan penulisan judul halaman "Rekonsiliasi Base/Prepare" jadi di atas dengan icon relevan seperti pada halaman lain
  -- tambahkan card card ringkasan yang diperlukan yang penting sebagai bahan analisa. kejutkan saya!
   -- tambahkan filter baris dan pagination. defaultnya 25
   -- tambahkan range filter hari. defautlnya bulan ini
   -- buat kolomnya jadi scrollabel atas bawah dan freeze di judul kolom 
    -- sesuaikan lebar tabel agar tidak perlu di scroll kanan kiri. karena banyak kolom yang ukurannya terlalu lebar sehingga tidak efisien

11. /production/component-opname
    -- sesuaikan button tab bertingkat sesuai coding_standars (seperti pada /purchase) teramasuk pada bentuk dan warna masing masing tingkat nya
    --  halamannya sudah  scrollabel atas bawah tapi belum di freeze di judul tabelnya. perbaiki agar freeze ketika di scroll ke bawah
    -- tambahkan filter baris, default 25
    -- modul generate stok opname dan stok awal taruh di samping tab filter jenis component. beri warna mencolok.
    -- ringkas tampilan tabel agar tinggi baris tidak terlalu tinggi. kejutkan saya (Divisi , lokasi dan TIPE bisa dijadikan 1 kolom agar tidak terllau banyak kolom)
    -- Cost lot aktif masih seragam. bisa digeser agar tidak mempertinggi baris
    -- sesuaikan lebar tabel agar tidak perlu di scroll kanan kiri. karena banyak kolom yang ukurannya terlalu lebar sehingga tidak efisien


guarding generate sok tidak bisa kalau ada yang minus


di inv_component_monthly_stock ada beberapa jenis adjustment, waste, spoil, plus, minus. sedangkan di inv_division_monthly_stock lebih banyak (discarded, spoil, waste, process_loss, variance, plus, minus)


adjustmen divisi belum sesuai pilihan di inv_division_monthly_stock