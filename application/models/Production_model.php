<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Production_model extends CI_Model
{
    private $componentFormulaLinesCache = [];
    private $componentFormulaSummaryCache = [];
    private $formulaLineCostCache = [];
    private $itemMaterialCache = [];
    private $materialLookupCache = [];
    private $materialBalanceCache = [];
    private $componentLookupCache = [];
    private $componentBalanceCache = [];

    private function division_name_column()
    {
        if ($this->db->field_exists('division_name', 'mst_operational_division')) {
            return 'division_name';
        }
        if ($this->db->field_exists('name', 'mst_operational_division')) {
            return 'name';
        }
        return null;
    }

    private function division_code_column()
    {
        if ($this->db->field_exists('code', 'mst_operational_division')) {
            return 'code';
        }
        if ($this->db->field_exists('division_code', 'mst_operational_division')) {
            return 'division_code';
        }
        return null;
    }

    private function component_operational_context(int $componentId): ?array
    {
        if ($componentId <= 0) {
            return null;
        }
        if (array_key_exists($componentId, $this->componentLookupCache)) {
            return $this->componentLookupCache[$componentId];
        }

        $divisionNameColumn = $this->division_name_column();
        $divisionCodeColumn = $this->division_code_column();
        $select = [
            'c.id',
            'c.component_name',
            'c.component_type',
            'c.uom_id',
            'c.operational_division_id',
        ];
        if ($divisionCodeColumn !== null) {
            $select[] = 'd.' . $divisionCodeColumn . ' AS division_code';
        }
        if ($divisionNameColumn !== null) {
            $select[] = 'd.' . $divisionNameColumn . ' AS division_name';
        }

        $row = $this->db->select(implode(', ', $select), false)
            ->from('mst_component c')
            ->join('mst_operational_division d', 'd.id = c.operational_division_id', 'left')
            ->where('c.id', $componentId)
            ->limit(1)
            ->get()
            ->row_array();

        $this->componentLookupCache[$componentId] = $row ?: null;
        return $this->componentLookupCache[$componentId];
    }

    private function location_group_to_component_location(?array $componentContext, string $locationType): ?string
    {
        $locationType = strtoupper(trim($locationType));
        if ($locationType === '') {
            return null;
        }

        $validLocations = ['BAR', 'KITCHEN', 'BAR_EVENT', 'KITCHEN_EVENT'];
        if (in_array($locationType, $validLocations, true)) {
            return $locationType;
        }

        if (!in_array($locationType, ['REGULER', 'EVENT'], true) || empty($componentContext['operational_division_id'])) {
            return null;
        }

        $divisionCode = strtoupper(trim((string)($componentContext['division_code'] ?? '')));
        if ($divisionCode === 'BAR') {
            return $locationType === 'EVENT' ? 'BAR_EVENT' : 'BAR';
        }
        if ($divisionCode === 'KITCHEN') {
            return $locationType === 'EVENT' ? 'KITCHEN_EVENT' : 'KITCHEN';
        }

        return null;
    }

    private function location_to_group(string $locationType): string
    {
        $locationType = strtoupper(trim($locationType));
        if ($locationType === 'BAR_EVENT' || $locationType === 'KITCHEN_EVENT') {
            return 'EVENT';
        }
        if ($locationType === 'BAR' || $locationType === 'KITCHEN') {
            return 'REGULER';
        }
        return $locationType;
    }

    private function location_filter_values(string $locationType): array
    {
        $locationType = strtoupper(trim($locationType));
        if ($locationType === '') {
            return [];
        }
        if ($locationType === 'REGULER') {
            return ['BAR', 'KITCHEN'];
        }
        if ($locationType === 'EVENT') {
            return ['BAR_EVENT', 'KITCHEN_EVENT'];
        }
        if (in_array($locationType, ['BAR', 'KITCHEN', 'BAR_EVENT', 'KITCHEN_EVENT'], true)) {
            return [$locationType];
        }
        return [];
    }

    private function apply_component_location_filter(string $column, string $locationType): void
    {
        $values = $this->location_filter_values($locationType);
        if (empty($values)) {
            return;
        }
        if (count($values) === 1) {
            $this->db->where($column, $values[0]);
            return;
        }
        $this->db->where_in($column, $values);
    }

    private function resolve_opening_component_context(array $lines): array
    {
        $resolvedDivisionId = null;
        $context = null;

        foreach ($lines as $line) {
            $componentId = (int)($line['component_id'] ?? 0);
            $uomId = (int)($line['uom_id'] ?? 0);
            $qty = (float)($line['opening_qty'] ?? 0);
            if ($componentId <= 0 || $uomId <= 0 || $qty <= 0) {
                continue;
            }

            $row = $this->component_operational_context($componentId);
            if (!$row || (int)($row['operational_division_id'] ?? 0) <= 0) {
                return ['ok' => false, 'message' => 'Semua component opening wajib memiliki divisi operasional.'];
            }

            $divisionId = (int)$row['operational_division_id'];
            if ($resolvedDivisionId === null) {
                $resolvedDivisionId = $divisionId;
                $context = $row;
                continue;
            }
            if ($resolvedDivisionId !== $divisionId) {
                return ['ok' => false, 'message' => 'Opening hanya boleh memuat component dari satu divisi operasional yang sama.'];
            }
        }

        if ($resolvedDivisionId === null || !$context) {
            return ['ok' => false, 'message' => 'Tambahkan minimal satu component opening yang valid.'];
        }

        return [
            'ok' => true,
            'division_id' => $resolvedDivisionId,
            'component_context' => $context,
        ];
    }

    private function normalize_month_key(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            return $value;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return substr($value, 0, 7);
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }
        return date('Y-m', $timestamp);
    }

    private function upsert_by_unique(string $table, array $rowData, array $uniqueColumns): void
    {
        $query = $this->db->from($table);
        foreach ($uniqueColumns as $column) {
            $value = $rowData[$column] ?? null;
            if ($value === null) {
                $this->db->where($column . ' IS NULL', null, false);
            } else {
                $this->db->where($column, $value);
            }
        }
        $existing = $query->limit(1)->get()->row_array();
        if ($existing) {
            $this->db->where('id', (int)$existing['id'])->update($table, $rowData);
            return;
        }
        $this->db->insert($table, $rowData);
    }

    public function component_stock_rows(array $filters, $limit = 200)
    {
        $q = trim((string)($filters['q'] ?? ''));
        $locationType = strtoupper(trim((string)($filters['location_type'] ?? '')));
        $divisionNameColumn = $this->division_name_column();
        $divisionNameSelect = $divisionNameColumn !== null ? ('d.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';

        $this->db->select("
            s.location_type,
            s.division_id,
            {$divisionNameSelect},
            s.component_id,
            c.component_code,
            c.component_name,
            c.component_type,
            s.uom_id,
            u.code AS uom_code,
            u.name AS uom_name,
            s.qty_on_hand,
            s.avg_cost,
            s.total_value,
            s.last_txn_at,
            s.updated_at
        ", false);
        $this->db->from('inv_component_stock_balance s');
        $this->db->join('mst_component c', 'c.id = s.component_id', 'inner');
        $this->db->join('mst_operational_division d', 'd.id = s.division_id', 'left');
        $this->db->join('mst_uom u', 'u.id = s.uom_id', 'left');
        $this->db->join('mst_component_category cat', 'cat.id = c.component_category_id', 'left');

        $this->apply_component_location_filter('s.location_type', $locationType);
        if ($q !== '') {
            $this->db->group_start();
            $this->db
                ->like('c.component_name', $q)
                ->or_like('c.component_code', $q);
            if ($divisionNameColumn !== null) {
                $this->db->or_like('d.' . $divisionNameColumn, $q);
            }
            $this->db->group_end();
        }

        $this->db->order_by('c.component_type', 'ASC');
        if ($divisionNameColumn !== null) {
            $this->db->order_by('d.' . $divisionNameColumn, 'ASC');
        }
        $this->db->order_by('cat.name', 'ASC');
        $this->db->order_by('c.component_name', 'ASC');
        $this->db->limit(max(1, (int)$limit));
        return $this->db->get()->result_array();
    }

    public function component_movement_rows(array $filters, $limit = 300)
    {
        $q = trim((string)($filters['q'] ?? ''));
        $locationType = strtoupper(trim((string)($filters['location_type'] ?? '')));
        $movementType = strtoupper(trim((string)($filters['movement_type'] ?? '')));
        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        $dateTo = trim((string)($filters['date_to'] ?? ''));
        $divisionNameColumn = $this->division_name_column();
        $divisionNameSelect = $divisionNameColumn !== null ? ('d.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';

        $this->db->select("
            m.id,
            m.movement_no,
            m.movement_date,
            m.movement_datetime,
            m.location_type,
            m.division_id,
            {$divisionNameSelect},
            m.component_id,
            c.component_code,
            c.component_name,
            m.uom_id,
            u.code AS uom_code,
            m.movement_type,
            m.qty_in,
            m.qty_out,
            m.unit_cost,
            m.total_cost,
            m.source_module,
            m.source_table,
            m.source_id,
            m.notes
        ", false);
        $this->db->from('inv_component_movement_log m');
        $this->db->join('mst_component c', 'c.id = m.component_id', 'inner');
        $this->db->join('mst_operational_division d', 'd.id = m.division_id', 'left');
        $this->db->join('mst_uom u', 'u.id = m.uom_id', 'left');
        $this->db->join('mst_component_category cat', 'cat.id = c.component_category_id', 'left');

        $this->apply_component_location_filter('m.location_type', $locationType);
        if ($movementType !== '') {
            $this->db->where('m.movement_type', $movementType);
        }
        if ($dateFrom !== '') {
            $this->db->where('m.movement_date >=', $dateFrom);
        }
        if ($dateTo !== '') {
            $this->db->where('m.movement_date <=', $dateTo);
        }
        if ($q !== '') {
            $this->db->group_start()
                ->like('c.component_name', $q)
                ->or_like('c.component_code', $q)
                ->or_like('m.movement_no', $q)
                ->group_end();
        }

        $this->db->order_by('c.component_type', 'ASC');
        if ($divisionNameColumn !== null) {
            $this->db->order_by('d.' . $divisionNameColumn, 'ASC');
        }
        $this->db->order_by('cat.name', 'ASC');
        $this->db->order_by('m.movement_datetime', 'DESC');
        $this->db->limit(max(1, (int)$limit));
        return $this->db->get()->result_array();
    }

    public function component_daily_rows(array $filters, $limit = 500)
    {
        $q = trim((string)($filters['q'] ?? ''));
        $locationType = strtoupper(trim((string)($filters['location_type'] ?? '')));
        $month = trim((string)($filters['month'] ?? date('Y-m')));
        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        $divisionNameColumn = $this->division_name_column();
        $divisionNameSelect = $divisionNameColumn !== null ? ('d.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';

        $this->db->select("
            r.month_key,
            r.movement_date,
            r.location_type,
            r.division_id,
            {$divisionNameSelect},
            r.component_id,
            c.component_code,
            c.component_name,
            c.component_type,
            r.uom_id,
            u.code AS uom_code,
            r.opening_qty,
            r.in_qty,
            r.out_qty,
            r.waste_qty,
            r.spoil_qty,
            r.adjustment_qty,
            r.closing_qty,
            r.avg_cost,
            r.total_value,
            r.mutation_count
        ", false);
        $this->db->from('inv_component_daily_rollup r');
        $this->db->join('mst_component c', 'c.id = r.component_id', 'inner');
        $this->db->join('mst_operational_division d', 'd.id = r.division_id', 'left');
        $this->db->join('mst_uom u', 'u.id = r.uom_id', 'left');
        $this->db->join('mst_component_category cat', 'cat.id = c.component_category_id', 'left');
        $this->db->where('r.movement_date >=', $startDate);
        $this->db->where('r.movement_date <=', $endDate);

        $this->apply_component_location_filter('r.location_type', $locationType);
        if ($q !== '') {
            $this->db->group_start();
            $this->db
                ->like('c.component_name', $q)
                ->or_like('c.component_code', $q);
            if ($divisionNameColumn !== null) {
                $this->db->or_like('d.' . $divisionNameColumn, $q);
            }
            $this->db->group_end();
        }

        $this->db->order_by('c.component_type', 'ASC');
        if ($divisionNameColumn !== null) {
            $this->db->order_by('d.' . $divisionNameColumn, 'ASC');
        }
        $this->db->order_by('cat.name', 'ASC');
        $this->db->order_by('r.movement_date', 'DESC');
        $this->db->order_by('c.component_name', 'ASC');
        $this->db->limit(max(1, (int)$limit));
        return $this->db->get()->result_array();
    }

    public function list_component_openings(array $filters = [], int $limit = 200): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $monthKey = $this->normalize_month_key((string)($filters['month'] ?? ''));
        $locationType = strtoupper(trim((string)($filters['location_type'] ?? '')));
        $divisionId = !empty($filters['division_id']) ? (int)$filters['division_id'] : null;
        $divisionNameColumn = $this->division_name_column();
        $divisionNameSelect = $divisionNameColumn !== null ? ('d.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';

        $this->db->select('h.*, ' . $divisionNameSelect, false);
        $this->db->from('inv_component_opening h');
        $this->db->join('mst_operational_division d', 'd.id = h.division_id', 'left');
        if ($monthKey !== null) {
            $this->db->where('h.opening_date >=', $monthKey . '-01');
            $this->db->where('h.opening_date <=', date('Y-m-t', strtotime($monthKey . '-01')));
        }
        $this->apply_component_location_filter('h.location_type', $locationType);
        if ($divisionId !== null) {
            $this->db->where('h.division_id', $divisionId);
        }
        if ($q !== '') {
            $this->db->group_start();
            $this->db
                ->like('h.opening_no', $q)
                ->or_like('h.location_type', $q);
            if ($divisionNameColumn !== null) {
                $this->db->or_like('d.' . $divisionNameColumn, $q);
            }
            $this->db->group_end();
        }
        $this->db->order_by('h.opening_date', 'DESC')->order_by('h.id', 'DESC')->limit(max(1, $limit));
        return $this->db->get()->result_array();
    }

    public function get_component_opening(int $id): ?array
    {
        $row = $this->db->get_where('inv_component_opening', ['id' => $id])->row_array();
        return $row ?: null;
    }

    public function get_component_opening_lines(int $openingId): array
    {
        return $this->db->select('l.*, c.component_name, c.component_code, u.code AS uom_code')
            ->from('inv_component_opening_line l')
            ->join('mst_component c', 'c.id = l.component_id', 'left')
            ->join('mst_uom u', 'u.id = l.uom_id', 'left')
            ->where('l.opening_id', $openingId)
            ->order_by('l.line_no', 'ASC')
            ->get()->result_array();
    }

    public function list_component_monthly_openings(array $filters = [], int $limit = 200): array
    {
        if (!$this->db->table_exists('inv_component_monthly_opening')) {
            return [];
        }

        $q = trim((string)($filters['q'] ?? ''));
        $monthKey = $this->normalize_month_key((string)($filters['month'] ?? ''));
        $locationType = strtoupper(trim((string)($filters['location_type'] ?? '')));
        $divisionId = !empty($filters['division_id']) ? (int)$filters['division_id'] : null;
        $divisionNameColumn = $this->division_name_column();
        $divisionNameSelect = $divisionNameColumn !== null ? ('d.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';

        $this->db->select('m.*, c.component_code, c.component_name, c.component_type, u.code AS uom_code, u.name AS uom_name, ' . $divisionNameSelect, false);
        $this->db->from('inv_component_monthly_opening m');
        $this->db->join('mst_component c', 'c.id = m.component_id', 'left');
        $this->db->join('mst_uom u', 'u.id = m.uom_id', 'left');
        $this->db->join('mst_operational_division d', 'd.id = m.division_id', 'left');

        if ($monthKey !== null) {
            $this->db->where('m.month_key', $monthKey);
        }
        $this->apply_component_location_filter('m.location_type', $locationType);
        if ($divisionId !== null) {
            $this->db->where('m.division_id', $divisionId);
        }
        if ($q !== '') {
            $this->db->group_start();
            $this->db
                ->like('c.component_name', $q)
                ->or_like('c.component_code', $q)
                ->or_like('m.location_type', $q);
            if ($divisionNameColumn !== null) {
                $this->db->or_like('d.' . $divisionNameColumn, $q);
            }
            $this->db->group_end();
        }

        $this->db->order_by('m.month_key', 'DESC');
        $this->db->order_by('m.location_type', 'ASC');
        if ($divisionNameColumn !== null) {
            $this->db->order_by('d.' . $divisionNameColumn, 'ASC');
        }
        $this->db->order_by('c.component_name', 'ASC');
        $this->db->limit(max(1, $limit));
        return $this->db->get()->result_array();
    }

    public function generate_component_monthly_opname_and_opening(array $payload, int $userId): array
    {
        $monthKey = $this->normalize_month_key((string)($payload['month'] ?? $payload['month_key'] ?? date('Y-m')));
        if ($monthKey === null) {
            return [
                'ok' => false,
                'message' => 'Parameter bulan tidak valid.',
            ];
        }

        if (!$this->db->table_exists('inv_component_daily_rollup')) {
            return [
                'ok' => false,
                'message' => 'Tabel inv_component_daily_rollup belum tersedia.',
            ];
        }
        if (!$this->db->table_exists('inv_component_monthly_opname')) {
            return [
                'ok' => false,
                'message' => 'Tabel inv_component_monthly_opname belum tersedia.',
            ];
        }
        if (!$this->db->table_exists('inv_component_monthly_opening')) {
            return [
                'ok' => false,
                'message' => 'Tabel inv_component_monthly_opening belum tersedia.',
            ];
        }

        $locationType = strtoupper(trim((string)($payload['location_type'] ?? '')));
        $divisionId = !empty($payload['division_id']) ? (int)$payload['division_id'] : null;
        $monthStart = $monthKey . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        $nextMonth = date('Y-m', strtotime('+1 month', strtotime($monthStart)));
        $nextOpeningDate = $nextMonth . '-01';

        $this->db->select('r.movement_date, r.location_type, r.division_id, r.component_id, r.uom_id, r.opening_qty, r.closing_qty, r.avg_cost, r.total_value, r.mutation_count, c.component_code, c.component_name');
        $this->db->from('inv_component_daily_rollup r');
        $this->db->join('mst_component c', 'c.id = r.component_id', 'left');
        $this->db->where('r.movement_date >=', $monthStart);
        $this->db->where('r.movement_date <=', $monthEnd);
        $this->apply_component_location_filter('r.location_type', $locationType);
        if ($divisionId !== null) {
            $this->db->where('r.division_id', $divisionId);
        }
        $rows = $this->db
            ->order_by('r.movement_date', 'ASC')
            ->order_by('r.location_type', 'ASC')
            ->order_by('r.division_id', 'ASC')
            ->order_by('r.component_id', 'ASC')
            ->get()
            ->result_array();

        if (empty($rows)) {
            return [
                'ok' => false,
                'message' => 'Belum ada daily rollup component untuk bulan ' . $monthKey . '.',
            ];
        }

        $negativeSamples = [];
        foreach ($rows as $row) {
            $closingQty = round((float)($row['closing_qty'] ?? 0), 4);
            if ($closingQty >= 0) {
                continue;
            }
            $negativeSamples[] = trim((string)($row['movement_date'] ?? ''))
                . ' | ' . (string)($row['location_type'] ?? '-')
                . ' | ' . (string)($row['component_code'] ?? '-')
                . ' - ' . (string)($row['component_name'] ?? '-')
                . ' | closing=' . number_format($closingQty, 4, '.', '');
            if (count($negativeSamples) >= 5) {
                break;
            }
        }
        if (!empty($negativeSamples)) {
            return [
                'ok' => false,
                'message' => 'Generate ditolak karena masih ada stok minus pada daily rollup component.',
                'data' => ['negative_samples' => $negativeSamples],
            ];
        }

        $aggregated = [];
        foreach ($rows as $row) {
            $groupKey = implode('|', [
                strtoupper((string)($row['location_type'] ?? '')),
                (int)($row['division_id'] ?? 0),
                (int)($row['component_id'] ?? 0),
                (int)($row['uom_id'] ?? 0),
            ]);

            if (!isset($aggregated[$groupKey])) {
                $aggregated[$groupKey] = [
                    'month_key' => $monthKey,
                    'location_type' => strtoupper((string)($row['location_type'] ?? '')),
                    'division_id' => !empty($row['division_id']) ? (int)$row['division_id'] : null,
                    'component_id' => (int)($row['component_id'] ?? 0),
                    'uom_id' => (int)($row['uom_id'] ?? 0),
                    'opname_date' => $monthEnd,
                    'closing_qty' => 0.0,
                    'hpp_live' => 0.0,
                    'total_value' => 0.0,
                    'generated_by' => $userId > 0 ? $userId : null,
                    '_last_date' => '0000-00-00',
                    '_mutation_count' => 0,
                ];
            }

            $movementDate = (string)($row['movement_date'] ?? '');
            $aggregated[$groupKey]['_mutation_count'] += (int)($row['mutation_count'] ?? 0);
            if ($movementDate >= $aggregated[$groupKey]['_last_date']) {
                $aggregated[$groupKey]['_last_date'] = $movementDate;
                $aggregated[$groupKey]['closing_qty'] = round((float)($row['closing_qty'] ?? 0), 4);
                $aggregated[$groupKey]['hpp_live'] = round((float)($row['avg_cost'] ?? 0), 6);
                $aggregated[$groupKey]['total_value'] = round((float)($row['total_value'] ?? 0), 2);
            }
        }

        foreach ($aggregated as $groupKey => $row) {
            unset($aggregated[$groupKey]['_last_date'], $aggregated[$groupKey]['_mutation_count']);
        }

        $conflictQuery = $this->db->from('inv_component_monthly_opening')
            ->where('month_key', $nextMonth)
            ->group_start()
                ->where('source_month IS NULL', null, false)
                ->or_where('source_month <>', $monthKey)
            ->group_end();
        if ($locationType !== '') {
            $this->db->where('location_type', $locationType);
        }
        if ($divisionId !== null) {
            $this->db->where('division_id', $divisionId);
        }
        $manualConflictCount = (int)$conflictQuery->count_all_results();
        if ($manualConflictCount > 0) {
            return [
                'ok' => false,
                'message' => 'Opening bulanan untuk ' . $nextMonth . ' sudah ada dan bukan hasil carry-forward bulan ' . $monthKey . '. Bersihkan dulu data manual yang bentrok.',
            ];
        }

        $this->db->trans_begin();

        $this->db->where('month_key', $monthKey);
        if ($locationType !== '') {
            $this->db->where('location_type', $locationType);
        }
        if ($divisionId !== null) {
            $this->db->where('division_id', $divisionId);
        }
        $this->db->delete('inv_component_monthly_opname');

        $this->db->where('month_key', $nextMonth);
        $this->db->where('source_month', $monthKey);
        if ($locationType !== '') {
            $this->db->where('location_type', $locationType);
        }
        if ($divisionId !== null) {
            $this->db->where('division_id', $divisionId);
        }
        $this->db->delete('inv_component_monthly_opening');

        $generatedRows = 0;
        $carriedRows = 0;
        foreach ($aggregated as $row) {
            $this->upsert_by_unique('inv_component_monthly_opname', $row, ['month_key', 'location_type', 'division_id', 'component_id', 'uom_id']);
            $generatedRows++;

            if ((float)$row['closing_qty'] <= 0) {
                continue;
            }

            $openingRow = [
                'month_key' => $nextMonth,
                'location_type' => (string)$row['location_type'],
                'division_id' => $row['division_id'],
                'component_id' => (int)$row['component_id'],
                'uom_id' => (int)$row['uom_id'],
                'opening_date' => $nextOpeningDate,
                'opening_qty' => round((float)$row['closing_qty'], 4),
                'hpp_live' => round((float)$row['hpp_live'], 6),
                'total_value' => round((float)$row['total_value'], 2),
                'source_month' => $monthKey,
                'generated_by' => $userId > 0 ? $userId : null,
            ];
            $this->upsert_by_unique('inv_component_monthly_opening', $openingRow, ['month_key', 'location_type', 'division_id', 'component_id', 'uom_id']);
            $carriedRows++;
        }

        if ($this->db->table_exists('aud_transaction_log')) {
            $this->db->insert('aud_transaction_log', [
                'module_code' => 'PRODUCTION',
                'action_code' => 'COMPONENT_MONTHLY_GENERATE',
                'entity_table' => 'inv_component_monthly_opname',
                'entity_id' => null,
                'transaction_no' => null,
                'actor_user_id' => $userId > 0 ? $userId : null,
                'after_payload' => json_encode([
                    'month' => $monthKey,
                    'next_month' => $nextMonth,
                    'location_type' => $locationType,
                    'division_id' => $divisionId,
                    'generated_rows' => $generatedRows,
                    'carried_rows' => $carriedRows,
                ]),
                'notes' => 'Generate opname bulanan dan opening otomatis modul component',
            ]);
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => 'Gagal generate carry-forward bulanan component.',
            ];
        }

        $this->db->trans_commit();

        return [
            'ok' => true,
            'message' => 'Generate opname bulanan component berhasil. Opening bulan berikutnya juga sudah dibuat.',
            'data' => [
                'month' => $monthKey,
                'next_month' => $nextMonth,
                'generated_rows' => $generatedRows,
                'carried_rows' => $carriedRows,
            ],
        ];
    }

    public function save_component_opening(array $header, array $lines, int $actorEmployeeId): array
    {
        $resolved = $this->resolve_opening_component_context($lines);
        if (!($resolved['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string)($resolved['message'] ?? 'Data opening tidak valid.')];
        }

        $resolvedDivisionId = (int)($resolved['division_id'] ?? 0);
        $locationType = $this->location_group_to_component_location($resolved['component_context'] ?? null, (string)($header['location_type'] ?? ''));
        if ($locationType === null) {
            return ['ok' => false, 'message' => 'Lokasi opening harus REGULER atau EVENT dan sesuai divisi component.'];
        }

        $id = (int)($header['id'] ?? 0);
        $payload = [
            'opening_no' => $id > 0 ? (string)$header['opening_no'] : $this->generate_doc_no('inv_component_opening', 'opening_no', 'ICO', (string)$header['opening_date']),
            'opening_date' => (string)$header['opening_date'],
            'location_type' => $locationType,
            'division_id' => $resolvedDivisionId,
            'notes' => $this->nullable_string($header['notes'] ?? null),
            'created_by' => $id > 0 ? null : ($actorEmployeeId > 0 ? $actorEmployeeId : null),
        ];

        $this->db->trans_start();
        if ($id > 0) {
            $row = $this->get_component_opening($id);
            if (!$row || strtoupper((string)$row['status']) !== 'DRAFT') {
                $this->db->trans_complete();
                return ['ok' => false, 'message' => 'Opening hanya bisa diedit saat status DRAFT.'];
            }
            unset($payload['created_by'], $payload['opening_no']);
            $this->db->where('id', $id)->update('inv_component_opening', $payload);
            $this->db->where('opening_id', $id)->delete('inv_component_opening_line');
        } else {
            $payload['status'] = 'DRAFT';
            $this->db->insert('inv_component_opening', $payload);
            $id = (int)$this->db->insert_id();
        }

        $lineNo = 1;
        foreach ($lines as $line) {
            $componentId = (int)($line['component_id'] ?? 0);
            $uomId = (int)($line['uom_id'] ?? 0);
            $qty = round((float)($line['opening_qty'] ?? 0), 4);
            if ($componentId <= 0 || $uomId <= 0 || $qty <= 0) {
                continue;
            }
            $componentContext = $this->component_operational_context($componentId);
            if ((int)($componentContext['operational_division_id'] ?? 0) !== $resolvedDivisionId) {
                $this->db->trans_complete();
                return ['ok' => false, 'message' => 'Divisi component opening tidak konsisten.'];
            }
            $unitCost = round((float)($line['unit_cost'] ?? 0), 6);
            $this->db->insert('inv_component_opening_line', [
                'opening_id' => $id,
                'line_no' => $lineNo++,
                'component_id' => $componentId,
                'uom_id' => $uomId,
                'opening_qty' => $qty,
                'unit_cost' => $unitCost,
                'total_value' => round($qty * $unitCost, 2),
                'note' => $this->nullable_string($line['note'] ?? null),
            ]);
        }
        $this->db->trans_complete();
        if ($this->db->trans_status() === false) {
            return ['ok' => false, 'message' => 'Gagal menyimpan opening.'];
        }
        return ['ok' => true, 'id' => $id];
    }

    public function list_component_adjustments(array $filters = [], int $limit = 200): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $this->db->select('h.*, d.name AS division_name');
        $this->db->from('inv_component_adjustment h');
        $this->db->join('mst_operational_division d', 'd.id = h.division_id', 'left');
        if ($q !== '') {
            $this->db->group_start()
                ->like('h.adjustment_no', $q)
                ->or_like('h.location_type', $q)
                ->group_end();
        }
        $this->db->order_by('h.adjustment_date', 'DESC')->order_by('h.id', 'DESC')->limit(max(1, $limit));
        return $this->db->get()->result_array();
    }

    public function get_component_adjustment(int $id): ?array
    {
        $row = $this->db->get_where('inv_component_adjustment', ['id' => $id])->row_array();
        return $row ?: null;
    }

    public function get_component_adjustment_lines(int $adjustmentId): array
    {
        return $this->db->select('l.*, c.component_name, c.component_code, u.code AS uom_code')
            ->from('inv_component_adjustment_line l')
            ->join('mst_component c', 'c.id = l.component_id', 'left')
            ->join('mst_uom u', 'u.id = l.uom_id', 'left')
            ->where('l.adjustment_id', $adjustmentId)
            ->order_by('l.line_no', 'ASC')
            ->get()->result_array();
    }

    public function save_component_adjustment(array $header, array $lines, int $actorEmployeeId): array
    {
        $id = (int)($header['id'] ?? 0);
        $payload = [
            'adjustment_no' => $id > 0 ? (string)$header['adjustment_no'] : $this->generate_doc_no('inv_component_adjustment', 'adjustment_no', 'ICA', (string)$header['adjustment_date']),
            'adjustment_date' => (string)$header['adjustment_date'],
            'location_type' => strtoupper(trim((string)$header['location_type'])),
            'division_id' => !empty($header['division_id']) ? (int)$header['division_id'] : null,
            'notes' => $this->nullable_string($header['notes'] ?? null),
            'created_by' => $id > 0 ? null : ($actorEmployeeId > 0 ? $actorEmployeeId : null),
        ];
        $this->db->trans_start();
        if ($id > 0) {
            $row = $this->get_component_adjustment($id);
            if (!$row || strtoupper((string)$row['status']) !== 'DRAFT') {
                $this->db->trans_complete();
                return ['ok' => false, 'message' => 'Adjustment hanya bisa diedit saat DRAFT.'];
            }
            unset($payload['created_by'], $payload['adjustment_no']);
            $this->db->where('id', $id)->update('inv_component_adjustment', $payload);
            $this->db->where('adjustment_id', $id)->delete('inv_component_adjustment_line');
        } else {
            $payload['status'] = 'DRAFT';
            $this->db->insert('inv_component_adjustment', $payload);
            $id = (int)$this->db->insert_id();
        }

        $lineNo = 1;
        foreach ($lines as $line) {
            $componentId = (int)($line['component_id'] ?? 0);
            $uomId = (int)($line['uom_id'] ?? 0);
            if ($componentId <= 0 || $uomId <= 0) {
                continue;
            }
            $this->db->insert('inv_component_adjustment_line', [
                'adjustment_id' => $id,
                'line_no' => $lineNo++,
                'component_id' => $componentId,
                'uom_id' => $uomId,
                'available_qty' => round((float)($line['available_qty'] ?? 0), 4),
                'qty_spoil' => round((float)($line['qty_spoil'] ?? 0), 4),
                'qty_waste' => round((float)($line['qty_waste'] ?? 0), 4),
                'qty_adjust_pos' => round((float)($line['qty_adjust_pos'] ?? 0), 4),
                'qty_adjust_neg' => round((float)($line['qty_adjust_neg'] ?? 0), 4),
                'note' => $this->nullable_string($line['note'] ?? null),
            ]);
        }
        $this->db->trans_complete();
        if ($this->db->trans_status() === false) {
            return ['ok' => false, 'message' => 'Gagal menyimpan adjustment.'];
        }
        return ['ok' => true, 'id' => $id];
    }

    public function list_component_batches(array $filters = [], int $limit = 200): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $this->db->select('b.*, c.component_name, c.component_code, d.name AS division_name');
        $this->db->from('inv_component_batch b');
        $this->db->join('mst_component c', 'c.id = b.component_id', 'left');
        $this->db->join('mst_operational_division d', 'd.id = b.division_id', 'left');
        if ($q !== '') {
            $this->db->group_start()
                ->like('b.batch_no', $q)
                ->or_like('c.component_name', $q)
                ->group_end();
        }
        $this->db->order_by('b.batch_date', 'DESC')->order_by('b.id', 'DESC')->limit(max(1, $limit));
        return $this->db->get()->result_array();
    }

    public function get_component_batch(int $id): ?array
    {
        $row = $this->db->get_where('inv_component_batch', ['id' => $id])->row_array();
        return $row ?: null;
    }

    public function get_component_batch_inputs(int $batchId): array
    {
        $this->db->select('i.*, c.component_name, c.component_code, m.material_name, u.code AS uom_code');
        if ($this->db->field_exists('item_id', 'inv_component_batch_input')) {
            $this->db->select('mi.item_name', false);
        }

        $this->db->from('inv_component_batch_input i')
            ->join('mst_component c', 'c.id = i.component_id', 'left')
            ->join('mst_material m', 'm.id = i.material_id', 'left')
            ->join('mst_uom u', 'u.id = i.uom_id', 'left');

        if ($this->db->field_exists('item_id', 'inv_component_batch_input')) {
            $this->db->join('mst_item mi', 'mi.id = i.item_id', 'left');
        }

        return $this->db
            ->where('i.batch_id', $batchId)
            ->order_by('i.line_no', 'ASC')
            ->get()
            ->result_array();
    }

    public function component_batch_preview(array $header): array
    {
        $componentId = (int)($header['component_id'] ?? 0);
        $component = $this->component_batch_component_context($componentId);
        if (!$component) {
            return ['ok' => false, 'message' => 'Output component batch tidak ditemukan.'];
        }
        if ((int)($component['operational_division_id'] ?? 0) <= 0) {
            return ['ok' => false, 'message' => 'Output component batch wajib memiliki divisi operasional.'];
        }

        $locationType = $this->location_group_to_component_location($component, (string)($header['location_type'] ?? ''));
        if ($locationType === null) {
            return ['ok' => false, 'message' => 'Lokasi batch harus REGULER atau EVENT dan sesuai divisi output component.'];
        }

        $baseOutputQty = $this->component_batch_base_output_qty($component);
        $formulaLines = $this->list_component_formulas($componentId);
        if (empty($formulaLines)) {
            return ['ok' => false, 'message' => 'Formula component belum tersedia.'];
        }

        $referenceOptions = [];
        foreach ($formulaLines as $line) {
            if (strtoupper((string)($line['line_type'] ?? '')) !== 'MATERIAL') {
                continue;
            }
            $referenceOptions[] = [
                'line_no' => (int)($line['line_no'] ?? 0),
                'material_id' => (int)(($line[$this->formula_material_column()] ?? 0) ?: 0),
                'label' => (string)($line['material_name'] ?? '-'),
                'base_qty' => round((float)($line['qty'] ?? 0), 4),
                'uom_id' => (int)($line['uom_id'] ?? 0),
                'uom_code' => (string)($line['uom_code'] ?? ''),
            ];
        }

        $scalingMode = strtoupper(trim((string)($header['scaling_mode'] ?? 'BATCH')));
        if (!in_array($scalingMode, ['BATCH', 'REFERENCE'], true)) {
            $scalingMode = 'BATCH';
        }
        $referenceMeta = null;
        if ($scalingMode === 'REFERENCE') {
            if (empty($referenceOptions)) {
                return ['ok' => false, 'message' => 'Formula ini belum punya bahan baku acuan untuk mode produksi berdasarkan bahan.', 'reference_options' => []];
            }
            $referenceLineNo = (int)($header['reference_line_no'] ?? 0);
            $referenceActualQty = round((float)($header['reference_actual_qty'] ?? 0), 4);
            $referenceMeta = null;
            foreach ($referenceOptions as $option) {
                if ((int)$option['line_no'] === $referenceLineNo) {
                    $referenceMeta = $option;
                    break;
                }
            }
            if (!$referenceMeta) {
                return ['ok' => false, 'message' => 'Bahan acuan tidak ditemukan di formula.', 'reference_options' => $referenceOptions];
            }
            if ($referenceActualQty <= 0) {
                return ['ok' => false, 'message' => 'Qty aktual bahan acuan harus lebih dari 0.', 'reference_options' => $referenceOptions];
            }
            $scaleFactor = round($referenceActualQty / max((float)($referenceMeta['base_qty'] ?? 0), 0.0001), 6);
            $batchCount = $scaleFactor;
            $outputQty = round($baseOutputQty * $scaleFactor, 4);
            $referenceMeta['actual_qty'] = $referenceActualQty;
        } else {
            $batchCount = round((float)($header['batch_count'] ?? 0), 4);
            if ($batchCount <= 0) {
                $batchCount = 1.0;
            }
            $scaleFactor = $batchCount;
            $outputQty = round($baseOutputQty * $scaleFactor, 4);
        }
        if ($outputQty <= 0) {
            return ['ok' => false, 'message' => 'Qty output batch harus lebih dari 0.'];
        }

        $state = [
            'line_no' => 1,
            'preview_lines' => [],
            'posting_lines' => [],
            'material_stock' => [],
            'component_stock' => [],
            'issues' => [],
            'material_issue_keys' => [],
            'component_issue_keys' => [],
        ];

        $plan = $this->plan_component_batch_component(
            $component,
            $outputQty,
            $locationType,
            (int)$component['operational_division_id'],
            $state,
            [],
            0,
            false,
            null
        );

        if (!($plan['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string)($plan['message'] ?? 'Preview batch gagal diproses.')];
        }

        $hasShortage = !empty($state['issues']) || !empty($plan['has_shortage']);
        $directMaterialCount = 0;
        $directComponentCount = 0;
        $inlineComponentCount = 0;
        foreach ($state['preview_lines'] as $line) {
            $role = strtoupper((string)($line['plan_role'] ?? ''));
            if ($role === 'MATERIAL_USAGE') {
                $directMaterialCount++;
            } elseif ($role === 'COMPONENT_USAGE') {
                $directComponentCount++;
            } elseif ($role === 'INLINE_OUTPUT') {
                $inlineComponentCount++;
            }
        }

        return [
            'ok' => true,
            'component' => [
                'id' => (int)($component['id'] ?? 0),
                'component_code' => (string)($component['component_code'] ?? ''),
                'component_name' => (string)($component['component_name'] ?? ''),
                'component_type' => (string)($component['component_type'] ?? ''),
                'division_id' => (int)($component['operational_division_id'] ?? 0),
                'division_code' => (string)($component['division_code'] ?? ''),
                'division_name' => (string)($component['division_name'] ?? ''),
                'uom_id' => (int)($component['uom_id'] ?? 0),
                'uom_code' => (string)($component['uom_code'] ?? ''),
                'uom_name' => (string)($component['uom_name'] ?? ''),
            ],
            'location_type' => $locationType,
            'location_group' => $this->location_to_group($locationType),
            'scaling_mode' => $scalingMode,
            'batch_count' => round($batchCount, 4),
            'reference' => $referenceMeta,
            'reference_options' => $referenceOptions,
            'base_output_qty' => round($baseOutputQty, 4),
            'output_qty' => round($outputQty, 4),
            'scale_factor' => round($scaleFactor, 6),
            'summary' => [
                'direct_material_count' => $directMaterialCount,
                'direct_component_count' => $directComponentCount,
                'inline_component_count' => $inlineComponentCount,
                'direct_input_cost' => round((float)($plan['direct_input_cost'] ?? 0), 2),
                'variable_cost_mode' => (string)($plan['variable_cost_mode'] ?? 'DEFAULT'),
                'variable_cost_label' => (string)($plan['variable_cost_label'] ?? 'Default'),
                'variable_cost_pct' => round((float)($plan['variable_cost_pct'] ?? 0), 4),
                'variable_cost_total' => round((float)($plan['variable_cost_total'] ?? 0), 2),
                'total_input_cost' => round((float)($plan['total_input_cost'] ?? 0), 2),
                'unit_cost' => round((float)($plan['unit_cost'] ?? 0), 6),
                'has_shortage' => $hasShortage,
                'shortage_count' => count($state['issues']),
            ],
            'issues' => array_values($state['issues']),
            'lines' => array_values($state['preview_lines']),
            'posting_lines' => array_values($state['posting_lines']),
        ];
    }

    public function save_component_batch(array $header, array $inputs, int $actorEmployeeId): array
    {
        $this->ensure_component_batch_input_trace_columns();

        $outputComponentId = (int)($header['component_id'] ?? 0);
        $outputContext = $this->component_operational_context($outputComponentId);
        if (!$outputContext || (int)($outputContext['operational_division_id'] ?? 0) <= 0) {
            return ['ok' => false, 'message' => 'Output component batch wajib memiliki divisi operasional.'];
        }

        $resolvedDivisionId = (int)$outputContext['operational_division_id'];
        $locationType = $this->location_group_to_component_location($outputContext, (string)($header['location_type'] ?? ''));
        if ($locationType === null) {
            return ['ok' => false, 'message' => 'Lokasi batch harus REGULER atau EVENT dan sesuai divisi output component.'];
        }

        $preview = $this->component_batch_preview([
            'component_id' => $outputComponentId,
            'location_type' => $locationType,
            'scaling_mode' => (string)($header['scaling_mode'] ?? 'BATCH'),
            'batch_count' => (float)($header['batch_count'] ?? 0),
            'reference_line_no' => (int)($header['reference_line_no'] ?? 0),
            'reference_actual_qty' => (float)($header['reference_actual_qty'] ?? 0),
        ]);
        if (!($preview['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string)($preview['message'] ?? 'Preview batch gagal diproses.')];
        }
        if (!empty($preview['summary']['has_shortage'])) {
            $issues = array_values(array_filter((array)($preview['issues'] ?? []), 'strlen'));
            return ['ok' => false, 'message' => !empty($issues) ? $issues[0] : 'Bahan atau component tidak cukup untuk batch ini.'];
        }
        $plannedInputs = array_values(array_filter((array)($preview['posting_lines'] ?? []), static function ($line): bool {
            return is_array($line) && round((float)($line['qty'] ?? 0), 4) > 0;
        }));
        if (empty($plannedInputs)) {
            return ['ok' => false, 'message' => 'Formula batch belum menghasilkan kebutuhan input yang valid.'];
        }

        $id = (int)($header['id'] ?? 0);
        $payload = [
            'batch_no' => $id > 0 ? (string)$header['batch_no'] : $this->generate_doc_no('inv_component_batch', 'batch_no', 'ICB', (string)$header['batch_date']),
            'batch_date' => (string)$header['batch_date'],
            'location_type' => $locationType,
            'division_id' => $resolvedDivisionId,
            'component_id' => $outputComponentId,
            'output_qty' => round((float)($preview['output_qty'] ?? $header['output_qty']), 4),
            'output_uom_id' => (int)($preview['component']['uom_id'] ?? $header['output_uom_id']),
            'notes' => $this->nullable_string($header['notes'] ?? null),
            'created_by' => $id > 0 ? null : ($actorEmployeeId > 0 ? $actorEmployeeId : null),
        ];
        $this->db->trans_start();
        if ($id > 0) {
            $row = $this->get_component_batch($id);
            if (!$row || strtoupper((string)$row['status']) !== 'DRAFT') {
                $this->db->trans_complete();
                return ['ok' => false, 'message' => 'Batch hanya bisa diedit saat DRAFT.'];
            }
            unset($payload['created_by'], $payload['batch_no']);
            $this->db->where('id', $id)->update('inv_component_batch', $payload);
            $this->db->where('batch_id', $id)->delete('inv_component_batch_input');
        } else {
            $payload['status'] = 'DRAFT';
            $this->db->insert('inv_component_batch', $payload);
            $id = (int)$this->db->insert_id();
        }

        $lineNo = 1;
        $totalInputCost = 0.0;
        foreach ($plannedInputs as $line) {
            $planRole = strtoupper(trim((string)($line['plan_role'] ?? 'INPUT')));
            $sourceKind = strtoupper(trim((string)($line['source_kind'] ?? '')));
            $itemId = !empty($line['item_id']) ? (int)$line['item_id'] : null;
            $materialId = !empty($line['material_id']) ? (int)$line['material_id'] : null;
            $componentId = !empty($line['component_id']) ? (int)$line['component_id'] : null;
            $uomId = (int)($line['uom_id'] ?? 0);
            $qty = round((float)($line['qty'] ?? 0), 4);
            if ($sourceKind === '' || $uomId <= 0 || $qty <= 0) {
                continue;
            }
            if ($sourceKind === 'COMPONENT') {
                $componentContext = $this->component_operational_context((int)$componentId);
                if ((int)($componentContext['operational_division_id'] ?? 0) !== $resolvedDivisionId) {
                    $this->db->trans_complete();
                    return ['ok' => false, 'message' => 'Input component batch harus berasal dari divisi yang sama dengan output component.'];
                }
            }
            $unitCost = round((float)($line['unit_cost'] ?? 0), 6);
            $lineCost = round($qty * $unitCost, 2);
            if ($planRole !== 'INLINE_OUTPUT' && $planRole !== 'INLINE_COMPONENT_USAGE') {
                $totalInputCost += $lineCost;
            }
            $row = [
                'batch_id' => $id,
                'line_no' => $lineNo++,
                'plan_role' => $this->db->field_exists('plan_role', 'inv_component_batch_input') ? $planRole : null,
                'source_kind' => $sourceKind,
                'item_id' => $this->db->field_exists('item_id', 'inv_component_batch_input') ? $itemId : null,
                'material_id' => $materialId,
                'component_id' => $componentId,
                'qty' => $qty,
                'uom_id' => $uomId,
                'unit_cost' => $unitCost,
                'total_cost' => $lineCost,
                'notes' => $this->nullable_string($line['notes'] ?? null),
            ];
            if (!$this->db->field_exists('plan_role', 'inv_component_batch_input')) {
                unset($row['plan_role']);
            }
            if (!$this->db->field_exists('item_id', 'inv_component_batch_input')) {
                unset($row['item_id']);
            }
            $this->db->insert('inv_component_batch_input', $row);
        }
        $outputQty = round((float)$payload['output_qty'], 4);
        $storedTotalInputCost = round((float)($preview['summary']['total_input_cost'] ?? $totalInputCost), 2);
        $unitCostOut = $outputQty > 0
            ? round((float)($preview['summary']['unit_cost'] ?? ($storedTotalInputCost / $outputQty)), 6)
            : 0.0;
        $this->db->where('id', $id)->update('inv_component_batch', [
            'total_input_cost' => $storedTotalInputCost,
            'unit_cost' => $unitCostOut,
        ]);

        $this->db->trans_complete();
        if ($this->db->trans_status() === false) {
            return ['ok' => false, 'message' => 'Gagal menyimpan batch.'];
        }
        return ['ok' => true, 'id' => $id];
    }

    private function ensure_component_batch_input_trace_columns(): void
    {
        $table = 'inv_component_batch_input';
        if (!$this->db->table_exists($table)) {
            return;
        }
        if (!$this->db->field_exists('plan_role', $table)) {
            $this->db->query("ALTER TABLE {$table} ADD COLUMN plan_role VARCHAR(40) NULL AFTER line_no");
        }
        if (!$this->db->field_exists('item_id', $table)) {
            $this->db->query("ALTER TABLE {$table} ADD COLUMN item_id BIGINT(20) UNSIGNED NULL AFTER source_kind");
        }
        if (!$this->db->field_exists('fifo_issue_id', $table)) {
            $this->db->query("ALTER TABLE {$table} ADD COLUMN fifo_issue_id BIGINT(20) UNSIGNED NULL AFTER total_cost");
        }
        if (!$this->db->field_exists('fifo_issue_no', $table)) {
            $this->db->query("ALTER TABLE {$table} ADD COLUMN fifo_issue_no VARCHAR(60) NULL AFTER fifo_issue_id");
        }
    }

    private function component_batch_component_context(int $componentId): ?array
    {
        if ($componentId <= 0) {
            return null;
        }

        $divisionNameColumn = $this->division_name_column();
        $divisionCodeColumn = $this->division_code_column();
        $select = [
            'c.id',
            'c.component_code',
            'c.component_name',
            'c.component_type',
            'c.operational_division_id',
            'c.uom_id',
            'c.variable_cost_mode',
            'c.variable_cost_percent',
            'u.code AS uom_code',
            'u.name AS uom_name',
        ];
        if ($this->db->field_exists('std_batch_qty', 'mst_component')) {
            $select[] = 'c.std_batch_qty';
        }
        if ($this->db->field_exists('yield_qty', 'mst_component')) {
            $select[] = 'c.yield_qty';
        }
        if ($this->db->field_exists('yield_percent', 'mst_component')) {
            $select[] = 'c.yield_percent';
        }
        if ($divisionCodeColumn !== null) {
            $select[] = 'd.' . $divisionCodeColumn . ' AS division_code';
        }
        if ($divisionNameColumn !== null) {
            $select[] = 'd.' . $divisionNameColumn . ' AS division_name';
        }

        $row = $this->db->select(implode(', ', $select), false)
            ->from('mst_component c')
            ->join('mst_uom u', 'u.id = c.uom_id', 'left')
            ->join('mst_operational_division d', 'd.id = c.operational_division_id', 'left')
            ->where('c.id', $componentId)
            ->limit(1)
            ->get()
            ->row_array();

        return $row ?: null;
    }

    private function component_batch_base_output_qty(array $component): float
    {
        $baseOutputQty = (float)($component['std_batch_qty'] ?? ($component['yield_qty'] ?? 1));
        if ($baseOutputQty <= 0) {
            $baseOutputQty = 1.0;
        }
        return round($baseOutputQty, 4);
    }

    private function plan_component_batch_component(
        array $component,
        float $requiredOutputQty,
        string $locationType,
        int $divisionId,
        array &$state,
        array $path,
        int $depth,
        bool $inlineStage,
        ?array $consumerComponent
    ): array {
        $componentId = (int)($component['id'] ?? 0);
        if ($componentId <= 0) {
            return ['ok' => false, 'message' => 'Component plan tidak valid.'];
        }
        if (in_array($componentId, $path, true)) {
            return ['ok' => false, 'message' => 'Siklus formula terdeteksi pada component ' . (string)($component['component_name'] ?? ('#' . $componentId)) . '.'];
        }

        $formulaLines = $this->list_component_formulas($componentId);
        if (empty($formulaLines)) {
            return ['ok' => false, 'message' => 'Formula component ' . (string)($component['component_name'] ?? ('#' . $componentId)) . ' belum tersedia.'];
        }

        $baseOutputQty = $this->component_batch_base_output_qty($component);
        $scaleFactor = $requiredOutputQty / max($baseOutputQty, 0.0001);
        $path[] = $componentId;
        $issueCountBefore = count($state['issues']);
        $directInputCost = 0.0;

        foreach ($formulaLines as $line) {
            $lineType = strtoupper((string)($line['line_type'] ?? ''));
            $lineQty = round((float)($line['qty'] ?? 0), 4);
            $requiredQty = round($lineQty * $scaleFactor, 4);
            if ($requiredQty <= 0) {
                continue;
            }

            if ($lineType === 'MATERIAL') {
                $materialPlan = $this->plan_component_batch_material_line($line, $requiredQty, $locationType, $divisionId, $depth, $component, $state);
                $directInputCost += (float)($materialPlan['cost_contribution'] ?? 0);
                continue;
            }

            if ($lineType !== 'COMPONENT') {
                continue;
            }

            $componentPlan = $this->plan_component_batch_sub_component_line(
                $line,
                $requiredQty,
                $locationType,
                $divisionId,
                $depth,
                $component,
                $state,
                $path
            );
            $directInputCost += (float)($componentPlan['cost_contribution'] ?? 0);
        }

        $canProduce = count($state['issues']) === $issueCountBefore;
        $variableMeta = $this->component_batch_variable_meta($component);
        $variableCost = !$inlineStage && $canProduce
            ? round($directInputCost * ((float)($variableMeta['pct'] ?? 0) / 100), 2)
            : 0.0;
        $totalInputCost = round($directInputCost + $variableCost, 2);

        if ($inlineStage && $canProduce) {
            $unitCost = $requiredOutputQty > 0 ? round($directInputCost / $requiredOutputQty, 6) : 0.0;
            $state['preview_lines'][] = $this->component_batch_preview_row([
                'plan_role' => 'INLINE_OUTPUT',
                'source_kind' => 'COMPONENT',
                'depth' => $depth,
                'stage_component_id' => $componentId,
                'stage_component_name' => (string)($component['component_name'] ?? ''),
                'source_label' => (string)($component['component_name'] ?? ''),
                'uom_id' => (int)($component['uom_id'] ?? 0),
                'uom_code' => (string)($component['uom_code'] ?? ''),
                'required_qty' => round($requiredOutputQty, 4),
                'available_qty' => round($requiredOutputQty, 4),
                'unit_cost' => $unitCost,
                'total_cost' => round($directInputCost, 2),
                'cost_contribution' => 0.0,
                'is_short' => false,
                'status_label' => 'INLINE PRODUCED',
                'notes' => 'Hasil inline produksi untuk ' . (string)($consumerComponent['component_name'] ?? 'component parent'),
                'component_id' => $componentId,
            ]);
            $state['posting_lines'][] = [
                'plan_role' => 'INLINE_OUTPUT',
                'source_kind' => 'COMPONENT',
                'item_id' => null,
                'material_id' => null,
                'component_id' => $componentId,
                'uom_id' => (int)($component['uom_id'] ?? 0),
                'qty' => round($requiredOutputQty, 4),
                'unit_cost' => $unitCost,
                'notes' => 'Inline output ' . (string)($component['component_name'] ?? ''),
            ];
        }

        return [
            'ok' => true,
            'has_shortage' => !$canProduce,
            'direct_input_cost' => round($directInputCost, 2),
            'variable_cost_mode' => (string)($variableMeta['mode'] ?? 'DEFAULT'),
            'variable_cost_label' => (string)($variableMeta['label'] ?? 'Default'),
            'variable_cost_pct' => round((float)($variableMeta['pct'] ?? 0), 4),
            'variable_cost_total' => $variableCost,
            'total_input_cost' => $totalInputCost,
            'unit_cost' => $requiredOutputQty > 0 ? round(($inlineStage ? $directInputCost : $totalInputCost) / $requiredOutputQty, 6) : 0.0,
        ];
    }

    private function plan_component_batch_material_line(
        array $line,
        float $requiredQty,
        string $locationType,
        int $divisionId,
        int $depth,
        array $stageComponent,
        array &$state
    ): array {
        $materialCol = $this->formula_material_column();
        $materialId = (int)($line[$materialCol] ?? 0);
        if ($materialId <= 0 && !empty($line['material_item_id'])) {
            $itemId = (int)$line['material_item_id'];
            $legacy = $this->db->select('material_id')->from('mst_item')->where('id', $itemId)->limit(1)->get()->row_array();
            $materialId = (int)($legacy['material_id'] ?? 0);
        }
        $itemId = !empty($line['material_item_id']) ? (int)$line['material_item_id'] : null;
        $uomId = (int)($line['uom_id'] ?? $this->resolve_formula_uom_id('MATERIAL', $materialId, null));
        $stockState = $this->component_batch_material_stock_state($materialId, $itemId, $uomId, $divisionId, $locationType);
        $stockKey = $materialId . '|' . $itemId . '|' . $uomId . '|' . $divisionId . '|' . $locationType;
        $availableQty = array_key_exists($stockKey, $state['material_stock'])
            ? (float)$state['material_stock'][$stockKey]['available_qty']
            : (float)$stockState['available_qty'];
        $unitCost = (float)$stockState['unit_cost'];
        $shortageQty = max(0, round($requiredQty - $availableQty, 4));
        $state['material_stock'][$stockKey] = [
            'available_qty' => max(0, round($availableQty - $requiredQty, 4)),
            'unit_cost' => $unitCost,
        ];

        if ($shortageQty > 0) {
            $issueKey = 'M|' . $materialId . '|' . $divisionId . '|' . $locationType . '|' . $depth . '|' . $stageComponent['id'];
            if (!isset($state['material_issue_keys'][$issueKey])) {
                $state['material_issue_keys'][$issueKey] = true;
                $state['issues'][] = 'Bahan ' . (string)($line['material_name'] ?? ('#' . $materialId)) . ' kurang ' . number_format($shortageQty, 2, '.', '') . ' ' . (string)($line['uom_code'] ?? '');
            }
        }

        $totalCost = round($requiredQty * $unitCost, 2);
        $state['preview_lines'][] = $this->component_batch_preview_row([
            'plan_role' => 'MATERIAL_USAGE',
            'source_kind' => 'MATERIAL',
            'depth' => $depth,
            'stage_component_id' => (int)($stageComponent['id'] ?? 0),
            'stage_component_name' => (string)($stageComponent['component_name'] ?? ''),
            'source_label' => (string)($line['material_name'] ?? ''),
            'uom_id' => $uomId,
            'uom_code' => (string)($line['uom_code'] ?? ''),
            'required_qty' => $requiredQty,
            'available_qty' => round($availableQty, 4),
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'cost_contribution' => $totalCost,
            'is_short' => $shortageQty > 0,
            'status_label' => $shortageQty > 0 ? 'KURANG' : 'READY',
            'notes' => $shortageQty > 0 ? 'Bahan tidak cukup untuk batch ini.' : null,
            'item_id' => $itemId,
            'material_id' => $materialId,
        ]);
        $state['posting_lines'][] = [
            'plan_role' => 'MATERIAL_USAGE',
            'source_kind' => 'MATERIAL',
            'item_id' => $itemId,
            'material_id' => $materialId > 0 ? $materialId : null,
            'component_id' => null,
            'uom_id' => $uomId,
            'qty' => round($requiredQty, 4),
            'unit_cost' => round($unitCost, 6),
            'notes' => 'Pakai bahan untuk ' . (string)($stageComponent['component_name'] ?? 'batch'),
        ];

        return ['cost_contribution' => $totalCost];
    }

    private function plan_component_batch_sub_component_line(
        array $line,
        float $requiredQty,
        string $locationType,
        int $divisionId,
        int $depth,
        array $stageComponent,
        array &$state,
        array $path
    ): array {
        $subComponentId = (int)($line['sub_component_id'] ?? 0);
        $subComponent = $this->component_batch_component_context($subComponentId);
        if (!$subComponent) {
            return ['cost_contribution' => 0.0];
        }
        if ((int)($subComponent['operational_division_id'] ?? 0) !== $divisionId) {
            $issueKey = 'CD|' . $subComponentId . '|' . $divisionId;
            if (!isset($state['component_issue_keys'][$issueKey])) {
                $state['component_issue_keys'][$issueKey] = true;
                $state['issues'][] = 'Component ' . (string)($subComponent['component_name'] ?? ('#' . $subComponentId)) . ' beda divisi dengan batch.';
            }
            return ['cost_contribution' => 0.0];
        }

        $uomId = (int)($line['uom_id'] ?? $subComponent['uom_id'] ?? 0);
        $stockState = $this->component_batch_component_stock_state($subComponentId, $uomId, $divisionId, $locationType);
        $stockKey = $subComponentId . '|' . $uomId . '|' . $divisionId . '|' . $locationType;
        $availableQty = array_key_exists($stockKey, $state['component_stock'])
            ? (float)$state['component_stock'][$stockKey]['available_qty']
            : (float)$stockState['available_qty'];
        $unitCost = (float)$stockState['unit_cost'];

        $costContribution = 0.0;
        $directQty = min($requiredQty, $availableQty);
        if ($directQty > 0) {
            $state['component_stock'][$stockKey] = [
                'available_qty' => max(0, round($availableQty - $directQty, 4)),
                'unit_cost' => $unitCost,
            ];
            $lineCost = round($directQty * $unitCost, 2);
            $costContribution += $lineCost;
            $state['preview_lines'][] = $this->component_batch_preview_row([
                'plan_role' => 'COMPONENT_USAGE',
                'source_kind' => 'COMPONENT',
                'depth' => $depth,
                'stage_component_id' => (int)($stageComponent['id'] ?? 0),
                'stage_component_name' => (string)($stageComponent['component_name'] ?? ''),
                'source_label' => (string)($subComponent['component_name'] ?? ''),
                'uom_id' => $uomId,
                'uom_code' => (string)($subComponent['uom_code'] ?? ''),
                'required_qty' => round($directQty, 4),
                'available_qty' => round($availableQty, 4),
                'unit_cost' => $unitCost,
                'total_cost' => $lineCost,
                'cost_contribution' => $lineCost,
                'is_short' => false,
                'status_label' => 'READY',
                'notes' => 'Pakai stok component yang sudah ada.',
                'component_id' => $subComponentId,
            ]);
            $state['posting_lines'][] = [
                'plan_role' => 'COMPONENT_USAGE',
                'source_kind' => 'COMPONENT',
                'item_id' => null,
                'material_id' => null,
                'component_id' => $subComponentId,
                'uom_id' => $uomId,
                'qty' => round($directQty, 4),
                'unit_cost' => round($unitCost, 6),
                'notes' => 'Pakai component untuk ' . (string)($stageComponent['component_name'] ?? 'batch'),
            ];
        }

        $shortageQty = round($requiredQty - $directQty, 4);
        if ($shortageQty <= 0) {
            return ['cost_contribution' => $costContribution];
        }

        $state['component_stock'][$stockKey] = [
            'available_qty' => 0.0,
            'unit_cost' => $unitCost,
        ];
        $inlinePlan = $this->plan_component_batch_component(
            $subComponent,
            $shortageQty,
            $locationType,
            $divisionId,
            $state,
            $path,
            $depth + 1,
            true,
            $stageComponent
        );
        if (!($inlinePlan['ok'] ?? false)) {
            $issueKey = 'CF|' . $subComponentId . '|' . $divisionId . '|' . $locationType;
            if (!isset($state['component_issue_keys'][$issueKey])) {
                $state['component_issue_keys'][$issueKey] = true;
                $state['issues'][] = (string)($inlinePlan['message'] ?? ('Formula component ' . (string)($subComponent['component_name'] ?? ('#' . $subComponentId)) . ' belum siap untuk inline produksi.'));
            }
            $state['preview_lines'][] = $this->component_batch_preview_row([
                'plan_role' => 'INLINE_COMPONENT_USAGE',
                'source_kind' => 'COMPONENT',
                'depth' => $depth,
                'stage_component_id' => (int)($stageComponent['id'] ?? 0),
                'stage_component_name' => (string)($stageComponent['component_name'] ?? ''),
                'source_label' => (string)($subComponent['component_name'] ?? ''),
                'uom_id' => $uomId,
                'uom_code' => (string)($subComponent['uom_code'] ?? ''),
                'required_qty' => round($shortageQty, 4),
                'available_qty' => 0.0,
                'unit_cost' => 0.0,
                'total_cost' => 0.0,
                'cost_contribution' => 0.0,
                'is_short' => true,
                'status_label' => 'INLINE GAGAL',
                'notes' => (string)($inlinePlan['message'] ?? 'Inline produksi gagal.'),
                'component_id' => $subComponentId,
            ]);
            return ['cost_contribution' => $costContribution];
        }

        if (!empty($inlinePlan['has_shortage'])) {
            $issueKey = 'CS|' . $subComponentId . '|' . $divisionId . '|' . $locationType;
            if (!isset($state['component_issue_keys'][$issueKey])) {
                $state['component_issue_keys'][$issueKey] = true;
                $state['issues'][] = 'Component ' . (string)($subComponent['component_name'] ?? ('#' . $subComponentId)) . ' masih kurang bahan untuk inline produksi.';
            }
            $state['preview_lines'][] = $this->component_batch_preview_row([
                'plan_role' => 'INLINE_COMPONENT_USAGE',
                'source_kind' => 'COMPONENT',
                'depth' => $depth,
                'stage_component_id' => (int)($stageComponent['id'] ?? 0),
                'stage_component_name' => (string)($stageComponent['component_name'] ?? ''),
                'source_label' => (string)($subComponent['component_name'] ?? ''),
                'uom_id' => $uomId,
                'uom_code' => (string)($subComponent['uom_code'] ?? ''),
                'required_qty' => round($shortageQty, 4),
                'available_qty' => 0.0,
                'unit_cost' => 0.0,
                'total_cost' => 0.0,
                'cost_contribution' => 0.0,
                'is_short' => true,
                'status_label' => 'INLINE KURANG',
                'notes' => 'Inline produksi component ini masih terkendala bahan.',
                'component_id' => $subComponentId,
            ]);
            return ['cost_contribution' => $costContribution];
        }

        $inlineUnitCost = (float)($inlinePlan['unit_cost'] ?? 0);
        $inlineTotalCost = round((float)($inlinePlan['direct_input_cost'] ?? 0), 2);
        $costContribution += $inlineTotalCost;
        $state['preview_lines'][] = $this->component_batch_preview_row([
            'plan_role' => 'INLINE_COMPONENT_USAGE',
            'source_kind' => 'COMPONENT',
            'depth' => $depth,
            'stage_component_id' => (int)($stageComponent['id'] ?? 0),
            'stage_component_name' => (string)($stageComponent['component_name'] ?? ''),
            'source_label' => (string)($subComponent['component_name'] ?? ''),
            'uom_id' => $uomId,
            'uom_code' => (string)($subComponent['uom_code'] ?? ''),
            'required_qty' => round($shortageQty, 4),
            'available_qty' => round($shortageQty, 4),
            'unit_cost' => $inlineUnitCost,
            'total_cost' => $inlineTotalCost,
            'cost_contribution' => 0.0,
            'is_short' => false,
            'status_label' => 'INLINE USED',
            'notes' => 'Pakai hasil inline produksi untuk ' . (string)($stageComponent['component_name'] ?? 'batch'),
            'component_id' => $subComponentId,
        ]);
        $state['posting_lines'][] = [
            'plan_role' => 'INLINE_COMPONENT_USAGE',
            'source_kind' => 'COMPONENT',
            'item_id' => null,
            'material_id' => null,
            'component_id' => $subComponentId,
            'uom_id' => $uomId,
            'qty' => round($shortageQty, 4),
            'unit_cost' => round($inlineUnitCost, 6),
            'notes' => 'Pakai hasil inline produksi untuk ' . (string)($stageComponent['component_name'] ?? 'batch'),
        ];

        return ['cost_contribution' => $costContribution];
    }

    private function component_batch_material_stock_state(int $materialId, ?int $itemId, int $uomId, int $divisionId, string $locationType): array
    {
        $availableQty = 0.0;
        $unitCost = 0.0;
        if ($materialId > 0 && $divisionId > 0 && $this->db->table_exists('inv_division_stock_balance')) {
            $destinationType = $locationType;
            $this->db->select('SUM(COALESCE(qty_content_balance,0)) AS qty_balance, AVG(COALESCE(avg_cost_per_content,0)) AS avg_cost_per_content', false)
                ->from('inv_division_stock_balance')
                ->where('division_id', $divisionId)
                ->where('material_id', $materialId);
            if ($this->db->field_exists('destination_type', 'inv_division_stock_balance')) {
                $this->db->where('destination_type', $destinationType);
            }
            if ($uomId > 0 && $this->db->field_exists('content_uom_id', 'inv_division_stock_balance')) {
                $this->db->where('content_uom_id', $uomId);
            }
            if ($itemId !== null && $itemId > 0 && $this->db->field_exists('item_id', 'inv_division_stock_balance')) {
                $this->db->where('item_id', $itemId);
            }
            $row = $this->db->get()->row_array();
            $availableQty = (float)($row['qty_balance'] ?? 0);
            $unitCost = (float)($row['avg_cost_per_content'] ?? 0);
        }
        if ($unitCost <= 0 && $materialId > 0) {
            $fallback = $this->db->select('hpp_standard')->from('mst_material')->where('id', $materialId)->limit(1)->get()->row_array();
            $unitCost = (float)($fallback['hpp_standard'] ?? 0);
        }
        return [
            'available_qty' => round($availableQty, 4),
            'unit_cost' => round($unitCost, 6),
        ];
    }

    private function component_batch_component_stock_state(int $componentId, int $uomId, int $divisionId, string $locationType): array
    {
        $availableQty = 0.0;
        $unitCost = 0.0;
        if ($componentId > 0 && $this->db->table_exists('inv_component_stock_balance')) {
            $this->db->select('SUM(COALESCE(qty_on_hand,0)) AS qty_balance, AVG(COALESCE(avg_cost,0)) AS avg_cost', false)
                ->from('inv_component_stock_balance')
                ->where('component_id', $componentId)
                ->where('location_type', $locationType);
            if ($divisionId > 0) {
                $this->db->where('division_id', $divisionId);
            }
            if ($uomId > 0) {
                $this->db->where('uom_id', $uomId);
            }
            $row = $this->db->get()->row_array();
            $availableQty = (float)($row['qty_balance'] ?? 0);
            $unitCost = (float)($row['avg_cost'] ?? 0);
        }
        if ($unitCost <= 0 && $componentId > 0) {
            $fallback = $this->db->select('hpp_standard')->from('mst_component')->where('id', $componentId)->limit(1)->get()->row_array();
            $unitCost = (float)($fallback['hpp_standard'] ?? 0);
        }
        return [
            'available_qty' => round($availableQty, 4),
            'unit_cost' => round($unitCost, 6),
        ];
    }

    private function component_batch_preview_row(array $payload): array
    {
        $row = [
            'line_no' => (int)($payload['line_no'] ?? 0),
            'plan_role' => (string)($payload['plan_role'] ?? 'INPUT'),
            'source_kind' => (string)($payload['source_kind'] ?? ''),
            'depth' => (int)($payload['depth'] ?? 0),
            'stage_component_id' => (int)($payload['stage_component_id'] ?? 0),
            'stage_component_name' => (string)($payload['stage_component_name'] ?? ''),
            'source_label' => (string)($payload['source_label'] ?? ''),
            'item_id' => !empty($payload['item_id']) ? (int)$payload['item_id'] : null,
            'material_id' => !empty($payload['material_id']) ? (int)$payload['material_id'] : null,
            'component_id' => !empty($payload['component_id']) ? (int)$payload['component_id'] : null,
            'uom_id' => (int)($payload['uom_id'] ?? 0),
            'uom_code' => (string)($payload['uom_code'] ?? ''),
            'required_qty' => round((float)($payload['required_qty'] ?? 0), 4),
            'available_qty' => round((float)($payload['available_qty'] ?? 0), 4),
            'unit_cost' => round((float)($payload['unit_cost'] ?? 0), 6),
            'total_cost' => round((float)($payload['total_cost'] ?? 0), 2),
            'cost_contribution' => round((float)($payload['cost_contribution'] ?? 0), 2),
            'is_short' => !empty($payload['is_short']),
            'status_label' => (string)($payload['status_label'] ?? ''),
            'notes' => $this->nullable_string($payload['notes'] ?? null),
        ];
        if ($row['line_no'] <= 0) {
            $row['line_no'] = 0;
        }
        return $row;
    }

    private function component_batch_variable_meta(array $component): array
    {
        $mode = strtoupper((string)($component['variable_cost_mode'] ?? 'DEFAULT'));
        $percent = (float)($component['variable_cost_percent'] ?? 0);
        if ($mode === 'NONE') {
            $effectivePercent = 0.0;
            $label = 'Tanpa Variable Cost';
        } elseif ($mode === 'CUSTOM') {
            $effectivePercent = max(0.0, $percent);
            $label = 'Custom Component';
        } else {
            $effectivePercent = $this->variable_cost_default_percent('COMPONENT');
            $label = 'Default Component';
            $mode = 'DEFAULT';
        }

        return [
            'mode' => $mode,
            'pct' => round($effectivePercent, 4),
            'label' => $label,
        ];
    }

    public function delete_draft_doc(string $tableHeader, string $tableLine, string $pkName, int $id): array
    {
        $row = $this->db->get_where($tableHeader, ['id' => $id])->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Data tidak ditemukan.'];
        }
        if (strtoupper((string)($row['status'] ?? '')) !== 'DRAFT') {
            return ['ok' => false, 'message' => 'Hanya dokumen DRAFT yang bisa dihapus.'];
        }
        $this->db->trans_start();
        $this->db->where($pkName, $id)->delete($tableLine);
        $this->db->where('id', $id)->delete($tableHeader);
        $this->db->trans_complete();
        if ($this->db->trans_status() === false) {
            return ['ok' => false, 'message' => 'Gagal menghapus dokumen.'];
        }
        return ['ok' => true];
    }

    public function list_component_categories(array $filters = [], int $limit = 300): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $scopeExpr = $this->db->field_exists('scope_type', 'mst_component_category')
            ? "c.scope_type"
            : "'ALL'";
        $this->db->select("
            c.id,
            c.code,
            c.name,
            {$scopeExpr} AS scope_type,
            c.parent_id,
            p.name AS parent_name,
            c.sort_order,
            c.is_active,
            (
                SELECT COUNT(1)
                FROM mst_component mc
                WHERE mc.component_category_id = c.id
            ) AS component_count
        ", false);
        $this->db->from('mst_component_category c');
        $this->db->join('mst_component_category p', 'p.id = c.parent_id', 'left');
        if ($q !== '') {
            $this->db->group_start()
                ->like('c.code', $q)
                ->or_like('c.name', $q)
                ->group_end();
        }
        $this->db->order_by('c.sort_order', 'ASC')->order_by('c.name', 'ASC')->limit(max(1, $limit));
        return $this->db->get()->result_array();
    }

    public function save_component_category(array $data): array
    {
        $id = (int)($data['id'] ?? 0);
        $scopeType = strtoupper(trim((string)($data['scope_type'] ?? 'ALL')));
        if (!in_array($scopeType, ['BASE', 'PREPARE', 'ALL'], true)) {
            $scopeType = 'ALL';
        }
        $parentId = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
        $payload = [
            'code' => strtoupper(trim((string)($data['code'] ?? ''))),
            'name' => trim((string)($data['name'] ?? '')),
            'parent_id' => $parentId,
            'sort_order' => (int)($data['sort_order'] ?? 0),
            'is_active' => isset($data['is_active']) ? (int)((bool)$data['is_active']) : 1,
        ];
        if ($this->db->field_exists('scope_type', 'mst_component_category')) {
            $payload['scope_type'] = $scopeType;
        }
        if ($payload['name'] === '') {
            return ['ok' => false, 'message' => 'Nama kategori wajib diisi.'];
        }
        if ($id > 0 && $parentId !== null && $id === $parentId) {
            return ['ok' => false, 'message' => 'Parent kategori tidak boleh dirinya sendiri.'];
        }
        if ($parentId !== null) {
            $parent = $this->db->select('id, is_active')->from('mst_component_category')->where('id', $parentId)->limit(1)->get()->row_array();
            if (!$parent) {
                return ['ok' => false, 'message' => 'Parent kategori tidak ditemukan.'];
            }
        }
        if ($payload['code'] === '') {
            $payload['code'] = strtoupper(preg_replace('/[^A-Z0-9]+/', '_', $payload['name']));
        }
        if ($id > 0) {
            $this->db->where('id', $id)->update('mst_component_category', $payload);
            return ['ok' => $this->db->affected_rows() >= 0, 'id' => $id];
        }
        $this->db->insert('mst_component_category', $payload);
        return ['ok' => $this->db->affected_rows() > 0, 'id' => (int)$this->db->insert_id()];
    }

    public function list_components(array $filters = [], int $limit = 300): array
    {
        $result = $this->list_components_paginated([
            'q' => (string)($filters['q'] ?? ''),
            'status' => 'ALL',
            'type' => 'ALL',
            'division_id' => 0,
            'category_id' => 0,
            'page' => 1,
            'limit' => $limit,
        ]);
        return $result['rows'] ?? [];
    }

    public function list_components_paginated(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        $type = strtoupper(trim((string)($filters['type'] ?? 'ALL')));
        $divisionId = (int)($filters['division_id'] ?? 0);
        $categoryId = (int)($filters['category_id'] ?? 0);
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = (int)($filters['limit'] ?? 50);
        if ($limit <= 0 || $limit > 300) {
            $limit = 50;
        }

        $base = $this->db->from('mst_component c')
            ->join('mst_component_category cat', 'cat.id = c.component_category_id', 'left')
            ->join('mst_uom u', 'u.id = c.uom_id', 'left')
            ->join('mst_operational_division d', 'd.id = c.operational_division_id', 'left')
            ->join('mst_product_division pd', 'pd.id = c.product_division_id', 'left');

        if ($q !== '') {
            $this->db->group_start()
                ->like('c.component_code', $q)
                ->or_like('c.component_name', $q)
                ->group_end();
        }
        if ($status === 'ACTIVE') {
            $this->db->where('c.is_active', 1);
        } elseif ($status === 'INACTIVE') {
            $this->db->where('c.is_active', 0);
        }
        if (in_array($type, ['BASE', 'PREPARE'], true)) {
            $this->db->where('c.component_type', $type);
        }
        if ($divisionId > 0) {
            $this->db->where('c.operational_division_id', $divisionId);
        }
        if ($categoryId > 0) {
            $this->db->where('c.component_category_id', $categoryId);
        }

        $total = (int)$this->db->count_all_results('', false);
        $totalPages = max(1, (int)ceil($total / $limit));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $limit;

        $formulaCountExpr = $this->db->table_exists('mst_component_formula')
            ? "(
                SELECT COUNT(1)
                FROM mst_component_formula f
                WHERE f.component_id = c.id
            )"
            : '0';
        $componentUsageExpr = $this->db->table_exists('mst_component_formula')
            ? "(
                SELECT COUNT(DISTINCT f.component_id)
                FROM mst_component_formula f
                WHERE f.sub_component_id = c.id
            )"
            : '0';
        $productUsageExpr = $this->db->table_exists('mst_product_recipe')
            ? "(
                SELECT COUNT(DISTINCT r.product_id)
                FROM mst_product_recipe r
                WHERE r.component_id = c.id
            )"
            : '0';

        $this->db->select("
            c.*,
            cat.name AS category_name,
            u.code AS uom_code,
            d.name AS division_name,
            pd.name AS product_division_name,
            (
                SELECT s.avg_cost
                FROM inv_component_stock_balance s
                WHERE s.component_id = c.id
                ORDER BY s.updated_at DESC, s.id DESC
                LIMIT 1
            ) AS stock_hpp_live,
            {$formulaCountExpr} AS formula_count,
            {$componentUsageExpr} AS component_usage_count,
            {$productUsageExpr} AS product_usage_count
        ", false);
        $this->db->order_by('c.component_type', 'ASC');
        $this->db->order_by('d.name', 'ASC');
        $this->db->order_by('cat.name', 'ASC');
        $this->db->order_by('c.component_name', 'ASC');
        $this->db->limit($limit, $offset);
        $rows = $this->db->get()->result_array();

        foreach ($rows as &$row) {
            $stockHppLive = (float)($row['stock_hpp_live'] ?? 0);
            if ($stockHppLive > 0) {
                $row['hpp_live'] = round($stockHppLive, 6);
            } else {
                $mode = strtoupper((string)($row['variable_cost_mode'] ?? 'DEFAULT'));
                $percent = (float)($row['variable_cost_percent'] ?? 0);
                $summary = $this->component_formula_cost_summary((int)($row['id'] ?? 0), (int)($row['operational_division_id'] ?? 0), $mode, $percent);
                $row['hpp_live'] = (float)($summary['hpp_live'] ?? 0);
            }
            $row['usage_count'] = (int)($row['component_usage_count'] ?? 0) + (int)($row['product_usage_count'] ?? 0);
        }
        unset($row);

        return [
            'rows' => $rows,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
            ],
        ];
    }

    public function save_component_master(array $data): array
    {
        $id = (int)($data['id'] ?? 0);
        $payload = [
            'component_code' => strtoupper(trim((string)($data['component_code'] ?? ''))),
            'component_name' => trim((string)($data['component_name'] ?? '')),
            'component_type' => strtoupper(trim((string)($data['component_type'] ?? 'BASE'))),
            'product_division_id' => (int)($data['product_division_id'] ?? 0),
            'operational_division_id' => (int)($data['operational_division_id'] ?? 0),
            'component_category_id' => (int)($data['component_category_id'] ?? 0),
            'uom_id' => (int)($data['uom_id'] ?? 0),
            'hpp_standard' => round((float)($data['hpp_standard'] ?? 0), 6),
            'yield_percent' => round((float)($data['yield_percent'] ?? 100), 2),
            'std_batch_qty' => round((float)($data['std_batch_qty'] ?? 1), 4),
            'process_loss_percent' => round((float)($data['process_loss_percent'] ?? 0), 2),
            'shelf_life_days' => (int)($data['shelf_life_days'] ?? 0),
            'is_active' => isset($data['is_active']) ? (int)((bool)$data['is_active']) : 1,
        ];
        if (!$this->db->field_exists('yield_percent', 'mst_component')) {
            unset($payload['yield_percent']);
        }
        if (!$this->db->field_exists('std_batch_qty', 'mst_component')) {
            unset($payload['std_batch_qty']);
        }
        if (!$this->db->field_exists('process_loss_percent', 'mst_component')) {
            unset($payload['process_loss_percent']);
        }
        if (!$this->db->field_exists('hpp_standard', 'mst_component')) {
            unset($payload['hpp_standard']);
        }
        if (!$this->db->field_exists('shelf_life_days', 'mst_component')) {
            unset($payload['shelf_life_days']);
        }
        if ($payload['component_name'] === '' || $payload['component_category_id'] <= 0 || $payload['uom_id'] <= 0) {
            return ['ok' => false, 'message' => 'Nama, kategori, dan UOM wajib diisi.'];
        }
        if ($payload['product_division_id'] <= 0) {
            return ['ok' => false, 'message' => 'Divisi produk wajib dipilih.'];
        }
        if ($payload['operational_division_id'] <= 0) {
            return ['ok' => false, 'message' => 'Divisi operasional wajib dipilih.'];
        }
        if (!in_array($payload['component_type'], ['BASE', 'PREPARE'], true)) {
            return ['ok' => false, 'message' => 'Tipe component harus BASE/PREPARE.'];
        }
        $category = $this->db->select('id, name' . ($this->db->field_exists('scope_type', 'mst_component_category') ? ', scope_type' : ''))
            ->from('mst_component_category')
            ->where('id', $payload['component_category_id'])
            ->limit(1)
            ->get()
            ->row_array();
        if (!$category) {
            return ['ok' => false, 'message' => 'Kategori component tidak ditemukan.'];
        }
        $existing = null;
        if ($id > 0) {
            $existing = $this->db->select('id, component_type, component_category_id')
                ->from('mst_component')
                ->where('id', $id)
                ->limit(1)
                ->get()
                ->row_array();
            if (!$existing) {
                return ['ok' => false, 'message' => 'Data component tidak ditemukan saat update.'];
            }
        }

        if (isset($category['scope_type'])) {
            $scope = strtoupper((string)$category['scope_type']);
            $isScopeValid = in_array($scope, ['ALL', $payload['component_type']], true);
            $isLegacySameCategory = $id > 0
                && $existing
                && (int)$existing['component_category_id'] === (int)$payload['component_category_id'];
            // Allow edit legacy row as long as category is not changed.
            if (!$isScopeValid && !$isLegacySameCategory) {
                return ['ok' => false, 'message' => 'Kategori tidak sesuai tipe component (' . $payload['component_type'] . ').'];
            }
        }
        if ($payload['component_code'] === '') {
            $payload['component_code'] = strtoupper(substr(preg_replace('/[^A-Z0-9]+/', '', $payload['component_name']), 0, 20));
        }

        $oldDbDebug = $this->db->db_debug;
        $this->db->db_debug = false;
        try {
            if ($id > 0) {
                $this->db->where('id', $id)->update('mst_component', $payload);
                $err = $this->db->error();
                if (!empty($err['code'])) {
                    return ['ok' => false, 'message' => 'Gagal update component: ' . (string)$err['message']];
                }
                return ['ok' => $this->db->affected_rows() >= 0, 'id' => $id];
            }
            $this->db->insert('mst_component', $payload);
            $err = $this->db->error();
            if (!empty($err['code'])) {
                return ['ok' => false, 'message' => 'Gagal simpan component: ' . (string)$err['message']];
            }
            return ['ok' => $this->db->affected_rows() > 0, 'id' => (int)$this->db->insert_id()];
        } finally {
            $this->db->db_debug = $oldDbDebug;
        }
    }

    public function list_components_for_mapping(int $limit = 1000): array
    {
        $limit = max(1, min(5000, $limit));
        return $this->db->select('c.id, c.component_code, c.component_name, c.component_type, c.component_category_id, cat.name AS category_name')
            ->from('mst_component c')
            ->join('mst_component_category cat', 'cat.id = c.component_category_id', 'left')
            ->join('mst_operational_division d', 'd.id = c.operational_division_id', 'left')
            ->order_by('c.component_type', 'ASC')
            ->order_by('d.name', 'ASC')
            ->order_by('cat.name', 'ASC')
            ->order_by('c.component_name', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    public function search_picker_options(string $entity, string $q = '', int $limit = 20, array $options = []): array
    {
        $entity = strtoupper(trim($entity));
        $q = trim($q);
        $limit = max(1, min(50, $limit));

        if ($entity === 'MATERIAL') {
            $this->db->select("m.id, m.material_code AS code, m.material_name AS name, 'MATERIAL' AS entity_type, m.content_uom_id AS uom_id, u.code AS uom_code, u.name AS uom_name", false);
            $this->db->from('mst_material m');
            $this->db->join('mst_uom u', 'u.id = m.content_uom_id', 'left');
            $this->db->where('m.is_active', 1);
            if ($q !== '') {
                $this->db->group_start()
                    ->like('m.material_code', $q)
                    ->or_like('m.material_name', $q)
                    ->group_end();
            }
            return $this->db
                ->order_by('m.material_name', 'ASC')
                ->limit($limit)
                ->get()
                ->result_array();
        }

        $excludeId = (int)($options['exclude_id'] ?? 0);
        $type = strtoupper(trim((string)($options['component_type'] ?? 'ALL')));
        $divisionId = (int)($options['division_id'] ?? 0);
        if (!in_array($type, ['ALL', 'BASE', 'PREPARE'], true)) {
            $type = 'ALL';
        }

        $divisionNameColumn = $this->division_name_column();
        $divisionCodeColumn = $this->division_code_column();
        $select = [
            'c.id',
            'c.component_code AS code',
            'c.component_name AS name',
            'c.component_type AS entity_type',
            'c.uom_id',
            'u.code AS uom_code',
            'u.name AS uom_name',
            'cat.name AS category_name',
            'c.operational_division_id',
        ];
        if ($divisionCodeColumn !== null) {
            $select[] = 'd.' . $divisionCodeColumn . ' AS division_code';
        }
        if ($divisionNameColumn !== null) {
            $select[] = 'd.' . $divisionNameColumn . ' AS division_name';
        }

        $this->db->select(implode(', ', $select), false);
        $this->db->from('mst_component c');
        $this->db->join('mst_uom u', 'u.id = c.uom_id', 'left');
        $this->db->join('mst_component_category cat', 'cat.id = c.component_category_id', 'left');
        $this->db->join('mst_operational_division d', 'd.id = c.operational_division_id', 'left');
        $this->db->where('c.is_active', 1);
        if ($excludeId > 0) {
            $this->db->where('c.id <>', $excludeId);
        }
        if ($divisionId > 0) {
            $this->db->where('c.operational_division_id', $divisionId);
        }
        if (in_array($type, ['BASE', 'PREPARE'], true)) {
            $this->db->where('c.component_type', $type);
        }
        if ($q !== '') {
            $this->db->group_start()
                ->like('c.component_code', $q)
                ->or_like('c.component_name', $q)
                ->group_end();
        }
        return $this->db
            ->order_by('c.component_type', 'ASC')
            ->order_by('c.component_name', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    public function list_unmapped_components(int $limit = 300): array
    {
        $limit = max(1, min(2000, $limit));
        $hasScope = $this->db->field_exists('scope_type', 'mst_component_category');
        $scopeSelect = $hasScope ? 'cat.scope_type' : "'ALL'";
        $this->db->select("c.id, c.component_code, c.component_name, c.component_type, c.component_category_id, cat.name AS category_name, {$scopeSelect} AS category_scope", false);
        $this->db->from('mst_component c');
        $this->db->join('mst_component_category cat', 'cat.id = c.component_category_id', 'left');
        if ($hasScope) {
            $this->db->group_start()
                ->where('cat.id IS NULL', null, false)
                ->or_group_start()
                    ->where('cat.scope_type !=', 'ALL')
                    ->where('cat.scope_type != c.component_type', null, false)
                ->group_end()
            ->group_end();
        } else {
            $this->db->where('cat.id IS NULL', null, false);
        }
        $this->db->order_by('c.component_name', 'ASC');
        $this->db->limit($limit);
        return $this->db->get()->result_array();
    }

    public function quick_map_component_category(int $componentId, int $categoryId): array
    {
        if ($componentId <= 0 || $categoryId <= 0) {
            return ['ok' => false, 'message' => 'Component dan kategori wajib dipilih.'];
        }
        $component = $this->db->select('id, component_type')
            ->from('mst_component')
            ->where('id', $componentId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$component) {
            return ['ok' => false, 'message' => 'Component tidak ditemukan.'];
        }
        $category = $this->db->select('id, name' . ($this->db->field_exists('scope_type', 'mst_component_category') ? ', scope_type' : ''))
            ->from('mst_component_category')
            ->where('id', $categoryId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$category) {
            return ['ok' => false, 'message' => 'Kategori tidak ditemukan.'];
        }
        if (isset($category['scope_type'])) {
            $scope = strtoupper((string)$category['scope_type']);
            $componentType = strtoupper((string)$component['component_type']);
            if (!in_array($scope, ['ALL', $componentType], true)) {
                return ['ok' => false, 'message' => 'Scope kategori tidak cocok untuk tipe component ' . $componentType . '.'];
            }
        }
        $this->db->where('id', $componentId)->update('mst_component', ['component_category_id' => $categoryId]);
        return ['ok' => true];
    }

    public function list_component_formulas(int $componentId): array
    {
        if (array_key_exists($componentId, $this->componentFormulaLinesCache)) {
            return $this->componentFormulaLinesCache[$componentId];
        }
        $uomSelect = "COALESCE(mu.code, cu.code) AS uom_code";
        $materialExpr = "COALESCE(f.material_id, i.material_id)";
        if (!$this->db->field_exists('material_id', 'mst_component_formula')) {
            $materialExpr = "i.material_id";
        }
        $rows = $this->db->select("f.*, {$materialExpr} AS material_id, m.material_name AS material_name, c.component_name AS sub_component_name, {$uomSelect}", false)
            ->from('mst_component_formula f')
            ->join('mst_item i', 'i.id = f.material_item_id', 'left')
            ->join('mst_material m', "m.id = {$materialExpr}", 'left')
            ->join('mst_component c', 'c.id = f.sub_component_id', 'left')
            ->join('mst_uom mu', 'mu.id = m.content_uom_id', 'left')
            ->join('mst_uom cu', 'cu.id = c.uom_id', 'left')
            ->where('f.component_id', $componentId)
            ->order_by('f.line_no', 'ASC')
            ->get()->result_array();
        $this->componentFormulaLinesCache[$componentId] = $rows;
        return $rows;
    }

    public function list_component_formula_components_paginated(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        $type = strtoupper(trim((string)($filters['type'] ?? 'ALL')));
        $divisionId = (int)($filters['division_id'] ?? 0);
        $categoryId = (int)($filters['category_id'] ?? 0);
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = (int)($filters['limit'] ?? 50);
        if ($limit < 0 || $limit > 300) {
            $limit = 50;
        }

        $this->db->from('mst_component c')
            ->join('mst_component_category cat', 'cat.id = c.component_category_id', 'left')
            ->join('mst_uom u', 'u.id = c.uom_id', 'left')
            ->join('mst_operational_division d', 'd.id = c.operational_division_id', 'left');
        if ($q !== '') {
            $this->db->group_start()
                ->like('c.component_code', $q)
                ->or_like('c.component_name', $q)
                ->group_end();
        }
        if ($status === 'ACTIVE') {
            $this->db->where('c.is_active', 1);
        } elseif ($status === 'INACTIVE') {
            $this->db->where('c.is_active', 0);
        }
        if (in_array($type, ['BASE', 'PREPARE'], true)) {
            $this->db->where('c.component_type', $type);
        }
        if ($divisionId > 0) {
            $this->db->where('c.operational_division_id', $divisionId);
        }
        if ($categoryId > 0) {
            $this->db->where('c.component_category_id', $categoryId);
        }

        $total = (int)$this->db->count_all_results('', false);
        if ($limit === 0) {
            $totalPages = 1;
            $page = 1;
            $offset = 0;
        } else {
            $totalPages = max(1, (int)ceil($total / $limit));
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $limit;
        }

        $componentUsageExpr = $this->db->table_exists('mst_component_formula')
            ? "(
                SELECT COUNT(DISTINCT f.component_id)
                FROM mst_component_formula f
                WHERE f.sub_component_id = c.id
            )"
            : '0';
        $productUsageExpr = $this->db->table_exists('mst_product_recipe')
            ? "(
                SELECT COUNT(DISTINCT r.product_id)
                FROM mst_product_recipe r
                WHERE r.component_id = c.id
            )"
            : '0';
        $outputBatchExpr = '1';
        if ($this->db->field_exists('std_batch_qty', 'mst_component')) {
            $outputBatchExpr = 'COALESCE(c.std_batch_qty, 1)';
        } elseif ($this->db->field_exists('yield_qty', 'mst_component')) {
            $outputBatchExpr = 'COALESCE(c.yield_qty, 1)';
        }

        $this->db->select("
            c.id,
            c.component_code,
            c.component_name,
            c.component_type,
            c.operational_division_id,
            c.component_category_id,
            c.uom_id,
            c.is_active,
            c.hpp_standard,
            c.variable_cost_mode,
            c.variable_cost_percent,
            {$outputBatchExpr} AS output_batch_qty,
            cat.name AS category_name,
            d.name AS division_name,
            u.code AS uom_code,
            (
                SELECT COUNT(1)
                FROM mst_component_formula f
                WHERE f.component_id = c.id
            ) AS formula_line_count,
            {$componentUsageExpr} AS component_usage_count,
            {$productUsageExpr} AS product_usage_count
        ", false);
        $this->db->order_by('c.component_type', 'ASC');
        $this->db->order_by('d.name', 'ASC');
        $this->db->order_by('cat.name', 'ASC');
        $this->db->order_by('c.component_name', 'ASC');
        if ($limit > 0) {
            $this->db->limit($limit, $offset);
        }
        $rows = $this->db->get()->result_array();

        foreach ($rows as &$row) {
            $mode = strtoupper((string)($row['variable_cost_mode'] ?? 'DEFAULT'));
            $percent = (float)($row['variable_cost_percent'] ?? 0);
            $summary = $this->component_formula_cost_summary((int)($row['id'] ?? 0), (int)($row['operational_division_id'] ?? 0), $mode, $percent);
            $row['hpp_live'] = (float)($summary['hpp_live'] ?? 0);
            $row['hpp_total'] = (float)($summary['hpp_total'] ?? 0);
            $row['usage_count'] = (int)($row['component_usage_count'] ?? 0) + (int)($row['product_usage_count'] ?? 0);
        }
        unset($row);

        return [
            'rows' => $rows,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
            ],
        ];
    }

    public function component_formula_detail(int $componentId): array
    {
        $componentSelect = 'c.id, c.component_code, c.component_name, c.component_type, c.operational_division_id, c.hpp_standard, c.variable_cost_mode, c.variable_cost_percent, c.uom_id, u.code AS uom_code';
        if ($this->db->field_exists('std_batch_qty', 'mst_component')) {
            $componentSelect .= ', c.std_batch_qty';
        } elseif ($this->db->field_exists('yield_qty', 'mst_component')) {
            $componentSelect .= ', c.yield_qty';
        }
        $component = $this->db->select($componentSelect)
            ->from('mst_component c')
            ->join('mst_uom u', 'u.id = c.uom_id', 'left')
            ->where('c.id', $componentId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$component) {
            return ['ok' => false, 'message' => 'Component tidak ditemukan.'];
        }
        $lines = $this->list_component_formulas($componentId);
        $detailedLines = [];
        $materialCount = 0;
        $componentCount = 0;
        $directCostLive = 0.0;
        $directCostStandard = 0.0;
        $outputBaseQty = (float)($component['std_batch_qty'] ?? ($component['yield_qty'] ?? 1));
        if ($outputBaseQty <= 0) {
            $outputBaseQty = 1;
        }
        $potencyMin = null;
        $potencyBottleneck = null;
        foreach ($lines as $line) {
            $cost = $this->resolve_formula_line_cost($line, (int)$component['operational_division_id']);
            $lineQty = round((float)($line['qty'] ?? 0), 4);
            if (strtoupper((string)($line['line_type'] ?? '')) === 'MATERIAL') {
                $materialCount++;
            } else {
                $componentCount++;
            }
            $lineLiveValue = round($lineQty * (float)$cost['live_unit_cost'], 6);
            $lineStdValue = round($lineQty * (float)$cost['standard_unit_cost'], 6);
            $directCostLive += $lineLiveValue;
            $directCostStandard += $lineStdValue;
            $line['standard_unit_cost'] = (float)$cost['standard_unit_cost'];
            $line['live_unit_cost'] = (float)$cost['live_unit_cost'];
            $line['line_live_total'] = $lineLiveValue;
            $line['line_standard_total'] = $lineStdValue;
            $line['source_label'] = (string)$cost['source_label'];
            $line['available_qty'] = (float)$cost['available_qty'];
            $line['live_cost_source'] = (string)$cost['live_cost_source'];
            $line['live_cost_source_label'] = (string)$cost['live_cost_source_label'];
            $linePotency = 0.0;
            if ($lineQty > 0) {
                $linePotency = ((float)$line['available_qty'] / $lineQty) * $outputBaseQty;
            }
            if ($linePotency < 0) {
                $linePotency = 0.0;
            }
            $line['potential_output_qty'] = round($linePotency, 4);
            if ($potencyMin === null || $linePotency < $potencyMin) {
                $potencyMin = $linePotency;
                $potencyBottleneck = strtoupper((string)($line['line_type'] ?? '')) === 'MATERIAL'
                    ? (string)($line['material_name'] ?? '-')
                    : (string)($line['sub_component_name'] ?? '-');
            }
            $detailedLines[] = $line;
        }

        $mode = strtoupper((string)($component['variable_cost_mode'] ?? 'DEFAULT'));
        $percent = (float)($component['variable_cost_percent'] ?? 0);
        if ($mode === 'NONE') {
            $effectivePercent = 0.0;
        } elseif ($mode === 'CUSTOM') {
            $effectivePercent = max(0.0, $percent);
        } else {
            $effectivePercent = $this->variable_cost_default_percent('COMPONENT');
        }
        $variableStd = round($directCostStandard * ($effectivePercent / 100), 6);
        $variableLive = round($directCostLive * ($effectivePercent / 100), 6);
        $totalCogsStd = round($directCostStandard + $variableStd, 6);
        $totalCogsLive = round($directCostLive + $variableLive, 6);

        return [
            'ok' => true,
            'component' => $component,
            'summary' => [
                'line_count' => count($detailedLines),
                'material_count' => $materialCount,
                'component_count' => $componentCount,
                'hpp_master_standard' => round((float)($component['hpp_standard'] ?? 0), 6),
                'direct_cost_standard' => round($directCostStandard, 6),
                'direct_cost_live' => round($directCostLive, 6),
                'variable_cost_std' => $variableStd,
                'variable_cost_live' => $variableLive,
                'total_cogs_std' => $totalCogsStd,
                'total_cogs_live' => $totalCogsLive,
                'variable_cost_mode' => $mode,
                'variable_cost_percent' => $effectivePercent,
                'std_batch_qty' => round((float)($component['std_batch_qty'] ?? 1), 4),
                'output_qty' => round($outputBaseQty, 4),
                'output_uom_code' => (string)($component['uom_code'] ?? '-'),
                'potential_output_total' => round((float)($potencyMin ?? 0), 4),
                'bottleneck_source' => (string)($potencyBottleneck ?? '-'),
            ],
            'lines' => $detailedLines,
        ];
    }

    public function component_usage_detail(int $componentId): array
    {
        $component = $this->db->select('c.id, c.component_code, c.component_name, c.component_type, u.name AS uom_name')
            ->from('mst_component c')
            ->join('mst_uom u', 'u.id = c.uom_id', 'left')
            ->where('c.id', $componentId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$component) {
            return ['ok' => false, 'message' => 'Component tidak ditemukan.'];
        }

        $componentRows = [];
        if ($this->db->table_exists('mst_component_formula')) {
            $componentRows = $this->db->select("'COMPONENT' AS usage_type, parent.id AS target_id, parent.component_code AS target_code, parent.component_name AS target_name, parent.component_type AS target_kind, d.name AS division_name, COUNT(f.id) AS usage_line_count", false)
                ->from('mst_component_formula f')
                ->join('mst_component parent', 'parent.id = f.component_id', 'inner')
                ->join('mst_operational_division d', 'd.id = parent.operational_division_id', 'left')
                ->where('f.sub_component_id', $componentId)
                ->group_by(['parent.id', 'parent.component_code', 'parent.component_name', 'parent.component_type', 'd.name'])
                ->order_by('parent.component_name', 'ASC')
                ->get()
                ->result_array();
        }

        $productRows = [];
        if ($this->db->table_exists('mst_product_recipe')) {
            $productRows = $this->db->select("'PRODUCT' AS usage_type, p.id AS target_id, p.product_code AS target_code, p.product_name AS target_name, 'PRODUCT' AS target_kind, pd.name AS division_name, COUNT(r.id) AS usage_line_count", false)
                ->from('mst_product_recipe r')
                ->join('mst_product p', 'p.id = r.product_id', 'inner')
                ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
                ->where('r.component_id', $componentId)
                ->group_by(['p.id', 'p.product_code', 'p.product_name', 'pd.name'])
                ->order_by('p.product_name', 'ASC')
                ->get()
                ->result_array();
        }

        $rows = array_merge($componentRows, $productRows);
        usort($rows, static function (array $left, array $right): int {
            $typeCompare = strcmp((string)($left['usage_type'] ?? ''), (string)($right['usage_type'] ?? ''));
            if ($typeCompare !== 0) {
                return $typeCompare;
            }
            return strcasecmp((string)($left['target_name'] ?? ''), (string)($right['target_name'] ?? ''));
        });

        return [
            'ok' => true,
            'component' => $component,
            'rows' => $rows,
            'summary' => [
                'component_usage_count' => count($componentRows),
                'product_usage_count' => count($productRows),
                'usage_count' => count($rows),
            ],
        ];
    }

    public function save_component_formula(array $data): array
    {
        $id = (int)($data['id'] ?? 0);
        $componentId = (int)($data['component_id'] ?? 0);
        $lineType = strtoupper(trim((string)($data['line_type'] ?? '')));
        $materialId = !empty($data['material_id']) ? (int)$data['material_id'] : null;
        $subComponentId = !empty($data['sub_component_id']) ? (int)$data['sub_component_id'] : null;
        $qty = round((float)($data['qty'] ?? 0), 4);
        if ($componentId <= 0 || $qty <= 0) {
            return ['ok' => false, 'message' => 'Component dan qty wajib valid.'];
        }
        if (!in_array($lineType, ['MATERIAL', 'COMPONENT'], true)) {
            return ['ok' => false, 'message' => 'Line type harus MATERIAL/COMPONENT.'];
        }
        $parent = $this->db->select('id, component_type')
            ->from('mst_component')
            ->where('id', $componentId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$parent) {
            return ['ok' => false, 'message' => 'Component parent tidak ditemukan.'];
        }
        if ($lineType === 'MATERIAL' && empty($materialId)) {
            return ['ok' => false, 'message' => 'Material wajib dipilih untuk line MATERIAL.'];
        }
        if ($lineType === 'COMPONENT' && empty($subComponentId)) {
            return ['ok' => false, 'message' => 'Sub component wajib dipilih untuk line COMPONENT.'];
        }
        if ($lineType === 'COMPONENT' && (int)$subComponentId === $componentId) {
            return ['ok' => false, 'message' => 'Sub component tidak boleh sama dengan parent component.'];
        }
        if ($lineType === 'COMPONENT') {
            $sub = $this->db->select('id, component_type')->from('mst_component')->where('id', (int)$subComponentId)->limit(1)->get()->row_array();
            if (!$sub) {
                return ['ok' => false, 'message' => 'Sub component tidak ditemukan.'];
            }
            $parentType = strtoupper((string)$parent['component_type']);
            $subType = strtoupper((string)$sub['component_type']);
            if ($parentType === 'BASE' && $subType !== 'BASE') {
                return ['ok' => false, 'message' => 'Formula BASE hanya boleh memakai bahan baku atau BASE lain.'];
            }
            if (!in_array($parentType, ['BASE', 'PREPARE'], true)) {
                return ['ok' => false, 'message' => 'Tipe parent component tidak valid.'];
            }
            if ($parentType === 'PREPARE' && !in_array($subType, ['BASE', 'PREPARE'], true)) {
                return ['ok' => false, 'message' => 'Formula PREPARE hanya boleh memakai bahan baku, BASE, atau PREPARE.'];
            }
        }
        if ($this->resolve_formula_uom_id($lineType, $materialId, $subComponentId) <= 0) {
            return ['ok' => false, 'message' => 'UOM sumber formula tidak valid.'];
        }
        $materialCol = $this->formula_material_column();
        $payload = [
            'component_id' => $componentId,
            'line_no' => (int)($data['line_no'] ?? 0),
            'line_type' => $lineType,
            $materialCol => $lineType === 'MATERIAL' ? $materialId : null,
            'sub_component_id' => $lineType === 'COMPONENT' ? $subComponentId : null,
            'qty' => $qty,
            'notes' => $this->nullable_string($data['notes'] ?? null),
            'sort_order' => (int)($data['sort_order'] ?? 0),
        ];
        if ($this->db->field_exists('uom_id', 'mst_component_formula')) {
            $payload['uom_id'] = $this->resolve_formula_uom_id($lineType, $materialId, $subComponentId);
        }
        if ($payload['line_no'] <= 0) {
            $last = $this->db->select_max('line_no')->get_where('mst_component_formula', ['component_id' => $componentId])->row_array();
            $payload['line_no'] = (int)($last['line_no'] ?? 0) + 1;
        }
        if ($id > 0) {
            $this->db->where('id', $id)->update('mst_component_formula', $payload);
            return ['ok' => $this->db->affected_rows() >= 0, 'id' => $id];
        }
        $this->db->insert('mst_component_formula', $payload);
        return ['ok' => $this->db->affected_rows() > 0, 'id' => (int)$this->db->insert_id()];
    }

    public function save_component_formula_bulk(int $componentId, array $lines): array
    {
        $component = $this->db->select('id, component_type')
            ->from('mst_component')
            ->where('id', $componentId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$component) {
            return ['ok' => false, 'message' => 'Component tidak ditemukan.'];
        }

        $normalized = [];
        $lineNo = 1;
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $lineType = strtoupper(trim((string)($line['line_type'] ?? '')));
            $qty = round((float)($line['qty'] ?? 0), 4);
            if (!in_array($lineType, ['MATERIAL', 'COMPONENT'], true) || $qty <= 0) {
                continue;
            }
            $materialId = !empty($line['material_id']) ? (int)$line['material_id'] : null;
            $subComponentId = !empty($line['sub_component_id']) ? (int)$line['sub_component_id'] : null;
            if ($lineType === 'MATERIAL' && empty($materialId)) {
                continue;
            }
            if ($lineType === 'COMPONENT') {
                if (empty($subComponentId) || $subComponentId === $componentId) {
                    continue;
                }
                $sub = $this->db->select('id, component_type')->from('mst_component')->where('id', (int)$subComponentId)->limit(1)->get()->row_array();
                if (!$sub) {
                    continue;
                }
                $parentType = strtoupper((string)$component['component_type']);
                $subType = strtoupper((string)$sub['component_type']);
                if ($parentType === 'BASE' && $subType !== 'BASE') {
                    continue;
                }
                if ($parentType === 'PREPARE' && !in_array($subType, ['BASE', 'PREPARE'], true)) {
                    continue;
                }
            }
            $resolvedUomId = $this->resolve_formula_uom_id($lineType, $materialId, $subComponentId);
            if ($resolvedUomId <= 0) {
                continue;
            }

            $materialCol = $this->formula_material_column();
            $row = [
                'component_id' => $componentId,
                'line_no' => $lineNo++,
                'line_type' => $lineType,
                $materialCol => $lineType === 'MATERIAL' ? $materialId : null,
                'sub_component_id' => $lineType === 'COMPONENT' ? $subComponentId : null,
                'qty' => $qty,
                'notes' => $this->nullable_string($line['notes'] ?? null),
                'sort_order' => (int)($line['sort_order'] ?? 0),
            ];
            if ($this->db->field_exists('uom_id', 'mst_component_formula')) {
                $row['uom_id'] = $resolvedUomId;
            }
            $normalized[] = $row;
        }
        if (count($normalized) <= 0) {
            return ['ok' => false, 'message' => 'Minimal harus ada 1 line formula valid.'];
        }

        $this->db->trans_start();
        $this->db->where('component_id', $componentId)->delete('mst_component_formula');
        foreach ($normalized as $row) {
            $this->db->insert('mst_component_formula', $row);
        }
        $this->db->trans_complete();
        if ($this->db->trans_status() === false) {
            return ['ok' => false, 'message' => 'Gagal menyimpan formula bulk.'];
        }
        return ['ok' => true];
    }

    public function variable_cost_default_list(): array
    {
        if (!$this->db->table_exists('mst_variable_cost_default')) {
            return [];
        }
        return $this->db->select('id, scope_code, default_percent, notes, is_active')
            ->from('mst_variable_cost_default')
            ->order_by('scope_code', 'ASC')
            ->get()
            ->result_array();
    }

    public function save_variable_cost_default(array $payload): array
    {
        if (!$this->db->table_exists('mst_variable_cost_default')) {
            return ['ok' => false, 'message' => 'Tabel mst_variable_cost_default belum tersedia.'];
        }
        $scopeCode = strtoupper(trim((string)($payload['scope_code'] ?? '')));
        $defaultPercent = round((float)($payload['default_percent'] ?? 0), 4);
        if (!in_array($scopeCode, ['COMPONENT', 'PRODUCT'], true)) {
            return ['ok' => false, 'message' => 'Scope harus COMPONENT atau PRODUCT.'];
        }
        if ($defaultPercent < 0) {
            return ['ok' => false, 'message' => 'Default percent tidak boleh negatif.'];
        }

        $row = $this->db->select('id')->from('mst_variable_cost_default')->where('scope_code', $scopeCode)->limit(1)->get()->row_array();
        $save = [
            'scope_code' => $scopeCode,
            'default_percent' => $defaultPercent,
            'notes' => $this->nullable_string($payload['notes'] ?? null),
            'is_active' => isset($payload['is_active']) ? (int)((bool)$payload['is_active']) : 1,
        ];
        if ($row) {
            $this->db->where('id', (int)$row['id'])->update('mst_variable_cost_default', $save);
        } else {
            $this->db->insert('mst_variable_cost_default', $save);
        }
        return ['ok' => true];
    }

    public function delete_component_formula(int $id): array
    {
        $row = $this->db->get_where('mst_component_formula', ['id' => $id])->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Line formula tidak ditemukan.'];
        }
        $componentId = (int)($row['component_id'] ?? 0);

        $hasMovement = $this->db->select('id')
            ->from('inv_component_movement_log')
            ->where('component_id', $componentId)
            ->limit(1)
            ->get()
            ->row_array();
        if ($hasMovement) {
            return ['ok' => false, 'message' => 'Formula tidak bisa dihapus karena component sudah memiliki histori movement.'];
        }

        $hasPostedBatch = $this->db->select('id')
            ->from('inv_component_batch')
            ->where('component_id', $componentId)
            ->where('status', 'POSTED')
            ->limit(1)
            ->get()
            ->row_array();
        if ($hasPostedBatch) {
            return ['ok' => false, 'message' => 'Formula tidak bisa dihapus karena component sudah dipakai batch POSTED.'];
        }

        $hasMonthly = $this->db->select('id')
            ->from('inv_component_monthly_opening')
            ->where('component_id', $componentId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$hasMonthly) {
            $hasMonthly = $this->db->select('id')
                ->from('inv_component_monthly_opname')
                ->where('component_id', $componentId)
                ->limit(1)
                ->get()
                ->row_array();
        }
        if ($hasMonthly) {
            return ['ok' => false, 'message' => 'Formula tidak bisa dihapus karena component sudah masuk proses closing/opening bulanan.'];
        }

        $this->db->where('id', $id)->delete('mst_component_formula');
        if ($this->db->affected_rows() <= 0) {
            return ['ok' => false, 'message' => 'Gagal menghapus line formula.'];
        }
        return ['ok' => true];
    }

    public function toggle_active(string $table, int $id): array
    {
        $row = $this->db->get_where($table, ['id' => $id])->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Data tidak ditemukan.'];
        }
        $isActive = (int)($row['is_active'] ?? 0) === 1 ? 0 : 1;
        $this->db->where('id', $id)->update($table, ['is_active' => $isActive]);
        return ['ok' => true, 'is_active' => $isActive];
    }

    private function generate_doc_no(string $table, string $column, string $prefix, string $date): string
    {
        $datePart = date('Ymd', strtotime($date));
        $key = $prefix . $datePart;
        $row = $this->db->select($column)
            ->from($table)
            ->like($column, $key, 'after')
            ->order_by($column, 'DESC')
            ->limit(1)
            ->get()
            ->row_array();
        $seq = 1;
        if (!empty($row[$column])) {
            $suffix = substr((string)$row[$column], strlen($key));
            if (ctype_digit($suffix)) {
                $seq = ((int)$suffix) + 1;
            }
        }
        return $key . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }

    private function nullable_string($value): ?string
    {
        $v = trim((string)$value);
        return $v === '' ? null : $v;
    }

    private function variable_cost_default_percent(string $scopeCode): float
    {
        if (!$this->db->table_exists('mst_variable_cost_default')) {
            return 20.0;
        }
        $row = $this->db->select('default_percent')
            ->from('mst_variable_cost_default')
            ->where('scope_code', strtoupper(trim($scopeCode)))
            ->where('is_active', 1)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$row) {
            return 20.0;
        }
        return max(0.0, (float)($row['default_percent'] ?? 20.0));
    }

    private function component_formula_cost_summary(int $componentId, int $divisionId, string $mode, float $percent): array
    {
        $cacheKey = $componentId . '|' . $divisionId . '|' . strtoupper($mode) . '|' . number_format($percent, 4, '.', '');
        if (isset($this->componentFormulaSummaryCache[$cacheKey])) {
            return $this->componentFormulaSummaryCache[$cacheKey];
        }
        $lines = $this->list_component_formulas($componentId);
        $lineCount = 0;
        $materialCount = 0;
        $componentCount = 0;
        $hppLive = 0.0;
        foreach ($lines as $line) {
            $lineCount++;
            if (strtoupper((string)($line['line_type'] ?? '')) === 'MATERIAL') {
                $materialCount++;
            } else {
                $componentCount++;
            }
            $cost = $this->resolve_formula_line_cost($line, $divisionId);
            $qty = (float)($line['qty'] ?? 0);
            $hppLive += ($qty * (float)$cost['live_unit_cost']);
        }
        if ($mode === 'NONE') {
            $effectivePercent = 0.0;
        } elseif ($mode === 'CUSTOM') {
            $effectivePercent = max(0.0, $percent);
        } else {
            $effectivePercent = $this->variable_cost_default_percent('COMPONENT');
        }
        $summary = [
            'line_count' => $lineCount,
            'material_count' => $materialCount,
            'component_count' => $componentCount,
            'hpp_live' => round($hppLive, 6),
            'hpp_total' => round($hppLive * (1 + ($effectivePercent / 100)), 6),
        ];
        $this->componentFormulaSummaryCache[$cacheKey] = $summary;
        return $summary;
    }

    private function resolve_formula_line_cost(array $line, int $divisionId): array
    {
        $lineType = strtoupper((string)($line['line_type'] ?? ''));
        if ($lineType === 'MATERIAL') {
            $materialCol = $this->formula_material_column();
            $materialId = (int)($line[$materialCol] ?? 0);
            if ($materialId <= 0 && !empty($line['material_item_id'])) {
                $itemId = (int)$line['material_item_id'];
                if (!array_key_exists($itemId, $this->itemMaterialCache)) {
                    $this->itemMaterialCache[$itemId] = $this->db->select('material_id')->from('mst_item')->where('id', $itemId)->limit(1)->get()->row_array();
                }
                $legacy = $this->itemMaterialCache[$itemId];
                $materialId = (int)($legacy['material_id'] ?? 0);
            }
            $cacheKey = 'M|' . $divisionId . '|' . $materialId;
            if (isset($this->formulaLineCostCache[$cacheKey])) {
                return $this->formulaLineCostCache[$cacheKey];
            }
            if (!array_key_exists($materialId, $this->materialLookupCache)) {
                $this->materialLookupCache[$materialId] = $this->db->select('id, material_name, hpp_standard')
                    ->from('mst_material')
                    ->where('id', $materialId)
                    ->limit(1)
                    ->get()
                    ->row_array();
            }
            $material = $this->materialLookupCache[$materialId];
            $standard = (float)($material['hpp_standard'] ?? 0);
            $live = 0.0;
            if ($divisionId > 0 && $this->db->table_exists('inv_division_stock_balance')) {
                $balanceKey = $divisionId . '|' . $materialId;
                if (!array_key_exists($balanceKey, $this->materialBalanceCache)) {
                    $liveRow = $this->db->select('avg_cost_per_content')
                        ->from('inv_division_stock_balance')
                        ->where('division_id', $divisionId)
                        ->where('material_id', $materialId)
                        ->order_by('updated_at', 'DESC')
                        ->limit(1)
                        ->get()
                        ->row_array();
                    $qtyRow = $this->db->select('SUM(COALESCE(qty_content_balance,0)) AS qty_balance', false)
                        ->from('inv_division_stock_balance')
                        ->where('division_id', $divisionId)
                        ->where('material_id', $materialId)
                        ->get()
                        ->row_array();
                    $this->materialBalanceCache[$balanceKey] = [
                        'avg_cost_per_content' => (float)($liveRow['avg_cost_per_content'] ?? 0),
                        'qty_balance' => (float)($qtyRow['qty_balance'] ?? 0),
                    ];
                }
                $live = (float)($this->materialBalanceCache[$balanceKey]['avg_cost_per_content'] ?? 0);
            }
            if ($live <= 0) {
                $live = $standard;
            }
            $availableQty = 0.0;
            if ($divisionId > 0 && $this->db->table_exists('inv_division_stock_balance')) {
                $availableQty = (float)($this->materialBalanceCache[$divisionId . '|' . $materialId]['qty_balance'] ?? 0);
            }
            $result = [
                'standard_unit_cost' => round($standard, 6),
                'live_unit_cost' => round($live, 6),
                'source_label' => 'MATERIAL',
                'available_qty' => round($availableQty, 4),
                'live_cost_source' => $live > 0 && $live !== $standard ? 'STOCK_DIVISION' : 'FALLBACK_STANDARD',
                'live_cost_source_label' => $live > 0 && $live !== $standard ? 'Stok Divisi' : 'Fallback Std',
            ];
            $this->formulaLineCostCache[$cacheKey] = $result;
            return $result;
        }

        $subComponentId = (int)($line['sub_component_id'] ?? 0);
        $cacheKey = 'C|' . $divisionId . '|' . $subComponentId;
        if (isset($this->formulaLineCostCache[$cacheKey])) {
            return $this->formulaLineCostCache[$cacheKey];
        }
        if (!array_key_exists($subComponentId, $this->componentLookupCache)) {
            $this->componentLookupCache[$subComponentId] = $this->db->select('id, component_name, hpp_standard')
                ->from('mst_component')
                ->where('id', $subComponentId)
                ->limit(1)
                ->get()
                ->row_array();
        }
        $sub = $this->componentLookupCache[$subComponentId];
        $standard = (float)($sub['hpp_standard'] ?? 0);
        $live = 0.0;
        if ($this->db->table_exists('inv_component_stock_balance')) {
            $balanceKey = $divisionId . '|' . $subComponentId;
            if (!array_key_exists($balanceKey, $this->componentBalanceCache)) {
                $this->db->select('avg_cost')->from('inv_component_stock_balance')->where('component_id', $subComponentId);
                if ($divisionId > 0) {
                    $this->db->where('division_id', $divisionId);
                }
                $liveRow = $this->db->order_by('updated_at', 'DESC')->limit(1)->get()->row_array();
                $this->db->select('SUM(COALESCE(qty_on_hand,0)) AS qty_balance', false)
                    ->from('inv_component_stock_balance')
                    ->where('component_id', $subComponentId);
                if ($divisionId > 0) {
                    $this->db->where('division_id', $divisionId);
                }
                $qtyRow = $this->db->get()->row_array();
                $this->componentBalanceCache[$balanceKey] = [
                    'avg_cost' => (float)($liveRow['avg_cost'] ?? 0),
                    'qty_balance' => (float)($qtyRow['qty_balance'] ?? 0),
                ];
            }
            $live = (float)($this->componentBalanceCache[$balanceKey]['avg_cost'] ?? 0);
        }
        if ($live <= 0) {
            $live = $standard;
        }
        $availableQty = 0.0;
        if ($this->db->table_exists('inv_component_stock_balance')) {
            $availableQty = (float)($this->componentBalanceCache[$divisionId . '|' . $subComponentId]['qty_balance'] ?? 0);
        }
        $liveSource = ($live > 0 && $live !== $standard) ? 'STOCK_COMPONENT' : 'FALLBACK_STANDARD';
        $result = [
            'standard_unit_cost' => round($standard, 6),
            'live_unit_cost' => round($live, 6),
            'source_label' => 'COMPONENT',
            'available_qty' => round($availableQty, 4),
            'live_cost_source' => $liveSource,
            'live_cost_source_label' => $liveSource === 'STOCK_COMPONENT' ? 'Stok Component' : 'Fallback Std',
        ];
        $this->formulaLineCostCache[$cacheKey] = $result;
        return $result;
    }

    private function resolve_formula_uom_id(string $lineType, ?int $materialId, ?int $subComponentId): int
    {
        $lineType = strtoupper(trim($lineType));
        if ($lineType === 'MATERIAL' && !empty($materialId)) {
            $row = $this->db->select('content_uom_id')
                ->from('mst_material')
                ->where('id', (int)$materialId)
                ->limit(1)
                ->get()
                ->row_array();
            return (int)($row['content_uom_id'] ?? 0);
        }
        if ($lineType === 'COMPONENT' && !empty($subComponentId)) {
            $row = $this->db->select('uom_id')
                ->from('mst_component')
                ->where('id', (int)$subComponentId)
                ->limit(1)
                ->get()
                ->row_array();
            return (int)($row['uom_id'] ?? 0);
        }
        return 0;
    }

    private function formula_material_column(): string
    {
        if ($this->db->field_exists('material_id', 'mst_component_formula')) {
            return 'material_id';
        }
        return 'material_item_id';
    }
}
