<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$route['default_controller'] = 'auth';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

// Auth
$route['login']  = 'auth/index';
$route['logout'] = 'auth/logout';

// Dashboard
$route['dashboard'] = 'dashboard/index';

// Attendance
$route['attendance/settings'] = 'attendance/settings';
$route['attendance/daily'] = 'attendance/daily';
$route['attendance/logs'] = 'attendance/logs';
$route['attendance/schedules'] = 'attendance/schedules';
$route['attendance/schedules-v2'] = 'attendance/schedules_v2';
$route['attendance/schedule-v2'] = 'attendance/schedules_v2';
$route['attendance/schedules-v2/save'] = 'attendance/schedule_v2_save';
$route['attendance/schedules/store'] = 'attendance/schedule_store';
$route['attendance/schedules/update/(:num)'] = 'attendance/schedule_update/$1';
$route['attendance/schedules/delete/(:num)'] = 'attendance/schedule_delete/$1';
$route['attendance/schedules/bulk-store'] = 'attendance/schedule_bulk_store';
$route['attendance/pending-requests'] = 'attendance/pending_requests';
$route['attendance/pending-requests/action/(:num)'] = 'attendance/pending_request_action/$1';
$route['attendance/pending-requests/bulk-action'] = 'attendance/pending_request_bulk_action';
$route['attendance/overtime-entries'] = 'attendance/overtime_entries';
$route['attendance/overtime-entries/store'] = 'attendance/overtime_entry_store';
$route['attendance/overtime-entries/update/(:num)'] = 'attendance/overtime_entry_update/$1';
$route['attendance/overtime-entries/delete/(:num)'] = 'attendance/overtime_entry_delete/$1';
$route['attendance/anomalies'] = 'attendance/anomalies';
$route['attendance/master-health'] = 'attendance/master_health';
$route['attendance/estimate'] = 'attendance/estimate';
$route['attendance/estimate/detail/(:num)'] = 'attendance/estimate_detail/$1';
$route['attendance/meal-calendar'] = 'attendance/meal_calendar';
$route['attendance/ph-assignments'] = 'attendance/ph_assignments';
$route['attendance/ph-assignments/save'] = 'attendance/ph_assignment_save';
$route['attendance/ph-assignments/delete/(:num)'] = 'attendance/ph_assignment_delete/$1';
$route['attendance/ph-ledger'] = 'attendance/ph_ledger';
$route['attendance/ph-ledger/store'] = 'attendance/ph_ledger_store';
$route['attendance/ph-ledger/update/(:num)'] = 'attendance/ph_ledger_update/$1';
$route['attendance/ph-ledger/delete/(:num)'] = 'attendance/ph_ledger_delete/$1';
$route['attendance/ph-ledger/sync-grants'] = 'attendance/ph_ledger_sync_grants';
$route['attendance/ph-recap'] = 'attendance/ph_recap';

// HR Contract Operational
$route['hr/contracts'] = 'hr_contracts/index';
$route['hr/contracts/create-draft'] = 'hr_contracts/create_draft';
$route['hr/contracts/(:num)'] = 'hr_contracts/view/$1';
$route['hr/contracts/(:num)/generate'] = 'hr_contracts/generate_contract/$1';
$route['hr/contracts/(:num)/approve'] = 'hr_contracts/approve/$1';
$route['hr/contracts/(:num)/sign'] = 'hr_contracts/sign/$1';
$route['hr/contracts/(:num)/transition/(:any)'] = 'hr_contracts/transition/$1/$2';

