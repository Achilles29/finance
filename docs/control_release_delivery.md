# Paket Finance untuk Control — kandidat internal

Alur ini menyiapkan bukti paket, bukan installer, bukan publish, dan bukan
aktivasi lisensi. Jalankan sebagai admin rilis Linux; jangan melalui browser.

1. Tetapkan commit lokal source. Buat checkout bersih terpisah dengan
   `git worktree add --detach /var/lib/finance-release/source COMMIT`.
   Catatan lokal, upload, credential dan log pada staging tidak dipindah atau
   dihapus untuk membuat status Git terlihat bersih.
2. Dari checkout tersebut, jalankan builder yang sudah menyediakan gate
   preflight, analisis statis dan advisory dependency:

   ```bash
   php tools/release/build_release_artifact.php --output=/var/lib/finance-release/finance-alpha.tar --source-epoch=COMMIT_UNIX_TIME
   ```

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
- Kandidat `INTERNAL_CANDIDATE`/ALPHA. Belum ada registrasi DRAFT ke DB
  Control, publish, install-plan, deployment customer atau enforcement.
- Template Menu Book legacy masih disediakan untuk kompatibilitas. Customer
  baru harus memilih template customer di Profil Usaha; audit identitas/aset
  legacy menyeluruh tetap menjadi checklist C2, bukan dinyatakan selesai oleh
  tanda tangan paket.

Setelah verifikasi: adapter registrasi Finance di private artifact storage
Control, lalu installer Linux/upgrade salinan database/rollback disposable.
Server aplikasi lama tidak menjadi target migration.
