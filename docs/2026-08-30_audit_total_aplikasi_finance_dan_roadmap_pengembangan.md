# Audit Total Aplikasi Finance dan Roadmap Pengembangan

**Tanggal audit awal:** 2026-08-30

**Pembaruan menyeluruh:** 2026-09-01

**Pembaruan status eksekusi:** 2026-09-05, setelah Batch 147 menetapkan cutoff
Git lokal bertag, memisahkan credential staging dan 1.367 file runtime dari
source/index Git tanpa menghapus file fisik, serta membakukan matriks SQL
server existing dan customer baru.

**Sifat audit:** Pemeriksaan baca-saja terhadap source code, konfigurasi, route,
sidebar, RBAC, struktur database, kesehatan data aktif, writer transaksi,
artefak operasional, dan konsistensi antarmuka.

**Status dokumen:** Sumber utama audit bug, risiko, integritas transaksi, dan
kesiapan teknis aplikasi. Dokumen ini bukan roadmap paket, harga, lisensi, atau
penjualan.

**Dokumen terkait:** docs/2026-08-28_roadmap_komersialisasi_finance_dan_lisensi.md
tetap menjadi pegangan konsep produk dan lisensi. Dokumen ini menjadi daftar
utang teknis dan urutan implementasi yang harus diselesaikan.

Dokumen ini menggantikan status temuan pada versi 30 Agustus yang sudah tidak
sesuai dengan kondisi sekarang. Angka snapshot audit tetap berasal dari
1 September 2026 dan dapat berubah setelah transaksi atau sinkronisasi database
server berikutnya. Status pekerjaan setelah snapshot dicatat pada checklist
di bawah dan pada `docs/2026-09-02_codex_execution_log.md`.

**Batas dengan dokumen komersialisasi:**

- Dokumen ini memiliki pekerjaan perbaikan aplikasi: security, RBAC, data,
  transaksi, HPP, navigasi, UI, test, dependency, schema, backup, dan release
  foundation.
- `docs/2026-08-28_roadmap_komersialisasi_finance_dan_lisensi.md` memiliki
  keputusan produk: paket, harga, EULA, entitlement, License Hub, Product
  Control Center, aktivasi device, pilot, support, dan penjualan.
- Laporan tanggal lain, termasuk progress per modul dan execution log, hanya
  mencatat pekerjaan yang benar-benar dilakukan. Laporan tersebut tidak
  membuat roadmap ketiga dan tidak menggantikan dua dokumen induk.

## 0. Audit Control Board (Sumber Status Tunggal)

Bagian ini adalah satu-satunya sumber status pekerjaan teknis. Bagian 1–15
menjelaskan detail masalah, target arsitektur, dan alasan keputusan. Execution
log hanya menjadi bukti historis batch; keberhasilan satu batch tidak otomatis
menutup fase atau seluruh temuan induknya.

### 0.1 Cara membaca status

Status tidak lagi diringkas dengan satu tanda `[x]`. Setiap item mempunyai tiga
dimensi yang harus dibaca bersama:

- **IMPLEMENTATION:** `NOT_STARTED`, `IN_PROGRESS`, atau `CODE_PASS`.
- **VALIDATION:** `NONE`, `AUTO_PASS`, `STAGING_PASS`, atau `UAT_PASS`.
- **RELEASE-DATA:** `N/A`, `BLOCKED`, `PROD_READY`, `DEFERRED_OWNER`, atau
  `REPAIRED_VALIDATED`.

Item hanya `DONE` apabila solusi/acceptance selesai, bukti tersedia, semua
validasi yang relevan lulus, dan tidak ada blocker atau data yang masih
ditunda. Fase hanya `DONE` bila seluruh child wajibnya `DONE`. `CODE_PASS` atau
`STAGING_PASS` sendiri tidak boleh diterjemahkan sebagai siap produksi.

### 0.2 Status fase A0–A5

| Fase | Implementasi | Validasi tertinggi | Release/data | Status fase | Alasan/gerbang berikutnya |
| --- | --- | --- | --- | --- | --- |
| A0 — baseline/deployment | `IN_PROGRESS` | `STAGING_PASS` | `BLOCKED` | `PARTIAL` | Credential DB dan runtime index/package sudah dipisahkan; cutoff lokal `finance-audit-cutoff-2026-09-05` tersedia. Full history, push remote, rotasi secret, off-site encryption, dan cutover customer belum selesai. |
| A1 — security/RBAC/scope | `IN_PROGRESS` | `AUTO_PASS` | `BLOCKED` | `PARTIAL` | Endpoint sisa, baseline role/scope nyata, step-up/MFA, dan UAT role/APK belum selesai; System Tools sensitif sudah ditutup pada Batch 136. |
| A2 — integritas bisnis/data | `CODE_PASS` | `STAGING_PASS` | `DEFERRED_OWNER` | `OPERATIONAL_PENDING` | Gate kode/query lulus; mismatch historis milik owner dan UAT browser/APK belum `UAT_PASS`. |
| A3 — navigasi/UI | `IN_PROGRESS` | `STAGING_PASS` | `N/A` | `PARTIAL` | Registry dan fondasi UI lulus; rollout UI 8.3 serta visual UAT belum selesai. |
| A4 — quality evidence | `CODE_PASS` | `AUTO_PASS` | `BLOCKED` | `TOOLING_PASS` | Tooling selesai, tetapi release nyata tetap diblokir A0 dan UAT fisik. |
| A5 — schema/release foundation | `IN_PROGRESS` | `STAGING_PASS` | `BLOCKED` | `PARTIAL` | A5.1–A5.16 dan disposition teknis SQL legacy lulus; database archive activation, recovery Git/A0, updater lintas versi customer, dan release customer nyata belum selesai. |

### 0.3 Register temuan audit

| ID | Sumber | Prioritas/fase | Masalah | Solusi/acceptance | Implementasi | Validasi | Release/data | Bukti atau langkah berikutnya |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `AUD-A1-SEC-01` | P0-01 | P0 / A1 | Endpoint Master belum seluruhnya deny-by-default. | Semua writer/read sensitif memakai permission aksi, scope, method, CSRF, dan negative test. | `IN_PROGRESS` | `AUTO_PASS` | `BLOCKED` | Batch 82, 84A–C, 91–92; inventaris endpoint sisa. |
| `AUD-A1-SEC-02` | P0-02 | P0 / A1 | Writer resep, formula, extra, dan bundle belum seragam. | Seluruh writer mempunyai RBAC aksi, CSRF/POST, concurrency, audit, dan formula versioning. | `IN_PROGRESS` | `AUTO_PASS` | `BLOCKED` | Batch 54–68; endpoint sisa dan versioning belum selesai. |
| `AUD-A1-POS-01` | P0-03 | P0 / A1 | Surface POS Mobile/APK belum seluruhnya terikat terminal/outlet. | Semua endpoint memakai bearer context otoritatif, izin aksi, step-up, dan UAT perangkat. | `IN_PROGRESS` | `AUTO_PASS` | `BLOCKED` | Batch 73–81, 89a–f, 93, 105–107; UAT APK dan surface tambahan terbuka. |
| `AUD-A1-RBAC-01` | P0-04 | P0 / A1 | Multi-role dan scope operasional terlalu luas. | Baseline role, precedence multi-role, outlet/division scope, dan negative matrix nyata lulus. | `IN_PROGRESS` | `AUTO_PASS` | `BLOCKED` | Filter role nonaktif dan fail-closed dasar lulus; baseline/UAT belum. |
| `AUD-A1-RBAC-02` | P0-05 | P0 / A1 | Penghapusan role dahulu memakai kolom relasi salah. | Relasi benar, transaksi aman, dan regression test lulus. | `CODE_PASS` | `AUTO_PASS` | `PROD_READY` | Batch 2A. |
| `AUD-A0-SEC-01` | P0-06 | P0 / A0 | Konfigurasi keamanan belum layak produksi. | External secret contract, cookie/session final, CSRF boundary, rotasi secret, MFA/step-up, dan startup fail-closed. | `IN_PROGRESS` | `STAGING_PASS` | `BLOCKED` | Batch 146: DB staging pindah ke file privat luar source, production tetap resolver, web/DB dan preflight 0 finding lulus; rotasi secret, cookie/session final, dan MFA tetap terbuka. |
| `AUD-A0-REPO-01` | P0-07 | P0 / A0+A5 | Backup/repository/runtime data belum sepenuhnya terisolasi. | Recovery Git non-destruktif, storage privat, enkripsi/retention, dan restore berkala. | `IN_PROGRESS` | `STAGING_PASS` | `BLOCKED` | Batch 146–147 melepas 1.367 payload runtime tanpa menghapus data dan menetapkan cutoff lokal bertag; clone masih shallow, push/full history, temp pack, serta off-site/enkripsi terbuka. |
| `AUD-A5-MIG-01` | P0-08 | P0 / A5 | Deployment schema belum sepenuhnya deterministik. | Semua schema/seed customer masuk katalog berurutan, checksum, clean-install, upgrade, dan rollback. | `IN_PROGRESS` | `STAGING_PASS` | `BLOCKED` | Batch 138/140/141/145: baseline, seed, bootstrap, rollback, serta disposition tujuh SQL legacy lulus; updater dari release customer nyata dan delivery tetap belum selesai. |
| `AUD-A1-PRINT-01` | P0-09 | P0 / A1+A5 | Printer Agent/service lokal belum mempunyai lifecycle produksi lengkap. | Pairing, auth, rotation, installer service, recovery, version compatibility, dan UAT fisik. | `IN_PROGRESS` | `AUTO_PASS` | `BLOCKED` | Trust contract dan smoke ada; service customer/UAT belum. |
| `AUD-A2-DATA-01` | P1-01 | P1 / A2 | Component mismatch nilai historis. | Script koreksi/VOID/cache benar; data hanya direpair owner dengan preview dan before/after. | `CODE_PASS` | `STAGING_PASS` | `DEFERRED_OWNER` | Batch 69–72; repair data tidak dikerjakan otomatis. |
| `AUD-A2-DASH-01` | P1-02 | P1 / A2 | Dashboard dahulu menyembunyikan mismatch nilai. | Quantity dan value mismatch dibedakan, dijelaskan, dan diuji. | `CODE_PASS` | `AUTO_PASS` | `PROD_READY` | Batch 48 dan regression dashboard. |
| `AUD-A3-NAV-01` | P1-03 | P1 / A3.1 | Sidebar mempunyai dua sumber kebenaran. | Renderer hanya memakai registry database terotorisasi. | `CODE_PASS` | `STAGING_PASS` | `PROD_READY` | Batch 100–101. |
| `AUD-A3-NAV-02` | P1-04 | P1 / A3.1 | Favorite/menu dapat berbeda dari permission resolver. | Favorite, pin, reorder, dan menu memakai resolver yang sama serta fail-closed. | `CODE_PASS` | `STAGING_PASS` | `PROD_READY` | Batch 102. |
| `AUD-A3-NAV-03` | P1-05 | P1 / A3.1 | Duplikasi URL dan alias page implisit. | URL/alias/parent/sort/icon kanonis tanpa collision. | `CODE_PASS` | `STAGING_PASS` | `PROD_READY` | SQL A3 dijalankan dua kali; Batch 100 dan 103. |
| `AUD-A3-IA-01` | P1-06 | P1 / A3.2 | Struktur menu belum seluruhnya berorientasi tugas user. | Workspace/tab per rumpun selesai tanpa registry kedua dan lulus UAT. | `IN_PROGRESS` | `AUTO_PASS` | `N/A` | Fondasi registry ada; konsolidasi halaman dan UAT terbuka. |
| `AUD-A3-UI-00` | P1-07 | P1 / A3.2 | Adopsi design system belum menyeluruh. | Seluruh wave 8.3 selesai, duplikasi dibersihkan, dan visual UAT lulus. | `IN_PROGRESS` | `AUTO_PASS` | `N/A` | Fondasi Batch 104 lulus; lihat checklist 0.4. |
| `AUD-C2-BRAND-01` | P1-08 | P1 / A0+A5→C2 | Branding/tenant dan hardcode identitas belum terpusat. | Boundary config/secret teknis selesai di `_30`; UI profil usaha/onboarding dikerjakan pada C2 `_28`. | `NOT_STARTED` | `NONE` | `BLOCKED` | Pisahkan technical handoff dari productization customer. |
| `AUD-A5-RUNTIME-01` | P1-09 | P1 / A0+A5 | Runtime/dependency deployment belum mempunyai matrix final. | Versi PHP/MariaDB/extension/Node/Python diuji pada install/upgrade. | `CODE_PASS` | `STAGING_PASS` | `BLOCKED` | Batch 142: matrix dan probe otomatis lulus pada PHP/FPM 8.1.32, MariaDB 10.6.23, Node 20.20.2, npm 10.8.2, Python 3.10.12; customer release masih menunggu runtime security qualification, `fileinfo` untuk WhatsApp file, dan Composer build yang lebih baru. |
| `AUD-A4-TEST-01` | P1-10 | P1 / A4+A5 | Test updater/install/upgrade belum lengkap. | Quality gate, clean install, upgrade, restore/rollback, dan UAT perangkat mempunyai bukti. | `IN_PROGRESS` | `STAGING_PASS` | `BLOCKED` | A4 tooling, A5.11 restore, A5.12 clean install, dan A5.13 rollback lulus; updater customer dan UAT perangkat terbuka. |
| `AUD-A5-PACK-01` | P1-11 | P1 / A0+A5 | Repository belum menjadi paket customer yang repeatable. | Source recovery, manifest versi, signed artifact, delivery, dan rollback lulus. | `IN_PROGRESS` | `AUTO_PASS` | `BLOCKED` | Signature/provenance dan source/runtime exclusion lulus; full-history/commit recovery, signing key produksi, installer/updater, serta delivery customer belum. |
| `AUD-A2-PAY-01` | P2-01 | P2 / A2 | Slip payroll belum menjelaskan uang makan terpisah. | Aturan hitung, UI, slip, dan audit disepakati serta diuji. | `NOT_STARTED` | `NONE` | `BLOCKED` | Batch bisnis khusus setelah gerbang prioritas tinggi. |
| `AUD-A2-FIN-01` | P2-02 | P2 / A2 | Riwayat rekening membingungkan pada transaksi backdate. | Running balance/as-of dan label backdate konsisten. | `NOT_STARTED` | `NONE` | `BLOCKED` | Perlu acceptance finance dan fixture backdate. |
| `AUD-A2-PH-01` | P2-03 | P2 / A2 | Jadwal PH lama mendahului eligibility. | Keputusan migrasi/arsip dan audit entitlement tertulis. | `NOT_STARTED` | `NONE` | `DEFERRED_OWNER` | Tidak mengubah data tanpa keputusan owner. |
| `AUD-A2-PUR-01` | P2-04 | P2 / A2 | Receipt purchase historis belum lengkap. | Repair/arsip dengan preview dan rekonsiliasi stok/nilai. | `NOT_STARTED` | `NONE` | `DEFERRED_OWNER` | Data historis memerlukan persetujuan. |
| `AUD-A2-POSDATA-01` | P2-05 | P2 / A2 | Status terminal order lama belum dinormalisasi. | Aturan normalisasi dan replay-safe audit disetujui. | `CODE_PASS` | `STAGING_PASS` | `DEFERRED_OWNER` | Sudah diaudit tanpa replay; keputusan data tetap milik owner. |
| `AUD-A1-REVIEW-01` | P2-06 | P2 / A1 | Public review memerlukan anti-spam. | Rate limit, validation, abuse logging, dan privacy rule. | `NOT_STARTED` | `NONE` | `BLOCKED` | Masuk security batch terpisah. |
| `AUD-A5-RET-01` | P2-07 | P2 / A5 | Availability rebuild log belum mempunyai retention. | Retention period, purge terukur, audit, backup, dan rollback. | `CODE_PASS` | `STAGING_PASS` | `BLOCKED` | Batch 143: policy, read-only preflight, quarantine audit, checksum, dan restore rollback tersedia; 52.445 row sukses lama baru kandidat archive, database purge tetap OFF sampai archive/agregasi lulus. |
| `AUD-A5-LIFE-01` | P2-08 | P2 / A5→C3 | Upload dan service pendamping belum mempunyai lifecycle produk. | Lokasi runtime, permission, backup, upgrade, uninstall, dan retention terdokumentasi. | `CODE_PASS` | `AUTO_PASS` | `BLOCKED` | Batch 143 menetapkan lokasi/preservasi/logrotate/uninstall; pemindahan runtime dan installer customer tetap C3. |
| `AUD-A1-SYS-01` | NEW-01 | P0 / A1 | Halaman System Tools dapat mengirim path root, daftar dump, status replication/failover, dan seluruh config kepada satu izin view. | Pecah izin read-sensitive, whitelist field, redaksi path/backup metadata, dan negative test. | `CODE_PASS` | `AUTO_PASS` | `PROD_READY` | Batch 136: hak Export menjadi izin baca sensitif terpisah; View-only mendapat ringkasan tanpa path/metadata; config di-whitelist tanpa password; test DB menjadi POST+CSRF; 18 negative contract dan 74 regression lulus. |
| `AUD-A1-TG-01` | NEW-02 | P1 / A1+A5 | Laporan internal belum mempunyai kanal Telegram Bot yang terotorisasi dan berjejak. | Target group/channel allowlist, webhook secret, queue idempoten, jadwal, RBAC, CSRF, log, resolusi status tidak pasti, dan worker aman. | `CODE_PASS` | `UAT_PASS` | `BLOCKED` | Batch 132–139: bot, target Namua, outbound/queue/webhook aktif; notifikasi Codex kini membawa ringkasan jawaban akhir yang dibatasi dan disaring tanpa prompt/tool output. Penutupan release tetap menunggu UAT command inbound dan otorisasi per pengirim untuk grup non-tepercaya. |

