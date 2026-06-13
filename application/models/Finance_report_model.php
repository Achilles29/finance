<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Finance_report_model extends CI_Model
{
    private $tableFieldCache = [];

    private function table_has_field(string $table, string $field): bool
    {
        $key = $table . '.' . $field;
        if (!array_key_exists($key, $this->tableFieldCache)) {
            $this->tableFieldCache[$key] = $this->db->field_exists($field, $table);
        }
        return (bool)$this->tableFieldCache[$key];
    }

    public function active_company_accounts(): array
    {
        if (!$this->db->table_exists('fin_company_account')) {
            return [];
        }

        return $this->db->select('id, account_code, account_name, account_type, bank_name, account_no, opening_balance, current_balance, is_default')
            ->from('fin_company_account')
            ->where('is_active', 1)
            ->order_by("CASE WHEN account_type = 'CASH' THEN 0 ELSE 1 END", '', false)
            ->order_by('is_default', 'DESC')
            ->order_by('account_name', 'ASC')
            ->order_by('id', 'ASC')
            ->get()->result_array();
    }

    public function default_cash_account_id(array $accounts = []): int
    {
        $rows = is_array($accounts) ? $accounts : [];
        if (empty($rows)) {
            $rows = $this->active_company_accounts();
        }
        if (empty($rows)) {
            return 0;
        }

        foreach ($rows as $row) {
            $accountType = strtoupper(trim((string)($row['account_type'] ?? '')));
            $haystack = strtoupper(trim(
                (string)($row['account_name'] ?? '') . ' ' .
                (string)($row['bank_name'] ?? '') . ' ' .
                (string)($row['account_code'] ?? '')
            ));
            if ($accountType === 'CASH' || strpos($haystack, 'BRANKAS') !== false || strpos($haystack, 'TUNAI') !== false || strpos($haystack, 'KAS') !== false) {
                return (int)($row['id'] ?? 0);
            }
        }

        foreach ($rows as $row) {
            if ((int)($row['is_default'] ?? 0) === 1) {
                return (int)($row['id'] ?? 0);
            }
        }

        return (int)($rows[0]['id'] ?? 0);
    }

    public function cash_vault_daily($month, $accountId): array
    {
        $month = trim((string)$month);
        if (!preg_match('/^\d{4}\-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $accountId = (int)$accountId;
        if ($accountId <= 0 || !$this->db->table_exists('fin_company_account') || !$this->db->table_exists('fin_account_mutation_log')) {
            return $this->empty_cash_vault_report($month);
        }

        $account = $this->db->select('id, account_code, account_name, account_type, bank_name, account_no, opening_balance, current_balance')
            ->from('fin_company_account')
            ->where('id', $accountId)
            ->where('is_active', 1)
            ->get()
            ->row_array();
        if (!$account) {
            return $this->empty_cash_vault_report($month);
        }

        $dateStart = $month . '-01';
        $dateEnd = date('Y-m-t', strtotime($dateStart));

        $dailyRows = $this->db->select("\n                mutation_date,\n                COALESCE(SUM(CASE\n                    WHEN mutation_type = 'IN'\n                     AND ref_module = 'POS'\n                     AND ref_table IN ('pos_payment', 'pos_payment_line')\n                    THEN amount ELSE 0 END), 0) AS pendapatan,\n                COALESCE(SUM(CASE\n                    WHEN mutation_type = 'IN'\n                     AND ref_module = 'FINANCE_TRANSFER'\n                    THEN amount ELSE 0 END), 0) AS rekening_masuk,\n                COALESCE(SUM(CASE\n                    WHEN mutation_type = 'OUT'\n                     AND ref_module = 'FINANCE_TRANSFER'\n                    THEN amount ELSE 0 END), 0) AS rekening_keluar,\n                COALESCE(SUM(CASE\n                    WHEN mutation_type = 'OUT'\n                     AND ref_module = 'POS'\n                     AND ref_table = 'pos_refund'\n                    THEN amount ELSE 0 END), 0) AS refund,\n                COALESCE(SUM(CASE\n                    WHEN mutation_type = 'OUT'\n                     AND ref_module = 'PURCHASE'\n                    THEN amount ELSE 0 END), 0) AS belanja,\n                COALESCE(SUM(CASE\n                    WHEN mutation_type = 'IN'\n                     AND NOT (\n                        ref_module = 'POS'\n                        AND ref_table IN ('pos_payment', 'pos_payment_line')\n                     )\n                     AND ref_module <> 'FINANCE_TRANSFER'\n                    THEN amount ELSE 0 END), 0) AS kas_masuk,\n                COALESCE(SUM(CASE\n                    WHEN mutation_type = 'OUT'\n                     AND ref_module <> 'FINANCE_TRANSFER'\n                     AND NOT (ref_module = 'POS' AND ref_table = 'pos_refund')\n                     AND ref_module <> 'PURCHASE'\n                    THEN amount ELSE 0 END), 0) AS kas_keluar\n            ", false)
            ->from('fin_account_mutation_log')
            ->where('account_id', $accountId)
            ->where('mutation_date >=', $dateStart)
            ->where('mutation_date <=', $dateEnd)
            ->group_by('mutation_date')
            ->order_by('mutation_date', 'ASC')
            ->get()
            ->result_array();

        $dailyMap = [];
        foreach ($dailyRows as $row) {
            $dateKey = (string)($row['mutation_date'] ?? '');
            if ($dateKey === '') {
                continue;
            }

            $dailyMap[$dateKey] = [
                'pendapatan' => round((float)($row['pendapatan'] ?? 0), 2),
                'kas_masuk' => round((float)($row['kas_masuk'] ?? 0), 2),
                'kas_keluar' => round((float)($row['kas_keluar'] ?? 0), 2),
                'rekening_masuk' => round((float)($row['rekening_masuk'] ?? 0), 2),
                'rekening_keluar' => round((float)($row['rekening_keluar'] ?? 0), 2),
                'refund' => round((float)($row['refund'] ?? 0), 2),
                'belanja' => round((float)($row['belanja'] ?? 0), 2),
            ];
        }

        $openingBalance = $this->opening_balance_for_month($accountId, $dateStart, (float)($account['opening_balance'] ?? 0));
        $running = $openingBalance;
        $rows = [];
        $totals = [
            'opening_balance' => round($openingBalance, 2),
            'closing_balance' => round($openingBalance, 2),
            'pendapatan' => 0.0,
            'kas_masuk' => 0.0,
            'kas_keluar' => 0.0,
            'rekening_masuk' => 0.0,
            'rekening_keluar' => 0.0,
            'refund' => 0.0,
            'belanja' => 0.0,
            'net_movement' => 0.0,
            'active_days' => 0,
        ];

        $cursorTs = strtotime($dateStart);
        $endTs = strtotime($dateEnd);
        while ($cursorTs <= $endTs) {
            $dateKey = date('Y-m-d', $cursorTs);
            $daily = $dailyMap[$dateKey] ?? [
                'pendapatan' => 0.0,
                'kas_masuk' => 0.0,
                'kas_keluar' => 0.0,
                'rekening_masuk' => 0.0,
                'rekening_keluar' => 0.0,
                'refund' => 0.0,
                'belanja' => 0.0,
            ];

            $saldoAwal = $running;
            $netMovement = (float)$daily['pendapatan']
                + (float)$daily['kas_masuk']
                + (float)$daily['rekening_masuk']
                - (float)$daily['kas_keluar']
                - (float)$daily['rekening_keluar']
                - (float)$daily['refund']
                - (float)$daily['belanja'];
            $saldoAkhir = round($saldoAwal + $netMovement, 2);

            $hasActivity = abs($netMovement) > 0.0001
                || (float)$daily['pendapatan'] > 0
                || (float)$daily['kas_masuk'] > 0
                || (float)$daily['kas_keluar'] > 0
                || (float)$daily['rekening_masuk'] > 0
                || (float)$daily['rekening_keluar'] > 0
                || (float)$daily['refund'] > 0
                || (float)$daily['belanja'] > 0;

            if ($hasActivity) {
                $totals['active_days']++;
            }

            $rows[] = [
                'tanggal' => $dateKey,
                'saldo_awal' => round($saldoAwal, 2),
                'pendapatan' => (float)$daily['pendapatan'],
                'kas_masuk' => (float)$daily['kas_masuk'],
                'kas_keluar' => (float)$daily['kas_keluar'],
                'rekening_masuk' => (float)$daily['rekening_masuk'],
                'rekening_keluar' => (float)$daily['rekening_keluar'],
                'refund' => (float)$daily['refund'],
                'belanja' => (float)$daily['belanja'],
                'saldo_akhir' => $saldoAkhir,
                'has_activity' => $hasActivity,
            ];

            $totals['pendapatan'] += (float)$daily['pendapatan'];
            $totals['kas_masuk'] += (float)$daily['kas_masuk'];
            $totals['kas_keluar'] += (float)$daily['kas_keluar'];
            $totals['rekening_masuk'] += (float)$daily['rekening_masuk'];
            $totals['rekening_keluar'] += (float)$daily['rekening_keluar'];
            $totals['refund'] += (float)$daily['refund'];
            $totals['belanja'] += (float)$daily['belanja'];
            $totals['net_movement'] += $netMovement;

            $running = $saldoAkhir;
            $cursorTs = strtotime('+1 day', $cursorTs);
        }

        $totals['pendapatan'] = round($totals['pendapatan'], 2);
        $totals['kas_masuk'] = round($totals['kas_masuk'], 2);
        $totals['kas_keluar'] = round($totals['kas_keluar'], 2);
        $totals['rekening_masuk'] = round($totals['rekening_masuk'], 2);
        $totals['rekening_keluar'] = round($totals['rekening_keluar'], 2);
        $totals['refund'] = round($totals['refund'], 2);
        $totals['belanja'] = round($totals['belanja'], 2);
        $totals['net_movement'] = round($totals['net_movement'], 2);
        $totals['closing_balance'] = round($running, 2);

        return [
            'month' => $month,
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'account' => $account,
            'opening_balance' => round($openingBalance, 2),
            'closing_balance' => round($running, 2),
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    public function cash_position_exposure(string $month, int $accountId = 0, string $viewMode = 'REAL'): array
    {
        $month = trim($month);
        if (!preg_match('/^\d{4}\-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $viewMode = strtoupper(trim($viewMode));
        if (!in_array($viewMode, ['PHYSICAL', 'REAL', 'HISTORICAL'], true)) {
            $viewMode = 'REAL';
        }

        $accounts = $this->active_company_accounts();
        $accountMap = [];
        foreach ($accounts as $row) {
            $accountMap[(int)($row['id'] ?? 0)] = $row;
        }

        if ($accountId > 0 && !isset($accountMap[$accountId])) {
            $accountId = 0;
        }

        $selectedAccounts = $accountId > 0
            ? [$accountMap[$accountId]]
            : array_values($accountMap);
        $selectedIds = array_values(array_map(static function (array $row): int {
            return (int)($row['id'] ?? 0);
        }, $selectedAccounts));

        $dateStart = $month . '-01';
        $dateEnd = date('Y-m-t', strtotime($dateStart));

        $payableMap = $this->loan_exposure_map('fin_payable', $selectedIds);
        $receivableMap = $this->loan_exposure_map('fin_receivable', $selectedIds);
        $cashAdvanceMap = $this->cash_advance_exposure_map($selectedIds);
        $payrollPendingMap = $this->payroll_pending_map($selectedIds);
        $mutationMap = $this->account_month_mutation_map($dateStart, $dateEnd, $selectedIds);
        $moduleRows = $this->module_mutation_breakdown($dateStart, $dateEnd, $accountId);
        $historicalRows = $this->historical_keep_balance_rows($dateStart, $dateEnd, $accountId, 50);
        $planningSummary = $this->planning_summary($dateStart, $dateEnd);

        $accountRows = [];
        $overview = [
            'physical_balance_total' => 0.0,
            'real_balance_total' => 0.0,
            'historical_net_total' => 0.0,
            'payable_outstanding_total' => 0.0,
            'receivable_outstanding_total' => 0.0,
            'cash_advance_outstanding_total' => 0.0,
            'payroll_pending_total' => 0.0,
            'mutation_in_total' => 0.0,
            'mutation_out_total' => 0.0,
            'accounts_count' => 0,
            'dominant_view_amount' => 0.0,
        ];

        foreach ($selectedAccounts as $account) {
            $id = (int)($account['id'] ?? 0);
            $physical = round((float)($account['current_balance'] ?? 0), 2);
            $payable = (array)($payableMap[$id] ?? []);
            $receivable = (array)($receivableMap[$id] ?? []);
            $cashAdvance = (array)($cashAdvanceMap[$id] ?? []);
            $payrollPending = (array)($payrollPendingMap[$id] ?? []);
            $mutation = (array)($mutationMap[$id] ?? []);

            $payableOutstanding = round((float)($payable['outstanding_total'] ?? 0), 2);
            $receivableOutstanding = round((float)($receivable['outstanding_total'] ?? 0), 2);
            $cashAdvanceOutstanding = round((float)($cashAdvance['outstanding_total'] ?? 0), 2);
            $payrollPendingAmount = round((float)($payrollPending['pending_total'] ?? 0), 2);
            $historicalNet = round(
                (float)($receivable['keep_total'] ?? 0)
                - (float)($payable['keep_total'] ?? 0),
                2
            );
            $realBalance = round(
                $physical
                + $receivableOutstanding
                + $cashAdvanceOutstanding
                - $payableOutstanding
                - $payrollPendingAmount,
                2
            );

            $dominantValue = $realBalance;
            if ($viewMode === 'PHYSICAL') {
                $dominantValue = $physical;
            } elseif ($viewMode === 'HISTORICAL') {
                $dominantValue = $historicalNet;
            }

            $row = [
                'account_id' => $id,
                'account_code' => (string)($account['account_code'] ?? ''),
                'account_name' => (string)($account['account_name'] ?? ''),
                'account_type' => (string)($account['account_type'] ?? ''),
                'bank_name' => (string)($account['bank_name'] ?? ''),
                'opening_balance' => round((float)($account['opening_balance'] ?? 0), 2),
                'physical_balance' => $physical,
                'receivable_outstanding' => $receivableOutstanding,
                'receivable_keep' => round((float)($receivable['keep_total'] ?? 0), 2),
                'payable_outstanding' => $payableOutstanding,
                'payable_keep' => round((float)($payable['keep_total'] ?? 0), 2),
                'cash_advance_outstanding' => $cashAdvanceOutstanding,
                'payroll_pending' => $payrollPendingAmount,
                'historical_net' => $historicalNet,
                'real_balance' => $realBalance,
                'month_in' => round((float)($mutation['amount_in'] ?? 0), 2),
                'month_out' => round((float)($mutation['amount_out'] ?? 0), 2),
                'month_net' => round((float)($mutation['net_amount'] ?? 0), 2),
                'last_mutation_date' => (string)($mutation['last_mutation_date'] ?? ''),
                'payable_doc_count' => (int)($payable['doc_count'] ?? 0),
                'receivable_doc_count' => (int)($receivable['doc_count'] ?? 0),
                'cash_advance_doc_count' => (int)($cashAdvance['doc_count'] ?? 0),
                'payroll_doc_count' => (int)($payrollPending['doc_count'] ?? 0),
                'dominant_value' => round($dominantValue, 2),
            ];
            $accountRows[] = $row;

            $overview['physical_balance_total'] += $physical;
            $overview['real_balance_total'] += $realBalance;
            $overview['historical_net_total'] += $historicalNet;
            $overview['payable_outstanding_total'] += $payableOutstanding;
            $overview['receivable_outstanding_total'] += $receivableOutstanding;
            $overview['cash_advance_outstanding_total'] += $cashAdvanceOutstanding;
            $overview['payroll_pending_total'] += $payrollPendingAmount;
            $overview['mutation_in_total'] += (float)($mutation['amount_in'] ?? 0);
            $overview['mutation_out_total'] += (float)($mutation['amount_out'] ?? 0);
            $overview['dominant_view_amount'] += $dominantValue;
            $overview['accounts_count']++;
        }

        usort($accountRows, static function (array $a, array $b): int {
            $valueCompare = (float)($b['dominant_value'] ?? 0) <=> (float)($a['dominant_value'] ?? 0);
            if ($valueCompare !== 0) {
                return $valueCompare;
            }
            return strcmp((string)($a['account_name'] ?? ''), (string)($b['account_name'] ?? ''));
        });

        foreach ($overview as $key => $value) {
            if ($key === 'accounts_count') {
                $overview[$key] = (int)$value;
                continue;
            }
            $overview[$key] = round((float)$value, 2);
        }
        $overview['physical_vs_real_gap'] = round($overview['real_balance_total'] - $overview['physical_balance_total'], 2);

        return [
            'month' => $month,
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'view_mode' => $viewMode,
            'selected_account_id' => $accountId,
            'accounts' => $accountRows,
            'overview' => $overview,
            'module_rows' => $moduleRows,
            'historical_rows' => $historicalRows,
            'planning_summary' => $planningSummary,
        ];
    }

    private function loan_exposure_map(string $table, array $accountIds = []): array
    {
        if (!$this->db->table_exists($table)) {
            return [];
        }

        $db = $this->db->select("
                company_account_id,
                COUNT(*) AS doc_count,
                COALESCE(SUM(amount), 0) AS nominal_total,
                COALESCE(SUM(outstanding_amount), 0) AS outstanding_total,
                COALESCE(SUM(CASE WHEN account_impact_mode = 'KEEP_BALANCE' THEN outstanding_amount ELSE 0 END), 0) AS keep_total,
                COALESCE(SUM(CASE WHEN account_impact_mode = 'APPLY_ACCOUNT' THEN outstanding_amount ELSE 0 END), 0) AS apply_total
            ", false)
            ->from($table)
            ->where('company_account_id IS NOT NULL', null, false)
            ->where('status <>', 'VOID')
            ->where('COALESCE(outstanding_amount, 0) > 0', null, false);

        if (!empty($accountIds)) {
            $db->where_in('company_account_id', $accountIds);
        }

        $rows = $db->group_by('company_account_id')
            ->get()->result_array();

        $result = [];
        foreach ($rows as $row) {
            $result[(int)($row['company_account_id'] ?? 0)] = $row;
        }
        return $result;
    }

    private function cash_advance_exposure_map(array $accountIds = []): array
    {
        if (
            !$this->db->table_exists('pay_cash_advance')
            || !$this->table_has_field('pay_cash_advance', 'company_account_id')
        ) {
            return [];
        }

        $db = $this->db->select("
                company_account_id,
                COUNT(*) AS doc_count,
                COALESCE(SUM(amount), 0) AS nominal_total,
                COALESCE(SUM(outstanding_amount), 0) AS outstanding_total
            ", false)
            ->from('pay_cash_advance')
            ->where('company_account_id IS NOT NULL', null, false)
            ->where_in('status', ['APPROVED', 'SETTLED'])
            ->where('COALESCE(outstanding_amount, 0) > 0', null, false);

        if (!empty($accountIds)) {
            $db->where_in('company_account_id', $accountIds);
        }

        $rows = $db->group_by('company_account_id')
            ->get()->result_array();

        $result = [];
        foreach ($rows as $row) {
            $result[(int)($row['company_account_id'] ?? 0)] = $row;
        }
        return $result;
    }

    private function payroll_pending_map(array $accountIds = []): array
    {
        if (
            !$this->db->table_exists('pay_salary_disbursement')
            || !$this->db->table_exists('pay_salary_disbursement_line')
        ) {
            return [];
        }

        $lineHasAccount = $this->table_has_field('pay_salary_disbursement_line', 'company_account_id');
        $accountExpr = $lineHasAccount
            ? 'COALESCE(l.company_account_id, h.company_account_id)'
            : 'h.company_account_id';

        $db = $this->db->select("
                {$accountExpr} AS company_account_id,
                COUNT(*) AS doc_count,
                COALESCE(SUM(CASE WHEN l.transfer_status IN ('PENDING','FAILED') THEN l.transfer_amount ELSE 0 END), 0) AS pending_total
            ", false)
            ->from('pay_salary_disbursement_line l')
            ->join('pay_salary_disbursement h', 'h.id = l.disbursement_id', 'inner')
            ->where('h.status <>', 'VOID')
            ->where("{$accountExpr} IS NOT NULL", null, false)
            ->where_in('l.transfer_status', ['PENDING', 'FAILED']);

        if (!empty($accountIds)) {
            $db->where_in($accountExpr, $accountIds, false);
        }

        $rows = $db->group_by($accountExpr, false)
            ->get()->result_array();

        $result = [];
        foreach ($rows as $row) {
            $result[(int)($row['company_account_id'] ?? 0)] = $row;
        }
        return $result;
    }

    private function account_month_mutation_map(string $dateStart, string $dateEnd, array $accountIds = []): array
    {
        if (!$this->db->table_exists('fin_account_mutation_log')) {
            return [];
        }

        $db = $this->db->select("
                account_id,
                COALESCE(SUM(CASE WHEN mutation_type = 'IN' THEN amount ELSE 0 END), 0) AS amount_in,
                COALESCE(SUM(CASE WHEN mutation_type = 'OUT' THEN amount ELSE 0 END), 0) AS amount_out,
                COALESCE(SUM(CASE WHEN mutation_type = 'IN' THEN amount ELSE -amount END), 0) AS net_amount,
                MAX(mutation_date) AS last_mutation_date
            ", false)
            ->from('fin_account_mutation_log')
            ->where('mutation_date >=', $dateStart)
            ->where('mutation_date <=', $dateEnd);

        if (!empty($accountIds)) {
            $db->where_in('account_id', $accountIds);
        }

        $rows = $db->group_by('account_id')
            ->get()->result_array();

        $result = [];
        foreach ($rows as $row) {
            $result[(int)($row['account_id'] ?? 0)] = $row;
        }
        return $result;
    }

    private function module_mutation_breakdown(string $dateStart, string $dateEnd, int $accountId = 0): array
    {
        if (!$this->db->table_exists('fin_account_mutation_log')) {
            return [];
        }

        $moduleExpr = "
            CASE
              WHEN ref_module = 'POS' AND mutation_type = 'IN' THEN 'Pendapatan POS'
              WHEN ref_module = 'POS' AND mutation_type = 'OUT' THEN 'Refund POS'
              WHEN ref_module = 'PURCHASE' THEN 'Belanja / Pembelian'
              WHEN ref_module = 'PAYROLL' AND ref_table = 'pay_salary_disbursement' THEN 'Pencairan Gaji'
              WHEN ref_module = 'PAYROLL' AND ref_table = 'pay_cash_advance' THEN 'Pencairan Kasbon'
              WHEN ref_module = 'FINANCE_PAYABLE' AND mutation_type = 'IN' THEN 'Utang Masuk'
              WHEN ref_module = 'FINANCE_PAYABLE' AND mutation_type = 'OUT' THEN 'Pembayaran Utang'
              WHEN ref_module = 'FINANCE_RECEIVABLE' AND mutation_type = 'OUT' THEN 'Piutang Keluar'
              WHEN ref_module = 'FINANCE_RECEIVABLE' AND mutation_type = 'IN' THEN 'Pelunasan Piutang'
              WHEN ref_module = 'FINANCE_TRANSFER' AND mutation_type = 'IN' THEN 'Transfer Antar Rekening Masuk'
              WHEN ref_module = 'FINANCE_TRANSFER' AND mutation_type = 'OUT' THEN 'Transfer Antar Rekening Keluar'
              ELSE CONCAT(COALESCE(ref_module, 'LAINNYA'), ' / ', COALESCE(ref_table, '-'))
            END
        ";

        $db = $this->db->select("
                {$moduleExpr} AS module_label,
                COUNT(*) AS line_count,
                COALESCE(SUM(CASE WHEN mutation_type = 'IN' THEN amount ELSE 0 END), 0) AS amount_in,
                COALESCE(SUM(CASE WHEN mutation_type = 'OUT' THEN amount ELSE 0 END), 0) AS amount_out,
                COALESCE(SUM(CASE WHEN mutation_type = 'IN' THEN amount ELSE -amount END), 0) AS net_amount
            ", false)
            ->from('fin_account_mutation_log')
            ->where('mutation_date >=', $dateStart)
            ->where('mutation_date <=', $dateEnd);

        if ($accountId > 0) {
            $db->where('account_id', $accountId);
        }

        $rows = $db->group_by($moduleExpr, false)
            ->order_by("ABS(COALESCE(SUM(CASE WHEN mutation_type = 'IN' THEN amount ELSE -amount END), 0))", 'DESC', false)
            ->get()->result_array();

        foreach ($rows as &$row) {
            $row['line_count'] = (int)($row['line_count'] ?? 0);
            $row['amount_in'] = round((float)($row['amount_in'] ?? 0), 2);
            $row['amount_out'] = round((float)($row['amount_out'] ?? 0), 2);
            $row['net_amount'] = round((float)($row['net_amount'] ?? 0), 2);
        }
        unset($row);

        return $rows;
    }

    private function historical_keep_balance_rows(string $dateStart, string $dateEnd, int $accountId = 0, int $limit = 50): array
    {
        $rows = [];

        if ($this->db->table_exists('fin_payable')) {
            $db = $this->db->select("
                    h.payable_date AS doc_date,
                    h.company_account_id,
                    h.payable_no AS doc_no,
                    'Utang Historis' AS row_type,
                    'MENGURANGI_RIIL' AS impact_type,
                    h.amount AS amount,
                    h.outstanding_amount AS outstanding_amount,
                    p.party_name,
                    h.notes
                ", false)
                ->from('fin_payable h')
                ->join('fin_relation_party p', 'p.id = h.party_id', 'left')
                ->where('h.account_impact_mode', 'KEEP_BALANCE')
                ->where('h.status <>', 'VOID')
                ->where('h.payable_date >=', $dateStart)
                ->where('h.payable_date <=', $dateEnd);
            if ($accountId > 0) {
                $db->where('h.company_account_id', $accountId);
            }
            $rows = array_merge($rows, $db->get()->result_array());
        }

        if ($this->db->table_exists('fin_receivable')) {
            $db = $this->db->select("
                    h.receivable_date AS doc_date,
                    h.company_account_id,
                    h.receivable_no AS doc_no,
                    'Piutang Historis' AS row_type,
                    'MENAMBAH_RIIL' AS impact_type,
                    h.amount AS amount,
                    h.outstanding_amount AS outstanding_amount,
                    p.party_name,
                    h.notes
                ", false)
                ->from('fin_receivable h')
                ->join('fin_relation_party p', 'p.id = h.party_id', 'left')
                ->where('h.account_impact_mode', 'KEEP_BALANCE')
                ->where('h.status <>', 'VOID')
                ->where('h.receivable_date >=', $dateStart)
                ->where('h.receivable_date <=', $dateEnd);
            if ($accountId > 0) {
                $db->where('h.company_account_id', $accountId);
            }
            $rows = array_merge($rows, $db->get()->result_array());
        }

        if ($this->db->table_exists('fin_payable_payment')) {
            $db = $this->db->select("
                    py.payment_date AS doc_date,
                    py.company_account_id,
                    py.payment_no AS doc_no,
                    'Bayar Utang Historis' AS row_type,
                    'MENAMBAH_RIIL' AS impact_type,
                    py.amount AS amount,
                    0 AS outstanding_amount,
                    p.party_name,
                    py.notes
                ", false)
                ->from('fin_payable_payment py')
                ->join('fin_payable h', 'h.id = py.payable_id', 'left')
                ->join('fin_relation_party p', 'p.id = h.party_id', 'left')
                ->where('py.account_impact_mode', 'KEEP_BALANCE')
                ->where('py.payment_date >=', $dateStart)
                ->where('py.payment_date <=', $dateEnd);
            if ($accountId > 0) {
                $db->where('py.company_account_id', $accountId);
            }
            $rows = array_merge($rows, $db->get()->result_array());
        }

        if ($this->db->table_exists('fin_receivable_payment')) {
            $db = $this->db->select("
                    py.payment_date AS doc_date,
                    py.company_account_id,
                    py.payment_no AS doc_no,
                    'Terima Piutang Historis' AS row_type,
                    'MENGURANGI_RIIL' AS impact_type,
                    py.amount AS amount,
                    0 AS outstanding_amount,
                    p.party_name,
                    py.notes
                ", false)
                ->from('fin_receivable_payment py')
                ->join('fin_receivable h', 'h.id = py.receivable_id', 'left')
                ->join('fin_relation_party p', 'p.id = h.party_id', 'left')
                ->where('py.account_impact_mode', 'KEEP_BALANCE')
                ->where('py.payment_date >=', $dateStart)
                ->where('py.payment_date <=', $dateEnd);
            if ($accountId > 0) {
                $db->where('py.company_account_id', $accountId);
            }
            $rows = array_merge($rows, $db->get()->result_array());
        }

        usort($rows, static function (array $a, array $b): int {
            $dateCompare = strcmp((string)($b['doc_date'] ?? ''), (string)($a['doc_date'] ?? ''));
            if ($dateCompare !== 0) {
                return $dateCompare;
            }
            return strcmp((string)($b['doc_no'] ?? ''), (string)($a['doc_no'] ?? ''));
        });

        $rows = array_slice($rows, 0, max(1, $limit));
        foreach ($rows as &$row) {
            $row['company_account_id'] = (int)($row['company_account_id'] ?? 0);
            $row['amount'] = round((float)($row['amount'] ?? 0), 2);
            $row['outstanding_amount'] = round((float)($row['outstanding_amount'] ?? 0), 2);
        }
        unset($row);

        return $rows;
    }

    private function planning_summary(string $dateStart, string $dateEnd): array
    {
        $summary = [
            'store_request_pending_value' => 0.0,
            'salary_estimate_running' => 0.0,
        ];

        if (file_exists(APPPATH . 'models/Procurement_model.php')) {
            $this->load->model('Procurement_model');
            if (method_exists($this->Procurement_model, 'get_store_request_summary')) {
                $srSummary = $this->Procurement_model->get_store_request_summary([
                    'date_start' => $dateStart,
                    'date_end' => $dateEnd,
                ]);
                $summary['store_request_pending_value'] = round((float)($srSummary['pending_fulfillment_value_total'] ?? 0), 2);
            }
        }

        if (file_exists(APPPATH . 'models/Payroll_preview_model.php')) {
            $this->load->model('Payroll_preview_model');
            if (method_exists($this->Payroll_preview_model, 'count_monthly_recap') && method_exists($this->Payroll_preview_model, 'list_monthly_recap')) {
                $filters = [
                    'q' => '',
                    'division_id' => 0,
                    'position_id' => 0,
                    'date_start' => $dateStart,
                    'date_end' => $dateEnd,
                ];
                $total = (int)$this->Payroll_preview_model->count_monthly_recap($filters);
                if ($total > 0) {
                    $rows = $this->Payroll_preview_model->list_monthly_recap($filters, min($total, 5000), 0);
                    $sum = 0.0;
                    foreach ($rows as $row) {
                        $sum += (float)($row['net_total'] ?? 0);
                    }
                    $summary['salary_estimate_running'] = round($sum, 2);
                }
            }
        }

        return $summary;
    }

    private function opening_balance_for_month(int $accountId, string $dateStart, float $baseOpening): float
    {
        $movement = $this->db->select("COALESCE(SUM(CASE WHEN mutation_type = 'IN' THEN amount ELSE -amount END), 0) AS net_movement", false)
            ->from('fin_account_mutation_log')
            ->where('account_id', $accountId)
            ->where('mutation_date <', $dateStart)
            ->get()
            ->row_array();

        return round($baseOpening + (float)($movement['net_movement'] ?? 0), 2);
    }

    private function empty_cash_vault_report(string $month): array
    {
        $dateStart = $month . '-01';

        return [
            'month' => $month,
            'date_start' => $dateStart,
            'date_end' => date('Y-m-t', strtotime($dateStart)),
            'account' => [],
            'opening_balance' => 0.0,
            'closing_balance' => 0.0,
            'rows' => [],
            'totals' => [
                'opening_balance' => 0.0,
                'closing_balance' => 0.0,
                'pendapatan' => 0.0,
                'kas_masuk' => 0.0,
                'kas_keluar' => 0.0,
                'rekening_masuk' => 0.0,
                'rekening_keluar' => 0.0,
                'refund' => 0.0,
                'belanja' => 0.0,
                'net_movement' => 0.0,
                'active_days' => 0,
            ],
        ];
    }
}
