# Alur Bisnis — Penjelasan dalam Bahasa User
**Tanggal:** 2026-05-01  
**Tujuan:** Mendokumentasikan alur tiap modul dalam bahasa sehari-hari sebelum masuk ke pengembangan kode.  
**Acuan:** `core` sebagai referensi, dengan penyempurnaan.

---

## Gambaran Besar Sistem

Aplikasi ini adalah sistem manajemen operasional kafe yang mencakup:
- **Orang**: karyawan, absensi, gaji
- **Barang**: bahan baku, produk, stok gudang, stok dapur
- **Transaksi**: pembelian, penjualan, keuangan
- **Laporan**: semua data tersebut bisa dilihat dalam bentuk laporan

---

## 1. Login & Hak Akses

### Alur Login
1. Pengguna membuka aplikasi → tampil halaman login
2. Masukkan email/username dan password
3. Sistem memeriksa: apakah akun aktif? apakah role-nya punya akses?
4. Jika berhasil → diarahkan ke dashboard sesuai role-nya

### Sidebar Utama (Operasional)
Hanya tampil menu yang punya izin akses sesuai role karyawan tersebut. Contoh:
- **Kasir** hanya lihat menu POS (kasir)
- **Manajer Gudang** hanya lihat menu Gudang & Pembelian
- **Admin/CEO** bisa akses semua

### Sidebar Pribadi (My)
Semua karyawan yang login bisa akses, tanpa perlu izin khusus:
- Slip gaji saya
- Rekap absensi saya
- Pengajuan izin saya
- Saldo kasbon saya
- Profil & data diri saya

### Manajemen Role & Akses (Admin)
1. Admin membuat role: "Kasir", "Manajer", "Admin", "CEO", dll.
2. Admin centang **halaman + izin aksi** yang boleh dilakukan role tersebut:
   - **View** — boleh buka/lihat halaman
   - **Create** — boleh tambah data baru
   - **Edit** — boleh ubah data yang sudah ada
   - **Delete** — boleh hapus data
   - **Export** — boleh export ke PDF/Excel (opsional, untuk halaman laporan)
3. Admin assign role ke karyawan
4. Jika ada kebutuhan khusus, bisa override per karyawan:
   - Tambah akses yang tidak ada di role-nya
   - Kurangi/cabut akses yang ada di role-nya

**Contoh penerapan:**
- Role "Kasir": View POS ✅, Create Order ✅, Edit Order ✅, Delete Order ❌
- Role "Manajer": semua POS ✅, plus View Laporan ✅, Export Laporan ✅
- Karyawan A (role Kasir) punya override: Delete Order ✅ (khusus dia saja)

---

## 2. Manajemen Karyawan (HR)

### Tambah Karyawan Baru
1. Admin membuka menu Karyawan → Tambah Baru
2. Isi data: nama, jenis kelamin, tanggal lahir, tanggal bergabung, jabatan, divisi
3. NIP akan otomatis ter-generate berdasarkan format yang disepakati
4. Isi data rekening bank (untuk transfer gaji/uang makan)
5. Simpan → karyawan aktif

### Kontrak Karyawan
1. Admin membuka profil karyawan → tab Kontrak
2. Buat kontrak: tanggal mulai, tanggal selesai, jenis kontrak (PKWT/PKWTT/magang)
3. Kontrak bisa dicetak dan punya kode QR untuk verifikasi keaslian
4. Admin HR bisa scan QR untuk konfirmasi kontrak valid

### Data yang Dikelola per Karyawan
- Biodata lengkap (nama, lahir, alamat, gender, kontak)
- NIP & jabatan
- Rekening bank
- Status: aktif / nonaktif
- Riwayat kontrak

---

## 3. Absensi

### Absensi Karyawan (Harian)
**Via GPS/Faceprint (device):**
1. Karyawan buka aplikasi absensi di HP
2. Tap "Masuk" → sistem catat lokasi GPS atau foto wajah + waktu
3. Akhir kerja: tap "Keluar"
4. Sistem simpan ke raw log

**Via Manual (Admin):**
1. Admin buka halaman Absensi → Input Manual
2. Pilih karyawan, tanggal, status (Hadir/Sakit/Izin/Cuti/Alpha/Libur)
3. Bisa sekaligus input untuk satu divisi