### 0.4 Checklist rollout UI 8.3

| ID | Gelombang | Scope/acceptance | Implementasi | Validasi | Status nyata |
| --- | ---: | --- | --- | --- | --- |
| `AUD-A3-UI-01` | 1 | Shared button, icon action, alert/confirm, loading, dan form validation tersedia serta dipakai konsisten. | `CODE_PASS` | `AUTO_PASS` | Primitive global tersedia; adopsi halaman lama tetap diperiksa per wave. |
| `AUD-A3-UI-02` | 2 | Filter, table, pagination, loading/error/empty state konsisten dan responsive. | `IN_PROGRESS` | `AUTO_PASS` | Fondasi CSS ada; seluruh view belum dimigrasikan. |
| `AUD-A3-UI-03` | 3 | Sidebar, page header, tabs, cards, keyboard focus, dan mobile shell konsisten. | `CODE_PASS` | `STAGING_PASS` | Shell/sidebar lulus; visual UAT belum. |
| `AUD-A3-UI-04` | 4 | POS web dan reservation memakai pola baru tanpa mengganggu kontrak APK. | `IN_PROGRESS` | `AUTO_PASS` | Belum audit visual per halaman; file APK dilindungi. |
| `AUD-A3-UI-05` | 5 | Inventory dan production selesai dimigrasikan serta regression lulus. | `IN_PROGRESS` | `AUTO_PASS` | Baru memperoleh shell global. |
| `AUD-A3-UI-06` | 6 | Purchase dan finance selesai dimigrasikan serta regression lulus. | `IN_PROGRESS` | `AUTO_PASS` | Baru memperoleh shell global. |
| `AUD-A3-UI-07` | 7 | Attendance, payroll, dan asset selesai dimigrasikan serta regression lulus. | `IN_PROGRESS` | `AUTO_PASS` | Baru memperoleh shell global. |
| `AUD-A3-UI-08` | 8 | Master, reports, dan system selesai dimigrasikan dengan permission tetap fail-closed. | `IN_PROGRESS` | `AUTO_PASS` | Sidebar manage dan dashboard roadmap internal bertab memakai primitive; rumpun lain belum lengkap. |
| `AUD-A3-UI-09` | 9 | Style/script duplikat dibersihkan per rumpun dan visual UAT desktop/mobile `UAT_PASS`. | `NOT_STARTED` | `NONE` | Tidak boleh big-bang rewrite. |

### 0.5 Register SQL staging dan server utama

SQL baru yang menjadi bagian implementasi wajib direview, dibackup/preflight,
langsung dijalankan di staging, diuji ulang bila idempoten, dan dicatat. Server
utama tetap dijalankan oleh pemilik. SQL repair data historis atau destructive
tidak otomatis dijalankan.

| File SQL | Klasifikasi | Status staging | Bukti staging | Status server utama | Tindakan berikutnya |
| --- | --- | --- | --- | --- | --- |
| `2026-08-15b_wa_report_schedule.sql` | Legacy → `baseline` | `DISPOSITION_CLOSED` | DDL dimiliki clean-install baseline; seed default diganti `2026-09-05e`; file lama tidak direplay. | `DO_NOT_RUN` | Server existing memakai migration runner; fresh install memakai baseline dan seed kanonis. |
| `2026-08-17e_pos_whatsapp_runtime_schema_preflight.sql` | Legacy → `replace` | `DISPOSITION_CLOSED` | File gabungan POS/WhatsApp berisiko dan tidak direplay; gap baru wajib migration sempit per domain. | `DO_NOT_RUN` | Jangan deploy file ini pada staging maupun server utama. |
| `2026-09-02a_wa_report_schedule_claim_lease.sql` | Legacy → `enroll` | `STAGING_PASS` | Fingerprint lease exact; state boleh masuk source-line managed tanpa klaim bahwa file lama pernah dieksekusi. | `DO_NOT_RUN` | Upgrade otomatis hanya dari receipt `finance-managed-v1`; instalasi lama perlu bridge manual. |
| `2026-09-03a_auth_login_throttle_foundation.sql` | Legacy → `enroll` | `STAGING_PASS` | Tabel, index, dan FK bernama exact pada fingerprint; tidak perlu replay. | `DO_NOT_RUN` | Enroll hanya lewat fingerprint/receipt, bukan insert ledger palsu. |
| `2026-09-03b_auth_session_log_login_at_microsecond_compatibility.sql` | Legacy → `enroll` | `STAGING_PASS` | Kontrak `DATETIME(6)` exact pada fingerprint; delimiter SQL lama tidak direplay. | `DO_NOT_RUN` | Enroll hanya lewat fingerprint/receipt setelah dependency auth lulus. |
| `2026-09-04a_a3_navigation_registry_canonicalization.sql` | Legacy → `retire` | `DISPOSITION_CLOSED` | DML reparent/deactivate/delete berisiko dipensiunkan; clean-install seed menjadi sumber kanonis. | `DO_NOT_RUN` | Perubahan navigasi berikutnya wajib migration sempit dengan postcondition. |
| `2026-09-04b_a3_page_alias_registry.sql` | Legacy → `enroll` | `STAGING_PASS` | Fingerprint struktur, FK, seed semantik, target, dan collision exact. | `DO_NOT_RUN` | Enroll hanya lewat fingerprint/receipt; DML lama tidak direplay. |
| `2026-09-04c_a5_schema_migration_registry_foundation.sql` | Managed migration | `STAGING_PASS` | Applied 1 dan replay skipped 1; ledger exact. | `PENDING_OWNER` | Jalankan melalui migration runner saat deployment utama. |
| `2026-09-05a_telegram_bot_foundation.sql` | Managed migration | `STAGING_PASS` | Backup valid; runner applied 1/skipped 1 lalu replay skipped 2; 6 tabel, 4 page, 5 menu, dan RBAC exact. | `PENDING_OWNER` | Jalankan melalui migration runner setelah `2026-09-04c`; provision secret dan worker secara terpisah. |
| `2026-09-05b_telegram_setup_guide.sql` | Managed seed | `STAGING_PASS` | Backup valid; runner applied 1/skipped 2 lalu replay skipped 3; page/menu tunggal dan grant SUPERADMIN view-only exact. | `PENDING_OWNER` | Jalankan melalui migration runner setelah `2026-09-05a`; hanya menambah page/menu panduan dan grant view-only. |
| `2026-09-05c_telegram_safe_activation_default.sql` | Managed seed | `STAGING_PASS` | Backup valid; runner applied 1/skipped 3 lalu replay skipped 4; setting untouched berubah OFF, ledger/checksum exact. | `PENDING_OWNER` | Wajib dijalankan tepat setelah `2026-09-05b`; jangan rollout aplikasi bila ledger `09-05c` belum tercatat. Nilai yang pernah diedit operator dipertahankan. |
| `2026-09-05d_a5_clean_install_reference_seed.sql` | Managed clean-install seed | `STAGING_PASS_DISPOSABLE` | Runner memasang seed sebelum Telegram; hasil exact 20 group, 206 page, 241 menu, 10 alias, 1 SUPERADMIN, dan 206 permission. | `FRESH_INSTALL_ONLY` | Jangan dijalankan pada staging/server utama yang sudah berisi data; migration runner otomatis mengecualikannya dari policy upgrade. |
| `2026-09-05e_whatsapp_safe_reference_seed.sql` | Managed repeat-safe seed | `STAGING_PASS` | Backup privat valid; runner applied 1/skipped 4 lalu replay applied 0/skipped 5; template, session, ledger, dan checksum exact. | `PENDING_OWNER` | Jalankan melalui migration runner policy `upgrade`; jangan menjalankan pengganti legacy secara manual di luar runner. |
| `baseline/2026-09-05_clean_install_schema.sql` | Clean-install schema-only | `STAGING_PASS` | 282 tabel dan checksum terkunci; catalog clean-install berisi enam migration, sedangkan seed baru `09-05e` juga lulus staging/replay. | `NOT_FOR_UPGRADE` | Hanya titik awal database customer baru, dilanjutkan migration runner policy clean_install. |

Migration runner kini mengelola enam file: `2026-09-04c`, clean-install-only
`2026-09-05d`, repeat-safe `2026-09-05e`, dan `2026-09-05a`–`2026-09-05c`.
Tujuh file lain tetap legacy/non-deployable, tetapi disposition-nya sudah final
dan dijaga otomatis: 1 baseline, 4 enroll via fingerprint, 1 replace, dan
1 retire. Jangan menjalankan seluruh folder `sql/` sekaligus.

### 0.6 Register tahap terlewat dan ditunda

Register ini mencegah pekerjaan lama hilang ketika eksekusi sudah maju ke fase
berikutnya. `TERLEWAT` berarti belum pernah ditutup walaupun fase sesudahnya
sudah berjalan. `DITUNDA` berarti keputusan penundaan memang disengaja.

