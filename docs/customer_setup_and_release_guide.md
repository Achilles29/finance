# Panduan setup customer dan pemeriksaan sebelum rilis

Status 2026-09-08: panduan setup web yang sudah tersedia, **bukan pernyataan
seluruh aplikasi/installer/APK siap jual**. Status utama tetap pada roadmap
audit `_30` dan komersialisasi `_28`.

## 1. Pengaturan lewat aplikasi — admin usaha

1. Masuk menggunakan akun yang memiliki akses **System → Profil Usaha & Tampilan**.
2. Isi nama dagang, nama legal bila ada, alamat, dan kontak.
3. Pilih logo PNG/JPG, lihat preview, lalu isi footer dokumen bila diperlukan.
4. Pada **Menu Book publik**, pilih:
   - **Katalog usaha** untuk customer baru;
   - **Tidak dipublikasikan** bila katalog belum boleh dibuka;
   - **Desain Namua lama** hanya untuk instalasi lama yang memang memakai desain itu.
5. Klik **Simpan Pengaturan Usaha**. Pemilihan template dan identitas disimpan
   bersama serta dicatat dalam audit. File logo/desain lama tidak dihapus.
6. Klik **Pilih produk publik**. Pada Landing Page → Menu, pilih produk yang
   boleh terlihat publik, kemudian buka **Lihat Menu Book tersimpan**.
7. Katalog menampilkan nama, kategori, deskripsi, dan harga jual produk aktif
   yang dipublikasikan. Data order, stok, HPP, serta identitas pembeli tidak
   ditampilkan. Promo/pajak/service bukan bagian perhitungan harga katalog.

Nama/alamat/logo outlet atau pengaturan cetak yang telah diisi tetap didahulukan.
Jika struk belum memakai logo utama, periksa **POS → Pengaturan Cetak**
(`/pos/printers/general`), jangan mengubah database secara manual.
Pengisian zona waktu/locale/mata uang pada profil belum merupakan konversi
transaksi historis atau dukungan multi-currency seluruh aplikasi.

Pengaturan lain tidak diubah massal:

| Keperluan | Halaman | Yang harus diperiksa admin |
| --- | --- | --- |
| Outlet dan terminal | `/pos/outlets-terminals` | Nama, alamat, terminal aktif dan pemilik perangkat |
| Metode pembayaran | `/pos/payment-methods` | Metode aktif dan rekening yang benar |
| Konten publik dan URL | `/landing-page?tab=config` | Kontak, tautan order/member, SEO, dan domain customer |
| Self Order | `/pos/self-order/settings` | Aktivasi, URL, dan alur penerimaan pesanan |
| Online Food | `/pos/online-food/settings` | Pengaturan layanan yang memang dipakai |
| Telegram | `/telegram/guide` lalu `/telegram/settings` | Ikuti panduan; jangan memakai token customer lain |
| Lisensi | `/system/license` | Mode pemantauan dan status verifikasi, bukan tombol naik paket |

Tautan tidak memberikan hak akses baru; RBAC halaman tujuan tetap berlaku.

## 2. Folder upload — admin server

Buka bagian **Pemeriksaan folder upload oleh server web** di Profil Usaha.
Hasilnya memakai akun PHP-FPM yang melayani aplikasi. Status:

- **Siap:** folder ada dan dapat ditulis akun PHP tersebut.
- **Belum dibuat:** folder belum tersedia.
- **Tidak bisa ditulis:** admin server harus meninjau owner/ACL.
- **Lokasi perlu diperiksa:** terdapat file/symlink yang tidak sesuai kontrak.

Contoh aaPanel staging ini (akun pool `www`, PHP 8.1):

```bash
cd /www/wwwroot/finance
runuser -u www -- /www/server/php/81/bin/php tools/install/upload_storage.php check
```

Bila folder belum ada dan parent memang telah diberikan hak tulis yang sesuai:

```bash
runuser -u www -- /www/server/php/81/bin/php tools/install/upload_storage.php prepare
```

Sesuaikan lokasi aplikasi, binary PHP, dan akun pool di server customer.
`prepare` hanya membuat folder upload yang tercantum pada policy; tidak
menghapus file, tidak mengubah owner/izin folder lama, dan menolak berjalan
sebagai root. Jangan memperbaiki dengan `chmod 777` seluruh project.
Jika parent tidak bisa ditulis, admin harus menentukan owner/ACL deployment
terlebih dahulu. Symlink upload ditolak oleh checker ini dan perlu desain
shared-storage yang disetujui tersendiri. Pemeriksaan ini tidak menggantikan
aturan webserver yang melarang eksekusi script pada folder upload.

## 3. Instalasi baru berbeda dengan pemindahan database lama

