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

KITA LANJut tahap berikutnya. 
Pertama saya ingin memodifikasi kolom penyesuaian yang sudah dibuat untuk stok gudang dan divisi (rollup), dari yang sebelumnya sudah dibuat untuk spoil dll, saya butuh mengakomodir penyesuaian jadi 5 komponen dibawah:

1) WASTE
cancel_order → order sudah dibuat, dibatalkan
kitchen_error → salah racik/overcook
overproduction → masak kebanyakan
spillage → tumpah
prep_trim_excess → trimming berlebihan (di luar standar)
expired_opened → bahan sudah dibuka lalu tidak terpakai
other


2) SPOILAGE
expired → lewat tanggal
temperature_abuse → chiller/freezer tidak stabil
contamination → terkontaminasi
overstock → beli terlalu banyak
improper_storage → salah penyimpanan
other

3) PROCESS_LOSS
defrost_loss → susut saat thawing
trimming_standard → potong lemak/tulang sesuai SOP
cooking_loss → susut saat masak
evaporation → penguapan
brew_loss → residu kopi/teh
absorption_loss → bahan terserap saat proses
process_residue → sisa proses yang wajar
other


4) VARIANCE
over_usage → pemakaian aktual lebih besar dari standar resep
under_usage → pemakaian aktual lebih kecil dari standar
unrecorded_usage → pemakaian belum tercatat
counting_error → salah hitung stok opname
system_mismatch → beda sistem vs fisik
theft_suspected → indikasi kehilangan
unknown_shrinkage → selisih tidak jelas
other

5) Adjustmen (penyesuaian tambah)

komponen diatas nanti digunakan saat input penyesuaian dengan enum sesuai rincian dan tambahkan form keterangan. pada tabel stok / opname /  cukup tampilkan kategorinya (nilai dan rupiahnya) sebagai kolom .
================================


nyambung

kita sudah buat skema purchase ke gudang dan ke divisi. tapi kita belum buat skema data awal gudang dan divis. 
data ini ini digunakan sebagai data awal pertama kali aplikasi ini digunakan, dan ketika setelah generate stok opname tiap akhir bulan.

skema input nya bisa manual langsung di halaman data awal, bisa juga ketika generate stok opname + stok awal. ketika generate stok opname otomatis generate stok awal juga. tombol generate ini ditampilkan de semua halaman terkait stok gudang atau divisi

- buat tabel stok opname (baik gudang dan divisi jika belum ada)

- stok awal / opening sudah ada belum ? perlu dibuat tabel atau tidak ? kalau dibuat pastikan nyambung dengan tabel stok lainnya.
- stok opname yang tersimpan saat generate adalah semua data keluar masuk dalam 1 bulan. baik sisa nya masih ataupun sisanya 0. harus ada guard jika ada stok minus, jadi harus dibetulkan dulu minusnya

- stok awal yang tersimpan adalah hasil dari generate yang stok akhir bulan ini / stok awal bulan berikutnya tidak sama dengan 0. misal saya generate stok opname bulan april, maka stok awal mei hanya dicatat yang stok awalnya lebih dari 0.

pastikan mengakomodir konsep profile yang sudah kita tetapkan

proses perlahan dan kejutkan saya
==========================
==========================


kalau kamu bilang  "di pencarian opening, hasil profil diprioritaskan dari stok dulu." padahal di stok divisi tidak ada profile 55856a... adanya di stok gudang. dan kita sedang transaski stok divisi bukan gudang.

- fallback utama harus ke catalog dulu

- bagaimana menurutmu baiknya untuk konsistensi apakah semua profil harus sesuai yang ada di catalog? jiika data ada di katalog maka fallback ke katalog, jika tidak ada maka tambahkan data baru ke katalog. bagaimana menurutmu?

ya lakukan
- Remap historis key non-catalog -> key catalog berbasis exact identity (dalam 1 transaksi DB, aman, idempotent).




- Store Request
store request adalah proses permintaan barang dari gudang menuju divisi (bar / kitchen / office baik reguler maupun event)

konsep store request tetap membawah data profil mulai dari nama sampai dengan keterangan dan exp (semua data) menuju stok divisi.
form ui bisa kamu adobsi dari core halaman store-requests dan store-requests/create. untuk status juga sama.
ketika ada void berarti data dikembalikan ke gudang.
fitur halaman sama seperti halaman lainnya. (search ajax, filter, card ringkasan, dan lai lain)

coba eksekusi, tanyakan jika masih ada yang kurang jelas


==========================
- pengajuan divisi
ini adalah halaman pengajuan PO dan SR seperti pada core division-requests.
adopsi dengan penyesuaian dan penyempurnaan yang memang diperlukan. perbaiki yang masih bug, tingkatkan yang harus ditingkatkan.
mulai dari input, proses verifikasi, cetak laporan, dan lainnya

