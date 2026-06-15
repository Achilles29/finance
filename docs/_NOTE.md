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

Per Rincian:
- No, Tanggal, dan Status terpotong, Alasan terlalu lebar. sesuaikan keempat kolom itu 	
- judul LOKASI dan DIVISI cukup tulis DIVIS agar tidak menyebrang
- apa beda Nilai Adj dan Jenis & Qty? kenapa beda nilainya? apakah nilai itu uangnya? kalau iya sesuaikan urutan kolom : | qty (qty adj ) | NILAI (uang) | Jenis ( misal " waste : erro system")|  


masih /production/component-adjustments :
- di kolom QTY tidak usah tuliskan jenisnya lagi karena sudah ada di kolom sebelahnya. cukup tulis QTY nya
- perlebar kolom No, Tanggal,dan Komponen agar kontennya tidak terpotong
- perkecil jenis dan alasan itu terlalu lebar
- kolom lot boleh tampilkan lagi untuk melihat apakah revisi tadi sudah berjalan (taruh setelah nilai)


7. /production/component-openings

sekarang /production/component-openings

   -- ubah skema tampilan jadi: form input opening sebagai modal saat di klik tambah opening. jadi tampilan utama hanya tabel daftar opening sesuai tab yang sudah dibuat
   - form export import tetap.
   -- tambahkan card card ringkasan yang diperlukan, seperti hasil opening divisi, nilainya, jumlahnya dll yang penting sebagai bahan analisa. kejutkan saya!
   -- tambahkan filter baris dan pagination. defaultnya 25
   -- tambahkan range filter hari. defautlnya bulan ini
   -- buat kolomnya jadi scrollabel atas bawah dan freeze di judul kolom 

8. /production/component-movements

sekarang /production/component-movements
   -- tambahkan card card ringkasan yang diperlukan, seperti hasil adjustmen divisi, nilainya, jumlahnya dll yang penting sebagai bahan analisa. kejutkan saya!
   -- tambahkan filter baris dan pagination. defaultnya 25
   -- tambahkan range filter hari. defautlnya bulan ini
   -- pindahkan data di kolom No untuk ditampilkan di kolom SUmber. jadi hapus saja kolom No, tapi perjelas yang kolom SUmber. boleh di bold 

9. /production/component-lots
sekarang /production/component-lots
   -- tambahkan card card ringkasan yang diperlukan yang penting sebagai bahan analisa. kejutkan saya!
   -- tambahkan filter baris dan pagination. defaultnya 25
   -- tambahkan range filter hari. defautlnya bulan ini
   -- buat kolomnya jadi scrollabel atas bawah dan freeze di judul kolom 
    -- sesuaikan lebar tabel agar tidak perlu di scroll kanan kiri. karena banyak kolom yang ukurannya terlalu lebar sehingga tidak efisien

10. /production/component-reconcile
sekarang /production/component-reconcile
    -- ubah posisi dan penulisan judul halaman "Rekonsiliasi Base/Prepare" jadi di atas dengan icon relevan seperti pada halaman lain
  -- tambahkan card card ringkasan yang diperlukan yang penting sebagai bahan analisa. kejutkan saya!
   -- tambahkan filter baris dan pagination. defaultnya 25
   -- tambahkan range filter hari. defautlnya bulan ini
   -- buat kolomnya jadi scrollabel atas bawah dan freeze di judul kolom 
    -- sesuaikan lebar tabel agar tidak perlu di scroll kanan kiri. karena banyak kolom yang ukurannya terlalu lebar sehingga tidak efisien

11. /production/component-opname

/production/component-opname
   -- tambahkan card card ringkasan yang diperlukan yang penting sebagai bahan analisa. kejutkan saya!
   -- tambahkan filter baris dan pagination. defaultnya 25
   -- buat kolomnya jadi scrollabel atas bawah dan freeze di judul kolom 
   -- urutan kolom: |JENIS - berisi divisi - lokasi - tipe | COMPONENT - berisi nama component tanpa kode | selanjutnya, yang terakhir ganti AVG COST dengan TOTAL VALUE / total nilai

