SET NAMES utf8mb4;

-- ============================================================
-- Cost Control POS: halaman baca-saja untuk menautkan pembelian
-- bahan, batch produksi, shrinkage, HPP, dan arus kas operasi.
-- ============================================================

START TRANSACTION;

INSERT INTO sys_page (page_code, page_name, module, description, is_active)
VALUES (
  'pos.report.cost_control.index',
  'Cost Control POS',
  'POS',
  'Dashboard baca-saja untuk analisis pembelian bahan, produksi, waste, HPP, margin, dan kas operasional.',
  1
)
ON DUPLICATE KEY UPDATE
  page_name = VALUES(page_name),
  module = VALUES(module),
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO sys_menu (menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type, parent_id)
SELECT
  'pos.report.cost_control',
  'Cost Control POS',
  'ri-funds-box-line',
  '/pos/reports/cost-control',
  page.id,
  2,
  1,
  'MAIN',
  report_group.id
FROM sys_page page
JOIN sys_menu report_group ON report_group.menu_code = 'pos.report.group'
WHERE page.page_code = 'pos.report.cost_control.index'
ON DUPLICATE KEY UPDATE
  menu_label = VALUES(menu_label),
  icon = VALUES(icon),
  url = VALUES(url),
  page_id = VALUES(page_id),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active),
  sidebar_type = VALUES(sidebar_type),
  parent_id = VALUES(parent_id),
  updated_at = CURRENT_TIMESTAMP;

INSERT INTO auth_role_permission (role_id, page_id, can_view, can_create, can_edit, can_delete, can_export, created_at)
SELECT role.id, page.id, 1, 0, 0, 0, 1, NOW()
FROM auth_role role
JOIN sys_page page ON page.page_code = 'pos.report.cost_control.index'
WHERE role.role_code IN ('SUPERADMIN', 'CEO', 'MGR', 'ADMIN', 'ADM_FIN', 'HOD')
ON DUPLICATE KEY UPDATE
  can_view = VALUES(can_view),
  can_create = VALUES(can_create),
  can_edit = VALUES(can_edit),
  can_delete = VALUES(can_delete),
  can_export = VALUES(can_export),
  updated_at = CURRENT_TIMESTAMP;

COMMIT;

SELECT page_code, page_name, module, is_active
FROM sys_page
WHERE page_code = 'pos.report.cost_control.index';
