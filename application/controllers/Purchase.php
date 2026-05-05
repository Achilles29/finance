<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Purchase extends MY_Controller
{
    const PAGE_ORDER = 'purchase.order.index';
    const PAGE_CATALOG = 'purchase.catalog.index';
    const PAGE_ACCOUNT = 'purchase.account.index';
    const PAGE_STOCK_WAREHOUSE = 'purchase.stock.warehouse.index';
    const PAGE_STOCK_DIVISION = 'purchase.stock.division.index';
    const PAGE_STOCK_WAREHOUSE_MATRIX = 'purchase.stock.warehouse.matrix.index';
    const PAGE_STOCK_MATERIAL_MATRIX = 'purchase.stock.material.matrix.index';
    const PAGE_RECEIPT = 'purchase.receipt.index';
    const PAGE_ORDER_LOG = 'purchase.order.log.index';
    const PAGE_REBUILD_IMPACT = 'purchase.rebuild.impact.index';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Purchase_model');
    }

    public function index()
    {
        $this->require_permission(self::PAGE_ORDER, 'view');

        $q = trim((string)$this->input->get('q', true));
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        $tab = strtolower(trim((string)$this->input->get('tab', true)));
        if (!in_array($tab, ['nota', 'rincian'], true)) {
            $tab = 'nota';
        }
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 300) {
            $limit = 100;
        }

        $data = [
            'title' => 'Purchase Order',
            'active_menu' => 'purchase.order',
            'summary' => $this->Purchase_model->get_dashboard_summary(),
            'q' => $q,
            'status' => $status,
            'tab' => $tab,
            'limit' => $limit,
            'status_options' => ['ALL', 'DRAFT', 'APPROVED', 'ORDERED', 'REJECTED', 'PARTIAL_RECEIVED', 'RECEIVED', 'PAID', 'VOID'],
            'rows' => $this->Purchase_model->list_purchase_orders_dashboard($q, $status, $limit),
            'line_rows' => $this->Purchase_model->list_purchase_order_lines_dashboard($q, $status, $limit),
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
            $limit = 300;
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
            'status_options' => ['DRAFT', 'APPROVED', 'ORDERED', 'REJECTED', 'PARTIAL_RECEIVED', 'RECEIVED', 'PAID', 'VOID'],
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
        ];

        $this->render('purchase/order_create', $data);
    }

    public function account_index()
    {
        redirect('master/company-account');
    }

    public function finance_mutation_index()
    {
        $this->require_permission(self::PAGE_ORDER, 'view');

        $accountId = (int)$this->input->get('account_id', true);
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo = trim((string)$this->input->get('date_to', true));
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 1000) {
            $limit = 200;
        }

        $data = [
            'title' => 'Mutasi Keuangan Rekening',
            'active_menu' => 'finance.mutation',
            'accounts' => $this->Purchase_model->list_active_company_accounts(),
            'summary' => $this->Purchase_model->get_account_mutation_summary($accountId, $dateFrom, $dateTo),
            'rows' => $this->Purchase_model->list_account_mutations($accountId, $dateFrom, $dateTo, $limit),
            'filter_account_id' => $accountId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'limit' => $limit,
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
            $limit = 200;
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
        if (!$this->can(self::PAGE_STOCK_WAREHOUSE, 'view')) {
            $this->require_permission(self::PAGE_ORDER, 'view');
        }

        $month = trim((string)$this->input->get('month', true));
        $q = trim((string)$this->input->get('q', true));
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 500) {
            $limit = 200;
        }

        $data = [
            'title' => 'Opening Gudang',
            'active_menu' => 'purchase.stock.warehouse',
            'month' => $month,
            'q' => $q,
            'limit' => $limit,
            'rows' => $this->Purchase_model->list_warehouse_opening_snapshots($month, $q, $limit),
            'items' => $this->Purchase_model->list_opening_items(),
            'uoms' => $this->Purchase_model->list_active_uoms(),
        ];

        $this->render('purchase/stock_opening_index', $data);
    }

    public function stock_opening_store()
    {
        if (!$this->can(self::PAGE_STOCK_WAREHOUSE, 'create')) {
            $this->require_permission(self::PAGE_ORDER, 'create');
        }

        $payload = $this->requestPayload();
        $result = $this->Purchase_model->store_warehouse_opening_and_post(
            $payload,
            (int)($this->current_user['id'] ?? 0),
            (string)$this->input->ip_address()
        );

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal menyimpan opening gudang.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
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
            'title' => 'Stok Bulanan / Daily Gudang',
            'active_menu' => 'purchase.stock.warehouse',
            'month' => $month,
            'q' => $q,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'limit' => $limit,
            'rows' => $this->Purchase_model->list_warehouse_daily_rollup($month, $q, $dateFrom, $dateTo, $limit),
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

        $q = trim((string)$this->input->get('q', true));
        $destinationFilter = strtoupper(trim((string)$this->input->get('destination', true)));
        if ($destinationFilter === '') {
            $destinationFilter = 'ALL';
        }
        $divisionId = (int)$this->input->get('division_id', true);
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo = trim((string)$this->input->get('date_to', true));
        $range = $this->resolveDateRange('', $dateFrom, $dateTo);
        $dateFrom = $range['date_from'];
        $dateTo = $range['date_to'];
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 500) {
            $limit = 200;
        }

        $data = [
            'title' => 'Stok Divisi',
            'active_menu' => 'purchase.stock.division',
            'q' => $q,
            'destination' => $destinationFilter,
            'division_id' => $divisionId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'limit' => $limit,
            'divisions' => $this->Purchase_model->list_active_operational_divisions(),
            'rows' => $this->Purchase_model->list_division_stock($q, $limit, $destinationFilter, $dateFrom, $dateTo, $divisionId > 0 ? $divisionId : null),
        ];

        $this->render('purchase/stock_division_index', $data);
    }

    public function stock_division_movement_index()
    {
        if (!$this->can(self::PAGE_STOCK_DIVISION, 'view')) {
            $this->require_permission(self::PAGE_ORDER, 'view');
        }

        $q = trim((string)$this->input->get('q', true));
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo = trim((string)$this->input->get('date_to', true));
        $range = $this->resolveDateRange('', $dateFrom, $dateTo);
        $dateFrom = $range['date_from'];
        $dateTo = $range['date_to'];
        $divisionId = (int)$this->input->get('division_id', true);
        $destinationFilter = strtoupper(trim((string)$this->input->get('destination', true)));
        if ($destinationFilter === '') {
            $destinationFilter = 'ALL';
        }
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 1000) {
            $limit = 300;
        }

        $data = [
            'title' => 'Keluar Masuk Stok Divisi',
            'active_menu' => 'purchase.stock.division',
            'q' => $q,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'division_id' => $divisionId,
            'destination' => $destinationFilter,
            'limit' => $limit,
            'divisions' => $this->Purchase_model->list_active_operational_divisions(),
            'rows' => $this->Purchase_model->list_stock_movements('DIVISION', $q, $dateFrom, $dateTo, $divisionId > 0 ? $divisionId : null, $limit, $destinationFilter),
        ];

        $this->render('purchase/stock_division_movement_index', $data);
    }

    public function stock_division_daily_index()
    {
        if (!$this->can(self::PAGE_STOCK_DIVISION, 'view')) {
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
        $divisionId = (int)$this->input->get('division_id', true);
        $destinationFilter = strtoupper(trim((string)$this->input->get('destination', true)));
        if ($destinationFilter === '') {
            $destinationFilter = 'ALL';
        }
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 1000) {
            $limit = 300;
        }

        $data = [
            'title' => 'Stok Bulanan / Daily Divisi',
            'active_menu' => 'purchase.stock.division',
            'month' => $month,
            'q' => $q,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'division_id' => $divisionId,
            'destination' => $destinationFilter,
            'limit' => $limit,
            'divisions' => $this->Purchase_model->list_active_operational_divisions(),
            'rows' => $this->Purchase_model->list_division_daily_rollup($month, $q, $divisionId > 0 ? $divisionId : null, $dateFrom, $dateTo, $limit, $destinationFilter),
        ];

        $this->render('purchase/stock_division_daily_index', $data);
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
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 1000) {
            $limit = 120;
        }

        $data = [
            'title' => 'Inventory Material Daily',
            'active_menu' => 'purchase.stock.material.matrix',
            'month' => $month,
            'q' => $q,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'division_id' => $divisionId,
            'destination' => $destinationFilter,
            'limit' => $limit,
            'divisions' => $this->Purchase_model->list_active_operational_divisions(),
            'matrix_url' => site_url('inventory-material-daily/matrix'),
            'detail_url' => site_url('inventory-daily/cell-detail'),
        ];

        $this->render('purchase/inventory_material_daily_index', $data);
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
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 1000) {
            $limit = 120;
        }

        $matrix = $this->Purchase_model->list_material_daily_matrix(
            $month,
            $q,
            $divisionId > 0 ? $divisionId : null,
            $dateFrom,
            $dateTo,
            $limit,
            $destinationFilter
        );

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => true,
                'data' => $matrix,
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

        $catalogRows = $this->Purchase_model->search_catalog_profiles(
            $q,
            $vendorId,
            $lineKind,
            $itemId,
            $materialId,
            $limit
        );
        $fallbackRows = [];

        // Layered search: prioritize purchase catalog (nama + merk).
        // Only when no catalog results, fallback to item name search.
        if (count($catalogRows) === 0) {
            $fallbackRows = $this->Purchase_model->search_master_fallback(
                $q,
                'ITEM',
                $itemId,
                0,
                $limit
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
        $lines = $payload['lines'] ?? [];

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
                'currency_code', 'exchange_rate', 'external_ref_no', 'notes',
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

        return implode('|', [
            $lineKind,
            $itemId,
            $materialId,
            $buyUomId,
            $contentUomId,
            $contentPerBuy,
            $brand,
            $desc,
        ]);
    }
}
