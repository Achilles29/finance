SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-07h_repair_division_monthly_stock_mismatch.sql
-- Tujuan :
-- 1) Audit selisih antara inv_division_monthly_stock (snapshot)
--    vs inv_stock_movement_log (sumber kebenaran)
-- 2) Repair closing_qty pada baris terbaru per identity
--    yang tidak sesuai dengan kumulatif movement
--
-- Urutan aman:
-- a) Jalankan bagian AUDIT dulu (SELECT saja, tidak mengubah data)
-- b) Cek hasilnya masuk akal
-- c) Jalankan bagian REPAIR jika yakin
--
-- Catatan:
-- - Hanya memperbaiki baris TERBARU per identity
-- - Repair menyeluruh per hari sebaiknya lewat tombol Repair di UI
--   setelah patch code (fix stock_domain identity inference) diterapkan
-- ============================================================

-- ============================================================
-- A. AUDIT: tampilkan identitas yang punya selisih > 0.001
-- ============================================================
SELECT
  d.name         AS division_name,
  dms.destination_type,
  COALESCE(mi.item_name, '-')     AS item_name,
  COALESCE(mm.material_name, '-') AS material_name,
  dms.profile_key,
  dms.movement_date               AS latest_snapshot_date,
  dms.closing_qty_content         AS snapshot_qty,
  mv.cumulative_content           AS movement_total,
  ROUND(dms.closing_qty_content - mv.cumulative_content, 4) AS selisih,
  CASE
    WHEN dms.closing_qty_content > mv.cumulative_content THEN 'SNAPSHOT_LEBIH'
    ELSE 'MOVEMENT_LEBIH'
  END AS arah_selisih
FROM inv_division_monthly_stock dms

-- Ambil hanya baris terbaru per identity
INNER JOIN (
  SELECT
    division_id,
    destination_type,
    item_id,
    material_id,
    buy_uom_id,
    content_uom_id,
    profile_key,
    MAX(movement_date) AS latest_date
  FROM inv_division_monthly_stock
  GROUP BY division_id, destination_type, item_id, material_id,
           buy_uom_id, content_uom_id, profile_key
) latest ON
  latest.division_id       <=> dms.division_id
  AND latest.destination_type <=> dms.destination_type
  AND latest.item_id          <=> dms.item_id
  AND latest.material_id      <=> dms.material_id
  AND latest.buy_uom_id       <=> dms.buy_uom_id
  AND latest.content_uom_id    = dms.content_uom_id
  AND latest.profile_key      <=> dms.profile_key
  AND latest.latest_date       = dms.movement_date

-- Kumulatif movement per identity (seluruh waktu)
INNER JOIN (
  SELECT
    division_id,
    destination_type,
    item_id,
    material_id,
    buy_uom_id,
    content_uom_id,
    profile_key,
    COALESCE(SUM(qty_content_delta), 0) AS cumulative_content,
    COALESCE(SUM(qty_buy_delta),     0) AS cumulative_buy
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
  GROUP BY division_id, destination_type, item_id, material_id,
           buy_uom_id, content_uom_id, profile_key
) mv ON
  mv.division_id       <=> dms.division_id
  AND mv.destination_type <=> dms.destination_type
  AND mv.item_id          <=> dms.item_id
  AND mv.material_id      <=> dms.material_id
  AND mv.buy_uom_id       <=> dms.buy_uom_id
  AND mv.content_uom_id    = dms.content_uom_id
  AND mv.profile_key      <=> dms.profile_key

LEFT JOIN mst_operational_division d  ON d.id  = dms.division_id
LEFT JOIN mst_item      mi ON mi.id  = dms.item_id
LEFT JOIN mst_material  mm ON mm.id  = dms.material_id

WHERE ABS(dms.closing_qty_content - mv.cumulative_content) > 0.001

ORDER BY ABS(dms.closing_qty_content - mv.cumulative_content) DESC;


-- ============================================================
-- B. RINGKASAN: jumlah identity mismatch dan total selisih
-- ============================================================
SELECT
  COUNT(*)                                                            AS identity_mismatch_count,
  SUM(ABS(ROUND(dms.closing_qty_content - mv.cumulative_content, 4))) AS total_selisih_content
FROM inv_division_monthly_stock dms
INNER JOIN (
  SELECT division_id, destination_type, item_id, material_id,
         buy_uom_id, content_uom_id, profile_key, MAX(movement_date) AS latest_date
  FROM inv_division_monthly_stock
  GROUP BY division_id, destination_type, item_id, material_id,
           buy_uom_id, content_uom_id, profile_key
) latest ON
  latest.division_id    <=> dms.division_id
  AND latest.destination_type <=> dms.destination_type
  AND latest.item_id    <=> dms.item_id
  AND latest.material_id <=> dms.material_id
  AND latest.buy_uom_id  <=> dms.buy_uom_id
  AND latest.content_uom_id = dms.content_uom_id
  AND latest.profile_key <=> dms.profile_key
  AND latest.latest_date  = dms.movement_date
