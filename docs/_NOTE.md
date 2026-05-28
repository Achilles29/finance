## INI FILE CATATAN SAYA. ABAIKAN.

Directory finance (C:\xampp\htdocs\finance).
ini adalah pengembangan dan penyempurnaan dari repo core (C:\xampp\htdocs\core). baca README.md dan seluruh dokumen terkait. temukan polanya. dan catat yang perlu dicatat sesuai ketentuan. kita kerjakan secara paralel 
======================


======================= 
/production/component-adjustments :

jika component yang dipilih lebih dari 1 lot mestinya memilih lot yang mana. jadi di preview pencarian seharusnya tampilkan identitas lot
post adjusment tidak terjadi apa apa
bukankah adj harusnya ada nilainya berdasarkan nilai stok


/production/component-monthly dan /production/component-stock rapikan kolom ringkasan seperti apda daily matrix


/production/component-daily, /production/component-stock, /production/component-monthly bagaimana tampilan jika ada 1 component lebih dari 1 lot? mungkin buat expand child khusus yang lebih dari 1 lot. jadi yang lebih dari 1 lot parent tampilkan data total dan rata rata, child tampilkan data seusai lot. sedangkan yang hanya 1 lot tampilkan data langsung tidak usah parent - child, dan buat yang rapi tampilan childnya, sesuai dengan tampilan parentnya

==============================
masih di /production/component-daily bukan seperti ini yang saya maksud. tapi buat tampilannya per tanggal persis seperti parent nya. dengan tanggal masuk, kuantitas, cost masing masing.



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





