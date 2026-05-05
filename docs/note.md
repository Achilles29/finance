## JANGAN UBAH FILE INI. INI CATATAN SAYA SENDIRI

kita gunakan 2 directory, finance dan core

finance adalah folder CI3 kosong yang ingin saya gunakan untuk membangun aplikasi keuangan kafe mulai dari absensi, pengelolaan stok, POS, pengelolaan keuangan dan lain sebagainya

sebelumnya kita sudah membuat di directory core dan sudah running online, hanya saja ini saya pull untuk kita kerjakan offline

saya ingin membangunan aplikasi baru yang tidak sepenuhnya baru, yaitu dengan mengadopsi yang sudah kita buat di core agar lebih baik, profesional, mengatasi bug dan kekurangan yang masih banyak terjadi di core.

meskipun kita buat baru, tapi saya ingin data data yang ada di core bisa kita gunakan dan skema alur proses bisnisnya tidak jauh berbeda agar user tidak kesulitan.

fokus saya sebenarnya adalah penyempurnaan core dengan dimulai ulang dari awal agar bisa mengantisipasi kesalahan yang sudah saya buat di core yang agaknya terlalu sulit dan rawan untuk diperbaiki.

jadi pertama aku ingin kamu scan fitur, modul, alur, dan lain lain yang sudah kita buat di core, kemudian kita tentukan alur pengembangan di finance mulai dari alur bisnis, skema database yang lebih baik dan efisien, dan lain sebagainya.

setelah kita matangkan tahap demi tahap modul yang akan kita buat lalu kita kerjakan developingnya
=============================================
=============================================

2A. untuk data master uom ambil seed data awal dari core saja, karena itu yang sudah digunakan

2B. Pisahkan database dan modul kategori untuk barang yang kamu jelaskan. terutama komponen dan produk, kalau digabung nanti malah susah. karena itu rumpun yang beda jadi jangan paksa jadi 1 tabel. khususnya produk, karena diatas kategori produk harusnya ada master klasifikasi produk dan master divisi produk. dan ambil data seed nya dari core.
kalau kategori item dan material mungkin malah bisa dianggap sama. tapi bagaimana menurutmu?


2C dan 2D, karena material pasti item, tapi item belum pasti material, maka semestinya data keduanya relativ sama, dan material lebih detail dari item. khusus item atau material, dalam pembelian ada 2 satuan, pertama 1 an packagingnya , misal DUS, BOTOL, PIECES, PACK dan lain lain, kedua adalah satuan isi atau gramasi atau yang disebut dengan nama lain yang satuan itu digunakan dalam resep, misal pieces, mililiter, gram dan sebagainya.
untuk UI nya , karena 2 data itu berhubungan, maka setiap input di item harus ada guarding jika item yang akan di simpan merupakan material, maka data juga terinput ke material dengan tambahan detail data yang diperlukan dalam tabel material jika memang datanya beda.
lalu pertanyaan saya jika item itu merupakan material, apakah kategorinya sama atau bagaimana?
mst_material_item_source itu berarti semacam jembatan antara 2 tabel tersebut ya? apakah tidak terlalu rumit? bagaimana kalau cukup menambahkan kolom material_id nullable pada tabel item? 
hpp_standar material apakah tidak diperlukan?


2E. component mestinya ada kolom divisi untuk membedakan itu milik divisi mana. karena tidak ada component sama dibuat di kedua divisi. kalau 1 component dipakai di 2 divisi dalam resep bisa, tapi yang memproduksi hanya 1 divisi. nanti disesuaikan di resep agar sumber nya bisa lintas divisi
kolom hpp_standard perlu tidak? di core saya buat sebagai acuan menentukan harga awal. dan agar bisa dibandingkan harga jual produk dan hpp sekarang masih relevan atau tidak.
kolom variable cost mungkin diperlukan untuk menentukan biaya variable dalam memproduksi component. kalau di core, nanti ada pengaturan default besaran biaya variabel baik untuk component maupun produk. defaultnya 20%. tapi pada master component dan master produk dapat dikasih 3 opsi, default (sesuai pengaturan), custom (angka % manual tidak sesuai pengaturan), NONE ( tanpa variabel cost)

lalu pada produksinya component nanti, kalau di core bisa pilih mode sesuai resep atau menurut bahan acuan. coba pelajari skema produksi component di core

2F. tambahkan kolom deskripsi pada mst_product, untuk ditampilkan di halaman nanti terkait deskripsi dari produk
hpp standar mungkin diperlukan, yaitu hpp dari perhitungan awal sebagai acuan. 

