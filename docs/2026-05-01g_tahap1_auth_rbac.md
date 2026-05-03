# Tahap 1 — Auth, RBAC & Sidebar
**Tanggal:** 2026-05-01  
**Status:** RANCANGAN — belum mulai coding  
**SQL File:** `finance/sql/2026-05-01a_auth_rbac_schema.sql`

---

## Ruang Lingkup

Modul ini adalah fondasi seluruh aplikasi. Semua modul lain bergantung pada ini.

Yang dibangun di tahap ini:
- Halaman login / logout
- Manajemen user (akun login)
- Manajemen role (kumpulan izin)
- Matrix izin: role → halaman + CRUD
- Assignment role ke karyawan
- Override izin per karyawan
- Sidebar menu (dinamis sesuai izin)
- Favorit sidebar per user

---

## Alur & Penjelasan Per Tabel

---

### `auth_user` — Akun Login

Setiap orang yang bisa login ke aplikasi harus punya record di sini.  
Satu karyawan = satu user akun (boleh tidak punya akun jika hanya absensi via device).

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | ID unik |
| `employee_id` | BIGINT UNSIGNED NULL FK→org_employee | Karyawan yang terkait (NULL jika user sistem/non-karyawan) |
| `username` | VARCHAR(60) UNIQUE | Username untuk login |
| `email` | VARCHAR(150) UNIQUE NULL | Email (opsional, bisa dipakai login juga) |
| `password_hash` | VARCHAR(255) | Password ter-hash (bcrypt) |
| `is_active` | TINYINT(1) | 1=aktif, 0=dinonaktifkan |
| `last_login_at` | DATETIME NULL | Waktu login terakhir |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME NULL | |

**Catatan:** `employee_id` NULL dimungkinkan untuk akun teknis/superadmin.

---

### `auth_session_log` — Log Sesi Login

Mencatat setiap aktivitas login dan logout. Berguna untuk audit.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `user_id` | BIGINT UNSIGNED FK→auth_user | User yang login |
| `ip_address` | VARCHAR(45) | IP address saat login |
| `user_agent` | VARCHAR(255) NULL | Browser/device info |
| `login_at` | DATETIME | Waktu login |
| `logout_at` | DATETIME NULL | Waktu logout (NULL jika belum logout) |
| `created_at` | DATETIME | |

---

### `auth_role` — Role / Grup Izin

Kumpulan izin yang diberi nama. Contoh: "Kasir", "Manajer", "Admin", "CEO".

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `role_code` | VARCHAR(50) UNIQUE | Kode unik role (contoh: `KASIR`, `MGR`) |
| `role_name` | VARCHAR(100) | Nama tampilan (contoh: "Kasir", "Manajer") |
| `description` | VARCHAR(255) NULL | Keterangan singkat role ini untuk apa |
| `is_active` | TINYINT(1) | |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME NULL | |

---

### `sys_page` — Daftar Halaman / Fitur

Setiap halaman (URL/controller/method) yang perlu dikontrol aksesnya didaftarkan di sini.  
Ini adalah "daftar izin yang bisa diberikan".

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `page_code` | VARCHAR(100) UNIQUE | Kode unik halaman (contoh: `pos.order.index`) |
| `page_name` | VARCHAR(150) | Nama tampilan (contoh: "Daftar Order POS") |
| `module` | VARCHAR(60) | Nama modul (contoh: `POS`, `HR`, `Finance`) |
| `description` | VARCHAR(255) NULL | Penjelasan halaman ini untuk apa |
| `is_active` | TINYINT(1) | 1=aktif, 0=sembunyikan dari matrix |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME NULL | |

**Format `page_code`:** `{modul}.{controller}.{aksi}`, contoh:
- `pos.order.index` — lihat daftar order
- `pos.order.create` — buat order baru
- `hr.employee.edit` — edit data karyawan

---

### `auth_role_permission` — Izin Role per Halaman + Aksi CRUD

Tabel ini menentukan: Role X boleh melakukan aksi apa di halaman Y.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `role_id` | BIGINT UNSIGNED FK→auth_role | Role yang diberi izin |
| `page_id` | BIGINT UNSIGNED FK→sys_page | Halaman yang diberi izin |
| `can_view` | TINYINT(1) | Boleh buka/lihat halaman |
| `can_create` | TINYINT(1) | Boleh tambah data baru |
| `can_edit` | TINYINT(1) | Boleh ubah data |
| `can_delete` | TINYINT(1) | Boleh hapus data |
| `can_export` | TINYINT(1) | Boleh export laporan |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME NULL | |
| UNIQUE | `(role_id, page_id)` | Satu role hanya boleh punya satu record per halaman |

**Logika:** Jika `can_view = 0` maka aksi lain tidak berlaku walau = 1. View adalah pintu masuk.

---

### `auth_user_role` — Assignment Role ke User

Menghubungkan user dengan role-nya. Satu user bisa punya lebih dari satu role.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `user_id` | BIGINT UNSIGNED FK→auth_user | User yang diberi role |
| `role_id` | BIGINT UNSIGNED FK→auth_role | Role yang diberikan |
| `assigned_by` | BIGINT UNSIGNED NULL FK→auth_user | Siapa yang assign |
| `assigned_at` | DATETIME | Kapan di-assign |
| UNIQUE | `(user_id, role_id)` | Tidak boleh duplikat |