// HR Contract compatibility routes (core-like)
$route['hr-contracts'] = 'hr_contracts/index';
$route['hr-contracts/templates'] = 'hr_contracts/templates';
$route['hr-contracts/template-edit'] = 'hr_contracts/template_edit';
$route['hr-contracts/template-edit/(:num)'] = 'hr_contracts/template_edit/$1';
$route['hr-contracts/template-delete/(:num)'] = 'hr_contracts/template_delete/$1';
$route['hr-contracts/template-preview'] = 'hr_contracts/template_preview';
$route['hr-contracts/generate'] = 'hr_contracts/generate';
$route['hr-contracts/view/(:num)'] = 'hr_contracts/view/$1';
$route['hr-contracts/print/(:num)'] = 'hr_contracts/print_view/$1';
$route['hr-contracts/(:num)/approve'] = 'hr_contracts/approve/$1';
$route['hr-contracts/(:num)/sign'] = 'hr_contracts/sign/$1';
$route['hr-contracts/(:num)/transition/(:any)'] = 'hr_contracts/transition/$1/$2';
$route['hr-contracts/verify/(:any)'] = 'hr_contract_verify/index/$1';
$route['payroll/preview-thp'] = 'payroll/preview_thp';
$route['payroll/manual-adjustments'] = 'payroll/manual_adjustments';
$route['payroll/manual-adjustments/store'] = 'payroll/manual_adjustment_store';
$route['payroll/manual-adjustments/update/(:num)'] = 'payroll/manual_adjustment_update/$1';
$route['payroll/manual-adjustments/delete/(:num)'] = 'payroll/manual_adjustment_delete/$1';
$route['payroll/meal-disbursements'] = 'payroll/meal_disbursements';
$route['payroll/meal-disbursements/generate'] = 'payroll/meal_disbursement_generate';
$route['payroll/meal-disbursements/mark-paid/(:num)'] = 'payroll/meal_disbursement_mark_paid/$1';
$route['payroll/meal-disbursements/void/(:num)'] = 'payroll/meal_disbursement_void/$1';
$route['payroll/salary-disbursements'] = 'payroll/salary_disbursements';
$route['payroll/payroll-periods'] = 'payroll/payroll_periods';
$route['payroll/salary-disbursements/period-generate'] = 'payroll/payroll_period_generate';
$route['payroll/salary-disbursements/period-void/(:num)'] = 'payroll/payroll_period_void/$1';
$route['payroll/salary-disbursements/period-delete/(:num)'] = 'payroll/payroll_period_delete/$1';
$route['payroll/salary-disbursements/generate'] = 'payroll/salary_disbursement_generate';
$route['payroll/salary-disbursements/mark-paid/(:num)'] = 'payroll/salary_disbursement_mark_paid/$1';
$route['payroll/salary-disbursements/slip/(:num)'] = 'payroll/salary_disbursement_slip/$1';
$route['payroll/salary-disbursements/void/(:num)'] = 'payroll/salary_disbursement_void/$1';
$route['payroll/salary-disbursements/delete/(:num)'] = 'payroll/salary_disbursement_delete/$1';
$route['payroll/cash-advances'] = 'payroll/cash_advances';
$route['payroll/cash-advances/store'] = 'payroll/cash_advance_store';
$route['payroll/cash-advances/update/(:num)'] = 'payroll/cash_advance_update/$1';
$route['payroll/cash-advances/pay-installment/(:num)'] = 'payroll/cash_advance_pay_installment/$1';
$route['payroll/cash-advances/void/(:num)'] = 'payroll/cash_advance_void/$1';
$route['payroll/cash-advances/delete/(:num)'] = 'payroll/cash_advance_delete/$1';

// Employee Portal
$route['my'] = 'my/index';
$route['my/attendance'] = 'my/attendance';
$route['my/attendance/mark'] = 'my/attendance_mark';
$route['my/profile'] = 'my/profile';
$route['my/schedule'] = 'my/schedule';
$route['my/payroll'] = 'my/payroll';
$route['my/payroll-slip/(:num)'] = 'my/payroll_slip/$1';
$route['my/leave-requests'] = 'my/leave_requests';
$route['my/leave-requests/schedule'] = 'my/leave_request_schedule';
$route['my/leave-requests/cancel/(:num)'] = 'my/leave_request_cancel/$1';
$route['my/meal-ledger'] = 'my/meal_ledger';
$route['my/overtime'] = 'my/overtime';
$route['my/ph-ledger'] = 'my/ph_ledger';
$route['my/manual-adjustments'] = 'my/manual_adjustments';
$route['my/cash-advance'] = 'my/cash_advance';

