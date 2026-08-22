SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-08-22c_repair_coklat_pasta_canonical_material_link.sql
-- Tujuan :
-- 1) Memperbaiki identitas bahan baku COKLAT PASTA pada master item/katalog
-- 2) Memperbaiki line draft PO202608220012 agar bisa diterima ke Kitchen
-- 3) Menjaga guard FIFO: bahan baku tidak boleh diterima tanpa material_id
--
-- Aman dijalankan berulang. Tidak mengubah PO/receipt historis yang sudah post.
-- ============================================================

START TRANSACTION;

-- Material lama dengan nama sama adalah canonical material untuk item ini.
UPDATE mst_material
SET is_active = 1
WHERE id = 260
  AND material_code = 'BB-CP-001'
  AND material_name = 'COKLAT PASTA';

UPDATE mst_item
SET
  material_id = 260,
  is_material = 1,
  default_usage_purpose = 'BAHAN_BAKU'
WHERE id = 176
  AND item_name = 'COKLAT PASTA';

UPDATE mst_purchase_catalog
SET material_id = 260
WHERE item_id = 176
  AND catalog_name = 'COKLAT PASTA'
  AND material_id IS NULL;

-- Hanya draft PO aktif yang sedang diperbaiki, bukan histori penerimaan lama.
UPDATE pur_purchase_order_line line_item
JOIN pur_purchase_order po ON po.id = line_item.purchase_order_id
SET line_item.material_id = 260
WHERE po.po_no = 'PO202608220012'
  AND po.status = 'DRAFT'
  AND line_item.id = 7606
  AND line_item.item_id = 176
  AND line_item.material_id IS NULL
  AND line_item.usage_purpose = 'BAHAN_BAKU';

COMMIT;

SELECT
  i.id AS item_id,
  i.item_name,
  i.material_id,
  i.is_material,
  i.default_usage_purpose,
  m.material_name,
  m.is_active AS material_is_active
FROM mst_item i
LEFT JOIN mst_material m ON m.id = i.material_id
WHERE i.id = 176;

SELECT
  po.po_no,
  po.status,
  line_item.id AS po_line_id,
  line_item.item_id,
  line_item.material_id,
  line_item.usage_purpose,
  line_item.profile_key
FROM pur_purchase_order po
JOIN pur_purchase_order_line line_item ON line_item.purchase_order_id = po.id
WHERE po.po_no = 'PO202608220012';
