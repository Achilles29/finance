<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Read-only cross-module view for operational cost decisions.  It deliberately
 * keeps purchasing, production, HPP, shrinkage, and cash movement separate:
 * those flows happen at different times and must never be summed as one cost.
 */
class Cost_control_model extends CI_Model
{
    private $tableFieldCache = [];

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Pos_report_model');
    }

    public function division_options(): array
    {
        if (!$this->db->table_exists('mst_operational_division')) {
            return [];
        }

        $name = $this->division_name_column();
        if ($name === '') {
            return [];
        }
        return $this->db->select('id, ' . $name . ' AS division_name', false)
            ->from('mst_operational_division')
            ->where('is_active', 1)
            ->order_by($name, 'ASC')
            ->get()->result_array();
    }

    public function dashboard(array $filters): array
    {
        $sales = $this->Pos_report_model->sales_summary_report([
            'q' => '', 'status' => 'ALL', 'order_scope' => 'ALL', 'service_type' => 'ALL',
            'payment_method_id' => 0, 'outlet_id' => 0,
            'date_from' => $filters['date_from'], 'date_to' => $filters['date_to'],
            'page' => 1, 'limit' => 1,
        ]);
        $salesOverview = (array)($sales['overview'] ?? []);
        $purchases = $this->material_purchase_summary($filters);
        $production = $this->production_summary($filters);
        $shrinkage = $this->shrinkage_summary($filters);
        $cashExpense = $this->cash_expense_summary($filters);

        $netSales = (float)($salesOverview['net_sales'] ?? 0);
        $hpp = (float)($salesOverview['hpp_final_amount'] ?? 0);
        $grossProfit = (float)($salesOverview['gross_profit'] ?? ($netSales - $hpp));
        $shrinkageTotal = (float)($shrinkage['waste_value'] ?? 0)
            + (float)($shrinkage['spoil_value'] ?? 0)
            + (float)($shrinkage['process_loss_value'] ?? 0)
            + (float)($shrinkage['variance_value'] ?? 0);
        $cashTotal = (float)($cashExpense['total'] ?? 0);

        return [
            'summary' => [
                'net_sales' => $netSales,
                'hpp_final' => $hpp,
                'gross_profit' => $grossProfit,
                'gross_margin_pct' => $netSales > 0 ? ($grossProfit / $netSales * 100) : 0,
                'material_purchase_value' => (float)($purchases['total'] ?? 0),
                'material_usage_value' => (float)($production['material_usage_value'] ?? 0),
                'production_cost' => (float)($production['total_cost'] ?? 0),
                'production_batch_count' => (int)($production['batch_count'] ?? 0),
                'waste_total' => $shrinkageTotal,
                'waste_ratio_pct' => $netSales > 0 ? ($shrinkageTotal / $netSales * 100) : 0,
                'cash_expense' => $cashTotal,
                'cash_after_gross_profit' => $grossProfit - $cashTotal,
            ],
            'purchase_rows' => (array)($purchases['rows'] ?? []),
            'usage_rows' => (array)($production['usage_rows'] ?? []),
            'batch_rows' => (array)($production['batch_rows'] ?? []),
            'shrinkage_rows' => (array)($shrinkage['rows'] ?? []),
            'expense_rows' => (array)($cashExpense['rows'] ?? []),
            'data_sources' => [
                'purchase_ready' => !empty($purchases['available']),
                'production_ready' => !empty($production['available']),
                'shrinkage_ready' => !empty($shrinkage['available']),
                'cash_ready' => !empty($cashExpense['available']),
            ],
        ];
    }

    private function material_purchase_summary(array $filters): array
    {
        if (!$this->db->table_exists('pur_purchase_receipt') || !$this->db->table_exists('pur_purchase_receipt_line') || !$this->db->table_exists('pur_purchase_order_line')) {
            return ['available' => false, 'total' => 0, 'rows' => []];
        }

        $usagePurpose = $this->field_exists('pur_purchase_receipt_line', 'usage_purpose');
        $base = $this->db->select('COALESCE(SUM(rl.qty_buy_received * pol.unit_price), 0) AS total', false)
            ->from('pur_purchase_receipt r')
            ->join('pur_purchase_receipt_line rl', 'rl.purchase_receipt_id = r.id')
            ->join('pur_purchase_order_line pol', 'pol.id = rl.purchase_order_line_id')
            ->where('r.status', 'POSTED')
            ->where('UPPER(COALESCE(rl.line_kind, "ITEM"))', 'MATERIAL', false)
            ->where('r.receipt_date >=', $filters['date_from'])
            ->where('r.receipt_date <=', $filters['date_to']);
        if ($usagePurpose) {
            $base->where('UPPER(COALESCE(rl.usage_purpose, "BAHAN_BAKU"))', 'BAHAN_BAKU', false);
        }
        $this->apply_division_filter($base, 'r.destination_division_id', $filters);
        $this->apply_location_filter($base, 'r.destination_type', $filters);
        $total = (float)($base->get()->row_array()['total'] ?? 0);

        $nameExpr = 'COALESCE(NULLIF(m.material_name, ""), NULLIF(pol.snapshot_material_name, ""), NULLIF(rl.line_description, ""), "Bahan tanpa nama")';
        $rowsQuery = $this->db->select($nameExpr . ' AS material_name', false)
            ->select('COALESCE(SUM(rl.qty_buy_received * pol.unit_price), 0) AS purchase_value, COUNT(DISTINCT r.id) AS receipt_count', false)
            ->from('pur_purchase_receipt r')
            ->join('pur_purchase_receipt_line rl', 'rl.purchase_receipt_id = r.id')
            ->join('pur_purchase_order_line pol', 'pol.id = rl.purchase_order_line_id')
            ->join('mst_material m', 'm.id = rl.material_id', 'left')
            ->where('r.status', 'POSTED')
            ->where('UPPER(COALESCE(rl.line_kind, "ITEM"))', 'MATERIAL', false)
            ->where('r.receipt_date >=', $filters['date_from'])
            ->where('r.receipt_date <=', $filters['date_to']);
        if ($usagePurpose) {
            $rowsQuery->where('UPPER(COALESCE(rl.usage_purpose, "BAHAN_BAKU"))', 'BAHAN_BAKU', false);
        }
        $this->apply_division_filter($rowsQuery, 'r.destination_division_id', $filters);
        $this->apply_location_filter($rowsQuery, 'r.destination_type', $filters);
        $rows = $rowsQuery->group_by($nameExpr, false)->order_by('purchase_value', 'DESC')->limit(8)->get()->result_array();

        return ['available' => true, 'total' => $total, 'rows' => $rows];
    }

    private function production_summary(array $filters): array
    {
        if (!$this->db->table_exists('inv_component_batch')) {
            return ['available' => false, 'total_cost' => 0, 'material_usage_value' => 0, 'batch_count' => 0, 'usage_rows' => [], 'batch_rows' => []];
        }

        $summaryQuery = $this->db->select('COALESCE(SUM(b.total_input_cost), 0) AS total_cost, COUNT(*) AS batch_count', false)
            ->from('inv_component_batch b')->where('b.status', 'POSTED')
            ->where('b.batch_date >=', $filters['date_from'])->where('b.batch_date <=', $filters['date_to']);
        $this->apply_division_filter($summaryQuery, 'b.division_id', $filters);
        $this->apply_location_filter($summaryQuery, 'b.location_type', $filters);
        $summary = (array)$summaryQuery->get()->row_array();

        $materialUsage = 0.0;
        $usageRows = [];
        if ($this->db->table_exists('inv_component_batch_input')) {
            $usageQuery = $this->db->select('COALESCE(SUM(i.total_cost), 0) AS total', false)
                ->from('inv_component_batch_input i')->join('inv_component_batch b', 'b.id = i.batch_id')
                ->where('b.status', 'POSTED')->where('i.source_kind', 'MATERIAL')
                ->where('b.batch_date >=', $filters['date_from'])->where('b.batch_date <=', $filters['date_to']);
            $this->apply_division_filter($usageQuery, 'b.division_id', $filters);
            $this->apply_location_filter($usageQuery, 'b.location_type', $filters);
            $materialUsage = (float)($usageQuery->get()->row_array()['total'] ?? 0);

            $usageRowsQuery = $this->db->select('COALESCE(m.material_name, "Bahan tanpa nama") AS material_name, COALESCE(SUM(i.total_cost), 0) AS usage_value, COUNT(DISTINCT b.id) AS batch_count', false)
                ->from('inv_component_batch_input i')->join('inv_component_batch b', 'b.id = i.batch_id')
                ->join('mst_material m', 'm.id = i.material_id', 'left')->where('b.status', 'POSTED')->where('i.source_kind', 'MATERIAL')
                ->where('b.batch_date >=', $filters['date_from'])->where('b.batch_date <=', $filters['date_to']);
            $this->apply_division_filter($usageRowsQuery, 'b.division_id', $filters);
            $this->apply_location_filter($usageRowsQuery, 'b.location_type', $filters);
            $usageRows = $usageRowsQuery->group_by('i.material_id')->order_by('usage_value', 'DESC')->limit(8)->get()->result_array();
        }

        $divisionName = $this->division_name_column();
        $batchRowsQuery = $this->db->select('b.batch_no, b.batch_date, b.location_type, b.output_qty, b.total_input_cost, b.unit_cost, c.component_name')
            ->select($divisionName !== '' ? 'd.' . $divisionName . ' AS division_name' : 'NULL AS division_name', false)
            ->from('inv_component_batch b')->join('mst_component c', 'c.id = b.component_id', 'left')->join('mst_operational_division d', 'd.id = b.division_id', 'left')
            ->where('b.status', 'POSTED')->where('b.batch_date >=', $filters['date_from'])->where('b.batch_date <=', $filters['date_to']);
        $this->apply_division_filter($batchRowsQuery, 'b.division_id', $filters);
        $this->apply_location_filter($batchRowsQuery, 'b.location_type', $filters);
        $batchRows = $batchRowsQuery->order_by('b.batch_date', 'DESC')->order_by('b.id', 'DESC')->limit(8)->get()->result_array();

        return ['available' => true, 'total_cost' => (float)($summary['total_cost'] ?? 0), 'material_usage_value' => $materialUsage, 'batch_count' => (int)($summary['batch_count'] ?? 0), 'usage_rows' => $usageRows, 'batch_rows' => $batchRows];
    }

    private function shrinkage_summary(array $filters): array
    {
        if (!$this->db->table_exists('inv_stock_adjustment') || !$this->db->table_exists('inv_stock_adjustment_line')) {
            return ['available' => false, 'waste_value' => 0, 'spoil_value' => 0, 'process_loss_value' => 0, 'variance_value' => 0, 'rows' => []];
        }
        $select = 'COALESCE(SUM(l.qty_waste_content * l.unit_cost), 0) AS waste_value, COALESCE(SUM(l.qty_spoil_content * l.unit_cost), 0) AS spoil_value, COALESCE(SUM(l.qty_process_loss_content * l.unit_cost), 0) AS process_loss_value, COALESCE(SUM(l.qty_variance_content * l.unit_cost), 0) AS variance_value';
        $summaryQuery = $this->db->select($select, false)->from('inv_stock_adjustment a')->join('inv_stock_adjustment_line l', 'l.adjustment_id = a.id')
            ->where('a.status', 'POSTED')->where('a.adjustment_date >=', $filters['date_from'])->where('a.adjustment_date <=', $filters['date_to']);
        $this->apply_division_filter($summaryQuery, 'a.division_id', $filters);
        $this->apply_location_filter($summaryQuery, 'a.destination_type', $filters);
        $summary = (array)$summaryQuery->get()->row_array();

        $rowsQuery = $this->db->select('COALESCE(m.material_name, i.item_name, l.profile_name, "Bahan tanpa nama") AS material_name', false)
            ->select('COALESCE(SUM((l.qty_waste_content + l.qty_spoil_content + l.qty_process_loss_content + l.qty_variance_content) * l.unit_cost), 0) AS shrinkage_value', false)
            ->from('inv_stock_adjustment a')->join('inv_stock_adjustment_line l', 'l.adjustment_id = a.id')->join('mst_material m', 'm.id = l.material_id', 'left')->join('mst_item i', 'i.id = l.item_id', 'left')
            ->where('a.status', 'POSTED')->where('a.adjustment_date >=', $filters['date_from'])->where('a.adjustment_date <=', $filters['date_to']);
        $this->apply_division_filter($rowsQuery, 'a.division_id', $filters);
        $this->apply_location_filter($rowsQuery, 'a.destination_type', $filters);
        $rows = $rowsQuery->group_by('l.material_id')->group_by('l.item_id')->group_by('l.profile_name')->order_by('shrinkage_value', 'DESC')->limit(8)->get()->result_array();

        return ['available' => true] + $summary + ['rows' => $rows];
    }

    private function cash_expense_summary(array $filters): array
    {
        if (!$this->db->table_exists('fin_account_mutation_log')) {
            return ['available' => false, 'total' => 0, 'rows' => []];
        }
        $where = "m.mutation_type = 'OUT' AND NOT (m.ref_module = 'POS' AND m.ref_table = 'pos_refund') AND COALESCE(m.ref_module, '') NOT IN ('FINANCE_TRANSFER', 'FINANCE_PAYABLE', 'FINANCE_RECEIVABLE')";
        $totalQuery = $this->db->select('COALESCE(SUM(m.amount), 0) AS total', false)->from('fin_account_mutation_log m')
            ->where($where, null, false)->where('m.mutation_date >=', $filters['date_from'])->where('m.mutation_date <=', $filters['date_to']);
        $this->apply_effective_mutation_filter($totalQuery, 'm');
        $total = (float)($totalQuery->get()->row_array()['total'] ?? 0);

        $rowsQuery = $this->db->select("COALESCE(NULLIF(m.ref_module, ''), 'MANUAL / LAINNYA') AS source_name", false)
            ->select('COALESCE(SUM(m.amount), 0) AS expense_value, COUNT(*) AS transaction_count', false)->from('fin_account_mutation_log m')
            ->where($where, null, false)->where('m.mutation_date >=', $filters['date_from'])->where('m.mutation_date <=', $filters['date_to']);
        $this->apply_effective_mutation_filter($rowsQuery, 'm');
        $rows = $rowsQuery->group_by('source_name', false)->order_by('expense_value', 'DESC')->limit(8)->get()->result_array();
        return ['available' => true, 'total' => $total, 'rows' => $rows];
    }

    private function apply_division_filter($builder, string $column, array $filters): void
    {
        if ((int)($filters['division_id'] ?? 0) > 0) {
            $builder->where($column, (int)$filters['division_id']);
        }
    }

    private function apply_location_filter($builder, string $column, array $filters): void
    {
        $location = strtoupper(trim((string)($filters['location_type'] ?? 'ALL')));
        if ($location === 'REGULAR') {
            $builder->where_in($column, ['GUDANG', 'BAR', 'KITCHEN', 'ROASTERY']);
        } elseif ($location === 'EVENT') {
            $builder->where_in($column, ['BAR_EVENT', 'KITCHEN_EVENT', 'ROASTERY_EVENT']);
        }
    }

    private function apply_effective_mutation_filter($builder, string $alias): void
    {
        if (!$this->field_exists('fin_account_mutation_log', 'reversal_of_mutation_id')) {
            return;
        }
        $builder->where($alias . '.reversal_of_mutation_id IS NULL', null, false)
            ->where('NOT EXISTS (SELECT 1 FROM fin_account_mutation_log reversal WHERE reversal.reversal_of_mutation_id = ' . $alias . '.id)', null, false);
    }

    private function division_name_column(): string
    {
        if (!$this->db->table_exists('mst_operational_division')) {
            return '';
        }
        return $this->field_exists('mst_operational_division', 'division_name') ? 'division_name' : ($this->field_exists('mst_operational_division', 'name') ? 'name' : '');
    }

    private function field_exists(string $table, string $field): bool
    {
        $key = $table . '.' . $field;
        if (!array_key_exists($key, $this->tableFieldCache)) {
            $this->tableFieldCache[$key] = $this->db->field_exists($field, $table);
        }
        return (bool)$this->tableFieldCache[$key];
    }
}