buatkan halaman stok awal untuk inv_component_monthly_opening, masukkan ke sidebar dan tab rumpun setelah Adjustment, dengan pola halaman sama dengan halaman opname



kita lanjut ke modul generate opname dan stok awal component.
kita review dulu yang sudah dibuat.
tambahkan 

(compoent id dan lot id yang sama).
cek juga apakah generate opname ini generate lot nya juga? atau bagaiaman skemanya nanti? karena skema kita ini pakai cut off bulanan, jadi inv_component_monthly_stock bulan berikutnya digenerate dari sini, dan semua halaman yang menampilkan sotok produk membaca ini. 

cek:
- /production/component-monthly bulan juni dan /production/component-stock di card ada 1 stock minus, tapi di /production/component-daily-recon tidak ada. stock minusnya apa?
- /production/component-stock ada 116 component total nilai  27.261.872, /production/component-monthly bulan juni ada 117 component total nilai  30.125.988. kenapa bisa beda? apa karena beda pembacaan bulannya? 
- /production/component-opname nilai stok 30,13 jt , 117 baris. /production/component-stock total nilai Rp 27.261.872 dengan 116 baris. kenapa beda? 



perbaiki:
/production/component-opname dan /production/component-opening-monthly : pola Jenis buat menjadi => BAR - REGULER - BASE , BAR - EVENT - PREPARE, bukan BAR - BAR - BASE

/production/component-stock 

- coba tunjukan stock minusnya apa? stock minus itu kan seharusnya stock minus di akhir bukan di movement log. buktinya bisa di generate berarti kan seharusnya tidak ada stock minus

- seharusnya, sumber kebenaran adalah inv_component_monthly_stock. sementara inv_component_movement_log merupakan pergerakan yang seharusnya hasil akhirnya sama dnegan inv_component_monthly_stock. jadi kalau memang ada perbedaan, harusny ada warning di ketiga halaman stok dan di halaman /production/component-reconcile. padahal di halaman reconcile tidak ada missmatch. jadi prinsipnya semua halaman harus menampilkan data yang sama untuk bulan yang sama, baik baris, nilai, minus dan lainnya. coba cek dulu semua halaman stok termasuk reconcile, opname dan openingt
=============

saat generate sok awal, harusnya generate juga di inv_component_monthly_stock dengan bulan dan tanggal baru kan?




sekarang sudah benar. kita pindah ke bahan baku dengan pola yang sama.
- rapikan halaman serumpun
- tambahkan card ringkasan relevan
- buat tab bertingkan dengan modul generate stock opname dan stok awal yang sama
- tabel dengan pola scrollable dan freeze di kolom judul
- halaman dengan filter divisi, reguler / event, kolom pencarian ajax, pagination dan filter baris halaman
- filter range tanggal atau range bulan sesuai halamannya
- buatkan halaman stok awal hasil generate jika belum ada.
- untuk halaman yang punya form input, jadikan sebagai modal agar tampilan lebih rapi
- pastikan halaman rapi, tabel tidak ada yang tumpang tindih.
- urutan sidebar dan tab bertingkat: 
    Daily Recon - Daily Material Matrix  - Posisi Stok Divisi (ganti "Stok Bahan Baku Live") - Stok Bulanan / Daily Divisi (ganti "Stok Bahan Baku Bulanan") - Adjustment Divisi (ganti "Adjustment Bahan Baku" ) - Keluar Masuk Divisi (ganti "mutasi Bahan Baku") - stok awal hasil generate (sesuaikan namanya) - Opening Bahan Baku Divisi (ganti "Opening Manual Bahan Baku")- Opname Divisi (ganti "Stok Opname Bahan Baku") - Lot Divisi (ganti "Lot Bahan Baku") - Banding Stok Akhir (ganti "Audit Bahan Baku" )
    ==> ganti semua nama halaman, nama sidebar, dan Page Title sesua nama yang saya ganti.

