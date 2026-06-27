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
inventory/stock/division/reconcile buatkan fitur audit missmatch nya dimana (bisa total atau per bahan) dan buatkan tombol repairnya 


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


4. Loyalty ke kasir
Setelah payment stabil, baru sambungkan:

redeem voucher
earn point
earn stamp
issue voucher
Kenapa belakangan:

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
produk di kasir baca availability dari cache DB/override operasional
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


v cek PH belum masuk gajinya
- laporan utang piutang
v laporan kasbon
- laporan keuangan


sekarang buatkan modul Utang dan modul Piutang. halaman ini harus memepengaruhi keuangan dan muatasi keuangan.
utamh init kepada pihak luar. sementara piutang adalah pihak luar hutang kepada kita.
buatkan halaman khusus penerima / pemberi hutang. pernerima / pemberi bisa sebagai member. modul tambah penerima/pemberi dibuatkan di halaman tersendiri dan ada modul tambah penerima / pemberi di saat tambah utang / piutang jadi tidak perlu pindah halaman.
berikan fitur  ketika input utang / piutang tidak mempengaruhi keuangan karena sudah terjadi sebelum aplikasi dijalankan (paham maksud saya kan). jadi misal saat ini saldo BRANKAS 10.000.000, saya input utang 1.000.000 tanggal ini, maka saldo jadi 11.000.000. tapi ada opsi saldo tetap karena hutang sudah dilakukan sejak lama. artinya saldo 10.000.000 itu sudah termasuk hutang.

masukan halamannya ke database modul dan sidebar rumpun keuangan 


sekarang saya ingin membuat laporan keuangan (harian, bulanan , tahunan) yang rapi dan tertib sesuai standar akuntansi dan keuangan kafe, yang berguna untuk melakukan analisa, yang mencakup belanja (bahan baku, operasional, dan lainnya), store request, pembayaran gaji, kasbon, utang, piutang, estimasi gaji berjalan.

terkait rekening bank jika saat ini saya lihat saldo rekening diambil dari fin_company_account. disitu tidak ada cut off per bulan. bagaimana jika nanti kita ingin melihat laporan keuangan bulanan?


saya juga ingin membuat target bulanan dan target harian, yang menganalisa pengeluaran dan pendapatan termasuk estimasi gaji berjalan. target ini nantinya akan digunakan sebagai batas untuk pegawai bisa mendapatkan bonus. ada ide?


/finance-reports/targets susah dimengerti, bahasanya terlalu teknis untuk user. ubah bahasa saat buat target lebih manusiawi. tambahkan tab panduan juga




karena sudah sampai sini, baiknya kita buat modul bonus sekalian ya?
modul bonus sudah pernah dipakai di directory core tapi belum sempurna. kita sempurnakan disini.

coba cek directory core / payroll-bonus dan halaman turunannya.
bonus dipengaruhi oleh kinerja pegawai dan target keuangan
aturan bonus antara lain di core /payroll-bonus/rules dengan penyesuian yang menurutmu diperlukan.
bonus bisa berbeda masing masing pegawai. masing masing divisi dan jabatan bisa diberi skor beda. 
bonus bisa dikurangi dengan penalti. seperti pada core / payroll-bonus/penalty-types.
bonus juga memperhatikan pengaturan absensi yang dibuat (misal pegawai PH tidak dihitung bonusnya pada hari itu)
tambahkan kedalam aturan pegawai ambil PH juga dikurangi poinnya.
poin bonus didapatkan beda beda tergantung jam orderan saat shift pegawai (misal shift 1 ramai, maka pegawai yang hadir dalam rentang shift itu dapat bonus sesuai proporsi yang ditentukan dalam shift itu sesuai omzet dalan rentang jam shift itu)
bonus tetap memperhatikan target, agar pegawai juga semangat dalam mengejar target

penalti untuk bonus juga bisa diinput manual oleh superadmin dengan kejadian tertentu, misal :
- belum follow ig namua
- belum share story / taging ig namua
- penalty personal dan tim, misal ketika pagi hari saya menemukan ada area kitchen masih kotor, maka semua tim kitchen ya shift terakhir semalam dapat penalti (manual)


- tambahkan komponent waktu penyajian dalam faktor penentu bonus.

%bonus yang dibagi juga ditentukan dalam pengaturan.
pisahkan pengaturan target dan pengaturan bonus, tapi hubungkan datanya