**Rekap Harian (Otomatis):**
- Setiap malam sistem merekap raw log → satu record per karyawan per hari
- Status: HADIR, TERLAMBAT, SAKIT, IZIN, ALPHA, LIBUR
- Menit terlambat dihitung otomatis dari jam kerja yang diset

### Pengajuan Izin/Sakit/Cuti (Workflow)
1. Karyawan ajukan lewat Sidebar Pribadi (My) → Pengajuan
2. Pilih tanggal, jenis pengajuan, upload bukti jika perlu
3. Atasan mendapat notifikasi → buka halaman Persetujuan
4. Atasan Approve atau Reject
5. Jika approve → rekap absensi hari tersebut ter-update otomatis

### Laporan Absensi
- **Harian**: lihat siapa yang hadir, terlambat, alpha hari ini
- **Bulanan per karyawan**: rekap lengkap satu orang selama satu bulan
- **Bulanan semua karyawan**: tabel ringkasan semua karyawan

---

## 4. Payroll & Penggajian

### Komponen Gaji
Setiap karyawan punya profil gaji yang terdiri dari:
- **Gaji Pokok** — tetap setiap bulan
- **Tunjangan Jabatan** — sesuai posisi/jabatan
- **Tunjangan Objektif** — berdasarkan penilaian/KPI (bisa 0 jika tidak ada)
- **Komponen tambahan lain** (opsional): transport, makan, dll.
- **Potongan**: terlambat, alpha, cicilan kasbon
- **Tambahan**: lembur, bonus omzet

### Proses Penggajian Bulanan
1. Admin buka menu Penggajian → Proses Bulan [Bulan/Tahun]
2. Sistem menarik data absensi bulan tersebut per karyawan
3. Sistem kalkulasi otomatis:
   - Hari kerja efektif
   - Potongan terlambat (Rp X × menit terlambat)
   - Potongan alpha (Rp Y × hari alpha)
   - Cicilan kasbon yang jatuh tempo bulan ini
   - Lembur (jika ada)
4. Admin review hasil kalkulasi
5. Admin bisa edit manual jika ada koreksi
6. Admin "Finalisasi" → slip gaji terkunci
7. Admin buat "Pencairan Gaji" → pilih rekening bank, tanggal transfer
8. Status berubah PAID

### Slip Gaji
- Bisa dicetak per karyawan
- Karyawan bisa lihat di Sidebar Pribadi (My) → Slip Gaji

### Uang Makan Mingguan
1. Setiap Senin/awal minggu, admin buka menu Uang Makan
2. Sistem otomatis hitung: karyawan yang hadir valid (bukan hari libur) × rate uang makan masing-masing
3. Admin review → finalisasi → cetak daftar transfer
4. Status berubah PAID setelah transfer dilakukan

### Kasbon Karyawan
1. Karyawan ajukan kasbon (lewat admin atau self-portal)
2. Admin atau atasan approve/reject
3. Jika approve → dicatat sebagai kasbon aktif
4. Admin buat pencairan kasbon (transfer ke rekening karyawan)
5. Saat proses gaji bulanan, cicilan kasbon otomatis dipotong dari gaji
6. Status kasbon: DRAFT → APPROVED → DISBURSED → PARTIAL_SETTLED → SETTLED

### THR
1. Admin buka menu THR → Generate untuk bulan puasa
2. Sistem hitung otomatis berdasarkan masa kerja:
   - < 12 bulan: proporsional
   - ≥ 12 bulan: 1 bulan gaji pokok
3. Admin review, finalisasi, cetak, transfer

---

## 5. Pembelian (Purchase)

### Permintaan Barang dari Divisi (Store Request)
1. Kepala divisi buka menu Permintaan Barang → Buat Baru
2. Pilih barang-barang yang dibutuhkan + jumlah
3. Submit → masuk ke antrian permintaan
4. Admin gudang/purchase melihat permintaan → setujui, tolak, atau buat PO

### Purchase Order (PO)
1. Admin purchase buka menu PO → Buat Baru
2. Pilih vendor, tambahkan item-item yang dibeli + jumlah + harga
3. PO bisa atas dasar store request, atau langsung tanpa request
4. Status PO: DRAFT → APPROVED → ORDERED → RECEIVED → CLOSED
5. Saat barang datang: admin klik "Terima Barang" → masukkan jumlah aktual yang diterima
6. Otomatis menambah stok di gudang (`inv_warehouse`)

