SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-07-28a_online_food_delivery_quote.sql
-- Tujuan : Menyimpan quote ongkir, jarak rute, dan lokasi customer
--          untuk order Online Food.
-- ============================================================

START TRANSACTION;

ALTER TABLE pos_order
  ADD COLUMN IF NOT EXISTS delivery_fee_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER service_amount,
  ADD COLUMN IF NOT EXISTS delivery_distance_km DECIMAL(10,3) NULL AFTER delivery_fee_amount,
  ADD COLUMN IF NOT EXISTS delivery_route_distance_km DECIMAL(10,3) NULL AFTER delivery_distance_km,
  ADD COLUMN IF NOT EXISTS delivery_duration_min DECIMAL(10,2) NULL AFTER delivery_route_distance_km,
  ADD COLUMN IF NOT EXISTS delivery_address VARCHAR(255) NULL AFTER delivery_duration_min,
  ADD COLUMN IF NOT EXISTS customer_lat DECIMAL(10,7) NULL AFTER delivery_address,
  ADD COLUMN IF NOT EXISTS customer_lng DECIMAL(10,7) NULL AFTER customer_lat,
  ADD COLUMN IF NOT EXISTS customer_location_accuracy DECIMAL(10,2) NULL AFTER customer_lng,
  ADD KEY IF NOT EXISTS idx_pos_order_delivery_location (customer_lat, customer_lng);

COMMIT;

SELECT
  'pos_order.online_food_delivery_quote' AS migration_key,
  COUNT(*) AS column_count
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pos_order'
  AND COLUMN_NAME IN (
    'delivery_fee_amount',
    'delivery_distance_km',
    'delivery_route_distance_km',
    'delivery_duration_min',
    'delivery_address',
    'customer_lat',
    'customer_lng',
    'customer_location_accuracy'
  );
