SET @target_month := '2026-06-01';
SELECT
  s.division_id,
  d.code AS division_code,
  d.name AS division_name,
  COALESCE(s.destination_type, 'OTHER') AS destination_type,
  s.material_id,
  m.material_code,
  m.material_name,
  s.item_id,
  i.item_code,
  i.item_name,
  COALESCE(s.profile_key, '') AS profile_key,
  COALESCE(s.profile_name, '') AS profile_name,
  ROUND(COALESCE(s.opening_qty_content, 0), 4) AS monthly_opening_qty_content,
  ROUND(COALESCE(os.opening_qty_content, 0), 4) AS snapshot_opening_qty_content,
  ROUND(COALESCE(prev.closing_qty_content, 0), 4) AS prev_closing_qty_content,
  ROUND(COALESCE(mv.net_non_opening_delta, 0), 4) AS net_non_opening_delta,
  ROUND(COALESCE(s.closing_qty_content, 0), 4) AS monthly_closing_qty_content,
  ROUND(COALESCE(s.closing_qty_content, 0) - (COALESCE(s.opening_qty_content, 0) + COALESCE(mv.net_non_opening_delta, 0)), 4) AS gap_value,
  COALESCE(sib.active_item_count, 0) AS active_item_count,
  CASE
    WHEN os.id IS NOT NULL AND ABS(COALESCE(s.closing_qty_content, 0) - (COALESCE(os.opening_qty_content, 0) + COALESCE(mv.net_non_opening_delta, 0))) <= 0.0001 THEN 'RESTORE_OPENING_FROM_SNAPSHOT'
    WHEN prev.id IS NOT NULL AND ABS(COALESCE(s.closing_qty_content, 0) - (COALESCE(prev.closing_qty_content, 0) + COALESCE(mv.net_non_opening_delta, 0))) <= 0.0001 THEN 'SEED_OPENING_FROM_PREV_MONTH_CLOSING'
    WHEN sib.active_item_count > 1 THEN 'CROSS_ITEM_LEGACY_CANDIDATE'
    WHEN COALESCE(mv.month_movement_rows, 0) = 0 THEN 'NO_MONTH_MOVEMENT_REVIEW_MONTHLY'
    ELSE 'REVIEW_MOVEMENT_HISTORY'
  END AS repair_bucket
FROM inv_division_monthly_stock s
LEFT JOIN inv_division_stock_opening_snapshot os
  ON os.snapshot_month = @target_month
 AND os.division_id = s.division_id
 AND COALESCE(os.destination_type, 'OTHER') = COALESCE(s.destination_type, 'OTHER')
 AND os.item_id = s.item_id
 AND COALESCE(os.material_id, 0) = COALESCE(s.material_id, 0)
 AND COALESCE(os.profile_key, '') = COALESCE(s.profile_key, '')
LEFT JOIN inv_division_monthly_stock prev
  ON prev.month_key = DATE_SUB(@target_month, INTERVAL 1 MONTH)
 AND prev.division_id = s.division_id
 AND COALESCE(prev.destination_type, 'OTHER') = COALESCE(s.destination_type, 'OTHER')
 AND prev.item_id = s.item_id
 AND COALESCE(prev.material_id, 0) = COALESCE(s.material_id, 0)
 AND COALESCE(prev.profile_key, '') = COALESCE(s.profile_key, '')
LEFT JOIN (
  SELECT
    division_id,
    COALESCE(destination_type, 'OTHER') AS destination_type,
    item_id,
    COALESCE(material_id, 0) AS material_id,
    COALESCE(profile_key, '') AS profile_key,
    ROUND(SUM(CASE
      WHEN COALESCE(ref_table, '') IN ('inv_division_stock_opening_snapshot', 'inv_warehouse_stock_opening_snapshot') THEN 0
      ELSE COALESCE(qty_content_delta, 0)
    END), 4) AS net_non_opening_delta,
    COUNT(*) AS month_movement_rows
  FROM inv_stock_movement_log
  WHERE movement_scope = 'DIVISION'
    AND movement_date >= @target_month
    AND movement_date < DATE_ADD(@target_month, INTERVAL 1 MONTH)
  GROUP BY division_id, COALESCE(destination_type, 'OTHER'), item_id, COALESCE(material_id, 0), COALESCE(profile_key, '')
) mv
  ON mv.division_id = s.division_id
 AND mv.destination_type = COALESCE(s.destination_type, 'OTHER')
 AND mv.item_id = s.item_id
 AND mv.material_id = COALESCE(s.material_id, 0)
 AND mv.profile_key = COALESCE(s.profile_key, '')
LEFT JOIN (
  SELECT material_id, COUNT(*) AS active_item_count
  FROM mst_item
  WHERE COALESCE(material_id,0) > 0 AND COALESCE(is_active,1)=1
  GROUP BY material_id
) sib ON sib.material_id = COALESCE(s.material_id, 0)
LEFT JOIN mst_operational_division d ON d.id = s.division_id
LEFT JOIN mst_material m ON m.id = s.material_id
LEFT JOIN mst_item i ON i.id = s.item_id
WHERE s.month_key = @target_month
  AND COALESCE(s.material_id, 0) > 0
  AND ABS(COALESCE(s.closing_qty_content, 0) - (COALESCE(s.opening_qty_content, 0) + COALESCE(mv.net_non_opening_delta, 0))) > 0.0001
ORDER BY repair_bucket, ABS(gap_value) DESC, s.material_id, s.item_id, s.profile_key
LIMIT 60;
