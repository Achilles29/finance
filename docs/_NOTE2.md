## INI FILE CATATAN SAYA. ABAIKAN.

Directory finance (C:\xampp\htdocs\finance).
ini adalah pengembangan dan penyempurnaan dari repo core (C:\xampp\htdocs\core). baca README.md dan seluruh dokumen terkait. temukan polanya. dan catat yang perlu dicatat sesuai ketentuan. kita kerjakan secara paralel 
======================
======================

=======================

kita lanjut ke Produksi Base dan Prepare
pertama siapkan database nya. kamu bisa adopsi dengan penyesuaian yang diperlukan dari aplikasi core. database di core yang digunakan adalah tabel prd_component dan semua turunannya.
halaman yang dibutuhkan antara lain:
- halaman kategori base / prepare
- halaman master base / prepare
- halaman resep base / pepare
- halaman stok, keluar masuk, matrix , daily untuk stok base / prepare seperti yang digunakan pada stok material atau bahan baku
- halaman lain yang berkaitan yang ada di core (coba cek) 

setelah kamu siapkan, konsepkan dulu tabel databasenya, dan skema operasional UI nya. setelah saya verifikasi lalu kita bisa lanjut

=================

- semua halaman terkait base prepare urutkan sesuai tipe, divisi lalu kategori
-/production/component-masters seharusnya ada kolom hpp live setelah hpp std
- production/component-cost-variables tambahkan ke sidebar
- /production/component-formulas edit saja atau pakai icon, biar rapi, tidak perlu EDIT TOTAL FORMULA. lalu untuk edit jangan pakai modal. tapi halaman tersendiri.
- filter baris semua kenapa belum muncul?


====

- /production/component-formulas tambahkan button detail formula, lalu di detail formula itu ada button editnya untuk masuk edit juga
- production/component-formulas/edit/ fungsi kolom line itu apa?
- di halaman edit dan detail formula kurang informatif. coba lihat gambar contoh di core

- kenapa masih ada desimal dengan banyak angka???