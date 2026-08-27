## INI FILE CATATAN SAYA. ABAIKAN.

Directory finance (C:\xampp\htdocs\finance).
ini adalah pengembangan dan penyempurnaan dari repo core (C:\xampp\htdocs\core). baca README.md dan seluruh dokumen terkait. temukan polanya. dan catat yang perlu dicatat sesuai ketentuan. kita kerjakan secara paralel 
======================
<!-- setelah ini kita mulai ke POS. tapi sebelum itu harus kita siapkan dulu database utama dan database penunjangnya. antara lain: -->
<!-- - member dan promo (poin , voucher, stamp) -->
<!-- - metode pembayaran
- void
- refund
- shift kasir
- produk paket -->
<!-- 
- pengaturan printer - bisa adopsi dari core, tapi perlu beberapa penyesuaian di seting printer dan tampilan printernya. karena nantinya kita juga akan membangun aplikasi mobile untuk POS nya, jadi seting tampilan printerdiatur di database umum (dipakai baik di desktop maupun mobile), sementar setting printernya berbeda antara desktop dan mobile, namun untuk setting printer mobile kita lakukan nanti saja kalau sudah mau develop mobile
- dan database lain yang dapat kamu baca dari core.

secara umum kita bisa adopsi dari core yang sudah terbukti berjalan, degan beberapa penyesuaian agar lebih efisien namun tetap profesional.

yang perlu diperhatikan di POS ini nanti terhubung dengan stok tersedia berdasarkan resep produk, dan dengan pengaturan yang memungkinkan override produk -->

================

<!-- - Monitor dapur, bar, dan checker seperti surface Pos_order_monitor di core, karena ini masih gap operasional paling nyata. -->

<!-- v update pegawai
v cetak ulang printer
v catatan order
v update gudang, bahan baku, component
v update data sif
v laporan daily sales seperti core /pos-reports/daily-sales , kemudian cetak  -->
<!-- - update menu
- update hak akses
- tata ulang sidebar
- tampilan daily matrix
- tampilan halaman stok
- urutan tab masing masing halaman stok
- laporan keuangan -->
<!-- - input kasbon dan hutang
- skema bonus, target harian dan bulanan
- redeem poin dll
- link bahan baku dan component yang digunakan
- estimasi keuangan
- estimasi uang makan
- cek po SR gudang air mineral galon
- catalog purchase jangan cari yang tidak aktif
- resep produk lintas lokasi
- resep component lintas -->
<!-- - master bahan baku relasi ke stok
- superadmin kunci tanggal libur dan PH
- skema duplikasi database
- akses stok (ketiganya) belum memperhatikan scope divisi -->
<!-- - metode pembayaran PO tidak bisa diedit padahal belum PAID
- mst_item / mst_material / purchase catalog yang statusnya tidak aktif harusnya tidak muncul di pencarian PO
- db replication belum sinkron -->

<!-- - halaman hutang piutang
- halaman laporan keuangan
- halaman item yang sering dibeli -->
<!-- - buatkan modul generate stok opname dan stok awal Gudang, divisi, component. siapkan dulu database stok opaname. lalu buatkan modul generate dan tambahkan tombolnya di semua halaman stok (modul harus sama). ketika klik generate maka menggenerate sesuai stok pada montly_stock masing masing sampai dengan profile (line terkecil), lalu menggenerate stok opening untuk bulan berikutnya. untuk stok opening hanya ambil cukup ambil yang stok akhir / stok awal bulan berikutnya tidak sama dengan 0. genertae stok awal berarti menggenerate data di tabel opening dan tabel monthly_stock bulan berikutnya.
dan jangan lupa buatkan halaman stok opname dan masukkan tab bertingkat semua halaman yang serumpun dan masukkan sidebar sesuai rumpun
 -->