| Skenario | Kebijakan |
| --- | --- |
| Customer baru tanpa data | Database kosong, baseline + migration clean-install, reference seed, owner pertama, konfigurasi customer |
| Trial dari database aplikasi berjalan | Salin database ke **database baru milik instance trial**; lakukan upgrade hanya pada salinan; jangan jalankan clean seed/owner bootstrap |
| Update customer yang sudah berjalan | Backup + bukti restore, migration upgrade, health check, uji penerimaan, baru pindah layanan |

Sesuai rencana owner, aplikasi utama lama tetap berjalan tanpa menjalankan SQL
baru di sana. Saat trial, sumber data lama hanya disalin; migration ditujukan
ke salinan database pada aplikasi hasil deploy.

Periksa rencana urutan tanpa mengubah database:

```bash
php tools/install/finance_install_plan.php plan --mode=clean_install
php tools/install/finance_install_plan.php plan --mode=upgrade
```

Kedua command masih **plan-only**, bukan installer satu klik. Jangan
mengartikan output `status=ok` sebagai database sudah diinstal. Mekanisme
credential/migration/owner berada pada `deployment_secret_contract.md`.
Jangan menjalankan semua file folder `sql` dengan wildcard.

Hook Composer sekarang memakai PHP, bukan `sed`, dan melewati compatibility
patch bila dependency development tidak terpasang. Ini memperbaiki hook
instalasi; bukan sertifikasi seluruh installer Windows.

## 4. Verifikasi lisensi — admin server dan pengelola Control

Verifikasi mengikuti envelope Control `NAMUA_LICENSE_V1`, bukan format tanda
tangan artefak. Kunci penandatangan **lisensi** dan **paket rilis** berbeda.
Private key penerbit tidak boleh disalin ke Finance.

Finance dapat memeriksa dokumen cache yang telah diterbitkan Control bila
deployment menyediakan dua file JSON di luar webroot:

- `FINANCE_LICENSE_TRUST_FILE`: lokasi salinan **public trust document**
  produk `NAMUA_FINANCE` dari Control, dengan schema/purpose/key-id/fingerprint.
- `FINANCE_LICENSE_IDENTITY_FILE`: lokasi identitas instalasi dengan field
  `instance_id`, `installation_id`, dan `instance_public_key_sha256` yang
  cocok dengan registrasi aktivasi Control.

Pada Linux, file harus dimiliki root, parent directory juga dimiliki root,
dan tidak boleh group/world-writable. Contoh lokasi yang dapat digunakan
admin adalah `/etc/finance/license-trust.json` dan
`/etc/finance/license-identity.json`; bukan folder upload atau `/tmp`.
Whitelist **path file**, bukan private key, pada pool PHP-FPM:

```ini
env[FINANCE_LICENSE_TRUST_FILE] = /etc/finance/license-trust.json
env[FINANCE_LICENSE_IDENTITY_FILE] = /etc/finance/license-identity.json
```

Nilai identitas harus berasal dari provisioning/aktivasi resmi; jangan
mengarang ID dan jangan mengubah tabel menjadi VERIFIED untuk mencoba
membuka lisensi. Aktivasi/polling/cache writer belum diimplementasikan pada
batch ini, sehingga langkah provisioning end-to-end masih menunggu C4.
Windows trust-file ACL belum disertifikasi dan reader menolak konfigurasi
tersebut, bukan diam-diam menganggapnya tepercaya.

**Jangan aktifkan enforcement sekarang.** Finance default tetap AUDIT_ONLY.
`FINANCE_LICENSE_ENFORCEMENT_APPROVED` tidak disetel pada staging; perubahan
flag mode di database saja tidak cukup untuk mengunci kasir. Pemisahan ini
bukan klaim bahwa seluruh enforcement endpoint/worker selesai.

## 5. Checklist sebelum pelanggan benar-benar memakai aplikasi

- [ ] Source terpilih dan commit cutoff disetujui; tidak mengikutsertakan backup/upload/secret.
- [ ] Paket bersih, signature artefak, serta proses delivery Control untuk Finance lulus.
- [ ] Clean-install atau upgrade pada **database salinan** lulus; restore dan rollback terbukti.
- [ ] Nama, logo, domain, katalog, outlet, dan dokumen benar untuk customer.
- [ ] Pajak/service, rekening, integrasi, scheduler dan privacy diperiksa sesuai kebutuhan customer.
- [ ] Uji alur web per peran pengguna dan printer fisik selesai.
- [ ] Kontrak paket/harga/support dan persetujuan go-live disepakati owner.
- [ ] APK diuji terpisah jika akan ikut dijual; saat ini ditunda dan belum lulus.

Panduan operasional seluruh modul, latihan pengguna, dan pilot non-Namua
tetap pekerjaan C5. File ini tidak menggantikan pekerjaan tersebut.