| ID | Klasifikasi | Pekerjaan yang belum tertutup | Alasan/status nyata | Rencana tindak lanjut |
| --- | --- | --- | --- | --- |
| `GAP-01` | `IN_PROGRESS` | Penutupan A0: credential produksi, rotasi secret, recovery Git, dan pemisahan runtime data customer. | Credential DB dan runtime index/package lulus Batch 146; cutoff commit/tag lokal dibuat Batch 147. Source masih shallow, cutoff belum dipush, secret lama belum dirotasi, dan off-site encryption belum aktif. | Verifikasi lalu push cutoff atas perintah owner, tetapkan strategi full-history, dan rotasi secret pada cutover terjadwal; jangan menghapus runtime staging. |
| `GAP-02` | `TERLEWAT_SEBAGIAN` | Sisa A1 non-mobile: inventaris endpoint, baseline role/scope nyata, public review anti-spam, step-up/MFA. | Writer prioritas lulus, tetapi acceptance fase belum lengkap. | Kerjakan batch kecil setelah A5; surface mobile hanya dilanjutkan setelah build APK siap. |
| `GAP-03` | `TERLEWAT` | P2 bisnis A2: uang makan slip payroll dan running balance rekening backdate. | Belum mendapat batch khusus walaupun pekerjaan bergerak ke A3–A5. | Audit aturan bisnis, buat fixture, lalu minta acceptance finance sebelum implementasi. |
| `GAP-04` | `TERLEWAT` | A3.2 rollout UI 8.3 gelombang 2 dan 4–9 serta visual UAT. | Fondasi UI dan sidebar selesai, tetapi migrasi halaman tidak pernah ditutup per wave. | Kembali ke checklist UI 8.3 gelombang 01–09 setelah fondasi A5; satu rumpun per batch, bukan rewrite besar. |
| `GAP-05` | `TERLEWAT_OPERASIONAL` | UAT browser role, APK/device, printer fisik, dan updater customer. | Automated tooling A4 lulus tetapi tidak menggantikan perangkat nyata. | Jalankan setelah kandidat build dan APK siap; bukti UAT harus terikat ke versi artefak. |
| `GAP-06` | `TERLEWAT_SEBAGIAN` | Telegram inbound `/menu`, `/omzet`, `/belanja` dan allowlist identitas pengirim. | Outbound/queue/webhook/Namua lulus; command inbound belum diterima sebagai UAT. | Uji pada Namua, lalu tambahkan sender allowlist sebelum dipakai pada grup non-tepercaya. |
| `GAP-07` | `SELESAI_TEKNIS` | Disposition tujuh SQL legacy dan batas jalur updater. | Batch 145 mengunci 1 baseline, 4 enroll, 1 replace, dan 1 retire; seluruh replay legacy serta adopsi ledger palsu ditolak. | Source-line `finance-managed-v1` dapat memakai migration managed; instalasi pre-catalog wajib bridge manual. UAT updater customer tetap `GAP-05`/C3. |
| `DEFER-01` | `DITUNDA_OWNER` | Repair mismatch component dan anomali transaksi historis. | Pemilik meminta data dibiarkan; script koreksi sudah lulus. | Owner memperbaiki melalui modul; otomatisasi hanya dengan preview dan persetujuan terpisah. |
| `DEFER-02` | `DITUNDA_TERLINDUNGI` | Sisa perubahan dan UAT POS Mobile/APK. | APK sedang dikembangkan paralel dan file integrasi dilindungi. | Jangan ubah kontrak mobile; lanjut saat pemilik menyerahkan build/acceptance terbaru. |
| `DEFER-03` | `DITUNDA_OWNER` | Migrasi/arsip PH lama dan receipt purchase historis. | Perubahan data historis berisiko dan membutuhkan keputusan owner. | Tetap read-only sampai aturan arsip/repair disetujui. |

**Urutan recovery yang berlaku:** `GAP-01` deployment/repo →
`GAP-02` A1 non-mobile dan `GAP-03` A2 bisnis → `GAP-04` UI per rumpun →
`GAP-05` UAT terikat artefak. `DEFER-01`–`03` tidak dikerjakan otomatis.

### 0.7 Ringkasan historis temuan (bukan sumber status)

Daftar berikut mempertahankan konteks audit lama. Bila kalimatnya berbeda dari
control board 0.2–0.6, control board yang berlaku.

- `[~]` P0-01 — guard endpoint Master: sebagian writer sudah diperketat;
  seluruh registry, direct URL, dan role negative test masih terbuka.
- `[~]` P0-02 — recipe, formula, extra, dan bundle: writer prioritas sudah
  diberi RBAC/CSRF/POST-only; seluruh endpoint dan versioning formula belum.
- `[~]` P0-03 — POS Mobile: authorization endpoint baca/tulis dan POST-only 11
  writer sudah ada. Audit ulang setelah Batch 88 menemukan implementasi binding
  outlet/terminal yang dicatat pada Batch 75–81 tidak lengkap pada source aktif;
  Batch 89a–89f sudah memulihkan binding endpoint APK inti dan smoke monolitik
  mencapai akhir. Batch 105 menerima sanitizer cetak dan panel order masuk dari
  backup APK tanpa mengganti model aktif. Batch 107 menyelaraskan empat smoke
  lama dengan kontrak terminal cadangan: sesi OPEN milik pegawai dan outlet yang
  sama dapat dipakai, sedangkan spoof konteks, pegawai/outlet berbeda, dan sesi
  tutup tetap ditolak. Surface API tambahan, step-up, dan UAT APK nyata masih
  perlu ditutup.
- `[~]` P0-04 — multi-role dan scope: filter role nonaktif serta fail-closed
  scope dasar sudah diperbaiki; baseline role dan scope nyata belum.
- `[x]` P0-05 — penghapusan permission role memakai relasi yang benar dan
  sudah diuji pada Batch 2A.
- `[~]` P0-06 — boundary secret, production preflight, session audit, login
  throttle, dan scoped CSRF sudah ada. Credential database staging kini berada
  di file privat luar source dan preflight bebas temuan; secure-cookie final,
  MFA, rotasi secret, dan secret store customer masih terbuka.
- `[~]` P0-07 — backup tidak lagi mendorong perubahan otomatis ke `main`;
  storage privat, checksum, retention quarantine, restore drill, dan untracking
  runtime tersedia. Connectivity Git lulus, tetapi clone masih shallow;
  baseline commit, temp pack, dan off-site terenkripsi masih terbuka.
- `[~]` P0-08 — migration runner, katalog checksum, registry, bundle,
  disposable restore, clean install, rollback, runtime matrix, retention, dan
  signature/provenance sudah lulus. Disposition tujuh SQL legacy telah dikunci
  tanpa replay; updater customer lintas release dan delivery masih terbuka.
- `[~]` P0-09 — trust boundary Printer Agent sudah diperketat; installer
  service, pairing produksi, rotation, dan recovery belum.
- `[x]` P1-01 pada sisi script — workflow koreksi nilai/VOID tidak mengubah
  kuantitas dan refresh cache HPP live mencakup material, item, dan component.
  Mismatch historis dipisahkan sebagai pekerjaan data pemilik, bukan blocker
  script A2.
- `[x]` P1-02 — dashboard sudah menghitung mismatch kuantitas dan nilai FIFO;
  smoke Batch 48 dan regression berikutnya lulus.
- `[x]` P1-03 — sidebar runtime sekarang hanya merender tree terotorisasi dari
  registry database; regroup, injection, ikon, dan menu sintetis hardcode sudah
  dikeluarkan dari renderer.
- `[x]` P1-04 — sidebar, favorite, pin, dan reorder memakai resolver akses yang
  sama serta fail-closed untuk page/menu nonaktif atau tidak terdaftar.
- `[x]` P1-05 — URL kanonis, ikon, urutan sibling, sales alias, dan sepuluh
  page alias sudah dinormalisasi. Enam deklarasi route identik tetap dibekukan
  karena dimiliki pekerjaan APK, tetapi probe memastikan tidak ada target yang
  bertentangan.
- `[x]` P1-06 — fondasi workspace kanonis untuk Master, Product Monitoring,
  Inventory/Component, dan Online Food sudah dipindahkan ke registry. Konsolidasi
  tab per halaman dapat dilanjutkan sebagai penyempurnaan UI tanpa membuat
  sumber navigasi kedua.
- `[x]` P1-07 pada fondasi A3 — app shell, token, header, action bar, card,
  filter, table region, state, focus, dan aturan responsive tersedia global.
  Modernisasi detail 336 view dilakukan bertahap di atas fondasi yang sama,
  bukan syarat mengulang arsitektur A3.
- `[ ]` P1-08 — branding/tenant masih menjadi pekerjaan productization di
  roadmap komersialisasi; hardcode runtime belum seluruhnya dihapus.
- `[~]` P1-09 — dependency Composer, npm, dan Python sudah dikunci serta
  diperiksa vulnerability gate. Matrix A5.14 dan probe staging sudah lulus;
  qualification runtime customer, `fileinfo`, dan pembaruan Composer build
  masih memblokir klaim produksi.
- `[~]` P1-10 — quality gate A4 sudah mencakup regression lintas modul, browser,
  Printer Agent, preflight, vulnerability, dan static analysis. Clean install,
  upgrade, restore, health, dan rollback A5 sudah lulus; updater customer serta
  UAT perangkat tetap terbuka.
- `[~]` P1-11 — allow/deny package policy, secret scan, builder deterministik,
  serta signature/provenance Ed25519 sudah tersedia. Recovery object Git,
  signing key produksi, installer/updater, dan delivery customer tetap
  pekerjaan A0/A5/C3.
- `[ ]` P2-01 sampai P2-06 masih menunggu batch/keputusan bisnis. P2-07 dan
  P2-08 sudah memperoleh baseline retention/lifecycle A5.15; aktivasi purge
  database dan installer customer tetap diblokir gerbang archive/C3.

### 0.8 Indeks bukti batch

Daftar ini menunjukkan batch yang pernah lulus pada scope masing-masing. Ia
tidak menjadi checklist penyelesaian fase; status aktif tetap berada pada
control board 0.2–0.6.

- `[x]` Batch 48: dashboard component membedakan mismatch quantity dan nilai.
- `[x]` Batch 53.1–53.2: login throttle, session audit, dan presisi timestamp.
- `[x]` Batch 54–65: hardening writer formula/recipe/extra/bundle dan validasi
  mapping Extra Group.
- `[x]` Batch 66–68: preflight read-only dan concurrency mapping.
- `[x]` Batch 69: koreksi HPP/nilai tanpa mengubah kuantitas, dengan saran HPP.
- `[x]` Batch 70: tampilan nilai aktif dan VOID koreksi berbasis exact-state.
- `[x]` Batch 72: sinkronisasi cache HPP live setelah koreksi nilai dan VOID.
- `[x]` Batch 73: POS Mobile writer wajib POST dan diuji sebelum autentikasi.
- `[x]` Batch 74: POS Mobile token terikat ke terminal aktif dan device key.
- `[x]` Batch 75: POS Mobile sesi kasir terikat ke terminal/outlet token dan
  tidak mengekspos daftar sesi global pada bearer request.
- `[x]` Batch 76: POS Mobile daftar dan detail order bearer terikat ke outlet
  token dengan 404 generik untuk order lintas outlet.
- `[x]` Batch 77: lima endpoint order-id POS Mobile bearer terikat ke outlet
  token sebelum preview, cetak, payment preparation, atau voucher query.
- `[x]` Batch 78: writer void, refund, dan payment POS Mobile terikat ke outlet
  token sebelum model, monitor task, atau idempotency sync-event.
- `[x]` Batch 79: document-print void, refund, dan payment POS Mobile terikat ke
  outlet order kanonik sebelum direct-print atau pembuatan print-attempt.
- `[x]` Batch 80A: bootstrap dan katalog POS Mobile terikat ke outlet/terminal
  perangkat; daftar outlet, terminal, dan sesi lintas perangkat tidak bocor.
- `[x]` Batch 81: simpan, konfirmasi, dan push order POS Mobile memakai konteks
  device otoritatif sebelum writer atau sync-event; replay lintas konteks ditolak.
- `[x]` Batch 82: direct URL opening stock divisi dan export template tidak lagi
  dapat dibuka hanya dengan permission Purchase Order.
- `[x]` Batch 83: rebuild impact dan reclassify Purchase wajib POST dan token
  CSRF maintenance khusus sebelum membaca payload atau menjalankan model.
- `[x]` Batch 84A: form generik create/edit Master menerbitkan token; store dan
  update wajib POST/CSRF sebelum validation, upload, lookup, atau writer.
- `[x]` Batch 84B: toggle, stock mode, dan reorder Master wajib POST/CSRF;
  fallback link toggle telah menjadi form POST dan AJAX mengirim header token.
- `[x]` Batch 84C: generator hari libur tahunan wajib POST/CSRF sebelum membaca
  tahun, sumber kalender `core`, atau menjalankan upsert.
- `[x]` Batch 85A: favorite sidebar wajib POST/CSRF, memfilter menu berdasarkan
  izin kanonis, mengisolasi ownership user, dan menyegarkan cache/UI secara aman.
- `[x]` Batch 85B: penyimpanan struktur sidebar superadmin wajib POST/AJAX dan
  token CSRF administrasi terpisah sebelum payload atau transaksi.
- `[x]` Batch 85C: store/update/delete/toggle menu sidebar superadmin wajib
  POST/CSRF sebelum payload, lookup, query, writer, atau cache clear.
- `[x]` Batch 86: proses dan retry antrean availability POS wajib POST/CSRF
  sebelum payload atau service; worker CLI tetap terpisah.
- `[x]` Batch 87: repair material ID, repair profile, dan merge profile pada
  rekonsiliasi stok divisi wajib POST/CSRF sebelum payload atau model.
- `[x]` Batch 88: tujuh writer System Tools—termasuk backup, konfigurasi MySQL,
  sinkronisasi, dan failover—wajib POST/CSRF sebelum side effect.
- `[x]` Batch 116: PHPStan terpin memindai seluruh `application/` dengan
  baseline lama yang dibatasi dan menjadi blocker profile release/staging.
- `[x]` Batch 117: preflight dan builder memakai policy package yang sama;
  artefak tar deterministik memiliki manifest SHA-256 dan fail-closed.

Catatan koreksi bukti: status PASS Batch 75–81 dibuka kembali setelah smoke POS
Mobile ternyata berhenti pada kegagalan pertama dan implementasi binding tidak
lengkap pada source aktif. Remediasi dimulai pada Batch 89; dokumentasi tidak
boleh menganggap binding tersebut selesai sebelum smoke penuh berakhir PASS.

- `[x]` Batch 89a: login POS Mobile tidak lagi membedakan credential versus
  terminal invalid; bearer tervalidasi membawa terminal dan outlet registry.
- `[x]` Batch 89b: bootstrap dan katalog POS Mobile memakai outlet/terminal
  bearer serta tidak mengekspos sesi, outlet, atau terminal perangkat lain.
- `[x]` Batch 89c: daftar dan detail order POS Mobile bearer dibatasi ke outlet
  perangkat; order lintas outlet menghasilkan 404 generik tanpa kebocoran.
- `[x]` Batch 89d.1: payment, void, refund, dan replay payment memvalidasi order
  serta outlet kanonis sebelum writer, monitor, atau sync-event.
- `[x]` Batch 89d.2: buka, status, preview tutup, dan tutup kasir POS Mobile
  terikat ke employee, outlet, terminal, dan sesi OPEN perangkat.
- `[x]` Batch 89d.3: simpan, konfirmasi, dan push order POS Mobile memakai
  outlet/terminal bearer serta sesi kasir OPEN; replay lintas konteks ditolak.
