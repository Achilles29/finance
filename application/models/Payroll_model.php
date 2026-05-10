<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Payroll_model extends CI_Model
{
    private $attDailyFieldCache = [];

    private function att_daily_has_field(string $field): bool
    {
        if (!array_key_exists($field, $this->attDailyFieldCache)) {
            $this->attDailyFieldCache[$field] = $this->db->field_exists($field, 'att_daily');
        }
        return (bool)$this->attDailyFieldCache[$field];
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
}
