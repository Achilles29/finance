<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Payroll_model extends CI_Model
{
    private $attDailyFieldCache = [];
    private $tableFieldCache = [];
    private $lockedPeriodDateCache = [];

    private function att_daily_has_field(string $field): bool
    {
        if (!array_key_exists($field, $this->attDailyFieldCache)) {
            $this->attDailyFieldCache[$field] = $this->db->field_exists($field, 'att_daily');
        }
        return (bool)$this->attDailyFieldCache[$field];
    }

    private function table_has_field(string $table, string $field): bool
    {
        $key = $table . '.' . $field;
        if (!array_key_exists($key, $this->tableFieldCache)) {
            $this->tableFieldCache[$key] = $this->db->field_exists($field, $table);
        }
        return (bool)$this->tableFieldCache[$key];
    }

    private function target_plan_name_expr(string $alias = 'tp'): string
    {
        if ($this->table_has_field('fin_target_plan', 'target_name')) {
            return $alias . '.target_name';
        }
        if ($this->table_has_field('fin_target_plan', 'plan_name')) {
            return $alias . '.plan_name';
        }
        if ($this->table_has_field('fin_target_plan', 'name')) {
            return $alias . '.name';
        }
        return "CONCAT('Target #', " . $alias . ".id)";
    }

    private function ph_shift_sql(string $alias = 's'): string
    {
        $alias = preg_replace('/[^A-Za-z0-9_]/', '', $alias);
        if ($alias === '') {
            $alias = 's';
        }

        $checks = [];
        if ($this->table_has_field('att_shift', 'is_ph_shift')) {
            $checks[] = "IFNULL({$alias}.is_ph_shift,0)=1";
        }
        $checks[] = "UPPER(TRIM(IFNULL({$alias}.shift_code,''))) IN ('PH','PHB')";
        $checks[] = "UPPER(TRIM(IFNULL({$alias}.shift_name,'')))='PUBLIC HOLIDAY'";

        return '(' . implode(' OR ', $checks) . ')';
    }

    private function order_payment_status_sql(string $alias = 'o'): string
    {
        $alias = preg_replace('/[^A-Za-z0-9_]/', '', $alias);
        if ($alias === '') {
            $alias = 'o';
        }

        if ($this->table_has_field('pos_order', 'payment_status')) {
            return "COALESCE(NULLIF({$alias}.payment_status,''), CASE"
                . " WHEN {$alias}.status = 'VOID' THEN 'VOID'"
                . " WHEN COALESCE({$alias}.balance_due,0) <= 0 AND COALESCE({$alias}.grand_total,0) > 0 THEN 'PAID'"
                . " WHEN COALESCE({$alias}.paid_amount,0) > 0 THEN 'PAID_PARTIAL'"
                . " WHEN {$alias}.status = 'DRAFT' THEN 'DRAFT'"
                . " ELSE 'PENDING' END)";
        }

        return "CASE"
            . " WHEN {$alias}.status = 'VOID' THEN 'VOID'"
            . " WHEN COALESCE({$alias}.balance_due,0) <= 0 AND COALESCE({$alias}.grand_total,0) > 0 THEN 'PAID'"
            . " WHEN COALESCE({$alias}.paid_amount,0) > 0 THEN 'PAID_PARTIAL'"
            . " WHEN {$alias}.status = 'DRAFT' THEN 'DRAFT'"
            . " ELSE 'PENDING' END";
    }

    private function normalize_bonus_service_score(float $avgMinutes, float $targetMinutes = 0.0, float $fallbackScore = 100.0): float
    {
        $avgMinutes = round(max(0, $avgMinutes), 2);
        $targetMinutes = round(max(0, $targetMinutes), 2);
        $fallbackScore = round(max(0, min(100, $fallbackScore)), 2);

        if ($avgMinutes <= 0) {
            return $fallbackScore > 0 ? $fallbackScore : 100.00;
        }

        if ($targetMinutes > 0) {
            if ($avgMinutes <= $targetMinutes) {
                return 100.00;
            }
            return round(max(0, min(100, ($targetMinutes / $avgMinutes) * 100)), 2);
        }

        return $fallbackScore > 0 ? $fallbackScore : 100.00;
    }

    private function get_locked_period_for_date(string $date): ?array
    {
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }
        if (array_key_exists($date, $this->lockedPeriodDateCache)) {
            return $this->lockedPeriodDateCache[$date];
        }
        if (!$this->db->table_exists('pay_payroll_period') || !$this->db->table_exists('pay_salary_disbursement')) {
            $this->lockedPeriodDateCache[$date] = null;
            return null;
        }

        $row = $this->db->select('p.id, p.period_code, p.period_start, p.period_end, d.id AS disbursement_id, d.disbursement_no, d.status AS disbursement_status')
            ->from('pay_payroll_period p')
            ->join('pay_salary_disbursement d', 'd.payroll_period_id = p.id AND d.status <> "VOID"', 'inner')
            ->where('p.period_start <=', $date)
            ->where('p.period_end >=', $date)
            ->order_by('p.period_start', 'DESC')
            ->order_by('d.id', 'DESC')
            ->limit(1)
            ->get()->row_array();

        $this->lockedPeriodDateCache[$date] = $row ?: null;
        return $this->lockedPeriodDateCache[$date];
    }

    private function immutable_period_guard_message(string $date): string
    {
        $locked = $this->get_locked_period_for_date($date);
        if (!$locked) {
            return '';
        }
        $periodCode = (string)($locked['period_code'] ?? '#');
        $disbursementNo = (string)($locked['disbursement_no'] ?? '#');
        $disbursementStatus = strtoupper((string)($locked['disbursement_status'] ?? '-'));
        return 'Periode payroll ' . $periodCode . ' sudah terkunci oleh batch gaji ' . $disbursementNo . ' [' . $disbursementStatus . ']. Perubahan data tanggal ini diblokir.';
    }

    private function get_manual_adjustment_totals_by_date(int $employeeId, string $date): array
    {
        if ($employeeId <= 0 || $date === '' || !$this->db->table_exists('pay_manual_adjustment')) {
            return ['addition' => 0.0, 'deduction' => 0.0, 'net' => 0.0];
        }
        $rows = $this->db->select('adjustment_kind, COALESCE(SUM(amount),0) AS total_amount', false)
            ->from('pay_manual_adjustment')
            ->where('employee_id', $employeeId)
            ->where('adjustment_date', $date)
            ->where('status', 'APPROVED')
            ->group_by('adjustment_kind')
            ->get()->result_array();
        $addition = 0.0;
        $deduction = 0.0;
        foreach ($rows as $row) {
            $kind = strtoupper((string)($row['adjustment_kind'] ?? ''));
            $amount = (float)($row['total_amount'] ?? 0);
            if ($kind === 'DEDUCTION') {
                $deduction += $amount;
            } else {
                $addition += $amount;
            }
        }
        return [
            'addition' => round($addition, 2),
            'deduction' => round($deduction, 2),
            'net' => round($addition - $deduction, 2),
        ];
    }

    private function recompute_daily_manual_adjustment(int $employeeId, string $date): void
    {
        if ($employeeId <= 0 || $date === '' || !$this->db->table_exists('att_daily')) {
            return;
        }
        $daily = $this->db->select('id, net_amount, daily_salary_amount')
            ->from('att_daily')
            ->where('employee_id', $employeeId)
            ->where('attendance_date', $date)
            ->limit(1)
            ->get()->row_array();
        if (!$daily) {
            return;
        }

        $adj = $this->get_manual_adjustment_totals_by_date($employeeId, $date);
        $payload = ['updated_at' => date('Y-m-d H:i:s')];
        if ($this->att_daily_has_field('manual_addition_amount')) {
            $payload['manual_addition_amount'] = round((float)$adj['addition'], 2);
        }
        if ($this->att_daily_has_field('manual_deduction_amount')) {
            $payload['manual_deduction_amount'] = round((float)$adj['deduction'], 2);
        }
        if ($this->att_daily_has_field('manual_adjustment_net_amount')) {
            $payload['manual_adjustment_net_amount'] = round((float)$adj['net'], 2);
        }
        if ($this->att_daily_has_field('daily_salary_amount')) {
            $netBase = $this->att_daily_has_field('net_amount')
                ? (float)($daily['net_amount'] ?? 0)
                : (float)($daily['daily_salary_amount'] ?? 0);
            $payload['daily_salary_amount'] = round($netBase + (float)$adj['net'], 2);
        }
        $this->db->where('id', (int)$daily['id'])->update('att_daily', $payload);
    }

    public function get_division_options(): array
    {
        return $this->db->select('id AS value, division_name AS label')
            ->from('org_division')
            ->where('is_active', 1)
            ->order_by('division_name', 'ASC')
            ->get()->result_array();
    }

    public function get_position_options(): array
    {
        if (!$this->db->table_exists('org_position')) {
            return [];
        }

        $db = $this->db->select('id AS value, position_name AS label')
            ->from('org_position');
        if ($this->table_has_field('org_position', 'is_active')) {
            $db->where('is_active', 1);
        }

        return $db->order_by('position_name', 'ASC')->get()->result_array();
    }

    public function get_employee_options(?int $divisionId = null): array
    {
        $this->db->select("e.id AS value, CONCAT(e.employee_code, ' - ', e.employee_name) AS label", false)
            ->from('org_employee e')
            ->where('e.is_active', 1);
        if (!empty($divisionId)) {
            $this->db->where('e.division_id', (int)$divisionId);
        }
        return $this->db->order_by('e.employee_name', 'ASC')->get()->result_array();
    }

    public function get_shift_options(): array
    {
        if (!$this->db->table_exists('att_shift')) {
            return [];
        }

        $db = $this->db->select("id AS value, CONCAT(COALESCE(shift_code, ''), ' - ', COALESCE(shift_name, '')) AS label", false)
            ->from('att_shift');
        if ($this->table_has_field('att_shift', 'is_active')) {
            $db->where('is_active', 1);
        }
        return $db->order_by('shift_name', 'ASC')->get()->result_array();
    }

    public function count_manual_adjustments(array $f): int
    {
        $this->build_manual_adjustment_query($f, false);
        return (int)$this->db->count_all_results();
    }

    public function list_manual_adjustments(array $f, int $limit, int $offset): array
    {
        $this->build_manual_adjustment_query($f, true);
        return $this->db
            ->order_by('ma.adjustment_date', 'DESC')
            ->order_by('e.employee_name', 'ASC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_manual_adjustment_query(array $f, bool $withSelect): void
    {
        if ($withSelect) {
            $this->db->select('ma.*, e.employee_code, e.employee_name, d.division_name, p.position_name, au.username AS approved_by_username');
        }
        $this->db->from('pay_manual_adjustment ma')
            ->join('org_employee e', 'e.id = ma.employee_id', 'inner')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->join('auth_user au', 'au.id = ma.approved_by', 'left');

        if (!empty($f['date_start'])) {
            $this->db->where('ma.adjustment_date >=', (string)$f['date_start']);
        }
        if (!empty($f['date_end'])) {
            $this->db->where('ma.adjustment_date <=', (string)$f['date_end']);
        }
        if (!empty($f['division_id'])) {
            $this->db->where('e.division_id', (int)$f['division_id']);
        }
        if (!empty($f['employee_id'])) {
            $this->db->where('ma.employee_id', (int)$f['employee_id']);
        }
        if (!empty($f['status'])) {
            $this->db->where('ma.status', strtoupper((string)$f['status']));
        }
        if (!empty($f['adjustment_kind'])) {
            $this->db->where('ma.adjustment_kind', strtoupper((string)$f['adjustment_kind']));
        }
        if (!empty($f['q'])) {
            $q = trim((string)$f['q']);
            $this->db->group_start()
                ->like('e.employee_code', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('ma.adjustment_name', $q)
                ->or_like('ma.notes', $q)
                ->group_end();
        }
    }

    public function get_manual_adjustment_by_id(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return $this->db->from('pay_manual_adjustment')->where('id', $id)->limit(1)->get()->row_array() ?: null;
    }

    public function save_manual_adjustment(array $payload, int $actorUserId = 0): array
    {
        $id = (int)($payload['id'] ?? 0);
        $employeeId = (int)($payload['employee_id'] ?? 0);
        $adjustmentDate = trim((string)($payload['adjustment_date'] ?? ''));
        $kind = strtoupper(trim((string)($payload['adjustment_kind'] ?? 'ADDITION')));
        $name = trim((string)($payload['adjustment_name'] ?? ''));
        $amount = (float)($payload['amount'] ?? 0);
        $status = strtoupper(trim((string)($payload['status'] ?? 'APPROVED')));
        $notes = trim((string)($payload['notes'] ?? ''));

        if ($employeeId <= 0 || $adjustmentDate === '' || $name === '' || $amount <= 0) {
            return ['ok' => false, 'message' => 'Pegawai, tanggal, nama komponen, dan nominal wajib diisi.'];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $adjustmentDate)) {
            return ['ok' => false, 'message' => 'Tanggal penyesuaian tidak valid.'];
        }
        if (!in_array($kind, ['ADDITION', 'DEDUCTION'], true)) {
            $kind = 'ADDITION';
        }
        if (!in_array($status, ['PENDING', 'APPROVED', 'REJECTED'], true)) {
            $status = 'APPROVED';
        }

        $employee = $this->db->select('id')->from('org_employee')->where('id', $employeeId)->where('is_active', 1)->limit(1)->get()->row_array();
        if (!$employee) {
            return ['ok' => false, 'message' => 'Pegawai tidak ditemukan atau nonaktif.'];
        }

        $lockMessage = $this->immutable_period_guard_message($adjustmentDate);
        if ($lockMessage !== '') {
            return ['ok' => false, 'message' => $lockMessage];
        }

        $dbPayload = [
            'employee_id' => $employeeId,
            'adjustment_date' => $adjustmentDate,
            'adjustment_kind' => $kind,
            'adjustment_name' => $name,
            'amount' => round($amount, 2),
            'status' => $status,
            'notes' => $notes !== '' ? $notes : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($status === 'APPROVED') {
            $dbPayload['approved_by'] = $actorUserId > 0 ? $actorUserId : null;
            $dbPayload['approved_at'] = date('Y-m-d H:i:s');
        } else {
            $dbPayload['approved_by'] = null;
            $dbPayload['approved_at'] = null;
        }

        if ($id > 0) {
            $exists = $this->get_manual_adjustment_by_id($id);
            if (!$exists) {
                return ['ok' => false, 'message' => 'Data penyesuaian tidak ditemukan.'];
            }
            $oldDate = (string)($exists['adjustment_date'] ?? '');
            $oldLockMessage = $this->immutable_period_guard_message($oldDate);
            if ($oldLockMessage !== '') {
                return ['ok' => false, 'message' => $oldLockMessage];
            }
            $oldEmployeeId = (int)($exists['employee_id'] ?? 0);
            $this->db->where('id', $id)->update('pay_manual_adjustment', $dbPayload);
            $this->recompute_daily_manual_adjustment($oldEmployeeId, $oldDate);
            if ($oldEmployeeId !== $employeeId || $oldDate !== $adjustmentDate) {
                $this->recompute_daily_manual_adjustment($employeeId, $adjustmentDate);
            }
            return ['ok' => true, 'message' => 'Data penyesuaian berhasil diperbarui.'];
        }

        $dbPayload['created_by'] = $actorUserId > 0 ? $actorUserId : null;
        $dbPayload['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('pay_manual_adjustment', $dbPayload);
        $this->recompute_daily_manual_adjustment($employeeId, $adjustmentDate);
        return ['ok' => true, 'message' => 'Data penyesuaian berhasil ditambahkan.'];
    }

    public function delete_manual_adjustment(int $id): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'ID penyesuaian tidak valid.'];
        }
        $exists = $this->get_manual_adjustment_by_id($id);
        if (!$exists) {
            return ['ok' => false, 'message' => 'Data penyesuaian tidak ditemukan.'];
        }
        $lockMessage = $this->immutable_period_guard_message((string)($exists['adjustment_date'] ?? ''));
        if ($lockMessage !== '') {
            return ['ok' => false, 'message' => $lockMessage];
        }
        $this->db->where('id', $id)->delete('pay_manual_adjustment');
        $this->recompute_daily_manual_adjustment((int)$exists['employee_id'], (string)$exists['adjustment_date']);
        return ['ok' => true, 'message' => 'Data penyesuaian berhasil dihapus.'];
    }

    public function get_company_account_options(): array
    {
        if (!$this->db->table_exists('fin_company_account')) {
            return [];
        }
        return $this->db->select("id AS value, CONCAT(account_code, ' - ', account_name) AS label, account_type, bank_id, bank_name, account_no", false)
            ->from('fin_company_account')
            ->where('is_active', 1)
            ->order_by('is_default', 'DESC')
            ->order_by('account_name', 'ASC')
            ->get()->result_array();
    }

    private function normalize_bank_key(string $raw): string
    {
        $v = strtoupper(trim($raw));
        if ($v === '') {
            return '';
        }
        $v = preg_replace('/[^A-Z0-9]/', '', $v);
        return (string)$v;
    }

    private function default_company_account_id(array $accounts): int
    {
        if (empty($accounts)) {
            return 0;
        }
        foreach ($accounts as $a) {
            if ((int)($a['value'] ?? 0) > 0) {
                return (int)$a['value'];
            }
        }
        return 0;
    }

    private function resolve_source_account_id_for_employee(array $candidate, array $accounts, int $fallbackId): int
    {
        if (empty($accounts)) {
            return 0;
        }

        $employeeBankId = (int)($candidate['bank_id'] ?? 0);
        $employeeBankNameKey = $this->normalize_bank_key((string)($candidate['bank_name'] ?? ''));

        if ($employeeBankId > 0) {
            foreach ($accounts as $a) {
                if ((int)($a['value'] ?? 0) > 0 && (int)($a['bank_id'] ?? 0) === $employeeBankId) {
                    return (int)$a['value'];
                }
            }
        }

        if ($employeeBankNameKey !== '') {
            foreach ($accounts as $a) {
                if ((int)($a['value'] ?? 0) <= 0) {
                    continue;
                }
                $accBankKey = $this->normalize_bank_key((string)($a['bank_name'] ?? ''));
                if ($accBankKey !== '' && $accBankKey === $employeeBankNameKey) {
                    return (int)$a['value'];
                }
            }
            foreach ($accounts as $a) {
                if ((int)($a['value'] ?? 0) <= 0) {
                    continue;
                }
                $accBankKey = $this->normalize_bank_key((string)($a['bank_name'] ?? ''));
                if ($accBankKey !== '' && (strpos($employeeBankNameKey, $accBankKey) !== false || strpos($accBankKey, $employeeBankNameKey) !== false)) {
                    return (int)$a['value'];
                }
            }
        }

        return $fallbackId > 0 ? $fallbackId : 0;
    }

    private function fetch_salary_disbursement_candidates(int $periodId): array
    {
        if ($periodId <= 0) {
            return [];
        }

        return $this->db->select('
                r.id AS payroll_result_id,
                r.employee_id,
                r.net_pay,
                r.gross_pay,
                r.total_deduction,
                r.employee_code_snapshot,
                r.employee_name_snapshot,
                ' . ($this->table_has_field('pay_payroll_result', 'basic_total') ? '
                r.basic_total,
                r.allowance_total,
                r.meal_total,
                r.overtime_total,
                r.manual_addition_total,
                r.late_deduction_total,
                r.alpha_deduction_total,
                r.manual_deduction_total,
                r.cash_advance_cut_total,
                r.net_pay_raw,
                r.rounding_adjustment,
                ' : '') . '
                e.bank_id,
                e.bank_name,
                e.bank_account_no,
                e.bank_account_name
            ', false)
            ->from('pay_payroll_result r')
            ->join('org_employee e', 'e.id = r.employee_id', 'left')
            ->where('r.payroll_period_id', $periodId)
            ->where('COALESCE(r.net_pay,0) >', 0)
            ->where_in('r.status', ['DRAFT', 'FINALIZED'])
            ->where('NOT EXISTS (
                SELECT 1
                FROM pay_salary_disbursement_line dup
                INNER JOIN pay_salary_disbursement d
                    ON d.id = dup.disbursement_id
                   AND d.status <> "VOID"
                WHERE dup.payroll_result_id = r.id
            )', null, false)
            ->group_by('r.id')
            ->order_by('r.employee_id', 'ASC')
            ->get()->result_array();
    }

    public function preview_salary_disbursement_candidates(int $periodId): array
    {
        if ($periodId <= 0) {
            return [];
        }
        $accounts = $this->get_company_account_options();
        $accountMap = [];
        foreach ($accounts as $a) {
            $accountMap[(int)($a['value'] ?? 0)] = $a;
        }
        $fallbackId = $this->default_company_account_id($accounts);
        $rows = $this->fetch_salary_disbursement_candidates($periodId);
        $uniqueRows = [];
        $seenResult = [];
        $seenEmployee = [];
        foreach ($rows as $row) {
            $resultId = (int)($row['payroll_result_id'] ?? 0);
            $employeeId = (int)($row['employee_id'] ?? 0);
            if ($resultId <= 0 || $employeeId <= 0) {
                continue;
            }
            if (isset($seenResult[$resultId]) || isset($seenEmployee[$employeeId])) {
                // Hard guard: preview hanya tampil 1 kandidat per payroll_result dan per pegawai.
                continue;
            }
            $seenResult[$resultId] = true;
            $seenEmployee[$employeeId] = true;
            $uniqueRows[] = $row;
        }
        $rows = $uniqueRows;
        foreach ($rows as &$row) {
            $sourceId = $this->resolve_source_account_id_for_employee($row, $accounts, $fallbackId);
            $source = $accountMap[$sourceId] ?? null;
            $row['source_account_id'] = $sourceId;
            $row['source_account_label'] = $source ? (string)($source['label'] ?? '') : '';
        }
        unset($row);

        return $rows;
    }

    private function next_doc_no(string $table, string $column, string $prefix): string
    {
        $ym = date('Ym');
        $head = $prefix . '-' . $ym . '-';
        $last = $this->db->select($column)
            ->from($table)
            ->like($column, $head, 'after')
            ->order_by($column, 'DESC')
            ->limit(1)
            ->get()->row_array();

        $next = 1;
        if (!empty($last[$column])) {
            $parts = explode('-', (string)$last[$column]);
            $run = (int)end($parts);
            $next = $run + 1;
        }
        return $head . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    }

    private function generate_account_mutation_no(string $mutationDate): string
    {
        $datePart = date('Ymd', strtotime($mutationDate));
        $prefix = 'MUT' . $datePart;

        $row = $this->db->select('mutation_no')
            ->from('fin_account_mutation_log')
            ->like('mutation_no', $prefix, 'after')
            ->order_by('mutation_no', 'DESC')
            ->limit(1)
            ->get()->row_array();

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

    private function debit_account_and_log_mutation(
        int $accountId,
        float $amount,
        string $mutationDate,
        string $refTable,
        int $refId,
        string $refNo,
        string $notes,
        int $actorUserId = 0
    ): array {
        if ($amount <= 0) {
            return ['ok' => true, 'message' => 'Nominal debit 0, tidak ada mutasi.'];
        }
        if ($accountId <= 0) {
            return ['ok' => false, 'message' => 'Rekening sumber belum dipilih.'];
        }
        if (!$this->db->table_exists('fin_company_account') || !$this->db->table_exists('fin_account_mutation_log')) {
            return ['ok' => false, 'message' => 'Tabel rekening/mutasi belum tersedia.'];
        }

        $account = $this->db->query('SELECT * FROM fin_company_account WHERE id = ? AND is_active = 1 LIMIT 1 FOR UPDATE', [$accountId])->row_array();
        if (!$account) {
            return ['ok' => false, 'message' => 'Rekening sumber tidak ditemukan atau nonaktif.'];
        }

        $balanceBefore = round((float)($account['current_balance'] ?? 0), 2);
        $amount = round($amount, 2);
        if ($balanceBefore < $amount) {
            return ['ok' => false, 'message' => 'Saldo rekening tidak cukup.'];
        }
        $balanceAfter = round($balanceBefore - $amount, 2);

        $this->db->where('id', $accountId)->update('fin_company_account', [
            'current_balance' => $balanceAfter,
        ]);

        $this->db->insert('fin_account_mutation_log', [
            'mutation_no' => $this->generate_account_mutation_no($mutationDate),
            'mutation_date' => $mutationDate,
            'account_id' => $accountId,
            'mutation_type' => 'OUT',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'ref_module' => 'PAYROLL',
            'ref_table' => $refTable,
            'ref_id' => $refId > 0 ? $refId : null,
            'ref_no' => $refNo !== '' ? $refNo : null,
            'notes' => $notes !== '' ? $notes : null,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true];
    }

    private function credit_account_and_log_mutation(
        int $accountId,
        float $amount,
        string $mutationDate,
        string $refTable,
        int $refId,
        string $refNo,
        string $notes,
        int $actorUserId = 0
    ): array {
        if ($amount <= 0) {
            return ['ok' => true, 'message' => 'Nominal kredit 0, tidak ada mutasi.'];
        }
        if ($accountId <= 0) {
            return ['ok' => false, 'message' => 'Rekening tujuan belum valid.'];
        }
        if (!$this->db->table_exists('fin_company_account') || !$this->db->table_exists('fin_account_mutation_log')) {
            return ['ok' => false, 'message' => 'Tabel rekening/mutasi belum tersedia.'];
        }

        $account = $this->db->query('SELECT * FROM fin_company_account WHERE id = ? AND is_active = 1 LIMIT 1 FOR UPDATE', [$accountId])->row_array();
        if (!$account) {
            return ['ok' => false, 'message' => 'Rekening tujuan tidak ditemukan atau nonaktif.'];
        }

        $balanceBefore = round((float)($account['current_balance'] ?? 0), 2);
        $amount = round($amount, 2);
        $balanceAfter = round($balanceBefore + $amount, 2);

        $this->db->where('id', $accountId)->update('fin_company_account', [
            'current_balance' => $balanceAfter,
        ]);

        $this->db->insert('fin_account_mutation_log', [
            'mutation_no' => $this->generate_account_mutation_no($mutationDate),
            'mutation_date' => $mutationDate,
            'account_id' => $accountId,
            'mutation_type' => 'IN',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'ref_module' => 'PAYROLL',
            'ref_table' => $refTable,
            'ref_id' => $refId > 0 ? $refId : null,
            'ref_no' => $refNo !== '' ? $refNo : null,
            'notes' => $notes !== '' ? $notes : null,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true];
    }

    public function count_meal_disbursements(array $filters): int
    {
        $this->build_meal_disbursement_query($filters, false);
        return (int)$this->db->count_all_results();
    }

    public function list_meal_disbursements(array $filters, int $limit, int $offset): array
    {
        $this->build_meal_disbursement_query($filters, true);
        return $this->db->order_by('md.disbursement_date', 'DESC')
            ->order_by('md.id', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_meal_disbursement_query(array $filters, bool $withSelect): void
    {
        if ($withSelect) {
            $this->db->select("
                md.*,
                au.username AS created_by_username,
                COALESCE((SELECT COUNT(*) FROM pay_meal_disbursement_line l WHERE l.disbursement_id = md.id),0) AS line_count,
                COALESCE((SELECT SUM(CASE WHEN l.transfer_status='PAID' THEN l.meal_amount ELSE 0 END) FROM pay_meal_disbursement_line l WHERE l.disbursement_id = md.id),0) AS paid_amount
            ", false);
        }

        $this->db->from('pay_meal_disbursement md')
            ->join('auth_user au', 'au.id = md.created_by', 'left');

        if (!empty($filters['status'])) {
            $this->db->where('md.status', strtoupper((string)$filters['status']));
        }
        if (!empty($filters['date_start'])) {
            $this->db->where('md.disbursement_date >=', (string)$filters['date_start']);
        }
        if (!empty($filters['date_end'])) {
            $this->db->where('md.disbursement_date <=', (string)$filters['date_end']);
        }
        if (!empty($filters['q'])) {
            $q = trim((string)$filters['q']);
            $this->db->group_start()
                ->like('md.disbursement_no', $q)
                ->or_like('md.notes', $q)
                ->group_end();
        }
    }

    public function get_meal_disbursement_by_id(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return $this->db->from('pay_meal_disbursement')->where('id', $id)->limit(1)->get()->row_array() ?: null;
    }

    public function list_meal_disbursement_lines(int $disbursementId): array
    {
        if ($disbursementId <= 0) {
            return [];
        }
        return $this->db->select('l.*, e.employee_code, e.employee_name, d.division_name')
            ->from('pay_meal_disbursement_line l')
            ->join('org_employee e', 'e.id = l.employee_id', 'inner')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->where('l.disbursement_id', $disbursementId)
            ->order_by('l.attendance_date', 'ASC')
            ->order_by('e.employee_name', 'ASC')
            ->get()->result_array();
    }

    public function list_meal_disbursement_employee_summary(int $disbursementId): array
    {
        if ($disbursementId <= 0) {
            return [];
        }
        return $this->db->select('
                l.employee_id,
                e.employee_code,
                e.employee_name,
                d.division_name,
                COUNT(*) AS day_count,
                SUM(COALESCE(l.meal_amount,0)) AS meal_total,
                SUM(CASE WHEN l.transfer_status = "PAID" THEN COALESCE(l.meal_amount,0) ELSE 0 END) AS paid_total
            ', false)
            ->from('pay_meal_disbursement_line l')
            ->join('org_employee e', 'e.id = l.employee_id', 'inner')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->where('l.disbursement_id', $disbursementId)
            ->group_by('l.employee_id')
            ->order_by('e.employee_name', 'ASC')
            ->get()->result_array();
    }

    public function list_meal_disbursement_employee_daily(int $disbursementId, int $employeeId): array
    {
        if ($disbursementId <= 0 || $employeeId <= 0) {
            return [];
        }
        return $this->db->select('l.*')
            ->from('pay_meal_disbursement_line l')
            ->where('l.disbursement_id', $disbursementId)
            ->where('l.employee_id', $employeeId)
            ->order_by('l.attendance_date', 'ASC')
            ->get()->result_array();
    }

    public function generate_meal_disbursement(array $payload, int $actorUserId): array
    {
        $periodStart = trim((string)($payload['period_start'] ?? ''));
        $periodEnd = trim((string)($payload['period_end'] ?? ''));
        $disbursementDate = trim((string)($payload['disbursement_date'] ?? date('Y-m-d')));
        $companyAccountId = (int)($payload['company_account_id'] ?? 0);
        $notes = trim((string)($payload['notes'] ?? ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodStart) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodEnd) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $disbursementDate)) {
            return ['ok' => false, 'message' => 'Format tanggal tidak valid.'];
        }
        if ($periodEnd < $periodStart) {
            return ['ok' => false, 'message' => 'Periode akhir tidak boleh kurang dari periode awal.'];
        }

        $candidates = $this->db->select('
                ad.id AS att_daily_id,
                ad.employee_id,
                ad.attendance_date,
                ad.meal_amount,
                dup.id AS existing_line_id,
                dup.disbursement_id AS existing_disbursement_id,
                dup_void_h.id AS existing_void_header_id
            ', false)
            ->from('att_daily ad')
            ->join('org_employee e', 'e.id = ad.employee_id', 'inner')
            ->join('pay_meal_disbursement_line dup', 'dup.employee_id = ad.employee_id AND dup.attendance_date = ad.attendance_date', 'left')
            ->join('pay_meal_disbursement dup_h', 'dup_h.id = dup.disbursement_id AND dup_h.status <> "VOID"', 'left')
            ->join('pay_meal_disbursement dup_void_h', 'dup_void_h.id = dup.disbursement_id AND dup_void_h.status = "VOID"', 'left')
            ->where('e.is_active', 1)
            ->where('ad.attendance_date >=', $periodStart)
            ->where('ad.attendance_date <=', $periodEnd)
            ->where('COALESCE(ad.meal_amount,0) >', 0)
            ->where('ad.checkin_at IS NOT NULL', null, false)
            ->where('dup_h.id IS NULL', null, false)
            ->group_by('ad.id')
            ->order_by('ad.attendance_date', 'ASC')
            ->get()->result_array();

        if (empty($candidates)) {
            return ['ok' => false, 'message' => 'Tidak ada kandidat uang makan baru pada periode tersebut.'];
        }

        $disbursementNo = $this->next_doc_no('pay_meal_disbursement', 'disbursement_no', 'MEAL');
        $totalAmount = 0.0;
        foreach ($candidates as $c) {
            $totalAmount += (float)($c['meal_amount'] ?? 0);
        }

        $this->db->trans_start();
        $this->db->insert('pay_meal_disbursement', [
            'disbursement_no' => $disbursementNo,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'disbursement_date' => $disbursementDate,
            'company_account_id' => $companyAccountId > 0 ? $companyAccountId : null,
            'status' => 'POSTED',
            'total_amount' => round($totalAmount, 2),
            'notes' => $notes !== '' ? $notes : null,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $disbursementId = (int)$this->db->insert_id();

        foreach ($candidates as $c) {
            $existingLineId = (int)($c['existing_line_id'] ?? 0);
            $hasVoidHeader = (int)($c['existing_void_header_id'] ?? 0) > 0;
            $linePayload = [
                'disbursement_id' => $disbursementId,
                'employee_id' => (int)$c['employee_id'],
                'attendance_date' => (string)$c['attendance_date'],
                'att_daily_id' => (int)$c['att_daily_id'] > 0 ? (int)$c['att_daily_id'] : null,
                'meal_amount' => round((float)($c['meal_amount'] ?? 0), 2),
                'transfer_status' => 'PENDING',
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($this->table_has_field('pay_meal_disbursement_line', 'transfer_ref_no')) {
                $linePayload['transfer_ref_no'] = null;
            }
            if ($this->table_has_field('pay_meal_disbursement_line', 'paid_at')) {
                $linePayload['paid_at'] = null;
            }

            if ($existingLineId > 0 && $hasVoidHeader) {
                $this->db->where('id', $existingLineId)->update('pay_meal_disbursement_line', $linePayload);
                continue;
            }

            $linePayload['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert('pay_meal_disbursement_line', $linePayload);
        }
        $this->db->trans_complete();

        if (!$this->db->trans_status()) {
            return ['ok' => false, 'message' => 'Gagal membuat batch pencairan uang makan.'];
        }

        return [
            'ok' => true,
            'message' => 'Batch uang makan berhasil dibuat.',
            'disbursement_id' => $disbursementId,
        ];
    }

    public function post_meal_disbursement_paid(int $disbursementId, string $transferRefNo = '', int $actorUserId = 0): array
    {
        $header = $this->get_meal_disbursement_by_id($disbursementId);
        if (!$header) {
            return ['ok' => false, 'message' => 'Batch uang makan tidak ditemukan.'];
        }
        if (strtoupper((string)$header['status']) === 'VOID') {
            return ['ok' => false, 'message' => 'Batch VOID tidak bisa diproses bayar.'];
        }

        $now = date('Y-m-d H:i:s');
        $this->db->trans_start();

        $header = $this->db->query('SELECT * FROM pay_meal_disbursement WHERE id = ? LIMIT 1 FOR UPDATE', [$disbursementId])->row_array();
        if (!$header) {
            $this->db->trans_complete();
            return ['ok' => false, 'message' => 'Batch uang makan tidak ditemukan.'];
        }
        if (strtoupper((string)$header['status']) === 'VOID') {
            $this->db->trans_complete();
            return ['ok' => false, 'message' => 'Batch VOID tidak bisa diproses bayar.'];
        }

        $pendingRows = $this->db->select('id, meal_amount')
            ->from('pay_meal_disbursement_line')
            ->where('disbursement_id', $disbursementId)
            ->where_in('transfer_status', ['PENDING', 'FAILED'])
            ->get()->result_array();
        $payNowAmount = 0.0;
        foreach ($pendingRows as $row) {
            $payNowAmount += (float)($row['meal_amount'] ?? 0);
        }
        $payNowAmount = round($payNowAmount, 2);

        if ($payNowAmount > 0) {
            $accountId = (int)($header['company_account_id'] ?? 0);
            if ($accountId > 0) {
                $debit = $this->debit_account_and_log_mutation(
                    $accountId,
                    $payNowAmount,
                    (string)($header['disbursement_date'] ?? date('Y-m-d')),
                    'pay_meal_disbursement',
                    $disbursementId,
                    (string)($header['disbursement_no'] ?? ''),
                    'Pencairan batch uang makan ' . (string)($header['disbursement_no'] ?? ''),
                    $actorUserId
                );
                if (empty($debit['ok'])) {
                    $this->db->trans_complete();
                    return $debit;
                }
            }

            $this->db->where('disbursement_id', $disbursementId)
                ->where_in('transfer_status', ['PENDING', 'FAILED'])
                ->update('pay_meal_disbursement_line', [
                    'transfer_status' => 'PAID',
                    'transfer_ref_no' => $transferRefNo !== '' ? $transferRefNo : null,
                    'paid_at' => $now,
                    'updated_at' => $now,
                ]);
        }

        $remaining = $this->db->select('COUNT(*) AS c', false)
            ->from('pay_meal_disbursement_line')
            ->where('disbursement_id', $disbursementId)
            ->where('transfer_status <>', 'PAID')
            ->get()->row_array();

        $headerStatus = ((int)($remaining['c'] ?? 0) === 0) ? 'PAID' : 'POSTED';
        $this->db->where('id', $disbursementId)->update('pay_meal_disbursement', [
            'status' => $headerStatus,
            'updated_at' => $now,
        ]);

        $this->db->trans_complete();
        if (!$this->db->trans_status()) {
            return ['ok' => false, 'message' => 'Gagal memproses pembayaran batch uang makan.'];
        }
        if ($payNowAmount <= 0) {
            return ['ok' => true, 'message' => 'Tidak ada baris PENDING untuk diposting.'];
        }
        return ['ok' => true, 'message' => $headerStatus === 'PAID' ? 'Batch uang makan ditandai lunas.' : 'Sebagian baris ditandai paid.'];
    }

    public function void_meal_disbursement(int $disbursementId, string $notes = '', int $actorUserId = 0): array
    {
        $header = $this->get_meal_disbursement_by_id($disbursementId);
        if (!$header) {
            return ['ok' => false, 'message' => 'Batch uang makan tidak ditemukan.'];
        }

        $now = date('Y-m-d H:i:s');
        $this->db->trans_start();
        $header = $this->db->query('SELECT * FROM pay_meal_disbursement WHERE id = ? LIMIT 1 FOR UPDATE', [$disbursementId])->row_array();
        if (!$header) {
            $this->db->trans_complete();
            return ['ok' => false, 'message' => 'Batch uang makan tidak ditemukan.'];
        }

        $currentStatus = strtoupper((string)($header['status'] ?? 'DRAFT'));
        if ($currentStatus === 'VOID') {
            $this->db->trans_complete();
            return ['ok' => false, 'message' => 'Batch sudah berstatus VOID.'];
        }

        if ($currentStatus === 'PAID') {
            if ($this->db->table_exists('fin_account_mutation_log') && $this->db->table_exists('fin_company_account')) {
                $outs = $this->db->select('account_id, SUM(COALESCE(amount,0)) AS total_out', false)
                    ->from('fin_account_mutation_log')
                    ->where('ref_module', 'PAYROLL')
                    ->where('ref_table', 'pay_meal_disbursement')
                    ->where('ref_id', $disbursementId)
                    ->where('mutation_type', 'OUT')
                    ->group_by('account_id')
                    ->get()->result_array();

                foreach ($outs as $out) {
                    $accountId = (int)($out['account_id'] ?? 0);
                    $amount = round((float)($out['total_out'] ?? 0), 2);
                    if ($accountId <= 0 || $amount <= 0) {
                        continue;
                    }
                    $credit = $this->credit_account_and_log_mutation(
                        $accountId,
                        $amount,
                        (string)($header['disbursement_date'] ?? date('Y-m-d')),
                        'pay_meal_disbursement',
                        $disbursementId,
                        (string)($header['disbursement_no'] ?? ''),
                        'VOID batch uang makan ' . (string)($header['disbursement_no'] ?? ''),
                        $actorUserId
                    );
                    if (empty($credit['ok'])) {
                        $this->db->trans_complete();
                        return $credit;
                    }
                }
            }
        }

        $voidNotes = trim($notes);
        $mergedNotes = trim((string)($header['notes'] ?? ''));
        $suffix = $voidNotes !== '' ? ('VOID: ' . $voidNotes) : 'VOID';
        $mergedNotes = $mergedNotes !== '' ? ($mergedNotes . ' | ' . $suffix) : $suffix;

        $this->db->where('id', $disbursementId)->update('pay_meal_disbursement', [
            'status' => 'VOID',
            'notes' => $mergedNotes,
            'updated_at' => $now,
        ]);
        $this->db->where('disbursement_id', $disbursementId)->update('pay_meal_disbursement_line', [
            'transfer_status' => 'VOID',
            'updated_at' => $now,
        ]);
        $this->db->trans_complete();
        if (!$this->db->trans_status()) {
            return ['ok' => false, 'message' => 'Gagal melakukan VOID batch uang makan.'];
        }
        return ['ok' => true, 'message' => 'Batch uang makan berhasil di-VOID.'];
    }

    public function count_payroll_periods(array $filters): int
    {
        $this->build_payroll_period_query($filters, false);
        return (int)$this->db->count_all_results();
    }

    public function list_payroll_periods(array $filters, int $limit, int $offset): array
    {
        $this->build_payroll_period_query($filters, true);
        return $this->db->order_by('pp.period_start', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_payroll_period_query(array $filters, bool $withSelect): void
    {
        if ($withSelect) {
            $this->db->select("
                pp.*,
                COALESCE((SELECT COUNT(*) FROM pay_payroll_result r WHERE r.payroll_period_id=pp.id),0) AS employee_count,
                COALESCE((SELECT SUM(r.net_pay) FROM pay_payroll_result r WHERE r.payroll_period_id=pp.id),0) AS net_pay_total
            ", false);
        }
        $this->db->from('pay_payroll_period pp');
        if (!empty($filters['status'])) {
            $this->db->where('pp.status', strtoupper((string)$filters['status']));
        }
        if (!empty($filters['q'])) {
            $this->db->group_start()
                ->like('pp.period_code', trim((string)$filters['q']))
                ->or_like('pp.notes', trim((string)$filters['q']))
                ->group_end();
        }
    }

    private function ensure_payroll_period(string $periodCode, string $periodStart, string $periodEnd): int
    {
        $row = $this->db->from('pay_payroll_period')
            ->where('period_code', $periodCode)
            ->limit(1)
            ->get()->row_array();
        if ($row) {
            $this->db->where('id', (int)$row['id'])->update('pay_payroll_period', [
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return (int)$row['id'];
        }

        $this->db->insert('pay_payroll_period', [
            'period_code' => $periodCode,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'status' => 'DRAFT',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return (int)$this->db->insert_id();
    }

    public function generate_payroll_period_results(array $payload, int $actorUserId): array
    {
        $periodCode = trim((string)($payload['period_code'] ?? ''));
        $periodStart = trim((string)($payload['period_start'] ?? ''));
        $periodEnd = trim((string)($payload['period_end'] ?? ''));
        $roundingMode = strtoupper(trim((string)($payload['rounding_mode'] ?? 'NONE')));
        $notes = trim((string)($payload['notes'] ?? ''));
        if (!in_array($roundingMode, ['NONE', 'UP_1000'], true)) {
            $roundingMode = 'NONE';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodStart) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodEnd)) {
            return ['ok' => false, 'message' => 'Tanggal periode tidak valid.'];
        }
        if ($periodEnd < $periodStart) {
            return ['ok' => false, 'message' => 'Periode akhir tidak boleh lebih kecil dari periode awal.'];
        }
        if ($periodCode === '') {
            $periodCode = date('Y-m', strtotime($periodStart));
        }

        $periodId = $this->ensure_payroll_period($periodCode, $periodStart, $periodEnd);
        if ($periodId <= 0) {
            return ['ok' => false, 'message' => 'Gagal menyiapkan payroll period.'];
        }

        $periodRow = $this->db->select('id, period_code, status')
            ->from('pay_payroll_period')
            ->where('id', $periodId)
            ->limit(1)
            ->get()->row_array();
        if ($periodRow && in_array(strtoupper((string)($periodRow['status'] ?? 'DRAFT')), ['PAID', 'CLOSED'], true)) {
            return ['ok' => false, 'message' => 'Payroll period ' . (string)($periodRow['period_code'] ?? '#') . ' sudah status PAID/CLOSED dan tidak bisa diregenerate.'];
        }
        $activeDisbursement = $this->db->select('COUNT(*) AS c', false)
            ->from('pay_salary_disbursement')
            ->where('payroll_period_id', $periodId)
            ->where('status <>', 'VOID')
            ->get()->row_array();
        if ((int)($activeDisbursement['c'] ?? 0) > 0) {
            return ['ok' => false, 'message' => 'Payroll period sudah punya batch gaji aktif. VOID/hapus batch dulu sebelum regenerate.'];
        }

        $rows = $this->db->select("
                ad.employee_id,
                e.employee_code,
                e.employee_name,
                COUNT(*) AS work_days,
                SUM(CASE WHEN ad.attendance_status IN ('PRESENT','LATE','HOLIDAY') THEN 1 ELSE 0 END) AS present_days,
                SUM(CASE WHEN ad.attendance_status='ALPHA' THEN 1 ELSE 0 END) AS alpha_days,
                SUM(COALESCE(ad.late_minutes,0)) AS late_minutes,
                SUM(COALESCE(ad.overtime_minutes,0))/60 AS overtime_hours,
                SUM(COALESCE(ad.basic_amount,0)) AS basic_total,
                SUM(COALESCE(ad.allowance_amount,0)) AS allowance_total,
                SUM(COALESCE(ad.meal_amount,0)) AS meal_total,
                SUM(COALESCE(ad.overtime_pay,0)) AS overtime_total,
                SUM(COALESCE(ad.manual_addition_amount,0)) AS manual_addition_total,
                SUM(COALESCE(ad.late_deduction_amount,0)) AS late_deduction_total,
                SUM(COALESCE(ad.alpha_deduction_amount,0)) AS alpha_deduction_total,
                SUM(COALESCE(ad.manual_deduction_amount,0)) AS manual_deduction_total,
                SUM(COALESCE(ad.gross_amount,0)) AS gross_pay_raw,
                SUM(COALESCE(ad.daily_salary_amount,0)) AS net_pay_raw
            ", false)
            ->from('att_daily ad')
            ->join('org_employee e', 'e.id = ad.employee_id', 'inner')
            ->where('ad.attendance_date >=', $periodStart)
            ->where('ad.attendance_date <=', $periodEnd)
            ->group_start()
            ->where('ad.checkout_at IS NOT NULL', null, false)
            ->or_where('ad.attendance_status', 'HOLIDAY')
            ->group_end()
            ->where('e.is_active', 1)
            ->group_by('ad.employee_id')
            ->get()->result_array();

        if (empty($rows)) {
            return ['ok' => false, 'message' => 'Belum ada data att_daily yang closed pada periode ini.'];
        }

        $cashCutMap = [];
        if ($periodCode !== '' && $this->db->table_exists('pay_cash_advance_installment') && $this->table_has_field('pay_cash_advance_installment', 'payment_method') && $this->table_has_field('pay_cash_advance_installment', 'salary_cut_period')) {
            $cashRows = $this->db->select('ca.employee_id, SUM(COALESCE(i.paid_amount,0)) AS total_cut', false)
                ->from('pay_cash_advance_installment i')
                ->join('pay_cash_advance ca', 'ca.id = i.cash_advance_id', 'inner')
                ->where('i.payment_method', 'SALARY_CUT')
                ->where('i.salary_cut_period', $periodCode)
                ->where('i.status', 'PAID')
                ->group_by('ca.employee_id')
                ->get()->result_array();
            foreach ($cashRows as $row) {
                $cashCutMap[(int)($row['employee_id'] ?? 0)] = round((float)($row['total_cut'] ?? 0), 2);
            }
        }

        $this->db->trans_start();
        foreach ($rows as $r) {
            $eid = (int)($r['employee_id'] ?? 0);
            $basicTotal = round((float)($r['basic_total'] ?? 0), 2);
            $allowanceTotal = round((float)($r['allowance_total'] ?? 0), 2);
            $mealTotal = round((float)($r['meal_total'] ?? 0), 2);
            $overtimeTotal = round((float)($r['overtime_total'] ?? 0), 2);
            $manualAdditionTotal = round((float)($r['manual_addition_total'] ?? 0), 2);
            $lateDeductionTotal = round((float)($r['late_deduction_total'] ?? 0), 2);
            $alphaDeductionTotal = round((float)($r['alpha_deduction_total'] ?? 0), 2);
            $manualDeductionTotal = round((float)($r['manual_deduction_total'] ?? 0), 2);
            $cashAdvanceCutTotal = round((float)($cashCutMap[$eid] ?? 0), 2);

            $grossPay = round($basicTotal + $allowanceTotal + $mealTotal + $overtimeTotal + $manualAdditionTotal, 2);
            $netPayRaw = round((float)($r['net_pay_raw'] ?? 0), 2);
            $netPay = $netPayRaw;
            if ($roundingMode === 'UP_1000' && $netPay > 0) {
                $netPay = (float)(ceil($netPay / 1000) * 1000);
            }
            $roundingAdjustment = round($netPay - $netPayRaw, 2);
            $deduction = max(0, round($grossPay - $netPay, 2));

            $dbPayload = [
                'payroll_period_id' => $periodId,
                'employee_id' => $eid,
                'employee_code_snapshot' => (string)($r['employee_code'] ?? ''),
                'employee_name_snapshot' => (string)($r['employee_name'] ?? ''),
                'work_days' => round((float)($r['work_days'] ?? 0), 2),
                'present_days' => round((float)($r['present_days'] ?? 0), 2),
                'alpha_days' => round((float)($r['alpha_days'] ?? 0), 2),
                'late_minutes' => (int)($r['late_minutes'] ?? 0),
                'overtime_hours' => round((float)($r['overtime_hours'] ?? 0), 2),
                'gross_pay' => $grossPay,
                'total_deduction' => $deduction,
                'net_pay' => $netPay,
                'status' => 'FINALIZED',
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($this->table_has_field('pay_payroll_result', 'basic_total')) {
                $dbPayload['basic_total'] = $basicTotal;
                $dbPayload['allowance_total'] = $allowanceTotal;
                $dbPayload['meal_total'] = $mealTotal;
                $dbPayload['overtime_total'] = $overtimeTotal;
                $dbPayload['manual_addition_total'] = $manualAdditionTotal;
                $dbPayload['late_deduction_total'] = $lateDeductionTotal;
                $dbPayload['alpha_deduction_total'] = $alphaDeductionTotal;
                $dbPayload['manual_deduction_total'] = $manualDeductionTotal;
                $dbPayload['cash_advance_cut_total'] = $cashAdvanceCutTotal;
                $dbPayload['net_pay_raw'] = $netPayRaw;
                $dbPayload['rounding_adjustment'] = $roundingAdjustment;
            }

            $existing = $this->db->select('id')
                ->from('pay_payroll_result')
                ->where('payroll_period_id', $periodId)
                ->where('employee_id', $eid)
                ->limit(1)
                ->get()->row_array();

            $resultId = 0;
            if ($existing) {
                $resultId = (int)$existing['id'];
                $this->db->where('id', $resultId)->update('pay_payroll_result', $dbPayload);
            } else {
                $dbPayload['created_at'] = date('Y-m-d H:i:s');
                $this->db->insert('pay_payroll_result', $dbPayload);
                $resultId = (int)$this->db->insert_id();
            }

            if ($resultId > 0 && $this->db->table_exists('pay_payroll_result_line')) {
                $manualCashDed = min($manualDeductionTotal, $cashAdvanceCutTotal);
                $manualOtherDed = max(0, round($manualDeductionTotal - $manualCashDed, 2));
                $this->refresh_payroll_result_lines($resultId, [
                    'basic_total' => $basicTotal,
                    'allowance_total' => $allowanceTotal,
                    'meal_total' => $mealTotal,
                    'overtime_total' => $overtimeTotal,
                    'manual_addition_total' => $manualAdditionTotal,
                    'late_deduction_total' => $lateDeductionTotal,
                    'alpha_deduction_total' => $alphaDeductionTotal,
                    'manual_deduction_other_total' => $manualOtherDed,
                    'cash_advance_cut_total' => $manualCashDed,
                    'rounding_adjustment' => $roundingAdjustment,
                ]);
            }
        }

        $periodPayload = [
            'status' => 'FINALIZED',
            'finalized_at' => date('Y-m-d H:i:s'),
            'finalized_by' => $actorUserId > 0 ? $actorUserId : null,
            'notes' => trim(($notes !== '' ? $notes . ' | ' : '') . 'Rounding=' . $roundingMode),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($this->table_has_field('pay_payroll_period', 'rounding_mode')) {
            $periodPayload['rounding_mode'] = $roundingMode;
        }
        $this->db->where('id', $periodId)->update('pay_payroll_period', $periodPayload);
        $this->db->trans_complete();

        if (!$this->db->trans_status()) {
            return ['ok' => false, 'message' => 'Gagal menghitung payroll period.'];
        }

        return ['ok' => true, 'message' => 'Payroll period berhasil dihitung/finalisasi.', 'payroll_period_id' => $periodId];
    }

    private function refresh_payroll_result_lines(int $payrollResultId, array $s): void
    {
        if ($payrollResultId <= 0) {
            return;
        }
        $this->db->where('payroll_result_id', $payrollResultId)->delete('pay_payroll_result_line');

        $lines = [
            ['code' => 'BASIC', 'name' => 'Gaji Pokok', 'type' => 'EARNING', 'amount' => (float)($s['basic_total'] ?? 0)],
            ['code' => 'ALLOWANCE', 'name' => 'Tunjangan', 'type' => 'EARNING', 'amount' => (float)($s['allowance_total'] ?? 0)],
            ['code' => 'MEAL', 'name' => 'Uang Makan', 'type' => 'EARNING', 'amount' => (float)($s['meal_total'] ?? 0)],
            ['code' => 'OVERTIME', 'name' => 'Lembur', 'type' => 'EARNING', 'amount' => (float)($s['overtime_total'] ?? 0)],
            ['code' => 'MANUAL_ADD', 'name' => 'Penyesuaian (+)', 'type' => 'EARNING', 'amount' => (float)($s['manual_addition_total'] ?? 0)],
            ['code' => 'LATE_DED', 'name' => 'Potongan Telat', 'type' => 'DEDUCTION', 'amount' => (float)($s['late_deduction_total'] ?? 0)],
            ['code' => 'ALPHA_DED', 'name' => 'Potongan Alpha', 'type' => 'DEDUCTION', 'amount' => (float)($s['alpha_deduction_total'] ?? 0)],
            ['code' => 'MANUAL_DED', 'name' => 'Penyesuaian (-) Lain', 'type' => 'DEDUCTION', 'amount' => (float)($s['manual_deduction_other_total'] ?? 0)],
            ['code' => 'CASH_ADV_DED', 'name' => 'Potongan Kasbon', 'type' => 'DEDUCTION', 'amount' => (float)($s['cash_advance_cut_total'] ?? 0)],
            ['code' => 'ROUNDING', 'name' => 'Pembulatan', 'type' => 'EARNING', 'amount' => (float)($s['rounding_adjustment'] ?? 0)],
        ];
        $now = date('Y-m-d H:i:s');
        foreach ($lines as $line) {
            $amount = round((float)($line['amount'] ?? 0), 2);
            if (abs($amount) < 0.00001) {
                continue;
            }
            $this->db->insert('pay_payroll_result_line', [
                'payroll_result_id' => $payrollResultId,
                'component_id' => null,
                'line_code' => (string)$line['code'],
                'line_name' => (string)$line['name'],
                'line_type' => (string)$line['type'],
                'qty' => 1,
                'rate' => $amount,
                'amount' => $amount,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function reset_payroll_period(int $periodId, string $notes = ''): array
    {
        if ($periodId <= 0) {
            return ['ok' => false, 'message' => 'Payroll period tidak valid.'];
        }
        $period = $this->db->from('pay_payroll_period')->where('id', $periodId)->limit(1)->get()->row_array();
        if (!$period) {
            return ['ok' => false, 'message' => 'Payroll period tidak ditemukan.'];
        }

        $activeDisbursement = $this->db->select('COUNT(*) AS c', false)
            ->from('pay_salary_disbursement')
            ->where('payroll_period_id', $periodId)
            ->where('status <>', 'VOID')
            ->get()->row_array();
        if ((int)($activeDisbursement['c'] ?? 0) > 0) {
            return ['ok' => false, 'message' => 'Period sudah dipakai batch gaji aktif. VOID/hapus batch dulu.'];
        }

        $noteText = trim((string)($period['notes'] ?? ''));
        if ($notes !== '') {
            $noteText = trim($noteText . ' | RESET: ' . $notes);
        }
        $this->db->trans_start();
        if ($this->db->table_exists('pay_payroll_result_line')) {
            $resultIds = $this->db->select('id')->from('pay_payroll_result')->where('payroll_period_id', $periodId)->get()->result_array();
            $ids = array_values(array_filter(array_map(static function ($r) {
                return (int)($r['id'] ?? 0);
            }, $resultIds)));
            if (!empty($ids)) {
                $this->db->where_in('payroll_result_id', $ids)->delete('pay_payroll_result_line');
            }
        }
        $this->db->where('payroll_period_id', $periodId)->update('pay_payroll_result', [
            'status' => 'DRAFT',
            'paid_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->where('id', $periodId)->update('pay_payroll_period', [
            'status' => 'DRAFT',
            'finalized_at' => null,
            'finalized_by' => null,
            'notes' => $noteText !== '' ? $noteText : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->trans_complete();
        if (!$this->db->trans_status()) {
            return ['ok' => false, 'message' => 'Gagal reset payroll period.'];
        }
        return ['ok' => true, 'message' => 'Payroll period berhasil di-reset ke DRAFT.'];
    }

    public function delete_payroll_period(int $periodId): array
    {
        if ($periodId <= 0) {
            return ['ok' => false, 'message' => 'Payroll period tidak valid.'];
        }
        $period = $this->db->from('pay_payroll_period')->where('id', $periodId)->limit(1)->get()->row_array();
        if (!$period) {
            return ['ok' => false, 'message' => 'Payroll period tidak ditemukan.'];
        }
        $disbursementCount = $this->db->select('COUNT(*) AS c', false)
            ->from('pay_salary_disbursement')
            ->where('payroll_period_id', $periodId)
            ->get()->row_array();
        if ((int)($disbursementCount['c'] ?? 0) > 0) {
            return ['ok' => false, 'message' => 'Payroll period sudah punya batch gaji, tidak bisa dihapus.'];
        }

        $this->db->trans_start();
        if ($this->db->table_exists('pay_payroll_result_line')) {
            $resultIds = $this->db->select('id')->from('pay_payroll_result')->where('payroll_period_id', $periodId)->get()->result_array();
            $ids = array_values(array_filter(array_map(static function ($r) {
                return (int)($r['id'] ?? 0);
            }, $resultIds)));
            if (!empty($ids)) {
                $this->db->where_in('payroll_result_id', $ids)->delete('pay_payroll_result_line');
            }
        }
        $this->db->where('payroll_period_id', $periodId)->delete('pay_payroll_result');
        $this->db->where('id', $periodId)->delete('pay_payroll_period');
        $this->db->trans_complete();
        if (!$this->db->trans_status()) {
            return ['ok' => false, 'message' => 'Gagal menghapus payroll period.'];
        }
        return ['ok' => true, 'message' => 'Payroll period berhasil dihapus.'];
    }

    public function list_payroll_results_by_period(int $periodId): array
    {
        if ($periodId <= 0) {
            return [];
        }
        return $this->db->select("
                r.*,
                e.employee_code,
                e.employee_name,
                d.division_name,
                (
                  SELECT COALESCE(SUM(ad.daily_salary_amount),0)
                  FROM att_daily ad
                  WHERE ad.employee_id = r.employee_id
                    AND ad.attendance_date >= pp.period_start
                    AND ad.attendance_date <= pp.period_end
                    AND (
                        ad.checkout_at IS NOT NULL
                        OR ad.attendance_status = 'HOLIDAY'
                    )
                ) AS attendance_net_total
            ", false)
            ->from('pay_payroll_result r')
            ->join('pay_payroll_period pp', 'pp.id = r.payroll_period_id', 'inner')
            ->join('org_employee e', 'e.id = r.employee_id', 'left')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->where('r.payroll_period_id', $periodId)
            ->order_by('e.employee_name', 'ASC')
            ->get()->result_array();
    }

    public function list_payroll_result_breakdown_by_period(int $periodId): array
    {
        if ($periodId <= 0) {
            return [];
        }
        if ($this->table_has_field('pay_payroll_result', 'basic_total')) {
            return $this->db->select('
                    r.id AS payroll_result_id,
                    r.employee_id,
                    r.employee_code_snapshot,
                    r.employee_name_snapshot,
                    r.status,
                    r.work_days,
                    r.present_days,
                    r.alpha_days,
                    r.late_minutes,
                    r.overtime_hours,
                    r.basic_total,
                    r.allowance_total,
                    r.meal_total,
                    r.overtime_total,
                    r.manual_addition_total,
                    r.late_deduction_total,
                    r.alpha_deduction_total,
                    r.manual_deduction_total,
                    r.cash_advance_cut_total AS cash_advance_cut,
                    r.gross_pay,
                    r.total_deduction,
                    r.net_pay_raw,
                    r.rounding_adjustment,
                    r.net_pay
                ', false)
                ->from('pay_payroll_result r')
                ->where('r.payroll_period_id', $periodId)
                ->order_by('r.employee_name_snapshot', 'ASC')
                ->get()->result_array();
        }

        // Fallback legacy schema: tetap bisa tampil walau masih baca att_daily.
        $period = $this->db->from('pay_payroll_period')->where('id', $periodId)->limit(1)->get()->row_array();
        if (!$period) {
            return [];
        }
        $periodStart = (string)($period['period_start'] ?? '');
        $periodEnd = (string)($period['period_end'] ?? '');
        return $this->db->select('
                r.id AS payroll_result_id,
                r.employee_id,
                r.employee_code_snapshot,
                r.employee_name_snapshot,
                r.status,
                r.work_days,
                r.present_days,
                r.alpha_days,
                r.late_minutes,
                r.overtime_hours,
                r.gross_pay,
                r.total_deduction,
                r.net_pay,
                SUM(COALESCE(ad.basic_amount,0)) AS basic_total,
                SUM(COALESCE(ad.allowance_amount,0)) AS allowance_total,
                SUM(COALESCE(ad.meal_amount,0)) AS meal_total,
                SUM(COALESCE(ad.overtime_pay,0)) AS overtime_total,
                SUM(COALESCE(ad.late_deduction_amount,0)) AS late_deduction_total,
                SUM(COALESCE(ad.alpha_deduction_amount,0)) AS alpha_deduction_total,
                SUM(COALESCE(ad.manual_addition_amount,0)) AS manual_addition_total,
                SUM(COALESCE(ad.manual_deduction_amount,0)) AS manual_deduction_total,
                0 AS cash_advance_cut,
                r.net_pay AS net_pay_raw,
                0 AS rounding_adjustment
            ', false)
            ->from('pay_payroll_result r')
            ->join('att_daily ad', 'ad.employee_id = r.employee_id AND ad.attendance_date >= "' . $this->db->escape_str($periodStart) . '" AND ad.attendance_date <= "' . $this->db->escape_str($periodEnd) . '" AND (ad.checkout_at IS NOT NULL OR ad.attendance_status = "HOLIDAY")', 'left', false)
            ->where('r.payroll_period_id', $periodId)
            ->group_by('r.id')
            ->order_by('r.employee_name_snapshot', 'ASC')
            ->get()->result_array();
    }

    public function get_payroll_period_options(): array
    {
        return $this->db->select("id AS value, CONCAT(period_code, ' (', period_start, ' s/d ', period_end, ')') AS label", false)
            ->from('pay_payroll_period')
            ->order_by('period_start', 'DESC')
            ->get()->result_array();
    }

    public function count_salary_disbursements(array $filters): int
    {
        $this->build_salary_disbursement_query($filters, false);
        return (int)$this->db->count_all_results();
    }

    public function list_salary_disbursements(array $filters, int $limit, int $offset): array
    {
        $this->build_salary_disbursement_query($filters, true);
        return $this->db->order_by('sd.disbursement_date', 'DESC')
            ->order_by('sd.id', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_salary_disbursement_query(array $filters, bool $withSelect): void
    {
        if ($withSelect) {
            $this->db->select("
                sd.*,
                pp.period_code,
                pp.period_start,
                pp.period_end,
                COALESCE((SELECT COUNT(*) FROM pay_salary_disbursement_line l WHERE l.disbursement_id=sd.id),0) AS line_count,
                COALESCE((SELECT SUM(CASE WHEN l.transfer_status='PAID' THEN l.transfer_amount ELSE 0 END) FROM pay_salary_disbursement_line l WHERE l.disbursement_id=sd.id),0) AS paid_amount
            ", false);
        }
        $this->db->from('pay_salary_disbursement sd')
            ->join('pay_payroll_period pp', 'pp.id = sd.payroll_period_id', 'left');
        if (!empty($filters['status'])) {
            $this->db->where('sd.status', strtoupper((string)$filters['status']));
        }
        if (!empty($filters['payroll_period_id'])) {
            $this->db->where('sd.payroll_period_id', (int)$filters['payroll_period_id']);
        }
    }

    public function get_salary_disbursement_by_id(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return $this->db->from('pay_salary_disbursement')->where('id', $id)->limit(1)->get()->row_array() ?: null;
    }

    public function list_salary_disbursement_lines(int $disbursementId): array
    {
        if ($disbursementId <= 0) {
            return [];
        }
        $bankNameExpr = $this->table_has_field('pay_salary_disbursement_line', 'employee_bank_name')
            ? 'COALESCE(l.employee_bank_name, e.bank_name) AS bank_name'
            : 'e.bank_name AS bank_name';
        $bankNoExpr = $this->table_has_field('pay_salary_disbursement_line', 'employee_bank_account_no')
            ? 'COALESCE(l.employee_bank_account_no, e.bank_account_no) AS bank_account_no'
            : 'e.bank_account_no AS bank_account_no';
        $bankHolderExpr = $this->table_has_field('pay_salary_disbursement_line', 'employee_bank_account_name')
            ? 'COALESCE(l.employee_bank_account_name, e.bank_account_name) AS bank_account_name'
            : 'e.bank_account_name AS bank_account_name';

        return $this->db->select('l.*, r.employee_name_snapshot, r.employee_code_snapshot, ' . $bankNameExpr . ', ' . $bankNoExpr . ', ' . $bankHolderExpr, false)
            ->from('pay_salary_disbursement_line l')
            ->join('pay_payroll_result r', 'r.id = l.payroll_result_id', 'left')
            ->join('org_employee e', 'e.id = l.employee_id', 'left')
            ->where('l.disbursement_id', $disbursementId)
            ->order_by('r.employee_name_snapshot', 'ASC')
            ->get()->result_array();
    }

    public function list_salary_disbursement_line_breakdown(int $disbursementId): array
    {
        if ($disbursementId <= 0) {
            return [];
        }
        $header = $this->db->select('sd.*, pp.period_code, pp.period_start, pp.period_end')
            ->from('pay_salary_disbursement sd')
            ->join('pay_payroll_period pp', 'pp.id = sd.payroll_period_id', 'left')
            ->where('sd.id', $disbursementId)
            ->limit(1)
            ->get()->row_array();
        if (!$header) {
            return [];
        }

        $bankNameExpr = $this->table_has_field('pay_salary_disbursement_line', 'employee_bank_name')
            ? 'COALESCE(l.employee_bank_name, e.bank_name) AS bank_name'
            : 'e.bank_name AS bank_name';
        $bankNoExpr = $this->table_has_field('pay_salary_disbursement_line', 'employee_bank_account_no')
            ? 'COALESCE(l.employee_bank_account_no, e.bank_account_no) AS bank_account_no'
            : 'e.bank_account_no AS bank_account_no';
        $bankHolderExpr = $this->table_has_field('pay_salary_disbursement_line', 'employee_bank_account_name')
            ? 'COALESCE(l.employee_bank_account_name, e.bank_account_name) AS bank_account_name'
            : 'e.bank_account_name AS bank_account_name';
        $resultSnapshotExpr = $this->table_has_field('pay_payroll_result', 'basic_total')
            ? '
                r.basic_total,
                r.allowance_total,
                r.meal_total,
                r.overtime_total,
                r.manual_addition_total,
                r.late_deduction_total,
                r.alpha_deduction_total,
                r.manual_deduction_total,
                r.cash_advance_cut_total,
                r.net_pay_raw,
                r.rounding_adjustment,
            '
            : '';
        $lineSnapshotExpr = $this->table_has_field('pay_salary_disbursement_line', 'basic_total_snapshot')
            ? '
                l.basic_total_snapshot,
                l.allowance_total_snapshot,
                l.meal_total_snapshot,
                l.overtime_total_snapshot,
                l.manual_addition_total_snapshot,
                l.late_deduction_total_snapshot,
                l.alpha_deduction_total_snapshot,
                l.manual_deduction_total_snapshot,
                l.cash_advance_cut_total_snapshot,
                l.gross_pay_snapshot,
                l.total_deduction_snapshot,
                l.net_pay_raw_snapshot,
                l.rounding_adjustment_snapshot,
                l.net_pay_snapshot,
            '
            : '';

        $baseRows = $this->db->select('
                l.id AS line_id,
                l.disbursement_id,
                l.payroll_result_id,
                l.employee_id,
                l.transfer_amount,
                l.transfer_status,
                l.transfer_ref_no,
                l.paid_at,
                r.employee_code_snapshot,
                r.employee_name_snapshot,
                r.work_days,
                r.present_days,
                r.alpha_days,
                r.late_minutes,
                r.overtime_hours,
                r.gross_pay,
                r.total_deduction,
                r.net_pay,
                ' . $resultSnapshotExpr . '
                ' . $lineSnapshotExpr . '
                ' . $bankNameExpr . ',
                ' . $bankNoExpr . ',
                ' . $bankHolderExpr . '
            ', false)
            ->from('pay_salary_disbursement_line l')
            ->join('pay_payroll_result r', 'r.id = l.payroll_result_id', 'inner')
            ->join('org_employee e', 'e.id = l.employee_id', 'left')
            ->where('l.disbursement_id', $disbursementId)
            ->order_by('r.employee_name_snapshot', 'ASC')
            ->get()->result_array();

        if (empty($baseRows)) {
            return [];
        }

        foreach ($baseRows as &$row) {
            if ($this->table_has_field('pay_salary_disbursement_line', 'basic_total_snapshot')) {
                $row['basic_total'] = (float)($row['basic_total_snapshot'] ?? 0);
                $row['allowance_total'] = (float)($row['allowance_total_snapshot'] ?? 0);
                $row['meal_total'] = (float)($row['meal_total_snapshot'] ?? 0);
                $row['overtime_total'] = (float)($row['overtime_total_snapshot'] ?? 0);
                $row['manual_addition_total'] = (float)($row['manual_addition_total_snapshot'] ?? 0);
                $row['late_deduction_total'] = (float)($row['late_deduction_total_snapshot'] ?? 0);
                $row['alpha_deduction_total'] = (float)($row['alpha_deduction_total_snapshot'] ?? 0);
                $row['manual_deduction_total'] = (float)($row['manual_deduction_total_snapshot'] ?? 0);
                $row['cash_advance_cut'] = (float)($row['cash_advance_cut_total_snapshot'] ?? 0);
                $row['gross_pay'] = (float)($row['gross_pay_snapshot'] ?? ($row['gross_pay'] ?? 0));
                $row['total_deduction'] = (float)($row['total_deduction_snapshot'] ?? ($row['total_deduction'] ?? 0));
                $row['net_pay_raw'] = (float)($row['net_pay_raw_snapshot'] ?? ($row['net_pay'] ?? 0));
                $row['rounding_adjustment'] = (float)($row['rounding_adjustment_snapshot'] ?? 0);
                $row['net_pay'] = (float)($row['net_pay_snapshot'] ?? ($row['net_pay'] ?? 0));
            } elseif ($this->table_has_field('pay_payroll_result', 'basic_total')) {
                $row['basic_total'] = (float)($row['basic_total'] ?? 0);
                $row['allowance_total'] = (float)($row['allowance_total'] ?? 0);
                $row['meal_total'] = (float)($row['meal_total'] ?? 0);
                $row['overtime_total'] = (float)($row['overtime_total'] ?? 0);
                $row['manual_addition_total'] = (float)($row['manual_addition_total'] ?? 0);
                $row['late_deduction_total'] = (float)($row['late_deduction_total'] ?? 0);
                $row['alpha_deduction_total'] = (float)($row['alpha_deduction_total'] ?? 0);
                $row['manual_deduction_total'] = (float)($row['manual_deduction_total'] ?? 0);
                $row['cash_advance_cut'] = (float)($row['cash_advance_cut_total'] ?? 0);
                $row['net_pay_raw'] = (float)($row['net_pay_raw'] ?? ($row['net_pay'] ?? 0));
                $row['rounding_adjustment'] = (float)($row['rounding_adjustment'] ?? 0);
            } else {
                $row['basic_total'] = 0;
                $row['allowance_total'] = 0;
                $row['meal_total'] = 0;
                $row['overtime_total'] = 0;
                $row['manual_addition_total'] = 0;
                $row['late_deduction_total'] = 0;
                $row['alpha_deduction_total'] = 0;
                $row['manual_deduction_total'] = 0;
                $row['cash_advance_cut'] = 0;
                $row['net_pay_raw'] = (float)($row['net_pay'] ?? 0);
                $row['rounding_adjustment'] = 0;
            }
        }
        unset($row);

        return $baseRows;
    }

    public function count_generated_salary_lines_by_employee(int $employeeId, string $dateStart = '', string $dateEnd = ''): int
    {
        if ($employeeId <= 0) {
            return 0;
        }
        $this->db->from('pay_salary_disbursement_line l')
            ->join('pay_salary_disbursement h', 'h.id = l.disbursement_id', 'inner')
            ->where('l.employee_id', $employeeId)
            ->where('h.status <>', 'VOID');
        if ($dateStart !== '') {
            $this->db->where('h.disbursement_date >=', $dateStart);
        }
        if ($dateEnd !== '') {
            $this->db->where('h.disbursement_date <=', $dateEnd);
        }
        return (int)$this->db->count_all_results();
    }

    public function list_generated_salary_lines_by_employee(int $employeeId, string $dateStart = '', string $dateEnd = '', int $limit = 50, int $offset = 0): array
    {
        if ($employeeId <= 0) {
            return [];
        }
        $sourceAccountExpr = $this->table_has_field('pay_salary_disbursement_line', 'company_account_id')
            ? 'COALESCE(src_line.account_name, src_header.account_name) AS source_account_name'
            : 'src_header.account_name AS source_account_name';

        $lineSnapshotExpr = $this->table_has_field('pay_salary_disbursement_line', 'net_pay_raw_snapshot')
            ? '
                l.net_pay_raw_snapshot,
                l.rounding_adjustment_snapshot,
                l.net_pay_snapshot,
            '
            : '';

        $this->db->select('
                l.id AS line_id,
                l.transfer_amount,
                l.transfer_status,
                l.transfer_ref_no,
                l.paid_at,
                h.id AS disbursement_id,
                h.disbursement_no,
                h.disbursement_date,
                h.status AS disbursement_status,
                p.period_code,
                p.period_start,
                p.period_end,
                r.employee_code_snapshot,
                r.employee_name_snapshot,
                r.net_pay,
                r.net_pay_raw,
                r.rounding_adjustment,
                ' . $lineSnapshotExpr . '
                ' . $sourceAccountExpr . '
            ', false)
            ->from('pay_salary_disbursement_line l')
            ->join('pay_salary_disbursement h', 'h.id = l.disbursement_id', 'inner')
            ->join('pay_payroll_period p', 'p.id = h.payroll_period_id', 'left')
            ->join('pay_payroll_result r', 'r.id = l.payroll_result_id', 'left')
            ->join('fin_company_account src_header', 'src_header.id = h.company_account_id', 'left');
        if ($this->table_has_field('pay_salary_disbursement_line', 'company_account_id')) {
            $this->db->join('fin_company_account src_line', 'src_line.id = l.company_account_id', 'left');
        }
        $this->db->where('l.employee_id', $employeeId)
            ->where('h.status <>', 'VOID');
        if ($dateStart !== '') {
            $this->db->where('h.disbursement_date >=', $dateStart);
        }
        if ($dateEnd !== '') {
            $this->db->where('h.disbursement_date <=', $dateEnd);
        }
        $rows = $this->db->order_by('h.disbursement_date', 'DESC')
            ->order_by('l.id', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();

        foreach ($rows as &$row) {
            if ($this->table_has_field('pay_salary_disbursement_line', 'net_pay_raw_snapshot')) {
                $row['net_pay_raw'] = (float)($row['net_pay_raw_snapshot'] ?? ($row['net_pay_raw'] ?? 0));
                $row['rounding_adjustment'] = (float)($row['rounding_adjustment_snapshot'] ?? ($row['rounding_adjustment'] ?? 0));
                $row['net_pay'] = (float)($row['net_pay_snapshot'] ?? ($row['net_pay'] ?? 0));
            }
        }
        unset($row);

        return $rows;
    }

    public function audit_payroll_period_consistency(int $periodId): array
    {
        $empty = [
            'period' => null,
            'summary' => [
                'result_rows' => 0,
                'result_net_raw_total' => 0.0,
                'result_net_final_total' => 0.0,
                'attendance_net_total' => 0.0,
                'active_disbursement_transfer_total' => 0.0,
                'raw_vs_attendance_diff_total' => 0.0,
                'transfer_vs_result_final_diff_total' => 0.0,
                'result_duplicates' => 0,
                'active_disbursement_duplicates' => 0,
                'mismatch_rows' => 0,
            ],
            'duplicates_result' => [],
            'duplicates_disbursement' => [],
            'mismatch_rows' => [],
        ];
        if ($periodId <= 0) {
            return $empty;
        }

        $period = $this->db->select('*')->from('pay_payroll_period')->where('id', $periodId)->limit(1)->get()->row_array();
        if (!$period) {
            return $empty;
        }
        $empty['period'] = $period;

        $resultRows = $this->db->select("
                r.id AS payroll_result_id,
                r.employee_id,
                r.employee_code_snapshot,
                r.employee_name_snapshot,
                COALESCE(r.net_pay_raw, r.net_pay, 0) AS result_net_raw,
                COALESCE(r.net_pay, 0) AS result_net_final,
                COALESCE(att.net_attendance, 0) AS attendance_net,
                COALESCE(disb.transfer_total, 0) AS active_transfer_total
            ", false)
            ->from('pay_payroll_result r')
            ->join('
                (
                    SELECT ad.employee_id, SUM(COALESCE(ad.daily_salary_amount,0)) AS net_attendance
                    FROM att_daily ad
                    WHERE ad.attendance_date >= ' . $this->db->escape((string)$period['period_start']) . '
                      AND ad.attendance_date <= ' . $this->db->escape((string)$period['period_end']) . '
                      AND (
                          ad.checkout_at IS NOT NULL
                          OR ad.attendance_status = "HOLIDAY"
                      )
                    GROUP BY ad.employee_id
                ) att
            ', 'att.employee_id = r.employee_id', 'left', false)
            ->join('
                (
                    SELECT l.payroll_result_id, SUM(COALESCE(l.transfer_amount,0)) AS transfer_total
                    FROM pay_salary_disbursement_line l
                    INNER JOIN pay_salary_disbursement h
                        ON h.id = l.disbursement_id
                       AND h.status <> "VOID"
                    GROUP BY l.payroll_result_id
                ) disb
            ', 'disb.payroll_result_id = r.id', 'left', false)
            ->where('r.payroll_period_id', $periodId)
            ->order_by('r.employee_name_snapshot', 'ASC')
            ->get()->result_array();

        $duplicatesResult = $this->db->select('r.employee_id, COUNT(*) AS duplicate_count, GROUP_CONCAT(r.id ORDER BY r.id) AS payroll_result_ids', false)
            ->from('pay_payroll_result r')
            ->where('r.payroll_period_id', $periodId)
            ->group_by('r.employee_id')
            ->having('COUNT(*) > 1', null, false)
            ->get()->result_array();

        $duplicatesDisbursement = $this->db->select('l.payroll_result_id, COUNT(*) AS duplicate_count, GROUP_CONCAT(CONCAT(h.disbursement_no, "#", l.id) ORDER BY l.id) AS line_refs', false)
            ->from('pay_salary_disbursement_line l')
            ->join('pay_salary_disbursement h', 'h.id = l.disbursement_id', 'inner')
            ->where('h.payroll_period_id', $periodId)
            ->where('h.status <>', 'VOID')
            ->group_by('l.payroll_result_id')
            ->having('COUNT(*) > 1', null, false)
            ->get()->result_array();

        $summary = $empty['summary'];
        $summary['result_rows'] = count($resultRows);
        $mismatchRows = [];
        foreach ($resultRows as $row) {
            $resultRaw = round((float)($row['result_net_raw'] ?? 0), 2);
            $resultFinal = round((float)($row['result_net_final'] ?? 0), 2);
            $attendance = round((float)($row['attendance_net'] ?? 0), 2);
            $transfer = round((float)($row['active_transfer_total'] ?? 0), 2);
            $diffAtt = round($resultRaw - $attendance, 2);
            $diffTransferFinal = round($transfer - $resultFinal, 2);

            $summary['result_net_raw_total'] += $resultRaw;
            $summary['result_net_final_total'] += $resultFinal;
            $summary['attendance_net_total'] += $attendance;
            $summary['active_disbursement_transfer_total'] += $transfer;

            if (abs($diffAtt) > 0.009 || abs($diffTransferFinal) > 0.009) {
                $row['diff_raw_vs_attendance'] = $diffAtt;
                $row['diff_transfer_vs_final'] = $diffTransferFinal;
                $mismatchRows[] = $row;
            }
        }
        $summary['result_net_raw_total'] = round((float)$summary['result_net_raw_total'], 2);
        $summary['result_net_final_total'] = round((float)$summary['result_net_final_total'], 2);
        $summary['attendance_net_total'] = round((float)$summary['attendance_net_total'], 2);
        $summary['active_disbursement_transfer_total'] = round((float)$summary['active_disbursement_transfer_total'], 2);
        $summary['raw_vs_attendance_diff_total'] = round($summary['result_net_raw_total'] - $summary['attendance_net_total'], 2);
        $summary['transfer_vs_result_final_diff_total'] = round($summary['active_disbursement_transfer_total'] - $summary['result_net_final_total'], 2);
        $summary['result_duplicates'] = count($duplicatesResult);
        $summary['active_disbursement_duplicates'] = count($duplicatesDisbursement);
        $summary['mismatch_rows'] = count($mismatchRows);

        return [
            'period' => $period,
            'summary' => $summary,
            'duplicates_result' => $duplicatesResult,
            'duplicates_disbursement' => $duplicatesDisbursement,
            'mismatch_rows' => $mismatchRows,
        ];
    }

    public function get_salary_disbursement_line_slip(int $lineId, int $employeeId = 0): ?array
    {
        if ($lineId <= 0) {
            return null;
        }

        $sourceAccountExpr = $this->table_has_field('pay_salary_disbursement_line', 'company_account_id')
            ? '
                COALESCE(src_line.account_name, src_header.account_name) AS source_account_name,
                COALESCE(src_line.account_code, src_header.account_code) AS source_account_code
            '
            : '
                src_header.account_name AS source_account_name,
                src_header.account_code AS source_account_code
            ';

        $resultSnapshotExpr = $this->table_has_field('pay_salary_disbursement_line', 'basic_total_snapshot')
            ? '
                l.basic_total_snapshot,
                l.allowance_total_snapshot,
                l.meal_total_snapshot,
                l.overtime_total_snapshot,
                l.manual_addition_total_snapshot,
                l.late_deduction_total_snapshot,
                l.alpha_deduction_total_snapshot,
                l.manual_deduction_total_snapshot,
                l.cash_advance_cut_total_snapshot,
                l.gross_pay_snapshot,
                l.total_deduction_snapshot,
                l.net_pay_raw_snapshot,
                l.rounding_adjustment_snapshot,
                l.net_pay_snapshot,
            '
            : '';

        $bankNameExpr = $this->table_has_field('pay_salary_disbursement_line', 'employee_bank_name')
            ? 'COALESCE(l.employee_bank_name, e.bank_name) AS employee_bank_name'
            : 'e.bank_name AS employee_bank_name';
        $bankNoExpr = $this->table_has_field('pay_salary_disbursement_line', 'employee_bank_account_no')
            ? 'COALESCE(l.employee_bank_account_no, e.bank_account_no) AS employee_bank_account_no'
            : 'e.bank_account_no AS employee_bank_account_no';
        $bankHolderExpr = $this->table_has_field('pay_salary_disbursement_line', 'employee_bank_account_name')
            ? 'COALESCE(l.employee_bank_account_name, e.bank_account_name) AS employee_bank_account_name'
            : 'e.bank_account_name AS employee_bank_account_name';

        $this->db->select('
                l.id AS line_id,
                l.transfer_amount,
                l.transfer_status,
                l.transfer_ref_no,
                l.paid_at,
                h.id AS disbursement_id,
                h.disbursement_no,
                h.disbursement_date,
                h.status AS disbursement_status,
                p.period_code,
                p.period_start,
                p.period_end,
                r.employee_id,
                r.employee_code_snapshot,
                r.employee_name_snapshot,
                r.net_pay,
                r.net_pay_raw,
                r.rounding_adjustment,
                ' . $resultSnapshotExpr . '
                ' . $sourceAccountExpr . ',
                ' . $bankNameExpr . ',
                ' . $bankNoExpr . ',
                ' . $bankHolderExpr . '
            ', false)
            ->from('pay_salary_disbursement_line l')
            ->join('pay_salary_disbursement h', 'h.id = l.disbursement_id', 'inner')
            ->join('pay_payroll_period p', 'p.id = h.payroll_period_id', 'left')
            ->join('pay_payroll_result r', 'r.id = l.payroll_result_id', 'left')
            ->join('org_employee e', 'e.id = l.employee_id', 'left')
            ->join('fin_company_account src_header', 'src_header.id = h.company_account_id', 'left');
        if ($this->table_has_field('pay_salary_disbursement_line', 'company_account_id')) {
            $this->db->join('fin_company_account src_line', 'src_line.id = l.company_account_id', 'left');
        }
        $this->db->where('l.id', $lineId)
            ->where('h.status <>', 'VOID');
        if ($employeeId > 0) {
            $this->db->where('l.employee_id', $employeeId);
        }
        $row = $this->db->limit(1)->get()->row_array();
        if (!$row) {
            return null;
        }

        if ($this->table_has_field('pay_salary_disbursement_line', 'basic_total_snapshot')) {
            $row['basic_total'] = (float)($row['basic_total_snapshot'] ?? 0);
            $row['allowance_total'] = (float)($row['allowance_total_snapshot'] ?? 0);
            $row['meal_total'] = (float)($row['meal_total_snapshot'] ?? 0);
            $row['overtime_total'] = (float)($row['overtime_total_snapshot'] ?? 0);
            $row['manual_addition_total'] = (float)($row['manual_addition_total_snapshot'] ?? 0);
            $row['late_deduction_total'] = (float)($row['late_deduction_total_snapshot'] ?? 0);
            $row['alpha_deduction_total'] = (float)($row['alpha_deduction_total_snapshot'] ?? 0);
            $row['manual_deduction_total'] = (float)($row['manual_deduction_total_snapshot'] ?? 0);
            $row['cash_advance_cut_total'] = (float)($row['cash_advance_cut_total_snapshot'] ?? 0);
            $row['gross_pay'] = (float)($row['gross_pay_snapshot'] ?? 0);
            $row['total_deduction'] = (float)($row['total_deduction_snapshot'] ?? 0);
            $row['net_pay_raw'] = (float)($row['net_pay_raw_snapshot'] ?? ($row['net_pay'] ?? 0));
            $row['rounding_adjustment'] = (float)($row['rounding_adjustment_snapshot'] ?? 0);
            $row['net_pay'] = (float)($row['net_pay_snapshot'] ?? ($row['net_pay'] ?? 0));
        } else {
            $row['basic_total'] = 0.0;
            $row['allowance_total'] = 0.0;
            $row['meal_total'] = 0.0;
            $row['overtime_total'] = 0.0;
            $row['manual_addition_total'] = 0.0;
            $row['late_deduction_total'] = 0.0;
            $row['alpha_deduction_total'] = 0.0;
            $row['manual_deduction_total'] = 0.0;
            $row['cash_advance_cut_total'] = 0.0;
            $row['gross_pay'] = (float)($row['net_pay'] ?? 0);
            $row['total_deduction'] = 0.0;
            $row['net_pay_raw'] = (float)($row['net_pay_raw'] ?? ($row['net_pay'] ?? 0));
            $row['rounding_adjustment'] = (float)($row['rounding_adjustment'] ?? 0);
            $row['net_pay'] = (float)($row['net_pay'] ?? 0);
        }

        $row['meal_paid_total'] = 0.0;
        $row['meal_paid_days'] = 0;
        $row['meal_paid_deduction'] = 0.0;
        if (
            $this->db->table_exists('pay_meal_disbursement_line')
            && $this->db->table_exists('pay_meal_disbursement')
            && !empty($row['employee_id'])
            && !empty($row['period_start'])
            && !empty($row['period_end'])
        ) {
            $mealPaid = $this->db->select('COUNT(*) AS day_count, COALESCE(SUM(ml.meal_amount),0) AS paid_total', false)
                ->from('pay_meal_disbursement_line ml')
                ->join('pay_meal_disbursement md', 'md.id = ml.disbursement_id', 'inner')
                ->where('ml.employee_id', (int)$row['employee_id'])
                ->where('md.status', 'PAID')
                ->where('ml.transfer_status', 'PAID')
                ->where('ml.attendance_date >=', (string)$row['period_start'])
                ->where('ml.attendance_date <=', (string)$row['period_end'])
                ->get()->row_array() ?: [];
            $row['meal_paid_total'] = round((float)($mealPaid['paid_total'] ?? 0), 2);
            $row['meal_paid_days'] = (int)($mealPaid['day_count'] ?? 0);
            $row['meal_paid_deduction'] = round(min((float)$row['meal_total'], (float)$row['meal_paid_total']), 2);
        }

        return $row;
    }

    public function generate_salary_disbursement(array $payload, int $actorUserId): array
    {
        $periodId = (int)($payload['payroll_period_id'] ?? 0);
        $disbursementDate = trim((string)($payload['disbursement_date'] ?? date('Y-m-d')));
        $companyAccountId = (int)($payload['company_account_id'] ?? 0);
        $sourceByEmployee = (array)($payload['employee_source_account'] ?? []);
        $notes = trim((string)($payload['notes'] ?? ''));

        if ($periodId <= 0) {
            return ['ok' => false, 'message' => 'Payroll period wajib dipilih.'];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $disbursementDate)) {
            return ['ok' => false, 'message' => 'Tanggal pencairan tidak valid.'];
        }
        $accounts = $this->get_company_account_options();
        $accountMap = [];
        foreach ($accounts as $a) {
            $accountMap[(int)($a['value'] ?? 0)] = $a;
        }
        $fallbackAccountId = $companyAccountId > 0 ? $companyAccountId : $this->default_company_account_id($accounts);

        $candidates = $this->fetch_salary_disbursement_candidates($periodId);

        if (empty($candidates)) {
            return ['ok' => false, 'message' => 'Tidak ada kandidat pencairan gaji baru untuk period ini.'];
        }

        $seenResult = [];
        $seenEmployee = [];
        foreach ($candidates as $candidate) {
            $resultId = (int)($candidate['payroll_result_id'] ?? 0);
            $employeeId = (int)($candidate['employee_id'] ?? 0);
            if ($resultId <= 0 || $employeeId <= 0) {
                return ['ok' => false, 'message' => 'Ada kandidat payroll tidak valid (payroll_result/employee kosong).'];
            }
            if (isset($seenResult[$resultId])) {
                return ['ok' => false, 'message' => 'Kandidat duplikat payroll result terdeteksi. Refresh payroll period lalu coba lagi.'];
            }
            if (isset($seenEmployee[$employeeId])) {
                return ['ok' => false, 'message' => 'Kandidat duplikat pegawai terdeteksi pada periode ini. Refresh payroll period lalu coba lagi.'];
            }
            $seenResult[$resultId] = true;
            $seenEmployee[$employeeId] = true;
        }

        $lineSources = [];
        $hasUnresolvedSource = false;
        foreach ($candidates as $c) {
            $eid = (int)($c['employee_id'] ?? 0);
            $chosen = isset($sourceByEmployee[$eid]) ? (int)$sourceByEmployee[$eid] : 0;
            if ($chosen <= 0 || !isset($accountMap[$chosen])) {
                $chosen = $this->resolve_source_account_id_for_employee($c, $accounts, $fallbackAccountId);
            }
            if ($chosen <= 0) {
                $hasUnresolvedSource = true;
            }
            $lineSources[$eid] = $chosen;
        }
        if ($hasUnresolvedSource) {
            return ['ok' => false, 'message' => 'Ada pegawai yang belum punya rekening sumber perusahaan yang valid. Lakukan preview lalu pilih rekening sumber per pegawai.'];
        }

        $no = $this->next_doc_no('pay_salary_disbursement', 'disbursement_no', 'SAL');
        $total = 0.0;
        foreach ($candidates as $c) {
            $total += (float)($c['net_pay'] ?? 0);
        }

        $headerAccountId = 0;
        $distinctSource = array_values(array_unique(array_filter(array_map(static function ($v) {
            return (int)$v;
        }, $lineSources))));
        if (count($distinctSource) === 1) {
            $headerAccountId = (int)$distinctSource[0];
        }

        $this->db->trans_start();
        $this->db->insert('pay_salary_disbursement', [
            'payroll_period_id' => $periodId,
            'disbursement_no' => $no,
            'disbursement_date' => $disbursementDate,
            'company_account_id' => $headerAccountId > 0 ? $headerAccountId : null,
            'status' => 'POSTED',
            'total_amount' => round($total, 2),
            'notes' => $notes !== '' ? $notes : null,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $disbursementId = (int)$this->db->insert_id();

        foreach ($candidates as $c) {
            $linePayload = [
                'disbursement_id' => $disbursementId,
                'payroll_result_id' => (int)$c['payroll_result_id'],
                'employee_id' => (int)$c['employee_id'],
                'transfer_amount' => round((float)($c['net_pay'] ?? 0), 2),
                'transfer_status' => 'PENDING',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($this->table_has_field('pay_salary_disbursement_line', 'company_account_id')) {
                $eid = (int)($c['employee_id'] ?? 0);
                $linePayload['company_account_id'] = (int)($lineSources[$eid] ?? 0) > 0 ? (int)$lineSources[$eid] : null;
            }
            if ($this->table_has_field('pay_salary_disbursement_line', 'employee_bank_name')) {
                $linePayload['employee_bank_name'] = trim((string)($c['bank_name'] ?? '')) !== '' ? trim((string)$c['bank_name']) : null;
            }
            if ($this->table_has_field('pay_salary_disbursement_line', 'employee_bank_account_no')) {
                $linePayload['employee_bank_account_no'] = trim((string)($c['bank_account_no'] ?? '')) !== '' ? trim((string)$c['bank_account_no']) : null;
            }
            if ($this->table_has_field('pay_salary_disbursement_line', 'employee_bank_account_name')) {
                $linePayload['employee_bank_account_name'] = trim((string)($c['bank_account_name'] ?? '')) !== '' ? trim((string)$c['bank_account_name']) : null;
            }
            if ($this->table_has_field('pay_salary_disbursement_line', 'basic_total_snapshot')) {
                $linePayload['basic_total_snapshot'] = round((float)($c['basic_total'] ?? 0), 2);
                $linePayload['allowance_total_snapshot'] = round((float)($c['allowance_total'] ?? 0), 2);
                $linePayload['meal_total_snapshot'] = round((float)($c['meal_total'] ?? 0), 2);
                $linePayload['overtime_total_snapshot'] = round((float)($c['overtime_total'] ?? 0), 2);
                $linePayload['manual_addition_total_snapshot'] = round((float)($c['manual_addition_total'] ?? 0), 2);
                $linePayload['late_deduction_total_snapshot'] = round((float)($c['late_deduction_total'] ?? 0), 2);
                $linePayload['alpha_deduction_total_snapshot'] = round((float)($c['alpha_deduction_total'] ?? 0), 2);
                $linePayload['manual_deduction_total_snapshot'] = round((float)($c['manual_deduction_total'] ?? 0), 2);
                $linePayload['cash_advance_cut_total_snapshot'] = round((float)($c['cash_advance_cut_total'] ?? 0), 2);
                $linePayload['gross_pay_snapshot'] = round((float)($c['gross_pay'] ?? 0), 2);
                $linePayload['total_deduction_snapshot'] = round((float)($c['total_deduction'] ?? 0), 2);
                $linePayload['net_pay_raw_snapshot'] = round((float)($c['net_pay_raw'] ?? ($c['net_pay'] ?? 0)), 2);
                $linePayload['rounding_adjustment_snapshot'] = round((float)($c['rounding_adjustment'] ?? 0), 2);
                $linePayload['net_pay_snapshot'] = round((float)($c['net_pay'] ?? 0), 2);
            }
            $this->db->insert('pay_salary_disbursement_line', $linePayload);
        }
        $this->db->trans_complete();

        if (!$this->db->trans_status()) {
            return ['ok' => false, 'message' => 'Gagal membuat batch pencairan gaji.'];
        }

        return ['ok' => true, 'message' => 'Batch pencairan gaji berhasil dibuat.', 'disbursement_id' => $disbursementId];
    }

    public function post_salary_disbursement_paid(int $disbursementId, string $transferRefNo = '', int $actorUserId = 0): array
    {
        $header = $this->get_salary_disbursement_by_id($disbursementId);
        if (!$header) {
            return ['ok' => false, 'message' => 'Batch gaji tidak ditemukan.'];
        }
        if (strtoupper((string)$header['status']) === 'VOID') {
            return ['ok' => false, 'message' => 'Batch VOID tidak bisa diproses bayar.'];
        }

        $now = date('Y-m-d H:i:s');
        $this->db->trans_start();

        $header = $this->db->query('SELECT * FROM pay_salary_disbursement WHERE id = ? LIMIT 1 FOR UPDATE', [$disbursementId])->row_array();
        if (!$header) {
            $this->db->trans_complete();
            return ['ok' => false, 'message' => 'Batch gaji tidak ditemukan.'];
        }
        if (strtoupper((string)$header['status']) === 'VOID') {
            $this->db->trans_complete();
            return ['ok' => false, 'message' => 'Batch VOID tidak bisa diproses bayar.'];
        }
        $accountId = (int)($header['company_account_id'] ?? 0);
        $lineHasSource = $this->table_has_field('pay_salary_disbursement_line', 'company_account_id');
        $pendingSelect = 'id, payroll_result_id, transfer_amount';
        if ($lineHasSource) {
            $pendingSelect .= ', company_account_id';
        }
        $pendingRows = $this->db->select($pendingSelect, false)
            ->from('pay_salary_disbursement_line')
            ->where('disbursement_id', $disbursementId)
            ->where_in('transfer_status', ['PENDING', 'FAILED'])
            ->get()->result_array();
        $payNowAmount = 0.0;
        $amountByAccount = [];
        foreach ($pendingRows as $row) {
            $amt = (float)($row['transfer_amount'] ?? 0);
            $payNowAmount += $amt;
            $lineAccountId = $lineHasSource ? (int)($row['company_account_id'] ?? 0) : 0;
            if ($lineAccountId <= 0) {
                $lineAccountId = $accountId;
            }
            if ($lineAccountId <= 0) {
                $this->db->trans_complete();
                return ['ok' => false, 'message' => 'Ada baris gaji tanpa rekening sumber mutasi.'];
            }
            if (!isset($amountByAccount[$lineAccountId])) {
                $amountByAccount[$lineAccountId] = 0.0;
            }
            $amountByAccount[$lineAccountId] += $amt;
        }
        $payNowAmount = round($payNowAmount, 2);

        if ($payNowAmount > 0) {
            if (!$this->db->table_exists('fin_company_account') || !$this->db->table_exists('fin_account_mutation_log')) {
                $this->db->trans_complete();
                return ['ok' => false, 'message' => 'Tabel mutasi/rekening belum tersedia.'];
            }
            $mutationDate = (string)($header['disbursement_date'] ?? date('Y-m-d'));
            foreach ($amountByAccount as $lineAccountId => $lineAmountRaw) {
                $lineAmount = round((float)$lineAmountRaw, 2);
                if ($lineAmount <= 0) {
                    continue;
                }
                $account = $this->db->query('SELECT * FROM fin_company_account WHERE id = ? AND is_active = 1 LIMIT 1 FOR UPDATE', [(int)$lineAccountId])->row_array();
                if (!$account) {
                    $this->db->trans_complete();
                    return ['ok' => false, 'message' => 'Rekening sumber batch gaji tidak ditemukan atau nonaktif.'];
                }
                $balanceBefore = round((float)($account['current_balance'] ?? 0), 2);
                if ($balanceBefore < $lineAmount) {
                    $this->db->trans_complete();
                    return ['ok' => false, 'message' => 'Saldo rekening tidak cukup untuk menandai batch PAID.'];
                }
                $balanceAfter = round($balanceBefore - $lineAmount, 2);

                $this->db->where('id', (int)$lineAccountId)->update('fin_company_account', [
                    'current_balance' => $balanceAfter,
                ]);

                $this->db->insert('fin_account_mutation_log', [
                    'mutation_no' => $this->generate_account_mutation_no($mutationDate),
                    'mutation_date' => $mutationDate,
                    'account_id' => (int)$lineAccountId,
                    'mutation_type' => 'OUT',
                    'amount' => $lineAmount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'ref_module' => 'PAYROLL',
                    'ref_table' => 'pay_salary_disbursement',
                    'ref_id' => $disbursementId,
                    'ref_no' => (string)($header['disbursement_no'] ?? ''),
                    'notes' => 'Pencairan batch gaji ' . (string)($header['disbursement_no'] ?? ''),
                    'created_by' => $actorUserId > 0 ? $actorUserId : null,
                    'created_at' => $now,
                ]);
            }
        }

        if ($payNowAmount > 0) {
            $this->db->where('disbursement_id', $disbursementId)
                ->where_in('transfer_status', ['PENDING', 'FAILED'])
                ->update('pay_salary_disbursement_line', [
                    'transfer_status' => 'PAID',
                    'transfer_ref_no' => $transferRefNo !== '' ? $transferRefNo : null,
                    'paid_at' => $now,
                    'updated_at' => $now,
                ]);
        }

        $paidResultIds = $this->db->select('payroll_result_id')
            ->from('pay_salary_disbursement_line')
            ->where('disbursement_id', $disbursementId)
            ->where('transfer_status', 'PAID')
            ->get()->result_array();
        $resultIds = array_map(static function ($r) {
            return (int)($r['payroll_result_id'] ?? 0);
        }, $paidResultIds);
        $resultIds = array_values(array_filter($resultIds));
        if (!empty($resultIds)) {
            $this->db->where_in('id', $resultIds)->update('pay_payroll_result', [
                'status' => 'PAID',
                'paid_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $remaining = $this->db->select('COUNT(*) AS c', false)
            ->from('pay_salary_disbursement_line')
            ->where('disbursement_id', $disbursementId)
            ->where('transfer_status <>', 'PAID')
            ->get()->row_array();
        $headerStatus = ((int)($remaining['c'] ?? 0) === 0) ? 'PAID' : 'POSTED';
        $this->db->where('id', $disbursementId)->update('pay_salary_disbursement', [
            'status' => $headerStatus,
            'updated_at' => $now,
        ]);

        $this->db->trans_complete();
        if (!$this->db->trans_status()) {
            return ['ok' => false, 'message' => 'Gagal memproses pembayaran batch gaji.'];
        }

        if ($payNowAmount <= 0) {
            return ['ok' => true, 'message' => 'Tidak ada baris PENDING untuk diposting.'];
        }
        return ['ok' => true, 'message' => $headerStatus === 'PAID' ? 'Batch gaji ditandai lunas.' : 'Sebagian baris ditandai paid.'];
    }

    public function void_salary_disbursement(int $disbursementId, string $notes = '', int $actorUserId = 0): array
    {
        $header = $this->get_salary_disbursement_by_id($disbursementId);
        if (!$header) {
            return ['ok' => false, 'message' => 'Batch gaji tidak ditemukan.'];
        }

        $now = date('Y-m-d H:i:s');
        $this->db->trans_start();
        $header = $this->db->query('SELECT * FROM pay_salary_disbursement WHERE id = ? LIMIT 1 FOR UPDATE', [$disbursementId])->row_array();
        if (!$header) {
            $this->db->trans_complete();
            return ['ok' => false, 'message' => 'Batch gaji tidak ditemukan.'];
        }
        $currentStatus = strtoupper((string)($header['status'] ?? 'DRAFT'));
        if ($currentStatus === 'VOID') {
            $this->db->trans_complete();
            return ['ok' => false, 'message' => 'Batch sudah berstatus VOID.'];
        }

        if ($currentStatus === 'PAID') {
            if ($this->db->table_exists('fin_account_mutation_log') && $this->db->table_exists('fin_company_account')) {
                $outs = $this->db->select('account_id, SUM(COALESCE(amount,0)) AS total_out', false)
                    ->from('fin_account_mutation_log')
                    ->where('ref_module', 'PAYROLL')
                    ->where('ref_table', 'pay_salary_disbursement')
                    ->where('ref_id', $disbursementId)
                    ->where('mutation_type', 'OUT')
                    ->group_by('account_id')
                    ->get()->result_array();

                foreach ($outs as $out) {
                    $accountId = (int)($out['account_id'] ?? 0);
                    $amount = round((float)($out['total_out'] ?? 0), 2);
                    if ($accountId <= 0 || $amount <= 0) {
                        continue;
                    }
                    $credit = $this->credit_account_and_log_mutation(
                        $accountId,
                        $amount,
                        (string)($header['disbursement_date'] ?? date('Y-m-d')),
                        'pay_salary_disbursement',
                        $disbursementId,
                        (string)($header['disbursement_no'] ?? ''),
                        'VOID batch gaji ' . (string)($header['disbursement_no'] ?? ''),
                        $actorUserId
                    );
                    if (empty($credit['ok'])) {
                        $this->db->trans_complete();
                        return $credit;
                    }
                }
            }
        }

        $lineRows = $this->db->select('payroll_result_id')
            ->from('pay_salary_disbursement_line')
            ->where('disbursement_id', $disbursementId)
            ->get()->result_array();
        $resultIds = array_values(array_filter(array_map(static function ($row) {
            return (int)($row['payroll_result_id'] ?? 0);
        }, $lineRows)));

        if (!empty($resultIds)) {
            $this->db->where_in('id', $resultIds)->update('pay_payroll_result', [
                'status' => 'FINALIZED',
                'paid_at' => null,
                'updated_at' => $now,
            ]);
        }

        $voidNotes = trim($notes);
        $mergedNotes = trim((string)($header['notes'] ?? ''));
        $suffix = $voidNotes !== '' ? ('VOID: ' . $voidNotes) : 'VOID';
        $mergedNotes = $mergedNotes !== '' ? ($mergedNotes . ' | ' . $suffix) : $suffix;

        $this->db->where('id', $disbursementId)->update('pay_salary_disbursement', [
            'status' => 'VOID',
            'notes' => $mergedNotes,
            'updated_at' => $now,
        ]);
        $this->db->where('disbursement_id', $disbursementId)->update('pay_salary_disbursement_line', [
            'transfer_status' => 'VOID',
            'updated_at' => $now,
        ]);
        $this->db->trans_complete();
        if (!$this->db->trans_status()) {
            return ['ok' => false, 'message' => 'Gagal melakukan VOID batch gaji.'];
        }
        return ['ok' => true, 'message' => 'Batch gaji berhasil di-VOID.'];
    }

    public function delete_salary_disbursement(int $disbursementId): array
    {
        $header = $this->get_salary_disbursement_by_id($disbursementId);
        if (!$header) {
            return ['ok' => false, 'message' => 'Batch gaji tidak ditemukan.'];
        }
        if (strtoupper((string)$header['status']) === 'PAID') {
            return ['ok' => false, 'message' => 'Batch PAID tidak bisa dihapus.'];
        }

        $paidLine = $this->db->select('COUNT(*) AS c', false)
            ->from('pay_salary_disbursement_line')
            ->where('disbursement_id', $disbursementId)
            ->where('transfer_status', 'PAID')
            ->get()->row_array();
        if ((int)($paidLine['c'] ?? 0) > 0) {
            return ['ok' => false, 'message' => 'Batch memiliki baris PAID, tidak bisa dihapus.'];
        }

        $this->db->trans_start();
        $this->db->where('disbursement_id', $disbursementId)->delete('pay_salary_disbursement_line');
        $this->db->where('id', $disbursementId)->delete('pay_salary_disbursement');
        $this->db->trans_complete();
        if (!$this->db->trans_status()) {
            return ['ok' => false, 'message' => 'Gagal menghapus batch gaji.'];
        }
        return ['ok' => true, 'message' => 'Batch gaji berhasil dihapus.'];
    }

    public function count_cash_advances(array $filters): int
    {
        $this->build_cash_advance_query($filters, false);
        return (int)$this->db->count_all_results();
    }

    public function list_cash_advances(array $filters, int $limit, int $offset): array
    {
        $this->build_cash_advance_query($filters, true);
        return $this->db->order_by('ca.request_date', 'DESC')
            ->order_by('ca.id', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_cash_advance_query(array $filters, bool $withSelect): void
    {
        if ($withSelect) {
            $mutationCountSelect = '0 AS account_mutation_count';
            if ($this->db->table_exists('fin_account_mutation_log')) {
                $mutationCountSelect = "COALESCE((SELECT COUNT(*) FROM fin_account_mutation_log m WHERE m.ref_table='pay_cash_advance' AND m.ref_id=ca.id),0) AS account_mutation_count";
            }
            $this->db->select("
                ca.*,
                e.employee_code,
                e.employee_name,
                d.division_name,
                COALESCE((SELECT SUM(i.plan_amount) FROM pay_cash_advance_installment i WHERE i.cash_advance_id=ca.id),0) AS installment_plan_total,
                COALESCE((SELECT SUM(i.paid_amount) FROM pay_cash_advance_installment i WHERE i.cash_advance_id=ca.id),0) AS installment_paid_total,
                COALESCE((SELECT COUNT(*) FROM pay_cash_advance_installment i WHERE i.cash_advance_id=ca.id AND COALESCE(i.paid_amount,0) > 0),0) AS paid_installment_count,
                {$mutationCountSelect}
            ", false);
        }
        $this->db->from('pay_cash_advance ca')
            ->join('org_employee e', 'e.id = ca.employee_id', 'left')
            ->join('org_division d', 'd.id = e.division_id', 'left');

        if (!empty($filters['status'])) {
            $this->db->where('ca.status', strtoupper((string)$filters['status']));
        }
        if (!empty($filters['employee_id'])) {
            $this->db->where('ca.employee_id', (int)$filters['employee_id']);
        }
        if (!empty($filters['date_start'])) {
            $this->db->where('ca.request_date >=', (string)$filters['date_start']);
        }
        if (!empty($filters['date_end'])) {
            $this->db->where('ca.request_date <=', (string)$filters['date_end']);
        }
        if (!empty($filters['q'])) {
            $q = trim((string)$filters['q']);
            $this->db->group_start()
                ->like('ca.advance_no', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('e.employee_code', $q)
                ->or_like('ca.notes', $q)
                ->group_end();
        }
    }

    public function count_cash_advance_employee_recap(array $filters): int
    {
        $this->build_cash_advance_employee_recap_query($filters, false);
        return (int)$this->db->count_all_results();
    }

    public function list_cash_advance_employee_recap(array $filters, int $limit, int $offset): array
    {
        $this->build_cash_advance_employee_recap_query($filters, true);
        return $this->db->order_by('outstanding_total', 'DESC')
            ->order_by('e.employee_name', 'ASC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_cash_advance_employee_recap_query(array $filters, bool $withSelect): void
    {
        if ($withSelect) {
            $this->db->select("
                e.id AS employee_id,
                e.employee_code,
                e.employee_name,
                d.division_name,
                COUNT(ca.id) AS doc_count,
                SUM(CASE WHEN ca.status IN ('APPROVED','SETTLED') THEN COALESCE(ca.amount,0) ELSE 0 END) AS total_amount,
                SUM(CASE WHEN ca.status IN ('APPROVED','SETTLED') THEN (COALESCE(ca.amount,0)-COALESCE(ca.outstanding_amount,0)) ELSE 0 END) AS paid_total,
                SUM(CASE WHEN ca.status IN ('APPROVED','SETTLED') THEN COALESCE(ca.outstanding_amount,0) ELSE 0 END) AS outstanding_total
            ", false);
        } else {
            // Hindari SELECT * pada query grouped count_all_results (bisa bentrok kolom `id`).
            $this->db->select('e.id', false);
        }

        $this->db->from('pay_cash_advance ca')
            ->join('org_employee e', 'e.id = ca.employee_id', 'inner')
            ->join('org_division d', 'd.id = e.division_id', 'left');

        if (!empty($filters['employee_id'])) {
            $this->db->where('ca.employee_id', (int)$filters['employee_id']);
        }
        if (!empty($filters['date_start'])) {
            $this->db->where('ca.request_date >=', (string)$filters['date_start']);
        }
        if (!empty($filters['date_end'])) {
            $this->db->where('ca.request_date <=', (string)$filters['date_end']);
        }
        if (!empty($filters['q'])) {
            $q = trim((string)$filters['q']);
            $this->db->group_start()
                ->like('ca.advance_no', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('e.employee_code', $q)
                ->group_end();
        }
        $this->db->where_in('ca.status', ['APPROVED', 'SETTLED']);
        $this->db->group_by('e.id');
    }

    public function list_cash_advance_employee_history(int $employeeId): array
    {
        if ($employeeId <= 0) {
            return [];
        }

        $rows = $this->db->select("
                ca.*,
                COALESCE((SELECT SUM(i.plan_amount) FROM pay_cash_advance_installment i WHERE i.cash_advance_id = ca.id),0) AS installment_plan_total,
                COALESCE((SELECT SUM(i.paid_amount) FROM pay_cash_advance_installment i WHERE i.cash_advance_id = ca.id),0) AS installment_paid_total
            ", false)
            ->from('pay_cash_advance ca')
            ->where('ca.employee_id', $employeeId)
            ->where_in('ca.status', ['APPROVED', 'SETTLED', 'VOID'])
            ->order_by('ca.request_date', 'DESC')
            ->order_by('ca.id', 'DESC')
            ->get()->result_array();

        foreach ($rows as &$row) {
            $row['installments'] = $this->list_cash_advance_installments((int)($row['id'] ?? 0));
        }
        unset($row);
        return $rows;
    }

    public function get_cash_advance_by_id(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return $this->db->from('pay_cash_advance')->where('id', $id)->limit(1)->get()->row_array() ?: null;
    }

    public function list_cash_advance_installments(int $cashAdvanceId): array
    {
        if ($cashAdvanceId <= 0) {
            return [];
        }
        return $this->db->from('pay_cash_advance_installment')
            ->where('cash_advance_id', $cashAdvanceId)
            ->order_by('installment_no', 'ASC')
            ->get()->result_array();
    }

    private function build_cash_advance_installments(int $cashAdvanceId, string $baseDate, float $amount, int $tenor): void
    {
        $base = strtotime($baseDate);
        if ($base <= 0 || $tenor <= 0 || $amount <= 0) {
            return;
        }
        $plan = round($amount / $tenor, 2);
        $inserted = 0.0;
        for ($i = 1; $i <= $tenor; $i++) {
            $dueTs = strtotime('+' . ($i - 1) . ' month', $base);
            $duePeriod = date('Y-m', $dueTs);
            $rowPlan = ($i === $tenor) ? round($amount - $inserted, 2) : $plan;
            $inserted += $rowPlan;
            $this->db->insert('pay_cash_advance_installment', [
                'cash_advance_id' => $cashAdvanceId,
                'installment_no' => $i,
                'due_period' => $duePeriod,
                'plan_amount' => $rowPlan,
                'paid_amount' => 0,
                'status' => 'OPEN',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function refresh_cash_advance_outstanding(int $cashAdvanceId): void
    {
        $row = $this->db->select('id, amount, status')
            ->from('pay_cash_advance')
            ->where('id', $cashAdvanceId)
            ->limit(1)
            ->get()->row_array();
        if (!$row) {
            return;
        }
        $sum = $this->db->select('COALESCE(SUM(paid_amount),0) AS paid_total', false)
            ->from('pay_cash_advance_installment')
            ->where('cash_advance_id', $cashAdvanceId)
            ->get()->row_array();
        $paidTotal = round((float)($sum['paid_total'] ?? 0), 2);
        $amount = round((float)($row['amount'] ?? 0), 2);
        $outstanding = max(0, round($amount - $paidTotal, 2));
        $status = strtoupper((string)($row['status'] ?? 'DRAFT'));
        if ($status === 'APPROVED' && $outstanding <= 0) {
            $status = 'SETTLED';
        }
        $this->db->where('id', $cashAdvanceId)->update('pay_cash_advance', [
            'outstanding_amount' => $outstanding,
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function save_cash_advance(array $payload, int $actorUserId = 0): array
    {
        $id = (int)($payload['id'] ?? 0);
        $employeeId = (int)($payload['employee_id'] ?? 0);
        $requestDate = trim((string)($payload['request_date'] ?? ''));
        $approvedDate = trim((string)($payload['approved_date'] ?? ''));
        $amount = (float)($payload['amount'] ?? 0);
        $tenor = max(0, (int)($payload['tenor_month'] ?? 0));
        $companyAccountId = (int)($payload['company_account_id'] ?? 0);
        $status = strtoupper(trim((string)($payload['status'] ?? 'DRAFT')));
        $notes = trim((string)($payload['notes'] ?? ''));

        if ($employeeId <= 0 || $amount <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestDate)) {
            return ['ok' => false, 'message' => 'Pegawai, tanggal request, dan nominal wajib valid.'];
        }
        if (!in_array($status, ['DRAFT', 'APPROVED', 'REJECTED', 'SETTLED', 'VOID'], true)) {
            $status = 'DRAFT';
        }
        if ($status === 'APPROVED' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $approvedDate)) {
            $approvedDate = $requestDate;
        }
        if ($status !== 'APPROVED' && $status !== 'SETTLED') {
            $approvedDate = '';
        }

        $monthlyPlan = $tenor > 0 ? round($amount / $tenor, 2) : 0.0;
        $outstanding = in_array($status, ['APPROVED', 'SETTLED'], true) ? $amount : 0.0;
        if ($status === 'SETTLED') {
            $outstanding = 0.0;
        }

        $dbPayload = [
            'employee_id' => $employeeId,
            'request_date' => $requestDate,
            'approved_date' => $approvedDate !== '' ? $approvedDate : null,
            'amount' => round($amount, 2),
            'tenor_month' => $tenor,
            'monthly_deduction_plan' => $monthlyPlan,
            'outstanding_amount' => round($outstanding, 2),
            'status' => $status,
            'notes' => $notes !== '' ? $notes : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($this->table_has_field('pay_cash_advance', 'company_account_id')) {
            $dbPayload['company_account_id'] = $companyAccountId > 0 ? $companyAccountId : null;
        }

        $this->db->trans_start();
        if ($id > 0) {
            $exists = $this->get_cash_advance_by_id($id);
            if (!$exists) {
                $this->db->trans_complete();
                return ['ok' => false, 'message' => 'Data kasbon tidak ditemukan.'];
            }
            $this->db->where('id', $id)->update('pay_cash_advance', $dbPayload);
            $hasPaidInstallment = $this->db->select('COUNT(*) AS c', false)
                ->from('pay_cash_advance_installment')
                ->where('cash_advance_id', $id)
                ->where('paid_amount >', 0)
                ->get()->row_array();
            if ((int)($hasPaidInstallment['c'] ?? 0) === 0) {
                $this->db->where('cash_advance_id', $id)->delete('pay_cash_advance_installment');
                if (in_array($status, ['APPROVED', 'SETTLED'], true) && $tenor > 0) {
                    $this->build_cash_advance_installments($id, $approvedDate !== '' ? $approvedDate : $requestDate, $amount, $tenor);
                }
            }
            $this->refresh_cash_advance_outstanding($id);
            $wasApproved = in_array(strtoupper((string)($exists['status'] ?? 'DRAFT')), ['APPROVED', 'SETTLED'], true);
            $isApproved = in_array($status, ['APPROVED', 'SETTLED'], true);
            if ($isApproved) {
                if (!$wasApproved || (int)($exists['company_account_id'] ?? 0) !== $companyAccountId) {
                    $fresh = $this->get_cash_advance_by_id($id);
                    $post = $this->post_cash_advance_disbursement($id, $fresh ?: $dbPayload, $actorUserId);
                    if (empty($post['ok'])) {
                        $this->db->trans_complete();
                        return $post;
                    }
                }
            }
            $this->db->trans_complete();
            if (!$this->db->trans_status()) {
                return ['ok' => false, 'message' => 'Gagal memperbarui kasbon.'];
            }
            return ['ok' => true, 'message' => 'Kasbon berhasil diperbarui.'];
        }

        $dbPayload['advance_no'] = $this->next_doc_no('pay_cash_advance', 'advance_no', 'CA');
        $dbPayload['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('pay_cash_advance', $dbPayload);
        $newId = (int)$this->db->insert_id();
        if ($newId > 0 && in_array($status, ['APPROVED', 'SETTLED'], true) && $tenor > 0) {
            $this->build_cash_advance_installments($newId, $approvedDate !== '' ? $approvedDate : $requestDate, $amount, $tenor);
            $this->refresh_cash_advance_outstanding($newId);
        }
        if ($newId > 0 && in_array($status, ['APPROVED', 'SETTLED'], true)) {
            $fresh = $this->get_cash_advance_by_id($newId);
            $post = $this->post_cash_advance_disbursement($newId, $fresh ?: $dbPayload, $actorUserId);
            if (empty($post['ok'])) {
                $this->db->trans_complete();
                return $post;
            }
        }
        $this->db->trans_complete();
        if (!$this->db->trans_status()) {
            return ['ok' => false, 'message' => 'Gagal menambahkan kasbon.'];
        }
        return ['ok' => true, 'message' => 'Kasbon berhasil ditambahkan.'];
    }

    private function register_cash_advance_salary_cut(int $cashAdvanceId, array $header, float $amount, string $salaryCutDate, int $actorUserId = 0): void
    {
        if ($cashAdvanceId <= 0 || $amount <= 0 || $salaryCutDate === '') {
            return;
        }
        if (!$this->db->table_exists('pay_manual_adjustment')) {
            return;
        }
        $employeeId = (int)($header['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            return;
        }
        $advNo = (string)($header['advance_no'] ?? ('CA#' . $cashAdvanceId));

        $exists = $this->db->select('id')
            ->from('pay_manual_adjustment')
            ->where('employee_id', $employeeId)
            ->where('adjustment_date', $salaryCutDate)
            ->where('adjustment_kind', 'DEDUCTION')
            ->where('adjustment_name', 'Potongan Kasbon ' . $advNo)
            ->where('status', 'APPROVED')
            ->limit(1)
            ->get()->row_array();
        if ($exists) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->insert('pay_manual_adjustment', [
            'employee_id' => $employeeId,
            'adjustment_date' => $salaryCutDate,
            'adjustment_kind' => 'DEDUCTION',
            'adjustment_name' => 'Potongan Kasbon ' . $advNo,
            'amount' => round($amount, 2),
            'status' => 'APPROVED',
            'notes' => 'Auto dari pembayaran kasbon metode POTONG_GAJI',
            'approved_by' => $actorUserId > 0 ? $actorUserId : null,
            'approved_at' => $now,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->recompute_daily_manual_adjustment($employeeId, $salaryCutDate);
    }

    private function post_cash_advance_disbursement(int $cashAdvanceId, array $header, int $actorUserId = 0): array
    {
        if ($cashAdvanceId <= 0) {
            return ['ok' => false, 'message' => 'Kasbon tidak valid.'];
        }
        if (!$this->table_has_field('pay_cash_advance', 'company_account_id')) {
            return ['ok' => true, 'message' => 'Kolom rekening kasbon belum tersedia, skip mutasi otomatis.'];
        }
        $accountId = (int)($header['company_account_id'] ?? 0);
        $amount = round((float)($header['amount'] ?? 0), 2);
        if ($amount <= 0) {
            return ['ok' => false, 'message' => 'Nominal kasbon tidak valid.'];
        }
        if ($accountId <= 0) {
            return ['ok' => false, 'message' => 'Rekening sumber kasbon belum diisi.'];
        }
        if (!$this->db->table_exists('fin_account_mutation_log')) {
            return ['ok' => false, 'message' => 'Tabel mutation log belum tersedia.'];
        }

        $already = $this->db->select('COUNT(*) AS c', false)
            ->from('fin_account_mutation_log')
            ->where('ref_module', 'PAYROLL')
            ->where('ref_table', 'pay_cash_advance')
            ->where('ref_id', $cashAdvanceId)
            ->where('mutation_type', 'OUT')
            ->get()->row_array();
        if ((int)($already['c'] ?? 0) > 0) {
            return ['ok' => true, 'message' => 'Mutasi pencairan kasbon sudah tercatat.'];
        }

        $advNo = (string)($header['advance_no'] ?? ('CA#' . $cashAdvanceId));
        return $this->debit_account_and_log_mutation(
            $accountId,
            $amount,
            (string)($header['approved_date'] ?? $header['request_date'] ?? date('Y-m-d')),
            'pay_cash_advance',
            $cashAdvanceId,
            $advNo,
            'Pencairan kasbon ' . $advNo,
            $actorUserId
        );
    }

    public function pay_cash_advance_installment(
        int $cashAdvanceId,
        int $installmentNo,
        float $paidAmount,
        string $paymentMethod = 'CASH',
        int $companyAccountId = 0,
        string $paymentDate = '',
        string $salaryCutPeriodStart = '',
        string $salaryCutPeriodEnd = '',
        string $transferRefNo = '',
        string $notes = '',
        int $actorUserId = 0
    ): array
    {
        if ($cashAdvanceId <= 0 || $paidAmount <= 0) {
            return ['ok' => false, 'message' => 'Parameter pembayaran kasbon tidak valid.'];
        }
        $paymentMethod = strtoupper(trim($paymentMethod));
        if (!in_array($paymentMethod, ['CASH', 'TRANSFER', 'SALARY_CUT'], true)) {
            $paymentMethod = 'CASH';
        }
        $transferRefNo = trim($transferRefNo);
        $paymentDate = trim($paymentDate);
        $salaryCutPeriodStart = trim($salaryCutPeriodStart);
        $salaryCutPeriodEnd = trim($salaryCutPeriodEnd);

        $header = $this->get_cash_advance_by_id($cashAdvanceId);
        if (!$header) {
            return ['ok' => false, 'message' => 'Data kasbon tidak ditemukan.'];
        }
        if (!in_array(strtoupper((string)($header['status'] ?? 'DRAFT')), ['APPROVED', 'SETTLED'], true)) {
            return ['ok' => false, 'message' => 'Kasbon belum status APPROVED.'];
        }
        $targetAccountId = $companyAccountId > 0 ? $companyAccountId : (int)($header['company_account_id'] ?? 0);
        $salaryCutPeriod = '';
        $salaryCutDate = '';
        if ($paymentMethod === 'SALARY_CUT') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $salaryCutPeriodStart) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $salaryCutPeriodEnd)) {
                return ['ok' => false, 'message' => 'Periode potong gaji wajib diisi (tanggal mulai dan akhir).'];
            }
            $startTs = strtotime($salaryCutPeriodStart);
            $endTs = strtotime($salaryCutPeriodEnd);
            if ($startTs <= 0 || $endTs <= 0 || $startTs > $endTs) {
                return ['ok' => false, 'message' => 'Rentang periode potong gaji tidak valid.'];
            }
            if (date('Y-m', $startTs) !== date('Y-m', $endTs)) {
                return ['ok' => false, 'message' => 'Periode potong gaji harus berada pada bulan yang sama.'];
            }
            $salaryCutPeriod = date('Y-m', $startTs);
            $salaryCutDate = date('Y-m-d', $endTs);
        } else {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
                return ['ok' => false, 'message' => 'Tanggal bayar wajib diisi untuk metode tunai/transfer.'];
            }
            if ($targetAccountId <= 0) {
                return ['ok' => false, 'message' => 'Rekening sumber wajib dipilih untuk metode tunai/transfer.'];
            }
            if ($paymentMethod === 'TRANSFER' && $transferRefNo === '') {
                return ['ok' => false, 'message' => 'Ref transfer wajib diisi untuk metode Transfer.'];
            }
            if (!$this->db->table_exists('fin_company_account')) {
                return ['ok' => false, 'message' => 'Tabel rekening perusahaan belum tersedia.'];
            }
            $account = $this->db->select('id, account_type, is_active')
                ->from('fin_company_account')
                ->where('id', $targetAccountId)
                ->limit(1)
                ->get()->row_array();
            if (!$account || (int)($account['is_active'] ?? 0) !== 1) {
                return ['ok' => false, 'message' => 'Rekening sumber tidak valid atau tidak aktif.'];
            }
            if ($this->db->field_exists('account_type', 'fin_company_account')) {
                $actualType = strtoupper(trim((string)($account['account_type'] ?? '')));
                $expectedType = $paymentMethod === 'CASH' ? 'CASH' : 'BANK';
                if ($actualType !== $expectedType) {
                    return ['ok' => false, 'message' => $paymentMethod === 'CASH'
                        ? 'Metode Cair Tunai wajib memakai rekening bertipe CASH.'
                        : 'Metode Transfer wajib memakai rekening bertipe BANK.'];
                }
            }
        }
        $paymentDateValue = ($paymentMethod === 'SALARY_CUT' && $salaryCutDate !== '')
            ? $salaryCutDate
            : $paymentDate;

        $this->db->trans_start();
        $row = null;
        if ($installmentNo > 0) {
            $row = $this->db->from('pay_cash_advance_installment')
                ->where('status <>', 'PAID')
                ->where('cash_advance_id', $cashAdvanceId)
                ->where('installment_no', $installmentNo)
                ->limit(1)
                ->get()->row_array();
        }

        if (!$row) {
            $maxRow = $this->db->select('COALESCE(MAX(installment_no),0) AS max_no', false)
                ->from('pay_cash_advance_installment')
                ->where('cash_advance_id', $cashAdvanceId)
                ->get()->row_array();
            $nextNo = max(1, ((int)($maxRow['max_no'] ?? 0)) + 1);
            $outstanding = round((float)($header['outstanding_amount'] ?? 0), 2);
            if ($outstanding <= 0) {
                $this->db->trans_complete();
                return ['ok' => false, 'message' => 'Kasbon sudah lunas, tidak ada outstanding.'];
            }
            $pay = round(min($outstanding, $paidAmount), 2);
            $insertPayload = [
                'cash_advance_id' => $cashAdvanceId,
                'installment_no' => $nextNo,
                'due_period' => $paymentMethod === 'SALARY_CUT' && $salaryCutPeriod !== '' ? $salaryCutPeriod : date('Y-m', strtotime($paymentDateValue)),
                'plan_amount' => $pay,
                'paid_amount' => $pay,
                'status' => 'PAID',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($this->table_has_field('pay_cash_advance_installment', 'payment_method')) {
                $insertPayload['payment_method'] = $paymentMethod;
            }
            if ($this->table_has_field('pay_cash_advance_installment', 'company_account_id')) {
                $insertPayload['company_account_id'] = ($paymentMethod === 'TRANSFER' || $paymentMethod === 'CASH') ? ($targetAccountId > 0 ? $targetAccountId : null) : null;
            }
            if ($this->table_has_field('pay_cash_advance_installment', 'payment_date')) {
                $insertPayload['payment_date'] = $paymentDateValue;
            }
            if ($this->table_has_field('pay_cash_advance_installment', 'salary_cut_period')) {
                $insertPayload['salary_cut_period'] = $paymentMethod === 'SALARY_CUT' ? $salaryCutPeriod : null;
            }
            if ($this->table_has_field('pay_cash_advance_installment', 'salary_cut_date')) {
                $insertPayload['salary_cut_date'] = $paymentMethod === 'SALARY_CUT' ? $salaryCutDate : null;
            }
            if ($this->table_has_field('pay_cash_advance_installment', 'transfer_ref_no')) {
                $insertPayload['transfer_ref_no'] = $transferRefNo !== '' ? $transferRefNo : null;
            }
            if ($this->table_has_field('pay_cash_advance_installment', 'notes')) {
                $insertPayload['notes'] = $notes !== '' ? $notes : null;
            }
            $this->db->insert('pay_cash_advance_installment', $insertPayload);
            $installmentId = (int)$this->db->insert_id();

            if ($paymentMethod === 'SALARY_CUT') {
                $this->register_cash_advance_salary_cut($cashAdvanceId, $header, $pay, $salaryCutDate, $actorUserId);
            } elseif (in_array($paymentMethod, ['CASH', 'TRANSFER'], true) && $pay > 0) {
                $credit = $this->credit_account_and_log_mutation(
                    $targetAccountId,
                    $pay,
                    $paymentDateValue,
                    'pay_cash_advance_installment',
                    $installmentId,
                    (string)($header['advance_no'] ?? ('CA#' . $cashAdvanceId)),
                    'Pembayaran kasbon ' . (string)($header['advance_no'] ?? ('CA#' . $cashAdvanceId)) . ' cicilan #' . $nextNo,
                    $actorUserId
                );
                if (!($credit['ok'] ?? false)) {
                    $this->db->trans_rollback();
                    return ['ok' => false, 'message' => (string)($credit['message'] ?? 'Gagal mencatat mutasi pelunasan kasbon.')];
                }
            }

            $this->refresh_cash_advance_outstanding($cashAdvanceId);
            $this->db->trans_complete();
            if (!$this->db->trans_status()) {
                return ['ok' => false, 'message' => 'Gagal posting pembayaran kasbon.'];
            }
            return ['ok' => true, 'message' => 'Pembayaran kasbon berhasil diposting.'];
        }

        $plan = (float)($row['plan_amount'] ?? 0);
        $currentPaid = (float)($row['paid_amount'] ?? 0);
        $newPaid = round(min($plan, $currentPaid + $paidAmount), 2);
        $status = 'OPEN';
        if ($newPaid <= 0) {
            $status = 'OPEN';
        } elseif ($newPaid < $plan) {
            $status = 'PARTIAL';
        } else {
            $status = 'PAID';
        }

        $this->db->where('id', (int)$row['id'])->update('pay_cash_advance_installment', [
            'paid_amount' => $newPaid,
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        if ($this->table_has_field('pay_cash_advance_installment', 'payment_method')) {
            $this->db->where('id', (int)$row['id'])->update('pay_cash_advance_installment', [
                'payment_method' => $paymentMethod,
            ]);
        }
        if ($this->table_has_field('pay_cash_advance_installment', 'company_account_id') && in_array($paymentMethod, ['CASH', 'TRANSFER'], true)) {
            $this->db->where('id', (int)$row['id'])->update('pay_cash_advance_installment', [
                'company_account_id' => $targetAccountId > 0 ? $targetAccountId : null,
            ]);
        }
        if ($this->table_has_field('pay_cash_advance_installment', 'payment_date')) {
            $this->db->where('id', (int)$row['id'])->update('pay_cash_advance_installment', [
                'payment_date' => $paymentDateValue,
            ]);
        }
        if ($this->table_has_field('pay_cash_advance_installment', 'salary_cut_period') && $paymentMethod === 'SALARY_CUT') {
            $this->db->where('id', (int)$row['id'])->update('pay_cash_advance_installment', [
                'salary_cut_period' => $salaryCutPeriod,
            ]);
        }
        if ($this->table_has_field('pay_cash_advance_installment', 'salary_cut_date') && $paymentMethod === 'SALARY_CUT') {
            $this->db->where('id', (int)$row['id'])->update('pay_cash_advance_installment', [
                'salary_cut_date' => $salaryCutDate,
            ]);
        }
        if ($this->table_has_field('pay_cash_advance_installment', 'transfer_ref_no') && $transferRefNo !== '') {
            $this->db->where('id', (int)$row['id'])->update('pay_cash_advance_installment', [
                'transfer_ref_no' => $transferRefNo,
            ]);
        }
        if ($this->table_has_field('pay_cash_advance_installment', 'notes') && $notes !== '') {
            $this->db->where('id', (int)$row['id'])->update('pay_cash_advance_installment', [
                'notes' => $notes,
            ]);
        }

        $postedAmount = round(max(0, $newPaid - $currentPaid), 2);
        if ($postedAmount > 0) {
            if ($paymentMethod === 'SALARY_CUT') {
                $this->register_cash_advance_salary_cut($cashAdvanceId, $header, $postedAmount, $salaryCutDate, $actorUserId);
            } elseif (in_array($paymentMethod, ['CASH', 'TRANSFER'], true)) {
                $credit = $this->credit_account_and_log_mutation(
                    $targetAccountId,
                    $postedAmount,
                    $paymentDateValue,
                    'pay_cash_advance_installment',
                    (int)($row['id'] ?? 0),
                    (string)($header['advance_no'] ?? ('CA#' . $cashAdvanceId)),
                    'Pembayaran kasbon ' . (string)($header['advance_no'] ?? ('CA#' . $cashAdvanceId)) . ' cicilan #' . (int)($row['installment_no'] ?? $installmentNo),
                    $actorUserId
                );
                if (!($credit['ok'] ?? false)) {
                    $this->db->trans_rollback();
                    return ['ok' => false, 'message' => (string)($credit['message'] ?? 'Gagal mencatat mutasi pelunasan kasbon.')];
                }
            }
        }

        $this->refresh_cash_advance_outstanding($cashAdvanceId);
        $this->db->trans_complete();
        if (!$this->db->trans_status()) {
            return ['ok' => false, 'message' => 'Gagal simpan pembayaran kasbon.'];
        }
        return ['ok' => true, 'message' => 'Pembayaran cicilan kasbon berhasil disimpan.'];
    }

    public function void_cash_advance(int $id, string $notes = ''): array
    {
        $row = $this->get_cash_advance_by_id($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Data kasbon tidak ditemukan.'];
        }
        $currentStatus = strtoupper((string)($row['status'] ?? 'DRAFT'));
        if ($currentStatus === 'VOID') {
            return ['ok' => false, 'message' => 'Kasbon sudah VOID.'];
        }
        $hasPaid = $this->db->select('COUNT(*) AS c', false)
            ->from('pay_cash_advance_installment')
            ->where('cash_advance_id', $id)
            ->where('paid_amount >', 0)
            ->get()->row_array();
        if ((int)($hasPaid['c'] ?? 0) > 0) {
            return ['ok' => false, 'message' => 'Kasbon sudah ada pembayaran, tidak bisa di-VOID.'];
        }
        if ($this->db->table_exists('fin_account_mutation_log')) {
            $hasMutation = $this->db->select('COUNT(*) AS c', false)
                ->from('fin_account_mutation_log')
                ->where('ref_table', 'pay_cash_advance')
                ->where('ref_id', $id)
                ->where('mutation_type', 'OUT')
                ->get()->row_array();
            if ((int)($hasMutation['c'] ?? 0) > 0) {
                return ['ok' => false, 'message' => 'Kasbon sudah punya mutasi kas keluar, VOID otomatis diblok agar laporan keuangan tetap aman.'];
            }
        }

        $append = trim((string)$notes);
        $noteText = trim((string)($row['notes'] ?? ''));
        if ($append !== '') {
            $noteText = trim($noteText . ' | VOID: ' . $append);
        }
        $this->db->where('id', $id)->update('pay_cash_advance', [
            'status' => 'VOID',
            'outstanding_amount' => 0,
            'notes' => $noteText !== '' ? $noteText : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->where('cash_advance_id', $id)->delete('pay_cash_advance_installment');
        return ['ok' => true, 'message' => 'Kasbon berhasil di-VOID.'];
    }

    public function delete_cash_advance(int $id): array
    {
        $row = $this->get_cash_advance_by_id($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Data kasbon tidak ditemukan.'];
        }
        if (strtoupper((string)($row['status'] ?? 'DRAFT')) === 'SETTLED') {
            return ['ok' => false, 'message' => 'Kasbon SETTLED tidak bisa dihapus.'];
        }
        $hasPaid = $this->db->select('COUNT(*) AS c', false)
            ->from('pay_cash_advance_installment')
            ->where('cash_advance_id', $id)
            ->where('paid_amount >', 0)
            ->get()->row_array();
        if ((int)($hasPaid['c'] ?? 0) > 0) {
            return ['ok' => false, 'message' => 'Kasbon sudah ada pembayaran, tidak bisa dihapus.'];
        }

        $this->db->trans_start();
        $this->db->where('cash_advance_id', $id)->delete('pay_cash_advance_installment');
        $this->db->where('id', $id)->delete('pay_cash_advance');
        $this->db->trans_complete();
        if (!$this->db->trans_status()) {
            return ['ok' => false, 'message' => 'Gagal menghapus kasbon.'];
        }
        return ['ok' => true, 'message' => 'Kasbon berhasil dihapus.'];
    }

    public function get_bonus_workspace_summary(string $month = ''): array
    {
        $month = preg_match('/^\d{4}-\d{2}$/', $month) ? $month : date('Y-m');
        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));

        $summary = [
            'month' => $month,
            'config_count' => 0,
            'active_rule_count' => 0,
            'target_linked_rule_count' => 0,
            'weight_rule_count' => 0,
            'penalty_type_count' => 0,
            'penalty_event_count' => 0,
            'pending_peer_review_count' => 0,
            'approved_peer_review_count' => 0,
            'pool_count' => 0,
            'approved_pool_count' => 0,
            'monthly_summary_count' => 0,
            'monthly_final_amount' => 0.0,
        ];

        if ($this->db->table_exists('pay_bonus_config')) {
            $summary['config_count'] = (int)$this->db->from('pay_bonus_config')->count_all_results();
        }
        if ($this->db->table_exists('pay_bonus_rule')) {
            $summary['active_rule_count'] = (int)$this->db->from('pay_bonus_rule')->where('is_active', 1)->count_all_results();
            if ($this->table_has_field('pay_bonus_rule', 'linked_target_plan_id')) {
                $summary['target_linked_rule_count'] = (int)$this->db->from('pay_bonus_rule')->where('linked_target_plan_id IS NOT NULL', null, false)->count_all_results();
            }
        }
        if ($this->db->table_exists('pay_bonus_weight_rule')) {
            $summary['weight_rule_count'] = (int)$this->db->from('pay_bonus_weight_rule')->where('is_active', 1)->count_all_results();
        }
        if ($this->db->table_exists('pay_bonus_penalty_type')) {
            $summary['penalty_type_count'] = (int)$this->db->from('pay_bonus_penalty_type')->where('is_active', 1)->count_all_results();
        }
        if ($this->db->table_exists('pay_bonus_penalty_event')) {
            $summary['penalty_event_count'] = (int)$this->db->from('pay_bonus_penalty_event')
                ->where('penalty_date >=', $startDate)
                ->where('penalty_date <=', $endDate)
                ->where('status <>', 'VOID')
                ->count_all_results();
        }
        if ($this->db->table_exists('perf_peer_feedback')) {
            $summary['pending_peer_review_count'] = (int)$this->db->from('perf_peer_feedback')
                ->where('feedback_date >=', $startDate)
                ->where('feedback_date <=', $endDate)
                ->where('status', 'SUBMITTED')
                ->count_all_results();
            $summary['approved_peer_review_count'] = (int)$this->db->from('perf_peer_feedback')
                ->where('feedback_date >=', $startDate)
                ->where('feedback_date <=', $endDate)
                ->where('status', 'APPROVED')
                ->count_all_results();
        }
        if ($this->db->table_exists('pay_bonus_pool_daily')) {
            $summary['pool_count'] = (int)$this->db->from('pay_bonus_pool_daily')
                ->where('bonus_date >=', $startDate)
                ->where('bonus_date <=', $endDate)
                ->count_all_results();
            $summary['approved_pool_count'] = (int)$this->db->from('pay_bonus_pool_daily')
                ->where('bonus_date >=', $startDate)
                ->where('bonus_date <=', $endDate)
                ->where('approval_status', 'APPROVED')
                ->count_all_results();
        }
        if ($this->db->table_exists('pay_bonus_monthly_summary')) {
            $row = $this->db->select('COUNT(*) AS total_rows, COALESCE(SUM(total_final_amount),0) AS total_amount', false)
                ->from('pay_bonus_monthly_summary')
                ->where('summary_month', $month)
                ->get()->row_array();
            $summary['monthly_summary_count'] = (int)($row['total_rows'] ?? 0);
            $summary['monthly_final_amount'] = round((float)($row['total_amount'] ?? 0), 2);
        }

        return $summary;
    }

    private function build_bonus_rule_query(string $q = ''): CI_DB_query_builder
    {
        if (!$this->db->table_exists('pay_bonus_rule')) {
            return $this->db;
        }

        $db = $this->db->select("
                r.*,
                c.config_name,
                o.outlet_name,
                d.division_name,
                " . $this->target_plan_name_expr('tp') . " AS target_plan_name,
                " . ($this->db->table_exists('fin_target_plan') ? $this->target_plan_name_expr('tdp') : "'-'") . " AS daily_target_plan_name
            ", false)
            ->from('pay_bonus_rule r')
            ->join('pay_bonus_config c', 'c.id = r.config_id', 'left');

        if ($this->db->table_exists('pos_outlet')) {
            $db->join('pos_outlet o', 'o.id = r.outlet_id', 'left');
        }
        $db->join('org_division d', 'd.id = r.division_id', 'left');
        if ($this->db->table_exists('fin_target_plan')) {
            $db->join('fin_target_plan tp', 'tp.id = r.linked_target_plan_id', 'left');
            if ($this->table_has_field('pay_bonus_rule', 'daily_target_plan_id')) {
                $db->join('fin_target_plan tdp', 'tdp.id = r.daily_target_plan_id', 'left');
            }
        }

        if ($q !== '') {
            $db->group_start()
                ->like('r.rule_name', $q)
                ->or_like('r.rule_code', $q)
                ->or_like('c.config_name', $q);
            if ($this->db->table_exists('pos_outlet')) {
                $db->or_like('o.outlet_name', $q);
            }
            $db->or_like('d.division_name', $q);
            if ($this->db->table_exists('fin_target_plan')) {
                if ($this->table_has_field('fin_target_plan', 'target_name')) {
                    $db->or_like('tp.target_name', $q);
                    if ($this->table_has_field('pay_bonus_rule', 'daily_target_plan_id')) {
                        $db->or_like('tdp.target_name', $q);
                    }
                } elseif ($this->table_has_field('fin_target_plan', 'plan_name')) {
                    $db->or_like('tp.plan_name', $q);
                    if ($this->table_has_field('pay_bonus_rule', 'daily_target_plan_id')) {
                        $db->or_like('tdp.plan_name', $q);
                    }
                }
            }
            $db->group_end();
        }

        return $db;
    }

    private function build_bonus_weight_rule_query(string $q = ''): CI_DB_query_builder
    {
        if (!$this->db->table_exists('pay_bonus_weight_rule')) {
            return $this->db;
        }

        $scopeLabelSql = "
            CASE
              WHEN w.weight_scope = 'DIVISION' THEN d.division_name
              WHEN w.weight_scope = 'POSITION' THEN p.position_name
              WHEN w.weight_scope = 'EMPLOYEE' THEN e.employee_name
              WHEN w.weight_scope = 'SHIFT' THEN CONCAT(COALESCE(s.shift_code,''), ' - ', COALESCE(s.shift_name,''))
              ELSE CONCAT('Scope #', w.scope_id)
            END
        ";

        $targetFrequencyExpr = $this->table_has_field('pay_bonus_weight_rule', 'target_frequency')
            ? "COALESCE(w.target_frequency, 'ALL')"
            : "'ALL'";

        $db = $this->db->select("
                w.*,
                r.rule_name,
                r.rule_code,
                {$targetFrequencyExpr} AS target_frequency_label,
                CASE
                  WHEN w.rule_id IS NULL THEN 'GLOBAL'
                  ELSE 'RULE'
                END AS binding_mode,
                {$scopeLabelSql} AS scope_label
            ", false)
            ->from('pay_bonus_weight_rule w')
            ->join('pay_bonus_rule r', 'r.id = w.rule_id', 'left')
            ->join('org_division d', "w.weight_scope = 'DIVISION' AND d.id = w.scope_id", 'left', false)
            ->join('org_position p', "w.weight_scope = 'POSITION' AND p.id = w.scope_id", 'left', false)
            ->join('org_employee e', "w.weight_scope = 'EMPLOYEE' AND e.id = w.scope_id", 'left', false)
            ->join('att_shift s', "w.weight_scope = 'SHIFT' AND s.id = w.scope_id", 'left', false);

        if ($q !== '') {
            $db->group_start()
                ->like('r.rule_name', $q)
                ->or_like('r.rule_code', $q)
                ->or_like('w.weight_scope', strtoupper($q))
                ->or_like($targetFrequencyExpr, strtoupper($q), 'both', false)
                ->or_like($scopeLabelSql, $q, 'both', false)
                ->group_end();
        }

        return $db;
    }

    public function count_bonus_weight_rules(string $q = ''): int
    {
        if (!$this->db->table_exists('pay_bonus_weight_rule')) {
            return 0;
        }

        $this->build_bonus_weight_rule_query($q);
        return (int)$this->db->count_all_results();
    }

    public function list_bonus_weight_rules(string $q = '', int $limit = 25, int $offset = 0): array
    {
        if (!$this->db->table_exists('pay_bonus_weight_rule')) {
            return [];
        }

        return $this->build_bonus_weight_rule_query($q)
            ->order_by('w.is_active', 'DESC')
            ->order_by('w.rule_id IS NULL', 'DESC', false)
            ->order_by('w.rule_id', 'ASC')
            ->order_by('target_frequency_label', 'ASC')
            ->order_by('w.weight_scope', 'ASC')
            ->order_by('w.scope_id', 'ASC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    public function count_bonus_rules(string $q = ''): int
    {
        if (!$this->db->table_exists('pay_bonus_rule')) {
            return 0;
        }

        $this->build_bonus_rule_query($q);
        return (int)$this->db->count_all_results();
    }

    public function list_bonus_rules(string $q = '', int $limit = 25, int $offset = 0): array
    {
        if (!$this->db->table_exists('pay_bonus_rule')) {
            return [];
        }

        $db = $this->build_bonus_rule_query($q);

        return $db->order_by('r.is_active', 'DESC')
            ->order_by('r.rule_name', 'ASC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_bonus_penalty_type_query(string $q = ''): CI_DB_query_builder
    {
        if (!$this->db->table_exists('pay_bonus_penalty_type')) {
            return $this->db;
        }

        $db = $this->db->from('pay_bonus_penalty_type');
        if ($q !== '') {
            $db->group_start()
                ->like('penalty_name', $q)
                ->or_like('penalty_code', $q)
                ->or_like('category', strtoupper($q))
                ->group_end();
        }
        return $db;
    }

    public function count_bonus_penalty_types(string $q = ''): int
    {
        if (!$this->db->table_exists('pay_bonus_penalty_type')) {
            return 0;
        }

        $this->build_bonus_penalty_type_query($q);
        return (int)$this->db->count_all_results();
    }

    public function list_bonus_penalty_types(string $q = '', int $limit = 25, int $offset = 0): array
    {
        if (!$this->db->table_exists('pay_bonus_penalty_type')) {
            return [];
        }

        return $this->build_bonus_penalty_type_query($q)
            ->order_by('is_active', 'DESC')
            ->order_by('sort_order', 'ASC')
            ->order_by('penalty_name', 'ASC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_bonus_penalty_event_query(string $month = '', string $q = ''): CI_DB_query_builder
    {
        if (!$this->db->table_exists('pay_bonus_penalty_event')) {
            return $this->db;
        }

        $month = preg_match('/^\d{4}-\d{2}$/', $month) ? $month : date('Y-m');
        $db = $this->db->select("
                pe.*,
                pt.penalty_name,
                pt.penalty_code,
                br.rule_name,
                e.employee_name,
                d.division_name,
                s.shift_code,
                s.shift_name
            ", false)
            ->from('pay_bonus_penalty_event pe')
            ->join('pay_bonus_penalty_type pt', 'pt.id = pe.penalty_type_id', 'left')
            ->join('pay_bonus_rule br', 'br.id = pe.rule_id', 'left')
            ->join('org_employee e', 'e.id = pe.employee_id', 'left')
            ->join('org_division d', 'd.id = pe.division_id', 'left')
            ->join('att_shift s', 's.id = pe.shift_id', 'left')
            ->where("DATE_FORMAT(pe.penalty_date, '%Y-%m') =", $month)
            ->where('pe.status <>', 'VOID');

        if ($q !== '') {
            $db->group_start()
                ->like('pt.penalty_name', $q)
                ->or_like('pt.penalty_code', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('d.division_name', $q)
                ->or_like('pe.reason_text', $q)
                ->group_end();
        }

        return $db;
    }

    public function count_bonus_penalty_events(string $month = '', string $q = ''): int
    {
        if (!$this->db->table_exists('pay_bonus_penalty_event')) {
            return 0;
        }

        $this->build_bonus_penalty_event_query($month, $q);
        return (int)$this->db->count_all_results();
    }

    public function list_bonus_penalty_events(string $month = '', string $q = '', int $limit = 25, int $offset = 0): array
    {
        if (!$this->db->table_exists('pay_bonus_penalty_event')) {
            return [];
        }

        return $this->build_bonus_penalty_event_query($month, $q)
            ->order_by('pe.penalty_date', 'DESC')
            ->order_by('pe.id', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_bonus_pool_query(string $month = '', string $q = ''): CI_DB_query_builder
    {
        if (!$this->db->table_exists('pay_bonus_pool_daily')) {
            return $this->db;
        }

        $month = preg_match('/^\d{4}-\d{2}$/', $month) ? $month : date('Y-m');

        $db = $this->db->select("
                p.*,
                c.config_name,
                r.rule_name,
                " . $this->target_plan_name_expr('tp') . " AS target_plan_name,
                o.outlet_name,
                d.division_name
            ", false)
            ->from('pay_bonus_pool_daily p')
            ->join('pay_bonus_config c', 'c.id = p.config_id', 'left')
            ->join('pay_bonus_rule r', 'r.id = p.rule_id', 'left');

        if ($this->db->table_exists('fin_target_plan')) {
            $db->join('fin_target_plan tp', 'tp.id = p.target_plan_id', 'left');
        }

        if ($this->db->table_exists('pos_outlet')) {
            $db->join('pos_outlet o', 'o.id = p.outlet_id', 'left');
        }
        $db->join('org_division d', 'd.id = p.division_id', 'left');

        $db->where("DATE_FORMAT(p.bonus_date, '%Y-%m') =", $month);
        if ($q !== '') {
            $db->group_start()
                ->like('r.rule_name', $q)
                ->or_like('c.config_name', $q)
                ->or_like('p.approval_status', strtoupper($q));
            if ($this->db->table_exists('fin_target_plan')) {
                if ($this->table_has_field('fin_target_plan', 'target_name')) {
                    $db->or_like('tp.target_name', $q);
                } elseif ($this->table_has_field('fin_target_plan', 'plan_name')) {
                    $db->or_like('tp.plan_name', $q);
                } elseif ($this->table_has_field('fin_target_plan', 'name')) {
                    $db->or_like('tp.name', $q);
                }
            }
            if ($this->db->table_exists('pos_outlet')) {
                $db->or_like('o.outlet_name', $q);
            }
            $db->or_like('d.division_name', $q)
                ->group_end();
        }
        return $db;
    }

    public function count_bonus_recent_pools(string $month = '', string $q = ''): int
    {
        if (!$this->db->table_exists('pay_bonus_pool_daily')) {
            return 0;
        }

        $this->build_bonus_pool_query($month, $q);
        return (int)$this->db->count_all_results();
    }

    public function list_bonus_recent_pools(string $month = '', string $q = '', int $limit = 25, int $offset = 0): array
    {
        if (!$this->db->table_exists('pay_bonus_pool_daily')) {
            return [];
        }

        return $this->build_bonus_pool_query($month, $q)
            ->order_by('p.bonus_date', 'DESC')
            ->order_by('p.id', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_bonus_service_metric_query(string $month = '', string $q = ''): CI_DB_query_builder
    {
        if (!$this->db->table_exists('pay_bonus_service_metric_daily')) {
            return $this->db;
        }

        $month = preg_match('/^\d{4}-\d{2}$/', $month) ? $month : date('Y-m');

        $db = $this->db->select("
                sm.*,
                o.outlet_name,
                d.division_name,
                s.shift_code,
                s.shift_name
            ", false)
            ->from('pay_bonus_service_metric_daily sm');

        if ($this->db->table_exists('pos_outlet')) {
            $db->join('pos_outlet o', 'o.id = sm.outlet_id', 'left');
        }
        $db->join('org_division d', 'd.id = sm.division_id', 'left')
            ->join('att_shift s', 's.id = sm.shift_id', 'left')
            ->where("DATE_FORMAT(sm.metric_date, '%Y-%m') =", $month);

        if ($q !== '') {
            $db->group_start();
            if ($this->db->table_exists('pos_outlet')) {
                $db->like('o.outlet_name', $q)
                    ->or_like('d.division_name', $q);
            } else {
                $db->like('d.division_name', $q);
            }
            $db->or_like('s.shift_code', $q)
                ->or_like('s.shift_name', $q)
                ->or_like('sm.source_notes', $q)
                ->group_end();
        }

        return $db;
    }

    public function count_bonus_service_metrics(string $month = '', string $q = ''): int
    {
        if (!$this->db->table_exists('pay_bonus_service_metric_daily')) {
            return 0;
        }

        $this->build_bonus_service_metric_query($month, $q);
        return (int)$this->db->count_all_results();
    }

    public function list_bonus_service_metrics(string $month = '', string $q = '', int $limit = 25, int $offset = 0): array
    {
        if (!$this->db->table_exists('pay_bonus_service_metric_daily')) {
            return [];
        }

        return $this->build_bonus_service_metric_query($month, $q)
            ->order_by('sm.metric_date', 'DESC')
            ->order_by('sm.outlet_id IS NULL', 'ASC', false)
            ->order_by('o.outlet_name', 'ASC')
            ->order_by('sm.division_id IS NULL', 'ASC', false)
            ->order_by('d.division_name', 'ASC')
            ->order_by('sm.shift_id IS NULL', 'ASC', false)
            ->order_by('s.shift_code', 'ASC')
            ->order_by('sm.id', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_bonus_monthly_summary_query(string $month = '', string $q = ''): CI_DB_query_builder
    {
        if (!$this->db->table_exists('pay_bonus_monthly_summary')) {
            return $this->db;
        }

        $month = preg_match('/^\d{4}-\d{2}$/', $month) ? $month : date('Y-m');

        $db = $this->db->select("
                ms.*,
                c.config_name,
                r.rule_name,
                e.employee_code,
                e.employee_name,
                o.outlet_name,
                d.division_name
            ", false)
            ->from('pay_bonus_monthly_summary ms')
            ->join('pay_bonus_config c', 'c.id = ms.config_id', 'left')
            ->join('pay_bonus_rule r', 'r.id = ms.rule_id', 'left')
            ->join('org_employee e', 'e.id = ms.employee_id', 'left');

        if ($this->db->table_exists('pos_outlet')) {
            $db->join('pos_outlet o', 'o.id = ms.outlet_id', 'left');
        }
        $db->join('org_division d', 'd.id = ms.division_id', 'left')
            ->where('ms.summary_month', $month);

        if ($q !== '') {
            $db->group_start()
                ->like('e.employee_name', $q)
                ->or_like('e.employee_code', $q)
                ->or_like('c.config_name', $q)
                ->or_like('r.rule_name', $q);
            if ($this->db->table_exists('pos_outlet')) {
                $db->or_like('o.outlet_name', $q);
            }
            $db->or_like('d.division_name', $q)
                ->or_like('ms.payout_status', strtoupper($q))
                ->or_like('ms.notes', $q)
                ->group_end();
        }

        return $db;
    }

    public function count_bonus_monthly_summaries(string $month = '', string $q = ''): int
    {
        if (!$this->db->table_exists('pay_bonus_monthly_summary')) {
            return 0;
        }

        $this->build_bonus_monthly_summary_query($month, $q);
        return (int)$this->db->count_all_results();
    }

    public function list_bonus_monthly_summaries(string $month = '', string $q = '', int $limit = 25, int $offset = 0): array
    {
        if (!$this->db->table_exists('pay_bonus_monthly_summary')) {
            return [];
        }

        return $this->build_bonus_monthly_summary_query($month, $q)
            ->order_by('ms.total_final_amount', 'DESC')
            ->order_by('e.employee_name', 'ASC')
            ->order_by('ms.id', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_pending_peer_feedback_query(string $q = ''): CI_DB_query_builder
    {
        if (!$this->db->table_exists('perf_peer_feedback')) {
            return $this->db;
        }

        $db = $this->db->select("
                pf.*,
                ef.employee_code AS from_employee_code,
                ef.employee_name AS from_employee_name,
                et.employee_code AS to_employee_code,
                et.employee_name AS to_employee_name,
                d.division_name,
                s.shift_code,
                s.shift_name
            ", false)
            ->from('perf_peer_feedback pf')
            ->join('org_employee ef', 'ef.id = pf.from_employee_id', 'left')
            ->join('org_employee et', 'et.id = pf.to_employee_id', 'left')
            ->join('org_division d', 'd.id = et.division_id', 'left')
            ->join('att_shift s', 's.id = pf.shift_id', 'left')
            ->where('pf.status', 'SUBMITTED');

        if ($q !== '') {
            $db->group_start()
                ->like('ef.employee_name', $q)
                ->or_like('et.employee_name', $q)
                ->or_like('d.division_name', $q)
                ->or_like('pf.reason_text', $q)
                ->group_end();
        }

        return $db;
    }

    public function count_pending_peer_feedback(string $q = ''): int
    {
        if (!$this->db->table_exists('perf_peer_feedback')) {
            return 0;
        }

        $this->build_pending_peer_feedback_query($q);
        return (int)$this->db->count_all_results();
    }

    public function list_pending_peer_feedback(string $q = '', int $limit = 25, int $offset = 0): array
    {
        if (!$this->db->table_exists('perf_peer_feedback')) {
            return [];
        }

        return $this->build_pending_peer_feedback_query($q)
            ->order_by('pf.feedback_date', 'DESC')
            ->order_by('pf.id', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    public function get_employee_bonus_overview(int $employeeId, string $month = ''): array
    {
        $month = preg_match('/^\d{4}-\d{2}$/', $month) ? $month : date('Y-m');
        $summary = [
            'summary_month' => $month,
            'total_raw_amount' => 0.0,
            'total_penalty_amount' => 0.0,
            'total_final_amount' => 0.0,
            'estimated_raw_amount' => 0.0,
            'estimated_penalty_amount' => 0.0,
            'estimated_final_amount' => 0.0,
            'total_raw_point' => 0.0,
            'total_penalty_point' => 0.0,
            'total_final_point' => 0.0,
            'estimated_raw_point' => 0.0,
            'estimated_penalty_point' => 0.0,
            'estimated_final_point' => 0.0,
            'peer_avg_star' => 0.0,
            'service_avg_score' => 0.0,
            'target_avg_score' => 0.0,
            'late_count' => 0,
            'alpha_count' => 0,
            'ph_taken_count' => 0,
            'payout_status' => 'DRAFT',
            'is_published' => 0,
        ];

        if ($employeeId <= 0) {
            return $summary;
        }

        if ($this->db->table_exists('pay_bonus_monthly_summary')) {
            $row = $this->db->select("
                    summary_month,
                    COALESCE(SUM(total_raw_amount),0) AS total_raw_amount,
                    COALESCE(SUM(total_penalty_amount),0) AS total_penalty_amount,
                    COALESCE(SUM(total_final_amount),0) AS total_final_amount,
                    COALESCE(SUM(total_raw_point),0) AS total_raw_point,
                    COALESCE(SUM(total_penalty_point),0) AS total_penalty_point,
                    COALESCE(SUM(total_final_point),0) AS total_final_point,
                    COALESCE(AVG(peer_avg_star),0) AS peer_avg_star,
                    COALESCE(AVG(service_avg_score),0) AS service_avg_score,
                    COALESCE(AVG(target_avg_score),0) AS target_avg_score,
                    COALESCE(SUM(late_count),0) AS late_count,
                    COALESCE(SUM(alpha_count),0) AS alpha_count,
                    COALESCE(SUM(ph_taken_count),0) AS ph_taken_count,
                    MAX(CASE WHEN payout_status = 'POSTED' THEN 3 WHEN payout_status = 'APPROVED' THEN 2 WHEN payout_status = 'DRAFT' THEN 1 ELSE 0 END) AS payout_rank,
                    COUNT(*) AS total_rows
                ", false)
                ->from('pay_bonus_monthly_summary')
                ->where('employee_id', $employeeId)
                ->where('summary_month', $month)
                ->get()->row_array();
            if (!empty($row['total_rows'])) {
                $row['payout_status'] = ((int)($row['payout_rank'] ?? 0) >= 3)
                    ? 'POSTED'
                    : (((int)($row['payout_rank'] ?? 0) >= 2) ? 'APPROVED' : 'DRAFT');
                $row['is_published'] = in_array(strtoupper((string)($row['payout_status'] ?? 'DRAFT')), ['APPROVED', 'POSTED'], true) ? 1 : 0;
                $summary = array_merge($summary, $row);
            }
        }

        if ($this->db->table_exists('pay_bonus_employee_daily')) {
            $startDate = $month . '-01';
            $endDate = date('Y-m-t', strtotime($startDate));
            $estimateRoll = $this->db->select("
                    COALESCE(SUM(raw_amount),0) AS estimated_raw_amount,
                    COALESCE(SUM(penalty_amount),0) AS estimated_penalty_amount,
                    COALESCE(SUM(final_amount),0) AS estimated_final_amount,
                    COALESCE(SUM(raw_point),0) AS estimated_raw_point,
                    COALESCE(SUM(penalty_point),0) AS estimated_penalty_point,
                    COALESCE(SUM(final_point),0) AS estimated_final_point
                ", false)
                ->from('pay_bonus_employee_daily')
                ->where('employee_id', $employeeId)
                ->where('attendance_date >=', $startDate)
                ->where('attendance_date <=', $endDate)
                ->where_in('approval_status', ['DRAFT', 'APPROVED'])
                ->get()->row_array();
            if ($estimateRoll) {
                $summary = array_merge($summary, $estimateRoll);
            }
            $roll = $this->db->select("
                    COALESCE(SUM(raw_amount),0) AS total_raw_amount,
                    COALESCE(SUM(penalty_amount),0) AS total_penalty_amount,
                    COALESCE(SUM(final_amount),0) AS total_final_amount,
                    COALESCE(SUM(raw_point),0) AS total_raw_point,
                    COALESCE(SUM(penalty_point),0) AS total_penalty_point,
                    COALESCE(SUM(final_point),0) AS total_final_point
                ", false)
                ->from('pay_bonus_employee_daily')
                ->where('employee_id', $employeeId)
                ->where('attendance_date >=', $startDate)
                ->where('attendance_date <=', $endDate)
                ->where('approval_status', 'APPROVED')
                ->get()->row_array();
            if ($roll) {
                $summary = array_merge($summary, $roll);
            }
            $summary['is_published'] = ((float)($summary['total_final_amount'] ?? 0) > 0) ? 1 : (int)($summary['is_published'] ?? 0);
            if ((int)($summary['is_published'] ?? 0) === 1 && strtoupper((string)($summary['payout_status'] ?? 'DRAFT')) === 'DRAFT') {
                $summary['payout_status'] = 'APPROVED';
            }
        }

        if ($this->db->table_exists('perf_peer_feedback')) {
            $peer = $this->db->select('COALESCE(AVG(star_rating),0) AS avg_star', false)
                ->from('perf_peer_feedback')
                ->where('to_employee_id', $employeeId)
                ->where("DATE_FORMAT(feedback_date, '%Y-%m') =", $month)
                ->where('status', 'APPROVED')
                ->get()->row_array();
            $summary['peer_avg_star'] = round((float)($peer['avg_star'] ?? 0), 2);
        }

        return $summary;
    }

    public function count_my_bonus_daily_rows(int $employeeId, string $month = '', string $q = '', bool $publishedOnly = true): int
    {
        if ($employeeId <= 0 || !$this->db->table_exists('pay_bonus_employee_daily')) {
            return 0;
        }

        $this->build_my_bonus_daily_query($employeeId, $month, $q, $publishedOnly);
        return (int)$this->db->count_all_results();
    }

    private function build_my_bonus_daily_query(int $employeeId, string $month = '', string $q = '', bool $publishedOnly = true): CI_DB_query_builder
    {
        if ($employeeId <= 0 || !$this->db->table_exists('pay_bonus_employee_daily')) {
            return $this->db;
        }

        $month = preg_match('/^\d{4}-\d{2}$/', $month) ? $month : date('Y-m');

        $db = $this->db->select("
                ed.*,
                p.bonus_date,
                r.rule_name,
                s.shift_code,
                s.shift_name
            ", false)
            ->from('pay_bonus_employee_daily ed')
            ->join('pay_bonus_pool_daily p', 'p.id = ed.pool_id', 'left')
            ->join('pay_bonus_rule r', 'r.id = p.rule_id', 'left')
            ->join('att_shift s', 's.id = ed.shift_id', 'left')
            ->where('ed.employee_id', $employeeId)
            ->where("DATE_FORMAT(ed.attendance_date, '%Y-%m') =", $month);

        if ($publishedOnly) {
            $db->where_in('ed.approval_status', ['APPROVED']);
        }
        if ($q !== '') {
            $db->group_start()
                ->like('r.rule_name', $q)
                ->or_like('s.shift_code', $q)
                ->or_like('s.shift_name', $q)
                ->or_like('ed.attendance_status', strtoupper($q))
                ->group_end();
        }

        return $db;
    }

    public function list_my_bonus_daily_rows(int $employeeId, string $month = '', string $q = '', int $limit = 25, int $offset = 0, bool $publishedOnly = true): array
    {
        if ($employeeId <= 0 || !$this->db->table_exists('pay_bonus_employee_daily')) {
            return [];
        }

        return $this->build_my_bonus_daily_query($employeeId, $month, $q, $publishedOnly)
            ->order_by('ed.attendance_date', 'DESC')
            ->order_by('ed.id', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    public function get_pending_peer_feedback_targets(int $employeeId, string $date): array
    {
        if ($employeeId <= 0 || $date === '' || !$this->db->table_exists('att_daily')) {
            return [];
        }

        $targets = $this->db->select("
                ad.employee_id,
                e.employee_code,
                e.employee_name,
                d.division_name,
                p.position_name,
                ad.shift_id,
                s.shift_code,
                s.shift_name,
                ad.attendance_status
            ", false)
            ->from('att_daily ad')
            ->join('org_employee e', 'e.id = ad.employee_id', 'inner')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->join('att_shift s', 's.id = ad.shift_id', 'left')
            ->where('ad.attendance_date', $date)
            ->where('ad.employee_id <>', $employeeId)
            ->where_in('ad.attendance_status', ['PRESENT', 'LATE', 'HOLIDAY'])
            ->order_by('e.employee_name', 'ASC')
            ->get()->result_array();

        if (empty($targets) || !$this->db->table_exists('perf_peer_feedback')) {
            return $targets;
        }

        $ratedRows = $this->db->select('to_employee_id')
            ->from('perf_peer_feedback')
            ->where('from_employee_id', $employeeId)
            ->where('feedback_date', $date)
            ->where('status <>', 'VOID')
            ->get()->result_array();
        $ratedMap = [];
        foreach ($ratedRows as $ratedRow) {
            $ratedMap[(int)($ratedRow['to_employee_id'] ?? 0)] = true;
        }

        $pending = [];
        foreach ($targets as $target) {
            $targetEmployeeId = (int)($target['employee_id'] ?? 0);
            if ($targetEmployeeId > 0 && empty($ratedMap[$targetEmployeeId])) {
                $pending[] = $target;
            }
        }

        return $pending;
    }

    private function build_my_peer_feedback_history_query(int $employeeId, string $month = '', string $q = ''): CI_DB_query_builder
    {
        if ($employeeId <= 0 || !$this->db->table_exists('perf_peer_feedback')) {
            return $this->db;
        }

        $month = preg_match('/^\d{4}-\d{2}$/', $month) ? $month : date('Y-m');

        $db = $this->db->select("
                pf.*,
                ef.employee_name AS from_employee_name,
                et.employee_name AS to_employee_name
            ", false)
            ->from('perf_peer_feedback pf')
            ->join('org_employee ef', 'ef.id = pf.from_employee_id', 'left')
            ->join('org_employee et', 'et.id = pf.to_employee_id', 'left')
            ->group_start()
                ->where('pf.from_employee_id', $employeeId)
                ->or_where('pf.to_employee_id', $employeeId)
            ->group_end()
            ->where("DATE_FORMAT(pf.feedback_date, '%Y-%m') =", $month);

        if ($q !== '') {
            $db->group_start()
                ->like('ef.employee_name', $q)
                ->or_like('et.employee_name', $q)
                ->or_like('pf.reason_text', $q)
                ->group_end();
        }

        return $db;
    }

    public function count_my_peer_feedback_history(int $employeeId, string $month = '', string $q = ''): int
    {
        if ($employeeId <= 0 || !$this->db->table_exists('perf_peer_feedback')) {
            return 0;
        }

        $this->build_my_peer_feedback_history_query($employeeId, $month, $q);
        return (int)$this->db->count_all_results();
    }

    public function list_my_peer_feedback_history(int $employeeId, string $month = '', string $q = '', int $limit = 25, int $offset = 0): array
    {
        if ($employeeId <= 0 || !$this->db->table_exists('perf_peer_feedback')) {
            return [];
        }

        return $this->build_my_peer_feedback_history_query($employeeId, $month, $q)
            ->order_by('pf.feedback_date', 'DESC')
            ->order_by('pf.id', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    public function list_bonus_config_options(): array
    {
        if (!$this->db->table_exists('pay_bonus_config')) {
            return [];
        }

        return $this->db->select('id, config_code, config_name, status')
            ->from('pay_bonus_config')
            ->where_in('status', ['ACTIVE', 'DRAFT'])
            ->order_by('status = "ACTIVE"', 'DESC', false)
            ->order_by('config_name', 'ASC')
            ->get()->result_array();
    }

    private function build_bonus_config_query(string $q = ''): CI_DB_query_builder
    {
        if (!$this->db->table_exists('pay_bonus_config')) {
            return $this->db;
        }

        $db = $this->db->select('c.*')
            ->from('pay_bonus_config c');

        if ($q !== '') {
            $db->group_start()
                ->like('c.config_name', $q)
                ->or_like('c.config_code', strtoupper($q))
                ->or_like('c.description', $q)
                ->or_like('c.distribution_scope', strtoupper($q))
                ->or_like('c.status', strtoupper($q))
                ->group_end();
        }

        return $db;
    }

    public function count_bonus_configs(string $q = ''): int
    {
        if (!$this->db->table_exists('pay_bonus_config')) {
            return 0;
        }

        $this->build_bonus_config_query($q);
        return (int)$this->db->count_all_results();
    }

    public function list_bonus_configs(string $q = '', int $limit = 25, int $offset = 0): array
    {
        if (!$this->db->table_exists('pay_bonus_config')) {
            return [];
        }

        return $this->build_bonus_config_query($q)
            ->order_by('c.status = "ACTIVE"', 'DESC', false)
            ->order_by('c.config_name', 'ASC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    public function save_bonus_config(array $payload, int $actorUserId = 0): array
    {
        if (!$this->db->table_exists('pay_bonus_config')) {
            return ['ok' => false, 'message' => 'Tabel kebijakan bonus belum tersedia. Jalankan SQL foundation bonus dulu.'];
        }

        $id = (int)($payload['id'] ?? 0);
        $configName = trim((string)($payload['config_name'] ?? ''));
        if ($configName === '') {
            return ['ok' => false, 'message' => 'Nama kebijakan bonus wajib diisi.'];
        }

        $configCode = strtoupper(trim((string)($payload['config_code'] ?? '')));
        if ($configCode === '') {
            $slug = preg_replace('/[^A-Z0-9]+/', '-', strtoupper($configName));
            $slug = trim((string)$slug, '-');
            if ($slug === '') {
                $slug = 'BONUS-CONFIG';
            }
            $configCode = substr($slug, 0, 40);
        }

        $distributionScope = strtoupper(trim((string)($payload['distribution_scope'] ?? 'GLOBAL')));
        if (!in_array($distributionScope, ['GLOBAL', 'OUTLET', 'DIVISION'], true)) {
            $distributionScope = 'GLOBAL';
        }

        $poolSourceMode = strtoupper(trim((string)($payload['pool_source_mode'] ?? 'TARGET_LINKED')));
        if (!in_array($poolSourceMode, ['FIXED', 'PERCENT_REVENUE', 'PERCENT_PROFIT', 'TARGET_LINKED', 'MANUAL'], true)) {
            $poolSourceMode = 'TARGET_LINKED';
        }

        $status = strtoupper(trim((string)($payload['status'] ?? 'ACTIVE')));
        if (!in_array($status, ['DRAFT', 'ACTIVE', 'INACTIVE'], true)) {
            $status = 'ACTIVE';
        }

        $pointPenaltyMode = 'PERCENT_SHARE';
        if ($this->table_has_field('pay_bonus_config', 'point_penalty_currency_mode')) {
            $pointPenaltyMode = strtoupper(trim((string)($payload['point_penalty_currency_mode'] ?? 'PERCENT_SHARE')));
            if (!in_array($pointPenaltyMode, ['NONE', 'PERCENT_SHARE', 'FIXED_RUPIAH'], true)) {
                $pointPenaltyMode = 'PERCENT_SHARE';
            }
        }

        $dbPayload = [
            'config_code' => $configCode,
            'config_name' => $configName,
            'description' => trim((string)($payload['description'] ?? '')) ?: null,
            'distribution_scope' => $distributionScope,
            'pool_source_mode' => $poolSourceMode,
            'pool_source_value' => round((float)($payload['pool_source_value'] ?? 0), 4),
            'payout_percent' => round((float)($payload['payout_percent'] ?? 100), 4),
            'linked_target_required' => !empty($payload['linked_target_required']) ? 1 : 0,
            'include_shift_revenue_factor' => !empty($payload['include_shift_revenue_factor']) ? 1 : 0,
            'include_service_time_factor' => !empty($payload['include_service_time_factor']) ? 1 : 0,
            'include_peer_review_factor' => !empty($payload['include_peer_review_factor']) ? 1 : 0,
            'include_attendance_factor' => !empty($payload['include_attendance_factor']) ? 1 : 0,
            'include_manual_penalty_factor' => !empty($payload['include_manual_penalty_factor']) ? 1 : 0,
            'status' => $status,
            'notes' => trim((string)($payload['notes'] ?? '')) ?: null,
        ];

        if ($this->table_has_field('pay_bonus_config', 'point_penalty_currency_mode')) {
            $dbPayload['point_penalty_currency_mode'] = $pointPenaltyMode;
        }
        if ($this->table_has_field('pay_bonus_config', 'point_penalty_currency_value')) {
            $dbPayload['point_penalty_currency_value'] = round((float)($payload['point_penalty_currency_value'] ?? 5), 4);
        }

        $exists = $this->db->from('pay_bonus_config')
            ->where('config_code', $configCode);
        if ($id > 0) {
            $exists->where('id <>', $id);
        }
        if ($exists->count_all_results() > 0) {
            return ['ok' => false, 'message' => 'Kode kebijakan bonus sudah dipakai.'];
        }

        if ($id > 0) {
            $dbPayload['updated_at'] = date('Y-m-d H:i:s');
            $this->db->where('id', $id)->update('pay_bonus_config', $dbPayload);
            return ['ok' => $this->db->affected_rows() >= 0, 'message' => 'Kebijakan bonus berhasil diperbarui.', 'id' => $id];
        }

        $dbPayload['created_by'] = $actorUserId > 0 ? $actorUserId : null;
        $dbPayload['created_at'] = date('Y-m-d H:i:s');
        $dbPayload['updated_at'] = date('Y-m-d H:i:s');
        $this->db->insert('pay_bonus_config', $dbPayload);
        return ['ok' => true, 'message' => 'Kebijakan bonus berhasil ditambahkan.', 'id' => (int)$this->db->insert_id()];
    }

    public function deactivate_bonus_config(int $id): array
    {
        if ($id <= 0 || !$this->db->table_exists('pay_bonus_config')) {
            return ['ok' => false, 'message' => 'Kebijakan bonus tidak ditemukan.'];
        }

        $this->db->where('id', $id)->update('pay_bonus_config', [
            'status' => 'INACTIVE',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => $this->db->affected_rows() >= 0, 'message' => 'Kebijakan bonus berhasil dinonaktifkan.'];
    }

    public function list_bonus_rule_options(): array
    {
        if (!$this->db->table_exists('pay_bonus_rule')) {
            return [];
        }

        return $this->db->select('id, rule_code, rule_name')
            ->from('pay_bonus_rule')
            ->where('is_active', 1)
            ->order_by('rule_name', 'ASC')
            ->get()->result_array();
    }

    public function list_bonus_outlet_options(): array
    {
        if (!$this->db->table_exists('pos_outlet')) {
            return [];
        }

        $db = $this->db->select('id, outlet_code, outlet_name')
            ->from('pos_outlet');
        if ($this->table_has_field('pos_outlet', 'is_active')) {
            $db->where('is_active', 1);
        }

        return $db->order_by('outlet_name', 'ASC')->get()->result_array();
    }

    public function list_bonus_target_plan_options(): array
    {
        if (!$this->db->table_exists('fin_target_plan')) {
            return [];
        }

        return $this->db->select('tp.id, tp.target_scope, ' . $this->target_plan_name_expr('tp') . ' AS target_name, tp.status, tp.date_start, tp.date_end', false)
            ->from('fin_target_plan tp')
            ->where_in('status', ['ACTIVE', 'DRAFT', 'LOCKED'])
            ->order_by('tp.date_end', 'DESC')
            ->order_by('tp.id', 'DESC')
            ->limit(100)
            ->get()->result_array();
    }

    private function get_bonus_rule_detail(int $ruleId): array
    {
        if ($ruleId <= 0 || !$this->db->table_exists('pay_bonus_rule') || !$this->db->table_exists('pay_bonus_config')) {
            return [];
        }

        $pointPenaltyModeExpr = $this->table_has_field('pay_bonus_config', 'point_penalty_currency_mode')
            ? 'c.point_penalty_currency_mode'
            : "'PERCENT_SHARE'";
        $pointPenaltyValueExpr = $this->table_has_field('pay_bonus_config', 'point_penalty_currency_value')
            ? 'c.point_penalty_currency_value'
            : '5.0000';

        $db = $this->db->select('r.*, c.config_name, c.payout_percent, c.status AS config_status, c.include_shift_revenue_factor, c.include_service_time_factor, c.include_peer_review_factor, c.include_attendance_factor, c.include_manual_penalty_factor, ' . $pointPenaltyModeExpr . ' AS point_penalty_currency_mode, ' . $pointPenaltyValueExpr . ' AS point_penalty_currency_value', false)
            ->from('pay_bonus_rule r')
            ->join('pay_bonus_config c', 'c.id = r.config_id', 'inner')
            ->where('r.id', $ruleId)
            ->limit(1);
        if ($this->db->table_exists('pos_outlet')) {
            $db->select('o.outlet_name', false)->join('pos_outlet o', 'o.id = r.outlet_id', 'left');
        }
        if ($this->db->table_exists('org_division')) {
            $db->select('d.division_name', false)->join('org_division d', 'd.id = r.division_id', 'left');
        }

        return $db->get()->row_array() ?: [];
    }

    private function calculate_bonus_target_context(array $rule, string $bonusDate = '', int $actorUserId = 0): array
    {
        $default = [
            'target_plan_id' => !empty($rule['linked_target_plan_id']) ? (int)$rule['linked_target_plan_id'] : null,
            'target_score_percent' => 100.00,
            'target_gate_passed' => 1,
            'target_multiplier' => 1.0000,
            'target_notes' => 'Target netral',
            'target_progress_start' => null,
            'target_progress_end' => null,
        ];

        $planId = (int)($rule['linked_target_plan_id'] ?? 0);
        if ($planId <= 0 || !$this->db->table_exists('fin_target_plan')) {
            return $default;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $bonusDate)) {
            $bonusDate = date('Y-m-d');
        }

        $CI = &get_instance();
        $CI->load->model('Finance_report_model');
        if (!isset($CI->Finance_report_model) || !method_exists($CI->Finance_report_model, 'get_target_progress_snapshot')) {
            return $default;
        }

        $progress = $CI->Finance_report_model->get_target_progress_snapshot($planId, $bonusDate);
        if (empty($progress['ok']) || empty($progress['applicable'])) {
            $default['target_notes'] = (string)($progress['notes'] ?? 'Target netral');
            return $default;
        }

        $avgScore = round((float)($progress['avg_score_percent'] ?? 100), 2);
        $allPassed = !empty($progress['all_required_passed']);
        $gateMode = strtoupper((string)($rule['target_gate_mode'] ?? 'WEIGHTED_SCORE'));
        $minTargetScore = max(1, (float)($rule['min_target_score'] ?? 100));
        $multiplier = 1.0000;
        $passed = 1;
        $notes = (string)($progress['notes'] ?? 'Target netral');

        if ($gateMode === 'ALL_REQUIRED') {
            $passed = $allPassed ? 1 : 0;
            $multiplier = $passed ? 1.0000 : 0.0000;
            $notes = $passed
                ? 'Semua target wajib saat ini lolos'
                : 'Masih ada target wajib yang belum lolos';
        } elseif ($gateMode === 'WEIGHTED_SCORE') {
            $ratio = max(0, min(1, $avgScore / $minTargetScore));
            $multiplier = round($ratio, 4);
            $passed = $avgScore >= $minTargetScore ? 1 : 0;
            $notes = 'Skor target progres ' . number_format($avgScore, 2, ',', '.') . '%';
        }

        return [
            'target_plan_id' => $planId,
            'target_score_percent' => $avgScore,
            'target_gate_passed' => $passed,
            'target_multiplier' => $multiplier,
            'target_notes' => $notes,
            'target_progress_start' => (string)($progress['date_start'] ?? ''),
            'target_progress_end' => (string)($progress['date_end'] ?? ''),
        ];
    }

    private function calculate_bonus_daily_threshold_context(array $rule, string $bonusDate = '', int $forcedPlanId = 0): array
    {
        $default = [
            'target_plan_id' => null,
            'threshold_amount' => round((float)($rule['threshold_amount'] ?? 0), 2),
            'target_name' => '',
            'notes' => 'Ambang omzet harian memakai angka langsung dari rule bonus.',
        ];

        if (
            !$this->table_has_field('pay_bonus_rule', 'daily_target_plan_id')
            || !$this->db->table_exists('fin_target_plan')
            || !$this->db->table_exists('fin_target_plan_line')
        ) {
            return $default;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $bonusDate)) {
            $bonusDate = date('Y-m-d');
        }

        $planId = $forcedPlanId > 0 ? $forcedPlanId : (int)($rule['daily_target_plan_id'] ?? 0);
        if ($planId <= 0) {
            return $default;
        }

        $plan = $this->db->select('tp.id, tp.target_scope, tp.status, tp.date_start, tp.date_end, ' . $this->target_plan_name_expr('tp') . ' AS target_name', false)
            ->from('fin_target_plan tp')
            ->where('tp.id', $planId)
            ->limit(1)
            ->get()->row_array();
        if (!$plan) {
            return $default;
        }

        $default['target_plan_id'] = (int)($plan['id'] ?? 0);
        $default['target_name'] = (string)($plan['target_name'] ?? '');

        $status = strtoupper((string)($plan['status'] ?? 'DRAFT'));
        $scope = strtoupper((string)($plan['target_scope'] ?? 'MONTHLY'));
        $dateStart = (string)($plan['date_start'] ?? '');
        $dateEnd = (string)($plan['date_end'] ?? '');
        if ($status !== 'ACTIVE' || $scope !== 'DAILY' || $dateStart === '' || $dateEnd === '' || $bonusDate < $dateStart || $bonusDate > $dateEnd) {
            $default['notes'] = 'Target harian terhubung belum aktif / belum berlaku, jadi engine fallback ke ambang di rule.';
            return $default;
        }

        $line = $this->db->select('metric_code, metric_label, target_value')
            ->from('fin_target_plan_line')
            ->where('target_plan_id', $planId)
            ->where_in('metric_code', ['POS_REVENUE', 'POS_NET_REVENUE', 'NET_REVENUE'])
            ->order_by("FIELD(metric_code, 'POS_REVENUE', 'POS_NET_REVENUE', 'NET_REVENUE')", '', false)
            ->limit(1)
            ->get()->row_array();
        if (!$line) {
            $default['notes'] = 'Target harian aktif, tetapi line omzet belum ada, jadi engine fallback ke ambang di rule.';
            return $default;
        }

        return [
            'target_plan_id' => (int)($plan['id'] ?? 0),
            'threshold_amount' => round((float)($line['target_value'] ?? 0), 2),
            'target_name' => (string)($plan['target_name'] ?? ''),
            'notes' => 'Ambang omzet harian diambil dari target aktif: ' . (string)($line['metric_label'] ?? 'Omzet POS') . '.',
        ];
    }

    private function get_bonus_shift_rows(string $bonusDate): array
    {
        if (!$this->db->table_exists('att_shift')) {
            return [];
        }

        if ($this->db->table_exists('att_shift_schedule')) {
            $select = 's.id, s.shift_code, s.shift_name, s.start_time, s.end_time';
            if ($this->table_has_field('att_shift', 'is_overnight')) {
                $select .= ', s.is_overnight';
            }
            $db = $this->db->select($select, false)
                ->from('att_shift_schedule ss')
                ->join('att_shift s', 's.id = ss.shift_id', 'inner')
                ->where('ss.schedule_date', $bonusDate)
                ->order_by('s.start_time', 'ASC');
            $db->group_by('s.id')
                ->group_by('s.shift_code')
                ->group_by('s.shift_name')
                ->group_by('s.start_time')
                ->group_by('s.end_time');
            if ($this->table_has_field('att_shift', 'is_overnight')) {
                $db->group_by('s.is_overnight');
            }
            $rows = $db->get()->result_array();
            if (!empty($rows)) {
                return $rows;
            }
        }

        $select = 'id, shift_code, shift_name, start_time, end_time';
        if ($this->table_has_field('att_shift', 'is_overnight')) {
            $select .= ', is_overnight';
        }
        $db = $this->db->select($select, false)
            ->from('att_shift');
        if ($this->table_has_field('att_shift', 'is_active')) {
            $db->where('is_active', 1);
        }
        return $db->order_by('start_time', 'ASC')->get()->result_array();
    }

    private function auto_penalty_type_rows(): array
    {
        if (!$this->db->table_exists('pay_bonus_penalty_type')) {
            return [];
        }

        $db = $this->db->select('id, penalty_code, penalty_name, default_points_deducted, default_amount_deducted')
            ->from('pay_bonus_penalty_type')
            ->where('is_active', 1);
        if ($this->table_has_field('pay_bonus_penalty_type', 'behavior_mode')) {
            $db->where('behavior_mode', 'AUTO');
        }

        $rows = $db->get()->result_array();
        $map = [];
        foreach ($rows as $row) {
            $code = strtoupper((string)($row['penalty_code'] ?? ''));
            if ($code !== '') {
                $map[$code] = $row;
            }
        }
        return $map;
    }

    public function sync_bonus_auto_penalties(string $bonusDate, int $employeeId = 0, int $actorUserId = 0): array
    {
        if (
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $bonusDate)
            || !$this->db->table_exists('pay_bonus_penalty_event')
            || !$this->db->table_exists('att_daily')
        ) {
            return ['ok' => false, 'message' => 'Tanggal atau tabel penalti bonus belum siap.'];
        }

        $typeMap = $this->auto_penalty_type_rows();
        if (empty($typeMap)) {
            return ['ok' => false, 'message' => 'Belum ada master penalti AUTO yang aktif.'];
        }

        $db = $this->db->select('ad.*, s.shift_code, s.shift_name, s.end_time' . ($this->table_has_field('att_shift', 'is_overnight') ? ', s.is_overnight' : '') . ', e.division_id', false)
            ->from('att_daily ad')
            ->join('org_employee e', 'e.id = ad.employee_id', 'left')
            ->join('att_shift s', 's.id = ad.shift_id', 'left')
            ->where('ad.attendance_date', $bonusDate);
        if ($employeeId > 0) {
            $db->where('ad.employee_id', $employeeId);
        }
        $dailyRows = $db->get()->result_array();

        if ($employeeId > 0) {
            $this->db->where('penalty_date', $bonusDate)->where('employee_id', $employeeId);
        } else {
            $this->db->where('penalty_date', $bonusDate);
        }
        $this->db->where_in('source_type', ['AUTO_ATTENDANCE', 'AUTO_SERVICE', 'AUTO_TARGET', 'AUTO_PEER'])->delete('pay_bonus_penalty_event');

        $inserted = 0;
        $now = date('Y-m-d H:i:s');
        foreach ($dailyRows as $dailyRow) {
            $empId = (int)($dailyRow['employee_id'] ?? 0);
            if ($empId <= 0) {
                continue;
            }

            $status = strtoupper((string)($dailyRow['attendance_status'] ?? ''));
            $lateMinutes = (int)($dailyRow['late_minutes'] ?? 0);
            $earlyLeaveMinutes = (int)($dailyRow['early_leave_minutes'] ?? 0);
            $divisionId = (int)($dailyRow['division_id'] ?? 0);
            $shiftId = (int)($dailyRow['shift_id'] ?? 0);
            $shiftCode = strtoupper(trim((string)($dailyRow['shift_code'] ?? '')));
            $sourceType = strtoupper(trim((string)($dailyRow['source_type'] ?? 'AUTO')));
            $eventQueue = [];

            if ($status === 'ALPHA' && isset($typeMap['ALPHA'])) {
                $eventQueue[] = ['code' => 'ALPHA', 'reason' => 'Tidak hadir pada jadwal kerja', 'points' => (float)($typeMap['ALPHA']['default_points_deducted'] ?? 10)];
            }

            if ($status === 'LATE' || $lateMinutes > 0) {
                if ($lateMinutes > 30 && isset($typeMap['LATE_SEVERE'])) {
                    $eventQueue[] = ['code' => 'LATE_SEVERE', 'reason' => 'Terlambat lebih dari 30 menit (' . $lateMinutes . ' menit)', 'points' => (float)($typeMap['LATE_SEVERE']['default_points_deducted'] ?? 5)];
                } elseif ($lateMinutes >= 15 && isset($typeMap['LATE_MAJOR'])) {
                    $eventQueue[] = ['code' => 'LATE_MAJOR', 'reason' => 'Terlambat 15-30 menit (' . $lateMinutes . ' menit)', 'points' => (float)($typeMap['LATE_MAJOR']['default_points_deducted'] ?? 3)];
                } elseif ($lateMinutes > 0 && isset($typeMap['LATE_MINOR'])) {
                    $eventQueue[] = ['code' => 'LATE_MINOR', 'reason' => 'Terlambat kurang dari 15 menit (' . $lateMinutes . ' menit)', 'points' => (float)($typeMap['LATE_MINOR']['default_points_deducted'] ?? 1)];
                }
            }

            if ($earlyLeaveMinutes > 0) {
                $isEvening = false;
                $endHour = (int)substr((string)($dailyRow['end_time'] ?? '00:00:00'), 0, 2);
                if ($shiftCode !== '' && $endHour >= 20) {
                    $isEvening = true;
                }
                if ($isEvening && $earlyLeaveMinutes <= 60 && isset($typeMap['EARLY_OUT_TOLERANCE'])) {
                    $eventQueue[] = ['code' => 'EARLY_OUT_TOLERANCE', 'reason' => 'Pulang lebih cepat dalam toleransi shift malam (' . $earlyLeaveMinutes . ' menit)', 'points' => (float)($typeMap['EARLY_OUT_TOLERANCE']['default_points_deducted'] ?? 1)];
                } elseif ($earlyLeaveMinutes < 15 && isset($typeMap['EARLY_OUT_MINOR'])) {
                    $eventQueue[] = ['code' => 'EARLY_OUT_MINOR', 'reason' => 'Pulang lebih cepat kurang dari 15 menit', 'points' => (float)($typeMap['EARLY_OUT_MINOR']['default_points_deducted'] ?? 1)];
                } elseif ($earlyLeaveMinutes < 30 && isset($typeMap['EARLY_OUT_MODERATE'])) {
                    $eventQueue[] = ['code' => 'EARLY_OUT_MODERATE', 'reason' => 'Pulang lebih cepat 15-30 menit', 'points' => (float)($typeMap['EARLY_OUT_MODERATE']['default_points_deducted'] ?? 2)];
                } elseif (isset($typeMap['EARLY_OUT'])) {
                    $eventQueue[] = ['code' => 'EARLY_OUT', 'reason' => 'Pulang lebih cepat lebih dari 30 menit', 'points' => (float)($typeMap['EARLY_OUT']['default_points_deducted'] ?? 3)];
                }
            }

            if (($shiftCode === 'PH' || $shiftCode === 'PHB') && $status === 'ALPHA' && isset($typeMap['ABSENT_PH'])) {
                $eventQueue[] = ['code' => 'ABSENT_PH', 'reason' => 'Tidak hadir pada shift PH yang sudah dijadwalkan', 'points' => (float)($typeMap['ABSENT_PH']['default_points_deducted'] ?? 5)];
            }

            if ($sourceType === 'MANUAL' && isset($typeMap['MANUAL_ATTENDANCE'])) {
                $eventQueue[] = ['code' => 'MANUAL_ATTENDANCE', 'reason' => 'Absensi masuk lewat jalur manual dan perlu dicermati', 'points' => (float)($typeMap['MANUAL_ATTENDANCE']['default_points_deducted'] ?? 2)];
            }

            foreach ($eventQueue as $event) {
                $typeRow = $typeMap[$event['code']] ?? null;
                if (!$typeRow) {
                    continue;
                }
                $this->db->insert('pay_bonus_penalty_event', [
                    'penalty_date' => $bonusDate,
                    'rule_id' => null,
                    'penalty_type_id' => (int)$typeRow['id'],
                    'employee_id' => $empId,
                    'division_id' => $divisionId > 0 ? $divisionId : null,
                    'shift_id' => $shiftId > 0 ? $shiftId : null,
                    'penalty_scope' => 'PERSONAL',
                    'source_type' => 'AUTO_ATTENDANCE',
                    'points_deducted' => round((float)$event['points'], 4),
                    'amount_deducted' => round((float)($typeRow['default_amount_deducted'] ?? 0), 2),
                    'reason_text' => $event['reason'],
                    'status' => 'APPROVED',
                    'created_by' => $actorUserId > 0 ? $actorUserId : null,
                    'approved_by' => $actorUserId > 0 ? $actorUserId : null,
                    'approved_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $inserted++;
            }
        }

        if ($this->db->table_exists('att_employee_ph_ledger') && isset($typeMap['BONUS-PH-TAKEN'])) {
            $phDb = $this->db->select('l.employee_id, e.division_id', false)
                ->from('att_employee_ph_ledger l')
                ->join('org_employee e', 'e.id = l.employee_id', 'left')
                ->where('l.tx_type', 'USE')
                ->where('l.tx_date', $bonusDate);
            if ($employeeId > 0) {
                $phDb->where('l.employee_id', $employeeId);
            }
            $phRows = $phDb->group_by(['l.employee_id', 'e.division_id'])->get()->result_array();
            foreach ($phRows as $phRow) {
                $empId = (int)($phRow['employee_id'] ?? 0);
                if ($empId <= 0) {
                    continue;
                }
                $this->db->insert('pay_bonus_penalty_event', [
                    'penalty_date' => $bonusDate,
                    'rule_id' => null,
                    'penalty_type_id' => (int)$typeMap['BONUS-PH-TAKEN']['id'],
                    'employee_id' => $empId,
                    'division_id' => (int)($phRow['division_id'] ?? 0) > 0 ? (int)$phRow['division_id'] : null,
                    'shift_id' => null,
                    'penalty_scope' => 'PERSONAL',
                    'source_type' => 'AUTO_ATTENDANCE',
                    'points_deducted' => round((float)($typeMap['BONUS-PH-TAKEN']['default_points_deducted'] ?? 1), 4),
                    'amount_deducted' => round((float)($typeMap['BONUS-PH-TAKEN']['default_amount_deducted'] ?? 0), 2),
                    'reason_text' => 'Pegawai menggunakan hak PH pada tanggal ini',
                    'status' => 'APPROVED',
                    'created_by' => $actorUserId > 0 ? $actorUserId : null,
                    'approved_by' => $actorUserId > 0 ? $actorUserId : null,
                    'approved_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $inserted++;
            }
        }

        return ['ok' => true, 'message' => 'Sinkron penalti otomatis selesai.', 'inserted' => $inserted];
    }

    public function generate_bonus_service_metric_daily(string $metricDate, int $outletId = 0): array
    {
        if (
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $metricDate)
            || !$this->db->table_exists('pay_bonus_service_metric_daily')
            || !$this->db->table_exists('pos_order')
        ) {
            return ['ok' => false, 'message' => 'Fondasi metric layanan belum lengkap.'];
        }

        $defaultTargetMinute = 15.00;
        $statusSql = $this->order_payment_status_sql('o');
        $rows = $this->db->select("
                o.outlet_id,
                o.shift_id,
                COUNT(*) AS total_orders,
                SUM(CASE WHEN o.served_at IS NOT NULL AND o.kitchen_status = 'SERVED' THEN 1 ELSE 0 END) AS served_orders,
                SUM(CASE WHEN o.served_at IS NOT NULL AND o.kitchen_status = 'SERVED' AND TIMESTAMPDIFF(MINUTE, o.ordered_at, o.served_at) <= " . $this->db->escape($defaultTargetMinute) . " THEN 1 ELSE 0 END) AS ontime_orders,
                SUM(CASE WHEN o.served_at IS NOT NULL AND o.kitchen_status = 'SERVED' AND TIMESTAMPDIFF(MINUTE, o.ordered_at, o.served_at) > " . $this->db->escape($defaultTargetMinute) . " THEN 1 ELSE 0 END) AS late_orders,
                COALESCE(AVG(CASE WHEN o.served_at IS NOT NULL AND o.kitchen_status = 'SERVED' THEN GREATEST(TIMESTAMPDIFF(MINUTE, o.ordered_at, o.served_at), 0) END), 0) AS avg_service_minutes
            ", false)
            ->from('pos_order o')
            ->where("DATE(COALESCE(o.paid_at, o.ordered_at)) = " . $this->db->escape($metricDate), null, false)
            ->where('o.status <>', 'VOID')
            ->where('o.kitchen_status <>', 'VOID')
            ->where("{$statusSql} IN ('PAID','PAID_PARTIAL','SERVED')", null, false);
        if ($outletId > 0 && $this->table_has_field('pos_order', 'outlet_id')) {
            $rows->where('o.outlet_id', $outletId);
        }
        $rows = $rows->group_by(['o.outlet_id', 'o.shift_id'])->get()->result_array();

        $inserted = 0;
        $upsert = static function (CI_DB_query_builder $db, array $payload, array $where) use (&$inserted) {
            $exists = $db->select('id')->from('pay_bonus_service_metric_daily');
            foreach ($where as $key => $value) {
                if ($value === null) {
                    $exists->where($key . ' IS NULL', null, false);
                } else {
                    $exists->where($key, $value);
                }
            }
            $current = $exists->limit(1)->get()->row_array();
            if ($current) {
                $db->where('id', (int)$current['id'])->update('pay_bonus_service_metric_daily', $payload);
            } else {
                $db->insert('pay_bonus_service_metric_daily', $payload);
                $inserted++;
            }
        };

        $aggregates = [];
        foreach ($rows as $row) {
            $currentOutletId = (int)($row['outlet_id'] ?? 0);
            $currentShiftId = (int)($row['shift_id'] ?? 0);
            $servedOrders = (int)($row['served_orders'] ?? 0);
            $avgMinutes = round((float)($row['avg_service_minutes'] ?? 0), 2);
            $baseScore = $servedOrders > 0 ? round(((int)($row['ontime_orders'] ?? 0) / max($servedOrders, 1)) * 100, 2) : 100.00;
            $scorePercent = $this->normalize_bonus_service_score($avgMinutes, $defaultTargetMinute, $baseScore);

            $payload = [
                'metric_date' => $metricDate,
                'outlet_id' => $currentOutletId > 0 ? $currentOutletId : null,
                'division_id' => null,
                'shift_id' => $currentShiftId > 0 ? $currentShiftId : null,
                'total_orders' => (int)($row['total_orders'] ?? 0),
                'served_orders' => $servedOrders,
                'ontime_orders' => (int)($row['ontime_orders'] ?? 0),
                'late_orders' => (int)($row['late_orders'] ?? 0),
                'avg_service_minutes' => $avgMinutes,
                'score_percent' => $scorePercent,
                'source_notes' => 'Generated from POS order served_at against ordered_at',
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            $upsert($this->db, $payload, [
                'metric_date' => $metricDate,
                'outlet_id' => $payload['outlet_id'],
                'division_id' => null,
                'shift_id' => $payload['shift_id'],
            ]);

            $aggregateKeys = [
                'OUTLET:' . $currentOutletId => ['outlet_id' => $currentOutletId > 0 ? $currentOutletId : null, 'shift_id' => null],
                'GLOBAL_SHIFT:' . $currentShiftId => ['outlet_id' => null, 'shift_id' => $currentShiftId > 0 ? $currentShiftId : null],
                'GLOBAL_TOTAL' => ['outlet_id' => null, 'shift_id' => null],
            ];
            foreach ($aggregateKeys as $key => $scope) {
                if (!isset($aggregates[$key])) {
                    $aggregates[$key] = [
                        'outlet_id' => $scope['outlet_id'],
                        'shift_id' => $scope['shift_id'],
                        'total_orders' => 0,
                        'served_orders' => 0,
                        'ontime_orders' => 0,
                        'late_orders' => 0,
                        'weighted_minutes' => 0.0,
                    ];
                }
                $aggregates[$key]['total_orders'] += (int)($row['total_orders'] ?? 0);
                $aggregates[$key]['served_orders'] += $servedOrders;
                $aggregates[$key]['ontime_orders'] += (int)($row['ontime_orders'] ?? 0);
                $aggregates[$key]['late_orders'] += (int)($row['late_orders'] ?? 0);
                $aggregates[$key]['weighted_minutes'] += $avgMinutes * max($servedOrders, 1);
            }
        }

        foreach ($aggregates as $aggregate) {
            $servedOrders = (int)($aggregate['served_orders'] ?? 0);
            $avgMinutes = $servedOrders > 0 ? round(((float)($aggregate['weighted_minutes'] ?? 0) / max($servedOrders, 1)), 2) : 0.00;
            $baseScore = $servedOrders > 0 ? round(((int)($aggregate['ontime_orders'] ?? 0) / max($servedOrders, 1)) * 100, 2) : 100.00;
            $payload = [
                'metric_date' => $metricDate,
                'outlet_id' => $aggregate['outlet_id'],
                'division_id' => null,
                'shift_id' => $aggregate['shift_id'],
                'total_orders' => (int)($aggregate['total_orders'] ?? 0),
                'served_orders' => $servedOrders,
                'ontime_orders' => (int)($aggregate['ontime_orders'] ?? 0),
                'late_orders' => (int)($aggregate['late_orders'] ?? 0),
                'avg_service_minutes' => $avgMinutes,
                'score_percent' => $this->normalize_bonus_service_score($avgMinutes, $defaultTargetMinute, $baseScore),
                'source_notes' => 'Generated aggregate from POS service metrics',
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            $upsert($this->db, $payload, [
                'metric_date' => $metricDate,
                'outlet_id' => $payload['outlet_id'],
                'division_id' => null,
                'shift_id' => $payload['shift_id'],
            ]);
        }

        return [
            'ok' => true,
            'message' => 'Metric layanan harian berhasil diperbarui.',
            'inserted' => $inserted,
            'group_count' => count($rows),
        ];
    }

    public function refresh_bonus_monthly_summary(string $month, int $actorUserId = 0): array
    {
        if (
            !preg_match('/^\d{4}-\d{2}$/', $month)
            || !$this->db->table_exists('pay_bonus_monthly_summary')
            || !$this->db->table_exists('pay_bonus_employee_daily')
            || !$this->db->table_exists('pay_bonus_pool_daily')
        ) {
            return ['ok' => false, 'message' => 'Fondasi rekap bonus bulanan belum lengkap.'];
        }

        $dateFrom = $month . '-01';
        $dateTo = date('Y-m-t', strtotime($dateFrom));

        $dailyRows = $this->db->select("
                p.config_id,
                ed.employee_id,
                CASE WHEN COUNT(DISTINCT p.rule_id) = 1 THEN MAX(p.rule_id) ELSE NULL END AS rule_id,
                CASE WHEN COUNT(DISTINCT COALESCE(p.outlet_id,0)) = 1 THEN MAX(p.outlet_id) ELSE NULL END AS outlet_id,
                CASE WHEN COUNT(DISTINCT COALESCE(p.division_id,0)) = 1 THEN MAX(p.division_id) ELSE NULL END AS division_id,
                COALESCE(SUM(ed.raw_point),0) AS total_raw_point,
                COALESCE(SUM(ed.penalty_point),0) AS total_penalty_point,
                COALESCE(SUM(ed.final_point),0) AS total_final_point,
                COALESCE(SUM(ed.raw_amount),0) AS total_raw_amount,
                COALESCE(SUM(ed.penalty_amount),0) AS total_penalty_amount,
                COALESCE(SUM(ed.final_amount),0) AS total_final_amount,
                COALESCE(AVG(p.service_score_percent),0) AS service_avg_score,
                COALESCE(AVG(p.target_score_percent),0) AS target_avg_score,
                COUNT(*) AS daily_row_count
            ", false)
            ->from('pay_bonus_employee_daily ed')
            ->join('pay_bonus_pool_daily p', 'p.id = ed.pool_id', 'inner')
            ->where('ed.attendance_date >=', $dateFrom)
            ->where('ed.attendance_date <=', $dateTo)
            ->where('ed.approval_status', 'APPROVED')
            ->where('p.approval_status', 'APPROVED')
            ->group_by(['p.config_id', 'ed.employee_id'])
            ->get()->result_array();

        $penaltyMap = [];
        if ($this->db->table_exists('pay_bonus_penalty_event') && $this->db->table_exists('pay_bonus_penalty_type')) {
            $penaltyRows = $this->db->select("
                    e.employee_id,
                    SUM(CASE WHEN t.penalty_code IN ('LATE_MINOR','LATE_MAJOR','LATE_SEVERE') THEN 1 ELSE 0 END) AS late_count,
                    SUM(CASE WHEN t.penalty_code = 'ALPHA' THEN 1 ELSE 0 END) AS alpha_count,
                    SUM(CASE WHEN t.penalty_code = 'BONUS-PH-TAKEN' THEN 1 ELSE 0 END) AS ph_taken_count
                ", false)
                ->from('pay_bonus_penalty_event e')
                ->join('pay_bonus_penalty_type t', 't.id = e.penalty_type_id', 'inner')
                ->where('e.penalty_scope', 'PERSONAL')
                ->where('e.status', 'APPROVED')
                ->where('e.penalty_date >=', $dateFrom)
                ->where('e.penalty_date <=', $dateTo)
                ->group_by('e.employee_id')
                ->get()->result_array();
            foreach ($penaltyRows as $penaltyRow) {
                $penaltyMap[(int)($penaltyRow['employee_id'] ?? 0)] = $penaltyRow;
            }
        }

        $peerMap = [];
        if ($this->db->table_exists('perf_peer_feedback')) {
            $peerRows = $this->db->select('to_employee_id AS employee_id, COALESCE(AVG(star_rating),0) AS peer_avg_star', false)
                ->from('perf_peer_feedback')
                ->where('status', 'APPROVED')
                ->where('feedback_date >=', $dateFrom)
                ->where('feedback_date <=', $dateTo)
                ->group_by('to_employee_id')
                ->get()->result_array();
            foreach ($peerRows as $peerRow) {
                $peerMap[(int)($peerRow['employee_id'] ?? 0)] = round((float)($peerRow['peer_avg_star'] ?? 0), 2);
            }
        }

        $manualMap = [];
        if ($this->db->table_exists('pay_bonus_manual_adjustment')) {
            $manualRows = $this->db->select("
                    employee_id,
                    COALESCE(SUM(CASE WHEN adjustment_basis = 'POINT' AND adjustment_kind = 'ADD' THEN adjustment_value WHEN adjustment_basis = 'POINT' AND adjustment_kind = 'DEDUCT' THEN -adjustment_value ELSE 0 END),0) AS point_delta,
                    COALESCE(SUM(CASE WHEN adjustment_basis = 'AMOUNT' AND adjustment_kind = 'ADD' THEN adjustment_value WHEN adjustment_basis = 'AMOUNT' AND adjustment_kind = 'DEDUCT' THEN -adjustment_value ELSE 0 END),0) AS amount_delta,
                    COUNT(*) AS adjustment_count
                ", false)
                ->from('pay_bonus_manual_adjustment')
                ->where('bonus_month', $month)
                ->where('status', 'APPROVED')
                ->group_by('employee_id')
                ->get()->result_array();
            foreach ($manualRows as $manualRow) {
                $manualMap[(int)($manualRow['employee_id'] ?? 0)] = $manualRow;
            }
        }

        $postedMap = [];
        $postedRows = $this->db->select('config_id, employee_id')
            ->from('pay_bonus_monthly_summary')
            ->where('summary_month', $month)
            ->where('payout_status', 'POSTED')
            ->get()->result_array();
        foreach ($postedRows as $postedRow) {
            $postedMap[(int)($postedRow['config_id'] ?? 0) . ':' . (int)($postedRow['employee_id'] ?? 0)] = true;
        }

        $this->db->trans_start();
        $this->db->where('summary_month', $month)
            ->where('payout_status <>', 'POSTED')
            ->delete('pay_bonus_monthly_summary');

        $inserted = 0;
        foreach ($dailyRows as $dailyRow) {
            $employeeId = (int)($dailyRow['employee_id'] ?? 0);
            $configId = (int)($dailyRow['config_id'] ?? 0);
            if ($employeeId <= 0 || $configId <= 0) {
                continue;
            }
            if (!empty($postedMap[$configId . ':' . $employeeId])) {
                continue;
            }
            $manual = $manualMap[$employeeId] ?? ['point_delta' => 0, 'amount_delta' => 0, 'adjustment_count' => 0];
            $penalty = $penaltyMap[$employeeId] ?? ['late_count' => 0, 'alpha_count' => 0, 'ph_taken_count' => 0];
            $notes = [];
            if ((int)($manual['adjustment_count'] ?? 0) > 0) {
                $notes[] = 'manual adj ' . (int)$manual['adjustment_count'];
            }
            $this->db->insert('pay_bonus_monthly_summary', [
                'summary_month' => $month,
                'config_id' => $configId,
                'rule_id' => !empty($dailyRow['rule_id']) ? (int)$dailyRow['rule_id'] : null,
                'employee_id' => $employeeId,
                'outlet_id' => !empty($dailyRow['outlet_id']) ? (int)$dailyRow['outlet_id'] : null,
                'division_id' => !empty($dailyRow['division_id']) ? (int)$dailyRow['division_id'] : null,
                'total_raw_point' => round((float)($dailyRow['total_raw_point'] ?? 0), 4),
                'total_penalty_point' => round((float)($dailyRow['total_penalty_point'] ?? 0), 4),
                'total_final_point' => round((float)($dailyRow['total_final_point'] ?? 0) + (float)($manual['point_delta'] ?? 0), 4),
                'total_raw_amount' => round((float)($dailyRow['total_raw_amount'] ?? 0), 2),
                'total_penalty_amount' => round((float)($dailyRow['total_penalty_amount'] ?? 0), 2),
                'total_final_amount' => round((float)($dailyRow['total_final_amount'] ?? 0) + (float)($manual['amount_delta'] ?? 0), 2),
                'ph_taken_count' => (int)($penalty['ph_taken_count'] ?? 0),
                'late_count' => (int)($penalty['late_count'] ?? 0),
                'alpha_count' => (int)($penalty['alpha_count'] ?? 0),
                'peer_avg_star' => round((float)($peerMap[$employeeId] ?? 0), 2),
                'service_avg_score' => round((float)($dailyRow['service_avg_score'] ?? 0), 2),
                'target_avg_score' => round((float)($dailyRow['target_avg_score'] ?? 0), 2),
                'payout_status' => 'APPROVED',
                'notes' => !empty($notes) ? implode(' | ', $notes) : null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $inserted++;
        }

        $this->db->trans_complete();
        if (!$this->db->trans_status()) {
            return ['ok' => false, 'message' => 'Gagal membangun rekap bonus bulanan.'];
        }

        return [
            'ok' => true,
            'message' => 'Rekap bonus bulanan berhasil diperbarui.',
            'inserted' => $inserted,
        ];
    }

    public function generate_bonus_pool_daily(string $bonusDate, int $ruleId, int $actorUserId = 0, int $forcedDailyTargetPlanId = 0): array
    {
        if (
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $bonusDate)
            || !$this->db->table_exists('pay_bonus_pool_daily')
            || !$this->db->table_exists('pay_bonus_pool_shift')
            || !$this->db->table_exists('pay_bonus_employee_daily')
            || !$this->db->table_exists('att_daily')
            || !$this->db->table_exists('pos_order')
        ) {
            return ['ok' => false, 'message' => 'Fondasi generator bonus belum lengkap.'];
        }

        $rule = $this->get_bonus_rule_detail($ruleId);
        if (!$rule || (int)($rule['is_active'] ?? 0) !== 1) {
            return ['ok' => false, 'message' => 'Aturan bonus tidak ditemukan atau belum aktif.'];
        }

        $exists = $this->db->from('pay_bonus_pool_daily')
            ->where('bonus_date', $bonusDate)
            ->where('rule_id', $ruleId)
            ->where('approval_status <>', 'VOID')
            ->count_all_results();
        if ($exists > 0) {
            return ['ok' => false, 'message' => 'Pool bonus untuk tanggal dan aturan ini sudah ada. Void dulu jika ingin generate ulang.'];
        }

        $paymentStatusSql = $this->order_payment_status_sql('o');
        $salesDb = $this->db->select('COALESCE(SUM(o.grand_total),0) AS gross_sales_amount', false)
            ->from('pos_order o')
            ->where("DATE(COALESCE(o.paid_at, o.ordered_at)) = " . $this->db->escape($bonusDate), null, false)
            ->where("{$paymentStatusSql} IN ('PAID','PAID_PARTIAL')", null, false);
        if ((int)($rule['outlet_id'] ?? 0) > 0 && $this->table_has_field('pos_order', 'outlet_id')) {
            $salesDb->where('o.outlet_id', (int)$rule['outlet_id']);
        }
        $grossSalesAmount = round((float)($salesDb->get()->row_array()['gross_sales_amount'] ?? 0), 2);

        $refundAmount = 0.00;
        if ($this->db->table_exists('pos_refund')) {
            $refundDb = $this->db->select('COALESCE(SUM(r.refund_amount),0) AS refund_amount', false)
                ->from('pos_refund r');
            if ($this->table_has_field('pos_refund', 'refunded_at')) {
                $refundDb->where('DATE(r.refunded_at)', $bonusDate);
            } elseif ($this->table_has_field('pos_refund', 'created_at')) {
                $refundDb->where('DATE(r.created_at)', $bonusDate);
            }
            if ((int)($rule['outlet_id'] ?? 0) > 0 && $this->table_has_field('pos_refund', 'outlet_id')) {
                $refundDb->where('r.outlet_id', (int)$rule['outlet_id']);
            }
            $refundAmount = round((float)($refundDb->get()->row_array()['refund_amount'] ?? 0), 2);
        }

        $netSalesAmount = round(max(0, $grossSalesAmount - $refundAmount), 2);
        $dailyThresholdCtx = $this->calculate_bonus_daily_threshold_context($rule, $bonusDate, $forcedDailyTargetPlanId);
        $thresholdAmount = round((float)($dailyThresholdCtx['threshold_amount'] ?? 0), 2);
        $basePoolAmount = 0.00;
        if ($thresholdAmount <= 0 || $netSalesAmount >= $thresholdAmount) {
            $formulaType = strtoupper((string)($rule['pool_formula_type'] ?? 'PERCENTAGE'));
            $formulaValue = (float)($rule['pool_formula_value'] ?? 0);
            $basePoolAmount = $formulaType === 'FIXED_STEP'
                ? round($formulaValue, 2)
                : round($netSalesAmount * ($formulaValue / 100), 2);
        }

        $targetCtx = $this->calculate_bonus_target_context($rule, $bonusDate, $actorUserId);
        $configPayoutMultiplier = max(0, min(100, (float)($rule['payout_percent'] ?? 100))) / 100;
        $serviceTargetMinute = round((float)($rule['service_time_target_minute'] ?? 0), 2);
        $serviceScorePercent = 100.00;
        $serviceAvgMinutes = 0.00;
        if ($this->db->table_exists('pay_bonus_service_metric_daily')) {
            $serviceDb = $this->db->select('COALESCE(AVG(score_percent),100) AS avg_score, COALESCE(AVG(avg_service_minutes),0) AS avg_minutes', false)
                ->from('pay_bonus_service_metric_daily')
                ->where('metric_date', $bonusDate)
                ->where('shift_id IS NULL', null, false)
                ->where('division_id IS NULL', null, false);
            if ((int)($rule['outlet_id'] ?? 0) > 0) {
                $serviceDb->where('outlet_id', (int)$rule['outlet_id']);
            } else {
                $serviceDb->where('outlet_id IS NULL', null, false);
            }
            $serviceMetric = $serviceDb->get()->row_array() ?: [];
            $serviceAvgMinutes = round((float)($serviceMetric['avg_minutes'] ?? 0), 2);
            $serviceScorePercent = $this->normalize_bonus_service_score($serviceAvgMinutes, $serviceTargetMinute, (float)($serviceMetric['avg_score'] ?? 100));
        }

        $payoutAmount = round($basePoolAmount * (float)$targetCtx['target_multiplier'] * $configPayoutMultiplier, 2);

        $shiftRows = $this->get_bonus_shift_rows($bonusDate);
        if (empty($shiftRows)) {
            return ['ok' => false, 'message' => 'Tidak ada data shift untuk tanggal bonus ini.'];
        }

        $shiftSalesMap = [];
        $shiftTotalGross = 0.00;
        foreach ($shiftRows as $shiftRow) {
            $shiftId = (int)($shiftRow['id'] ?? 0);
            if ($shiftId <= 0) {
                continue;
            }
            $startTime = (string)($shiftRow['start_time'] ?? '00:00:00');
            $endTime = (string)($shiftRow['end_time'] ?? '23:59:59');
            $isOvernight = (int)($shiftRow['is_overnight'] ?? 0) === 1 || $endTime < $startTime;

            $shiftSalesDb = $this->db->select('COALESCE(SUM(o.grand_total),0) AS gross_sales_amount, COUNT(*) AS total_orders', false)
                ->from('pos_order o')
                ->where("DATE(COALESCE(o.paid_at, o.ordered_at)) = " . $this->db->escape($bonusDate), null, false)
                ->where("{$paymentStatusSql} IN ('PAID','PAID_PARTIAL')", null, false);
            if ((int)($rule['outlet_id'] ?? 0) > 0 && $this->table_has_field('pos_order', 'outlet_id')) {
                $shiftSalesDb->where('o.outlet_id', (int)$rule['outlet_id']);
            }
            if ($isOvernight) {
                $shiftSalesDb->where("(TIME(o.ordered_at) >= " . $this->db->escape($startTime) . " OR TIME(o.ordered_at) <= " . $this->db->escape($endTime) . ")", null, false);
            } else {
                $shiftSalesDb->where("TIME(o.ordered_at) >= " . $this->db->escape($startTime), null, false)
                    ->where("TIME(o.ordered_at) <= " . $this->db->escape($endTime), null, false);
            }
            $shiftSalesRow = $shiftSalesDb->get()->row_array() ?: [];
            $shiftGross = round((float)($shiftSalesRow['gross_sales_amount'] ?? 0), 2);
            $shiftTotalGross += $shiftGross;
            $shiftSalesMap[$shiftId] = [
                'gross_sales_amount' => $shiftGross,
                'total_orders' => (int)($shiftSalesRow['total_orders'] ?? 0),
            ];
        }

        $shiftServiceMetricMap = [];
        if ($this->db->table_exists('pay_bonus_service_metric_daily')) {
            foreach ($shiftRows as $shiftRow) {
                $shiftId = (int)($shiftRow['id'] ?? 0);
                if ($shiftId <= 0) {
                    continue;
                }
                $metricDb = $this->db->select('COALESCE(AVG(score_percent),100) AS avg_score, COALESCE(AVG(avg_service_minutes),0) AS avg_minutes', false)
                    ->from('pay_bonus_service_metric_daily')
                    ->where('metric_date', $bonusDate)
                    ->where('shift_id', $shiftId)
                    ->where('division_id IS NULL', null, false);
                if ((int)($rule['outlet_id'] ?? 0) > 0) {
                    $metricDb->where('outlet_id', (int)$rule['outlet_id']);
                } else {
                    $metricDb->where('outlet_id IS NULL', null, false);
                }
                $metricRow = $metricDb->get()->row_array() ?: [];
                $shiftAvgMinutes = round((float)($metricRow['avg_minutes'] ?? 0), 2);
                $shiftServiceMetricMap[$shiftId] = [
                    'avg_minutes' => $shiftAvgMinutes,
                    'score_percent' => $this->normalize_bonus_service_score($shiftAvgMinutes, $serviceTargetMinute, (float)($metricRow['avg_score'] ?? $serviceScorePercent)),
                ];
            }
        }

        $this->sync_bonus_auto_penalties($bonusDate, 0, $actorUserId);

        $employeeDb = $this->db->select("
                ad.employee_id,
                ad.attendance_date,
                ad.shift_id,
                ad.attendance_status,
                ad.late_minutes,
                ad.early_leave_minutes,
                e.division_id,
                e.position_id,
                e.employee_name
            ", false)
            ->from('att_daily ad')
            ->join('org_employee e', 'e.id = ad.employee_id', 'inner')
            ->where('ad.attendance_date', $bonusDate)
            ->where_in('ad.attendance_status', ['PRESENT', 'LATE', 'HOLIDAY']);
        if ((int)($rule['division_id'] ?? 0) > 0) {
            $employeeDb->where('e.division_id', (int)$rule['division_id']);
        }
        $employeeRows = $employeeDb->get()->result_array();
        if (empty($employeeRows)) {
            return ['ok' => false, 'message' => 'Tidak ada pegawai hadir yang cocok untuk rule ini pada tanggal tersebut.'];
        }

        $weightRows = [];
        if ($this->db->table_exists('pay_bonus_weight_rule')) {
            $weightDb = $this->db->from('pay_bonus_weight_rule')
                ->where('is_active', 1)
                ->group_start()
                    ->where('rule_id', $ruleId);
            if ($this->table_has_field('pay_bonus_weight_rule', 'target_frequency')) {
                $weightDb->or_group_start()
                    ->where('rule_id IS NULL', null, false)
                    ->where_in('target_frequency', ['ALL', 'DAILY'])
                    ->group_end();
            } else {
                $weightDb->or_where('rule_id IS NULL', null, false);
            }
            $weightDb->group_end();
            $weightRows = $weightDb->get()->result_array();
        }
        $weightMap = ['DIVISION' => [], 'POSITION' => [], 'EMPLOYEE' => [], 'SHIFT' => []];
        foreach ($weightRows as $weightRow) {
            $scope = strtoupper((string)($weightRow['weight_scope'] ?? ''));
            $scopeId = (int)($weightRow['scope_id'] ?? 0);
            if ($scope !== '' && $scopeId > 0) {
                $weightMap[$scope][$scopeId] = [
                    'point_weight' => (float)($weightRow['point_weight'] ?? 1),
                    'pool_weight' => (float)($weightRow['pool_weight'] ?? 1),
                ];
            }
        }

        $penaltyRows = $this->db->select("
                employee_id,
                division_id,
                shift_id,
                penalty_scope,
                COALESCE(SUM(points_deducted),0) AS penalty_point,
                COALESCE(SUM(amount_deducted),0) AS penalty_amount
            ", false)
            ->from('pay_bonus_penalty_event')
            ->where('penalty_date', $bonusDate)
            ->where('status', 'APPROVED')
            ->group_by(['employee_id', 'division_id', 'shift_id', 'penalty_scope'])
            ->get()->result_array();
        $personalPenaltyMap = [];
        $teamPenaltyMap = [];
        foreach ($penaltyRows as $penaltyRow) {
            $scope = strtoupper((string)($penaltyRow['penalty_scope'] ?? 'PERSONAL'));
            if ($scope === 'TEAM') {
                $teamPenaltyMap[(int)($penaltyRow['division_id'] ?? 0) . ':' . (int)($penaltyRow['shift_id'] ?? 0)] = [
                    'point' => (float)($penaltyRow['penalty_point'] ?? 0),
                    'amount' => (float)($penaltyRow['penalty_amount'] ?? 0),
                ];
            } else {
                $personalPenaltyMap[(int)($penaltyRow['employee_id'] ?? 0)] = [
                    'point' => (float)($penaltyRow['penalty_point'] ?? 0),
                    'amount' => (float)($penaltyRow['penalty_amount'] ?? 0),
                ];
            }
        }

        $employeeDraftRows = [];
        $totalPointWeight = 0.0000;
        foreach ($employeeRows as $employeeRow) {
            $empId = (int)($employeeRow['employee_id'] ?? 0);
            $shiftId = (int)($employeeRow['shift_id'] ?? 0);
            if ($empId <= 0 || $shiftId <= 0) {
                continue;
            }

            $divisionWeight = (float)($weightMap['DIVISION'][(int)($employeeRow['division_id'] ?? 0)]['point_weight'] ?? 1);
            $positionWeight = (float)($weightMap['POSITION'][(int)($employeeRow['position_id'] ?? 0)]['point_weight'] ?? 1);
            $employeeWeight = (float)($weightMap['EMPLOYEE'][$empId]['point_weight'] ?? 1);
            $shiftWeight = (float)($weightMap['SHIFT'][$shiftId]['point_weight'] ?? 1);
            $attendanceWeight = strtoupper((string)($employeeRow['attendance_status'] ?? '')) === 'LATE'
                ? max(0.1, 1 - ((float)($rule['attendance_weight'] ?? 1) * 0.1))
                : 1.0;
            $targetWeight = max(0, (float)$targetCtx['target_multiplier']);
            $shiftServiceMetric = $shiftServiceMetricMap[$shiftId] ?? ['score_percent' => $serviceScorePercent, 'avg_minutes' => $serviceAvgMinutes];
            $serviceWeight = !empty($rule['include_service_time_factor'])
                ? max(0.1, min(1.2, ((float)($shiftServiceMetric['score_percent'] ?? $serviceScorePercent)) / 100))
                : 1.0;
            $peerWeight = 1.0;
            if (!empty($rule['include_peer_review_factor']) && $this->db->table_exists('perf_peer_feedback')) {
                $peerRow = $this->db->select('COALESCE(AVG(star_rating),0) AS avg_star', false)
                    ->from('perf_peer_feedback')
                    ->where('to_employee_id', $empId)
                    ->where('feedback_date', $bonusDate)
                    ->where('status', 'APPROVED')
                    ->get()->row_array();
                $avgStar = round((float)($peerRow['avg_star'] ?? 0), 2);
                if ($avgStar > 0) {
                    $peerWeight = max(0.6, min(1.2, $avgStar / 5));
                }
            }

            $personalPenalty = $personalPenaltyMap[$empId] ?? ['point' => 0.0, 'amount' => 0.0];
            $teamPenalty = $teamPenaltyMap[(int)($employeeRow['division_id'] ?? 0) . ':' . $shiftId] ?? ['point' => 0.0, 'amount' => 0.0];
            $shiftSales = $shiftSalesMap[$shiftId] ?? ['gross_sales_amount' => 0.0, 'total_orders' => 0];
            $shiftNetSales = $shiftTotalGross > 0 ? round($netSalesAmount * ((float)$shiftSales['gross_sales_amount'] / $shiftTotalGross), 2) : 0.00;
            $rawPoint = round(
                $divisionWeight
                * $positionWeight
                * $employeeWeight
                * $shiftWeight
                * $attendanceWeight
                * ($targetWeight > 0 ? $targetWeight : 1)
                * $serviceWeight
                * $peerWeight,
                4
            );

            $employeeDraftRows[] = [
                'employee_id' => $empId,
                'attendance_date' => $bonusDate,
                'shift_id' => $shiftId,
                'attendance_status' => (string)($employeeRow['attendance_status'] ?? ''),
                'division_weight' => $divisionWeight,
                'position_weight' => $positionWeight,
                'employee_weight' => $employeeWeight,
                'shift_weight' => $shiftWeight,
                'attendance_weight' => $attendanceWeight,
                'target_weight' => $targetWeight > 0 ? $targetWeight : 1.0,
                'service_weight' => $serviceWeight,
                'peer_weight' => $peerWeight,
                'revenue_in_shift' => $shiftNetSales,
                'raw_point' => $rawPoint,
                'penalty_point' => round((float)$personalPenalty['point'] + (float)$teamPenalty['point'], 4),
                'penalty_amount_direct' => round((float)$personalPenalty['amount'] + (float)$teamPenalty['amount'], 2),
            ];
            $totalPointWeight += $rawPoint;
        }

        if (empty($employeeDraftRows)) {
            return ['ok' => false, 'message' => 'Tidak ada pegawai eligible setelah rule dan filter diterapkan.'];
        }

        $this->db->trans_start();
        $this->db->insert('pay_bonus_pool_daily', [
            'bonus_date' => $bonusDate,
            'config_id' => (int)$rule['config_id'],
            'rule_id' => $ruleId,
            'outlet_id' => (int)($rule['outlet_id'] ?? 0) > 0 ? (int)$rule['outlet_id'] : null,
            'division_id' => (int)($rule['division_id'] ?? 0) > 0 ? (int)$rule['division_id'] : null,
            'target_plan_id' => $targetCtx['target_plan_id'],
            'target_score_percent' => round((float)$targetCtx['target_score_percent'], 2),
            'target_gate_passed' => (int)$targetCtx['target_gate_passed'],
            'gross_sales_amount' => $grossSalesAmount,
            'net_sales_amount' => $netSalesAmount,
            'refund_amount' => $refundAmount,
            'service_score_percent' => $serviceScorePercent,
            'pool_amount' => $basePoolAmount,
            'payout_amount' => $payoutAmount,
            'total_employee_point' => round($totalPointWeight, 4),
            'total_employee_amount' => $payoutAmount,
            'approval_status' => 'DRAFT',
            'notes' => trim($targetCtx['target_notes'] . ' | ' . (string)($dailyThresholdCtx['notes'] ?? 'Ambang omzet harian') . ' | Omzet bersih ' . number_format($netSalesAmount, 2, ',', '.') . ' | Service ' . number_format($serviceScorePercent, 2, ',', '.') . '%'),
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $poolId = (int)$this->db->insert_id();

        $shiftPoolIdMap = [];
        foreach ($shiftRows as $shiftRow) {
            $shiftId = (int)($shiftRow['id'] ?? 0);
            if ($shiftId <= 0) {
                continue;
            }
            $shiftSales = $shiftSalesMap[$shiftId] ?? ['gross_sales_amount' => 0.0, 'total_orders' => 0];
            $shiftNetSales = $shiftTotalGross > 0 ? round($netSalesAmount * ((float)$shiftSales['gross_sales_amount'] / $shiftTotalGross), 2) : 0.00;
            $shiftPoolAmount = $netSalesAmount > 0 ? round($payoutAmount * ($shiftNetSales / max($netSalesAmount, 0.01)), 2) : 0.00;
            $shiftServiceScore = (float)($shiftServiceMetricMap[$shiftId]['score_percent'] ?? 100.00);
            if ($this->db->table_exists('pay_bonus_service_metric_daily')) {
                $avgServiceMinutes = round((float)($shiftServiceMetricMap[$shiftId]['avg_minutes'] ?? 0), 2);
            } else {
                $avgServiceMinutes = 0.00;
            }

            $this->db->insert('pay_bonus_pool_shift', [
                'pool_id' => $poolId,
                'shift_id' => $shiftId,
                'shift_start' => (string)($shiftRow['start_time'] ?? null) ?: null,
                'shift_end' => (string)($shiftRow['end_time'] ?? null) ?: null,
                'gross_sales_amount' => round((float)$shiftSales['gross_sales_amount'], 2),
                'net_sales_amount' => $shiftNetSales,
                'total_orders' => (int)($shiftSales['total_orders'] ?? 0),
                'avg_service_minutes' => $avgServiceMinutes,
                'service_score_percent' => $shiftServiceScore,
                'shift_point_weight' => 1.0000,
                'shift_pool_amount' => $shiftPoolAmount,
                'employee_count' => count(array_filter($employeeDraftRows, static function ($row) use ($shiftId) {
                    return (int)($row['shift_id'] ?? 0) === $shiftId;
                })),
                'notes' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $shiftPoolIdMap[$shiftId] = (int)$this->db->insert_id();
        }

        foreach ($employeeDraftRows as $draftRow) {
            $rawAmount = $totalPointWeight > 0 ? round($payoutAmount * ((float)$draftRow['raw_point'] / $totalPointWeight), 2) : 0.00;
            $penaltyPoint = (float)$draftRow['penalty_point'];
            $penaltyAmountDirect = (float)($draftRow['penalty_amount_direct'] ?? 0);
            $penaltyAmountByPoint = 0.00;
            $pointPenaltyMode = strtoupper((string)($rule['point_penalty_currency_mode'] ?? 'PERCENT_SHARE'));
            $pointPenaltyValue = (float)($rule['point_penalty_currency_value'] ?? 5);
            if ($penaltyPoint > 0.00001 && $pointPenaltyValue > 0) {
                if ($pointPenaltyMode === 'FIXED_RUPIAH') {
                    $penaltyAmountByPoint = round($penaltyPoint * $pointPenaltyValue, 2);
                } elseif ($pointPenaltyMode === 'PERCENT_SHARE') {
                    $penaltyPercent = min(100, max(0, $penaltyPoint * $pointPenaltyValue));
                    $penaltyAmountByPoint = round($rawAmount * ($penaltyPercent / 100), 2);
                }
            }
            $totalPenaltyAmount = round(min($rawAmount, $penaltyAmountDirect + $penaltyAmountByPoint), 2);
            $finalPoint = round(max(0, (float)$draftRow['raw_point'] - $penaltyPoint), 4);
            $finalAmount = round(max(0, $rawAmount - $totalPenaltyAmount), 2);
            $this->db->insert('pay_bonus_employee_daily', [
                'pool_id' => $poolId,
                'pool_shift_id' => $shiftPoolIdMap[(int)$draftRow['shift_id']] ?? null,
                'employee_id' => (int)$draftRow['employee_id'],
                'attendance_date' => $draftRow['attendance_date'],
                'shift_id' => (int)$draftRow['shift_id'] > 0 ? (int)$draftRow['shift_id'] : null,
                'attendance_status' => $draftRow['attendance_status'],
                'division_weight' => $draftRow['division_weight'],
                'position_weight' => $draftRow['position_weight'],
                'employee_weight' => $draftRow['employee_weight'],
                'shift_weight' => $draftRow['shift_weight'],
                'attendance_weight' => $draftRow['attendance_weight'],
                'target_weight' => $draftRow['target_weight'],
                'service_weight' => $draftRow['service_weight'],
                'peer_weight' => $draftRow['peer_weight'],
                'revenue_in_shift' => $draftRow['revenue_in_shift'],
                'raw_point' => $draftRow['raw_point'],
                'raw_amount' => $rawAmount,
                'penalty_point' => $draftRow['penalty_point'],
                'penalty_amount' => $totalPenaltyAmount,
                'final_point' => $finalPoint,
                'final_amount' => $finalAmount,
                'approval_status' => 'DRAFT',
                'notes' => $penaltyAmountByPoint > 0
                    ? ('Konversi penalti poin: ' . $pointPenaltyMode . ' x ' . number_format($pointPenaltyValue, 2, ',', '.'))
                    : null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->db->trans_complete();
        if (!$this->db->trans_status()) {
            return ['ok' => false, 'message' => 'Gagal menyimpan draft pool bonus harian.'];
        }

        return [
            'ok' => true,
            'message' => 'Draft pool bonus harian berhasil dibuat.',
            'pool_id' => $poolId,
        ];
    }

    public function generate_bonus_pool_daily_bulk(string $dateStart, string $dateEnd, int $ruleId, int $actorUserId = 0, int $forcedDailyTargetPlanId = 0): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStart) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEnd)) {
            return ['ok' => false, 'message' => 'Range tanggal bonus belum valid.'];
        }

        if ($dateEnd < $dateStart) {
            [$dateStart, $dateEnd] = [$dateEnd, $dateStart];
        }

        $startTs = strtotime($dateStart);
        $endTs = strtotime($dateEnd);
        if ($startTs === false || $endTs === false) {
            return ['ok' => false, 'message' => 'Range tanggal bonus belum valid.'];
        }

        $success = [];
        $failed = [];
        for ($cursor = $startTs; $cursor <= $endTs; $cursor = strtotime('+1 day', $cursor)) {
            $bonusDate = date('Y-m-d', $cursor);
            $result = $this->generate_bonus_pool_daily($bonusDate, $ruleId, $actorUserId, $forcedDailyTargetPlanId);
            if (!empty($result['ok'])) {
                $success[] = $bonusDate;
            } else {
                $failed[] = $bonusDate . ': ' . (string)($result['message'] ?? 'gagal');
            }
        }

        if (empty($success)) {
            return ['ok' => false, 'message' => 'Tidak ada pool yang berhasil dibuat. ' . implode(' | ', $failed)];
        }

        $message = count($success) . ' tanggal berhasil digenerate.';
        if (!empty($failed)) {
            $message .= ' Beberapa tanggal dilewati: ' . implode(' | ', array_slice($failed, 0, 5));
        }

        return [
            'ok' => true,
            'message' => $message,
            'success_dates' => $success,
            'failed_rows' => $failed,
        ];
    }

    public function approve_bonus_pool_daily(int $poolId, int $actorUserId = 0): array
    {
        if ($poolId <= 0 || !$this->db->table_exists('pay_bonus_pool_daily')) {
            return ['ok' => false, 'message' => 'Pool bonus tidak ditemukan.'];
        }

        $pool = $this->db->from('pay_bonus_pool_daily')
            ->where('id', $poolId)
            ->limit(1)
            ->get()->row_array();
        if (!$pool) {
            return ['ok' => false, 'message' => 'Pool bonus harian tidak ditemukan.'];
        }

        $status = strtoupper((string)($pool['approval_status'] ?? 'DRAFT'));
        if ($status === 'VOID') {
            return ['ok' => false, 'message' => 'Pool yang sudah VOID tidak bisa dipublikasikan.'];
        }
        if ($status === 'APPROVED') {
            return ['ok' => true, 'message' => 'Pool bonus ini sudah dipublikasikan sebelumnya.', 'pool_id' => $poolId];
        }

        $now = date('Y-m-d H:i:s');
        $this->db->trans_start();
        $this->db->where('id', $poolId)->update('pay_bonus_pool_daily', [
            'approval_status' => 'APPROVED',
            'approved_by' => $actorUserId > 0 ? $actorUserId : null,
            'approved_at' => $now,
            'updated_at' => $now,
        ]);
        if ($this->db->table_exists('pay_bonus_employee_daily')) {
            $this->db->where('pool_id', $poolId)->update('pay_bonus_employee_daily', [
                'approval_status' => 'APPROVED',
                'updated_at' => $now,
            ]);
        }
        $this->db->trans_complete();

        if (!$this->db->trans_status()) {
            return ['ok' => false, 'message' => 'Gagal mempublikasikan pool bonus harian.'];
        }

        $month = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($pool['bonus_date'] ?? ''))
            ? substr((string)$pool['bonus_date'], 0, 7)
            : date('Y-m');
        $this->refresh_bonus_monthly_summary($month, $actorUserId);

        return ['ok' => true, 'message' => 'Pool bonus harian berhasil dipublikasikan ke portal pegawai.', 'pool_id' => $poolId];
    }

    private function generate_bonus_reference_code(string $prefix, string $seed = ''): string
    {
        $hash = strtoupper(substr(sha1($prefix . '|' . $seed . '|' . microtime(true) . '|' . mt_rand()), 0, 6));
        return strtoupper(trim($prefix)) . '-' . date('ymd') . '-' . $hash;
    }

    public function save_bonus_rule(array $payload, int $actorUserId = 0): array
    {
        if (!$this->db->table_exists('pay_bonus_rule') || !$this->db->table_exists('pay_bonus_config')) {
            return ['ok' => false, 'message' => 'Tabel bonus belum tersedia. Jalankan migration bonus terlebih dahulu.'];
        }

        $id = (int)($payload['id'] ?? 0);
        $configId = (int)($payload['config_id'] ?? 0);
        $ruleName = trim((string)($payload['rule_name'] ?? ''));
        $ruleCode = strtoupper(trim((string)($payload['rule_code'] ?? '')));
        $outletId = (int)($payload['outlet_id'] ?? 0);
        $divisionId = (int)($payload['division_id'] ?? 0);
        $targetPlanId = (int)($payload['linked_target_plan_id'] ?? 0);
        $dailyTargetPlanId = (int)($payload['daily_target_plan_id'] ?? 0);
        $targetGateMode = strtoupper(trim((string)($payload['target_gate_mode'] ?? 'WEIGHTED_SCORE')));
        $phBonusMode = strtoupper(trim((string)($payload['ph_bonus_mode'] ?? 'EXCLUDE')));
        $notes = trim((string)($payload['notes'] ?? ''));

        if ($configId <= 0 || !$this->db->where('id', $configId)->count_all_results('pay_bonus_config')) {
            return ['ok' => false, 'message' => 'Konfigurasi bonus wajib dipilih.'];
        }
        if ($ruleName === '') {
            return ['ok' => false, 'message' => 'Nama aturan bonus wajib diisi.'];
        }
        if (!in_array($targetGateMode, ['NONE', 'ALL_REQUIRED', 'WEIGHTED_SCORE'], true)) {
            $targetGateMode = 'WEIGHTED_SCORE';
        }
        if (!in_array($phBonusMode, ['ALLOW', 'EXCLUDE', 'REDUCE'], true)) {
            $phBonusMode = 'EXCLUDE';
        }
        if ($ruleCode === '') {
            $ruleCode = $this->generate_bonus_reference_code('BONUS-RULE', $ruleName);
        }

        $dbPayload = [
            'config_id' => $configId,
            'rule_code' => $ruleCode,
            'rule_name' => $ruleName,
            'outlet_id' => $outletId > 0 ? $outletId : null,
            'division_id' => $divisionId > 0 ? $divisionId : null,
            'linked_target_plan_id' => ($targetPlanId > 0 && $this->db->table_exists('fin_target_plan')) ? $targetPlanId : null,
            'min_target_score' => round((float)($payload['min_target_score'] ?? 100), 2),
            'target_gate_mode' => $targetGateMode,
            'ph_bonus_mode' => $phBonusMode,
            'ph_point_deduction' => round((float)($payload['ph_point_deduction'] ?? 0), 4),
            'service_time_target_minute' => round((float)($payload['service_time_target_minute'] ?? 0), 2),
            'service_time_weight' => round((float)($payload['service_time_weight'] ?? 0), 4),
            'shift_revenue_weight' => round((float)($payload['shift_revenue_weight'] ?? 1), 4),
            'peer_review_weight' => round((float)($payload['peer_review_weight'] ?? 0), 4),
            'attendance_weight' => round((float)($payload['attendance_weight'] ?? 1), 4),
            'manual_penalty_weight' => round((float)($payload['manual_penalty_weight'] ?? 1), 4),
            'is_active' => !empty($payload['is_active']) ? 1 : 0,
            'notes' => $notes !== '' ? $notes : null,
        ];
        if ($this->table_has_field('pay_bonus_rule', 'daily_target_plan_id')) {
            $dbPayload['daily_target_plan_id'] = ($dailyTargetPlanId > 0 && $this->db->table_exists('fin_target_plan')) ? $dailyTargetPlanId : null;
        }
        if ($this->table_has_field('pay_bonus_rule', 'threshold_amount')) {
            $dbPayload['threshold_amount'] = round((float)($payload['threshold_amount'] ?? 0), 2);
        }
        if ($this->table_has_field('pay_bonus_rule', 'pool_formula_type')) {
            $poolFormulaType = strtoupper(trim((string)($payload['pool_formula_type'] ?? 'PERCENTAGE')));
            if (!in_array($poolFormulaType, ['PERCENTAGE', 'FIXED_STEP'], true)) {
                $poolFormulaType = 'PERCENTAGE';
            }
            $dbPayload['pool_formula_type'] = $poolFormulaType;
        }
        if ($this->table_has_field('pay_bonus_rule', 'pool_formula_value')) {
            $dbPayload['pool_formula_value'] = round((float)($payload['pool_formula_value'] ?? 0), 4);
        }
        if ($this->table_has_field('pay_bonus_rule', 'min_shift_base_pct')) {
            $dbPayload['min_shift_base_pct'] = round((float)($payload['min_shift_base_pct'] ?? 30), 2);
        }

        $exists = $this->db->select('id')
            ->from('pay_bonus_rule')
            ->where('rule_code', $ruleCode);
        if ($id > 0) {
            $exists->where('id <>', $id);
        }
        if ($exists->limit(1)->get()->row_array()) {
            return ['ok' => false, 'message' => 'Kode aturan bonus sudah dipakai.'];
        }

        if ($id > 0) {
            $current = $this->db->from('pay_bonus_rule')->where('id', $id)->limit(1)->get()->row_array();
            if (!$current) {
                return ['ok' => false, 'message' => 'Aturan bonus yang ingin diubah tidak ditemukan.'];
            }
            if ($this->table_has_field('pay_bonus_rule', 'approved_by')) {
                $dbPayload['approved_by'] = $actorUserId > 0 ? $actorUserId : null;
            }
            $this->db->where('id', $id)->update('pay_bonus_rule', $dbPayload);
            return ['ok' => true, 'message' => 'Aturan bonus berhasil diperbarui.', 'id' => $id];
        }

        if ($this->table_has_field('pay_bonus_rule', 'created_by')) {
            $dbPayload['created_by'] = $actorUserId > 0 ? $actorUserId : null;
        }
        $this->db->insert('pay_bonus_rule', $dbPayload);
        return ['ok' => true, 'message' => 'Aturan bonus berhasil ditambahkan.', 'id' => (int)$this->db->insert_id()];
    }

    public function save_bonus_weight_rule(array $payload): array
    {
        if (!$this->db->table_exists('pay_bonus_weight_rule')) {
            return ['ok' => false, 'message' => 'Tabel bobot bonus belum tersedia. Jalankan SQL foundation bonus dulu.'];
        }

        $id = (int)($payload['id'] ?? 0);
        $ruleId = (int)($payload['rule_id'] ?? 0);
        $weightScope = strtoupper(trim((string)($payload['weight_scope'] ?? '')));
        $scopeId = (int)($payload['scope_id'] ?? 0);
        $targetFrequency = strtoupper(trim((string)($payload['target_frequency'] ?? 'ALL')));
        $validScopes = ['DIVISION', 'POSITION', 'EMPLOYEE', 'SHIFT'];

        if ($ruleId > 0 && (!$this->db->table_exists('pay_bonus_rule') || !$this->db->where('id', $ruleId)->count_all_results('pay_bonus_rule'))) {
            return ['ok' => false, 'message' => 'Skema bonus yang dipilih tidak ditemukan.'];
        }
        if (!in_array($weightScope, $validScopes, true)) {
            return ['ok' => false, 'message' => 'Jenis bobot bonus belum valid.'];
        }
        if ($scopeId <= 0) {
            return ['ok' => false, 'message' => 'Target bobot bonus wajib dipilih.'];
        }
        if (!in_array($targetFrequency, ['ALL', 'DAILY', 'MONTHLY'], true)) {
            $targetFrequency = 'ALL';
        }

        $dbPayload = [
            'rule_id' => $ruleId > 0 ? $ruleId : null,
            'weight_scope' => $weightScope,
            'scope_id' => $scopeId,
            'point_weight' => round((float)($payload['point_weight'] ?? 1), 4),
            'pool_weight' => round((float)($payload['pool_weight'] ?? 1), 4),
            'is_active' => !empty($payload['is_active']) ? 1 : 0,
            'notes' => trim((string)($payload['notes'] ?? '')) ?: null,
        ];
        if ($this->table_has_field('pay_bonus_weight_rule', 'target_frequency')) {
            $dbPayload['target_frequency'] = $targetFrequency;
        }

        $exists = $this->db->from('pay_bonus_weight_rule')
            ->where('weight_scope', $weightScope)
            ->where('scope_id', $scopeId);
        if ($ruleId > 0) {
            $exists->where('rule_id', $ruleId);
        } else {
            $exists->where('rule_id IS NULL', null, false);
        }
        if ($this->table_has_field('pay_bonus_weight_rule', 'target_frequency')) {
            $exists->where('target_frequency', $targetFrequency);
        }
        if ($id > 0) {
            $exists->where('id <>', $id);
        }
        if ($exists->count_all_results() > 0) {
            return ['ok' => false, 'message' => 'Bobot untuk kombinasi scope ini sudah ada. Edit yang lama saja supaya tidak dobel.'];
        }

        if ($id > 0) {
            $current = $this->db->from('pay_bonus_weight_rule')->where('id', $id)->limit(1)->get()->row_array();
            if (!$current) {
                return ['ok' => false, 'message' => 'Bobot bonus yang ingin diubah tidak ditemukan.'];
            }
            $this->db->where('id', $id)->update('pay_bonus_weight_rule', $dbPayload);
            return ['ok' => true, 'message' => 'Bobot bonus berhasil diperbarui.', 'id' => $id];
        }

        $this->db->insert('pay_bonus_weight_rule', $dbPayload);
        return ['ok' => true, 'message' => 'Bobot bonus berhasil ditambahkan.', 'id' => (int)$this->db->insert_id()];
    }

    public function deactivate_bonus_rule(int $id): array
    {
        if ($id <= 0 || !$this->db->table_exists('pay_bonus_rule')) {
            return ['ok' => false, 'message' => 'Skema distribusi bonus tidak ditemukan.'];
        }

        $row = $this->db->from('pay_bonus_rule')->where('id', $id)->limit(1)->get()->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Skema distribusi bonus tidak ditemukan.'];
        }

        $this->db->where('id', $id)->update('pay_bonus_rule', [
            'is_active' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return ['ok' => true, 'message' => 'Skema distribusi bonus berhasil dinonaktifkan.'];
    }

    public function deactivate_bonus_weight_rule(int $id): array
    {
        if ($id <= 0 || !$this->db->table_exists('pay_bonus_weight_rule')) {
            return ['ok' => false, 'message' => 'Bobot bonus tidak ditemukan.'];
        }

        $row = $this->db->from('pay_bonus_weight_rule')->where('id', $id)->limit(1)->get()->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Bobot bonus tidak ditemukan.'];
        }

        $this->db->where('id', $id)->update('pay_bonus_weight_rule', [
            'is_active' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return ['ok' => true, 'message' => 'Bobot bonus berhasil dinonaktifkan.'];
    }

    public function save_bonus_penalty_type(array $payload): array
    {
        if (!$this->db->table_exists('pay_bonus_penalty_type')) {
            return ['ok' => false, 'message' => 'Tabel penalti bonus belum tersedia. Jalankan migration bonus terlebih dahulu.'];
        }

        $id = (int)($payload['id'] ?? 0);
        $penaltyName = trim((string)($payload['penalty_name'] ?? ''));
        $penaltyCode = strtoupper(trim((string)($payload['penalty_code'] ?? '')));
        $category = strtoupper(trim((string)($payload['category'] ?? 'OTHER')));
        $appliesScope = strtoupper(trim((string)($payload['applies_scope'] ?? 'BOTH')));
        $deductionMode = strtoupper(trim((string)($payload['deduction_mode'] ?? 'FIXED_POINT')));
        $behaviorMode = strtoupper(trim((string)($payload['behavior_mode'] ?? 'MANUAL')));
        $autoSource = strtoupper(trim((string)($payload['auto_source'] ?? '')));
        $attendanceTrigger = trim((string)($payload['attendance_trigger'] ?? ''));
        $verificationCycle = strtoupper(trim((string)($payload['verification_cycle'] ?? 'PER_EVENT')));
        $notes = trim((string)($payload['notes'] ?? ''));

        if ($penaltyName === '') {
            return ['ok' => false, 'message' => 'Nama penalti wajib diisi.'];
        }
        if (!in_array($category, ['ATTENDANCE', 'DISCIPLINE', 'PERFORMANCE', 'SERVICE', 'PROPERTY', 'SOCIAL_MEDIA', 'HYGIENE', 'OTHER'], true)) {
            $category = 'OTHER';
        }
        if (!in_array($appliesScope, ['PERSONAL', 'TEAM', 'BOTH'], true)) {
            $appliesScope = 'BOTH';
        }
        if (!in_array($deductionMode, ['FIXED_POINT', 'FIXED_AMOUNT', 'VARIABLE'], true)) {
            $deductionMode = 'FIXED_POINT';
        }
        if (!in_array($behaviorMode, ['AUTO', 'MANUAL', 'SEMI_MANUAL'], true)) {
            $behaviorMode = 'MANUAL';
        }
        if (!in_array($verificationCycle, ['PER_EVENT', 'DAILY', 'MONTHLY', 'UNTIL_CHANGED'], true)) {
            $verificationCycle = 'PER_EVENT';
        }
        if ($autoSource !== '' && !in_array($autoSource, ['ATTENDANCE', 'SERVICE', 'TARGET', 'PEER', 'SOCIAL_MEDIA', 'AUDIT', 'CHECKLIST', 'OTHER'], true)) {
            $autoSource = 'OTHER';
        }
        if ($penaltyCode === '') {
            $penaltyCode = $this->generate_bonus_reference_code('BONUS-PEN', $penaltyName);
        }

        $exists = $this->db->select('id')
            ->from('pay_bonus_penalty_type')
            ->where('penalty_code', $penaltyCode);
        if ($id > 0) {
            $exists->where('id <>', $id);
        }
        if ($exists->limit(1)->get()->row_array()) {
            return ['ok' => false, 'message' => 'Kode penalti bonus sudah dipakai.'];
        }

        $dbPayload = [
            'penalty_code' => $penaltyCode,
            'penalty_name' => $penaltyName,
            'category' => $category,
            'default_points_deducted' => round((float)($payload['default_points_deducted'] ?? 0), 4),
            'default_amount_deducted' => round((float)($payload['default_amount_deducted'] ?? 0), 2),
            'applies_scope' => $appliesScope,
            'is_manual_only' => $behaviorMode === 'MANUAL' ? 1 : 0,
            'approval_required' => !empty($payload['approval_required']) ? 1 : 0,
            'is_active' => !empty($payload['is_active']) ? 1 : 0,
            'notes' => $notes !== '' ? $notes : null,
            'sort_order' => (int)($payload['sort_order'] ?? 0),
        ];
        if ($this->table_has_field('pay_bonus_penalty_type', 'deduction_mode')) {
            $dbPayload['deduction_mode'] = $deductionMode;
        }
        if ($this->table_has_field('pay_bonus_penalty_type', 'behavior_mode')) {
            $dbPayload['behavior_mode'] = $behaviorMode;
        }
        if ($this->table_has_field('pay_bonus_penalty_type', 'auto_source')) {
            $dbPayload['auto_source'] = $autoSource !== '' ? $autoSource : null;
        }
        if ($this->table_has_field('pay_bonus_penalty_type', 'attendance_trigger')) {
            $dbPayload['attendance_trigger'] = $attendanceTrigger !== '' ? $attendanceTrigger : null;
        }
        if ($this->table_has_field('pay_bonus_penalty_type', 'verification_cycle')) {
            $dbPayload['verification_cycle'] = $verificationCycle;
        }
        if ($this->table_has_field('pay_bonus_penalty_type', 'requires_evidence')) {
            $dbPayload['requires_evidence'] = !empty($payload['requires_evidence']) ? 1 : 0;
        }

        if ($id > 0) {
            $current = $this->db->from('pay_bonus_penalty_type')->where('id', $id)->limit(1)->get()->row_array();
            if (!$current) {
                return ['ok' => false, 'message' => 'Master penalti yang ingin diubah tidak ditemukan.'];
            }
            $this->db->where('id', $id)->update('pay_bonus_penalty_type', $dbPayload);
            return ['ok' => true, 'message' => 'Master penalti bonus berhasil diperbarui.', 'id' => $id];
        }

        $this->db->insert('pay_bonus_penalty_type', $dbPayload);
        return ['ok' => true, 'message' => 'Master penalti bonus berhasil ditambahkan.', 'id' => (int)$this->db->insert_id()];
    }

    public function deactivate_bonus_penalty_type(int $id): array
    {
        if ($id <= 0 || !$this->db->table_exists('pay_bonus_penalty_type')) {
            return ['ok' => false, 'message' => 'Master penalti bonus tidak ditemukan.'];
        }

        $row = $this->db->from('pay_bonus_penalty_type')->where('id', $id)->limit(1)->get()->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Master penalti bonus tidak ditemukan.'];
        }

        $this->db->where('id', $id)->update('pay_bonus_penalty_type', [
            'is_active' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return ['ok' => true, 'message' => 'Master penalti bonus berhasil dinonaktifkan.'];
    }

    public function save_bonus_penalty_event(array $payload, int $actorUserId = 0): array
    {
        if (!$this->db->table_exists('pay_bonus_penalty_event') || !$this->db->table_exists('pay_bonus_penalty_type')) {
            return ['ok' => false, 'message' => 'Tabel kejadian penalti bonus belum tersedia. Jalankan migration bonus terlebih dahulu.'];
        }

        $penaltyDate = trim((string)($payload['penalty_date'] ?? ''));
        $penaltyTypeId = (int)($payload['penalty_type_id'] ?? 0);
        $ruleId = (int)($payload['rule_id'] ?? 0);
        $employeeId = (int)($payload['employee_id'] ?? 0);
        $divisionId = (int)($payload['division_id'] ?? 0);
        $shiftId = (int)($payload['shift_id'] ?? 0);
        $penaltyScope = strtoupper(trim((string)($payload['penalty_scope'] ?? 'PERSONAL')));
        $status = strtoupper(trim((string)($payload['status'] ?? 'APPROVED')));
        $reasonText = trim((string)($payload['reason_text'] ?? ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $penaltyDate)) {
            return ['ok' => false, 'message' => 'Tanggal penalti wajib diisi.'];
        }
        if ($penaltyTypeId <= 0) {
            return ['ok' => false, 'message' => 'Jenis penalti wajib dipilih.'];
        }
        if (!in_array($penaltyScope, ['PERSONAL', 'TEAM'], true)) {
            $penaltyScope = 'PERSONAL';
        }
        if (!in_array($status, ['DRAFT', 'APPROVED', 'REJECTED', 'VOID'], true)) {
            $status = 'APPROVED';
        }
        if ($penaltyScope === 'PERSONAL' && $employeeId <= 0) {
            return ['ok' => false, 'message' => 'Untuk penalti personal, pegawai wajib dipilih.'];
        }
        if ($penaltyScope === 'TEAM' && $divisionId <= 0) {
            return ['ok' => false, 'message' => 'Untuk penalti tim, divisi wajib dipilih.'];
        }
        if ($reasonText === '') {
            return ['ok' => false, 'message' => 'Alasan penalti wajib diisi.'];
        }

        $type = $this->db->select('id, default_points_deducted, default_amount_deducted')
            ->select($this->table_has_field('pay_bonus_penalty_type', 'behavior_mode') ? 'behavior_mode' : "'MANUAL' AS behavior_mode", false)
            ->from('pay_bonus_penalty_type')
            ->where('id', $penaltyTypeId)
            ->limit(1)
            ->get()->row_array();
        if (!$type) {
            return ['ok' => false, 'message' => 'Jenis penalti tidak ditemukan.'];
        }
        if (strtoupper((string)($type['behavior_mode'] ?? 'MANUAL')) === 'AUTO') {
            return ['ok' => false, 'message' => 'Jenis penalti otomatis tidak perlu diinput manual. Biarkan engine bonus yang membuatnya otomatis.'];
        }

        $points = trim((string)($payload['points_deducted'] ?? '')) === ''
            ? (float)($type['default_points_deducted'] ?? 0)
            : (float)($payload['points_deducted'] ?? 0);
        $amount = trim((string)($payload['amount_deducted'] ?? '')) === ''
            ? (float)($type['default_amount_deducted'] ?? 0)
            : (float)($payload['amount_deducted'] ?? 0);

        $dbPayload = [
            'penalty_date' => $penaltyDate,
            'rule_id' => $ruleId > 0 ? $ruleId : null,
            'penalty_type_id' => $penaltyTypeId,
            'employee_id' => $employeeId > 0 ? $employeeId : null,
            'division_id' => $divisionId > 0 ? $divisionId : null,
            'shift_id' => $shiftId > 0 ? $shiftId : null,
            'penalty_scope' => $penaltyScope,
            'source_type' => 'MANUAL',
            'points_deducted' => round($points, 4),
            'amount_deducted' => round($amount, 2),
            'reason_text' => $reasonText,
            'status' => $status,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'approved_by' => ($status === 'APPROVED' && $actorUserId > 0) ? $actorUserId : null,
            'approved_at' => $status === 'APPROVED' ? date('Y-m-d H:i:s') : null,
        ];

        $this->db->insert('pay_bonus_penalty_event', $dbPayload);
        return ['ok' => true, 'message' => 'Kejadian penalti bonus berhasil disimpan.', 'id' => (int)$this->db->insert_id()];
    }

    public function moderate_peer_feedback(int $feedbackId, string $decision, string $notes = '', int $actorUserId = 0): array
    {
        if ($feedbackId <= 0 || !$this->db->table_exists('perf_peer_feedback')) {
            return ['ok' => false, 'message' => 'Data penilaian 360 tidak ditemukan.'];
        }

        $decision = strtoupper(trim($decision));
        if (!in_array($decision, ['APPROVED', 'REJECTED', 'VOID'], true)) {
            return ['ok' => false, 'message' => 'Aksi moderasi tidak valid.'];
        }

        $row = $this->db->from('perf_peer_feedback')
            ->where('id', $feedbackId)
            ->limit(1)
            ->get()->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Data penilaian 360 tidak ditemukan.'];
        }
        if (strtoupper((string)($row['status'] ?? '')) !== 'SUBMITTED' && $decision !== 'VOID') {
            return ['ok' => false, 'message' => 'Penilaian 360 ini sudah dimoderasi sebelumnya.'];
        }

        $update = [
            'status' => $decision,
            'moderator_id' => $actorUserId > 0 ? $actorUserId : null,
            'moderation_notes' => trim($notes) !== '' ? trim($notes) : null,
        ];
        if ($this->table_has_field('perf_peer_feedback', 'approved_at')) {
            $update['approved_at'] = $decision === 'APPROVED' ? date('Y-m-d H:i:s') : null;
        }

        $this->db->where('id', $feedbackId)->update('perf_peer_feedback', $update);

        $label = $decision === 'APPROVED' ? 'disetujui' : ($decision === 'REJECTED' ? 'ditolak' : 'di-void');
        return ['ok' => true, 'message' => 'Penilaian 360 berhasil ' . $label . '.'];
    }

    public function submit_peer_feedback_batch(int $fromEmployeeId, string $date, array $entries): array
    {
        if ($fromEmployeeId <= 0 || $date === '') {
            return ['ok' => false, 'message' => 'Data penilaian tidak valid.'];
        }
        if (!$this->db->table_exists('perf_peer_feedback')) {
            return ['ok' => false, 'message' => 'Tabel penilaian 360 belum tersedia. Jalankan migration bonus terlebih dahulu.'];
        }

        $saved = 0;
        $errors = [];
        $now = date('Y-m-d H:i:s');

        foreach ($entries as $entry) {
            $toEmployeeId = (int)($entry['to_employee_id'] ?? 0);
            $starRating = (int)($entry['star_rating'] ?? 0);
            $reasonText = trim((string)($entry['reason_text'] ?? ''));
            $shiftId = (int)($entry['shift_id'] ?? 0);

            if ($toEmployeeId <= 0 || $toEmployeeId === $fromEmployeeId) {
                continue;
            }
            if ($starRating < 1 || $starRating > 5) {
                $errors[] = 'Rating harus 1 sampai 5 bintang.';
                continue;
            }
            if ($starRating <= 3 && $reasonText === '') {
                $errors[] = 'Alasan wajib diisi untuk rating 1 sampai 3.';
                continue;
            }

            $exists = $this->db->select('id')
                ->from('perf_peer_feedback')
                ->where('feedback_date', $date)
                ->where('from_employee_id', $fromEmployeeId)
                ->where('to_employee_id', $toEmployeeId)
                ->where('status <>', 'VOID')
                ->limit(1)
                ->get()->row_array();
            if ($exists) {
                continue;
            }

            $this->db->insert('perf_peer_feedback', [
                'feedback_date' => $date,
                'from_employee_id' => $fromEmployeeId,
                'to_employee_id' => $toEmployeeId,
                'shift_id' => $shiftId > 0 ? $shiftId : null,
                'star_rating' => $starRating,
                'reason_text' => $reasonText !== '' ? $reasonText : null,
                'status' => 'SUBMITTED',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $saved++;
        }

        if ($saved <= 0 && !empty($errors)) {
            return ['ok' => false, 'message' => implode(' ', array_unique($errors))];
        }

        if ($saved <= 0) {
            return ['ok' => false, 'message' => 'Tidak ada penilaian baru yang disimpan.'];
        }

        return [
            'ok' => true,
            'message' => 'Penilaian rekan kerja berhasil disimpan.',
            'saved_count' => $saved,
            'warning' => !empty($errors) ? implode(' ', array_unique($errors)) : '',
        ];
    }
}