===========================


- Adjusment atau penyesuaian
halaman penyesuaian stok gudang dan stok divisi. dengan alasan kategori dan alasan ksesuai yang ditentukan.

1) WASTE
cancel_order → order sudah dibuat, dibatalkan
kitchen_error → salah racik/overcook
overproduction → masak kebanyakan
spillage → tumpah
prep_trim_excess → trimming berlebihan (di luar standar)
expired_opened → bahan sudah dibuka lalu tidak terpakai
other


2) SPOILAGE
expired → lewat tanggal
temperature_abuse → chiller/freezer tidak stabil
contamination → terkontaminasi
overstock → beli terlalu banyak
improper_storage → salah penyimpanan
other

3) PROCESS_LOSS
defrost_loss → susut saat thawing
trimming_standard → potong lemak/tulang sesuai SOP
cooking_loss → susut saat masak
evaporation → penguapan
brew_loss → residu kopi/teh
absorption_loss → bahan terserap saat proses
process_residue → sisa proses yang wajar
other


4) VARIANCE
over_usage → pemakaian aktual lebih besar dari standar resep
under_usage → pemakaian aktual lebih kecil dari standar
unrecorded_usage → pemakaian belum tercatat
counting_error → salah hitung stok opname
system_mismatch → beda sistem vs fisik
theft_suspected → indikasi kehilangan
unknown_shrinkage → selisih tidak jelas
other

5) Adjustmen (penyesuaian tambah)


sesuaikan jika ada yang perlu disesuikan. format kolom buat sesuai kebutuhan profesioanal. tambahkan juga kolom keterangan.

============================


- mutasi stok 
- halaman mutasi rekening


=============

skema HR, absensi, payroll yang akan kita buat, dengan yang sudah kamu plannig dengan konsep saya. kita buat dan rapikan bertahap.


adopsi data pegawai dari core:
- dimulai dari data pegawai. data pegawai bisa diadopsi dari core, beserta dengan tabel relasinya (divisi, jabatan dan lain lain)
- jangan adopsi kolom yang ada "abs" nya pada core.org_employee karena itu data legacy dari dashboard.
- data yang digunakan untuk perhitungan adalah gaji_pokok, tunjangan (ini ganti dengan tunjangan_jabatan), tambahan_lain (ganti dengan tunjangan_lain)
- hapus is_kasir, kasir nanti di set dari role akses
- lakukan penyesuaian lain yang diperlukan
- divisi, master shift, jadwal shift pegawai dan semua data lain diambil dari core dengan penyesuaian yang diperlukan
- di core pengturan PH pegawai dobel, jadikan 1 saja.
halaman pengaturan absensi seperti di core dengan penyesuaian tambahan. antara lain:
- pengaturan skema absen harian (berdasarkan jam kerja hari masuk) atau absen bulanan (gaji dihitung penuh lalu dikurangi jam / hari tidak masuk)
- pengaturan hari kerja dalam 1 bulan
- pengaturan perhitungan gaji, apakah keterlambatan dihitung pengurangan total Take home pay atau dihitung pengurangan gaji pokok nya saja (seperti core sekarang), apakah tunjangan diberikan penuh ketika pegawai masuk (tidak peduli terlambat atau tidak), atau tunjangan tetap dipotong jika terlambat
- pengaturan batasan waktu absen (sebelum saat jam masuk dan sesudah jam pada jam pulang)
- pengaturan uang makan (bulanan atau custom)
- pengaturan lain yang diperlukan dan yang sudah ada sekarang selama tidak bertentangan
- pengaturan PH , uang makan adopsi 

sekarang yang sudah kamu buat, konsepkan ulang mulai dari pengaturan, absen sampai dengan gajian dan bonus. tuliskan dengan bahasa user alurnya. lalu saya teliti lagi baru kita eksekusi


====================
tambahan info update agar tidak miss:
Pengaturan absen:
- BUKA CEK IN misal di set 30 menit itu artinya 30 menit sebelum jadwal shift masing masing pegawai ya, bukan 30 menit sebelum jam operasional, karena kita ada beberpaa shift
- batas tutup cek out belum ada?
- Potongan telat dan alpha, jika di set no maka dihitung prorata sesuai pengaturan (apakah hanya gaji pokok atau plus tunjangan)
- PH auto hadir saat buka dan PH tetap wajib itu kan berkebalikan, bukan seharusnya itu pilihan salah satu?

- periksa lagi pengaturan di core yang masih relevan dan bisa dipakai (seperti pengajuan dan approval absen. pengajuan absen di core hanya 2 opsi, self atau jabatan terntentu. ubah polanya jad 3 opsi, self, jabatan terntentu, self dan jabatan tertentu. untuk approval tetap buat 3 tingkat)
- halaman absen seharusnya dibuat dulu di dashboard employe kan??. 
- rapikan tampilan

