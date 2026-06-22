<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Purchase extends MY_Controller
{
    const PAGE_ORDER = 'purchase.order.index';
    const PAGE_CATALOG = 'purchase.catalog.index';
    const PAGE_ACCOUNT = 'purchase.account.index';
    const PAGE_STOCK_WAREHOUSE = 'purchase.stock.warehouse.index';
    const PAGE_STOCK_DIVISION = 'purchase.stock.division.index';
    const PAGE_STOCK_OPENING = 'purchase.stock.opening.index';
    const PAGE_STOCK_ADJUSTMENT_WAREHOUSE = 'purchase.stock.adjustment.warehouse.index';
    const PAGE_STOCK_ADJUSTMENT_DIVISION = 'purchase.stock.adjustment.division.index';
    const PAGE_STOCK_WAREHOUSE_MATRIX = 'purchase.stock.warehouse.matrix.index';
    const PAGE_STOCK_MATERIAL_MATRIX = 'purchase.stock.material.matrix.index';
    const PAGE_RECEIPT = 'purchase.receipt.index';
    const PAGE_ORDER_LOG = 'purchase.order.log.index';
    const PAGE_REBUILD_IMPACT = 'purchase.rebuild.impact.index';
    const PAGE_REPORT = 'purchase.report.index';
    const PAGE_RECLASSIFY_PROFILE_DOMAIN = 'purchase.reclassify.profile.domain.index';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Purchase_model');
    }

    public function index()
    {
        $this->require_permission(self::PAGE_ORDER, 'view');

        $today = date('Y-m-d');
        $q = trim((string)$this->input->get('q', true));
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        $dateStart = trim((string)$this->input->get('date_start', true));
        $dateEnd = trim((string)$this->input->get('date_end', true));
        $tab = strtolower(trim((string)$this->input->get('tab', true)));
        if (!in_array($tab, ['nota', 'rincian', 'paid'], true)) {
            $tab = 'nota';
        }
        if ($dateStart === '') {
            $dateStart = $today;
        }
        if ($dateEnd === '') {
            $dateEnd = $today;
        }
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 300) {
            $limit = 100;
        }

        $rangeStatus = 'ALL';

        $data = [
            'title' => 'Purchase Order',
            'active_menu' => 'purchase.order',
            'summary' => $this->Purchase_model->get_dashboard_summary(),
            'card_summary' => $this->Purchase_model->get_purchase_order_filtered_summary($q, $rangeStatus, $dateStart, $dateEnd),
            'filtered_summary' => $this->Purchase_model->get_purchase_order_filtered_summary($q, $status, $dateStart, $dateEnd),
            'line_summary' => $this->Purchase_model->get_purchase_order_line_filtered_summary($q, $status, $dateStart, $dateEnd),
            'month_attention_summary' => $this->Purchase_model->get_purchase_order_filtered_summary('', 'ALL', date('Y-m-01'), date('Y-m-t')),
            'payment_method_breakdown' => $this->Purchase_model->get_purchase_order_payment_method_breakdown($q, $rangeStatus, $dateStart, $dateEnd),
            'type_breakdown' => $this->Purchase_model->get_purchase_order_type_breakdown($q, $rangeStatus, $dateStart, $dateEnd),
            'q' => $q,
            'status' => $status,
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'tab' => $tab,
            'limit' => $limit,
            'status_options' => ['ALL', 'DRAFT', 'APPROVED', 'ORDERED', 'REJECTED', 'PARTIAL_RECEIVED', 'RECEIVED', 'PAID', 'VOID'],
            'rows' => $this->Purchase_model->list_purchase_orders_dashboard($q, $status, $dateStart, $dateEnd, $limit),
            'line_rows' => $this->Purchase_model->list_purchase_order_lines_dashboard($q, $status, $dateStart, $dateEnd, $limit),
            'paid_rows' => $tab === 'paid' ? $this->Purchase_model->list_purchase_orders_paid_dashboard($q, $dateStart, $dateEnd, $limit) : [],
            'paid_summary' => $tab === 'paid' ? $this->Purchase_model->get_purchase_order_paid_filtered_summary($q, $dateStart, $dateEnd) : ['total_count' => 0, 'total_value' => 0.0],
        ];

        $this->render('purchase/index', $data);
    }

    public function order_detail(int $purchaseOrderId = 0)
    {
        $this->require_permission(self::PAGE_ORDER, 'view');

        if ($purchaseOrderId <= 0) {
            $this->session->set_flashdata('error', 'Purchase order tidak valid.');
            redirect('purchase-orders');
            return;
        }

        $detail = $this->Purchase_model->get_purchase_order_detail($purchaseOrderId);
        if (!$detail) {
            $this->session->set_flashdata('error', 'Purchase order tidak ditemukan.');
            redirect('purchase-orders');
            return;
        }

        $data = [
            'title' => 'Purchase Order / Detail',
            'active_menu' => 'purchase.order',
            'detail' => $detail,
            'editability' => $this->Purchase_model->get_order_data_editability($purchaseOrderId),
        ];

        $this->render('purchase/order_detail', $data);
    }

    public function order_log_index()
    {
        if (!$this->can(self::PAGE_ORDER_LOG, 'view')) {
            $this->require_permission(self::PAGE_ORDER, 'view');
        }

        $q = trim((string)$this->input->get('q', true));
        $action = strtoupper(trim((string)$this->input->get('action', true)));
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo = trim((string)$this->input->get('date_to', true));
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 1000) {
            $limit = 50;
        }

        $data = [
            'title' => 'Purchase Transaction Log',
            'active_menu' => 'purchase.order.log',
            'q' => $q,
            'action' => $action,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'limit' => $limit,
            'action_options' => $this->Purchase_model->list_purchase_txn_action_codes(),
            'rows' => $this->Purchase_model->list_purchase_txn_logs($q, $action, $dateFrom, $dateTo, $limit),
        ];

        $this->render('purchase/order_log_index', $data);
    }

    public function report_index()
    {
        if (!$this->can(self::PAGE_REPORT, 'view')) {
            $this->require_permission(self::PAGE_ORDER, 'view');
        }

        $today = date('Y-m-d');
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo = trim((string)$this->input->get('date_to', true));
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        $purchaseTypeId = (int)$this->input->get('purchase_type_id', true);
        $detailDate = trim((string)$this->input->get('detail_date', true));
        $detailPurchaseTypeId = (int)$this->input->get('detail_purchase_type_id', true);
        $reportTab = strtolower(trim((string)$this->input->get('report_tab', true)));
        if (!in_array($reportTab, ['ringkasan', 'matrix'], true)) {
            $reportTab = 'ringkasan';
        }

        if ($dateFrom === '') {
            $dateFrom = date('Y-m-01');
        }
        if ($dateTo === '') {
            $dateTo = date('Y-m-t');
        }
        if ($status === '') {
            $status = 'PAID';
        }

        $dailyRows = $this->Purchase_model->list_purchase_report_daily($dateFrom, $dateTo, $status, $purchaseTypeId);
        $matrixDates = [];
        $matrixRowsMap = [];
        try {
            $start = new DateTime($dateFrom);
            $end = new DateTime($dateTo);
            if ($start <= $end) {
                $itEnd = clone $end;
                $itEnd->modify('+1 day');
                $period = new DatePeriod($start, new DateInterval('P1D'), $itEnd);
                foreach ($period as $d) {
                    $matrixDates[] = $d->format('Y-m-d');
                }
            }
        } catch (Exception $e) {
            $matrixDates = [];
        }
        if (count($matrixDates) > 62) {
            $matrixDates = array_slice($matrixDates, -62);
        }
        foreach ((array)$this->Purchase_model->list_active_purchase_types() as $pt) {
            $ptId = (int)($pt['id'] ?? 0);
            if ($ptId <= 0) {
                continue;
            }
            if ($purchaseTypeId > 0 && $ptId !== $purchaseTypeId) {
                continue;
            }
            $matrixRowsMap[$ptId] = [
                'purchase_type_id' => $ptId,
                'purchase_type_name' => (string)($pt['type_name'] ?? ('TYPE #' . $ptId)),
                'cells' => [],
                'total_po' => 0,
                'total_qty_buy' => 0.0,
                'total_value' => 0.0,
            ];
        }
        foreach ($dailyRows as $dr) {
            $ptId = (int)($dr['purchase_type_id'] ?? 0);
            if ($ptId <= 0) {
                continue;
            }
            if (!isset($matrixRowsMap[$ptId])) {
                $matrixRowsMap[$ptId] = [
                    'purchase_type_id' => $ptId,
                    'purchase_type_name' => (string)($dr['purchase_type_name'] ?? ('TYPE #' . $ptId)),
                    'cells' => [],
                    'total_po' => 0,
                    'total_qty_buy' => 0.0,
                    'total_value' => 0.0,
                ];
            }
            $dayKey = (string)($dr['request_date'] ?? '');
            $poCount = (int)($dr['total_po'] ?? 0);
            $qtyBuy = (float)($dr['total_qty_buy'] ?? 0);
            $value = (float)($dr['total_value'] ?? 0);
            $matrixRowsMap[$ptId]['cells'][$dayKey] = [
                'total_po' => $poCount,
                'total_qty_buy' => $qtyBuy,
                'total_value' => $value,
            ];
            $matrixRowsMap[$ptId]['total_po'] += $poCount;
            $matrixRowsMap[$ptId]['total_qty_buy'] += $qtyBuy;
            $matrixRowsMap[$ptId]['total_value'] += $value;
        }
        $detailMatrixLines = $this->Purchase_model->list_purchase_report_matrix_product_details($dateFrom, $dateTo, $status, $purchaseTypeId);
        $matrixDetailMap = [];
        foreach ($detailMatrixLines as $ln) {
            $dt = (string)($ln['request_date'] ?? '');
            $ptId = (int)($ln['purchase_type_id'] ?? 0);
            if ($dt === '' || $ptId <= 0) {
                continue;
            }
            $nm = trim((string)($ln['snapshot_item_name'] ?? ''));
            if ($nm === '') {
                $nm = trim((string)($ln['snapshot_material_name'] ?? ''));
            }
            if ($nm === '') {
                $nm = trim((string)($ln['snapshot_line_description'] ?? '-'));
            }
            $matrixDetailMap[$ptId][$dt][] = [
                'name' => $nm === '' ? '-' : $nm,
                'qty_buy' => (float)($ln['qty_buy'] ?? 0),
                'buy_uom_code' => (string)($ln['snapshot_buy_uom_code'] ?? '-'),
                'line_subtotal' => (float)($ln['line_subtotal'] ?? 0),
            ];
        }
        $matrixRows = array_values($matrixRowsMap);

        $data = [
            'title' => 'Laporan Purchase',
            'active_menu' => 'purchase.report',
            'report_tab' => $reportTab,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'status' => $status,
            'purchase_type_id' => $purchaseTypeId,
            'detail_date' => $detailDate,
            'detail_purchase_type_id' => $detailPurchaseTypeId,
            'status_options' => ['ALL', 'DRAFT', 'APPROVED', 'ORDERED', 'REJECTED', 'PARTIAL_RECEIVED', 'RECEIVED', 'PAID', 'VOID'],
            'purchase_types' => $this->Purchase_model->list_active_purchase_types(),
            'overview' => $this->Purchase_model->get_purchase_report_overview($dateFrom, $dateTo, $status, $purchaseTypeId),
            'monthly_rows' => $this->Purchase_model->list_purchase_report_monthly($dateFrom, $dateTo, $status, $purchaseTypeId),
            'daily_rows' => $dailyRows,
            'matrix_dates' => $matrixDates,
            'matrix_rows' => $matrixRows,
            'matrix_detail_map' => $matrixDetailMap,
            'detail_rows' => $this->Purchase_model->list_purchase_report_detail_by_day_type($detailDate, $detailPurchaseTypeId, $status),
        ];

        $this->render('purchase/report_index', $data);
    }

    public function rebuild_impact_index()
    {
        if (!$this->can(self::PAGE_REBUILD_IMPACT, 'view')) {
            $this->require_permission(self::PAGE_ORDER, 'view');
        }

        $data = [
            'title' => 'Rebuild Impact Purchase',
            'active_menu' => 'purchase.rebuild.impact',
            'status_options' => ['DRAFT', 'APPROVED', 'ORDERED', 'REJECTED', 'PARTIAL_RECEIVED', 'RECEIVED', 'PAID', 'VOID'],
        ];

        $this->render('purchase/rebuild_impact_index', $data);
    }

    public function rebuild_impact_run()
    {
        if (!$this->can(self::PAGE_REBUILD_IMPACT, 'edit') && !$this->can(self::PAGE_ORDER, 'edit')) {
            $this->jsonError('Anda tidak memiliki izin untuk menjalankan rebuild impact purchase.', 403);
            return;
        }

        $payload = $this->requestPayload();

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Purchase_model->rebuild_purchase_impacts(
                $payload,
                (int)($this->current_user['id'] ?? 0),
                (string)$this->input->ip_address()
            );
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal menjalankan rebuild impact purchase.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function repair_inventory_opening_history_cli()
    {
        if (!$this->input->is_cli_request()) {
            show_404();
            return;
        }

        $argv = $_SERVER['argv'] ?? [];
        $cliArgs = [];
        for ($i = 3; $i < count($argv); $i += 2) {
            $key = isset($argv[$i]) ? trim((string)$argv[$i]) : '';
            if ($key === '') {
                continue;
            }
            $cliArgs[$key] = isset($argv[$i + 1]) ? trim((string)$argv[$i + 1]) : '';
        }

        $scope = strtoupper(trim((string)($cliArgs['scope'] ?? 'WAREHOUSE')));
        $monthFrom = trim((string)($cliArgs['month_from'] ?? date('Y-m-01')));
        $itemId = (int)($cliArgs['item_id'] ?? 0);
        $limit = (int)($cliArgs['limit'] ?? 500);

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Purchase_model->repair_inventory_opening_history([
                'stock_scope' => $scope,
                'month_from' => $monthFrom,
                'item_id' => $itemId,
                'limit' => $limit,
            ], 0, 'CLI');
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if (!($result['ok'] ?? false)) {
            fwrite(STDERR, (string)($result['message'] ?? 'Repair opening gagal.') . PHP_EOL);
            exit(1);
        }

        fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL);
        exit(0);
    }

    public function reclassify_profile_domain_index()
    {
        if (
            !$this->can(self::PAGE_RECLASSIFY_PROFILE_DOMAIN, 'view')
            && !$this->can(self::PAGE_REBUILD_IMPACT, 'view')
            && !$this->can(self::PAGE_ORDER, 'view')
        ) {
            $this->require_permission(self::PAGE_ORDER, 'view');
        }

        $data = [
            'title' => 'Reclassify ITEM/MATERIAL by Profile Key',
            'active_menu' => 'purchase.reclassify-profile-domain',
        ];

        $this->render('purchase/reclassify_profile_domain_index', $data);
    }

    public function reclassify_profile_domain_run()
    {
        if (
            !$this->can(self::PAGE_RECLASSIFY_PROFILE_DOMAIN, 'edit')
            && !$this->can(self::PAGE_REBUILD_IMPACT, 'edit')
            && !$this->can(self::PAGE_ORDER, 'edit')
        ) {
            $this->jsonError('Anda tidak memiliki izin untuk menjalankan reclassify profile domain.', 403);
            return;
        }

        $payload = $this->requestPayload();

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Purchase_model->reclassify_item_material_by_profile_key(
                $payload,
                (int)($this->current_user['id'] ?? 0),
                (string)$this->input->ip_address()
            );
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal menjalankan reclassify.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function order_create()
    {
        $this->require_permission(self::PAGE_ORDER, 'create');

        $data = [
            'title' => 'Purchase Orders / Create',
            'active_menu' => 'purchase.order',
            'purchase_types' => $this->Purchase_model->list_active_purchase_types(),
            'vendors' => $this->Purchase_model->list_active_vendors(),
            'divisions' => $this->Purchase_model->list_active_operational_divisions(),
            'uoms' => $this->Purchase_model->list_active_uoms(),
            'payment_accounts' => $this->Purchase_model->list_active_payment_accounts(),
            'status_options' => ['DRAFT'],
        ];

        $this->render('purchase/order_create', $data);
    }

    public function order_edit(int $purchaseOrderId = 0)
    {
        $this->require_permission(self::PAGE_ORDER, 'edit');

        if ($purchaseOrderId <= 0) {
            $this->session->set_flashdata('error', 'Purchase order tidak valid.');
            redirect('purchase-orders');
            return;
        }

        $detail = $this->Purchase_model->get_purchase_order_detail($purchaseOrderId);
        if (!$detail) {
            $this->session->set_flashdata('error', 'Purchase order tidak ditemukan.');
            redirect('purchase-orders');
            return;
        }

        $editability = $this->Purchase_model->get_order_data_editability($purchaseOrderId);
        if (!($editability['ok'] ?? false)) {
            $this->session->set_flashdata('error', (string)($editability['message'] ?? 'PO tidak dapat diedit.'));
            redirect('purchase-orders/detail/' . $purchaseOrderId);
            return;
        }

        $data = [
            'title' => 'Purchase Orders / Edit',
            'active_menu' => 'purchase.order',
            'purchase_types' => $this->Purchase_model->list_active_purchase_types(),
            'vendors' => $this->Purchase_model->list_active_vendors(),
            'divisions' => $this->Purchase_model->list_active_operational_divisions(),
            'uoms' => $this->Purchase_model->list_active_uoms(),
            'payment_accounts' => $this->Purchase_model->list_active_payment_accounts(),
            'status_options' => ['DRAFT', 'APPROVED', 'ORDERED', 'REJECTED', 'PARTIAL_RECEIVED', 'RECEIVED', 'PAID', 'VOID'],
            'edit_mode' => true,
            'detail' => $detail,
            'editability' => $editability,
        ];

        $this->render('purchase/order_create', $data);
    }

    public function vendor_quick_store()
    {
        if (!$this->can(self::PAGE_ORDER, 'create') && !$this->can(self::PAGE_ORDER, 'edit')) {
            $this->jsonError('Anda tidak memiliki izin untuk menambah vendor dari halaman purchase.', 403);
            return;
        }

        $payload = $this->requestPayload();

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Purchase_model->quick_create_vendor($payload);
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal menambah vendor.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function account_index()
    {
        redirect('finance/accounts');
    }

    public function finance_mutation_index()
    {
        $this->require_permission(self::PAGE_ORDER, 'view');

        $accountId = (int)$this->input->get('account_id', true);
        $scope = strtolower(trim((string)$this->input->get('scope', true)));
        if (!in_array($scope, ['all', 'manual'], true)) {
            $scope = 'all';
        }
        $mutationType = strtoupper(trim((string)$this->input->get('mutation_type', true)));
        if (!in_array($mutationType, ['ALL', 'IN', 'OUT'], true)) {
            $mutationType = 'ALL';
        }
        $moduleFilter = strtoupper(trim((string)$this->input->get('module_filter', true)));
        $allowedModuleFilters = ['ALL', 'POS', 'PURCHASE', 'FINANCE', 'FINANCE_TRANSFER', 'FINANCE_PAYABLE', 'FINANCE_RECEIVABLE', 'PAYROLL'];
        if (!in_array($moduleFilter, $allowedModuleFilters, true)) {
            $moduleFilter = 'ALL';
        }
        $dateFromRaw = trim((string)$this->input->get('date_from', true));
        $dateToRaw = trim((string)$this->input->get('date_to', true));
        $range = $this->resolveDateRange('', $dateFromRaw, $dateToRaw);
        $dateFrom = $range['date_from'];
        $dateTo = $range['date_to'];

        $perPage = $this->mutation_per_page();
        $page = max(1, (int)$this->input->get('page', true));
        $totalRows = $this->Purchase_model->count_account_mutations($accountId, $dateFrom, $dateTo, $scope, $mutationType, $moduleFilter);
        $pg = $this->build_pagination($totalRows, $perPage, $page);

        $data = [
            'title' => 'Mutasi Keuangan Rekening',
            'active_menu' => 'finance.mutation',
            'accounts' => $this->Purchase_model->list_active_company_accounts(),
            'summary' => $this->Purchase_model->get_account_mutation_summary($accountId, $dateFrom, $dateTo, $scope, $mutationType, $moduleFilter),
            'account_breakdown' => $this->Purchase_model->get_account_mutation_per_account_breakdown($dateFrom, $dateTo, $scope, $mutationType, $moduleFilter),
            'rows' => $this->Purchase_model->list_account_mutations($accountId, $dateFrom, $dateTo, $pg['per_page'], $pg['offset'], $scope, $mutationType, $moduleFilter),
            'pg' => $pg,
            'filter_account_id' => $accountId,
            'scope' => $scope,
            'filter_mutation_type' => $mutationType,
            'filter_module' => $moduleFilter,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'month' => $range['month'],
        ];

        $this->render('purchase/finance_mutation_index', $data);
    }

    public function finance_mutation_store()
    {
        $this->require_permission(self::PAGE_ORDER, 'edit');

        $payload = $this->requestPayload();
        $result = $this->Purchase_model->apply_manual_account_mutation(
            $payload,
            (int)($this->current_user['id'] ?? 0),
            (string)$this->input->ip_address()
        );

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal memproses mutasi rekening.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function stock_warehouse_index()
    {
        if (!$this->can(self::PAGE_STOCK_WAREHOUSE, 'view')) {
            $this->require_permission(self::PAGE_ORDER, 'view');
        }

        $q = trim((string)$this->input->get('q', true));
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo = trim((string)$this->input->get('date_to', true));
        $range = $this->resolveDateRange('', $dateFrom, $dateTo);
        $dateFrom = $range['date_from'];
        $dateTo = $range['date_to'];
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 500) {
            $limit = 500;
        }

        $data = [
            'title' => 'Stok Gudang',
            'active_menu' => 'purchase.stock.warehouse',
            'q' => $q,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'limit' => $limit,
            'rows' => $this->Purchase_model->list_warehouse_stock($q, $limit, $dateFrom, $dateTo),
        ];

        $this->render('purchase/stock_warehouse_index', $data);
    }

    public function stock_opening_index()
    {
        redirect('inventory/stock/opening/warehouse');
    }

    public function stock_opening_warehouse_index()
    {
        if (!$this->can(self::PAGE_STOCK_WAREHOUSE, 'view')) {
            $this->require_permission(self::PAGE_ORDER, 'view');
        }

        $month = trim((string)$this->input->get('month', true));
        $q = trim((string)$this->input->get('q', true));
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 500) {
            $limit = 500;
        }

        $data = [
            'title' => 'Opening Gudang',
            'active_menu' => 'purchase.stock.opening.warehouse',
            'stock_scope' => 'WAREHOUSE',
            'is_division_scope' => false,
            'base_url_opening' => 'inventory/stock/opening/warehouse',
            'month' => $month,
            'q' => $q,
            'division_id' => 0,
            'destination' => 'ALL',
            'limit' => $limit,
            'rows' => $this->Purchase_model->list_stock_opening_snapshots('WAREHOUSE', $month, $q, $limit, null, null),
            'uoms' => $this->Purchase_model->list_active_uoms(),
            'divisions' => [],
        ];

        $this->render('purchase/stock_opening_index', $data);
    }

    public function stock_opening_division_index()
    {
        if (!$this->can(self::PAGE_STOCK_DIVISION, 'view')) {
            $this->require_permission(self::PAGE_ORDER, 'view');
        }

        $month      = trim((string)$this->input->get('month', true));
        $q          = trim((string)$this->input->get('q', true));
        $divisionId = (int)$this->input->get('division_id', true);
        $destination = strtoupper(trim((string)$this->input->get('destination', true)));
        if ($destination === '') {
            $destination = 'ALL';
        }
        $perPage = (int)$this->input->get('per_page', true);
        if ($perPage < 10 || $perPage > 200) {
            $perPage = 25;
        }
        $page = max(1, (int)$this->input->get('page', true));

        $data = [
            'title'                         => 'Opening Manual Bahan Baku',
            'active_menu'                   => 'purchase.stock.opening.division',
            'stock_scope'                   => 'DIVISION',
            'is_division_scope'             => true,
            'base_url_opening'              => 'inventory/stock/opening/division',
            'stock_opening_export_url'      => site_url('inventory/stock/opening/division/export-template'),
            'stock_opening_export_existing_url' => site_url('inventory/stock/opening/division/export-existing'),
            'stock_opening_import_url'      => site_url('inventory/stock/opening/division/import'),
            'month'       => $month,
            'q'           => $q,
            'division_id' => $divisionId,
            'destination' => $destination,
            'per_page'    => $perPage,
            'page'        => $page,
            'rows'        => $this->Purchase_model->list_stock_opening_snapshots('DIVISION', $month, $q, 500, $divisionId > 0 ? $divisionId : null, $destination),
            'uoms'        => $this->Purchase_model->list_active_uoms(),
            'divisions'   => $this->Purchase_model->list_active_operational_divisions(),
        ];

        $this->render('purchase/stock_opening_division_index', $data);
    }

    public function stock_opening_division_generated()
    {
        if (!$this->can(self::PAGE_STOCK_DIVISION, 'view')) {
            $this->require_permission(self::PAGE_ORDER, 'view');
        }

        $month       = trim((string)$this->input->get('month', true));
        $q           = trim((string)$this->input->get('q', true));
        $divisionId  = (int)$this->input->get('division_id', true);
        $destination = strtoupper(trim((string)$this->input->get('destination', true)));
        if ($destination === '') {
            $destination = 'ALL';
        }
        $perPage = (int)$this->input->get('per_page', true);
        if ($perPage < 10 || $perPage > 200) {
            $perPage = 25;
        }
        $page = max(1, (int)$this->input->get('page', true));

        $data = [
            'title'       => 'Stok Awal Bahan Baku',
            'active_menu' => 'inventory.stock.opening.division.generated',
            'month'       => $month,
            'q'           => $q,
            'division_id' => $divisionId,
            'destination' => $destination,
            'per_page'    => $perPage,
            'page'        => $page,
            'rows'        => $this->Purchase_model->list_stock_opening_snapshots(
                'DIVISION', $month, $q, 500,
                $divisionId > 0 ? $divisionId : null,
                $destination,
                ['source_type' => 'AUTO_REBUILD']
            ),
            'divisions'   => $this->Purchase_model->list_active_operational_divisions(),
        ];

        $this->render('purchase/stock_opening_division_generated_index', $data);
    }

    public function stock_opening_division_export_template()
    {
        if (!$this->can(self::PAGE_STOCK_DIVISION, 'view') && !$this->can(self::PAGE_STOCK_DIVISION, 'create')) {
            $this->require_permission(self::PAGE_ORDER, 'view');
        }

        $divisionId = (int)$this->input->get('division_id', true);
        $destination = $this->stock_opening_import_destination((string)$this->input->get('destination', true));
        $month = $this->stock_opening_import_month((string)$this->input->get('month', true));
        $backUrl = $this->stock_opening_division_redirect_url([
            'division_id' => $divisionId,
            'destination' => $destination,
            'month' => $month,
        ]);

        $divisionMap = $this->stock_opening_division_map();
        if ($divisionId <= 0 || empty($divisionMap[$divisionId])) {
            $this->session->set_flashdata('error', 'Pilih divisi lebih dulu untuk export template opening divisi.');
            redirect($backUrl);
            return;
        }

        $division = $divisionMap[$divisionId];
        $rows = [];
        foreach ($this->stock_opening_import_item_rows() as $item) {
            $rows[] = [
                'division_code' => (string)($division['code'] ?? ''),
                'division_name' => (string)($division['name'] ?? ''),
                'destination_type' => $destination,
                'snapshot_month' => $month,
                'item_id' => (int)($item['id'] ?? 0),
                'item_code' => (string)($item['item_code'] ?? ''),
                'item_name' => (string)($item['item_name'] ?? ''),
                'material_id' => (int)($item['material_id'] ?? 0),
                'material_code' => (string)($item['material_code'] ?? ''),
                'material_name' => (string)($item['material_name'] ?? ''),
                'buy_uom_code' => (string)($item['buy_uom_code'] ?? ''),
                'content_uom_code' => (string)($item['content_uom_code'] ?? ''),
                'profile_content_per_buy' => (string)($item['content_per_buy'] ?? '1'),
                'opening_qty_buy' => '',
                'opening_qty_content' => '',
                'opening_avg_cost_per_content' => '',
                'profile_name' => (string)($item['item_name'] ?? ''),
                'profile_brand' => '',
                'profile_description' => '',
                'profile_expired_date' => '',
                'replace_mode' => '1',
                'notes' => '',
            ];
        }

        $filename = 'opening-division-template-' . strtolower((string)($division['code'] ?? ('division-' . $divisionId))) . '-' . $month . '.xlsx';
        $headers = [
            'division_code', 'division_name', 'destination_type', 'snapshot_month',
            'item_id', 'item_code', 'item_name', 'material_id', 'material_code', 'material_name',
            'buy_uom_code', 'content_uom_code', 'profile_content_per_buy',
            'opening_qty_buy', 'opening_qty_content', 'opening_avg_cost_per_content',
            'profile_name', 'profile_brand', 'profile_description', 'profile_expired_date',
            'replace_mode', 'notes',
        ];

        $this->load->library('SimpleSpreadsheetIO');
        $this->simplespreadsheetio->output_xlsx($filename, $headers, $rows, 'Template Opening');
    }

    public function stock_opening_division_export_existing()
    {
        if (!$this->can(self::PAGE_STOCK_DIVISION, 'view') && !$this->can(self::PAGE_STOCK_DIVISION, 'export')) {
            $this->require_permission(self::PAGE_STOCK_DIVISION, 'view');
        }

        $month = trim((string)$this->input->get('month', true));
        $q = trim((string)$this->input->get('q', true));
        $divisionId = (int)$this->input->get('division_id', true);
        $destination = strtoupper(trim((string)$this->input->get('destination', true)));
        if ($destination === '') {
            $destination = 'ALL';
        }

        $rows = $this->Purchase_model->list_stock_opening_snapshots('DIVISION', $month, $q, 2000, $divisionId > 0 ? $divisionId : null, $destination);
        $exportRows = [];
        foreach ($rows as $row) {
            $exportRows[] = [
                'snapshot_month' => (string)($row['snapshot_month'] ?? ''),
                'division_code' => (string)($row['division_code'] ?? ''),
                'division_name' => (string)($row['division_name'] ?? ''),
                'destination_type' => (string)($row['destination_type'] ?? ''),
                'item_id' => (string)($row['item_id'] ?? ''),
                'item_code' => (string)($row['item_code'] ?? ''),
                'item_name' => (string)($row['item_name'] ?? ''),
                'material_id' => (string)($row['material_id'] ?? ''),
                'material_code' => (string)($row['material_code'] ?? ''),
                'material_name' => (string)($row['material_name'] ?? ''),
                'profile_name' => (string)($row['profile_name'] ?? ''),
                'profile_brand' => (string)($row['profile_brand'] ?? ''),
                'profile_description' => (string)($row['profile_description'] ?? ''),
                'buy_uom_code' => (string)($row['buy_uom_code'] ?? ''),
                'content_uom_code' => (string)($row['content_uom_code'] ?? ''),
                'profile_content_per_buy' => (string)($row['profile_content_per_buy'] ?? ''),
                'opening_qty_buy' => (string)($row['opening_qty_buy'] ?? ''),
                'opening_qty_content' => (string)($row['opening_qty_content'] ?? ''),
                'opening_avg_cost_per_content' => (string)($row['opening_avg_cost_per_content'] ?? ''),
                'opening_total_value' => (string)($row['opening_total_value'] ?? ''),
                'source_type' => (string)($row['source_type'] ?? ''),
                'notes' => (string)($row['notes'] ?? ''),
            ];
        }

        $headers = [
            'snapshot_month', 'division_code', 'division_name', 'destination_type',
            'item_id', 'item_code', 'item_name', 'material_id', 'material_code', 'material_name',
            'profile_name', 'profile_brand', 'profile_description',
            'buy_uom_code', 'content_uom_code', 'profile_content_per_buy',
            'opening_qty_buy', 'opening_qty_content', 'opening_avg_cost_per_content', 'opening_total_value',
            'source_type', 'notes',
        ];
        $filename = 'opening-division-existing-' . ($month !== '' ? preg_replace('/[^0-9\-]/', '', $month) : date('Y-m')) . '.xlsx';

        $this->load->library('SimpleSpreadsheetIO');
        $this->simplespreadsheetio->output_xlsx($filename, $headers, $exportRows, 'Opening Existing');
    }

    public function stock_opening_division_import()
    {
        $this->require_permission(self::PAGE_STOCK_DIVISION, 'create');

        $defaultDivisionId = (int)$this->input->post('division_id', true);
        $defaultDestination = $this->stock_opening_import_destination((string)$this->input->post('destination', true));
        $defaultMonth = $this->stock_opening_import_month((string)$this->input->post('month', true));
        $backUrl = $this->stock_opening_division_redirect_url([
            'division_id' => $defaultDivisionId,
            'destination' => $defaultDestination,
            'month' => $defaultMonth,
        ]);

        $this->load->library('SimpleSpreadsheetIO');
        $parsed = $this->simplespreadsheetio->parse_uploaded_file('import_file');
        if (!($parsed['ok'] ?? false)) {
            $this->session->set_flashdata('error', (string)($parsed['message'] ?? 'File import opening divisi tidak valid.'));
            redirect($backUrl);
            return;
        }

        $divisionMap = $this->stock_opening_division_map();
        $uomMap = $this->stock_opening_uom_map();
        $itemMaps = $this->stock_opening_item_lookup_maps();
        $successCount = 0;
        $skippedCount = 0;
        $errors = [];
        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;

        try {
            foreach ((array)($parsed['rows'] ?? []) as $index => $row) {
                $rowNumber = $index + 2;
                $qtyBuy = $this->stock_opening_import_decimal($this->stock_opening_row_value($row, ['opening_qty_buy', 'qty_buy'], ''));
                if ($qtyBuy <= 0) {
                    $skippedCount++;
                    continue;
                }

                $resolved = $this->stock_opening_import_payload_from_row($row, $defaultDivisionId, $defaultDestination, $defaultMonth, $divisionMap, $uomMap, $itemMaps);
                if (!($resolved['ok'] ?? false)) {
                    $errors[] = 'Baris ' . $rowNumber . ': ' . (string)($resolved['message'] ?? 'Data tidak valid.');
                    continue;
                }

                $result = $this->Purchase_model->store_warehouse_opening_and_post(
                    (array)$resolved['payload'],
                    (int)($this->current_user['id'] ?? 0),
                    (string)$this->input->ip_address()
                );
                if (!($result['ok'] ?? false)) {
                    $errors[] = 'Baris ' . $rowNumber . ': ' . (string)($result['message'] ?? 'Gagal menyimpan opening.');
                    continue;
                }

                $successCount++;
            }
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        $summary = 'Import opening divisi selesai. Berhasil ' . $successCount . ' baris';
        if ($skippedCount > 0) {
            $summary .= ', dilewati ' . $skippedCount . ' baris kosong/qty 0';
        }
        if (!empty($errors)) {
            $summary .= ', gagal ' . count($errors) . ' baris. ' . implode(' | ', array_slice($errors, 0, 5));
        }

        if ($successCount > 0 && empty($errors)) {
            $this->session->set_flashdata('success', $summary . '.');
        } elseif ($successCount > 0) {
            $this->session->set_flashdata('warning', $summary);
        } else {
            $this->session->set_flashdata('error', $summary);
        }

        redirect($backUrl);
    }

    private function stock_opening_division_redirect_url(array $state = []): string
    {
        $query = [
            'month' => $this->stock_opening_import_month((string)($state['month'] ?? date('Y-m'))),
            'division_id' => (int)($state['division_id'] ?? 0),
            'destination' => $this->stock_opening_import_destination((string)($state['destination'] ?? 'ALL')),
        ];

        return site_url('inventory/stock/opening/division') . '?' . http_build_query($query);
    }

    private function stock_opening_division_map(): array
    {
        $rows = $this->Purchase_model->list_active_operational_divisions();
        $map = [];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $map[$id] = $row;
                $code = strtoupper(trim((string)($row['code'] ?? '')));
                if ($code !== '') {
                    $map['CODE:' . $code] = $row;
                }
            }
        }
        return $map;
    }

    private function stock_opening_uom_map(): array
    {
        $rows = $this->Purchase_model->list_active_uoms();
        $map = [];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $map[$id] = $row;
                $code = strtoupper(trim((string)($row['code'] ?? '')));
                if ($code !== '') {
                    $map['CODE:' . $code] = $row;
                }
            }
        }
        return $map;
    }

    private function stock_opening_item_lookup_maps(): array
    {
        $rows = $this->stock_opening_import_item_rows();
        $maps = [
            'rows' => $rows,
            'id' => [],
            'item_code' => [],
            'material_id' => [],
            'material_code' => [],
        ];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $maps['id'][$id] = $row;
            }
            $itemCode = strtoupper(trim((string)($row['item_code'] ?? '')));
            if ($itemCode !== '') {
                $maps['item_code'][$itemCode] = $row;
            }
            $materialId = (int)($row['material_id'] ?? 0);
            if ($materialId > 0 && empty($maps['material_id'][$materialId])) {
                $maps['material_id'][$materialId] = $row;
            }
            $materialCode = strtoupper(trim((string)($row['material_code'] ?? '')));
            if ($materialCode !== '' && empty($maps['material_code'][$materialCode])) {
                $maps['material_code'][$materialCode] = $row;
            }
        }

        return $maps;
    }

    private function stock_opening_import_item_rows(): array
    {
        if (!$this->db->table_exists('mst_item')) {
            return [];
        }

        $hasMaterial = $this->db->table_exists('mst_material') && $this->db->field_exists('material_id', 'mst_item');
        $hasUom = $this->db->table_exists('mst_uom');
        $buyUomField = $this->db->field_exists('buy_uom_id', 'mst_item') ? 'i.buy_uom_id' : ($this->db->field_exists('base_uom_id', 'mst_item') ? 'i.base_uom_id' : 'NULL');
        $contentUomField = $this->db->field_exists('content_uom_id', 'mst_item') ? 'i.content_uom_id' : ($this->db->field_exists('base_uom_id', 'mst_item') ? 'i.base_uom_id' : 'NULL');
        $contentPerBuyField = $this->db->field_exists('content_per_buy', 'mst_item') ? 'COALESCE(i.content_per_buy, 1)' : '1';

        $this->db
            ->select('i.id, i.item_code, i.item_name')
            ->select($buyUomField . ' AS buy_uom_id', false)
            ->select($contentUomField . ' AS content_uom_id', false)
            ->select($contentPerBuyField . ' AS content_per_buy', false)
            ->from('mst_item i')
            ->where('i.is_active', 1)
            ->order_by('i.item_name', 'ASC');

        if ($hasMaterial) {
            $this->db->select('m.id AS material_id, m.material_code, m.material_name');
            $this->db->join('mst_material m', 'm.id = i.material_id', 'left');
        } else {
            $this->db->select('NULL AS material_id, NULL AS material_code, NULL AS material_name', false);
        }

        if ($hasUom && $buyUomField !== 'NULL') {
            $this->db->select('bu.code AS buy_uom_code');
            $this->db->join('mst_uom bu', 'bu.id = ' . $buyUomField, 'left', false);
        } else {
            $this->db->select('NULL AS buy_uom_code', false);
        }

        if ($hasUom && $contentUomField !== 'NULL') {
            $this->db->select('cu.code AS content_uom_code');
            $this->db->join('mst_uom cu', 'cu.id = ' . $contentUomField, 'left', false);
        } else {
            $this->db->select('NULL AS content_uom_code', false);
        }

        return $this->db->get()->result_array();
    }

    private function stock_opening_import_payload_from_row(array $row, int $defaultDivisionId, string $defaultDestination, string $defaultMonth, array $divisionMap, array $uomMap, array $itemMaps): array
    {
        $divisionCode = strtoupper(trim((string)$this->stock_opening_row_value($row, ['division_code'], '')));
        $divisionId = (int)$this->stock_opening_row_value($row, ['division_id'], $defaultDivisionId);
        if ($divisionId <= 0 && $divisionCode !== '' && !empty($divisionMap['CODE:' . $divisionCode])) {
            $divisionId = (int)($divisionMap['CODE:' . $divisionCode]['id'] ?? 0);
        }
        if ($divisionId <= 0 || empty($divisionMap[$divisionId])) {
            return ['ok' => false, 'message' => 'Divisi tidak valid.'];
        }

        $destination = $this->stock_opening_import_destination((string)$this->stock_opening_row_value($row, ['destination_type'], $defaultDestination));
        $month = $this->stock_opening_import_month((string)$this->stock_opening_row_value($row, ['snapshot_month', 'opening_month'], $defaultMonth));

        $item = null;
        $itemId = (int)$this->stock_opening_row_value($row, ['item_id'], 0);
        if ($itemId > 0 && !empty($itemMaps['id'][$itemId])) {
            $item = $itemMaps['id'][$itemId];
        }
        if ($item === null) {
            $itemCode = strtoupper(trim((string)$this->stock_opening_row_value($row, ['item_code'], '')));
            if ($itemCode !== '' && !empty($itemMaps['item_code'][$itemCode])) {
                $item = $itemMaps['item_code'][$itemCode];
            }
        }
        if ($item === null) {
            $materialId = (int)$this->stock_opening_row_value($row, ['material_id'], 0);
            if ($materialId > 0 && !empty($itemMaps['material_id'][$materialId])) {
                $item = $itemMaps['material_id'][$materialId];
            }
        }
        if ($item === null) {
            $materialCode = strtoupper(trim((string)$this->stock_opening_row_value($row, ['material_code'], '')));
            if ($materialCode !== '' && !empty($itemMaps['material_code'][$materialCode])) {
                $item = $itemMaps['material_code'][$materialCode];
            }
        }
        if ($item === null) {
            return ['ok' => false, 'message' => 'Item atau material tidak ditemukan.'];
        }

        $buyUomId = $this->stock_opening_import_uom_id($this->stock_opening_row_value($row, ['buy_uom_id', 'buy_uom_code'], ''), $uomMap, (int)($item['buy_uom_id'] ?? 0));
        $contentUomId = $this->stock_opening_import_uom_id($this->stock_opening_row_value($row, ['content_uom_id', 'content_uom_code'], ''), $uomMap, (int)($item['content_uom_id'] ?? 0));
        if ($buyUomId <= 0 || $contentUomId <= 0) {
            return ['ok' => false, 'message' => 'UOM beli/isi tidak valid.'];
        }

        $qtyBuy = round($this->stock_opening_import_decimal($this->stock_opening_row_value($row, ['opening_qty_buy', 'qty_buy'], '0')), 4);
        $ratio = round(max(0.000001, $this->stock_opening_import_decimal($this->stock_opening_row_value($row, ['profile_content_per_buy', 'content_per_buy'], (string)($item['content_per_buy'] ?? '1')))), 6);
        $qtyContent = round($this->stock_opening_import_decimal($this->stock_opening_row_value($row, ['opening_qty_content', 'qty_content'], '0')), 4);
        if ($qtyContent <= 0) {
            $qtyContent = round($qtyBuy * $ratio, 4);
        }

        $payload = [
            'stock_scope' => 'DIVISION',
            'stock_domain' => ((int)($item['id'] ?? 0) > 0) ? 'ITEM' : ((((int)($item['material_id'] ?? 0) > 0) ? 'MATERIAL' : 'ITEM')),
            'division_id' => $divisionId,
            'destination_type' => $destination,
            'snapshot_month' => $month,
            'movement_date' => $month . '-01',
            'item_id' => (int)($item['id'] ?? 0),
            'material_id' => (int)($item['material_id'] ?? 0),
            'buy_uom_id' => $buyUomId,
            'content_uom_id' => $contentUomId,
            'opening_qty_buy' => $qtyBuy,
            'opening_qty_content' => $qtyContent,
            'opening_avg_cost_per_content' => round($this->stock_opening_import_decimal($this->stock_opening_row_value($row, ['opening_avg_cost_per_content', 'avg_cost', 'unit_cost'], '0')), 6),
            'profile_name' => (string)$this->stock_opening_row_value($row, ['profile_name'], (string)($item['item_name'] ?? '')),
            'profile_brand' => (string)$this->stock_opening_row_value($row, ['profile_brand'], ''),
            'profile_description' => (string)$this->stock_opening_row_value($row, ['profile_description'], ''),
            'profile_expired_date' => $this->stock_opening_import_date((string)$this->stock_opening_row_value($row, ['profile_expired_date'], '')),
            'profile_content_per_buy' => $ratio,
            'replace_mode' => (int)$this->stock_opening_row_value($row, ['replace_mode'], '1') === 1 ? 1 : 0,
            'notes' => (string)$this->stock_opening_row_value($row, ['notes', 'note'], ''),
        ];

        return ['ok' => true, 'payload' => $payload];
    }

    private function stock_opening_import_uom_id($rawValue, array $uomMap, int $fallbackId): int
    {
        $value = trim((string)$rawValue);
        if ($value === '') {
            return $fallbackId;
        }

        $numeric = (int)$value;
        if ($numeric > 0 && !empty($uomMap[$numeric])) {
            return $numeric;
        }

        $code = strtoupper($value);
        if (!empty($uomMap['CODE:' . $code])) {
            return (int)($uomMap['CODE:' . $code]['id'] ?? 0);
        }

        return $fallbackId;
    }

    private function stock_opening_row_value(array $row, array $keys, $default = '')
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && trim((string)$row[$key]) !== '') {
                return $row[$key];
            }
        }
        return $default;
    }

    private function stock_opening_import_month(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return date('Y-m');
        }
        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            return $value;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return substr($value, 0, 7);
        }
        if (preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            $date = $this->stock_opening_excel_serial_to_date((float)$value);
            if ($date !== null) {
                return substr($date, 0, 7);
            }
        }
        $time = strtotime($value);
        return $time ? date('Y-m', $time) : date('Y-m');
    }

    private function stock_opening_import_date(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        if (preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            return $this->stock_opening_excel_serial_to_date((float)$value);
        }
        $time = strtotime($value);
        return $time ? date('Y-m-d', $time) : null;
    }

    private function stock_opening_import_destination(string $value): string
    {
        $value = strtoupper(trim($value));
        if (in_array($value, ['BAR', 'KITCHEN', 'BAR_EVENT', 'KITCHEN_EVENT', 'OFFICE', 'OTHER'], true)) {
            return $value;
        }
        return 'OTHER';
    }

    private function stock_opening_import_decimal($value): float
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return 0.0;
        }
        $raw = str_replace(' ', '', $raw);
        if (strpos($raw, ',') !== false && strpos($raw, '.') !== false) {
            if (strrpos($raw, ',') > strrpos($raw, '.')) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif (strpos($raw, ',') !== false) {
            $raw = str_replace(',', '.', $raw);
        }
        return (float)$raw;
    }

    private function stock_opening_excel_serial_to_date(float $serial): ?string
    {
        if ($serial <= 0) {
            return null;
        }
        $days = (int)floor($serial) - 25569;
        if ($days <= 0) {
            return null;
        }
        return gmdate('Y-m-d', $days * 86400);
    }

    public function stock_opening_item_search()
    {
        $canWarehouse = $this->can(self::PAGE_STOCK_WAREHOUSE, 'view') || $this->can(self::PAGE_STOCK_WAREHOUSE, 'create');
        $canDivision = $this->can(self::PAGE_STOCK_DIVISION, 'view') || $this->can(self::PAGE_STOCK_DIVISION, 'create');
        if (!$canWarehouse && !$canDivision) {
            $this->jsonError('Anda tidak memiliki izin untuk pencarian item opening.', 403);
            return;
        }

        $q = trim((string)$this->input->get('q', true));
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 50) {
            $limit = 20;
        }

        $rows = $this->Purchase_model->search_opening_items($q, $limit);
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => true,
                'items' => $rows,
                'meta' => [
                    'q' => $q,
                    'limit' => $limit,
                    'count' => count($rows),
                ],
            ]));
    }

    public function stock_opening_store()
    {
        if (!$this->can(self::PAGE_STOCK_WAREHOUSE, 'create') && !$this->can(self::PAGE_STOCK_DIVISION, 'create')) {
            $this->require_permission(self::PAGE_ORDER, 'create');
        }

        $payload = $this->requestPayload();
        unset($payload['adjustment_category'], $payload['adjustment_reason_code']);
        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Purchase_model->store_warehouse_opening_and_post(
                $payload,
                (int)($this->current_user['id'] ?? 0),
                (string)$this->input->ip_address()
            );
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal menyimpan opening gudang.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function stock_opening_void($id)
    {
        $payload = $this->requestPayload();
        $scope = strtoupper(trim((string)($payload['stock_scope'] ?? 'DIVISION')));
        if ($scope === 'DIVISION') {
            $this->require_permission(self::PAGE_STOCK_DIVISION, 'delete');
        } else {
            $this->require_permission(self::PAGE_STOCK_WAREHOUSE, 'delete');
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Purchase_model->void_stock_opening_snapshot(
                $scope,
                (int)$id,
                (int)($this->current_user['id'] ?? 0),
                (string)$this->input->ip_address()
            );
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal VOID opening snapshot.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function stock_adjustment_index()
    {
        redirect('inventory/stock/adjustment/warehouse');
    }

    public function stock_adjustment_warehouse_index()
    {
        $this->require_permission(self::PAGE_STOCK_ADJUSTMENT_WAREHOUSE, 'view');

        $month = trim((string)$this->input->get('month', true));
        $q = trim((string)$this->input->get('q', true));
        $activeTab = strtolower(trim((string)$this->input->get('tab', true)));
        if (!in_array($activeTab, ['input', 'rincian'], true)) {
            $activeTab = 'input';
        }
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 500) {
            $limit = 200;
        }

        $this->render('purchase/stock_adjustment_index', [
            'title' => 'Adjustment Stok Gudang',
            'active_menu' => 'purchase.stock.adjustment.warehouse',
            'stock_scope' => 'WAREHOUSE',
            'is_division_scope' => false,
            'base_url_adjustment' => 'inventory/stock/adjustment/warehouse',
            'active_tab' => $activeTab,
            'month' => $month,
            'q' => $q,
            'division_id' => 0,
            'destination' => 'ALL',
            'limit' => $limit,
            'rows' => $this->Purchase_model->list_stock_adjustments('WAREHOUSE', $month, $q, $limit, null, null),
            'line_rows' => $activeTab === 'rincian'
                ? $this->Purchase_model->list_stock_adjustment_detail_rows('WAREHOUSE', $month, $q, $limit, null, null)
                : [],
            'divisions' => [],
            'destination_guard_map' => [],
        ]);
    }

    public function stock_adjustment_division_index()
    {
        $this->require_permission(self::PAGE_STOCK_ADJUSTMENT_DIVISION, 'view');

        $month      = trim((string)$this->input->get('month', true));
        $q          = trim((string)$this->input->get('q', true));
        $dateFrom   = trim((string)$this->input->get('date_from', true));
        $dateTo     = trim((string)$this->input->get('date_to', true));
        $page       = max(1, (int)$this->input->get('page', true));
        $activeTab  = strtolower(trim((string)$this->input->get('tab', true)));
        if (!in_array($activeTab, ['input', 'rincian'], true)) {
            $activeTab = 'input';
        }
        $divisionId  = (int)$this->input->get('division_id', true);
        $destination = strtoupper(trim((string)$this->input->get('destination', true)));
        if ($destination === '') {
            $destination = 'ALL';
        }
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 500) {
            $limit = 25;
        }

        // Validate date params
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) !== 1) { $dateFrom = ''; }
        if ($dateTo   !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)   !== 1) { $dateTo   = ''; }

        // Default to current month when no date filter supplied
        if ($dateFrom === '' && $dateTo === '' && $month === '') {
            $dateFrom = date('Y-m-01');
            $dateTo   = date('Y-m-d');
        }

        $divisions           = $this->Purchase_model->list_active_operational_divisions();
        $destinationGuardMap = $this->buildDivisionDestinationGuardMap($divisions);
        $destination         = $this->normalizeDestinationForDivisionFilter($destination, $divisionId, $destinationGuardMap);

        $this->render('purchase/stock_adjustment_index', [
            'title'               => 'Adjustment Bahan Baku',
            'active_menu'         => 'purchase.stock.adjustment.division',
            'stock_scope'         => 'DIVISION',
            'is_division_scope'   => true,
            'base_url_adjustment' => 'inventory/stock/adjustment/division',
            'active_tab'          => $activeTab,
            'month'               => $month,
            'date_from'           => $dateFrom,
            'date_to'             => $dateTo,
            'q'                   => $q,
            'division_id'         => $divisionId,
            'destination'         => $destination,
            'limit'               => $limit,
            'page'                => $page,
            'rows'                => $this->Purchase_model->list_stock_adjustments('DIVISION', $month, $q, 500, $divisionId > 0 ? $divisionId : null, $destination, $dateFrom, $dateTo),
            'line_rows'           => $activeTab === 'rincian'
                ? $this->Purchase_model->list_stock_adjustment_detail_rows('DIVISION', $month, $q, 500, $divisionId > 0 ? $divisionId : null, $destination, $dateFrom, $dateTo)
                : [],
            'divisions'           => $divisions,
            'destination_guard_map' => $destinationGuardMap,
        ]);
    }

    public function stock_adjustment_item_search()
    {
        $canWarehouse = $this->can(self::PAGE_STOCK_ADJUSTMENT_WAREHOUSE, 'view') || $this->can(self::PAGE_STOCK_ADJUSTMENT_WAREHOUSE, 'create');
        $canDivision = $this->can(self::PAGE_STOCK_ADJUSTMENT_DIVISION, 'view') || $this->can(self::PAGE_STOCK_ADJUSTMENT_DIVISION, 'create');
        if (!$canWarehouse && !$canDivision) {
            $this->jsonError('Anda tidak memiliki izin untuk pencarian item adjustment.', 403);
            return;
        }

        $q = trim((string)$this->input->get('q', true));
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 50) {
            $limit = 20;
        }

        $scope = strtoupper(trim((string)$this->input->get('stock_scope', true)));
        if (!in_array($scope, ['WAREHOUSE', 'DIVISION'], true)) {
            $scope = 'WAREHOUSE';
        }

        $rows = $this->Purchase_model->search_stock_adjustment_items([
            'stock_scope' => $scope,
            'division_id' => (int)$this->input->get('division_id', true),
            'destination_type' => (string)$this->input->get('destination', true),
        ], $q, $limit);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => true,
                'items' => $rows,
                'meta' => [
                    'q' => $q,
                    'limit' => $limit,
                    'count' => count($rows),
                ],
            ]));
    }

    public function stock_adjustment_store()
    {
        $payload = $this->requestPayload();
        $scope = strtoupper(trim((string)($payload['stock_scope'] ?? 'WAREHOUSE')));
        $autoPost = !empty($payload['auto_post']);
        if ($scope === 'DIVISION') {
            $this->require_permission(self::PAGE_STOCK_ADJUSTMENT_DIVISION, 'create');
        } else {
            $this->require_permission(self::PAGE_STOCK_ADJUSTMENT_WAREHOUSE, 'create');
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Purchase_model->save_stock_adjustment([
                'id' => (int)($payload['id'] ?? 0),
                'adjustment_no' => (string)($payload['adjustment_no'] ?? ''),
                'adjustment_date' => (string)($payload['adjustment_date'] ?? date('Y-m-d')),
                'stock_scope' => $scope,
                'division_id' => !empty($payload['division_id']) ? (int)$payload['division_id'] : null,
                'destination_type' => (string)($payload['destination_type'] ?? ''),
                'notes' => (string)($payload['notes'] ?? ''),
            ], (array)($payload['lines'] ?? []), (int)($this->current_user['id'] ?? 0));
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal menyimpan adjustment stok.'), 422);
            return;
        }

        if ($autoPost) {
            if ($scope === 'DIVISION') {
                $this->require_permission(self::PAGE_STOCK_ADJUSTMENT_DIVISION, 'edit');
            } else {
                $this->require_permission(self::PAGE_STOCK_ADJUSTMENT_WAREHOUSE, 'edit');
            }

            $adjustmentId = (int)($result['id'] ?? 0);
            $posted = null;
            $dbDebugBefore = (bool)$this->db->db_debug;
            $this->db->db_debug = false;
            try {
                $posted = $this->Purchase_model->post_stock_adjustment($adjustmentId, (int)($this->current_user['id'] ?? 0), (string)$this->input->ip_address());
                if (!($posted['ok'] ?? false) && $adjustmentId > 0) {
                    $this->Purchase_model->delete_draft_stock_adjustment($adjustmentId);
                }
            } finally {
                $this->db->db_debug = $dbDebugBefore;
            }

            if (!($posted['ok'] ?? false)) {
                $this->jsonError((string)($posted['message'] ?? 'Gagal posting adjustment stok.'), 422);
                return;
            }

            $result['posted'] = true;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function stock_adjustment_post($id)
    {
        $header = $this->Purchase_model->get_stock_adjustment((int)$id);
        if (!$header) {
            $this->jsonError('Adjustment tidak ditemukan.', 404);
            return;
        }

        if (strtoupper((string)($header['stock_scope'] ?? 'WAREHOUSE')) === 'DIVISION') {
            $this->require_permission(self::PAGE_STOCK_ADJUSTMENT_DIVISION, 'edit');
        } else {
            $this->require_permission(self::PAGE_STOCK_ADJUSTMENT_WAREHOUSE, 'edit');
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Purchase_model->post_stock_adjustment((int)$id, (int)($this->current_user['id'] ?? 0), (string)$this->input->ip_address());
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal posting adjustment stok.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function stock_adjustment_delete($id)
    {
        $header = $this->Purchase_model->get_stock_adjustment((int)$id);
        if (!$header) {
            $this->jsonError('Adjustment tidak ditemukan.', 404);
            return;
        }

        if (strtoupper((string)($header['stock_scope'] ?? 'WAREHOUSE')) === 'DIVISION') {
            $this->require_permission(self::PAGE_STOCK_ADJUSTMENT_DIVISION, 'delete');
        } else {
            $this->require_permission(self::PAGE_STOCK_ADJUSTMENT_WAREHOUSE, 'delete');
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Purchase_model->delete_draft_stock_adjustment((int)$id);
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal menghapus draft adjustment stok.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function stock_adjustment_void($id)
    {
        $header = $this->Purchase_model->get_stock_adjustment((int)$id);
        if (!$header) {
            $this->jsonError('Adjustment tidak ditemukan.', 404);
            return;
        }

        if (strtoupper((string)($header['stock_scope'] ?? 'WAREHOUSE')) === 'DIVISION') {
            $this->require_permission(self::PAGE_STOCK_ADJUSTMENT_DIVISION, 'delete');
        } else {
            $this->require_permission(self::PAGE_STOCK_ADJUSTMENT_WAREHOUSE, 'delete');
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Purchase_model->void_posted_stock_adjustment((int)$id, (int)($this->current_user['id'] ?? 0), (string)$this->input->ip_address());
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal VOID adjustment stok.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function stock_opname_generate()
    {
        if (!$this->can(self::PAGE_STOCK_WAREHOUSE, 'create') && !$this->can(self::PAGE_STOCK_DIVISION, 'create')) {
            $this->require_permission(self::PAGE_ORDER, 'create');
        }

        $payload = $this->requestPayload();
        if (empty($payload)) {
            $payload = [
                'stock_scope' => (string)$this->input->post('stock_scope', true),
                'month' => (string)$this->input->post('month', true),
                'division_id' => (int)$this->input->post('division_id', true),
                'destination' => (string)$this->input->post('destination', true),
            ];
        }

        $result = $this->Purchase_model->generate_monthly_opname_and_opening(
            $payload,
            (int)($this->current_user['id'] ?? 0),
            (string)$this->input->ip_address()
        );

        $acceptHeader = strtolower((string)$this->input->server('HTTP_ACCEPT'));
        $contentType = strtolower((string)$this->input->server('CONTENT_TYPE'));
        $isJsonRequest = strpos($acceptHeader, 'application/json') !== false
            || strpos($contentType, 'application/json') !== false;

        if ($isJsonRequest) {
            if (!($result['ok'] ?? false)) {
                $this->jsonError((string)($result['message'] ?? 'Gagal generate opname.'), 422);
                return;
            }

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($result));
            return;
        }

        if (!($result['ok'] ?? false)) {
            $this->session->set_flashdata('error', (string)($result['message'] ?? 'Gagal generate opname.'));
        } else {
            $this->session->set_flashdata('success', (string)($result['message'] ?? 'Generate opname berhasil.'));
        }

        $backUrl = (string)$this->input->post('back_url', true);
        if ($backUrl !== '') {
            redirect($backUrl);
            return;
        }

        $scope = strtoupper(trim((string)($payload['stock_scope'] ?? 'WAREHOUSE')));
        if ($scope === 'DIVISION') {
            redirect('inventory/stock/division/daily?month=' . date('Y-m', strtotime((string)($payload['month'] ?? date('Y-m-01')))));
            return;
        }

        redirect('inventory/stock/warehouse/daily?month=' . date('Y-m', strtotime((string)($payload['month'] ?? date('Y-m-01')))));
    }

    public function stock_warehouse_daily_index()
    {
        if (!$this->can(self::PAGE_STOCK_WAREHOUSE, 'view')) {
            $this->require_permission(self::PAGE_ORDER, 'view');
        }

        $month = trim((string)$this->input->get('month', true));
        $q = trim((string)$this->input->get('q', true));
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo = trim((string)$this->input->get('date_to', true));
        $range = $this->resolveDateRange($month, $dateFrom, $dateTo);
        $month = $range['month'];
        $dateFrom = $range['date_from'];
        $dateTo = $range['date_to'];
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 1000) {
            $limit = 300;
        }

        $data = [
            'title' => 'Stok Bulanan / Snapshot Harian Gudang',
            'active_menu' => 'purchase.stock.warehouse',
            'month' => $month,
            'q' => $q,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'limit' => $limit,
            'rows' => $this->Purchase_model->list_warehouse_daily_snapshot($month, $q, $dateFrom, $dateTo, $limit),
        ];

        $this->render('purchase/stock_warehouse_daily_index', $data);
    }

    public function stock_warehouse_daily_matrix()
    {
        if (!$this->can(self::PAGE_STOCK_WAREHOUSE, 'view') && !$this->can(self::PAGE_STOCK_WAREHOUSE_MATRIX, 'view')) {
            $this->require_permission(self::PAGE_ORDER, 'view');
        }

        $month = trim((string)$this->input->get('month', true));
        $q = trim((string)$this->input->get('q', true));
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo = trim((string)$this->input->get('date_to', true));
        $range = $this->resolveDateRange($month, $dateFrom, $dateTo);
        $month = $range['month'];
        $dateFrom = $range['date_from'];
        $dateTo = $range['date_to'];
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 1000) {
            $limit = 120;
        }

        $matrix = $this->Purchase_model->list_warehouse_daily_matrix($month, $q, $dateFrom, $dateTo, $limit);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => true,
                'data' => $matrix,
            ]));
    }

    public function stock_warehouse_movement_index()
    {
        if (!$this->can(self::PAGE_STOCK_WAREHOUSE, 'view')) {
            $this->require_permission(self::PAGE_ORDER, 'view');
        }

        $q = trim((string)$this->input->get('q', true));
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo = trim((string)$this->input->get('date_to', true));
        $range = $this->resolveDateRange('', $dateFrom, $dateTo);
        $dateFrom = $range['date_from'];
        $dateTo = $range['date_to'];
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 1000) {
            $limit = 300;
        }

        $data = [
            'title' => 'Keluar Masuk Stok Gudang',
            'active_menu' => 'purchase.stock.warehouse',
            'q' => $q,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'limit' => $limit,
            'rows' => $this->Purchase_model->list_stock_movements('WAREHOUSE', $q, $dateFrom, $dateTo, null, $limit),
        ];

        $this->render('purchase/stock_warehouse_movement_index', $data);
    }

    public function stock_division_index()
    {
        if (!$this->can(self::PAGE_STOCK_DIVISION, 'view')) {
            $this->require_permission(self::PAGE_ORDER, 'view');
        }

        $divisions = $this->Purchase_model->list_active_operational_divisions();
        $destinationGuardMap = $this->buildDivisionDestinationGuardMap($divisions);
        $q = trim((string)$this->input->get('q', true));
        $destinationFilter = strtoupper(trim((string)$this->input->get('destination', true)));
        if ($destinationFilter === '') {
            $destinationFilter = 'ALL';
        }
        $divisionId = (int)$this->input->get('division_id', true);
        $destinationFilter = $this->normalizeDestinationForDivisionFilter($destinationFilter, $divisionId, $destinationGuardMap);
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo = trim((string)$this->input->get('date_to', true));
        $range = $this->resolveDateRange('', $dateFrom, $dateTo);
        $dateFrom = $range['date_from'];
        $dateTo = $range['date_to'];
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 500) {
            $limit = 100;
        }
        $page = max(1, (int)$this->input->get('page', true));

        $data = [
            'title' => 'Stok Bahan Baku Live',
            'active_menu' => 'purchase.stock.division',
            'q' => $q,
            'destination' => $destinationFilter,
            'division_id' => $divisionId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'limit' => $limit,
            'page' => $page,
            'divisions' => $divisions,
            'destination_guard_map' => $destinationGuardMap,
            'rows' => $this->Purchase_model->list_division_stock($q, 2000, $destinationFilter, $dateFrom, $dateTo, $divisionId > 0 ? $divisionId : null),
        ];

        $this->render('purchase/stock_division_index', $data);
    }

    public function stock_division_movement_index()
    {
        if (!$this->can(self::PAGE_STOCK_DIVISION, 'view')) {
            $this->require_permission(self::PAGE_ORDER, 'view');
        }

        $q            = trim((string)$this->input->get('q', true));
        $dateFrom     = trim((string)$this->input->get('date_from', true));
        $dateTo       = trim((string)$this->input->get('date_to', true));
        $range        = $this->resolveDateRange('', $dateFrom, $dateTo);
        $dateFrom     = $range['date_from'];
        $dateTo       = $range['date_to'];
        $divisionId   = (int)$this->input->get('division_id', true);
        $destinationFilter = strtoupper(trim((string)$this->input->get('destination', true)));
        if ($destinationFilter === '') { $destinationFilter = 'ALL'; }
        $perPage = (int)$this->input->get('per_page', true);
        if ($perPage < 10 || $perPage > 200) { $perPage = 25; }
        $page = max(1, (int)$this->input->get('page', true));

        $data = [
            'title'        => 'Mutasi Bahan Baku',
            'active_menu'  => 'purchase.stock.division',
            'q'            => $q,
            'date_from'    => $dateFrom,
            'date_to'      => $dateTo,
            'division_id'  => $divisionId,
            'destination'  => $destinationFilter,
            'per_page'     => $perPage,
            'page'         => $page,
            'divisions'    => $this->Purchase_model->list_active_operational_divisions(),
            'rows'         => $this->Purchase_model->list_stock_movements('DIVISION', $q, $dateFrom, $dateTo, $divisionId > 0 ? $divisionId : null, 500, $destinationFilter),
        ];

        $this->render('purchase/stock_division_movement_index', $data);
    }

    public function stock_division_daily_index()
    {
        if (!$this->can(self::PAGE_STOCK_DIVISION, 'view')) {
            $this->require_permission(self::PAGE_ORDER, 'view');
        }

        $divisions = $this->Purchase_model->list_active_operational_divisions();
        $destinationGuardMap = $this->buildDivisionDestinationGuardMap($divisions);
        $month = trim((string)$this->input->get('month', true));
        $q = trim((string)$this->input->get('q', true));
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo = trim((string)$this->input->get('date_to', true));
        $range = $this->resolveDateRange($month, $dateFrom, $dateTo);
        $month = $range['month'];
        $dateFrom = $range['date_from'];
        $dateTo = $range['date_to'];
        $divisionId = (int)$this->input->get('division_id', true);
        $destinationFilter = strtoupper(trim((string)$this->input->get('destination', true)));
        if ($destinationFilter === '') {
            $destinationFilter = 'ALL';
        }
        $destinationFilter = $this->normalizeDestinationForDivisionFilter($destinationFilter, $divisionId, $destinationGuardMap);
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 500) {
            $limit = 100;
        }
        $page = max(1, (int)$this->input->get('page', true));

        $data = [
            'title' => 'Stok Bahan Baku Bulanan',
            'active_menu' => 'purchase.stock.division',
            'month' => $month,
            'q' => $q,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'division_id' => $divisionId,
            'destination' => $destinationFilter,
            'limit' => $limit,
            'page' => $page,
            'divisions' => $divisions,
            'destination_guard_map' => $destinationGuardMap,
            'rows' => $this->Purchase_model->list_division_daily_snapshot($month, $q, $divisionId > 0 ? $divisionId : null, $dateFrom, $dateTo, 2000, $destinationFilter),
        ];

        $this->render('purchase/stock_division_daily_index', $data);
    }

    public function stock_division_reconcile_index()
    {
        if (!$this->can(self::PAGE_STOCK_DIVISION, 'view')) {
            $this->require_permission(self::PAGE_ORDER, 'view');
        }

        $divisions           = $this->Purchase_model->list_active_operational_divisions();
        $destinationGuardMap = $this->buildDivisionDestinationGuardMap($divisions);
        $asOfDate            = trim((string)$this->input->get('as_of_date', true));
        if ($asOfDate === '') {
            $asOfDate = date('Y-m-d');
        }
        $q                  = trim((string)$this->input->get('q', true));
        $divisionId         = (int)$this->input->get('division_id', true);
        $destinationFilter  = strtoupper(trim((string)$this->input->get('destination', true)));
        if ($destinationFilter === '') {
            $destinationFilter = 'ALL';
        }
        $destinationFilter = $this->normalizeDestinationForDivisionFilter($destinationFilter, $divisionId, $destinationGuardMap);
        $perPage           = (int)$this->input->get('per_page', true);
        if ($perPage < 10 || $perPage > 200) {
            $perPage = 25;
        }
        $page = max(1, (int)$this->input->get('page', true));

        $compare = $this->Purchase_model->list_division_material_stock_compare(
            $asOfDate,
            $q,
            $divisionId > 0 ? $divisionId : null,
            500,
            $destinationFilter
        );

        $orphanStock = $this->Purchase_model->list_monthly_stock_no_material_id($divisionId > 0 ? $divisionId : null);

        $this->render('purchase/stock_division_reconcile_index', [
            'title'                => 'Rekonsiliasi Stok Divisi',
            'page_title'           => 'Rekonsiliasi Stok Divisi',
            'active_menu'          => 'purchase.stock.division',
            'as_of_date'           => $compare['as_of_date'] ?? $asOfDate,
            'q'                    => $q,
            'division_id'          => $divisionId,
            'destination'          => $destinationFilter,
            'per_page'             => $perPage,
            'page'                 => $page,
            'divisions'            => $divisions,
            'destination_guard_map' => $destinationGuardMap,
            'rows'                 => $compare['rows'] ?? [],
            'summary'              => $compare['summary'] ?? [],
            'orphan_stock'         => $orphanStock,
        ]);
    }

    public function stock_division_reconcile_audit()
    {
        if (!$this->can(self::PAGE_STOCK_DIVISION, 'view')) {
            $this->require_permission(self::PAGE_ORDER, 'view');
        }

        $divisionId = (int)$this->input->get('division_id', true);
        $destinationFilter = strtoupper(trim((string)$this->input->get('destination', true)));
        if ($destinationFilter === '') {
            $destinationFilter = 'ALL';
        }

        $result = $this->Purchase_model->division_material_reconcile_audit(
            trim((string)$this->input->get('as_of_date', true)),
            [
                'division_id' => $divisionId,
                'item_id' => (int)$this->input->get('item_id', true),
                'material_id' => (int)$this->input->get('material_id', true),
                'destination' => $destinationFilter,
            ]
        );

        $status = !empty($result['ok']) ? 200 : 422;
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function stock_division_reconcile_repair()
    {
        $this->require_permission(self::PAGE_STOCK_DIVISION, 'edit');

        $payload = json_decode((string)$this->input->raw_input_stream, true);
        if (!is_array($payload)) {
            $payload = $this->input->post(null, true) ?: [];
        }

        $destinationFilter = strtoupper(trim((string)($payload['destination'] ?? 'ALL')));
        if ($destinationFilter === '') {
            $destinationFilter = 'ALL';
        }

        $result = $this->Purchase_model->repair_division_material_reconcile(
            (string)($payload['as_of_date'] ?? ''),
            [
                'division_id' => (int)($payload['division_id'] ?? 0),
                'item_id' => (int)($payload['item_id'] ?? 0),
                'material_id' => (int)($payload['material_id'] ?? 0),
                'destination' => $destinationFilter,
                'force_mode' => (string)($payload['force_mode'] ?? ''),
            ]
        );

        $status = (!empty($result['ok']) || !empty($result['needs_choice'])) ? 200 : 422;
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function stock_division_reconcile_lot_repair()
    {
        $this->require_permission(self::PAGE_STOCK_DIVISION, 'edit');

        $payload = json_decode((string)$this->input->raw_input_stream, true);
        if (!is_array($payload)) {
            $payload = $this->input->post(null, true) ?: [];
        }

        $result = $this->Purchase_model->repair_division_material_lot_balance([
            'division_id' => (int)($payload['division_id'] ?? 0),
            'material_id' => (int)($payload['material_id'] ?? 0),
            'destination' => strtoupper(trim((string)($payload['destination'] ?? 'ALL'))),
        ]);

        $status = !empty($result['ok']) ? 200 : 422;
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function stock_division_reconcile_lot_profile_sync()
    {
        $this->require_permission(self::PAGE_STOCK_DIVISION, 'edit');

        $payload = json_decode((string)$this->input->raw_input_stream, true);
        if (!is_array($payload)) {
            $payload = $this->input->post(null, true) ?: [];
        }

        $result = $this->Purchase_model->sync_division_lot_by_profile([
            'division_id' => (int)($payload['division_id'] ?? 0),
            'material_id' => (int)($payload['material_id'] ?? 0),
            'destination' => strtoupper(trim((string)($payload['destination'] ?? 'ALL'))),
        ]);

        $status = !empty($result['ok']) ? 200 : 422;
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function stock_division_reconcile_lot_repair_all()
    {
        $this->require_permission(self::PAGE_STOCK_DIVISION, 'edit');

        $payload = json_decode((string)$this->input->raw_input_stream, true);
        if (!is_array($payload)) {
            $payload = $this->input->post(null, true) ?: [];
        }

        $asOfDate        = trim((string)($payload['as_of_date'] ?? date('Y-m-d')));
        $divisionId      = (int)($payload['division_id'] ?? 0);
        $destinationFilter = strtoupper(trim((string)($payload['destination'] ?? 'ALL')));
        $q               = trim((string)($payload['q'] ?? ''));

        $compare = $this->Purchase_model->list_division_material_stock_compare(
            $asOfDate,
            $q,
            $divisionId > 0 ? $divisionId : null,
            2000,
            $destinationFilter !== 'ALL' ? $destinationFilter : null
        );

        $rows = is_array($compare['rows'] ?? null) ? $compare['rows'] : [];
        $toRepair = array_values(array_filter($rows, static fn($r) =>
            !empty($r['has_lot_mismatch']) || !empty($r['has_profile_lot_mismatch'])
        ));

        if (empty($toRepair)) {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'ok'      => true,
                    'message' => 'Semua lot sudah sesuai, tidak ada yang perlu direpair.',
                    'data'    => ['processed' => 0, 'repaired' => 0, 'profile_synced' => 0, 'failed' => 0, 'results' => []],
                ]));
            return;
        }

        $repaired      = 0;
        $profileSynced = 0;
        $failed        = 0;
        $results       = [];

        foreach ($toRepair as $row) {
            $params = [
                'division_id' => (int)($row['division_id'] ?? 0),
                'material_id' => (int)($row['material_id'] ?? 0),
                'destination' => strtoupper((string)($row['destination_group'] ?? 'ALL')),
            ];
            $label = trim((string)($row['material_name'] ?? '-')) . ' @ ' . trim((string)($row['division_name'] ?? '-'));

            if ($params['material_id'] <= 0) {
                $failed++;
                $results[] = ['label' => $label, 'status' => 'skipped', 'message' => 'Tidak punya material_id — jalankan Repair Material ID terlebih dahulu.'];
                continue;
            }

            $repair = $this->Purchase_model->repair_division_material_lot_balance($params);

            if (!empty($repair['ok'])) {
                $repaired++;
                $results[] = ['label' => $label, 'status' => 'repaired'];
            } elseif (!empty($repair['needs_profile_sync'])) {
                $sync = $this->Purchase_model->sync_division_lot_by_profile($params);
                if (!empty($sync['ok'])) {
                    $profileSynced++;
                    $results[] = ['label' => $label, 'status' => 'profile_synced'];
                } else {
                    $failed++;
                    $results[] = ['label' => $label, 'status' => 'failed', 'message' => (string)($sync['message'] ?? '')];
                }
            } else {
                $failed++;
                $results[] = ['label' => $label, 'status' => 'failed', 'message' => (string)($repair['message'] ?? '')];
            }
        }

        $total   = count($toRepair);
        $message = "Repair Lot selesai: {$repaired} direpair, {$profileSynced} sinkronisasi profil, {$failed} gagal dari {$total} bahan baku.";
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok'      => ($failed === 0),
                'message' => $message,
                'data'    => [
                    'processed'      => $total,
                    'repaired'       => $repaired,
                    'profile_synced' => $profileSynced,
                    'failed'         => $failed,
                    'results'        => $results,
                ],
            ]));
    }

    public function stock_division_reconcile_lot_only_adjust()
    {
        $this->require_permission(self::PAGE_STOCK_DIVISION, 'edit');

        $payload = json_decode((string)$this->input->raw_input_stream, true);
        if (!is_array($payload)) {
            $payload = $this->input->post(null, true) ?: [];
        }

        $lotId         = (int)($payload['lot_id'] ?? 0);
        $divisionId    = (int)($payload['division_id'] ?? 0);
        $materialId    = (int)($payload['material_id'] ?? 0);
        $destination   = strtoupper(trim((string)($payload['destination'] ?? '')));
        $profileKey    = trim((string)($payload['profile_key'] ?? ''));
        $targetQty     = round((float)($payload['target_qty'] ?? 0), 4);
        $adjustDate    = trim((string)($payload['adjustment_date'] ?? date('Y-m-d')));
        $notes         = trim((string)($payload['notes'] ?? ''));
        $unitCostInput = round((float)($payload['unit_cost'] ?? 0), 6);
        $contentUomId  = (int)($payload['content_uom_id'] ?? 0);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $adjustDate)) {
            $adjustDate = date('Y-m-d');
        }

        // Resolve lot
        $lot = null;
        if ($lotId > 0) {
            $lot = $this->db->where('id', $lotId)->get('inv_material_fifo_lot')->row_array() ?: null;
            if (!$lot) {
                $this->output->set_status_header(404)->set_content_type('application/json')
                    ->set_output(json_encode(['ok' => false, 'message' => 'Lot tidak ditemukan.']));
                return;
            }
        } else {
            if ($materialId <= 0 || $divisionId <= 0 || $destination === '') {
                $this->output->set_status_header(422)->set_content_type('application/json')
                    ->set_output(json_encode(['ok' => false, 'message' => 'Identitas lot (division_id, material_id, destination) wajib diisi.']));
                return;
            }
            $q = $this->db->where('location_scope', 'DIVISION')
                ->where('division_id', $divisionId)
                ->where('destination_type', $destination)
                ->where('material_id', $materialId)
                ->where('status', 'OPEN');
            if ($profileKey !== '') {
                $q->where('profile_key', $profileKey);
            }
            $lot = $q->order_by('receipt_date', 'ASC')->order_by('id', 'ASC')
                ->limit(1)->get('inv_material_fifo_lot')->row_array() ?: null;

            if (!$lot && $targetQty <= 0) {
                $this->output->set_status_header(422)->set_content_type('application/json')
                    ->set_output(json_encode(['ok' => false, 'message' => 'Tidak ada lot aktif untuk identity ini. Target harus > 0 untuk membuat lot baru.']));
                return;
            }
        }

        $currentQty = $lot ? round((float)($lot['qty_balance'] ?? 0), 4) : 0.0;
        $delta      = round($targetQty - $currentQty, 4);

        if (abs($delta) < 0.0001) {
            $this->output->set_status_header(422)->set_content_type('application/json')
                ->set_output(json_encode(['ok' => false, 'message' => 'Saldo target sama dengan saldo lot saat ini. Tidak ada perubahan.']));
            return;
        }

        $now    = date('Y-m-d H:i:s');
        $result = ['ok' => false, 'message' => 'Gagal menyimpan adjustment lot.'];

        $this->db->trans_begin();

        if ($delta < 0) {
            if (!$lot) {
                $this->db->trans_rollback();
                $this->output->set_status_header(422)->set_content_type('application/json')
                    ->set_output(json_encode(['ok' => false, 'message' => 'Lot tidak ditemukan untuk adjustment minus.']));
                return;
            }
            $baseLotId = (int)$lot['id'];
            $outQty    = round(abs($delta), 4);
            $issueNo   = 'MFLADJ' . date('YmdHis') . substr(md5($baseLotId . '|' . $now), 0, 6);
            $totalCost = round($outQty * (float)($lot['unit_cost'] ?? 0), 2);

            $this->db->insert('inv_material_fifo_issue_log', [
                'issue_no'      => $issueNo,
                'issue_date'    => $adjustDate,
                'issue_datetime'=> $now,
                'location_scope'=> 'DIVISION',
                'division_id'   => (int)($lot['division_id'] ?? $divisionId),
                'destination_type' => (string)($lot['destination_type'] ?? $destination),
                'item_id'       => !empty($lot['item_id']) ? (int)$lot['item_id'] : null,
                'material_id'   => (int)($lot['material_id'] ?? $materialId),
                'buy_uom_id'    => !empty($lot['buy_uom_id']) ? (int)$lot['buy_uom_id'] : null,
                'content_uom_id'=> (int)$lot['content_uom_id'],
                'profile_key'   => $lot['profile_key'] ?? ($profileKey !== '' ? $profileKey : null),
                'issue_qty'     => $outQty,
                'total_cost'    => $totalCost,
                'source_module' => 'DIVISION_RECONCILE',
                'source_table'  => 'inv_material_fifo_lot',
                'source_id'     => $baseLotId,
                'notes'         => 'Lot-only adj rekonsiliasi divisi' . ($notes !== '' ? ': ' . $notes : ''),
                'status'        => 'POSTED',
                'created_at'    => $now,
            ]);
            $issueId = (int)$this->db->insert_id();

            $this->db->insert('inv_material_fifo_issue_line', [
                'issue_id'             => $issueId,
                'lot_id'               => $baseLotId,
                'qty_out'              => $outQty,
                'unit_cost'            => round((float)($lot['unit_cost'] ?? 0), 6),
                'total_cost'           => $totalCost,
                'source_balance_before'=> $currentQty,
                'source_balance_after' => $targetQty,
                'created_at'           => $now,
            ]);

            $this->db->where('id', $baseLotId)->update('inv_material_fifo_lot', [
                'qty_out'    => round((float)($lot['qty_out'] ?? 0) + $outQty, 4),
                'qty_balance'=> $targetQty,
                'status'     => $targetQty > 0.0001 ? 'OPEN' : 'CLOSED',
                'updated_at' => $now,
            ]);

            $result = ['ok' => true, 'before_qty' => $currentQty, 'after_qty' => $targetQty, 'delta_qty' => $delta,
                'message' => 'Adjustment lot minus berhasil. Monthly stock tidak diubah.'];
        } else {
            $unitCost = $unitCostInput > 0 ? $unitCostInput
                : ($lot ? round((float)($lot['unit_cost'] ?? 0), 6) : 0.0);
            if ($unitCost <= 0) {
                $this->db->trans_rollback();
                $this->output->set_status_header(422)->set_content_type('application/json')
                    ->set_output(json_encode(['ok' => false, 'message' => 'Unit cost wajib diisi untuk adjustment lot plus.']));
                return;
            }

            $baseLotId       = $lot ? (int)$lot['id'] : 0;
            $inQty           = round($delta, 4);
            $lotNo           = substr('MFLADJ-' . date('YmdHis') . '-' . $materialId . '-' . $baseLotId, 0, 80);
            $baseDiv         = $lot ? (int)($lot['division_id'] ?? $divisionId) : $divisionId;
            $baseDest        = $lot ? (string)($lot['destination_type'] ?? $destination) : $destination;
            $baseItemId      = ($lot && !empty($lot['item_id'])) ? (int)$lot['item_id'] : null;
            $baseMaterialId  = $lot ? (int)($lot['material_id'] ?? $materialId) : $materialId;
            $baseBuyUomId    = ($lot && !empty($lot['buy_uom_id'])) ? (int)$lot['buy_uom_id'] : null;
            $baseContentUomId= $lot ? (int)$lot['content_uom_id'] : $contentUomId;
            $basePk          = $lot ? ($lot['profile_key'] ?? ($profileKey !== '' ? $profileKey : null))
                                    : ($profileKey !== '' ? $profileKey : null);

            if ($baseContentUomId <= 0) {
                $this->db->trans_rollback();
                $this->output->set_status_header(422)->set_content_type('application/json')
                    ->set_output(json_encode(['ok' => false, 'message' => 'content_uom_id tidak diketahui. Sertakan identitas lot atau content_uom_id dalam payload.']));
                return;
            }

            $this->db->insert('inv_material_fifo_lot', [
                'lot_no'          => $lotNo,
                'location_scope'  => 'DIVISION',
                'receipt_date'    => $adjustDate,
                'expiry_date'     => null,
                'division_id'     => $baseDiv,
                'destination_type'=> $baseDest,
                'item_id'         => $baseItemId,
                'material_id'     => $baseMaterialId,
                'buy_uom_id'      => $baseBuyUomId,
                'content_uom_id'  => $baseContentUomId,
                'profile_key'     => $basePk,
                'qty_in'          => $inQty,
                'qty_out'         => 0,
                'qty_balance'     => $inQty,
                'unit_cost'       => $unitCost,
                'source_table'    => 'div_lot_manual_adj',
                'source_id'       => $baseLotId > 0 ? $baseLotId : null,
                'parent_lot_id'   => $baseLotId > 0 ? $baseLotId : null,
                'status'          => 'OPEN',
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);

            $result = ['ok' => true, 'before_qty' => $currentQty, 'after_qty' => $targetQty, 'delta_qty' => $delta,
                'message' => 'Lot koreksi berhasil dibuat. Monthly stock tidak diubah.'];
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            $this->output->set_status_header(422)->set_content_type('application/json')
                ->set_output(json_encode(['ok' => false, 'message' => 'Gagal menyimpan adjustment lot (transaksi DB gagal).']));
            return;
        }

        $this->db->trans_commit();
        $status = !empty($result['ok']) ? 200 : 422;
        $this->output->set_status_header($status)->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function stock_division_reconcile_gap_repair_all()
    {
        $this->require_permission(self::PAGE_STOCK_DIVISION, 'edit');

        $payload = json_decode((string)$this->input->raw_input_stream, true);
        if (!is_array($payload)) {
            $payload = $this->input->post(null, true) ?: [];
        }

        $asOfDate = trim((string)($payload['as_of_date'] ?? date('Y-m-d')));
        $divisionId = (int)($payload['division_id'] ?? 0);
        $destinationFilter = strtoupper(trim((string)($payload['destination'] ?? 'ALL')));
        if ($destinationFilter === '') {
            $destinationFilter = 'ALL';
        }

        $result = $this->Purchase_model->repair_division_material_log_gap_opening_batch($asOfDate, [
            'division_id' => $divisionId,
            'destination' => $destinationFilter,
        ]);

        $status = (!empty($result['ok']) || !empty($result['data']['processed'])) ? 200 : 422;
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function stock_division_reconcile_repair_material_id()
    {
        $this->require_permission(self::PAGE_STOCK_DIVISION, 'edit');

        $payload = json_decode((string)$this->input->raw_input_stream, true);
        if (!is_array($payload)) {
            $payload = $this->input->post(null, true) ?: [];
        }
        $divisionId = (int)($payload['division_id'] ?? 0);

        $result = $this->Purchase_model->repair_monthly_stock_missing_material_id($divisionId > 0 ? $divisionId : null);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok'      => (bool)($result['ok'] ?? false),
                'message' => (string)($result['message'] ?? ''),
                'data'    => ['repaired' => (int)($result['repaired'] ?? 0)],
            ]));
    }

    public function stock_division_reconcile_profile_repair()
    {
        $this->require_permission(self::PAGE_STOCK_DIVISION, 'edit');

        $payload = json_decode((string)$this->input->raw_input_stream, true);
        if (!is_array($payload)) {
            $payload = $this->input->post(null, true) ?: [];
        }

        $result = $this->Purchase_model->repair_division_material_profile([
            'division_id' => (int)($payload['division_id'] ?? 0),
            'material_id' => (int)($payload['material_id'] ?? 0),
            'destination' => strtoupper(trim((string)($payload['destination'] ?? 'ALL'))),
            'profile_key' => trim((string)($payload['profile_key'] ?? '')),
            'repair_mode' => trim((string)($payload['repair_mode'] ?? 'lot_repair')),
            'as_of_date'  => trim((string)($payload['as_of_date'] ?? date('Y-m-d'))),
        ]);

        $status = !empty($result['ok']) ? 200 : 422;
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function stock_division_reconcile_profile_merge()
    {
        $this->require_permission(self::PAGE_STOCK_DIVISION, 'edit');

        $payload = json_decode((string)$this->input->raw_input_stream, true);
        if (!is_array($payload)) {
            $payload = $this->input->post(null, true) ?: [];
        }

        try {
            $result = $this->Purchase_model->merge_division_material_profiles([
                'division_id' => (int)($payload['division_id'] ?? 0),
                'material_id' => (int)($payload['material_id'] ?? 0),
                'destination' => strtoupper(trim((string)($payload['destination'] ?? 'ALL'))),
                'target_profile_key' => trim((string)($payload['target_profile_key'] ?? '')),
                'source_profile_keys' => is_array($payload['source_profile_keys'] ?? null) ? $payload['source_profile_keys'] : [],
                'as_of_date' => trim((string)($payload['as_of_date'] ?? date('Y-m-d'))),
            ]);
            $status = !empty($result['ok']) ? 200 : 422;
        } catch (Throwable $e) {
            log_message('error', 'stock_division_reconcile_profile_merge failed: ' . $e->getMessage());
            $result = [
                'ok' => false,
                'message' => 'Join profile gagal di server: ' . $e->getMessage(),
            ];
            $status = 500;
        }

        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function inventory_warehouse_daily_index()
    {
        if (!$this->can(self::PAGE_STOCK_WAREHOUSE_MATRIX, 'view')) {
            if (!$this->can(self::PAGE_STOCK_WAREHOUSE, 'view')) {
                $this->require_permission(self::PAGE_ORDER, 'view');
            }
        }

        $month = trim((string)$this->input->get('month', true));
        $q = trim((string)$this->input->get('q', true));
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo = trim((string)$this->input->get('date_to', true));
        $range = $this->resolveDateRange($month, $dateFrom, $dateTo);
        $month = $range['month'];
        $dateFrom = $range['date_from'];
        $dateTo = $range['date_to'];
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 1000) {
            $limit = 120;
        }

        $data = [
            'title' => 'Inventory Warehouse Daily',
            'active_menu' => 'purchase.stock.warehouse.matrix',
            'month' => $month,
            'q' => $q,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'limit' => $limit,
            'matrix_url' => site_url('inventory-warehouse-daily/matrix'),
            'detail_url' => site_url('inventory-daily/cell-detail'),
        ];

        $this->render('purchase/inventory_warehouse_daily_index', $data);
    }

    public function inventory_material_daily_index()
    {
        if (!$this->can(self::PAGE_STOCK_MATERIAL_MATRIX, 'view')) {
            if (!$this->can(self::PAGE_STOCK_DIVISION, 'view')) {
                $this->require_permission(self::PAGE_ORDER, 'view');
            }
        }

        $divisions = $this->Purchase_model->list_active_operational_divisions();
        $destinationGuardMap = $this->buildDivisionDestinationGuardMap($divisions);
        $month = trim((string)$this->input->get('month', true));
        $q = trim((string)$this->input->get('q', true));
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo = trim((string)$this->input->get('date_to', true));
        $range = $this->resolveDateRange($month, $dateFrom, $dateTo);
        $month = $range['month'];
        $dateFrom = $range['date_from'];
        $dateTo = $range['date_to'];
        $divisionId = (int)$this->input->get('division_id', true);
        $destinationFilter = strtoupper(trim((string)$this->input->get('destination', true)));
        if ($destinationFilter === '') {
            $destinationFilter = 'ALL';
        }
        $destinationFilter = $this->normalizeDestinationForDivisionFilter($destinationFilter, $divisionId, $destinationGuardMap);
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 1000) {
            $limit = 120;
        }

        $data = [
            'title' => 'Daily Material Matrix',
            'active_menu' => 'purchase.stock.material.matrix',
            'month' => $month,
            'q' => $q,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'division_id' => $divisionId,
            'destination' => $destinationFilter,
            'limit' => $limit,
            'divisions' => $divisions,
            'destination_guard_map' => $destinationGuardMap,
            'matrix_url' => site_url('inventory-material-daily/matrix'),
            'detail_url' => site_url('inventory-daily/cell-detail'),
        ];

        $this->render('purchase/inventory_material_daily_index', $data);
    }

    public function fifo_audit_index()
    {
        $canView = $this->can(self::PAGE_STOCK_WAREHOUSE, 'view')
            || $this->can(self::PAGE_STOCK_DIVISION, 'view')
            || $this->can(self::PAGE_STOCK_WAREHOUSE_MATRIX, 'view')
            || $this->can(self::PAGE_STOCK_MATERIAL_MATRIX, 'view');
        if (!$canView) {
            $this->require_permission(self::PAGE_ORDER, 'view');
        }

        $divisions = $this->Purchase_model->list_active_operational_divisions();
        $destinationGuardMap = $this->buildDivisionDestinationGuardMap($divisions);
        $month = trim((string)$this->input->get('month', true));
        $q = trim((string)$this->input->get('q', true));
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo = trim((string)$this->input->get('date_to', true));
        $range = $this->resolveDateRange($month, $dateFrom, $dateTo);
        $month = $range['month'];
        $dateFrom = $range['date_from'];
        $dateTo = $range['date_to'];
        $scope = strtoupper(trim((string)$this->input->get('scope', true)));
        if (!in_array($scope, ['ALL', 'WAREHOUSE', 'DIVISION'], true)) {
            $scope = 'ALL';
        }
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['ALL', 'POSTED', 'VOID'], true)) {
            $status = 'POSTED';
        }
        $divisionId = (int)$this->input->get('division_id', true);
        $destinationFilter = strtoupper(trim((string)$this->input->get('destination', true)));
        if ($destinationFilter === '') {
            $destinationFilter = 'ALL';
        }
        $destinationFilter = $this->normalizeDestinationForDivisionFilter($destinationFilter, $divisionId, $destinationGuardMap);
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 500) {
            $limit = 100;
        }

        $issues = $this->Purchase_model->list_fifo_issue_audit([
            'q' => $q,
            'scope' => $scope,
            'status' => $status,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'division_id' => $divisionId,
            'destination' => $destinationFilter,
        ], $limit);

        $data = [
            'title' => 'Audit FIFO Material',
            'active_menu' => 'purchase.stock.warehouse',
            'month' => $month,
            'q' => $q,
            'scope' => $scope,
            'status' => $status,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'division_id' => $divisionId,
            'destination' => $destinationFilter,
            'limit' => $limit,
            'divisions' => $divisions,
            'destination_guard_map' => $destinationGuardMap,
            'issues' => $issues,
        ];

        $this->render('purchase/fifo_audit_index', $data);
    }

    protected function render_lot_audit_page(
        string $defaultScope = 'ALL',
        bool $scopeLocked = false,
        string $title = 'Audit Lot Material',
        string $activeMenu = 'purchase.stock.warehouse',
        string $baseRoute = 'inventory/lot-audit',
        string $subtitle = 'Posisi lot FIFO per scope, profile, dan lokasi stok.'
    ): void {
        $canView = $this->can(self::PAGE_STOCK_WAREHOUSE, 'view')
            || $this->can(self::PAGE_STOCK_DIVISION, 'view')
            || $this->can(self::PAGE_STOCK_WAREHOUSE_MATRIX, 'view')
            || $this->can(self::PAGE_STOCK_MATERIAL_MATRIX, 'view');
        if (!$canView) {
            $this->require_permission(self::PAGE_ORDER, 'view');
        }

        $divisions = $this->Purchase_model->list_active_operational_divisions();
        $destinationGuardMap = $this->buildDivisionDestinationGuardMap($divisions);
        $q = trim((string)$this->input->get('q', true));
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo = trim((string)$this->input->get('date_to', true));
        $defaultScope = strtoupper(trim($defaultScope));
        if (!in_array($defaultScope, ['ALL', 'WAREHOUSE', 'DIVISION'], true)) {
            $defaultScope = 'ALL';
        }
        $scope = strtoupper(trim((string)$this->input->get('scope', true)));
        if ($scopeLocked) {
            $scope = $defaultScope;
        } elseif (!in_array($scope, ['ALL', 'WAREHOUSE', 'DIVISION'], true)) {
            $scope = $defaultScope;
        }
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['ALL', 'OPEN', 'CLOSED'], true)) {
            $status = 'OPEN';
        }
        $divisionId = (int)$this->input->get('division_id', true);
        $destinationFilter = strtoupper(trim((string)$this->input->get('destination', true)));
        if ($destinationFilter === '') {
            $destinationFilter = 'ALL';
        }
        $destinationFilter = $this->normalizeDestinationForDivisionFilter($destinationFilter, $divisionId, $destinationGuardMap);
        $profileKey = trim((string)$this->input->get('profile_key', true));
        $itemId = (int)$this->input->get('item_id', true);
        $materialId = (int)$this->input->get('material_id', true);
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 500) {
            $limit = 200;
        }

        $rows = $this->Purchase_model->list_fifo_lot_audit([
            'q' => $q,
            'scope' => $scope,
            'status' => $status,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'division_id' => $divisionId,
            'destination' => $destinationFilter,
            'profile_key' => $profileKey,
            'item_id' => $itemId,
            'material_id' => $materialId,
        ], $limit);

        $data = [
            'title' => $title,
            'subtitle' => $subtitle,
            'active_menu' => $activeMenu,
            'base_url' => site_url($baseRoute),
            'q' => $q,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'scope' => $scope,
            'page_scope' => $defaultScope,
            'scope_locked' => $scopeLocked,
            'status' => $status,
            'division_id' => $divisionId,
            'destination' => $destinationFilter,
            'profile_key' => $profileKey,
            'item_id' => $itemId,
            'material_id' => $materialId,
            'limit' => $limit,
            'divisions' => $divisions,
            'destination_guard_map' => $destinationGuardMap,
            'rows' => $rows,
        ];

        $this->render('purchase/lot_audit_index', $data);
    }

    public function lot_audit_index()
    {
        $this->render_lot_audit_page();
    }

    public function warehouse_lot_audit_index()
    {
        if ($this->can('purchase.stock.warehouse.lot.index', 'view')) {
            $this->require_permission('purchase.stock.warehouse.lot.index', 'view');
        }
        $this->render_lot_audit_page(
            'WAREHOUSE',
            true,
            'Lot Stok Gudang',
            'purchase.stock.warehouse.lot',
            'inventory/stock/warehouse/lot',
            'Posisi lot FIFO untuk stok gudang.'
        );
    }

    public function division_lot_audit_index()
    {
        $canView = $this->can('purchase.stock.division.lot.index', 'view')
            || $this->can(self::PAGE_STOCK_WAREHOUSE, 'view')
            || $this->can(self::PAGE_STOCK_DIVISION, 'view')
            || $this->can(self::PAGE_STOCK_WAREHOUSE_MATRIX, 'view')
            || $this->can(self::PAGE_STOCK_MATERIAL_MATRIX, 'view');
        if (!$canView) {
            $this->require_permission(self::PAGE_ORDER, 'view');
        } elseif ($this->can('purchase.stock.division.lot.index', 'view')) {
            $this->require_permission('purchase.stock.division.lot.index', 'view');
        }

        $divisions          = $this->Purchase_model->list_active_operational_divisions();
        $destinationGuardMap = $this->buildDivisionDestinationGuardMap($divisions);
        $q                  = trim((string)$this->input->get('q', true));
        $dateFrom           = trim((string)$this->input->get('date_from', true));
        $dateTo             = trim((string)$this->input->get('date_to', true));
        $range              = $this->resolveDateRange('', $dateFrom, $dateTo);
        $dateFrom           = $range['date_from'];
        $dateTo             = $range['date_to'];
        $status             = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['ALL', 'OPEN', 'CLOSED'], true)) {
            $status = 'OPEN';
        }
        $divisionId         = (int)$this->input->get('division_id', true);
        $destinationFilter  = strtoupper(trim((string)$this->input->get('destination', true)));
        if ($destinationFilter === '') {
            $destinationFilter = 'ALL';
        }
        $destinationFilter  = $this->normalizeDestinationForDivisionFilter($destinationFilter, $divisionId, $destinationGuardMap);
        $profileKey         = trim((string)$this->input->get('profile_key', true));
        $perPage            = (int)$this->input->get('per_page', true);
        if ($perPage < 10 || $perPage > 200) {
            $perPage = 25;
        }
        $page               = max(1, (int)$this->input->get('page', true));

        $rows = $this->Purchase_model->list_fifo_lot_audit([
            'q'           => $q,
            'scope'       => 'DIVISION',
            'status'      => $status,
            'date_from'   => $dateFrom,
            'date_to'     => $dateTo,
            'division_id' => $divisionId,
            'destination' => $destinationFilter,
            'profile_key' => $profileKey,
        ], 500);

        $this->render('purchase/lot_division_index', [
            'title'                => 'Lot Bahan Baku',
            'subtitle'             => 'Posisi lot FIFO untuk stok divisi operasional.',
            'active_menu'          => 'purchase.stock.division.lot',
            'base_url'             => site_url('inventory/stock/division/lot'),
            'q'                    => $q,
            'date_from'            => $dateFrom,
            'date_to'              => $dateTo,
            'status'               => $status,
            'division_id'          => $divisionId,
            'destination'          => $destinationFilter,
            'profile_key'          => $profileKey,
            'per_page'             => $perPage,
            'page'                 => $page,
            'divisions'            => $divisions,
            'destination_guard_map' => $destinationGuardMap,
            'rows'                 => $rows,
        ]);
    }

    public function material_lot_usage($lotId)
    {
        $canView = $this->can('purchase.stock.warehouse.lot.index', 'view')
            || $this->can('purchase.stock.division.lot.index', 'view')
            || $this->can(self::PAGE_STOCK_WAREHOUSE, 'view')
            || $this->can(self::PAGE_STOCK_DIVISION, 'view')
            || $this->can(self::PAGE_STOCK_WAREHOUSE_MATRIX, 'view')
            || $this->can(self::PAGE_STOCK_MATERIAL_MATRIX, 'view');
        if (!$canView) {
            $this->require_permission(self::PAGE_ORDER, 'view');
        }

        $detail = $this->Purchase_model->fifo_lot_usage_detail((int)$lotId);
        if (!($detail['ok'] ?? false)) {
            show_error((string)($detail['message'] ?? 'Detail pemakaian lot bahan baku tidak ditemukan.'), 404, 'Not Found');
            return;
        }

        $header = (array)($detail['header'] ?? []);
        $title = 'Pemakaian Lot ' . (string)($header['lot_no'] ?? '#');
        $subtitle = 'Jejak konsumsi lot FIFO bahan baku ke transaksi, transfer, atau penyesuaian.';
        $this->render('purchase/lot_usage_detail', [
            'title' => $title,
            'subtitle' => $subtitle,
            'detail' => $detail,
            'active_menu' => 'purchase.stock.warehouse.lot',
        ]);
    }

    public function stock_daily_cell_detail()
    {
        $scope = strtoupper(trim((string)$this->input->get('scope', true)));
        if (!in_array($scope, ['WAREHOUSE', 'DIVISION'], true)) {
            $scope = 'WAREHOUSE';
        }

        if ($scope === 'DIVISION') {
            if (!$this->can(self::PAGE_STOCK_DIVISION, 'view') && !$this->can(self::PAGE_STOCK_MATERIAL_MATRIX, 'view')) {
                $this->require_permission(self::PAGE_ORDER, 'view');
            }
        } else {
            if (!$this->can(self::PAGE_STOCK_WAREHOUSE, 'view') && !$this->can(self::PAGE_STOCK_WAREHOUSE_MATRIX, 'view')) {
                $this->require_permission(self::PAGE_ORDER, 'view');
            }
        }

        $movementDate = trim((string)$this->input->get('movement_date', true));
        if ($movementDate === '') {
            $this->jsonError('movement_date wajib diisi.', 422);
            return;
        }

        $payload = [
            'scope' => $scope,
            'movement_date' => $movementDate,
            'division_id' => (int)$this->input->get('division_id', true),
            'stock_domain' => strtoupper(trim((string)$this->input->get('stock_domain', true))),
            'item_id' => (int)$this->input->get('item_id', true),
            'material_id' => (int)$this->input->get('material_id', true),
            'buy_uom_id' => (int)$this->input->get('buy_uom_id', true),
            'content_uom_id' => (int)$this->input->get('content_uom_id', true),
            'destination_type' => trim((string)$this->input->get('destination_type', true)),
            'profile_key' => trim((string)$this->input->get('profile_key', true)),
            'limit' => (int)$this->input->get('limit', true),
        ];

        $rows = $this->Purchase_model->list_stock_movement_cell_detail($payload);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => true,
                'rows' => $rows,
                'meta' => [
                    'scope' => $scope,
                    'movement_date' => $movementDate,
                    'total' => count($rows),
                ],
            ]));
    }

    public function stock_material_daily_matrix()
    {
        if (!$this->can(self::PAGE_STOCK_DIVISION, 'view') && !$this->can(self::PAGE_STOCK_MATERIAL_MATRIX, 'view')) {
            $this->require_permission(self::PAGE_ORDER, 'view');
        }

        $divisions = $this->Purchase_model->list_active_operational_divisions();
        $destinationGuardMap = $this->buildDivisionDestinationGuardMap($divisions);
        $month = trim((string)$this->input->get('month', true));
        $q = trim((string)$this->input->get('q', true));
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo = trim((string)$this->input->get('date_to', true));
        $range = $this->resolveDateRange($month, $dateFrom, $dateTo);
        $month = $range['month'];
        $dateFrom = $range['date_from'];
        $dateTo = $range['date_to'];
        $divisionId = (int)$this->input->get('division_id', true);
        $destinationFilter = strtoupper(trim((string)$this->input->get('destination', true)));
        if ($destinationFilter === '') {
            $destinationFilter = 'ALL';
        }
        $destinationFilter = $this->normalizeDestinationForDivisionFilter($destinationFilter, $divisionId, $destinationGuardMap);
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 1000) {
            $limit = 120;
        }
        $offset = max(0, (int)$this->input->get('offset', true));

        $matrix = $this->Purchase_model->list_material_daily_matrix(
            $month,
            $q,
            $divisionId > 0 ? $divisionId : null,
            $dateFrom,
            $dateTo,
            $limit,
            $destinationFilter,
            $offset
        );

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok'          => true,
                'data'        => $matrix,
                'total_count' => (int)($matrix['total_count'] ?? count($matrix['rows'] ?? [])),
                'limit'       => $limit,
                'offset'      => $offset,
            ]));
    }

    public function receipt_index()
    {
        if (!$this->can(self::PAGE_RECEIPT, 'view')) {
            $this->require_permission(self::PAGE_ORDER, 'view');
        }

        $q = trim((string)$this->input->get('q', true));
        $poOptions = $this->Purchase_model->list_purchase_orders_for_receipt($q, 150);

        $data = [
            'title' => 'Purchase Orders / Receipt',
            'active_menu' => 'purchase.receipt',
            'q' => $q,
            'po_options' => $poOptions,
        ];

        $this->render('purchase/receipt_index', $data);
    }

    public function receipt_po_lines()
    {
        if (!$this->can(self::PAGE_RECEIPT, 'view')) {
            $this->require_permission(self::PAGE_ORDER, 'view');
        }

        $purchaseOrderId = (int)$this->input->get('purchase_order_id', true);
        if ($purchaseOrderId <= 0) {
            $this->jsonError('purchase_order_id wajib valid.', 422);
            return;
        }

        $lines = $this->Purchase_model->get_po_lines_for_receipt($purchaseOrderId);
        foreach ($lines as &$line) {
            $ordered = (float)($line['qty_buy'] ?? 0);
            $received = (float)($line['qty_buy_received_total'] ?? 0);
            $line['qty_buy_remaining'] = round(max(0, $ordered - $received), 4);
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => true,
                'items' => $lines,
                'meta' => [
                    'purchase_order_id' => $purchaseOrderId,
                    'total' => count($lines),
                ],
            ]));
    }

    public function receipt_store()
    {
        if (!$this->can(self::PAGE_RECEIPT, 'create')) {
            $this->require_permission(self::PAGE_ORDER, 'create');
        }

        $payload = $this->requestPayload();
        if (isset($payload['lines_json']) && (!isset($payload['lines']) || !is_array($payload['lines']))) {
            $decodedLines = json_decode((string)$payload['lines_json'], true);
            if (is_array($decodedLines)) {
                $payload['lines'] = $decodedLines;
            }
        }

        $result = $this->Purchase_model->store_receipt_and_post(
            $payload,
            (int)($this->current_user['id'] ?? 0),
            (string)$this->input->ip_address()
        );

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal memproses receipt purchase.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function catalog_search()
    {
        $canCatalog = $this->can(self::PAGE_CATALOG, 'view');
        $canOrder = $this->can(self::PAGE_ORDER, 'view') || $this->can(self::PAGE_ORDER, 'create');
        if (!$canCatalog && !$canOrder) {
            $this->jsonError('Anda tidak memiliki izin untuk pencarian catalog.', 403);
            return;
        }

        $q = trim((string)$this->input->get('q', true));
        $vendorId = (int)$this->input->get('vendor_id', true);
        $itemId = (int)$this->input->get('item_id', true);
        $materialId = (int)$this->input->get('material_id', true);
        $lineKind = strtoupper(trim((string)$this->input->get('line_kind', true)));
        $limit = (int)$this->input->get('limit', true);

        if (!in_array($lineKind, ['ITEM', 'MATERIAL', 'SERVICE', 'ASSET'], true)) {
            $lineKind = '';
        }
        if ($limit <= 0 || $limit > 100) {
            $limit = 25;
        }

        $resultLimit = $vendorId > 0 ? min($limit, 5) : $limit;

        $vendorCatalogRows = $this->Purchase_model->search_catalog_profiles(
            $q,
            $vendorId,
            $lineKind,
            $itemId,
            $materialId,
            $resultLimit
        );
        $generalCatalogRows = [];
        if ($vendorId > 0 && count($vendorCatalogRows) < $resultLimit) {
            $generalCatalogRows = $this->Purchase_model->search_catalog_profiles(
                $q,
                0,
                $lineKind,
                $itemId,
                $materialId,
                $resultLimit
            );
        }

        $catalogRows = $vendorCatalogRows;
        if (!empty($generalCatalogRows)) {
            $seenKeys = [];
            foreach ($catalogRows as $row) {
                $seenKeys[] = implode('|', [
                    (string)($row['source_type'] ?? 'CATALOG'),
                    (string)($row['catalog_id'] ?? 0),
                    (string)($row['profile_key'] ?? ''),
                    (string)($row['item_id'] ?? 0),
                    (string)($row['material_id'] ?? 0),
                ]);
            }

            foreach ($generalCatalogRows as $row) {
                if (count($catalogRows) >= $resultLimit) {
                    break;
                }

                $rowKey = implode('|', [
                    (string)($row['source_type'] ?? 'CATALOG'),
                    (string)($row['catalog_id'] ?? 0),
                    (string)($row['profile_key'] ?? ''),
                    (string)($row['item_id'] ?? 0),
                    (string)($row['material_id'] ?? 0),
                ]);
                if (in_array($rowKey, $seenKeys, true)) {
                    continue;
                }

                $catalogRows[] = $row;
                $seenKeys[] = $rowKey;
            }
        }
        $fallbackRows = [];

        // Layered search: vendor catalog -> general catalog -> master fallback.
        if (count($catalogRows) === 0) {
            $fallbackRows = $this->Purchase_model->search_master_fallback(
                $q,
                $lineKind !== '' ? $lineKind : 'ITEM',
                $itemId,
                $materialId,
                $resultLimit
            );
        }

        $rows = !empty($catalogRows) ? $catalogRows : $fallbackRows;

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => true,
                'items' => $rows,
                'meta' => [
                    'q' => $q,
                    'vendor_id' => $vendorId,
                    'line_kind' => $lineKind,
                    'result_limit' => $resultLimit,
                    'vendor_catalog_count' => count($vendorCatalogRows),
                    'general_catalog_count' => count($generalCatalogRows),
                    'catalog_count' => count($catalogRows),
                    'fallback_count' => count($fallbackRows),
                    'total' => count($rows),
                ],
            ]));
    }

    public function catalog_sync_core()
    {
        $canCatalog = $this->can(self::PAGE_CATALOG, 'create');
        $canOrder = $this->can(self::PAGE_ORDER, 'create');
        if (!$canCatalog && !$canOrder) {
            $this->jsonError('Anda tidak memiliki izin untuk sinkronisasi catalog.', 403);
            return;
        }

        $payload = $this->requestPayload();
        $limit = (int)($payload['limit'] ?? 1000);
        $result = $this->Purchase_model->sync_catalog_from_core($limit);

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal sinkron katalog dari core.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function setup_sync_core()
    {
        $this->require_permission(self::PAGE_ORDER, 'create');

        $payload = $this->requestPayload();
        $limit = (int)($payload['limit'] ?? 2000);
        $result = $this->Purchase_model->sync_purchase_setup_from_core($limit);

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal sinkron posting/purchase type dari core.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function setup_sync_core_all()
    {
        $this->require_permission(self::PAGE_ORDER, 'create');

        $payload = $this->requestPayload();
        $limit = (int)($payload['limit'] ?? 2000);
        $result = $this->Purchase_model->sync_purchase_master_data_from_core($limit);

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal sinkron master purchase dari core.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function order_store()
    {
        $this->require_permission(self::PAGE_ORDER, 'create');

        $payload = $this->requestPayload();
        $header = (array)($payload['header'] ?? []);
        $header['status'] = 'DRAFT';
        $lines = $payload['lines'] ?? [];

        if (!is_array($lines) || empty($lines)) {
            $this->jsonError('Baris purchase order wajib diisi.', 422);
            return;
        }

        $vendorId = max(0, (int)($header['vendor_id'] ?? 0));
        $purchaseTypeId = (int)($header['purchase_type_id'] ?? 0);
        $requestDate = trim((string)($header['request_date'] ?? ''));
        if ($purchaseTypeId <= 0 || $requestDate === '') {
            $this->jsonError('Header PO belum lengkap: purchase_type_id dan request_date wajib diisi.', 422);
            return;
        }
        if ($vendorId <= 0) {
            $this->jsonError('Vendor wajib dipilih sebelum menyimpan purchase order.', 422);
            return;
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $store = $this->Purchase_model->store_order_with_lines(
                $header,
                $lines,
                (int)($this->current_user['id'] ?? 0),
                (string)$this->input->ip_address()
            );
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }
        if (!($store['ok'] ?? false)) {
            $this->jsonError((string)($store['message'] ?? 'Gagal menyimpan purchase order.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($store));
    }

    public function order_status_update()
    {
        if (!$this->can(self::PAGE_ORDER, 'edit')) {
            $this->jsonError('Anda tidak memiliki izin untuk update status purchase order.', 403);
            return;
        }

        $payload = $this->requestPayload();
        $purchaseOrderId = (int)($payload['purchase_order_id'] ?? 0);
        $newStatus = strtoupper(trim((string)($payload['status'] ?? '')));

        if ($purchaseOrderId <= 0 || $newStatus === '') {
            $this->jsonError('purchase_order_id dan status wajib diisi.', 422);
            return;
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Purchase_model->update_order_status(
                $purchaseOrderId,
                $newStatus,
                (int)($this->current_user['id'] ?? 0),
                (string)$this->input->ip_address()
            );
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }
        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal update status purchase order.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function order_update(int $purchaseOrderId = 0)
    {
        if (!$this->can(self::PAGE_ORDER, 'edit')) {
            $this->jsonError('Anda tidak memiliki izin untuk edit purchase order.', 403);
            return;
        }

        if ($purchaseOrderId <= 0) {
            $this->jsonError('purchase_order_id tidak valid.', 422);
            return;
        }

        $payload = $this->requestPayload();
        $header = (array)($payload['header'] ?? []);
        $lines = $payload['lines'] ?? [];
        $targetStatus = strtoupper(trim((string)($header['status'] ?? '')));
        $currentStatus = '';

        $editability = $this->Purchase_model->get_order_data_editability($purchaseOrderId);
        if ($editability['ok'] ?? false) {
            $currentStatus = strtoupper(trim((string)($editability['data']['status'] ?? '')));
        }

        if (!is_array($lines) || empty($lines)) {
            $this->jsonError('Baris purchase order wajib diisi.', 422);
            return;
        }

        $purchaseTypeId = (int)($header['purchase_type_id'] ?? 0);
        $requestDate = trim((string)($header['request_date'] ?? ''));
        if ($purchaseTypeId <= 0 || $requestDate === '') {
            $this->jsonError('Header PO belum lengkap: purchase_type_id dan request_date wajib diisi.', 422);
            return;
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Purchase_model->update_order_with_lines(
                $purchaseOrderId,
                $header,
                $lines,
                (int)($this->current_user['id'] ?? 0),
                (string)$this->input->ip_address()
            );
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }
        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal update purchase order.'), 422);
            return;
        }

        if ($targetStatus !== '' && $currentStatus !== '' && $targetStatus !== $currentStatus) {
            $statusResult = $this->Purchase_model->update_order_status(
                $purchaseOrderId,
                $targetStatus,
                (int)($this->current_user['id'] ?? 0),
                (string)$this->input->ip_address()
            );
            if (!($statusResult['ok'] ?? false)) {
                $this->jsonError('Data PO tersimpan, tetapi ubah status gagal: ' . (string)($statusResult['message'] ?? 'error'), 422);
                return;
            }

            $result['message'] = 'Purchase order berhasil diperbarui dan status ditinjau ulang.';
            $result['data']['status_change'] = (array)($statusResult['data'] ?? []);
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function payment_apply()
    {
        $this->require_permission(self::PAGE_ORDER, 'edit');

        $payload = $this->requestPayload();
        $result = $this->Purchase_model->apply_payment(
            $payload,
            (int)($this->current_user['id'] ?? 0),
            (string)$this->input->ip_address()
        );

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal memproses pembayaran.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    private function resolveDateRange(string $month, string $dateFrom, string $dateTo): array
    {
        $month = trim($month);
        $monthTs = $month !== '' ? strtotime($month . '-01') : false;
        if ($monthTs === false) {
            $monthTs = strtotime(date('Y-m-01'));
        }

        $monthNorm = date('Y-m', $monthTs);
        $defaultFrom = date('Y-m-01', $monthTs);
        $defaultTo = date('Y-m-t', $monthTs);

        $fromTs = trim($dateFrom) !== '' ? strtotime($dateFrom) : false;
        $toTs = trim($dateTo) !== '' ? strtotime($dateTo) : false;

        $from = $fromTs !== false ? date('Y-m-d', $fromTs) : $defaultFrom;
        $to = $toTs !== false ? date('Y-m-d', $toTs) : $defaultTo;

        if ($from > $to) {
            $tmp = $from;
            $from = $to;
            $to = $tmp;
        }

        return [
            'month' => $monthNorm,
            'date_from' => $from,
            'date_to' => $to,
        ];
    }

    private function buildDivisionDestinationGuardMap(array $divisions): array
    {
        $map = [];
        foreach ($divisions as $row) {
            $divisionId = (int)($row['id'] ?? 0);
            if ($divisionId <= 0) {
                continue;
            }
            $allowedCsv = strtoupper(trim((string)($row['destination_allowed'] ?? '')));
            $allowed = array_values(array_filter(array_map('trim', explode(',', $allowedCsv)), static function ($x) {
                return $x !== '';
            }));
            if (empty($allowed)) {
                $allowed = ['BAR', 'KITCHEN', 'BAR_EVENT', 'KITCHEN_EVENT', 'OFFICE', 'OTHER'];
            }
            $map[$divisionId] = array_values(array_unique($allowed));
        }
        return $map;
    }

    private function normalizeDestinationForDivisionFilter(string $destination, int $divisionId, array $guardMap): string
    {
        $dest = strtoupper(trim($destination));
        if ($dest === '') {
            $dest = 'ALL';
        }
        if (!in_array($dest, ['ALL', 'REGULER', 'EVENT', 'BAR', 'KITCHEN', 'BAR_EVENT', 'KITCHEN_EVENT', 'OFFICE', 'OTHER'], true)) {
            $dest = 'ALL';
        }
        if ($divisionId <= 0 || !isset($guardMap[$divisionId]) || in_array($dest, ['ALL', 'REGULER', 'EVENT'], true)) {
            return $dest;
        }
        return in_array($dest, (array)$guardMap[$divisionId], true) ? $dest : 'ALL';
    }

    private function mutation_per_page(): int
    {
        $pp = (int)$this->input->get('per_page', true);
        if (!in_array($pp, [10, 25, 50, 100, 200], true)) {
            $pp = 25;
        }
        return $pp;
    }

    private function build_pagination(int $total, int $perPage, int $page): array
    {
        $totalPages = max(1, (int)ceil($total / max(1, $perPage)));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        return [
            'total' => $total,
            'per_page' => $perPage,
            'page' => $page,
            'total_pages' => $totalPages,
            'offset' => ($page - 1) * $perPage,
        ];
    }

    private function requestPayload(): array
    {
        $raw = (string)$this->input->raw_input_stream;
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $post = $this->input->post(null, true);
        if (!is_array($post)) {
            return [];
        }

        if (isset($post['lines']) && is_string($post['lines'])) {
            $decodedLines = json_decode((string)$post['lines'], true);
            if (is_array($decodedLines)) {
                $post['lines'] = $decodedLines;
            }
        }

        if (!isset($post['header']) || !is_array($post['header'])) {
            $headerKeys = [
                'po_no', 'request_date', 'expected_date', 'purchase_type_id', 'destination_type',
                'destination_division_id', 'vendor_id', 'payment_account_id', 'status',
                'currency_code', 'exchange_rate', 'external_ref_no', 'notes', 'review_confirmed',
            ];
            $header = [];
            foreach ($headerKeys as $key) {
                if (array_key_exists($key, $post)) {
                    $header[$key] = $post[$key];
                    unset($post[$key]);
                }
            }
            $post['header'] = $header;
        }

        return $post;
    }

    private function jsonOk(array $data = []): void
    {
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(array_merge(['ok' => true], $data), JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function jsonError(string $message, int $statusCode = 400): void
    {
        $this->output
            ->set_status_header($statusCode)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => false,
                'message' => $message,
            ]));
    }

    private function rowIdentityKey(array $row): string
    {
        $lineKind = strtoupper((string)($row['line_kind'] ?? ''));
        $itemId = (int)($row['item_id'] ?? 0);
        $materialId = (int)($row['material_id'] ?? 0);
        $buyUomId = (int)($row['buy_uom_id'] ?? 0);
        $contentUomId = (int)($row['content_uom_id'] ?? 0);
        $contentPerBuy = (string)($row['content_per_buy'] ?? '0');
        $brand = strtoupper(trim((string)($row['brand_name'] ?? '')));
        $desc = strtoupper(trim((string)($row['line_description'] ?? '')));
        $expiredDate = trim((string)($row['expired_date'] ?? ''));

        return implode('|', [
            $lineKind,
            $itemId,
            $materialId,
            $buyUomId,
            $contentUomId,
            $contentPerBuy,
            $brand,
            $desc,
            $expiredDate,
        ]);
    }

    // ── Riwayat Harga Item / Bahan ────────────────────────────────────

    public function item_price_history(int $itemId = 0)
    {
        $this->require_permission(self::PAGE_ORDER, 'view');
        $itemId     = max(0, $itemId);
        $materialId = max(0, (int)$this->input->get('material_id', true));

        // Jika datang dari halaman material (material_id), cari item yang paling terakhir
        // dibeli untuk material tersebut dari inv_stock_movement_log
        if ($itemId <= 0 && $materialId > 0 && $this->db->table_exists('inv_stock_movement_log')) {
            $latestRow = $this->db->query("
                SELECT l.item_id
                FROM inv_stock_movement_log l
                LEFT JOIN mst_item i ON i.id = l.item_id
                WHERE COALESCE(l.material_id, i.material_id) = {$materialId}
                  AND l.movement_type = 'PURCHASE_IN'
                  AND l.item_id IS NOT NULL
                ORDER BY l.movement_date DESC, l.id DESC
                LIMIT 1
            ")->row_array();
            if (!empty($latestRow['item_id'])) {
                $itemId = (int)$latestRow['item_id'];
            }
        }

        $preselected = null;
        if ($itemId > 0) {
            $preselected = $this->db
                ->select('i.id, i.item_name, i.item_code, m.id AS material_id, m.material_name, cat.name AS category_name, COALESCE(cu.code, cu.name) AS content_uom, COALESCE(bu.code, bu.name) AS buy_uom', false)
                ->from('mst_item i')
                ->join('mst_material m', 'm.id = i.material_id', 'left')
                ->join('mst_item_category cat', 'cat.id = i.item_category_id', 'left')
                ->join('mst_uom cu', 'cu.id = i.content_uom_id', 'left')
                ->join('mst_uom bu', 'bu.id = i.buy_uom_id', 'left')
                ->where('i.id', $itemId)
                ->get()->row_array() ?: null;
        }

        $this->render('purchase/item_price_history', [
            'title'          => 'Riwayat Harga Item',
            'active_menu'    => 'purchase.material.price_history',
            'preselected'    => $preselected,
            'preselected_id' => $itemId,
            'from_material_id' => $materialId,
        ]);
    }

    public function item_price_history_item_search()
    {
        $this->require_permission(self::PAGE_ORDER, 'view');
        $q = trim((string)$this->input->get('q', true));
        if ($q === '') {
            $this->jsonOk(['rows' => []]);
            return;
        }

        $rows = $this->db
            ->select('i.id, i.item_name, i.item_code, m.material_name, cat.name AS category_name, COALESCE(cu.code, cu.name) AS content_uom, COALESCE(bu.code, bu.name) AS buy_uom', false)
            ->from('mst_item i')
            ->join('mst_material m', 'm.id = i.material_id', 'left')
            ->join('mst_item_category cat', 'cat.id = i.item_category_id', 'left')
            ->join('mst_uom cu', 'cu.id = i.content_uom_id', 'left')
            ->join('mst_uom bu', 'bu.id = i.buy_uom_id', 'left')
            ->group_start()
                ->like('i.item_name', $q)
                ->or_like('i.item_code', $q)
                ->or_like('m.material_name', $q)
            ->group_end()
            ->order_by('i.item_name', 'ASC')
            ->limit(15)
            ->get()->result_array();

        $this->jsonOk(['rows' => $rows]);
    }

    public function item_price_history_data()
    {
        $this->require_permission(self::PAGE_ORDER, 'view');
        $itemId = max(0, (int)$this->input->get('item_id', true));
        $limit  = min(200, max(5, (int)($this->input->get('limit', true) ?: 20)));
        $mode   = in_array($this->input->get('mode', true), ['hpp', 'buy'], true)
                ? $this->input->get('mode', true) : 'hpp';

        if ($itemId <= 0 || !$this->db->table_exists('inv_stock_movement_log')) {
            $this->jsonOk(['rows' => [], 'meta' => ['total' => 0]]);
            return;
        }

        $rows = $this->db->query("
            SELECT
                l.id,
                l.movement_date,
                l.item_id,
                COALESCE(l.profile_name, i.item_name, '') AS item_name,
                COALESCE(l.profile_brand, '') AS brand,
                l.unit_cost,
                ROUND(l.unit_cost * COALESCE(l.profile_content_per_buy, 1), 4) AS price_per_buy,
                l.qty_buy_delta,
                l.qty_content_delta,
                COALESCE(l.profile_buy_uom_code, bu.code, 'pack') AS buy_uom,
                COALESCE(l.profile_content_uom_code, cu.code, '') AS content_uom,
                COALESCE(l.profile_content_per_buy, 0) AS content_per_buy,
                d.name AS division_name
            FROM inv_stock_movement_log l
            LEFT JOIN mst_item i ON i.id = l.item_id
            LEFT JOIN mst_operational_division d ON d.id = l.division_id
            LEFT JOIN mst_uom bu ON bu.id = l.buy_uom_id
            LEFT JOIN mst_uom cu ON cu.id = l.content_uom_id
            WHERE l.item_id = {$itemId}
              AND l.movement_type = 'PURCHASE_IN'
            ORDER BY l.movement_date DESC, l.id DESC
            LIMIT {$limit}
        ")->result_array();

        $total = (int)($this->db->query("
            SELECT COUNT(*) AS cnt FROM inv_stock_movement_log
            WHERE item_id = {$itemId} AND movement_type = 'PURCHASE_IN'
        ")->row_array()['cnt'] ?? 0);

        $this->jsonOk(['rows' => $rows, 'meta' => ['total' => $total, 'limit' => $limit, 'mode' => $mode]]);
    }

    public function stock_warehouse_opname_monthly()
    {
        $pageCode = $this->can('inventory.stock.opname.warehouse.monthly', 'view')
            ? 'inventory.stock.opname.warehouse.monthly'
            : self::PAGE_STOCK_WAREHOUSE;
        $this->require_permission($pageCode, 'view');

        $month = trim((string)$this->input->get('month', true));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $filters = [
            'month' => $month,
            'q'     => trim((string)$this->input->get('q', true)),
        ];
        $rows = $this->Purchase_model->list_warehouse_monthly_opname($filters, 500);
        $this->render('purchase/stock_warehouse_opname_monthly_index', [
            'page_title'   => 'Stok Opname Bulanan Gudang',
            'active_menu'  => 'inventory.stock.opname.warehouse.monthly',
            'rows'         => $rows,
            'filters'      => $filters,
            'generate_url' => site_url('inventory/stock/opname/generate'),
        ]);
    }

    public function stock_division_opname_monthly()
    {
        $pageCode = $this->can('inventory.stock.opname.division.monthly', 'view')
            ? 'inventory.stock.opname.division.monthly'
            : self::PAGE_STOCK_DIVISION;
        $this->require_permission($pageCode, 'view');

        $month = trim((string)$this->input->get('month', true));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $limitParam = (int)$this->input->get('limit', true);
        $limit = $limitParam > 0 ? min($limitParam, 2000) : 200;
        $filters = [
            'month'            => $month,
            'division_id'      => (int)$this->input->get('division_id', true),
            'destination_type' => strtoupper(trim((string)$this->input->get('destination_type', true))),
            'q'                => trim((string)$this->input->get('q', true)),
            'limit'            => $limit,
        ];
        $rows = $this->Purchase_model->list_division_monthly_opname($filters, $limit);
        $this->render('purchase/stock_division_opname_monthly_index', [
            'page_title'   => 'Opname Bahan Baku',
            'active_menu'  => 'inventory.stock.opname.division.monthly',
            'rows'         => $rows,
            'filters'      => $filters,
            'divisions'    => $this->Purchase_model->list_active_operational_divisions(),
            'generate_url' => site_url('inventory/stock/opname/generate'),
        ]);
    }
}
