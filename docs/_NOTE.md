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




kita pindah ke component dulu., lakukan penyesuaian untuk /component-reconcile. ubah struktur kolom sperti pada /inventory/stock/division/reconcile.
tambahkan juga logika logika pengecekan serta reparing seperti pada bahan baku yang sudah kita buat kemarin

buatkan reclass lintas profil / lintas lot di bahan baku dan component


ubah data yang ditampilkna jadi montly stock, lot FIFO, movement log, selisih , status. lalu buat baris nya bisa di expand per child lot jika lebih dari 1 lot. dan buat fungsi repair stock, movement dan log nya per baris

cek CHICKEN CUBE 40


kita pindah ke component stock dan lot. cek di /production/component-reconcile , bahan CHICKEN CUBE 40.
stock 25, Lot Fifo 30. setelah di repair Lot FIFO malah jadi 42. coba cek dulu kenapa demikian.
lalu harus ada beberapa opsi reparir tergantung kasus
1. Repair monthly stock berdasarkan movement (sudah ada, tapi saya tidak tau apakah masih ada bug)
2. Repair Lot FIFO, ini ada, tapi belum jelas bagaimana SOP nya. karena tadi saya repair dari 30 malah jadi 42. semakin jauh dari monthly sock
3. Adjustmen manual saldo, ini sudah ada tapi belum berfungsi. ini harusnya nyambung ke modul adjustmen dengan model daily recon
4. harus ada adjustmen Lot, ini sebagai senjata terakhir jika lota dan stock berbeda, dan sudah tidak bisa lagi di repair dengan modil repair yang ada.



cek juga:
konsep component lebih simpel dari bahan baku (inv divisi)
di bahan baku ada montly_stock, 1 material_id bisa lebih dari 1 baris jika profile nya berbeda. lalu 1 profile bisa lebih dari 1 lot.
sementara di component lebih simple, tidak profil yang component dan lot.
1. bagaimana perhitungan cost di stock? apakah sudah berdasarkan jumlah total cost lot?
2. bagaimana perhitungan hpp live di pos live dan recipe dan formula yang menganduk component? dari cost di stock atau cost di live?
3. apa plus dan minus konsep component dibanding bahan baku?
4. apakah bisa bahan baku memakai konsep component agar lebih simple namun tetap aman untuk untuk profile yang berbeda?

================
- adjustment stock di /production/component-reconcile belum berfungsi. icon adjustmennya harusnya sampai dengan ke lot jika punya lebih dari lot / punya child. perbaiki
- jadi untuk masalah chicken cube penyelesaiannya bagaimana? ada modul repairnya? atau dengan adjusment lot?
- buatkan adjustmen lot nya
- nah untuk monthly avg cost bisakah dibuat repair menyesuaikan cost lot aktif? atau lebih bagus setiap transaksi (batch, adjustment, pos, void, refund, dan lainnya jika mungkin masih ada) menghitung ulang avg cost berdasarkan lot? atau bagaimana menurutmu?
- HPP live  harusnya ikut lot cost donk, kan kita pakai FIFO.


- /production/component-formulas/detail/77 cost live dari mana? padahal di /inventory/stock/division AYAM DADA FILLET cost avg lot aktif 44 

- /master/relation/product-recipe/182 CHICKEN CUBE 40 kenapa COST LIVE nya Fallback Std ? padahal stok CHICKEN CUBE 40 ada.

- /pos/stock-live duo platter HPP Live di CHACE DB dan LIVE CALC kenapa 0?

- audit jalur fallback component supaya commit POS yang gagal issue lot tidak lagi meninggalkan selisih movement vs lot.

-=====================================


v sesuaikan /production/component-daily-recon agar muncul cost hpp / harga total sampai dengan masing masing childe
- tombol adjustment ada tapi di klik masih tidak bisa
- lot-only adjustment dimana tombolnya kok g ada? harusnya muncul di kolom aksi sampai ke childnya juga di reconcile
- /master/relation/product-recipe/182 cost live CHICKEN CUBE 40  2.119,93 Lot Aktif FIFO, padahal di /production/component-lots?q=PREP-DASH-00001&status=OPEN&location_type=REGULER&division_id=3&type=PREPARE lot aktif 2.112,00. bagaimana menurutmu?
- operasional live atau /pos/stock-live ya lebih tepat ke FIFO juga donk, kan itu nanti untuk snapshot hpp live saat transaksi jadi bisa dianalisa keuntungan bersihnya tiap produk. benar monthly hanya untuk ringkasan laporan di UI stock saja.

- /production/component-formulas/detail/77 cost live 45,64 , padahal di /inventory/stock/division AYAM DADA FILLET lot aktif HPP live 44. bagaimaba penjelasanmu?

- modul audit/repair otomatis untuk drift component per kasus

- sql 18a :
CREATE TEMPORARY TABLE tmp_component_latest_movement AS
SELECT
  m.location_type,
  m.division_id,
  m.component_id,
  m.uom_id,
  ROUND(MAX(COALESCE(m.qty_after, 0)), 4) AS latest_qty_after_guess,
  ROUND(SUM(COALESCE(m.qty_in, 0)) - SUM(COALESCE(m.qty_out, 0)), 4) AS net_movement_qty,
  SUM(CASE WHEN COALESCE(m.notes, '') LIKE '%Lot fallback:%' THEN 1 ELSE 0 END) AS fallback_rows,
  ROUND(SUM(CASE WHEN COALESCE(m.notes, '') LIKE '%Lot fallback:%' THEN COALESCE(m.qty_out, 0) ELSE 0 END), 4) AS fallback_out_qty
FROM inv_component_movement_log m
GROUP BY m.location_type, m.division_id, m.component_id, m.uom_id
> 1054 - Unknown column 'm.qty_after' in 'field list'
> Time: 0s

===============
- /production/component-daily-recon ganti judul sistem dengan stok, hapus kolom nilai fisik
- /production/component-reconcile tombol adjustment bukan tidak jelas , ada tapi di klik tidak bisa. tidak berfungsi. kembalikan icon seperti semula tanpa text dan pastikan bisa di klik!
- /production/component-reconcile tombol adj stock dan adjst lot seharusnya keduanya sampai ke child. justru kalau di parent tidak muncul, kecuali data baris yang tidak punya child baru muncul. karena prinsipnya adjstmen itu harus di tingkat data inti bukan parent
- /production/component-reconcile Adj lot juga tidak bisa diklik. berikan icon saja tanpa text dan bedakan iconnya dengan adjs stock
- "operasional live / recipe / formula / POS live lebih mengutamakan lot FIFO aktif terdepan" terdepan ini maksudnya data paling lama atau paling baru? karena namanya FIFO berarti data paling awal masuk.
- /production/component-formulas/detail/77 cost live AYAM DADA FILLET sekarang jadi 48, padahal /inventory/stock/division/lot?scope=DIVISION&status=ALL&division_id=3&destination=REGULER&profile_key=e11d6029e30b57aae25319da38e53e988cfe2894087c0ac94d5d6fd10eb0b3b1 44


cek dan perbaiki dulu baru lanjut nomor 2 dan 3