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



=======================
- /finance-reports/daily-overview, icon aksi tidak terlihat. freeze judul tabel dan buat scrollable. kurangi ukuran font dalam tabel agar terlihat semua

- /finance-reports/financial-estimation tambahkan total dibawah tabel. freeze judul tabel dan buat scrollable. 

- /finance-reports/rekap-rekening-harian freeze judul tabel dan buat scrollable. apakah sudah menghitung kasbon dan DP juga? kasbon aktif berarti dihitung sebagai tambahan saldo bersih, sementara DP aktif sebagai pengurang saldo bersih. betul bukan? CMIIW

================

kita pindah ke /role dan /roles/matrix-groups
- cek semua menu dan group. sesuaikan penataan rumpun dan urutan matrix-groups sesuai dengan posisi sidebar sekarang agar user lebih mudah menemukan modul mana untuk menu mana.
- modul tidak aktif atau yang tidak terpakai di sidebar atau di halaman, hapus saja biar tidak membingungkan. selama modul itu memang tidak digunakan. kecuali ada modul crud yang memang bukan menu halaman tapi bagian dari izin crud halaman, yang seperti itu perjelas lagi biar saya tau
- jika ada 1 modul / menu tambil lebih dari 1 kali di sidebar, sebutkan nanti saya tentukan mana yang dipakai
- cek jika masih ada halaman atau menu di sidebar yang belum ada di database baik halaman ataupun modul
- pastikan semua matrix-group izinnya berjalan sesuai dengan halaman masing masing dan sesuai user grup. karena ada kasus user A (misal MANAGEMEN) saya beri aksis untuk modul B full akses, tapi ternyata buka halaman saja tidak bisa, tidak punya akses.


saya belum run sql.
saya baru saja menata ulang sidebar. cek apakah masih relevan dengan sql yang baru kamu buat. kalau tidak sesuaikan ulang sql mu.


Duplikasi menu/page yang masih tersisa , berikan link nya yang jelas!
semua halaman yang rancu, yang masalah, yang tidak ada di sidebar, itu sebutkan linknya! jadi saya bisa cek!

==============


terkait temuanmu Duplikasi menu/page yang masih ada sekarang:
    /attendance/estimate dan /attendance/meal-calendar itu 2 halaman yang berbeda! pisahkan dan biarkan keduanya hidup.

    /attendance/schedules dan /attendance/schedules-v2 data yang ditampilkan memang sama, tapi tampilannya memang beda!  pisahkan dan biarkan keduanya hidup.

    /master/hr-contract dan /hr/contracts itu sama. gunakan /hr/contracts saja

    /pos/stock-live dan /pos/stock-commit-audit itu 2 halaman yang beda tujuannya!

    /production/component-daily dan /production/component-reconcile juga 2 halaman yang berbeda tujuannya

    /inventory/stock/division dan /inventory/stock/division/lot memang menampilkan 2 hal yang berbeda

    /inventory/stock/warehouse dan /inventory/stock/warehouse/lot juga menampilkan hal yang berbeda



terkait Halaman/menu yang memang bermasalah secara registry saat ini. perbaiki donk! itu semua menu dan halaman terpakai
    untuk /my/schedule halaman belum ada. buatkan sekalian! menampilkan jadwal harian selama sebulan masing masing pegawai

untuk Halaman aktif yang saat ini tidak ada di sidebar, sepertinya memang saya pernah memerintahkan merubah link atau route dengan nama yang lebih relevan
    - /pos/members ketika dibuka mengarah ke /loyalty/members. saya prefer /loyalty/members  (lebih relevan)
    - /inventory/stock/opname/division halaman sama persis dan sepertinya routing ke /inventory/stock/daily-recon/division. untuk hiperlinknya saya prefer /inventory/stock/daily-recon/division  (lebih relevan)
    - /procurement/purchasing-desk => /store-requests  (lebih relevan)
    - /purchase/account => /master/company-account  (lebih relevan)
    - /purchase/stock/opening => /inventory/stock/opening/warehouse (lebih relevan)
    - /dbtools/backup-guide dan /dbtools/replication-guide jadi 1 di /dbtools (CMIIW)
    - /my/schedule harusnya ada, buatkan juga halamannya
    - /master/component boleh dihapus karena sudah ada /production/component-masters
    - /master/relation/component-formula boleh dihapus karena sudah ada /production/component-formulas
    - /master/company-account ada di sidebar. /finance/accounts malah tidak ada. kalau saya prefer /finance/accounts lebih relevan namanya. boleh sesuaikan!
    - /master/payment-channel 404. hapus aja? 


Yang bukan sidebar page normal / rancu / internal:
    - /purchase/catalog/search ini modul bukan? atau helper pencarian di halaman tertentu? terpakai tidak? kalau tidak terpakai hapus.
    - /purchase/catalog/sync-core sudah tidak terpakai. hapus saja.
    - grp.finance dan grp.purchase kalau tidak ada pengaruhnya ke mana puun, hapus saja 



    sql sudah saya jalankan
    - /purchase/account -> /finance/accounts , di sidebar kenapa malah /master/company-account ?
    - di /sidebar/manage yang masih status nonaktif itu tidak terpakai kan? amanka kalau dihapus?
    - di /role, jadikan Audit Registry Hak Akses  dan tabel role sebagai tab yang berbeda. tab utama tabel role nya
    - saat edit di /roles/matrix, misal Point of sales sudah saya klik semua, tapi kenapa masih 21/22? pembelian 9/13?
    - di /roles/matrix bagian inventory, urutkan sesuai sub rumpunnya lagi, (gudang, bahan baku, component)



buat “daftar final yang aman dihapus” untuk page/menu legacy yang sudah benar-benar tidak punya pemakai lagi.