- `[x]` Batch 89e: preview reversal, reprint, target cetak konfirmasi, persiapan
  pembayaran, dan pencarian voucher dibatasi ke outlet order kanonis.
- `[x]` Batch 89f: cetak dokumen void, refund, dan payment memakai relasi dokumen
  ke order/outlet kanonis sebelum membuat target atau attempt cetak.
- `[x]` Batch 90: sinkronisasi runtime order POS wajib POST dan token transaksi
  sebelum payload, refresh stok live, atau job diproses.
- `[x]` Batch 91: export data opening divisi existing wajib permission export;
  pengguna view-only tidak lagi melihat tombol atau dapat mengunduh datanya.
- `[x]` Batch 92: matrix direct-URL A1 menjalankan 21 smoke terisolasi untuk 22
  kontrak route/controller lintas Master, Sidebar, Purchase, System, dan POS.
- `[x]` Batch 93: discovery dan test printer POS Mobile dibatasi ke outlet serta
  terminal bearer, memakai izin test lebih kuat, dan meredaksi secret jaringan.
- `[x]` Batch 94: period guard fail-closed bila schema belum siap dan tanggal
  ambigu/tidak valid ditolak.
- `[x]` Batch 95: perubahan HPP item-centric ikut menandai dan membangun ulang
  cache produk terdampak, termasuk resep dan component bertingkat.
- `[x]` Batch 96: reversal POS memakai quantity residual otoritatif, menolak
  keputusan invalid/duplikat, dan menjaga status partial/full tetap konsisten.
- `[x]` Batch 97: void/refund web maupun APK menyegarkan availability setelah
  commit melalui shared `Pos_model`, tanpa mengubah controller POS Mobile.
- `[x]` Batch 98: period lock menjadi barrier transaksional untuk ledger, koreksi
  nilai, POS commit/reversal, backdate, rollover, dan lifecycle close.
- `[x]` Batch 99: matrix 12 smoke terisolasi dan probe database read-only A2
  lulus; auditor menyatakan gerbang kode/smoke/read-only DB A2 selesai.
- `[x]` Batch 100–104: registry/sidebar database-only, favorite fail-closed,
  page alias eksplisit, dan fondasi design system menutup A3.1 serta fondasi
  A3.2 pada gate kode/database staging/smoke; rollout UI A3.2 tetap terbuka.
- `[x]` Batch 105: perubahan backup APK digabung terarah ke sanitizer printer
  mobile dan panel order masuk web; `Pos_model` aktif serta tiga backup tetap
  dipertahankan.
- `[x]` Batch 106: runner quality gate deterministik menyediakan profil
  parallel, release, dan staging dengan proses test terisolasi.
- `[x]` Batch 107: contract test POS Mobile untuk sesi kasir, draft/upsert,
  authorization, printer, dan reader sinkron dengan mode terminal cadangan.
- `[x]` Batch 129: control board tunggal memisahkan status implementasi,
  validasi, release/data, rollout UI, dan register SQL.
- `[x]` Batch 130: dashboard roadmap internal read-only menampilkan control
  board `_30` dan fase komersialisasi `_28`; akses dibatasi ke staging internal
  serta superadmin dan tidak masuk artefak customer.
- `[x]` Batch 131: dashboard roadmap dibagi menjadi lima tab—ringkasan,
  temuan, UI 8.3, SQL, dan komersialisasi—dengan URL hash dan tampilan mobile.
- `[x]` Batch 132: fondasi Telegram Bot internal menyediakan target allowlist,
  jadwal, command laporan, queue/lease, log, resolusi `UNKNOWN`, setting,
  sidebar, RBAC, migration managed, dan worker CLI tanpa menyentuh POS/APK.
- `[x]` Batch 133: halaman panduan Telegram bertab menjelaskan pembuatan bot,
  environment, Chat ID, webhook, target, jadwal, pengujian, dan troubleshooting;
  sidebar serta permission panduan tetap view-only.
- `[x]` Batch 134: panduan disederhanakan menjadi tiga tab dan Pengaturan
  menjadi Setup Assistant. Operasi aman dilakukan lewat UI, credential/URL
  kanonis/cron tetap server-side, dan master switch default OFF telah diterapkan
  serta direplay pada staging.
- `[~]` Batch 135: credential Telegram ditempatkan di file root-only di luar
  webroot, PHP-FPM 8.1 dan cron membaca environment yang sama, bot nyata
  terverifikasi, dan panduan aaPanel dilengkapi contoh langsung. Target/webhook
  dan UAT kirim menunggu penghentian consumer long-poll lain yang menghasilkan
  konflik `getUpdates` HTTP 409.
- `[x]` Batch 136: System Tools memisahkan baca sensitif melalui hak Export,
  memberi halaman ringkasan aman untuk View-only, memakai whitelist config
  tanpa password, melindungi test koneksi dengan POST+CSRF, dan lulus negative
  serta regression contract.
- `[x]` Batch 137: token bot di-rotate oleh pemilik tanpa dikirim ulang ke chat;
  grup Namua ditemukan dan di-allowlist, switch aktif, webhook HTTPS cocok,
  pesan langsung serta notifikasi Codex terkirim, dan antrean worker berakhir
  `SENT` dalam satu attempt dengan satu delivery log.
- `[~]` Batch 138: A5.12 menghasilkan baseline schema-only 282 tabel dan guard
  checksum/policy fail-closed. Import disposable, empat managed migration,
  default Telegram OFF, nol row customer, serta cleanup database/file lulus.
  Seed referensi dan bootstrap owner sengaja belum diekstrak dari staging.
- `[x]` Batch 139: hook penyelesaian Codex mengirim ringkasan jawaban akhir ke
  Namua. Prompt pengguna dan output tool tidak diteruskan; blok kode, URL,
  token, password, secret, dan API key disaring serta panjang pesan dibatasi.
- `[x]` Batch 140: A5.12 selesai pada staging disposable. Seed hanya membawa
  metadata navigasi netral-customer dan satu role global SUPERADMIN; bootstrap
  owner memakai file privat 0600, menolak database yang sudah mempunyai user,
  dan percobaan kedua ditolak. Lima migration, hash password, postcondition,
  serta cleanup database/file semuanya lulus.
- `[x]` Batch 141: A5.13 selesai. Health check pascainstalasi mengikat seluruh
  file pada release manifest, baseline, seed, 25 tabel wajib, ledger migration,
  SUPERADMIN, owner, serta default aman clean-install. Probe upgrade pada
  `db_finance` lulus tanpa mutasi. Drill database disposable membuktikan empat
  migration diterapkan, kegagalan canary ditolak, backup yang sama dipulihkan,
  schema/ledger/seed kembali identik, dan database/user sementara bersih 0/0.
- `[x]` Batch 142: A5.14 mengunci matrix PHP/FPM 8.1, MariaDB 10.6, Node 20,
  npm 10, Python 3.10, Composer 2, extension PHP, dan tiga dependency lock.
  Contract serta probe staging lulus; warning `fileinfo` WhatsApp dan Composer
  build lama dicatat sebagai blocker capability/release, bukan disembunyikan.
  Register 0.6 juga memisahkan tahap terlewat dari data/POS yang sengaja ditunda.
- `[x]` Batch 143: A5.15 mengganti penghapusan backup otomatis dengan
  SHA-256, retain-newest, dry-run, konfirmasi hash, quarantine, dan audit 0600.
  Preflight database hanya SELECT dan menemukan 52.445 detail availability
  sukses lama; mismatch, queue aktif, upload, ledger, serta transaksi tidak
  menjadi target. Tidak ada file/data staging yang dihapus.
- `[x]` Batch 144: A5.16 menambahkan signature/provenance Ed25519 terpisah.
  Verifier pra-instalasi menolak paket unsigned, berubah, memakai key asing,
  mempunyai path/symlink berbahaya, atau tidak cocok dengan manifest dan tiga
  policy release. Private key wajib 0600 di luar repository; sodium menjadi
  extension runtime wajib. Key produksi dan artefak customer tidak dibuat.

Status `[x]` di atas berarti batch lulus review dan smoke, bukan berarti
seluruh modul atau database customer sudah lulus UAT. Repair mismatch historis
tidak dilakukan pada batch ini sesuai batas pekerjaan operator.

## 1. Kesimpulan untuk Pemilik Aplikasi

Finance sudah memiliki fondasi bisnis yang jauh lebih lengkap daripada aplikasi
kasir biasa. Di dalam satu sistem sudah tersedia POS, reservasi, self order,
printer per divisi, loyalty, purchase order, store request, gudang, persediaan
divisi, produksi, HPP, keuangan, absensi, PH, payroll, aset, laporan, dan audit
operasional.

Aplikasi ini layak dikembangkan menjadi produk komersial, tetapi **belum aman
langsung dipaketkan dan dijual**. Hambatan utama sekarang bukan kekurangan
fitur. Hambatan utamanya adalah pengamanan aksi, konsistensi nilai data lama,
navigasi yang tumbuh tanpa satu sumber tunggal, tampilan yang belum seragam,
serta proses deployment dan backup yang masih bercampur dengan repository
source.

Kesimpulan terpenting dari scan ulang:

1. Stok bahan baku aktif saat ini sehat: tidak ada mismatch material, tidak ada
   lot negatif, dan tidak ada defisit terbuka.
2. Enam component masih mismatch nilai walaupun kuantitasnya sama. Selisih
   absolutnya sekitar Rp1.418.765.823,55 dan berasal dari nilai historis negatif
   yang terbawa ke bulan aktif.
3. Bug dashboard component yang hanya memeriksa kuantitas sudah diperbaiki pada
   Batch 48. Enam mismatch nilai tetap merupakan utang data dan masih perlu
   repair/UAT terarah.
4. Guard writer component sekarang sudah menolak unit cost negatif. Jadi masalah
   component tersebut adalah utang data historis yang harus direpair terarah,
   bukan bukti bahwa writer baru masih bebas membuat nilai negatif.
5. Endpoint master, resep, bundle, formula, dan POS mobile belum seluruhnya
   memeriksa izin per aksi. Menyembunyikan menu belum cukup untuk mengamankan
   URL.
6. Role operasional masih menerima hak yang terlalu luas. Kasir dan Barista
   dapat memperoleh hak mutasi pada area yang tidak sesuai tugasnya.
7. Sidebar saat ini mempunyai dua sumber kebenaran: tabel sys_menu dan
   penyusunan ulang secara hardcode di view sidebar. Kondisi ini membuat urutan,
   izin, ikon, dan grouping mudah berbeda.
8. Runner backup sudah dipisahkan dari perubahan otomatis ke `origin/main`,
   tetapi backup masih membutuhkan storage terpisah, retention, restore drill,
   dan pembersihan artefak/history.
9. Keamanan produksi belum layak untuk distribusi customer: sebagian boundary
   secret, CSRF, session audit, dan login throttling sudah diperbaiki, tetapi
   secure cookie, MFA, rotasi secret, dan verifikasi seluruh endpoint masih
   terbuka.
10. Fondasi UI bersama sebenarnya sudah ada, tetapi pemakaiannya belum merata.
    Ratusan view masih membawa style, script, modal, alert, filter, tabel, dan
    pagination sendiri-sendiri.
11. Focused smoke test sekarang sudah mencakup auth, POS, WhatsApp, Telegram, inventory,
    formula, recipe, mapping, release boundary, dan area prioritas lain.
    Regression browser/DB, migration/restore, CI, serta end-to-end finance,
    attendance, payroll, asset, dan printer masih belum cukup.
12. Schema deployment masih mengandalkan ratusan SQL manual. Belum ada registry
    versi schema dan migrasi deterministik untuk instalasi atau update customer.
13. Branding dan pengaturan tenant belum terpusat. Identitas Namua masih
    hardcode di banyak file dan belum dapat diubah aman oleh customer.
14. Repository pengembangan terlalu besar karena backup, upload, log, dan
    artefak operasional. Git pack lokal sudah sekitar 7,66 GiB.
15. Sebelum masuk ke pekerjaan komersialisasi, prioritas teknis tetap pengamanan,
    repair nilai component, penyederhanaan navigasi, test, schema, dan release
    foundation. Keputusan paket dan lisensi berada di dokumen `_28`.

## 2. Cara Audit Dilakukan

Pemeriksaan ini dilakukan tanpa menulis data bisnis dan tanpa menjalankan SQL
repair. Area yang diperiksa:

- 444 file PHP diperiksa dengan PHP lint; hasilnya 444 lulus dan 0 gagal.
- Smoke test inventory period guard dijalankan; 9 skenario lulus.
- Registry sys_page, sys_menu, auth_role_permission, controller page code, dan
  route dibandingkan.
- URL menu internal diuji tanpa login untuk memastikan tidak 404 atau 500.
- Hak setiap role dihitung dan dibandingkan dengan pekerjaan role tersebut.
- Controller mutasi penting dibaca untuk memastikan guard tidak hanya berada di
  tampilan.
- Kesehatan lot, ledger bulanan, defisit, runtime job, availability queue,
  rekening, PH, payroll, purchase, asset, dan relasi foreign key diperiksa.
- Inventory_control_model dipanggil untuk membandingkan ledger bulanan dengan
  lot aktual pada material dan component.
- View, CSS, JavaScript, modal, filter, tabel, pagination, ikon, warna, dan
  branding dipetakan secara statis.
- Isi repository, backup, upload, log, migration, dependency, dan script layanan
  pendamping diperiksa.

Batas audit:

- Audit ini bukan penetration test eksternal.
- Audit visual dilakukan dari struktur view dan pola komponen, bukan smoke test
  browser pada seluruh ratusan halaman.
- Tidak semua kombinasi transaksi dapat diuji hanya dengan snapshot database.
- Temuan historis tidak boleh langsung direpair massal tanpa preview,
  preflight, transaksi, audit trail, dan validasi sesudah apply.

## 3. Snapshot Kondisi Saat Ini

### 3.1 Source dan database

| Area | Hasil |
| --- | ---: |
| File PHP yang dilint | 444 |
| Kegagalan lint | 0 |
| Smoke test inventory | 9 lulus |
| Tabel aktif | 317 |
| View database | 0 |
| Foreign key | 663 |
| Engine tabel aktif | Seluruhnya InnoDB |
| sys_page | 201 total, 195 aktif |
| sys_menu | 234 total, 226 aktif |
| Role aktif | 12 |
| Baris auth_role_permission | 1.333 |
| SQL utama di folder sql | 74 |
| SQL arsip di sql/_old | 386 |

