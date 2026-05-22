<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Production_model extends CI_Model
{
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

        if ($locationType !== '') {
            $this->db->where('s.location_type', $locationType);
        }
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

        if ($locationType !== '') {
            $this->db->where('m.location_type', $locationType);
        }
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

        if ($locationType !== '') {
            $this->db->where('r.location_type', $locationType);
        }
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
        $this->db->select('h.*, d.name AS division_name');
        $this->db->from('inv_component_opening h');
        $this->db->join('mst_operational_division d', 'd.id = h.division_id', 'left');
        if ($q !== '') {
            $this->db->group_start()
                ->like('h.opening_no', $q)
                ->or_like('h.location_type', $q)
                ->group_end();
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

    public function save_component_opening(array $header, array $lines, int $actorEmployeeId): array
    {
        $id = (int)($header['id'] ?? 0);
        $payload = [
            'opening_no' => $id > 0 ? (string)$header['opening_no'] : $this->generate_doc_no('inv_component_opening', 'opening_no', 'ICO', (string)$header['opening_date']),
            'opening_date' => (string)$header['opening_date'],
            'location_type' => strtoupper(trim((string)$header['location_type'])),
            'division_id' => !empty($header['division_id']) ? (int)$header['division_id'] : null,
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

    public function save_component_batch(array $header, array $inputs, int $actorEmployeeId): array
    {
        $this->ensure_component_batch_input_trace_columns();

        $id = (int)($header['id'] ?? 0);
        $payload = [
            'batch_no' => $id > 0 ? (string)$header['batch_no'] : $this->generate_doc_no('inv_component_batch', 'batch_no', 'ICB', (string)$header['batch_date']),
            'batch_date' => (string)$header['batch_date'],
            'location_type' => strtoupper(trim((string)$header['location_type'])),
            'division_id' => !empty($header['division_id']) ? (int)$header['division_id'] : null,
            'component_id' => (int)$header['component_id'],
            'output_qty' => round((float)$header['output_qty'], 4),
            'output_uom_id' => (int)$header['output_uom_id'],
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
        foreach ($inputs as $line) {
            $sourceKind = strtoupper(trim((string)($line['source_kind'] ?? '')));
            $itemId = !empty($line['item_id']) ? (int)$line['item_id'] : null;
            $materialId = !empty($line['material_id']) ? (int)$line['material_id'] : null;
            $componentId = !empty($line['component_id']) ? (int)$line['component_id'] : null;
            $uomId = (int)($line['uom_id'] ?? 0);
            $qty = round((float)($line['qty'] ?? 0), 4);
            if ($sourceKind === '' || $uomId <= 0 || $qty <= 0) {
                continue;
            }
            $unitCost = round((float)($line['unit_cost'] ?? 0), 6);
            $lineCost = round($qty * $unitCost, 2);
            $totalInputCost += $lineCost;
            $row = [
                'batch_id' => $id,
                'line_no' => $lineNo++,
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
            if (!$this->db->field_exists('item_id', 'inv_component_batch_input')) {
                unset($row['item_id']);
            }
            $this->db->insert('inv_component_batch_input', $row);
        }
        $outputQty = round((float)$payload['output_qty'], 4);
        $unitCostOut = $outputQty > 0 ? round($totalInputCost / $outputQty, 6) : 0.0;
        $this->db->where('id', $id)->update('inv_component_batch', [
            'total_input_cost' => round($totalInputCost, 2),
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
            ) AS hpp_live,
            (
                SELECT COUNT(1)
                FROM mst_component_formula f
                WHERE f.component_id = c.id
            ) AS formula_count
        ", false);
        $this->db->order_by('c.component_type', 'ASC');
        $this->db->order_by('d.name', 'ASC');
        $this->db->order_by('cat.name', 'ASC');
        $this->db->order_by('c.component_name', 'ASC');
        $this->db->limit($limit, $offset);
        $rows = $this->db->get()->result_array();

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
        $uomSelect = "COALESCE(mu.code, cu.code) AS uom_code";
        $materialExpr = "COALESCE(f.material_id, i.material_id)";
        if (!$this->db->field_exists('material_id', 'mst_component_formula')) {
            $materialExpr = "i.material_id";
        }
        return $this->db->select("f.*, {$materialExpr} AS material_id, m.material_name AS material_name, c.component_name AS sub_component_name, {$uomSelect}", false)
            ->from('mst_component_formula f')
            ->join('mst_item i', 'i.id = f.material_item_id', 'left')
            ->join('mst_material m', "m.id = {$materialExpr}", 'left')
            ->join('mst_component c', 'c.id = f.sub_component_id', 'left')
            ->join('mst_uom mu', 'mu.id = m.content_uom_id', 'left')
            ->join('mst_uom cu', 'cu.id = c.uom_id', 'left')
            ->where('f.component_id', $componentId)
            ->order_by('f.line_no', 'ASC')
            ->get()->result_array();
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
            cat.name AS category_name,
            d.name AS division_name,
            u.code AS uom_code,
            (
                SELECT COUNT(1)
                FROM mst_component_formula f
                WHERE f.component_id = c.id
            ) AS formula_line_count
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
            $std = (float)($row['hpp_standard'] ?? 0);
            $mode = strtoupper((string)($row['variable_cost_mode'] ?? 'DEFAULT'));
            $percent = (float)($row['variable_cost_percent'] ?? 0);
            if ($mode === 'NONE') {
                $effectivePercent = 0.0;
            } elseif ($mode === 'CUSTOM') {
                $effectivePercent = max(0.0, $percent);
            } else {
                $effectivePercent = $this->variable_cost_default_percent('COMPONENT');
            }
            $row['hpp_live'] = round($std, 6);
            $row['hpp_total'] = round($std * (1 + ($effectivePercent / 100)), 6);
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
        return [
            'line_count' => $lineCount,
            'material_count' => $materialCount,
            'component_count' => $componentCount,
            'hpp_live' => round($hppLive, 6),
            'hpp_total' => round($hppLive * (1 + ($effectivePercent / 100)), 6),
        ];
    }

    private function resolve_formula_line_cost(array $line, int $divisionId): array
    {
        $lineType = strtoupper((string)($line['line_type'] ?? ''));
        if ($lineType === 'MATERIAL') {
            $materialCol = $this->formula_material_column();
            $materialId = (int)($line[$materialCol] ?? 0);
            if ($materialId <= 0 && !empty($line['material_item_id'])) {
                $legacy = $this->db->select('material_id')->from('mst_item')->where('id', (int)$line['material_item_id'])->limit(1)->get()->row_array();
                $materialId = (int)($legacy['material_id'] ?? 0);
            }
            $material = $this->db->select('id, material_name, hpp_standard')
                ->from('mst_material')
                ->where('id', $materialId)
                ->limit(1)
                ->get()
                ->row_array();
            $standard = (float)($material['hpp_standard'] ?? 0);
            $live = 0.0;
            if ($divisionId > 0 && $this->db->table_exists('inv_division_stock_balance')) {
                $bal = $this->db->select('avg_cost_per_content')
                    ->from('inv_division_stock_balance')
                    ->where('division_id', $divisionId)
                    ->where('material_id', $materialId)
                    ->order_by('updated_at', 'DESC')
                    ->limit(1)
                    ->get()
                    ->row_array();
                $live = (float)($bal['avg_cost_per_content'] ?? 0);
            }
            if ($live <= 0) {
                $live = $standard;
            }
            $availableQty = 0.0;
            if ($divisionId > 0 && $this->db->table_exists('inv_division_stock_balance')) {
                $balQty = $this->db->select('SUM(COALESCE(qty_content_balance,0)) AS qty_balance', false)
                    ->from('inv_division_stock_balance')
                    ->where('division_id', $divisionId)
                    ->where('material_id', $materialId)
                    ->get()
                    ->row_array();
                $availableQty = (float)($balQty['qty_balance'] ?? 0);
            }
            return [
                'standard_unit_cost' => round($standard, 6),
                'live_unit_cost' => round($live, 6),
                'source_label' => 'MATERIAL',
                'available_qty' => round($availableQty, 4),
                'live_cost_source' => $live > 0 && $live !== $standard ? 'STOCK_DIVISION' : 'FALLBACK_STANDARD',
                'live_cost_source_label' => $live > 0 && $live !== $standard ? 'Stok Divisi' : 'Fallback Std',
            ];
        }

        $subComponentId = (int)($line['sub_component_id'] ?? 0);
        $sub = $this->db->select('id, component_name, hpp_standard')
            ->from('mst_component')
            ->where('id', $subComponentId)
            ->limit(1)
            ->get()
            ->row_array();
        $standard = (float)($sub['hpp_standard'] ?? 0);
        $live = 0.0;
        if ($this->db->table_exists('inv_component_stock_balance')) {
            $this->db->select('avg_cost')->from('inv_component_stock_balance')->where('component_id', $subComponentId);
            if ($divisionId > 0) {
                $this->db->where('division_id', $divisionId);
            }
            $bal = $this->db->order_by('updated_at', 'DESC')->limit(1)->get()->row_array();
            $live = (float)($bal['avg_cost'] ?? 0);
        }
        if ($live <= 0) {
            $live = $standard;
        }
        $availableQty = 0.0;
        if ($this->db->table_exists('inv_component_stock_balance')) {
            $this->db->select('SUM(COALESCE(qty_on_hand,0)) AS qty_balance', false)
                ->from('inv_component_stock_balance')
                ->where('component_id', $subComponentId);
            if ($divisionId > 0) {
                $this->db->where('division_id', $divisionId);
            }
            $balQty = $this->db->get()->row_array();
            $availableQty = (float)($balQty['qty_balance'] ?? 0);
        }
        $liveSource = ($live > 0 && $live !== $standard) ? 'STOCK_COMPONENT' : 'FALLBACK_STANDARD';
        return [
            'standard_unit_cost' => round($standard, 6),
            'live_unit_cost' => round($live, 6),
            'source_label' => 'COMPONENT',
            'available_qty' => round($availableQty, 4),
            'live_cost_source' => $liveSource,
            'live_cost_source_label' => $liveSource === 'STOCK_COMPONENT' ? 'Stok Component' : 'Fallback Std',
        ];
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