===============
<!-- 
setelah join, maka terjadi gap.
- data yang ditampilkan (gambar 2) tidak ada selisih kolom stock dan kolom movement, tapi gambar 1 ada seliish gap movement.
- pada kondisi seperti ini sumber bagaimana saya bisa memilih sumber kebenaran dan melakukan penyesuaian untuk yang lain. misal yang benar adalah stock, dan saya ingin movement log menyesuakan stock. buatkan modulnya -->
<!-- /inventory/stock/daily-recon/division, /inventory-material-daily, /inventory/stock/adjustment/division, /inventory/stock/division/reconcile
4 halaman itu ada modul adjustmen. 
ketika stok minus kemudian di adjustmen menjadi 0 atau lebih dari 0, lot nya juga ikut naik sejumlah kenaikan stock sehingga ada perbedaan antara stock dan lot.
Periksa apakah kondisi saat ini sesuai dengan analisaku>? -->
<!-- nah seharusnya ada guarding adjustmen di ke 4 halaman itu, jika adjustmen dari minus, maka menyesuaikan kenaikan mulai dari 0 saja. misal JERUK NIPIS stock -5, di adj jadi 0, maka lot tidak ikut bertambah. jika JERUK NIPIS stock -5 di adj jadi 3, maka lot hanya naik 3.
lakukan penyesuaian jika analisaku benar. bantah jika tidak tepat -->
<!-- sekarang halaman /inventory/stock/division/reconcile produk yang tidak ada stock tapi ada lot aktif, tetap dimunculkan dengan stock 0 dan lot ada. sehingga dapat dilakukan adj lot
nah sekarang jadi kelihatan dan lebih lebar lagi missmatchnya. ini penting karena miss lot ini juga harus di repair.
perbaiki:
- kolom pencarian belum berfungsi
- button "repair lot semua" seharusnya juga langsung me repair lot yang aktif tapi stok nya 0 -->
<!-- sekarang pindah ke component. apakah 4 halamana adjustmen component /production/component-daily-recon, /production/component-daily, /production/component-reconcile, dan /production/component-adjustments juga ketika stok minus kemudian di adjustmen menjadi 0 atau lebih dari 0, lot nya juga ikut naik sejumlah kenaikan stock sehingga ada perbedaan antara stock dan lot?4r
Periksa, kalau iya maka lakukan penyesuaian seperti bahan baku, guarding adjustmen di ke 4 halaman itu, jika adjustmen dari minus, maka menyesuaikan kenaikan mulai dari 0 saja. -->
<!-- lalu di /production/component-reconcile component yang tidak ada stock tapi ada lot aktif, tetap dimunculkan dengan stock 0 dan lot ada. sehingga dapat dilakukan adj lot. buatkan repair lot per child dan repair lot semmua untuk kasus serupa -->
<!-- lakukan pengecekan di halaman adjustmen seperti daily matrix, daily recon, reconcile, adjustment , yang mungkin bisa mempengaruhi bahan baku juga -->


<!-- /master/product divisi, klasifikasi dan kategori jadikan 1 kolom, mode stok dan status  jadikan 1 kolom, % hpp dan estimasi profit jadikan  1 kolom, icon kolom aksi jadikan 2 baris -->
<!-- cek backup git
cek ganti ip
cek server

finalkan generate stok gudang, bahan baku. component. pastikan cutoff dan membuat data baru stok dan lot nya sesuai
finalkan generate keuangan -->

<!-- setelah update ROLLBACK REFUND dan VOID, guardingnya terlalu ketat. ROLLBACK gagal karena stock minus. harusnya ROLLBACK tetap behasil dengan menambahkan ke stok yang minus sehinggu minusnya berkurang.
kasus disini adalah ada bahan baku yang habis atau minus, ketok di order POS menjadi minus atau minusnya bertambah. nah ketika void atau refund harusnya tetap rollback dengan mengembalikan ke posisi stok semula  -->

<!-- 1. ketika ada penalty yang sifatnya TIm (gambar 1), mestinya langsung masuk ke masing masing personil sesuai divisi / shift yang dipilih
2. hari ini Setiawan ambil PH att_daily HOLIDAY, tapi saya tes syncronisasi penalty, belum muncul di setiawan
3. di /attendance/pending-requests?q=&division_id=0&status=APPROVED ada data pegawai yang mengajukan absen manual. apakah di record / log / database absen pegawai terlihat log mana saja yang absen mandiri menggunakan gps mana yang melaluli pengajuan? saya cek di att_daily ada source_type PENDING_APPROVAL , nah seharusnya itu kena penalty sesuai MANUAL_ATTENDANCE. saya cek belum ada. -->

<!-- - modal Detail Kejadian Penalti (gambar 1) rapikan lagi. nama target dan lain lain diatas, tabel dibawah, jadi tidak perlu discroll

