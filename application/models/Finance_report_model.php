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

    private function auth_user_display_expr(string $alias = 'u'): string
    {
        $parts = [];
        foreach (['name', 'full_name', 'display_name', 'username', 'email'] as $field) {
            if ($this->table_has_field('auth_user', $field)) {
                $parts[] = "NULLIF(TRIM({$alias}.{$field}), '')";
            }
        }
        $parts[] = "CONCAT('User #', {$alias}.id)";
        return 'COALESCE(' . implode(', ', $parts) . ')';
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

    public function financial_estimation_report(string $dateStart, string $dateEnd): array
    {
        [$dateStart, $dateEnd] = $this->normalize_date_range($dateStart, $dateEnd);

        $accounts = $this->active_company_accounts();
        $snapshotRows = $this->collect_account_snapshot_rows($dateStart, $dateEnd, $accounts);
        $metricRows = $this->collect_management_metric_rows($dateStart, $dateEnd, $snapshotRows);

        $globalMetricMap = [];
        $globalMetricGroups = [];
        $divisionRows = [];
        foreach ($metricRows as $row) {
            $scopeType = strtoupper(trim((string)($row['scope_type'] ?? '')));
            $scopeRefId = (int)($row['scope_ref_id'] ?? 0);
            $metricCode = strtoupper(trim((string)($row['metric_code'] ?? '')));
            $metricGroup = strtoupper(trim((string)($row['metric_group'] ?? '')));
            $amount = round((float)($row['metric_amount'] ?? 0), 2);

            if ($scopeType === 'GLOBAL' && $scopeRefId === 0) {
                $globalMetricMap[$metricCode] = $amount;
                if (!isset($globalMetricGroups[$metricGroup])) {
                    $globalMetricGroups[$metricGroup] = [];
                }
                $globalMetricGroups[$metricGroup][] = $row;
                continue;
            }

            if ($scopeType === 'DIVISION' && $scopeRefId > 0) {
                $divisionRows[] = $row;
            }
        }

        $overview = [
            'net_revenue' => round(($globalMetricMap['POS_REVENUE'] ?? 0) - ($globalMetricMap['POS_REFUND'] ?? 0), 2),
            'pos_revenue' => round((float)($globalMetricMap['POS_REVENUE'] ?? 0), 2),
            'pos_refund' => round((float)($globalMetricMap['POS_REFUND'] ?? 0), 2),
            'live_hpp' => round((float)($globalMetricMap['LIVE_HPP_VALUE'] ?? 0), 2),
            'purchase_operational' => round((float)($globalMetricMap['PURCHASE_OPERATIONAL'] ?? 0), 2),
            'purchase_utility' => round((float)($globalMetricMap['PURCHASE_UTILITY'] ?? 0), 2),
            'purchase_other' => round((float)($globalMetricMap['PURCHASE_OTHER'] ?? 0), 2),
            'payroll_estimate_running' => round((float)($globalMetricMap['PAYROLL_ESTIMATE_RUNNING'] ?? 0), 2),
            'sr_pending_value' => round((float)($globalMetricMap['SR_PENDING_VALUE'] ?? 0), 2),
            'warehouse_adjustment' => round((float)($globalMetricMap['WAREHOUSE_ADJUSTMENT_VALUE'] ?? 0), 2),
            'division_adjustment' => round((float)($globalMetricMap['DIVISION_ADJUSTMENT_VALUE'] ?? 0), 2),
            'component_adjustment' => round((float)($globalMetricMap['COMPONENT_ADJUSTMENT_VALUE'] ?? 0), 2),
            'estimated_profit_value' => round((float)($globalMetricMap['ESTIMATED_PROFIT_VALUE'] ?? 0), 2),
            'estimated_profit_percent' => round((float)($globalMetricMap['ESTIMATED_PROFIT_PERCENT'] ?? 0), 2),
            'physical_balance_value' => round((float)($globalMetricMap['PHYSICAL_BALANCE_VALUE'] ?? 0), 2),
            'real_balance_value' => round((float)($globalMetricMap['REAL_BALANCE_VALUE'] ?? 0), 2),
        ];
        $overview['estimated_cost_total'] = round(
            $overview['live_hpp']
            + $overview['purchase_operational']
            + $overview['purchase_utility']
            + $overview['purchase_other']
            + $overview['payroll_estimate_running']
            + $overview['warehouse_adjustment']
            + $overview['division_adjustment']
            + $overview['component_adjustment'],
            2
        );

        $waterfallRows = [
            ['label' => 'Omzet POS', 'amount' => $overview['pos_revenue'], 'tone' => 'positive'],
            ['label' => 'Refund POS', 'amount' => $overview['pos_refund'], 'tone' => 'negative'],
            ['label' => 'Omzet Bersih', 'amount' => $overview['net_revenue'], 'tone' => 'neutral'],
            ['label' => 'HPP Live', 'amount' => $overview['live_hpp'], 'tone' => 'negative'],
            ['label' => 'Belanja Operasional', 'amount' => $overview['purchase_operational'], 'tone' => 'negative'],
            ['label' => 'Belanja Utilitas', 'amount' => $overview['purchase_utility'], 'tone' => 'negative'],
            ['label' => 'Belanja Lainnya', 'amount' => $overview['purchase_other'], 'tone' => 'negative'],
            ['label' => 'Estimasi Gaji Berjalan', 'amount' => $overview['payroll_estimate_running'], 'tone' => 'negative'],
            ['label' => 'Adjustment Gudang', 'amount' => $overview['warehouse_adjustment'], 'tone' => 'negative'],
            ['label' => 'Adjustment Divisi', 'amount' => $overview['division_adjustment'], 'tone' => 'negative'],
            ['label' => 'Adjustment Component', 'amount' => $overview['component_adjustment'], 'tone' => 'negative'],
            ['label' => 'Profit Estimasi', 'amount' => $overview['estimated_profit_value'], 'tone' => $overview['estimated_profit_value'] < 0 ? 'negative' : 'positive'],
        ];

        $divisionMap = [];
        foreach ($this->division_options() as $division) {
            $divisionMap[(int)($division['id'] ?? 0)] = (string)($division['name'] ?? ('Divisi #' . (int)($division['id'] ?? 0)));
        }

        $divisionMetricMap = [];
        foreach ($divisionRows as $row) {
            $divisionId = (int)($row['scope_ref_id'] ?? 0);
            $metricCode = strtoupper(trim((string)($row['metric_code'] ?? '')));
            if ($divisionId <= 0 || $metricCode === '') {
                continue;
            }
            if (!isset($divisionMetricMap[$divisionId])) {
                $divisionMetricMap[$divisionId] = [
                    'division_id' => $divisionId,
                    'division_name' => $divisionMap[$divisionId] ?? ('Divisi #' . $divisionId),
                    'SR_PENDING_VALUE' => 0.0,
                    'PAYROLL_ESTIMATE_RUNNING' => 0.0,
                    'RAW_MATERIAL_IN_VALUE' => 0.0,
                    'RAW_MATERIAL_USAGE_VALUE' => 0.0,
                    'DIVISION_ADJUSTMENT_VALUE' => 0.0,
                    'DIVISION_ENDING_STOCK_VALUE' => 0.0,
                ];
            }
            if (array_key_exists($metricCode, $divisionMetricMap[$divisionId])) {
                $divisionMetricMap[$divisionId][$metricCode] = round((float)($row['metric_amount'] ?? 0), 2);
            }
        }

        $divisionScoreboard = array_values($divisionMetricMap);
        usort($divisionScoreboard, static function (array $a, array $b): int {
            $scoreA = abs((float)($a['RAW_MATERIAL_USAGE_VALUE'] ?? 0)) + abs((float)($a['DIVISION_ADJUSTMENT_VALUE'] ?? 0));
            $scoreB = abs((float)($b['RAW_MATERIAL_USAGE_VALUE'] ?? 0)) + abs((float)($b['DIVISION_ADJUSTMENT_VALUE'] ?? 0));
            if ($scoreA !== $scoreB) {
                return $scoreB <=> $scoreA;
            }
            return strcmp((string)($a['division_name'] ?? ''), (string)($b['division_name'] ?? ''));
        });

        usort($snapshotRows, static function (array $a, array $b): int {
            $balanceCompare = (float)($b['closing_balance_real'] ?? 0) <=> (float)($a['closing_balance_real'] ?? 0);
            if ($balanceCompare !== 0) {
                return $balanceCompare;
            }
            return strcmp((string)($a['account_name_snapshot'] ?? ''), (string)($b['account_name_snapshot'] ?? ''));
        });

        return [
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'overview' => $overview,
            'waterfall_rows' => $waterfallRows,
            'global_metric_groups' => $globalMetricGroups,
            'division_scoreboard' => $divisionScoreboard,
            'account_snapshots' => $snapshotRows,
        ];
    }

    public function bank_daily_recap(string $month, int $accountId = 0): array
    {
        $month = trim($month);
        if (!preg_match('/^\d{4}\-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $accounts = $this->active_company_accounts();
        $accountMap = [];
        foreach ($accounts as $account) {
            $accountMap[(int)($account['id'] ?? 0)] = $account;
        }

        if ($accountId > 0 && !isset($accountMap[$accountId])) {
            $accountId = 0;
        }

        $dateStart = $month . '-01';
        $dateEnd = date('Y-m-t', strtotime($dateStart));
        $dailyRows = [];
        $endingAccountRows = [];

        $cursor = strtotime($dateStart);
        $endCursor = strtotime($dateEnd);
        while ($cursor <= $endCursor) {
            $day = date('Y-m-d', $cursor);
            $snapshotRows = $this->collect_account_snapshot_rows($day, $day, $accounts);
            $kafeSummary = $this->snapshot_summary_from_rows($snapshotRows);
            $focusSummary = $kafeSummary;
            if ($accountId > 0) {
                $focusSummary = $this->snapshot_summary_from_rows(array_values(array_filter($snapshotRows, static function (array $row) use ($accountId): bool {
                    return (int)($row['company_account_id'] ?? 0) === $accountId;
                })));
            }

            $dailyRows[] = [
                'date' => $day,
                'rekening_mutation_in' => round((float)($focusSummary['mutation_in_total'] ?? 0), 2),
                'rekening_mutation_out' => round((float)($focusSummary['mutation_out_total'] ?? 0), 2),
                'rekening_physical_balance' => round((float)($focusSummary['physical_total'] ?? 0), 2),
                'rekening_real_balance' => round((float)($focusSummary['real_total'] ?? 0), 2),
                'kafe_mutation_in' => round((float)($kafeSummary['mutation_in_total'] ?? 0), 2),
                'kafe_mutation_out' => round((float)($kafeSummary['mutation_out_total'] ?? 0), 2),
                'kafe_physical_balance' => round((float)($kafeSummary['physical_total'] ?? 0), 2),
                'kafe_real_balance' => round((float)($kafeSummary['real_total'] ?? 0), 2),
                'kafe_gap' => round((float)($kafeSummary['real_total'] ?? 0) - (float)($kafeSummary['physical_total'] ?? 0), 2),
                'payable_total' => round((float)($kafeSummary['payable_total'] ?? 0), 2),
                'receivable_total' => round((float)($kafeSummary['receivable_total'] ?? 0), 2),
                'cash_advance_total' => round((float)($kafeSummary['cash_advance_total'] ?? 0), 2),
                'payroll_pending_total' => round((float)($kafeSummary['payroll_pending_total'] ?? 0), 2),
            ];

            if ($day === $dateEnd) {
                $endingAccountRows = $snapshotRows;
            }
            $cursor = strtotime('+1 day', $cursor);
        }

        if (empty($endingAccountRows)) {
            $endingAccountRows = $this->collect_account_snapshot_rows($dateEnd, $dateEnd, $accounts);
        }

        usort($endingAccountRows, static function (array $a, array $b): int {
            $realCompare = (float)($b['closing_balance_real'] ?? 0) <=> (float)($a['closing_balance_real'] ?? 0);
            if ($realCompare !== 0) {
                return $realCompare;
            }
            return strcmp((string)($a['account_name_snapshot'] ?? ''), (string)($b['account_name_snapshot'] ?? ''));
        });

        $endingKafeSummary = $this->snapshot_summary_from_rows($endingAccountRows);
        $endingFocusSummary = $endingKafeSummary;
        if ($accountId > 0) {
            $endingFocusSummary = $this->snapshot_summary_from_rows(array_values(array_filter($endingAccountRows, static function (array $row) use ($accountId): bool {
                return (int)($row['company_account_id'] ?? 0) === $accountId;
            })));
        }

        return [
            'month' => $month,
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'selected_account_id' => $accountId,
            'selected_account' => $accountId > 0 ? ($accountMap[$accountId] ?? null) : null,
            'overview' => [
                'rekening_physical_balance' => round((float)($endingFocusSummary['physical_total'] ?? 0), 2),
                'rekening_real_balance' => round((float)($endingFocusSummary['real_total'] ?? 0), 2),
                'kafe_physical_balance' => round((float)($endingKafeSummary['physical_total'] ?? 0), 2),
                'kafe_real_balance' => round((float)($endingKafeSummary['real_total'] ?? 0), 2),
                'kafe_gap' => round((float)($endingKafeSummary['real_total'] ?? 0) - (float)($endingKafeSummary['physical_total'] ?? 0), 2),
                'payable_total' => round((float)($endingKafeSummary['payable_total'] ?? 0), 2),
                'receivable_total' => round((float)($endingKafeSummary['receivable_total'] ?? 0), 2),
                'cash_advance_total' => round((float)($endingKafeSummary['cash_advance_total'] ?? 0), 2),
                'payroll_pending_total' => round((float)($endingKafeSummary['payroll_pending_total'] ?? 0), 2),
            ],
            'daily_rows' => $dailyRows,
            'account_rows' => $endingAccountRows,
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

    private function normalize_date_range(string $dateStart, string $dateEnd): array
    {
        $dateStart = trim($dateStart);
        $dateEnd = trim($dateEnd);
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $dateStart)) {
            $dateStart = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $dateEnd)) {
            $dateEnd = date('Y-m-t');
        }
        if ($dateStart > $dateEnd) {
            [$dateStart, $dateEnd] = [$dateEnd, $dateStart];
        }
        return [$dateStart, $dateEnd];
    }

    private function snapshot_summary_from_rows(array $rows): array
    {
        $summary = [
            'physical_total' => 0.0,
            'real_total' => 0.0,
            'payable_total' => 0.0,
            'receivable_total' => 0.0,
            'cash_advance_total' => 0.0,
            'payroll_pending_total' => 0.0,
            'mutation_in_total' => 0.0,
            'mutation_out_total' => 0.0,
        ];

        foreach ($rows as $row) {
            $summary['physical_total'] += (float)($row['closing_balance_physical'] ?? 0);
            $summary['real_total'] += (float)($row['closing_balance_real'] ?? 0);
            $summary['payable_total'] += (float)($row['payable_outstanding'] ?? 0);
            $summary['receivable_total'] += (float)($row['receivable_outstanding'] ?? 0);
            $summary['cash_advance_total'] += (float)($row['cash_advance_outstanding'] ?? 0);
            $summary['payroll_pending_total'] += (float)($row['payroll_pending'] ?? 0);
            $summary['mutation_in_total'] += (float)($row['mutation_in_total'] ?? 0);
            $summary['mutation_out_total'] += (float)($row['mutation_out_total'] ?? 0);
        }

        foreach ($summary as $key => $value) {
            $summary[$key] = round((float)$value, 2);
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

    public function division_options(): array
    {
        if (!$this->db->table_exists('mst_operational_division')) {
            return [];
        }

        return $this->db->select('id, name, code')
            ->from('mst_operational_division')
            ->where('is_active', 1)
            ->order_by('name', 'ASC')
            ->get()->result_array();
    }

    public function metric_catalog_options(): array
    {
        if (!$this->db->table_exists('fin_metric_catalog')) {
            return [];
        }

        return $this->db->select('id, metric_code, metric_group, metric_label, metric_unit, metric_scope, comparator_hint, description')
            ->from('fin_metric_catalog')
            ->where('is_active', 1)
            ->order_by('metric_group', 'ASC')
            ->order_by('metric_label', 'ASC')
            ->get()->result_array();
    }

    public function count_period_closes(array $filters = []): int
    {
        if (!$this->db->table_exists('fin_period_close')) {
            return 0;
        }

        $db = $this->db->from('fin_period_close pc');
        $this->apply_period_close_filters($db, $filters);
        return (int)$db->count_all_results();
    }

    public function list_period_closes(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        if (!$this->db->table_exists('fin_period_close')) {
            return [];
        }

        $db = $this->db
            ->select('pc.*')
            ->select('(SELECT COUNT(*) FROM fin_account_period_snapshot s WHERE s.period_close_id = pc.id) AS snapshot_count', false)
            ->select('(SELECT COUNT(*) FROM fin_management_period_metric m WHERE m.period_close_id = pc.id) AS metric_count', false)
            ->from('fin_period_close pc');
        if ($this->db->table_exists('auth_user')) {
            $db->select($this->auth_user_display_expr('uc') . ' AS created_by_name', false)
                ->select($this->auth_user_display_expr('ucl') . ' AS closed_by_name', false)
                ->select($this->auth_user_display_expr('ur') . ' AS reopened_by_name', false)
                ->join('auth_user uc', 'uc.id = pc.created_by', 'left')
                ->join('auth_user ucl', 'ucl.id = pc.closed_by', 'left')
                ->join('auth_user ur', 'ur.id = pc.reopened_by', 'left');
        }
        $this->apply_period_close_filters($db, $filters);
        return $db->order_by('pc.period_end', 'DESC')
            ->order_by('pc.snapshot_version', 'DESC')
            ->limit(max(1, $limit), max(0, $offset))
            ->get()->result_array();
    }

    public function summarize_period_closes(array $filters = []): array
    {
        $summary = [
            'total_rows' => 0,
            'closed_rows' => 0,
            'open_rows' => 0,
            'reopened_rows' => 0,
        ];

        if (!$this->db->table_exists('fin_period_close')) {
            return $summary;
        }

        $row = $this->db->select("
                COUNT(*) AS total_rows,
                COALESCE(SUM(CASE WHEN status = 'CLOSED' THEN 1 ELSE 0 END), 0) AS closed_rows,
                COALESCE(SUM(CASE WHEN status = 'OPEN' THEN 1 ELSE 0 END), 0) AS open_rows,
                COALESCE(SUM(CASE WHEN status = 'REOPENED' THEN 1 ELSE 0 END), 0) AS reopened_rows
            ", false)
            ->from('fin_period_close pc');
        $this->apply_period_close_filters($this->db, $filters);
        $result = $this->db->get()->row_array();

        return [
            'total_rows' => (int)($result['total_rows'] ?? 0),
            'closed_rows' => (int)($result['closed_rows'] ?? 0),
            'open_rows' => (int)($result['open_rows'] ?? 0),
            'reopened_rows' => (int)($result['reopened_rows'] ?? 0),
        ];
    }

    public function save_period_close(array $payload, int $actorUserId = 0): array
    {
        if (!$this->db->table_exists('fin_period_close')) {
            return ['ok' => false, 'message' => 'Tabel tutup periode keuangan belum tersedia. Jalankan SQL foundation terlebih dahulu.'];
        }

        $periodType = strtoupper(trim((string)($payload['period_type'] ?? 'MONTHLY')));
        if (!in_array($periodType, ['MONTHLY', 'YEARLY'], true)) {
            $periodType = 'MONTHLY';
        }

        $periodStart = trim((string)($payload['period_start'] ?? ''));
        $periodEnd = trim((string)($payload['period_end'] ?? ''));
        if ($periodStart === '' || $periodEnd === '') {
            return ['ok' => false, 'message' => 'Tanggal awal dan akhir periode wajib diisi.'];
        }
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $periodStart) || !preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $periodEnd)) {
            return ['ok' => false, 'message' => 'Format tanggal periode tidak valid.'];
        }
        if ($periodStart > $periodEnd) {
            return ['ok' => false, 'message' => 'Tanggal akhir periode tidak boleh lebih kecil dari tanggal awal.'];
        }

        $periodYear = (int)date('Y', strtotime($periodStart));
        $periodMonth = $periodType === 'MONTHLY' ? (int)date('m', strtotime($periodStart)) : null;
        $periodCode = $periodType === 'MONTHLY'
            ? 'FIN-CLOSE-' . date('Ym', strtotime($periodStart))
            : 'FIN-CLOSE-' . $periodYear;

        $existingVersion = $this->db->select('MAX(snapshot_version) AS max_version', false)
            ->from('fin_period_close')
            ->where('period_code', $periodCode)
            ->get()->row_array();
        $snapshotVersion = ((int)($existingVersion['max_version'] ?? 0)) + 1;

        $row = [
            'period_code' => $periodCode,
            'period_type' => $periodType,
            'period_year' => $periodYear,
            'period_month' => $periodMonth,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'snapshot_version' => $snapshotVersion,
            'close_mode' => strtoupper(trim((string)($payload['close_mode'] ?? 'AUTO_REBUILD'))) === 'MANUAL_LOCK' ? 'MANUAL_LOCK' : 'AUTO_REBUILD',
            'status' => 'OPEN',
            'notes' => trim((string)($payload['notes'] ?? '')) ?: null,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->insert('fin_period_close', $row);
        return [
            'ok' => true,
            'id' => (int)$this->db->insert_id(),
            'message' => 'Draft periode keuangan berhasil dibuat.',
        ];
    }

    public function count_target_plans(array $filters = []): int
    {
        if (!$this->db->table_exists('fin_target_plan')) {
            return 0;
        }

        $db = $this->db->from('fin_target_plan tp');
        $this->apply_target_plan_filters($db, $filters);
        return (int)$db->count_all_results();
    }

    public function list_target_plans(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        if (!$this->db->table_exists('fin_target_plan')) {
            return [];
        }

        $db = $this->db
            ->select('tp.*')
            ->select('d.name AS division_name, acc.account_name, acc.bank_name')
            ->from('fin_target_plan tp');
        if ($this->db->table_exists('mst_operational_division')) {
            $db->join('mst_operational_division d', 'd.id = tp.division_id', 'left');
        }
        if ($this->db->table_exists('fin_company_account')) {
            $db->join('fin_company_account acc', 'acc.id = tp.company_account_id', 'left');
        }
        if ($this->db->table_exists('fin_target_plan_line')) {
            $db->select('(SELECT COUNT(*) FROM fin_target_plan_line tl WHERE tl.target_plan_id = tp.id) AS metric_count', false);
        } else {
            $db->select('0 AS metric_count', false);
        }
        if ($this->db->table_exists('fin_target_realization')) {
            $db->select('(SELECT COUNT(*) FROM fin_target_realization tr WHERE tr.target_plan_id = tp.id) AS realization_count', false)
                ->select('(SELECT ROUND(AVG(tr.score_percent), 2) FROM fin_target_realization tr WHERE tr.target_plan_id = tp.id) AS avg_score_percent', false)
                ->select('(SELECT MAX(tr.realization_date) FROM fin_target_realization tr WHERE tr.target_plan_id = tp.id) AS last_realization_date', false);
        } else {
            $db->select('0 AS realization_count', false)
                ->select('0 AS avg_score_percent', false)
                ->select('NULL AS last_realization_date', false);
        }
        $this->apply_target_plan_filters($db, $filters);
        return $db->order_by('tp.date_end', 'DESC')
            ->order_by('tp.id', 'DESC')
            ->limit(max(1, $limit), max(0, $offset))
            ->get()->result_array();
    }

    public function summarize_target_plans(array $filters = []): array
    {
        $summary = [
            'total_rows' => 0,
            'active_rows' => 0,
            'draft_rows' => 0,
            'locked_rows' => 0,
        ];

        if (!$this->db->table_exists('fin_target_plan')) {
            return $summary;
        }

        $this->db->select("
                COUNT(*) AS total_rows,
                COALESCE(SUM(CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END), 0) AS active_rows,
                COALESCE(SUM(CASE WHEN status = 'DRAFT' THEN 1 ELSE 0 END), 0) AS draft_rows,
                COALESCE(SUM(CASE WHEN status = 'LOCKED' THEN 1 ELSE 0 END), 0) AS locked_rows
            ", false)
            ->from('fin_target_plan tp');
        $this->apply_target_plan_filters($this->db, $filters);
        $result = $this->db->get()->row_array();

        return [
            'total_rows' => (int)($result['total_rows'] ?? 0),
            'active_rows' => (int)($result['active_rows'] ?? 0),
            'draft_rows' => (int)($result['draft_rows'] ?? 0),
            'locked_rows' => (int)($result['locked_rows'] ?? 0),
        ];
    }

    public function save_target_plan(array $payload, int $actorUserId = 0): array
    {
        if (!$this->db->table_exists('fin_target_plan')) {
            return ['ok' => false, 'message' => 'Tabel target keuangan belum tersedia. Jalankan SQL foundation terlebih dahulu.'];
        }

        $targetName = trim((string)($payload['target_name'] ?? ''));
        if ($targetName === '') {
            return ['ok' => false, 'message' => 'Nama target wajib diisi.'];
        }

        $scope = strtoupper(trim((string)($payload['target_scope'] ?? 'MONTHLY')));
        if (!in_array($scope, ['DAILY', 'MONTHLY', 'YEARLY'], true)) {
            $scope = 'MONTHLY';
        }

        $dateStart = trim((string)($payload['date_start'] ?? ''));
        $dateEnd = trim((string)($payload['date_end'] ?? ''));
        if ($dateStart === '' || $dateEnd === '') {
            return ['ok' => false, 'message' => 'Tanggal awal dan akhir target wajib diisi.'];
        }
        if ($dateStart > $dateEnd) {
            return ['ok' => false, 'message' => 'Tanggal akhir target tidak boleh lebih kecil dari tanggal awal.'];
        }

        $targetYear = (int)date('Y', strtotime($dateStart));
        $targetMonth = $scope !== 'YEARLY' ? (int)date('m', strtotime($dateStart)) : null;
        $targetDate = $scope === 'DAILY' ? $dateStart : null;
        $maxIdRow = $this->db->select('MAX(id) AS max_id', false)->from('fin_target_plan')->get()->row_array();
        $nextSeq = ((int)($maxIdRow['max_id'] ?? 0)) + 1;
        $targetCode = 'FIN-TARGET-' . date('Ym', strtotime($dateStart)) . '-' . str_pad((string)$nextSeq, 4, '0', STR_PAD_LEFT);

        $row = [
            'target_code' => $targetCode,
            'target_name' => $targetName,
            'target_scope' => $scope,
            'target_year' => $targetYear,
            'target_month' => $targetMonth,
            'target_date' => $targetDate,
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'division_id' => (int)($payload['division_id'] ?? 0) > 0 ? (int)$payload['division_id'] : null,
            'company_account_id' => (int)($payload['company_account_id'] ?? 0) > 0 ? (int)$payload['company_account_id'] : null,
            'status' => 'DRAFT',
            'bonus_gate_mode' => in_array(strtoupper(trim((string)($payload['bonus_gate_mode'] ?? 'WEIGHTED_SCORE'))), ['NONE', 'ALL_REQUIRED', 'WEIGHTED_SCORE'], true)
                ? strtoupper(trim((string)($payload['bonus_gate_mode'] ?? 'WEIGHTED_SCORE')))
                : 'WEIGHTED_SCORE',
            'min_bonus_score' => round((float)($payload['min_bonus_score'] ?? 100), 2),
            'bonus_pool_amount' => round((float)($payload['bonus_pool_amount'] ?? 0), 2),
            'bonus_percent_of_profit' => round((float)($payload['bonus_percent_of_profit'] ?? 0), 4),
            'notes' => trim((string)($payload['notes'] ?? '')) ?: null,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->insert('fin_target_plan', $row);
        $targetPlanId = (int)$this->db->insert_id();

        $metricCodes = $payload['metric_codes'] ?? [];
        if (!is_array($metricCodes)) {
            $metricCodes = [$metricCodes];
        }
        $metricCodes = array_values(array_unique(array_filter(array_map(static function ($v) {
            return strtoupper(trim((string)$v));
        }, $metricCodes))));

        if ($targetPlanId > 0 && !empty($metricCodes) && $this->db->table_exists('fin_metric_catalog') && $this->db->table_exists('fin_target_plan_line')) {
            $catalogRows = $this->db->select('metric_group, metric_code, metric_label, comparator_hint')
                ->from('fin_metric_catalog')
                ->where_in('metric_code', $metricCodes)
                ->where('is_active', 1)
                ->order_by('metric_group', 'ASC')
                ->order_by('metric_label', 'ASC')
                ->get()->result_array();
            $lineCount = count($catalogRows);
            $weight = $lineCount > 0 ? round(100 / $lineCount, 4) : 0;
            foreach ($catalogRows as $catalogRow) {
                $this->db->insert('fin_target_plan_line', [
                    'target_plan_id' => $targetPlanId,
                    'metric_group' => (string)($catalogRow['metric_group'] ?? 'OTHER'),
                    'metric_code' => (string)($catalogRow['metric_code'] ?? ''),
                    'metric_label' => (string)($catalogRow['metric_label'] ?? ''),
                    'comparator' => in_array((string)($catalogRow['comparator_hint'] ?? 'MAX'), ['MIN', 'MAX', 'RANGE', 'EQUAL'], true)
                        ? (string)$catalogRow['comparator_hint']
                        : 'MAX',
                    'target_value' => 0,
                    'weight_percent' => $weight,
                    'is_required' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        return [
            'ok' => true,
            'id' => $targetPlanId,
            'message' => 'Draft target keuangan berhasil dibuat.',
        ];
    }

    public function get_period_close_by_id(int $id): ?array
    {
        if ($id <= 0 || !$this->db->table_exists('fin_period_close')) {
            return null;
        }

        return $this->db->select('pc.*')
            ->from('fin_period_close pc')
            ->where('pc.id', $id)
            ->limit(1)
            ->get()->row_array() ?: null;
    }

    public function get_period_close_detail(int $id): ?array
    {
        $row = $this->get_period_close_by_id($id);
        if (!$row) {
            return null;
        }

        $snapshotSummary = $this->summarize_period_close_snapshots($id);
        $metricSummary = $this->summarize_period_close_metrics($id);

        return array_merge($row, [
            'snapshot_summary' => $snapshotSummary,
            'metric_summary' => $metricSummary,
        ]);
    }

    public function summarize_period_close_snapshots(int $periodCloseId): array
    {
        $summary = [
            'account_count' => 0,
            'physical_total' => 0.0,
            'real_total' => 0.0,
            'payable_total' => 0.0,
            'receivable_total' => 0.0,
            'cash_advance_total' => 0.0,
            'payroll_pending_total' => 0.0,
        ];

        if ($periodCloseId <= 0 || !$this->db->table_exists('fin_account_period_snapshot')) {
            return $summary;
        }

        $row = $this->db->select("
                COUNT(*) AS account_count,
                COALESCE(SUM(closing_balance_physical), 0) AS physical_total,
                COALESCE(SUM(closing_balance_real), 0) AS real_total,
                COALESCE(SUM(payable_outstanding), 0) AS payable_total,
                COALESCE(SUM(receivable_outstanding), 0) AS receivable_total,
                COALESCE(SUM(cash_advance_outstanding), 0) AS cash_advance_total,
                COALESCE(SUM(payroll_pending), 0) AS payroll_pending_total
            ", false)
            ->from('fin_account_period_snapshot')
            ->where('period_close_id', $periodCloseId)
            ->get()->row_array();

        if (!$row) {
            return $summary;
        }

        foreach ($summary as $key => $defaultValue) {
            $summary[$key] = $key === 'account_count'
                ? (int)($row[$key] ?? 0)
                : round((float)($row[$key] ?? 0), 2);
        }

        return $summary;
    }

    public function list_period_close_snapshots(int $periodCloseId): array
    {
        if ($periodCloseId <= 0 || !$this->db->table_exists('fin_account_period_snapshot')) {
            return [];
        }

        return $this->db->select('s.*')
            ->from('fin_account_period_snapshot s')
            ->where('s.period_close_id', $periodCloseId)
            ->order_by('s.account_name_snapshot', 'ASC')
            ->order_by('s.id', 'ASC')
            ->get()->result_array();
    }

    public function summarize_period_close_metrics(int $periodCloseId): array
    {
        $summary = [
            'metric_count' => 0,
            'global_count' => 0,
            'division_count' => 0,
            'account_count' => 0,
        ];

        if ($periodCloseId <= 0 || !$this->db->table_exists('fin_management_period_metric')) {
            return $summary;
        }

        $row = $this->db->select("
                COUNT(*) AS metric_count,
                COALESCE(SUM(CASE WHEN scope_type = 'GLOBAL' THEN 1 ELSE 0 END), 0) AS global_count,
                COALESCE(SUM(CASE WHEN scope_type = 'DIVISION' THEN 1 ELSE 0 END), 0) AS division_count,
                COALESCE(SUM(CASE WHEN scope_type = 'ACCOUNT' THEN 1 ELSE 0 END), 0) AS account_count
            ", false)
            ->from('fin_management_period_metric')
            ->where('period_close_id', $periodCloseId)
            ->get()->row_array();

        if (!$row) {
            return $summary;
        }

        foreach ($summary as $key => $defaultValue) {
            $summary[$key] = (int)($row[$key] ?? 0);
        }

        return $summary;
    }

    public function list_period_close_metrics(int $periodCloseId): array
    {
        if ($periodCloseId <= 0 || !$this->db->table_exists('fin_management_period_metric')) {
            return [];
        }

        return $this->db->select('m.*')
            ->from('fin_management_period_metric m')
            ->where('m.period_close_id', $periodCloseId)
            ->order_by("FIELD(m.scope_type, 'GLOBAL','DIVISION','ACCOUNT')", '', false)
            ->order_by('m.metric_group', 'ASC')
            ->order_by('m.metric_label', 'ASC')
            ->get()->result_array();
    }

    public function reopen_period(int $periodCloseId, int $actorUserId = 0): array
    {
        $row = $this->get_period_close_by_id($periodCloseId);
        if (!$row) {
            return ['ok' => false, 'message' => 'Draft period close tidak ditemukan.'];
        }

        if (strtoupper((string)($row['status'] ?? 'OPEN')) !== 'CLOSED') {
            return ['ok' => false, 'message' => 'Hanya period yang sudah CLOSED yang bisa dibuka ulang.'];
        }

        $notes = trim((string)($row['notes'] ?? ''));
        $notes = trim($notes . ($notes !== '' ? ' | ' : '') . 'Reopened ' . date('Y-m-d H:i'));

        $this->db->where('id', $periodCloseId)->update('fin_period_close', [
            'status' => 'REOPENED',
            'reopened_by' => $actorUserId > 0 ? $actorUserId : null,
            'reopened_at' => date('Y-m-d H:i:s'),
            'notes' => $notes !== '' ? substr($notes, 0, 255) : null,
        ]);

        return [
            'ok' => (bool)$this->db->affected_rows() >= 0,
            'message' => 'Period berhasil dibuka ulang. Anda bisa koreksi data lalu proses close lagi.',
        ];
    }

    public function close_period(int $periodCloseId, int $actorUserId = 0): array
    {
        if (
            $periodCloseId <= 0
            || !$this->db->table_exists('fin_period_close')
            || !$this->db->table_exists('fin_account_period_snapshot')
            || !$this->db->table_exists('fin_management_period_metric')
        ) {
            return ['ok' => false, 'message' => 'Fondasi tutup periode belum lengkap. Jalankan SQL foundation terlebih dahulu.'];
        }

        $period = $this->get_period_close_by_id($periodCloseId);
        if (!$period) {
            return ['ok' => false, 'message' => 'Draft period close tidak ditemukan.'];
        }

        $status = strtoupper(trim((string)($period['status'] ?? 'OPEN')));
        if (!in_array($status, ['OPEN', 'REOPENED'], true)) {
            return ['ok' => false, 'message' => 'Period ini tidak bisa diproses karena statusnya bukan OPEN/REOPENED.'];
        }

        $dateStart = (string)($period['period_start'] ?? '');
        $dateEnd = (string)($period['period_end'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStart) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEnd)) {
            return ['ok' => false, 'message' => 'Tanggal period close tidak valid.'];
        }

        $accounts = $this->active_company_accounts();
        if (empty($accounts)) {
            return ['ok' => false, 'message' => 'Belum ada rekening aktif untuk dibuat snapshot.'];
        }

        $this->db->trans_start();

        $this->db->where('period_close_id', $periodCloseId)->delete('fin_account_period_snapshot');
        $this->db->where('period_close_id', $periodCloseId)->delete('fin_management_period_metric');

        $snapshotRows = $this->collect_account_snapshot_rows($dateStart, $dateEnd, $accounts);
        foreach ($snapshotRows as $row) {
            $row['period_close_id'] = $periodCloseId;
            $this->db->insert('fin_account_period_snapshot', $row);
        }

        $metricRows = $this->collect_management_metric_rows($dateStart, $dateEnd, $snapshotRows);
        foreach ($metricRows as $row) {
            $row['period_close_id'] = $periodCloseId;
            $this->db->insert('fin_management_period_metric', $row);
        }

        $this->db->where('id', $periodCloseId)->update('fin_period_close', [
            'status' => 'CLOSED',
            'closed_by' => $actorUserId > 0 ? $actorUserId : null,
            'closed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db->trans_complete();
        if (!$this->db->trans_status()) {
            return ['ok' => false, 'message' => 'Gagal membuat snapshot tutup periode.'];
        }

        return [
            'ok' => true,
            'message' => 'Period berhasil ditutup. Snapshot rekening: ' . count($snapshotRows) . ' | metric manajerial: ' . count($metricRows) . '.',
        ];
    }

    public function get_target_plan_by_id(int $id): ?array
    {
        if ($id <= 0 || !$this->db->table_exists('fin_target_plan')) {
            return null;
        }

        return $this->db->select('tp.*')
            ->from('fin_target_plan tp')
            ->where('tp.id', $id)
            ->limit(1)
            ->get()->row_array() ?: null;
    }

    public function get_target_plan_detail(int $id): ?array
    {
        if ($id <= 0 || !$this->db->table_exists('fin_target_plan')) {
            return null;
        }

        $db = $this->db->select('tp.*');
        if ($this->db->table_exists('mst_operational_division')) {
            $db->select('d.name AS division_name')
                ->join('mst_operational_division d', 'd.id = tp.division_id', 'left');
        }
        if ($this->db->table_exists('fin_company_account')) {
            $db->select('acc.account_name, acc.bank_name')
                ->join('fin_company_account acc', 'acc.id = tp.company_account_id', 'left');
        }

        $row = $db->from('fin_target_plan tp')
            ->where('tp.id', $id)
            ->limit(1)
            ->get()->row_array();

        if (!$row) {
            return null;
        }

        return array_merge($row, [
            'realization_summary' => $this->summarize_target_plan_realization($id),
        ]);
    }

    public function list_target_plan_lines(int $targetPlanId): array
    {
        if (
            $targetPlanId <= 0
            || !$this->db->table_exists('fin_target_plan_line')
        ) {
            return [];
        }

        $db = $this->db->select('tl.*');
        if ($this->db->table_exists('fin_target_realization')) {
            $db->select('lr.actual_value AS latest_actual_value, lr.score_percent AS latest_score_percent, lr.is_passed AS latest_is_passed, lr.realization_date AS latest_realization_date')
                ->join('(
                    SELECT r1.target_plan_line_id, r1.actual_value, r1.score_percent, r1.is_passed, r1.realization_date
                    FROM fin_target_realization r1
                    INNER JOIN (
                        SELECT target_plan_line_id, MAX(realization_date) AS latest_date
                        FROM fin_target_realization
                        GROUP BY target_plan_line_id
                    ) rx
                      ON rx.target_plan_line_id = r1.target_plan_line_id
                     AND rx.latest_date = r1.realization_date
                ) lr', 'lr.target_plan_line_id = tl.id', 'left', false);
        } else {
            $db->select('NULL AS latest_actual_value, NULL AS latest_score_percent, NULL AS latest_is_passed, NULL AS latest_realization_date', false);
        }

        return $db->from('fin_target_plan_line tl')
            ->where('tl.target_plan_id', $targetPlanId)
            ->order_by('tl.metric_group', 'ASC')
            ->order_by('tl.metric_label', 'ASC')
            ->get()->result_array();
    }

    public function summarize_target_plan_realization(int $targetPlanId): array
    {
        $summary = [
            'line_count' => 0,
            'required_count' => 0,
            'weight_total' => 0.0,
            'avg_score_percent' => 0.0,
            'passed_count' => 0,
            'last_realization_date' => null,
        ];

        if ($targetPlanId <= 0 || !$this->db->table_exists('fin_target_plan_line')) {
            return $summary;
        }

        $lineRow = $this->db->select("
                COUNT(*) AS line_count,
                COALESCE(SUM(CASE WHEN is_required = 1 THEN 1 ELSE 0 END), 0) AS required_count,
                COALESCE(SUM(weight_percent), 0) AS weight_total
            ", false)
            ->from('fin_target_plan_line')
            ->where('target_plan_id', $targetPlanId)
            ->get()->row_array();

        if ($lineRow) {
            $summary['line_count'] = (int)($lineRow['line_count'] ?? 0);
            $summary['required_count'] = (int)($lineRow['required_count'] ?? 0);
            $summary['weight_total'] = round((float)($lineRow['weight_total'] ?? 0), 4);
        }

        if ($this->db->table_exists('fin_target_realization')) {
            $realizationRow = $this->db->select("
                    ROUND(COALESCE(AVG(score_percent), 0), 2) AS avg_score_percent,
                    COALESCE(SUM(CASE WHEN is_passed = 1 THEN 1 ELSE 0 END), 0) AS passed_count,
                    MAX(realization_date) AS last_realization_date
                ", false)
                ->from('fin_target_realization')
                ->where('target_plan_id', $targetPlanId)
                ->get()->row_array();

            if ($realizationRow) {
                $summary['avg_score_percent'] = round((float)($realizationRow['avg_score_percent'] ?? 0), 2);
                $summary['passed_count'] = (int)($realizationRow['passed_count'] ?? 0);
                $summary['last_realization_date'] = $realizationRow['last_realization_date'] ?: null;
            }
        }

        return $summary;
    }

    public function update_target_plan(int $targetPlanId, array $payload, int $actorUserId = 0): array
    {
        unset($actorUserId);

        $row = $this->get_target_plan_by_id($targetPlanId);
        if (!$row) {
            return ['ok' => false, 'message' => 'Draft target tidak ditemukan.'];
        }

        $status = strtoupper(trim((string)($payload['status'] ?? ($row['status'] ?? 'DRAFT'))));
        if (!in_array($status, ['DRAFT', 'ACTIVE', 'LOCKED', 'VOID'], true)) {
            $status = 'DRAFT';
        }

        $bonusGateMode = strtoupper(trim((string)($payload['bonus_gate_mode'] ?? ($row['bonus_gate_mode'] ?? 'WEIGHTED_SCORE'))));
        if (!in_array($bonusGateMode, ['NONE', 'ALL_REQUIRED', 'WEIGHTED_SCORE'], true)) {
            $bonusGateMode = 'WEIGHTED_SCORE';
        }

        $update = [
            'status' => $status,
            'bonus_gate_mode' => $bonusGateMode,
            'min_bonus_score' => round((float)($payload['min_bonus_score'] ?? ($row['min_bonus_score'] ?? 100)), 2),
            'bonus_pool_amount' => round((float)($payload['bonus_pool_amount'] ?? ($row['bonus_pool_amount'] ?? 0)), 2),
            'bonus_percent_of_profit' => round((float)($payload['bonus_percent_of_profit'] ?? ($row['bonus_percent_of_profit'] ?? 0)), 4),
            'notes' => trim((string)($payload['notes'] ?? ($row['notes'] ?? ''))) ?: null,
        ];

        $this->db->where('id', $targetPlanId)->update('fin_target_plan', $update);

        return ['ok' => true, 'message' => 'Header target berhasil diperbarui.'];
    }

    public function save_target_plan_lines(int $targetPlanId, array $payload, int $actorUserId = 0): array
    {
        unset($actorUserId);

        $plan = $this->get_target_plan_by_id($targetPlanId);
        if (!$plan) {
            return ['ok' => false, 'message' => 'Draft target tidak ditemukan.'];
        }
        if (!$this->db->table_exists('fin_target_plan_line')) {
            return ['ok' => false, 'message' => 'Tabel metric target belum tersedia.'];
        }

        $lineIds = $payload['line_id'] ?? [];
        if (!is_array($lineIds) || empty($lineIds)) {
            return ['ok' => false, 'message' => 'Belum ada line metric yang dikirim.'];
        }

        $comparator = $payload['comparator'] ?? [];
        $targetValue = $payload['target_value'] ?? [];
        $minimumValue = $payload['minimum_value'] ?? [];
        $maximumValue = $payload['maximum_value'] ?? [];
        $warningValue = $payload['warning_value'] ?? [];
        $weightPercent = $payload['weight_percent'] ?? [];
        $isRequired = $payload['is_required'] ?? [];
        $notes = $payload['line_notes'] ?? [];

        $updated = 0;
        $this->db->trans_start();
        foreach ($lineIds as $idx => $lineIdRaw) {
            $lineId = (int)$lineIdRaw;
            if ($lineId <= 0) {
                continue;
            }

            $comp = strtoupper(trim((string)($comparator[$idx] ?? 'MIN')));
            if (!in_array($comp, ['MIN', 'MAX', 'RANGE', 'EQUAL'], true)) {
                $comp = 'MIN';
            }

            $update = [
                'comparator' => $comp,
                'target_value' => round((float)($targetValue[$idx] ?? 0), 2),
                'minimum_value' => $minimumValue[$idx] !== '' ? round((float)$minimumValue[$idx], 2) : null,
                'maximum_value' => $maximumValue[$idx] !== '' ? round((float)$maximumValue[$idx], 2) : null,
                'warning_value' => $warningValue[$idx] !== '' ? round((float)$warningValue[$idx], 2) : null,
                'weight_percent' => round((float)($weightPercent[$idx] ?? 0), 4),
                'is_required' => isset($isRequired[$lineId]) ? 1 : 0,
                'notes' => trim((string)($notes[$idx] ?? '')) ?: null,
            ];

            $this->db->where('id', $lineId)
                ->where('target_plan_id', $targetPlanId)
                ->update('fin_target_plan_line', $update);
            $updated++;
        }
        $this->db->trans_complete();

        if (!$this->db->trans_status()) {
            return ['ok' => false, 'message' => 'Gagal menyimpan perubahan line metric.'];
        }

        return ['ok' => true, 'message' => 'Metric target berhasil diperbarui (' . $updated . ' baris).'];
    }

    public function generate_target_realization(int $targetPlanId, int $actorUserId = 0): array
    {
        unset($actorUserId);

        if (
            $targetPlanId <= 0
            || !$this->db->table_exists('fin_target_plan')
            || !$this->db->table_exists('fin_target_plan_line')
            || !$this->db->table_exists('fin_target_realization')
        ) {
            return ['ok' => false, 'message' => 'Fondasi target keuangan belum lengkap.'];
        }

        $plan = $this->get_target_plan_by_id($targetPlanId);
        if (!$plan) {
            return ['ok' => false, 'message' => 'Draft target tidak ditemukan.'];
        }
        if (strtoupper(trim((string)($plan['status'] ?? 'DRAFT'))) === 'VOID') {
            return ['ok' => false, 'message' => 'Target berstatus VOID tidak bisa dihitung.'];
        }

        $lines = $this->db->select('tl.*')
            ->from('fin_target_plan_line tl')
            ->where('tl.target_plan_id', $targetPlanId)
            ->order_by('tl.metric_group', 'ASC')
            ->order_by('tl.metric_label', 'ASC')
            ->get()->result_array();
        if (empty($lines)) {
            return ['ok' => false, 'message' => 'Target ini belum punya metric line.'];
        }

        $dateStart = (string)($plan['date_start'] ?? '');
        $dateEnd = (string)($plan['date_end'] ?? '');
        $closedPeriods = [];
        if ($this->db->table_exists('fin_period_close')) {
            $closedPeriods = $this->db->select('id, period_start, period_end, period_type')
                ->from('fin_period_close')
                ->where('status', 'CLOSED')
                ->where('period_start >=', $dateStart)
                ->where('period_end <=', $dateEnd)
                ->order_by('period_end', 'ASC')
                ->get()->result_array();
        }

        if (strtoupper(trim((string)($plan['target_scope'] ?? 'MONTHLY'))) !== 'DAILY' && empty($closedPeriods)) {
            return ['ok' => false, 'message' => 'Belum ada period close CLOSED di rentang target ini. Tutup periodenya dulu baru hitung realisasi.'];
        }

        $rawMetricRows = [];
        if (empty($closedPeriods)) {
            $rawMetricRows = $this->collect_management_metric_rows(
                $dateStart,
                $dateEnd,
                $this->collect_account_snapshot_rows($dateStart, $dateEnd, $this->active_company_accounts())
            );
        }

        $this->db->trans_start();
        foreach ($lines as $line) {
            $resolved = $this->resolve_target_line_actual($plan, $line, $closedPeriods, $rawMetricRows);
            $score = $this->calculate_target_score($line, (float)$resolved['actual_value']);

            $payload = [
                'target_plan_id' => $targetPlanId,
                'target_plan_line_id' => (int)$line['id'],
                'period_close_id' => (int)($resolved['period_close_id'] ?? 0) > 0 ? (int)$resolved['period_close_id'] : null,
                'realization_date' => $dateEnd,
                'metric_code' => (string)($line['metric_code'] ?? ''),
                'target_value_snapshot' => round((float)($line['target_value'] ?? 0), 2),
                'actual_value' => round((float)($resolved['actual_value'] ?? 0), 2),
                'score_percent' => round((float)($score['score_percent'] ?? 0), 2),
                'is_passed' => !empty($score['is_passed']) ? 1 : 0,
                'bonus_gate_passed' => !empty($score['bonus_gate_passed']) ? 1 : 0,
                'notes' => $resolved['notes'] !== '' ? $resolved['notes'] : null,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $exists = $this->db->select('id')
                ->from('fin_target_realization')
                ->where('target_plan_line_id', (int)$line['id'])
                ->where('realization_date', $dateEnd)
                ->limit(1)
                ->get()->row_array();

            if ($exists) {
                $this->db->where('id', (int)$exists['id'])->update('fin_target_realization', $payload);
            } else {
                $payload['created_at'] = date('Y-m-d H:i:s');
                $this->db->insert('fin_target_realization', $payload);
            }
        }
        $this->db->trans_complete();

        if (!$this->db->trans_status()) {
            return ['ok' => false, 'message' => 'Gagal menghitung realisasi target.'];
        }

        return ['ok' => true, 'message' => 'Realisasi target berhasil dihitung untuk ' . count($lines) . ' metric.'];
    }

    public function get_target_progress_snapshot(int $targetPlanId, string $asOfDate = ''): array
    {
        $default = [
            'ok' => false,
            'applicable' => false,
            'target_plan_id' => $targetPlanId,
            'plan' => null,
            'date_start' => null,
            'date_end' => null,
            'as_of_date' => $asOfDate,
            'line_count' => 0,
            'avg_score_percent' => 100.00,
            'all_required_passed' => true,
            'required_failed_count' => 0,
            'notes' => 'Target netral',
            'lines' => [],
        ];

        if (
            $targetPlanId <= 0
            || !$this->db->table_exists('fin_target_plan')
            || !$this->db->table_exists('fin_target_plan_line')
        ) {
            return $default;
        }

        $plan = $this->get_target_plan_by_id($targetPlanId);
        if (!$plan) {
            $default['notes'] = 'Target tidak ditemukan.';
            return $default;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOfDate)) {
            $asOfDate = date('Y-m-d');
        }

        $planStart = (string)($plan['date_start'] ?? '');
        $planEnd = (string)($plan['date_end'] ?? '');
        $scope = strtoupper(trim((string)($plan['target_scope'] ?? 'MONTHLY')));
        $default['plan'] = $plan;
        $default['date_start'] = $planStart;
        $default['date_end'] = $planEnd;
        $default['as_of_date'] = $asOfDate;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $planStart) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $planEnd)) {
            $default['notes'] = 'Rentang target belum valid.';
            return $default;
        }

        if ($asOfDate < $planStart || $asOfDate > $planEnd) {
            $default['notes'] = 'Tanggal bonus berada di luar rentang target.';
            return $default;
        }

        $effectiveStart = $scope === 'DAILY' ? $asOfDate : $planStart;
        $effectiveEnd = $scope === 'DAILY' ? $asOfDate : min($asOfDate, $planEnd);

        $lines = $this->db->select('tl.*')
            ->from('fin_target_plan_line tl')
            ->where('tl.target_plan_id', $targetPlanId)
            ->order_by('tl.metric_group', 'ASC')
            ->order_by('tl.metric_label', 'ASC')
            ->get()->result_array();
        if (empty($lines)) {
            $default['notes'] = 'Target belum punya metric line.';
            return $default;
        }

        $snapshotRows = $this->collect_account_snapshot_rows($effectiveStart, $effectiveEnd, $this->active_company_accounts());
        $rawMetricRows = $this->collect_management_metric_rows($effectiveStart, $effectiveEnd, $snapshotRows);

        $weightedScoreSum = 0.0;
        $weightTotal = 0.0;
        $requiredFailedCount = 0;
        $lineResults = [];

        foreach ($lines as $line) {
            $resolved = $this->resolve_target_line_actual($plan, $line, [], $rawMetricRows);
            $score = $this->calculate_target_score($line, (float)($resolved['actual_value'] ?? 0));
            $weight = round((float)($line['weight_percent'] ?? 0), 4);
            if ($weight <= 0) {
                $weight = 1.0;
            }

            $weightedScoreSum += ((float)($score['score_percent'] ?? 0) * $weight);
            $weightTotal += $weight;
            if ((int)($line['is_required'] ?? 0) === 1 && empty($score['bonus_gate_passed'])) {
                $requiredFailedCount++;
            }

            $lineResults[] = [
                'line_id' => (int)($line['id'] ?? 0),
                'metric_code' => (string)($line['metric_code'] ?? ''),
                'metric_label' => (string)($line['metric_label'] ?? ''),
                'target_value' => round((float)($line['target_value'] ?? 0), 2),
                'actual_value' => round((float)($resolved['actual_value'] ?? 0), 2),
                'score_percent' => round((float)($score['score_percent'] ?? 0), 2),
                'is_passed' => !empty($score['is_passed']) ? 1 : 0,
                'bonus_gate_passed' => !empty($score['bonus_gate_passed']) ? 1 : 0,
                'weight_percent' => $weight,
                'is_required' => (int)($line['is_required'] ?? 0),
                'notes' => (string)($resolved['notes'] ?? ''),
            ];
        }

        $avgScore = $weightTotal > 0 ? round($weightedScoreSum / $weightTotal, 2) : 100.00;

        return [
            'ok' => true,
            'applicable' => true,
            'target_plan_id' => $targetPlanId,
            'plan' => $plan,
            'date_start' => $effectiveStart,
            'date_end' => $effectiveEnd,
            'as_of_date' => $asOfDate,
            'line_count' => count($lineResults),
            'avg_score_percent' => $avgScore,
            'all_required_passed' => $requiredFailedCount === 0,
            'required_failed_count' => $requiredFailedCount,
            'notes' => 'Progress target ' . $effectiveStart . ' s/d ' . $effectiveEnd,
            'lines' => $lineResults,
        ];
    }

    private function collect_account_snapshot_rows(string $dateStart, string $dateEnd, array $accounts): array
    {
        if (empty($accounts)) {
            return [];
        }

        $accountIds = array_values(array_filter(array_map(static function ($row) {
            return (int)($row['id'] ?? 0);
        }, $accounts)));
        $mutationMap = $this->account_month_mutation_map($dateStart, $dateEnd, $accountIds);
        $breakdownMap = $this->account_mutation_breakdown_map($dateStart, $dateEnd, $accountIds);
        $payableMap = $this->loan_outstanding_as_of_map('payable', $dateEnd, $accountIds);
        $receivableMap = $this->loan_outstanding_as_of_map('receivable', $dateEnd, $accountIds);
        $cashAdvanceMap = $this->cash_advance_outstanding_as_of_map($dateEnd, $accountIds);
        $payrollMap = $this->payroll_pending_as_of_map($dateEnd, $accountIds);

        $rows = [];
        foreach ($accounts as $account) {
            $accountId = (int)($account['id'] ?? 0);
            if ($accountId <= 0) {
                continue;
            }

            $opening = $this->opening_balance_for_month($accountId, $dateStart, (float)($account['opening_balance'] ?? 0));
            $movement = (array)($mutationMap[$accountId] ?? []);
            $breakdown = (array)($breakdownMap[$accountId] ?? []);
            $payable = (array)($payableMap[$accountId] ?? []);
            $receivable = (array)($receivableMap[$accountId] ?? []);
            $cashAdvance = (array)($cashAdvanceMap[$accountId] ?? []);
            $payroll = (array)($payrollMap[$accountId] ?? []);

            $mutationIn = round((float)($movement['amount_in'] ?? 0), 2);
            $mutationOut = round((float)($movement['amount_out'] ?? 0), 2);
            $closingPhysical = round($opening + ((float)($movement['net_amount'] ?? 0)), 2);
            $payableOutstanding = round((float)($payable['outstanding_total'] ?? 0), 2);
            $receivableOutstanding = round((float)($receivable['outstanding_total'] ?? 0), 2);
            $cashAdvanceOutstanding = round((float)($cashAdvance['outstanding_total'] ?? 0), 2);
            $payrollPending = round((float)($payroll['pending_total'] ?? 0), 2);
            $historicalNet = round((float)($receivable['keep_total'] ?? 0) - (float)($payable['keep_total'] ?? 0), 2);
            $closingReal = round($closingPhysical + $receivableOutstanding + $cashAdvanceOutstanding - $payableOutstanding - $payrollPending, 2);

            $rows[] = [
                'company_account_id' => $accountId,
                'account_code_snapshot' => (string)($account['account_code'] ?? ''),
                'account_name_snapshot' => (string)($account['account_name'] ?? ''),
                'account_type_snapshot' => (string)($account['account_type'] ?? ''),
                'bank_name_snapshot' => (string)($account['bank_name'] ?? ''),
                'opening_balance_physical' => round($opening, 2),
                'mutation_in_total' => $mutationIn,
                'mutation_out_total' => $mutationOut,
                'closing_balance_physical' => $closingPhysical,
                'receivable_outstanding' => $receivableOutstanding,
                'payable_outstanding' => $payableOutstanding,
                'cash_advance_outstanding' => $cashAdvanceOutstanding,
                'payroll_pending' => $payrollPending,
                'historical_keep_balance_net' => $historicalNet,
                'closing_balance_real' => $closingReal,
                'pos_in_total' => round((float)($breakdown['pos_in_total'] ?? 0), 2),
                'pos_refund_out_total' => round((float)($breakdown['pos_refund_out_total'] ?? 0), 2),
                'purchase_out_total' => round((float)($breakdown['purchase_out_total'] ?? 0), 2),
                'payroll_out_total' => round((float)($breakdown['payroll_out_total'] ?? 0), 2),
                'cash_advance_out_total' => round((float)($breakdown['cash_advance_out_total'] ?? 0), 2),
                'payable_in_total' => round((float)($breakdown['payable_in_total'] ?? 0), 2),
                'payable_payment_out_total' => round((float)($breakdown['payable_payment_out_total'] ?? 0), 2),
                'receivable_out_total' => round((float)($breakdown['receivable_out_total'] ?? 0), 2),
                'receivable_payment_in_total' => round((float)($breakdown['receivable_payment_in_total'] ?? 0), 2),
                'transfer_in_total' => round((float)($breakdown['transfer_in_total'] ?? 0), 2),
                'transfer_out_total' => round((float)($breakdown['transfer_out_total'] ?? 0), 2),
                'manual_in_total' => round((float)($breakdown['manual_in_total'] ?? 0), 2),
                'manual_out_total' => round((float)($breakdown['manual_out_total'] ?? 0), 2),
                'notes' => 'Snapshot ' . $dateStart . ' s/d ' . $dateEnd,
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }

        return $rows;
    }

    private function collect_management_metric_rows(string $dateStart, string $dateEnd, array $snapshotRows): array
    {
        $bucket = [];
        $monthStart = date('Y-m-01', strtotime($dateStart));
        $monthEnd = date('Y-m-01', strtotime($dateEnd));

        $physicalTotal = 0.0;
        $realTotal = 0.0;
        $payableTotal = 0.0;
        $receivableTotal = 0.0;
        $cashAdvanceTotal = 0.0;
        foreach ($snapshotRows as $row) {
            $accountId = (int)($row['company_account_id'] ?? 0);
            $physical = round((float)($row['closing_balance_physical'] ?? 0), 2);
            $real = round((float)($row['closing_balance_real'] ?? 0), 2);
            $payable = round((float)($row['payable_outstanding'] ?? 0), 2);
            $receivable = round((float)($row['receivable_outstanding'] ?? 0), 2);
            $cashAdvance = round((float)($row['cash_advance_outstanding'] ?? 0), 2);

            $this->metric_add($bucket, 'ACCOUNT', $accountId, 'CASH_POSITION', 'PHYSICAL_BALANCE_VALUE', 'Saldo Fisik', $physical, 0, 'snapshot_account', 'Snapshot saldo fisik per rekening');
            $this->metric_add($bucket, 'ACCOUNT', $accountId, 'CASH_POSITION', 'REAL_BALANCE_VALUE', 'Saldo Riil', $real, 0, 'snapshot_account', 'Snapshot saldo riil per rekening');
            $this->metric_add($bucket, 'ACCOUNT', $accountId, 'EXPOSURE', 'PAYABLE_OUTSTANDING', 'Utang Outstanding', $payable, 0, 'snapshot_account', 'Utang aktif per rekening');
            $this->metric_add($bucket, 'ACCOUNT', $accountId, 'EXPOSURE', 'RECEIVABLE_OUTSTANDING', 'Piutang Outstanding', $receivable, 0, 'snapshot_account', 'Piutang aktif per rekening');

            $physicalTotal += $physical;
            $realTotal += $real;
            $payableTotal += $payable;
            $receivableTotal += $receivable;
            $cashAdvanceTotal += $cashAdvance;
        }
        $this->metric_add($bucket, 'GLOBAL', 0, 'CASH_POSITION', 'PHYSICAL_BALANCE_VALUE', 'Saldo Fisik', $physicalTotal, 0, 'snapshot_global', 'Akumulasi saldo fisik semua rekening');
        $this->metric_add($bucket, 'GLOBAL', 0, 'CASH_POSITION', 'REAL_BALANCE_VALUE', 'Saldo Riil', $realTotal, 0, 'snapshot_global', 'Akumulasi saldo riil semua rekening');
        $this->metric_add($bucket, 'GLOBAL', 0, 'EXPOSURE', 'PAYABLE_OUTSTANDING', 'Utang Outstanding', $payableTotal, 0, 'snapshot_global', 'Akumulasi utang outstanding');
        $this->metric_add($bucket, 'GLOBAL', 0, 'EXPOSURE', 'RECEIVABLE_OUTSTANDING', 'Piutang Outstanding', $receivableTotal, 0, 'snapshot_global', 'Akumulasi piutang outstanding');
        $this->metric_add($bucket, 'GLOBAL', 0, 'EXPOSURE', 'CASH_ADVANCE_OUTSTANDING', 'Kasbon Outstanding', $cashAdvanceTotal, 0, 'snapshot_global', 'Akumulasi kasbon outstanding');

        if ($this->db->table_exists('fin_account_mutation_log')) {
            $row = $this->db->select("
                    COALESCE(SUM(CASE WHEN ref_module = 'POS' AND mutation_type = 'IN' THEN amount ELSE 0 END), 0) AS pos_revenue,
                    COALESCE(SUM(CASE WHEN ref_module = 'POS' AND mutation_type = 'OUT' THEN amount ELSE 0 END), 0) AS pos_refund,
                    COALESCE(SUM(CASE WHEN ref_module = 'PAYROLL' AND ref_table = 'pay_salary_disbursement' AND mutation_type = 'OUT' THEN amount ELSE 0 END), 0) AS payroll_disbursed
                ", false)
                ->from('fin_account_mutation_log')
                ->where('mutation_date >=', $dateStart)
                ->where('mutation_date <=', $dateEnd)
                ->get()->row_array();

            $this->metric_add($bucket, 'GLOBAL', 0, 'REVENUE', 'POS_REVENUE', 'Omzet POS', (float)($row['pos_revenue'] ?? 0), 0, 'fin_account_mutation_log', 'Akumulasi mutasi masuk POS');
            $this->metric_add($bucket, 'GLOBAL', 0, 'REVENUE', 'POS_REFUND', 'Refund POS', (float)($row['pos_refund'] ?? 0), 0, 'fin_account_mutation_log', 'Akumulasi mutasi refund POS');
            $this->metric_add($bucket, 'GLOBAL', 0, 'PAYROLL', 'PAYROLL_DISBURSED', 'Pencairan Gaji', (float)($row['payroll_disbursed'] ?? 0), 0, 'fin_account_mutation_log', 'Akumulasi gaji cair');
        }

        if (
            $this->db->table_exists('pur_purchase_order')
            && $this->db->table_exists('pur_purchase_order_line')
            && $this->db->table_exists('mst_purchase_type')
            && $this->db->table_exists('mst_posting_type')
        ) {
            $rows = $this->db->select("
                    pt.type_code,
                    pt.type_name,
                    pst.affects_inventory,
                    pst.affects_asset,
                    pst.affects_expense,
                    pst.affects_service,
                    COALESCE(SUM(l.line_subtotal), 0) AS total_value
                ", false)
                ->from('pur_purchase_order po')
                ->join('pur_purchase_order_line l', 'l.purchase_order_id = po.id', 'inner')
                ->join('mst_purchase_type pt', 'pt.id = po.purchase_type_id', 'left')
                ->join('mst_posting_type pst', 'pst.id = pt.posting_type_id', 'left')
                ->where('po.request_date >=', $dateStart)
                ->where('po.request_date <=', $dateEnd)
                ->where('po.status <>', 'VOID')
                ->group_by(['pt.type_code', 'pt.type_name', 'pst.affects_inventory', 'pst.affects_asset', 'pst.affects_expense', 'pst.affects_service'])
                ->get()->result_array();

            foreach ($rows as $row) {
                $metricCode = $this->purchase_metric_code_for_row($row);
                $metricLabel = $this->metric_label_for_code($metricCode);
                $this->metric_add($bucket, 'GLOBAL', 0, 'PURCHASE', $metricCode, $metricLabel, (float)($row['total_value'] ?? 0), 0, 'pur_purchase_order', 'Akumulasi belanja per purchase type');
            }
        }

        $planning = $this->planning_summary($dateStart, $dateEnd);
        $this->metric_add($bucket, 'GLOBAL', 0, 'PROCUREMENT', 'SR_PENDING_VALUE', 'SR Pending', (float)($planning['store_request_pending_value'] ?? 0), 0, 'pur_store_request', 'Estimasi store request yang masih pending');
        $this->metric_add($bucket, 'GLOBAL', 0, 'PAYROLL', 'PAYROLL_ESTIMATE_RUNNING', 'Estimasi Gaji Berjalan', (float)($planning['salary_estimate_running'] ?? 0), 0, 'payroll_preview', 'Estimasi payroll berjalan');

        foreach ($this->division_metric_rows($dateStart, $dateEnd, $monthStart, $monthEnd) as $row) {
            $this->metric_add(
                $bucket,
                'DIVISION',
                (int)$row['scope_ref_id'],
                (string)$row['metric_group'],
                (string)$row['metric_code'],
                (string)$row['metric_label'],
                (float)$row['metric_amount'],
                (float)$row['metric_qty'],
                (string)$row['source_ref'],
                (string)$row['notes']
            );
        }

        foreach ($this->global_inventory_metric_rows($dateStart, $dateEnd, $monthStart, $monthEnd) as $row) {
            $this->metric_add(
                $bucket,
                'GLOBAL',
                0,
                (string)$row['metric_group'],
                (string)$row['metric_code'],
                (string)$row['metric_label'],
                (float)$row['metric_amount'],
                (float)$row['metric_qty'],
                (string)$row['source_ref'],
                (string)$row['notes']
            );
        }

        $globalNetRevenue = $this->bucket_metric_amount($bucket, 'GLOBAL', 0, 'POS_REVENUE')
            - $this->bucket_metric_amount($bucket, 'GLOBAL', 0, 'POS_REFUND');
        $globalEstimatedCost = $this->bucket_metric_amount($bucket, 'GLOBAL', 0, 'LIVE_HPP_VALUE')
            + $this->bucket_metric_amount($bucket, 'GLOBAL', 0, 'PURCHASE_OPERATIONAL')
            + $this->bucket_metric_amount($bucket, 'GLOBAL', 0, 'PURCHASE_UTILITY')
            + $this->bucket_metric_amount($bucket, 'GLOBAL', 0, 'PURCHASE_OTHER')
            + $this->bucket_metric_amount($bucket, 'GLOBAL', 0, 'PAYROLL_ESTIMATE_RUNNING')
            + $this->bucket_metric_amount($bucket, 'GLOBAL', 0, 'WAREHOUSE_ADJUSTMENT_VALUE')
            + $this->bucket_metric_amount($bucket, 'GLOBAL', 0, 'DIVISION_ADJUSTMENT_VALUE')
            + $this->bucket_metric_amount($bucket, 'GLOBAL', 0, 'COMPONENT_ADJUSTMENT_VALUE');
        $globalEstimatedProfit = round($globalNetRevenue - $globalEstimatedCost, 2);
        $globalEstimatedProfitPct = $globalNetRevenue > 0
            ? round(($globalEstimatedProfit / $globalNetRevenue) * 100, 2)
            : 0.00;

        $this->metric_add($bucket, 'GLOBAL', 0, 'PROFITABILITY', 'ESTIMATED_PROFIT_VALUE', 'Profit Estimasi', $globalEstimatedProfit, 0, 'derived_global', 'Estimasi profit = omzet bersih - HPP live - beban operasional - estimasi gaji - adjustment.');
        $this->metric_add($bucket, 'GLOBAL', 0, 'PROFITABILITY', 'ESTIMATED_PROFIT_PERCENT', 'Margin Profit Estimasi %', $globalEstimatedProfitPct, 0, 'derived_global', 'Margin estimasi dihitung dari profit estimasi dibanding omzet bersih.');

        return array_values($bucket);
    }

    private function division_metric_rows(string $dateStart, string $dateEnd, string $monthStart, string $monthEnd): array
    {
        $rows = [];
        $latestMonth = $monthEnd;

        if ($this->db->table_exists('mst_operational_division')) {
            $divisions = $this->division_options();
            foreach ($divisions as $division) {
                $divisionId = (int)($division['id'] ?? 0);
                if ($divisionId <= 0) {
                    continue;
                }
                $planning = $this->planning_summary_by_division($dateStart, $dateEnd, $divisionId);
                $rows[] = [
                    'scope_ref_id' => $divisionId,
                    'metric_group' => 'PROCUREMENT',
                    'metric_code' => 'SR_PENDING_VALUE',
                    'metric_label' => 'SR Pending',
                    'metric_amount' => (float)($planning['store_request_pending_value'] ?? 0),
                    'metric_qty' => 0,
                    'source_ref' => 'pur_store_request',
                    'notes' => 'Store request pending per divisi',
                ];
                $rows[] = [
                    'scope_ref_id' => $divisionId,
                    'metric_group' => 'PAYROLL',
                    'metric_code' => 'PAYROLL_ESTIMATE_RUNNING',
                    'metric_label' => 'Estimasi Gaji Berjalan',
                    'metric_amount' => (float)($planning['salary_estimate_running'] ?? 0),
                    'metric_qty' => 0,
                    'source_ref' => 'payroll_preview',
                    'notes' => 'Estimasi payroll per divisi',
                ];
            }
        }

        if ($this->db->table_exists('inv_division_monthly_stock')) {
            $flowRows = $this->db->select("
                    division_id,
                    COALESCE(SUM(in_total_value), 0) AS raw_in_value,
                    COALESCE(SUM(out_total_value), 0) AS usage_value,
                    COALESCE(SUM(discarded_total_value + spoilage_total_value + waste_total_value + process_loss_total_value + variance_total_value + adjustment_minus_total_value), 0) AS adjustment_value
                ", false)
                ->from('inv_division_monthly_stock')
                ->where('month_key >=', $monthStart)
                ->where('month_key <=', $monthEnd)
                ->group_by('division_id')
                ->get()->result_array();
            foreach ($flowRows as $row) {
                $divisionId = (int)($row['division_id'] ?? 0);
                $rows[] = [
                    'scope_ref_id' => $divisionId,
                    'metric_group' => 'INVENTORY_FLOW',
                    'metric_code' => 'RAW_MATERIAL_IN_VALUE',
                    'metric_label' => 'Bahan Baku Masuk',
                    'metric_amount' => (float)($row['raw_in_value'] ?? 0),
                    'metric_qty' => 0,
                    'source_ref' => 'inv_division_monthly_stock',
                    'notes' => 'Akumulasi bahan baku masuk divisi',
                ];
                $rows[] = [
                    'scope_ref_id' => $divisionId,
                    'metric_group' => 'INVENTORY_FLOW',
                    'metric_code' => 'RAW_MATERIAL_USAGE_VALUE',
                    'metric_label' => 'Bahan Baku Terpakai',
                    'metric_amount' => (float)($row['usage_value'] ?? 0),
                    'metric_qty' => 0,
                    'source_ref' => 'inv_division_monthly_stock',
                    'notes' => 'Akumulasi pemakaian bahan baku divisi',
                ];
                $rows[] = [
                    'scope_ref_id' => $divisionId,
                    'metric_group' => 'INVENTORY_ADJ',
                    'metric_code' => 'DIVISION_ADJUSTMENT_VALUE',
                    'metric_label' => 'Adjustment Bahan Baku Divisi',
                    'metric_amount' => (float)($row['adjustment_value'] ?? 0),
                    'metric_qty' => 0,
                    'source_ref' => 'inv_division_monthly_stock',
                    'notes' => 'Akumulasi koreksi / waste / spoil bahan baku divisi',
                ];
            }

            $endingRows = $this->db->select('division_id, COALESCE(SUM(total_value), 0) AS ending_value', false)
                ->from('inv_division_monthly_stock')
                ->where('month_key', $latestMonth)
                ->group_by('division_id')
                ->get()->result_array();
            foreach ($endingRows as $row) {
                $rows[] = [
                    'scope_ref_id' => (int)($row['division_id'] ?? 0),
                    'metric_group' => 'INVENTORY_POSITION',
                    'metric_code' => 'DIVISION_ENDING_STOCK_VALUE',
                    'metric_label' => 'Stok Akhir Divisi',
                    'metric_amount' => (float)($row['ending_value'] ?? 0),
                    'metric_qty' => 0,
                    'source_ref' => 'inv_division_monthly_stock',
                    'notes' => 'Nilai stok akhir divisi di bulan terakhir period',
                ];
            }
        }

        if ($this->db->table_exists('inv_component_monthly_stock')) {
            $componentRows = $this->db->select("
                    division_id,
                    COALESCE(SUM(waste_total_value + spoil_total_value + adjustment_minus_total_value), 0) AS adjustment_value
                ", false)
                ->from('inv_component_monthly_stock')
                ->where('month_key >=', $monthStart)
                ->where('month_key <=', $monthEnd)
                ->where('division_id IS NOT NULL', null, false)
                ->group_by('division_id')
                ->get()->result_array();
            foreach ($componentRows as $row) {
                $rows[] = [
                    'scope_ref_id' => (int)($row['division_id'] ?? 0),
                    'metric_group' => 'INVENTORY_ADJ',
                    'metric_code' => 'COMPONENT_ADJUSTMENT_VALUE',
                    'metric_label' => 'Adjustment Component',
                    'metric_amount' => (float)($row['adjustment_value'] ?? 0),
                    'metric_qty' => 0,
                    'source_ref' => 'inv_component_monthly_stock',
                    'notes' => 'Akumulasi koreksi / waste / spoil component',
                ];
            }
        }

        if ($this->db->table_exists('pos_order') && $this->db->table_exists('pos_order_line')) {
            $posRows = $this->db->select("
                    COALESCE(l.operational_division_id, 0) AS division_id,
                    COALESCE(SUM(CASE
                        WHEN o.stock_commit_status = 'POSTED'
                         AND o.paid_at IS NOT NULL
                         AND o.status IN ('PAID','READY','SERVED','REFUND_PARTIAL','REFUND_FULL')
                        THEN COALESCE(NULLIF(l.cogs_amount, 0), (l.qty * l.hpp_live_snapshot))
                        ELSE 0
                    END), 0) AS live_hpp_total
                ", false)
                ->from('pos_order_line l')
                ->join('pos_order o', 'o.id = l.order_id', 'inner')
                ->where('DATE(o.paid_at) >=', $dateStart)
                ->where('DATE(o.paid_at) <=', $dateEnd)
                ->group_by('COALESCE(l.operational_division_id, 0)', false)
                ->get()->result_array();
            foreach ($posRows as $row) {
                $divisionId = (int)($row['division_id'] ?? 0);
                if ($divisionId <= 0) {
                    continue;
                }
                $rows[] = [
                    'scope_ref_id' => $divisionId,
                    'metric_group' => 'INVENTORY_COST',
                    'metric_code' => 'LIVE_HPP_VALUE',
                    'metric_label' => 'HPP Live',
                    'metric_amount' => (float)($row['live_hpp_total'] ?? 0),
                    'metric_qty' => 0,
                    'source_ref' => 'pos_order_line',
                    'notes' => 'HPP live aktual dari transaksi POS',
                ];
            }
        }

        if ($this->db->table_exists('pay_cash_advance') && $this->db->table_exists('org_employee')) {
            $cashRows = $this->db->select("
                    e.division_id,
                    COALESCE(SUM(ca.outstanding_amount), 0) AS outstanding_total
                ", false)
                ->from('pay_cash_advance ca')
                ->join('org_employee e', 'e.id = ca.employee_id', 'left')
                ->where('ca.status <>', 'VOID')
                ->where('COALESCE(ca.outstanding_amount, 0) >', 0)
                ->group_by('e.division_id')
                ->get()->result_array();
            foreach ($cashRows as $row) {
                $divisionId = (int)($row['division_id'] ?? 0);
                if ($divisionId <= 0) {
                    continue;
                }
                $rows[] = [
                    'scope_ref_id' => $divisionId,
                    'metric_group' => 'EXPOSURE',
                    'metric_code' => 'CASH_ADVANCE_OUTSTANDING',
                    'metric_label' => 'Kasbon Outstanding',
                    'metric_amount' => (float)($row['outstanding_total'] ?? 0),
                    'metric_qty' => 0,
                    'source_ref' => 'pay_cash_advance',
                    'notes' => 'Kasbon aktif per divisi pegawai',
                ];
            }
        }

        return $rows;
    }

    private function global_inventory_metric_rows(string $dateStart, string $dateEnd, string $monthStart, string $monthEnd): array
    {
        $rows = [];
        $latestMonth = $monthEnd;

        if ($this->db->table_exists('inv_warehouse_monthly_stock')) {
            $flow = $this->db->select("
                    COALESCE(SUM(discarded_total_value + spoilage_total_value + waste_total_value + process_loss_total_value + variance_total_value + adjustment_minus_total_value), 0) AS adjustment_value
                ", false)
                ->from('inv_warehouse_monthly_stock')
                ->where('month_key >=', $monthStart)
                ->where('month_key <=', $monthEnd)
                ->get()->row_array();
            $rows[] = [
                'metric_group' => 'INVENTORY_ADJ',
                'metric_code' => 'WAREHOUSE_ADJUSTMENT_VALUE',
                'metric_label' => 'Adjustment Gudang',
                'metric_amount' => (float)($flow['adjustment_value'] ?? 0),
                'metric_qty' => 0,
                'source_ref' => 'inv_warehouse_monthly_stock',
                'notes' => 'Akumulasi koreksi / waste / spoil gudang',
            ];

            $ending = $this->db->select('COALESCE(SUM(total_value), 0) AS ending_value', false)
                ->from('inv_warehouse_monthly_stock')
                ->where('month_key', $latestMonth)
                ->get()->row_array();
            $rows[] = [
                'metric_group' => 'INVENTORY_POSITION',
                'metric_code' => 'WAREHOUSE_ENDING_STOCK_VALUE',
                'metric_label' => 'Stok Akhir Gudang',
                'metric_amount' => (float)($ending['ending_value'] ?? 0),
                'metric_qty' => 0,
                'source_ref' => 'inv_warehouse_monthly_stock',
                'notes' => 'Nilai stok akhir gudang di bulan terakhir period',
            ];
        }

        if ($this->db->table_exists('pos_order') && $this->db->table_exists('pos_order_line')) {
            $row = $this->db->select("
                    COALESCE(SUM(CASE
                        WHEN o.stock_commit_status = 'POSTED'
                         AND o.paid_at IS NOT NULL
                         AND o.status IN ('PAID','READY','SERVED','REFUND_PARTIAL','REFUND_FULL')
                        THEN COALESCE(NULLIF(l.cogs_amount, 0), (l.qty * l.hpp_live_snapshot))
                        ELSE 0
                    END), 0) AS live_hpp_total
                ", false)
                ->from('pos_order_line l')
                ->join('pos_order o', 'o.id = l.order_id', 'inner')
                ->where('DATE(o.paid_at) >=', $dateStart)
                ->where('DATE(o.paid_at) <=', $dateEnd)
                ->get()->row_array();
            $rows[] = [
                'metric_group' => 'INVENTORY_COST',
                'metric_code' => 'LIVE_HPP_VALUE',
                'metric_label' => 'HPP Live',
                'metric_amount' => (float)($row['live_hpp_total'] ?? 0),
                'metric_qty' => 0,
                'source_ref' => 'pos_order_line',
                'notes' => 'HPP live aktual transaksi POS',
            ];
        }

        return $rows;
    }

    private function planning_summary_by_division(string $dateStart, string $dateEnd, int $divisionId = 0): array
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
                    'division_id' => $divisionId,
                ]);
                $summary['store_request_pending_value'] = round((float)($srSummary['pending_fulfillment_value_total'] ?? 0), 2);
            }
        }

        if (file_exists(APPPATH . 'models/Payroll_preview_model.php')) {
            $this->load->model('Payroll_preview_model');
            if (method_exists($this->Payroll_preview_model, 'count_monthly_recap') && method_exists($this->Payroll_preview_model, 'list_monthly_recap')) {
                $filters = [
                    'q' => '',
                    'division_id' => $divisionId,
                    'position_id' => 0,
                    'date_start' => $dateStart,
                    'date_end' => $dateEnd,
                ];
                $total = (int)$this->Payroll_preview_model->count_monthly_recap($filters);
                if ($total > 0) {
                    $payRows = $this->Payroll_preview_model->list_monthly_recap($filters, min($total, 5000), 0);
                    $sum = 0.0;
                    foreach ($payRows as $row) {
                        $sum += (float)($row['net_total'] ?? 0);
                    }
                    $summary['salary_estimate_running'] = round($sum, 2);
                }
            }
        }

        return $summary;
    }

    private function metric_add(array &$bucket, string $scopeType, int $scopeRefId, string $metricGroup, string $metricCode, string $metricLabel, float $amount, float $qty = 0.0, string $sourceRef = '', string $notes = ''): void
    {
        $key = $scopeType . '|' . $scopeRefId . '|' . $metricCode;
        if (!isset($bucket[$key])) {
            $bucket[$key] = [
                'scope_type' => $scopeType,
                'scope_ref_id' => $scopeRefId,
                'metric_group' => $metricGroup,
                'metric_code' => $metricCode,
                'metric_label' => $metricLabel,
                'metric_amount' => 0.0,
                'metric_qty' => 0.0,
                'source_ref' => $sourceRef !== '' ? $sourceRef : null,
                'notes' => $notes !== '' ? $notes : null,
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }

        $bucket[$key]['metric_amount'] = round((float)$bucket[$key]['metric_amount'] + round($amount, 2), 2);
        $bucket[$key]['metric_qty'] = round((float)$bucket[$key]['metric_qty'] + round($qty, 4), 4);
    }

    private function bucket_metric_amount(array $bucket, string $scopeType, int $scopeRefId, string $metricCode): float
    {
        $key = $scopeType . '|' . $scopeRefId . '|' . $metricCode;
        return round((float)($bucket[$key]['metric_amount'] ?? 0), 2);
    }

    private function purchase_metric_code_for_row(array $row): string
    {
        $typeCode = strtoupper(trim((string)($row['type_code'] ?? '')));
        $typeName = strtoupper(trim((string)($row['type_name'] ?? '')));
        $affectsInventory = (int)($row['affects_inventory'] ?? 0) === 1;
        $affectsAsset = (int)($row['affects_asset'] ?? 0) === 1;
        $affectsExpense = (int)($row['affects_expense'] ?? 0) === 1;

        if ($affectsAsset || strpos($typeCode, 'ASET') !== false || strpos($typeName, 'ASET') !== false) {
            return 'PURCHASE_ASSET';
        }
        if (strpos($typeCode, 'UTILITY') !== false || strpos($typeName, 'UTILITY') !== false || strpos($typeName, 'LISTRIK') !== false || strpos($typeName, 'AIR') !== false) {
            return 'PURCHASE_UTILITY';
        }
        if ($affectsInventory || strpos($typeCode, 'INV_') === 0) {
            return 'PURCHASE_RAW_MATERIAL';
        }
        if ($affectsExpense || in_array($typeCode, ['BEBAN', 'JASA'], true)) {
            return 'PURCHASE_OPERATIONAL';
        }
        return 'PURCHASE_OTHER';
    }

    private function metric_label_for_code(string $metricCode): string
    {
        static $map = [
            'POS_REVENUE' => 'Omzet POS',
            'POS_REFUND' => 'Refund POS',
            'PURCHASE_RAW_MATERIAL' => 'Belanja Bahan Baku',
            'PURCHASE_OPERATIONAL' => 'Belanja Operasional',
            'PURCHASE_UTILITY' => 'Belanja Utilitas',
            'PURCHASE_ASSET' => 'Belanja Aset',
            'PURCHASE_OTHER' => 'Belanja Lainnya',
            'SR_PENDING_VALUE' => 'SR Pending',
            'PAYROLL_ESTIMATE_RUNNING' => 'Estimasi Gaji Berjalan',
            'PAYROLL_DISBURSED' => 'Pencairan Gaji',
            'PAYABLE_OUTSTANDING' => 'Utang Outstanding',
            'RECEIVABLE_OUTSTANDING' => 'Piutang Outstanding',
            'CASH_ADVANCE_OUTSTANDING' => 'Kasbon Outstanding',
            'LIVE_HPP_VALUE' => 'HPP Live',
            'WAREHOUSE_ADJUSTMENT_VALUE' => 'Adjustment Gudang',
            'DIVISION_ADJUSTMENT_VALUE' => 'Adjustment Bahan Baku Divisi',
            'COMPONENT_ADJUSTMENT_VALUE' => 'Adjustment Component',
            'RAW_MATERIAL_IN_VALUE' => 'Bahan Baku Masuk',
            'RAW_MATERIAL_USAGE_VALUE' => 'Bahan Baku Terpakai',
            'WAREHOUSE_ENDING_STOCK_VALUE' => 'Stok Akhir Gudang',
            'DIVISION_ENDING_STOCK_VALUE' => 'Stok Akhir Divisi',
            'ESTIMATED_PROFIT_VALUE' => 'Profit Estimasi',
            'ESTIMATED_PROFIT_PERCENT' => 'Margin Profit Estimasi %',
            'REAL_BALANCE_VALUE' => 'Saldo Riil',
            'PHYSICAL_BALANCE_VALUE' => 'Saldo Fisik',
        ];

        return $map[$metricCode] ?? $metricCode;
    }

    private function account_mutation_breakdown_map(string $dateStart, string $dateEnd, array $accountIds = []): array
    {
        if (!$this->db->table_exists('fin_account_mutation_log')) {
            return [];
        }

        $db = $this->db->select("
                account_id,
                COALESCE(SUM(CASE WHEN ref_module = 'POS' AND mutation_type = 'IN' THEN amount ELSE 0 END), 0) AS pos_in_total,
                COALESCE(SUM(CASE WHEN ref_module = 'POS' AND mutation_type = 'OUT' THEN amount ELSE 0 END), 0) AS pos_refund_out_total,
                COALESCE(SUM(CASE WHEN ref_module = 'PURCHASE' AND mutation_type = 'OUT' THEN amount ELSE 0 END), 0) AS purchase_out_total,
                COALESCE(SUM(CASE WHEN ref_module = 'PAYROLL' AND ref_table = 'pay_salary_disbursement' AND mutation_type = 'OUT' THEN amount ELSE 0 END), 0) AS payroll_out_total,
                COALESCE(SUM(CASE WHEN ref_module = 'PAYROLL' AND ref_table = 'pay_cash_advance' AND mutation_type = 'OUT' THEN amount ELSE 0 END), 0) AS cash_advance_out_total,
                COALESCE(SUM(CASE WHEN ref_module = 'FINANCE_PAYABLE' AND mutation_type = 'IN' THEN amount ELSE 0 END), 0) AS payable_in_total,
                COALESCE(SUM(CASE WHEN ref_module = 'FINANCE_PAYABLE' AND mutation_type = 'OUT' THEN amount ELSE 0 END), 0) AS payable_payment_out_total,
                COALESCE(SUM(CASE WHEN ref_module = 'FINANCE_RECEIVABLE' AND mutation_type = 'OUT' THEN amount ELSE 0 END), 0) AS receivable_out_total,
                COALESCE(SUM(CASE WHEN ref_module = 'FINANCE_RECEIVABLE' AND mutation_type = 'IN' THEN amount ELSE 0 END), 0) AS receivable_payment_in_total,
                COALESCE(SUM(CASE WHEN ref_module = 'FINANCE_TRANSFER' AND mutation_type = 'IN' THEN amount ELSE 0 END), 0) AS transfer_in_total,
                COALESCE(SUM(CASE WHEN ref_module = 'FINANCE_TRANSFER' AND mutation_type = 'OUT' THEN amount ELSE 0 END), 0) AS transfer_out_total,
                COALESCE(SUM(CASE WHEN mutation_type = 'IN' AND COALESCE(ref_module, '') NOT IN ('POS','FINANCE_PAYABLE','FINANCE_RECEIVABLE','FINANCE_TRANSFER') THEN amount ELSE 0 END), 0) AS manual_in_total,
                COALESCE(SUM(CASE WHEN mutation_type = 'OUT' AND COALESCE(ref_module, '') NOT IN ('POS','PURCHASE','PAYROLL','FINANCE_PAYABLE','FINANCE_RECEIVABLE','FINANCE_TRANSFER') THEN amount ELSE 0 END), 0) AS manual_out_total
            ", false)
            ->from('fin_account_mutation_log')
            ->where('mutation_date >=', $dateStart)
            ->where('mutation_date <=', $dateEnd);

        if (!empty($accountIds)) {
            $db->where_in('account_id', $accountIds);
        }

        $result = [];
        foreach ($db->group_by('account_id')->get()->result_array() as $row) {
            $result[(int)($row['account_id'] ?? 0)] = $row;
        }
        return $result;
    }

    private function loan_outstanding_as_of_map(string $kind, string $dateEnd, array $accountIds = []): array
    {
        $isReceivable = $kind === 'receivable';
        $headerTable = $isReceivable ? 'fin_receivable' : 'fin_payable';
        $paymentTable = $isReceivable ? 'fin_receivable_payment' : 'fin_payable_payment';
        $dateField = $isReceivable ? 'receivable_date' : 'payable_date';
        $loanFk = $isReceivable ? 'receivable_id' : 'payable_id';

        if (!$this->db->table_exists($headerTable) || !$this->db->table_exists($paymentTable)) {
            return [];
        }

        $paymentSql = $this->db->select($loanFk . ', COALESCE(SUM(amount), 0) AS paid_total', false)
            ->from($paymentTable)
            ->where('payment_date <=', $dateEnd)
            ->group_by($loanFk)
            ->get_compiled_select();

        $db = $this->db->select("
                h.company_account_id,
                COUNT(*) AS doc_count,
                COALESCE(SUM(h.amount), 0) AS nominal_total,
                COALESCE(SUM(GREATEST(h.amount - COALESCE(py.paid_total, 0), 0)), 0) AS outstanding_total,
                COALESCE(SUM(CASE WHEN h.account_impact_mode = 'KEEP_BALANCE' THEN GREATEST(h.amount - COALESCE(py.paid_total, 0), 0) ELSE 0 END), 0) AS keep_total,
                COALESCE(SUM(CASE WHEN h.account_impact_mode = 'APPLY_ACCOUNT' THEN GREATEST(h.amount - COALESCE(py.paid_total, 0), 0) ELSE 0 END), 0) AS apply_total
            ", false)
            ->from($headerTable . ' h')
            ->join('(' . $paymentSql . ') py', 'py.' . $loanFk . ' = h.id', 'left', false)
            ->where('h.company_account_id IS NOT NULL', null, false)
            ->where('h.' . $dateField . ' <=', $dateEnd)
            ->where('h.status <>', 'VOID');

        if (!empty($accountIds)) {
            $db->where_in('h.company_account_id', $accountIds);
        }

        $result = [];
        foreach ($db->group_by('h.company_account_id')->get()->result_array() as $row) {
            $result[(int)($row['company_account_id'] ?? 0)] = $row;
        }
        return $result;
    }

    private function cash_advance_outstanding_as_of_map(string $dateEnd, array $accountIds = []): array
    {
        if (
            !$this->db->table_exists('pay_cash_advance')
            || !$this->db->table_exists('pay_cash_advance_installment')
            || !$this->table_has_field('pay_cash_advance', 'company_account_id')
        ) {
            return [];
        }

        $cutPeriod = date('Y-m', strtotime($dateEnd));
        $paymentWhere = [];
        if ($this->table_has_field('pay_cash_advance_installment', 'payment_date')) {
            $paymentWhere[] = "(payment_date IS NOT NULL AND payment_date <= " . $this->db->escape($dateEnd) . ")";
        }
        if ($this->table_has_field('pay_cash_advance_installment', 'salary_cut_date')) {
            $paymentWhere[] = "(salary_cut_date IS NOT NULL AND salary_cut_date <= " . $this->db->escape($dateEnd) . ")";
        }
        if ($this->table_has_field('pay_cash_advance_installment', 'salary_cut_period')) {
            $paymentWhere[] = "(salary_cut_period IS NOT NULL AND salary_cut_period <= " . $this->db->escape($cutPeriod) . ")";
        }
        $paymentCondition = empty($paymentWhere) ? '1=1' : implode(' OR ', $paymentWhere);
        $paymentSql = "SELECT cash_advance_id, COALESCE(SUM(paid_amount), 0) AS paid_total
            FROM pay_cash_advance_installment
            WHERE COALESCE(paid_amount, 0) > 0 AND ({$paymentCondition})
            GROUP BY cash_advance_id";

        $effectiveDateExpr = $this->table_has_field('pay_cash_advance', 'approved_date')
            ? 'COALESCE(ca.approved_date, ca.request_date)'
            : 'ca.request_date';

        $db = $this->db->select("
                ca.company_account_id,
                COUNT(*) AS doc_count,
                COALESCE(SUM(ca.amount), 0) AS nominal_total,
                COALESCE(SUM(GREATEST(ca.amount - COALESCE(py.paid_total, 0), 0)), 0) AS outstanding_total
            ", false)
            ->from('pay_cash_advance ca')
            ->join('(' . $paymentSql . ') py', 'py.cash_advance_id = ca.id', 'left', false)
            ->where('ca.company_account_id IS NOT NULL', null, false)
            ->where("{$effectiveDateExpr} <=", $dateEnd, false)
            ->where_in('ca.status', ['APPROVED', 'SETTLED']);

        if (!empty($accountIds)) {
            $db->where_in('ca.company_account_id', $accountIds);
        }

        $result = [];
        foreach ($db->group_by('ca.company_account_id')->get()->result_array() as $row) {
            $result[(int)($row['company_account_id'] ?? 0)] = $row;
        }
        return $result;
    }

    private function payroll_pending_as_of_map(string $dateEnd, array $accountIds = []): array
    {
        if (
            !$this->db->table_exists('pay_salary_disbursement')
            || !$this->db->table_exists('pay_salary_disbursement_line')
        ) {
            return [];
        }

        $lineHasAccount = $this->table_has_field('pay_salary_disbursement_line', 'company_account_id');
        $accountExpr = $lineHasAccount ? 'COALESCE(l.company_account_id, h.company_account_id)' : 'h.company_account_id';

        $db = $this->db->select("
                {$accountExpr} AS company_account_id,
                COUNT(*) AS doc_count,
                COALESCE(SUM(CASE WHEN l.transfer_status IN ('PENDING','FAILED') THEN l.transfer_amount ELSE 0 END), 0) AS pending_total
            ", false)
            ->from('pay_salary_disbursement_line l')
            ->join('pay_salary_disbursement h', 'h.id = l.disbursement_id', 'inner')
            ->where('h.disbursement_date <=', $dateEnd)
            ->where('h.status <>', 'VOID')
            ->where_in('l.transfer_status', ['PENDING', 'FAILED'])
            ->where("{$accountExpr} IS NOT NULL", null, false);

        if (!empty($accountIds)) {
            $db->where_in($accountExpr, $accountIds, false);
        }

        $result = [];
        foreach ($db->group_by($accountExpr, false)->get()->result_array() as $row) {
            $result[(int)($row['company_account_id'] ?? 0)] = $row;
        }
        return $result;
    }

    private function resolve_target_line_actual(array $plan, array $line, array $closedPeriods, array $rawMetricRows): array
    {
        $metricCode = strtoupper(trim((string)($line['metric_code'] ?? '')));
        $scopeCandidates = $this->target_scope_candidates($plan);
        $isSnapshotMetric = $this->is_snapshot_metric_code($metricCode);

        if (!empty($closedPeriods) && $this->db->table_exists('fin_management_period_metric')) {
            foreach ($scopeCandidates as $scope) {
                if ($isSnapshotMetric) {
                    $row = $this->db->select('m.metric_amount, m.period_close_id, p.period_end')
                        ->from('fin_management_period_metric m')
                        ->join('fin_period_close p', 'p.id = m.period_close_id', 'inner')
                        ->where('m.metric_code', $metricCode)
                        ->where('m.scope_type', $scope['scope_type'])
                        ->where('m.scope_ref_id', $scope['scope_ref_id'])
                        ->where('p.status', 'CLOSED')
                        ->where('p.period_start >=', (string)($plan['date_start'] ?? ''))
                        ->where('p.period_end <=', (string)($plan['date_end'] ?? ''))
                        ->order_by('p.period_end', 'DESC')
                        ->limit(1)
                        ->get()->row_array();
                    if ($row) {
                        return [
                            'actual_value' => round((float)($row['metric_amount'] ?? 0), 2),
                            'period_close_id' => (int)($row['period_close_id'] ?? 0),
                            'notes' => 'Ambil snapshot closed period terakhir.',
                        ];
                    }
                } else {
                    $row = $this->db->select('COALESCE(SUM(m.metric_amount), 0) AS actual_total', false)
                        ->from('fin_management_period_metric m')
                        ->join('fin_period_close p', 'p.id = m.period_close_id', 'inner')
                        ->where('m.metric_code', $metricCode)
                        ->where('m.scope_type', $scope['scope_type'])
                        ->where('m.scope_ref_id', $scope['scope_ref_id'])
                        ->where('p.status', 'CLOSED')
                        ->where('p.period_start >=', (string)($plan['date_start'] ?? ''))
                        ->where('p.period_end <=', (string)($plan['date_end'] ?? ''))
                        ->get()->row_array();
                    if ($row && array_key_exists('actual_total', $row)) {
                        return [
                            'actual_value' => round((float)($row['actual_total'] ?? 0), 2),
                            'period_close_id' => (int)end($closedPeriods)['id'],
                            'notes' => 'Akumulasi dari period close yang sudah ditutup.',
                        ];
                    }
                }
            }
        }

        if (!empty($rawMetricRows)) {
            foreach ($scopeCandidates as $scope) {
                foreach ($rawMetricRows as $row) {
                    if (
                        strtoupper((string)($row['metric_code'] ?? '')) === $metricCode
                        && strtoupper((string)($row['scope_type'] ?? 'GLOBAL')) === $scope['scope_type']
                        && (int)($row['scope_ref_id'] ?? 0) === (int)$scope['scope_ref_id']
                    ) {
                        return [
                            'actual_value' => round((float)($row['metric_amount'] ?? 0), 2),
                            'period_close_id' => null,
                            'notes' => 'Fallback hitung langsung dari data live karena belum ada closed period.',
                        ];
                    }
                }
            }
        }

        return [
            'actual_value' => 0.0,
            'period_close_id' => null,
            'notes' => 'Belum ada data actual untuk metric ini di scope yang dipilih.',
        ];
    }

    private function target_scope_candidates(array $plan): array
    {
        $accountId = (int)($plan['company_account_id'] ?? 0);
        $divisionId = (int)($plan['division_id'] ?? 0);
        $candidates = [];
        if ($accountId > 0) {
            $candidates[] = ['scope_type' => 'ACCOUNT', 'scope_ref_id' => $accountId];
        }
        if ($divisionId > 0) {
            $candidates[] = ['scope_type' => 'DIVISION', 'scope_ref_id' => $divisionId];
        }
        $candidates[] = ['scope_type' => 'GLOBAL', 'scope_ref_id' => 0];
        return $candidates;
    }

    private function is_snapshot_metric_code(string $metricCode): bool
    {
        return in_array($metricCode, [
            'PAYABLE_OUTSTANDING',
            'RECEIVABLE_OUTSTANDING',
            'CASH_ADVANCE_OUTSTANDING',
            'REAL_BALANCE_VALUE',
            'PHYSICAL_BALANCE_VALUE',
            'WAREHOUSE_ENDING_STOCK_VALUE',
            'DIVISION_ENDING_STOCK_VALUE',
        ], true);
    }

    private function calculate_target_score(array $line, float $actualValue): array
    {
        $comparator = strtoupper(trim((string)($line['comparator'] ?? 'MIN')));
        $targetValue = round((float)($line['target_value'] ?? 0), 2);
        $minimumValue = $line['minimum_value'] !== null ? round((float)$line['minimum_value'], 2) : null;
        $maximumValue = $line['maximum_value'] !== null ? round((float)$line['maximum_value'], 2) : null;

        if ($targetValue <= 0 && $minimumValue === null && $maximumValue === null) {
            return ['score_percent' => 0, 'is_passed' => false, 'bonus_gate_passed' => false];
        }

        $score = 0.0;
        $passed = false;
        switch ($comparator) {
            case 'MAX':
                if ($targetValue > 0) {
                    $score = $actualValue <= 0 ? 100.0 : min(200.0, ($targetValue / max($actualValue, 0.0001)) * 100);
                    $passed = $actualValue <= $targetValue;
                }
                break;
            case 'RANGE':
                $min = $minimumValue ?? $targetValue;
                $max = $maximumValue ?? $targetValue;
                $passed = $actualValue >= $min && $actualValue <= $max;
                $score = $passed ? 100.0 : 0.0;
                break;
            case 'EQUAL':
                $passed = abs($actualValue - $targetValue) < 0.0001;
                $score = $passed ? 100.0 : 0.0;
                break;
            case 'MIN':
            default:
                if ($targetValue > 0) {
                    $score = min(200.0, ($actualValue / $targetValue) * 100);
                    $passed = $actualValue >= $targetValue;
                }
                break;
        }

        return [
            'score_percent' => round($score, 2),
            'is_passed' => $passed,
            'bonus_gate_passed' => $passed || (int)($line['is_required'] ?? 0) === 0,
        ];
    }

    private function apply_period_close_filters($db, array $filters = []): void
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? '')));
        $periodType = strtoupper(trim((string)($filters['period_type'] ?? '')));

        if ($q !== '') {
            $db->group_start()
                ->like('pc.period_code', $q)
                ->or_like('pc.notes', $q)
                ->group_end();
        }
        if (in_array($status, ['OPEN', 'CLOSED', 'REOPENED', 'VOID'], true)) {
            $db->where('pc.status', $status);
        }
        if (in_array($periodType, ['MONTHLY', 'YEARLY'], true)) {
            $db->where('pc.period_type', $periodType);
        }
    }

    private function apply_target_plan_filters($db, array $filters = []): void
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? '')));
        $scope = strtoupper(trim((string)($filters['target_scope'] ?? '')));
        $divisionId = (int)($filters['division_id'] ?? 0);

        if ($q !== '') {
            $db->group_start()
                ->like('tp.target_code', $q)
                ->or_like('tp.target_name', $q)
                ->or_like('tp.notes', $q)
                ->group_end();
        }
        if (in_array($status, ['DRAFT', 'ACTIVE', 'LOCKED', 'VOID'], true)) {
            $db->where('tp.status', $status);
        }
        if (in_array($scope, ['DAILY', 'MONTHLY', 'YEARLY'], true)) {
            $db->where('tp.target_scope', $scope);
        }
        if ($divisionId > 0) {
            $db->where('tp.division_id', $divisionId);
        }
    }
}
