# Kontrak paket customer bersih — Finance ↔ Control

Status: implementasi sisi Finance untuk source **0.1.0-alpha.11**, 10 September 2026. Control dikerjakan thread lain; dokumen ini menjadi kontrak bersama. Jangan mengosongkan sumber Finance atau database development.

Handoff source lokal: tag `finance-web-alpha.11-clean-profile-source-20260910` (commit Finance khusus batch ini; belum push). Gunakan cutoff yang sama untuk builder dan validator. Validasi kode: **114 entry release gate PASS**, termasuk **56 pemeriksaan paket bersih**. Ini belum merupakan artifact alpha.11 yang signed/published atau bukti install customer end-to-end.

## Pilihan dan batas tanggung jawab

- Finance menyediakan `CUSTOMER_CLEAN`, versi profil `1`, seed `REFERENCE_ONLY`. Tidak menyediakan demo produk/transaksi pada versi ini.
- Builder: `php tools/release/build_release_artifact.php --profile=CUSTOMER_CLEAN --root=/checkout/committed --output=/private/finance-VERSION.tar --source-epoch=EPOCH`.
- Default builder baru adalah `CUSTOMER_CLEAN`. `LEGACY_INTERNAL` hanya untuk reproduksi/test internal, bukan penjualan. Tidak ada opsi pembersihan database sumber.
- Sumber harus checkout bersih pada commit tertentu. Output di direktori terpisah; file sumber tidak ditulis ulang. Profil memilih file dan checksum konten statis/SQL yang disetujui, bukan menyalin seluruh folder assets.
- Control memiliki UI pemilihan profil, job build, penyimpanan metadata/evidence, approval/publish, dan deployment plan. Finance tidak mengubah kode/database Control.

## Kontrak artifact dan manifest

Tetap tiga artifact: `.tar`, `.release.json`, `.release.sig.json`; mekanisme signature Ed25519 dan `manifest_version: 2` tetap. Inner `RELEASE-MANIFEST.json` tetap schema 1. Profil `tools/release/customer_clean_profile.json` termasuk file yang checksum-nya dilindungi inner manifest.

Sidecar hasil export paket baru menambahkan:

```json
{
  "distribution_profile": "CUSTOMER_CLEAN",
  "distribution_profile_version": 1,
  "seed_profile": "REFERENCE_ONLY",
  "customer_content_audit": {
    "schema": "finance.customer-content-audit",
    "schema_version": 1,
    "status": "PASS",
    "profile_sha256": "<64 hex>",
    "artifact_sha256": "<64 hex>",
    "source_manifest_sha256": "<64 hex>",
    "files_checked": 0,
    "static_files_checked": 0,
    "sql_files_checked": 0,
    "demo_data": false,
    "source_database_accessed": false
  },
  "contains_customer_data": false
}
```

Angka jumlah file di atas hanya contoh; validator menghitung isi TAR sebenarnya. `PASS` berarti sesuai allowlist Finance dan checksum SQL/static yang dikurasi, bukan hasil memeriksa database customer. Secret scan dan install-test tetap gate terpisah.

- Control harus memanggil validator Finance yang dipercaya, bukan menerima `contains_customer_data=false` atau report upload tanpa verifikasi.
- Gate baru yang disarankan: `CUSTOMER_CONTENT_AUDIT`. Ikat profile/version/seed, artifact SHA, inner manifest SHA, source commit, dan policy SHA ke release; jangan mengandalkan versi/nama file saja.
- Paket lama tanpa profil tidak memenuhi syarat clean-customer. Signature lama tetap dapat diperiksa untuk provenance/rollback, tetapi bukan bukti bersih. Jangan mengubah artifact/tag alpha.10 yang sudah immutable; buat versi release baru.
- Semua checksum/report harus dihitung ulang dari artifact yang akan dipublish, bukan dari worktree lain. Update validator tepercaya di Control beserta dependensinya dari cutoff yang sama.