// Inventory flow (item -> material)

// Purchase
$route['procurement'] = 'procurement/store_requests';
$route['procurement/division-requests'] = 'procurement/division_requests';
$route['procurement/purchasing-desk'] = 'procurement/purchasing_desk';
$route['store-requests'] = 'procurement/store_requests';
$route['store-requests/create'] = 'procurement/store_request_create';
$route['store-requests/detail/(:num)'] = 'procurement/store_request_detail/$1';
$route['store-requests/edit/(:num)'] = 'procurement/store_request_edit/$1';
$route['procurement/division-po-sr'] = 'procurement/division_po_sr';
$route['procurement/division-po-sr/print'] = 'procurement/division_po_sr_print';
$route['procurement/division-po-sr/pdf'] = 'procurement/division_po_sr_pdf';
$route['procurement/division-po-sr/create'] = 'procurement/division_po_sr_create';
$route['procurement/division-po-sr/edit/(:num)'] = 'procurement/division_po_sr_edit/$1';
$route['procurement/division-po-sr/profile-search'] = 'procurement/division_po_sr_profile_search';
$route['procurement/division-po-sr/store'] = 'procurement/division_po_sr_store';
$route['procurement/division-po-sr/detail/(:num)'] = 'procurement/division_po_sr_detail/$1';
$route['procurement/division-po-sr/verify/(:num)'] = 'procurement/division_po_sr_verify/$1';
$route['procurement/division-po-sr/action/(:num)'] = 'procurement/division_po_sr_action/$1';
$route['procurement/store-request/profile-search'] = 'procurement/store_request_profile_search';
$route['procurement/store-request/store'] = 'procurement/store_request_store';
$route['procurement/store-request/update/(:num)'] = 'procurement/store_request_update/$1';
$route['procurement/store-request/action/(:num)'] = 'procurement/store_request_action/$1';
$route['procurement/store-request/split-preview/(:num)'] = 'procurement/store_request_split_preview/$1';
$route['procurement/store-request/fulfill/(:num)'] = 'procurement/store_request_fulfill/$1';
$route['procurement/store-request/repair-history/(:num)'] = 'procurement/store_request_repair_history/$1';
$route['procurement/store-request/generate-po/(:num)'] = 'procurement/store_request_generate_po/$1';