untuk hpp live mungkin cukup ditampilkan di UI dengan perhitungan hpp bahan sesuai resep. bagaimana menurutmu? atau jika menurutmu lebih baik ditambahkan kolom hpp, bagaimana proses update datanya? masak pakai cron, terlalu berat. atau bagaimana? karena data hpp bahan baku dan component bisa jadi dinamis dan fluktuatif. atau ada skema yang lebih efisien?

kolom variable cost juga perlu ditambahkan.

tambah kolokm untuk set ketersedian stok, untuk mengatus Stok Tersedia, Stok Habis, Atau ketersedian sesuai perhitungan (auto)

Untuk available mungkin bisa dirubah, saya perlu skema yang menentukan produk tampil di POS, Tampil di halaman member (untuk order mandiri dari meja), tampil di Landing Page. bisa semua , bisa salah 1 atau 2 atau semua.

untuk upload gambar pastikan support JPG, JPEG, PNG, HEIC



Lalu menjawab pertanyaanmu:
1.kalau di core untuk varian tidak, tapi nanti ada produk extra untuk pilihan produknya. misal ayam goreng standar nasi putih, bisa extra nasi nya dobel, atau extra mengganti nasi putih jadi nasi garlic, bisa juga extra packaging untuk Take Away.
sebenernya saya juga pengen ada kosep varian, tapi dengan skema yang efisien. misal semua produk yang menggunakan nasi putih, bisa memilih varian nasi, tapi apa saya harus input 1 per satu varian untuk semua produk? nah kalau dengan extra tinggal pakai group extra (ini yang digunakan di core)

2. Add-on / Topping itu masuk produk extra. 
untuk extra perlua sekalian dibuat tabel masternya? atau mst_product_addon juga boleh

3. Satuan Yield Component tetap dibuat standar untuk 1 resep. jadi 1 batch dnegan resep yang dicatata, hasilnya sekian. tapi yang saya jelaskan tadi, ada skema pilihan acuan bahan baku. contoh untuk prepare bebek bumbu ireng, 1 kali prepare dibutuhkan 2 ekor bebek, 10 gram garam, dan seterusnya, dengan hasil jadi 8 pack. jadi dengan skema resep 1 batch = 8 pack. nah kalau dengan acuan bahan baku, bisa jadi kita punya 3 ekor bebek dan ingin di prepare semua. dari pada kita input1,5 batch, kita pilih skema acuan bahan baku, lalu kita pilih bahan baku di resep yang dipakai acuan (bebek) 3 ekor, maka hasil jadinya bukan 8 tapi 12. dengan catatan  untuk konsumsi bahan baku lainnya bukan sesuai resep tapi menyesuaikan kelipatannya hasil jadinya, dalam hal ini 1,5 x resep.

4. foto simpan di uploads/products/ saja



catatan tambahan: pastikan semua halaman mobile friendly. kolom stripe dan responsif. pagination dengan filter baris (default 25, maksimal semua). kolom pencarian ajax.


=======================================


=================================

agar tidak lupa, cek ulang 2026-05-01c_konsep_inventori_fnb.md, 2026-05-01d_roadmap_pengembangan.md, 2026-05-01e_alur_bisnis_user.md dan sandingkan yang sudah kita buat. update yang harus diupdate dan kita tentukan tahap berikutnya.
================

di master/item ada UOM BELI dan UOM ISI, itu maksudnya seperti BOTOL dan MIliliter ya? bagaimana jika nanti ada perubahan UOM BELI? coba cek di core seperti apa. kalau disana sepertinya ada 1 lagi tabel katalog purchase, jadi yang menentukan BOTOL atau DUS bukan di master item atau material tapi di katalog, karena bisa jadi ada perubahan.
dan penegesannya kita buat aplikasi ini dengan konsep Managemen Keuangan FnB yang profesional. seperti yang sedang dibuat di core. kamu bisa scan perlahan kalau diperlukan dan tuliskan ulang kalau perlu.
ingat ya semua perubahan atau catatan penting harus dicatat dan di update dalam file yang sudah kita buat sebagai peganganmu.

Pola yang dibuat di core terkait profil stok kemarin masih partial jadi belum sempurna, kita sempurnakan disini.

sekarang kita masih ada yang perlu diperbaiki dan disempurnakan di tahap ini atau lanjut tahap berikutnya?

====================
lanjut tahap 6