### 3.2 Kesehatan persediaan dan antrean

| Pemeriksaan | Hasil |
| --- | ---: |
| Lot material negatif | 0 |
| Lot component negatif | 0 |
| Mismatch kuantitas material | 0 |
| Mismatch nilai material | 0 |
| Mismatch kuantitas component | 0 |
| Mismatch nilai component | 6 |
| Defisit OPEN | 0 |
| Defisit SETTLED | 75 |
| Defisit VOID | 7 |
| Defisit WRITTEN_OFF | 9 |
| Adjustment material/component bertanggal masa depan | 0 |
| Runtime job SUCCESS | 2.447 |
| Runtime job CANCELLED | 3 |
| Runtime job aktif terminal atau gagal | 0 |
| Availability queue SUCCESS | 253 |
| Availability rebuild log | 885.829 baris, sekitar 304,30 MiB |

### 3.3 Kondisi sehat yang perlu dipertahankan

- Ledger rekening aktif sama dengan saldo awal ditambah seluruh mutasi.
- Hanya ada satu rekening default dari tujuh rekening aktif.
- Tidak ditemukan orphan pada PO line, receipt, SR line, salary assignment,
  schedule, asset change request, dan finance mutation account.
- Tidak ada saldo PH mentah negatif.
- Tidak ada USE PH sebelum cutover 1 Juni 2026 yang masih aktif.
- PH memakai FIFO dan expiry disinkronkan sebelum ringkasan saldo ditampilkan.
- Guard batas PH dan jumlah hari jadwal sudah tersedia.
- Fairuz memiliki saldo aktif yang diharapkan, yaitu 1 PH dari hak terbaru.
- Pengaman periode inventory menolak backdate, future date, reopen, dan gap lot
  pada smoke suite yang tersedia.
- Kasus TAHU PONG dan KENTANG tidak lagi menjadi anomali aktif setelah repair
  terarah dan pengaman periode.
- Writer component saat ini menolak unit cost negatif dan rollover produksi
  menolak valuation negatif.
- Reservasi menghitung ulang harga dan HPP saat diverifikasi kasir ke POS.
- Halaman asset management yang baru memakai guard aksi lebih baik daripada
  pola controller master lama.

Makna penting: temuan yang sudah sehat di atas tidak boleh dihapus dari test.
Ia harus diubah menjadi invariant permanen agar bug lama tidak kembali.

## 4. Temuan Prioritas 0: Harus Ditutup Sebelum Handoff Komersial

### P0-01. Endpoint master generik belum deny-by-default

**Bukti utama:** application/controllers/Master.php.

Store, update, toggle, stock mode, dan beberapa endpoint generik belum seluruhnya
memanggil guard page dan aksi yang kanonis.

**Risiko untuk user:** akun yang dapat login berpotensi memanggil URL perubahan
langsung walaupun menu disembunyikan.

**Perbaikan wajib:**

1. Buat registry satu entity ke satu page code.
2. Semua endpoint view, create, edit, delete, export, approve, post, void, dan
   reopen harus memanggil guard server.
3. Entity yang tidak terdaftar harus ditolak, bukan memakai fallback.
4. Tambahkan audit log untuk perubahan master sensitif.
5. Tambahkan negative test direct URL untuk setiap role.

### P0-02. Writer resep, extra, bundle, dan formula belum konsisten memakai RBAC

**Bukti utama:** application/controllers/Master_relation.php.

Sebagian workspace sudah dijaga, tetapi banyak endpoint mutasi recipe, formula,
extra, dan bundle belum menggunakan izin aksi yang eksplisit.

**Risiko untuk user:** perubahan formula dapat mengubah HPP, kebutuhan bahan,
stok POS, dan produksi tanpa hak yang semestinya.

**Perbaikan wajib:**

- Gunakan page code kanonis untuk setiap kelompok writer.
- Pisahkan hak melihat, mengubah, menyetujui, dan mempublikasikan formula.
- Simpan before/after, alasan, aktor, waktu, dan versi formula.
- Formula yang sudah dipakai transaksi tidak boleh diubah tanpa versioning.

### P0-03. POS Mobile memakai token dan izin per aksi, dengan scope lanjutan

**Bukti utama:** application/controllers/Pos_mobile.php.

Batch 73–81 sudah memetakan permission endpoint prioritas, mewajibkan POST pada
writer, mengikat token ke terminal aktif/device key, mengikat buka/tutup/status
kasir ke outlet serta terminal token, dan membatasi discovery/load order ke outlet
token. Lima endpoint order-id dan writer void/refund/payment juga sudah dibatasi;
document-print memakai outlet order kanonik, sedangkan bootstrap/katalog hanya
menampilkan konteks outlet serta terminal perangkat. Simpan/konfirmasi/push
order juga memakai konteks token secara otoritatif sebelum writer/sync-event.
Printer dan persetujuan step-up masih belum selesai.

**Risiko untuk user:** token valid dapat memiliki kemampuan lebih luas daripada
menu atau role pemilik token.

**Perbaikan wajib:**

- Token harus membawa user, employee, outlet, terminal, device, role, dan
  daftar entitlement.
- Setiap endpoint mobile memakai permission yang sama dengan web.
- Gunakan masa token pendek, refresh token, revoke device, dan rotasi token.
- Aksi void, refund, reprint, reopen, serta adjustment membutuhkan step-up
  approval.
- Jangan membuat kebijakan terpisah antara web POS dan APK POS.

### P0-04. Matrix role operasional terlalu luas

**Status 2026-09-03: `[~]`.** Batch 6 sudah membuat scope `NONE` dan
`AMBIGUOUS` fail-closed; masalah `NULL` yang selalu dibaca sebagai bebas adalah
risiko historis pada jalur lama, bukan alasan untuk menganggap patch tersebut
belum ada. Yang masih terbuka adalah reset role baseline, uji multi-role,
revocation, dan pembuktian scope outlet/divisi pada database nyata.

Jumlah izin saat ini:

| Role | View | Create | Edit | Delete | Export |
| --- | ---: | ---: | ---: | ---: | ---: |
| SUPERADMIN | 195 | 184 | 185 | 181 | 194 |
| CEO | 183 | 106 | 108 | 83 | 153 |
| MGR | 181 | 147 | 147 | 148 | 158 |
| ADMIN | 153 | 94 | 97 | 65 | 121 |
| ADM_GDG | 117 | 81 | 80 | 55 | 100 |
| ADM_FIN | 98 | 43 | 41 | 24 | 83 |
| KASIR | 107 | 85 | 85 | 75 | 80 |
| BARISTA | 117 | 87 | 85 | 81 | 85 |
| CHEF | 79 | 46 | 44 | 42 | 45 |
| ADM_HR | 44 | 27 | 26 | 26 | 32 |
| HOD | 33 | 11 | 7 | 6 | 18 |
| STAFF | 16 | 2 | 0 | 0 | 0 |

Jumlah besar tidak otomatis salah, tetapi sampling menemukan overgrant nyata:

- KASIR dapat memperoleh mutasi attendance settings, schedule, PH, finance
  account, mutation, payroll setup, PO, receipt, opening stock, master item,
  material, product, recipe, dan vendor.
- BARISTA dapat memperoleh mutasi inventory health, deficit, period control,
  value correction, opname, master item/material/vendor, recipe, bundle,
  component master, transfer, adjustment, dan WA settings.

Catatan historis yang menjadi alasan perbaikan:

- Auth_model sebelumnya menggabungkan seluruh role dengan pola OR tanpa state
  scope yang eksplisit.
- `get_division_scope()` sekarang membedakan `SINGLE`, `GLOBAL`, `NONE`, dan
  `AMBIGUOUS`; nilai scope invalid atau konflik tidak boleh dipakai sebagai
  akses bebas.
- Multi-role tetap perlu diuji pada kombinasi role nyata karena union
  permission dan scope adalah dua hal yang berbeda.

**Perbaikan wajib:**

1. Definisikan role dari tugas user, bukan menyalin role luas lalu menambah.
2. Seed harus bersifat konvergen: menambah hak yang benar dan mencabut hak yang
   sudah tidak benar.
3. Multi-role harus memakai union permission tetapi scope outlet/divisi harus
   eksplisit, tidak boleh null berarti bebas.
4. Pisahkan permission operasional, approval, correction, system, dan audit.
5. Sediakan halaman simulasi: pilih user lalu lihat menu, URL, dan scope efektif.
6. Sediakan report permission drift antara baseline paket dan database customer.

### P0-05. Penghapusan role salah kolom — selesai pada batch prioritas

**Status 2026-09-03: `[x]` untuk bug yang diaudit.**

**Bukti utama:** application/models/Role_model.php.

Delete auth_role_permission masih menggunakan kolom id, padahal relasi role
berada pada role_id. Operasi juga belum dibungkus transaksi dan validasi
ketergantungan.

**Risiko:** permission role dapat tertinggal atau baris yang salah terhapus.

**Perbaikan wajib:** perbaiki kondisi ke role_id, gunakan transaksi, tolak role
sistem, periksa user-role, dan lakukan post-delete assertion.

### P0-06. Konfigurasi keamanan belum layak produksi

Bagian yang sudah ditangani pada level kode atau smoke:

- Resolver environment/production preflight untuk secret aplikasi dan database.
- Scoped CSRF pada banyak writer prioritas.
- Login throttling atomik, session audit fail-closed, dan kompatibilitas
  timestamp microsecond.

Bagian yang masih aktif atau belum terbukti pada deployment:

- Staging memakai file privat `/var/lib/finance-config/database.php` di luar
  source; production sengaja mengabaikannya dan wajib memakai secret resolver.
- CSRF global dan pengecualian API belum diverifikasi menyeluruh.
- Cookie secure dan httponly belum aman.
- Environment default masih development.
- Session dapat hidup satu tahun dan regenerasi terlalu jarang.
- MFA/step-up, rotasi secret, dan secret store per instalasi belum lengkap.
- Secret printer, WhatsApp, tunnel, backup, dan integrasi belum seluruhnya
  memakai boundary per instalasi.

**Perbaikan wajib:**

- `[x]` Pindahkan credential database staging ke file privat di luar source dan
  pertahankan resolver environment untuk production.
- `[x]` Pastikan source config dan paket release tidak membawa nilai database.
- Aktifkan HTTPS-only cookie, httponly, samesite, CSRF, dan session pendek.
- Tambahkan rate limit login dan endpoint publik.
- Buat pemeriksaan startup yang gagal tertutup bila production config belum
  lengkap.
- Jangan pernah memasukkan database.php customer ke paket update.

### P0-07. Backup otomatis pernah mengubah origin/main — containment selesai

**Status 2026-09-05: `[~]`.** Batch 5 menghentikan alur commit, merge, dan push
backup ke `origin/main`. Batch 146 melepas 1.367 upload, dump, log, `.env`,
output sementara, dan bytecode dari index Git tanpa menghapus file fisik.
Contract 25/25 serta `git fsck --connectivity-only` lulus. Clone masih shallow,
perubahan belum menjadi baseline commit, dan temp pack 2,1 GB belum boleh
dihapus tanpa recovery terpisah.

**Bukti historis utama:**

- scripts/backup/backup_full.sh men-stage backup/dumps dan backup/logs, commit,
  fetch, merge, lalu push ke branch main.
- scripts/backup/backup_full.bat melakukan pola yang sama.
- Log backup 1 September mencatat Git push berhasil ke origin/main.

**Risiko untuk user dan developer:**

- Backup customer bercampur dengan source product.
- Main dapat berubah setiap 30 menit tanpa review.
- Pull developer dapat membawa dump dan log operasional.
- Backup job dapat memerge source saat working tree sedang dipakai.
- Konflik source, kebocoran data, ukuran repository, dan rollback menjadi jauh
  lebih sulit.

**Perbaikan wajib:**

1. Hentikan Git sebagai media backup database.
2. Backup ke storage terpisah: object storage, SFTP, NAS, atau repository backup
   khusus yang terenkripsi.
3. Branch source hanya berubah melalui commit developer dan release pipeline.
4. Enkripsi backup, buat checksum, retention, restore drill, dan alert gagal.
5. Tambahkan backup/dumps, backup/logs, upload customer, PID, cache, dan runtime
   artifact ke ignore serta package exclude.
6. Bersihkan riwayat Git dengan prosedur terencana setelah backup eksternal
   terverifikasi. Jangan menjalankan history rewrite langsung di server aktif.

### P0-08. Deployment schema belum deterministik

Kondisi audit awal sudah berubah setelah A5.1–A5.11. Kondisi aktif sekarang:

- Migration CodeIgniter tetap nonaktif agar tidak ada auto-latest dari web.
- Runner A5, katalog checksum, registry staging, bundle, preflight, dan restore
  disposable sudah tersedia serta teruji.
- Lima migration registry dikelola runner; tujuh SQL top-level lain
  masih legacy/non-deployable.
- Clean-install, upgrade/rollback, retention, compatibility, serta signature
  sudah mempunyai kontrak teknis; tujuh SQL legacy dan updater customer belum
  disatukan ke jalur upgrade kanonis.

**Risiko:** source baru dapat berjalan di schema lama, SQL terlewat, SQL
terulang, atau customer berbeda mempunyai struktur berbeda.

**Perbaikan wajib:**

- Tetapkan satu migration runner.
- Setiap migration mempunyai version, checksum, dependency, preflight, apply,
  verify, dan bila aman rollback.
- Updater harus menolak build bila versi schema tidak cocok.
- Installer memakai baseline schema bersih, bukan dump database operasional.
- Simpan migration history per instalasi dan tampilkan di dashboard system.

### P0-09. Printer Agent dan layanan lokal belum memakai trust contract produksi

Printer Agent telah jauh lebih rapi, tetapi masih ada risiko produk:

- Local HTTP service belum memakai autentikasi request yang kuat.
- Bootstrap dapat fail-open pada konfigurasi tertentu.
- Development Flask server belum layak menjadi service customer.
- Secret, device identity, pairing, retry, dan version compatibility belum
  menjadi kontrak rilis yang seragam.