## Install mode bukan distribution profile

- `clean_install`: hanya database tujuan baru/kosong; schema + migration terkelola + reference seed + bootstrap owner. Tidak mengimpor backup Namua.
- `upgrade`: mempertahankan database, uploads, identitas, dan transaksi customer; reference seed clean-install tidak dijalankan. Bukan perintah reset/pembersihan.
- Control perlu membawa pilihan profil/seed ke metadata release dan deployment plan. Install mode tetap keputusan deployment instance, bukan toggle yang menghapus data pada saat build.
- Response claim `/api/v1/install-plans/claim` wajib menambahkan pada object `release`: `distribution_profile: "CUSTOMER_CLEAN"`, `distribution_profile_version: 1` (integer), `seed_profile: "REFERENCE_ONLY"`, dan `customer_content_profile_sha256` (hash profil dari audit signed). Keempat nilai juga harus masuk material pembentuk `plan_sha256`. Finance membandingkan semuanya setelah signature artifact diperiksa; field hilang/salah memblokir delivery paket baru, tanpa menurunkan ke mode legacy.
- Verifier CLI mengembalikan `customer_clean_eligible: true`, profile/version/seed dan `customer_content_audit`. Legacy mengembalikan `customer_clean_eligible: false` serta audit `NOT_AUDITED`, sekalipun signature valid. Installer clean-install Finance menolak paket legacy sebelum koneksi DB.
- Migrasi database aplikasi Namua yang berjalan merupakan workflow import/upgrade terpisah, bukan clean-install customer.

## Konten

- Masuk: program, dependency contract, tema/icon umum, placeholder netral, schema tanpa data, sidebar/RBAC sistem dan template generik yang disetujui.
- Tidak masuk: foto/menu statis Namua, materi roastery/promosi, logo usaha lama, uploads, backup/dump, log/cache, credential lokal, probe/repair/truncate development, SQL legacy yang tidak dikelola.
- Data staging tetap utuh. Legacy Menu Book tetap bisa dipakai di development; paket customer beralih ke katalog usaha jika desain legacy tidak disertakan.

## Checklist lintas thread

- [x] Finance: filter profil, audit TAR, runtime fallback netral, test regresi (56 pemeriksaan khusus dengan fixture; bukan install-test operasional).
- [ ] Control: UI/build memakai profil eksplisit dan validator Finance terbaru.
- [ ] Control: gate evidence dan deployment plan terikat ke artifact/profile yang sama.
- [ ] Bersama: build release versi baru dari cutoff bersih, sign, register, clean-install terisolasi, cek layar kosong/owner dan upgrade preservasi.
- [ ] User: latihan penjualan melalui UI Control setelah semua gate di atas lulus.

Jangan tandai integrasi Control/latihan penjualan selesai hanya karena unit test Finance lulus.

## Perintah validator untuk thread Control

```sh
# Read-only terhadap artifact; bukan import/SQL/database cleanup.
php tools/release/customer_content_audit.php /private/finance-VERSION.tar
php tools/release/control_release.php verify /private/finance-VERSION.release.json /private/trust/NAMUA_FINANCE.json
```

Dependensi validator tepercaya kini juga mencakup `tools/release/CustomerReleaseProfile.php` dan `tools/release/customer_clean_profile.json`, selain bridge, artifact_signature dan package_policy yang sudah dipakai. Allowlist ini tidak boleh diganti memakai file dari upload sebelum diverifikasi. Pertahankan validator/cutoff lama untuk rollback kandidat historis; perubahan profil berikutnya membutuhkan review dan versi profil baru, bukan mengubah allowlist v1 diam-diam.

Data demo tidak dibawa, sehingga tidak ada `CUSTOMER_DEMO` atau `CLONE_NAMUA` pada kontrak ini. Jika nanti diperlukan, buat profil/seed terpisah dengan fixture sintetis dan gate sendiri.
