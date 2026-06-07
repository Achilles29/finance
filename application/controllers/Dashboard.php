<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends MY_Controller
{
    private const PAGE_INDEX = 'dashboard.index';

    /** @var array<string,bool> */
    private $dashboardTableReadyCache = [];

    /** @var array<string,bool> */
    private $registeredPageCache = [];

    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $this->require_registered_page_permission(self::PAGE_INDEX);

        $filters = $this->dashboard_filters();
        $stockCards = $this->dashboard_stock_cards();
        $salesOverview = $this->dashboard_pos_sales_overview($filters);
        $data = [
            'title'       => 'Dashboard',
            'active_menu' => 'dashboard',
            'filters' => $filters,
            'kpi' => $this->dashboard_kpi($filters, $stockCards, $salesOverview),
            'trend' => $this->dashboard_trend_series($filters),
            'pos_status_rows' => $this->dashboard_pos_status_rows($filters),
            'pos_scope_rows' => $this->dashboard_pos_scope_rows($filters),
            'purchase_status_rows' => $this->dashboard_purchase_status_rows($filters),
            'store_request_status_rows' => $this->dashboard_store_request_status_rows($filters),
            'stock_cards' => $stockCards,
            'critical_stock_rows' => $this->dashboard_critical_stock_rows(),
            'recent_activity' => $this->dashboard_recent_activity($filters),
        ];

        $this->render('dashboard/index', $data);
    }

    private function require_registered_page_permission(string $pageCode): void
    {
        if ($this->is_registered_page($pageCode)) {
            $this->require_permission($pageCode, 'view');
        }
    }

    private function is_registered_page(string $pageCode): bool
    {
        if (!array_key_exists($pageCode, $this->registeredPageCache)) {
            $exists = $this->db
                ->select('id')
                ->from('sys_page')
                ->where('page_code', $pageCode)
                ->where('is_active', 1)
                ->limit(1)
                ->get()
                ->row_array();

            $this->registeredPageCache[$pageCode] = !empty($exists);
        }

        return $this->registeredPageCache[$pageCode];
    }

    private function dashboard_filters(): array
    {
        $today = date('Y-m-d');
        $period = strtolower(trim((string)$this->input->get('period', true)));
        if (!in_array($period, ['today', '7', '30', 'custom'], true)) {
            $period = 'today';
        }

        $dateFrom = $today;
        $dateTo = $today;
        $periodLabel = 'Hari Ini';

        if ($period === '7') {
            $dateFrom = date('Y-m-d', strtotime('-6 days', strtotime($today)));
            $periodLabel = '7 Hari Terakhir';
        } elseif ($period === '30') {
            $dateFrom = date('Y-m-d', strtotime('-29 days', strtotime($today)));
            $periodLabel = '30 Hari Terakhir';
        } elseif ($period === 'custom') {
            $rawFrom = trim((string)$this->input->get('date_from', true));
            $rawTo = trim((string)$this->input->get('date_to', true));
            if ($this->is_valid_date($rawFrom) && $this->is_valid_date($rawTo)) {
                $dateFrom = $rawFrom;
                $dateTo = $rawTo;
                if ($dateFrom > $dateTo) {
                    $tmp = $dateFrom;
                    $dateFrom = $dateTo;
                    $dateTo = $tmp;
                }
                $periodLabel = date('d-m-Y', strtotime($dateFrom)) . ' s/d ' . date('d-m-Y', strtotime($dateTo));
            } else {
                $period = 'today';
            }
        }

        return [
            'period' => $period,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'period_label' => $periodLabel,
        ];
    }

    private function is_valid_date(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        [$year, $month, $day] = array_map('intval', explode('-', $value));
        return checkdate($month, $day, $year);
    }

    private function dashboard_sales_statuses(): array
    {
        return ['PAID_PARTIAL', 'PAID', 'IN_KITCHEN', 'READY', 'SERVED'];
    }

    private function dashboard_pos_excluded_statuses(): array
    {
        return ['DRAFT', 'PENDING', 'VOID'];
    }

    private function dashboard_pending_sr_statuses(): array
    {
        return ['DRAFT', 'SUBMITTED', 'APPROVED', 'PARTIAL_FULFILLED'];
    }

    private function dashboard_active_po_statuses(): array
    {
        return ['DRAFT', 'APPROVED', 'ORDERED', 'PARTIAL_RECEIVED'];
    }

    private function dashboard_kpi(array $filters, array $stockCards, array $salesOverview): array
    {
        $kpi = [
            'transaction_count' => 0,
            'sales_total' => 0.0,
            'gross_sales_total' => 0.0,
            'refund_total' => 0.0,
            'critical_stock_total' => 0,
            'present_employee_count' => 0,
            'open_purchase_order_count' => 0,
            'pending_store_request_count' => 0,
        ];

        $kpi['transaction_count'] = (int)($salesOverview['transaction_count'] ?? 0);
        $kpi['gross_sales_total'] = (float)($salesOverview['gross_sales_total'] ?? 0);
        $kpi['refund_total'] = (float)($salesOverview['refund_total'] ?? 0);
        $kpi['sales_total'] = (float)($salesOverview['net_sales_total'] ?? 0);

        if ($this->dashboard_table_ready('att_daily')) {
            $kpi['present_employee_count'] = (int)$this->db
                ->select('COUNT(DISTINCT employee_id) AS total', false)
                ->from('att_daily')
                ->where('attendance_date >=', $filters['date_from'])
                ->where('attendance_date <=', $filters['date_to'])
                ->where_in('attendance_status', ['PRESENT', 'LATE', 'HOLIDAY'])
                ->get()->row('total');
        }

        if ($this->dashboard_table_ready('pur_purchase_order')) {
            $kpi['open_purchase_order_count'] = (int)$this->db
                ->select('COUNT(*) AS total', false)
                ->from('pur_purchase_order')
                ->where('request_date >=', $filters['date_from'])
                ->where('request_date <=', $filters['date_to'])
                ->where_in('status', $this->dashboard_active_po_statuses())
                ->get()->row('total');
        }

        if ($this->dashboard_table_ready('pur_store_request')) {
            $kpi['pending_store_request_count'] = (int)$this->db
                ->select('COUNT(*) AS total', false)
                ->from('pur_store_request')
                ->where('request_date >=', $filters['date_from'])
                ->where('request_date <=', $filters['date_to'])
                ->where_in('status', $this->dashboard_pending_sr_statuses())
                ->get()->row('total');
        }
        foreach ($stockCards as $stockCard) {
            $kpi['critical_stock_total'] += (int)($stockCard['critical_count'] ?? 0);
        }

        return $kpi;
    }

    private function dashboard_trend_series(array $filters): array
    {
        $labels = [];
        $sales = [];
        $refunds = [];
        $orders = [];
        $statusMap = [];

        if ($this->dashboard_table_ready('pos_order')) {
            $eventDateExpr = $this->dashboard_pos_event_date_expr('o');
            $this->db
                ->select($eventDateExpr . ' AS day_key', false)
                ->select('COUNT(DISTINCT o.id) AS order_count', false)
                ->select('COALESCE(SUM(o.paid_total),0) AS gross_sales_total', false)
                ->from('pos_order o')
                ->where_not_in('o.status', $this->dashboard_pos_excluded_statuses())
                ->where($eventDateExpr . ' >=', $filters['date_from'], false)
                ->where($eventDateExpr . ' <=', $filters['date_to'], false);

            if ($this->dashboard_pos_refund_available()) {
                $this->db
                    ->select('COALESCE(SUM(COALESCE(rf.refund_amount,0)),0) AS refund_total', false)
                    ->join($this->dashboard_pos_refund_subquery() . ' rf', 'rf.order_id = o.id', 'left', false);
            } else {
                $this->db->select('0 AS refund_total', false);
            }

            $rows = $this->db
                ->group_by($eventDateExpr, false)
                ->order_by('day_key', 'ASC')
                ->get()->result_array();
            foreach ($rows as $row) {
                $grossSalesTotal = (float)($row['gross_sales_total'] ?? 0);
                $refundTotal = (float)($row['refund_total'] ?? 0);
                $statusMap[(string)($row['day_key'] ?? '')] = [
                    'orders' => (int)($row['order_count'] ?? 0),
                    'sales' => max(0, $grossSalesTotal - $refundTotal),
                    'refunds' => $refundTotal,
                ];
            }
        }

        $period = new DatePeriod(
            new DateTime($filters['date_from']),
            new DateInterval('P1D'),
            (new DateTime($filters['date_to']))->modify('+1 day')
        );
        foreach ($period as $dateObj) {
            $key = $dateObj->format('Y-m-d');
            $labels[] = $dateObj->format('d M');
            $sales[] = (float)($statusMap[$key]['sales'] ?? 0);
            $refunds[] = (float)($statusMap[$key]['refunds'] ?? 0);
            $orders[] = (int)($statusMap[$key]['orders'] ?? 0);
        }

        return [
            'labels' => $labels,
            'sales' => $sales,
            'refunds' => $refunds,
            'orders' => $orders,
        ];
    }

    private function dashboard_pos_status_rows(array $filters): array
    {
        if (!$this->dashboard_table_ready('pos_order')) {
            return [];
        }

        $eventDateExpr = $this->dashboard_pos_event_date_expr();

        return $this->db
            ->select('status, COUNT(*) AS total', false)
            ->from('pos_order')
            ->where_not_in('status', $this->dashboard_pos_excluded_statuses())
            ->where($eventDateExpr . ' >=', $filters['date_from'], false)
            ->where($eventDateExpr . ' <=', $filters['date_to'], false)
            ->group_by('status')
            ->order_by('total', 'DESC')
            ->get()->result_array();
    }

    private function dashboard_pos_sales_overview(array $filters, ?string $scope = null): array
    {
        $overview = [
            'transaction_count' => 0,
            'gross_sales_total' => 0.0,
            'refund_total' => 0.0,
            'net_sales_total' => 0.0,
        ];
        if (!$this->dashboard_table_ready('pos_order')) {
            return $overview;
        }

        $eventDateExpr = $this->dashboard_pos_event_date_expr('o');
        $this->db
            ->select('COUNT(DISTINCT o.id) AS transaction_count', false)
            ->select('COALESCE(SUM(o.paid_total),0) AS gross_sales_total', false)
            ->from('pos_order o')
            ->where_not_in('o.status', $this->dashboard_pos_excluded_statuses())
            ->where($eventDateExpr . ' >=', $filters['date_from'], false)
            ->where($eventDateExpr . ' <=', $filters['date_to'], false);

        if ($scope !== null && $scope !== '') {
            $this->db->where('o.order_scope', strtoupper($scope));
        }

        if ($this->dashboard_pos_refund_available()) {
            $this->db
                ->select('COALESCE(SUM(COALESCE(rf.refund_amount,0)),0) AS refund_total', false)
                ->join($this->dashboard_pos_refund_subquery() . ' rf', 'rf.order_id = o.id', 'left', false);
        } else {
            $this->db->select('0 AS refund_total', false);
        }

        $row = $this->db->get()->row_array() ?: [];
        $overview['transaction_count'] = (int)($row['transaction_count'] ?? 0);
        $overview['gross_sales_total'] = (float)($row['gross_sales_total'] ?? 0);
        $overview['refund_total'] = (float)($row['refund_total'] ?? 0);
        $overview['net_sales_total'] = max(0, $overview['gross_sales_total'] - $overview['refund_total']);

        return $overview;
    }

    private function dashboard_pos_scope_rows(array $filters): array
    {
        $scopes = ['REGULAR', 'EVENT'];
        $labels = [
            'REGULAR' => 'Reguler',
            'EVENT' => 'Event',
        ];
        $rows = [];

        foreach ($scopes as $scope) {
            $overview = $this->dashboard_pos_sales_overview($filters, $scope);
            $rows[] = [
                'scope_code' => $scope,
                'scope_label' => $labels[$scope] ?? $scope,
                'transaction_count' => (int)($overview['transaction_count'] ?? 0),
                'gross_sales_total' => (float)($overview['gross_sales_total'] ?? 0),
                'refund_total' => (float)($overview['refund_total'] ?? 0),
                'net_sales_total' => (float)($overview['net_sales_total'] ?? 0),
            ];
        }

        return $rows;
    }

    private function dashboard_pos_event_date_expr(string $alias = 'pos_order'): string
    {
        return 'DATE(COALESCE(' . $alias . '.paid_at, ' . $alias . '.confirmed_at, ' . $alias . '.ordered_at, ' . $alias . '.created_at))';
    }

    private function dashboard_pos_refund_available(): bool
    {
        return $this->dashboard_table_ready('pos_refund_line') && $this->dashboard_table_ready('pos_order_line');
    }

    private function dashboard_pos_refund_subquery(): string
    {
        return "(
            SELECT l.order_id,
                   COALESCE(SUM(rl.amount_refunded), 0) AS refund_amount
            FROM pos_refund_line rl
            INNER JOIN pos_order_line l ON l.id = rl.order_line_id
            GROUP BY l.order_id
        )";
    }

    private function dashboard_purchase_status_rows(array $filters): array
    {
        if (!$this->dashboard_table_ready('pur_purchase_order')) {
            return [];
        }

        return $this->db
            ->select('status, COUNT(*) AS total, COALESCE(SUM(grand_total),0) AS grand_total', false)
            ->from('pur_purchase_order')
            ->where('request_date >=', $filters['date_from'])
            ->where('request_date <=', $filters['date_to'])
            ->group_by('status')
            ->order_by('total', 'DESC')
            ->get()->result_array();
    }

    private function dashboard_store_request_status_rows(array $filters): array
    {
        if (!$this->dashboard_table_ready('pur_store_request')) {
            return [];
        }

        return $this->db
            ->select('status, COUNT(*) AS total', false)
            ->from('pur_store_request')
            ->where('request_date >=', $filters['date_from'])
            ->where('request_date <=', $filters['date_to'])
            ->group_by('status')
            ->order_by('total', 'DESC')
            ->get()->result_array();
    }

    private function dashboard_stock_cards(): array
    {
        $cards = [];

        $warehouseSummary = $this->dashboard_warehouse_stock_summary();
        if ($warehouseSummary !== null) {
            $cards[] = [
                'code' => 'warehouse',
                'label' => 'Stok Gudang',
                'total_rows' => (int)($warehouseSummary['total_rows'] ?? 0),
                'critical_count' => (int)($warehouseSummary['critical_count'] ?? 0),
                'total_value' => (float)($warehouseSummary['total_value'] ?? 0),
            ];
        }

        $divisionSummary = $this->dashboard_division_stock_summary();
        if ($divisionSummary !== null) {
            $cards[] = [
                'code' => 'division',
                'label' => 'Stok Divisi',
                'total_rows' => (int)($divisionSummary['total_rows'] ?? 0),
                'critical_count' => (int)($divisionSummary['critical_count'] ?? 0),
                'total_value' => (float)($divisionSummary['total_value'] ?? 0),
            ];
        }

        $componentSummary = $this->dashboard_component_stock_summary();
        if ($componentSummary !== null) {
            $cards[] = [
                'code' => 'component',
                'label' => 'Base/Prepare',
                'total_rows' => (int)($componentSummary['total_rows'] ?? 0),
                'critical_count' => (int)($componentSummary['critical_count'] ?? 0),
                'total_value' => (float)($componentSummary['total_value'] ?? 0),
            ];
        }

        return $cards;
    }

    private function dashboard_critical_stock_rows(): array
    {
        $rows = [];
        $divisionNameColumn = $this->dashboard_division_name_column();

        $warehouseRows = $this->dashboard_warehouse_critical_rows();
        if (!empty($warehouseRows)) {
            $rows = array_merge($rows, $warehouseRows);
        }

        $divisionRows = $this->dashboard_division_critical_rows($divisionNameColumn);
        if (!empty($divisionRows)) {
            $rows = array_merge($rows, $divisionRows);
        }

        $rows = array_merge($rows, $this->dashboard_component_critical_rows($divisionNameColumn));

        usort($rows, static function (array $left, array $right): int {
            $leftGap = (float)($left['threshold_qty'] ?? 0) - (float)($left['qty_balance'] ?? 0);
            $rightGap = (float)($right['threshold_qty'] ?? 0) - (float)($right['qty_balance'] ?? 0);
            if ($leftGap === $rightGap) {
                return strcmp((string)($left['item_name'] ?? ''), (string)($right['item_name'] ?? ''));
            }
            return $rightGap <=> $leftGap;
        });

        return array_slice($rows, 0, 10);
    }

    private function dashboard_warehouse_stock_summary(): ?array
    {
        $query = $this->dashboard_warehouse_monthly_query(
            "SELECT COUNT(*) AS total_rows,
                    COALESCE(SUM(CASE
                        WHEN s.closing_qty_content <= GREATEST(COALESCE(NULLIF(m.reorder_level_content, 0), NULLIF(i.min_stock_content, 0), 0), 0)
                        THEN 1 ELSE 0 END), 0) AS critical_count,
                    COALESCE(SUM(s.total_value), 0) AS total_value
             FROM inv_warehouse_monthly_stock s
             INNER JOIN ({latest_month_subquery}) lm
                ON lm.identity_key = s.identity_key
               AND lm.month_key = s.month_key
             LEFT JOIN mst_item i ON i.id = s.item_id
             LEFT JOIN mst_material m ON m.id = COALESCE(s.material_id, i.material_id)"
        );

        return $query !== null ? $query->row_array() : null;
    }

    private function dashboard_division_stock_summary(): ?array
    {
        $query = $this->dashboard_division_monthly_query(
            "SELECT COUNT(*) AS total_rows,
                    COALESCE(SUM(CASE
                        WHEN s.closing_qty_content <= GREATEST(COALESCE(NULLIF(m.reorder_level_content, 0), NULLIF(i.min_stock_content, 0), 0), 0)
                        THEN 1 ELSE 0 END), 0) AS critical_count,
                    COALESCE(SUM(s.total_value), 0) AS total_value
             FROM inv_division_monthly_stock s
             INNER JOIN ({latest_month_subquery}) lm
                ON lm.division_id = s.division_id
               AND lm.destination_type = s.destination_type
               AND lm.identity_key = s.identity_key
               AND lm.month_key = s.month_key
             LEFT JOIN mst_item i ON i.id = s.item_id
             LEFT JOIN mst_material m ON m.id = COALESCE(s.material_id, i.material_id)"
        );

        return $query !== null ? $query->row_array() : null;
    }

    private function dashboard_warehouse_critical_rows(): array
    {
        $query = $this->dashboard_warehouse_monthly_query(
            "SELECT 'Gudang' AS stock_scope,
                    COALESCE(s.profile_name, m.material_name, i.item_name, CONCAT('Item #', s.item_id)) AS item_name,
                    'Gudang Pusat' AS location_name,
                    s.closing_qty_content AS qty_balance,
                    GREATEST(COALESCE(NULLIF(m.reorder_level_content, 0), NULLIF(i.min_stock_content, 0), 0), 0) AS threshold_qty,
                    COALESCE(s.total_value, 0) AS total_value
             FROM inv_warehouse_monthly_stock s
             INNER JOIN ({latest_month_subquery}) lm
                ON lm.identity_key = s.identity_key
               AND lm.month_key = s.month_key
             LEFT JOIN mst_item i ON i.id = s.item_id
             LEFT JOIN mst_material m ON m.id = COALESCE(s.material_id, i.material_id)
             WHERE s.closing_qty_content <= GREATEST(COALESCE(NULLIF(m.reorder_level_content, 0), NULLIF(i.min_stock_content, 0), 0), 0)
             ORDER BY (GREATEST(COALESCE(NULLIF(m.reorder_level_content, 0), NULLIF(i.min_stock_content, 0), 0), 0) - s.closing_qty_content) DESC,
                      s.closing_qty_content ASC
             LIMIT 5"
        );

        return $query !== null ? $query->result_array() : [];
    }

    private function dashboard_division_critical_rows(?string $divisionNameColumn): array
    {
        $divisionLocationSelect = $divisionNameColumn !== null
            ? ('COALESCE(d.' . $divisionNameColumn . ', s.destination_type, CONCAT(\'Divisi #\', s.division_id))')
            : "COALESCE(s.destination_type, CONCAT('Divisi #', s.division_id))";

        $query = $this->dashboard_division_monthly_query(
            "SELECT 'Divisi' AS stock_scope,
                    COALESCE(s.profile_name, m.material_name, i.item_name, CONCAT('Item #', s.item_id)) AS item_name,
                    {$divisionLocationSelect} AS location_name,
                    s.closing_qty_content AS qty_balance,
                    GREATEST(COALESCE(NULLIF(m.reorder_level_content, 0), NULLIF(i.min_stock_content, 0), 0), 0) AS threshold_qty,
                    COALESCE(s.total_value, 0) AS total_value
             FROM inv_division_monthly_stock s
             INNER JOIN ({latest_month_subquery}) lm
                ON lm.division_id = s.division_id
               AND lm.destination_type = s.destination_type
               AND lm.identity_key = s.identity_key
               AND lm.month_key = s.month_key
             LEFT JOIN mst_item i ON i.id = s.item_id
             LEFT JOIN mst_material m ON m.id = COALESCE(s.material_id, i.material_id)
             LEFT JOIN mst_operational_division d ON d.id = s.division_id
             WHERE s.closing_qty_content <= GREATEST(COALESCE(NULLIF(m.reorder_level_content, 0), NULLIF(i.min_stock_content, 0), 0), 0)
             ORDER BY (GREATEST(COALESCE(NULLIF(m.reorder_level_content, 0), NULLIF(i.min_stock_content, 0), 0), 0) - s.closing_qty_content) DESC,
                      s.closing_qty_content ASC
             LIMIT 5"
        );

        return $query !== null ? $query->result_array() : [];
    }

    private function dashboard_division_name_column(): ?string
    {
        if (!$this->dashboard_table_ready('mst_operational_division')) {
            return null;
        }

        if ($this->db->field_exists('division_name', 'mst_operational_division')) {
            return 'division_name';
        }

        if ($this->db->field_exists('name', 'mst_operational_division')) {
            return 'name';
        }

        return null;
    }

    private function dashboard_component_stock_summary(): ?array
    {
        $componentQuery = $this->dashboard_component_monthly_query(
            "SELECT COUNT(*) AS total_rows,
                    COALESCE(SUM(CASE
                        WHEN s.closing_qty <= GREATEST(COALESCE(c.min_stock, 0), 0)
                        THEN 1 ELSE 0 END), 0) AS critical_count,
                    COALESCE(SUM(s.total_value), 0) AS total_value
             FROM inv_component_monthly_stock s
             INNER JOIN ({latest_month_subquery}) lm
                ON lm.location_type = s.location_type
               AND lm.division_id <=> s.division_id
               AND lm.component_id = s.component_id
               AND lm.uom_id = s.uom_id
               AND lm.month_key = s.month_key
             LEFT JOIN mst_component c ON c.id = s.component_id"
        );

        if ($componentQuery === null) {
            return null;
        }

        return $componentQuery->row_array();
    }

    private function dashboard_component_critical_rows(?string $divisionNameColumn): array
    {
        $componentLocationSelect = $divisionNameColumn !== null
            ? ('COALESCE(d.' . $divisionNameColumn . ', s.location_type, CONCAT(\'Divisi #\', s.division_id))')
            : "COALESCE(s.location_type, CONCAT('Divisi #', s.division_id))";

        $componentQuery = $this->dashboard_component_monthly_query(
            "SELECT 'Base/Prepare' AS stock_scope,
                    COALESCE(c.component_name, CONCAT('Component #', s.component_id)) AS item_name,
                    {$componentLocationSelect} AS location_name,
                    s.closing_qty AS qty_balance,
                    GREATEST(COALESCE(c.min_stock, 0), 0) AS threshold_qty,
                    COALESCE(s.total_value, 0) AS total_value
             FROM inv_component_monthly_stock s
             INNER JOIN ({latest_month_subquery}) lm
                ON lm.location_type = s.location_type
               AND lm.division_id <=> s.division_id
               AND lm.component_id = s.component_id
               AND lm.uom_id = s.uom_id
               AND lm.month_key = s.month_key
             LEFT JOIN mst_component c ON c.id = s.component_id
             LEFT JOIN mst_operational_division d ON d.id = s.division_id
             WHERE s.closing_qty <= GREATEST(COALESCE(c.min_stock, 0), 0)
             ORDER BY (GREATEST(COALESCE(c.min_stock, 0), 0) - s.closing_qty) DESC,
                      s.closing_qty ASC
             LIMIT 5"
        );

        return $componentQuery !== null ? $componentQuery->result_array() : [];
    }

    private function dashboard_component_monthly_query(string $sql)
    {
        if (!$this->dashboard_table_ready('inv_component_monthly_stock')) {
            return null;
        }

        $targetMonth = $this->db->escape(date('Y-m-01'));
        $latestMonthSubquery = "SELECT location_type, division_id, component_id, uom_id, MAX(month_key) AS month_key
                                FROM inv_component_monthly_stock
                                WHERE month_key <= {$targetMonth}
                                GROUP BY location_type, division_id, component_id, uom_id";

        return $this->dashboard_safe_query(str_replace('{latest_month_subquery}', $latestMonthSubquery, $sql));
    }

    private function dashboard_warehouse_monthly_query(string $sql)
    {
        if (!$this->dashboard_table_ready('inv_warehouse_monthly_stock')) {
            return null;
        }

        $targetMonth = $this->db->escape(date('Y-m-01'));
        $latestMonthSubquery = "SELECT identity_key, MAX(month_key) AS month_key
                                FROM inv_warehouse_monthly_stock
                                WHERE month_key <= {$targetMonth}
                                GROUP BY identity_key";

        return $this->dashboard_safe_query(str_replace('{latest_month_subquery}', $latestMonthSubquery, $sql));
    }

    private function dashboard_division_monthly_query(string $sql)
    {
        if (!$this->dashboard_table_ready('inv_division_monthly_stock')) {
            return null;
        }

        $targetMonth = $this->db->escape(date('Y-m-01'));
        $latestMonthSubquery = "SELECT division_id, destination_type, identity_key, MAX(month_key) AS month_key
                                FROM inv_division_monthly_stock
                                WHERE month_key <= {$targetMonth}
                                GROUP BY division_id, destination_type, identity_key";

        return $this->dashboard_safe_query(str_replace('{latest_month_subquery}', $latestMonthSubquery, $sql));
    }

    private function dashboard_table_ready(string $table): bool
    {
        if (array_key_exists($table, $this->dashboardTableReadyCache)) {
            return $this->dashboardTableReadyCache[$table];
        }

        if (!$this->db->table_exists($table)) {
            $this->dashboardTableReadyCache[$table] = false;
            return false;
        }

        $protectedTable = $this->db->protect_identifiers($table, true);
        $probe = $this->dashboard_safe_query('SELECT 1 FROM ' . $protectedTable . ' LIMIT 1');
        $isReady = $probe !== null;
        $this->dashboardTableReadyCache[$table] = $isReady;

        return $isReady;
    }

    private function dashboard_safe_query(string $sql)
    {
        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;

        try {
            $query = $this->db->query($sql);
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if ($query === false) {
            $error = $this->db->error();
            if (!empty($error['code']) || !empty($error['message'])) {
                log_message('error', 'Dashboard query failed: [' . ($error['code'] ?? 0) . '] ' . ($error['message'] ?? 'unknown database error'));
            }
            return null;
        }

        return $query;
    }

    private function dashboard_recent_activity(array $filters): array
    {
        $rows = [];

        if ($this->dashboard_table_ready('pos_order')) {
            $posRows = $this->db
                ->select('order_no AS ref_no, status, grand_total AS amount, COALESCE(paid_at, ordered_at, created_at) AS event_at', false)
                ->select($this->db->escape('POS') . ' AS source_label', false)
                ->from('pos_order')
                ->where('DATE(COALESCE(paid_at, ordered_at, created_at)) >=', $filters['date_from'])
                ->where('DATE(COALESCE(paid_at, ordered_at, created_at)) <=', $filters['date_to'])
                ->order_by('event_at', 'DESC')
                ->limit(6)
                ->get()->result_array();
            $rows = array_merge($rows, $posRows);
        }

        if ($this->dashboard_table_ready('pur_purchase_order')) {
            $poRows = $this->db
                ->select('po_no AS ref_no, status, grand_total AS amount, CONCAT(request_date, " 00:00:00") AS event_at', false)
                ->select($this->db->escape('PURCHASE') . ' AS source_label', false)
                ->from('pur_purchase_order')
                ->where('request_date >=', $filters['date_from'])
                ->where('request_date <=', $filters['date_to'])
                ->order_by('request_date', 'DESC')
                ->limit(6)
                ->get()->result_array();
            $rows = array_merge($rows, $poRows);
        }

        if ($this->dashboard_table_ready('inv_stock_movement_log')) {
            $movementRows = $this->db
                ->select('movement_no AS ref_no, movement_type AS status, ABS(qty_content_delta * unit_cost) AS amount, CONCAT(movement_date, " 00:00:00") AS event_at', false)
                ->select($this->db->escape('INVENTORY') . ' AS source_label', false)
                ->from('inv_stock_movement_log')
                ->where('movement_date >=', $filters['date_from'])
                ->where('movement_date <=', $filters['date_to'])
                ->order_by('movement_date', 'DESC')
                ->order_by('id', 'DESC')
                ->limit(6)
                ->get()->result_array();
            $rows = array_merge($rows, $movementRows);
        }

        usort($rows, static function (array $left, array $right): int {
            return strcmp((string)($right['event_at'] ?? ''), (string)($left['event_at'] ?? ''));
        });

        return array_slice($rows, 0, 10);
    }
}