### Aturan UOM di Purchase (Penegasan)
1. Input belanja wajib memisahkan:
   - UOM BELI (kemasan transaksi): misal BOTOL, DUS, PACK
   - UOM ISI (satuan dasar): misal ML, GR, PCS
2. Sistem menghitung total isi dasar dari Qty Beli x Isi per Unit.
3. Jika vendor ganti kemasan (misal BOTOL menjadi DUS), master item tidak diganti.
4. Sistem menyimpan profil transaksi tersebut di katalog purchase sebagai riwayat referensi pembelian.
5. Resep dan konsumsi stok tetap memakai UOM ISI agar konsisten dan tidak terpengaruh perubahan kemasan vendor.

### Katalog Harga Supplier
- Admin bisa input harga per item per vendor
- Saat buat PO, harga bisa di-suggest dari katalog
- History harga tersimpan → bisa lihat tren harga ("price pulse")

---

## 6. Gudang (Inventory)

### Konsep
- **Gudang** = tempat penyimpanan semua barang yang sudah diterima dari PO
- **Stok Bahan Baku (Material)** = bahan yang sudah didistribusikan ke dapur/bar untuk produksi

### Terima Barang dari PO
- Otomatis saat admin konfirmasi terima PO
- Stok gudang item bertambah

### Distribusi ke Divisi/Dapur
1. Admin gudang buka menu Distribusi / Mutasi Keluar
2. Pilih item yang akan didistribusikan, jumlah, tujuan divisi
3. Konfirmasi → stok gudang berkurang, stok material divisi bertambah
4. Sistem otomatis mapping: item "ITIK" → material "BEBEK" (via konfigurasi)

### Catatan Keputusan (Update 2026-05-03)

- Pada Recipe Product, field source division ditetapkan sebagai **Divisi Operasional** (bukan Divisi Produk)
   agar pengurangan stok bahan baku selalu konsisten dengan jalur operasional.
- Fondasi transaksi inventori item -> material sudah dimulai lewat mapping per divisi operasional,
   dengan pencatatan transaksi manual sebagai tahap awal kontrol dan audit.

### Penyesuaian Stok (Adjustment)
- Jika ada selisih saat opname → buat adjustment
- Wajib isi alasan (susut, rusak, hilang, koreksi hitung)

### Opname Bulanan
- Admin buka menu Opname → pilih bulan
- Input stok fisik aktual
- Sistem tampilkan selisih dengan stok sistem
- Admin konfirmasi → selisih otomatis jadi adjustment

---

## 7. Produksi

### Buat Komponen (Base/Prepare)
1. Chef/admin produksi buka menu Produksi → Buat Komponen
2. Pilih komponen yang akan dibuat (contoh: "Espresso Shot 30ml")
3. Sistem tampilkan formula bahan (misalnya: 8g biji kopi + 30ml air)
4. Input jumlah batch yang dibuat (misal: 50 batch)
5. Konfirmasi → stok bahan baku berkurang sesuai formula × batch, stok komponen bertambah
6. Jika ada sisa atau selisih → input sebagai adjustment

### Kalkulasi HPP (Harga Pokok Produksi)
- Otomatis dihitung berdasarkan harga bahan baku terbaru
- HPP per komponen = jumlah material × harga satuan material
- HPP per produk = sum(HPP komponen × qty) + sum(material langsung × harga)
- Tersedia laporan HPP harian: total biaya produksi per hari

---

## 8. POS / Kasir

### Buka Shift
1. Kasir login → buka halaman POS
2. Pilih outlet dan terminal
3. Input uang kas awal (opening cash)
4. Shift terbuka → siap terima order

### Terima Order
1. Kasir pilih produk dari daftar menu (tampil foto, nama, harga)
2. Pilih extra/add-on jika ada (contoh: +extra shot, tanpa gula, dll.)

### Update Alur Extra (2026-05-03)

- Pengelolaan keterhubungan produk dengan group extra dipusatkan di halaman Group Extra (model checklist produk).
- Dari master produk, akses ke halaman relasi tetap tersedia untuk melihat keterhubungan per produk.
3. Atur jumlah
4. Pilih tipe layanan: Dine In, Take Away, Delivery
5. Bisa input nama tamu atau cari member

