# Handoff Progress Purchase + Inventory
**Tanggal update:** 2026-05-07
**Tujuan:** ringkasan cepat agar lanjutan kerja di laptop lain tetap nyambung tanpa kehilangan konteks.

---

## Snapshot Status Saat Ini

- Jalur aktif: Tahap 6 (Purchase) dan fondasi Tahap 7 (Inventori & Gudang).
- Kebijakan profile sudah dikunci: catalog menjadi source of truth untuk profile_key.
- Scope DIVISION: remap historis exact identity -> canonical catalog sudah selesai dan residual mismatch sudah 0.
- Scope WAREHOUSE: belum di-apply, sudah ada baseline kandidat remap (opening=2, balance=0, daily=5, movement=6).

---

## Perubahan Penting Yang Sudah Selesai

1. Hardening opening profile di aplikasi:
   - Catalog-first fallback saat pencarian profile opening.
   - Saat simpan opening, profile identity selalu dicoba canonical ke catalog; jika belum ada, auto-create di catalog lalu gunakan key catalog.
   - File: `application/models/Purchase_model.php`.

2. Remap historis DIVISION:
   - Script: `tools/remap_division_profile_keys_to_catalog.php`.
   - Karakteristik: exact identity, 1 transaksi DB, conflict-aware merge/delete, idempotent.
   - Hasil akhir verifikasi: remaining_opening=0, remaining_balance=0, remaining_daily=0, remaining_movement=0.

3. Fondasi inventori opening/opname:
   - Split menu opening gudang/divisi: `sql/2026-05-06e_purchase_stock_opening_split_menu_seed.sql`.
   - Split tabel opening snapshot: `sql/2026-05-06f_inventory_opening_snapshot_split_tables.sql`.
   - Flow monthly opname + generate opening bulan berikut: `sql/2026-05-06c_inventory_monthly_opname_and_opening_flow.sql`.
   - Adjustment 5 komponen: `sql/2026-05-06b_inventory_adjustment_components.sql`.

---

## Baseline Teknis Terakhir (Untuk Lanjutan Remap Gudang)

Hasil check read-only canonical match ke catalog (per 2026-05-07):

- warehouse_opening_candidates: 2
- warehouse_balance_candidates: 0
- warehouse_daily_candidates: 5
- warehouse_movement_candidates: 6

Catatan:
- Tabel opening gudang memakai `profile_key` 40-char, sehingga remap opening harus memakai `LEFT(canonical_profile_key, 40)`.
- Potensi conflict unique key perlu ditangani dengan pola merge/delete sebelum update key final.

---

## Rencana Eksekusi Berikutnya

1. Siapkan script remap khusus WAREHOUSE dengan pola aman yang sama seperti DIVISION (single transaction, idempotent, canonical by identity).
2. Jalankan dry-run dulu, catat candidate + conflict.
3. Jalankan apply dalam satu transaksi, lalu verifikasi residual sampai 0.
4. Simpan output before/after ke dokumen log agar audit trail lintas perangkat tetap utuh.
5. Lanjut smoke test UI opening gudang/divisi untuk memastikan konsistensi profile_key end-to-end.

---

## Titik Referensi Utama

- Roadmap utama: `docs/2026-05-01d_roadmap_pengembangan.md`
- Dokumen fondasi Tahap 6: `docs/2026-05-03f_tahap6_purchase_foundation.md`
- Checklist sinkronisasi/rebuild: `docs/purchase_rebuild_sync_checklist.md`
- Script remap yang sudah proven (DIVISION): `tools/remap_division_profile_keys_to_catalog.php`
