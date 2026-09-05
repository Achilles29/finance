# Log Eksekusi Codex Finance

## Batch 1 — Containment authorization endpoint generic

- Waktu: 2026-09-02 06:58 WIB.
- Prioritas: P0 security/RBAC — endpoint generic `Master` dan `Master_relation` dapat menjadi jalur bypass bila hanya mengandalkan menu/sidebar.
- Arah auditor: prioritaskan deny-by-default server-side; batasi batch pada dua controller, gunakan page-code registry kanonis, bedakan action `view/create/edit/delete`, dan pastikan guard terjadi sebelum query atau mutasi.
- Implementasi fixer: menambahkan mapping permission fail-closed 403 dan guard pada seluruh endpoint publik terkait; fallback permission workspace extra dihapus; business logic, route, schema, dan file sensitif tidak diubah.
- File berubah:
  - `application/controllers/Master.php`
  - `application/controllers/Master_relation.php`
- Validasi:
  - `php -l` kedua controller: lulus.
  - `git diff --check`: lulus.
  - Audit cakupan endpoint publik: seluruh endpoint selain constructor memiliki guard; `att_holiday_generate_year()` telah memiliki guard eksplisit.
  - Query read-only `sys_page`: `product.availability` dan `master.product_extra.workspace.index` aktif; tidak ada `product.monitoring.availability.index`.
  - `php tools/tests/inventory_period_guard_smoke.php`: 9/9 lulus.
- Review auditor: PASS. Auditor mengonfirmasi mapping `product.availability` benar, seluruh endpoint tercakup, guard berada sebelum query/mutasi, dan tidak ada patch tambahan.
- Risiko sisa: endpoint/controller lain di luar dua controller ini belum tercakup; izin role/scope fail-open, mobile policy, konfigurasi produksi, dan integritas data masih terbuka. Belum ada probe HTTP dengan akun uji least-privilege.
- Batch berikutnya: audit dan perbaiki bug terisolasi `Role_model` pada penghapusan permission role, dengan transaksi dan verifikasi aman tanpa mengubah data staging.

## Batch 2A — Atomic role deletion

- Waktu: 2026-09-02 07:11 WIB.
- Prioritas: P0 RBAC/integritas — `Role_model::delete()` memakai kolom `id` untuk menghapus permission, tidak atomik, dan tidak memvalidasi postcondition.
- Arah auditor: ganti filter ke `role_id`; gunakan transaksi; tolak role tidak ditemukan, `SUPERADMIN`, dan role yang masih dipakai user; lakukan assertion sebelum dan sesudah commit.
- Implementasi fixer: menambahkan locking `SELECT ... FOR UPDATE`, dependency check berbasis result object, delete terverifikasi, assertion pre-commit, commit, dan postcondition read-only. API boolean serta caller `Roles::delete()` dipertahankan.
- File berubah:
  - `application/models/Role_model.php`
- Validasi:
  - `php -l application/models/Role_model.php`: lulus.
  - `git diff --check`: lulus.
  - `php tools/tests/inventory_period_guard_smoke.php`: 9/9 lulus.
  - Query read-only snapshot role/user/permission: 12 role terbaca tanpa perubahan.
  - Query read-only FK: `auth_user_role.role_id` dan `auth_role_permission.role_id` mereferensikan `auth_role.id`.
  - Konfigurasi staging read-only: dbprefix kosong; `Roles::delete()` satu-satunya caller dan tidak berada dalam outer transaction.
- Diskusi auditor/fixer: auditor pertama menemukan false-success karena `count_all_results()` mengembalikan 0 saat query gagal dan meminta locking/assertion pre-commit. Fixer merevisi badan metode tanpa menyentuh schema/data; auditor final memeriksa API transaksi CI3 dan menyatakan PASS.
- Review auditor: PASS. Implementasi memenuhi P0-05 pada deployment staging yang diverifikasi.
- Risiko sisa: belum dilakukan live delete fixture karena tidak diperlukan dan perubahan data staging sengaja dihindari; invariant FK dan dbprefix kosong harus dijaga pada paket customer. Rebuild role baseline, multi-role scope, dan negative permission test lintas endpoint masih terbuka.
- Batch berikutnya: auditor memilih prioritas P0 RBAC berikutnya, dengan kandidat utama fail-closed scope pada `Auth_model`/`MY_Controller` atau policy aksi `Pos_mobile`.

## Batch 3 — Fail-closed Purchase rebuild/reclassify

- Waktu: 2026-09-02 07:18 WIB.
- Prioritas: P0/P1 RBAC containment — utilitas `rebuild` dan `reclassify` masih menerima fallback izin `purchase.order.index`, sehingga writer Purchase umum dapat menjalankan tool yang memproses ulang dampak receipt/payment atau snapshot inventory.
- Diskusi auditor/fixer: auditor memilih batch kecil tanpa perubahan schema/data; fixer menghapus seluruh fallback dan menerapkan page code khusus dengan action `view` untuk halaman dan `edit` untuk endpoint run. Pemanggilan profil `finance_fixer` pada CLI tidak didukung akun, sehingga instruksi yang sama dijalankan oleh sesi fixer fallback; hasil patch tetap dibatasi pada file target.
- File berubah:
  - `application/controllers/Purchase.php`
- Perubahan utama:
  - `rebuild_impact_index()` hanya memakai `purchase.rebuild.impact.index:view`.
  - `rebuild_impact_run()` hanya memakai `purchase.rebuild.impact.index:edit`.
  - `reclassify_profile_domain_index()` hanya memakai `purchase.reclassify.profile.domain.index:view`.
  - `reclassify_profile_domain_run()` hanya memakai `purchase.reclassify.profile.domain.index:edit`.
  - Logika bisnis, route, schema, role matrix, dan data staging tidak diubah.
- Validasi:
  - `php -l application/controllers/Purchase.php`: lulus.
  - `git diff --check -- application/controllers/Purchase.php`: lulus.
  - `php tools/tests/inventory_period_guard_smoke.php`: 9/9 lulus.
  - Static scan memastikan empat method tidak lagi memiliki fallback `PAGE_ORDER` atau page tool lain.
  - Query SELECT-only registry: kedua page code aktif; permission eksplisit terbaca untuk role terkait, termasuk akses view/edit sesuai output preflight.
- Review auditor: PASS. Guard berada sebelum payload/query/mutasi, diff scope tepat, dan tidak ada blocker acceptance.
- Risiko sisa: role matrix masih memberikan akses edit rebuild cukup luas; ini perlu review kebijakan akses, bukan cacat guard batch ini. Negative HTTP test dengan akun least-privilege belum dijalankan.
- Batch berikutnya: auditor mengarahkan containment P0 `Pos_mobile` untuk permission per aksi; setelah itu review fail-closed division/outlet scope pada `Auth_model`/`MY_Controller`.

## Batch 4A — POS mobile core-writer RBAC dan token fail-closed

- Waktu: 2026-09-02 07:55 WIB.
- Prioritas: P0 security/RBAC — endpoint writer POS mobile hanya memvalidasi token/session, tetapi belum memeriksa permission per aksi; token valid yang izinnya dicabut masih berisiko menulis. Review lanjutan juga menemukan token bearer invalid dapat jatuh ke jalur API-key/session.
- Diskusi auditor/fixer: auditor menetapkan batch terbatas pada writer inti POS mobile dan `printer_test`, dengan selector page/action yang sama seperti web. Actor harus berasal dari token tervalidasi atau session, permission diambil melalui `Auth_model`, dan penolakan harus terjadi sebelum writer, sinkronisasi, atau printer work. Fixer menambahkan helper permission fail-closed, cache per request, guard per aksi, lalu memperbaiki jalur bearer invalid agar tidak fallback. Profil `finance_fixer` tidak dapat dipanggil langsung oleh akun CLI, sehingga instruksi yang sama dijalankan melalui sesi fixer fallback.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
- Perubahan utama:
  - Guard `edit` pada save/confirm/payment/void/refund/cashier open/close dan `printer_test`.
  - Selector `create` versus `edit`, fallback refund paid → cashier → draft, dan printer registry → legacy diuji terhadap helper controller.
  - Permission memuat user ID dari token/session tervalidasi, mendukung `__superadmin__`, mengembalikan JSON 403 dengan `page_code`/`action`, dan tidak memakai user ID dari payload.
  - Bearer atau `X-Pos-Mobile-Token` yang disuplai tetapi invalid sekarang langsung 401; tidak dapat fallback ke session/API-key.
  - Harness fake menguji token valid/invalid, API-key tanpa user, invalid API-key/bearer, urutan guard sebelum protected work, selector nyata, dan tidak adanya writer/sync/printer call saat ditolak.
- Validasi:
  - `php -l application/controllers/Pos_mobile.php`: lulus.
  - `php -l tools/tests/pos_mobile_authorization_smoke.php`: lulus.
  - `git diff --check`: lulus.
  - `php tools/tests/pos_mobile_authorization_smoke.php`: seluruh check lulus.
  - `php tools/tests/inventory_period_guard_smoke.php`: seluruh check lulus.
  - Tidak ada perubahan route, schema, maupun data staging pada batch ini.
- Review auditor: review pertama CHANGES karena fallback token invalid dan coverage harness belum membuktikan selector/credential edge case. Setelah fixer menutup fallback dan menambah test controller nyata, review final: PASS.
- Risiko sisa: endpoint read/print-target mobile belum mendapat permission per aksi; binding outlet/terminal/device, token refresh/rotation, dan step-up approval masih ditunda sesuai kontrak saat ini. Belum ada negative HTTP test dengan akun least-privilege terhadap server staging. Test harness baru masih untracked dan perlu ikut dipaketkan saat perubahan ini dipromosikan.
- Batch berikutnya: auditor menilai prioritas P0 berikutnya pada fail-closed division/outlet scope di `Auth_model`/`MY_Controller`, dengan bukti query dan negative test sebelum implementasi.

## Batch 5 — P0-07A isolasi backup dari repository

- Waktu: 2026-09-02 08:14 WIB.
- Prioritas: P0 operasional/security — runner backup aktif melakukan stage/commit/fetch/merge/push ke `origin/main`; histori menunjukkan commit `backup:` berkala dan repository memuat artefak dump/log. Ini berisiko membocorkan data serta mengubah source tree otomatis.
- Diskusi auditor/fixer: auditor memilih batch kecil untuk menghentikan seluruh operasi Git pada runner, mempertahankan dump lokal, retensi, dan logging. Fixer menghapus konfigurasi repository dari runner, env example/actual, controller generator, dan UI; menambahkan aturan ignore untuk dump/log baru tanpa menghapus artefak lama. Review pertama menemukan dua key stale pada `scripts/backup/.env`; fixer menghapus hanya key tersebut dan menambah coverage smoke test.
- File berubah:
  - `.gitignore`
  - `scripts/backup/.env`
  - `scripts/backup/.env.example`
  - `scripts/backup/backup_full.sh`
  - `scripts/backup/backup_full.bat`
  - `application/controllers/System_tools.php`
  - `application/views/system/dbtools.php`
  - `application/views/system/settings.php`
  - `application/views/system/backup_guide.php`
  - `tools/tests/backup_source_isolation_smoke.php`
- Perubahan utama:
  - Runner Linux/Windows hanya membuat dump dan log lokal, menjalankan retensi, lalu memberi pesan bahwa off-site terenkripsi harus dikonfigurasi terpisah.
  - Seluruh executable Git dan field repository dihapus dari generator `.env`, UI, dan contoh konfigurasi.
  - Dump/log baru di-ignore; `.gitkeep` tetap dipertahankan. Dump, log, backup, dan runtime existing tidak dihapus.
  - Smoke test membaca `.env` aktual tanpa mencetak nilai dan memastikan key repository stale tidak muncul.
- Validasi:
  - `bash -n scripts/backup/backup_full.sh`: lulus.
  - `php -l` `System_tools.php`, tiga view backup, dan smoke test: lulus.
  - `git diff --check`: lulus.
  - `php tools/tests/backup_source_isolation_smoke.php`: 32/32 lulus.
  - `php tools/tests/inventory_period_guard_smoke.php`: 9/9 lulus.
  - Runner backup tidak dijalankan; tidak ada query/mutasi database.
- Review auditor: PASS. Auditor mengonfirmasi tidak ada executable Git atau repository setting tersisa pada runner/env/UI, jalur simpan tetap ada, retensi/logging dipertahankan, dan artefak existing tidak dihapus.
- Risiko sisa: backup masih lokal dan belum terenkripsi/off-site; belum ada restore drill, monitoring, atau uji Windows VM. Artefak lama yang sudah tracked tetap ada dan perlu kebijakan cleanup/migrasi terpisah. `finance_fixer` tidak tersedia sebagai profil CLI akun ini, sehingga implementasi dijalankan oleh sesi fallback dengan instruksi yang sama.
- Batch berikutnya: auditor memilih prioritas P0 berikutnya, terutama fail-closed division/outlet scope pada `Auth_model`/`MY_Controller` atau hardening konfigurasi produksi, dengan batch kecil dan negative test yang terukur.

## Batch 6 — P0-04A fail-closed division scope

- Waktu: 2026-09-02 08:54 WIB.
- Prioritas: P0 security/RBAC — scope divisi lama mengubah role tanpa scope atau multi-scope menjadi `NULL`, lalu `active_division_id()` menafsirkan `NULL` sebagai akses unrestricted.
- Diskusi auditor/fixer: auditor menetapkan policy ketat: non-superadmin hanya boleh lewat bila role aktif menghasilkan tepat satu division ID positif; duplikat divisi yang sama tetap `SINGLE`, sedangkan `NONE`, `AMBIGUOUS`, null/0/negatif/mixed ditolak. SUPERADMIN menjadi satu-satunya jalur unrestricted. Review pertama menemukan stale detector masih memfilter `r.is_active = 1`, sehingga deaktivasi role SUPERADMIN tidak terdeteksi. Fixer menghapus filter itu hanya dari stale-signal query dan menambah coverage role deactivation serta login rejection.
- File berubah:
  - `application/models/Auth_model.php`
  - `application/controllers/Auth.php`
  - `application/core/MY_Controller.php`
  - `tools/tests/auth_division_scope_smoke.php`
- Perubahan utama:
  - Menambahkan resolver state eksplisit `SINGLE`, `NONE`, dan `AMBIGUOUS`; hanya `SINGLE` dengan ID positif yang disimpan sebagai scope efektif.
  - Login menolak non-superadmin tanpa scope tunggal valid sebelum membuat session authenticated atau session log login.
  - `MY_Controller` memvalidasi scope di request entry sebelum constructor controller turunan bekerja; sesi legacy direfresh sekali dan hasil unresolved tetap 403, baik HTML maupun AJAX JSON.
  - Refresh permission memperbarui kembali `auth_user`, scope, dan status SUPERADMIN; stale detector memeriksa perubahan role termasuk role yang baru dinonaktifkan.
  - `active_division_id()` tidak lagi mengembalikan `NULL` untuk non-superadmin unresolved; ia fail-closed dengan exception di luar gate normal.
  - Smoke DB-free mencakup resolver invalid/multi-scope, role deactivation cached SUPERADMIN, child-work zero sebelum 403, ID 0/negatif, dan login rejection tanpa auth session/log.
- Validasi:
  - `php -l application/models/Auth_model.php`: lulus.
  - `php -l application/controllers/Auth.php`: lulus.
  - `php -l application/core/MY_Controller.php`: lulus.
  - `php -l tools/tests/auth_division_scope_smoke.php`: lulus.
  - `git diff --check`: lulus.
  - `php tools/tests/auth_division_scope_smoke.php`: 39/39 lulus.
  - Regression smoke backup: 32/32 lulus; POS mobile lulus; inventory period guard lulus.
  - Verifikasi CI 3.1.13 mendukung query-builder `group_start()`; tidak ada perubahan schema/data dan tidak ada koneksi DB yang diperlukan.
- Review auditor: review pertama CHANGES karena role yang dinonaktifkan tidak masuk stale detector dan test belum membuktikan jalur itu. Setelah fixer memperbaiki query dan test, review final PASS.
- Risiko sisa: revocation masih mengikuti throttle staleness 120 detik dan timestamp beresolusi detik; pencabutan segera memerlukan invalidasi session eksplisit. `_get_role_permissions()` masih perlu ditinjau untuk memfilter role nonaktif saat refresh agar permission lama tidak diwarisi. Smoke harness baru masih untracked dan wajib ikut dipaketkan saat promosi. Pengguna existing dengan scope `NONE`/`AMBIGUOUS` akan ditolak sampai role diperbaiki.
- Batch berikutnya: auditor memilih hardening P0 berikutnya, dengan kandidat utama filter role nonaktif pada permission refresh atau P0-06A konfigurasi produksi (secret, CSRF, cookie, session, CORS) dalam batch kecil dengan test regresi.

## Batch 7 — P0-04B filter permission role nonaktif

- Waktu: 2026-09-02 09:08 WIB.
- Prioritas: P0 security/RBAC — `_get_role_permissions()` masih menggabungkan permission dari `auth_user_role` dan `auth_role_permission` tanpa memastikan role masih aktif. Role yang dinonaktifkan dapat tetap memberi akses sampai cache/session diperbarui.
- Diskusi auditor/fixer: auditor memilih hardening terpusat ini sebelum konfigurasi produksi P0-06A karena satu query memengaruhi login, refresh permission, dan jalur POS; batch dapat diuji DB-free tanpa mutasi staging. Fixer menambahkan join role dan filter aktif, mempertahankan union antar-role aktif, filter page aktif, serta urutan override GRANT lalu REVOKE.
- File berubah:
  - `application/models/Auth_model.php`
  - `tools/tests/auth_inactive_role_permission_smoke.php`
- Perubahan utama:
  - Query permission kini join `auth_role r` pada `r.id = ur.role_id` dan hanya mengambil `r.is_active = 1`.
  - Smoke test memakai `Auth_model` nyata dan fake query builder in-memory; mencakup role hanya nonaktif, kombinasi aktif/nonaktif, union dua role aktif, page aktif, override GRANT/REVOKE, SUPERADMIN nonaktif, dan refresh session.
- Validasi:
  - `php -l application/models/Auth_model.php`: lulus.
  - `php -l tools/tests/auth_inactive_role_permission_smoke.php`: lulus.
  - `git diff --check`: lulus.
  - `php tools/tests/auth_inactive_role_permission_smoke.php`: 19/19 assertion lulus.
  - Regression smoke `auth_division_scope_smoke.php`: 39/39 lulus; POS mobile, inventory period guard, dan backup source isolation lulus.
  - Tidak ada koneksi atau mutasi database staging.
- Review auditor: review pertama menandai CHANGES hanya karena worktree mengakumulasi perubahan Batch 1–6; setelah baseline batch dijelaskan, review final PASS. Auditor mengonfirmasi delta Batch 7 tidak memiliki defect fungsional.
- Risiko sisa: permission session web yang sudah aktif tetap mengikuti refresh/re-login atau stale throttle hingga 120 detik; smoke masih DB-free sehingga konsistensi relasi/status pada database nyata perlu diverifikasi saat koneksi staging tersedia. Harness baru masih untracked dan harus ikut dipaketkan saat promosi.
- Batch berikutnya: auditor memilih sub-batch P0-06A hardening konfigurasi produksi yang paling kecil dan aman, dimulai dari kontrak/validasi konfigurasi secret, session, cookie, CSRF, dan CORS tanpa mengubah nilai staging secara membabi buta.

## Batch 8 — P0-06A-1 boundary secret dan production preflight

- Waktu: 2026-09-02 09:30 WIB.
- Prioritas: P0 security/deployment — encryption key dan credential database masih berada di konfigurasi aplikasi; aplikasi membutuhkan boundary secret yang aman dan fail-closed sebelum bootstrap production.
- Diskusi auditor/fixer: auditor memilih sub-batch paling kecil: resolver environment-only, preflight production sebelum bootstrap CodeIgniter, wiring config/database, dokumentasi kontrak provisioning, dan smoke DB-free. CSRF, cookie, session, CORS, service config, dan License Hub sengaja tidak disentuh pada batch ini. Profil `finance_fixer` tidak dapat dipanggil langsung oleh akun CLI, sehingga implementasi dijalankan oleh sesi fallback dengan instruksi yang sama.
- File berubah:
  - `application/libraries/DeploymentConfig.php`
  - `application/config/config.php`
  - `application/config/database.php`
  - `index.php`
  - `docs/deployment_secret_contract.md`
  - `tools/tests/deployment_secret_config_smoke.php`
- Perubahan utama:
  - Resolver membaca lima nama konfigurasi dari process environment tanpa logging, output, atau fallback secret.
  - Production memvalidasi seluruh kontrak sebelum bootstrap CI; konfigurasi tidak lengkap menghasilkan HTTP 503 generik tanpa membocorkan nama atau nilai secret.
  - Encryption key dan empat parameter database memakai resolver; driver `mysqli` serta opsi koneksi non-kredensial dipertahankan.
  - Kontrak provisioning, cutover, perlindungan kompatibilitas session terenkripsi, dan aturan redaksi dicatat tanpa menyimpan nilai secret.
  - Smoke test memakai fixture environment sintetis, menguji wiring config/database dan child process production dengan environment kosong.
- Validasi:
  - `php -l` lima file PHP target: lulus.
  - `git diff --check`: lulus.
  - `php tools/tests/deployment_secret_config_smoke.php`: 39/39 lulus.
  - Regression smoke `auth_division_scope_smoke.php`: 39/39 lulus; inactive-role, POS mobile, inventory period guard, dan backup source isolation juga lulus.
  - Tidak ada koneksi/mutasi database, runner backup, atau penghapusan file runtime.
- Review auditor: PASS. Preflight berada sebelum bootstrap, resolver dan wiring benar, tidak ada defect fungsional pada delta batch, dan opsi koneksi non-kredensial identik dengan baseline VCS.
- Risiko sisa: process PHP saat ini belum memiliki variabel `FINANCE_*`; staging/production akan tetap 503 sampai variabel diprovision ke PHP-FPM dan CLI lalu worker di-reload. Belum ada runtime health test dengan environment lengkap, restore/session compatibility drill, atau rotasi secret. Temuan CORS wildcard, CSRF disabled, cookie flags, dan session lifetime masih terbuka untuk batch berikutnya.
- Batch berikutnya: minta auditor memilih sub-batch P0 konfigurasi/security berikutnya dengan acceptance test terukur; provisioning secret production dilakukan sebagai langkah operasional terpisah sebelum cutover.

## Batch 9 — P0-09A trust boundary Printer Agent lokal

- Waktu: 2026-09-02 10:18 WIB.
- Prioritas: P0 security — local Printer Agent menerima request lintas-origin secara terlalu longgar dan bootstrap belum mempunyai kontrak origin/key yang fail-closed.
- Diskusi auditor/fixer: auditor meminta allowlist origin exact, key header-only, bootstrap guard sebelum model/query, bind loopback, serta penghapusan jalur bootstrap legacy. Fixer menerapkan perubahan kecil pada agent, controller, helper diagnostik, dokumentasi, contoh konfigurasi, dan smoke test.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/controllers/Pos_printer_agent.php`
  - `tools/pos_printer_agent/agent.py`
  - `tools/pos_printer_agent/check_saved_printers.py`
  - `tools/pos_printer_agent/config.example.json`
  - `tools/pos_printer_agent/README.md`
  - `tools/pos_printer_agent/requirements.txt`
  - `application/views/pos/printer_guide.php`
  - `application/views/pos/printer_guide_config.php`
  - `tools/tests/printer_agent_trust_smoke.py`
- Perubahan utama:
  - CORS global dihapus; `/cetak` hanya menerima origin exact dari `api.base_url`/allowlist dan service bind ke `127.0.0.1`.
  - Bootstrap hanya menerima `X-Printer-Key`; environment kosong menghasilkan 503, key salah 403, dan guard dilakukan sebelum model/query.
  - `Pos::printer_bootstrap()` publik dihapus; kedua route resmi tetap menuju `pos_printer_agent/bootstrap`.
  - Key tidak lagi dikirim melalui query string; dependency CORS yang tidak dipakai dihapus.
- Validasi:
  - PHP lint target: lulus.
  - Python source compile tanpa bytecode dan static/source smoke: lulus.
  - Regression POS mobile, auth scope, inactive-role, inventory, backup, deployment: lulus.
  - `git diff --check`: lulus; `config.json`, `agent.log`, dan `routes.php` tidak berubah.
  - HTTP smoke penuh belum berjalan karena Flask tidak terpasang di environment ini.
- Review auditor: review pertama menemukan legacy `Pos::printer_bootstrap`; fixer menghapus method tersebut. Review final: PASS untuk delta batch.
- Risiko sisa: trust contract produksi lengkap masih membutuhkan signed/nonce/timestamp atau pairing per-device, rotasi token, installer/service manager, health/version contract, dan uji fisik printer. Field `key_query_param` pada config runtime lama masih ada tetapi tidak dibaca dan bukan bypass.
- Batch berikutnya: auditor memilih hardening provisioning secret sebelum melanjutkan ke kontrak HTTP/pairing yang lebih besar.

## Batch 10 — P0-09A gate provisioning config dan bundle

- Waktu: 2026-09-02 10:18 WIB.
- Prioritas: P0 security — `config_json` membawa `POS_PRINTER_BOOTSTRAP_KEY`, tetapi sebelumnya dapat diunduh dengan permission halaman printer umum yang juga dimiliki kasir/barista.
- Diskusi auditor/fixer: auditor menetapkan gate exact `pos.printer.connection:edit`, tanpa fallback ke `pos.printer.index`, dan meminta link UI ikut disembunyikan. Fixer menerapkan gate sebelum generator JSON/ZIP serta memperbarui smoke test dan kedua guide.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/printer_guide.php`
  - `application/views/pos/printer_guide_config.php`
  - `tools/tests/printer_agent_trust_smoke.py`
- Perubahan utama:
  - `config_json` dan `agent_bundle` memerlukan `pos.printer.connection:edit` secara langsung sebelum file dibuat.
  - Link provisioning pada dua guide hanya ditampilkan jika boolean permission exact bernilai true; backend tetap menjadi pengaman utama.
  - Seed permission dan route tidak diubah; bundle tetap mengecualikan `config.json`.
- Validasi:
  - PHP lint tiga file target: lulus.
  - Python source compile tanpa bytecode dan static/source smoke: lulus.
  - POS mobile, auth scope 39, inactive-role, inventory 9, backup 32, deployment 39: lulus.
  - `git diff --check`: lulus; file runtime secret/log dan routes tidak berubah.
  - HTTP smoke penuh tetap tertunda karena Flask tidak tersedia.
- Review auditor: PASS. Tidak ada fallback permission untuk secret-bearing download, gate mendahului generator/archive, tidak ada legacy bypass, dan test mencakup permission/route/view/seed policy.
- Risiko sisa: permission seed `pos.printer.connection` harus tersedia pada instalasi customer; jika belum, akses non-SUPERADMIN fail-closed. Shared bootstrap key belum per-agent/rotatable dan HTTP `/cetak` masih mengandalkan origin boundary lokal; ini memerlukan batch pairing terpisah. Runtime `FINANCE_*` juga belum diprovision ke PHP-FPM/CLI staging.
- Batch berikutnya: minta auditor memilih prioritas P0 berikutnya; kandidat terdekat adalah CSRF/CORS/session hardening atau dependency/release preflight, tetap tanpa License Hub.

## Batch 11 — P0-03B RBAC endpoint baca POS Mobile

- Waktu: 2026-09-02 10:36 WIB.
- Prioritas: P0 security/RBAC — endpoint baca POS Mobile sebelumnya sudah mengautentikasi token, tetapi belum konsisten memeriksa permission `view` sebelum mengambil katalog, member, order, status sesi, preview, voucher, target cetak, dan data printer.
- Diskusi auditor/fixer: auditor memilih hardening endpoint baca sebagai prioritas tertinggi setelah writer POS Mobile terlindungi. Fixer menambahkan autentikasi user/employee dan guard `view` sebelum kerja bisnis pada semua endpoint baca/print-target; `printers` memakai selector registry/fallback exact. Bootstrap API-key-only tanpa user ditolak. Ping, login, dan logout tetap public; writer dan fallback workspace tidak diubah.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
- Perubahan utama:
  - `bootstrap`, katalog, pencarian member/extra, orders, load/preview/reversal, target cetak, payment prepare/voucher, status sesi, dan cashier close preview kini memanggil `authorize_mobile(true)` lalu permission `view` sebelum query/model/printer work.
  - `printers` memeriksa `pos.printer.connection:view` bila registry tersedia, atau `pos.printer.index:view` bila tidak tersedia; tidak fallback ketika registry ada.
  - API key tanpa user/employee tidak dapat membuka bootstrap; selector cashier → draft dan preferensi paid untuk refund tetap dipertahankan.
  - Smoke test mencakup 403 deny-before-work, page/action, API-key/bearer regression, superadmin, selector printer, fallback workspace, serta writer regression.
- Validasi:
  - `php -l application/controllers/Pos_mobile.php`: lulus.
  - `php -l tools/tests/pos_mobile_authorization_smoke.php`: lulus.
  - `php tools/tests/pos_mobile_authorization_smoke.php`: lulus.
  - Regression `auth_division_scope_smoke.php`, `auth_inactive_role_permission_smoke.php`, `inventory_period_guard_smoke.php`, `backup_source_isolation_smoke.php`, dan `deployment_secret_config_smoke.php`: seluruhnya lulus.
  - `git diff --check`: lulus.
  - Tidak ada perubahan database, route, seed, schema, data, atau runtime artifact.
- Review auditor: PASS. Semua endpoint baca/print-target menjalankan autentikasi lalu permission sebelum kerja terlindungi; bootstrap API-key-only ditolak; selector printer dan fallback workspace sesuai; smoke dan regression lulus.
- Risiko sisa: client lama yang hanya memakai API key untuk bootstrap kini ditolak dan perlu login user/employee; scope record-level outlet/order, binding perangkat, rotasi token, serta uji least-privilege HTTP staging belum dilakukan. HTTP smoke Printer Agent tetap tertunda karena Flask tidak tersedia.
- Batch berikutnya: auditor memilih prioritas P0 berikutnya dengan batch kecil dan acceptance test terukur; kandidat tetap CSRF/CORS/session hardening atau dependency/release preflight, tanpa License Hub.

## Batch 12 — P0-06A-2 boundary browser global

- Waktu: 2026-09-02 10:52 WIB.
- Prioritas: P0 security/configuration — aplikasi masih mengirim CORS wildcard global dan cookie aplikasi belum `HttpOnly`/`Secure` sesuai environment.
- Diskusi auditor/fixer: auditor menilai CSRF global belum aman diaktifkan karena terdapat banyak form POST dan AJAX yang belum seragam membawa token. Fixer memilih hardening browser yang terpusat: menghapus CORS wildcard global, mengaktifkan `HttpOnly`, dan mengaktifkan `Secure` hanya pada production; Printer Agent tetap memakai boundary CORS exact miliknya.
- File berubah:
  - `application/config/config.php`
  - `tools/tests/web_runtime_boundary_smoke.php`
  - `docs/deployment_secret_contract.md`
- Perubahan utama:
  - Tiga header `Access-Control-Allow-*` global dihapus dari config aplikasi.
  - `cookie_secure` menjadi `(ENVIRONMENT === 'production')`, `cookie_httponly` menjadi `TRUE`, dan `cookie_samesite` tetap `Lax`.
  - Smoke DB-free baru memeriksa source aplikasi, mengecualikan controller Printer Agent, dan memuat config dalam child PHP untuk development/production tanpa bootstrap/DB/runtime write.
  - Kontrak deployment menjelaskan HTTPS sampai browser, TLS terminator tepercaya, `X-Forwarded-Proto: https`, dan verifikasi sebelum cutover.
- Validasi:
  - `php -l application/config/config.php`: lulus.
  - `php -l tools/tests/web_runtime_boundary_smoke.php`: lulus.
  - `php tools/tests/web_runtime_boundary_smoke.php`: 14/14 lulus.
  - Regression `deployment_secret_config_smoke.php`: 39/39; `pos_mobile_authorization_smoke.php`, auth scope, inactive-role, inventory, dan backup: lulus.
  - `git diff --check`: lulus.
  - Printer Agent static/source smoke: lulus; HTTP smoke belum berjalan karena Flask tidak tersedia.
  - Tidak ada perubahan database/schema/route, session lifetime/regenerasi, CSRF, Printer Agent, atau runtime artifact.
- Review auditor: PASS. Header global hilang, cookie production-only/HttpOnly/SameSite sesuai, smoke aman dan tidak membocorkan secret, serta delta tidak mengubah area di luar scope.
- Risiko sisa: client browser lintas-origin yang mengandalkan wildcard CORS akan gagal; production harus benar-benar HTTPS dengan proxy tepercaya. CSRF global, session satu tahun/regenerasi dua jam, login throttling, dependency/release preflight, migration/updater, dan endpoint HTTP Printer Agent tetap terbuka/tertunda sesuai scope.
- Batch berikutnya: auditor memilih sub-batch P0 berikutnya dengan acceptance terukur; kandidat utama CSRF inventory/contract bertahap atau dependency/runtime/release preflight, tanpa License Hub.

## Batch 13 — P0-06A-3 scoped CSRF Inventory Control

- Waktu: 2026-09-02 11:41 WIB.
- Prioritas: P0 security — CSRF global masih disabled, sementara lima writer Inventory Control memiliki dampak tinggi: write-off defisit, koreksi nilai stok, buka periode, posting cut-off, dan buka kembali periode.
- Diskusi auditor/fixer: auditor memilih perlindungan scoped agar tidak mengaktifkan CSRF global secara prematur pada seluruh form/AJAX aplikasi. Fixer menambahkan token session-bound khusus Inventory Control, guard setelah RBAC/superadmin dan sebelum payload/query/service/transaksi/writer, lalu memperkuat smoke test berdasarkan review auditor berulang sampai behavioral harness memenuhi acceptance.
- File berubah:
  - `application/controllers/Inventory_control.php`
  - `application/views/inventory/stock_deficit_detail.php`
  - `application/views/inventory/stock_period_detail.php`
  - `application/views/inventory/stock_period_index.php`
  - `application/views/inventory/stock_value_reconciliation_index.php`
  - `tools/tests/inventory_control_mutation_csrf_smoke.php`
- Perubahan utama:
  - Lima endpoint writer menolak non-POST dengan 405 dan token hilang/kosong/malformed/session mismatch/mismatch dengan 403 sebelum kerja bisnis.
  - Token memakai `random_bytes(32)`, hanya reuse bila tepat 64 karakter hex, disimpan pada session, dibandingkan dengan `hash_equals`, dan tidak dicatat ke log.
  - Empat view menerima token scoped eksplisit; semua form POST memakai hidden token non-empty, form GET tetap tanpa token; placeholder CSRF global di area ini dihapus.
  - Smoke test DB-free memuat controller dengan stub/reflection dan dependency tripwire, menguji lima writer pada seluruh kasus invalid termasuk key session yang benar-benar absent, valid private guard, token regeneration, serta render/DOM form empat view.
- Validasi:
  - `php -l` controller, view yang berubah, dan smoke test: lulus.
  - `php tools/tests/inventory_control_mutation_csrf_smoke.php`: 272/272 lulus.
  - `php tools/tests/inventory_period_guard_smoke.php`: 9/9 lulus.
  - Regression web boundary 14/14, deployment secret 39/39, POS mobile, auth division, inactive-role, backup isolation: lulus.
  - `git diff --check`: lulus; tidak ada perubahan route, model, schema, data, atau runtime artifact pada batch ini.
- Review auditor: PASS setelah dua putaran perbaikan harness. Auditor mengonfirmasi urutan guard, strict token invariant, public-action behavioral cases, session absence, dependency tripwire, dan DOM form checks.
- Risiko sisa: test masih DB-free/stubbed sehingga E2E browser dengan cookie/session backend nyata dan jalur AJAX perlu dijalankan kemudian. CSRF global, session lifetime satu tahun/regenerasi dua jam, serta writer web/AJAX lain di luar lima endpoint ini belum tercakup. Uji HTTP Printer Agent tetap tertunda karena Flask tidak tersedia di environment.
- Batch berikutnya: minta auditor memilih prioritas P0 berikutnya; kandidat terdekat adalah audit coverage CSRF writer lain atau dependency/runtime/release preflight, tetap tanpa License Hub.

## Batch 14 — P0-06A-4 scoped CSRF repair Audit Commit Stok POS

- Waktu: 2026-09-02 12:09 WIB.
- Prioritas: P0 security/integritas inventory — empat endpoint AJAX repair Audit Commit Stok POS dapat menjalankan rekonsiliasi atau repair drift stok/HPP, tetapi sebelumnya hanya memiliki RBAC tanpa POST-only dan CSRF sementara CSRF global masih disabled.
- Diskusi auditor/fixer: auditor memilih batch kecil yang terisolasi pada satu controller dan satu view, karena repair material/component langsung menyentuh integritas stok/HPP. Fixer menambahkan token session-bound scoped, guard setelah RBAC dan sebelum body/payload/query/model, serta wrapper AJAX yang hanya dipakai empat repair tersebut.
- Review auditor putaran pertama: CHANGES. Auditor menemukan risiko casing header pada CGI/FastCGI dan false assurance pada stub smoke test yang melakukan exact lookup. Fixer menyesuaikan lookup ke canonical CodeIgniter `X-Pos-Stock-Commit-Csrf`, mempertahankan kontrak browser `X-Pos-Stock-Commit-CSRF`, lalu memperbarui stub agar memodelkan normalisasi header dan menambah jalur valid untuk keempat writer.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/stock_commit_audit_index.php`
  - `tools/tests/pos_stock_commit_repair_csrf_smoke.php`
- Perubahan utama:
  - Empat endpoint `stock_commit_audit_repair_*` menolak non-POST dengan 405 JSON dan header scoped hilang, kosong, malformed, session absent, cross-session, atau mismatch dengan 403 JSON.
  - Token dibuat dengan `random_bytes(32)`, disimpan pada session, hanya reuse jika tepat 64 karakter hex, dan diverifikasi memakai `hash_equals`.
  - Header dibaca memakai casing canonical CodeIgniter agar tetap cocok setelah normalisasi CGI/FastCGI; tidak ada fallback token dari query atau body.
  - Halaman Audit Commit Stok POS merender token setelah permission; shared `postJson` tetap tidak membawa header secara default, sedangkan wrapper scoped hanya dipakai empat panggilan repair. Repair lot material lain tetap memakai helper bersama.
  - Tidak ada perubahan model, route, schema, role matrix, fallback permission, data, atau konfigurasi CSRF global.
- Validasi:
  - `php -l` controller, view, dan smoke test: lulus.
  - `php tools/tests/pos_stock_commit_repair_csrf_smoke.php`: 317/317 lulus, termasuk normalisasi CGI/FastCGI, valid request keempat writer, invalid request sebelum body/dependency, token generation/reuse, dan render/header scope.
  - `php tools/tests/inventory_control_mutation_csrf_smoke.php`: 272/272 lulus.
  - `php tools/tests/inventory_period_guard_smoke.php`: seluruh skenario lulus.
  - `php tools/tests/web_runtime_boundary_smoke.php`: 14/14 lulus.
  - `git diff --check`: lulus.
  - Tidak menjalankan query database, backup runner, atau mutasi data.
- Review auditor final: PASS. Canonical header sesuai `system/core/Input.php`; guard tepat setelah RBAC dan sebelum payload/model pada empat writer; wrapper scoped tepat; tidak ada perubahan di luar scope batch.
- Risiko sisa: smoke masih DB-free/stubbed; reverse proxy/FastCGI harus meneruskan custom header dan cache halaman tidak boleh berbagi token lintas sesi. E2E staging perlu membuktikan empat request valid sukses pada user berizin serta GET/header hilang/token salah menghasilkan 405/403 tanpa mutasi. Writer lain, termasuk repair lot material di luar empat endpoint, masih belum tercakup scoped CSRF.
- Batch berikutnya: minta auditor memilih prioritas tertinggi berikutnya, dengan kandidat CSRF writer lain yang berdampak tinggi atau dependency/runtime/release preflight; tetap tanpa License Hub.

## Batch 15 — P0-06A-5 scoped CSRF transaksi inti POS web

- Waktu: 2026-09-02 12:39 WIB.
- Prioritas: P0 security/integritas transaksi — writer payment, void, dan refund POS web memiliki dampak langsung terhadap pembayaran, reversal, status order, sinkronisasi task, serta rebuild availability, sementara CSRF global masih disabled.
- Diskusi auditor/fixer: auditor memilih batch kecil pada tiga writer transaksi inti karena dampaknya finansial dan batas perbaikannya jelas. Fixer menambahkan token session-bound dan guard setelah RBAC namun sebelum payload/model/sinkronisasi/rebuild, lalu menghubungkan wrapper AJAX scoped pada tiga view. Review auditor pertama menemukan smoke test menghitung deklarasi wrapper sebagai invocation, assertion header terlalu global, dan belum menguji reuse token session valid tanpa write. Fixer memperbaiki smoke-only; review kedua menyatakan PASS.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/cashier_index.php`
  - `application/views/pos/order_draft_index.php`
  - `application/views/pos/order_paid_index.php`
  - `tools/tests/pos_transaction_csrf_smoke.php`
- Perubahan utama:
  - `order_payment_save`, `order_void_save`, dan `order_refund_save` mempertahankan RBAC lalu menjalankan guard scoped sebelum `request_payload()`, model writer, sync task, atau rebuild availability.
  - Token memakai `random_bytes(32)`, invariant 64 karakter hex, reuse hanya untuk token session valid, dan `hash_equals`; non-POST ditolak 405 JSON, token invalid/missing/malformed/session mismatch ditolak 403 JSON.
  - Browser memakai header `X-Pos-Transaction-CSRF`; server membaca nama canonical CodeIgniter `X-Pos-Transaction-Csrf` agar sesuai normalisasi CGI/FastCGI. Tidak ada fallback query/body dan CSRF global tidak diaktifkan.
  - Cashier memakai wrapper hanya untuk payment dan void; draft hanya void; paid hanya refund. Shared `postJson` tetap tidak membawa header scoped secara default.
  - Smoke DB-free diperketat untuk menghitung invocation tanpa deklarasi, memeriksa badan wrapper, menghitung tepat tiga guard call-site, dan membuktikan token valid direuse tanpa `set_userdata`.
- Validasi:
  - `php -l` seluruh empat view/controller dan smoke test: lulus.
  - `php tools/tests/pos_transaction_csrf_smoke.php`: 258/258 lulus.
  - Regression `pos_stock_commit_repair_csrf_smoke.php`: 317/317; `inventory_control_mutation_csrf_smoke.php`: 272/272; `auth_division_scope_smoke.php`: 39/39; `web_runtime_boundary_smoke.php`: 14/14; POS Mobile authorization: seluruh pemeriksaan lulus.
  - `inventory_period_guard_smoke.php`: seluruh skenario lulus.
  - `git diff --check`: lulus.
  - Tidak menjalankan query database, backup runner, atau mutasi data.
- Review auditor final: PASS. Harness tidak lagi memberi false assurance pada wrapper/header/helper; implementasi tetap menjaga urutan RBAC → CSRF → payload/writer dan alur UI transaksi.
- Risiko sisa: smoke masih DB-free/source-based; E2E HTTP dengan session/cookie nyata dan stack FastCGI/proxy belum dilakukan. CSRF scoped belum mencakup writer POS lain seperti draft save/confirm, cashier open/close, runtime job, stock-live rebuild, dan endpoint web mutasi lain. Perlindungan ini juga tidak menggantikan mitigasi XSS atau session theft.
- Batch berikutnya: minta auditor memilih prioritas tertinggi; kandidat terdekat adalah E2E HTTP security probe untuk kontrak CSRF yang sudah dipasang atau scoped CSRF pada writer POS berisiko tinggi berikutnya, sambil tetap menunda License Hub.

## Batch 16 — P0-06A-6 scoped CSRF buka/tutup kasir POS web

- Waktu: 2026-09-02 12:54 WIB.
- Prioritas: P0 security/integritas transaksi — `cashier_open` membuat shift/sesi kasir, sedangkan `cashier_close` mengubah status shift, kas aktual, selisih, ringkasan, dan menyiapkan direct print. Keduanya sebelumnya hanya RBAC tanpa guard CSRF scoped.
- Diskusi auditor/fixer: auditor memilih dua writer ini sebagai delta terkecil dengan dampak finansial langsung. Fixer menambahkan guard setelah RBAC dan mengalihkan dua call AJAX ke wrapper transaksi yang sudah ada. Smoke diperluas dari tiga menjadi lima writer dan diberi stub daily-recon serta tripwire dependency agar request invalid berhenti sebelum payload/model.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/cashier_index.php`
  - `tools/tests/pos_transaction_csrf_smoke.php`
- Perubahan utama:
  - `cashier_open` dan `cashier_close` menjalankan `require_pos_transaction_csrf()` tepat setelah permission check dan sebelum payload, daily recon, writer shift/session, atau direct print.
  - Pemanggilan `pos/cashier/open` dan `pos/cashier/close` memakai `postPosTransactionJson`; helper `postJson` umum tetap tidak mengirim header CSRF global.
  - Smoke DB-free menguji lima writer untuk GET 405, header/session invalid 403, valid browser/canonical CI header, urutan RBAC → CSRF → payload/recon/writer, serta tidak ada dependency model pada rejection.
- Validasi:
  - `php -l` controller, view, dan smoke test: lulus.
  - `php tools/tests/pos_transaction_csrf_smoke.php`: 437/437 lulus.
  - Regression `pos_stock_commit_repair_csrf_smoke.php`: 317/317; `inventory_control_mutation_csrf_smoke.php`: 272/272; `inventory_period_guard_smoke.php`: seluruh skenario lulus; POS Mobile authorization: seluruh pemeriksaan lulus.
  - `git diff --check`: lulus.
  - Tidak menjalankan query database, backup runner, atau mutasi data.
- Review auditor final: PASS. Guard dan wrapper berada pada lokasi yang tepat; acceptance B1 terpenuhi dan smoke tidak memberi false assurance yang terlihat.
- Risiko sisa: validasi masih DB-free/source-based; E2E HTTP dengan session/cookie nyata melalui FastCGI/reverse proxy belum dilakukan. Writer POS web lain seperti draft save/confirm, runtime job, dan stock-live rebuild masih belum tercakup CSRF scoped.
- Batch berikutnya: minta auditor memilih prioritas tertinggi berikutnya, dengan kandidat scoped CSRF draft/confirm atau E2E HTTP security probe bila URL dan akun uji aman tersedia; tetap tanpa License Hub.

## Batch 17 — P0-06A-7 scoped CSRF + permission delete draft POS

- Waktu: 2026-09-02 13:11 WIB.
- Prioritas: P0 security/integritas transaksi — endpoint `order_draft_delete($id)` menghapus order draft beserta line, state log, snapshot, dan runtime job; sebelumnya hanya memakai permission `edit` tanpa pembatasan POST dan CSRF scoped.
- Diskusi auditor/fixer: auditor memilih satu writer dengan route ID yang jelas sebagai batch kecil berisiko terukur. Fixer mengganti authorization menjadi aksi `delete`, mempertahankan selector/fallback halaman POS yang ada, menambahkan guard CSRF setelah RBAC dan sebelum actor/model, serta mengalihkan dua pemanggil UI ke wrapper scoped.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/cashier_index.php`
  - `application/views/pos/order_draft_index.php`
  - `tools/tests/pos_transaction_csrf_smoke.php`
- Perubahan utama:
  - `order_draft_delete` sekarang memakai `delete`, menolak non-POST dengan 405 dan token/header/session invalid dengan 403, tanpa mencapai `delete_order_draft` pada request invalid.
  - Request valid dengan header browser atau canonical CodeIgniter yang session-bound meneruskan route ID dan actor ke writer tepat sekali; endpoint tidak membaca payload body.
  - Delete draft pada halaman cashier dan draft memakai `postPosTransactionJson`; helper `postJson` umum tetap tidak membawa header scoped secara default.
  - Smoke DB-free diperluas untuk permission delete, route ID/actor, urutan guard, 405/403, invalid tanpa model, valid dua casing header, dan wrapper invocation kedua view.
- Validasi:
  - `php -l` controller, dua view, dan smoke test: lulus.
  - `php tools/tests/pos_transaction_csrf_smoke.php`: 529/529 lulus.
  - Regression `pos_stock_commit_repair_csrf_smoke.php`: 317/317; `inventory_control_mutation_csrf_smoke.php`: 272/272; `inventory_period_guard_smoke.php`: seluruh skenario lulus; POS Mobile authorization: seluruh pemeriksaan lulus.
  - `git diff --check`: lulus.
  - Tidak menjalankan query database, backup runner, atau mutasi data.
- Review auditor final: PASS; batch layak diterima. Auditor mengonfirmasi guard berada setelah RBAC dan sebelum actor/model, kedua view memakai wrapper, serta smoke memenuhi acceptance tanpa temuan baru di luar scope.
- Risiko sisa: validasi masih DB-free/source-based; E2E HTTP dengan session/RBAC nyata, browser runtime, dan FastCGI/reverse proxy belum dilakukan. Perubahan permission dapat menghasilkan 403 bagi user yang sebelumnya hanya memiliki `edit`; cache JavaScript lama juga harus memuat token baru. Writer POS lain seperti draft save/confirm, runtime job, dan stock-live rebuild masih belum tercakup.
- Batch berikutnya: minta auditor memilih prioritas P0 tertinggi berikutnya, kandidat utama adalah scoped CSRF pada draft save/confirm atau probe E2E HTTP jika URL dan akun uji staging tersedia; tetap tanpa License Hub.

## Batch 18 — P0-06A-8 scoped CSRF + action guard draft save/confirm POS

- Waktu: 2026-09-02 13:36 WIB.
- Prioritas: P0 security/integritas transaksi — endpoint `order_draft_save`, `order_draft_save_confirm`, dan `order_draft_confirm` mengubah draft, menyimpan snapshot/HPP, membuat queue stock commit, dan mengonfirmasi status order; sebelumnya belum memiliki CSRF scoped dan pembatasan POST.
- Diskusi auditor/fixer: auditor memilih tiga endpoint ini sebagai batch kecil berikutnya setelah writer payment, void/refund, buka/tutup kasir, dan delete draft ditutup. Fixer menerapkan urutan `view RBAC → CSRF → payload/id → permission create/edit → writer` untuk endpoint berbasis body, serta `edit RBAC → CSRF → actor/downstream` untuk confirm berbasis route. Caller frontend pada cashier dan draft memakai wrapper scoped yang sama.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/cashier_index.php`
  - `application/views/pos/order_draft_index.php`
  - `tools/tests/pos_transaction_csrf_smoke.php`
- Perubahan utama:
  - Ketiga endpoint kini menolak non-POST dengan 405 dan token/header/session invalid dengan 403 sebelum payload, actor, model, snapshot, queue, atau service downstream.
  - `order_draft_save` dan `order_draft_save_confirm` tetap membedakan aksi `create` untuk `id=0` dan `edit` untuk `id>0`, dengan permission action diperiksa setelah CSRF dan sebelum writer.
  - `order_draft_confirm` tetap memakai permission `edit`, lalu menjalankan guard sebelum actor dan proses konfirmasi.
  - Semua caller draft save/confirm pada cashier dan draft memakai `postPosTransactionJson` dengan `X-Pos-Transaction-CSRF`; helper POST umum tidak diberi header scoped secara global.
  - Smoke DB-free mencakup tiga endpoint baru, 405 non-POST, invalid token tanpa body/model/downstream, dua bentuk header, create/edit `id=0/1701`, writer terkontrol, dan wrapper exact.
- Validasi:
  - `php -l` controller, dua view, dan smoke test: lulus.
  - `php tools/tests/pos_transaction_csrf_smoke.php`: 832/832 lulus.
  - Regression `pos_stock_commit_repair_csrf_smoke.php`: 317/317; `inventory_control_mutation_csrf_smoke.php`: 272/272; `inventory_period_guard_smoke.php`: semua skenario lulus; POS Mobile authorization: seluruh pemeriksaan lulus.
  - `git diff --check`: lulus.
  - Tidak menjalankan query database, backup runner, atau mutasi data.
- Review auditor final: PASS; auditor mengonfirmasi urutan guard/RBAC, semantik create/edit, wrapper frontend, dan assertion smoke sesuai acceptance. Tidak ada perubahan schema atau algoritme bisnis pada batch ini.
- Risiko sisa: validasi masih DB-free/source-based; E2E HTTP dengan session/RBAC nyata, browser runtime, dan FastCGI/reverse proxy belum dilakukan. Writer POS lain seperti runtime job trigger/process/retry, stock-live rebuild, dan endpoint web mutasi lain masih perlu audit/guard scoped. Permission `view` tetap menjadi prasyarat sebelum action create/edit dan dapat menghasilkan 403 secara fail-closed bagi konfigurasi role yang tidak lengkap.
- Batch berikutnya: minta auditor memilih prioritas P0 tertinggi berikutnya; kandidat utama adalah scoped CSRF pada runtime-job writer yang dipicu setelah confirm atau E2E HTTP security probe jika endpoint dan akun uji staging tersedia, tetap tanpa License Hub.

## Batch 19 — P0 runtime-job trigger POS

- Waktu: 2026-09-02 14:11 WIB.
- Prioritas: P0 security/integritas stok — `POST /pos/orders/runtime-jobs/trigger/{orderId}` memproses queue commit stok dan rebuild availability; sebelumnya belum memiliki CSRF scoped, POST-only, dan binding kuat antara route order, job, dan snapshot.
- Diskusi auditor/fixer: auditor memilih route ini karena dipanggil setelah confirm dan dapat memicu writer stok. Fixer menambahkan urutan `RBAC edit → POST/CSRF → payload/ID → binding job/order/type/snapshot/status/scope → service`, serta wrapper header scoped pada enam caller. Global `postJson` tidak diubah.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/cashier_index.php`
  - `application/views/pos/order_draft_index.php`
  - `application/views/pos/reservation_index.php`
  - `application/views/pos/self_order_orders.php`
  - `application/views/pos/online_food_orders.php`
  - `application/views/pos/stock_live_index.php`
  - `tools/tests/pos_runtime_job_trigger_csrf_smoke.php`
  - `tools/tests/pos_transaction_csrf_smoke.php`
- Perubahan utama:
  - Route menolak non-POST dan CSRF invalid sebelum membaca payload, query, atau memanggil service.
  - `orderId`, `job_id`, `job.order_id`, `job_type`, `snapshot_id/order_id`, status job/order/snapshot, retry, dan scope divalidasi fail-closed.
  - Pemanggil UI mengirim `X-Pos-Transaction-CSRF` melalui wrapper scoped; token disediakan pada controller/view yang sebelumnya belum memilikinya.
- Validasi:
  - `php -l` pada controller, enam view, dan dua smoke test: lulus.
  - Runtime smoke: 247/247; transaction smoke: 832/832.
  - Regression stock-commit repair: 317/317; inventory-control mutation: 272/272; inventory period guard, POS Mobile authorization, dan web runtime boundary: lulus.
  - `git diff --check`: lulus.
  - Tidak menjalankan query database, backup runner, atau mutasi data.
- Review auditor final: PASS. Implementasi sesuai acceptance dan tidak mengubah schema maupun algoritme bisnis utama.
- Risiko sisa: smoke masih DB-free/source-based; E2E HTTP dengan session/RBAC nyata dan locking/query aktual belum dilakukan. `candidate_jobs(job_id)` di service belum memfilter `order_id`, tetapi controller menutup binding sebelum service. Branch refund pada view draft/paid masih merupakan dead-code inconsistency; route PAID aktif memakai view paid terpisah.
- Batch berikutnya: minta auditor memilih prioritas P0 tertinggi berikutnya, dengan kandidat scoped CSRF pada runtime-job process/retry atau stock-live rebuild, lalu lanjutkan tanpa License Hub.

## Batch 20 — P0 scoped CSRF manual rebuild Stock Live POS

- Waktu: 2026-09-02 14:27 WIB.
- Prioritas: P0 security/integritas availability — endpoint `stock_live_rebuild` dan `stock_live_rebuild_all` menulis cache availability/HPP live serta audit log, tetapi sebelumnya belum POST-only dan belum memakai CSRF scoped.
- Diskusi auditor/fixer: auditor memilih dua writer ini sebagai batch kecil dengan dampak jelas. Fixer mempertahankan permission dan seluruh payload/context, menempatkan guard `RBAC edit → CSRF → payload/actor/service`, lalu mengganti tepat dua caller rebuild pada view Stock Live ke wrapper scoped. `postJson` global tetap netral.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/stock_live_index.php`
  - `tools/tests/pos_stock_live_rebuild_csrf_smoke.php`
  - `tools/tests/pos_transaction_csrf_smoke.php`
- Perubahan utama:
  - Dua writer kini menolak GET/PUT/PATCH dengan 405 dan token missing/malformed/cross-session/mismatch dengan 403 sebelum body, actor, library, cache, atau log disentuh.
  - Header browser dan canonical CodeIgniter/FastCGI diterima hanya bila sama dengan token session; token valid direuse tanpa rotasi.
  - Rebuild per-produk dan rebuild-all mengirim `X-Pos-Transaction-CSRF` melalui `postPosTransactionJson`; route, model, service, schema, dan algoritme availability tidak diubah.
  - Regression smoke transaction disesuaikan dari 10 menjadi 12 guarded writers.
- Validasi:
  - `php -l` controller, view, dan dua smoke test: lulus.
  - Stock Live smoke: 237/237; transaction smoke: 832/832.
  - Regression runtime trigger: 247/247; stock-commit repair: 317/317; inventory-control: 272/272; inventory period guard, POS Mobile authorization, dan web runtime boundary: lulus.
  - `git diff --check`: lulus.
  - Tidak menjalankan query database, rebuild nyata, backup runner, atau mutasi data.
- Review auditor final: PASS. Kedua writer memenuhi acceptance; tepat dua caller memakai wrapper, global `postJson` bebas header scoped, dan tidak ada artefak schema/data/runtime baru.
- Risiko sisa: smoke masih isolated/source-based; E2E browser melalui FastCGI/reverse proxy belum membuktikan header diteruskan. Rebuild-all tetap sinkron dan dapat menghasilkan banyak log bila diulang oleh operator berizin; pembatasan/queue/retensi perlu batch terpisah.
- Batch berikutnya: scoped-CSRF dan method hardening untuk mutasi runtime-job POS yang masih memakai `postJson`, terutama `process-all`, `retry`, dan `retry-failed-all`; tetap tanpa License Hub.

## Batch 21 — P0 scoped CSRF mutasi runtime-job POS

- Waktu: 2026-09-02 14:46 WIB.
- Prioritas: P0 security/integritas stok — endpoint `runtime-jobs/retry/{jobId}`, `process-all`, dan `retry-failed-all` masih menerima mutasi tanpa CSRF scoped dan sebagian caller UI masih memakai `postJson` netral.
- Diskusi auditor/fixer: auditor memilih tiga endpoint ini sebagai kelanjutan langsung Batch 19 karena retry/process dapat memicu `PosRuntimeJobService`, retry stok, dan pemrosesan queue. Fixer menerapkan urutan `edit RBAC → POST/session-bound CSRF → validasi input/ID → dependency/service`, memindahkan `set_time_limit(0)` setelah guard, serta menambah wrapper token pada view Stock Live, Audit Commit POS, dan reconcile Purchase. `postJson` bersama tetap netral.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/controllers/Purchase.php`
  - `application/views/pos/stock_live_index.php`
  - `application/views/pos/stock_commit_audit_index.php`
  - `application/views/purchase/stock_division_reconcile_index.php`
  - `tools/tests/pos_runtime_job_mutation_csrf_smoke.php`
  - `tools/tests/pos_transaction_csrf_smoke.php`
- Perubahan utama:
  - `retry/{jobId}` menolak method non-POST, memverifikasi token session/header canonical, lalu memvalidasi route ID positif sebelum memuat service atau memanggil retry.
  - `process-all` dan `retry-failed-all` memverifikasi CSRF sebelum membaca body, mengubah time limit, memuat service, atau memproses queue.
  - Caller runtime-job aktif pada tiga view memakai `postPosTransactionJson` dengan `X-Pos-Transaction-CSRF`; token Audit Commit POS dan reconcile Purchase dirender dari session.
  - Smoke baru menguji 308 skenario source/behavioral DB-free, termasuk method invalid, token missing/malformed/cross-session, ID invalid, canonical header, urutan dependency, dan seluruh caller target.
- Validasi:
  - `php -l` lima file aplikasi target dan dua smoke test: lulus.
  - `php tools/tests/pos_runtime_job_mutation_csrf_smoke.php`: 308/308 lulus.
  - Regression `pos_transaction_csrf_smoke.php`: 832/832; runtime trigger: 247/247; stock-live rebuild: 237/237; stock-commit repair: 317/317.
  - `git diff --check`: lulus.
  - Tidak menjalankan query database, backup runner, rebuild nyata, atau mutasi data.
- Review auditor: PASS. Route aktif tetap benar, guard dan validasi ID berada sebelum dependency, wrapper scoped dipakai pada seluruh caller target, dan helper `postJson` tidak membawa header transaksi secara default.
- Risiko sisa:
  - Smoke masih DB-free/source-based; E2E browser melalui session nyata, FastCGI, dan reverse proxy belum membuktikan custom header diteruskan.
  - Tombol `process-all` pada `stock_commit_audit_index.php` masih mengirim `{limit}` tanpa `outlet_id`, sementara controller mewajibkan outlet; ini temuan lama di luar delta batch dan menyebabkan 422 sampai kontrak UI diselaraskan.
  - Mutasi runtime lain (`dismiss/delete job` dan `retry/dismiss snapshot`) masih perlu scoped CSRF/method hardening; tiga endpoint Batch 21 sudah tertutup.
- Batch berikutnya: auditor mengarahkan scoped CSRF/method hardening untuk mutasi runtime POS lain, khususnya dismiss/delete job dan retry/dismiss snapshot, lalu probe E2E HTTP CSRF bila endpoint dan akun uji staging tersedia; tetap tanpa License Hub.

## Batch 22 — P0 scoped CSRF delete-draft runtime-job POS

- Waktu: 2026-09-02 15:08 WIB.
- Prioritas: P0 security/integritas transaksi dan stok — endpoint `runtime-jobs/delete-draft/{jobId}` dapat menghapus order beserta artefak job/snapshot/line, tetapi sebelumnya belum POST-only, belum memakai CSRF scoped, dan masih melakukan cast ID sebelum validasi.
- Diskusi auditor/fixer: auditor memilih subset delete-draft sebagai mutasi paling destruktif dengan delta terkecil; snapshot retry ditunda karena menyentuh rangkaian writer stok yang lebih luas. Fixer mempertahankan policy `edit` yang sudah ada, route, model, query, dan status rules; perubahan dibatasi pada guard/parser, caller, smoke test, dan hitungan regresi.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/stock_commit_audit_index.php`
  - `tools/tests/pos_runtime_failed_job_delete_draft_csrf_smoke.php`
  - `tools/tests/pos_transaction_csrf_smoke.php`
- Perubahan utama:
  - `order_runtime_failed_job_delete_draft()` kini menjalankan urutan `edit RBAC → require_pos_transaction_csrf() → parse_positive_runtime_id(jobId) → DB/query → Pos_model`.
  - Caller aktif delete-draft pada Audit Commit Stok POS memakai `postPosTransactionJson` dengan header `X-Pos-Transaction-CSRF`; `postJson` bersama tetap netral.
  - Smoke DB-free baru mencakup 121 check: GET/PUT/PATCH `405`, CSRF missing/malformed/no-session/cross-session/mismatch `403`, ID `0/-1/1abc/float` `422` sebelum DB/model, dua bentuk header valid, binding job/type/status, route, dan writer `delete_order_draft(order_id, actor)` tepat sekali.
  - Regression count transaction diperbarui dari 15 menjadi 16 guarded writers.
- Validasi:
  - `php -l` empat file target: lulus.
  - `php tools/tests/pos_runtime_failed_job_delete_draft_csrf_smoke.php`: 121/121 lulus.
  - `php tools/tests/pos_transaction_csrf_smoke.php`: 832/832 lulus.
  - `php tools/tests/pos_runtime_job_mutation_csrf_smoke.php`: 308/308 lulus.
  - `php tools/tests/pos_runtime_job_trigger_csrf_smoke.php`: 247/247 lulus.
  - `php tools/tests/pos_stock_live_rebuild_csrf_smoke.php`: 237/237 lulus.
  - `php tools/tests/pos_stock_commit_repair_csrf_smoke.php`: 317/317 lulus.
  - `git diff --check`: lulus.
  - Tidak menjalankan query/mutasi database, backup runner, rebuild nyata, atau perubahan artefak backup/upload/credential/log/runtime.
- Review auditor: PASS. Auditor mengisolasi delta Batch 22 dari perubahan kumulatif Batch 1–21 dan mengonfirmasi guard, parser, query binding, caller wrapper, serta smoke sesuai acceptance. Route, `Pos_model`, permission policy, endpoint dismiss/snapshot, dan algoritme stok tidak berubah.
- Risiko sisa:
  - Validasi masih DB-free/source-based; E2E browser dengan session/cookie nyata dan FastCGI/reverse proxy belum dijalankan.
  - ID nonnumerik dibuktikan pada level method-controller; route `(:num)` dapat menolak sebagian input lebih awal pada HTTP nyata.
  - Endpoint audit delete-draft masih memakai permission `edit`, berbeda dari `order_draft_delete` yang memakai `delete`; ini follow-up kebijakan RBAC dan sengaja tidak diubah pada batch ini.
  - Mutasi runtime-job dismiss serta runtime-snapshot retry/dismiss masih terbuka dan membutuhkan batch tersendiri.
- Batch berikutnya: hardening scoped CSRF/method untuk `runtime-jobs/dismiss/{jobId}`, lalu `runtime-snapshots/retry/{snapshotId}` dan `runtime-snapshots/dismiss/{snapshotId}`; setelah itu probe E2E HTTP dengan akun uji staging bila tersedia. Tetap tanpa License Hub.

## Batch 23 — P0 scoped CSRF dismiss runtime-job POS

- Waktu: 2026-09-02 15:28 WIB.
- Prioritas: P0 security/integritas runtime POS — endpoint `runtime-jobs/dismiss/{jobId}` menutup job FAILED secara mutatif, tetapi sebelumnya belum POST-only, belum memakai CSRF scoped, melakukan load service terlalu awal, dan memakai cast ID longgar.
- Diskusi auditor/fixer: auditor memilih dismiss job sebagai batch kecil sesudah delete-draft. Snapshot retry/dismiss dipisahkan karena menyentuh refresh snapshot, pembuatan job, dan/atau beberapa job sekaligus. Fixer mempertahankan permission selector `edit`, route, query, policy status, alasan cancel, service/model/SQL/schema, delete-draft, dan snapshot; perubahan hanya pada guard/parser, caller, smoke, dan regression count.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/stock_commit_audit_index.php`
  - `tools/tests/pos_runtime_failed_job_dismiss_csrf_smoke.php`
  - `tools/tests/pos_transaction_csrf_smoke.php`
- Perubahan utama:
  - `order_runtime_failed_job_dismiss()` kini menjalankan urutan `edit RBAC → POST/scoped CSRF → parse_positive_runtime_id() → readiness/lookup DB → status FAILED → load/cancel_job()`.
  - ID `0`, negatif, campuran, float, leading zero, whitespace, dan overflow ditolak `422` setelah CSRF dan sebelum DB/service.
  - Hanya job berstatus tepat `FAILED` yang boleh memanggil `cancel_job()`; caller `.sca_dismiss_failed_job_btn` memakai `postPosTransactionJson`, sedangkan `postJson` shared tetap netral.
  - Smoke DB-free baru menjalankan method controller aktual dengan fake/tripwire dan mencakup 256 check: RBAC selector, 405, CSRF 403, strict ID 422, query `LEFT JOIN`/binding/type, status policy, dua bentuk header valid, urutan dependency, alasan cancel, respons, dan kontrak route/caller.
  - Regression count transaction diperbarui dari 16 menjadi 17 guarded writers.
- Validasi:
  - `php -l` controller, view, dan dua smoke test: lulus.
  - `php tools/tests/pos_runtime_failed_job_dismiss_csrf_smoke.php`: 256/256 lulus.
  - `php tools/tests/pos_transaction_csrf_smoke.php`: 832/832 lulus.
  - Regression delete-draft: 121/121; runtime mutation: 308/308; runtime trigger: 247/247; stock-live rebuild: 237/237; stock-commit repair: 317/317 lulus.
  - `git diff --check`: lulus untuk delta tracked dan smoke baru tidak memiliki whitespace error.
  - Tidak menjalankan query/mutasi database, backup runner, rebuild nyata, atau perubahan artefak backup/upload/credential/log/runtime.
- Review auditor final: PASS tanpa changes required. Auditor mengonfirmasi smoke memuat dan menjalankan method controller aktual, guard berada sebelum DB/service, query/status/reason/response tetap, caller tepat scoped, dan route/service/model/schema/delete-draft/snapshot tidak ikut berubah.
- Risiko sisa:
  - Validasi masih DB-free/source-based; E2E HTTP dengan session/RBAC nyata melalui FastCGI/reverse proxy belum dijalankan. Race antara lookup status FAILED dan conditional update service masih bergantung pada guard atomik di service.
  - Route `(:num)` dapat menolak sebagian ID nonkanonis lebih awal pada HTTP nyata; method-level smoke tetap menguji controller.
  - Mutasi runtime-snapshot retry/dismiss masih terbuka. Tombol `process-all` yang mengirim `{limit}` tanpa `outlet_id` juga tetap menjadi temuan lama di luar batch.
- Batch berikutnya: auditor mengarahkan hardening scoped CSRF/method untuk `runtime-snapshots/retry/{snapshotId}` sebagai batch terpisah; setelah itu `runtime-snapshots/dismiss/{snapshotId}` dan probe E2E HTTP bila akun/fixture staging tersedia. Tetap tanpa License Hub.

## Batch 24 — P0 scoped CSRF retry runtime-snapshot POS

- Waktu: 2026-09-02 15:56 WIB.
- Prioritas: P0 security/integritas stok — endpoint `runtime-snapshots/retry/{snapshotId}` dapat merefresh snapshot, mengubah state order, membuat job, dan langsung memproses stock commit, tetapi sebelumnya belum POST-only, belum memakai CSRF scoped, memuat dependency terlalu awal, dan melakukan cast ID longgar.
- Diskusi auditor/fixer: auditor memilih retry snapshot sebagai writer berisiko tertinggi berikutnya. Fixer mempertahankan permission selector `edit`, route, query, state policy, service/model/schema, sequence writer, dan response; perubahan dibatasi pada guard/parser, caller, smoke, serta assertion regression yang terdampak.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/stock_commit_audit_index.php`
  - `tools/tests/pos_runtime_failed_snapshot_retry_csrf_smoke.php`
  - `tools/tests/pos_runtime_failed_job_dismiss_csrf_smoke.php`
  - `tools/tests/pos_transaction_csrf_smoke.php`
- Perubahan utama:
  - `order_runtime_failed_snapshot_retry()` kini menjalankan urutan `edit RBAC → POST/scoped CSRF → parse_positive_runtime_id(snapshotId) → lookup/state gate → actor/service load → writer lama`.
  - ID nonkanonis seperti `0`, negatif, campuran, float, leading zero, whitespace, dan overflow ditolak `422` sebelum DB/service.
  - State gate tetap mensyaratkan snapshot `FAILED`, order valid dan bukan `VOID`, serta stock commit order bukan `POSTED`, `REVERSED`, atau `NOT_REQUIRED`.
  - Sequence lama dibuktikan tetap: refresh snapshot → mark queued → update order `QUEUED` → queue job → proses satu job → latest lookup.
  - Caller retry memakai `postPosTransactionJson`/`X-Pos-Transaction-CSRF`; caller snapshot dismiss tetap netral untuk batch berikutnya.
  - Smoke baru DB-free menjalankan method controller aktual via reflection/fake/tripwire dan mencakup 391 check: RBAC, method 405, semua failure CSRF, strict ID, query/state gate, refresh/queue/process failure, sequence writer, header canonical, dan response.
  - Assertion pada smoke dismiss diselaraskan agar memverifikasi split caller retry scoped vs snapshot dismiss netral. Regression count transaction tetap 832 dan sekarang mencakup 18 writer guarded.
- Validasi:
  - `php -l` controller, view, dan tiga smoke test: lulus.
  - Retry smoke: 391/391; dismiss smoke: 256/256; transaction smoke: 832/832 lulus.
  - Regression delete-draft: 121/121; runtime mutation: 308/308; runtime trigger: 247/247; stock-live rebuild: 237/237; stock-commit repair: 317/317 lulus.
  - `git diff --check`: lulus untuk delta tracked; smoke baru tidak memiliki whitespace error.
  - Tidak menjalankan query/mutasi database, backup runner, rebuild nyata, atau perubahan artefak backup/upload/credential/log/runtime.
- Review auditor final: PASS tanpa changes required. Auditor mengonfirmasi guard sebelum DB/actor/library/writer, parser strict, query dan policy tidak melonggar, sequence/response tetap, caller scoped tepat, smoke menguji method aktual dan failure paths, serta tidak ada spillover di luar retry snapshot dan assertion smoke terkait.
- Risiko sisa:
  - Validasi masih DB-free/source-based; E2E HTTP dengan session/RBAC nyata melalui FastCGI/reverse proxy belum dijalankan.
  - Lookup status awal dan rangkaian writer berada pada transaksi terpisah; race sempit masih mungkin dan idealnya ditutup dengan revalidasi conditional/atomic di service.
  - Snapshot dismiss dan `process-all` masih batch/follow-up terpisah; tombol `process-all` yang belum mengirim `outlet_id` tetap temuan lama di luar scope.
- Batch berikutnya: auditor mengarahkan hardening scoped CSRF/method untuk `runtime-snapshots/dismiss/{snapshotId}`; setelah itu probe E2E HTTP dan penyelarasan kontrak `process-all` bila fixture/akun staging tersedia. Tetap tanpa License Hub.

## Batch 25 — P0 hardening smoke boundary runtime-snapshot POS

- Waktu: 2026-09-02 16:24 WIB.
- Prioritas: P0 kualitas validasi/security regression — menutup false-pass pada smoke test Batch 25 untuk endpoint `runtime-snapshots/dismiss/{snapshotId}` dan regression smoke runtime-job dismiss.
- Diskusi auditor/fixer: auditor menemukan delimiter test memakai ID tombol `sca_process_all_jobs_btn` yang tidak ada di view nyata (`sca_process_all_btn`), sehingga blok handler dapat melebar sampai EOF. Fixer mengubah hanya dua smoke test, memakai selector nyata, dan menambahkan assertion bahwa delimiter ditemukan serta posisinya berada setelah handler snapshot dismiss; endpoint, view, route, service, model, dan schema tidak diubah.
- File berubah:
  - `tools/tests/pos_runtime_failed_snapshot_dismiss_csrf_smoke.php`
  - `tools/tests/pos_runtime_failed_job_dismiss_csrf_smoke.php`
- Perubahan utama:
  - Mengganti delimiter stale `sca_process_all_jobs_btn` menjadi `sca_process_all_btn`.
  - Menambahkan assertion delimiter tidak `false` dan berada setelah selector snapshot dismiss sebelum `substr()` mengambil blok caller.
  - Menghilangkan jalur silent fallback ke EOF sebagai kondisi yang dapat menghasilkan false pass; smoke tetap memakai fallback teknis, tetapi assertion wajib gagal bila delimiter tidak ditemukan.
- Validasi:
  - `php -l` kedua smoke: lulus.
  - Snapshot dismiss smoke: 504/504 lulus.
  - Job dismiss smoke: 257/257 lulus.
  - Snapshot retry regression: 391/391 lulus.
  - POS transaction CSRF regression: 832/832 lulus.
  - Selector stale tidak ditemukan; delimiter nyata terverifikasi pada view dan kedua smoke.
  - `git diff --check`: lulus; tidak ada trailing whitespace pada dua smoke baru.
- Review auditor final: PASS. Auditor mengonfirmasi kedua test tidak lagi dapat false-pass karena EOF slicing, selector/caller/route/endpoint tetap selaras, seluruh validasi yang diminta lulus, dan tidak ada perubahan aplikasi di luar scope.
- Risiko sisa:
  - Dua smoke test masih untracked sehingga wajib ikut dalam release/change set agar perlindungan regresinya tidak hilang.
  - Belum ada E2E browser/database dengan session, RBAC, FastCGI/reverse proxy, atau fixture staging nyata.
  - Race antara perubahan status snapshot dan pembatalan runtime job tetap ada sebagai risiko desain terpisah.
- Batch berikutnya: pastikan dua smoke test masuk paket perubahan, lalu auditor memilih prioritas berikutnya; kandidat terdekat adalah probe E2E staging bila fixture tersedia atau penyelarasan tombol `process-all` dengan kontrak `outlet_id`. Tetap tanpa License Hub.

## Batch 25R — Rekonsiliasi log implementasi runtime-snapshot dismiss

- Waktu: 2026-09-02 16:52 WIB (entri rekonsiliasi; implementasi dilakukan sebelum koreksi smoke Batch 25).
- Prioritas: P0 integritas stok — endpoint `runtime-snapshots/dismiss/{snapshotId}` menutup snapshot FAILED dan job runtime terkait sehingga harus POST-only, session-bound, dan memiliki binding order/status yang ketat.
- Ringkasan auditor/fixer: implementasi guard scoped, parser ID positif, state gate, penutupan snapshot, dan pembatalan job sudah diterapkan; review auditor final PASS setelah selector boundary smoke diperbaiki. Entri ini ditambahkan agar implementasi tidak hilang dari jejak eksekusi.
- File terkait: `application/controllers/Pos.php`, `application/views/pos/stock_commit_audit_index.php`, `tools/tests/pos_runtime_failed_snapshot_dismiss_csrf_smoke.php`, `tools/tests/pos_runtime_failed_snapshot_retry_csrf_smoke.php`, `tools/tests/pos_runtime_failed_job_dismiss_csrf_smoke.php`, `tools/tests/pos_transaction_csrf_smoke.php`.
- Validasi: snapshot dismiss 504 checks, snapshot retry 391 checks, job dismiss 257 checks, POS transaction 832 checks, lint terkait, selector boundary, dan `git diff --check` lulus.
- Risiko sisa: test masih DB-free/source-based; E2E HTTP, race status snapshot/job, dan kontrak process-all tanpa `outlet_id` tetap terbuka.

## Batch 26 — P0 scoped CSRF web-order verification writers

- Waktu: 2026-09-02 16:52 WIB.
- Prioritas: P0 security/integritas order dan stok — verifikasi reservasi, self-order, dan online-food dapat membuat/finalisasi order, snapshot stok, queue runtime job, sinkronisasi task, dan direct print, tetapi sebelumnya belum memakai guard POS transaction-CSRF.
- Ringkasan diskusi auditor/fixer: auditor memilih tiga writer sebagai batch kecil berisiko tinggi dengan infrastruktur token yang sudah tersedia. Process-all tidak dipilih karena audit page belum memiliki outlet selector yang aman. Fixer menempatkan `require_pos_transaction_csrf()` setelah RBAC dan sebelum payload/helper, mengganti hanya caller verifikasi terkait ke `postPosTransactionJson()`, serta memperluas smoke test dengan fake/tripwire.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/reservation_index.php`
  - `application/views/pos/self_order_orders.php`
  - `application/views/pos/online_food_orders.php`
  - `tools/tests/pos_transaction_csrf_smoke.php`
- Perubahan utama:
  - `reservation_verify()`, `self_order_order_verify()`, dan `online_food_order_verify()` kini menjalankan urutan `edit RBAC → scoped CSRF/POST → payload atau helper lama`.
  - Caller first-party verifikasi memakai header `X-Pos-Transaction-CSRF` dengan `credentials: same-origin`; helper `postJson()` umum tetap tidak membawa header scoped.
  - Smoke membuktikan GET/PUT `405`, token missing/malformed/mismatch `403`, tidak ada payload/model/service/writer sebelum guard, serta valid-token mempertahankan actor, payload, context, label/event, dan flow service.
  - Tidak ada perubahan route, schema, order rule, outlet scope, global CSRF, data, secret, atau runtime artifact.
- Validasi:
  - `php -l` controller, tiga view, dan smoke: lulus.
  - `pos_transaction_csrf_smoke.php`: 1.152 checks lulus.
  - Runtime trigger: 247; runtime mutation: 308; failed-job dismiss: 257; failed-snapshot retry: 391; failed-snapshot dismiss: 504 checks lulus.
  - `git diff --check`: lulus.
  - Tidak menjalankan query/mutasi database atau E2E browser karena fixture/session staging belum tersedia.
- Hasil review auditor: `REVIEW: PASS`; tidak ada required fix. Auditor mengonfirmasi urutan guard, caller scoped, smoke tripwire, dan perilaku valid-token sesuai acceptance.
- Risiko sisa: E2E browser/FastCGI/reverse proxy belum membuktikan custom header pada server nyata; CSRF global masih nonaktif; writer reject dan lifecycle reservasi/order lain di luar batch ini masih terbuka; worktree tetap memiliki perubahan kumulatif sebelumnya.
- Batch berikutnya: auditor memilih hardening scoped CSRF untuk writer lifecycle reservasi serta reject self-order/online-food, tetap tanpa License Hub.

## Batch 27 — P0 scoped CSRF lifecycle reservasi

- Waktu: 2026-09-02 17:07 WIB.
- Prioritas: P0 security/integritas transaksi reservasi — endpoint tambah DP, reject, dan cancel melakukan mutasi finansial/status, tetapi sebelumnya belum POST-only dan belum terikat CSRF scoped.
- Ringkasan diskusi auditor/fixer: auditor memilih tiga writer dalam satu halaman reservasi karena token dan wrapper scoped sudah tersedia dari batch sebelumnya. `reservation_save()` sengaja dipisahkan karena permission create/edit ditentukan dari payload; reject self-order/online-food dan process-all tetap batch lain. Fixer menempatkan guard setelah permission existing dan sebelum payload/model, lalu mengganti caller writer ke wrapper scoped.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/reservation_index.php`
  - `tools/tests/pos_transaction_csrf_smoke.php` (untracked; wajib ikut change/release set)
- Perubahan utama:
  - `reservation_deposit()` memakai `edit → scoped CSRF/POST → request_payload() → add_deposit()`.
  - `reservation_reject()` memakai `edit → scoped CSRF/POST → request_payload() → reject_reservation()`.
  - `reservation_cancel()` memakai `delete → scoped CSRF/POST → request_payload() → cancel_reservation()`.
  - `saveMoreDeposit()` dan `saveClose()` memakai `postPosTransactionJson()`; helper `request()` generik dan caller read-only tetap netral.
  - Smoke diperluas dari 22 menjadi 25 guarded writers dan menguji method controller aktual, 405 GET/PUT, 403 token invalid tanpa payload/model, valid header, payload, actor, permission, response, serta writer invocation.
- Validasi:
  - `php -l` untuk controller, view, dan smoke: lulus.
  - `php tools/tests/pos_transaction_csrf_smoke.php`: 1.384 checks lulus.
  - Regression runtime POS: trigger 247, mutation 308, failed-job dismiss 257, failed-snapshot retry 391, failed-snapshot dismiss 504 checks lulus.
  - `git diff --check`: lulus.
  - Tidak ada perubahan route, schema, model production, global CSRF, business rule, database, secret, atau runtime artifact.
- Hasil review auditor: `REVIEW: PASS`; tidak ada required fix. Auditor mengonfirmasi guard placement, caller scoped, helper read-only yang tetap netral, smoke tripwire, dan validasi independen.
- Risiko sisa:
  - Smoke masih DB-free/source-based; E2E HTTP dengan session/RBAC nyata, FastCGI/reverse proxy, dan mutasi finansial live belum dibuktikan.
  - Worktree berisi perubahan kumulatif batch sebelumnya sehingga review dibatasi pada delta Batch 27.
  - `reservation_save()`, reject self-order/online-food, dan bug kontrak process-all tanpa `outlet_id` masih terbuka.
- Batch berikutnya: auditor memilih satu batch kecil berikutnya; kandidat prioritas adalah authenticated HTTP integration smoke untuk satu reservation writer bila fixture/akun staging tersedia, atau hardening writer reject self-order/online-food bila E2E belum dapat dijalankan. Tetap tanpa License Hub.

## Batch 28 — P0 scoped CSRF reject Self Order dan Online Food

- Waktu: 2026-09-02 17:21 WIB.
- Prioritas: P0 security/integritas order — endpoint reject Self Order dan Online Food melakukan perubahan status order/pembayaran menjadi VOID/REJECTED, tetapi belum memakai scoped transaction-CSRF.
- Ringkasan diskusi auditor/fixer: auditor memilih dua endpoint sebagai batch kecil dengan risiko tinggi dan pola perbaikan mekanis. Fixer menempatkan urutan `edit RBAC → scoped CSRF/POST → payload/reason → actor → writer`, mengganti caller reject pada dua view ke wrapper scoped, dan memperkuat smoke dengan fixture writer, reflection tripwire, pemeriksaan urutan, 405/403, argumen, response flow, serta netralitas `postJson`. Tidak ada perluasan ke route, schema, model production, global CSRF, atau License Hub.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/self_order_orders.php`
  - `application/views/pos/online_food_orders.php`
  - `tools/tests/pos_transaction_csrf_smoke.php` (untracked; wajib ikut change/release set)
- Perubahan utama:
  - `self_order_order_reject()` dan `online_food_order_reject()` kini mempertahankan permission `edit`, menolak non-POST dengan 405 dan token tidak valid dengan 403 sebelum payload/model, lalu menjalankan writer lama hanya setelah token session-bound valid.
  - Caller `submitReject()` pada self-order dan online-food memakai `postPosTransactionJson()` sehingga mengirim `X-Pos-Transaction-CSRF` dengan `same-origin`; helper `postJson()` tetap netral.
  - Smoke mencakup 27 guarded writers dan membuktikan ID route `1701`, actor `1`, reason trim `Alasan smoke`, writer tepat satu kali, response error/success, serta wrapper/caller.
- Validasi:
  - `php -l` controller, dua view, dan smoke: lulus.
  - `php tools/tests/pos_transaction_csrf_smoke.php`: 1.591 checks lulus.
  - Regression runtime POS: trigger 247 dan mutation 308 checks lulus.
  - `git diff --check` untuk file target: lulus.
  - Tidak menjalankan query/mutasi database atau authenticated browser/HTTP karena fixture/session staging belum tersedia.
- Hasil review auditor: `REVIEW: PASS`; tidak ada required fix. Auditor mengonfirmasi guard placement, perilaku 405/403, writer tripwire, caller scoped, dan smoke tidak false-pass pada area batch ini.
- Risiko sisa:
  - Validasi masih DB-free/source-based; authenticated HTTP dengan session/RBAC nyata, FastCGI/reverse proxy, dan transaksi database belum dibuktikan.
  - `reservation_save()` yang permission-nya ditentukan dari payload masih terpisah; process-all tanpa `outlet_id` dan kontrak writer lain tetap terbuka.
  - Worktree tetap berisi perubahan kumulatif batch sebelumnya.
- Batch berikutnya: auditor mengarahkan pembuatan authenticated HTTP/session integration smoke minimal untuk endpoint reject ini bila fixture/akun staging dapat digunakan; jika belum, pilih hardening writer lifecycle yang paling aman berikutnya. Tetap tanpa License Hub.

## Batch 29 — P0 scoped CSRF reservation_save dengan guard aksi

- Waktu: 2026-09-02 17:34 WIB.
- Prioritas: P0 security/RBAC/integritas transaksi — `reservation_save()` membuat atau mengubah reservasi, line, dan DP awal, tetapi sebelumnya membaca payload sebelum authorization dan belum POST-only/scoped CSRF.
- Ringkasan diskusi auditor/fixer: auditor tidak memilih E2E HTTP karena repository tidak memiliki harness HTTP, fixture session/akun, atau kontrak staging yang aman. Auditor memilih writer reservasi inti ini karena token dan wrapper scoped sudah tersedia. Fixer menerapkan urutan `view RBAC → scoped CSRF/POST → payload/id → create|edit RBAC → writer`, mengalihkan hanya caller `saveReservation()` ke wrapper scoped, dan menambah fake/reflection/tripwire untuk response sukses serta failure path.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/reservation_index.php`
  - `tools/tests/pos_transaction_csrf_smoke.php` (untracked; wajib ikut change/release set)
- Perubahan utama:
  - `reservation_save()` sekarang memeriksa `pos.reservation.index:view` sebelum body, menolak non-POST dengan 405 dan token tidak valid dengan 403, lalu baru memilih permission `create` untuk `id=0` atau `edit` untuk `id>0` sebelum `save_reservation()`.
  - Caller `saveReservation()` memakai `postPosTransactionJson()`; helper `request()` dan caller read-only tetap netral.
  - Smoke membuktikan 28 guarded writers, GET/PUT, enam kelas token invalid, empat kombinasi header/id valid, urutan body/permission, payload, actor employee `1`/user `2`, writer tepat satu kali, dan kontrak JSON sukses.
- Validasi:
  - `php -l` controller, view, dan smoke: lulus.
  - `php tools/tests/pos_transaction_csrf_smoke.php`: 1.689 checks lulus.
  - Regression runtime POS: trigger 247 dan mutation 308 checks lulus.
  - `git diff --check` untuk file target: lulus.
  - Tidak ada query/mutasi database atau E2E HTTP/browser.
- Hasil review auditor: `REVIEW: PASS`; tidak ada required fix. Auditor mengonfirmasi body-derived action permission tetap setelah CSRF, response-completion fake tidak melemahkan test lama, wrapper count tidak menghitung definisi helper, dan tidak ada spillover Batch 29.
- Risiko sisa:
  - Smoke masih DB-free/fake-based; authenticated HTTP dengan session/RBAC nyata, FastCGI/reverse proxy, dan transaksi database belum dibuktikan.
  - Process-all tanpa `outlet_id` dan writer POS order-monitor lain masih terbuka.
  - Worktree berisi perubahan kumulatif batch sebelumnya.
- Batch berikutnya: auditor mengarahkan hardening endpoint mutasi POS order-monitor yang masih tersisa dengan boundary method/scoped CSRF. Tetap tanpa License Hub.

## Batch 30 — P0 scoped CSRF task tunggal POS Order Monitor

- Waktu: 2026-09-02 17:54 WIB.
- Prioritas: P0 security/RBAC/integritas operasional — tiga endpoint task tunggal Order Monitor (`ack-task`, `ready-task`, `checker-task`) sebelumnya menerima mutasi tanpa POST-only dan scoped CSRF.
- Ringkasan diskusi auditor/fixer: auditor memilih batch kecil ini karena seluruh endpoint memakai shared handler dan tiga writer yang sudah memiliki kontrak scope; bulk action dan mutasi tersembunyi `bootstrap_open_tasks()` sengaja tetap dipisahkan. Fixer menambahkan token session-bound khusus Order Monitor, guard terpusat, serta wrapper browser hanya untuk tiga caller task tunggal.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/order_monitor_index.php`
  - `tools/tests/pos_order_monitor_task_csrf_smoke.php` (untracked; wajib ikut change/release set)
- Perubahan utama:
  - `order_monitor()` membuat atau memakai ulang token pada session key khusus setelah permission `view`, lalu merendernya ke halaman.
  - Shared handler menjalankan urutan `edit RBAC → POST/scoped CSRF → payload/task_id → actor/scope → writer`, dengan mapping tetap ke `ack_task`, `ready_task`, dan `checker_task` serta response lama.
  - Caller `*-task` memakai header `X-Pos-Order-Monitor-CSRF` dan `same-origin`; caller bulk `*-order`, route, model production, global CSRF, dan `bootstrap_open_tasks()` tidak diubah.
  - Smoke DB-free baru menguji 405/403, session binding, validasi `task_id`, mapping writer, response, render view, caller bulk, serta tripwire dependency.
- Validasi:
  - `php -l` controller, view, dan smoke: lulus.
  - `php tools/tests/pos_order_monitor_task_csrf_smoke.php`: 570 checks lulus.
  - Regression `pos_transaction_csrf_smoke.php`: 1.689 checks lulus.
  - Regression runtime POS: trigger 247 dan mutation 308 checks lulus.
  - `git diff --check` untuk file target: lulus.
  - Tidak ada query/mutasi database atau authenticated HTTP/browser karena fixture session/RBAC nyata belum tersedia.
- Hasil review auditor: `REVIEW: PASS`; tidak ada required fix. Auditor mengonfirmasi urutan guard, penolakan method/token sebelum writer, mapping tiga aksi, tripwire smoke, dan isolasi caller bulk.
- Risiko sisa:
  - Verifikasi HTTP browser/FastCGI dengan session dan RBAC nyata belum dilakukan; smoke tetap DB-free.
  - Tiga writer bulk Order Monitor masih belum memiliki hardening scoped CSRF.
  - Mutasi tersembunyi pada `bootstrap_open_tasks()` masih terbuka dan membutuhkan keputusan lifecycle terpisah.
- Batch berikutnya: auditor mengarahkan audit/hardening terpisah untuk tiga aksi bulk Order Monitor dengan kontrak method, CSRF, payload, dan scope yang sama; tetap tanpa License Hub.

## Batch 31 — P0 scoped CSRF tiga aksi bulk POS Order Monitor

- Waktu: 2026-09-02 18:12 WIB.
- Prioritas: P0 security/RBAC/integritas operasional — tiga endpoint bulk Order Monitor (`ack-order-station`, `ready-order-station`, `checker-order`) sebelumnya melakukan mutasi tanpa POST-only dan scoped CSRF.
- Ringkasan diskusi auditor/fixer: auditor memilih hardening boundary HTTP/UI karena kontrak model bulk sudah menerima actor dan `monitorScope`; perubahan atomicity model serta lifecycle `bootstrap_open_tasks()` dinilai berisiko dan dipisahkan. Fixer membuat shared handler, parser payload ketat, dan memperluas smoke controller nyata dari tiga menjadi enam writer.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/order_monitor_index.php`
  - `tools/tests/pos_order_monitor_task_csrf_smoke.php` (untracked; wajib ikut change/release set)
- Perubahan utama:
  - Tiga public method bulk kini mendelegasikan tepat sekali ke shared handler dengan urutan `edit RBAC → POST/scoped CSRF → payload → validasi order_id/station → actor/scope → writer`.
  - `order_id` menolak nilai missing/null/zero/negative/zero-padded/float/bool/array/non-numeric/overflow tanpa coercion; station bulk hanya `BAR` atau `KITCHEN`.
  - Caller tiga aksi bulk memakai wrapper `same-origin` dan header `X-Pos-Order-Monitor-CSRF`; tiga caller task tunggal tetap scoped, helper `getJson`/`postJson` tetap netral.
  - Smoke memverifikasi route/model tetap, 405/403/422, mapping writer, actor `4242`, scope, response sukses/gagal, enam caller, serta fake DB/loader/controller tripwire.
- Validasi:
  - `php -l` controller, view, dan smoke: lulus.
  - `php tools/tests/pos_order_monitor_task_csrf_smoke.php`: 1.285 checks lulus.
  - Regression `pos_transaction_csrf_smoke.php`: 1.689 checks lulus.
  - Regression runtime POS: trigger 247 dan mutation 308 checks lulus.
  - `git diff --check`: clean, termasuk pemeriksaan smoke untracked.
  - Tidak ada query/mutasi database atau authenticated HTTP/browser.
- Hasil review auditor: `REVIEW: PASS`; tidak ada required fix. Auditor mengonfirmasi delta terbatas pada controller/view/smoke, route dan model production tidak berubah, urutan guard benar, parser non-koersif, response terminal kompatibel, dan smoke tidak false-pass.
- Risiko sisa:
  - Validasi masih DB-free; authenticated browser/FastCGI dengan session, RBAC, scope pegawai restricted, dan database nyata belum dibuktikan.
  - Atomicity bulk model masih parsial bila salah satu task gagal; sengaja tidak diubah pada batch ini.
  - Mutasi tersembunyi `bootstrap_open_tasks()` pada GET/data masih terbuka dan perlu audit lifecycle terpisah.
- Batch berikutnya: auditor mengarahkan authenticated HTTP integration smoke untuk Order Monitor bila fixture aman tersedia; setelah itu audit atomicity bulk model dan lifecycle `bootstrap_open_tasks()`. Tetap tanpa License Hub.

## Batch 32 — P1 lifecycle read-only dan sinkronisasi task Order Monitor

- Waktu: 2026-09-02 18:34 WIB.
- Prioritas: P1 integritas lifecycle — `order_monitor()` dan polling `order_monitor_data()` sebelumnya memanggil `bootstrap_open_tasks()`, yang dapat memutasi task/order ketika user hanya membaca; beberapa writer juga belum menyinkronkan projection task setelah perubahan order tertentu.
- Ringkasan diskusi auditor/fixer: auditor menolak authenticated HTTP sebagai prioritas karena belum ada harness/fixture aman, lalu memilih purity read path dan penutupan gap sync. Fixer menghapus bootstrap dari read path dan model publik, menambah sync terarah setelah writer sukses, serta membuat smoke DB-free dengan tripwire. Atomicity bulk dan backfill historis tetap dipisahkan.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/controllers/Pos_mobile.php`
  - `application/models/Pos_order_monitor_model.php`
  - `tools/tests/pos_order_monitor_lifecycle_smoke.php` (untracked; wajib ikut change/release set)
- Perubahan utama:
  - GET halaman dan polling Order Monitor hanya membaca options/board payload; `bootstrap_open_tasks()` dihapus dari controller dan model production.
  - Sync ditambahkan setelah standalone confirmed append dengan line baru pada web/mobile/`orders_push` non-confirm, setelah void/refund mobile sukses, dan setelah recovery finalisasi reservasi sukses.
  - Draft biasa, failure, header-only append, dan combined save-confirm tidak mendapat sync tambahan; confirm dan task action internal mempertahankan tepat satu sync pada jalur sukses.
  - Smoke baru menguji purity endpoint baca, payload board, urutan writer/failure, recovery, dan anti-double-sync menggunakan controller/model/DB tripwire.
- Validasi:
  - `php -l` pada 4 file Batch 32: lulus.
  - `php tools/tests/pos_order_monitor_lifecycle_smoke.php`: PASS 38 checks.
  - `php tools/tests/pos_transaction_csrf_smoke.php`: PASS 1.689 checks.
  - `php tools/tests/pos_order_monitor_task_csrf_smoke.php`: PASS 1.285 checks.
  - `php tools/tests/pos_mobile_authorization_smoke.php`: seluruh checks lulus.
  - `git diff --check`: bersih; source production tidak lagi mengandung `bootstrap_open_tasks`.
  - Tidak ada query/mutasi database nyata atau authenticated HTTP/browser.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi delta Batch 32 terisolasi, GET/poll murni read-only, seluruh jalur sync diminta, dan tidak ada regression jelas.
- Risiko sisa:
  - Validasi tetap DB-free; authenticated HTTP/session/RBAC nyata, FastCGI/reverse proxy, dan transaksi database belum dibuktikan.
  - Order historis yang belum memiliki task tidak lagi dibackfill melalui read path dan memerlukan operasi rekonsiliasi terpisah bila diperlukan.
  - Sync projection masih best-effort setelah transaksi writer; atomicity bulk tetap terbuka.
  - Baseline PHP manifest/release masih perlu diselaraskan dengan source/runtime aktual.
- Batch berikutnya: auditor mengarahkan penyelarasan baseline/runtime PHP release secara terpisah, tetap tanpa License Hub.

## Batch 33 — P1 kontrak baseline PHP runtime dan release

- Waktu: 2026-09-02 18:57 WIB.
- Prioritas: P1 release readiness — metadata PHP/dependency belum mencerminkan source aktif dan belum ada kontrak runtime/release yang dapat dipakai sebagai preflight.
- Ringkasan diskusi auditor/fixer: auditor memilih audit kontrak baseline tanpa mengubah versi atau dependency secara spekulatif. Source memakai native PHP 8, CLI staging teramati PHP 8.1.32, sedangkan web/FPM dan worker belum terbukti. Fixer membakukan fakta, batas teknis, kandidat dukungan provisional, matrix extension, kebijakan Composer/lockfile, risiko `fileinfo` WhatsApp, dan checklist preflight dalam dokumentasi serta smoke DB-free.
- File berubah:
  - `docs/README.md`
  - `docs/release_runtime_contract.md` (untracked; wajib ikut change/release set)
  - `tools/tests/release_runtime_contract_smoke.php` (untracked; wajib ikut change/release set)
- Perubahan utama:
  - Menambahkan link kontrak runtime/release pada indeks dokumentasi.
  - Mendokumentasikan floor teknis source `>=8.0`, runtime CLI yang teramati, kandidat `>=8.0 <8.2` yang belum menjadi janji dukungan, status web/FPM/worker, extension, Composer, lockfile, fileinfo, dan release preflight.
  - Menambahkan smoke tanpa bootstrap aplikasi, database, network, Composer/vendor, atau pembuatan artefak; smoke memeriksa PHP, extension inti, metadata Composer, status lockfile, parse 622 file PHP produksi, dan marker syntax/native PHP 8.
- Validasi:
  - `php -l tools/tests/release_runtime_contract_smoke.php`: lulus.
  - `php tools/tests/release_runtime_contract_smoke.php`: PASS 19 checks, 3 warning (`fileinfo` tidak loaded, floor Composer stale, lockfile absent), 3 info.
  - `composer validate --no-check-publish`: exit 0, `composer.json` valid; Composer 2.0.14 mengeluarkan deprecation toolchain dan root warning.
  - `git diff --check` untuk README dan pemeriksaan whitespace file baru: bersih.
  - Tidak ada perubahan Batch 33 pada `composer.json`, `.gitignore`, `composer.lock`, application source, route, schema, atau execution log sebelum pencatatan ini.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi fakta dokumen, smoke tidak false-pass yang jelas/side effect berbahaya, deteksi parse/syntax konservatif, dan scope batch.
- Risiko sisa:
  - Lint/parser PHP 8.1 dan tripwire syntax bukan pengganti matrix aktual PHP 8.0/8.1.
  - Web/FPM dan worker/cron belum diverifikasi; fileinfo masih wajib untuk jalur WhatsApp/MIME.
  - Kebijakan support resmi, lockfile, toolchain Composer reproducible, release manifest/tag, dan bukti web/FPM masih terbuka.
- Batch berikutnya: auditor memilih prioritas lanjutan untuk keputusan support PHP, matrix CLI/web-FPM/worker, serta mekanisme dependency lock dan release reproducible; tetap tanpa License Hub.

## Batch 34 — P0 containment secret WhatsApp dan boundary view

- Waktu: 2026-09-02 19:24 WIB.
- Prioritas: P0 security — `wa/api/env-read` dan settings sebelumnya dapat membocorkan `DB_PASS`/`WA_TOKEN` kepada user `view` atau merender token di HTML; save kosong juga berisiko mereset secret.
- Ringkasan diskusi auditor/fixer: auditor memilih containment secret sebelum fileinfo/release. Fixer membatasi env-read ke `wa.settings:edit` sebelum file access, membuat response status-only, menghapus secret dari semua data view yang relevan, menjaga secret lama pada blank save, mempertahankan baris env lain, dan menambah smoke controller DB-free.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/views/wa/settings.php`
  - `tools/tests/whatsapp_settings_secret_boundary_smoke.php` (untracked; wajib ikut change/release set)
- Perubahan utama:
  - `api_env_read()` edit-only, permission gate sebelum path/file read, response hanya status configured/secret allowlist.
  - settings/dashboard/guide tidak lagi menerima token pada view; token write-only; JS tidak mengisi rahasia dan fallback manual `.env` dihapus.
  - blank secret pada DB setting dan `.env` dipertahankan; non-secret updates tetap bekerja; baris/secret tambahan dipertahankan; error tidak memantulkan content/path.
  - smoke memakai sentinel sintetis dan child process tanpa DB/network/secret runtime.
- Validasi:
  - lint 3 target: lulus.
  - `php tools/tests/whatsapp_settings_secret_boundary_smoke.php`: PASS 20 checks.
  - `php tools/tests/deployment_secret_config_smoke.php`: PASS 39 checks.
  - `php tools/tests/release_runtime_contract_smoke.php`: PASS 19 checks, 3 warning lama (`fileinfo` tidak loaded, floor Composer stale, lockfile absent).
  - `git diff --check` tracked dan no-index file baru: bersih.
  - tidak ada perubahan target pada Composer/lock/routes/schema; tidak ada file dihapus.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`.
- Risiko sisa:
  - E2E HTTP/browser dengan session/RBAC nyata dan filesystem nyata belum dilakukan.
  - `.env` masih penyimpanan secret file karena secret manager migration out of scope.
  - `api_engine_logs` masih mengembalikan baris log mentah kepada `wa.settings:view`; log saat ini hanya menyatakan status konfigurasi, tetapi producer log baru wajib tidak mencetak credential.
  - CSRF global tetap disabled; template/group writer RBAC dan fallback `fileinfo` masih terbuka.
- Batch berikutnya: auditor mengarahkan pembatasan/redaksi engine-log dan smoke duplicate/CRLF `.env`, dengan kandidat prioritas P0 action-RBAC untuk writer `template()`/`group()` sebelum fileinfo; tetap tanpa License Hub.

## Batch 35 — P0 RBAC aksi server-side writer WhatsApp template/group

- Waktu: 2026-09-02 19:37 WIB.
- Prioritas: P0 security/RBAC — POST langsung pada `Whatsapp::template()` dan `Whatsapp::group()` sebelumnya hanya melewati `view`, sehingga user view-only berpotensi create/edit/toggle/delete melalui URL.
- Ringkasan diskusi auditor/fixer: auditor memilih menutup bypass writer yang aktif sebelum containment engine-log. Fixer menambahkan guard aksi setelah pembacaan minimal `action`/`id`, mempertahankan GET view-only dan `send_group:create`, lalu membuat smoke DB-free yang menguji jalur ditolak dan diizinkan.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `tools/tests/whatsapp_template_group_action_rbac_smoke.php` (untracked; wajib ikut change/release set)
- Perubahan utama:
  - Template dan group `save` memakai `create` untuk `id <= 0`, `edit` untuk `id > 0`.
  - `toggle` memerlukan `edit` sebelum SELECT/update; `delete` memerlukan `delete` sebelum operasi DB.
  - Jalur view-only ditolak HTTP 403 sebelum payload mutasi, query/write, session, upload, atau API bot disentuh.
  - GET tetap dapat dirender dengan izin `view`; permission `send_group:create` tetap dipertahankan.
  - Smoke mencakup 8 jalur CRUD, 2 GET, dan `send_group`, plus pemeriksaan dependency/order guard.
- Validasi:
  - `php -l application/controllers/Whatsapp.php`: lulus.
  - `php -l tools/tests/whatsapp_template_group_action_rbac_smoke.php`: lulus.
  - `php tools/tests/whatsapp_template_group_action_rbac_smoke.php`: PASS 63 checks.
  - `php tools/tests/whatsapp_settings_secret_boundary_smoke.php`: PASS 20 checks.
  - `php tools/tests/deployment_secret_config_smoke.php`: PASS 39 checks.
  - `php tools/tests/release_runtime_contract_smoke.php`: PASS 19 checks; warning baseline tetap (`fileinfo` tidak loaded, floor Composer stale, lockfile absent).
  - `git diff --check` tracked dan no-index file baru: bersih; routes tidak berubah.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`.
- Risiko sisa:
  - CSRF form template/group belum diperluas dalam batch ini.
  - `api_engine_logs` masih perlu containment/redaksi; `fileinfo` belum aktif.
  - E2E HTTP/session/RBAC nyata dan query database belum dijalankan.
  - `composer.json` masih meng-underclaim PHP source floor dan `composer.lock` belum tersedia.
- Batch berikutnya: auditor mengarahkan containment `api_engine_logs` agar raw log/path/directory tidak terbuka, disertai smoke redaction dan pemeriksaan duplicate/CRLF `.env`; tetap tanpa License Hub.

## Batch 36 — P0 containment engine-log dan deterministik `.env` WhatsApp

- Waktu: 2026-09-02 19:56 WIB.
- Prioritas: P0 security — `api_engine_logs()` sebelumnya dapat mengembalikan tail log mentah, path absolut, dan directory listing kepada pemegang izin `view`; merge `.env` juga membiarkan duplicate key yang disentuh.
- Ringkasan diskusi auditor/fixer: auditor memilih containment diagnostik sebelum CSRF endpoint engine. Fixer menjadikan endpoint edit-only sebelum path/filesystem, menghapus pembacaan/return raw log dan `ls`, membatasi UI ke status generik, lalu mengkanonisasi hanya managed key `.env` yang disentuh dengan sanitasi CR/LF/NUL.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/views/wa/settings.php`
  - `tools/tests/whatsapp_engine_log_boundary_smoke.php` (untracked; wajib ikut change/release set)
- Perubahan utama:
  - `api_engine_logs()` kini memeriksa `wa.settings:edit` sebelum resolve path/status file dan hanya mengembalikan `ok/status/available/message` generik.
  - `ls`, `tail`, file-content read, nama file, path, listing, PII, dan secret tidak lagi dikirim lewat endpoint; kontrol log di view hanya tampil untuk `$canEdit`.
  - `mergeWaEnvContent()` mempertahankan komentar/key tambahan dan untouched key, menghapus duplicate hanya pada key yang disentuh, memilih kemunculan pertama sebagai posisi kanonis, dan selalu menghasilkan LF.
  - `waEnvUpdatesFromPayload()` dan merge memberi defense-in-depth terhadap CR/LF/NUL serta blank secret tetap berarti tidak berubah.
  - Smoke memakai fixture sintetis/temp, probe filesystem, child process, dan reflection helper; tidak membaca `.env`/log runtime.
- Validasi:
  - lint controller, view, smoke: lulus.
  - `php tools/tests/whatsapp_engine_log_boundary_smoke.php`: PASS 23 checks.
  - `php tools/tests/whatsapp_settings_secret_boundary_smoke.php`: PASS 20 checks.
  - `php tools/tests/deployment_secret_config_smoke.php`: PASS 39 checks.
  - `php tools/tests/release_runtime_contract_smoke.php`: PASS 19 checks; warning baseline (`fileinfo` tidak loaded, floor Composer stale, lockfile absent).
  - `git diff --check` tracked dan no-index file baru: bersih; tidak ada perubahan route/schema/seed/CSRF/start-stop-reset/License Hub.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`.
- Risiko sisa:
  - Smoke dynamic bergantung pada temp directory writable dan belum digabung dengan authenticated HTTP/browser E2E.
  - Detail log sekarang hanya dapat dibaca operator melalui akses server; `fileinfo`, CSRF endpoint engine, runtime web/FPM, Composer floor/lock, dan release reproducibility masih terbuka.
  - Producer log baru tetap wajib tidak menulis secret/PII sensitif.
- Batch berikutnya: auditor mengarahkan Batch 37 P0 POST-only dan scoped CSRF untuk `api_engine_start()`, `api_engine_stop()`, dan `api_session_reset()`; tetap tanpa License Hub.

## Batch 37 — P0 POST-only dan scoped CSRF kontrol engine WhatsApp

- Waktu: 2026-09-02 20:15 WIB.
- Prioritas: P0 security — endpoint `api_engine_start()`, `api_engine_stop()`, dan `api_session_reset()` dapat menjalankan/mematikan proses atau menghapus `auth_info`; sebelumnya hanya memakai RBAC dan CSRF global masih nonaktif.
- Ringkasan diskusi auditor/fixer: auditor memilih satu batch kecil untuk mengunci tiga kontrol engine dengan scope CSRF terpisah. Fixer mengikuti pola POS berupa token sesi 64-hex, header khusus, `hash_equals`, penolakan 405/403, serta menjaga urutan RBAC -> CSRF -> dependency/mutasi. Smoke awal sempat memiliki assertion UI restart yang terlalu sempit; fixer memperbaiki assertion test tanpa memperluas perubahan produksi.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/views/wa/settings.php`
  - `tools/tests/whatsapp_engine_control_csrf_smoke.php` (untracked; wajib ikut change/release set)
- Perubahan utama:
  - Menambahkan scope sesi `wa_engine_control_csrf`, token 32 random bytes/64 hex, header browser `X-Wa-Engine-Control-CSRF`, dan lookup CI `X-Wa-Engine-Control-Csrf`.
  - Ketiga endpoint kini POST-only dan menolak method non-POST dengan 405 serta token missing/malformed/mismatch dengan 403 sebelum filesystem, `exec`, atau DB.
  - Token hanya dibuat/dikirim pada settings view bila pemegang `wa.settings:edit`; Start, Stop, dua request Restart, dan Reset mengirim header scoped.
  - Smoke DB-free menguji RBAC ordering, side-effect boundary, kedua bentuk header, larangan fallback query/body, token rendering, dan seluruh caller UI.
- Validasi yang dijalankan:
  - `php -l application/controllers/Whatsapp.php`: lulus.
  - `php -l application/views/wa/settings.php`: lulus.
  - `php -l tools/tests/whatsapp_engine_control_csrf_smoke.php`: lulus.
  - `php tools/tests/whatsapp_engine_control_csrf_smoke.php`: PASS 160 checks.
  - Regresi `whatsapp_engine_log_boundary_smoke.php`: PASS 23; `whatsapp_settings_secret_boundary_smoke.php`: PASS 20; `whatsapp_template_group_action_rbac_smoke.php`: PASS 63; `deployment_secret_config_smoke.php`: PASS 39.
  - `release_runtime_contract_smoke.php`: PASS 19, dengan warning baseline `fileinfo` belum aktif, floor PHP Composer masih stale, dan lockfile belum tersedia/masih di-ignore.
  - `git diff --check` tracked dan file smoke baru: bersih.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi scope batch, urutan guard, validasi token, non-exposure pada view-only, dan regresi Batch 34-36.
- Risiko sisa:
  - E2E HTTP/browser dengan session, RBAC, web/FPM, dan proxy header nyata belum dijalankan.
  - `api_env_save()` serta writer template/group belum diberi scoped CSRF; global CSRF tetap disabled.
  - Jalur gagal `api_engine_start()` masih dapat menyertakan diagnostik berbasis log dan perlu containment generik tersendiri.
- Batch berikutnya: auditor mengarahkan Batch 38 P0 POST-only dan scoped CSRF untuk `api_env_save()`, termasuk header UI dan smoke DB-free sebelum filesystem/write; tetap tanpa License Hub.

## Batch 38 — P0 POST-only dan scoped CSRF `api_env_save()` WhatsApp

- Waktu: 2026-09-02 20:32 WIB.
- Prioritas: P0 security — `api_env_save()` adalah writer edit-only untuk `.env` konfigurasi sensitif, tetapi sebelumnya hanya memakai RBAC dan CSRF global masih disabled.
- Ringkasan diskusi auditor/fixer: auditor memilih writer `.env` yang paling sempit setelah kontrol engine dikunci. Fixer menambahkan scope CSRF mandiri, guard sebelum raw payload/path/file write, header UI, smoke DB-free, dan hanya mengadaptasi fixture Batch 34 agar caller save positif membawa token. Semantik blank secret, sanitasi CR/LF/NUL, canonical duplicate-key, dan response status-only dipertahankan.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/views/wa/settings.php`
  - `tools/tests/whatsapp_env_save_csrf_smoke.php` (untracked; wajib ikut change/release set)
  - `tools/tests/whatsapp_settings_secret_boundary_smoke.php` (fixture/header/session adaptasi; untracked; wajib ikut change/release set)
- Perubahan utama:
  - Menambahkan scope sesi `wa_env_save_csrf`, token 32 random bytes/64 lowercase hex, header browser `X-Wa-Env-Save-CSRF`, dan lookup CI `X-Wa-Env-Save-Csrf` dengan `hash_equals`.
  - `api_env_save()` kini menjalankan urutan `wa.settings:edit` -> POST/scoped CSRF -> raw JSON -> path/filesystem -> merge -> write; method non-POST ditolak 405 dan token invalid ditolak 403 sebelum side effect.
  - Settings hanya merender token bagi editor dan request env-save mengirim header scoped bersama JSON; tidak memakai token engine-control.
  - Smoke baru menguji RBAC/order, method/token rejection, side-effect boundary, body/query fallback, dua bentuk header, token isolation/rendering, source ordering, dan UI caller.
- Validasi yang dijalankan:
  - lint controller, view, smoke baru, dan smoke secret-boundary: lulus.
  - `php tools/tests/whatsapp_env_save_csrf_smoke.php`: PASS 70 checks.
  - `php tools/tests/whatsapp_settings_secret_boundary_smoke.php`: PASS 20; `whatsapp_engine_log_boundary_smoke.php`: PASS 23; `whatsapp_engine_control_csrf_smoke.php`: PASS 160; `whatsapp_template_group_action_rbac_smoke.php`: PASS 63.
  - `deployment_secret_config_smoke.php`: PASS 39.
  - `release_runtime_contract_smoke.php`: PASS 19 dengan warning baseline `fileinfo` belum aktif, floor PHP Composer stale, dan lockfile absent/ignored.
  - `git diff --check` tracked dan file smoke baru: bersih; tidak ada perubahan route/schema/global config.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi guard-before-body/path/write, isolasi scope, status-only/blank-secret/merge regression, dan tidak ada runtime secret/log yang dibaca.
- Risiko sisa:
  - E2E browser/FPM/proxy header dan persistence session produksi belum diuji.
  - Global CSRF tetap disabled; writer template/group masih memerlukan scoped CSRF terpisah.
  - Smoke baru dan smoke WhatsApp terkait masih untracked dan wajib masuk release change set.
- Batch berikutnya: auditor mengarahkan Batch 39 P0 scoped CSRF untuk mutation writer `template()` dan `group()`, mempertahankan RBAC Batch 35 serta GET view-only; tetap tanpa License Hub.

## Batch 39 — P0 scoped CSRF mutasi form WhatsApp template/group

- Waktu: 2026-09-02 20:50 WIB.
- Prioritas: P0 security — tujuh writer form `Whatsapp::template()`/`group()` (`save`, `toggle`, `delete`, dan `send_group`) sudah memiliki RBAC per aksi dari Batch 35, tetapi belum memiliki CSRF scoped sementara CSRF global tetap disabled.
- Ringkasan diskusi auditor/fixer: auditor memilih batch ini karena template/grup dapat mengubah data, sedangkan `send_group` juga dapat mengirim pesan/media eksternal. Fixer menambahkan satu token session/form khusus, mempertahankan urutan `view gate -> action/id minimal -> RBAC -> CSRF -> payload/DB/upload/API`, serta tidak mengubah permission matrix atau writer lain.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/views/wa/template.php`
  - `application/views/wa/group.php`
  - `tools/tests/whatsapp_template_group_mutation_csrf_smoke.php` (baru; wajib ikut change/release set)
  - `tools/tests/whatsapp_template_group_action_rbac_smoke.php` (adaptasi fixture token/session/output; wajib ikut change/release set)
- Perubahan utama:
  - Menambahkan scope `wa_template_group_mutation_csrf` dengan token `random_bytes(32)`, lowercase 64-hex, session-local, dan verifikasi `hash_equals`.
  - Guard diterapkan tepat pada tujuh action mutation; method selain POST ditolak 405 dan token missing/malformed/mismatch ditolak 403 sebelum payload bisnis, query, upload, writer, flash, atau Bot API.
  - Hidden field token dirender pada 3 form template dan 4 form group, termasuk multipart `send_group`; token hanya disediakan bagi pemegang izin writer. GET view-only tetap tidak mint/render token.
  - Smoke DB-free baru memeriksa ordering, isolation dari scope Batch 37/38, side-effect boundary, token rendering, serta seluruh tujuh form; RBAC smoke tetap memeriksa denial dan permission order Batch 35.
- Validasi yang dijalankan:
  - `php -l` controller, dua view, dan dua smoke: lulus.
  - `whatsapp_template_group_mutation_csrf_smoke.php`: PASS 151 checks.
  - `whatsapp_template_group_action_rbac_smoke.php`: PASS 63 checks.
  - Regresi `whatsapp_engine_control_csrf_smoke.php`: PASS 160; `whatsapp_env_save_csrf_smoke.php`: PASS 70; `whatsapp_engine_log_boundary_smoke.php`: PASS 23; `whatsapp_settings_secret_boundary_smoke.php`: PASS 20.
  - `git diff --check` tracked dan file smoke baru: bersih.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi tujuh urutan guard, perlindungan multipart sebelum upload/query/API, token scoped tanpa fallback query/header/raw-body, rendering seluruh form, serta regresi Batch 35/37/38.
- Risiko sisa:
  - Belum ada E2E browser/HTTP/FPM dengan session nyata dan multipart/bot/database nyata; smoke menggunakan CI/DB doubles.
  - Token masih session-scoped, bukan one-time; kompromi session/XSS berada di luar cakupan CSRF.
  - Global CSRF tetap disabled dan smoke baru masih untracked sampai release change set dikemas.
- Batch berikutnya: auditor mengarahkan P0 scoped CSRF untuk `Whatsapp::report_schedules()`—terutama `send_now` yang dapat memicu pengiriman eksternal—setelah audit action/RBAC dan form/JS terkait.

## Batch 40 — P0 scoped CSRF mutasi jadwal laporan WhatsApp

- Waktu: 2026-09-02 21:07 WIB.
- Prioritas: P0 security — route `wa/template/schedules` menerima empat mutasi (`save_schedule`, `toggle_schedule`, `delete_schedule`, `send_now`) saat CSRF global masih disabled; `send_now` dapat memanggil Bot API serta menulis status/audit pengiriman.
- Ringkasan diskusi auditor/fixer: auditor memilih scoped form token terpisah dan mempertahankan `wa.report_schedule:edit` untuk `send_now`; fixer menerapkan guard setelah pembacaan action/id minimal dan RBAC, sebelum payload, DB, flash, atau `sendWaReportSchedule()`. Tidak ada perubahan matriks permission atau perilaku bisnis schedule.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/views/wa/report_schedule.php`
  - `tools/tests/whatsapp_report_schedule_mutation_csrf_smoke.php` (baru; wajib ikut change/release set)
- Perubahan utama:
  - Menambahkan scope session/form `wa_report_schedule_mutation_csrf` dengan `random_bytes(32)`, lowercase 64-hex, dan `hash_equals`; tidak menerima query/header/raw-body fallback.
  - Keempat action menolak method non-POST dengan 405 dan token invalid dengan 403; `send_now` dijaga sebelum Bot API, `wa_send_log`, dan update status melalui `sendWaReportSchedule()`.
  - Token hanya disediakan bagi role yang memiliki create/edit/delete; hidden field ditambahkan ke empat form dan otomatis ikut AJAX `new FormData(form)` untuk `send_now`.
  - Smoke DB/network/bootstrap-free menguji ordering, side-effect boundary, isolation Batch 37–39, rendering role, empat form, dan FormData caller.
- Validasi yang dijalankan:
  - `php -l` controller, view, dan smoke: lulus.
  - `whatsapp_report_schedule_mutation_csrf_smoke.php`: PASS 140 checks.
  - Regresi `whatsapp_engine_control_csrf_smoke.php`: PASS 160; `whatsapp_env_save_csrf_smoke.php`: PASS 70; `whatsapp_engine_log_boundary_smoke.php`: PASS 23; `whatsapp_settings_secret_boundary_smoke.php`: PASS 20; `whatsapp_template_group_mutation_csrf_smoke.php`: PASS 151; `whatsapp_template_group_action_rbac_smoke.php`: PASS 63.
  - `git diff --check` tracked dan file smoke baru: bersih.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi ordering empat action, guard sebelum `sendWaReportSchedule()`, strict scoped token, form/FormData, dan tidak ada perubahan di luar scope.
- Risiko sisa:
  - Belum ada E2E browser/HTTP/FPM dengan session, database, dan Bot API nyata; smoke menggunakan doubles.
  - Token session-scoped bukan one-time; global CSRF tetap disabled dan writer WhatsApp lain di luar batch belum seluruhnya dikunci.
- Batch berikutnya: auditor mengarahkan scoped CSRF untuk mutation WhatsApp broadcast yang menulis queue/status atau dapat memicu pengiriman.

## Batch 41 — P0 scoped CSRF mutasi dan dispatch WhatsApp broadcast

- Waktu: 2026-09-02 21:46 WIB.
- Prioritas: P0 security — `broadcast_delete()` masih dapat dipanggil melalui GET, `broadcast_deactivate()` belum memiliki CSRF scoped, dan `api_broadcast_start()`/bulk manual masih menggunakan dispatch GET tanpa token saat CSRF global disabled. Jalur ini dapat mengubah queue/status/log atau memicu pengiriman eksternal.
- Ringkasan diskusi auditor/fixer: auditor memilih satu kontrak scoped untuk mutation broadcast dan dispatch. Fixer menerapkan guard setelah RBAC dan sebelum payload, target DB, upload, flash, lock, atau Bot API; delete caller diubah menjadi POST form; dua caller dispatch diubah menjadi POST + dedicated header. Sesi fixer sempat tersendat saat mencetak diff besar, sehingga smoke test baru diselesaikan secara terkontrol oleh main agent tanpa memperluas production scope. Auditor kemudian melakukan review read-only final atas diff dan hasil validasi.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/views/wa/broadcast.php`
  - `application/views/wa/broadcast_form.php`
  - `application/views/wa/broadcast_detail.php`
  - `application/views/wa/manual.php`
  - `tools/tests/whatsapp_broadcast_mutation_csrf_smoke.php` (baru; wajib ikut change/release set)
- Perubahan utama:
  - Menambahkan scope session/form/header `wa_broadcast_mutation_csrf`, token `random_bytes(32)` lowercase 64-hex, session-local, dan verifikasi `hash_equals`.
  - Mengunci `broadcast_create`, `broadcast_edit`, `broadcast_delete`, `broadcast_deactivate`, serta hanya branch `delivery_mode=bulk` pada `manual()` dengan POST + scoped form token; non-POST ditolak 405 dan token invalid ditolak 403.
  - `api_broadcast_start()` menjadi POST-only dan menerima token hanya dari header kanonis `X-Wa-Broadcast-Csrf`; query retry tetap dipertahankan dan tidak digunakan sebagai token fallback.
  - Token dirender hanya pada view writer yang relevan; delete list/detail menjadi form POST; broadcast detail dan manual bulk mengirim POST + header token.
  - Initial RBAC dispatch dan pemeriksaan izin berdasarkan tipe queue tetap dipertahankan; tidak ada perubahan route, schema, seed, matriks permission, circuit breaker, queue model, Bot API, atau kebijakan upload.
- Validasi yang dijalankan:
  - `php -l` controller, empat view, dan smoke baru: lulus.
  - `php tools/tests/whatsapp_broadcast_mutation_csrf_smoke.php`: PASS 157 checks, DB/bootstrap/network/secret-free.
  - Regresi lulus: report schedule 140; engine control 160; env save 70; engine log boundary 23; settings secret boundary 20; template/group mutation 151; template/group action RBAC 63.
  - `git diff --check` tracked Batch 41 scope dan smoke baru: lulus.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi urutan RBAC -> CSRF -> dependency, header-only dispatch, seluruh caller POST, delete tanpa GET anchor, dan smoke test yang bermakna serta tidak menyentuh runtime sensitif.
- Risiko sisa:
  - Belum ada E2E browser/HTTP/FPM dengan session, database, proxy header, dan Bot API nyata; jalur dispatch valid tidak dieksekusi melewati circuit breaker outbound yang sedang disabled.
  - Token masih session-scoped, bukan one-time; global CSRF tetap disabled dan writer WhatsApp lain di luar batch masih menjadi pekerjaan berikutnya.
  - Smoke baru masih untracked sampai masuk change/release set.
- Batch berikutnya: auditor diminta memilih prioritas P0 berikutnya; kandidat terdekat adalah scoped CSRF untuk `api_log_retry()` dan caller-nya setelah audit action/RBAC.

## Batch 42 — P0 scoped CSRF dan least-privilege RBAC `api_log_retry()`

- Waktu: 2026-09-02 22:26 WIB.
- Prioritas: P0 security — `api_log_retry()` sebelumnya menerima GET tanpa token dan `wa.log:view` dapat menjadi jalur retry outbound; tiga caller first-party juga belum mengirim CSRF scoped. Risiko terbesar adalah retry pesan GROUP oleh pemegang izin baca log serta CSRF terhadap pengiriman eksternal.
- Ringkasan diskusi auditor/fixer: auditor memilih penguncian endpoint retry dengan POST/header-only dan pemisahan izin aksi berdasarkan sumber log. Fixer menerapkan token session-scoped terpisah, exact RBAC (`wa.group:create` untuk GROUP, `wa.manual:create` untuk personal, `wa.broadcast:edit` untuk membuka detail broadcast), serta menghapus capability GROUP yang tidak terpakai dari view manual. Auditor awal meminta behavior smoke yang lebih kuat; fixer menambahkannya. Auditor final menyatakan PASS tanpa required fixes.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/views/wa/dashboard.php`
  - `application/views/wa/log.php`
  - `application/views/wa/manual.php`
  - `tools/tests/whatsapp_log_retry_csrf_rbac_smoke.php` (tracked/staged sebagai file baru; wajib masuk change/release set)
- Perubahan utama:
  - Menambahkan scope sesi `wa_log_retry_csrf` dengan token `random_bytes(32)`, lowercase 64-hex, header kanonis `X-Wa-Log-Retry-Csrf`, dan verifikasi `hash_equals`; query, form, JSON, serta raw body tidak menjadi fallback.
  - `api_log_retry()` kini POST-only, menjalankan coarse writer gate lalu CSRF sebelum baca DB/rekonstruksi pesan/circuit breaker/Bot API/log writer.
  - `wa.log:view` tidak lagi memberi hak retry; klasifikasi BROADCAST dilakukan sebelum `group_jid`, broadcast hanya membuka detail, GROUP dan personal memakai permission terpisah, dan GROUP tetap tidak bergantung pada personal circuit breaker.
  - Dashboard, log, dan manual mengirim POST + `same-origin` + dedicated header; tombol/token hanya dirender untuk capability sumber yang sesuai. `manual()` hanya menerbitkan token pada tab single bagi `wa.manual:create`.
- Validasi yang dijalankan:
  - `php -l` controller, tiga view, dan smoke baru: lulus.
  - Smoke behavior-level DB/network/bootstrap-free: PASS 158 checks.
  - Regresi lulus: broadcast 157; report schedule 140; template/group mutation 151; template/group action RBAC 63; engine control 160; env save 70; engine log boundary 23; settings secret boundary 20.
  - `git diff --check` dan `git diff --cached --check`: lulus; hanya smoke baru yang staged, file lain staged tidak ada.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi behavior cases benar-benar dieksekusi, urutan guard, klasifikasi, RBAC least-privilege, ketiga caller, dan tracking smoke.
- Risiko sisa:
  - Belum ada E2E browser/HTTP/FPM dengan session, database, proxy header, dan Bot API nyata; khususnya retry GROUP dan redirect detail broadcast masih perlu probe staging aman.
  - Token masih session-scoped, bukan one-time; global CSRF tetap disabled dan writer WhatsApp lain di luar batch masih perlu dikunci.
- Batch berikutnya: auditor memilih prioritas P0 berikutnya, dengan kandidat terdekat direct manual single-send (`api_send_test`) setelah penguncian retry selesai; tetap tanpa License Hub.

## Batch 43 — P0 scoped CSRF writer `Whatsapp::settings()`

- Waktu: 2026-09-02 22:44 WIB.
- Prioritas: P0 security — form settings mengubah endpoint Bot, token write-only, dan path Node, tetapi sebelumnya hanya memiliki RBAC tanpa CSRF scoped. Nilai tersebut berada pada trust boundary request Bot dan engine.
- Ringkasan diskusi auditor/fixer: auditor memilih `settings()` sebagai prioritas yang lebih kritis daripada direct send karena konfigurasi ini memengaruhi jalur Bot/engine. Fixer menambahkan token session/form khusus, guard sebelum input bisnis dan DB, serta rendering editor-only. Validasi awal menemukan fixture secret-boundary lama belum mengirim token; fixer menambahkan sentinel CSRF sintetis pada fixture tanpa mengubah production behavior. Auditor final menyatakan PASS.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/views/wa/settings.php`
  - `tools/tests/whatsapp_settings_mutation_csrf_smoke.php` (tracked/staged sebagai file baru)
  - `tools/tests/whatsapp_settings_secret_boundary_smoke.php` (fixture regression; tracked/staged sebagai file baru)
- Perubahan utama:
  - Menambahkan scope sesi/form `wa_settings_mutation_csrf` dengan `random_bytes(32)`, lowercase 64-hex, dan `hash_equals`; token tidak memakai scope engine/env/log sebelumnya.
  - `settings()` mempertahankan view/edit RBAC, lalu menjalankan POST-only scoped form CSRF sebelum membaca field, mengecek schema, atau update `wa_session`; GET tetap read-only.
  - Token hanya dimintakan dan dirender bagi editor settings. Semantik token Bot kosong tetap mempertahankan nilai lama; validasi URL, `node_path`, `.env`, engine runner, route, dan schema tidak diubah.
  - Fixture secret-boundary diberi token sentinel agar regression test tetap menguji preserve-old-value dan generic flash setelah guard baru.
- Validasi yang dijalankan:
  - `php -l` controller, view, smoke baru, dan fixture: lulus.
  - Smoke settings mutation: PASS 60 checks; secret boundary: PASS 20; env-save: PASS 70; log-retry: PASS 158; engine-control: PASS 160; engine-log-boundary: PASS 23.
  - `git diff --check` dan `git diff --cached --check`: lulus. Cached scope Batch 43 tepat dua smoke file; perubahan production tetap unstaged karena worktree telah memiliki perubahan sebelumnya.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi urutan view RBAC -> edit RBAC -> CSRF -> input/schema/DB, token isolation, editor-only rendering, blank-token preservation, dan smoke behavior-level tanpa DB/network/bootstrap.
- Risiko sisa:
  - Belum ada integrasi browser/full-stack dengan session, DB staging, FPM, proxy header, atau Bot/engine nyata; validasi deployment tetap diperlukan.
  - Global CSRF tetap disabled dan writer lain di luar batch masih perlu hardening; token settings session-scoped bukan one-time.
- Batch berikutnya: auditor diminta memilih P0 berikutnya, kandidat terdekat branch single-send `Whatsapp::manual()` lalu `api_send_test()`; tetap tanpa License Hub.

## Batch 44 — P0 scoped CSRF single-send `Whatsapp::manual()`

- Waktu: 2026-09-02 22:57 WIB.
- Prioritas: P0 security — form single-send manual langsung memanggil Bot API dan menulis log, tetapi sebelumnya hanya jalur bulk yang memiliki CSRF scoped. Ini membuka risiko CSRF pengiriman personal pada sesi pengguna berizin.
- Ringkasan diskusi auditor/fixer: auditor memilih writer personal langsung setelah settings dikunci. Fixer menambahkan token single-send yang terpisah dari token broadcast, guard setelah RBAC dan `delivery_mode` tetapi sebelum circuit breaker, upload, parsing target, Bot API, dan log. Bulk tetap memakai kontrak broadcast. Auditor final menyatakan PASS tanpa required fixes.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/views/wa/manual.php`
  - `tools/tests/whatsapp_manual_single_send_csrf_smoke.php` (tracked/staged sebagai file baru)
- Perubahan utama:
  - Menambahkan scope sesi/form `wa_manual_single_send_csrf` dengan `random_bytes(32)`, lowercase 64-hex, dan `hash_equals`; tidak memakai token broadcast/log/settings/env.
  - `manual()` mempertahankan `wa.manual:create`, membaca mode minimal, lalu mewajibkan POST + token single untuk mode non-bulk; mode bulk tetap memakai token broadcast.
  - Token single hanya dimintakan pada GET tab single bagi editor dan hidden field ditambahkan ke `waManualForm`; token single tidak diterima untuk bulk.
- Validasi yang dijalankan:
  - `php -l` controller, view, dan smoke: lulus.
  - Smoke behavior-level tanpa DB/network/bootstrap/secret: PASS 69 checks, termasuk view-only, valid/malformed/mismatch/foreign token, method rejection, early side-effect boundary, valid mock sender sekali, serta bulk isolation.
  - Regresi lulus: broadcast 157; log-retry/RBAC 158; settings mutation 60; settings secret boundary 20; env-save 70; engine-control 160; engine-log-boundary 23.
  - `git diff --check` dan `git diff --cached --check`: lulus.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi scoped token, guard sebelum seluruh side effect, editor-only mint, isolasi bulk, dan tidak ada regression route/schema/RBAC/global-CSRF.
- Risiko sisa:
  - Token masih reusable sampai rotasi/session expiry dan bukan kontrol idempotency; duplicate submit oleh user tetap perlu dikendalikan terpisah.
  - Belum ada browser/full-stack HTTP/FPM dengan session, upload, DB, dan Bot API nyata.
- Batch berikutnya: auditor memilih P0 scoped header-only CSRF untuk `api_send_test()`; tetap tanpa perubahan global CSRF atau License Hub.

## Batch 45 — P0 scoped header-only CSRF `Whatsapp::api_send_test()`

- Waktu: 2026-09-02 23:11 WIB.
- Prioritas: P0 security — endpoint JSON `api_send_test()` dapat mengirim pesan personal dan menulis send log, sementara CSRF global disabled dan jalur single-send form baru saja dikunci. Circuit breaker bukan pengganti request guard karena dapat dibuka kembali.
- Ringkasan diskusi auditor/fixer: auditor memilih endpoint test sebagai writer outbound terakhir pada alur settings. Fixer menambahkan token header-only session-scoped terpisah, memperbarui caller settings, dan membuat smoke behavior-level. Auditor final menyatakan PASS tanpa required fixes.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/views/wa/settings.php`
  - `tools/tests/whatsapp_api_send_test_csrf_smoke.php` (tracked/staged sebagai file baru)
- Perubahan utama:
  - Menambahkan scope `wa_send_test_csrf`, token `random_bytes(32)` lowercase 64-hex, dan `hash_equals`; tidak memakai token settings/manual/broadcast/log/env.
  - `api_send_test()` kini mempertahankan `wa.settings:edit`, lalu mewajibkan POST dan header `X-Wa-Send-Test-Csrf` sebelum circuit breaker, raw JSON, validasi, Bot API, atau log writer. Query/form/raw body tidak menjadi fallback.
  - Settings hanya merender token/kontrol test bagi editor; fetch mempertahankan payload/response dan memakai POST, `same-origin`, serta header dedicated.
- Validasi yang dijalankan:
  - `php -l` controller, view, dan smoke: lulus.
  - Smoke behavior-level tanpa DB/network/bootstrap/secret: PASS 91 checks, termasuk RBAC, method/token rejection, header normalization, isolation, side-effect boundary, circuit breaker, dan valid mock sender sekali.
  - Regresi lulus: manual single 69; broadcast 157; log-retry 158; settings mutation 60; settings secret boundary 20; env-save 70; engine-control 160; engine-log-boundary 23.
  - `git diff --check` dan `git diff --cached --check`: lulus.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi urutan RBAC -> POST/header-only CSRF -> breaker/body/Bot/log, editor-only UI, token isolation, dan tidak ada perubahan route/schema/global-CSRF/circuit-breaker.
- Risiko sisa:
  - Smoke masih memakai CI-compatible doubles; authenticated browser POST melalui web server/FastCGI/header path tetap perlu diuji saat deployment.
  - Token session-scoped reusable dan bukan idempotency control; personal outbound tetap circuit-breaker disabled.
- Batch berikutnya: auditor memilih audit P0 terpisah untuk `api_schedule_run()` yang masih memiliki kontrak token query/service; jangan mencampurkannya dengan CSRF browser batch ini.

## Batch 46 — P0 CLI-only `Whatsapp::api_schedule_run()`

- Waktu: 2026-09-02 23:24 WIB.
- Prioritas: P0 security — `api_schedule_run()` adalah writer outbound terjadwal tetapi sebelumnya menerima HTTP anonymous dengan token query/header yang berbagi token Bot WA. URL token berisiko masuk access log/history dan endpoint dapat dipicu dari luar sesi.
- Ringkasan diskusi auditor/fixer: auditor menetapkan scheduler sebagai service/cron boundary, bukan browser CSRF. Fixer menjadikan endpoint CLI-only, menghapus pembacaan token lama, memperketat anonymous allowlist hanya untuk mempertahankan `api_group_command`, dan mengubah dokumentasi cron ke CLI lokal. Auditor final menyatakan PASS tanpa required fixes.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/core/MY_Controller.php`
  - `application/views/wa/guide.php`
  - `application/views/wa/report_schedule.php`
  - `tools/tests/whatsapp_api_schedule_run_cli_smoke.php` (tracked/staged sebagai file baru)
- Perubahan utama:
  - `api_schedule_run()` memeriksa `is_cli_request()` sebagai guard pertama dan menolak HTTP dengan 404 sebelum scheduler/DB/Bot/log; query/header token dan dependensi token Bot bersama dihapus dari endpoint.
  - `api_schedule_run` dihapus dari anonymous HTTP exception; akses CLI tetap didukung dan `api_group_command` tidak berubah.
  - Panduan dan petunjuk schedule memakai `php index.php whatsapp api_schedule_run` dari root aplikasi tanpa URL atau token.
- Validasi yang dijalankan:
  - `php -l` controller, core, dua view, dan smoke: lulus.
  - Smoke behavior-level tanpa bootstrap/DB/network/secret: PASS 30 checks, mencakup HTTP GET/POST + token lama, CLI once/no-token, auth allowlist, dan side-effect boundary.
  - Regresi lulus: auth/division 39; report-schedule CSRF 140; api-send-test 91; manual single 69; log-retry 158.
  - `git diff --check` dan `git diff --cached --check`: lulus.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi CLI-first guard, scheduler sekali pada CLI, anonymous `api_group_command` tetap, route unchanged, dan dokumentasi tanpa token URL.
- Risiko sisa:
  - Smoke memakai CI/DB doubles; cron production/staging tetap perlu dijalankan sekali dengan jadwal due yang terkontrol untuk memastikan konfigurasi PHP/cron.
  - Atomic claim/lease dan actor/audit service scheduler belum diubah; paralel cron masih menjadi batch integrity terpisah.
- Batch berikutnya: auditor memilih P0 integrity atomic claim/lease pada `runDueWaReportSchedules()`; jangan mencampurkannya dengan kontrak CLI batch ini.

## Batch 47 — P0 atomic claim/lease scheduler laporan WA

- Waktu: 2026-09-02 23:39 WIB.
- Prioritas: P0 integrity — dua proses cron dapat memilih jadwal due yang sama sebelum `last_run_at` diperbarui setelah Bot API, sehingga satu jadwal berpotensi mengirim laporan ganda.
- Ringkasan diskusi auditor/fixer: auditor menetapkan claim/lease atomik sebagai batch terpisah setelah trigger HTTP ditutup. Karena `last_run_at` tidak cukup untuk membuktikan owner, fixer menambah token claim dan waktu lease nullable, mengunci klaim dengan conditional update, dan membatasi finalisasi pada token owner. Auditor final menyatakan PASS tanpa required fixes.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `sql/2026-09-02a_wa_report_schedule_claim_lease.sql` (migration idempoten)
  - `sql/2026-08-15b_wa_report_schedule.sql`
  - `sql/2026-08-17e_pos_whatsapp_runtime_schema_preflight.sql`
  - `tools/tests/whatsapp_report_schedule_claim_lease_smoke.php` (tracked/staged sebagai file baru)
- Perubahan utama:
  - Menambahkan `run_claim_token CHAR(32) NULL` dan `run_claimed_at DATETIME NULL`; migration harus dijalankan setelah base table dan menggunakan `ADD COLUMN IF NOT EXISTS`.
  - Runner hanya boleh mengirim setelah conditional claim dengan syarat aktif, due, belum terkirim hari ini, retry eligible, dan claim kosong/kedaluwarsa menghasilkan `affected_rows() === 1`.
  - Finalisasi success/failure memakai `id + run_claim_token`, mengosongkan kedua kolom claim, dan menolak stale owner yang lease-nya telah direbut.
  - Lease/retry 10 menit, CLI scheduler Batch 46, dan manual `send_now` tetap dipertahankan; tidak ada perubahan route/global auth atau scheduler content.
- Validasi yang dijalankan:
  - `php -l` controller dan smoke: lulus.
  - Smoke behavior-level in-memory: PASS 27 checks, mencakup race dua runner, active/expired lease, stale finalizer, owner-only success/failure, retry failure, manual/CLI/SQL source checks.
  - Regresi lulus: CLI scheduler 30; report mutation 140; api send 91; manual single 69; log retry 158.
  - `git diff --check` dan `git diff --cached --check`: lulus.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi predicate grouping, claim CAS, token-owner finalisasi, sinkronisasi tiga sumber schema, dan manual `send_now` claim-free.
- Risiko sisa:
  - Lease 10 menit memberi delivery at-least-once, bukan exactly-once: runner yang tersendat dapat selesai mengirim setelah lease direbut runner lain. Idempotency eksternal Bot diperlukan untuk menutup crash/timeout ambiguity.
  - Migration memerlukan DB yang mendukung `ADD COLUMN IF NOT EXISTS`; perlu diverifikasi pada versi DB staging/target.
- Batch berikutnya: auditor memilih Batch 48 untuk idempotency outbound dengan `schedule_id` + tanggal bisnis/receipt bila kontrak Bot mendukung; keputusan ini dipisahkan dari claim lease.

## Batch 48 — P1-02 Dashboard Component mismatch nilai FIFO

- Waktu: 2026-09-02 23:57 WIB.
- Prioritas: P1 integrity/observability — dashboard sebelumnya hanya menghitung mismatch kuantitas dan dapat menampilkan Clear meskipun `monthly_lot_value_gap` berbeda.
- Ringkasan diskusi auditor/fixer: auditor menolak idempotency Bot sebagai batch langsung karena kontrak `/internal/send-group` belum memiliki idempotency key/receipt. Fixer menghubungkan dashboard ke flag kanonis `Production_model`. Review pertama menemukan scope creep berupa limit 2000 menjadi 300; fixer memulihkan limit/argumen lama dan memperkuat smoke. Auditor final menyatakan PASS tanpa required fixes.
- File berubah:
  - `application/controllers/Dashboard.php`
  - `application/views/dashboard/index.php`
  - `tools/tests/dashboard_component_value_mismatch_smoke.php` (tracked/staged sebagai file baru)
- Perubahan utama:
  - Menggunakan `is_match`, `qty_is_match`, `has_lot_value_mismatch`, dan `monthly_lot_value_gap` dari model tanpa tolerance baru.
  - Menghitung total mismatch row satu kali serta breakdown qty/value; mismatch combined masuk kedua breakdown.
  - Menampilkan nominal gap, label “Qty sama, nilai FIFO berbeda”, dan link reconcile yang ada; Clear/Aman hanya ketika kedua breakdown nol.
  - Mempertahankan material summary dengan limit 2000, destination `ALL`, diagnostics `true`; component result tetap limit 2000 agar tidak mengubah coverage/perilaku lama.
- Validasi yang dijalankan:
  - `php -l` controller, view, dan smoke: lulus.
  - Smoke DB/network/bootstrap-free: PASS 19 checks, termasuk qty-only, value-only, combined, gap tepat tolerance, link/Clear, material contract, dan mismatch pada sorted row 305.
  - Regresi scheduler claim/lease: PASS 27 checks.
  - `git diff --check` dan `git diff --cached --check`: lulus.
- Hasil review auditor: awal `NEEDS_FIX` untuk limit/scope creep; setelah fixer memulihkan limit dan menambah coverage, final `REVIEW: PASS`; `REQUIRED_FIXES: none`.
- Risiko sisa:
  - Smoke masih fake-model/isolated; production-like DB fixture >300 component rows tetap berguna sebagai integration coverage.
  - Batas kontrak lama 2000 baris tetap berlaku; repair enam mismatch nilai historis dan idempotency outbound tidak termasuk batch.
- Batch berikutnya: auditor memilih prioritas P0/P1 berikutnya; idempotency Bot tetap memerlukan kontrak provider/engine yang jelas.

## Batch 49 — P0 menonaktifkan mutasi rekening dari command grup WA

- Waktu: 2026-09-03 00:07 WIB.
- Prioritas: P0 integrity — callback grup dapat mem-posting `IN/OUT/TRANSFER` tanpa identitas pengirim WhatsApp yang terpetakan ke user Finance, RBAC Finance, approval, atau aktor audit; writer memakai actor `0`.
- Ringkasan diskusi auditor/fixer: auditor memilih deny-by-default untuk mutasi rekening grup sebagai kontrol aman yang tidak memerlukan kontrak Bot baru. Fixer menambahkan rejection setelah autentikasi/token dan mapping grup aktif tetapi sebelum parser, lookup rekening, load `Purchase_model`, query/write, atau audit mutation. Menu WA dan panduan menghapus instruksi input/transfer, sementara report `mutasi` read-only dipertahankan. Auditor menyatakan PASS tanpa required fixes.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/views/wa/report_schedule.php`
  - `tools/tests/whatsapp_group_command_mutation_disabled_smoke.php` (tracked/staged sebagai file baru)
- Perubahan utama:
  - Command ter-normalisasi `mutasi in`, `mutasi out`, dan `mutasi transfer` langsung menghasilkan pesan bahwa mutasi grup dinonaktifkan dan harus dilakukan melalui Finance.
  - Help/menu WA tidak lagi mengajarkan format posting mutasi; kartu `Mutasi Rekening` di report schedule tetap hanya berisi query laporan hari ini/kemarin.
  - Tidak mengubah kontrak token/HTTP callback, parser/helper lama, schema, RBAC matrix, data, atau Bot engine.
- Validasi yang dijalankan:
  - `php -l` controller, view, dan smoke: lulus.
  - Smoke behavior-level DB/network/bootstrap-free: PASS 18 checks untuk tiga mode mutasi, prefix/spasi/case normalization, no mutation dependency/write, report read-only, serta menu/UI copy.
  - Regresi lulus: scheduler CLI 30; claim/lease 27; report mutation CSRF 140; api send 91; manual 69; log retry 158.
  - `git diff --check` dan `git diff --cached --check`: lulus.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi active-group mapping/token tetap dipertahankan, guard sebelum writer, report mutasi read-only, dan tidak ada perluasan scope.
- Risiko sisa:
  - Helper parser mutasi lama tetap ada sebagai dead code private yang tidak terjangkau dari endpoint grup; perlu dihapus/retire dalam cleanup terpisah agar tidak tidak sengaja diaktifkan kembali.
  - `api_group_command` masih memakai token query/shared Bot token dan belum POST/header-only; itu menjadi batch P0 berikutnya.
- Batch berikutnya: auditor memilih hardening `api_group_command`—POST-only, token header-only, serta kontrak credential service terpisah—tanpa mencampurkan desain pairing sender/RBAC.

## Batch 50 — P0 service-auth callback `wa-engine -> Finance`

- Waktu: 2026-09-03 00:30 WIB.
- Prioritas: P0 security — `api_group_command` adalah endpoint anonymous service callback yang sebelumnya menerima token query/header legacy dan memakai shared `wa_session.bot_api_token`; query token dapat masuk telemetry/proxy log dan credential tidak dapat dirotasi per arah.
- Ringkasan diskusi auditor/fixer: auditor menetapkan kontrak service-to-service terpisah, bukan browser CSRF. Fixer membuat endpoint POST-only, header-only, fail-closed dari `FINANCE_WA_ENGINE_COMMAND_TOKEN`, memperketat anonymous bypass, mengubah caller Node dan dokumentasi provisioning. Review pertama menemukan redirect Node dapat membocorkan header credential; fixer menambahkan `redirect: 'error'` dan regression simulation. Auditor final menyatakan PASS.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `application/core/MY_Controller.php`
  - `wa-engine/index.js`
  - `wa-engine/.env.example` (template non-secret)
  - `docs/wa_group_command_service_auth_runbook.md` (runbook provisioning/cutover non-secret)
  - `tools/tests/whatsapp_group_command_service_auth_smoke.php`
  - `tools/tests/wa_engine_group_command_service_auth_smoke.js`
  - `tools/tests/whatsapp_group_command_mutation_disabled_smoke.php`
  - `tools/tests/whatsapp_api_schedule_run_cli_smoke.php`
- Perubahan utama:
  - Finance menerima hanya POST + `X-Finance-Group-Command-Token`, expected token dari env `FINANCE_WA_ENGINE_COMMAND_TOKEN`, nonempty fail-closed; query token dan `X-Sync-Token` ditolak sebelum raw body/group DB/report.
  - Anonymous `MY_Controller` bypass hanya untuk kombinasi tepat WhatsApp/`api_group_command` + POST; CLI dan kontrak endpoint lain dipertahankan.
  - `wa-engine` memakai credential env khusus, exact `FINANCE_COMMAND_URL`, JSON payload, header baru saja, tanpa token URL/legacy header; redirect fetch disetel `error` agar credential tidak diteruskan lintas-origin.
  - Runbook mewajibkan provision secret process-only di luar web root untuk PHP-FPM dan wa-engine, coordinated restart/cutover, serta URL final tanpa redirect; nilai secret aktual tidak dibuat/diubah.
  - Fixture scheduler lama disesuaikan agar preserved anonymous `api_group_command` case memakai POST, sementara GET denial diuji oleh smoke service-auth.
- Validasi yang dijalankan:
  - `php -l` seluruh PHP terkait: lulus; `node --check` engine/smoke: lulus.
  - Service-auth PHP: PASS 31; Node caller/redirect: PASS 16; mutation deny: PASS 18; scheduler CLI: PASS 30.
  - Regresi lulus: claim/lease 27; report mutation 140; api send 91; manual 69; log retry 158; settings 60/20; env 70; engine control 160; engine log 23.
  - `composer validate --no-check-publish`: composer.json valid dengan deprecation warnings baseline; `git diff --check` dan `git diff --cached --check`: lulus.
- Hasil review auditor: awal `NEEDS_FIX` untuk redirect credential; setelah fixer menambah `redirect: 'error'` dan smoke 16 checks, final `REVIEW: PASS`; `REQUIRED_FIXES: none`.
- Risiko sisa:
  - Callback fail-closed sampai env token yang sama diprovision pada PHP-FPM dan wa-engine; cutover/restart serta satu health-check authenticated perlu dilakukan operasional.
  - Belum ada E2E FastCGI/reverse proxy/active group; HMAC/timestamp/nonce, sender pairing/RBAC, rate limit, dan replay protection di luar batch.
  - Arah Finance -> engine `callBotApi()` masih kontrak shared `WA_TOKEN`/`X-Sync-Token` dan sengaja belum diubah.
- Batch berikutnya: auditor memilih hardening arah Finance -> engine (`callBotApi()` dan internal engine API) dengan header-only credential terpisah, lalu signed request bila diperlukan; tetap tanpa License Hub.

## Batch 51 — P1 SSRF dan egress boundary `callBotApi()` WhatsApp

- Waktu: 2026-09-03 00:48 WIB.
- Prioritas: P1 security — URL Bot API tersimpan dan konfigurasi settings perlu dibatasi agar tidak menjadi SSRF/egress path, termasuk redirect dan proxy environment yang dapat menerima credential outbound.
- Ringkasan diskusi auditor/fixer: auditor menetapkan batch sempit tanpa migrasi credential. Fixer menambahkan validator URL loopback ber-port, validasi settings sebelum schema/DB, validasi stored URL sebelum `curl_init`, dan mematikan redirect. Review pertama meminta hardening tambahan karena cURL dapat menghormati proxy environment; fixer menambahkan `CURLOPT_PROXY => ''` dan assertion smoke. Auditor final menyatakan PASS.
- File berubah:
  - `application/controllers/Whatsapp.php`
  - `tools/tests/whatsapp_settings_mutation_csrf_smoke.php`
- Perubahan utama:
  - Hanya menerima `http://127.0.0.1:<port>` atau `http://localhost:<port>`, port 1–65535; `localhost` dinormalisasi ke `127.0.0.1`.
  - URL invalid pada settings ditolak sebelum pemeriksaan schema maupun update DB; URL tersimpan invalid ditolak sebelum `curl_init`.
  - `CURLOPT_FOLLOWLOCATION => false`, `CURLOPT_MAXREDIRS => 0`, dan `CURLOPT_PROXY => ''` mencegah redirect/proxy egress pada call ke engine.
  - Endpoint literal `/internal/*`, method, timeout, header `X-Sync-Token`, dan token query lama dipertahankan; migrasi credential Finance → engine tidak termasuk batch.
- Validasi yang dijalankan:
  - `php -l` controller dan smoke: lulus; `git diff --check` dan `git diff --cached --check`: lulus.
  - Settings boundary smoke: PASS 123 checks, termasuk URL matrix, invalid-before-DB/cURL, no-follow, no-proxy, dan kontrak secret lama.
  - Regresi lulus: service-auth 31; Node service-auth 16; mutation-disabled 18; scheduler CLI 30; claim/lease 27; report schedule 140; API send 91; manual 69; log retry 158; settings-secret 20; env-save 70; engine-control 160; engine-log 23; broadcast 157; template/group CSRF 151; template/group RBAC 63.
  - `composer validate --no-check-publish`: `composer.json` valid dengan deprecation warning baseline.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`. Auditor mengonfirmasi parser loopback/port, urutan validasi, no-follow, no-proxy, dan preservation kontrak outbound.
- Risiko sisa:
  - Assertion proxy masih source-level; integration test dengan proxy sungguhan belum ada.
  - Proses lokal berprivilege yang mengambil alih port loopback masih dapat menerima token; kontrol proses/host berada di luar batch.
  - Arah Finance → engine masih memakai `WA_TOKEN`/`X-Sync-Token`; credential header-only terpisah menjadi batch berikutnya.
- Batch berikutnya: auditor memilih hardening credential arah Finance → engine (`callBotApi()` dan internal engine API), dimulai dari kontrak header-only terpisah dan tanpa License Hub.

## Batch 52 — P1 service-auth API internal `Finance → wa-engine`

- Waktu: 2026-09-03 00:48–01:12 WIB.
- Prioritas: P1 security/readiness — `callBotApi()` masih mengirim `wa_session.bot_api_token` pada query `?token=` dan header `X-Sync-Token`, sementara `wa-engine` menerima credential legacy/fallback development. Credential di URL berisiko masuk telemetry/log dan shared token menyulitkan rotasi per arah.
- Ringkasan diskusi auditor/fixer: auditor menetapkan migrasi header-only ke credential process-only terpisah sebagai batch kecil yang siap dikerjakan. Fixer memindahkan Finance → engine ke `FINANCE_WA_ENGINE_API_TOKEN`/`X-Finance-Wa-Engine-Token`, mengunci gate `/internal/*` engine fail-closed, mengecualikan credential dari `.env` web-root dan launcher PHP, serta menghapus editor/status legacy dari UI/panduan. Review pertama menemukan runbook belum mencakup CLI/cron dan runbook Batch 50 masih menyebut `WA_TOKEN`; fixer memperbaiki dua runbook, menambah assertion smoke, lalu membersihkan blank line EOF. Auditor final menyatakan PASS.
- File berubah (14 file unik):
  - `application/controllers/Whatsapp.php`
  - `wa-engine/index.js`
  - `wa-engine/.env.example`
  - `application/views/wa/settings.php`
  - `application/views/wa/guide.php`
  - `docs/wa_engine_internal_service_auth_runbook.md`
  - `docs/wa_group_command_service_auth_runbook.md`
  - `tools/tests/whatsapp_engine_api_service_auth_smoke.php`
  - `tools/tests/wa_engine_internal_service_auth_smoke.js`
  - `tools/tests/wa_engine_group_command_service_auth_smoke.js`
  - `tools/tests/whatsapp_group_command_service_auth_smoke.php`
  - `tools/tests/whatsapp_settings_secret_boundary_smoke.php`
  - `tools/tests/whatsapp_engine_log_boundary_smoke.php`
  - `tools/tests/whatsapp_settings_mutation_csrf_smoke.php`
- Perubahan utama:
  - Finance membaca credential baru dari process environment, fail-closed sebelum session/DB/cURL; request ke engine hanya memakai header `X-Finance-Wa-Engine-Token`, tanpa token query atau `X-Sync-Token`.
  - Gate Node untuk seluruh `/internal/*` hanya menerima header dedicated yang tepat; query token, header legacy, credential kosong/salah, dan fallback `local-dev-token` ditolak.
  - Loader Node dan `buildEnvString()` PHP tidak mengimpor `FINANCE_WA_ENGINE_API_TOKEN` maupun `FINANCE_WA_ENGINE_COMMAND_TOKEN` dari `.env` di bawah web root; nilai legacy DB/.env tidak dihapus/ditimpa.
  - UI/panduan tidak lagi mengelola atau menginstruksikan `bot_api_token`/`WA_TOKEN`; endpoint, method, payload, timeout, loopback URL, no-follow, dan no-proxy dipertahankan.
  - Runbook mendokumentasikan provisioning process-only untuk PHP/FPM, scheduler CLI/cron, dan wa-engine; callback Batch 50 `FINANCE_WA_ENGINE_COMMAND_TOKEN` tetap terpisah.
- Validasi yang dijalankan:
  - PHP lint 8/8 dan Node `--check` 3/3: lulus.
  - Smoke Batch 52: PHP 17 checks; Node 21 checks.
  - Regresi: callback Batch 50 PHP 31; Node 16; settings CSRF 123; settings secret 20; engine log 24; env-save 70; engine-control 160; API send 91; manual 69; report schedule 140; scheduler CLI 30.
  - `composer validate --no-check-publish`: `composer.json` valid dengan deprecation warning baseline; `git diff --check` dan `git diff --cached --check`: lulus.
  - Seluruh smoke bersifat DB/network/bootstrap-free dan tidak mencetak credential.
- Hasil review auditor: awal `REVIEW: NEEDS_FIX` untuk coverage CLI/cron dan kontradiksi `WA_TOKEN` pada runbook; setelah dokumentasi + assertion diperbaiki, final `REVIEW: PASS`; `REQUIRED_FIXES: none`.
- Risiko sisa:
  - Cutover fail-closed sampai credential yang sama diprovision secara process-only pada PHP/FPM, CLI/cron, dan wa-engine; ketiganya perlu reload/restart terkoordinasi serta health check terkontrol tanpa mengekspos secret.
  - Smoke menguji fixture fungsi/gate, bukan proses PHP-FPM/cron/Node production nyata; rotasi tidak mendukung overlap token.
  - Legacy `wa_session.bot_api_token` dan `.env` `WA_TOKEN` dipertahankan untuk rollback eksplisit, tetapi tidak lagi dipakai oleh API internal; pembersihan/retensi secret lama menjadi pekerjaan operasional terpisah.
- Batch berikutnya: minta auditor memilih prioritas tertinggi berikutnya dari P0/P1 audit; jangan melakukan cutover secret atau membuat License Hub tanpa kebutuhan eksplisit.

## Batch 53.1 — P0 login throttle atomik dan session-audit fail-closed

- Waktu: 2026-09-03 01:12–01:42 WIB.
- Prioritas: P0 authentication integrity — review awal Batch 53 menemukan count→bcrypt→insert tidak atomik terhadap request paralel, timing blocked/unknown dapat membocorkan status akun, dan session authenticated dapat tersimpan walau `auth_session_log` gagal.
- Ringkasan diskusi auditor/fixer: auditor mensyaratkan serialisasi per IP dan akun, common response-time floor, serta audit session sebelum session write. Fixer menambahkan advisory lock MySQL dengan urutan IP-hash → user-id dan release terbalik, menjaga lock sampai `log_login()` selesai; menambahkan floor failure 400 ms yang dibatasi 500 ms tanpa bcrypt untuk blocked; memeriksa insert/id audit dan menangani finalisasi dengan pesan maintenance generik. Auditor final menyatakan PASS.
- File berubah:
  - `application/controllers/Auth.php`
  - `application/models/Auth_model.php`
  - `tools/tests/auth_login_throttle_smoke.php`
- Perubahan utama:
  - Login web menolak non-POST sebelum lookup/throttle/bcrypt; jalur mobile dua-argumen tetap tidak memakai throttle web.
  - Request web dikunci per IP canonical/hash lalu akun, meliputi count, bcrypt, insert failure, persistence success, dan `auth_session_log`; kegagalan acquire/release fail-closed.
  - Unknown user memakai dummy bcrypt; blocked tidak menjalankan bcrypt; failure responses memakai floor waktu bersama dan pesan generik.
  - `auth_session_log` berhasil ditulis dan menghasilkan ID sebelum `auth_user`/`session_log_id` masuk session; kegagalan DB tidak membuat session authenticated.
  - Migration `auth_login_failure` dari Batch 53 tetap belum dieksekusi/diubah.
- Validasi yang dijalankan:
  - PHP lint 3/3: lulus.
  - Smoke login throttle: PASS 52 checks; auth division scope 39; inactive-role permission 19; POS mobile authorization lulus.
  - `composer validate --no-check-publish`: valid dengan deprecation warning baseline; `git diff --check` dan `git diff --cached --check`: lulus.
  - Smoke DB/network/bootstrap-free; SQL tidak dijalankan.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`.
- Risiko sisa:
  - Contention dua koneksi MySQL nyata dan eksekusi migration belum diuji; deployment harus memastikan `GET_LOCK()`/`RELEASE_LOCK()` tersedia dan koneksi tetap sama.
  - Presisi timestamp `auth_login_failure.failed_at` vs `auth_session_log.login_at` masih menjadi Batch 53.2; proxy/IP bucket dan kebijakan retensi log perlu verifikasi operasional.
- Batch berikutnya: Batch 53.2 menyelaraskan presisi timestamp reset logis dan memperkuat migration compatibility, tanpa mengubah batas throttle atau flow login.

## Batch 53.2 — P0 presisi timestamp reset login dan cleanup migration

- Waktu: 2026-09-03 01:42–02:03 WIB.
- Prioritas: P0 integrity — `auth_login_failure.failed_at` sudah `DATETIME(6)`, sedangkan `auth_session_log.login_at` legacy masih presisi detik; failure dan successful login pada detik yang sama dapat salah dihitung setelah logical reset.
- Ringkasan diskusi auditor/fixer: auditor menetapkan batch sempit untuk microsecond boundary tanpa mengubah limit/lock. Fixer mengubah window/cutoff dan `log_login()` ke enam digit mikrodetik serta menambah migration guard yang hanya ALTER bila schema valid belum `DATETIME(6)`. Review pertama menemukan temporary procedure dapat tertinggal setelah `SIGNAL`; fixer menambah wrapper cleanup dengan `EXIT` trap, simulasi fake client, dan assertion safety. Auditor final menyatakan PASS.
- File berubah:
  - `application/models/Auth_model.php`
  - `sql/2026-09-03b_auth_session_log_login_at_microsecond_compatibility.sql`
  - `tools/db/apply_auth_session_log_login_at_microsecond.sh` (mode executable 0755)
  - `tools/tests/auth_login_throttle_smoke.php`
- Perubahan utama:
  - Rolling window IP/account memakai `Y-m-d H:i:s.u`; timestamp login sukses memakai microseconds; legacy success row tanpa fraction dinormalisasi ke `.000000`; `failed_at > cutoff` dipertahankan.
  - Migration `53b` mem-preflight table/column/type/presisi/nullability melalui `information_schema`, SIGNAL pada mismatch, dan hanya mengubah `auth_session_log.login_at` menjadi `DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)`; tanpa DML/backfill/replacement/TIMESTAMP conversion.
  - Wrapper menjalankan file SQL utuh melalui client delimiter-aware, menerima konfigurasi client/option-file tanpa password CLI, selalu mencoba `DROP PROCEDURE IF EXISTS` pada EXIT trap, mempertahankan exit failure migration, dan mengembalikan nonzero bila cleanup gagal.
  - Smoke fake-client memverifikasi same-second boundary, payload enam digit, DDL-only/idempotent guard, migration failure cleanup, cleanup failure status, whole-file input, dan tidak ada secret/arg password.
- Validasi yang dijalankan:
  - `php -l` Auth_model dan smoke: lulus; `bash -n` wrapper: lulus.
  - Auth throttle smoke: PASS 74 checks; auth division 39; POS mobile authorization lulus.
  - `composer validate --no-check-publish`: valid dengan deprecation warning baseline; `git diff --check` dan `git diff --cached --check`: lulus.
  - Tidak menjalankan SQL ke DB nyata, tidak membuka network, dan tidak membaca/mengubah credential/runtime.
- Hasil review auditor: awal `REVIEW: NEEDS_FIX` karena procedure dapat tertinggal pada preflight `SIGNAL`; setelah wrapper + fake-client regression, final `REVIEW: PASS`; `REQUIRED_FIXES: none`.
- Risiko sisa:
  - Belum ada eksekusi MySQL/MariaDB nyata pada clone non-produksi; perlu verifikasi preflight failure cleanup, successful ALTER, metadata default, DDL lock, dan dukungan `SIGNAL`/fractional datetime.
  - EXIT trap tidak dapat berjalan pada `SIGKILL`/host mati; bila koneksi/privilege cleanup gagal, wrapper melaporkan nonzero tetapi routine mungkin perlu dibersihkan manual.
  - Kebijakan retensi/monitoring `auth_login_failure` dan pengaturan proxy tepercaya/IP bucket masih operasional.
- Batch berikutnya: minta auditor memilih prioritas P0/P1 berikutnya dari audit total; migration runner global, role baseline, integrity stok, dan E2E DB tetap belum dijadikan batch tanpa desain/fixture yang aman.

## Batch 54 — P0 CSRF scoped dan POST-only writer Formula Component

- Waktu: 2026-09-03 02:17 WIB.
- Prioritas: P0 security/RBAC — global CSRF masih nonaktif, sementara `component_formula_save`, `component_formula_save_bulk`, dan `component_formula_delete` dapat mengubah formula yang menjadi dasar HPP, kebutuhan bahan, produksi, dan availability POS.
- Ringkasan diskusi auditor/fixer: auditor memilih guard scoped sebagai batch kecil tanpa schema/data change. Fixer mengubah halaman edit agar membutuhkan permission `edit`, menambahkan token session-scoped 64-hex, serta memaksa RBAC → POST → CSRF header sebelum payload/model pada ketiga writer. View editor mengirim header baru untuk save-bulk. Auditor menjalankan review ulang dan menyatakan PASS; catatan non-blocking hanya memperluas matriks smoke create-only/update dan edit-only/create.
- File berubah:
  - `application/controllers/Production.php`
  - `application/views/production/component_formula_edit.php`
  - `tools/tests/production_component_formula_mutation_csrf_smoke.php`
- Perubahan utama:
  - `component_formula_edit()` sekarang mewajibkan `production.component.formula.index:edit`.
  - Ketiga writer hanya menerima POST dan token melalui header canonical `X-Production-Component-Formula-Csrf`; token dibuat dengan `random_bytes(32)`, divalidasi strict, dan dibandingkan memakai `hash_equals` terhadap session key scoped.
  - Token form/query/JSON/raw body tidak diterima sebagai fallback; request invalid menghasilkan JSON 405/403 sebelum payload/model/DB.
  - Save tunggal tetap mempertahankan action `create` untuk insert dan `edit` untuk update; precheck memastikan caller memiliki salah satunya sebelum payload dibaca.
  - Editor merender token scoped dan mengirimkannya pada request `save-bulk` bersama header JSON/XHR yang sudah ada.
- Validasi yang dijalankan:
  - `php -l` controller, view, dan smoke: lulus.
  - Smoke behavior-level DB/network/bootstrap-free: PASS 191 checks, mencakup RBAC, method, missing/wrong/cross-scope token, fallback rejection, guard ordering, dan model reachability.
  - `git diff --check`: lulus.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`.
- Risiko sisa:
  - Caller direct/eksternal yang belum ditemukan harus mengirim header scoped baru; caller internal save-bulk sudah diperbarui.
  - Token mengikuti lifecycle session sehingga halaman perlu dimuat ulang setelah session berubah.
  - Jalur formula legacy `Master_relation` dan writer komersialisasi lain masih perlu audit/batch terpisah.
- Batch berikutnya: minta auditor memilih prioritas tertinggi P0/P1 berikutnya; fokus kandidat adalah writer formula legacy atau jalur stok/HPP yang masih belum memiliki guard setara, tanpa memperluas scope ke License Hub.

## Batch 55 — P0 CSRF scoped dan POST-only writer Resep Produk legacy

- Waktu: 2026-09-03 02:29 WIB.
- Prioritas: P0 security/RBAC — jalur legacy `Master_relation` masih memiliki writer resep yang menghapus–menulis ulang `mst_product_recipe`, sementara delete UI masih berupa anchor GET. Perubahan resep berdampak langsung pada HPP, kebutuhan bahan, produksi, dan availability POS.
- Ringkasan diskusi auditor/fixer: auditor memilih empat writer sebagai batch terkecil: `product_recipe_bulk_save`, `product_recipe_store`, `product_recipe_update`, dan `product_recipe_delete`. Fixer mempertahankan RBAC kanonis yang sudah ada, lalu menerapkan urutan RBAC → POST-only → CSRF form/session → parent/payload/model/DB. Token hanya disediakan pada render detail/bulk/create/edit; delete UI diubah menjadi form POST. Auditor final memisahkan delta ini dari perubahan RBAC pre-existing dan menyatakan PASS.
- File berubah:
  - `application/controllers/Master_relation.php`
  - `application/views/master/relation_product_recipe_edit.php`
  - `application/views/master/relation_form.php`
  - `application/views/master/relation_list.php`
  - `tools/tests/master_relation_product_recipe_mutation_csrf_smoke.php`
- Perubahan utama:
  - Keempat writer wajib POST dan memvalidasi token session-scoped `master_relation_product_recipe_mutation_csrf` melalui field form khusus; token dibuat dengan `random_bytes(32)`, format 64 hex ketat, dan `hash_equals`.
  - Non-POST ditolak 405; token kosong/malformed/salah/lintas-scope ditolak 403 sebelum membaca parent, payload bisnis, model, atau DB. Header/query/raw JSON tidak diterima sebagai fallback.
  - Token disupply hanya setelah permission render recipe lolos; bulk form dan form create/update memiliki hidden field scoped.
  - Delete recipe pada daftar menjadi form POST dengan konfirmasi; delete formula component dan product-extra tetap tidak diubah.
- Validasi yang dijalankan:
  - `php -l` controller, tiga view, dan smoke: lulus.
  - Smoke behavior-level DB/network/bootstrap-free: PASS 137 checks, mencakup RBAC, method, token missing/malformed/wrong/cross-scope, kanal alternatif, urutan guard, jalur valid, dan no-write boundary.
  - `git diff --check`: lulus.
- Hasil review auditor: `REVIEW: PASS`; `REQUIRED_FIXES: none`.
- Risiko sisa:
  - Belum ada probe browser dengan session CI nyata atau verifikasi transaksi DB nyata; itu diperlukan pada staging sebelum release.
  - Writer/delete formula component legacy dan product-extra masih belum mendapat CSRF batch ini; versioning/audit before-after resep juga belum ditambahkan.
  - Smoke belum mengeksekusi render sukses dengan token nyata; auditor menilai ini penyempurnaan non-blocking.
- Batch berikutnya: auditor memilih prioritas P0/P1 berikutnya, kemungkinan writer formula/extra legacy atau boundary stok/HPP; tetap satu batch kecil dan tanpa License Hub.

## Batch 56 — P0 CSRF scoped dan POST-only writer Formula Component legacy

- Waktu: 2026-09-03 02:41 WIB.
- Prioritas: P0 security/RBAC — endpoint legacy `Master_relation` untuk formula component masih hanya RBAC, sedangkan delete UI masih anchor GET. Writer langsung mengubah `mst_component_formula`, yang menjadi dasar HPP, kebutuhan bahan, produksi, dan availability POS; jalur ini melewati boundary modern Batch 54.
- Ringkasan diskusi auditor/fixer: auditor memilih `component_formula_store`, `component_formula_update`, dan `component_formula_delete` sebagai batch kecil tanpa schema/data change. Fixer menerapkan RBAC → POST-only → CSRF form/session sebelum parent, input, validation, model, atau DB; token disediakan hanya pada render formula yang terotorisasi. Delete formula di view menjadi form POST, sementara recipe dan product-extra dipisahkan. Validasi awal menemukan assertion smoke Batch 55 yang masih mengharapkan formula GET; fixer memperbaiki assertion agar formula POST dan product-extra tetap GET. Auditor final menerima remediasi delivery setelah smoke baru di-stage dan menyatakan PASS.
- File berubah:
  - `application/controllers/Master_relation.php`
  - `application/views/master/relation_form.php`
  - `application/views/master/relation_list.php`
  - `tools/tests/master_relation_component_formula_mutation_csrf_smoke.php`
  - `tools/tests/master_relation_product_recipe_mutation_csrf_smoke.php` (adaptasi regression assertion)
- Perubahan utama:
  - Tiga writer formula legacy hanya menerima POST; token `master_relation_component_formula_mutation_csrf` dibuat `random_bytes(32)`, strict lowercase 64-hex, dan dibandingkan dengan `hash_equals` terhadap session.
  - Guard hanya membaca field form scoped setelah method check; token header/query/raw JSON/field alternatif tidak diterima. Non-POST 405 dan token invalid 403 sebelum lookup/write.
  - Token formula di-render hanya pada detail/create/edit formula yang sudah lolos permission; generic form hanya menyisipkan token pada branch `component-formula`.
  - Delete formula pada relation list memakai POST form bertoken; recipe tetap POST dari Batch 55; product-extra tetap GET.
- Validasi yang dijalankan:
  - `php -l` controller, dua view, dan dua smoke: lulus.
  - Formula legacy smoke: PASS 159 checks; recipe regression smoke: PASS 138 checks; Production formula regression: PASS 191 checks.
  - `git diff --check` dan `git diff --cached --check`: lulus setelah smoke baru ditambahkan ke index.
- Hasil review auditor: awal `REVIEW: NEEDS_FIX` hanya karena smoke baru untracked (delivery risk Tinggi); fixer men-stage file tanpa mengubah isinya dan mengulang validasi; auditor final `REVIEW: PASS`; `REQUIRED_FIXES: none`.
- Risiko sisa:
  - Belum ada probe browser dengan session CI nyata atau transaksi DB nyata; release staging tetap perlu uji GET→405, token kosong/salah→403, dan POST valid pada akun least-privilege.
  - Smoke memakai doubles sehingga tidak membuktikan deployment HTTP/session/database aktual.
  - Writer formula/extra lain di luar tiga endpoint ini, audit before-after/versioning resep/formula, dan hardening product-extra masih terbuka.
- Batch berikutnya: auditor memilih prioritas P0/P1 berikutnya dari writer product-extra/bundle atau jalur stok/HPP yang masih terbuka; tetap satu batch kecil dan tanpa License Hub.

## Batch 57 — P0 CSRF scoped dan POST-only writer Product Extra legacy

- Waktu: 2026-09-03 02:58 WIB.
- Prioritas: P0 security/RBAC — mapping `product-extra` menentukan opsi extra di kasir serta nilai/harga dan konsumsi stok terkait, tetapi `product_extra_store`/`product_extra_delete` belum memiliki boundary POST/CSRF dan delete masih GET.
- Ringkasan diskusi auditor/fixer: auditor memilih dua writer saja agar batch sempit. Fixer menambahkan token session/form product-extra terpisah dan menerapkan RBAC → POST-only → CSRF form sebelum lookup/input/DB. Token disediakan hanya pada detail/create product-extra; form dan relation-list memakai token sesuai domain. Dua smoke regression lama diadaptasi ketika assertion GET product-extra menjadi usang. Auditor pertama menemukan implementasi belum staged bersama test; fixer men-stage tiga implementation file dan smoke terkait tanpa mengubah isi, lalu auditor re-review final menyatakan PASS.
- File berubah:
  - `application/controllers/Master_relation.php`
  - `application/views/master/relation_form.php`
  - `application/views/master/relation_list.php`
  - `tools/tests/master_relation_product_extra_mutation_csrf_smoke.php`
  - `tools/tests/master_relation_product_recipe_mutation_csrf_smoke.php` (adaptasi regression assertion)
  - `tools/tests/master_relation_component_formula_mutation_csrf_smoke.php` (adaptasi regression assertion)
- Perubahan utama:
  - `product_extra_store` dan `product_extra_delete` hanya menerima POST; token `master_relation_product_extra_mutation_csrf` dibuat dengan `random_bytes(32)`, format lowercase 64-hex ketat, dan `hash_equals` terhadap session.
  - Hanya form field scoped yang diterima; query/header/raw JSON/field alternatif tidak menjadi fallback. Non-POST 405 dan token invalid 403 sebelum parent/row lookup, input bisnis, model, atau DB.
  - Hidden token hanya berada di branch product-extra pada generic form; delete mapping product-extra menjadi confirmed POST form. Recipe dan component-formula tetap POST dengan token scoped masing-masing.
- Validasi yang dijalankan:
  - `php -l` controller, dua view, dan smoke product-extra: lulus.
  - Smoke product-extra: PASS 141 checks; recipe regression: PASS 138; legacy component formula: PASS 159; modern Production formula: PASS 191.
  - `git diff --check` dan `git diff --cached --check`: lulus.
  - Ketujuh berkas batch staged atomik; tidak ada commit atau staged deletion.
- Hasil review auditor: awal `REVIEW: NEEDS_FIX` karena implementasi unstaged sementara smoke staged (delivery risk Tinggi); fixer men-stage implementation bersama test tanpa mengubah isi; final `REVIEW: PASS`; `REQUIRED_FIXES: none`.
- Risiko sisa:
  - Belum ada browser probe dengan session CI nyata atau transaksi DB nyata; staging perlu uji create/delete mapping pada akun least-privilege dan cek opsi extra POS.
  - Global CSRF tetap nonaktif; guard ini masih route-scoped. Constraint schema `mst_product_extra_map` perlu diverifikasi terhadap drift sebelum release.
  - Extra-group AJAX, bundle, audit before-after/versioning, dan repair mismatch HPP belum dikerjakan.
- Batch berikutnya: auditor memilih P0/P1 berikutnya, kemungkinan extra-group/bundle writer atau jalur stok/HPP kritis; tetap satu batch kecil dan tanpa License Hub.

## Batch 58 — P0 CSRF scoped dan POST-only writer Bundle Produk

- Waktu: 2026-09-03 03:12 WIB.
- Prioritas: P0 security/RBAC — writer bundle produk masih menerima store/update/toggle tanpa CSRF scoped dan toggle masih GET. Bundle memengaruhi harga, komposisi, status aktif, katalog, dan perilaku POS.
- Ringkasan diskusi auditor/fixer: auditor memilih tiga writer bundle sebagai batch kecil tanpa schema/data change. Fixer menerapkan RBAC → POST-only → CSRF form/session sebelum normalisasi payload, load bundle, atau DB; token hanya disediakan pada hub/create/edit yang berizin, detail tetap token-free. Toggle hub diubah menjadi form POST bertoken. Auditor pertama menemukan implementasi belum staged bersama smoke; fixer men-stage tiga implementation file dan smoke tanpa perubahan isi, lalu auditor re-review final menyatakan PASS.
- File berubah:
  - `application/controllers/Master_relation.php`
  - `application/views/master/product_bundle_edit.php`
  - `application/views/master/product_bundle_hub.php`
  - `tools/tests/master_relation_product_bundle_mutation_csrf_smoke.php`
- Perubahan utama:
  - `product_bundle_store`, `product_bundle_update`, dan `product_bundle_toggle` hanya menerima POST; token `master_relation_product_bundle_mutation_csrf` dibuat dengan `random_bytes(32)`, lowercase 64-hex ketat, dan `hash_equals` terhadap session.
  - Token hanya diterima dari field form scoped; query/header/raw JSON/field alternatif tidak diterima. Non-POST 405 dan token invalid 403 sebelum akses bisnis.
  - Form editor memakai hidden token; hub memakai confirmed POST form untuk toggle; link detail/edit dipertahankan.
- Validasi yang dijalankan:
  - `php -l` controller, dua view, dan smoke: lulus.
  - Bundle smoke: PASS 149 checks; regression Production formula 191, legacy formula 159, recipe 138, product-extra 141: seluruhnya lulus.
  - `git diff --check` dan `git diff --cached --check`: lulus.
  - Empat artefak batch staged atomik; tidak ada commit/staged deletion.
- Hasil review auditor: awal `REVIEW: NEEDS_FIX` karena implementation unstaged sementara smoke staged (delivery risk Tinggi); fixer men-stage tiga implementation file bersama smoke tanpa mengubah isi; final `REVIEW: PASS`; `REQUIRED_FIXES: none`.
- Risiko sisa:
  - Belum ada browser probe dengan session CI nyata atau transaksi DB nyata; staging perlu uji create/update/toggle pada akun least-privilege dan verifikasi katalog/POS.
  - Global CSRF tetap nonaktif sehingga proteksi ini route-scoped. Constraint/foreign-key bundle perlu diverifikasi terhadap schema drift.
  - Audit integritas konsumsi bundle, snapshot/alokasi harga transaksi, extra-group AJAX, dan repair mismatch HPP masih terbuka.
- Batch berikutnya: auditor memilih prioritas P0/P1 berikutnya dari writer extra-group/AJAX atau integrity stok/HPP; tetap satu batch kecil dan tanpa License Hub.

## Batch 59 — P0 CSRF scoped dan POST-only writer AJAX Extra Group

- Waktu: 2026-09-03 03:28 WIB.
- Prioritas: P0 security/RBAC — dua endpoint AJAX replace-all untuk relasi extra-group masih menjalankan delete/reinsert pada `mst_extra_group_item` dan `mst_product_extra_map` setelah RBAC, tetapi tanpa POST-only dan CSRF scoped. Global CSRF CI3 tetap nonaktif.
- Ringkasan diskusi auditor/fixer: auditor memilih `extra_group_items_save_ajax` dan `extra_group_products_save_ajax` sebagai batch kecil tanpa schema/data change. Saat menelusuri caller aktual, ditemukan modal first-party di `master/index.php`; scope diperluas secara terarah agar token dibaca dari response GET editor, disimpan di state modal, save view-only dinonaktifkan, dan POST mengirim header canonical. Fixer menerapkan urutan RBAC → POST/header-CSRF guard → lookup/input/transaksi. Token read hanya diterbitkan untuk user dengan permission edit. Auditor final memeriksa controller, caller, smoke, serta staging dan menyatakan PASS.
- File berubah:
  - `application/controllers/Master_relation.php`
  - `application/views/master/index.php`
  - `tools/tests/master_relation_extra_group_mutation_csrf_smoke.php`
- Perubahan utama:
  - `extra_group_items_save_ajax` dan `extra_group_products_save_ajax` hanya menerima POST; token session scoped dibuat dengan `random_bytes(32)`, divalidasi lowercase 64-hex, dan dibandingkan dengan `hash_equals`.
  - Guard hanya menerima `X-Master-Extra-Group-Csrf`; query, form, raw body, JSON, dan header alternatif tidak menjadi fallback. Non-POST menghasilkan 405 dan token invalid/mismatch 403 sebelum lookup atau DB.
  - Response read GET editor memuat `mutation_csrf`; user view-only tidak menerima token dan tombol simpan disabled. Caller mengirim token hanya sebagai header pada POST.
- Validasi yang dijalankan:
  - `php -l` controller, view, dan smoke: lulus.
  - Extra-group smoke: PASS 85 checks; regression bundle 149, product-extra 141, legacy component formula 159, recipe 138, modern Production formula 191: seluruhnya lulus.
  - `git diff --check` dan `git diff --cached --check`: lulus.
  - Tiga artefak Batch 59 staged atomik; tidak ada route/schema baru, commit, atau staged deletion.
- Hasil review auditor: `PASS`; tidak ada finding blocking atau required fix. Auditor mengonfirmasi kedua writer menjalankan RBAC edit → POST/header-CSRF → DB dan tidak ada caller lain untuk dua route save tersebut.
- Risiko sisa:
  - Global CSRF tetap nonaktif sehingga proteksi ini route-scoped; belum ada browser probe dengan session CI nyata atau transaksi DB staging.
  - Writer extra-group non-AJAX dan writer AJAX/form lain di luar batch ini masih perlu audit/hardening; smoke behavior-level belum membuktikan deployment HTTP/session/database aktual.
- Batch berikutnya: auditor memilih writer mutasi P0 lain yang belum tercakup dan/atau memulai browser-level integration probe setelah rangkaian writer Master Relation selesai; tetap satu batch kecil dan tanpa License Hub.

## Batch 60 — P0 CSRF scoped dan POST-only checklist Extra Group non-AJAX

- Waktu: 2026-09-03 03:50 WIB.
- Prioritas: P0 security/RBAC — `extra_group_products_save` dan `extra_item_groups_save` adalah writer replace-all yang sebelumnya hanya memeriksa RBAC, menerima method apa pun, dan tanpa CSRF scoped. Karena itu GET berizin dapat menghapus seluruh mapping; dampaknya langsung ke katalog opsi extra dan perilaku POS.
- Ringkasan diskusi auditor/fixer: auditor memilih dua sisi checklist Extra Group sebagai satu batch atomik, melanjutkan hardening versi AJAX pada Batch 59. Fixer menambahkan token form/session terpisah, menerapkan urutan RBAC edit → POST-only → CSRF form-only → parent/payload/transaksi, serta hanya merender token dan kontrol simpan bagi editor. Kontrak header AJAX Batch 59 dipertahankan. Auditor final menyatakan PASS setelah memeriksa staged diff dan validasi.
- File berubah:
  - `application/controllers/Master_relation.php`
  - `application/views/master/extra_group_products.php`
  - `application/views/master/extra_item_groups.php`
  - `tools/tests/master_relation_extra_group_checklist_mutation_csrf_smoke.php`
- Perubahan utama:
  - Token `master_relation_extra_group_checklist_mutation_csrf` dibuat dengan `random_bytes(32)`, strict lowercase 64-hex, dan `hash_equals` terhadap session.
  - Kedua writer menolak GET/PUT/DELETE dan method non-POST lain dengan 405; token kosong, malformed, mismatch, cross-scope, query/header/JSON/raw-body fallback ditolak 403 sebelum lookup atau DB.
  - View editor mendapat hidden token dan kontrol aktif; view-only tidak mendapat token, tombol simpan disembunyikan, dan checkbox dinonaktifkan.
- Validasi yang dijalankan:
  - `php -l` keempat file: lulus.
  - Checklist smoke: PASS 157 checks; regresi AJAX Batch 59: 85; product-extra 141; component formula 159; product recipe 138; product bundle 149: seluruhnya lulus.
  - `git diff --check` dan `git diff --cached --check`: lulus.
  - Empat artefak Batch 60 staged atomik tanpa overlay unstaged; tidak ada route/schema/config/data change, commit, atau deletion.
- Hasil review auditor: `PASS`; tidak ada finding blocking atau required fix. Urutan guard, isolasi token dari AJAX, rendering editor/view-only, dan kualitas smoke dinyatakan benar.
- Risiko sisa:
  - Filter `q` belum diubah: save replace-all saat daftar terfilter masih dapat menghapus mapping yang tidak tampil.
  - Payload belum memvalidasi status aktif dan kecocokan divisi/parent secara penuh; concurrent save belum memakai revision/optimistic locking.
  - Global CSRF tetap nonaktif dan belum ada browser probe dengan session CI nyata/transaksi DB staging.
- Batch berikutnya: auditor memprioritaskan perbaikan semantik filter checklist (delta update atau blok save saat `q` aktif), lalu validasi ID aktif/divisi dan concurrency secara terpisah; tetap satu batch kecil dan tanpa License Hub.

## Batch 61 — HIGH filter checklist Extra Group menjadi read-only

- Waktu: 2026-09-03 04:12 WIB.
- Prioritas: HIGH integrity — kedua writer checklist melakukan replace-all berdasarkan checkbox yang sedang tampil. Saat `q` aktif, ID mapping di luar hasil filter tidak ikut terkirim dan dapat terhapus diam-diam.
- Ringkasan diskusi auditor/fixer: auditor memilih solusi paling kecil dan fail-safe: halaman checklist terfilter menjadi read-only, bukan delta update yang berisiko mengubah semantics. Fixer tidak menerbitkan token dan tidak menampilkan kontrol simpan/select-all saat `q` aktif; kedua writer menolak POST dengan query `q` aktif setelah RBAC+CSRF dan sebelum parent/payload/transaksi, lalu redirect dengan `q` dipertahankan secara aman. Tanpa filter, replace-all dan clear-all eksplisit tetap kompatibel. Auditor final menyatakan PASS.
- File berubah:
  - `application/controllers/Master_relation.php`
  - `application/views/master/extra_group_products.php`
  - `application/views/master/extra_item_groups.php`
  - `tools/tests/master_relation_extra_group_checklist_mutation_csrf_smoke.php`
- Perubahan utama:
  - Reader hanya menerbitkan token checklist ketika `q` kosong; filter aktif menampilkan pesan bahwa reset filter diperlukan untuk mengubah checklist.
  - Checkbox/filter controls ter-disable atau disembunyikan pada mode terfilter; view-only tetap tidak mendapat kontrol edit.
  - Writer menolak query `q` nonkosong tanpa lookup/model/DB dan memakai redirect dengan encoding aman. Payload kosong pada `q` kosong tetap berarti clear-all yang disengaja.
- Validasi yang dijalankan:
  - `php -l` tiga file aplikasi dan smoke: lulus.
  - Checklist smoke: PASS 203 checks; regresi AJAX Batch 59: 85; recipe 138; formula legacy 159; product-extra 141; bundle 149; Production formula 191: seluruhnya lulus.
  - `git diff --check` dan `git diff --cached --check`: lulus.
  - Empat artefak staged atomik tanpa overlay unstaged; tidak ada route/schema/config/data change atau deletion.
- Hasil review auditor: `PASS`; urutan guard, redirect, clear-all tanpa filter, isolasi kontrak AJAX, smoke, dan staging dinyatakan benar.
- Risiko sisa:
  - Replace-all masih last-writer-wins pada dua editor bersamaan tanpa optimistic locking/versioning.
  - Payload masih menerima ID positif/deduplikasi tanpa validasi server-side penuh untuk keberadaan, status aktif, kecocokan divisi, dan eligibility parent.
  - Belum ada browser probe dengan session CI nyata/transaksi DB staging.
- Batch berikutnya: auditor memilih hardening payload set untuk writer checklist Extra Group (non-AJAX dan AJAX) secara terukur; validasi concurrency dipisahkan agar batch tetap kecil.

## Batch 62 — HIGH validasi set Extra Group → Product

- Waktu: 2026-09-03 04:01 WIB.
- Prioritas: HIGH integrity — dua writer `Extra Group → Product` mengubah `mst_product_extra_map` dengan delete-all/insert berdasarkan ID positif/deduplikasi saja. Payload manipulatif dapat memasukkan produk tidak ada, nonaktif, atau lintas divisi dan memengaruhi opsi extra POS.
- Ringkasan diskusi auditor/fixer: auditor memilih dua writer yang setara (form dan AJAX) agar tidak ada bypass antar-caller. Karena aturan divisi produk bersifat objektif dari `mst_extra_group.product_division_id`, validator bersama ditambahkan; sisi `Extra → Group` ditunda karena `mst_extra` tidak memiliki aturan divisi yang aman untuk diinferensikan. Fixer mempertahankan guard RBAC/POST/CSRF/q, clear-all eksplisit, dedupe, dan sort order; invalid mixed set ditolak sebelum delete/transaksi. Auditor final menyatakan PASS.
- File berubah:
  - `application/controllers/Master_relation.php`
  - `tools/tests/master_relation_extra_group_checklist_mutation_csrf_smoke.php`
  - `tools/tests/master_relation_extra_group_mutation_csrf_smoke.php`
- Perubahan utama:
  - Helper shared memakai query set-based untuk memastikan seluruh product ID ada, `is_active = 1`, dan cocok dengan divisi group bila divisi group non-NULL; divisi NULL mengizinkan lintas divisi.
  - Field tidak ada/array kosong tetap clear-all, termasuk group nonaktif; group nonaktif dengan set nonempty, scalar/malformed/0/negatif/nonexistent/inactive/wrong-division/mixed-invalid ditolak sebelum write.
  - Form mengembalikan flash+redirect, AJAX JSON 422; delete dan insert tetap dalam transaksi. Kontrak header AJAX dan filter Batch 59–61 tidak berubah.
- Validasi yang dijalankan:
  - `php -l` controller dan dua smoke: lulus.
  - Checklist smoke: PASS 254 checks; AJAX smoke: PASS 119; Production formula 191; recipe 138; component formula 159; product-extra 141; bundle 149: seluruhnya lulus.
  - `composer validate --no-check-publish`: valid; hanya deprecation warning dari Composer lama dan peringatan root.
  - `git diff --check` dan `git diff --cached --check`: lulus.
  - Tiga artefak staged tanpa overlay unstaged; tidak ada route/view/schema/config/data change atau deletion.
- Hasil review auditor: `PASS`; validator set, urutan sebelum delete, empty-set policy, division NULL behavior, transaction, smoke, dan staging dinyatakan benar.
- Risiko sisa:
  - Validasi state product/group masih dapat berlomba dengan perubahan bersamaan; last-writer-wins/concurrency belum memakai lock/versioning.
  - Writer legacy `product_extra_store/delete` belum mengikuti seluruh aturan eligibility; mapping invalid historis belum dibersihkan.
  - Belum ada query preflight dan browser/DB integration probe di staging karena environment DB tidak tersedia dalam CLI.
- Batch berikutnya: auditor memilih hardening payload `Extra → Group` dengan kebijakan existence/active yang jelas, atau lebih dahulu merancang probe DB/rollback untuk transaksi mapping; jangan menginfer aturan divisi tanpa dasar bisnis.

## Batch 63 — HIGH validasi existence/active Extra ↔ Group

- Waktu: 2026-09-03 04:15 WIB.
- Prioritas: HIGH integrity — dua writer sisi `Extra → Group` sebelumnya melakukan cast integer lalu replace-all pada `mst_extra_group_item`; ID rusak dapat menjadi clear-all diam-diam atau baru gagal di FK setelah delete.
- Ringkasan diskusi auditor/fixer: auditor menetapkan policy tanpa inferensi divisi/source-kind: parent dan selected child harus ada serta aktif untuk set nonempty; parent nonaktif tetap boleh clear-all; missing/empty field berarti clear-all eksplisit; invalid mixed set harus gagal sebelum transaksi. Fixer menambah helper shared dan memperluas dua smoke. Auditor pertama memberi catatan packaging karena index global kumulatif; fixer memverifikasi target-scoped staging tanpa mengubah staging batch lain; auditor re-review menyatakan PASS untuk workflow tanpa commit.
- File berubah:
  - `application/controllers/Master_relation.php`
  - `tools/tests/master_relation_extra_group_checklist_mutation_csrf_smoke.php`
  - `tools/tests/master_relation_extra_group_mutation_csrf_smoke.php`
- Perubahan utama:
  - Validator `Extra → Group` menolak scalar/null/malformed/0/negatif/float/bool/noncanonical numeric, nonexistent, inactive child, inactive parent dengan set nonempty, dan mixed-invalid sebelum delete/insert/transaksi.
  - ID valid dideduplikasi berdasarkan urutan pertama; sort order tetap 10/20; cross-domain, division, dan `source_kind` tetap diizinkan karena belum ada policy bisnis objektif.
  - Form memakai flash+redirect, AJAX JSON 422; absent/empty set tetap delete-only clear-all, termasuk parent nonaktif. Guard RBAC/POST/CSRF/q dan B62 product-side validation tidak berubah.
- Validasi yang dijalankan:
  - `php -l` controller dan dua smoke: lulus.
  - Checklist smoke: PASS 322 checks; AJAX smoke: PASS 161; regresi B54 191, B55 138, B56 159, B57 141, B58 149: seluruhnya lulus.
  - `composer validate --no-check-publish`: valid; warning hanya dari Composer sistem lama/root/deprecation.
  - `git diff --check` dan `git diff --cached --check`: lulus.
  - Tiga target staged tanpa overlay unstaged; target-scoped atomik, sementara index global tetap kumulatif untuk menjaga batch sebelumnya/user changes.
- Hasil review auditor: awal `NEEDS_FIX` hanya pada interpretasi standalone commit; setelah workflow tanpa commit dikonfirmasi dan staging target diverifikasi, re-review `PASS`. Tidak ada finding kode atau delivery aktual.
- Risiko sisa:
  - TOCTOU dan last-writer-wins masih memungkinkan saat parent/child berubah bersamaan; belum ada lock/versioning.
  - Mapping historis orphan/nonaktif tidak diremediasi otomatis; hanya writer baru yang divalidasi.
  - Belum ada query preflight atau DB/browser integration probe pada staging karena kredensial DB CLI tidak tersedia.
- Batch berikutnya: auditor memilih integrity constraint/query preflight atau desain concurrency/revalidation transaction untuk mapping, tetap tanpa perubahan schema/data kecuali bukti staging mengharuskannya.

## Batch 64 — HIGH hardening legacy Product → Extra Group

- Waktu: 2026-09-03 04:28 WIB.
- Prioritas: HIGH integrity — `product_extra_store` legacy masih menjadi bypass terhadap invariant B62: hanya cast `extra_group_id`, lalu insert tanpa validasi product/group aktif atau kecocokan divisi. Opsi create juga menawarkan group nonaktif/beda divisi.
- Ringkasan diskusi auditor/fixer: auditor memilih jalur create legacy, bukan reconciliation/versioning yang lebih besar. Fixer membatasi opsi group ke group aktif generik atau sesuai divisi product, dan memvalidasi server-side setelah RBAC+CSRF sebelum duplicate/insert. Delete tetap tidak memeriksa status agar mapping historis dapat dibersihkan. Auditor pertama memberi catatan staging global kumulatif; fixer memverifikasi target scoped tanpa mengubah staging lain; auditor re-review menyatakan PASS untuk workflow tanpa commit.
- File berubah:
  - `application/controllers/Master_relation.php`
  - `tools/tests/master_relation_product_extra_mutation_csrf_smoke.php`
- Perubahan utama:
  - Create hanya menawarkan group aktif dengan `product_division_id IS NULL` atau sama dengan divisi product.
  - Store menolak product nonaktif, ID group kosong/noncanonical/malformed/0/negatif/float/bool, group tidak ada/nonaktif, dan mismatch divisi sebelum insert; generic NULL division tetap sah.
  - Duplicate valid tetap warning/no insert; delete mapping historis tetap boleh meski parent/child nonaktif.
- Validasi yang dijalankan:
  - `php -l` controller dan smoke: lulus.
  - B57 smoke: PASS 252 checks; B59 161; B60 322; B54 191; B55 138; B56 159; B58 149: seluruhnya lulus.
  - `composer validate --no-check-publish`: valid; warning hanya dari Composer sistem/deprecation/root.
  - `git diff --check` dan `git diff --cached --check`: lulus.
  - Dua target staged tanpa overlay unstaged; tidak ada route/schema/data/config/view change atau deletion.
- Hasil review auditor: awal `NEEDS_FIX` hanya staging gate bersyarat standalone commit; setelah target-scoped staging diverifikasi dan no-commit workflow dikonfirmasi, re-review `PASS` tanpa defect kode.
- Risiko sisa:
  - TOCTOU/status dan race duplicate masih mungkin antara validasi, duplicate check, dan insert; hasil insert belum ditangani secara eksplisit pada race unique key.
  - Mapping historis inactive/orphan/mismatch belum diremediasi otomatis dan tetap sengaja dapat dihapus.
  - Belum ada DB/browser integration probe nyata; schema snapshot lama belum membuktikan unique/FK aktif di deployment.
- Batch berikutnya: auditor memilih penanganan duplicate-key/insert failure secara atomik atau reconciliation read-only sebelum concurrency/versioning; tetap satu batch kecil dan tanpa License Hub.

## Batch 65 — HIGH atomic duplicate-key dan insert-failure handling

- Waktu: 2026-09-03 04:46 WIB.
- Prioritas: HIGH reliability/security — `product_extra_store` melakukan pre-check duplicate lalu generic insert yang mengabaikan hasil; race unique-key atau error DB dapat menghasilkan flash sukses palsu. Selain itu CI3 `DB_driver::query()` mencatat SQL/detail error bahkan saat `db_debug=false`.
- Ringkasan diskusi auditor/fixer: auditor memilih memperbaiki satu legacy writer aktif tanpa menyentuh `Master_model` generik. Fixer pertama menambahkan cek hasil insert, tetapi auditor menemukan SQL/detail masih dapat bocor melalui logging internal CI3. Fixer kemudian memakai prepared statement statis langsung dari connection `mysqli`, binding integer, menangani 1062 sebagai warning duplicate, error lain/throwable sebagai error generik, dan memulihkan `db_debug`/menutup statement di `finally`. Auditor re-review menyatakan PASS.
- File berubah:
  - `application/controllers/Master_relation.php`
  - `tools/tests/master_relation_product_extra_mutation_csrf_smoke.php`
- Perubahan utama:
  - Insert final tidak lagi memakai `Master_model->insert` atau `$db->insert`; memakai `conn_id->prepare` dengan SQL tabel/kolom statis dan `bind_param('iii')`.
  - Hanya errno numerik yang dipakai untuk routing/log context; SQL, DB message, exception detail, dan secret marker tidak ditampilkan/logged oleh aplikasi.
  - Error 1062 menjadi warning “Mapping sudah ada” dan redirect list; error lain/throwable menjadi pesan generik dan redirect create; pre-check duplicate dan delete historis tetap kompatibel.
- Validasi yang dijalankan:
  - `php -l` controller dan smoke: lulus.
  - B57 smoke: PASS 299 checks; B59 161; B60/B62/B63 322; B54 191; B55 138; B56 159; B58 149: seluruhnya lulus.
  - `composer validate --no-check-publish`: valid; warning hanya deprecated Composer/Symfony lama dan root.
  - `git diff --check` dan `git diff --cached --check`: lulus.
  - Dua target staged tanpa overlay unstaged; no-commit workflow dan staging target-scoped dipertahankan.
- Hasil review auditor: awal `NEEDS_FIX` karena CI3 query builder tetap berpotensi menulis SQL/detail ke log; setelah prepared statement langsung dan smoke log-sink, re-review `PASS`.
- Risiko sisa:
  - Requirement runtime kini eksplisit: CI3 harus memakai driver `mysqli` dengan `conn_id` kompatibel; belum ada uji database/browser/concurrency nyata.
  - Unique/FK deployment aktual belum dibuktikan; tanpa unique key, race tetap dapat menggandakan mapping.
  - Mapping historis invalid belum diremediasi dan empat replace-all writer masih last-writer-wins.
- Batch berikutnya: auditor memilih read-only reconciliation/preflight untuk memeriksa constraint dan mapping historis sebelum merancang concurrency/versioning; tetap tanpa mutasi data otomatis.

## Batch 66 — HIGH read-only Extra Group mapping preflight

- Waktu: 2026-09-03 05:30 WIB.
- Prioritas: HIGH integrity/release safety — sebelum memperbaiki data atau merancang concurrency, perlu bukti read-only tentang tabel mapping, 16 kolom wajib, unique pair, FK, orphan, duplicate, inactive master, dan mismatch divisi.
- Ringkasan diskusi auditor/fixer: auditor memilih preflight kecil yang tidak mengubah schema/data. Fixer menambahkan SQL SELECT-only, wrapper marker/exit-code, smoke, dan runbook. Review awal meminta `sort_order`/kontrak 16 kolom, identity serta aksi FK `RESTRICT`, parser yang menolak marker setelah `B66_END` dan detail melebihi count, serta runbook yang jujur pada schema-missing. Setelah diperbaiki, review akhir menemukan satu risiko HIGH: `--defaults-extra-file` masih membolehkan global option files; fixer menggantinya menjadi `--defaults-file` sebagai opsi client pertama, memperbarui smoke/runbook, dan auditor akhir menyatakan PASS.
- File berubah:
  - `tools/sql/2026-09-03_extra_group_mapping_preflight.sql`
  - `tools/run_extra_group_mapping_preflight.sh`
  - `tools/tests/extra_group_mapping_preflight_smoke.sh`
  - `docs/2026-09-03_extra_group_mapping_preflight_runbook.md`
- Perubahan utama:
  - Preflight memakai `START TRANSACTION READ ONLY`, SELECT terhadap target/`information_schema`, `COMMIT`, tanpa dynamic SQL, DDL, DML, atau remediation otomatis.
  - Memeriksa lima tabel InnoDB, kolom/`sort_order`, unique pair, empat FK dengan identity dan `DELETE/UPDATE RESTRICT`, serta sembilan kategori finding dengan detail maksimum 50 baris.
  - Wrapper memakai external option file yang dicanonicalisasi dan dilarang berada di repository; `--defaults-file` mengisolasi konfigurasi client; credential environment di-unset; output database/error dirahasiakan; marker dan exit code divalidasi.
  - Smoke menguji kontrak SQL, allow-list source, parser malformed/terminal/count, schema/data exit mapping, secret boundary, external option file, dan repository immutability. Runbook memberi prosedur akun SELECT-only dan follow-up browser/POS.
- Validasi yang dijalankan:
  - `bash -n tools/run_extra_group_mapping_preflight.sh tools/tests/extra_group_mapping_preflight_smoke.sh`: lulus.
  - B66 smoke: PASS 113 checks.
  - Regression B65 product-extra mutation CSRF smoke: PASS 299 checks.
  - Static SQL: 44 SELECT, tanpa match DML/DDL, sembilan `LIMIT 50`.
  - Wrapper bad argument exit `64` dan missing option file exit `66`: lulus; permission script `755`.
  - `git diff --check` dan `git diff --cached --check`: lulus; empat target B66 staged tanpa overlay unstaged; tidak ada commit.
  - Auditor review akhir: PASS, tanpa defect konkret.
- Risiko sisa:
  - Preflight database nyata belum dijalankan karena belum ada option file eksternal dan akun DB read-only; schema/deployment aktual belum terbukti.
  - UAT browser mapping dan probe POS web/mobile untuk group generic, group sesuai divisi, serta group beda divisi masih wajib sebelum release.
  - Hasil ini tidak meremediasi orphan/duplicate/inactive/mismatch; remediation dan concurrency/versioning tetap batch terpisah.
- Batch berikutnya: auditor memilih batch kecil berbasis bukti preflight/reconciliation atau hardening concurrency mapping; tetap read-only dahulu bila DB belum tersedia, tanpa License Hub dan tanpa mutasi data otomatis.

## Batch 67 — HIGH optimistic concurrency Extra Group → Product

- Waktu: 2026-09-03 06:01 WIB.
- Prioritas: HIGH integrity/concurrency — dua writer replace-all dapat mengalami last-writer-wins; editor kedua dengan snapshot lama dapat menghapus mapping yang baru disimpan editor pertama.
- Ringkasan diskusi auditor/fixer: auditor memilih dua writer Product mapping saja, tanpa mengubah arah Extra → Group atau schema/data. Fixer menambahkan revision SHA-256 deterministik, lock parent dan mapping, pemeriksaan transaksi, serta guard state modal. Review pertama menemukan tiga isu HIGH: response AJAX Group A dapat masuk ke state Group B, return `trans_begin`/`trans_commit` diabaikan, dan lock parent saja belum menahan legacy Product → Extra Group delete. Fixer memperbaiki ketiganya dan menambah fixture smoke. Auditor re-review menyatakan PASS. Catatan staging kumulatif 46 file diperlakukan sebagai packaging residual; perubahan batch/user lain tidak di-unstage.
- File berubah:
  - `application/controllers/Master_relation.php`
  - `application/views/master/extra_group_products.php`
  - `application/views/master/index.php`
  - `tools/tests/master_relation_extra_group_checklist_mutation_csrf_smoke.php`
  - `tools/tests/master_relation_extra_group_mutation_csrf_smoke.php`
- Perubahan utama:
  - Reader form/AJAX menghitung revision canonical dari seluruh pasangan `product_id + sort_order`; serialization length-prefixed dan SHA-256 dipakai konsisten pada reader dan writer.
  - Writer menolak revision missing/malformed; transaksi mengunci parent `mst_extra_group` lalu rows mapping `mst_product_extra_map` dengan `FOR UPDATE`, memverifikasi revision sebelum validasi dan DELETE/INSERT.
  - `trans_begin`/`trans_commit` failure tidak dapat menghasilkan sukses; rollback/error form/AJAX generik dipertahankan. Stale revision rollback tanpa mapping DML dan AJAX mengembalikan HTTP 409.
  - Modal memakai epoch/context group+kind serta request/query sequence; response stale tidak mengubah state group lain, filter tidak menimpa snapshot awal, dan save fail-closed jika revision/CSRF tidak valid.
  - Smoke mencakup canonical revision, race response A/B dan initial/filter, lock ordering, missing/malformed revision, begin/commit failure, conflict tanpa DML, clear-all/valid path, serta regresi permission/payload.
- Validasi yang dijalankan:
  - `php -l` controller, dua view, dan dua smoke: lulus.
  - Checklist smoke: PASS 344 checks; AJAX smoke: PASS 185 checks.
  - Regression product-extra: PASS 299 checks; production formula: PASS 191 checks; B66 preflight smoke: PASS 113 checks.
  - `git diff --check` dan `git diff --cached --check`: lulus; lima target staged tanpa unstaged delta; tidak ada commit.
  - Auditor re-review: PASS, tanpa defect konkret.
- Risiko sisa:
  - Legacy `product_extra_delete` masih tidak mengambil parent lock sendiri; serialisasi lintas jalur bergantung pada InnoDB, FK `fk_mst_product_extra_map_group`, unique pair, dan index schema yang harus dibuktikan B66 pada DB target.
  - Smoke race frontend masih source-level; UAT dua browser/session dan initial/filter out-of-order belum dijalankan.
  - DB multi-session untuk deadlock/commit failure belum tersedia; mapping historis invalid dan arah Extra → Group tetap belum ditangani.
  - Index kerja tetap kumulatif 46 file staged; packaging/commit wajib memakai manifest/whitelist batch dan tidak boleh membawa file runtime/backup/user secara tidak sengaja.
- Batch berikutnya: auditor memilih hardening jalur legacy Product → Extra Group agar delete/insert mengikuti concurrency protocol, atau batch read-only untuk reconciliation berbasis hasil preflight; tetap satu batch kecil, tanpa mutasi data otomatis dan tanpa License Hub.

## Batch 68 — HIGH serialisasi legacy Product → Extra Group

- Waktu: 2026-09-03 06:32 WIB.
- Prioritas: HIGH integrity/concurrency — writer legacy `product_extra_store` dan `product_extra_delete` masih bypass protocol lock B67; insert dapat berinterleaving, dan delete dapat sukses palsu setelah mapping diganti atau hilang.
- Ringkasan diskusi auditor/fixer: auditor memilih serialisasi dua writer legacy saja. Review pertama menerima lock/order/transaction secara fungsional, tetapi menemukan query CI3 (`query`, `get_where`, query-builder delete, dan lookup awal) masih dapat mencatat SQL/detail error ke application log walau `db_debug=false`. Fixer mengganti seluruh read/lock/duplicate/insert/delete pada dua writer dengan prepared mysqli langsung, bind integer, `bind_result/fetch`, error code numerik, dan smoke failure fixtures. Auditor re-review menyatakan PASS.
- File berubah:
  - `application/controllers/Master_relation.php`
  - `tools/tests/master_relation_product_extra_mutation_csrf_smoke.php`
- Perubahan utama:
  - `product_extra_store` memulai transaksi terverifikasi, mengunci parent `mst_extra_group` sebelum revalidasi product/group, duplicate check, dan prepared insert; begin/status/commit/prepare/bind/execute failure di-rollback dan tidak pernah menghasilkan sukses palsu; duplicate `1062` tetap warning.
  - `product_extra_delete` melakukan initial lookup aman, mengunci parent dahulu bila ada, lalu exact mapping `id + extra_group_id` dengan `FOR UPDATE`; orphan parent-missing tetap bisa dibersihkan; exact delete wajib affected rows tepat satu; stale/zero-row menjadi warning, bukan sukses.
  - Seluruh alur B68 memakai `db_debug=false` boundary dan helper prepared langsung sehingga CI DB driver tidak menerima query yang dapat bocor; log hanya context method/id dan errno numerik; kebijakan delete historical inactive tidak berubah.
  - Smoke diperluas dari 350 menjadi 428 checks untuk lock order, prepared result/mutation failures, rollback, no-false-success, affected-row anomaly, duplicate 1062, orphan/inactive semantics, dan log redaction.
- Validasi yang dijalankan:
  - `php -l` controller dan smoke: lulus.
  - B68 product-extra smoke: PASS 428 checks; B67 checklist: PASS 344; B67 AJAX: PASS 185; B66 preflight: PASS 113; production formula: PASS 191.
  - `git diff --check` dan `git diff-files --check`: lulus; kedua target B68 tidak memiliki unstaged overlay; target tetap staged tanpa commit.
  - Auditor re-review: PASS, tanpa defect B68 yang memerlukan perbaikan.
  - `git diff --cached --check` dan cached name listing belum dapat dijalankan: repository memiliki korupsi object yang telah ada, termasuk missing HEAD tree `b3d09b6c42f0e6cf8e53f2dc5f5bb93091f02e44`, banyak missing blob/tree/cache-tree, dan invalid remote pointer. Tidak ada upaya destructive untuk memulihkan object pada batch ini.
- Risiko sisa:
  - Contention/deadlock InnoDB nyata dan observasi application log pada error DB belum diuji; live/staging preflight B66 serta UAT dua session masih wajib.
  - B68 delete belum mengunci parent pada jalur tersendiri bila parent orphan; mapping row menjadi lock target sesuai kebutuhan cleanup. Arah Extra → Group tetap belum concurrency-hardened.
  - Korupsi object Git menghalangi pemeriksaan cached diff/status normal dan harus dipulihkan/ditangani terpisah sebelum packaging/release; jangan menjalankan commit dari index kumulatif 46 file tanpa manifest/whitelist.
- Batch berikutnya: auditor memilih perbaikan read-only/repository integrity dan/atau hardening concurrency arah Extra → Group setelah blocker Git ditangani dengan prosedur recovery aman; tidak melakukan remediation data otomatis atau License Hub.

## Batch 69 — Component HPP/value reconciliation tanpa perubahan saldo

- Waktu: 2026-09-03 13:00 WIB.
- Prioritas: HIGH integrity/value — mismatch SAUCE BANGKOK menunjukkan qty lot dan stok dapat sama sementara nilai/HPP berbeda; adjustment fisik tidak boleh dipakai untuk memperbaiki nilai.
- Ringkasan diskusi auditor/fixer: auditor mengarahkan agar reconcile component membuka `InventoryValueReconciliationService`, membawa `monthly_stock_id`, dan hanya menampilkan jalur koreksi pada bulan aktif ketika qty stok = qty lot. Saran HPP dibuat read-only dari weighted lot OPEN, formula live, formula normal, dan hpp standar; tidak ada auto-post.
- File berubah:
  - `application/libraries/InventoryValueReconciliationService.php`
  - `application/controllers/Inventory_control.php`
  - `application/controllers/Production.php`
  - `application/models/Production_model.php`
  - `application/views/inventory/stock_value_reconciliation_index.php`
  - `application/views/production/component_reconcile_index.php`
- Perubahan utama:
  - Reconcile component meneruskan ID saldo bulanan dan menyediakan tombol `Koreksi HPP/nilai` ke halaman koreksi nilai untuk mismatch nilai murni bulan aktif.
  - Koreksi manual dapat diisi sebagai HPP/unit; total dihitung dari qty saldo dan backend menghitung ulang memakai qty yang terkunci dalam transaksi.
  - Tambahan guard snapshot qty mencegah posting memakai saldo lama; angka negatif tetap ditolak dan lot CLOSED tidak disentuh.
  - Halaman menampilkan HPP stok saat ini sebagai diagnostik, weighted HPP lot OPEN, HPP formula live berbasis weighted lot, HPP formula normal, dan HPP standar master sebagai saran eksplisit.
- Validasi yang dijalankan:
  - `php -l` keenam file PHP berubah: lulus.
  - Dashboard component value mismatch smoke: PASS 19 checks.
  - Inventory control mutation CSRF smoke: PASS 272 checks.
  - Production component formula mutation CSRF smoke: PASS 191 checks.
  - `composer validate --no-check-publish`: `composer.json` valid; hanya muncul deprecation notice dari Composer lama/PHP runtime.
  - `git diff --check` pada target batch: lulus.
- Hasil review auditor: putaran pertama menemukan guard tanggal, kecocokan scope formula, mode source lot, validasi unit/total, dan output resep nol; seluruh temuan tersebut ditutup termasuk perbandingan divisi null-safe. Validasi ulang lint dan smoke tetap lulus.
- Risiko sisa:
  - Koreksi SAUCE BANGKOK belum diposting; operator tetap harus memverifikasi resep/lot dan memilih HPP secara eksplisit.
  - Validasi browser dan query DB nyata untuk memastikan qty 785 tetap 785, nilai stock = nilai lot, audit header/line tercatat, dan closed lots tidak berubah masih wajib.
  - Fondasi tabel `inv_stock_value_reconciliation*` harus sudah dijalankan di server target.
- Batch berikutnya: UAT terarah SAUCE BANGKOK dan probe DB read-only; setelah bukti cocok, baru pertimbangkan hardening pembulatan/post-condition revaluasi.

## Batch 70 — Perbaikan tampilan nilai stok dan VOID koreksi nilai

- Waktu: 2026-09-03.
- Prioritas: HIGH integrity/recovery — hasil koreksi lot sudah berubah, tetapi reconcile masih menimpa nilai ledger dengan proyeksi movement lama; dokumen POSTED belum dapat dibatalkan secara aman.
- Ringkasan diskusi auditor/fixer: auditor mengidentifikasi overlay proyeksi harian sebagai penyebab nilai stok tampak belum berubah dan merekomendasikan void bersyarat berdasarkan snapshot exact-state. Implementasi menjaga histori dokumen, memulihkan nilai dalam transaksi, dan menolak void bila ada perubahan lanjutan.
- File berubah:
  - `application/models/Production_model.php`
  - `application/libraries/InventoryValueReconciliationService.php`
  - `application/controllers/Inventory_control.php`
  - `application/config/routes.php`
  - `application/views/inventory/stock_value_reconciliation_index.php`
  - `tools/tests/inventory_control_mutation_csrf_smoke.php`
- Perubahan utama:
  - Hari aktif memakai nilai/avg dari ledger `inv_component_monthly_stock` setelah koreksi; tanggal histori tetap read-only memakai proyeksi harian.
  - Endpoint `inventory/stock/value-reconciliation/void` men-lock dokumen, stok, dan lot; memverifikasi qty/nilai/HPP masih sama dengan hasil dokumen; lalu memulihkan nilai sebelum koreksi dan memberi status `VOID`.
  - Riwayat menampilkan tombol VOID untuk Superadmin, meminta alasan, dan tidak menghapus header/line/audit.
- Validasi yang dijalankan:
  - `php -l` seluruh file PHP/controller/view/model/service/test dan route: lulus.
  - Inventory control mutation CSRF smoke: PASS 312 checks.
  - Dashboard component value mismatch smoke: PASS 19 checks.
  - Production component formula mutation CSRF smoke: PASS 191 checks.
  - `git diff --check`: lulus.
- Hasil review auditor: PASS bersyarat; invariant qty, scope formula, source mode lot, validasi manual, output nol, dan cache weighted telah ditutup. UAT DB nyata tetap diperlukan.
- Risiko sisa:
  - Belum dilakukan void/post terhadap data SAUCE BANGKOK atau CHICKEN CUBE secara otomatis.
  - Void sengaja ditolak jika ada perubahan stok/HPP/koreksi lanjutan; dalam kondisi itu perlu audit manual, bukan overwrite.
  - UAT browser/DB wajib membuktikan qty tidak berubah, nilai stock = lot setelah post, dan nilai kembali ke sebelum dokumen setelah void.
- Batch berikutnya: UAT terarah dua component tersebut dan post-condition pembulatan revaluasi bila hasil DB menunjukkan sisa sen.

## Batch 71 — Pemisahan dua dokumen roadmap dan sinkronisasi status

- Waktu: 2026-09-03 13:50 WIB.
- Prioritas: dokumentasi/readiness — roadmap audit dan roadmap komersialisasi
  masih memiliki bagian yang tumpang tindih sehingga status bug, fondasi
  teknis, dan pekerjaan lisensi dapat terbaca sebagai satu antrean.
- Ringkasan diskusi auditor dan fixer: arah yang ditetapkan adalah `_30`
  sebagai sumber tunggal audit bug, security/RBAC, integritas stok/HPP,
  navigasi/UI, test, dependency, schema, backup, dan release foundation.
  `_28` menjadi sumber tunggal paket, harga, kontrak, productization customer,
  installer komersial, entitlement, License Hub, Product Control Center,
  pilot, support, dan penjualan. Laporan tanggal lain tetap hanya menjadi
  laporan batch/modul.
- File berubah:
  - `docs/2026-08-30_audit_total_aplikasi_finance_dan_roadmap_pengembangan.md`
  - `docs/2026-08-28_roadmap_komersialisasi_finance_dan_lisensi.md`
  - `docs/2026-09-03_progress_roadmap_user_finance.md`
  - `docs/2026-09-02_codex_execution_log.md`
- Perubahan utama:
  - Menambahkan checklist status P0/P1/P2 dan Batch 48, 53, 54–70 pada `_30`.
  - Memisahkan roadmap teknis `_30` menjadi Fase A0–A5 dan roadmap komersial
    `_28` menjadi Fase C0–C5.
  - Menandai Batch 69–70 sebagai workflow koreksi HPP/nilai dan VOID yang
    sudah lulus batch, tetapi repair data enam component serta UAT tetap
    terbuka.
  - Menghapus pengulangan langkah audit dari urutan komersialisasi dan
    menjadikan laporan progres 2026-09-03 sebagai laporan turunan.
- Validasi yang dijalankan:
  - Pembacaan ulang penuh dua dokumen induk dan execution log sampai Batch 70.
  - Pemeriksaan heading, status checklist, dan istilah lintas dokumen.
  - `git diff --check` untuk tiga dokumen target: lulus.
  - Tidak ada perubahan kode, database, SQL, route, config, backup, upload,
    credential, atau data runtime pada batch ini.
- Hasil review auditor: review awal menemukan klaim status lama pada P0-04,
  P0-06, P1-09, P1-10, P1-11, serta sisa tanggung jawab generator/PCC di `_30`.
  Semua koreksi tersebut sudah diterapkan; auditor menyatakan checklist Batch
  69–70 konsisten dan pemisahan scope `_30`/`_28` sudah jelas.
- Risiko sisa:
  - Beberapa bagian lama di `_30` dan `_28` masih berupa spesifikasi teknis
    yang saling merujuk; ownership sudah ditegaskan, tetapi perlu dipertahankan
    pada setiap batch baru.
  - Status database live, UAT browser, dan object Git tetap mengikuti checklist
    `_30`, bukan dianggap selesai hanya karena dokumen sudah diperbarui.
- Batch berikutnya: kembali ke Fase A2 — probe read-only dan preview repair
  enam mismatch component, lalu perbaiki trigger rebuild cache HPP live setelah
  bukti database tersedia.

## Batch 72 — Sinkronisasi cache HPP live setelah koreksi nilai

- Waktu: 2026-09-03.
- Prioritas: HIGH integritas HPP — koreksi/VOID nilai persediaan sudah mengubah
  ledger dan lot, tetapi cache availability POS/HPP live belum otomatis ikut
  dihitung ulang.
- Ringkasan diskusi auditor/fixer: auditor memilih hook post-commit terkecil.
  Setelah transaksi koreksi atau VOID commit, service memanggil rebuild
  availability sesuai domain component/material. Kegagalan queue/rebuild tidak
  boleh membatalkan koreksi yang sudah tersimpan; hasilnya dikembalikan sebagai
  metadata warning.
- File berubah:
  - `application/libraries/InventoryValueReconciliationService.php`
  - `tools/tests/inventory_value_reconciliation_availability_smoke.php`
- Perubahan utama:
  - `post()` dan `voidRecord()` memanggil `refreshAvailabilityAfterCommit()` di
    luar blok rollback setelah `trans_commit()`.
  - Component diarahkan ke `handle_component_change`, material ke
    `handle_material_change`, dengan `trigger_context`, `event_source`,
    `event_table`, event ID, dan actor user.
  - Koreksi tetap dilaporkan sukses bila cache gagal; warning dan log tersedia
    untuk tindak lanjut worker/cache.
- Validasi yang dijalankan:
  - `php -l` service dan smoke: lulus.
  - Availability smoke: PASS 19 checks.
  - Inventory control mutation smoke: PASS 312 checks.
  - `git diff --check`: lulus.
- Hasil review auditor: PASS; urutan post-commit, isolasi exception, dispatch
  domain, dan metadata event sesuai.
- Risiko sisa:
  - Queue/worker dan cache DB nyata belum diuji; warning belum dibuat sebagai
    notifikasi UI khusus.
  - Data mismatch historis tidak diubah pada batch ini.
- Batch berikutnya: Fase A1 POS Mobile, dimulai dari POST-only seluruh writer.

## Batch 73 — Fase A1 POS Mobile: POST-only boundary

- Waktu: 2026-09-03.
- Prioritas: P0 security/RBAC — endpoint writer POS Mobile masih perlu menolak
  GET/PUT secara eksplisit sebelum autentikasi, pembacaan payload, query
  sinkronisasi, revoke token, model, atau percobaan printer.
- Ringkasan diskusi auditor/fixer: auditor memilih boundary request terkecil
  tanpa perubahan schema/route. Fixer menambahkan helper `require_mobile_post()`
  pada login, logout, printer test, order save/confirm/push, payment save,
  void/refund, dan buka/tutup kasir. Logout juga mengautentikasi token/session
  sebelum revoke.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
- Perubahan utama:
  - Semua 11 writer menolak metode selain POST dengan HTTP 405 dan daftar
    metode yang diizinkan.
  - Guard berada sebelum auth, payload, model, query sync, printer attempt,
    dan perubahan token.
  - Smoke dibuat table-driven untuk GET dan PUT pada seluruh writer dan
    membuktikan tidak ada side effect, termasuk `logout` tidak merevoke token.
- Validasi yang dijalankan:
  - `php -l` controller dan smoke terkait: lulus.
  - POS Mobile authorization smoke: PASS; 22 kasus GET dan 22 kasus PUT.
  - Inventory control mutation smoke: PASS 312 checks.
  - Cache availability smoke: PASS 19 checks.
  - `git diff --check`: lulus.
- Hasil review auditor: PASS; urutan guard dan cakupan smoke memenuhi syarat
  batch.
- Risiko sisa:
  - POS Mobile masih belum memiliki binding terminal/outlet/device yang kuat,
    step-up untuk void/refund, dan CSRF scoped bila memakai fallback session.
  - UAT HTTP/APK dengan akun dan perangkat nyata belum dilakukan.
- Batch berikutnya: lanjutkan A1 POS Mobile pada binding scope device/outlet
  atau action policy step-up, setelah memilih perubahan kecil yang tidak
  memerlukan migration baru.

## Batch 74 — Fase A1 POS Mobile: device binding fail-closed

- Waktu: 2026-09-03 16:20 WIB.
- Prioritas: P0 security — token mobile sebelumnya hanya menyimpan device key
  tetapi tidak memverifikasi bahwa request datang dari terminal aktif yang sama.
- Ringkasan diskusi auditor/fixer: auditor memilih binding device sebagai
  primitive yang sudah didukung schema, sementara step-up ditunda karena belum
  memiliki approval, TTL, dan audit contract. Fixer menambahkan validasi terminal
  aktif/unik untuk login dan bearer request, lalu auditor menemukan credential
  oracle pada urutan/pesan login. Urutan kemudian diperbaiki dan kegagalan
  kredensial/perangkat diseragamkan.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
- Perubahan utama:
  - Login mewajibkan identifier, password, dan `terminal_device_key`; field
    kosong ditolak sebelum autentikasi/lookup. Setelah kredensial valid, key
    harus menunjuk tepat satu `pos_terminal` aktif.
  - Kredensial salah dan terminal unknown/nonaktif/duplikat memakai 401 generik
    yang sama, sehingga status registry tidak dapat dijadikan oracle.
  - Bearer token wajib membawa `X-Pos-Mobile-Device-Key`; key dibandingkan
    dengan `hash_equals`, terminal harus aktif dan unik, baru `last_seen_at`
    diperbarui. Token lama tanpa binding otomatis ditolak; invalid bearer tidak
    fallback ke session/API key.
  - Device key tidak ditulis ke response atau log oleh endpoint ini. Logout
    mengikuti binding yang sama.
- Validasi yang dijalankan:
  - `php -l` controller dan smoke: lulus.
  - POS Mobile authorization smoke: lulus, termasuk seluruh writer GET/PUT
    405, kasus anti-oracle login, token legacy, key missing/mismatch, terminal
    nonaktif/duplikat, invalid bearer, dan logout binding.
  - Availability smoke: PASS 19 checks; inventory-control smoke: PASS 312
    checks.
  - `git diff --check`: lulus.
- Hasil review auditor: PASS setelah perbaikan anti-oracle; alur credential →
  device registry → RBAC/token issuance dan bearer header → token → terminal →
  `last_seen_at` dinyatakan benar.
- Risiko sisa:
  - Device key masih static shared secret, bukan hardware attestation.
  - Token lama tanpa binding harus login ulang; rollout APK harus mengirim
    device key pada body login dan header `X-Pos-Mobile-Device-Key` pada setiap
    request bearer.
  - Binding outlet/order dan step-up void/refund/reprint belum selesai; UAT
    HTTP/APK nyata belum dilakukan.
- Batch berikutnya: auditor memilih binding scope outlet/order atau rancangan
  action policy step-up, tetap satu batch kecil dan tanpa migration baru bila
  primitive existing mencukupi.

## Batch 75 — Fase A1 POS Mobile: terminal-bound cashier session

- Waktu: 2026-09-03 18:25 WIB.
- Prioritas: P0 security/scope — token yang sudah terikat device/terminal juga
  harus mencegah sesi kasir dibuka, ditutup, atau dilihat dari outlet/terminal
  yang berbeda.
- Ringkasan diskusi auditor dan fixer: auditor memilih guard controller kecil
  tanpa perubahan schema. Fixer menambahkan konteks `terminal_id` dan `outlet_id`
  dari terminal aktif pada bearer token, memeriksa konteks request, dan
  memvalidasi sesi kasir aktif sebelum operasi bisnis. Auditor meminta dua
  koreksi: `session_status` tanpa sesi aktif harus tetap 200, dan `cashier_open`
  harus menolak sesi aktif yang masih terikat terminal lain sebelum recon/open.
  Keduanya diterapkan dan direview PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
  - `docs/2026-08-30_audit_total_aplikasi_finance_dan_roadmap_pengembangan.md`
  - `docs/2026-09-03_progress_roadmap_user_finance.md`
  - `docs/2026-09-02_codex_execution_log.md`
- Perubahan utama:
  - `cashier_open` mewajibkan `outlet_id` dan `terminal_id` yang sama dengan
    terminal token, serta menolak active session milik employee yang terikat
    terminal/outlet lain sebelum daily recon dan pembukaan sesi.
  - `cashier_close` dan `cashier_close_preview` hanya berjalan untuk sesi aktif
    pada terminal/outlet token.
  - `session_status` bearer hanya mengembalikan sesi milik perangkat; tanpa
    sesi aktif mengembalikan `session: null` dan `active_sessions: []`, bukan
    daftar sesi global.
  - Mismatch terminal/outlet, sesi nonaktif, dan sesi existing lintas terminal
    ditolak dengan HTTP 403 tanpa memanggil operasi bisnis, recon, writer, atau
    printer.
- Validasi yang dijalankan:
  - `php -l` controller, service, dan smoke test: lulus.
  - POS Mobile authorization smoke: PASS.
  - Availability smoke: PASS 19 checks.
  - `git diff --check` pada file source dan dokumen: lulus.
  - Tidak ada SQL/schema/database/backup/upload/credential/runtime yang diubah.
- Hasil review auditor: PASS. Auditor menyatakan binding sesi kasir dan
  pemisahan daftar sesi bearer sudah benar.
- Risiko sisa:
  - Masih ada race kecil bila sesi berubah bersamaan dari web/perangkat lain;
    validasi atomik berbasis session-id/terminal dapat menjadi batch berikutnya.
  - Binding belum merata ke seluruh endpoint katalog/order/read, step-up
    void/refund/reprint belum ada, dan UAT HTTP/APK nyata belum dilakukan.
  - Fallback web-session sengaja tetap kompatibel dan masih dapat menampilkan
    daftar sesi global.
- Batch berikutnya: lanjutkan A1 POS Mobile pada binding scope katalog/order/read
  yang dapat dipakai APK, lalu action policy step-up; tetap tanpa migration baru
  bila primitive schema yang ada sudah mencukupi.

## Batch 76 — Fase A1 POS Mobile: bearer outlet scope untuk order

- Waktu: 2026-09-03 18:31 WIB.
- Prioritas: P0 security/scope — endpoint daftar order dapat jatuh ke filter
  outlet `0`, sedangkan detail order berbasis ID belum memeriksa outlet token.
- Ringkasan diskusi auditor dan fixer: auditor memilih dua endpoint dengan risiko
  paling jelas dan dampak paling kecil, yaitu `orders()` dan `order_load($id)`.
  Fixer mempertahankan fallback web-session, tetapi membuat bearer memakai
  outlet terminal token secara authoritative. Review awal auditor menemukan
  fixture smoke tidak mengikuti kontrak nyata `find_order_draft()` karena
  memakai `outlet_id` datar. Fixer kemudian memperbaiki source dan fixture untuk
  memakai `header.outlet_id`; auditor mereview ulang dan menyatakan PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
  - `docs/2026-08-30_audit_total_aplikasi_finance_dan_roadmap_pengembangan.md`
  - `docs/2026-09-03_progress_roadmap_user_finance.md`
  - `docs/2026-09-02_codex_execution_log.md`
- Perubahan utama:
  - Bearer `orders()` tidak lagi mengambil outlet dari sesi kasir; endpoint
    memakai `mobileUser.outlet_id` dan menolak konteks outlet tidak valid dengan
    403 sebelum query order.
  - Bearer `order_load($id)` hanya mengembalikan order dengan `outlet_id` yang
    sama. Order lintas outlet atau tanpa outlet menghasilkan 404 generik tanpa
    payload order/customer/alamat.
  - Order dari terminal lain dalam outlet yang sama tetap dapat dilihat agar
    antrean kerja kasir tidak berubah.
  - Jalur web-session tanpa bearer tetap mempertahankan perilaku lama.
- Validasi yang dijalankan:
  - `php -l` controller dan smoke test: lulus.
  - POS Mobile authorization smoke: PASS.
  - Smoke memakai struktur nyata `find_order_draft()` (`header` + `lines`) dan
    memverifikasi same outlet, terminal lain, cross/missing outlet, outlet `0`,
    serta fallback web.
  - `git diff --check` dan pemeriksaan trailing whitespace: lulus.
  - Tidak ada SQL/schema/model/config/database/backup/upload/credential/runtime
    yang diubah.
- Hasil review auditor: PASS. Scope outlet, 404 generik, pencegahan kebocoran
  payload, dan kompatibilitas web dinyatakan sesuai.
- Risiko sisa:
  - Endpoint order berbasis ID lain (`order_reversal_preview`, print-target,
    `payment_prepare`, `voucher_search`, dan writer) masih perlu scope.
  - `bootstrap`, `catalog`, dan `printers` masih menjadi batch berikutnya;
    validasi query database nyata belum dilakukan.
- Batch berikutnya: lanjutkan scope endpoint order berbasis ID yang tersisa,
  lalu resolver outlet untuk bootstrap/catalog/printer sebelum action policy
  step-up; tetap tanpa migration bila schema yang ada mencukupi.

## Batch 77 — Fase A1 POS Mobile: scope lima endpoint order-id

- Waktu: 2026-09-03 18:40 WIB.
- Prioritas: P0 security/scope — preview reversal, target cetak order,
  persiapan pembayaran, dan pencarian voucher menerima ID order tanpa guard
  outlet bearer.
- Ringkasan diskusi auditor dan fixer: auditor memilih lima endpoint dengan
  resolver order yang sama: `order_reversal_preview`, `order_reprint_targets`,
  `order_confirm_print_targets`, `payment_prepare`, dan `voucher_search`.
  Fixer menambahkan helper outlet bearer tanpa mengubah model atau schema.
  Review awal menemukan fixture smoke memakai bentuk order datar, sedangkan
  kontrak nyata memakai `header` dan `lines`. Fixer menyamakan source serta
  fixture ke `header.outlet_id`; auditor mereview ulang dan menyatakan PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
  - `docs/2026-08-30_audit_total_aplikasi_finance_dan_roadmap_pengembangan.md`
  - `docs/2026-09-03_progress_roadmap_user_finance.md`
  - `docs/2026-09-02_codex_execution_log.md`
- Perubahan utama:
  - Bearer harus memiliki `mobileUser.outlet_id` yang valid; outlet `0` ditolak
    dengan 403 sebelum resolver order.
  - Order tidak ada, outlet kosong, atau outlet berbeda menghasilkan 404 generik
    tanpa payload dan tanpa pemanggilan model preview/payment/voucher/printer
    downstream.
  - Terminal berbeda tetapi outlet sama tetap diizinkan; web-session fallback
    tidak berubah.
- Validasi yang dijalankan:
  - `php -l` controller dan smoke test: lulus.
  - POS Mobile authorization smoke penuh: PASS.
  - Same outlet, terminal lain, cross outlet, missing order, outlet `0`,
    no-downstream, dan web fallback: PASS.
  - `git diff --check` dan pemeriksaan trailing whitespace: lulus.
  - Tidak ada SQL/schema/model/config/database/backup/upload/credential/runtime
    yang diubah.
- Hasil review auditor: PASS setelah perbaikan struktur fixture/model contract.
- Risiko sisa:
  - Endpoint document-ID void/refund/payment print dan writer void/refund/payment
    belum diberi resolver outlet.
  - `bootstrap`, `catalog`, dan `printers` masih belum sepenuhnya terikat outlet
    bearer; validasi database nyata belum dilakukan.
- Batch berikutnya: pilih satu resolver document-ID atau binding outlet
  bootstrap/catalog/printer, lalu lanjutkan action policy step-up secara terarah.

## Batch 78 — Fase A1 POS Mobile: scope writer finansial

- Waktu: 2026-09-03 19:01 WIB.
- Prioritas: P0 security/scope — bearer masih dapat mengirim `order_id` outlet
  lain ke writer void, refund, dan payment; replay payment juga berpotensi
  membaca sync-event sebelum outlet diverifikasi.
- Ringkasan diskusi auditor dan fixer: auditor memilih tiga writer finansial
  sebagai risiko tertinggi. Fixer menggunakan resolver order-outlet yang sudah
  lulus Batch 77 dan menempatkannya sebelum model, monitor task, serta seluruh
  lookup/insert/update sync-event. Auditor mereview diff dan smoke lalu PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
  - `docs/2026-08-30_audit_total_aplikasi_finance_dan_roadmap_pengembangan.md`
  - `docs/2026-09-03_progress_roadmap_user_finance.md`
  - `docs/2026-09-02_codex_execution_log.md`
- Perubahan utama:
  - `order_void_save`, `order_refund_save`, dan `payment_save` memvalidasi
    `payload.order_id` terhadap outlet token setelah POST/auth/RBAC.
  - Outlet token tidak valid menghasilkan 403; order hilang atau lintas outlet
    menghasilkan 404 generik tanpa writer, monitor, atau sync-event downstream.
  - Payment replay lintas outlet tidak dapat membaca response event lama.
  - Terminal lain pada outlet sama dan fallback web-session tetap kompatibel.
- Validasi yang dijalankan:
  - `php -l` controller dan smoke: lulus.
  - POS Mobile authorization smoke penuh: PASS.
  - Matrix tiga writer untuk same/cross/missing/outlet-zero/invalid order,
    no-downstream, replay, web fallback, serta GET/PUT 405: PASS.
  - `git diff --check` target batch: lulus.
  - Tidak ada SQL/schema/model/config/database/runtime yang diubah.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: document-print void/refund/payment masih belum outlet-bound dan
  smoke belum menjadi probe HTTP/database nyata.
- Batch berikutnya: resolver outlet untuk tiga endpoint document-print mobile.

## Batch 79 — Fase A1 POS Mobile: scope document-print

- Waktu: 2026-09-03 19:08 WIB.
- Prioritas: P0 security/scope — endpoint cetak void, refund, dan payment
  menerima document ID tanpa memverifikasi outlet sebelum direct-print dapat
  membuat `print_attempt`.
- Ringkasan diskusi auditor dan fixer: auditor menetapkan resolver dokumen ke
  order/outlet kanonik. Fixer menambahkan resolver model untuk VOID, REFUND,
  dan PAYMENT serta guard controller. Smoke awal memiliki source assertion
  yang salah format dan sudah diperbaiki; smoke penuh serta review auditor PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `application/models/Pos_model.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Resolver memakai allowlist tabel dokumen dan `INNER JOIN pos_order`; outlet
    serta terminal berasal dari order kanonik.
  - Payment deposit tanpa order, dokumen orphan/hilang, outlet nol, dan dokumen
    lintas outlet menghasilkan 404 generik tanpa direct-print/print-attempt.
  - Bearer dengan outlet token nol ditolak 403 sebelum resolver; terminal lain
    pada outlet sama dan fallback web-session tetap kompatibel.
- Validasi yang dijalankan:
  - `php -l` controller, model, dan smoke: lulus.
  - POS Mobile authorization smoke penuh: PASS.
  - Matrix tiga document-print, source-order assertion, no-downstream,
    payment-deposit, dan web fallback: PASS.
  - `git diff --check` target batch: lulus.
  - Tidak ada SQL/schema/config/database/runtime yang diubah.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: UAT printer/config/schema nyata belum dilakukan.
- Batch berikutnya: binding outlet bearer untuk bootstrap, catalog, dan printer.

## Batch 80A — Fase A1 POS Mobile: scope bootstrap dan katalog

- Waktu: 2026-09-03 19:19 WIB.
- Prioritas: P0 security/scope — bearer dapat meminta outlet lain pada bootstrap
  atau katalog dan respons bootstrap membawa daftar outlet, terminal, serta sesi
  kasir global.
- Ringkasan diskusi auditor dan fixer: auditor memisahkan katalog/bootstrap dari
  kebijakan printer agar perubahan tetap kecil. Fixer mengikat request ke outlet
  dan terminal token, membatasi seluruh pilihan serta sesi pada respons, lalu
  menambah matrix smoke. Review menemukan `filter_options` top-level juga membawa
  outlet/terminal global; fixer ikut membatasinya. Auditor mereview ulang dan PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Outlet/terminal binding bearer wajib positif; request outlet positif yang
    berbeda ditolak 403 sebelum model bisnis.
  - Katalog selalu memakai outlet perangkat. Bootstrap menetapkan default outlet
    dan terminal perangkat serta hanya mengembalikan pilihan outlet, terminal,
    dan sesi kasir yang cocok.
  - Pegawai tanpa sesi aktif tetap memperoleh respons 200 dengan sesi null/kosong;
    sesi aktif pada outlet/terminal lain ditolak.
  - Jalur web-session mempertahankan perilaku lama. Kebijakan printer belum diubah.
- Validasi yang dijalankan:
  - `php -l` controller dan smoke: lulus.
  - POS Mobile authorization smoke penuh: 683 pemeriksaan PASS.
  - Matrix mismatch request/binding/session, no-session, scope response/catalog,
    no-downstream, dan web fallback: PASS.
  - `git diff --check` dan trailing-whitespace check: lulus.
  - Tidak ada SQL/schema/model/config/database/runtime yang diubah.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: `order_reprint_printers` dan endpoint printer menunggu keputusan
  kebijakan printer global; smoke belum berupa probe HTTP/database/APK nyata.
- Batch berikutnya: authoritative order-upsert binding untuk `order_save`,
  `order_confirm`, dan `orders_push` sebelum model/sync-event.

## Batch 81 — Fase A1 POS Mobile: authoritative order-upsert

- Waktu: 2026-09-03 19:28 WIB.
- Prioritas: P0 security/scope — payload simpan/konfirmasi/push order masih dapat
  membawa outlet/terminal sendiri dan replay sync-event mendahului scope order.
- Ringkasan diskusi auditor dan fixer: auditor menetapkan konteks perangkat harus
  menjadi sumber otoritatif sebelum writer/sync. Fixer menambahkan satu helper
  bersama untuk tiga endpoint dan binding replay event. Auditor mereview diff,
  kontrak field `id`, serta 715 smoke dan menyatakan PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Outlet/terminal kosong diisi dari token; nilai positif berbeda ditolak 403.
  - Existing order wajib lolos resolver outlet sebelum writer dan sebelum akses
    sync-event. New order wajib memiliki sesi kasir perangkat yang cocok.
  - Replay memvalidasi konteks pada `request_json`; event lama tanpa konteks hanya
    diterima bila `server_order_id` lolos resolver outlet.
  - Respons replay lintas outlet/terminal tidak bocor; web-session tetap legacy.
- Validasi yang dijalankan:
  - `php -l` controller dan smoke: lulus.
  - POS Mobile authorization smoke penuh: 715 pemeriksaan PASS.
  - Matrix new/same/cross/missing/no-session/payload mismatch/replay dan
    no-downstream: PASS.
  - `git diff --check` dan trailing-whitespace: lulus.
  - Tidak ada SQL/schema/model/config/database/runtime yang diubah.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: bukti HTTP/database/APK nyata dan kebijakan printer masih terbuka.
- Batch berikutnya: hapus fallback hak Purchase Order dari direct URL opening
  stock divisi dan export template.

## Batch 82 — Fase A1 Purchase: direct URL opening stock divisi

- Waktu: 2026-09-03 19:34 WIB.
- Prioritas: P0 authorization — permission Purchase Order dapat menjadi fallback
  untuk halaman dan export opening stock divisi.
- Ringkasan diskusi auditor dan fixer: auditor menemukan fallback lintas domain
  pada tiga endpoint. Fixer menghapus fallback dan membuat matrix role dengan
  tripwire downstream. Auditor mereview ulang dan PASS.
- File berubah:
  - `application/controllers/Purchase.php`
  - `tools/tests/purchase_stock_opening_authorization_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Index/generated wajib `purchase.stock.division.index:view`.
  - Export template hanya menerima view atau create pada page stock divisi.
  - Permission Purchase Order saja tidak lagi membuka query, render, atau XLSX.
  - Perilaku opening warehouse tidak diubah.
- Validasi yang dijalankan:
  - `php -l` controller dan smoke: lulus.
  - Smoke source/runtime: 46 pemeriksaan PASS.
  - Matrix order-only/none/anonymous/division-view/division-create dan
    no-downstream: PASS.
  - `git diff --check` dan trailing-whitespace: lulus.
  - Tidak ada SQL/schema/model/view/config/database/runtime yang diubah.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: probe HTTP/RBAC/database nyata belum dijalankan.
- Batch berikutnya: POST-only dan scoped CSRF untuk rebuild/reclassify Purchase.

## Batch 83 — Fase A1 Purchase: POST/CSRF maintenance

- Waktu: 2026-09-03 19:39 WIB.
- Prioritas: P0 request integrity — rebuild impact dan reclassify dapat dipanggil
  tanpa pembatasan metode dan tanpa token CSRF saat proteksi global nonaktif.
- Ringkasan diskusi auditor dan fixer: auditor menetapkan token khusus domain
  maintenance Purchase, bukan token POS. Fixer menambah guard controller dan
  header pada dua halaman; smoke dibuat ringkas agar batch tidak melebar.
  Auditor mereview diff dan 23 pemeriksaan lalu PASS.
- File berubah:
  - `application/controllers/Purchase.php`
  - `application/views/purchase/rebuild_impact_index.php`
  - `application/views/purchase/reclassify_profile_domain_index.php`
  - `tools/tests/purchase_maintenance_mutation_csrf_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Dua run hanya menerima POST; metode lain mendapat JSON 405 dan `Allow: POST`.
  - Token session 64-hex dikirim hanya lewat header khusus dan dibandingkan
    fail-closed dengan `hash_equals`; token tidak valid mendapat 403 generik.
  - Urutan tetap RBAC edit, request guard, payload, lalu model; actor, IP, dan
    pemulihan `db_debug` melalui `finally` dipertahankan.
- Validasi yang dijalankan:
  - `php -l` controller, dua view, dan smoke: lulus.
  - Smoke source/helper: 23 pemeriksaan PASS.
  - Matrix method/token/view-only/success/error/exception dan source view: PASS.
  - `git diff --check` dan trailing-whitespace: lulus.
  - Tidak ada SQL/schema/model/config/database/runtime yang diubah.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: browser/session/database nyata belum diuji.
- Batch berikutnya: Master generic form `store/update` dengan POST-only dan
  scoped CSRF.

## Batch 84A — Fase A1 Master: CSRF form generik

- Waktu: 2026-09-03 19:45 WIB.
- Prioritas: P0/P1 request integrity — store/update Master berjalan saat CSRF
  global nonaktif dan belum mewajibkan metode POST serta token lokal.
- Ringkasan diskusi auditor dan fixer: auditor memecah Master menjadi form,
  mutasi inline, dan generator holiday. Fixer membuat fondasi token domain Master,
  memasangnya pada form generik, serta menjaga urutan redirect/config/RBAC lama.
  Auditor mereview 18 pemeriksaan dan menyatakan PASS.
- File berubah:
  - `application/controllers/Master.php`
  - `application/views/master/form.php`
  - `tools/tests/master_generic_form_mutation_csrf_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Create/edit menerbitkan token session 64-hex setelah config dan RBAC.
  - Store/update wajib POST dan token valid sebelum validation, lookup row,
    payload, upload, insert/update, atau sinkronisasi employee.
  - Form multipart produk tetap bekerja; redirect/flash dan contract redirect
    lama dipertahankan.
- Validasi yang dijalankan:
  - `php -l` controller, form, dan smoke: lulus.
  - Smoke source/helper: 18 pemeriksaan PASS.
  - GET/PUT/token invalid/no-writer, valid token, header, token generator, dan
    multipart: PASS.
  - `git diff --check` dan trailing-whitespace: lulus.
  - Tidak ada SQL/schema/model/config/database/runtime yang diubah.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: DB/browser nyata belum diuji; toggle/stock mode/reorder/holiday
  belum memakai guard baru.
- Batch berikutnya: mutasi inline Master `toggle`, `stock_mode`, dan `reorder`.

## Batch 84B — Fase A1 Master: CSRF mutasi inline

- Waktu: 2026-09-03 19:50 WIB.
- Prioritas: P0/P1 request integrity — toggle dan stock mode dapat dimutasi lewat
  GET, sedangkan reorder belum memiliki CSRF lokal.
- Ringkasan diskusi auditor dan fixer: fixer memakai token Master dari 84A,
  menambah respons error sesuai tipe request, memperbarui AJAX, dan mengganti
  link toggle legacy dengan form POST. Auditor mereview dan menyatakan PASS.
- File berubah:
  - `application/controllers/Master.php`
  - `application/views/master/index.php`
  - `application/views/master/material_index.php`
  - `tools/tests/master_generic_inline_mutation_csrf_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Toggle, stock mode, dan reorder memeriksa RBAC lalu POST/token sebelum
    lookup, schema, payload JSON, transaksi, atau writer.
  - AJAX mengirim header CSRF dan tetap menerima JSON; form fallback memakai
    hidden token dan mempertahankan flash/redirect.
  - Entity/reorder yang tidak didukung tetap memakai kontrak error lama.
- Validasi yang dijalankan:
  - `php -l` empat file: lulus.
  - Smoke inline 15 pemeriksaan dan regresi form 18 pemeriksaan: PASS.
  - `git diff --check` dan trailing-whitespace: lulus.
  - Tidak ada SQL/schema/model/config/database/runtime yang diubah.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: browser nyata dan regenerasi session belum diuji.
- Batch berikutnya: CSRF untuk generator hari libur tahunan Master.

## Batch 84C — Fase A1 Master: CSRF generator hari libur

- Waktu: 2026-09-03 19:54 WIB.
- Prioritas: P1 request integrity — generator massal hari libur hanya memeriksa
  metode, tetapi belum memakai token saat CSRF global nonaktif.
- Ringkasan diskusi auditor dan fixer: generator dipisahkan karena membaca schema
  `core` dan menjalankan banyak upsert. Fixer memakai token Master yang sama;
  auditor mereview urutan guard dan menyatakan PASS.
- File berubah:
  - `application/controllers/Master.php`
  - `application/views/master/index.php`
  - `tools/tests/master_att_holiday_generate_csrf_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Permission create lalu POST/token diverifikasi sebelum tahun, query sumber,
    atau upsert.
  - Form Generate 1 Tahun membawa hidden token ter-escape.
  - Validasi tahun, flash, redirect, sumber core, dan alur upsert lama tetap ada.
- Validasi yang dijalankan:
  - `php -l` tiga file: lulus.
  - Smoke holiday 9, regresi 84A 18, dan regresi 84B 15: PASS.
  - `git diff --check` dan trailing-whitespace: lulus.
  - Tidak ada SQL/schema/model/config/database/runtime yang diubah.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: upsert massal lama belum transaksional; DB/browser nyata belum diuji.
- Batch berikutnya: integrity favorite sidebar (POST/CSRF, permission, ownership,
  dan cache invalidation).

## Batch 85A — Fase A1 Sidebar: integrity favorite user

- Waktu: 2026-09-03 20:09 WIB.
- Prioritas: P0/P1 authorization/request integrity — endpoint favorite hanya
  memeriksa AJAX, pin menerima menu arbitrer, favorite lama dapat membocorkan URL,
  dan cache reorder dapat stale.
- Ringkasan diskusi auditor dan fixer: fixer menambah token khusus, predicate
  akses kanonis, ownership reorder, dan invalidasi cache. Review pertama auditor
  menemukan reload penuh, subset reorder, dan tombol UI yang belum memakai
  predicate. Ketiganya diperbaiki; review ulang PASS.
- File berubah:
  - `application/controllers/Sidebar.php`
  - `application/models/Menu_model.php`
  - `application/core/MY_Controller.php`
  - `application/views/layout/footer.php`
  - `application/views/layout/sidebar.php`
  - `assets/js/app.js`
  - `tools/tests/sidebar_favorite_mutation_csrf_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Pin/unpin/reorder wajib POST dan header CSRF khusus sebelum payload/model.
  - Menu pin harus aktif, memiliki link nyata, dan lolos permission kanonis;
    favorite historis yang izinnya dicabut tidak ditampilkan.
  - Reorder wajib memuat exact set favorite milik user; subset/duplikat/asing
    ditolak sebelum transaksi. Unpin selalu dibatasi user+menu.
  - Cache user dibersihkan setelah sukses, fingerprint memuat urutan, dan UI
    diperbarui lokal tanpa reload halaman.
- Validasi yang dijalankan:
  - `php -l` seluruh file PHP terkait dan `node --check`: lulus.
  - Smoke favorite setelah revisi: 23 pemeriksaan PASS.
  - `git diff --check` dan trailing-whitespace: lulus.
  - Tidak ada SQL/schema/config/database/runtime yang diubah.
- Hasil review auditor: PASS setelah tiga required fixes.
- Risiko sisa: reorder dua tab bersamaan masih last-write-wins; tidak lintas-user.
- Batch berikutnya: POST/CSRF terpisah untuk penyimpanan struktur sidebar.

## Batch 85B — Fase A1 Sidebar: integrity struktur superadmin

- Waktu: 2026-09-03 20:11 WIB.
- Prioritas: P0/P1 request integrity — save structure superadmin belum POST-only
  dan belum memiliki token CSRF.
- Ringkasan diskusi auditor dan fixer: token structure dipisahkan dari token
  favorite karena boundary aktornya berbeda. Fixer menjaga kontrak payload/AJAX;
  auditor mereview urutan guard hingga invalidasi cache dan PASS.
- File berubah:
  - `application/controllers/Sidebar.php`
  - `application/views/sidebar/manage.php`
  - `tools/tests/sidebar_structure_mutation_csrf_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Manage menerbitkan token setelah superadmin gate.
  - Save structure memeriksa superadmin, POST, AJAX, dan header token sebelum
    sidebar type, JSON tree, transaksi, model, atau cache clear.
  - Method salah mendapat 405 + `Allow: POST`; token salah 403 generik; JSON
    rusak 400 tanpa transaksi.
- Validasi yang dijalankan:
  - `php -l` tiga file: lulus.
  - Smoke structure 12 dan regresi favorite 23: PASS.
  - `git diff --check` dan trailing-whitespace: lulus.
  - Tidak ada SQL/schema/model/config/database/runtime yang diubah.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: browser superadmin nyata belum diuji.
- Batch berikutnya: POST/CSRF untuk CRUD menu sidebar superadmin.

## Batch 85C — Fase A1 Sidebar: integrity CRUD admin

- Waktu: 2026-09-03 20:18 WIB.
- Prioritas: P0/P1 request integrity — CRUD/toggle menu superadmin belum memakai
  method dan token guard yang sama dengan penyimpanan struktur.
- Ringkasan diskusi auditor dan fixer: token struktur dipakai ulang sebagai satu
  boundary administrasi sidebar, dengan kanal hidden field untuk form dan header
  untuk AJAX. Auditor mereview urutan empat endpoint dan PASS.
- File berubah:
  - `application/controllers/Sidebar.php`
  - `application/views/sidebar/manage.php`
  - `tools/tests/sidebar_admin_menu_mutation_csrf_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Store/update/delete memeriksa superadmin dan POST/hidden token sebelum
    payload/lookup/query/write.
  - Toggle memeriksa superadmin, POST/header token, dan AJAX sebelum lookup/write.
  - Dua pemanggil toggle pada editor tree dan tabel mengirim header token.
- Validasi yang dijalankan:
  - PHP lint, Node check, dan diff/whitespace: lulus.
  - Smoke CRUD 24, regresi favorite 23, dan structure 12: PASS.
  - Tidak ada SQL/schema/model/config/database/runtime yang diubah.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: cycle/parent lintas sidebar pada data admin belum dibatasi penuh.
- Batch berikutnya: POST/CSRF khusus antrean availability POS.

## Batch 86 — Fase A1 POS: CSRF antrean availability

- Waktu: 2026-09-03 20:23 WIB.
- Prioritas: P1 request integrity — proses/retry antrean availability sudah POST,
  tetapi belum terlindungi saat CSRF global nonaktif.
- Ringkasan diskusi auditor dan fixer: token dipisahkan dari transaksi POS karena
  boundary operasional berbeda. Fixer menjaga form/redirect lama dan endpoint CLI;
  auditor mereview lalu PASS.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/availability_queue_index.php`
  - `tools/tests/pos_availability_queue_csrf_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Process/retry memeriksa edit RBAC, POST, dan hidden token sebelum payload,
    load service, atau pemrosesan job.
  - Form proses dan seluruh form retry membawa token khusus sambil mempertahankan
    CSRF global bila kelak diaktifkan serta filter return.
  - Worker cron CLI tidak memakai boundary CSRF web.
- Validasi yang dijalankan:
  - PHP lint dan diff/whitespace: lulus.
  - Smoke B86 11 dan regresi inventory availability 19: PASS.
  - Tidak ada SQL/schema/model/config/database/runtime yang diubah.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: browser/session nyata belum diuji.
- Batch berikutnya: POST/CSRF untuk tiga writer rekonsiliasi inventory divisi.

## Batch 87 — Fase A1 Inventory: CSRF rekonsiliasi stok divisi

- Waktu: 2026-09-03 20:30 WIB.
- Prioritas: P0/P1 request integrity — repair material ID, repair profile, dan
  merge profile masih dapat dijangkau melalui direct request setelah RBAC tanpa
  pembatasan method atau token CSRF khusus.
- Ringkasan diskusi auditor dan fixer: auditor menetapkan satu boundary khusus
  untuk tiga writer rekonsiliasi. Fixer menerapkan urutan edit RBAC, POST/token,
  payload, lalu model sambil mempertahankan perilaku `division_id=0` dan kontrak
  respons lama. Auditor mereview ulang source, test, dan hasil validasi lalu PASS.
- File berubah:
  - `application/controllers/Purchase.php`
  - `application/views/purchase/stock_division_reconcile_index.php`
  - `tools/tests/inventory_division_reconcile_mutation_csrf_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Tiga writer hanya menerima POST dan header token session khusus
    `X-Inventory-Reconcile-CSRF` sebelum membaca body atau memanggil model.
  - Method selain POST mendapat JSON 405 dan `Allow: POST`; token tidak valid
    mendapat JSON 403 generik tanpa menjalankan pekerjaan bisnis.
  - Wrapper JavaScript khusus hanya dipakai oleh tiga endpoint tersebut;
    wrapper transaksi POS dan caller runtime-job tetap terpisah.
- Validasi yang dijalankan:
  - PHP lint tiga file: lulus.
  - Smoke Batch 87: 28 pemeriksaan PASS.
  - Regresi purchase maintenance: 23 PASS; POS runtime-job: 308 PASS.
  - `git diff --check`: lulus.
  - Tidak ada SQL/schema/model/config/database/runtime yang diubah.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: browser/session/database nyata belum diuji.
- Batch berikutnya: POST/CSRF untuk tujuh writer berisiko tinggi pada System
  Tools (backup, konfigurasi MySQL, replication, sync, dan failover).

## Batch 88 — Fase A1 System Tools: CSRF aksi berisiko tinggi

- Waktu: 2026-09-03 20:36 WIB.
- Prioritas: P0 request integrity — aksi backup, konfigurasi MySQL, setup master,
  initial sync, failover, dan restart replication dapat mencapai side effect
  setelah RBAC tanpa pembatasan method dan token khusus.
- Ringkasan diskusi auditor dan fixer: auditor memilih tujuh writer dalam satu
  boundary administrasi System Tools. Fixer menambahkan token session khusus dan
  header pada dua wrapper view. Auditor menjalankan review ulang dan menyatakan
  PASS; konfigurasi database lokal milik pengguna tidak direvert.
- File berubah:
  - `application/controllers/System_tools.php`
  - `application/views/system/dbtools.php`
  - `application/views/system/settings.php`
  - `tools/tests/system_tools_mutation_csrf_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - `settings_save`, run backup, apply MySQL config, setup master, initial sync,
    failover, dan restart replication wajib edit RBAC, POST, serta header token
    `X-System-Tools-CSRF` sebelum payload, query, file, script, atau service.
  - Method selain POST mendapat JSON 405 + `Allow: POST`; token invalid mendapat
    JSON 403 generik tanpa side effect. Endpoint baca tetap kompatibel.
- Validasi yang dijalankan:
  - PHP lint empat file: lulus.
  - Smoke Batch 88: 67 PASS; backup source isolation: 32 PASS.
  - `git diff --check`: lulus.
  - `deployment_secret_config_smoke.php` tetap gagal pada `database.php` karena
    pengguna memilih konfigurasi database eksplisit; file itu di luar scope B88.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: browser/session serta operasi backup/replication nyata belum diuji.
- Temuan lanjutan: smoke POS Mobile berhenti pada kegagalan login pertama dan
  membuktikan binding outlet/terminal B75–81 tidak lengkap di source aktif;
  statusnya dibuka kembali sebagai blocker A1.
- Batch berikutnya: Batch 89a memulihkan anti-oracle login dan identitas bearer
  terminal/outlet sebelum reader/writer POS Mobile dipulihkan bertahap.

## Batch 89a — Fase A1 POS Mobile: pemulihan device identity

- Waktu: 2026-09-03 20:42 WIB.
- Prioritas: P0 security regression — source aktif membedakan kegagalan password
  dan terminal serta belum membawa identitas outlet/terminal registry pada
  bearer yang sudah tervalidasi.
- Ringkasan diskusi auditor dan fixer: auditor membuka kembali bukti Batch 75–81
  setelah smoke monolitik berhenti pada failure pertama. Fixer memulihkan
  anti-oracle login dan binding identity dalam batch kecil; duplikat device key
  aktif maupun nonaktif ikut ditolak. Auditor mereview dan menyatakan PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_device_binding_recovery_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Credential salah dan terminal invalid mendapat body 401 identik tanpa code
    pembeda, echo device key, atau token insert.
  - Registry wajib tepat satu row untuk device key, aktif, dan memiliki ID
    terminal/outlet positif.
  - Bearer menyimpan terminal/outlet registry sebelum memperbarui `last_seen`;
    seluruh binding invalid berhenti tanpa update tersebut.
- Validasi yang dijalankan:
  - PHP lint controller/test: lulus.
  - Smoke recovery: 33 PASS; inventory-control regression: 312 PASS.
  - `git diff --check`: lulus.
  - Smoke POS Mobile monolitik maju melewati login/token lalu berhenti pada gap
    reader berikutnya: bootstrap outlet mismatch.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: endpoint reader dan writer/replay belum seluruhnya terikat ke
  outlet/terminal bearer; smoke monolitik belum PASS penuh.
- Batch berikutnya: 89b memulihkan binding bootstrap dan katalog.

## Batch 89b — Fase A1 POS Mobile: pemulihan bootstrap/katalog

- Waktu: 2026-09-03 20:55 WIB.
- Prioritas: P0 scope regression — bootstrap dan katalog pada source aktif masih
  dapat memakai outlet request/session/default, bukan identitas bearer.
- Ringkasan diskusi auditor dan fixer: auditor membatasi batch pada dua reader.
  Fixer mengikat respons dan query katalog ke outlet/terminal perangkat sambil
  mempertahankan fallback web. Review menemukan kontradiksi status pada harness;
  auditor menetapkan registry invalid ditolak 401 oleh autentikasi dan test
  monolitik diperbaiki tanpa melemahkan source. Review final PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_reader_outlet_binding_smoke.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Bearer bootstrap/katalog hanya memakai outlet dan terminal hasil registry;
    request context berbeda ditolak sebelum model.
  - Bootstrap memfilter pilihan outlet/terminal serta sesi ke perangkat aktif;
    tanpa sesi tetap 200 dengan sesi null dan daftar kosong.
  - Web-session tetap memakai perilaku legacy.
- Validasi yang dijalankan:
  - PHP lint: lulus.
  - Smoke 89a: 33 PASS; smoke 89b: 21 PASS; inventory-control: 312 PASS.
  - Monolithic POS smoke maju sampai gap writer `order_confirm`.
  - `git diff --check`: lulus untuk scope batch.
- Hasil review auditor: PASS setelah koreksi harness 401; tidak ada required fix.
- Risiko sisa: daftar/detail order dan writer/cashier/replay belum dipulihkan;
  smoke POS Mobile monolitik belum PASS penuh.
- Batch berikutnya: 89c memulihkan binding `orders` dan `order_load`.

## Batch 89c — Fase A1 POS Mobile: pemulihan reader order

- Waktu: 2026-09-03 20:59 WIB.
- Prioritas: P0 scope regression — daftar order masih mengambil outlet dari
  sesi kasir dan detail order belum memeriksa outlet bearer.
- Ringkasan diskusi auditor dan fixer: auditor memisahkan reader dari writer.
  Fixer membatasi daftar/detail ke outlet perangkat tanpa memerlukan sesi kasir
  aktif untuk membaca daftar. Auditor mereview dan menyatakan PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_order_reader_binding_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Daftar order bearer selalu memakai outlet registry, bukan query atau sesi.
  - Detail missing, outlet nol, atau lintas outlet mendapat 404 generik identik
    tanpa isi order; terminal lain pada outlet sama tetap boleh.
  - Web-session tetap memakai perilaku lama.
- Validasi yang dijalankan:
  - PHP lint: lulus.
  - Smoke 89c: 13 PASS; 89a: 33; 89b: 21; inventory-control: 312 PASS.
  - `git diff --check`: lulus untuk scope batch.
  - Smoke monolitik masih berhenti lebih awal pada gap writer `order_confirm`,
    sehingga matrix reader-ID monolitik belum menjadi bukti final.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: writer finansial, kasir, draft/upsert/replay belum terikat penuh.
- Batch berikutnya: 89d.1 financial mutation/replay.

## Batch 89d.1 — Fase A1 POS Mobile: financial mutation/replay

- Waktu: 2026-09-03 21:05 WIB.
- Prioritas: P0 financial scope — payment, void, refund, dan replay idempotency
  belum membuktikan order/outlet bearer sebelum writer atau sync-event.
- Ringkasan diskusi auditor dan fixer: auditor memisahkan tiga writer finansial
  dari alur kasir dan upsert. Fixer menambah resolver order kanonis serta bukti
  replay yang konsisten. Auditor mereview dan menyatakan PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_financial_writer_binding_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Bearer payment/void/refund memuat order dan mencocokkan outlet sebelum writer
    atau monitor.
  - Payment mengikat order sebelum membaca, membuat, atau mengulang sync-event.
    Replay tanpa bukti order aman atau dengan konteks berbeda mendapat 404
    generik tanpa mengembalikan response tersimpan.
  - Web-session tetap menggunakan perilaku lama.
- Validasi yang dijalankan:
  - PHP lint: lulus.
  - Smoke 89d.1: 26 PASS; 89a: 33; 89b: 21; 89c: 13;
    inventory-control: 312 PASS.
  - `git diff --check`: lulus untuk scope batch.
  - Smoke monolitik tetap berhenti lebih awal pada gap `order_confirm`.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: sesi kasir dan draft/upsert/confirm belum dipulihkan; smoke
  monolitik belum PASS penuh.
- Batch berikutnya: 89d.2 binding sesi kasir terminal/outlet.

## Batch 89d.2 — Fase A1 POS Mobile: cashier terminal-session

- Waktu: 2026-09-03 21:12 WIB.
- Prioritas: P0 session scope — buka/status/preview/tutup kasir belum memastikan
  sesi OPEN berasal dari employee, outlet, dan terminal bearer yang sama.
- Ringkasan diskusi auditor dan fixer: fixer menambah resolver sesi scoped.
  Review pertama menemukan payload buka kasir belum selalu ditimpa dengan
  context authoritative saat sesi cocok sudah ada; fixer memperbaiki dan auditor
  final menyatakan PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_cashier_session_binding_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Status hanya menampilkan sesi perangkat sendiri; tanpa sesi tetap 200 dengan
    `session: null` dan daftar kosong.
  - Buka kasir menolak context positif berbeda lalu selalu menulis outlet dan
    terminal bearer ke payload sebelum recon/open.
  - Preview/tutup menolak sesi missing, bukan OPEN, atau terminal/outlet berbeda
    sebelum recon, close, dan print.
  - Web-session tetap legacy.
- Validasi yang dijalankan:
  - PHP lint: lulus.
  - Smoke 89d.2 setelah revisi: 23 PASS; 89a: 33; 89b: 21; 89c: 13;
    89d.1: 26; inventory-control: 312 PASS.
  - `git diff --check`: lulus untuk scope batch.
- Hasil review auditor: PASS setelah satu required fix.
- Risiko sisa: draft/upsert/confirm/replay order belum dipulihkan; smoke
  monolitik belum PASS penuh.
- Batch berikutnya: 89d.3 binding draft, confirm, dan orders_push.

## Batch 89d.3 — Fase A1 POS Mobile: draft, confirm, dan push order

- Waktu: 2026-09-03 21:24 WIB.
- Prioritas: P0 order scope — simpan/konfirmasi/push order belum konsisten
  memakai outlet dan terminal bearer serta sesi kasir OPEN perangkat.
- Ringkasan diskusi auditor dan fixer: fixer memulihkan konteks authoritative
  untuk order baru dan binding order lama. Auditor memeriksa replay serta
  harness sesi kasir, lalu menyatakan PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_draft_upsert_binding_smoke.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - `order_save`, `order_confirm`, dan `orders_push` mengikat order ke outlet
    bearer serta memerlukan sesi kasir OPEN yang sesuai employee/perangkat.
  - Order baru selalu menerima outlet/terminal authoritative dari bearer;
    order lama yang missing atau lintas outlet ditolak sebelum writer.
  - Replay push hanya diterima bila jenis event, order, outlet, terminal,
    server event, dan response tersimpan membuktikan konteks yang sama.
  - Web-session tetap memakai perilaku legacy.
- Validasi yang dijalankan:
  - PHP lint: lulus.
  - Smoke 89d.3: 49 PASS; seluruh smoke terarah 89a–89d.3: 165 PASS;
    inventory-control: 312 PASS.
  - Smoke POS Mobile monolitik maju sampai 237 PASS dan menemukan residual
    nyata pada `order_reversal_preview`.
  - `git diff --check`: lulus untuk scope batch.
- Hasil review auditor: PASS; harness sudah selaras dengan resolver sesi model.
- Risiko sisa: lima reader aksi berbasis order dan tiga document-print belum
  seluruhnya membuktikan outlet order kanonis; smoke monolitik belum tamat.
- Batch berikutnya: 89e binding reader aksi berbasis `order_id`.

## Batch 89e — Fase A1 POS Mobile: reader aksi berbasis order

- Waktu: 2026-09-03 21:30 WIB.
- Prioritas: P0 order scope — preview reversal, reprint, target cetak konfirmasi,
  persiapan pembayaran, dan voucher masih dapat membaca order lintas outlet.
- Ringkasan diskusi auditor dan fixer: auditor menetapkan urutan auth, RBAC,
  resolver order kanonis, lalu downstream. Fixer menerapkan satu resolver yang
  sama pada lima endpoint; auditor mereview dan menyatakan PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_order_action_reader_binding_smoke.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Bearer hanya dapat membaca aksi untuk order dengan outlet yang sama.
  - Order missing, outlet nol, atau lintas outlet mendapat 404 generik sebelum
    preview, query voucher, payment prepare, atau pencarian target cetak.
  - Order dari terminal lain pada outlet sama tetap boleh dan reader tidak
    mensyaratkan sesi kasir aktif; web-session tetap legacy.
- Validasi yang dijalankan:
  - PHP lint: lulus.
  - Smoke 89e: 54 PASS; seluruh smoke terarah 89a–89e: 219 PASS;
    inventory-control: 312 PASS.
  - Smoke POS Mobile monolitik maju sampai 273 PASS dan menemukan residual
    pada tiga endpoint cetak berbasis dokumen.
  - `git diff --check`: lulus untuk scope batch.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: dokumen void/refund/payment belum diikat melalui order/outlet
  kanonis; smoke monolitik belum tamat.
- Batch berikutnya: 89f binding document-print.

## Batch 89f — Fase A1 POS Mobile: binding dokumen cetak

- Waktu: 2026-09-03 21:36 WIB.
- Prioritas: P0 print scope — ID void, refund, atau payment belum membuktikan
  parent order dan outlet bearer sebelum membuat target/attempt cetak.
- Ringkasan diskusi auditor dan fixer: auditor mengarahkan pemakaian resolver
  dokumen kanonis yang sudah ada. Fixer memasang guard pada tiga endpoint dan
  menyelaraskan matrix lama; auditor mereview dan menyatakan PASS.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `tools/tests/pos_mobile_print_document_binding_smoke.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Dokumen void/refund/payment bearer di-resolve ke parent order dan outlet
    sebelum `direct_print_targets*` dijalankan.
  - Dokumen/order missing, outlet nol, atau lintas outlet mendapat 404 generik
    tanpa target, print-attempt, atau marker response.
  - Terminal asal berbeda pada outlet sama tetap boleh; web-session tetap legacy.
  - Resolver model yang digunakan sudah memulihkan `db_debug` melalui `finally`.
- Validasi yang dijalankan:
  - PHP lint: lulus.
  - Smoke 89f: 34 PASS; seluruh smoke terarah 89a–89f: 253 PASS;
    inventory-control: 312 PASS.
  - Smoke POS Mobile monolitik selesai penuh: 717 PASS.
  - `git diff --check`: lulus untuk scope batch.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: printer discovery/test, surface API tambahan, step-up approval,
  serta UAT token/perangkat/APK nyata belum ditutup.
- Batch berikutnya: 90 mengamankan runtime sync POS dengan POST/CSRF.

## Batch 90 — Fase A1: POST/CSRF runtime sync POS

- Waktu: 2026-09-03 21:42 WIB.
- Prioritas: P0 request integrity — `order_runtime_sync` masih dapat memicu
  refresh/job tanpa pemeriksaan metode dan token transaksi POS.
- Ringkasan diskusi auditor dan fixer: auditor menemukan satu mutator runtime
  yang tertinggal. Fixer menambah guard scoped serta mengalihkan pemanggil UI
  ke wrapper bertoken; auditor mereview dan menyatakan PASS.
- File berubah:
  - `application/controllers/Pos.php`
  - `application/views/pos/online_food_orders.php`
  - `application/views/pos/self_order_orders.php`
  - `tools/tests/pos_runtime_sync_csrf_smoke.php`
  - `tools/tests/pos_transaction_csrf_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Runtime sync kini berurutan RBAC, POST/token CSRF, payload, lalu refresh.
  - GET/PUT/PATCH serta token hilang/salah berhenti sebelum payload atau job.
  - Pemanggil online-food dan self-order mengirim header transaksi yang sama;
    pemanggil verify/reject pada kedua layar juga diselaraskan.
- Validasi yang dijalankan:
  - PHP lint: lulus; smoke fokus: 19 PASS.
  - Regression: transaction-CSRF 1693; runtime trigger 247; mutation 308;
    stock-live 237; monitor 38; delete job 121; dismiss job 257;
    dismiss snapshot 504; retry snapshot 391 PASS.
  - `git diff --check`: lulus untuk scope batch.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: policy export-existing terlalu longgar dan matrix direct-URL
  gabungan belum menjadi quality gate; UAT runtime nyata tetap diperlukan.
- Batch berikutnya: 91 mewajibkan permission export untuk data opening existing.

## Batch 91 — Fase A1: permission export opening divisi

- Waktu: 2026-09-03 21:48 WIB.
- Prioritas: P0 data access — unduhan data opening existing masih menerima
  permission view sebagai pengganti permission export.
- Ringkasan diskusi auditor dan fixer: auditor menetapkan kebijakan strict
  export tanpa menambah grant role. Fixer mengubah guard server dan visibilitas
  UI; satu kegagalan smoke awal pada conditional view diperbaiki, lalu auditor
  menyatakan PASS.
- File berubah:
  - `application/controllers/Purchase.php`
  - `application/views/purchase/stock_opening_division_index.php`
  - `tools/tests/purchase_stock_opening_authorization_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Export existing wajib `inventory.stock.opening.division:export` sebelum
    input, query, loader spreadsheet, atau output XLSX.
  - Index mengirim capability export dan form tidak dirender bagi view-only.
  - JavaScript tetap aman ketika form export existing tidak tersedia.
  - Export template tetap memakai kontrak lama view atau create.
- Validasi yang dijalankan:
  - PHP lint: lulus.
  - Authorization smoke setelah revisi: 66 PASS.
  - Purchase maintenance regression: 23 PASS.
  - `git diff --check`: lulus untuk scope batch.
- Hasil review auditor: PASS; tidak ada required fix.
- Risiko sisa: role kustom view-only sengaja kehilangan unduhan existing dan
  perlu diverifikasi pada UAT permission deployment; tidak ada seed diubah.
- Batch berikutnya: 92 membentuk matrix direct-URL guard gabungan A1.

## Batch 93 — Fase A1 POS Mobile: printer bearer binding

- Waktu: 2026-09-03 22:13 WIB.
- Prioritas: P0 scope/secret — discovery printer memakai filter request setelah
  pagination dan test print belum membuktikan outlet/terminal bearer.
- Ringkasan diskusi auditor dan fixer: auditor menolak helper permission tak
  bertuan dan smoke statis sebagai bukti cukup. Backend finance_fixer gagal 404
  berulang, sehingga main agent menerapkan scope yang sudah diarahkan; auditor
  meminta behavioral test lalu menyatakan PASS setelah revisi.
- File berubah:
  - `application/controllers/Pos_mobile.php`
  - `application/models/Pos_print_model.php`
  - `tools/tests/pos_mobile_printer_binding_smoke.php`
  - `tools/tests/pos_mobile_authorization_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - List printer bearer memakai outlet/terminal token sebelum count/pagination;
    hanya koneksi aktif exact-outlet dan route runtime-compatible diproyeksikan.
  - Test print memerlukan permission edit, bukan view, lalu memeriksa koneksi
    dan route exact/generic yang kompatibel sebelum preview atau attempt.
  - Attempt selalu mencatat outlet/terminal bearer; query context berbeda
    ditolak 403 dan objek printer lintas scope mendapat 404 generik.
  - Password Wi-Fi, agent host, dan port dirahasiakan secara rekursif dari
    response discovery/test bearer. Web-session mempertahankan lookup legacy.
- Validasi yang dijalankan:
  - PHP lint dan scoped `git diff --check`: lulus.
  - Smoke printer: 32 pemeriksaan behavioral/struktur; bersama monolitik dalam
    invocation terarah menghasilkan 750 PASS tanpa warning.
  - Smoke terarah POS Mobile 89a–89f + printer: 275 PASS; monolitik mandiri
    setelah expectation policy diperbarui: 718 PASS.
- Hasil review auditor: PASS setelah required behavioral coverage ditambahkan.
- Risiko sisa: general printer settings tetap global sesuai kontrak model;
  UAT printer/APK nyata dan surface API mobile tambahan masih diperlukan.
- Batch berikutnya: tuntaskan matrix A1.92 lalu audit/bind surface API tambahan.

## Batch 92 — Fase A1: matrix direct-URL gabungan

- Waktu: 2026-09-03 22:17 WIB; disahkan setelah Batch 93 masuk ke matrix.
- Prioritas: quality gate security — smoke A1 tersebar dan belum mempunyai satu
  runner yang memastikan route, method, serta seluruh family guard tetap aktif.
- Ringkasan diskusi auditor dan fixer: tiga percobaan finance_fixer gagal 404
  sebelum membuat patch, sehingga main agent membuat runner sesuai manifest
  auditor. Review pertama meminta coverage printer dan regex lebih ketat; setelah
  revisi auditor menyatakan PASS.
- File berubah:
  - `tools/tests/a1_direct_url_guard_matrix_smoke.php`
  - dokumen roadmap/progres/log.
- Perubahan utama:
  - Manifest mencatat family, URI, target controller, method, policy, dan smoke.
  - Route assignment serta public method divalidasi dengan regex line-anchored.
  - Setiap smoke unik dijalankan satu kali pada proses PHP terisolasi; kegagalan
    menyebut family dan file sumber tanpa bergantung pada teks sukses.
  - Matrix mencakup Master, enam family Master Relation, Sidebar, Purchase,
    reconcile divisi, System Tools, POS web, POS Mobile, dan printer.
- Validasi yang dijalankan:
  - PHP lint dan scoped `git diff --check`: lulus.
  - 22 kontrak manifest dan 21 source smoke: PASS dalam 0,28 detik.
- Hasil review auditor: PASS setelah dua required fixes.
- Risiko sisa: matrix adalah guard A1, bukan pengganti browser/UAT akun, database,
  perangkat, proxy HTTPS, atau APK nyata.
- Batch berikutnya: audit dan binding surface API POS Mobile tambahan.

## Batch 94 — A2 period guard fail-closed

- Waktu: 2026-09-04 05:20 WIB.
- Prioritas: period guard tidak boleh mengizinkan writer ketika schema fondasi
  belum tersedia atau tanggal transaksi ambigu.
- Ringkasan auditor/fixer: auditor memilih kegagalan schema sebagai risiko
  tertinggi; fixer mengubah guard menjadi fail-closed dan memperketat tanggal.
- File berubah: `application/libraries/InventoryPeriodGuard.php`,
  `tools/tests/inventory_period_guard_smoke.php`.
- Perubahan utama: kode stabil `INVENTORY_PERIOD_SCHEMA_NOT_READY`; tanggal
  wajib kalender `Y-m-d` yang valid.
- Validasi: PHP lint, 13 period smoke, dan `git diff --check` lulus.
- Review auditor: PASS.
- Risiko sisa: race period dengan transaksi masih ditangani pada Batch 98.
- Batch berikutnya: propagasi cache HPP item-centric.

## Batch 95 — A2 cache HPP item-centric

- Waktu: 2026-09-04 05:30 WIB.
- Prioritas: koreksi material berbasis `item_id` dapat melewatkan rebuild HPP.
- Ringkasan auditor/fixer: fixer menambah resolver dampak produk langsung,
  formula component, component bertingkat, dan mapping material legacy.
- File berubah: `application/libraries/InventoryValueReconciliationService.php`,
  `application/libraries/PosAvailabilityRebuildService.php`, serta dua smoke A2.
- Perubahan utama: POST/VOID memilih handler material/item/component secara
  eksplisit dan de-duplicate queue/rebuild.
- Validasi: PHP lint; smoke reconciliation 23 dan item cache 9; diff-check lulus.
- Review auditor: PASS.
- Risiko sisa: data mismatch historis tidak disentuh.
- Batch berikutnya: invariant reversal POS.

## Batch 96 — A2 invariant reversal POS

- Waktu: 2026-09-04 05:40 WIB.
- Prioritas: quantity reversal mentah dapat mencapai rollback fisik sebelum
  cap dan status header dapat salah menjadi penuh.
- Ringkasan auditor/fixer: keputusan reversal dinormalisasi terhadap residual
  snapshot yang terkunci sebelum mutasi stok.
- File berubah: `application/libraries/PosOrderStockService.php`,
  `application/libraries/PosStockCommitService.php`, dan smoke reversal.
- Perubahan utama: quantity di-cap, policy invalid/duplikat/unknown ditolak,
  dan status partial/full dihitung dari seluruh residual line.
- Validasi: PHP lint; reversal invariant 8, POS transaction 1691, monitor 38;
  diff-check lulus.
- Review auditor: PASS.
- Risiko sisa: anomali POS historis tidak direplay.
- Batch berikutnya: refresh availability setelah reversal.

## Batch 97 — A2 cache setelah void/refund POS

- Waktu: 2026-09-04 05:50 WIB.
- Prioritas: void/refund dapat selesai tetapi cache availability tetap stale.
- Ringkasan auditor/fixer: shared `Pos_model` mengumpulkan outlet/produk lalu
  refresh satu kali setelah commit; controller web tidak menggandakan refresh.
- File berubah: `application/models/Pos_model.php`,
  `application/controllers/Pos.php`, dan smoke cache reversal.
- Perubahan utama: web dan APK memperoleh metadata rebuild/warning yang sama;
  kegagalan cache tidak membatalkan transaksi reversal yang sudah commit.
- Validasi: PHP lint; smoke cache 20, transaction 1691, invariant reversal dan
  monitor lulus; diff-check lulus.
- Review auditor: PASS. `Pos_mobile.php` dan `routes.php` tidak diubah.
- Risiko sisa: UAT APK mengikuti build milik pemilik.
- Batch berikutnya: barrier periode transaksional.

## Batch 98 — A2 barrier periode transaksional

- Waktu: 2026-09-04 06:16 WIB.
- Prioritas: close/rollover dapat beradu dengan writer stok dan dua pembuat
  periode baru dapat deadlock atau salah dianggap sukses.
- Ringkasan auditor/fixer: dua review auditor menemukan range race lalu
  missing-key creation race; fixer menutup keduanya secara bertahap.
- File berubah: `application/libraries/InventoryPeriodGuard.php`,
  `application/libraries/InventoryLedger.php`,
  `application/libraries/InventoryValueReconciliationService.php`,
  `application/libraries/PosOrderStockService.php`, dan atomic period smoke.
- Perubahan utama: writer mengunci periode di dalam transaksi; historical write
  memakai exact+newer-range lock; current-month creation memakai atomic upsert
  lalu exact lock; status close memakai CAS dan transaksi gagal selalu ditolak.
- Validasi: PHP lint; period guard dan 17 atomic checks lulus; regression value,
  reversal, POS transaction, dan monitor lulus; diff-check lulus.
- Review auditor: PASS setelah dua blocker diperbaiki.
- Risiko sisa: asumsi lock dibuktikan oleh engine InnoDB dan unique key staging.
- Batch berikutnya: matrix dan probe final A2.

## Batch 99 — Gerbang akhir Fase A2

- Waktu: 2026-09-04 06:20 WIB.
- Prioritas: satu bukti gabungan untuk menutup A2 tanpa repair data historis.
- Ringkasan auditor/fixer: fixer membuat runner terisolasi dan probe SELECT-only;
  auditor mereview seluruh batch A2 dan menyatakan tidak ada blocker tersisa.
- File berubah: `tools/tests/a2_inventory_transaction_matrix_smoke.php`,
  `tools/tests/a2_database_invariant_probe.php`, serta dokumen roadmap/log.
- Perubahan utama: matrix menjalankan 12 smoke; probe memeriksa engine/index,
  periode, lot/HPP negatif, defisit, queue cache, dan anomali terminal POS.
- Validasi: seluruh 12 smoke PASS. Probe staging: InnoDB dan unique domain/bulan;
  current COMPONENT/MATERIAL OPEN; lot quantity/cost negatif 0; defisit tersisa
  0; queue gagal 0; anomali historis 37/26 sampai 2026-08-31.
- Review auditor: PASS; A2 selesai pada gate kode, smoke, dan read-only DB.
- Risiko sisa: data historis tidak direplay; APK/browser UAT dikecualikan dari
  completion A2. Tidak ada SQL atau perubahan schema pada batch ini.
- Batch berikutnya: Fase A3 registry navigasi dan UI operasional.

## Batch 100 — A3 registry navigasi kanonis

- Waktu: 2026-09-04; penutupan dicatat 17:06 WIB.
- Prioritas: P1-03/P1-05 — hilangkan perbedaan struktur antara registry
  database dan sidebar yang dilihat pengguna.
- Ringkasan auditor/fixer: auditor meminta migration fail-closed, idempotent,
  dan tidak menebak page/parent; fixer menormalkan parent, ikon, URL, sales
  alias, dan urutan sibling dengan pre/postcondition sebelum commit.
- File berubah: `sql/2026-09-04a_a3_navigation_registry_canonicalization.sql`,
  `application/controllers/Sidebar.php`,
  `tools/tests/a3_navigation_registry_smoke.php`.
- Perubahan utama: Master dan Product Monitoring dimaterialisasi di registry;
  group Inventory/Component yang aktif dipakai ulang; Product Availability
  dipindahkan ke parent kanonis; lima ikon dilengkapi; duplikasi laporan sales
  dinonaktifkan setelah favorite dipindahkan; Online Food menjadi pure group.
- Validasi: migration sempat dua kali berhenti dan rollback aman pada ambiguity
  kolom serta action-bearing group; setelah direvisi berhasil dijalankan dua
  kali di staging. Missing icon, duplicate code/URL, sort collision, favorite
  invalid, dan action-bearing group semuanya 0. Hash state fungsional sebelum
  dan sesudah rerun identik:
  `78118baa84693c0a6aec669b84a5099b5dd9fe0dcc53f0d5727d832792498636`.
- Hasil review auditor: PASS setelah qualifier SQL dan idempotensi seed
  diperbaiki.
- Risiko sisa: struktur menu bisnis tetap perlu dinilai pengguna melalui UAT;
  migration belum dijalankan di server utama.
- Batch berikutnya: hapus renderer sidebar hardcode.

## Batch 101 — A3 sidebar database-only

- Waktu: 2026-09-04; penutupan dicatat 17:06 WIB.
- Prioritas: P1-03 — view tidak boleh menyuntikkan menu atau regrouping yang
  tidak ada di database.
- Ringkasan auditor/fixer: auditor menetapkan `sys_menu` sebagai satu-satunya
  sumber struktur; fixer menyederhanakan view menjadi renderer tree generik.
- File berubah: `application/views/layout/sidebar.php`,
  `application/controllers/Sidebar.php`,
  `tools/tests/a3_sidebar_renderer_single_source_smoke.php`.
- Perubahan utama: map ikon, regroup/injection, synthetic negative ID,
  `is_virtual`, relabel PO, dan helper struktur runtime dihapus. Label, ikon,
  URL, parent, dan sort berasal dari registry; ikon fallback hanya untuk
  defensif tampilan.
- Validasi: PHP lint dan source smoke sidebar single-source lulus.
- Hasil review auditor: PASS; tidak ada sumber tree kedua yang tersisa pada
  renderer.
- Risiko sisa: salah konfigurasi registry sekarang terlihat langsung dan harus
  diselesaikan melalui halaman pengelolaan/migration, bukan disamarkan view.
- Batch berikutnya: satukan resolver favorite dan permission sidebar.

## Batch 102 — A3 favorite dan menu fail-closed

- Waktu: 2026-09-04; penutupan dicatat 17:06 WIB.
- Prioritas: P1-04 — favorite/pin tidak boleh menampilkan atau menyimpan menu
  yang sudah kehilangan hak akses.
- Ringkasan auditor/fixer: auditor meminta tree, favorite, pin, dan reorder
  memakai keputusan akses yang sama; fixer menyatukan resolver efektif dan
  mempertahankan multi-role sebagai union permission.
- File berubah: `application/models/Menu_model.php`,
  `application/controllers/Sidebar.php`,
  `tools/tests/a3_sidebar_favorite_registry_smoke.php`.
- Perubahan utama: action menu wajib mempunyai menu dan page aktif; group tanpa
  page hanya menjadi container dan tidak dapat dipin; favorite stale/revoked
  otomatis tersembunyi; pin idempotent memakai unique user/menu; alias URL
  hardcode di model dihapus.
- Validasi: PHP lint, 12 favorite/registry checks, serta smoke CSRF mutasi
  sidebar admin/favorite/structure lulus.
- Hasil review auditor: PASS.
- Risiko sisa: baseline isi permission tiap role tetap merupakan keputusan
  pemilik; A3 hanya menjamin enforcement registry konsisten.
- Batch berikutnya: page alias eksplisit dan direct-URL fail-closed.

## Batch 103 — A3 page alias registry

- Waktu: 2026-09-04; penutupan dicatat 17:06 WIB.
- Prioritas: P1-05 — sepuluh page code alias tidak boleh bergantung pada
  perilaku “page tidak ditemukan maka lanjut”.
- Ringkasan auditor/fixer: review pertama menemukan precedence direct grant
  dan collision alias; fixer mengubah resolver menjadi canonical-only serta
  menambah pre/postcondition collision fail-closed.
- File berubah: `sql/2026-09-04b_a3_page_alias_registry.sql`,
  `application/core/MY_Controller.php`, `application/controllers/My.php`,
  `tools/tests/a3_page_alias_registry_smoke.php`.
- Perubahan utama: `sys_page_alias` menyimpan sepuluh mapping eksplisit; `can()`
  me-resolve alias aktif satu kali per request; schema/query alias bermasalah
  ditolak; My Schedule selalu melalui permission guard; tidak ada permission
  role/user yang disalin.
- Validasi: migration dijalankan dua kali di staging; active alias 10 dan
  invalid alias 0. PHP lint dan alias smoke lulus.
- Hasil review auditor: PASS setelah canonical precedence dan collision guard
  diperbaiki.
- Risiko sisa: SQL harus diterapkan setelah migration registry pada server
  utama; perubahan page code baru wajib mendaftarkan alias secara eksplisit.
- Batch berikutnya: fondasi design system dan gate akhir A3.

## Batch 104 — A3 UI shell dan gerbang akhir

- Waktu: 2026-09-04; penutupan dicatat 17:06 WIB.
- Prioritas: P1-06/P1-07 — pola tampilan dasar lintas modul dan bukti gabungan
  penutupan A3.
- Ringkasan auditor/fixer: auditor membatasi A3 pada shell/token/primitives
  global agar tidak mengubah 336 view sekaligus; fixer menambahkan fondasi UI,
  menyesuaikan halaman pengelolaan sidebar, dan membuat gate route collision.
- File berubah: `application/views/layout/main.php`,
  `application/views/sidebar/manage.php`, `assets/css/theme-custom.css`,
  `tools/tests/a3_finance_ui_shell_smoke.php`,
  `tools/tests/a3_route_collision_smoke.php`, serta dokumen roadmap/progres/log.
- Perubahan utama: shell lintas rumpun, token, page header, action bar, card,
  filter/table/state, focus keyboard, target aksi 40px, responsive, reduced
  motion, dan flash message escaped. Halaman registry menampilkan tujuh kategori
  hasil validator. Enam key route terminal/outlet identik dibiarkan karena
  freeze APK, tetapi tidak ada target route yang bertentangan.
- Validasi: PHP lint seluruh file PHP A3; UI shell 50/50; route collision
  conflicting 0 dan identical duplicate 6; seluruh smoke A3 serta regression
  sidebar/RBAC lulus; `composer validate --no-check-publish` valid; scoped
  `git diff --check` lulus. Probe staging akhir: missing page/icon, duplicate
  URL, sort collision, active child under inactive parent, dan invalid alias 0.
- Hasil review auditor: review pertama menemukan token CSRF favorite terputus
  antara main layout dan footer. Fixer meneruskan token secara eksplisit serta
  menambah assertion `render → main → footer → app.js`; review ulang PASS pada
  code/database/smoke gate.
- Risiko sisa: UAT visual browser/viewport nyata belum dijalankan. Dua ekspektasi
  A1 POS Mobile terkait sesi lintas terminal masih gagal pada kode APK milik
  pengguna dan dikecualikan; A3 tidak mengubah `Pos_mobile.php`, `Pos_model.php`,
  atau `routes.php`.
- Batch berikutnya: Fase A4 automated quality gate.

## Batch 105 — Sinkronisasi backup APK dan POS web

- Waktu: 2026-09-04; penutupan dicatat setelah review A3.
- Prioritas: menerima perubahan APK tanpa menimpa hardening web, cache
  availability A2, atau binding keamanan POS Mobile.
- Ringkasan auditor/fixer: auditor menolak copy langsung ketiga backup. Fixer
  melakukan merge terarah pada sanitizer cetak dan UI kasir; model aktif tetap
  dipertahankan karena backup model belum memiliki refresh availability setelah
  void/refund. Review pertama menemukan trailing `FEED` terpangkas dan test belum
  mengunci backup model; keduanya diperbaiki lalu auditor menyatakan PASS.
- File berubah: `application/controllers/Pos_mobile.php`,
  `application/views/pos/cashier_index.php`, dan
  `tools/tests/pos_apk_web_backup_merge_smoke.php`.
- Perubahan utama: output printer mobile menormalisasi newline, menghapus marker
  media termasuk `LOGO_BASE64`, mempertahankan UTF-8, dan menerjemahkan
  `[[FEED:n]]` menjadi 1–5 line feed. Kasir web memperoleh quick navigation serta
  modal order masuk yang hanya merender channel berizin, hanya membaca outlet
  sesi kasir aktif, memakai tanggal server, dan refresh on-demand agar tidak
  menggandakan notifier global.
- Validasi: PHP lint lulus; focused merge 28/28; print-document binding 34/34;
  order reader 13/13; order-action reader 54/54; financial writer 26/26;
  reversal availability 20/20; reversal invariant 8/8; order monitor 38/38;
  transaksi POS 1.691 checks; scoped diff-check bersih. Ketiga file `*_bak.php`
  tetap byte-for-byte sama.
- Hasil review auditor: PASS untuk merge backup APK/web.
- Risiko sisa: smoke A1 lama masih melaporkan dua mismatch cashier-session,
  satu draft-upsert, dan satu authorization terkait urutan/kontrak session.
  Area tersebut identik pada backup dan file aktif serta tidak diubah dalam
  Batch 105; perlu batch A1 terpisah dan UAT APK nyata.
- Batch berikutnya: tentukan apakah menutup residual A1 terlebih dahulu atau
  melanjutkan Fase A4 sesuai keputusan pemilik.

## Batch 106 — A4.1 runner quality gate deterministik

- Waktu: 2026-09-04 18:05 WIB.
- Prioritas: menyediakan satu pintu validasi otomatis yang dapat dipakai sambil
  APK masih dikembangkan tanpa mengaburkan syarat release.
- Ringkasan auditor/fixer: auditor menetapkan proses terisolasi, timeout,
  laporan ringkas, profil eksplisit, dan kontrak credential produksi tetap
  strict; fixer membangun runner serta contract smoke deterministik.
- File berubah: `tools/tests/finance_quality_gate.php` dan
  `tools/tests/finance_quality_gate_contract_smoke.php`.
- Perubahan utama: profil `parallel` menjalankan required, development, serta
  release check tetapi hanya required yang wajib; `release` mewajibkan ketiga
  tier; `staging` menambah probe database SELECT-only. Browser dan APK nyata
  selalu dilaporkan sebagai manual pending.
- Validasi: PHP lint; contract smoke 14/14; dua run parallel menghasilkan output
  identik dan lulus dengan required 19/19 serta development 4/4.
- Hasil review auditor: PASS untuk A4.1. Kegagalan release check tidak
  disembunyikan dan tidak dilonggarkan.
- Risiko sisa: profil release/staging masih exit 1 karena konfigurasi database
  staging memakai credential langsung sesuai pilihan operasional sebelumnya.
- Batch berikutnya: sinkronkan contract test POS Mobile terminal cadangan.

## Batch 107 — A4.2 contract test POS Mobile paralel

- Waktu: 2026-09-04 18:06 WIB.
- Prioritas: menghapus false failure test lama tanpa mengubah source APK/web
  yang sedang dikembangkan pemilik.
- Ringkasan auditor/fixer: auditor menyetujui sesi kasir OPEN pada terminal
  cadangan hanya bila pegawai dan outlet sama; fixer memperbarui harness dan
  assertion authorization, session, draft/upsert, printer, dan reader.
- File berubah: `tools/tests/a1_direct_url_guard_matrix_smoke.php`,
  `tools/tests/pos_mobile_authorization_smoke.php`,
  `tools/tests/pos_mobile_cashier_session_binding_smoke.php`,
  `tools/tests/pos_mobile_draft_upsert_binding_smoke.php`,
  `tools/tests/pos_mobile_printer_binding_smoke.php`, dan
  `tools/tests/pos_mobile_reader_outlet_binding_smoke.php`.
- Perubahan utama: owner terminal sesi tetap menjadi terminal transaksi;
  terminal perangkat dicatat sebagai origin, backup mode eksplisit, dan
  employee/outlet berbeda, sesi tutup, serta spoof request tetap ditolak.
- Validasi: PHP lint; authorization PASS; cashier-session 34/34; draft/upsert
  57/57; printer 32/32; reader 27/27; A1 required dan development PASS; quality
  gate parallel PASS dua kali; staging database probe PASS.
- Hasil review auditor: PASS. Hash `Pos_mobile.php`, `Pos_model.php`,
  `routes.php`, dan `cashier_index.php` sama dengan sebelum batch.
- Risiko sisa: browser role UAT serta APK/device/printer nyata tetap manual
  pending. Profil release tetap diblokir oleh direct database credential.
- Batch berikutnya: A4.3 contract test lintas modul, browser, migration, dan
  restore.

## Batch 108 — A4.3 matriks contract lintas modul

- Waktu: 2026-09-04 19:06 WIB.
- Prioritas: membentuk bukti otomatis lintas permission, finance, purchase/SR,
  inventory, production, people, asset, Printer Agent, dan WhatsApp.
- Ringkasan auditor/fixer: auditor menetapkan test DB-free yang deterministik;
  fixer membangun tujuh smoke A4 dan runner gabungan dengan proses terisolasi.
- File berubah: delapan `tools/tests/a4_*_smoke.php`, perbaikan fixture auth/WA,
  dan `tools/tests/printer_agent_trust_smoke.py`.
- Perubahan utama: 35 test lintas modul dijalankan dengan timeout dan output
  terbatas; migration/restore diuji pada fixture temporer tanpa database live.
- Validasi: matriks A4 lulus 35/35.
- Hasil review auditor: kontrak dasar diterima; test awal mengungkap gap nyata
  pada CSRF, transaksi finance, receipt PO, dan atomicity posting component.
- Risiko sisa: source contract bukan pengganti concurrency test database live.
- Batch berikutnya: perbaiki gap kode yang ditemukan matriks.

## Batch 109 — A4.3 CSRF Purchase dan Store Request

- Waktu: 2026-09-04 19:06 WIB.
- Prioritas: endpoint mutasi tetap aman saat global CSRF CodeIgniter `FALSE`.
- Ringkasan auditor/fixer: auditor menemukan endpoint create/update/status,
  receipt, mutasi finance, action, dan fulfill; fixer memasang token session dan
  header scoped sebelum payload/model.
- File berubah: `application/controllers/Purchase.php`,
  `application/controllers/Procurement.php`, lima view purchase, dua view
  procurement, dan `tools/tests/a4_purchase_sr_contract_smoke.php`.
- Perubahan utama: sembilan endpoint Purchase/Procurement wajib POST dan token
  session 64-hex yang cocok melalui `hash_equals`.
- Validasi: PHP lint lulus; Purchase/SR 55/55 dan CSRF-only 30/30 lulus.
- Hasil review auditor: PASS setelah endpoint edit PO dan edit SR ikut dijaga.
- Risiko sisa: browser E2E dengan session nyata belum dijalankan.
- Batch berikutnya: atomicity finance, receipt, dan production.

## Batch 110 — A4.3 atomicity finance, receipt, dan component

- Waktu: 2026-09-04 19:06 WIB.
- Prioritas: mencegah balance/lot/ledger committed ketika header atau audit
  gagal berubah.
- Ringkasan auditor/fixer: auditor mengharuskan lock dan status berada dalam
  transaksi yang sama; fixer memperbaiki writer dan model, lalu review kedua
  menutup race terhadap proses tutup periode.
- File berubah: `application/models/Purchase_model.php`,
  `application/models/Finance_report_model.php`,
  `application/libraries/ComponentStockWriter.php`,
  `application/controllers/Production.php`, serta smoke finance/purchase/
  inventory-production.
- Perubahan utama: mutasi rekening menolak future/CLOSED dan mengunci periode
  serta rekening; close period mengunci row sebelum snapshot; receipt mengunci
  PO/line, menolak status terminal, dan menghitung `PARTIAL_RECEIVED`/
  `RECEIVED`; status adjustment/batch component disimpan di transaksi writer.
- Validasi: PHP lint lulus; finance 20/20, Purchase/SR 55/55,
  inventory-production 27/27, dan matriks A2 12/12 lulus.
- Hasil review auditor: PASS untuk gap kode setelah koreksi kedua.
- Risiko sisa: uji dua transaksi database paralel masih perlu runner integrasi.
- Batch berikutnya: pisahkan gate source dan runtime lalu jalankan gate gabungan.

## Batch 111 — A4.3 gate source/runtime dan status akhir

- Waktu: 2026-09-04 19:06 WIB.
- Prioritas: gate harian tetap cepat tanpa menyamarkan syarat runtime release.
- Ringkasan auditor/fixer: browser/Printer Agent dipisahkan menjadi source
  contract dan runtime capability; backup APK yang untracked tidak lagi dikunci
  checksum lama tetapi tetap wajib terisolasi dari runtime.
- File berubah: `tools/tests/a4_browser_shell_runtime_smoke.php`,
  `tools/tests/printer_agent_trust_smoke.py`, matriks A4,
  `tools/tests/finance_quality_gate.php`, contract gate, smoke backup APK, serta
  tiga dokumen roadmap/progres/log.
- Perubahan utama: profile `parallel` menjalankan source contract; profile
  `release`/`staging` menambahkan browser render dan Printer Agent HTTP sebagai
  blocker. Tidak ada SQL/schema/data yang diubah.
- Validasi: PHP lint lulus; Composer valid dengan deprecation tool lama;
  matriks A4 35/35; gate `parallel` required 20/20 dan development 4/4; probe
  database A2 SELECT-only selesai.
- Hasil review auditor: gate semantik PASS, tetapi A4.3 tetap `[~]` sampai dua
  runtime benar-benar lulus.
- Risiko sisa: Firefox Snap gagal headless render pada server ini; Python Flask
  belum tersedia; release secret gate tetap blocked karena credential staging
  langsung.
- Batch berikutnya: sediakan runner browser/Python kompatibel, tutup A4.3, lalu
  masuk A4.4.

## Batch 112 — Penutupan runtime A4.3

- Waktu: 2026-09-04 19:35 WIB.
- Prioritas: membuktikan runtime browser dan Printer Agent yang sebelumnya
  tertahan tanpa mengubah source aplikasi atau database.
- Ringkasan auditor/fixer: runtime Chrome headless dipakai untuk fixture shell
  loopback desktop/mobile; Printer Agent dijalankan dari virtual environment
  terisolasi. Auditor menyatakan kedua bukti runtime cukup untuk menutup A4.3.
- File berubah: `tools/tests/a4_browser_shell_runtime_smoke.php`,
  `tools/tests/bootstrap_a4_runtime.sh`, `tools/tests/finance_quality_gate.php`,
  dan `tools/tests/finance_quality_gate_contract_smoke.php`.
- Perubahan utama: runner browser memilih Chrome native, mengambil dua
  screenshot nyata, dan membersihkan fixture temporer; bootstrap runtime
  idempotent menyiapkan Python venv di luar document root; gate kini memakai
  interpreter Python yang benar dan gagal tertutup bila runtime tidak ada.
- Perubahan runtime server: Google Chrome stable dan paket `python3-venv`
  dipasang; virtual environment dibuat di
  `/var/lib/finance-a4-runtime/printer-venv`. Direktori temporer hasil probe
  batch ini sudah dihapus.
- Validasi: browser runtime 28 check lulus; Printer Agent source dan HTTP Flask
  runtime lulus; bootstrap dijalankan dua kali; gate `release` dan `staging`
  masing-masing required 20/20, development 4/4, runtime 2/2, serta probe
  database staging 1/1 lulus.
- Hasil review auditor: A4.3 PASS `[x]`. Kegagalan kontrak secret produksi
  bukan blocker A4.3 dan tetap dipertahankan sebagai blocker release.
- Risiko sisa: UAT role melalui browser serta APK/device/printer fisik tetap
  validasi manual; dependency Python belum dikunci secara reproducible.
- Batch berikutnya: A4.4 lint, dependency, secret, dan package preflight.

## Batch 113 — A4.4-1 release preflight fail-closed

- Waktu: 2026-09-04 19:35 WIB.
- Prioritas: membangun pemeriksaan release read-only yang tidak dapat dilewati
  dengan memperkecil scope policy atau memperluas allowlist rahasia.
- Ringkasan auditor/fixer: auditor dua kali menemukan scope policy dan
  pengecualian fixture yang terlalu longgar. Fixer menyiapkan policy schema v2
  dan fingerprint fixture; integrasi diselesaikan main agent setelah runner
  fixer dihentikan agar batch tidak melebar. Review akhir auditor menyatakan
  PASS tanpa regresi keamanan baru.
- File berubah: `tools/release/package_policy.json`,
  `tools/tests/a4_release_preflight_smoke.php`,
  `tools/tests/a4_release_preflight_contract_smoke.php`,
  `tools/tests/a4_cross_module_contract_matrix_smoke.php`,
  `tools/tests/finance_quality_gate.php`,
  `tools/tests/finance_quality_gate_contract_smoke.php`, serta dokumen status
  `_30`, `_28`, progres, dan log ini.
- Perubahan utama: lint PHP dan syntax JS/Python, validasi dependency lock,
  secret scan, serta virtual package allow/deny policy masuk tier preflight.
  Policy mewajibkan seluruh root/file utama, menolak wildcard exception, dan
  hanya menerima fixture exact path/line/category/SHA-256. File symlink,
  unreadable, atau oversize gagal tertutup tanpa mengikuti target symlink;
  output temuan hanya kategori/path/baris.
- Validasi: PHP lint lulus; preflight contract 14/14; quality-gate contract
  23/23; matriks A4 35/35; dua output preflight byte-identik; quality gate
  `parallel` required 21/21 dan development 4/4, hasil keseluruhan PASS.
- Hasil review auditor: A4.4-1 hardening PASS. Tujuh temuan release yang tersisa
  teridentifikasi benar dan bukan regresi batch.
- Risiko sisa: `composer.lock` belum ada, empat requirement Python belum
  hash-pinned, dan dua baris credential database staging langsung tetap ditolak
  kontrak produksi. Virtual candidate set belum menjadi enforcement artefak
  paket aktual; semantic static analysis juga belum ditambahkan.
- Dampak aplikasi/data: tidak ada source aplikasi, SQL, schema, atau data yang
  diubah. File POS/mobile tidak disentuh.
- Batch berikutnya: A4.4-2 dependency reproducibility (`composer.lock`, Python
  hash lock, dan bootstrap `pip --require-hashes`), kemudian static/dependency
  vulnerability scan serta package artifact enforcement.

## Batch 114 — A4.4-2 dependency reproducibility

- Waktu: 2026-09-04 20:05 WIB.
- Prioritas: memastikan instalasi dependency PHP dan Printer Agent dapat diulang
  dengan versi serta checksum yang sama.
- Ringkasan auditor/fixer: auditor mengarahkan lock tanpa perubahan constraint
  aplikasi dan mempertahankan credential staging sebagai blocker terpisah.
  Fixer menghasilkan Composer lock serta Python direct-input/hash lock; main
  agent menyelesaikan pengikatan bootstrap dan preflight. Auditor akhir
  menyatakan batch PASS.
- File berubah: `.gitignore`, `composer.lock`,
  `tools/pos_printer_agent/requirements.in`,
  `tools/pos_printer_agent/requirements.txt`,
  `tools/tests/bootstrap_a4_runtime.sh`, `tools/release/package_policy.json`,
  `tools/tests/a4_release_preflight_smoke.php`, contract preflight, serta
  dokumen status `_30`, `_28`, progres, dan log ini. `composer.json` tidak
  diubah.
- Perubahan utama: `composer.lock` tidak lagi diabaikan Git; direct intent
  Python dipisahkan ke `requirements.in`; seluruh dependency transitif di
  `requirements.txt` exact-pinned dan memiliki SHA-256; bootstrap mewajibkan
  `pip --require-hashes`; preflight memahami lock multiline dan memvalidasi
  struktur Composer lock.
- Validasi: PHP lint dan Bash syntax lulus; Composer validate lulus; Composer
  install dry-run tanpa scripts/plugins lulus dengan rencana 29 paket dan tidak
  membuat `vendor/`; preflight contract 16/16; bootstrap existing venv lulus;
  instalasi fresh temporary venv dengan `--require-hashes` serta import Flask,
  serial, Pillow, dan qrcode lulus; quality gate `parallel` required 21/21 dan
  development 4/4 lulus.
- Hasil review auditor: PASS. Composer/Python lock, parser multiline, dan
  bootstrap hash enforcement dinilai konsisten.
- Risiko sisa: bootstrap terhadap venv lama tidak menghapus paket yatim;
  deployment bersih sebaiknya rebuild/swap venv. Versi Python dan tool pembuat
  lock harus dicatat saat regenerasi. Composer CLI server versi 2.0.14 masih
  mencetak peringatan deprecation pada PHP 8.1 walaupun validate/dry-run lulus.
  Dua credential database staging langsung tetap memblokir release/staging.
- Dampak aplikasi/data: patch batch ini tidak mengubah source aplikasi, SQL,
  schema, atau data. Saat batch berjalan terdeteksi perubahan concurrent pada
  `Pos_mobile.php` dan `Pos_model.php`; keduanya dianggap pekerjaan APK pemilik,
  dipertahankan, dan tidak di-revert. `routes.php` serta `cashier_index.php`
  tetap sama. Venv temporer validasi sudah dihapus.
- Batch berikutnya: A4.4-3 secret externalization bila kebijakan deployment
  diubah; bila direct config staging dipertahankan, lanjut static/dependency
  vulnerability scan dan enforcement artefak paket dengan release tetap BLOCKED.

## Batch 115 — A4.4-3a vulnerability gate offline

- Waktu: 2026-09-04 20:25 WIB.
- Prioritas: menutup advisory dependency nyata dan membuat scan kerentanan
  Composer/npm/Python yang repeatable tanpa mengirim dependency project saat
  quality gate berjalan.
- Ringkasan auditor/fixer: auditor memilih OSV-Scanner pinned dan database
  offline serta menerima update lock-only `mysql2`. Fixer memperbarui npm lock
  dan menyiapkan toolchain/bootstrap; main agent melengkapi smoke, contract, dan
  wiring gate. Dua review auditor menutup false-clean extraction serta race
  refresh/scan sebelum verdict akhir PASS.
- File berubah: `wa-engine/package-lock.json`,
  `tools/security/toolchain.lock.json`,
  `tools/tests/bootstrap_a4_security_runtime.sh`,
  `tools/tests/a4_dependency_vulnerability_smoke.php`, contract vulnerability,
  matriks A4, quality gate beserta contract, dan empat dokumen status. File
  `wa-engine/package.json` tetap byte-identik.
- Perubahan utama: `mysql2` naik 3.22.6 → 3.24.3 tanpa `audit fix --force`;
  OSV-Scanner Linux amd64 2.5.1 dikunci SHA-256; snapshot Packagist/npm/PyPI
  maksimal 48 jam ditempatkan di `/var/lib/finance-a4-security`. Scanner selalu
  `--offline --all-packages`, mewajibkan exact tiga source dan minimal satu
  package per source, serta memegang shared lock selama checksum+scan. Bootstrap
  memakai exclusive lock saat aktivasi database+snapshot.
- Validasi: PHP lint dan Bash syntax lulus; vulnerability source PASS; contract
  15/15; quality-gate contract 25/25; temporary `npm ci --ignore-scripts`
  memuat `mysql2` 3.24.3; npm audit produksi 0; seluruh matriks A4/WhatsApp
  35/35; OSV offline memindai 3 source/144 paket dengan 0 advisory; gate
  `parallel` required 23/23 dan development 4/4 lulus.
- Validasi release: runtime browser/Printer Agent 2/2 dan security 1/1 lulus.
  Profile release tetap exit 1 secara benar karena release-secret dan preflight
  mendeteksi dua credential database staging langsung.
- Hasil review auditor: PASS setelah bukti ekstraksi package diwajibkan dan race
  snapshot ditutup dengan shared/exclusive lock.
- Risiko sisa: snapshot advisory perlu direfresh maksimal tiap 48 jam. Versi
  Node/npm pembentuk lock perlu dipin agar metadata lock tidak churn pada update
  berikutnya. Runtime security memakai sekitar 565 MB di luar document root.
- Dampak aplikasi/data: tidak ada controller/model/view/POS, SQL, schema, atau
  data yang diubah. Direktori temporer dependency yang dibuat fixer telah
  dibersihkan.
- Batch berikutnya: A4.4-3b semantic static gate, lalu A4.4-3c deterministic
  package artifact enforcement. Secret staging tetap menjadi blocker terpisah.

## Batch 116 — A4.4-3b semantic static gate

- Waktu: 2026-09-04 20:45 WIB.
- Prioritas: menahan regresi semantik PHP baru pada seluruh source aplikasi
  tanpa menjalankan bootstrap CodeIgniter atau membuka database.
- Ringkasan auditor/fixer: auditor menetapkan full scope `application/`, runtime
  terpin di luar document root, baseline yang tidak boleh bertambah diam-diam,
  dan blocking pada profile release/staging. Fixer memasang PHPStan serta gate;
  auditor menyatakan PASS dan menerima level 0 sebagai baseline awal CI3.
- File berubah: `composer.json`, `composer.lock`, `tools/static/phpstan.neon`,
  `tools/static/ci3-stubs.php`, `tools/static/phpstan-baseline.neon`,
  `tools/static/toolchain.lock.json`, bootstrap/static smoke beserta contract,
  dan quality gate beserta contract.
- Perubahan utama: PHPStan 1.12.27 dikunci pada Composer lock dan dipasang di
  `/var/lib/finance-a4-static`; seluruh `application/`, termasuk POS/mobile,
  dipindai. Baseline 5.008 temuan lama dikunci tepat, unmatched ignore dilaporkan,
  timeout/output dibatasi, dan source/contract selalu menjadi required gate.
- Validasi: PHP lint dan Bash syntax lulus; Composer validate valid; static
  contract 14/14; quality-gate contract 27/27; scan PHPStan aktual PASS dengan
  `scope=application baseline_errors=5008`.
- Hasil review auditor: PASS; gate efektif memblokir error baru dan tidak
  menyentuh source aplikasi, database, SQL, atau empat file POS/APK yang dijaga.
- Risiko sisa: level analisis masih 0 dan baseline lama besar; peningkatan level
  serta pengurangan baseline dilakukan bertahap per modul setelah A4.
- Batch berikutnya: A4.4-3c shared package policy dan artifact builder.

## Batch 117 — A4.4-3c artifact builder dan penutupan A4

- Waktu: 2026-09-04 21:07 WIB.
- Prioritas: memastikan hanya source yang diizinkan dapat menjadi artefak rilis
  dan kegagalan gate tidak pernah meninggalkan paket final.
- Ringkasan auditor/fixer: auditor mewajibkan enumerator tunggal, urutan
  preflight→static→vulnerability, paket reproducible, checksum, dan publish
  atomik. Fixer mengekstrak policy bersama serta membuat builder/contract. Dua
  masalah runner yang ditemukan pada validasi gabungan diperbaiki: static smoke
  tidak lagi terhitung dua kali dan assertion overflow tidak lagi bergantung
  pada race exit code child process.
- File berubah: `tools/release/ReleasePackagePolicy.php`,
  `tools/release/build_release_artifact.php`, preflight smoke beserta contract,
  `tools/tests/a4_release_artifact_contract_smoke.php`, matriks lintas modul,
  quality gate, static contract, serta dokumen roadmap/progres/log.
- Perubahan utama: preflight dan builder memakai candidate set yang sama dari
  Git+policy; traversal, symlink, unreadable, backup, upload, log, vendor, secret,
  dan mutasi source ditolak. Tar memakai urutan, epoch, owner/group, serta mode
  ternormalisasi dan membawa `RELEASE-MANIFEST.json` berisi SHA-256 setiap file.
  File final hanya dipublish melalui rename setelah semua pemeriksaan lulus.
- Validasi: PHP lint lulus; preflight contract 18/18; artifact contract 9/9;
  quality-gate contract 27/27; cross-module 35/35; overflow contract 10/10 run;
  profile `parallel` required 26/26 dan development 4/4, hasil PASS. Dua fixture
  build byte-identik dan seluruh checksum archive cocok.
- Validasi workspace nyata: preflight menemukan tepat dua assignment credential
  database staging pada path/baris tanpa mencetak nilai. Builder exit 1 sebelum
  static/security/package dan `FINAL_ARTIFACT_PRESENT=no`.
- Hasil review auditor: PASS. Fase A4 dinyatakan `[x]` implementation-complete;
  tidak ada blocker A4 tersisa.
- Risiko sisa: release/staging tetap `BLOCKED` oleh keputusan direct credential
  A0. UAT role browser dan APK/device/printer fisik masih manual. PHPStan level
  dan baseline perlu ditingkatkan bertahap, bukan dengan memperlebar ignore.
- Batch berikutnya: A5 schema version registry dan migration runner
  deterministik; lalu clean install/upgrade/backup-restore/rollback/signature.

## Batch 118 — A5.1 katalog migration dan registry DB-free

- Waktu: 2026-09-04 21:22 WIB.
- Prioritas: membangun sumber tunggal urutan/checksum migration sebelum ada SQL
  yang boleh dieksekusi oleh release tooling.
- Ringkasan auditor/fixer: auditor memilih fondasi DB-free agar tujuh SQL aktif
  lama tidak langsung diasumsikan aman dan SQL historis/repair tidak terbawa.
  Fixer membuat katalog, DDL registry, runner validate/plan, serta contract;
  auditor akhir menyatakan PASS.
- File berubah: `tools/db/migration_catalog.json`,
  `tools/db/migration_runner.php`,
  `sql/2026-09-04c_a5_schema_migration_registry_foundation.sql`,
  `tools/tests/a5_migration_catalog_runner_contract_smoke.php`, dan wiring
  required pada quality gate beserta contract.
- Perubahan utama: katalog v1 mengelola satu migration registry dan mengakui
  tujuh SQL top-level sebagai legacy belum dikelola. Runner baru hanya memiliki
  mode DB-free `validate`/`plan`; checksum, ID/order/path/dependency/policy,
  symlink, cycle, SQL tak dikenal, dan support-repair semuanya fail-closed.
- Validasi: PHP lint lulus; A5.1 contract 27/27; quality-gate contract 27/27;
  A4 migration/backup/restore 19/19; katalog validate dan dua plan deterministik
  lulus; Composer validate valid.
- Hasil review auditor: PASS. SQL registry dinilai DDL-only, repeat-safe, dan
  tidak berisi seed/data customer; database staging tidak disentuh.
- Risiko sisa: tujuh SQL legacy belum memiliki checksum/dependency terkelola;
  runner belum memiliki executor, lock, ledger verification, atau mode apply.
- Batch berikutnya: A5.2 executor/state protocol dengan fake-client fixture;
  belum melakukan apply ke staging.

## Batch 119 — A5.2 executor dan state protocol fixture-only

- Waktu: 2026-09-04 21:37 WIB.
- Prioritas: memastikan apply migration memiliki lock, verifikasi ledger,
  timeout, replay aman, dan redaksi sebelum diizinkan menyentuh staging.
- Ringkasan auditor/fixer: fixer membuat executor satu sesi client dan fake
  client contract. Auditor awal menolak karena timeout belum membunuh child,
  pemeriksaan environment terlalu lemah/lebar, dan probe registry hanya memakai
  jumlah kolom. Fixer menutup ketiganya; review ulang menyatakan PASS.
- File berubah: `tools/db/migration_runner.php`,
  `tools/tests/a5_migration_executor_contract_smoke.php`, dan wiring quality
  gate beserta contract.
- Perubahan utama: mode `apply`/`--dry-run` memakai option file absolut di luar
  repo dengan permission aman; menolak credential CLI/environment; menjalankan
  advisory lock, exact registry contract, state/checksum verification, SQL,
  ledger insert setelah sukses, replay no-op, release lock, serta global timeout
  yang menghentikan client macet.
- Validasi: PHP lint lulus; A5.1 27/27; A5.2 21/21; quality-gate contract 27/27.
  Fixture membuktikan drift, contention, wrong schema/index, malformed output,
  SQL failure, timeout, permission, dan credential semuanya fail-closed.
- Hasil review auditor: PASS untuk controlled staging readiness; database nyata
  belum disentuh pada batch ini.
- Risiko sisa: controlled apply memerlukan backup dan client option file 0600;
  baru registry foundation yang terkelola, belum tujuh SQL legacy.
- Batch berikutnya: A5.3 backup staging, before evidence, bootstrap registry,
  read-only verification, dan replay `applied=0/skipped=1`.

## Batch 120 — A5.3 controlled staging bootstrap registry

- Waktu: 2026-09-04 21:45 WIB.
- Prioritas: membuktikan executor dan ledger pada MariaDB staging dengan backup,
  before/after evidence, serta replay tanpa mengadopsi SQL legacy.
- Ringkasan auditor/fixer: auditor mengizinkan bootstrap satu migration setelah
  A5.2 lulus. Percobaan awal fail-closed mengungkap representasi default `NULL`
  MariaDB dan konflik collation pada state lookup. Fixer membuat kompatibilitas
  yang sempit; auditor mereview kedua patch sebelum replay akhir.
- File berubah: `tools/db/migration_runner.php` dan
  `tools/tests/a5_migration_executor_contract_smoke.php`. Database staging
  mendapat tabel `sys_schema_migration` serta satu ledger row registry.
- Perubahan utama: exact registry contract menerima dua representasi metadata
  nullable MariaDB/MySQL hanya pada empat kolom nullable; state lookup memakai
  pembandingan byte ID kanonis agar bebas collation koneksi. Query registry
  diekstrak untuk test library-only tanpa menjalankan CLI main.
- Validasi: A5.1 27/27; A5.2 26/26; query registry aktual
  `COMPATIBLE_V1`; backup gzip sekitar 19 MB di luar repo memiliki sidecar
  SHA-256 valid dan permission 0600. Final apply menghasilkan
  `applied=1/skipped=0`; verifikasi row ID/path/checksum/catalog/tool tepat satu;
  replay menghasilkan `applied=0/skipped=1`.
- Hasil review auditor: patch nullable dan byte comparison PASS; dua kegagalan
  awal tidak menulis ledger palsu dan advisory lock telah bebas.
- Risiko sisa: backup staging berbentuk gzip+checksum dan belum terenkripsi;
  tujuh SQL legacy belum masuk ledger. Rollback registry tidak otomatis dan
  hanya boleh manual bila tidak ada migration setelahnya.
- Batch berikutnya: audit/enrollment tujuh SQL legacy dengan pemisahan tegas
  antara bukti already-applied staging dan urutan clean install.

## Batch 121 — A5.4 disposition tujuh SQL legacy

- Waktu: 2026-09-04 21:55 WIB.
- Prioritas: mencegah replay/adopsi palsu SQL lama sebelum tersedia bukti
  struktur dan riwayat deployment yang cukup.
- Ringkasan auditor/fixer: auditor menetapkan semua SQL lama harus tetap
  non-executable. Fixer membuat inventaris hash, fitur SQL, objek, dependency
  runtime, risiko, probe read-only, dan disposition; auditor menyatakan PASS.
- File berubah: `tools/db/legacy_sql_inventory.json`,
  `tools/tests/a5_legacy_sql_inventory_contract_smoke.php`, dan wiring quality
  gate beserta contract.
- Perubahan utama: tiga file ditandai `historical-unverified` dan empat file
  `candidate-canonical`; tidak satu pun masuk plan install/upgrade atau ledger.
  Probe inventaris dibatasi satu SELECT tanpa comment, multi-statement, DDL/DML,
  transaction, lock, prepared statement, atau CALL.
- Validasi: PHP lint lulus; A5.4 17/17; A5.1 27/27; A5.2 26/26;
  quality-gate contract 27/27. Plan upgrade tetap hanya registry foundation.
- Hasil review auditor: PASS; hash dan evidence tujuh file cocok serta semua
  disposition tetap non-deployable. Database/SQL tidak diubah pada batch ini.
- Risiko sisa: probe saat ini baru inventaris, belum fingerprint struktur exact;
  keberadaan tabel/kolom tidak membuktikan migration pernah diterapkan.
- Batch berikutnya: A5.5 structural fingerprint dan eligibility read-only;
  tanpa enrollment, replay, atau ledger adoption.

## Batch 122 — A5.5 structural fingerprint dan probe staging read-only

- Waktu: 2026-09-04 22:14 WIB.
- Prioritas: membuktikan kesesuaian struktur empat kandidat SQL lama tanpa
  replay, enrollment, ledger adoption, atau perubahan data aplikasi.
- Ringkasan auditor/fixer: fixer membuat manifest fingerprint, probe, dan
  contract. Auditor menemukan lalu meminta penutupan bentuk SELECT berefek
  samping/lock, integritas alias global, `FOR SHARE`, dan terminator protocol
  MariaDB. Fixer menutup seluruh temuan; auditor akhir menyatakan PASS.
- File berubah: `tools/db/legacy_schema_fingerprints.json`,
  `tools/db/schema_fingerprint_probe.php`,
  `tools/tests/a5_schema_fingerprint_contract_smoke.php`, serta wiring required
  quality gate/manifest.
- Perubahan utama: tiga SQL historical selalu `not_eligible`; empat kandidat
  diperiksa dengan SELECT metadata/semantik exact. Validator menolak stacked
  SQL, file output/read, variable assignment, lock, wait/sleep/benchmark, DDL,
  dan DML. Probe tidak memiliki jalur apply/adopt.
- Validasi: PHP lint dan manifest validate lulus; A5.5 contract 40/40;
  quality-gate contract 27/27; `git diff --check` lulus. Probe staging memakai
  akun sementara SELECT-only melalui option file 0600, environment bersih, dan
  menghasilkan tiga kandidat `eligible_evidence`. Kandidat auth throttle
  `not_eligible` hanya pada `auth_login_failure_fk`. Ledger sebelum/sesudah
  identik satu row; SQL legacy tidak dijalankan dan akun/file sementara bersih.
- Hasil review auditor: PASS untuk evidence probe. Kegagalan FK dinilai
  structural drift, bukan dasar replay atau adopsi migration lama.
- Risiko sisa: status migration auth throttle belum kanonis. Penyebab detail,
  kompatibilitas tipe/index, dan jumlah orphan harus dibuktikan sebelum DDL.
- Batch berikutnya: A5.6 forensic preflight FK auth read-only; forward migration
  baru hanya boleh dibuat bila state `missing_fk_clean`.

## Batch 123 — A5.6 forensic preflight FK auth

- Waktu: 2026-09-04 22:21 WIB.
- Prioritas: membedakan FK hilang yang aman ditambah dari orphan atau
  ketidakcocokan struktur sebelum membuat migration baru.
- Ringkasan auditor/fixer: auditor menetapkan empat state redacted dan seluruh
  pemeriksaan harus SELECT-only. Fixer membuat probe/contract. Auditor meminta
  pembatasan referenced schema agar FK lintas database tidak salah dianggap
  valid; fixer memperbaiki dan review akhir menyatakan PASS.
- File berubah: `tools/db/auth_login_failure_fk_probe.php`,
  `tools/tests/a5_auth_login_failure_fk_probe_contract_smoke.php`, serta wiring
  required pada quality gate beserta contract.
- Perubahan utama: probe memeriksa engine dua tabel, kompatibilitas tipe/sign/
  nullability, index sumber/target, FK bernama atau bersaing, dan jumlah orphan
  agregat. Output dibatasi `missing_fk_clean`, `missing_fk_orphan`,
  `structural_incompatible`, atau `already_present`; tidak ada row user.
- Validasi: PHP lint/validate lulus; A5.6 contract 38/38; quality-gate contract
  27/27; `git diff --check` lulus. Controlled staging probe memakai akun
  sementara SELECT-only dan menghasilkan `structural_incompatible`; ledger
  migration sebelum/sesudah identik dan akun/file sementara dibersihkan.
- Hasil review auditor: PASS untuk probe. State staging belum mengizinkan DDL
  atau forward migration karena bukan `missing_fk_clean`.
- Risiko sisa: alasan struktur exact belum diklasifikasikan; replay migration
  auth lama tetap dilarang dan data login failure tidak disentuh.
- Batch berikutnya: forensic reason-code schema auth read-only atau lanjut
  fondasi runtime/install sesuai pilihan auditor A5.

## Batch 124 — A5.7 reason-code detail FK auth

- Waktu: 2026-09-04 22:27 WIB.
- Prioritas: mengisolasi penyebab `structural_incompatible` tanpa membaca row
  login atau langsung mengubah constraint.
- Ringkasan auditor/fixer: auditor memilih metadata-only reason-code probe.
  Fixer membuat satu SELECT `information_schema`, precedence kode tetap, dan
  contract seluruh cabang; auditor menyatakan PASS tanpa revisi.
- File berubah: `tools/db/auth_login_failure_fk_detail_probe.php`,
  `tools/tests/a5_auth_login_failure_fk_detail_probe_contract_smoke.php`, serta
  wiring required quality gate beserta contract.
- Perubahan utama: probe membedakan metadata/engine/type/index/FK bernama/FK
  kompetitor melalui kode redacted. Tidak ada query ke row `auth_login_failure`,
  ledger, adoption, DDL, DML, atau eksekusi SQL legacy.
- Validasi: PHP lint/validate lulus; A5.7 contract 49/49; A5.5 40/40; A5.6
  38/38; quality-gate contract 27/27; `git diff --check` lulus. Controlled
  staging probe SELECT-only menghasilkan `named_fk_wrong_contract`; ledger
  identik dan seluruh akun/file sementara dibersihkan.
- Hasil review auditor: PASS; output fixed-code, urutan alasan, metadata exact,
  timeout/kill, dan redaksi dinilai layak.
- Risiko sisa: aspek kontrak FK yang salah belum dibedakan antara target/schema/
  kolom/action; constraint belum boleh di-drop atau dibuat ulang.
- Batch berikutnya: A5.8 exact FK contract preflight lalu forward migration baru
  hanya setelah review dan bukti orphan aman.

## Batch 125 — A5.8 exact named-FK contract preflight

- Waktu: 2026-09-04 22:31 WIB.
- Prioritas: memecah `named_fk_wrong_contract` menjadi aspek local column,
  schema, target, delete/update rule, cardinality, atau metadata incomplete.
- Ringkasan auditor/fixer: auditor mewajibkan satu SELECT metadata dan kode
  redacted berprecedence. Fixer membuat probe/contract; auditor menyatakan PASS.
- File berubah: `tools/db/auth_login_failure_named_fk_contract_probe.php`,
  `tools/tests/a5_auth_login_failure_named_fk_contract_probe_contract_smoke.php`,
  serta wiring required quality gate beserta contract.
- Perubahan utama: metadata constraint bernama diperiksa tanpa row data, DDL,
  ledger, adoption, atau replay SQL lama.
- Validasi: PHP lint/validate lulus; A5.8 contract awal 57/57 dan quality-gate
  contract 27/27. Probe SELECT-only menghasilkan
  `named_fk_metadata_incomplete`; ledger identik dan akun sementara bersih.
- Hasil review auditor: implementasi probe PASS, tetapi hasil staging belum
  mengizinkan DDL. Investigasi berikutnya membuktikan ini false negative
  visibility MariaDB, bukan FK rusak.
- Risiko sisa: akun SELECT-only tidak dapat melihat row
  `REFERENTIAL_CONSTRAINTS`; dikoreksi pada Batch 126.
- Batch berikutnya: A5.9 permission visibility correction dan rerun metadata.

## Batch 126 — A5.9 koreksi visibility metadata MariaDB

- Waktu: 2026-09-04 22:49 WIB.
- Prioritas: mencegah keterbatasan izin metadata dianggap sebagai schema drift
  dan menutup track FK auth tanpa DDL yang tidak perlu.
- Ringkasan auditor/fixer: bukti server menunjukkan akun SELECT-only melihat
  KCU tetapi tidak referential rule; penambahan REFERENCES sementara membuka
  metadata exact. Fixer mengoreksi tiga probe; auditor meminta mixed-state
  precedence dan penamaan acknowledgement yang jujur. Main menyelesaikan
  fixture akhir; review menyatakan PASS bersyarat grant eksternal.
- File berubah: tiga probe A5.6–A5.8 dan tiga contract terkait. Tidak ada file
  aplikasi, POS/APK, SQL migration, atau schema/data yang diubah.
- Perubahan utama: state `permission_visibility_limited` tidak lagi dianggap
  drift dan hanya muncul bila seluruh fakta KCU lain exact. Fakta
  table/type/index/KCU/competing selalu menang. Flag CLI bernama
  `--permission-profile-acknowledgement=metadata-read`, bukan klaim enforcement.
- Validasi: PHP lint lulus; A5.5 40/40; A5.6 48/48; A5.7 62/62; A5.8 76/76;
  quality-gate contract 27/27; diff check lulus. Controlled staging dengan
  grant table-scoped SELECT+REFERENCES menghasilkan A5.6 `already_present`,
  A5.7/A5.8 tanpa reason. Rerun fingerprint terpisah menghasilkan 4/4 kandidat
  `eligible_evidence`, tiga historical tetap excluded. Kedua run membuktikan
  ledger unchanged; akun/option file dibersihkan.
- Hasil review auditor: FK auth exact; tidak ada repair, orphan preflight, DDL,
  legacy replay, atau ledger adoption yang diperlukan. Redacted grant
  attestation tersimpan di luar repo:
  `/var/lib/finance-a5-runtime/evidence/a5_metadata_grant_attestation_20260904_224853.json`
  dan `a5_fingerprint_grant_attestation_20260904_224925.json`.
- Risiko sisa: acknowledgement tidak memverifikasi grant dari dalam tool;
  operator/DBA tetap wajib memverifikasi profil minimum dari luar.
- Batch berikutnya: kembali ke fondasi luas A5—clean install, upgrade,
  backup/restore, runtime matrix, dan signature—tanpa melanjutkan track FK auth.

## Batch 127 — A5.10 atomic backup bundle dan restore preflight

- Waktu: 2026-09-04 23:12 WIB.
- Prioritas: membentuk artefak backup yang utuh, terverifikasi, dan tidak dapat
  tertukar sebelum restore drill disposable dilakukan.
- Ringkasan auditor/fixer: fixer membuat manifest builder/preflight DB-free.
  Auditor menemukan resolver gzip dari PATH, TOCTOU publish, permission/fsync
  yang belum fail-closed, parent/race, dan final yang tertinggal saat sync gagal.
  Fixer menutup temuan secara bertahap; review akhir menyatakan PASS.
- File berubah: `tools/db/backup_bundle_manifest.php`,
  `tools/db/restore_preflight.php`,
  `tools/tests/a5_backup_bundle_restore_preflight_contract_smoke.php`, serta
  wiring required quality gate beserta contract. `backup_full.sh` tidak diubah.
- Perubahan utama: builder menyalin archive regular ke private sibling stage,
  memverifikasi identitas/hash/size/gzip/katalog, menulis manifest kanonis,
  memaksa mode 0700/0600, fsync, owner-controlled parent, parent flock, rename
  atomik, dan trusted `/usr/bin/sync`. Gzip dikunci ke `/usr/bin/gzip` yang
  canonical/root-owned/non-writable. Restore preflight hanya menghasilkan plan
  dan mewajibkan reverify tepat sebelum stream konsumsi; tidak memiliki client
  database atau target restore.
- Validasi: PHP lint lulus; A5.10 contract 24/24; A4 migration/backup/restore
  19/19; quality-gate contract 27/27. Quality profile `parallel` lulus required
  34/34 dan development 4/4. Release/preflight tetap diblokir tepat oleh direct
  credential staging, bukan regresi A5. Empat hash file POS/APK tetap identik.
- Hasil review auditor: PASS; A5.11 siap dimulai hanya untuk fresh disposable
  database dengan immediate reverify dan tanpa overwrite sumber/staging.
- Risiko sisa: A5.10 belum menjalankan restore nyata. Test hook hanya tersedia
  saat library mode dan tidak dapat diaktifkan lewat CLI normal. Failure sync
  membersihkan bundle final sambil lock masih dipegang dan mempertahankan error
  primer.
- Batch berikutnya: A5.11 controlled disposable restore drill, lalu verifikasi
  ledger/fingerprint/health sebelum cleanup target disposable.

## Batch 128 — A5.11 controlled disposable restore drill

- Waktu: 2026-09-05 05:51 WIB.
- Prioritas: membuktikan backup A5 dapat dipulihkan nyata tanpa menimpa atau
  mengubah database Finance sumber.
- Ringkasan auditor/fixer: auditor mengunci target/user acak lokal, akun restore
  target-only, immediate bundle reverify, registry/fingerprint, evidence
  redacted, dan cleanup exact. Fixer menyiapkan runner/contract serta wiring;
  review auditor menemukan metacommand client, endpoint remote, dan reap child.
  Temuan ditutup dengan sandbox, scanner fail-closed, local-only endpoint, dan
  cleanup process sebelum live drill. Review akhir menyatakan PASS.
- File berubah: `tools/db/disposable_restore_drill.php`,
  `tools/tests/a5_disposable_restore_drill_contract_smoke.php`, serta wiring
  required di `tools/tests/finance_quality_gate.php` dan contract-nya.
- Perubahan utama: runner membuat database/user `a511_restore_<16hex>` yang
  fresh, memberi privilege hanya pada target tanpa global/grant option,
  memverifikasi bundle ulang tepat sebelum stream, memakai trusted
  `/usr/bin/gzip` dan `/usr/bin/mariadb --sandbox --local-infile=0`, menolak
  metacommand/escape lintas database, menjalankan migration runner resmi dan
  fingerprint, lalu selalu membersihkan target serta option file sementara.
- Validasi: PHP lint lulus; A5.11 contract 42/42; A5.10 24/24; A4 migration,
  backup, restore 19/19; quality-gate contract 27/27. Live drill menghasilkan
  registry `COMPATIBLE_V1`, fingerprint 4/4, dan cleanup verified. Post-check
  independen memastikan target DB/user `0/0`, source registry tetap satu row,
  backup checksum valid, evidence mode 0600/redacted, dan tidak ada option
  target/admin sementara. Quality profile parallel lulus required 35/35 dan
  development 4/4. Empat hash file POS/APK tetap identik.
- Hasil review auditor: PASS A5.11 post-live. Evidence retained di luar repo:
  `/var/lib/finance-a5-runtime/evidence/a5_disposable_restore_962512dbd73727fb.json`.
- Risiko sisa: release/preflight masih diblokir direct credential database
  staging. Live drill membuktikan upgrade dari backup staging, belum menjadi
  bukti clean install customer, rollback release, atau retention operasional.
- Cleanup material: database dan user disposable dihapus permanen setelah
  verifikasi; keduanya hanya fixture dan dapat dibuat ulang dari bundle sumber
  yang tetap disimpan. Option credential sementara milik drill juga dihapus.
- Batch berikutnya: A5.12 baseline clean-install schema/seed kanonis, kemudian
  health check/rollback dan validasi retention.

## Batch 129 — Normalisasi roadmap dan control board tunggal

- Waktu: 2026-09-05 06:37 WIB.
- Prioritas: menghilangkan status fase yang tumpang tindih atau tampak selesai
  hanya karena satu batch lulus, terutama rollout UI A3 pada bagian 8.3.
- Ringkasan auditor/fixer: auditor memisahkan status implementasi, validasi,
  release/data, serta membedakan bukti batch dari penyelesaian fase. Fixer
  menambahkan pemeriksaan konsistensi roadmap dan memasukkannya ke quality gate.
  Main agent menata dua dokumen induk dan snapshot progress mengikuti kontrak
  tersebut.
- File berubah: `docs/2026-08-30_audit_total_aplikasi_finance_dan_roadmap_pengembangan.md`,
  `docs/2026-08-28_roadmap_komersialisasi_finance_dan_lisensi.md`,
  `docs/2026-09-03_progress_roadmap_user_finance.md`,
  `tools/tests/roadmap_consistency_smoke.php`,
  `tools/tests/finance_quality_gate.php`,
  `tools/tests/finance_quality_gate_contract_smoke.php`, dan execution log ini.
- Perubahan utama: `_30` sekarang menjadi sumber status teknis tunggal dengan
  register ID permanen, status fase A0–A5, checklist rollout UI sembilan
  gelombang, dan register delapan SQL staging/server utama. `_28` hanya memuat
  C0–C5 komersialisasi serta membaca satu gerbang handoff dari `_30`. A3
  dikoreksi menjadi `PARTIAL`: A3.1 dan fondasi A3.2 lulus, tetapi rollout 8.3
  serta visual UAT belum selesai. A2 menjadi `OPERATIONAL_PENDING`, A4 menjadi
  `TOOLING_PASS`, sedangkan A0, A1, dan A5 tetap `PARTIAL`.
- Keputusan controller: tidak dibuat. Audit memuat path, deployment, database,
  security, dan backlog internal; menampilkannya melalui route web menambah
  surface akses tanpa manfaat operasional. Markdown terversi dan gate otomatis
  menjadi media kendali saat ini.
- Temuan tambahan auditor: `AUD-A1-SYS-01` mencatat halaman System Tools yang
  masih perlu memisahkan izin baca sensitif, meredaksi path/metadata backup,
  dan membatasi field konfigurasi. Temuan dicatat, belum diperbaiki pada batch
  dokumentasi ini.
- Database/SQL: tidak ada schema, data, atau SQL yang diubah/dijalankan pada
  batch ini.
- Validasi: PHP lint 3/3 lulus; roadmap consistency 22/22 dengan 38 register
  item dan 8 SQL; quality-gate contract 27/27; quality profile `parallel`
  lulus required 36/36 dan development 4/4. Release serta preflight tetap
  diblokir secara benar oleh credential database langsung pada staging. Diff
  whitespace lulus dan empat hash source POS/APK yang dilindungi tetap identik.
- Hasil review auditor: PASS tanpa temuan blocking. Seluruh P0/P1/P2, temuan
  System Tools, sembilan gelombang UI, dan delapan SQL root terpetakan; status
  A2/A3/A4 tidak menyesatkan, batas `_30`/`_28` konsisten, policy SQL aman,
  dan keputusan tidak membuat controller dinilai tepat. PASS hanya berlaku
  untuk normalisasi dokumentasi/gate, bukan klaim UAT atau release produksi.
- Risiko sisa: control board tetap perlu diperbarui bersamaan dengan setiap
  batch; test mencegah struktur/ID/SQL register hilang tetapi tidak dapat
  menggantikan penilaian acceptance atau UAT manusia. Recovery Git juga masih
  menjadi blocker packaging.
- Batch berikutnya: tutup temuan P0 `AUD-A1-SYS-01` dalam batch kecil, kemudian
  lanjutkan A5.12 baseline clean-install. Rollout A3.2 dikerjakan per rumpun
  tanpa menyentuh kontrak APK yang dilindungi.

## Batch 130 — Dashboard roadmap internal yang user-friendly

- Waktu: 2026-09-05 07:28 WIB.
- Prioritas: menampilkan control board audit dan roadmap komersialisasi sebagai
  halaman web yang mudah dibaca tanpa membuat sumber status ketiga.
- Ringkasan auditor/fixer: auditor menetapkan fixed-document parser, output
  ter-escape, GET-only, no-store, dan boundary internal. Fixer membuat reader,
  controller, view, filter, serta release exclusion. Review pertama membuka
  readiness hardcode dan guard non-production terlalu luas; fixer mengganti
  readiness menjadi turunan 12 fase dan membuat allowlist development plus
  marker internal. Review akhir auditor menyatakan PASS.
- File berubah/dibuat: `application/controllers/Audit.php`,
  `application/libraries/AuditRoadmapReader.php`,
  `application/views/audit/roadmap.php`,
  `.codex/internal_audit_dashboard.enabled`, tabel status C0–C5 pada roadmap
  `_28`, `tools/release/package_policy.json`,
  `tools/release/ReleasePackagePolicy.php`, contract artefak, smoke dashboard,
  wiring quality gate, roadmap `_30`, progress report, dan execution log ini.
- Konfigurasi staging di luar repository: vhost Nginx
  `/www/server/panel/vhost/nginx/pos.namuacoffee.com.conf` menolak akses statis
  ke `/docs/` dan `/.codex/`; syntax test dan reload Nginx lulus.
- Perubahan utama: `/audit/roadmap` membaca hanya tabel kanonis A0–A5, register
  temuan, rollout UI 8.3, register SQL, dan C0–C5. Halaman menampilkan cards,
  badge tiga dimensi, alasan blocker, tabel responsive, serta pencarian/filter
  temuan. Tidak ada edit, POST, export, AJAX, path input, raw Markdown, atau
  akses model bisnis.
- Boundary akses: hanya aktif bila `ENVIRONMENT=development` dan marker fixed
  valid; environment lain atau marker invalid menghasilkan 404 sebelum auth.
  Pada staging, hanya superadmin yang dapat membuka. Controller, reader, view,
  marker, `_NOTE`, dan docs bertanggal dikecualikan dari artefak customer.
- Database/SQL: tidak ada schema, data, migration, menu, atau SQL yang dibuat
  maupun dijalankan. URL memakai routing default CodeIgniter; `routes.php`
  tidak disentuh.
- Validasi: PHP lint lulus; dashboard smoke 27/27; artifact contract 10/10;
  roadmap consistency 22/22; quality-gate contract 27/27; Composer valid dengan
  warning deprecation dari Composer lama. Quality profile `parallel` lulus
  required 37/37 dan development 4/4. HTTP anonymous: dashboard 307 ke login,
  raw docs 404, marker 404, dan config `.codex` 404. Nginx config test lulus.
- Hasil review auditor: PASS tanpa blocker keamanan, logika, atau UX pada scope
  batch internal ini. Readiness hanya menjadi `READY` bila seluruh A0–A5 dan
  C0–C5 berstatus `DONE`.
- Risiko sisa: visual UAT dengan sesi superadmin pada desktop/mobile belum
  dilakukan. Aturan blok `/docs/` dan `/.codex/` harus diterapkan tersendiri
  pada vhost server internal lain; halaman sengaja tidak tersedia pada build
  production/customer.
- Batch berikutnya: kembali ke temuan P0 `AUD-A1-SYS-01`, lalu A5.12. Rollout
  UI A3.2 tetap berjalan per rumpun dan dashboard tidak mengubah status fase.

## Batch 131 — Dashboard roadmap bertab

- Waktu: 2026-09-05 07:51 WIB.
- Prioritas: mengurangi halaman roadmap yang terlalu panjang dan membuat tiap
  jenis pekerjaan lebih mudah dipahami tanpa membentuk sumber status baru.
- Ringkasan auditor/fixer: fixer membagi dashboard menjadi lima tab dan
  mempertahankan filter temuan. Auditor memeriksa aksesibilitas, state awal,
  hash URL, tampilan mobile, dan boundary read-only; review akhir `PASS`.
- File berubah: `application/views/audit/roadmap.php` dan
  `tools/tests/audit_roadmap_dashboard_smoke.php`.
- Perubahan utama: tab `Ringkasan`, `Temuan`, `UI 8.3`, `SQL`, dan
  `Komersialisasi`; hanya satu panel aktif saat awal, tab disimpan di URL hash,
  alias anchor lama tetap bekerja, dan navigasi mobile dapat digeser horizontal.
- Validasi: PHP lint lulus; dashboard smoke 30/30; diff whitespace lulus.
- Hasil review auditor: `PASS`; tidak ada form, writer, AJAX, export, atau
  sumber data baru.
- Risiko sisa: visual UAT sesi superadmin pada beberapa ukuran layar tetap
  diperlukan dan status roadmap tetap berasal dari dua Markdown induk.
- Batch berikutnya: fondasi Telegram Bot internal sebelum kembali ke backlog
  teknis berikutnya.

## Batch 132 — Fondasi Telegram Bot dan deployment migration

- Waktu: 2026-09-05 08:30 WIB.
- Prioritas: menyediakan kanal Telegram internal yang setara dengan kebutuhan
  laporan inti WhatsApp, tetapi memakai Bot API langsung dan tidak bergantung
  pada service Node WhatsApp.
- Ringkasan auditor/fixer: auditor membatasi MVP pada group/channel allowlist,
  command `/menu`, `/omzet`, `/belanja`, jadwal, queue, log, dan setting.
  Fixer membangun modul; review pertama memperbaiki sumber refund omzet,
  webhook sinkron, status `UNKNOWN`, TLS/response cap, dan kontrak test.
  Saat deployment, auditor menemukan validasi nama database runner terlalu
  longgar; fixer menutupnya dan review akhir menyatakan `PASS`.
- File berubah/dibuat: controller `Telegram` dan `Telegram_webhook`, model
  `Telegram_model`, library `TelegramBotClient` dan `TelegramReportService`,
  empat view `application/views/telegram/`, migration
  `sql/2026-09-05a_telegram_bot_foundation.sql`, katalog/runner migration,
  smoke Telegram/A5/quality gate, dua roadmap induk, progress report,
  deployment secret contract, dan execution log ini.
- Perubahan utama: target group/channel internal, jadwal omzet/belanja, queue
  idempoten dengan lease, webhook deduplikasi dan immediate ACK, worker CLI,
  log delivery, resolusi manual `UNKNOWN` dengan alasan/audit/resend baru,
  env-only token/secret, sidebar database, serta empat page permission yang
  awalnya hanya diberikan penuh kepada `SUPERADMIN`. Runner, fingerprint,
  probe FK, dan disposable restore sekarang selalu memilih database target
  lewat file nama database privat; MariaDB tidak lagi diandalkan membaca
  `database=` dari defaults file.
- Database/SQL: backup privat dibuat di
  `/var/lib/finance-a5-runtime/backups/pre_telegram_20260905_ITFKDL.sql.gz`
  dan checksum valid. Diagnosis awal menjalankan SQL idempoten langsung karena
  runner belum memilih database. Setelah runner diperbaiki, migration resmi
  mencatat `applied=1/skipped=1`; replay `applied=0/skipped=2`. Staging berisi
  6 tabel, 13 FK, 34 index, 4 page unik, 5 menu unik, 4 grant penuh
  `SUPERADMIN`, 0 grant role lain, dan 2 ledger checksum exact.
- Validasi: lint PHP lulus; Telegram smoke 50/50; quality-gate contract 27/27;
  A5 catalog 28/28; A5 executor 35/35; A5 fingerprint/FK/restore contracts
  lulus; migration validate/plan/dry-run/apply/replay lulus. Probe staging
  nyata menghasilkan fingerprint kandidat 4/4, FK `already_present`, detail
  FK tanpa alasan, dan named-FK tanpa alasan. CLI worker menghasilkan 0
  enqueue/0 proses pada target kosong;
  anonymous dashboard redirect login, webhook GET 405, dan POST tanpa secret
  403. Quality profile `parallel` lulus required 38/38 dan development 4/4;
  release/preflight tetap diblokir secara benar oleh credential database
  langsung staging. Empat hash source POS/APK tetap identik.
- Hasil review auditor: `PASS` untuk kode, migration, dan staging apply. Omzet
  memakai receipt PAID FINAL/DEPOSIT dikurangi refund POSTED; webhook hanya
  enqueue; pengiriman timeout tidak di-retry otomatis sebelum operator
  menyelesaikan status `UNKNOWN`.
- Risiko sisa: token bot, webhook secret/registrasi HTTPS, cron, target chat,
  serta UAT kirim nyata belum diprovision. MVP belum mencakup broadcast member,
  template/media, atau parity penuh seluruh fitur WhatsApp.
- Batch berikutnya: provision dan UAT Telegram saat credential bot tersedia,
  kemudian kembali ke `AUD-A1-SYS-01` atau A5.12 sesuai control board.

## Batch 133 — Panduan setup Telegram di aplikasi

- Waktu: 2026-09-05 09:20 WIB.
- Prioritas: memberi pengguna dan administrator urutan setup Telegram yang
  jelas dari pembuatan bot sampai webhook, target, jadwal, tes, dan diagnosis,
  tanpa menaruh credential dalam aplikasi atau dokumentasi.
- Ringkasan auditor/fixer: auditor memilih page registry `tg.guide` view-only
  dan migration seed baru agar sidebar tetap mempunyai satu sumber kebenaran.
  Fixer membuat panduan tujuh tab dan kontrak test. Review pertama menolak
  contoh `curl` yang dapat mendorong secret ke process list serta CTA Settings
  yang dapat berakhir 403; fixer mengganti contoh dengan checklist operasi
  non-eksekusi dan membuat CTA mengikuti permission. Review akhir `PASS`.
- File berubah/dibuat: `application/controllers/Telegram.php`, view
  `application/views/telegram/guide.php` dan `settings.php`, migration
  `sql/2026-09-05b_telegram_setup_guide.sql`, katalog migration, runner restore
  drill, contract Telegram/A5/roadmap, dua roadmap induk, progress report,
  deployment secret contract, dan execution log ini.
- Perubahan utama: `/telegram/guide` mempunyai tab Mulai, Buat Bot, Server,
  Chat ID, Webhook, Target & Jadwal, serta Tes & Masalah. Isi menjelaskan
  BotFather, Privacy Mode, environment FPM/CLI, `getUpdates`, operasi
  `getMe`–`setWebhook`–`getWebhookInfo`, allowlist, cron, status `UNKNOWN`, dan
  peringatan penggunaan grup internal tepercaya. Halaman tidak mempunyai form,
  writer, AJAX, custom script, pembacaan environment, atau nilai credential.
- Database/SQL: backup privat
  `/var/lib/finance-a5-runtime/backups/pre_telegram_guide_20260905_3CxfHA.sql.gz`
  dibuat dengan mode 0600 dan checksum valid. Preflight page/menu/ledger 0/0/0.
  Runner menghasilkan `applied=1/skipped=2`, lalu replay
  `applied=0/skipped=3`. Staging mempunyai satu page aktif `tg.guide`, satu menu
  child `grp.telegram`, satu grant SUPERADMIN view-only, nol grant role lain,
  dan ledger checksum exact.
- Validasi: PHP lint lulus; Telegram smoke 118/118; A5 catalog 29/29;
  executor 35/35; legacy inventory 17/17; fingerprint 43/43; disposable
  restore contract 50/50; roadmap consistency 22/22 dengan 10 SQL; migration
  validate/plan/dry-run/apply/replay dan backup checksum lulus. Quality profile
  `parallel` lulus required 38/38 dan development 4/4; release/preflight tetap
  diblokir secara benar oleh credential database langsung staging.
- Hasil review auditor: `PASS` untuk staging. Registry, dependency, checksum,
  escaping, permission view-only, conditional CTA, serta larangan secret pada
  UI/command sudah sesuai.
- Risiko sisa: UAT sesi SUPERADMIN dan role tanpa izin belum dijalankan di
  browser nyata. Token/webhook/cron/target belum diprovision; setiap anggota
  grup allowlist masih dapat meminta laporan sampai otorisasi per pengirim
  ditambahkan.
- Batch berikutnya: provision dan UAT Telegram bila credential tersedia;
  security berikutnya menambah otorisasi pengirim sebelum memakai grup yang
  tidak sepenuhnya tepercaya. Setelah itu kembali ke `AUD-A1-SYS-01`/A5.12.

## Batch 134 — Setup Assistant Telegram untuk pengguna non-programmer

- Waktu: 2026-09-05 10:04 WIB.
- Prioritas: mengubah onboarding Telegram dari panduan teknis menjadi alur
  pengguna yang jelas, sambil memisahkan credential dan tugas deployment yang
  memang tidak aman dikelola melalui UI biasa.
- Ringkasan auditor/fixer: auditor menetapkan token, webhook secret, URL HTTPS
  kanonis, environment PHP-FPM/CLI, dan cron sebagai tugas admin server.
  Verifikasi identitas bot, discovery target, simpan target, test kirim, master
  switch, pemasangan/pemeriksaan webhook, serta jadwal dipindahkan ke UI. Fixer
  menerapkan endpoint setup dan dua halaman; review integrasi menemukan nama
  field kandidat yang sempat berbeda lalu diselaraskan ke opaque
  `candidate_key`. Review pertama menyetujui migration staging; review akhir
  menemukan token bot lama masih tertulis di `_NOTE.md`, lalu token tersebut
  langsung direduksi tanpa ditampilkan. Rotasi melalui BotFather tetap harus
  dilakukan pemilik karena nilai yang pernah terekspos tidak boleh dipakai lagi.
- File berubah/dibuat: `application/controllers/Telegram.php`,
  `Telegram_webhook.php`, `application/libraries/TelegramBotClient.php`,
  `application/models/Telegram_model.php`, view Telegram `settings.php` dan
  `guide.php`, `sql/2026-09-05c_telegram_safe_activation_default.sql`, katalog
  dan disposable restore A5, contract test Telegram/A5, dua roadmap induk,
  progress report, `docs/deployment_secret_contract.md`, `docs/_NOTE.md`, dan
  log ini.
- Perubahan utama: Pengaturan Telegram menjadi Setup Assistant dengan tab
  pengguna dan admin server. Panduan menjadi tiga tab dan menjelaskan langkah
  BotFather sampai jadwal. Client hanya memakai tiga environment
  `FINANCE_TELEGRAM_*`; URL webhook tidak berasal dari host/request. Endpoint
  setup memakai RBAC, POST, scoped CSRF, PRG, discovery session 10 menit,
  kandidat satu kali pakai, dan hasil Telegram yang sudah disanitasi. Master
  switch ditolak backend bila konfigurasi/target belum siap. Webhook valid saat
  switch OFF di-ACK 200 tanpa menjalankan command.
- Database/SQL: sebelum perubahan dibuat backup privat
  `/var/lib/finance-a5-runtime/backups/pre_telegram_setup_assistant_20260905_octMcQ.sql.gz`
  mode 0600, size 21.062.486 byte, SHA256
  `2b56c6e7160f6f28875d4a8fb31bfe32169e4f7f8c6348c5502d348e27b669f1`.
  Runner menghasilkan `applied=1/skipped=3`, replay
  `applied=0/skipped=4`; `telegram.enabled=0` dengan status `UNTOUCHED`, ledger
  berjumlah empat dan checksum `09-05c` exact.
- Validasi: PHP lint seluruh file PHP batch lulus; Composer validate lulus
  dengan deprecation tool lama; Telegram smoke 102/102; A5 catalog 30/30,
  executor 35/35, legacy 17/17, fingerprint 43/43, disposable restore 50/50;
  migration validate/plan/apply/replay dan roadmap consistency 22/22 lulus.
  Quality gate `parallel` lulus required 38/38 dan development 4/4. Anonymous
  Settings/Guide redirect 307 ke login dan webhook tanpa secret ditolak 403.
  Hash empat file POS/APK yang dilindungi tetap identik.
- Hasil review auditor: migration aman diterapkan. Karena migration `09-05a`
  yang sudah immutable dahulu menanam switch ON, rollout wajib memastikan
  ledger `09-05c` sudah tercatat; kondisi ini sudah dipenuhi pada staging.
  Token mentah pada `_NOTE.md` telah direduksi dan scan ulang file teks tidak
  menemukan pola token lain. Angka gate lama pada roadmap juga diselaraskan
  menjadi required 38/38 dan development 4/4.
- Risiko sisa: tiga environment dan cron belum diprovision, sehingga uji bot
  nyata belum dapat dilakukan. Token yang pernah terekspos wajib di-revoke dan
  dibuat ulang melalui BotFather sebelum diprovision. Setiap anggota grup allowlist masih dapat
  meminta laporan; gunakan hanya grup internal tepercaya sampai otorisasi per
  pengirim tersedia. Release/preflight tetap diblokir keputusan credential DB
  langsung staging, bukan oleh batch Telegram.
- Batch berikutnya: pemilik membuat bot dan menyerahkan token secara aman;
  admin server memprovision tiga environment serta cron; pemilik menyelesaikan
  Setup Assistant dan UAT nyata. Setelah itu kembali ke `AUD-A1-SYS-01`/A5.12.

## Batch 135 — Provisioning nyata Telegram staging (parsial)

- Waktu: 2026-09-05 10:37–10:48 WIB.
- Prioritas: membuat panduan benar-benar implementatif bagi admin aaPanel,
  memprovision bot nyata tanpa menyimpan credential di Git/database, dan
  menyiapkan notifikasi penyelesaian Codex.
- Ringkasan fixer tunggal: token ditempatkan di file root-only di luar webroot;
  webhook secret dibuat acak di server; URL kanonis ditetapkan ke endpoint HTTPS
  staging; PHP-FPM dan cron memakai environment yang sama. Panduan UI diperjelas
  dengan lokasi file dan perintah aaPanel yang langsung dapat diikuti.
- File aplikasi berubah: `application/views/telegram/guide.php`,
  `docs/deployment_secret_contract.md`, dua roadmap induk, progress report, dan
  execution log. Konfigurasi host di luar repo: `/var/lib/finance-telegram/`,
  `/etc/init.d/php-fpm-81`, `/www/server/php/81/etc/php-fpm.conf`,
  `/usr/local/sbin/finance-telegram-run-due`, `/etc/cron.d/finance-telegram`,
  `/usr/local/sbin/finance-codex-notify`, dan user-level Codex config.
- Perubahan utama: file credential mode 0600, folder mode 0700, whitelist tiga
  environment pada pool PHP-FPM, restart penuh PHP 8.1, scheduler setiap menit,
  serta hook `agent-turn-complete` yang hanya mengirim pesan tetap tanpa prompt
  atau jawaban thread.
- Validasi: syntax PHP-FPM lulus, restart/status PHP-FPM lulus, probe lokal
  melalui Nginx/PHP-FPM menghasilkan `READY`, lint notification script lulus,
  cron wrapper berjalan dan secara benar melaporkan modul masih OFF. Bot API
  memverifikasi bot nyata sebagai `@cacacia_bot` dan webhook belum terpasang.
- Hasil review: boundary credential dan scheduler `PASS`; token tidak ditaruh
  di source, database, dokumentasi, maupun argumen proses. Penyelesaian target
  dan UAT `BLOCKED` karena API Telegram mengembalikan HTTP 409: ada consumer
  lain yang sedang melakukan `getUpdates`.
- Risiko sisa: target grup Namua, test kirim, master switch, webhook, dan hook
  Codex belum dapat diuji end-to-end sampai long-poll lain dihentikan dan
  `/menu` dikirim ulang. Token yang pernah dibagikan melalui chat harus di-revoke
  setelah UAT dan diganti hanya pada file privat.
- Batch berikutnya: selesaikan UAT Telegram setelah konflik hilang; pekerjaan
  kode dilanjutkan langsung ke `AUD-A1-SYS-01` tanpa menunggu.

## Batch 136 — Baca sensitif System Tools

- Waktu: 2026-09-05 10:42–10:48 WIB.
- Prioritas: menutup P0 `AUD-A1-SYS-01` tanpa memperluas controller atau
  menambah migration/permission baru.
- Ringkasan fixer tunggal: hak View dipertahankan untuk ringkasan umum; hak
  Export yang sudah tersedia dipakai sebagai izin baca sensitif terpisah.
  Teknisi yang melakukan writer wajib mempunyai Edit dan Export sekaligus.
- File berubah/dibuat: `application/controllers/System_tools.php`, view
  `application/views/system/dbtools.php`, `settings.php`, dan
  `dbtools_limited.php`; test `system_tools_mutation_csrf_smoke.php` dan
  `system_tools_sensitive_read_smoke.php`; roadmap/progress/log.
- Perubahan utama: user View-only tidak menerima root path, nama/tanggal/ukuran
  dump, log, detail replication/failover, atau konfigurasi. Full view hanya
  menerima whitelist key yang dikenal dan tidak pernah menerima password
  tersimpan. Endpoint status/detail memerlukan View+Export. Test koneksi DB
  berubah dari query URL menjadi POST+CSRF, memvalidasi parameter, dan tidak
  mengirim exception koneksi mentah ke browser.
- Validasi: lint seluruh PHP batch lulus; sensitive-read contract 18/18,
  mutation CSRF regression 74/74, roadmap consistency 22/22, Telegram
  regression 102/102, dan quality profile `parallel` required 38/38 serta
  development 4/4 lulus. Release/preflight tetap diblokir hanya oleh credential
  database langsung staging. Empat hash source POS/APK tetap identik.
- Hasil review: `PASS`; acceptance pemisahan izin, whitelist, redaksi, dan
  negative test terpenuhi. Tidak ada SQL baru dan tidak ada perubahan POS/APK.
- Risiko sisa: hak Export harus diberikan hanya kepada teknisi tepercaya;
  failover/replication nyata tetap membutuhkan prosedur serta UAT server.
- Batch berikutnya: A5.12 baseline clean-install schema/seed kanonis, sambil
  menyelesaikan UAT Telegram segera setelah konflik long-poll hilang.

## Batch 137 — Aktivasi Telegram dan notifikasi Codex ke Namua

- Waktu: 2026-09-05 11:09–11:18 WIB.
- Prioritas: menyelesaikan provisioning nyata Telegram staging setelah pemilik
  merotasi token secara mandiri pada file privat.
- Ringkasan fixer tunggal: token baru divalidasi tanpa dicetak; konflik
  `getUpdates` sudah hilang. Pesan grup menemukan target negatif bertipe GROUP
  dengan judul exact Namua. Target disimpan aktif, master switch dinyalakan,
  Chat ID notifikasi Codex disimpan root-only, dan webhook dipasang.
- Perubahan source: hanya pembaruan status pada dua roadmap induk, progress
  report, dan execution log. Tidak ada kode POS/APK atau SQL migration baru.
  Perubahan runtime normal terjadi pada `tg_target`, `tg_setting`, queue/log,
  `/var/lib/finance-telegram/codex_chat_id`, dan Telegram Bot API.
- Validasi: identitas bot `@cacacia_bot` valid; pesan koneksi langsung berstatus
  `SENT` HTTP 200 dengan message ID 537; hook Codex mencatat `notification sent`;
  webhook configured dan URL match; scheduler menghasilkan `processed=1`;
  queue UAT berstatus `SENT`, attempt 1, message ID 539, dan tepat satu delivery
  log. Pemeriksaan webhook akhir mempunyai pending update 0.
- Hasil review: `PASS` untuk outbound, queue, audit log, scheduler, webhook,
  serta notifikasi Codex. Token hasil rotasi tidak dimasukkan ke chat, source,
  database, dokumentasi, atau argumen proses.
- Risiko sisa: command inbound `/menu`, `/omzet`, dan `/belanja` belum diuji
  setelah webhook terpasang. Semua anggota target allowlist masih dapat meminta
  laporan; grup harus tetap internal tepercaya sampai allowlist pengirim ada.
- Batch berikutnya: A5.12 baseline clean-install schema/seed kanonis; UAT command
  Telegram dapat dilakukan paralel tanpa menyentuh POS Mobile/APK.

## Batch 138 — Baseline schema clean-install A5.12

- Waktu: 2026-09-05 11:18–11:28 WIB.
- Prioritas: menyediakan baseline schema-only yang dapat dipasang pada database
  kosong tanpa membawa data pelanggan, transaksi, credential, backup, staging,
  atau artefak operasional.
- Ringkasan fixer tunggal: metadata schema staging dipetakan menjadi 302 tabel;
  20 tabel backup/staging/temporary/history dikeluarkan sehingga baseline
  kanonis berisi 282 tabel. Guard dan test kontrak ditambahkan agar isi serta
  checksum baseline tidak berubah tanpa review.
- File dibuat/berubah: `sql/baseline/2026-09-05_clean_install_schema.sql`,
  `tools/db/clean_install_baseline_policy.json`,
  `tools/db/clean_install_baseline_guard.php`,
  `tools/tests/a5_clean_install_baseline_guard_smoke.php`, quality gate dan test
  kontraknya, dua roadmap induk, progress report, dan execution log.
- Perubahan utama: baseline hanya memuat DDL, membuang nilai runtime
  `AUTO_INCREMENT`, mengosongkan default token lama, membungkus pemulihan
  foreign-key check, serta melarang DML, user/grant, path absolut, default
  secret tidak kosong, dan tabel operasional. SHA-256 baseline:
  `dcea97a6cbaba13e404aab88122d41bfb79321ca788045f1c5e6f2401d843a0c`.
- Validasi: lint PHP lulus; guard baseline lulus; smoke baseline 12/12 dan
  kontrak quality gate 27/27. Drill database disposable berhasil mengimpor 282
  tabel, menjalankan 4 migration terdaftar, menghasilkan 0 row pada
  `auth_user`, `pos_order`, `crm_member`, dan `org_employee`, mempertahankan
  Telegram OFF, lalu menghapus database uji tanpa sisa. Quality gate required
  39/39 dan development 4/4 lulus.
- Hasil review: `PASS` untuk sub-batch baseline schema-only. Baseline layak
  dipakai sebagai titik awal instalasi baru, bukan untuk upgrade database lama.
- Risiko sisa: A5.12 secara keseluruhan belum selesai karena reference seed
  netral-pelanggan dan mekanisme bootstrap pemilik pertama belum diklasifikasi.
  Tujuh SQL legacy root juga masih berstatus non-deployable. Release/preflight
  tetap diblokir oleh credential DB langsung staging yang sudah diketahui.
- Batch berikutnya: lanjutkan A5.12 dengan klasifikasi seed minimal dan bootstrap
  pemilik pertama yang aman. Tidak ada file POS Mobile/APK yang disentuh.

## Batch 139 — Ringkasan hasil pada notifikasi Codex Telegram

- Waktu: 2026-09-05 11:30–11:38 WIB.
- Prioritas: membuat notifikasi penyelesaian ke Namua memberi informasi hasil,
  bukan hanya penanda bahwa tugas selesai.
- Ringkasan fixer tunggal: hook membaca hanya `last-assistant-message`; prompt
  pengguna dan output tool sengaja tidak digunakan. Teks diringkas dengan batas
  18 baris/2.400 karakter serta penyaringan blok kode, tautan, token Telegram,
  API key, password, dan secret.
- File dibuat/berubah: `tools/telegram/codex_notify.php`,
  `tools/tests/codex_telegram_notify_summary_smoke.php`, wrapper host
  `/usr/local/sbin/finance-codex-notify`, quality gate, deployment secret
  contract, roadmap audit, progress report, dan execution log.
- Validasi: lint tiga entrypoint PHP lulus; smoke redaksi/ringkasan 9/9;
  quality gate parallel required 40/40 dan development 4/4 lulus; scan
  repository menemukan 0 file yang memuat pola token Telegram. Wrapper tetap
  `root:root` mode 0700. Release/preflight masih diblokir hanya oleh credential
  database langsung staging yang sudah diketahui.
- Hasil review: `PASS`; laporan pengguna dapat dibaca langsung dari Telegram
  tanpa meneruskan prompt maupun data mentah proses.
- Risiko sisa: penyaringan berbasis pola bukan pengganti disiplin untuk tidak
  menulis rahasia pada jawaban akhir. Grup Namua tetap harus dibatasi sebagai
  grup internal tepercaya.
- Batch berikutnya: kembali ke klasifikasi seed dan bootstrap owner A5.12.
  Tidak ada file POS Mobile/APK atau schema database yang disentuh.

## Batch 140 — A5.12 seed clean-install dan bootstrap owner

- Waktu: 2026-09-05 11:39–11:57 WIB.
- Prioritas: menyelesaikan A5.12 agar database kosong tidak hanya mempunyai
  tabel, tetapi juga navigasi produk dan akun pemilik pertama yang aman.
- Ringkasan fixer tunggal: seed staging diklasifikasikan per tabel/kolom. Hanya
  `sys_matrix_group`, page/menu/alias, satu role global SUPERADMIN, dan
  permission SUPERADMIN turunan yang diterima. Nama usaha pada Menu Book
  dinormalisasi; user, pegawai, role operasional, transaksi, saldo, stok, lot,
  target Telegram, queue, log, dan credential ditolak.
- File dibuat/berubah: `sql/2026-09-05d_a5_clean_install_reference_seed.sql`,
  `tools/db/build_clean_install_reference_seed.php`, policy/guard baseline,
  migration catalog, `tools/db/bootstrap_first_owner.php`, dua smoke A5.12,
  quality gate/contract, dua roadmap induk, progress report, dan execution log.
- Perubahan utama: migration `09-05d` hanya masuk policy `clean_install` dan
  otomatis dilewati policy `upgrade`. Bootstrap menerima username/email/password
  hanya dari JSON privat mode 0600 di luar repository, mengunci proses, menolak
  bila user/assignment sudah ada, membuat hash bcrypt cost 12, lalu memberikan
  hanya role SUPERADMIN. Percobaan bootstrap kedua selalu ditolak.
- Validasi: guard schema/seed 18/18 dan kontrak bootstrap 12/12 lulus. Drill
  disposable mengimpor 282 tabel, menjalankan lima migration, menghasilkan
  20 matrix group, 206 page, 241 menu, 10 alias, 1 role, 206 permission, 1
  owner, dan 1 assignment. Verifikasi password benar, role SUPERADMIN exact,
  percobaan kedua ditolak, serta database dan file temporer tersisa 0/0.
  Quality gate parallel required 41/41 dan development 4/4 lulus; release dan
  preflight tetap diblokir hanya oleh credential DB langsung staging.
- Hasil review: `PASS`; kegagalan awal akibat urutan ID Telegram ditemukan oleh
  drill dan diperbaiki menjadi registry → seed → migration Telegram. Upgrade
  tetap tidak menjalankan seed sehingga konfigurasi customer lama tidak ditimpa.
- Risiko sisa: SQL `09-05d` tidak boleh dijalankan manual pada server yang sudah
  berisi data. Tujuh SQL legacy masih memerlukan disposition upgrade; rollback,
  runtime matrix, retention, dan signature A5 tetap terbuka.
- Batch berikutnya: A5.13 health check pascainstalasi dan drill rollback upgrade.
  Tidak ada file POS Mobile/APK atau data staging aktif yang diubah.

## Batch 141 — A5.13 health check pascainstalasi dan rollback upgrade

- Waktu: 2026-09-05 12:37–13:06 WIB.
- Prioritas: memastikan hasil instalasi/upgrade dapat diverifikasi terhadap
  artefak rilis dan database dapat dikembalikan utuh bila health check gagal.
- Ringkasan fixer tunggal: health checker CLI mengikat file rilis ke
  `RELEASE-MANIFEST.json`, memvalidasi baseline dan katalog, lalu memeriksa 25
  tabel wajib, ledger migration exact, role/owner SUPERADMIN, reference seed
  clean-install, dan default Telegram OFF. Upgrade tidak menimpa seed customer.
  Drill disposable memulihkan backup, menerapkan empat migration upgrade,
  meluluskan health check, menyuntik canary gagal, memastikan kegagalan
  terdeteksi, lalu memulihkan backup yang sama dan membandingkan kembali schema,
  ledger, serta digest seed.
- File dibuat/berubah: `tools/db/post_install_health_check.php`,
  `tools/db/disposable_upgrade_rollback_drill.php`, dua smoke A5.13,
  quality gate/contract, dua roadmap induk, laporan progress, dan execution log.
  Tidak ada SQL baru dan file POS Mobile/APK tidak disentuh.
- Perubahan utama: pemeriksaan database memakai query read-only satu proses per
  probe agar error MariaDB gagal cepat dan output client selalu disamarkan.
  Manifest lama A5.11 memang ditolak setelah katalog A5.12 berubah; bundle
  runtime `a513_source_20260905` dibangun ulang dari archive yang sama dengan
  manifest katalog terbaru, tanpa mengubah archive sumber.
- Validasi: lint PHP lulus; health contract 13/13 dan rollback contract 13/13
  lulus. Probe read-only `db_finance` lulus dengan 25 tabel wajib, empat ledger
  migration, dan tiga owner SUPERADMIN aktif. Drill nyata berstatus `ok`:
  upgrade health lulus, canary ditolak dengan `migration_ledger_count`, rollback
  terverifikasi, seed digest sebelum/sesudah identik, source archive tetap,
  cleanup database/user/file sementara 0/0. Evidence privat mode 0600 berada
  di `/var/lib/finance-a5-runtime/evidence/a5_upgrade_rollback_f67b0b68ed090c09.json`.
  Quality gate `parallel` lulus pada required 43/43 dan development 4/4;
  release/preflight tetap hanya gagal pada dua credential DB langsung staging
  yang sudah diketahui.
- Hasil review: `PASS` untuk A5.13. Health check tidak menganggap kekurangan row
  permission SUPERADMIN pada database upgrade sebagai kerusakan karena role itu
  memang bypass; coverage exact tetap diwajibkan pada clean install. Tidak ada
  mutasi pada `db_finance` selama probe.
- Risiko sisa: manifest pada drill adalah release-contract staging, belum
  signed artifact customer penuh. Release nyata tetap diblokir credential DB
  langsung staging. Tujuh SQL legacy, matrix runtime, retention, signature,
  updater customer, serta UAT perangkat masih terbuka.
- Batch berikutnya: A5.14 compatibility matrix runtime PHP/MariaDB/extension,
  Node/Python, dan dependency lock.

## Batch 142 — A5.14 matrix kompatibilitas runtime dan register tahap terlewat

- Waktu: 2026-09-05 13:13–13:22 WIB.
- Prioritas: menutup A5.14 dengan kontrak runtime yang dapat diuji, lalu
  memastikan pekerjaan fase lama yang belum selesai tidak hilang dari roadmap.
- Ringkasan fixer tunggal: metadata PHP lama diselaraskan dengan source aktif;
  matrix machine-readable menetapkan PHP/FPM, MariaDB, Node/npm, Python,
  Composer, extension, dan lock dependency. Pemeriksa mempunyai mode contract
  DB-free dan probe staging. Audit urutan memisahkan pekerjaan benar-benar
  terlewat dari data dan POS/APK yang sengaja ditunda.
- File dibuat/berubah: `composer.json`, `composer.lock`,
  `tools/release/runtime_compatibility.json`,
  `tools/release/runtime_compatibility_check.php`,
  `tools/tests/a5_runtime_compatibility_contract_smoke.php`, quality gate dan
  contract-nya, kontrak runtime, dua roadmap induk, laporan progress, serta log
  ini. Tidak ada SQL, database, controller/model/route/view POS yang diubah.
- Perubahan utama: constraint Composer menjadi `>=8.1 <8.2`; tiga lock wajib
  diverifikasi; npm dependency registry wajib integrity hash dan dependency Git
  wajib commit penuh; Python wajib exact-pin+SHA-256. Staging tier quality gate
  kini memuat probe runtime A5.14.
- Validasi: lint seluruh PHP batch lulus; Composer validate lulus dan lock hash
  disegarkan tanpa perubahan versi paket; contract A5.14 10/10 dan probe
  staging lulus pada PHP CLI/FPM 8.1.32, MariaDB 10.6.23, Node 20.20.2, npm
  10.8.2, Python 3.10.12, Composer 2.0.14. Quality-gate contract 27/27 lulus.
- Hasil review: `PASS` untuk baseline A5.14 staging. Warning tidak disembunyikan:
  build PHP 8.1 memakai `--disable-fileinfo`, sehingga kemampuan WhatsApp file
  belum siap; Composer 2.0.14 juga perlu dinaikkan untuk build customer.
- Risiko sisa: matrix ini membuktikan staging, bukan dukungan semua runtime
  customer. Release tetap diblokir credential langsung, qualification runtime
  yang masih mendapat security maintenance, `fileinfo`, retention, signature,
  SQL legacy/updater, recovery Git, dan UAT perangkat.
- Tahap terlewat/rencana: register kanonis 0.6 pada `_30` mencatat A0, sisa A1,
  P2 A2, rollout UI A3.2, UAT A4, Telegram inbound, serta SQL legacy/updater.
  Urutan berikutnya A5.15 → A5.16 → SQL/updater → A0 → sisa A1/A2 → A3.2 →
  UAT. Data historis dan POS/APK tetap ditunda sesuai keputusan pemilik.
- Batch berikutnya: A5.15 retention dan lifecycle. Tidak ada file POS
  Mobile/APK yang disentuh.

## Batch 143 — A5.15 retention dan lifecycle aman

- Waktu: 2026-09-05 13:29–13:40 WIB.
- Prioritas: menghentikan penghapusan backup berbasis umur yang tidak
  terverifikasi dan menyediakan lifecycle runtime yang dapat diaudit.
- Ringkasan fixer tunggal: policy menetapkan root runtime privat, umur minimum,
  retain-newest, batas item/run, kelas yang tidak boleh age-delete, dan rule
  database archive-gated. Engine selalu plan dahulu; apply memerlukan prefix
  hash policy, memverifikasi identitas file/checksum, lalu memindahkan ke
  quarantine alih-alih menghapus.
- File dibuat/berubah: `tools/release/retention_policy.json`,
  `tools/release/retention_manager.php`, `tools/db/retention_preflight.php`,
  template logrotate, backup runner Linux/Windows dan contoh environment,
  contract smoke/quality gate, kontrak lifecycle, dua roadmap induk, laporan
  progress, dan execution log.
- Perubahan utama: backup Linux/Windows tidak lagi menjalankan `find -delete`
  atau `forfiles del`; setiap dump memperoleh SHA-256. Default Linux diarahkan
  ke `/var/lib/finance-backup`. Upload, ledger transaksi, jurnal, movement stok,
  payroll, queue aktif, Telegram UNKNOWN, credential, config device, dan state
  WhatsApp tidak pernah menjadi target age-based deletion.
- Validasi: lint PHP dan `bash -n` lulus; contract retention 19/19 lulus pada
  fixture disposable termasuk tamper/symlink/bad-checksum, konfirmasi salah,
  quarantine recoverable, audit 0600, dan cleanup 0. Quality-gate contract
  tetap 27/27; A4 cross-module 35/35 dan quality gate parallel required 45/45
  serta development 4/4 lulus. Dry-run staging menghasilkan kandidat 0,
  invalid 0, deleted 0. Release/preflight tetap hanya ditolak oleh credential
  database langsung staging yang sudah diketahui.
- Bukti database read-only: 887.635 total availability rebuild log; 52.445 row
  sukses non-mismatch berumur >90 hari (1–6 Juni 2026) menjadi kandidat archive;
  mismatch lama 0. Kandidat auth/WA/Telegram menurut batas policy semuanya 0.
- Hasil review: `PASS` untuk baseline teknis A5.15. Backup Telegram lama yang
  belum memiliki companion diberi SHA-256 mode 0600 dan diverifikasi tanpa
  mengubah archive. Root `/var/lib/finance-backup/{dumps,logs}` dibuat root-only
  mode 0700. Tidak ada purge atau pemindahan file staging.
- Risiko sisa: database purge tetap `enabled=false` sampai agregasi/archive,
  batch purge, dan acceptance rollback tersedia. Off-site encryption, schedule
  customer, serta pemindahan log/session service dari source menjadi pekerjaan
  deployment/C3. Quarantine permanen hanya boleh dipurge melalui review lain.
- Batch berikutnya: A5.16 signature dan provenance artefak. Tidak ada SQL,
  mutasi database, atau perubahan POS Mobile/APK.

## Batch 144 — A5.16 signature dan provenance artefak release

- Waktu: 2026-09-05 15:42–15:57 WIB.
- Prioritas: memastikan installer/updater dapat membuktikan penerbit, source,
  manifest, dan policy setiap paket sebelum paket diekstrak atau dijalankan.
- Ringkasan fixer tunggal: tool Ed25519 dipisahkan dari builder agar build host
  tidak memegang private key. Key generator hanya menulis key baru di luar
  repository; signer mengikat artefak, manifest, revision, epoch, migration
  catalog, package policy, dan runtime policy; verifier hanya membutuhkan
  public key tepercaya serta gagal tertutup pada setiap mismatch.
- File dibuat/berubah: `tools/release/artifact_signature.php`,
  `tools/tests/a5_artifact_signature_contract_smoke.php`, policy/test runtime,
  quality gate/contract, `docs/release_signature_provenance.md`, kontrak
  runtime, dua roadmap induk, laporan progress, dan execution log ini.
- Perubahan utama: private key harus raw Ed25519 mode 0600, dimiliki process,
  berada di parent privat di luar source, dan tidak pernah masuk provenance.
  Verifier memeriksa canonical JSON, trusted `key_id`, signature, SHA-256 dan
  ukuran artefak, isi manifest, seluruh checksum file, file type regular,
  source revision immutable, serta tiga digest policy. PHP `sodium` menjadi
  extension wajib pada runtime matrix.
- Validasi: lint seluruh PHP batch lulus. Contract A5.16 lulus 20/20, contract
  runtime A5.14 11/11, runtime smoke 20 check dengan satu warning `fileinfo`,
  quality-gate contract 27/27, roadmap consistency 22/22, artifact builder
  fixture 10/10, source static dan vulnerability scan lulus. Probe matrix
  staging lulus dengan warning lama `fileinfo` dan Composer 2.0.14.
  Quality gate parallel lulus pada required 46/46 dan development 4/4.
- Hasil review: `PASS` untuk kontrak teknis A5.16. Test negatif membuktikan
  penolakan paket berubah/unsigned, provenance berubah, key asing, private key
  berizin longgar atau berada di repo, revision ambigu, policy hilang, checksum
  manifest salah, path traversal, dan symlink. Test memakai key disposable dan
  tidak meninggalkan private key produksi.
- Risiko sisa: artefak customer nyata belum dapat dibangun karena release gate
  tetap menemukan credential database langsung pada `application/config/database.php`
  baris 20–21. Signing key produksi, trust-store installer, release approval,
  updater customer, SBOM, registry, dan recovery Git belum tersedia. A5 tetap
  `PARTIAL` walaupun A5.1–A5.16 baseline teknis sudah lulus.
- Database/SQL/POS: tidak ada SQL baru, query, mutasi database, atau perubahan
  controller/model/route/view POS. Hash empat file integrasi POS/APK tetap
  identik dengan baseline perlindungan.
- Batch berikutnya: `GAP-07`, yaitu disposition tujuh SQL legacy dan kontrak
  jalur updater customer. SQL lama tidak akan direplay blind ke database aktif.

## Batch 145 — GAP-07 disposition SQL legacy dan batas updater

- Waktu: 2026-09-05 16:21–16:55 WIB.
- Prioritas: menutup klasifikasi tujuh SQL legacy tanpa replay blind atau
  pencatatan ledger palsu, lalu menambah pengganti seed WhatsApp yang aman.
- Ringkasan fixer tunggal: bukti inventaris, checksum, clean-install baseline,
  dan fingerprint staging dipakai kembali. Keputusan final adalah 1 `baseline`
  (`08-15b`), 4 `enroll` hanya lewat fingerprint exact (`09-02a`, `09-03a`,
  `09-03b`, `09-04b`), 1 `replace` (`08-17e`), dan 1 `retire` (`09-04a`).
  Jalur otomatis dibatasi ke source-line `finance-managed-v1`; instalasi
  pre-catalog tetap berhenti fail-closed dan memerlukan bridge manual.
- File dibuat/berubah: policy/guard GAP-07, katalog dan policy baseline,
  `sql/2026-09-05e_whatsapp_safe_reference_seed.sql`, kontrak migration/
  restore/rollback/health/Telegram/quality gate, dua roadmap induk, laporan
  progress, dan execution log ini. File POS Mobile/APK tidak disentuh.
- Perubahan utama: migration runner kini memiliki 6 migration managed dan
  policy upgrade memilih 5. Seed `09-05e` hanya memastikan satu
  `REPORT_DEFAULT` dan `wa_session.id=1`; tidak memiliki DDL, UPDATE, DELETE,
  repair transaksi, atau perubahan stok. Guard DB-free mengikat seluruh
  keputusan ke checksum bukti dan menolak replay legacy/adopsi ledger.
- Database staging: fingerprint kandidat kembali lulus 4/4. Backup privat
  `/var/lib/finance-a5-runtime/backups/pre_gap07_20260905_164348_a333c774.sql.gz`
  beserta checksum dan bundle terverifikasi dibuat sebelum apply. Dry-run
  merencanakan 5 migration; apply menghasilkan 1 baru/4 skip dan replay
  menghasilkan 0 baru/5 skip. Postcondition template, session, dan ledger
  masing-masing tepat 1 dengan checksum `09-05e` exact.
- Validasi: lint PHP seluruh file batch lulus; migration catalog 33/33,
  executor 35/35, legacy inventory 17/17, fingerprint 43/43, GAP-07 19/19,
  clean-install guard 18/18, restore contract 50/50, rollback contract 13/13,
  health contract 13/13, Telegram 102 check, quality-gate contract 27/27, dan
  roadmap consistency 22/22 lulus. Quality gate `parallel` lulus required
  47/47 serta development 4/4; release/preflight tetap hanya diblokir dua
  credential database langsung staging yang sudah diketahui.
- Catatan drill: re-run tambahan restore atas dump operasional historis berhenti
  pada fase import (`restore_failed`), tetapi cleanup database/user/file
  disposable terverifikasi. Hasil ini tidak dianggap PASS dan tidak mengubah
  bukti A5.11 sebelumnya; jalur managed-v1 batch ini dibuktikan langsung pada
  staging. Drill dump lama dapat diinvestigasi terpisah tanpa menahan
  disposition SQL.
- Hasil review: `PASS` untuk disposition teknis `GAP-07` dan managed-v1.
  Ketujuh SQL lama tetap berada di daftar non-deployable. Server utama hanya
  menjalankan migration runner policy `upgrade`; file legacy dan seed
  clean-install-only `09-05d` tidak dijalankan manual.
- Risiko sisa: updater customer lintas versi, bridge instalasi pre-catalog,
  installer, signing key produksi, recovery Git, credential/secret produksi,
  database archive activation, dan UAT artefak masih terbuka. A5 tetap
  `PARTIAL`; UAT updater berada di `GAP-05`/C3.
- Batch berikutnya: `GAP-01`, yaitu penutupan credential produksi, rotasi
  secret, recovery Git, dan pemisahan runtime customer tanpa mengganggu POS/APK.

## Batch 146 — GAP-01.1 credential dan runtime keluar dari source

- Waktu: 2026-09-05 17:15–17:46 WIB.
- Prioritas: memisahkan data runtime serta credential database staging dari
  source tanpa menghapus upload, backup, log, output, atau mengubah kontrak
  POS/APK.
- Kondisi awal Git: repository tersedia pada `main` dan HEAD sama dengan
  `origin/main`, tetapi clone hanya mempunyai shallow history satu commit.
  Working state berisi 119 modified, 341 untracked, dan 1.133 deleted. Object
  connectivity lulus; temp pack tak-terpakai berukuran sekitar 2,1 GB tidak
  dihapus atau diolah ulang pada batch ini.
- Implementasi index/runtime: 1.367 entri runtime lama dilepas dari index Git
  memakai `git rm --cached`; file fisik tidak dihapus. Sebelum/sesudah
  terverifikasi tetap 344 file upload, 27 backup/log aktif, 6 generated/tmp,
  dan file credential dengan digest identik. Index kini hanya menyimpan lima
  `.gitkeep` untuk backup, upload, tmp, dan output. `.gitignore` menutup runtime,
  `.env`, local agent/editor state, `__pycache__`, dan bytecode.
- Implementasi credential: konfigurasi aktif lama dipindahkan tanpa mencetak
  nilainya ke `/var/lib/finance-config/database.php`; directory `root:www 0750`
  dan file `root:www 0640`. Source `application/config/database.php` sekarang
  bebas nilai koneksi, memakai fallback file privat hanya untuk
  development/staging, dan tetap memakai `DeploymentConfig` pada production.
  `.user.ini` membuka hanya root privat tersebut. PHP-FPM 8.1 direload.
- File dibuat/berubah: `.gitignore`, `.user.ini`, tiga placeholder runtime,
  `application/config/database.php`, kontrak repository/runtime, quality gate,
  perbaikan environment PHPStan, deployment secret contract, dua roadmap,
  laporan progress, dan execution log. Tidak ada SQL atau mutasi database.
- Validasi aplikasi: lint config lulus; private config lengkap tanpa mencetak
  nilai; koneksi staging melalui loader lulus; halaman web merespons 200 tanpa
  pola error database. Deployment secret contract 39/39, source preflight
  0 finding, backup isolation 32/32, dan runtime source 20 check dengan warning
  lama `fileinfo` lulus.
- Validasi Git/release: repository/runtime boundary lulus 29/29 dengan status
  `SHALLOW_PENDING`; `git fsck --connectivity-only` lulus. Quality profile
  `release` meluluskan required 48/48, development 4/4, deployment 1/1,
  preflight 1/1, security 1/1, dan Printer Agent runtime. Browser runtime
  timeout pada desktop. Bug environment tmp PHPStan diperbaiki dan contract
  15/15 lulus; setelah benar-benar berjalan, static analysis menemukan
  80 error source baru sehingga tidak disamarkan sebagai PASS. Quality profile
  `parallel` final lulus required 48/48, development 4/4, deployment 1/1, dan
  preflight 1/1.
- Hasil review: `PASS` untuk sub-batch pemisahan credential dan runtime index.
  Aplikasi staging tetap aktif dan release tidak lagi diblokir credential
  langsung. `GAP-01` tetap `IN_PROGRESS`, bukan selesai.
- Risiko sisa: 1.367 penghapusan index dan tiga placeholder baru masih staged
  sampai baseline commit dibuat. Full history belum diambil; temp pack tidak
  boleh dihapus sembarang. Credential lama wajib dirotasi saat cutover karena
  pernah berada pada source/history; rotasi akun database tidak dilakukan
  otomatis karena dapat dipakai aplikasi lain. Off-site encryption dan storage
  customer juga belum diaktifkan.
- POS/APK: hash empat file integrasi tetap identik; tidak ada file POS yang
  diubah.
- Batch berikutnya: `GAP-01.2` menetapkan baseline commit/recovery history dan
  rencana rotasi terikat cutover. Ini memerlukan keputusan scope commit dan
  waktu rotasi agar tidak mencampur perubahan pengguna atau memutus aplikasi
  lain.

## Batch 147 — GAP-01.2 cutoff Git lokal dan matriks deployment SQL

- Waktu: 2026-09-05 WIB.
- Prioritas: membuat batas pelacakan yang pasti antara source awal dan seluruh
  perubahan audit, tanpa mengirim perubahan ke remote secara implisit.
- Kondisi awal: HEAD `677078143b16e8151764ee20738b696f86547cb7`
  adalah satu-satunya commit pada clone shallow; perubahan source, test, SQL,
  dokumentasi, dan pelepasan runtime dari index belum mempunyai cutoff commit.
- Audit file lama: 128 dari 139 file source lama yang hilang dari lokasi asal
  terbukti identik dan telah dipindah ke `docs/_old` atau `sql/_old`. Sebelas
  sisanya adalah satu gambar dokumentasi lama dan sepuluh probe/SQL sementara
  yang sudah tidak ada; penghapusan dicatat oleh Git. Tiga file backup handoff
  APK dipertahankan sebagai fixture audit dan ditolak oleh package policy
  melalui suffix `_bak.php`.
- Implementasi: menambahkan dokumen cutoff dan matriks SQL untuk server utama,
  customer existing, serta customer baru; mengunci titik sebelum perubahan,
  nama tag sesudah perubahan, perintah diff A/M/D/R, daftar managed migration,
  daftar legacy `DO_NOT_RUN`, boundary runtime/secret, dan deliverable panduan
  aplikasi final.
- Git: commit lokal dan annotated tag
  `finance-audit-cutoff-2026-09-05` dibuat setelah validasi staged diff. Push
  sengaja tidak dijalankan karena merupakan operasi eksternal terpisah dan
  menunggu perintah eksplisit pemilik.
- Database: tidak ada SQL baru dan tidak ada mutasi database pada batch ini.
  Server existing memakai migration runner policy `upgrade`; fresh customer
  memakai baseline schema-only lalu policy `clean_install`. SQL legacy,
  `sql/_old`, dan repair historis tidak boleh dijalankan massal.
- Risiko sisa: clone tetap shallow; pemulihan full history, push cutoff, rotasi
  secret lama, off-site encryption, dan cutover customer belum selesai.
- Batch berikutnya: setelah owner memerintahkan push, lanjutkan `GAP-01` pada
  strategi full-history/rotasi terjadwal; pekerjaan aplikasi berikutnya tetap
  mengikuti register gap `_30`.

## Batch 148 — GAP-02 inventaris endpoint Master fail-closed

- Waktu: 2026-09-05 WIB.
- Prioritas: melanjutkan A1 non-mobile tanpa menyentuh POS Mobile/APK, dimulai
  dari ketidakpastian inventaris endpoint Master generik pada P0-01.
- Hasil audit: controller aktif mempunyai 12 endpoint publik selain constructor
  dan 36 entity generik. Probe registry staging menemukan mapping `component`
  masih memakai page code lama `master.component.index` yang tidak tersedia;
  page aktifnya adalah `production.component.master.index`. Konfigurasi ke-37,
  `payment-channel`, sengaja ditolak dari controller generik. Writer store,
  update, toggle, stock mode, generate holiday, dan reorder sudah memakai POST
  serta scoped CSRF; read endpoint memakai izin view yang sesuai.
- Implementasi: memperbaiki mapping Component ke page code aktif serta
  menambahkan smoke DB-free yang mengunci daftar public method,
  kebijakan izin/CSRF setiap endpoint, kesetaraan registry entity-page,
  fail-closed unknown/legacy entity, daftar route, dan urutan route spesifik
  sebelum generic catch. Test dimasukkan ke required quality gate.
- File berubah: `application/controllers/Master.php`,
  `tools/tests/master_endpoint_registry_smoke.php`, quality gate beserta
  contract manifest, roadmap `_30`, dan execution log.
- SQL/database: tidak ada SQL baru dan tidak ada mutasi database.
- Risiko sisa: P0-01 belum ditutup karena mutasi master sensitif belum menulis
  audit trail atomik dan negative role UAT belum dilakukan. Formula versioning,
  baseline role/scope, anti-spam public review, serta MFA/step-up tetap item
  GAP-02 terpisah.
- Batch berikutnya: audit trail perubahan Master memakai
  `aud_transaction_log`, dengan before/after teredaksi dan transaksi atomik;
  tetap tanpa perubahan kontrak POS Mobile/APK.

## Batch 149 — GAP-02 audit trail atomik perubahan Master

- Waktu: 2026-09-05 WIB.
- Prioritas: menutup kekurangan audit trail P0-01 setelah endpoint dan registry
  Master dibuktikan pada Batch 148.
- Implementasi: enam jalur mutasi generik—create, update, toggle aktif, perubahan
  stock mode produk, generate kalender libur, dan reorder—sekarang memulai
  transaksi hanya setelah permission serta scoped CSRF lulus, lalu menulis
  `aud_transaction_log` sebelum commit. Gagal schema, insert, status transaksi,
  audit, atau commit menyebabkan perubahan ditolak/rollback.
- Isi audit: module/action, tabel dan ID entity, actor user, source IP, before,
  after, catatan aksi, dan timestamp. Key bertipe password, token, secret,
  credential, authorization, cookie, atau session disaring rekursif; payload
  bersarang dibatasi kedalamannya.
- Generate holiday mencatat snapshot tahun sebelum/sesudah beserta jumlah
  sumber/diproses. Reorder mencatat urutan ID dan sort order sebelum/sesudah.
- Schema: tabel `aud_transaction_log` dan kolom yang diperlukan sudah terbukti
  ada pada database staging serta baseline clean-install. Tidak ada SQL baru dan
  tidak ada mutasi data staging pada batch ini.
- File berubah: `application/controllers/Master.php`, smoke audit trail dan
  penyesuaian smoke inline, quality gate/contract, roadmap `_30`, dan execution
  log.
- Validasi: PHP lint, 22 contract audit trail, CSRF form 18, inline 15, holiday
  9, endpoint registry 47, quality-gate contract 27, serta quality gate
  `parallel` lulus: required 50/50, development 4/4, release 1/1, dan preflight
  1/1 tanpa temuan.
- Status: P0-01 menjadi `CODE_PASS + STAGING_PASS`; release tetap diblokir
  sampai negative role UAT. Tidak ada file POS Mobile/APK yang disentuh.
- Risiko sisa: alasan operator masih berupa catatan aksi sistem, bukan field
  alasan wajib pada setiap master; perubahan akun/role pegawai mempunyai jalur
  audit domain auth tersendiri yang masih perlu ditinjau pada baseline
  role/scope.
- Batch berikutnya: inventaris dan negative matrix baseline multi-role serta
  scope outlet/divisi pada database staging, tanpa mengubah definisi izin
  bisnis milik owner.

## Batch 150 — GAP-02 scope multi-role web/POS Mobile dan negative matrix

- Waktu: 2026-09-05 WIB.
- Prioritas: menyamakan fail-closed scope antara web dan POS Mobile serta
  membuktikan kondisi multi-role/outlet/terminal pada database staging.
- Temuan: web sudah menolak scope `NONE`/`AMBIGUOUS`, tetapi login POS Mobile
  belum memeriksa scope sebelum menerbitkan token dan request bearer belum
  memvalidasi ulang scope setelah perubahan role.
- Implementasi: login POS Mobile sekarang memvalidasi hasil
  `Auth_model::resolve_division_scope()` sebelum insert token. Setiap request
  bearer dan request mobile berbasis sesi memuat izin/scope aktif; status selain
  `GLOBAL`, `SINGLE` valid, atau superadmin ditolak sebelum identitas request,
  pembaruan `last_seen`, dan operasi bisnis. Respons login/bootstrap bearer
  mengirim konteks division, outlet, dan terminal yang otoritatif ke APK.
- Negative matrix: token tidak diterbitkan untuk `NONE`/`AMBIGUOUS`; token lama
  langsung ditolak bila kombinasi role menjadi ambigu; `SINGLE` tetap diterima;
  scope dan permission hanya dimuat sekali per request; session-backed endpoint
  mengikuti kebijakan yang sama.
- Probe staging read-only: 16 user aktif, 13 multi-role, 3 superadmin, 3 global,
  10 single, 0 `NONE`, 0 `AMBIGUOUS`; dua user bertoken mobile aktif mempunyai
  scope valid. Tidak ada orphan role assignment, duplicate active device key,
  atau terminal aktif dengan outlet invalid.
- Matrix permission: tidak diubah. Hak KASIR/BARISTA dan role lain tetap milik
  owner serta dapat disesuaikan lewat modul role; batch ini hanya memastikan
  script menerapkan union permission dan scope secara aman.
- File berubah: `application/controllers/Pos_mobile.php`, harness recovery POS,
  smoke negative scope baru, probe staging baru, quality gate/contract,
  package preflight fixture line, roadmap `_30`, dan execution log.
- SQL/database: tidak ada SQL baru dan seluruh query staging bersifat `SELECT`.
- Validasi: PHP lint; seluruh 11 smoke POS Mobile/APK; negative scope 20/20;
  auth division scope 42; inactive-role smoke; quality-gate contract; release
  preflight; probe staging; roadmap consistency; dan quality gate `parallel`
  lulus required 51/51, development 4/4, release 1/1, serta preflight 1/1.
- Risiko sisa: baseline hak per jabatan belum boleh di-reset tanpa keputusan
  bisnis owner; halaman simulator/report permission drift, step-up aksi sensitif,
  dan UAT APK/perangkat tetap terbuka.
- Batch berikutnya: simulator akses dan report permission drift read-only agar
  owner dapat meninjau dampak role/user tanpa mengubah matrix izin.

## Batch 151 — GAP-02 simulator akses dan report selisih permission

- Waktu: 2026-09-05, selesai pemeriksaan utama 20:13 WIB.
- Prioritas: P0-04/AUD-A1-RBAC-01. Menyediakan alat pemeriksaan dampak role
  sebelum owner mengubah izin bisnis; bukan mereset matrix role atau membangun
  License Hub/Control Center.
- Ringkasan arah dan implementasi: mengikuti pola fixer tunggal yang diminta
  owner. Resolver permission/scope dipakai bersama oleh jalur aktif dan
  simulator; mempertahankan signature publik lama untuk kompatibilitas login
  web/mobile dan turunannya. Simulasi tidak menyimpan role atau sesi.
- Perubahan utama:
  - Manajemen User/detail user mendapat tombol **Simulasi Akses**.
  - Empat tab: Akses Efektif, Perbandingan, Role & Scope, dan Baseline Paket;
    pencarian/filter modul dan paginasi 25 baris; scope sebelum/sesudah jelas.
  - Gabungan role aktif, GRANT lalu REVOKE, superadmin, akun nonaktif, scope
    kosong/konflik, serta menu memakai resolver yang sama dengan aplikasi.
  - Baseline paket berupa JSON berversi. Default belum disetujui; perbandingan
    tidak dianggap lulus atau gagal sebelum owner menetapkan acuan izin.
    Role di luar baseline ditandai belum dinilai, bukan otomatis salah.
  - Endpoint GET-only, no-store, membutuhkan izin lihat user dan permission
    user; input dibatasi dan output di-escape. Tidak ada impersonasi.
- File berubah:
  - `application/models/Auth_model.php`, `Access_audit_model.php` (baru).
  - `application/controllers/Users.php`, `application/config/routes.php`.
  - `application/config/rbac_permission_baseline.json` (baru).
  - `application/views/users/access_audit.php` (baru), `index.php`, `detail.php`.
  - `tools/tests/access_simulator_smoke.php` (baru), `finance_quality_gate.php`,
    `finance_quality_gate_contract_smoke.php`.
  - Roadmap induk `_30`, `_28`, serta execution log ini.
- SQL/database: tidak ada SQL/schema baru. Probe staging menggunakan transaksi
  read-only dan memverifikasi seluruh query laporan adalah SELECT. Hak akses,
  role, data transaksi, dan credential tidak diubah.
- Validasi:
  - PHP lint seluruh 10 file PHP berubah/baru dan `git diff --check` lulus.
  - Smoke simulator 43 pemeriksaan lulus; memakai model/query builder CI asli
    dengan fixture SQLite in-memory yang dikunci read-only setelah setup.
  - Probe staging `CI_ENV=staging ...access_simulator_smoke.php --staging`:
    46 pemeriksaan, 22 akun aktif/nonaktif, 200 halaman aktif, 0 database write.
    Preview role aktual identik dengan resolver aktif; preview tanpa role
    selalu gagal tertutup, termasuk untuk user superadmin yang sebenarnya.
  - Auth division scope 42, inactive-role smoke, dan login throttle 74 lulus.
  - Quality gate `parallel`: required 52/52, development 4/4, release 1/1,
    preflight 1/1 lulus. Runtime/security/static tier tidak dijalankan oleh
    profil ini; UAT perangkat bukan bagian dari klaim lulus.
  - Roadmap consistency 22 dan roadmap dashboard 30 lulus. Render Chrome
    fixture desktop ditinjau; struktur tab disesuaikan agar tetap horizontal
    dengan CSS aplikasi. Ini bukan pengganti UAT login browser nyata.
  - Composer tidak berubah, sehingga composer validate tidak diperlukan.
- Hasil review fixer tunggal: layak untuk batch ini. Regresi awal signature
  subclass pada smoke throttle diperbaiki dengan mempertahankan signature lama,
  bukan melemahkan test. Filter modul tidak lagi menyembunyikan hasil ketika
  pindah ke tab baseline. Batas data pengguna versus simulasi tetap eksplisit.
- Risiko sisa: isi baseline izin per jabatan menunggu keputusan owner;
  simulator bukan bukti akses tiap dokumen/outlet/terminal dan tidak menguji
  transaksi. UAT role/APK, anti-spam public review, serta step-up/MFA masih
  terbuka. A1 secara keseluruhan **belum selesai**.
- Catatan roadmap: penundaan edit mobile DEFER-02 lama dicatat telah dicabut
  owner; repair mismatch/historis DEFER-01/03 tetap tidak dikerjakan otomatis.
- Batch berikutnya: audit dan penguatan anti-spam endpoint public review,
  dibatasi pada script/validasi tanpa mengubah izin bisnis atau data mismatch.
- Penyerahan: commit lokal terpisah untuk pelacakan cutoff; tidak push.
  Ringkasan penyelesaian dikirim ke Telegram Namua setelah commit.

## Batch 152 — GAP-02 anti-spam formulir ulasan publik

- Waktu/tanggal: 2026-09-05, validasi akhir 20:51 WIB.
- Prioritas: P2-06/AUD-A1-REVIEW-01, tindak lanjut Batch 151. Scope terbatas
  formulir QR nota dan QR area; tidak menyentuh APK, matrix izin, atau data lama.
- Diskusi/arah: fixer tunggal sesuai pola terbaru owner. Temuan utama adalah
  kiriman station tanpa limiter, input array yang memicu warning, respons/error
  yang membeberkan profil member, dan member baru yang dapat tertinggal ketika
  insert ulasan gagal. Solusi memakai penyimpanan runtime lokal terkunci,
  validasi sebelum writer, minimisasi informasi publik, dan transaksi atomik.
- File berubah:
  - `application/controllers/Customer_reviews.php`.
  - `application/models/Pos_customer_review_model.php`.
  - `application/libraries/CustomerReviewGuard.php` dan `CustomerReviewInput.php`
    (baru).
  - `application/views/pos/customer_review_form.php` dan
    `customer_review_station_form.php`.
  - `tools/tests/public_customer_review_smoke.php` (baru),
    `finance_quality_gate.php`, `finance_quality_gate_contract_smoke.php`.
  - Roadmap induk `_30`, `_28`, serta execution log ini.
- Perubahan utama:
  - Percobaan POST dibatasi 60/IP/10 menit dan 12/sesi browser/10 menit.
    IP menggunakan resolver CI, tidak membaca header forwarding sembarang.
  - Form bertanda tangan terikat sesi/QR, minimal 2 detik, kedaluwarsa 1 jam,
    honeypot, serta reservasi sekali pakai yang aman lintas PHP worker.
  - Cooldown 60 detik per sesi dan nomor/nota; deduplikasi 10 menit. Gagal simpan
    boleh dicoba ulang setelah cooldown biasa, bukan menahan selama 10 menit.
  - Rating, scalar input, UTF-8, nomor telepon, ukuran, serta consent diperiksa
    sebelum pendaftaran member. Persetujuan nomor berlaku juga untuk member lama.
  - Nama/nomor member tersimpan dan detail error tidak ditampilkan ke publik;
    halaman sukses sama untuk member baru/lama. Informasi member diarahkan ke
    kasir. Input telepon tetap bukan verifikasi kepemilikan nomor/OTP.
  - Pembuatan member dan ulasan station atomik; receipt tetap sekali pakai.
    Data IP/user-agent baru tidak ditambahkan ke tabel ulasan. Data historis
    tidak diubah. Header no-store/no-referrer/noindex dan frame guard diterapkan.
  - Diagnostik tersampling dibatasi 200 event/24 jam (dipangkas pada akses
    berikutnya); menggunakan HMAC IP dan ID internal, tanpa data isi ulasan,
    nomor telepon, session ID, token formulir, atau IP mentah.
- SQL/runtime: **tidak ada SQL baru**, tidak ada migration atau perubahan
  credential/database config. Direktori baru
  `application/cache/customer-review-guard` disiapkan `www:www` mode 0700;
  file state dibuat PHP-FPM mode 0600 dan dikecualikan Git/package. Tidak
  mengubah permission direktori cache lain, nginx, backup, upload, atau log lama.
- Validasi:
  - `php -l` sembilan file PHP berubah/baru dan `git diff --check` lulus.
  - Smoke publik 43 pemeriksaan lulus: replay, honeypot, batas IP/sesi, expiry,
    cooldown/duplikasi, error storage, validasi, privasi, dan controller guard.
  - Empat proses PHP paralel memakai form identik: tepat satu diterima.
  - Model review/query builder CI asli pada SQLite disposable: receipt sekali
    pakai lulus; member writer fixture memakai nested transaction seperti POS;
    kegagalan insert ulasan membatalkan member baru. Tidak memakai DB staging
    untuk membuat member/ulasan uji.
  - HTTP staging via nginx/PHP-FPM: GET QR area 200 dengan signed form dan
    header privasi; empat POST negatif menghasilkan 403/403/422/403 tanpa
    warning PHP, termasuk route langsung controller. Jumlah review/member
    sebelum/sesudah tetap. Kedua tabel staging memakai InnoDB.
  - HTTP file state menghasilkan status 200 dengan body **0 byte** karena
    prefix PHP exit; tidak mengklaim URL tersebut diblokir nginx.
  - Quality gate `parallel`: required 53/53, development 4/4, release 1/1,
    preflight 1/1 lulus. Roadmap consistency 22 lulus. Runtime/security/static
    tier serta UAT QR fisik bukan bagian dari klaim lulus profil ini.
  - Composer tidak berubah; composer validate tidak diperlukan.
- Review akhir fixer tunggal: batch formulir publik layak. Review menemukan
  empat writer admin moderasi/pengaturan QR belum memiliki CSRF terarah; tidak
  memperluas patch ke sana pada batch ini. P2-06 tetap IN_PROGRESS/STAGING_PASS,
  bukan mengklaim A1 selesai.
- Risiko sisa: UAT Wi-Fi/proxy/QR/perangkat; limiter bersifat single-server dan
  harus diganti backend bersama untuk multi-node. Nomor WhatsApp belum OTP.
  CAPTCHA adaptif bukan fitur batch ini. Diagnostik bounded bukan audit permanen;
  relasi member/review dan catatan pendaftaran adalah jejak bisnis yang tersimpan.
- Batch berikutnya: guard CSRF untuk visibility, pengaturan ulasan, simpan QR
  area, serta aktif/nonaktif QR; perbaiki juga teks konfirmasi moderasi yang
  menyebut tindakan terbalik. Setelah itu kembali ke step-up/UAT A1.
- Penyerahan: commit lokal sesudah `b87db84`, tanpa push; ringkasan hasil dikirim
  ke Telegram Namua setelah commit. Roadmap `_30` memuat petunjuk runtime
  server utama/customer sehingga pemasangan tidak hanya bergantung pada SQL.

## Batch 153 — CSRF moderasi ulasan dan pengaturan QR admin

- Waktu/tanggal: 2026-09-05, validasi akhir 21:10 WIB.
- Prioritas: P2-06/AUD-A1-REVIEW-01, menutup empat writer admin yang ditemukan
  pada review Batch 152. Scope tetap modul Ulasan Pelanggan.
- Diskusi/arah: fixer tunggal sesuai pola terbaru owner. Writer visibility,
  pengaturan QR struk, simpan QR area, dan aktif/nonaktif QR sebelumnya belum
  memeriksa token CSRF. Tambahkan guard khusus modul tanpa mengubah izin bisnis,
  formulir publik, token transaksi POS, atau helper printer bersama.
- File berubah:
  - `application/controllers/Pos.php`.
  - `application/views/pos/customer_reviews_index.php`.
  - `tools/tests/customer_review_admin_csrf_smoke.php` (baru).
  - `tools/tests/finance_quality_gate.php` dan
    `tools/tests/finance_quality_gate_contract_smoke.php`.
  - Roadmap induk `_30`, `_28`, serta execution log ini.
- Perubahan utama:
  - Keempat writer mempertahankan pemeriksaan izin edit terlebih dahulu,
    kemudian wajib POST dan token CSRF sesi/header khusus sebelum membaca
    payload atau menjalankan model writer. Request tidak valid ditolak.
  - Token acak diterbitkan hanya melalui halaman yang diizinkan, tetap stabil
    dalam sesi untuk multi-tab, dan tidak menggunakan token transaksi POS.
    Halaman serta respons guard memakai no-store.
  - JavaScript lokal mengirim token lewat header, bukan URL; request dibatasi
    ke origin yang sama dan tidak mengikuti redirect. Respons gagal/non-JSON
    ditampilkan sebagai pesan, bukan dianggap berhasil.
  - Konfirmasi sembunyikan/tampilkan ulasan diperbaiki agar sesuai tindakan.
    Pesan schema belum tersedia mengarahkan admin memeriksa migrasi versi
    aplikasi, bukan menyuruh menjalankan ulang SQL lama secara sembarang.
  - Setelah update, muat ulang halaman Ulasan Pelanggan yang sudah terbuka
    supaya menerima token. Login ulang bila sesi telah berakhir.
- SQL/runtime: **tidak ada SQL baru**, perubahan schema/data, credential,
  permission/sidebar, atau konfigurasi runtime. Pos_mobile, Pos_model, routes,
  dan helper printer bersama tidak berubah.
- Validasi:
  - `php -l` kelima file PHP berubah/baru dan `git diff --check` lulus.
  - Smoke admin CSRF 179 pemeriksaan lulus: controller asli dengan model/session
    doubles menguji izin, metode, token hilang/salah/malformed, jalur valid,
    fallback registry permission, multi-tab, serta pemisahan token POS.
  - JavaScript hasil render view asli dijalankan di Node dengan DOM/fetch
    doubles: hide/show, pengaturan struk, simpan/toggle QR, penolakan lintas
    origin, respons error/non-JSON, dan mode hanya-baca lulus. Ini bukan UAT
    browser admin login nyata dan tidak membuat data uji di database staging.
  - Regresi CSRF transaksi POS 1691 dan ulasan publik 43 pemeriksaan lulus.
  - Quality gate `parallel`: required 54/54, development 4/4, release 1/1,
    preflight 1/1 lulus. Runtime/security/static/staging tier dan UAT perangkat
    bukan bagian dari klaim lulus profil ini.
  - HTTP staging: empat POST tanpa sesi login menghasilkan 303; Location login
    dikonfirmasi pada endpoint settings. Tidak mengklaim ini bukti writer admin
    berhasil dengan sesi nyata; tidak mengubah konfigurasi QR/ulasan aktual.
  - Setelah update roadmap, consistency 22 dan dashboard 30 pemeriksaan lulus.
  - Composer tidak berubah; composer validate tidak diperlukan.
- Review akhir fixer tunggal: batch layak. Guard berjalan sebelum payload dan
  writer, izin existing dipertahankan, dan token tidak tercampur dengan modul
  lain. AUD-A1-REVIEW-01 menjadi CODE_PASS; STAGING_PASS merujuk juga bukti
  publik Batch 152. Release tetap BLOCKED sampai acceptance/UAT terpenuhi,
  bukan mengklaim seluruh A1 selesai.
- Risiko sisa: UAT admin login dan QR/perangkat/proxy nyata; batas single-server
  limiter publik tetap berlaku. Baseline izin owner serta step-up tindakan
  sensitif masih terbuka dan tidak diputuskan otomatis pada batch ini.
- Batch berikutnya: telaah approval void/refund/reopen yang sudah ada sebelum
  memperkuat konfirmasi identitas tindakan sensitif secara bertahap; lanjut
  acceptance A1 tanpa mengubah data mismatch historis.
- Penyerahan: commit lokal sesudah `f0b3ce4`, tanpa push; ringkasan penyelesaian
  dikirim ke Telegram Namua setelah commit.