sebelum eksekusi sql
tanggapan saya untuk tambahan penejelasan detail untuk PURCHASE:


- perlu dibuat master tipe posting dan tipe purchase dulu? (di core ada m_posting_type dan m_purchase_type), atau ada yang lebih efisien namun tetap profesional
- tabel pur_purchase_order_line kok belum ada merk dan keterangan? karena item untuk produksi atau bahan baku sudah ada uom beli dan uom isi, apakah masih perlu conversion_factor_to_content? bukan kah tinggal kalikan kuantitas beli dengan kuantitas isi?. kolom keterangan juga belum ada. kolom keterangan tetap diperlukan karena bisa jadi ada produk yang identik tapi perlu di bedakan di keterangan, dan keterangan ini juga harus masuk profil gudang dan atau stok bahan baku.

- Kontrak snapshot (yang dibekukan di line) belum ada merk dan keterangan juga
=======================

- untuk rekening ambil datanya dari core ya
- lalu sebelum eksekusi sql kita bahas tabel stok dulu.

inv_warehouse_stock_balance dan inv_division_stock_balance

- tabel yang dibuat itu skemanya stok live saat ini? atau stok bulanan?
- lalu nantinya saya butuh menampilkan daily bulanan stok juga yang berisi stok awal , stok masuk, penyesuaian dst seperti UI di CORE.

coba kita lihat SS Gambar yang saya tampilkan


gambar 1,2,3 adalah stok gudang
gambar 4,5,6 adalah stok bahan baku

UI nya memang tidak kita buat sekarang, tapi tabel databasenya bisa kita siapkan sejak sekarang agar tidak kerja dua kali.
intinya kedepan saya butuh tampilan data seperti itu, menurutmu bagaimana dan apa saja format tabel yang diperlukan?


==============================
==============================

kalau terlalu banyak tabel jadi terlalu berat dan rawan bug nggak sih?

bisa nggak dibuat gini:
- Buat dulu stok awal gudang dan stok awal bahan baku ( inputnya nanti bisa manual, bisa generate dari stok opname. jadi di akhir bulan kita generate stok opname sekaligus stok awal bulan berikut)

Purchase => gudang. 
Purchase menggenerate log, dan input stok masuk gudang, dan menghitung ulang data lainnya sehingga dapat dilihat stok akhir , hpp, harga total dan sebagainya. 

Purchase => Divisi.
Skemanya sama, hanya tujuannya ke divisi dan sedikit penyesuaian logika.

tapi harus tetap aman dan bisa dilakukan rebuild ketika ada miss.

menurutmu apakah tabel yang kamu rencakana masih relevan? atau terlalu banyak? atau kurang.

intinya saya butuh seefisien mungkin. karena pola yang dibuat di CORE rawan bug dan terlalu berat. data bisa berbeda antar halaman karena ada 3 versi halaman yang menampilkan stok 


==================
===================

Oke saya tanggapi dulu:
- berarti ada 3 tabel untuk masing masing? balance (live), log, dan inv_stock_daily_rollup?
rawan miss nggak balance dan daily rollup? karena nanti akan ada modul lebih kompleks, Seperti Store Request, POS, dan lainnya. apa bisa dengan dibuat semacam library dimana ada transaksi itu otomatis menggenerate ke 3 tabel?

- inv_warehouse_stock_balance dan inv_division_stock_balance ini live terus berjalan? atau ada cut off bulanan? untuk produk sama tapi profie berbeda apakah jadi 1 atau dipisah? apa cukup dipisah dengan profile_key? di core dibuat skema profile_key karena ide profile muncul belakangan jadi agar tidak merusak tabel dibuatlah profile_key. tapi ketika dibuat lebih awal apakah tidak lebih baik profile itu juga dijabarkan kolomnya ?



- inv_stock_daily_rollup itu digabung antara gudang dan divisi?? jangan donk. pisahkan saja. gudang itu penyimpaan, divisi itu untuk produksi.
lalu apakah tabel itu live atau cutoff bulanan? apakah menimpa data profil yang sama atau buat baris baru di setiap transaksi?? intinya saya ingin skema seefisien mungkin, tidak terlalu banyak tabel dan baris tapi bisa mengakomodir tampilan tadi dan tetap profesional

sesuaikan dulu yang perlu disesuaikan


===============
tambahan agar tidak miss, ketiga tabel itu harus menyajikan data produk yang sama mulai dari purchase (nama, merk, keterangan , ukuran dan seterusnya) agar jelas profilnya. jadi data barang dari purchase terus terbawa sampai barang digunakan.

