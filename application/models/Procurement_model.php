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

    private function has_division_request_request_uom_mode_column(): bool
    {
        return $this->db->field_exists('request_uom_mode', 'pur_division_request_line');
    }

    private function has_division_request_vendor_column(): bool
    {
        return $this->db->field_exists('vendor_id', 'pur_division_request_line');
    }

    private function normalize_division_request_uom_mode($value): string
    {
        return strtoupper(trim((string)$value)) === 'CONTENT' ? 'CONTENT' : 'BUY';
    }
    private function normalize_expiry_policy($value): string
    {
        $policy = strtoupper(trim((string)$value));
        if (in_array($policy, ['NONE', 'EXACT_DATE', 'MIN_REMAINING_DAYS'], true)) {
            return $policy;
        }
        return 'NONE';
    }

    private function normalize_min_remaining_days($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $days = (int)$value;
        return $days > 0 ? $days : null;
    }

    private function extract_expiry_requirement(array $source, string $legacyDateKey = 'profile_expired_date'): array
    {
        $requiredDate = $this->normalize_date((string)($source['required_expiry_date'] ?? ($source[$legacyDateKey] ?? '')));
        $minRemainingDays = $this->normalize_min_remaining_days($source['min_remaining_days'] ?? null);
        $policy = $this->normalize_expiry_policy($source['expiry_policy'] ?? '');

        if ($policy === 'NONE') {
            if ($requiredDate !== null) {
                $policy = 'EXACT_DATE';
            } elseif ($minRemainingDays !== null) {
                $policy = 'MIN_REMAINING_DAYS';
            }
        }

        if ($policy !== 'MIN_REMAINING_DAYS') {
            $minRemainingDays = null;
        }
        if ($policy === 'MIN_REMAINING_DAYS' && $requiredDate === null) {
            $requiredDate = null;
        }

        return [
            'expiry_policy' => $policy,
            'required_expiry_date' => $requiredDate,
            'min_remaining_days' => $minRemainingDays,
        ];
    }

    private function append_expiry_requirement_columns(string $table, array &$target, array $source, string $legacyDateKey = 'profile_expired_date'): void
    {
        if (!$this->db->table_exists($table)) {
            return;
        }

        $expiry = $this->extract_expiry_requirement($source, $legacyDateKey);
        if ($this->db->field_exists('expiry_policy', $table)) {
            $target['expiry_policy'] = $expiry['expiry_policy'];
        }
        if ($this->db->field_exists('required_expiry_date', $table)) {
            $target['required_expiry_date'] = $expiry['required_expiry_date'];
        }
        if ($this->db->field_exists('min_remaining_days', $table)) {
            $target['min_remaining_days'] = $expiry['min_remaining_days'];
        }
    }

    public function has_store_request_schema(): bool
    {
        return $this->db->table_exists('pur_store_request')
            && $this->db->table_exists('pur_store_request_line');
    }

    private function purchaseCatalogVendorTableExists(): bool
    {
        return $this->db->table_exists('mst_purchase_catalog_vendor')
            && $this->db->field_exists('catalog_id', 'mst_purchase_catalog_vendor')
            && $this->db->field_exists('vendor_id', 'mst_purchase_catalog_vendor');
    }

    private function latestCatalogVendorPriceSubquerySql(): string
    {
        return "
            SELECT
                src.catalog_id,
                src.vendor_id,
                src.standard_price,
                src.last_unit_price,
                src.last_purchase_date
            FROM mst_purchase_catalog_vendor src
            INNER JOIN (
                SELECT
                    catalog_id,
                    MAX(CONCAT(
                        COALESCE(DATE_FORMAT(last_purchase_date, '%Y%m%d'), '00000000'),
                        LPAD(CAST(id AS CHAR), 20, '0')
                    )) AS pick_key
                FROM mst_purchase_catalog_vendor
                WHERE COALESCE(is_active, 1) = 1
                GROUP BY catalog_id
            ) picked
                ON picked.catalog_id = src.catalog_id
               AND CONCAT(
                    COALESCE(DATE_FORMAT(src.last_purchase_date, '%Y%m%d'), '00000000'),
                    LPAD(CAST(src.id AS CHAR), 20, '0')
               ) = picked.pick_key
            WHERE COALESCE(src.is_active, 1) = 1
        ";
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

    public function build_destination_guard_map(array $divisionRows): array
    {
        $map = [];
        foreach ($divisionRows as $row) {
            $divisionId = (int)($row['id'] ?? 0);
            if ($divisionId <= 0) {
                continue;
            }
            $map[$divisionId] = $this->allowed_destinations_for_division($divisionId);
        }
        return $map;
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
            'req_value_total' => 0.0,
            'fulfilled_value_total' => 0.0,
            'pending_fulfillment_count' => 0,
            'pending_fulfillment_value_total' => 0.0,
        ];

        if (!$this->has_store_request_schema()) {
            return $summary;
        }

        $costAggSql = $this->build_store_request_line_cost_agg_sql();
        $this->db
            ->select('COUNT(*) AS total', false)
            ->select("SUM(CASE WHEN sr.status = 'DRAFT' THEN 1 ELSE 0 END) AS draft", false)
            ->select("SUM(CASE WHEN sr.status = 'SUBMITTED' THEN 1 ELSE 0 END) AS submitted", false)
            ->select("SUM(CASE WHEN sr.status IN ('APPROVED','PARTIAL_FULFILLED') THEN 1 ELSE 0 END) AS approved", false)
            ->select("SUM(CASE WHEN sr.status = 'REJECTED' THEN 1 ELSE 0 END) AS rejected", false)
            ->select("SUM(CASE WHEN sr.status = 'FULFILLED' THEN 1 ELSE 0 END) AS fulfilled", false)
            ->select("SUM(CASE WHEN sr.status = 'VOID' THEN 1 ELSE 0 END) AS void", false)
            ->select('COALESCE(SUM(ca.req_total_value), 0) AS req_value_total', false)
            ->select('COALESCE(SUM(ca.fulfilled_total_value), 0) AS fulfilled_value_total', false)
            ->select("SUM(CASE WHEN sr.status IN ('DRAFT','SUBMITTED','APPROVED','PARTIAL_FULFILLED') THEN 1 ELSE 0 END) AS pending_fulfillment_count", false)
            ->select("COALESCE(SUM(CASE WHEN sr.status IN ('DRAFT','SUBMITTED','APPROVED','PARTIAL_FULFILLED') THEN ca.req_total_value ELSE 0 END), 0) AS pending_fulfillment_value_total", false)
            ->from('pur_store_request sr')
            ->join('(' . $costAggSql . ') ca', 'ca.store_request_id = sr.id', 'left', false);

        $this->apply_store_request_filters($filters, 'sr');
        $row = $this->db->get()->row_array();
        if (!$row) {
            return $summary;
        }

        foreach (['total', 'draft', 'submitted', 'approved', 'rejected', 'fulfilled', 'void', 'pending_fulfillment_count'] as $key) {
            $summary[$key] = (int)($row[$key] ?? 0);
        }
        $summary['req_value_total'] = round((float)($row['req_value_total'] ?? 0), 2);
        $summary['fulfilled_value_total'] = round((float)($row['fulfilled_value_total'] ?? 0), 2);
        $summary['pending_fulfillment_value_total'] = round((float)($row['pending_fulfillment_value_total'] ?? 0), 2);

        return $summary;
    }

    public function get_store_request_line_summary(array $filters): array
    {
        $summary = [
            'total_lines' => 0,
            'req_buy_total' => 0.0,
            'fulfilled_buy_total' => 0.0,
            'req_value_total' => 0.0,
            'fulfilled_value_total' => 0.0,
        ];

        if (!$this->has_store_request_schema()) {
            return $summary;
        }

        $lineCostSql = $this->build_store_request_line_cost_sql();
        $this->db
            ->select('COUNT(*) AS total_lines', false)
            ->select('COALESCE(SUM(ln.qty_buy_requested), 0) AS req_buy_total', false)
            ->select('COALESCE(SUM(ln.qty_buy_fulfilled), 0) AS fulfilled_buy_total', false)
            ->select('COALESCE(SUM(lc.req_total_value), 0) AS req_value_total', false)
            ->select('COALESCE(SUM(lc.fulfilled_total_value), 0) AS fulfilled_value_total', false)
            ->from('pur_store_request_line ln')
            ->join('pur_store_request sr', 'sr.id = ln.store_request_id', 'inner')
            ->join('(' . $lineCostSql . ') lc', 'lc.line_id = ln.id', 'left', false);

        $this->apply_store_request_filters($filters, 'sr');

        $row = $this->db->get()->row_array();
        if (!$row) {
            return $summary;
        }

        $summary['total_lines'] = (int)($row['total_lines'] ?? 0);
        $summary['req_buy_total'] = round((float)($row['req_buy_total'] ?? 0), 4);
        $summary['fulfilled_buy_total'] = round((float)($row['fulfilled_buy_total'] ?? 0), 4);
        $summary['req_value_total'] = round((float)($row['req_value_total'] ?? 0), 2);
        $summary['fulfilled_value_total'] = round((float)($row['fulfilled_value_total'] ?? 0), 2);

        return $summary;
    }

    public function list_store_requests(array $filters, int $limit, int $offset): array
    {
        if (!$this->has_store_request_schema()) {
            return [];
        }

        $lineAggSql = $this->build_store_request_line_agg_sql();
        $divisionNameCol = $this->resolve_division_name_column();

        $this->db
            ->select('sr.id, sr.sr_no, sr.request_date, sr.needed_date, sr.request_division_id, sr.destination_type, sr.status, sr.notes, sr.created_at')
            ->select($divisionNameCol . ' AS division_name', false)
            ->select('u.username AS created_by_username')
            ->select('COALESCE(la.line_count, 0) AS line_count', false)
            ->select('COALESCE(la.req_buy_total, 0) AS req_buy_total', false)
            ->select('COALESCE(la.req_content_total, 0) AS req_content_total', false)
            ->select('COALESCE(la.fulfilled_buy_total, 0) AS fulfilled_buy_total', false)
            ->select('COALESCE(la.fulfilled_content_total, 0) AS fulfilled_content_total', false)
            ->select('COALESCE(la.req_total_value, 0) AS req_total_value', false)
            ->select('COALESCE(la.fulfilled_total_value, 0) AS fulfilled_total_value', false)
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

    public function list_store_request_lines(array $filters, int $limit, int $offset): array
    {
        if (!$this->has_store_request_schema()) {
            return [];
        }

        $divisionNameCol = $this->resolve_division_name_column();
        $lineCostSql = $this->build_store_request_line_cost_sql();
        $this->db
            ->select('ln.id AS line_id, ln.line_no, ln.line_kind, ln.item_id, ln.material_id')
            ->select("CASE
                WHEN COALESCE(ln.material_id, 0) > 0 THEN 'MATERIAL'
                WHEN UPPER(TRIM(COALESCE(c.line_kind, ''))) IN ('MATERIAL','ITEM') THEN UPPER(TRIM(c.line_kind))
                WHEN UPPER(TRIM(COALESCE(ln.line_kind, ''))) IN ('MATERIAL','ITEM') THEN UPPER(TRIM(ln.line_kind))
                ELSE 'ITEM'
            END AS effective_line_kind", false)
            ->select('ln.profile_key, ln.profile_name, ln.profile_brand, ln.profile_description')
            ->select('ln.profile_buy_uom_code, ln.profile_content_uom_code')
            ->select('ln.qty_buy_requested, ln.qty_content_requested, ln.qty_buy_fulfilled, ln.qty_content_fulfilled')
            ->select('COALESCE(lc.unit_cost_ref, 0) AS unit_cost_ref', false)
            ->select('COALESCE(lc.req_total_value, 0) AS req_total_value', false)
            ->select('COALESCE(lc.fulfilled_total_value, 0) AS fulfilled_total_value', false)
            ->select('sr.id AS store_request_id, sr.sr_no, sr.request_date, sr.needed_date, sr.destination_type, sr.status')
            ->select($divisionNameCol . ' AS division_name', false)
            ->select('i.item_name, m.material_name')
            ->from('pur_store_request_line ln')
            ->join('pur_store_request sr', 'sr.id = ln.store_request_id', 'inner')
            ->join('mst_operational_division d', 'd.id = sr.request_division_id', 'left')
            ->join('mst_purchase_catalog c', 'c.profile_key = ln.profile_key', 'left')
            ->join('mst_item i', 'i.id = ln.item_id', 'left')
            ->join('mst_material m', 'm.id = ln.material_id', 'left')
            ->join('(' . $lineCostSql . ') lc', 'lc.line_id = ln.id', 'left', false);

        $this->apply_store_request_filters($filters, 'sr');

        $purchaseTypeSortSql = $this->build_store_request_purchase_type_sort_sql('sr.destination_type');

        return $this->db
            ->order_by($purchaseTypeSortSql, 'ASC', false)
            ->order_by('sr.request_date', 'DESC')
            ->order_by('sr.id', 'DESC')
            ->order_by('ln.line_no', 'ASC')
            ->limit($limit, max(0, $offset))
            ->get()
            ->result_array();
    }

    private function build_store_request_purchase_type_sort_sql(string $destinationExpr): string
    {
        $destinationMap = [
            'BAR' => $this->find_inventory_purchase_type_id('BAR'),
            'KITCHEN' => $this->find_inventory_purchase_type_id('KITCHEN'),
            'BAR_EVENT' => $this->find_inventory_purchase_type_id('BAR_EVENT'),
            'KITCHEN_EVENT' => $this->find_inventory_purchase_type_id('KITCHEN_EVENT'),
            'OFFICE' => $this->find_inventory_purchase_type_id('OFFICE'),
        ];
        $defaultId = $this->find_inventory_purchase_type_id(null);
        $defaultSort = $defaultId > 0 ? $defaultId : 999999;

        $cases = [];
        foreach ($destinationMap as $destination => $purchaseTypeId) {
            $sortId = (int)$purchaseTypeId;
            if ($sortId <= 0) {
                continue;
            }
            $cases[] = "WHEN UPPER(TRIM(COALESCE(" . $destinationExpr . ", ''))) = '" . $this->db->escape_str($destination) . "' THEN " . $sortId;
        }

        if (empty($cases)) {
            return (string)$defaultSort;
        }

        return '(CASE ' . implode(' ', $cases) . ' ELSE ' . $defaultSort . ' END)';
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
        $hasWhStockDomain = $this->db->field_exists('stock_domain', 'inv_warehouse_stock_balance');
        $hasCatalog = $this->db->table_exists('mst_purchase_catalog')
            && $this->db->field_exists('profile_key', 'mst_purchase_catalog')
            && $this->db->field_exists('last_purchase_date', 'mst_purchase_catalog');
        $hasCatalogVendorPrice = $hasCatalog && $this->purchaseCatalogVendorTableExists();
        $latestVendorPriceSql = $hasCatalogVendorPrice ? $this->latestCatalogVendorPriceSubquerySql() : '';
        $hasCatalogLineKind = $hasCatalog && $this->db->field_exists('line_kind', 'mst_purchase_catalog');
        $hasCatalogLastUnitPrice = $hasCatalog && $this->db->field_exists('last_unit_price', 'mst_purchase_catalog');
        $hasCatalogStandardPrice = $hasCatalog && $this->db->field_exists('standard_price', 'mst_purchase_catalog');

        $this->db
            ->select('s.item_id, ' . ($hasWhMaterial ? 'COALESCE(s.material_id, i.material_id) AS material_id' : 'i.material_id AS material_id') . ', s.buy_uom_id, s.content_uom_id, s.profile_key', false)
            ->select($hasWhStockDomain ? 's.stock_domain' : 'NULL AS stock_domain', false)
            ->select('s.profile_name, s.profile_brand, s.profile_description, s.profile_expired_date, s.profile_content_per_buy')
            ->select('s.profile_buy_uom_code, s.profile_content_uom_code, s.qty_buy_balance, s.qty_content_balance')
            ->select(($hasCatalogVendorPrice || $hasCatalogLastUnitPrice || $hasCatalogStandardPrice)
                ? 'COALESCE(' . ($hasCatalogVendorPrice ? 'cvp.last_unit_price, cvp.standard_price, ' : '') . ($hasCatalogLastUnitPrice ? 'c.last_unit_price' : 'NULL') . ', ' . ($hasCatalogStandardPrice ? 'c.standard_price' : 'NULL') . ', 0) AS last_unit_price'
                : '0 AS last_unit_price', false)
            ->select($hasCatalog ? 'COALESCE(' . ($hasCatalogVendorPrice ? 'cvp.last_purchase_date, ' : '') . 'c.last_purchase_date) AS last_purchase_date' : 'NULL AS last_purchase_date', false)
            ->select($hasCatalogLineKind ? 'c.line_kind AS catalog_line_kind' : 'NULL AS catalog_line_kind', false)
            ->select('i.item_code, i.item_name, ' . ($hasWhMaterial ? 'm.material_code, m.material_name' : 'NULL AS material_code, NULL AS material_name'), false)
            ->from('inv_warehouse_stock_balance s')
            ->join('mst_item i', 'i.id = s.item_id', 'left')
            ->where('COALESCE(s.qty_content_balance, 0) >', 0);

        if ($hasWhMaterial) {
            $this->db->join('mst_material m', 'm.id = s.material_id', 'left');
        }
        if ($hasCatalog) {
            $this->db->join('mst_purchase_catalog c', 'c.profile_key = s.profile_key', 'left');
            if ($hasCatalogVendorPrice) {
                $this->db->join('(' . $latestVendorPriceSql . ') cvp', 'cvp.catalog_id = c.id', 'left', false);
            }
        }

        if ($q !== '') {
            $this->db->group_start()
                ->like('s.profile_name', $q)
                ->or_like('s.profile_brand', $q)
                ->or_like('s.profile_description', $q)
                ->or_like('s.profile_key', $q)
                ->or_like('i.item_name', $q);
            if ($hasWhMaterial) {
                $this->db->or_like('m.material_name', $q);
            }
            $this->db->group_end();
        }

        $rows = $this->db
            ->order_by('s.qty_buy_balance', 'DESC')
            ->order_by('s.qty_content_balance', 'DESC')
            ->limit(max(1, min(100, $limit)))
            ->get()
            ->result_array();

        foreach ($rows as &$row) {
            $stockDomain = strtoupper(trim((string)($row['stock_domain'] ?? '')));
            $catalogLineKind = strtoupper(trim((string)($row['catalog_line_kind'] ?? '')));
            if ((int)($row['material_id'] ?? 0) > 0) {
                $row['line_kind'] = 'MATERIAL';
            } elseif (in_array($stockDomain, ['MATERIAL', 'ITEM'], true)) {
                $row['line_kind'] = $stockDomain;
            } elseif (in_array($catalogLineKind, ['MATERIAL', 'ITEM'], true)) {
                $row['line_kind'] = $catalogLineKind;
            } else {
                $row['line_kind'] = (int)($row['material_id'] ?? 0) > 0 ? 'MATERIAL' : 'ITEM';
            }
        }
        unset($row);

        $dedup = [];
        foreach ($rows as $row) {
            $profileKey = trim((string)($row['profile_key'] ?? ''));
            $key = $profileKey !== ''
                ? ('PK:' . $profileKey . '|B:' . (int)($row['buy_uom_id'] ?? 0) . '|C:' . (int)($row['content_uom_id'] ?? 0))
                : ('I:' . (int)($row['item_id'] ?? 0) . '|M:' . (int)($row['material_id'] ?? 0) . '|B:' . (int)($row['buy_uom_id'] ?? 0) . '|C:' . (int)($row['content_uom_id'] ?? 0));
            $score = strtoupper((string)($row['line_kind'] ?? 'ITEM')) === 'MATERIAL' ? 2 : 1;
            if (!isset($dedup[$key])) {
                $dedup[$key] = ['row' => $row, 'score' => $score];
                continue;
            }
            if ($score > $dedup[$key]['score']) {
                $dedup[$key] = ['row' => $row, 'score' => $score];
                continue;
            }
            if ($score === $dedup[$key]['score']) {
                if ($this->should_prefer_division_request_candidate($dedup[$key]['row'], $row, true)) {
                    $dedup[$key] = ['row' => $row, 'score' => $score];
                }
            }
        }

        $finalRows = [];
        foreach ($dedup as $bucket) {
            $finalRows[] = $bucket['row'];
        }
        return $finalRows;
    }

    public function search_division_request_candidates(string $q, int $limit = 20): array
    {
        $q = trim($q);
        $limit = max(1, min(100, $limit));
        $catalogLimit = max($limit, min(250, $limit * 5));

        if ($q === '') {
            return [
                'rows' => [],
                'source' => 'EMPTY',
                'allow_manual' => false,
            ];
        }

        $warehouseRows = $this->search_warehouse_profiles($q, $limit);
        if (!empty($warehouseRows)) {
            foreach ($warehouseRows as &$row) {
                $row['source_type'] = 'WAREHOUSE';
                $row['search_source'] = 'WAREHOUSE';
            }
            unset($row);

            return [
                'rows' => $warehouseRows,
                'source' => 'WAREHOUSE',
                'allow_manual' => false,
            ];
        }

        $this->load->model('Purchase_model');
        $catalogRows = $this->Purchase_model->search_catalog_profiles($q, 0, '', 0, 0, $catalogLimit);
        if (empty($catalogRows)) {
            $catalogRows = $this->Purchase_model->search_master_fallback($q, '', 0, 0, $catalogLimit);
        }

        $normalizedRows = $this->normalize_division_request_candidate_rows($catalogRows);

        return [
            'rows' => array_slice($normalizedRows, 0, $limit),
            'source' => empty($catalogRows) ? 'MANUAL' : 'CATALOG',
            'allow_manual' => empty($catalogRows),
        ];
    }

    public function list_division_requests(array $filters, int $limit = 50): array
    {
        if (!$this->has_division_request_schema()) {
            return [];
        }

        $q = trim((string)($filters['q'] ?? ''));
        $divisionId = (int)($filters['division_id'] ?? 0);
        $status = strtoupper(trim((string)($filters['status'] ?? '')));
        $dateField = strtoupper(trim((string)($filters['date_field'] ?? 'REQUEST_DATE')));
        $dateColumn = $dateField === 'NEEDED_DATE' ? 'r.needed_date' : 'r.request_date';
        $dateStart = $this->normalize_date((string)($filters['date_start'] ?? ''));
        $dateEnd = $this->normalize_date((string)($filters['date_end'] ?? ''));
        $allowedDivisionIds = array_values(array_unique(array_filter(array_map('intval', (array)($filters['allowed_division_ids'] ?? [])), static function ($value) {
            return $value > 0;
        })));
        $divisionNameCol = $this->resolve_division_name_column();

        $this->db
            ->select('r.id, r.request_no, r.request_date, r.needed_date, r.division_id, r.destination_type, r.status, r.notes, r.created_at')
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
        if (!empty($allowedDivisionIds)) {
            $this->db->where_in('r.division_id', $allowedDivisionIds);
        }
        if ($status !== '' && $status !== 'ALL') {
            $this->db->where('r.status', $status);
        }
        if ($dateStart !== null) {
            $this->db->where($dateColumn . ' >=', $dateStart);
        }
        if ($dateEnd !== null) {
            $this->db->where($dateColumn . ' <=', $dateEnd);
        }

        return $this->db
            ->order_by('r.request_date', 'DESC')
            ->order_by('r.id', 'DESC')
            ->limit(max(1, min(5000, $limit)))
            ->get()
            ->result_array();
    }

    public function list_division_request_line_rows(array $filters, int $limit = 50): array
    {
        if (!$this->has_division_request_schema()) {
            return [];
        }

        $q = trim((string)($filters['q'] ?? ''));
        $divisionId = (int)($filters['division_id'] ?? 0);
        $status = strtoupper(trim((string)($filters['status'] ?? '')));
        $dateField = strtoupper(trim((string)($filters['date_field'] ?? 'REQUEST_DATE')));
        $dateColumn = $dateField === 'NEEDED_DATE' ? 'r.needed_date' : 'r.request_date';
        $dateStart = $this->normalize_date((string)($filters['date_start'] ?? ''));
        $dateEnd = $this->normalize_date((string)($filters['date_end'] ?? ''));
        $allowedDivisionIds = array_values(array_unique(array_filter(array_map('intval', (array)($filters['allowed_division_ids'] ?? [])), static function ($value) {
            return $value > 0;
        })));
        $divisionNameCol = $this->resolve_division_name_column();
        $hasRequestUomMode = $this->has_division_request_request_uom_mode_column();

        $this->db
            ->select('r.id AS request_id, r.request_no, r.request_date, r.needed_date, r.division_id, r.destination_type, r.status')
            ->select($divisionNameCol . ' AS division_name', false)
            ->select('u.username AS created_by_username')
            ->select('l.id AS line_id, l.line_no, l.profile_name, l.line_kind, l.profile_buy_uom_code, l.profile_content_uom_code')
            ->select('l.qty_buy_requested, l.qty_content_requested, l.qty_content_available_snapshot, l.qty_content_to_sr, l.qty_content_to_po, l.notes AS line_notes')
            ->from('pur_division_request_line l')
            ->join('pur_division_request r', 'r.id = l.request_id')
            ->join('mst_operational_division d', 'd.id = r.division_id', 'left')
            ->join('auth_user u', 'u.id = r.created_by', 'left');

        if ($hasRequestUomMode) {
            $this->db->select('l.request_uom_mode');
        }

        if ($q !== '') {
            $this->db->group_start()
                ->like('r.request_no', $q)
                ->or_like('r.notes', $q)
                ->or_like('l.profile_name', $q)
                ->or_like('l.notes', $q)
                ->group_end();
        }
        if ($divisionId > 0) {
            $this->db->where('r.division_id', $divisionId);
        }
        if (!empty($allowedDivisionIds)) {
            $this->db->where_in('r.division_id', $allowedDivisionIds);
        }
        if ($status !== '' && $status !== 'ALL') {
            $this->db->where('r.status', $status);
        }
        if ($dateStart !== null) {
            $this->db->where($dateColumn . ' >=', $dateStart);
        }
        if ($dateEnd !== null) {
            $this->db->where($dateColumn . ' <=', $dateEnd);
        }

        return $this->db
            ->order_by('r.request_date', 'DESC')
            ->order_by('r.id', 'DESC')
            ->order_by('l.line_no', 'ASC')
            ->limit(max(1, min(5000, $limit)))
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

    public function get_division_request_detail(int $requestId): ?array
    {
        if (!$this->has_division_request_schema() || $requestId <= 0) {
            return null;
        }

        $divisionNameCol = $this->resolve_division_name_column();
        $header = $this->db
            ->select('r.id, r.request_no, r.request_date, r.needed_date, r.division_id, r.destination_type, r.status, r.notes, r.created_at, r.updated_at')
            ->select($divisionNameCol . ' AS division_name', false)
            ->select('u.username AS created_by_username')
            ->from('pur_division_request r')
            ->join('mst_operational_division d', 'd.id = r.division_id', 'left')
            ->join('auth_user u', 'u.id = r.created_by', 'left')
            ->where('r.id', $requestId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$header) {
            return null;
        }

        $this->db
            ->from('pur_division_request_line l')
            ->where('l.request_id', $requestId)
            ->order_by('l.line_no', 'ASC')
            ->select('l.*');
        if ($this->has_division_request_vendor_column() && $this->db->table_exists('mst_vendor')) {
            $this->db
                ->select('v.vendor_name')
                ->join('mst_vendor v', 'v.id = l.vendor_id', 'left');
        }
        $lines = $this->db->get()->result_array();

        $linksMap = $this->list_division_request_links_map([$requestId]);

        return [
            'header' => $header,
            'lines' => $lines,
            'links' => (array)($linksMap[$requestId] ?? []),
        ];
    }

    public function create_division_request(array $header, array $lines, int $userId, string $sourceIp = ''): array
    {
        if (!$this->has_division_request_schema()) {
            return ['ok' => false, 'message' => 'Schema PO/SR Divisi belum tersedia. Jalankan SQL terbaru dulu.'];
        }

        $requestDate = $this->normalize_date((string)($header['request_date'] ?? date('Y-m-d')));
        $neededDate = $this->normalize_date((string)($header['needed_date'] ?? ''));
        $divisionId = (int)($header['division_id'] ?? 0);
        $destinationType = $this->resolve_division_request_destination_type($divisionId, (string)($header['destination_type'] ?? ''));
        $notes = $this->nullable_string($header['notes'] ?? null);
        if ($requestDate === null || $divisionId <= 0 || $destinationType === null) {
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

        $requestNo = $this->generate_division_request_no($requestDate);
        $prepared = $this->prepare_division_request_routes($requestNo, $lineRows);
        $insertLines = (array)($prepared['stored_lines'] ?? []);

        $this->db->trans_begin();
        $this->db->insert('pur_division_request', [
            'request_no' => $requestNo,
            'request_date' => $requestDate,
            'needed_date' => $neededDate,
            'division_id' => $divisionId,
            'destination_type' => $destinationType,
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
            $this->append_expiry_requirement_columns('pur_division_request_line', $row, $row, 'profile_expired_date');
            $this->db->insert('pur_division_request_line', $row);
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal menyimpan pengajuan PO/SR divisi.'];
        }
        $this->db->trans_commit();

        return [
            'ok' => true,
            'message' => 'Pengajuan PO/SR divisi berhasil dibuat dan menunggu verifikasi purchase.',
            'data' => [
                'request_id' => $requestId,
                'request_no' => $requestNo,
            ],
        ];
    }

    public function update_division_request(int $requestId, array $header, array $lines, int $userId): array
    {
        if (!$this->has_division_request_schema()) {
            return ['ok' => false, 'message' => 'Schema PO/SR Divisi belum tersedia.'];
        }
        if ($requestId <= 0) {
            return ['ok' => false, 'message' => 'Request ID tidak valid.'];
        }

        $existing = $this->db
            ->from('pur_division_request')
            ->where('id', $requestId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$existing) {
            return ['ok' => false, 'message' => 'Pengajuan PO/SR divisi tidak ditemukan.'];
        }

        $status = strtoupper((string)($existing['status'] ?? 'SUBMITTED'));
        if (!in_array($status, ['SUBMITTED', 'REJECTED'], true)) {
            return ['ok' => false, 'message' => 'Hanya pengajuan SUBMITTED/REJECTED yang dapat diedit.'];
        }

        $linkCount = (int)$this->db
            ->where('request_id', $requestId)
            ->count_all_results('pur_division_request_link');
        if ($linkCount > 0) {
            return ['ok' => false, 'message' => 'Pengajuan yang sudah punya dokumen hasil tidak dapat diedit lagi.'];
        }

        $requestDate = $this->normalize_date((string)($header['request_date'] ?? (string)($existing['request_date'] ?? '')));
        $neededDate = $this->normalize_date((string)($header['needed_date'] ?? (string)($existing['needed_date'] ?? '')));
        $divisionId = (int)($header['division_id'] ?? (int)($existing['division_id'] ?? 0));
        $destinationType = $this->resolve_division_request_destination_type($divisionId, (string)($header['destination_type'] ?? (string)($existing['destination_type'] ?? '')));
        $notes = $this->nullable_string($header['notes'] ?? ($existing['notes'] ?? null));
        if ($requestDate === null || $divisionId <= 0 || $destinationType === null) {
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

        $prepared = $this->prepare_division_request_routes((string)($existing['request_no'] ?? ''), $lineRows);
        $insertLines = (array)($prepared['stored_lines'] ?? []);

        $this->db->trans_begin();

        $this->db
            ->where('id', $requestId)
            ->update('pur_division_request', [
                'request_date' => $requestDate,
                'needed_date' => $neededDate,
                'division_id' => $divisionId,
                'destination_type' => $destinationType,
                'status' => 'SUBMITTED',
                'notes' => $notes,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        $this->db->where('request_id', $requestId)->delete('pur_division_request_line');
        foreach ($insertLines as $row) {
            $row['request_id'] = $requestId;
            $this->append_expiry_requirement_columns('pur_division_request_line', $row, $row, 'profile_expired_date');
            $this->db->insert('pur_division_request_line', $row);
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal memperbarui pengajuan PO/SR divisi.'];
        }

        $this->db->trans_commit();

        return [
            'ok' => true,
            'message' => 'Pengajuan PO/SR divisi berhasil diperbarui.',
            'data' => [
                'request_id' => $requestId,
                'request_no' => (string)($existing['request_no'] ?? ''),
            ],
        ];
    }

    public function verify_division_request(int $requestId, array $header, array $lines, int $userId, string $sourceIp = ''): array
    {
        if (!$this->has_division_request_schema()) {
            return ['ok' => false, 'message' => 'Schema PO/SR Divisi belum tersedia.'];
        }
        if (!$this->has_store_request_schema()) {
            return ['ok' => false, 'message' => 'Schema Store Request belum tersedia.'];
        }
        if ($requestId <= 0) {
            return ['ok' => false, 'message' => 'Request ID tidak valid.'];
        }

        $existing = $this->db
            ->from('pur_division_request')
            ->where('id', $requestId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$existing) {
            return ['ok' => false, 'message' => 'Pengajuan PO/SR divisi tidak ditemukan.'];
        }

        $status = strtoupper((string)($existing['status'] ?? 'SUBMITTED'));
        if ($status !== 'SUBMITTED') {
            return ['ok' => false, 'message' => 'Hanya pengajuan status SUBMITTED yang dapat diverifikasi purchase.'];
        }

        $requestDate = $this->normalize_date((string)($header['request_date'] ?? (string)($existing['request_date'] ?? '')));
        $neededDate = $this->normalize_date((string)($header['needed_date'] ?? (string)($existing['needed_date'] ?? '')));
        $divisionId = (int)($header['division_id'] ?? (int)($existing['division_id'] ?? 0));
        $destinationType = $this->resolve_division_request_destination_type($divisionId, (string)($header['destination_type'] ?? (string)($existing['destination_type'] ?? '')));
        $notes = $this->nullable_string($header['notes'] ?? ($existing['notes'] ?? null));
        if ($requestDate === null || $divisionId <= 0 || $destinationType === null) {
            return ['ok' => false, 'message' => 'Header verifikasi belum lengkap (tanggal/divisi).'];
        }

        $normalized = $this->normalize_division_request_lines($lines);
        if (!($normalized['ok'] ?? false)) {
            return $normalized;
        }
        $lineRows = (array)($normalized['lines'] ?? []);
        if (empty($lineRows)) {
            return ['ok' => false, 'message' => 'Minimal 1 baris hasil verifikasi wajib diisi.'];
        }

        $prepared = $this->prepare_division_request_routes((string)($existing['request_no'] ?? ''), $lineRows);
        $insertLines = (array)($prepared['stored_lines'] ?? []);
        $srLines = (array)($prepared['sr_lines'] ?? []);
        $poLines = (array)($prepared['po_lines'] ?? []);
        if (empty($insertLines)) {
            return ['ok' => false, 'message' => 'Tidak ada line hasil verifikasi yang valid.'];
        }

        $this->load->model('Purchase_model');
        $purchaseTypeId = $this->find_inventory_purchase_type_id($destinationType);
        if (!empty($poLines) && ($purchaseTypeId === null || $purchaseTypeId <= 0)) {
            return ['ok' => false, 'message' => 'Purchase type inventory untuk draft PO belum tersedia.'];
        }

        $this->db->trans_begin();

        $this->db->where('request_id', $requestId)->delete('pur_division_request_link');
        $this->db->where('request_id', $requestId)->delete('pur_division_request_line');
        foreach ($insertLines as $row) {
            $row['request_id'] = $requestId;
            $this->db->insert('pur_division_request_line', $row);
        }

        $createdSr = null;
        if (!empty($srLines)) {
            $createSr = $this->create_store_request([
                'request_date' => $requestDate,
                'needed_date' => $neededDate,
                'request_division_id' => $divisionId,
                'destination_type' => $destinationType,
                'status' => 'SUBMITTED',
                'notes' => null,
            ], $srLines, $userId);
            if (!($createSr['ok'] ?? false)) {
                $this->db->trans_rollback();
                return ['ok' => false, 'message' => 'Gagal membuat SR hasil verifikasi: ' . (string)($createSr['message'] ?? 'error')];
            }

            $srId = (int)($createSr['id'] ?? 0);
            $approveSr = $this->apply_store_request_action($srId, 'APPROVE', '', $userId);
            if (!($approveSr['ok'] ?? false)) {
                $this->db->trans_rollback();
                return ['ok' => false, 'message' => 'Gagal approve SR hasil verifikasi: ' . (string)($approveSr['message'] ?? 'error')];
            }

            $createdSr = ['id' => $srId, 'no' => (string)($createSr['sr_no'] ?? '')];
            $this->db->insert('pur_division_request_link', [
                'request_id' => $requestId,
                'doc_type' => 'SR',
                'doc_id' => $srId,
                'created_at' => date('Y-m-d H:i:s'),
                'notes' => null,
            ]);
        }

        $createdPo = [];
        if (!empty($poLines)) {
            $poGroups = [];
            foreach ($poLines as $poLine) {
                $vendorId = (int)($poLine['vendor_id'] ?? 0);
                if ($vendorId <= 0) {
                    $this->db->trans_rollback();
                    return ['ok' => false, 'message' => 'Vendor wajib dipilih untuk setiap line yang masuk PO.'];
                }
                if (!isset($poGroups[$vendorId])) {
                    $poGroups[$vendorId] = [];
                }
                $linePayload = $poLine;
                unset($linePayload['source_line_no']);
                $poGroups[$vendorId][] = $linePayload;
            }

            foreach ($poGroups as $vendorId => $vendorLines) {
                $createPo = $this->Purchase_model->store_order_with_lines([
                    'request_date' => $requestDate,
                    'expected_date' => $neededDate ?: $requestDate,
                    'purchase_type_id' => (int)$purchaseTypeId,
                    'destination_type' => $destinationType,
                    'status' => 'DRAFT',
                    'destination_division_id' => $divisionId,
                    'vendor_id' => (int)$vendorId,
                    'external_ref_no' => (string)($existing['request_no'] ?? ''),
                    'notes' => null,
                ], $vendorLines, $userId, $sourceIp);
                if (!($createPo['ok'] ?? false)) {
                    $this->db->trans_rollback();
                    return ['ok' => false, 'message' => 'Gagal membuat draft PO hasil verifikasi: ' . (string)($createPo['message'] ?? 'error')];
                }

                $poData = (array)($createPo['data'] ?? []);
                $poId = (int)($poData['purchase_order_id'] ?? 0);
                $createdPo[] = [
                    'id' => $poId,
                    'no' => (string)($poData['po_no'] ?? ''),
                    'vendor_id' => (int)$vendorId,
                ];
                $this->db->insert('pur_division_request_link', [
                    'request_id' => $requestId,
                    'doc_type' => 'PO',
                    'doc_id' => $poId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'notes' => null,
                ]);
            }
        }

        $this->db
            ->where('id', $requestId)
            ->update('pur_division_request', [
                'request_date' => $requestDate,
                'needed_date' => $neededDate,
                'division_id' => $divisionId,
                'destination_type' => $destinationType,
                'status' => 'VERIFIED',
                'notes' => $notes,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal menyimpan hasil verifikasi purchase.'];
        }

        $this->db->trans_commit();

        return [
            'ok' => true,
            'message' => 'Verifikasi purchase berhasil disimpan dan dokumen final sudah dibuat.',
            'data' => [
                'request_id' => $requestId,
                'request_no' => (string)($existing['request_no'] ?? ''),
                'created_sr' => $createdSr,
                'created_po' => count($createdPo) === 1 ? $createdPo[0] : null,
                'created_po_list' => $createdPo,
            ],
        ];
    }

    public function apply_division_request_action(int $requestId, string $action, string $notes, int $userId): array
    {
        if (!$this->has_division_request_schema()) {
            return ['ok' => false, 'message' => 'Schema PO/SR Divisi belum tersedia.'];
        }
        if ($requestId <= 0) {
            return ['ok' => false, 'message' => 'Request ID tidak valid.'];
        }

        $action = strtoupper(trim($action));
        if (!in_array($action, ['REJECT', 'VOID'], true)) {
            return ['ok' => false, 'message' => 'Aksi pengajuan divisi tidak valid.'];
        }

        $header = $this->db
            ->from('pur_division_request')
            ->where('id', $requestId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$header) {
            return ['ok' => false, 'message' => 'Pengajuan PO/SR divisi tidak ditemukan.'];
        }

        $status = strtoupper((string)($header['status'] ?? 'SUBMITTED'));
        if ($action === 'REJECT' && $status !== 'SUBMITTED') {
            return ['ok' => false, 'message' => 'Hanya pengajuan SUBMITTED yang dapat direject.'];
        }
        if ($action === 'VOID' && !in_array($status, ['SUBMITTED', 'REJECTED'], true)) {
            return ['ok' => false, 'message' => 'Hanya pengajuan SUBMITTED/REJECTED yang dapat di-void.'];
        }

        $nextStatus = $action === 'REJECT' ? 'REJECTED' : 'VOID';
        $mergedNotes = $this->merge_division_request_notes((string)($header['notes'] ?? ''), $notes, $nextStatus);

        $this->db
            ->where('id', $requestId)
            ->update('pur_division_request', [
                'status' => $nextStatus,
                'notes' => $mergedNotes,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        if ((int)($this->db->error()['code'] ?? 0) !== 0) {
            return ['ok' => false, 'message' => 'Gagal memproses aksi pengajuan divisi.'];
        }

        return [
            'ok' => true,
            'message' => 'Aksi berhasil diproses. Status: ' . $nextStatus,
            'status' => $nextStatus,
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
        $initialStatus = strtoupper(trim((string)($header['status'] ?? 'DRAFT')));
        if (!in_array($initialStatus, ['DRAFT', 'SUBMITTED'], true)) {
            $initialStatus = 'DRAFT';
        }

        if ($requestDate === null || $divisionId <= 0 || $destinationType === null) {
            return [
                'ok' => false,
                'message' => 'Header belum lengkap: request_date, divisi, dan destination wajib valid.',
            ];
        }

        if (!$this->is_destination_allowed_for_division($divisionId, $destinationType)) {
            $allowed = $this->allowed_destinations_for_division($divisionId);
            return [
                'ok' => false,
                'message' => 'Tujuan tidak sesuai divisi. Pilihan yang diizinkan: ' . implode(', ', $allowed) . '.',
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
            'status' => $initialStatus,
            'notes' => $notes,
            'created_by' => $userId > 0 ? $userId : null,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $this->db->insert('pur_store_request', $headerData);
        $requestId = (int)$this->db->insert_id();

        foreach ($lineRows as $lineData) {
            $lineData['store_request_id'] = $requestId;
            $this->append_expiry_requirement_columns('pur_store_request_line', $lineData, $lineData, 'profile_expired_date');
            $this->db->insert('pur_store_request_line', $lineData);
        }

        if ($initialStatus === 'SUBMITTED' && $this->db->table_exists('pur_store_request_approval')) {
            $this->db->insert('pur_store_request_approval', [
                'store_request_id' => $requestId,
                'action' => 'SUBMIT',
                'actor_user_id' => $userId > 0 ? $userId : null,
                'actor_name_snapshot' => null,
                'notes' => 'Store Request disubmit saat pembuatan',
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
            'message' => $initialStatus === 'SUBMITTED'
                ? 'Store Request berhasil disimpan dan langsung SUBMITTED.'
                : 'Store Request berhasil disimpan sebagai DRAFT.',
            'id' => $requestId,
            'sr_no' => $srNo,
        ];
    }

    public function get_store_request_detail(int $requestId): ?array
    {
        if (!$this->has_store_request_schema() || $requestId <= 0) {
            return null;
        }

        $divisionNameSelect = 'NULL AS division_name';
        if ($this->db->field_exists('division_name', 'mst_operational_division')) {
            $divisionNameSelect = 'd.division_name AS division_name';
        } elseif ($this->db->field_exists('name', 'mst_operational_division')) {
            $divisionNameSelect = 'd.name AS division_name';
        }

        $header = $this->db
            ->select('sr.*')
            ->select($divisionNameSelect, false)
            ->from('pur_store_request sr')
            ->join('mst_operational_division d', 'd.id = sr.request_division_id', 'left')
            ->where('sr.id', $requestId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$header) {
            return null;
        }

        $lines = $this->db
            ->select('l.*')
            ->select('bu.code AS buy_uom_code, cu.code AS content_uom_code', false)
            ->from('pur_store_request_line l')
            ->join('mst_uom bu', 'bu.id = l.buy_uom_id', 'left')
            ->join('mst_uom cu', 'cu.id = l.content_uom_id', 'left')
            ->where('l.store_request_id', $requestId)
            ->order_by('line_no', 'ASC')
            ->get()
            ->result_array();

        $fulfillments = [];
        $movementRows = [];
        if ($this->db->table_exists('pur_store_request_fulfillment') && $this->db->table_exists('pur_store_request_fulfillment_line')) {
            $fulfillments = $this->db
                ->select('f.*')
                ->select('COUNT(fl.id) AS line_count', false)
                ->select('COALESCE(SUM(fl.qty_buy_posted), 0) AS qty_buy_total', false)
                ->select('COALESCE(SUM(fl.qty_content_posted), 0) AS qty_content_total', false)
                ->from('pur_store_request_fulfillment f')
                ->join('pur_store_request_fulfillment_line fl', 'fl.fulfillment_id = f.id', 'left')
                ->where('f.store_request_id', $requestId)
                ->group_by('f.id')
                ->order_by('f.fulfillment_date', 'ASC')
                ->order_by('f.id', 'ASC')
                ->get()
                ->result_array();

            $fulfillmentIds = array_values(array_filter(array_map(static function ($row) {
                return (int)($row['id'] ?? 0);
            }, $fulfillments), static function ($id) {
                return $id > 0;
            }));

            if (!empty($fulfillmentIds)) {
                $fulfillmentLineRows = $this->db
                    ->select('fl.*')
                    ->select('srl.line_no AS request_line_no', false)
                    ->select('bu.code AS buy_uom_code, cu.code AS content_uom_code', false)
                    ->from('pur_store_request_fulfillment_line fl')
                    ->join('pur_store_request_line srl', 'srl.id = fl.store_request_line_id', 'left')
                    ->join('mst_uom bu', 'bu.id = fl.buy_uom_id', 'left')
                    ->join('mst_uom cu', 'cu.id = fl.content_uom_id', 'left')
                    ->where_in('fl.fulfillment_id', $fulfillmentIds)
                    ->order_by('fl.fulfillment_id', 'ASC')
                    ->order_by('fl.id', 'ASC')
                    ->get()
                    ->result_array();

                $issueIds = array_values(array_filter(array_unique(array_map(static function ($row) {
                    return (int)($row['fifo_issue_id'] ?? 0);
                }, $fulfillmentLineRows)), static function ($id) {
                    return $id > 0;
                }));

                $allocationMap = [];
                if (
                    !empty($issueIds)
                    && $this->db->table_exists('inv_material_fifo_issue_line')
                    && $this->db->table_exists('inv_material_fifo_lot')
                ) {
                    $sourceBalanceBeforeSelect = $this->db->field_exists('source_balance_before', 'inv_material_fifo_issue_line')
                        ? 'il.source_balance_before'
                        : 'NULL AS source_balance_before';
                    $sourceBalanceAfterSelect = $this->db->field_exists('source_balance_after', 'inv_material_fifo_issue_line')
                        ? 'il.source_balance_after'
                        : 'NULL AS source_balance_after';
                    $targetBalanceBeforeSelect = $this->db->field_exists('target_balance_before', 'inv_material_fifo_issue_line')
                        ? 'il.target_balance_before'
                        : 'NULL AS target_balance_before';
                    $targetBalanceAfterSelect = $this->db->field_exists('target_balance_after', 'inv_material_fifo_issue_line')
                        ? 'il.target_balance_after'
                        : 'NULL AS target_balance_after';
                    $allocationRows = $this->db
                        ->select('il.issue_id, il.lot_id AS source_lot_id, il.target_lot_id, il.qty_out, il.unit_cost, il.total_cost')
                        ->select($sourceBalanceBeforeSelect . ', ' . $sourceBalanceAfterSelect . ', ' . $targetBalanceBeforeSelect . ', ' . $targetBalanceAfterSelect, false)
                        ->select('sl.lot_no AS source_lot_no, sl.receipt_date AS source_receipt_date, sl.expiry_date AS source_expiry_date', false)
                        ->select('tl.lot_no AS target_lot_no, tl.receipt_date AS target_receipt_date, tl.expiry_date AS target_expiry_date', false)
                        ->from('inv_material_fifo_issue_line il')
                        ->join('inv_material_fifo_lot sl', 'sl.id = il.lot_id', 'left')
                        ->join('inv_material_fifo_lot tl', 'tl.id = il.target_lot_id', 'left')
                        ->where_in('il.issue_id', $issueIds)
                        ->order_by('il.issue_id', 'ASC')
                        ->order_by('il.id', 'ASC')
                        ->get()
                        ->result_array();
                    foreach ($allocationRows as $allocationRow) {
                        $allocationMap[(int)($allocationRow['issue_id'] ?? 0)][] = $allocationRow;
                    }
                }

                $fulfillmentLineMap = [];
                foreach ($fulfillmentLineRows as $fulfillmentLineRow) {
                    $fulfillmentLineRow['lot_rows'] = $allocationMap[(int)($fulfillmentLineRow['fifo_issue_id'] ?? 0)] ?? [];
                    $fulfillmentLineMap[(int)($fulfillmentLineRow['fulfillment_id'] ?? 0)][] = $fulfillmentLineRow;
                }

                foreach ($fulfillments as &$fulfillmentRow) {
                    $fulfillmentRow['lines'] = $fulfillmentLineMap[(int)($fulfillmentRow['id'] ?? 0)] ?? [];
                }
                unset($fulfillmentRow);
            }

            if (!empty($fulfillmentIds) && $this->db->table_exists('inv_stock_movement_log')) {
                $movementRows = $this->db
                    ->select('id, ref_id AS fulfillment_id, movement_scope, movement_type, movement_date, movement_no, division_id, destination_type, item_id, material_id, profile_name, profile_brand, profile_description, profile_buy_uom_code, profile_content_uom_code, qty_buy_delta, qty_content_delta, qty_buy_after, qty_content_after, unit_cost, notes, created_at')
                    ->from('inv_stock_movement_log')
                    ->where('ref_table', 'pur_store_request_fulfillment')
                    ->where_in('ref_id', $fulfillmentIds)
                    ->order_by('movement_date', 'ASC')
                    ->order_by('id', 'ASC')
                    ->get()
                    ->result_array();

                $movementMap = [];
                foreach ($movementRows as $movementRow) {
                    $movementMap[(int)($movementRow['fulfillment_id'] ?? 0)][] = $movementRow;
                }

                foreach ($fulfillments as &$fulfillmentRow) {
                    $fulfillmentRow['movement_rows'] = $movementMap[(int)($fulfillmentRow['id'] ?? 0)] ?? [];
                }
                unset($fulfillmentRow);
            }
        }

        return [
            'header' => $header,
            'lines' => $lines,
            'fulfillments' => $fulfillments,
            'movement_rows' => $movementRows,
        ];
    }

    public function update_store_request(int $requestId, array $header, array $lines, int $userId): array
    {
        if (!$this->has_store_request_schema()) {
            return ['ok' => false, 'message' => 'Schema Store Request belum tersedia.'];
        }
        if ($requestId <= 0) {
            return ['ok' => false, 'message' => 'Request ID tidak valid.'];
        }

        $existing = $this->db
            ->from('pur_store_request')
            ->where('id', $requestId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$existing) {
            return ['ok' => false, 'message' => 'Store Request tidak ditemukan.'];
        }

        $status = strtoupper((string)($existing['status'] ?? 'DRAFT'));
        if ($status !== 'DRAFT') {
            return ['ok' => false, 'message' => 'Hanya Store Request status DRAFT yang dapat diedit.'];
        }
        $targetStatus = strtoupper(trim((string)($header['status'] ?? 'DRAFT')));
        if (!in_array($targetStatus, ['DRAFT', 'SUBMITTED'], true)) {
            $targetStatus = 'DRAFT';
        }

        $requestDate = $this->normalize_date((string)($header['request_date'] ?? (string)($existing['request_date'] ?? '')));
        $neededDate = $this->normalize_date((string)($header['needed_date'] ?? (string)($existing['needed_date'] ?? '')));
        $divisionId = (int)($header['request_division_id'] ?? (int)($existing['request_division_id'] ?? 0));
        $destinationType = $this->normalize_destination((string)($header['destination_type'] ?? (string)($existing['destination_type'] ?? '')));
        $notes = $this->nullable_string($header['notes'] ?? ($existing['notes'] ?? null));

        if ($requestDate === null || $divisionId <= 0 || $destinationType === null) {
            return ['ok' => false, 'message' => 'Header belum lengkap: request_date, divisi, dan destination wajib valid.'];
        }
        if (!$this->is_destination_allowed_for_division($divisionId, $destinationType)) {
            $allowed = $this->allowed_destinations_for_division($divisionId);
            return ['ok' => false, 'message' => 'Tujuan tidak sesuai divisi. Pilihan yang diizinkan: ' . implode(', ', $allowed) . '.'];
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

        $this->db
            ->where('id', $requestId)
            ->update('pur_store_request', [
                'request_date' => $requestDate,
                'needed_date' => $neededDate,
                'request_division_id' => $divisionId,
                'destination_type' => $destinationType,
                'status' => $targetStatus,
                'notes' => $notes,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        if ($targetStatus === 'SUBMITTED' && $this->db->table_exists('pur_store_request_approval')) {
            $this->db->insert('pur_store_request_approval', [
                'store_request_id' => $requestId,
                'action' => 'SUBMIT',
                'actor_user_id' => $userId > 0 ? $userId : null,
                'actor_name_snapshot' => null,
                'notes' => 'Store Request disubmit saat update draft',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->db->where('store_request_id', $requestId)->delete('pur_store_request_line');
        foreach ($lineRows as $lineData) {
            $lineData['store_request_id'] = $requestId;
            $this->append_expiry_requirement_columns('pur_store_request_line', $lineData, $lineData, 'profile_expired_date');
            $this->db->insert('pur_store_request_line', $lineData);
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal memperbarui Store Request.'];
        }

        $this->db->trans_commit();
        return [
            'ok' => true,
            'message' => $targetStatus === 'SUBMITTED'
                ? 'Store Request berhasil diperbarui dan disubmit.'
                : 'Store Request berhasil diperbarui.',
            'id' => $requestId,
            'sr_no' => (string)($existing['sr_no'] ?? ''),
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
        $needsReverseFulfillment = false;

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
            if (!in_array($status, ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED', 'PARTIAL_FULFILLED', 'FULFILLED'], true)) {
                return ['ok' => false, 'message' => 'Status saat ini tidak bisa di-void pada tahap ini.'];
            }
            if (in_array($status, ['PARTIAL_FULFILLED', 'FULFILLED'], true)) {
                $needsReverseFulfillment = true;
            }
            $nextStatus = 'VOID';
        }

        $this->db->trans_begin();
        if ($needsReverseFulfillment) {
            $reverseResult = $this->reverse_fulfillments_before_void($requestId, $notes, $userId);
            if (!($reverseResult['ok'] ?? false)) {
                $this->db->trans_rollback();
                return $reverseResult;
            }
        }
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
        $this->load->library('MaterialFifoManager');
        $fifoReady = $this->materialfifomanager->ensureReady();
        if (!($fifoReady['ok'] ?? false)) {
            return $fifoReady;
        }
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

            $unitCostSnapshot = $this->resolve_store_request_line_unit_cost($line, $contentPerBuy);

            $fulfillmentLineData = [
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
                'unit_cost_snapshot' => $unitCostSnapshot,
                'notes' => $this->nullable_string($notes),
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $this->append_expiry_requirement_columns('pur_store_request_fulfillment_line', $fulfillmentLineData, $line, 'profile_expired_date');
            if ($this->db->field_exists('fifo_issue_id', 'pur_store_request_fulfillment_line')) {
                $fulfillmentLineData['fifo_issue_id'] = null;
            }
            if ($this->db->field_exists('fifo_issue_no', 'pur_store_request_fulfillment_line')) {
                $fulfillmentLineData['fifo_issue_no'] = null;
            }
            $this->db->insert('pur_store_request_fulfillment_line', $fulfillmentLineData);
            $fulfillmentLineId = (int)$this->db->insert_id();
            if ($fulfillmentLineId <= 0) {
                $this->db->trans_rollback();
                return ['ok' => false, 'message' => 'Gagal menyimpan detail fulfillment SR.'];
            }

            $fifoTransfer = $this->materialfifomanager->transferWarehouseToDivision([
                'issue_date' => $date,
                'division_id' => $divisionId,
                'destination_type' => $destinationType,
                'item_id' => $this->nullable_int($line['item_id'] ?? null),
                'material_id' => $this->nullable_int($line['material_id'] ?? null),
                'buy_uom_id' => $this->nullable_int($line['buy_uom_id'] ?? null),
                'content_uom_id' => $this->nullable_int($line['content_uom_id'] ?? null),
                'profile_key' => $this->nullable_string($line['profile_key'] ?? null),
                'qty_content_out' => $qtyContent,
                'source_module' => 'PROCUREMENT_SR',
                'source_table' => 'pur_store_request_fulfillment',
                'source_id' => $fulfillmentId,
                'source_line_id' => $fulfillmentLineId,
                'notes' => 'SR ' . $srNo . ' fulfill ke divisi',
            ]);
            if (!($fifoTransfer['ok'] ?? false)) {
                $this->db->trans_rollback();
                return ['ok' => false, 'message' => (string)($fifoTransfer['message'] ?? 'Gagal consume FIFO gudang untuk fulfillment SR.')];
            }

            $fifoIssueId = (int)($fifoTransfer['data']['issue_id'] ?? 0);
            $fifoIssueNo = $this->nullable_string($fifoTransfer['data']['issue_no'] ?? null);
            $fifoUnitCost = round((float)($fifoTransfer['data']['avg_unit_cost'] ?? 0), 6);
            if ($fifoUnitCost > 0) {
                $unitCostSnapshot = $fifoUnitCost;
            }

            $this->db->where('id', $fulfillmentLineId)->update('pur_store_request_fulfillment_line', [
                'unit_cost_snapshot' => $unitCostSnapshot,
                'fifo_issue_id' => $fifoIssueId > 0 ? $fifoIssueId : null,
                'fifo_issue_no' => $fifoIssueNo,
                'updated_at' => date('Y-m-d H:i:s'),
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
                'unit_cost' => $unitCostSnapshot,
                'stock_domain' => $this->resolve_line_stock_domain($line),
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

    public function repair_void_store_request_history(int $requestId, int $userId = 0): array
    {
        if (!$this->has_store_request_schema()) {
            return ['ok' => false, 'message' => 'Schema Store Request belum tersedia.'];
        }
        if ($requestId <= 0) {
            return ['ok' => false, 'message' => 'Request ID tidak valid.'];
        }

        $header = $this->db
            ->select('id, sr_no, request_division_id, destination_type')
            ->from('pur_store_request')
            ->where('id', $requestId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$header) {
            return ['ok' => false, 'message' => 'Store Request tidak ditemukan.'];
        }

        $voidFulfillments = $this->db
            ->from('pur_store_request_fulfillment')
            ->where('store_request_id', $requestId)
            ->where('status', 'VOID')
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();
        if (empty($voidFulfillments)) {
            return ['ok' => true, 'message' => 'Tidak ada fulfillment VOID yang perlu direpair.', 'data' => ['rebuild_targets' => 0]];
        }

        $this->db->trans_begin();
        $repair = $this->purge_fulfillment_movements_and_rebuild($header, $voidFulfillments, $userId);
        if (!($repair['ok'] ?? false)) {
            $this->db->trans_rollback();
            return $repair;
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal repair histori void fulfillment SR.'];
        }

        $this->db->trans_commit();
        return $repair;
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
                'line_description' => $this->nullable_string($row['profile_description'] ?? null),
                'brand_name' => $this->nullable_string($row['profile_brand'] ?? null),
                'qty_buy' => round($shortageContent / max($cpb, 0.000001), 4),
                'buy_uom_id' => (int)($row['buy_uom_id'] ?? 0),
                'content_per_buy' => $cpb,
                'qty_content' => $shortageContent,
                'content_uom_id' => (int)($row['content_uom_id'] ?? 0),
                'conversion_factor_to_content' => $cpb,
                'unit_price' => 0,
                'discount_percent' => 0,
                'tax_percent' => 0,
                'profile_key' => (string)($row['profile_key'] ?? ''),
                'snapshot_item_name' => (string)($row['profile_name'] ?? ''),
                'snapshot_material_name' => (string)($row['profile_name'] ?? ''),
                'snapshot_brand_name' => $this->nullable_string($row['profile_brand'] ?? null),
                'snapshot_line_description' => $this->nullable_string($row['profile_description'] ?? null),
                'snapshot_buy_uom_code' => (string)($row['profile_buy_uom_code'] ?? ''),
                'snapshot_content_uom_code' => (string)($row['profile_content_uom_code'] ?? ''),
                'notes' => 'Auto shortage dari SR ' . (string)($header['sr_no'] ?? ''),
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

    public function find_inventory_purchase_type_id(?string $destinationType = null): ?int
    {
        if (!$this->db->table_exists('mst_purchase_type')) {
            return null;
        }

        $destination = strtoupper(trim((string)$destinationType));
        $preferredCodes = ['INV_STOK'];
        if ($destination === 'BAR') {
            $preferredCodes = ['BAR_STOK', 'INV_BAR', 'INV_STOK'];
        } elseif ($destination === 'KITCHEN') {
            $preferredCodes = ['KITCHEN_STOK', 'INV_KITCHEN', 'INV_STOK'];
        } elseif ($destination === 'BAR_EVENT') {
            $preferredCodes = ['BAR_STOK_EVENT', 'BAR_STOK', 'INV_BAR', 'INV_STOK'];
        } elseif ($destination === 'KITCHEN_EVENT') {
            $preferredCodes = ['KITCHEN_STOK_EVENT', 'KITCHEN_STOK', 'INV_KITCHEN', 'INV_STOK'];
        } elseif ($destination === 'OFFICE') {
            $preferredCodes = ['OPERASIONAL_OFFICE', 'INV_STOK'];
        }

        $escapedCodes = array_map([$this->db, 'escape'], $preferredCodes);
        $fieldOrderSql = implode(',', array_map(static function (string $code): string {
            return "'" . strtoupper(trim($code)) . "'";
        }, $preferredCodes));

        $row = $this->db->query(
            'SELECT id FROM mst_purchase_type '
            . 'WHERE is_active = 1 AND UPPER(TRIM(type_code)) IN (' . implode(',', $escapedCodes) . ') '
            . 'ORDER BY FIELD(UPPER(TRIM(type_code)), ' . $fieldOrderSql . '), id ASC LIMIT 1'
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
            $qtyBuyRequested = round((float)($line['qty_buy_requested'] ?? 0), 2);
            $qtyContentRequested = round((float)($line['qty_content_requested'] ?? 0), 2);

            if ($materialId > 0) {
                $lineKind = 'MATERIAL';
            } elseif ($lineKind === '') {
                $lineKind = $itemId > 0 ? 'ITEM' : '';
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
                $qtyContentRequested = round($qtyBuyRequested * $contentPerBuy, 2);
            }
            if ($qtyBuyRequested <= 0 && $qtyContentRequested > 0) {
                $qtyBuyRequested = round($qtyContentRequested / max($contentPerBuy, 0.000001), 2);
            }

            $expiryRequirement = $this->extract_expiry_requirement($line, 'profile_expired_date');

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
                'expiry_policy' => $expiryRequirement['expiry_policy'],
                'required_expiry_date' => $expiryRequirement['required_expiry_date'],
                'min_remaining_days' => $expiryRequirement['min_remaining_days'],
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
            $requestUomModeRaw = strtoupper(trim((string)($line['request_uom_mode'] ?? '')));
            if (!in_array($requestUomModeRaw, ['BUY', 'CONTENT'], true)) {
                return ['ok' => false, 'message' => 'Line #' . $lineNo . ' pilih mode input dulu (per beli / per isi).'];
            }
            $requestUomMode = $requestUomModeRaw;
            $vendorId = (int)($line['vendor_id'] ?? 0);
            $qtyBuyRequested = round((float)($line['qty_buy_requested'] ?? 0), 4);
            $qtyContentRequested = round((float)($line['qty_content_requested'] ?? 0), 4);
            $qtyBuyPoRequested = round((float)($line['qty_buy_po_requested'] ?? 0), 4);
            $qtyContentPoRequested = round((float)($line['qty_content_po_requested'] ?? 0), 4);
            $estimatedUnitPrice = round((float)($line['estimated_unit_price'] ?? ($line['unit_price'] ?? 0)), 2);
            $sourceType = strtoupper(trim((string)($line['source_type'] ?? ($line['search_source'] ?? 'WAREHOUSE'))));
            $profileName = $this->nullable_string($line['profile_name'] ?? ($line['catalog_name'] ?? ($line['item_name'] ?? null)));
            $profileBrand = $this->nullable_string($line['profile_brand'] ?? ($line['brand_name'] ?? null));
            $profileDescription = $this->nullable_string($line['profile_description'] ?? ($line['line_description'] ?? null));

            if ($materialId > 0) {
                $lineKind = 'MATERIAL';
            } elseif ($lineKind === '') {
                $lineKind = ($itemId > 0 || $sourceType === 'MANUAL') ? 'ITEM' : '';
            }
            if (!in_array($lineKind, ['ITEM', 'MATERIAL'], true)) {
                return ['ok' => false, 'message' => 'Line #' . $lineNo . ' jenis line tidak valid.'];
            }
            if ($lineKind === 'ITEM' && $itemId <= 0 && $sourceType !== 'MANUAL') {
                return ['ok' => false, 'message' => 'Line #' . $lineNo . ' wajib item.'];
            }
            if ($lineKind === 'MATERIAL' && $materialId <= 0) {
                return ['ok' => false, 'message' => 'Line #' . $lineNo . ' wajib material.'];
            }
            if ($sourceType === 'MANUAL') {
                if ($profileName === null) {
                    return ['ok' => false, 'message' => 'Line #' . $lineNo . ' nama barang manual wajib diisi.'];
                }
                $lineKind = 'ITEM';
                if ($profileKey === '') {
                    $profileKey = $this->build_division_request_manual_profile_key($profileName, $buyUomId, $contentUomId, $profileDescription);
                }
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
            if ($qtyContentPoRequested <= 0 && $qtyBuyPoRequested > 0) {
                $qtyContentPoRequested = round($qtyBuyPoRequested * $contentPerBuy, 4);
            }
            if ($qtyBuyPoRequested <= 0 && $qtyContentPoRequested > 0) {
                $qtyBuyPoRequested = round($qtyContentPoRequested / max($contentPerBuy, 0.000001), 4);
            }
            if ($qtyBuyPoRequested < 0 || $qtyContentPoRequested < 0) {
                return ['ok' => false, 'message' => 'Line #' . $lineNo . ' qty tambahan PO tidak valid.'];
            }
            if ($estimatedUnitPrice < 0) {
                return ['ok' => false, 'message' => 'Line #' . $lineNo . ' harga estimasi tidak valid.'];
            }
            if ($qtyContentPoRequested > $qtyContentRequested) {
                $qtyContentPoRequested = $qtyContentRequested;
                $qtyBuyPoRequested = round($qtyContentPoRequested / max($contentPerBuy, 0.000001), 4);
            }

            $expiryRequirement = $this->extract_expiry_requirement($line, 'profile_expired_date');

            $normalizedLines[] = [
                'line_kind' => $lineKind,
                'item_id' => $itemId > 0 ? $itemId : null,
                'material_id' => $materialId > 0 ? $materialId : null,
                'profile_key' => substr($profileKey, 0, 64),
                'profile_name' => $profileName,
                'profile_brand' => $profileBrand,
                'profile_description' => $profileDescription,
                'profile_expired_date' => $this->normalize_date((string)($line['profile_expired_date'] ?? '')),
                'expiry_policy' => $expiryRequirement['expiry_policy'],
                'required_expiry_date' => $expiryRequirement['required_expiry_date'],
                'min_remaining_days' => $expiryRequirement['min_remaining_days'],
                'buy_uom_id' => $buyUomId,
                'content_uom_id' => $contentUomId,
                'profile_content_per_buy' => $contentPerBuy,
                'profile_buy_uom_code' => $this->nullable_string($line['profile_buy_uom_code'] ?? null),
                'profile_content_uom_code' => $this->nullable_string($line['profile_content_uom_code'] ?? null),
                'request_uom_mode' => $requestUomMode,
                'vendor_id' => $vendorId > 0 ? $vendorId : null,
                'qty_buy_requested' => $qtyBuyRequested,
                'qty_content_requested' => $qtyContentRequested,
                'qty_buy_po_requested' => $qtyBuyPoRequested,
                'qty_content_po_requested' => $qtyContentPoRequested,
                'estimated_unit_price' => max(0, $estimatedUnitPrice),
                'source_type' => in_array($sourceType, ['WAREHOUSE', 'CATALOG', 'MASTER', 'MANUAL'], true) ? $sourceType : 'WAREHOUSE',
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

    private function build_store_request_line_agg_sql(): string
    {
        $costSql = $this->build_store_request_line_cost_sql();
        return "SELECT
                ln.store_request_id,
                COUNT(*) AS line_count,
                SUM(ln.qty_buy_requested) AS req_buy_total,
                SUM(ln.qty_content_requested) AS req_content_total,
                SUM(ln.qty_buy_fulfilled) AS fulfilled_buy_total,
                SUM(ln.qty_content_fulfilled) AS fulfilled_content_total,
                SUM(COALESCE(c.req_total_value, 0)) AS req_total_value,
                SUM(COALESCE(c.fulfilled_total_value, 0)) AS fulfilled_total_value
            FROM pur_store_request_line ln
            LEFT JOIN ({$costSql}) c ON c.line_id = ln.id
            GROUP BY ln.store_request_id";
    }

    private function build_store_request_line_cost_agg_sql(): string
    {
        $costSql = $this->build_store_request_line_cost_sql();
        return "SELECT
                ln.store_request_id,
                SUM(COALESCE(c.req_total_value, 0)) AS req_total_value,
                SUM(COALESCE(c.fulfilled_total_value, 0)) AS fulfilled_total_value
            FROM pur_store_request_line ln
            LEFT JOIN ({$costSql}) c ON c.line_id = ln.id
            GROUP BY ln.store_request_id";
    }

    private function build_store_request_line_cost_sql(): string
    {
        $fulfillAggSql = "SELECT
                fl.store_request_line_id,
                SUM(COALESCE(fl.qty_content_posted, 0)) AS posted_qty_total,
                SUM(COALESCE(fl.qty_content_posted, 0) * COALESCE(fl.unit_cost_snapshot, 0)) AS posted_total_value
            FROM pur_store_request_fulfillment_line fl
            INNER JOIN pur_store_request_fulfillment f ON f.id = fl.fulfillment_id
            WHERE COALESCE(f.status, '') <> 'VOID'
            GROUP BY fl.store_request_line_id";
        $warehouseCostSql = "SELECT
                COALESCE(w.profile_key, '') AS profile_key,
                COALESCE(w.buy_uom_id, 0) AS buy_uom_id,
                COALESCE(w.content_uom_id, 0) AS content_uom_id,
                AVG(COALESCE(w.avg_cost_per_content, 0)) AS avg_cost_per_content
            FROM inv_warehouse_stock_balance w
            GROUP BY COALESCE(w.profile_key, ''), COALESCE(w.buy_uom_id, 0), COALESCE(w.content_uom_id, 0)";
        $warehouseItemCostSql = "SELECT
                COALESCE(w.item_id, 0) AS item_id,
                COALESCE(w.buy_uom_id, 0) AS buy_uom_id,
                COALESCE(w.content_uom_id, 0) AS content_uom_id,
                AVG(COALESCE(w.avg_cost_per_content, 0)) AS avg_cost_per_content
            FROM inv_warehouse_stock_balance w
            GROUP BY COALESCE(w.item_id, 0), COALESCE(w.buy_uom_id, 0), COALESCE(w.content_uom_id, 0)";

        return "SELECT
                ln.id AS line_id,
                CASE
                    WHEN COALESCE(fa.posted_qty_total, 0) > 0
                         AND COALESCE(fa.posted_total_value, 0) > 0
                    THEN (COALESCE(fa.posted_total_value, 0) / NULLIF(fa.posted_qty_total, 0))
                    ELSE COALESCE(NULLIF(wc.avg_cost_per_content, 0), NULLIF(wci.avg_cost_per_content, 0), 0)
                END AS unit_cost_ref,
                COALESCE(ln.qty_content_requested, 0) * CASE
                    WHEN COALESCE(fa.posted_qty_total, 0) > 0
                         AND COALESCE(fa.posted_total_value, 0) > 0
                    THEN (COALESCE(fa.posted_total_value, 0) / NULLIF(fa.posted_qty_total, 0))
                    ELSE COALESCE(NULLIF(wc.avg_cost_per_content, 0), NULLIF(wci.avg_cost_per_content, 0), 0)
                END AS req_total_value,
                COALESCE(fa.posted_total_value, 0) AS fulfilled_total_value
            FROM pur_store_request_line ln
            LEFT JOIN ({$fulfillAggSql}) fa ON fa.store_request_line_id = ln.id
            LEFT JOIN ({$warehouseCostSql}) wc
                ON COALESCE(wc.profile_key, '') = COALESCE(ln.profile_key, '')
                AND COALESCE(wc.buy_uom_id, 0) = COALESCE(ln.buy_uom_id, 0)
                AND COALESCE(wc.content_uom_id, 0) = COALESCE(ln.content_uom_id, 0)
            LEFT JOIN ({$warehouseItemCostSql}) wci
                ON COALESCE(wci.item_id, 0) = COALESCE(ln.item_id, 0)
                AND COALESCE(wci.buy_uom_id, 0) = COALESCE(ln.buy_uom_id, 0)
                AND COALESCE(wci.content_uom_id, 0) = COALESCE(ln.content_uom_id, 0)";
    }

    private function resolve_store_request_line_unit_cost(array $line, float $contentPerBuy): float
    {
        $itemId = (int)($line['item_id'] ?? 0);
        $buyUomId = (int)($line['buy_uom_id'] ?? 0);
        $contentUomId = (int)($line['content_uom_id'] ?? 0);
        $profileKey = trim((string)($line['profile_key'] ?? ''));
        $contentPerBuy = $contentPerBuy > 0 ? $contentPerBuy : 1.0;

        if ($this->db->table_exists('inv_warehouse_stock_balance') && $itemId > 0 && $buyUomId > 0 && $contentUomId > 0) {
            if ($profileKey !== '') {
                $row = $this->db
                    ->select('avg_cost_per_content, qty_content_balance')
                    ->from('inv_warehouse_stock_balance')
                    ->where('item_id', $itemId)
                    ->where('buy_uom_id', $buyUomId)
                    ->where('content_uom_id', $contentUomId)
                    ->where('profile_key', $profileKey)
                    ->order_by('qty_content_balance', 'DESC')
                    ->limit(1)
                    ->get()
                    ->row_array();
                $v = (float)($row['avg_cost_per_content'] ?? 0);
                if ($v > 0) {
                    return round($v, 6);
                }
            }

            $row = $this->db
                ->select('AVG(COALESCE(avg_cost_per_content,0)) AS avg_cost', false)
                ->from('inv_warehouse_stock_balance')
                ->where('item_id', $itemId)
                ->where('buy_uom_id', $buyUomId)
                ->where('content_uom_id', $contentUomId)
                ->get()
                ->row_array();
            $avg = (float)($row['avg_cost'] ?? 0);
            if ($avg > 0) {
                return round($avg, 6);
            }
        }

        if ($this->db->table_exists('mst_purchase_catalog') && $profileKey !== '') {
            $hasCatalogVendorPrice = $this->purchaseCatalogVendorTableExists();
            $latestVendorPriceSql = $hasCatalogVendorPrice ? $this->latestCatalogVendorPriceSubquerySql() : '';
            if ($hasCatalogVendorPrice) {
                $row = $this->db
                    ->select('COALESCE(cvp.last_unit_price, cvp.standard_price, c.last_unit_price, c.standard_price) AS unit_buy', false)
                    ->from('mst_purchase_catalog c')
                    ->join('(' . $latestVendorPriceSql . ') cvp', 'cvp.catalog_id = c.id', 'left', false)
                    ->where('c.profile_key', $profileKey)
                    ->order_by('COALESCE(cvp.last_purchase_date, c.last_purchase_date)', 'DESC', false)
                    ->order_by('c.id', 'DESC')
                    ->limit(1)
                    ->get()
                    ->row_array();
            } else {
                $row = $this->db
                    ->select('COALESCE(c.last_unit_price, c.standard_price) AS unit_buy', false)
                    ->from('mst_purchase_catalog c')
                    ->where('c.profile_key', $profileKey)
                    ->order_by('c.last_purchase_date', 'DESC')
                    ->order_by('c.id', 'DESC')
                    ->limit(1)
                    ->get()
                    ->row_array();
            }

            $unitBuy = (float)($row['unit_buy'] ?? 0);
            if ($unitBuy > 0) {
                return round($unitBuy / max($contentPerBuy, 0.000001), 6);
            }
        }

        return 0.0;
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

    private function prepare_division_request_routes(string $requestNo, array $lineRows): array
    {
        $storedLines = [];
        $srLines = [];
        $poLines = [];
        $lineNo = 1;
        $hasRequestUomMode = $this->has_division_request_request_uom_mode_column();
        $hasVendorColumn = $this->has_division_request_vendor_column();
        $hasEstimatedUnitPrice = $this->db->field_exists('estimated_unit_price', 'pur_division_request_line');

        foreach ($lineRows as $line) {
            $sourceType = strtoupper(trim((string)($line['source_type'] ?? 'WAREHOUSE')));
            $availableContent = $sourceType === 'WAREHOUSE'
                ? $this->get_warehouse_available_content(
                    $this->nullable_int($line['item_id'] ?? null),
                    $this->nullable_int($line['material_id'] ?? null),
                    $this->nullable_int($line['buy_uom_id'] ?? null),
                    $this->nullable_int($line['content_uom_id'] ?? null),
                    (string)($line['profile_key'] ?? '')
                )
                : 0.0;
            $requestedContent = round((float)($line['qty_content_requested'] ?? 0), 4);
            $requestedBuy = round((float)($line['qty_buy_requested'] ?? 0), 4);
            $requestedPoContent = round((float)($line['qty_content_po_requested'] ?? 0), 4);
            $cpb = round((float)($line['profile_content_per_buy'] ?? 1), 6);
            if ($cpb <= 0) {
                $cpb = 1;
            }
            $estimatedUnitPrice = round((float)($line['estimated_unit_price'] ?? 0), 2);
            if ($estimatedUnitPrice < 0) {
                $estimatedUnitPrice = 0;
            }
            $profileBrand = $this->nullable_string($line['profile_brand'] ?? null);
            $profileDescription = $this->nullable_string($line['profile_description'] ?? null);

            $srContent = 0.0;
            $poContent = 0.0;
            if ($sourceType === 'WAREHOUSE') {
                if ($requestedPoContent > 0) {
                    $poContent = min($requestedContent, $requestedPoContent);
                    $srContent = min(max(0, $requestedContent - $poContent), $availableContent);
                    $remainingContent = max(0, $requestedContent - $srContent - $poContent);
                    if ($remainingContent > 0) {
                        $poContent += $remainingContent;
                    }
                } else {
                    $srContent = min($requestedContent, $availableContent);
                    $poContent = max(0, $requestedContent - $srContent);
                }
            } else {
                $poContent = $requestedContent;
            }

            if ($srContent < 0) {
                $srContent = 0;
            }
            if ($poContent < 0) {
                $poContent = 0;
            }
            $srBuy = round($srContent / max($cpb, 0.000001), 4);
            $poBuy = round($poContent / max($cpb, 0.000001), 4);
            $routeType = $srContent > 0 && $poContent > 0 ? 'MIXED' : ($srContent > 0 ? 'SR' : 'PO');

            $storedLine = [
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
            $this->append_expiry_requirement_columns('pur_division_request_line', $storedLine, $line, 'profile_expired_date');
            if ($hasRequestUomMode) {
                $storedLine['request_uom_mode'] = $this->normalize_division_request_uom_mode($line['request_uom_mode'] ?? 'BUY');
            }
            if ($hasVendorColumn) {
                $storedLine['vendor_id'] = $this->nullable_int($line['vendor_id'] ?? null);
            }
            if ($hasEstimatedUnitPrice) {
                $storedLine['estimated_unit_price'] = $estimatedUnitPrice;
            }
            $storedLines[] = $storedLine;

            if ($srContent > 0) {
                $srLine = [
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
                    'notes' => null,
                ];
                $this->append_expiry_requirement_columns('pur_store_request_line', $srLine, $line, 'profile_expired_date');
                $srLines[] = $srLine;
            }

            if ($poContent > 0) {
                $poLineNotes = [];
                $requestMarker = trim($requestNo);
                if ($requestMarker !== '') {
                    $poLineNotes[] = 'Dari pengajuan divisi ' . $requestMarker;
                }
                $lineNoteText = trim((string)($line['notes'] ?? ''));
                if ($lineNoteText !== '') {
                    $poLineNotes[] = $lineNoteText;
                }
                $poLines[] = [
                    'source_line_no' => (int)($storedLine['line_no'] ?? 0),
                    'line_kind' => (string)$line['line_kind'],
                    'item_id' => $this->nullable_int($line['item_id'] ?? null),
                    'material_id' => $this->nullable_int($line['material_id'] ?? null),
                    'vendor_id' => $this->nullable_int($line['vendor_id'] ?? null),
                    'catalog_name' => (string)($line['profile_name'] ?? ''),
                    'item_name' => (string)($line['profile_name'] ?? ''),
                    'line_description' => $profileDescription,
                    'brand_name' => $profileBrand,
                    'qty_buy' => $poBuy,
                    'buy_uom_id' => (int)$line['buy_uom_id'],
                    'content_per_buy' => $cpb,
                    'qty_content' => $poContent,
                    'content_uom_id' => (int)$line['content_uom_id'],
                    'conversion_factor_to_content' => $cpb,
                    'unit_price' => $estimatedUnitPrice,
                    'discount_percent' => 0,
                    'tax_percent' => 0,
                    'profile_key' => (string)$line['profile_key'],
                    'snapshot_item_name' => (string)($line['profile_name'] ?? ''),
                    'snapshot_material_name' => (string)($line['profile_name'] ?? ''),
                    'snapshot_brand_name' => $profileBrand,
                    'snapshot_line_description' => $profileDescription,
                    'snapshot_buy_uom_code' => (string)($line['profile_buy_uom_code'] ?? ''),
                    'snapshot_content_uom_code' => (string)($line['profile_content_uom_code'] ?? ''),
                    'notes' => $this->nullable_string(implode(' | ', $poLineNotes)),
                ];
            }
        }

        return [
            'stored_lines' => $storedLines,
            'sr_lines' => $srLines,
            'po_lines' => $poLines,
        ];
    }

    private function normalize_division_request_candidate_rows(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $profileName = $this->nullable_string($row['catalog_name'] ?? ($row['item_name'] ?? ($row['material_name'] ?? null)));
            if ($profileName === null) {
                continue;
            }

            $lineKind = strtoupper(trim((string)($row['line_kind'] ?? 'ITEM')));
            $itemId = $this->nullable_int($row['item_id'] ?? null);
            $materialId = $this->nullable_int($row['material_id'] ?? null);
            if ($materialId !== null && $materialId > 0) {
                $lineKind = 'MATERIAL';
            }
            if (!in_array($lineKind, ['ITEM', 'MATERIAL'], true)) {
                if ($itemId === null || $itemId <= 0) {
                    continue;
                }
                $lineKind = 'ITEM';
            }

            $buyUomId = (int)($row['buy_uom_id'] ?? 0);
            $contentUomId = (int)($row['content_uom_id'] ?? 0);
            if ($buyUomId <= 0 || $contentUomId <= 0) {
                continue;
            }

            $contentPerBuy = round((float)($row['content_per_buy'] ?? ($row['conversion_factor_to_content'] ?? 1)), 6);
            if ($contentPerBuy <= 0) {
                $contentPerBuy = 1;
            }

            $profileKey = trim((string)($row['profile_key'] ?? ''));
            if ($profileKey === '') {
                $profileKey = hash('sha256', implode('|', [
                    'DREQCAT',
                    $lineKind,
                    (int)($row['item_id'] ?? 0),
                    (int)($row['material_id'] ?? 0),
                    $profileName,
                    $buyUomId,
                    $contentUomId,
                    $contentPerBuy,
                ]));
            }

            $identity = $this->build_division_request_candidate_identity([
                'line_kind' => $lineKind,
                'item_id' => $itemId,
                'material_id' => $materialId,
                'profile_key' => $profileKey,
                'profile_name' => $profileName,
                'buy_uom_id' => $buyUomId,
                'content_uom_id' => $contentUomId,
                'profile_content_per_buy' => $contentPerBuy,
            ]);
            $expiryRequirement = $this->extract_expiry_requirement(['required_expiry_date' => $row['expired_date'] ?? null], 'required_expiry_date');

            $candidate = [
                'line_kind' => $lineKind,
                'item_id' => $itemId,
                'material_id' => $materialId,
                'catalog_id' => $this->nullable_int($row['catalog_id'] ?? null),
                'profile_key' => substr($profileKey, 0, 64),
                'profile_name' => $profileName,
                'profile_brand' => $this->nullable_string($row['brand_name'] ?? null),
                'profile_description' => $this->nullable_string($row['line_description'] ?? ($row['notes'] ?? null)),
                'profile_expired_date' => $this->normalize_date((string)($row['expired_date'] ?? '')),
                'expiry_policy' => $expiryRequirement['expiry_policy'],
                'required_expiry_date' => $expiryRequirement['required_expiry_date'],
                'min_remaining_days' => $expiryRequirement['min_remaining_days'],
                'buy_uom_id' => $buyUomId,
                'content_uom_id' => $contentUomId,
                'profile_content_per_buy' => $contentPerBuy,
                'profile_buy_uom_code' => $this->nullable_string($row['buy_uom_code'] ?? null),
                'profile_content_uom_code' => $this->nullable_string($row['content_uom_code'] ?? null),
                'qty_buy_balance' => 0,
                'qty_content_balance' => 0,
                'standard_price' => round((float)($row['standard_price'] ?? 0), 2),
                'last_unit_price' => round((float)($row['last_unit_price'] ?? ($row['standard_price'] ?? 0)), 2),
                'last_purchase_date' => $this->normalize_date((string)($row['last_purchase_date'] ?? '')),
                'source_type' => strtoupper(trim((string)($row['source_type'] ?? 'CATALOG'))),
                'search_source' => 'CATALOG',
            ];

            if (!isset($normalized[$identity]) || $this->should_prefer_division_request_candidate($normalized[$identity], $candidate, false)) {
                $normalized[$identity] = $candidate;
            }
        }

        return array_values($normalized);
    }

    private function build_division_request_candidate_identity(array $row): string
    {
        $lineKind = strtoupper(trim((string)($row['line_kind'] ?? 'ITEM')));
        $itemId = (int)($row['item_id'] ?? 0);
        $materialId = (int)($row['material_id'] ?? 0);
        $profileKey = strtoupper(trim((string)($row['profile_key'] ?? '')));
        $buyUomId = (int)($row['buy_uom_id'] ?? 0);
        $contentUomId = (int)($row['content_uom_id'] ?? 0);
        $contentPerBuy = round((float)($row['profile_content_per_buy'] ?? 1), 6);
        $suffix = '|PK:' . $profileKey . '|B:' . $buyUomId . '|C:' . $contentUomId . '|CPB:' . $contentPerBuy;
        if ($materialId > 0) {
            return 'MATERIAL|' . $materialId . $suffix;
        }
        if ($itemId > 0) {
            return $lineKind . '|' . $itemId . $suffix;
        }

        return $lineKind . '|NAME|' . strtoupper(trim((string)($row['profile_name'] ?? ''))) . $suffix;
    }

    private function should_prefer_division_request_candidate(array $currentRow, array $candidateRow, bool $preferHigherStock): bool
    {
        $currentDate = (string)($this->normalize_date((string)($currentRow['last_purchase_date'] ?? '')) ?? '');
        $candidateDate = (string)($this->normalize_date((string)($candidateRow['last_purchase_date'] ?? '')) ?? '');
        if ($candidateDate !== $currentDate) {
            return $candidateDate > $currentDate;
        }

        $currentCatalogId = (int)($currentRow['catalog_id'] ?? 0);
        $candidateCatalogId = (int)($candidateRow['catalog_id'] ?? 0);
        if ($candidateCatalogId !== $currentCatalogId) {
            return $candidateCatalogId > $currentCatalogId;
        }

        $currentPrice = (float)($currentRow['last_unit_price'] ?? ($currentRow['standard_price'] ?? 0));
        $candidatePrice = (float)($candidateRow['last_unit_price'] ?? ($candidateRow['standard_price'] ?? 0));
        if (abs($candidatePrice - $currentPrice) > 0.00001) {
            return $candidatePrice > $currentPrice;
        }

        if ($preferHigherStock) {
            $currentQty = (float)($currentRow['qty_content_balance'] ?? ($currentRow['qty_buy_balance'] ?? 0));
            $candidateQty = (float)($candidateRow['qty_content_balance'] ?? ($candidateRow['qty_buy_balance'] ?? 0));
            if (abs($candidateQty - $currentQty) > 0.00001) {
                return $candidateQty > $currentQty;
            }
        }

        return false;
    }

    private function build_division_request_manual_profile_key(string $profileName, int $buyUomId, int $contentUomId, ?string $profileDescription): string
    {
        return hash('sha256', implode('|', [
            'DREQMANUAL',
            strtoupper(trim($profileName)),
            $buyUomId,
            $contentUomId,
            strtoupper(trim((string)$profileDescription)),
        ]));
    }

    private function infer_destination_from_division(int $divisionId): string
    {
        if ($divisionId <= 0) {
            return 'OTHER';
        }
        $selectParts = ['id'];
        if ($this->db->field_exists('code', 'mst_operational_division')) {
            $selectParts[] = 'code';
        }
        if ($this->db->field_exists('name', 'mst_operational_division')) {
            $selectParts[] = 'name';
        } elseif ($this->db->field_exists('division_name', 'mst_operational_division')) {
            $selectParts[] = 'division_name AS name';
        } elseif ($this->db->field_exists('division', 'mst_operational_division')) {
            $selectParts[] = 'division AS name';
        }

        $row = $this->db
            ->select(implode(', ', $selectParts), false)
            ->from('mst_operational_division')
            ->where('id', $divisionId)
            ->limit(1)
            ->get()
            ->row_array();
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

    private function allowed_destinations_for_division(int $divisionId): array
    {
        $base = $this->infer_destination_from_division($divisionId);
        if ($base === 'BAR') {
            return ['BAR', 'BAR_EVENT'];
        }
        if ($base === 'KITCHEN') {
            return ['KITCHEN', 'KITCHEN_EVENT'];
        }
        if ($base === 'OFFICE') {
            return ['OFFICE'];
        }
        return ['OTHER'];
    }

    private function is_destination_allowed_for_division(int $divisionId, string $destinationType): bool
    {
        $destinationType = strtoupper(trim($destinationType));
        if ($divisionId <= 0 || $destinationType === '') {
            return false;
        }
        return in_array($destinationType, $this->allowed_destinations_for_division($divisionId), true);
    }

    private function resolve_division_request_destination_type(int $divisionId, string $requestedDestination): ?string
    {
        if ($divisionId <= 0) {
            return null;
        }

        $allowed = $this->allowed_destinations_for_division($divisionId);
        if (empty($allowed)) {
            return null;
        }

        $requestedDestination = strtoupper(trim($requestedDestination));
        if ($requestedDestination === '') {
            return (string)($allowed[0] ?? null);
        }

        return in_array($requestedDestination, $allowed, true)
            ? $requestedDestination
            : null;
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
        $hasWarehouseMaterial = $this->db->field_exists('material_id', 'inv_warehouse_stock_balance');

        if ($hasWarehouseMaterial && $materialId !== null && $materialId > 0) {
            $sql .= ' AND material_id = ?';
            $params[] = $materialId;
        } elseif ($itemId !== null && $itemId > 0) {
            $sql .= ' AND item_id = ?';
            $params[] = $itemId;
        } else {
            return 0.0;
        }
        $sql .= ' LIMIT 1';

        $row = $this->db->query($sql, $params)->row_array();
        return round((float)($row['qty_content_balance'] ?? 0), 4);
    }

    private function reverse_fulfillments_before_void(int $requestId, string $notes, int $userId): array
    {
        if (!$this->db->table_exists('pur_store_request_fulfillment') || !$this->db->table_exists('pur_store_request_fulfillment_line')) {
            return ['ok' => false, 'message' => 'Schema fulfillment belum tersedia, tidak bisa void SR fulfilled.'];
        }

        $header = $this->db
            ->select('id, sr_no, request_division_id, destination_type')
            ->from('pur_store_request')
            ->where('id', $requestId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$header) {
            return ['ok' => false, 'message' => 'Store Request tidak ditemukan saat proses void fulfillment.'];
        }

        $fulfillments = $this->db
            ->from('pur_store_request_fulfillment')
            ->where('store_request_id', $requestId)
            ->where('status', 'POSTED')
            ->order_by('id', 'DESC')
            ->get()
            ->result_array();
        if (empty($fulfillments)) {
            return ['ok' => true];
        }

        return $this->purge_fulfillment_movements_and_rebuild($header, $fulfillments, $userId, $notes);
    }

    private function purge_fulfillment_movements_and_rebuild(array $header, array $fulfillments, int $userId, string $notes = ''): array
    {
        $requestId = (int)($header['id'] ?? 0);
        if ($requestId <= 0) {
            return ['ok' => false, 'message' => 'Store Request tidak valid untuk rollback fulfillment.'];
        }

        $this->load->model('Purchase_model');
        $this->load->library('MaterialFifoManager');
        $fifoReady = $this->materialfifomanager->ensureReady();
        if (!($fifoReady['ok'] ?? false)) {
            return $fifoReady;
        }

        $srNo = (string)($header['sr_no'] ?? ('SR#' . $requestId));
        $divisionId = (int)($header['request_division_id'] ?? 0);
        $destinationType = $this->normalize_destination((string)($header['destination_type'] ?? 'OTHER')) ?? 'OTHER';
        $voidNote = trim((string)$notes);
        if ($voidNote === '') {
            $voidNote = 'Void Store Request ' . $srNo;
        }

        $rebuildTargets = [];
        foreach ($fulfillments as $fulfillment) {
            $fulfillmentId = (int)($fulfillment['id'] ?? 0);
            if ($fulfillmentId <= 0) {
                continue;
            }

            $fulfillmentDate = $this->normalize_date((string)($fulfillment['fulfillment_date'] ?? ''));
            if ($fulfillmentDate === null) {
                $fulfillmentDate = date('Y-m-d');
            }
            $rebuildStartDate = date('Y-m-01', strtotime($fulfillmentDate));

            $lines = $this->db
                ->from('pur_store_request_fulfillment_line')
                ->where('fulfillment_id', $fulfillmentId)
                ->order_by('id', 'ASC')
                ->get()
                ->result_array();

            $fifoRollback = $this->materialfifomanager->rollbackTransferLotsBySource(
                'pur_store_request_fulfillment',
                $fulfillmentId,
                null,
                $voidNote
            );
            if (!($fifoRollback['ok'] ?? false)) {
                return $fifoRollback;
            }

            foreach ($lines as $line) {
                $qtyBuy = round((float)($line['qty_buy_posted'] ?? 0), 4);
                $qtyContent = round((float)($line['qty_content_posted'] ?? 0), 4);
                if ($qtyBuy <= 0 && $qtyContent <= 0) {
                    continue;
                }

                $stockDomain = $this->resolve_line_stock_domain($line);
                $baseIdentity = [
                    'stock_domain' => $stockDomain,
                    'item_id' => (int)($line['item_id'] ?? 0),
                    'material_id' => $this->nullable_int($line['material_id'] ?? null),
                    'buy_uom_id' => $this->nullable_int($line['buy_uom_id'] ?? null),
                    'content_uom_id' => (int)($line['content_uom_id'] ?? 0),
                    'profile_key' => $this->nullable_string($line['profile_key'] ?? null),
                    'profile_name' => $this->nullable_string($line['profile_name'] ?? null),
                    'profile_brand' => $this->nullable_string($line['profile_brand'] ?? null),
                    'profile_description' => $this->nullable_string($line['profile_description'] ?? null),
                    'profile_expired_date' => $this->normalize_date((string)($line['profile_expired_date'] ?? '')),
                    'profile_content_per_buy' => round((float)($line['profile_content_per_buy'] ?? 1), 6),
                    'profile_buy_uom_code' => $this->nullable_string($line['profile_buy_uom_code'] ?? null),
                    'profile_content_uom_code' => $this->nullable_string($line['profile_content_uom_code'] ?? null),
                ];

                $this->register_inventory_rebuild_target($rebuildTargets, 'WAREHOUSE', $rebuildStartDate, $baseIdentity);
                $divisionIdentity = $baseIdentity;
                $divisionIdentity['division_id'] = $divisionId;
                $divisionIdentity['destination_type'] = $destinationType;
                $this->register_inventory_rebuild_target($rebuildTargets, 'DIVISION', $rebuildStartDate, $divisionIdentity);

                $srLineId = (int)($line['store_request_line_id'] ?? 0);
                if ($srLineId > 0) {
                    $srLine = $this->db->query('SELECT * FROM pur_store_request_line WHERE id = ? LIMIT 1 FOR UPDATE', [$srLineId])->row_array();
                    if ($srLine) {
                        $newFulfilledContent = round(max(0, (float)($srLine['qty_content_fulfilled'] ?? 0) - $qtyContent), 4);
                        $newFulfilledBuy = round(max(0, (float)($srLine['qty_buy_fulfilled'] ?? 0) - $qtyBuy), 4);
                        $requestedContent = round((float)($srLine['qty_content_requested'] ?? 0), 4);
                        $lineStatus = 'OPEN';
                        if ($newFulfilledContent > 0 && $newFulfilledContent + 0.0001 < $requestedContent) {
                            $lineStatus = 'PARTIAL';
                        } elseif ($requestedContent > 0 && $newFulfilledContent + 0.0001 >= $requestedContent) {
                            $lineStatus = 'DONE';
                        }

                        $this->db
                            ->where('id', $srLineId)
                            ->update('pur_store_request_line', [
                                'qty_content_fulfilled' => $newFulfilledContent,
                                'qty_buy_fulfilled' => $newFulfilledBuy,
                                'line_status' => $lineStatus,
                                'updated_at' => date('Y-m-d H:i:s'),
                            ]);
                    }
                }
            }

            $this->db
                ->where('ref_table', 'pur_store_request_fulfillment')
                ->where('ref_id', $fulfillmentId)
                ->delete('inv_stock_movement_log');

            $this->db
                ->where('id', $fulfillmentId)
                ->update('pur_store_request_fulfillment', [
                    'status' => 'VOID',
                    'voided_by' => $userId > 0 ? $userId : null,
                    'voided_at' => date('Y-m-d H:i:s'),
                    'void_reason' => $voidNote,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }

        foreach ($rebuildTargets as $target) {
            $rebuild = $this->Purchase_model->rebuild_inventory_history_for_identity(
                (string)$target['scope'],
                (string)$target['start_date'],
                (array)$target['identity']
            );
            if (!($rebuild['ok'] ?? false)) {
                $message = (string)($rebuild['message'] ?? 'Gagal rebuild histori stok setelah purge fulfillment.');
                if (!empty($rebuild['data']['negative_samples']) && is_array($rebuild['data']['negative_samples'])) {
                    $message .= ' Contoh: ' . implode('; ', array_slice($rebuild['data']['negative_samples'], 0, 3));
                }
                return [
                    'ok' => false,
                    'message' => $message,
                ];
            }
        }

        return [
            'ok' => true,
            'message' => 'Fulfillment SR berhasil dihapus dari histori stok dan direbuild ulang.',
            'data' => [
                'rebuild_targets' => count($rebuildTargets),
            ],
        ];
    }

    private function register_inventory_rebuild_target(array &$targets, string $scope, string $startDate, array $identity): void
    {
        $scope = strtoupper(trim($scope));
        $normalizedDate = $this->normalize_date($startDate);
        if ($normalizedDate === null) {
            $normalizedDate = date('Y-m-01');
        }

        $keyParts = [
            $scope,
            strtoupper((string)($identity['stock_domain'] ?? 'ITEM')),
            (int)($identity['division_id'] ?? 0),
            strtoupper((string)($identity['destination_type'] ?? 'OTHER')),
            (int)($identity['item_id'] ?? 0),
            (int)($identity['material_id'] ?? 0),
            (int)($identity['buy_uom_id'] ?? 0),
            (int)($identity['content_uom_id'] ?? 0),
            (string)($identity['profile_key'] ?? ''),
        ];
        $key = implode('|', $keyParts);

        if (!isset($targets[$key])) {
            $targets[$key] = [
                'scope' => $scope,
                'start_date' => $normalizedDate,
                'identity' => $identity,
            ];
            return;
        }

        if ($normalizedDate < (string)$targets[$key]['start_date']) {
            $targets[$key]['start_date'] = $normalizedDate;
        }
    }

    private function resolve_line_stock_domain(array $line): string
    {
        $lineKind = strtoupper(trim((string)($line['line_kind'] ?? '')));
        if (in_array($lineKind, ['ITEM', 'MATERIAL'], true)) {
            return $lineKind;
        }
        return (int)($line['material_id'] ?? 0) > 0 ? 'MATERIAL' : 'ITEM';
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