$route['purchase-orders'] = 'purchase/index';
$route['purchase-orders/create'] = 'purchase/order_create';
$route['purchase-orders/edit/(:num)'] = 'purchase/order_edit/$1';
$route['purchase-orders/detail/(:num)'] = 'purchase/order_detail/$1';
$route['purchase-orders/logs'] = 'purchase/order_log_index';
$route['purchase-orders/receipt'] = 'purchase/receipt_index';
$route['purchase'] = 'purchase/index';
$route['purchase/account'] = 'purchase/account_index';
$route['purchase/stock/warehouse'] = 'inventory_warehouse/index';
$route['purchase/stock/division'] = 'inventory_division/index';
$route['purchase/receipt'] = 'purchase/receipt_index';
$route['purchase/receipt/po-lines'] = 'purchase/receipt_po_lines';
$route['purchase/receipt/store'] = 'purchase/receipt_store';
$route['purchase/catalog/search'] = 'purchase/catalog_search';
$route['purchase/catalog/sync-core'] = 'purchase/catalog_sync_core';
$route['purchase/vendor/quick-store'] = 'purchase/vendor_quick_store';
$route['purchase/order/store'] = 'purchase/order_store';
$route['purchase/order/update/(:num)'] = 'purchase/order_update/$1';
$route['purchase/order/status-update'] = 'purchase/order_status_update';
$route['purchase/order/logs'] = 'purchase/order_log_index';
$route['purchase/rebuild-impact'] = 'purchase/rebuild_impact_index';
$route['purchase/rebuild-impact/run'] = 'purchase/rebuild_impact_run';
$route['purchase/reclassify-profile-domain'] = 'purchase/reclassify_profile_domain_index';
$route['purchase/reclassify-profile-domain/run'] = 'purchase/reclassify_profile_domain_run';
$route['purchase/payment/apply'] = 'purchase/payment_apply';
$route['finance/accounts'] = 'master/index/company-account';
$route['finance/mutations'] = 'purchase/finance_mutation_index';
$route['finance/mutations/store'] = 'purchase/finance_mutation_store';
$route['purchase/stock/opening'] = 'inventory/stock_opening_index';
$route['purchase/stock/opening/warehouse'] = 'inventory_warehouse/opening';
$route['purchase/stock/opening/division'] = 'inventory_division/opening';
$route['purchase/stock/opening/item-search'] = 'inventory/stock_opening_item_search';
$route['purchase/stock/opening/store'] = 'inventory/stock_opening_store';
$route['purchase/stock/opname/generate'] = 'inventory/stock_opname_generate';
$route['purchase/stock/warehouse/daily'] = 'inventory_warehouse/daily';
$route['purchase/stock/warehouse/daily-matrix'] = 'inventory_warehouse/daily_matrix';
$route['purchase/stock/warehouse/daily-matrix-view'] = 'inventory_warehouse/matrix_view';
$route['purchase/stock/warehouse/movement'] = 'inventory_warehouse/movement';
$route['purchase/stock/division/movement'] = 'inventory_division/movement';
$route['purchase/stock/division/daily'] = 'inventory_division/daily';
$route['purchase/stock/material/daily-matrix'] = 'inventory_division/material_matrix';
$route['purchase/stock/material/daily-matrix-view'] = 'inventory_division/matrix_view';
$route['inventory/stock/warehouse'] = 'inventory_warehouse/index';
$route['inventory/stock/division'] = 'inventory_division/index';
$route['inventory/stock/opening'] = 'inventory/stock_opening_index';
$route['inventory/stock/opening/warehouse'] = 'inventory_warehouse/opening';
$route['inventory/stock/opening/division'] = 'inventory_division/opening';
$route['inventory/stock/adjustment'] = 'inventory/stock_adjustment_index';
$route['inventory/stock/adjustment/warehouse'] = 'inventory_warehouse/adjustment';
$route['inventory/stock/adjustment/division'] = 'inventory_division/adjustment';
$route['inventory/stock/warehouse/lot'] = 'inventory_warehouse/lot';
$route['inventory/stock/division/lot'] = 'inventory_division/lot';
$route['inventory/stock/opening/item-search'] = 'inventory/stock_opening_item_search';
$route['inventory/stock/opening/store'] = 'inventory/stock_opening_store';
$route['inventory/stock/adjustment/item-search'] = 'inventory/stock_adjustment_item_search';
$route['inventory/stock/adjustment/store'] = 'inventory/stock_adjustment_store';
$route['inventory/stock/adjustment/post/(:num)'] = 'inventory/stock_adjustment_post/$1';
$route['inventory/stock/adjustment/void/(:num)'] = 'inventory/stock_adjustment_void/$1';
$route['inventory/stock/adjustment/delete/(:num)'] = 'inventory/stock_adjustment_delete/$1';
$route['inventory/stock/opname/generate'] = 'inventory/stock_opname_generate';
$route['inventory/stock/warehouse/daily'] = 'inventory_warehouse/daily';
$route['inventory/stock/warehouse/movement'] = 'inventory_warehouse/movement';
$route['inventory/stock/warehouse/daily-matrix'] = 'inventory_warehouse/daily_matrix';
$route['inventory/stock/division/movement'] = 'inventory_division/movement';
$route['inventory/stock/division/daily'] = 'inventory_division/daily';
$route['inventory/stock/material/daily-matrix'] = 'inventory_division/material_matrix';
$route['inventory-warehouse-daily/matrix'] = 'inventory_warehouse/daily_matrix';
$route['inventory-material-daily/matrix'] = 'inventory_division/material_matrix';
$route['inventory-warehouse-daily'] = 'inventory_warehouse/matrix_view';
$route['inventory-material-daily'] = 'inventory_division/matrix_view';
$route['inventory-daily/cell-detail'] = 'inventory/stock_daily_cell_detail';
$route['inventory/fifo-audit'] = 'inventory/fifo_audit';
$route['inventory/lot-audit'] = 'inventory/lot_audit';
$route['purchase/setup/sync-core'] = 'purchase/setup_sync_core';
$route['purchase/setup/sync-core-all'] = 'purchase/setup_sync_core_all';