jadi di _stock_balance dan _daily_rollup setiap ada profile baru maka tambah baris, tapi jika profil masuk sama cukup update stok nya. ini juga sebagai kontrol agar purchase konsisten dalam input data.

sekarang cek ulang sql 3i, 3j (ada 2), 3k, pastikan tidak ada yang overlab. kalau perlu hapus file yang tidak diperlukan dan bisa digabung 

==================================
==================================
Master Company Account dan Purchase Account sumbernya sama nggak? saldonya sama nggak?
saya rasa terlalu ruwer. kalau kebanyakan tabel malah rawan miss data keuangannya kan? masalahnya fokusnya sama yaitu saldo rekening.
mending opsi 2. karena Purchase dan sales juga kan dihitung realtime langsung ke rekening perusahaan.

========================================
apa fungsinya finance/purchase/receipt?
saya kok masih bingung. sementara user sudah mulai terbiasa dengan logika /purchase-orders dan purchase-orders/create pada core. coba cek disana, bisa nggak adopsi itu aja biar nggak terlalu banyak perubahan?

=======================
sebelum bisa mulai praktek input purchase kita perlu buat:
1. modul keuangan, crud saldo rekening.
2. halaman opening gudang, stok gudang, stok bulanan gudang, daily gudang,
3. copy data catalog dari pucrchase agar kita bisa praktek input
========================

uom isi untuk bahan baku tidak dapat dirubah, harus sesuai dengan di master material

uom beli jika dirubah berikan warning yakin merubah?

Jenis sudah di input diatas jadi tidak perlu munculkan lagi di baris

jika data item yang diinput benar benar baru, tambahkan ke master item, jadi tidak perlu input item dulu


"Harga beda saja: tidak membuat profile key baru, tetap profile yang sama lalu update harga terakhir." menurut saya harus buat profile baru, karena harga satuan itu juga termasuk bagian dari profile. karena untuk perhitungan hpp nanti kita butuh harga satuan sesuai FIFO. takutnya kalau harga diupdate profile berubah dan hpp berubah sebelum barang digunakan



Tambah mode edit cepat per baris untuk re-pick item/material via ajax preview langsung di dalam row (bukan hanya dari search atas), supaya alur perubahan data makin mirip form core.

=============================

- apa fungsinya status CLOSED dan apa pengaruhnya?
- ya:
Tambahkan badge warna per status (DRAFT, ORDERED, RECEIVED, PAID, dll) di list PO.
Tambahkan timeline status di detail PO agar alur bisnis lebih jelas untuk user.
Sinkronkan gaya visual halaman yang belum lewat layout utama (jika ada view khusus terpisah).

- ada warning Header belum lengkap: request date, purchase type, vendor wajib diisi. vendor tidak wajib diisi, bisa dikosongi

- tampilan purchase-orders terpotong

- button create order di purchase-orders hilang

- sql sudah saya jalankan, tapi kenapa belum muncul status barunya?

- preview barang tertutup tabel

- di purchase-orders tambahkan tab Daftar Purchase Order jadi per nota dan per rincian

- hapus nav bawah (image 4)
================

data barang yang saya input di purchase dengan yang tampil kenapa beda? apa ada miss antara material id dan item id?
coba cek.
=========================
- purchase type urutkan id terkecil
- tampilan preview kok jadi jelek lagi, tau udah bagus
- snapshot tersimpan tidak sesuai yang dipilih. beras kok jadi backing powder
- fungsi edit juga masih gagal saat simpan
=====================

tabel log purchase belum ada? ini kan penting untuk rebuild dan audit.  apakah semua log nanti jadi 1 di aud_transaction_log?

pur_purchase_payment_plan, pur_purchase_receipt dan pur_purchase_receipt_line jadinya masih dipakai tidak?

dampaknya pruchase bukan cuma di keuangan, tapi juga stok gudang dan atau bahan baku. coba cek lagi ya logika pruchase di core lalu kita petakan ulang. jangan catatn jika ada perubahan alur atau roadmap

====================
- berarti nanti ada 2 log untuk setiap modul transaksi ya? 1 di aud_transaction_log, 1 nya di masing masing modul? rawan miss atau aman?

- saya klik ulang sync dampak di /purchase-orders/detail/ muncul baris baru lagi di tabel log nya. apakah itu aman atau berdampak dobel ke keuangan atau pun stok gudang?

- buatkan halaman log purchase nya