- ekseskusi 1 per satu semua halaman nanti akan saya cek satu per satu hasilnya


Perintah saya kan ganti nama dan urutannya baik di sidebar maupun di tab bertingkat:
    Daily Recon => tetap
    Daily Material Matrix => tetap
    Posisi Stok Divisi=>  Stok Bahan Baku Live
    Stok Bulanan / Daily Divisi => Stok Bahan Baku Bulanan
    Adjustment Divisi => Adjustment Bahan Baku
    Keluar Masuk Divisi => mutasi Bahan Baku
    stok awal hasil generate (halaman baru untuk menampilkan database stok opening hasil generate , sesuaikan namanya)
    Opening Bahan Baku Divisi => Opening Manual Bahan Baku
    Opname Divisi => Opname Bahan Baku
    Lot Divisi => Lot Bahan Baku
    Banding Stok Akhir => Audit Bahan Baku

    hapus /inventory/stock/opening/division/generate

penyesuaian di semua halaman:
    - tambahkan card ringkasan relevan
    - tabel dengan pola scrollable dan freeze di baris judul
    - halaman dengan filter divisi, reguler / event, kolom pencarian ajax, pagination dan filter baris halaman
    - filter range tanggal atau range bulan sesuai halamannya (kamu tentukan sesuai halamannya)
    - pastikan halaman rapi, tabel tidak ada yang tumpang tindih.

sesuaikan di atas dulu baru saya lanjutkan


/inventory-material-daily 
- sesuaikan ukuran form filter sampai dengan button "Terapkan" dan "Clear" agar bisa jadi 1 baris
- tambahkan card card ringkasan yang diperlukan yang penting sebagai bahan analisa. kejutkan saya!
- buatkan pagination berdasarkan filter baris

/inventory/stock/division
- sesuaikan ukuran form filter sampai dengan button "Terapkan" dan "Clear" agar bisa jadi 1 baris
- buat card card ringkasan yang diperlukan yang penting sebagai bahan analisa dengan tampilan lebih menarik. kejutkan saya!
- buat tabel nya scrollable dan freeze judul tabel
- buatkan pagination berdasarkan filter baris

sesuaikan tampilan tabel agar lebih efisien:

paling kanan Arrow expand collapase untuk line yang punya childe

kolom 1 : Divisi (berisi Divisi / tujuan)
Kolom 2 : Nama Barang, jangan tampilkan Kode, jangan tampilkan Material (karena seharusnya pasti material), enter profile dan lihat lot
Kolom 3 : Merk
Kolom 4 : Keterangan
Kolom 5 : UKURAN Isi (data  sudah benar)
Kolom 6 : QTY (beli dan isi) => yang atas QTY isi, enter QTY beli , misal 200 pcs enter 1 pack
Kolom 7 : Avg Cost
Kolom 8 : Total Nilai
Kolom 9 : Update

===========================

sekarang /inventory/stock/division/daily
- buat polanya seperti /inventory/stock/division

- sesuaikan ukuran form filter sampai dengan button "Terapkan" dan "Clear" agar bisa jadi 1 baris
- buat card card ringkasan yang diperlukan yang penting sebagai bahan analisa dengan tampilan lebih menarik. kejutkan saya!
- buat tabel nya scrollable dan freeze judul tabel
- buatkan pagination berdasarkan filter baris

sesuaikan tampilan tabel agar lebih efisien:

paling kanan Arrow expand collapase untuk line yang punya childe

kolom 1 : Divisi (berisi Divisi / tujuan)
Kolom 2 : Nama Barang, jangan tampilkan Kode, jangan tampilkan Material (karena seharusnya pasti material), enter profile dan lihat lot
Kolom 3 : Merk
Kolom 4 : Keterangan
Kolom 5 : UKURAN Isi (data  sudah benar)