di /payroll/bonus?month=2026-07&tab=employee_daily, /payroll/bonus/monthly-detail/9/18?month=2026-07 dan /my/bonus, mestinya bisa menampilkan detail audit bonus per hari. misal pada hari apa, shift apa, ada omzet berapa, dibagi berapa orang, bagian saya berapa. agar fair dapat diketahui semua -->
<!-- 
tampilan tabel /payroll/bonus?tab=weights&month=2026-07 kolom status terlalu lebar. sesuaikan semua kolom agar proporsional dengan konten tapi tidak perlu scroll kanan kiri
/finance-reports/targets?tab=list&page=1 bulk hitung "Hitung Hasil Terpilih", target keuangan masih muter2 lama dan belum finish
/payroll/bonus?month=2026-07&tab=overview Generate Bulk dari Target Harian masih muter lama dan belum finish -->
<!-- estimasi penalty di /my masih 0
sekarang perbaiki logika detail audit bonus di /payroll/bonus?month=2026-07&tab=employee_daily, /payroll/bonus/monthly-detail/9/18?month=2026-07 dan /my/bonus apakah sudah mengadopsi time slice -->
<!-- lakukan perbaikan:
member : 
- tadi sudah saya tes pembayaran qris, tagihan yang muncul hanya untuk produk, ongkirnya belum tertagih. seharusnya nilai tagihan termasuk ongkir, kecuali gratis ongkir
- pesanan maasuk belum ada notifikasi , buat sound dan flare notifikasi di semua halaman seperti pada self_order -->

<!-- revisi modul order online.
member dapat memilih apakah orderan dikirim atau di self pickup / ambil sendiri. ketika dikirim ada pilihan lagi, ongkir di penerima atau ongkir ikut dihitung totalnya. jika ongkir di penerima maka nilai yang muncul di qris hanya nilai orderan, jika ongkir include maka nilai yang muncul di qris termasuk nilai ongkir. jika self pickup atau ambil sendiri maka tidak termasuk ongkir. -->
<!-- perbaiki di directory member dan directory finance terkait pengaturan order online nya -->
<!-- sekarang saya ingin membuat modul pengelolaan aset.
digunakan untuk mengelola aset namua
metode CRUD , tambah aset bisa dengan bulk , contoh untuk menambah gelas dengan model sama bisa langsung input 1 kali dan terdata x buah sesuai jumlah, dan bisa memasukkan foto asetnya
ada modul untuk penyusutan aset, jadi jika ada aset rusak pegawai wajib melakukan input serta alasan dan bukti fotonya
rekon aset setiap akhir bulan untuk mengetahui perkembangan aset, terutama jika ada aset baru dan aset rusak -->
<!-- gunakan templating sesuai coding standar
masukkan sidebar dan role matrix -->
<!-- kembangkan sesuai pengalaman dan pengetahuannmu
kejutkan saya
TAMPILAN /asset-management yang utama ditampilkan per produk yang sama. misal ada gelas latte 10 buah sama , maka tampilkan 1 baris. lalu bisa di lihat detailnya -->


<!-- /asset-management/recon harusnya menampilkan preview data sebelum di snaphot, jadi sebelum generate snapshoot bisa di cek dulu datanya sudah benar apa belum


rapikan lagi tampilan ketiga halaman itu termasuk halaman detailnya. buat UI nya menarik. buat tablenya benar benar rapi di semua kolom termasuk kolom aksi. ukuran gambar agar fixed sesuai form bukan sesuai gambar riil agar lebih rapi


lalu modul apa lagi yang menurutmu bisa ditambahkan di modul aset ini?
/asset-management/damage kolom aksi terpotong
/asset-management/recon buat tabelnya scrollable dengan filter baris, lalu pisahkan tampilan tabel nya dengan tab , jangan paksakan di 1 halaman
sekarang pindah ke /roastery/packaging-labels
- rapikan tampilan halaman index nya
/roastery/packaging-labels?
- Tasting Notes + Icon ukuran font belum bisa diatur
- berikan garis kotak diluar background label ketika di preview cetak, sebagai penanda potongan agar rapi
- /roastery/packaging-labels? buat tampilannya lebih menarik dan enak dibaca. kalau perlu pisahkan bagian dengan tab atau bagian agar rapi.
- tambahkan variasi jenis icon agar lebih bervariasi dan banyak, dan tampilkan icon nya di dropdown pilihan agar lebih memudahkan memilih gambar
- berikan opsi saat cetak untuk dapat menampilkan berapa gambar dalam 1 kertas, dan ukuran kertas yang bisa diatur. misal ukuran A4 atau A3 , dan dalam 1 kertas ingin mencetak berapa gambar sesuai ukuran label yang diseting. jadi ketika di preview cetak dan simpan atau download bisa langsung sesuai yang diinginkan
- /roastery/packaging-labels?new= /roastery/packaging-labels?edit= nama kopi bisa mengambil dari master produk divisi roastery
icon juga ikut pecah
/stock/daily-recon/division saya coba recon almond data berkurang tapi ada notif "Error: Unexpected token '<', ""
 -->