- Audit Ringkas yang ada di /purchase-orders/detail/ tetap diambil dari aud_transaction_log atau pindah di pur_purchase_txn_log?

- transaksi belum berdampak ke stok

- SAYA buat purchase-orders/create dengan status langsung PAID, belum masuk ke keuangan, kalau dari perubahan status di purchase-orders bisa masuk


- harus ada modul rebuild atau rescync di masing masing. jadi rebuild tidak harus dari purchase tapi sesuai kebutuhan,  misal di gudang butuh audit dan rebuild berarti rebuild dari gudang untuk memeriksa semua tabel terkait. tombolnya bisa by item / transaksi bisa juga global atau by filter


- saya lihat di pur_purchase_payment_plan sudah ada transaksi hasil update status pembayaran. tapi di pur_purchase_receipt dan pur_purchase_receipt_line belum ada. itu kalau diaktifkan nanti tumpang tindih dan jadi ada 2 metode nggak?

===============================
- halaman purchase order log belum ada di sidebar
- purchase/stock/warehouse/daily, /purchase/stock/warehouse, purchase/stock/warehouse/movement, purchase/stock/division/movement, purchase/stock/division, purchase/stock/division/daily masih ada desimal lebih dari 2 angka. cek juga untuk yang halaman lain. ingat untuk semua halaman UI desimal cukup 2 angka dibelakang koma. apa mungkin perlu dibuat tempate ?


lanjut ke :

- lanjutkan modul rebuild/resync terstruktur (by transaksi, by item, by filter tanggal/status, dan global) khusus purchase.

- tambah halaman khusus Rebuild Impact Purchase agar tidak bergantung tombol detail per PO.

- bantu siapkan checklist uji data riil untuk memastikan stok dan keuangan benar-benar sinkron di environment Anda.

=======================

- saya ingin membuat tampilan mirip inventory-warehouse-daily untuk gudang dan /inventory-material-daily untuk bahan baku. dimana tampilan pertanggal memanjang ke kanan, di masing masing tanggal ada pergerakan keluar masuk stok nya. tabel mana yang bisa dipakai
===============



v inventory-warehouse-daily dan inventory-material-daily seharusnya keluar masuk stok nya diakumulasi sampai dengan akhir bulan, bukan harian. stok akhir hari ini jadi stok awal besok. persempit lagi area sebelah kiri agar efisien space nya, dan buat sticky. lalu ketika pertama buka halaman atau setelah refresh possisi default tampilan di tanggal hari ini

stock/division/daily dan purchase/stock/warehouse/movement itu kan ada tanggal di UI nya. jika ada purchase masuk yang identik tapi tanggal beda, apakah buat baris baru atau update stok nya?


v pisahkan Stok Divisi Reguler dan Event. di tipe purchase  sudah dipisahkan Tujuan, tapi di halaman purchase/stock/division/daily, purchase/stock/division, purchase/stock/division/movement belum memisahkan tujuan, masih gabung jadi 1 divisi

v tambahkan tujuan setelah kolom divisi
v divisi dan tujuan jangan tampilkan angka, tapi namanya. gunakan join tabel

v /purchase/stock/division kolom Ukuran Isi masih banyak angka dibelakang koma desimalnya. total nilai belum ada

v lalu tambahkan filter dan card ringkasan  pada SEMUA halaman gudang dan bahan baku. filter range defaultnya bulan ini. buat tampilannya informatif dan user friendly. kejutkan saya

======================
======================

General: 
- jangan samakan FONT dengan FONT pada CORE. karena terlalu kaku dan tidak enak dipandang
- tambahkan tombol clear filter untuk semua halaman yang punya tabel



inventory-warehouse-daily dan inventory-material-daily

v freeze di kolom ringkasan
v modal  Detail Mutasi Harian Material pada  tidak bisa di close
v perkecil space PROFIL, di wrap saja agar tidak terlalu makan space
v untuk material atau item yang sama, gabungkan jadi 1 dengan ringkasan di rata rata, dan dapat di expand untuk melihat per profile nya
v pada ringkasan tampilkan : HPP, Stok awal dan akhir pack, stok awal dan akhir isi, Total nilai rupiah sisa
v pada profil pack tambahkan harga satuan masing masing baris
v kolom per tanggal nya seharisnya awal, in, out, adj, akhir. jadi awal dan in masing masing tanggal jelas terlihat, tidak digabung

v purchase/stock/warehouse dan purchase/stock/warehouse/movement belum ada kolom nilai total
v purchase/stock/warehouse kolom Ukuran Isi cukup tampilkan 2 desimal (sekarang masih ,000000)

