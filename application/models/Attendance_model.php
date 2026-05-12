<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Attendance_model extends CI_Model
{
    private $attDailyFieldCache = [];

    private function att_daily_has_field(string $field): bool
    {
        if (!array_key_exists($field, $this->attDailyFieldCache)) {
            $this->attDailyFieldCache[$field] = $this->db->field_exists($field, 'att_daily');
        }
        return (bool)$this->attDailyFieldCache[$field];
    }

    private function filter_existing_fields(string $table, array $payload): array
    {
        $filtered = [];
        foreach ($payload as $key => $value) {
            if ($this->db->field_exists($key, $table)) {
                $filtered[$key] = $value;
            }
        }
        return $filtered;
    }

    public function get_active_policy(): array
    {
        $row = $this->db->from('att_attendance_policy')
            ->where('is_active', 1)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get()->row_array();

        if ($row) {
            if (!isset($row['ph_attendance_mode']) || $row['ph_attendance_mode'] === '') {
                $row['ph_attendance_mode'] = ((int)($row['ph_requires_clock_in_out'] ?? 0) === 1) ? 'MANUAL_CLOCK' : 'AUTO_PRESENT';
            }
            if (!isset($row['ph_grant_mode']) || $row['ph_grant_mode'] === '') {
                $row['ph_grant_mode'] = 'HOLIDAY_ONLY';
            }
            if (!isset($row['ph_grant_holiday_type']) || $row['ph_grant_holiday_type'] === '') {
                $row['ph_grant_holiday_type'] = 'ANY';
            }
            if (!isset($row['ph_grant_requires_checkout'])) {
                $row['ph_grant_requires_checkout'] = 1;
            }
            if (!isset($row['ph_grant_qty_per_day']) || (float)$row['ph_grant_qty_per_day'] <= 0) {
                $row['ph_grant_qty_per_day'] = 1.0;
            }
            if (!isset($row['overtime_calc_mode']) || $row['overtime_calc_mode'] === '') {
                $row['overtime_calc_mode'] = 'AUTO';
            }
            return $row;
        }

        return [
            'policy_code' => 'FINANCE_DEFAULT',
            'policy_name' => 'Finance Default Policy',
            'checkin_open_minutes_before' => 30,
            'enforce_geofence' => 1,
            'require_photo' => 0,
            'late_deduction_per_minute' => 0,
            'alpha_deduction_per_day' => 0,
            'use_basic_salary_daily_rate' => 1,
            'default_work_days_per_month' => 26,
            'attendance_calc_mode' => 'DAILY',
            'payroll_late_deduction_scope' => 'BASIC_ONLY',
            'allowance_late_treatment' => 'FULL_IF_PRESENT',
            'meal_calc_mode' => 'MONTHLY',
            'overtime_calc_mode' => 'AUTO',
            'operation_start_time' => '08:00:00',
            'operation_end_time' => '23:00:00',
            'night_shift_checkout_credit_after' => '22:00:00',
            'night_shift_checkout_credit_to_operation_end' => 1,
            'checkout_close_minutes_after' => 180,
            'enable_late_deduction' => 1,
            'enable_alpha_deduction' => 1,
            'prorate_deduction_scope' => 'BASIC_ONLY',
            'pending_request_scope' => 'SELF_ONLY',
            'pending_approval_levels' => 3,
            'ph_attendance_mode' => 'AUTO_PRESENT',
            'ph_grant_mode' => 'HOLIDAY_ONLY',
            'ph_grant_holiday_type' => 'ANY',
            'ph_grant_requires_checkout' => 1,
            'ph_grant_qty_per_day' => 1,
            'ph_expiry_months' => 3,
            'ph_gets_meal_allowance' => 0,
            'ph_gets_bonus' => 0,
        ];
    }

    public function save_policy(array $payload, array $submitterPositionIds = [], array $verifierByLevel = []): void
    {
        $payload = $this->filter_existing_fields('att_attendance_policy', $payload);
        if (empty($payload)) {
            return;
        }

        $this->db->trans_start();
        $current = $this->db->from('att_attendance_policy')
            ->where('is_active', 1)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get()->row_array();

        if ($this->db->field_exists('is_active', 'att_attendance_policy')) {
            $this->db->set('is_active', 0)->update('att_attendance_policy');
        }

        if ($current) {
            $policyId = (int)$current['id'];
            $this->db->where('id', $policyId)->update('att_attendance_policy', $payload + ['is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
        } else {
            $this->db->insert('att_attendance_policy', $payload + ['is_active' => 1, 'created_at' => date('Y-m-d H:i:s')]);
            $policyId = (int)$this->db->insert_id();
        }

        if (!empty($policyId)) {
            $this->save_pending_submitter_positions($policyId, $submitterPositionIds);
            $this->save_pending_verifier_positions($policyId, $verifierByLevel);
        }
        $this->db->trans_complete();
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
        return $this->db->select('id AS value, position_name AS label')
            ->from('org_position')
            ->where('is_active', 1)
            ->order_by('position_name', 'ASC')
            ->get()->result_array();
    }

    public function get_pending_submitter_position_ids(int $policyId): array
    {
        if ($policyId <= 0 || !$this->db->table_exists('att_pending_submitter_position')) {
            return [];
        }
        $rows = $this->db->select('position_id')
            ->from('att_pending_submitter_position')
            ->where('policy_id', $policyId)
            ->order_by('position_id', 'ASC')
            ->get()->result_array();
        return array_map(static function ($r) { return (int)$r['position_id']; }, $rows);
    }

    public function get_pending_verifier_position_ids(int $policyId, int $level): array
    {
        if ($policyId <= 0 || $level <= 0 || !$this->db->table_exists('att_pending_verifier_position')) {
            return [];
        }
        $rows = $this->db->select('position_id')
            ->from('att_pending_verifier_position')
            ->where('policy_id', $policyId)
            ->where('verify_level', $level)
            ->order_by('position_id', 'ASC')
            ->get()->result_array();
        return array_map(static function ($r) { return (int)$r['position_id']; }, $rows);
    }

    private function save_pending_submitter_positions(int $policyId, array $positionIds): void
    {
        if (!$this->db->table_exists('att_pending_submitter_position')) {
            return;
        }

        $this->db->where('policy_id', $policyId)->delete('att_pending_submitter_position');

        $clean = [];
        foreach ($positionIds as $id) {
            $val = (int)$id;
            if ($val > 0) {
                $clean[$val] = true;
            }
        }

        if (empty($clean)) {
            return;
        }

        $batch = [];
        foreach (array_keys($clean) as $positionId) {
            $batch[] = [
                'policy_id' => $policyId,
                'position_id' => $positionId,
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }
        $this->db->insert_batch('att_pending_submitter_position', $batch);
    }

    private function save_pending_verifier_positions(int $policyId, array $verifierByLevel): void
    {
        if (!$this->db->table_exists('att_pending_verifier_position')) {
            return;
        }

        $this->db->where('policy_id', $policyId)->delete('att_pending_verifier_position');

        $batch = [];
        foreach ($verifierByLevel as $level => $ids) {
            $levelInt = (int)$level;
            if ($levelInt < 1 || $levelInt > 3 || !is_array($ids)) {
                continue;
            }

            $clean = [];
            foreach ($ids as $id) {
                $val = (int)$id;
                if ($val > 0) {
                    $clean[$val] = true;
                }
            }

            foreach (array_keys($clean) as $positionId) {
                $batch[] = [
                    'policy_id' => $policyId,
                    'verify_level' => $levelInt,
                    'position_id' => $positionId,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
            }
        }

        if (!empty($batch)) {
            $this->db->insert_batch('att_pending_verifier_position', $batch);
        }
    }

    public function count_daily(array $f): int
    {
        $this->build_daily_query($f, false);
        return (int)$this->db->count_all_results();
    }

    public function list_daily(array $f, int $limit, int $offset): array
    {
        $this->build_daily_query($f, true);
        return $this->db
            ->order_by('ad.attendance_date', 'DESC')
            ->order_by('e.employee_name', 'ASC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_daily_query(array $f, bool $withSelect): void
    {
        if ($withSelect) {
            $this->db->select('ad.*, e.employee_code, e.employee_name, d.division_name, p.position_name, s.shift_code, s.shift_name');
        }

        $this->db->from('att_daily ad')
            ->join('org_employee e', 'e.id = ad.employee_id', 'inner')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->join('att_shift s', 's.id = ad.shift_id', 'left');

        if (!empty($f['date_start'])) {
            $this->db->where('ad.attendance_date >=', $f['date_start']);
        }
        if (!empty($f['date_end'])) {
            $this->db->where('ad.attendance_date <=', $f['date_end']);
        }
        if (!empty($f['status'])) {
            $this->db->where('ad.attendance_status', $f['status']);
        }
        if (!empty($f['division_id'])) {
            $this->db->where('e.division_id', (int)$f['division_id']);
        }
        if (!empty($f['q'])) {
            $q = trim((string)$f['q']);
            $this->db->group_start()
                ->like('e.employee_code', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('d.division_name', $q)
                ->or_like('p.position_name', $q)
                ->or_like('s.shift_code', $q)
                ->or_like('s.shift_name', $q)
                ->group_end();
        }
    }

    public function count_logs(array $f): int
    {
        $this->build_logs_query($f, false);
        return (int)$this->db->count_all_results();
    }

    public function list_logs(array $f, int $limit, int $offset): array
    {
        $this->build_logs_query($f, true);
        return $this->db
            ->order_by('ap.attendance_at', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_logs_query(array $f, bool $withSelect): void
    {
        if ($withSelect) {
            $this->db->select('ap.*, e.employee_code, e.employee_name, d.division_name, s.shift_code, s.shift_name, l.location_name');
        }

        $this->db->from('att_presence ap')
            ->join('org_employee e', 'e.id = ap.employee_id', 'inner')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('att_shift s', 's.id = ap.shift_id', 'left')
            ->join('att_location l', 'l.id = ap.location_id', 'left');

        if (!empty($f['date_start'])) {
            $this->db->where('ap.attendance_date >=', $f['date_start']);
        }
        if (!empty($f['date_end'])) {
            $this->db->where('ap.attendance_date <=', $f['date_end']);
        }
        if (!empty($f['division_id'])) {
            $this->db->where('e.division_id', (int)$f['division_id']);
        }
        if (!empty($f['event_type'])) {
            $this->db->where('ap.event_type', $f['event_type']);
        }
        if (!empty($f['source_type'])) {
            $this->db->where('ap.source_type', $f['source_type']);
        }
        if (!empty($f['q'])) {
            $q = trim((string)$f['q']);
            $this->db->group_start()
                ->like('e.employee_code', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('s.shift_code', $q)
                ->or_like('l.location_name', $q)
                ->group_end();
        }
    }

    public function count_schedules(array $f): int
    {
        $this->build_schedules_query($f, false);
        return (int)$this->db->count_all_results();
    }

    public function list_schedules(array $f, int $limit, int $offset): array
    {
        $this->build_schedules_query($f, true);
        return $this->db
            ->order_by('ss.schedule_date', 'DESC')
            ->order_by('e.employee_name', 'ASC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_schedules_query(array $f, bool $withSelect): void
    {
        if ($withSelect) {
            $this->db->select('ss.*, e.employee_code, e.employee_name, d.division_name, s.shift_code, s.shift_name');
        }

        $this->db->from('att_shift_schedule ss')
            ->join('org_employee e', 'e.id = ss.employee_id', 'inner')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('att_shift s', 's.id = ss.shift_id', 'left');

        if (!empty($f['date_start'])) {
            $this->db->where('ss.schedule_date >=', $f['date_start']);
        }
        if (!empty($f['date_end'])) {
            $this->db->where('ss.schedule_date <=', $f['date_end']);
        }
        if (!empty($f['division_id'])) {
            $this->db->where('e.division_id', (int)$f['division_id']);
        }
        if (!empty($f['shift_code'])) {
            $this->db->where('s.shift_code', $f['shift_code']);
        }
        if (!empty($f['q'])) {
            $q = trim((string)$f['q']);
            $this->db->group_start()
                ->like('e.employee_code', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('s.shift_code', $q)
                ->or_like('s.shift_name', $q)
                ->group_end();
        }
    }

    public function get_shift_options(): array
    {
        return $this->db->select('id AS value, CONCAT(shift_code, \' - \', shift_name) AS label', false)
            ->from('att_shift')
            ->where('is_active', 1)
            ->order_by('shift_code', 'ASC')
            ->get()->result_array();
    }

    public function get_employee_options(?int $divisionId = null): array
    {
        $this->db->select('e.id AS value, CONCAT(e.employee_code, \' - \', e.employee_name) AS label', false)
            ->from('org_employee e')
            ->where('e.is_active', 1);
        if (!empty($divisionId)) {
            $this->db->where('e.division_id', (int)$divisionId);
        }
        return $this->db->order_by('e.employee_name', 'ASC')->get()->result_array();
    }

    private function normalize_meal_calendar_dates(string $dateStart, string $dateEnd): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStart)) {
            $dateStart = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEnd)) {
            $dateEnd = date('Y-m-t', strtotime($dateStart));
        }
        if ($dateEnd < $dateStart) {
            $dateEnd = $dateStart;
        }

        $startTs = strtotime($dateStart);
        $endTs = strtotime($dateEnd);
        if ($startTs <= 0 || $endTs <= 0) {
            $dateStart = date('Y-m-01');
            $dateEnd = date('Y-m-t');
            $startTs = strtotime($dateStart);
            $endTs = strtotime($dateEnd);
        }

        $maxDays = 62;
        if ((int)floor(($endTs - $startTs) / 86400) + 1 > $maxDays) {
            $endTs = strtotime('+' . ($maxDays - 1) . ' day', $startTs);
            $dateEnd = date('Y-m-d', $endTs);
        }

        return [$dateStart, $dateEnd];
    }

    private function apply_meal_calendar_filter_conditions(array $filters): void
    {
        $this->db->where('e.is_active', 1);

        if (!empty($filters['division_id'])) {
            $this->db->where('e.division_id', (int)$filters['division_id']);
        }

        if (!empty($filters['q'])) {
            $q = trim((string)$filters['q']);
            $this->db->group_start()
                ->like('e.employee_code', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('d.division_name', $q)
                ->group_end();
        }
    }

    public function count_meal_calendar_employees(array $filters): int
    {
        [$dateStart, $dateEnd] = $this->normalize_meal_calendar_dates(
            (string)($filters['date_start'] ?? ''),
            (string)($filters['date_end'] ?? '')
        );

        $this->db->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('att_daily ad', 'ad.employee_id = e.id AND ad.attendance_date >= ' . $this->db->escape($dateStart) . ' AND ad.attendance_date <= ' . $this->db->escape($dateEnd), 'inner', false);
        $this->apply_meal_calendar_filter_conditions($filters);
        $row = $this->db->select('COUNT(DISTINCT e.id) AS c', false)->get()->row_array();
        return (int)($row['c'] ?? 0);
    }

    public function list_meal_calendar_employees(array $filters, int $limit, int $offset): array
    {
        [$dateStart, $dateEnd] = $this->normalize_meal_calendar_dates(
            (string)($filters['date_start'] ?? ''),
            (string)($filters['date_end'] ?? '')
        );

        $rows = $this->db->select("
                e.id AS employee_id,
                e.employee_code,
                e.employee_name,
                d.division_name,
                COALESCE(e.meal_rate, 0) AS meal_rate,
                COUNT(ad.id) AS day_rows,
                SUM(CASE WHEN COALESCE(ad.meal_amount,0) > 0 THEN 1 ELSE 0 END) AS meal_days,
                SUM(COALESCE(ad.meal_amount,0)) AS meal_total,
                SUM(
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM pay_meal_disbursement_line mdl
                            JOIN pay_meal_disbursement md ON md.id = mdl.disbursement_id
                            WHERE mdl.employee_id = ad.employee_id
                              AND mdl.attendance_date = ad.attendance_date
                              AND mdl.transfer_status = 'PAID'
                              AND md.status = 'PAID'
                        ) THEN COALESCE(ad.meal_amount,0)
                        ELSE 0
                    END
                ) AS paid_total
            ", false)
            ->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('att_daily ad', 'ad.employee_id = e.id AND ad.attendance_date >= ' . $this->db->escape($dateStart) . ' AND ad.attendance_date <= ' . $this->db->escape($dateEnd), 'inner', false)
            ->group_by('e.id')
            ->order_by('e.employee_name', 'ASC')
            ->limit($limit, $offset);

        $this->apply_meal_calendar_filter_conditions($filters);
        return $rows->get()->result_array();
    }

    public function meal_calendar_summary(array $filters): array
    {
        [$dateStart, $dateEnd] = $this->normalize_meal_calendar_dates(
            (string)($filters['date_start'] ?? ''),
            (string)($filters['date_end'] ?? '')
        );

        $row = $this->db->select("
                COUNT(DISTINCT e.id) AS employee_count,
                SUM(CASE WHEN COALESCE(ad.meal_amount,0) > 0 THEN 1 ELSE 0 END) AS meal_days,
                SUM(COALESCE(ad.meal_amount,0)) AS meal_total,
                SUM(
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM pay_meal_disbursement_line mdl
                            JOIN pay_meal_disbursement md ON md.id = mdl.disbursement_id
                            WHERE mdl.employee_id = ad.employee_id
                              AND mdl.attendance_date = ad.attendance_date
                              AND mdl.transfer_status = 'PAID'
                              AND md.status = 'PAID'
                        ) THEN COALESCE(ad.meal_amount,0)
                        ELSE 0
                    END
                ) AS paid_total
            ", false)
            ->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('att_daily ad', 'ad.employee_id = e.id AND ad.attendance_date >= ' . $this->db->escape($dateStart) . ' AND ad.attendance_date <= ' . $this->db->escape($dateEnd), 'inner', false);
        $this->apply_meal_calendar_filter_conditions($filters);
        $row = $row->get()->row_array() ?: [];

        $mealTotal = round((float)($row['meal_total'] ?? 0), 2);
        $paidTotal = round((float)($row['paid_total'] ?? 0), 2);
        return [
            'employee_count' => (int)($row['employee_count'] ?? 0),
            'meal_days' => (int)($row['meal_days'] ?? 0),
            'meal_total' => $mealTotal,
            'paid_total' => $paidTotal,
            'unpaid_total' => round(max(0, $mealTotal - $paidTotal), 2),
        ];
    }

    public function meal_calendar_daily_map(array $employeeIds, string $dateStart, string $dateEnd): array
    {
        if (empty($employeeIds)) {
            return [];
        }
        [$dateStart, $dateEnd] = $this->normalize_meal_calendar_dates($dateStart, $dateEnd);
        $cleanIds = array_values(array_filter(array_map('intval', $employeeIds), static function ($v) {
            return $v > 0;
        }));
        if (empty($cleanIds)) {
            return [];
        }

        $rows = $this->db->select("
                ad.employee_id,
                ad.attendance_date,
                ad.attendance_status,
                ad.checkin_at,
                ad.checkout_at,
                ad.meal_amount,
                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM pay_meal_disbursement_line mdl
                        JOIN pay_meal_disbursement md ON md.id = mdl.disbursement_id
                        WHERE mdl.employee_id = ad.employee_id
                          AND mdl.attendance_date = ad.attendance_date
                          AND mdl.transfer_status = 'PAID'
                          AND md.status = 'PAID'
                    ) THEN 1 ELSE 0
                END AS is_paid
            ", false)
            ->from('att_daily ad')
            ->where_in('ad.employee_id', $cleanIds)
            ->where('ad.attendance_date >=', $dateStart)
            ->where('ad.attendance_date <=', $dateEnd)
            ->get()->result_array();

        $map = [];
        foreach ($rows as $row) {
            $eid = (int)($row['employee_id'] ?? 0);
            $date = (string)($row['attendance_date'] ?? '');
            if ($eid <= 0 || $date === '') {
                continue;
            }
            if (!isset($map[$eid])) {
                $map[$eid] = [];
            }
            $map[$eid][$date] = $row;
        }
        return $map;
    }

    public function count_ph_assignments(array $filters): int
    {
        $this->build_ph_assignment_query($filters, false);
        return (int)$this->db->count_all_results();
    }

    public function list_ph_assignments(array $filters, int $limit, int $offset): array
    {
        $this->build_ph_assignment_query($filters, true);
        return $this->db
            ->order_by('d.division_name', 'ASC')
            ->order_by('e.employee_name', 'ASC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_ph_assignment_query(array $filters, bool $withSelect): void
    {
        if ($withSelect) {
            $this->db->select("
                pe.id AS assignment_id,
                e.id AS employee_id,
                e.employee_code,
                e.employee_name,
                d.division_name,
                p.position_name,
                COALESCE(pe.is_eligible, 0) AS is_eligible,
                pe.effective_date,
                pe.expiry_months_override,
                pe.notes,
                COALESCE((
                    SELECT SUM(CASE WHEN l.tx_type IN ('GRANT', 'ADJUST') THEN l.qty_days ELSE 0 END)
                    FROM att_employee_ph_ledger l
                    WHERE l.employee_id = e.id
                ), 0) AS grant_adjust_days,
                COALESCE((
                    SELECT SUM(CASE WHEN l.tx_type IN ('USE', 'EXPIRE') THEN l.qty_days ELSE 0 END)
                    FROM att_employee_ph_ledger l
                    WHERE l.employee_id = e.id
                ), 0) AS use_expire_days
            ", false);
        }

        $this->db->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->join('att_ph_eligibility pe', 'pe.employee_id = e.id', 'left')
            ->where('e.is_active', 1);

        if (!empty($filters['division_id'])) {
            $this->db->where('e.division_id', (int)$filters['division_id']);
        }

        if (isset($filters['is_eligible']) && $filters['is_eligible'] !== '') {
            if ((string)$filters['is_eligible'] === '1') {
                $this->db->where('COALESCE(pe.is_eligible, 0) = 1', null, false);
            } elseif ((string)$filters['is_eligible'] === '0') {
                $this->db->where('COALESCE(pe.is_eligible, 0) = 0', null, false);
            }
        }

        if (!empty($filters['q'])) {
            $q = trim((string)$filters['q']);
            $this->db->group_start()
                ->like('e.employee_code', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('d.division_name', $q)
                ->or_like('p.position_name', $q)
                ->group_end();
        }
    }

    public function upsert_ph_assignment(array $payload, int $actorUserId): array
    {
        if (!$this->db->table_exists('att_ph_eligibility')) {
            return ['ok' => false, 'message' => 'Tabel assignment PH belum tersedia. Jalankan migration terbaru.'];
        }

        $employeeId = (int)($payload['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            return ['ok' => false, 'message' => 'Pegawai wajib dipilih.'];
        }

        $employee = $this->db->select('id')
            ->from('org_employee')
            ->where('id', $employeeId)
            ->where('is_active', 1)
            ->limit(1)
            ->get()->row_array();
        if (!$employee) {
            return ['ok' => false, 'message' => 'Pegawai tidak valid atau nonaktif.'];
        }

        $effectiveDate = trim((string)($payload['effective_date'] ?? ''));
        if ($effectiveDate === '') {
            $effectiveDate = date('Y-m-d');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $effectiveDate)) {
            return ['ok' => false, 'message' => 'Format tanggal efektif wajib YYYY-MM-DD.'];
        }

        $expiryOverride = $payload['expiry_months_override'] ?? null;
        if ($expiryOverride === '' || $expiryOverride === null) {
            $expiryOverride = null;
        } else {
            $expiryOverride = max(0, (int)$expiryOverride);
        }

        $dbPayload = [
            'employee_id' => $employeeId,
            'is_eligible' => !empty($payload['is_eligible']) ? 1 : 0,
            'effective_date' => $effectiveDate,
            'expiry_months_override' => $expiryOverride,
            'notes' => trim((string)($payload['notes'] ?? '')) ?: null,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $sql = $this->db->insert_string('att_ph_eligibility', $dbPayload)
            . ' ON DUPLICATE KEY UPDATE'
            . ' is_eligible=VALUES(is_eligible),'
            . ' effective_date=VALUES(effective_date),'
            . ' expiry_months_override=VALUES(expiry_months_override),'
            . ' notes=VALUES(notes),'
            . ' created_by=VALUES(created_by),'
            . ' updated_at=VALUES(updated_at)';
        $this->db->query($sql);

        return ['ok' => true, 'message' => 'Assignment PH pegawai berhasil disimpan.'];
    }

    public function delete_ph_assignment(int $assignmentId): array
    {
        if (!$this->db->table_exists('att_ph_eligibility')) {
            return ['ok' => false, 'message' => 'Tabel assignment PH belum tersedia.'];
        }
        if ($assignmentId <= 0) {
            return ['ok' => false, 'message' => 'ID assignment tidak valid.'];
        }
        $row = $this->db->select('id')
            ->from('att_ph_eligibility')
            ->where('id', $assignmentId)
            ->limit(1)
            ->get()->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Assignment PH tidak ditemukan.'];
        }
        $this->db->where('id', $assignmentId)->delete('att_ph_eligibility');
        return ['ok' => true, 'message' => 'Assignment PH berhasil dihapus.'];
    }

    public function count_ph_ledger(array $filters): int
    {
        $this->build_ph_ledger_query($filters, false);
        return (int)$this->db->count_all_results();
    }

    public function list_ph_ledger(array $filters, int $limit, int $offset): array
    {
        $this->build_ph_ledger_query($filters, true);
        return $this->db
            ->order_by('l.tx_date', 'DESC')
            ->order_by('l.id', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_ph_ledger_query(array $filters, bool $withSelect): void
    {
        if ($withSelect) {
            $this->db->select("
                l.*,
                e.employee_code,
                e.employee_name,
                d.division_name,
                p.position_name,
                u.username AS created_by_username
            ", false);
        }

        $this->db->from('att_employee_ph_ledger l')
            ->join('org_employee e', 'e.id = l.employee_id', 'inner')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->join('auth_user u', 'u.id = l.created_by', 'left');

        if (!empty($filters['employee_id'])) {
            $this->db->where('l.employee_id', (int)$filters['employee_id']);
        }
        if (!empty($filters['tx_type'])) {
            $this->db->where('l.tx_type', strtoupper((string)$filters['tx_type']));
        }
        if (!empty($filters['date_start'])) {
            $this->db->where('l.tx_date >=', (string)$filters['date_start']);
        }
        if (!empty($filters['date_end'])) {
            $this->db->where('l.tx_date <=', (string)$filters['date_end']);
        }
        if (!empty($filters['q'])) {
            $q = trim((string)$filters['q']);
            $this->db->group_start()
                ->like('e.employee_code', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('d.division_name', $q)
                ->or_like('p.position_name', $q)
                ->or_like('l.notes', $q)
                ->group_end();
        }
    }

    public function save_ph_ledger_entry(array $payload, int $actorUserId): array
    {
        if (!$this->db->table_exists('att_employee_ph_ledger')) {
            return ['ok' => false, 'message' => 'Tabel ledger PH belum tersedia. Jalankan migration terbaru.'];
        }

        $employeeId = (int)($payload['employee_id'] ?? 0);
        $txType = strtoupper(trim((string)($payload['tx_type'] ?? 'ADJUST')));
        $qtyDays = round((float)($payload['qty_days'] ?? 0), 2);
        $txDate = trim((string)($payload['tx_date'] ?? ''));
        $notes = trim((string)($payload['notes'] ?? ''));

        if ($employeeId <= 0 || $qtyDays <= 0 || $txDate === '') {
            return ['ok' => false, 'message' => 'Pegawai, qty hari, dan tanggal transaksi wajib diisi.'];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $txDate)) {
            return ['ok' => false, 'message' => 'Format tanggal transaksi wajib YYYY-MM-DD.'];
        }
        if (!in_array($txType, ['GRANT', 'USE', 'EXPIRE', 'ADJUST'], true)) {
            $txType = 'ADJUST';
        }

        $employee = $this->db->select('id')
            ->from('org_employee')
            ->where('id', $employeeId)
            ->where('is_active', 1)
            ->limit(1)
            ->get()->row_array();
        if (!$employee) {
            return ['ok' => false, 'message' => 'Pegawai tidak valid atau nonaktif.'];
        }

        $this->db->insert('att_employee_ph_ledger', [
            'employee_id' => $employeeId,
            'tx_date' => $txDate,
            'tx_type' => $txType,
            'qty_days' => $qtyDays,
            'entry_mode' => 'MANUAL',
            'notes' => $notes !== '' ? $notes : null,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true, 'message' => 'Mutasi PH manual berhasil ditambahkan.'];
    }

    public function get_ph_ledger_by_id(int $id): ?array
    {
        if ($id <= 0 || !$this->db->table_exists('att_employee_ph_ledger')) {
            return null;
        }
        return $this->db->from('att_employee_ph_ledger')
            ->where('id', $id)
            ->limit(1)
            ->get()->row_array() ?: null;
    }

    public function update_ph_ledger_entry(int $id, array $payload, int $actorUserId, bool $isSuperadmin = false): array
    {
        $row = $this->get_ph_ledger_by_id($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Mutasi PH tidak ditemukan.'];
        }
        if (strtoupper((string)($row['entry_mode'] ?? 'AUTO')) !== 'MANUAL' && !$isSuperadmin) {
            return ['ok' => false, 'message' => 'Mutasi otomatis tidak bisa diedit.'];
        }

        $employeeId = (int)($payload['employee_id'] ?? 0);
        $txType = strtoupper(trim((string)($payload['tx_type'] ?? 'ADJUST')));
        $qtyDays = round((float)($payload['qty_days'] ?? 0), 2);
        $txDate = trim((string)($payload['tx_date'] ?? ''));
        $notes = trim((string)($payload['notes'] ?? ''));

        if ($employeeId <= 0 || $qtyDays <= 0 || $txDate === '') {
            return ['ok' => false, 'message' => 'Pegawai, qty hari, dan tanggal transaksi wajib diisi.'];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $txDate)) {
            return ['ok' => false, 'message' => 'Format tanggal transaksi wajib YYYY-MM-DD.'];
        }
        if (!in_array($txType, ['GRANT', 'USE', 'EXPIRE', 'ADJUST'], true)) {
            $txType = 'ADJUST';
        }

        $employee = $this->db->select('id')
            ->from('org_employee')
            ->where('id', $employeeId)
            ->where('is_active', 1)
            ->limit(1)
            ->get()->row_array();
        if (!$employee) {
            return ['ok' => false, 'message' => 'Pegawai tidak valid atau nonaktif.'];
        }

        $updatePayload = [
            'employee_id' => $employeeId,
            'tx_date' => $txDate,
            'tx_type' => $txType,
            'qty_days' => $qtyDays,
            'notes' => $notes !== '' ? $notes : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($isSuperadmin) {
            $updatePayload['created_by'] = $actorUserId > 0 ? $actorUserId : null;
        }

        $this->db->where('id', $id)->update('att_employee_ph_ledger', $updatePayload);
        return ['ok' => true, 'message' => 'Mutasi PH berhasil diperbarui.'];
    }

    public function delete_ph_ledger_entry(int $id, bool $isSuperadmin = false): array
    {
        $row = $this->get_ph_ledger_by_id($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Mutasi PH tidak ditemukan.'];
        }
        if (strtoupper((string)($row['entry_mode'] ?? 'AUTO')) !== 'MANUAL' && !$isSuperadmin) {
            return ['ok' => false, 'message' => 'Mutasi otomatis tidak bisa dihapus.'];
        }
        $this->db->where('id', $id)->delete('att_employee_ph_ledger');
        return ['ok' => true, 'message' => 'Mutasi PH berhasil dihapus.'];
    }

    public function ph_ledger_summary(array $filters): array
    {
        if (!$this->db->table_exists('att_employee_ph_ledger')) {
            return [
                'grant' => 0.0,
                'use' => 0.0,
                'expire' => 0.0,
                'adjust' => 0.0,
                'balance' => 0.0,
            ];
        }

        $this->db->select("
            COALESCE(SUM(CASE WHEN l.tx_type = 'GRANT' THEN l.qty_days ELSE 0 END),0) AS grant_days,
            COALESCE(SUM(CASE WHEN l.tx_type = 'USE' THEN l.qty_days ELSE 0 END),0) AS use_days,
            COALESCE(SUM(CASE WHEN l.tx_type = 'EXPIRE' THEN l.qty_days ELSE 0 END),0) AS expire_days,
            COALESCE(SUM(CASE WHEN l.tx_type = 'ADJUST' THEN l.qty_days ELSE 0 END),0) AS adjust_days
        ", false)->from('att_employee_ph_ledger l');

        if (!empty($filters['employee_id'])) {
            $this->db->where('l.employee_id', (int)$filters['employee_id']);
        }
        if (!empty($filters['date_start'])) {
            $this->db->where('l.tx_date >=', (string)$filters['date_start']);
        }
        if (!empty($filters['date_end'])) {
            $this->db->where('l.tx_date <=', (string)$filters['date_end']);
        }
        $row = $this->db->get()->row_array() ?: [];

        $grant = (float)($row['grant_days'] ?? 0);
        $use = (float)($row['use_days'] ?? 0);
        $expire = (float)($row['expire_days'] ?? 0);
        $adjust = (float)($row['adjust_days'] ?? 0);
        return [
            'grant' => round($grant, 2),
            'use' => round($use, 2),
            'expire' => round($expire, 2),
            'adjust' => round($adjust, 2),
            'balance' => round(($grant + $adjust) - ($use + $expire), 2),
        ];
    }

    public function count_ph_recap(array $filters): int
    {
        $this->build_ph_recap_query($filters, false);
        return (int)$this->db->count_all_results();
    }

    public function list_ph_recap(array $filters, int $limit, int $offset): array
    {
        $this->build_ph_recap_query($filters, true);
        return $this->db
            ->order_by('d.division_name', 'ASC')
            ->order_by('e.employee_name', 'ASC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_ph_recap_query(array $filters, bool $withSelect): void
    {
        $month = trim((string)($filters['month'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $monthStart = $month . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        if ($withSelect) {
            $this->db->select("
                e.id AS employee_id,
                e.employee_code,
                e.employee_name,
                d.division_name,
                p.position_name,
                COALESCE(pe.is_eligible, 0) AS is_eligible,
                COALESCE((SELECT SUM(CASE WHEN l.tx_type='GRANT' THEN l.qty_days ELSE 0 END) FROM att_employee_ph_ledger l WHERE l.employee_id=e.id),0) AS grant_total,
                COALESCE((SELECT SUM(CASE WHEN l.tx_type='USE' THEN l.qty_days ELSE 0 END) FROM att_employee_ph_ledger l WHERE l.employee_id=e.id),0) AS use_total,
                COALESCE((SELECT SUM(CASE WHEN l.tx_type='EXPIRE' THEN l.qty_days ELSE 0 END) FROM att_employee_ph_ledger l WHERE l.employee_id=e.id),0) AS expire_total,
                COALESCE((SELECT SUM(CASE WHEN l.tx_type='ADJUST' THEN l.qty_days ELSE 0 END) FROM att_employee_ph_ledger l WHERE l.employee_id=e.id),0) AS adjust_total,
                COALESCE((SELECT SUM(CASE WHEN l.tx_type='GRANT' THEN l.qty_days ELSE 0 END) FROM att_employee_ph_ledger l WHERE l.employee_id=e.id AND l.tx_date >= " . $this->db->escape($monthStart) . " AND l.tx_date <= " . $this->db->escape($monthEnd) . "),0) AS grant_month,
                COALESCE((SELECT SUM(CASE WHEN l.tx_type='USE' THEN l.qty_days ELSE 0 END) FROM att_employee_ph_ledger l WHERE l.employee_id=e.id AND l.tx_date >= " . $this->db->escape($monthStart) . " AND l.tx_date <= " . $this->db->escape($monthEnd) . "),0) AS use_month
            ", false);
        }

        $this->db->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->join('att_ph_eligibility pe', 'pe.employee_id = e.id', 'left')
            ->where('e.is_active', 1);

        if (!empty($filters['division_id'])) {
            $this->db->where('e.division_id', (int)$filters['division_id']);
        }
        if (isset($filters['is_eligible']) && $filters['is_eligible'] !== '') {
            if ((string)$filters['is_eligible'] === '1') {
                $this->db->where('COALESCE(pe.is_eligible, 0) = 1', null, false);
            } elseif ((string)$filters['is_eligible'] === '0') {
                $this->db->where('COALESCE(pe.is_eligible, 0) = 0', null, false);
            }
        }
        if (!empty($filters['q'])) {
            $q = trim((string)$filters['q']);
            $this->db->group_start()
                ->like('e.employee_code', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('d.division_name', $q)
                ->or_like('p.position_name', $q)
                ->group_end();
        }
    }

    public function sync_ph_grants_from_attendance(string $dateStart, string $dateEnd, int $actorUserId): array
    {
        if (!$this->db->table_exists('att_ph_eligibility') || !$this->db->table_exists('att_employee_ph_ledger')) {
            return ['ok' => false, 'message' => 'Tabel PH belum lengkap. Jalankan migration terbaru.'];
        }
        if ($dateStart === '' || $dateEnd === '') {
            return ['ok' => false, 'message' => 'Tanggal awal dan akhir wajib diisi.'];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStart) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEnd)) {
            return ['ok' => false, 'message' => 'Format tanggal wajib YYYY-MM-DD.'];
        }
        if ($dateEnd < $dateStart) {
            return ['ok' => false, 'message' => 'Rentang tanggal tidak valid.'];
        }

        $policy = $this->get_active_policy();
        $grantMode = strtoupper((string)($policy['ph_grant_mode'] ?? 'HOLIDAY_ONLY'));
        if (!in_array($grantMode, ['SHIFT_ONLY', 'HOLIDAY_ONLY', 'SHIFT_OR_HOLIDAY'], true)) {
            $grantMode = 'HOLIDAY_ONLY';
        }
        $grantHolidayType = strtoupper((string)($policy['ph_grant_holiday_type'] ?? 'ANY'));
        if (!in_array($grantHolidayType, ['ANY', 'NATIONAL', 'COMPANY', 'SPECIAL'], true)) {
            $grantHolidayType = 'ANY';
        }
        $requireCheckout = (int)($policy['ph_grant_requires_checkout'] ?? 1) === 1;
        $grantQty = round((float)($policy['ph_grant_qty_per_day'] ?? 1), 2);
        if ($grantQty <= 0) {
            $grantQty = 1;
        }
        $defaultExpiryMonths = (int)($policy['ph_expiry_months'] ?? 0);
        if ($defaultExpiryMonths < 0) {
            $defaultExpiryMonths = 0;
        }

        $rows = $this->db->query("
            SELECT
                ad.id AS daily_id,
                ad.employee_id,
                ad.attendance_date,
                ad.checkin_at,
                ad.checkout_at,
                ad.attendance_status,
                s.shift_code,
                hc.holiday_type,
                pe.effective_date,
                pe.expiry_months_override
            FROM att_daily ad
            JOIN att_ph_eligibility pe ON pe.employee_id = ad.employee_id AND pe.is_eligible = 1
            LEFT JOIN att_shift s ON s.id = ad.shift_id
            LEFT JOIN att_holiday_calendar hc ON hc.holiday_date = ad.attendance_date AND hc.is_active = 1
            WHERE ad.attendance_date >= ? AND ad.attendance_date <= ?
              AND ad.attendance_status IN ('PRESENT', 'LATE', 'HOLIDAY')
        ", [$dateStart, $dateEnd])->result_array();

        $inserted = 0;
        $skipped = 0;
        $now = date('Y-m-d H:i:s');
        foreach ($rows as $row) {
            $dailyId = (int)($row['daily_id'] ?? 0);
            $employeeId = (int)($row['employee_id'] ?? 0);
            $attendanceDate = (string)($row['attendance_date'] ?? '');
            if ($dailyId <= 0 || $employeeId <= 0 || $attendanceDate === '') {
                $skipped++;
                continue;
            }

            $effectiveDate = (string)($row['effective_date'] ?? '');
            if ($effectiveDate !== '' && $attendanceDate < $effectiveDate) {
                $skipped++;
                continue;
            }

            if ($requireCheckout) {
                $checkinAt = trim((string)($row['checkin_at'] ?? ''));
                $checkoutAt = trim((string)($row['checkout_at'] ?? ''));
                if ($checkinAt === '' || $checkoutAt === '') {
                    $skipped++;
                    continue;
                }
            }

            $shiftCode = strtoupper(trim((string)($row['shift_code'] ?? '')));
            $holidayType = strtoupper(trim((string)($row['holiday_type'] ?? '')));
            $isShiftPh = ($shiftCode === 'PH');
            $isHoliday = ($holidayType !== '');
            if ($grantHolidayType !== 'ANY' && $holidayType !== $grantHolidayType) {
                $isHoliday = false;
            }

            $qualified = false;
            if ($grantMode === 'SHIFT_ONLY') {
                $qualified = $isShiftPh;
            } elseif ($grantMode === 'HOLIDAY_ONLY') {
                $qualified = $isHoliday;
            } else {
                $qualified = $isShiftPh || $isHoliday;
            }
            if (!$qualified) {
                $skipped++;
                continue;
            }

            $exists = $this->db->select('id')
                ->from('att_employee_ph_ledger')
                ->where('employee_id', $employeeId)
                ->where('tx_type', 'GRANT')
                ->where('ref_table', 'att_daily')
                ->where('ref_id', $dailyId)
                ->limit(1)
                ->get()->row_array();
            if ($exists) {
                $skipped++;
                continue;
            }

            $expiryMonths = $row['expiry_months_override'] !== null
                ? max(0, (int)$row['expiry_months_override'])
                : $defaultExpiryMonths;
            $expiredAt = null;
            if ($expiryMonths > 0) {
                $expiredAt = date('Y-m-d', strtotime($attendanceDate . ' +' . $expiryMonths . ' month'));
            }

            $this->db->insert('att_employee_ph_ledger', [
                'employee_id' => $employeeId,
                'tx_date' => $attendanceDate,
                'tx_type' => 'GRANT',
                'qty_days' => $grantQty,
                'expired_at' => $expiredAt,
                'ref_table' => 'att_daily',
                'ref_id' => $dailyId,
                'entry_mode' => 'AUTO',
                'notes' => 'Auto grant dari absensi PH/holiday',
                'created_by' => $actorUserId > 0 ? $actorUserId : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $inserted++;
        }

        return [
            'ok' => true,
            'message' => 'Sinkron grant PH selesai.',
            'inserted' => $inserted,
            'skipped' => $skipped,
            'total_scanned' => count($rows),
        ];
    }

    public function sync_ph_grant_for_employee_date(int $employeeId, string $date, int $actorUserId = 0): array
    {
        if ($employeeId <= 0 || $date === '') {
            return ['ok' => false, 'message' => 'Employee/date tidak valid.'];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return ['ok' => false, 'message' => 'Format tanggal tidak valid.'];
        }
        if (!$this->db->table_exists('att_ph_eligibility') || !$this->db->table_exists('att_employee_ph_ledger')) {
            return ['ok' => false, 'message' => 'Tabel PH belum lengkap.'];
        }

        $policy = $this->get_active_policy();
        $grantMode = strtoupper((string)($policy['ph_grant_mode'] ?? 'HOLIDAY_ONLY'));
        if (!in_array($grantMode, ['SHIFT_ONLY', 'HOLIDAY_ONLY', 'SHIFT_OR_HOLIDAY'], true)) {
            $grantMode = 'HOLIDAY_ONLY';
        }
        $grantHolidayType = strtoupper((string)($policy['ph_grant_holiday_type'] ?? 'ANY'));
        if (!in_array($grantHolidayType, ['ANY', 'NATIONAL', 'COMPANY', 'SPECIAL'], true)) {
            $grantHolidayType = 'ANY';
        }
        $requireCheckout = (int)($policy['ph_grant_requires_checkout'] ?? 1) === 1;
        $grantQty = round((float)($policy['ph_grant_qty_per_day'] ?? 1), 2);
        if ($grantQty <= 0) {
            $grantQty = 1;
        }
        $defaultExpiryMonths = max(0, (int)($policy['ph_expiry_months'] ?? 0));

        $row = $this->db->query("
            SELECT
                ad.id AS daily_id,
                ad.employee_id,
                ad.attendance_date,
                ad.checkin_at,
                ad.checkout_at,
                ad.attendance_status,
                s.shift_code,
                hc.holiday_type,
                pe.effective_date,
                pe.expiry_months_override
            FROM att_daily ad
            JOIN att_ph_eligibility pe ON pe.employee_id = ad.employee_id AND pe.is_eligible = 1
            LEFT JOIN att_shift s ON s.id = ad.shift_id
            LEFT JOIN att_holiday_calendar hc ON hc.holiday_date = ad.attendance_date AND hc.is_active = 1
            WHERE ad.employee_id = ?
              AND ad.attendance_date = ?
              AND ad.attendance_status IN ('PRESENT', 'LATE', 'HOLIDAY')
            LIMIT 1
        ", [$employeeId, $date])->row_array();

        if (!$row) {
            return ['ok' => true, 'inserted' => 0, 'skipped' => 1, 'message' => 'Tidak memenuhi syarat grant PH.'];
        }

        $effectiveDate = (string)($row['effective_date'] ?? '');
        if ($effectiveDate !== '' && $date < $effectiveDate) {
            return ['ok' => true, 'inserted' => 0, 'skipped' => 1, 'message' => 'Tanggal absen sebelum effective date PH.'];
        }

        if ($requireCheckout) {
            $checkinAt = trim((string)($row['checkin_at'] ?? ''));
            $checkoutAt = trim((string)($row['checkout_at'] ?? ''));
            if ($checkinAt === '' || $checkoutAt === '') {
                return ['ok' => true, 'inserted' => 0, 'skipped' => 1, 'message' => 'Grant PH butuh check-in/out lengkap.'];
            }
        }

        $shiftCode = strtoupper(trim((string)($row['shift_code'] ?? '')));
        $holidayType = strtoupper(trim((string)($row['holiday_type'] ?? '')));
        $isShiftPh = ($shiftCode === 'PH');
        $isHoliday = ($holidayType !== '');
        if ($grantHolidayType !== 'ANY' && $holidayType !== $grantHolidayType) {
            $isHoliday = false;
        }

        $qualified = false;
        if ($grantMode === 'SHIFT_ONLY') {
            $qualified = $isShiftPh;
        } elseif ($grantMode === 'HOLIDAY_ONLY') {
            $qualified = $isHoliday;
        } else {
            $qualified = $isShiftPh || $isHoliday;
        }
        if (!$qualified) {
            return ['ok' => true, 'inserted' => 0, 'skipped' => 1, 'message' => 'Tidak memenuhi mode grant PH.'];
        }

        $dailyId = (int)($row['daily_id'] ?? 0);
        if ($dailyId <= 0) {
            return ['ok' => true, 'inserted' => 0, 'skipped' => 1, 'message' => 'Rekap harian tidak valid.'];
        }
        $exists = $this->db->select('id')
            ->from('att_employee_ph_ledger')
            ->where('employee_id', $employeeId)
            ->where('tx_type', 'GRANT')
            ->where('ref_table', 'att_daily')
            ->where('ref_id', $dailyId)
            ->limit(1)
            ->get()->row_array();
        if ($exists) {
            return ['ok' => true, 'inserted' => 0, 'skipped' => 1, 'message' => 'Grant PH sudah pernah dibuat.'];
        }

        $expiryMonths = $row['expiry_months_override'] !== null
            ? max(0, (int)$row['expiry_months_override'])
            : $defaultExpiryMonths;
        $expiredAt = null;
        if ($expiryMonths > 0) {
            $expiredAt = date('Y-m-d', strtotime($date . ' +' . $expiryMonths . ' month'));
        }

        $this->db->insert('att_employee_ph_ledger', [
            'employee_id' => $employeeId,
            'tx_date' => $date,
            'tx_type' => 'GRANT',
            'qty_days' => $grantQty,
            'expired_at' => $expiredAt,
            'ref_table' => 'att_daily',
            'ref_id' => $dailyId,
            'entry_mode' => 'AUTO',
            'notes' => 'Auto grant dari absensi PH/holiday',
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true, 'inserted' => 1, 'skipped' => 0, 'message' => 'Grant PH dibuat.'];
    }

    public function count_overtime_entries(array $f): int
    {
        $this->build_overtime_query($f, false);
        return (int)$this->db->count_all_results();
    }

    public function list_overtime_entries(array $f, int $limit, int $offset): array
    {
        $this->build_overtime_query($f, true);
        return $this->db
            ->order_by('oe.overtime_date', 'DESC')
            ->order_by('e.employee_name', 'ASC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_overtime_query(array $f, bool $withSelect): void
    {
        $hasStandardSchema = $this->has_overtime_standard_schema();
        if ($withSelect) {
            $select = 'oe.*, e.employee_code, e.employee_name, d.division_name, p.position_name, au.username AS approved_by_username';
            if ($hasStandardSchema) {
                $select .= ', os.standard_name AS overtime_standard_name, os.hourly_rate AS overtime_standard_rate';
            }
            $this->db->select($select);
        }
        $this->db->from('att_overtime_entry oe')
            ->join('org_employee e', 'e.id = oe.employee_id', 'inner')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->join('auth_user au', 'au.id = oe.approved_by', 'left');
        if ($hasStandardSchema) {
            $this->db->join('att_overtime_standard os', 'os.id = oe.overtime_standard_id', 'left');
        }

        if (!empty($f['date_start'])) {
            $this->db->where('oe.overtime_date >=', (string)$f['date_start']);
        }
        if (!empty($f['date_end'])) {
            $this->db->where('oe.overtime_date <=', (string)$f['date_end']);
        }
        if (!empty($f['division_id'])) {
            $this->db->where('e.division_id', (int)$f['division_id']);
        }
        if (!empty($f['employee_id'])) {
            $this->db->where('oe.employee_id', (int)$f['employee_id']);
        }
        if (!empty($f['status'])) {
            $this->db->where('oe.status', strtoupper((string)$f['status']));
        }
        if (!empty($f['q'])) {
            $q = trim((string)$f['q']);
            $this->db->group_start()
                ->like('e.employee_code', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('d.division_name', $q)
                ->or_like('oe.notes', $q)
                ->group_end();
        }
    }

    public function get_overtime_entry_by_id(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $hasStandardSchema = $this->has_overtime_standard_schema();
        if ($hasStandardSchema) {
            $this->db->select('oe.*, os.standard_name AS overtime_standard_name, os.hourly_rate AS overtime_standard_rate')
                ->from('att_overtime_entry oe')
                ->join('att_overtime_standard os', 'os.id = oe.overtime_standard_id', 'left')
                ->where('oe.id', $id)
                ->limit(1);
            return $this->db->get()->row_array() ?: null;
        }
        return $this->db->from('att_overtime_entry')->where('id', $id)->limit(1)->get()->row_array() ?: null;
    }

    public function get_overtime_standard_options(): array
    {
        if (!$this->has_overtime_standard_schema()) {
            return [];
        }
        return $this->db->select('id AS value, standard_code, standard_name, hourly_rate')
            ->from('att_overtime_standard')
            ->where('is_active', 1)
            ->order_by('hourly_rate', 'ASC')
            ->order_by('standard_name', 'ASC')
            ->get()->result_array();
    }

    public function save_overtime_entry(array $payload, int $actorUserId = 0): array
    {
        $id = (int)($payload['id'] ?? 0);
        $employeeId = (int)($payload['employee_id'] ?? 0);
        $overtimeStandardId = (int)($payload['overtime_standard_id'] ?? 0);
        $overtimeDate = trim((string)($payload['overtime_date'] ?? ''));
        $startTime = trim((string)($payload['start_time'] ?? ''));
        $endTime = trim((string)($payload['end_time'] ?? ''));
        $status = strtoupper(trim((string)($payload['status'] ?? 'PENDING')));
        $notes = trim((string)($payload['notes'] ?? ''));
        $inputRate = (float)($payload['overtime_rate'] ?? 0);

        if ($employeeId <= 0 || $overtimeDate === '' || $startTime === '' || $endTime === '') {
            return ['ok' => false, 'message' => 'Pegawai, tanggal, jam mulai, dan jam selesai wajib diisi.'];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $overtimeDate)) {
            return ['ok' => false, 'message' => 'Tanggal lembur tidak valid.'];
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
            return ['ok' => false, 'message' => 'Format jam lembur harus HH:MM.'];
        }
        if (!in_array($status, ['PENDING', 'APPROVED', 'REJECTED'], true)) {
            $status = 'PENDING';
        }

        $employee = $this->db->select('id, overtime_rate')->from('org_employee')->where('id', $employeeId)->where('is_active', 1)->limit(1)->get()->row_array();
        if (!$employee) {
            return ['ok' => false, 'message' => 'Pegawai tidak ditemukan atau nonaktif.'];
        }

        $startAt = strtotime($overtimeDate . ' ' . $startTime . ':00');
        $endAt = strtotime($overtimeDate . ' ' . $endTime . ':00');
        if (!$startAt || !$endAt) {
            return ['ok' => false, 'message' => 'Jam lembur tidak valid.'];
        }
        if ($endAt <= $startAt) {
            $endAt = strtotime('+1 day', $endAt);
        }
        if ($endAt <= $startAt) {
            return ['ok' => false, 'message' => 'Jam selesai harus setelah jam mulai.'];
        }

        $hours = round(($endAt - $startAt) / 3600, 2);
        if ($hours <= 0) {
            return ['ok' => false, 'message' => 'Durasi lembur harus lebih dari 0 jam.'];
        }

        $hasStandardSchema = $this->has_overtime_standard_schema();
        $standard = null;
        if ($hasStandardSchema && $overtimeStandardId > 0) {
            $standard = $this->db->select('id, standard_name, hourly_rate, is_active')
                ->from('att_overtime_standard')
                ->where('id', $overtimeStandardId)
                ->where('is_active', 1)
                ->limit(1)
                ->get()->row_array();
            if (!$standard) {
                return ['ok' => false, 'message' => 'Standar lembur tidak valid atau nonaktif.'];
            }
        }

        $rate = (float)($employee['overtime_rate'] ?? 0);
        if ($standard) {
            $rate = (float)($standard['hourly_rate'] ?? 0);
        } elseif ($inputRate > 0) {
            $rate = $inputRate;
        }
        $totalPay = round($hours * $rate, 2);

        $dbPayload = [
            'employee_id' => $employeeId,
            'overtime_date' => $overtimeDate,
            'start_at' => date('Y-m-d H:i:s', $startAt),
            'end_at' => date('Y-m-d H:i:s', $endAt),
            'overtime_hours' => $hours,
            'overtime_rate' => round($rate, 2),
            'total_overtime_pay' => $totalPay,
            'status' => $status,
            'notes' => $notes !== '' ? $notes : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($hasStandardSchema) {
            $dbPayload['overtime_standard_id'] = $standard ? (int)$standard['id'] : null;
        }

        if ($status === 'APPROVED') {
            $dbPayload['approved_by'] = $actorUserId > 0 ? $actorUserId : null;
            $dbPayload['approved_at'] = date('Y-m-d H:i:s');
        } else {
            $dbPayload['approved_by'] = null;
            $dbPayload['approved_at'] = null;
        }

        if ($id > 0) {
            $exists = $this->get_overtime_entry_by_id($id);
            if (!$exists) {
                return ['ok' => false, 'message' => 'Data lembur tidak ditemukan.'];
            }
            $oldEmployeeId = (int)($exists['employee_id'] ?? 0);
            $oldDate = (string)($exists['overtime_date'] ?? '');
            $this->db->where('id', $id)->update('att_overtime_entry', $dbPayload);
            $this->recompute_overtime_daily_payroll($oldEmployeeId, $oldDate);
            if ($oldEmployeeId !== $employeeId || $oldDate !== $overtimeDate) {
                $this->recompute_overtime_daily_payroll($employeeId, $overtimeDate);
            }
            return ['ok' => true, 'message' => 'Data lembur berhasil diperbarui.'];
        }

        $dbPayload['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('att_overtime_entry', $dbPayload);
        $this->recompute_overtime_daily_payroll($employeeId, $overtimeDate);
        return ['ok' => true, 'message' => 'Data lembur berhasil ditambahkan.'];
    }

    public function delete_overtime_entry(int $id): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'ID lembur tidak valid.'];
        }
        $exists = $this->get_overtime_entry_by_id($id);
        if (!$exists) {
            return ['ok' => false, 'message' => 'Data lembur tidak ditemukan.'];
        }
        $this->db->where('id', $id)->delete('att_overtime_entry');
        $this->recompute_overtime_daily_payroll((int)$exists['employee_id'], (string)$exists['overtime_date']);
        return ['ok' => true, 'message' => 'Data lembur berhasil dihapus.'];
    }

    private function has_overtime_standard_schema(): bool
    {
        static $checked = null;
        if ($checked !== null) {
            return $checked;
        }
        $checked = $this->db->table_exists('att_overtime_standard')
            && $this->db->field_exists('overtime_standard_id', 'att_overtime_entry');
        return $checked;
    }

    private function recompute_overtime_daily_payroll(int $employeeId, string $date): void
    {
        if ($employeeId <= 0 || $date === '') {
            return;
        }
        if (!$this->db->table_exists('att_daily')) {
            return;
        }
        $dailyRow = $this->db->from('att_daily')
            ->where('employee_id', $employeeId)
            ->where('attendance_date', $date)
            ->limit(1)
            ->get()->row_array();
        if (!$dailyRow) {
            return;
        }

        $policy = $this->get_active_policy();
        $manualOvertimePay = $this->get_manual_overtime_pay($employeeId, $date);
        $payload = $this->build_daily_payroll_payload($dailyRow, $policy, $employeeId, $manualOvertimePay);
        if (!empty($payload)) {
            $this->db->where('id', (int)$dailyRow['id'])->update('att_daily', $payload);
        }
    }

    public function save_schedule(int $employeeId, int $shiftId, string $date, string $notes = '', int $createdBy = 0): array
    {
        if ($employeeId <= 0 || $shiftId <= 0 || $date === '') {
            return ['ok' => false, 'message' => 'Data jadwal tidak lengkap.'];
        }

        $exists = $this->db->select('id')
            ->from('att_shift_schedule')
            ->where('employee_id', $employeeId)
            ->where('schedule_date', $date)
            ->limit(1)
            ->get()->row_array();

        $payload = [
            'shift_id' => $shiftId,
            'notes' => ($notes !== '') ? $notes : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($exists) {
            $this->db->where('id', (int)$exists['id'])->update('att_shift_schedule', $payload);
            return ['ok' => true, 'message' => 'Jadwal diperbarui.'];
        }

        $this->db->insert('att_shift_schedule', [
            'employee_id' => $employeeId,
            'shift_id' => $shiftId,
            'schedule_date' => $date,
            'notes' => ($notes !== '') ? $notes : null,
            'created_by' => $createdBy > 0 ? $createdBy : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return ['ok' => true, 'message' => 'Jadwal ditambahkan.'];
    }

    public function update_schedule(int $id, int $shiftId, string $date, string $notes = ''): array
    {
        $row = $this->db->from('att_shift_schedule')->where('id', $id)->limit(1)->get()->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Jadwal tidak ditemukan.'];
        }
        if ($shiftId <= 0 || $date === '') {
            return ['ok' => false, 'message' => 'Shift dan tanggal wajib diisi.'];
        }

        $dup = $this->db->select('id')
            ->from('att_shift_schedule')
            ->where('employee_id', (int)$row['employee_id'])
            ->where('schedule_date', $date)
            ->where('id !=', $id)
            ->limit(1)
            ->get()->row_array();
        if ($dup) {
            return ['ok' => false, 'message' => 'Pegawai sudah punya jadwal lain di tanggal tersebut.'];
        }

        $this->db->where('id', $id)->update('att_shift_schedule', [
            'shift_id' => $shiftId,
            'schedule_date' => $date,
            'notes' => ($notes !== '') ? $notes : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return ['ok' => true, 'message' => 'Jadwal berhasil diperbarui.'];
    }

    public function delete_schedule(int $id): array
    {
        $row = $this->db->from('att_shift_schedule')->where('id', $id)->limit(1)->get()->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Jadwal tidak ditemukan.'];
        }
        $this->db->where('id', $id)->delete('att_shift_schedule');
        return ['ok' => true, 'message' => 'Jadwal berhasil dihapus.'];
    }

    public function bulk_save_schedule(array $employeeIds, int $shiftId, string $startDate, string $endDate, string $notes = '', int $createdBy = 0): array
    {
        if ($shiftId <= 0 || $startDate === '' || $endDate === '' || empty($employeeIds)) {
            return ['ok' => false, 'message' => 'Data bulk jadwal tidak lengkap.'];
        }

        $startTs = strtotime($startDate);
        $endTs = strtotime($endDate);
        if (!$startTs || !$endTs || $endTs < $startTs) {
            return ['ok' => false, 'message' => 'Rentang tanggal tidak valid.'];
        }

        $cleanEmployeeIds = [];
        foreach ($employeeIds as $employeeId) {
            $val = (int)$employeeId;
            if ($val > 0) {
                $cleanEmployeeIds[$val] = true;
            }
        }
        if (empty($cleanEmployeeIds)) {
            return ['ok' => false, 'message' => 'Tidak ada pegawai valid dipilih.'];
        }

        $affected = 0;
        $this->db->trans_start();
        for ($ts = $startTs; $ts <= $endTs; $ts = strtotime('+1 day', $ts)) {
            $date = date('Y-m-d', $ts);
            foreach (array_keys($cleanEmployeeIds) as $employeeId) {
                $exists = $this->db->select('id')
                    ->from('att_shift_schedule')
                    ->where('employee_id', $employeeId)
                    ->where('schedule_date', $date)
                    ->limit(1)
                    ->get()->row_array();
                if ($exists) {
                    $this->db->where('id', (int)$exists['id'])->update('att_shift_schedule', [
                        'shift_id' => $shiftId,
                        'notes' => ($notes !== '') ? $notes : null,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                } else {
                    $this->db->insert('att_shift_schedule', [
                        'employee_id' => $employeeId,
                        'shift_id' => $shiftId,
                        'schedule_date' => $date,
                        'notes' => ($notes !== '') ? $notes : null,
                        'created_by' => $createdBy > 0 ? $createdBy : null,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
                $affected++;
            }
        }
        $this->db->trans_complete();
        if (!$this->db->trans_status()) {
            return ['ok' => false, 'message' => 'Gagal menyimpan bulk jadwal.'];
        }

        return ['ok' => true, 'message' => 'Bulk jadwal berhasil diproses: ' . $affected . ' baris.'];
    }

    public function get_shift_code_map(): array
    {
        $rows = $this->db->select('id, shift_code, shift_name')
            ->from('att_shift')
            ->where('is_active', 1)
            ->order_by('shift_code', 'ASC')
            ->get()->result_array();

        $map = [];
        foreach ($rows as $row) {
            $code = strtoupper(trim((string)($row['shift_code'] ?? '')));
            if ($code === '') {
                continue;
            }
            $map[$code] = $row;
        }
        return $map;
    }

    public function schedule_matrix(int $year, int $month): array
    {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));

        $employees = $this->db->select('id, employee_code, employee_name, division_id, position_id')
            ->from('org_employee')
            ->where('is_active', 1)
            ->order_by('division_id', 'ASC')
            ->order_by('position_id', 'ASC')
            ->order_by('employee_name', 'ASC')
            ->get()->result();

        $rows = $this->db->select('ss.employee_id, ss.schedule_date, s.shift_code')
            ->from('att_shift_schedule ss')
            ->join('att_shift s', 's.id = ss.shift_id', 'left')
            ->where('ss.schedule_date >=', $start)
            ->where('ss.schedule_date <=', $end)
            ->get()->result_array();

        $map = [];
        foreach ($rows as $row) {
            $employeeId = (int)($row['employee_id'] ?? 0);
            $scheduleDate = (string)($row['schedule_date'] ?? '');
            if ($employeeId <= 0 || $scheduleDate === '') {
                continue;
            }
            $map[$employeeId][$scheduleDate] = (string)($row['shift_code'] ?? '');
        }

        return [
            'employees' => $employees,
            'schedule_map' => $map,
            'start' => $start,
            'end' => $end,
        ];
    }

    public function get_holiday_dates_between(string $startDate, string $endDate): array
    {
        if ($startDate === '' || $endDate === '' || !$this->db->table_exists('att_holiday_calendar')) {
            return [];
        }

        $rows = $this->db->select('holiday_date')
            ->from('att_holiday_calendar')
            ->where('holiday_date >=', $startDate)
            ->where('holiday_date <=', $endDate)
            ->where('is_active', 1)
            ->get()->result_array();

        return array_values(array_filter(array_map(static function ($r) {
            return (string)($r['holiday_date'] ?? '');
        }, $rows)));
    }

    public function upsert_schedule_by_shift_code(int $employeeId, string $date, string $shiftCode, int $actorEmployeeId = 0): array
    {
        $shiftCode = strtoupper(trim($shiftCode));
        $date = trim($date);
        if ($employeeId <= 0 || $date === '') {
            return ['ok' => false, 'message' => 'Data tidak lengkap.'];
        }

        $employee = $this->db->select('id')
            ->from('org_employee')
            ->where('id', $employeeId)
            ->where('is_active', 1)
            ->limit(1)
            ->get()->row_array();
        if (!$employee) {
            return ['ok' => false, 'message' => 'Pegawai tidak valid.'];
        }

        if ($shiftCode === '') {
            $this->db->where('employee_id', $employeeId)
                ->where('schedule_date', $date)
                ->delete('att_shift_schedule');
            return ['ok' => true, 'message' => 'Jadwal dihapus.'];
        }

        $shift = $this->db->select('id')
            ->from('att_shift')
            ->where('shift_code', $shiftCode)
            ->where('is_active', 1)
            ->limit(1)
            ->get()->row_array();
        if (!$shift) {
            return ['ok' => false, 'message' => 'Kode shift tidak valid: ' . $shiftCode];
        }

        $now = date('Y-m-d H:i:s');
        $payload = [
            'employee_id' => $employeeId,
            'schedule_date' => $date,
            'shift_id' => (int)$shift['id'],
            'created_by' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
            'updated_at' => $now,
        ];
        $sql = $this->db->insert_string('att_shift_schedule', $payload);
        $sql .= ' ON DUPLICATE KEY UPDATE shift_id=VALUES(shift_id), updated_at=VALUES(updated_at), notes=NULL';
        $this->db->query($sql);

        return ['ok' => true, 'message' => 'Jadwal tersimpan.'];
    }

    public function get_active_locations(): array
    {
        $hasDefault = $this->db->field_exists('is_default', 'att_location');
        $select = $hasDefault
            ? 'id AS value, location_name AS label, is_default'
            : 'id AS value, location_name AS label, 0 AS is_default';
        $this->db->select($select, false)
            ->from('att_location')
            ->where('is_active', 1);
        if ($hasDefault) {
            $this->db->order_by('is_default', 'DESC');
        }
        return $this->db->order_by('location_name', 'ASC')->get()->result_array();
    }

    public function get_default_location_id(): int
    {
        if (!$this->db->field_exists('is_default', 'att_location')) {
            $row = $this->db->select('id')->from('att_location')->where('is_active', 1)->order_by('id', 'ASC')->limit(1)->get()->row_array();
            return (int)($row['id'] ?? 0);
        }
        $row = $this->db->select('id')->from('att_location')->where('is_active', 1)->where('is_default', 1)->order_by('id', 'ASC')->limit(1)->get()->row_array();
        if ($row) {
            return (int)$row['id'];
        }
        $fallback = $this->db->select('id')->from('att_location')->where('is_active', 1)->order_by('id', 'ASC')->limit(1)->get()->row_array();
        return (int)($fallback['id'] ?? 0);
    }

    public function count_pending_requests(array $f): int
    {
        $this->build_pending_requests_query($f, false);
        return (int)$this->db->count_all_results();
    }

    public function list_pending_requests(array $f, int $limit, int $offset): array
    {
        $this->build_pending_requests_query($f, true);
        return $this->db
            ->order_by('pr.request_date', 'DESC')
            ->order_by('pr.id', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    public function pending_request_approval_history_map(array $requestIds): array
    {
        $cleanIds = [];
        foreach ($requestIds as $requestId) {
            $id = (int)$requestId;
            if ($id > 0) {
                $cleanIds[$id] = true;
            }
        }
        if (empty($cleanIds) || !$this->db->table_exists('att_pending_request_approval')) {
            return [];
        }

        $rows = $this->db->select('pa.pending_request_id, pa.approval_level, pa.action, pa.notes, pa.acted_at, pa.created_at, pa.approver_employee_id, e.employee_name AS approver_name, e.employee_code AS approver_code')
            ->from('att_pending_request_approval pa')
            ->join('org_employee e', 'e.id = pa.approver_employee_id', 'left')
            ->where_in('pa.pending_request_id', array_keys($cleanIds))
            ->order_by('pa.pending_request_id', 'ASC')
            ->order_by('pa.approval_level', 'ASC')
            ->get()->result_array();

        $map = [];
        foreach ($rows as $row) {
            $requestId = (int)($row['pending_request_id'] ?? 0);
            if ($requestId <= 0) {
                continue;
            }
            if (!isset($map[$requestId])) {
                $map[$requestId] = [];
            }
            $map[$requestId][] = $row;
        }

        return $map;
    }

    private function build_pending_requests_query(array $f, bool $withSelect): void
    {
        if ($withSelect) {
            $select = 'pr.*, e.employee_code, e.employee_name, d.division_name, p.position_name';
            if ($this->db->table_exists('att_pending_request_approval')) {
                $select .= ",
                    (SELECT COUNT(*) FROM att_pending_request_approval pa WHERE pa.pending_request_id = pr.id AND pa.action = 'APPROVED') AS approved_levels,
                    (SELECT MAX(pa.approval_level) FROM att_pending_request_approval pa WHERE pa.pending_request_id = pr.id AND pa.action = 'APPROVED') AS last_approved_level,
                    (SELECT GROUP_CONCAT(CONCAT('L', pa.approval_level, ':', COALESCE(a.employee_name, '-'), ' ', pa.action) ORDER BY pa.approval_level SEPARATOR ' | ')
                       FROM att_pending_request_approval pa
                       LEFT JOIN org_employee a ON a.id = pa.approver_employee_id
                      WHERE pa.pending_request_id = pr.id) AS approval_timeline";
            }
            $this->db->select($select, false);
        }

        $this->db->from('att_pending_request pr')
            ->join('org_employee e', 'e.id = pr.employee_id', 'inner')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left');

        if (!empty($f['date_start'])) {
            $this->db->where('pr.request_date >=', $f['date_start']);
        }
        if (!empty($f['date_end'])) {
            $this->db->where('pr.request_date <=', $f['date_end']);
        }
        if (!empty($f['division_id'])) {
            $this->db->where('e.division_id', (int)$f['division_id']);
        }
        if (!empty($f['status'])) {
            $this->db->where('pr.status', $f['status']);
        }
        if (!empty($f['request_type'])) {
            $this->db->where('pr.request_type', $f['request_type']);
        }
        if (!empty($f['q'])) {
            $q = trim((string)$f['q']);
            $this->db->group_start()
                ->like('e.employee_code', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('d.division_name', $q)
                ->or_like('p.position_name', $q)
                ->or_like('pr.reason', $q)
                ->group_end();
        }
    }

    public function count_anomalies(array $f): int
    {
        $this->build_anomalies_query($f, false);
        return (int)$this->db->count_all_results();
    }

    public function list_anomalies(array $f, int $limit, int $offset): array
    {
        $this->build_anomalies_query($f, true);
        return $this->db
            ->order_by('ad.attendance_date', 'DESC')
            ->order_by('e.employee_name', 'ASC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_anomalies_query(array $f, bool $withSelect): void
    {
        $issueCase = "CASE
            WHEN ad.checkin_at IS NULL AND ad.checkout_at IS NOT NULL THEN 'MISSING_CHECKIN'
            WHEN ad.checkin_at IS NOT NULL AND ad.checkout_at IS NULL THEN 'MISSING_CHECKOUT'
            WHEN ad.checkin_at IS NOT NULL AND ad.checkout_at IS NOT NULL AND ad.checkout_at < ad.checkin_at THEN 'CHECKOUT_BEFORE_CHECKIN'
            WHEN ad.checkin_at IS NOT NULL AND ad.checkout_at IS NOT NULL AND COALESCE(ad.work_minutes,0) = 0 THEN 'ZERO_WORK_WITH_CHECKIO'
            WHEN COALESCE(ad.late_minutes,0) > 0 AND ad.attendance_status <> 'LATE' THEN 'STATUS_MISMATCH_LATE'
            ELSE NULL
        END";

        if ($withSelect) {
            $this->db->select(
                "ad.*, e.employee_code, e.employee_name, d.division_name, p.position_name, s.shift_code, s.shift_name, {$issueCase} AS issue_type",
                false
            );
        }

        $this->db->from('att_daily ad')
            ->join('org_employee e', 'e.id = ad.employee_id', 'inner')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->join('att_shift s', 's.id = ad.shift_id', 'left')
            ->where("({$issueCase}) IS NOT NULL", null, false);

        if (!empty($f['date_start'])) {
            $this->db->where('ad.attendance_date >=', $f['date_start']);
        }
        if (!empty($f['date_end'])) {
            $this->db->where('ad.attendance_date <=', $f['date_end']);
        }
        if (!empty($f['division_id'])) {
            $this->db->where('e.division_id', (int)$f['division_id']);
        }
        if (!empty($f['issue_type'])) {
            $this->db->where("({$issueCase}) = " . $this->db->escape($f['issue_type']), null, false);
        }
        if (!empty($f['q'])) {
            $q = trim((string)$f['q']);
            $this->db->group_start()
                ->like('e.employee_code', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('d.division_name', $q)
                ->or_like('p.position_name', $q)
                ->or_like('s.shift_code', $q)
                ->group_end();
        }
    }

    public function master_health_summary(): array
    {
        $summary = [
            'active_employee' => (int)$this->db->from('org_employee')->where('is_active', 1)->count_all_results(),
            'employee_without_user' => 0,
            'user_without_employee' => 0,
            'employee_without_division' => 0,
            'employee_without_position' => 0,
            'employee_without_month_schedule' => 0,
            'employee_without_active_contract' => 0,
        ];

        $summary['employee_without_user'] = (int)$this->db
            ->from('org_employee e')
            ->join('auth_user u', 'u.employee_id = e.id', 'left')
            ->where('e.is_active', 1)
            ->where('u.id IS NULL', null, false)
            ->count_all_results();

        $summary['user_without_employee'] = (int)$this->db
            ->from('auth_user u')
            ->join('org_employee e', 'e.id = u.employee_id', 'left')
            ->where('u.is_active', 1)
            ->where('(u.employee_id IS NULL OR e.id IS NULL)', null, false)
            ->count_all_results();

        $summary['employee_without_division'] = (int)$this->db
            ->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->where('e.is_active', 1)
            ->where('(e.division_id IS NULL OR d.id IS NULL)', null, false)
            ->count_all_results();

        $summary['employee_without_position'] = (int)$this->db
            ->from('org_employee e')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->where('e.is_active', 1)
            ->where('(e.position_id IS NULL OR p.id IS NULL)', null, false)
            ->count_all_results();

        $summary['employee_without_month_schedule'] = (int)$this->db
            ->from('org_employee e')
            ->join('att_shift_schedule ss', "ss.employee_id = e.id AND ss.schedule_date >= '" . date('Y-m-01') . "' AND ss.schedule_date <= '" . date('Y-m-t') . "'", 'left')
            ->where('e.is_active', 1)
            ->where('ss.id IS NULL', null, false)
            ->count_all_results();

        if ($this->db->table_exists('hr_contract')) {
            $summary['employee_without_active_contract'] = (int)$this->db
                ->from('org_employee e')
                ->join('hr_contract c', "c.employee_id = e.id AND c.status = 'ACTIVE' AND c.start_date <= CURDATE() AND c.end_date >= CURDATE()", 'left')
                ->where('e.is_active', 1)
                ->where('c.id IS NULL', null, false)
                ->count_all_results();
        }

        return $summary;
    }

    public function count_master_health_issues(array $filters): int
    {
        return count($this->master_health_issue_rows($filters));
    }

    public function list_master_health_issues(array $filters, int $limit, int $offset): array
    {
        $rows = $this->master_health_issue_rows($filters);
        return array_slice($rows, max(0, $offset), max(1, $limit));
    }

    private function master_health_issue_rows(array $filters): array
    {
        $rows = [];
        $q = strtolower(trim((string)($filters['q'] ?? '')));
        $issueFilter = strtoupper(trim((string)($filters['issue_type'] ?? '')));

        $addRows = static function (array $sourceRows, string $issueType, string $issueLabel) use (&$rows, $q, $issueFilter): void {
            if ($issueFilter !== '' && $issueFilter !== $issueType) {
                return;
            }

            foreach ($sourceRows as $r) {
                $searchBlob = strtolower(
                    (string)($r['employee_code'] ?? '') . ' ' .
                    (string)($r['employee_name'] ?? '') . ' ' .
                    (string)($r['username'] ?? '') . ' ' .
                    (string)($r['email'] ?? '')
                );
                if ($q !== '' && strpos($searchBlob, $q) === false) {
                    continue;
                }

                $rows[] = [
                    'issue_type' => $issueType,
                    'issue_label' => $issueLabel,
                    'employee_id' => (int)($r['employee_id'] ?? 0),
                    'employee_code' => (string)($r['employee_code'] ?? '-'),
                    'employee_name' => (string)($r['employee_name'] ?? '-'),
                    'username' => (string)($r['username'] ?? '-'),
                    'email' => (string)($r['email'] ?? '-'),
                    'division_name' => (string)($r['division_name'] ?? '-'),
                    'position_name' => (string)($r['position_name'] ?? '-'),
                    'notes' => (string)($r['notes'] ?? ''),
                ];
            }
        };

        $employeeWithoutUser = $this->db
            ->select('e.id AS employee_id, e.employee_code, e.employee_name, d.division_name, p.position_name')
            ->from('org_employee e')
            ->join('auth_user u', 'u.employee_id = e.id', 'left')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->where('e.is_active', 1)
            ->where('u.id IS NULL', null, false)
            ->get()->result_array();
        $addRows($employeeWithoutUser, 'EMPLOYEE_WITHOUT_USER', 'Pegawai aktif belum terhubung user login');

        $userWithoutEmployee = $this->db
            ->select('COALESCE(e.id, 0) AS employee_id, COALESCE(e.employee_code, \'-\') AS employee_code, COALESCE(e.employee_name, \'-\') AS employee_name, u.username, u.email')
            ->from('auth_user u')
            ->join('org_employee e', 'e.id = u.employee_id', 'left')
            ->where('u.is_active', 1)
            ->where('(u.employee_id IS NULL OR e.id IS NULL)', null, false)
            ->get()->result_array();
        $addRows($userWithoutEmployee, 'USER_WITHOUT_EMPLOYEE', 'User aktif tidak punya relasi pegawai');

        $employeeWithoutDivision = $this->db
            ->select('e.id AS employee_id, e.employee_code, e.employee_name, p.position_name')
            ->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->where('e.is_active', 1)
            ->where('(e.division_id IS NULL OR d.id IS NULL)', null, false)
            ->get()->result_array();
        $addRows($employeeWithoutDivision, 'EMPLOYEE_WITHOUT_DIVISION', 'Pegawai aktif belum punya divisi valid');

        $employeeWithoutPosition = $this->db
            ->select('e.id AS employee_id, e.employee_code, e.employee_name, d.division_name')
            ->from('org_employee e')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->where('e.is_active', 1)
            ->where('(e.position_id IS NULL OR p.id IS NULL)', null, false)
            ->get()->result_array();
        $addRows($employeeWithoutPosition, 'EMPLOYEE_WITHOUT_POSITION', 'Pegawai aktif belum punya jabatan valid');

        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $employeeWithoutSchedule = $this->db
            ->select('e.id AS employee_id, e.employee_code, e.employee_name, d.division_name, p.position_name')
            ->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->join('att_shift_schedule ss', "ss.employee_id = e.id AND ss.schedule_date >= '{$monthStart}' AND ss.schedule_date <= '{$monthEnd}'", 'left')
            ->where('e.is_active', 1)
            ->where('ss.id IS NULL', null, false)
            ->get()->result_array();
        $addRows($employeeWithoutSchedule, 'EMPLOYEE_WITHOUT_MONTH_SCHEDULE', 'Pegawai aktif belum punya jadwal shift bulan berjalan');

        if ($this->db->table_exists('hr_contract')) {
            $employeeWithoutContract = $this->db
                ->select('e.id AS employee_id, e.employee_code, e.employee_name, d.division_name, p.position_name')
                ->from('org_employee e')
                ->join('org_division d', 'd.id = e.division_id', 'left')
                ->join('org_position p', 'p.id = e.position_id', 'left')
                ->join('hr_contract c', "c.employee_id = e.id AND c.status = 'ACTIVE' AND c.start_date <= CURDATE() AND c.end_date >= CURDATE()", 'left')
                ->where('e.is_active', 1)
                ->where('c.id IS NULL', null, false)
                ->get()->result_array();
            $addRows($employeeWithoutContract, 'EMPLOYEE_WITHOUT_ACTIVE_CONTRACT', 'Pegawai aktif belum punya kontrak ACTIVE');
        }

        usort($rows, static function (array $a, array $b): int {
            $cmp = strcmp((string)$a['issue_type'], (string)$b['issue_type']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp((string)$a['employee_name'], (string)$b['employee_name']);
        });

        return $rows;
    }

    public function process_pending_request_action(int $requestId, int $actorEmployeeId, string $action, string $notes, bool $isSuperadmin = false, bool $forceFinalApprove = false): array
    {
        $action = strtoupper(trim($action));
        if (!in_array($action, ['APPROVE', 'REJECT', 'CANCEL'], true)) {
            return ['ok' => false, 'message' => 'Aksi tidak valid.'];
        }

        $req = $this->db->from('att_pending_request')->where('id', $requestId)->limit(1)->get()->row_array();
        if (!$req) {
            return ['ok' => false, 'message' => 'Pengajuan tidak ditemukan.'];
        }
        if (strtoupper((string)($req['status'] ?? '')) !== 'PENDING') {
            return ['ok' => false, 'message' => 'Pengajuan ini sudah diproses sebelumnya.'];
        }

        $policy = $this->get_active_policy();
        $policyId = (int)($policy['id'] ?? 0);
        $approvalLevels = max(1, min(3, (int)($policy['pending_approval_levels'] ?? 3)));

        $approvedLevels = 0;
        $hasApprovalTable = $this->db->table_exists('att_pending_request_approval');
        if ($hasApprovalTable) {
            $approvedLevels = (int)$this->db
                ->from('att_pending_request_approval')
                ->where('pending_request_id', $requestId)
                ->where('action', 'APPROVED')
                ->count_all_results();
        } else {
            $approvalLevels = 1;
        }

        $currentLevel = min($approvalLevels, max(1, $approvedLevels + 1));

        if ($action === 'CANCEL') {
            if (!$isSuperadmin && (int)$req['employee_id'] !== $actorEmployeeId) {
                return ['ok' => false, 'message' => 'Hanya pengaju atau superadmin yang dapat membatalkan.'];
            }
            $this->db->where('id', $requestId)->update('att_pending_request', [
                'status' => 'CANCELLED',
                'approval_notes' => $notes !== '' ? $notes : 'Dibatalkan pengaju',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return ['ok' => true, 'message' => 'Pengajuan dibatalkan.'];
        }

        if (!$isSuperadmin && !$this->can_verify_level($policyId, $currentLevel, $actorEmployeeId)) {
            return ['ok' => false, 'message' => 'Anda tidak terdaftar sebagai verifier pada level saat ini.'];
        }

        if ($forceFinalApprove && !$isSuperadmin) {
            return ['ok' => false, 'message' => 'Override final approval hanya untuk superadmin.'];
        }

        $this->db->trans_start();
        if ($hasApprovalTable) {
            $this->db->where('pending_request_id', $requestId)
                ->where('approval_level', $currentLevel)
                ->delete('att_pending_request_approval');
            $this->db->insert('att_pending_request_approval', [
                'pending_request_id' => $requestId,
                'approval_level' => $currentLevel,
                'approver_employee_id' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
                'action' => $action === 'APPROVE' ? 'APPROVED' : 'REJECTED',
                'notes' => $notes !== '' ? $notes : null,
                'acted_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if ($action === 'REJECT') {
            $this->db->where('id', $requestId)->update('att_pending_request', [
                'status' => 'REJECTED',
                'approved_by' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
                'approved_at' => date('Y-m-d H:i:s'),
                'approval_notes' => $notes !== '' ? $notes : 'Ditolak pada level ' . $currentLevel,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db->trans_complete();
            return ['ok' => $this->db->trans_status(), 'message' => 'Pengajuan ditolak.'];
        }

        if ($forceFinalApprove || $currentLevel >= $approvalLevels) {
            $apply = $this->apply_pending_request_to_daily($req, $notes);
            if (!$apply['ok']) {
                $this->db->trans_rollback();
                return $apply;
            }

            $this->db->where('id', $requestId)->update('att_pending_request', [
                'status' => 'APPROVED',
                'approved_by' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
                'approved_at' => date('Y-m-d H:i:s'),
                'approval_notes' => $notes !== '' ? $notes : ($forceFinalApprove ? 'Disetujui override final' : 'Disetujui final'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db->trans_complete();
            return ['ok' => $this->db->trans_status(), 'message' => ($forceFinalApprove ? 'Pengajuan override final disetujui' : 'Pengajuan disetujui final') . ' dan rekap harian diperbarui.'];
        }

        $this->db->where('id', $requestId)->update('att_pending_request', [
            'approval_notes' => $notes !== '' ? $notes : ('Disetujui level ' . $currentLevel),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->trans_complete();

        return ['ok' => $this->db->trans_status(), 'message' => 'Pengajuan disetujui level ' . $currentLevel . '. Menunggu verifier level berikutnya.'];
    }

    private function can_verify_level(int $policyId, int $level, int $actorEmployeeId): bool
    {
        if ($actorEmployeeId <= 0 || $policyId <= 0 || $level <= 0) {
            return false;
        }

        if (!$this->db->table_exists('att_pending_verifier_position')) {
            return false;
        }

        $employee = $this->db->select('position_id')
            ->from('org_employee')
            ->where('id', $actorEmployeeId)
            ->limit(1)
            ->get()->row_array();
        $positionId = (int)($employee['position_id'] ?? 0);
        if ($positionId <= 0) {
            return false;
        }

        $exists = $this->db->select('id')
            ->from('att_pending_verifier_position')
            ->where('policy_id', $policyId)
            ->where('verify_level', $level)
            ->where('position_id', $positionId)
            ->limit(1)
            ->get()->row_array();

        return !empty($exists);
    }

    private function apply_pending_request_to_daily(array $req, string $notes = ''): array
    {
        $employeeId = (int)($req['employee_id'] ?? 0);
        $date = (string)($req['request_date'] ?? '');
        $requestType = strtoupper((string)($req['request_type'] ?? ''));
        if ($employeeId <= 0 || $date === '') {
            return ['ok' => false, 'message' => 'Data pengajuan tidak valid untuk diterapkan.'];
        }

        $daily = $this->db->from('att_daily')
            ->where('employee_id', $employeeId)
            ->where('attendance_date', $date)
            ->limit(1)
            ->get()->row_array();

        $schedule = $this->db->select('ss.shift_id, s.start_time, s.end_time, s.is_overnight, s.grace_late_minute')
            ->from('att_shift_schedule ss')
            ->join('att_shift s', 's.id = ss.shift_id', 'left')
            ->where('ss.employee_id', $employeeId)
            ->where('ss.schedule_date', $date)
            ->limit(1)
            ->get()->row_array();

        $shiftId = (int)($daily['shift_id'] ?? 0);
        if ($shiftId <= 0) {
            $shiftId = (int)($schedule['shift_id'] ?? 0);
        }

        $checkinAt = (string)($daily['checkin_at'] ?? '');
        $checkoutAt = (string)($daily['checkout_at'] ?? '');
        $status = (string)($daily['attendance_status'] ?? 'OFF');

        if ($requestType === 'MISSING_CHECKIN' && !empty($req['requested_checkin_at'])) {
            $checkinAt = (string)$req['requested_checkin_at'];
        } elseif ($requestType === 'MISSING_CHECKOUT' && !empty($req['requested_checkout_at'])) {
            $checkoutAt = (string)$req['requested_checkout_at'];
        } elseif ($requestType === 'STATUS_CORRECTION' && !empty($req['requested_status'])) {
            $status = (string)$req['requested_status'];
        } elseif (in_array($requestType, ['LEAVE', 'SICK'], true)) {
            $status = $requestType === 'LEAVE' ? 'LEAVE' : 'SICK';
            $checkinAt = '';
            $checkoutAt = '';
        }

        [$startTs, $endTs] = $this->shift_bounds_from_schedule($date, $schedule);
        $inTs = $checkinAt !== '' ? strtotime($checkinAt) : 0;
        $outTs = $checkoutAt !== '' ? strtotime($checkoutAt) : 0;
        $policy = $this->get_active_policy();

        if ($outTs > 0) {
            $outTsCredited = $this->apply_checkout_credit_to_operation_end($outTs, $date, $policy);
            if ($outTsCredited !== $outTs) {
                $outTs = $outTsCredited;
                $checkoutAt = date('Y-m-d H:i:s', $outTs);
            }
        }

        [$lateMinutes, $workMinutes, $statusDerived] = $this->compute_daily_metrics(
            $date,
            $checkinAt,
            $checkoutAt,
            $schedule
        );
        if (!in_array($requestType, ['LEAVE', 'SICK', 'STATUS_CORRECTION'], true)) {
            $status = $statusDerived;
        }

        $scheduledWorkMinutes = ($startTs > 0 && $endTs > $startTs) ? (int)floor(($endTs - $startTs) / 60) : 0;
        $overtimeMode = strtoupper((string)($policy['overtime_calc_mode'] ?? 'AUTO'));
        if (!in_array($overtimeMode, ['AUTO', 'MANUAL'], true)) {
            $overtimeMode = 'AUTO';
        }

        $overtimeMinutes = 0;
        $manualOvertimePay = 0.0;
        if ($overtimeMode === 'MANUAL') {
            if ($workMinutes > 0 && $scheduledWorkMinutes > 0) {
                $workMinutes = min($workMinutes, $scheduledWorkMinutes);
            }
            $manualOvertimePay = $this->get_manual_overtime_pay($employeeId, $date);
            $overtimeMinutes = 0;
        } elseif ($inTs > 0 && $outTs > $inTs && $endTs > 0 && $outTs > $endTs) {
            $overtimeMinutes = (int)floor(($outTs - $endTs) / 60);
        }

        $payload = [
            'shift_id' => $shiftId > 0 ? $shiftId : null,
            'checkin_at' => $checkinAt !== '' ? $checkinAt : null,
            'checkout_at' => $checkoutAt !== '' ? $checkoutAt : null,
            'attendance_status' => $status,
            'work_minutes' => $workMinutes,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => ($outTs > 0 && $endTs > 0 && $outTs < $endTs) ? max(0, (int)floor(($endTs - $outTs) / 60)) : 0,
            'overtime_minutes' => max(0, $overtimeMinutes),
            'source_type' => 'PENDING_APPROVAL',
            'remarks' => $notes !== '' ? $notes : ('Approved pending request #' . (int)$req['id']),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($daily) {
            $this->db->where('id', (int)$daily['id'])->update('att_daily', $payload);
        } else {
            $this->db->insert('att_daily', [
                'attendance_date' => $date,
                'employee_id' => $employeeId,
                'created_at' => date('Y-m-d H:i:s'),
            ] + $payload);
        }

        $dailyRow = $this->db->from('att_daily')
            ->where('employee_id', $employeeId)
            ->where('attendance_date', $date)
            ->limit(1)
            ->get()->row_array();
        if ($dailyRow) {
            $dailyPayrollPayload = $this->build_daily_payroll_payload($dailyRow, $policy, $employeeId, $manualOvertimePay);
            if (!empty($dailyPayrollPayload)) {
                $this->db->where('id', (int)$dailyRow['id'])->update('att_daily', $dailyPayrollPayload);
            }
            $this->sync_ph_grant_for_employee_date($employeeId, $date, 0);
        }

        return ['ok' => true, 'message' => ''];
    }

    private function compute_daily_metrics(string $date, string $checkinAt, string $checkoutAt, array $schedule): array
    {
        $lateMinutes = 0;
        $workMinutes = 0;
        $status = 'OFF';

        $startTime = (string)($schedule['start_time'] ?? '');
        $endTime = (string)($schedule['end_time'] ?? '');
        $isOvernight = (int)($schedule['is_overnight'] ?? 0);
        $grace = max(0, (int)($schedule['grace_late_minute'] ?? 0));

        $startTs = ($startTime !== '') ? strtotime($date . ' ' . $startTime) : 0;
        $endTs = ($endTime !== '') ? strtotime($date . ' ' . $endTime) : 0;
        if ($startTs > 0 && $endTs > 0 && ($isOvernight === 1 || $endTs <= $startTs)) {
            $endTs = strtotime('+1 day', $endTs);
        }

        $inTs = $checkinAt !== '' ? strtotime($checkinAt) : 0;
        $outTs = $checkoutAt !== '' ? strtotime($checkoutAt) : 0;

        if ($inTs > 0 && $startTs > 0) {
            $lateMinutes = max(0, ((int)floor(($inTs - $startTs) / 60)) - $grace);
        }
        if ($inTs > 0 && $outTs > $inTs) {
            $workMinutes = (int)floor(($outTs - $inTs) / 60);
        }

        if ($inTs > 0 || $outTs > 0) {
            $status = $lateMinutes > 0 ? 'LATE' : 'PRESENT';
        }

        return [$lateMinutes, max(0, $workMinutes), $status];
    }

    private function shift_bounds_from_schedule(string $date, array $schedule): array
    {
        $startTime = (string)($schedule['start_time'] ?? '');
        $endTime = (string)($schedule['end_time'] ?? '');
        $isOvernight = (int)($schedule['is_overnight'] ?? 0);

        $startTs = ($startTime !== '') ? strtotime($date . ' ' . $startTime) : 0;
        $endTs = ($endTime !== '') ? strtotime($date . ' ' . $endTime) : 0;
        if ($startTs > 0 && $endTs > 0 && ($isOvernight === 1 || $endTs <= $startTs)) {
            $endTs = strtotime('+1 day', $endTs);
        }
        return [$startTs ?: 0, $endTs ?: 0];
    }

    private function apply_checkout_credit_to_operation_end(int $checkoutTs, string $date, array $policy): int
    {
        if ($checkoutTs <= 0 || (int)($policy['night_shift_checkout_credit_to_operation_end'] ?? 1) !== 1) {
            return $checkoutTs;
        }

        $creditAfter = trim((string)($policy['night_shift_checkout_credit_after'] ?? ''));
        $opStart = trim((string)($policy['operation_start_time'] ?? ''));
        $opEnd = trim((string)($policy['operation_end_time'] ?? ''));
        if ($creditAfter === '' || $opStart === '' || $opEnd === '') {
            return $checkoutTs;
        }

        $checkoutClock = date('H:i:s', $checkoutTs);
        if ($checkoutClock < $creditAfter) {
            return $checkoutTs;
        }

        $opEndTs = strtotime($date . ' ' . $opEnd);
        if ($opEndTs <= 0) {
            return $checkoutTs;
        }
        if ($opEnd < $opStart) {
            $opEndTs = strtotime('+1 day', $opEndTs);
        }

        if ($opEndTs > 0 && $checkoutTs < $opEndTs) {
            return (int)$opEndTs;
        }
        return $checkoutTs;
    }

    private function get_manual_overtime_pay(int $employeeId, string $date): float
    {
        if ($employeeId <= 0 || $date === '' || !$this->db->table_exists('att_overtime_entry')) {
            return 0.0;
        }
        $row = $this->db->select('COALESCE(SUM(total_overtime_pay),0) AS total', false)
            ->from('att_overtime_entry')
            ->where('employee_id', $employeeId)
            ->where('overtime_date', $date)
            ->where('status', 'APPROVED')
            ->get()->row_array();
        return round((float)($row['total'] ?? 0), 2);
    }

    private function build_daily_payroll_payload(array $dailyRow, array $policy, int $employeeId, float $manualOvertimePay = 0.0): array
    {
        $checkinTs = !empty($dailyRow['checkin_at']) ? strtotime((string)$dailyRow['checkin_at']) : 0;
        $checkoutTs = !empty($dailyRow['checkout_at']) ? strtotime((string)$dailyRow['checkout_at']) : 0;
        $hasCompletedCheckout = ($checkinTs > 0 && $checkoutTs > $checkinTs);
        $attendanceDate = (string)($dailyRow['attendance_date'] ?? '');

        $employee = $this->db->select('basic_salary, position_allowance, objective_allowance, meal_rate, overtime_rate')
            ->from('org_employee')
            ->where('id', $employeeId)
            ->limit(1)
            ->get()->row_array();

        $basicSalary = isset($dailyRow['snapshot_basic_salary']) && $dailyRow['snapshot_basic_salary'] !== null
            ? (float)$dailyRow['snapshot_basic_salary']
            : (float)($employee['basic_salary'] ?? 0);
        $positionAllowance = isset($dailyRow['snapshot_position_allowance']) && $dailyRow['snapshot_position_allowance'] !== null
            ? (float)$dailyRow['snapshot_position_allowance']
            : (float)($employee['position_allowance'] ?? 0);
        $objectiveAllowance = isset($dailyRow['snapshot_objective_allowance']) && $dailyRow['snapshot_objective_allowance'] !== null
            ? (float)$dailyRow['snapshot_objective_allowance']
            : (float)($employee['objective_allowance'] ?? 0);
        $mealRate = isset($dailyRow['snapshot_meal_rate']) && $dailyRow['snapshot_meal_rate'] !== null
            ? (float)$dailyRow['snapshot_meal_rate']
            : (float)($employee['meal_rate'] ?? 0);
        $overtimeRate = isset($dailyRow['snapshot_overtime_rate']) && $dailyRow['snapshot_overtime_rate'] !== null
            ? (float)$dailyRow['snapshot_overtime_rate']
            : (float)($employee['overtime_rate'] ?? 0);

        $workDays = max(1, (int)($policy['default_work_days_per_month'] ?? 26));
        $basicDailyRate = $basicSalary / $workDays;
        $allowanceDailyRate = ($positionAllowance + $objectiveAllowance) / $workDays;
        $mealMode = strtoupper((string)($policy['meal_calc_mode'] ?? 'MONTHLY'));
        $allowanceLateTreatment = strtoupper((string)($policy['allowance_late_treatment'] ?? 'FULL_IF_PRESENT'));
        $enableLateDeduction = (int)($policy['enable_late_deduction'] ?? 1) === 1;
        $enableAlphaDeduction = (int)($policy['enable_alpha_deduction'] ?? 1) === 1;
        $lateDeductionPerMinute = (float)($policy['late_deduction_per_minute'] ?? 0);
        $alphaDeductionPerDay = (float)($policy['alpha_deduction_per_day'] ?? 0);
        $attendanceMode = strtoupper((string)($policy['attendance_calc_mode'] ?? 'DAILY'));
        $prorateScope = strtoupper((string)($policy['prorate_deduction_scope'] ?? ($policy['payroll_late_deduction_scope'] ?? 'BASIC_ONLY')));
        if (!in_array($prorateScope, ['BASIC_ONLY', 'THP_TOTAL'], true)) {
            $prorateScope = 'BASIC_ONLY';
        }
        $hasConfiguredDeduction = ($enableLateDeduction && $lateDeductionPerMinute > 0)
            || ($enableAlphaDeduction && $alphaDeductionPerDay > 0);
        $overtimeMode = strtoupper((string)($policy['overtime_calc_mode'] ?? 'AUTO'));
        if (!in_array($overtimeMode, ['AUTO', 'MANUAL'], true)) {
            $overtimeMode = 'AUTO';
        }

        $status = strtoupper((string)($dailyRow['attendance_status'] ?? 'OFF'));
        $lateMinutes = (int)($dailyRow['late_minutes'] ?? 0);
        $workMinutes = max(0, (int)($dailyRow['work_minutes'] ?? 0));
        $scheduledWorkMinutes = $this->resolve_scheduled_work_minutes(
            $employeeId,
            $attendanceDate,
            (int)($dailyRow['shift_id'] ?? 0)
        );
        $isPresentish = in_array($status, ['PRESENT', 'LATE', 'HOLIDAY'], true);
        $isCheckedIn = $checkinTs > 0;
        $allowanceEligible = $isPresentish;
        if ($allowanceEligible && $allowanceLateTreatment === 'DEDUCT_IF_LATE' && $status === 'LATE') {
            $allowanceEligible = false;
        }

        $basicAmount = 0.0;
        $allowanceAmount = 0.0;
        $mealAmount = 0.0;
        $overtimePay = 0.0;
        $lateDeduction = 0.0;
        $alphaDeduction = 0.0;
        $grossAmount = 0.0;
        $netAmount = 0.0;

        $mealAmount = ($mealMode === 'CUSTOM' && $isPresentish && $isCheckedIn) ? $mealRate : 0;

        if ($hasCompletedCheckout) {
            $basicAmount = $isPresentish ? $basicDailyRate : 0;
            $allowanceAmount = $allowanceEligible ? $allowanceDailyRate : 0;
            if ($overtimeMode === 'MANUAL') {
                $overtimePay = max(0, $manualOvertimePay);
            } else {
                $overtimeMinutes = max(0, (int)($dailyRow['overtime_minutes'] ?? 0));
                $overtimePay = ($overtimeMinutes > 0 && $overtimeRate > 0)
                    ? (($overtimeMinutes / 60) * $overtimeRate)
                    : 0;
            }
            $lateDeduction = $enableLateDeduction ? ($lateMinutes * $lateDeductionPerMinute) : 0;
            $alphaDeduction = ($enableAlphaDeduction && $status === 'ALPHA') ? $alphaDeductionPerDay : 0;

            if (!$hasConfiguredDeduction && $scheduledWorkMinutes > 0) {
                $ratio = max(0, min(1, $workMinutes / $scheduledWorkMinutes));
                $deductionFromProrate = 0.0;
                if ($attendanceMode === 'DAILY') {
                    if ($prorateScope === 'THP_TOTAL') {
                        $deductionFromProrate = ($basicAmount + $allowanceAmount + $mealAmount) * (1 - $ratio);
                    } else {
                        $deductionFromProrate = $basicAmount * (1 - $ratio);
                    }
                } else {
                    $missingRatio = max(0, 1 - $ratio);
                    if ($prorateScope === 'THP_TOTAL') {
                        $deductionFromProrate = (($basicDailyRate + $allowanceDailyRate + $mealAmount) * $missingRatio);
                    } else {
                        $deductionFromProrate = ($basicDailyRate * $missingRatio);
                    }
                }
                $lateDeduction += round($deductionFromProrate, 2);
            }

            $grossAmount = round($basicAmount + $allowanceAmount + $mealAmount + $overtimePay, 2);
            $baseForNet = ($mealMode === 'CUSTOM') ? ($grossAmount - $mealAmount) : $grossAmount;
            $netAmount = round($baseForNet - ($lateDeduction + $alphaDeduction), 2);
        }

        $manualAdj = $this->get_manual_adjustment_totals_by_date($employeeId, $attendanceDate);
        $manualAddition = (float)($manualAdj['addition'] ?? 0);
        $manualDeduction = (float)($manualAdj['deduction'] ?? 0);
        $manualNet = (float)($manualAdj['net'] ?? 0);

        $payload = [
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($this->att_daily_has_field('snapshot_basic_salary')) {
            $payload['snapshot_basic_salary'] = round($basicSalary, 2);
            $payload['snapshot_position_allowance'] = round($positionAllowance, 2);
            $payload['snapshot_objective_allowance'] = round($objectiveAllowance, 2);
            $payload['snapshot_meal_rate'] = round($mealRate, 2);
            $payload['snapshot_overtime_rate'] = round($overtimeRate, 2);
        }
        if ($this->att_daily_has_field('basic_amount')) {
            $payload['basic_amount'] = round($basicAmount, 2);
            $payload['allowance_amount'] = round($allowanceAmount, 2);
            $payload['meal_amount'] = round($mealAmount, 2);
            $payload['late_deduction_amount'] = round($lateDeduction, 2);
            $payload['alpha_deduction_amount'] = round($alphaDeduction, 2);
            $payload['gross_amount'] = round($grossAmount, 2);
            $payload['net_amount'] = round($netAmount, 2);
        }
        if ($this->att_daily_has_field('manual_addition_amount')) {
            $payload['manual_addition_amount'] = round($manualAddition, 2);
        }
        if ($this->att_daily_has_field('manual_deduction_amount')) {
            $payload['manual_deduction_amount'] = round($manualDeduction, 2);
        }
        if ($this->att_daily_has_field('manual_adjustment_net_amount')) {
            $payload['manual_adjustment_net_amount'] = round($manualNet, 2);
        }
        $payload['overtime_pay'] = round($overtimePay, 2);
        $payload['daily_salary_amount'] = round($netAmount + $manualNet, 2);

        return $payload;
    }

    private function resolve_scheduled_work_minutes(int $employeeId, string $date, int $shiftId): int
    {
        if ($date === '') {
            return 0;
        }

        $shift = null;
        if ($shiftId > 0) {
            $shift = $this->db->select('start_time, end_time, is_overnight')
                ->from('att_shift')
                ->where('id', $shiftId)
                ->limit(1)
                ->get()->row_array();
        }
        if (!$shift && $employeeId > 0) {
            $shift = $this->db->select('s.start_time, s.end_time, s.is_overnight')
                ->from('att_shift_schedule ss')
                ->join('att_shift s', 's.id = ss.shift_id', 'left')
                ->where('ss.employee_id', $employeeId)
                ->where('ss.schedule_date', $date)
                ->limit(1)
                ->get()->row_array();
        }
        if (!$shift) {
            return 0;
        }

        [$startTs, $endTs] = $this->shift_bounds_from_schedule($date, $shift);
        if ($startTs <= 0 || $endTs <= $startTs) {
            return 0;
        }
        return max(0, (int)floor(($endTs - $startTs) / 60));
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
}
