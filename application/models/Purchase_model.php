<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Purchase_model extends CI_Model
{
    private $uomCodeCache = [];

    public function get_dashboard_summary(): array
    {
        $summary = [
            'accounts_total' => 0,
            'accounts_active' => 0,
            'accounts_balance' => 0.0,
            'warehouse_profiles' => 0,
            'warehouse_qty_content' => 0.0,
            'division_profiles' => 0,
            'division_qty_content' => 0.0,
            'po_total' => 0,
            'po_open' => 0,
            'receipt_posted' => 0,
        ];

        if ($this->db->table_exists('fin_company_account')) {
            $row = $this->db
                ->select('COUNT(*) AS total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_total, SUM(current_balance) AS balance_total', false)
                ->from('fin_company_account')
                ->get()
                ->row_array();
            $summary['accounts_total'] = (int)($row['total'] ?? 0);
            $summary['accounts_active'] = (int)($row['active_total'] ?? 0);
            $summary['accounts_balance'] = (float)($row['balance_total'] ?? 0);
        }

        if ($this->db->table_exists('inv_warehouse_stock_balance')) {
            $row = $this->db
                ->select('COUNT(*) AS total, SUM(qty_content_balance) AS qty_total', false)
                ->from('inv_warehouse_stock_balance')
                ->get()
                ->row_array();
            $summary['warehouse_profiles'] = (int)($row['total'] ?? 0);
            $summary['warehouse_qty_content'] = (float)($row['qty_total'] ?? 0);
        }

        if ($this->db->table_exists('inv_division_stock_balance')) {
            $row = $this->db
                ->select('COUNT(*) AS total, SUM(qty_content_balance) AS qty_total', false)
                ->from('inv_division_stock_balance')
                ->get()
                ->row_array();
            $summary['division_profiles'] = (int)($row['total'] ?? 0);
            $summary['division_qty_content'] = (float)($row['qty_total'] ?? 0);
        }

        if ($this->db->table_exists('pur_purchase_order')) {
            $row = $this->db
                ->select("COUNT(*) AS total, SUM(CASE WHEN status IN ('DRAFT','APPROVED','ORDERED','PARTIAL_RECEIVED','RECEIVED') THEN 1 ELSE 0 END) AS open_total", false)
                ->from('pur_purchase_order')
                ->get()
                ->row_array();
            $summary['po_total'] = (int)($row['total'] ?? 0);
            $summary['po_open'] = (int)($row['open_total'] ?? 0);
        }

        if ($this->db->table_exists('pur_purchase_receipt')) {
            $row = $this->db
                ->select("SUM(CASE WHEN status = 'POSTED' THEN 1 ELSE 0 END) AS posted_total", false)
                ->from('pur_purchase_receipt')
                ->get()
                ->row_array();
            $summary['receipt_posted'] = (int)($row['posted_total'] ?? 0);
        }

        return $summary;
    }

    public function list_company_accounts(string $q, int $limit): array
    {
        if (!$this->db->table_exists('fin_company_account')) {
            return [];
        }

        $this->db
            ->select('id, account_code, account_name, account_type, bank_name, account_no, currency_code, current_balance, is_default, is_active, updated_at')
            ->from('fin_company_account');

        if ($q !== '') {
            $this->db->group_start()
                ->like('account_code', $q)
                ->or_like('account_name', $q)
                ->or_like('bank_name', $q)
                ->or_like('account_no', $q)
                ->group_end();
        }

        $this->db
            ->order_by('is_default', 'DESC')
            ->order_by('account_name', 'ASC')
            ->limit($limit);

        $query = $this->db->get();
        if (!$query) {
            return [];
        }

        return $query->result_array();
    }

    public function list_active_company_accounts(): array
    {
        if (!$this->db->table_exists('fin_company_account')) {
            return [];
        }

        return $this->db
            ->select('id, account_code, account_name, current_balance, currency_code')
            ->from('fin_company_account')
            ->where('is_active', 1)
            ->order_by('is_default', 'DESC')
            ->order_by('account_name', 'ASC')
            ->get()
            ->result_array();
    }

    public function list_account_mutations(int $accountId, string $dateFrom, string $dateTo, int $limit): array
    {
        if (!$this->db->table_exists('fin_account_mutation_log')) {
            return [];
        }

        $this->db
            ->select('m.id, m.mutation_no, m.mutation_date, m.account_id, m.mutation_type, m.amount, m.balance_before, m.balance_after')
            ->select('m.ref_module, m.ref_table, m.ref_id, m.ref_no, m.notes, m.created_at, a.account_code, a.account_name')
            ->from('fin_account_mutation_log m')
            ->join('fin_company_account a', 'a.id = m.account_id', 'left');

        if ($accountId > 0) {
            $this->db->where('m.account_id', $accountId);
        }

        $from = $this->normalizeDate($dateFrom);
        $to = $this->normalizeDate($dateTo);
        if ($from !== null) {
            $this->db->where('m.mutation_date >=', $from);
        }
        if ($to !== null) {
            $this->db->where('m.mutation_date <=', $to);
        }

        return $this->db
            ->order_by('m.mutation_date', 'DESC')
            ->order_by('m.id', 'DESC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    public function get_account_mutation_summary(int $accountId, string $dateFrom, string $dateTo): array
    {
        $summary = [
            'in_total' => 0.0,
            'out_total' => 0.0,
            'rows_total' => 0,
        ];

        if (!$this->db->table_exists('fin_account_mutation_log')) {
            return $summary;
        }

        $this->db
            ->select('COUNT(*) AS rows_total', false)
            ->select("SUM(CASE WHEN mutation_type = 'IN' THEN amount ELSE 0 END) AS in_total", false)
            ->select("SUM(CASE WHEN mutation_type = 'OUT' THEN amount ELSE 0 END) AS out_total", false)
            ->from('fin_account_mutation_log');

        if ($accountId > 0) {
            $this->db->where('account_id', $accountId);
        }

        $from = $this->normalizeDate($dateFrom);
        $to = $this->normalizeDate($dateTo);
        if ($from !== null) {
            $this->db->where('mutation_date >=', $from);
        }
        if ($to !== null) {
            $this->db->where('mutation_date <=', $to);
        }

        $row = $this->db->get()->row_array();
        if (!$row) {
            return $summary;
        }

        $summary['in_total'] = (float)($row['in_total'] ?? 0);
        $summary['out_total'] = (float)($row['out_total'] ?? 0);
        $summary['rows_total'] = (int)($row['rows_total'] ?? 0);

        return $summary;
    }

    public function apply_manual_account_mutation(array $payload, int $userId, string $sourceIp = ''): array
    {
        if (!$this->db->table_exists('fin_company_account') || !$this->db->table_exists('fin_account_mutation_log')) {
            return [
                'ok' => false,
                'message' => 'Tabel finance mutation belum tersedia. Jalankan SQL terbaru terlebih dahulu.',
            ];
        }

        $accountId = (int)($payload['account_id'] ?? 0);
        $mutationType = strtoupper(trim((string)($payload['mutation_type'] ?? '')));
        $amount = round((float)($payload['amount'] ?? 0), 2);
        $mutationDate = $this->normalizeDate((string)($payload['mutation_date'] ?? date('Y-m-d')));

        if ($accountId <= 0 || !in_array($mutationType, ['IN', 'OUT'], true) || $amount <= 0 || $mutationDate === null) {
            return [
                'ok' => false,
                'message' => 'account_id, mutation_type(IN/OUT), amount, mutation_date wajib valid.',
            ];
        }

        $this->db->trans_begin();

        $account = $this->db
            ->query('SELECT * FROM fin_company_account WHERE id = ? AND is_active = 1 LIMIT 1 FOR UPDATE', [$accountId])
            ->row_array();

        if (!$account) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => 'Akun rekening tidak ditemukan atau tidak aktif.',
            ];
        }

        $balanceBefore = (float)($account['current_balance'] ?? 0);
        $balanceAfter = $mutationType === 'IN'
            ? round($balanceBefore + $amount, 2)
            : round($balanceBefore - $amount, 2);

        if ($balanceAfter < 0) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => 'Saldo rekening tidak cukup untuk mutasi OUT.',
            ];
        }

        $this->db->where('id', $accountId)->update('fin_company_account', [
            'current_balance' => $balanceAfter,
        ]);

        $mutationNo = $this->generateAccountMutationNo($mutationDate);
        $this->db->insert('fin_account_mutation_log', [
            'mutation_no' => $mutationNo,
            'mutation_date' => $mutationDate,
            'account_id' => $accountId,
            'mutation_type' => $mutationType,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'ref_module' => 'FINANCE',
            'ref_table' => null,
            'ref_id' => null,
            'ref_no' => $this->nullableString($payload['reference_no'] ?? null),
            'notes' => $this->nullableString($payload['notes'] ?? null),
            'created_by' => $userId > 0 ? $userId : null,
        ]);
        $mutationId = (int)$this->db->insert_id();

        if ($this->db->table_exists('aud_transaction_log')) {
            $this->db->insert('aud_transaction_log', [
                'module_code' => 'FINANCE',
                'action_code' => 'ACCOUNT_MUTATION',
                'entity_table' => 'fin_account_mutation_log',
                'entity_id' => $mutationId > 0 ? $mutationId : null,
                'transaction_no' => $mutationNo,
                'actor_user_id' => $userId > 0 ? $userId : null,
                'source_ip' => $sourceIp !== '' ? $sourceIp : null,
                'after_payload' => json_encode([
                    'account_id' => $accountId,
                    'mutation_type' => $mutationType,
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'mutation_date' => $mutationDate,
                ]),
                'notes' => 'Mutasi rekening manual',
            ]);
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => 'Gagal menyimpan mutasi rekening.',
            ];
        }

        $this->db->trans_commit();

        return [
            'ok' => true,
            'message' => 'Mutasi rekening berhasil diposting.',
            'data' => [
                'mutation_id' => $mutationId,
                'mutation_no' => $mutationNo,
                'account_id' => $accountId,
                'mutation_type' => $mutationType,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
            ],
        ];
    }

    public function list_opening_items(): array
    {
        if (!$this->db->table_exists('mst_item')) {
            return [];
        }

        return $this->db
            ->select('id, item_code, item_name')
            ->from('mst_item')
            ->where('is_active', 1)
            ->order_by('item_name', 'ASC')
            ->limit(500)
            ->get()
            ->result_array();
    }

    public function list_active_uoms(): array
    {
        if (!$this->db->table_exists('mst_uom')) {
            return [];
        }

        return $this->db
            ->select('id, code, name')
            ->from('mst_uom')
            ->where('is_active', 1)
            ->order_by('name', 'ASC')
            ->limit(500)
            ->get()
            ->result_array();
    }

    public function list_warehouse_opening_snapshots(string $month, string $q, int $limit): array
    {
        if (!$this->db->table_exists('inv_stock_opening_snapshot')) {
            return [];
        }

        $monthKey = $this->normalizeMonth($month);

        $this->db
            ->select('s.id, s.snapshot_month, s.item_id, s.buy_uom_id, s.content_uom_id, s.profile_name, s.profile_brand, s.profile_description')
            ->select('s.profile_content_per_buy, s.profile_buy_uom_code, s.profile_content_uom_code')
            ->select('s.opening_qty_buy, s.opening_qty_content, s.opening_avg_cost_per_content, s.opening_total_value, s.source_type, s.updated_at')
            ->select('i.item_code, i.item_name')
            ->from('inv_stock_opening_snapshot s')
            ->join('mst_item i', 'i.id = s.item_id', 'left')
            ->where('s.stock_scope', 'WAREHOUSE');

        if ($monthKey !== null) {
            $this->db->where('s.snapshot_month', $monthKey);
        }

        if ($q !== '') {
            $this->db->group_start()
                ->like('i.item_code', $q)
                ->or_like('i.item_name', $q)
                ->or_like('s.profile_name', $q)
                ->or_like('s.profile_brand', $q)
                ->or_like('s.profile_description', $q)
                ->or_like('s.profile_key', $q)
                ->group_end();
        }

        return $this->db
            ->order_by('s.snapshot_month', 'DESC')
            ->order_by('i.item_name', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    public function list_warehouse_daily_rollup(string $month, string $q, string $dateFrom, string $dateTo, int $limit): array
    {
        if (!$this->db->table_exists('inv_warehouse_daily_rollup')) {
            return [];
        }

        $monthKey = $this->normalizeMonth($month);
        $from = $this->normalizeDate($dateFrom);
        $to = $this->normalizeDate($dateTo);

        $this->db
            ->select('d.id, d.movement_date, d.item_id, d.profile_name, d.profile_brand, d.profile_description')
            ->select('d.profile_content_per_buy, d.profile_buy_uom_code, d.profile_content_uom_code')
            ->select('d.opening_qty_content, d.in_qty_content, d.out_qty_content, d.adjustment_qty_content, d.closing_qty_content')
            ->select('d.avg_cost_per_content, d.total_value, d.mutation_count')
            ->select('i.item_code, i.item_name')
            ->from('inv_warehouse_daily_rollup d')
            ->join('mst_item i', 'i.id = d.item_id', 'left');

        if ($monthKey !== null) {
            $this->db->where('d.month_key', $monthKey);
        }
        if ($from !== null) {
            $this->db->where('d.movement_date >=', $from);
        }
        if ($to !== null) {
            $this->db->where('d.movement_date <=', $to);
        }

        if ($q !== '') {
            $this->db->group_start()
                ->like('i.item_code', $q)
                ->or_like('i.item_name', $q)
                ->or_like('d.profile_name', $q)
                ->or_like('d.profile_brand', $q)
                ->or_like('d.profile_description', $q)
                ->group_end();
        }

        return $this->db
            ->order_by('d.movement_date', 'DESC')
            ->order_by('i.item_name', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    public function list_division_daily_rollup(string $month, string $q, ?int $divisionId, string $dateFrom, string $dateTo, int $limit): array
    {
        if (!$this->db->table_exists('inv_division_daily_rollup')) {
            return [];
        }

        $monthKey = $this->normalizeMonth($month);
        $from = $this->normalizeDate($dateFrom);
        $to = $this->normalizeDate($dateTo);

        $hasDivisionCode = $this->db->field_exists('division_code', 'mst_operational_division');
        $hasDivisionName = $this->db->field_exists('division_name', 'mst_operational_division');
        $divisionCodeSelect = $hasDivisionCode ? 'dv.division_code' : 'CAST(d.division_id AS CHAR) AS division_code';
        $divisionNameSelect = $hasDivisionName ? 'dv.division_name' : 'NULL AS division_name';

        $this->db
            ->select('d.id, d.month_key, d.movement_date, d.division_id, ' . $divisionCodeSelect . ', ' . $divisionNameSelect, false)
            ->select('d.stock_domain, d.item_id, d.material_id, d.profile_name, d.profile_brand, d.profile_description')
            ->select('d.profile_content_per_buy, d.profile_buy_uom_code, d.profile_content_uom_code')
            ->select('d.opening_qty_content, d.in_qty_content, d.out_qty_content, d.adjustment_qty_content, d.closing_qty_content')
            ->select('d.avg_cost_per_content, d.total_value, d.mutation_count')
            ->select('i.item_code, i.item_name, m.material_code, m.material_name')
            ->from('inv_division_daily_rollup d')
            ->join('mst_operational_division dv', 'dv.id = d.division_id', 'left')
            ->join('mst_item i', 'i.id = d.item_id', 'left')
            ->join('mst_material m', 'm.id = d.material_id', 'left');

        if ($monthKey !== null) {
            $this->db->where('d.month_key', $monthKey);
        }
        if ($divisionId !== null && $divisionId > 0) {
            $this->db->where('d.division_id', $divisionId);
        }
        if ($from !== null) {
            $this->db->where('d.movement_date >=', $from);
        }
        if ($to !== null) {
            $this->db->where('d.movement_date <=', $to);
        }

        if ($q !== '') {
            $this->db->group_start();
            if ($hasDivisionCode) {
                $this->db->like('dv.division_code', $q);
                if ($hasDivisionName) {
                    $this->db->or_like('dv.division_name', $q);
                }
            } elseif ($hasDivisionName) {
                $this->db->like('dv.division_name', $q);
            }
            $this->db->or_like('i.item_code', $q)
                ->or_like('i.item_name', $q)
                ->or_like('m.material_code', $q)
                ->or_like('m.material_name', $q)
                ->or_like('d.profile_name', $q)
                ->or_like('d.profile_brand', $q)
                ->or_like('d.profile_description', $q)
                ->group_end();
        }

        return $this->db
            ->order_by('d.movement_date', 'DESC')
            ->order_by($hasDivisionName ? 'dv.division_name' : 'd.division_id', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    public function list_stock_movements(string $scope, string $q, string $dateFrom, string $dateTo, ?int $divisionId, int $limit): array
    {
        if (!$this->db->table_exists('inv_stock_movement_log')) {
            return [];
        }

        $scope = strtoupper(trim($scope));
        if (!in_array($scope, ['WAREHOUSE', 'DIVISION'], true)) {
            $scope = 'WAREHOUSE';
        }

        $from = $this->normalizeDate($dateFrom);
        $to = $this->normalizeDate($dateTo);

        $hasDivisionCode = $this->db->field_exists('division_code', 'mst_operational_division');
        $hasDivisionName = $this->db->field_exists('division_name', 'mst_operational_division');
        $divisionCodeSelect = $hasDivisionCode ? 'dv.division_code' : 'CAST(l.division_id AS CHAR) AS division_code';
        $divisionNameSelect = $hasDivisionName ? 'dv.division_name' : 'NULL AS division_name';

        $this->db
            ->select('l.id, l.movement_no, l.movement_date, l.movement_scope, l.division_id, ' . $divisionCodeSelect . ', ' . $divisionNameSelect, false)
            ->select('l.movement_type, l.item_id, l.material_id, l.profile_name, l.profile_brand, l.profile_description')
            ->select('l.profile_content_per_buy, l.profile_buy_uom_code, l.profile_content_uom_code')
            ->select('l.qty_buy_delta, l.qty_content_delta, l.qty_buy_after, l.qty_content_after, l.unit_cost')
            ->select('l.ref_table, l.ref_id, l.receipt_id, l.receipt_line_id, l.notes, l.created_at')
            ->select('i.item_code, i.item_name, m.material_code, m.material_name')
            ->from('inv_stock_movement_log l')
            ->join('mst_operational_division dv', 'dv.id = l.division_id', 'left')
            ->join('mst_item i', 'i.id = l.item_id', 'left')
            ->join('mst_material m', 'm.id = l.material_id', 'left')
            ->where('l.movement_scope', $scope);

        if ($scope === 'DIVISION' && $divisionId !== null && $divisionId > 0) {
            $this->db->where('l.division_id', $divisionId);
        }
        if ($from !== null) {
            $this->db->where('l.movement_date >=', $from);
        }
        if ($to !== null) {
            $this->db->where('l.movement_date <=', $to);
        }

        if ($q !== '') {
            $this->db->group_start();
            if ($hasDivisionCode) {
                $this->db->like('dv.division_code', $q);
                if ($hasDivisionName) {
                    $this->db->or_like('dv.division_name', $q);
                }
            } elseif ($hasDivisionName) {
                $this->db->like('dv.division_name', $q);
            }
            $this->db->or_like('l.movement_no', $q)
                ->or_like('l.movement_type', $q)
                ->or_like('i.item_code', $q)
                ->or_like('i.item_name', $q)
                ->or_like('m.material_code', $q)
                ->or_like('m.material_name', $q)
                ->or_like('l.profile_name', $q)
                ->or_like('l.profile_brand', $q)
                ->or_like('l.profile_description', $q)
                ->group_end();
        }

        return $this->db
            ->order_by('l.movement_date', 'DESC')
            ->order_by('l.id', 'DESC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    public function sync_purchase_setup_from_core(int $limit = 2000): array
    {
        if (!$this->db->table_exists('mst_posting_type') || !$this->db->table_exists('mst_purchase_type')) {
            return [
                'ok' => false,
                'message' => 'Tabel master posting/purchase type belum tersedia.',
            ];
        }

        $limit = max(1, min(5000, $limit));

        $corePostingExists = $this->db->query("SHOW TABLES FROM core LIKE 'm_posting_type'")->num_rows() > 0;
        $corePurchaseExists = $this->db->query("SHOW TABLES FROM core LIKE 'm_purchase_type'")->num_rows() > 0;
        if (!$corePostingExists || !$corePurchaseExists) {
            return [
                'ok' => false,
                'message' => 'Tabel core.m_posting_type atau core.m_purchase_type tidak ditemukan.',
            ];
        }

        $hasCorePostingDescription = $this->coreColumnExists('m_posting_type', 'description');
        $hasCorePostingIsActive = $this->coreColumnExists('m_posting_type', 'is_active');
        $hasCorePurchasePostingId = $this->coreColumnExists('m_purchase_type', 'posting_type_id');
        $hasCorePurchasePostingCode = $this->coreColumnExists('m_purchase_type', 'posting_type');
        $hasCorePurchaseDestBehavior = $this->coreColumnExists('m_purchase_type', 'destination_behavior');
        $hasCorePurchaseDefaultDest = $this->coreColumnExists('m_purchase_type', 'default_destination');
        $hasCorePurchaseSortOrder = $this->coreColumnExists('m_purchase_type', 'sort_order');
        $hasCorePurchaseNotes = $this->coreColumnExists('m_purchase_type', 'notes');
        $hasCorePurchaseIsActive = $this->coreColumnExists('m_purchase_type', 'is_active');

        $postingDescriptionExpr = $hasCorePostingDescription ? 'cp.description' : 'NULL';
        $postingActiveExpr = $hasCorePostingIsActive ? 'cp.is_active' : '1';
        $purchaseDestBehaviorExpr = $hasCorePurchaseDestBehavior ? 'ct.destination_behavior' : "'REQUIRED'";
        $purchaseDefaultDestExpr = $hasCorePurchaseDefaultDest ? 'ct.default_destination' : "''";
        $purchaseSortOrderExpr = $hasCorePurchaseSortOrder ? 'ct.sort_order' : 'ct.id';
        $purchaseNotesExpr = $hasCorePurchaseNotes ? 'ct.notes' : 'NULL';
        $purchaseActiveExpr = $hasCorePurchaseIsActive ? 'ct.is_active' : '1';

        $postingJoinExpr = 'NULL';
        if ($hasCorePurchasePostingId) {
            $postingJoinExpr = 'cp.id = ct.posting_type_id';
            if ($hasCorePurchasePostingCode) {
                $postingJoinExpr .= ' OR cp.posting_code = ct.posting_type';
            }
        } elseif ($hasCorePurchasePostingCode) {
            $postingJoinExpr = 'cp.posting_code = ct.posting_type';
        }

        $this->db->query("\n            INSERT INTO mst_posting_type (\n                type_code, type_name, affects_inventory, affects_service, affects_asset, affects_payroll, affects_expense, sort_order, notes, is_active\n            )\n            SELECT\n                UPPER(TRIM(cp.posting_code)) AS type_code,\n                cp.posting_name AS type_name,\n                CASE WHEN UPPER(TRIM(cp.posting_code)) = 'INVENTORY' THEN 1 ELSE 0 END AS affects_inventory,\n                CASE WHEN UPPER(TRIM(cp.posting_code)) = 'SERVICE' THEN 1 ELSE 0 END AS affects_service,\n                CASE WHEN UPPER(TRIM(cp.posting_code)) = 'ASSET' THEN 1 ELSE 0 END AS affects_asset,\n                CASE WHEN UPPER(TRIM(cp.posting_code)) = 'PAYROLL' THEN 1 ELSE 0 END AS affects_payroll,\n                CASE WHEN UPPER(TRIM(cp.posting_code)) IN ('EXPENSE','OPEX') THEN 1 ELSE 0 END AS affects_expense,\n                cp.id AS sort_order,\n                {$postingDescriptionExpr} AS notes,\n                {$postingActiveExpr} AS is_active\n            FROM core.m_posting_type cp\n            WHERE cp.posting_code IS NOT NULL AND cp.posting_code <> ''\n            ORDER BY cp.id ASC\n            LIMIT {$limit}\n            ON DUPLICATE KEY UPDATE\n                type_name = VALUES(type_name),\n                affects_inventory = VALUES(affects_inventory),\n                affects_service = VALUES(affects_service),\n                affects_asset = VALUES(affects_asset),\n                affects_payroll = VALUES(affects_payroll),\n                affects_expense = VALUES(affects_expense),\n                sort_order = VALUES(sort_order),\n                notes = VALUES(notes),\n                is_active = VALUES(is_active),\n                updated_at = CURRENT_TIMESTAMP\n        ");

        $this->db->query("\n            INSERT INTO mst_purchase_type (\n                type_code, type_name, posting_type_id, destination_behavior, default_destination, sort_order, notes, is_active\n            )\n            SELECT\n                UPPER(TRIM(ct.type_code)) AS type_code,\n                ct.type_name AS type_name,\n                pt.id AS posting_type_id,\n                CASE WHEN UPPER(TRIM(COALESCE({$purchaseDestBehaviorExpr}, 'REQUIRED'))) = 'NONE' THEN 'NONE' ELSE 'REQUIRED' END AS destination_behavior,\n                CASE\n                    WHEN UPPER(TRIM(COALESCE({$purchaseDefaultDestExpr}, ''))) IN ('GUDANG','BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER')\n                        THEN UPPER(TRIM({$purchaseDefaultDestExpr}))\n                    ELSE NULL\n                END AS default_destination,\n                {$purchaseSortOrderExpr} AS sort_order,\n                {$purchaseNotesExpr} AS notes,\n                {$purchaseActiveExpr} AS is_active\n            FROM core.m_purchase_type ct\n            LEFT JOIN core.m_posting_type cp ON ({$postingJoinExpr})\n            JOIN mst_posting_type pt ON pt.type_code = UPPER(TRIM(cp.posting_code))\n            WHERE ct.type_code IS NOT NULL AND ct.type_code <> ''\n            ORDER BY ct.id ASC\n            LIMIT {$limit}\n            ON DUPLICATE KEY UPDATE\n                type_name = VALUES(type_name),\n                posting_type_id = VALUES(posting_type_id),\n                destination_behavior = VALUES(destination_behavior),\n                default_destination = VALUES(default_destination),\n                sort_order = VALUES(sort_order),\n                notes = VALUES(notes),\n                is_active = VALUES(is_active),\n                updated_at = CURRENT_TIMESTAMP\n        ");

        if ($this->db->error()['code']) {
            return [
                'ok' => false,
                'message' => 'Gagal sinkron posting/purchase type: ' . (string)$this->db->error()['message'],
            ];
        }

        $postingTotal = (int)$this->db->count_all('mst_posting_type');
        $purchaseTypeTotal = (int)$this->db->count_all('mst_purchase_type');

        return [
            'ok' => true,
            'message' => 'Sinkron posting type dan purchase type dari core berhasil.',
            'data' => [
                'limit' => $limit,
                'posting_type_total' => $postingTotal,
                'purchase_type_total' => $purchaseTypeTotal,
            ],
        ];
    }

    public function sync_purchase_master_data_from_core(int $limit = 2000): array
    {
        $setup = $this->sync_purchase_setup_from_core($limit);
        if (!($setup['ok'] ?? false)) {
            return $setup;
        }

        $catalog = $this->sync_catalog_from_core($limit);
        if (!($catalog['ok'] ?? false)) {
            return $catalog;
        }

        return [
            'ok' => true,
            'message' => 'Sinkron master purchase dari core berhasil (posting type, purchase type, catalog).',
            'data' => [
                'setup' => $setup['data'] ?? [],
                'catalog' => $catalog['data'] ?? [],
            ],
        ];
    }

    public function store_warehouse_opening_and_post(array $payload, int $userId, string $sourceIp = ''): array
    {
        if (!$this->db->table_exists('inv_stock_opening_snapshot')) {
            return [
                'ok' => false,
                'message' => 'Tabel opening snapshot belum tersedia. Jalankan SQL 2026-05-03j terlebih dahulu.',
            ];
        }

        $itemId = (int)($payload['item_id'] ?? 0);
        $buyUomId = (int)($payload['buy_uom_id'] ?? 0);
        $contentUomId = (int)($payload['content_uom_id'] ?? 0);
        $snapshotMonth = $this->normalizeMonth((string)($payload['snapshot_month'] ?? date('Y-m-01')));
        $movementDate = $this->normalizeDate((string)($payload['movement_date'] ?? date('Y-m-d')));
        $targetQtyBuy = round((float)($payload['opening_qty_buy'] ?? 0), 4);
        $targetQtyContent = round((float)($payload['opening_qty_content'] ?? 0), 4);
        $avgCost = round(max(0, (float)($payload['opening_avg_cost_per_content'] ?? 0)), 6);
        $replaceMode = (int)($payload['replace_mode'] ?? 1) === 1;

        if ($itemId <= 0 || $buyUomId <= 0 || $contentUomId <= 0 || $snapshotMonth === null || $movementDate === null) {
            return [
                'ok' => false,
                'message' => 'item_id, buy_uom_id, content_uom_id, snapshot_month, movement_date wajib valid.',
            ];
        }

        if ($targetQtyBuy < 0 || $targetQtyContent < 0) {
            return [
                'ok' => false,
                'message' => 'Qty opening tidak boleh negatif.',
            ];
        }

        $profileName = $this->nullableString($payload['profile_name'] ?? null);
        $profileBrand = $this->nullableString($payload['profile_brand'] ?? null);
        $profileDescription = $this->nullableString($payload['profile_description'] ?? null);
        $profileContentPerBuy = round(max(0.000001, (float)($payload['profile_content_per_buy'] ?? 1)), 6);
        $profileKey = $this->nullableString($payload['profile_key'] ?? null);
        if ($profileKey === null) {
            $profileKey = sha1(implode('|', [
                $itemId,
                $buyUomId,
                $contentUomId,
                strtoupper((string)$profileName),
                strtoupper((string)$profileBrand),
                strtoupper((string)$profileDescription),
                number_format($profileContentPerBuy, 6, '.', ''),
            ]));
        }

        $buyUomCode = $this->resolveUomCode($buyUomId);
        $contentUomCode = $this->resolveUomCode($contentUomId);

        $this->db->trans_begin();

        $snapshot = [
            'snapshot_month' => $snapshotMonth,
            'stock_scope' => 'WAREHOUSE',
            'division_id' => null,
            'stock_domain' => 'ITEM',
            'item_id' => $itemId,
            'material_id' => null,
            'buy_uom_id' => $buyUomId,
            'content_uom_id' => $contentUomId,
            'profile_key' => $profileKey,
            'profile_name' => $profileName,
            'profile_brand' => $profileBrand,
            'profile_description' => $profileDescription,
            'profile_content_per_buy' => $profileContentPerBuy,
            'profile_buy_uom_code' => $buyUomCode,
            'profile_content_uom_code' => $contentUomCode,
            'opening_qty_buy' => $targetQtyBuy,
            'opening_qty_content' => $targetQtyContent,
            'opening_avg_cost_per_content' => $avgCost,
            'opening_total_value' => round($targetQtyContent * $avgCost, 2),
            'source_type' => 'MANUAL',
            'notes' => $this->nullableString($payload['notes'] ?? null),
            'created_by' => $userId > 0 ? $userId : null,
        ];

        $this->db->query(
            'INSERT INTO inv_stock_opening_snapshot (
                snapshot_month, stock_scope, division_id, stock_domain, item_id, material_id, buy_uom_id, content_uom_id,
                profile_key, profile_name, profile_brand, profile_description, profile_content_per_buy, profile_buy_uom_code,
                profile_content_uom_code, opening_qty_buy, opening_qty_content, opening_avg_cost_per_content, opening_total_value,
                source_type, notes, created_by
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
                profile_name = VALUES(profile_name),
                profile_brand = VALUES(profile_brand),
                profile_description = VALUES(profile_description),
                profile_content_per_buy = VALUES(profile_content_per_buy),
                profile_buy_uom_code = VALUES(profile_buy_uom_code),
                profile_content_uom_code = VALUES(profile_content_uom_code),
                opening_qty_buy = VALUES(opening_qty_buy),
                opening_qty_content = VALUES(opening_qty_content),
                opening_avg_cost_per_content = VALUES(opening_avg_cost_per_content),
                opening_total_value = VALUES(opening_total_value),
                source_type = VALUES(source_type),
                notes = VALUES(notes),
                updated_at = CURRENT_TIMESTAMP',
            [
                $snapshot['snapshot_month'],
                $snapshot['stock_scope'],
                $snapshot['division_id'],
                $snapshot['stock_domain'],
                $snapshot['item_id'],
                $snapshot['material_id'],
                $snapshot['buy_uom_id'],
                $snapshot['content_uom_id'],
                $snapshot['profile_key'],
                $snapshot['profile_name'],
                $snapshot['profile_brand'],
                $snapshot['profile_description'],
                $snapshot['profile_content_per_buy'],
                $snapshot['profile_buy_uom_code'],
                $snapshot['profile_content_uom_code'],
                $snapshot['opening_qty_buy'],
                $snapshot['opening_qty_content'],
                $snapshot['opening_avg_cost_per_content'],
                $snapshot['opening_total_value'],
                $snapshot['source_type'],
                $snapshot['notes'],
                $snapshot['created_by'],
            ]
        );

        $current = $this->db
            ->query(
                'SELECT qty_buy_balance, qty_content_balance FROM inv_warehouse_stock_balance WHERE item_id = ? AND buy_uom_id = ? AND content_uom_id = ? AND profile_key <=> ? LIMIT 1 FOR UPDATE',
                [$itemId, $buyUomId, $contentUomId, $profileKey]
            )
            ->row_array();

        $currentQtyBuy = round((float)($current['qty_buy_balance'] ?? 0), 4);
        $currentQtyContent = round((float)($current['qty_content_balance'] ?? 0), 4);
        $deltaQtyBuy = $replaceMode ? round($targetQtyBuy - $currentQtyBuy, 4) : $targetQtyBuy;
        $deltaQtyContent = $replaceMode ? round($targetQtyContent - $currentQtyContent, 4) : $targetQtyContent;

        $movementNo = null;
        if ($deltaQtyBuy != 0.0 || $deltaQtyContent != 0.0) {
            $this->load->library('InventoryLedger');
            $ledger = $this->inventoryledger->post([
                'manage_transaction' => false,
                'movement_scope' => 'WAREHOUSE',
                'movement_date' => $movementDate,
                'movement_type' => 'ADJUSTMENT',
                'stock_domain' => 'ITEM',
                'item_id' => $itemId,
                'buy_uom_id' => $buyUomId,
                'content_uom_id' => $contentUomId,
                'qty_buy_delta' => $deltaQtyBuy,
                'qty_content_delta' => $deltaQtyContent,
                'profile_key' => $profileKey,
                'profile_name' => $profileName,
                'profile_brand' => $profileBrand,
                'profile_description' => $profileDescription,
                'profile_content_per_buy' => $profileContentPerBuy,
                'profile_buy_uom_code' => $buyUomCode,
                'profile_content_uom_code' => $contentUomCode,
                'unit_cost' => $avgCost,
                'ref_table' => 'inv_stock_opening_snapshot',
                'ref_id' => null,
                'notes' => 'Opening gudang manual',
                'created_by' => $userId > 0 ? $userId : null,
            ]);

            if (!($ledger['ok'] ?? false)) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => (string)($ledger['message'] ?? 'Gagal posting opening ke ledger.'),
                ];
            }

            $movementNo = (string)($ledger['data']['movement_no'] ?? '');
        }

        if ($this->db->table_exists('aud_transaction_log')) {
            $this->db->insert('aud_transaction_log', [
                'module_code' => 'INVENTORY',
                'action_code' => 'WAREHOUSE_OPENING',
                'entity_table' => 'inv_stock_opening_snapshot',
                'entity_id' => null,
                'transaction_no' => $movementNo !== '' ? $movementNo : null,
                'actor_user_id' => $userId > 0 ? $userId : null,
                'source_ip' => $sourceIp !== '' ? $sourceIp : null,
                'after_payload' => json_encode([
                    'snapshot_month' => $snapshotMonth,
                    'item_id' => $itemId,
                    'profile_key' => $profileKey,
                    'target_qty_buy' => $targetQtyBuy,
                    'target_qty_content' => $targetQtyContent,
                    'delta_qty_buy' => $deltaQtyBuy,
                    'delta_qty_content' => $deltaQtyContent,
                    'replace_mode' => $replaceMode ? 1 : 0,
                ]),
                'notes' => 'Opening gudang diposting ke live balance dan daily rollup',
            ]);
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => 'Gagal menyimpan opening gudang.',
            ];
        }

        $this->db->trans_commit();

        return [
            'ok' => true,
            'message' => 'Opening gudang berhasil disimpan dan diposting.',
            'data' => [
                'snapshot_month' => $snapshotMonth,
                'item_id' => $itemId,
                'profile_key' => $profileKey,
                'movement_no' => $movementNo,
                'delta_qty_buy' => $deltaQtyBuy,
                'delta_qty_content' => $deltaQtyContent,
            ],
        ];
    }

    public function sync_catalog_from_core(int $limit = 1000): array
    {
        if (!$this->db->table_exists('mst_purchase_catalog')) {
            return [
                'ok' => false,
                'message' => 'Tabel mst_purchase_catalog belum tersedia.',
            ];
        }

        $limit = max(1, min(5000, $limit));

        $coreExists = $this->db->query("SHOW TABLES FROM core LIKE 'pur_purchase_catalog'")->num_rows() > 0;
        if (!$coreExists) {
            return [
                'ok' => false,
                'message' => 'Tabel core.pur_purchase_catalog tidak ditemukan atau database core tidak bisa diakses.',
            ];
        }

        $coreItemExists = $this->db->query("SHOW TABLES FROM core LIKE 'm_item'")->num_rows() > 0;
        $coreMaterialExists = $this->db->query("SHOW TABLES FROM core LIKE 'm_material'")->num_rows() > 0;
        $coreVendorExists = $this->db->query("SHOW TABLES FROM core LIKE 'm_vendor'")->num_rows() > 0;
        $coreUomExists = $this->db->query("SHOW TABLES FROM core LIKE 'm_uom'")->num_rows() > 0;
        if (!$coreItemExists || !$coreMaterialExists || !$coreVendorExists || !$coreUomExists) {
            return [
                'ok' => false,
                'message' => 'Tabel referensi core (m_item/m_material/m_vendor/m_uom) belum lengkap untuk sinkronisasi catalog yang aman.',
            ];
        }

        $defaultVendor = $this->db->select('id')->from('mst_vendor')->where('is_active', 1)->order_by('id', 'ASC')->limit(1)->get()->row_array();
        $defaultVendorId = (int)($defaultVendor['id'] ?? 0);
        if ($defaultVendorId <= 0) {
            $this->db->query(
                "INSERT INTO mst_vendor (vendor_code, vendor_name, notes, is_active)
                 VALUES ('VENDOR-CORE-DEFAULT', 'Vendor Default Core Import', 'Auto-generated fallback for core catalog sync', 1)
                 ON DUPLICATE KEY UPDATE
                    vendor_name = VALUES(vendor_name),
                    notes = VALUES(notes),
                    is_active = 1,
                    updated_at = CURRENT_TIMESTAMP"
            );

            $defaultVendor = $this->db
                ->select('id')
                ->from('mst_vendor')
                ->where('vendor_code', 'VENDOR-CORE-DEFAULT')
                ->limit(1)
                ->get()
                ->row_array();
            $defaultVendorId = (int)($defaultVendor['id'] ?? 0);

            if ($defaultVendorId <= 0) {
                return [
                    'ok' => false,
                    'message' => 'Vendor fallback untuk sinkron core gagal dibuat.',
                ];
            }
        }

        $defaultUom = $this->db->select('id')->from('mst_uom')->where('is_active', 1)->order_by('id', 'ASC')->limit(1)->get()->row_array();
        $defaultUomId = (int)($defaultUom['id'] ?? 0);
        if ($defaultUomId <= 0) {
            return [
                'ok' => false,
                'message' => 'UOM aktif belum tersedia di finance. Isi master UOM dulu.',
            ];
        }

        $sql = "
            INSERT INTO mst_purchase_catalog (
                profile_key, line_kind, item_id, material_id, vendor_id, catalog_name, brand_name, line_description,
                buy_uom_id, content_uom_id, content_per_buy, conversion_factor_to_content,
                standard_price, last_unit_price, last_purchase_date, last_purchase_order_id, last_purchase_line_id,
                notes, is_active
            )
            SELECT
                LEFT(c.profile_key, 64) AS profile_key,
                CASE
                    WHEN COALESCE(NULLIF(COALESCE(i_code.material_id, i_name.material_id), 0), m_code.id, m_name.id) IS NOT NULL THEN 'MATERIAL'
                    WHEN UPPER(TRIM(COALESCE(c.line_type, 'ITEM'))) = 'ASSET' THEN 'ASSET'
                    WHEN UPPER(TRIM(COALESCE(c.line_type, 'ITEM'))) IN ('SERVICE', 'PAYROLL_COMPONENT') THEN 'SERVICE'
                    ELSE 'ITEM'
                END AS line_kind,
                COALESCE(i_code.id, i_name.id) AS item_id,
                COALESCE(NULLIF(COALESCE(i_code.material_id, i_name.material_id), 0), m_code.id, m_name.id) AS material_id,
                COALESCE(v_code.id, v_name.id, {$defaultVendorId}) AS vendor_id,
                LEFT(COALESCE(NULLIF(TRIM(c.display_name), ''), NULLIF(TRIM(ci.item_name), ''), NULLIF(TRIM(cm.material_name), ''), 'UNNAMED CORE CATALOG'), 150) AS catalog_name,
                NULLIF(LEFT(TRIM(c.brand_name), 120), '') AS brand_name,
                NULLIF(LEFT(TRIM(c.last_description), 255), '') AS line_description,
                COALESCE(bu_code.id, {$defaultUomId}) AS buy_uom_id,
                COALESCE(cu_code.id, bu_code.id, {$defaultUomId}) AS content_uom_id,
                COALESCE(NULLIF(c.qty_gramasi, 0), 1) AS content_per_buy,
                COALESCE(NULLIF(c.qty_gramasi, 0), 1) AS conversion_factor_to_content,
                COALESCE(c.last_unit_price, 0) AS standard_price,
                COALESCE(c.last_unit_price, 0) AS last_unit_price,
                c.last_purchase_date,
                NULL,
                NULL,
                CONCAT('Sync core pur_purchase_catalog id=', c.id),
                c.is_active
            FROM core.pur_purchase_catalog c
            LEFT JOIN core.m_item ci ON ci.id = c.item_id
            LEFT JOIN core.m_material cm ON cm.id = c.material_id
            LEFT JOIN core.m_vendor cv ON cv.id = c.last_vendor_id
            LEFT JOIN core.m_uom cbu ON cbu.id = c.uom_id
            LEFT JOIN core.m_uom ccu ON ccu.id = c.gramasi_uom_id

            LEFT JOIN (
                SELECT item_code, MIN(id) AS id, MIN(material_id) AS material_id
                FROM mst_item
                WHERE item_code IS NOT NULL AND item_code <> ''
                GROUP BY item_code
            ) i_code ON i_code.item_code = ci.item_code
            LEFT JOIN (
                SELECT UPPER(TRIM(item_name)) AS item_name_key, MIN(id) AS id, MIN(material_id) AS material_id
                FROM mst_item
                WHERE item_name IS NOT NULL AND item_name <> ''
                GROUP BY UPPER(TRIM(item_name))
            ) i_name ON i_name.item_name_key = UPPER(TRIM(ci.item_name))

            LEFT JOIN (
                SELECT material_code, MIN(id) AS id
                FROM mst_material
                WHERE material_code IS NOT NULL AND material_code <> ''
                GROUP BY material_code
            ) m_code ON m_code.material_code = cm.material_code
            LEFT JOIN (
                SELECT UPPER(TRIM(material_name)) AS material_name_key, MIN(id) AS id
                FROM mst_material
                WHERE material_name IS NOT NULL AND material_name <> ''
                GROUP BY UPPER(TRIM(material_name))
            ) m_name ON m_name.material_name_key = UPPER(TRIM(cm.material_name))

            LEFT JOIN (
                SELECT vendor_code, MIN(id) AS id
                FROM mst_vendor
                WHERE vendor_code IS NOT NULL AND vendor_code <> ''
                GROUP BY vendor_code
            ) v_code ON v_code.vendor_code = cv.vendor_code
            LEFT JOIN (
                SELECT UPPER(TRIM(vendor_name)) AS vendor_name_key, MIN(id) AS id
                FROM mst_vendor
                WHERE vendor_name IS NOT NULL AND vendor_name <> ''
                GROUP BY UPPER(TRIM(vendor_name))
            ) v_name ON v_name.vendor_name_key = UPPER(TRIM(cv.vendor_name))

            LEFT JOIN (
                SELECT code, MIN(id) AS id
                FROM mst_uom
                WHERE code IS NOT NULL AND code <> ''
                GROUP BY code
            ) bu_code ON bu_code.code = cbu.code
            LEFT JOIN (
                SELECT code, MIN(id) AS id
                FROM mst_uom
                WHERE code IS NOT NULL AND code <> ''
                GROUP BY code
            ) cu_code ON cu_code.code = ccu.code
            WHERE c.profile_key IS NOT NULL AND c.profile_key <> ''
            ORDER BY c.id DESC
            LIMIT {$limit}
            ON DUPLICATE KEY UPDATE
                line_kind = VALUES(line_kind),
                item_id = VALUES(item_id),
                material_id = VALUES(material_id),
                vendor_id = VALUES(vendor_id),
                catalog_name = VALUES(catalog_name),
                brand_name = VALUES(brand_name),
                line_description = VALUES(line_description),
                buy_uom_id = VALUES(buy_uom_id),
                content_uom_id = VALUES(content_uom_id),
                content_per_buy = VALUES(content_per_buy),
                conversion_factor_to_content = VALUES(conversion_factor_to_content),
                standard_price = VALUES(standard_price),
                last_unit_price = VALUES(last_unit_price),
                last_purchase_date = VALUES(last_purchase_date),
                notes = VALUES(notes),
                is_active = VALUES(is_active),
                updated_at = CURRENT_TIMESTAMP
        ";

        $this->db->query($sql);

        if ($this->db->error()['code']) {
            return [
                'ok' => false,
                'message' => 'Gagal sinkron katalog: ' . (string)$this->db->error()['message'],
            ];
        }

        $total = (int)$this->db->count_all('mst_purchase_catalog');
        return [
            'ok' => true,
            'message' => 'Sinkron katalog core berhasil dijalankan.',
            'data' => [
                'limit' => $limit,
                'affected_rows' => (int)$this->db->affected_rows(),
                'catalog_total' => $total,
            ],
        ];
    }

    public function list_warehouse_stock(string $q, int $limit): array
    {
        if (!$this->db->table_exists('inv_warehouse_stock_balance')) {
            return [];
        }

        $this->db
            ->select('s.id, s.item_id, i.item_code, i.item_name, s.profile_key, s.profile_name, s.profile_brand, s.profile_description')
            ->select('s.profile_content_per_buy, s.profile_buy_uom_code, s.profile_content_uom_code')
            ->select('s.qty_buy_balance, s.qty_content_balance, s.avg_cost_per_content, s.updated_at')
            ->from('inv_warehouse_stock_balance s')
            ->join('mst_item i', 'i.id = s.item_id', 'left');

        if ($q !== '') {
            $this->db->group_start()
                ->like('i.item_code', $q)
                ->or_like('i.item_name', $q)
                ->or_like('s.profile_name', $q)
                ->or_like('s.profile_brand', $q)
                ->or_like('s.profile_description', $q)
                ->or_like('s.profile_key', $q)
                ->group_end();
        }

        $this->db
            ->order_by('s.item_id', 'ASC')
            ->order_by('s.profile_name', 'ASC')
            ->limit($limit);

        $query = $this->db->get();
        if (!$query) {
            return [];
        }

        return $query->result_array();
    }

    public function list_division_stock(string $q, int $limit): array
    {
        if (!$this->db->table_exists('inv_division_stock_balance')) {
            return [];
        }

        $hasDivisionCode = $this->db->field_exists('division_code', 'mst_operational_division');
        $hasDivisionName = $this->db->field_exists('division_name', 'mst_operational_division');
        $divisionCodeSelect = $hasDivisionCode ? 'd.division_code' : 'CAST(s.division_id AS CHAR) AS division_code';
        $divisionNameSelect = $hasDivisionName ? 'd.division_name' : 'NULL AS division_name';

        $this->db
            ->select('s.id, s.division_id, ' . $divisionCodeSelect . ', ' . $divisionNameSelect . ', s.item_id, s.material_id', false)
            ->select('i.item_code, i.item_name, m.material_code, m.material_name')
            ->select('s.profile_key, s.profile_name, s.profile_brand, s.profile_description')
            ->select('s.profile_content_per_buy, s.profile_buy_uom_code, s.profile_content_uom_code')
            ->select('s.qty_buy_balance, s.qty_content_balance, s.avg_cost_per_content, s.updated_at')
            ->from('inv_division_stock_balance s')
            ->join('mst_operational_division d', 'd.id = s.division_id', 'left')
            ->join('mst_item i', 'i.id = s.item_id', 'left')
            ->join('mst_material m', 'm.id = s.material_id', 'left');

        if ($q !== '') {
            $this->db->group_start();
            $hasDivisionFilter = false;
            if ($hasDivisionCode) {
                $this->db->like('d.division_code', $q);
                $hasDivisionFilter = true;
            }
            if ($hasDivisionName) {
                if ($hasDivisionFilter) {
                    $this->db->or_like('d.division_name', $q);
                } else {
                    $this->db->like('d.division_name', $q);
                }
                $hasDivisionFilter = true;
            }
            if (!$hasDivisionFilter) {
                $this->db->like('s.division_id', $q);
            }
            $this->db->or_like('i.item_code', $q)
                ->or_like('i.item_name', $q)
                ->or_like('m.material_code', $q)
                ->or_like('m.material_name', $q)
                ->or_like('s.profile_name', $q)
                ->or_like('s.profile_brand', $q)
                ->or_like('s.profile_description', $q)
                ->or_like('s.profile_key', $q)
                ->group_end();
        }

        $this->db->order_by($hasDivisionName ? 'd.division_name' : 's.division_id', 'ASC');

        $this->db
            ->order_by('i.item_name', 'ASC')
            ->order_by('m.material_name', 'ASC')
            ->limit($limit);

        $query = $this->db->get();
        if (!$query) {
            return [];
        }

        return $query->result_array();
    }

    public function list_purchase_orders_for_receipt(string $q = '', int $limit = 100): array
    {
        if (!$this->db->table_exists('pur_purchase_order')) {
            return [];
        }

        $this->db
            ->select('po.id, po.po_no, po.request_date, po.expected_date, po.status, v.vendor_name')
            ->from('pur_purchase_order po')
            ->join('mst_vendor v', 'v.id = po.vendor_id', 'left')
            ->where_not_in('po.status', ['VOID']);

        if ($q !== '') {
            $this->db->group_start()
                ->like('po.po_no', $q)
                ->or_like('v.vendor_name', $q)
                ->group_end();
        }

        $this->db
            ->order_by('po.request_date', 'DESC')
            ->order_by('po.id', 'DESC')
            ->limit($limit);

        return $this->db->get()->result_array();
    }

    public function list_purchase_orders_dashboard(string $q, string $status, int $limit): array
    {
        if (!$this->db->table_exists('pur_purchase_order')) {
            return [];
        }

        $this->db
            ->select('po.id, po.po_no, po.request_date, po.destination_type, po.status, po.grand_total')
            ->select('po.created_at, po.payment_account_id, pt.type_code AS purchase_type_code, pt.type_name AS purchase_type_name')
            ->select('v.vendor_code, v.vendor_name, a.account_code AS payment_account_code, a.account_name AS payment_account_name')
            ->from('pur_purchase_order po')
            ->join('mst_purchase_type pt', 'pt.id = po.purchase_type_id', 'left')
            ->join('mst_vendor v', 'v.id = po.vendor_id', 'left')
            ->join('fin_company_account a', 'a.id = po.payment_account_id', 'left');

        $status = strtoupper(trim($status));
        if ($status !== '' && $status !== 'ALL') {
            $this->db->where('po.status', $status);
        }

        if ($q !== '') {
            $this->db->group_start()
                ->like('po.po_no', $q)
                ->or_like('v.vendor_name', $q)
                ->or_like('pt.type_name', $q)
                ->or_like('po.notes', $q)
                ->group_end();
        }

        return $this->db
            ->order_by('po.request_date', 'DESC')
            ->order_by('po.id', 'DESC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    public function list_purchase_order_lines_dashboard(string $q, string $status, int $limit): array
    {
        if (!$this->db->table_exists('pur_purchase_order_line') || !$this->db->table_exists('pur_purchase_order')) {
            return [];
        }

        $this->db
            ->select('l.id, l.purchase_order_id, l.line_no, l.line_kind, l.qty_buy, l.content_per_buy, l.qty_content, l.unit_price, l.line_subtotal')
            ->select('l.snapshot_item_name, l.snapshot_material_name, l.snapshot_brand_name, l.snapshot_line_description')
            ->select('l.snapshot_buy_uom_code, l.snapshot_content_uom_code, l.profile_key')
            ->select('po.po_no, po.request_date, po.status, po.destination_type')
            ->select('pt.type_name AS purchase_type_name, v.vendor_name')
            ->from('pur_purchase_order_line l')
            ->join('pur_purchase_order po', 'po.id = l.purchase_order_id', 'inner')
            ->join('mst_purchase_type pt', 'pt.id = po.purchase_type_id', 'left')
            ->join('mst_vendor v', 'v.id = po.vendor_id', 'left');

        $status = strtoupper(trim($status));
        if ($status !== '' && $status !== 'ALL') {
            $this->db->where('po.status', $status);
        }

        if ($q !== '') {
            $this->db->group_start()
                ->like('po.po_no', $q)
                ->or_like('pt.type_name', $q)
                ->or_like('v.vendor_name', $q)
                ->or_like('l.snapshot_item_name', $q)
                ->or_like('l.snapshot_material_name', $q)
                ->or_like('l.snapshot_brand_name', $q)
                ->or_like('l.snapshot_line_description', $q)
                ->group_end();
        }

        return $this->db
            ->order_by('po.request_date', 'DESC')
            ->order_by('po.id', 'DESC')
            ->order_by('l.line_no', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    public function list_purchase_txn_action_codes(): array
    {
        if (!$this->db->table_exists('pur_purchase_txn_log')) {
            return [];
        }

        $rows = $this->db
            ->select('action_code')
            ->from('pur_purchase_txn_log')
            ->group_by('action_code')
            ->order_by('action_code', 'ASC')
            ->get()
            ->result_array();

        $result = [];
        foreach ($rows as $row) {
            $code = strtoupper(trim((string)($row['action_code'] ?? '')));
            if ($code !== '') {
                $result[] = $code;
            }
        }

        return $result;
    }

    public function list_purchase_txn_logs(string $q, string $action, string $dateFrom, string $dateTo, int $limit): array
    {
        if (!$this->db->table_exists('pur_purchase_txn_log')) {
            return [];
        }

        $this->db
            ->select('l.id, l.purchase_order_id, l.purchase_receipt_id, l.payment_plan_id')
            ->select('l.action_code, l.status_before, l.status_after, l.transaction_no')
            ->select('l.ref_table, l.ref_id, l.amount, l.payload_json, l.notes, l.created_by, l.created_at')
            ->select('po.po_no, po.request_date, po.status AS po_status')
            ->from('pur_purchase_txn_log l')
            ->join('pur_purchase_order po', 'po.id = l.purchase_order_id', 'left');

        $action = strtoupper(trim($action));
        if ($action !== '' && $action !== 'ALL') {
            $this->db->where('l.action_code', $action);
        }

        $from = $this->normalizeDate($dateFrom);
        $to = $this->normalizeDate($dateTo);
        if ($from !== null) {
            $this->db->where('DATE(l.created_at) >=', $from, false);
        }
        if ($to !== null) {
            $this->db->where('DATE(l.created_at) <=', $to, false);
        }

        $q = trim($q);
        if ($q !== '') {
            $this->db
                ->group_start()
                    ->like('po.po_no', $q)
                    ->or_like('l.action_code', $q)
                    ->or_like('l.transaction_no', $q)
                    ->or_like('l.notes', $q)
                    ->or_like('l.ref_table', $q)
                ->group_end();
        }

        return $this->db
            ->order_by('l.created_at', 'DESC')
            ->order_by('l.id', 'DESC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    public function get_purchase_order_detail(int $purchaseOrderId): ?array
    {
        if ($purchaseOrderId <= 0 || !$this->db->table_exists('pur_purchase_order')) {
            return null;
        }

        $order = $this->db
            ->select('po.*')
            ->select('pt.type_code AS purchase_type_code, pt.type_name AS purchase_type_name')
            ->select('v.vendor_code, v.vendor_name')
            ->select('a.account_code AS payment_account_code, a.account_name AS payment_account_name')
            ->from('pur_purchase_order po')
            ->join('mst_purchase_type pt', 'pt.id = po.purchase_type_id', 'left')
            ->join('mst_vendor v', 'v.id = po.vendor_id', 'left')
            ->join('fin_company_account a', 'a.id = po.payment_account_id', 'left')
            ->where('po.id', $purchaseOrderId)
            ->limit(1)
            ->get()
            ->row_array();

        if (!$order) {
            return null;
        }

        $lines = [];
        if ($this->db->table_exists('pur_purchase_order_line')) {
            $lines = $this->db
                ->select('id, line_no, line_kind, item_id, material_id, buy_uom_id, content_uom_id')
                ->select('brand_name, line_description, qty_buy, content_per_buy, qty_content, conversion_factor_to_content')
                ->select('unit_price, discount_percent, tax_percent, line_subtotal, profile_key, notes')
                ->select('snapshot_item_name, snapshot_material_name, snapshot_brand_name, snapshot_line_description')
                ->select('snapshot_buy_uom_code, snapshot_content_uom_code')
                ->from('pur_purchase_order_line')
                ->where('purchase_order_id', $purchaseOrderId)
                ->order_by('line_no', 'ASC')
                ->get()
                ->result_array();
        }

        $payments = [
            'total_paid' => 0.0,
            'payment_count' => 0,
            'last_payment_date' => null,
        ];
        if ($this->db->table_exists('pur_purchase_payment_plan')) {
            $pay = $this->db
                ->select('COALESCE(SUM(CASE WHEN status = \'PAID\' THEN paid_amount ELSE 0 END), 0) AS total_paid', false)
                ->select('SUM(CASE WHEN status = \'PAID\' THEN 1 ELSE 0 END) AS payment_count', false)
                ->select('MAX(CASE WHEN status = \'PAID\' THEN payment_date ELSE NULL END) AS last_payment_date', false)
                ->from('pur_purchase_payment_plan')
                ->where('purchase_order_id', $purchaseOrderId)
                ->get()
                ->row_array();
            $payments['total_paid'] = (float)($pay['total_paid'] ?? 0);
            $payments['payment_count'] = (int)($pay['payment_count'] ?? 0);
            $payments['last_payment_date'] = $pay['last_payment_date'] ?? null;
        }

        $receipts = [
            'receipt_count' => 0,
            'last_receipt_at' => null,
        ];
        if ($this->db->table_exists('pur_purchase_receipt')) {
            $rc = $this->db
                ->select('COUNT(*) AS receipt_count', false)
                ->select('MAX(CASE WHEN status = \'POSTED\' THEN posted_at ELSE receipt_date END) AS last_receipt_at', false)
                ->from('pur_purchase_receipt')
                ->where('purchase_order_id', $purchaseOrderId)
                ->where('status !=', 'VOID')
                ->get()
                ->row_array();
            $receipts['receipt_count'] = (int)($rc['receipt_count'] ?? 0);
            $receipts['last_receipt_at'] = $rc['last_receipt_at'] ?? null;
        }

        $auditRows = [];
        if ($this->db->table_exists('aud_transaction_log')) {
            $auditRows = $this->db
                ->select('action_code, entity_table, transaction_no, created_at, notes')
                ->from('aud_transaction_log')
                ->group_start()
                    ->where('ref_table', 'pur_purchase_order')
                    ->where('ref_id', $purchaseOrderId)
                ->group_end()
                ->order_by('created_at', 'ASC')
                ->get()
                ->result_array();
        }

        $txnRows = [];
        if ($this->db->table_exists('pur_purchase_txn_log')) {
            $txnRows = $this->db
                ->select('id, action_code, status_before, status_after, transaction_no, ref_table, ref_id, amount, notes, created_at')
                ->from('pur_purchase_txn_log')
                ->where('purchase_order_id', $purchaseOrderId)
                ->order_by('created_at', 'ASC')
                ->order_by('id', 'ASC')
                ->get()
                ->result_array();
        }

        $grandTotal = round((float)($order['grand_total'] ?? 0), 2);
        $outstanding = max(0, round($grandTotal - (float)$payments['total_paid'], 2));

        return [
            'order' => $order,
            'lines' => $lines,
            'payments' => $payments,
            'receipts' => $receipts,
            'txn_rows' => $txnRows,
            'audit_rows' => $auditRows,
            'outstanding' => $outstanding,
        ];
    }

    public function get_order_data_editability(int $purchaseOrderId): array
    {
        if ($purchaseOrderId <= 0 || !$this->db->table_exists('pur_purchase_order')) {
            return [
                'ok' => false,
                'message' => 'Purchase order tidak valid.',
            ];
        }

        $order = $this->db
            ->select('id, po_no, status')
            ->from('pur_purchase_order')
            ->where('id', $purchaseOrderId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$order) {
            return [
                'ok' => false,
                'message' => 'Purchase order tidak ditemukan.',
            ];
        }

        return $this->evaluateOrderDataEditability($order);
    }

    public function update_order_status(int $purchaseOrderId, string $newStatus, int $userId, string $sourceIp = ''): array
    {
        if ($purchaseOrderId <= 0 || !$this->db->table_exists('pur_purchase_order')) {
            return [
                'ok' => false,
                'message' => 'Purchase order tidak valid.',
            ];
        }

        $allowed = ['DRAFT', 'APPROVED', 'ORDERED', 'REJECTED', 'PARTIAL_RECEIVED', 'RECEIVED', 'PAID', 'CLOSED', 'VOID'];
        $newStatus = strtoupper(trim($newStatus));
        if (!in_array($newStatus, $allowed, true)) {
            return [
                'ok' => false,
                'message' => 'Status tujuan tidak valid.',
            ];
        }

        if ($newStatus === 'CLOSED') {
            return [
                'ok' => false,
                'message' => 'Status CLOSED sudah dinonaktifkan. Gunakan PAID sebagai status akhir atau VOID untuk pembatalan.',
            ];
        }

        $order = $this->db->get_where('pur_purchase_order', ['id' => $purchaseOrderId])->row_array();
        if (!$order) {
            return [
                'ok' => false,
                'message' => 'Purchase order tidak ditemukan.',
            ];
        }

        $current = strtoupper((string)($order['status'] ?? 'DRAFT'));
        $transitions = [
            'DRAFT' => ['APPROVED', 'ORDERED', 'PARTIAL_RECEIVED', 'RECEIVED', 'PAID', 'REJECTED', 'VOID'],
            'APPROVED' => ['ORDERED', 'PARTIAL_RECEIVED', 'RECEIVED', 'PAID', 'REJECTED', 'VOID'],
            'ORDERED' => ['PARTIAL_RECEIVED', 'RECEIVED', 'PAID', 'REJECTED', 'VOID'],
            'REJECTED' => [],
            'PARTIAL_RECEIVED' => ['RECEIVED', 'PAID', 'VOID'],
            'RECEIVED' => ['PAID', 'VOID'],
            'PAID' => ['VOID'],
            'CLOSED' => ['VOID'],
            'VOID' => [],
        ];

        if ($newStatus === $current) {
            if (in_array($current, ['RECEIVED', 'PAID'], true)) {
                $this->db->trans_begin();

                $receiptPosting = $this->autoPostOutstandingReceiptOnStatusReached(
                    $order,
                    $userId,
                    $sourceIp
                );
                if (!($receiptPosting['ok'] ?? false)) {
                    $this->db->trans_rollback();
                    return [
                        'ok' => false,
                        'message' => (string)($receiptPosting['message'] ?? 'Gagal sinkronisasi dampak stok untuk status PAID.'),
                    ];
                }
                $receiptPostingData = (array)($receiptPosting['data'] ?? []);

                $paidPostingData = [];
                if ($current === 'PAID') {
                    $paidPosting = $this->autoApplyOutstandingPaymentOnStatusPaid(
                        $order,
                        $userId,
                        $sourceIp
                    );
                    if (!($paidPosting['ok'] ?? false)) {
                        $this->db->trans_rollback();
                        return [
                            'ok' => false,
                            'message' => (string)($paidPosting['message'] ?? 'Gagal sinkronisasi dampak PAID.'),
                        ];
                    }

                    $paidPostingData = (array)($paidPosting['data'] ?? []);
                } else {
                    $paidPostingData = ['skipped' => 'STATUS_NOT_PAID'];
                }

                $reconcileActionCode = $current === 'PAID' ? 'PO_PAID_RECONCILE' : 'PO_RECEIVED_RECONCILE';
                $reconcileLabel = $current === 'PAID' ? 'PAID' : 'RECEIVED';
                $hasReceiptEffect = empty((string)($receiptPostingData['skipped'] ?? ''));
                $hasPaymentEffect = $current === 'PAID'
                    ? empty((string)($paidPostingData['skipped'] ?? ''))
                    : false;

                if (!$hasReceiptEffect && !$hasPaymentEffect) {
                    if ($this->db->trans_status() === false) {
                        $this->db->trans_rollback();
                        return [
                            'ok' => false,
                            'message' => 'Gagal sinkronisasi dampak ' . $reconcileLabel . '.',
                        ];
                    }

                    $this->db->trans_commit();

                    return [
                        'ok' => true,
                        'message' => 'Status tetap ' . $reconcileLabel . '. Tidak ada dampak baru yang perlu diposting.',
                        'data' => [
                            'purchase_order_id' => $purchaseOrderId,
                            'status' => $current,
                            'receipt_auto_post' => $receiptPostingData,
                            'payment_auto_apply' => $paidPostingData,
                        ],
                    ];
                }

                if ($this->db->table_exists('aud_transaction_log')) {
                    $this->db->insert('aud_transaction_log', [
                        'module_code' => 'PURCHASE',
                        'action_code' => $reconcileActionCode,
                        'entity_table' => 'pur_purchase_order',
                        'entity_id' => $purchaseOrderId,
                        'transaction_no' => (string)($order['po_no'] ?? null),
                        'ref_table' => 'pur_purchase_order',
                        'ref_id' => $purchaseOrderId,
                        'actor_user_id' => $userId > 0 ? $userId : null,
                        'source_ip' => $sourceIp !== '' ? $sourceIp : null,
                        'after_payload' => json_encode([
                            'status_before' => $current,
                            'status_after' => $newStatus,
                            'receipt_auto_post' => $receiptPostingData,
                            'payment_auto_apply' => $paidPostingData,
                        ]),
                        'notes' => 'Rekonsiliasi dampak ' . $reconcileLabel . ' tanpa perubahan status',
                    ]);
                }

                $txnLog = $this->writePurchaseTxnLog([
                    'purchase_order_id' => $purchaseOrderId,
                    'action_code' => $reconcileActionCode,
                    'status_before' => $current,
                    'status_after' => $newStatus,
                    'transaction_no' => (string)($order['po_no'] ?? ''),
                    'ref_table' => 'pur_purchase_order',
                    'ref_id' => $purchaseOrderId,
                    'payload' => [
                        'receipt_auto_post' => $receiptPostingData,
                        'payment_auto_apply' => $paidPostingData,
                    ],
                    'notes' => 'Sinkronisasi dampak ' . $reconcileLabel,
                    'created_by' => $userId,
                ]);
                if (!($txnLog['ok'] ?? false)) {
                    $this->db->trans_rollback();
                    return [
                        'ok' => false,
                        'message' => (string)($txnLog['message'] ?? 'Gagal mencatat log transaksi purchase.'),
                    ];
                }

                if ($this->db->trans_status() === false) {
                    $this->db->trans_rollback();
                    return [
                        'ok' => false,
                        'message' => 'Gagal sinkronisasi dampak ' . $reconcileLabel . '.',
                    ];
                }

                $this->db->trans_commit();

                return [
                    'ok' => true,
                    'message' => 'Status tetap ' . $reconcileLabel . '. Dampak transaksi berhasil disinkronkan.',
                    'data' => [
                        'purchase_order_id' => $purchaseOrderId,
                        'status' => $current,
                        'receipt_auto_post' => $receiptPostingData,
                        'payment_auto_apply' => $paidPostingData,
                    ],
                ];
            }

            return [
                'ok' => true,
                'message' => 'Status tidak berubah.',
                'data' => [
                    'purchase_order_id' => $purchaseOrderId,
                    'status' => $current,
                ],
            ];
        }

        $nextAllowed = $transitions[$current] ?? [];
        if (!in_array($newStatus, $nextAllowed, true)) {
            return [
                'ok' => false,
                'message' => 'Transisi status tidak diizinkan: ' . $current . ' -> ' . $newStatus,
            ];
        }

        $this->db->trans_begin();

        $rollbackData = [];
        $receiptPostingData = [];
        $paidPostingData = [];
        if ($newStatus === 'VOID' && in_array($current, ['PARTIAL_RECEIVED', 'RECEIVED', 'PAID', 'CLOSED'], true)) {
            $rollback = $this->rollbackPurchaseOnVoid(
                $purchaseOrderId,
                $order,
                $userId,
                $sourceIp
            );
            if (!($rollback['ok'] ?? false)) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => (string)($rollback['message'] ?? 'Rollback VOID gagal.'),
                ];
            }
            $rollbackData = (array)($rollback['data'] ?? []);
        }

        if (in_array($newStatus, ['RECEIVED', 'PAID'], true)) {
            $receiptPosting = $this->autoPostOutstandingReceiptOnStatusReached(
                $order,
                $userId,
                $sourceIp
            );
            if (!($receiptPosting['ok'] ?? false)) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => (string)($receiptPosting['message'] ?? 'Gagal memposting dampak stok saat update status.'),
                ];
            }
            $receiptPostingData = (array)($receiptPosting['data'] ?? []);
        }

        if ($newStatus === 'PAID') {
            $paidPosting = $this->autoApplyOutstandingPaymentOnStatusPaid(
                $order,
                $userId,
                $sourceIp
            );
            if (!($paidPosting['ok'] ?? false)) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => (string)($paidPosting['message'] ?? 'Gagal memposting dampak pembayaran saat ubah status ke PAID.'),
                ];
            }
            $paidPostingData = (array)($paidPosting['data'] ?? []);
        }

        $update = ['status' => $newStatus];
        if ($newStatus === 'APPROVED') {
            $update['approved_by'] = $userId > 0 ? $userId : null;
            $update['approved_at'] = date('Y-m-d H:i:s');
        }

        $this->db->where('id', $purchaseOrderId)->update('pur_purchase_order', $update);
        if ($this->db->error()['code']) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => 'Gagal update status: ' . (string)$this->db->error()['message'],
            ];
        }

        if ($this->db->table_exists('aud_transaction_log')) {
            $this->db->insert('aud_transaction_log', [
                'module_code' => 'PURCHASE',
                'action_code' => 'PO_STATUS_UPDATE',
                'entity_table' => 'pur_purchase_order',
                'entity_id' => $purchaseOrderId,
                'transaction_no' => (string)($order['po_no'] ?? null),
                'ref_table' => 'pur_purchase_order',
                'ref_id' => $purchaseOrderId,
                'actor_user_id' => $userId > 0 ? $userId : null,
                'source_ip' => $sourceIp !== '' ? $sourceIp : null,
                'after_payload' => json_encode([
                    'status_before' => $current,
                    'status_after' => $newStatus,
                    'rollback' => $rollbackData,
                    'receipt_auto_post' => $receiptPostingData,
                    'payment_auto_apply' => $paidPostingData,
                ]),
                'notes' => 'Update status purchase order',
            ]);
        }

        $txnLog = $this->writePurchaseTxnLog([
            'purchase_order_id' => $purchaseOrderId,
            'action_code' => 'PO_STATUS_UPDATE',
            'status_before' => $current,
            'status_after' => $newStatus,
            'transaction_no' => (string)($order['po_no'] ?? ''),
            'ref_table' => 'pur_purchase_order',
            'ref_id' => $purchaseOrderId,
            'payload' => [
                'rollback' => $rollbackData,
                'receipt_auto_post' => $receiptPostingData,
                'payment_auto_apply' => $paidPostingData,
            ],
            'notes' => 'Update status purchase order',
            'created_by' => $userId,
        ]);
        if (!($txnLog['ok'] ?? false)) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => (string)($txnLog['message'] ?? 'Gagal mencatat log transaksi purchase.'),
            ];
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => 'Gagal update status purchase order.',
            ];
        }

        $this->db->trans_commit();

        return [
            'ok' => true,
            'message' => 'Status purchase order berhasil diperbarui.',
            'data' => [
                'purchase_order_id' => $purchaseOrderId,
                'status_before' => $current,
                'status_after' => $newStatus,
                'rollback' => $rollbackData,
                'receipt_auto_post' => $receiptPostingData,
                'payment_auto_apply' => $paidPostingData,
            ],
        ];
    }

    private function autoPostOutstandingReceiptOnStatusReached(array $order, int $userId, string $sourceIp = ''): array
    {
        $purchaseOrderId = (int)($order['id'] ?? 0);
        if ($purchaseOrderId <= 0) {
            return [
                'ok' => false,
                'message' => 'Purchase order tidak valid untuk auto-post receipt.',
            ];
        }

        $purchaseTypeId = (int)($order['purchase_type_id'] ?? 0);
        $typeRule = $this->getPurchaseTypeRule($purchaseTypeId);
        if ($typeRule === null || (int)($typeRule['affects_inventory'] ?? 0) !== 1) {
            return [
                'ok' => true,
                'data' => [
                    'posted_line_count' => 0,
                    'skipped' => 'NOT_INVENTORY',
                ],
            ];
        }

        $destinationType = $this->normalizeDestination((string)($order['destination_type'] ?? ''));
        if ($destinationType === null) {
            return [
                'ok' => false,
                'message' => 'Destination type pada PO tidak valid untuk auto-post receipt.',
            ];
        }

        $destinationDivisionId = $this->nullableInt($order['destination_division_id'] ?? null);
        if ($destinationType !== 'GUDANG' && $destinationDivisionId === null) {
            $destinationDivisionId = $this->resolveDestinationDivisionId($destinationType);
        }
        if ($destinationType !== 'GUDANG' && $destinationDivisionId === null) {
            return [
                'ok' => false,
                'message' => 'Divisi tujuan tidak ditemukan untuk auto-post receipt destination ' . $destinationType . '.',
            ];
        }

        $poLines = $this->get_po_lines_for_receipt($purchaseOrderId);
        $receiptLines = [];
        foreach ($poLines as $poLine) {
            $orderedQty = round((float)($poLine['qty_buy'] ?? 0), 4);
            $receivedQty = round((float)($poLine['qty_buy_received_total'] ?? 0), 4);
            $remainingQty = round(max(0, $orderedQty - $receivedQty), 4);
            if ($remainingQty <= 0) {
                continue;
            }

            $receiptLines[] = [
                'purchase_order_line_id' => (int)($poLine['purchase_order_line_id'] ?? 0),
                'qty_buy_received' => $remainingQty,
                'notes' => 'Auto receipt saat status PO mencapai RECEIVED/PAID',
            ];
        }

        if (empty($receiptLines)) {
            return [
                'ok' => true,
                'data' => [
                    'posted_line_count' => 0,
                    'skipped' => 'NO_REMAINING_QTY',
                ],
            ];
        }

        $receiptPayload = [
            'header' => [
                'purchase_order_id' => $purchaseOrderId,
                'receipt_date' => date('Y-m-d'),
                'destination_type' => $destinationType,
                'destination_division_id' => $destinationDivisionId,
                'notes' => 'Auto receipt saat status PO mencapai RECEIVED/PAID',
            ],
            'lines' => $receiptLines,
        ];

        $postReceipt = $this->store_receipt_and_post($receiptPayload, $userId, $sourceIp);
        if (!($postReceipt['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => (string)($postReceipt['message'] ?? 'Gagal auto-post receipt saat status PO mencapai RECEIVED/PAID.'),
            ];
        }

        return [
            'ok' => true,
            'data' => [
                'receipt_id' => (int)($postReceipt['data']['receipt_id'] ?? 0),
                'receipt_no' => (string)($postReceipt['data']['receipt_no'] ?? ''),
                'posted_line_count' => (int)($postReceipt['data']['line_count'] ?? 0),
            ],
        ];
    }

    private function autoApplyOutstandingPaymentOnStatusPaid(array $order, int $userId, string $sourceIp = ''): array
    {
        $purchaseOrderId = (int)($order['id'] ?? 0);
        if ($purchaseOrderId <= 0) {
            return [
                'ok' => false,
                'message' => 'Purchase order tidak valid untuk auto-post pembayaran PAID.',
            ];
        }

        if (!$this->db->table_exists('pur_purchase_payment_plan') || !$this->db->table_exists('fin_company_account')) {
            return [
                'ok' => false,
                'message' => 'Tabel payment plan/rekening belum tersedia untuk memproses dampak status PAID.',
            ];
        }

        $grandTotal = round((float)($order['grand_total'] ?? 0), 2);
        $paidRow = $this->db
            ->select('COALESCE(SUM(paid_amount), 0) AS total_paid', false)
            ->from('pur_purchase_payment_plan')
            ->where('purchase_order_id', $purchaseOrderId)
            ->where('status', 'PAID')
            ->get()
            ->row_array();

        $totalPaidBefore = round((float)($paidRow['total_paid'] ?? 0), 2);
        $outstanding = max(0, round($grandTotal - $totalPaidBefore, 2));
        if ($outstanding <= 0) {
            return [
                'ok' => true,
                'data' => [
                    'auto_paid_amount' => 0.0,
                    'total_paid_before' => $totalPaidBefore,
                    'total_paid_after' => $totalPaidBefore,
                    'outstanding_after' => 0.0,
                    'skipped' => 'NO_OUTSTANDING',
                ],
            ];
        }

        $accountId = (int)($order['payment_account_id'] ?? 0);
        if ($accountId <= 0) {
            return [
                'ok' => false,
                'message' => 'Status PAID membutuhkan akun pembayaran. Pilih Payment Account di PO atau gunakan menu pembayaran.',
            ];
        }

        $account = $this->db
            ->query('SELECT * FROM fin_company_account WHERE id = ? LIMIT 1 FOR UPDATE', [$accountId])
            ->row_array();
        if (!$account) {
            return [
                'ok' => false,
                'message' => 'Akun pembayaran tidak ditemukan untuk auto-post status PAID.',
            ];
        }

        if ($this->db->field_exists('is_active', 'fin_company_account') && (int)($account['is_active'] ?? 1) !== 1) {
            return [
                'ok' => false,
                'message' => 'Akun pembayaran tidak aktif untuk auto-post status PAID.',
            ];
        }

        $balanceBefore = round((float)($account['current_balance'] ?? 0), 2);
        if ($balanceBefore < $outstanding) {
            return [
                'ok' => false,
                'message' => 'Saldo akun pembayaran tidak cukup untuk auto-post PAID. Outstanding: ' . number_format($outstanding, 2, ',', '.'),
            ];
        }

        $paymentDate = date('Y-m-d');
        $balanceAfter = round($balanceBefore - $outstanding, 2);

        $this->db->where('id', $accountId)->update('fin_company_account', [
            'current_balance' => $balanceAfter,
        ]);
        if ((int)($this->db->error()['code'] ?? 0) !== 0) {
            return [
                'ok' => false,
                'message' => 'Gagal update saldo rekening saat auto-post PAID.',
            ];
        }

        $transactionNo = $this->generatePaymentNo($paymentDate);

        if ($this->db->table_exists('fin_account_mutation_log')) {
            $this->db->insert('fin_account_mutation_log', [
                'mutation_no' => $this->generateAccountMutationNo($paymentDate),
                'mutation_date' => $paymentDate,
                'account_id' => $accountId,
                'mutation_type' => 'OUT',
                'amount' => $outstanding,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'ref_module' => 'PURCHASE',
                'ref_table' => 'pur_purchase_order',
                'ref_id' => $purchaseOrderId,
                'ref_no' => $transactionNo,
                'notes' => 'Auto pembayaran outstanding saat status PO diubah ke PAID',
                'created_by' => $userId > 0 ? $userId : null,
            ]);

            if ((int)($this->db->error()['code'] ?? 0) !== 0) {
                return [
                    'ok' => false,
                    'message' => 'Gagal mencatat mutasi rekening saat auto-post PAID.',
                ];
            }
        }

        $planData = [
            'purchase_order_id' => $purchaseOrderId,
            'plan_type' => $totalPaidBefore > 0 ? 'PARTIAL' : 'FULL',
            'terms_days' => 0,
            'due_date' => null,
            'payment_date' => $paymentDate,
            'planned_amount' => $outstanding,
            'paid_amount' => $outstanding,
            'status' => 'PAID',
            'reference_no' => null,
            'transaction_no' => $transactionNo,
            'notes' => 'Auto pembayaran outstanding saat status PO diubah ke PAID',
            'created_by' => $userId > 0 ? $userId : null,
        ];
        if ($this->db->field_exists('payment_method_id', 'pur_purchase_payment_plan')) {
            $planData['payment_method_id'] = null;
        }
        if ($this->db->field_exists('payment_channel_id', 'pur_purchase_payment_plan')) {
            $planData['payment_channel_id'] = null;
        }
        if ($this->db->field_exists('paid_from_account_id', 'pur_purchase_payment_plan')) {
            $planData['paid_from_account_id'] = $accountId;
        }

        $this->db->insert('pur_purchase_payment_plan', $planData);
        $paymentPlanId = (int)$this->db->insert_id();
        if ($paymentPlanId <= 0 || (int)($this->db->error()['code'] ?? 0) !== 0) {
            return [
                'ok' => false,
                'message' => 'Gagal membuat payment plan otomatis saat status PAID.',
            ];
        }

        $totalPaidAfter = round($totalPaidBefore + $outstanding, 2);
        $outstandingAfter = max(0, round($grandTotal - $totalPaidAfter, 2));

        if ($this->db->table_exists('aud_transaction_log')) {
            $this->db->insert('aud_transaction_log', [
                'module_code' => 'PURCHASE',
                'action_code' => 'PAYMENT_AUTO_APPLY_STATUS',
                'entity_table' => 'pur_purchase_payment_plan',
                'entity_id' => $paymentPlanId,
                'transaction_no' => $transactionNo,
                'ref_table' => 'pur_purchase_order',
                'ref_id' => $purchaseOrderId,
                'actor_user_id' => $userId > 0 ? $userId : null,
                'source_ip' => $sourceIp !== '' ? $sourceIp : null,
                'after_payload' => json_encode([
                    'purchase_order_id' => $purchaseOrderId,
                    'paid_from_account_id' => $accountId,
                    'auto_paid_amount' => $outstanding,
                    'payment_date' => $paymentDate,
                    'total_paid_before' => $totalPaidBefore,
                    'total_paid_after' => $totalPaidAfter,
                    'outstanding_after' => $outstandingAfter,
                ]),
                'notes' => 'Auto posting pembayaran saat status PO diubah ke PAID',
            ]);
        }

        $txnLog = $this->writePurchaseTxnLog([
            'purchase_order_id' => $purchaseOrderId,
            'payment_plan_id' => $paymentPlanId,
            'action_code' => 'PAYMENT_AUTO_APPLY_STATUS',
            'status_before' => strtoupper((string)($order['status'] ?? '')),
            'status_after' => 'PAID',
            'transaction_no' => $transactionNo,
            'ref_table' => 'pur_purchase_payment_plan',
            'ref_id' => $paymentPlanId,
            'amount' => $outstanding,
            'payload' => [
                'purchase_order_id' => $purchaseOrderId,
                'paid_from_account_id' => $accountId,
                'payment_date' => $paymentDate,
                'total_paid_before' => $totalPaidBefore,
                'total_paid_after' => $totalPaidAfter,
                'outstanding_after' => $outstandingAfter,
            ],
            'notes' => 'Auto pembayaran outstanding saat status PO menjadi PAID',
            'created_by' => $userId,
        ]);
        if (!($txnLog['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => (string)($txnLog['message'] ?? 'Gagal mencatat log transaksi purchase.'),
            ];
        }

        return [
            'ok' => true,
            'data' => [
                'payment_plan_id' => $paymentPlanId,
                'transaction_no' => $transactionNo,
                'paid_from_account_id' => $accountId,
                'auto_paid_amount' => $outstanding,
                'payment_date' => $paymentDate,
                'total_paid_before' => $totalPaidBefore,
                'total_paid_after' => $totalPaidAfter,
                'outstanding_after' => $outstandingAfter,
            ],
        ];
    }

    private function rollbackPurchaseOnVoid(int $purchaseOrderId, array $order, int $userId, string $sourceIp = ''): array
    {
        $receiptRollback = $this->rollbackPostedReceiptsOnVoid($purchaseOrderId, $userId, $sourceIp);
        if (!($receiptRollback['ok'] ?? false)) {
            return $receiptRollback;
        }

        $paymentRollback = $this->rollbackPaidPlansOnVoid(
            $purchaseOrderId,
            (int)($order['payment_account_id'] ?? 0),
            $userId,
            $sourceIp
        );
        if (!($paymentRollback['ok'] ?? false)) {
            return $paymentRollback;
        }

        return [
            'ok' => true,
            'data' => [
                'receipts' => (array)($receiptRollback['data'] ?? []),
                'payments' => (array)($paymentRollback['data'] ?? []),
            ],
        ];
    }

    private function rollbackPostedReceiptsOnVoid(int $purchaseOrderId, int $userId, string $sourceIp = ''): array
    {
        if (
            !$this->db->table_exists('pur_purchase_receipt') ||
            !$this->db->table_exists('pur_purchase_receipt_line') ||
            !$this->db->table_exists('pur_purchase_order_line')
        ) {
            return ['ok' => true, 'data' => ['receipts_voided' => 0, 'lines_reversed' => 0]];
        }

        $receipts = $this->db
            ->select('id, receipt_no, destination_type, destination_division_id, notes')
            ->from('pur_purchase_receipt')
            ->where('purchase_order_id', $purchaseOrderId)
            ->where('status', 'POSTED')
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();

        if (empty($receipts)) {
            return ['ok' => true, 'data' => ['receipts_voided' => 0, 'lines_reversed' => 0]];
        }

        $this->load->library('InventoryLedger');

        $receiptsVoided = 0;
        $linesReversed = 0;
        $rollbackDate = date('Y-m-d');
        $receiptLinesById = [];
        $requirements = [];

        foreach ($receipts as $receipt) {
            $receiptId = (int)($receipt['id'] ?? 0);
            if ($receiptId <= 0) {
                continue;
            }

            $scope = strtoupper((string)($receipt['destination_type'] ?? '')) === 'GUDANG' ? 'WAREHOUSE' : 'DIVISION';
            $divisionId = $scope === 'DIVISION' ? $this->nullableInt($receipt['destination_division_id'] ?? null) : null;
            if ($scope === 'DIVISION' && $divisionId === null) {
                return [
                    'ok' => false,
                    'message' => 'Rollback receipt gagal: division tujuan tidak valid untuk receipt #' . (string)($receipt['receipt_no'] ?? $receiptId),
                ];
            }

            $lines = $this->db
                ->select('rl.*, pol.line_no, pol.unit_price, pol.content_per_buy, pol.snapshot_item_name, pol.snapshot_material_name')
                ->select('pol.snapshot_brand_name, pol.snapshot_line_description, pol.snapshot_buy_uom_code, pol.snapshot_content_uom_code')
                ->from('pur_purchase_receipt_line rl')
                ->join('pur_purchase_order_line pol', 'pol.id = rl.purchase_order_line_id', 'left')
                ->where('rl.purchase_receipt_id', $receiptId)
                ->order_by('rl.id', 'ASC')
                ->get()
                ->result_array();

            $receiptLinesById[$receiptId] = $lines;

            foreach ($lines as $line) {
                $qtyBuy = abs(round((float)($line['qty_buy_received'] ?? 0), 4));
                $qtyContent = abs(round((float)($line['qty_content_received'] ?? 0), 4));
                if ($qtyBuy <= 0 && $qtyContent <= 0) {
                    continue;
                }

                $profileName = trim((string)($line['snapshot_item_name'] ?? ''));
                if ($profileName === '') {
                    $profileName = trim((string)($line['snapshot_material_name'] ?? ''));
                }

                $req = [
                    'scope' => $scope,
                    'division_id' => $divisionId,
                    'item_id' => $this->nullableInt($line['item_id'] ?? null),
                    'material_id' => $this->nullableInt($line['material_id'] ?? null),
                    'buy_uom_id' => $this->nullableInt($line['buy_uom_id'] ?? null),
                    'content_uom_id' => $this->nullableInt($line['content_uom_id'] ?? null),
                    'profile_key' => $this->nullableString($line['profile_key'] ?? null),
                ];
                $reqKey = $this->buildVoidRollbackRequirementKey($req);
                if (!isset($requirements[$reqKey])) {
                    $requirements[$reqKey] = [
                        'scope' => $scope,
                        'division_id' => $divisionId,
                        'item_id' => $req['item_id'],
                        'material_id' => $req['material_id'],
                        'buy_uom_id' => $req['buy_uom_id'],
                        'content_uom_id' => $req['content_uom_id'],
                        'profile_key' => $req['profile_key'],
                        'profile_name' => $profileName,
                        'qty_buy_needed' => 0.0,
                        'qty_content_needed' => 0.0,
                        'refs' => [],
                    ];
                }

                $requirements[$reqKey]['qty_buy_needed'] = round((float)$requirements[$reqKey]['qty_buy_needed'] + $qtyBuy, 4);
                $requirements[$reqKey]['qty_content_needed'] = round((float)$requirements[$reqKey]['qty_content_needed'] + $qtyContent, 4);

                $lineNo = (int)($line['line_no'] ?? 0);
                $requirements[$reqKey]['refs'][] = (string)($receipt['receipt_no'] ?? ('RCV#' . $receiptId)) . ($lineNo > 0 ? (' L' . $lineNo) : '');
            }
        }

        $blocked = [];
        foreach ($requirements as $req) {
            $current = $this->fetchVoidRollbackCurrentBalance($req);
            $needBuy = (float)($req['qty_buy_needed'] ?? 0);
            $needContent = (float)($req['qty_content_needed'] ?? 0);
            $hasBuy = (float)($current['qty_buy_balance'] ?? 0);
            $hasContent = (float)($current['qty_content_balance'] ?? 0);

            if ($hasBuy + 0.0001 < $needBuy || $hasContent + 0.0001 < $needContent) {
                $scopeLabel = (string)($req['scope'] ?? '') === 'WAREHOUSE'
                    ? 'GUDANG'
                    : ('DIVISI ' . (string)($req['division_id'] ?? '-'));
                $nameLabel = trim((string)($req['profile_name'] ?? ''));
                if ($nameLabel === '') {
                    if (!empty($req['item_id'])) {
                        $nameLabel = 'ITEM#' . (string)$req['item_id'];
                    } elseif (!empty($req['material_id'])) {
                        $nameLabel = 'MATERIAL#' . (string)$req['material_id'];
                    } else {
                        $nameLabel = 'PROFILE#' . substr((string)($req['profile_key'] ?? ''), 0, 8);
                    }
                }

                $blocked[] = $scopeLabel
                    . ' - ' . $nameLabel
                    . ' (butuh ' . number_format($needBuy, 2, ',', '.') . ' beli / ' . number_format($needContent, 2, ',', '.') . ' isi, tersedia '
                    . number_format($hasBuy, 2, ',', '.') . ' beli / ' . number_format($hasContent, 2, ',', '.') . ' isi)'
                    . ' [sumber: ' . implode(', ', array_unique((array)($req['refs'] ?? []))) . ']';
            }
        }

        if (!empty($blocked)) {
            return [
                'ok' => false,
                'message' => 'VOID ditolak karena sebagian stok receipt sudah terpakai. Detail: ' . implode(' ; ', $blocked),
            ];
        }

        foreach ($receipts as $receipt) {
            $receiptId = (int)($receipt['id'] ?? 0);
            if ($receiptId <= 0) {
                continue;
            }

            $scope = strtoupper((string)($receipt['destination_type'] ?? '')) === 'GUDANG' ? 'WAREHOUSE' : 'DIVISION';
            $divisionId = $scope === 'DIVISION' ? $this->nullableInt($receipt['destination_division_id'] ?? null) : null;
            if ($scope === 'DIVISION' && $divisionId === null) {
                return [
                    'ok' => false,
                    'message' => 'Rollback receipt gagal: division tujuan tidak valid untuk receipt #' . (string)($receipt['receipt_no'] ?? $receiptId),
                ];
            }

            $lines = (array)($receiptLinesById[$receiptId] ?? []);

            foreach ($lines as $line) {
                $qtyBuy = abs(round((float)($line['qty_buy_received'] ?? 0), 4));
                $qtyContent = abs(round((float)($line['qty_content_received'] ?? 0), 4));
                if ($qtyBuy <= 0 && $qtyContent <= 0) {
                    continue;
                }

                $factor = (float)($line['conversion_factor_to_content'] ?? 0);
                if ($factor <= 0) {
                    $factor = (float)($line['content_per_buy'] ?? 1);
                }
                if ($factor <= 0) {
                    $factor = 1;
                }

                $unitPrice = (float)($line['unit_price'] ?? 0);
                $unitCost = $factor > 0 ? round($unitPrice / $factor, 6) : 0;

                $profileName = trim((string)($line['snapshot_item_name'] ?? ''));
                if ($profileName === '') {
                    $profileName = trim((string)($line['snapshot_material_name'] ?? ''));
                }

                $post = $this->postInventoryLedgerEntry([
                    'manage_transaction' => false,
                    'movement_date' => $rollbackDate,
                    'movement_scope' => $scope,
                    'division_id' => $divisionId,
                    'movement_type' => 'ADJUSTMENT',
                    'stock_domain' => strtoupper((string)($line['line_kind'] ?? 'ITEM')) === 'MATERIAL' ? 'MATERIAL' : 'ITEM',
                    'ref_table' => 'pur_purchase_order',
                    'ref_id' => $purchaseOrderId,
                    'receipt_id' => $receiptId,
                    'receipt_line_id' => (int)($line['id'] ?? 0),
                    'item_id' => $this->nullableInt($line['item_id'] ?? null),
                    'material_id' => $this->nullableInt($line['material_id'] ?? null),
                    'buy_uom_id' => $this->nullableInt($line['buy_uom_id'] ?? null),
                    'content_uom_id' => $this->nullableInt($line['content_uom_id'] ?? null),
                    'qty_buy_delta' => $qtyBuy > 0 ? (-1 * $qtyBuy) : 0,
                    'qty_content_delta' => $qtyContent > 0 ? (-1 * $qtyContent) : 0,
                    'profile_key' => $this->nullableString($line['profile_key'] ?? null),
                    'profile_name' => $this->nullableString($profileName !== '' ? $profileName : null),
                    'profile_brand' => $this->nullableString(($line['brand_name'] ?? null) ?: ($line['snapshot_brand_name'] ?? null)),
                    'profile_description' => $this->nullableString(($line['line_description'] ?? null) ?: ($line['snapshot_line_description'] ?? null)),
                    'profile_content_per_buy' => (float)($line['content_per_buy'] ?? $factor),
                    'profile_buy_uom_code' => $this->nullableString($line['snapshot_buy_uom_code'] ?? null),
                    'profile_content_uom_code' => $this->nullableString($line['snapshot_content_uom_code'] ?? null),
                    'unit_cost' => $unitCost,
                    'notes' => 'Rollback otomatis stok karena status PO diubah ke VOID',
                    'created_by' => $userId > 0 ? $userId : null,
                ]);

                if (!($post['ok'] ?? false)) {
                    return [
                        'ok' => false,
                        'message' => 'Rollback stok gagal untuk receipt #' . (string)($receipt['receipt_no'] ?? $receiptId) . ': ' . (string)($post['message'] ?? 'gagal posting inventory ledger'),
                    ];
                }

                $linesReversed++;
            }

            $existingNotes = trim((string)($receipt['notes'] ?? ''));
            $voidNote = 'Receipt di-VOID otomatis karena status PO diubah ke VOID';
            $updateReceipt = [
                'status' => 'VOID',
                'notes' => $existingNotes !== '' ? ($existingNotes . ' | ' . $voidNote) : $voidNote,
            ];

            $this->db->where('id', $receiptId)->update('pur_purchase_receipt', $updateReceipt);
            if ((int)($this->db->error()['code'] ?? 0) !== 0) {
                return [
                    'ok' => false,
                    'message' => 'Rollback receipt gagal saat update status VOID.',
                ];
            }

            if ($this->db->table_exists('aud_transaction_log')) {
                $this->db->insert('aud_transaction_log', [
                    'module_code' => 'PURCHASE',
                    'action_code' => 'RECEIPT_VOID_ROLLBACK',
                    'entity_table' => 'pur_purchase_receipt',
                    'entity_id' => $receiptId,
                    'transaction_no' => (string)($receipt['receipt_no'] ?? null),
                    'ref_table' => 'pur_purchase_order',
                    'ref_id' => $purchaseOrderId,
                    'actor_user_id' => $userId > 0 ? $userId : null,
                    'source_ip' => $sourceIp !== '' ? $sourceIp : null,
                    'after_payload' => json_encode([
                        'purchase_order_id' => $purchaseOrderId,
                        'receipt_id' => $receiptId,
                        'lines_reversed' => $linesReversed,
                    ]),
                    'notes' => 'Rollback receipt otomatis saat VOID PO',
                ]);
            }

            $receiptsVoided++;
        }

        return [
            'ok' => true,
            'data' => [
                'receipts_voided' => $receiptsVoided,
                'lines_reversed' => $linesReversed,
            ],
        ];
    }

    private function buildVoidRollbackRequirementKey(array $requirement): string
    {
        return implode('|', [
            strtoupper((string)($requirement['scope'] ?? '')),
            (string)($requirement['division_id'] ?? 0),
            (string)($requirement['item_id'] ?? 0),
            (string)($requirement['material_id'] ?? 0),
            (string)($requirement['buy_uom_id'] ?? 0),
            (string)($requirement['content_uom_id'] ?? 0),
            strtoupper((string)($requirement['profile_key'] ?? '')),
        ]);
    }

    private function fetchVoidRollbackCurrentBalance(array $requirement): array
    {
        $scope = strtoupper((string)($requirement['scope'] ?? ''));
        if ($scope === 'WAREHOUSE') {
            if (!$this->db->table_exists('inv_warehouse_stock_balance')) {
                return ['qty_buy_balance' => 0.0, 'qty_content_balance' => 0.0];
            }

            $row = $this->db->query(
                'SELECT qty_buy_balance, qty_content_balance
                 FROM inv_warehouse_stock_balance
                 WHERE item_id <=> ? AND buy_uom_id <=> ? AND content_uom_id <=> ? AND profile_key <=> ?
                 LIMIT 1',
                [
                    $requirement['item_id'] ?? null,
                    $requirement['buy_uom_id'] ?? null,
                    $requirement['content_uom_id'] ?? null,
                    $requirement['profile_key'] ?? null,
                ]
            )->row_array();

            return [
                'qty_buy_balance' => (float)($row['qty_buy_balance'] ?? 0),
                'qty_content_balance' => (float)($row['qty_content_balance'] ?? 0),
            ];
        }

        if (!$this->db->table_exists('inv_division_stock_balance')) {
            return ['qty_buy_balance' => 0.0, 'qty_content_balance' => 0.0];
        }

        $row = $this->db->query(
            'SELECT qty_buy_balance, qty_content_balance
             FROM inv_division_stock_balance
             WHERE division_id <=> ?
               AND item_id <=> ?
               AND material_id <=> ?
               AND buy_uom_id <=> ?
               AND content_uom_id <=> ?
               AND profile_key <=> ?
             LIMIT 1',
            [
                $requirement['division_id'] ?? null,
                $requirement['item_id'] ?? null,
                $requirement['material_id'] ?? null,
                $requirement['buy_uom_id'] ?? null,
                $requirement['content_uom_id'] ?? null,
                $requirement['profile_key'] ?? null,
            ]
        )->row_array();

        return [
            'qty_buy_balance' => (float)($row['qty_buy_balance'] ?? 0),
            'qty_content_balance' => (float)($row['qty_content_balance'] ?? 0),
        ];
    }

    private function postInventoryLedgerEntry(array $payload): array
    {
        $this->load->library('InventoryLedger');
        return $this->inventoryledger->post($payload);
    }

    private function rollbackPaidPlansOnVoid(int $purchaseOrderId, int $fallbackAccountId, int $userId, string $sourceIp = ''): array
    {
        if (!$this->db->table_exists('pur_purchase_payment_plan')) {
            return ['ok' => true, 'data' => ['plans_voided' => 0, 'amount_restored' => 0.0]];
        }

        $paidPlans = $this->db
            ->from('pur_purchase_payment_plan')
            ->where('purchase_order_id', $purchaseOrderId)
            ->where('status', 'PAID')
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();

        if (empty($paidPlans)) {
            return ['ok' => true, 'data' => ['plans_voided' => 0, 'amount_restored' => 0.0]];
        }

        if (!$this->db->table_exists('fin_company_account')) {
            return [
                'ok' => false,
                'message' => 'Rollback pembayaran gagal: tabel fin_company_account tidak tersedia.',
            ];
        }

        $hasPaidFromAccount = $this->db->field_exists('paid_from_account_id', 'pur_purchase_payment_plan');
        $plansVoided = 0;
        $amountRestored = 0.0;

        foreach ($paidPlans as $plan) {
            $planId = (int)($plan['id'] ?? 0);
            if ($planId <= 0) {
                continue;
            }

            $amount = round((float)($plan['paid_amount'] ?? 0), 2);
            $accountId = $hasPaidFromAccount ? (int)($plan['paid_from_account_id'] ?? 0) : 0;
            if ($accountId <= 0) {
                $accountId = $fallbackAccountId;
            }

            if ($amount > 0) {
                if ($accountId <= 0) {
                    return [
                        'ok' => false,
                        'message' => 'Rollback pembayaran gagal: akun asal pembayaran tidak ditemukan pada payment plan #' . $planId . '.',
                    ];
                }

                $account = $this->db
                    ->query('SELECT * FROM fin_company_account WHERE id = ? LIMIT 1 FOR UPDATE', [$accountId])
                    ->row_array();
                if (!$account) {
                    return [
                        'ok' => false,
                        'message' => 'Rollback pembayaran gagal: akun pembayaran tidak ditemukan untuk payment plan #' . $planId . '.',
                    ];
                }

                $balanceBefore = (float)($account['current_balance'] ?? 0);
                $balanceAfter = round($balanceBefore + $amount, 2);

                $this->db->where('id', $accountId)->update('fin_company_account', [
                    'current_balance' => $balanceAfter,
                ]);
                if ((int)($this->db->error()['code'] ?? 0) !== 0) {
                    return [
                        'ok' => false,
                        'message' => 'Rollback pembayaran gagal saat mengembalikan saldo rekening.',
                    ];
                }

                $mutationNo = $this->generateAccountMutationNo(date('Y-m-d'));
                if ($this->db->table_exists('fin_account_mutation_log')) {
                    $this->db->insert('fin_account_mutation_log', [
                        'mutation_no' => $mutationNo,
                        'mutation_date' => date('Y-m-d'),
                        'account_id' => $accountId,
                        'mutation_type' => 'IN',
                        'amount' => $amount,
                        'balance_before' => round($balanceBefore, 2),
                        'balance_after' => round($balanceAfter, 2),
                        'ref_module' => 'PURCHASE',
                        'ref_table' => 'pur_purchase_order',
                        'ref_id' => $purchaseOrderId,
                        'ref_no' => $this->nullableString($plan['transaction_no'] ?? null),
                        'notes' => 'Rollback otomatis pembayaran karena status PO diubah ke VOID',
                        'created_by' => $userId > 0 ? $userId : null,
                    ]);
                }

                if ($this->db->table_exists('aud_transaction_log')) {
                    $this->db->insert('aud_transaction_log', [
                        'module_code' => 'PURCHASE',
                        'action_code' => 'PAYMENT_VOID_ROLLBACK',
                        'entity_table' => 'pur_purchase_payment_plan',
                        'entity_id' => $planId,
                        'transaction_no' => $this->nullableString($plan['transaction_no'] ?? null),
                        'ref_table' => 'pur_purchase_order',
                        'ref_id' => $purchaseOrderId,
                        'actor_user_id' => $userId > 0 ? $userId : null,
                        'source_ip' => $sourceIp !== '' ? $sourceIp : null,
                        'after_payload' => json_encode([
                            'payment_plan_id' => $planId,
                            'paid_from_account_id' => $accountId,
                            'amount_restored' => $amount,
                        ]),
                        'notes' => 'Rollback pembayaran otomatis saat VOID PO',
                    ]);
                }

                $amountRestored = round($amountRestored + $amount, 2);
            }

            $currentNotes = trim((string)($plan['notes'] ?? ''));
            $voidNote = 'Payment di-VOID otomatis karena status PO diubah ke VOID';
            $updatePlan = [
                'status' => 'VOID',
                'notes' => $currentNotes !== '' ? ($currentNotes . ' | ' . $voidNote) : $voidNote,
            ];

            $this->db->where('id', $planId)->update('pur_purchase_payment_plan', $updatePlan);
            if ((int)($this->db->error()['code'] ?? 0) !== 0) {
                return [
                    'ok' => false,
                    'message' => 'Rollback pembayaran gagal saat update status payment plan ke VOID.',
                ];
            }

            $plansVoided++;
        }

        return [
            'ok' => true,
            'data' => [
                'plans_voided' => $plansVoided,
                'amount_restored' => $amountRestored,
            ],
        ];
    }

    public function list_active_purchase_types(): array
    {
        if (!$this->db->table_exists('mst_purchase_type')) {
            return [];
        }

        return $this->db
            ->select('t.id, t.type_code, t.type_name, t.destination_behavior, t.default_destination')
            ->select('p.type_code AS posting_type_code, p.affects_inventory')
            ->from('mst_purchase_type t')
            ->join('mst_posting_type p', 'p.id = t.posting_type_id', 'left')
            ->where('t.is_active', 1)
            ->order_by('t.id', 'ASC')
            ->get()
            ->result_array();
    }

    public function list_active_vendors(): array
    {
        if (!$this->db->table_exists('mst_vendor')) {
            return [];
        }

        return $this->db
            ->select('id, vendor_code, vendor_name')
            ->from('mst_vendor')
            ->where('is_active', 1)
            ->order_by('vendor_name', 'ASC')
            ->get()
            ->result_array();
    }

    public function list_active_payment_accounts(): array
    {
        if (!$this->db->table_exists('fin_company_account')) {
            return [];
        }

        return $this->db
            ->select('id, account_code, account_name, bank_name, account_no, current_balance, currency_code')
            ->from('fin_company_account')
            ->where('is_active', 1)
            ->order_by('is_default', 'DESC')
            ->order_by('account_name', 'ASC')
            ->get()
            ->result_array();
    }

    public function list_active_operational_divisions(): array
    {
        if (!$this->db->table_exists('mst_operational_division')) {
            return [];
        }

        $hasCode = $this->db->field_exists('code', 'mst_operational_division');
        $hasName = $this->db->field_exists('name', 'mst_operational_division');

        $select = 'id';
        if ($hasCode) {
            $select .= ', code';
        }
        if ($hasName) {
            $select .= ', name';
        }

        $qb = $this->db->select($select, false)->from('mst_operational_division');
        if ($this->db->field_exists('is_active', 'mst_operational_division')) {
            $qb->where('is_active', 1);
        }
        if ($this->db->field_exists('sort_order', 'mst_operational_division')) {
            $qb->order_by('sort_order', 'ASC');
        }
        if ($hasName) {
            $qb->order_by('name', 'ASC');
        }

        return $qb->get()->result_array();
    }

    public function get_po_lines_for_receipt(int $purchaseOrderId): array
    {
        if ($purchaseOrderId <= 0 || !$this->db->table_exists('pur_purchase_order_line')) {
            return [];
        }

        $receivedSub = $this->db
            ->select('rl.purchase_order_line_id, SUM(rl.qty_buy_received) AS qty_buy_received_total, SUM(rl.qty_content_received) AS qty_content_received_total', false)
            ->from('pur_purchase_receipt_line rl')
            ->join('pur_purchase_receipt r', 'r.id = rl.purchase_receipt_id', 'inner')
            ->where('r.status !=', 'VOID')
            ->group_by('rl.purchase_order_line_id')
            ->get_compiled_select();

        return $this->db
            ->select('l.id AS purchase_order_line_id, l.line_no, l.line_kind, l.item_id, l.material_id')
            ->select('l.snapshot_item_name, l.snapshot_material_name, l.snapshot_brand_name, l.snapshot_line_description')
            ->select('l.buy_uom_id, l.content_uom_id, l.snapshot_buy_uom_code, l.snapshot_content_uom_code')
            ->select('l.qty_buy, l.qty_content, l.content_per_buy, l.conversion_factor_to_content, l.unit_price, l.profile_key')
            ->select('COALESCE(rcv.qty_buy_received_total, 0) AS qty_buy_received_total', false)
            ->select('COALESCE(rcv.qty_content_received_total, 0) AS qty_content_received_total', false)
            ->from('pur_purchase_order_line l')
            ->join("({$receivedSub}) rcv", 'rcv.purchase_order_line_id = l.id', 'left', false)
            ->where('l.purchase_order_id', $purchaseOrderId)
            ->order_by('l.line_no', 'ASC')
            ->get()
            ->result_array();
    }

    public function store_receipt_and_post(array $payload, int $userId, string $sourceIp = ''): array
    {
        if (
            !$this->db->table_exists('pur_purchase_receipt') ||
            !$this->db->table_exists('pur_purchase_receipt_line') ||
            !$this->db->table_exists('pur_purchase_order') ||
            !$this->db->table_exists('pur_purchase_order_line')
        ) {
            return [
                'ok' => false,
                'message' => 'Tabel receipt/PO belum lengkap. Jalankan SQL fondasi purchase terbaru terlebih dahulu.',
            ];
        }

        $header = (array)($payload['header'] ?? []);
        $lines = $payload['lines'] ?? [];
        if (!is_array($lines) || empty($lines)) {
            return [
                'ok' => false,
                'message' => 'Baris receipt wajib diisi.',
            ];
        }

        $purchaseOrderId = (int)($header['purchase_order_id'] ?? 0);
        $receiptDate = $this->normalizeDate((string)($header['receipt_date'] ?? date('Y-m-d')));
        $destinationType = $this->normalizeDestination((string)($header['destination_type'] ?? 'GUDANG'));
        $destinationDivisionId = $this->nullableInt($header['destination_division_id'] ?? null);

        if ($purchaseOrderId <= 0 || $receiptDate === null || $destinationType === null) {
            return [
                'ok' => false,
                'message' => 'Header receipt belum lengkap: purchase_order_id, receipt_date, destination_type wajib valid.',
            ];
        }
        if ($destinationType !== 'GUDANG' && $destinationDivisionId === null) {
            return [
                'ok' => false,
                'message' => 'destination_division_id wajib untuk tujuan non-gudang.',
            ];
        }

        $order = $this->db->get_where('pur_purchase_order', ['id' => $purchaseOrderId])->row_array();
        if (!$order) {
            return [
                'ok' => false,
                'message' => 'Purchase order tidak ditemukan.',
            ];
        }

        $poLines = $this->get_po_lines_for_receipt($purchaseOrderId);
        $lineMap = [];
        foreach ($poLines as $line) {
            $lineMap[(int)$line['purchase_order_line_id']] = $line;
        }

        $this->load->library('InventoryLedger');
        $this->db->trans_begin();

        $receiptNo = trim((string)($header['receipt_no'] ?? ''));
        if ($receiptNo === '') {
            $receiptNo = $this->generateReceiptNo($receiptDate);
        }

        $receiptData = [
            'receipt_no' => strtoupper($receiptNo),
            'purchase_order_id' => $purchaseOrderId,
            'receipt_date' => $receiptDate,
            'destination_type' => $destinationType,
            'destination_division_id' => $destinationDivisionId,
            'status' => 'DRAFT',
            'notes' => $this->nullableString($header['notes'] ?? null),
            'created_by' => $userId > 0 ? $userId : null,
        ];

        $this->db->insert('pur_purchase_receipt', $receiptData);
        $receiptId = (int)$this->db->insert_id();
        if ($receiptId <= 0) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => 'Gagal membuat header receipt.',
            ];
        }

        $lineCount = 0;
        foreach ($lines as $rawLine) {
            $line = (array)$rawLine;
            $poLineId = (int)($line['purchase_order_line_id'] ?? 0);
            $qtyBuyReceived = round((float)($line['qty_buy_received'] ?? 0), 4);
            if ($poLineId <= 0 || $qtyBuyReceived <= 0) {
                continue;
            }

            if (!isset($lineMap[$poLineId])) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => 'Baris PO tidak ditemukan: ' . $poLineId,
                ];
            }

            $poLine = $lineMap[$poLineId];
            $orderedQtyBuy = (float)($poLine['qty_buy'] ?? 0);
            $alreadyReceived = (float)($poLine['qty_buy_received_total'] ?? 0);
            $remainingQtyBuy = round(max(0, $orderedQtyBuy - $alreadyReceived), 4);
            $allowOver = !empty($line['allow_over_receive']);
            if (!$allowOver && $qtyBuyReceived > $remainingQtyBuy + 0.0001) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => 'Qty diterima melebihi sisa untuk line #' . (int)($poLine['line_no'] ?? 0),
                ];
            }

            $factor = (float)($poLine['conversion_factor_to_content'] ?? 1);
            if ($factor <= 0) {
                $factor = 1;
            }
            $qtyContentReceived = round($qtyBuyReceived * $factor, 4);

            $receiptLineData = [
                'purchase_receipt_id' => $receiptId,
                'purchase_order_line_id' => $poLineId,
                'line_kind' => (string)($poLine['line_kind'] ?? 'ITEM'),
                'item_id' => $this->nullableInt($poLine['item_id'] ?? null),
                'material_id' => $this->nullableInt($poLine['material_id'] ?? null),
                'qty_buy_received' => $qtyBuyReceived,
                'buy_uom_id' => (int)($poLine['buy_uom_id'] ?? 0),
                'qty_content_received' => $qtyContentReceived,
                'content_uom_id' => $this->nullableInt($poLine['content_uom_id'] ?? null),
                'conversion_factor_to_content' => round($factor, 8),
                'brand_name' => $this->nullableString($poLine['snapshot_brand_name'] ?? null),
                'line_description' => $this->nullableString($poLine['snapshot_line_description'] ?? null),
                'profile_key' => $this->nullableString($poLine['profile_key'] ?? null),
                'notes' => $this->nullableString($line['notes'] ?? null),
            ];

            $this->db->insert('pur_purchase_receipt_line', $receiptLineData);
            $receiptLineId = (int)$this->db->insert_id();
            if ($receiptLineId <= 0) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => 'Gagal menyimpan receipt line.',
                ];
            }

            $profileName = trim((string)($poLine['snapshot_item_name'] ?? ''));
            if ($profileName === '') {
                $profileName = trim((string)($poLine['snapshot_material_name'] ?? ''));
            }

            $movementScope = $destinationType === 'GUDANG' ? 'WAREHOUSE' : 'DIVISION';
            $unitPrice = (float)($poLine['unit_price'] ?? 0);
            $unitCost = $factor > 0 ? round($unitPrice / $factor, 6) : 0;

            $ledger = $this->postInventoryLedgerEntry([
                'manage_transaction' => false,
                'movement_date' => $receiptDate,
                'movement_scope' => $movementScope,
                'division_id' => $movementScope === 'DIVISION' ? $destinationDivisionId : null,
                'movement_type' => 'PURCHASE_IN',
                'stock_domain' => strtoupper((string)($poLine['line_kind'] ?? 'ITEM')) === 'MATERIAL' ? 'MATERIAL' : 'ITEM',
                'ref_table' => 'pur_purchase_receipt',
                'ref_id' => $receiptId,
                'receipt_id' => $receiptId,
                'receipt_line_id' => $receiptLineId,
                'item_id' => $this->nullableInt($poLine['item_id'] ?? null),
                'material_id' => $this->nullableInt($poLine['material_id'] ?? null),
                'buy_uom_id' => $this->nullableInt($poLine['buy_uom_id'] ?? null),
                'content_uom_id' => $this->nullableInt($poLine['content_uom_id'] ?? null),
                'qty_buy_delta' => $qtyBuyReceived,
                'qty_content_delta' => $qtyContentReceived,
                'profile_key' => $this->nullableString($poLine['profile_key'] ?? null),
                'profile_name' => $this->nullableString($profileName),
                'profile_brand' => $this->nullableString($poLine['snapshot_brand_name'] ?? null),
                'profile_description' => $this->nullableString($poLine['snapshot_line_description'] ?? null),
                'profile_content_per_buy' => (float)($poLine['content_per_buy'] ?? 1),
                'profile_buy_uom_code' => $this->nullableString($poLine['snapshot_buy_uom_code'] ?? null),
                'profile_content_uom_code' => $this->nullableString($poLine['snapshot_content_uom_code'] ?? null),
                'unit_cost' => $unitCost,
                'notes' => $this->nullableString($line['notes'] ?? null),
                'created_by' => $userId > 0 ? $userId : null,
            ]);

            if (!($ledger['ok'] ?? false)) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => (string)($ledger['message'] ?? 'Gagal posting inventory ledger.'),
                ];
            }

            $lineCount++;
        }

        if ($lineCount <= 0) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => 'Tidak ada line receipt yang valid untuk disimpan.',
            ];
        }

        $this->db->where('id', $receiptId)->update('pur_purchase_receipt', [
            'status' => 'POSTED',
            'posted_by' => $userId > 0 ? $userId : null,
            'posted_at' => date('Y-m-d H:i:s'),
        ]);

        if ($this->db->table_exists('aud_transaction_log')) {
            $this->db->insert('aud_transaction_log', [
                'module_code' => 'PURCHASE',
                'action_code' => 'RECEIPT_POST',
                'entity_table' => 'pur_purchase_receipt',
                'entity_id' => $receiptId,
                'transaction_no' => $receiptData['receipt_no'],
                'ref_table' => 'pur_purchase_order',
                'ref_id' => $purchaseOrderId,
                'actor_user_id' => $userId > 0 ? $userId : null,
                'source_ip' => $sourceIp !== '' ? $sourceIp : null,
                'after_payload' => json_encode([
                    'receipt_no' => $receiptData['receipt_no'],
                    'destination_type' => $destinationType,
                    'destination_division_id' => $destinationDivisionId,
                    'line_count' => $lineCount,
                ]),
                'notes' => 'Auto log posting receipt purchase',
            ]);
        }

        $txnLog = $this->writePurchaseTxnLog([
            'purchase_order_id' => $purchaseOrderId,
            'purchase_receipt_id' => $receiptId,
            'action_code' => 'RECEIPT_POST',
            'status_before' => strtoupper((string)($order['status'] ?? '')),
            'status_after' => strtoupper((string)($order['status'] ?? '')),
            'transaction_no' => (string)($receiptData['receipt_no'] ?? ''),
            'ref_table' => 'pur_purchase_receipt',
            'ref_id' => $receiptId,
            'payload' => [
                'destination_type' => $destinationType,
                'destination_division_id' => $destinationDivisionId,
                'line_count' => $lineCount,
            ],
            'notes' => 'Posting receipt purchase',
            'created_by' => $userId,
        ]);
        if (!($txnLog['ok'] ?? false)) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => (string)($txnLog['message'] ?? 'Gagal mencatat log transaksi purchase.'),
            ];
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => 'Gagal menyimpan receipt purchase.',
            ];
        }

        $this->db->trans_commit();

        return [
            'ok' => true,
            'message' => 'Receipt berhasil diposting dan stok sudah diperbarui.',
            'data' => [
                'receipt_id' => $receiptId,
                'receipt_no' => $receiptData['receipt_no'],
                'purchase_order_id' => $purchaseOrderId,
                'line_count' => $lineCount,
            ],
        ];
    }

    public function apply_payment(array $payload, int $userId, string $sourceIp = ''): array
    {
        if (!$this->db->table_exists('pur_purchase_payment_plan') || !$this->db->table_exists('fin_company_account')) {
            return [
                'ok' => false,
                'message' => 'Tabel company account/purchase payment plan belum tersedia. Jalankan migration terbaru terlebih dahulu.',
            ];
        }

        $purchaseOrderId = (int)($payload['purchase_order_id'] ?? 0);
        $paymentChannelId = (int)($payload['payment_channel_id'] ?? 0);
        $accountId = (int)($payload['paid_from_account_id'] ?? ($payload['account_id'] ?? 0));
        $amount = round((float)($payload['amount'] ?? 0), 2);
        $paymentDate = $this->normalizeDate((string)($payload['payment_date'] ?? date('Y-m-d')));

        if ($purchaseOrderId <= 0 || $amount <= 0) {
            return [
                'ok' => false,
                'message' => 'purchase_order_id dan amount wajib valid.',
            ];
        }
        if ($paymentDate === null) {
            return [
                'ok' => false,
                'message' => 'payment_date tidak valid.',
            ];
        }

        $order = $this->db->get_where('pur_purchase_order', ['id' => $purchaseOrderId])->row_array();
        if (!$order) {
            return [
                'ok' => false,
                'message' => 'Purchase order tidak ditemukan.',
            ];
        }

        $channel = null;
        if ($paymentChannelId > 0 && $this->db->table_exists('pur_payment_channel')) {
            $channel = $this->db
                ->from('pur_payment_channel')
                ->where('id', $paymentChannelId)
                ->where('is_active', 1)
                ->get()
                ->row_array();
            if (!$channel) {
                return [
                    'ok' => false,
                    'message' => 'Payment channel tidak ditemukan atau tidak aktif.',
                ];
            }
        }

        if ($accountId <= 0 && $channel) {
            $accountId = (int)($channel['company_account_id'] ?? 0);
        }
        if ($accountId <= 0) {
            return [
                'ok' => false,
                'message' => 'paid_from_account_id/account_id wajib diisi.',
            ];
        }

        $this->db->trans_begin();

        $account = $this->db->get_where('fin_company_account', ['id' => $accountId, 'is_active' => 1])->row_array();
        if (!$account) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => 'Akun pembayaran tidak ditemukan.',
            ];
        }

        $balance = (float)($account['current_balance'] ?? 0);
        if ($balance < $amount) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => 'Saldo akun tidak cukup.',
            ];
        }

        $this->db->where('id', $accountId)->update('fin_company_account', [
            'current_balance' => round($balance - $amount, 2),
        ]);

        if ($this->db->table_exists('fin_account_mutation_log')) {
            $this->db->insert('fin_account_mutation_log', [
                'mutation_no' => $this->generateAccountMutationNo($paymentDate),
                'mutation_date' => $paymentDate,
                'account_id' => $accountId,
                'mutation_type' => 'OUT',
                'amount' => $amount,
                'balance_before' => round($balance, 2),
                'balance_after' => round($balance - $amount, 2),
                'ref_module' => 'PURCHASE',
                'ref_table' => 'pur_purchase_order',
                'ref_id' => $purchaseOrderId,
                'ref_no' => null,
                'notes' => 'Pembayaran purchase order',
                'created_by' => $userId > 0 ? $userId : null,
            ]);
        }

        $transactionNo = trim((string)($payload['transaction_no'] ?? ''));
        if ($transactionNo === '') {
            $transactionNo = $this->generatePaymentNo($paymentDate);
        }

        $planData = [
            'purchase_order_id' => $purchaseOrderId,
            'plan_type' => 'PARTIAL',
            'terms_days' => (int)($payload['terms_days'] ?? 0),
            'due_date' => $this->normalizeDate((string)($payload['due_date'] ?? '')),
            'payment_date' => $paymentDate,
            'planned_amount' => $amount,
            'paid_amount' => $amount,
            'status' => 'PAID',
            'reference_no' => $this->nullableString($payload['reference_no'] ?? null),
            'transaction_no' => $transactionNo,
            'notes' => $this->nullableString($payload['notes'] ?? null),
            'created_by' => $userId > 0 ? $userId : null,
        ];

        if ($this->db->field_exists('payment_method_id', 'pur_purchase_payment_plan')) {
            $planData['payment_method_id'] = null;
        }
        if ($this->db->field_exists('payment_channel_id', 'pur_purchase_payment_plan')) {
            $planData['payment_channel_id'] = $paymentChannelId > 0 ? $paymentChannelId : null;
        }
        if ($this->db->field_exists('paid_from_account_id', 'pur_purchase_payment_plan')) {
            $planData['paid_from_account_id'] = $accountId;
        }

        $this->db->insert('pur_purchase_payment_plan', $planData);
        $planId = (int)$this->db->insert_id();

        $totalPaid = 0.0;
        $statusAfter = strtoupper((string)($order['status'] ?? 'DRAFT'));
        $grandTotal = round((float)($order['grand_total'] ?? 0), 2);
        if ($this->db->table_exists('pur_purchase_payment_plan')) {
            $paidRow = $this->db
                ->select('COALESCE(SUM(paid_amount), 0) AS total_paid', false)
                ->from('pur_purchase_payment_plan')
                ->where('purchase_order_id', $purchaseOrderId)
                ->where('status', 'PAID')
                ->get()
                ->row_array();
            $totalPaid = round((float)($paidRow['total_paid'] ?? 0), 2);
        }

        $outstanding = max(0, round($grandTotal - $totalPaid, 2));
        if ($grandTotal > 0 && $totalPaid >= $grandTotal && $statusAfter === 'RECEIVED') {
            $this->db->where('id', $purchaseOrderId)->update('pur_purchase_order', ['status' => 'PAID']);
            if (!((int)($this->db->error()['code'] ?? 0))) {
                $statusAfter = 'PAID';
            }
        }

        if ($this->db->table_exists('aud_transaction_log')) {
            $this->db->insert('aud_transaction_log', [
                'module_code' => 'PURCHASE',
                'action_code' => 'PAYMENT_APPLY',
                'entity_table' => 'pur_purchase_payment_plan',
                'entity_id' => $planId > 0 ? $planId : null,
                'transaction_no' => $transactionNo,
                'ref_table' => 'pur_purchase_order',
                'ref_id' => $purchaseOrderId,
                'actor_user_id' => $userId > 0 ? $userId : null,
                'source_ip' => $sourceIp !== '' ? $sourceIp : null,
                'after_payload' => json_encode([
                    'purchase_order_id' => $purchaseOrderId,
                    'paid_from_account_id' => $accountId,
                    'amount' => $amount,
                    'payment_date' => $paymentDate,
                    'reference_no' => $planData['reference_no'],
                    'total_paid' => $totalPaid,
                    'outstanding' => $outstanding,
                    'purchase_order_status_after' => $statusAfter,
                ]),
                'notes' => 'Auto log pembayaran purchase',
            ]);
        }

        $txnLog = $this->writePurchaseTxnLog([
            'purchase_order_id' => $purchaseOrderId,
            'payment_plan_id' => $planId,
            'action_code' => 'PAYMENT_APPLY',
            'status_before' => strtoupper((string)($order['status'] ?? '')),
            'status_after' => $statusAfter,
            'transaction_no' => $transactionNo,
            'ref_table' => 'pur_purchase_payment_plan',
            'ref_id' => $planId > 0 ? $planId : null,
            'amount' => $amount,
            'payload' => [
                'purchase_order_id' => $purchaseOrderId,
                'paid_from_account_id' => $accountId,
                'payment_date' => $paymentDate,
                'total_paid' => $totalPaid,
                'outstanding' => $outstanding,
            ],
            'notes' => 'Pembayaran purchase order',
            'created_by' => $userId,
        ]);
        if (!($txnLog['ok'] ?? false)) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => (string)($txnLog['message'] ?? 'Gagal mencatat log transaksi purchase.'),
            ];
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => 'Gagal memproses pembayaran.',
            ];
        }

        $this->db->trans_commit();

        return [
            'ok' => true,
            'message' => 'Pembayaran berhasil diproses dan saldo sudah berkurang.',
            'data' => [
                'purchase_order_id' => $purchaseOrderId,
                'payment_plan_id' => $planId,
                'transaction_no' => $transactionNo,
                'amount' => $amount,
                'paid_from_account_id' => $accountId,
                'payment_date' => $paymentDate,
                'po_status_after' => $statusAfter,
                'total_paid' => $totalPaid,
                'outstanding' => $outstanding,
            ],
        ];
    }

    public function store_order_with_lines(array $header, array $lines, int $userId, string $sourceIp = ''): array
    {
        if (!$this->db->table_exists('pur_purchase_order') || !$this->db->table_exists('pur_purchase_order_line')) {
            return [
                'ok' => false,
                'message' => 'Tabel purchase belum tersedia. Jalankan SQL fondasi Tahap 6 terlebih dahulu.',
            ];
        }

        $requestDate = $this->normalizeDate((string)($header['request_date'] ?? ''));
        if ($requestDate === null) {
            return [
                'ok' => false,
                'message' => 'Format request_date tidak valid.',
            ];
        }

        $vendorId = $this->nullableInt($header['vendor_id'] ?? null);
        $purchaseTypeId = (int)($header['purchase_type_id'] ?? 0);
        if ($purchaseTypeId <= 0) {
            return [
                'ok' => false,
                'message' => 'purchase_type_id wajib diisi.',
            ];
        }

        $typeRule = $this->getPurchaseTypeRule($purchaseTypeId);
        if ($typeRule === null) {
            return [
                'ok' => false,
                'message' => 'Purchase type tidak ditemukan atau tidak aktif.',
            ];
        }

        $allowedStatuses = ['DRAFT', 'APPROVED', 'ORDERED', 'REJECTED', 'PARTIAL_RECEIVED', 'RECEIVED', 'PAID', 'VOID'];
        $status = strtoupper(trim((string)($header['status'] ?? 'DRAFT')));
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'DRAFT';
        }

        $paymentAccountId = array_key_exists('payment_account_id', $header)
            ? $this->nullableInt($header['payment_account_id'] ?? null)
            : null;

        $destinationType = null;
        $destinationDivisionId = null;
        $isInventory = (int)($typeRule['affects_inventory'] ?? 0) === 1;
        $destinationBehavior = strtoupper(trim((string)($typeRule['destination_behavior'] ?? 'REQUIRED')));
        $defaultDestination = strtoupper(trim((string)($typeRule['default_destination'] ?? '')));

        if ($isInventory && $destinationBehavior !== 'NONE') {
            $destinationCandidate = $defaultDestination !== ''
                ? $defaultDestination
                : (string)($header['destination_type'] ?? '');

            $destinationType = $this->normalizeDestination($destinationCandidate);
            if ($destinationType === null) {
                return [
                    'ok' => false,
                    'message' => 'Tujuan wajib valid sesuai aturan purchase type.',
                ];
            }

            $destinationDivisionId = $this->resolveDestinationDivisionId($destinationType);
            if ($destinationDivisionId === null && in_array($destinationType, ['BAR', 'BAR_EVENT', 'KITCHEN', 'KITCHEN_EVENT', 'OFFICE'], true)) {
                return [
                    'ok' => false,
                    'message' => 'Divisi tujuan tidak ditemukan untuk destination ' . $destinationType . '.',
                ];
            }
        }

        $expectedDate = $this->normalizeDate((string)($header['expected_date'] ?? ''));
        $poNo = strtoupper(trim((string)($header['po_no'] ?? '')));
        if ($poNo === '') {
            $poNo = $this->generatePoNo($requestDate);
        } elseif ($this->poNoExists($poNo)) {
            return [
                'ok' => false,
                'message' => 'Nomor PO sudah digunakan.',
            ];
        }

        $currencyCode = strtoupper(trim((string)($header['currency_code'] ?? 'IDR')));
        if ($currencyCode === '') {
            $currencyCode = 'IDR';
        }

        $orderData = [
            'po_no' => $poNo,
            'request_date' => $requestDate,
            'expected_date' => $expectedDate,
            'purchase_type_id' => $purchaseTypeId,
            'destination_type' => $destinationType,
            'destination_division_id' => $destinationDivisionId,
            'vendor_id' => $vendorId,
            'status' => $status,
            'currency_code' => $currencyCode,
            'exchange_rate' => $this->positiveDecimal($header['exchange_rate'] ?? 1, 1),
            'subtotal' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'grand_total' => 0,
            'external_ref_no' => $this->nullableString($header['external_ref_no'] ?? null),
            'notes' => $this->nullableString($header['notes'] ?? null),
            'created_by' => $userId > 0 ? $userId : null,
        ];

        if ($this->db->field_exists('payment_account_id', 'pur_purchase_order')) {
            $orderData['payment_account_id'] = $paymentAccountId;
        }

        $this->db->trans_begin();

        $this->db->insert('pur_purchase_order', $orderData);
        $orderId = (int)$this->db->insert_id();
        if ($orderId <= 0) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => 'Gagal membuat header purchase order.',
            ];
        }

        $subtotal = 0.0;
        $taxAmount = 0.0;
        $discountAmount = 0.0;
        $lineNo = 1;

        foreach ($lines as $row) {
            $lineResult = $this->buildLinePayload((array)$row, $vendorId !== null ? $vendorId : 0);
            if (!($lineResult['ok'] ?? false)) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => 'Baris #' . $lineNo . ': ' . (string)($lineResult['message'] ?? 'Data tidak valid.'),
                ];
            }

            $lineData = $lineResult['data'];
            $lineData['purchase_order_id'] = $orderId;
            $lineData['line_no'] = $lineNo;

            $this->db->insert('pur_purchase_order_line', $lineData);
            $lineId = (int)$this->db->insert_id();
            if ($lineId <= 0) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => 'Gagal menyimpan line purchase order.',
                ];
            }

            $subtotal += (float)$lineData['line_subtotal'];
            $gross = ((float)$lineData['qty_buy']) * ((float)$lineData['unit_price']);
            $discountAmount += max(0, $gross - ($gross * (1 - ((float)$lineData['discount_percent'] / 100))));
            $taxAmount += (($gross * (1 - ((float)$lineData['discount_percent'] / 100))) * ((float)$lineData['tax_percent'] / 100));

            $this->upsertCatalogFromLine($vendorId !== null ? $vendorId : 0, $requestDate, $orderId, $lineId, $lineData);
            $lineNo++;
        }

        $subtotal = round($subtotal, 2);
        $taxAmount = round($taxAmount, 2);
        $discountAmount = round($discountAmount, 2);
        $grandTotal = round($subtotal, 2);

        $this->db->where('id', $orderId)->update('pur_purchase_order', [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'grand_total' => $grandTotal,
        ]);

        $txnLog = $this->writePurchaseTxnLog([
            'purchase_order_id' => $orderId,
            'action_code' => 'PO_CREATE',
            'status_after' => $status,
            'transaction_no' => $poNo,
            'ref_table' => 'pur_purchase_order',
            'ref_id' => $orderId,
            'amount' => $grandTotal,
            'payload' => [
                'request_date' => $requestDate,
                'purchase_type_id' => $purchaseTypeId,
                'destination_type' => $destinationType,
                'destination_division_id' => $destinationDivisionId,
                'vendor_id' => $vendorId,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'grand_total' => $grandTotal,
                'line_count' => $lineNo - 1,
            ],
            'notes' => 'Create purchase order',
            'created_by' => $userId,
        ]);
        if (!($txnLog['ok'] ?? false)) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => (string)($txnLog['message'] ?? 'Gagal mencatat log transaksi purchase.'),
            ];
        }

        $statusSyncReceipt = [];
        $statusSyncPayment = [];
        if (in_array($status, ['RECEIVED', 'PAID'], true)) {
            $effectOrder = [
                'id' => $orderId,
                'purchase_type_id' => $purchaseTypeId,
                'destination_type' => $destinationType,
                'destination_division_id' => $destinationDivisionId,
                'payment_account_id' => $paymentAccountId,
                'grand_total' => $grandTotal,
                'status' => $status,
            ];

            $receiptPosting = $this->autoPostOutstandingReceiptOnStatusReached(
                $effectOrder,
                $userId,
                $sourceIp
            );
            if (!($receiptPosting['ok'] ?? false)) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => (string)($receiptPosting['message'] ?? 'Gagal auto-post receipt saat create PO.'),
                ];
            }
            $statusSyncReceipt = (array)($receiptPosting['data'] ?? []);

            if ($status === 'PAID') {
                $paidPosting = $this->autoApplyOutstandingPaymentOnStatusPaid(
                    $effectOrder,
                    $userId,
                    $sourceIp
                );
                if (!($paidPosting['ok'] ?? false)) {
                    $this->db->trans_rollback();
                    return [
                        'ok' => false,
                        'message' => (string)($paidPosting['message'] ?? 'Gagal auto-post pembayaran saat create PO status PAID.'),
                    ];
                }
                $statusSyncPayment = (array)($paidPosting['data'] ?? []);
            }

            if ($this->db->table_exists('aud_transaction_log')) {
                $this->db->insert('aud_transaction_log', [
                    'module_code' => 'PURCHASE',
                    'action_code' => 'PO_CREATE_STATUS_SYNC',
                    'entity_table' => 'pur_purchase_order',
                    'entity_id' => $orderId,
                    'transaction_no' => $poNo,
                    'ref_table' => 'pur_purchase_order',
                    'ref_id' => $orderId,
                    'actor_user_id' => $userId > 0 ? $userId : null,
                    'source_ip' => $sourceIp !== '' ? $sourceIp : null,
                    'after_payload' => json_encode([
                        'status_after' => $status,
                        'receipt_auto_post' => $statusSyncReceipt,
                        'payment_auto_apply' => $statusSyncPayment,
                    ]),
                    'notes' => 'Sinkronisasi dampak saat create PO dengan status final',
                ]);
            }
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => 'Transaksi gagal disimpan.',
            ];
        }

        $this->db->trans_commit();

        return [
            'ok' => true,
            'message' => 'Purchase order berhasil disimpan.',
            'data' => [
                'purchase_order_id' => $orderId,
                'po_no' => $poNo,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'grand_total' => $grandTotal,
                'line_count' => $lineNo - 1,
                'status_sync' => [
                    'receipt_auto_post' => $statusSyncReceipt,
                    'payment_auto_apply' => $statusSyncPayment,
                ],
            ],
        ];
    }

    public function update_order_with_lines(int $purchaseOrderId, array $header, array $lines, int $userId, string $sourceIp = ''): array
    {
        if ($purchaseOrderId <= 0 || !$this->db->table_exists('pur_purchase_order') || !$this->db->table_exists('pur_purchase_order_line')) {
            return [
                'ok' => false,
                'message' => 'Tabel purchase belum tersedia atau purchase_order_id tidak valid.',
            ];
        }

        if (empty($lines)) {
            return [
                'ok' => false,
                'message' => 'Baris purchase order wajib diisi.',
            ];
        }

        $order = $this->db
            ->select('id, po_no, status, approved_by, approved_at, expected_date, currency_code, exchange_rate, external_ref_no, payment_account_id')
            ->from('pur_purchase_order')
            ->where('id', $purchaseOrderId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$order) {
            return [
                'ok' => false,
                'message' => 'Purchase order tidak ditemukan.',
            ];
        }

        $editability = $this->evaluateOrderDataEditability($order);
        if (!($editability['ok'] ?? false)) {
            return $editability;
        }

        $requestDate = $this->normalizeDate((string)($header['request_date'] ?? ''));
        if ($requestDate === null) {
            return [
                'ok' => false,
                'message' => 'Format request_date tidak valid.',
            ];
        }

        $vendorId = $this->nullableInt($header['vendor_id'] ?? null);
        $purchaseTypeId = (int)($header['purchase_type_id'] ?? 0);
        if ($purchaseTypeId <= 0) {
            return [
                'ok' => false,
                'message' => 'purchase_type_id wajib diisi.',
            ];
        }

        $typeRule = $this->getPurchaseTypeRule($purchaseTypeId);
        if ($typeRule === null) {
            return [
                'ok' => false,
                'message' => 'Purchase type tidak ditemukan atau tidak aktif.',
            ];
        }

        $paymentAccountId = $this->nullableInt($header['payment_account_id'] ?? null);

        $destinationType = null;
        $destinationDivisionId = null;
        $isInventory = (int)($typeRule['affects_inventory'] ?? 0) === 1;
        $destinationBehavior = strtoupper(trim((string)($typeRule['destination_behavior'] ?? 'REQUIRED')));
        $defaultDestination = strtoupper(trim((string)($typeRule['default_destination'] ?? '')));

        if ($isInventory && $destinationBehavior !== 'NONE') {
            $destinationCandidate = $defaultDestination !== ''
                ? $defaultDestination
                : (string)($header['destination_type'] ?? '');

            $destinationType = $this->normalizeDestination($destinationCandidate);
            if ($destinationType === null) {
                return [
                    'ok' => false,
                    'message' => 'Tujuan wajib valid sesuai aturan purchase type.',
                ];
            }

            $destinationDivisionId = $this->resolveDestinationDivisionId($destinationType);
            if ($destinationDivisionId === null && in_array($destinationType, ['BAR', 'BAR_EVENT', 'KITCHEN', 'KITCHEN_EVENT', 'OFFICE'], true)) {
                return [
                    'ok' => false,
                    'message' => 'Divisi tujuan tidak ditemukan untuk destination ' . $destinationType . '.',
                ];
            }
        }

        $expectedDate = array_key_exists('expected_date', $header)
            ? $this->normalizeDate((string)($header['expected_date'] ?? ''))
            : $this->normalizeDate((string)($order['expected_date'] ?? ''));

        $currencyRaw = trim((string)($header['currency_code'] ?? ''));
        if ($currencyRaw === '') {
            $currencyRaw = trim((string)($order['currency_code'] ?? 'IDR'));
        }
        $currencyCode = strtoupper($currencyRaw);
        if ($currencyCode === '') {
            $currencyCode = 'IDR';
        }

        $exchangeRateDefault = (float)($order['exchange_rate'] ?? 1);
        if ($exchangeRateDefault <= 0) {
            $exchangeRateDefault = 1;
        }
        $exchangeRate = array_key_exists('exchange_rate', $header)
            ? $this->positiveDecimal($header['exchange_rate'] ?? 1, 1)
            : $this->positiveDecimal($exchangeRateDefault, 1);

        $externalRefNo = array_key_exists('external_ref_no', $header)
            ? $this->nullableString($header['external_ref_no'] ?? null)
            : $this->nullableString($order['external_ref_no'] ?? null);

        $orderData = [
            'request_date' => $requestDate,
            'expected_date' => $expectedDate,
            'purchase_type_id' => $purchaseTypeId,
            'destination_type' => $destinationType,
            'destination_division_id' => $destinationDivisionId,
            'vendor_id' => $vendorId,
            'currency_code' => $currencyCode,
            'exchange_rate' => $exchangeRate,
            'subtotal' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'grand_total' => 0,
            'external_ref_no' => $externalRefNo,
            'notes' => $this->nullableString($header['notes'] ?? null),
        ];

        if ($this->db->field_exists('payment_account_id', 'pur_purchase_order')) {
            $orderData['payment_account_id'] = $paymentAccountId;
        }

        $this->db->trans_begin();

        $this->db->where('id', $purchaseOrderId)->update('pur_purchase_order', $orderData);
        if ((int)($this->db->error()['code'] ?? 0) !== 0) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => 'Gagal update header purchase order.',
            ];
        }

        $existingLineIds = [];
        $lineRows = $this->db
            ->select('id')
            ->from('pur_purchase_order_line')
            ->where('purchase_order_id', $purchaseOrderId)
            ->get()
            ->result_array();
        foreach ($lineRows as $lineRow) {
            $lineId = (int)($lineRow['id'] ?? 0);
            if ($lineId > 0) {
                $existingLineIds[] = $lineId;
            }
        }

        if (!empty($existingLineIds) && $this->db->table_exists('mst_purchase_catalog') && $this->db->field_exists('last_purchase_line_id', 'mst_purchase_catalog')) {
            $clearRefData = ['last_purchase_line_id' => null];
            if ($this->db->field_exists('last_purchase_order_id', 'mst_purchase_catalog')) {
                $clearRefData['last_purchase_order_id'] = null;
            }

            $this->db
                ->group_start()
                    ->where_in('last_purchase_line_id', $existingLineIds);

            if ($this->db->field_exists('last_purchase_order_id', 'mst_purchase_catalog')) {
                $this->db->or_where('last_purchase_order_id', $purchaseOrderId);
            }

            $this->db
                ->group_end()
                ->update('mst_purchase_catalog', $clearRefData);

            if ((int)($this->db->error()['code'] ?? 0) !== 0) {
                $dbErr = $this->db->error();
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => 'Gagal melepas referensi catalog ke line lama: ' . (string)($dbErr['message'] ?? '-'),
                ];
            }
        }

        $this->db->where('purchase_order_id', $purchaseOrderId)->delete('pur_purchase_order_line');
        if ((int)($this->db->error()['code'] ?? 0) !== 0) {
            $dbErr = $this->db->error();
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => 'Gagal reset line purchase order: ' . (string)($dbErr['message'] ?? '-'),
            ];
        }

        $subtotal = 0.0;
        $taxAmount = 0.0;
        $discountAmount = 0.0;
        $lineNo = 1;

        foreach ($lines as $row) {
            $lineResult = $this->buildLinePayload((array)$row, $vendorId !== null ? $vendorId : 0);
            if (!($lineResult['ok'] ?? false)) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => 'Baris #' . $lineNo . ': ' . (string)($lineResult['message'] ?? 'Data tidak valid.'),
                ];
            }

            $lineData = $lineResult['data'];
            $lineData['purchase_order_id'] = $purchaseOrderId;
            $lineData['line_no'] = $lineNo;

            $this->db->insert('pur_purchase_order_line', $lineData);
            $lineId = (int)$this->db->insert_id();
            if ($lineId <= 0) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => 'Gagal menyimpan line purchase order.',
                ];
            }

            $subtotal += (float)$lineData['line_subtotal'];
            $gross = ((float)$lineData['qty_buy']) * ((float)$lineData['unit_price']);
            $discountAmount += max(0, $gross - ($gross * (1 - ((float)$lineData['discount_percent'] / 100))));
            $taxAmount += (($gross * (1 - ((float)$lineData['discount_percent'] / 100))) * ((float)$lineData['tax_percent'] / 100));

            $this->upsertCatalogFromLine($vendorId !== null ? $vendorId : 0, $requestDate, $purchaseOrderId, $lineId, $lineData);
            $lineNo++;
        }

        $subtotal = round($subtotal, 2);
        $taxAmount = round($taxAmount, 2);
        $discountAmount = round($discountAmount, 2);
        $grandTotal = round($subtotal, 2);

        $this->db->where('id', $purchaseOrderId)->update('pur_purchase_order', [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'grand_total' => $grandTotal,
        ]);

        if ((int)($this->db->error()['code'] ?? 0) !== 0 || $this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => 'Transaksi update purchase order gagal.',
            ];
        }

        if ($this->db->table_exists('aud_transaction_log')) {
            $this->db->insert('aud_transaction_log', [
                'module_code' => 'PURCHASE',
                'action_code' => 'PO_UPDATE',
                'entity_table' => 'pur_purchase_order',
                'entity_id' => $purchaseOrderId,
                'transaction_no' => (string)($order['po_no'] ?? null),
                'ref_table' => 'pur_purchase_order',
                'ref_id' => $purchaseOrderId,
                'actor_user_id' => $userId > 0 ? $userId : null,
                'source_ip' => $sourceIp !== '' ? $sourceIp : null,
                'after_payload' => json_encode([
                    'request_date' => $requestDate,
                    'purchase_type_id' => $purchaseTypeId,
                    'destination_type' => $destinationType,
                    'destination_division_id' => $destinationDivisionId,
                    'vendor_id' => $vendorId,
                    'payment_account_id' => $paymentAccountId,
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'discount_amount' => $discountAmount,
                    'grand_total' => $grandTotal,
                    'line_count' => $lineNo - 1,
                ]),
                'notes' => 'Update data purchase order',
            ]);
        }

        $txnLog = $this->writePurchaseTxnLog([
            'purchase_order_id' => $purchaseOrderId,
            'action_code' => 'PO_UPDATE',
            'status_before' => strtoupper((string)($order['status'] ?? '')),
            'status_after' => strtoupper((string)($order['status'] ?? '')),
            'transaction_no' => (string)($order['po_no'] ?? ''),
            'ref_table' => 'pur_purchase_order',
            'ref_id' => $purchaseOrderId,
            'amount' => $grandTotal,
            'payload' => [
                'request_date' => $requestDate,
                'purchase_type_id' => $purchaseTypeId,
                'destination_type' => $destinationType,
                'destination_division_id' => $destinationDivisionId,
                'vendor_id' => $vendorId,
                'payment_account_id' => $paymentAccountId,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'grand_total' => $grandTotal,
                'line_count' => $lineNo - 1,
            ],
            'notes' => 'Update purchase order',
            'created_by' => $userId,
        ]);
        if (!($txnLog['ok'] ?? false)) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => (string)($txnLog['message'] ?? 'Gagal mencatat log transaksi purchase.'),
            ];
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => 'Transaksi update purchase order gagal.',
            ];
        }

        $this->db->trans_commit();

        return [
            'ok' => true,
            'message' => 'Purchase order berhasil diperbarui.',
            'data' => [
                'purchase_order_id' => $purchaseOrderId,
                'po_no' => (string)($order['po_no'] ?? ''),
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'grand_total' => $grandTotal,
                'line_count' => $lineNo - 1,
            ],
        ];
    }

    private function evaluateOrderDataEditability(array $order): array
    {
        $orderId = (int)($order['id'] ?? 0);
        $status = strtoupper(trim((string)($order['status'] ?? 'DRAFT')));
        $editableStatuses = ['DRAFT', 'APPROVED'];

        if (!in_array($status, $editableStatuses, true)) {
            return [
                'ok' => false,
                'message' => 'PO dengan status ' . $status . ' tidak boleh edit data. Hanya DRAFT/APPROVED yang dapat diedit.',
            ];
        }

        if ($orderId > 0 && $this->db->table_exists('pur_purchase_receipt')) {
            $receiptHistoryCount = (int)$this->db
                ->from('pur_purchase_receipt')
                ->where('purchase_order_id', $orderId)
                ->count_all_results();
            if ($receiptHistoryCount > 0) {
                return [
                    'ok' => false,
                    'message' => 'PO tidak bisa diedit karena sudah memiliki riwayat receipt (termasuk VOID) sehingga line referensi harus tetap konsisten.',
                ];
            }
        }

        if ($orderId > 0 && $this->db->table_exists('pur_purchase_payment_plan')) {
            $paidCount = (int)$this->db
                ->from('pur_purchase_payment_plan')
                ->where('purchase_order_id', $orderId)
                ->where('status', 'PAID')
                ->count_all_results();
            if ($paidCount > 0) {
                return [
                    'ok' => false,
                    'message' => 'PO tidak bisa diedit karena sudah memiliki pembayaran PAID.',
                ];
            }
        }

        return [
            'ok' => true,
            'data' => [
                'order_id' => $orderId,
                'status' => $status,
            ],
        ];
    }

    private function getPurchaseTypeRule(int $purchaseTypeId): ?array
    {
        if ($purchaseTypeId <= 0) {
            return null;
        }

        return $this->db
            ->select('t.id, t.type_code, t.destination_behavior, t.default_destination, t.is_active')
            ->select('p.type_code AS posting_type_code, p.affects_inventory')
            ->from('mst_purchase_type t')
            ->join('mst_posting_type p', 'p.id = t.posting_type_id', 'left')
            ->where('t.id', $purchaseTypeId)
            ->where('t.is_active', 1)
            ->limit(1)
            ->get()
            ->row_array();
    }

    private function resolveDestinationDivisionId(string $destinationType): ?int
    {
        if (!$this->db->table_exists('mst_operational_division')) {
            return null;
        }

        $dest = strtoupper(trim($destinationType));
        $divisionCode = null;
        if ($dest === 'BAR' || $dest === 'BAR_EVENT') {
            $divisionCode = 'BAR';
        } elseif ($dest === 'KITCHEN' || $dest === 'KITCHEN_EVENT') {
            $divisionCode = 'KITCHEN';
        } elseif ($dest === 'OFFICE') {
            $divisionCode = 'MANAJEMEN';
        }

        if ($divisionCode === null) {
            return null;
        }

        $row = $this->db
            ->select('id')
            ->from('mst_operational_division')
            ->group_start()
            ->where('UPPER(code)', $divisionCode)
            ->or_where('UPPER(name)', $divisionCode)
            ->group_end()
            ->where('is_active', 1)
            ->order_by('id', 'ASC')
            ->limit(1)
            ->get()
            ->row_array();

        return $this->nullableInt($row['id'] ?? null);
    }

    public function search_catalog_profiles(
        string $q,
        int $vendorId,
        string $lineKind,
        int $itemId,
        int $materialId,
        int $limit
    ): array {
        $rankExpr = $this->buildRankExpression($q, ['c.catalog_name', 'c.brand_name']);

        $this->db
            ->select("'CATALOG' AS source_type", false)
            ->select('c.id AS catalog_id, c.profile_key, c.line_kind, c.item_id, c.material_id, c.vendor_id')
            ->select('c.catalog_name, c.brand_name, c.line_description, c.notes')
            ->select('c.buy_uom_id, bu.code AS buy_uom_code, bu.name AS buy_uom_name')
            ->select('c.content_uom_id, cu.code AS content_uom_code, cu.name AS content_uom_name')
            ->select('c.content_per_buy, c.conversion_factor_to_content')
            ->select('c.standard_price, c.last_unit_price, c.last_purchase_date')
            ->select('i.item_code, i.item_name, m.material_code, m.material_name')
            ->select($rankExpr . ' AS rank_score', false)
            ->from('mst_purchase_catalog c')
            ->join('mst_uom bu', 'bu.id = c.buy_uom_id', 'left')
            ->join('mst_uom cu', 'cu.id = c.content_uom_id', 'left')
            ->join('mst_item i', 'i.id = c.item_id', 'left')
            ->join('mst_material m', 'm.id = c.material_id', 'left')
            ->where('c.is_active', 1);

        if ($vendorId > 0) {
            $this->db->where('c.vendor_id', $vendorId);
        }
        if ($lineKind !== '') {
            $this->db->where('c.line_kind', $lineKind);
        }
        if ($itemId > 0) {
            $this->db->where('c.item_id', $itemId);
        }
        if ($materialId > 0) {
            $this->db->where('c.material_id', $materialId);
        }

        if ($q !== '') {
            $this->db->group_start()
                ->like('c.catalog_name', $q)
                ->or_like('c.brand_name', $q)
                ->or_like('c.line_description', $q)
                ->group_end();
        }

        $this->db
            ->order_by('rank_score', 'DESC', false)
            ->order_by('c.last_purchase_date', 'DESC')
            ->order_by('c.id', 'DESC')
            ->limit($limit);

        return $this->db->get()->result_array();
    }

    public function search_master_fallback(
        string $q,
        string $lineKind,
        int $itemId,
        int $materialId,
        int $limit
    ): array {
        $rows = [];

        if ($lineKind === '' || $lineKind === 'ITEM') {
            $rows = array_merge($rows, $this->search_master_item_rows($q, $itemId, $materialId, $limit));
        }

        if (($lineKind === '' || $lineKind === 'MATERIAL') && count($rows) < $limit) {
            $remaining = $limit - count($rows);
            $rows = array_merge($rows, $this->search_master_material_rows($q, $materialId, $remaining));
        }

        usort($rows, function ($a, $b) {
            $left = (float)($a['rank_score'] ?? 0);
            $right = (float)($b['rank_score'] ?? 0);
            if ($left === $right) {
                return ((int)($b['item_id'] ?? $b['material_id'] ?? 0)) <=> ((int)($a['item_id'] ?? $a['material_id'] ?? 0));
            }
            return $right <=> $left;
        });

        return array_slice($rows, 0, $limit);
    }

    private function search_master_item_rows(string $q, int $itemId, int $materialId, int $limit): array
    {
        $rankExpr = $this->buildRankExpression($q, ['i.item_name', 'i.item_code']);

        $this->db
            ->select("'MASTER' AS source_type", false)
            ->select('NULL AS catalog_id, NULL AS profile_key', false)
            ->select("CASE WHEN i.material_id IS NOT NULL AND i.material_id > 0 THEN 'MATERIAL' ELSE 'ITEM' END AS line_kind", false)
            ->select('i.id AS item_id, i.material_id, NULL AS vendor_id', false)
            ->select('i.item_name AS catalog_name, NULL AS brand_name, NULL AS line_description, i.notes', false)
            ->select('i.buy_uom_id, bu.code AS buy_uom_code, bu.name AS buy_uom_name')
            ->select('i.content_uom_id, cu.code AS content_uom_code, cu.name AS content_uom_name')
            ->select('i.content_per_buy, i.content_per_buy AS conversion_factor_to_content', false)
            ->select('NULL AS standard_price, i.last_buy_price AS last_unit_price, NULL AS last_purchase_date', false)
            ->select('i.item_code, i.item_name, m.material_code, m.material_name')
            ->select($rankExpr . ' AS rank_score', false)
            ->from('mst_item i')
            ->join('mst_uom bu', 'bu.id = i.buy_uom_id', 'left')
            ->join('mst_uom cu', 'cu.id = i.content_uom_id', 'left')
            ->join('mst_material m', 'm.id = i.material_id', 'left')
            ->where('i.is_active', 1);

        if ($itemId > 0) {
            $this->db->where('i.id', $itemId);
        }
        if ($materialId > 0) {
            $this->db->where('i.material_id', $materialId);
        }
        if ($q !== '') {
            $this->db->group_start()
                ->like('i.item_name', $q)
                ->or_like('i.item_code', $q)
                ->group_end();
        }

        $this->db
            ->order_by('rank_score', 'DESC', false)
            ->order_by('i.id', 'DESC')
            ->limit($limit);

        return $this->db->get()->result_array();
    }

    private function search_master_material_rows(string $q, int $materialId, int $limit): array
    {
        $rankExpr = $this->buildRankExpression($q, ['m.material_name', 'm.material_code']);

        $this->db
            ->select("'MASTER' AS source_type", false)
            ->select('NULL AS catalog_id, NULL AS profile_key', false)
            ->select("'MATERIAL' AS line_kind", false)
            ->select('mi.id AS item_id, m.id AS material_id, NULL AS vendor_id', false)
            ->select('m.material_name AS catalog_name, NULL AS brand_name, NULL AS line_description, m.notes', false)
            ->select('IFNULL(mi.buy_uom_id, m.content_uom_id) AS buy_uom_id', false)
            ->select('bu.code AS buy_uom_code, bu.name AS buy_uom_name')
            ->select('m.content_uom_id, cu.code AS content_uom_code, cu.name AS content_uom_name')
            ->select('IFNULL(mi.content_per_buy, 1) AS content_per_buy', false)
            ->select('IFNULL(mi.content_per_buy, 1) AS conversion_factor_to_content', false)
            ->select('NULL AS standard_price, NULL AS last_unit_price, NULL AS last_purchase_date', false)
            ->select('mi.item_code, mi.item_name, m.material_code, m.material_name')
            ->select($rankExpr . ' AS rank_score', false)
            ->from('mst_material m')
            ->join('mst_item mi', 'mi.material_id = m.id AND mi.is_active = 1', 'left')
            ->join('mst_uom bu', 'bu.id = IFNULL(mi.buy_uom_id, m.content_uom_id)', 'left', false)
            ->join('mst_uom cu', 'cu.id = m.content_uom_id', 'left')
            ->where('m.is_active', 1);

        if ($materialId > 0) {
            $this->db->where('m.id', $materialId);
        }
        if ($q !== '') {
            $this->db->group_start()
                ->like('m.material_name', $q)
                ->or_like('m.material_code', $q)
                ->group_end();
        }

        $this->db
            ->order_by('rank_score', 'DESC', false)
            ->order_by('m.id', 'DESC')
            ->limit($limit);

        return $this->db->get()->result_array();
    }

    private function buildRankExpression(string $q, array $columns): string
    {
        if ($q === '') {
            return '0';
        }

        $qEsc = $this->db->escape($q);
        $qLike = $this->db->escape_like_str($q);
        $parts = [];

        foreach ($columns as $index => $column) {
            $weightExact = $index === 0 ? 110 : 80;
            $weightPrefix = $index === 0 ? 60 : 35;
            $weightContain = $index === 0 ? 20 : 12;
            $parts[] = "CASE WHEN {$column} = {$qEsc} THEN {$weightExact} ELSE 0 END";
            $parts[] = "CASE WHEN {$column} LIKE '{$qLike}%' THEN {$weightPrefix} ELSE 0 END";
            $parts[] = "CASE WHEN {$column} LIKE '%{$qLike}%' THEN {$weightContain} ELSE 0 END";
        }

        return '(' . implode(' + ', $parts) . ')';
    }

    private function buildLinePayload(array $line, int $vendorId): array
    {
        $lineKind = strtoupper(trim((string)($line['line_kind'] ?? 'ITEM')));
        if (!in_array($lineKind, ['ITEM', 'MATERIAL', 'SERVICE', 'ASSET'], true)) {
            $lineKind = 'ITEM';
        }

        $itemId = $this->nullableInt($line['item_id'] ?? null);
        $materialId = $this->nullableInt($line['material_id'] ?? null);
        $buyUomId = (int)($line['buy_uom_id'] ?? 0);
        $contentUomId = $this->nullableInt($line['content_uom_id'] ?? null);
        $lineName = $this->nullableString($line['catalog_name'] ?? ($line['item_name'] ?? null));

        if ($buyUomId <= 0) {
            return ['ok' => false, 'message' => 'buy_uom_id wajib diisi.'];
        }

        if ($lineKind === 'ITEM' && $itemId === null && $lineName !== null) {
            $autoItemId = $this->ensureItemFromPurchaseLine($lineName, $buyUomId, $contentUomId, (float)($line['content_per_buy'] ?? 1), (float)($line['unit_price'] ?? 0));
            if ($autoItemId !== null) {
                $itemId = $autoItemId;
            }
        }

        $entity = $this->resolveEntitySnapshot($itemId, $materialId);

        $typedName = trim((string)($line['catalog_name'] ?? ($line['item_name'] ?? '')));
        if (($itemId !== null || $materialId !== null) && $typedName !== '') {
            $entityName = trim((string)($entity['item_name'] ?? ''));
            if ($entityName === '') {
                $entityName = trim((string)($entity['material_name'] ?? ''));
            }

            if ($entityName !== '' && strtoupper($typedName) !== strtoupper($entityName)) {
                return [
                    'ok' => false,
                    'message' => 'Nama baris tidak sinkron dengan item/material yang dipilih. Silakan pilih ulang dari preview agar snapshot sesuai.',
                ];
            }
        }

        if ($lineKind === 'ITEM' && $itemId === null) {
            return ['ok' => false, 'message' => 'Line ITEM wajib memiliki item_id atau nama item untuk auto create.'];
        }
        if ($lineKind === 'MATERIAL' && $materialId === null) {
            return ['ok' => false, 'message' => 'Line MATERIAL wajib memiliki material_id.'];
        }

        $qtyBuy = $this->positiveDecimal($line['qty_buy'] ?? 0, 0);
        if ($qtyBuy <= 0) {
            return ['ok' => false, 'message' => 'qty_buy harus lebih dari 0.'];
        }

        $contentPerBuy = $this->positiveDecimal($line['content_per_buy'] ?? 1, 1);
        $factor = $this->positiveDecimal($line['conversion_factor_to_content'] ?? $contentPerBuy, $contentPerBuy);
        $qtyContent = round($qtyBuy * $factor, 4);

        if ($contentUomId === null) {
            $contentUomId = $entity['content_uom_id'];
        }
        if ($contentUomId === null) {
            $contentUomId = $buyUomId;
        }

        if ($lineKind === 'MATERIAL') {
            $materialContentUomId = $entity['content_uom_id'];
            if ($materialContentUomId === null || $materialContentUomId <= 0) {
                return ['ok' => false, 'message' => 'UOM isi MATERIAL tidak ditemukan di master material.'];
            }
            $contentUomId = $materialContentUomId;
        }

        $unitPrice = $this->positiveDecimal($line['unit_price'] ?? 0, 0);
        $discountPercent = $this->boundedPercent($line['discount_percent'] ?? 0);
        $taxPercent = $this->boundedPercent($line['tax_percent'] ?? 0);

        $gross = $qtyBuy * $unitPrice;
        $afterDiscount = $gross * (1 - ($discountPercent / 100));
        $lineSubtotal = round($afterDiscount * (1 + ($taxPercent / 100)), 2);

        $brandName = $this->nullableString($line['brand_name'] ?? null);
        $lineDescription = $this->nullableString($line['line_description'] ?? null);

        $profileKey = hash('sha256', implode('|', [
            $lineKind,
            (string)($itemId ?? 0),
            (string)($materialId ?? 0),
            (string)$vendorId,
            (string)$buyUomId,
            (string)$contentUomId,
            number_format($contentPerBuy, 6, '.', ''),
            number_format($unitPrice, 2, '.', ''),
            strtoupper((string)($brandName ?? '')),
            strtoupper((string)($lineDescription ?? '')),
        ]));

        $data = [
            'line_kind' => $lineKind,
            'item_id' => $itemId,
            'material_id' => $materialId,
            'line_description' => $lineDescription,
            'brand_name' => $brandName,
            'qty_buy' => round($qtyBuy, 4),
            'buy_uom_id' => $buyUomId,
            'content_per_buy' => round($contentPerBuy, 6),
            'qty_content' => $qtyContent,
            'content_uom_id' => $contentUomId,
            'conversion_factor_to_content' => round($factor, 8),
            'unit_price' => round($unitPrice, 2),
            'discount_percent' => round($discountPercent, 4),
            'tax_percent' => round($taxPercent, 4),
            'line_subtotal' => $lineSubtotal,
            'snapshot_item_name' => $entity['item_name'],
            'snapshot_material_name' => $entity['material_name'],
            'snapshot_brand_name' => $brandName,
            'snapshot_line_description' => $lineDescription,
            'snapshot_buy_uom_code' => $this->uomCode($buyUomId),
            'snapshot_content_uom_code' => $contentUomId ? $this->uomCode((int)$contentUomId) : null,
            'profile_key' => $profileKey,
            'notes' => $this->nullableString($line['notes'] ?? null),
        ];

        return [
            'ok' => true,
            'data' => $data,
        ];
    }

    private function upsertCatalogFromLine(int $vendorId, string $purchaseDate, int $orderId, int $lineId, array $lineData): void
    {
        if ($vendorId <= 0) {
            return;
        }

        $itemId = $this->nullableInt($lineData['item_id'] ?? null);
        $materialId = $this->nullableInt($lineData['material_id'] ?? null);
        if ($itemId === null && $materialId === null) {
            return;
        }

        $catalogName = trim((string)($lineData['snapshot_item_name'] ?? ''));
        if ($catalogName === '') {
            $catalogName = trim((string)($lineData['snapshot_material_name'] ?? ''));
        }
        if ($catalogName === '') {
            $catalogName = trim((string)($lineData['line_description'] ?? ''));
        }
        if ($catalogName === '') {
            $catalogName = 'PURCHASE PROFILE ' . $lineId;
        }

        $upsertData = [
            'profile_key' => (string)($lineData['profile_key'] ?? ''),
            'line_kind' => (string)($lineData['line_kind'] ?? 'ITEM'),
            'item_id' => $itemId,
            'material_id' => $materialId,
            'vendor_id' => $vendorId,
            'catalog_name' => $catalogName,
            'brand_name' => $this->nullableString($lineData['brand_name'] ?? null),
            'line_description' => $this->nullableString($lineData['line_description'] ?? null),
            'buy_uom_id' => (int)$lineData['buy_uom_id'],
            'content_uom_id' => $this->nullableInt($lineData['content_uom_id'] ?? null),
            'content_per_buy' => (float)$lineData['content_per_buy'],
            'conversion_factor_to_content' => (float)$lineData['conversion_factor_to_content'],
            'last_unit_price' => (float)$lineData['unit_price'],
            'last_purchase_date' => $purchaseDate,
            'last_purchase_order_id' => $orderId,
            'last_purchase_line_id' => $lineId,
            'notes' => $this->nullableString($lineData['notes'] ?? null),
            'is_active' => 1,
        ];

        $existing = $this->db->get_where('mst_purchase_catalog', ['profile_key' => $upsertData['profile_key']])->row_array();
        if ($existing) {
            $updateExisting = [
                'catalog_name' => $upsertData['catalog_name'],
                'last_unit_price' => $upsertData['last_unit_price'],
                'last_purchase_date' => $upsertData['last_purchase_date'],
                'last_purchase_order_id' => $upsertData['last_purchase_order_id'],
                'last_purchase_line_id' => $upsertData['last_purchase_line_id'],
                'notes' => $upsertData['notes'],
                'is_active' => 1,
            ];

            // Keep profile identity stable; for brand/description fill if legacy row still kosong.
            if (empty($existing['brand_name']) && !empty($upsertData['brand_name'])) {
                $updateExisting['brand_name'] = $upsertData['brand_name'];
            }
            if (empty($existing['line_description']) && !empty($upsertData['line_description'])) {
                $updateExisting['line_description'] = $upsertData['line_description'];
            }

            $this->db->where('id', (int)$existing['id'])->update('mst_purchase_catalog', $updateExisting);
            return;
        }

        $upsertData['standard_price'] = (float)$lineData['unit_price'];
        $this->db->insert('mst_purchase_catalog', $upsertData);
    }

    private function ensureItemFromPurchaseLine(string $itemName, int $buyUomId, ?int $contentUomId, float $contentPerBuy, float $unitPrice): ?int
    {
        $normalizedName = trim($itemName);
        if ($normalizedName === '' || !$this->db->table_exists('mst_item')) {
            return null;
        }

        $existing = $this->db
            ->select('id')
            ->from('mst_item')
            ->where('UPPER(TRIM(item_name))', strtoupper($normalizedName))
            ->limit(1)
            ->get()
            ->row_array();
        if (!empty($existing['id'])) {
            return (int)$existing['id'];
        }

        if (!$this->db->table_exists('mst_item_category')) {
            return null;
        }

        $category = $this->db
            ->select('id')
            ->from('mst_item_category')
            ->where('is_active', 1)
            ->order_by('sort_order', 'ASC')
            ->order_by('id', 'ASC')
            ->limit(1)
            ->get()
            ->row_array();
        $categoryId = $this->nullableInt($category['id'] ?? null);
        if ($categoryId === null) {
            return null;
        }

        $contentUomId = $contentUomId !== null && $contentUomId > 0 ? $contentUomId : $buyUomId;
        $contentPerBuy = $contentPerBuy > 0 ? $contentPerBuy : 1.0;

        $codeBase = 'ITM' . date('ymdHis');
        $itemCode = $codeBase;
        $counter = 1;
        while ($this->db->where('item_code', $itemCode)->count_all_results('mst_item') > 0) {
            $itemCode = $codeBase . str_pad((string)$counter, 2, '0', STR_PAD_LEFT);
            $counter++;
            if ($counter > 99) {
                $itemCode = $codeBase . substr((string)mt_rand(1000, 9999), -4);
                break;
            }
        }

        $this->db->insert('mst_item', [
            'item_code' => $itemCode,
            'item_name' => $normalizedName,
            'item_category_id' => $categoryId,
            'buy_uom_id' => $buyUomId,
            'content_uom_id' => $contentUomId,
            'content_per_buy' => round($contentPerBuy, 6),
            'min_stock_content' => 0,
            'last_buy_price' => $unitPrice > 0 ? round($unitPrice, 2) : null,
            'is_material' => 0,
            'material_id' => null,
            'notes' => 'Auto create dari Purchase Order',
            'is_active' => 1,
        ]);

        $newId = (int)$this->db->insert_id();
        return $newId > 0 ? $newId : null;
    }

    private function resolveEntitySnapshot(?int $itemId, ?int $materialId): array
    {
        $snapshot = [
            'item_name' => null,
            'material_name' => null,
            'content_uom_id' => null,
        ];

        if ($itemId !== null && $itemId > 0) {
            $item = $this->db
                ->select('i.item_name, i.content_uom_id, i.material_id, m.material_name')
                ->from('mst_item i')
                ->join('mst_material m', 'm.id = i.material_id', 'left')
                ->where('i.id', $itemId)
                ->get()
                ->row_array();
            if ($item) {
                $snapshot['item_name'] = $this->nullableString($item['item_name'] ?? null);
                $snapshot['material_name'] = $this->nullableString($item['material_name'] ?? null);
                $snapshot['content_uom_id'] = $this->nullableInt($item['content_uom_id'] ?? null);
                if ($materialId === null && !empty($item['material_id'])) {
                    $materialId = (int)$item['material_id'];
                }
            }
        }

        if ($materialId !== null && $materialId > 0 && $snapshot['material_name'] === null) {
            $material = $this->db
                ->select('material_name, content_uom_id')
                ->from('mst_material')
                ->where('id', $materialId)
                ->get()
                ->row_array();
            if ($material) {
                $snapshot['material_name'] = $this->nullableString($material['material_name'] ?? null);
                if ($snapshot['content_uom_id'] === null) {
                    $snapshot['content_uom_id'] = $this->nullableInt($material['content_uom_id'] ?? null);
                }
            }
        }

        return $snapshot;
    }

    private function uomCode(int $uomId): ?string
    {
        if ($uomId <= 0) {
            return null;
        }

        if (isset($this->uomCodeCache[$uomId])) {
            return $this->uomCodeCache[$uomId];
        }

        $row = $this->db->select('code')->from('mst_uom')->where('id', $uomId)->get()->row_array();
        $this->uomCodeCache[$uomId] = $row['code'] ?? null;
        return $this->uomCodeCache[$uomId];
    }

    private function resolveUomCode(int $uomId): ?string
    {
        return $this->uomCode($uomId);
    }

    private function normalizeDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d', $ts);
    }

    private function normalizeMonth(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return date('Y-m-01');
        }

        $ts = strtotime($raw . '-01');
        if ($ts === false) {
            $ts = strtotime($raw);
        }
        if ($ts === false) {
            return null;
        }

        return date('Y-m-01', $ts);
    }

    private function normalizeDestination(string $value): ?string
    {
        $value = strtoupper(trim($value));
        if ($value === '') {
            return null;
        }

        $allowed = ['GUDANG', 'BAR', 'KITCHEN', 'BAR_EVENT', 'KITCHEN_EVENT', 'OFFICE', 'OTHER'];
        return in_array($value, $allowed, true) ? $value : null;
    }

    private function positiveDecimal($value, float $fallback): float
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        $parsed = (float)$value;
        if ($parsed <= 0) {
            return $fallback;
        }

        return $parsed;
    }

    private function boundedPercent($value): float
    {
        $pct = (float)$value;
        if ($pct < 0) {
            return 0;
        }
        if ($pct > 100) {
            return 100;
        }
        return $pct;
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $v = (int)$value;
        return $v > 0 ? $v : null;
    }

    private function nullableString($value): ?string
    {
        $v = trim((string)$value);
        return $v === '' ? null : $v;
    }

    private function coreColumnExists(string $table, string $column): bool
    {
        $row = $this->db
            ->query(
                'SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1',
                ['core', $table, $column]
            )
            ->row_array();

        return !empty($row);
    }

    private function writePurchaseTxnLog(array $payload): array
    {
        if (!$this->db->table_exists('pur_purchase_txn_log')) {
            return ['ok' => true, 'skipped' => 'TABLE_NOT_AVAILABLE'];
        }

        $purchaseOrderId = (int)($payload['purchase_order_id'] ?? 0);
        if ($purchaseOrderId <= 0) {
            return [
                'ok' => false,
                'message' => 'purchase_order_id wajib valid untuk log transaksi purchase.',
            ];
        }

        $actionCode = strtoupper(trim((string)($payload['action_code'] ?? '')));
        if ($actionCode === '') {
            return [
                'ok' => false,
                'message' => 'action_code wajib diisi untuk log transaksi purchase.',
            ];
        }

        $statusBefore = $this->nullableString($payload['status_before'] ?? null);
        $statusAfter = $this->nullableString($payload['status_after'] ?? null);

        $row = [
            'purchase_order_id' => $purchaseOrderId,
            'purchase_receipt_id' => $this->nullableInt($payload['purchase_receipt_id'] ?? null),
            'payment_plan_id' => $this->nullableInt($payload['payment_plan_id'] ?? null),
            'action_code' => $actionCode,
            'status_before' => $statusBefore !== null ? strtoupper($statusBefore) : null,
            'status_after' => $statusAfter !== null ? strtoupper($statusAfter) : null,
            'transaction_no' => $this->nullableString($payload['transaction_no'] ?? null),
            'ref_table' => $this->nullableString($payload['ref_table'] ?? null),
            'ref_id' => $this->nullableInt($payload['ref_id'] ?? null),
            'amount' => array_key_exists('amount', $payload) && $payload['amount'] !== null
                ? round((float)$payload['amount'], 2)
                : null,
            'payload_json' => array_key_exists('payload', $payload)
                ? json_encode($payload['payload'])
                : null,
            'notes' => $this->nullableString($payload['notes'] ?? null),
            'created_by' => $this->nullableInt($payload['created_by'] ?? null),
        ];

        $this->db->insert('pur_purchase_txn_log', $row);
        if ((int)($this->db->error()['code'] ?? 0) !== 0 || $this->db->affected_rows() <= 0) {
            return [
                'ok' => false,
                'message' => 'Gagal menulis pur_purchase_txn_log: ' . (string)($this->db->error()['message'] ?? 'unknown error'),
            ];
        }

        return [
            'ok' => true,
            'id' => (int)$this->db->insert_id(),
        ];
    }

    private function generatePoNo(string $requestDate): string
    {
        $datePart = date('Ymd', strtotime($requestDate));
        $prefix = 'PO' . $datePart;

        $row = $this->db
            ->select('po_no')
            ->from('pur_purchase_order')
            ->like('po_no', $prefix, 'after')
            ->order_by('po_no', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();

        $seq = 1;
        if (!empty($row['po_no'])) {
            $suffix = substr((string)$row['po_no'], strlen($prefix));
            if (ctype_digit($suffix)) {
                $seq = ((int)$suffix) + 1;
            }
        }

        do {
            $poNo = $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
            $seq++;
        } while ($this->poNoExists($poNo));

        return $poNo;
    }

    private function poNoExists(string $poNo): bool
    {
        return $this->db->where('po_no', $poNo)->count_all_results('pur_purchase_order') > 0;
    }

    private function generatePaymentNo(string $paymentDate): string
    {
        $datePart = date('Ymd', strtotime($paymentDate));
        $prefix = 'PAY' . $datePart;

        $row = $this->db
            ->select('transaction_no')
            ->from('pur_purchase_payment_plan')
            ->like('transaction_no', $prefix, 'after')
            ->order_by('transaction_no', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();

        $seq = 1;
        if (!empty($row['transaction_no'])) {
            $suffix = substr((string)$row['transaction_no'], strlen($prefix));
            if (ctype_digit($suffix)) {
                $seq = ((int)$suffix) + 1;
            }
        }

        do {
            $no = $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
            $seq++;
        } while ($this->db->where('transaction_no', $no)->count_all_results('pur_purchase_payment_plan') > 0);

        return $no;
    }

    private function generateAccountMutationNo(string $mutationDate): string
    {
        $datePart = date('Ymd', strtotime($mutationDate));
        $prefix = 'MUT' . $datePart;

        $row = $this->db
            ->select('mutation_no')
            ->from('fin_account_mutation_log')
            ->like('mutation_no', $prefix, 'after')
            ->order_by('mutation_no', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();

        $seq = 1;
        if (!empty($row['mutation_no'])) {
            $suffix = substr((string)$row['mutation_no'], strlen($prefix));
            if (ctype_digit($suffix)) {
                $seq = ((int)$suffix) + 1;
            }
        }

        do {
            $no = $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
            $seq++;
        } while ($this->db->where('mutation_no', $no)->count_all_results('fin_account_mutation_log') > 0);

        return $no;
    }

    private function generateReceiptNo(string $receiptDate): string
    {
        $datePart = date('Ymd', strtotime($receiptDate));
        $prefix = 'RCV' . $datePart;

        $row = $this->db
            ->select('receipt_no')
            ->from('pur_purchase_receipt')
            ->like('receipt_no', $prefix, 'after')
            ->order_by('receipt_no', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();

        $seq = 1;
        if (!empty($row['receipt_no'])) {
            $suffix = substr((string)$row['receipt_no'], strlen($prefix));
            if (ctype_digit($suffix)) {
                $seq = ((int)$suffix) + 1;
            }
        }

        do {
            $no = $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
            $seq++;
        } while ($this->db->where('receipt_no', $no)->count_all_results('pur_purchase_receipt') > 0);

        return $no;
    }
}