Kolom 6 dan seterusnya sesuai kondisi sekarang
======================

sekarang /inventory/stock/adjustment/division
   -- buat polanya seperti /inventory/stock/division
   -- ubah skema tampilan jadi: form adjustmen muncul sebagai modal saat di klik tambah adjustmen. jadi tampilan utama hanya tabel daftar adjustmen (tab Per Nota dan Per Rincian)
   -- sesuai card card ringkasan yang diperlukan, seperti hasil adjustmen divisi, nilainya, jumlahnya dll yang penting sebagai bahan analisa. kejutkan saya!
   -- card ringkasan hanya untuk yang posted ya, yang draft dan void jangan tampilkan
   -- filter divisi dan lokasi.
   -- tambahkan kolm pencarian ajax
   -- filter baris dan pagination. defaultnya 25
   -- range filter hari. defautlnya bulan ini
   -- buat kolomnya jadi scrollabel atas bawah dan freeze di judul kolom 


apakah adj mengintervensi LOT? sekalian di fix kan


/inventory/stock/adjustment/division 

Per Rincian:
sesuaikan tampilan : NO , TANGGAL , Divisi / Tujuan 	(KITCHEN , REGULER), Status, Line hapus saja, Objek NAMAN BAHAN BAKU (hapus kode dan profile), QTY isi adj, Jenis adj dan alasan, cost isi, nilai total, Lot in, catatan. tidak usah tampilkan semua kolom jenis, paham kan? seperti pada component (gambar 1) 
apakah adj mengintervensi LOT? karena saya cek kolom LOT kosong semua



======================================
harusnya link nya bukan /inventory/stock/opening/division/generate donk, mending pakai /inventory/stock/division/opening-stock

saya belum coba generate, tapi pastikan guardnya seperti ini:

- guarding generate sok tidak bisa kalau ada yang minus. jadi harus ada penyesuaian dulu.
- jika generate dilakukan lebih dari 1 kali di bulan yang sama, data dengan profile identik ditimpa 
- saat generate otomatis menggenrate snapshot di opname, opening, dan stok opening di monthly_stock
- pastikan qty stok dan nilai di semua halaman stok sama


/inventory/stock/division/reconcile sesuaikan form filter agar tidak ada tampilan terpotong. tambahkan filter missmatch



masih gagal. cek dengan serius, apa karena PAPER FILTER V60 punya 2 baris profile, tapi di movement log dan halaman audit hanya tercatat 1?

PAPER FILTER V60
sekalian cek, kemarin ada ordern MANUAL BREW V60 JAPANESE dari MSO-20260614153456-3FEA. tapi paper filter v60 tidak terpotong stok nya. tidak masuk log. kenapa? apa karena orderan dari self order? yang di klik saat verifikasi. /pos/self-order/orders
CEK DAN PERBAIKI






cek logika simpan transaksi di POS. hari ini sari lemon stok awal 35 ml dengan 2 baris, 15 dan 20. keluar 55.
pos_stock_commit #567	stok awal 20 keluar 15 sisa 5. pos_stock_commit #573 keluar 5 kenapa yang 10 tidak mengambil dari baris 1 nya? malah melanjutkan jadi minus
 

kenapa patokannya profile key? harusnya kan patokan bahan baku / material_id bukan? memotong lot dan stock sesuai material dengan FIFO.
nah sekarnag malah kalau kita cek di /inventory/stock/division/lot , sisa lot sari lemon 5 ml, ini jelas beda dengan stock.
sementara di pos/stock-commit-audit tidak ditemukan selisih stock dan lot.
coba cek ya. baiknya di /inventory/stock/division/reconcile ada guarding bahan baku dengan jumlah stock dan dan jumlah lot yang berbeda


- adakah kasus serupa dengan sari lemon?
- apakah bisa dibuat repair sqlnya?


/inventory/stock/division/reconcile metode searcnya bukan ajax, tapi refresh. lebih baik pencairan dengan enter saja. perbaiki

