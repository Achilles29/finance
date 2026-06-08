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
        $chartFilters = $this->dashboard_chart_filters();
        $data = [
            'title'       => 'Dashboard',
            'active_menu' => 'dashboard',
            'filters' => $filters,
            'kpi' => $this->dashboard_kpi($filters, $stockCards, $salesOverview),
            'trend' => $this->dashboard_trend_series($chartFilters),
            'chart_filters' => $chartFilters,
            'pos_status_rows' => $this->dashboard_pos_status_rows($filters),
            'pos_scope_rows' => $this->dashboard_pos_scope_rows($filters),
            'stock_breakdown' => $this->dashboard_stock_breakdown(),
            'stock_product_live' => $this->dashboard_stock_product_live(),
            'critical_stock_rows' => $this->dashboard_critical_stock_rows(0),
            'negative_stock_rows' => $this->dashboard_negative_stock_rows(),
            'recent_activity' => $this->dashboard_recent_activity($filters),
        ];

        $this->render('dashboard/index', $data);
    }

    public function product_recipe_stock()
    {
        $productId = (int)$this->input->get('product_id', true);
        if ($productId <= 0) {
            $this->json_error('product_id wajib diisi.', 422);
            return;
        }

        if (!$this->dashboard_table_ready('mst_product_recipe')) {
            $this->json_ok(['recipe' => []]);
            return;
        }

        $hasMaterial = $this->dashboard_table_ready('inv_division_monthly_stock');
        $hasComponent = $this->dashboard_table_ready('inv_component_monthly_stock');
        $targetMonth = date('Y-m-01');

        $sql = "
            SELECT
                r.id,
                r.line_type,
                r.ingredient_role,
                r.qty,
                COALESCE(u.code, '') AS uom_code,
                COALESCE(m.material_name, c.component_name, 'Unknown') AS ingredient_name,
                COALESCE(m.material_code, '') AS material_code,
                r.material_item_id,
                r.component_id
            FROM mst_product_recipe r
            LEFT JOIN mst_uom u ON u.id = r.uom_id
            LEFT JOIN mst_item mi ON mi.id = r.material_item_id
            LEFT JOIN mst_material m ON m.id = mi.material_id
            LEFT JOIN mst_component c ON c.id = r.component_id
            WHERE r.product_id = {$productId}
            ORDER BY r.sort_order, r.line_no
        ";
        $recipeRows = $this->dashboard_safe_query($sql);
        if (!$recipeRows) {
            $this->json_ok(['recipe' => []]);
            return;
        }

        $materialStockMap = [];
        $componentStockMap = [];

        if ($hasMaterial) {
            $matSql = "
                SELECT
                    COALESCE(s.material_id, mi2.material_id) AS material_id,
                    ROUND(SUM(s.closing_qty_content), 4) AS total_qty,
                    COALESCE(s.avg_cost_per_content, 0) AS avg_cost
                FROM inv_division_monthly_stock s
                LEFT JOIN mst_item mi2 ON mi2.id = s.item_id
                INNER JOIN (
                    SELECT division_id, destination_type, identity_key, MAX(month_key) AS max_month
                    FROM inv_division_monthly_stock WHERE month_key <= " . $this->db->escape($targetMonth) . "
                    GROUP BY division_id, destination_type, identity_key
                ) lm ON lm.division_id = s.division_id AND lm.destination_type = s.destination_type
                    AND lm.identity_key = s.identity_key AND lm.max_month = s.month_key
                WHERE COALESCE(s.material_id, mi2.material_id) IS NOT NULL
                GROUP BY COALESCE(s.material_id, mi2.material_id)
            ";
            $matResult = $this->dashboard_safe_query($matSql);
            if ($matResult) {
                foreach ($matResult->result_array() as $r) {
                    $materialStockMap[(int)$r['material_id']] = ['qty' => (float)$r['total_qty'], 'avg_cost' => (float)$r['avg_cost']];
                }
            }
        }

        if ($hasComponent) {
            $compSql = "
                SELECT s.component_id, ROUND(SUM(s.closing_qty), 4) AS total_qty
                FROM inv_component_monthly_stock s
                INNER JOIN (
                    SELECT location_type, division_id, component_id, uom_id, MAX(month_key) AS max_month
                    FROM inv_component_monthly_stock WHERE month_key <= " . $this->db->escape($targetMonth) . "
                    GROUP BY location_type, division_id, component_id, uom_id
                ) lm ON lm.location_type = s.location_type AND lm.division_id <=> s.division_id
                    AND lm.component_id = s.component_id AND lm.uom_id = s.uom_id AND lm.max_month = s.month_key
                GROUP BY s.component_id
            ";
            $compResult = $this->dashboard_safe_query($compSql);
            if ($compResult) {
                foreach ($compResult->result_array() as $r) {
                    $componentStockMap[(int)$r['component_id']] = (float)$r['total_qty'];
                }
            }
        }

        $recipe = [];
        foreach ($recipeRows->result_array() as $r) {
            $stockQty = 0.0;
            if ($r['line_type'] === 'MATERIAL' && !empty($r['material_item_id'])) {
                $matId = (int)$this->db->query("SELECT material_id FROM mst_item WHERE id = " . (int)$r['material_item_id'])->row('material_id');
                $stockQty = (float)($materialStockMap[$matId]['qty'] ?? 0);
            } elseif ($r['line_type'] === 'COMPONENT' && !empty($r['component_id'])) {
                $stockQty = (float)($componentStockMap[(int)$r['component_id']] ?? 0);
            }

            $recipe[] = [
                'ingredient_name' => (string)$r['ingredient_name'],
                'line_type' => (string)$r['line_type'],
                'ingredient_role' => (string)$r['ingredient_role'],
                'qty_per_serve' => (float)$r['qty'],
                'uom_code' => (string)$r['uom_code'],
                'stock_qty' => $stockQty,
                'is_bottleneck' => $stockQty <= 0,
            ];
        }

        $this->json_ok(['recipe' => $recipe]);
    }

    private function dashboard_chart_filters(): array
    {
        return [
            'date_from' => date('Y-m-01'),
            'date_to' => date('Y-m-d'),
            'period_label' => 'Bulan Ini',
        ];
    }

    private function dashboard_stock_product_live(): array
    {
        if (!$this->dashboard_table_ready('pos_product_availability_cache') || !$this->dashboard_table_ready('mst_product')) {
            return ['summary' => [], 'rows' => []];
        }

        $summaryRows = $this->dashboard_safe_query("
            SELECT
                pac.availability_status,
                pd.name AS division_name,
                COUNT(*) AS total
            FROM pos_product_availability_cache pac
            INNER JOIN mst_product p ON p.id = pac.product_id
            INNER JOIN mst_product_category pc ON pc.id = p.product_category_id
            INNER JOIN mst_product_division pd ON pd.id = pc.product_division_id
            GROUP BY pac.availability_status, pd.name
            ORDER BY pd.name, pac.availability_status
        ");

        $summary = ['total' => 0, 'out' => 0, 'limited' => 0, 'available' => 0, 'by_division' => []];
        if ($summaryRows) {
            foreach ($summaryRows->result_array() as $r) {
                $div = (string)($r['division_name'] ?? 'LAIN');
                $status = strtoupper((string)($r['availability_status'] ?? ''));
                $cnt = (int)$r['total'];
                $summary['total'] += $cnt;
                if ($status === 'OUT') {
                    $summary['out'] += $cnt;
                } elseif ($status === 'LIMITED') {
                    $summary['limited'] += $cnt;
                } elseif ($status === 'AVAILABLE') {
                    $summary['available'] += $cnt;
                }
                if (!isset($summary['by_division'][$div])) {
                    $summary['by_division'][$div] = ['out' => 0, 'limited' => 0, 'available' => 0];
                }
                $summary['by_division'][$div][$status === 'OUT' ? 'out' : ($status === 'LIMITED' ? 'limited' : 'available')] += $cnt;
            }
        }

        $productRows = $this->dashboard_safe_query("
            SELECT
                pac.product_id,
                p.product_name,
                pd.name AS division_name,
                pac.availability_status,
                ROUND(pac.estimated_available_qty, 2) AS qty,
                COALESCE(u.code, '') AS uom_code,
                pac.bottleneck_name_snapshot,
                pac.bottleneck_kind,
                pac.hpp_live_snapshot,
                pac.is_dirty
            FROM pos_product_availability_cache pac
            INNER JOIN mst_product p ON p.id = pac.product_id
            INNER JOIN mst_product_category pc ON pc.id = p.product_category_id
            INNER JOIN mst_product_division pd ON pd.id = pc.product_division_id
            LEFT JOIN mst_uom u ON u.id = p.uom_id
            ORDER BY
                FIELD(pac.availability_status, 'OUT', 'LIMITED', 'AVAILABLE'),
                pd.name,
                p.product_name
            LIMIT 200
        ");

        $rows = $productRows ? $productRows->result_array() : [];
        return ['summary' => $summary, 'rows' => $rows];
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
        if (!in_array($period, ['today', 'month', '7', '30', 'custom'], true)) {
            $period = 'today';
        }

        $dateFrom = $today;
        $dateTo = $today;
        $periodLabel = 'Hari Ini';

        if ($period === 'month') {
            $dateFrom = date('Y-m-01');
            $dateTo = $today;
            $periodLabel = 'Bulan Ini';
        } elseif ($period === '7') {
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
            'total_nilai_po' => 0.0,
            'total_nilai_sr' => 0.0,
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
            $kpi['total_nilai_po'] = (float)$this->db
                ->select('COALESCE(SUM(grand_total), 0) AS total', false)
                ->from('pur_purchase_order')
                ->where('request_date >=', $filters['date_from'])
                ->where('request_date <=', $filters['date_to'])
                ->where('status !=', 'VOID')
                ->get()->row('total');
        }

        if ($this->dashboard_table_ready('pur_store_request') && $this->dashboard_table_ready('pur_store_request_line')) {
            $kpi['total_nilai_sr'] = $this->dashboard_total_nilai_sr($filters);
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
        $poMap = [];
        $srMap = [];
        $statusMap = [];

        if ($this->dashboard_table_ready('pos_order')) {
            $eventDateExpr = $this->dashboard_pos_event_date_expr('o');
            $this->db
                ->select($eventDateExpr . ' AS day_key', false)
                ->select('COUNT(DISTINCT o.id) AS order_count', false)
                ->select('COALESCE(SUM(o.paid_total),0) AS gross_sales_total', false)
                ->from('pos_order o')
                ->where_not_in('o.status', $this->dashboard_pos_excluded_statuses())
                ->where($eventDateExpr . " >= '" . $filters['date_from'] . "'", null, false)
                ->where($eventDateExpr . " <= '" . $filters['date_to'] . "'", null, false);

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

        if ($this->dashboard_table_ready('pur_purchase_order')) {
            $poRows = $this->db
                ->select('request_date AS day_key, COALESCE(SUM(grand_total), 0) AS po_total', false)
                ->from('pur_purchase_order')
                ->where('request_date >=', $filters['date_from'])
                ->where('request_date <=', $filters['date_to'])
                ->where('status !=', 'VOID')
                ->group_by('request_date')
                ->get()->result_array();
            foreach ($poRows as $row) {
                $poMap[(string)($row['day_key'] ?? '')] = (float)($row['po_total'] ?? 0);
            }
        }

        if ($this->dashboard_table_ready('pur_store_request')) {
            $srRows = $this->db
                ->select('request_date AS day_key, COUNT(*) AS sr_count', false)
                ->from('pur_store_request')
                ->where('request_date >=', $filters['date_from'])
                ->where('request_date <=', $filters['date_to'])
                ->where('status !=', 'VOID')
                ->group_by('request_date')
                ->get()->result_array();
            foreach ($srRows as $row) {
                $srMap[(string)($row['day_key'] ?? '')] = (int)($row['sr_count'] ?? 0);
            }
        }

        $period = new DatePeriod(
            new DateTime($filters['date_from']),
            new DateInterval('P1D'),
            (new DateTime($filters['date_to']))->modify('+1 day')
        );
        $po = [];
        $sr = [];
        foreach ($period as $dateObj) {
            $key = $dateObj->format('Y-m-d');
            $labels[] = $dateObj->format('d M');
            $sales[] = (float)($statusMap[$key]['sales'] ?? 0);
            $refunds[] = (float)($statusMap[$key]['refunds'] ?? 0);
            $orders[] = (int)($statusMap[$key]['orders'] ?? 0);
            $po[] = (float)($poMap[$key] ?? 0);
            $sr[] = (int)($srMap[$key] ?? 0);
        }

        return [
            'labels' => $labels,
            'sales' => $sales,
            'refunds' => $refunds,
            'orders' => $orders,
            'po' => $po,
            'sr' => $sr,
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
            ->where($eventDateExpr . " >= '" . $filters['date_from'] . "'", null, false)
            ->where($eventDateExpr . " <= '" . $filters['date_to'] . "'", null, false)
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
            ->where($eventDateExpr . " >= '" . $filters['date_from'] . "'", null, false)
            ->where($eventDateExpr . " <= '" . $filters['date_to'] . "'", null, false);

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
            ->group_by('status')
            ->order_by('total', 'DESC')
            ->get()->result_array();
    }

    private function dashboard_total_nilai_sr(array $filters): float
    {
        if (!$this->dashboard_table_ready('pur_store_request') || !$this->dashboard_table_ready('pur_store_request_line')) {
            return 0.0;
        }

        $sql = "SELECT COALESCE(SUM(srl.qty_buy_requested * COALESCE(pc.last_unit_price, 0)), 0) AS total
                FROM pur_store_request sr
                INNER JOIN pur_store_request_line srl ON srl.store_request_id = sr.id
                LEFT JOIN (
                    SELECT profile_key, MAX(last_unit_price) AS last_unit_price
                    FROM mst_purchase_catalog
                    WHERE is_active = 1
                    GROUP BY profile_key
                ) pc ON pc.profile_key = srl.profile_key
                WHERE sr.request_date >= " . $this->db->escape($filters['date_from']) . "
                  AND sr.request_date <= " . $this->db->escape($filters['date_to']) . "
                  AND sr.status != 'VOID'";

        $result = $this->dashboard_safe_query($sql);
        return $result ? (float)$result->row('total') : 0.0;
    }

    private function dashboard_stock_breakdown(): array
    {
        $divisionNameColumn = $this->dashboard_division_name_column();
        return [
            'warehouse' => $this->dashboard_stock_breakdown_warehouse(),
            'division' => $this->dashboard_stock_breakdown_division($divisionNameColumn),
            'component' => $this->dashboard_stock_breakdown_component($divisionNameColumn),
        ];
    }

    private function dashboard_stock_breakdown_warehouse(): array
    {
        $query = $this->dashboard_warehouse_monthly_query(
            "SELECT
                COALESCE(s.profile_name, m.material_name, i.item_name, CONCAT('Item #', s.item_id)) AS item_name,
                s.closing_qty_content AS qty,
                GREATEST(COALESCE(NULLIF(m.reorder_level_content, 0), NULLIF(i.min_stock_content, 0), 0), 0) AS threshold,
                COALESCE(s.total_value, 0) AS total_value,
                CASE WHEN s.closing_qty_content < 0 THEN 'minus' ELSE 'kritis' END AS severity
             FROM inv_warehouse_monthly_stock s
             INNER JOIN ({latest_month_subquery}) lm
                ON lm.identity_key = s.identity_key AND lm.month_key = s.month_key
             LEFT JOIN mst_item i ON i.id = s.item_id
             LEFT JOIN mst_material m ON m.id = COALESCE(s.material_id, i.material_id)
             WHERE s.closing_qty_content <= GREATEST(COALESCE(NULLIF(m.reorder_level_content, 0), NULLIF(i.min_stock_content, 0), 0), 0)
             ORDER BY s.closing_qty_content ASC
             LIMIT 20"
        );
        return $query ? $query->result_array() : [];
    }

    private function dashboard_stock_breakdown_division(?string $divisionNameColumn): array
    {
        $locationSelect = $divisionNameColumn
            ? ('COALESCE(d.' . $divisionNameColumn . ', CONCAT(\'Divisi #\', s.division_id))')
            : "CONCAT('Divisi #', s.division_id)";

        $query = $this->dashboard_division_monthly_query(
            "SELECT
                COALESCE(s.profile_name, m.material_name, i.item_name, CONCAT('Item #', s.item_id)) AS item_name,
                {$locationSelect} AS location_name,
                s.closing_qty_content AS qty,
                GREATEST(COALESCE(NULLIF(m.reorder_level_content, 0), NULLIF(i.min_stock_content, 0), 0), 0) AS threshold,
                COALESCE(s.total_value, 0) AS total_value,
                CASE WHEN s.closing_qty_content < 0 THEN 'minus' ELSE 'kritis' END AS severity
             FROM inv_division_monthly_stock s
             INNER JOIN ({latest_month_subquery}) lm
                ON lm.division_id = s.division_id AND lm.destination_type = s.destination_type
               AND lm.identity_key = s.identity_key AND lm.month_key = s.month_key
             LEFT JOIN mst_item i ON i.id = s.item_id
             LEFT JOIN mst_material m ON m.id = COALESCE(s.material_id, i.material_id)
             LEFT JOIN mst_operational_division d ON d.id = s.division_id
             WHERE s.closing_qty_content <= GREATEST(COALESCE(NULLIF(m.reorder_level_content, 0), NULLIF(i.min_stock_content, 0), 0), 0)
             ORDER BY s.closing_qty_content ASC
             LIMIT 20"
        );
        return $query ? $query->result_array() : [];
    }

    private function dashboard_stock_breakdown_component(?string $divisionNameColumn): array
    {
        $locationSelect = $divisionNameColumn
            ? ('COALESCE(d.' . $divisionNameColumn . ', s.location_type)')
            : 's.location_type';

        $query = $this->dashboard_component_monthly_query(
            "SELECT
                COALESCE(c.component_name, CONCAT('Component #', s.component_id)) AS item_name,
                {$locationSelect} AS location_name,
                s.closing_qty AS qty,
                GREATEST(COALESCE(c.min_stock, 0), 0) AS threshold,
                COALESCE(s.total_value, 0) AS total_value,
                CASE WHEN s.closing_qty < 0 THEN 'minus' ELSE 'kritis' END AS severity
             FROM inv_component_monthly_stock s
             INNER JOIN ({latest_month_subquery}) lm
                ON lm.location_type = s.location_type AND lm.division_id <=> s.division_id
               AND lm.component_id = s.component_id AND lm.uom_id = s.uom_id AND lm.month_key = s.month_key
             LEFT JOIN mst_component c ON c.id = s.component_id
             LEFT JOIN mst_operational_division d ON d.id = s.division_id
             WHERE s.closing_qty <= GREATEST(COALESCE(c.min_stock, 0), 0)
             ORDER BY s.closing_qty ASC
             LIMIT 20"
        );
        return $query ? $query->result_array() : [];
    }

    private function dashboard_critical_divisions(): array
    {
        if (!$this->dashboard_table_ready('mst_operational_division')) {
            return [];
        }
        $nameCol = $this->db->field_exists('division_name', 'mst_operational_division') ? 'division_name'
            : ($this->db->field_exists('name', 'mst_operational_division') ? 'name' : null);
        if ($nameCol === null) {
            return [];
        }
        return $this->db->select('id, ' . $nameCol . ' AS name')->from('mst_operational_division')
            ->where('is_active', 1)->order_by($nameCol, 'ASC')->get()->result_array();
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

    private function dashboard_critical_stock_rows(int $divisionFilter = 0): array
    {
        $rows = [];
        $divisionNameColumn = $this->dashboard_division_name_column();

        if ($divisionFilter <= 0) {
            $warehouseRows = $this->dashboard_warehouse_critical_rows();
            if (!empty($warehouseRows)) {
                $rows = array_merge($rows, $warehouseRows);
            }
        }

        $divisionRows = $this->dashboard_division_critical_rows($divisionNameColumn, $divisionFilter > 0 ? $divisionFilter : null);
        if (!empty($divisionRows)) {
            $rows = array_merge($rows, $divisionRows);
        }

        if ($divisionFilter <= 0) {
            $rows = array_merge($rows, $this->dashboard_component_critical_rows($divisionNameColumn));
        }

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

    private function dashboard_division_critical_rows(?string $divisionNameColumn, ?int $divisionId = null): array
    {
        $divisionLocationSelect = $divisionNameColumn !== null
            ? ('COALESCE(d.' . $divisionNameColumn . ', s.destination_type, CONCAT(\'Divisi #\', s.division_id))')
            : "COALESCE(s.destination_type, CONCAT('Divisi #', s.division_id))";

        $divisionWhere = $divisionId !== null ? ' AND s.division_id = ' . (int)$divisionId : '';

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
               {$divisionWhere}
             ORDER BY (GREATEST(COALESCE(NULLIF(m.reorder_level_content, 0), NULLIF(i.min_stock_content, 0), 0), 0) - s.closing_qty_content) DESC,
                      s.closing_qty_content ASC
             LIMIT 15"
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

    private function dashboard_negative_stock_rows(): array
    {
        $divisionNameColumn = $this->dashboard_division_name_column();
        $rows = [];

        // Material divisi dengan closing_qty_content < 0
        $divLocSelect = $divisionNameColumn !== null
            ? ('COALESCE(d.' . $divisionNameColumn . ', s.destination_type, CONCAT(\'Divisi #\', s.division_id))')
            : "COALESCE(s.destination_type, CONCAT('Divisi #', s.division_id))";

        $matQuery = $this->dashboard_division_monthly_query(
            "SELECT 'material' AS stock_type,
                    COALESCE(s.profile_name, m.material_name, i.item_name, CONCAT('Item #', s.item_id)) AS item_name,
                    {$divLocSelect} AS location_name,
                    s.closing_qty_content AS qty_balance,
                    COALESCE(cu.code, '') AS uom_code
             FROM inv_division_monthly_stock s
             INNER JOIN ({latest_month_subquery}) lm
                ON lm.division_id      = s.division_id
               AND lm.destination_type = s.destination_type
               AND lm.identity_key     = s.identity_key
               AND lm.month_key        = s.month_key
             LEFT JOIN mst_item i ON i.id = s.item_id
             LEFT JOIN mst_material m ON m.id = COALESCE(s.material_id, i.material_id)
             LEFT JOIN mst_uom cu ON cu.id = s.content_uom_id
             LEFT JOIN mst_operational_division d ON d.id = s.division_id
             WHERE s.closing_qty_content < -0.0001
             ORDER BY s.closing_qty_content ASC
             LIMIT 20"
        );
        if ($matQuery) {
            $rows = array_merge($rows, $matQuery->result_array());
        }

        // Component dengan closing_qty < 0
        $compLocSelect = $divisionNameColumn !== null
            ? ('COALESCE(d.' . $divisionNameColumn . ', s.location_type, CONCAT(\'Divisi #\', s.division_id))')
            : "COALESCE(s.location_type, CONCAT('Divisi #', s.division_id))";

        $compQuery = $this->dashboard_component_monthly_query(
            "SELECT 'component' AS stock_type,
                    COALESCE(c.component_name, CONCAT('Component #', s.component_id)) AS item_name,
                    {$compLocSelect} AS location_name,
                    s.closing_qty AS qty_balance,
                    COALESCE(u.code, '') AS uom_code
             FROM inv_component_monthly_stock s
             INNER JOIN ({latest_month_subquery}) lm
                ON lm.location_type = s.location_type
               AND lm.division_id <=> s.division_id
               AND lm.component_id = s.component_id
               AND lm.uom_id       = s.uom_id
               AND lm.month_key    = s.month_key
             LEFT JOIN mst_component c ON c.id = s.component_id
             LEFT JOIN mst_uom u ON u.id = s.uom_id
             LEFT JOIN mst_operational_division d ON d.id = s.division_id
             WHERE s.closing_qty < -0.0001
             ORDER BY s.closing_qty ASC
             LIMIT 20"
        );
        if ($compQuery) {
            $rows = array_merge($rows, $compQuery->result_array());
        }

        // Warehouse dengan closing_qty_content < 0
        $whQuery = $this->dashboard_warehouse_monthly_query(
            "SELECT 'warehouse' AS stock_type,
                    COALESCE(s.profile_name, m.material_name, i.item_name, CONCAT('Item #', s.item_id)) AS item_name,
                    'Gudang Pusat' AS location_name,
                    s.closing_qty_content AS qty_balance,
                    COALESCE(cu.code, '') AS uom_code
             FROM inv_warehouse_monthly_stock s
             INNER JOIN ({latest_month_subquery}) lm
                ON lm.identity_key = s.identity_key
               AND lm.month_key    = s.month_key
             LEFT JOIN mst_item i ON i.id = s.item_id
             LEFT JOIN mst_material m ON m.id = COALESCE(s.material_id, i.material_id)
             LEFT JOIN mst_uom cu ON cu.id = s.content_uom_id
             WHERE s.closing_qty_content < -0.0001
             ORDER BY s.closing_qty_content ASC
             LIMIT 10"
        );
        if ($whQuery) {
            $rows = array_merge($rows, $whQuery->result_array());
        }

        usort($rows, static function (array $a, array $b): int {
            return (float)$a['qty_balance'] <=> (float)$b['qty_balance'];
        });

        return array_slice($rows, 0, 30);
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

    private function json_ok(array $data = []): void
    {
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(['ok' => true, 'data' => $data]));
    }

    private function json_error(string $message, int $statusCode = 400): void
    {
        $this->output
            ->set_status_header($statusCode)
            ->set_content_type('application/json')
            ->set_output(json_encode(['ok' => false, 'message' => $message]));
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