v purchase/stock/warehouse purchase/stock/division/movement belum ada kolom nilai total
v filter /stock/division belum berfungsi (dropdownnya tidak sesuai data), buat pola filter divisinya sama dengan di /stock/division/daily saja. 
v rapikan area filter /stock/division/daily, masak dari tanggal dan sampai tanggal ter enter tidak sejajar
v filter divisi dan tujuan pada /stock/division/movement juga belum relevan (dropdownnya tidak sesuai data)

- /purchase/stock/warehouse/daily dan /purchase/stock/division itu kan stok bulanan ya, kalau dipisah per tanggal padahal barangnya identik, maka nanti akan banyak baris dengan barang yang sama dan akan kesulitan melihat sisa stok nya. padahal kan yang saya butuhkan pada bulan itu bara dengan profil A, berap keluar masuknya bisa langsung terlihat jelas, tidak peduli tanggalnya.
bagaimana menurtmu? perlu dirubah skema databasenya? atau skema logika CRUD nya? atau cukup tampilan saja? mana yang menurutmu lebih rapi, efisien dan aman untukk audit?

buat warna badge berbeda untuk masing masing status pada /purchase (saya lihat received dan paid masih sama warnanya)

===============
inventory-material-daily dan inventory-warehouse-daily:
- belum di freeze di kolom ringkasan (ketika di scroll ke kanan masih ikut)
- freeze juga bari JUDUL nya ketika content tabel di scroll kebawah agar terlihat tanggalnya
- persempit lagi kolom Item / Material,	Profil,	Ringkasan
- berikan button / arrow expand/collapse di sebelah kiri untuk menampilkan rincian baris , bukan dengan klik tampilkan profil, (default tetap collapse)
- inventory-warehouse-daily ganti kata material dengan bahan baku

- untuk barang yang hanya berisi 1 rincian langsung tampilkan data tanpa perlu expand collapse
- berikan warna berbeda untuk yang punya rincian dan yang tidak punya rincian
- tambahkan harga satuan pada profil pack, bukan cuma hpp, hpp cukup tampilkan di Profil di ringkasang tidak usah agar tidak terlalu banyak. Hpp rata2 cukup tampilkan di ringkasan Parent, child tidak perlu
 rapikan tampilannya. sesuaikan ukuran font agar tidak terlalu kecil
- beri stripes berbeda untuk kolom masing masing tanggal agar terlihat beda tanggal nya

KEJUTKAN SAYA

=====================


- purchase/stock/division/daily dan /purchase/stock/division/movement, purchase/stock/division dropdown filter tujuan diambil dari mana datanya??? kenapa ada pengulangan pengulangan

- purchase/stock/division balik posisi filter tujuan dan divisi (divisi dulu baru tujuan)

- purchase/stock/division/daily, purchase/stock/division , /purchase/stock/division/movement kolom divisi jangan diulang BAR-BAR , cukup BAR. lalu kolom tujuan cukup Event / reguler

=========

/purchase/stock/warehouse/daily dan /purchase/stock/division/daily bukankah transaksi per tanggal bisa dilihat dari log?
dan intinya saya ingin tampilan bulanan yang keluar masuk Barang dengan profil yang sama itu jelas 1 baris tidak dipisah per tanggal. bagaimana menurutmu?

=================

inventory-material-daily dan inventory-warehouse-daily:
- belum di freeze di kolom ringkasan (ketika di scroll ke kanan masih ikut). dan halaman terasa berat


purchase/stock/warehouse/daily dan purchase/stock/division/daily gimana jadinya??? saya ingin tampilan keluar masuk per barang dalam 1 bulan seperti ilustrasi pada gambar



================

saya tanya sekali lagi purchase/stock/warehouse/daily dan purchase/stock/division/daily aman dengan pola seperti itu?

purchase/stock/warehouse/daily itu kan masih di gudang, harusnya data yang tampil bukan isi tapi pack nya. 

jadi sesuaikan tampilan purchase/stock/warehouse/daily dan purchase/stock/division/daily agar yang tampil bukan cuma isi tapi pack nya juga. bagaimana menurutmu?

======

perbaiki lagi tampilan /inventory-warehouse-daily dan inventory-material-daily, kolom tanggal mengecil, refresh berat, tampilan patah patah. seharusnya ketika pertama dibuka atau di refresh, atau di expand freeze tampilan tegas kolom divisi/tujuan atau jenis terlihat bukan tergeser kekiri

=================