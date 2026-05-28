## INI FILE CATATAN SAYA. ABAIKAN.

Directory finance (C:\xampp\htdocs\finance).
ini adalah pengembangan dan penyempurnaan dari repo core (C:\xampp\htdocs\core). baca README.md dan seluruh dokumen terkait. temukan polanya. dan catat yang perlu dicatat sesuai ketentuan. kita kerjakan secara paralel 
======================
======================


- memisahkan costumer dan member sepertinya terlalu tidak efisien. bagaimana kalau cukup walkin customer dan member customer. untuk walkin customer tidak perlu dibuatkan database sendiri, cukup database untuk member
- ada pos_void_line_extra lalu perlu pos_refund_line_extra nggak?
- pos_product_availability_cache itu untuk apa? berat nggak jika pos langsung menghitung ketersediaan berdasarkan resep dan bahan. saya takutnya kalau pakai cache jadi nggak realtime

=======================

- terkait member, perlu saya tegaskan lagi. justru yang perlu dihidupkan adalah database member. jadi nanti di POS ketik ketik nama, pertama cari dari database member, kalau ada pilih preview nama dan no hp yang sesuai, lalu masukkan member_id pada transaksi. kalau tidak ada di transaksi berarti null member_id nya. karena nanti jika kita akan buat aplikasi member yang terhubung dengan poin, stamp, voucher dan mungkin promo lain yang lebih kompleks.

- terkait penjelasanmu pos_product_availability_cache lebih masuk akal, dengan catatan cache di update langsung setiap ada transaksi yang mempengaruhi

