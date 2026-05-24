# Finance

Finance adalah aplikasi operasional internal berbasis CodeIgniter 3 untuk purchase, inventory, produksi, HR, payroll, dan fondasi POS/akuntansi. Repo ini adalah pengembangan lanjutan dari surface `core`, dengan dokumentasi aktif dipusatkan di folder `docs/`.

## Mulai Dari Sini

- `docs/README.md` — indeks dokumentasi aktif
- `docs/SETUP.md` — setup lokal, troubleshooting, dan keputusan teknis final
- `docs/CODING_STANDARDS.md` — pola coding dan UI yang wajib diikuti
- `docs/ROADMAP.md` — progress resmi lintas tahap
- `docs/MODULES.md` — peta modul, tabel, alur, dan file kunci

## Update Terbaru

- Workbench produksi `component` sekarang sudah mencakup master, formula, variable cost, stok, mutasi, daily matrix, opening, adjustment, dan batch produksi.
- Form operasional component sudah memakai editor baris dan AJAX picker, tanpa form JSON mentah.
- Monthly carry-forward component dari daily rollup ke monthly opname + opening bulan berikutnya sudah tersedia.
- Master dan formula component sekarang punya indikator penggunaan, halaman usage detail, fallback HPP live yang selaras, dan action icon yang distandarkan.

## Catatan Praktis

- Progress modul dicatat di `docs/ROADMAP.md`.
- Detail modul dan file kunci dicatat di `docs/MODULES.md`.
- Pola coding dan UI baru dicatat di `docs/CODING_STANDARDS.md`.
- Root README ini sengaja ringkas; detail implementasi harian dipelihara di dokumen aktif dalam `docs/`.
