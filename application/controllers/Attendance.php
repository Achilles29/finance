<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Attendance extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Attendance_model');
    }

    private function per_page(): int
    {
        $pp = (int)$this->input->get('per_page', true);
        return in_array($pp, [10, 25, 50, 100], true) ? $pp : 25;
    }

    private function page(): int
    {
        return max(1, (int)$this->input->get('page', true));
    }

    private function build_pagination(int $total, int $perPage, int $page): array
    {
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        return [
            'total' => $total,
            'per_page' => $perPage,
            'page' => $page,
            'total_pages' => $totalPages,
            'offset' => ($page - 1) * $perPage,
        ];
    }

    private function normalize_date_range(string $dateStart, string $dateEnd, int $maxDays = 62): array
    {
        if ($dateStart === '') {
            $dateStart = date('Y-m-01');
        }
        if ($dateEnd === '') {
            $dateEnd = date('Y-m-t', strtotime($dateStart));
        }
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

        if ($maxDays > 0) {
            $rangeDays = (int)floor(($endTs - $startTs) / 86400) + 1;
            if ($rangeDays > $maxDays) {
                $endTs = strtotime('+' . ($maxDays - 1) . ' day', $startTs);
                $dateEnd = date('Y-m-d', $endTs);
            }
        }

        return [$dateStart, $dateEnd];
    }

    private function actor_employee_id(): int
    {
        return (int)($this->current_user['employee_id'] ?? 0);
    }

    public function settings()
    {
        $this->require_permission('attendance.settings.index', 'view');
        $currentPolicy = $this->Attendance_model->get_active_policy();

        if ($this->input->method() === 'post') {
            $this->require_permission('attendance.settings.index', 'edit');

            $phMode = strtoupper(trim((string)$this->input->post('ph_attendance_mode', true)));
            if (!in_array($phMode, ['AUTO_PRESENT', 'MANUAL_CLOCK'], true)) {
                $phMode = 'AUTO_PRESENT';
            }
            $phGrantMode = strtoupper(trim((string)$this->input->post('ph_grant_mode', true)));
            if (!in_array($phGrantMode, ['SHIFT_ONLY', 'HOLIDAY_ONLY', 'SHIFT_OR_HOLIDAY'], true)) {
                $phGrantMode = strtoupper((string)($currentPolicy['ph_grant_mode'] ?? 'HOLIDAY_ONLY'));
            }
            $phGrantHolidayType = strtoupper(trim((string)$this->input->post('ph_grant_holiday_type', true)));
            if (!in_array($phGrantHolidayType, ['ANY', 'NATIONAL', 'COMPANY', 'SPECIAL'], true)) {
                $phGrantHolidayType = strtoupper((string)($currentPolicy['ph_grant_holiday_type'] ?? 'ANY'));
            }

            $pendingScope = strtoupper(trim((string)$this->input->post('pending_request_scope', true)));
            if (!in_array($pendingScope, ['SELF_ONLY', 'POSITION_ONLY', 'SELF_AND_POSITION'], true)) {
                $pendingScope = 'SELF_ONLY';
            }

            $attendanceMode = strtoupper(trim((string)$this->input->post('attendance_calc_mode', true)));
            if (!in_array($attendanceMode, ['DAILY', 'MONTHLY'], true)) {
                $attendanceMode = strtoupper((string)($currentPolicy['attendance_calc_mode'] ?? 'DAILY'));
            }

            $allowanceLate = strtoupper(trim((string)$this->input->post('allowance_late_treatment', true)));
            if (!in_array($allowanceLate, ['FULL_IF_PRESENT', 'DEDUCT_IF_LATE'], true)) {
                $allowanceLate = strtoupper((string)($currentPolicy['allowance_late_treatment'] ?? 'FULL_IF_PRESENT'));
            }

            $mealMode = strtoupper(trim((string)$this->input->post('meal_calc_mode', true)));
            if (!in_array($mealMode, ['MONTHLY', 'CUSTOM'], true)) {
                $mealMode = strtoupper((string)($currentPolicy['meal_calc_mode'] ?? 'MONTHLY'));
            }
            $overtimeMode = strtoupper(trim((string)$this->input->post('overtime_calc_mode', true)));
            if (!in_array($overtimeMode, ['AUTO', 'MANUAL'], true)) {
                $overtimeMode = strtoupper((string)($currentPolicy['overtime_calc_mode'] ?? 'AUTO'));
            }

            $prorateScope = strtoupper(trim((string)$this->input->post('prorate_deduction_scope', true)));
            if (!in_array($prorateScope, ['BASIC_ONLY', 'THP_TOTAL'], true)) {
                $prorateScope = strtoupper((string)($currentPolicy['prorate_deduction_scope'] ?? 'BASIC_ONLY'));
            }

            $payload = [
                'policy_code' => strtoupper(trim((string)$this->input->post('policy_code', true))),
                'policy_name' => trim((string)$this->input->post('policy_name', true)),
                'checkin_open_minutes_before' => (int)$this->input->post('checkin_open_minutes_before', true),
                'enforce_geofence' => $this->input->post('enforce_geofence') ? 1 : 0,
                'require_photo' => $this->input->post('require_photo') ? 1 : 0,
                'late_deduction_per_minute' => (float)$this->input->post('late_deduction_per_minute', true),
                'alpha_deduction_per_day' => (float)$this->input->post('alpha_deduction_per_day', true),
                'use_basic_salary_daily_rate' => array_key_exists('use_basic_salary_daily_rate', $_POST)
                    ? ($this->input->post('use_basic_salary_daily_rate') ? 1 : 0)
                    : (int)($currentPolicy['use_basic_salary_daily_rate'] ?? 1),
                'default_work_days_per_month' => (int)$this->input->post('default_work_days_per_month', true),
                'attendance_calc_mode' => $attendanceMode,
                'payroll_late_deduction_scope' => $prorateScope,
                'allowance_late_treatment' => $allowanceLate,
                'meal_calc_mode' => $mealMode,
                'overtime_calc_mode' => $overtimeMode,
                'operation_start_time' => trim((string)$this->input->post('operation_start_time', true)),
                'operation_end_time' => trim((string)$this->input->post('operation_end_time', true)),
                'night_shift_checkout_credit_after' => trim((string)$this->input->post('night_shift_checkout_credit_after', true)),
                'night_shift_checkout_credit_to_operation_end' => $this->input->post('night_shift_checkout_credit_to_operation_end') ? 1 : 0,
                'checkout_close_minutes_after' => (int)$this->input->post('checkout_close_minutes_after', true),
                'enable_late_deduction' => $this->input->post('enable_late_deduction') ? 1 : 0,
                'enable_alpha_deduction' => $this->input->post('enable_alpha_deduction') ? 1 : 0,
                'prorate_deduction_scope' => $prorateScope,
                'pending_request_scope' => $pendingScope,
                'pending_approval_levels' => max(1, min(3, (int)$this->input->post('pending_approval_levels', true))),
                'ph_attendance_mode' => $phMode,
                'ph_grant_mode' => $phGrantMode,
                'ph_grant_holiday_type' => $phGrantHolidayType,
                'ph_grant_requires_checkout' => $this->input->post('ph_grant_requires_checkout') ? 1 : 0,
                'ph_grant_qty_per_day' => (float)$this->input->post('ph_grant_qty_per_day', true),
                'ph_auto_presence_on_open' => $phMode === 'AUTO_PRESENT' ? 1 : 0,
                'ph_requires_clock_in_out' => $phMode === 'MANUAL_CLOCK' ? 1 : 0,
                'ph_expiry_months' => (int)$this->input->post('ph_expiry_months', true),
                'ph_gets_meal_allowance' => $this->input->post('ph_gets_meal_allowance') ? 1 : 0,
                'ph_gets_bonus' => $this->input->post('ph_gets_bonus') ? 1 : 0,
            ];

            if ($payload['policy_code'] === '') {
                $payload['policy_code'] = 'FINANCE_DEFAULT';
            }
            if ($payload['policy_name'] === '') {
                $payload['policy_name'] = 'Finance Default Policy';
            }
            if (($payload['ph_grant_qty_per_day'] ?? 0) <= 0) {
                $payload['ph_grant_qty_per_day'] = 1;
            }

            $this->Attendance_model->save_policy(
                $payload,
                (array)$this->input->post('pending_submitter_position_ids'),
                [
                    1 => (array)$this->input->post('pending_verifier_l1_position_ids'),
                    2 => (array)$this->input->post('pending_verifier_l2_position_ids'),
                    3 => (array)$this->input->post('pending_verifier_l3_position_ids'),
                ]
            );
            $this->session->set_flashdata('success', 'Pengaturan absensi berhasil disimpan.');
            redirect('attendance/settings');
        }

        $policy = $this->Attendance_model->get_active_policy();
        $policyId = (int)($policy['id'] ?? 0);
        $data = [
            'title' => 'Pengaturan Absensi & Payroll',
            'active_menu' => 'grp.hr',
            'policy' => $policy,
            'position_options' => $this->Attendance_model->get_position_options(),
            'pending_submitter_position_ids' => $this->Attendance_model->get_pending_submitter_position_ids($policyId),
            'pending_verifier_l1_position_ids' => $this->Attendance_model->get_pending_verifier_position_ids($policyId, 1),
            'pending_verifier_l2_position_ids' => $this->Attendance_model->get_pending_verifier_position_ids($policyId, 2),
            'pending_verifier_l3_position_ids' => $this->Attendance_model->get_pending_verifier_position_ids($policyId, 3),
        ];
        $this->render('attendance/settings', $data);
    }

    public function daily()
    {
        $this->require_permission('attendance.daily.index', 'view');
        $this->load->model('Payroll_preview_model');

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'division_id' => (int)$this->input->get('division_id', true),
            'status' => strtoupper(trim((string)$this->input->get('status', true))),
            'date_start' => trim((string)$this->input->get('date_start', true)),
            'date_end' => trim((string)$this->input->get('date_end', true)),
        ];
        $tab = strtolower(trim((string)$this->input->get('tab', true)));
        if (!in_array($tab, ['daily', 'recap'], true)) {
            $tab = 'daily';
        }

        if ($filters['date_start'] === '') {
            $filters['date_start'] = date('Y-m-01');
        }
        if ($filters['date_end'] === '') {
            $filters['date_end'] = date('Y-m-t');
        }

        $perPage = $this->per_page();
        $page = $this->page();
        $dailyTotal = $this->Attendance_model->count_daily($filters);
        $dailyPg = $this->build_pagination($dailyTotal, $perPage, $page);
        $dailyRows = $this->Attendance_model->list_daily($filters, $dailyPg['per_page'], $dailyPg['offset']);

        $recapTotal = $this->Payroll_preview_model->count_monthly_recap($filters);
        $recapPg = $this->build_pagination($recapTotal, $perPage, $page);
        $recapRows = $this->Payroll_preview_model->list_monthly_recap($filters, $recapPg['per_page'], $recapPg['offset']);

        $activePg = ($tab === 'recap') ? $recapPg : $dailyPg;

        $data = [
            'title' => 'Rekap Absensi Harian',
            'active_menu' => 'grp.hr',
            'tab' => $tab,
            'filters' => $filters,
            'rows' => $dailyRows,
            'pg' => $dailyPg,
            'recap_rows' => $recapRows,
            'recap_pg' => $recapPg,
            'active_pg' => $activePg,
            'division_options' => $this->Attendance_model->get_division_options(),
            'status_options' => ['PRESENT', 'LATE', 'ALPHA', 'SICK', 'LEAVE', 'OFF', 'HOLIDAY'],
        ];
        $this->render('attendance/daily', $data);
    }

    public function logs()
    {
        $this->require_permission('attendance.logs.index', 'view');

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'division_id' => (int)$this->input->get('division_id', true),
            'event_type' => strtoupper(trim((string)$this->input->get('event_type', true))),
            'source_type' => strtoupper(trim((string)$this->input->get('source_type', true))),
            'date_start' => trim((string)$this->input->get('date_start', true)),
            'date_end' => trim((string)$this->input->get('date_end', true)),
        ];
        if ($filters['date_start'] === '') {
            $filters['date_start'] = date('Y-m-01');
        }
        if ($filters['date_end'] === '') {
            $filters['date_end'] = date('Y-m-t');
        }

        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->Attendance_model->count_logs($filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->Attendance_model->list_logs($filters, $pg['per_page'], $pg['offset']);

        $data = [
            'title' => 'Log Presensi',
            'active_menu' => 'grp.hr',
            'filters' => $filters,
            'rows' => $rows,
            'pg' => $pg,
            'division_options' => $this->Attendance_model->get_division_options(),
            'event_options' => ['CHECKIN', 'CHECKOUT'],
            'source_options' => ['GPS', 'DEVICE', 'MANUAL'],
        ];
        $this->render('attendance/logs', $data);
    }

    public function schedules()
    {
        $this->require_permission('attendance.schedules.index', 'view');

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'division_id' => (int)$this->input->get('division_id', true),
            'shift_code' => strtoupper(trim((string)$this->input->get('shift_code', true))),
            'date_start' => trim((string)$this->input->get('date_start', true)),
            'date_end' => trim((string)$this->input->get('date_end', true)),
        ];
        if ($filters['date_start'] === '') {
            $filters['date_start'] = date('Y-m-01');
        }
        if ($filters['date_end'] === '') {
            $filters['date_end'] = date('Y-m-t');
        }

        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->Attendance_model->count_schedules($filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->Attendance_model->list_schedules($filters, $pg['per_page'], $pg['offset']);

        $data = [
            'title' => 'Jadwal Shift Pegawai',
            'active_menu' => 'grp.hr',
            'filters' => $filters,
            'rows' => $rows,
            'pg' => $pg,
            'division_options' => $this->Attendance_model->get_division_options(),
            'shift_options' => $this->Attendance_model->get_shift_options(),
            'employee_options' => $this->Attendance_model->get_employee_options(!empty($filters['division_id']) ? (int)$filters['division_id'] : null),
        ];
        $this->render('attendance/schedules', $data);
    }

    public function schedules_v2()
    {
        $this->require_permission('attendance.schedules.index', 'view');

        $month = (int)($this->input->get('month', true) ?: date('m'));
        $year = (int)($this->input->get('year', true) ?: date('Y'));
        if ($month < 1 || $month > 12) {
            $month = (int)date('m');
        }
        if ($year < 2000 || $year > 2100) {
            $year = (int)date('Y');
        }

        $matrix = $this->Attendance_model->schedule_matrix($year, $month);
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));

        $data = [
            'title' => 'Jadwal Shift (Spreadsheet)',
            'active_menu' => 'grp.hr',
            'selected_month' => sprintf('%02d', $month),
            'selected_year' => (string)$year,
            'employees' => $matrix['employees'],
            'schedule_map' => $matrix['schedule_map'],
            'shift_codes' => array_values($this->Attendance_model->get_shift_code_map()),
            'holiday_dates' => $this->Attendance_model->get_holiday_dates_between($start, $end),
        ];
        $this->render('attendance/schedules_v2', $data);
    }

    public function schedule_v2_save()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('attendance.schedules.index', 'edit');

        $payload = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $result = $this->Attendance_model->upsert_schedule_by_shift_code(
            (int)($payload['employee_id'] ?? 0),
            trim((string)($payload['schedule_date'] ?? '')),
            strtoupper(trim((string)($payload['shift_code'] ?? ''))),
            $this->actor_employee_id()
        );
        $statusCode = !empty($result['ok']) ? 200 : 422;

        $this->output->set_status_header($statusCode)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => !empty($result['ok']) ? 1 : 0,
                'message' => (string)($result['message'] ?? 'Gagal menyimpan jadwal.'),
            ]));
    }

    public function schedule_store()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('attendance.schedules.index', 'edit');
        $result = $this->Attendance_model->save_schedule(
            (int)$this->input->post('employee_id', true),
            (int)$this->input->post('shift_id', true),
            trim((string)$this->input->post('schedule_date', true)),
            trim((string)$this->input->post('notes', true)),
            $this->actor_employee_id()
        );
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menyimpan jadwal.'));
        redirect('attendance/schedules');
    }

    public function schedule_update(int $id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('attendance.schedules.index', 'edit');
        $result = $this->Attendance_model->update_schedule(
            $id,
            (int)$this->input->post('shift_id', true),
            trim((string)$this->input->post('schedule_date', true)),
            trim((string)$this->input->post('notes', true))
        );
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal memperbarui jadwal.'));
        redirect('attendance/schedules?' . http_build_query([
            'q' => $this->input->get('q', true),
            'division_id' => $this->input->get('division_id', true),
            'shift_code' => $this->input->get('shift_code', true),
            'date_start' => $this->input->get('date_start', true),
            'date_end' => $this->input->get('date_end', true),
            'per_page' => $this->input->get('per_page', true),
            'page' => $this->input->get('page', true),
        ]));
    }

    public function schedule_delete(int $id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('attendance.schedules.index', 'edit');
        $result = $this->Attendance_model->delete_schedule($id);
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menghapus jadwal.'));
        redirect('attendance/schedules?' . http_build_query([
            'q' => $this->input->get('q', true),
            'division_id' => $this->input->get('division_id', true),
            'shift_code' => $this->input->get('shift_code', true),
            'date_start' => $this->input->get('date_start', true),
            'date_end' => $this->input->get('date_end', true),
            'per_page' => $this->input->get('per_page', true),
            'page' => $this->input->get('page', true),
        ]));
    }

    public function schedule_bulk_store()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('attendance.schedules.index', 'edit');
        $result = $this->Attendance_model->bulk_save_schedule(
            (array)$this->input->post('employee_ids'),
            (int)$this->input->post('shift_id', true),
            trim((string)$this->input->post('date_start', true)),
            trim((string)$this->input->post('date_end', true)),
            trim((string)$this->input->post('notes', true)),
            $this->actor_employee_id()
        );
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal memproses bulk jadwal.'));
        redirect('attendance/schedules');
    }

    public function pending_requests()
    {
        $this->require_permission('attendance.pending.index', 'view');

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'division_id' => (int)$this->input->get('division_id', true),
            'status' => strtoupper(trim((string)$this->input->get('status', true))),
            'request_type' => strtoupper(trim((string)$this->input->get('request_type', true))),
            'date_start' => trim((string)$this->input->get('date_start', true)),
            'date_end' => trim((string)$this->input->get('date_end', true)),
        ];
        if ($filters['date_start'] === '') {
            $filters['date_start'] = date('Y-m-01');
        }
        if ($filters['date_end'] === '') {
            $filters['date_end'] = date('Y-m-t');
        }

        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->Attendance_model->count_pending_requests($filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->Attendance_model->list_pending_requests($filters, $pg['per_page'], $pg['offset']);
        $requestIds = array_map(static function ($row) {
            return (int)($row['id'] ?? 0);
        }, $rows);
        $approvalHistoryMap = $this->Attendance_model->pending_request_approval_history_map($requestIds);

        $data = [
            'title' => 'Pengajuan & Approval Absensi',
            'active_menu' => 'grp.hr',
            'filters' => $filters,
            'rows' => $rows,
            'approval_history_map' => $approvalHistoryMap,
            'pg' => $pg,
            'division_options' => $this->Attendance_model->get_division_options(),
            'status_options' => ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'],
            'request_type_options' => ['MISSING_CHECKIN', 'MISSING_CHECKOUT', 'STATUS_CORRECTION', 'OVERTIME', 'LEAVE', 'SICK'],
        ];
        $this->render('attendance/pending_requests', $data);
    }

    public function overtime_entries()
    {
        $this->require_permission('attendance.overtime_entry.index', 'view');

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'division_id' => (int)$this->input->get('division_id', true),
            'employee_id' => (int)$this->input->get('employee_id', true),
            'status' => strtoupper(trim((string)$this->input->get('status', true))),
            'date_start' => trim((string)$this->input->get('date_start', true)),
            'date_end' => trim((string)$this->input->get('date_end', true)),
        ];
        if ($filters['date_start'] === '') {
            $filters['date_start'] = date('Y-m-01');
        }
        if ($filters['date_end'] === '') {
            $filters['date_end'] = date('Y-m-t');
        }

        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->Attendance_model->count_overtime_entries($filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->Attendance_model->list_overtime_entries($filters, $pg['per_page'], $pg['offset']);

        $editId = (int)$this->input->get('edit_id', true);
        $editRow = $editId > 0 ? $this->Attendance_model->get_overtime_entry_by_id($editId) : null;

        $this->render('attendance/overtime_entries', [
            'title' => 'Input Lembur Manual',
            'active_menu' => 'hr.att-overtime',
            'filters' => $filters,
            'pg' => $pg,
            'rows' => $rows,
            'edit_row' => $editRow,
            'division_options' => $this->Attendance_model->get_division_options(),
            'employee_options' => $this->Attendance_model->get_employee_options($filters['division_id'] > 0 ? (int)$filters['division_id'] : null),
            'overtime_standard_options' => $this->Attendance_model->get_overtime_standard_options(),
            'status_options' => ['PENDING', 'APPROVED', 'REJECTED'],
        ]);
    }

    public function overtime_entry_store()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('attendance.overtime_entry.index', 'create');

        $result = $this->Attendance_model->save_overtime_entry([
            'employee_id' => (int)$this->input->post('employee_id', true),
            'overtime_standard_id' => (int)$this->input->post('overtime_standard_id', true),
            'overtime_date' => trim((string)$this->input->post('overtime_date', true)),
            'start_time' => trim((string)$this->input->post('start_time', true)),
            'end_time' => trim((string)$this->input->post('end_time', true)),
            'overtime_rate' => (float)$this->input->post('overtime_rate', true),
            'status' => strtoupper(trim((string)$this->input->post('status', true))),
            'notes' => trim((string)$this->input->post('notes', true)),
        ], (int)($this->current_user['id'] ?? 0));

        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menyimpan lembur.'));
        redirect('attendance/overtime-entries');
    }

    public function overtime_entry_update(int $id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('attendance.overtime_entry.index', 'edit');

        $result = $this->Attendance_model->save_overtime_entry([
            'id' => $id,
            'employee_id' => (int)$this->input->post('employee_id', true),
            'overtime_standard_id' => (int)$this->input->post('overtime_standard_id', true),
            'overtime_date' => trim((string)$this->input->post('overtime_date', true)),
            'start_time' => trim((string)$this->input->post('start_time', true)),
            'end_time' => trim((string)$this->input->post('end_time', true)),
            'overtime_rate' => (float)$this->input->post('overtime_rate', true),
            'status' => strtoupper(trim((string)$this->input->post('status', true))),
            'notes' => trim((string)$this->input->post('notes', true)),
        ], (int)($this->current_user['id'] ?? 0));

        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal memperbarui lembur.'));
        redirect('attendance/overtime-entries');
    }

    public function overtime_entry_delete(int $id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('attendance.overtime_entry.index', 'delete');
        $result = $this->Attendance_model->delete_overtime_entry($id);
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menghapus lembur.'));
        redirect('attendance/overtime-entries');
    }

    public function pending_request_action(int $id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('attendance.pending.index', 'edit');

        $action = strtoupper(trim((string)$this->input->post('action', true)));
        $notes = trim((string)$this->input->post('notes', true));
        $forceFinal = (int)$this->input->post('force_final', true) === 1;
        $actorEmployeeId = $this->actor_employee_id();

        $result = $this->Attendance_model->process_pending_request_action(
            $id,
            $actorEmployeeId,
            $action,
            $notes,
            $this->is_superadmin(),
            $forceFinal
        );

        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal memproses pengajuan absensi.'));

        redirect('attendance/pending-requests?' . http_build_query([
            'q' => $this->input->get('q', true),
            'division_id' => $this->input->get('division_id', true),
            'status' => $this->input->get('status', true),
            'request_type' => $this->input->get('request_type', true),
            'date_start' => $this->input->get('date_start', true),
            'date_end' => $this->input->get('date_end', true),
            'per_page' => $this->input->get('per_page', true),
            'page' => $this->input->get('page', true),
        ]));
    }

    public function pending_request_bulk_action()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('attendance.pending.index', 'edit');

        $action = strtoupper(trim((string)$this->input->post('bulk_action', true)));
        $ids = (array)$this->input->post('request_ids');
        $notes = trim((string)$this->input->post('bulk_notes', true));
        $forceFinal = (int)$this->input->post('bulk_force_final', true) === 1;
        $actorEmployeeId = $this->actor_employee_id();

        if ($action !== 'APPROVE') {
            $this->session->set_flashdata('error', 'Aksi bulk belum didukung.');
            redirect('attendance/pending-requests');
            return;
        }

        $cleanIds = [];
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $cleanIds[$id] = $id;
            }
        }
        if (empty($cleanIds)) {
            $this->session->set_flashdata('warning', 'Pilih minimal satu pengajuan.');
            redirect('attendance/pending-requests?' . http_build_query([
                'q' => $this->input->get('q', true),
                'division_id' => $this->input->get('division_id', true),
                'status' => $this->input->get('status', true),
                'request_type' => $this->input->get('request_type', true),
                'date_start' => $this->input->get('date_start', true),
                'date_end' => $this->input->get('date_end', true),
                'per_page' => $this->input->get('per_page', true),
                'page' => $this->input->get('page', true),
            ]));
            return;
        }

        $success = 0;
        $failed = 0;
        $failedMessages = [];
        foreach (array_values($cleanIds) as $requestId) {
            $result = $this->Attendance_model->process_pending_request_action(
                (int)$requestId,
                $actorEmployeeId,
                'APPROVE',
                $notes,
                $this->is_superadmin(),
                $forceFinal
            );
            if (!empty($result['ok'])) {
                $success++;
            } else {
                $failed++;
                if (count($failedMessages) < 3) {
                    $failedMessages[] = '#' . (int)$requestId . ': ' . (string)($result['message'] ?? 'Gagal');
                }
            }
        }

        if ($failed === 0) {
            $this->session->set_flashdata('success', 'Bulk approve berhasil untuk ' . $success . ' pengajuan.');
        } else {
            $message = 'Bulk approve selesai. Berhasil: ' . $success . ', gagal: ' . $failed . '.';
            if (!empty($failedMessages)) {
                $message .= ' Detail: ' . implode(' | ', $failedMessages);
            }
            $this->session->set_flashdata($success > 0 ? 'warning' : 'error', $message);
        }

        redirect('attendance/pending-requests?' . http_build_query([
            'q' => $this->input->get('q', true),
            'division_id' => $this->input->get('division_id', true),
            'status' => $this->input->get('status', true),
            'request_type' => $this->input->get('request_type', true),
            'date_start' => $this->input->get('date_start', true),
            'date_end' => $this->input->get('date_end', true),
            'per_page' => $this->input->get('per_page', true),
            'page' => $this->input->get('page', true),
        ]));
    }

    public function anomalies()
    {
        $this->require_permission('attendance.anomalies.index', 'view');

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'division_id' => (int)$this->input->get('division_id', true),
            'issue_type' => strtoupper(trim((string)$this->input->get('issue_type', true))),
            'date_start' => trim((string)$this->input->get('date_start', true)),
            'date_end' => trim((string)$this->input->get('date_end', true)),
        ];
        if ($filters['date_start'] === '') {
            $filters['date_start'] = date('Y-m-01');
        }
        if ($filters['date_end'] === '') {
            $filters['date_end'] = date('Y-m-t');
        }

        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->Attendance_model->count_anomalies($filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->Attendance_model->list_anomalies($filters, $pg['per_page'], $pg['offset']);

        $data = [
            'title' => 'Monitoring Anomali Absensi',
            'active_menu' => 'grp.hr',
            'filters' => $filters,
            'rows' => $rows,
            'pg' => $pg,
            'division_options' => $this->Attendance_model->get_division_options(),
            'issue_options' => [
                'MISSING_CHECKIN',
                'MISSING_CHECKOUT',
                'CHECKOUT_BEFORE_CHECKIN',
                'ZERO_WORK_WITH_CHECKIO',
                'STATUS_MISMATCH_LATE',
            ],
        ];
        $this->render('attendance/anomalies', $data);
    }

    public function master_health()
    {
        $this->require_permission('attendance.master_health.index', 'view');

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'issue_type' => strtoupper(trim((string)$this->input->get('issue_type', true))),
        ];

        $perPage = $this->per_page();
        $page = $this->page();

        $total = $this->Attendance_model->count_master_health_issues($filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->Attendance_model->list_master_health_issues($filters, $pg['per_page'], $pg['offset']);

        $data = [
            'title' => 'Kesehatan Data Master HR',
            'active_menu' => 'grp.hr',
            'filters' => $filters,
            'rows' => $rows,
            'pg' => $pg,
            'summary' => $this->Attendance_model->master_health_summary(),
            'issue_options' => [
                'EMPLOYEE_WITHOUT_USER',
                'USER_WITHOUT_EMPLOYEE',
                'EMPLOYEE_WITHOUT_DIVISION',
                'EMPLOYEE_WITHOUT_POSITION',
                'EMPLOYEE_WITHOUT_MONTH_SCHEDULE',
                'EMPLOYEE_WITHOUT_ACTIVE_CONTRACT',
            ],
        ];
        $this->render('attendance/master_health', $data);
    }

    public function estimate()
    {
        $this->require_permission('attendance.estimate.index', 'view');
        $this->load->model('Payroll_preview_model');

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'division_id' => (int)$this->input->get('division_id', true),
            'date_start' => trim((string)$this->input->get('date_start', true)),
            'date_end' => trim((string)$this->input->get('date_end', true)),
        ];
        if ($filters['date_start'] === '') {
            $filters['date_start'] = date('Y-m-01');
        }
        if ($filters['date_end'] === '') {
            $filters['date_end'] = date('Y-m-t');
        }

        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->Payroll_preview_model->count_monthly_recap($filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->Payroll_preview_model->list_monthly_recap($filters, $pg['per_page'], $pg['offset']);

        $this->render('attendance/estimate', [
            'title' => 'Rekap Gaji Bulanan (Absensi)',
            'active_menu' => 'grp.hr',
            'filters' => $filters,
            'rows' => $rows,
            'pg' => $pg,
            'division_options' => $this->Attendance_model->get_division_options(),
        ]);
    }

    public function estimate_detail(int $employeeId)
    {
        $this->require_permission('attendance.estimate.index', 'view');
        $this->load->model('Payroll_preview_model');

        $dateStart = trim((string)$this->input->get('date_start', true));
        $dateEnd = trim((string)$this->input->get('date_end', true));
        if ($dateStart === '') {
            $dateStart = date('Y-m-01');
        }
        if ($dateEnd === '') {
            $dateEnd = date('Y-m-t');
        }

        [$summary, $dailyRows] = $this->Payroll_preview_model->estimate_employee_attendance_payroll(
            $employeeId,
            $dateStart,
            $dateEnd
        );

        if (!$summary) {
            show_404();
            return;
        }

        $this->render('attendance/estimate_detail', [
            'title' => 'Detail Estimasi Gaji Pegawai',
            'active_menu' => 'grp.hr',
            'summary' => $summary,
            'daily_rows' => $dailyRows,
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'back_filters' => [
                'q' => trim((string)$this->input->get('q', true)),
                'division_id' => (int)$this->input->get('division_id', true),
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
                'per_page' => (int)$this->input->get('per_page', true),
                'page' => (int)$this->input->get('page', true),
            ],
        ]);
    }

    public function meal_calendar()
    {
        $this->require_permission('attendance.estimate.index', 'view');

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'division_id' => (int)$this->input->get('division_id', true),
            'date_start' => trim((string)$this->input->get('date_start', true)),
            'date_end' => trim((string)$this->input->get('date_end', true)),
        ];
        [$filters['date_start'], $filters['date_end']] = $this->normalize_date_range(
            (string)$filters['date_start'],
            (string)$filters['date_end'],
            62
        );

        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->Attendance_model->count_meal_calendar_employees($filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->Attendance_model->list_meal_calendar_employees($filters, $pg['per_page'], $pg['offset']);

        $employeeIds = array_map(static function ($r) {
            return (int)($r['employee_id'] ?? 0);
        }, $rows);
        $dailyMap = $this->Attendance_model->meal_calendar_daily_map($employeeIds, (string)$filters['date_start'], (string)$filters['date_end']);
        $summary = $this->Attendance_model->meal_calendar_summary($filters);

        $days = [];
        $startTs = strtotime((string)$filters['date_start']);
        $endTs = strtotime((string)$filters['date_end']);
        for ($ts = $startTs; $ts <= $endTs; $ts = strtotime('+1 day', $ts)) {
            $days[] = [
                'date' => date('Y-m-d', $ts),
                'day' => date('d', $ts),
                'dow' => date('D', $ts),
            ];
        }

        $this->render('attendance/meal_calendar', [
            'title' => 'Estimasi Uang Makan',
            'active_menu' => 'hr.att-meal-calendar',
            'filters' => $filters,
            'rows' => $rows,
            'days' => $days,
            'daily_map' => $dailyMap,
            'summary' => $summary,
            'pg' => $pg,
            'division_options' => $this->Attendance_model->get_division_options(),
        ]);
    }

    public function ph_assignments()
    {
        $this->require_permission('attendance.ph.assignment.index', 'view');

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'division_id' => (int)$this->input->get('division_id', true),
            'is_eligible' => trim((string)$this->input->get('is_eligible', true)),
        ];
        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->Attendance_model->count_ph_assignments($filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->Attendance_model->list_ph_assignments($filters, $pg['per_page'], $pg['offset']);

        $this->render('attendance/ph_assignments', [
            'title' => 'Assignment PH Pegawai',
            'active_menu' => 'hr.att-ph-assignment',
            'filters' => $filters,
            'rows' => $rows,
            'pg' => $pg,
            'division_options' => $this->Attendance_model->get_division_options(),
            'employee_options' => $this->Attendance_model->get_employee_options(),
        ]);
    }

    public function ph_assignment_save()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('attendance.ph.assignment.index', 'edit');

        $result = $this->Attendance_model->upsert_ph_assignment([
            'employee_id' => (int)$this->input->post('employee_id', true),
            'is_eligible' => $this->input->post('is_eligible') ? 1 : 0,
            'effective_date' => trim((string)$this->input->post('effective_date', true)),
            'expiry_months_override' => trim((string)$this->input->post('expiry_months_override', true)),
            'notes' => trim((string)$this->input->post('notes', true)),
        ], (int)($this->current_user['id'] ?? 0));

        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menyimpan assignment PH.'));
        redirect('attendance/ph-assignments?' . http_build_query([
            'q' => $this->input->get('q', true),
            'division_id' => $this->input->get('division_id', true),
            'is_eligible' => $this->input->get('is_eligible', true),
            'per_page' => $this->input->get('per_page', true),
            'page' => $this->input->get('page', true),
        ]));
    }

    public function ph_assignment_delete(int $id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('attendance.ph.assignment.index', 'delete');

        $result = $this->Attendance_model->delete_ph_assignment($id);
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menghapus assignment PH.'));
        redirect('attendance/ph-assignments?' . http_build_query([
            'q' => $this->input->get('q', true),
            'division_id' => $this->input->get('division_id', true),
            'is_eligible' => $this->input->get('is_eligible', true),
            'per_page' => $this->input->get('per_page', true),
            'page' => $this->input->get('page', true),
        ]));
    }

    public function ph_ledger()
    {
        $this->require_permission('attendance.ph.ledger.index', 'view');

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'employee_id' => (int)$this->input->get('employee_id', true),
            'tx_type' => strtoupper(trim((string)$this->input->get('tx_type', true))),
            'date_start' => trim((string)$this->input->get('date_start', true)),
            'date_end' => trim((string)$this->input->get('date_end', true)),
        ];
        if ($filters['date_start'] === '') {
            $filters['date_start'] = date('Y-m-01');
        }
        if ($filters['date_end'] === '') {
            $filters['date_end'] = date('Y-m-t');
        }

        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->Attendance_model->count_ph_ledger($filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->Attendance_model->list_ph_ledger($filters, $pg['per_page'], $pg['offset']);
        $summary = $this->Attendance_model->ph_ledger_summary($filters);
        $editId = (int)$this->input->get('edit_id', true);
        $editRow = $editId > 0 ? $this->Attendance_model->get_ph_ledger_by_id($editId) : null;

        $this->render('attendance/ph_ledger', [
            'title' => 'Ledger & Log PH',
            'active_menu' => 'hr.att-ph-ledger',
            'filters' => $filters,
            'rows' => $rows,
            'pg' => $pg,
            'summary' => $summary,
            'edit_row' => $editRow,
            'employee_options' => $this->Attendance_model->get_employee_options(),
            'tx_type_options' => ['GRANT', 'USE', 'EXPIRE', 'ADJUST'],
        ]);
    }

    public function ph_ledger_store()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('attendance.ph.ledger.index', 'create');

        $result = $this->Attendance_model->save_ph_ledger_entry([
            'employee_id' => (int)$this->input->post('employee_id', true),
            'tx_date' => trim((string)$this->input->post('tx_date', true)),
            'tx_type' => strtoupper(trim((string)$this->input->post('tx_type', true))),
            'qty_days' => (float)$this->input->post('qty_days', true),
            'notes' => trim((string)$this->input->post('notes', true)),
        ], (int)($this->current_user['id'] ?? 0));

        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menyimpan mutasi PH.'));
        redirect('attendance/ph-ledger');
    }

    public function ph_ledger_update(int $id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('attendance.ph.ledger.index', 'edit');

        $result = $this->Attendance_model->update_ph_ledger_entry(
            $id,
            [
                'employee_id' => (int)$this->input->post('employee_id', true),
                'tx_date' => trim((string)$this->input->post('tx_date', true)),
                'tx_type' => strtoupper(trim((string)$this->input->post('tx_type', true))),
                'qty_days' => (float)$this->input->post('qty_days', true),
                'notes' => trim((string)$this->input->post('notes', true)),
            ],
            (int)($this->current_user['id'] ?? 0),
            $this->is_superadmin()
        );

        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal memperbarui mutasi PH.'));
        redirect('attendance/ph-ledger');
    }

    public function ph_ledger_delete(int $id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('attendance.ph.ledger.index', 'delete');

        $result = $this->Attendance_model->delete_ph_ledger_entry($id, $this->is_superadmin());
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menghapus mutasi PH.'));
        redirect('attendance/ph-ledger');
    }

    public function ph_ledger_sync_grants()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('attendance.ph.ledger.index', 'edit');

        $dateStart = trim((string)$this->input->post('date_start', true));
        $dateEnd = trim((string)$this->input->post('date_end', true));
        if ($dateStart === '') {
            $dateStart = date('Y-m-01');
        }
        if ($dateEnd === '') {
            $dateEnd = date('Y-m-t');
        }

        $result = $this->Attendance_model->sync_ph_grants_from_attendance(
            $dateStart,
            $dateEnd,
            (int)($this->current_user['id'] ?? 0)
        );
        $message = (string)($result['message'] ?? 'Sinkron grant PH selesai.');
        if (!empty($result['ok'])) {
            $message .= ' Scan: ' . (int)($result['total_scanned'] ?? 0)
                . ', insert: ' . (int)($result['inserted'] ?? 0)
                . ', skip: ' . (int)($result['skipped'] ?? 0) . '.';
        }
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', $message);
        redirect('attendance/ph-ledger?' . http_build_query([
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
        ]));
    }

    public function ph_recap()
    {
        $this->require_permission('attendance.ph.recap.index', 'view');

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'division_id' => (int)$this->input->get('division_id', true),
            'is_eligible' => trim((string)$this->input->get('is_eligible', true)),
            'month' => trim((string)$this->input->get('month', true)),
        ];
        if (!preg_match('/^\d{4}-\d{2}$/', (string)$filters['month'])) {
            $filters['month'] = date('Y-m');
        }

        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->Attendance_model->count_ph_recap($filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->Attendance_model->list_ph_recap($filters, $pg['per_page'], $pg['offset']);

        $this->render('attendance/ph_recap', [
            'title' => 'Rekap PH Pegawai',
            'active_menu' => 'hr.att-ph-recap',
            'filters' => $filters,
            'rows' => $rows,
            'pg' => $pg,
            'division_options' => $this->Attendance_model->get_division_options(),
        ]);
    }
}