- kalau sudah boleh lanjutkan 1 , 2 , 3

========
tambahkan user di users, dengan username dan password sesuai data core.org_employee dan link kan pegawai id sesuai pegawai id di db_finance.org_employee.x`

==============================
================================

1. Master HR (wajib beres dulu)

    Master pegawai belum punya blok data kontrak lengkap (di finance sekarang fokus data inti pegawai).
    Belum ada modul manajemen kontrak seperti di core:
        hr_contract
        hr_contract_template
        hr_contract_approval
        hr_contract_signature
        hr_contract_comp_snapshot (+line)
    Halaman employee portal untuk kontrak masih placeholder (my/profile dkk belum final).

2. Pengaturan absensi

    Setting inti baru sudah ada di finance (scope pengajuan 3 opsi, approval 3 level, PH mode, checkout close, dsb).
    Tapi beberapa pengaturan dari core yang masih relevan belum dibawa:
        photo_max_width
        photo_quality
        overtime_round_minutes
        meal_payment_cycle versi core
        transfer_excludes_meal (jika masih dipakai proses payroll transfer)

3. PH subsystem (masih gap besar dibanding core)

    Di core ada:
        att_ph_policy
        att_ph_eligibility
        att_employee_ph_ledger
    Di finance belum ada tabel/halaman PH ledger & eligibility penuh.
    Saat ini baru ada mode PH di policy + shift PH, tapi belum full ledger, expiry process, logs seperti core.

4. Alur pengajuan/approval absen

    Mapping submitter/verifier sudah ada dan terisi.
    Halaman attendance/pending-requests masih monitoring/listing.
    Aksi proses approval berlevel (approve/reject per level + jejak audit + efek ke att_daily) belum lengkap.

5. Master operasional absensi

    Shift, lokasi, holiday sudah ada.
    Pengelolaan jadwal shift massal/spreadsheet (seperti core schedules_v2) belum ada di finance (baru monitoring jadwal).

-  perbaiki tampilan attendance/schedules (lihat gambar)

- absen belum mengecek lokasi dari user. harusnya di cek lokasi gps nya dengan batas radius sesuai yang ditentukan bukan?

- jadwal absen versi 2 belum terlihat di sidebar

- estimasi gaji masih 0. saat pegawai melakukan absensi, apakah logikanya sudah otomatis menghitung gaji sesuai jam dan pengaturan absensi?


========
- absen cekin ataupun cekout bisa lebih dari 1 kali, dan hanya diambil yang dampak nilai gajinya paling besar (untuk checkin jika sebelum jam shift ambil terdekat, jika terlambat berarti ambil absen pertama. Jika cekout sebelum jam pulang ambil paling mendekati jam pulang, paham maksudku kan?).
- attendance/schedules biarkan seperti semula , cukup tambhakan CRUD, dan rapikan kolom aksinya. tampilannya masih berantakan. sedangkan attendance/schedules-v2 buat seperti pada core attendance-masters/schedules-v2 , dimani create edit delete bisa langsung dilakukan di halaman seperti pada microsoft excel (paham maksudku kan?)


perbaiki dan lanjut ke:
- detail timeline approval per-level (L1/L2/L3 full riwayat),
- submit pengajuan dari /my/leave-requests sampai masuk ke approval center.


- attendance/schedules kolom aksi masih ngebug. cek lagi icon tidak terlihat
- /attendance/schedules-v2 freeze kolom tanggal ketika tabel di scoll ke bawah
- attendance/estimate belum muncul datanya. 
- my/payroll perhitungannya apakah sudah sesuai pengaturan absen ???
- seharusnya data nilai gaji masing masing pegawai yang ditampilkan pada attendance/estimate dan my/payroll sama bukan ???

- setelah itu lanjutkan ke halaman submit employee portal my/leave-requests end-to-end (create request -> masuk pending -> approval L1/L2/L3 -> apply ke daily).



========

- /attendance/estimate harusnya tampil rekap semua pegawai dalam 1 bulan, buat khusus halaman detail nya untuk melihat data harian masing masing pegawai. jadi bukan sama plek tampilannya dengan my/payroll, maksud saya yang sama itu perhitungan gajinya. seperti di halaman attendance/payroll-monthly pada core. tampil secara jelasa semua data dan pembagian gaji nya yang diperoleh.

- saya tes absen atas Nama FAIRUZ, saya buat pulang cepat tapi di att_daily.early_leave_minutes kenapa 0? dan sepertinya belum ada kolom perhitungan gajinya? padahal menurut saya harus ada generate gaji hariannya sesuai pengaturan dan sesuai jam hadir saat itu, agar ketika ada perubahan kontrak data yang sudah tergenerate tidak berubah. paham maksud saya ya?

==============

- saya test FAIRUZ, tadi sudah checkin masuk, lalu saya cekin lagi. di att_presence tersimpan (sudah benar), di att_daily checkin_at berubah jadi jam saat absen lagi, harusnya jangan ubah karena jam absen sebelumnya lebih mendekati jam masuk. di late_minus memang tidak bertambah, tapi di jam checkin berubah. harusnya tidak berubah. kecuali kalau untuk jam pulang biasanya absen kedua dan seterusnya lebih mendekati jam pulang (kalau early leave) 
- uang makan mestinya langsung dihitung ketika sudah absen masuk (tidak perlu menunggu absen pulang), tapi di net harian kalau di pengaturan uang makan custom maka nilainya dikurangi / tanpa uang makan. jadi tambahkan kolom sebelum net harian yang merupakan total take home paynya, lalu di net harian yang tanpa uang makan

- my/leave-requests tampilkan jadwal shift pegawai bersangkutan sesuai tanggal yang dipilih (ketika mau input pengajuan)

=====================

- MODIFIKASI attendance/daily dan my/attendance agar menampilkan data sesusai database yang sudah kita modifikasi di att_daily
- gaji dan tunjangan jangan dihitung dulu sebelum cekout, kan belum dihitung telat atu tidaknya, belum terlihat menit kerjanya

======================

- tambahkan lembur di pengaturan absen attendance/settings. lembur dihitung otomatis atau pengajuan. kalau otomatis berarti dihitung dari jam absen pulang (dihitung jam pembulatan ke bawah), kalau di set manual berarti lembur di input manual oleh pegawai yang diberi hak akses input lembur. jadi kalau di set manual, walaupun pegawai absen lebih pulang lebih beberapa jam dari seharusnya, overtimes_minute seharusnya 0, dan tidak diperhitungkan dalam work_minutes.
- di pengaturan absen Uang Makan di set custom, jadi harusnya Net tidak dihitung uang makannya, cukup di gros, betul kan?
- data yang ditampilkan di attendance/estimate seharusnya sama dengan data di atas ( data nya ya bukan layoutnya) 
- modifikasi attendance/daily tambahkan tab untuk rekap (bukan harian) lalu bisa di lihat detailnya, jadi nanti ada 2 tab, rekap per pegawai per bulan dan harian
- approve gaji manual dari pengajuan belum dihitung seperti absen manual
=========================
- pengajuan bisa di overide, kalau sudah L3 berarti langsun acc
- attendance/pending-requests bulk approve
- cek semua button di kolom aksi, masih banyak yang terpotong dan tidak terbaca teks nya (apa tidak ada templatenya?)
- percantik semua tampilan modal alert, jangan alert mentah begitu  tapi buat tampilan yang menarik

- buatkan spinner di setiap tombol transaksi agar kelihatan bahwa transaksi sedang proses
=====================
- buatkan halaman form CRUD input lembur, tambahan , pengurangan. lembur diinput tanggal dan jam lemburnya baru dihitung nilainya. tambahan dan pengurangan adalah tambahandan pengurangan  gaji manual , bisa apa saja.

- perbaiki dan percantik sidebar/manage, buat agar bisa di expand collapse di  menu sub menu nya. proses drag n drop masih kurang smooth dan susah.
=================
- revisi skema lembur. buatkan dulu standar lembur per jam (bisa beberapa, misal 7.000, 8.000,), lalu di modul input lembur tinggal pilih standar lembur dan input jam nya, simpan dapat dihitung nilai lemburnya. Nilai lembur masuk ke att_daily nggak?
- my/payroll belum dipisah gross dan net nya seperti di my/attendance
- attendance/schedule-v2 berikan warna berbeda untuk kolom hari ini
=============

- attendance/pending-requests rapikan lagi kolom aksi. approve dan override di klik berputar terus tidak berhasil. perbaiki!
====================


kita pindah ke modul PH

- buatkan generate master/att-holiday dalam 1 tahun berdasarkan kalender seperti di attendance-masters/holidays generate
- pengaturan tentang PH mengikut di pengaturan absensi ( cara mendapatkanya, expirednya, cara absensi saat ph, dapat uang makan dan bonus atau tidak, )
- assigment PH ke pegawai belum ada (pengaturan pegawai yang berhak mendapatkan PH)
- halaman managemen PH, laporan PH, log, rekap, dan lainnya



ada data history pencairan uang makan per nama per tanggal, agar tidak ada pencairan ganda. jadi ditolak ketika ada tanggal yang sudah dicairkan mau dicairkan lagi