**Perbaikan wajib:** signed request, nonce, timestamp, device pairing,
certificate/token rotation, service manager Windows/Linux, health endpoint,
version negotiation, installer, auto-start, log rotation, dan update rollback.

## 5. Temuan Prioritas 1: Stabilitas Data, Navigasi, dan Pengalaman User

### P1-01. Enam component mismatch nilai masih aktif

**Status 2026-09-04: `[x]` pada sisi script; data historis menjadi tanggung
jawab pemilik.** Batch 69–70 menyediakan koreksi nilai HPP dan VOID tanpa
mengubah kuantitas. Batch 72 dan 95 memastikan perubahan material/item/component
memicu refresh cache HPP live produk setelah commit. Aplikasi tidak melakukan
repair otomatis atau menebak HPP untuk data lama.

| Component | Qty ledger vs lot | Selisih nilai |
| --- | --- | ---: |
| SAUCE BANGKOK | 785 = 785 | Rp -1.416.006.811,46 |
| CHICKEN CUBE 40 | 25 = 25 | Rp -2.731.669,34 |
| CHICKEN SLICE SUSHI | 4 = 4 | Rp -27.197,02 |
| SAMBAL BAWANG GEPREK | sama | Rp -89,46 |
| SAMBAL DABU-DABU | sama | Rp -50,03 |
| NASI PUTIH | sama | Rp -6,24 |

Total selisih absolut sekitar Rp1.418.765.823,55. Tiga nilai terbesar sudah
muncul sejak ledger historis Juni/Juli lalu dibawa saat rollover. Opening lot
September bernilai nol, sementara ledger bulanan membawa nilai negatif.

**Kesimpulan:** ini bukan stok fisik minus dan bukan defisit. Ini adalah nilai
historis rusak yang terbawa ke periode aktif.

**Perbaikan:**

1. Buat preview per component dari sumber nilai pertama kali menjadi negatif.
2. Pastikan formula produksi, unit conversion, qty hasil, total input cost,
   waste, dan allocation denominator pada transaksi asal.
3. Repair hanya ledger/lot yang terbukti, tanpa menebak harga.
4. Simpan before/after dan alasan.
5. Jalankan ulang health check sampai qty gap dan value gap nol.
6. Tambahkan assertion unit cost, total value, dan average cost tidak negatif
   di seluruh jalan masuk component.
7. Tambahkan regression test rollover bulan dan production posting.

### P1-02. Dashboard component menampilkan clear walaupun mismatch nilai ada

**Status 2026-09-03: `[x]` pada kode, UAT masih diperlukan.** Batch 48 sudah
memasukkan `monthly_lot_value_gap` ke pemeriksaan dashboard.

Sebelum perbaikan, fungsi dashboard hanya membandingkan kuantitas monthly,
movement, dan lot sehingga pemilik dapat melihat 0 mismatch walaupun Stock
Health menemukan selisih nilai besar.

**Perbaikan yang sudah dilakukan:**

- Dashboard harus menghitung mismatch qty dan mismatch nilai.
- Card harus memisahkan “Qty berbeda” dan “Nilai FIFO berbeda”.
- Tampilkan nilai absolut, divisi, component, bulan, dan link ke detail health.
- Status clear hanya boleh muncul bila kedua gap berada dalam tolerance.
- Tambahkan test dengan qty sama tetapi nilai berbeda.

### P1-03. Sidebar mempunyai dua sumber kebenaran

Registry database saat ini relatif sehat:

- Tidak ada menu aktif dengan parent hilang.
- Tidak ada menu aktif yang menunjuk page nonaktif.
- Tidak ada page aktif tanpa permission.
- Probe URL tidak menemukan 404 atau 500.

Namun application/views/layout/sidebar.php berukuran sekitar 46 KB dan masih:

- Mengganti ikon dari map hardcode.
- Memindahkan grouping menu.
- Menyisipkan workspace sintetis.
- Menggabungkan master, inventory, availability, component, dan POS saat
  runtime.
- Dapat menghasilkan item sintetis tanpa page_id.

**Dampak:** perubahan sys_menu belum tentu sama dengan sidebar yang terlihat.
Audit database dapat lulus tetapi hasil user berbeda.

**Perbaikan:**

1. sys_menu menjadi satu-satunya sumber struktur, urutan, label, ikon, parent,
   route, dan page.
2. View sidebar hanya merender tree yang sudah diotorisasi.
3. Hapus injection dan regrouping hardcode.
4. Menu tanpa page_id hanya boleh berupa group yang tidak dapat memanggil aksi.
5. Tambahkan validator registry pada CI dan halaman System Health.

### P1-04. Favorites dan menu tanpa page berpotensi melewati filter izin

Menu_model memfilter page-linked menu berdasarkan can_view. Namun:

- Menu tanpa page_id selalu dapat tampil.
- Favorites memeriksa aktif/nonaktif, tetapi belum selalu memverifikasi izin
  page efektif.
- Pin menu dapat menerima ID menu tanpa validasi permission.

**Perbaikan:** query favorites dan pin harus memakai resolver permission yang
sama dengan sidebar. Saat izin dicabut, favorite otomatis hilang.

### P1-05. Registry masih mempunyai duplikasi dan alias implisit

Temuan aktif:

- Dua menu aktif menuju /pos/reports/sales:
  Penjualan & Margin POS dan Laporan Penjualan POS.
- Lima menu aktif tidak mempunyai ikon:
  opname divisi bulanan, opname gudang bulanan, daily recon component,
  opening component bulanan, dan opname component bulanan.
- Ada collision sort pada root, POS, finance, loyalty, POS report, asset,
  inventory control, dan menu personal.
- Enam key route terminal/outlet didefinisikan ganda.
- Sepuluh page code controller tidak terdaftar, sebagian memang alias:
  schedules v2, my schedule, stock commit audit, availability, component lot,
  component reconcile, purchase account, division lot, opening, dan warehouse
  lot.
- My schedule menjadi fail-open karena guard hanya bekerja bila page ditemukan.

**Perbaikan:**

- Setiap endpoint memiliki page sendiri atau alias eksplisit di registry.
- Alias tidak boleh bergantung pada “kalau page tidak ada maka lanjut”.
- Tambahkan unique rule konseptual untuk URL kanonis dan sort sibling.
- Gabungkan dua laporan sales menjadi satu workspace bertab.
- Lengkapi ikon dan accessibility label.

### P1-06. Struktur menu perlu disederhanakan berdasarkan pekerjaan user

Usulan susunan utama:

1. Dashboard
2. POS & Customer
3. Purchase & Supplier
4. Inventory & Production
5. Finance
6. People: Attendance & Payroll
7. Asset
8. Reports & Audit
9. Master Data
10. System
11. My Workspace

Prinsip penyederhanaan:

- User operasional melihat pekerjaan harian lebih dulu.
- Halaman setup, rebuild, repair, integrity, dan reclassify masuk System atau
  Diagnostics, bukan berdampingan dengan transaksi harian.
- Satu objek bisnis memakai satu workspace dengan tab, bukan banyak menu yang
  mengulang filter dan tabel serupa.
- Halaman personal tetap terpisah dari halaman administrasi.

Usulan penggabungan:

| Rumpun saat ini | Workspace target |
| --- | --- |
| Live stock, daily matrix, daily recon, monthly stock | Kontrol Stok Harian dengan tab Live, Matrix, Recon, Riwayat |
| Opening manual dan generated | Saldo Awal dengan sumber dan status jelas |
| Component opening, opname, recon, lot | Kontrol Component dengan tab |
| Sales dan sales margin | Penjualan & Margin |
| PH eligibility, ledger, calendar, expiry | Public Holiday dengan tab Hak, Kalender, Saldo, Audit |
| Schedule legacy dan schedules-v2 | Pertahankan V2, arsipkan legacy setelah parity |
| Payroll setup yang tersebar | Setup Payroll |
| Payroll input/approval | Input & Persetujuan |
| Payroll run/disbursement | Proses & Pembayaran |
| Asset master/change/recon/incident | Siklus Aset dengan tab |
| Purchase rebuild/reclassify | System Diagnostics, hak khusus |
| Printer setting dan customer review | Tetap terpisah di sidebar; integrasi QR berada di setting printer |

### P1-07. UI belum memakai design system tunggal

Snapshot statis:

| Pola | Jumlah |
| --- | ---: |
| View PHP | 336 |
| View dengan style inline | 282 |
| View dengan script inline | 167 |
| View memakai Remix icon | 180 |
| File terkait modal | 79 |
| Pemanggilan alert native | 62 |
| Pemanggilan confirm native | 79 |
| Halaman dengan spinner/loading | 61 |
| Table responsive | 154 |
| Empty state | 98 |
| Penanda pagination | 56 |
| Filter dengan clear/reset eksplisit | 9 |

FinanceUI di assets/js/app.js sudah menyediakan alert, confirm, prompt, toast,
dan loading button. Masalahnya adalah adopsinya belum menyeluruh.

Dampak yang dirasakan user:

- Search dan clear filter berbeda antarhalaman.
- Tombol aksi kadang teks, kadang kotak kosong, kadang ikon tanpa tooltip.
- Warna merah dipakai untuk aksi utama maupun bahaya.
- Tab, card, badge, tabel, modal, pagination, dan empty state berbeda.
- Loading dan error tidak selalu terlihat.
- Lebar tabel dan modal tidak konsisten di layar kecil.
- Tampilan satu rumpun terasa dibuat oleh aplikasi berbeda.

**Perbaikan:** bangun Finance Design System, bukan memperbaiki halaman satu per
satu tanpa pola.

Komponen minimum:

- App shell dan page header.
- Breadcrumb dan action bar.
- Primary/secondary/danger button.
- Icon action dengan tooltip dan aria-label.
- Tab utama dan subtab.
- Filter bar responsive dengan apply dan clear.
- KPI/card ringkasan.
- Data table, sticky column, empty state, skeleton, error state.
- Pagination tunggal.
- CRUD modal, drawer, dan confirm dialog.
- Form field, validation, help text, date/currency/quantity input.
- Toast dan job progress.
- Print/preview container.
- Mobile table strategy.

Aturan visual:

- Satu set token warna, typography, radius, spacing, shadow, dan z-index.
- Merah solid hanya untuk brand primary atau danger yang jelas; jangan
  menggunakan satu warna untuk dua arti pada konteks yang sama.
- Ikon harus berasal dari satu keluarga dan selalu terlihat.
- Tombol ikon wajib mempunyai title, aria-label, focus state, dan ukuran klik
  minimum.
- Apply filter dapat berupa ikon search; clear dapat berupa ikon reset, tetapi
  maknanya harus muncul pada tooltip dan pembaca layar.
- Modal create/edit pada satu rumpun memakai susunan dan footer yang sama.
- Tabel memakai header, alignment angka, badge status, action column, dan
  pagination yang sama.
- Halaman tidak boleh menambahkan token warna baru tanpa alasan desain.

### P1-08. Branding dan pengaturan tenant belum terpusat

Ditemukan referensi hardcode Namua, alamat Magnolia/Kabongan, dan identitas
usaha di sekitar 49 file, termasuk login, printer, review, dokumen HR, menu book,
asset, landing, dan roastery.

sys_app_config saat ini lebih banyak berisi backup, dashboard, POS, replication,
dan tunnel. Belum ada profil aplikasi/tenant lengkap.

Pengaturan yang harus dapat dikelola:

- Nama aplikasi dan nama badan usaha.
- Nama outlet, alamat, kontak, domain, timezone, locale, dan mata uang.
- Logo utama, logo dokumen, favicon, watermark, dan warna brand.
- Header/footer dokumen, invoice, receipt, QR, dan kontrak.
- Identitas WhatsApp/email.
- Format nomor transaksi.
- Kebijakan fiskal, service, tax, rounding, dan accounting date.
- Tema UI yang terbatas pada preset aman.
- Feature entitlement sesuai paket.

Jangan menyimpan password, token, private key, atau DB credential di halaman
pengaturan biasa.

### P1-09. Kontrak runtime dan dependency tidak sesuai kode aktual

- composer.json masih menyatakan PHP minimal 5.3.7.
- Source sudah memakai sintaks dan kemampuan PHP 8.
- Composer CLI 2.0.14 tersedia dan `composer validate --no-check-publish`
  lulus, tetapi toolchain mengeluarkan deprecation warning.
- composer.lock tidak menjadi artefak rilis yang dapat direproduksi.
- Python dependency Printer Agent dan service pendamping perlu dikunci.

**Perbaikan:** tetapkan PHP 8.x yang didukung, extension wajib, MariaDB minimum,
Node/browser bila diperlukan, Python version, lockfile, health check installer,
dan compatibility matrix per release.

### P1-10. Test otomatis belum cukup untuk updater

Focused smoke suite sekarang sudah mencakup auth, POS, WhatsApp, Telegram, inventory,
formula, recipe, mapping, release boundary, dan area prioritas lainnya. Namun
coverage belum cukup untuk:

- Permission setiap endpoint dan role.
- PO, SR, receipt, fulfillment, reversal, dan cancel.
- Produksi, lot, HPP, rollover, deficit, adjustment, recon, dan void.
- POS order, bundle, extra, DP, payment, refund, void, reservation, dan printer.
- Finance mutation, backdate, reversal, dan reconciliation.
- Attendance, PH, schedule, payroll, dan disbursement.
- Asset lock/change/incident/recon.
- Installer, migration, update, rollback, dan license entitlement.
- Browser regression pada komponen UI utama.

Updater tidak boleh diaktifkan untuk customer sebelum critical path mempunyai
test otomatis lintas modul, browser/DB integration, migration, restore, dan
quality gate CI.

### P1-11. Repository belum dapat menjadi paket customer

Snapshot working tree:

- Folder backup sekitar 706 MiB.
- Upload sekitar 339,77 MiB.
- Sekitar 837 file menyerupai log masih tracked.
- Sekitar 13 file tmp/fix/probe masih tracked.
- Git pack lokal sekitar 7,66 GiB.
- Ada 12 tabel backup tanpa primary key di schema aktif.
- Collation masih campur: 262 general_ci dan 55 unicode_ci.
- Object Git yang hilang menghalangi pemeriksaan cached diff/status normal dan
  membuat packaging/commit dari index kumulatif belum aman.