---

### `auth_user_permission_override` — Override Izin per User

Jika ada karyawan yang perlu akses lebih atau kurang dari role-nya, dicatat di sini.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `user_id` | BIGINT UNSIGNED FK→auth_user | User yang di-override |
| `page_id` | BIGINT UNSIGNED FK→sys_page | Halaman yang di-override |
| `override_type` | ENUM('GRANT','REVOKE') | GRANT=tambah izin, REVOKE=cabut izin |
| `can_view` | TINYINT(1) | Override izin view |
| `can_create` | TINYINT(1) | Override izin create |
| `can_edit` | TINYINT(1) | Override izin edit |
| `can_delete` | TINYINT(1) | Override izin delete |
| `can_export` | TINYINT(1) | Override izin export |
| `reason` | VARCHAR(255) NULL | Alasan override (untuk audit) |
| `overridden_by` | BIGINT UNSIGNED NULL FK→auth_user | Admin yang buat override |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME NULL | |
| UNIQUE | `(user_id, page_id)` | Satu user satu record override per halaman |

**Logika penggabungan izin saat login:**
```
Izin final = gabungan semua role user (OR)
           DITAMBAH override GRANT
           DIKURANGI override REVOKE
```

---

### `sys_menu` — Daftar Item Sidebar Menu

Mendefinisikan struktur menu navigasi. Bisa bersarang (parent → child).

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `parent_id` | BIGINT UNSIGNED NULL FK→sys_menu | NULL = item menu level atas |
| `menu_code` | VARCHAR(80) UNIQUE | Kode unik menu |
| `menu_label` | VARCHAR(100) | Label yang tampil di sidebar |
| `icon` | VARCHAR(80) NULL | Nama ikon (contoh: `fa-shopping-cart`) |
| `url` | VARCHAR(255) NULL | URL tujuan (NULL jika hanya heading) |
| `page_id` | BIGINT UNSIGNED NULL FK→sys_page | Izin halaman yang diperlukan untuk tampil |
| `sort_order` | INT | Urutan tampil |
| `is_active` | TINYINT(1) | |
| `sidebar_type` | ENUM('MAIN','MY') | MAIN=sidebar operasional, MY=sidebar pribadi |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME NULL | |

**Logika tampil menu:** Menu tampil jika `page_id` NULL (selalu tampil) ATAU user punya `can_view = 1` untuk `page_id` tersebut.

---

### `sys_sidebar_favorite` — Favorit Sidebar per User

User bisa pin menu favorit untuk akses cepat.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `user_id` | BIGINT UNSIGNED FK→auth_user | |
| `menu_id` | BIGINT UNSIGNED FK→sys_menu | Menu yang di-favoritkan |
| `sort_order` | INT | Urutan favorit user tersebut |
| `created_at` | DATETIME | |
| UNIQUE | `(user_id, menu_id)` | |

---

## Relasi Antar Tabel

```
auth_user
  ├── FK employee_id → org_employee (akan dibuat di Tahap 3)
  ├── 1:N auth_session_log
  ├── 1:N auth_user_role → auth_role
  │        └── auth_role → 1:N auth_role_permission → sys_page
  ├── 1:N auth_user_permission_override → sys_page
  └── 1:N sys_sidebar_favorite → sys_menu → sys_page

sys_menu
  └── self-ref parent_id (tree structure)
```

---

## Logika Cek Izin (Di Kode)

```
function can(user_id, page_code, action='view') {
  1. Ambil semua role user dari auth_user_role
  2. Ambil izin semua role tersebut dari auth_role_permission JOIN sys_page
  3. Gabungkan (OR): jika salah satu role punya izin → user punya izin
  4. Cek override GRANT → tambahkan izin
  5. Cek override REVOKE → cabut izin
  6. Return true/false untuk action yang dicek
}
```

Fungsi ini di-cache ke session saat login agar tidak query DB tiap request.

---

## Halaman yang Dibangun

| Halaman | URL | Izin yang Diperlukan |
|---|---|---|
| Login | `/login` | — (publik) |
| Logout | `/logout` | — (login saja) |
| Dashboard | `/` | — (login saja) |
| Daftar User | `/users` | `auth.users.index` |
| Tambah User | `/users/create` | `auth.users.create` |
| Edit User | `/users/edit/{id}` | `auth.users.edit` |
| Nonaktifkan User | `/users/toggle/{id}` | `auth.users.edit` |
| Daftar Role | `/roles` | `auth.roles.index` |
| Buat Role | `/roles/create` | `auth.roles.create` |
| Edit Role + Matrix Izin | `/roles/edit/{id}` | `auth.roles.edit` |
| Hapus Role | `/roles/delete/{id}` | `auth.roles.delete` |
| Override Izin User | `/users/permissions/{id}` | `auth.users.edit` |
| Favorit Sidebar | `/sidebar/favorites` | — (login saja) |

---

## Catatan Implementasi

- Password di-hash menggunakan `password_hash()` PHP (bcrypt, bukan MD5/SHA1)
- Session menggunakan session native CI3 atau database-backed session
- Izin di-cache ke session saat login, di-refresh saat ada perubahan role/override
- Superadmin (role khusus `SUPERADMIN`) bypass semua cek izin
- Halaman Sidebar Pribadi (My) tidak perlu `page_id` karena semua karyawan login boleh akses
