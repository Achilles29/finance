# Paket Finance untuk Control — kandidat internal

Alur ini menyiapkan bukti paket dan registrasi DRAFT, bukan publish atau
aktivasi lisensi. Executor database belum merupakan deployment web/customer
lengkap. Jalankan sebagai admin rilis Linux; jangan melalui browser.

Update Batch 229–231: kode gate cold-cache kini 360 detik dengan outer 420
detik, cache per checkout; tidak perlu warming manual. Perbaikan ini belum
berada dalam alpha.3. Paket alpha.3 sudah DRAFT di Control, tetapi trial DB
diblokir sebelum DDL: runtime server 10.11.10 berbeda dari kontrak paket 10.6.

Registrasi CLI Control:

```bash
php tools/register_finance_release.php CHECKOUT_FINANCE_ROOT MANIFEST.release.json --actor-id=ID_OPERATOR
```

Path harus absolut dan validator milik root. Actor harus OWNER/RELEASE_MANAGER
aktif. Hasil selalu DRAFT/ALPHA dengan tiga artefak privat. Import identik
UNCHANGED, isi berbeda pada versi sama ditolak. Tidak mengubah limit HTTP
25 MiB. File 0640/root:www, direktori 0750; web hanya membaca file kandidat.
Jika COMMIT kehilangan koneksi, file dipertahankan untuk read-back/recovery;
jangan menghapusnya sebelum hasil commit dipastikan.

Executor database `tools/install/clean_install_database.php apply` menerima
enam parameter file/path: `--release-root`, `--signed-manifest`, `--trust-file`,
`--defaults-extra-file`, `--database-name-file`, `--owner-file`. Source harus
ekstraksi persis paket signed, database harus kosong dan akun DB terbatas
ke database itu. Tidak menerima credential langsung di argv, tidak menghapus
DB agar bisa retry, dan tidak mem-publish/deploy web. Urutan signature,
source exact, empty-state/runtime guard, baseline, migration, bootstrap dan
health bersifat wajib. Kegagalan DDL tidak bisa di-rollback otomatis; simpan
DB dan file untuk inspeksi. **Trial sukses belum dinyatakan** karena runtime.

1. Tetapkan commit lokal source. Buat checkout bersih terpisah dengan
   `git worktree add --detach /var/lib/finance-release/source COMMIT`.
   Catatan lokal, upload, credential dan log pada staging tidak dipindah atau
   dihapus untuk membuat status Git terlihat bersih.
2. Dari checkout tersebut, jalankan builder yang sudah menyediakan gate
   preflight, analisis statis dan advisory dependency:

   ```bash
   php tools/release/build_release_artifact.php --output=/var/lib/finance-release/finance-alpha.tar --source-epoch=COMMIT_UNIX_TIME
   ```

   Catatan Batch 228: checkout baru/cold cache melampaui batas PHPStan gate
   150 detik. Analisis penuh dengan command PHPStan yang sama, scope seluruh
   `application`, satu worker dan memory 2G lulus nol error dalam batas
   diagnostik 300 detik. Builder kemudian dijalankan ulang dan semua gate
   lulus. Jangan menonaktifkan gate atau menyalin hasil PASS dari source lain.
   Budget cold-start dan isolasi cache per checkout masih merupakan tindak
   lanjut tooling; panduan ini belum mengklaim installer clean-machine selesai.

3. Control menyediakan key release khusus produk `NAMUA_FINANCE` melalui
   `tools/provision_release_signing_key.php NAMUA_FINANCE`. Hanya sekali oleh
   root; file private/trust berada di `/var/lib/namua-control/release-signing/`.
   Jangan memakai key Penatausahaan, key lisensi, atau key fixture.
4. Ekspor sidecar dengan format tanda tangan Control:

   ```bash
   php tools/release/control_release.php export /var/lib/finance-release/source /var/lib/finance-release/finance-alpha.tar /var/lib/namua-control/release-signing/private/NAMUA_FINANCE.json
   ```

   Hasil: `.tar`, `.release.json`, `.release.sig.json`. Folder output harus
   milik root dan tidak writable oleh group/others; output tidak boleh sudah
   ada. Kode menolak dirty source atau file arsip berbeda dari source commit.
5. Di checkout Control jalankan verifikasi read-only:

   ```bash
   php tools/verify_finance_release.php /var/lib/finance-release/source /var/lib/finance-release/finance-alpha.release.json
   ```

   Perintah Control menggunakan validator dari checkout Finance milik admin,
   bukan mengeksekusi script yang diambil dari arsip. Trust produk Finance
   berasal dari lokasi tetap di Control. Tidak membuka koneksi database.

Kontrak:

- Tanda tangan Ed25519 atas `NAMUA_RELEASE_MANIFEST_V1`, newline dan SHA256
  byte asli sidecar. Berbeda dari `NAMUA_LICENSE_V1` untuk entitlement.
- Plain TAR regular files, maksimum 1 GiB pada CLI ini. Batas upload HTTP
  Control 25 MiB **tidak diubah**. Upload UI/importer Penatausahaan bukan
  jalur untuk paket Finance ini.
- Seluruh file cocok dengan inner manifest; SQL managed harus sesuai katalog,
  SQL legacy dinyatakan eksplisit dan **tidak otomatis dieksekusi**. Upgrade
  harus menggunakan policy upgrade, bukan clean-install reference seed.
- Isi source/SQL di paket tidak berarti dependency vendor atau runtime
  customer sudah terpasang. Installer tetap harus memakai lock dependency,
  menyiapkan secret/folder, dan menguji install/restore/upgrade/rollback.
- Paket web tidak menyertakan binary/source APK. Komersialisasi APK boleh
  dilanjutkan, bug produksi/build/UAT masih tertunda. Jangan mengiklankan
  APK siap rilis atau menentukan versi minimum APK tanpa build teruji.
- Kandidat `INTERNAL_CANDIDATE`/ALPHA sudah DRAFT di DB Control (Batch 230).
  Belum publish, install-plan, deployment customer atau enforcement.
- Template Menu Book legacy masih disediakan untuk kompatibilitas. Customer
  baru harus memilih template customer di Profil Usaha; audit identitas/aset
  legacy menyeluruh tetap menjadi checklist C2, bukan dinyatakan selesai oleh
  tanda tangan paket.

Kandidat Batch 228: versi `0.1.0-alpha.3`, cutoff Git
`b10fa37a40a06b1867800327812ac1dc1490c176`. Bukti/hash dan lokasi arsip
staging dicatat pada execution log; source/tag terpisah dari commit laporan
setelah build. Tidak perlu menjalankan SQL tambahan untuk batch delivery ini.

Setelah DRAFT: tetapkan runtime kandidat berikutnya, lalu buktikan installer
Linux/upgrade salinan database/rollback disposable.
Server aplikasi lama tidak menjadi target migration.