**Perbaikan:**

- Pisahkan source, runtime data, upload, backup, log, cache, config, dan secret.
- Buat manifest file yang boleh masuk paket.
- Buat manifest tabel seed awal dan tabel yang harus kosong.
- Pindahkan zz_bak dan backup table keluar schema aktif setelah arsip aman.
- Normalisasi collation secara bertahap.
- Buat cleanup history repository hanya setelah backup dan clone verification.
- Pulihkan atau verifikasi object Git secara non-destructive sebelum commit,
  packaging, atau history rewrite.

## 6. Temuan Prioritas 2 dan Utang Historis

### P2-01. Payroll belum menjelaskan uang makan yang dibayar terpisah

Ditemukan 30 detail payroll yang nilai rincian dan net pay berbeda. Selisih
mengikuti meal_total pada contoh yang diperiksa.

Ini belum tentu salah hitung, tetapi UI dan slip harus menjelaskan:

- Gaji bersih payroll.
- Uang makan terpisah.
- Total hak pegawai.
- Total yang sudah dibayar per rekening/tanggal.
- Sisa kewajiban.

Tambahkan assertion agar selisih selain komponen yang memang dibayar terpisah
ditolak.

### P2-02. Riwayat saldo rekening membingungkan pada transaksi backdate

Saldo akhir akun saat ini benar dan chain berdasarkan urutan posting ID tidak
putus. Namun jika diurutkan berdasarkan tanggal bisnis, ditemukan 62
diskontinuitas before/after.

Perbaikan:

- Bedakan tanggal transaksi, waktu posting, dan accounting period.
- Riwayat utama memakai urutan posting untuk running balance.
- Tanggal bisnis tetap dapat difilter, tetapi diberi penjelasan backdate.
- Setelah periode ditutup, backdate membutuhkan reopen/approval.
- Sediakan rebuild saldo yang idempotent dan ter-audit.

### P2-03. Enam jadwal PH lama mendahului eligibility

Fadilla memiliki enam jadwal PH dari April sampai Agustus yang lebih awal dari
effective date eligibility 17 Agustus 2026.

Data ini harus direview sebagai data historis. Jangan mengubah otomatis tanpa
dokumen kebijakan dan bukti hak pada periode tersebut.

### P2-04. Receipt purchase lama belum lengkap

Pada transaksi posted awal Juni masih ditemukan:

- 30 receipt line tanpa material_id.
- 6 receipt line tanpa lot_id.

Writer baru tidak boleh meniru pola ini. Buat audit detail per receipt untuk
menentukan apakah line adalah non-stock, mapping material lama, atau lot yang
hilang. Repair hanya kasus yang dapat dibuktikan.

### P2-05. Status terminal beberapa order POS belum dinormalisasi

Contoh:

- Order id 3469, MSO-20260822114341-AA80, berstatus PAID/PENDING tanpa commit
  dan job.
- Beberapa order VOID masih mempunyai stock_commit_status FAILED atau PENDING,
  termasuk id 3549, 3551, 3553, 3653, dan 3887.

Sebagian dapat merupakan jejak kegagalan server lama, bukan bug writer aktif.
Buat status normalization audit yang membedakan:

- Order selesai secara bisnis.
- Commit stok selesai/reversed.
- Job terminal cancelled/success.
- Data yang benar-benar perlu replay.
- Data yang hanya perlu dinormalisasi status.

### P2-06. Public customer review memerlukan anti-spam

Receipt token sekali pakai adalah fondasi baik, tetapi station QR publik dapat
menerima input tanpa rate limit atau CAPTCHA.

Tambahkan rate limit IP/device, honeypot, cooldown, moderation, duplicate
detection, dan audit member creation.

### P2-07. Availability rebuild log memerlukan retensi

885.829 baris dan sekitar 304,30 MiB menunjukkan log tumbuh terus.

Terapkan:

- Ringkasan harian untuk dashboard.
- Retensi detail terbatas.
- Archive/export bila dibutuhkan audit.
- Index sesuai query.
- Alert hanya pada kegagalan bermakna, bukan setiap event sukses.

### P2-08. Upload dan layanan pendamping memerlukan lifecycle produk

Setiap upload harus mempunyai allowlist MIME, ukuran, random path, antivirus bila
tersedia, access policy, retention, dan delete audit.

Worker, cron, Printer Agent, WhatsApp, tunnel, dan backup harus mempunyai:

- Install/uninstall.
- Start/stop/restart.
- Health check.
- Log rotation.
- Version.
- Recovery.
- Least privilege.
- Dokumentasi Windows dan Linux.

## 7. Target Arsitektur Sidebar, Page, Route, dan RBAC

Bagian ini adalah target teknis untuk menutup temuan audit. Ia tidak
menentukan paket lisensi atau FeatureGate komersial; keputusan tersebut hanya
dicatat di roadmap `_28` setelah fondasi ini lulus.

### 7.1 Satu registry kanonis

Buat satu resolver dengan alur:

1. Route dikenali.
2. Route menunjuk page code.
3. Page menunjuk module dan capability.
4. Role/user memberi action permission.
5. Outlet/division scope dihitung eksplisit.
6. Menu hanya tampil bila can_view.
7. Endpoint mengulangi pemeriksaan action di server.
8. Audit log menyimpan permission dan scope efektif.

Tidak boleh ada tiga definisi terpisah di route, controller, dan sidebar tanpa
validator.

### 7.2 Permission tidak cukup hanya CRUD

Tambahkan capability bisnis bila diperlukan:

- view
- create
- edit
- delete
- export
- approve
- post
- void
- refund
- reopen
- reconcile
- adjust
- reclassify
- rebuild
- manage_settings
- manage_access
- impersonate
- download_sensitive

Jika schema permission belum diperluas, buat action policy layer di atas CRUD
sebagai transisi. Jangan menyamakan edit dengan approve atau post.

### 7.3 Role baseline yang lebih aman

- Superadmin: system dan emergency access, semua aksi tercatat.
- Management: laporan lintas divisi dan approval, bukan writer teknis otomatis.
- Finance: rekening, settlement, reconciliation, payroll payment; bukan recipe.
- Warehouse: PO/SR/receipt/warehouse stock; bukan payroll atau finance account.
- HOD: jadwal dan operasi divisinya; tidak lintas scope.
- Cashier: POS, payment, reservation verification, reprint terbatas.
- Barista/Chef: order produksi divisinya, stock view/recon yang ditugaskan.
- HR: employee, attendance, PH, payroll input sesuai tugas.
- Staff: self service dan tugas eksplisit.
- Auditor: read/export dengan data sensitif yang disamarkan sesuai kebijakan.

### 7.4 Validator otomatis registry

Build harus gagal bila ditemukan:

- Route mutasi tanpa policy.
- Controller page code tidak terdaftar dan tidak menjadi alias eksplisit.
- Menu aktif tanpa ikon atau label.
- Menu action tanpa page_id.
- URL kanonis ganda.
- Sort sibling ganda.
- Page aktif tanpa owner/module/permission baseline.
- Favorite menuju page yang tidak lagi diizinkan.
- Role package drift dari baseline.

## 8. Target Finance Design System

Bagian ini menetapkan standar UI aplikasi agar bug usability dan regression
visual dapat diperbaiki konsisten. Rancangan white-label dan paket branding
customer berada di roadmap `_28`.

### 8.1 Artefak yang perlu dibuat

- Halaman component gallery internal.
- File token CSS tunggal.
- Library komponen view/JS.
- Panduan penggunaan dan contoh benar/salah.
- Checklist visual QA desktop, tablet, dan mobile.
- Screenshot regression untuk halaman penting.

### 8.2 Pola halaman standar

Urutan halaman:

1. Page header: judul, penjelasan singkat, primary action.
2. Ringkasan/KPI bila memang membantu keputusan.
3. Tab dan subtab bila satu objek mempunyai beberapa sudut pandang.
4. Filter bar.
5. Tabel/card data.
6. Pagination dan total data.
7. Empty/error/loading state.
8. Modal atau drawer untuk CRUD sederhana.
9. Halaman khusus untuk editor kompleks dan live preview.

### 8.3 Migrasi UI bertahap

Urutan, status, dan acceptance sembilan gelombang UI berada pada checklist
kanonis `AUD-A3-UI-01` sampai `AUD-A3-UI-09` di bagian 0.4. Fondasi primitive
dan shell sudah lulus, tetapi rollout seluruh view dan visual UAT belum selesai.
Karena itu pekerjaan ini adalah A3.2 yang masih `IN_PROGRESS`, bukan pekerjaan
A5 dan bukan fase yang sudah `DONE`.

Jangan melakukan big-bang CSS rewrite. Migrasi per rumpun dengan visual
regression agar halaman produksi tidak rusak.

## 9. Pengaturan Sistem untuk Produk Siap Jual

Bagian ini dibatasi sebagai checklist technical boundary: apa yang boleh berada
di source, deployment, atau secret store. Detail productization, onboarding
customer, entitlement, dan halaman komersial menjadi tanggung jawab roadmap
`_28`.

Pisahkan tiga jenis konfigurasi.

### 9.1 Product/build configuration

Dikendalikan vendor:

- Product ID dan release version.
- Schema version.
- Build channel.
- Feature catalog.
- License public key.
- Compatibility matrix.
- Update manifest.

Customer tidak boleh mengedit bagian ini.

### 9.2 Tenant/business configuration

Dapat dikelola customer sesuai izin:

- Profil usaha dan outlet.
- Branding.
- Locale, timezone, currency.
- Format dokumen.
- Kebijakan bisnis.
- Default account dan mapping.
- Printer layout.
- Feature entitlement yang diterima dari license server.

### 9.3 Secret dan machine configuration

Berada di luar source dan database setting biasa:

- DB credential.
- Encryption key.
- License device key.
- API token.
- Printer Agent secret.
- SMTP/WhatsApp secret.
- Backup credential.
- Tunnel credential.

### 9.4 Generator instalasi

Untuk scope audit, yang perlu disiapkan hanya kontrak input teknis dan
acceptance criteria release. Implementasi generator dan Product Control Center
adalah pekerjaan komersialisasi di `_28`.

Release foundation harus menyediakan:

- Build aplikasi berdasarkan versi.
- Baseline schema.
- Seed referensi wajib.
- Config template.
- Migration bundle.
- Checksum/signature sebagai kontrak artefak.
- SBOM/dependency manifest.
- Bukti backup/restore dan upgrade/rollback.

`_28` kemudian menggunakan kontrak tersebut untuk paket fitur, akun onboarding,
service installer, entitlement, dan Product Control Center. `_30` tidak
membuat atau mengimplementasikan License Hub.

Data yang tidak boleh masuk installer:

- Transaksi Namua.
- Data pegawai/customer/vendor nyata.
- Upload bukti.
- Database dump.
- Log.
- Token.
- Password.
- Device ID.
- Backup.
- Cache.
- PID.

## 10. Roadmap Audit dan Perbaikan Aplikasi

Roadmap ini hanya mengatur pekerjaan teknis sampai aplikasi aman, konsisten,
teruji, dan siap diserahkan ke proses komersialisasi. Paket, harga, lisensi,
FeatureGate, Product Control Center, pilot, dan penjualan tidak diulang di
sini; semuanya berada di roadmap `_28`.

### Fase A0 — Baseline source, runtime, dan data

- `[x]` Hentikan perubahan source dari job backup.
- `[~]` Pisahkan backup, upload, log, cache, credential, dan data customer:
  index/package serta credential DB lulus; storage persisten/off-site customer
  masih perlu provisioning.
- `[~]` Pulihkan/review object Git dengan prosedur non-destructive: connectivity
  lulus; shallow history, baseline commit, dan temp pack masih terbuka.
- Tetapkan dependency/runtime support dan baseline health.
- Pastikan tidak ada fitur besar baru sebelum bug P0/P1 tertutup.

**Gerbang:** source dapat diaudit dan dibangun tanpa mengambil data runtime.

### Fase A1 — Security, RBAC, dan scope

- Tutup guard Master, Master Relation, POS Mobile, export, job, favorite, pin,
  rebuild, dan reclassify.
- Perbaiki role baseline, multi-role, outlet/division scope, dan action policy.
- Lengkapi secure cookie, session, login throttling, MFA/step-up, dan secret
  boundary production.
- Uji direct URL/API/APK dengan role yang berhak dan tidak berhak.

**Gerbang:** aksi tanpa izin selalu ditolak dan tidak mengubah data.

### Fase A2 — Integritas stok, HPP, dan transaksi

- `[x]` Koreksi/preview HPP dan VOID menjaga saldo kuantitas; repair mismatch
  historis tidak diotomatisasi dan diserahkan kepada pemilik data.
- `[x]` Rebuild cache HPP live berjalan setelah perubahan material, item, dan
  component serta setelah reversal POS.
- `[x]` Guard periode fail-closed dan barrier transaksi mencakup backdate,
  rollover, close, ledger, koreksi nilai, POS commit, refund, dan void.
- `[x]` Matrix A2 menjalankan 12 smoke terisolasi untuk invariant, writer,
  recon, produksi/formula, purchase/opening, dan lifecycle POS.
- `[x]` Status terminal POS historis diaudit tanpa replay otomatis.

**Gerbang:** transaksi baru menjaga invariant quantity, value, lot, HPP, dan
audit trail; data historis yang belum terbukti tidak dianggap selesai.

**Status aktif: `CODE_PASS + STAGING_PASS + DEFERRED_OWNER + UAT_PENDING`.**
Probe staging menunjukkan period table InnoDB dengan unique key
domain/bulan, periode aktif OPEN, lot OPEN negatif 0, HPP lot negatif 0,
defisit tersisa 0, dan queue availability gagal 0. Sebanyak 37 relasi order
terminal/nonterminal commit dan 26 snapshot aktif historis sampai 31 Agustus
dicatat tanpa replay. Browser/APK UAT dan keputusan data historis tetap child
terbuka sehingga A2 berstatus `OPERATIONAL_PENDING`, bukan `DONE`.

