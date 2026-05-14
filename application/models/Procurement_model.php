<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Procurement_model extends CI_Model
{
    public function has_division_request_schema(): bool
    {
        return $this->db->table_exists('pur_division_request')
            && $this->db->table_exists('pur_division_request_line')
            && $this->db->table_exists('pur_division_request_link');
    }

    public function has_store_request_schema(): bool
    {
        return $this->db->table_exists('pur_store_request')
            && $this->db->table_exists('pur_store_request_line');
    }

    public function list_destination_options(): array
    {
        return [
            ['value' => 'BAR', 'label' => 'BAR (Reguler)'],
            ['value' => 'KITCHEN', 'label' => 'KITCHEN (Reguler)'],
            ['value' => 'OFFICE', 'label' => 'OFFICE'],
            ['value' => 'BAR_EVENT', 'label' => 'BAR EVENT'],
            ['value' => 'KITCHEN_EVENT', 'label' => 'KITCHEN EVENT'],
            ['value' => 'OTHER', 'label' => 'OTHER'],
        ];
    }

    public function list_store_request_status_options(): array
    {
        return ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED', 'PARTIAL_FULFILLED', 'FULFILLED', 'VOID'];
    }

    public function get_store_request_summary(array $filters): array
    {
        $summary = [
            'total' => 0,
            'draft' => 0,
            'submitted' => 0,
            'approved' => 0,
            'rejected' => 0,
            'fulfilled' => 0,
            'void' => 0,
        ];

        if (!$this->has_store_request_schema()) {
            return $summary;
        }

        $this->db
            ->select('COUNT(*) AS total', false)
            ->select("SUM(CASE WHEN sr.status = 'DRAFT' THEN 1 ELSE 0 END) AS draft", false)
            ->select("SUM(CASE WHEN sr.status = 'SUBMITTED' THEN 1 ELSE 0 END) AS submitted", false)
            ->select("SUM(CASE WHEN sr.status IN ('APPROVED','PARTIAL_FULFILLED') THEN 1 ELSE 0 END) AS approved", false)
            ->select("SUM(CASE WHEN sr.status = 'REJECTED' THEN 1 ELSE 0 END) AS rejected", false)
            ->select("SUM(CASE WHEN sr.status = 'FULFILLED' THEN 1 ELSE 0 END) AS fulfilled", false)
            ->select("SUM(CASE WHEN sr.status = 'VOID' THEN 1 ELSE 0 END) AS void", false)
            ->from('pur_store_request sr');

        $this->apply_store_request_filters($filters, 'sr');
        $row = $this->db->get()->row_array();
        if (!$row) {
            return $summary;
        }

        foreach ($summary as $key => $val) {
            $summary[$key] = (int)($row[$key] ?? 0);
        }

        return $summary;
    }

    public function list_store_requests(array $filters, int $limit, int $offset): array
    {
        if (!$this->has_store_request_schema()) {
            return [];
        }

        $lineAggSql = "SELECT store_request_id, COUNT(*) AS line_count, SUM(qty_content_requested) AS req_content_total, SUM(qty_content_fulfilled) AS fulfilled_content_total FROM pur_store_request_line GROUP BY store_request_id";
        $divisionNameCol = $this->resolve_division_name_column();

        $this->db
            ->select('sr.id, sr.sr_no, sr.request_date, sr.needed_date, sr.request_division_id, sr.destination_type, sr.status, sr.notes, sr.created_at')
            ->select($divisionNameCol . ' AS division_name', false)
            ->select('u.username AS created_by_username')
            ->select('COALESCE(la.line_count, 0) AS line_count', false)
            ->select('COALESCE(la.req_content_total, 0) AS req_content_total', false)
            ->select('COALESCE(la.fulfilled_content_total, 0) AS fulfilled_content_total', false)
            ->from('pur_store_request sr')
            ->join('mst_operational_division d', 'd.id = sr.request_division_id', 'left')
            ->join('auth_user u', 'u.id = sr.created_by', 'left')
            ->join('(' . $lineAggSql . ') la', 'la.store_request_id = sr.id', 'left', false);

        $this->apply_store_request_filters($filters, 'sr');

        return $this->db
            ->order_by('sr.request_date', 'DESC')
            ->order_by('sr.id', 'DESC')
            ->limit($limit, max(0, $offset))
            ->get()
            ->result_array();
    }

    public function count_store_requests(array $filters): int
    {
        if (!$this->has_store_request_schema()) {
            return 0;
        }

        $this->db->from('pur_store_request sr');
        $this->apply_store_request_filters($filters, 'sr');

        $row = $this->db->select('COUNT(*) AS total', false)->get()->row_array();
        return (int)($row['total'] ?? 0);
    }

    public function list_store_request_timeline_map(array $requestIds): array
    {
        $map = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $requestIds), static function ($v) {
            return $v > 0;
        })));
        if (empty($ids) || !$this->has_store_request_schema()) {
            return $map;
        }

        if ($this->db->table_exists('pur_store_request_approval')) {
            $rows = $this->db
                ->select('a.store_request_id, a.action, a.notes, a.created_at, u.username AS actor_username')
                ->from('pur_store_request_approval a')
                ->join('auth_user u', 'u.id = a.actor_user_id', 'left')
                ->where_in('a.store_request_id', $ids)
                ->order_by('a.store_request_id', 'ASC')
                ->order_by('a.id', 'ASC')
                ->get()
                ->result_array();
            foreach ($rows as $row) {
                $rid = (int)($row['store_request_id'] ?? 0);
                if (!isset($map[$rid])) {
                    $map[$rid] = ['approvals' => [], 'fulfillments' => [], 'po_links' => []];
                }
                $map[$rid]['approvals'][] = $row;
            }
        }

        if ($this->db->table_exists('pur_store_request_fulfillment')) {
            $rows = $this->db
                ->select('f.store_request_id, f.fulfillment_no, f.fulfillment_date, f.status, f.created_at, f.notes')
                ->from('pur_store_request_fulfillment f')
                ->where_in('f.store_request_id', $ids)
                ->order_by('f.store_request_id', 'ASC')
                ->order_by('f.id', 'ASC')
                ->get()
                ->result_array();
            foreach ($rows as $row) {
                $rid = (int)($row['store_request_id'] ?? 0);
                if (!isset($map[$rid])) {
                    $map[$rid] = ['approvals' => [], 'fulfillments' => [], 'po_links' => []];
                }
                $map[$rid]['fulfillments'][] = $row;
            }
        }

        if ($this->db->table_exists('pur_store_request_po_link')) {
            $rows = $this->db
                ->select('l.store_request_id, l.purchase_order_id, l.link_type, l.notes, l.created_at, p.po_no, p.status')
                ->from('pur_store_request_po_link l')
                ->join('pur_purchase_order p', 'p.id = l.purchase_order_id', 'left')
                ->where_in('l.store_request_id', $ids)
                ->order_by('l.store_request_id', 'ASC')
                ->order_by('l.id', 'ASC')
                ->get()
                ->result_array();
            foreach ($rows as $row) {
                $rid = (int)($row['store_request_id'] ?? 0);
                if (!isset($map[$rid])) {
                    $map[$rid] = ['approvals' => [], 'fulfillments' => [], 'po_links' => []];
                }
                $map[$rid]['po_links'][] = $row;
            }
        }

        foreach ($ids as $id) {
            if (!isset($map[$id])) {
                $map[$id] = ['approvals' => [], 'fulfillments' => [], 'po_links' => []];
            }
        }

        return $map;
    }

    public function get_store_request_report_rows(array $filters): array
    {
        if (!$this->has_store_request_schema()) {
            return [];
        }

        $divisionNameCol = $this->resolve_division_name_column();
        $lineAggSql = "SELECT store_request_id, SUM(qty_content_requested) AS req_content_total, SUM(qty_content_fulfilled) AS fulfilled_content_total FROM pur_store_request_line GROUP BY store_request_id";

        $this->db
            ->select('sr.request_division_id, sr.destination_type, sr.status')
            ->select($divisionNameCol . ' AS division_name', false)
            ->select('COUNT(*) AS total_request', false)
            ->select('COALESCE(SUM(la.req_content_total),0) AS req_content_total', false)
            ->select('COALESCE(SUM(la.fulfilled_content_total),0) AS fulfilled_content_total', false)
            ->from('pur_store_request sr')
            ->join('mst_operational_division d', 'd.id = sr.request_division_id', 'left')
            ->join('(' . $lineAggSql . ') la', 'la.store_request_id = sr.id', 'left', false);

        $this->apply_store_request_filters($filters, 'sr');

        return $this->db
            ->group_by(['sr.request_division_id', 'sr.destination_type', 'sr.status'])
            ->order_by('division_name', 'ASC')
            ->order_by('sr.destination_type', 'ASC')
            ->order_by('sr.status', 'ASC')
            ->get()
            ->result_array();
    }

    public function search_warehouse_profiles(string $q, int $limit = 20): array
    {
        if (!$this->db->table_exists('inv_warehouse_stock_balance')) {
            return [];
        }

        $q = trim($q);
        $hasWhMaterial = $this->db->field_exists('material_id', 'inv_warehouse_stock_balance');

        $this->db
            ->select('s.item_id, ' . ($hasWhMaterial ? 's.material_id' : 'NULL AS material_id') . ', s.buy_uom_id, s.content_uom_id, s.profile_key', false)
            ->select('s.profile_name, s.profile_brand, s.profile_description, s.profile_expired_date, s.profile_content_per_buy')
            ->select('s.profile_buy_uom_code, s.profile_content_uom_code, s.qty_buy_balance, s.qty_content_balance')
            ->select('i.item_code, i.item_name, ' . ($hasWhMaterial ? 'm.material_code, m.material_name' : 'NULL AS material_code, NULL AS material_name'), false)
            ->from('inv_warehouse_stock_balance s')
            ->join('mst_item i', 'i.id = s.item_id', 'left')
            ->where('COALESCE(s.qty_content_balance, 0) >', 0);

        if ($hasWhMaterial) {
            $this->db->join('mst_material m', 'm.id = s.material_id', 'left');
        }

        if ($q !== '') {
            $this->db->group_start()
                ->like('s.profile_name', $q)
                ->or_like('s.profile_brand', $q)
                ->or_like('s.profile_description', $q)
                ->or_like('s.profile_key', $q)
                ->or_like('i.item_name', $q)
                ->or_like('m.material_name', $q)
                ->group_end();
        }

        $rows = $this->db
            ->order_by('s.qty_content_balance', 'DESC')
            ->limit(max(1, min(100, $limit)))
            ->get()
            ->result_array();

        foreach ($rows as &$row) {
            $row['line_kind'] = (int)($row['material_id'] ?? 0) > 0 ? 'MATERIAL' : 'ITEM';
        }
        unset($row);

        return $rows;
    }

    public function list_division_requests(array $filters, int $limit = 50): array
    {
        if (!$this->has_division_request_schema()) {
            return [];
        }

        $q = trim((string)($filters['q'] ?? ''));
        $divisionId = (int)($filters['division_id'] ?? 0);
        $status = strtoupper(trim((string)($filters['status'] ?? '')));
        $dateStart = $this->normalize_date((string)($filters['date_start'] ?? ''));
        $dateEnd = $this->normalize_date((string)($filters['date_end'] ?? ''));
        $divisionNameCol = $this->resolve_division_name_column();

        $this->db
            ->select('r.id, r.request_no, r.request_date, r.needed_date, r.division_id, r.status, r.notes, r.created_at')
            ->select($divisionNameCol . ' AS division_name', false)
            ->select('u.username AS created_by_username')
            ->select('COALESCE(la.line_total,0) AS line_total, COALESCE(la.qty_total,0) AS qty_total', false)
            ->select('COALESCE(ln.sr_count,0) AS sr_count, COALESCE(ln.po_count,0) AS po_count', false)
            ->from('pur_division_request r')
            ->join('mst_operational_division d', 'd.id = r.division_id', 'left')
            ->join('auth_user u', 'u.id = r.created_by', 'left')
            ->join('(SELECT request_id, COUNT(*) AS line_total, SUM(qty_content_requested) AS qty_total FROM pur_division_request_line GROUP BY request_id) la', 'la.request_id = r.id', 'left', false)
            ->join("(SELECT request_id, SUM(CASE WHEN doc_type='SR' THEN 1 ELSE 0 END) AS sr_count, SUM(CASE WHEN doc_type='PO' THEN 1 ELSE 0 END) AS po_count FROM pur_division_request_link GROUP BY request_id) ln", 'ln.request_id = r.id', 'left', false);

        if ($q !== '') {
            $this->db->group_start()
                ->like('r.request_no', $q)
                ->or_like('r.notes', $q)
                ->group_end();
        }
        if ($divisionId > 0) {
            $this->db->where('r.division_id', $divisionId);
        }
        if ($status !== '' && $status !== 'ALL') {
            $this->db->where('r.status', $status);
        }
        if ($dateStart !== null) {
            $this->db->where('r.request_date >=', $dateStart);
        }
        if ($dateEnd !== null) {
            $this->db->where('r.request_date <=', $dateEnd);
        }

        return $this->db
            ->order_by('r.request_date', 'DESC')
            ->order_by('r.id', 'DESC')
            ->limit(max(1, min(300, $limit)))
            ->get()
            ->result_array();
    }

    public function list_division_request_links_map(array $requestIds): array
    {
        $map = [];
        if (!$this->has_division_request_schema()) {
            return $map;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $requestIds), static function ($id) {
            return $id > 0;
        })));
        if (empty($ids)) {
            return $map;
        }

        $rows = $this->db
            ->select('l.id, l.request_id, l.doc_type, l.doc_id, l.created_at, l.notes')
            ->select("CASE WHEN l.doc_type='SR' THEN sr.sr_no ELSE po.po_no END AS doc_no", false)
            ->select("CASE WHEN l.doc_type='SR' THEN sr.status ELSE po.status END AS doc_status", false)
            ->from('pur_division_request_link l')
            ->join('pur_store_request sr', "sr.id = l.doc_id AND l.doc_type='SR'", 'left', false)
            ->join('pur_purchase_order po', "po.id = l.doc_id AND l.doc_type='PO'", 'left', false)
            ->where_in('l.request_id', $ids)
            ->order_by('l.id', 'ASC')
            ->get()
            ->result_array();

        foreach ($rows as $row) {
            $rid = (int)($row['request_id'] ?? 0);
            if (!isset($map[$rid])) {
                $map[$rid] = [];
            }
            $map[$rid][] = $row;
        }

        foreach ($ids as $rid) {
            if (!isset($map[$rid])) {
                $map[$rid] = [];
            }
        }
        return $map;
    }

    public function create_division_request(array $header, array $lines, int $userId, string $sourceIp = ''): array
    {
        if (!$this->has_division_request_schema()) {
            return ['ok' => false, 'message' => 'Schema PO/SR Divisi belum tersedia. Jalankan SQL terbaru dulu.'];
        }
        if (!$this->has_store_request_schema()) {
            return ['ok' => false, 'message' => 'Schema Store Request belum tersedia.'];
        }

        $requestDate = $this->normalize_date((string)($header['request_date'] ?? date('Y-m-d')));
        $neededDate = $this->normalize_date((string)($header['needed_date'] ?? ''));
        $divisionId = (int)($header['division_id'] ?? 0);
        $notes = $this->nullable_string($header['notes'] ?? null);
        if ($requestDate === null || $divisionId <= 0) {
            return ['ok' => false, 'message' => 'Header belum lengkap (tanggal/divisi).'];
        }

        $normalized = $this->normalize_division_request_lines($lines);
        if (!($normalized['ok'] ?? false)) {
            return $normalized;
        }
        $lineRows = (array)($normalized['lines'] ?? []);
        if (empty($lineRows)) {
            return ['ok' => false, 'message' => 'Minimal 1 baris pengajuan wajib diisi.'];
        }

        $this->load->model('Purchase_model');
        $purchaseTypeId = $this->find_inventory_purchase_type_id();
        if ($purchaseTypeId === null || $purchaseTypeId <= 0) {
            return ['ok' => false, 'message' => 'Purchase type inventory untuk auto-PO belum tersedia.'];
        }

        $requestNo = $this->generate_division_request_no($requestDate);
        $srLines = [];
        $poLines = [];
        $insertLines = [];
        $lineNo = 1;

        foreach ($lineRows as $line) {
            $availableContent = $this->get_warehouse_available_content(
                $this->nullable_int($line['item_id'] ?? null),
                $this->nullable_int($line['material_id'] ?? null),
                $this->nullable_int($line['buy_uom_id'] ?? null),
                $this->nullable_int($line['content_uom_id'] ?? null),
                (string)($line['profile_key'] ?? '')
            );
            $requestedContent = round((float)($line['qty_content_requested'] ?? 0), 4);
            $requestedBuy = round((float)($line['qty_buy_requested'] ?? 0), 4);
            $cpb = round((float)($line['profile_content_per_buy'] ?? 1), 6);
            if ($cpb <= 0) {
                $cpb = 1;
            }

            $srContent = min($requestedContent, $availableContent);
            if ($srContent < 0) {
                $srContent = 0;
            }
            $poContent = max(0, $requestedContent - $srContent);
            $srBuy = round($srContent / max($cpb, 0.000001), 4);
            $poBuy = round($poContent / max($cpb, 0.000001), 4);

            $routeType = $srContent > 0 && $poContent > 0 ? 'MIXED' : ($srContent > 0 ? 'SR' : 'PO');
            $insertLines[] = [
                'line_no' => $lineNo++,
                'line_kind' => (string)$line['line_kind'],
                'item_id' => $this->nullable_int($line['item_id'] ?? null),
                'material_id' => $this->nullable_int($line['material_id'] ?? null),
                'profile_key' => (string)$line['profile_key'],
                'profile_name' => $this->nullable_string($line['profile_name'] ?? null),
                'profile_brand' => $this->nullable_string($line['profile_brand'] ?? null),
                'profile_description' => $this->nullable_string($line['profile_description'] ?? null),
                'profile_expired_date' => $this->normalize_date((string)($line['profile_expired_date'] ?? '')),
                'buy_uom_id' => (int)$line['buy_uom_id'],
                'content_uom_id' => (int)$line['content_uom_id'],
                'profile_content_per_buy' => $cpb,
                'profile_buy_uom_code' => $this->nullable_string($line['profile_buy_uom_code'] ?? null),
                'profile_content_uom_code' => $this->nullable_string($line['profile_content_uom_code'] ?? null),
                'qty_buy_requested' => $requestedBuy,
                'qty_content_requested' => $requestedContent,
                'qty_content_available_snapshot' => $availableContent,
                'routed_to' => $routeType,
                'qty_content_to_sr' => $srContent,
                'qty_content_to_po' => $poContent,
                'notes' => $this->nullable_string($line['notes'] ?? null),
                'created_at' => date('Y-m-d H:i:s'),
            ];

            if ($srContent > 0) {
                $srLines[] = [
                    'line_kind' => (string)$line['line_kind'],
                    'item_id' => $this->nullable_int($line['item_id'] ?? null),
                    'material_id' => $this->nullable_int($line['material_id'] ?? null),
                    'profile_key' => (string)$line['profile_key'],
                    'profile_name' => $this->nullable_string($line['profile_name'] ?? null),
                    'profile_brand' => $this->nullable_string($line['profile_brand'] ?? null),
                    'profile_description' => $this->nullable_string($line['profile_description'] ?? null),
                    'profile_expired_date' => $this->normalize_date((string)($line['profile_expired_date'] ?? '')),
                    'buy_uom_id' => (int)$line['buy_uom_id'],
                    'content_uom_id' => (int)$line['content_uom_id'],
                    'profile_content_per_buy' => $cpb,
                    'profile_buy_uom_code' => $this->nullable_string($line['profile_buy_uom_code'] ?? null),
                    'profile_content_uom_code' => $this->nullable_string($line['profile_content_uom_code'] ?? null),
                    'qty_buy_requested' => $srBuy,
                    'qty_content_requested' => $srContent,
                    'notes' => 'Auto route SR dari ' . $requestNo,
                ];
            }
            if ($poContent > 0) {
                $poLines[] = [
                    'line_kind' => (string)$line['line_kind'],
                    'item_id' => $this->nullable_int($line['item_id'] ?? null),
                    'material_id' => $this->nullable_int($line['material_id'] ?? null),
                    'line_description' => 'Auto shortage dari ' . $requestNo,
                    'brand_name' => null,
                    'qty_buy' => $poBuy,
                    'buy_uom_id' => (int)$line['buy_uom_id'],
                    'content_per_buy' => $cpb,
                    'qty_content' => $poContent,
                    'content_uom_id' => (int)$line['content_uom_id'],
                    'conversion_factor_to_content' => 1,
                    'unit_price' => 0,
                    'discount_percent' => 0,
                    'tax_percent' => 0,
                    'profile_key' => (string)$line['profile_key'],
                    'snapshot_item_name' => (string)($line['profile_name'] ?? ''),
                    'snapshot_material_name' => (string)($line['profile_name'] ?? ''),
                    'snapshot_brand_name' => null,
                    'snapshot_line_description' => 'Auto shortage dari ' . $requestNo,
                    'snapshot_buy_uom_code' => (string)($line['profile_buy_uom_code'] ?? ''),
                    'snapshot_content_uom_code' => (string)($line['profile_content_uom_code'] ?? ''),
                ];
            }
        }

        $this->db->trans_begin();
        $this->db->insert('pur_division_request', [
            'request_no' => $requestNo,
            'request_date' => $requestDate,
            'needed_date' => $neededDate,
            'division_id' => $divisionId,
            'status' => 'SUBMITTED',
            'notes' => $notes,
            'created_by' => $userId > 0 ? $userId : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $requestId = (int)$this->db->insert_id();
        if ($requestId <= 0) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal membuat header PO/SR divisi.'];
        }

        foreach ($insertLines as $row) {
            $row['request_id'] = $requestId;
            $this->db->insert('pur_division_request_line', $row);
        }

        $createdSr = null;
        $createdPo = null;

        if (!empty($srLines)) {
            $createSr = $this->create_store_request([
                'request_date' => $requestDate,
                'needed_date' => $neededDate,
                'request_division_id' => $divisionId,
                'destination_type' => $this->infer_destination_from_division($divisionId),
                'notes' => 'Auto SR dari ' . $requestNo,
            ], $srLines, $userId);
            if (!($createSr['ok'] ?? false)) {
                $this->db->trans_rollback();
                return ['ok' => false, 'message' => 'Gagal membuat SR: ' . (string)($createSr['message'] ?? 'error')];
            }

            $srId = (int)($createSr['id'] ?? 0);
            $this->apply_store_request_action($srId, 'SUBMIT', 'Submit otomatis dari ' . $requestNo, $userId);
            $createdSr = ['id' => $srId, 'no' => (string)($createSr['sr_no'] ?? '')];

            $this->db->insert('pur_division_request_link', [
                'request_id' => $requestId,
                'doc_type' => 'SR',
                'doc_id' => $srId,
                'created_at' => date('Y-m-d H:i:s'),
                'notes' => 'Auto route dari division request',
            ]);
        }

        if (!empty($poLines)) {
            $createPo = $this->Purchase_model->store_order_with_lines([
                'request_date' => $requestDate,
                'expected_date' => $neededDate ?: $requestDate,
                'purchase_type_id' => $purchaseTypeId,
                'destination_type' => 'GUDANG',
                'status' => 'DRAFT',
                'external_ref_no' => $requestNo,
                'notes' => 'Auto PO shortage dari ' . $requestNo,
            ], $poLines, $userId, $sourceIp);
            if (!($createPo['ok'] ?? false)) {
                $this->db->trans_rollback();
                return ['ok' => false, 'message' => 'Gagal membuat PO: ' . (string)($createPo['message'] ?? 'error')];
            }
            $poData = (array)($createPo['data'] ?? []);
            $poId = (int)($poData['purchase_order_id'] ?? 0);
            $createdPo = ['id' => $poId, 'no' => (string)($poData['po_no'] ?? '')];

            $this->db->insert('pur_division_request_link', [
                'request_id' => $requestId,
                'doc_type' => 'PO',
                'doc_id' => $poId,
                'created_at' => date('Y-m-d H:i:s'),
                'notes' => 'Auto route shortage dari division request',
            ]);
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal menyimpan pengajuan PO/SR divisi.'];
        }
        $this->db->trans_commit();

        return [
            'ok' => true,
            'message' => 'Pengajuan PO/SR divisi berhasil dibuat.',
            'data' => [
                'request_id' => $requestId,
                'request_no' => $requestNo,
                'created_sr' => $createdSr,
                'created_po' => $createdPo,
            ],
        ];
    }

    public function list_purchasing_verification_queue(array $filters, int $limit = 100): array
    {
        if (!$this->has_division_request_schema()) {
            return [];
        }
        $q = trim((string)($filters['q'] ?? ''));
        $docType = strtoupper(trim((string)($filters['doc_type'] ?? '')));
        $status = strtoupper(trim((string)($filters['status'] ?? '')));

        $this->db
            ->select('r.id AS request_id, r.request_no, r.request_date, r.needed_date, r.division_id, r.status AS request_status')
            ->select('l.id AS link_id, l.doc_type, l.doc_id, l.notes AS link_notes, l.created_at AS linked_at')
            ->select("CASE WHEN l.doc_type='SR' THEN sr.sr_no ELSE po.po_no END AS doc_no", false)
            ->select("CASE WHEN l.doc_type='SR' THEN sr.status ELSE po.status END AS doc_status", false)
            ->from('pur_division_request_link l')
            ->join('pur_division_request r', 'r.id = l.request_id', 'inner')
            ->join('pur_store_request sr', "sr.id = l.doc_id AND l.doc_type='SR'", 'left', false)
            ->join('pur_purchase_order po', "po.id = l.doc_id AND l.doc_type='PO'", 'left', false);

        if ($q !== '') {
            $this->db->group_start()
                ->like('r.request_no', $q)
                ->or_like('sr.sr_no', $q)
                ->or_like('po.po_no', $q)
                ->group_end();
        }
        if (in_array($docType, ['SR', 'PO'], true)) {
            $this->db->where('l.doc_type', $docType);
        }
        if ($status !== '' && $status !== 'ALL') {
            $this->db->where("(CASE WHEN l.doc_type='SR' THEN sr.status ELSE po.status END) =", $status, false);
        }

        return $this->db
            ->order_by('l.id', 'DESC')
            ->limit(max(1, min(400, $limit)))
            ->get()
            ->result_array();
    }

    public function verify_purchasing_po(int $purchaseOrderId, string $action, int $userId, string $sourceIp = ''): array
    {
        $action = strtoupper(trim($action));
        if (!in_array($action, ['APPROVE', 'REJECT', 'VOID'], true)) {
            return ['ok' => false, 'message' => 'Aksi PO tidak valid.'];
        }
        $targetStatus = $action === 'APPROVE' ? 'APPROVED' : ($action === 'REJECT' ? 'REJECTED' : 'VOID');
        $this->load->model('Purchase_model');
        return $this->Purchase_model->update_order_status($purchaseOrderId, $targetStatus, $userId, $sourceIp);
    }

    public function create_store_request(array $header, array $lines, int $userId): array
    {
        if (!$this->has_store_request_schema()) {
            return [
                'ok' => false,
                'message' => 'Schema Store Request belum tersedia. Jalankan SQL 2026-05-14d terlebih dahulu.',
            ];
        }

        $requestDate = $this->normalize_date((string)($header['request_date'] ?? date('Y-m-d')));
        $neededDate = $this->normalize_date((string)($header['needed_date'] ?? ''));
        $divisionId = (int)($header['request_division_id'] ?? 0);
        $destinationType = $this->normalize_destination((string)($header['destination_type'] ?? ''));
        $notes = $this->nullable_string($header['notes'] ?? null);

        if ($requestDate === null || $divisionId <= 0 || $destinationType === null) {
            return [
                'ok' => false,
                'message' => 'Header belum lengkap: request_date, divisi, dan destination wajib valid.',
            ];
        }

        $normalizedLines = $this->normalize_store_request_lines($lines);
        if (!($normalizedLines['ok'] ?? false)) {
            return $normalizedLines;
        }
        $lineRows = (array)($normalizedLines['lines'] ?? []);
        if (empty($lineRows)) {
            return ['ok' => false, 'message' => 'Minimal harus ada 1 line request.'];
        }

        $this->db->trans_begin();
        $srNo = $this->generate_store_request_no($requestDate);

        $headerData = [
            'sr_no' => $srNo,
            'request_date' => $requestDate,
            'needed_date' => $neededDate,
            'request_division_id' => $divisionId,
            'destination_type' => $destinationType,
            'status' => 'DRAFT',
            'notes' => $notes,
            'created_by' => $userId > 0 ? $userId : null,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $this->db->insert('pur_store_request', $headerData);
        $requestId = (int)$this->db->insert_id();

        foreach ($lineRows as $lineData) {
            $lineData['store_request_id'] = $requestId;
            $this->db->insert('pur_store_request_line', $lineData);
        }

        if ($this->db->table_exists('pur_store_request_approval')) {
            $this->db->insert('pur_store_request_approval', [
                'store_request_id' => $requestId,
                'action' => 'SUBMIT',
                'actor_user_id' => $userId > 0 ? $userId : null,
                'actor_name_snapshot' => null,
                'notes' => 'Draft dibuat',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal menyimpan Store Request.'];
        }

        $this->db->trans_commit();
        return [
            'ok' => true,
            'message' => 'Store Request berhasil disimpan sebagai DRAFT.',
            'id' => $requestId,
            'sr_no' => $srNo,
        ];
    }

    public function apply_store_request_action(int $requestId, string $action, string $notes, int $userId): array
    {
        if (!$this->has_store_request_schema()) {
            return ['ok' => false, 'message' => 'Schema Store Request belum tersedia.'];
        }

        if ($requestId <= 0) {
            return ['ok' => false, 'message' => 'Request ID tidak valid.'];
        }

        $action = strtoupper(trim($action));
        $allowed = ['SUBMIT', 'APPROVE', 'REJECT', 'VOID'];
        if (!in_array($action, $allowed, true)) {
            return ['ok' => false, 'message' => 'Action tidak valid.'];
        }

        $request = $this->db->get_where('pur_store_request', ['id' => $requestId])->row_array();
        if (!$request) {
            return ['ok' => false, 'message' => 'Store Request tidak ditemukan.'];
        }

        $status = strtoupper((string)($request['status'] ?? 'DRAFT'));
        $nextStatus = $status;

        if ($action === 'SUBMIT') {
            if ($status !== 'DRAFT') {
                return ['ok' => false, 'message' => 'Hanya DRAFT yang dapat disubmit.'];
            }
            $nextStatus = 'SUBMITTED';
        } elseif ($action === 'APPROVE') {
            if ($status !== 'SUBMITTED') {
                return ['ok' => false, 'message' => 'Hanya SUBMITTED yang dapat diapprove.'];
            }
            $nextStatus = 'APPROVED';
        } elseif ($action === 'REJECT') {
            if ($status !== 'SUBMITTED') {
                return ['ok' => false, 'message' => 'Hanya SUBMITTED yang dapat direject.'];
            }
            $nextStatus = 'REJECTED';
        } elseif ($action === 'VOID') {
            if (!in_array($status, ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED'], true)) {
                return ['ok' => false, 'message' => 'Status saat ini tidak bisa di-void pada tahap ini.'];
            }
            $nextStatus = 'VOID';
        }

        $this->db->trans_begin();
        $update = [
            'status' => $nextStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($action === 'APPROVE') {
            $update['approved_by'] = $userId > 0 ? $userId : null;
            $update['approved_at'] = date('Y-m-d H:i:s');
        }
        if ($action === 'VOID') {
            $update['voided_by'] = $userId > 0 ? $userId : null;
            $update['voided_at'] = date('Y-m-d H:i:s');
            $update['void_reason'] = $this->nullable_string($notes);
        }
        $this->db->where('id', $requestId)->update('pur_store_request', $update);

        if ($this->db->table_exists('pur_store_request_approval')) {
            $this->db->insert('pur_store_request_approval', [
                'store_request_id' => $requestId,
                'action' => $action === 'APPROVE' ? 'APPROVE' : ($action === 'REJECT' ? 'REJECT' : ($action === 'SUBMIT' ? 'SUBMIT' : 'VOID')),
                'actor_user_id' => $userId > 0 ? $userId : null,
                'actor_name_snapshot' => null,
                'notes' => $this->nullable_string($notes),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal memproses aksi Store Request.'];
        }

        $this->db->trans_commit();
        return [
            'ok' => true,
            'message' => 'Aksi berhasil diproses. Status: ' . $nextStatus,
            'status' => $nextStatus,
        ];
    }

    public function preview_split(int $requestId): array
    {
        if (!$this->has_store_request_schema()) {
            return ['ok' => false, 'message' => 'Schema Store Request belum tersedia.'];
        }
        if ($requestId <= 0) {
            return ['ok' => false, 'message' => 'Request ID tidak valid.'];
        }

        $header = $this->db->get_where('pur_store_request', ['id' => $requestId])->row_array();
        if (!$header) {
            return ['ok' => false, 'message' => 'Store Request tidak ditemukan.'];
        }

        $lines = $this->db
            ->from('pur_store_request_line')
            ->where('store_request_id', $requestId)
            ->order_by('line_no', 'ASC')
            ->get()
            ->result_array();

        if (empty($lines)) {
            return ['ok' => false, 'message' => 'Line Store Request tidak ditemukan.'];
        }

        $resultRows = [];
        $totalRequest = 0.0;
        $totalFulfillable = 0.0;
        $totalShortage = 0.0;

        foreach ($lines as $ln) {
            $requestRemain = round((float)($ln['qty_content_requested'] ?? 0) - (float)($ln['qty_content_fulfilled'] ?? 0), 4);
            if ($requestRemain < 0) {
                $requestRemain = 0;
            }
            $available = $this->get_warehouse_available_content(
                $this->nullable_int($ln['item_id'] ?? null),
                $this->nullable_int($ln['material_id'] ?? null),
                $this->nullable_int($ln['buy_uom_id'] ?? null),
                $this->nullable_int($ln['content_uom_id'] ?? null),
                (string)($ln['profile_key'] ?? '')
            );
            $fulfillable = min($requestRemain, $available);
            $shortage = max(0, $requestRemain - $fulfillable);

            $contentPerBuy = round((float)($ln['profile_content_per_buy'] ?? 1), 6);
            if ($contentPerBuy <= 0) {
                $contentPerBuy = 1;
            }

            $resultRows[] = [
                'line_id' => (int)$ln['id'],
                'line_no' => (int)$ln['line_no'],
                'line_kind' => (string)$ln['line_kind'],
                'item_id' => (int)($ln['item_id'] ?? 0),
                'material_id' => (int)($ln['material_id'] ?? 0),
                'profile_key' => (string)($ln['profile_key'] ?? ''),
                'profile_name' => (string)($ln['profile_name'] ?? ''),
                'profile_buy_uom_code' => (string)($ln['profile_buy_uom_code'] ?? ''),
                'profile_content_uom_code' => (string)($ln['profile_content_uom_code'] ?? ''),
                'buy_uom_id' => (int)($ln['buy_uom_id'] ?? 0),
                'content_uom_id' => (int)($ln['content_uom_id'] ?? 0),
                'content_per_buy' => $contentPerBuy,
                'request_remain_content' => $requestRemain,
                'available_content' => $available,
                'fulfillable_content' => $fulfillable,
                'shortage_content' => $shortage,
                'fulfillable_buy' => round($fulfillable / max($contentPerBuy, 0.000001), 4),
                'shortage_buy' => round($shortage / max($contentPerBuy, 0.000001), 4),
            ];

            $totalRequest += $requestRemain;
            $totalFulfillable += $fulfillable;
            $totalShortage += $shortage;
        }

        return [
            'ok' => true,
            'header' => $header,
            'rows' => $resultRows,
            'totals' => [
                'request_content' => round($totalRequest, 4),
                'fulfillable_content' => round($totalFulfillable, 4),
                'shortage_content' => round($totalShortage, 4),
            ],
        ];
    }

    public function fulfill_auto_from_warehouse(int $requestId, string $fulfillmentDate, string $notes, int $userId): array
    {
        if (!$this->db->table_exists('pur_store_request_fulfillment') || !$this->db->table_exists('pur_store_request_fulfillment_line')) {
            return ['ok' => false, 'message' => 'Schema fulfillment belum tersedia.'];
        }

        $preview = $this->preview_split($requestId);
        if (!($preview['ok'] ?? false)) {
            return $preview;
        }

        $header = (array)($preview['header'] ?? []);
        $status = strtoupper((string)($header['status'] ?? 'DRAFT'));
        if (!in_array($status, ['APPROVED', 'PARTIAL_FULFILLED'], true)) {
            return ['ok' => false, 'message' => 'Fulfillment hanya bisa dari status APPROVED/PARTIAL_FULFILLED.'];
        }

        $date = $this->normalize_date($fulfillmentDate);
        if ($date === null) {
            $date = date('Y-m-d');
        }

        $rows = (array)($preview['rows'] ?? []);
        $toFulfill = [];
        foreach ($rows as $row) {
            $qtyContent = round((float)($row['fulfillable_content'] ?? 0), 4);
            if ($qtyContent <= 0) {
                continue;
            }
            $toFulfill[] = $row;
        }
        if (empty($toFulfill)) {
            return ['ok' => false, 'message' => 'Tidak ada qty yang bisa dipenuhi dari stok gudang.'];
        }

        $this->load->library('InventoryLedger');
        $divisionId = (int)($header['request_division_id'] ?? 0);
        $destinationType = $this->normalize_destination((string)($header['destination_type'] ?? 'OTHER')) ?? 'OTHER';
        $fulfillmentNo = $this->generate_fulfillment_no($date);
        $srNo = (string)($header['sr_no'] ?? ('SR#' . $requestId));

        $this->db->trans_begin();

        $this->db->insert('pur_store_request_fulfillment', [
            'store_request_id' => $requestId,
            'fulfillment_no' => $fulfillmentNo,
            'fulfillment_date' => $date,
            'status' => 'POSTED',
            'notes' => $this->nullable_string($notes),
            'posted_by' => $userId > 0 ? $userId : null,
            'posted_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $fulfillmentId = (int)$this->db->insert_id();
        if ($fulfillmentId <= 0) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal membuat dokumen fulfillment.'];
        }

        foreach ($toFulfill as $row) {
            $lineId = (int)($row['line_id'] ?? 0);
            $line = $this->db->query('SELECT * FROM pur_store_request_line WHERE id = ? FOR UPDATE', [$lineId])->row_array();
            if (!$line) {
                $this->db->trans_rollback();
                return ['ok' => false, 'message' => 'Line SR tidak ditemukan saat posting fulfillment.'];
            }

            $contentPerBuy = round((float)($line['profile_content_per_buy'] ?? 1), 6);
            if ($contentPerBuy <= 0) {
                $contentPerBuy = 1;
            }
            $remaining = round((float)($line['qty_content_requested'] ?? 0) - (float)($line['qty_content_fulfilled'] ?? 0), 4);
            if ($remaining <= 0) {
                continue;
            }

            $available = $this->get_warehouse_available_content(
                $this->nullable_int($line['item_id'] ?? null),
                $this->nullable_int($line['material_id'] ?? null),
                $this->nullable_int($line['buy_uom_id'] ?? null),
                $this->nullable_int($line['content_uom_id'] ?? null),
                (string)($line['profile_key'] ?? '')
            );

            $qtyContent = min($remaining, $available, round((float)($row['fulfillable_content'] ?? 0), 4));
            if ($qtyContent <= 0) {
                continue;
            }
            $qtyBuy = round($qtyContent / max($contentPerBuy, 0.000001), 4);

            $this->db->insert('pur_store_request_fulfillment_line', [
                'fulfillment_id' => $fulfillmentId,
                'store_request_line_id' => (int)$line['id'],
                'item_id' => $this->nullable_int($line['item_id'] ?? null),
                'material_id' => $this->nullable_int($line['material_id'] ?? null),
                'profile_key' => (string)($line['profile_key'] ?? ''),
                'profile_name' => $this->nullable_string($line['profile_name'] ?? null),
                'profile_brand' => $this->nullable_string($line['profile_brand'] ?? null),
                'profile_description' => $this->nullable_string($line['profile_description'] ?? null),
                'profile_expired_date' => $this->normalize_date((string)($line['profile_expired_date'] ?? '')),
                'buy_uom_id' => (int)($line['buy_uom_id'] ?? 0),
                'content_uom_id' => (int)($line['content_uom_id'] ?? 0),
                'profile_content_per_buy' => $contentPerBuy,
                'profile_buy_uom_code' => $this->nullable_string($line['profile_buy_uom_code'] ?? null),
                'profile_content_uom_code' => $this->nullable_string($line['profile_content_uom_code'] ?? null),
                'qty_buy_posted' => $qtyBuy,
                'qty_content_posted' => $qtyContent,
                'unit_cost_snapshot' => 0,
                'notes' => $this->nullable_string($notes),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $commonPayload = [
                'movement_date' => $date,
                'ref_table' => 'pur_store_request_fulfillment',
                'ref_id' => $fulfillmentId,
                'item_id' => $this->nullable_int($line['item_id'] ?? null),
                'material_id' => $this->nullable_int($line['material_id'] ?? null),
                'buy_uom_id' => $this->nullable_int($line['buy_uom_id'] ?? null),
                'content_uom_id' => $this->nullable_int($line['content_uom_id'] ?? null),
                'profile_key' => $this->nullable_string($line['profile_key'] ?? null),
                'profile_name' => $this->nullable_string($line['profile_name'] ?? null),
                'profile_brand' => $this->nullable_string($line['profile_brand'] ?? null),
                'profile_description' => $this->nullable_string($line['profile_description'] ?? null),
                'profile_expired_date' => $this->normalize_date((string)($line['profile_expired_date'] ?? '')),
                'profile_content_per_buy' => $contentPerBuy,
                'profile_buy_uom_code' => $this->nullable_string($line['profile_buy_uom_code'] ?? null),
                'profile_content_uom_code' => $this->nullable_string($line['profile_content_uom_code'] ?? null),
                'created_by' => $userId > 0 ? $userId : null,
                'manage_transaction' => false,
            ];

            $warehousePost = $this->inventoryledger->post(array_merge($commonPayload, [
                'movement_scope' => 'WAREHOUSE',
                'movement_type' => 'TRANSFER_OUT',
                'qty_buy_delta' => -1 * $qtyBuy,
                'qty_content_delta' => -1 * $qtyContent,
                'notes' => 'SR ' . $srNo . ' fulfill ke divisi',
            ]));
            if (!($warehousePost['ok'] ?? false)) {
                $this->db->trans_rollback();
                return ['ok' => false, 'message' => (string)($warehousePost['message'] ?? 'Gagal posting mutasi gudang.')];
            }

            $divisionPost = $this->inventoryledger->post(array_merge($commonPayload, [
                'movement_scope' => 'DIVISION',
                'movement_type' => 'TRANSFER_IN',
                'division_id' => $divisionId,
                'destination_type' => $destinationType,
                'qty_buy_delta' => $qtyBuy,
                'qty_content_delta' => $qtyContent,
                'notes' => 'SR ' . $srNo . ' diterima divisi',
            ]));
            if (!($divisionPost['ok'] ?? false)) {
                $this->db->trans_rollback();
                return ['ok' => false, 'message' => (string)($divisionPost['message'] ?? 'Gagal posting mutasi divisi.')];
            }

            $newFulfilledContent = round((float)($line['qty_content_fulfilled'] ?? 0) + $qtyContent, 4);
            $newFulfilledBuy = round((float)($line['qty_buy_fulfilled'] ?? 0) + $qtyBuy, 4);
            $lineStatus = $newFulfilledContent + 0.0001 >= (float)($line['qty_content_requested'] ?? 0) ? 'DONE' : 'PARTIAL';

            $this->db->where('id', (int)$line['id'])->update('pur_store_request_line', [
                'qty_content_fulfilled' => $newFulfilledContent,
                'qty_buy_fulfilled' => $newFulfilledBuy,
                'line_status' => $lineStatus,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $lineAgg = $this->db
            ->select('SUM(qty_content_requested) AS req_total, SUM(qty_content_fulfilled) AS fulfilled_total', false)
            ->from('pur_store_request_line')
            ->where('store_request_id', $requestId)
            ->get()
            ->row_array();

        $reqTotal = (float)($lineAgg['req_total'] ?? 0);
        $fulfilledTotal = (float)($lineAgg['fulfilled_total'] ?? 0);
        $newStatus = 'APPROVED';
        if ($reqTotal > 0 && $fulfilledTotal > 0) {
            $newStatus = ($fulfilledTotal + 0.0001 >= $reqTotal) ? 'FULFILLED' : 'PARTIAL_FULFILLED';
        }

        $this->db->where('id', $requestId)->update('pur_store_request', [
            'status' => $newStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($this->db->table_exists('pur_store_request_approval')) {
            $this->db->insert('pur_store_request_approval', [
                'store_request_id' => $requestId,
                'action' => 'APPROVE',
                'actor_user_id' => $userId > 0 ? $userId : null,
                'actor_name_snapshot' => null,
                'notes' => 'Fulfillment posted: ' . $fulfillmentNo,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal posting fulfillment Store Request.'];
        }

        $this->db->trans_commit();
        return [
            'ok' => true,
            'message' => 'Fulfillment berhasil diposting.',
            'data' => [
                'fulfillment_id' => $fulfillmentId,
                'fulfillment_no' => $fulfillmentNo,
                'status_after' => $newStatus,
            ],
        ];
    }

    public function get_shortage_po_payload(int $requestId): array
    {
        $preview = $this->preview_split($requestId);
        if (!($preview['ok'] ?? false)) {
            return $preview;
        }

        $header = (array)($preview['header'] ?? []);
        $status = strtoupper((string)($header['status'] ?? 'DRAFT'));
        if (!in_array($status, ['APPROVED', 'PARTIAL_FULFILLED'], true)) {
            return ['ok' => false, 'message' => 'Generate PO shortage hanya dari status APPROVED/PARTIAL_FULFILLED.'];
        }

        $rows = (array)($preview['rows'] ?? []);
        $poLines = [];
        foreach ($rows as $row) {
            $shortageContent = round((float)($row['shortage_content'] ?? 0), 4);
            if ($shortageContent <= 0) {
                continue;
            }
            $cpb = round((float)($row['content_per_buy'] ?? 1), 6);
            if ($cpb <= 0) {
                $cpb = 1;
            }
            $poLines[] = [
                'line_kind' => (string)($row['line_kind'] ?? 'ITEM'),
                'item_id' => (int)($row['item_id'] ?? 0) > 0 ? (int)$row['item_id'] : null,
                'material_id' => (int)($row['material_id'] ?? 0) > 0 ? (int)$row['material_id'] : null,
                'line_description' => 'Auto shortage dari SR ' . (string)($header['sr_no'] ?? ''),
                'brand_name' => null,
                'qty_buy' => round($shortageContent / max($cpb, 0.000001), 4),
                'buy_uom_id' => (int)($row['buy_uom_id'] ?? 0),
                'content_per_buy' => $cpb,
                'qty_content' => $shortageContent,
                'content_uom_id' => (int)($row['content_uom_id'] ?? 0),
                'conversion_factor_to_content' => 1,
                'unit_price' => 0,
                'discount_percent' => 0,
                'tax_percent' => 0,
                'profile_key' => (string)($row['profile_key'] ?? ''),
                'snapshot_item_name' => (string)($row['profile_name'] ?? ''),
                'snapshot_material_name' => (string)($row['profile_name'] ?? ''),
                'snapshot_brand_name' => null,
                'snapshot_line_description' => 'Auto shortage dari SR ' . (string)($header['sr_no'] ?? ''),
                'snapshot_buy_uom_code' => (string)($row['profile_buy_uom_code'] ?? ''),
                'snapshot_content_uom_code' => (string)($row['profile_content_uom_code'] ?? ''),
            ];
        }

        if (empty($poLines)) {
            return ['ok' => false, 'message' => 'Tidak ada shortage untuk dibuatkan draft PO.'];
        }

        return [
            'ok' => true,
            'header' => $header,
            'lines' => $poLines,
        ];
    }

    public function find_inventory_purchase_type_id(): ?int
    {
        if (!$this->db->table_exists('mst_purchase_type')) {
            return null;
        }

        $row = $this->db->query(
            "SELECT id FROM mst_purchase_type WHERE is_active = 1 AND UPPER(TRIM(type_code)) IN ('INV_STOK','INV_BAR','INV_KITCHEN') ORDER BY FIELD(UPPER(TRIM(type_code)), 'INV_STOK','INV_BAR','INV_KITCHEN'), id ASC LIMIT 1"
        )->row_array();

        return $row ? (int)($row['id'] ?? 0) : null;
    }

    public function link_store_request_po(int $requestId, int $purchaseOrderId, string $linkType, string $notes, int $userId): void
    {
        if (!$this->db->table_exists('pur_store_request_po_link')) {
            return;
        }
        if ($requestId <= 0 || $purchaseOrderId <= 0) {
            return;
        }
        $linkType = strtoupper(trim($linkType));
        if (!in_array($linkType, ['SHORTAGE', 'MANUAL'], true)) {
            $linkType = 'SHORTAGE';
        }
        $this->db->insert('pur_store_request_po_link', [
            'store_request_id' => $requestId,
            'purchase_order_id' => $purchaseOrderId,
            'link_type' => $linkType,
            'notes' => $this->nullable_string($notes),
            'created_by' => $userId > 0 ? $userId : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function normalize_store_request_lines(array $lines): array
    {
        $normalizedLines = [];
        $lineNo = 0;

        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $lineNo++;
            $lineKind = strtoupper(trim((string)($line['line_kind'] ?? '')));
            $itemId = (int)($line['item_id'] ?? 0);
            $materialId = (int)($line['material_id'] ?? 0);
            $profileKey = trim((string)($line['profile_key'] ?? ''));
            $buyUomId = (int)($line['buy_uom_id'] ?? 0);
            $contentUomId = (int)($line['content_uom_id'] ?? 0);
            $contentPerBuy = round((float)($line['profile_content_per_buy'] ?? 0), 6);
            $qtyBuyRequested = round((float)($line['qty_buy_requested'] ?? 0), 4);
            $qtyContentRequested = round((float)($line['qty_content_requested'] ?? 0), 4);

            if ($lineKind === '') {
                $lineKind = $materialId > 0 ? 'MATERIAL' : 'ITEM';
            }
            if (!in_array($lineKind, ['ITEM', 'MATERIAL'], true)) {
                return ['ok' => false, 'message' => 'Line #' . $lineNo . ' line_kind tidak valid.'];
            }
            if ($lineKind === 'ITEM' && $itemId <= 0) {
                return ['ok' => false, 'message' => 'Line #' . $lineNo . ' wajib pilih item untuk line ITEM.'];
            }
            if ($lineKind === 'MATERIAL' && $materialId <= 0) {
                return ['ok' => false, 'message' => 'Line #' . $lineNo . ' wajib pilih material untuk line MATERIAL.'];
            }
            if ($profileKey === '' || $buyUomId <= 0 || $contentUomId <= 0) {
                return ['ok' => false, 'message' => 'Line #' . $lineNo . ' profile/UOM belum lengkap.'];
            }
            if ($contentPerBuy <= 0) {
                $contentPerBuy = 1;
            }
            if ($qtyBuyRequested <= 0 && $qtyContentRequested <= 0) {
                return ['ok' => false, 'message' => 'Line #' . $lineNo . ' qty request harus diisi.'];
            }
            if ($qtyContentRequested <= 0 && $qtyBuyRequested > 0) {
                $qtyContentRequested = round($qtyBuyRequested * $contentPerBuy, 4);
            }
            if ($qtyBuyRequested <= 0 && $qtyContentRequested > 0) {
                $qtyBuyRequested = round($qtyContentRequested / max($contentPerBuy, 0.000001), 4);
            }

            $normalizedLines[] = [
                'line_no' => $lineNo,
                'line_kind' => $lineKind,
                'item_id' => $itemId > 0 ? $itemId : null,
                'material_id' => $materialId > 0 ? $materialId : null,
                'profile_key' => substr($profileKey, 0, 64),
                'profile_name' => $this->nullable_string($line['profile_name'] ?? null),
                'profile_brand' => $this->nullable_string($line['profile_brand'] ?? null),
                'profile_description' => $this->nullable_string($line['profile_description'] ?? null),
                'profile_expired_date' => $this->normalize_date((string)($line['profile_expired_date'] ?? '')),
                'buy_uom_id' => $buyUomId,
                'content_uom_id' => $contentUomId,
                'profile_content_per_buy' => $contentPerBuy,
                'profile_buy_uom_code' => $this->nullable_string($line['profile_buy_uom_code'] ?? null),
                'profile_content_uom_code' => $this->nullable_string($line['profile_content_uom_code'] ?? null),
                'qty_buy_requested' => $qtyBuyRequested,
                'qty_content_requested' => $qtyContentRequested,
                'qty_buy_approved' => 0,
                'qty_content_approved' => 0,
                'qty_buy_fulfilled' => 0,
                'qty_content_fulfilled' => 0,
                'line_status' => 'OPEN',
                'notes' => $this->nullable_string($line['notes'] ?? null),
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }

        return ['ok' => true, 'lines' => $normalizedLines];
    }

    private function normalize_division_request_lines(array $lines): array
    {
        $normalizedLines = [];
        $lineNo = 0;
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $lineNo++;
            $lineKind = strtoupper(trim((string)($line['line_kind'] ?? '')));
            $itemId = (int)($line['item_id'] ?? 0);
            $materialId = (int)($line['material_id'] ?? 0);
            $profileKey = trim((string)($line['profile_key'] ?? ''));
            $buyUomId = (int)($line['buy_uom_id'] ?? 0);
            $contentUomId = (int)($line['content_uom_id'] ?? 0);
            $contentPerBuy = round((float)($line['profile_content_per_buy'] ?? 0), 6);
            $qtyBuyRequested = round((float)($line['qty_buy_requested'] ?? 0), 4);
            $qtyContentRequested = round((float)($line['qty_content_requested'] ?? 0), 4);

            if ($lineKind === '') {
                $lineKind = $materialId > 0 ? 'MATERIAL' : 'ITEM';
            }
            if (!in_array($lineKind, ['ITEM', 'MATERIAL'], true)) {
                return ['ok' => false, 'message' => 'Line #' . $lineNo . ' jenis line tidak valid.'];
            }
            if ($lineKind === 'ITEM' && $itemId <= 0) {
                return ['ok' => false, 'message' => 'Line #' . $lineNo . ' wajib item.'];
            }
            if ($lineKind === 'MATERIAL' && $materialId <= 0) {
                return ['ok' => false, 'message' => 'Line #' . $lineNo . ' wajib material.'];
            }
            if ($profileKey === '' || $buyUomId <= 0 || $contentUomId <= 0) {
                return ['ok' => false, 'message' => 'Line #' . $lineNo . ' profile/UOM belum lengkap.'];
            }
            if ($contentPerBuy <= 0) {
                $contentPerBuy = 1;
            }
            if ($qtyContentRequested <= 0 && $qtyBuyRequested > 0) {
                $qtyContentRequested = round($qtyBuyRequested * $contentPerBuy, 4);
            }
            if ($qtyBuyRequested <= 0 && $qtyContentRequested > 0) {
                $qtyBuyRequested = round($qtyContentRequested / max($contentPerBuy, 0.000001), 4);
            }
            if ($qtyBuyRequested <= 0 || $qtyContentRequested <= 0) {
                return ['ok' => false, 'message' => 'Line #' . $lineNo . ' qty tidak valid.'];
            }

            $normalizedLines[] = [
                'line_kind' => $lineKind,
                'item_id' => $itemId > 0 ? $itemId : null,
                'material_id' => $materialId > 0 ? $materialId : null,
                'profile_key' => substr($profileKey, 0, 64),
                'profile_name' => $this->nullable_string($line['profile_name'] ?? null),
                'profile_brand' => $this->nullable_string($line['profile_brand'] ?? null),
                'profile_description' => $this->nullable_string($line['profile_description'] ?? null),
                'profile_expired_date' => $this->normalize_date((string)($line['profile_expired_date'] ?? '')),
                'buy_uom_id' => $buyUomId,
                'content_uom_id' => $contentUomId,
                'profile_content_per_buy' => $contentPerBuy,
                'profile_buy_uom_code' => $this->nullable_string($line['profile_buy_uom_code'] ?? null),
                'profile_content_uom_code' => $this->nullable_string($line['profile_content_uom_code'] ?? null),
                'qty_buy_requested' => $qtyBuyRequested,
                'qty_content_requested' => $qtyContentRequested,
                'notes' => $this->nullable_string($line['notes'] ?? null),
            ];
        }

        return ['ok' => true, 'lines' => $normalizedLines];
    }

    private function apply_store_request_filters(array $filters, string $alias): void
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? '')));
        $divisionId = (int)($filters['division_id'] ?? 0);
        $destination = $this->normalize_destination((string)($filters['destination_type'] ?? ''));
        $dateStart = $this->normalize_date((string)($filters['date_start'] ?? ''));
        $dateEnd = $this->normalize_date((string)($filters['date_end'] ?? ''));

        if ($q !== '') {
            $this->db->group_start()
                ->like($alias . '.sr_no', $q)
                ->or_like($alias . '.notes', $q)
                ->group_end();
        }
        if ($status !== '' && $status !== 'ALL') {
            $this->db->where($alias . '.status', $status);
        }
        if ($divisionId > 0) {
            $this->db->where($alias . '.request_division_id', $divisionId);
        }
        if ($destination !== null) {
            $this->db->where($alias . '.destination_type', $destination);
        }
        if ($dateStart !== null) {
            $this->db->where($alias . '.request_date >=', $dateStart);
        }
        if ($dateEnd !== null) {
            $this->db->where($alias . '.request_date <=', $dateEnd);
        }
    }

    private function resolve_division_name_column(): string
    {
        $columns = [];
        try {
            $rows = $this->db->query('SHOW COLUMNS FROM mst_operational_division')->result_array();
            foreach ($rows as $row) {
                $field = strtolower((string)($row['Field'] ?? ''));
                if ($field !== '') {
                    $columns[$field] = true;
                }
            }
        } catch (Throwable $e) {
            $columns = [];
        }

        if (isset($columns['name'])) {
            return 'd.name';
        }
        if (isset($columns['division_name'])) {
            return 'd.division_name';
        }
        if (isset($columns['division'])) {
            return 'd.division';
        }
        return 'd.id';
    }

    private function generate_store_request_no(string $requestDate): string
    {
        $dateToken = date('Ymd', strtotime($requestDate));
        $prefix = 'SR' . $dateToken;
        do {
            $seq = str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $srNo = $prefix . $seq;
            $exists = (int)$this->db->where('sr_no', $srNo)->count_all_results('pur_store_request');
        } while ($exists > 0);
        return $srNo;
    }

    private function generate_fulfillment_no(string $fulfillmentDate): string
    {
        $dateToken = date('Ymd', strtotime($fulfillmentDate));
        $prefix = 'SRF' . $dateToken;
        do {
            $seq = str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $no = $prefix . $seq;
            $exists = (int)$this->db->where('fulfillment_no', $no)->count_all_results('pur_store_request_fulfillment');
        } while ($exists > 0);
        return $no;
    }

    private function generate_division_request_no(string $requestDate): string
    {
        $dateToken = date('Ymd', strtotime($requestDate));
        $prefix = 'DREQ' . $dateToken;
        do {
            $seq = str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $no = $prefix . $seq;
            $exists = (int)$this->db->where('request_no', $no)->count_all_results('pur_division_request');
        } while ($exists > 0);
        return $no;
    }

    private function infer_destination_from_division(int $divisionId): string
    {
        if ($divisionId <= 0) {
            return 'OTHER';
        }
        $row = $this->db->query('SELECT code, name FROM mst_operational_division WHERE id = ? LIMIT 1', [$divisionId])->row_array();
        $code = strtoupper(trim((string)($row['code'] ?? '')));
        $name = strtoupper(trim((string)($row['name'] ?? '')));
        $text = $code . ' ' . $name;
        if (strpos($text, 'BAR') !== false) {
            return 'BAR';
        }
        if (strpos($text, 'KITCHEN') !== false || strpos($text, 'DAPUR') !== false) {
            return 'KITCHEN';
        }
        if (strpos($text, 'OFFICE') !== false) {
            return 'OFFICE';
        }
        return 'OTHER';
    }

    private function get_warehouse_available_content(?int $itemId, ?int $materialId, ?int $buyUomId, ?int $contentUomId, string $profileKey): float
    {
        if (!$this->db->table_exists('inv_warehouse_stock_balance')) {
            return 0.0;
        }
        if (
            (($itemId === null || $itemId <= 0) && ($materialId === null || $materialId <= 0))
            || $buyUomId === null || $buyUomId <= 0
            || $contentUomId === null || $contentUomId <= 0
            || trim($profileKey) === ''
        ) {
            return 0.0;
        }
        $sql = 'SELECT qty_content_balance
                FROM inv_warehouse_stock_balance
                WHERE buy_uom_id = ?
                  AND content_uom_id = ?
                  AND profile_key <=> ?';
        $params = [$buyUomId, $contentUomId, $profileKey];

        if ($materialId !== null && $materialId > 0) {
            $sql .= ' AND material_id = ?';
            $params[] = $materialId;
        } else {
            $sql .= ' AND item_id = ?';
            $params[] = $itemId;
        }
        $sql .= ' LIMIT 1';

        $row = $this->db->query($sql, $params)->row_array();
        return round((float)($row['qty_content_balance'] ?? 0), 4);
    }

    private function normalize_destination(string $destination): ?string
    {
        $destination = strtoupper(trim($destination));
        $allowed = ['BAR', 'KITCHEN', 'BAR_EVENT', 'KITCHEN_EVENT', 'OFFICE', 'OTHER'];
        if (!in_array($destination, $allowed, true)) {
            return null;
        }
        return $destination;
    }

    private function normalize_date(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d', $ts);
    }

    private function nullable_string($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = trim((string)$value);
        return $v === '' ? null : $v;
    }

    private function nullable_int($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $int = (int)$value;
        return $int > 0 ? $int : null;
    }
}
