<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Payroll_preview_model extends CI_Model
{
    public function count_monthly_recap(array $filters): int
    {
        $employeeFilters = [
            'q' => trim((string)($filters['q'] ?? '')),
            'division_id' => (int)($filters['division_id'] ?? 0),
            'position_id' => (int)($filters['position_id'] ?? 0),
        ];
        return $this->count_employees($employeeFilters);
    }

    public function list_monthly_recap(array $filters, int $limit, int $offset): array
    {
        $employeeFilters = [
            'q' => trim((string)($filters['q'] ?? '')),
            'division_id' => (int)($filters['division_id'] ?? 0),
            'position_id' => (int)($filters['position_id'] ?? 0),
            'as_of' => (string)($filters['date_end'] ?? date('Y-m-d')),
        ];
        $baseRows = $this->list_preview_rows($employeeFilters, $limit, $offset);
        if (empty($baseRows)) {
            return [];
        }

        $dateStart = trim((string)($filters['date_start'] ?? date('Y-m-01')));
        $dateEnd = trim((string)($filters['date_end'] ?? date('Y-m-t')));
        $result = [];

        foreach ($baseRows as $base) {
            $employeeId = (int)($base['employee_id'] ?? 0);
            if ($employeeId <= 0) {
                continue;
            }

            [$summary, $dailyRows] = $this->estimate_employee_attendance_payroll($employeeId, $dateStart, $dateEnd);
            if (!$summary) {
                continue;
            }

            $scheduledDays = (int)$this->db->from('att_shift_schedule')
                ->where('employee_id', $employeeId)
                ->where('schedule_date >=', $dateStart)
                ->where('schedule_date <=', $dateEnd)
                ->count_all_results();

            $earlyLeaveMinutes = (int)$this->db->select('COALESCE(SUM(early_leave_minutes),0) AS total', false)
                ->from('att_daily')
                ->where('employee_id', $employeeId)
                ->where('attendance_date >=', $dateStart)
                ->where('attendance_date <=', $dateEnd)
                ->get()->row('total');

            $overtimeMinutes = (int)$this->db->select('COALESCE(SUM(overtime_minutes),0) AS total', false)
                ->from('att_daily')
                ->where('employee_id', $employeeId)
                ->where('attendance_date >=', $dateStart)
                ->where('attendance_date <=', $dateEnd)
                ->get()->row('total');

            $result[] = [
                'employee_id' => $employeeId,
                'employee_code' => (string)($summary['employee_code'] ?? ''),
                'employee_name' => (string)($summary['employee_name'] ?? ''),
                'division_name' => (string)($summary['division_name'] ?? ''),
                'position_name' => (string)($summary['position_name'] ?? ''),
                'source_type' => (string)($base['source_type'] ?? 'EMPLOYEE'),
                'source_ref' => (string)($base['source_ref'] ?? '-'),
                'scheduled_days' => $scheduledDays,
                'present_days' => (int)($summary['present_days'] ?? 0),
                'alpha_days' => (int)($summary['alpha_days'] ?? 0),
                'late_minutes' => (int)($summary['late_minutes'] ?? 0),
                'early_leave_minutes' => $earlyLeaveMinutes,
                'work_minutes' => (int)array_sum(array_map(static function ($row) {
                    return (int)($row['work_minutes'] ?? 0);
                }, $dailyRows)),
                'overtime_minutes' => $overtimeMinutes,
                'basic_salary' => (float)($summary['basic_salary'] ?? 0),
                'position_allowance' => (float)($summary['position_allowance'] ?? 0),
                'objective_allowance' => (float)($summary['objective_allowance'] ?? 0),
                'meal_rate' => (float)($summary['meal_rate'] ?? 0),
                'overtime_rate' => (float)($summary['overtime_rate'] ?? 0),
                'basic_total' => (float)($summary['basic_estimate'] ?? 0),
                'allowance_total' => (float)($summary['allowance_estimate'] ?? 0),
                'meal_total' => (float)($summary['meal_estimate'] ?? 0),
                'overtime_total' => (float)($summary['overtime_estimate'] ?? 0),
                'late_deduction_total' => (float)($summary['late_deduction'] ?? 0),
                'alpha_deduction_total' => (float)($summary['alpha_deduction'] ?? 0),
                'manual_addition_total' => (float)($summary['manual_addition'] ?? 0),
                'manual_deduction_total' => (float)($summary['manual_deduction'] ?? 0),
                'manual_adjustment_net_total' => (float)($summary['manual_adjustment_net'] ?? 0),
                'gross_total' => (float)($summary['gross_estimate'] ?? 0),
                'net_only_total' => (float)($summary['net_only_estimate'] ?? 0),
                'net_total' => (float)($summary['net_estimate'] ?? 0),
            ];
        }

        return $result;
    }

    public function estimate_employee_attendance_payroll(int $employeeId, string $dateStart, string $dateEnd): array
    {
        $employee = $this->db->select('e.*, d.division_name, p.position_name')
            ->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->where('e.id', $employeeId)
            ->where('e.is_active', 1)
            ->limit(1)
            ->get()->row_array();
        if (!$employee) {
            return [null, []];
        }

        $policy = $this->db->from('att_attendance_policy')
            ->where('is_active', 1)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get()->row_array();
        if (!$policy) {
            $policy = [];
        }

        $rows = $this->db->select('ad.*, s.shift_code, s.shift_name')
            ->from('att_daily ad')
            ->join('att_shift s', 's.id = ad.shift_id', 'left')
            ->where('ad.employee_id', $employeeId)
            ->where('ad.attendance_date >=', $dateStart)
            ->where('ad.attendance_date <=', $dateEnd)
            ->order_by('ad.attendance_date', 'ASC')
            ->get()->result_array();

        $baseRow = $this->list_preview_rows([
            'employee_id' => $employeeId,
            'as_of' => $dateEnd,
        ], 1, 0);
        $base = $baseRow[0] ?? null;

        $basicSalary = (float)($base['basic_salary'] ?? $employee['basic_salary'] ?? 0);
        $positionAllowance = (float)($base['position_allowance'] ?? $employee['position_allowance'] ?? 0);
        $objectiveAllowance = (float)($base['objective_allowance'] ?? $employee['objective_allowance'] ?? 0);
        $mealRate = (float)($base['meal_rate'] ?? $employee['meal_rate'] ?? 0);
        $overtimeRateUsed = (float)($base['overtime_rate'] ?? $employee['overtime_rate'] ?? 0);
        $attendanceMode = strtoupper((string)($policy['attendance_calc_mode'] ?? 'DAILY'));
        $mealMode = strtoupper((string)($policy['meal_calc_mode'] ?? 'MONTHLY'));
        $workDays = max(1, (int)($policy['default_work_days_per_month'] ?? 26));

        $presentDays = 0;
        $alphaDays = 0;
        $totalLateMinutes = 0;
        $dailyRows = [];
        $hasManualDailyCols = $this->db->field_exists('manual_addition_amount', 'att_daily')
            && $this->db->field_exists('manual_deduction_amount', 'att_daily')
            && $this->db->field_exists('manual_adjustment_net_amount', 'att_daily');

        $hasBreakdown = $this->db->field_exists('basic_amount', 'att_daily')
            && $this->db->field_exists('allowance_amount', 'att_daily')
            && $this->db->field_exists('meal_amount', 'att_daily')
            && $this->db->field_exists('late_deduction_amount', 'att_daily')
            && $this->db->field_exists('alpha_deduction_amount', 'att_daily')
            && $this->db->field_exists('gross_amount', 'att_daily')
            && $this->db->field_exists('net_amount', 'att_daily');

        foreach ($rows as $row) {
            $status = strtoupper((string)($row['attendance_status'] ?? 'OFF'));
            $lateMinutes = (int)($row['late_minutes'] ?? 0);
            $workMinutes = (int)($row['work_minutes'] ?? 0);
            $isClosed = !empty($row['checkout_at']);
            $overtimePay = (float)($row['overtime_pay'] ?? 0);

            $isPresentish = in_array($status, ['PRESENT', 'LATE', 'HOLIDAY'], true);
            if ($isPresentish) {
                $presentDays += 1;
            }
            if ($status === 'ALPHA') {
                $alphaDays += 1;
            }

            $basicEst = 0.0;
            $allowEst = 0.0;
            $mealEst = 0.0;
            $lateDeduction = 0.0;
            $alphaDeduction = 0.0;
            $grossAmount = 0.0;
            $netAmount = 0.0;
            $dailyTakeHome = 0.0;

            if ($hasBreakdown) {
                $basicEst = $isClosed ? (float)($row['basic_amount'] ?? 0) : 0.0;
                $allowEst = $isClosed ? (float)($row['allowance_amount'] ?? 0) : 0.0;
                $mealEst = $isClosed ? (float)($row['meal_amount'] ?? 0) : 0.0;
                $overtimePay = $isClosed ? (float)($row['overtime_pay'] ?? 0) : 0.0;
                $lateDeduction = $isClosed ? (float)($row['late_deduction_amount'] ?? 0) : 0.0;
                $alphaDeduction = $isClosed ? (float)($row['alpha_deduction_amount'] ?? 0) : 0.0;
                $grossAmount = $isClosed ? (float)($row['gross_amount'] ?? 0) : 0.0;
                $netAmount = $isClosed ? (float)($row['net_amount'] ?? 0) : 0.0;
                $dailyTakeHome = $isClosed ? (float)($row['daily_salary_amount'] ?? $netAmount) : 0.0;
            } else {
                // Fallback bila kolom breakdown belum ada.
                $dailyTakeHome = 0.0;
            }

            $dailyRows[] = [
                'attendance_date' => (string)$row['attendance_date'],
                'shift_code' => (string)($row['shift_code'] ?? '-'),
                'status' => $status,
                'checkin_at' => (string)($row['checkin_at'] ?? ''),
                'checkout_at' => (string)($row['checkout_at'] ?? ''),
                'late_minutes' => $lateMinutes,
                'early_leave_minutes' => (int)($row['early_leave_minutes'] ?? 0),
                'work_minutes' => $workMinutes,
                'overtime_minutes' => (int)($row['overtime_minutes'] ?? 0),
                'basic_est' => round($basicEst, 2),
                'allowance_est' => round($allowEst, 2),
                'meal_est' => round($mealEst, 2),
                'late_deduction' => round($lateDeduction, 2),
                'alpha_deduction' => round($alphaDeduction, 2),
                'overtime_pay' => round($overtimePay, 2),
                'gross_amount' => round($grossAmount, 2),
                'net_amount' => round($netAmount, 2),
                'manual_addition_amount' => $isClosed ? round((float)($row['manual_addition_amount'] ?? 0), 2) : 0.0,
                'manual_deduction_amount' => $isClosed ? round((float)($row['manual_deduction_amount'] ?? 0), 2) : 0.0,
                'manual_adjustment_net_amount' => $isClosed ? round((float)($row['manual_adjustment_net_amount'] ?? 0), 2) : 0.0,
                'day_total' => round($dailyTakeHome, 2),
            ];

            $totalLateMinutes += $lateMinutes;
        }

        $basicEstimate = round(array_sum(array_map(static function ($r) {
            return (float)($r['basic_est'] ?? 0);
        }, $dailyRows)), 2);
        $allowanceEstimate = round(array_sum(array_map(static function ($r) {
            return (float)($r['allowance_est'] ?? 0);
        }, $dailyRows)), 2);
        $mealEstimate = round(array_sum(array_map(static function ($r) {
            return (float)($r['meal_est'] ?? 0);
        }, $dailyRows)), 2);
        $lateDeductionTotal = round(array_sum(array_map(static function ($r) {
            return (float)($r['late_deduction'] ?? 0);
        }, $dailyRows)), 2);
        $alphaDeductionTotal = round(array_sum(array_map(static function ($r) {
            return (float)($r['alpha_deduction'] ?? 0);
        }, $dailyRows)), 2);
        $overtimeTotal = round(array_sum(array_map(static function ($r) {
            return (float)($r['overtime_pay'] ?? 0);
        }, $dailyRows)), 2);
        $grossEstimate = round(array_sum(array_map(static function ($r) {
            return (float)($r['gross_amount'] ?? 0);
        }, $dailyRows)), 2);
        $netOnlyEstimate = round(array_sum(array_map(static function ($r) {
            return (float)($r['net_amount'] ?? 0);
        }, $dailyRows)), 2);
        $thpEstimate = round(array_sum(array_map(static function ($r) {
            return (float)($r['day_total'] ?? 0);
        }, $dailyRows)), 2);

        if ($hasManualDailyCols) {
            $manualAddition = round(array_sum(array_map(static function ($r) {
                return (float)($r['manual_addition_amount'] ?? 0);
            }, $dailyRows)), 2);
            $manualDeduction = round(array_sum(array_map(static function ($r) {
                return (float)($r['manual_deduction_amount'] ?? 0);
            }, $dailyRows)), 2);
            $manualNet = round(array_sum(array_map(static function ($r) {
                return (float)($r['manual_adjustment_net_amount'] ?? 0);
            }, $dailyRows)), 2);
            $thpBeforeAdjustment = round($netOnlyEstimate, 2);
            $thpAfterAdjustment = round($thpEstimate, 2);
        } else {
            $manualAdjustment = $this->get_manual_adjustment_summary($employeeId, $dateStart, $dateEnd);
            $manualAddition = (float)($manualAdjustment['addition_total'] ?? 0);
            $manualDeduction = (float)($manualAdjustment['deduction_total'] ?? 0);
            $manualNet = $manualAddition - $manualDeduction;
            $thpBeforeAdjustment = round($thpEstimate, 2);
            $thpAfterAdjustment = round($thpBeforeAdjustment + $manualNet, 2);
        }

        $summary = [
            'employee_id' => (int)$employee['id'],
            'employee_code' => (string)$employee['employee_code'],
            'employee_name' => (string)$employee['employee_name'],
            'division_name' => (string)($employee['division_name'] ?? ''),
            'position_name' => (string)($employee['position_name'] ?? ''),
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'attendance_mode' => $attendanceMode,
            'meal_mode' => $mealMode,
            'work_days_basis' => $workDays,
            'present_days' => $presentDays,
            'allowance_eligible_days' => 0,
            'alpha_days' => $alphaDays,
            'late_minutes' => $totalLateMinutes,
            'basic_salary' => round($basicSalary, 2),
            'position_allowance' => round($positionAllowance, 2),
            'objective_allowance' => round($objectiveAllowance, 2),
            'meal_rate' => round($mealRate, 2),
            'overtime_rate' => round($overtimeRateUsed, 2),
            'basic_estimate' => round($basicEstimate, 2),
            'allowance_estimate' => round($allowanceEstimate, 2),
            'meal_estimate' => round($mealEstimate, 2),
            'overtime_estimate' => round($overtimeTotal, 2),
            'late_deduction' => round($lateDeductionTotal, 2),
            'alpha_deduction' => round($alphaDeductionTotal, 2),
            'gross_estimate' => round($grossEstimate, 2),
            'net_only_estimate' => round($netOnlyEstimate, 2),
            'manual_addition' => round($manualAddition, 2),
            'manual_deduction' => round($manualDeduction, 2),
            'manual_adjustment_net' => round($manualNet, 2),
            'net_estimate_before_adjustment' => $thpBeforeAdjustment,
            'net_estimate' => $thpAfterAdjustment,
        ];

        return [$summary, $dailyRows];
    }

    private function get_manual_adjustment_summary(int $employeeId, string $dateStart, string $dateEnd): array
    {
        if ($employeeId <= 0 || !$this->db->table_exists('pay_manual_adjustment')) {
            return ['addition_total' => 0.0, 'deduction_total' => 0.0];
        }

        $rows = $this->db->select('adjustment_kind, COALESCE(SUM(amount),0) AS total_amount', false)
            ->from('pay_manual_adjustment')
            ->where('employee_id', $employeeId)
            ->where('status', 'APPROVED')
            ->where('adjustment_date >=', $dateStart)
            ->where('adjustment_date <=', $dateEnd)
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
            'addition_total' => round($addition, 2),
            'deduction_total' => round($deduction, 2),
        ];
    }

    public function count_employees(array $filters): int
    {
        $this->apply_employee_filter_query($filters);
        return (int)$this->db->count_all_results();
    }

    public function list_preview_rows(array $filters, int $limit, int $offset): array
    {
        $asOf = $this->normalize_as_of($filters['as_of'] ?? null);

        $employeeRows = $this->db->select('e.id, e.employee_code, e.employee_name, e.join_date, e.employment_status, e.basic_salary, e.position_allowance, e.objective_allowance, e.meal_rate, e.overtime_rate, d.division_name, p.position_name, e.division_id, e.position_id')
            ->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->where('e.is_active', 1);

        if (!empty($filters['q'])) {
            $q = trim((string)$filters['q']);
            $employeeRows->group_start()
                ->like('e.employee_code', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('d.division_name', $q)
                ->or_like('p.position_name', $q)
            ->group_end();
        }
        if (!empty($filters['division_id'])) {
            $employeeRows->where('e.division_id', (int)$filters['division_id']);
        }
        if (!empty($filters['position_id'])) {
            $employeeRows->where('e.position_id', (int)$filters['position_id']);
        }
        if (!empty($filters['employee_id'])) {
            $employeeRows->where('e.id', (int)$filters['employee_id']);
        }

        $employees = $employeeRows
            ->order_by('e.employee_name', 'ASC')
            ->order_by('e.id', 'ASC')
            ->limit($limit, $offset)
            ->get()->result_array();

        if (empty($employees)) {
            return [];
        }

        $employeeIds = array_map(static function (array $r): int {
            return (int)$r['id'];
        }, $employees);

        $assignmentMap = $this->get_active_assignment_map($employeeIds, $asOf);
        $profileComponentMap = $this->get_profile_component_map(array_values(array_unique(array_filter(array_map(static function (array $r): int {
            return (int)($r['profile_id'] ?? 0);
        }, $assignmentMap)))));
        $contractMap = $this->get_active_contract_map($employeeIds, $asOf);
        $objectiveOverrideMap = $this->get_active_objective_override_map($employeeIds, $asOf);
        $basicStandards = $this->get_active_basic_standards($asOf);

        $result = [];
        foreach ($employees as $employee) {
            $employeeId = (int)$employee['id'];
            $assignment = $assignmentMap[$employeeId] ?? null;
            $contract = $contractMap[$employeeId] ?? null;
            $objectiveOverride = $objectiveOverrideMap[$employeeId] ?? null;
            $standard = $this->resolve_basic_standard_for_employee($employee, $basicStandards, $asOf);
            $yearsOfService = $this->years_of_service((string)($employee['join_date'] ?? ''), $asOf);
            $profileComponent = [];
            if (!empty($assignment['profile_id'])) {
                $profileComponent = $profileComponentMap[(int)$assignment['profile_id']] ?? [];
            }

            $employeeBasic = (float)($employee['basic_salary'] ?? 0);
            $employeePosition = (float)($employee['position_allowance'] ?? 0);
            $employeeObjective = (float)($employee['objective_allowance'] ?? 0);
            $employeeMeal = (float)($employee['meal_rate'] ?? 0);
            $employeeOvertime = (float)($employee['overtime_rate'] ?? 0);
            $employeeFixed = round($employeeBasic + $employeePosition + $employeeObjective, 2);

            $source = 'EMPLOYEE';
            $sourceRef = '-';
            $basic = $employeeBasic;
            $position = $employeePosition;
            $objective = $employeeObjective;
            $meal = $employeeMeal;
            $overtime = $employeeOvertime;

            if ($contract) {
                $source = 'CONTRACT';
                $sourceRef = (string)($contract['contract_number'] ?? '-');
                $basic = (float)($contract['effective_basic_salary'] ?? $basic);
                $position = (float)($contract['effective_position_allowance'] ?? $position);
                $objective = (float)($contract['effective_other_allowance'] ?? $objective);
                $meal = (float)($contract['effective_meal_rate'] ?? $meal);
                $overtime = (float)($contract['effective_overtime_rate'] ?? $overtime);
            } elseif ($assignment) {
                $source = 'ASSIGNMENT';
                $sourceRef = trim((string)($assignment['profile_code'] ?? '') . ' - ' . (string)($assignment['profile_name'] ?? ''));
                if ($sourceRef === '-' || $sourceRef === '') {
                    $sourceRef = (string)($assignment['profile_name'] ?? '-');
                }

                if ($standard) {
                    $basic = (float)($standard['resolved_amount'] ?? $basic);
                }
                if (isset($profileComponent['POSITION_ALLOWANCE'])) {
                    $position = (float)$profileComponent['POSITION_ALLOWANCE'];
                }
                if (isset($profileComponent['OBJECTIVE_ALLOWANCE'])) {
                    $objective = (float)$profileComponent['OBJECTIVE_ALLOWANCE'];
                }
                if (isset($profileComponent['MEAL_ALLOWANCE'])) {
                    $meal = (float)$profileComponent['MEAL_ALLOWANCE'];
                }
            } elseif ($standard) {
                $source = 'STANDARD';
                $sourceRef = (string)($standard['standard_code'] ?? '-');
                $basic = (float)($standard['resolved_amount'] ?? $basic);
            }

            if ($objectiveOverride !== null) {
                $objective = (float)$objectiveOverride;
                if ($source === 'ASSIGNMENT' || $source === 'STANDARD' || $source === 'EMPLOYEE') {
                    $sourceRef = trim($sourceRef . ' + OBJ_OVERRIDE');
                }
            }

            $previewFixed = round($basic + $position + $objective, 2);

            $result[] = [
                'employee_id' => $employeeId,
                'employee_code' => (string)($employee['employee_code'] ?? ''),
                'employee_name' => (string)($employee['employee_name'] ?? ''),
                'division_name' => (string)($employee['division_name'] ?? ''),
                'position_name' => (string)($employee['position_name'] ?? ''),
                'employment_status' => (string)($employee['employment_status'] ?? ''),
                'years_of_service' => $yearsOfService,
                'source_type' => $source,
                'source_ref' => $sourceRef,
                'basic_salary' => round($basic, 2),
                'position_allowance' => round($position, 2),
                'objective_allowance' => round($objective, 2),
                'meal_rate' => round($meal, 2),
                'overtime_rate' => round($overtime, 2),
                'fixed_total' => $previewFixed,
                'employee_fixed_total' => $employeeFixed,
                'delta_fixed_total' => round($previewFixed - $employeeFixed, 2),
            ];
        }

        return $result;
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

    private function apply_employee_filter_query(array $filters): void
    {
        $this->db->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->where('e.is_active', 1);

        if (!empty($filters['q'])) {
            $q = trim((string)$filters['q']);
            $this->db->group_start()
                ->like('e.employee_code', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('d.division_name', $q)
                ->or_like('p.position_name', $q)
            ->group_end();
        }
        if (!empty($filters['division_id'])) {
            $this->db->where('e.division_id', (int)$filters['division_id']);
        }
        if (!empty($filters['position_id'])) {
            $this->db->where('e.position_id', (int)$filters['position_id']);
        }
        if (!empty($filters['employee_id'])) {
            $this->db->where('e.id', (int)$filters['employee_id']);
        }
    }

    private function get_active_assignment_map(array $employeeIds, string $asOf): array
    {
        if (empty($employeeIds)) {
            return [];
        }

        $rows = $this->db->select('a.*, p.profile_code, p.profile_name')
            ->from('pay_salary_assignment a')
            ->join('pay_salary_profile p', 'p.id = a.profile_id', 'left')
            ->where_in('a.employee_id', $employeeIds)
            ->where('a.is_active', 1)
            ->where('a.effective_start <=', $asOf)
            ->group_start()
                ->where('a.effective_end IS NULL', null, false)
                ->or_where('a.effective_end >=', $asOf)
            ->group_end()
            ->order_by('a.employee_id', 'ASC')
            ->order_by('a.effective_start', 'DESC')
            ->order_by('a.id', 'DESC')
            ->get()->result_array();

        $map = [];
        foreach ($rows as $row) {
            $empId = (int)$row['employee_id'];
            if (!isset($map[$empId])) {
                $map[$empId] = $row;
            }
        }
        return $map;
    }

    private function get_profile_component_map(array $profileIds): array
    {
        if (empty($profileIds)) {
            return [];
        }

        $rows = $this->db->select('l.profile_id, c.component_code, SUM(l.amount) AS total_amount', false)
            ->from('pay_salary_profile_line l')
            ->join('pay_salary_component c', 'c.id = l.component_id', 'inner')
            ->where_in('l.profile_id', $profileIds)
            ->where('l.is_active', 1)
            ->where('c.is_active', 1)
            ->group_by(['l.profile_id', 'c.component_code'])
            ->get()->result_array();

        $map = [];
        foreach ($rows as $row) {
            $profileId = (int)$row['profile_id'];
            if (!isset($map[$profileId])) {
                $map[$profileId] = [];
            }
            $map[$profileId][(string)$row['component_code']] = (float)($row['total_amount'] ?? 0);
        }
        return $map;
    }

    private function get_active_contract_map(array $employeeIds, string $asOf): array
    {
        if (empty($employeeIds)) {
            return [];
        }

        $rows = $this->db->select('c.id, c.employee_id, c.contract_number, c.status, c.start_date, c.end_date, c.basic_salary, c.position_allowance, c.other_allowance, c.meal_rate, c.overtime_rate,
                s.basic_salary_amount, s.position_allowance_amount, s.other_allowance_amount, s.meal_rate_amount, s.overtime_rate_amount')
            ->from('hr_contract c')
            ->join('hr_contract_comp_snapshot s', 's.contract_id = c.id', 'left')
            ->where_in('c.employee_id', $employeeIds)
            ->where_in('c.status', ['ACTIVE', 'SIGNED'])
            ->where('c.start_date <=', $asOf)
            ->where('c.end_date >=', $asOf)
            ->order_by('c.employee_id', 'ASC')
            ->order_by("FIELD(c.status,'ACTIVE','SIGNED')", '', false)
            ->order_by('c.start_date', 'DESC')
            ->order_by('c.id', 'DESC')
            ->get()->result_array();

        $map = [];
        foreach ($rows as $row) {
            $empId = (int)$row['employee_id'];
            if (isset($map[$empId])) {
                continue;
            }

            $row['effective_basic_salary'] = $row['basic_salary_amount'] !== null ? (float)$row['basic_salary_amount'] : (float)$row['basic_salary'];
            $row['effective_position_allowance'] = $row['position_allowance_amount'] !== null ? (float)$row['position_allowance_amount'] : (float)$row['position_allowance'];
            $row['effective_other_allowance'] = $row['other_allowance_amount'] !== null ? (float)$row['other_allowance_amount'] : (float)$row['other_allowance'];
            $row['effective_meal_rate'] = $row['meal_rate_amount'] !== null ? (float)$row['meal_rate_amount'] : (float)$row['meal_rate'];
            $row['effective_overtime_rate'] = $row['overtime_rate_amount'] !== null ? (float)$row['overtime_rate_amount'] : (float)$row['overtime_rate'];
            $map[$empId] = $row;
        }

        return $map;
    }

    private function get_active_objective_override_map(array $employeeIds, string $asOf): array
    {
        if (empty($employeeIds) || !$this->db->table_exists('pay_objective_override')) {
            return [];
        }

        $rows = $this->db->select('employee_id, override_amount')
            ->from('pay_objective_override')
            ->where_in('employee_id', $employeeIds)
            ->where('is_active', 1)
            ->where('effective_start <=', $asOf)
            ->group_start()
                ->where('effective_end IS NULL', null, false)
                ->or_where('effective_end >=', $asOf)
            ->group_end()
            ->order_by('employee_id', 'ASC')
            ->order_by('effective_start', 'DESC')
            ->order_by('id', 'DESC')
            ->get()->result_array();

        $map = [];
        foreach ($rows as $row) {
            $empId = (int)$row['employee_id'];
            if (!isset($map[$empId])) {
                $map[$empId] = (float)$row['override_amount'];
            }
        }
        return $map;
    }

    private function get_active_basic_standards(string $asOf): array
    {
        if (!$this->db->table_exists('pay_basic_salary_standard')) {
            return [];
        }

        return $this->db->select('*')
            ->from('pay_basic_salary_standard')
            ->where('is_active', 1)
            ->where('effective_start <=', $asOf)
            ->group_start()
                ->where('effective_end IS NULL', null, false)
                ->or_where('effective_end >=', $asOf)
            ->group_end()
            ->order_by('effective_start', 'DESC')
            ->order_by('id', 'DESC')
            ->get()->result_array();
    }

    private function resolve_basic_standard_for_employee(array $employee, array $standards, string $asOf): ?array
    {
        if (empty($standards)) {
            return null;
        }

        $best = null;
        $bestScore = -1;
        $employeeDivisionId = (int)($employee['division_id'] ?? 0);
        $employeePositionId = (int)($employee['position_id'] ?? 0);
        $employeeStatus = strtoupper(trim((string)($employee['employment_status'] ?? '')));

        foreach ($standards as $row) {
            $divisionId = (int)($row['division_id'] ?? 0);
            $positionId = (int)($row['position_id'] ?? 0);
            $employmentType = strtoupper(trim((string)($row['employment_type'] ?? '')));

            if ($positionId > 0 && $positionId !== $employeePositionId) {
                continue;
            }
            if ($divisionId > 0 && $divisionId !== $employeeDivisionId) {
                continue;
            }
            if ($employmentType !== '' && $employmentType !== $employeeStatus) {
                continue;
            }

            $score = 0;
            if ($positionId > 0) {
                $score += 4;
            }
            if ($divisionId > 0) {
                $score += 2;
            }
            if ($employmentType !== '') {
                $score += 1;
            }

            if ($score > $bestScore) {
                $best = $row;
                $bestScore = $score;
            }
        }

        if (!$best) {
            return null;
        }

        $years = $this->years_of_service((string)($employee['join_date'] ?? ''), $asOf);
        $cap = array_key_exists('year_cap', $best) && $best['year_cap'] !== null ? (int)$best['year_cap'] : null;
        if ($cap !== null && $cap >= 0) {
            $years = min($years, $cap);
        }

        $start = (float)($best['start_amount'] ?? 0);
        $increment = (float)($best['annual_increment'] ?? 0);
        $best['resolved_amount'] = round($start + ($increment * $years), 2);
        return $best;
    }

    private function years_of_service(string $joinDate, string $asOfDate): int
    {
        $joinDate = trim($joinDate);
        if ($joinDate === '' || $joinDate === '0000-00-00') {
            return 0;
        }

        $start = strtotime($joinDate);
        $end = strtotime($asOfDate);
        if (!$start || !$end || $end < $start) {
            return 0;
        }

        return max(0, (int)floor((date('Y', $end) - date('Y', $start)) - ((date('md', $end) < date('md', $start)) ? 1 : 0)));
    }

    private function normalize_as_of($raw): string
    {
        $asOf = trim((string)$raw);
        if ($asOf === '') {
            return date('Y-m-d');
        }
        $ts = strtotime($asOf);
        return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
    }
}
