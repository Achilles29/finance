<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Payroll extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Payroll_preview_model');
        $this->load->model('Payroll_model');
    }

    private function per_page(string $param = 'per_page'): int
    {
        $pp = (int)$this->input->get($param, true);
        return in_array($pp, [10, 25, 50, 100], true) ? $pp : 25;
    }

    private function page(string $param = 'page'): int
    {
        return max(1, (int)$this->input->get($param, true));
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

    private function actor_employee_id(): int
    {
        return (int)($this->current_user['employee_id'] ?? 0);
    }

    public function preview_thp()
    {
        $this->require_permission('payroll.preview_thp.index', 'view');

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'division_id' => (int)$this->input->get('division_id', true),
            'position_id' => (int)$this->input->get('position_id', true),
            'as_of' => trim((string)$this->input->get('as_of', true)),
        ];
        if ($filters['as_of'] === '') {
            $filters['as_of'] = date('Y-m-d');
        }

        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->Payroll_preview_model->count_employees($filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->Payroll_preview_model->list_preview_rows($filters, $pg['per_page'], $pg['offset']);

        $this->render('payroll/preview_thp', [
            'title' => 'Preview THP',
            'active_menu' => 'pay.preview-thp',
            'filters' => $filters,
            'pg' => $pg,
            'rows' => $rows,
            'division_options' => $this->Payroll_preview_model->get_division_options(),
            'position_options' => $this->Payroll_preview_model->get_position_options(),
        ]);
    }

    public function manual_adjustments()
    {
        $this->require_permission('payroll.manual_adjustment.index', 'view');

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'division_id' => (int)$this->input->get('division_id', true),
            'employee_id' => (int)$this->input->get('employee_id', true),
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
        $total = $this->Payroll_model->count_manual_adjustments($filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->Payroll_model->list_manual_adjustments($filters, $pg['per_page'], $pg['offset']);

        $editId = (int)$this->input->get('edit_id', true);
        $editRow = $editId > 0 ? $this->Payroll_model->get_manual_adjustment_by_id($editId) : null;

        $this->render('payroll/manual_adjustments', [
            'title' => 'Penyesuaian Gaji Manual',
            'active_menu' => 'pay.manual-adjustment',
            'filters' => $filters,
            'pg' => $pg,
            'rows' => $rows,
            'edit_row' => $editRow,
            'division_options' => $this->Payroll_model->get_division_options(),
            'employee_options' => $this->Payroll_model->get_employee_options($filters['division_id'] > 0 ? (int)$filters['division_id'] : null),
            'status_options' => ['PENDING', 'APPROVED', 'REJECTED'],
            'kind_options' => ['ADDITION', 'DEDUCTION'],
        ]);
    }

    public function manual_adjustment_store()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('payroll.manual_adjustment.index', 'create');

        $result = $this->Payroll_model->save_manual_adjustment([
            'employee_id' => (int)$this->input->post('employee_id', true),
            'adjustment_date' => trim((string)$this->input->post('adjustment_date', true)),
            'adjustment_kind' => strtoupper(trim((string)$this->input->post('adjustment_kind', true))),
            'adjustment_name' => trim((string)$this->input->post('adjustment_name', true)),
            'amount' => (float)$this->input->post('amount', true),
            'status' => strtoupper(trim((string)$this->input->post('status', true))),
            'notes' => trim((string)$this->input->post('notes', true)),
        ], (int)($this->current_user['id'] ?? 0));

        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menyimpan penyesuaian.'));
        redirect('payroll/manual-adjustments');
    }

    public function manual_adjustment_update(int $id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('payroll.manual_adjustment.index', 'edit');

        $result = $this->Payroll_model->save_manual_adjustment([
            'id' => $id,
            'employee_id' => (int)$this->input->post('employee_id', true),
            'adjustment_date' => trim((string)$this->input->post('adjustment_date', true)),
            'adjustment_kind' => strtoupper(trim((string)$this->input->post('adjustment_kind', true))),
            'adjustment_name' => trim((string)$this->input->post('adjustment_name', true)),
            'amount' => (float)$this->input->post('amount', true),
            'status' => strtoupper(trim((string)$this->input->post('status', true))),
            'notes' => trim((string)$this->input->post('notes', true)),
        ], (int)($this->current_user['id'] ?? 0));

        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal memperbarui penyesuaian.'));
        redirect('payroll/manual-adjustments');
    }

    public function manual_adjustment_delete(int $id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('payroll.manual_adjustment.index', 'delete');
        $result = $this->Payroll_model->delete_manual_adjustment($id);
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menghapus penyesuaian.'));
        redirect('payroll/manual-adjustments');
    }

    public function meal_disbursements()
    {
        $this->require_permission('payroll.meal_disbursement.index', 'view');

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

        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->Payroll_model->count_meal_disbursements($filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->Payroll_model->list_meal_disbursements($filters, $pg['per_page'], $pg['offset']);

        $detailId = (int)$this->input->get('detail_id', true);
        $detailEmployeeId = (int)$this->input->get('detail_employee_id', true);
        $detailHeader = $detailId > 0 ? $this->Payroll_model->get_meal_disbursement_by_id($detailId) : null;
        $detailLines = $detailId > 0 ? $this->Payroll_model->list_meal_disbursement_lines($detailId) : [];
        $detailEmployeeRows = $detailId > 0 ? $this->Payroll_model->list_meal_disbursement_employee_summary($detailId) : [];
        $detailEmployeeDailyRows = ($detailId > 0 && $detailEmployeeId > 0) ? $this->Payroll_model->list_meal_disbursement_employee_daily($detailId, $detailEmployeeId) : [];

        $this->render('payroll/meal_disbursements', [
            'title' => 'Pencairan Uang Makan',
            'active_menu' => 'pay.meal-disbursement',
            'filters' => $filters,
            'rows' => $rows,
            'pg' => $pg,
            'detail_header' => $detailHeader,
            'detail_lines' => $detailLines,
            'detail_employee_rows' => $detailEmployeeRows,
            'detail_employee_daily_rows' => $detailEmployeeDailyRows,
            'detail_employee_id' => $detailEmployeeId,
            'status_options' => ['DRAFT', 'POSTED', 'PAID', 'VOID'],
            'company_account_options' => $this->Payroll_model->get_company_account_options(),
        ]);
    }

    public function meal_disbursement_generate()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('payroll.meal_disbursement.index', 'create');

        $result = $this->Payroll_model->generate_meal_disbursement([
            'period_start' => trim((string)$this->input->post('period_start', true)),
            'period_end' => trim((string)$this->input->post('period_end', true)),
            'disbursement_date' => trim((string)$this->input->post('disbursement_date', true)),
            'company_account_id' => (int)$this->input->post('company_account_id', true),
            'notes' => trim((string)$this->input->post('notes', true)),
        ], (int)($this->current_user['id'] ?? 0));

        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal generate batch uang makan.'));
        if (!empty($result['ok']) && !empty($result['disbursement_id'])) {
            redirect('payroll/meal-disbursements?detail_id=' . (int)$result['disbursement_id']);
            return;
        }
        redirect('payroll/meal-disbursements');
    }

    public function meal_disbursement_mark_paid(int $id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('payroll.meal_disbursement.index', 'edit');
        $result = $this->Payroll_model->post_meal_disbursement_paid(
            $id,
            trim((string)$this->input->post('transfer_ref_no', true)),
            (int)($this->current_user['id'] ?? 0)
        );
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal update status bayar uang makan.'));
        redirect('payroll/meal-disbursements?detail_id=' . $id);
    }

    public function meal_disbursement_void(int $id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('payroll.meal_disbursement.index', 'delete');
        $result = $this->Payroll_model->void_meal_disbursement(
            $id,
            trim((string)$this->input->post('notes', true)),
            (int)($this->current_user['id'] ?? 0)
        );
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal VOID batch uang makan.'));
        redirect('payroll/meal-disbursements');
    }

    public function salary_disbursements()
    {
        $this->require_permission('payroll.salary_disbursement.index', 'view');

        $filters = [
            'status' => strtoupper(trim((string)$this->input->get('status', true))),
            'payroll_period_id' => (int)$this->input->get('payroll_period_id', true),
            'q' => trim((string)$this->input->get('q', true)),
        ];

        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->Payroll_model->count_salary_disbursements($filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->Payroll_model->list_salary_disbursements($filters, $pg['per_page'], $pg['offset']);

        $detailId = (int)$this->input->get('detail_id', true);
        $detailHeader = $detailId > 0 ? $this->Payroll_model->get_salary_disbursement_by_id($detailId) : null;
        $detailLines = $detailId > 0 ? $this->Payroll_model->list_salary_disbursement_lines($detailId) : [];
        $detailLineBreakdown = $detailId > 0 ? $this->Payroll_model->list_salary_disbursement_line_breakdown($detailId) : [];

        $gen = [
            'payroll_period_id' => (int)$this->input->get('gen_payroll_period_id', true),
            'disbursement_date' => trim((string)$this->input->get('gen_disbursement_date', true)),
            'notes' => trim((string)$this->input->get('gen_notes', true)),
        ];
        if ($gen['disbursement_date'] === '') {
            $gen['disbursement_date'] = date('Y-m-d');
        }
        $previewRows = [];
        if ($gen['payroll_period_id'] > 0) {
            $previewRows = $this->Payroll_model->preview_salary_disbursement_candidates($gen['payroll_period_id']);
        }

        $this->render('payroll/salary_disbursements', [
            'title' => 'Pencairan Gaji',
            'active_menu' => 'pay.salary-disbursement',
            'filters' => $filters,
            'rows' => $rows,
            'pg' => $pg,
            'detail_header' => $detailHeader,
            'detail_lines' => $detailLines,
            'status_options' => ['DRAFT', 'POSTED', 'PAID', 'VOID'],
            'payroll_period_options' => $this->Payroll_model->get_payroll_period_options(),
            'company_account_options' => $this->Payroll_model->get_company_account_options(),
            'detail_line_breakdown' => $detailLineBreakdown,
            'gen' => $gen,
            'preview_rows' => $previewRows,
        ]);
    }

    public function payroll_periods()
    {
        $this->require_permission('payroll.salary_disbursement.index', 'view');

        $periodFilters = [
            'status' => strtoupper(trim((string)$this->input->get('period_status', true))),
            'q' => trim((string)$this->input->get('period_q', true)),
        ];

        $periodPerPage = 10;
        $periodPage = max(1, (int)$this->input->get('period_page', true));
        $periodTotal = $this->Payroll_model->count_payroll_periods($periodFilters);
        $periodPg = $this->build_pagination($periodTotal, $periodPerPage, $periodPage);
        $periodRows = $this->Payroll_model->list_payroll_periods($periodFilters, $periodPg['per_page'], $periodPg['offset']);

        $periodDetailId = (int)$this->input->get('period_detail_id', true);
        $periodResultRows = $periodDetailId > 0 ? $this->Payroll_model->list_payroll_results_by_period($periodDetailId) : [];
        $periodBreakdownRows = $periodDetailId > 0 ? $this->Payroll_model->list_payroll_result_breakdown_by_period($periodDetailId) : [];
        $periodAudit = $periodDetailId > 0 ? $this->Payroll_model->audit_payroll_period_consistency($periodDetailId) : null;

        $this->render('payroll/payroll_periods', [
            'title' => 'Generate Payroll Period',
            'active_menu' => 'pay.salary-disbursement',
            'period_filters' => $periodFilters,
            'period_rows' => $periodRows,
            'period_pg' => $periodPg,
            'period_detail_rows' => $periodResultRows,
            'period_breakdown_rows' => $periodBreakdownRows,
            'period_audit' => $periodAudit,
            'period_detail_id' => $periodDetailId,
            'period_status_options' => ['DRAFT', 'CALCULATED', 'FINALIZED', 'PAID', 'CLOSED'],
        ]);
    }

    public function payroll_period_void(int $id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('payroll.salary_disbursement.index', 'delete');
        $result = $this->Payroll_model->reset_payroll_period($id, trim((string)$this->input->post('notes', true)));
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal reset payroll period.'));
        redirect('payroll/payroll-periods');
    }

    public function payroll_period_delete(int $id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('payroll.salary_disbursement.index', 'delete');
        $result = $this->Payroll_model->delete_payroll_period($id);
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal hapus payroll period.'));
        redirect('payroll/payroll-periods');
    }

    public function payroll_period_generate()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('payroll.salary_disbursement.index', 'create');

        $result = $this->Payroll_model->generate_payroll_period_results([
            'period_code' => trim((string)$this->input->post('period_code', true)),
            'period_start' => trim((string)$this->input->post('period_start', true)),
            'period_end' => trim((string)$this->input->post('period_end', true)),
            'rounding_mode' => strtoupper(trim((string)$this->input->post('rounding_mode', true))),
            'notes' => trim((string)$this->input->post('notes', true)),
        ], $this->actor_employee_id());

        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal generate payroll period.'));
        if (!empty($result['ok']) && !empty($result['payroll_period_id'])) {
            redirect('payroll/payroll-periods?period_detail_id=' . (int)$result['payroll_period_id']);
            return;
        }
        redirect('payroll/payroll-periods');
    }

    public function salary_disbursement_generate()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('payroll.salary_disbursement.index', 'create');

        $sourceMapRaw = (array)$this->input->post('employee_source_account');
        $sourceMap = [];
        if ((int)$this->input->post('ignore_preview_map', true) !== 1) {
            foreach ($sourceMapRaw as $employeeId => $accountId) {
                $eid = (int)$employeeId;
                if ($eid <= 0) {
                    continue;
                }
                $sourceMap[$eid] = (int)$accountId;
            }
        }

        $result = $this->Payroll_model->generate_salary_disbursement([
            'payroll_period_id' => (int)$this->input->post('payroll_period_id', true),
            'disbursement_date' => trim((string)$this->input->post('disbursement_date', true)),
            'company_account_id' => (int)$this->input->post('company_account_id', true),
            'employee_source_account' => $sourceMap,
            'notes' => trim((string)$this->input->post('notes', true)),
        ], $this->actor_employee_id());

        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal generate pencairan gaji.'));
        if (!empty($result['ok']) && !empty($result['disbursement_id'])) {
            redirect('payroll/salary-disbursements?detail_id=' . (int)$result['disbursement_id']);
            return;
        }
        redirect('payroll/salary-disbursements');
    }

    public function salary_disbursement_mark_paid(int $id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('payroll.salary_disbursement.index', 'edit');
        $result = $this->Payroll_model->post_salary_disbursement_paid(
            $id,
            trim((string)$this->input->post('transfer_ref_no', true)),
            (int)($this->current_user['id'] ?? 0)
        );
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal update status bayar gaji.'));
        redirect('payroll/salary-disbursements?detail_id=' . $id);
    }

    public function salary_disbursement_slip(int $lineId)
    {
        $this->require_permission('payroll.salary_disbursement.index', 'view');
        $line = $this->Payroll_model->get_salary_disbursement_line_slip($lineId, 0);
        if (!$line) {
            show_404();
            return;
        }
        $this->load->view('payroll/salary_slip', [
            'line' => $line,
            'context' => 'admin',
        ]);
    }

    public function salary_disbursement_void(int $id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('payroll.salary_disbursement.index', 'delete');
        $result = $this->Payroll_model->void_salary_disbursement(
            $id,
            trim((string)$this->input->post('notes', true)),
            (int)($this->current_user['id'] ?? 0)
        );
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal VOID batch gaji.'));
        redirect('payroll/salary-disbursements');
    }

    public function salary_disbursement_delete(int $id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('payroll.salary_disbursement.index', 'delete');
        $result = $this->Payroll_model->delete_salary_disbursement($id);
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menghapus batch gaji.'));
        redirect('payroll/salary-disbursements');
    }

    public function cash_advances()
    {
        $this->require_permission('payroll.cash_advance.index', 'view');
        $tab = strtolower(trim((string)$this->input->get('tab', true)));
        if (!in_array($tab, ['transaction', 'recap'], true)) {
            $tab = 'transaction';
        }
        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'employee_id' => (int)$this->input->get('employee_id', true),
            'status' => strtoupper(trim((string)$this->input->get('status', true))),
            'date_start' => trim((string)$this->input->get('date_start', true)),
            'date_end' => trim((string)$this->input->get('date_end', true)),
        ];
        if ($tab === 'transaction' && $filters['date_start'] === '') {
            $filters['date_start'] = date('Y-m-01');
        }
        if ($tab === 'transaction' && $filters['date_end'] === '') {
            $filters['date_end'] = date('Y-m-t');
        }

        $perPage = $this->per_page();
        $page = $this->page();
        $rows = [];
        $recapRows = [];
        $recapEmployeeHistory = [];
        $recapEmployeeId = (int)$this->input->get('recap_employee_id', true);
        if ($tab === 'recap') {
            $total = $this->Payroll_model->count_cash_advance_employee_recap($filters);
            $pg = $this->build_pagination($total, $perPage, $page);
            $recapRows = $this->Payroll_model->list_cash_advance_employee_recap($filters, $pg['per_page'], $pg['offset']);
            if ($recapEmployeeId > 0) {
                $recapEmployeeHistory = $this->Payroll_model->list_cash_advance_employee_history($recapEmployeeId);
            }
        } else {
            $total = $this->Payroll_model->count_cash_advances($filters);
            $pg = $this->build_pagination($total, $perPage, $page);
            $rows = $this->Payroll_model->list_cash_advances($filters, $pg['per_page'], $pg['offset']);
            foreach ($rows as &$row) {
                $row['installments'] = $this->Payroll_model->list_cash_advance_installments((int)($row['id'] ?? 0));
            }
            unset($row);
        }

        $editId = (int)$this->input->get('edit_id', true);
        $editRow = $editId > 0 ? $this->Payroll_model->get_cash_advance_by_id($editId) : null;

        $this->render('payroll/cash_advances', [
            'title' => 'Kasbon Pegawai',
            'active_menu' => 'pay.cash-advance',
            'tab' => $tab,
            'filters' => $filters,
            'rows' => $rows,
            'recap_rows' => $recapRows,
            'recap_employee_id' => $recapEmployeeId,
            'recap_employee_history' => $recapEmployeeHistory,
            'pg' => $pg,
            'edit_row' => $editRow,
            'status_options' => ['DRAFT', 'APPROVED', 'REJECTED', 'SETTLED', 'VOID'],
            'employee_options' => $this->Payroll_model->get_employee_options(),
            'company_account_options' => $this->Payroll_model->get_company_account_options(),
        ]);
    }

    public function cash_advance_store()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('payroll.cash_advance.index', 'create');
        $result = $this->Payroll_model->save_cash_advance([
            'employee_id' => (int)$this->input->post('employee_id', true),
            'request_date' => trim((string)$this->input->post('request_date', true)),
            'approved_date' => trim((string)$this->input->post('approved_date', true)),
            'amount' => (float)$this->input->post('amount', true),
            'tenor_month' => (int)$this->input->post('tenor_month', true),
            'company_account_id' => (int)$this->input->post('company_account_id', true),
            'status' => strtoupper(trim((string)$this->input->post('status', true))),
            'notes' => trim((string)$this->input->post('notes', true)),
        ], (int)($this->current_user['id'] ?? 0));
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal simpan kasbon.'));
        redirect('payroll/cash-advances');
    }

    public function cash_advance_update(int $id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('payroll.cash_advance.index', 'edit');
        $result = $this->Payroll_model->save_cash_advance([
            'id' => $id,
            'employee_id' => (int)$this->input->post('employee_id', true),
            'request_date' => trim((string)$this->input->post('request_date', true)),
            'approved_date' => trim((string)$this->input->post('approved_date', true)),
            'amount' => (float)$this->input->post('amount', true),
            'tenor_month' => (int)$this->input->post('tenor_month', true),
            'company_account_id' => (int)$this->input->post('company_account_id', true),
            'status' => strtoupper(trim((string)$this->input->post('status', true))),
            'notes' => trim((string)$this->input->post('notes', true)),
        ], (int)($this->current_user['id'] ?? 0));
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal update kasbon.'));
        redirect('payroll/cash-advances');
    }

    public function cash_advance_pay_installment(int $id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('payroll.cash_advance.index', 'edit');
        $result = $this->Payroll_model->pay_cash_advance_installment(
            $id,
            (int)$this->input->post('installment_no', true),
            (float)$this->input->post('paid_amount', true),
            trim((string)$this->input->post('payment_method', true)),
            (int)$this->input->post('company_account_id', true),
            trim((string)$this->input->post('payment_date', true)),
            trim((string)$this->input->post('salary_cut_period_start', true)),
            trim((string)$this->input->post('salary_cut_period_end', true)),
            trim((string)$this->input->post('transfer_ref_no', true)),
            trim((string)$this->input->post('notes', true)),
            (int)($this->current_user['id'] ?? 0)
        );
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal simpan pembayaran cicilan.'));
        redirect('payroll/cash-advances');
    }

    public function cash_advance_void(int $id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('payroll.cash_advance.index', 'delete');
        $result = $this->Payroll_model->void_cash_advance($id, trim((string)$this->input->post('notes', true)));
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal VOID kasbon.'));
        redirect('payroll/cash-advances');
    }

    public function cash_advance_delete(int $id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }
        $this->require_permission('payroll.cash_advance.index', 'delete');
        $result = $this->Payroll_model->delete_cash_advance($id);
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal hapus kasbon.'));
        redirect('payroll/cash-advances');
    }

    public function bonus()
    {
        $this->require_permission('payroll.bonus.index', 'view');

        $month = trim((string)$this->input->get('month', true));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $tab = strtolower(trim((string)$this->input->get('tab', true)));
        if (!in_array($tab, ['overview', 'rules', 'weights', 'penalties', 'peer', 'service', 'monthly', 'guide'], true)) {
            $tab = 'overview';
        }

        $poolFilters = ['q' => trim((string)$this->input->get('pool_q', true))];
        $ruleFilters = ['q' => trim((string)$this->input->get('rule_q', true))];
        $weightFilters = ['q' => trim((string)$this->input->get('weight_q', true))];
        $penaltyTypeFilters = ['q' => trim((string)$this->input->get('penalty_type_q', true))];
        $penaltyEventFilters = ['q' => trim((string)$this->input->get('penalty_event_q', true))];
        $peerFilters = ['q' => trim((string)$this->input->get('peer_q', true))];
        $serviceFilters = ['q' => trim((string)$this->input->get('service_q', true))];
        $monthlyFilters = ['q' => trim((string)$this->input->get('monthly_q', true))];

        $poolPg = $this->build_pagination(
            $this->Payroll_model->count_bonus_recent_pools($month, $poolFilters['q']),
            $this->per_page('pool_per_page'),
            $this->page('pool_page')
        );
        $rulePg = $this->build_pagination(
            $this->Payroll_model->count_bonus_rules($ruleFilters['q']),
            $this->per_page('rule_per_page'),
            $this->page('rule_page')
        );
        $weightPg = $this->build_pagination(
            $this->Payroll_model->count_bonus_weight_rules($weightFilters['q']),
            $this->per_page('weight_per_page'),
            $this->page('weight_page')
        );
        $penaltyTypePg = $this->build_pagination(
            $this->Payroll_model->count_bonus_penalty_types($penaltyTypeFilters['q']),
            $this->per_page('penalty_type_per_page'),
            $this->page('penalty_type_page')
        );
        $penaltyEventPg = $this->build_pagination(
            $this->Payroll_model->count_bonus_penalty_events($month, $penaltyEventFilters['q']),
            $this->per_page('penalty_event_per_page'),
            $this->page('penalty_event_page')
        );
        $peerPg = $this->build_pagination(
            $this->Payroll_model->count_pending_peer_feedback($peerFilters['q']),
            $this->per_page('peer_per_page'),
            $this->page('peer_page')
        );
        $servicePg = $this->build_pagination(
            $this->Payroll_model->count_bonus_service_metrics($month, $serviceFilters['q']),
            $this->per_page('service_per_page'),
            $this->page('service_page')
        );
        $monthlyPg = $this->build_pagination(
            $this->Payroll_model->count_bonus_monthly_summaries($month, $monthlyFilters['q']),
            $this->per_page('monthly_per_page'),
            $this->page('monthly_page')
        );

        $this->render('payroll/bonus_workspace', [
            'title' => 'Bonus Pegawai',
            'active_menu' => 'pay.bonus',
            'month' => $month,
            'tab' => $tab,
            'summary' => $this->Payroll_model->get_bonus_workspace_summary($month),
            'pool_filters' => $poolFilters,
            'rule_filters' => $ruleFilters,
            'weight_filters' => $weightFilters,
            'penalty_type_filters' => $penaltyTypeFilters,
            'penalty_event_filters' => $penaltyEventFilters,
            'peer_filters' => $peerFilters,
            'service_filters' => $serviceFilters,
            'monthly_filters' => $monthlyFilters,
            'pool_pg' => $poolPg,
            'rule_pg' => $rulePg,
            'weight_pg' => $weightPg,
            'penalty_type_pg' => $penaltyTypePg,
            'penalty_event_pg' => $penaltyEventPg,
            'peer_pg' => $peerPg,
            'service_pg' => $servicePg,
            'monthly_pg' => $monthlyPg,
            'config_rows' => $this->Payroll_model->list_bonus_config_options(),
            'outlet_rows' => $this->Payroll_model->list_bonus_outlet_options(),
            'division_rows' => $this->Payroll_model->get_division_options(),
            'position_rows' => $this->Payroll_model->get_position_options(),
            'employee_rows' => $this->Payroll_model->get_employee_options(),
            'shift_rows' => $this->Payroll_model->get_shift_options(),
            'bonus_rule_option_rows' => $this->Payroll_model->list_bonus_rule_options(),
            'target_plan_rows' => $this->Payroll_model->list_bonus_target_plan_options(),
            'rule_rows' => $this->Payroll_model->list_bonus_rules($ruleFilters['q'], $rulePg['per_page'], $rulePg['offset']),
            'weight_rows' => $this->Payroll_model->list_bonus_weight_rules($weightFilters['q'], $weightPg['per_page'], $weightPg['offset']),
            'penalty_type_rows' => $this->Payroll_model->list_bonus_penalty_types($penaltyTypeFilters['q'], $penaltyTypePg['per_page'], $penaltyTypePg['offset']),
            'penalty_event_rows' => $this->Payroll_model->list_bonus_penalty_events($month, $penaltyEventFilters['q'], $penaltyEventPg['per_page'], $penaltyEventPg['offset']),
            'pool_rows' => $this->Payroll_model->list_bonus_recent_pools($month, $poolFilters['q'], $poolPg['per_page'], $poolPg['offset']),
            'pending_peer_rows' => $this->Payroll_model->list_pending_peer_feedback($peerFilters['q'], $peerPg['per_page'], $peerPg['offset']),
            'service_metric_rows' => $this->Payroll_model->list_bonus_service_metrics($month, $serviceFilters['q'], $servicePg['per_page'], $servicePg['offset']),
            'monthly_summary_rows' => $this->Payroll_model->list_bonus_monthly_summaries($month, $monthlyFilters['q'], $monthlyPg['per_page'], $monthlyPg['offset']),
        ]);
    }

    public function bonus_pool_generate()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $this->require_permission('payroll.bonus.index', 'create');
        $bonusDate = trim((string)$this->input->post('bonus_date', true));
        $ruleId = (int)$this->input->post('rule_id', true);
        $result = $this->Payroll_model->generate_bonus_pool_daily($bonusDate, $ruleId, (int)($this->current_user['id'] ?? 0));
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal generate draft bonus.'));
        redirect('payroll/bonus?' . http_build_query([
            'tab' => 'overview',
            'month' => preg_match('/^\d{4}-\d{2}$/', substr($bonusDate, 0, 7)) ? substr($bonusDate, 0, 7) : date('Y-m'),
        ]));
    }

    public function bonus_auto_penalty_sync()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $this->require_permission('payroll.bonus.index', 'edit');
        $bonusDate = trim((string)$this->input->post('bonus_date', true));
        $employeeId = (int)$this->input->post('employee_id', true);
        $result = $this->Payroll_model->sync_bonus_auto_penalties($bonusDate, $employeeId, (int)($this->current_user['id'] ?? 0));
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal sinkron penalti otomatis.'));
        redirect('payroll/bonus?' . http_build_query([
            'tab' => 'penalties',
            'month' => preg_match('/^\d{4}-\d{2}$/', substr($bonusDate, 0, 7)) ? substr($bonusDate, 0, 7) : date('Y-m'),
        ]));
    }

    public function bonus_pool_approve($poolId = 0)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $this->require_permission('payroll.bonus.index', 'edit');
        $poolId = (int)$poolId;
        $result = $this->Payroll_model->approve_bonus_pool_daily($poolId, (int)($this->current_user['id'] ?? 0));
        $month = trim((string)$this->input->post('month', true));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal mempublikasikan pool bonus.'));
        redirect('payroll/bonus?' . http_build_query([
            'tab' => 'overview',
            'month' => $month,
        ]));
    }

    public function bonus_service_metric_generate()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $this->require_permission('payroll.bonus.index', 'edit');
        $metricDate = trim((string)$this->input->post('metric_date', true));
        $outletId = (int)$this->input->post('outlet_id', true);
        $result = $this->Payroll_model->generate_bonus_service_metric_daily($metricDate, $outletId);
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal membangun metric layanan harian.'));
        redirect('payroll/bonus?' . http_build_query([
            'tab' => 'overview',
            'month' => preg_match('/^\d{4}-\d{2}$/', substr($metricDate, 0, 7)) ? substr($metricDate, 0, 7) : date('Y-m'),
        ]));
    }

    public function bonus_monthly_summary_generate()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $this->require_permission('payroll.bonus.index', 'edit');
        $month = trim((string)$this->input->post('summary_month', true));
        $result = $this->Payroll_model->refresh_bonus_monthly_summary($month, (int)($this->current_user['id'] ?? 0));
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal membangun rekap bonus bulanan.'));
        redirect('payroll/bonus?' . http_build_query([
            'tab' => 'overview',
            'month' => preg_match('/^\d{4}-\d{2}$/', $month) ? $month : date('Y-m'),
        ]));
    }

    public function bonus_rule_save()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $payload = $this->input->post(null, true) ?? [];
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('payroll.bonus.index', $id > 0 ? 'edit' : 'create');

        $result = $this->Payroll_model->save_bonus_rule($payload, (int)($this->current_user['id'] ?? 0));
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menyimpan aturan bonus.'));

        $query = http_build_query([
            'tab' => 'rules',
            'month' => trim((string)($payload['month'] ?? date('Y-m'))),
        ]);
        redirect('payroll/bonus' . ($query !== '' ? ('?' . $query) : ''));
    }

    public function bonus_weight_save()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $payload = $this->input->post(null, true) ?? [];
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('payroll.bonus.index', $id > 0 ? 'edit' : 'create');

        $result = $this->Payroll_model->save_bonus_weight_rule($payload);
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menyimpan bobot bonus.'));

        $query = http_build_query([
            'tab' => 'weights',
            'month' => trim((string)($payload['month'] ?? date('Y-m'))),
        ]);
        redirect('payroll/bonus' . ($query !== '' ? ('?' . $query) : ''));
    }

    public function bonus_penalty_type_save()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $payload = $this->input->post(null, true) ?? [];
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('payroll.bonus.index', $id > 0 ? 'edit' : 'create');

        $result = $this->Payroll_model->save_bonus_penalty_type($payload);
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menyimpan master penalti bonus.'));

        $query = http_build_query([
            'tab' => 'penalties',
            'month' => trim((string)($payload['month'] ?? date('Y-m'))),
        ]);
        redirect('payroll/bonus' . ($query !== '' ? ('?' . $query) : ''));
    }

    public function bonus_penalty_event_save()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $this->require_permission('payroll.bonus.index', 'create');
        $payload = $this->input->post(null, true) ?? [];
        $result = $this->Payroll_model->save_bonus_penalty_event($payload, (int)($this->current_user['id'] ?? 0));
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menyimpan kejadian penalti bonus.'));

        $query = http_build_query([
            'tab' => 'penalties',
            'month' => trim((string)($payload['month'] ?? date('Y-m'))),
        ]);
        redirect('payroll/bonus' . ($query !== '' ? ('?' . $query) : ''));
    }

    public function bonus_peer_moderate(int $id)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $this->require_permission('payroll.bonus.index', 'edit');
        $decision = trim((string)$this->input->post('decision', true));
        $notes = trim((string)$this->input->post('moderation_notes', true));
        $result = $this->Payroll_model->moderate_peer_feedback($id, $decision, $notes, (int)($this->current_user['id'] ?? 0));
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal moderasi penilaian 360.'));

        $query = http_build_query([
            'tab' => 'peer',
            'month' => trim((string)$this->input->post('month', true)) ?: date('Y-m'),
        ]);
        redirect('payroll/bonus' . ($query !== '' ? ('?' . $query) : ''));
    }
}
