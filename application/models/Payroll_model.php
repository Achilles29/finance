<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Payroll_model extends CI_Model
{
    private $attDailyFieldCache = [];
    private $tableFieldCache = [];

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
            $oldEmployeeId = (int)($exists['employee_id'] ?? 0);
            $oldDate = (string)($exists['adjustment_date'] ?? '');
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
        $this->db->where('id', $id)->delete('pay_manual_adjustment');
        $this->recompute_daily_manual_adjustment((int)$exists['employee_id'], (string)$exists['adjustment_date']);
        return ['ok' => true, 'message' => 'Data penyesuaian berhasil dihapus.'];
    }

    public function get_company_account_options(): array
    {
        if (!$this->db->table_exists('fin_company_account')) {
            return [];
        }
        return $this->db->select("id AS value, CONCAT(account_code, ' - ', account_name) AS label, account_type, bank_name, account_no", false)
            ->from('fin_company_account')
            ->where('is_active', 1)
            ->order_by('is_default', 'DESC')
            ->order_by('account_name', 'ASC')
            ->get()->result_array();
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
            ->where('ad.checkout_at IS NOT NULL', null, false)
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
                    AND ad.checkout_at IS NOT NULL
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
            ->join('att_daily ad', 'ad.employee_id = r.employee_id AND ad.attendance_date >= "' . $this->db->escape_str($periodStart) . '" AND ad.attendance_date <= "' . $this->db->escape_str($periodEnd) . '" AND ad.checkout_at IS NOT NULL', 'left', false)
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

    public function generate_salary_disbursement(array $payload, int $actorUserId): array
    {
        $periodId = (int)($payload['payroll_period_id'] ?? 0);
        $disbursementDate = trim((string)($payload['disbursement_date'] ?? date('Y-m-d')));
        $companyAccountId = (int)($payload['company_account_id'] ?? 0);
        $notes = trim((string)($payload['notes'] ?? ''));

        if ($periodId <= 0) {
            return ['ok' => false, 'message' => 'Payroll period wajib dipilih.'];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $disbursementDate)) {
            return ['ok' => false, 'message' => 'Tanggal pencairan tidak valid.'];
        }
        if ($companyAccountId <= 0) {
            return ['ok' => false, 'message' => 'Rekening sumber mutasi wajib dipilih sebelum generate batch gaji.'];
        }

        $candidates = $this->db->select('
                r.id AS payroll_result_id,
                r.employee_id,
                r.net_pay,
                r.gross_pay,
                r.total_deduction,
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
                e.bank_name,
                e.bank_account_no,
                e.bank_account_name
            ', false)
            ->from('pay_payroll_result r')
            ->join('org_employee e', 'e.id = r.employee_id', 'left')
            ->join('pay_salary_disbursement_line dup', 'dup.payroll_result_id = r.id', 'left')
            ->join('pay_salary_disbursement d', 'd.id = dup.disbursement_id AND d.status <> "VOID"', 'left')
            ->where('r.payroll_period_id', $periodId)
            ->where('COALESCE(r.net_pay,0) >', 0)
            ->where_in('r.status', ['DRAFT', 'FINALIZED'])
            ->where('d.id IS NULL', null, false)
            ->order_by('r.employee_id', 'ASC')
            ->get()->result_array();

        if (empty($candidates)) {
            return ['ok' => false, 'message' => 'Tidak ada kandidat pencairan gaji baru untuk period ini.'];
        }

        $no = $this->next_doc_no('pay_salary_disbursement', 'disbursement_no', 'SAL');
        $total = 0.0;
        foreach ($candidates as $c) {
            $total += (float)($c['net_pay'] ?? 0);
        }

        $this->db->trans_start();
        $this->db->insert('pay_salary_disbursement', [
            'payroll_period_id' => $periodId,
            'disbursement_no' => $no,
            'disbursement_date' => $disbursementDate,
            'company_account_id' => $companyAccountId > 0 ? $companyAccountId : null,
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
        if ($accountId <= 0) {
            $this->db->trans_complete();
            return ['ok' => false, 'message' => 'Rekening sumber mutasi belum diisi pada batch ini.'];
        }

        $pendingRows = $this->db->select('id, payroll_result_id, transfer_amount')
            ->from('pay_salary_disbursement_line')
            ->where('disbursement_id', $disbursementId)
            ->where_in('transfer_status', ['PENDING', 'FAILED'])
            ->get()->result_array();
        $payNowAmount = 0.0;
        foreach ($pendingRows as $row) {
            $payNowAmount += (float)($row['transfer_amount'] ?? 0);
        }
        $payNowAmount = round($payNowAmount, 2);

        if ($payNowAmount > 0) {
            if (!$this->db->table_exists('fin_company_account') || !$this->db->table_exists('fin_account_mutation_log')) {
                $this->db->trans_complete();
                return ['ok' => false, 'message' => 'Tabel mutasi/rekening belum tersedia.'];
            }
            $account = $this->db->query('SELECT * FROM fin_company_account WHERE id = ? AND is_active = 1 LIMIT 1 FOR UPDATE', [$accountId])->row_array();
            if (!$account) {
                $this->db->trans_complete();
                return ['ok' => false, 'message' => 'Rekening sumber batch gaji tidak ditemukan atau nonaktif.'];
            }
            $balanceBefore = round((float)($account['current_balance'] ?? 0), 2);
            if ($balanceBefore < $payNowAmount) {
                $this->db->trans_complete();
                return ['ok' => false, 'message' => 'Saldo rekening tidak cukup untuk menandai batch PAID.'];
            }
            $balanceAfter = round($balanceBefore - $payNowAmount, 2);

            $this->db->where('id', $accountId)->update('fin_company_account', [
                'current_balance' => $balanceAfter,
            ]);

            $mutationDate = (string)($header['disbursement_date'] ?? date('Y-m-d'));
            $this->db->insert('fin_account_mutation_log', [
                'mutation_no' => $this->generate_account_mutation_no($mutationDate),
                'mutation_date' => $mutationDate,
                'account_id' => $accountId,
                'mutation_type' => 'OUT',
                'amount' => $payNowAmount,
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

    public function void_salary_disbursement(int $disbursementId, string $notes = ''): array
    {
        $header = $this->get_salary_disbursement_by_id($disbursementId);
        if (!$header) {
            return ['ok' => false, 'message' => 'Batch gaji tidak ditemukan.'];
        }
        if (strtoupper((string)$header['status']) === 'PAID') {
            return ['ok' => false, 'message' => 'Batch PAID tidak bisa di-VOID.'];
        }

        $now = date('Y-m-d H:i:s');
        $this->db->trans_start();
        $this->db->where('id', $disbursementId)->update('pay_salary_disbursement', [
            'status' => 'VOID',
            'notes' => trim((string)$header['notes'] . ' | VOID: ' . $notes),
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
            $this->db->select("
                ca.*,
                e.employee_code,
                e.employee_name,
                d.division_name,
                COALESCE((SELECT SUM(i.plan_amount) FROM pay_cash_advance_installment i WHERE i.cash_advance_id=ca.id),0) AS installment_plan_total,
                COALESCE((SELECT SUM(i.paid_amount) FROM pay_cash_advance_installment i WHERE i.cash_advance_id=ca.id),0) AS installment_paid_total
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

            if ($paymentMethod === 'SALARY_CUT') {
                $this->register_cash_advance_salary_cut($cashAdvanceId, $header, $pay, $salaryCutDate, $actorUserId);
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
        if (strtoupper((string)($row['status'] ?? 'DRAFT')) === 'SETTLED') {
            return ['ok' => false, 'message' => 'Kasbon SETTLED tidak bisa di-VOID.'];
        }
        $hasPaid = $this->db->select('COUNT(*) AS c', false)
            ->from('pay_cash_advance_installment')
            ->where('cash_advance_id', $id)
            ->where('paid_amount >', 0)
            ->get()->row_array();
        if ((int)($hasPaid['c'] ?? 0) > 0) {
            return ['ok' => false, 'message' => 'Kasbon sudah ada pembayaran, tidak bisa di-VOID.'];
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
}