// Production - Base/Prepare (read-only stage)
$route['production/component-stock'] = 'production/component_stock';
$route['production/component-stock/data'] = 'production/component_stock_data';
$route['production/component-movements'] = 'production/component_movements';
$route['production/component-movements/data'] = 'production/component_movements_data';
$route['production/component-daily'] = 'production/component_daily';
$route['production/component-daily/data'] = 'production/component_daily_data';
$route['production/component-openings'] = 'production/component_openings';
$route['production/component-openings/save'] = 'production/component_opening_save';
$route['production/component-openings/post/(:num)'] = 'production/component_opening_post/$1';
$route['production/component-openings/delete/(:num)'] = 'production/component_opening_delete/$1';
$route['production/component-openings/generate-monthly'] = 'production/component_opening_generate_monthly';
$route['production/component-adjustments'] = 'production/component_adjustments';
$route['production/component-adjustments/save'] = 'production/component_adjustment_save';
$route['production/component-adjustments/post/(:num)'] = 'production/component_adjustment_post/$1';
$route['production/component-adjustments/delete/(:num)'] = 'production/component_adjustment_delete/$1';
$route['production/component-batches'] = 'production/component_batches';
$route['production/component-batches/save'] = 'production/component_batch_save';
$route['production/component-batches/preview'] = 'production/component_batch_preview';
$route['production/component-batches/post/(:num)'] = 'production/component_batch_post/$1';
$route['production/component-batches/delete/(:num)'] = 'production/component_batch_delete/$1';
$route['production/component-picker-search'] = 'production/component_picker_search';
$route['production/component-categories'] = 'production/component_categories';
$route['production/component-categories/save'] = 'production/component_category_save';
$route['production/component-categories/toggle/(:num)'] = 'production/component_category_toggle/$1';
$route['production/component-categories/quick-map'] = 'production/component_category_quick_map';
$route['production/component-masters'] = 'production/component_masters';
$route['production/component-masters/data'] = 'production/component_masters_data';
$route['production/component-masters/save'] = 'production/component_master_save';
$route['production/component-masters/toggle/(:num)'] = 'production/component_master_toggle/$1';
$route['production/component-masters/usage/(:num)'] = 'production/component_master_usage/$1';
$route['production/component-formulas'] = 'production/component_formulas';
$route['production/component-formulas/data'] = 'production/component_formulas_data';
$route['production/component-formulas/detail-data'] = 'production/component_formula_detail';
$route['production/component-formulas/source-search'] = 'production/component_formula_source_search';
$route['production/component-formulas/detail/(:num)'] = 'production/component_formula_show/$1';
$route['production/component-formulas/edit/(:num)'] = 'production/component_formula_edit/$1';
$route['production/component-formulas/save'] = 'production/component_formula_save';
$route['production/component-formulas/save-bulk'] = 'production/component_formula_save_bulk';
$route['production/component-formulas/delete/(:num)'] = 'production/component_formula_delete/$1';
$route['production/component-cost-variables'] = 'production/component_cost_variables';
$route['production/component-cost-variables/save'] = 'production/component_cost_variable_save';

// Users
$route['users']                   = 'users/index';
$route['users/create']            = 'users/create';
$route['users/store']             = 'users/store';
$route['users/edit/(:num)']       = 'users/edit/$1';
$route['users/update/(:num)']     = 'users/update/$1';
$route['users/toggle/(:num)']     = 'users/toggle/$1';
$route['users/permissions/(:num)']= 'users/permissions/$1';
$route['users/save_override/(:num)'] = 'users/save_override/$1';