<!-- catat ya:
setiap kali selesai per fase, jelaskan dengan bahasa user apa yang sudah kamu perbaiki, lalu apa yang harus saya lakukan. dan apa yang harus saya coba untuk mngetest hasil perbaikan
sekarang review ulang perbaikan apa saja yang sudah kamu perbaik dari tahap pertama dan apa yang harus saya test dan lakukan
oke sql sudah saya run.
berarti revisi kedepan kamu haru lebih cermat dan berhati hati, ketika ada perubahan struktur maka pastikan semua UI terdapampak ikut berubah.
tahap revisi selanjutnya apa? -->

<!-- aku catat kesimpulan penting dari hasil perbaikan kita:
- Belum ada halaman operator khusus untuk melihat defisit atau membuka/menutup periode. Fondasinya sudah ada, tetapi layar dan hak aksesnya adalah tahap berikutnya.
/production/component-batches Pemakaian dan trace belum bisa dibuka
Yang Belum Dijanjikan
- Perbaikan ini tidak otomatis membersihkan mismatch lama dari bulan sebelumnya.
- Tidak semua proses bisnis sudah diuji dengan transaksi nyata, karena saya tidak membuat batch, adjustment, refund, atau POS sungguhan pada database Anda.
- Batch tetap dapat menampilkan pesan gagal jika bahan memang tidak cukup, tetapi pesannya seharusnya berupa alasan bisnis, bukan error JSON mentah.
1. Berarti sekarang sudah tidak ada stok minus ya? melainkan ganti dengan stok defisit? kalau benar demikian maka sesuaikan /dashboard yang sebelumnya menampilkan stok bahan baku / component minus menjadi stok defisit ini. CMIIW
2. /inventory/stock/deficits sifatnya akumulatif bukan? maksudnya kalau akumulatif berarti saya pakai angka defisit terupdate? dan tampilannya seharusnya data terupdate di atas. karena sebagai contoh ada beberapa baris NORI , dan tidak terlihat profilnya, jadi asumsi saya profil serta lot nya sama. CMIIW. jadi kalau sama yang dijadikan patokan adalah defisit terupdate. kemudian akan lebih efisien kalau dihalaman tersebut bisa langsung melakukan rekon langsung agar data yang dipilih tepat sesuai profil yang defisit. -->
<!-- 3.jelaskan fungsi dan alasan kuat perlunya modul Tutup Periode Stok ?


4. /inventory/stock/adjustment/division "Tambah Adjustment Bahan Baku" form "Cari item / Profile" , fallback pertama stok aktif divisi yang ada barangnya, baru fallback ke selanjutnya. dan untuk stok yang kosong tidak ada stok dan nilai uangnya, pastikan preview cost nya ada sesuai dengan catalog, jadi kita tidak menebak. karena ini saya coba preview NORI tampil banyak tapi Avail 0 Avg cost 0 semua, jadi bingung mau milih mana. cost ini seharunsya muncul di semua modul adjustment , fungsinya adalah ketika stok 0 kita tetap tau nilai katalog dari barang itu, jadi ketika ada adjustment plus tidak gagal melainkan memakai nilai sesuai katalog -->
<!-- - masuk recon dari halaman /inventory/stock/deficits tidak menampilkan data, misal nori saya klik recon maka data tidak tampil (mungkin karena dicari data sesuai profil). dan yang saya maksud recon langsung di halaman defisit tanpa mengarah ke halaman recon -->
<!-- - kemudian saya ambil contoh FRENCH FRIES FROZEN masuk defisit, padahal di stok ada, itu harus bagaimana?
- jika adjustmen menyentuh stok defisit, apakah defisit otomatis selesai? misal stok dan lot NORI 0, defisitnya 1000, lalu saya adjustment plus NORI 1000, apakah stok dan lot jadi 1000dan defisit 0?
- apakah defisit memperhatikan profil dan lot? misal defisit nori 1000, apakah defisit itu mengikuti profil atau lot tertentu? atau defisit bebas? yang akan selesai dengan adjustmen di profil manapun? lalu apakah PO SR ke bahan baku dan BATCH Produksi ke component bisa menyelesaikan defisit? -->



