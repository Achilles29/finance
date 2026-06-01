<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Finance_report_model extends CI_Model
{
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