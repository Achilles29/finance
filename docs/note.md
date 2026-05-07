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