### Pembayaran
1. Kasir klik "Bayar"
2. Pilih metode bayar: Cash, QRIS, Transfer, Debit, dll.
3. Bisa split payment (bayar sebagian cash, sebagian QRIS)
4. Input nominal yang diterima → sistem hitung kembalian
5. Konfirmasi → struk otomatis tercetak (jika printer terhubung)
6. Stok bahan baku dan komponen otomatis berkurang sesuai resep

### Void / Batal Order
1. Supervisor/manajer bisa void order yang sudah dibuat
2. Wajib isi alasan void
3. Stok otomatis dikembalikan ke stok sebelumnya
4. Dicatat di laporan void

### Refund
1. Customer minta refund → kasir buka menu Refund
2. Pilih order yang di-refund, item mana yang di-refund
3. Input alasan, pilih metode pengembalian
4. Stok dikembalikan atau masuk ke waste sesuai kondisi produk

### Loyalty — Poin
- Setiap transaksi member otomatis dapat poin
- Poin bisa di-redeem untuk diskon/produk gratis
- Admin atur aturan poin: Rp X → 1 poin, 100 poin = diskon Rp Y

### Loyalty — Stamp
- Setiap pembelian 1 cup = 1 stamp
- Setelah X stamp → gratis 1 produk tertentu
- Admin buat campaign stamp

### Voucher
- Admin buat campaign voucher (diskon %, nominal, atau produk gratis)
- Bisa dibagikan dengan kode unik per pelanggan
- Kasir scan/input kode voucher saat transaksi

### Monitor Order (KDS)
- Halaman monitor tampil di dapur/bar
- Setiap order baru masuk → tampil real-time
- Staff kitchen tandai order: Proses → Siap
- Kasir bisa lihat status order mana yang sudah siap disajikan

### Tutup Shift
1. Kasir klik "Tutup Shift"
2. Input uang kas akhir (actual cash)
3. Sistem hitung: kas awal + total cash in - total cash out = expected cash
4. Tampilkan selisih kas
5. Kasir konfirmasi → shift tertutup
6. Laporan shift otomatis tersimpan

---

## 9. Keuangan

### Transaksi Bank (Masuk/Keluar)
- Admin catat setiap mutasi rekening bank
- Kategori: penjualan, pembelian, gaji, kasbon, dll.
- Bisa import dari mutasi rekening bank

### Rekap Rekening Harian
- Tampilan ringkasan saldo semua rekening per hari
- Bisa diakses oleh manajemen tanpa login khusus (halaman public dengan PIN)

### Hutang (AP — Account Payable)
1. Admin buat dokumen AP ketika ada tagihan dari vendor yang belum dibayar
2. Dokumen AP punya status: DRAFT → APPROVED → PARTIALLY_PAID → PAID
3. Saat bayar: catat pembayaran → otomatis catat transaksi bank keluar

### Piutang (AR — Account Receivable)
1. Admin buat dokumen AR untuk tagihan yang belum diterima
2. Saat terima pembayaran: catat → otomatis catat transaksi bank masuk

### Cash Advance Operasional
- Admin bisa keluarkan uang untuk keperluan operasional (bukan kasbon karyawan)
- Harus ada pertanggungjawaban (settlement)

### Laporan Keuangan
- Laporan arus kas harian
- Rekap pendapatan vs pengeluaran
- Monthly management summary (ringkasan bulanan untuk manajemen)
- Estimasi keuangan bulanan

---

## 10. Laporan & Dashboard

### Dashboard Utama
- KPI real-time: omzet hari ini, HPP, jumlah transaksi, produk terlaris
- Grafik tren penjualan mingguan/bulanan

### Reports Hub
- Semua laporan terpusat di satu halaman
- Filter: produk, divisi, periode
- Bisa export ke PDF/Excel

---

## Catatan Mobile-Friendly

Semua halaman harus bisa digunakan dengan nyaman di HP, khususnya:
- **Kasir**: layar kasir yang touch-friendly
- **Absensi**: input absensi via HP
- **My (Pribadi)**: karyawan lihat slip gaji, absensi dari HP
- **Monitor Order**: tampil di tablet/layar dapur
- **Approval izin**: atasan approve dari HP

Halaman laporan kompleks (banyak kolom) tetap responsif, tapi boleh lebih optimal di desktop.