<!-- NOTE: SETIAP PERNAYATAAN DAN PERTANYAANKU TIDAK MESTI BENAR. KAMU BOLEH BANTAH
== maksud saya agar recon dapat dilakukan langsung di /inventory/stock/deficits. dan sekarang tombol recon tidak dapat di klik. tombol recon di /inventory/stock/deficits/detail/ juga tidak dapat di klik.
== recon yang ada di halaman diatas tujuannya hanya untuk recon defisit saja atau termasuk recon stok? harus jelas disini. karea seperti kasus FRENCH FRIES FROZEN ternyata kan ada stok tersedia tapi masih ada defisit. kalau saya recon defisitnya jadi 0 apakah stok nya juga ikut 0? dan misal stok sistem ada 10, apakah saya recon tetap di 10? atau bagaimana? dan mestinya di halaman defisit menampilkan juga stok dan lot existingnya. CMIIW -->
<!-- PERHITUNGAN hpp nya bagaimana ketika stok defisit? ketika.
maksud saya misal ada orderan, nah sementara NORI stok nya tidak cukup atau bahkan 0. bagaimana menghitung hpp dari transaksi yang disimpan? -->

<!-- modifikasi /inventory/stock/health berikan tab semua, gudang, component, bahan baku
- /inventory/stock/health kok malah hilang dari sidebar?
- koreksi **Nilai Stok / Lot yang nilainya berbeda bukan di koreksi nilai stok, karena halaman "Koreksi Nilai Stok" kosong. HALAMAN "Koreksi Nilai Stok" apa memang hanya menampilkan riwayat koreksi? saya  tanya ini.**
- **Recon Stok Fisik Gudang nbel**
- 
  di /dashboard NORI defisit, padahal di stock nya ada. dan mau saya recon tidak bisa karena "Stok fisik sekarang (GR)
  " ketika di cek di /inventory/stock/deficits/detail/ hasilnya 0. padahal di stock ada


  saat koreksi nilai BUMBU IRENG MADURA berhasil , tapi kenapa muncul warning "Belum bisa diposting. Nilai stok dan lot sudah cukup sama; tidak ada koreksi nilai yang perlu diposting."

  Recon Stok Fisik Gudang belum berhasil -->


  <!-- cek dashboard ada defisit minyak goreng dan breadcrumb. padahal di stok ada. apakah defisit itu per profil? 
  bagaimana kalau kasusnya penggunaan stok lintas lot lintas profil? apakah dihitung defisit?
  lalu bagaimana penyelesaian minyak goreng dan breadcrumb ini? 
<<<<<<< HEAD
  * * * * * /usr/bin/php /www/wwwroot/finance/index.php pos availability_queue_run 100 -->
<!-- buatkan halaman analisa dan saran dan tampilkan di dashboard, di atas Stok Produk Live POS.
datanya menganalisa bahan baku yang menumpuk yang merupakan component base/prepare tapi stok base / prepare tersebut kritis atau kosong.
misal stok Prepare DIMSUM FILL kosong. di formulanya ada ayam, sementara stok ayam di kitchen menumpuk banyak. maka berikan warning / analisa / dan saran untuk memproses DIMSUM FILL -->
=======


  * * * * * /usr/bin/php /www/wwwroot/finance/index.php pos availability_queue_run 100

  cron untuk semua halaman yang butuh cron. kamu cek semua
  
  cek semua halaman yang dobel
<!-- 
buatkan halaman analisa dan saran dan tampilkan di dashboard, di atas Stok Produk Live POS.
datanya menganalisa bahan baku yang menumpuk yang merupakan component base/prepare tapi stok base / prepare tersebut kritis atau kosong.
misal stok Prepare DIMSUM FILL kosong. di formulanya ada ayam, sementara stok ayam di kitchen menumpuk banyak. maka berikan warning / analisa / dan saran untuk memproses DIMSUM FILL
 -->

