SET NAMES utf8mb4;

-- ============================================================
-- File   : 2026-06-19b_loyalty_redeem_rules_schema.sql
-- Tujuan :
-- 1) Tabel pos_redeem_rule — katalog hadiah yang bisa diperoleh
--    member melalui penukaran poin atau stamp
-- 2) Tambah rule_id ke pos_redeem_transaction (link ke katalog)
-- 3) Daftarkan halaman loyalty.redeem_rule.index di sys_page
-- 4) Tambah menu "Pengaturan Redeem" di bawah grp.loyalty
-- 5) Set permissions
-- ============================================================

START TRANSACTION;

-- ─────────────────────────────────────────────────────────────
-- 1. Katalog reward redeem
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pos_redeem_rule (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  rule_code           VARCHAR(60)     NOT NULL                COMMENT 'Kode unik internal, format RR-NAMA',
  rule_name           VARCHAR(150)    NOT NULL                COMMENT 'Nama reward (tampil ke operator)',
  description         TEXT            NULL                    COMMENT 'Penjelasan detail reward ini',

  -- ── Cara bayar (cost) ──
  cost_type           ENUM('POINT','STAMP','BOTH') NOT NULL DEFAULT 'POINT'
                      COMMENT 'Aset yang digunakan untuk redeem',
  point_cost          DECIMAL(14,4)   NULL        COMMENT 'Jumlah poin yang dibutuhkan (jika POINT atau BOTH)',
  stamp_campaign_id   BIGINT UNSIGNED NULL        COMMENT 'Campaign stamp yang berlaku (jika STAMP atau BOTH)',
  stamp_cost          DECIMAL(14,4)   NULL        COMMENT 'Jumlah stamp yang dibutuhkan (jika STAMP atau BOTH)',

  -- ── Reward yang didapat ──
  reward_type         ENUM('VOUCHER','PRODUCT','MERCHANDISE','DISCOUNT_AMOUNT','DISCOUNT_PERCENT','FREE_PRODUCT','OTHER')
                      NOT NULL DEFAULT 'DISCOUNT_AMOUNT'
                      COMMENT 'Jenis benefit/hadiah yang diterima member',
  voucher_campaign_id BIGINT UNSIGNED NULL        COMMENT 'Campaign voucher yang diterbitkan (jika reward_type = VOUCHER)',
  product_id          BIGINT UNSIGNED NULL        COMMENT 'Produk reward atau produk gratis (FK ke mst_product)',
  product_qty         DECIMAL(10,4)   NULL        COMMENT 'Jumlah produk yang diberikan',
  discount_amount     DECIMAL(14,2)   NULL        COMMENT 'Nilai diskon dalam rupiah (jika DISCOUNT_AMOUNT)',
  discount_percent    DECIMAL(8,4)    NULL        COMMENT 'Persen diskon 0–100 (jika DISCOUNT_PERCENT)',
  reward_notes        VARCHAR(255)    NULL        COMMENT 'Deskripsi reward untuk tipe MERCHANDISE / OTHER',

  -- ── Kondisi penggunaan ──
  min_spend_amount    DECIMAL(14,2)   NULL        COMMENT 'Minimal nominal transaksi agar bisa redeem ini (opsional)',
  stock_qty           INT             NULL        COMMENT 'Stok tersedia; NULL = tidak terbatas',
  redeemed_count      INT             NOT NULL DEFAULT 0 COMMENT 'Sudah berapa kali ditebus (diupdate otomatis)',

  -- ── Masa berlaku ──
  valid_from          DATE            NULL,
  valid_until         DATE            NULL,

  is_active           TINYINT(1)      NOT NULL DEFAULT 1,
  created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME        NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uk_rr_code         (rule_code),
  KEY idx_rr_cost_type          (cost_type),
  KEY idx_rr_reward_type        (reward_type),
  KEY idx_rr_active             (is_active),
  KEY idx_rr_stamp_campaign     (stamp_campaign_id),
  KEY idx_rr_voucher_campaign   (voucher_campaign_id),

  CONSTRAINT fk_rr_stamp_campaign   FOREIGN KEY (stamp_campaign_id)   REFERENCES pos_stamp_campaign(id),
  CONSTRAINT fk_rr_voucher_campaign FOREIGN KEY (voucher_campaign_id) REFERENCES pos_voucher_campaign(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Katalog reward yang bisa diperoleh member melalui proses redeem (poin/stamp)';

-- ─────────────────────────────────────────────────────────────
-- 2. Tambah rule_id ke pos_redeem_transaction
--    (idempotent — cek dulu via information_schema)
-- ─────────────────────────────────────────────────────────────
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'pos_redeem_transaction'
    AND COLUMN_NAME  = 'rule_id'
);

SET @add_col_sql = IF(
  @col_exists = 0,
  'ALTER TABLE pos_redeem_transaction ADD COLUMN rule_id BIGINT UNSIGNED NULL COMMENT ''FK ke pos_redeem_rule'' AFTER redeem_type',
  'SELECT ''rule_id already exists'' AS info'
);

PREPARE _stmt FROM @add_col_sql;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- ─────────────────────────────────────────────────────────────
-- 3. Halaman sys_page
-- ─────────────────────────────────────────────────────────────
INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES ('loyalty.redeem_rule.index', 'Pengaturan Redeem', 'LOYALTY',
        'Katalog reward yang bisa ditukar dengan poin atau stamp oleh member', 1)
ON DUPLICATE KEY UPDATE
  page_name   = VALUES(page_name),
  module      = VALUES(module),
  description = VALUES(description),
  is_active   = VALUES(is_active),
  updated_at  = CURRENT_TIMESTAMP;

-- ─────────────────────────────────────────────────────────────
-- 4. Menu sidebar di bawah grp.loyalty (sort_order 6)
-- ─────────────────────────────────────────────────────────────
INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'loyalty.redeem_rule',
  'Pengaturan Redeem',
  'ri-settings-3-line',
  '/loyalty/redeem-rules',
  p.id,
  6,
  1,
  'MAIN',
  parent.id
FROM sys_page p
JOIN sys_menu parent ON parent.menu_code = 'grp.loyalty'
WHERE p.page_code = 'loyalty.redeem_rule.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon       = VALUES(icon),
  url        = VALUES(url),
  page_id    = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active  = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

-- ─────────────────────────────────────────────────────────────
-- 5. Permissions — hanya MGR+ bisa create/edit/delete
-- ─────────────────────────────────────────────────────────────
INSERT INTO auth_role_permission
  (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at)
SELECT
  r.id,
  p.id,
  1,
  CASE WHEN r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN') THEN 1 ELSE 0 END,
  CASE WHEN r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN') THEN 1 ELSE 0 END,
  CASE WHEN r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN') THEN 1 ELSE 0 END,
  0,
  NOW()
FROM auth_role r
JOIN sys_page p ON p.page_code = 'loyalty.redeem_rule.index'
WHERE r.role_code IN ('SUPERADMIN','CEO','MGR','ADMIN','KASIR')
ON DUPLICATE KEY UPDATE
  can_view   = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit   = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

-- ─────────────────────────────────────────────────────────────
-- Verifikasi
-- ─────────────────────────────────────────────────────────────
SELECT 'pos_redeem_rule table'            AS item, COUNT(*) AS count
  FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='pos_redeem_rule'
UNION ALL
SELECT 'pos_redeem_transaction.rule_id',  COUNT(*)
  FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pos_redeem_transaction' AND column_name='rule_id'
UNION ALL
SELECT 'sys_page loyalty.redeem_rule.index', COUNT(*) FROM sys_page WHERE page_code='loyalty.redeem_rule.index'
UNION ALL
SELECT 'sys_menu loyalty.redeem_rule',    COUNT(*) FROM sys_menu WHERE menu_code='loyalty.redeem_rule'
UNION ALL
SELECT 'permissions seeded',              COUNT(*) FROM auth_role_permission rp
  JOIN sys_page p ON p.id=rp.page_id WHERE p.page_code='loyalty.redeem_rule.index';