### Fase A3 — Navigasi dan UI operasional

- Jadikan registry page/menu/route sebagai sumber navigasi tunggal.
- Benahi favorite, alias, duplicate URL, sort, ikon, dan menu teknis.
- Terapkan design system bertahap pada POS, inventory, production, purchase,
  finance, people, asset, master, dan reports.

**Gerbang:** role utama melihat menu yang benar dan tugas harian memiliki pola
UI yang konsisten pada desktop serta mobile.

**Status aktif: `IN_PROGRESS + STAGING_PASS`.** A3.1 registry/navigation dan
fondasi A3.2 sudah lulus, tetapi rollout UI 8.3 serta visual UAT belum selesai.
`sys_menu` menjadi sumber tunggal tree sidebar; favorite dan action
menu memakai permission resolver yang sama; alias page eksplisit tersimpan di
`sys_page_alias`; validator staging menunjukkan missing page/icon, duplicate
URL, sort collision, parent nonaktif, dan alias tidak valid semuanya 0.
Migration registry dan alias berhasil dijalankan dua kali; hash state fungsional
registry tetap identik pada pengulangan. Shell/design-system global tersedia
untuk seluruh rumpun, tetapi belum berarti setiap view lama sudah dimigrasikan.
Enam deklarasi route terminal/outlet yang identik
tidak diubah selama freeze APK; probe memastikan conflicting duplicate 0.
UAT visual browser pada viewport nyata dan UAT APK tetap menjadi validasi
operasional terpisah dan tidak diklaim lulus oleh smoke source.

### Fase A4 — Automated quality gate

- `[x]` **A4.1:** runner deterministik menjalankan test di proses PHP terpisah,
  memiliki timeout dan ringkasan kegagalan terbatas, serta menyediakan profil
  `parallel`, `release`, dan `staging`.
- `[x]` **A4.2:** contract test POS Mobile inti diselaraskan untuk pengembangan
  APK paralel, termasuk sesi/draft/reader terminal cadangan, authorization, dan
  printer. Tidak ada source controller/model/route/view POS yang diubah.
- `[x]` **A4.3:** contract test permission, finance, purchase/SR, inventory,
  production, people/payroll/attendance, asset, Printer Agent, WhatsApp, dan
  migration/restore sudah masuk matriks 35 test dan lulus. Chrome headless nyata
  merender shell desktop/mobile dan HTTP runtime Printer Agent lulus melalui
  virtual environment terisolasi di luar document root.
- `[x]` **A4.4:** preflight release read-only sudah mengintegrasikan PHP lint,
  validasi JavaScript/Python, dependency metadata, secret scan tanpa membocorkan
  nilai, dan package allow/deny policy fail-closed. Contract hardening
  menutup pengurangan scope, wildcard exception, symlink, unreadable, dan file
  oversize. Composer dan dependency Python sudah dikunci secara reproducible;
  vulnerability gate OSV offline memverifikasi tiga lockfile terhadap snapshot
  Packagist/npm/PyPI yang berumur maksimal 48 jam. PHPStan memindai seluruh
  `application/` tanpa menjalankan CodeIgniter/DB dan membekukan baseline lama
  agar error baru memblokir release. Builder menghasilkan tar deterministik di
  luar document root dengan daftar file tunggal, metadata ternormalisasi, dan
  manifest SHA-256; kegagalan gate tidak meninggalkan artefak final.

**Gerbang:** release candidate gagal otomatis bila test kritis, scan, atau
  invariant gagal.

**Status aktif: `TOOLING_PASS`; release customer tetap `BLOCKED(A0)`.**
Implementasi A4.1–A4.4 selesai. Matriks lintas modul tetap lulus 35/35;
profil `release` Batch 146 meluluskan required 48/48, development 4/4,
deployment 1/1, preflight 1/1, dan security 1/1. A4.3
sekaligus menutup CSRF mutasi Purchase/Store Request, atomicity mutasi
rekening serta tutup periode, lifecycle receipt PO, finalisasi status posting
component, render browser desktop/mobile, dan HTTP runtime Printer Agent. Test
migration/restore memakai fixture temporer dan tidak mengubah database.
Preflight A4.4 sudah deterministik. `composer.lock` tervalidasi dan dependency
Printer Agent memiliki direct-input serta transitive lock exact+SHA-256 yang
berhasil dipasang dari virtual environment kosong dengan `--require-hashes`.
Batch 146 memindahkan credential database staging ke file privat di luar source
tanpa mewajibkan environment PHP-FPM. Production tetap memakai resolver.
Deployment-secret contract 39/39 dan preflight workspace lulus dengan
0 finding; nilai rahasia tidak dicetak atau dimasukkan ke paket.
Advisory `mysql2` ditutup dengan lock 3.24.3; temporary `npm ci`, seluruh smoke
WhatsApp, npm audit produksi, dan OSV offline tiga lockfile lulus dengan 144
paket serta 0 advisory. Runtime security menggunakan OSV-Scanner 2.5.1 yang
checksum-pinned dan snapshot database di luar document root. Gate `release`
sebelumnya telah meluluskan runtime dan security; hasil terbaru setelah
pemisahan credential dicatat pada Batch 146.

Static contract, static scan seluruh `application/`, preflight contract, dan
artifact contract lulus. Dua build fixture dengan epoch sama byte-identik;
archive hanya berisi candidate set serta manifest dan seluruh checksum cocok.
Workspace staging kini lolos source preflight tanpa temuan credential. A4 tetap
selesai pada level tooling/contract, sedangkan release customer masih `BLOCKED`
oleh baseline commit/full history, rotasi secret, operasi installer, browser
runtime yang timeout, 80 finding static analysis di source baru, dan UAT role
browser serta APK/device/printer fisik.

### Fase A5 — Schema dan release foundation

- `[~]` Schema version registry, katalog checksum, dan migration runner
  deterministik sudah dimulai. Mode DB-free `validate`/`plan`, executor dengan
  lock/state/timeout, serta contract lulus. Enam migration dikelola; policy
  upgrade menjalankan lima dan mengecualikan seed navigasi clean-install.
  Tujuh SQL lama tetap non-deployable dan disposition finalnya 1 baseline,
  4 enroll berbasis fingerprint exact, 1 replace, serta 1 retire. Replay file
  lama dan adopsi ledger palsu ditolak. Source pre-catalog tetap memerlukan
  bridge manual; updater customer lintas release belum UAT.
- `[~]` Bundle backup kini memiliki builder staging privat, manifest kanonis,
  hash/ukuran/katalog, permission ketat, publish atomik, durability sync, dan
  restore preflight tanpa jalur DB. `[x]` A5.11 sudah menjalankan restore nyata
  ke database/user disposable lokal: registry bootstrap dan replay menjadi
  `COMPATIBLE_V1`, fingerprint schema cocok `4/4`, sumber bundle tidak berubah,
  serta target dan credential sementara terhapus terverifikasi. `[x]` A5.12
  clean install schema/seed/bootstrap owner sudah lulus disposable. `[x]`
  A5.13 health check upgrade lulus pada staging dan rollback schema/ledger/seed
  lulus pada database disposable. `[x]` A5.14 mengunci matrix PHP/FPM,
  MariaDB, Node/npm, Python, Composer, extension, dan tiga dependency lock;
  probe staging lulus dengan dua warning lingkungan yang tercatat. `[x]`
  A5.15 menetapkan retention/lifecycle, checksum backup, dry-run, quarantine,
  audit, dan preflight database read-only. Purge database tetap fail-closed
  sampai archive/agregasi tersedia. `[x]` A5.16 mengikat artefak, manifest,
  source revision, migration catalog, package policy, dan runtime policy dengan
  Ed25519 serta verifier fail-closed sebelum install/update.
- `[x]` Versi PHP/MariaDB/extension/Node/Python dan dependency lock sudah
  mempunyai policy machine-readable serta contract/staging probe.
- `[x]` Builder artefak teknis generik sudah menolak backup, upload, log,
  secret, dan data customer; manifest versi, signature, provenance, dan
  rollback teknis tersedia. Delivery/installer customer tetap pekerjaan C3.

**Gerbang handoff:** aplikasi dapat dipasang dan dipulihkan secara berulang;
setelah gerbang ini lulus, pekerjaan paket/lisensi dilanjutkan di `_28`.

## 11. Urutan Backlog yang Disarankan

### Batch teknis berikutnya

1. Tutup `GAP-01`: credential produksi, rotasi secret, recovery Git, dan
   pemisahan runtime data customer tanpa melonggarkan preflight fail-closed.
2. Uji command inbound `/menu`, `/omzet`, dan `/belanja` dari grup Namua;
   sebelum grup tidak tepercaya dipakai, tambahkan allowlist identitas pengirim.
3. Jalankan A3.2 rollout UI melalui `AUD-A3-UI-01`–`09` per rumpun; jangan
   menutup A3 sebelum visual UAT yang relevan lulus.
4. Pertahankan freeze pada `Pos_mobile.php` dan `routes.php` selama pekerjaan
   APK pemilik; perubahan shared `Pos_model.php` wajib kompatibel ke belakang.
5. Kontrak credential produksi tidak boleh dilonggarkan oleh konfigurasi
   staging yang memakai nilai langsung.
6. Browser/APK UAT A1 dan A2 dijalankan terpisah saat build APK siap.
7. Data mismatch dan anomali POS historis hanya diperbaiki atas keputusan
   pemilik, dengan preview, before/after, dan post-check; jangan replay otomatis.

Paket, lisensi, FeatureGate, Product Control Center, pilot, dan penjualan baru
masuk antrean setelah gerbang Fase A5 lulus dan dikerjakan berdasarkan `_28`.

## 12. Matrix Pengujian Wajib

### RBAC

- Setiap role membuka seluruh menu yang diizinkan.
- Direct URL create/edit/delete/post/void ditolak bila tidak berhak.
- APK memakai policy yang sama.
- Multi-role tidak memperluas scope secara diam-diam.
- Favorite tidak membocorkan menu.
- Permission seed dapat mencabut hak lama.

### POS dan printer

- Draft, confirm, payment, DP, reservation, bundle, extra.
- Void sebelum/sesudah stock commit.
- Refund partial/full.
- Reprint dan tanyakan cetak.
- Printer offline, timeout, duplicate response.
- QR review.
- HPP dan stock commit idempotent.

### Purchase, SR, dan gudang

- PO create/approve/receive/return/cancel.
- SR request/approve/fulfill/partial/reject.
- Lot dan account mutation.
- Reversal dan period close.
- Non-stock line.
- Direct URL role test.

### Inventory dan production

- Material/component receive, transfer, production, sale, void.
- Adjustment plus/minus dan recon.
- Deficit create/settle/write-off.
- Month rollover.
- Qty and value reconciliation.
- Backdate/future/reopen.
- Concurrent writer.

### Finance

- Mutation, payment, reversal, backdate.
- Account balance and chain.
- Daily/gate reconciliation.
- Closed period.
- Export and sensitive field masking.

### Attendance, PH, dan payroll

- Schedule normal/PH/OFF.
- Attendance manual/location/request.
- Eligibility, grant, FIFO use, expiry.
- Monthly day limit and exception approval.
- Payroll input, run, meal payment, disbursement, reversal.
- Employee scope and privacy.

### Asset

- Open data entry.
- Bulk lock.
- Change request.
- Incident, repair, lost, retire.
- Monthly recon.
- Audit history.

### Installer dan update

- Fresh install.
- Seed repeat.
- Upgrade N ke N+1.
- Failed migration rollback.
- Backup and restore.
- License offline/online.
- Feature entitlement.
- Customer data preservation.
- Source package excludes secret, dump, upload, and log.

## 13. Keputusan Arsitektur yang Tidak Boleh Berubah Diam-diam

1. Satu source code untuk seluruh paket.
2. Paket fitur memakai entitlement, bukan source fork manual.
3. Server selalu mengulang pemeriksaan permission.
4. Menu bukan mekanisme keamanan.
5. sys_menu/page registry menjadi sumber navigasi tunggal.
6. Lot/FIFO menjadi sumber kuantitas dan nilai persediaan.
7. Defisit tidak sama dengan mismatch.
8. Repair historis harus preview, preflight, transaksi, audit, dan post-check.
9. Backup tidak boleh berada di repository source.
10. Secret tidak boleh berada di source atau paket customer.
11. Update harus signed, versioned, tested, dan dapat rollback.
12. Branding customer tidak boleh membutuhkan edit source.
13. Data awal installer bukan dump database operasional.
14. Scope multi-role harus eksplisit.
15. Tampilan baru harus memakai design system.

## 14. Gerbang Handoff ke Roadmap Komersialisasi

Audit ini dinyatakan selesai pada level fondasi bila:

- seluruh child wajib pada control board berstatus `DONE`; status
  `CODE_PASS`, `STAGING_PASS`, `DEFERRED_OWNER`, atau `BLOCKED` tidak boleh
  dinaikkan diam-diam menjadi selesai;

- seluruh aksi sensitif yang masuk scope audit memiliki guard server-side;
- role, multi-role, outlet, dan division scope lulus negative test;
- mismatch quantity/value memiliki hasil repair atau keputusan tertulis yang
  dapat diaudit;
- HPP live, lot/FIFO, POS snapshot, dan koreksi/VOID lulus UAT yang relevan;
- navigasi kanonis dan invariant registry tidak lagi memiliki bypass jelas;
- critical path memiliki regression test dan database invariant;
- runtime/dependency, schema, backup/restore, dan package foundation dapat
  diulang tanpa membawa data customer atau secret;
- risiko yang tersisa sudah dicatat sebagai batas produk dan disetujui owner.

Kriteria siap jual, paket, lisensi, entitlement, pilot, dan operasi penjualan
ditetapkan serta dinyatakan lulus hanya di dokumen `_28`.

## 15. Penutup

Dokumen `_30` menjadi daftar masalah aplikasi dan urutan perbaikannya. Setiap
perubahan kode harus mempunyai bukti lint/test/query/UAT dan dicatat pada
execution log atau laporan modul. Setelah fondasi teknis lulus, dokumen `_28`
menjadi pegangan tunggal untuk productization dan komersialisasi.