<!-- 
  Refactor void/reversal movement.
Pindahkan sisa runtime schema ke migration.
Tambahkan heartbeat cron dan audit otomatis bulan aktif.
Buat pengujian integrasi otomatis, lalu lakukan UAT manual akhir. -->
<!-- 

- ubah status PO masih gagal "Receipt ke stok divisi wajib punya material_id canonical. Pilih profile bahan baku yang terhubung ke material sebelum terima barang." cek **PO202608220012**
- SR gagal "**PO202608220012"**
- pemakaian voucher belum bisa dipakai jika nominal transaksi kurang dari kurang dari nilai voucher "Masukkan nominal pembayaran yang valid."
- adjustment gagal "**Gagal memposting adjustment stok."**
<!-- 
sepertinya perlu kamu cek RBAC setelah perubahan kita kemarin. karena seperti PO SR dari superadmin bisa, tapi dari user lain yang sudah diberi hak akses masih gagal. termasuk adjustmen , baik bahan baku maupun component di semua halaman adjustment


 <!-- -->

 <!-- kunci aset. agar tidak bisa diedit

Proses dihentikan: Akun WhatsApp dibatasi untuk pesan personal (kode 463). Antrean dihentikan; nomor ini tetap pending dan dapat dicoba ulang setelah pembatasan dicabut.

masih seperti itu setelah kirim 1 nomor?
apa tidak ada untuk mengatasi? itu kan sama saja polanya kamu kirim pesan manual yang sama ke beberapa nomor terpilih. form UI hanya untuk memudahkan saya. sementara proses backgroundnya kamu buat sedemikian rupa agar sama seperti kirim manual ke beberapa nomor
kenapa saya coba kirim pesan manual selain 085730012324 tidak bisa? -->

<!-- 
-  missmatch itu terjadi karena memang ada bug scirpt? atau leftover karena kemarin saya suruh repair data karena banya order gagal waktu server lag (di sever)
- apakah kalau server normal masih memungkinkan missmatch dengan kasus yang kamu jelaskan?
- lakukan perbaikan 1-5 mu, dan untuk repair data buatkan juga sql nya untuk saya run di server -->

sekarang aku ingin kamu memeriksa halaman modul /pos/printers , beserta semua halaman dan database terkait printer, serta hasil cetaknya. saya masih menemukan banyak data ambigu, tumpang tindih, yang akhir nya membingungkan dan tidak simple. padahal prinsip awalnya simple:
1. pengaturan koneksi printer per divisi / lokasi
2. pengaturan umum tampilan printer (terkait data data master misal nama outlet, logo , dan lain sebagainya)
3. pengaturan tampilan printer per divisi / lokasi, terkait layouting printer, data apa saja yang ditampilkan dan tidak perlu ditampilkan di masing masing lokasi
4. halaman panduan.
5. halaman lain yang menurutmu penting

nah coba lakukan analisa detail, dan buatkan saran perbaikian!



kunci source code untuk dijual, dengan licensi
pengaturan umum, upload logo, ganti nama, dll




rencana saya mau memperjualbelikan aplikasi kasir (finance) saya ini, dengan modul sumper komplit ini. termasuk APK POS yang sedang dalam tahap pengembangan.
konsep yang saya inginkan antara lain:
- beli putus, berarti customer membeli putus, dengan licensi terbatas. misal 1 akun dapat 3 licensi untuk digunakan di 3 device. berarti harus ada modul penguncian source code agar tidak bisa di pergunakan ulang atau malah diperjual belikan lagi
- leveling fitur, dengan fitur yang sudah ada sekarang, saya ingin membedakan harga di beberapa level, dengan pembatasan fitur fitur tertentu
- modul update. jadi customer bisa mendapatkan update jika saya ada update tersedia

sementara seperti itu. ada ide diskusi?


revisi tampilan cetak payment kasir menampilkan link reviewnya. seharusnya cukup qr code tidak perlu link. perbaiki


cek PH
jadwal PH kok malah dapat jatah PH, harusnya kan digundakan PH nya

guarding tanggal jadwal absen yang sudah lewat hari tidak bisa diganti