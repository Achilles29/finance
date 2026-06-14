<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Purchase_model extends CI_Model
{
    private $uomCodeCache = [];
    private $tableFieldsCache = [];
    private $columnNullableCache = [];
    private $usagePurposeSchemaEnsured = false;
    private $itemIdentityResolverInstance;
    private const USAGE_PURPOSE_PRODUCTION = 'BAHAN_BAKU';
    private const USAGE_PURPOSE_OPERATIONAL = 'OPERASIONAL';

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

        if ($this->db->table_exists('inv_warehouse_monthly_stock')) {
            $targetMonth = date('Y-m-01');
            $latestMonthSubquery = $this->db
                ->select('identity_key, MAX(month_key) AS month_key', false)
                ->from('inv_warehouse_monthly_stock')
                ->where('month_key <=', $targetMonth)
                ->group_by('identity_key')
                ->get_compiled_select();
            $row = $this->db
                ->select('COUNT(*) AS total, SUM(COALESCE(s.closing_qty_content, 0)) AS qty_total', false)
                ->from('inv_warehouse_monthly_stock s')
                ->join('(' . $latestMonthSubquery . ') lm', 'lm.identity_key = s.identity_key AND lm.month_key = s.month_key', 'inner', false)
                ->get()
                ->row_array();
            $summary['warehouse_profiles'] = (int)($row['total'] ?? 0);
            $summary['warehouse_qty_content'] = (float)($row['qty_total'] ?? 0);
        }

        if ($this->db->table_exists('inv_division_monthly_stock')) {
            $targetMonth = date('Y-m-01');
            $latestMonthSubquery = $this->db
                ->select('division_id, destination_type, identity_key, MAX(month_key) AS month_key', false)
                ->from('inv_division_monthly_stock')
                ->where('month_key <=', $targetMonth)
                ->group_by(['division_id', 'destination_type', 'identity_key'])
                ->get_compiled_select();
            $row = $this->db
                ->select('COUNT(*) AS total, SUM(COALESCE(s.closing_qty_content, 0)) AS qty_total', false)
                ->from('inv_division_monthly_stock s')
                ->join('(' . $latestMonthSubquery . ') lm', 'lm.division_id = s.division_id AND lm.destination_type = s.destination_type AND lm.identity_key = s.identity_key AND lm.month_key = s.month_key', 'inner', false)
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

    public function get_purchase_order_filtered_summary(string $q, string $status, string $dateStart, string $dateEnd): array
    {
        $summary = [
            'total_count' => 0,
            'total_value' => 0.0,
            'paid_count' => 0,
            'paid_value' => 0.0,
            'unpaid_count' => 0,
            'unpaid_value' => 0.0,
            'active_count' => 0,
            'active_value' => 0.0,
        ];

        if (!$this->db->table_exists('pur_purchase_order')) {
            return $summary;
        }

        $from = $this->normalizeDate($dateStart);
        $to = $this->normalizeDate($dateEnd);

        $this->db
            ->select('COUNT(*) AS total_count', false)
            ->select('COALESCE(SUM(po.grand_total), 0) AS total_value', false)
            ->select("SUM(CASE WHEN po.status = 'PAID' THEN 1 ELSE 0 END) AS paid_count", false)
            ->select("COALESCE(SUM(CASE WHEN po.status = 'PAID' THEN po.grand_total ELSE 0 END), 0) AS paid_value", false)
            ->select("SUM(CASE WHEN po.status IN ('DRAFT','APPROVED','ORDERED','PARTIAL_RECEIVED','RECEIVED') THEN 1 ELSE 0 END) AS unpaid_count", false)
            ->select("COALESCE(SUM(CASE WHEN po.status IN ('DRAFT','APPROVED','ORDERED','PARTIAL_RECEIVED','RECEIVED') THEN po.grand_total ELSE 0 END), 0) AS unpaid_value", false)
            ->select("SUM(CASE WHEN po.status <> 'VOID' THEN 1 ELSE 0 END) AS active_count", false)
            ->select("COALESCE(SUM(CASE WHEN po.status <> 'VOID' THEN po.grand_total ELSE 0 END), 0) AS active_value", false)
            ->from('pur_purchase_order po')
            ->join('mst_purchase_type pt', 'pt.id = po.purchase_type_id', 'left')
            ->join('mst_vendor v', 'v.id = po.vendor_id', 'left');

        $status = strtoupper(trim($status));
        if ($status !== '' && $status !== 'ALL') {
            $this->db->where('po.status', $status);
        }
        if ($from !== null) {
            $this->db->where('po.request_date >=', $from);
        }
        if ($to !== null) {
            $this->db->where('po.request_date <=', $to);
        }

        if ($q !== '') {
            $this->db->group_start()
                ->like('po.po_no', $q)
                ->or_like('v.vendor_name', $q)
                ->or_like('pt.type_name', $q)
                ->or_like('po.notes', $q)
                ->group_end();
        }

        $row = $this->db->get()->row_array();
        if (!$row) {
            return $summary;
        }

        $summary['total_count'] = (int)($row['total_count'] ?? 0);
        $summary['total_value'] = round((float)($row['total_value'] ?? 0), 2);
        $summary['paid_count'] = (int)($row['paid_count'] ?? 0);
        $summary['paid_value'] = round((float)($row['paid_value'] ?? 0), 2);
        $summary['unpaid_count'] = (int)($row['unpaid_count'] ?? 0);
        $summary['unpaid_value'] = round((float)($row['unpaid_value'] ?? 0), 2);
        $summary['active_count'] = (int)($row['active_count'] ?? 0);
        $summary['active_value'] = round((float)($row['active_value'] ?? 0), 2);

        return $summary;
    }

    public function get_purchase_order_line_filtered_summary(string $q, string $status, string $dateStart, string $dateEnd): array
    {
        $summary = [
            'total_lines' => 0,
            'total_qty_buy' => 0.0,
            'total_value' => 0.0,
        ];

        if (!$this->db->table_exists('pur_purchase_order_line') || !$this->db->table_exists('pur_purchase_order')) {
            return $summary;
        }

        $from = $this->normalizeDate($dateStart);
        $to = $this->normalizeDate($dateEnd);

        $this->db
            ->select('COUNT(*) AS total_lines', false)
            ->select('COALESCE(SUM(l.qty_buy), 0) AS total_qty_buy', false)
            ->select('COALESCE(SUM(l.line_subtotal), 0) AS total_value', false)
            ->from('pur_purchase_order_line l')
            ->join('pur_purchase_order po', 'po.id = l.purchase_order_id', 'inner')
            ->join('mst_purchase_type pt', 'pt.id = po.purchase_type_id', 'left')
            ->join('mst_vendor v', 'v.id = po.vendor_id', 'left');

        $status = strtoupper(trim($status));
        if ($status !== '' && $status !== 'ALL') {
            $this->db->where('po.status', $status);
        }
        if ($from !== null) {
            $this->db->where('po.request_date >=', $from);
        }
        if ($to !== null) {
            $this->db->where('po.request_date <=', $to);
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

        $row = $this->db->get()->row_array();
        if (!$row) {
            return $summary;
        }

        $summary['total_lines'] = (int)($row['total_lines'] ?? 0);
        $summary['total_qty_buy'] = round((float)($row['total_qty_buy'] ?? 0), 4);
        $summary['total_value'] = round((float)($row['total_value'] ?? 0), 2);

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

    public function list_account_mutations(int $accountId, string $dateFrom, string $dateTo, int $limit, int $offset = 0): array
    {
        if (!$this->db->table_exists('fin_account_mutation_log')) {
            return [];
        }

        $this->db
            ->select('m.id, m.mutation_no, m.mutation_date, m.account_id, m.mutation_type, m.amount, m.balance_before, m.balance_after')
            ->select('m.ref_module, m.ref_table, m.ref_id, m.ref_no, m.notes, m.created_at, a.account_code, a.account_name')
            ->from('fin_account_mutation_log m')
            ->join('fin_company_account a', 'a.id = m.account_id', 'left');

        $this->applyAccountMutationDateFilters('m', $accountId, $dateFrom, $dateTo);

        return $this->db
            ->order_by('m.mutation_date', 'DESC')
            ->order_by('m.id', 'DESC')
            ->limit($limit, max(0, $offset))
            ->get()
            ->result_array();
    }

    public function count_account_mutations(int $accountId, string $dateFrom, string $dateTo): int
    {
        if (!$this->db->table_exists('fin_account_mutation_log')) {
            return 0;
        }

        $this->db
            ->from('fin_account_mutation_log m');

        $this->applyAccountMutationDateFilters('m', $accountId, $dateFrom, $dateTo);

        $row = $this->db
            ->select('COUNT(*) AS total_rows', false)
            ->get()
            ->row_array();

        return (int)($row['total_rows'] ?? 0);
    }

    private function applyAccountMutationDateFilters(string $alias, int $accountId, string $dateFrom, string $dateTo): void
    {
        if ($accountId > 0) {
            $this->db->where($alias . '.account_id', $accountId);
        }

        $from = $this->normalizeDate($dateFrom);
        $to = $this->normalizeDate($dateTo);
        if ($from !== null) {
            $this->db->where($alias . '.mutation_date >=', $from);
        }
        if ($to !== null) {
            $this->db->where($alias . '.mutation_date <=', $to);
        }
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
        $toAccountId = (int)($payload['to_account_id'] ?? 0);
        $amount = round((float)($payload['amount'] ?? 0), 2);
        $mutationDate = $this->normalizeDate((string)($payload['mutation_date'] ?? date('Y-m-d')));
        $referenceNo = $this->nullableString($payload['reference_no'] ?? null);
        $notes = $this->nullableString($payload['notes'] ?? null);

        if ($mutationType === 'TRANSFER') {
            if ($accountId <= 0 || $toAccountId <= 0 || $accountId === $toAccountId || $amount <= 0 || $mutationDate === null) {
                return [
                    'ok' => false,
                    'message' => 'Transfer wajib mengisi rekening sumber, rekening tujuan (berbeda), amount, mutation_date.',
                ];
            }
        } elseif ($accountId <= 0 || !in_array($mutationType, ['IN', 'OUT'], true) || $amount <= 0 || $mutationDate === null) {
            return [
                'ok' => false,
                'message' => 'account_id, mutation_type(IN/OUT), amount, mutation_date wajib valid.',
            ];
        }

        $mutationIds = [];
        $mutationNos = [];

        if ($mutationType === 'TRANSFER') {
            $lockRows = $this->db
                ->query(
                    'SELECT * FROM fin_company_account WHERE id IN (?, ?) AND is_active = 1 ORDER BY id ASC FOR UPDATE',
                    [$accountId, $toAccountId]
                )
                ->result_array();
            $accountsById = [];
            foreach ($lockRows as $row) {
                $accountsById[(int)($row['id'] ?? 0)] = $row;
            }
            $source = $accountsById[$accountId] ?? null;
            $target = $accountsById[$toAccountId] ?? null;
            if (!$source || !$target) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => 'Rekening sumber/tujuan tidak ditemukan atau tidak aktif.',
                ];
            }

            $sourceBefore = (float)($source['current_balance'] ?? 0);
            $targetBefore = (float)($target['current_balance'] ?? 0);
            $sourceAfter = round($sourceBefore - $amount, 2);
            $targetAfter = round($targetBefore + $amount, 2);

            if ($sourceAfter < 0) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => 'Saldo rekening sumber tidak cukup untuk transfer.',
                ];
            }

            $this->db->where('id', $accountId)->update('fin_company_account', [
                'current_balance' => $sourceAfter,
            ]);
            $this->db->where('id', $toAccountId)->update('fin_company_account', [
                'current_balance' => $targetAfter,
            ]);

            $transferRef = $referenceNo;
            if ($transferRef === null || $transferRef === '') {
                $transferRef = 'TRF-' . date('YmdHis');
            }
            $notesOut = $notes !== null && $notes !== ''
                ? $notes
                : 'Transfer ke ' . (string)($target['account_code'] ?? ('#' . $toAccountId));
            $notesIn = $notes !== null && $notes !== ''
                ? $notes
                : 'Transfer dari ' . (string)($source['account_code'] ?? ('#' . $accountId));

            $mutationNoOut = $this->generateAccountMutationNo($mutationDate);
            $this->db->insert('fin_account_mutation_log', [
                'mutation_no' => $mutationNoOut,
                'mutation_date' => $mutationDate,
                'account_id' => $accountId,
                'mutation_type' => 'OUT',
                'amount' => $amount,
                'balance_before' => $sourceBefore,
                'balance_after' => $sourceAfter,
                'ref_module' => 'FINANCE_TRANSFER',
                'ref_table' => null,
                'ref_id' => null,
                'ref_no' => $transferRef,
                'notes' => $notesOut,
                'created_by' => $userId > 0 ? $userId : null,
            ]);
            $mutationIds[] = (int)$this->db->insert_id();
            $mutationNos[] = $mutationNoOut;

            $mutationNoIn = $this->generateAccountMutationNo($mutationDate);
            $this->db->insert('fin_account_mutation_log', [
                'mutation_no' => $mutationNoIn,
                'mutation_date' => $mutationDate,
                'account_id' => $toAccountId,
                'mutation_type' => 'IN',
                'amount' => $amount,
                'balance_before' => $targetBefore,
                'balance_after' => $targetAfter,
                'ref_module' => 'FINANCE_TRANSFER',
                'ref_table' => null,
                'ref_id' => null,
                'ref_no' => $transferRef,
                'notes' => $notesIn,
                'created_by' => $userId > 0 ? $userId : null,
            ]);
            $mutationIds[] = (int)$this->db->insert_id();
            $mutationNos[] = $mutationNoIn;

            if ($this->db->table_exists('aud_transaction_log')) {
                $this->db->insert('aud_transaction_log', [
                    'module_code' => 'FINANCE',
                    'action_code' => 'ACCOUNT_TRANSFER',
                    'entity_table' => 'fin_account_mutation_log',
                    'entity_id' => $mutationIds[0] > 0 ? $mutationIds[0] : null,
                    'transaction_no' => $transferRef,
                    'actor_user_id' => $userId > 0 ? $userId : null,
                    'source_ip' => $sourceIp !== '' ? $sourceIp : null,
                    'after_payload' => json_encode([
                        'from_account_id' => $accountId,
                        'to_account_id' => $toAccountId,
                        'amount' => $amount,
                        'from_balance_before' => $sourceBefore,
                        'from_balance_after' => $sourceAfter,
                        'to_balance_before' => $targetBefore,
                        'to_balance_after' => $targetAfter,
                        'mutation_date' => $mutationDate,
                        'mutation_no_out' => $mutationNoOut,
                        'mutation_no_in' => $mutationNoIn,
                    ]),
                    'notes' => 'Mutasi antar rekening manual',
                ]);
            }
        } else {
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
                'ref_no' => $referenceNo,
                'notes' => $notes,
                'created_by' => $userId > 0 ? $userId : null,
            ]);
            $mutationId = (int)$this->db->insert_id();
            $mutationIds[] = $mutationId;
            $mutationNos[] = $mutationNo;

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
            'message' => $mutationType === 'TRANSFER'
                ? 'Mutasi antar rekening berhasil diposting.'
                : 'Mutasi rekening berhasil diposting.',
            'data' => [
                'mutation_id' => $mutationIds[0] ?? 0,
                'mutation_no' => $mutationNos[0] ?? null,
                'mutation_ids' => $mutationIds,
                'mutation_nos' => $mutationNos,
                'account_id' => $accountId,
                'mutation_type' => $mutationType,
                'amount' => $amount,
                'to_account_id' => $mutationType === 'TRANSFER' ? $toAccountId : null,
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

    public function search_opening_items(string $q, int $limit = 20): array
    {
        if (!$this->db->table_exists('mst_item')) {
            return [];
        }

        $q = trim($q);
        if ($q === '') {
            return [];
        }

        $limit = max(1, min(50, $limit));
        $mergedRows = [];
        $seen = [];
        $existingProfileRows = $this->searchOpeningExistingProfiles($q, $limit * 2);
        foreach ($existingProfileRows as $row) {
            $dedupeKey = $this->openingSearchRowKey($row);
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;
            $mergedRows[] = $row;
            if (count($mergedRows) >= $limit) {
                return $this->enrichOpeningSearchSuggestedPrices($mergedRows);
            }
        }

        $hasMaterial = $this->db->table_exists('mst_material') && $this->db->field_exists('material_id', 'mst_item');
        $hasUomTable = $this->db->table_exists('mst_uom');
        $hasBuyUom = $this->db->field_exists('buy_uom_id', 'mst_item');
        $hasContentUom = $this->db->field_exists('content_uom_id', 'mst_item');
        $hasContentPerBuy = $this->db->field_exists('content_per_buy', 'mst_item');
        $hasIsMaterial = $this->db->field_exists('is_material', 'mst_item');
        $hasBaseUom = $this->db->field_exists('base_uom_id', 'mst_item');
        $hasWarehouseMonthlyStock = $this->db->table_exists('inv_warehouse_monthly_stock')
            && $this->db->field_exists('item_id', 'inv_warehouse_monthly_stock')
            && $this->db->field_exists('closing_qty_content', 'inv_warehouse_monthly_stock');
        $hasCatalogTable = $this->db->table_exists('mst_purchase_catalog');
        $hasCatalogItem = $hasCatalogTable && $this->db->field_exists('item_id', 'mst_purchase_catalog');
        $hasCatalogMaterial = $hasCatalogTable && $this->db->field_exists('material_id', 'mst_purchase_catalog');
        $hasCatalogIsActive = $hasCatalogTable && $this->db->field_exists('is_active', 'mst_purchase_catalog');

        $buyUomField = $hasBuyUom
            ? 'i.buy_uom_id'
            : ($hasBaseUom ? 'i.base_uom_id' : 'NULL');
        $contentUomField = $hasContentUom
            ? 'i.content_uom_id'
            : ($hasBaseUom ? 'i.base_uom_id' : 'NULL');
        $isMaterialExpr = $hasIsMaterial
            ? 'COALESCE(i.is_material, 0)'
            : ($hasMaterial ? 'CASE WHEN i.material_id IS NULL OR i.material_id = 0 THEN 0 ELSE 1 END' : '0');
        $contentPerBuyExpr = $hasContentPerBuy ? 'COALESCE(i.content_per_buy, 1)' : '1';
        $targetMonth = date('Y-m-01');
        if ($hasWarehouseMonthlyStock) {
            $warehousePriorityExpr = "CASE WHEN EXISTS (
                SELECT 1
                FROM inv_warehouse_monthly_stock ws
                WHERE ws.item_id = i.id
                  AND COALESCE(ws.closing_qty_content, 0) <> 0
                  AND ws.month_key = (
                      SELECT MAX(wm.month_key)
                      FROM inv_warehouse_monthly_stock wm
                      WHERE wm.identity_key = ws.identity_key
                        AND wm.month_key <= " . $this->db->escape($targetMonth) . "
                  )
            ) THEN 0 ELSE 1 END";
        } else {
            $warehousePriorityExpr = '1';
        }

        $catalogPriorityExpr = '2';
        if ($hasCatalogItem || ($hasCatalogMaterial && $hasMaterial)) {
            $catalogPredicates = [];
            if ($hasCatalogItem) {
                $catalogPredicates[] = 'c.item_id = i.id';
            }
            if ($hasCatalogMaterial && $hasMaterial) {
                $catalogPredicates[] = 'c.material_id = i.material_id';
            }

            $catalogWhere = implode(' OR ', $catalogPredicates);
            if ($catalogWhere !== '') {
                $catalogPriorityExpr = 'CASE WHEN EXISTS (SELECT 1 FROM mst_purchase_catalog c WHERE ';
                if ($hasCatalogIsActive) {
                    $catalogPriorityExpr .= 'COALESCE(c.is_active, 1) = 1 AND ';
                }
                $catalogPriorityExpr .= '(' . $catalogWhere . ')) THEN 0 ELSE 1 END';
            }
        }

        $this->db
            ->select('i.id, i.item_code, i.item_name')
            ->select("'MASTER' AS source_type", false)
            ->select('NULL AS profile_key, NULL AS profile_name, NULL AS profile_brand, NULL AS profile_description, NULL AS profile_expired_date', false)
            ->select($buyUomField . ' AS default_buy_uom_id', false)
            ->select($contentUomField . ' AS default_content_uom_id', false)
            ->select($contentPerBuyExpr . ' AS default_content_per_buy', false)
            ->select($isMaterialExpr . ' AS is_material', false)
            ->select($warehousePriorityExpr . ' AS warehouse_priority', false)
            ->select($catalogPriorityExpr . ' AS catalog_priority', false)
            ->from('mst_item i')
            ->where('i.is_active', 1);

        if ($hasMaterial) {
            $this->db->select('m.material_code, m.material_name, m.id AS material_id');
            $this->db->join('mst_material m', 'm.id = i.material_id', 'left');
        } else {
            $this->db->select('NULL AS material_code, NULL AS material_name, NULL AS material_id', false);
        }

        if ($hasUomTable) {
            if ($buyUomField !== 'NULL') {
                $this->db->select('bu.code AS default_buy_uom_code, bu.name AS default_buy_uom_name');
                $this->db->join('mst_uom bu', 'bu.id = ' . $buyUomField, 'left', false);
            } else {
                $this->db->select('NULL AS default_buy_uom_code, NULL AS default_buy_uom_name', false);
            }
            if ($contentUomField !== 'NULL') {
                $this->db->select('cu.code AS default_content_uom_code, cu.name AS default_content_uom_name');
                $this->db->join('mst_uom cu', 'cu.id = ' . $contentUomField, 'left', false);
            } else {
                $this->db->select('NULL AS default_content_uom_code, NULL AS default_content_uom_name', false);
            }
        } else {
            $this->db->select('NULL AS default_buy_uom_code, NULL AS default_buy_uom_name', false);
            $this->db->select('NULL AS default_content_uom_code, NULL AS default_content_uom_name', false);
        }

        $this->db->group_start()
            ->like('i.item_code', $q)
            ->or_like('i.item_name', $q);
        if ($hasMaterial) {
            $this->db
                ->or_like('m.material_code', $q)
                ->or_like('m.material_name', $q);
        }
        $this->db->group_end();

        $masterRows = $this->db
            ->order_by('warehouse_priority', 'ASC')
            ->order_by('catalog_priority', 'ASC')
            ->order_by('CASE WHEN i.item_code LIKE ' . $this->db->escape($q . '%') . ' THEN 0 ELSE 1 END', '', false)
            ->order_by('i.item_name', 'ASC')
            ->limit($limit * 2)
            ->get()
            ->result_array();

        foreach ($masterRows as $row) {
            $dedupeKey = $this->openingSearchRowKey($row);
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;
            $mergedRows[] = $row;
            if (count($mergedRows) >= $limit) {
                break;
            }
        }

        return $this->enrichOpeningSearchSuggestedPrices($mergedRows);
    }

    private function openingSearchRowKey(array $row): string
    {
        $itemId = (int)($row['id'] ?? $row['item_id'] ?? 0);
        return implode('|', [
            $itemId,
            trim((string)($row['profile_key'] ?? '')),
            (int)($row['default_buy_uom_id'] ?? 0),
            (int)($row['default_content_uom_id'] ?? 0),
            number_format((float)($row['default_content_per_buy'] ?? 1), 6, '.', ''),
            strtoupper(trim((string)($row['profile_name'] ?? ''))),
            strtoupper(trim((string)($row['profile_brand'] ?? ''))),
            strtoupper(trim((string)($row['profile_description'] ?? ''))),
        ]);
    }

    private function enrichOpeningSearchSuggestedPrices(array $rows): array
    {
        if (empty($rows) || !$this->db->table_exists('mst_purchase_catalog')) {
            return $rows;
        }

        $hasCatalogProfileKey = $this->db->field_exists('profile_key', 'mst_purchase_catalog');
        $hasCatalogItem = $this->db->field_exists('item_id', 'mst_purchase_catalog');
        $hasCatalogMaterial = $this->db->field_exists('material_id', 'mst_purchase_catalog');
        if (!$hasCatalogProfileKey && !$hasCatalogItem && !$hasCatalogMaterial) {
            return $rows;
        }

        $profileKeys = [];
        $itemIds = [];
        $materialIds = [];
        foreach ($rows as $row) {
            $profileKey = trim((string)($row['profile_key'] ?? ''));
            if ($profileKey !== '') {
                $profileKeys[$profileKey] = $profileKey;
            }
            $itemId = (int)($row['id'] ?? $row['item_id'] ?? 0);
            if ($itemId > 0) {
                $itemIds[$itemId] = $itemId;
            }
            $materialId = (int)($row['material_id'] ?? 0);
            if ($materialId > 0) {
                $materialIds[$materialId] = $materialId;
            }
        }

        if (empty($profileKeys) && empty($itemIds) && empty($materialIds)) {
            return $rows;
        }

        $catalogFactorExpr = 'ROUND(COALESCE(NULLIF(c.content_per_buy, 0), NULLIF(c.conversion_factor_to_content, 0), 1), 6)';
        $useVendorLinkTable = $this->purchaseCatalogVendorTableExists();
        $latestVendorPriceSql = $useVendorLinkTable ? $this->latestCatalogVendorPriceSubquerySql() : '';
        if ($useVendorLinkTable) {
            $standardPriceExpr = 'COALESCE(cvl.standard_price, cvl.last_unit_price, c.standard_price, c.last_unit_price)';
            $lastUnitPriceExpr = 'COALESCE(cvl.last_unit_price, cvl.standard_price, c.last_unit_price, c.standard_price)';
            $lastPurchaseDateExpr = 'COALESCE(cvl.last_purchase_date, c.last_purchase_date)';
        } else {
            $standardPriceExpr = 'COALESCE(c.standard_price, c.last_unit_price)';
            $lastUnitPriceExpr = 'COALESCE(c.last_unit_price, c.standard_price)';
            $lastPurchaseDateExpr = 'c.last_purchase_date';
        }

        $this->db
            ->select('c.id, c.profile_key, c.item_id, c.material_id, c.buy_uom_id, c.content_uom_id', false)
            ->select($catalogFactorExpr . ' AS content_per_buy', false)
            ->select('ROUND(COALESCE(' . $lastUnitPriceExpr . ', 0), 2) AS last_unit_price', false)
            ->select('ROUND(COALESCE(' . $standardPriceExpr . ', 0), 2) AS standard_price', false)
            ->select($lastPurchaseDateExpr . ' AS last_purchase_date', false)
            ->from('mst_purchase_catalog c');

        if ($useVendorLinkTable) {
            $this->db->join('(' . $latestVendorPriceSql . ') cvl', 'cvl.catalog_id = c.id', 'left', false);
        }
        if ($this->db->field_exists('is_active', 'mst_purchase_catalog')) {
            $this->db->where('COALESCE(c.is_active, 1) = 1', null, false);
        }
        $this->db->group_start();
        $hasPredicate = false;
        if ($hasCatalogProfileKey && !empty($profileKeys)) {
            $this->db->where_in('c.profile_key', array_values($profileKeys));
            $hasPredicate = true;
        }
        if ($hasCatalogItem && !empty($itemIds)) {
            if ($hasPredicate) {
                $this->db->or_where_in('c.item_id', array_values($itemIds));
            } else {
                $this->db->where_in('c.item_id', array_values($itemIds));
                $hasPredicate = true;
            }
        }
        if ($hasCatalogMaterial && !empty($materialIds)) {
            if ($hasPredicate) {
                $this->db->or_where_in('c.material_id', array_values($materialIds));
            } else {
                $this->db->where_in('c.material_id', array_values($materialIds));
                $hasPredicate = true;
            }
        }
        $this->db->group_end();

        $catalogRows = $this->db
            ->order_by($lastPurchaseDateExpr, 'DESC', false)
            ->order_by('c.id', 'DESC')
            ->get()
            ->result_array();
        if (empty($catalogRows)) {
            return $rows;
        }

        $catalogByProfile = [];
        $catalogByItem = [];
        $catalogByMaterial = [];
        foreach ($catalogRows as $catalogRow) {
            $profileKey = trim((string)($catalogRow['profile_key'] ?? ''));
            if ($profileKey !== '') {
                $catalogByProfile[$profileKey][] = $catalogRow;
            }
            $itemId = (int)($catalogRow['item_id'] ?? 0);
            if ($itemId > 0) {
                $catalogByItem[$itemId][] = $catalogRow;
            }
            $materialId = (int)($catalogRow['material_id'] ?? 0);
            if ($materialId > 0) {
                $catalogByMaterial[$materialId][] = $catalogRow;
            }
        }

        foreach ($rows as &$row) {
            $match = null;
            $profileKey = trim((string)($row['profile_key'] ?? ''));
            $itemId = (int)($row['id'] ?? $row['item_id'] ?? 0);
            $materialId = (int)($row['material_id'] ?? 0);

            if ($profileKey !== '' && isset($catalogByProfile[$profileKey])) {
                $match = $this->pickOpeningSuggestedCatalogRow($row, $catalogByProfile[$profileKey]);
            }
            if ($match === null && $itemId > 0 && isset($catalogByItem[$itemId])) {
                $match = $this->pickOpeningSuggestedCatalogRow($row, $catalogByItem[$itemId]);
            }
            if ($match === null && $materialId > 0 && isset($catalogByMaterial[$materialId])) {
                $match = $this->pickOpeningSuggestedCatalogRow($row, $catalogByMaterial[$materialId]);
            }

            $suggestedUnitPrice = 0.0;
            $suggestedSource = null;
            if ($match !== null) {
                $lastUnitPrice = round((float)($match['last_unit_price'] ?? 0), 2);
                $standardPrice = round((float)($match['standard_price'] ?? 0), 2);
                if ($lastUnitPrice > 0) {
                    $suggestedUnitPrice = $lastUnitPrice;
                    $suggestedSource = 'CATALOG_LAST_PURCHASE';
                } elseif ($standardPrice > 0) {
                    $suggestedUnitPrice = $standardPrice;
                    $suggestedSource = 'CATALOG_STANDARD';
                }
            }

            $contentPerBuy = max(0.000001, (float)($row['default_content_per_buy'] ?? 1));
            $row['suggested_unit_price'] = round($suggestedUnitPrice, 2);
            $row['suggested_avg_cost_per_content'] = round($suggestedUnitPrice / $contentPerBuy, 6);
            $row['suggested_price_source'] = $suggestedSource;
        }
        unset($row);

        return $rows;
    }

    private function pickOpeningSuggestedCatalogRow(array $row, array $candidates): ?array
    {
        if (empty($candidates)) {
            return null;
        }

        $targetProfileKey = trim((string)($row['profile_key'] ?? ''));
        $targetItemId = (int)($row['id'] ?? $row['item_id'] ?? 0);
        $targetMaterialId = (int)($row['material_id'] ?? 0);
        $targetBuyUomId = (int)($row['default_buy_uom_id'] ?? 0);
        $targetContentUomId = (int)($row['default_content_uom_id'] ?? 0);
        $targetFactor = round((float)($row['default_content_per_buy'] ?? 1), 6);

        $best = null;
        $bestScore = -1;
        foreach ($candidates as $candidate) {
            $score = 0;
            if ($targetProfileKey !== '' && $targetProfileKey === trim((string)($candidate['profile_key'] ?? ''))) {
                $score += 1000;
            }
            if ($targetItemId > 0 && $targetItemId === (int)($candidate['item_id'] ?? 0)) {
                $score += 200;
            }
            if ($targetMaterialId > 0 && $targetMaterialId === (int)($candidate['material_id'] ?? 0)) {
                $score += 100;
            }
            if ($targetBuyUomId > 0 && $targetBuyUomId === (int)($candidate['buy_uom_id'] ?? 0)) {
                $score += 30;
            }
            if ($targetContentUomId > 0 && $targetContentUomId === (int)($candidate['content_uom_id'] ?? 0)) {
                $score += 30;
            }
            if (abs($targetFactor - round((float)($candidate['content_per_buy'] ?? 1), 6)) < 0.000001) {
                $score += 20;
            }
            if ((float)($candidate['last_unit_price'] ?? 0) > 0) {
                $score += 5;
            } elseif ((float)($candidate['standard_price'] ?? 0) > 0) {
                $score += 3;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        return $best;
    }

    private function searchOpeningExistingProfiles(string $q, int $limit): array
    {
        $limit = max(1, min(50, $limit));
        $rows = [];
        $seen = [];
        $stockCandidates = [];
        $hasMaterial = $this->db->table_exists('mst_material') && $this->db->field_exists('material_id', 'mst_item');
        $hasUom = $this->db->table_exists('mst_uom');

        if ($this->db->table_exists('inv_warehouse_monthly_stock') && $this->db->table_exists('mst_item')) {
            $targetMonth = date('Y-m-01');
            $latestMonthSubquery = $this->db
                ->select('identity_key, MAX(month_key) AS month_key', false)
                ->from('inv_warehouse_monthly_stock')
                ->where('month_key <=', $targetMonth)
                ->group_by('identity_key')
                ->get_compiled_select();

            $this->db
                ->select("'PROFILE_STOCK' AS source_type", false)
                ->select('s.item_id AS id, i.item_code, i.item_name')
                ->select('s.buy_uom_id AS default_buy_uom_id, s.content_uom_id AS default_content_uom_id')
                ->select('COALESCE(NULLIF(s.profile_content_per_buy, 0), 1) AS default_content_per_buy', false)
                ->select('CASE WHEN COALESCE(i.material_id, 0) > 0 THEN 1 ELSE 0 END AS is_material', false)
                ->select('COALESCE(i.material_id, 0) AS material_id', false)
                ->select('m.material_code, m.material_name')
                ->select('s.profile_key, s.profile_name, s.profile_brand, s.profile_description')
                ->from('inv_warehouse_monthly_stock s')
                ->join('(' . $latestMonthSubquery . ') lm', 'lm.identity_key = s.identity_key AND lm.month_key = s.month_key', 'inner', false)
                ->join('mst_item i', 'i.id = s.item_id', 'inner')
                ->join('mst_material m', 'm.id = i.material_id', 'left')
                ->where('i.is_active', 1);

            if ($hasUom) {
                $this->db->select('bu.code AS default_buy_uom_code, bu.name AS default_buy_uom_name');
                $this->db->select('cu.code AS default_content_uom_code, cu.name AS default_content_uom_name');
                $this->db->join('mst_uom bu', 'bu.id = s.buy_uom_id', 'left');
                $this->db->join('mst_uom cu', 'cu.id = s.content_uom_id', 'left');
            } else {
                $this->db->select('NULL AS default_buy_uom_code, NULL AS default_buy_uom_name', false);
                $this->db->select('NULL AS default_content_uom_code, NULL AS default_content_uom_name', false);
            }

            $this->db->select('s.profile_expired_date');

            $this->db->group_start()
                ->like('i.item_code', $q)
                ->or_like('i.item_name', $q)
                ->or_like('s.profile_name', $q)
                ->or_like('s.profile_brand', $q)
                ->or_like('s.profile_description', $q);
            if ($hasMaterial) {
                $this->db
                    ->or_like('m.material_code', $q)
                    ->or_like('m.material_name', $q);
            }
            $this->db->group_end();

            $stockRows = $this->db
                ->order_by('COALESCE(s.updated_at, s.last_movement_at, CONCAT(s.month_key, " 00:00:00"))', 'DESC', false)
                ->order_by('i.item_name', 'ASC')
                ->order_by('s.profile_name', 'ASC')
                ->limit($limit * 2)
                ->get()
                ->result_array();
        }

        if ($this->db->table_exists('mst_purchase_catalog') && $this->db->table_exists('mst_item')) {
            $hasCatalogActive = $this->db->field_exists('is_active', 'mst_purchase_catalog');
            $hasCatalogExpired = $this->db->field_exists('expired_date', 'mst_purchase_catalog');
            $hasCatalogLastPurchaseDate = $this->db->field_exists('last_purchase_date', 'mst_purchase_catalog');
            $hasCatalogMaterial = $this->db->field_exists('material_id', 'mst_purchase_catalog');

            $materialMapSub = $this->db
                ->select('material_id, MIN(id) AS mapped_item_id', false)
                ->from('mst_item')
                ->where('is_active', 1)
                ->where('material_id IS NOT NULL', null, false)
                ->where('material_id >', 0)
                ->group_by('material_id')
                ->get_compiled_select();

            $itemExpr = $hasCatalogMaterial ? 'COALESCE(c.item_id, imap.mapped_item_id)' : 'c.item_id';
            $materialExpr = $hasCatalogMaterial ? 'COALESCE(c.material_id, i.material_id)' : 'i.material_id';

            $this->db
                ->select("'PROFILE_CATALOG' AS source_type", false)
                ->select($itemExpr . ' AS id', false)
                ->select('i.item_code, i.item_name')
                ->select('c.buy_uom_id AS default_buy_uom_id, c.content_uom_id AS default_content_uom_id')
                ->select('COALESCE(NULLIF(c.content_per_buy, 0), 1) AS default_content_per_buy', false)
                ->select('CASE WHEN COALESCE(' . $materialExpr . ', 0) > 0 THEN 1 ELSE 0 END AS is_material', false)
                ->select('COALESCE(' . $materialExpr . ', 0) AS material_id', false)
                ->select('m.material_code, m.material_name')
                ->select('c.profile_key, c.catalog_name AS profile_name, c.brand_name AS profile_brand, c.line_description AS profile_description')
                ->from('mst_purchase_catalog c')
                ->join("({$materialMapSub}) imap", $hasCatalogMaterial ? 'imap.material_id = c.material_id' : '1=0', 'left', false)
                ->join('mst_item i', 'i.id = ' . $itemExpr, 'left', false)
                ->join('mst_material m', 'm.id = ' . $materialExpr, 'left', false)
                ->where($itemExpr . ' IS NOT NULL', null, false)
                ->where('i.is_active', 1);

            if ($hasCatalogActive) {
                $this->db->where('COALESCE(c.is_active,1) = 1', null, false);
            }

            if ($hasUom) {
                $this->db->select('bu.code AS default_buy_uom_code, bu.name AS default_buy_uom_name');
                $this->db->select('cu.code AS default_content_uom_code, cu.name AS default_content_uom_name');
                $this->db->join('mst_uom bu', 'bu.id = c.buy_uom_id', 'left');
                $this->db->join('mst_uom cu', 'cu.id = c.content_uom_id', 'left');
            } else {
                $this->db->select('NULL AS default_buy_uom_code, NULL AS default_buy_uom_name', false);
                $this->db->select('NULL AS default_content_uom_code, NULL AS default_content_uom_name', false);
            }

            if ($hasCatalogExpired) {
                $this->db->select('c.expired_date AS profile_expired_date');
            } else {
                $this->db->select('NULL AS profile_expired_date', false);
            }

            $this->db->group_start()
                ->like('c.catalog_name', $q)
                ->or_like('c.brand_name', $q)
                ->or_like('c.line_description', $q)
                ->or_like('i.item_code', $q)
                ->or_like('i.item_name', $q);
            if ($hasMaterial) {
                $this->db
                    ->or_like('m.material_code', $q)
                    ->or_like('m.material_name', $q);
            }
            $this->db->group_end();

            if ($hasCatalogLastPurchaseDate) {
                $this->db->order_by('c.last_purchase_date', 'DESC');
            }
            $catalogRows = $this->db
                ->order_by('c.id', 'DESC')
                ->limit($limit * 2)
                ->get()
                ->result_array();

            foreach ($catalogRows as $row) {
                $itemId = (int)($row['id'] ?? 0);
                if ($itemId <= 0) {
                    continue;
                }
                $dedupeKey = implode('|', [
                    $itemId,
                    trim((string)($row['profile_key'] ?? '')),
                    (int)($row['default_buy_uom_id'] ?? 0),
                    (int)($row['default_content_uom_id'] ?? 0),
                    number_format((float)($row['default_content_per_buy'] ?? 1), 6, '.', ''),
                    strtoupper(trim((string)($row['profile_name'] ?? ''))),
                    strtoupper(trim((string)($row['profile_brand'] ?? ''))),
                    strtoupper(trim((string)($row['profile_description'] ?? ''))),
                ]);
                if (isset($seen[$dedupeKey])) {
                    continue;
                }
                $seen[$dedupeKey] = true;
                $rows[] = $row;
                if (count($rows) >= $limit) {
                    return $rows;
                }
            }
        }

        // Catalog is the canonical source; stock suggestions are appended only when
        // an identity is not found in active catalog.
        foreach ($stockCandidates as $row) {
            $itemId = (int)($row['id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }
            $dedupeKey = implode('|', [
                $itemId,
                trim((string)($row['profile_key'] ?? '')),
                (int)($row['default_buy_uom_id'] ?? 0),
                (int)($row['default_content_uom_id'] ?? 0),
                number_format((float)($row['default_content_per_buy'] ?? 1), 6, '.', ''),
                strtoupper(trim((string)($row['profile_name'] ?? ''))),
                strtoupper(trim((string)($row['profile_brand'] ?? ''))),
                strtoupper(trim((string)($row['profile_description'] ?? ''))),
            ]);
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;
            $rows[] = $row;
            if (count($rows) >= $limit) {
                return $rows;
            }
        }

        return $rows;
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

    public function list_stock_opening_snapshots(string $scope, string $month, string $q, int $limit, ?int $divisionId = null, ?string $destinationFilter = null): array
    {
        $scope = strtoupper(trim($scope));
        if (!in_array($scope, ['WAREHOUSE', 'DIVISION'], true)) {
            $scope = 'WAREHOUSE';
        }

        $openingTable = $this->openingSnapshotTableForScope($scope);
        if (!$this->db->table_exists($openingTable)) {
            return [];
        }

        $monthKey = $this->normalizeMonth($month);
        $hasDestinationType = $this->db->field_exists('destination_type', $openingTable);
        $destinationFilter = $this->normalizeDestinationFilter($destinationFilter);

        $this->db
            ->select('s.id, s.snapshot_month')
            ->select($this->db->escape($scope) . ' AS stock_scope', false)
            ->select('s.item_id, s.material_id, s.buy_uom_id, s.content_uom_id, s.profile_name, s.profile_brand, s.profile_description')
            ->select('s.profile_content_per_buy, s.profile_buy_uom_code, s.profile_content_uom_code')
            ->select('s.opening_qty_buy, s.opening_qty_content, s.opening_avg_cost_per_content, s.opening_total_value, s.source_type, s.updated_at')
            ->select('i.item_code, i.item_name, m.material_code, m.material_name')
            ->from($openingTable . ' s')
            ->join('mst_item i', 'i.id = s.item_id', 'left')
            ->join('mst_material m', 'm.id = s.material_id', 'left');

        if ($scope === 'DIVISION') {
            $divisionCodeColumn = $this->db->field_exists('division_code', 'mst_operational_division')
                ? 'division_code'
                : ($this->db->field_exists('code', 'mst_operational_division') ? 'code' : null);
            $divisionNameColumn = $this->db->field_exists('division_name', 'mst_operational_division')
                ? 'division_name'
                : ($this->db->field_exists('name', 'mst_operational_division') ? 'name' : null);
            $divisionCodeSelect = $divisionCodeColumn !== null ? ('d.' . $divisionCodeColumn . ' AS division_code') : 'CAST(s.division_id AS CHAR) AS division_code';
            $divisionNameSelect = $divisionNameColumn !== null ? ('d.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';
            $destinationTypeExpr = $hasDestinationType ? 's.destination_type' : "'OTHER'";
            $destinationGroupExpr = "CASE
                    WHEN COALESCE({$destinationTypeExpr}, 'OTHER') IN ('BAR_EVENT','KITCHEN_EVENT') THEN 'EVENT'
                    ELSE 'REGULER'
                END";

            $this->db
                ->select('s.division_id, ' . $divisionCodeSelect . ', ' . $divisionNameSelect, false)
                ->select($destinationTypeExpr . ' AS destination_type', false)
                ->select($destinationGroupExpr . ' AS destination_group', false)
                ->join('mst_operational_division d', 'd.id = s.division_id', 'left');
        } else {
            $this->db
                ->select('NULL AS division_id, NULL AS division_code, NULL AS division_name', false)
                ->select('NULL AS destination_type, NULL AS destination_group', false);
        }

        if ($this->db->field_exists('profile_expired_date', $openingTable)) {
            $this->db->select('s.profile_expired_date');
        } else {
            $this->db->select('NULL AS profile_expired_date', false);
        }

        if ($monthKey !== null) {
            $this->db->where('s.snapshot_month', $monthKey);
        }

        if ($scope === 'DIVISION' && $divisionId !== null && $divisionId > 0) {
            $this->db->where('s.division_id', $divisionId);
        }

        if ($scope === 'DIVISION' && $destinationFilter !== null) {
            if ($destinationFilter === 'REGULER') {
                if ($hasDestinationType) {
                    $this->db->where_not_in('s.destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
                } else {
                    $this->db->where("'OTHER' NOT IN ('BAR_EVENT','KITCHEN_EVENT')", null, false);
                }
            } elseif ($destinationFilter === 'EVENT') {
                if ($hasDestinationType) {
                    $this->db->where_in('s.destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
                } else {
                    $this->db->where("'OTHER' IN ('BAR_EVENT','KITCHEN_EVENT')", null, false);
                }
            } else {
                if ($hasDestinationType) {
                    $this->db->where('s.destination_type', $destinationFilter);
                } else {
                    $this->db->where("'OTHER' = " . $this->db->escape($destinationFilter), null, false);
                }
            }
        }

        if ($q !== '') {
            $this->db->group_start()
                ->like('i.item_code', $q)
                ->or_like('i.item_name', $q)
                ->or_like('m.material_code', $q)
                ->or_like('m.material_name', $q)
                ->or_like('s.profile_name', $q)
                ->or_like('s.profile_brand', $q)
                ->or_like('s.profile_description', $q)
                ->or_like('s.profile_key', $q)
                ->group_end();
        }

        $this->db
            ->order_by('s.snapshot_month', 'DESC')
            ->order_by('i.item_name', 'ASC')
            ->order_by('m.material_name', 'ASC')
            ->limit($limit);

        if ($scope === 'DIVISION') {
            $this->db->order_by('s.division_id', 'ASC');
        }

        return $this->db->get()->result_array();
    }

    public function list_warehouse_opening_snapshots(string $month, string $q, int $limit): array
    {
        return $this->list_stock_opening_snapshots('WAREHOUSE', $month, $q, $limit, null, null);
    }

    public function list_division_generate_openings(array $filters = [], int $limit = 500): array
    {
        $openingTable = 'inv_division_stock_opening_snapshot';
        if (!$this->db->table_exists($openingTable)) {
            return [];
        }

        $month      = trim((string)($filters['month'] ?? date('Y-m')));
        $monthKey   = $this->normalizeMonth($month);
        $divisionId = !empty($filters['division_id']) ? (int)$filters['division_id'] : null;
        $destFilter = $this->normalizeDestinationFilter($filters['destination_type'] ?? '');
        $q          = trim((string)($filters['q'] ?? ''));

        $divisionNameColumn = $this->db->field_exists('division_name', 'mst_operational_division')
            ? 'division_name'
            : ($this->db->field_exists('name', 'mst_operational_division') ? 'name' : null);
        $divisionNameSelect = $divisionNameColumn !== null ? ('d.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';

        $this->db
            ->select('s.id, s.snapshot_month, s.division_id, s.destination_type, s.stock_domain', false)
            ->select($divisionNameSelect, false)
            ->select('s.item_id, s.material_id, s.profile_name, s.profile_brand, s.profile_content_per_buy, s.profile_buy_uom_code, s.profile_content_uom_code', false)
            ->select('s.opening_qty_buy, s.opening_qty_content, s.opening_avg_cost_per_content, s.opening_total_value, s.source_type, s.notes, s.updated_at', false)
            ->select('i.item_code, i.item_name, m.material_code, m.material_name', false)
            ->from($openingTable . ' s')
            ->join('mst_item i', 'i.id = s.item_id', 'left')
            ->join('mst_material m', 'm.id = s.material_id', 'left')
            ->join('mst_operational_division d', 'd.id = s.division_id', 'left')
            ->where('s.source_type', 'OPNAME');

        if ($monthKey !== null) {
            $this->db->where('s.snapshot_month', $monthKey);
        }
        if ($divisionId !== null && $divisionId > 0) {
            $this->db->where('s.division_id', $divisionId);
        }
        if ($destFilter === 'REGULER') {
            $this->db->where_not_in('s.destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
        } elseif ($destFilter === 'EVENT') {
            $this->db->where_in('s.destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
        } elseif ($destFilter !== null && $destFilter !== '') {
            $this->db->where('s.destination_type', $destFilter);
        }
        if ($q !== '') {
            $this->db->group_start()
                ->like('s.profile_name', $q)
                ->or_like('i.item_name', $q)
                ->or_like('i.item_code', $q)
                ->or_like('m.material_name', $q)
                ->or_like('m.material_code', $q)
                ->group_end();
        }

        $this->db
            ->order_by('s.snapshot_month', 'DESC')
            ->order_by($divisionNameColumn !== null ? ('d.' . $divisionNameColumn) : 's.division_id', 'ASC')
            ->order_by('s.destination_type', 'ASC')
            ->order_by('s.profile_name', 'ASC')
            ->limit(max(1, $limit));

        return $this->db->get()->result_array();
    }

    public function list_warehouse_daily_snapshot(string $month, string $q, string $dateFrom, string $dateTo, int $limit): array
    {
        if ($this->db->table_exists('inv_stock_movement_log')) {
            $window = $this->resolveDailyWindow($month, $dateFrom, $dateTo);
            $rows = $this->fetchInventoryDailyMatrixSourceRowsFromMovement(
                'WAREHOUSE',
                $q,
                null,
                $window['date_from'],
                $window['date_to'],
                null,
                false
            );
            $rows = $this->filterZeroOpeningClosingDailyRows($rows);

            return $this->limitRowsByProfile($rows, $limit);
        }

        return [];
    }

    public function get_stock_opening_snapshot(string $scope, int $id): ?array
    {
        $scope = strtoupper(trim($scope));
        if (!in_array($scope, ['WAREHOUSE', 'DIVISION'], true) || $id <= 0) {
            return null;
        }

        $openingTable = $this->openingSnapshotTableForScope($scope);
        if (!$this->db->table_exists($openingTable)) {
            return null;
        }

        $row = $this->db->get_where($openingTable, ['id' => $id])->row_array();
        return $row ?: null;
    }

    public function list_warehouse_daily_matrix(string $month, string $q, string $dateFrom, string $dateTo, int $limit): array
    {
        $window = $this->resolveDailyWindow($month, $dateFrom, $dateTo);
        $dates = $this->buildDateSeries($window['date_from'], $window['date_to']);

        if ($this->db->table_exists('inv_stock_movement_log')) {
            $rows = $this->fetchInventoryDailyMatrixSourceRowsFromMovement(
                'WAREHOUSE',
                $q,
                null,
                $window['date_from'],
                $window['date_to'],
                null,
                false
            );
            $rows = $this->filterZeroOpeningClosingDailyRows($rows);

            return [
                'window' => $window,
                'dates' => $dates,
                'rows' => $this->pivotDailyRows($rows, $limit),
            ];
        }

        return [
            'window' => [
                'month_key' => null,
                'date_from' => null,
                'date_to' => null,
            ],
            'dates' => [],
            'rows' => [],
        ];
    }

    public function list_division_daily_snapshot(string $month, string $q, ?int $divisionId, string $dateFrom, string $dateTo, int $limit, ?string $destinationFilter = null): array
    {
        if ($this->db->table_exists('inv_division_monthly_stock')) {
            return $this->build_division_daily_rows_from_monthly_base(
                $q,
                $divisionId,
                $dateFrom,
                $dateTo,
                $limit,
                $destinationFilter
            );
        }

        if ($this->db->table_exists('inv_stock_movement_log')) {
            $window = $this->resolveDailyWindow($month, $dateFrom, $dateTo);
            $rows = $this->fetchInventoryDailyMatrixSourceRowsFromMovement(
                'DIVISION',
                $q,
                $divisionId,
                $window['date_from'],
                $window['date_to'],
                $destinationFilter,
                false
            );
            $rows = $this->normalizeDivisionProfileKeyRows($rows);
            $rows = $this->filterZeroOpeningClosingDailyRows($rows);

            return $this->limitRowsByProfile($rows, $limit);
        }

        return [];
    }

    public function list_material_daily_matrix(string $month, string $q, ?int $divisionId, string $dateFrom, string $dateTo, int $limit, ?string $destinationFilter = null, int $offset = 0): array
    {
        $window = $this->resolveDailyWindow($month, $dateFrom, $dateTo);
        $dates = $this->buildDateSeries($window['date_from'], $window['date_to']);

        if ($this->db->table_exists('inv_division_monthly_stock')) {
            $result = $this->build_material_daily_rows_from_monthly_base(
                $q,
                $divisionId,
                $window['date_from'],
                $window['date_to'],
                $dates,
                $limit,
                $destinationFilter,
                $offset
            );
            $rows = $this->attachMaterialDailyProfilePrices($result['rows']);

            return [
                'window'      => $window,
                'dates'       => $dates,
                'rows'        => $rows,
                'total_count' => $result['total_count'],
            ];
        }

        if ($this->db->table_exists('inv_stock_movement_log')) {
            $rows = $this->fetchInventoryDailyMatrixSourceRowsFromMovement(
                'DIVISION',
                $q,
                $divisionId,
                $window['date_from'],
                $window['date_to'],
                $destinationFilter,
                true
            );
            $rows = $this->normalizeDivisionProfileKeyRows($rows);
            $rows = $this->filterZeroOpeningClosingDailyRows($rows);
            $rows = $this->attachMaterialDailyProfilePrices($rows);

            return [
                'window' => $window,
                'dates' => $dates,
                'rows' => $this->pivotDailyRows($rows, $limit),
            ];
        }

        return [
            'window' => [
                'month_key' => null,
                'date_from' => null,
                'date_to' => null,
            ],
            'dates' => [],
            'rows' => [],
        ];
    }

    private function build_division_daily_rows_from_monthly_base(
        string $q,
        ?int $divisionId,
        string $dateFrom,
        string $dateTo,
        int $limit,
        ?string $destinationFilter = null
    ): array {
        $dates = $this->buildDateSeries($dateFrom, $dateTo);
        if (empty($dates)) {
            return [];
        }

        $baseRows = $this->list_division_stock_monthly($q, $limit, $destinationFilter, '', $dateTo, $divisionId);
        if (empty($baseRows)) {
            return [];
        }

        $movementRows = [];
        if ($this->db->table_exists('inv_stock_movement_log')) {
            $movementRows = $this->fetchInventoryDailyMatrixSourceRowsFromMovement(
                'DIVISION',
                '',
                $divisionId,
                $dateFrom,
                $dateTo,
                $destinationFilter,
                false
            );
            $movementRows = $this->normalizeDivisionProfileKeyRows($movementRows);
        }

        $movementByIdentity = [];
        foreach ($movementRows as $movementRow) {
            $identityKey = $this->buildInventoryDailyMatrixIdentityKey('DIVISION', $movementRow);
            if (!isset($movementByIdentity[$identityKey])) {
                $movementByIdentity[$identityKey] = [];
            }
            $movementDate = (string)($movementRow['movement_date'] ?? '');
            if ($movementDate === '') {
                continue;
            }
            $movementByIdentity[$identityKey][$movementDate] = $movementRow;
        }

        $baseIdentitySet = [];
        $baseGroupKeys = [];
        foreach ($baseRows as $baseRow) {
            $identityKey = $this->buildInventoryDailyMatrixIdentityKey('DIVISION', $baseRow);
            $baseIdentitySet[$identityKey] = true;
            $groupKey = $this->build_material_daily_group_key($baseRow);
            if (!isset($baseGroupKeys[$groupKey])) {
                $baseGroupKeys[$groupKey] = true;
            }
        }
        $movementByIdentity = array_intersect_key($movementByIdentity, $baseIdentitySet);

        $mismatchByGroup = [];
        foreach ($movementRows as $movementRow) {
            $identityKey = $this->buildInventoryDailyMatrixIdentityKey('DIVISION', $movementRow);
            if (isset($baseIdentitySet[$identityKey])) {
                continue;
            }
            $groupKey = $this->build_material_daily_group_key($movementRow);
            if (!isset($baseGroupKeys[$groupKey])) {
                continue;
            }
            if (!isset($mismatchByGroup[$groupKey])) {
                $mismatchByGroup[$groupKey] = [
                    'audit_has_mismatch' => 0,
                    'audit_mismatch_row_count' => 0,
                    'audit_mismatch_qty_content' => 0.0,
                    'audit_mismatch_notes' => [],
                ];
            }
            $mismatchByGroup[$groupKey]['audit_has_mismatch'] = 1;
            $mismatchByGroup[$groupKey]['audit_mismatch_row_count'] += 1;
            $mismatchByGroup[$groupKey]['audit_mismatch_qty_content'] = round(
                (float)$mismatchByGroup[$groupKey]['audit_mismatch_qty_content'] + (float)($movementRow['closing_qty_content'] ?? 0),
                4
            );
            $note = trim((string)($movementRow['profile_name'] ?? ''));
            if ($note === '') {
                $note = 'fallback ' . (string)($movementRow['buy_uom_id'] ?? 0) . '->' . (string)($movementRow['content_uom_id'] ?? 0);
            }
            $mismatchByGroup[$groupKey]['audit_mismatch_notes'][$note] = true;
        }

        $dailyRows = [];
        foreach ($baseRows as $baseRow) {
            $identityKey = $this->buildInventoryDailyMatrixIdentityKey('DIVISION', $baseRow);
            $dayMap = $movementByIdentity[$identityKey] ?? [];
            $groupKey = $this->build_material_daily_group_key($baseRow);
            $mismatchMeta = $mismatchByGroup[$groupKey] ?? [
                'audit_has_mismatch' => 0,
                'audit_mismatch_row_count' => 0,
                'audit_mismatch_qty_content' => 0.0,
                'audit_mismatch_notes' => [],
            ];

            $nextClosingBuy = round((float)($baseRow['qty_buy_balance'] ?? 0), 4);
            $nextClosingContent = round((float)($baseRow['qty_content_balance'] ?? 0), 4);
            $avgCost = round((float)($baseRow['avg_cost_per_content'] ?? 0), 6);
            $totalValue = round((float)($baseRow['qty_content_balance'] ?? 0) * $avgCost, 2);

            for ($i = count($dates) - 1; $i >= 0; $i--) {
                $day = (string)$dates[$i];
                $existingRow = $dayMap[$day] ?? null;
                $row = $this->build_division_daily_base_day_row($baseRow, $day, $existingRow);
                $adjustmentContent = round($this->resolveDailyMatrixAdjustmentQtyContent($row), 4);
                $adjustmentBuy = round($this->resolveDailyMatrixAdjustmentQtyBuy($row), 4);
                $deltaContent = round(
                    (float)($row['in_qty_content'] ?? 0)
                    - (float)($row['out_qty_content'] ?? 0)
                    + $adjustmentContent,
                    4
                );
                $deltaBuy = round(
                    (float)($row['in_qty_buy'] ?? 0)
                    - (float)($row['out_qty_buy'] ?? 0)
                    + $adjustmentBuy,
                    4
                );

                $row['closing_qty_buy'] = $nextClosingBuy;
                $row['closing_qty_content'] = $nextClosingContent;
                $row['opening_qty_buy'] = round($nextClosingBuy - $deltaBuy, 4);
                $row['opening_qty_content'] = round($nextClosingContent - $deltaContent, 4);
                $row['closing_qty_pack'] = round((float)$row['closing_qty_buy'], 4);
                $row['opening_qty_pack'] = round((float)$row['opening_qty_buy'], 4);
                $row['adjustment_qty_pack'] = round((float)$adjustmentBuy, 4);
                $row['avg_cost_per_content'] = $avgCost;
                $row['total_value'] = $i === count($dates) - 1
                    ? $totalValue
                    : round((float)$row['closing_qty_content'] * $avgCost, 2);
                $row['audit_has_mismatch'] = (int)$mismatchMeta['audit_has_mismatch'];
                $row['audit_mismatch_row_count'] = (int)$mismatchMeta['audit_mismatch_row_count'];
                $row['audit_mismatch_qty_content'] = round((float)$mismatchMeta['audit_mismatch_qty_content'], 4);
                $row['audit_mismatch_notes'] = implode(', ', array_keys((array)$mismatchMeta['audit_mismatch_notes']));

                $dailyRows[] = $row;
                $nextClosingBuy = round((float)$row['opening_qty_buy'], 4);
                $nextClosingContent = round((float)$row['opening_qty_content'], 4);
            }
        }

        return $dailyRows;
    }

    private function build_division_daily_base_day_row(array $baseRow, string $day, ?array $existingRow = null): array
    {
        $row = $existingRow ?? [];
        $row['movement_date'] = $day;
        $row['division_id'] = isset($baseRow['division_id']) ? (int)$baseRow['division_id'] : null;
        $row['division_code'] = (string)($baseRow['division_code'] ?? '');
        $row['division_name'] = (string)($baseRow['division_name'] ?? '');
        $row['destination_type'] = (string)($baseRow['destination_type'] ?? 'OTHER');
        $row['destination_group'] = (string)($baseRow['destination_group'] ?? 'REGULER');
        $row['destination_name'] = (string)($baseRow['destination_name'] ?? 'Reguler');
        $row['stock_domain'] = 'ITEM';
        $row['item_id'] = (int)($baseRow['item_id'] ?? 0);
        $row['material_id'] = (int)($baseRow['material_id'] ?? 0);
        $row['buy_uom_id'] = (int)($baseRow['buy_uom_id'] ?? 0);
        $row['content_uom_id'] = (int)($baseRow['content_uom_id'] ?? 0);
        $row['item_code'] = (string)($baseRow['item_code'] ?? '');
        $row['item_name'] = (string)($baseRow['item_name'] ?? '');
        $row['material_code'] = (string)($baseRow['material_code'] ?? '');
        $row['material_name'] = (string)($baseRow['material_name'] ?? '');
        $row['profile_key'] = (string)($baseRow['profile_key'] ?? '');
        $row['profile_name'] = (string)($baseRow['profile_name'] ?? '');
        $row['profile_brand'] = (string)($baseRow['profile_brand'] ?? '');
        $row['profile_description'] = (string)($baseRow['profile_description'] ?? '');
        $row['profile_expired_date'] = (string)($baseRow['profile_expired_date'] ?? '');
        $row['profile_content_per_buy'] = round((float)($baseRow['profile_content_per_buy'] ?? 0), 6);
        $row['profile_buy_uom_code'] = (string)($baseRow['profile_buy_uom_code'] ?? '');
        $row['profile_content_uom_code'] = (string)($baseRow['profile_content_uom_code'] ?? '');
        foreach ([
            'in_qty_buy',
            'in_qty_content',
            'out_qty_buy',
            'out_qty_content',
            'adjustment_qty_buy',
            'adjustment_qty_content',
            'discarded_qty_buy',
            'discarded_qty_content',
            'spoil_qty_buy',
            'spoil_qty_content',
            'waste_qty_buy',
            'waste_qty_content',
            'process_loss_qty_buy',
            'process_loss_qty_content',
            'variance_qty_buy',
            'variance_qty_content',
            'adjustment_plus_qty_buy',
            'adjustment_plus_qty_content',
            'waste_total_value',
            'spoilage_total_value',
            'process_loss_total_value',
            'variance_total_value',
            'adjustment_plus_total_value',
            'mutation_count',
        ] as $field) {
            if (!isset($row[$field])) {
                $row[$field] = 0;
            }
        }

        return $row;
    }

    private function build_material_daily_rows_from_monthly_base(
        string $q,
        ?int $divisionId,
        string $dateFrom,
        string $dateTo,
        array $dates,
        int $limit,
        ?string $destinationFilter = null,
        int $offset = 0
    ): array {
        $totalCount = $this->count_material_daily_monthly_base_rows($q, $divisionId, $dateTo, $destinationFilter);
        $baseRows = $this->fetch_material_daily_monthly_base_rows($q, $divisionId, $dateTo, $limit, $destinationFilter, $offset);
        if (empty($baseRows)) {
            return [];
        }

        $movementRows = [];
        if ($this->db->table_exists('inv_stock_movement_log')) {
            $movementRows = $this->fetchInventoryDailyMatrixSourceRowsFromMovement(
                'DIVISION',
                '',
                $divisionId,
                $dateFrom,
                $dateTo,
                $destinationFilter,
                true
            );
            $movementRows = $this->normalizeDivisionProfileKeyRows($movementRows);
        }

        $movementByIdentity = [];
        foreach ($movementRows as $movementRow) {
            $identityKey = $this->buildInventoryDailyMatrixIdentityKey('DIVISION', $movementRow);
            if (!isset($movementByIdentity[$identityKey])) {
                $movementByIdentity[$identityKey] = [];
            }
            $movementDate = (string)($movementRow['movement_date'] ?? '');
            if ($movementDate === '') {
                continue;
            }
            $movementByIdentity[$identityKey][$movementDate] = $movementRow;
        }

        $baseIdentitySet = [];
        $baseGroupKeys = [];
        foreach ($baseRows as $baseRow) {
            $identityKey = $this->buildInventoryDailyMatrixIdentityKey('DIVISION', $baseRow);
            $baseIdentitySet[$identityKey] = true;
            $groupKey = $this->build_material_daily_group_key($baseRow);
            if (!isset($baseGroupKeys[$groupKey])) {
                $baseGroupKeys[$groupKey] = [
                    'division_id' => (int)($baseRow['division_id'] ?? 0),
                    'destination_group' => strtoupper(trim((string)($baseRow['destination_group'] ?? 'REGULER'))),
                    'item_id' => (int)($baseRow['item_id'] ?? 0),
                    'material_id' => (int)($baseRow['material_id'] ?? 0),
                ];
            }
        }
        $movementByIdentity = array_intersect_key($movementByIdentity, $baseIdentitySet);

        $mismatchByGroup = [];
        foreach ($movementRows as $movementRow) {
            $identityKey = $this->buildInventoryDailyMatrixIdentityKey('DIVISION', $movementRow);
            if (isset($baseIdentitySet[$identityKey])) {
                continue;
            }
            $groupKey = $this->build_material_daily_group_key($movementRow);
            if (!isset($baseGroupKeys[$groupKey])) {
                continue;
            }
            if (!isset($mismatchByGroup[$groupKey])) {
                $mismatchByGroup[$groupKey] = [
                    'audit_has_mismatch' => 0,
                    'audit_mismatch_row_count' => 0,
                    'audit_mismatch_qty_content' => 0.0,
                    'audit_mismatch_notes' => [],
                ];
            }
            $mismatchByGroup[$groupKey]['audit_has_mismatch'] = 1;
            $mismatchByGroup[$groupKey]['audit_mismatch_row_count'] += 1;
            $mismatchByGroup[$groupKey]['audit_mismatch_qty_content'] = round(
                (float)$mismatchByGroup[$groupKey]['audit_mismatch_qty_content'] + (float)($movementRow['closing_qty_content'] ?? 0),
                4
            );
            $note = trim((string)($movementRow['profile_name'] ?? ''));
            if ($note === '') {
                $note = 'fallback ' . (string)($movementRow['buy_uom_id'] ?? 0) . '->' . (string)($movementRow['content_uom_id'] ?? 0);
            }
            $mismatchByGroup[$groupKey]['audit_mismatch_notes'][$note] = true;
        }

        $dailyRows = [];
        foreach ($baseRows as $baseRow) {
            $identityKey = $this->buildInventoryDailyMatrixIdentityKey('DIVISION', $baseRow);
            $dayMap = $movementByIdentity[$identityKey] ?? [];
            $groupKey = $this->build_material_daily_group_key($baseRow);
            $mismatchMeta = $mismatchByGroup[$groupKey] ?? [
                'audit_has_mismatch' => 0,
                'audit_mismatch_row_count' => 0,
                'audit_mismatch_qty_content' => 0.0,
                'audit_mismatch_notes' => [],
            ];

            $nextClosingBuy = round((float)($baseRow['qty_buy_balance'] ?? 0), 4);
            $nextClosingContent = round((float)($baseRow['qty_content_balance'] ?? 0), 4);
            $avgCost = round((float)($baseRow['avg_cost_per_content'] ?? 0), 6);
            $totalValue = round((float)($baseRow['total_value'] ?? ($nextClosingContent * $avgCost)), 2);

            for ($i = count($dates) - 1; $i >= 0; $i--) {
                $day = (string)$dates[$i];
                $existingRow = $dayMap[$day] ?? null;
                $row = $this->build_material_daily_base_day_row($baseRow, $day, $existingRow);
                $adjustmentContent = round($this->resolveDailyMatrixAdjustmentQtyContent($row), 4);
                $adjustmentBuy = round($this->resolveDailyMatrixAdjustmentQtyBuy($row), 4);
                $deltaContent = round(
                    (float)($row['in_qty_content'] ?? 0)
                    - (float)($row['out_qty_content'] ?? 0)
                    + $adjustmentContent,
                    4
                );
                $deltaBuy = round(
                    (float)($row['in_qty_buy'] ?? 0)
                    - (float)($row['out_qty_buy'] ?? 0)
                    + $adjustmentBuy,
                    4
                );

                $row['closing_qty_buy'] = $nextClosingBuy;
                $row['closing_qty_content'] = $nextClosingContent;
                $row['opening_qty_buy'] = round($nextClosingBuy - $deltaBuy, 4);
                $row['opening_qty_content'] = round($nextClosingContent - $deltaContent, 4);
                $row['avg_cost_per_content'] = $avgCost;
                $row['total_value'] = $i === count($dates) - 1
                    ? $totalValue
                    : round((float)$row['closing_qty_content'] * $avgCost, 2);
                $row['audit_has_mismatch'] = (int)$mismatchMeta['audit_has_mismatch'];
                $row['audit_mismatch_row_count'] = (int)$mismatchMeta['audit_mismatch_row_count'];
                $row['audit_mismatch_qty_content'] = round((float)$mismatchMeta['audit_mismatch_qty_content'], 4);
                $row['audit_mismatch_notes'] = implode(', ', array_keys((array)$mismatchMeta['audit_mismatch_notes']));

                $dailyRows[] = $row;
                $nextClosingBuy = round((float)$row['opening_qty_buy'], 4);
                $nextClosingContent = round((float)$row['opening_qty_content'], 4);
            }
        }

        return ['rows' => $this->pivotDailyRows($dailyRows, $limit), 'total_count' => $totalCount];
    }

    private function fetch_material_daily_monthly_base_rows(
        string $q,
        ?int $divisionId,
        string $dateTo,
        int $limit,
        ?string $destinationFilter = null,
        int $offset = 0
    ): array {
        if (!$this->db->table_exists('inv_division_monthly_stock')) {
            return [];
        }

        $targetMonth = date('Y-m-01', strtotime($dateTo ?: date('Y-m-d')));
        $destinationFilter = $this->normalizeDestinationFilter($destinationFilter);
        $limit = max(1, min(1000, $limit));
        $divisionCodeColumn = $this->db->field_exists('division_code', 'mst_operational_division')
            ? 'division_code'
            : ($this->db->field_exists('code', 'mst_operational_division') ? 'code' : null);
        $divisionNameColumn = $this->db->field_exists('division_name', 'mst_operational_division')
            ? 'division_name'
            : ($this->db->field_exists('name', 'mst_operational_division') ? 'name' : null);
        $divisionCodeSelect = $divisionCodeColumn !== null ? ('d.' . $divisionCodeColumn . ' AS division_code') : 'CAST(s.division_id AS CHAR) AS division_code';
        $divisionNameSelect = $divisionNameColumn !== null ? ('d.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';
        $latestMonthSubquery = $this->db
            ->select('division_id, destination_type, identity_key, MAX(month_key) AS month_key', false)
            ->from('inv_division_monthly_stock')
            ->where('month_key <=', $targetMonth)
            ->group_by(['division_id', 'destination_type', 'identity_key'])
            ->get_compiled_select();
        $destinationGroupExpr = "CASE
                WHEN COALESCE(s.destination_type, 'OTHER') IN ('BAR_EVENT','KITCHEN_EVENT') THEN 'EVENT'
                ELSE 'REGULER'
            END";
        $destinationNameExpr = "CASE COALESCE(s.destination_type, 'OTHER')
                WHEN 'BAR' THEN 'Bar Reguler'
                WHEN 'KITCHEN' THEN 'Kitchen Reguler'
                WHEN 'BAR_EVENT' THEN 'Bar Event'
                WHEN 'KITCHEN_EVENT' THEN 'Kitchen Event'
                WHEN 'OFFICE' THEN 'Office Reguler'
                WHEN 'GUDANG' THEN 'Gudang'
                ELSE 'Reguler'
            END";

        $this->db
            ->select('s.id, "ITEM" AS stock_domain, s.division_id, ' . $divisionCodeSelect . ', ' . $divisionNameSelect . ', s.item_id, COALESCE(s.material_id, i.material_id) AS material_id, s.buy_uom_id, s.content_uom_id', false)
            ->select('s.destination_type AS destination_type', false)
            ->select($destinationGroupExpr . ' AS destination_group', false)
            ->select($destinationNameExpr . ' AS destination_name', false)
            ->select('i.item_code, i.item_name, m.material_code, m.material_name')
            ->select('s.profile_key, s.profile_name, s.profile_brand, s.profile_description, s.profile_expired_date')
            ->select('s.profile_content_per_buy, s.profile_buy_uom_code, s.profile_content_uom_code')
            ->select('s.closing_qty_buy AS qty_buy_balance, s.closing_qty_content AS qty_content_balance, s.avg_cost_per_content, s.total_value')
            ->select('COALESCE(s.updated_at, s.last_movement_at, CONCAT(s.month_key, " 00:00:00")) AS updated_at', false)
            ->from('inv_division_monthly_stock s')
            ->join('(' . $latestMonthSubquery . ') lm', 'lm.division_id = s.division_id AND lm.destination_type = s.destination_type AND lm.identity_key = s.identity_key AND lm.month_key = s.month_key', 'inner', false)
            ->join('mst_operational_division d', 'd.id = s.division_id', 'left')
            ->join('mst_item i', 'i.id = s.item_id', 'left')
            ->join('mst_material m', 'm.id = COALESCE(s.material_id, i.material_id)', 'left')
            ->where('COALESCE(s.material_id, i.material_id) IS NOT NULL', null, false);

        if ($divisionId !== null && $divisionId > 0) {
            $this->db->where('s.division_id', $divisionId);
        }
        if ($destinationFilter !== null && $destinationFilter !== 'ALL') {
            if ($destinationFilter === 'REGULER') {
                $this->db->where_not_in('s.destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
            } elseif ($destinationFilter === 'EVENT') {
                $this->db->where_in('s.destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
            } else {
                $this->db->where('s.destination_type', $destinationFilter);
            }
        }
        if ($q !== '') {
            $this->db->group_start()
                ->like('i.item_code', $q)
                ->or_like('i.item_name', $q)
                ->or_like('m.material_code', $q)
                ->or_like('m.material_name', $q)
                ->or_like('s.profile_name', $q)
                ->or_like('s.profile_brand', $q)
                ->or_like('s.profile_description', $q)
                ->or_like('s.profile_key', $q)
                ->group_end();
        }

        $offset = max(0, (int)$offset);
        $query = $this->db
            ->order_by($divisionNameColumn !== null ? ('d.' . $divisionNameColumn) : 's.division_id', 'ASC')
            ->order_by($destinationGroupExpr, 'ASC', false)
            ->order_by('m.material_name', 'ASC')
            ->order_by('s.profile_name', 'ASC')
            ->limit($limit, $offset);
        return $query->get()->result_array();
    }

    private function count_material_daily_monthly_base_rows(
        string $q,
        ?int $divisionId,
        string $dateTo,
        ?string $destinationFilter = null
    ): int {
        if (!$this->db->table_exists('inv_division_monthly_stock')) {
            return 0;
        }
        $targetMonth = date('Y-m-01', strtotime($dateTo ?: date('Y-m-d')));
        $destinationFilter = $this->normalizeDestinationFilter($destinationFilter);
        $latestMonthSubquery = $this->db
            ->select('division_id, destination_type, identity_key, MAX(month_key) AS month_key', false)
            ->from('inv_division_monthly_stock')
            ->where('month_key <=', $targetMonth)
            ->group_by(['division_id', 'destination_type', 'identity_key'])
            ->get_compiled_select();

        $this->db
            ->select('COUNT(*) AS cnt', false)
            ->from('inv_division_monthly_stock s')
            ->join('(' . $latestMonthSubquery . ') lm', 'lm.division_id = s.division_id AND lm.destination_type = s.destination_type AND lm.identity_key = s.identity_key AND lm.month_key = s.month_key', 'inner', false)
            ->join('mst_operational_division d', 'd.id = s.division_id', 'left')
            ->join('mst_item i', 'i.id = s.item_id', 'left')
            ->join('mst_material m', 'm.id = COALESCE(s.material_id, i.material_id)', 'left')
            ->where('COALESCE(s.material_id, i.material_id) IS NOT NULL', null, false);

        if ($divisionId !== null && $divisionId > 0) {
            $this->db->where('s.division_id', $divisionId);
        }
        if ($destinationFilter !== null && $destinationFilter !== 'ALL') {
            if ($destinationFilter === 'REGULER') {
                $this->db->where_not_in('s.destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
            } elseif ($destinationFilter === 'EVENT') {
                $this->db->where_in('s.destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
            } else {
                $this->db->where('s.destination_type', $destinationFilter);
            }
        }
        if ($q !== '') {
            $this->db->group_start()
                ->like('i.item_code', $q)
                ->or_like('i.item_name', $q)
                ->or_like('m.material_code', $q)
                ->or_like('m.material_name', $q)
                ->or_like('s.profile_name', $q)
                ->or_like('s.profile_brand', $q)
                ->or_like('s.profile_description', $q)
                ->or_like('s.profile_key', $q)
                ->group_end();
        }
        $row = $this->db->get()->row_array();
        return (int)($row['cnt'] ?? 0);
    }

    private function build_material_daily_base_day_row(array $baseRow, string $day, ?array $existingRow = null): array
    {
        $row = $existingRow ?? [];
        $row['movement_date'] = $day;
        $row['division_id'] = isset($baseRow['division_id']) ? (int)$baseRow['division_id'] : null;
        $row['division_code'] = (string)($baseRow['division_code'] ?? '');
        $row['division_name'] = (string)($baseRow['division_name'] ?? '');
        $row['destination_type'] = (string)($baseRow['destination_type'] ?? 'OTHER');
        $row['destination_group'] = (string)($baseRow['destination_group'] ?? 'REGULER');
        $row['destination_name'] = (string)($baseRow['destination_name'] ?? 'Reguler');
        $row['stock_domain'] = 'ITEM';
        $row['item_id'] = (int)($baseRow['item_id'] ?? 0);
        $row['material_id'] = (int)($baseRow['material_id'] ?? 0);
        $row['buy_uom_id'] = (int)($baseRow['buy_uom_id'] ?? 0);
        $row['content_uom_id'] = (int)($baseRow['content_uom_id'] ?? 0);
        $row['item_code'] = (string)($baseRow['item_code'] ?? '');
        $row['item_name'] = (string)($baseRow['item_name'] ?? '');
        $row['material_code'] = (string)($baseRow['material_code'] ?? '');
        $row['material_name'] = (string)($baseRow['material_name'] ?? '');
        $row['profile_key'] = (string)($baseRow['profile_key'] ?? '');
        $row['profile_name'] = (string)($baseRow['profile_name'] ?? '');
        $row['profile_brand'] = (string)($baseRow['profile_brand'] ?? '');
        $row['profile_description'] = (string)($baseRow['profile_description'] ?? '');
        $row['profile_expired_date'] = (string)($baseRow['profile_expired_date'] ?? '');
        $row['profile_content_per_buy'] = round((float)($baseRow['profile_content_per_buy'] ?? 0), 6);
        $row['profile_buy_uom_code'] = (string)($baseRow['profile_buy_uom_code'] ?? '');
        $row['profile_content_uom_code'] = (string)($baseRow['profile_content_uom_code'] ?? '');
        foreach ([
            'in_qty_buy',
            'in_qty_content',
            'out_qty_buy',
            'out_qty_content',
            'adjustment_qty_buy',
            'adjustment_qty_content',
            'discarded_qty_buy',
            'discarded_qty_content',
            'spoil_qty_buy',
            'spoil_qty_content',
            'waste_qty_buy',
            'waste_qty_content',
            'process_loss_qty_buy',
            'process_loss_qty_content',
            'variance_qty_buy',
            'variance_qty_content',
            'adjustment_plus_qty_buy',
            'adjustment_plus_qty_content',
            'waste_total_value',
            'spoilage_total_value',
            'process_loss_total_value',
            'variance_total_value',
            'adjustment_plus_total_value',
            'mutation_count',
        ] as $field) {
            if (!isset($row[$field])) {
                $row[$field] = 0;
            }
        }

        return $row;
    }

    private function build_material_daily_group_key(array $row): string
    {
        $destinationGroup = strtoupper(trim((string)($row['destination_group'] ?? 'REGULER')));
        return implode('|', [
            (int)($row['division_id'] ?? 0),
            $destinationGroup,
            (int)($row['material_id'] ?? 0),
            (int)($row['item_id'] ?? 0),
        ]);
    }

    private function fetchMaterialDailySourceRows(string $q, ?int $divisionId, string $dateFrom, string $dateTo, ?string $destinationFilter = null): array
    {
        if ($this->db->table_exists('inv_stock_movement_log')) {
            return $this->fetchInventoryDailyMatrixSourceRowsFromMovement('DIVISION', $q, $divisionId, $dateFrom, $dateTo, $destinationFilter, true);
        }

        return [];
    }

    private function fetchInventoryDailyMatrixSourceRowsFromMovement(string $stockScope, string $q, ?int $divisionId, string $dateFrom, string $dateTo, ?string $destinationFilter = null, bool $materialOnly = false): array
    {
        if (!$this->db->table_exists('inv_stock_movement_log')) {
            return [];
        }

        $destinationFilter = $this->normalizeDestinationFilter($destinationFilter);
        $hasMovementStockDomain = $this->db->field_exists('stock_domain', 'inv_stock_movement_log');
        $hasMovementDestination = $stockScope === 'DIVISION' && $this->db->field_exists('destination_type', 'inv_stock_movement_log');
        $hasMovementAdjustmentCategory = $this->db->field_exists('adjustment_category', 'inv_stock_movement_log');
        $hasMovementProfileExpiredDate = $this->db->field_exists('profile_expired_date', 'inv_stock_movement_log');
        $divisionCodeColumn = $this->db->field_exists('division_code', 'mst_operational_division')
            ? 'division_code'
            : ($this->db->field_exists('code', 'mst_operational_division') ? 'code' : null);
        $divisionNameColumn = $this->db->field_exists('division_name', 'mst_operational_division')
            ? 'division_name'
            : ($this->db->field_exists('name', 'mst_operational_division') ? 'name' : null);

        $this->db
            ->select('l.id, l.movement_date, l.movement_type, l.ref_table, l.ref_id, l.item_id, COALESCE(l.material_id, i.material_id) AS material_id, l.buy_uom_id, l.content_uom_id', false)
            ->select('l.profile_key, l.profile_name, l.profile_brand, l.profile_description, l.profile_content_per_buy, l.profile_buy_uom_code, l.profile_content_uom_code')
            ->select('l.qty_buy_delta, l.qty_content_delta, l.unit_cost, l.created_at')
            ->select('i.item_code, i.item_name, m.material_code, m.material_name')
            ->from('inv_stock_movement_log l')
            ->join('mst_item i', 'i.id = l.item_id', 'left')
            ->join('mst_material m', 'm.id = COALESCE(l.material_id, i.material_id)', 'left')
            ->where('l.movement_scope', $stockScope)
            ->where('l.movement_date <=', $dateTo);

        if ($hasMovementStockDomain) {
            $this->db->select('"ITEM" AS stock_domain', false);
        } else {
            $this->db->select('"ITEM" AS stock_domain', false);
        }

        if ($hasMovementAdjustmentCategory) {
            $this->db->select('l.adjustment_category');
        } else {
            $this->db->select('NULL AS adjustment_category', false);
        }
        if ($hasMovementProfileExpiredDate) {
            $this->db->select('l.profile_expired_date');
        } else {
            $this->db->select('NULL AS profile_expired_date', false);
        }

        if ($stockScope === 'DIVISION') {
            $this->db->select('l.division_id');
            if ($hasMovementDestination) {
                $this->db->select('l.destination_type');
            } else {
                $this->db->select('NULL AS destination_type', false);
            }
            $divisionCodeSelect = $divisionCodeColumn !== null ? ('dv.' . $divisionCodeColumn . ' AS division_code') : 'CAST(l.division_id AS CHAR) AS division_code';
            $divisionNameSelect = $divisionNameColumn !== null ? ('dv.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';
            $this->db->select($divisionCodeSelect, false)
                ->select($divisionNameSelect, false)
                ->join('mst_operational_division dv', 'dv.id = l.division_id', 'left');
            if ($divisionId !== null && $divisionId > 0) {
                $this->db->where('l.division_id', $divisionId);
            }
            if ($destinationFilter !== null && $destinationFilter !== 'ALL' && $hasMovementDestination) {
                if ($destinationFilter === 'REGULER') {
                    $this->db->where_not_in('l.destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
                } elseif ($destinationFilter === 'EVENT') {
                    $this->db->where_in('l.destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
                } else {
                    $this->db->where('l.destination_type', $destinationFilter);
                }
            }
        } else {
            $this->db->select('NULL AS division_id', false)
                ->select('NULL AS division_code', false)
                ->select('NULL AS division_name', false)
                ->select('NULL AS destination_type', false);
        }

        if ($materialOnly) {
            $this->db->where('COALESCE(l.material_id, i.material_id) IS NOT NULL', null, false);
        }

        $movementRows = $this->db
            ->order_by('l.movement_date', 'ASC')
            ->order_by('l.id', 'ASC')
            ->get()
            ->result_array();

        // Identifikasi void pair yang keduanya ada di window yang sama.
        // Keduanya di-skip dari display — net effect = 0, seolah tidak pernah terjadi.
        // Void lintas periode (originalnya di periode lain) TIDAK di-skip.
        $voidPairKeys   = [];
        $originPairKeys = [];
        foreach ($movementRows as $_r) {
            $_mt = strtoupper(trim((string)($_r['movement_type'] ?? '')));
            $_rt = (string)($_r['ref_table'] ?? '');
            $_ri = (int)($_r['ref_id'] ?? 0);
            if ($_rt === '' || $_ri <= 0) { continue; }
            $_pk = $_rt . '|' . $_ri;
            if ($_mt === 'VOID_REVERSE') {
                $voidPairKeys[$_pk] = true;
            } else {
                $originPairKeys[$_pk] = true;
            }
        }
        $skipVoidPairKeys = array_intersect_key($voidPairKeys, $originPairKeys);

        $states = [];
        $daily = [];
        foreach ($movementRows as $movementRow) {
            $_rt = (string)($movementRow['ref_table'] ?? '');
            $_ri = (int)($movementRow['ref_id'] ?? 0);
            if ($_rt !== '' && $_ri > 0 && isset($skipVoidPairKeys[$_rt . '|' . $_ri])) {
                continue; // skip: pasangan void dalam window yang sama
            }
            $movementDay = (string)($movementRow['movement_date'] ?? '');
            if ($movementDay === '') {
                continue;
            }

            $identityKey = $this->buildInventoryDailyMatrixIdentityKey($stockScope, $movementRow);
            if (!isset($states[$identityKey])) {
                $states[$identityKey] = [
                    'qty_buy' => 0.0,
                    'qty_content' => 0.0,
                    'avg_cost_per_content' => 0.0,
                ];
            }

            $state = $states[$identityKey];
            if (!isset($daily[$identityKey][$movementDay])) {
                $destinationType = strtoupper(trim((string)($movementRow['destination_type'] ?? 'OTHER')));
                if ($destinationType === '' || $destinationType === 'ALL') {
                    $destinationType = 'OTHER';
                }
                $destinationGroup = in_array($destinationType, ['BAR_EVENT', 'KITCHEN_EVENT'], true) ? 'EVENT' : 'REGULER';
                $destinationNameMap = [
                    'BAR' => 'Bar Reguler',
                    'KITCHEN' => 'Kitchen Reguler',
                    'BAR_EVENT' => 'Bar Event',
                    'KITCHEN_EVENT' => 'Kitchen Event',
                    'OFFICE' => 'Office Reguler',
                    'GUDANG' => 'Gudang',
                    'OTHER' => 'Reguler',
                ];
                $daily[$identityKey][$movementDay] = [
                    'movement_date' => $movementDay,
                    'division_id' => $stockScope === 'DIVISION' ? $this->nullableInt($movementRow['division_id'] ?? null) : null,
                    'division_code' => (string)($movementRow['division_code'] ?? ''),
                    'division_name' => (string)($movementRow['division_name'] ?? ''),
                    'destination_type' => $stockScope === 'DIVISION' ? $destinationType : 'OTHER',
                    'destination_group' => $stockScope === 'DIVISION' ? $destinationGroup : 'REGULER',
                    'destination_name' => $stockScope === 'DIVISION' ? (string)($destinationNameMap[$destinationType] ?? 'Reguler') : 'Gudang',
                    'stock_domain' => strtoupper((string)($movementRow['stock_domain'] ?? 'ITEM')),
                    'item_id' => (int)($movementRow['item_id'] ?? 0),
                    'material_id' => (int)($movementRow['material_id'] ?? 0),
                    'buy_uom_id' => (int)($movementRow['buy_uom_id'] ?? 0),
                    'content_uom_id' => (int)($movementRow['content_uom_id'] ?? 0),
                    'item_code' => (string)($movementRow['item_code'] ?? ''),
                    'item_name' => (string)($movementRow['item_name'] ?? ''),
                    'material_code' => (string)($movementRow['material_code'] ?? ''),
                    'material_name' => (string)($movementRow['material_name'] ?? ''),
                    'profile_key' => (string)($movementRow['profile_key'] ?? ''),
                    'profile_name' => (string)($movementRow['profile_name'] ?? ''),
                    'profile_brand' => (string)($movementRow['profile_brand'] ?? ''),
                    'profile_description' => (string)($movementRow['profile_description'] ?? ''),
                    'profile_expired_date' => (string)($movementRow['profile_expired_date'] ?? ''),
                    'profile_content_per_buy' => round((float)($movementRow['profile_content_per_buy'] ?? 0), 6),
                    'profile_buy_uom_code' => (string)($movementRow['profile_buy_uom_code'] ?? ''),
                    'profile_content_uom_code' => (string)($movementRow['profile_content_uom_code'] ?? ''),
                    'opening_qty_buy' => round((float)$state['qty_buy'], 4),
                    'opening_qty_content' => round((float)$state['qty_content'], 4),
                    'in_qty_buy' => 0.0,
                    'in_qty_content' => 0.0,
                    'out_qty_buy' => 0.0,
                    'out_qty_content' => 0.0,
                    'adjustment_qty_buy' => 0.0,
                    'adjustment_qty_content' => 0.0,
                    'discarded_qty_buy' => 0.0,
                    'discarded_qty_content' => 0.0,
                    'spoil_qty_buy' => 0.0,
                    'spoil_qty_content' => 0.0,
                    'waste_qty_buy' => 0.0,
                    'waste_qty_content' => 0.0,
                    'process_loss_qty_buy' => 0.0,
                    'process_loss_qty_content' => 0.0,
                    'variance_qty_buy' => 0.0,
                    'variance_qty_content' => 0.0,
                    'adjustment_plus_qty_buy' => 0.0,
                    'adjustment_plus_qty_content' => 0.0,
                    'avg_cost_per_content' => round((float)$state['avg_cost_per_content'], 6),
                    'closing_qty_buy' => round((float)$state['qty_buy'], 4),
                    'closing_qty_content' => round((float)$state['qty_content'], 4),
                    'waste_total_value' => 0.0,
                    'spoilage_total_value' => 0.0,
                    'process_loss_total_value' => 0.0,
                    'variance_total_value' => 0.0,
                    'adjustment_plus_total_value' => 0.0,
                    'total_value' => round((float)$state['qty_content'] * (float)$state['avg_cost_per_content'], 2),
                    'mutation_count' => 0,
                ];
            }

            $entry =& $daily[$identityKey][$movementDay];
            $movementType = strtoupper(trim((string)($movementRow['movement_type'] ?? 'ADJUSTMENT')));
            $qtyBuyDelta = round((float)($movementRow['qty_buy_delta'] ?? 0), 4);
            $qtyContentDelta = round((float)($movementRow['qty_content_delta'] ?? 0), 4);
            $adjustmentCategory = $this->normalizeInventoryAdjustmentCategory((string)($movementRow['adjustment_category'] ?? ''));
            if ($adjustmentCategory === null) {
                $adjustmentCategory = $this->resolveInventoryAdjustmentCategoryFromMovement($movementType, $qtyBuyDelta, $qtyContentDelta);
            }
            $isOpeningSnapshotMovement = in_array((string)($movementRow['ref_table'] ?? ''), ['inv_warehouse_stock_opening_snapshot', 'inv_division_stock_opening_snapshot'], true);
            if ($isOpeningSnapshotMovement) {
                $entry['opening_qty_buy'] = round((float)$entry['opening_qty_buy'] + max(0, $qtyBuyDelta), 4);
                $entry['opening_qty_content'] = round((float)$entry['opening_qty_content'] + max(0, $qtyContentDelta), 4);
            } else {
                $deltaPack = $this->buildInventoryDailyDeltaPack($movementType, $qtyBuyDelta, $qtyContentDelta, $adjustmentCategory, (float)$state['avg_cost_per_content']);
                $entry['in_qty_buy'] = round((float)$entry['in_qty_buy'] + (float)$deltaPack['delta']['in_qty_buy'], 4);
                $entry['in_qty_content'] = round((float)$entry['in_qty_content'] + (float)$deltaPack['delta']['in_qty_content'], 4);
                $entry['out_qty_buy'] = round((float)$entry['out_qty_buy'] + (float)$deltaPack['delta']['out_qty_buy'], 4);
                $entry['out_qty_content'] = round((float)$entry['out_qty_content'] + (float)$deltaPack['delta']['out_qty_content'], 4);
                $entry['adjustment_qty_buy'] = round((float)$entry['adjustment_qty_buy'] + (float)$deltaPack['delta']['adjustment_qty_buy'], 4);
                $entry['adjustment_qty_content'] = round((float)$entry['adjustment_qty_content'] + (float)$deltaPack['delta']['adjustment_qty_content'], 4);
                $entry['discarded_qty_buy'] = round((float)$entry['discarded_qty_buy'] + (float)$deltaPack['delta']['discarded_qty_buy'], 4);
                $entry['discarded_qty_content'] = round((float)$entry['discarded_qty_content'] + (float)$deltaPack['delta']['discarded_qty_content'], 4);
                $entry['spoil_qty_buy'] = round((float)$entry['spoil_qty_buy'] + (float)$deltaPack['delta']['spoil_qty_buy'], 4);
                $entry['spoil_qty_content'] = round((float)$entry['spoil_qty_content'] + (float)$deltaPack['delta']['spoil_qty_content'], 4);
                $entry['waste_qty_buy'] = round((float)$entry['waste_qty_buy'] + (float)$deltaPack['delta']['waste_qty_buy'], 4);
                $entry['waste_qty_content'] = round((float)$entry['waste_qty_content'] + (float)$deltaPack['delta']['waste_qty_content'], 4);
                $entry['process_loss_qty_buy'] = round((float)$entry['process_loss_qty_buy'] + (float)$deltaPack['delta']['process_loss_qty_buy'], 4);
                $entry['process_loss_qty_content'] = round((float)$entry['process_loss_qty_content'] + (float)$deltaPack['delta']['process_loss_qty_content'], 4);
                $entry['variance_qty_buy'] = round((float)$entry['variance_qty_buy'] + (float)$deltaPack['delta']['variance_qty_buy'], 4);
                $entry['variance_qty_content'] = round((float)$entry['variance_qty_content'] + (float)$deltaPack['delta']['variance_qty_content'], 4);
                $entry['adjustment_plus_qty_buy'] = round((float)$entry['adjustment_plus_qty_buy'] + (float)$deltaPack['delta']['adjustment_plus_qty_buy'], 4);
                $entry['adjustment_plus_qty_content'] = round((float)$entry['adjustment_plus_qty_content'] + (float)$deltaPack['delta']['adjustment_plus_qty_content'], 4);
                $entry['waste_total_value'] = round((float)$entry['waste_total_value'] + (float)$deltaPack['value']['waste_total_value'], 2);
                $entry['spoilage_total_value'] = round((float)$entry['spoilage_total_value'] + (float)$deltaPack['value']['spoilage_total_value'], 2);
                $entry['process_loss_total_value'] = round((float)$entry['process_loss_total_value'] + (float)$deltaPack['value']['process_loss_total_value'], 2);
                $entry['variance_total_value'] = round((float)$entry['variance_total_value'] + (float)$deltaPack['value']['variance_total_value'], 2);
                $entry['adjustment_plus_total_value'] = round((float)$entry['adjustment_plus_total_value'] + (float)$deltaPack['value']['adjustment_plus_total_value'], 2);
            }

            $state = $this->applyInventoryHistoryMovement(
                (float)$state['qty_buy'],
                (float)$state['qty_content'],
                (float)$state['avg_cost_per_content'],
                $qtyBuyDelta,
                $qtyContentDelta,
                (float)($movementRow['unit_cost'] ?? 0)
            );
            $states[$identityKey] = $state;

            $entry['closing_qty_buy'] = round((float)$state['qty_buy'], 4);
            $entry['closing_qty_content'] = round((float)$state['qty_content'], 4);
            $entry['avg_cost_per_content'] = round((float)$state['avg_cost_per_content'], 6);
            $entry['total_value'] = round((float)$state['qty_content'] * (float)$state['avg_cost_per_content'], 2);
            $entry['mutation_count'] = (int)$entry['mutation_count'] + 1;
            foreach (['profile_name', 'profile_brand', 'profile_description', 'profile_buy_uom_code', 'profile_content_uom_code'] as $field) {
                if (trim((string)($movementRow[$field] ?? '')) !== '') {
                    $entry[$field] = (string)$movementRow[$field];
                }
            }
            if (!empty($movementRow['profile_expired_date'])) {
                $entry['profile_expired_date'] = (string)$movementRow['profile_expired_date'];
            }
            if ((float)($movementRow['profile_content_per_buy'] ?? 0) > 0) {
                $entry['profile_content_per_buy'] = round((float)$movementRow['profile_content_per_buy'], 6);
            }
            unset($entry);
        }

        $this->applyInventoryDailyMonthlyClosingGuard(
            $stockScope,
            $daily,
            $dateFrom,
            $dateTo,
            $divisionId,
            $destinationFilter,
            $materialOnly
        );

        $rows = [];
        foreach ($daily as $dayMap) {
            foreach ($dayMap as $day => $row) {
                if ($day < $dateFrom || $day > $dateTo) {
                    continue;
                }
                if ($materialOnly && (int)($row['material_id'] ?? 0) <= 0) {
                    continue;
                }
                $rows[] = $row;
            }
        }

        if ($q !== '') {
            $needle = strtoupper(trim($q));
            $rows = array_values(array_filter($rows, static function (array $row) use ($needle): bool {
                $haystacks = [
                    (string)($row['division_code'] ?? ''),
                    (string)($row['division_name'] ?? ''),
                    (string)($row['destination_group'] ?? ''),
                    (string)($row['destination_name'] ?? ''),
                    (string)($row['item_code'] ?? ''),
                    (string)($row['item_name'] ?? ''),
                    (string)($row['material_code'] ?? ''),
                    (string)($row['material_name'] ?? ''),
                    (string)($row['profile_name'] ?? ''),
                    (string)($row['profile_brand'] ?? ''),
                    (string)($row['profile_description'] ?? ''),
                ];
                foreach ($haystacks as $haystack) {
                    if ($haystack !== '' && stripos($haystack, $needle) !== false) {
                        return true;
                    }
                }
                return false;
            }));
        }

        usort($rows, static function (array $left, array $right): int {
            $cmp = strcmp((string)($left['division_name'] ?? ''), (string)($right['division_name'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = strcmp((string)($left['destination_group'] ?? ''), (string)($right['destination_group'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = strcmp((string)($left['material_name'] ?? $left['item_name'] ?? ''), (string)($right['material_name'] ?? $right['item_name'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = strcmp((string)($left['profile_name'] ?? ''), (string)($right['profile_name'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp((string)($left['movement_date'] ?? ''), (string)($right['movement_date'] ?? ''));
        });

        return $rows;
    }

    private function applyInventoryDailyMonthlyClosingGuard(string $stockScope, array &$daily, string $dateFrom, string $dateTo, ?int $divisionId = null, ?string $destinationFilter = null, bool $materialOnly = false): void
    {
        if (empty($daily)) {
            return;
        }

        $monthlyMap = $this->fetchInventoryDailyMonthlyClosingGuardMap(
            $stockScope,
            $dateFrom,
            $dateTo,
            $divisionId,
            $destinationFilter,
            $materialOnly
        );
        if (empty($monthlyMap)) {
            return;
        }

        foreach ($daily as $identityKey => &$dayMap) {
            if (empty($dayMap)) {
                continue;
            }

            ksort($dayMap);
            $days = array_keys($dayMap);
            $latestDay = (string)end($days);
            if ($latestDay === '') {
                continue;
            }

            $latestRow = $dayMap[$latestDay];
            $monthKey = date('Y-m-01', strtotime($latestDay));
            $guardKey = $monthKey . '|' . $this->buildInventoryDailyMatrixIdentityKey($stockScope, $latestRow);
            if (!isset($monthlyMap[$guardKey])) {
                continue;
            }

            $guard = $monthlyMap[$guardKey];
            $nextClosingBuy = round((float)($guard['closing_qty_buy'] ?? 0), 4);
            $nextClosingContent = round((float)($guard['closing_qty_content'] ?? 0), 4);
            $latestAvgCost = round((float)($guard['avg_cost_per_content'] ?? 0), 6);
            $latestTotalValue = round((float)($guard['total_value'] ?? 0), 2);

            for ($index = count($days) - 1; $index >= 0; $index--) {
                $day = (string)$days[$index];
                $row = $dayMap[$day];
                $adjustmentContent = round($this->resolveDailyMatrixAdjustmentQtyContent($row), 4);
                $adjustmentBuy = round($this->resolveDailyMatrixAdjustmentQtyBuy($row), 4);
                $deltaContent = round(
                    (float)($row['in_qty_content'] ?? 0)
                    - (float)($row['out_qty_content'] ?? 0)
                    + $adjustmentContent,
                    4
                );
                $deltaBuy = round(
                    (float)($row['in_qty_buy'] ?? 0)
                    - (float)($row['out_qty_buy'] ?? 0)
                    + $adjustmentBuy,
                    4
                );

                $row['closing_qty_buy'] = $nextClosingBuy;
                $row['closing_qty_content'] = $nextClosingContent;
                $row['opening_qty_buy'] = round($nextClosingBuy - $deltaBuy, 4);
                $row['opening_qty_content'] = round($nextClosingContent - $deltaContent, 4);

                if ($index === count($days) - 1) {
                    $row['avg_cost_per_content'] = $latestAvgCost;
                    $row['total_value'] = $latestTotalValue;
                } else {
                    $currentAvg = round((float)($row['avg_cost_per_content'] ?? 0), 6);
                    $row['total_value'] = round((float)$row['closing_qty_content'] * $currentAvg, 2);
                }

                $dayMap[$day] = $row;
                $nextClosingBuy = round((float)$row['opening_qty_buy'], 4);
                $nextClosingContent = round((float)$row['opening_qty_content'], 4);
            }
        }
        unset($dayMap);
    }

    private function fetchInventoryDailyMonthlyClosingGuardMap(string $stockScope, string $dateFrom, string $dateTo, ?int $divisionId = null, ?string $destinationFilter = null, bool $materialOnly = false): array
    {
        $monthFrom = date('Y-m-01', strtotime($dateFrom));
        $monthTo = date('Y-m-01', strtotime($dateTo));
        $destinationFilter = $this->normalizeDestinationFilter($destinationFilter);
        $rows = [];

        if ($stockScope === 'DIVISION' && $this->db->table_exists('inv_division_monthly_stock')) {
            $query = $this->db
                ->select('month_key, division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id')
                ->select('profile_key, profile_name, profile_brand, profile_description, profile_expired_date, profile_content_per_buy')
                ->select('closing_qty_buy, closing_qty_content, avg_cost_per_content, total_value')
                ->from('inv_division_monthly_stock')
                ->where('month_key >=', $monthFrom)
                ->where('month_key <=', $monthTo);

            if ($divisionId !== null && $divisionId > 0) {
                $query->where('division_id', $divisionId);
            }
            if ($destinationFilter !== null && $destinationFilter !== 'ALL') {
                if ($destinationFilter === 'REGULER') {
                    $query->where_not_in('destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
                } elseif ($destinationFilter === 'EVENT') {
                    $query->where_in('destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
                } else {
                    $query->where('destination_type', $destinationFilter);
                }
            }
            if ($materialOnly) {
                $query->where('material_id IS NOT NULL', null, false);
            }

            $rows = $query->get()->result_array();
        } elseif ($stockScope === 'WAREHOUSE' && $this->db->table_exists('inv_warehouse_monthly_stock')) {
            $query = $this->db
                ->select('month_key, NULL AS division_id, NULL AS destination_type, item_id, material_id, buy_uom_id, content_uom_id', false)
                ->select('profile_key, profile_name, profile_brand, profile_description, profile_expired_date, profile_content_per_buy')
                ->select('closing_qty_buy, closing_qty_content, avg_cost_per_content, total_value')
                ->from('inv_warehouse_monthly_stock')
                ->where('month_key >=', $monthFrom)
                ->where('month_key <=', $monthTo);

            if ($materialOnly) {
                $query->where('material_id IS NOT NULL', null, false);
            }

            $rows = $query->get()->result_array();
        }

        if (empty($rows)) {
            return [];
        }

        $rows = $stockScope === 'DIVISION' ? $this->normalizeDivisionProfileKeyRows($rows) : $rows;
        $map = [];
        foreach ($rows as $row) {
            $monthKey = (string)($row['month_key'] ?? '');
            if ($monthKey === '') {
                continue;
            }
            $identityKey = $this->buildInventoryDailyMatrixIdentityKey($stockScope, $row);
            $map[$monthKey . '|' . $identityKey] = [
                'closing_qty_buy' => round((float)($row['closing_qty_buy'] ?? 0), 4),
                'closing_qty_content' => round((float)($row['closing_qty_content'] ?? 0), 4),
                'avg_cost_per_content' => round((float)($row['avg_cost_per_content'] ?? 0), 6),
                'total_value' => round((float)($row['total_value'] ?? 0), 2),
            ];
        }

        return $map;
    }

    private function buildInventoryDailyMatrixIdentityKey(string $stockScope, array $row): string
    {
        $profileKey = strtoupper(trim((string)($row['profile_key'] ?? '')));
        if ($profileKey !== '') {
            return implode('|', [
                $stockScope,
                $stockScope === 'DIVISION' ? (string)($row['division_id'] ?? '') : '',
                $stockScope === 'DIVISION' ? strtoupper((string)($row['destination_type'] ?? 'OTHER')) : '',
                (int)($row['item_id'] ?? 0),
                (int)($row['material_id'] ?? 0),
                (int)($row['buy_uom_id'] ?? 0),
                (int)($row['content_uom_id'] ?? 0),
                $profileKey,
            ]);
        }

        return implode('|', [
            $stockScope,
            $stockScope === 'DIVISION' ? (string)($row['division_id'] ?? '') : '',
            $stockScope === 'DIVISION' ? strtoupper((string)($row['destination_type'] ?? 'OTHER')) : '',
            (int)($row['item_id'] ?? 0),
            (int)($row['material_id'] ?? 0),
            (int)($row['buy_uom_id'] ?? 0),
            (int)($row['content_uom_id'] ?? 0),
            (string)($row['profile_key'] ?? ''),
            strtoupper(trim((string)($row['profile_name'] ?? ''))),
            strtoupper(trim((string)($row['profile_brand'] ?? ''))),
            strtoupper(trim((string)($row['profile_description'] ?? ''))),
        ]);
    }

    public function list_division_material_stock_compare(string $asOfDate, string $q, ?int $divisionId, int $limit, ?string $destinationFilter = null): array
    {
        $asOfDate = $this->normalizeDate($asOfDate) ?? date('Y-m-d');
        $destinationFilter = $this->normalizeDestinationFilter($destinationFilter);
        if ($limit <= 0 || $limit > 2000) {
            $limit = 300;
        }

        $balanceRows = $this->list_division_stock($q, 5000, $destinationFilter, '', '', $divisionId);
        $dailyRows = $this->list_division_daily_snapshot_latest_closing($asOfDate, $q, $divisionId, $destinationFilter);
        $movementRows = $this->list_division_material_movement_closing($asOfDate, $q, $divisionId, $destinationFilter);

        $balanceMap = $this->aggregateDivisionMaterialCompareSource($balanceRows, 'balance');
        $dailyMap = $this->aggregateDivisionMaterialCompareSource($dailyRows, 'daily');
        // Daily rollup/material matrix sudah dipensiunkan. Untuk audit item-centric,
        // gunakan snapshot harian berbasis movement log yang sama agar tidak muncul
        // mismatch palsu dari source legacy yang sudah tidak diisi lagi.
        $matrixMap = $dailyMap;
        $movementMap = $this->aggregateDivisionMaterialCompareSource($movementRows, 'movement');

        $allKeys = array_fill_keys(array_merge(
            array_keys($balanceMap),
            array_keys($dailyMap),
            array_keys($matrixMap),
            array_keys($movementMap)
        ), true);

        $rows = [];
        foreach (array_keys($allKeys) as $key) {
            $balance = $balanceMap[$key] ?? null;
            $daily = $dailyMap[$key] ?? null;
            $matrix = $matrixMap[$key] ?? null;
            $movement = $movementMap[$key] ?? null;
            $meta = $balance['_meta'] ?? $daily['_meta'] ?? $matrix['_meta'] ?? $movement['_meta'] ?? [];
            if (empty($meta['material_id'])) {
                continue;
            }

            $balanceContent = (float)($balance['qty_content'] ?? 0);
            $dailyContent = (float)($daily['qty_content'] ?? 0);
            $matrixContent = (float)($matrix['qty_content'] ?? 0);
            $movementContent = (float)($movement['qty_content'] ?? 0);
            if (abs($balanceContent) < 0.0001) {
                continue;
            }

            $deltaBalanceVsMovement = round($balanceContent - $movementContent, 4);
            $deltaDailyVsMovement = round($dailyContent - $movementContent, 4);
            $deltaMatrixVsMovement = round($matrixContent - $movementContent, 4);
            $verdict = $this->build_division_material_reconcile_verdict(
                $balanceContent,
                $dailyContent,
                $movementContent,
                [
                    'daily_date' => (string)($daily['_meta']['latest_date'] ?? ''),
                    'as_of_date' => $asOfDate,
                ],
                [
                    'movement_date' => (string)($movement['_meta']['latest_date'] ?? ''),
                ]
            );

            $matches = abs($deltaBalanceVsMovement) < 0.0001
                && abs($deltaDailyVsMovement) < 0.0001
                && abs($deltaMatrixVsMovement) < 0.0001;

            $rows[] = [
                'division_id' => (int)($meta['division_id'] ?? 0),
                'division_name' => (string)($meta['division_name'] ?? ''),
                'division_code' => (string)($meta['division_code'] ?? ''),
                'destination_group' => (string)($meta['destination_group'] ?? 'REGULER'),
                'destination_name' => (string)($meta['destination_name'] ?? 'Reguler'),
                'item_id' => (int)($meta['item_id'] ?? 0),
                'material_id' => (int)($meta['material_id'] ?? 0),
                'material_code' => (string)($meta['material_code'] ?? ''),
                'material_name' => (string)($meta['material_name'] ?? ''),
                'balance_qty_content' => $balanceContent,
                'balance_qty_pack' => (float)($balance['qty_pack'] ?? 0),
                'daily_qty_content' => $dailyContent,
                'daily_qty_pack' => (float)($daily['qty_pack'] ?? 0),
                'matrix_qty_content' => $matrixContent,
                'matrix_qty_pack' => (float)($matrix['qty_pack'] ?? 0),
                'movement_qty_content' => $movementContent,
                'movement_qty_pack' => (float)($movement['qty_pack'] ?? 0),
                'delta_balance_vs_movement' => $deltaBalanceVsMovement,
                'delta_daily_vs_movement' => $deltaDailyVsMovement,
                'delta_matrix_vs_movement' => $deltaMatrixVsMovement,
                'daily_date' => (string)($daily['_meta']['latest_date'] ?? ''),
                'movement_date' => (string)($movement['_meta']['latest_date'] ?? ''),
                'daily_audit_has_mismatch' => (int)($daily['_meta']['audit_has_mismatch'] ?? 0),
                'daily_audit_mismatch_qty_content' => (float)($daily['_meta']['audit_mismatch_qty_content'] ?? 0),
                'daily_audit_mismatch_notes' => (string)($daily['_meta']['audit_mismatch_notes'] ?? ''),
                'suspect_table' => (string)($verdict['suspect_table'] ?? 'MATCH'),
                'suspect_reason' => (string)($verdict['reason'] ?? ''),
                'is_match' => $matches ? 1 : 0,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $cmp = strcasecmp((string)($a['division_name'] ?? ''), (string)($b['division_name'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = strcasecmp((string)($a['destination_group'] ?? ''), (string)($b['destination_group'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = strcasecmp((string)($a['material_name'] ?? ''), (string)($b['material_name'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcasecmp((string)($a['material_code'] ?? ''), (string)($b['material_code'] ?? ''));
        });

        $this->attach_material_lot_totals($rows);
        $this->attach_material_daily_check($rows);

        $summary = [
            'total_rows' => count($rows),
            'match_rows' => 0,
            'mismatch_rows' => 0,
        ];
        foreach ($rows as $row) {
            if (!empty($row['is_match'])) {
                $summary['match_rows']++;
            } else {
                $summary['mismatch_rows']++;
            }
        }

        return [
            'as_of_date' => $asOfDate,
            'rows' => array_slice($rows, 0, $limit),
            'summary' => $summary,
        ];
    }

    private function attach_material_lot_totals(array &$rows): void
    {
        foreach ($rows as &$row) {
            $row['lot_qty_content'] = null;
        }
        unset($row);

        if (!$this->db->table_exists('inv_material_fifo_lot') || empty($rows)) {
            return;
        }

        $divisionIds = array_values(array_unique(array_filter(array_map(
            static function ($r) { return isset($r['division_id']) ? (int)$r['division_id'] : null; },
            $rows
        ))));
        if (empty($divisionIds)) {
            return;
        }

        $destGroupExpr = "CASE WHEN l.destination_type IN ('BAR_EVENT','KITCHEN_EVENT') THEN 'EVENT' ELSE 'REGULER' END";
        $results = $this->db
            ->select("l.division_id, ({$destGroupExpr}) AS destination_group, COALESCE(l.item_id,0) AS item_id, COALESCE(l.material_id,0) AS material_id, SUM(l.qty_balance) AS lot_total", false)
            ->from('inv_material_fifo_lot l')
            ->where('l.location_scope', 'DIVISION')
            ->where('l.status', 'OPEN')
            ->where('l.qty_balance >', 0.0001)
            ->where_in('l.division_id', $divisionIds)
            ->group_by(['l.division_id', 'destination_group', 'l.item_id', 'l.material_id'])
            ->get()->result_array();

        $lotMap = [];
        foreach ($results as $r) {
            $k = (int)$r['division_id'] . '|' . strtoupper((string)($r['destination_group'] ?? 'REGULER'))
               . '|M-' . (int)$r['material_id'] . '|I-' . (int)$r['item_id'];
            $lotMap[$k] = round((float)($r['lot_total'] ?? 0), 4);
        }

        foreach ($rows as &$row) {
            $k = (int)($row['division_id'] ?? 0) . '|' . strtoupper((string)($row['destination_group'] ?? 'REGULER'))
               . '|M-' . (int)($row['material_id'] ?? 0) . '|I-' . (int)($row['item_id'] ?? 0);
            $row['lot_qty_content'] = $lotMap[$k] ?? 0.0;
        }
        unset($row);
    }

    private function attach_material_daily_check(array &$rows): void
    {
        foreach ($rows as &$row) {
            $row['daily_check_status']      = 'UNKNOWN';
            $row['daily_check_drift']       = 0.0;
            $row['daily_check_drift_count'] = 0;
        }
        unset($row);

        if (!$this->db->table_exists('inv_division_monthly_stock') || empty($rows)) {
            return;
        }

        $divisionIds = array_values(array_unique(array_filter(array_map(
            static function ($r) { return isset($r['division_id']) ? (int)$r['division_id'] : null; },
            $rows
        ))));
        if (empty($divisionIds)) {
            return;
        }

        $driftExpr = 'ROUND(ABS(s.closing_qty_content - ROUND('
            . 's.opening_qty_content + s.in_qty_content + s.adjustment_plus_qty_content'
            . ' - s.out_qty_content - s.waste_qty_content - s.adjustment_minus_qty_content'
            . ' - COALESCE(s.discarded_qty_content,0) - COALESCE(s.spoil_qty_content,0)'
            . ' - COALESCE(s.process_loss_qty_content,0) + COALESCE(s.variance_qty_content,0)'
            . ', 4)), 4)';
        $destGroupExpr = "CASE WHEN s.destination_type IN ('BAR_EVENT','KITCHEN_EVENT') THEN 'EVENT' ELSE 'REGULER' END";

        // Only check the latest month per identity to avoid false positives from old months
        $latestMonthSub = $this->db
            ->select('s2.division_id, s2.destination_type, COALESCE(s2.item_id,0) AS item_id, COALESCE(s2.material_id,0) AS material_id, MAX(s2.month_key) AS max_month', false)
            ->from('inv_division_monthly_stock s2')
            ->where_in('s2.division_id', $divisionIds)
            ->group_by(['s2.division_id', 's2.destination_type', 's2.item_id', 's2.material_id'])
            ->get_compiled_select();

        $results = $this->db
            ->select("s.division_id, ({$destGroupExpr}) AS destination_group, COALESCE(s.item_id,0) AS item_id, COALESCE(s.material_id,0) AS material_id, SUM({$driftExpr}) AS total_drift, COUNT(*) AS profile_count, SUM(CASE WHEN {$driftExpr} > 0.0001 THEN 1 ELSE 0 END) AS drift_count", false)
            ->from('inv_division_monthly_stock s')
            ->join('(' . $latestMonthSub . ') lm',
                's.division_id = lm.division_id AND s.destination_type = lm.destination_type'
                . ' AND COALESCE(s.item_id,0) = lm.item_id AND COALESCE(s.material_id,0) = lm.material_id'
                . ' AND s.month_key = lm.max_month',
                'inner', false)
            ->where_in('s.division_id', $divisionIds)
            ->group_by(['s.division_id', 'destination_group', 's.item_id', 's.material_id'])
            ->get()->result_array();

        $checkMap = [];
        foreach ($results as $r) {
            $k = (int)$r['division_id'] . '|' . strtoupper((string)($r['destination_group'] ?? 'REGULER'))
               . '|M-' . (int)$r['material_id'] . '|I-' . (int)$r['item_id'];
            $checkMap[$k] = [
                'total_drift'  => round((float)($r['total_drift'] ?? 0), 4),
                'drift_count'  => (int)($r['drift_count'] ?? 0),
                'profile_count' => (int)($r['profile_count'] ?? 0),
            ];
        }

        foreach ($rows as &$row) {
            $k = (int)($row['division_id'] ?? 0) . '|' . strtoupper((string)($row['destination_group'] ?? 'REGULER'))
               . '|M-' . (int)($row['material_id'] ?? 0) . '|I-' . (int)($row['item_id'] ?? 0);
            $check = $checkMap[$k] ?? null;
            if ($check === null) {
                $row['daily_check_status']      = 'UNKNOWN';
                $row['daily_check_drift']       = 0.0;
                $row['daily_check_drift_count'] = 0;
            } else {
                $row['daily_check_status']      = $check['drift_count'] > 0 ? 'DRIFT' : 'OK';
                $row['daily_check_drift']       = $check['total_drift'];
                $row['daily_check_drift_count'] = $check['drift_count'];
            }
        }
        unset($row);
    }

    private function list_division_daily_snapshot_latest_closing(string $asOfDate, string $q, ?int $divisionId, ?string $destinationFilter = null): array
    {
        if ($this->db->table_exists('inv_division_monthly_stock')) {
            $monthStart = date('Y-m-01', strtotime($asOfDate));
            $rows = $this->build_division_daily_rows_from_monthly_base(
                $q,
                $divisionId,
                $monthStart,
                $asOfDate,
                5000,
                $destinationFilter
            );

            $latestRows = [];
            foreach ($rows as $row) {
                $identityKey = $this->buildInventoryDailyMatrixIdentityKey('DIVISION', $row);
                // rows are ordered newest-first (backward loop); keep first encounter = as_of_date row
                if (!isset($latestRows[$identityKey])) {
                    $latestRows[$identityKey] = $row;
                }
            }

            return array_values($latestRows);
        }

        return $this->list_division_daily_snapshot_latest_closing_raw_movement($asOfDate, $q, $divisionId, $destinationFilter);
    }

    private function list_division_daily_snapshot_latest_closing_raw_movement(string $asOfDate, string $q, ?int $divisionId, ?string $destinationFilter = null): array
    {
        if (!$this->db->table_exists('inv_stock_movement_log')) {
            return [];
        }

        $rows = $this->fetchInventoryDailyMatrixSourceRowsFromMovement(
            'DIVISION',
            $q,
            $divisionId,
            '2000-01-01',
            $asOfDate,
            $destinationFilter,
            true
        );
        $rows = $this->normalizeDivisionProfileKeyRows($rows);

        $latestRows = [];
        foreach ($rows as $row) {
            $identityKey = implode('|', [
                (int)($row['division_id'] ?? 0),
                strtoupper((string)($row['destination_type'] ?? 'OTHER')),
                (int)($row['item_id'] ?? 0),
                (int)($row['material_id'] ?? 0),
                (int)($row['buy_uom_id'] ?? 0),
                (int)($row['content_uom_id'] ?? 0),
                strtoupper(trim((string)($row['profile_key'] ?? ''))),
            ]);
            $packSize = (float)($row['profile_content_per_buy'] ?? 0);
            $row['closing_qty_pack'] = $packSize > 0
                ? round((float)($row['closing_qty_content'] ?? 0) / $packSize, 4)
                : 0.0;
            $latestRows[$identityKey] = $row;
        }

        return array_values($latestRows);
    }

    private function normalizeDivisionProfileKeyRows(array $rows): array
    {
        if (empty($rows) || !$this->db->table_exists('inv_stock_movement_log')) {
            return $rows;
        }

        $cache = [];
        foreach ($rows as &$row) {
            $currentProfileKey = trim((string)($row['profile_key'] ?? ''));
            if ($currentProfileKey !== '') {
                $row['profile_key'] = $currentProfileKey;
                continue;
            }

            $itemId = (int)($row['item_id'] ?? 0);
            $buyUomId = (int)($row['buy_uom_id'] ?? 0);
            $contentUomId = (int)($row['content_uom_id'] ?? 0);
            if ($itemId <= 0 || $buyUomId <= 0 || $contentUomId <= 0) {
                continue;
            }

            $divisionId = isset($row['division_id']) ? (int)$row['division_id'] : null;
            $destinationType = strtoupper(trim((string)($row['destination_type'] ?? 'OTHER')));
            $materialId = $this->nullableInt($row['material_id'] ?? null);
            $profileName = $this->nullableString($row['profile_name'] ?? null);
            $profileBrand = $this->nullableString($row['profile_brand'] ?? null);
            $profileDescription = $this->nullableString($row['profile_description'] ?? null);
            $profileExpiredDate = $this->normalizeDate((string)($row['profile_expired_date'] ?? ''));
            $profileContentPerBuy = round((float)($row['profile_content_per_buy'] ?? 0), 6);

            $cacheKey = implode('|', [
                $divisionId !== null ? (string)$divisionId : '',
                $destinationType,
                (string)$itemId,
                $materialId !== null ? (string)$materialId : '',
                (string)$buyUomId,
                (string)$contentUomId,
                strtoupper((string)$profileName),
                strtoupper((string)$profileBrand),
                strtoupper((string)$profileDescription),
                (string)$profileExpiredDate,
                number_format($profileContentPerBuy, 6, '.', ''),
            ]);

            if (!array_key_exists($cacheKey, $cache)) {
                $canonicalProfileKey = $this->resolveCatalogProfileKeyByIdentity(
                    $itemId,
                    $materialId,
                    $buyUomId,
                    $contentUomId,
                    $profileName,
                    $profileBrand,
                    $profileDescription,
                    $profileExpiredDate,
                    $profileContentPerBuy
                );
                if ($canonicalProfileKey === null) {
                    $canonicalProfileKey = $this->resolveExistingOpeningProfileKey(
                        'DIVISION',
                        $divisionId,
                        $destinationType,
                        $itemId,
                        $materialId,
                        $buyUomId,
                        $contentUomId,
                        $profileName,
                        $profileBrand,
                        $profileDescription,
                        $profileExpiredDate,
                        $profileContentPerBuy
                    );
                }

                $cache[$cacheKey] = trim((string)$canonicalProfileKey);
            }

            if ($cache[$cacheKey] !== '') {
                $row['profile_key'] = $cache[$cacheKey];
            }
        }
        unset($row);

        return $rows;
    }

    private function build_division_material_reconcile_verdict(float $balanceContent, float $dailyContent, float $movementContent, array $dailyMeta = [], array $movementMeta = []): array
    {
        $eps = 0.0001;
        $balanceVsDaily = abs($balanceContent - $dailyContent);
        $balanceVsMovement = abs($balanceContent - $movementContent);
        $dailyVsMovement = abs($dailyContent - $movementContent);
        $dailyDate = (string)($dailyMeta['daily_date'] ?? '');
        $movementDate = (string)($movementMeta['movement_date'] ?? '');

        if ($balanceVsDaily < $eps && $balanceVsMovement < $eps) {
            return ['suspect_table' => 'MATCH', 'reason' => 'Balance, proyeksi harian, dan movement masih sinkron.'];
        }
        if ($dailyVsMovement < $eps && $balanceVsDaily >= $eps) {
            return ['suspect_table' => 'BALANCE', 'reason' => 'Balance berbeda, sementara proyeksi harian masih sama dengan closing movement.'];
        }
        if ($balanceVsMovement < $eps && $dailyVsMovement >= $eps) {
            $reason = 'Proyeksi harian berbeda, sementara balance masih sama dengan closing movement.';
            if ($dailyDate === '') {
                $reason = 'Proyeksi harian tidak punya closing sampai tanggal audit, sementara balance sudah sama dengan movement.';
            } elseif ($movementDate !== '' && $dailyDate !== '' && $dailyDate < $movementDate) {
                $reason = 'Proyeksi harian tertinggal dari movement terakhir, sementara balance sudah mengikuti movement.';
            }
            return ['suspect_table' => 'DAILY', 'reason' => $reason];
        }
        if ($balanceVsDaily < $eps && $dailyVsMovement >= $eps) {
            return ['suspect_table' => 'MOVEMENT_OR_SOURCE', 'reason' => 'Balance dan proyeksi harian sama, tetapi closing movement berbeda. Periksa movement log atau sumber posting.'];
        }

        return ['suspect_table' => 'MULTIPLE', 'reason' => 'Balance, proyeksi harian, dan movement tidak saling cocok. Periksa jalur posting atau rebuild identity ini dari movement log.'];
    }

    public function division_material_reconcile_audit(string $asOfDate, array $filters): array
    {
        $asOfDate = $this->normalizeDate($asOfDate) ?? date('Y-m-d');
        $divisionId = (int)($filters['division_id'] ?? 0);
        $itemId = (int)($filters['item_id'] ?? 0);
        $materialId = (int)($filters['material_id'] ?? 0);
        $destinationFilter = $this->normalizeDestinationFilter((string)($filters['destination'] ?? 'ALL'));

        if ($itemId <= 0 && $materialId <= 0) {
            return ['ok' => false, 'message' => 'Material audit membutuhkan item_id atau material_id.'];
        }

        $compare = $this->list_division_material_stock_compare($asOfDate, '', $divisionId > 0 ? $divisionId : null, 5000, $destinationFilter);
        $summaryRow = $this->find_division_material_compare_row((array)($compare['rows'] ?? []), [
            'division_id' => $divisionId,
            'item_id' => $itemId,
            'material_id' => $materialId,
            'destination' => $destinationFilter,
        ]);
        if ($summaryRow === null) {
            return ['ok' => false, 'message' => 'Data reconcile bahan tidak ditemukan untuk filter ini.'];
        }

        $movements = $this->list_division_material_reconcile_movements($asOfDate, [
            'division_id' => $divisionId,
            'item_id' => $itemId,
            'material_id' => $materialId,
            'destination' => $destinationFilter,
        ]);

        $bucketSeed = [
            'OPENING' => 'Opening',
            'PO' => 'PO',
            'SR' => 'SR',
            'VOID' => 'Void',
            'REFUND' => 'Refund',
            'ADJUSTMENT' => 'Adjustment',
            'POS' => 'POS',
            'OTHER' => 'Lainnya',
        ];
        $bucketRows = [];
        foreach ($bucketSeed as $code => $label) {
            $bucketRows[$code] = [
                'bucket_code' => $code,
                'bucket_label' => $label,
                'count' => 0,
                'delta_content' => 0.0,
                'delta_buy' => 0.0,
                'mutation_value' => 0.0,
                'last_movement_date' => '',
                'last_movement_no' => '',
            ];
        }

        foreach ($movements as $movement) {
            $bucket = $this->classify_division_material_reconcile_bucket($movement);
            $code = (string)($bucket['code'] ?? 'OTHER');
            if (!isset($bucketRows[$code])) {
                $bucketRows[$code] = [
                    'bucket_code' => $code,
                    'bucket_label' => (string)($bucket['label'] ?? $code),
                    'count' => 0,
                    'delta_content' => 0.0,
                    'delta_buy' => 0.0,
                    'mutation_value' => 0.0,
                    'last_movement_date' => '',
                    'last_movement_no' => '',
                ];
            }
            $bucketRows[$code]['count']++;
            $bucketRows[$code]['delta_content'] = round((float)$bucketRows[$code]['delta_content'] + (float)($movement['qty_content_delta'] ?? 0), 4);
            $bucketRows[$code]['delta_buy'] = round((float)$bucketRows[$code]['delta_buy'] + (float)($movement['qty_buy_delta'] ?? 0), 4);
            $bucketRows[$code]['mutation_value'] = round((float)$bucketRows[$code]['mutation_value'] + (float)($movement['mutation_value'] ?? 0), 2);
            $movementDate = (string)($movement['movement_date'] ?? '');
            if ($movementDate >= (string)$bucketRows[$code]['last_movement_date']) {
                $bucketRows[$code]['last_movement_date'] = $movementDate;
                $bucketRows[$code]['last_movement_no'] = (string)($movement['movement_no'] ?? '');
            }
        }

        $identities = $this->list_division_material_reconcile_identities($asOfDate, [
            'division_id' => $divisionId,
            'item_id' => $itemId,
            'material_id' => $materialId,
            'destination' => $destinationFilter,
        ]);

        return [
            'ok' => true,
            'summary' => $summaryRow,
            'buckets' => array_values($bucketRows),
            'movements' => array_reverse($movements),
            'diagnosis' => [
                'suspect_table' => (string)($summaryRow['suspect_table'] ?? 'MATCH'),
                'reason' => (string)($summaryRow['suspect_reason'] ?? ''),
                'daily_date' => (string)($summaryRow['daily_date'] ?? ''),
                'movement_date' => (string)($summaryRow['movement_date'] ?? ''),
            ],
            'repair_identity_count' => count($identities),
        ];
    }

    public function repair_division_material_reconcile(string $asOfDate, array $filters): array
    {
        $asOfDate = $this->normalizeDate($asOfDate) ?? date('Y-m-d');
        $divisionId = (int)($filters['division_id'] ?? 0);
        $itemId = (int)($filters['item_id'] ?? 0);
        $materialId = (int)($filters['material_id'] ?? 0);
        $destinationFilter = $this->normalizeDestinationFilter((string)($filters['destination'] ?? 'ALL'));

        $identities = $this->list_division_material_reconcile_identities($asOfDate, [
            'division_id' => $divisionId,
            'item_id' => $itemId,
            'material_id' => $materialId,
            'destination' => $destinationFilter,
        ]);
        if (empty($identities)) {
            return ['ok' => false, 'message' => 'Identity stok bahan untuk repair tidak ditemukan.'];
        }

        $results = [];
        $successCount = 0;
        foreach ($identities as $identity) {
            unset($identity['_start_date']);
            $movementRefresh = $this->refresh_division_material_movement_after_balances($asOfDate, $identity);
            $repair = $this->rebuild_division_material_history_from_movements($asOfDate, $identity);
            if (!empty($movementRefresh['ok'])) {
                $repair['data']['movement_after_rows_refreshed'] = (int)($movementRefresh['data']['rows_refreshed'] ?? 0);
            }
            if (empty($movementRefresh['ok'])) {
                $repair['data']['movement_after_refresh_error'] = (string)($movementRefresh['message'] ?? '');
            }
            if (($repair['ok'] ?? false)) {
                $successCount++;
            }
            $results[] = [
                'identity' => $identity,
                'result' => $repair,
            ];
        }

        if ($successCount <= 0) {
            $message = 'Repair stok bahan gagal pada semua identity.';
            if (!empty($results[0]['result']['message'])) {
                $message = (string)$results[0]['result']['message'];
            }
            return ['ok' => false, 'message' => $message, 'data' => ['results' => $results]];
        }

        return [
            'ok' => true,
            'message' => 'Repair stok bahan selesai dijalankan.',
            'data' => [
                'identity_count' => count($identities),
                'success_count' => $successCount,
                'results' => $results,
            ],
        ];
    }

    public function repair_material_monthly_stock_drift(string $asOfDate, array $params): array
    {
        if (!$this->db->table_exists('inv_division_monthly_stock')) {
            return ['ok' => false, 'message' => 'Tabel inv_division_monthly_stock tidak ditemukan.'];
        }
        $divisionId = (int)($params['division_id'] ?? 0);
        $itemId = (int)($params['item_id'] ?? 0);
        $materialId = (int)($params['material_id'] ?? 0);
        $destinationGroup = strtoupper(trim((string)($params['destination_group'] ?? 'REGULER')));
        if ($divisionId <= 0) {
            return ['ok' => false, 'message' => 'division_id diperlukan.'];
        }

        $destinationTypes = $destinationGroup === 'EVENT'
            ? ['BAR_EVENT', 'KITCHEN_EVENT']
            : ['BAR', 'KITCHEN', 'REGULER', 'WAREHOUSE', 'CUSTOM'];

        $monthKeySubQ = $this->db
            ->select('MAX(s2.month_key)', false)
            ->from('inv_division_monthly_stock s2')
            ->where('s2.division_id', $divisionId)
            ->where('COALESCE(s2.item_id,0)', $itemId)
            ->where('COALESCE(s2.material_id,0)', $materialId)
            ->where_in('s2.destination_type', $destinationTypes)
            ->get_compiled_select();

        $driftExpr = 'ROUND(ABS(s.closing_qty_content - ROUND('
            . 's.opening_qty_content + s.in_qty_content + COALESCE(s.adjustment_plus_qty_content,0)'
            . ' - s.out_qty_content - COALESCE(s.waste_qty_content,0) - COALESCE(s.adjustment_minus_qty_content,0)'
            . ' - COALESCE(s.discarded_qty_content,0) - COALESCE(s.spoil_qty_content,0)'
            . ' - COALESCE(s.process_loss_qty_content,0) + COALESCE(s.variance_qty_content,0)'
            . ', 4)), 4)';

        $rows = $this->db
            ->select('s.id, s.closing_qty_content, s.opening_qty_content, s.in_qty_content, s.out_qty_content, s.waste_qty_content, s.adjustment_plus_qty_content, s.adjustment_minus_qty_content, s.discarded_qty_content, s.spoil_qty_content, s.process_loss_qty_content, s.variance_qty_content', false)
            ->from('inv_division_monthly_stock s')
            ->where('s.division_id', $divisionId)
            ->where('COALESCE(s.item_id,0)', $itemId)
            ->where('COALESCE(s.material_id,0)', $materialId)
            ->where_in('s.destination_type', $destinationTypes)
            ->where("s.month_key = ({$monthKeySubQ})", null, false)
            ->where("{$driftExpr} > 0.0001", null, false)
            ->get()->result_array();

        if (empty($rows)) {
            return ['ok' => true, 'message' => 'Tidak ada drift pada monthly stock bahan ini.', 'data' => ['rows_fixed' => 0]];
        }

        $fixed = 0;
        foreach ($rows as $r) {
            $newVariance = round(
                (float)$r['closing_qty_content']
                - (float)$r['opening_qty_content']
                - (float)$r['in_qty_content']
                - (float)($r['adjustment_plus_qty_content'] ?? 0)
                + (float)$r['out_qty_content']
                + (float)($r['waste_qty_content'] ?? 0)
                + (float)($r['adjustment_minus_qty_content'] ?? 0)
                + (float)($r['discarded_qty_content'] ?? 0)
                + (float)($r['spoil_qty_content'] ?? 0)
                + (float)($r['process_loss_qty_content'] ?? 0),
            4);
            $this->db->where('id', (int)$r['id'])
                ->update('inv_division_monthly_stock', ['variance_qty_content' => $newVariance]);
            $fixed++;
        }

        return [
            'ok' => true,
            'message' => 'Drift monthly stock bahan diserap ke variance.',
            'data' => ['rows_fixed' => $fixed],
        ];
    }

    private function refresh_division_material_movement_after_balances(string $asOfDate, array $identity): array
    {
        if (!$this->db->table_exists('inv_stock_movement_log')) {
            return ['ok' => true, 'data' => ['rows_refreshed' => 0]];
        }

        $hasDestinationType = $this->db->field_exists('destination_type', 'inv_stock_movement_log');
        $this->db
            ->select('l.id, l.qty_buy_delta, l.qty_content_delta, l.unit_cost', false)
            ->from('inv_stock_movement_log l')
            ->where('l.movement_scope', 'DIVISION')
            ->where('l.movement_date <=', $asOfDate);
        $this->applyInventoryHistoryIdentityFilter('l', 'DIVISION', $identity, $hasDestinationType);
        $rows = $this->db
            ->order_by('l.movement_date', 'ASC')
            ->order_by('l.id', 'ASC')
            ->get()
            ->result_array();

        if (empty($rows)) {
            return ['ok' => true, 'data' => ['rows_refreshed' => 0]];
        }

        $currentBuy = 0.0;
        $currentContent = 0.0;
        $currentAvg = 0.0;
        $updates = [];
        foreach ($rows as $row) {
            $state = $this->applyInventoryHistoryMovement(
                $currentBuy,
                $currentContent,
                $currentAvg,
                (float)($row['qty_buy_delta'] ?? 0),
                (float)($row['qty_content_delta'] ?? 0),
                (float)($row['unit_cost'] ?? 0)
            );
            $currentBuy = round((float)($state['qty_buy'] ?? 0), 4);
            $currentContent = round((float)($state['qty_content'] ?? 0), 4);
            $currentAvg = round((float)($state['avg_cost_per_content'] ?? 0), 6);
            $updates[] = [
                'id' => (int)($row['id'] ?? 0),
                'qty_buy_after' => $currentBuy,
                'qty_content_after' => $currentContent,
            ];
        }

        $this->db->trans_begin();
        foreach ($updates as $update) {
            $this->db
                ->where('id', (int)$update['id'])
                ->update('inv_stock_movement_log', [
                    'qty_buy_after' => $update['qty_buy_after'],
                    'qty_content_after' => $update['qty_content_after'],
                ]);
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal refresh saldo after movement log bahan.'];
        }

        $this->db->trans_commit();
        return ['ok' => true, 'data' => ['rows_refreshed' => count($updates)]];
    }

    private function rebuild_division_material_history_from_movements(string $asOfDate, array $identity): array
    {
        $asOfDate = $this->normalizeDate($asOfDate) ?? date('Y-m-d');
        if (!$this->db->table_exists('inv_stock_movement_log') || !$this->db->table_exists('inv_division_monthly_stock')) {
            return ['ok' => false, 'message' => 'Tabel movement/monthly stock bahan belum lengkap untuk repair reconcile.'];
        }

        $hasMovementDestination = $this->db->field_exists('destination_type', 'inv_stock_movement_log');
        $hasMovementAdjustmentCategory = $this->db->field_exists('adjustment_category', 'inv_stock_movement_log');
        $hasMovementProfileExpiredDate = $this->db->field_exists('profile_expired_date', 'inv_stock_movement_log');
        $movementColumns = [
            'l.id, l.movement_date, l.movement_type, l.item_id, l.material_id, l.buy_uom_id, l.content_uom_id, l.profile_key',
            'l.profile_name, l.profile_brand, l.profile_description, l.profile_content_per_buy, l.profile_buy_uom_code, l.profile_content_uom_code',
            'l.qty_buy_delta, l.qty_content_delta, l.unit_cost, l.created_at, l.ref_table',
        ];
        $movementColumns[] = $hasMovementAdjustmentCategory ? 'l.adjustment_category' : 'NULL AS adjustment_category';
        $movementColumns[] = $hasMovementProfileExpiredDate ? 'l.profile_expired_date' : 'NULL AS profile_expired_date';
        if ($hasMovementDestination) {
            $movementColumns[] = 'l.destination_type';
        }

        $this->db
            ->select(implode(', ', $movementColumns), false)
            ->from('inv_stock_movement_log l')
            ->where('l.movement_scope', 'DIVISION')
            ->where('l.movement_date <=', $asOfDate);
        $this->applyInventoryHistoryIdentityFilter('l', 'DIVISION', $identity, $hasMovementDestination);
        $movementRows = $this->db
            ->order_by('l.movement_date', 'ASC')
            ->order_by('l.id', 'ASC')
            ->get()
            ->result_array();

        $this->db->trans_begin();

        if (empty($movementRows)) {
            $this->purgeInventoryMonthlyStockForIdentity('DIVISION', $identity, null);
            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                return ['ok' => false, 'message' => 'Gagal membersihkan identity bahan tanpa movement.'];
            }
            $this->db->trans_commit();
            return ['ok' => true, 'message' => 'Identity bahan dibersihkan karena tidak punya movement log.', 'data' => ['days_rebuilt' => 0]];
        }

        $currentBuy = 0.0;
        $currentContent = 0.0;
        $currentAvg = 0.0;
        $lastProfile = [
            'stock_domain' => (string)($identity['stock_domain'] ?? 'ITEM'),
            'item_id' => $identity['item_id'] ?? null,
            'material_id' => $identity['material_id'] ?? null,
            'buy_uom_id' => $identity['buy_uom_id'] ?? null,
            'content_uom_id' => $identity['content_uom_id'] ?? null,
            'profile_key' => $identity['profile_key'] ?? null,
            'profile_name' => $this->nullableString($identity['profile_name'] ?? null),
            'profile_brand' => $this->nullableString($identity['profile_brand'] ?? null),
            'profile_description' => $this->nullableString($identity['profile_description'] ?? null),
            'profile_expired_date' => $this->normalizeDate((string)($identity['profile_expired_date'] ?? '')),
            'profile_content_per_buy' => round((float)($identity['profile_content_per_buy'] ?? 0), 6),
            'profile_buy_uom_code' => $this->nullableString($identity['profile_buy_uom_code'] ?? null),
            'profile_content_uom_code' => $this->nullableString($identity['profile_content_uom_code'] ?? null),
        ];

        $movementByDate = [];
        foreach ($movementRows as $movementRow) {
            $movementDay = (string)($movementRow['movement_date'] ?? '');
            if ($movementDay === '') {
                continue;
            }
            if (!isset($movementByDate[$movementDay])) {
                $movementByDate[$movementDay] = [];
            }
            $movementByDate[$movementDay][] = $movementRow;
        }

        $monthlyRows = [];
        foreach (array_keys($movementByDate) as $day) {
            $openingBuy = $currentBuy;
            $openingContent = $currentContent;
            $deltaMaps = $this->emptyInventoryDailyDeltaMaps();
            $mutationCount = 0;
            $lastMovementAt = null;

            foreach ($movementByDate[$day] as $movementRow) {
                $movementType = strtoupper(trim((string)($movementRow['movement_type'] ?? 'ADJUSTMENT')));
                $qtyBuyDelta = round((float)($movementRow['qty_buy_delta'] ?? 0), 4);
                $qtyContentDelta = round((float)($movementRow['qty_content_delta'] ?? 0), 4);
                $adjustmentCategory = $this->normalizeInventoryAdjustmentCategory((string)($movementRow['adjustment_category'] ?? ''));
                if ($adjustmentCategory === null) {
                    $adjustmentCategory = $this->resolveInventoryAdjustmentCategoryFromMovement($movementType, $qtyBuyDelta, $qtyContentDelta);
                }
                $deltaPack = $this->buildInventoryDailyDeltaPack($movementType, $qtyBuyDelta, $qtyContentDelta, $adjustmentCategory, $currentAvg);
                foreach ($deltaPack['delta'] as $field => $value) {
                    $deltaMaps['delta'][$field] = round($deltaMaps['delta'][$field] + $value, 4);
                }
                foreach ($deltaPack['value'] as $field => $value) {
                    $deltaMaps['value'][$field] = round($deltaMaps['value'][$field] + $value, 2);
                }

                $state = $this->applyInventoryHistoryMovement($currentBuy, $currentContent, $currentAvg, $qtyBuyDelta, $qtyContentDelta, (float)($movementRow['unit_cost'] ?? 0));
                $currentBuy = $state['qty_buy'];
                $currentContent = $state['qty_content'];
                $currentAvg = $state['avg_cost_per_content'];
                $mutationCount++;
                $lastMovementAt = $movementRow['created_at'] ?? $lastMovementAt;

                foreach (['profile_name', 'profile_brand', 'profile_description', 'profile_buy_uom_code', 'profile_content_uom_code'] as $profileField) {
                    $value = $this->nullableString($movementRow[$profileField] ?? null);
                    if ($value !== null) {
                        $lastProfile[$profileField] = $value;
                    }
                }
                if (!empty($movementRow['profile_expired_date'])) {
                    $lastProfile['profile_expired_date'] = $this->normalizeDate((string)$movementRow['profile_expired_date']);
                }
                if (isset($movementRow['profile_content_per_buy']) && (float)$movementRow['profile_content_per_buy'] > 0) {
                    $lastProfile['profile_content_per_buy'] = round((float)$movementRow['profile_content_per_buy'], 6);
                }
            }

            $row = [
                'month_key' => date('Y-m-01', strtotime($day)),
                'movement_date' => $day,
                'stock_domain' => (string)($lastProfile['stock_domain'] ?? $identity['stock_domain'] ?? 'ITEM'),
                'division_id' => $identity['division_id'] ?? null,
                'destination_type' => $identity['destination_type'] ?? 'OTHER',
                'item_id' => $identity['item_id'] ?? null,
                'material_id' => $identity['material_id'] ?? null,
                'buy_uom_id' => $identity['buy_uom_id'] ?? null,
                'content_uom_id' => $identity['content_uom_id'] ?? null,
                'profile_key' => $identity['profile_key'] ?? null,
                'profile_name' => $lastProfile['profile_name'] ?? null,
                'profile_brand' => $lastProfile['profile_brand'] ?? null,
                'profile_description' => $lastProfile['profile_description'] ?? null,
                'profile_content_per_buy' => round((float)($lastProfile['profile_content_per_buy'] ?? 0), 6),
                'profile_buy_uom_code' => $lastProfile['profile_buy_uom_code'] ?? null,
                'profile_content_uom_code' => $lastProfile['profile_content_uom_code'] ?? null,
                'opening_qty_buy' => round($openingBuy, 4),
                'opening_qty_content' => round($openingContent, 4),
                'in_qty_buy' => $deltaMaps['delta']['in_qty_buy'],
                'in_qty_content' => $deltaMaps['delta']['in_qty_content'],
                'out_qty_buy' => $deltaMaps['delta']['out_qty_buy'],
                'out_qty_content' => $deltaMaps['delta']['out_qty_content'],
                'discarded_qty_buy' => $deltaMaps['delta']['discarded_qty_buy'],
                'discarded_qty_content' => $deltaMaps['delta']['discarded_qty_content'],
                'spoil_qty_buy' => $deltaMaps['delta']['spoil_qty_buy'],
                'spoil_qty_content' => $deltaMaps['delta']['spoil_qty_content'],
                'waste_qty_buy' => $deltaMaps['delta']['waste_qty_buy'],
                'waste_qty_content' => $deltaMaps['delta']['waste_qty_content'],
                'adjustment_qty_buy' => $deltaMaps['delta']['adjustment_qty_buy'],
                'adjustment_qty_content' => $deltaMaps['delta']['adjustment_qty_content'],
                'closing_qty_buy' => round($currentBuy, 4),
                'closing_qty_content' => round($currentContent, 4),
                'avg_cost_per_content' => round($currentAvg, 6),
                'total_value' => round($currentContent * $currentAvg, 2),
                'mutation_count' => $mutationCount,
                'last_movement_at' => $lastMovementAt,
                'process_loss_qty_buy' => $deltaMaps['delta']['process_loss_qty_buy'],
                'process_loss_qty_content' => $deltaMaps['delta']['process_loss_qty_content'],
                'variance_qty_buy' => $deltaMaps['delta']['variance_qty_buy'],
                'variance_qty_content' => $deltaMaps['delta']['variance_qty_content'],
                'adjustment_plus_qty_buy' => $deltaMaps['delta']['adjustment_plus_qty_buy'],
                'adjustment_plus_qty_content' => $deltaMaps['delta']['adjustment_plus_qty_content'],
                'waste_total_value' => $deltaMaps['value']['waste_total_value'],
                'spoilage_total_value' => $deltaMaps['value']['spoilage_total_value'],
                'process_loss_total_value' => $deltaMaps['value']['process_loss_total_value'],
                'variance_total_value' => $deltaMaps['value']['variance_total_value'],
                'adjustment_plus_total_value' => $deltaMaps['value']['adjustment_plus_total_value'],
                'profile_expired_date' => $lastProfile['profile_expired_date'] ?? null,
            ];
            $monthlyRows[] = $row;
        }

        $monthlySync = $this->syncInventoryMonthlyStockFromDailyRows(
            'DIVISION',
            $identity,
            $monthlyRows,
            [],
            'Rebuilt from reconcile movement log ' . $asOfDate
        );
        if (!($monthlySync['ok'] ?? false)) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => (string)($monthlySync['message'] ?? 'Repair reconcile bahan gagal saat sinkron monthly stock.')];
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Repair reconcile bahan gagal saat sinkron ulang monthly stock dari movement log.'];
        }

        $this->db->trans_commit();
        return [
            'ok' => true,
            'message' => 'Identity bahan berhasil disinkronkan ulang dari movement log.',
            'data' => [
                'days_rebuilt' => count($monthlyRows),
                'last_relevant_date' => (string)($movementRows[count($movementRows) - 1]['movement_date'] ?? $asOfDate),
            ],
        ];
    }

    private function find_division_material_compare_row(array $rows, array $filters): ?array
    {
        $divisionId = (int)($filters['division_id'] ?? 0);
        $itemId = (int)($filters['item_id'] ?? 0);
        $materialId = (int)($filters['material_id'] ?? 0);
        $destination = strtoupper(trim((string)($filters['destination'] ?? 'ALL')));

        foreach ($rows as $row) {
            if ((int)($row['division_id'] ?? 0) !== $divisionId) {
                continue;
            }
            if ($itemId > 0 && (int)($row['item_id'] ?? 0) !== $itemId) {
                continue;
            }
            if ($materialId > 0 && (int)($row['material_id'] ?? 0) !== $materialId) {
                continue;
            }
            if ($destination !== '' && $destination !== 'ALL' && strtoupper(trim((string)($row['destination_group'] ?? 'ALL'))) !== $destination) {
                continue;
            }
            return $row;
        }

        return null;
    }

    private function list_division_material_reconcile_movements(string $asOfDate, array $filters): array
    {
        if (!$this->db->table_exists('inv_stock_movement_log')) {
            return [];
        }

        $divisionId = (int)($filters['division_id'] ?? 0);
        $itemId = (int)($filters['item_id'] ?? 0);
        $materialId = (int)($filters['material_id'] ?? 0);
        $destinationFilter = $this->normalizeDestinationFilter((string)($filters['destination'] ?? 'ALL'));
        $hasDestinationType = $this->db->field_exists('destination_type', 'inv_stock_movement_log');
        $hasAdjustmentCategory = $this->db->field_exists('adjustment_category', 'inv_stock_movement_log');
        $hasAdjustmentReasonCode = $this->db->field_exists('adjustment_reason_code', 'inv_stock_movement_log');
        $destinationTypeSource = $hasDestinationType ? 'l.destination_type' : "'OTHER'";
        $openingSourceExpr = "CASE WHEN COALESCE(l.ref_table,'') IN ('inv_warehouse_stock_opening_snapshot','inv_division_stock_opening_snapshot') THEN 1 ELSE 0 END";
        $movementTypeLabelExpr = "CASE WHEN {$openingSourceExpr} = 1 THEN 'OPENING_STOK_AWAL' ELSE l.movement_type END";
        $adjustmentCategoryBaseExpr = $hasAdjustmentCategory
            ? 'l.adjustment_category'
            : "CASE
                WHEN l.movement_type IN ('DISCARDED_OUT','WASTE_OUT') THEN 'WASTE'
                WHEN l.movement_type = 'SPOIL_OUT' THEN 'SPOILAGE'
                WHEN l.movement_type = 'PROCESS_LOSS_OUT' THEN 'PROCESS_LOSS'
                WHEN l.movement_type = 'VARIANCE_OUT' THEN 'VARIANCE'
                WHEN l.movement_type = 'ADJUSTMENT_IN' THEN 'ADJUSTMENT_PLUS'
                WHEN l.movement_type = 'ADJUSTMENT' AND COALESCE(l.qty_content_delta, 0) >= 0 THEN 'ADJUSTMENT_PLUS'
                WHEN l.movement_type = 'ADJUSTMENT' AND COALESCE(l.qty_content_delta, 0) < 0 THEN 'VARIANCE'
                ELSE NULL
            END";
        $adjustmentReasonBaseExpr = $hasAdjustmentReasonCode ? 'l.adjustment_reason_code' : 'NULL';
        $adjustmentCategoryExpr = "CASE WHEN {$openingSourceExpr} = 1 THEN NULL ELSE ({$adjustmentCategoryBaseExpr}) END";
        $adjustmentReasonExpr = "CASE WHEN {$openingSourceExpr} = 1 THEN NULL ELSE ({$adjustmentReasonBaseExpr}) END";
        $destinationGroupExpr = "CASE WHEN COALESCE({$destinationTypeSource}, 'OTHER') IN ('BAR_EVENT','KITCHEN_EVENT') THEN 'EVENT' ELSE 'REGULER' END";

        $this->db
            ->select('l.id, l.movement_no, l.movement_date, l.division_id, l.destination_type, l.movement_type, l.ref_table, l.ref_id, l.receipt_id, l.receipt_line_id, l.item_id, COALESCE(l.material_id, i.material_id) AS material_id, l.buy_uom_id, l.content_uom_id, l.qty_buy_delta, l.qty_content_delta, l.qty_buy_after, l.qty_content_after, l.profile_key, l.profile_name, l.profile_brand, l.profile_description, l.profile_expired_date, l.profile_content_per_buy, l.profile_buy_uom_code, l.profile_content_uom_code, l.unit_cost, l.notes, l.created_at, i.item_code, i.item_name, m.material_code, m.material_name', false)
            ->select($destinationGroupExpr . ' AS destination_group', false)
            ->select($movementTypeLabelExpr . ' AS movement_type_label', false)
            ->select($adjustmentCategoryExpr . ' AS adjustment_category', false)
            ->select($adjustmentReasonExpr . ' AS adjustment_reason_code', false)
            ->from('inv_stock_movement_log l')
            ->join('mst_item i', 'i.id = l.item_id', 'left')
            ->join('mst_material m', 'm.id = COALESCE(l.material_id, i.material_id)', 'left')
            ->where('l.movement_scope', 'DIVISION')
            ->where('l.movement_date <=', $asOfDate);

        if ($divisionId > 0) {
            $this->db->where('l.division_id', $divisionId);
        }
        if ($itemId > 0) {
            $this->db->where('l.item_id', $itemId);
        }
        if ($materialId > 0) {
            $this->db->where('COALESCE(l.material_id, i.material_id) = ' . (int)$materialId, null, false);
        }
        if ($hasDestinationType && $destinationFilter !== null && $destinationFilter !== 'ALL') {
            if ($destinationFilter === 'REGULER') {
                $this->db->where_not_in('l.destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
            } elseif ($destinationFilter === 'EVENT') {
                $this->db->where_in('l.destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
            } else {
                $this->db->where('l.destination_type', $destinationFilter);
            }
        }

        $rows = $this->db
            ->order_by('l.movement_date', 'ASC')
            ->order_by('l.id', 'ASC')
            ->get()
            ->result_array();

        foreach ($rows as &$row) {
            $row['qty_buy_before'] = round((float)($row['qty_buy_after'] ?? 0) - (float)($row['qty_buy_delta'] ?? 0), 4);
            $row['qty_content_before'] = round((float)($row['qty_content_after'] ?? 0) - (float)($row['qty_content_delta'] ?? 0), 4);
            $row['mutation_value'] = round(abs((float)($row['qty_content_delta'] ?? 0)) * (float)($row['unit_cost'] ?? 0), 2);
            $bucket = $this->classify_division_material_reconcile_bucket($row);
            $row['source_bucket'] = (string)($bucket['code'] ?? 'OTHER');
            $row['source_bucket_label'] = (string)($bucket['label'] ?? 'Lainnya');
            $row['source_label'] = $this->format_division_material_reconcile_source_label($row);
        }
        unset($row);

        return $rows;
    }

    private function list_division_material_reconcile_identities(string $asOfDate, array $filters): array
    {
        if (!$this->db->table_exists('inv_stock_movement_log') && !$this->db->table_exists('inv_division_monthly_stock')) {
            return [];
        }

        $divisionId = (int)($filters['division_id'] ?? 0);
        $itemId = (int)($filters['item_id'] ?? 0);
        $materialId = (int)($filters['material_id'] ?? 0);
        $destinationFilter = $this->normalizeDestinationFilter((string)($filters['destination'] ?? 'ALL'));
        $rows = [];

        if ($this->db->table_exists('inv_stock_movement_log')) {
            $hasDestinationType = $this->db->field_exists('destination_type', 'inv_stock_movement_log');
            $this->db
                ->distinct()
                ->select('l.division_id, l.destination_type, l.item_id, COALESCE(l.material_id, i.material_id) AS material_id, l.buy_uom_id, l.content_uom_id, l.profile_key, l.profile_name, l.profile_brand, l.profile_description, l.profile_expired_date, l.profile_content_per_buy, l.profile_buy_uom_code, l.profile_content_uom_code', false)
                ->from('inv_stock_movement_log l')
                ->join('mst_item i', 'i.id = l.item_id', 'left')
                ->where('l.movement_scope', 'DIVISION')
                ->where('l.movement_date <=', $asOfDate);

            if ($divisionId > 0) {
                $this->db->where('l.division_id', $divisionId);
            }
            if ($itemId > 0) {
                $this->db->where('l.item_id', $itemId);
            }
            if ($materialId > 0) {
                $this->db->where('COALESCE(l.material_id, i.material_id) = ' . (int)$materialId, null, false);
            }
            if ($hasDestinationType && $destinationFilter !== null && $destinationFilter !== 'ALL') {
                if ($destinationFilter === 'REGULER') {
                    $this->db->where_not_in('l.destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
                } elseif ($destinationFilter === 'EVENT') {
                    $this->db->where_in('l.destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
                } else {
                    $this->db->where('l.destination_type', $destinationFilter);
                }
            }

            $rows = array_merge($rows, $this->db->get()->result_array());
        }

        if ($this->db->table_exists('inv_division_monthly_stock')) {
            $targetMonth = date('Y-m-01', strtotime($asOfDate));
            $this->db
                ->distinct()
                ->select('s.division_id, s.destination_type, s.item_id, COALESCE(s.material_id, i.material_id) AS material_id, s.buy_uom_id, s.content_uom_id, s.profile_key, s.profile_name, s.profile_brand, s.profile_description, s.profile_expired_date, s.profile_content_per_buy, s.profile_buy_uom_code, s.profile_content_uom_code', false)
                ->from('inv_division_monthly_stock s')
                ->join('mst_item i', 'i.id = s.item_id', 'left')
                ->where('s.month_key <=', $targetMonth);

            if ($divisionId > 0) {
                $this->db->where('s.division_id', $divisionId);
            }
            if ($itemId > 0) {
                $this->db->where('s.item_id', $itemId);
            }
            if ($materialId > 0) {
                $this->db->where('COALESCE(s.material_id, i.material_id) = ' . (int)$materialId, null, false);
            }
            if ($destinationFilter !== null && $destinationFilter !== 'ALL') {
                if ($destinationFilter === 'REGULER') {
                    $this->db->where_not_in('s.destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
                } elseif ($destinationFilter === 'EVENT') {
                    $this->db->where_in('s.destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
                } else {
                    $this->db->where('s.destination_type', $destinationFilter);
                }
            }

            $rows = array_merge($rows, $this->db->get()->result_array());
        }

        $results = [];
        $seen = [];
        foreach ($rows as $row) {
            $identity = [
                'division_id' => !empty($row['division_id']) ? (int)$row['division_id'] : null,
                'destination_type' => !empty($row['destination_type']) ? strtoupper((string)$row['destination_type']) : null,
                'stock_domain' => 'ITEM',
                'item_id' => (int)($row['item_id'] ?? 0),
                'material_id' => !empty($row['material_id']) ? (int)$row['material_id'] : null,
                'buy_uom_id' => (int)($row['buy_uom_id'] ?? 0),
                'content_uom_id' => (int)($row['content_uom_id'] ?? 0),
                'profile_key' => (string)($row['profile_key'] ?? ''),
                'profile_name' => $this->nullableString($row['profile_name'] ?? null),
                'profile_brand' => $this->nullableString($row['profile_brand'] ?? null),
                'profile_description' => $this->nullableString($row['profile_description'] ?? null),
                'profile_expired_date' => $this->normalizeDate((string)($row['profile_expired_date'] ?? '')),
                'profile_content_per_buy' => round((float)($row['profile_content_per_buy'] ?? 1), 6),
                'profile_buy_uom_code' => $this->nullableString($row['profile_buy_uom_code'] ?? null),
                'profile_content_uom_code' => $this->nullableString($row['profile_content_uom_code'] ?? null),
            ];

            $seenKey = implode('|', [
                (string)($identity['division_id'] ?? 0),
                strtoupper((string)($identity['destination_type'] ?? 'OTHER')),
                (string)($identity['item_id'] ?? 0),
                (string)($identity['material_id'] ?? 0),
                (string)($identity['buy_uom_id'] ?? 0),
                (string)($identity['content_uom_id'] ?? 0),
                trim((string)($identity['profile_key'] ?? '')),
            ]);
            if (isset($seen[$seenKey])) {
                continue;
            }
            $seen[$seenKey] = true;

            $identity['_start_date'] = $this->resolve_division_material_reconcile_identity_start_date($asOfDate, $identity);
            $results[] = $identity;
        }

        return $results;
    }

    private function resolve_division_material_reconcile_identity_start_date(string $asOfDate, array $identity): string
    {
        $this->db->select('MIN(l.movement_date) AS min_date', false)
            ->from('inv_stock_movement_log l')
            ->join('mst_item i', 'i.id = l.item_id', 'left')
            ->where('l.movement_scope', 'DIVISION')
            ->where('l.movement_date <=', $asOfDate);
        if (!empty($identity['division_id'])) {
            $this->db->where('l.division_id', (int)$identity['division_id']);
        }
        if (!empty($identity['destination_type'])) {
            $this->db->where('l.destination_type', (string)$identity['destination_type']);
        }
        $this->db->where('l.item_id', (int)($identity['item_id'] ?? 0));
        if (!empty($identity['material_id'])) {
            $this->db->where('COALESCE(l.material_id, i.material_id) = ' . (int)$identity['material_id'], null, false);
        } else {
            $this->db->where('COALESCE(l.material_id, i.material_id) IS NULL', null, false);
        }
        $this->db->where('l.buy_uom_id', (int)($identity['buy_uom_id'] ?? 0));
        $this->db->where('l.content_uom_id', (int)($identity['content_uom_id'] ?? 0));
        $profileKey = trim((string)($identity['profile_key'] ?? ''));
        if ($profileKey !== '') {
            $this->db->where('l.profile_key', $profileKey);
        } else {
            $this->db->where('(l.profile_key IS NULL OR TRIM(l.profile_key) = \'\')', null, false);
        }

        $row = $this->db->limit(1)->get()->row_array();
        $minDate = $this->normalizeDate((string)($row['min_date'] ?? ''));
        return $minDate ?? $asOfDate;
    }

    private function classify_division_material_reconcile_bucket(array $row): array
    {
        $movementType = strtoupper(trim((string)($row['movement_type'] ?? '')));
        $movementLabel = strtoupper(trim((string)($row['movement_type_label'] ?? '')));
        $refTable = strtolower(trim((string)($row['ref_table'] ?? '')));
        $notes = strtolower(trim((string)($row['notes'] ?? '')));

        if ($movementLabel === 'OPENING_STOK_AWAL' || strpos($refTable, 'opening') !== false) {
            return ['code' => 'OPENING', 'label' => 'Opening'];
        }
        if (strpos($refTable, 'refund') !== false || strpos($notes, 'refund') !== false) {
            return ['code' => 'REFUND', 'label' => 'Refund'];
        }
        if (strpos($movementType, 'VOID') !== false || strpos($notes, 'void') !== false) {
            return ['code' => 'VOID', 'label' => 'Void'];
        }
        if ($refTable === 'pos_stock_commit' || strpos($notes, 'pos ') !== false) {
            return ['code' => 'POS', 'label' => 'POS'];
        }
        if (strpos($refTable, 'store_request') !== false || strpos($refTable, 'fulfillment') !== false) {
            return ['code' => 'SR', 'label' => 'SR'];
        }
        if (strpos($refTable, 'purchase_receipt') !== false || $movementType === 'PURCHASE_IN') {
            return ['code' => 'PO', 'label' => 'PO'];
        }
        if (!empty($row['adjustment_category']) || strpos($movementType, 'ADJUSTMENT') !== false || strpos($movementType, 'WASTE') !== false || strpos($movementType, 'SPOIL') !== false || strpos($movementType, 'LOSS') !== false || strpos($movementType, 'VARIANCE') !== false) {
            return ['code' => 'ADJUSTMENT', 'label' => 'Adjustment'];
        }
        return ['code' => 'OTHER', 'label' => 'Lainnya'];
    }

    private function format_division_material_reconcile_source_label(array $row): string
    {
        $refTable = strtolower(trim((string)($row['ref_table'] ?? '')));
        $refId = (int)($row['ref_id'] ?? 0);
        $map = [
            'pur_purchase_receipt' => 'Receipt PO',
            'pur_store_request_fulfillment' => 'Fulfillment SR',
            'pos_stock_commit' => 'POS Commit',
            'pos_refund' => 'Refund POS',
            'inv_division_stock_opening_snapshot' => 'Opening Snapshot',
            'inv_stock_adjustment' => 'Adjustment Stok',
        ];
        $label = $map[$refTable] ?? ($refTable !== '' ? strtoupper(str_replace('_', ' ', $refTable)) : '-');
        if ($refId > 0) {
            $label .= ' #' . $refId;
        }
        return $label;
    }

    private function aggregateDivisionMaterialCompareSource(array $rows, string $source): array
    {
        $map = [];
        foreach ($rows as $row) {
            $materialId = (int)($row['material_id'] ?? 0);
            if ($materialId <= 0) {
                continue;
            }
            $divisionId = (int)($row['division_id'] ?? 0);
            $destinationGroup = strtoupper(trim((string)($row['destination_group'] ?? 'REGULER')));
            $itemId = (int)($row['item_id'] ?? 0);
            $key = $divisionId . '|' . $destinationGroup . '|M-' . $materialId . '|I-' . $itemId;
            if (!isset($map[$key])) {
                $map[$key] = [
                    'qty_content' => 0.0,
                    'qty_pack' => 0.0,
                    '_meta' => [
                        'division_id' => $divisionId,
                        'division_code' => (string)($row['division_code'] ?? ''),
                        'division_name' => (string)($row['division_name'] ?? ''),
                        'destination_group' => $destinationGroup,
                        'destination_name' => (string)($row['destination_name'] ?? ($destinationGroup === 'EVENT' ? 'Event' : 'Reguler')),
                        'item_id' => $itemId,
                        'material_id' => $materialId,
                        'material_code' => (string)($row['material_code'] ?? ''),
                        'material_name' => (string)($row['material_name'] ?? ($row['item_name'] ?? '')),
                        'latest_date' => (string)($row['movement_date'] ?? ''),
                        'audit_has_mismatch' => 0,
                        'audit_mismatch_qty_content' => 0.0,
                        'audit_mismatch_notes' => '',
                    ],
                ];
            }

            $rowDate = (string)($row['movement_date'] ?? '');
            if ($rowDate !== '' && $rowDate >= (string)($map[$key]['_meta']['latest_date'] ?? '')) {
                $map[$key]['_meta']['latest_date'] = $rowDate;
            }
            if (!empty($row['audit_has_mismatch'])) {
                $map[$key]['_meta']['audit_has_mismatch'] = 1;
                $map[$key]['_meta']['audit_mismatch_qty_content'] = round(
                    (float)($map[$key]['_meta']['audit_mismatch_qty_content'] ?? 0) + (float)($row['audit_mismatch_qty_content'] ?? 0),
                    4
                );
                $noteParts = array_filter(array_map('trim', explode(',', (string)($row['audit_mismatch_notes'] ?? ''))));
                $existingParts = array_filter(array_map('trim', explode(',', (string)($map[$key]['_meta']['audit_mismatch_notes'] ?? ''))));
                $map[$key]['_meta']['audit_mismatch_notes'] = implode(', ', array_values(array_unique(array_merge($existingParts, $noteParts))));
            }

            if ($source === 'balance') {
                $map[$key]['qty_content'] += (float)($row['qty_content_balance'] ?? 0);
                $map[$key]['qty_pack'] += (float)($row['qty_buy_balance'] ?? 0);
            } elseif ($source === 'movement') {
                $map[$key]['qty_content'] += (float)($row['qty_content_after'] ?? 0);
                $map[$key]['qty_pack'] += (float)($row['qty_buy_after'] ?? 0);
            } else {
                $map[$key]['qty_content'] += (float)($row['closing_qty_content'] ?? 0);
                $map[$key]['qty_pack'] += (float)($row['closing_qty_pack'] ?? 0);
            }
        }

        return $map;
    }

    private function aggregateDivisionMaterialCompareRawDaily(array $rows): array
    {
        $latestIdentityRows = [];
        foreach ($rows as $row) {
            $materialId = (int)($row['material_id'] ?? 0);
            if ($materialId <= 0) {
                continue;
            }

            $identityKey = implode('|', [
                (int)($row['division_id'] ?? 0),
                strtoupper(trim((string)($row['destination_type'] ?? 'OTHER'))),
                (int)($row['item_id'] ?? 0),
                $materialId,
                (int)($row['buy_uom_id'] ?? 0),
                (int)($row['content_uom_id'] ?? 0),
                strtoupper(trim((string)($row['profile_key'] ?? ''))),
            ]);
            $rowDate = (string)($row['movement_date'] ?? '');
            $currentDate = (string)($latestIdentityRows[$identityKey]['movement_date'] ?? '');
            if (!isset($latestIdentityRows[$identityKey]) || $rowDate >= $currentDate) {
                $latestIdentityRows[$identityKey] = $row;
            }
        }

        $map = [];
        foreach ($latestIdentityRows as $row) {
            $materialId = (int)($row['material_id'] ?? 0);
            if ($materialId <= 0) {
                continue;
            }
            $divisionId = (int)($row['division_id'] ?? 0);
            $destinationGroup = strtoupper(trim((string)($row['destination_group'] ?? 'REGULER')));
            $itemId = (int)($row['item_id'] ?? 0);
            $key = $divisionId . '|' . $destinationGroup . '|M-' . $materialId . '|I-' . $itemId;

            $closingContent = round((float)($row['closing_qty_content'] ?? 0), 4);
            $packSize = (float)($row['profile_content_per_buy'] ?? 0);
            $closingPack = $packSize > 0 ? round($closingContent / $packSize, 4) : 0.0;

            if (!isset($map[$key])) {
                $map[$key] = [
                    'qty_content' => 0.0,
                    'qty_pack' => 0.0,
                    '_meta' => [
                        'division_id' => $divisionId,
                        'division_code' => (string)($row['division_code'] ?? ''),
                        'division_name' => (string)($row['division_name'] ?? ''),
                        'destination_group' => $destinationGroup,
                        'destination_name' => (string)($row['destination_name'] ?? ($destinationGroup === 'EVENT' ? 'Event' : 'Reguler')),
                        'item_id' => $itemId,
                        'material_id' => $materialId,
                        'material_code' => (string)($row['material_code'] ?? ''),
                        'material_name' => (string)($row['material_name'] ?? ($row['item_name'] ?? '')),
                        'latest_date' => (string)($row['movement_date'] ?? ''),
                    ],
                ];
            }
            $rowDate = (string)($row['movement_date'] ?? '');
            if ($rowDate !== '' && $rowDate >= (string)($map[$key]['_meta']['latest_date'] ?? '')) {
                $map[$key]['_meta']['latest_date'] = $rowDate;
            }
            $map[$key]['qty_content'] += $closingContent;
            $map[$key]['qty_pack'] += $closingPack;
        }

        return $map;
    }

    private function list_division_material_movement_closing(string $asOfDate, string $q, ?int $divisionId, ?string $destinationFilter = null): array
    {
        // Item-centric audit: jangan percaya qty_after legacy yang tersimpan bila
        // sudah tersedia rekonstruksi closing dari delta movement yang lebih sehat.
        // Namun bentuk row yang dikembalikan tetap harus mengikuti kontrak
        // source `movement`, yaitu memakai `qty_buy_after` / `qty_content_after`.
        $rows = $this->list_division_daily_snapshot_latest_closing_raw_movement($asOfDate, $q, $divisionId, $destinationFilter);
        foreach ($rows as &$row) {
            $row['qty_buy_after'] = round((float)($row['closing_qty_pack'] ?? 0), 4);
            $row['qty_content_after'] = round((float)($row['closing_qty_content'] ?? 0), 4);
        }
        unset($row);

        return $rows;
    }

    private function attachMaterialDailyProfilePrices(array $rows): array
    {
        if (empty($rows) || !$this->db->table_exists('mst_purchase_catalog')) {
            return $rows;
        }

        $profileKeys = [];
        foreach ($rows as $row) {
            $profileKey = trim((string)($row['profile_key'] ?? ''));
            if ($profileKey !== '') {
                $profileKeys[$profileKey] = true;
            }
        }
        if (empty($profileKeys)) {
            return $rows;
        }

        $useVendorLinkTable = $this->purchaseCatalogVendorTableExists();
        $latestVendorPriceSql = $useVendorLinkTable ? $this->latestCatalogVendorPriceSubquerySql() : '';
        $catalogFactorExpr = 'ROUND(COALESCE(NULLIF(c.content_per_buy, 0), NULLIF(c.conversion_factor_to_content, 0), 1), 6)';

        if ($useVendorLinkTable) {
            $standardPriceExpr = 'COALESCE(cvl.standard_price, cvl.last_unit_price, c.standard_price, c.last_unit_price)';
            $lastUnitPriceExpr = 'COALESCE(cvl.last_unit_price, cvl.standard_price, c.last_unit_price, c.standard_price)';
            $lastPurchaseDateExpr = 'COALESCE(cvl.last_purchase_date, c.last_purchase_date)';
        } else {
            $standardPriceExpr = 'COALESCE(c.standard_price, c.last_unit_price)';
            $lastUnitPriceExpr = 'COALESCE(c.last_unit_price, c.standard_price)';
            $lastPurchaseDateExpr = 'c.last_purchase_date';
        }

        $this->db
            ->select('c.profile_key, c.item_id, c.material_id, c.buy_uom_id, c.content_uom_id', false)
            ->select($catalogFactorExpr . ' AS profile_content_per_buy', false)
            ->select($standardPriceExpr . ' AS standard_price', false)
            ->select($lastUnitPriceExpr . ' AS last_unit_price', false)
            ->from('mst_purchase_catalog c')
            ->where('c.is_active', 1)
            ->where_in('c.profile_key', array_keys($profileKeys));

        if ($useVendorLinkTable) {
            $this->db->join('(' . $latestVendorPriceSql . ') cvl', 'cvl.catalog_id = c.id', 'left', false);
        }

        $catalogRows = $this->db
            ->order_by($lastPurchaseDateExpr, 'DESC', false)
            ->order_by('c.id', 'DESC')
            ->get()
            ->result_array();

        $priceMap = [];
        foreach ($catalogRows as $catalogRow) {
            $identity = implode('|', [
                strtoupper(trim((string)($catalogRow['profile_key'] ?? ''))),
                (int)($catalogRow['item_id'] ?? 0),
                (int)($catalogRow['material_id'] ?? 0),
                (int)($catalogRow['buy_uom_id'] ?? 0),
                (int)($catalogRow['content_uom_id'] ?? 0),
                round((float)($catalogRow['profile_content_per_buy'] ?? 0), 6),
            ]);
            if (!isset($priceMap[$identity])) {
                $priceMap[$identity] = [
                    'standard_price' => round((float)($catalogRow['standard_price'] ?? 0), 2),
                    'last_unit_price' => round((float)($catalogRow['last_unit_price'] ?? 0), 2),
                ];
            }
        }

        foreach ($rows as &$row) {
            $identity = implode('|', [
                strtoupper(trim((string)($row['profile_key'] ?? ''))),
                (int)($row['item_id'] ?? 0),
                (int)($row['material_id'] ?? 0),
                (int)($row['buy_uom_id'] ?? 0),
                (int)($row['content_uom_id'] ?? 0),
                round((float)($row['profile_content_per_buy'] ?? 0), 6),
            ]);
            $priceMeta = $priceMap[$identity] ?? null;
            $row['profile_standard_price'] = (float)($priceMeta['standard_price'] ?? 0);
            $row['profile_last_unit_price'] = (float)($priceMeta['last_unit_price'] ?? 0);
        }
        unset($row);

        return $rows;
    }

    public function list_stock_movements(string $scope, string $q, string $dateFrom, string $dateTo, ?int $divisionId, int $limit, ?string $destinationFilter = null): array
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
        $destinationFilter = $this->normalizeDestinationFilter($destinationFilter);
        $hasDestinationType = $this->db->field_exists('destination_type', 'inv_stock_movement_log');
        $hasAdjustmentCategory = $this->db->field_exists('adjustment_category', 'inv_stock_movement_log');
        $hasAdjustmentReasonCode = $this->db->field_exists('adjustment_reason_code', 'inv_stock_movement_log');
        $destinationTypeSource = $hasDestinationType ? 'l.destination_type' : "'OTHER'";
        $openingSourceExpr = "CASE WHEN COALESCE(l.ref_table,'') IN ('inv_warehouse_stock_opening_snapshot','inv_division_stock_opening_snapshot') THEN 1 ELSE 0 END";
        $movementTypeLabelExpr = "CASE WHEN {$openingSourceExpr} = 1 THEN 'OPENING_STOK_AWAL' ELSE l.movement_type END";
        $adjustmentCategoryBaseExpr = $hasAdjustmentCategory
            ? 'l.adjustment_category'
            : "CASE
                WHEN l.movement_type IN ('DISCARDED_OUT','WASTE_OUT') THEN 'WASTE'
                WHEN l.movement_type = 'SPOIL_OUT' THEN 'SPOILAGE'
                WHEN l.movement_type = 'PROCESS_LOSS_OUT' THEN 'PROCESS_LOSS'
                WHEN l.movement_type = 'VARIANCE_OUT' THEN 'VARIANCE'
                WHEN l.movement_type = 'ADJUSTMENT_IN' THEN 'ADJUSTMENT_PLUS'
                WHEN l.movement_type = 'ADJUSTMENT' AND COALESCE(l.qty_content_delta, 0) >= 0 THEN 'ADJUSTMENT_PLUS'
                WHEN l.movement_type = 'ADJUSTMENT' AND COALESCE(l.qty_content_delta, 0) < 0 THEN 'VARIANCE'
                ELSE NULL
            END";
        $adjustmentReasonBaseExpr = $hasAdjustmentReasonCode ? 'l.adjustment_reason_code' : 'NULL';
        $adjustmentCategoryExpr = "CASE WHEN {$openingSourceExpr} = 1 THEN NULL ELSE ({$adjustmentCategoryBaseExpr}) END";
        $adjustmentReasonExpr = "CASE WHEN {$openingSourceExpr} = 1 THEN NULL ELSE ({$adjustmentReasonBaseExpr}) END";

        $destinationGroupExpr = "CASE\n                WHEN COALESCE({$destinationTypeSource}, 'OTHER') IN ('BAR_EVENT','KITCHEN_EVENT') THEN 'EVENT'\n                ELSE 'REGULER'\n            END";
        $destinationNameExpr = "CASE COALESCE({$destinationTypeSource}, 'OTHER')\n                WHEN 'BAR' THEN 'Bar Reguler'\n                WHEN 'KITCHEN' THEN 'Kitchen Reguler'\n                WHEN 'BAR_EVENT' THEN 'Bar Event'\n                WHEN 'KITCHEN_EVENT' THEN 'Kitchen Event'\n                WHEN 'OFFICE' THEN 'Office Reguler'\n                WHEN 'GUDANG' THEN 'Gudang'\n                ELSE 'Reguler'\n            END";

        $divisionCodeColumn = $this->db->field_exists('division_code', 'mst_operational_division')
            ? 'division_code'
            : ($this->db->field_exists('code', 'mst_operational_division') ? 'code' : null);
        $divisionNameColumn = $this->db->field_exists('division_name', 'mst_operational_division')
            ? 'division_name'
            : ($this->db->field_exists('name', 'mst_operational_division') ? 'name' : null);
        $hasDivisionCode = $divisionCodeColumn !== null;
        $hasDivisionName = $divisionNameColumn !== null;
        $divisionCodeSelect = $hasDivisionCode ? ('dv.' . $divisionCodeColumn . ' AS division_code') : 'CAST(l.division_id AS CHAR) AS division_code';
        $divisionNameSelect = $hasDivisionName ? ('dv.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';

        $this->db
            ->select('l.id, l.movement_no, l.movement_date, l.movement_scope, l.division_id, ' . $divisionCodeSelect . ', ' . $divisionNameSelect, false)
            ->select($destinationTypeSource . ' AS destination_type', false)
            ->select($destinationGroupExpr . ' AS destination_group', false)
            ->select($destinationNameExpr . ' AS destination_name', false)
            ->select($movementTypeLabelExpr . ' AS movement_type_label', false)
            ->select($adjustmentCategoryExpr . ' AS adjustment_category', false)
            ->select($adjustmentReasonExpr . ' AS adjustment_reason_code', false)
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
        if ($scope === 'DIVISION' && $destinationFilter !== null && $destinationFilter !== 'ALL' && $hasDestinationType) {
            if ($destinationFilter === 'REGULER') {
                $this->db->where_not_in('l.destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
            } elseif ($destinationFilter === 'EVENT') {
                $this->db->where_in('l.destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
            } else {
                $this->db->where('l.destination_type', $destinationFilter);
            }
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
                $this->db->like('dv.' . $divisionCodeColumn, $q);
                if ($hasDivisionName) {
                    $this->db->or_like('dv.' . $divisionNameColumn, $q);
                }
            } elseif ($hasDivisionName) {
                $this->db->like('dv.' . $divisionNameColumn, $q);
            }
            $this->db->or_like('l.movement_no', $q)
                ->or_like('l.movement_type', $q)
                ->or_like($movementTypeLabelExpr, $q, 'both', false)
                ->or_like('i.item_code', $q)
                ->or_like('i.item_name', $q)
                ->or_like('m.material_code', $q)
                ->or_like('m.material_name', $q)
                ->or_like('l.profile_name', $q)
                ->or_like('l.profile_brand', $q)
                ->or_like('l.profile_description', $q)
                ->or_like($adjustmentCategoryExpr, $q, 'both', false)
                ->or_like($adjustmentReasonExpr, $q, 'both', false)
                ->or_like($destinationGroupExpr, $q, 'both', false)
                ->or_like($destinationNameExpr, $q, 'both', false)
                ->group_end();
        }

        return $this->db
            ->order_by('l.movement_date', 'DESC')
            ->order_by('l.id', 'DESC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    public function list_stock_movement_cell_detail(array $payload): array
    {
        if (!$this->db->table_exists('inv_stock_movement_log')) {
            return [];
        }

        $scope = strtoupper(trim((string)($payload['scope'] ?? 'WAREHOUSE')));
        if (!in_array($scope, ['WAREHOUSE', 'DIVISION'], true)) {
            $scope = 'WAREHOUSE';
        }

        $movementDate = $this->normalizeDate((string)($payload['movement_date'] ?? ''));
        if ($movementDate === null) {
            return [];
        }

        $divisionId = (int)($payload['division_id'] ?? 0);
        $destinationType = $this->normalizeDestinationFilter((string)($payload['destination_type'] ?? ''));
        $stockDomain = strtoupper(trim((string)($payload['stock_domain'] ?? '')));
        $itemId = (int)($payload['item_id'] ?? 0);
        $materialId = (int)($payload['material_id'] ?? 0);
        $buyUomId = (int)($payload['buy_uom_id'] ?? 0);
        $contentUomId = (int)($payload['content_uom_id'] ?? 0);
        $profileKey = trim((string)($payload['profile_key'] ?? ''));
        $limit = (int)($payload['limit'] ?? 200);
        if ($limit <= 0 || $limit > 1000) {
            $limit = 200;
        }

        $divisionCodeColumn = $this->db->field_exists('division_code', 'mst_operational_division')
            ? 'division_code'
            : ($this->db->field_exists('code', 'mst_operational_division') ? 'code' : null);
        $divisionNameColumn = $this->db->field_exists('division_name', 'mst_operational_division')
            ? 'division_name'
            : ($this->db->field_exists('name', 'mst_operational_division') ? 'name' : null);
        $hasDivisionCode = $divisionCodeColumn !== null;
        $hasDivisionName = $divisionNameColumn !== null;
        $hasDestinationType = $this->db->field_exists('destination_type', 'inv_stock_movement_log');
        $hasAdjustmentCategory = $this->db->field_exists('adjustment_category', 'inv_stock_movement_log');
        $hasAdjustmentReasonCode = $this->db->field_exists('adjustment_reason_code', 'inv_stock_movement_log');
        $destinationTypeSource = $hasDestinationType ? 'l.destination_type' : "'OTHER'";
        $openingSourceExpr = "CASE WHEN COALESCE(l.ref_table,'') IN ('inv_warehouse_stock_opening_snapshot','inv_division_stock_opening_snapshot') THEN 1 ELSE 0 END";
        $movementTypeLabelExpr = "CASE WHEN {$openingSourceExpr} = 1 THEN 'OPENING_STOK_AWAL' ELSE l.movement_type END";
        $adjustmentCategoryBaseExpr = $hasAdjustmentCategory
            ? 'l.adjustment_category'
            : "CASE
                WHEN l.movement_type IN ('DISCARDED_OUT','WASTE_OUT') THEN 'WASTE'
                WHEN l.movement_type = 'SPOIL_OUT' THEN 'SPOILAGE'
                WHEN l.movement_type = 'PROCESS_LOSS_OUT' THEN 'PROCESS_LOSS'
                WHEN l.movement_type = 'VARIANCE_OUT' THEN 'VARIANCE'
                WHEN l.movement_type = 'ADJUSTMENT_IN' THEN 'ADJUSTMENT_PLUS'
                WHEN l.movement_type = 'ADJUSTMENT' AND COALESCE(l.qty_content_delta, 0) >= 0 THEN 'ADJUSTMENT_PLUS'
                WHEN l.movement_type = 'ADJUSTMENT' AND COALESCE(l.qty_content_delta, 0) < 0 THEN 'VARIANCE'
                ELSE NULL
            END";
        $adjustmentReasonBaseExpr = $hasAdjustmentReasonCode ? 'l.adjustment_reason_code' : 'NULL';
        $adjustmentCategoryExpr = "CASE WHEN {$openingSourceExpr} = 1 THEN NULL ELSE ({$adjustmentCategoryBaseExpr}) END";
        $adjustmentReasonExpr = "CASE WHEN {$openingSourceExpr} = 1 THEN NULL ELSE ({$adjustmentReasonBaseExpr}) END";
        $destinationGroupExpr = "CASE\n                WHEN COALESCE({$destinationTypeSource}, 'OTHER') IN ('BAR_EVENT','KITCHEN_EVENT') THEN 'EVENT'\n                ELSE 'REGULER'\n            END";
        $destinationNameExpr = "CASE COALESCE({$destinationTypeSource}, 'OTHER')\n                WHEN 'BAR' THEN 'Bar Reguler'\n                WHEN 'KITCHEN' THEN 'Kitchen Reguler'\n                WHEN 'BAR_EVENT' THEN 'Bar Event'\n                WHEN 'KITCHEN_EVENT' THEN 'Kitchen Event'\n                WHEN 'OFFICE' THEN 'Office Reguler'\n                WHEN 'GUDANG' THEN 'Gudang'\n                ELSE 'Reguler'\n            END";
        $divisionCodeSelect = $hasDivisionCode ? ('dv.' . $divisionCodeColumn . ' AS division_code') : 'CAST(l.division_id AS CHAR) AS division_code';
        $divisionNameSelect = $hasDivisionName ? ('dv.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';

        $this->db
            ->select('l.id, l.movement_no, l.movement_date, l.movement_scope, l.division_id, ' . $divisionCodeSelect . ', ' . $divisionNameSelect, false)
            ->select($destinationTypeSource . ' AS destination_type', false)
            ->select($destinationGroupExpr . ' AS destination_group', false)
            ->select($destinationNameExpr . ' AS destination_name', false)
            ->select($movementTypeLabelExpr . ' AS movement_type_label', false)
            ->select($adjustmentCategoryExpr . ' AS adjustment_category', false)
            ->select($adjustmentReasonExpr . ' AS adjustment_reason_code', false)
            ->select('l.movement_type, l.item_id, l.material_id, l.buy_uom_id, l.content_uom_id')
            ->select('l.profile_key, l.profile_name, l.profile_brand, l.profile_description')
            ->select('l.profile_content_per_buy, l.profile_buy_uom_code, l.profile_content_uom_code')
            ->select('l.qty_buy_delta, l.qty_content_delta, l.qty_buy_after, l.qty_content_after, l.unit_cost')
            ->select('l.ref_table, l.ref_id, l.receipt_id, l.receipt_line_id, l.notes, l.created_at')
            ->select('i.item_code, i.item_name, m.material_code, m.material_name')
            ->from('inv_stock_movement_log l')
            ->join('mst_operational_division dv', 'dv.id = l.division_id', 'left')
            ->join('mst_item i', 'i.id = l.item_id', 'left')
            ->join('mst_material m', 'm.id = l.material_id', 'left')
            ->where('l.movement_scope', $scope)
            ->where('l.movement_date', $movementDate);

        if ($scope === 'DIVISION' && $divisionId > 0) {
            $this->db->where('l.division_id', $divisionId);
        }
        if ($scope === 'DIVISION' && $destinationType !== null && $destinationType !== 'ALL' && $hasDestinationType) {
            if ($destinationType === 'REGULER') {
                $this->db->where_not_in('l.destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
            } elseif ($destinationType === 'EVENT') {
                $this->db->where_in('l.destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
            } else {
                $this->db->where('l.destination_type', $destinationType);
            }
        }

        if ($itemId > 0 && $materialId > 0) {
            $this->db->group_start()
                ->where('l.item_id', $itemId)
                ->or_group_start()
                    ->where('l.material_id', $materialId)
                    ->where('l.item_id IS NULL', null, false)
                ->group_end()
                ->group_end();
        } elseif ($itemId > 0) {
            $this->db->where('l.item_id', $itemId);
            if ($materialId > 0) {
                $this->db->where('l.material_id', $materialId);
            }
        } else {
            if ($materialId > 0) {
                $this->db->where('l.material_id', $materialId);
            }
        }

        if ($contentUomId > 0) {
            $this->db->where('l.content_uom_id', $contentUomId);
        }
        if ($buyUomId > 0) {
            $this->db->where('l.buy_uom_id', $buyUomId);
        }
        if ($profileKey !== '') {
            $this->db->where('l.profile_key', $profileKey);
        }

        return $this->db
            ->order_by('l.created_at', 'ASC')
            ->order_by('l.id', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    public function list_fifo_lot_audit(array $filters, int $limit = 200): array
    {
        if (!$this->db->table_exists('inv_material_fifo_lot')) {
            return [];
        }

        $scope = strtoupper(trim((string)($filters['scope'] ?? 'ALL')));
        if (!in_array($scope, ['ALL', 'WAREHOUSE', 'DIVISION'], true)) {
            $scope = 'ALL';
        }
        $status = strtoupper(trim((string)($filters['status'] ?? 'OPEN')));
        if (!in_array($status, ['ALL', 'OPEN', 'CLOSED'], true)) {
            $status = 'OPEN';
        }

        $q = trim((string)($filters['q'] ?? ''));
        $from = $this->normalizeDate((string)($filters['date_from'] ?? ''));
        $to = $this->normalizeDate((string)($filters['date_to'] ?? ''));
        $divisionId = (int)($filters['division_id'] ?? 0);
        $itemId = (int)($filters['item_id'] ?? 0);
        $materialId = (int)($filters['material_id'] ?? 0);
        $profileKey = trim((string)($filters['profile_key'] ?? ''));
        $destinationFilter = $this->normalizeDestinationFilter((string)($filters['destination'] ?? $filters['destination_type'] ?? ''));
        if ($limit <= 0 || $limit > 500) {
            $limit = 200;
        }

        $divisionCodeColumn = $this->db->field_exists('division_code', 'mst_operational_division')
            ? 'division_code'
            : ($this->db->field_exists('code', 'mst_operational_division') ? 'code' : null);
        $divisionNameColumn = $this->db->field_exists('division_name', 'mst_operational_division')
            ? 'division_name'
            : ($this->db->field_exists('name', 'mst_operational_division') ? 'name' : null);
        $divisionCodeSelect = $divisionCodeColumn !== null ? ('dv.' . $divisionCodeColumn . ' AS division_code') : 'CAST(l.division_id AS CHAR) AS division_code';
        $divisionNameSelect = $divisionNameColumn !== null ? ('dv.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';
        $destinationExpr = "CASE WHEN l.location_scope = 'WAREHOUSE' AND l.destination_type IS NULL THEN 'GUDANG' ELSE COALESCE(l.destination_type, 'OTHER') END";
        $destinationNameExpr = "CASE " . $destinationExpr . "
                WHEN 'BAR' THEN 'Bar Reguler'
                WHEN 'KITCHEN' THEN 'Kitchen Reguler'
                WHEN 'BAR_EVENT' THEN 'Bar Event'
                WHEN 'KITCHEN_EVENT' THEN 'Kitchen Event'
                WHEN 'OFFICE' THEN 'Office Reguler'
                WHEN 'GUDANG' THEN 'Gudang'
                ELSE 'Reguler'
            END";
        $hasReceiptTable = $this->db->table_exists('pur_purchase_receipt');
        $hasFulfillmentTable = $this->db->table_exists('pur_store_request_fulfillment');
        $hasComponentBatchTable = $this->db->table_exists('inv_component_batch');
        $hasDivisionMonthlyStock = $this->db->table_exists('inv_division_monthly_stock');

        $divisionMonthlySubquery = '';
        $divisionLotAggregateSubquery = '';
        $divisionMonthlyOn = '';
        $divisionLotAggregateOn = '';
        if ($hasDivisionMonthlyStock) {
            $divisionMonthlySubquery = "
                (
                    SELECT
                        s.division_id,
                        COALESCE(s.destination_type, 'OTHER') AS destination_type,
                        s.item_id,
                        COALESCE(s.material_id, i.material_id) AS material_id,
                        s.buy_uom_id,
                        s.content_uom_id,
                        COALESCE(s.profile_key, '') AS profile_key,
                        ROUND(SUM(COALESCE(s.closing_qty_buy, 0)), 4) AS closing_qty_buy,
                        ROUND(SUM(COALESCE(s.closing_qty_content, 0)), 4) AS closing_qty_content,
                        MAX(ROUND(COALESCE(NULLIF(s.profile_content_per_buy, 0), 1), 6)) AS profile_content_per_buy
                    FROM inv_division_monthly_stock s
                    LEFT JOIN mst_item i ON i.id = s.item_id
                    WHERE COALESCE(s.material_id, i.material_id, 0) > 0
                    GROUP BY
                        s.division_id,
                        COALESCE(s.destination_type, 'OTHER'),
                        s.item_id,
                        COALESCE(s.material_id, i.material_id),
                        s.buy_uom_id,
                        s.content_uom_id,
                        COALESCE(s.profile_key, '')
                ) dms
            ";
            $divisionLotAggregateSubquery = "
                (
                    SELECT
                        x.division_id,
                        COALESCE(x.destination_type, 'OTHER') AS destination_type,
                        x.item_id,
                        COALESCE(x.material_id, ix.material_id) AS material_id,
                        x.buy_uom_id,
                        x.content_uom_id,
                        COALESCE(x.profile_key, '') AS profile_key,
                        ROUND(SUM(COALESCE(x.qty_balance, 0)), 4) AS identity_lot_qty_balance
                    FROM inv_material_fifo_lot x
                    LEFT JOIN mst_item ix ON ix.id = x.item_id
                    WHERE x.location_scope = 'DIVISION'
                      AND COALESCE(x.material_id, ix.material_id, 0) > 0
                    GROUP BY
                        x.division_id,
                        COALESCE(x.destination_type, 'OTHER'),
                        x.item_id,
                        COALESCE(x.material_id, ix.material_id),
                        x.buy_uom_id,
                        x.content_uom_id,
                        COALESCE(x.profile_key, '')
                ) dlg
            ";
            $divisionMonthlyOn = "
                dms.division_id = l.division_id
                AND dms.destination_type = " . $destinationExpr . "
                AND dms.item_id <=> l.item_id
                AND dms.material_id <=> COALESCE(l.material_id, i.material_id)
                AND dms.buy_uom_id <=> l.buy_uom_id
                AND dms.content_uom_id <=> l.content_uom_id
                AND dms.profile_key <=> COALESCE(l.profile_key, '')
            ";
            $divisionLotAggregateOn = "
                dlg.division_id = l.division_id
                AND dlg.destination_type = " . $destinationExpr . "
                AND dlg.item_id <=> l.item_id
                AND dlg.material_id <=> COALESCE(l.material_id, i.material_id)
                AND dlg.buy_uom_id <=> l.buy_uom_id
                AND dlg.content_uom_id <=> l.content_uom_id
                AND dlg.profile_key <=> COALESCE(l.profile_key, '')
            ";
        }

        $this->db
            ->select('l.id, l.lot_no, l.location_scope, l.receipt_date, l.expiry_date, l.division_id, l.item_id, l.material_id, l.buy_uom_id, l.content_uom_id, l.profile_key')
            ->select('l.qty_in, l.qty_out, l.qty_balance, l.unit_cost, l.source_table, l.source_id, l.source_line_id, l.receipt_id, l.receipt_line_id, l.parent_lot_id, l.status, l.created_at, l.updated_at')
            ->select($divisionCodeSelect . ', ' . $divisionNameSelect, false)
            ->select($destinationExpr . ' AS destination_type', false)
            ->select($destinationNameExpr . ' AS destination_name', false)
            ->select('i.item_code, i.item_name, m.material_code, m.material_name, bu.code AS buy_uom_code, cu.code AS content_uom_code', false)
            ->select('pl.lot_no AS parent_lot_no, pl.receipt_date AS parent_receipt_date', false)
            ->select('COUNT(il.id) AS issue_line_count, COALESCE(SUM(il.qty_out), 0) AS issue_qty_total', false)
            ->from('inv_material_fifo_lot l')
            ->join('mst_operational_division dv', 'dv.id = l.division_id', 'left')
            ->join('mst_item i', 'i.id = l.item_id', 'left')
            ->join('mst_material m', 'm.id = l.material_id', 'left')
            ->join('mst_uom bu', 'bu.id = l.buy_uom_id', 'left')
            ->join('mst_uom cu', 'cu.id = l.content_uom_id', 'left')
            ->join('inv_material_fifo_lot pl', 'pl.id = l.parent_lot_id', 'left')
            ->join('inv_material_fifo_issue_line il', 'il.lot_id = l.id', 'left');

        if ($hasDivisionMonthlyStock) {
            $this->db
                ->select('COALESCE(dms.profile_content_per_buy, 1) AS identity_content_per_buy', false)
                ->select('COALESCE(dms.closing_qty_buy, 0) AS identity_closing_qty_buy', false)
                ->select('COALESCE(dms.closing_qty_content, 0) AS identity_closing_qty_content', false)
                ->select('COALESCE(dlg.identity_lot_qty_balance, 0) AS identity_lot_qty_balance', false)
                ->join($divisionMonthlySubquery, $divisionMonthlyOn, 'left', false)
                ->join($divisionLotAggregateSubquery, $divisionLotAggregateOn, 'left', false);
        } else {
            $this->db
                ->select('1 AS identity_content_per_buy', false)
                ->select('0 AS identity_closing_qty_buy, 0 AS identity_closing_qty_content, 0 AS identity_lot_qty_balance', false);
        }

        if ($hasReceiptTable) {
            $this->db
                ->select('pr.purchase_order_id AS receipt_purchase_order_id, pr.receipt_no AS receipt_no', false)
                ->join('pur_purchase_receipt pr', "pr.id = IF(l.receipt_id IS NOT NULL AND l.receipt_id > 0, l.receipt_id, l.source_id) AND l.source_table = 'pur_purchase_receipt'", 'left', false);
        } else {
            $this->db->select('NULL AS receipt_purchase_order_id, NULL AS receipt_no', false);
        }

        if ($hasFulfillmentTable) {
            $this->db
                ->select('sf.store_request_id AS source_store_request_id, sf.fulfillment_no AS source_fulfillment_no', false)
                ->join('pur_store_request_fulfillment sf', "sf.id = l.source_id AND l.source_table = 'pur_store_request_fulfillment'", 'left', false);
        } else {
            $this->db->select('NULL AS source_store_request_id, NULL AS source_fulfillment_no', false);
        }

        if ($hasComponentBatchTable) {
            $this->db
                ->select('cb.batch_no AS source_batch_no', false)
                ->join('inv_component_batch cb', "cb.id = l.source_id AND l.source_table = 'inv_component_batch'", 'left', false);
        } else {
            $this->db->select('NULL AS source_batch_no', false);
        }

        if ($scope !== 'ALL') {
            $this->db->where('l.location_scope', $scope);
        }
        if ($status !== 'ALL') {
            $this->db->where('l.status', $status);
        }
        if ($from !== null) {
            $this->db->where('l.receipt_date >=', $from);
        }
        if ($to !== null) {
            $this->db->where('l.receipt_date <=', $to);
        }
        if ($divisionId > 0) {
            $this->db->where('l.division_id', $divisionId);
        }
        if ($itemId > 0) {
            $this->db->where('l.item_id', $itemId);
        }
        if ($materialId > 0) {
            $this->db->where('l.material_id', $materialId);
        }
        if ($profileKey !== '') {
            $this->db->where('l.profile_key', $profileKey);
        }
        if ($destinationFilter !== null && $destinationFilter !== 'ALL') {
            if ($destinationFilter === 'REGULER') {
                $this->db->where($destinationExpr . " NOT IN ('BAR_EVENT','KITCHEN_EVENT')", null, false);
            } elseif ($destinationFilter === 'EVENT') {
                $this->db->where($destinationExpr . " IN ('BAR_EVENT','KITCHEN_EVENT')", null, false);
            } else {
                $this->db->where($destinationExpr . ' = ' . $this->db->escape($destinationFilter), null, false);
            }
        }

        if ($q !== '') {
            $this->db->group_start()
                ->like('l.lot_no', $q)
                ->or_like('l.profile_key', $q)
                ->or_like('i.item_code', $q)
                ->or_like('i.item_name', $q)
                ->or_like('m.material_code', $q)
                ->or_like('m.material_name', $q)
                ->or_like('pl.lot_no', $q)
                ->or_like('l.source_table', $q)
                ->group_end();
        }

        return $this->db
            ->group_by('l.id')
            ->order_by('l.qty_balance', 'DESC')
            ->order_by('l.receipt_date', 'ASC')
            ->order_by('l.id', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    public function list_fifo_issue_audit(array $filters, int $limit = 100): array
    {
        if (!$this->db->table_exists('inv_material_fifo_issue_log')) {
            return [];
        }

        $scope = strtoupper(trim((string)($filters['scope'] ?? 'ALL')));
        if (!in_array($scope, ['ALL', 'WAREHOUSE', 'DIVISION'], true)) {
            $scope = 'ALL';
        }
        $status = strtoupper(trim((string)($filters['status'] ?? 'POSTED')));
        if (!in_array($status, ['ALL', 'POSTED', 'VOID'], true)) {
            $status = 'POSTED';
        }

        $q = trim((string)($filters['q'] ?? ''));
        $from = $this->normalizeDate((string)($filters['date_from'] ?? ''));
        $to = $this->normalizeDate((string)($filters['date_to'] ?? ''));
        $divisionId = (int)($filters['division_id'] ?? 0);
        $destinationFilter = $this->normalizeDestinationFilter((string)($filters['destination'] ?? $filters['destination_type'] ?? ''));
        if ($limit <= 0 || $limit > 500) {
            $limit = 100;
        }

        $divisionCodeColumn = $this->db->field_exists('division_code', 'mst_operational_division')
            ? 'division_code'
            : ($this->db->field_exists('code', 'mst_operational_division') ? 'code' : null);
        $divisionNameColumn = $this->db->field_exists('division_name', 'mst_operational_division')
            ? 'division_name'
            : ($this->db->field_exists('name', 'mst_operational_division') ? 'name' : null);
        $hasDivisionCode = $divisionCodeColumn !== null;
        $hasDivisionName = $divisionNameColumn !== null;
        $sourceDivisionCodeSelect = $hasDivisionCode ? ('dvs.' . $divisionCodeColumn . ' AS source_division_code') : 'CAST(l.division_id AS CHAR) AS source_division_code';
        $sourceDivisionNameSelect = $hasDivisionName ? ('dvs.' . $divisionNameColumn . ' AS source_division_name') : 'NULL AS source_division_name';
        $targetDivisionCodeSelect = $hasDivisionCode ? ('dvt.' . $divisionCodeColumn . ' AS target_division_code') : 'CAST(l.target_division_id AS CHAR) AS target_division_code';
        $targetDivisionNameSelect = $hasDivisionName ? ('dvt.' . $divisionNameColumn . ' AS target_division_name') : 'NULL AS target_division_name';

        $effectiveDivisionExpr = "CASE WHEN l.target_scope = 'DIVISION' AND l.target_division_id IS NOT NULL THEN l.target_division_id ELSE l.division_id END";
        $effectiveDestinationExpr = "CASE WHEN l.target_scope = 'DIVISION' AND l.target_destination_type IS NOT NULL THEN l.target_destination_type ELSE COALESCE(l.destination_type, 'OTHER') END";
        $sourceDestinationNameExpr = "CASE COALESCE(l.destination_type, 'OTHER')
                WHEN 'BAR' THEN 'Bar Reguler'
                WHEN 'KITCHEN' THEN 'Kitchen Reguler'
                WHEN 'BAR_EVENT' THEN 'Bar Event'
                WHEN 'KITCHEN_EVENT' THEN 'Kitchen Event'
                WHEN 'OFFICE' THEN 'Office Reguler'
                WHEN 'GUDANG' THEN 'Gudang'
                ELSE 'Reguler'
            END";
        $targetDestinationNameExpr = "CASE COALESCE(l.target_destination_type, 'OTHER')
                WHEN 'BAR' THEN 'Bar Reguler'
                WHEN 'KITCHEN' THEN 'Kitchen Reguler'
                WHEN 'BAR_EVENT' THEN 'Bar Event'
                WHEN 'KITCHEN_EVENT' THEN 'Kitchen Event'
                WHEN 'OFFICE' THEN 'Office Reguler'
                WHEN 'GUDANG' THEN 'Gudang'
                ELSE 'Reguler'
            END";

        $hasIssueLineTable = $this->db->table_exists('inv_material_fifo_issue_line');

        $this->db
            ->select('l.id, l.issue_no, l.issue_date, l.issue_datetime, l.location_scope, l.division_id, l.destination_type, l.target_scope, l.target_division_id, l.target_destination_type')
            ->select('l.item_id, l.material_id, l.buy_uom_id, l.content_uom_id, l.profile_key, l.issue_qty, l.total_cost')
            ->select('l.source_module, l.source_table, l.source_id, l.source_line_id, l.notes, l.status, l.voided_at, l.created_at')
            ->select($sourceDivisionCodeSelect . ', ' . $sourceDivisionNameSelect . ', ' . $targetDivisionCodeSelect . ', ' . $targetDivisionNameSelect, false)
            ->select($sourceDestinationNameExpr . ' AS source_destination_name', false)
            ->select($targetDestinationNameExpr . ' AS target_destination_name', false)
            ->select('i.item_code, i.item_name, m.material_code, m.material_name, cu.code AS content_uom_code', false)
            ->from('inv_material_fifo_issue_log l')
            ->join('mst_operational_division dvs', 'dvs.id = l.division_id', 'left')
            ->join('mst_operational_division dvt', 'dvt.id = l.target_division_id', 'left')
            ->join('mst_item i', 'i.id = l.item_id', 'left')
            ->join('mst_material m', 'm.id = l.material_id', 'left')
            ->join('mst_uom cu', 'cu.id = l.content_uom_id', 'left');

        if ($hasIssueLineTable) {
            $this->db
                ->select('COUNT(il.id) AS line_count, COALESCE(SUM(il.qty_out), 0) AS allocated_qty_total', false)
                ->join('inv_material_fifo_issue_line il', 'il.issue_id = l.id', 'left');
        } else {
            $this->db->select('0 AS line_count, 0 AS allocated_qty_total', false);
        }

        if ($scope !== 'ALL') {
            $this->db->where('l.location_scope', $scope);
        }
        if ($status !== 'ALL') {
            $this->db->where('l.status', $status);
        }
        if ($from !== null) {
            $this->db->where('l.issue_date >=', $from);
        }
        if ($to !== null) {
            $this->db->where('l.issue_date <=', $to);
        }
        if ($divisionId > 0) {
            $this->db->where($effectiveDivisionExpr . ' = ' . (int)$divisionId, null, false);
        }
        if ($destinationFilter !== null && $destinationFilter !== 'ALL') {
            if ($destinationFilter === 'REGULER') {
                $this->db->where($effectiveDestinationExpr . " NOT IN ('BAR_EVENT','KITCHEN_EVENT')", null, false);
            } elseif ($destinationFilter === 'EVENT') {
                $this->db->where($effectiveDestinationExpr . " IN ('BAR_EVENT','KITCHEN_EVENT')", null, false);
            } else {
                $this->db->where($effectiveDestinationExpr . ' = ' . $this->db->escape($destinationFilter), null, false);
            }
        }

        if ($q !== '') {
            $this->db->group_start()
                ->like('l.issue_no', $q)
                ->or_like('l.source_module', $q)
                ->or_like('l.source_table', $q)
                ->or_like('l.profile_key', $q)
                ->or_like('i.item_code', $q)
                ->or_like('i.item_name', $q)
                ->or_like('m.material_code', $q)
                ->or_like('m.material_name', $q)
                ->or_like('dvs.' . ($divisionNameColumn ?? 'id'), $q, 'both', $hasDivisionName === false)
                ->or_like('dvt.' . ($divisionNameColumn ?? 'id'), $q, 'both', $hasDivisionName === false)
                ->or_like('l.notes', $q)
                ->group_end();
        }

        if ($hasIssueLineTable) {
            $this->db->group_by('l.id');
        }

        $issues = $this->db
            ->order_by('l.issue_date', 'DESC')
            ->order_by('l.id', 'DESC')
            ->limit($limit)
            ->get()
            ->result_array();

        if (empty($issues) || !$hasIssueLineTable) {
            return $issues;
        }

        $issueIds = array_values(array_filter(array_map(static function ($row) {
            return (int)($row['id'] ?? 0);
        }, $issues), static function ($id) {
            return $id > 0;
        }));

        if (empty($issueIds)) {
            return $issues;
        }

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

        $lineRows = $this->db
            ->select('il.id, il.issue_id, il.lot_id AS source_lot_id, il.target_lot_id, il.qty_out, il.unit_cost, il.total_cost')
            ->select($sourceBalanceBeforeSelect . ', ' . $sourceBalanceAfterSelect . ', ' . $targetBalanceBeforeSelect . ', ' . $targetBalanceAfterSelect, false)
            ->select('sl.lot_no AS source_lot_no, sl.receipt_date AS source_receipt_date, sl.expiry_date AS source_expiry_date, sl.qty_balance AS source_live_balance', false)
            ->select('tl.lot_no AS target_lot_no, tl.receipt_date AS target_receipt_date, tl.expiry_date AS target_expiry_date, tl.qty_balance AS target_live_balance', false)
            ->from('inv_material_fifo_issue_line il')
            ->join('inv_material_fifo_lot sl', 'sl.id = il.lot_id', 'left')
            ->join('inv_material_fifo_lot tl', 'tl.id = il.target_lot_id', 'left')
            ->where_in('il.issue_id', $issueIds)
            ->order_by('il.issue_id', 'ASC')
            ->order_by('il.id', 'ASC')
            ->get()
            ->result_array();

        $lineMap = [];
        foreach ($lineRows as $lineRow) {
            $lineMap[(int)($lineRow['issue_id'] ?? 0)][] = $lineRow;
        }

        foreach ($issues as &$issue) {
            $issue['line_rows'] = $lineMap[(int)($issue['id'] ?? 0)] ?? [];
        }
        unset($issue);

        return $issues;
    }

    public function fifo_lot_usage_detail(int $lotId): array
    {
        if ($lotId <= 0 || !$this->db->table_exists('inv_material_fifo_lot')) {
            return ['ok' => false, 'message' => 'Lot bahan baku tidak ditemukan.'];
        }

        $divisionCodeColumn = $this->db->field_exists('division_code', 'mst_operational_division')
            ? 'division_code'
            : ($this->db->field_exists('code', 'mst_operational_division') ? 'code' : null);
        $divisionNameColumn = $this->db->field_exists('division_name', 'mst_operational_division')
            ? 'division_name'
            : ($this->db->field_exists('name', 'mst_operational_division') ? 'name' : null);
        $divisionCodeSelect = $divisionCodeColumn !== null ? ('dv.' . $divisionCodeColumn . ' AS division_code') : 'CAST(l.division_id AS CHAR) AS division_code';
        $divisionNameSelect = $divisionNameColumn !== null ? ('dv.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';
        $destinationExpr = "CASE WHEN l.location_scope = 'WAREHOUSE' AND l.destination_type IS NULL THEN 'GUDANG' ELSE COALESCE(l.destination_type, 'OTHER') END";
        $destinationNameExpr = "CASE " . $destinationExpr . "
                WHEN 'BAR' THEN 'Bar Reguler'
                WHEN 'KITCHEN' THEN 'Kitchen Reguler'
                WHEN 'BAR_EVENT' THEN 'Bar Event'
                WHEN 'KITCHEN_EVENT' THEN 'Kitchen Event'
                WHEN 'OFFICE' THEN 'Office Reguler'
                WHEN 'GUDANG' THEN 'Gudang'
                ELSE 'Reguler'
            END";

        $header = $this->db
            ->select('l.*')
            ->select($divisionCodeSelect . ', ' . $divisionNameSelect, false)
            ->select($destinationExpr . ' AS destination_type', false)
            ->select($destinationNameExpr . ' AS destination_name', false)
            ->select('i.item_code, i.item_name, m.material_code, m.material_name, bu.code AS buy_uom_code, cu.code AS content_uom_code', false)
            ->from('inv_material_fifo_lot l')
            ->join('mst_operational_division dv', 'dv.id = l.division_id', 'left')
            ->join('mst_item i', 'i.id = l.item_id', 'left')
            ->join('mst_material m', 'm.id = l.material_id', 'left')
            ->join('mst_uom bu', 'bu.id = l.buy_uom_id', 'left')
            ->join('mst_uom cu', 'cu.id = l.content_uom_id', 'left')
            ->where('l.id', $lotId)
            ->limit(1)
            ->get()
            ->row_array();

        if (!$header) {
            return ['ok' => false, 'message' => 'Lot bahan baku tidak ditemukan.'];
        }

        if (!$this->db->table_exists('inv_material_fifo_issue_line') || !$this->db->table_exists('inv_material_fifo_issue_log')) {
            return [
                'ok' => true,
                'header' => $header,
                'summary' => ['usage_count' => 0, 'qty_out_total' => 0.0, 'cost_total' => 0.0],
                'rows' => [],
            ];
        }

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

        $rows = $this->db
            ->select('il.id, il.issue_id, il.lot_id AS source_lot_id, il.target_lot_id, il.qty_out, il.unit_cost, il.total_cost')
            ->select($sourceBalanceBeforeSelect . ', ' . $sourceBalanceAfterSelect . ', ' . $targetBalanceBeforeSelect . ', ' . $targetBalanceAfterSelect, false)
            ->select('ig.issue_no, ig.issue_date, ig.issue_datetime, ig.location_scope, ig.source_module, ig.source_table, ig.source_id, ig.source_line_id, ig.notes, ig.status', false)
            ->select('sl.lot_no AS source_lot_no, tl.lot_no AS target_lot_no', false)
            ->from('inv_material_fifo_issue_line il')
            ->join('inv_material_fifo_issue_log ig', 'ig.id = il.issue_id', 'inner')
            ->join('inv_material_fifo_lot sl', 'sl.id = il.lot_id', 'left')
            ->join('inv_material_fifo_lot tl', 'tl.id = il.target_lot_id', 'left')
            ->where('il.lot_id', $lotId)
            ->order_by('ig.issue_date', 'DESC')
            ->order_by('ig.id', 'DESC')
            ->order_by('il.id', 'DESC')
            ->get()
            ->result_array();

        $summary = [
            'usage_count' => count($rows),
            'qty_out_total' => 0.0,
            'cost_total' => 0.0,
        ];
        foreach ($rows as $row) {
            $summary['qty_out_total'] += (float)($row['qty_out'] ?? 0);
            $summary['cost_total'] += (float)($row['total_cost'] ?? 0);
        }

        return [
            'ok' => true,
            'header' => $header,
            'summary' => $summary,
            'rows' => $rows,
        ];
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
        $stockScope = strtoupper(trim((string)($payload['stock_scope'] ?? 'WAREHOUSE')));
        if (!in_array($stockScope, ['WAREHOUSE', 'DIVISION'], true)) {
            $stockScope = 'WAREHOUSE';
        }

        $openingTable = $this->openingSnapshotTableForScope($stockScope);
        if (!$this->db->table_exists($openingTable)) {
            return [
                'ok' => false,
                'message' => 'Tabel opening snapshot belum tersedia untuk scope ' . $stockScope . '. Jalankan SQL 2026-05-06f.',
            ];
        }

        $divisionId = null;
        $destinationType = null;
        if ($stockScope === 'DIVISION') {
            if (!$this->db->table_exists('inv_division_monthly_stock')) {
                return [
                    'ok' => false,
                    'message' => 'Tabel proyeksi stok divisi belum tersedia. Jalankan SQL inventory foundation terlebih dahulu.',
                ];
            }

            $divisionId = (int)($payload['division_id'] ?? 0);
            if ($divisionId <= 0) {
                return [
                    'ok' => false,
                    'message' => 'division_id wajib diisi untuk opening divisi.',
                ];
            }

            $destinationType = $this->normalizeDestination((string)($payload['destination_type'] ?? 'OTHER'));
            if ($destinationType === null) {
                $destinationType = 'OTHER';
            }
        }

        $itemId = (int)($payload['item_id'] ?? 0);
        $materialId = (int)($payload['material_id'] ?? 0);
        $buyUomId = (int)($payload['buy_uom_id'] ?? 0);
        $contentUomId = (int)($payload['content_uom_id'] ?? 0);
        $snapshotMonth = $this->normalizeMonth((string)($payload['snapshot_month'] ?? date('Y-m-01')));
        $movementDate = $snapshotMonth;
        $targetQtyBuy = round((float)($payload['opening_qty_buy'] ?? 0), 4);
        $targetQtyContent = round((float)($payload['opening_qty_content'] ?? 0), 4);
        $avgCost = round(max(0, (float)($payload['opening_avg_cost_per_content'] ?? 0)), 6);
        $replaceMode = (int)($payload['replace_mode'] ?? 1) === 1;

        if ($itemId <= 0 || $buyUomId <= 0 || $contentUomId <= 0 || $snapshotMonth === null) {
            return [
                'ok' => false,
                'message' => 'item_id, buy_uom_id, content_uom_id, snapshot_month wajib valid.',
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
        $profileDescription = $this->normalizeProfileDescription($payload['profile_description'] ?? null);
        $profileExpiredDate = $this->normalizeDate((string)($payload['profile_expired_date'] ?? ''));
        $profileContentPerBuy = round(max(0.000001, (float)($payload['profile_content_per_buy'] ?? 1)), 6);

        $itemHasMaterial = false;
        if ($itemId > 0 && $this->db->table_exists('mst_item') && $this->db->field_exists('material_id', 'mst_item')) {
            $itemRow = $this->db
                ->select('material_id')
                ->from('mst_item')
                ->where('id', $itemId)
                ->limit(1)
                ->get()
                ->row_array();
            $itemMaterialId = (int)($itemRow['material_id'] ?? 0);
            if ($itemMaterialId > 0) {
                $itemHasMaterial = true;
                if ($materialId <= 0) {
                    $materialId = $itemMaterialId;
                }
            }
        }

        $materialId = $materialId > 0 ? $materialId : null;
        if ($stockScope === 'DIVISION' && $materialId === null) {
            return [
                'ok' => false,
                'message' => 'Opening stok divisi wajib punya material_id. Gunakan item/profile bahan baku yang terhubung ke master material.',
            ];
        }
        $stockDomain = $this->resolveLegacyIdentityStockDomain(
            $itemId > 0 ? $itemId : null,
            $materialId,
            $payload['stock_domain'] ?? ($itemHasMaterial ? 'ITEM' : null)
        );

        $catalogUnitPrice = $profileContentPerBuy > 0
            ? round($avgCost * $profileContentPerBuy, 2)
            : round($avgCost, 2);

        $profileKey = null;
        // Canonicalize to catalog profile key when same identity exists in active catalog.
        // This avoids persisting stale stock-only keys for the same profile.
        $catalogProfileKey = $this->resolveCatalogProfileKeyByIdentity(
            $itemId,
            $materialId,
            $buyUomId,
            $contentUomId,
            $profileName,
            $profileBrand,
            $profileDescription,
            $profileExpiredDate,
            $profileContentPerBuy,
            $catalogUnitPrice
        );
        if ($catalogProfileKey === null) {
            $catalogProfileKey = $this->ensureCatalogProfileFromOpeningIdentity(
                $stockDomain,
                $itemId,
                $materialId,
                $buyUomId,
                $contentUomId,
                $profileName,
                $profileBrand,
                $profileDescription,
                $profileExpiredDate,
                $profileContentPerBuy,
                $catalogUnitPrice
            );
        }
        if ($catalogProfileKey !== null) {
            $profileKey = $catalogProfileKey;
        }
        if ($profileKey === null) {
            $profileKey = $this->resolveExistingOpeningProfileKey(
                $stockScope,
                $divisionId,
                $destinationType,
                $itemId,
                $materialId,
                $buyUomId,
                $contentUomId,
                $profileName,
                $profileBrand,
                $profileDescription,
                $profileExpiredDate,
                $profileContentPerBuy
            );
        }
        if ($profileKey === null) {
            $profileKey = hash('sha256', implode('|', [
                $stockDomain,
                (string)$itemId,
                (string)($materialId ?? 0),
                (string)$buyUomId,
                (string)$contentUomId,
                strtoupper((string)$profileName),
                strtoupper((string)$profileBrand),
                strtoupper((string)$profileDescription),
                number_format($profileContentPerBuy, 6, '.', ''),
                (string)($profileExpiredDate ?? ''),
            ]));
        }

        $buyUomCode = $this->resolveUomCode($buyUomId);
        $contentUomCode = $this->resolveUomCode($contentUomId);
        $openingHasDestinationType = $this->db->field_exists('destination_type', $openingTable);
        $openingHasProfileExpiredDate = $this->db->field_exists('profile_expired_date', $openingTable);

        $openingCriteria = [
            'snapshot_month' => $snapshotMonth,
            'division_id' => $divisionId,
            'destination_type' => $destinationType,
            'stock_domain' => $stockDomain,
            'item_id' => $itemId,
            'material_id' => $materialId,
            'buy_uom_id' => $buyUomId,
            'content_uom_id' => $contentUomId,
            'profile_key' => $profileKey,
            'profile_name' => $profileName,
            'profile_brand' => $profileBrand,
            'profile_description' => $profileDescription,
            'profile_expired_date' => $profileExpiredDate,
            'profile_content_per_buy' => $profileContentPerBuy,
            'opening_avg_cost_per_content' => $avgCost,
        ];

        $this->db->trans_begin();

        $existingSnapshot = $this->findOpeningSnapshotRowForUpdate($openingTable, $openingCriteria);
        if (empty($existingSnapshot) && $replaceMode) {
            $existingSnapshot = $this->findOpeningSnapshotReplaceCandidate($openingTable, $openingCriteria);
        }
        $previousHistoryIdentity = null;
        if (!empty($existingSnapshot['id'])) {
            $existingProfileKey = trim((string)($existingSnapshot['profile_key'] ?? ''));
            if ($existingProfileKey !== '' && $existingProfileKey !== $profileKey) {
                $previousHistoryIdentity = $this->buildOpeningSnapshotHistoryIdentity($stockScope, $existingSnapshot);
            }
        }
        $previousOpeningQtyBuy = round((float)($existingSnapshot['opening_qty_buy'] ?? 0), 4);
        $previousOpeningQtyContent = round((float)($existingSnapshot['opening_qty_content'] ?? 0), 4);
        $previousOpeningAvgCost = round((float)($existingSnapshot['opening_avg_cost_per_content'] ?? 0), 6);

        $snapshotQtyBuy = $replaceMode
            ? $targetQtyBuy
            : round($previousOpeningQtyBuy + $targetQtyBuy, 4);
        $snapshotQtyContent = $replaceMode
            ? $targetQtyContent
            : round($previousOpeningQtyContent + $targetQtyContent, 4);

        if ($snapshotQtyBuy < 0 || $snapshotQtyContent < 0) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => 'Hasil saldo opening tidak boleh negatif.',
            ];
        }

        $inputOpeningValue = round($targetQtyContent * $avgCost, 2);
        $previousOpeningValue = round($previousOpeningQtyContent * $previousOpeningAvgCost, 2);
        $snapshotTotalValue = $replaceMode
            ? $inputOpeningValue
            : round($previousOpeningValue + $inputOpeningValue, 2);
        $snapshotAvgCost = $snapshotQtyContent > 0
            ? round($snapshotTotalValue / max(0.000001, $snapshotQtyContent), 6)
            : 0.0;

        $snapshot = [
            'snapshot_month' => $snapshotMonth,
            'division_id' => $divisionId,
            'destination_type' => $destinationType,
            'stock_domain' => $stockDomain,
            'item_id' => $itemId,
            'material_id' => $materialId,
            'buy_uom_id' => $buyUomId,
            'content_uom_id' => $contentUomId,
            'profile_key' => $profileKey,
            'profile_name' => $profileName,
            'profile_brand' => $profileBrand,
            'profile_description' => $profileDescription,
            'profile_expired_date' => $profileExpiredDate,
            'profile_content_per_buy' => $profileContentPerBuy,
            'profile_buy_uom_code' => $buyUomCode,
            'profile_content_uom_code' => $contentUomCode,
            'opening_qty_buy' => $snapshotQtyBuy,
            'opening_qty_content' => $snapshotQtyContent,
            'opening_avg_cost_per_content' => $snapshotAvgCost,
            'opening_total_value' => $snapshotTotalValue,
            'source_type' => 'MANUAL',
            'notes' => $this->nullableString($payload['notes'] ?? null),
            'created_by' => $userId > 0 ? $userId : null,
        ];

        $existingOpeningColumns = $this->listTableFields($openingTable);
        $insertColumnsCandidate = [
            'snapshot_month', 'division_id', 'stock_domain', 'item_id', 'material_id', 'buy_uom_id', 'content_uom_id',
            'profile_key', 'profile_name', 'profile_brand', 'profile_description', 'profile_content_per_buy', 'profile_buy_uom_code',
            'profile_content_uom_code', 'opening_qty_buy', 'opening_qty_content', 'opening_avg_cost_per_content', 'opening_total_value',
            'source_type', 'notes', 'created_by',
        ];
        if ($openingHasDestinationType) {
            array_splice($insertColumnsCandidate, 3, 0, 'destination_type');
        }
        if ($openingHasProfileExpiredDate) {
            array_splice($insertColumnsCandidate, 13, 0, 'profile_expired_date');
        }

        $insertColumns = [];
        foreach ($insertColumnsCandidate as $col) {
            if (isset($existingOpeningColumns[$col])) {
                $insertColumns[] = $col;
            }
        }

        $insertValues = [];
        foreach ($insertColumns as $col) {
            $insertValues[] = array_key_exists($col, $snapshot) ? $snapshot[$col] : null;
        }

        $updateColumnsCandidate = [
            'profile_name',
            'profile_brand',
            'profile_description',
            'profile_content_per_buy',
            'profile_buy_uom_code',
            'profile_content_uom_code',
            'opening_qty_buy',
            'opening_qty_content',
            'opening_avg_cost_per_content',
            'opening_total_value',
            'source_type',
            'notes',
        ];
        if ($openingHasDestinationType) {
            array_unshift($updateColumnsCandidate, 'destination_type');
        }
        if ($openingHasProfileExpiredDate) {
            array_splice($updateColumnsCandidate, 4, 0, 'profile_expired_date');
        }

        $updateColumns = [];
        foreach ($updateColumnsCandidate as $col) {
            if (isset($existingOpeningColumns[$col])) {
                $updateColumns[] = $col;
            }
        }

        $updateParts = [];
        foreach ($updateColumns as $col) {
            $updateParts[] = $col . ' = VALUES(' . $col . ')';
        }
        $updateParts[] = 'updated_at = CURRENT_TIMESTAMP';

        if (!empty($existingSnapshot['id'])) {
            $updateData = [];
            foreach ($insertColumns as $col) {
                if (array_key_exists($col, $snapshot)) {
                    $updateData[$col] = $snapshot[$col];
                }
            }
            $this->db->where('id', (int)$existingSnapshot['id'])->update($openingTable, $updateData);
            $snapshotRow = $this->db->query('SELECT * FROM ' . $openingTable . ' WHERE id = ? LIMIT 1 FOR UPDATE', [(int)$existingSnapshot['id']])->row_array();
        } else {
            $placeholders = implode(',', array_fill(0, count($insertColumns), '?'));
            $this->db->query(
                'INSERT INTO ' . $openingTable . ' (' . implode(', ', $insertColumns) . ') VALUES (' . $placeholders . ') ON DUPLICATE KEY UPDATE ' . implode(', ', $updateParts),
                $insertValues
            );
            $snapshotRow = $this->findOpeningSnapshotRowForUpdate($openingTable, $openingCriteria);
        }
        $snapshotId = (int)($snapshotRow['id'] ?? 0);
        if ($snapshotId <= 0) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => 'Gagal membaca ulang snapshot opening yang baru disimpan.',
            ];
        }

        $deltaQtyBuy = round($snapshotQtyBuy, 4);
        $deltaQtyContent = round($snapshotQtyContent, 4);
        $adjustmentCategory = strtoupper(trim((string)($payload['adjustment_category'] ?? '')));
        $allowedAdjustmentCategories = ['WASTE', 'SPOILAGE', 'PROCESS_LOSS', 'VARIANCE', 'ADJUSTMENT_PLUS'];
        if (!in_array($adjustmentCategory, $allowedAdjustmentCategories, true)) {
            $adjustmentCategory = $deltaQtyContent >= 0 ? 'ADJUSTMENT_PLUS' : 'VARIANCE';
        }

        $adjustmentReasonCode = strtolower(trim((string)($payload['adjustment_reason_code'] ?? '')));
        if ($adjustmentReasonCode === '') {
            $adjustmentReasonCode = 'other';
        }
        $notes = $this->nullableString($payload['notes'] ?? null);
        $scopeLabel = $stockScope === 'DIVISION' ? 'divisi' : 'gudang';
        $defaultLedgerNotes = 'OPENING STOK AWAL ' . date('Y-m', strtotime((string)$snapshotMonth)) . ' ' . strtoupper($scopeLabel);
        $ledgerNotes = $notes !== null ? $notes : $defaultLedgerNotes;

        $movementNo = null;
        $purgeIdentity = !empty($existingSnapshot['id'])
            ? $this->buildOpeningSnapshotHistoryIdentity($stockScope, $existingSnapshot)
            : [
                'division_id' => $divisionId,
                'destination_type' => $destinationType,
                'stock_domain' => $stockDomain,
                'item_id' => $itemId,
                'material_id' => $materialId,
                'buy_uom_id' => $buyUomId,
                'content_uom_id' => $contentUomId,
                'profile_key' => $profileKey,
                'profile_name' => $profileName,
                'profile_brand' => $profileBrand,
                'profile_description' => $profileDescription,
                'profile_expired_date' => $profileExpiredDate,
                'profile_content_per_buy' => $profileContentPerBuy,
            ];

        $this->purgeOpeningMovementEntries(
            $stockScope,
            $openingTable,
            $movementDate,
            $purgeIdentity
        );

        if ($deltaQtyBuy != 0.0 || $deltaQtyContent != 0.0) {
            $this->load->library('InventoryLedger');
            $ledger = $this->inventoryledger->post([
                'manage_transaction' => false,
                'movement_scope' => $stockScope,
                'division_id' => $divisionId,
                'destination_type' => $destinationType,
                'movement_date' => $movementDate,
                'movement_type' => 'ADJUSTMENT',
                'stock_domain' => $stockDomain,
                'item_id' => $itemId,
                'material_id' => $materialId,
                'buy_uom_id' => $buyUomId,
                'content_uom_id' => $contentUomId,
                'qty_buy_delta' => $deltaQtyBuy,
                'qty_content_delta' => $deltaQtyContent,
                'profile_key' => $profileKey,
                'profile_name' => $profileName,
                'profile_brand' => $profileBrand,
                'profile_description' => $profileDescription,
                'profile_expired_date' => $profileExpiredDate,
                'profile_content_per_buy' => $profileContentPerBuy,
                'profile_buy_uom_code' => $buyUomCode,
                'profile_content_uom_code' => $contentUomCode,
                'unit_cost' => $avgCost,
                'ref_table' => $openingTable,
                'ref_id' => $snapshotId,
                'adjustment_category' => $adjustmentCategory,
                'adjustment_reason_code' => $adjustmentReasonCode,
                'notes' => $ledgerNotes,
                'created_by' => $userId > 0 ? $userId : null,
                'force_avg_cost_per_content' => $replaceMode ? $snapshotAvgCost : null,
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

        $rebuild = $this->rebuild_inventory_history_from_opening(
            $stockScope,
            $snapshotMonth,
            [
                'division_id' => $divisionId,
                'destination_type' => $destinationType,
                'stock_domain' => $stockDomain,
                'item_id' => $itemId,
                'material_id' => $materialId,
                'buy_uom_id' => $buyUomId,
                'content_uom_id' => $contentUomId,
                'profile_key' => $profileKey,
                'profile_name' => $profileName,
                'profile_brand' => $profileBrand,
                'profile_description' => $profileDescription,
                'profile_expired_date' => $profileExpiredDate,
                'profile_content_per_buy' => $profileContentPerBuy,
                'profile_buy_uom_code' => $buyUomCode,
                'profile_content_uom_code' => $contentUomCode,
                'avg_cost_per_content' => $snapshotAvgCost,
            ]
        );
        if (!($rebuild['ok'] ?? false)) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => (string)($rebuild['message'] ?? 'Gagal rebuild histori opening.'),
            ];
        }
        if ($previousHistoryIdentity !== null) {
            $previousRebuild = $this->rebuild_inventory_history_from_opening($stockScope, $snapshotMonth, $previousHistoryIdentity);
            if (!($previousRebuild['ok'] ?? false)) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => (string)($previousRebuild['message'] ?? 'Gagal membersihkan histori identity opening lama.'),
                ];
            }
        }
        $needsLotSync = $stockScope === 'DIVISION'
            && (
                empty($existingSnapshot['id'])
                || abs($snapshotQtyContent - $previousOpeningQtyContent) > 0.0001
                || abs($snapshotAvgCost - $previousOpeningAvgCost) > 0.000001
            );
        if ($needsLotSync) {
            $lotSync = $this->syncOpeningSnapshotLots($stockScope, $openingTable, $snapshotRow, true);
            if (!($lotSync['ok'] ?? false)) {
                $this->db->trans_rollback();
                return $lotSync;
            }
        }

        if ($this->db->table_exists('aud_transaction_log')) {
            $this->db->insert('aud_transaction_log', [
                'module_code' => 'INVENTORY',
                'action_code' => $stockScope === 'DIVISION' ? 'DIVISION_OPENING' : 'WAREHOUSE_OPENING',
                'entity_table' => $openingTable,
                'entity_id' => null,
                'transaction_no' => $movementNo !== '' ? $movementNo : null,
                'actor_user_id' => $userId > 0 ? $userId : null,
                'source_ip' => $sourceIp !== '' ? $sourceIp : null,
                'after_payload' => json_encode([
                    'snapshot_id' => $snapshotId,
                    'stock_scope' => $stockScope,
                    'stock_domain' => $stockDomain,
                    'division_id' => $divisionId,
                    'destination_type' => $destinationType,
                    'snapshot_month' => $snapshotMonth,
                    'movement_date' => $movementDate,
                    'item_id' => $itemId,
                    'material_id' => $materialId,
                    'profile_key' => $profileKey,
                    'target_qty_buy' => $snapshotQtyBuy,
                    'target_qty_content' => $snapshotQtyContent,
                    'delta_qty_buy' => $deltaQtyBuy,
                    'delta_qty_content' => $deltaQtyContent,
                    'adjustment_category' => $adjustmentCategory,
                    'adjustment_reason_code' => $adjustmentReasonCode,
                    'replace_mode' => $replaceMode ? 1 : 0,
                ]),
                'notes' => 'Opening ' . $scopeLabel . ' diposting ke live balance dan daily rollup',
            ]);
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => 'Gagal menyimpan opening ' . $scopeLabel . '.',
            ];
        }

        $this->db->trans_commit();

        return [
            'ok' => true,
            'message' => 'Opening ' . $scopeLabel . ' berhasil disimpan dan diposting.',
            'data' => [
                'stock_scope' => $stockScope,
                'snapshot_id' => $snapshotId,
                'snapshot_id' => $snapshotId,
                'division_id' => $divisionId,
                'destination_type' => $destinationType,
                'snapshot_month' => $snapshotMonth,
                'item_id' => $itemId,
                'profile_key' => $profileKey,
                'movement_no' => $movementNo,
                'target_qty_buy' => $snapshotQtyBuy,
                'target_qty_content' => $snapshotQtyContent,
                'delta_qty_buy' => $deltaQtyBuy,
                'delta_qty_content' => $deltaQtyContent,
            ],
        ];
    }

    public function list_stock_adjustments(string $scope, string $month, string $q, int $limit, ?int $divisionId = null, ?string $destination = null, string $dateFrom = '', string $dateTo = ''): array
    {
        if (!$this->db->table_exists('inv_stock_adjustment')) {
            return [];
        }

        $scope = strtoupper(trim($scope));
        if (!in_array($scope, ['WAREHOUSE', 'DIVISION'], true)) {
            $scope = 'WAREHOUSE';
        }

        $month = trim($month);
        if ($month !== '' && preg_match('/^\d{4}\-\d{2}$/', $month) !== 1) {
            $month = '';
        }
        $dateFrom = trim($dateFrom);
        $dateTo   = trim($dateTo);
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) !== 1) { $dateFrom = ''; }
        if ($dateTo   !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)   !== 1) { $dateTo   = ''; }
        $q = trim($q);
        $limit = max(1, min(500, $limit));

        $divisionNameColumn = $this->db->field_exists('division_name', 'mst_operational_division')
            ? 'division_name'
            : ($this->db->field_exists('name', 'mst_operational_division') ? 'name' : null);
        $divisionNameSelect = $divisionNameColumn !== null ? ('d.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';

        $this->db
            ->select('h.*, ' . $divisionNameSelect, false)
            ->select('COUNT(l.id) AS line_count', false)
            ->select('SUM(COALESCE(l.qty_waste_content, 0)) AS total_waste_content', false)
            ->select('SUM(CASE WHEN COALESCE(l.profile_content_per_buy, 0) > 0 THEN COALESCE(l.qty_waste_content, 0) / l.profile_content_per_buy ELSE 0 END) AS total_waste_buy', false)
            ->select('SUM(COALESCE(l.qty_waste_content, 0) * COALESCE(l.unit_cost, 0)) AS total_waste_value', false)
            ->select('SUM(COALESCE(l.qty_spoil_content, 0)) AS total_spoil_content', false)
            ->select('SUM(CASE WHEN COALESCE(l.profile_content_per_buy, 0) > 0 THEN COALESCE(l.qty_spoil_content, 0) / l.profile_content_per_buy ELSE 0 END) AS total_spoil_buy', false)
            ->select('SUM(COALESCE(l.qty_spoil_content, 0) * COALESCE(l.unit_cost, 0)) AS total_spoil_value', false)
            ->select('SUM(COALESCE(l.qty_process_loss_content, 0)) AS total_process_loss_content', false)
            ->select('SUM(CASE WHEN COALESCE(l.profile_content_per_buy, 0) > 0 THEN COALESCE(l.qty_process_loss_content, 0) / l.profile_content_per_buy ELSE 0 END) AS total_process_loss_buy', false)
            ->select('SUM(COALESCE(l.qty_process_loss_content, 0) * COALESCE(l.unit_cost, 0)) AS total_process_loss_value', false)
            ->select('SUM(COALESCE(l.qty_variance_content, 0)) AS total_variance_content', false)
            ->select('SUM(CASE WHEN COALESCE(l.profile_content_per_buy, 0) > 0 THEN COALESCE(l.qty_variance_content, 0) / l.profile_content_per_buy ELSE 0 END) AS total_variance_buy', false)
            ->select('SUM(COALESCE(l.qty_variance_content, 0) * COALESCE(l.unit_cost, 0)) AS total_variance_value', false)
            ->select('SUM(COALESCE(l.qty_adjustment_plus_content, 0)) AS total_adjustment_plus_content', false)
            ->select('SUM(CASE WHEN COALESCE(l.profile_content_per_buy, 0) > 0 THEN COALESCE(l.qty_adjustment_plus_content, 0) / l.profile_content_per_buy ELSE 0 END) AS total_adjustment_plus_buy', false)
            ->select('SUM(COALESCE(l.qty_adjustment_plus_content, 0) * COALESCE(l.unit_cost, 0)) AS total_adjustment_plus_value', false)
            ->from('inv_stock_adjustment h')
            ->join('inv_stock_adjustment_line l', 'l.adjustment_id = h.id', 'left')
            ->join('mst_operational_division d', 'd.id = h.division_id', 'left')
            ->where('h.stock_scope', $scope);

        if ($dateFrom !== '' && $dateTo !== '') {
            $this->db->where('h.adjustment_date >=', $dateFrom)->where('h.adjustment_date <=', $dateTo);
        } elseif ($month !== '') {
            $this->db->where("DATE_FORMAT(h.adjustment_date, '%Y-%m') =", $month, false);
        }
        if ($scope === 'DIVISION' && $divisionId !== null && $divisionId > 0) {
            $this->db->where('h.division_id', $divisionId);
        }
        $destination = $this->normalizeStockAdjustmentDestination($destination, false);
        if ($scope === 'DIVISION' && $destination !== null && $destination !== 'ALL') {
            $this->db->where('COALESCE(h.destination_type, "OTHER") =', $destination, false);
        }
        if ($q !== '') {
            $this->db->group_start()
                ->like('h.adjustment_no', $q)
                ->or_like('h.notes', $q)
                ->or_like('h.status', $q);
            if ($divisionNameColumn !== null) {
                $this->db->or_like('d.' . $divisionNameColumn, $q);
            }
            $this->db->group_end();
        }

        return $this->db
            ->group_by('h.id')
            ->order_by('h.adjustment_date', 'DESC')
            ->order_by('h.id', 'DESC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    public function list_stock_adjustment_detail_rows(string $scope, string $month, string $q, int $limit, ?int $divisionId = null, ?string $destination = null, string $dateFrom = '', string $dateTo = ''): array
    {
        if (!$this->db->table_exists('inv_stock_adjustment') || !$this->db->table_exists('inv_stock_adjustment_line')) {
            return [];
        }

        $scope = strtoupper(trim($scope));
        if (!in_array($scope, ['WAREHOUSE', 'DIVISION'], true)) {
            $scope = 'WAREHOUSE';
        }

        $month = trim($month);
        if ($month !== '' && preg_match('/^\d{4}\-\d{2}$/', $month) !== 1) {
            $month = '';
        }
        $dateFrom = trim($dateFrom);
        $dateTo   = trim($dateTo);
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) !== 1) { $dateFrom = ''; }
        if ($dateTo   !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)   !== 1) { $dateTo   = ''; }
        $q = trim($q);
        $limit = max(1, min(500, $limit));
        $lineLimit = max(50, min(2000, $limit * 5));

        $divisionNameColumn = $this->db->field_exists('division_name', 'mst_operational_division')
            ? 'division_name'
            : ($this->db->field_exists('name', 'mst_operational_division') ? 'name' : null);
        $divisionNameSelect = $divisionNameColumn !== null ? ('d.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';

        $this->db
            ->select('h.id AS adjustment_id, h.adjustment_no, h.adjustment_date, h.status, h.stock_scope, h.division_id, h.destination_type, h.notes AS header_notes', false)
            ->select($divisionNameSelect, false)
            ->select('l.*, i.item_code, i.item_name, m.material_code, m.material_name', false)
            ->select('mfl.lot_no AS plus_lot_no', false)
            ->select('COALESCE((SELECT mfl2.lot_no FROM inv_material_fifo_issue_line mfil JOIN inv_material_fifo_lot mfl2 ON mfl2.id=mfil.lot_id WHERE mfil.issue_id = COALESCE(l.waste_issue_id, l.spoil_issue_id, l.process_loss_issue_id, l.variance_issue_id) ORDER BY mfil.id ASC LIMIT 1),(SELECT cl.lot_no FROM inv_component_lot_issue_line cil JOIN inv_component_lot cl ON cl.id=cil.lot_id WHERE cil.issue_id = COALESCE(l.waste_issue_id, l.spoil_issue_id, l.process_loss_issue_id, l.variance_issue_id) ORDER BY cil.id ASC LIMIT 1)) AS shrink_lot_no', false)
            ->from('inv_stock_adjustment_line l')
            ->join('inv_stock_adjustment h', 'h.id = l.adjustment_id')
            ->join('mst_item i', 'i.id = l.item_id', 'left')
            ->join('mst_material m', 'm.id = l.material_id', 'left')
            ->join('mst_operational_division d', 'd.id = h.division_id', 'left')
            ->join('inv_material_fifo_lot mfl', 'mfl.id = l.adjustment_plus_lot_id', 'left')
            ->where('h.stock_scope', $scope);

        if ($dateFrom !== '' && $dateTo !== '') {
            $this->db->where('h.adjustment_date >=', $dateFrom)->where('h.adjustment_date <=', $dateTo);
        } elseif ($month !== '') {
            $this->db->where("DATE_FORMAT(h.adjustment_date, '%Y-%m') =", $month, false);
        }
        if ($scope === 'DIVISION' && $divisionId !== null && $divisionId > 0) {
            $this->db->where('h.division_id', $divisionId);
        }

        $destination = $this->normalizeStockAdjustmentDestination($destination, false);
        if ($scope === 'DIVISION' && $destination !== null && $destination !== 'ALL') {
            $this->db->where('COALESCE(h.destination_type, "OTHER") =', $destination, false);
        }

        if ($q !== '') {
            $this->db->group_start()
                ->like('h.adjustment_no', $q)
                ->or_like('h.notes', $q)
                ->or_like('h.status', $q)
                ->or_like('l.line_note', $q)
                ->or_like('l.inbound_lot_no', $q)
                ->or_like('l.profile_key', $q)
                ->or_like('l.profile_name', $q)
                ->or_like('l.profile_brand', $q)
                ->or_like('l.profile_description', $q)
                ->or_like('i.item_code', $q)
                ->or_like('i.item_name', $q)
                ->or_like('m.material_code', $q)
                ->or_like('m.material_name', $q);
            if ($divisionNameColumn !== null) {
                $this->db->or_like('d.' . $divisionNameColumn, $q);
            }
            $this->db->group_end();
        }

        return $this->db
            ->order_by('h.adjustment_date', 'DESC')
            ->order_by('h.id', 'DESC')
            ->order_by('l.line_no', 'ASC')
            ->limit($lineLimit)
            ->get()
            ->result_array();
    }

    public function get_stock_adjustment(int $id): ?array
    {
        if ($id <= 0 || !$this->db->table_exists('inv_stock_adjustment')) {
            return null;
        }

        $divisionNameColumn = $this->db->field_exists('division_name', 'mst_operational_division')
            ? 'division_name'
            : ($this->db->field_exists('name', 'mst_operational_division') ? 'name' : null);
        $divisionNameSelect = $divisionNameColumn !== null ? ('d.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';

        $row = $this->db
            ->select('h.*, ' . $divisionNameSelect, false)
            ->from('inv_stock_adjustment h')
            ->join('mst_operational_division d', 'd.id = h.division_id', 'left')
            ->where('h.id', $id)
            ->limit(1)
            ->get()
            ->row_array();

        return $row ?: null;
    }

    public function get_stock_adjustment_lines(int $adjustmentId): array
    {
        if ($adjustmentId <= 0 || !$this->db->table_exists('inv_stock_adjustment_line')) {
            return [];
        }

        return $this->db
            ->select('l.*, i.item_code, i.item_name, m.material_code, m.material_name')
            ->from('inv_stock_adjustment_line l')
            ->join('mst_item i', 'i.id = l.item_id', 'left')
            ->join('mst_material m', 'm.id = l.material_id', 'left')
            ->where('l.adjustment_id', $adjustmentId)
            ->order_by('l.line_no', 'ASC')
            ->get()
            ->result_array();
    }

    public function search_stock_adjustment_items(array $context, string $q, int $limit = 20): array
    {
        $scope = strtoupper(trim((string)($context['stock_scope'] ?? 'WAREHOUSE')));
        if (!in_array($scope, ['WAREHOUSE', 'DIVISION'], true)) {
            $scope = 'WAREHOUSE';
        }

        $divisionId = !empty($context['division_id']) ? (int)$context['division_id'] : null;
        $destinationType = $scope === 'DIVISION'
            ? $this->normalizeStockAdjustmentDestination((string)($context['destination_type'] ?? ''), true)
            : 'GUDANG';

        $limit = max(1, min(50, $limit));
        $candidateLimit = min(50, max($limit, $limit * 3));

        $rows = [];
        $seen = [];

        if ($scope === 'DIVISION' && $divisionId !== null && $divisionId > 0 && $destinationType !== null) {
            $stockRows = $this->search_division_stock_adjustment_items($q, $divisionId, $destinationType, $candidateLimit);
            foreach ($stockRows as $row) {
                $dedupeKey = $this->openingSearchRowKey($row);
                if (isset($seen[$dedupeKey])) {
                    continue;
                }
                $seen[$dedupeKey] = true;
                $rows[] = $row;
            }
        }

        $fallbackRows = $this->search_opening_items($q, $candidateLimit);
        $stockProfileKeys = [];
        $stockProfileNames = [];
        if ($scope === 'DIVISION') {
            foreach ($rows as $stockRow) {
                if (($stockRow['source_type'] ?? '') !== 'PROFILE_DIVISION_STOCK') {
                    continue;
                }
                $itemId = (int)($stockRow['id'] ?? $stockRow['item_id'] ?? 0);
                $profileKey = trim((string)($stockRow['profile_key'] ?? ''));
                if ($itemId > 0 && $profileKey !== '') {
                    $stockProfileKeys[$itemId . '|' . strtoupper($profileKey)] = true;
                }
                $stockProfileNames[$itemId . '|' . strtoupper(trim((string)($stockRow['profile_name'] ?? ''))) . '|' . strtoupper(trim((string)($stockRow['profile_brand'] ?? ''))) . '|' . strtoupper(trim((string)($stockRow['profile_description'] ?? '')))] = true;
            }
        }
        foreach ($fallbackRows as $row) {
            if ($scope === 'DIVISION') {
                $itemId = (int)($row['id'] ?? $row['item_id'] ?? 0);
                $profileKey = trim((string)($row['profile_key'] ?? ''));
                $nameKey = $itemId . '|' . strtoupper(trim((string)($row['profile_name'] ?? ''))) . '|' . strtoupper(trim((string)($row['profile_brand'] ?? ''))) . '|' . strtoupper(trim((string)($row['profile_description'] ?? '')));
                if ($itemId > 0 && $profileKey !== '' && isset($stockProfileKeys[$itemId . '|' . strtoupper($profileKey)])) {
                    continue;
                }
                if (isset($stockProfileNames[$nameKey])) {
                    continue;
                }
            }
            $dedupeKey = $this->openingSearchRowKey($row);
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;
            $rows[] = $row;
        }
        foreach ($rows as $index => &$row) {
            $profileContentPerBuy = round((float)($row['default_content_per_buy'] ?? 1), 6);
            if ($profileContentPerBuy <= 0) {
                $profileContentPerBuy = 1.0;
            }

            $identity = [
                'stock_scope' => $scope,
                'division_id' => $scope === 'DIVISION' ? $divisionId : null,
                'destination_type' => $scope === 'DIVISION' ? $destinationType : 'GUDANG',
                'item_id' => !empty($row['id']) ? (int)$row['id'] : null,
                'material_id' => !empty($row['material_id']) ? (int)$row['material_id'] : null,
                'buy_uom_id' => !empty($row['default_buy_uom_id']) ? (int)$row['default_buy_uom_id'] : null,
                'content_uom_id' => !empty($row['default_content_uom_id']) ? (int)$row['default_content_uom_id'] : null,
                'profile_key' => $this->nullableString($row['profile_key'] ?? null),
            ];
            $balance = $this->fetchStockAdjustmentCurrentBalance($identity);
            $row['stock_domain'] = (string)($balance['stock_domain'] ?? $this->resolveLegacyIdentityStockDomain(
                !empty($row['id']) ? (int)$row['id'] : null,
                !empty($row['material_id']) ? (int)$row['material_id'] : null
            ));
            $row['available_qty_buy'] = round((float)($balance['qty_buy_balance'] ?? 0), 4);
            $row['available_qty_content'] = round((float)($balance['qty_content_balance'] ?? 0), 4);
            $row['avg_cost_per_content'] = round((float)($balance['avg_cost_per_content'] ?? 0), 6);
            $row['updated_at'] = !empty($balance['updated_at']) ? (string)$balance['updated_at'] : null;
            $row['default_content_per_buy'] = $profileContentPerBuy;
            $row['_search_order'] = $index;
            $row['_has_positive_stock'] = ($row['available_qty_content'] > 0 || $row['available_qty_buy'] > 0) ? 1 : 0;
        }
        unset($row);

        usort($rows, static function (array $a, array $b): int {
            $aHasStock = (int)($a['_has_positive_stock'] ?? 0);
            $bHasStock = (int)($b['_has_positive_stock'] ?? 0);
            if ($aHasStock !== $bHasStock) {
                return $bHasStock <=> $aHasStock;
            }

            $aQtyContent = (float)($a['available_qty_content'] ?? 0);
            $bQtyContent = (float)($b['available_qty_content'] ?? 0);
            if ($aQtyContent !== $bQtyContent) {
                return $bQtyContent <=> $aQtyContent;
            }

            $aQtyBuy = (float)($a['available_qty_buy'] ?? 0);
            $bQtyBuy = (float)($b['available_qty_buy'] ?? 0);
            if ($aQtyBuy !== $bQtyBuy) {
                return $bQtyBuy <=> $aQtyBuy;
            }

            return (int)($a['_search_order'] ?? 0) <=> (int)($b['_search_order'] ?? 0);
        });

        if ($scope === 'DIVISION') {
            $stockRows = [];
            $catalogRows = [];
            foreach ($rows as $row) {
                if (($row['source_type'] ?? '') === 'PROFILE_DIVISION_STOCK') {
                    $stockRows[] = $row;
                } else {
                    $catalogRows[] = $row;
                }
            }

            $reservedCatalogSlots = min(5, max(2, (int)floor($limit / 4)));
            $stockTake = max(0, $limit - min($reservedCatalogSlots, count($catalogRows)));
            $rows = array_merge(
                array_slice($stockRows, 0, $stockTake),
                array_slice($catalogRows, 0, min($reservedCatalogSlots, count($catalogRows)))
            );

            if (count($rows) < $limit) {
                $stockLeft = array_slice($stockRows, $stockTake);
                $catalogLeft = array_slice($catalogRows, min($reservedCatalogSlots, count($catalogRows)));
                $rows = array_merge($rows, array_slice(array_merge($stockLeft, $catalogLeft), 0, $limit - count($rows)));
            }
        } else {
            $rows = array_slice($rows, 0, $limit);
        }
        foreach ($rows as &$row) {
            unset($row['_search_order'], $row['_has_positive_stock']);
        }
        unset($row);

        return $rows;
    }

    private function search_division_stock_adjustment_items(string $q, int $divisionId, string $destinationType, int $limit): array
    {
        if ($q === '' || !$this->db->table_exists('inv_division_monthly_stock') || !$this->db->table_exists('mst_item')) {
            return [];
        }

        $targetMonth = date('Y-m-01');
        $latestMonthSubquery = $this->db
            ->select('division_id, destination_type, identity_key, MAX(month_key) AS month_key', false)
            ->from('inv_division_monthly_stock')
            ->where('month_key <=', $targetMonth)
            ->group_by(['division_id', 'destination_type', 'identity_key'])
            ->get_compiled_select();

        $hasUom = $this->db->table_exists('mst_uom');
        $rows = [];

        $this->db
            ->select("'PROFILE_DIVISION_STOCK' AS source_type", false)
            ->select('s.item_id AS id, i.item_code, i.item_name')
            ->select('s.buy_uom_id AS default_buy_uom_id, s.content_uom_id AS default_content_uom_id')
            ->select('COALESCE(NULLIF(s.profile_content_per_buy, 0), 1) AS default_content_per_buy', false)
            ->select('CASE WHEN COALESCE(s.material_id, i.material_id, 0) > 0 THEN 1 ELSE 0 END AS is_material', false)
            ->select('COALESCE(s.material_id, i.material_id, 0) AS material_id', false)
            ->select('m.material_code, m.material_name')
            ->select('s.profile_key, s.profile_name, s.profile_brand, s.profile_description')
            ->select('s.profile_expired_date')
            ->from('inv_division_monthly_stock s')
            ->join('(' . $latestMonthSubquery . ') lm', 'lm.division_id = s.division_id AND lm.destination_type = s.destination_type AND lm.identity_key = s.identity_key AND lm.month_key = s.month_key', 'inner', false)
            ->join('mst_item i', 'i.id = s.item_id', 'inner')
            ->join('mst_material m', 'm.id = COALESCE(s.material_id, i.material_id)', 'left', false)
            ->where('s.division_id', $divisionId)
            ->where('s.destination_type', $destinationType)
            ->where('i.is_active', 1)
            ->where('(COALESCE(s.closing_qty_buy, 0) <> 0 OR COALESCE(s.closing_qty_content, 0) <> 0)', null, false);

        if ($hasUom) {
            $this->db->select('bu.code AS default_buy_uom_code, bu.name AS default_buy_uom_name');
            $this->db->select('cu.code AS default_content_uom_code, cu.name AS default_content_uom_name');
            $this->db->join('mst_uom bu', 'bu.id = s.buy_uom_id', 'left');
            $this->db->join('mst_uom cu', 'cu.id = s.content_uom_id', 'left');
        } else {
            $this->db->select('NULL AS default_buy_uom_code, NULL AS default_buy_uom_name', false);
            $this->db->select('NULL AS default_content_uom_code, NULL AS default_content_uom_name', false);
        }

        $this->db->group_start()
            ->like('i.item_code', $q)
            ->or_like('i.item_name', $q)
            ->or_like('s.profile_name', $q)
            ->or_like('s.profile_brand', $q)
            ->or_like('s.profile_description', $q)
            ->or_like('m.material_code', $q)
            ->or_like('m.material_name', $q)
            ->group_end();

        $rows = $this->db
            ->order_by('COALESCE(s.updated_at, s.last_movement_at, CONCAT(s.month_key, " 00:00:00"))', 'DESC', false)
            ->order_by('COALESCE(s.closing_qty_content, 0)', 'DESC', false)
            ->order_by('i.item_name', 'ASC')
            ->order_by('s.profile_name', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();

        return $this->normalizeDivisionProfileKeyRows($rows);
    }

    public function save_stock_adjustment(array $header, array $lines, int $userId): array
    {
        if (!$this->db->table_exists('inv_stock_adjustment') || !$this->db->table_exists('inv_stock_adjustment_line')) {
            return ['ok' => false, 'message' => 'Tabel adjustment stok belum tersedia.'];
        }

        $scope = strtoupper(trim((string)($header['stock_scope'] ?? 'WAREHOUSE')));
        if (!in_array($scope, ['WAREHOUSE', 'DIVISION'], true)) {
            return ['ok' => false, 'message' => 'stock_scope adjustment tidak valid.'];
        }

        $adjustmentDate = $this->normalizeDate((string)($header['adjustment_date'] ?? date('Y-m-d')));
        if ($adjustmentDate === null) {
            return ['ok' => false, 'message' => 'Tanggal adjustment tidak valid.'];
        }

        $divisionId = !empty($header['division_id']) ? (int)$header['division_id'] : null;
        $destinationType = $scope === 'DIVISION'
            ? $this->normalizeStockAdjustmentDestination((string)($header['destination_type'] ?? ''), true)
            : 'GUDANG';
        if ($scope === 'DIVISION' && ($divisionId === null || $destinationType === null)) {
            return ['ok' => false, 'message' => 'Adjustment divisi membutuhkan division_id dan tujuan yang valid.'];
        }

        $preparedLines = [];
        foreach ($lines as $line) {
            $prepared = $this->normalizeStockAdjustmentLine($scope, $divisionId, $destinationType, $line);
            if ($prepared === null) {
                continue;
            }
            $preparedLines[] = $prepared;
        }
        if (empty($preparedLines)) {
            return ['ok' => false, 'message' => 'Baris adjustment belum diisi atau tidak valid.'];
        }

        $id = !empty($header['id']) ? (int)$header['id'] : 0;
        $payload = [
            'adjustment_no' => $id > 0 ? (string)($header['adjustment_no'] ?? '') : $this->generateStockAdjustmentNo($scope, $adjustmentDate),
            'adjustment_date' => $adjustmentDate,
            'stock_scope' => $scope,
            'division_id' => $scope === 'DIVISION' ? $divisionId : null,
            'destination_type' => $scope === 'DIVISION' ? $destinationType : null,
            'notes' => $this->nullableString($header['notes'] ?? null),
        ];

        $this->db->trans_begin();

        if ($id > 0) {
            $existing = $this->get_stock_adjustment($id);
            if (!$existing || strtoupper((string)($existing['status'] ?? '')) !== 'DRAFT') {
                $this->db->trans_rollback();
                return ['ok' => false, 'message' => 'Hanya draft adjustment yang bisa diubah.'];
            }
            unset($payload['adjustment_no']);
            $this->db->where('id', $id)->update('inv_stock_adjustment', $payload);
            $this->db->where('adjustment_id', $id)->delete('inv_stock_adjustment_line');
        } else {
            $payload['status'] = 'DRAFT';
            $payload['created_by'] = $userId > 0 ? $userId : null;
            $inserted = $this->db->insert('inv_stock_adjustment', $payload);
            if (!$inserted) {
                $dbError = $this->db->error();
                $this->db->trans_rollback();
                return ['ok' => false, 'message' => 'Gagal menyimpan header adjustment: ' . (string)($dbError['message'] ?? 'unknown error')];
            }
            $id = (int)$this->db->insert_id();
        }

        $lineNo = 1;
        foreach ($preparedLines as $preparedLine) {
            unset($preparedLine['stock_domain']);
            $preparedLine['adjustment_id'] = $id;
            $preparedLine['line_no'] = $lineNo++;
            $inserted = $this->db->insert('inv_stock_adjustment_line', $preparedLine);
            if (!$inserted) {
                $dbError = $this->db->error();
                $this->db->trans_rollback();
                return ['ok' => false, 'message' => 'Gagal menyimpan line adjustment: ' . (string)($dbError['message'] ?? 'unknown error')];
            }
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal menyimpan draft adjustment.'];
        }

        $this->db->trans_commit();
        return ['ok' => true, 'id' => $id, 'adjustment_no' => $payload['adjustment_no'] ?? ($header['adjustment_no'] ?? '')];
    }

    public function post_stock_adjustment(int $id, int $userId, string $sourceIp = ''): array
    {
        $header = $this->get_stock_adjustment($id);
        if (!$header) {
            return ['ok' => false, 'message' => 'Dokumen adjustment tidak ditemukan.'];
        }
        if (strtoupper((string)($header['status'] ?? '')) !== 'DRAFT') {
            return ['ok' => false, 'message' => 'Hanya draft adjustment yang bisa diposting.'];
        }

        $lines = $this->get_stock_adjustment_lines($id);
        if (empty($lines)) {
            return ['ok' => false, 'message' => 'Dokumen adjustment belum punya baris.'];
        }

        $this->load->library('MaterialFifoManager');
        $fifoReady = $this->materialfifomanager->ensureReady();
        if (!($fifoReady['ok'] ?? false)) {
            return $fifoReady;
        }

        $this->db->trans_begin();
        $postedLineIds = [];

        foreach ($lines as $line) {
            $posted = $this->postStockAdjustmentLine($header, $line, $userId);
            if (!($posted['ok'] ?? false)) {
                $this->db->trans_rollback();
                return $posted;
            }

            $lineUpdates = $posted['data']['line_updates'] ?? [];
            if (!empty($lineUpdates)) {
                $this->db->where('id', (int)$line['id'])->update('inv_stock_adjustment_line', $lineUpdates);
            }
            $postedLineIds[] = (int)$line['id'];
        }

        $syncProfiles = $this->syncPostedStockAdjustmentProfiles($header, $lines);
        if (!($syncProfiles['ok'] ?? false)) {
            $this->db->trans_rollback();
            return $syncProfiles;
        }

        $this->db->where('id', $id)->update('inv_stock_adjustment', [
            'status' => 'POSTED',
            'posted_at' => date('Y-m-d H:i:s'),
            'posted_by' => $userId > 0 ? $userId : null,
        ]);

        if ($this->db->table_exists('aud_transaction_log')) {
            $this->db->insert('aud_transaction_log', [
                'module_code' => 'INVENTORY',
                'action_code' => strtoupper((string)$header['stock_scope']) === 'DIVISION' ? 'DIVISION_ADJUSTMENT' : 'WAREHOUSE_ADJUSTMENT',
                'entity_table' => 'inv_stock_adjustment',
                'entity_id' => $id,
                'transaction_no' => (string)($header['adjustment_no'] ?? ''),
                'actor_user_id' => $userId > 0 ? $userId : null,
                'source_ip' => $sourceIp !== '' ? $sourceIp : null,
                'after_payload' => json_encode([
                    'header' => [
                        'id' => $id,
                        'adjustment_no' => (string)($header['adjustment_no'] ?? ''),
                        'adjustment_date' => (string)($header['adjustment_date'] ?? ''),
                        'stock_scope' => (string)($header['stock_scope'] ?? ''),
                        'division_id' => !empty($header['division_id']) ? (int)$header['division_id'] : null,
                        'destination_type' => $header['destination_type'] ?? null,
                    ],
                    'line_ids' => $postedLineIds,
                ]),
                'notes' => 'Adjustment stok diposting ke live balance, daily rollup, dan lot FIFO',
            ]);
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal memposting adjustment stok.'];
        }

        $this->db->trans_commit();
        return ['ok' => true, 'id' => $id];
    }

    public function delete_draft_stock_adjustment(int $id): array
    {
        $header = $this->get_stock_adjustment($id);
        if (!$header) {
            return ['ok' => false, 'message' => 'Dokumen adjustment tidak ditemukan.'];
        }
        if (strtoupper((string)($header['status'] ?? '')) !== 'DRAFT') {
            return ['ok' => false, 'message' => 'Hanya draft adjustment yang bisa dihapus.'];
        }

        $this->db->trans_begin();
        $this->db->where('adjustment_id', $id)->delete('inv_stock_adjustment_line');
        $this->db->where('id', $id)->delete('inv_stock_adjustment');

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal menghapus draft adjustment.'];
        }

        $this->db->trans_commit();
        return ['ok' => true, 'id' => $id];
    }

    public function void_posted_stock_adjustment(int $id, int $userId, string $sourceIp = ''): array
    {
        $header = $this->get_stock_adjustment($id);
        if (!$header) {
            return ['ok' => false, 'message' => 'Dokumen adjustment tidak ditemukan.'];
        }
        if (strtoupper((string)($header['status'] ?? 'DRAFT')) !== 'POSTED') {
            return ['ok' => false, 'message' => 'Hanya adjustment POSTED yang bisa di-void.'];
        }

        $lines = $this->get_stock_adjustment_lines($id);
        if (empty($lines)) {
            return ['ok' => false, 'message' => 'Dokumen adjustment tidak memiliki line untuk di-void.'];
        }

        $this->load->library('MaterialFifoManager');
        $fifoReady = $this->materialfifomanager->ensureReady();
        if (!($fifoReady['ok'] ?? false)) {
            return $fifoReady;
        }

        $scope = strtoupper((string)($header['stock_scope'] ?? 'WAREHOUSE'));
        $divisionId = !empty($header['division_id']) ? (int)$header['division_id'] : null;
        $destinationType = $scope === 'DIVISION'
            ? $this->normalizeStockAdjustmentDestination((string)($header['destination_type'] ?? ''), true)
            : null;
        $voidNote = 'VOID adjustment membatalkan dokumen posted';
        $rebuildTargets = [];
        $adjustmentDate = $this->normalizeDate((string)($header['adjustment_date'] ?? date('Y-m-d')));
        if ($adjustmentDate === null) {
            $adjustmentDate = date('Y-m-d');
        }

        $this->db->trans_begin();

        foreach ($lines as $line) {
            $issueRollback = $this->materialfifomanager->rollbackTransferLotsBySource(
                'inv_stock_adjustment',
                $id,
                (int)($line['id'] ?? 0),
                $voidNote
            );
            if (!($issueRollback['ok'] ?? false)) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => 'VOID adjustment ditolak: ' . (string)($issueRollback['message'] ?? 'gagal rollback issue FIFO'),
                ];
            }

            $lotRollback = $this->materialfifomanager->rollbackReceiptInboundLotsBySource(
                'inv_stock_adjustment',
                $id,
                (int)($line['id'] ?? 0)
            );
            if (!($lotRollback['ok'] ?? false)) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => 'VOID adjustment ditolak: ' . (string)($lotRollback['message'] ?? 'gagal rollback lot inbound'),
                ];
            }

            $this->registerVoidRollbackRebuildTarget($rebuildTargets, $scope, $adjustmentDate, [
                'stock_domain' => 'ITEM',
                'division_id' => $scope === 'DIVISION' ? $divisionId : null,
                'destination_type' => $scope === 'DIVISION' ? $destinationType : null,
                'item_id' => $this->nullableInt($line['item_id'] ?? null),
                'material_id' => $this->nullableInt($line['material_id'] ?? null),
                'buy_uom_id' => $this->nullableInt($line['buy_uom_id'] ?? null),
                'content_uom_id' => $this->nullableInt($line['content_uom_id'] ?? null),
                'profile_key' => $this->nullableString($line['profile_key'] ?? null),
                'profile_name' => $this->nullableString($line['profile_name'] ?? null),
                'profile_brand' => $this->nullableString($line['profile_brand'] ?? null),
                'profile_description' => $this->nullableString($line['profile_description'] ?? null),
                'profile_expired_date' => $this->normalizeDate((string)($line['profile_expired_date'] ?? '')),
                'profile_content_per_buy' => (float)($line['profile_content_per_buy'] ?? 0),
                'profile_buy_uom_code' => $this->nullableString($line['profile_buy_uom_code'] ?? null),
                'profile_content_uom_code' => $this->nullableString($line['profile_content_uom_code'] ?? null),
            ]);
        }

        if ($this->db->table_exists('inv_stock_movement_log')) {
            $this->db
                ->where('ref_table', 'inv_stock_adjustment')
                ->where('ref_id', $id)
                ->delete('inv_stock_movement_log');
            if ((int)($this->db->error()['code'] ?? 0) !== 0) {
                $this->db->trans_rollback();
                return ['ok' => false, 'message' => 'Gagal menghapus histori movement adjustment.'];
            }
        }

        $update = [
            'status' => 'VOID',
        ];
        if ($this->db->field_exists('voided_at', 'inv_stock_adjustment')) {
            $update['voided_at'] = date('Y-m-d H:i:s');
        }
        if ($this->db->field_exists('voided_by', 'inv_stock_adjustment')) {
            $update['voided_by'] = $userId > 0 ? $userId : null;
        }
        if ($this->db->field_exists('notes', 'inv_stock_adjustment')) {
            $existingNotes = trim((string)($header['notes'] ?? ''));
            $update['notes'] = $existingNotes !== '' ? ($existingNotes . ' | ' . $voidNote) : $voidNote;
        }
        $this->db->where('id', $id)->update('inv_stock_adjustment', $update);
        if ((int)($this->db->error()['code'] ?? 0) !== 0) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal update status VOID adjustment.'];
        }

        foreach ($rebuildTargets as $target) {
            $rebuild = $this->rebuild_inventory_history_for_identity(
                (string)$target['scope'],
                (string)$target['start_date'],
                (array)$target['identity']
            );
            if (!($rebuild['ok'] ?? false)) {
                $this->db->trans_rollback();
                $message = (string)($rebuild['message'] ?? 'Gagal rebuild histori stok setelah VOID adjustment.');
                if (!empty($rebuild['data']['negative_samples']) && is_array($rebuild['data']['negative_samples'])) {
                    $message .= ' Contoh: ' . implode('; ', array_slice($rebuild['data']['negative_samples'], 0, 3));
                }
                return ['ok' => false, 'message' => $message];
            }
        }

        if ($this->db->table_exists('aud_transaction_log')) {
            $this->db->insert('aud_transaction_log', [
                'module_code' => 'INVENTORY',
                'action_code' => $scope === 'DIVISION' ? 'DIVISION_ADJUSTMENT_VOID' : 'WAREHOUSE_ADJUSTMENT_VOID',
                'entity_table' => 'inv_stock_adjustment',
                'entity_id' => $id,
                'transaction_no' => (string)($header['adjustment_no'] ?? ''),
                'actor_user_id' => $userId > 0 ? $userId : null,
                'source_ip' => $sourceIp !== '' ? $sourceIp : null,
                'after_payload' => json_encode([
                    'header_id' => $id,
                    'adjustment_no' => (string)($header['adjustment_no'] ?? ''),
                    'status' => 'VOID',
                    'line_ids' => array_values(array_map(static function (array $line): int {
                        return (int)($line['id'] ?? 0);
                    }, $lines)),
                    'rebuild_targets' => count($rebuildTargets),
                ]),
                'notes' => 'Adjustment stok di-VOID dan histori stok direbuild ulang',
            ]);
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal VOID adjustment stok.'];
        }

        $this->db->trans_commit();
        return ['ok' => true, 'id' => $id, 'rebuild_targets' => count($rebuildTargets)];
    }

    public function void_stock_opening_snapshot(string $scope, int $id, int $userId, string $sourceIp = ''): array
    {
        $scope = strtoupper(trim($scope));
        if (!in_array($scope, ['WAREHOUSE', 'DIVISION'], true)) {
            return ['ok' => false, 'message' => 'Scope opening tidak valid.'];
        }

        $openingTable = $this->openingSnapshotTableForScope($scope);
        if (!$this->db->table_exists($openingTable)) {
            return ['ok' => false, 'message' => 'Tabel opening snapshot tidak ditemukan: ' . $openingTable];
        }

        $snapshot = $this->get_stock_opening_snapshot($scope, $id);
        if (!$snapshot) {
            return ['ok' => false, 'message' => 'Opening snapshot tidak ditemukan.'];
        }

        $identity = $this->buildOpeningSnapshotHistoryIdentity($scope, $snapshot);
        $startDate = (string)($snapshot['snapshot_month'] ?? '');
        if ($startDate === '') {
            return ['ok' => false, 'message' => 'snapshot_month opening tidak valid.'];
        }

        $this->db->trans_begin();

        $lotSync = $this->syncOpeningSnapshotLots($scope, $openingTable, $snapshot, false);
        if (!($lotSync['ok'] ?? false)) {
            $this->db->trans_rollback();
            return $lotSync;
        }

        $this->db->where('id', $id)->delete($openingTable);
        $this->purgeOpeningMovementEntries($scope, $openingTable, $startDate, $identity);

        $rebuild = $this->rebuild_inventory_history_from_opening($scope, $startDate, $identity);
        if (!($rebuild['ok'] ?? false)) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => (string)($rebuild['message'] ?? 'Gagal rebuild histori setelah void opening.'),
            ];
        }

        if ($this->db->table_exists('aud_transaction_log')) {
            $this->db->insert('aud_transaction_log', [
                'module_code' => 'INVENTORY',
                'action_code' => $scope === 'DIVISION' ? 'DIVISION_OPENING_VOID' : 'WAREHOUSE_OPENING_VOID',
                'entity_table' => $openingTable,
                'entity_id' => $id,
                'transaction_no' => null,
                'actor_user_id' => $userId > 0 ? $userId : null,
                'source_ip' => $sourceIp !== '' ? $sourceIp : null,
                'after_payload' => json_encode([
                    'stock_scope' => $scope,
                    'snapshot_id' => $id,
                    'snapshot_month' => $startDate,
                    'division_id' => $identity['division_id'] ?? null,
                    'destination_type' => $identity['destination_type'] ?? null,
                    'item_id' => $identity['item_id'] ?? null,
                    'material_id' => $identity['material_id'] ?? null,
                    'profile_key' => $identity['profile_key'] ?? null,
                ]),
                'notes' => 'Opening snapshot di-void dan histori direbuild ulang',
            ]);
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal void opening snapshot.'];
        }

        $this->db->trans_commit();

        return [
            'ok' => true,
            'message' => 'Opening snapshot berhasil di-void.',
            'data' => [
                'stock_scope' => $scope,
                'snapshot_id' => $id,
                'snapshot_month' => $startDate,
            ],
        ];
    }

    private function postStockAdjustmentLine(array $header, array $line, int $userId): array
    {
        $scope = strtoupper((string)($header['stock_scope'] ?? 'WAREHOUSE'));
        $movementDate = (string)($header['adjustment_date'] ?? date('Y-m-d'));
        $divisionId = !empty($header['division_id']) ? (int)$header['division_id'] : null;
        $destinationType = $scope === 'DIVISION'
            ? $this->normalizeStockAdjustmentDestination((string)($header['destination_type'] ?? ''), true)
            : 'GUDANG';
        $contentPerBuy = round((float)($line['profile_content_per_buy'] ?? 1), 6);
        if ($contentPerBuy <= 0) {
            $contentPerBuy = 1.0;
        }
        $currentBalance = $this->fetchStockAdjustmentCurrentBalance([
            'stock_scope' => $scope,
            'division_id' => $scope === 'DIVISION' ? $divisionId : null,
            'destination_type' => $scope === 'DIVISION' ? $destinationType : 'GUDANG',
            'item_id' => !empty($line['item_id']) ? (int)$line['item_id'] : null,
            'material_id' => !empty($line['material_id']) ? (int)$line['material_id'] : null,
            'buy_uom_id' => !empty($line['buy_uom_id']) ? (int)$line['buy_uom_id'] : null,
            'content_uom_id' => !empty($line['content_uom_id']) ? (int)$line['content_uom_id'] : null,
            'profile_key' => $this->nullableString($line['profile_key'] ?? null),
            'profile_name' => $this->nullableString($line['profile_name'] ?? null),
            'profile_brand' => $this->nullableString($line['profile_brand'] ?? null),
            'profile_description' => $this->nullableString($line['profile_description'] ?? null),
            'profile_expired_date' => $this->normalizeDate((string)($line['profile_expired_date'] ?? '')),
            'profile_content_per_buy' => $contentPerBuy,
        ]);

        $basePayload = [
            'manage_transaction' => false,
            'movement_scope' => $scope,
            'division_id' => $scope === 'DIVISION' ? $divisionId : null,
            'destination_type' => $scope === 'DIVISION' ? $destinationType : 'GUDANG',
            'movement_date' => $movementDate,
            'item_id' => !empty($line['item_id']) ? (int)$line['item_id'] : null,
            'material_id' => !empty($line['material_id']) ? (int)$line['material_id'] : null,
            'buy_uom_id' => !empty($line['buy_uom_id']) ? (int)$line['buy_uom_id'] : null,
            'content_uom_id' => !empty($line['content_uom_id']) ? (int)$line['content_uom_id'] : null,
            'profile_key' => $this->nullableString($line['profile_key'] ?? null),
            'profile_name' => $this->nullableString($line['profile_name'] ?? null),
            'profile_brand' => $this->nullableString($line['profile_brand'] ?? null),
            'profile_description' => $this->nullableString($line['profile_description'] ?? null),
            'profile_expired_date' => $this->normalizeDate((string)($line['profile_expired_date'] ?? '')),
            'profile_content_per_buy' => $contentPerBuy,
            'profile_buy_uom_code' => $this->nullableString($line['profile_buy_uom_code'] ?? null),
            'profile_content_uom_code' => $this->nullableString($line['profile_content_uom_code'] ?? null),
            'ref_table' => 'inv_stock_adjustment',
            'ref_id' => (int)($header['id'] ?? 0),
            'created_by' => $userId > 0 ? $userId : null,
        ];
        $lineUpdates = [];
        if ($this->db->field_exists('stock_domain', 'inv_stock_adjustment_line')
            && array_key_exists('stock_domain', $line)
            && $line['stock_domain'] !== null
        ) {
            $lineUpdates['stock_domain'] = null;
        }
        $negativeMoves = [
            'qty_waste_content' => ['movement_type' => 'WASTE_OUT', 'category' => 'WASTE', 'issue_field' => 'waste_issue_id', 'reason_field' => 'waste_reason_code'],
            'qty_spoil_content' => ['movement_type' => 'SPOIL_OUT', 'category' => 'SPOILAGE', 'issue_field' => 'spoil_issue_id', 'reason_field' => 'spoil_reason_code'],
            'qty_process_loss_content' => ['movement_type' => 'PROCESS_LOSS_OUT', 'category' => 'PROCESS_LOSS', 'issue_field' => 'process_loss_issue_id', 'reason_field' => 'process_loss_reason_code'],
            'qty_variance_content' => ['movement_type' => 'VARIANCE_OUT', 'category' => 'VARIANCE', 'issue_field' => 'variance_issue_id', 'reason_field' => 'variance_reason_code'],
        ];

        $hasNegativeMutation = false;
        foreach (array_keys($negativeMoves) as $negativeColumn) {
            if (round((float)($line[$negativeColumn] ?? 0), 4) > 0) {
                $hasNegativeMutation = true;
                break;
            }
        }

        if ($hasNegativeMutation
            && ($basePayload['item_id'] !== null || $basePayload['material_id'] !== null)
            && $basePayload['content_uom_id'] !== null
        ) {
            $syncPayload = [
                'movement_date' => $movementDate,
                'item_id' => $basePayload['item_id'],
                'material_id' => $basePayload['material_id'],
                'buy_uom_id' => $basePayload['buy_uom_id'],
                'content_uom_id' => $basePayload['content_uom_id'],
                'profile_key' => $basePayload['profile_key'],
                'sync_note' => 'Pre-synced from FIFO lots before stock adjustment posting',
            ];
            if ($scope === 'DIVISION') {
                $syncPayload['division_id'] = $divisionId;
                $syncPayload['destination_type'] = $destinationType;
            }

            $preSync = $scope === 'DIVISION'
                ? $this->materialfifomanager->syncDivisionMonthlyStockFromLots($syncPayload)
                : $this->materialfifomanager->syncWarehouseMonthlyStockFromLots($syncPayload);
            if (!($preSync['ok'] ?? false)) {
                return [
                    'ok' => false,
                    'message' => (string)($preSync['message'] ?? 'Gagal sinkron saldo exact profile sebelum posting adjustment.'),
                ];
            }

            $currentBalance = $this->fetchStockAdjustmentCurrentBalance([
                'stock_scope' => $scope,
                'division_id' => $scope === 'DIVISION' ? $divisionId : null,
                'destination_type' => $scope === 'DIVISION' ? $destinationType : 'GUDANG',
                'item_id' => $basePayload['item_id'],
                'material_id' => $basePayload['material_id'],
                'buy_uom_id' => $basePayload['buy_uom_id'],
                'content_uom_id' => $basePayload['content_uom_id'],
                'profile_key' => $basePayload['profile_key'],
                'profile_name' => $basePayload['profile_name'],
                'profile_brand' => $basePayload['profile_brand'],
                'profile_description' => $basePayload['profile_description'],
                'profile_expired_date' => $basePayload['profile_expired_date'],
                'profile_content_per_buy' => $contentPerBuy,
            ]);
            if ($this->db->field_exists('stock_domain', 'inv_stock_adjustment_line')) {
                $lineUpdates['stock_domain'] = null;
            }
        }

        foreach ($negativeMoves as $column => $meta) {
            $qtyContent = round((float)($line[$column] ?? 0), 4);
            if ($qtyContent <= 0) {
                continue;
            }

            $qtyBuy = $this->convertStockAdjustmentQtyBuy($qtyContent, $contentPerBuy, !empty($line['buy_uom_id']));
            $notes = $this->buildStockAdjustmentMovementNote((string)($header['adjustment_no'] ?? ''), $meta['category'], (string)($line['note'] ?? ''), (string)($header['notes'] ?? ''));
            $reasonCode = $this->normalizeInventoryAdjustmentReasonCode((string)($line[$meta['reason_field']] ?? ''), $meta['category']) ?? 'other';
            $fifoPayload = [
                'division_id' => $scope === 'DIVISION' ? $divisionId : null,
                'destination_type' => $scope === 'DIVISION' ? $destinationType : 'GUDANG',
                'issue_date' => $movementDate,
                'item_id' => !empty($line['item_id']) ? (int)$line['item_id'] : null,
                'material_id' => !empty($line['material_id']) ? (int)$line['material_id'] : null,
                'buy_uom_id' => !empty($line['buy_uom_id']) ? (int)$line['buy_uom_id'] : null,
                'content_uom_id' => !empty($line['content_uom_id']) ? (int)$line['content_uom_id'] : null,
                'profile_key' => $this->nullableString($line['profile_key'] ?? null),
                'qty_content_out' => $qtyContent,
                'source_module' => 'INVENTORY_ADJUSTMENT',
                'source_table' => 'inv_stock_adjustment',
                'source_id' => (int)($header['id'] ?? 0),
                'source_line_id' => (int)($line['id'] ?? 0),
                'notes' => $notes,
            ];
            $fifo = $scope === 'DIVISION'
                ? $this->materialfifomanager->consumeDivisionUsage($fifoPayload)
                : $this->materialfifomanager->consumeWarehouseUsage($fifoPayload);
            if (!($fifo['ok'] ?? false)) {
                return ['ok' => false, 'message' => (string)($fifo['message'] ?? 'Gagal alokasi FIFO adjustment.')];
            }

            $ledger = $this->postInventoryLedgerEntry($basePayload + [
                'movement_type' => $meta['movement_type'],
                'qty_buy_delta' => $qtyBuy * -1,
                'qty_content_delta' => $qtyContent * -1,
                'unit_cost' => round((float)($fifo['data']['avg_unit_cost'] ?? ($line['unit_cost'] ?? 0)), 6),
                'adjustment_category' => $meta['category'],
                'adjustment_reason_code' => $reasonCode,
                'notes' => $notes,
            ]);
            if (!($ledger['ok'] ?? false)) {
                return ['ok' => false, 'message' => (string)($ledger['message'] ?? 'Gagal posting ledger adjustment.')];
            }

            $lineUpdates[$meta['issue_field']] = (int)($fifo['data']['issue_id'] ?? 0) > 0 ? (int)$fifo['data']['issue_id'] : null;
        }

        $qtyPlus = round((float)($line['qty_adjustment_plus_content'] ?? 0), 4);
        if ($qtyPlus > 0) {
            $qtyBuyPlus = $this->convertStockAdjustmentQtyBuy($qtyPlus, $contentPerBuy, !empty($line['buy_uom_id']));
            $unitCost = round((float)($line['unit_cost'] ?? 0), 6);
            if ($unitCost <= 0) {
                $unitCost = round((float)($currentBalance['avg_cost_per_content'] ?? 0), 6);
            }
            if ($unitCost <= 0) {
                return ['ok' => false, 'message' => 'Adjustment plus membutuhkan unit_cost yang valid.'];
            }

            $notes = $this->buildStockAdjustmentMovementNote((string)($header['adjustment_no'] ?? ''), 'ADJUSTMENT_PLUS', (string)($line['note'] ?? ''), (string)($header['notes'] ?? ''));
            $lot = $this->materialfifomanager->registerReceiptInboundLot([
                'location_scope' => $scope,
                'division_id' => $scope === 'DIVISION' ? $divisionId : null,
                'destination_type' => $scope === 'DIVISION' ? $destinationType : 'GUDANG',
                'receipt_date' => $movementDate,
                'movement_date' => $movementDate,
                'item_id' => !empty($line['item_id']) ? (int)$line['item_id'] : null,
                'material_id' => !empty($line['material_id']) ? (int)$line['material_id'] : null,
                'buy_uom_id' => !empty($line['buy_uom_id']) ? (int)$line['buy_uom_id'] : null,
                'content_uom_id' => !empty($line['content_uom_id']) ? (int)$line['content_uom_id'] : null,
                'profile_key' => $this->nullableString($line['profile_key'] ?? null),
                'lot_no' => $this->nullableString($line['inbound_lot_no'] ?? null),
                'expiry_date' => $this->normalizeDate((string)($line['inbound_expiry_date'] ?? '')),
                'qty_content_in' => $qtyPlus,
                'unit_cost' => $unitCost,
                'source_table' => 'inv_stock_adjustment',
                'source_id' => (int)($header['id'] ?? 0),
                'source_line_id' => (int)($line['id'] ?? 0),
            ]);
            if (!($lot['ok'] ?? false)) {
                return ['ok' => false, 'message' => (string)($lot['message'] ?? 'Gagal membuat lot inbound adjustment.')];
            }

            // Rekonsiliasi: jika saldo bulanan sebelum adjustment negatif, lot hanya boleh
            // ada sebesar saldo positif baru. Sisa mengisi "hutang" stok, tidak jadi lot fisik.
            $preBalance = round((float)($currentBalance['qty_content_balance'] ?? 0), 4);
            if ($preBalance < -0.0001) {
                $newBalance    = round($preBalance + $qtyPlus, 4);
                $effectiveQty  = max(0.0, $newBalance);
                $lotId         = (int)($lot['data']['lot_id'] ?? 0);
                if ($lotId > 0 && $effectiveQty < $qtyPlus - 0.0001) {
                    $this->db->where('id', $lotId)->update('inv_material_fifo_lot', [
                        'qty_out'     => round($qtyPlus - $effectiveQty, 4),
                        'qty_balance' => round($effectiveQty, 4),
                        'status'      => $effectiveQty < 0.0001 ? 'CLOSED' : 'OPEN',
                        'updated_at'  => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            $ledger = $this->postInventoryLedgerEntry($basePayload + [
                'movement_type' => 'ADJUSTMENT_IN',
                'qty_buy_delta' => $qtyBuyPlus,
                'qty_content_delta' => $qtyPlus,
                'unit_cost' => $unitCost,
                'adjustment_category' => 'ADJUSTMENT_PLUS',
                'adjustment_reason_code' => $this->normalizeInventoryAdjustmentReasonCode((string)($line['adjustment_plus_reason_code'] ?? ''), 'ADJUSTMENT_PLUS') ?? 'other',
                'notes' => $notes,
            ]);
            if (!($ledger['ok'] ?? false)) {
                return ['ok' => false, 'message' => (string)($ledger['message'] ?? 'Gagal posting ledger adjustment plus.')];
            }

            $lineUpdates['adjustment_plus_lot_id'] = (int)($lot['data']['lot_id'] ?? 0) > 0 ? (int)$lot['data']['lot_id'] : null;
        }

        return ['ok' => true, 'data' => ['line_updates' => $lineUpdates]];
    }

    private function syncPostedStockAdjustmentProfiles(array $header, array $lines): array
    {
        $scope = strtoupper((string)($header['stock_scope'] ?? 'WAREHOUSE'));
        $movementDate = (string)($header['adjustment_date'] ?? date('Y-m-d'));
        $divisionId = !empty($header['division_id']) ? (int)$header['division_id'] : null;
        $destinationType = $scope === 'DIVISION'
            ? $this->normalizeStockAdjustmentDestination((string)($header['destination_type'] ?? ''), true)
            : 'GUDANG';

        $targets = [];
        foreach ($lines as $line) {
            $itemId = !empty($line['item_id']) ? (int)$line['item_id'] : null;
            $materialId = !empty($line['material_id']) ? (int)$line['material_id'] : null;
            $contentUomId = !empty($line['content_uom_id']) ? (int)$line['content_uom_id'] : null;
            if ($itemId === null && $materialId === null) {
                continue;
            }
            if ($contentUomId === null) {
                continue;
            }

            $hasNegativeMutation = round((float)($line['qty_waste_content'] ?? 0), 4) > 0
                || round((float)($line['qty_spoil_content'] ?? 0), 4) > 0
                || round((float)($line['qty_process_loss_content'] ?? 0), 4) > 0
                || round((float)($line['qty_variance_content'] ?? 0), 4) > 0;
            if (!$hasNegativeMutation) {
                continue;
            }

            $target = [
                'movement_date' => $movementDate,
                'item_id' => $itemId,
                'material_id' => $materialId,
                'buy_uom_id' => !empty($line['buy_uom_id']) ? (int)$line['buy_uom_id'] : null,
                'content_uom_id' => $contentUomId,
                'profile_key' => $this->nullableString($line['profile_key'] ?? null),
                'sync_note' => 'Synced from FIFO lots after stock adjustment posting',
            ];
            if ($scope === 'DIVISION') {
                $target['division_id'] = $divisionId;
                $target['destination_type'] = $destinationType;
            }

            $targetKey = implode('|', [
                $scope,
                $scope === 'DIVISION' ? (string)$divisionId : 'NULL',
                $scope === 'DIVISION' ? (string)$destinationType : 'GUDANG',
                (string)($itemId ?? 0),
                (string)($materialId ?? 0),
                (string)($target['buy_uom_id'] ?? 0),
                (string)$contentUomId,
                (string)($target['profile_key'] ?? ''),
            ]);
            $targets[$targetKey] = $target;
        }

        foreach (array_values($targets) as $target) {
            $sync = $scope === 'DIVISION'
                ? $this->materialfifomanager->syncDivisionMonthlyStockFromLots($target)
                : $this->materialfifomanager->syncWarehouseMonthlyStockFromLots($target);
            if (!($sync['ok'] ?? false)) {
                return [
                    'ok' => false,
                    'message' => (string)($sync['message'] ?? 'Gagal sinkron saldo exact profile setelah posting adjustment.'),
                ];
            }
        }

        return ['ok' => true, 'data' => ['sync_count' => count($targets)]];
    }

    private function normalizeStockAdjustmentLine(string $scope, ?int $divisionId, ?string $destinationType, array $line): ?array
    {
        $itemId = !empty($line['item_id']) ? (int)$line['item_id'] : null;
        $materialId = !empty($line['material_id']) ? (int)$line['material_id'] : null;
        $buyUomId = !empty($line['buy_uom_id']) ? (int)$line['buy_uom_id'] : null;
        $contentUomId = !empty($line['content_uom_id']) ? (int)$line['content_uom_id'] : null;
        $profileKey = $this->nullableString($line['profile_key'] ?? null);
        if ($itemId === null && $materialId === null) {
            return null;
        }
        if ($scope === 'WAREHOUSE' && $itemId === null) {
            return null;
        }
        if ($contentUomId === null) {
            return null;
        }

        $qtyWaste = round((float)($line['qty_waste_content'] ?? 0), 4);
        $qtySpoil = round((float)($line['qty_spoil_content'] ?? 0), 4);
        $qtyProcessLoss = round((float)($line['qty_process_loss_content'] ?? 0), 4);
        $qtyVariance = round((float)($line['qty_variance_content'] ?? 0), 4);
        $qtyPlus = round((float)($line['qty_adjustment_plus_content'] ?? 0), 4);
        if ($qtyWaste <= 0 && $qtySpoil <= 0 && $qtyProcessLoss <= 0 && $qtyVariance <= 0 && $qtyPlus <= 0) {
            return null;
        }

        $balance = $this->fetchStockAdjustmentCurrentBalance([
            'stock_scope' => $scope,
            'division_id' => $divisionId,
            'destination_type' => $destinationType,
            'item_id' => $itemId,
            'material_id' => $materialId,
            'buy_uom_id' => $buyUomId,
            'content_uom_id' => $contentUomId,
            'profile_key' => $profileKey,
        ]);

        return [
            'stock_domain' => null,
            'item_id' => $itemId,
            'material_id' => $materialId,
            'buy_uom_id' => $buyUomId,
            'content_uom_id' => $contentUomId,
            'profile_key' => $profileKey,
            'profile_name' => $this->nullableString($line['profile_name'] ?? null),
            'profile_brand' => $this->nullableString($line['profile_brand'] ?? null),
            'profile_description' => $this->nullableString($line['profile_description'] ?? null),
            'profile_expired_date' => $this->normalizeDate((string)($line['profile_expired_date'] ?? '')),
            'profile_content_per_buy' => round(max(0.000001, (float)($line['profile_content_per_buy'] ?? 1)), 6),
            'profile_buy_uom_code' => $this->nullableString($line['profile_buy_uom_code'] ?? null),
            'profile_content_uom_code' => $this->nullableString($line['profile_content_uom_code'] ?? null),
            'available_qty_buy' => round((float)($balance['qty_buy_balance'] ?? 0), 4),
            'available_qty_content' => round((float)($balance['qty_content_balance'] ?? 0), 4),
            'unit_cost' => round((float)($line['unit_cost'] ?? ($balance['avg_cost_per_content'] ?? 0)), 6),
            'qty_waste_content' => $qtyWaste,
            'waste_reason_code' => $qtyWaste > 0 ? ($this->normalizeInventoryAdjustmentReasonCode((string)($line['waste_reason_code'] ?? ''), 'WASTE') ?? 'other') : null,
            'qty_spoil_content' => $qtySpoil,
            'spoil_reason_code' => $qtySpoil > 0 ? ($this->normalizeInventoryAdjustmentReasonCode((string)($line['spoil_reason_code'] ?? ''), 'SPOILAGE') ?? 'other') : null,
            'qty_process_loss_content' => $qtyProcessLoss,
            'process_loss_reason_code' => $qtyProcessLoss > 0 ? ($this->normalizeInventoryAdjustmentReasonCode((string)($line['process_loss_reason_code'] ?? ''), 'PROCESS_LOSS') ?? 'other') : null,
            'qty_variance_content' => $qtyVariance,
            'variance_reason_code' => $qtyVariance > 0 ? ($this->normalizeInventoryAdjustmentReasonCode((string)($line['variance_reason_code'] ?? ''), 'VARIANCE') ?? 'other') : null,
            'qty_adjustment_plus_content' => $qtyPlus,
            'adjustment_plus_reason_code' => $qtyPlus > 0 ? ($this->normalizeInventoryAdjustmentReasonCode((string)($line['adjustment_plus_reason_code'] ?? ''), 'ADJUSTMENT_PLUS') ?? 'other') : null,
            'inbound_lot_no' => $this->nullableString($line['inbound_lot_no'] ?? null),
            'inbound_expiry_date' => $this->normalizeDate((string)($line['inbound_expiry_date'] ?? '')),
            'note' => $this->nullableString($line['note'] ?? null),
        ];
    }

    private function normalizeInventoryAdjustmentReasonCode(string $value, string $category): ?string
    {
        $category = strtoupper(trim($category));
        $value = strtolower(trim($value));
        if ($value === '') {
            return null;
        }

        $reasonMap = [
            'WASTE' => ['cancel_order', 'kitchen_error', 'overproduction', 'spillage', 'prep_trim_excess', 'expired_opened', 'other'],
            'SPOILAGE' => ['expired', 'temperature_abuse', 'contamination', 'overstock', 'improper_storage', 'other'],
            'PROCESS_LOSS' => ['defrost_loss', 'trimming_standard', 'cooking_loss', 'evaporation', 'brew_loss', 'absorption_loss', 'process_residue', 'variable_process_consumable', 'other'],
            'VARIANCE' => ['over_usage', 'under_usage', 'unrecorded_usage', 'counting_error', 'system_mismatch', 'theft_suspected', 'unknown_shrinkage', 'other'],
            'ADJUSTMENT_PLUS' => ['opening_correction', 'stock_found', 'manual_reclass', 'other'],
        ];

        if (!isset($reasonMap[$category])) {
            return null;
        }

        return in_array($value, $reasonMap[$category], true) ? $value : 'other';
    }

    private function fetchStockAdjustmentCurrentBalance(array $identity): array
    {
        $scope = strtoupper(trim((string)($identity['stock_scope'] ?? 'WAREHOUSE')));
        $itemId = $this->nullableInt($identity['item_id'] ?? null);
        $materialId = $this->nullableInt($identity['material_id'] ?? null);
        $legacyStockDomain = 'ITEM';

        if ($scope === 'DIVISION') {
            if ($this->db->table_exists('inv_division_monthly_stock')) {
                $targetMonth = date('Y-m-01');
                $latestMonthSubquery = $this->db
                    ->select('division_id, destination_type, identity_key, MAX(month_key) AS month_key', false)
                    ->from('inv_division_monthly_stock')
                    ->where('month_key <=', $targetMonth)
                    ->group_by(['division_id', 'destination_type', 'identity_key'])
                    ->get_compiled_select();
                $identityKey = $this->buildInventoryMonthlyIdentityKey([
                    'stock_domain' => 'ITEM',
                    'item_id' => $itemId,
                    'material_id' => $materialId,
                    'buy_uom_id' => $identity['buy_uom_id'] ?? null,
                    'content_uom_id' => $identity['content_uom_id'] ?? null,
                    'profile_key' => $identity['profile_key'] ?? null,
                    'profile_name' => $identity['profile_name'] ?? null,
                    'profile_brand' => $identity['profile_brand'] ?? null,
                    'profile_description' => $identity['profile_description'] ?? null,
                    'profile_content_per_buy' => $identity['profile_content_per_buy'] ?? 1,
                    'profile_expired_date' => $identity['profile_expired_date'] ?? null,
                ]);
                $row = $this->db
                    ->select('s.closing_qty_buy AS qty_buy_balance, s.closing_qty_content AS qty_content_balance, s.avg_cost_per_content', false)
                    ->select('COALESCE(s.updated_at, s.last_movement_at, CONCAT(s.month_key, " 00:00:00")) AS updated_at', false)
                    ->from('inv_division_monthly_stock s')
                    ->join('(' . $latestMonthSubquery . ') lm', 'lm.division_id = s.division_id AND lm.destination_type = s.destination_type AND lm.identity_key = s.identity_key AND lm.month_key = s.month_key', 'inner', false)
                    ->where('s.division_id', $identity['division_id'] ?? null)
                    ->where('s.destination_type', strtoupper((string)($identity['destination_type'] ?? 'OTHER')))
                    ->where('s.identity_key', $identityKey)
                    ->limit(1)
                    ->get()
                    ->row_array();
                if (!empty($row)) {
                    return [
                        'qty_buy_balance' => (float)($row['qty_buy_balance'] ?? 0),
                        'qty_content_balance' => (float)($row['qty_content_balance'] ?? 0),
                        'avg_cost_per_content' => (float)($row['avg_cost_per_content'] ?? 0),
                        'updated_at' => $row['updated_at'] ?? null,
                        'stock_domain' => 'ITEM',
                    ];
                }
            }

            return ['qty_buy_balance' => 0.0, 'qty_content_balance' => 0.0, 'avg_cost_per_content' => 0.0, 'updated_at' => null, 'stock_domain' => $legacyStockDomain];
        } else {
            if ($this->db->table_exists('inv_warehouse_monthly_stock')) {
                $targetMonth = date('Y-m-01');
                $latestMonthSubquery = $this->db
                    ->select('identity_key, MAX(month_key) AS month_key', false)
                    ->from('inv_warehouse_monthly_stock')
                    ->where('month_key <=', $targetMonth)
                    ->group_by('identity_key')
                    ->get_compiled_select();
                $identityKey = $this->buildInventoryMonthlyIdentityKey([
                    'stock_domain' => 'ITEM',
                    'item_id' => $itemId,
                    'material_id' => $materialId,
                    'buy_uom_id' => $identity['buy_uom_id'] ?? null,
                    'content_uom_id' => $identity['content_uom_id'] ?? null,
                    'profile_key' => $identity['profile_key'] ?? null,
                    'profile_name' => $identity['profile_name'] ?? null,
                    'profile_brand' => $identity['profile_brand'] ?? null,
                    'profile_description' => $identity['profile_description'] ?? null,
                    'profile_content_per_buy' => $identity['profile_content_per_buy'] ?? 1,
                    'profile_expired_date' => $identity['profile_expired_date'] ?? null,
                ]);
                $row = $this->db
                    ->select('s.closing_qty_buy AS qty_buy_balance, s.closing_qty_content AS qty_content_balance, s.avg_cost_per_content', false)
                    ->select('COALESCE(s.updated_at, s.last_movement_at, CONCAT(s.month_key, " 00:00:00")) AS updated_at', false)
                    ->from('inv_warehouse_monthly_stock s')
                    ->join('(' . $latestMonthSubquery . ') lm', 'lm.identity_key = s.identity_key AND lm.month_key = s.month_key', 'inner', false)
                    ->where('s.identity_key', $identityKey)
                    ->limit(1)
                    ->get()
                    ->row_array();
                if (!empty($row)) {
                    return [
                        'qty_buy_balance' => (float)($row['qty_buy_balance'] ?? 0),
                        'qty_content_balance' => (float)($row['qty_content_balance'] ?? 0),
                        'avg_cost_per_content' => (float)($row['avg_cost_per_content'] ?? 0),
                        'updated_at' => $row['updated_at'] ?? null,
                        'stock_domain' => 'ITEM',
                    ];
                }
            }

            return ['qty_buy_balance' => 0.0, 'qty_content_balance' => 0.0, 'avg_cost_per_content' => 0.0, 'updated_at' => null, 'stock_domain' => $legacyStockDomain];
        }

        return [
            'qty_buy_balance' => (float)($row['qty_buy_balance'] ?? 0),
            'qty_content_balance' => (float)($row['qty_content_balance'] ?? 0),
            'avg_cost_per_content' => (float)($row['avg_cost_per_content'] ?? 0),
            'updated_at' => !empty($row['updated_at']) ? (string)$row['updated_at'] : null,
            'stock_domain' => $legacyStockDomain,
        ];
    }

    private function normalizeStockAdjustmentDestination(?string $value, bool $strict): ?string
    {
        $value = strtoupper(trim((string)$value));
        if ($value === '') {
            return $strict ? null : 'ALL';
        }
        $allowed = ['GUDANG', 'BAR', 'KITCHEN', 'BAR_EVENT', 'KITCHEN_EVENT', 'OFFICE', 'OTHER', 'ALL'];
        if (!in_array($value, $allowed, true)) {
            return $strict ? null : 'ALL';
        }
        if ($strict && in_array($value, ['ALL', 'GUDANG'], true)) {
            return null;
        }
        return $value;
    }

    private function convertStockAdjustmentQtyBuy(float $qtyContent, float $contentPerBuy, bool $hasBuyUom): float
    {
        if (!$hasBuyUom) {
            return 0.0;
        }
        if ($contentPerBuy <= 0) {
            $contentPerBuy = 1.0;
        }
        return round($qtyContent / $contentPerBuy, 4);
    }

    private function buildStockAdjustmentMovementNote(string $adjustmentNo, string $category, string $lineNote, string $headerNote): string
    {
        $parts = [];
        if ($adjustmentNo !== '') {
            $parts[] = $adjustmentNo;
        }
        $parts[] = strtoupper($category);
        if (trim($lineNote) !== '') {
            $parts[] = trim($lineNote);
        } elseif (trim($headerNote) !== '') {
            $parts[] = trim($headerNote);
        }
        return implode(' | ', $parts);
    }

    private function generateStockAdjustmentNo(string $scope, string $date): string
    {
        $prefix = strtoupper($scope) === 'DIVISION' ? 'IAD' : 'IAW';
        $dayKey = date('Ymd', strtotime($date));
        do {
            $candidate = $prefix . $dayKey . '-' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $exists = (int)$this->db->where('adjustment_no', $candidate)->count_all_results('inv_stock_adjustment');
        } while ($exists > 0);

        return $candidate;
    }

    private function findOpeningSnapshotRowForUpdate(string $table, array $criteria): ?array
    {
        $hasDestinationType = $this->db->field_exists('destination_type', $table);
        $sql = 'SELECT * FROM ' . $table . ' WHERE snapshot_month = ?';
        $sql .= ' AND item_id = ? AND material_id <=> ? AND buy_uom_id = ? AND content_uom_id = ? AND profile_key <=> ?';
        $params = [
            $criteria['snapshot_month'] ?? null,
        ];
        $params[] = $criteria['item_id'] ?? null;
        $params[] = $criteria['material_id'] ?? null;
        $params[] = $criteria['buy_uom_id'] ?? null;
        $params[] = $criteria['content_uom_id'] ?? null;
        $params[] = $criteria['profile_key'] ?? null;

        if ($table === 'inv_division_stock_opening_snapshot') {
            $sql .= ' AND division_id = ?';
            $params[] = $criteria['division_id'] ?? null;
            if ($hasDestinationType) {
                $sql .= ' AND destination_type = ?';
                $params[] = $criteria['destination_type'] ?? 'OTHER';
            }
        }

        $sql .= ' ORDER BY id DESC';
        $sql .= ' LIMIT 1 FOR UPDATE';

        $row = $this->db->query($sql, $params)->row_array();

        return !empty($row) ? $row : null;
    }

    private function findOpeningSnapshotReplaceCandidate(string $table, array $criteria): ?array
    {
        $hasDestinationType = $this->db->field_exists('destination_type', $table);
        $hasProfileExpiredDate = $this->db->field_exists('profile_expired_date', $table);
        $sql = 'SELECT * FROM ' . $table . ' WHERE snapshot_month = ?';
        $sql .= ' AND item_id = ? AND material_id <=> ? AND buy_uom_id = ? AND content_uom_id = ?';
        $params = [
            $criteria['snapshot_month'] ?? null,
        ];
        $params[] = $criteria['item_id'] ?? null;
        $params[] = $criteria['material_id'] ?? null;
        $params[] = $criteria['buy_uom_id'] ?? null;
        $params[] = $criteria['content_uom_id'] ?? null;

        $sql .= ' AND UPPER(TRIM(COALESCE(profile_name, \'\'))) = ?';
        $params[] = strtoupper(trim((string)($criteria['profile_name'] ?? '')));

        $sql .= ' AND UPPER(TRIM(COALESCE(profile_brand, \'\'))) = ?';
        $params[] = strtoupper(trim((string)($criteria['profile_brand'] ?? '')));

        $sql .= ' AND UPPER(TRIM(COALESCE(profile_description, \'\'))) = ?';
        $params[] = strtoupper(trim((string)($criteria['profile_description'] ?? '')));

        if ($hasProfileExpiredDate) {
            $sql .= ' AND profile_expired_date <=> ?';
            $params[] = $criteria['profile_expired_date'] ?? null;
        }

        $sql .= ' AND ROUND(COALESCE(profile_content_per_buy, 0), 6) = ?';
        $params[] = round((float)($criteria['profile_content_per_buy'] ?? 0), 6);

        $sql .= ' AND ROUND(COALESCE(opening_avg_cost_per_content, 0), 6) = ?';
        $params[] = round((float)($criteria['opening_avg_cost_per_content'] ?? 0), 6);

        if ($table === 'inv_division_stock_opening_snapshot') {
            $sql .= ' AND division_id = ?';
            $params[] = $criteria['division_id'] ?? null;
            if ($hasDestinationType) {
                $sql .= ' AND destination_type = ?';
                $params[] = $criteria['destination_type'] ?? 'OTHER';
            }
        }

        $sql .= ' ORDER BY id ASC';
        $sql .= ' LIMIT 2 FOR UPDATE';
        $rows = $this->db->query($sql, $params)->result_array();
        if (count($rows) !== 1) {
            return null;
        }

        return $rows[0];
    }

    private function buildOpeningSnapshotHistoryIdentity(string $stockScope, array $snapshot): array
    {
        return [
            'division_id' => $stockScope === 'DIVISION' && !empty($snapshot['division_id']) ? (int)$snapshot['division_id'] : null,
            'destination_type' => $stockScope === 'DIVISION' ? strtoupper(trim((string)($snapshot['destination_type'] ?? 'OTHER'))) : null,
            'stock_domain' => 'ITEM',
            'item_id' => !empty($snapshot['item_id']) ? (int)$snapshot['item_id'] : null,
            'material_id' => !empty($snapshot['material_id']) ? (int)$snapshot['material_id'] : null,
            'buy_uom_id' => !empty($snapshot['buy_uom_id']) ? (int)$snapshot['buy_uom_id'] : null,
            'content_uom_id' => !empty($snapshot['content_uom_id']) ? (int)$snapshot['content_uom_id'] : null,
            'profile_key' => $this->nullableString($snapshot['profile_key'] ?? null),
            'profile_name' => $this->nullableString($snapshot['profile_name'] ?? null),
            'profile_brand' => $this->nullableString($snapshot['profile_brand'] ?? null),
            'profile_description' => $this->nullableString($snapshot['profile_description'] ?? null),
            'profile_expired_date' => $this->normalizeDate((string)($snapshot['profile_expired_date'] ?? '')),
            'profile_content_per_buy' => round((float)($snapshot['profile_content_per_buy'] ?? 0), 6),
            'profile_buy_uom_code' => $this->nullableString($snapshot['profile_buy_uom_code'] ?? null),
            'profile_content_uom_code' => $this->nullableString($snapshot['profile_content_uom_code'] ?? null),
            'avg_cost_per_content' => round((float)($snapshot['opening_avg_cost_per_content'] ?? 0), 6),
        ];
    }

    private function syncOpeningSnapshotLots(string $stockScope, string $openingTable, array $snapshot, bool $registerAfterRollback): array
    {
        if ($stockScope !== 'DIVISION') {
            return ['ok' => true];
        }

        $snapshotId = (int)($snapshot['id'] ?? 0);
        if ($snapshotId <= 0) {
            return ['ok' => false, 'message' => 'Snapshot opening belum memiliki ID untuk sinkron lot.'];
        }

        $this->load->library('MaterialFifoManager');
        $fifoReady = $this->materialfifomanager->ensureReady();
        if (!($fifoReady['ok'] ?? false)) {
            return $fifoReady;
        }

        $rollback = $this->materialfifomanager->rollbackReceiptInboundLotsBySource($openingTable, $snapshotId, null);
        if (!($rollback['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => 'Opening snapshot tidak bisa diproses karena lot awal sudah terpakai: ' . (string)($rollback['message'] ?? 'rollback lot gagal.'),
            ];
        }

        $qtyContent = round((float)($snapshot['opening_qty_content'] ?? 0), 4);
        if (!$registerAfterRollback || $qtyContent <= 0) {
            return ['ok' => true, 'data' => ['lot_count' => 0]];
        }

        $destinationType = strtoupper(trim((string)($snapshot['destination_type'] ?? 'OTHER')));
        if ($destinationType === '' || $destinationType === 'ALL') {
            $destinationType = 'OTHER';
        }

        $register = $this->materialfifomanager->registerReceiptInboundLot([
            'location_scope' => 'DIVISION',
            'division_id' => !empty($snapshot['division_id']) ? (int)$snapshot['division_id'] : null,
            'destination_type' => $destinationType,
            'item_id' => !empty($snapshot['item_id']) ? (int)$snapshot['item_id'] : null,
            'material_id' => !empty($snapshot['material_id']) ? (int)$snapshot['material_id'] : null,
            'buy_uom_id' => !empty($snapshot['buy_uom_id']) ? (int)$snapshot['buy_uom_id'] : null,
            'content_uom_id' => !empty($snapshot['content_uom_id']) ? (int)$snapshot['content_uom_id'] : null,
            'profile_key' => $this->nullableString($snapshot['profile_key'] ?? null),
            'profile_expired_date' => $this->normalizeDate((string)($snapshot['profile_expired_date'] ?? '')),
            'qty_content_in' => $qtyContent,
            'unit_cost' => round((float)($snapshot['opening_avg_cost_per_content'] ?? 0), 6),
            'receipt_date' => (string)($snapshot['snapshot_month'] ?? date('Y-m-01')),
            'source_table' => $openingTable,
            'source_id' => $snapshotId,
            'source_line_id' => null,
        ]);
        if (!($register['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => 'Gagal membentuk lot opening snapshot: ' . (string)($register['message'] ?? 'registrasi lot gagal.'),
            ];
        }

        return $register;
    }

    private function purgeOpeningMovementEntries(string $stockScope, string $openingTable, string $movementDate, array $identity): void
    {
        if (!$this->db->table_exists('inv_stock_movement_log')) {
            return;
        }

        $hasDestinationType = $this->db->field_exists('destination_type', 'inv_stock_movement_log');

        $this->db
            ->where('movement_scope', $stockScope)
            ->where('movement_date', $movementDate)
            ->where('ref_table', $openingTable)
            ->where('item_id', $identity['item_id'] ?? 0)
            ->where('buy_uom_id', $identity['buy_uom_id'] ?? 0)
            ->where('content_uom_id', $identity['content_uom_id'] ?? 0);

        $materialId = $identity['material_id'] ?? null;
        if ($materialId !== null) {
            $this->db->where('material_id', $materialId);
        } else {
            $this->db->where('material_id IS NULL', null, false);
        }

        if ($stockScope === 'DIVISION') {
            $this->db->where('division_id', $identity['division_id'] ?? 0);
            if ($hasDestinationType) {
                $this->db->where('destination_type', $identity['destination_type'] ?? 'OTHER');
            }
        }

        $profileKey = trim((string)($identity['profile_key'] ?? ''));
        if ($profileKey !== '') {
            $this->db->group_start()
                ->where('profile_key', $profileKey)
                ->or_like('profile_key', substr($profileKey, 0, 40), 'after')
                ->group_end();
        }

        $profileName = strtoupper(trim((string)($identity['profile_name'] ?? '')));
        if ($profileName !== '') {
            $this->db->where("UPPER(TRIM(COALESCE(profile_name,''))) = " . $this->db->escape($profileName), null, false);
        }
        $profileBrand = strtoupper(trim((string)($identity['profile_brand'] ?? '')));
        if ($profileBrand !== '') {
            $this->db->where("UPPER(TRIM(COALESCE(profile_brand,''))) = " . $this->db->escape($profileBrand), null, false);
        }
        $profileDescription = strtoupper(trim((string)($identity['profile_description'] ?? '')));
        if ($profileDescription !== '') {
            $this->db->where("UPPER(TRIM(COALESCE(profile_description,''))) = " . $this->db->escape($profileDescription), null, false);
        }

        $this->db->delete('inv_stock_movement_log');
    }

    private function rebuild_inventory_history_from_opening(string $stockScope, string $startDate, array $identity): array
    {
        $startDate = $this->normalizeDate($startDate);
        if ($startDate === null) {
            return [
                'ok' => false,
                'message' => 'Tanggal rebuild opening tidak valid.',
            ];
        }

        $openingTable = $this->openingSnapshotTableForScope($stockScope);
        $monthlyTable = $stockScope === 'DIVISION' ? 'inv_division_monthly_stock' : 'inv_warehouse_monthly_stock';
        if (!$this->db->table_exists($openingTable) || !$this->db->table_exists($monthlyTable)) {
            return [
                'ok' => false,
                'message' => 'Tabel histori opening/monthly stock belum lengkap untuk rebuild.',
            ];
        }

        $today = date('Y-m-d');
        $startMonth = date('Y-m-01', strtotime($startDate));
        $todayMonth = date('Y-m-01');
        $hasMovementLog = $this->db->table_exists('inv_stock_movement_log');
        $hasMovementDestination = $stockScope === 'DIVISION' && $this->db->field_exists('destination_type', 'inv_stock_movement_log');
        $hasMovementAdjustmentCategory = $this->db->field_exists('adjustment_category', 'inv_stock_movement_log');
        $hasMovementProfileExpiredDate = $this->db->field_exists('profile_expired_date', 'inv_stock_movement_log');
        $movementRows = [];
        if ($hasMovementLog) {
            $movementColumns = [
                'l.id, l.movement_date, l.movement_type, l.item_id, l.material_id, l.buy_uom_id, l.content_uom_id, l.profile_key',
                'l.profile_name, l.profile_brand, l.profile_description, l.profile_content_per_buy, l.profile_buy_uom_code, l.profile_content_uom_code',
                'l.qty_buy_delta, l.qty_content_delta, l.unit_cost, l.created_at, l.ref_table',
            ];
            $movementColumns[] = $hasMovementAdjustmentCategory ? 'l.adjustment_category' : 'NULL AS adjustment_category';
            $movementColumns[] = $hasMovementProfileExpiredDate ? 'l.profile_expired_date' : 'NULL AS profile_expired_date';
            if ($stockScope === 'DIVISION' && $hasMovementDestination) {
                $movementColumns[] = 'l.destination_type';
            }

            $this->db
                ->select(implode(', ', $movementColumns), false)
                ->from('inv_stock_movement_log l')
                ->where('l.movement_scope', $stockScope)
                ->where('l.movement_date >=', $startDate)
                ->where('l.movement_date <=', $today)
                ->where("COALESCE(l.ref_table,'') <> " . $this->db->escape($openingTable), null, false);
            $this->applyInventoryHistoryIdentityFilter('l', $stockScope, $identity, $hasMovementDestination);
            $movementRows = $this->db
                ->order_by('l.movement_date', 'ASC')
                ->order_by('l.id', 'ASC')
                ->get()
                ->result_array();
        }

        $this->db
            ->from($openingTable . ' s')
            ->where('s.snapshot_month >=', $startMonth)
            ->where('s.snapshot_month <=', $todayMonth);
        $this->applyOpeningHistoryIdentityFilter('s', $stockScope, $identity);
        $snapshotRows = $this->db
            ->order_by('s.snapshot_month', 'ASC')
            ->get()
            ->result_array();

        if (empty($snapshotRows) && empty($movementRows)) {
            $this->purgeInventoryMonthlyStockForIdentity($stockScope, $identity, null);
            return [
                'ok' => true,
                'message' => 'Tidak ada snapshot/movement untuk direbuild. Monthly stock terkait dibersihkan.',
                'data' => ['days_rebuilt' => 0],
            ];
        }

        $lastMovementDate = $startDate;
        foreach ($movementRows as $movementRow) {
            $candidateDate = (string)($movementRow['movement_date'] ?? '');
            if ($candidateDate !== '' && $candidateDate > $lastMovementDate) {
                $lastMovementDate = $candidateDate;
            }
        }

        $lastSnapshotDate = $startDate;
        $snapshotByMonth = [];
        foreach ($snapshotRows as $snapshotRow) {
            $monthKey = (string)($snapshotRow['snapshot_month'] ?? '');
            if ($monthKey === '') {
                continue;
            }
            $snapshotByMonth[$monthKey] = $snapshotRow;
            if ($monthKey > $lastSnapshotDate) {
                $lastSnapshotDate = $monthKey;
            }
        }

        $lastRelevantDate = max($startDate, $lastMovementDate, $lastSnapshotDate);
        $movementByDate = [];
        foreach ($movementRows as $movementRow) {
            $movementDay = (string)($movementRow['movement_date'] ?? '');
            if ($movementDay === '') {
                continue;
            }
            if (!isset($movementByDate[$movementDay])) {
                $movementByDate[$movementDay] = [];
            }
            $movementByDate[$movementDay][] = $movementRow;
        }

        $dayMap = [];
        foreach (array_keys($snapshotByMonth) as $snapshotMonthKey) {
            if ($snapshotMonthKey >= $startDate && $snapshotMonthKey <= $lastRelevantDate) {
                $dayMap[$snapshotMonthKey] = true;
            }
        }
        foreach (array_keys($movementByDate) as $movementDay) {
            if ($movementDay >= $startDate && $movementDay <= $lastRelevantDate) {
                $dayMap[$movementDay] = true;
            }
        }

        if (empty($dayMap)) {
            return [
                'ok' => true,
                'message' => 'Tidak ada hari yang perlu direbuild.',
                'data' => ['days_rebuilt' => 0],
            ];
        }

        ksort($dayMap);
        $days = array_keys($dayMap);
        $currentBuy = 0.0;
        $currentContent = 0.0;
        $currentAvg = 0.0;
        $lastProfile = [
            'stock_domain' => (string)($identity['stock_domain'] ?? 'ITEM'),
            'item_id' => $identity['item_id'] ?? null,
            'material_id' => $identity['material_id'] ?? null,
            'buy_uom_id' => $identity['buy_uom_id'] ?? null,
            'content_uom_id' => $identity['content_uom_id'] ?? null,
            'profile_key' => $identity['profile_key'] ?? null,
            'profile_name' => $this->nullableString($identity['profile_name'] ?? null),
            'profile_brand' => $this->nullableString($identity['profile_brand'] ?? null),
            'profile_description' => $this->nullableString($identity['profile_description'] ?? null),
            'profile_expired_date' => $this->normalizeDate((string)($identity['profile_expired_date'] ?? '')),
            'profile_content_per_buy' => round((float)($identity['profile_content_per_buy'] ?? 0), 6),
            'profile_buy_uom_code' => $this->nullableString($identity['profile_buy_uom_code'] ?? null),
            'profile_content_uom_code' => $this->nullableString($identity['profile_content_uom_code'] ?? null),
        ];

        $monthlyRows = [];
        foreach ($days as $day) {
            $monthKey = date('Y-m-01', strtotime($day));
            if (isset($snapshotByMonth[$monthKey]) && $day === $monthKey) {
                $snapshot = $snapshotByMonth[$monthKey];
                $currentBuy = round((float)($snapshot['opening_qty_buy'] ?? 0), 4);
                $currentContent = round((float)($snapshot['opening_qty_content'] ?? 0), 4);
                $currentAvg = round((float)($snapshot['opening_avg_cost_per_content'] ?? 0), 6);
                $lastProfile = [
                    'stock_domain' => (string)($snapshot['stock_domain'] ?? $lastProfile['stock_domain']),
                    'item_id' => $snapshot['item_id'] ?? $lastProfile['item_id'],
                    'material_id' => $snapshot['material_id'] ?? $lastProfile['material_id'],
                    'buy_uom_id' => $snapshot['buy_uom_id'] ?? $lastProfile['buy_uom_id'],
                    'content_uom_id' => $snapshot['content_uom_id'] ?? $lastProfile['content_uom_id'],
                    'profile_key' => $snapshot['profile_key'] ?? $lastProfile['profile_key'],
                    'profile_name' => $this->nullableString($snapshot['profile_name'] ?? $lastProfile['profile_name']),
                    'profile_brand' => $this->nullableString($snapshot['profile_brand'] ?? $lastProfile['profile_brand']),
                    'profile_description' => $this->nullableString($snapshot['profile_description'] ?? $lastProfile['profile_description']),
                    'profile_expired_date' => $this->normalizeDate((string)($snapshot['profile_expired_date'] ?? '')),
                    'profile_content_per_buy' => round((float)($snapshot['profile_content_per_buy'] ?? $lastProfile['profile_content_per_buy']), 6),
                    'profile_buy_uom_code' => $this->nullableString($snapshot['profile_buy_uom_code'] ?? $lastProfile['profile_buy_uom_code']),
                    'profile_content_uom_code' => $this->nullableString($snapshot['profile_content_uom_code'] ?? $lastProfile['profile_content_uom_code']),
                ];
            }

            $openingBuy = $currentBuy;
            $openingContent = $currentContent;
            $deltaMaps = $this->emptyInventoryDailyDeltaMaps();
            $mutationCount = 0;
            $lastMovementAt = null;
            $dayMovements = $movementByDate[$day] ?? [];

            foreach ($dayMovements as $movementRow) {
                $movementType = strtoupper(trim((string)($movementRow['movement_type'] ?? 'ADJUSTMENT')));
                $qtyBuyDelta = round((float)($movementRow['qty_buy_delta'] ?? 0), 4);
                $qtyContentDelta = round((float)($movementRow['qty_content_delta'] ?? 0), 4);
                $adjustmentCategory = $this->normalizeInventoryAdjustmentCategory((string)($movementRow['adjustment_category'] ?? ''));
                if ($adjustmentCategory === null) {
                    $adjustmentCategory = $this->resolveInventoryAdjustmentCategoryFromMovement($movementType, $qtyBuyDelta, $qtyContentDelta);
                }
                $deltaPack = $this->buildInventoryDailyDeltaPack($movementType, $qtyBuyDelta, $qtyContentDelta, $adjustmentCategory, $currentAvg);
                foreach ($deltaPack['delta'] as $field => $value) {
                    $deltaMaps['delta'][$field] = round($deltaMaps['delta'][$field] + $value, 4);
                }
                foreach ($deltaPack['value'] as $field => $value) {
                    $deltaMaps['value'][$field] = round($deltaMaps['value'][$field] + $value, 2);
                }

                $state = $this->applyInventoryHistoryMovement($currentBuy, $currentContent, $currentAvg, $qtyBuyDelta, $qtyContentDelta, (float)($movementRow['unit_cost'] ?? 0));
                $currentBuy = $state['qty_buy'];
                $currentContent = $state['qty_content'];
                $currentAvg = $state['avg_cost_per_content'];
                $mutationCount++;
                $lastMovementAt = $movementRow['created_at'] ?? $lastMovementAt;

                foreach (['profile_name', 'profile_brand', 'profile_description', 'profile_buy_uom_code', 'profile_content_uom_code'] as $profileField) {
                    $value = $this->nullableString($movementRow[$profileField] ?? null);
                    if ($value !== null) {
                        $lastProfile[$profileField] = $value;
                    }
                }
                if (!empty($movementRow['profile_expired_date'])) {
                    $lastProfile['profile_expired_date'] = $this->normalizeDate((string)$movementRow['profile_expired_date']);
                }
                if (isset($movementRow['profile_content_per_buy']) && (float)$movementRow['profile_content_per_buy'] > 0) {
                    $lastProfile['profile_content_per_buy'] = round((float)$movementRow['profile_content_per_buy'], 6);
                }
            }

            $row = [
                'month_key' => $monthKey,
                'movement_date' => $day,
                'stock_domain' => (string)($lastProfile['stock_domain'] ?? $identity['stock_domain'] ?? 'ITEM'),
                'item_id' => $identity['item_id'] ?? null,
                'material_id' => $identity['material_id'] ?? null,
                'buy_uom_id' => $identity['buy_uom_id'] ?? null,
                'content_uom_id' => $identity['content_uom_id'] ?? null,
                'profile_key' => $identity['profile_key'] ?? null,
                'profile_name' => $lastProfile['profile_name'] ?? null,
                'profile_brand' => $lastProfile['profile_brand'] ?? null,
                'profile_description' => $lastProfile['profile_description'] ?? null,
                'profile_content_per_buy' => round((float)($lastProfile['profile_content_per_buy'] ?? 0), 6),
                'profile_buy_uom_code' => $lastProfile['profile_buy_uom_code'] ?? null,
                'profile_content_uom_code' => $lastProfile['profile_content_uom_code'] ?? null,
                'opening_qty_buy' => round($openingBuy, 4),
                'opening_qty_content' => round($openingContent, 4),
                'in_qty_buy' => $deltaMaps['delta']['in_qty_buy'],
                'in_qty_content' => $deltaMaps['delta']['in_qty_content'],
                'out_qty_buy' => $deltaMaps['delta']['out_qty_buy'],
                'out_qty_content' => $deltaMaps['delta']['out_qty_content'],
                'discarded_qty_buy' => $deltaMaps['delta']['discarded_qty_buy'],
                'discarded_qty_content' => $deltaMaps['delta']['discarded_qty_content'],
                'spoil_qty_buy' => $deltaMaps['delta']['spoil_qty_buy'],
                'spoil_qty_content' => $deltaMaps['delta']['spoil_qty_content'],
                'waste_qty_buy' => $deltaMaps['delta']['waste_qty_buy'],
                'waste_qty_content' => $deltaMaps['delta']['waste_qty_content'],
                'adjustment_qty_buy' => $deltaMaps['delta']['adjustment_qty_buy'],
                'adjustment_qty_content' => $deltaMaps['delta']['adjustment_qty_content'],
                'closing_qty_buy' => round($currentBuy, 4),
                'closing_qty_content' => round($currentContent, 4),
                'avg_cost_per_content' => round($currentAvg, 6),
                'total_value' => round($currentContent * $currentAvg, 2),
                'mutation_count' => $mutationCount,
                'last_movement_at' => $lastMovementAt,
                'process_loss_qty_buy' => $deltaMaps['delta']['process_loss_qty_buy'],
                'process_loss_qty_content' => $deltaMaps['delta']['process_loss_qty_content'],
                'variance_qty_buy' => $deltaMaps['delta']['variance_qty_buy'],
                'variance_qty_content' => $deltaMaps['delta']['variance_qty_content'],
                'adjustment_plus_qty_buy' => $deltaMaps['delta']['adjustment_plus_qty_buy'],
                'adjustment_plus_qty_content' => $deltaMaps['delta']['adjustment_plus_qty_content'],
                'waste_total_value' => $deltaMaps['value']['waste_total_value'],
                'spoilage_total_value' => $deltaMaps['value']['spoilage_total_value'],
                'process_loss_total_value' => $deltaMaps['value']['process_loss_total_value'],
                'variance_total_value' => $deltaMaps['value']['variance_total_value'],
                'adjustment_plus_total_value' => $deltaMaps['value']['adjustment_plus_total_value'],
                'profile_expired_date' => $lastProfile['profile_expired_date'] ?? null,
            ];
            if ($stockScope === 'DIVISION') {
                $row['division_id'] = $identity['division_id'] ?? null;
                $row['destination_type'] = $identity['destination_type'] ?? 'OTHER';
            }
            $monthlyRows[] = $row;
        }

        $monthlySync = $this->syncInventoryMonthlyStockFromDailyRows(
            $stockScope,
            $identity,
            $monthlyRows,
            $snapshotByMonth,
            'Rebuilt from opening snapshot ' . $startMonth
        );
        if (!($monthlySync['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => (string)($monthlySync['message'] ?? 'Gagal sinkron monthly stock setelah rebuild opening.'),
            ];
        }

        if ($this->db->trans_status() === false) {
            return [
                'ok' => false,
                'message' => 'Gagal rebuild histori opening.',
            ];
        }

        return [
            'ok' => true,
            'message' => 'Histori opening berhasil direbuild.',
            'data' => [
                'days_rebuilt' => count($monthlyRows),
                'last_relevant_date' => $lastRelevantDate,
            ],
        ];
    }

    private function applyInventoryHistoryIdentityFilter(string $alias, string $stockScope, array $identity, bool $hasDestinationType): void
    {
        $this->db->where($alias . '.item_id', $identity['item_id'] ?? 0);
        $materialId = $identity['material_id'] ?? null;
        if ($materialId !== null) {
            $this->db->where($alias . '.material_id', $materialId);
        } else {
            $this->db->where($alias . '.material_id IS NULL', null, false);
        }
        $buyUomId = $identity['buy_uom_id'] ?? null;
        if ($buyUomId !== null) {
            $this->db->where($alias . '.buy_uom_id', $buyUomId);
        } else {
            $this->db->where($alias . '.buy_uom_id IS NULL', null, false);
        }
        $this->db->where($alias . '.content_uom_id', $identity['content_uom_id'] ?? 0);
        $profileKey = $identity['profile_key'] ?? null;
        if ($profileKey !== null && $profileKey !== '') {
            $this->db->where($alias . '.profile_key', $profileKey);
        } else {
            $this->db->where($alias . '.profile_key IS NULL', null, false);
        }
        if ($stockScope === 'DIVISION') {
            $this->db->where($alias . '.division_id', $identity['division_id'] ?? 0);
            if ($hasDestinationType) {
                $this->db->where($alias . '.destination_type', $identity['destination_type'] ?? 'OTHER');
            }
        }
    }

    private function applyOpeningHistoryIdentityFilter(string $alias, string $stockScope, array $identity): void
    {
        $this->db->where($alias . '.item_id', $identity['item_id'] ?? 0);
        $materialId = $identity['material_id'] ?? null;
        if ($materialId !== null) {
            $this->db->where($alias . '.material_id', $materialId);
        } else {
            $this->db->where($alias . '.material_id IS NULL', null, false);
        }
        $this->db->where($alias . '.buy_uom_id', $identity['buy_uom_id'] ?? 0);
        $this->db->where($alias . '.content_uom_id', $identity['content_uom_id'] ?? 0);
        $profileKey = $identity['profile_key'] ?? null;
        if ($profileKey !== null && $profileKey !== '') {
            $this->db->where($alias . '.profile_key', $profileKey);
        } else {
            $this->db->where($alias . '.profile_key IS NULL', null, false);
        }
        if ($stockScope === 'DIVISION') {
            $this->db->where($alias . '.division_id', $identity['division_id'] ?? 0);
            if ($this->db->field_exists('destination_type', $this->openingSnapshotTableForScope($stockScope))) {
                $this->db->where($alias . '.destination_type', $identity['destination_type'] ?? 'OTHER');
            }
        }
    }

    private function applyDailyRollupIdentityFilter(string $table, string $stockScope, array $identity): void
    {
        $this->db->where('item_id', $identity['item_id'] ?? 0);
        $materialId = $identity['material_id'] ?? null;
        if ($materialId !== null) {
            $this->db->where('material_id', $materialId);
        } else {
            $this->db->where('material_id IS NULL', null, false);
        }
        $buyUomId = $identity['buy_uom_id'] ?? null;
        if ($buyUomId !== null) {
            $this->db->where('buy_uom_id', $buyUomId);
        } else {
            $this->db->where('buy_uom_id IS NULL', null, false);
        }
        $this->db->where('content_uom_id', $identity['content_uom_id'] ?? 0);
        $profileKey = $identity['profile_key'] ?? null;
        if ($profileKey !== null && $profileKey !== '') {
            $this->db->where('profile_key', $profileKey);
        } else {
            $this->db->where('profile_key IS NULL', null, false);
        }
        if ($stockScope === 'DIVISION') {
            $this->db->where('division_id', $identity['division_id'] ?? 0);
            if ($this->db->field_exists('destination_type', $table)) {
                $this->db->where('destination_type', $identity['destination_type'] ?? 'OTHER');
            }
        }
    }

    private function emptyInventoryDailyDeltaMaps(): array
    {
        return [
            'delta' => [
                'in_qty_buy' => 0.0,
                'in_qty_content' => 0.0,
                'out_qty_buy' => 0.0,
                'out_qty_content' => 0.0,
                'discarded_qty_buy' => 0.0,
                'discarded_qty_content' => 0.0,
                'spoil_qty_buy' => 0.0,
                'spoil_qty_content' => 0.0,
                'waste_qty_buy' => 0.0,
                'waste_qty_content' => 0.0,
                'process_loss_qty_buy' => 0.0,
                'process_loss_qty_content' => 0.0,
                'variance_qty_buy' => 0.0,
                'variance_qty_content' => 0.0,
                'adjustment_plus_qty_buy' => 0.0,
                'adjustment_plus_qty_content' => 0.0,
                'adjustment_qty_buy' => 0.0,
                'adjustment_qty_content' => 0.0,
            ],
            'value' => [
                'waste_total_value' => 0.0,
                'spoilage_total_value' => 0.0,
                'process_loss_total_value' => 0.0,
                'variance_total_value' => 0.0,
                'adjustment_plus_total_value' => 0.0,
            ],
        ];
    }

    private function buildInventoryDailyDeltaPack(string $movementType, float $qtyBuyDelta, float $qtyContentDelta, ?string $adjustmentCategory, float $avgCostPerContent): array
    {
        $pack = $this->emptyInventoryDailyDeltaMaps();
        $mutationValue = round(abs($qtyContentDelta) * max(0, $avgCostPerContent), 2);
        if (in_array($movementType, ['PURCHASE_IN', 'TRANSFER_IN'], true)) {
            $pack['delta']['in_qty_buy'] = max(0, $qtyBuyDelta);
            $pack['delta']['in_qty_content'] = max(0, $qtyContentDelta);
        } elseif (in_array($movementType, ['TRANSFER_OUT', 'USAGE_OUT'], true)) {
            $pack['delta']['out_qty_buy'] = abs(min(0, $qtyBuyDelta));
            $pack['delta']['out_qty_content'] = abs(min(0, $qtyContentDelta));
        } elseif ($movementType === 'DISCARDED_OUT') {
            $pack['delta']['discarded_qty_buy'] = abs(min(0, $qtyBuyDelta));
            $pack['delta']['discarded_qty_content'] = abs(min(0, $qtyContentDelta));
            $pack['delta']['waste_qty_buy'] = abs(min(0, $qtyBuyDelta));
            $pack['delta']['waste_qty_content'] = abs(min(0, $qtyContentDelta));
            $pack['delta']['adjustment_qty_buy'] = $qtyBuyDelta;
            $pack['delta']['adjustment_qty_content'] = $qtyContentDelta;
            $pack['value']['waste_total_value'] = $mutationValue;
        } elseif ($movementType === 'SPOIL_OUT') {
            $pack['delta']['spoil_qty_buy'] = abs(min(0, $qtyBuyDelta));
            $pack['delta']['spoil_qty_content'] = abs(min(0, $qtyContentDelta));
            $pack['delta']['adjustment_qty_buy'] = $qtyBuyDelta;
            $pack['delta']['adjustment_qty_content'] = $qtyContentDelta;
            $pack['value']['spoilage_total_value'] = $mutationValue;
        } elseif ($movementType === 'WASTE_OUT') {
            $pack['delta']['waste_qty_buy'] = abs(min(0, $qtyBuyDelta));
            $pack['delta']['waste_qty_content'] = abs(min(0, $qtyContentDelta));
            $pack['delta']['adjustment_qty_buy'] = $qtyBuyDelta;
            $pack['delta']['adjustment_qty_content'] = $qtyContentDelta;
            $pack['value']['waste_total_value'] = $mutationValue;
        } elseif ($movementType === 'PROCESS_LOSS_OUT') {
            $pack['delta']['process_loss_qty_buy'] = abs(min(0, $qtyBuyDelta));
            $pack['delta']['process_loss_qty_content'] = abs(min(0, $qtyContentDelta));
            $pack['delta']['adjustment_qty_buy'] = $qtyBuyDelta;
            $pack['delta']['adjustment_qty_content'] = $qtyContentDelta;
            $pack['value']['process_loss_total_value'] = $mutationValue;
        } elseif ($movementType === 'VARIANCE_OUT') {
            $pack['delta']['variance_qty_buy'] = abs(min(0, $qtyBuyDelta));
            $pack['delta']['variance_qty_content'] = abs(min(0, $qtyContentDelta));
            $pack['delta']['adjustment_qty_buy'] = $qtyBuyDelta;
            $pack['delta']['adjustment_qty_content'] = $qtyContentDelta;
            $pack['value']['variance_total_value'] = $mutationValue;
        } elseif ($movementType === 'ADJUSTMENT_IN') {
            $pack['delta']['adjustment_plus_qty_buy'] = max(0, $qtyBuyDelta);
            $pack['delta']['adjustment_plus_qty_content'] = max(0, $qtyContentDelta);
            $pack['delta']['adjustment_qty_buy'] = $qtyBuyDelta;
            $pack['delta']['adjustment_qty_content'] = $qtyContentDelta;
            $pack['value']['adjustment_plus_total_value'] = $mutationValue;
        } else {
            $pack['delta']['adjustment_qty_buy'] = $qtyBuyDelta;
            $pack['delta']['adjustment_qty_content'] = $qtyContentDelta;
        }

        if ($adjustmentCategory === 'WASTE') {
            $pack['delta']['waste_qty_buy'] = max($pack['delta']['waste_qty_buy'], abs($qtyBuyDelta));
            $pack['delta']['waste_qty_content'] = max($pack['delta']['waste_qty_content'], abs($qtyContentDelta));
            $pack['value']['waste_total_value'] = max($pack['value']['waste_total_value'], $mutationValue);
        } elseif ($adjustmentCategory === 'SPOILAGE') {
            $pack['delta']['spoil_qty_buy'] = max($pack['delta']['spoil_qty_buy'], abs($qtyBuyDelta));
            $pack['delta']['spoil_qty_content'] = max($pack['delta']['spoil_qty_content'], abs($qtyContentDelta));
            $pack['value']['spoilage_total_value'] = max($pack['value']['spoilage_total_value'], $mutationValue);
        } elseif ($adjustmentCategory === 'PROCESS_LOSS') {
            $pack['delta']['process_loss_qty_buy'] = max($pack['delta']['process_loss_qty_buy'], abs($qtyBuyDelta));
            $pack['delta']['process_loss_qty_content'] = max($pack['delta']['process_loss_qty_content'], abs($qtyContentDelta));
            $pack['value']['process_loss_total_value'] = max($pack['value']['process_loss_total_value'], $mutationValue);
        } elseif ($adjustmentCategory === 'VARIANCE') {
            $pack['delta']['variance_qty_buy'] = max($pack['delta']['variance_qty_buy'], abs($qtyBuyDelta));
            $pack['delta']['variance_qty_content'] = max($pack['delta']['variance_qty_content'], abs($qtyContentDelta));
            $pack['value']['variance_total_value'] = max($pack['value']['variance_total_value'], $mutationValue);
        } elseif ($adjustmentCategory === 'ADJUSTMENT_PLUS') {
            $pack['delta']['adjustment_plus_qty_buy'] = max($pack['delta']['adjustment_plus_qty_buy'], max(0, $qtyBuyDelta));
            $pack['delta']['adjustment_plus_qty_content'] = max($pack['delta']['adjustment_plus_qty_content'], max(0, $qtyContentDelta));
            $pack['value']['adjustment_plus_total_value'] = max($pack['value']['adjustment_plus_total_value'], $mutationValue);
        }

        return $pack;
    }

    private function applyInventoryHistoryMovement(float $currentBuy, float $currentContent, float $currentAvg, float $qtyBuyDelta, float $qtyContentDelta, float $unitCost): array
    {
        $qtyBuyAfter = round($currentBuy + $qtyBuyDelta, 4);
        $qtyContentAfter = round($currentContent + $qtyContentDelta, 4);
        if ($qtyBuyAfter < 0) {
            $qtyBuyAfter = 0.0;
        }
        if ($qtyContentAfter < 0) {
            $qtyContentAfter = 0.0;
        }

        $avgAfter = $currentAvg;
        if ($qtyContentAfter <= 0) {
            $avgAfter = 0.0;
        } elseif ($qtyContentDelta > 0) {
            $oldValue = $currentContent * $currentAvg;
            $inValue = $qtyContentDelta * max(0, round($unitCost, 6));
            $avgAfter = round(($oldValue + $inValue) / max(0.000001, $qtyContentAfter), 6);
        }

        return [
            'qty_buy' => $qtyBuyAfter,
            'qty_content' => $qtyContentAfter,
            'avg_cost_per_content' => $avgAfter,
        ];
    }

    private function normalizeInventoryAdjustmentCategory(string $value): ?string
    {
        $value = strtoupper(trim($value));
        if ($value === '') {
            return null;
        }
        $allowed = ['WASTE', 'SPOILAGE', 'PROCESS_LOSS', 'VARIANCE', 'ADJUSTMENT_PLUS'];
        return in_array($value, $allowed, true) ? $value : null;
    }

    private function resolveInventoryAdjustmentCategoryFromMovement(string $movementType, float $qtyBuyDelta, float $qtyContentDelta): ?string
    {
        if (in_array($movementType, ['DISCARDED_OUT', 'WASTE_OUT'], true)) {
            return 'WASTE';
        }
        if ($movementType === 'SPOIL_OUT') {
            return 'SPOILAGE';
        }
        if ($movementType === 'PROCESS_LOSS_OUT') {
            return 'PROCESS_LOSS';
        }
        if ($movementType === 'VARIANCE_OUT') {
            return 'VARIANCE';
        }
        if ($movementType === 'ADJUSTMENT_IN') {
            return 'ADJUSTMENT_PLUS';
        }
        if ($movementType === 'ADJUSTMENT') {
            return ($qtyBuyDelta > 0 || $qtyContentDelta > 0) ? 'ADJUSTMENT_PLUS' : 'VARIANCE';
        }
        return null;
    }

    private function filterExistingColumns(array $row, array $columns): array
    {
        $filtered = [];
        foreach ($row as $column => $value) {
            if (isset($columns[$column])) {
                $filtered[$column] = $value;
            }
        }
        return $filtered;
    }

    private function syncInventoryMonthlyStockFromDailyRows(string $stockScope, array $identity, array $dailyRows, array $openingRowsByMonth = [], string $note = ''): array
    {
        $table = $stockScope === 'DIVISION' ? 'inv_division_monthly_stock' : 'inv_warehouse_monthly_stock';
        if (!$this->db->table_exists($table)) {
            return ['ok' => true, 'skipped' => 'MONTHLY_TABLE_NOT_AVAILABLE'];
        }

        if (empty($dailyRows)) {
            $this->purgeInventoryMonthlyStockForIdentity($stockScope, $identity, null);
            return ['ok' => true, 'data' => ['rows_synced' => 0]];
        }

        usort($dailyRows, static function (array $left, array $right): int {
            $leftMonth = (string)($left['month_key'] ?? ($left['movement_date'] ?? ''));
            $rightMonth = (string)($right['month_key'] ?? ($right['movement_date'] ?? ''));
                        if ($leftMonth !== $rightMonth) {
                            return strcmp($leftMonth, $rightMonth);
                        }
                        return strcmp((string)($left['movement_date'] ?? ''), (string)($right['movement_date'] ?? ''));
                    });

                    $startMonth = $this->normalizeMonth((string)($dailyRows[0]['month_key'] ?? ($dailyRows[0]['movement_date'] ?? '')));
                    $this->purgeInventoryMonthlyStockForIdentity($stockScope, $identity, $startMonth);

                    $tableColumns = $this->listTableFields($table);
                    $grouped = [];
                    $previousClosingValue = 0.0;

                    foreach ($dailyRows as $row) {
                        $monthKey = $this->normalizeMonth((string)($row['month_key'] ?? ($row['movement_date'] ?? '')));
                        if ($monthKey === null) {
                            continue;
                        }

                        if (!isset($grouped[$monthKey])) {
                            $openingRow = $openingRowsByMonth[$monthKey] ?? null;
                            $openingQtyBuy = round((float)($openingRow['opening_qty_buy'] ?? ($row['opening_qty_buy'] ?? 0)), 4);
                            $openingQtyContent = round((float)($openingRow['opening_qty_content'] ?? ($row['opening_qty_content'] ?? 0)), 4);
                            $openingTotalValue = array_key_exists('opening_total_value', (array)$openingRow)
                                ? round((float)($openingRow['opening_total_value'] ?? 0), 2)
                                : round($previousClosingValue, 2);

                            $grouped[$monthKey] = [
                                'month_key' => $monthKey,
                                'stock_domain' => strtoupper((string)($row['stock_domain'] ?? ($identity['stock_domain'] ?? 'ITEM'))),
                                'identity_key' => $this->buildInventoryMonthlyIdentityKey(array_merge($identity, $row)),
                                'item_id' => $this->nullableInt($row['item_id'] ?? ($identity['item_id'] ?? null)),
                                'material_id' => $this->nullableInt($row['material_id'] ?? ($identity['material_id'] ?? null)),
                                'buy_uom_id' => $this->nullableInt($row['buy_uom_id'] ?? ($identity['buy_uom_id'] ?? null)),
                                'content_uom_id' => $this->nullableInt($row['content_uom_id'] ?? ($identity['content_uom_id'] ?? null)),
                                'profile_key' => $this->nullableString($row['profile_key'] ?? ($identity['profile_key'] ?? null)),
                                'profile_name' => $this->nullableString($row['profile_name'] ?? ($identity['profile_name'] ?? null)),
                                'profile_brand' => $this->nullableString($row['profile_brand'] ?? ($identity['profile_brand'] ?? null)),
                                'profile_description' => $this->nullableString($row['profile_description'] ?? ($identity['profile_description'] ?? null)),
                                'profile_expired_date' => $this->normalizeDate((string)($row['profile_expired_date'] ?? ($identity['profile_expired_date'] ?? ''))),
                                'profile_content_per_buy' => round((float)($row['profile_content_per_buy'] ?? ($identity['profile_content_per_buy'] ?? 0)), 6),
                                'profile_buy_uom_code' => $this->nullableString($row['profile_buy_uom_code'] ?? ($identity['profile_buy_uom_code'] ?? null)),
                                'profile_content_uom_code' => $this->nullableString($row['profile_content_uom_code'] ?? ($identity['profile_content_uom_code'] ?? null)),
                                'opening_qty_buy' => $openingQtyBuy,
                                'opening_qty_content' => $openingQtyContent,
                                'opening_total_value' => $openingTotalValue,
                                'in_qty_buy' => 0.0,
                                'in_qty_content' => 0.0,
                                'in_total_value' => 0.0,
                                'out_qty_buy' => 0.0,
                                'out_qty_content' => 0.0,
                                'out_total_value' => 0.0,
                                'discarded_qty_buy' => 0.0,
                                'discarded_qty_content' => 0.0,
                                'discarded_total_value' => 0.0,
                                'spoil_qty_buy' => 0.0,
                                'spoil_qty_content' => 0.0,
                                'spoilage_total_value' => 0.0,
                                'waste_qty_buy' => 0.0,
                                'waste_qty_content' => 0.0,
                                'waste_total_value' => 0.0,
                                'process_loss_qty_buy' => 0.0,
                                'process_loss_qty_content' => 0.0,
                                'process_loss_total_value' => 0.0,
                                'variance_qty_buy' => 0.0,
                                'variance_qty_content' => 0.0,
                                'variance_total_value' => 0.0,
                                'adjustment_plus_qty_buy' => 0.0,
                                'adjustment_plus_qty_content' => 0.0,
                                'adjustment_plus_total_value' => 0.0,
                                'adjustment_minus_qty_buy' => 0.0,
                                'adjustment_minus_qty_content' => 0.0,
                                'adjustment_minus_total_value' => 0.0,
                                'closing_qty_buy' => 0.0,
                                'closing_qty_content' => 0.0,
                                'avg_cost_per_content' => 0.0,
                                'total_value' => 0.0,
                                'movement_day_count' => 0,
                                'mutation_count' => 0,
                                'last_movement_date' => null,
                                'last_movement_at' => null,
                                'last_movement_table' => null,
                                'last_movement_id' => null,
                                'source_mode' => 'REBUILD',
                                'notes' => $this->nullableString($note !== '' ? $note : ('Rebuilt monthly stock ' . $monthKey)),
                                '_day_map' => [],
                            ];
                            if ($stockScope === 'DIVISION') {
                                $grouped[$monthKey]['division_id'] = $this->nullableInt($row['division_id'] ?? ($identity['division_id'] ?? null));
                                $grouped[$monthKey]['destination_type'] = strtoupper((string)($row['destination_type'] ?? ($identity['destination_type'] ?? 'OTHER')));
                            }
                        }

                        $avg = round((float)($row['avg_cost_per_content'] ?? 0), 6);
                        $adjustmentQtyBuy = round((float)($row['adjustment_qty_buy'] ?? 0), 4);
                        $adjustmentQtyContent = round((float)($row['adjustment_qty_content'] ?? 0), 4);
                        $adjustmentPlusQtyBuy = round((float)($row['adjustment_plus_qty_buy'] ?? max($adjustmentQtyBuy, 0)), 4);
                        $adjustmentPlusQtyContent = round((float)($row['adjustment_plus_qty_content'] ?? max($adjustmentQtyContent, 0)), 4);
                        $adjustmentMinusQtyBuy = round(abs(min($adjustmentQtyBuy, 0)), 4);
                        $adjustmentMinusQtyContent = round(abs(min($adjustmentQtyContent, 0)), 4);

                        $bucket =& $grouped[$monthKey];
                        $bucket['in_qty_buy'] = round($bucket['in_qty_buy'] + (float)($row['in_qty_buy'] ?? 0), 4);
                        $bucket['in_qty_content'] = round($bucket['in_qty_content'] + (float)($row['in_qty_content'] ?? 0), 4);
                        $bucket['in_total_value'] = round($bucket['in_total_value'] + (round((float)($row['in_qty_content'] ?? 0), 4) * $avg), 2);
                        $bucket['out_qty_buy'] = round($bucket['out_qty_buy'] + (float)($row['out_qty_buy'] ?? 0), 4);
                        $bucket['out_qty_content'] = round($bucket['out_qty_content'] + (float)($row['out_qty_content'] ?? 0), 4);
                        $bucket['out_total_value'] = round($bucket['out_total_value'] + (round((float)($row['out_qty_content'] ?? 0), 4) * $avg), 2);
                        $bucket['discarded_qty_buy'] = round($bucket['discarded_qty_buy'] + (float)($row['discarded_qty_buy'] ?? 0), 4);
                        $bucket['discarded_qty_content'] = round($bucket['discarded_qty_content'] + (float)($row['discarded_qty_content'] ?? 0), 4);
                        $bucket['discarded_total_value'] = round($bucket['discarded_total_value'] + (round((float)($row['discarded_qty_content'] ?? 0), 4) * $avg), 2);
                        $bucket['spoil_qty_buy'] = round($bucket['spoil_qty_buy'] + (float)($row['spoil_qty_buy'] ?? 0), 4);
                        $bucket['spoil_qty_content'] = round($bucket['spoil_qty_content'] + (float)($row['spoil_qty_content'] ?? 0), 4);
                        $bucket['spoilage_total_value'] = round($bucket['spoilage_total_value'] + (float)($row['spoilage_total_value'] ?? (round((float)($row['spoil_qty_content'] ?? 0), 4) * $avg)), 2);
                        $bucket['waste_qty_buy'] = round($bucket['waste_qty_buy'] + (float)($row['waste_qty_buy'] ?? 0), 4);
                        $bucket['waste_qty_content'] = round($bucket['waste_qty_content'] + (float)($row['waste_qty_content'] ?? 0), 4);
                        $bucket['waste_total_value'] = round($bucket['waste_total_value'] + (float)($row['waste_total_value'] ?? (round((float)($row['waste_qty_content'] ?? 0), 4) * $avg)), 2);
                        $bucket['process_loss_qty_buy'] = round($bucket['process_loss_qty_buy'] + (float)($row['process_loss_qty_buy'] ?? 0), 4);
                        $bucket['process_loss_qty_content'] = round($bucket['process_loss_qty_content'] + (float)($row['process_loss_qty_content'] ?? 0), 4);
                        $bucket['process_loss_total_value'] = round($bucket['process_loss_total_value'] + (float)($row['process_loss_total_value'] ?? (round((float)($row['process_loss_qty_content'] ?? 0), 4) * $avg)), 2);
                        $bucket['variance_qty_buy'] = round($bucket['variance_qty_buy'] + (float)($row['variance_qty_buy'] ?? 0), 4);
                        $bucket['variance_qty_content'] = round($bucket['variance_qty_content'] + (float)($row['variance_qty_content'] ?? 0), 4);
                        $bucket['variance_total_value'] = round($bucket['variance_total_value'] + (float)($row['variance_total_value'] ?? 0), 2);
                        $bucket['adjustment_plus_qty_buy'] = round($bucket['adjustment_plus_qty_buy'] + $adjustmentPlusQtyBuy, 4);
                        $bucket['adjustment_plus_qty_content'] = round($bucket['adjustment_plus_qty_content'] + $adjustmentPlusQtyContent, 4);
                        $bucket['adjustment_plus_total_value'] = round($bucket['adjustment_plus_total_value'] + (float)($row['adjustment_plus_total_value'] ?? ($adjustmentPlusQtyContent * $avg)), 2);
                        $bucket['adjustment_minus_qty_buy'] = round($bucket['adjustment_minus_qty_buy'] + $adjustmentMinusQtyBuy, 4);
                        $bucket['adjustment_minus_qty_content'] = round($bucket['adjustment_minus_qty_content'] + $adjustmentMinusQtyContent, 4);
                        $bucket['adjustment_minus_total_value'] = round($bucket['adjustment_minus_total_value'] + ($adjustmentMinusQtyContent * $avg), 2);
                        $bucket['closing_qty_buy'] = round((float)($row['closing_qty_buy'] ?? 0), 4);
                        $bucket['closing_qty_content'] = round((float)($row['closing_qty_content'] ?? 0), 4);
                        $bucket['avg_cost_per_content'] = $avg;
                        $bucket['total_value'] = round((float)($row['total_value'] ?? ($bucket['closing_qty_content'] * $avg)), 2);
                        $bucket['mutation_count'] += (int)($row['mutation_count'] ?? 0);

                        $day = (string)($row['movement_date'] ?? '');
                        if ($day !== '') {
                            $bucket['_day_map'][$day] = true;
                            $bucket['last_movement_date'] = $day;
                        }
                        $lastMovementAt = $this->nullableString($row['last_movement_at'] ?? null);
                        if ($lastMovementAt !== null) {
                            $bucket['last_movement_at'] = $lastMovementAt;
                        }

                        foreach (['profile_name', 'profile_brand', 'profile_description', 'profile_buy_uom_code', 'profile_content_uom_code'] as $field) {
                            $value = $this->nullableString($row[$field] ?? null);
                            if ($value !== null) {
                                $bucket[$field] = $value;
                            }
                        }
                        if (!empty($row['profile_expired_date'])) {
                            $bucket['profile_expired_date'] = $this->normalizeDate((string)$row['profile_expired_date']);
                        }
                        if (isset($row['profile_content_per_buy']) && (float)$row['profile_content_per_buy'] > 0) {
                            $bucket['profile_content_per_buy'] = round((float)$row['profile_content_per_buy'], 6);
                        }

                        unset($bucket);
                    }

                    $rowsSynced = 0;
                    foreach ($grouped as $monthKey => $row) {
                        $row['movement_day_count'] = count($row['_day_map']);
                        unset($row['_day_map']);
                        $previousClosingValue = round((float)($row['total_value'] ?? 0), 2);
                        $this->db->insert($table, $this->filterExistingColumns($row, $tableColumns));
                        $rowsSynced++;
                    }

                    return ['ok' => true, 'data' => ['rows_synced' => $rowsSynced]];
                }

                private function purgeInventoryMonthlyStockForIdentity(string $stockScope, array $identity, ?string $startMonth = null): void
                {
                    $table = $stockScope === 'DIVISION' ? 'inv_division_monthly_stock' : 'inv_warehouse_monthly_stock';
                    if (!$this->db->table_exists($table)) {
                        return;
                    }

                if ($startMonth !== null) {
                    $this->db->where('month_key >=', $startMonth);
                }

                // Item-centric cleanup: purge exact identity lintas domain legacy.
                // Jangan sisakan sibling MATERIAL/ITEM untuk identity yang sama.

                    $itemId = $this->nullableInt($identity['item_id'] ?? null);
                    if ($itemId !== null) {
                        $this->db->where('item_id', $itemId);
                    } else {
                        $this->db->where('item_id IS NULL', null, false);
                    }

                    $materialId = $this->nullableInt($identity['material_id'] ?? null);
                    if ($materialId !== null) {
                        $this->db->where('material_id', $materialId);
                    } else {
                        $this->db->where('material_id IS NULL', null, false);
                    }

                    $buyUomId = $this->nullableInt($identity['buy_uom_id'] ?? null);
                    if ($buyUomId !== null) {
                        $this->db->where('buy_uom_id', $buyUomId);
                    } else {
                        $this->db->where('buy_uom_id IS NULL', null, false);
                    }

                    $this->db->where('content_uom_id', $this->nullableInt($identity['content_uom_id'] ?? null) ?? 0);

                    $profileKey = $this->nullableString($identity['profile_key'] ?? null);
                    if ($profileKey !== null) {
                        $this->db->where('profile_key', $profileKey);
                    } else {
                        $this->db->where('profile_key IS NULL', null, false);
                    }

                    if ($stockScope === 'DIVISION') {
                        $this->db->where('division_id', $this->nullableInt($identity['division_id'] ?? null) ?? 0);
                        if ($this->db->field_exists('destination_type', $table)) {
                            $this->db->where('destination_type', strtoupper((string)($identity['destination_type'] ?? 'OTHER')));
                        }
                    }

                    $this->db->delete($table);
                }

                private function buildInventoryMonthlyIdentityKey(array $row): string
                {
                    $profileKey = $this->nullableString($row['profile_key'] ?? null);
                    if ($profileKey !== null) {
                        return $profileKey;
                    }

                return hash('sha256', implode('|', [
                    (string)((int)($row['item_id'] ?? 0)),
                    (string)((int)($row['material_id'] ?? 0)),
                    (string)((int)($row['buy_uom_id'] ?? 0)),
                        (string)((int)($row['content_uom_id'] ?? 0)),
                        strtoupper(trim((string)($row['profile_name'] ?? ''))),
                        strtoupper(trim((string)($row['profile_brand'] ?? ''))),
                        strtoupper(trim((string)($row['profile_description'] ?? ''))),
                        number_format((float)($row['profile_content_per_buy'] ?? 1), 6, '.', ''),
                        trim((string)($row['profile_expired_date'] ?? '')),
                    ]));
                }

    public function generate_monthly_opname_and_opening(array $payload, int $userId, string $sourceIp = ''): array
    {
        $stockScope = strtoupper(trim((string)($payload['stock_scope'] ?? 'WAREHOUSE')));
        if (!in_array($stockScope, ['WAREHOUSE', 'DIVISION'], true)) {
            $stockScope = 'WAREHOUSE';
        }

        $monthKey = $this->normalizeMonth((string)($payload['month'] ?? $payload['snapshot_month'] ?? date('Y-m-01')));
        if ($monthKey === null) {
            return [
                'ok' => false,
                'message' => 'Parameter month tidak valid.',
            ];
        }

        $dateFrom = $monthKey;
        $dateTo = date('Y-m-t', strtotime($monthKey));
        $nextMonth = date('Y-m-01', strtotime('+1 month', strtotime($monthKey)));

        $divisionId = null;
        $destinationFilter = null;
        if ($stockScope === 'DIVISION') {
            $divisionIdRaw = (int)($payload['division_id'] ?? 0);
            $divisionId = $divisionIdRaw > 0 ? $divisionIdRaw : null;
            $destinationFilter = $this->normalizeDestinationFilter($payload['destination_type'] ?? $payload['destination'] ?? null);
        }

        $opnameTable = $stockScope === 'DIVISION' ? 'inv_division_monthly_opname' : 'inv_warehouse_monthly_opname';
        $openingTable = $this->openingSnapshotTableForScope($stockScope);
        if (!$this->db->table_exists('inv_stock_movement_log')) {
            return [
                'ok' => false,
                'message' => 'Tabel movement log inventory tidak ditemukan. Generator bulanan sekarang memakai movement log aktif.',
            ];
        }
        if (!$this->db->table_exists($opnameTable)) {
            return [
                'ok' => false,
                'message' => 'Tabel opname bulanan belum tersedia: ' . $opnameTable . '. Jalankan SQL 2026-05-06c.',
            ];
        }
        if (!$this->db->table_exists($openingTable)) {
            return [
                'ok' => false,
                'message' => 'Tabel opening snapshot belum tersedia untuk scope ' . $stockScope . '. Jalankan SQL 2026-05-06f.',
            ];
        }

        $rows = $this->fetchInventoryDailyMatrixSourceRowsFromMovement(
            $stockScope,
            '',
            $divisionId,
            $dateFrom,
            $dateTo,
            $destinationFilter,
            false
        );

        if (empty($rows)) {
            return [
                'ok' => false,
                'message' => 'Data movement bulan ' . date('Y-m', strtotime($monthKey)) . ' tidak ditemukan untuk digenerate.',
            ];
        }

        $negativeSamples = [];
        foreach ($rows as $row) {
            $closing = (float)($row['closing_qty_content'] ?? 0);
            if ($closing >= 0) {
                continue;
            }

            $negativeSamples[] = trim((string)($row['movement_date'] ?? ''))
                . ' | '
                . ($stockScope === 'DIVISION' ? ('DIV ' . (string)($row['division_id'] ?? '-') . ' ' . (string)($row['destination_type'] ?? 'OTHER') . ' | ') : '')
                . (string)($row['profile_name'] ?? '-')
                . ' | closing=' . number_format($closing, 4, '.', '');
            if (count($negativeSamples) >= 5) {
                break;
            }
        }
        if (!empty($negativeSamples)) {
            return [
                'ok' => false,
                'message' => 'Generate ditolak karena masih ada stok minus. Perbaiki dulu data minus sebelum generate opname.',
                'data' => [
                    'negative_samples' => $negativeSamples,
                ],
            ];
        }

        $numberFields = [
            'opening_qty_buy', 'opening_qty_content',
            'in_qty_buy', 'in_qty_content',
            'out_qty_buy', 'out_qty_content',
            'discarded_qty_buy', 'discarded_qty_content',
            'spoil_qty_buy', 'spoil_qty_content',
            'waste_qty_buy', 'waste_qty_content',
            'process_loss_qty_buy', 'process_loss_qty_content',
            'variance_qty_buy', 'variance_qty_content',
            'adjustment_plus_qty_buy', 'adjustment_plus_qty_content',
            'adjustment_qty_buy', 'adjustment_qty_content',
            'closing_qty_buy', 'closing_qty_content',
            'waste_total_value', 'spoilage_total_value', 'process_loss_total_value', 'variance_total_value', 'adjustment_plus_total_value',
            'total_value',
        ];

        $aggregated = [];
        foreach ($rows as $row) {
            $groupKeyParts = [
                strtoupper((string)($row['stock_domain'] ?? 'ITEM')),
                (int)($row['item_id'] ?? 0),
                (int)($row['material_id'] ?? 0),
                (int)($row['buy_uom_id'] ?? 0),
                (int)($row['content_uom_id'] ?? 0),
                (string)($row['profile_key'] ?? ''),
            ];
            if ($stockScope === 'DIVISION') {
                array_unshift($groupKeyParts, strtoupper((string)($row['destination_type'] ?? 'OTHER')));
                array_unshift($groupKeyParts, (int)($row['division_id'] ?? 0));
            }
            $groupKey = implode('|', $groupKeyParts);

            if (!isset($aggregated[$groupKey])) {
                $aggregated[$groupKey] = [
                    'month_key' => $monthKey,
                    'division_id' => $stockScope === 'DIVISION' ? (int)($row['division_id'] ?? 0) : null,
                    'destination_type' => $stockScope === 'DIVISION' ? strtoupper((string)($row['destination_type'] ?? 'OTHER')) : null,
                    'stock_domain' => strtoupper((string)($row['stock_domain'] ?? 'ITEM')),
                    'item_id' => isset($row['item_id']) ? (int)$row['item_id'] : null,
                    'material_id' => isset($row['material_id']) ? (int)$row['material_id'] : null,
                    'buy_uom_id' => isset($row['buy_uom_id']) ? (int)$row['buy_uom_id'] : null,
                    'content_uom_id' => (int)($row['content_uom_id'] ?? 0),
                    'profile_key' => (string)($row['profile_key'] ?? ''),
                    'profile_name' => $this->nullableString($row['profile_name'] ?? null),
                    'profile_brand' => $this->nullableString($row['profile_brand'] ?? null),
                    'profile_description' => $this->nullableString($row['profile_description'] ?? null),
                    'profile_expired_date' => $this->normalizeDate((string)($row['profile_expired_date'] ?? '')),
                    'profile_content_per_buy' => round((float)($row['profile_content_per_buy'] ?? 0), 6),
                    'profile_buy_uom_code' => $this->nullableString($row['profile_buy_uom_code'] ?? null),
                    'profile_content_uom_code' => $this->nullableString($row['profile_content_uom_code'] ?? null),
                    'avg_cost_per_content' => round((float)($row['avg_cost_per_content'] ?? 0), 6),
                    'movement_day_count' => 0,
                    'mutation_count' => 0,
                    '_first_date' => '9999-12-31',
                    '_last_date' => '0000-00-00',
                    '_day_map' => [],
                ];
                foreach ($numberFields as $field) {
                    $aggregated[$groupKey][$field] = 0.0;
                }
            }

            $day = (string)($row['movement_date'] ?? '');
            if ($day !== '') {
                $aggregated[$groupKey]['_day_map'][$day] = true;
            }
            $aggregated[$groupKey]['mutation_count'] += (int)($row['mutation_count'] ?? 0);

            if ($day !== '' && $day < $aggregated[$groupKey]['_first_date']) {
                $aggregated[$groupKey]['_first_date'] = $day;
                $aggregated[$groupKey]['opening_qty_buy'] = round((float)($row['opening_qty_buy'] ?? 0), 4);
                $aggregated[$groupKey]['opening_qty_content'] = round((float)($row['opening_qty_content'] ?? 0), 4);
            }

            if ($day !== '' && $day >= $aggregated[$groupKey]['_last_date']) {
                $aggregated[$groupKey]['_last_date'] = $day;
                $aggregated[$groupKey]['closing_qty_buy'] = round((float)($row['closing_qty_buy'] ?? 0), 4);
                $aggregated[$groupKey]['closing_qty_content'] = round((float)($row['closing_qty_content'] ?? 0), 4);
                $aggregated[$groupKey]['avg_cost_per_content'] = round((float)($row['avg_cost_per_content'] ?? 0), 6);
                $aggregated[$groupKey]['total_value'] = round((float)($row['total_value'] ?? 0), 2);
            }

            $sumFields = [
                'in_qty_buy', 'in_qty_content',
                'out_qty_buy', 'out_qty_content',
                'discarded_qty_buy', 'discarded_qty_content',
                'spoil_qty_buy', 'spoil_qty_content',
                'waste_qty_buy', 'waste_qty_content',
                'process_loss_qty_buy', 'process_loss_qty_content',
                'variance_qty_buy', 'variance_qty_content',
                'adjustment_plus_qty_buy', 'adjustment_plus_qty_content',
                'adjustment_qty_buy', 'adjustment_qty_content',
                'waste_total_value', 'spoilage_total_value', 'process_loss_total_value', 'variance_total_value', 'adjustment_plus_total_value',
            ];
            foreach ($sumFields as $field) {
                $aggregated[$groupKey][$field] = round($aggregated[$groupKey][$field] + (float)($row[$field] ?? 0), 4);
            }
        }

        foreach ($aggregated as $groupKey => $row) {
            $aggregated[$groupKey]['movement_day_count'] = count($row['_day_map']);
            unset($aggregated[$groupKey]['_first_date'], $aggregated[$groupKey]['_last_date'], $aggregated[$groupKey]['_day_map']);
        }

        $upsertRow = function (string $table, array $rowData, array $uniqueColumns): void {
            $existingColumns = $this->listTableFields($table);
            if (empty($existingColumns)) {
                return;
            }

            $insertColumns = [];
            $insertValues = [];
            foreach ($rowData as $column => $value) {
                if (isset($existingColumns[$column])) {
                    $insertColumns[] = $column;
                    $insertValues[] = $value;
                }
            }
            if (empty($insertColumns)) {
                return;
            }

            $updateColumns = [];
            foreach ($insertColumns as $column) {
                if (!in_array($column, $uniqueColumns, true)) {
                    $updateColumns[] = $column;
                }
            }
            if (isset($existingColumns['updated_at'])) {
                $updateColumns[] = 'updated_at';
            }

            $updateParts = [];
            foreach ($updateColumns as $column) {
                if ($column === 'updated_at') {
                    $updateParts[] = 'updated_at = CURRENT_TIMESTAMP';
                } else {
                    $updateParts[] = $column . ' = VALUES(' . $column . ')';
                }
            }

            $placeholders = implode(',', array_fill(0, count($insertColumns), '?'));
            $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $insertColumns) . ') VALUES (' . $placeholders . ')';
            if (!empty($updateParts)) {
                $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updateParts);
            }
            $this->db->query($sql, $insertValues);
        };

        $this->db->trans_begin();

        if ($stockScope === 'WAREHOUSE') {
            $this->db->where('month_key', $monthKey)->delete('inv_warehouse_monthly_opname');
        } else {
            $this->db->where('month_key', $monthKey);
            if ($divisionId !== null) {
                $this->db->where('division_id', $divisionId);
            }
            if ($destinationFilter !== null) {
                if ($destinationFilter === 'REGULER') {
                    $this->db->where_not_in('destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
                } elseif ($destinationFilter === 'EVENT') {
                    $this->db->where_in('destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
                } else {
                    $this->db->where('destination_type', $destinationFilter);
                }
            }
            $this->db->delete('inv_division_monthly_opname');
        }

        $opnameUniqueColumns = $stockScope === 'DIVISION'
            ? ['month_key', 'division_id', 'destination_type', 'stock_domain', 'item_id', 'material_id', 'buy_uom_id', 'content_uom_id', 'profile_key']
            : ['month_key', 'stock_domain', 'item_id', 'material_id', 'buy_uom_id', 'content_uom_id', 'profile_key'];

        $generatedRows = 0;
        foreach ($aggregated as $row) {
            $row['generated_by'] = $userId > 0 ? $userId : null;
            $upsertRow($opnameTable, $row, $opnameUniqueColumns);
            $generatedRows++;
        }

        $openingHasDestinationType = $this->db->field_exists('destination_type', $openingTable);
        $openingHasProfileExpiredDate = $this->db->field_exists('profile_expired_date', $openingTable);

        $existingAutoOpeningRows = [];
        if ($stockScope === 'DIVISION') {
            $this->db->from($openingTable)
                ->where('snapshot_month', $nextMonth)
                ->where('source_type', 'AUTO_REBUILD');
            if ($divisionId !== null) {
                $this->db->where('division_id', $divisionId);
            }
            if ($destinationFilter !== null && $openingHasDestinationType) {
                if ($destinationFilter === 'REGULER') {
                    $this->db->where_not_in('destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
                } elseif ($destinationFilter === 'EVENT') {
                    $this->db->where_in('destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
                } else {
                    $this->db->where('destination_type', $destinationFilter);
                }
            }
            $existingAutoOpeningRows = $this->db->get()->result_array();
            foreach ($existingAutoOpeningRows as $existingAutoOpeningRow) {
                $lotSync = $this->syncOpeningSnapshotLots($stockScope, $openingTable, $existingAutoOpeningRow, false);
                if (!($lotSync['ok'] ?? false)) {
                    $this->db->trans_rollback();
                    return $lotSync;
                }
            }
        }

        $this->db
            ->where('snapshot_month', $nextMonth)
            ->where('source_type', 'AUTO_REBUILD');
        if ($stockScope === 'DIVISION' && $divisionId !== null) {
            $this->db->where('division_id', $divisionId);
        }
        if ($stockScope === 'DIVISION' && $destinationFilter !== null && $openingHasDestinationType) {
            if ($destinationFilter === 'REGULER') {
                $this->db->where_not_in('destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
            } elseif ($destinationFilter === 'EVENT') {
                $this->db->where_in('destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
            } else {
                $this->db->where('destination_type', $destinationFilter);
            }
        }
        $this->db->delete($openingTable);

        $openingUniqueColumns = $stockScope === 'DIVISION'
            ? ['snapshot_month', 'division_id', 'destination_type', 'stock_domain', 'item_id', 'material_id', 'buy_uom_id', 'content_uom_id', 'profile_key']
            : ['snapshot_month', 'stock_domain', 'item_id', 'material_id', 'buy_uom_id', 'content_uom_id', 'profile_key'];

        $carriedRows = 0;
        foreach ($aggregated as $row) {
            $closingQtyContent = round((float)($row['closing_qty_content'] ?? 0), 4);
            if ($closingQtyContent <= 0) {
                continue;
            }

            $openingRow = [
                'snapshot_month' => $nextMonth,
                'division_id' => $stockScope === 'DIVISION' ? (int)($row['division_id'] ?? 0) : null,
                'destination_type' => $stockScope === 'DIVISION' ? (string)($row['destination_type'] ?? 'OTHER') : null,
                'stock_domain' => (string)($row['stock_domain'] ?? 'ITEM'),
                'item_id' => isset($row['item_id']) ? (int)$row['item_id'] : null,
                'material_id' => isset($row['material_id']) ? (int)$row['material_id'] : null,
                'buy_uom_id' => isset($row['buy_uom_id']) ? (int)$row['buy_uom_id'] : null,
                'content_uom_id' => (int)($row['content_uom_id'] ?? 0),
                'profile_key' => (string)($row['profile_key'] ?? ''),
                'profile_name' => $this->nullableString($row['profile_name'] ?? null),
                'profile_brand' => $this->nullableString($row['profile_brand'] ?? null),
                'profile_description' => $this->nullableString($row['profile_description'] ?? null),
                'profile_expired_date' => $this->normalizeDate((string)($row['profile_expired_date'] ?? '')),
                'profile_content_per_buy' => round((float)($row['profile_content_per_buy'] ?? 0), 6),
                'profile_buy_uom_code' => $this->nullableString($row['profile_buy_uom_code'] ?? null),
                'profile_content_uom_code' => $this->nullableString($row['profile_content_uom_code'] ?? null),
                'opening_qty_buy' => round((float)($row['closing_qty_buy'] ?? 0), 4),
                'opening_qty_content' => $closingQtyContent,
                'opening_avg_cost_per_content' => round((float)($row['avg_cost_per_content'] ?? 0), 6),
                'opening_total_value' => round((float)($row['total_value'] ?? 0), 2),
                'source_type' => 'AUTO_REBUILD',
                'notes' => 'Auto carry-forward dari opname ' . date('Y-m', strtotime($monthKey)),
                'created_by' => $userId > 0 ? $userId : null,
            ];

            if (!$openingHasDestinationType) {
                unset($openingRow['destination_type']);
            }
            if (!$openingHasProfileExpiredDate) {
                unset($openingRow['profile_expired_date']);
            }

            $upsertRow($openingTable, $openingRow, $openingUniqueColumns);
            if ($stockScope === 'DIVISION') {
                $syncedOpeningRow = $this->findOpeningSnapshotRowForUpdate($openingTable, [
                    'snapshot_month' => $nextMonth,
                    'division_id' => (int)($row['division_id'] ?? 0),
                    'destination_type' => (string)($row['destination_type'] ?? 'OTHER'),
                    'stock_domain' => (string)($row['stock_domain'] ?? 'ITEM'),
                    'item_id' => isset($row['item_id']) ? (int)$row['item_id'] : null,
                    'material_id' => isset($row['material_id']) ? (int)$row['material_id'] : null,
                    'buy_uom_id' => isset($row['buy_uom_id']) ? (int)$row['buy_uom_id'] : null,
                    'content_uom_id' => (int)($row['content_uom_id'] ?? 0),
                    'profile_key' => (string)($row['profile_key'] ?? ''),
                ]);
                $lotSync = $this->syncOpeningSnapshotLots($stockScope, $openingTable, (array)$syncedOpeningRow, true);
                if (!($lotSync['ok'] ?? false)) {
                    $this->db->trans_rollback();
                    return $lotSync;
                }
            }
            $carriedRows++;
        }

        if ($this->db->table_exists('aud_transaction_log')) {
            $this->db->insert('aud_transaction_log', [
                'module_code' => 'INVENTORY',
                'action_code' => 'MONTHLY_OPNAME_GENERATE',
                'entity_table' => $opnameTable,
                'entity_id' => null,
                'transaction_no' => null,
                'actor_user_id' => $userId > 0 ? $userId : null,
                'source_ip' => $sourceIp !== '' ? $sourceIp : null,
                'after_payload' => json_encode([
                    'stock_scope' => $stockScope,
                    'month' => $monthKey,
                    'division_id' => $divisionId,
                    'destination_filter' => $destinationFilter,
                    'generated_rows' => $generatedRows,
                    'carried_rows' => $carriedRows,
                ]),
                'notes' => 'Generate opname bulanan dan carry-forward opening otomatis',
            ]);
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => 'Gagal generate opname bulanan.',
            ];
        }

        $this->db->trans_commit();

        return [
            'ok' => true,
            'message' => 'Generate opname bulanan berhasil. Opening bulan berikutnya juga sudah dibuat untuk saldo akhir > 0.',
            'data' => [
                'stock_scope' => $stockScope,
                'month' => $monthKey,
                'next_month' => $nextMonth,
                'division_id' => $divisionId,
                'destination_filter' => $destinationFilter,
                'opname_rows' => $generatedRows,
                'opening_rows' => $carriedRows,
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

        $catalogHasVendorId = $this->db->field_exists('vendor_id', 'mst_purchase_catalog');
        $vendorLinkReady = $this->purchaseCatalogVendorTableExists();

        $insertColumns = [
            'profile_key',
            'line_kind',
            'item_id',
            'material_id',
        ];
        $selectColumns = [
            'LEFT(c.profile_key, 64) AS profile_key',
            "CASE
                WHEN UPPER(TRIM(COALESCE(c.line_type, 'ITEM'))) = 'ASSET' THEN 'ASSET'
                WHEN UPPER(TRIM(COALESCE(c.line_type, 'ITEM'))) IN ('SERVICE', 'PAYROLL_COMPONENT') THEN 'SERVICE'
                WHEN COALESCE(i_code.id, i_name.id) IS NOT NULL THEN 'ITEM'
                WHEN COALESCE(m_code.id, m_name.id) IS NOT NULL THEN 'MATERIAL'
                ELSE 'ITEM'
            END AS line_kind",
            'COALESCE(i_code.id, i_name.id) AS item_id',
            'COALESCE(NULLIF(COALESCE(i_code.material_id, i_name.material_id), 0), m_code.id, m_name.id) AS material_id',
        ];
        $updateColumns = [
            'line_kind = VALUES(line_kind)',
            'item_id = VALUES(item_id)',
            'material_id = VALUES(material_id)',
        ];

        if ($catalogHasVendorId) {
            $insertColumns[] = 'vendor_id';
            $selectColumns[] = 'COALESCE(v_code.id, v_name.id, ' . $defaultVendorId . ') AS vendor_id';
            $updateColumns[] = 'vendor_id = VALUES(vendor_id)';
        }

        $insertColumns = array_merge($insertColumns, [
            'catalog_name',
            'brand_name',
            'line_description',
            'buy_uom_id',
            'content_uom_id',
            'content_per_buy',
            'conversion_factor_to_content',
            'standard_price',
            'last_unit_price',
            'last_purchase_date',
            'last_purchase_order_id',
            'last_purchase_line_id',
            'notes',
            'is_active',
        ]);
        $selectColumns = array_merge($selectColumns, [
            "LEFT(COALESCE(NULLIF(TRIM(c.display_name), ''), NULLIF(TRIM(ci.item_name), ''), NULLIF(TRIM(cm.material_name), ''), 'UNNAMED CORE CATALOG'), 150) AS catalog_name",
            "NULLIF(LEFT(TRIM(c.brand_name), 120), '') AS brand_name",
            "NULLIF(LEFT(TRIM(c.last_description), 255), '') AS line_description",
            'COALESCE(bu_code.id, ' . $defaultUomId . ') AS buy_uom_id',
            'COALESCE(cu_code.id, bu_code.id, ' . $defaultUomId . ') AS content_uom_id',
            'COALESCE(NULLIF(c.qty_gramasi, 0), 1) AS content_per_buy',
            'COALESCE(NULLIF(c.qty_gramasi, 0), 1) AS conversion_factor_to_content',
            'COALESCE(c.last_unit_price, 0) AS standard_price',
            'COALESCE(c.last_unit_price, 0) AS last_unit_price',
            'c.last_purchase_date',
            'NULL',
            'NULL',
            "CONCAT('Sync core pur_purchase_catalog id=', c.id)",
            'c.is_active',
        ]);
        $updateColumns = array_merge($updateColumns, [
            'catalog_name = VALUES(catalog_name)',
            'brand_name = VALUES(brand_name)',
            'line_description = VALUES(line_description)',
            'buy_uom_id = VALUES(buy_uom_id)',
            'content_uom_id = VALUES(content_uom_id)',
            'content_per_buy = VALUES(content_per_buy)',
            'conversion_factor_to_content = VALUES(conversion_factor_to_content)',
            'standard_price = VALUES(standard_price)',
            'last_unit_price = VALUES(last_unit_price)',
            'last_purchase_date = VALUES(last_purchase_date)',
            'notes = VALUES(notes)',
            'is_active = VALUES(is_active)',
            'updated_at = CURRENT_TIMESTAMP',
        ]);

        $sql = "
            INSERT INTO mst_purchase_catalog (
                " . implode(",\n                ", $insertColumns) . "
            )
            SELECT
                " . implode(",\n                ", $selectColumns) . "
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
                " . implode(",\n                ", $updateColumns) . "
        ";

        $this->db->query($sql);
        $masterAffectedRows = (int)$this->db->affected_rows();

        if ($this->db->error()['code']) {
            return [
                'ok' => false,
                'message' => 'Gagal sinkron katalog: ' . (string)$this->db->error()['message'],
            ];
        }

        $vendorLinkAffectedRows = 0;
        if ($vendorLinkReady) {
            $vendorSql = "
                INSERT INTO mst_purchase_catalog_vendor (
                    catalog_id, vendor_id, standard_price, last_unit_price, last_purchase_date,
                    last_purchase_order_id, last_purchase_line_id, notes, is_active
                )
                SELECT
                    mc.id AS catalog_id,
                    COALESCE(v_code.id, v_name.id, {$defaultVendorId}) AS vendor_id,
                    COALESCE(c.last_unit_price, 0) AS standard_price,
                    COALESCE(c.last_unit_price, 0) AS last_unit_price,
                    c.last_purchase_date,
                    NULL,
                    NULL,
                    CONCAT('Sync core pur_purchase_catalog id=', c.id),
                    c.is_active
                FROM core.pur_purchase_catalog c
                JOIN mst_purchase_catalog mc ON mc.profile_key = LEFT(c.profile_key, 64)
                LEFT JOIN core.m_vendor cv ON cv.id = c.last_vendor_id
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
                WHERE c.profile_key IS NOT NULL AND c.profile_key <> ''
                ORDER BY c.id DESC
                LIMIT {$limit}
                ON DUPLICATE KEY UPDATE
                    standard_price = VALUES(standard_price),
                    last_unit_price = VALUES(last_unit_price),
                    last_purchase_date = VALUES(last_purchase_date),
                    notes = VALUES(notes),
                    is_active = VALUES(is_active),
                    updated_at = CURRENT_TIMESTAMP
            ";

            $this->db->query($vendorSql);
            $vendorLinkAffectedRows = (int)$this->db->affected_rows();

            if ($this->db->error()['code']) {
                return [
                    'ok' => false,
                    'message' => 'Gagal sinkron vendor catalog: ' . (string)$this->db->error()['message'],
                ];
            }
        }

        $total = (int)$this->db->count_all('mst_purchase_catalog');
        return [
            'ok' => true,
            'message' => 'Sinkron katalog core berhasil dijalankan.',
            'data' => [
                'limit' => $limit,
                'affected_rows' => $masterAffectedRows + $vendorLinkAffectedRows,
                'catalog_total' => $total,
                'vendor_link_affected_rows' => $vendorLinkAffectedRows,
            ],
        ];
    }

    public function list_warehouse_stock(string $q, int $limit, string $dateFrom = '', string $dateTo = ''): array
    {
        if ($this->db->table_exists('inv_warehouse_monthly_stock')) {
            return $this->list_warehouse_stock_monthly($q, $limit, $dateFrom, $dateTo);
        }

        return [];
    }

    public function list_division_stock(string $q, int $limit, ?string $destinationFilter = null, string $dateFrom = '', string $dateTo = '', ?int $divisionId = null): array
    {
        if ($this->db->table_exists('inv_division_monthly_stock')) {
            return $this->list_division_stock_monthly($q, $limit, $destinationFilter, $dateFrom, $dateTo, $divisionId);
        }

        return [];
    }

    private function list_warehouse_stock_monthly(string $q, int $limit, string $dateFrom = '', string $dateTo = ''): array
    {
        $from = $this->normalizeDate($dateFrom);
        $to = $this->normalizeDate($dateTo);
        $targetMonth = date('Y-m-01', strtotime($to ?: date('Y-m-d')));

        $latestMonthSubquery = $this->db
            ->select('identity_key, MAX(month_key) AS month_key', false)
            ->from('inv_warehouse_monthly_stock')
            ->where('month_key <=', $targetMonth)
            ->group_by('identity_key')
            ->get_compiled_select();

        $activityDateExpr = 'COALESCE(s.last_movement_date, DATE(s.updated_at), s.month_key)';

        $this->db
            ->select('s.id, "ITEM" AS stock_domain, s.item_id, COALESCE(s.material_id, i.material_id) AS material_id, s.buy_uom_id, s.content_uom_id, i.item_code, i.item_name, s.profile_key, s.profile_name, s.profile_brand, s.profile_description', false)
            ->select('s.profile_content_per_buy, s.profile_buy_uom_code, s.profile_content_uom_code')
            ->select('s.closing_qty_buy AS qty_buy_balance, s.closing_qty_content AS qty_content_balance, s.avg_cost_per_content')
            ->select('COALESCE(s.updated_at, s.last_movement_at, CONCAT(s.month_key, " 00:00:00")) AS updated_at', false)
            ->select('s.profile_expired_date')
            ->from('inv_warehouse_monthly_stock s')
            ->join('(' . $latestMonthSubquery . ') lm', 'lm.identity_key = s.identity_key AND lm.month_key = s.month_key', 'inner', false)
            ->join('mst_item i', 'i.id = s.item_id', 'left');

        if ($from !== null) {
            $this->db->where($activityDateExpr . ' >= ' . $this->db->escape($from), null, false);
        }
        if ($to !== null) {
            $this->db->where($activityDateExpr . ' <= ' . $this->db->escape($to), null, false);
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

        $query = $this->db
            ->order_by('s.item_id', 'ASC')
            ->order_by('s.profile_name', 'ASC')
            ->limit($limit)
            ->get();

        if (!$query) {
            return [];
        }

        $rows = $query->result_array();
        $best = [];
        foreach ($rows as $row) {
            $identityKey = implode('|', [
                (int)($row['item_id'] ?? 0),
                (int)($row['material_id'] ?? 0),
                (int)($row['buy_uom_id'] ?? 0),
                (int)($row['content_uom_id'] ?? 0),
                strtoupper(trim((string)($row['profile_key'] ?? ''))),
            ]);
            if (!isset($best[$identityKey])) {
                $best[$identityKey] = $row;
                continue;
            }
            $current = $best[$identityKey];
            $currentUpdated = (string)($current['updated_at'] ?? '');
            $nextUpdated = (string)($row['updated_at'] ?? '');
            if ($nextUpdated > $currentUpdated || ($nextUpdated === $currentUpdated && (int)($row['id'] ?? 0) > (int)($current['id'] ?? 0))) {
                $best[$identityKey] = $row;
            }
        }

        return array_values($best);
    }

    private function list_division_stock_monthly(string $q, int $limit, ?string $destinationFilter = null, string $dateFrom = '', string $dateTo = '', ?int $divisionId = null): array
    {
        $from = $this->normalizeDate($dateFrom);
        $to = $this->normalizeDate($dateTo);
        $targetMonth = date('Y-m-01', strtotime($to ?: date('Y-m-d')));
        $destinationFilter = $this->normalizeDestinationFilter($destinationFilter);

        $divisionCodeColumn = $this->db->field_exists('division_code', 'mst_operational_division')
            ? 'division_code'
            : ($this->db->field_exists('code', 'mst_operational_division') ? 'code' : null);
        $divisionNameColumn = $this->db->field_exists('division_name', 'mst_operational_division')
            ? 'division_name'
            : ($this->db->field_exists('name', 'mst_operational_division') ? 'name' : null);
        $hasDivisionCode = $divisionCodeColumn !== null;
        $hasDivisionName = $divisionNameColumn !== null;
        $divisionCodeSelect = $hasDivisionCode ? ('d.' . $divisionCodeColumn . ' AS division_code') : 'CAST(s.division_id AS CHAR) AS division_code';
        $divisionNameSelect = $hasDivisionName ? ('d.' . $divisionNameColumn . ' AS division_name') : 'NULL AS division_name';

        $latestMonthSubquery = $this->db
            ->select('division_id, destination_type, identity_key, MAX(month_key) AS month_key', false)
            ->from('inv_division_monthly_stock')
            ->where('month_key <=', $targetMonth)
            ->group_by(['division_id', 'destination_type', 'identity_key'])
            ->get_compiled_select();

        $destinationGroupExpr = "CASE
                WHEN COALESCE(s.destination_type, 'OTHER') IN ('BAR_EVENT','KITCHEN_EVENT') THEN 'EVENT'
                ELSE 'REGULER'
            END";
        $destinationNameExpr = "CASE COALESCE(s.destination_type, 'OTHER')
                WHEN 'BAR' THEN 'Bar Reguler'
                WHEN 'KITCHEN' THEN 'Kitchen Reguler'
                WHEN 'BAR_EVENT' THEN 'Bar Event'
                WHEN 'KITCHEN_EVENT' THEN 'Kitchen Event'
                WHEN 'OFFICE' THEN 'Office Reguler'
                WHEN 'GUDANG' THEN 'Gudang'
                ELSE 'Reguler'
            END";
        $activityDateExpr = 'COALESCE(s.last_movement_date, DATE(s.updated_at), s.month_key)';

        $this->db
            ->select('s.id, "ITEM" AS stock_domain, s.division_id, ' . $divisionCodeSelect . ', ' . $divisionNameSelect . ', s.item_id, COALESCE(s.material_id, i.material_id) AS material_id, s.buy_uom_id, s.content_uom_id', false)
            ->select('s.destination_type AS destination_type', false)
            ->select($destinationGroupExpr . ' AS destination_group', false)
            ->select($destinationNameExpr . ' AS destination_name', false)
            ->select('i.item_code, i.item_name, m.material_code, m.material_name')
            ->select('s.profile_key, s.profile_name, s.profile_brand, s.profile_description')
            ->select('s.profile_content_per_buy, s.profile_buy_uom_code, s.profile_content_uom_code')
            ->select('s.closing_qty_buy AS qty_buy_balance, s.closing_qty_content AS qty_content_balance, s.avg_cost_per_content')
            ->select('COALESCE(s.updated_at, s.last_movement_at, CONCAT(s.month_key, " 00:00:00")) AS updated_at', false)
            ->select('s.profile_expired_date')
            ->from('inv_division_monthly_stock s')
            ->join('(' . $latestMonthSubquery . ') lm', 'lm.division_id = s.division_id AND lm.destination_type = s.destination_type AND lm.identity_key = s.identity_key AND lm.month_key = s.month_key', 'inner', false)
            ->join('mst_operational_division d', 'd.id = s.division_id', 'left')
            ->join('mst_item i', 'i.id = s.item_id', 'left')
            ->join('mst_material m', 'm.id = s.material_id', 'left');

        if ($from !== null) {
            $this->db->where($activityDateExpr . ' >= ' . $this->db->escape($from), null, false);
        }
        if ($to !== null) {
            $this->db->where($activityDateExpr . ' <= ' . $this->db->escape($to), null, false);
        }
        if ($divisionId !== null && $divisionId > 0) {
            $this->db->where('s.division_id', $divisionId);
        }

        if ($destinationFilter !== null && $destinationFilter !== 'ALL') {
            if ($destinationFilter === 'REGULER') {
                $this->db->where_not_in('s.destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
            } elseif ($destinationFilter === 'EVENT') {
                $this->db->where_in('s.destination_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
            } else {
                $this->db->where('s.destination_type', $destinationFilter);
            }
        }

        if ($q !== '') {
            $this->db->group_start();
            $hasDivisionFilter = false;
            if ($hasDivisionCode) {
                $this->db->like('d.' . $divisionCodeColumn, $q);
                $hasDivisionFilter = true;
            }
            if ($hasDivisionName) {
                if ($hasDivisionFilter) {
                    $this->db->or_like('d.' . $divisionNameColumn, $q);
                } else {
                    $this->db->like('d.' . $divisionNameColumn, $q);
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
                ->or_like($destinationGroupExpr, $q, 'both', false)
                ->or_like($destinationNameExpr, $q, 'both', false)
                ->group_end();
        }

        $query = $this->db
            ->order_by($hasDivisionName ? ('d.' . $divisionNameColumn) : 's.division_id', 'ASC')
            ->order_by('i.item_name', 'ASC')
            ->order_by('m.material_name', 'ASC')
            ->limit($limit)
            ->get();

        if (!$query) {
            return [];
        }

        $rows = $query->result_array();
        $best = [];
        foreach ($rows as $row) {
            $identityKey = implode('|', [
                (int)($row['division_id'] ?? 0),
                strtoupper((string)($row['destination_type'] ?? 'OTHER')),
                (int)($row['item_id'] ?? 0),
                (int)($row['material_id'] ?? 0),
                (int)($row['buy_uom_id'] ?? 0),
                (int)($row['content_uom_id'] ?? 0),
                strtoupper(trim((string)($row['profile_key'] ?? ''))),
            ]);
            if (!isset($best[$identityKey])) {
                $best[$identityKey] = $row;
                continue;
            }
            $current = $best[$identityKey];
            $currentUpdated = (string)($current['updated_at'] ?? '');
            $nextUpdated = (string)($row['updated_at'] ?? '');
            if ($nextUpdated > $currentUpdated || ($nextUpdated === $currentUpdated && (int)($row['id'] ?? 0) > (int)($current['id'] ?? 0))) {
                $best[$identityKey] = $row;
            }
        }

        return array_values($best);
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

    public function list_purchase_orders_dashboard(string $q, string $status, string $dateStart, string $dateEnd, int $limit): array
    {
        if (!$this->db->table_exists('pur_purchase_order')) {
            return [];
        }

        $from = $this->normalizeDate($dateStart);
        $to = $this->normalizeDate($dateEnd);
        $hasDivisionRequestLink = $this->db->table_exists('pur_division_request_link');
        $hasTxnLog = $this->db->table_exists('pur_purchase_txn_log');

        $this->db
            ->select('po.id, po.po_no, po.request_date, po.destination_type, po.status, po.grand_total')
            ->select('po.created_at, po.payment_account_id, pt.type_code AS purchase_type_code, pt.type_name AS purchase_type_name')
            ->select('v.vendor_code, v.vendor_name, a.account_code AS payment_account_code, a.account_name AS payment_account_name')
            ->from('pur_purchase_order po')
            ->join('mst_purchase_type pt', 'pt.id = po.purchase_type_id', 'left')
            ->join('mst_vendor v', 'v.id = po.vendor_id', 'left')
            ->join('fin_company_account a', 'a.id = po.payment_account_id', 'left');

        if ($hasDivisionRequestLink) {
            $this->db
                ->select('CASE WHEN dsrc.purchase_order_id IS NULL THEN 0 ELSE 1 END AS is_division_request_po', false)
                ->join("(SELECT DISTINCT doc_id AS purchase_order_id FROM pur_division_request_link WHERE doc_type='PO') dsrc", 'dsrc.purchase_order_id = po.id', 'left', false);
        } else {
            $this->db->select('0 AS is_division_request_po', false);
        }

        if ($hasTxnLog) {
            $this->db
                ->select('CASE WHEN pup.purchase_order_id IS NULL THEN 0 ELSE 1 END AS has_po_update_review', false)
                ->join("(SELECT DISTINCT purchase_order_id FROM pur_purchase_txn_log WHERE action_code='PO_UPDATE') pup", 'pup.purchase_order_id = po.id', 'left', false);
        } else {
            $this->db->select('0 AS has_po_update_review', false);
        }

        if ($hasDivisionRequestLink && $hasTxnLog) {
            $this->db->select('CASE WHEN dsrc.purchase_order_id IS NOT NULL AND pup.purchase_order_id IS NULL THEN 1 ELSE 0 END AS requires_edit_review', false);
        } elseif ($hasDivisionRequestLink) {
            $this->db->select('CASE WHEN dsrc.purchase_order_id IS NOT NULL THEN 1 ELSE 0 END AS requires_edit_review', false);
        } else {
            $this->db->select('0 AS requires_edit_review', false);
        }

        $status = strtoupper(trim($status));
        if ($status !== '' && $status !== 'ALL') {
            $this->db->where('po.status', $status);
        }
        if ($from !== null) {
            $this->db->where('po.request_date >=', $from);
        }
        if ($to !== null) {
            $this->db->where('po.request_date <=', $to);
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

    public function list_purchase_order_lines_dashboard(string $q, string $status, string $dateStart, string $dateEnd, int $limit): array
    {
        if (!$this->db->table_exists('pur_purchase_order_line') || !$this->db->table_exists('pur_purchase_order')) {
            return [];
        }

        $from = $this->normalizeDate($dateStart);
        $to = $this->normalizeDate($dateEnd);

        $this->db
            ->select('l.id, l.purchase_order_id, l.line_no, l.line_kind, l.qty_buy, l.content_per_buy, l.qty_content, l.unit_price, l.line_subtotal')
            ->select('l.snapshot_item_name, l.snapshot_material_name, l.snapshot_brand_name, l.snapshot_line_description')
            ->select('l.snapshot_buy_uom_code, l.snapshot_content_uom_code, l.profile_key')
            ->select('po.po_no, po.request_date, po.status, po.destination_type, po.purchase_type_id')
            ->select('pt.type_name AS purchase_type_name, v.vendor_name')
            ->from('pur_purchase_order_line l')
            ->join('pur_purchase_order po', 'po.id = l.purchase_order_id', 'inner')
            ->join('mst_purchase_type pt', 'pt.id = po.purchase_type_id', 'left')
            ->join('mst_vendor v', 'v.id = po.vendor_id', 'left');

        $status = strtoupper(trim($status));
        if ($status !== '' && $status !== 'ALL') {
            $this->db->where('po.status', $status);
        }
        if ($from !== null) {
            $this->db->where('po.request_date >=', $from);
        }
        if ($to !== null) {
            $this->db->where('po.request_date <=', $to);
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
            ->order_by('COALESCE(po.purchase_type_id, 999999)', 'ASC', false)
            ->order_by('po.request_date', 'DESC')
            ->order_by('po.id', 'DESC')
            ->order_by('l.line_no', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    public function get_purchase_report_overview(string $dateFrom, string $dateTo, string $status, int $purchaseTypeId = 0): array
    {
        $summary = [
            'total_po' => 0,
            'total_line' => 0,
            'total_qty_buy' => 0.0,
            'total_value' => 0.0,
        ];

        if (!$this->db->table_exists('pur_purchase_order') || !$this->db->table_exists('pur_purchase_order_line')) {
            return $summary;
        }

        $from = $this->normalizeDate($dateFrom);
        $to = $this->normalizeDate($dateTo);
        $status = strtoupper(trim($status));

        $this->db
            ->select('COUNT(DISTINCT po.id) AS total_po', false)
            ->select('COUNT(l.id) AS total_line', false)
            ->select('COALESCE(SUM(l.qty_buy), 0) AS total_qty_buy', false)
            ->select('COALESCE(SUM(l.line_subtotal), 0) AS total_value', false)
            ->from('pur_purchase_order po')
            ->join('pur_purchase_order_line l', 'l.purchase_order_id = po.id', 'inner');

        if ($from !== null) {
            $this->db->where('po.request_date >=', $from);
        }
        if ($to !== null) {
            $this->db->where('po.request_date <=', $to);
        }
        if ($status !== '' && $status !== 'ALL') {
            $this->db->where('po.status', $status);
        }
        if ($purchaseTypeId > 0) {
            $this->db->where('po.purchase_type_id', $purchaseTypeId);
        }

        $row = $this->db->get()->row_array();
        if (!$row) {
            return $summary;
        }

        $summary['total_po'] = (int)($row['total_po'] ?? 0);
        $summary['total_line'] = (int)($row['total_line'] ?? 0);
        $summary['total_qty_buy'] = round((float)($row['total_qty_buy'] ?? 0), 2);
        $summary['total_value'] = round((float)($row['total_value'] ?? 0), 2);
        return $summary;
    }

    public function list_purchase_report_monthly(string $dateFrom, string $dateTo, string $status, int $purchaseTypeId = 0): array
    {
        if (!$this->db->table_exists('pur_purchase_order') || !$this->db->table_exists('pur_purchase_order_line')) {
            return [];
        }

        $from = $this->normalizeDate($dateFrom);
        $to = $this->normalizeDate($dateTo);
        $status = strtoupper(trim($status));

        $this->db
            ->select("DATE_FORMAT(po.request_date, '%Y-%m') AS month_key", false)
            ->select('po.purchase_type_id, pt.type_name AS purchase_type_name')
            ->select('COUNT(DISTINCT po.id) AS total_po', false)
            ->select('COUNT(l.id) AS total_line', false)
            ->select('COALESCE(SUM(l.qty_buy), 0) AS total_qty_buy', false)
            ->select('COALESCE(SUM(l.line_subtotal), 0) AS total_value', false)
            ->from('pur_purchase_order po')
            ->join('pur_purchase_order_line l', 'l.purchase_order_id = po.id', 'inner')
            ->join('mst_purchase_type pt', 'pt.id = po.purchase_type_id', 'left');

        if ($from !== null) {
            $this->db->where('po.request_date >=', $from);
        }
        if ($to !== null) {
            $this->db->where('po.request_date <=', $to);
        }
        if ($status !== '' && $status !== 'ALL') {
            $this->db->where('po.status', $status);
        }
        if ($purchaseTypeId > 0) {
            $this->db->where('po.purchase_type_id', $purchaseTypeId);
        }

        return $this->db
            ->group_by(['month_key', 'po.purchase_type_id', 'pt.type_name'])
            ->order_by('month_key', 'DESC')
            ->order_by('COALESCE(po.purchase_type_id, 999999)', 'ASC', false)
            ->get()
            ->result_array();
    }

    public function list_purchase_report_daily(string $dateFrom, string $dateTo, string $status, int $purchaseTypeId = 0): array
    {
        if (!$this->db->table_exists('pur_purchase_order') || !$this->db->table_exists('pur_purchase_order_line')) {
            return [];
        }

        $from = $this->normalizeDate($dateFrom);
        $to = $this->normalizeDate($dateTo);
        $status = strtoupper(trim($status));

        $this->db
            ->select('po.request_date')
            ->select('po.purchase_type_id, pt.type_name AS purchase_type_name')
            ->select('COUNT(DISTINCT po.id) AS total_po', false)
            ->select('COUNT(l.id) AS total_line', false)
            ->select('COALESCE(SUM(l.qty_buy), 0) AS total_qty_buy', false)
            ->select('COALESCE(SUM(l.line_subtotal), 0) AS total_value', false)
            ->from('pur_purchase_order po')
            ->join('pur_purchase_order_line l', 'l.purchase_order_id = po.id', 'inner')
            ->join('mst_purchase_type pt', 'pt.id = po.purchase_type_id', 'left');

        if ($from !== null) {
            $this->db->where('po.request_date >=', $from);
        }
        if ($to !== null) {
            $this->db->where('po.request_date <=', $to);
        }
        if ($status !== '' && $status !== 'ALL') {
            $this->db->where('po.status', $status);
        }
        if ($purchaseTypeId > 0) {
            $this->db->where('po.purchase_type_id', $purchaseTypeId);
        }

        return $this->db
            ->group_by(['po.request_date', 'po.purchase_type_id', 'pt.type_name'])
            ->order_by('po.request_date', 'DESC')
            ->order_by('COALESCE(po.purchase_type_id, 999999)', 'ASC', false)
            ->get()
            ->result_array();
    }

    public function list_purchase_report_detail_by_day_type(string $requestDate, int $purchaseTypeId, string $status = 'ALL'): array
    {
        if (!$this->db->table_exists('pur_purchase_order') || !$this->db->table_exists('pur_purchase_order_line')) {
            return [];
        }

        $date = $this->normalizeDate($requestDate);
        if ($date === null || $purchaseTypeId <= 0) {
            return [];
        }

        $status = strtoupper(trim($status));

        $this->db
            ->select('po.id AS purchase_order_id, po.po_no, po.request_date, po.status')
            ->select('pt.type_name AS purchase_type_name, v.vendor_name')
            ->select('l.line_no, l.line_kind, l.qty_buy, l.content_per_buy, l.line_subtotal')
            ->select('l.snapshot_item_name, l.snapshot_material_name, l.snapshot_brand_name, l.snapshot_line_description')
            ->select('l.snapshot_buy_uom_code, l.snapshot_content_uom_code')
            ->from('pur_purchase_order po')
            ->join('pur_purchase_order_line l', 'l.purchase_order_id = po.id', 'inner')
            ->join('mst_purchase_type pt', 'pt.id = po.purchase_type_id', 'left')
            ->join('mst_vendor v', 'v.id = po.vendor_id', 'left')
            ->where('po.request_date', $date)
            ->where('po.purchase_type_id', $purchaseTypeId);

        if ($status !== '' && $status !== 'ALL') {
            $this->db->where('po.status', $status);
        }

        return $this->db
            ->order_by('po.po_no', 'ASC')
            ->order_by('l.line_no', 'ASC')
            ->get()
            ->result_array();
    }

    public function list_purchase_report_matrix_product_details(string $dateFrom, string $dateTo, string $status, int $purchaseTypeId = 0): array
    {
        if (!$this->db->table_exists('pur_purchase_order') || !$this->db->table_exists('pur_purchase_order_line')) {
            return [];
        }

        $from = $this->normalizeDate($dateFrom);
        $to = $this->normalizeDate($dateTo);
        $status = strtoupper(trim($status));

        $this->db
            ->select('po.request_date, po.purchase_type_id')
            ->select('l.line_no, l.qty_buy, l.line_subtotal')
            ->select('l.snapshot_item_name, l.snapshot_material_name, l.snapshot_line_description')
            ->select('l.snapshot_buy_uom_code')
            ->from('pur_purchase_order po')
            ->join('pur_purchase_order_line l', 'l.purchase_order_id = po.id', 'inner');

        if ($from !== null) {
            $this->db->where('po.request_date >=', $from);
        }
        if ($to !== null) {
            $this->db->where('po.request_date <=', $to);
        }
        if ($status !== '' && $status !== 'ALL') {
            $this->db->where('po.status', $status);
        }
        if ($purchaseTypeId > 0) {
            $this->db->where('po.purchase_type_id', $purchaseTypeId);
        }

        return $this->db
            ->order_by('po.request_date', 'DESC')
            ->order_by('po.purchase_type_id', 'ASC')
            ->order_by('po.id', 'ASC')
            ->order_by('l.line_no', 'ASC')
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

    public function rebuild_purchase_impacts(array $payload, int $userId, string $sourceIp = ''): array
    {
        if (!$this->db->table_exists('pur_purchase_order')) {
            return [
                'ok' => false,
                'message' => 'Tabel pur_purchase_order belum tersedia.',
            ];
        }

        $scope = strtoupper(trim((string)($payload['scope'] ?? 'GLOBAL')));
        if (!in_array($scope, ['TRANSACTION', 'ITEM', 'FILTER', 'GLOBAL'], true)) {
            return [
                'ok' => false,
                'message' => 'Scope rebuild tidak valid. Gunakan TRANSACTION, ITEM, FILTER, atau GLOBAL.',
            ];
        }

        $dryRun = !empty($payload['dry_run']);
        $limit = (int)($payload['limit'] ?? 300);
        if ($limit <= 0 || $limit > 5000) {
            $limit = 300;
        }

        $dateFrom = $this->normalizeDate((string)($payload['date_from'] ?? ''));
        $dateTo = $this->normalizeDate((string)($payload['date_to'] ?? ''));
        if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            return [
                'ok' => false,
                'message' => 'date_from tidak boleh lebih besar dari date_to.',
            ];
        }

        $statuses = $this->normalizeRebuildStatuses($payload['statuses'] ?? []);
        if (empty($statuses) && $scope !== 'TRANSACTION') {
            $statuses = ['RECEIVED', 'PAID'];
        }

        $purchaseOrderId = (int)($payload['purchase_order_id'] ?? 0);
        $poNo = trim((string)($payload['po_no'] ?? ''));
        $itemId = (int)($payload['item_id'] ?? 0);
        $materialId = (int)($payload['material_id'] ?? 0);

        if ($scope === 'TRANSACTION') {
            if ($purchaseOrderId <= 0 && $poNo !== '') {
                $resolved = $this->db
                    ->select('id')
                    ->from('pur_purchase_order')
                    ->where('po_no', $poNo)
                    ->limit(1)
                    ->get()
                    ->row_array();
                $purchaseOrderId = (int)($resolved['id'] ?? 0);
            }

            if ($purchaseOrderId <= 0) {
                return [
                    'ok' => false,
                    'message' => 'Scope TRANSACTION membutuhkan purchase_order_id valid atau po_no yang terdaftar.',
                ];
            }
        }

        if ($scope === 'ITEM') {
            if (!$this->db->table_exists('pur_purchase_order_line')) {
                return [
                    'ok' => false,
                    'message' => 'Tabel pur_purchase_order_line belum tersedia untuk scope ITEM.',
                ];
            }

            if ($itemId <= 0 && $materialId <= 0) {
                return [
                    'ok' => false,
                    'message' => 'Scope ITEM membutuhkan item_id atau material_id.',
                ];
            }
        }

        $this->db
            ->distinct()
            ->select('po.id, po.po_no, po.status, po.request_date')
            ->from('pur_purchase_order po');

        if ($scope === 'TRANSACTION') {
            $this->db->where('po.id', $purchaseOrderId);
        }

        if ($scope === 'ITEM') {
            $this->db->join('pur_purchase_order_line pol', 'pol.purchase_order_id = po.id', 'inner');
            if ($itemId > 0) {
                $this->db->where('pol.item_id', $itemId);
            }
            if ($materialId > 0) {
                $this->db->where('pol.material_id', $materialId);
            }
        }

        if ($dateFrom !== null) {
            $this->db->where('po.request_date >=', $dateFrom);
        }
        if ($dateTo !== null) {
            $this->db->where('po.request_date <=', $dateTo);
        }
        if (!empty($statuses)) {
            $this->db->where_in('po.status', $statuses);
        }

        $this->db
            ->order_by('po.request_date', 'DESC')
            ->order_by('po.id', 'DESC');

        if ($scope === 'TRANSACTION') {
            $this->db->limit(1);
        } else {
            $this->db->limit($limit);
        }

        $candidates = $this->db->get()->result_array();

        $summary = [
            'total_candidates' => count($candidates),
            'planned' => 0,
            'processed' => 0,
            'success' => 0,
            'changed' => 0,
            'unchanged' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        if (empty($candidates)) {
            return [
                'ok' => true,
                'message' => 'Tidak ada purchase order yang cocok dengan filter rebuild.',
                'data' => [
                    'scope' => $scope,
                    'dry_run' => $dryRun,
                    'summary' => $summary,
                    'rows' => [],
                ],
            ];
        }

        $eligibleStatuses = ['RECEIVED', 'PAID'];
        $resultRows = [];

        foreach ($candidates as $row) {
            $poId = (int)($row['id'] ?? 0);
            $poNoValue = (string)($row['po_no'] ?? '');
            $status = strtoupper(trim((string)($row['status'] ?? '')));

            $itemResult = [
                'purchase_order_id' => $poId,
                'po_no' => $poNoValue,
                'status' => $status,
                'request_date' => (string)($row['request_date'] ?? ''),
                'result' => 'SKIPPED',
                'changed' => false,
                'message' => '',
                'receipt_effect' => 'SKIPPED',
                'payment_effect' => 'SKIPPED',
            ];

            if (!in_array($status, $eligibleStatuses, true)) {
                $summary['skipped']++;
                $itemResult['message'] = 'Status tidak eligible untuk rebuild dampak (hanya RECEIVED/PAID).';
                $resultRows[] = $itemResult;
                continue;
            }

            if ($dryRun) {
                $summary['planned']++;
                $itemResult['result'] = 'PLANNED';
                $itemResult['message'] = 'Akan menjalankan reconcile pada status ' . $status . '.';
                $itemResult['receipt_effect'] = 'POTENTIAL';
                $itemResult['payment_effect'] = $status === 'PAID' ? 'POTENTIAL' : 'SKIPPED';
                $resultRows[] = $itemResult;
                continue;
            }

            $summary['processed']++;

            $run = $this->update_order_status($poId, $status, $userId, $sourceIp);
            if (!($run['ok'] ?? false)) {
                $summary['failed']++;
                $itemResult['result'] = 'FAILED';
                $itemResult['message'] = (string)($run['message'] ?? 'Gagal menjalankan reconcile.');
                $resultRows[] = $itemResult;
                continue;
            }

            $summary['success']++;

            $runData = (array)($run['data'] ?? []);
            $receiptData = (array)($runData['receipt_auto_post'] ?? []);
            $paymentData = (array)($runData['payment_auto_apply'] ?? []);
            $receiptChanged = $this->rebuildEffectChanged($receiptData);
            $paymentChanged = $status === 'PAID' ? $this->rebuildEffectChanged($paymentData) : false;
            $changed = $receiptChanged || $paymentChanged;

            if ($changed) {
                $summary['changed']++;
            } else {
                $summary['unchanged']++;
            }

            $itemResult['result'] = 'OK';
            $itemResult['changed'] = $changed;
            $itemResult['message'] = (string)($run['message'] ?? 'Rebuild selesai.');
            $itemResult['receipt_effect'] = $receiptChanged ? 'POSTED' : 'SKIPPED';
            $itemResult['payment_effect'] = $status === 'PAID'
                ? ($paymentChanged ? 'POSTED' : 'SKIPPED')
                : 'SKIPPED';
            $resultRows[] = $itemResult;
        }

        if (!$dryRun && $this->db->table_exists('aud_transaction_log')) {
            $this->db->insert('aud_transaction_log', [
                'module_code' => 'PURCHASE',
                'action_code' => 'PO_REBUILD_IMPACT_BATCH',
                'entity_table' => 'pur_purchase_order',
                'entity_id' => null,
                'transaction_no' => null,
                'actor_user_id' => $userId > 0 ? $userId : null,
                'source_ip' => $sourceIp !== '' ? $sourceIp : null,
                'after_payload' => json_encode([
                    'scope' => $scope,
                    'filter' => [
                        'purchase_order_id' => $purchaseOrderId,
                        'po_no' => $poNo,
                        'item_id' => $itemId,
                        'material_id' => $materialId,
                        'statuses' => $statuses,
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo,
                        'limit' => $limit,
                    ],
                    'summary' => $summary,
                ]),
                'notes' => 'Batch rebuild impact purchase (' . $scope . ')',
            ]);
        }

        $message = $dryRun
            ? 'Dry-run rebuild selesai. Tidak ada perubahan data.'
            : 'Rebuild impact purchase selesai diproses.';

        return [
            'ok' => true,
            'message' => $message,
            'data' => [
                'scope' => $scope,
                'dry_run' => $dryRun,
                'summary' => $summary,
                'rows' => $resultRows,
            ],
        ];
    }

    public function repair_inventory_opening_history(array $payload, int $userId, string $sourceIp = ''): array
    {
        $stockScope = strtoupper(trim((string)($payload['stock_scope'] ?? 'WAREHOUSE')));
        if (!in_array($stockScope, ['WAREHOUSE', 'DIVISION'], true)) {
            $stockScope = 'WAREHOUSE';
        }

        $openingTable = $this->openingSnapshotTableForScope($stockScope);
        if (!$this->db->table_exists($openingTable)) {
            return [
                'ok' => false,
                'message' => 'Tabel opening snapshot tidak ditemukan: ' . $openingTable,
            ];
        }

        $monthFrom = $this->normalizeMonth((string)($payload['month_from'] ?? date('Y-m-01')));
        $itemIdFilter = (int)($payload['item_id'] ?? 0);
        $limit = (int)($payload['limit'] ?? 500);
        if ($limit <= 0 || $limit > 5000) {
            $limit = 500;
        }

        $this->db
            ->from($openingTable . ' s')
            ->where('s.snapshot_month >=', $monthFrom);
        if ($itemIdFilter > 0) {
            $this->db->where('s.item_id', $itemIdFilter);
        }
        if ($stockScope === 'DIVISION' && !empty($payload['division_id'])) {
            $this->db->where('s.division_id', (int)$payload['division_id']);
        }

        $rows = $this->db
            ->order_by('s.snapshot_month', 'ASC')
            ->order_by('s.item_id', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();

        if (empty($rows)) {
            return [
                'ok' => true,
                'message' => 'Tidak ada opening snapshot yang perlu direpair.',
                'data' => ['keys_rebuilt' => 0, 'rows_scanned' => 0],
            ];
        }

        $groups = [];
        $this->db->trans_begin();
        foreach ($rows as $row) {
            $canonicalProfileKey = $this->resolveCatalogProfileKeyByIdentity(
                (int)($row['item_id'] ?? 0),
                $this->nullableInt($row['material_id'] ?? null),
                (int)($row['buy_uom_id'] ?? 0),
                (int)($row['content_uom_id'] ?? 0),
                $this->nullableString($row['profile_name'] ?? null),
                $this->nullableString($row['profile_brand'] ?? null),
                $this->nullableString($row['profile_description'] ?? null),
                $this->normalizeDate((string)($row['profile_expired_date'] ?? '')),
                round((float)($row['profile_content_per_buy'] ?? 1), 6)
            );
            if ($canonicalProfileKey === null) {
                $canonicalProfileKey = $this->resolveExistingOpeningProfileKey(
                    $stockScope,
                    $stockScope === 'DIVISION' ? $this->nullableInt($row['division_id'] ?? null) : null,
                    $stockScope === 'DIVISION' ? (string)($row['destination_type'] ?? 'OTHER') : null,
                    (int)($row['item_id'] ?? 0),
                    $this->nullableInt($row['material_id'] ?? null),
                    (int)($row['buy_uom_id'] ?? 0),
                    (int)($row['content_uom_id'] ?? 0),
                    $this->nullableString($row['profile_name'] ?? null),
                    $this->nullableString($row['profile_brand'] ?? null),
                    $this->nullableString($row['profile_description'] ?? null),
                    $this->normalizeDate((string)($row['profile_expired_date'] ?? '')),
                    round((float)($row['profile_content_per_buy'] ?? 1), 6)
                );
            }

            $currentProfileKey = trim((string)($row['profile_key'] ?? ''));
            $effectiveProfileKey = $canonicalProfileKey !== null && $canonicalProfileKey !== ''
                ? $canonicalProfileKey
                : $currentProfileKey;
            if ($effectiveProfileKey !== '' && $currentProfileKey !== $effectiveProfileKey) {
                $this->db->where('id', (int)$row['id'])->update($openingTable, ['profile_key' => $effectiveProfileKey]);
                $row['profile_key'] = $effectiveProfileKey;
            }

            $groupKeyParts = [
                $stockScope,
                $stockScope === 'DIVISION' ? (int)($row['division_id'] ?? 0) : 0,
                $stockScope === 'DIVISION' ? strtoupper((string)($row['destination_type'] ?? 'OTHER')) : 'WAREHOUSE',
                strtoupper((string)($row['stock_domain'] ?? 'ITEM')),
                (int)($row['item_id'] ?? 0),
                (int)($row['material_id'] ?? 0),
                (int)($row['buy_uom_id'] ?? 0),
                (int)($row['content_uom_id'] ?? 0),
                (string)($row['profile_key'] ?? ''),
            ];
            $groupKey = implode('|', $groupKeyParts);
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'start_date' => (string)($row['snapshot_month'] ?? $monthFrom),
                    'identity' => [
                        'division_id' => $stockScope === 'DIVISION' ? $this->nullableInt($row['division_id'] ?? null) : null,
                        'destination_type' => $stockScope === 'DIVISION' ? strtoupper((string)($row['destination_type'] ?? 'OTHER')) : null,
                        'stock_domain' => strtoupper((string)($row['stock_domain'] ?? 'ITEM')),
                        'item_id' => (int)($row['item_id'] ?? 0),
                        'material_id' => $this->nullableInt($row['material_id'] ?? null),
                        'buy_uom_id' => (int)($row['buy_uom_id'] ?? 0),
                        'content_uom_id' => (int)($row['content_uom_id'] ?? 0),
                        'profile_key' => (string)($row['profile_key'] ?? ''),
                        'profile_name' => $this->nullableString($row['profile_name'] ?? null),
                        'profile_brand' => $this->nullableString($row['profile_brand'] ?? null),
                        'profile_description' => $this->nullableString($row['profile_description'] ?? null),
                        'profile_expired_date' => $this->normalizeDate((string)($row['profile_expired_date'] ?? '')),
                        'profile_content_per_buy' => round((float)($row['profile_content_per_buy'] ?? 1), 6),
                        'profile_buy_uom_code' => $this->nullableString($row['profile_buy_uom_code'] ?? null),
                        'profile_content_uom_code' => $this->nullableString($row['profile_content_uom_code'] ?? null),
                    ],
                ];
            } elseif ((string)($row['snapshot_month'] ?? $monthFrom) < $groups[$groupKey]['start_date']) {
                $groups[$groupKey]['start_date'] = (string)$row['snapshot_month'];
            }
        }

        foreach ($groups as $group) {
            $identity = $group['identity'];
            $this->purgeOpeningMovementEntries($stockScope, $openingTable, $group['start_date'], $identity);
            $rebuild = $this->rebuild_inventory_history_from_opening($stockScope, $group['start_date'], $identity);
            if (!($rebuild['ok'] ?? false)) {
                $this->db->trans_rollback();
                return $rebuild;
            }
        }

        if ($this->db->table_exists('aud_transaction_log')) {
            $this->db->insert('aud_transaction_log', [
                'module_code' => 'INVENTORY',
                'action_code' => $stockScope === 'DIVISION' ? 'DIVISION_OPENING_REPAIR' : 'WAREHOUSE_OPENING_REPAIR',
                'entity_table' => $openingTable,
                'entity_id' => null,
                'transaction_no' => null,
                'actor_user_id' => $userId > 0 ? $userId : null,
                'source_ip' => $sourceIp !== '' ? $sourceIp : null,
                'after_payload' => json_encode([
                    'stock_scope' => $stockScope,
                    'month_from' => $monthFrom,
                    'item_id' => $itemIdFilter,
                    'rows_scanned' => count($rows),
                    'keys_rebuilt' => count($groups),
                ]),
                'notes' => 'Repair histori opening stok ' . strtolower($stockScope),
            ]);
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => 'Gagal repair histori opening.',
            ];
        }

        $this->db->trans_commit();

        return [
            'ok' => true,
            'message' => 'Repair histori opening selesai.',
            'data' => [
                'rows_scanned' => count($rows),
                'keys_rebuilt' => count($groups),
            ],
        ];
    }

    public function rebuild_inventory_history_for_identity(string $stockScope, string $startDate, array $identity): array
    {
        $stockScope = strtoupper(trim($stockScope));
        if (!in_array($stockScope, ['WAREHOUSE', 'DIVISION'], true)) {
            return [
                'ok' => false,
                'message' => 'stockScope rebuild tidak valid.',
            ];
        }

        $normalizedStartDate = $this->normalizeDate($startDate);
        if ($normalizedStartDate === null) {
            return [
                'ok' => false,
                'message' => 'Tanggal start rebuild tidak valid.',
            ];
        }

        $rebuild = $this->rebuild_inventory_history_from_opening($stockScope, $normalizedStartDate, $identity);
        if (!($rebuild['ok'] ?? false)) {
            return $rebuild;
        }

        $monthlyTable = $stockScope === 'DIVISION' ? 'inv_division_monthly_stock' : 'inv_warehouse_monthly_stock';
        if (!$this->db->table_exists($monthlyTable)) {
            return $rebuild;
        }

        $identityKey = $this->buildInventoryMonthlyIdentityKey($identity);
        $startMonth = date('Y-m-01', strtotime($normalizedStartDate));

        $this->db
            ->select('month_key AS movement_date, profile_name, closing_qty_content', false)
            ->from($monthlyTable)
            ->where('month_key >=', $startMonth)
            ->where('closing_qty_content <', 0);
        if ($stockScope === 'DIVISION') {
            $this->db->where('division_id', $identity['division_id'] ?? null);
            if ($this->db->field_exists('destination_type', $monthlyTable)) {
                $this->db->where('destination_type', $identity['destination_type'] ?? 'OTHER');
            }
        }
        $this->db->where('identity_key', $identityKey);
        $negativeRows = $this->db
            ->order_by('month_key', 'ASC')
            ->limit(5)
            ->get()
            ->result_array();

        if (!empty($negativeRows)) {
            $samples = [];
            foreach ($negativeRows as $row) {
                $samples[] = trim((string)($row['movement_date'] ?? ''))
                    . ' | '
                    . (string)($row['profile_name'] ?? '-')
                    . ' | closing=' . number_format((float)($row['closing_qty_content'] ?? 0), 4, '.', '');
            }

            return [
                'ok' => false,
                'message' => 'Rollback membuat histori stok minus.',
                'data' => [
                    'negative_samples' => $samples,
                ],
            ];
        }

        return $rebuild;
    }

    public function reclassify_item_material_by_profile_key(array $payload, int $userId, string $sourceIp = ''): array
    {
        if (!$this->db->table_exists('mst_purchase_catalog')) {
            return [
                'ok' => false,
                'message' => 'Tabel mst_purchase_catalog belum tersedia.',
            ];
        }
        if (!$this->db->field_exists('profile_key', 'mst_purchase_catalog') || !$this->db->field_exists('line_kind', 'mst_purchase_catalog')) {
            return [
                'ok' => false,
                'message' => 'Kolom profile_key/line_kind di mst_purchase_catalog belum tersedia.',
            ];
        }

        $dryRun = !empty($payload['dry_run']);
        $limit = (int)($payload['limit'] ?? 200);
        if ($limit <= 0 || $limit > 2000) {
            $limit = 200;
        }

        $profileKeyFilter = trim((string)($payload['profile_key'] ?? ''));
        $q = trim((string)($payload['q'] ?? ''));
        $lineKind = strtoupper(trim((string)($payload['line_kind'] ?? 'ALL')));
        if (!in_array($lineKind, ['ALL', 'ITEM', 'MATERIAL'], true)) {
            $lineKind = 'ALL';
        }

        $targetTables = $this->reclassify_target_tables();
        if (empty($targetTables)) {
            return [
                'ok' => false,
                'message' => 'Tidak ada tabel snapshot inventory yang bisa direclassify pada database ini.',
            ];
        }

        $rawProfiles = [];
        $seenKeys = [];
        $candidateRowsByKey = [];

        foreach ($targetTables as $cfg) {
            $table = (string)$cfg['table'];
            if ($table === '') {
                continue;
            }

            $this->db
                ->select('profile_key, "ITEM" AS stock_domain, item_id, material_id, profile_name, profile_brand', false)
                ->from($table)
                ->where('profile_key IS NOT NULL', null, false)
                ->where("TRIM(profile_key) <> ''", null, false);

            if ($profileKeyFilter !== '') {
                $this->db->where('profile_key', $profileKeyFilter);
            } elseif ($q !== '') {
                $this->db->group_start()
                    ->like('profile_key', $q)
                    ->or_like('profile_name', $q)
                    ->or_like('profile_brand', $q)
                    ->group_end();
            }

            $rows = $this->db->order_by('id', 'DESC')->limit($limit)->get()->result_array();
            foreach ($rows as $r) {
                $key = trim((string)($r['profile_key'] ?? ''));
                if ($key === '') {
                    continue;
                }
                if (!isset($candidateRowsByKey[$key])) {
                    $candidateRowsByKey[$key] = [];
                }
                $candidateRowsByKey[$key][] = $r;
            }
        }

        if (empty($candidateRowsByKey)) {
            return [
                'ok' => true,
                'message' => 'Tidak ada data snapshot dengan profile_key yang cocok filter.',
                'data' => [
                    'dry_run' => $dryRun,
                    'summary' => [
                        'profiles_scanned' => 0,
                        'profiles_affected' => 0,
                        'tables_affected' => 0,
                        'rows_before' => 0,
                        'rows_after' => 0,
                        'rows_merged' => 0,
                        'rows_changed' => 0,
                    ],
                    'table_summary' => [],
                    'profiles' => [],
                ],
            ];
        }

        foreach ($candidateRowsByKey as $candidateKey => $rows) {
            if (count($rawProfiles) >= $limit) {
                break;
            }
            if (isset($seenKeys[$candidateKey])) {
                continue;
            }
            $seenKeys[$candidateKey] = true;

            $catalog = $this->db
                ->select('profile_key, line_kind, item_id, material_id, catalog_name, brand_name')
                ->from('mst_purchase_catalog')
                ->where('profile_key', $candidateKey)
                ->order_by('id', 'DESC')
                ->limit(1)
                ->get()
                ->row_array();

            $lineKindResolved = '';
            $itemIdResolved = 0;
            $materialIdResolved = 0;
            $catalogName = '';
            $brandName = '';

            if (!empty($catalog)) {
                $lineKindResolved = strtoupper(trim((string)($catalog['line_kind'] ?? '')));
                $itemIdResolved = (int)($catalog['item_id'] ?? 0);
                $materialIdResolved = (int)($catalog['material_id'] ?? 0);
                $catalogName = (string)($catalog['catalog_name'] ?? '');
                $brandName = (string)($catalog['brand_name'] ?? '');
            }

            if ($itemIdResolved <= 0 || $materialIdResolved <= 0 || $lineKindResolved === '') {
                foreach ($rows as $rr) {
                    if ($itemIdResolved <= 0) {
                        $itemIdResolved = (int)($rr['item_id'] ?? 0);
                    }
                    if ($materialIdResolved <= 0) {
                        $materialIdResolved = (int)($rr['material_id'] ?? 0);
                    }
                    if ($catalogName === '') {
                        $catalogName = trim((string)($rr['profile_name'] ?? ''));
                    }
                    if ($brandName === '') {
                        $brandName = trim((string)($rr['profile_brand'] ?? ''));
                    }
                }
            }

            if ($itemIdResolved > 0 && $this->db->table_exists('mst_item')) {
                $item = $this->db
                    ->select('material_id')
                    ->from('mst_item')
                    ->where('id', $itemIdResolved)
                    ->limit(1)
                    ->get()
                    ->row_array();
                $itemMaterialId = (int)($item['material_id'] ?? 0);
                if ($materialIdResolved <= 0 && $itemMaterialId > 0) {
                    $materialIdResolved = $itemMaterialId;
                }
            }

            if ($itemIdResolved > 0) {
                $lineKindResolved = 'ITEM';
            } elseif ($lineKindResolved === '') {
                $lineKindResolved = $materialIdResolved > 0 ? 'MATERIAL' : 'ITEM';
            }
            if (!in_array($lineKindResolved, ['ITEM', 'MATERIAL'], true)) {
                $lineKindResolved = $itemIdResolved > 0 ? 'ITEM' : ($materialIdResolved > 0 ? 'MATERIAL' : 'ITEM');
            }

            if ($lineKind !== 'ALL' && $lineKindResolved !== $lineKind) {
                continue;
            }

            $rawProfiles[] = [
                'profile_key' => $candidateKey,
                'line_kind' => $lineKindResolved,
                'item_id' => $itemIdResolved > 0 ? $itemIdResolved : null,
                'material_id' => $materialIdResolved > 0 ? $materialIdResolved : null,
                'catalog_name' => $catalogName !== '' ? $catalogName : '(inferred from snapshot)',
                'brand_name' => $brandName,
            ];
        }

        $profileRows = $rawProfiles;

        if (empty($profileRows)) {
            return [
                'ok' => true,
                'message' => 'Tidak ada profile_key yang cocok dengan filter.',
                'data' => [
                    'dry_run' => $dryRun,
                    'summary' => [
                        'profiles_scanned' => 0,
                        'profiles_affected' => 0,
                        'tables_affected' => 0,
                        'rows_before' => 0,
                        'rows_after' => 0,
                        'rows_merged' => 0,
                        'rows_changed' => 0,
                    ],
                    'table_summary' => [],
                    'profiles' => [],
                ],
            ];
        }

        $tableSummary = [];
        foreach ($targetTables as $cfg) {
            $tableSummary[$cfg['table']] = [
                'table' => $cfg['table'],
                'profiles' => 0,
                'rows_before' => 0,
                'rows_after' => 0,
                'rows_merged' => 0,
                'rows_changed' => 0,
            ];
        }

        $summary = [
            'profiles_scanned' => count($profileRows),
            'profiles_affected' => 0,
            'tables_affected' => 0,
            'rows_before' => 0,
            'rows_after' => 0,
            'rows_merged' => 0,
            'rows_changed' => 0,
        ];

        $applyQueue = [];
        $profileResult = [];
        foreach ($profileRows as $profile) {
            $profileKey = trim((string)($profile['profile_key'] ?? ''));
            if ($profileKey === '') {
                continue;
            }

            $targetDomain = (int)($profile['item_id'] ?? 0) > 0
                ? 'ITEM'
                : (strtoupper(trim((string)($profile['line_kind'] ?? 'ITEM'))) === 'MATERIAL' ? 'MATERIAL' : 'ITEM');
            $targetItemId = (int)($profile['item_id'] ?? 0);
            $targetMaterialId = (int)($profile['material_id'] ?? 0);

            if ($targetDomain === 'MATERIAL' && $targetMaterialId <= 0 && $targetItemId > 0 && $this->db->table_exists('mst_item')) {
                $item = $this->db->select('material_id')->from('mst_item')->where('id', $targetItemId)->limit(1)->get()->row_array();
                $targetMaterialId = (int)($item['material_id'] ?? 0);
            }
            if ($targetDomain !== 'MATERIAL') {
                $targetMaterialId = 0;
            }

            $profileTouched = false;
            $profileTableDetails = [];

            foreach ($targetTables as $cfg) {
                $table = $cfg['table'];
                $rows = $this->db
                    ->from($table)
                    ->where('profile_key', $profileKey)
                    ->order_by('id', 'DESC')
                    ->get()
                    ->result_array();

                if (empty($rows)) {
                    continue;
                }

                $transformedRows = [];
                $changedCount = 0;
                foreach ($rows as $row) {
                    $oldDomain = strtoupper(trim((string)($row['stock_domain'] ?? 'ITEM')));
                    $newRow = $row;

                    if ($oldDomain !== $targetDomain) {
                        $newRow['stock_domain'] = $targetDomain;
                        $changedCount++;
                    }

                    $existingMaterialId = (int)($row['material_id'] ?? 0);
                    if ($targetDomain === 'MATERIAL') {
                        $resolvedMaterialId = $targetMaterialId > 0 ? $targetMaterialId : $existingMaterialId;
                        if ($resolvedMaterialId > 0 && $resolvedMaterialId !== $existingMaterialId) {
                            $newRow['material_id'] = $resolvedMaterialId;
                            $changedCount++;
                        }
                    } else {
                        if (!empty($row['material_id'])) {
                            $newRow['material_id'] = null;
                            $changedCount++;
                        }
                    }

                    $transformedRows[] = $newRow;
                }

                $mergedRows = $this->reclassify_merge_rows($table, $transformedRows, $cfg['unique']);
                $beforeCount = count($rows);
                $afterCount = count($mergedRows);
                $mergeCount = max(0, $beforeCount - $afterCount);

                if ($changedCount <= 0 && $mergeCount <= 0) {
                    continue;
                }

                $profileTouched = true;
                $tableSummary[$table]['profiles']++;
                $tableSummary[$table]['rows_before'] += $beforeCount;
                $tableSummary[$table]['rows_after'] += $afterCount;
                $tableSummary[$table]['rows_merged'] += $mergeCount;
                $tableSummary[$table]['rows_changed'] += $changedCount;

                $summary['rows_before'] += $beforeCount;
                $summary['rows_after'] += $afterCount;
                $summary['rows_merged'] += $mergeCount;
                $summary['rows_changed'] += $changedCount;

                $profileTableDetails[] = [
                    'table' => $table,
                    'rows_before' => $beforeCount,
                    'rows_after' => $afterCount,
                    'rows_merged' => $mergeCount,
                    'rows_changed' => $changedCount,
                ];

                if (!$dryRun) {
                    $ids = [];
                    foreach ($rows as $row) {
                        $id = (int)($row['id'] ?? 0);
                        if ($id > 0) {
                            $ids[] = $id;
                        }
                    }
                    if (!empty($ids)) {
                        $applyQueue[] = [
                            'table' => $table,
                            'ids' => array_values(array_unique($ids)),
                            'rows' => $mergedRows,
                        ];
                    }
                }
            }

            if ($profileTouched) {
                $summary['profiles_affected']++;
                $profileResult[] = [
                    'profile_key' => $profileKey,
                    'line_kind' => strtoupper(trim((string)($profile['line_kind'] ?? 'ITEM'))),
                    'target_domain' => $targetDomain,
                    'target_item_id' => $targetItemId > 0 ? $targetItemId : null,
                    'target_material_id' => $targetMaterialId > 0 ? $targetMaterialId : null,
                    'catalog_name' => (string)($profile['catalog_name'] ?? ''),
                    'brand_name' => (string)($profile['brand_name'] ?? ''),
                    'tables' => $profileTableDetails,
                ];
            }
        }

        $summary['tables_affected'] = 0;
        foreach ($tableSummary as $table => $stat) {
            if ($stat['rows_before'] > 0) {
                $summary['tables_affected']++;
            }
        }

        if (!$dryRun && !empty($applyQueue)) {
            $this->db->trans_begin();
            try {
                foreach ($applyQueue as $job) {
                    $table = (string)$job['table'];
                    $ids = (array)$job['ids'];
                    $rows = (array)$job['rows'];

                    if (empty($ids)) {
                        continue;
                    }

                    $this->db->where_in('id', $ids)->delete($table);

                    foreach ($rows as $row) {
                        if (!is_array($row) || empty($row)) {
                            continue;
                        }

                        unset($row['id'], $row['__source_ids']);
                        $insert = [];
                        $columns = $this->listTableFields($table);
                        foreach ($row as $col => $val) {
                            if (!isset($columns[$col])) {
                                continue;
                            }
                            $insert[$col] = $val;
                        }
                        if (!empty($insert)) {
                            $this->db->insert($table, $insert);
                        }
                    }
                }

                if ($this->db->trans_status() === false) {
                    throw new Exception('DB transaction gagal.');
                }

                if ($this->db->table_exists('aud_transaction_log')) {
                    $this->db->insert('aud_transaction_log', [
                        'module_code' => 'PURCHASE',
                        'action_code' => 'INV_RECLASSIFY_DOMAIN',
                        'entity_table' => 'mst_purchase_catalog',
                        'entity_id' => null,
                        'transaction_no' => null,
                        'actor_user_id' => $userId > 0 ? $userId : null,
                        'source_ip' => $sourceIp !== '' ? $sourceIp : null,
                        'after_payload' => json_encode([
                            'filter' => [
                                'profile_key' => $profileKeyFilter,
                                'q' => $q,
                                'line_kind' => $lineKind,
                                'limit' => $limit,
                            ],
                            'summary' => $summary,
                        ]),
                        'notes' => 'Reclassify ITEM/MATERIAL by profile_key (snapshot cleanup)',
                    ]);
                }

                $this->db->trans_commit();
            } catch (Throwable $e) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => 'Gagal apply reclassify: ' . $e->getMessage(),
                ];
            }
        }

        return [
            'ok' => true,
            'message' => $dryRun
                ? 'Dry-run selesai. Tidak ada perubahan data.'
                : 'Reclassify ITEM/MATERIAL by profile_key berhasil diterapkan.',
            'data' => [
                'dry_run' => $dryRun,
                'summary' => $summary,
                'table_summary' => array_values($tableSummary),
                'profiles' => $profileResult,
            ],
        ];
    }

    public function production_domain_root_cause_audit(array $filters = []): array
    {
        if (
            !$this->db->table_exists('mst_purchase_catalog') ||
            !$this->db->table_exists('mst_item') ||
            !$this->db->field_exists('line_kind', 'mst_purchase_catalog') ||
            !$this->db->field_exists('material_id', 'mst_item')
        ) {
            return [
                'ok' => false,
                'message' => 'Schema purchase/item belum lengkap untuk audit akar masalah domain produksi.',
            ];
        }

        $q = trim((string)($filters['q'] ?? ''));
        $limit = (int)($filters['limit'] ?? 25);
        if ($limit <= 0 || $limit > 200) {
            $limit = 25;
        }
        $activeOnly = array_key_exists('active_only', $filters) ? !empty($filters['active_only']) : true;

        $hasPoLine = $this->db->table_exists('pur_purchase_order_line')
            && $this->db->field_exists('profile_key', 'pur_purchase_order_line')
            && $this->db->field_exists('line_kind', 'pur_purchase_order_line');
        $hasPoUsage = $hasPoLine && $this->db->field_exists('usage_purpose', 'pur_purchase_order_line');
        $hasReceiptLine = $this->db->table_exists('pur_purchase_receipt_line')
            && $this->db->field_exists('profile_key', 'pur_purchase_receipt_line')
            && $this->db->field_exists('line_kind', 'pur_purchase_receipt_line');
        $hasReceiptUsage = $hasReceiptLine && $this->db->field_exists('usage_purpose', 'pur_purchase_receipt_line');
        $hasStoreRequestLine = $this->db->table_exists('pur_store_request_line')
            && $this->db->field_exists('profile_key', 'pur_store_request_line')
            && $this->db->field_exists('line_kind', 'pur_store_request_line');
        $hasStoreRequestUsage = $hasStoreRequestLine && $this->db->field_exists('usage_purpose', 'pur_store_request_line');
        $hasDivisionRequestLine = $this->db->table_exists('pur_division_request_line')
            && $this->db->field_exists('profile_key', 'pur_division_request_line')
            && $this->db->field_exists('line_kind', 'pur_division_request_line');
        $hasDivisionRequestUsage = $hasDivisionRequestLine && $this->db->field_exists('usage_purpose', 'pur_division_request_line');
        $hasFulfillmentLine = $this->db->table_exists('pur_store_request_fulfillment_line')
            && $this->db->field_exists('profile_key', 'pur_store_request_fulfillment_line')
            && $this->db->field_exists('line_kind', 'pur_store_request_fulfillment_line');
        $hasFulfillmentUsage = $hasFulfillmentLine && $this->db->field_exists('usage_purpose', 'pur_store_request_fulfillment_line');
        $hasMovementLog = $this->db->table_exists('inv_stock_movement_log')
            && $this->db->field_exists('profile_key', 'inv_stock_movement_log')
            && $this->db->field_exists('item_id', 'inv_stock_movement_log')
            && $this->db->field_exists('material_id', 'inv_stock_movement_log');
        $hasDivisionMonthly = $this->db->table_exists('inv_division_monthly_stock')
            && $this->db->field_exists('profile_key', 'inv_division_monthly_stock')
            && $this->db->field_exists('stock_domain', 'inv_division_monthly_stock');
        $hasWarehouseMonthly = $this->db->table_exists('inv_warehouse_monthly_stock')
            && $this->db->field_exists('profile_key', 'inv_warehouse_monthly_stock')
            && $this->db->field_exists('stock_domain', 'inv_warehouse_monthly_stock');
        $subqueries = [
            'po_item_rows' => $hasPoUsage
                ? "(SELECT COUNT(*) FROM pur_purchase_order_line pol WHERE pol.profile_key = c.profile_key AND COALESCE(pol.usage_purpose,'BAHAN_BAKU') = 'BAHAN_BAKU' AND UPPER(COALESCE(pol.line_kind,'ITEM')) = 'MATERIAL')"
                : '0',
            'receipt_item_rows' => $hasReceiptUsage
                ? "(SELECT COUNT(*) FROM pur_purchase_receipt_line rl WHERE rl.profile_key = c.profile_key AND COALESCE(rl.usage_purpose,'BAHAN_BAKU') = 'BAHAN_BAKU' AND UPPER(COALESCE(rl.line_kind,'ITEM')) = 'MATERIAL')"
                : '0',
            'sr_item_rows' => $hasStoreRequestUsage
                ? "(SELECT COUNT(*) FROM pur_store_request_line srl WHERE srl.profile_key = c.profile_key AND COALESCE(srl.usage_purpose,'BAHAN_BAKU') = 'BAHAN_BAKU' AND UPPER(COALESCE(srl.line_kind,'ITEM')) = 'MATERIAL')"
                : '0',
            'division_request_item_rows' => $hasDivisionRequestUsage
                ? "(SELECT COUNT(*) FROM pur_division_request_line drl WHERE drl.profile_key = c.profile_key AND COALESCE(drl.usage_purpose,'BAHAN_BAKU') = 'BAHAN_BAKU' AND UPPER(COALESCE(drl.line_kind,'ITEM')) = 'MATERIAL')"
                : '0',
            'fulfillment_item_rows' => $hasFulfillmentUsage
                ? "(SELECT COUNT(*) FROM pur_store_request_fulfillment_line fl WHERE fl.profile_key = c.profile_key AND COALESCE(fl.usage_purpose,'BAHAN_BAKU') = 'BAHAN_BAKU' AND UPPER(COALESCE(fl.line_kind,'ITEM')) = 'MATERIAL')"
                : '0',
            'movement_rows' => $hasMovementLog
                ? "(SELECT COUNT(*) FROM inv_stock_movement_log ml WHERE ml.profile_key = c.profile_key)"
                : '0',
            'movement_wrong_material_rows' => $hasMovementLog
                ? "(SELECT COUNT(*) FROM inv_stock_movement_log ml WHERE ml.profile_key = c.profile_key AND COALESCE(ml.item_id,0) = COALESCE(c.item_id,0) AND COALESCE(ml.material_id,0) <> COALESCE(i.material_id,0))"
                : '0',
            'division_monthly_item_rows' => $hasDivisionMonthly
                ? "(SELECT COUNT(*) FROM inv_division_monthly_stock ds WHERE ds.profile_key = c.profile_key AND UPPER(COALESCE(ds.stock_domain,'ITEM')) = 'ITEM')"
                : '0',
            'division_monthly_material_rows' => $hasDivisionMonthly
                ? "(SELECT COUNT(*) FROM inv_division_monthly_stock ds WHERE ds.profile_key = c.profile_key AND UPPER(COALESCE(ds.stock_domain,'ITEM')) = 'MATERIAL')"
                : '0',
            'warehouse_monthly_item_rows' => $hasWarehouseMonthly
                ? "(SELECT COUNT(*) FROM inv_warehouse_monthly_stock ws WHERE ws.profile_key = c.profile_key AND UPPER(COALESCE(ws.stock_domain,'ITEM')) = 'ITEM')"
                : '0',
            'warehouse_monthly_material_rows' => $hasWarehouseMonthly
                ? "(SELECT COUNT(*) FROM inv_warehouse_monthly_stock ws WHERE ws.profile_key = c.profile_key AND UPPER(COALESCE(ws.stock_domain,'ITEM')) = 'MATERIAL')"
                : '0',
        ];

        $this->db
            ->from('mst_purchase_catalog c')
            ->join('mst_item i', 'i.id = c.item_id', 'inner')
            ->join('mst_material m', 'm.id = i.material_id', 'left')
            ->select('c.id AS catalog_id, c.profile_key, c.catalog_name, c.brand_name, c.line_kind AS catalog_line_kind, c.item_id, c.material_id AS catalog_material_id, COALESCE(c.is_active,1) AS is_active', false)
            ->select('i.item_name, i.material_id AS expected_material_id', false)
            ->select('m.material_code AS expected_material_code, m.material_name AS expected_material_name', false);

        foreach ($subqueries as $alias => $expr) {
            $this->db->select($expr . ' AS ' . $alias, false);
        }

        $this->db
            ->where("UPPER(COALESCE(c.line_kind,'ITEM')) = 'ITEM'", null, false)
            ->where('COALESCE(i.material_id,0) >', 0, false);

        if ($activeOnly) {
            $this->db->where('COALESCE(c.is_active,1) = 1', null, false);
        }

        if ($q !== '') {
            $this->db->group_start()
                ->like('c.profile_key', $q)
                ->or_like('c.catalog_name', $q)
                ->or_like('c.brand_name', $q)
                ->or_like('i.item_name', $q)
                ->or_like('m.material_name', $q)
            ->group_end();
        }

        $rows = $this->db
            ->order_by('c.catalog_name', 'ASC')
            ->order_by('c.brand_name', 'ASC')
            ->order_by('c.id', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();

        $filteredRows = [];
        foreach ($rows as $row) {
            $transactionLegacyCount =
                (int)($row['po_item_rows'] ?? 0)
                + (int)($row['receipt_item_rows'] ?? 0)
                + (int)($row['sr_item_rows'] ?? 0)
                + (int)($row['division_request_item_rows'] ?? 0)
                + (int)($row['fulfillment_item_rows'] ?? 0);
            $row['has_split_snapshot'] =
                ((int)($row['division_monthly_item_rows'] ?? 0) > 0 && (int)($row['division_monthly_material_rows'] ?? 0) > 0)
                || ((int)($row['warehouse_monthly_item_rows'] ?? 0) > 0 && (int)($row['warehouse_monthly_material_rows'] ?? 0) > 0);
            $row['impact_count'] = $transactionLegacyCount
                + (int)($row['movement_wrong_material_rows'] ?? 0)
                + (int)($row['division_monthly_material_rows'] ?? 0)
                + (int)($row['warehouse_monthly_material_rows'] ?? 0);
            $row['has_transaction_drift'] = $transactionLegacyCount > 0;
            $row['has_snapshot_legacy'] =
                (int)($row['division_monthly_material_rows'] ?? 0) > 0
                || (int)($row['warehouse_monthly_material_rows'] ?? 0) > 0;
            $row['has_movement_drift'] = (int)($row['movement_wrong_material_rows'] ?? 0) > 0;

            if (!$row['has_transaction_drift'] && !$row['has_snapshot_legacy'] && !$row['has_movement_drift'] && empty($row['has_split_snapshot'])) {
                continue;
            }
            $filteredRows[] = $row;
        }

        $summary = [
            'total_wrong_active_profiles' => count($filteredRows),
            'profiles_with_transaction_drift' => 0,
            'profiles_with_snapshot_split' => 0,
            'total_po_item_rows' => 0,
            'total_receipt_item_rows' => 0,
            'total_movement_wrong_rows' => 0,
            'total_division_item_rows' => 0,
            'total_warehouse_item_rows' => 0,
        ];

        foreach ($filteredRows as $row) {
            if (!empty($row['has_transaction_drift'])) {
                $summary['profiles_with_transaction_drift']++;
            }
            if (!empty($row['has_split_snapshot'])) {
                $summary['profiles_with_snapshot_split']++;
            }

            $summary['total_po_item_rows'] += (int)($row['po_item_rows'] ?? 0);
            $summary['total_receipt_item_rows'] += (int)($row['receipt_item_rows'] ?? 0);
            $summary['total_movement_wrong_rows'] += (int)($row['movement_wrong_material_rows'] ?? 0);
            $summary['total_division_item_rows'] += (int)($row['division_monthly_item_rows'] ?? 0) + (int)($row['division_daily_item_rows'] ?? 0);
            $summary['total_warehouse_item_rows'] += (int)($row['warehouse_monthly_item_rows'] ?? 0) + (int)($row['warehouse_daily_item_rows'] ?? 0);
        }

        return [
            'ok' => true,
            'message' => empty($filteredRows)
                ? 'Belum ditemukan profile aktif salah domain pada jalur Persediaan Produksi.'
                : 'Audit akar masalah domain produksi selesai.',
            'data' => [
                'summary' => $summary,
                'rows' => $filteredRows,
            ],
        ];
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
            $lineQb = $this->db
                ->select('id, line_no, line_kind, item_id, material_id, buy_uom_id, content_uom_id')
                ->select('brand_name, line_description, qty_buy, content_per_buy, qty_content, conversion_factor_to_content')
                ->select('unit_price, discount_percent, tax_percent, line_subtotal, profile_key, notes')
                ->select('snapshot_item_name, snapshot_material_name, snapshot_brand_name, snapshot_line_description')
                ->select('snapshot_buy_uom_code, snapshot_content_uom_code')
                ->from('pur_purchase_order_line')
                ->where('purchase_order_id', $purchaseOrderId)
                ->order_by('line_no', 'ASC');

            if ($this->db->field_exists('expired_date', 'pur_purchase_order_line')) {
                $lineQb->select('expired_date');
            }
            if ($this->db->field_exists('snapshot_expired_date', 'pur_purchase_order_line')) {
                $lineQb->select('snapshot_expired_date');
            }
            if ($this->hasPurchaseOrderUsagePurposeColumn()) {
                $lineQb->select('usage_purpose');
            }

            $lines = $lineQb->get()->result_array();
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
        $receiptRows = [];
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

            if ($this->db->table_exists('pur_purchase_receipt_line')) {
                $receiptDivisionNameSelect = 'NULL AS destination_division_name';
                if ($this->db->field_exists('division_name', 'mst_operational_division')) {
                    $receiptDivisionNameSelect = 'd.division_name AS destination_division_name';
                } elseif ($this->db->field_exists('name', 'mst_operational_division')) {
                    $receiptDivisionNameSelect = 'd.name AS destination_division_name';
                }

                $receiptRows = $this->db
                    ->select('r.id, r.receipt_no, r.receipt_date, r.destination_type, r.destination_division_id, r.status, r.notes, r.posted_at, r.created_at')
                    ->select($receiptDivisionNameSelect, false)
                    ->select('COUNT(rl.id) AS line_count', false)
                    ->select('COALESCE(SUM(rl.qty_buy_received), 0) AS qty_buy_total', false)
                    ->select('COALESCE(SUM(rl.qty_content_received), 0) AS qty_content_total', false)
                    ->from('pur_purchase_receipt r')
                    ->join('pur_purchase_receipt_line rl', 'rl.purchase_receipt_id = r.id', 'left')
                    ->join('mst_operational_division d', 'd.id = r.destination_division_id', 'left')
                    ->where('r.purchase_order_id', $purchaseOrderId)
                    ->group_by('r.id')
                    ->order_by('r.receipt_date', 'ASC')
                    ->order_by('r.id', 'ASC')
                    ->get()
                    ->result_array();

                $receiptIds = array_values(array_filter(array_map(static function ($row) {
                    return (int)($row['id'] ?? 0);
                }, $receiptRows), static function ($id) {
                    return $id > 0;
                }));

                if (!empty($receiptIds)) {
                    $receiptLineRowsQb = $this->db
                        ->select('rl.id AS receipt_line_id, rl.purchase_receipt_id, rl.purchase_order_line_id, rl.line_kind, rl.item_id, rl.material_id, rl.qty_buy_received, rl.qty_content_received, rl.buy_uom_id, rl.content_uom_id, rl.brand_name, rl.line_description, rl.expired_date, rl.profile_key, rl.lot_id, rl.lot_no, rl.notes, rl.created_at')
                        ->select('pol.line_no AS po_line_no', false)
                        ->select('i.item_name, m.material_name, bu.code AS buy_uom_code, cu.code AS content_uom_code', false)
                        ->from('pur_purchase_receipt_line rl')
                        ->join('pur_purchase_order_line pol', 'pol.id = rl.purchase_order_line_id', 'left')
                        ->join('mst_item i', 'i.id = rl.item_id', 'left')
                        ->join('mst_material m', 'm.id = rl.material_id', 'left')
                        ->join('mst_uom bu', 'bu.id = rl.buy_uom_id', 'left')
                        ->join('mst_uom cu', 'cu.id = rl.content_uom_id', 'left')
                        ->where_in('rl.purchase_receipt_id', $receiptIds)
                        ->order_by('rl.purchase_receipt_id', 'ASC')
                        ->order_by('rl.id', 'ASC');
                    if ($this->hasPurchaseReceiptUsagePurposeColumn()) {
                        $receiptLineRowsQb->select('rl.usage_purpose');
                    }
                    $receiptLineRows = $receiptLineRowsQb->get()->result_array();

                    $receiptLineIds = array_values(array_filter(array_map(static function ($row) {
                        return (int)($row['receipt_line_id'] ?? 0);
                    }, $receiptLineRows), static function ($id) {
                        return $id > 0;
                    }));

                    $lotMap = [];
                    if ($this->db->table_exists('inv_material_fifo_lot') && !empty($receiptLineIds)) {
                        $lotRows = $this->db
                            ->select('id, receipt_id, receipt_line_id, lot_no, receipt_date, expiry_date, qty_in, qty_out, qty_balance, unit_cost, status')
                            ->from('inv_material_fifo_lot')
                            ->where_in('receipt_line_id', $receiptLineIds)
                            ->order_by('receipt_date', 'ASC')
                            ->order_by('id', 'ASC')
                            ->get()
                            ->result_array();
                        foreach ($lotRows as $lotRow) {
                            $lotMap[(int)($lotRow['receipt_line_id'] ?? 0)][] = $lotRow;
                        }
                    }

                    $receiptLineMap = [];
                    foreach ($receiptLineRows as $receiptLineRow) {
                        $receiptLineId = (int)($receiptLineRow['receipt_line_id'] ?? 0);
                        $receiptLineRow['lot_rows'] = $lotMap[$receiptLineId] ?? [];
                        $receiptLineMap[(int)($receiptLineRow['purchase_receipt_id'] ?? 0)][] = $receiptLineRow;
                    }

                    foreach ($receiptRows as &$receiptRow) {
                        $receiptRow['lines'] = $receiptLineMap[(int)($receiptRow['id'] ?? 0)] ?? [];
                    }
                    unset($receiptRow);
                }
            }
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
            'receipt_rows' => $receiptRows,
            'txn_rows' => $txnRows,
            'audit_rows' => $auditRows,
            'outstanding' => $outstanding,
        ];
    }

    private function reclassify_target_tables(): array
    {
        $targets = [
            [
                'table' => 'inv_warehouse_monthly_opname',
                'unique' => ['month_key', 'stock_domain', 'item_id', 'material_id', 'buy_uom_id', 'content_uom_id', 'profile_key'],
            ],
            [
                'table' => 'inv_division_monthly_opname',
                'unique' => ['month_key', 'division_id', 'destination_type', 'stock_domain', 'item_id', 'material_id', 'buy_uom_id', 'content_uom_id', 'profile_key'],
            ],
            [
                'table' => 'inv_warehouse_stock_opening_snapshot',
                'unique' => ['snapshot_month', 'stock_domain', 'item_id', 'material_id', 'buy_uom_id', 'content_uom_id', 'profile_key'],
            ],
            [
                'table' => 'inv_division_stock_opening_snapshot',
                'unique' => ['snapshot_month', 'division_id', 'destination_type', 'stock_domain', 'item_id', 'material_id', 'buy_uom_id', 'content_uom_id', 'profile_key'],
            ],
            [
                'table' => 'inv_stock_opening_snapshot',
                'unique' => ['snapshot_month', 'stock_scope', 'division_id', 'destination_type', 'stock_domain', 'item_id', 'material_id', 'buy_uom_id', 'content_uom_id', 'profile_key'],
            ],
        ];

        $result = [];
        foreach ($targets as $cfg) {
            $table = (string)($cfg['table'] ?? '');
            if ($table === '' || !$this->db->table_exists($table)) {
                continue;
            }
            if (!$this->db->field_exists('profile_key', $table) || !$this->db->field_exists('stock_domain', $table)) {
                continue;
            }
            $result[] = $cfg;
        }

        return $result;
    }

    private function reclassify_merge_rows(string $table, array $rows, array $uniqueColumns): array
    {
        if (empty($rows)) {
            return [];
        }

        $columns = $this->listTableFields($table);
        $fields = $this->db->field_data($table);
        $fieldTypes = [];
        foreach ($fields as $f) {
            $fieldTypes[(string)$f->name] = strtolower((string)$f->type);
        }

        $sumExclude = [
            'id' => true,
            'division_id' => true,
            'item_id' => true,
            'material_id' => true,
            'buy_uom_id' => true,
            'content_uom_id' => true,
            'created_by' => true,
            'generated_by' => true,
            'stock_domain' => true,
            'stock_scope' => true,
            'month_key' => true,
            'movement_date' => true,
            'snapshot_month' => true,
            'destination_type' => true,
            'profile_key' => true,
            'profile_name' => true,
            'profile_brand' => true,
            'profile_description' => true,
            'profile_expired_date' => true,
            'profile_content_per_buy' => true,
            'profile_buy_uom_code' => true,
            'profile_content_uom_code' => true,
            'source_type' => true,
            'notes' => true,
            'created_at' => true,
            'updated_at' => true,
            'generated_at' => true,
            'last_movement_at' => true,
            'rebuild_batch_no' => true,
            'avg_cost_per_content' => true,
            'opening_avg_cost_per_content' => true,
        ];

        $numericTypes = [
            'int' => true, 'tinyint' => true, 'smallint' => true, 'mediumint' => true, 'bigint' => true,
            'decimal' => true, 'float' => true, 'double' => true, 'real' => true, 'numeric' => true,
        ];
        $sumColumns = [];
        foreach ($fieldTypes as $col => $type) {
            if (!isset($columns[$col])) {
                continue;
            }
            if (isset($sumExclude[$col])) {
                continue;
            }
            if (!isset($numericTypes[$type])) {
                continue;
            }
            $sumColumns[$col] = true;
        }

        $groups = [];
        foreach ($rows as $row) {
            $groupTokens = [];
            foreach ($uniqueColumns as $col) {
                $groupTokens[] = $this->reclassify_key_token($row[$col] ?? null);
            }
            $groupKey = implode('|', $groupTokens);

            if (!isset($groups[$groupKey])) {
                $base = $row;
                $base['__source_ids'] = [];
                $id = (int)($row['id'] ?? 0);
                if ($id > 0) {
                    $base['__source_ids'][] = $id;
                }
                $groups[$groupKey] = $base;
                continue;
            }

            $dst = $groups[$groupKey];
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $dst['__source_ids'][] = $id;
            }

            foreach ($sumColumns as $col => $dummy) {
                $dst[$col] = (float)($dst[$col] ?? 0) + (float)($row[$col] ?? 0);
            }

            foreach (['profile_name', 'profile_brand', 'profile_description', 'profile_buy_uom_code', 'profile_content_uom_code', 'notes', 'source_type', 'rebuild_batch_no'] as $textCol) {
                if (!isset($columns[$textCol])) {
                    continue;
                }
                $left = trim((string)($dst[$textCol] ?? ''));
                $right = trim((string)($row[$textCol] ?? ''));
                if ($left === '' && $right !== '') {
                    $dst[$textCol] = $right;
                }
            }

            foreach (['profile_content_per_buy', 'avg_cost_per_content', 'opening_avg_cost_per_content'] as $numCol) {
                if (!isset($columns[$numCol])) {
                    continue;
                }
                $left = (float)($dst[$numCol] ?? 0);
                $right = (float)($row[$numCol] ?? 0);
                if ($left <= 0 && $right > 0) {
                    $dst[$numCol] = $right;
                }
            }

            if (isset($columns['profile_expired_date'])) {
                $left = trim((string)($dst['profile_expired_date'] ?? ''));
                $right = trim((string)($row['profile_expired_date'] ?? ''));
                if ($left === '' && $right !== '') {
                    $dst['profile_expired_date'] = $right;
                }
            }

            if (isset($columns['created_at'])) {
                $left = trim((string)($dst['created_at'] ?? ''));
                $right = trim((string)($row['created_at'] ?? ''));
                if ($left === '' || ($right !== '' && $right < $left)) {
                    $dst['created_at'] = $right;
                }
            }
            if (isset($columns['updated_at'])) {
                $left = trim((string)($dst['updated_at'] ?? ''));
                $right = trim((string)($row['updated_at'] ?? ''));
                if ($left === '' || ($right !== '' && $right > $left)) {
                    $dst['updated_at'] = $right;
                }
            }
            if (isset($columns['generated_at'])) {
                $left = trim((string)($dst['generated_at'] ?? ''));
                $right = trim((string)($row['generated_at'] ?? ''));
                if ($left === '' || ($right !== '' && $right > $left)) {
                    $dst['generated_at'] = $right;
                }
            }
            if (isset($columns['last_movement_at'])) {
                $left = trim((string)($dst['last_movement_at'] ?? ''));
                $right = trim((string)($row['last_movement_at'] ?? ''));
                if ($left === '' || ($right !== '' && $right > $left)) {
                    $dst['last_movement_at'] = $right;
                }
            }

            $groups[$groupKey] = $dst;
        }

        $merged = array_values($groups);
        foreach ($merged as &$row) {
            if (isset($columns['avg_cost_per_content']) && isset($columns['total_value']) && isset($columns['closing_qty_content'])) {
                $den = (float)($row['closing_qty_content'] ?? 0);
                $num = (float)($row['total_value'] ?? 0);
                if ($den > 0) {
                    $row['avg_cost_per_content'] = round($num / $den, 6);
                }
            }
            if (isset($columns['opening_avg_cost_per_content']) && isset($columns['opening_total_value']) && isset($columns['opening_qty_content'])) {
                $den = (float)($row['opening_qty_content'] ?? 0);
                $num = (float)($row['opening_total_value'] ?? 0);
                if ($den > 0) {
                    $row['opening_avg_cost_per_content'] = round($num / $den, 6);
                }
            }
        }
        unset($row);

        return $merged;
    }

    private function reclassify_key_token($value): string
    {
        if ($value === null || $value === '') {
            return '__NULL__';
        }
        if (is_numeric($value)) {
            return (string)+$value;
        }
        return trim((string)$value);
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

        if ($this->requiresPurchaseOrderEditReviewBeforeStatusUpdate($purchaseOrderId)) {
            return [
                'ok' => false,
                'message' => 'PO hasil verifikasi divisi harus dibuka Edit Data dulu untuk review buyer sebelum status bisa diubah dari daftar PO.',
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

        $availabilityRefresh = [];
        if ($newStatus === 'VOID') {
            $availabilityRefresh = $this->trigger_pos_availability_refresh_for_materials(
                (array)($rollbackData['receipts']['material_ids'] ?? []),
                [
                    'event_source' => 'PURCHASE_ORDER_VOID',
                    'event_table' => 'pur_purchase_order',
                    'event_id' => $purchaseOrderId,
                    'actor_employee_id' => $userId > 0 ? $userId : null,
                ]
            );
        }

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
                'availability_rebuild' => $availabilityRefresh,
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

        $autoReceiptDate = $this->normalizeDate((string)($order['request_date'] ?? ''));
        if ($autoReceiptDate === null) {
            $autoReceiptDate = date('Y-m-d');
        }

        $receiptPayload = [
            'header' => [
                'purchase_order_id' => $purchaseOrderId,
                'receipt_date' => $autoReceiptDate,
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

    public function normalizePurchaseCatalogConversion(array $filters = [], bool $manageTransaction = true): array
    {
        if (
            !$this->db->table_exists('mst_purchase_catalog') ||
            !$this->db->field_exists('content_per_buy', 'mst_purchase_catalog') ||
            !$this->db->field_exists('conversion_factor_to_content', 'mst_purchase_catalog')
        ) {
            return [
                'ok' => true,
                'message' => 'Tabel/kolom konversi purchase catalog belum tersedia.',
                'data' => ['rows_updated' => 0],
            ];
        }

        $qb = $this->db
            ->select('id, profile_key, catalog_name, line_kind, content_per_buy, conversion_factor_to_content')
            ->from('mst_purchase_catalog');

        $catalogIds = array_values(array_filter(array_map('intval', (array)($filters['catalog_ids'] ?? [])), static function ($id) {
            return $id > 0;
        }));
        if (!empty($catalogIds)) {
            $qb->where_in('id', $catalogIds);
        }

        $profileKeys = array_values(array_filter(array_map('trim', (array)($filters['profile_keys'] ?? [])), static function ($value) {
            return $value !== '';
        }));
        if (!empty($profileKeys)) {
            $qb->where_in('profile_key', $profileKeys);
        }

        $rows = $qb->order_by('id', 'ASC')->get()->result_array();
        if (empty($rows)) {
            return [
                'ok' => true,
                'message' => 'Tidak ada row purchase catalog yang cocok untuk dinormalisasi.',
                'data' => ['rows_updated' => 0],
            ];
        }

        if ($manageTransaction) {
            $this->db->trans_begin();
        }

        $rowsUpdated = 0;
        $samples = [];

        foreach ($rows as $row) {
            $canonicalFactor = $this->canonicalConversionFactor(
                $row['content_per_buy'] ?? 0,
                $row['conversion_factor_to_content'] ?? 0
            );
            $targetContentPerBuy = round($canonicalFactor, 6);
            $targetFactor = round($canonicalFactor, 8);
            $currentContentPerBuy = round((float)($row['content_per_buy'] ?? 0), 6);
            $currentFactor = round((float)($row['conversion_factor_to_content'] ?? 0), 8);

            if (abs($currentContentPerBuy - $targetContentPerBuy) < 0.000001 && abs($currentFactor - $targetFactor) < 0.00000001) {
                continue;
            }

            $this->db
                ->where('id', (int)$row['id'])
                ->update('mst_purchase_catalog', [
                    'content_per_buy' => $targetContentPerBuy,
                    'conversion_factor_to_content' => $targetFactor,
                ]);

            if ((int)($this->db->error()['code'] ?? 0) !== 0) {
                if ($manageTransaction) {
                    $this->db->trans_rollback();
                }
                return [
                    'ok' => false,
                    'message' => 'Gagal menormalisasi purchase catalog #' . (int)$row['id'] . '.',
                ];
            }

            $rowsUpdated++;
            if (count($samples) < 5) {
                $samples[] = [
                    'id' => (int)$row['id'],
                    'catalog_name' => (string)($row['catalog_name'] ?? ''),
                    'before_content_per_buy' => $currentContentPerBuy,
                    'before_factor' => $currentFactor,
                    'after_factor' => $targetFactor,
                ];
            }
        }

        if ($manageTransaction) {
            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => 'Gagal commit normalisasi purchase catalog.',
                ];
            }
            $this->db->trans_commit();
        }

        return [
            'ok' => true,
            'message' => $rowsUpdated > 0
                ? ('Normalisasi purchase catalog selesai. Row diubah: ' . $rowsUpdated . '.')
                : 'Semua row purchase catalog sudah sinkron.',
            'data' => [
                'rows_updated' => $rowsUpdated,
                'samples' => $samples,
            ],
        ];
    }

    private function catalogUnitPriceGroupKeyValue(array $row): string
    {
        $lastUnitPrice = $row['last_unit_price'] ?? null;
        $standardPrice = $row['standard_price'] ?? null;

        $effectivePrice = 0.0;
        if ($lastUnitPrice !== null && $lastUnitPrice !== '') {
            $effectivePrice = (float)$lastUnitPrice;
        } elseif ($standardPrice !== null && $standardPrice !== '') {
            $effectivePrice = (float)$standardPrice;
        }

        return number_format(round(max(0, $effectivePrice), 2), 2, '.', '');
    }

    private function catalogExactSignatureGroupKey(array $row, bool $includeUnitPrice = true): string
    {
        $parts = [
            strtoupper(trim((string)($row['line_kind'] ?? 'ITEM'))),
            (string)($row['item_id'] ?? ''),
            (string)($row['material_id'] ?? ''),
            (string)($row['buy_uom_id'] ?? ''),
            (string)($row['content_uom_id'] ?? ''),
            strtoupper(trim((string)($row['catalog_name'] ?? ''))),
            strtoupper(trim((string)($row['brand_name'] ?? ''))),
            strtoupper(trim((string)($row['line_description'] ?? ''))),
            number_format(round((float)($row['content_per_buy'] ?? 0), 6), 6, '.', ''),
        ];

        if ($includeUnitPrice) {
            $parts[] = $this->catalogUnitPriceGroupKeyValue($row);
        }

        return implode('|', $parts);
    }

    public function normalizePurchaseCatalogProfileKeys(array $filters = [], bool $manageTransaction = true): array
    {
        if (
            !$this->db->table_exists('mst_purchase_catalog') ||
            !$this->db->field_exists('profile_key', 'mst_purchase_catalog') ||
            !$this->db->field_exists('catalog_name', 'mst_purchase_catalog')
        ) {
            return [
                'ok' => true,
                'message' => 'Tabel/kolom purchase catalog untuk normalisasi profile_key belum tersedia.',
                'data' => [
                    'dry_run' => !empty($filters['dry_run']),
                    'groups_found' => 0,
                    'rows_to_deactivate' => 0,
                    'rows_updated' => 0,
                    'samples' => [],
                ],
            ];
        }

        $dryRun = !empty($filters['dry_run']);
        $limit = (int)($filters['limit'] ?? 10000);
        if ($limit <= 0 || $limit > 50000) {
            $limit = 10000;
        }

        $catalogSelectColumns = [
            'id', 'profile_key', 'line_kind', 'item_id', 'material_id', 'catalog_name', 'brand_name', 'line_description',
            'buy_uom_id', 'content_uom_id', 'content_per_buy', 'conversion_factor_to_content',
            'standard_price', 'last_unit_price', 'last_purchase_date', 'notes', 'is_active',
        ];
        foreach (['expired_date', 'last_purchase_order_id', 'last_purchase_line_id'] as $optionalColumn) {
            if ($this->db->field_exists($optionalColumn, 'mst_purchase_catalog')) {
                $catalogSelectColumns[] = $optionalColumn;
            }
        }

        $qb = $this->db
            ->select(implode(', ', $catalogSelectColumns), false)
            ->from('mst_purchase_catalog')
            ->where('profile_key IS NOT NULL', null, false)
            ->where("TRIM(profile_key) <> ''", null, false)
            ->order_by('id', 'ASC')
            ->limit($limit);
        if ($this->db->field_exists('is_active', 'mst_purchase_catalog')) {
            $qb->where('COALESCE(is_active,1) = 1', null, false);
        }

        $catalogIds = array_values(array_filter(array_map('intval', (array)($filters['catalog_ids'] ?? [])), static function ($id) {
            return $id > 0;
        }));
        if (!empty($catalogIds)) {
            $qb->where_in('id', $catalogIds);
        }

        $profileKeys = array_values(array_filter(array_map('trim', (array)($filters['profile_keys'] ?? [])), static function ($value) {
            return $value !== '';
        }));
        if (!empty($profileKeys)) {
            $qb->where_in('profile_key', $profileKeys);
        }

        $rows = $qb->get()->result_array();
        if (empty($rows)) {
            return [
                'ok' => true,
                'message' => 'Tidak ada row purchase catalog yang cocok untuk dicek.',
                'data' => [
                    'dry_run' => $dryRun,
                    'groups_found' => 0,
                    'rows_to_deactivate' => 0,
                    'rows_updated' => 0,
                    'samples' => [],
                ],
            ];
        }

        $groups = [];
        foreach ($rows as $row) {
            $groupKey = $this->catalogExactSignatureGroupKey($row, true);
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [];
            }
            $groups[$groupKey][] = $row;
        }

        $duplicateGroups = [];
        foreach ($groups as $groupRows) {
            $distinctKeys = [];
            foreach ($groupRows as $row) {
                $profileKey = trim((string)($row['profile_key'] ?? ''));
                if ($profileKey !== '') {
                    $distinctKeys[$profileKey] = true;
                }
            }
            if (count($distinctKeys) <= 1) {
                continue;
            }

            $usageScores = $this->catalogProfileKeyUsageScores(array_values(array_map(static function (array $row): string {
                return trim((string)($row['profile_key'] ?? ''));
            }, $groupRows)));
            usort($groupRows, static function (array $a, array $b) use ($usageScores): int {
                $aKey = trim((string)($a['profile_key'] ?? ''));
                $bKey = trim((string)($b['profile_key'] ?? ''));
                $aScore = (int)($usageScores[$aKey] ?? 0);
                $bScore = (int)($usageScores[$bKey] ?? 0);
                if ($aScore !== $bScore) {
                    return $bScore <=> $aScore;
                }

                $aActive = (int)($a['is_active'] ?? 1);
                $bActive = (int)($b['is_active'] ?? 1);
                if ($aActive !== $bActive) {
                    return $bActive <=> $aActive;
                }

                return (int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0);
            });

            $canonical = $groupRows[0];
            $duplicates = array_slice($groupRows, 1);
            if (empty($duplicates)) {
                continue;
            }

            $canonicalUpdate = [];
            foreach ($duplicates as $duplicate) {
                $canonicalDate = $this->normalizeDate((string)($canonical['last_purchase_date'] ?? ''));
                $duplicateDate = $this->normalizeDate((string)($duplicate['last_purchase_date'] ?? ''));
                $duplicateIsNewer = $duplicateDate !== null && ($canonicalDate === null || $duplicateDate > $canonicalDate);

                if ($duplicateIsNewer) {
                    foreach (['last_purchase_date', 'last_purchase_order_id', 'last_purchase_line_id', 'last_unit_price', 'standard_price'] as $column) {
                        if (array_key_exists($column, $duplicate)) {
                            $canonicalUpdate[$column] = $duplicate[$column];
                        }
                    }
                }

                foreach (['brand_name', 'line_description', 'notes'] as $column) {
                    $canonicalValue = trim((string)($canonicalUpdate[$column] ?? $canonical[$column] ?? ''));
                    $duplicateValue = trim((string)($duplicate[$column] ?? ''));
                    if ($canonicalValue === '' && $duplicateValue !== '') {
                        $canonicalUpdate[$column] = $duplicate[$column];
                    }
                }
            }

            $canonicalUpdate = $this->sanitizeCatalogPurchaseReferenceData($canonicalUpdate);

            $duplicateGroups[] = [
                'canonical' => $canonical,
                'canonical_update' => $canonicalUpdate,
                'duplicates' => $duplicates,
            ];
        }

        if (empty($duplicateGroups)) {
            return [
                'ok' => true,
                'message' => 'Tidak ada duplicate identity purchase catalog dengan profile_key berbeda.',
                'data' => [
                    'dry_run' => $dryRun,
                    'groups_found' => 0,
                    'rows_to_deactivate' => 0,
                    'rows_updated' => 0,
                    'samples' => [],
                ],
            ];
        }

        $rowsToDeactivate = 0;
        $samples = [];
        foreach ($duplicateGroups as $group) {
            $rowsToDeactivate += count($group['duplicates']);
            if (count($samples) < 10) {
                $samples[] = [
                    'canonical_id' => (int)($group['canonical']['id'] ?? 0),
                    'canonical_profile_key' => (string)($group['canonical']['profile_key'] ?? ''),
                    'catalog_name' => (string)($group['canonical']['catalog_name'] ?? ''),
                    'duplicate_ids' => array_map(static function (array $row): int {
                        return (int)($row['id'] ?? 0);
                    }, $group['duplicates']),
                    'duplicate_profile_keys' => array_values(array_map(static function (array $row): string {
                        return (string)($row['profile_key'] ?? '');
                    }, $group['duplicates'])),
                ];
            }
        }

        if ($dryRun) {
            return [
                'ok' => true,
                'message' => 'Dry-run duplicate profile_key purchase catalog selesai.',
                'data' => [
                    'dry_run' => true,
                    'groups_found' => count($duplicateGroups),
                    'rows_to_deactivate' => $rowsToDeactivate,
                    'rows_updated' => 0,
                    'samples' => $samples,
                ],
            ];
        }

        if ($manageTransaction) {
            $this->db->trans_begin();
        }

        $rowsUpdated = 0;
        foreach ($duplicateGroups as $group) {
            $canonical = $group['canonical'];
            $canonicalUpdate = $group['canonical_update'];

            if (!empty($canonicalUpdate)) {
                $canonicalUpdate['is_active'] = 1;
                $this->db->where('id', (int)$canonical['id'])->update('mst_purchase_catalog', $canonicalUpdate);
                if ((int)($this->db->error()['code'] ?? 0) !== 0) {
                    if ($manageTransaction) {
                        $this->db->trans_rollback();
                    }
                    return [
                        'ok' => false,
                        'message' => 'Gagal mengupdate row catalog kanonik #' . (int)$canonical['id'] . '.',
                    ];
                }
                $rowsUpdated++;
            }

            foreach ($group['duplicates'] as $duplicate) {
                $existingNotes = trim((string)($duplicate['notes'] ?? ''));
                $mergeNote = 'Duplicate identity merged into canonical profile_key ' . (string)($canonical['profile_key'] ?? '');
                if (stripos($existingNotes, $mergeNote) !== false) {
                    $newNotes = $existingNotes;
                } else {
                    $newNotes = $existingNotes !== '' ? ($existingNotes . ' | ' . $mergeNote) : $mergeNote;
                }

                $updateData = [
                    'is_active' => 0,
                    'notes' => $newNotes,
                ];
                if ($this->db->field_exists('last_purchase_order_id', 'mst_purchase_catalog')) {
                    $updateData['last_purchase_order_id'] = null;
                }
                if ($this->db->field_exists('last_purchase_line_id', 'mst_purchase_catalog')) {
                    $updateData['last_purchase_line_id'] = null;
                }

                $this->db->where('id', (int)$duplicate['id'])->update('mst_purchase_catalog', $updateData);
                if ((int)($this->db->error()['code'] ?? 0) !== 0) {
                    if ($manageTransaction) {
                        $this->db->trans_rollback();
                    }
                    return [
                        'ok' => false,
                        'message' => 'Gagal menonaktifkan duplicate catalog #' . (int)$duplicate['id'] . '.',
                    ];
                }
                $rowsUpdated++;
            }
        }

        if ($manageTransaction) {
            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => 'Gagal commit normalisasi profile_key purchase catalog.',
                ];
            }
            $this->db->trans_commit();
        }

        return [
            'ok' => true,
            'message' => 'Normalisasi duplicate profile_key purchase catalog selesai.',
            'data' => [
                'dry_run' => false,
                'groups_found' => count($duplicateGroups),
                'rows_to_deactivate' => $rowsToDeactivate,
                'rows_updated' => $rowsUpdated,
                'samples' => $samples,
            ],
        ];
    }

    public function reconcilePurchaseCatalogExactProfiles(array $filters = [], bool $manageTransaction = true): array
    {
        if (
            !$this->db->table_exists('mst_purchase_catalog') ||
            !$this->db->field_exists('profile_key', 'mst_purchase_catalog') ||
            !$this->db->field_exists('catalog_name', 'mst_purchase_catalog')
        ) {
            return [
                'ok' => true,
                'message' => 'Tabel/kolom purchase catalog belum tersedia untuk reconcile exact profile.',
                'data' => [
                    'dry_run' => !empty($filters['dry_run']),
                    'groups_found' => 0,
                    'rows_to_deactivate' => 0,
                    'rows_to_reactivate' => 0,
                    'rows_updated' => 0,
                    'samples' => [],
                ],
            ];
        }

        $dryRun = !empty($filters['dry_run']);
        $limit = (int)($filters['limit'] ?? 10000);
        if ($limit <= 0 || $limit > 50000) {
            $limit = 10000;
        }

        $catalogSelectColumns = [
            'id', 'profile_key', 'line_kind', 'item_id', 'material_id', 'catalog_name', 'brand_name', 'line_description',
            'buy_uom_id', 'content_uom_id', 'content_per_buy', 'conversion_factor_to_content',
            'standard_price', 'last_unit_price', 'last_purchase_date', 'notes', 'is_active',
        ];
        foreach (['expired_date', 'last_purchase_order_id', 'last_purchase_line_id'] as $optionalColumn) {
            if ($this->db->field_exists($optionalColumn, 'mst_purchase_catalog')) {
                $catalogSelectColumns[] = $optionalColumn;
            }
        }

        $qb = $this->db
            ->select(implode(', ', $catalogSelectColumns), false)
            ->from('mst_purchase_catalog')
            ->where('profile_key IS NOT NULL', null, false)
            ->where("TRIM(profile_key) <> ''", null, false)
            ->order_by('id', 'ASC')
            ->limit($limit);

        $catalogIds = array_values(array_filter(array_map('intval', (array)($filters['catalog_ids'] ?? [])), static function ($id) {
            return $id > 0;
        }));
        if (!empty($catalogIds)) {
            $qb->where_in('id', $catalogIds);
        }

        $profileKeys = array_values(array_filter(array_map('trim', (array)($filters['profile_keys'] ?? [])), static function ($value) {
            return $value !== '';
        }));
        if (!empty($profileKeys)) {
            $qb->where_in('profile_key', $profileKeys);
        }

        $rows = $qb->get()->result_array();
        if (empty($rows)) {
            return [
                'ok' => true,
                'message' => 'Tidak ada row purchase catalog yang cocok untuk exact reconcile.',
                'data' => [
                    'dry_run' => $dryRun,
                    'groups_found' => 0,
                    'rows_to_deactivate' => 0,
                    'rows_to_reactivate' => 0,
                    'rows_updated' => 0,
                    'samples' => [],
                ],
            ];
        }

        $groups = [];
        foreach ($rows as $row) {
            $groupKey = $this->catalogExactSignatureGroupKey($row, true);
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [];
            }
            $groups[$groupKey][] = $row;
        }

        $reconcileGroups = [];
        foreach ($groups as $groupRows) {
            $usageScores = $this->catalogProfileKeyUsageScores(array_values(array_map(static function (array $row): string {
                return trim((string)($row['profile_key'] ?? ''));
            }, $groupRows)));

            usort($groupRows, static function (array $a, array $b) use ($usageScores): int {
                $aKey = trim((string)($a['profile_key'] ?? ''));
                $bKey = trim((string)($b['profile_key'] ?? ''));
                $aScore = (int)($usageScores[$aKey] ?? 0);
                $bScore = (int)($usageScores[$bKey] ?? 0);
                if ($aScore !== $bScore) {
                    return $bScore <=> $aScore;
                }

                $aActive = (int)($a['is_active'] ?? 1);
                $bActive = (int)($b['is_active'] ?? 1);
                if ($aActive !== $bActive) {
                    return $bActive <=> $aActive;
                }

                $aDate = $a['last_purchase_date'] ?? null;
                $bDate = $b['last_purchase_date'] ?? null;
                if ($aDate !== $bDate) {
                    return strcmp((string)$bDate, (string)$aDate);
                }

                return (int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0);
            });

            $canonical = $groupRows[0];
            $duplicates = array_slice($groupRows, 1);
            $hasInactiveCanonical = (int)($canonical['is_active'] ?? 1) !== 1;
            if (empty($duplicates) && !$hasInactiveCanonical) {
                continue;
            }

            $reconcileGroups[] = [
                'canonical' => $canonical,
                'duplicates' => $duplicates,
            ];
        }

        if (empty($reconcileGroups)) {
            return [
                'ok' => true,
                'message' => 'Semua exact profile purchase catalog sudah sesuai aturan harga.',
                'data' => [
                    'dry_run' => $dryRun,
                    'groups_found' => 0,
                    'rows_to_deactivate' => 0,
                    'rows_to_reactivate' => 0,
                    'rows_updated' => 0,
                    'samples' => [],
                ],
            ];
        }

        $rowsToDeactivate = 0;
        $rowsToReactivate = 0;
        $samples = [];
        foreach ($reconcileGroups as $group) {
            $canonical = $group['canonical'];
            $duplicates = $group['duplicates'];
            $rowsToDeactivate += count($duplicates);
            if ((int)($canonical['is_active'] ?? 1) !== 1) {
                $rowsToReactivate++;
            }
            if (count($samples) < 10) {
                $samples[] = [
                    'canonical_id' => (int)($canonical['id'] ?? 0),
                    'canonical_profile_key' => (string)($canonical['profile_key'] ?? ''),
                    'catalog_name' => (string)($canonical['catalog_name'] ?? ''),
                    'unit_price' => $this->catalogUnitPriceGroupKeyValue($canonical),
                    'duplicate_ids' => array_map(static function (array $row): int {
                        return (int)($row['id'] ?? 0);
                    }, $duplicates),
                ];
            }
        }

        if ($dryRun) {
            return [
                'ok' => true,
                'message' => 'Dry-run reconcile exact purchase catalog selesai.',
                'data' => [
                    'dry_run' => true,
                    'groups_found' => count($reconcileGroups),
                    'rows_to_deactivate' => $rowsToDeactivate,
                    'rows_to_reactivate' => $rowsToReactivate,
                    'rows_updated' => 0,
                    'samples' => $samples,
                ],
            ];
        }

        if ($manageTransaction) {
            $this->db->trans_begin();
        }

        $rowsUpdated = 0;
        foreach ($reconcileGroups as $group) {
            $canonical = $group['canonical'];
            $canonicalNotes = trim((string)($canonical['notes'] ?? ''));
            $canonicalTouchNote = 'Exact profile canonicalized by price-aware reconcile';
            if (stripos($canonicalNotes, $canonicalTouchNote) === false) {
                $canonicalNotes = $canonicalNotes !== '' ? ($canonicalNotes . ' | ' . $canonicalTouchNote) : $canonicalTouchNote;
            }

            $this->db->where('id', (int)$canonical['id'])->update('mst_purchase_catalog', [
                'is_active' => 1,
                'notes' => $canonicalNotes,
            ]);
            if ((int)($this->db->error()['code'] ?? 0) !== 0) {
                if ($manageTransaction) {
                    $this->db->trans_rollback();
                }
                return [
                    'ok' => false,
                    'message' => 'Gagal mengaktifkan canonical catalog #' . (int)$canonical['id'] . '.',
                ];
            }
            $rowsUpdated++;

            foreach ($group['duplicates'] as $duplicate) {
                $existingNotes = trim((string)($duplicate['notes'] ?? ''));
                $mergeNote = 'Exact duplicate merged into canonical profile_key ' . (string)($canonical['profile_key'] ?? '');
                if (stripos($existingNotes, $mergeNote) !== false) {
                    $newNotes = $existingNotes;
                } else {
                    $newNotes = $existingNotes !== '' ? ($existingNotes . ' | ' . $mergeNote) : $mergeNote;
                }

                $updateData = [
                    'is_active' => 0,
                    'notes' => $newNotes,
                ];
                if ($this->db->field_exists('last_purchase_order_id', 'mst_purchase_catalog')) {
                    $updateData['last_purchase_order_id'] = null;
                }
                if ($this->db->field_exists('last_purchase_line_id', 'mst_purchase_catalog')) {
                    $updateData['last_purchase_line_id'] = null;
                }

                $this->db->where('id', (int)$duplicate['id'])->update('mst_purchase_catalog', $updateData);
                if ((int)($this->db->error()['code'] ?? 0) !== 0) {
                    if ($manageTransaction) {
                        $this->db->trans_rollback();
                    }
                    return [
                        'ok' => false,
                        'message' => 'Gagal menonaktifkan duplicate exact catalog #' . (int)$duplicate['id'] . '.',
                    ];
                }
                $rowsUpdated++;
            }
        }

        if ($manageTransaction) {
            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => 'Gagal commit reconcile exact profile purchase catalog.',
                ];
            }
            $this->db->trans_commit();
        }

        return [
            'ok' => true,
            'message' => 'Reconcile exact profile purchase catalog selesai.',
            'data' => [
                'dry_run' => false,
                'groups_found' => count($reconcileGroups),
                'rows_to_deactivate' => $rowsToDeactivate,
                'rows_to_reactivate' => $rowsToReactivate,
                'rows_updated' => $rowsUpdated,
                'samples' => $samples,
            ],
        ];
    }

    public function repairPostedReceiptConversion(int $purchaseOrderId, int $userId = 0, bool $normalizeCatalog = false): array
    {
        if (
            $purchaseOrderId <= 0 ||
            !$this->db->table_exists('pur_purchase_order') ||
            !$this->db->table_exists('pur_purchase_order_line') ||
            !$this->db->table_exists('pur_purchase_receipt') ||
            !$this->db->table_exists('pur_purchase_receipt_line') ||
            !$this->db->table_exists('inv_stock_movement_log')
        ) {
            return [
                'ok' => false,
                'message' => 'Schema purchase/receipt/movement belum lengkap untuk repair konversi receipt.',
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

        $receipts = $this->db
            ->select('id, receipt_no, receipt_date, destination_type, destination_division_id, status, notes')
            ->from('pur_purchase_receipt')
            ->where('purchase_order_id', $purchaseOrderId)
            ->where('status', 'POSTED')
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();
        if (empty($receipts)) {
            return [
                'ok' => false,
                'message' => 'Tidak ada receipt POSTED untuk PO ini.',
            ];
        }

        $this->db->trans_begin();

        $poLinesUpdated = 0;
        $receiptLinesUpdated = 0;
        $lotRowsUpdated = 0;
        $movementRowsUpdated = 0;
        $receiptsTouched = 0;
        $rebuildTargets = [];
        $catalogProfileKeys = [];
        $hasMovementProfileContentPerBuy = $this->db->field_exists('profile_content_per_buy', 'inv_stock_movement_log');

        foreach ($receipts as $receipt) {
            $receiptId = (int)($receipt['id'] ?? 0);
            if ($receiptId <= 0) {
                continue;
            }

            $scope = strtoupper((string)($receipt['destination_type'] ?? '')) === 'GUDANG' ? 'WAREHOUSE' : 'DIVISION';
            $divisionId = $scope === 'DIVISION' ? $this->nullableInt($receipt['destination_division_id'] ?? null) : null;
            $destinationType = $scope === 'DIVISION' ? $this->normalizeDestination((string)($receipt['destination_type'] ?? '')) : null;
            if ($scope === 'DIVISION' && $divisionId === null) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => 'Repair receipt gagal: division tujuan tidak valid untuk receipt #' . (string)($receipt['receipt_no'] ?? $receiptId) . '.',
                ];
            }

            $receiptDate = $this->normalizeDate((string)($receipt['receipt_date'] ?? ''));
            if ($receiptDate === null) {
                $receiptDate = date('Y-m-d');
            }
            $rebuildStartDate = date('Y-m-01', strtotime($receiptDate));

            $lineQb = $this->db
                ->select('rl.id AS receipt_line_id, rl.purchase_order_line_id, rl.line_kind, rl.item_id, rl.material_id')
                ->select('rl.qty_buy_received, rl.qty_content_received, rl.conversion_factor_to_content AS receipt_conversion_factor_to_content')
                ->select('rl.buy_uom_id, rl.content_uom_id, rl.brand_name, rl.line_description, rl.profile_key')
                ->select('pol.line_no, pol.qty_buy AS po_qty_buy, pol.qty_content AS po_qty_content, pol.unit_price, pol.content_per_buy')
                ->select('pol.conversion_factor_to_content AS po_conversion_factor_to_content')
                ->select('pol.snapshot_item_name, pol.snapshot_material_name, pol.snapshot_brand_name, pol.snapshot_line_description')
                ->select('pol.snapshot_buy_uom_code, pol.snapshot_content_uom_code')
                ->from('pur_purchase_receipt_line rl')
                ->join('pur_purchase_order_line pol', 'pol.id = rl.purchase_order_line_id', 'inner')
                ->where('rl.purchase_receipt_id', $receiptId)
                ->order_by('rl.id', 'ASC');

            if ($this->db->field_exists('expired_date', 'pur_purchase_receipt_line')) {
                $lineQb->select('rl.expired_date AS receipt_expired_date');
            }
            if ($this->db->field_exists('expired_date', 'pur_purchase_order_line')) {
                $lineQb->select('pol.expired_date AS po_expired_date');
            }
            if ($this->db->field_exists('snapshot_expired_date', 'pur_purchase_order_line')) {
                $lineQb->select('pol.snapshot_expired_date');
            }
            if ($this->hasPurchaseReceiptUsagePurposeColumn()) {
                $lineQb->select('rl.usage_purpose');
            } elseif ($this->hasPurchaseOrderUsagePurposeColumn()) {
                $lineQb->select('pol.usage_purpose');
            }

            $lines = $lineQb->get()->result_array();
            $receiptTouched = false;

            foreach ($lines as $line) {
                $receiptLineId = (int)($line['receipt_line_id'] ?? 0);
                $poLineId = (int)($line['purchase_order_line_id'] ?? 0);
                if ($receiptLineId <= 0 || $poLineId <= 0) {
                    continue;
                }

                $qtyBuyReceived = round((float)($line['qty_buy_received'] ?? 0), 4);
                $canonicalFactor = $this->canonicalConversionFactor(
                    $line['content_per_buy'] ?? 0,
                    $line['po_conversion_factor_to_content'] ?? $line['receipt_conversion_factor_to_content'] ?? 0
                );
                $expectedQtyContent = round($qtyBuyReceived * $canonicalFactor, 4);
                $expectedUnitCost = $canonicalFactor > 0 ? round((float)($line['unit_price'] ?? 0) / $canonicalFactor, 6) : 0;
                $expectedPoQtyContent = round((float)($line['po_qty_buy'] ?? 0) * $canonicalFactor, 4);
                $catalogProfileKey = trim((string)($line['profile_key'] ?? ''));
                if ($catalogProfileKey !== '') {
                    $catalogProfileKeys[$catalogProfileKey] = $catalogProfileKey;
                }

                $lotRows = [];
                if ($this->db->table_exists('inv_material_fifo_lot')) {
                    $lotRows = $this->db
                        ->select('id, qty_in, qty_out, qty_balance, unit_cost, status')
                        ->from('inv_material_fifo_lot')
                        ->where('receipt_line_id', $receiptLineId)
                        ->order_by('id', 'ASC')
                        ->get()
                        ->result_array();

                    if (count($lotRows) > 1) {
                        $this->db->trans_rollback();
                        return [
                            'ok' => false,
                            'message' => 'Repair receipt gagal: receipt line #' . $receiptLineId . ' memiliki lebih dari satu lot inbound.',
                        ];
                    }

                    if (!empty($lotRows)) {
                        $lot = $lotRows[0];
                        $qtyOut = round((float)($lot['qty_out'] ?? 0), 4);
                        $qtyIn = round((float)($lot['qty_in'] ?? 0), 4);
                        $qtyBalance = round((float)($lot['qty_balance'] ?? 0), 4);
                        if ($qtyOut > 0.0001 || abs($qtyBalance - $qtyIn) > 0.0001) {
                            $this->db->trans_rollback();
                            return [
                                'ok' => false,
                                'message' => 'Repair receipt ditolak karena lot inbound untuk receipt line #' . $receiptLineId . ' sudah terpakai / berubah.',
                            ];
                        }
                    }
                }

                $movementRows = $this->db
                    ->select('id, qty_content_delta, unit_cost' . ($hasMovementProfileContentPerBuy ? ', profile_content_per_buy' : ''), false)
                    ->from('inv_stock_movement_log')
                    ->where('ref_table', 'pur_purchase_receipt')
                    ->where('ref_id', $receiptId)
                    ->where('receipt_line_id', $receiptLineId)
                    ->order_by('id', 'ASC')
                    ->get()
                    ->result_array();
                if (empty($movementRows)) {
                    $this->db->trans_rollback();
                    return [
                        'ok' => false,
                        'message' => 'Repair receipt gagal: movement row tidak ditemukan untuk receipt line #' . $receiptLineId . '.',
                    ];
                }

                $poLineChanged = (
                    abs(round((float)($line['content_per_buy'] ?? 0), 6) - round($canonicalFactor, 6)) > 0.000001 ||
                    abs(round((float)($line['po_conversion_factor_to_content'] ?? 0), 8) - round($canonicalFactor, 8)) > 0.00000001 ||
                    abs(round((float)($line['po_qty_content'] ?? 0), 4) - $expectedPoQtyContent) > 0.0001
                );
                if ($poLineChanged) {
                    $this->db
                        ->where('id', $poLineId)
                        ->update('pur_purchase_order_line', [
                            'content_per_buy' => round($canonicalFactor, 6),
                            'conversion_factor_to_content' => round($canonicalFactor, 8),
                            'qty_content' => $expectedPoQtyContent,
                        ]);
                    if ((int)($this->db->error()['code'] ?? 0) !== 0) {
                        $this->db->trans_rollback();
                        return [
                            'ok' => false,
                            'message' => 'Gagal memperbaiki line PO #' . (int)($line['line_no'] ?? 0) . '.',
                        ];
                    }
                    $poLinesUpdated++;
                }

                $receiptLineChanged = (
                    abs(round((float)($line['qty_content_received'] ?? 0), 4) - $expectedQtyContent) > 0.0001 ||
                    abs(round((float)($line['receipt_conversion_factor_to_content'] ?? 0), 8) - round($canonicalFactor, 8)) > 0.00000001
                );
                if ($receiptLineChanged) {
                    $this->db
                        ->where('id', $receiptLineId)
                        ->update('pur_purchase_receipt_line', [
                            'qty_content_received' => $expectedQtyContent,
                            'conversion_factor_to_content' => round($canonicalFactor, 8),
                        ]);
                    if ((int)($this->db->error()['code'] ?? 0) !== 0) {
                        $this->db->trans_rollback();
                        return [
                            'ok' => false,
                            'message' => 'Gagal memperbaiki receipt line #' . $receiptLineId . '.',
                        ];
                    }
                    $receiptLinesUpdated++;
                    $receiptTouched = true;
                }

                if (!empty($lotRows)) {
                    $lot = $lotRows[0];
                    $lotChanged = (
                        abs(round((float)($lot['qty_in'] ?? 0), 4) - $expectedQtyContent) > 0.0001 ||
                        abs(round((float)($lot['qty_balance'] ?? 0), 4) - $expectedQtyContent) > 0.0001 ||
                        abs(round((float)($lot['unit_cost'] ?? 0), 6) - $expectedUnitCost) > 0.000001
                    );
                    if ($lotChanged) {
                        $lotUpdate = [
                            'qty_in' => $expectedQtyContent,
                            'qty_balance' => $expectedQtyContent,
                            'unit_cost' => $expectedUnitCost,
                        ];
                        if ($this->db->field_exists('status', 'inv_material_fifo_lot')) {
                            $lotUpdate['status'] = $expectedQtyContent > 0 ? 'OPEN' : 'CLOSED';
                        }
                        $this->db->where('id', (int)$lot['id'])->update('inv_material_fifo_lot', $lotUpdate);
                        if ((int)($this->db->error()['code'] ?? 0) !== 0) {
                            $this->db->trans_rollback();
                            return [
                                'ok' => false,
                                'message' => 'Gagal memperbaiki lot receipt #' . (int)$lot['id'] . '.',
                            ];
                        }
                        $lotRowsUpdated++;
                        $receiptTouched = true;
                    }
                }

                foreach ($movementRows as $movementRow) {
                    $movementChanged = (
                        abs(round((float)($movementRow['qty_content_delta'] ?? 0), 4) - $expectedQtyContent) > 0.0001 ||
                        abs(round((float)($movementRow['unit_cost'] ?? 0), 6) - $expectedUnitCost) > 0.000001 ||
                        ($hasMovementProfileContentPerBuy && abs(round((float)($movementRow['profile_content_per_buy'] ?? 0), 6) - round($canonicalFactor, 6)) > 0.000001)
                    );
                    if (!$movementChanged) {
                        continue;
                    }

                    $movementUpdate = [
                        'qty_content_delta' => $expectedQtyContent,
                        'unit_cost' => $expectedUnitCost,
                    ];
                    if ($hasMovementProfileContentPerBuy) {
                        $movementUpdate['profile_content_per_buy'] = round($canonicalFactor, 6);
                    }
                    $this->db->where('id', (int)$movementRow['id'])->update('inv_stock_movement_log', $movementUpdate);
                    if ((int)($this->db->error()['code'] ?? 0) !== 0) {
                        $this->db->trans_rollback();
                        return [
                            'ok' => false,
                            'message' => 'Gagal memperbaiki movement receipt line #' . $receiptLineId . '.',
                        ];
                    }
                    $movementRowsUpdated++;
                    $receiptTouched = true;
                }

                if ($poLineChanged || $receiptLineChanged || !empty($lotRows) || !empty($movementRows)) {
                    $profileName = trim((string)($line['snapshot_item_name'] ?? ''));
                    if ($profileName === '') {
                        $profileName = trim((string)($line['snapshot_material_name'] ?? ''));
                    }
                    $profileExpiredDate = $this->normalizeDate((string)(
                        $line['receipt_expired_date']
                        ?? ($line['po_expired_date'] ?? ($line['snapshot_expired_date'] ?? ''))
                    ));
                    $stockDomain = $this->resolveLineStockDomain($line);
                    $stockMaterialId = $this->resolveLineMaterialIdForStock($line);
                    $this->registerVoidRollbackRebuildTarget($rebuildTargets, $scope, $rebuildStartDate, [
                        'stock_domain' => $stockDomain,
                        'division_id' => $scope === 'DIVISION' ? $divisionId : null,
                        'destination_type' => $scope === 'DIVISION' ? $destinationType : null,
                        'item_id' => $this->nullableInt($line['item_id'] ?? null),
                        'material_id' => $stockMaterialId,
                        'buy_uom_id' => $this->nullableInt($line['buy_uom_id'] ?? null),
                        'content_uom_id' => $this->nullableInt($line['content_uom_id'] ?? null),
                        'profile_key' => $this->nullableString($line['profile_key'] ?? null),
                        'profile_name' => $this->nullableString($profileName !== '' ? $profileName : null),
                        'profile_brand' => $this->nullableString(($line['brand_name'] ?? null) ?: ($line['snapshot_brand_name'] ?? null)),
                        'profile_description' => $this->nullableString(($line['line_description'] ?? null) ?: ($line['snapshot_line_description'] ?? null)),
                        'profile_expired_date' => $profileExpiredDate,
                        'profile_content_per_buy' => round($canonicalFactor, 6),
                        'profile_buy_uom_code' => $this->nullableString($line['snapshot_buy_uom_code'] ?? null),
                        'profile_content_uom_code' => $this->nullableString($line['snapshot_content_uom_code'] ?? null),
                        'unit_cost' => $expectedUnitCost,
                    ]);
                }
            }

            if ($receiptTouched) {
                $existingNotes = trim((string)($receipt['notes'] ?? ''));
                $repairNote = 'Repair konversi isi receipt diselaraskan dari content_per_buy PO';
                if (stripos($existingNotes, $repairNote) === false) {
                    $newNotes = $existingNotes !== '' ? ($existingNotes . ' | ' . $repairNote) : $repairNote;
                    $this->db->where('id', $receiptId)->update('pur_purchase_receipt', ['notes' => $newNotes]);
                    if ((int)($this->db->error()['code'] ?? 0) !== 0) {
                        $this->db->trans_rollback();
                        return [
                            'ok' => false,
                            'message' => 'Gagal menyimpan catatan repair di receipt #' . (string)($receipt['receipt_no'] ?? $receiptId) . '.',
                        ];
                    }
                }
                $receiptsTouched++;
            }
        }

        $catalogNormalization = ['ok' => true, 'data' => ['rows_updated' => 0]];
        if ($normalizeCatalog && !empty($catalogProfileKeys)) {
            $catalogNormalization = $this->normalizePurchaseCatalogConversion([
                'profile_keys' => array_values($catalogProfileKeys),
            ], false);
            if (!($catalogNormalization['ok'] ?? false)) {
                $this->db->trans_rollback();
                return $catalogNormalization;
            }
        }

        foreach ($rebuildTargets as $target) {
            $rebuild = $this->rebuild_inventory_history_for_identity(
                (string)$target['scope'],
                (string)$target['start_date'],
                (array)$target['identity']
            );
            if (!($rebuild['ok'] ?? false)) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => (string)($rebuild['message'] ?? 'Gagal rebuild histori stok setelah repair receipt.'),
                ];
            }
        }

        if ($this->db->table_exists('aud_transaction_log') && $receiptsTouched > 0) {
            $this->db->insert('aud_transaction_log', [
                'module_code' => 'PURCHASE',
                'action_code' => 'RECEIPT_CONVERSION_REPAIR',
                'entity_table' => 'pur_purchase_order',
                'entity_id' => $purchaseOrderId,
                'transaction_no' => (string)($order['po_no'] ?? null),
                'ref_table' => 'pur_purchase_order',
                'ref_id' => $purchaseOrderId,
                'actor_user_id' => $userId > 0 ? $userId : null,
                'after_payload' => json_encode([
                    'po_lines_updated' => $poLinesUpdated,
                    'receipt_lines_updated' => $receiptLinesUpdated,
                    'lot_rows_updated' => $lotRowsUpdated,
                    'movement_rows_updated' => $movementRowsUpdated,
                    'catalog_rows_normalized' => (int)($catalogNormalization['data']['rows_updated'] ?? 0),
                ]),
                'notes' => 'Repair konversi isi receipt dari content_per_buy PO',
            ]);
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => 'Gagal commit repair konversi receipt PO.',
            ];
        }

        $this->db->trans_commit();

        return [
            'ok' => true,
            'message' => $receiptsTouched > 0
                ? ('Repair receipt PO selesai. Receipt diperbaiki: ' . $receiptsTouched . '.')
                : 'Tidak ada mismatch receipt yang perlu direpair.',
            'data' => [
                'purchase_order_id' => $purchaseOrderId,
                'po_lines_updated' => $poLinesUpdated,
                'receipt_lines_updated' => $receiptLinesUpdated,
                'lot_rows_updated' => $lotRowsUpdated,
                'movement_rows_updated' => $movementRowsUpdated,
                'receipts_touched' => $receiptsTouched,
                'rebuild_targets' => count($rebuildTargets),
                'catalog_rows_normalized' => (int)($catalogNormalization['data']['rows_updated'] ?? 0),
            ],
        ];
    }

    public function repairPostedReceiptProfileKeys(int $purchaseOrderId, int $userId = 0): array
    {
        if (
            $purchaseOrderId <= 0 ||
            !$this->db->table_exists('pur_purchase_order') ||
            !$this->db->table_exists('pur_purchase_order_line') ||
            !$this->db->table_exists('pur_purchase_receipt') ||
            !$this->db->table_exists('pur_purchase_receipt_line') ||
            !$this->db->table_exists('inv_stock_movement_log')
        ) {
            return [
                'ok' => false,
                'message' => 'Schema purchase/receipt/movement belum lengkap untuk repair profile key receipt.',
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

        $receipts = $this->db
            ->select('id, receipt_no, receipt_date, destination_type, destination_division_id, status, notes')
            ->from('pur_purchase_receipt')
            ->where('purchase_order_id', $purchaseOrderId)
            ->where('status', 'POSTED')
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();
        if (empty($receipts)) {
            return [
                'ok' => false,
                'message' => 'Tidak ada receipt POSTED untuk PO ini.',
            ];
        }

        $this->db->trans_begin();

        $receiptLinesUpdated = 0;
        $lotRowsUpdated = 0;
        $movementRowsUpdated = 0;
        $receiptsTouched = 0;
        $rebuildTargets = [];
        $hasLotProfileKey = $this->db->table_exists('inv_material_fifo_lot') && $this->db->field_exists('profile_key', 'inv_material_fifo_lot');

        foreach ($receipts as $receipt) {
            $receiptId = (int)($receipt['id'] ?? 0);
            if ($receiptId <= 0) {
                continue;
            }

            $scope = strtoupper((string)($receipt['destination_type'] ?? '')) === 'GUDANG' ? 'WAREHOUSE' : 'DIVISION';
            $divisionId = $scope === 'DIVISION' ? $this->nullableInt($receipt['destination_division_id'] ?? null) : null;
            $destinationType = $scope === 'DIVISION' ? $this->normalizeDestination((string)($receipt['destination_type'] ?? '')) : null;
            if ($scope === 'DIVISION' && $divisionId === null) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => 'Repair profile key gagal: division tujuan tidak valid untuk receipt #' . (string)($receipt['receipt_no'] ?? $receiptId) . '.',
                ];
            }

            $receiptDate = $this->normalizeDate((string)($receipt['receipt_date'] ?? ''));
            if ($receiptDate === null) {
                $receiptDate = date('Y-m-d');
            }
            $rebuildStartDate = date('Y-m-01', strtotime($receiptDate));

            $lineQb = $this->db
                ->select('rl.id AS receipt_line_id, rl.purchase_order_line_id, rl.line_kind, rl.item_id, rl.material_id, rl.profile_key AS receipt_profile_key')
                ->select('rl.qty_buy_received, rl.qty_content_received, rl.buy_uom_id, rl.content_uom_id, rl.brand_name, rl.line_description')
                ->select('pol.line_no, pol.profile_key AS po_profile_key, pol.unit_price, pol.content_per_buy')
                ->select('pol.snapshot_item_name, pol.snapshot_material_name, pol.snapshot_brand_name, pol.snapshot_line_description')
                ->select('pol.snapshot_buy_uom_code, pol.snapshot_content_uom_code')
                ->from('pur_purchase_receipt_line rl')
                ->join('pur_purchase_order_line pol', 'pol.id = rl.purchase_order_line_id', 'inner')
                ->where('rl.purchase_receipt_id', $receiptId)
                ->order_by('rl.id', 'ASC');

            if ($this->db->field_exists('expired_date', 'pur_purchase_receipt_line')) {
                $lineQb->select('rl.expired_date AS receipt_expired_date');
            }
            if ($this->db->field_exists('expired_date', 'pur_purchase_order_line')) {
                $lineQb->select('pol.expired_date AS po_expired_date');
            }
            if ($this->db->field_exists('snapshot_expired_date', 'pur_purchase_order_line')) {
                $lineQb->select('pol.snapshot_expired_date');
            }

            $lines = $lineQb->get()->result_array();
            $receiptTouched = false;

            foreach ($lines as $line) {
                $receiptLineId = (int)($line['receipt_line_id'] ?? 0);
                $poLineId = (int)($line['purchase_order_line_id'] ?? 0);
                if ($receiptLineId <= 0 || $poLineId <= 0) {
                    continue;
                }

                $profileName = trim((string)($line['snapshot_item_name'] ?? ''));
                if ($profileName === '') {
                    $profileName = trim((string)($line['snapshot_material_name'] ?? ''));
                }
                $profileExpiredDate = $this->normalizeDate((string)(
                    $line['receipt_expired_date']
                    ?? ($line['po_expired_date'] ?? ($line['snapshot_expired_date'] ?? ''))
                ));

                $expectedProfileKey = $this->resolveCatalogProfileKeyByIdentity(
                    (int)($line['item_id'] ?? 0),
                    $this->nullableInt($line['material_id'] ?? null),
                    (int)($line['buy_uom_id'] ?? 0),
                    $this->nullableInt($line['content_uom_id'] ?? null) ?? (int)($line['buy_uom_id'] ?? 0),
                    $profileName !== '' ? $profileName : null,
                    $this->nullableString($line['snapshot_brand_name'] ?? null),
                    $this->nullableString($line['snapshot_line_description'] ?? null),
                    $profileExpiredDate ?: null,
                    (float)($line['content_per_buy'] ?? 1),
                    (float)($line['unit_price'] ?? 0)
                );
                if ($expectedProfileKey === null) {
                    $expectedProfileKey = $this->nullableString($line['po_profile_key'] ?? null);
                }

                $currentProfileKey = $this->nullableString($line['receipt_profile_key'] ?? null);
                if ($expectedProfileKey === null || $expectedProfileKey === $currentProfileKey) {
                    continue;
                }

                $lotRows = [];
                if ($hasLotProfileKey) {
                    $lotRows = $this->db
                        ->select('id, qty_in, qty_out, qty_balance')
                        ->from('inv_material_fifo_lot')
                        ->where('receipt_line_id', $receiptLineId)
                        ->order_by('id', 'ASC')
                        ->get()
                        ->result_array();
                    foreach ($lotRows as $lot) {
                        $qtyOut = round((float)($lot['qty_out'] ?? 0), 4);
                        $qtyIn = round((float)($lot['qty_in'] ?? 0), 4);
                        $qtyBalance = round((float)($lot['qty_balance'] ?? 0), 4);
                        if ($qtyOut > 0.0001 || abs($qtyBalance - $qtyIn) > 0.0001) {
                            $this->db->trans_rollback();
                            return [
                                'ok' => false,
                                'message' => 'Repair profile key ditolak karena lot inbound receipt line #' . $receiptLineId . ' sudah terpakai / berubah.',
                            ];
                        }
                    }
                }

                $movementRows = $this->db
                    ->select('id')
                    ->from('inv_stock_movement_log')
                    ->where('ref_table', 'pur_purchase_receipt')
                    ->where('receipt_id', $receiptId)
                    ->where('receipt_line_id', $receiptLineId)
                    ->order_by('id', 'ASC')
                    ->get()
                    ->result_array();
                if (empty($movementRows)) {
                    $this->db->trans_rollback();
                    return [
                        'ok' => false,
                        'message' => 'Repair profile key gagal: movement row tidak ditemukan untuk receipt line #' . $receiptLineId . '.',
                    ];
                }

                $this->db
                    ->where('id', $receiptLineId)
                    ->update('pur_purchase_receipt_line', ['profile_key' => $expectedProfileKey]);
                if ((int)($this->db->error()['code'] ?? 0) !== 0) {
                    $this->db->trans_rollback();
                    return [
                        'ok' => false,
                        'message' => 'Gagal mengupdate profile_key receipt line #' . $receiptLineId . '.',
                    ];
                }
                $receiptLinesUpdated++;
                $receiptTouched = true;

                if ($hasLotProfileKey && !empty($lotRows)) {
                    $lotIds = array_values(array_filter(array_map(static function (array $lot): int {
                        return (int)($lot['id'] ?? 0);
                    }, $lotRows)));
                    if (!empty($lotIds)) {
                        $this->db->where_in('id', $lotIds)->update('inv_material_fifo_lot', ['profile_key' => $expectedProfileKey]);
                        if ((int)($this->db->error()['code'] ?? 0) !== 0) {
                            $this->db->trans_rollback();
                            return [
                                'ok' => false,
                                'message' => 'Gagal mengupdate profile_key lot receipt line #' . $receiptLineId . '.',
                            ];
                        }
                        $lotRowsUpdated += count($lotIds);
                    }
                }

                $movementIds = array_values(array_filter(array_map(static function (array $row): int {
                    return (int)($row['id'] ?? 0);
                }, $movementRows)));
                if (!empty($movementIds)) {
                    $this->db->where_in('id', $movementIds)->update('inv_stock_movement_log', ['profile_key' => $expectedProfileKey]);
                    if ((int)($this->db->error()['code'] ?? 0) !== 0) {
                        $this->db->trans_rollback();
                        return [
                            'ok' => false,
                            'message' => 'Gagal mengupdate profile_key movement receipt line #' . $receiptLineId . '.',
                        ];
                    }
                    $movementRowsUpdated += count($movementIds);
                }

                $baseIdentity = [
                    'stock_domain' => $this->resolveLineStockDomain($line),
                    'division_id' => $scope === 'DIVISION' ? $divisionId : null,
                    'destination_type' => $scope === 'DIVISION' ? $destinationType : null,
                    'item_id' => $this->nullableInt($line['item_id'] ?? null),
                    'material_id' => $this->resolveLineMaterialIdForStock($line),
                    'buy_uom_id' => $this->nullableInt($line['buy_uom_id'] ?? null),
                    'content_uom_id' => $this->nullableInt($line['content_uom_id'] ?? null),
                    'profile_name' => $this->nullableString($profileName !== '' ? $profileName : null),
                    'profile_brand' => $this->nullableString(($line['brand_name'] ?? null) ?: ($line['snapshot_brand_name'] ?? null)),
                    'profile_description' => $this->nullableString(($line['line_description'] ?? null) ?: ($line['snapshot_line_description'] ?? null)),
                    'profile_expired_date' => $profileExpiredDate,
                    'profile_content_per_buy' => round((float)($line['content_per_buy'] ?? 1), 6),
                    'profile_buy_uom_code' => $this->nullableString($line['snapshot_buy_uom_code'] ?? null),
                    'profile_content_uom_code' => $this->nullableString($line['snapshot_content_uom_code'] ?? null),
                ];

                $this->registerVoidRollbackRebuildTarget($rebuildTargets, $scope, $rebuildStartDate, array_merge($baseIdentity, [
                    'profile_key' => $currentProfileKey,
                ]));
                $this->registerVoidRollbackRebuildTarget($rebuildTargets, $scope, $rebuildStartDate, array_merge($baseIdentity, [
                    'profile_key' => $expectedProfileKey,
                ]));
            }

            if ($receiptTouched) {
                $existingNotes = trim((string)($receipt['notes'] ?? ''));
                $repairNote = 'Repair profile_key receipt diselaraskan dari identity PO';
                if (stripos($existingNotes, $repairNote) === false) {
                    $newNotes = $existingNotes !== '' ? ($existingNotes . ' | ' . $repairNote) : $repairNote;
                    $this->db->where('id', $receiptId)->update('pur_purchase_receipt', ['notes' => $newNotes]);
                    if ((int)($this->db->error()['code'] ?? 0) !== 0) {
                        $this->db->trans_rollback();
                        return [
                            'ok' => false,
                            'message' => 'Gagal menyimpan catatan repair profile_key di receipt #' . (string)($receipt['receipt_no'] ?? $receiptId) . '.',
                        ];
                    }
                }
                $receiptsTouched++;
            }
        }

        foreach ($rebuildTargets as $target) {
            $rebuild = $this->rebuild_inventory_history_for_identity(
                (string)$target['scope'],
                (string)$target['start_date'],
                (array)$target['identity']
            );
            if (!($rebuild['ok'] ?? false)) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => (string)($rebuild['message'] ?? 'Gagal rebuild histori stok setelah repair profile key receipt.'),
                ];
            }
        }

        if ($this->db->table_exists('aud_transaction_log') && $receiptsTouched > 0) {
            $this->db->insert('aud_transaction_log', [
                'module_code' => 'PURCHASE',
                'action_code' => 'RECEIPT_PROFILE_KEY_REPAIR',
                'entity_table' => 'pur_purchase_order',
                'entity_id' => $purchaseOrderId,
                'transaction_no' => (string)($order['po_no'] ?? null),
                'ref_table' => 'pur_purchase_order',
                'ref_id' => $purchaseOrderId,
                'actor_user_id' => $userId > 0 ? $userId : null,
                'after_payload' => json_encode([
                    'receipt_lines_updated' => $receiptLinesUpdated,
                    'lot_rows_updated' => $lotRowsUpdated,
                    'movement_rows_updated' => $movementRowsUpdated,
                    'rebuild_targets' => count($rebuildTargets),
                ]),
                'notes' => 'Repair profile_key receipt dari identity purchase order',
            ]);
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return [
                'ok' => false,
                'message' => 'Gagal commit repair profile key receipt PO.',
            ];
        }

        $this->db->trans_commit();

        return [
            'ok' => true,
            'message' => $receiptsTouched > 0
                ? ('Repair profile key receipt PO selesai. Receipt diperbaiki: ' . $receiptsTouched . '.')
                : 'Tidak ada mismatch profile_key receipt yang perlu direpair.',
            'data' => [
                'purchase_order_id' => $purchaseOrderId,
                'receipt_lines_updated' => $receiptLinesUpdated,
                'lot_rows_updated' => $lotRowsUpdated,
                'movement_rows_updated' => $movementRowsUpdated,
                'receipts_touched' => $receiptsTouched,
                'rebuild_targets' => count($rebuildTargets),
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
            ->select('id, receipt_no, receipt_date, destination_type, destination_division_id, notes')
            ->from('pur_purchase_receipt')
            ->where('purchase_order_id', $purchaseOrderId)
            ->where('status', 'POSTED')
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();

        if (empty($receipts)) {
            return ['ok' => true, 'data' => ['receipts_voided' => 0, 'lines_reversed' => 0]];
        }

        $receiptsVoided = 0;
        $linesReversed = 0;
        $availabilityMaterialIds = [];
        $receiptLinesById = [];
        $requirements = [];
        $rebuildTargets = [];

        $this->load->library('MaterialFifoManager');

        foreach ($receipts as $receipt) {
            $receiptId = (int)($receipt['id'] ?? 0);
            if ($receiptId <= 0) {
                continue;
            }

            $scope = strtoupper((string)($receipt['destination_type'] ?? '')) === 'GUDANG' ? 'WAREHOUSE' : 'DIVISION';
            $divisionId = $scope === 'DIVISION' ? $this->nullableInt($receipt['destination_division_id'] ?? null) : null;
            $destinationType = $scope === 'DIVISION' ? $this->normalizeDestination((string)($receipt['destination_type'] ?? '')) : null;
            if ($scope === 'DIVISION' && $divisionId === null) {
                return [
                    'ok' => false,
                    'message' => 'Rollback receipt gagal: division tujuan tidak valid untuk receipt #' . (string)($receipt['receipt_no'] ?? $receiptId),
                ];
            }

            $rbQb = $this->db
                ->select('rl.*, pol.line_no, pol.unit_price, pol.content_per_buy, pol.snapshot_item_name, pol.snapshot_material_name')
                ->select('pol.snapshot_brand_name, pol.snapshot_line_description, pol.snapshot_buy_uom_code, pol.snapshot_content_uom_code')
                ->from('pur_purchase_receipt_line rl')
                ->join('pur_purchase_order_line pol', 'pol.id = rl.purchase_order_line_id', 'left')
                ->where('rl.purchase_receipt_id', $receiptId)
                ->order_by('rl.id', 'ASC');

            if ($this->db->field_exists('expired_date', 'pur_purchase_order_line')) {
                $rbQb->select('pol.expired_date AS po_expired_date');
            }
            if ($this->db->field_exists('snapshot_expired_date', 'pur_purchase_order_line')) {
                $rbQb->select('pol.snapshot_expired_date AS po_snapshot_expired_date');
            }
            if ($this->hasPurchaseReceiptUsagePurposeColumn()) {
                $rbQb->select('rl.usage_purpose');
            } elseif ($this->hasPurchaseOrderUsagePurposeColumn()) {
                $rbQb->select('pol.usage_purpose');
            }

            $lines = $rbQb->get()->result_array();

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
                    'destination_type' => $destinationType,
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
                        'destination_type' => $req['destination_type'],
                        'profile_key' => $req['profile_key'],
                        'profile_name' => $profileName,
                        'profile_content_per_buy' => (float)($line['content_per_buy'] ?? ($line['conversion_factor_to_content'] ?? 0)),
                        'qty_buy_needed' => 0.0,
                        'qty_content_needed' => 0.0,
                        'refs' => [],
                        'receipt_ids' => [],
                        'receipt_line_ids' => [],
                    ];
                }

                $requirements[$reqKey]['qty_buy_needed'] = round((float)$requirements[$reqKey]['qty_buy_needed'] + $qtyBuy, 4);
                $requirements[$reqKey]['qty_content_needed'] = round((float)$requirements[$reqKey]['qty_content_needed'] + $qtyContent, 4);
                if ((float)($requirements[$reqKey]['profile_content_per_buy'] ?? 0) <= 0) {
                    $requirements[$reqKey]['profile_content_per_buy'] = (float)($line['content_per_buy'] ?? ($line['conversion_factor_to_content'] ?? 0));
                }
                $requirements[$reqKey]['receipt_ids'][$receiptId] = $receiptId;
                $receiptLineId = (int)($line['id'] ?? 0);
                if ($receiptLineId > 0) {
                    $requirements[$reqKey]['receipt_line_ids'][$receiptLineId] = $receiptLineId;
                }

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
            $destinationType = $scope === 'DIVISION' ? $this->normalizeDestination((string)($receipt['destination_type'] ?? '')) : null;
            if ($scope === 'DIVISION' && $divisionId === null) {
                return [
                    'ok' => false,
                    'message' => 'Rollback receipt gagal: division tujuan tidak valid untuk receipt #' . (string)($receipt['receipt_no'] ?? $receiptId),
                ];
            }

            $lines = (array)($receiptLinesById[$receiptId] ?? []);
            $receiptDate = $this->normalizeDate((string)($receipt['receipt_date'] ?? ''));
            if ($receiptDate === null) {
                $receiptDate = date('Y-m-d');
            }
            $rebuildStartDate = date('Y-m-01', strtotime($receiptDate));

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
                $profileExpiredDate = $this->normalizeDate((string)(
                    $line['expired_date']
                    ?? ($line['po_expired_date'] ?? ($line['po_snapshot_expired_date'] ?? ''))
                ));
                $stockDomain = $this->resolveLineStockDomain($line);
                $stockMaterialId = $this->resolveLineMaterialIdForStock($line);

                if ($stockMaterialId !== null && $stockMaterialId > 0) {
                    $lotRollback = $this->materialfifomanager->rollbackReceiptInboundLotsBySource(
                        'pur_purchase_receipt',
                        $receiptId,
                        (int)($line['id'] ?? 0)
                    );
                    if (!($lotRollback['ok'] ?? false)) {
                        return [
                            'ok' => false,
                            'message' => 'Rollback lot receipt gagal untuk receipt #' . (string)($receipt['receipt_no'] ?? $receiptId) . ': ' . (string)($lotRollback['message'] ?? 'gagal rollback lot inbound'),
                        ];
                    }
                }

                $this->registerVoidRollbackRebuildTarget($rebuildTargets, $scope, $rebuildStartDate, [
                    'stock_domain' => $stockDomain,
                    'division_id' => $scope === 'DIVISION' ? $divisionId : null,
                    'destination_type' => $scope === 'DIVISION' ? $destinationType : null,
                    'item_id' => $this->nullableInt($line['item_id'] ?? null),
                    'material_id' => $stockMaterialId,
                    'buy_uom_id' => $this->nullableInt($line['buy_uom_id'] ?? null),
                    'content_uom_id' => $this->nullableInt($line['content_uom_id'] ?? null),
                    'profile_key' => $this->nullableString($line['profile_key'] ?? null),
                    'profile_name' => $this->nullableString($profileName !== '' ? $profileName : null),
                    'profile_brand' => $this->nullableString(($line['brand_name'] ?? null) ?: ($line['snapshot_brand_name'] ?? null)),
                    'profile_description' => $this->nullableString(($line['line_description'] ?? null) ?: ($line['snapshot_line_description'] ?? null)),
                    'profile_expired_date' => $profileExpiredDate,
                    'profile_content_per_buy' => (float)($line['content_per_buy'] ?? $factor),
                    'profile_buy_uom_code' => $this->nullableString($line['snapshot_buy_uom_code'] ?? null),
                    'profile_content_uom_code' => $this->nullableString($line['snapshot_content_uom_code'] ?? null),
                    'unit_cost' => $unitCost,
                ]);
                $this->registerVoidRollbackRelatedBalanceTargets(
                    $rebuildTargets,
                    $scope,
                    $rebuildStartDate,
                    [
                        'stock_domain' => $stockDomain,
                        'division_id' => $scope === 'DIVISION' ? $divisionId : null,
                        'destination_type' => $scope === 'DIVISION' ? $destinationType : null,
                        'item_id' => $this->nullableInt($line['item_id'] ?? null),
                        'material_id' => $stockMaterialId,
                        'buy_uom_id' => $this->nullableInt($line['buy_uom_id'] ?? null),
                        'content_uom_id' => $this->nullableInt($line['content_uom_id'] ?? null),
                        'profile_key' => $this->nullableString($line['profile_key'] ?? null),
                        'profile_name' => $this->nullableString($profileName !== '' ? $profileName : null),
                        'profile_brand' => $this->nullableString(($line['brand_name'] ?? null) ?: ($line['snapshot_brand_name'] ?? null)),
                        'profile_description' => $this->nullableString(($line['line_description'] ?? null) ?: ($line['snapshot_line_description'] ?? null)),
                        'profile_expired_date' => $profileExpiredDate,
                        'profile_content_per_buy' => (float)($line['content_per_buy'] ?? $factor),
                        'profile_buy_uom_code' => $this->nullableString($line['snapshot_buy_uom_code'] ?? null),
                        'profile_content_uom_code' => $this->nullableString($line['snapshot_content_uom_code'] ?? null),
                    ],
                    (int)($line['id'] ?? 0)
                );

                $availabilityMaterialId = $this->resolveAvailabilityMaterialIdFromLine($line);
                if ($availabilityMaterialId > 0) {
                    $availabilityMaterialIds[$availabilityMaterialId] = $availabilityMaterialId;
                }

                $linesReversed++;
            }

            if ($this->db->table_exists('inv_stock_movement_log')) {
                $this->db
                    ->where('ref_table', 'pur_purchase_receipt')
                    ->where('ref_id', $receiptId)
                    ->delete('inv_stock_movement_log');
                if ((int)($this->db->error()['code'] ?? 0) !== 0) {
                    return [
                        'ok' => false,
                        'message' => 'Rollback receipt gagal saat menghapus histori movement receipt.',
                    ];
                }
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
                    'notes' => 'Histori receipt dihapus otomatis saat VOID PO',
                ]);
            }

            $receiptsVoided++;
        }

        foreach ($rebuildTargets as $target) {
            $rebuild = $this->rebuild_inventory_history_for_identity(
                (string)$target['scope'],
                (string)$target['start_date'],
                (array)$target['identity']
            );
            if (!($rebuild['ok'] ?? false)) {
                $message = (string)($rebuild['message'] ?? 'Gagal rebuild histori stok setelah VOID PO.');
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
            'data' => [
                'receipts_voided' => $receiptsVoided,
                'lines_reversed' => $linesReversed,
                'rebuild_targets' => count($rebuildTargets),
                'material_ids' => array_values($availabilityMaterialIds),
            ],
        ];
    }

    private function resolveAvailabilityMaterialIdFromLine(array $line): int
    {
        if ($this->resolveLineUsagePurpose($line) === self::USAGE_PURPOSE_OPERATIONAL) {
            return 0;
        }

        $materialId = (int)($line['material_id'] ?? 0);
        if ($materialId > 0) {
            return $materialId;
        }

        $itemId = (int)($line['item_id'] ?? 0);
        if ($itemId <= 0 || !$this->db->table_exists('mst_item') || !$this->db->field_exists('material_id', 'mst_item')) {
            return 0;
        }

        $item = $this->db
            ->select('material_id')
            ->from('mst_item')
            ->where('id', $itemId)
            ->limit(1)
            ->get()
            ->row_array();

        return (int)($item['material_id'] ?? 0);
    }

    private function trigger_pos_availability_refresh_for_materials(array $materialIds, array $context = []): array
    {
        $materialIds = array_values(array_unique(array_filter(array_map('intval', $materialIds))));
        if (empty($materialIds)) {
            return [
                'ok' => true,
                'material_ids' => [],
                'success_count' => 0,
                'failed_count' => 0,
                'results' => [],
            ];
        }

        $this->load->library('PosAvailabilityRebuildService');

        $results = [];
        $success = 0;
        $failed = 0;
        foreach ($materialIds as $materialId) {
            $result = $this->posavailabilityrebuildservice->handle_material_change($materialId, $context + [
                'material_id' => $materialId,
            ]);
            $results[] = ['material_id' => $materialId] + $result;
            if ($result['ok'] ?? false) {
                $success++;
            } else {
                $failed++;
            }
        }

        return [
            'ok' => $failed === 0,
            'material_ids' => $materialIds,
            'success_count' => $success,
            'failed_count' => $failed,
            'results' => $results,
        ];
    }

    private function registerVoidRollbackRebuildTarget(array &$targets, string $scope, string $startDate, array $identity): void
    {
        $scope = strtoupper(trim($scope));
        $normalizedStartDate = $this->normalizeDate($startDate);
        if ($normalizedStartDate === null) {
            $normalizedStartDate = date('Y-m-01');
        }

        $key = implode('|', [
            $scope,
            (string)($identity['division_id'] ?? 0),
            strtoupper((string)($identity['destination_type'] ?? 'OTHER')),
            (string)($identity['item_id'] ?? 0),
            (string)($identity['material_id'] ?? 0),
            (string)($identity['buy_uom_id'] ?? 0),
            (string)($identity['content_uom_id'] ?? 0),
            strtoupper((string)($identity['profile_key'] ?? '')),
            strtoupper(trim((string)($identity['profile_name'] ?? ''))),
            strtoupper(trim((string)($identity['profile_brand'] ?? ''))),
            strtoupper(trim((string)($identity['profile_description'] ?? ''))),
        ]);

        if (!isset($targets[$key])) {
            $targets[$key] = [
                'scope' => $scope,
                'start_date' => $normalizedStartDate,
                'identity' => $identity,
            ];
            return;
        }

        if ($normalizedStartDate < (string)($targets[$key]['start_date'] ?? $normalizedStartDate)) {
            $targets[$key]['start_date'] = $normalizedStartDate;
        }
    }

    private function registerVoidRollbackRelatedBalanceTargets(array &$targets, string $scope, string $startDate, array $identity, int $receiptLineId): void
    {
        if ($receiptLineId <= 0) {
            return;
        }

        $scope = strtoupper(trim($scope));
        if (!$this->db->table_exists('inv_stock_movement_log') || !$this->db->field_exists('receipt_line_id', 'inv_stock_movement_log')) {
            return;
        }

        $hasMovementStockDomain = $this->db->field_exists('stock_domain', 'inv_stock_movement_log');
        $hasMovementDestination = $scope === 'DIVISION' && $this->db->field_exists('destination_type', 'inv_stock_movement_log');
        $hasMovementProfileExpiredDate = $this->db->field_exists('profile_expired_date', 'inv_stock_movement_log');
        $movementColumns = [
            'division_id',
            'item_id',
            'material_id',
            'buy_uom_id',
            'content_uom_id',
            'profile_key',
            'profile_name',
            'profile_brand',
            'profile_description',
            'profile_content_per_buy',
            'profile_buy_uom_code',
            'profile_content_uom_code',
        ];
        $movementColumns[] = $hasMovementStockDomain ? 'stock_domain' : 'NULL AS stock_domain';
        $movementColumns[] = $hasMovementProfileExpiredDate ? 'profile_expired_date' : 'NULL AS profile_expired_date';
        if ($hasMovementDestination) {
            $movementColumns[] = 'destination_type';
        }

        $qb = $this->db
            ->select(implode(', ', $movementColumns), false)
            ->from('inv_stock_movement_log')
            ->where('movement_scope', $scope)
            ->where('receipt_line_id', $receiptLineId);
        if ($scope === 'DIVISION') {
            $qb->where('division_id', $identity['division_id'] ?? 0);
            if ($hasMovementDestination) {
                $qb->where('destination_type', $identity['destination_type'] ?? 'OTHER');
            }
        }

        $rows = $qb->order_by('id', 'ASC')->get()->result_array();
        foreach ($rows as $row) {
            $this->registerVoidRollbackRebuildTarget($targets, $scope, $startDate, [
                'stock_domain' => strtoupper((string)($row['stock_domain'] ?? ($identity['stock_domain'] ?? 'ITEM'))),
                'division_id' => $scope === 'DIVISION' ? $this->nullableInt($row['division_id'] ?? null) : null,
                'destination_type' => $scope === 'DIVISION' ? $this->normalizeDestination((string)($row['destination_type'] ?? ($identity['destination_type'] ?? ''))) : null,
                'item_id' => $this->nullableInt($row['item_id'] ?? ($identity['item_id'] ?? null)),
                'material_id' => $this->nullableInt($row['material_id'] ?? ($identity['material_id'] ?? null)),
                'buy_uom_id' => $this->nullableInt($row['buy_uom_id'] ?? ($identity['buy_uom_id'] ?? null)),
                'content_uom_id' => $this->nullableInt($row['content_uom_id'] ?? ($identity['content_uom_id'] ?? null)),
                'profile_key' => $this->nullableString($row['profile_key'] ?? ($identity['profile_key'] ?? null)),
                'profile_name' => $this->nullableString($row['profile_name'] ?? ($identity['profile_name'] ?? null)),
                'profile_brand' => $this->nullableString($row['profile_brand'] ?? ($identity['profile_brand'] ?? null)),
                'profile_description' => $this->nullableString($row['profile_description'] ?? ($identity['profile_description'] ?? null)),
                'profile_expired_date' => $this->normalizeDate((string)($row['profile_expired_date'] ?? ($identity['profile_expired_date'] ?? ''))),
                'profile_content_per_buy' => round((float)($row['profile_content_per_buy'] ?? ($identity['profile_content_per_buy'] ?? 0)), 6),
                'profile_buy_uom_code' => $this->nullableString($row['profile_buy_uom_code'] ?? ($identity['profile_buy_uom_code'] ?? null)),
                'profile_content_uom_code' => $this->nullableString($row['profile_content_uom_code'] ?? ($identity['profile_content_uom_code'] ?? null)),
            ]);
        }
    }

    private function purgeInventoryHistoryArtifactsForIdentity(string $rollupTable, string $balanceTable, string $stockScope, array $identity): void
    {
        if ($this->db->table_exists($rollupTable)) {
            $this->applyDailyRollupIdentityFilter($rollupTable, $stockScope, $identity);
            $this->db->delete($rollupTable);
        }

        $this->purgeInventoryMonthlyStockForIdentity($stockScope, $identity, null);

        if (!$this->db->table_exists($balanceTable)) {
            return;
        }

        if ($stockScope === 'WAREHOUSE') {
            $this->db->where('item_id', $identity['item_id'] ?? null)
                ->where('buy_uom_id', $identity['buy_uom_id'] ?? null)
                ->where('content_uom_id', $identity['content_uom_id'] ?? null);
            $profileKey = $identity['profile_key'] ?? null;
            if ($profileKey !== null && $profileKey !== '') {
                $this->db->where('profile_key', $profileKey);
            } else {
                $this->db->where('profile_key IS NULL', null, false);
            }
            $this->db->delete($balanceTable);
            return;
        }

        $this->db->where('division_id', $identity['division_id'] ?? null)
            ->where('item_id', $identity['item_id'] ?? null)
            ->where('content_uom_id', $identity['content_uom_id'] ?? null);

        $materialId = $identity['material_id'] ?? null;
        if ($materialId !== null) {
            $this->db->where('material_id', $materialId);
        } else {
            $this->db->where('material_id IS NULL', null, false);
        }

        $buyUomId = $identity['buy_uom_id'] ?? null;
        if ($buyUomId !== null) {
            $this->db->where('buy_uom_id', $buyUomId);
        } else {
            $this->db->where('buy_uom_id IS NULL', null, false);
        }

        if ($this->db->field_exists('destination_type', $balanceTable)) {
            $this->db->where('destination_type', $identity['destination_type'] ?? 'OTHER');
        }

        $profileKey = $identity['profile_key'] ?? null;
        if ($profileKey !== null && $profileKey !== '') {
            $this->db->where('profile_key', $profileKey);
        } else {
            $this->db->where('profile_key IS NULL', null, false);
        }

        $this->db->delete($balanceTable);
    }

    private function buildVoidRollbackRequirementKey(array $requirement): string
    {
        return implode('|', [
            strtoupper((string)($requirement['scope'] ?? '')),
            (string)($requirement['division_id'] ?? 0),
            strtoupper((string)($requirement['destination_type'] ?? '')),
            (string)($requirement['item_id'] ?? 0),
            (string)($requirement['material_id'] ?? 0),
            (string)($requirement['buy_uom_id'] ?? 0),
            (string)($requirement['content_uom_id'] ?? 0),
            strtoupper((string)($requirement['profile_key'] ?? '')),
        ]);
    }

    private function fetchVoidRollbackCurrentBalance(array $requirement): array
    {
        $lotBalance = $this->fetchVoidRollbackLotBalance($requirement);
        if ($lotBalance !== null) {
            return $lotBalance;
        }

        $scope = strtoupper((string)($requirement['scope'] ?? ''));
        if ($scope === 'WAREHOUSE') {
            if ($this->db->table_exists('inv_warehouse_monthly_stock')) {
                $targetMonth = date('Y-m-01');
                $identityKey = $this->buildInventoryMonthlyIdentityKey([
                    'stock_domain' => $requirement['stock_domain'] ?? 'ITEM',
                    'item_id' => $requirement['item_id'] ?? null,
                    'material_id' => $requirement['material_id'] ?? null,
                    'buy_uom_id' => $requirement['buy_uom_id'] ?? null,
                    'content_uom_id' => $requirement['content_uom_id'] ?? null,
                    'profile_key' => $requirement['profile_key'] ?? null,
                    'profile_name' => $requirement['profile_name'] ?? null,
                    'profile_brand' => $requirement['profile_brand'] ?? null,
                    'profile_description' => $requirement['profile_description'] ?? null,
                    'profile_content_per_buy' => $requirement['profile_content_per_buy'] ?? 1,
                    'profile_expired_date' => $requirement['profile_expired_date'] ?? null,
                ]);
                $latestMonthSubquery = $this->db
                    ->select('identity_key, MAX(month_key) AS month_key', false)
                    ->from('inv_warehouse_monthly_stock')
                    ->where('month_key <=', $targetMonth)
                    ->group_by('identity_key')
                    ->get_compiled_select();

                $row = $this->db
                    ->select('s.closing_qty_buy AS qty_buy_balance, s.closing_qty_content AS qty_content_balance', false)
                    ->from('inv_warehouse_monthly_stock s')
                    ->join('(' . $latestMonthSubquery . ') lm', 'lm.identity_key = s.identity_key AND lm.month_key = s.month_key', 'inner', false)
                    ->where('s.identity_key', $identityKey)
                    ->limit(1)
                    ->get()
                    ->row_array();

                return [
                    'qty_buy_balance' => (float)($row['qty_buy_balance'] ?? 0),
                    'qty_content_balance' => (float)($row['qty_content_balance'] ?? 0),
                ];
            }

            return ['qty_buy_balance' => 0.0, 'qty_content_balance' => 0.0];
        }

        if ($this->db->table_exists('inv_division_monthly_stock')) {
            $targetMonth = date('Y-m-01');
            $identityKey = $this->buildInventoryMonthlyIdentityKey([
                'stock_domain' => $requirement['stock_domain'] ?? 'ITEM',
                'item_id' => $requirement['item_id'] ?? null,
                'material_id' => $requirement['material_id'] ?? null,
                'buy_uom_id' => $requirement['buy_uom_id'] ?? null,
                'content_uom_id' => $requirement['content_uom_id'] ?? null,
                'profile_key' => $requirement['profile_key'] ?? null,
                'profile_name' => $requirement['profile_name'] ?? null,
                'profile_brand' => $requirement['profile_brand'] ?? null,
                'profile_description' => $requirement['profile_description'] ?? null,
                'profile_content_per_buy' => $requirement['profile_content_per_buy'] ?? 1,
                'profile_expired_date' => $requirement['profile_expired_date'] ?? null,
            ]);
            $latestMonthSubquery = $this->db
                ->select('division_id, destination_type, identity_key, MAX(month_key) AS month_key', false)
                ->from('inv_division_monthly_stock')
                ->where('month_key <=', $targetMonth)
                ->group_by(['division_id', 'destination_type', 'identity_key'])
                ->get_compiled_select();

            $row = $this->db
                ->select('s.closing_qty_buy AS qty_buy_balance, s.closing_qty_content AS qty_content_balance', false)
                ->from('inv_division_monthly_stock s')
                ->join('(' . $latestMonthSubquery . ') lm', 'lm.division_id = s.division_id AND lm.destination_type = s.destination_type AND lm.identity_key = s.identity_key AND lm.month_key = s.month_key', 'inner', false)
                ->where('s.division_id', $requirement['division_id'] ?? null)
                ->where('s.destination_type', strtoupper((string)($requirement['destination_type'] ?? 'OTHER')))
                ->where('s.identity_key', $identityKey)
                ->limit(1)
                ->get()
                ->row_array();

            return [
                'qty_buy_balance' => (float)($row['qty_buy_balance'] ?? 0),
                'qty_content_balance' => (float)($row['qty_content_balance'] ?? 0),
            ];
        }

        return ['qty_buy_balance' => 0.0, 'qty_content_balance' => 0.0];
    }

    private function fetchVoidRollbackLotBalance(array $requirement): ?array
    {
        if (!$this->db->table_exists('inv_material_fifo_lot')) {
            return null;
        }

        $receiptIds = array_values(array_filter(array_map('intval', (array)($requirement['receipt_ids'] ?? []))));
        $receiptLineIds = array_values(array_filter(array_map('intval', (array)($requirement['receipt_line_ids'] ?? []))));
        if (empty($receiptIds) && empty($receiptLineIds)) {
            return null;
        }

        $qb = $this->db
            ->select('COUNT(*) AS row_count, COALESCE(SUM(qty_balance), 0) AS qty_content_balance', false)
            ->from('inv_material_fifo_lot')
            ->where('source_table', 'pur_purchase_receipt');

        if (!empty($receiptLineIds) && $this->db->field_exists('receipt_line_id', 'inv_material_fifo_lot')) {
            $qb->where_in('receipt_line_id', $receiptLineIds);
        } elseif (!empty($receiptLineIds) && $this->db->field_exists('source_line_id', 'inv_material_fifo_lot')) {
            $qb->where_in('source_line_id', $receiptLineIds);
        } elseif (!empty($receiptIds) && $this->db->field_exists('receipt_id', 'inv_material_fifo_lot')) {
            $qb->where_in('receipt_id', $receiptIds);
        } else {
            $qb->where_in('source_id', $receiptIds);
        }

        $row = $qb->get()->row_array();
        $rowCount = (int)($row['row_count'] ?? 0);
        if ($rowCount <= 0) {
            return null;
        }

        $qtyContentBalance = round((float)($row['qty_content_balance'] ?? 0), 4);
        $contentPerBuy = round((float)($requirement['profile_content_per_buy'] ?? 0), 6);
        if ($contentPerBuy <= 0) {
            $needBuy = round((float)($requirement['qty_buy_needed'] ?? 0), 4);
            $needContent = round((float)($requirement['qty_content_needed'] ?? 0), 4);
            if ($needBuy > 0 && $needContent > 0) {
                $contentPerBuy = round($needContent / $needBuy, 6);
            }
        }

        return [
            'qty_buy_balance' => $contentPerBuy > 0 ? round($qtyContentBalance / $contentPerBuy, 4) : 0.0,
            'qty_content_balance' => $qtyContentBalance,
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
                ->where("UPPER(TRIM(t.type_code)) NOT IN ('BEBAN','INV_KITCHEN','INV_BAR')", null, false)
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

    public function quick_create_vendor(array $payload): array
    {
        if (!$this->db->table_exists('mst_vendor')) {
            return ['ok' => false, 'message' => 'Master vendor belum tersedia.'];
        }

        $vendorName = trim((string)($payload['vendor_name'] ?? ''));
        $vendorCode = strtoupper(trim((string)($payload['vendor_code'] ?? '')));
        $contactName = trim((string)($payload['contact_name'] ?? ''));
        $phone = trim((string)($payload['phone'] ?? ''));
        $email = trim((string)($payload['email'] ?? ''));
        $city = trim((string)($payload['city'] ?? ''));
        $address = trim((string)($payload['address'] ?? ''));
        $notes = trim((string)($payload['notes'] ?? ''));

        if ($vendorName === '') {
            return ['ok' => false, 'message' => 'Nama vendor wajib diisi.'];
        }

        $existing = $this->find_quick_vendor_match($vendorCode, $vendorName);
        if ($existing) {
            if ($this->db->field_exists('is_active', 'mst_vendor') && (int)($existing['is_active'] ?? 1) !== 1) {
                $updateData = ['is_active' => 1];
                if ($this->db->field_exists('updated_at', 'mst_vendor')) {
                    $updateData['updated_at'] = date('Y-m-d H:i:s');
                }
                $this->db->where('id', (int)$existing['id'])->update('mst_vendor', $updateData);
            }

            $vendor = $this->get_vendor_quick_row((int)$existing['id']);
            if ($vendor) {
                return [
                    'ok' => true,
                    'message' => 'Vendor sudah ada, vendor tersebut dipilih ulang.',
                    'data' => ['vendor' => $vendor, 'created' => false],
                ];
            }
        }

        if ($vendorCode === '') {
            $vendorCode = $this->generate_quick_vendor_code($vendorName);
        }

        $insert = [];
        if ($this->db->field_exists('vendor_code', 'mst_vendor')) {
            $insert['vendor_code'] = $vendorCode;
        }
        if ($this->db->field_exists('vendor_name', 'mst_vendor')) {
            $insert['vendor_name'] = $vendorName;
        }
        if ($this->db->field_exists('contact_name', 'mst_vendor')) {
            $insert['contact_name'] = $contactName !== '' ? $contactName : null;
        }
        if ($this->db->field_exists('phone', 'mst_vendor')) {
            $insert['phone'] = $phone !== '' ? $phone : null;
        }
        if ($this->db->field_exists('email', 'mst_vendor')) {
            $insert['email'] = $email !== '' ? $email : null;
        }
        if ($this->db->field_exists('city', 'mst_vendor')) {
            $insert['city'] = $city !== '' ? $city : null;
        }
        if ($this->db->field_exists('address', 'mst_vendor')) {
            $insert['address'] = $address !== '' ? $address : null;
        }
        if ($this->db->field_exists('notes', 'mst_vendor')) {
            $insert['notes'] = $notes !== '' ? $notes : null;
        }
        if ($this->db->field_exists('is_active', 'mst_vendor')) {
            $insert['is_active'] = 1;
        }
        if ($this->db->field_exists('created_at', 'mst_vendor')) {
            $insert['created_at'] = date('Y-m-d H:i:s');
        }
        if ($this->db->field_exists('updated_at', 'mst_vendor')) {
            $insert['updated_at'] = date('Y-m-d H:i:s');
        }

        $this->db->insert('mst_vendor', $insert);
        $vendorId = (int)$this->db->insert_id();
        if ($vendorId <= 0) {
            return ['ok' => false, 'message' => 'Vendor gagal disimpan.'];
        }

        $vendor = $this->get_vendor_quick_row($vendorId);
        if (!$vendor) {
            return ['ok' => false, 'message' => 'Vendor tersimpan, tetapi gagal dimuat ulang.'];
        }

        return [
            'ok' => true,
            'message' => 'Vendor baru berhasil ditambahkan.',
            'data' => ['vendor' => $vendor, 'created' => true],
        ];
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

        $codeColumn = $this->db->field_exists('code', 'mst_operational_division')
            ? 'code'
            : ($this->db->field_exists('division_code', 'mst_operational_division') ? 'division_code' : null);
        $nameColumn = $this->db->field_exists('name', 'mst_operational_division')
            ? 'name'
            : ($this->db->field_exists('division_name', 'mst_operational_division') ? 'division_name' : null);
        $hasCode = $codeColumn !== null;
        $hasName = $nameColumn !== null;

        $select = 'id';
        if ($hasCode) {
            $select .= ', ' . $codeColumn . ' AS code';
        }
        if ($hasName) {
            $select .= ', ' . $nameColumn . ' AS name';
        }

        $qb = $this->db->select($select, false)->from('mst_operational_division');
        if ($this->db->field_exists('is_active', 'mst_operational_division')) {
            $qb->where('is_active', 1);
        }
        if ($this->db->field_exists('sort_order', 'mst_operational_division')) {
            $qb->order_by('sort_order', 'ASC');
        }
        if ($hasName) {
            $qb->order_by($nameColumn, 'ASC');
        }

        $rows = $qb->get()->result_array();
        foreach ($rows as &$row) {
            $code = trim((string)($row['code'] ?? ''));
            $name = trim((string)($row['name'] ?? ''));
            $rule = $this->resolveDivisionDestinationRule($code, $name);
            $row['destination_default'] = (string)$rule['default'];
            $row['destination_allowed'] = implode(',', (array)$rule['allowed']);
        }
        unset($row);

        return $rows;
    }

    private function resolveDivisionDestinationRule(string $divisionCode, string $divisionName): array
    {
        $code = strtoupper(trim($divisionCode));
        $name = strtoupper(trim($divisionName));
        $joined = trim($code . ' ' . $name);

        if ($joined !== '') {
            if (strpos($joined, 'BAR') !== false) {
                return [
                    'default' => 'BAR',
                    'allowed' => ['BAR', 'BAR_EVENT'],
                ];
            }

            if (strpos($joined, 'KITCHEN') !== false || strpos($joined, 'DAPUR') !== false) {
                return [
                    'default' => 'KITCHEN',
                    'allowed' => ['KITCHEN', 'KITCHEN_EVENT'],
                ];
            }

            $officeKeywords = ['OFFICE', 'MANAGEMENT', 'MANAJEMEN', 'ADMIN', 'ACCOUNT', 'FINANCE', 'HR', 'GA'];
            foreach ($officeKeywords as $keyword) {
                if (strpos($joined, $keyword) !== false) {
                    return [
                        'default' => 'OFFICE',
                        'allowed' => ['OFFICE', 'OTHER'],
                    ];
                }
            }
        }

        return [
            'default' => 'OTHER',
            'allowed' => ['BAR', 'KITCHEN', 'BAR_EVENT', 'KITCHEN_EVENT', 'OFFICE', 'OTHER'],
        ];
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

        $qb = $this->db
            ->select('l.id AS purchase_order_line_id, l.line_no, l.line_kind, l.item_id, l.material_id')
            ->select('l.snapshot_item_name, l.snapshot_material_name, l.snapshot_brand_name, l.snapshot_line_description')
            ->select('l.buy_uom_id, l.content_uom_id, l.snapshot_buy_uom_code, l.snapshot_content_uom_code')
            ->select('l.qty_buy, l.qty_content, l.content_per_buy, l.conversion_factor_to_content, l.unit_price, l.profile_key, l.notes')
            ->select('COALESCE(rcv.qty_buy_received_total, 0) AS qty_buy_received_total', false)
            ->select('COALESCE(rcv.qty_content_received_total, 0) AS qty_content_received_total', false)
            ->from('pur_purchase_order_line l')
            ->join("({$receivedSub}) rcv", 'rcv.purchase_order_line_id = l.id', 'left', false)
            ->where('l.purchase_order_id', $purchaseOrderId)
            ->order_by('l.line_no', 'ASC');

        if ($this->db->field_exists('expired_date', 'pur_purchase_order_line')) {
            $qb->select('l.expired_date');
        }
        if ($this->db->field_exists('snapshot_expired_date', 'pur_purchase_order_line')) {
            $qb->select('l.snapshot_expired_date');
        }
        if ($this->hasPurchaseOrderUsagePurposeColumn()) {
            $qb->select('l.usage_purpose');
        }

        return $qb->get()->result_array();
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
        $this->load->library('MaterialFifoManager');
        $fifoReady = $this->materialfifomanager->ensureReady();
        if (!($fifoReady['ok'] ?? false)) {
            return $fifoReady;
        }
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

            $factor = $this->canonicalConversionFactor(
                $poLine['content_per_buy'] ?? 0,
                $poLine['conversion_factor_to_content'] ?? 0
            );
            $qtyContentReceived = round($qtyBuyReceived * $factor, 4);
            $profileExpiredDate = $this->normalizeDate((string)($poLine['expired_date'] ?? ($poLine['snapshot_expired_date'] ?? '')));
            $usagePurpose = $this->resolveLineUsagePurpose($poLine);
            $canonicalIdentity = $this->resolveCanonicalTransactionIdentity($poLine, $usagePurpose);
            $stockWriteCtx = $this->resolveCanonicalStockWriteContext($poLine, $usagePurpose);
            $stockItemId = $this->nullableInt($stockWriteCtx['item_id'] ?? null);
            $stockMaterialId = $this->nullableInt($stockWriteCtx['material_id'] ?? null);
            $movementScope = $destinationType === 'GUDANG' ? 'WAREHOUSE' : 'DIVISION';
            if ($movementScope === 'DIVISION' && $stockMaterialId === null) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => 'Receipt ke stok divisi wajib punya material_id canonical. Pilih profile bahan baku yang terhubung ke material sebelum terima barang.',
                ];
            }

            $receiptLineData = [
                'purchase_receipt_id' => $receiptId,
                'purchase_order_line_id' => $poLineId,
                'line_kind' => $this->legacyLineKindForStorage(
                    'pur_purchase_receipt_line',
                    (string)($canonicalIdentity['line_kind'] ?? ($poLine['line_kind'] ?? 'ITEM')),
                    $this->nullableInt($canonicalIdentity['item_id'] ?? ($poLine['item_id'] ?? null)),
                    $this->nullableInt($canonicalIdentity['material_id'] ?? ($poLine['material_id'] ?? null))
                ),
                'item_id' => $this->nullableInt($canonicalIdentity['item_id'] ?? ($poLine['item_id'] ?? null)),
                'material_id' => $this->nullableInt($canonicalIdentity['material_id'] ?? ($poLine['material_id'] ?? null)),
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
            if ($this->hasPurchaseReceiptUsagePurposeColumn()) {
                $receiptLineData['usage_purpose'] = $usagePurpose;
            }
            if ($this->db->field_exists('expired_date', 'pur_purchase_receipt_line')) {
                $receiptLineData['expired_date'] = $profileExpiredDate;
            }

            $profileName = trim((string)($poLine['snapshot_item_name'] ?? ''));
            if ($profileName === '') {
                $profileName = trim((string)($poLine['snapshot_material_name'] ?? ''));
            }

            // Resolve canonical catalog profile_key so that existing PO lines with
            // stale/vendor-locked keys still land under the correct inventory profile.
            $canonicalKey = $this->resolveCatalogProfileKeyByIdentity(
                (int)($poLine['item_id'] ?? 0),
                $this->nullableInt($poLine['material_id'] ?? null),
                (int)($poLine['buy_uom_id'] ?? 0),
                $this->nullableInt($poLine['content_uom_id'] ?? null) ?? (int)($poLine['buy_uom_id'] ?? 0),
                $profileName ?: null,
                $this->nullableString($poLine['snapshot_brand_name'] ?? null),
                $this->nullableString($poLine['snapshot_line_description'] ?? null),
                $profileExpiredDate ?: null,
                (float)($poLine['content_per_buy'] ?? 1),
                (float)($poLine['unit_price'] ?? 0)
            );
            $effectiveProfileKey = $canonicalKey ?? $this->nullableString($poLine['profile_key'] ?? null);

            // Override profile_key in receipt line with canonical key before insert
            $receiptLineData['profile_key'] = $effectiveProfileKey;

            $this->db->insert('pur_purchase_receipt_line', $receiptLineData);
            $receiptLineId = (int)$this->db->insert_id();
            if ($receiptLineId <= 0) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => 'Gagal menyimpan receipt line.',
                ];
            }

            $unitPrice = (float)($poLine['unit_price'] ?? 0);
            $unitCost = $factor > 0 ? round($unitPrice / $factor, 6) : 0;

            if (!empty($stockWriteCtx['uses_material_fifo'])) {
                $lotResult = $this->materialfifomanager->registerReceiptInboundLot([
                    'location_scope' => $movementScope,
                    'division_id' => $movementScope === 'DIVISION' ? $destinationDivisionId : null,
                    'destination_type' => $movementScope === 'DIVISION' ? $destinationType : 'GUDANG',
                    'receipt_date' => $receiptDate,
                    'expiry_date' => $profileExpiredDate,
                    'item_id' => $stockItemId,
                    'material_id' => $stockMaterialId,
                    'buy_uom_id' => $this->nullableInt($poLine['buy_uom_id'] ?? null),
                    'content_uom_id' => $this->nullableInt($poLine['content_uom_id'] ?? null),
                    'profile_key' => $effectiveProfileKey,
                    'qty_content_in' => $qtyContentReceived,
                    'unit_cost' => $unitCost,
                    'source_table' => 'pur_purchase_receipt',
                    'source_id' => $receiptId,
                    'source_line_id' => $receiptLineId,
                    'receipt_id' => $receiptId,
                    'receipt_line_id' => $receiptLineId,
                ]);
                if (!($lotResult['ok'] ?? false)) {
                    $this->db->trans_rollback();
                    return [
                        'ok' => false,
                        'message' => (string)($lotResult['message'] ?? 'Gagal membuat lot FIFO inbound untuk receipt.'),
                    ];
                }

                $lotId = (int)($lotResult['data']['lot_id'] ?? 0);
                $lotNo = $this->nullableString($lotResult['data']['lot_no'] ?? null);
                if ($lotId > 0 || $lotNo !== null) {
                    $receiptLineUpdate = [];
                    if ($this->db->field_exists('lot_id', 'pur_purchase_receipt_line') && $lotId > 0) {
                        $receiptLineUpdate['lot_id'] = $lotId;
                    }
                    if ($this->db->field_exists('lot_no', 'pur_purchase_receipt_line') && $lotNo !== null) {
                        $receiptLineUpdate['lot_no'] = $lotNo;
                    }
                    if (!empty($receiptLineUpdate)) {
                        $this->db->where('id', $receiptLineId)->update('pur_purchase_receipt_line', $receiptLineUpdate);
                    }
                }
            }

            $ledger = $this->postInventoryLedgerEntry([
                'manage_transaction' => false,
                'movement_date' => $receiptDate,
                'movement_scope' => $movementScope,
                'division_id' => $movementScope === 'DIVISION' ? $destinationDivisionId : null,
                'destination_type' => $movementScope === 'DIVISION' ? $destinationType : null,
                'movement_type' => 'PURCHASE_IN',
                'ref_table' => 'pur_purchase_receipt',
                'ref_id' => $receiptId,
                'receipt_id' => $receiptId,
                'receipt_line_id' => $receiptLineId,
                'item_id' => $stockItemId,
                'material_id' => $stockMaterialId,
                'buy_uom_id' => $this->nullableInt($poLine['buy_uom_id'] ?? null),
                'content_uom_id' => $this->nullableInt($poLine['content_uom_id'] ?? null),
                'qty_buy_delta' => $qtyBuyReceived,
                'qty_content_delta' => $qtyContentReceived,
                'profile_key' => $effectiveProfileKey,
                'profile_name' => $this->nullableString($profileName),
                'profile_brand' => $this->nullableString($poLine['snapshot_brand_name'] ?? null),
                'profile_description' => $this->nullableString($poLine['snapshot_line_description'] ?? null),
                'profile_expired_date' => $profileExpiredDate,
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
        if ($vendorId === null || $vendorId <= 0) {
            return [
                'ok' => false,
                'message' => 'Vendor wajib dipilih.',
            ];
        }
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

        if ($isInventory) {
            $headerDestinationType = strtoupper(trim((string)($header['destination_type'] ?? '')));
            $destinationCandidate = $headerDestinationType !== ''
                ? $headerDestinationType
                : $defaultDestination;

            $destinationType = $this->normalizeDestination($destinationCandidate);
            if ($destinationType === null) {
                return [
                    'ok' => false,
                    'message' => 'Tujuan wajib valid sesuai aturan purchase type inventory.',
                ];
            }

            $destinationDivisionId = $this->nullableInt($header['destination_division_id'] ?? null);
            if ($destinationDivisionId === null) {
                $destinationDivisionId = $this->resolveDestinationDivisionId($destinationType);
            }
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
            $lineResult = $this->buildLinePayload(
                (array)$row,
                $vendorId !== null ? $vendorId : 0,
                $isInventory && $destinationBehavior !== 'NONE'
            );
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
                'request_date' => $requestDate,
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
        $editMode = (string)($editability['data']['mode'] ?? 'full');

        if ($editMode !== 'payment_only' && empty($lines)) {
            return [
                'ok' => false,
                'message' => 'Baris purchase order wajib diisi.',
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

        $currentStatus = strtoupper(trim((string)($order['status'] ?? 'DRAFT')));
        $allowedStatuses = ['DRAFT', 'APPROVED', 'ORDERED', 'REJECTED', 'PARTIAL_RECEIVED', 'RECEIVED', 'PAID', 'VOID'];
        $targetStatus = strtoupper(trim((string)($header['status'] ?? $currentStatus)));
        if (!in_array($targetStatus, $allowedStatuses, true)) {
            $targetStatus = $currentStatus;
        }

        $reviewConfirmed = false;
        $reviewRaw = $header['review_confirmed'] ?? null;
        if (is_bool($reviewRaw)) {
            $reviewConfirmed = $reviewRaw;
        } elseif (is_numeric($reviewRaw)) {
            $reviewConfirmed = ((int)$reviewRaw) === 1;
        } else {
            $reviewConfirmed = in_array(strtolower(trim((string)$reviewRaw)), ['1', 'true', 'yes', 'on'], true);
        }

        if ($targetStatus !== $currentStatus && !$reviewConfirmed) {
            return [
                'ok' => false,
                'message' => 'Sebelum ubah status PO, buka review dulu dan konfirmasi pengecekan buyer.',
            ];
        }

        $paymentAccountId = $this->nullableInt($header['payment_account_id'] ?? null);
        if ($targetStatus === 'PAID' && $paymentAccountId === null) {
            return [
                'ok' => false,
                'message' => 'Status PAID membutuhkan Payment Account yang valid sebelum disimpan.',
            ];
        }

        if ($editMode === 'payment_only') {
            $this->db->trans_begin();

            if ($this->db->field_exists('payment_account_id', 'pur_purchase_order')) {
                $this->db
                    ->where('id', $purchaseOrderId)
                    ->update('pur_purchase_order', [
                        'payment_account_id' => $paymentAccountId,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            }

            if ((int)($this->db->error()['code'] ?? 0) !== 0 || $this->db->trans_status() === false) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => 'Gagal memperbarui metode pembayaran PO.',
                ];
            }

            $txnLog = $this->writePurchaseTxnLog([
                'purchase_order_id' => $purchaseOrderId,
                'action_code' => 'PO_UPDATE_PAYMENT_ACCOUNT',
                'status_before' => strtoupper((string)($order['status'] ?? '')),
                'status_after' => strtoupper((string)($order['status'] ?? '')),
                'transaction_no' => (string)($order['po_no'] ?? ''),
                'ref_table' => 'pur_purchase_order',
                'ref_id' => $purchaseOrderId,
                'amount' => null,
                'payload' => [
                    'payment_account_id' => $paymentAccountId,
                ],
                'notes' => 'Update metode pembayaran PO unpaid',
                'created_by' => $userId,
            ]);
            if (!($txnLog['ok'] ?? false)) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => (string)($txnLog['message'] ?? 'Gagal mencatat log perubahan metode pembayaran.'),
                ];
            }

            $this->db->trans_commit();

            return [
                'ok' => true,
                'message' => 'Metode pembayaran PO berhasil diperbarui.',
                'data' => [
                    'purchase_order_id' => $purchaseOrderId,
                    'po_no' => (string)($order['po_no'] ?? ''),
                    'payment_account_id' => $paymentAccountId,
                    'edit_mode' => $editMode,
                ],
            ];
        }

        $destinationType = null;
        $destinationDivisionId = null;
        $isInventory = (int)($typeRule['affects_inventory'] ?? 0) === 1;
        $destinationBehavior = strtoupper(trim((string)($typeRule['destination_behavior'] ?? 'REQUIRED')));
        $defaultDestination = strtoupper(trim((string)($typeRule['default_destination'] ?? '')));

        if ($isInventory) {
            $headerDestinationType = strtoupper(trim((string)($header['destination_type'] ?? '')));
            $destinationCandidate = $headerDestinationType !== ''
                ? $headerDestinationType
                : ($defaultDestination !== '' ? $defaultDestination : (string)($order['destination_type'] ?? ''));

            $destinationType = $this->normalizeDestination($destinationCandidate);
            if ($destinationType === null) {
                return [
                    'ok' => false,
                    'message' => 'Tujuan wajib valid sesuai aturan purchase type inventory.',
                ];
            }

            $destinationDivisionId = $this->nullableInt($header['destination_division_id'] ?? null);
            if ($destinationDivisionId === null) {
                $destinationDivisionId = $this->nullableInt($order['destination_division_id'] ?? null);
            }
            if ($destinationDivisionId === null) {
                $destinationDivisionId = $this->resolveDestinationDivisionId($destinationType);
            }
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

        if (!empty($existingLineIds)) {
            $clearCatalogRefError = $this->clearPurchaseCatalogPurchaseRefs($purchaseOrderId, $existingLineIds);
            if ($clearCatalogRefError !== null) {
                $this->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => $clearCatalogRefError,
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
            $lineResult = $this->buildLinePayload(
                (array)$row,
                $vendorId !== null ? $vendorId : 0,
                $isInventory && $destinationBehavior !== 'NONE'
            );
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
                'status_before' => $currentStatus,
                'status_target' => $targetStatus,
            ],
        ];
    }

    private function evaluateOrderDataEditability(array $order): array
    {
        $orderId = (int)($order['id'] ?? 0);
        $status = strtoupper(trim((string)($order['status'] ?? 'DRAFT')));
        $fullEditableStatuses = ['DRAFT', 'APPROVED'];
        $receiptHistoryCount = 0;
        $paidCount = 0;

        if ($orderId > 0 && $this->db->table_exists('pur_purchase_receipt')) {
            $receiptHistoryCount = (int)$this->db
                ->from('pur_purchase_receipt')
                ->where('purchase_order_id', $orderId)
                ->count_all_results();
        }

        if ($orderId > 0 && $this->db->table_exists('pur_purchase_payment_plan')) {
            $paidCount = (int)$this->db
                ->from('pur_purchase_payment_plan')
                ->where('purchase_order_id', $orderId)
                ->where('status', 'PAID')
                ->count_all_results();
        }

        if ($paidCount > 0) {
            return [
                'ok' => false,
                'message' => 'PO tidak bisa diedit karena sudah memiliki pembayaran PAID.',
            ];
        }

        if (in_array($status, $fullEditableStatuses, true) && $receiptHistoryCount <= 0) {
            return [
                'ok' => true,
                'data' => [
                    'order_id' => $orderId,
                    'status' => $status,
                    'mode' => 'full',
                    'receipt_history_count' => $receiptHistoryCount,
                    'paid_count' => $paidCount,
                ],
            ];
        }

        if (in_array($status, ['ORDERED', 'PARTIAL_RECEIVED', 'RECEIVED'], true)) {
            return [
                'ok' => true,
                'data' => [
                    'order_id' => $orderId,
                    'status' => $status,
                    'mode' => 'payment_only',
                    'receipt_history_count' => $receiptHistoryCount,
                    'paid_count' => $paidCount,
                ],
                'message' => 'PO pada status ini hanya boleh mengubah metode pembayaran sebelum lunas.',
            ];
        }

        return [
            'ok' => false,
            'message' => 'PO dengan status ' . $status . ' tidak boleh edit data lagi.',
        ];
    }

    private function requiresPurchaseOrderEditReviewBeforeStatusUpdate(int $purchaseOrderId): bool
    {
        if ($purchaseOrderId <= 0 || !$this->db->table_exists('pur_division_request_link')) {
            return false;
        }

        $linkedCount = (int)$this->db
            ->from('pur_division_request_link')
            ->where('doc_type', 'PO')
            ->where('doc_id', $purchaseOrderId)
            ->count_all_results();
        if ($linkedCount <= 0) {
            return false;
        }

        if (!$this->db->table_exists('pur_purchase_txn_log')) {
            return true;
        }

        $reviewCount = (int)$this->db
            ->from('pur_purchase_txn_log')
            ->where('purchase_order_id', $purchaseOrderId)
            ->where('action_code', 'PO_UPDATE')
            ->count_all_results();

        return $reviewCount <= 0;
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
        $catalogFactorExpr = 'ROUND(COALESCE(NULLIF(c.content_per_buy, 0), NULLIF(c.conversion_factor_to_content, 0), 1), 6)';
        $useVendorLinkTable = $this->purchaseCatalogVendorTableExists();
        $catalogHasVendorId = $this->db->field_exists('vendor_id', 'mst_purchase_catalog');
        $latestVendorPriceSql = $useVendorLinkTable ? $this->latestCatalogVendorPriceSubquerySql() : '';

        $vendorSelectExpr = 'NULL';
        if ($useVendorLinkTable && $vendorId > 0) {
            $vendorSelectExpr = 'cv.vendor_id';
        } elseif ($useVendorLinkTable) {
            $vendorSelectExpr = 'cvl.vendor_id';
        } elseif (!$useVendorLinkTable && $catalogHasVendorId) {
            $vendorSelectExpr = 'c.vendor_id';
        }

        if ($useVendorLinkTable && $vendorId > 0) {
            $standardPriceExpr = 'COALESCE(cv.standard_price, cv.last_unit_price, c.standard_price, c.last_unit_price)';
            $lastUnitPriceExpr = 'COALESCE(cv.last_unit_price, cv.standard_price, c.last_unit_price, c.standard_price)';
            $lastPurchaseDateExpr = 'COALESCE(cv.last_purchase_date, c.last_purchase_date)';
        } elseif ($useVendorLinkTable) {
            $standardPriceExpr = 'COALESCE(cvl.standard_price, cvl.last_unit_price, c.standard_price, c.last_unit_price)';
            $lastUnitPriceExpr = 'COALESCE(cvl.last_unit_price, cvl.standard_price, c.last_unit_price, c.standard_price)';
            $lastPurchaseDateExpr = 'COALESCE(cvl.last_purchase_date, c.last_purchase_date)';
        } else {
            $standardPriceExpr = 'COALESCE(c.standard_price, c.last_unit_price)';
            $lastUnitPriceExpr = 'COALESCE(c.last_unit_price, c.standard_price)';
            $lastPurchaseDateExpr = 'c.last_purchase_date';
        }

        $this->db
            ->select("'CATALOG' AS source_type", false)
            ->select('c.id AS catalog_id, c.profile_key, c.line_kind, c.item_id, c.material_id, ' . $vendorSelectExpr . ' AS vendor_id', false)
            ->select('c.catalog_name, c.brand_name, c.line_description, c.notes')
            ->select('c.buy_uom_id, bu.code AS buy_uom_code, bu.name AS buy_uom_name')
            ->select('c.content_uom_id, cu.code AS content_uom_code, cu.name AS content_uom_name')
            ->select($catalogFactorExpr . ' AS content_per_buy', false)
            ->select($catalogFactorExpr . ' AS conversion_factor_to_content', false)
            ->select($standardPriceExpr . ' AS standard_price', false)
            ->select($lastUnitPriceExpr . ' AS last_unit_price', false)
            ->select($lastPurchaseDateExpr . ' AS last_purchase_date', false)
            ->select('i.item_code, i.item_name, m.material_code, m.material_name')
            ->select($rankExpr . ' AS rank_score', false)
            ->from('mst_purchase_catalog c')
            ->join('mst_uom bu', 'bu.id = c.buy_uom_id', 'left')
            ->join('mst_uom cu', 'cu.id = c.content_uom_id', 'left')
            ->join('mst_item i', 'i.id = c.item_id', 'left')
            ->join('mst_material m', 'm.id = c.material_id', 'left')
            ->where('c.is_active', 1);

        $invalidSameUomExpr = 'COALESCE(c.buy_uom_id, 0) = COALESCE(c.content_uom_id, 0)'
            . ' AND COALESCE(c.buy_uom_id, 0) > 0'
            . ' AND ROUND(COALESCE(NULLIF(c.content_per_buy, 0), NULLIF(c.conversion_factor_to_content, 0), 1), 6) <> 1';
        $hasValidSiblingExpr = "EXISTS (
                SELECT 1
                FROM mst_purchase_catalog alt
                WHERE alt.id <> c.id
                  AND COALESCE(alt.item_id, 0) = COALESCE(c.item_id, 0)
                  AND COALESCE(alt.material_id, 0) = COALESCE(c.material_id, 0)
                  AND UPPER(TRIM(COALESCE(alt.catalog_name, ''))) = UPPER(TRIM(COALESCE(c.catalog_name, '')))
                  AND UPPER(TRIM(COALESCE(alt.brand_name, ''))) = UPPER(TRIM(COALESCE(c.brand_name, '')))
                  AND UPPER(TRIM(COALESCE(alt.line_description, ''))) = UPPER(TRIM(COALESCE(c.line_description, '')))
                  AND ROUND(COALESCE(NULLIF(alt.content_per_buy, 0), NULLIF(alt.conversion_factor_to_content, 0), 1), 6)
                      = ROUND(COALESCE(NULLIF(c.content_per_buy, 0), NULLIF(c.conversion_factor_to_content, 0), 1), 6)
                  AND COALESCE(alt.buy_uom_id, 0) <> COALESCE(alt.content_uom_id, 0)
            )";
        $this->db->where('NOT ((' . $invalidSameUomExpr . ') AND ' . $hasValidSiblingExpr . ')', null, false);

        if ($useVendorLinkTable && $vendorId > 0) {
            $this->db->join(
                'mst_purchase_catalog_vendor cv',
                'cv.catalog_id = c.id AND cv.vendor_id = ' . (int)$vendorId . ' AND COALESCE(cv.is_active, 1) = 1',
                'inner',
                false
            );
        } elseif ($useVendorLinkTable) {
            $this->db->join('(' . $latestVendorPriceSql . ') cvl', 'cvl.catalog_id = c.id', 'left', false);
        }

        if ($this->db->field_exists('expired_date', 'mst_purchase_catalog')) {
            $this->db->select('c.expired_date');
        } else {
            $this->db->select('NULL AS expired_date', false);
        }

        if ($vendorId > 0 && !$useVendorLinkTable && $catalogHasVendorId) {
            $this->db->where('c.vendor_id', $vendorId);
        }
        if ($lineKind === 'MATERIAL') {
            $this->db->where('c.material_id IS NOT NULL', null, false);
            $this->db->where('c.material_id >', 0);
        } elseif ($lineKind !== '') {
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
            ->order_by($lastPurchaseDateExpr, 'DESC', false)
            ->order_by('c.id', 'DESC')
            ->limit($limit);

        return $this->db->get()->result_array();
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

    private function canonicalConversionFactor($contentPerBuy, $conversionFactor = null): float
    {
        $contentPerBuy = (float)$contentPerBuy;
        if ($contentPerBuy > 0) {
            return round($contentPerBuy, 8);
        }

        $conversionFactor = (float)$conversionFactor;
        if ($conversionFactor > 0) {
            return round($conversionFactor, 8);
        }

        return 1.0;
    }

    public function search_master_fallback(
        string $q,
        string $lineKind,
        int $itemId,
        int $materialId,
        int $limit
    ): array {
        $rows = [];

        if ($lineKind === '' || $lineKind === 'ITEM' || $lineKind === 'MATERIAL') {
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
            ->select("'ITEM' AS line_kind", false)
            ->select('i.id AS item_id, i.material_id, NULL AS vendor_id', false)
            ->select('i.item_name AS catalog_name, NULL AS brand_name, NULL AS line_description, i.notes', false)
            ->select('NULL AS expired_date', false)
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
            ->select("'ITEM' AS line_kind", false)
            ->select('mi.id AS item_id, m.id AS material_id, NULL AS vendor_id', false)
            ->select('m.material_name AS catalog_name, NULL AS brand_name, NULL AS line_description, m.notes', false)
            ->select('NULL AS expired_date', false)
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
            ->where('m.is_active', 1)
            ->where('mi.id IS NULL', null, false);

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

    private function buildLinePayload(array $line, int $vendorId, bool $requireEntityLink = false): array
    {
        $lineKind = strtoupper(trim((string)($line['line_kind'] ?? 'ITEM')));
        if (!in_array($lineKind, ['ITEM', 'MATERIAL', 'SERVICE', 'ASSET'], true)) {
            $lineKind = 'ITEM';
        }

        $itemId = $this->nullableInt($line['item_id'] ?? null);
        $materialId = $this->nullableInt($line['material_id'] ?? null);
        $usagePurpose = $this->resolveLineUsagePurpose($line);
        $buyUomId = (int)($line['buy_uom_id'] ?? 0);
        $contentUomId = $this->nullableInt($line['content_uom_id'] ?? null);
        $lineName = $this->nullableString($line['catalog_name'] ?? ($line['item_name'] ?? null));

        if (in_array($lineKind, ['ITEM', 'MATERIAL'], true)) {
            $canonicalIdentity = $this->resolveCanonicalTransactionIdentity([
                'item_id' => $itemId,
                'material_id' => $materialId,
                'profile_key' => $line['profile_key'] ?? null,
                'usage_purpose' => $usagePurpose,
            ], $usagePurpose);
            $itemId = $canonicalIdentity['item_id'] ?? $itemId;
            $materialId = $canonicalIdentity['material_id'] ?? $materialId;
            $lineKind = 'ITEM';
        }

        if ($lineKind === 'MATERIAL') {
            $lineKind = 'ITEM';
        }

        if ($buyUomId <= 0) {
            return ['ok' => false, 'message' => 'buy_uom_id wajib diisi.'];
        }

        $allowAutoCreate = in_array($lineKind, ['ITEM', 'SERVICE', 'ASSET'], true);
        if (
            $allowAutoCreate
            && $itemId === null
            && $materialId === null
            && $lineName !== null
            && ($lineKind === 'ITEM' || $requireEntityLink)
        ) {
            $autoItemId = $this->ensureItemFromPurchaseLine($lineName, $buyUomId, $contentUomId, (float)($line['content_per_buy'] ?? 1), (float)($line['unit_price'] ?? 0));
            if ($autoItemId !== null) {
                $itemId = $autoItemId;
                if ($lineKind !== 'MATERIAL') {
                    $lineKind = 'ITEM';
                }
            }
        }

        if ($requireEntityLink && $itemId === null && $materialId === null) {
            return [
                'ok' => false,
                'message' => 'Line inventory wajib memiliki item/material. Isi nama barang + UOM beli agar sistem auto-create item, atau pilih ulang dari katalog yang sudah terhubung.',
            ];
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

        $qtyBuy = $this->positiveDecimal($line['qty_buy'] ?? 0, 0);
        if ($qtyBuy <= 0) {
            return ['ok' => false, 'message' => 'qty_buy harus lebih dari 0.'];
        }

        $contentPerBuy = $this->positiveDecimal($line['content_per_buy'] ?? 1, 1);
        $factor = $contentPerBuy;
        if ($factor <= 0) {
            $factor = $this->positiveDecimal($line['conversion_factor_to_content'] ?? $contentPerBuy, $contentPerBuy);
        }
        $qtyContent = round($qtyBuy * $factor, 4);

        if ($contentUomId === null) {
            $contentUomId = $entity['content_uom_id'];
        }
        if ($contentUomId === null) {
            $contentUomId = $buyUomId;
        }

        $unitPrice = $this->positiveDecimal($line['unit_price'] ?? 0, 0);
        $discountPercent = $this->boundedPercent($line['discount_percent'] ?? 0);
        $taxPercent = $this->boundedPercent($line['tax_percent'] ?? 0);

        $gross = $qtyBuy * $unitPrice;
        $afterDiscount = $gross * (1 - ($discountPercent / 100));
        $lineSubtotal = round($afterDiscount * (1 + ($taxPercent / 100)), 2);

        $brandName = $this->nullableString($line['brand_name'] ?? null);
        $lineDescription = $this->normalizeProfileDescription($line['line_description'] ?? null);
        $expiryRequirement = $this->extractOrderLineExpiryRequirement($line, 'expired_date');
        $expiredDate = $expiryRequirement['required_expiry_date'];
        $lineName = trim((string)($line['catalog_name'] ?? ($entity['item_name'] ?? ($entity['material_name'] ?? ($lineDescription ?? '')))));

        if (
            $lineKind === 'ITEM'
            && $itemId !== null
            && $buyUomId > 0
            && $contentUomId !== null
            && $this->isImpossibleSameUomProfileStructure($buyUomId, (int)$contentUomId, $contentPerBuy)
        ) {
            $legacyStructure = $this->resolveLegacyMixedUomProfileStructure(
                (int)$itemId,
                $materialId,
                $lineName ?: null,
                $brandName,
                $lineDescription,
                $contentPerBuy
            );
            if ($legacyStructure !== null) {
                $buyUomId = (int)$legacyStructure['buy_uom_id'];
                $contentUomId = (int)$legacyStructure['content_uom_id'];
            } else {
                return [
                    'ok' => false,
                    'message' => 'Struktur profile beli tidak valid. UOM beli dan UOM isi sama, tetapi content_per_buy bukan 1. Repair master item/katalog profile ini dulu sebelum membuat PO baru.',
                ];
            }
        }

        // Resolve profile_key against catalog signature including buyer-edited price.
        $profileKey = ($itemId !== null || $materialId !== null)
            ? $this->resolveCatalogProfileKeyByIdentity(
                (int)($itemId ?? 0),
                $materialId,
                $buyUomId,
                (int)$contentUomId,
                $lineName ?: null,
                $brandName,
                $lineDescription,
                $expiredDate ?: null,
                $contentPerBuy,
                $unitPrice
            )
            : null;

        if ($profileKey === null) {
            // Fallback: keep different price bands as distinct purchase profiles.
            $profileKey = hash('sha256', implode('|', [
                $lineKind,
                (string)($itemId ?? 0),
                (string)($materialId ?? 0),
                (string)$buyUomId,
                (string)$contentUomId,
                number_format($contentPerBuy, 6, '.', ''),
                number_format($unitPrice, 2, '.', ''),
                strtoupper((string)($lineName ?: '')),
                strtoupper((string)($brandName ?? '')),
                strtoupper((string)($lineDescription ?? '')),
            ]));
        }

        $data = [
            'line_kind' => $this->legacyLineKindForStorage('pur_purchase_order_line', $lineKind, $itemId, $materialId),
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
        if ($this->hasPurchaseOrderUsagePurposeColumn()) {
            $data['usage_purpose'] = $usagePurpose;
        }
        if ($this->db->field_exists('expired_date', 'pur_purchase_order_line')) {
            $data['expired_date'] = $expiredDate;
        }
        if ($this->db->field_exists('snapshot_expired_date', 'pur_purchase_order_line')) {
            $data['snapshot_expired_date'] = $expiredDate;
        }
        $this->appendOrderLineExpiryRequirementColumns('pur_purchase_order_line', $data, $line, 'expired_date');

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

        $canonicalProfileKey = $this->resolveCatalogProfileKeyByIdentity(
            (int)($itemId ?? 0),
            $materialId,
            (int)($lineData['buy_uom_id'] ?? 0),
            (int)($lineData['content_uom_id'] ?? ($lineData['buy_uom_id'] ?? 0)),
            $catalogName,
            $this->nullableString($lineData['brand_name'] ?? null),
            $this->normalizeProfileDescription($lineData['line_description'] ?? null),
            null,
            (float)($lineData['content_per_buy'] ?? 0),
            (float)($lineData['unit_price'] ?? 0)
        );
        $effectiveProfileKey = trim((string)($canonicalProfileKey ?? ($lineData['profile_key'] ?? '')));
        if ($effectiveProfileKey === '') {
            return;
        }

        $canonicalIdentity = $this->resolveCanonicalTransactionIdentity($lineData);

        $upsertData = [
            'profile_key' => $effectiveProfileKey,
            'line_kind' => $this->legacyLineKindForStorage(
                'mst_purchase_catalog',
                (string)($canonicalIdentity['line_kind'] ?? 'ITEM'),
                $this->nullableInt($canonicalIdentity['item_id'] ?? $itemId),
                $this->nullableInt($canonicalIdentity['material_id'] ?? $materialId)
            ),
            'item_id' => $this->nullableInt($canonicalIdentity['item_id'] ?? $itemId),
            'material_id' => $this->nullableInt($canonicalIdentity['material_id'] ?? $materialId),
            'catalog_name' => $catalogName,
            'brand_name' => $this->nullableString($lineData['brand_name'] ?? null),
            'line_description' => $this->normalizeProfileDescription($lineData['line_description'] ?? null),
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
        if ($this->db->field_exists('vendor_id', 'mst_purchase_catalog')) {
            $upsertData['vendor_id'] = $vendorId;
        }

        $existing = $this->db->get_where('mst_purchase_catalog', ['profile_key' => $upsertData['profile_key']])->row_array();
        $catalogId = 0;
        if ($existing) {
            $catalogId = (int)($existing['id'] ?? 0);
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
            $this->upsertCatalogVendorFromLine($catalogId, $vendorId, $purchaseDate, $orderId, $lineId, $lineData);
            return;
        }

        $upsertData['standard_price'] = (float)$lineData['unit_price'];
        $this->db->insert('mst_purchase_catalog', $upsertData);
        $catalogId = (int)$this->db->insert_id();
        $this->upsertCatalogVendorFromLine($catalogId, $vendorId, $purchaseDate, $orderId, $lineId, $lineData);
    }

    private function clearPurchaseCatalogPurchaseRefs(int $purchaseOrderId, array $existingLineIds): ?string
    {
        $lineIds = array_values(array_unique(array_filter(array_map('intval', $existingLineIds), static function ($id) {
            return $id > 0;
        })));
        if ($purchaseOrderId <= 0 && empty($lineIds)) {
            return null;
        }

        $targets = [];
        if ($this->purchaseCatalogVendorTableExists() && $this->db->field_exists('last_purchase_line_id', 'mst_purchase_catalog_vendor')) {
            $targets[] = [
                'table' => 'mst_purchase_catalog_vendor',
                'label' => 'referensi vendor catalog',
            ];
        }
        if ($this->db->table_exists('mst_purchase_catalog') && $this->db->field_exists('last_purchase_line_id', 'mst_purchase_catalog')) {
            $targets[] = [
                'table' => 'mst_purchase_catalog',
                'label' => 'referensi catalog',
            ];
        }

        foreach ($targets as $target) {
            $table = (string)($target['table'] ?? '');
            if ($table === '') {
                continue;
            }

            $clearRefData = ['last_purchase_line_id' => null];
            if ($this->db->field_exists('last_purchase_order_id', $table)) {
                $clearRefData['last_purchase_order_id'] = null;
            }

            $this->db->group_start();
            if (!empty($lineIds)) {
                $this->db->where_in('last_purchase_line_id', $lineIds);
            }
            if ($purchaseOrderId > 0 && $this->db->field_exists('last_purchase_order_id', $table)) {
                if (!empty($lineIds)) {
                    $this->db->or_where('last_purchase_order_id', $purchaseOrderId);
                } else {
                    $this->db->where('last_purchase_order_id', $purchaseOrderId);
                }
            }
            $this->db->group_end()->update($table, $clearRefData);

            if ((int)($this->db->error()['code'] ?? 0) !== 0) {
                $dbErr = $this->db->error();
                return 'Gagal melepas ' . (string)($target['label'] ?? 'referensi katalog') . ': ' . (string)($dbErr['message'] ?? '-');
            }
        }

        return null;
    }

    private function upsertCatalogVendorFromLine(int $catalogId, int $vendorId, string $purchaseDate, int $orderId, int $lineId, array $lineData): void
    {
        if ($catalogId <= 0 || $vendorId <= 0 || !$this->purchaseCatalogVendorTableExists()) {
            return;
        }

        $vendorData = [
            'catalog_id' => $catalogId,
            'vendor_id' => $vendorId,
            'standard_price' => (float)($lineData['unit_price'] ?? 0),
            'last_unit_price' => (float)($lineData['unit_price'] ?? 0),
            'last_purchase_date' => $purchaseDate,
            'last_purchase_order_id' => $orderId,
            'last_purchase_line_id' => $lineId,
            'notes' => $this->nullableString($lineData['notes'] ?? null),
            'is_active' => 1,
        ];
        $vendorData = $this->sanitizeCatalogPurchaseReferenceData($vendorData);

        $existing = $this->db
            ->get_where('mst_purchase_catalog_vendor', [
                'catalog_id' => $catalogId,
                'vendor_id' => $vendorId,
            ])
            ->row_array();

        if ($existing) {
            $updateData = [
                'standard_price' => $vendorData['standard_price'],
                'last_unit_price' => $vendorData['last_unit_price'],
                'last_purchase_date' => $vendorData['last_purchase_date'],
                'last_purchase_order_id' => $vendorData['last_purchase_order_id'],
                'last_purchase_line_id' => $vendorData['last_purchase_line_id'],
                'notes' => $vendorData['notes'],
                'is_active' => 1,
            ];
            $this->db->where('id', (int)$existing['id'])->update('mst_purchase_catalog_vendor', $updateData);
            return;
        }

        $this->db->insert('mst_purchase_catalog_vendor', $vendorData);
    }

    private function catalogProfileKeyUsageScores(array $profileKeys): array
    {
        $keys = array_values(array_unique(array_filter(array_map('trim', $profileKeys), static function ($value) {
            return $value !== '';
        })));
        if (empty($keys)) {
            return [];
        }

        $scores = [];
        foreach ($keys as $key) {
            $scores[$key] = 0;
        }

        $sources = [
            ['table' => 'inv_warehouse_monthly_stock', 'weight' => 1000],
            ['table' => 'inv_division_monthly_stock', 'weight' => 1000],
            ['table' => 'inv_stock_movement_log', 'weight' => 100],
            ['table' => 'pur_purchase_receipt_line', 'weight' => 10],
            ['table' => 'pur_purchase_order_line', 'weight' => 5],
        ];

        foreach ($sources as $source) {
            $table = (string)$source['table'];
            if (!$this->db->table_exists($table) || !$this->db->field_exists('profile_key', $table)) {
                continue;
            }

            $rows = $this->db
                ->select('profile_key, COUNT(*) AS row_count', false)
                ->from($table)
                ->where_in('profile_key', $keys)
                ->group_by('profile_key')
                ->get()
                ->result_array();
            foreach ($rows as $row) {
                $profileKey = trim((string)($row['profile_key'] ?? ''));
                if ($profileKey === '' || !isset($scores[$profileKey])) {
                    continue;
                }
                $scores[$profileKey] += ((int)($source['weight'] ?? 0)) * (int)($row['row_count'] ?? 0);
            }
        }

        return $scores;
    }

    private function sanitizeCatalogPurchaseReferenceData(array $data): array
    {
        $hasOrderRef = array_key_exists('last_purchase_order_id', $data);
        $hasLineRef = array_key_exists('last_purchase_line_id', $data);
        if (!$hasOrderRef && !$hasLineRef) {
            return $data;
        }

        $orderId = $hasOrderRef ? $this->nullableInt($data['last_purchase_order_id'] ?? null) : null;
        if ($hasOrderRef && $orderId !== null) {
            if (!$this->db->table_exists('pur_purchase_order')) {
                $orderId = null;
            } else {
                $order = $this->db
                    ->select('id')
                    ->from('pur_purchase_order')
                    ->where('id', $orderId)
                    ->limit(1)
                    ->get()
                    ->row_array();
                if (empty($order['id'])) {
                    $orderId = null;
                }
            }
            $data['last_purchase_order_id'] = $orderId;
        }

        $lineId = $hasLineRef ? $this->nullableInt($data['last_purchase_line_id'] ?? null) : null;
        if ($hasLineRef && $lineId !== null) {
            if (!$this->db->table_exists('pur_purchase_order_line')) {
                $lineId = null;
            } else {
                $line = $this->db
                    ->select('id, purchase_order_id')
                    ->from('pur_purchase_order_line')
                    ->where('id', $lineId)
                    ->limit(1)
                    ->get()
                    ->row_array();
                if (empty($line['id'])) {
                    $lineId = null;
                } elseif ($orderId !== null && (int)($line['purchase_order_id'] ?? 0) !== $orderId) {
                    $lineId = null;
                } elseif ($orderId === null) {
                    $lineId = null;
                }
            }
            $data['last_purchase_line_id'] = $lineId;
        }

        if ($hasLineRef && $hasOrderRef && ($data['last_purchase_line_id'] ?? null) === null) {
            $data['last_purchase_order_id'] = null;
        }

        return $data;
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

    private function resolveDailyWindow(string $month, string $dateFrom, string $dateTo): array
    {
        $monthKey = $this->normalizeMonth($month);
        if ($monthKey === null) {
            $monthKey = date('Y-m-01');
        }

        $defaultFrom = $monthKey;
        $defaultTo = date('Y-m-t', strtotime($monthKey));

        $from = $this->normalizeDate($dateFrom);
        $to = $this->normalizeDate($dateTo);
        if ($from === null) {
            $from = $defaultFrom;
        }
        if ($to === null) {
            $to = $defaultTo;
        }

        if ($from > $to) {
            $tmp = $from;
            $from = $to;
            $to = $tmp;
        }

        return [
            'month_key' => $monthKey,
            'date_from' => $from,
            'date_to' => $to,
        ];
    }

    private function buildDateSeries(string $from, string $to): array
    {
        $result = [];
        $startTs = strtotime($from);
        $endTs = strtotime($to);
        if ($startTs === false || $endTs === false || $startTs > $endTs) {
            return $result;
        }

        for ($ts = $startTs; $ts <= $endTs; $ts = strtotime('+1 day', $ts)) {
            $result[] = date('Y-m-d', $ts);
        }

        return $result;
    }

    private function pivotDailyRows(array $sourceRows, int $limitProfiles): array
    {
        $limitProfiles = max(1, min(1000, $limitProfiles));

        $pivot = [];
        foreach ($sourceRows as $row) {
            $profileKey = implode('|', [
                (string)($row['division_id'] ?? ''),
                (string)($row['destination_type'] ?? ''),
                strtoupper((string)($row['stock_domain'] ?? '')),
                (int)($row['item_id'] ?? 0),
                (int)($row['material_id'] ?? 0),
                (int)($row['buy_uom_id'] ?? 0),
                (int)($row['content_uom_id'] ?? 0),
                (string)($row['profile_key'] ?? ''),
            ]);

            if (!isset($pivot[$profileKey])) {
                if (count($pivot) >= $limitProfiles) {
                    continue;
                }

                $pivot[$profileKey] = [
                    'stock_domain' => strtoupper((string)($row['stock_domain'] ?? '')),
                    'division_id' => isset($row['division_id']) ? (int)$row['division_id'] : null,
                    'division_code' => (string)($row['division_code'] ?? ''),
                    'division_name' => (string)($row['division_name'] ?? ''),
                    'destination_type' => (string)($row['destination_type'] ?? 'OTHER'),
                    'destination_group' => (string)($row['destination_group'] ?? 'REGULER'),
                    'destination_name' => (string)($row['destination_name'] ?? 'Reguler'),
                    'item_id' => (int)($row['item_id'] ?? 0),
                    'material_id' => (int)($row['material_id'] ?? 0),
                    'buy_uom_id' => (int)($row['buy_uom_id'] ?? 0),
                    'content_uom_id' => (int)($row['content_uom_id'] ?? 0),
                    'item_code' => (string)($row['item_code'] ?? ''),
                    'item_name' => (string)($row['item_name'] ?? ''),
                    'material_code' => (string)($row['material_code'] ?? ''),
                    'material_name' => (string)($row['material_name'] ?? ''),
                    'profile_key' => (string)($row['profile_key'] ?? ''),
                    'profile_name' => (string)($row['profile_name'] ?? ''),
                    'profile_brand' => (string)($row['profile_brand'] ?? ''),
                    'profile_description' => (string)($row['profile_description'] ?? ''),
                    'profile_expired_date' => (string)($row['profile_expired_date'] ?? ''),
                    'profile_content_per_buy' => (float)($row['profile_content_per_buy'] ?? 0),
                    'profile_buy_uom_code' => (string)($row['profile_buy_uom_code'] ?? ''),
                    'profile_content_uom_code' => (string)($row['profile_content_uom_code'] ?? ''),
                    'profile_standard_price' => round((float)($row['profile_standard_price'] ?? 0), 2),
                    'profile_last_unit_price' => round((float)($row['profile_last_unit_price'] ?? 0), 2),
                    'audit_has_mismatch' => (int)($row['audit_has_mismatch'] ?? 0),
                    'audit_mismatch_row_count' => (int)($row['audit_mismatch_row_count'] ?? 0),
                    'audit_mismatch_qty_content' => round((float)($row['audit_mismatch_qty_content'] ?? 0), 4),
                    'audit_mismatch_notes' => (string)($row['audit_mismatch_notes'] ?? ''),
                    'daily' => [],
                    'summary' => [
                        'in_total' => 0.0,
                        'out_total' => 0.0,
                        'adjustment_total' => 0.0,
                        'closing_last' => 0.0,
                        'total_value_last' => 0.0,
                    ],
                ];
            }

            $day = (string)($row['movement_date'] ?? '');
            if ($day === '') {
                continue;
            }

            $openingQty = round((float)($row['opening_qty_content'] ?? 0), 4);
            $inQty = round((float)($row['in_qty_content'] ?? 0), 4);
            $outQty = round((float)($row['out_qty_content'] ?? 0), 4);
            $adjustmentQty = round($this->resolveDailyMatrixAdjustmentQtyContent($row), 4);
            $rollupClosingQty = round((float)($row['closing_qty_content'] ?? 0), 4);
            $computedClosingQty = round($openingQty + $inQty - $outQty + $adjustmentQty, 4);
            $closingQty = abs($rollupClosingQty - $computedClosingQty) > 0.0001
                ? $computedClosingQty
                : $rollupClosingQty;

            $entry = [
                'opening' => round($openingQty, 2),
                'in' => round($inQty, 2),
                'out' => round($outQty, 2),
                'adjustment' => round($adjustmentQty, 2),
                'closing' => round($closingQty, 2),
                'mutations' => (int)($row['mutation_count'] ?? 0),
                'total_value' => round((float)($row['total_value'] ?? 0), 2),
            ];

            $pivot[$profileKey]['daily'][$day] = $entry;
            $pivot[$profileKey]['summary']['in_total'] = round($pivot[$profileKey]['summary']['in_total'] + $entry['in'], 2);
            $pivot[$profileKey]['summary']['out_total'] = round($pivot[$profileKey]['summary']['out_total'] + $entry['out'], 2);
            $pivot[$profileKey]['summary']['adjustment_total'] = round($pivot[$profileKey]['summary']['adjustment_total'] + $entry['adjustment'], 2);
            $pivot[$profileKey]['summary']['closing_last'] = $entry['closing'];
            $pivot[$profileKey]['summary']['total_value_last'] = $entry['total_value'];
            if ((int)($row['audit_has_mismatch'] ?? 0) > 0) {
                $pivot[$profileKey]['audit_has_mismatch'] = 1;
                $pivot[$profileKey]['audit_mismatch_row_count'] = max(
                    (int)$pivot[$profileKey]['audit_mismatch_row_count'],
                    (int)($row['audit_mismatch_row_count'] ?? 0)
                );
                $pivot[$profileKey]['audit_mismatch_qty_content'] = round((float)($row['audit_mismatch_qty_content'] ?? 0), 4);
                if (trim((string)($row['audit_mismatch_notes'] ?? '')) !== '') {
                    $pivot[$profileKey]['audit_mismatch_notes'] = (string)$row['audit_mismatch_notes'];
                }
            }
        }

        return array_values($pivot);
    }

    private function resolveDailyMatrixAdjustmentQtyContent(array $row): float
    {
        $adjustmentQty = round((float)($row['adjustment_qty_content'] ?? 0), 4);
        if (abs($adjustmentQty) > 0.0001) {
            return $adjustmentQty;
        }

        $discardQty = round((float)($row['discarded_qty_content'] ?? 0), 4);
        $wasteQty = round((float)($row['waste_qty_content'] ?? 0), 4);
        $spoilQty = round((float)($row['spoil_qty_content'] ?? 0), 4);
        $processLossQty = round((float)($row['process_loss_qty_content'] ?? 0), 4);
        $varianceQty = round((float)($row['variance_qty_content'] ?? 0), 4);
        $adjustmentPlusQty = round((float)($row['adjustment_plus_qty_content'] ?? 0), 4);

        $negativeWasteQty = $discardQty > 0 ? $discardQty : $wasteQty;
        return $adjustmentPlusQty - $negativeWasteQty - $spoilQty - $processLossQty - $varianceQty;
    }

    private function resolveDailyMatrixAdjustmentQtyBuy(array $row): float
    {
        $adjustmentQty = round((float)($row['adjustment_qty_buy'] ?? 0), 4);
        if (abs($adjustmentQty) > 0.0001) {
            return $adjustmentQty;
        }

        $discardQty = round((float)($row['discarded_qty_buy'] ?? 0), 4);
        $wasteQty = round((float)($row['waste_qty_buy'] ?? 0), 4);
        $spoilQty = round((float)($row['spoil_qty_buy'] ?? 0), 4);
        $processLossQty = round((float)($row['process_loss_qty_buy'] ?? 0), 4);
        $varianceQty = round((float)($row['variance_qty_buy'] ?? 0), 4);
        $adjustmentPlusQty = round((float)($row['adjustment_plus_qty_buy'] ?? 0), 4);

        $negativeWasteQty = $discardQty > 0 ? $discardQty : $wasteQty;
        return $adjustmentPlusQty - $negativeWasteQty - $spoilQty - $processLossQty - $varianceQty;
    }

    private function filterZeroOpeningClosingDailyRows(array $rows): array
    {
        return array_values(array_filter($rows, static function (array $row): bool {
            $opening = round((float)($row['opening_qty_content'] ?? 0), 4);
            $closing = round((float)($row['closing_qty_content'] ?? 0), 4);
            $inQty = round((float)($row['in_qty_content'] ?? 0), 4);
            $outQty = round((float)($row['out_qty_content'] ?? 0), 4);
            $adjustment = round((float)($row['adjustment_qty_content'] ?? 0), 4);
            $discard = round((float)($row['discarded_qty_content'] ?? 0), 4);
            $waste = round((float)($row['waste_qty_content'] ?? 0), 4);
            $spoil = round((float)($row['spoil_qty_content'] ?? 0), 4);
            $processLoss = round((float)($row['process_loss_qty_content'] ?? 0), 4);
            $variance = round((float)($row['variance_qty_content'] ?? 0), 4);
            $adjustmentPlus = round((float)($row['adjustment_plus_qty_content'] ?? 0), 4);

            $hasMovement = abs($inQty) > 0.0001
                || abs($outQty) > 0.0001
                || abs($adjustment) > 0.0001
                || abs($discard) > 0.0001
                || abs($waste) > 0.0001
                || abs($spoil) > 0.0001
                || abs($processLoss) > 0.0001
                || abs($variance) > 0.0001
                || abs($adjustmentPlus) > 0.0001;

            return abs($opening) > 0.0001 || abs($closing) > 0.0001 || $hasMovement;
        }));
    }

    private function limitRowsByProfile(array $sourceRows, int $limitProfiles): array
    {
        $limitProfiles = max(1, min(1000, $limitProfiles));
        $accepted = [];
        $result = [];

        foreach ($sourceRows as $row) {
            $profileKey = implode('|', [
                (string)($row['division_id'] ?? ''),
                (string)($row['destination_type'] ?? ''),
                strtoupper((string)($row['stock_domain'] ?? '')),
                (int)($row['item_id'] ?? 0),
                (int)($row['material_id'] ?? 0),
                (int)($row['buy_uom_id'] ?? 0),
                (int)($row['content_uom_id'] ?? 0),
                (string)($row['profile_key'] ?? ''),
                strtoupper(trim((string)($row['profile_name'] ?? ''))),
                strtoupper(trim((string)($row['profile_brand'] ?? ''))),
                strtoupper(trim((string)($row['profile_description'] ?? ''))),
            ]);

            if (!isset($accepted[$profileKey]) && count($accepted) >= $limitProfiles) {
                continue;
            }

            $accepted[$profileKey] = true;
            $result[] = $row;
        }

        return $result;
    }

    private function resolveCatalogProfileKeyByIdentity(
        int $itemId,
        ?int $materialId,
        int $buyUomId,
        int $contentUomId,
        ?string $profileName,
        ?string $profileBrand,
        ?string $profileDescription,
        ?string $profileExpiredDate,
        float $profileContentPerBuy,
        ?float $profileUnitPrice = null
    ): ?string {
        if (!$this->db->table_exists('mst_purchase_catalog') || !$this->db->field_exists('profile_key', 'mst_purchase_catalog')) {
            return null;
        }

        $nameNorm = strtoupper(trim((string)$profileName));
        $brandNorm = strtoupper(trim((string)$profileBrand));
        $descNorm = strtoupper(trim((string)$profileDescription));
        $cpbNorm = number_format(max(0.000001, (float)$profileContentPerBuy), 6, '.', '');

        $hasCatalogItem = $this->db->field_exists('item_id', 'mst_purchase_catalog');
        $hasCatalogMaterial = $this->db->field_exists('material_id', 'mst_purchase_catalog');
        $hasCatalogBuyUom = $this->db->field_exists('buy_uom_id', 'mst_purchase_catalog');
        $hasCatalogContentUom = $this->db->field_exists('content_uom_id', 'mst_purchase_catalog');
        $hasCatalogContentPerBuy = $this->db->field_exists('content_per_buy', 'mst_purchase_catalog');
        $hasCatalogIsActive = $this->db->field_exists('is_active', 'mst_purchase_catalog');
        $hasCatalogLastPurchaseDate = $this->db->field_exists('last_purchase_date', 'mst_purchase_catalog');
        $hasCatalogLastUnitPrice = $this->db->field_exists('last_unit_price', 'mst_purchase_catalog');
        $hasCatalogStandardPrice = $this->db->field_exists('standard_price', 'mst_purchase_catalog');

        $candidateRows = $this->db
            ->select('c.id, c.profile_key' . ($hasCatalogIsActive ? ', c.is_active' : ''), false)
            ->from('mst_purchase_catalog c')
            ->where('c.profile_key IS NOT NULL', null, false)
            ->where("TRIM(c.profile_key) <> ''", null, false)
            ->where("UPPER(TRIM(COALESCE(c.catalog_name,''))) = " . $this->db->escape($nameNorm), null, false)
            ->where("UPPER(TRIM(COALESCE(c.brand_name,''))) = " . $this->db->escape($brandNorm), null, false)
            ->where("UPPER(TRIM(COALESCE(c.line_description,''))) = " . $this->db->escape($descNorm), null, false);

        if ($hasCatalogIsActive) {
            $this->db->where('COALESCE(c.is_active,1) = 1', null, false);
        }
        if ($hasCatalogItem) {
            $this->db->where('c.item_id', $itemId);
        }
        if ($hasCatalogMaterial && $materialId !== null && $materialId > 0) {
            $this->db->where('c.material_id', $materialId);
        }
        if ($hasCatalogBuyUom) {
            $this->db->where('c.buy_uom_id', $buyUomId);
        }
        if ($hasCatalogContentUom) {
            $this->db->where('c.content_uom_id', $contentUomId);
        }
        if ($hasCatalogContentPerBuy) {
            $this->db->where('ROUND(COALESCE(c.content_per_buy, 0), 6) = ' . $this->db->escape($cpbNorm), null, false);
        }
        if ($profileUnitPrice !== null && ($hasCatalogLastUnitPrice || $hasCatalogStandardPrice)) {
            $priceNorm = number_format(max(0, (float)$profileUnitPrice), 2, '.', '');
            $priceExpr = $hasCatalogLastUnitPrice && $hasCatalogStandardPrice
                ? 'ROUND(COALESCE(c.last_unit_price, c.standard_price, 0), 2)'
                : ($hasCatalogLastUnitPrice
                    ? 'ROUND(COALESCE(c.last_unit_price, 0), 2)'
                    : 'ROUND(COALESCE(c.standard_price, 0), 2)');
            $this->db->where($priceExpr . ' = ' . $this->db->escape($priceNorm), null, false);
        }

        $candidateRows = $candidateRows
            ->order_by('c.id', 'ASC')
            ->get()
            ->result_array();
        if (empty($candidateRows)) {
            return null;
        }

        $usageScores = $this->catalogProfileKeyUsageScores(array_values(array_map(static function (array $row): string {
            return trim((string)($row['profile_key'] ?? ''));
        }, $candidateRows)));
        usort($candidateRows, static function (array $a, array $b) use ($usageScores, $hasCatalogIsActive): int {
            $aKey = trim((string)($a['profile_key'] ?? ''));
            $bKey = trim((string)($b['profile_key'] ?? ''));
            $aScore = (int)($usageScores[$aKey] ?? 0);
            $bScore = (int)($usageScores[$bKey] ?? 0);
            if ($aScore !== $bScore) {
                return $bScore <=> $aScore;
            }

            if ($hasCatalogIsActive) {
                $aActive = (int)($a['is_active'] ?? 1);
                $bActive = (int)($b['is_active'] ?? 1);
                if ($aActive !== $bActive) {
                    return $bActive <=> $aActive;
                }
            }

            return (int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0);
        });

        $profileKey = trim((string)($candidateRows[0]['profile_key'] ?? ''));
        return $profileKey !== '' ? $profileKey : null;
    }

    private function isImpossibleSameUomProfileStructure(int $buyUomId, int $contentUomId, float $contentPerBuy): bool
    {
        return $buyUomId > 0
            && $contentUomId > 0
            && $buyUomId === $contentUomId
            && abs(round($contentPerBuy, 6) - 1.0) > 0.000001;
    }

    private function resolveLegacyMixedUomProfileStructure(
        int $itemId,
        ?int $materialId,
        ?string $profileName,
        ?string $profileBrand,
        ?string $profileDescription,
        float $profileContentPerBuy
    ): ?array {
        if (
            $itemId <= 0
            || !$this->db->table_exists('mst_purchase_catalog')
            || !$this->db->field_exists('profile_key', 'mst_purchase_catalog')
        ) {
            return null;
        }

        $nameNorm = strtoupper(trim((string)$profileName));
        $brandNorm = strtoupper(trim((string)$profileBrand));
        $descNorm = strtoupper(trim((string)$profileDescription));
        $cpbNorm = number_format(max(0.000001, (float)$profileContentPerBuy), 6, '.', '');

        $hasCatalogMaterial = $this->db->field_exists('material_id', 'mst_purchase_catalog');
        $hasCatalogIsActive = $this->db->field_exists('is_active', 'mst_purchase_catalog');
        $hasCatalogLastPurchaseDate = $this->db->field_exists('last_purchase_date', 'mst_purchase_catalog');

        $this->db
            ->select('c.id, c.profile_key, c.buy_uom_id, c.content_uom_id' . ($hasCatalogIsActive ? ', c.is_active' : '') . ($hasCatalogLastPurchaseDate ? ', c.last_purchase_date' : ''), false)
            ->from('mst_purchase_catalog c')
            ->where('c.item_id', $itemId)
            ->where('c.profile_key IS NOT NULL', null, false)
            ->where("TRIM(c.profile_key) <> ''", null, false)
            ->where("UPPER(TRIM(COALESCE(c.catalog_name,''))) = " . $this->db->escape($nameNorm), null, false)
            ->where("UPPER(TRIM(COALESCE(c.brand_name,''))) = " . $this->db->escape($brandNorm), null, false)
            ->where("UPPER(TRIM(COALESCE(c.line_description,''))) = " . $this->db->escape($descNorm), null, false)
            ->where('ROUND(COALESCE(c.content_per_buy, 0), 6) = ' . $this->db->escape($cpbNorm), null, false)
            ->where('COALESCE(c.buy_uom_id, 0) > 0', null, false)
            ->where('COALESCE(c.content_uom_id, 0) > 0', null, false)
            ->where('COALESCE(c.buy_uom_id, 0) <> COALESCE(c.content_uom_id, 0)', null, false);

        if ($hasCatalogMaterial && $materialId !== null && $materialId > 0) {
            $this->db->where('c.material_id', $materialId);
        }

        if ($hasCatalogIsActive) {
            $this->db->order_by('COALESCE(c.is_active, 1)', 'DESC', false);
        }
        if ($hasCatalogLastPurchaseDate) {
            $this->db->order_by('c.last_purchase_date', 'DESC');
        }

        $row = $this->db
            ->order_by('c.id', 'ASC')
            ->limit(1)
            ->get()
            ->row_array();

        return !empty($row) ? $row : null;
    }

    private function ensureCatalogProfileFromOpeningIdentity(
        string $stockDomain,
        int $itemId,
        ?int $materialId,
        int $buyUomId,
        int $contentUomId,
        ?string $profileName,
        ?string $profileBrand,
        ?string $profileDescription,
        ?string $profileExpiredDate,
        float $profileContentPerBuy,
        ?float $profileUnitPrice = null
    ): ?string {
        if (!$this->db->table_exists('mst_purchase_catalog')) {
            return null;
        }

        $existingKey = $this->resolveCatalogProfileKeyByIdentity(
            $itemId,
            $materialId,
            $buyUomId,
            $contentUomId,
            $profileName,
            $profileBrand,
            $profileDescription,
            $profileExpiredDate,
            $profileContentPerBuy,
            $profileUnitPrice
        );
        if ($existingKey !== null) {
            return $existingKey;
        }

        $catalogColumns = $this->listTableFields('mst_purchase_catalog');
        if (empty($catalogColumns)) {
            return null;
        }

        $catalogHasVendorId = isset($catalogColumns['vendor_id']);
        $vendorId = $catalogHasVendorId ? $this->resolveCatalogFallbackVendorId() : null;
        if ($catalogHasVendorId && ($vendorId === null || $vendorId <= 0)) {
            return null;
        }

        $catalogName = trim((string)$profileName);
        if ($catalogName === '' && $this->db->table_exists('mst_item')) {
            $itemRow = $this->db
                ->select('i.item_name, m.material_name')
                ->from('mst_item i')
                ->join('mst_material m', 'm.id = i.material_id', 'left')
                ->where('i.id', $itemId)
                ->limit(1)
                ->get()
                ->row_array();
            $catalogName = trim((string)($itemRow['item_name'] ?? ''));
            if ($catalogName === '') {
                $catalogName = trim((string)($itemRow['material_name'] ?? ''));
            }
        }
        if ($catalogName === '') {
            $catalogName = 'OPENING PROFILE ' . $itemId;
        }

        $contentPerBuy = max(0.000001, (float)$profileContentPerBuy);
        $lineKind = ($itemId !== null && $itemId > 0) ? 'ITEM' : (($materialId !== null && $materialId > 0) ? 'MATERIAL' : 'ITEM');
        $profileKey = hash('sha256', implode('|', [
            strtoupper(trim($stockDomain)),
            (string)$itemId,
            (string)($materialId ?? 0),
            (string)$buyUomId,
            (string)$contentUomId,
            strtoupper(trim((string)$catalogName)),
            strtoupper(trim((string)$profileBrand)),
            strtoupper(trim((string)$profileDescription)),
            number_format($contentPerBuy, 6, '.', ''),
            number_format(max(0, (float)$profileUnitPrice), 2, '.', ''),
        ]));

        $upsertData = [
            'profile_key' => $profileKey,
            'line_kind' => $lineKind,
            'item_id' => $itemId,
            'material_id' => $materialId,
            'catalog_name' => $catalogName,
            'brand_name' => $this->nullableString($profileBrand),
            'line_description' => $this->normalizeProfileDescription($profileDescription),
            'buy_uom_id' => $buyUomId,
            'content_uom_id' => $contentUomId,
            'content_per_buy' => $contentPerBuy,
            'conversion_factor_to_content' => $contentPerBuy,
            'is_active' => 1,
            'notes' => 'Auto-created from opening identity',
        ];
        if (isset($catalogColumns['standard_price'])) {
            $upsertData['standard_price'] = round(max(0, (float)$profileUnitPrice), 2);
        }
        if (isset($catalogColumns['last_unit_price'])) {
            $upsertData['last_unit_price'] = round(max(0, (float)$profileUnitPrice), 2);
        }
        if ($catalogHasVendorId) {
            $upsertData['vendor_id'] = $vendorId;
        }
        $filteredData = [];
        foreach ($upsertData as $column => $value) {
            if (isset($catalogColumns[$column])) {
                $filteredData[$column] = $value;
            }
        }

        if (!isset($filteredData['profile_key']) || !isset($filteredData['catalog_name']) || !isset($filteredData['buy_uom_id'])) {
            return null;
        }

        $existing = $this->db->get_where('mst_purchase_catalog', ['profile_key' => $profileKey])->row_array();
        if ($existing) {
            $updateData = [];
            foreach (['catalog_name', 'brand_name', 'line_description', 'buy_uom_id', 'content_uom_id', 'content_per_buy', 'conversion_factor_to_content', 'standard_price', 'last_unit_price', 'is_active', 'notes'] as $col) {
                if (array_key_exists($col, $filteredData)) {
                    $updateData[$col] = $filteredData[$col];
                }
            }
            if ($catalogHasVendorId && $vendorId !== null && $vendorId > 0) {
                $updateData['vendor_id'] = $vendorId;
            }
            if (!empty($updateData)) {
                $this->db->where('id', (int)$existing['id'])->update('mst_purchase_catalog', $updateData);
            }
        } else {
            $this->db->insert('mst_purchase_catalog', $filteredData);
            if ((int)$this->db->affected_rows() <= 0) {
                return null;
            }
        }

        return $this->resolveCatalogProfileKeyByIdentity(
            $itemId,
            $materialId,
            $buyUomId,
            $contentUomId,
            $catalogName,
            $profileBrand,
            $profileDescription,
            $profileExpiredDate,
            $contentPerBuy,
            $profileUnitPrice
        );
    }

    private function resolveCatalogFallbackVendorId(): ?int
    {
        if (!$this->db->table_exists('mst_vendor')) {
            return null;
        }

        $activeVendor = $this->db
            ->select('id')
            ->from('mst_vendor')
            ->where('is_active', 1)
            ->order_by('id', 'ASC')
            ->limit(1)
            ->get()
            ->row_array();
        $activeVendorId = (int)($activeVendor['id'] ?? 0);
        if ($activeVendorId > 0) {
            return $activeVendorId;
        }

        $anyVendor = $this->db
            ->select('id')
            ->from('mst_vendor')
            ->order_by('id', 'ASC')
            ->limit(1)
            ->get()
            ->row_array();
        $anyVendorId = (int)($anyVendor['id'] ?? 0);

        return $anyVendorId > 0 ? $anyVendorId : null;
    }

    private function resolveExistingOpeningProfileKey(
        string $stockScope,
        ?int $divisionId,
        ?string $destinationType,
        int $itemId,
        ?int $materialId,
        int $buyUomId,
        int $contentUomId,
        ?string $profileName,
        ?string $profileBrand,
        ?string $profileDescription,
        ?string $profileExpiredDate,
        float $profileContentPerBuy
    ): ?string {
        if (!$this->db->table_exists('inv_stock_movement_log')) {
            return null;
        }

        $hasDestinationType = $this->db->field_exists('destination_type', 'inv_stock_movement_log');

        $nameNorm = strtoupper(trim((string)$profileName));
        $brandNorm = strtoupper(trim((string)$profileBrand));
        $descNorm = strtoupper(trim((string)$profileDescription));
        $cpbNorm = number_format(round($profileContentPerBuy, 6), 6, '.', '');

        $this->db
            ->select('l.profile_key')
            ->from('inv_stock_movement_log l')
            ->where('l.movement_scope', $stockScope)
            ->where('l.item_id', $itemId)
            ->where('l.buy_uom_id', $buyUomId)
            ->where('l.content_uom_id', $contentUomId)
            ->where('l.profile_key IS NOT NULL', null, false)
            ->where("TRIM(l.profile_key) <> ''", null, false)
            ->where("UPPER(TRIM(COALESCE(l.profile_name,''))) = " . $this->db->escape($nameNorm), null, false)
            ->where("UPPER(TRIM(COALESCE(l.profile_brand,''))) = " . $this->db->escape($brandNorm), null, false)
            ->where("UPPER(TRIM(COALESCE(l.profile_description,''))) = " . $this->db->escape($descNorm), null, false)
            ->where('ROUND(COALESCE(l.profile_content_per_buy, 0), 6) = ' . $this->db->escape($cpbNorm), null, false);

        if ($materialId !== null && $materialId > 0) {
            $this->db->where('l.material_id', $materialId);
        } else {
            $this->db->where('l.material_id IS NULL', null, false);
        }

        if ($stockScope === 'DIVISION' && $divisionId !== null && $divisionId > 0) {
            $this->db->where('l.division_id', $divisionId);
        }

        if ($stockScope === 'DIVISION' && $destinationType !== null && $hasDestinationType) {
            $this->db->where('l.destination_type', strtoupper(trim((string)$destinationType)));
        }

        $row = $this->db
            ->order_by('l.movement_date', 'DESC')
            ->order_by('l.id', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();

        $profileKey = trim((string)($row['profile_key'] ?? ''));
        if ($profileKey !== '') {
            return $profileKey;
        }

        // Fallback guard: when text/UOM identity is same, reuse latest profile key
        // even if historical content-per-buy differs.
        $this->db
            ->select('l.profile_key')
            ->from('inv_stock_movement_log l')
            ->where('l.movement_scope', $stockScope)
            ->where('l.item_id', $itemId)
            ->where('l.buy_uom_id', $buyUomId)
            ->where('l.content_uom_id', $contentUomId)
            ->where('l.profile_key IS NOT NULL', null, false)
            ->where("TRIM(l.profile_key) <> ''", null, false)
            ->where("UPPER(TRIM(COALESCE(l.profile_name,''))) = " . $this->db->escape($nameNorm), null, false)
            ->where("UPPER(TRIM(COALESCE(l.profile_brand,''))) = " . $this->db->escape($brandNorm), null, false)
            ->where("UPPER(TRIM(COALESCE(l.profile_description,''))) = " . $this->db->escape($descNorm), null, false);

        if ($materialId !== null && $materialId > 0) {
            $this->db->where('l.material_id', $materialId);
        } else {
            $this->db->where('l.material_id IS NULL', null, false);
        }

        if ($stockScope === 'DIVISION' && $divisionId !== null && $divisionId > 0) {
            $this->db->where('l.division_id', $divisionId);
        }

        if ($stockScope === 'DIVISION' && $destinationType !== null && $hasDestinationType) {
            $this->db->where('l.destination_type', strtoupper(trim((string)$destinationType)));
        }

        $rowLoose = $this->db
            ->order_by('l.movement_date', 'DESC')
            ->order_by('l.id', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();

        $profileKeyLoose = trim((string)($rowLoose['profile_key'] ?? ''));
        if ($profileKeyLoose !== '') {
            return $profileKeyLoose;
        }

        if ($this->db->table_exists('mst_purchase_catalog') && $this->db->field_exists('profile_key', 'mst_purchase_catalog')) {
            $hasCatalogItem = $this->db->field_exists('item_id', 'mst_purchase_catalog');
            $hasCatalogMaterial = $this->db->field_exists('material_id', 'mst_purchase_catalog');
            $hasCatalogBuyUom = $this->db->field_exists('buy_uom_id', 'mst_purchase_catalog');
            $hasCatalogContentUom = $this->db->field_exists('content_uom_id', 'mst_purchase_catalog');
            $hasCatalogContentPerBuy = $this->db->field_exists('content_per_buy', 'mst_purchase_catalog');
            $hasCatalogIsActive = $this->db->field_exists('is_active', 'mst_purchase_catalog');

            $this->db
                ->select('c.profile_key')
                ->from('mst_purchase_catalog c')
                ->where('c.profile_key IS NOT NULL', null, false)
                ->where("TRIM(c.profile_key) <> ''", null, false)
                ->where("UPPER(TRIM(COALESCE(c.catalog_name,''))) = " . $this->db->escape($nameNorm), null, false)
                ->where("UPPER(TRIM(COALESCE(c.brand_name,''))) = " . $this->db->escape($brandNorm), null, false)
                ->where("UPPER(TRIM(COALESCE(c.line_description,''))) = " . $this->db->escape($descNorm), null, false);

            if ($hasCatalogIsActive) {
                $this->db->where('COALESCE(c.is_active,1) = 1', null, false);
            }
            if ($hasCatalogItem) {
                $this->db->where('c.item_id', $itemId);
            }
            if ($hasCatalogMaterial && $materialId !== null && $materialId > 0) {
                $this->db->where('c.material_id', $materialId);
            }
            if ($hasCatalogBuyUom) {
                $this->db->where('c.buy_uom_id', $buyUomId);
            }
            if ($hasCatalogContentUom) {
                $this->db->where('c.content_uom_id', $contentUomId);
            }
            if ($hasCatalogContentPerBuy) {
                $this->db->where('ROUND(COALESCE(c.content_per_buy, 0), 6) = ' . $this->db->escape($cpbNorm), null, false);
            }

            $catalogRow = $this->db
                ->order_by('c.id', 'DESC')
                ->limit(1)
                ->get()
                ->row_array();

            $catalogProfileKey = trim((string)($catalogRow['profile_key'] ?? ''));
            if ($catalogProfileKey !== '') {
                return $catalogProfileKey;
            }
        }

        return null;
    }

    private function openingSnapshotTableForScope(string $scope): string
    {
        $scope = strtoupper(trim($scope));
        if ($scope === 'DIVISION') {
            return 'inv_division_stock_opening_snapshot';
        }

        return 'inv_warehouse_stock_opening_snapshot';
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

    private function normalizeExpiryPolicy($value): string
    {
        $policy = strtoupper(trim((string)$value));
        if (in_array($policy, ['NONE', 'EXACT_DATE', 'MIN_REMAINING_DAYS'], true)) {
            return $policy;
        }
        return 'NONE';
    }

    private function normalizeMinRemainingDays($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $days = (int)$value;
        return $days > 0 ? $days : null;
    }

    private function extractOrderLineExpiryRequirement(array $source, string $legacyDateKey = 'expired_date'): array
    {
        $requiredDate = $this->normalizeDate((string)($source['required_expiry_date'] ?? ($source[$legacyDateKey] ?? '')));
        $minRemainingDays = $this->normalizeMinRemainingDays($source['min_remaining_days'] ?? null);
        $policy = $this->normalizeExpiryPolicy($source['expiry_policy'] ?? '');

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

        return [
            'expiry_policy' => $policy,
            'required_expiry_date' => $requiredDate,
            'min_remaining_days' => $minRemainingDays,
        ];
    }

    private function appendOrderLineExpiryRequirementColumns(string $table, array &$target, array $source, string $legacyDateKey = 'expired_date'): void
    {
        if (!$this->db->table_exists($table)) {
            return;
        }

        $expiry = $this->extractOrderLineExpiryRequirement($source, $legacyDateKey);
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

    private function normalizeDestinationFilter(?string $value): ?string
    {
        $value = strtoupper(trim((string)$value));
        if ($value === '' || $value === 'ALL') {
            return null;
        }

        if (in_array($value, ['REGULER', 'EVENT'], true)) {
            return $value;
        }

        return $this->normalizeDestination($value);
    }

    private function listTableFields(string $table): array
    {
        if (isset($this->tableFieldsCache[$table])) {
            return $this->tableFieldsCache[$table];
        }

        if (!$this->db->table_exists($table)) {
            $this->tableFieldsCache[$table] = [];
            return $this->tableFieldsCache[$table];
        }

        $fields = $this->db->list_fields($table);
        $map = [];
        foreach ($fields as $field) {
            $map[(string)$field] = true;
        }

        $this->tableFieldsCache[$table] = $map;
        return $this->tableFieldsCache[$table];
    }

    private function columnAllowsNull(string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, $this->columnNullableCache)) {
            return $this->columnNullableCache[$cacheKey];
        }

        $row = $this->db
            ->select('IS_NULLABLE')
            ->from('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $this->db->database)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->limit(1)
            ->get()
            ->row_array();

        $this->columnNullableCache[$cacheKey] = strtoupper((string)($row['IS_NULLABLE'] ?? 'NO')) === 'YES';
        return $this->columnNullableCache[$cacheKey];
    }

    private function legacyLineKindForStorage(string $table, ?string $lineKind, ?int $itemId, ?int $materialId): ?string
    {
        if (!isset($this->listTableFields($table)['line_kind'])) {
            return null;
        }

        if ($this->columnAllowsNull($table, 'line_kind')) {
            return null;
        }

        $resolved = strtoupper(trim((string)$lineKind));
        if (!in_array($resolved, ['ITEM', 'MATERIAL', 'SERVICE', 'ASSET'], true)) {
            $resolved = 'ITEM';
        }

        // In the item-centric flow, raw material is kept as a marker on the
        // canonical item, not as a separate inventory line domain.
        if ($resolved === 'MATERIAL') {
            $resolved = 'ITEM';
        }

        return $resolved;
    }

    private function ensureUsagePurposeSchema(): void
    {
        if ($this->usagePurposeSchemaEnsured) {
            return;
        }
        $this->usagePurposeSchemaEnsured = true;

        $this->ensureTableColumn('mst_item', 'default_usage_purpose', "VARCHAR(20) NOT NULL DEFAULT 'BAHAN_BAKU'");
        $this->ensureTableColumn('pur_purchase_order_line', 'usage_purpose', "VARCHAR(20) NOT NULL DEFAULT 'BAHAN_BAKU'");
        $this->ensureTableColumn('pur_purchase_receipt_line', 'usage_purpose', "VARCHAR(20) NOT NULL DEFAULT 'BAHAN_BAKU'");
        $this->ensureTableColumn('pur_store_request_fulfillment_line', 'usage_purpose', "VARCHAR(20) NOT NULL DEFAULT 'BAHAN_BAKU'");
    }

    private function ensureTableColumn(string $table, string $column, string $definition): void
    {
        if (!$this->db->table_exists($table) || $this->db->field_exists($column, $table)) {
            return;
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $this->db->query(sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $table, $column, $definition));
        } catch (Throwable $e) {
            // Keep feature fallback-safe for partially patched environments.
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }
    }

    private function hasPurchaseOrderUsagePurposeColumn(): bool
    {
        $this->ensureUsagePurposeSchema();
        return isset($this->listTableFields('pur_purchase_order_line')['usage_purpose']);
    }

    private function hasPurchaseReceiptUsagePurposeColumn(): bool
    {
        $this->ensureUsagePurposeSchema();
        return isset($this->listTableFields('pur_purchase_receipt_line')['usage_purpose']);
    }

    private function normalizeUsagePurpose($value): string
    {
        return strtoupper(trim((string)$value)) === self::USAGE_PURPOSE_OPERATIONAL
            ? self::USAGE_PURPOSE_OPERATIONAL
            : self::USAGE_PURPOSE_PRODUCTION;
    }

    private function usagePurposeLabel($value): string
    {
        return $this->normalizeUsagePurpose($value) === self::USAGE_PURPOSE_OPERATIONAL
            ? 'Kebutuhan Operasional'
            : 'Persediaan Produksi';
    }

    private function parseUsagePurposeFromNotes(?string $notes): string
    {
        $notes = (string)$notes;
        if (preg_match('/Tujuan\s+pemakaian\s*:\s*(Kebutuhan\s+Operasional|Persediaan\s+Produksi|Operasional|Bahan\s+Baku)/i', $notes, $matches)) {
            $label = strtoupper(trim((string)$matches[1]));
            return in_array($label, ['KEBUTUHAN OPERASIONAL', 'OPERASIONAL'], true)
                ? self::USAGE_PURPOSE_OPERATIONAL
                : self::USAGE_PURPOSE_PRODUCTION;
        }
        return self::USAGE_PURPOSE_PRODUCTION;
    }

    private function resolveLineUsagePurpose(array $line): string
    {
        if (array_key_exists('usage_purpose', $line) && trim((string)$line['usage_purpose']) !== '') {
            return $this->normalizeUsagePurpose($line['usage_purpose']);
        }
        if (array_key_exists('default_usage_purpose', $line) && trim((string)$line['default_usage_purpose']) !== '') {
            return $this->normalizeUsagePurpose($line['default_usage_purpose']);
        }
        return $this->parseUsagePurposeFromNotes((string)($line['notes'] ?? ''));
    }

    private function itemIdentityResolver(): ItemIdentityResolver
    {
        if (!$this->itemIdentityResolverInstance instanceof ItemIdentityResolver) {
            $this->load->library('ItemIdentityResolver');
            $this->itemIdentityResolverInstance = $this->itemidentityresolver;
        }

        return $this->itemIdentityResolverInstance;
    }

    private function resolveCanonicalTransactionIdentity(array $line, ?string $usagePurpose = null): array
    {
        return $this->itemIdentityResolver()->resolveTransactionIdentity(
            $line,
            $usagePurpose ?? $this->resolveLineUsagePurpose($line)
        );
    }

    private function resolveCanonicalStockWriteContext(array $line, ?string $usagePurpose = null): array
    {
        $usagePurpose = $usagePurpose ?? $this->resolveLineUsagePurpose($line);
        $identity = $this->resolveCanonicalTransactionIdentity($line, $usagePurpose);
        $itemId = $this->nullableInt($identity['item_id'] ?? ($line['item_id'] ?? null));
        $materialId = $this->nullableInt($identity['material_id'] ?? ($line['material_id'] ?? null));
        $hasMaterialMarker = $usagePurpose === self::USAGE_PURPOSE_PRODUCTION && $materialId !== null;
        $legacyStockDomain = $this->resolveLegacyIdentityStockDomain($itemId, $materialId, $line['stock_domain'] ?? null);

        return [
            'usage_purpose' => $usagePurpose,
            'item_id' => $itemId,
            'material_id' => $materialId,
            'stock_domain' => 'ITEM',
            'is_production_flow' => $usagePurpose === self::USAGE_PURPOSE_PRODUCTION,
            'has_material_marker' => $hasMaterialMarker,
            'uses_material_fifo' => $hasMaterialMarker,
        ];
    }

    private function resolveLegacyIdentityStockDomain(?int $itemId, ?int $materialId, $currentValue = null): string
    {
        if ($itemId !== null || $materialId !== null) {
            return 'ITEM';
        }
        return 'ITEM';
    }

    private function resolveLineStockDomain(array $line): string
    {
        return 'ITEM';
    }

    private function resolveLineMaterialIdForStock(array $line): ?int
    {
        return $this->resolveLineUsagePurpose($line) === self::USAGE_PURPOSE_OPERATIONAL
            ? null
            : $this->resolveProductionMaterialIdFromLine($line);
    }

    private function resolveProductionMaterialIdFromLine(array $line): ?int
    {
        $materialId = $this->nullableInt($line['material_id'] ?? null);
        if ($materialId !== null && $materialId > 0) {
            return $materialId;
        }

        $itemId = $this->nullableInt($line['item_id'] ?? null);
        if ($itemId === null || $itemId <= 0 || !$this->db->table_exists('mst_item') || !$this->db->field_exists('material_id', 'mst_item')) {
            return null;
        }

        $row = $this->db
            ->select('material_id')
            ->from('mst_item')
            ->where('id', $itemId)
            ->limit(1)
            ->get()
            ->row_array();

        $resolved = (int)($row['material_id'] ?? 0);
        return $resolved > 0 ? $resolved : null;
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

    private function normalizeProfileDescription($value): ?string
    {
        $description = $this->nullableString(preg_replace('/\s+/', ' ', trim((string)$value)));
        if ($description === null) {
            return null;
        }

        $normalized = strtoupper($description);
        if (
            preg_match('/^(IMPORT\s+DARI|OPENING\b|DARI\s+PENGAJUAN|AUTO[- ]CREATED FROM OPENING IDENTITY)\b/u', $normalized)
        ) {
            return null;
        }

        return $description;
    }

    private function normalizeRebuildStatuses($value): array
    {
        $allowed = ['DRAFT', 'APPROVED', 'ORDERED', 'REJECTED', 'PARTIAL_RECEIVED', 'RECEIVED', 'PAID', 'VOID'];
        $raw = [];

        if (is_string($value)) {
            $raw = preg_split('/[\s,]+/', $value) ?: [];
        } elseif (is_array($value)) {
            $raw = $value;
        }

        $result = [];
        foreach ($raw as $item) {
            $status = strtoupper(trim((string)$item));
            if ($status === '' || !in_array($status, $allowed, true)) {
                continue;
            }
            $result[$status] = true;
        }

        return array_keys($result);
    }

    private function rebuildEffectChanged(array $effectData): bool
    {
        if (empty($effectData)) {
            return false;
        }

        return empty((string)($effectData['skipped'] ?? ''));
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

    private function find_quick_vendor_match(string $vendorCode, string $vendorName): ?array
    {
        $select = 'id, vendor_code, vendor_name';
        if ($this->db->field_exists('is_active', 'mst_vendor')) {
            $select .= ', is_active';
        }

        if ($vendorCode !== '' && $this->db->field_exists('vendor_code', 'mst_vendor')) {
            $row = $this->db
                ->select($select, false)
                ->from('mst_vendor')
                ->where('vendor_code', $vendorCode)
                ->order_by('id', 'ASC')
                ->limit(1)
                ->get()
                ->row_array();
            if ($row) {
                return $row;
            }
        }

        if ($vendorName !== '' && $this->db->field_exists('vendor_name', 'mst_vendor')) {
            $sql = 'SELECT ' . $select . ' FROM mst_vendor WHERE UPPER(TRIM(vendor_name)) = ? ORDER BY id ASC LIMIT 1';
            $row = $this->db->query($sql, [strtoupper($vendorName)])->row_array();
            if ($row) {
                return $row;
            }
        }

        return null;
    }

    private function get_vendor_quick_row(int $vendorId): ?array
    {
        if ($vendorId <= 0 || !$this->db->table_exists('mst_vendor')) {
            return null;
        }

        $row = $this->db
            ->select('id, vendor_code, vendor_name')
            ->from('mst_vendor')
            ->where('id', $vendorId)
            ->limit(1)
            ->get()
            ->row_array();

        return $row ?: null;
    }

    private function generate_quick_vendor_code(string $vendorName): string
    {
        $base = strtoupper(trim($vendorName));
        $base = preg_replace('/[^A-Z0-9]+/', '-', $base);
        $base = trim((string)$base, '-');
        if ($base === '') {
            $base = 'VENDOR';
        }
        $base = substr($base, 0, 24);

        $candidate = 'VND-' . $base;
        $suffix = 2;
        while ($this->quick_vendor_code_exists($candidate)) {
            $suffixLabel = '-' . $suffix;
            $candidate = 'VND-' . substr($base, 0, max(1, 24 - strlen($suffixLabel))) . $suffixLabel;
            $suffix++;
            if ($suffix > 999) {
                $candidate = 'VND-' . date('YmdHis');
                break;
            }
        }

        return $candidate;
    }

    private function quick_vendor_code_exists(string $vendorCode): bool
    {
        if ($vendorCode === '' || !$this->db->table_exists('mst_vendor') || !$this->db->field_exists('vendor_code', 'mst_vendor')) {
            return false;
        }

        $row = $this->db
            ->select('id')
            ->from('mst_vendor')
            ->where('vendor_code', $vendorCode)
            ->limit(1)
            ->get()
            ->row_array();

        return !empty($row);
    }

    public function list_warehouse_monthly_opname(array $filters = [], int $limit = 500): array
    {
        if (!$this->db->table_exists('inv_warehouse_monthly_opname')) {
            return [];
        }
        $month = trim((string)($filters['month'] ?? date('Y-m')));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $monthKey = $month . '-01';

        $this->db->select('o.*, e.employee_name AS generated_by_name', false)
            ->from('inv_warehouse_monthly_opname o')
            ->join('org_employee e', 'e.id = o.generated_by', 'left')
            ->where('o.month_key', $monthKey);

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $this->db->group_start()
                ->like('o.profile_name', $q)
                ->or_like('o.profile_key', $q)
                ->group_end();
        }
        $this->db->order_by('o.stock_domain, o.profile_name', '', false);
        if ($limit > 0) {
            $this->db->limit($limit);
        }
        return $this->db->get()->result_array();
    }

    public function list_division_monthly_opname(array $filters = [], int $limit = 500): array
    {
        if (!$this->db->table_exists('inv_division_monthly_opname')) {
            return [];
        }
        $month = trim((string)($filters['month'] ?? date('Y-m')));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $monthKey = $month . '-01';

        $this->db->select('o.*, d.division_name, e.employee_name AS generated_by_name', false)
            ->from('inv_division_monthly_opname o')
            ->join('org_division d', 'd.id = o.division_id', 'left')
            ->join('org_employee e', 'e.id = o.generated_by', 'left')
            ->where('o.month_key', $monthKey);

        $divisionId = (int)($filters['division_id'] ?? 0);
        if ($divisionId > 0) {
            $this->db->where('o.division_id', $divisionId);
        }
        $destination = strtoupper(trim((string)($filters['destination_type'] ?? '')));
        if ($destination !== '') {
            $this->db->where('o.destination_type', $destination);
        }
        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $this->db->group_start()
                ->like('o.profile_name', $q)
                ->or_like('d.division_name', $q)
                ->group_end();
        }
        $this->db->order_by('d.division_name, o.destination_type, o.profile_name', '', false);
        if ($limit > 0) {
            $this->db->limit($limit);
        }
        return $this->db->get()->result_array();
    }
}
