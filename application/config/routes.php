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

// Inventory flow (item -> material)
$route['inventory/item-material-flow'] = 'inventory_flow/item_material';
$route['inventory/item-material-flow/store'] = 'inventory_flow/item_material_store';

// Purchase
$route['purchase-orders'] = 'purchase/index';
$route['purchase-orders/create'] = 'purchase/order_create';
$route['purchase-orders/edit/(:num)'] = 'purchase/order_edit/$1';
$route['purchase-orders/detail/(:num)'] = 'purchase/order_detail/$1';
$route['purchase-orders/logs'] = 'purchase/order_log_index';
$route['purchase-orders/receipt'] = 'purchase/receipt_index';
$route['purchase'] = 'purchase/index';
$route['purchase/account'] = 'purchase/account_index';
$route['purchase/stock/warehouse'] = 'purchase/stock_warehouse_index';
$route['purchase/stock/division'] = 'purchase/stock_division_index';
$route['purchase/receipt'] = 'purchase/receipt_index';
$route['purchase/receipt/po-lines'] = 'purchase/receipt_po_lines';
$route['purchase/receipt/store'] = 'purchase/receipt_store';
$route['purchase/catalog/search'] = 'purchase/catalog_search';
$route['purchase/catalog/sync-core'] = 'purchase/catalog_sync_core';
$route['purchase/order/store'] = 'purchase/order_store';
$route['purchase/order/update/(:num)'] = 'purchase/order_update/$1';
$route['purchase/order/status-update'] = 'purchase/order_status_update';
$route['purchase/order/logs'] = 'purchase/order_log_index';
$route['purchase/rebuild-impact'] = 'purchase/rebuild_impact_index';
$route['purchase/rebuild-impact/run'] = 'purchase/rebuild_impact_run';
$route['purchase/payment/apply'] = 'purchase/payment_apply';
$route['finance/accounts'] = 'master/index/company-account';
$route['finance/mutations'] = 'purchase/finance_mutation_index';
$route['finance/mutations/store'] = 'purchase/finance_mutation_store';
$route['purchase/stock/opening'] = 'purchase/stock_opening_index';
$route['purchase/stock/opening/store'] = 'purchase/stock_opening_store';
$route['purchase/stock/warehouse/daily'] = 'purchase/stock_warehouse_daily_index';
$route['purchase/stock/warehouse/daily-matrix'] = 'purchase/stock_warehouse_daily_matrix';
$route['purchase/stock/warehouse/daily-matrix-view'] = 'purchase/inventory_warehouse_daily_index';
$route['purchase/stock/warehouse/movement'] = 'purchase/stock_warehouse_movement_index';
$route['purchase/stock/division/movement'] = 'purchase/stock_division_movement_index';
$route['purchase/stock/division/daily'] = 'purchase/stock_division_daily_index';
$route['purchase/stock/material/daily-matrix'] = 'purchase/stock_material_daily_matrix';
$route['purchase/stock/material/daily-matrix-view'] = 'purchase/inventory_material_daily_index';
$route['inventory-warehouse-daily/matrix'] = 'purchase/stock_warehouse_daily_matrix';
$route['inventory-material-daily/matrix'] = 'purchase/stock_material_daily_matrix';
$route['inventory-warehouse-daily'] = 'purchase/inventory_warehouse_daily_index';
$route['inventory-material-daily'] = 'purchase/inventory_material_daily_index';
$route['inventory-daily/cell-detail'] = 'purchase/stock_daily_cell_detail';
$route['purchase/setup/sync-core'] = 'purchase/setup_sync_core';
$route['purchase/setup/sync-core-all'] = 'purchase/setup_sync_core_all';

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

$route['master/(:any)']                  = 'master/index/$1';
$route['master/(:any)/create']           = 'master/create/$1';
$route['master/(:any)/store']            = 'master/store/$1';
$route['master/(:any)/edit/(:num)']      = 'master/edit/$1/$2';
$route['master/(:any)/update/(:num)']    = 'master/update/$1/$2';
$route['master/(:any)/toggle/(:num)']    = 'master/toggle/$1/$2';
