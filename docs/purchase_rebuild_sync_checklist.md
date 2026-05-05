# Checklist Uji Data Riil - Purchase Rebuild/Resync

## 1. Persiapan
1. Backup database terbaru.
2. Jalankan seed menu/page terbaru:
   - finance/sql/2026-05-04a_purchase_log_rebuild_menu_seed.sql
3. Login sebagai role yang punya akses edit purchase.
4. Pastikan data sample riil mencakup minimal:
   - PO status RECEIVED
   - PO status PAID
   - PO status non-eligible (mis. DRAFT) untuk validasi skip.

## 2. Validasi Sidebar dan Halaman
1. Buka menu Purchase di sidebar.
2. Pastikan ada menu:
   - Log Purchase
   - Rebuild Impact
3. Buka halaman Log Purchase dan pastikan data tampil.
4. Buka halaman Rebuild Impact Purchase.

## 3. Uji Dry Run (tanpa perubahan data)
1. Scope TRANSACTION untuk 1 PO RECEIVED, klik Dry Run.
2. Scope TRANSACTION untuk 1 PO PAID, klik Dry Run.
3. Scope ITEM (item_id/material_id valid), klik Dry Run.
4. Scope FILTER (date range + status), klik Dry Run.
5. Scope GLOBAL, klik Dry Run.
6. Pastikan:
   - Message sukses dry-run.
   - Ringkasan berisi planned/skipped sesuai kondisi.
   - Tidak ada perubahan saldo stok/keuangan.

## 4. Uji Execute By Transaksi
1. Pilih 1 PO RECEIVED yang sebelumnya belum sinkron penuh.
2. Jalankan Execute Rebuild.
3. Verifikasi:
   - Receipt effect sesuai hasil (POSTED/SKIPPED).
   - Mutasi stok gudang/divisi konsisten.
   - Tidak ada duplikasi posting saat diulang kedua kali.
4. Ulangi untuk 1 PO PAID.
5. Verifikasi tambahan untuk PAID:
   - Payment effect sesuai hasil.
   - Mutasi rekening perusahaan konsisten.

## 5. Uji Execute By Item / Filter / Global
1. Jalankan Execute scope ITEM untuk item/material aktif.
2. Jalankan Execute scope FILTER dengan kombinasi tanggal + status RECEIVED/PAID.
3. Jalankan Execute scope GLOBAL dengan limit kecil dulu (mis. 50), lalu bertahap.
4. Pastikan hasil mengandung kombinasi:
   - OK + changed
   - OK + unchanged (idempotent)
   - SKIPPED (status non-eligible)

## 6. Verifikasi Konsistensi Data
1. Tabel purchase:
   - pur_purchase_order
   - pur_purchase_receipt / pur_purchase_receipt_line
   - pur_purchase_payment_plan
2. Tabel stok:
   - inv_warehouse_stock_balance
   - inv_division_stock_balance
   - inv_stock_movement_log
   - inv_warehouse_daily_rollup
   - inv_division_daily_rollup
3. Tabel log:
   - pur_purchase_txn_log (action reconcile/update)
   - aud_transaction_log (termasuk PO_REBUILD_IMPACT_BATCH)
4. Pastikan rerun pada data sama tidak menambah efek baru (idempotent).

## 7. Kriteria Lulus
1. Sidebar menampilkan menu log + rebuild sesuai role.
2. Semua tampilan angka UI purchase tampil 2 desimal.
3. Rebuild bisa dijalankan pada 4 scope tanpa error fatal.
4. Dampak stok/finance sinkron dan tidak double-posting saat rerun.
5. Audit log terbentuk untuk eksekusi batch rebuild.