// Roles
$route['roles']                = 'roles/index';
$route['roles/create']         = 'roles/create';
$route['roles/store']          = 'roles/store';
$route['roles/edit/(:num)']    = 'roles/edit/$1';
$route['roles/update/(:num)']  = 'roles/update/$1';
$route['roles/delete/(:num)']  = 'roles/delete/$1';
$route['roles/matrix/(:num)']  = 'roles/matrix/$1';
$route['roles/save_matrix/(:num)'] = 'roles/save_matrix/$1';

// Sidebar favorites (AJAX)
$route['sidebar/pin']   = 'sidebar/pin';
$route['sidebar/unpin'] = 'sidebar/unpin';
$route['sidebar/reorder'] = 'sidebar/reorder';
$route['sidebar/manage'] = 'sidebar/manage';
$route['sidebar/manage/save'] = 'sidebar/save_structure';
$route['sidebar/manage/menu/store'] = 'sidebar/menu_store';
$route['sidebar/manage/menu/update/(:num)'] = 'sidebar/menu_update/$1';
$route['sidebar/manage/menu/delete/(:num)'] = 'sidebar/menu_delete/$1';

// Master Data Tahap 2
$route['master']                         = 'master/index/uom';
$route['master/relation/product-recipe']                         = 'master_relation/product_recipe_hub';
$route['master/relation/product-recipe/(:num)']                 = 'master_relation/product_recipe/$1';
$route['master/relation/product-recipe/(:num)/create']          = 'master_relation/product_recipe_create/$1';
$route['master/relation/product-recipe/(:num)/store']           = 'master_relation/product_recipe_store/$1';
$route['master/relation/product-recipe/edit/(:num)']            = 'master_relation/product_recipe_edit/$1';
$route['master/relation/product-recipe/edit/(:num)/update']     = 'master_relation/product_recipe_update/$1';
$route['master/relation/product-recipe/delete/(:num)']          = 'master_relation/product_recipe_delete/$1';

$route['master/relation/component-formula']                      = 'master_relation/component_formula_hub';
$route['master/relation/component-formula/(:num)']              = 'master_relation/component_formula/$1';
$route['master/relation/component-formula/(:num)/create']       = 'master_relation/component_formula_create/$1';
$route['master/relation/component-formula/(:num)/store']        = 'master_relation/component_formula_store/$1';
$route['master/relation/component-formula/edit/(:num)']         = 'master_relation/component_formula_edit/$1';
$route['master/relation/component-formula/edit/(:num)/update']  = 'master_relation/component_formula_update/$1';
$route['master/relation/component-formula/delete/(:num)']       = 'master_relation/component_formula_delete/$1';

$route['master/relation/product-extra']                         = 'master_relation/product_extra_hub';
$route['master/relation/product-extra/(:num)']                  = 'master_relation/product_extra/$1';
$route['master/relation/product-extra/(:num)/create']           = 'master_relation/product_extra_create/$1';
$route['master/relation/product-extra/(:num)/store']            = 'master_relation/product_extra_store/$1';
$route['master/relation/product-extra/delete/(:num)']           = 'master_relation/product_extra_delete/$1';

$route['master/relation/extra-group']                           = 'master_relation/extra_group_hub';
$route['master/relation/extra-group/(:num)']                    = 'master_relation/extra_group_products/$1';
$route['master/relation/extra-group/(:num)/save']               = 'master_relation/extra_group_products_save/$1';
$route['master/att-holiday/generate-year']      = 'master/att_holiday_generate_year';

$route['master/(:any)']                  = 'master/index/$1';
$route['master/(:any)/create']           = 'master/create/$1';
$route['master/(:any)/detail/(:num)']    = 'master/detail/$1/$2';
$route['master/(:any)/store']            = 'master/store/$1';
$route['master/(:any)/edit/(:num)']      = 'master/edit/$1/$2';
$route['master/(:any)/update/(:num)']    = 'master/update/$1/$2';
$route['master/(:any)/toggle/(:num)']    = 'master/toggle/$1/$2';
