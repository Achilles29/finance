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
}
