<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class My extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('My_portal_model');
        $this->load->model('Attendance_model');
        $this->load->model('Payroll_preview_model');
        $this->load->model('Payroll_model');
    }

    private function selected_employee_id(): int
    {
        $sessionEmployeeId = (int)($this->current_user['employee_id'] ?? 0);
        if ($sessionEmployeeId > 0) {
            return $sessionEmployeeId;
        }

        if ($this->is_superadmin()) {
            return max(0, (int)$this->input->get('employee_id', true));
        }

        return 0;
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

    public function index()
    {
        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;

        $data = [
            'title' => 'Portal Pegawai',
            'active_menu' => 'my.home',
            'employee' => $employee,
            'employee_options' => $this->is_superadmin() ? $this->My_portal_model->get_employee_options() : [],
            'selected_employee_id' => $employeeId,
        ];
        $this->render('my/index', $data);
    }

    public function attendance()
    {
        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;

        $today = date('Y-m-d');
        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
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

        $rows = [];
        $policy = $this->My_portal_model->get_active_policy();
        $todaySchedule = null;
        $todayPresence = ['checkin_count' => 0, 'checkout_count' => 0, 'last_checkin_at' => null, 'last_checkout_at' => null];
        $pg = ['total' => 0, 'per_page' => $this->per_page(), 'page' => 1, 'total_pages' => 1, 'offset' => 0];
        $locationOptions = $this->Attendance_model->get_active_locations();
        $defaultLocationId = $this->Attendance_model->get_default_location_id();

        if ($employee) {
            $this->My_portal_model->ensure_auto_ph_presence((int)$employee['id'], $today, $policy);
            $todaySchedule = $this->My_portal_model->get_schedule_with_shift((int)$employee['id'], $today);
            $todayPresence = $this->My_portal_model->get_today_presence_state((int)$employee['id'], $today);

            $perPage = $this->per_page();
            $page = $this->page();
            $total = $this->My_portal_model->count_my_daily((int)$employee['id'], $filters);
            $pg = $this->build_pagination($total, $perPage, $page);
            $rows = $this->My_portal_model->list_my_daily((int)$employee['id'], $filters, $pg['per_page'], $pg['offset']);
        }

        $data = [
            'title' => 'Absensi Saya',
            'active_menu' => 'my.attendance',
            'employee' => $employee,
            'employee_options' => $this->is_superadmin() ? $this->My_portal_model->get_employee_options() : [],
            'selected_employee_id' => $employeeId,
            'policy' => $policy,
            'today' => $today,
            'today_schedule' => $todaySchedule,
            'today_presence' => $todayPresence,
            'location_options' => $locationOptions,
            'default_location_id' => $defaultLocationId,
            'filters' => $filters,
            'rows' => $rows,
            'pg' => $pg,
            'status_options' => ['PRESENT', 'LATE', 'ALPHA', 'SICK', 'LEAVE', 'OFF', 'HOLIDAY'],
        ];
        $this->render('my/attendance', $data);
    }

    public function attendance_mark()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;
        if (!$employee) {
            $this->session->set_flashdata('error', 'Data pegawai belum terhubung ke akun ini.');
            redirect('my/attendance');
        }

        $eventType = strtoupper(trim((string)$this->input->post('event_type', true)));
        $locationId = (int)$this->input->post('location_id', true);
        $latitude = $this->input->post('latitude', true);
        $longitude = $this->input->post('longitude', true);
        $latVal = ($latitude === '' || $latitude === null) ? null : (float)$latitude;
        $lonVal = ($longitude === '' || $longitude === null) ? null : (float)$longitude;
        $policy = $this->My_portal_model->get_active_policy();
        $result = $this->My_portal_model->mark_attendance((int)$employee['id'], date('Y-m-d'), $eventType, $policy, $locationId, $latVal, $lonVal);
        if (!empty($result['ok'])) {
            $this->Attendance_model->sync_ph_grant_for_employee_date((int)$employee['id'], date('Y-m-d'), (int)($this->current_user['id'] ?? 0));
        }
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal memproses absensi.'));

        $redirectParams = [];
        if ($this->is_superadmin()) {
            $redirectParams['employee_id'] = (int)$employee['id'];
        }
        $suffix = $redirectParams ? ('?' . http_build_query($redirectParams)) : '';
        redirect('my/attendance' . $suffix);
    }

    public function profile()
    {
        $this->render_placeholder('my.profile', 'Profil & Data Diri', 'Data pribadi, kontrak kerja, tanda tangan kontrak, dan dokumen pegawai akan dipusatkan di halaman ini.');
    }

    public function schedule()
    {
        $this->render_placeholder('my.schedule', 'Jadwal Shift Saya', 'Halaman jadwal shift personal akan disatukan dengan kalender shift bulanan per pegawai.');
    }

    public function payroll()
    {
        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;

        $dateStart = trim((string)$this->input->get('date_start', true));
        $dateEnd = trim((string)$this->input->get('date_end', true));
        if ($dateStart === '') {
            $dateStart = date('Y-m-01');
        }
        if ($dateEnd === '') {
            $dateEnd = date('Y-m-t');
        }

        $summary = null;
        $dailyRows = [];
        $generatedRows = [];
        $generatedSummary = ['count' => 0, 'total_transfer' => 0.0];
        if ($employee) {
            [$summary, $dailyRows] = $this->Payroll_preview_model->estimate_employee_attendance_payroll((int)$employee['id'], $dateStart, $dateEnd);
            $generatedRows = $this->Payroll_model->list_generated_salary_lines_by_employee((int)$employee['id'], $dateStart, $dateEnd, 100, 0);
            $generatedSummary['count'] = count($generatedRows);
            foreach ($generatedRows as $gr) {
                $generatedSummary['total_transfer'] += (float)($gr['transfer_amount'] ?? 0);
            }
            $generatedSummary['total_transfer'] = round((float)$generatedSummary['total_transfer'], 2);
        }

        $this->render('my/payroll', [
            'title' => 'Estimasi Gaji Saya',
            'active_menu' => 'my.payroll',
            'employee' => $employee,
            'employee_options' => $this->is_superadmin() ? $this->My_portal_model->get_employee_options() : [],
            'selected_employee_id' => $employeeId,
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'summary' => $summary,
            'daily_rows' => $dailyRows,
            'generated_rows' => $generatedRows,
            'generated_summary' => $generatedSummary,
        ]);
    }

    public function payroll_slip(int $lineId)
    {
        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;
        if (!$employee) {
            show_404();
            return;
        }
        $line = $this->Payroll_model->get_salary_disbursement_line_slip($lineId, (int)$employee['id']);
        if (!$line) {
            show_404();
            return;
        }
        $this->load->view('payroll/salary_slip', [
            'line' => $line,
            'context' => 'my',
        ]);
    }

    public function leave_requests()
    {
        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;
        if (!$employee) {
            $this->session->set_flashdata('error', 'Data pegawai belum terhubung ke akun ini.');
            redirect('my');
            return;
        }

        if ($this->input->method() === 'post') {
            $result = $this->My_portal_model->create_leave_request((int)$employee['id'], [
                'request_date' => trim((string)$this->input->post('request_date', true)),
                'request_type' => strtoupper(trim((string)$this->input->post('request_type', true))),
                'requested_checkin_at' => trim((string)$this->input->post('requested_checkin_at', true)),
                'requested_checkout_at' => trim((string)$this->input->post('requested_checkout_at', true)),
                'requested_status' => strtoupper(trim((string)$this->input->post('requested_status', true))),
                'reason' => trim((string)$this->input->post('reason', true)),
            ]);
            $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menyimpan pengajuan.'));
            $suffix = $this->is_superadmin() ? ('?employee_id=' . (int)$employee['id']) : '';
            redirect('my/leave-requests' . $suffix);
            return;
        }

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
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
        $total = $this->My_portal_model->count_my_leave_requests((int)$employee['id'], $filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->My_portal_model->list_my_leave_requests((int)$employee['id'], $filters, $pg['per_page'], $pg['offset']);

        $this->render('my/leave_requests', [
            'title' => 'Pengajuan Absensi Saya',
            'active_menu' => 'my.leave',
            'employee' => $employee,
            'employee_options' => $this->is_superadmin() ? $this->My_portal_model->get_employee_options() : [],
            'selected_employee_id' => $employeeId,
            'filters' => $filters,
            'rows' => $rows,
            'pg' => $pg,
            'status_options' => ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'],
            'request_type_options' => ['LEAVE', 'SICK', 'MISSING_CHECKIN', 'MISSING_CHECKOUT', 'STATUS_CORRECTION'],
            'status_correction_options' => ['PRESENT', 'LATE', 'ALPHA', 'SICK', 'LEAVE', 'OFF', 'HOLIDAY'],
        ]);
    }

    public function leave_request_cancel(int $id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;
        if (!$employee) {
            $this->session->set_flashdata('error', 'Data pegawai tidak valid.');
            redirect('my/leave-requests');
            return;
        }

        $note = trim((string)$this->input->post('notes', true));
        $result = $this->Attendance_model->process_pending_request_action(
            $id,
            (int)$employee['id'],
            'CANCEL',
            $note,
            $this->is_superadmin()
        );
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal membatalkan pengajuan.'));

        $suffix = $this->is_superadmin() ? ('?employee_id=' . (int)$employee['id']) : '';
        redirect('my/leave-requests' . $suffix);
    }

    public function leave_request_schedule()
    {
        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;
        if (!$employee) {
            $this->output->set_status_header(404)
                ->set_content_type('application/json')
                ->set_output(json_encode(['ok' => 0, 'message' => 'Pegawai tidak ditemukan.']));
            return;
        }

        $date = trim((string)$this->input->get('date', true));
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->output->set_status_header(422)
                ->set_content_type('application/json')
                ->set_output(json_encode(['ok' => 0, 'message' => 'Tanggal tidak valid.']));
            return;
        }

        $schedule = $this->My_portal_model->get_schedule_with_shift((int)$employee['id'], $date);
        if (!$schedule) {
            $this->output->set_content_type('application/json')
                ->set_output(json_encode([
                    'ok' => 1,
                    'has_schedule' => 0,
                    'message' => 'Tidak ada jadwal shift pada tanggal ini.',
                ]));
            return;
        }

        $this->output->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => 1,
                'has_schedule' => 1,
                'shift_code' => (string)($schedule['shift_code'] ?? ''),
                'shift_name' => (string)($schedule['shift_name'] ?? ''),
                'start_time' => (string)($schedule['start_time'] ?? ''),
                'end_time' => (string)($schedule['end_time'] ?? ''),
                'is_overnight' => (int)($schedule['is_overnight'] ?? 0),
            ]));
    }

    public function meal_ledger()
    {
        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;
        if (!$employee) {
            $this->session->set_flashdata('error', 'Data pegawai belum terhubung ke akun ini.');
            redirect('my');
            return;
        }

        $filters = [
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
        $total = $this->My_portal_model->count_my_meal_ledger((int)$employee['id'], $filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->My_portal_model->list_my_meal_ledger((int)$employee['id'], $filters, $pg['per_page'], $pg['offset']);
        $summary = $this->My_portal_model->my_meal_ledger_summary((int)$employee['id'], $filters);

        $this->render('my/meal_ledger', [
            'title' => 'Uang Makan Saya',
            'active_menu' => 'my.meal',
            'employee' => $employee,
            'employee_options' => $this->is_superadmin() ? $this->My_portal_model->get_employee_options() : [],
            'selected_employee_id' => $employeeId,
            'filters' => $filters,
            'rows' => $rows,
            'pg' => $pg,
            'summary' => $summary,
            'status_options' => ['UNPAID', 'PENDING', 'PAID', 'FAILED', 'VOID'],
        ]);
    }

    public function overtime()
    {
        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;
        if (!$employee) {
            $this->session->set_flashdata('error', 'Data pegawai belum terhubung ke akun ini.');
            redirect('my');
            return;
        }

        $filters = [
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
        $total = $this->My_portal_model->count_my_overtime_entries((int)$employee['id'], $filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->My_portal_model->list_my_overtime_entries((int)$employee['id'], $filters, $pg['per_page'], $pg['offset']);

        $this->render('my/overtime', [
            'title' => 'Lembur Saya',
            'active_menu' => 'my.overtime',
            'employee' => $employee,
            'employee_options' => $this->is_superadmin() ? $this->My_portal_model->get_employee_options() : [],
            'selected_employee_id' => $employeeId,
            'filters' => $filters,
            'rows' => $rows,
            'pg' => $pg,
            'status_options' => ['PENDING', 'APPROVED', 'REJECTED'],
        ]);
    }

    public function ph_ledger()
    {
        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;
        if (!$employee) {
            $this->session->set_flashdata('error', 'Data pegawai belum terhubung ke akun ini.');
            redirect('my');
            return;
        }

        $filters = [
            'tx_type' => strtoupper(trim((string)$this->input->get('tx_type', true))),
            'expired_state' => strtoupper(trim((string)$this->input->get('expired_state', true))),
            'date_start' => trim((string)$this->input->get('date_start', true)),
            'date_end' => trim((string)$this->input->get('date_end', true)),
        ];
        if (!in_array($filters['expired_state'], ['ALL', 'ACTIVE', 'EXPIRED'], true)) {
            $filters['expired_state'] = 'ALL';
        }
        if ($filters['date_start'] === '') {
            $filters['date_start'] = date('Y-m-01', strtotime('-3 month'));
        }
        if ($filters['date_end'] === '') {
            $filters['date_end'] = date('Y-m-t');
        }
        $this->Attendance_model->sync_ph_expiry_ledger(date('Y-m-d'), (int)($this->current_user['id'] ?? 0));

        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->My_portal_model->count_my_ph_ledger((int)$employee['id'], $filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->My_portal_model->list_my_ph_ledger((int)$employee['id'], $filters, $pg['per_page'], $pg['offset']);
        $summary = $this->My_portal_model->my_ph_balance_summary((int)$employee['id']);

        $this->render('my/ph_ledger', [
            'title' => 'PH Saya',
            'active_menu' => 'my.ph',
            'employee' => $employee,
            'employee_options' => $this->is_superadmin() ? $this->My_portal_model->get_employee_options() : [],
            'selected_employee_id' => $employeeId,
            'filters' => $filters,
            'rows' => $rows,
            'pg' => $pg,
            'summary' => $summary,
            'tx_type_options' => ['GRANT', 'USE', 'EXPIRE', 'ADJUST'],
        ]);
    }

    public function manual_adjustments()
    {
        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;
        if (!$employee) {
            $this->session->set_flashdata('error', 'Data pegawai belum terhubung ke akun ini.');
            redirect('my');
            return;
        }

        $filters = [
            'status' => strtoupper(trim((string)$this->input->get('status', true))),
            'adjustment_kind' => strtoupper(trim((string)$this->input->get('adjustment_kind', true))),
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
        $total = $this->My_portal_model->count_my_manual_adjustments((int)$employee['id'], $filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->My_portal_model->list_my_manual_adjustments((int)$employee['id'], $filters, $pg['per_page'], $pg['offset']);

        $this->render('my/manual_adjustments', [
            'title' => 'Tambahan / Pengurangan Saya',
            'active_menu' => 'my.adjustment',
            'employee' => $employee,
            'employee_options' => $this->is_superadmin() ? $this->My_portal_model->get_employee_options() : [],
            'selected_employee_id' => $employeeId,
            'filters' => $filters,
            'rows' => $rows,
            'pg' => $pg,
            'status_options' => ['PENDING', 'APPROVED', 'REJECTED'],
            'kind_options' => ['ADDITION', 'DEDUCTION'],
        ]);
    }

    public function cash_advance()
    {
        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;
        if (!$employee) {
            $this->session->set_flashdata('error', 'Data pegawai belum terhubung ke akun ini.');
            redirect('my');
            return;
        }

        $filters = [
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
        $total = $this->My_portal_model->count_my_cash_advances((int)$employee['id'], $filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->My_portal_model->list_my_cash_advances((int)$employee['id'], $filters, $pg['per_page'], $pg['offset']);
        foreach ($rows as &$row) {
            $row['installments'] = $this->My_portal_model->list_cash_advance_installments((int)($row['id'] ?? 0));
        }
        unset($row);

        $this->render('my/cash_advance', [
            'title' => 'Kasbon Saya',
            'active_menu' => 'my.cash_advance',
            'employee' => $employee,
            'employee_options' => $this->is_superadmin() ? $this->My_portal_model->get_employee_options() : [],
            'selected_employee_id' => $employeeId,
            'filters' => $filters,
            'rows' => $rows,
            'pg' => $pg,
            'status_options' => ['DRAFT', 'APPROVED', 'REJECTED', 'SETTLED', 'VOID'],
        ]);
    }

    private function render_placeholder(string $activeMenu, string $title, string $message): void
    {
        $employeeId = $this->selected_employee_id();
        $employee = $employeeId > 0 ? $this->My_portal_model->get_employee_by_id($employeeId) : null;

        $this->render('my/placeholder', [
            'title' => $title,
            'active_menu' => $activeMenu,
            'employee' => $employee,
            'employee_options' => $this->is_superadmin() ? $this->My_portal_model->get_employee_options() : [],
            'selected_employee_id' => $employeeId,
            'message' => $message,
        ]);
    }
}