INNER JOIN (
  SELECT division_id, destination_type, item_id, material_id,
         buy_uom_id, content_uom_id, profile_key,
         COALESCE(SUM(qty_content_delta), 0) AS cumulative_content,
         COALESCE(SUM(qty_buy_delta),     0) AS cumulative_buy
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
  GROUP BY division_id, destination_type, item_id, material_id,
           buy_uom_id, content_uom_id, profile_key
) mv ON
  mv.division_id    <=> dms.division_id
  AND mv.destination_type <=> dms.destination_type
  AND mv.item_id    <=> dms.item_id
  AND mv.material_id <=> dms.material_id
  AND mv.buy_uom_id  <=> dms.buy_uom_id
  AND mv.content_uom_id = dms.content_uom_id
  AND mv.profile_key <=> dms.profile_key
WHERE ABS(dms.closing_qty_content - mv.cumulative_content) > 0.001;


-- ============================================================
-- C. REPAIR: update closing_qty baris terbaru ke nilai movement
--
-- JALANKAN HANYA SETELAH:
-- 1) Bagian A menunjukkan data yang masuk akal
-- 2) Sudah backup / yakin dengan hasilnya
-- ============================================================
UPDATE inv_division_monthly_stock dms
INNER JOIN (
  SELECT division_id, destination_type, item_id, material_id,
         buy_uom_id, content_uom_id, profile_key, MAX(movement_date) AS latest_date
  FROM inv_division_monthly_stock
  GROUP BY division_id, destination_type, item_id, material_id,
           buy_uom_id, content_uom_id, profile_key
) latest ON
  latest.division_id    <=> dms.division_id
  AND latest.destination_type <=> dms.destination_type
  AND latest.item_id    <=> dms.item_id
  AND latest.material_id <=> dms.material_id
  AND latest.buy_uom_id  <=> dms.buy_uom_id
  AND latest.content_uom_id = dms.content_uom_id
  AND latest.profile_key <=> dms.profile_key
  AND latest.latest_date  = dms.movement_date
INNER JOIN (
  SELECT division_id, destination_type, item_id, material_id,
         buy_uom_id, content_uom_id, profile_key,
         COALESCE(SUM(qty_content_delta), 0) AS cumulative_content,
         COALESCE(SUM(qty_buy_delta),     0) AS cumulative_buy
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
  GROUP BY division_id, destination_type, item_id, material_id,
           buy_uom_id, content_uom_id, profile_key
) mv ON
  mv.division_id    <=> dms.division_id
  AND mv.destination_type <=> dms.destination_type
  AND mv.item_id    <=> dms.item_id
  AND mv.material_id <=> dms.material_id
  AND mv.buy_uom_id  <=> dms.buy_uom_id
  AND mv.content_uom_id = dms.content_uom_id
  AND mv.profile_key <=> dms.profile_key
SET
  dms.closing_qty_content = ROUND(mv.cumulative_content, 4),
  dms.closing_qty_buy     = ROUND(mv.cumulative_buy, 4),
  dms.total_value         = ROUND(mv.cumulative_content * dms.avg_cost_per_content, 2)
WHERE ABS(dms.closing_qty_content - mv.cumulative_content) > 0.001;


-- ============================================================
-- D. VERIFIKASI PASCA REPAIR: harusnya 0 mismatch
-- ============================================================
SELECT
  COUNT(*) AS sisa_mismatch
FROM inv_division_monthly_stock dms
INNER JOIN (
  SELECT division_id, destination_type, item_id, material_id,
         buy_uom_id, content_uom_id, profile_key, MAX(movement_date) AS latest_date
  FROM inv_division_monthly_stock
  GROUP BY division_id, destination_type, item_id, material_id,
           buy_uom_id, content_uom_id, profile_key
) latest ON
  latest.division_id    <=> dms.division_id
  AND latest.destination_type <=> dms.destination_type
  AND latest.item_id    <=> dms.item_id
  AND latest.material_id <=> dms.material_id
  AND latest.buy_uom_id  <=> dms.buy_uom_id
  AND latest.content_uom_id = dms.content_uom_id
  AND latest.profile_key <=> dms.profile_key
  AND latest.latest_date  = dms.movement_date
INNER JOIN (
  SELECT division_id, destination_type, item_id, material_id,
         buy_uom_id, content_uom_id, profile_key,
         COALESCE(SUM(qty_content_delta), 0) AS cumulative_content
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
  GROUP BY division_id, destination_type, item_id, material_id,
           buy_uom_id, content_uom_id, profile_key
) mv ON
  mv.division_id    <=> dms.division_id
  AND mv.destination_type <=> dms.destination_type
  AND mv.item_id    <=> dms.item_id
  AND mv.material_id <=> dms.material_id
  AND mv.buy_uom_id  <=> dms.buy_uom_id
  AND mv.content_uom_id = dms.content_uom_id
  AND mv.profile_key <=> dms.profile_key
WHERE ABS(dms.closing_qty_content - mv.cumulative_content) > 0.001;