lalu buatkan juga modul penilaian 360 untuk rekan yang hadir di hari yang sama.
penilaian dilakukan dengan memberi bintang 1 - 5 dengan disertai alasan.
form penilaian muncul setelah pegawai melakukan absensi.
penilaian hanya dapat dilihat dan dimoderasi oleh superadmin. dan dari hasil penilaian ini nanti superadmin bisa memberi pengurangan atau penambahan poin pada pegawai tertentu.

setelah ini baru kita bahas laporan keuangannya
kejutkan saya!





missmatch taruh dashboard
v cek metode pembayaran self order
v cek gambar produk member
v cek extra member self order
v cek verifikasi self order sudah potong stok?


=======================


extra!
- Perbaiki input grup extra
- perbaiki add master extra add
- cek list produk extra ke grup extra dari grup
- cek list produk ke extra grup dari grup 
- extra yang bahan baku 



/pos/self-order/orders tolak transaksi. verifikasi ke draft (agar bisa di edit)
belum clear

tambah extra oat


cek dashboard stock habis harus sesuai sumber resep
cek resep prepare






OKe kita perbaiki target dulu 
- Bonus disiapkan dan % laba untuk bonus itu bagaimana ? diisi apa? bagaimana jika saya ingin membagi bonus 3% dari omzet harian yang target harian yang tercapai, tapi baru bisa cair jika target bulanan tercapai? misal minimal omzet 3 juta maka 3% untuk pegawai dibagai proporsional sesuai ketentuan bonus. tapi bonus baru dapat cair jika target bulanan tercapai
- sesuaikan halaman tabel, buat scrollabel dan freeze di judul kolom
- sesuaikan ukuran form input agar tidak terpotong
- bagaimana jika saya ingin membuat indikator bahan baku mengendap di akhir bulan? baik gudang maupun divisi
- bagaimana jika saya ingin membuat persentase profit di akhir bulan berdasarkan estimasi harian belanja, pendapatan , dan estimasi gaji?
- bagaimana jika saya ingin pegawai dapat melihat target dan realisasi dari yang sudah ditetapkan agar bisa melakukan evaluasi untuk mencapai target bonus dapat cair?


ok sekarang coba buatkan sql untuk skema bonus yang saya maksud sebagai contoh agar tidak bingun kedepan. skemanya:
1. target harian 3.000.000
2. target bulanan estimasi keuangan (omzet - purchase - gaji) 10.000.000. 
3. laba dibagi 3% dari omzet harian yang mencapai target


Lalu tambahkan aturan bonus pegawai seperti pada directory core (database core), /payroll-bonus/rules?page=1&per_page=25&q=  dan /payroll-bonus/rules/edit/1?ctx=employee
sesuaikan dengan pola dan database yang sudah kita buat

lalui dimana generate pool hariannya?



Template Target Harian Omzet  DAILY status nya masih dibuat DRAFT. kenapa demikian?

bobot ala ctx=employee  dihalaman mana? saya belum menemukan.

revisi dulu halaman halaman berikut agar lebih enak baca:
/payroll/bonus?month=2026-06&tab=overview
/payroll/bonus?month=2026-06&tab=rules
/payroll/bonus?month=2026-06&tab=penalties

buat tampilannya agar lebih enak dibaca.
tampilan utama hanya tabel data, untuk form input dibuat dalam bentuk modal
tambahka juga button detail di kolom aksi selain button yang sudah ada untuk dapat melihat detial data yang sudah diinput
buat tampilan tabelnya scrollable dan freeze di judul tabel
khusus /payroll/bonus? perlu dilakukan pemisahan tampilan (mungkin dengan tab) agar tidak terlalu jauh kebawah




/payroll/bonus?month=2026-06&tab=penalties tab master penalti, lakukan penyesuaian tampilan seperti yang lainnya agar lebih enak dibaca

buatkan buatkan UI khusus Bobot Bonus untuk divisi / jabatan / pegawai / shift


ubah target DAILY agar benar-benar bisa dipakai engine, jadi statusnya masuk akal untuk ACTIVE bukan sekadar template


lalu buatkan halaman khusus di tab /finance-reports/targets yang menampilkan target dan realisasi, berupa data dan grafik. data ini nanti yang digunakan sebagai acuan bonus pegawai.
tambahan : untuk target berupa Profit estimasi (yang sudah kita buat), data diambil seperti pada halaman /finance-reports/financial-estimation ketika gaji bulan itu belum tergenerate, dan ketika gaji sudah tergenerate data diambil dari Pendapatan (dikurangi refund) dikurangi pengeluaran termasuk gaji 