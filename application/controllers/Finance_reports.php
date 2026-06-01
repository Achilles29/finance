<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Finance_reports extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Finance_report_model');
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
}