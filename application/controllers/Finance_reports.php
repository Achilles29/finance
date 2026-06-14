<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Finance_reports extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Finance_report_model');
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

    private function actor_user_id(): int
    {
        return (int)($this->current_user['id'] ?? 0);
    }

    public function cash_vault_daily()
    {
        $this->require_permission('finance.cash_vault_daily.index', 'view');

        $month = trim((string)$this->input->get('month', true));
        if (!preg_match('/^\d{4}\-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $accounts = $this->Finance_report_model->active_company_accounts();
        $selectedAccountId = (int)$this->input->get('account_id', true);
        if ($selectedAccountId <= 0) {
            $selectedAccountId = (int)$this->input->get('bank_account_id', true);
        }

        $allowedIds = array_map(static function ($row) {
            return (int)($row['id'] ?? 0);
        }, (array)$accounts);
        $allowedIds = array_values(array_filter($allowedIds));

        if ($selectedAccountId <= 0 || !in_array($selectedAccountId, $allowedIds, true)) {
            $selectedAccountId = $this->Finance_report_model->default_cash_account_id($accounts);
        }

        $report = $this->Finance_report_model->cash_vault_daily($month, $selectedAccountId);

        $this->render('finance/cash_vault_daily', [
            'page_title' => 'Laporan Brankas Harian',
            'active_menu' => 'finance.cash_vault_daily',
            'month' => $month,
            'accounts' => $accounts,
            'selected_account_id' => $selectedAccountId,
            'report' => $report,
        ]);
    }

    public function cash_position()
    {
        $this->require_permission('finance.cash_position.index', 'view');

        $month = trim((string)$this->input->get('month', true));
        if (!preg_match('/^\d{4}\-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $viewMode = strtoupper(trim((string)$this->input->get('view_mode', true)));
        if (!in_array($viewMode, ['PHYSICAL', 'REAL', 'HISTORICAL'], true)) {
            $viewMode = 'REAL';
        }

        $accounts = $this->Finance_report_model->active_company_accounts();
        $selectedAccountId = (int)$this->input->get('account_id', true);
        $allowedIds = array_map(static function ($row) {
            return (int)($row['id'] ?? 0);
        }, (array)$accounts);
        $allowedIds = array_values(array_filter($allowedIds));
        if ($selectedAccountId > 0 && !in_array($selectedAccountId, $allowedIds, true)) {
            $selectedAccountId = 0;
        }

        $report = $this->Finance_report_model->cash_position_exposure($month, $selectedAccountId, $viewMode);

        $this->render('finance/cash_position_exposure', [
            'page_title' => 'Posisi Kas & Eksposur',
            'active_menu' => 'finance.cash_position',
            'month' => $month,
            'view_mode' => $viewMode,
            'accounts' => $accounts,
            'selected_account_id' => $selectedAccountId,
            'report' => $report,
        ]);
    }

    public function period_close()
    {
        $this->require_permission('finance.period_close.index', 'view');

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => strtoupper(trim((string)$this->input->get('status', true))),
            'period_type' => strtoupper(trim((string)$this->input->get('period_type', true))),
        ];

        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->Finance_report_model->count_period_closes($filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->Finance_report_model->list_period_closes($filters, $pg['per_page'], $pg['offset']);
        $summary = $this->Finance_report_model->summarize_period_closes($filters);

        $this->render('finance/period_close_index', [
            'page_title' => 'Tutup Periode Keuangan',
            'active_menu' => 'finance.period_close',
            'filters' => $filters,
            'pg' => $pg,
            'rows' => $rows,
            'summary' => $summary,
        ]);
    }

    public function period_close_detail($id = 0)
    {
        $this->require_permission('finance.period_close.index', 'view');

        $row = $this->Finance_report_model->get_period_close_detail((int)$id);
        if (!$row) {
            show_404();
        }

        $this->render('finance/period_close_detail', [
            'page_title' => 'Detail Tutup Periode',
            'active_menu' => 'finance.period_close',
            'row' => $row,
            'snapshot_rows' => $this->Finance_report_model->list_period_close_snapshots((int)$id),
            'metric_rows' => $this->Finance_report_model->list_period_close_metrics((int)$id),
            'snapshot_summary' => $this->Finance_report_model->summarize_period_close_snapshots((int)$id),
            'metric_summary' => $this->Finance_report_model->summarize_period_close_metrics((int)$id),
        ]);
    }

    public function period_close_store()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $this->require_permission('finance.period_close.index', 'create');
        $result = $this->Finance_report_model->save_period_close($this->input->post(NULL, true) ?: [], $this->actor_user_id());
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menyimpan draft tutup periode.'));
        redirect('finance-reports/period-close');
    }

    public function period_close_process($id = 0)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $this->require_permission('finance.period_close.index', 'edit');
        $result = $this->Finance_report_model->close_period((int)$id, $this->actor_user_id());
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal memproses close period.'));
        $redirectTo = trim((string)$this->input->post('redirect_to', true));
        redirect($redirectTo !== '' ? $redirectTo : 'finance-reports/period-close');
    }

    public function period_close_reopen($id = 0)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $this->require_permission('finance.period_close.index', 'edit');
        $result = $this->Finance_report_model->reopen_period((int)$id, $this->actor_user_id());
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal membuka ulang period close.'));
        redirect('finance-reports/period-close/detail/' . (int)$id);
    }

    public function targets()
    {
        $this->require_permission('finance.target.index', 'view');

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => strtoupper(trim((string)$this->input->get('status', true))),
            'target_scope' => strtoupper(trim((string)$this->input->get('target_scope', true))),
            'division_id' => (int)$this->input->get('division_id', true),
        ];

        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->Finance_report_model->count_target_plans($filters);
        $pg = $this->build_pagination($total, $perPage, $page);
        $rows = $this->Finance_report_model->list_target_plans($filters, $pg['per_page'], $pg['offset']);
        $summary = $this->Finance_report_model->summarize_target_plans($filters);

        $this->render('finance/target_plan_index', [
            'page_title' => 'Target Keuangan',
            'active_menu' => 'finance.target',
            'filters' => $filters,
            'pg' => $pg,
            'rows' => $rows,
            'summary' => $summary,
            'division_options' => $this->Finance_report_model->division_options(),
            'company_accounts' => $this->Finance_report_model->active_company_accounts(),
            'metric_catalog' => $this->Finance_report_model->metric_catalog_options(),
        ]);
    }

    public function target_detail($id = 0)
    {
        $this->require_permission('finance.target.index', 'view');

        $row = $this->Finance_report_model->get_target_plan_detail((int)$id);
        if (!$row) {
            show_404();
        }

        $this->render('finance/target_plan_detail', [
            'page_title' => 'Detail Target Keuangan',
            'active_menu' => 'finance.target',
            'row' => $row,
            'metric_lines' => $this->Finance_report_model->list_target_plan_lines((int)$id),
            'realization_summary' => $this->Finance_report_model->summarize_target_plan_realization((int)$id),
        ]);
    }

    public function target_store()
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $this->require_permission('finance.target.index', 'create');
        $result = $this->Finance_report_model->save_target_plan($this->input->post(NULL, true) ?: [], $this->actor_user_id());
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menyimpan draft target keuangan.'));
        redirect('finance-reports/targets');
    }

    public function target_realize($id = 0)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $this->require_permission('finance.target.index', 'edit');
        $result = $this->Finance_report_model->generate_target_realization((int)$id, $this->actor_user_id());
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menghitung realisasi target.'));
        $redirectTo = trim((string)$this->input->post('redirect_to', true));
        redirect($redirectTo !== '' ? $redirectTo : 'finance-reports/targets');
    }

    public function target_update($id = 0)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $this->require_permission('finance.target.index', 'edit');
        $result = $this->Finance_report_model->update_target_plan((int)$id, $this->input->post(NULL, true) ?: [], $this->actor_user_id());
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal memperbarui target.'));
        redirect('finance-reports/targets/detail/' . (int)$id);
    }

    public function target_lines_save($id = 0)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $this->require_permission('finance.target.index', 'edit');
        $result = $this->Finance_report_model->save_target_plan_lines((int)$id, $this->input->post(NULL, true) ?: [], $this->actor_user_id());
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Gagal menyimpan metric target.'));
        redirect('finance-reports/targets/detail/' . (int)$id);
    }
}
