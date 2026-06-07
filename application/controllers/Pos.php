<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pos extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Pos_model');
        $this->load->model('Pos_report_model');
        $this->load->model('Pos_order_monitor_model');
        $this->load->model('Purchase_model');
        $this->load->model('Production_model');
        $this->load->library('PosPrinterPreviewService', null, 'posprinterpreviewservice');
        $this->load->library('PosRuntimeJobService', null, 'posruntimejobservice');
    }

    public function members()
    {
        redirect('loyalty/members');
    }

    public function members_data()
    {
        $this->require_permission('pos.member.index', 'view');
        $this->json_ok($this->Pos_model->member_rows($this->member_filters()));
    }

    public function member_save()
    {
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('pos.member.index', $id > 0 ? 'edit' : 'create');
        $result = $this->Pos_model->save_member($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan member.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function member_toggle($id)
    {
        $this->require_permission('pos.member.index', 'edit');
        $result = $this->Pos_model->toggle_member((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal mengubah status member.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id'], 'is_active' => (int)$result['is_active']]);
    }

    public function payment_methods()
    {
        $this->require_permission('pos.payment_method.index', 'view');
        $this->render('pos/payment_method_index', [
            'page_title' => 'Payment Method POS',
            'filters' => $this->payment_method_filters(),
            'filter_options' => $this->Pos_model->payment_method_filter_options(),
        ]);
    }

    public function stock_commit_audit()
    {
        $this->require_permission('pos.stock.live.index', 'view');
        if (!isset($this->posruntimejobservice) || !is_object($this->posruntimejobservice)) {
            $this->load->library('PosRuntimeJobService', null, 'posruntimejobservice');
        }

        $asOfDate = trim((string)$this->input->get('as_of_date', true));
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $asOfDate)) {
            $asOfDate = date('Y-m-d');
        }
        $tab = strtolower(trim((string)$this->input->get('tab', true)));
        if (!in_array($tab, ['material', 'component'], true)) {
            $tab = 'material';
        }

        $materialCompare = $this->Purchase_model->list_division_material_stock_compare(
            $asOfDate,
            '',
            null,
            20,
            'ALL'
        );
        $domainAudit = $this->Purchase_model->production_domain_root_cause_audit([
            'limit' => 20,
            'active_only' => true,
        ]);
        $componentCompare = $this->Production_model->component_reconcile_rows([
            'as_of_date' => $asOfDate,
            'q' => '',
            'location_type' => 'ALL',
            'division_id' => 0,
            'type' => '',
        ], 20);

        $failedJobs = $this->posruntimejobservice->failed_jobs(['limit' => 15]);
        $activeJobs = $this->posruntimejobservice->active_jobs(['limit' => 15]);

        $this->render('pos/stock_commit_audit_index', [
            'page_title' => 'Audit Commit Stok POS',
            'active_menu' => 'pos.stock.commit.audit',
            'pos_master_tab_active' => 'stock-commit-audit',
            'audit_tab' => $tab,
            'as_of_date' => $asOfDate,
            'material_compare' => $materialCompare,
            'domain_audit' => !empty($domainAudit['ok']) ? (array)($domainAudit['data'] ?? []) : ['summary' => [], 'rows' => []],
            'component_compare' => $componentCompare,
            'failed_jobs' => !empty($failedJobs['ok']) ? (array)($failedJobs['rows'] ?? []) : [],
            'active_jobs' => !empty($activeJobs['ok']) ? (array)($activeJobs['rows'] ?? []) : [],
        ]);
    }

    public function deposits()
    {
        $this->require_permission('pos.deposit.index', 'view');
        $this->render('pos/deposit_index', [
            'page_title' => 'Deposit / DP POS',
            'filters' => $this->deposit_filters(),
            'payment_methods' => $this->Pos_model->deposit_payment_method_options(),
        ]);
    }

    public function deposits_data()
    {
        $this->require_permission('pos.deposit.index', 'view');
        $this->json_ok($this->Pos_model->deposit_rows($this->deposit_filters()));
    }

    public function self_order()
    {
        redirect('pos/self-order/orders');
    }

    public function self_order_settings()
    {
        $this->require_permission('pos.self_order.index', 'view');

        if (strtoupper((string)$this->input->method()) === 'POST') {
            $this->require_permission('pos.self_order.index', 'edit');
            $result = $this->Pos_model->save_self_order_settings($this->input->post(null, false) ?: []);
            $this->session->set_flashdata(($result['ok'] ?? false) ? 'success' : 'error', (string)($result['message'] ?? (($result['ok'] ?? false) ? 'Pengaturan self order berhasil disimpan.' : 'Gagal menyimpan pengaturan self order.')));
            redirect('pos/self-order/settings');
            return;
        }

        $this->render('pos/self_order_settings', [
            'page_title' => 'Self Order POS',
            'active_menu' => 'pos.self_order.index',
            'settings' => $this->Pos_model->self_order_settings(),
        ]);
    }

    public function self_order_tables()
    {
        $this->require_permission('pos.self_order.index', 'view');
        $this->render('pos/self_order_tables', [
            'page_title' => 'QR Meja Self Order',
            'active_menu' => 'pos.self_order.index',
            'filters' => $this->self_order_table_filters(),
            'settings' => $this->Pos_model->self_order_settings(),
        ]);
    }

    public function self_order_tables_data()
    {
        $this->require_permission('pos.self_order.index', 'view');
        $this->json_ok($this->Pos_model->self_order_table_rows($this->self_order_table_filters()));
    }

    public function self_order_table_save()
    {
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('pos.self_order.index', $id > 0 ? 'edit' : 'create');
        $result = $this->Pos_model->save_self_order_table($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan meja self order.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)($result['id'] ?? 0)]);
    }

    public function self_order_table_delete($id)
    {
        $this->require_permission('pos.self_order.index', 'delete');
        $result = $this->Pos_model->delete_self_order_table((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menghapus meja self order.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id]);
    }

    public function self_order_tables_print()
    {
        $this->require_permission('pos.self_order.index', 'view');
        $rows = $this->Pos_model->self_order_print_rows();
        $this->load->view('pos/self_order_tables_print', [
            'title' => 'Print QR Meja Self Order',
            'rows' => $rows,
        ]);
    }

    public function self_order_orders()
    {
        $this->require_permission('pos.self_order.index', 'view');
        $filters = $this->self_order_order_filters();
        $this->render('pos/self_order_orders', [
            'page_title' => 'Orderan Self Order',
            'active_menu' => 'pos.self_order.index',
            'filters' => $filters,
            'filter_options' => $this->Pos_model->self_order_order_filter_options(),
        ]);
    }

    public function self_order_orders_data()
    {
        $this->require_permission('pos.self_order.index', 'view');
        $this->json_ok($this->Pos_model->self_order_order_rows($this->self_order_order_filters()));
    }

    public function self_order_order_detail($id)
    {
        $this->require_permission('pos.self_order.index', 'view');
        $result = $this->Pos_model->find_self_order_order((int)$id);
        if (!$result) {
            $this->json_error('Order self order tidak ditemukan.', 404);
            return;
        }
        $this->json_ok($result + [
            'payments' => $this->Pos_report_model->order_payment_rows((int)$id),
            'refunds' => $this->Pos_report_model->order_refund_rows((int)$id),
            'voids' => $this->Pos_report_model->order_void_rows((int)$id),
        ]);
    }

    public function self_order_order_verify($id)
    {
        $this->require_permission('pos.self_order.index', 'edit');
        $this->verify_self_order_and_respond((int)$id, $this->current_actor_employee_id());
    }

    public function deposit_member_search()
    {
        $this->require_permission('pos.deposit.index', 'view');
        $q = trim((string)$this->input->get('q', true));
        $limit = max(1, min(15, (int)$this->input->get('limit', true)));
        $this->json_ok([
            'rows' => $this->Pos_model->order_member_search($q, $limit),
        ]);
    }

    public function deposit_save()
    {
        $this->require_permission('pos.deposit.index', 'create');
        $payload = $this->request_payload();
        $result = $this->Pos_model->save_deposit($payload, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan deposit / DP.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)($result['id'] ?? 0),
            'payment_no' => (string)($result['payment_no'] ?? ''),
            'member_id' => (int)($result['member_id'] ?? 0),
        ]);
    }

    public function deposit_void($id)
    {
        $this->require_permission('pos.deposit.index', 'edit');
        $result = $this->Pos_model->void_deposit((int)$id, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal membatalkan deposit / DP.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id]);
    }

    public function sales_channels()
    {
        $this->require_permission('pos.sales_channel.index', 'view');
        $this->render('pos/sales_channel_index', [
            'page_title' => 'Sales Channel POS',
            'filters' => $this->sales_channel_filters(),
            'filter_options' => $this->Pos_model->sales_channel_filter_options(),
        ]);
    }

    public function sales_channels_data()
    {
        $this->require_permission('pos.sales_channel.index', 'view');
        $this->json_ok($this->Pos_model->sales_channel_rows($this->sales_channel_filters()));
    }

    public function sales_channel_save()
    {
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('pos.sales_channel.index', $id > 0 ? 'edit' : 'create');
        $result = $this->Pos_model->save_sales_channel($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan sales channel.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function sales_channel_toggle($id)
    {
        $this->require_permission('pos.sales_channel.index', 'edit');
        $result = $this->Pos_model->toggle_sales_channel((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal mengubah status sales channel.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id'], 'is_active' => (int)$result['is_active']]);
    }

    public function sales_channel_delete($id)
    {
        $this->require_permission('pos.sales_channel.index', 'delete');
        $result = $this->Pos_model->delete_sales_channel((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menghapus sales channel.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function payment_methods_data()
    {
        $this->require_permission('pos.payment_method.index', 'view');
        $this->json_ok($this->Pos_model->payment_method_rows($this->payment_method_filters()));
    }

    public function payment_method_save()
    {
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('pos.payment_method.index', $id > 0 ? 'edit' : 'create');
        $result = $this->Pos_model->save_payment_method($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan payment method.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function payment_method_toggle($id)
    {
        $this->require_permission('pos.payment_method.index', 'edit');
        $result = $this->Pos_model->toggle_payment_method((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal mengubah status payment method.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id'], 'is_active' => (int)$result['is_active']]);
    }

    public function outlets_terminals()
    {
        $this->require_permission('pos.outlet_terminal.index', 'view');
        $this->render('pos/outlet_terminal_index', [
            'page_title' => 'Outlet + Terminal POS',
            'outlet_filters' => $this->outlet_filters(),
            'terminal_filters' => $this->terminal_filters(),
            'filter_options' => $this->Pos_model->outlet_terminal_filter_options(),
        ]);
    }

    public function outlets_data()
    {
        $this->require_permission('pos.outlet_terminal.index', 'view');
        $this->json_ok($this->Pos_model->outlet_rows($this->outlet_filters()));
    }

    public function outlet_save()
    {
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('pos.outlet_terminal.index', $id > 0 ? 'edit' : 'create');
        $result = $this->Pos_model->save_outlet($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan outlet.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function outlet_toggle($id)
    {
        $this->require_permission('pos.outlet_terminal.index', 'edit');
        $result = $this->Pos_model->toggle_outlet((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal mengubah status outlet.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id'], 'is_active' => (int)$result['is_active']]);
    }

    public function terminals_data()
    {
        $this->require_permission('pos.outlet_terminal.index', 'view');
        $this->json_ok($this->Pos_model->terminal_rows($this->terminal_filters()));
    }

    public function terminal_save()
    {
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('pos.outlet_terminal.index', $id > 0 ? 'edit' : 'create');
        $result = $this->Pos_model->save_terminal($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan terminal.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function terminal_toggle($id)
    {
        $this->require_permission('pos.outlet_terminal.index', 'edit');
        $result = $this->Pos_model->toggle_terminal((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal mengubah status terminal.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id'], 'is_active' => (int)$result['is_active']]);
    }

    public function printers()
    {
        $this->require_permission('pos.printer.index', 'view');
        $this->render('pos/printer_index', [
            'page_title' => 'Printer POS',
            'active_menu' => 'pos.printer.index',
            'template_filters' => $this->printer_template_filters(),
            'profile_filters' => $this->printer_profile_filters(),
            'device_filters' => $this->printer_device_filters(),
            'filter_options' => $this->Pos_model->printer_filter_options(),
        ]);
    }

    public function printer_templates()
    {
        $this->require_permission('pos.printer.index', 'view');
        $this->render('pos/printer_templates_index', [
            'page_title' => 'Template Printer POS',
            'active_menu' => 'pos.printer.index',
            'template_filters' => $this->printer_template_filters(),
        ]);
    }

    public function printer_profiles()
    {
        $this->require_permission('pos.printer.index', 'view');
        $this->render('pos/printer_profiles_index', [
            'page_title' => 'Pengaturan Output Printer POS',
            'active_menu' => 'pos.printer.index',
            'profile_filters' => $this->printer_profile_filters(),
            'filter_options' => $this->Pos_model->printer_filter_options(),
        ]);
    }

    public function printer_devices()
    {
        $this->require_permission('pos.printer.index', 'view');
        $this->render('pos/printer_devices_index', [
            'page_title' => 'Device Printer POS',
            'active_menu' => 'pos.printer.index',
            'device_filters' => $this->printer_device_filters(),
            'filter_options' => $this->Pos_model->printer_filter_options(),
        ]);
    }

    public function printer_workspace_legacy()
    {
        $this->require_permission('pos.printer.index', 'view');
        $this->render('pos/printer_index', [
            'page_title' => 'Printer POS',
            'active_menu' => 'pos.printer.index',
            'template_filters' => $this->printer_template_filters(),
            'profile_filters' => $this->printer_profile_filters(),
            'device_filters' => $this->printer_device_filters(),
            'filter_options' => $this->Pos_model->printer_filter_options(),
        ]);
    }

    public function printer_settings()
    {
        $this->require_permission('pos.printer.index', 'edit');
        $general = $this->Pos_model->printer_general_settings();
        $payload = is_array($general['payload'] ?? null) ? $general['payload'] : [];

        if ($this->input->method() === 'post') {
            $result = $this->Pos_model->save_printer_general_settings([
                'title' => $this->input->post('title', false),
                'subtitle' => $this->input->post('subtitle', false),
                'logo_url' => $this->input->post('logo_url', false),
                'wifi_name' => $this->input->post('wifi_name', false),
                'wifi_password' => $this->input->post('wifi_password', false),
                'show_customer_point_info' => $this->input->post('show_customer_point_info') ? 1 : 0,
                'show_customer_stamp_info' => $this->input->post('show_customer_stamp_info') ? 1 : 0,
                'show_customer_voucher' => $this->input->post('show_customer_voucher') ? 1 : 0,
                'customer_voucher_limit' => $this->input->post('customer_voucher_limit', false),
                'customer_voucher_message_template' => $this->input->post('customer_voucher_message_template', false),
                'customer_voucher_align' => $this->input->post('customer_voucher_align', false),
                'header_lines' => preg_split('/\r?\n/', trim((string)$this->input->post('header_lines', false))),
                'footer_lines' => preg_split('/\r?\n/', trim((string)$this->input->post('footer_lines', false))),
            ]);
            if ($result['ok'] ?? false) {
                $this->session->set_flashdata('success', 'Pengaturan umum printer berhasil disimpan.');
                redirect('pos/printers/settings');
                return;
            }
            $this->session->set_flashdata('error', (string)($result['message'] ?? 'Gagal menyimpan pengaturan umum printer.'));
            $payload = array_merge($payload, [
                'title' => (string)$this->input->post('title', false),
                'subtitle' => (string)$this->input->post('subtitle', false),
                'logo_url' => (string)$this->input->post('logo_url', false),
                'wifi_name' => (string)$this->input->post('wifi_name', false),
                'wifi_password' => (string)$this->input->post('wifi_password', false),
                'show_customer_point_info' => $this->input->post('show_customer_point_info') ? 1 : 0,
                'show_customer_stamp_info' => $this->input->post('show_customer_stamp_info') ? 1 : 0,
                'show_customer_voucher' => $this->input->post('show_customer_voucher') ? 1 : 0,
                'customer_voucher_limit' => max(1, (int)$this->input->post('customer_voucher_limit', false)),
                'customer_voucher_message_template' => (string)$this->input->post('customer_voucher_message_template', false),
                'customer_voucher_align' => strtoupper((string)$this->input->post('customer_voucher_align', false)),
                'header_lines' => preg_split('/\r?\n/', trim((string)$this->input->post('header_lines', false))),
                'footer_lines' => preg_split('/\r?\n/', trim((string)$this->input->post('footer_lines', false))),
            ]);
        }

        $this->render('pos/printer_settings', [
            'page_title' => 'Pengaturan Umum Printer POS',
            'active_menu' => 'pos.printer.index',
            'payload' => $payload,
        ]);
    }

    public function printer_templates_data()
    {
        $this->require_permission('pos.printer.index', 'view');
        $this->json_ok($this->Pos_model->printer_template_rows($this->printer_template_filters()));
    }

    public function printer_template_create()
    {
        $this->require_permission('pos.printer.index', 'create');
        $documentType = strtoupper(trim((string)$this->input->get('document_type', true)));
        if (!in_array($documentType, ['RECEIPT', 'KITCHEN_TICKET', 'VOID_SLIP', 'REFUND_SLIP', 'DEPOSIT_RECEIPT'], true)) {
            $documentType = 'RECEIPT';
        }

        $generalSettings = $this->Pos_model->printer_general_settings();
        $payload = $this->posprinterpreviewservice->defaultPayload($documentType, (array)($generalSettings['payload'] ?? []));
        if ($this->input->method() === 'post') {
            $saved = $this->save_printer_template_from_form(0);
            if ($saved['ok']) {
                redirect('pos/printers/templates/preview/' . (int)$saved['id']);
                return;
            }
            $this->session->set_flashdata('error', (string)$saved['message']);
            $payload = $this->posprinterpreviewservice->payloadFromInput($this->input->post(null, false), $documentType, (array)($generalSettings['payload'] ?? []));
        }

        $this->render('pos/printer_template_editor', [
            'page_title' => 'Tambah Template Printer POS',
            'active_menu' => 'pos.printer.index',
            'row' => null,
            'document_types' => ['RECEIPT', 'KITCHEN_TICKET', 'VOID_SLIP', 'REFUND_SLIP', 'DEPOSIT_RECEIPT'],
            'payload' => $payload,
            'preview_printers' => $this->Pos_model->active_printer_preview_options(),
        ]);
    }

    public function printer_template_edit($id)
    {
        $this->require_permission('pos.printer.index', 'edit');
        $row = $this->Pos_model->find_printer_template((int)$id);
        if (!$row) {
            show_404();
            return;
        }

        $generalSettings = $this->Pos_model->printer_general_settings();
        $payload = $this->posprinterpreviewservice->decodePayload((string)($row['template_payload'] ?? '{}'), (string)($row['document_type'] ?? 'RECEIPT'), (array)($generalSettings['payload'] ?? []));
        if ($this->input->method() === 'post') {
            $saved = $this->save_printer_template_from_form((int)$id);
            if ($saved['ok']) {
                redirect('pos/printers/templates/preview/' . (int)$saved['id']);
                return;
            }
            $this->session->set_flashdata('error', (string)$saved['message']);
            $payload = $this->posprinterpreviewservice->payloadFromInput($this->input->post(null, false), (string)($row['document_type'] ?? 'RECEIPT'), (array)($generalSettings['payload'] ?? []));
            $row = array_merge($row, $this->input->post(null, false) ?: []);
        }

        $this->render('pos/printer_template_editor', [
            'page_title' => 'Edit Template Printer POS',
            'active_menu' => 'pos.printer.index',
            'row' => $row,
            'document_types' => ['RECEIPT', 'KITCHEN_TICKET', 'VOID_SLIP', 'REFUND_SLIP', 'DEPOSIT_RECEIPT'],
            'payload' => $payload,
            'preview_printers' => $this->Pos_model->active_printer_preview_options(),
        ]);
    }

    public function printer_template_preview($id)
    {
        $this->require_permission('pos.printer.index', 'view');
        $row = $this->Pos_model->find_printer_template((int)$id);
        if (!$row) {
            show_404();
            return;
        }

        $previewPrinters = $this->Pos_model->active_printer_preview_options();
        $selectedPrinterId = max(0, (int)$this->input->get('printer_id', true));
        $selectedPrinter = [];
        foreach ($previewPrinters as $printer) {
            if ((int)$printer['id'] === $selectedPrinterId) {
                $selectedPrinter = $printer;
                break;
            }
        }
        if (!$selectedPrinter && !empty($previewPrinters)) {
            $selectedPrinter = $previewPrinters[0];
            $selectedPrinterId = (int)$selectedPrinter['id'];
        }

        $generalSettings = $this->Pos_model->printer_general_settings();
        $payload = $this->posprinterpreviewservice->decodePayload((string)($row['template_payload'] ?? '{}'), (string)($row['document_type'] ?? 'RECEIPT'), (array)($generalSettings['payload'] ?? []));
        $preview = $this->posprinterpreviewservice->buildPreviewPackage($payload, $selectedPrinter, (string)($row['document_type'] ?? 'RECEIPT'));

        $this->render('pos/printer_template_preview', [
            'page_title' => 'Preview Template Printer POS',
            'active_menu' => 'pos.printer.index',
            'row' => $row,
            'payload' => $payload,
            'preview' => $preview,
            'preview_printers' => $previewPrinters,
            'selected_printer_id' => $selectedPrinterId,
        ]);
    }

    public function printer_template_live_preview()
    {
        $this->require_permission('pos.printer.index', 'view');
        $payload = $this->request_payload();
        $documentType = strtoupper(trim((string)($payload['document_type'] ?? 'RECEIPT')));
        if (!in_array($documentType, ['RECEIPT', 'KITCHEN_TICKET', 'VOID_SLIP', 'REFUND_SLIP', 'DEPOSIT_RECEIPT'], true)) {
            $documentType = 'RECEIPT';
        }
        $printerId = (int)($payload['printer_id'] ?? 0);
        $printer = $printerId > 0 ? ($this->Pos_model->find_printer_device($printerId) ?: []) : [];
        $generalSettings = $this->Pos_model->printer_general_settings();
        $templatePayload = $this->posprinterpreviewservice->payloadFromInput($payload, $documentType, (array)($generalSettings['payload'] ?? []));
        $preview = $this->posprinterpreviewservice->buildPreviewPackage($templatePayload, $printer, $documentType);
        $this->json_ok($preview);
    }

    public function printer_template_save()
    {
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('pos.printer.index', $id > 0 ? 'edit' : 'create');
        $result = $this->Pos_model->save_printer_template($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan template printer.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function printer_template_toggle($id)
    {
        $this->require_permission('pos.printer.index', 'edit');
        $result = $this->Pos_model->toggle_printer_template((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal mengubah status template printer.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id'], 'is_active' => (int)$result['is_active']]);
    }

    public function printer_profiles_data()
    {
        $this->require_permission('pos.printer.index', 'view');
        $this->json_ok($this->Pos_model->printer_profile_rows($this->printer_profile_filters()));
    }

    public function printer_profile_save()
    {
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('pos.printer.index', $id > 0 ? 'edit' : 'create');
        $result = $this->Pos_model->save_printer_profile($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan profile printer.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function printer_profile_toggle($id)
    {
        $this->require_permission('pos.printer.index', 'edit');
        $result = $this->Pos_model->toggle_printer_profile((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal mengubah status profile printer.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id'], 'is_active' => (int)$result['is_active']]);
    }

    public function printer_devices_data()
    {
        $this->require_permission('pos.printer.index', 'view');
        $this->json_ok($this->Pos_model->printer_device_rows($this->printer_device_filters()));
    }

    public function printer_device_save()
    {
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('pos.printer.index', $id > 0 ? 'edit' : 'create');
        $result = $this->Pos_model->save_printer_device($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan device printer.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function printer_device_toggle($id)
    {
        $this->require_permission('pos.printer.index', 'edit');
        $result = $this->Pos_model->toggle_printer_device((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal mengubah status device printer.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id'], 'is_active' => (int)$result['is_active']]);
    }

    public function printer_preview($id)
    {
        $this->require_permission('pos.printer.index', 'view');
        $row = $this->Pos_model->find_printer_device((int)$id);
        if (!$row) {
            show_404();
            return;
        }

        $templates = $this->Pos_model->active_printer_template_options();
        $templateId = max(0, (int)$this->input->get('template_id', true));
        $selectedTemplate = [];
        foreach ($templates as $template) {
            if ((int)$template['id'] === $templateId) {
                $selectedTemplate = $template;
                break;
            }
        }
        if (!$selectedTemplate) {
            foreach ($templates as $template) {
                if (strtoupper((string)($template['document_type'] ?? '')) === 'RECEIPT' && (int)($template['is_default'] ?? 0) === 1) {
                    $selectedTemplate = $template;
                    break;
                }
            }
        }
        if (!$selectedTemplate && !empty($templates)) {
            $selectedTemplate = $templates[0];
        }

        $payload = $this->posprinterpreviewservice->defaultPayload('RECEIPT');
        $documentType = 'RECEIPT';
        if (!empty($selectedTemplate)) {
            $documentType = (string)($selectedTemplate['document_type'] ?? 'RECEIPT');
            $templateRow = $this->Pos_model->find_printer_template((int)$selectedTemplate['id']);
            if ($templateRow) {
                $selectedTemplate = $templateRow;
                $payload = $this->posprinterpreviewservice->decodePayload((string)($templateRow['template_payload'] ?? '{}'), $documentType);
            }
        }

        $preview = $this->posprinterpreviewservice->buildPreviewPackage($payload, $row, $documentType);
        $this->render('pos/printer_preview', [
            'page_title' => 'Preview Printer POS',
            'active_menu' => 'pos.printer.index',
            'row' => $row,
            'templates' => $templates,
            'selected_template' => $selectedTemplate,
            'preview' => $preview,
            'autoprint' => (int)$this->input->get('autoprint', true) === 1,
        ]);
    }

    public function printer_test($id)
    {
        $this->require_permission('pos.printer.index', 'view');
        $row = $this->Pos_model->find_printer_device((int)$id);
        if (!$row) {
            $this->json_error('Device printer tidak ditemukan.', 404);
            return;
        }

        $templates = $this->Pos_model->active_printer_template_options();
        $templateId = max(0, (int)$this->input->get('template_id', true));
        $selectedTemplate = [];
        foreach ($templates as $template) {
            if ((int)$template['id'] === $templateId) {
                $selectedTemplate = $template;
                break;
            }
        }
        if (!$selectedTemplate) {
            foreach ($templates as $template) {
                if (
                    strtoupper((string)($template['document_type'] ?? '')) === 'RECEIPT'
                    && (int)($template['is_default'] ?? 0) === 1
                ) {
                    $selectedTemplate = $template;
                    break;
                }
            }
        }
        if (!$selectedTemplate && !empty($templates)) {
            $selectedTemplate = $templates[0];
        }

        $payload = $this->posprinterpreviewservice->defaultPayload('RECEIPT');
        $documentType = 'RECEIPT';
        if (!empty($selectedTemplate)) {
            $documentType = (string)($selectedTemplate['document_type'] ?? 'RECEIPT');
            $templateRow = $this->Pos_model->find_printer_template((int)$selectedTemplate['id']);
            if ($templateRow) {
                $selectedTemplate = $templateRow;
                $payload = $this->posprinterpreviewservice->decodePayload(
                    (string)($templateRow['template_payload'] ?? '{}'),
                    $documentType
                );
            }
        }

        $preview = $this->posprinterpreviewservice->buildPreviewPackage($payload, $row, $documentType);
        $this->json_ok([
            'printer' => [
                'id' => (int)($row['id'] ?? 0),
                'device_code' => (string)($row['device_code'] ?? ''),
                'device_name' => (string)($row['device_name'] ?? ''),
                'agent_host' => (string)($row['agent_host'] ?? ''),
                'python_port' => (int)($preview['summary']['python_port'] ?? $row['python_port'] ?? 0),
            ],
            'template' => [
                'id' => (int)($selectedTemplate['id'] ?? 0),
                'template_name' => (string)($selectedTemplate['template_name'] ?? 'Preview Default'),
                'document_type' => (string)($selectedTemplate['document_type'] ?? $documentType),
            ],
            'preview' => $preview,
            'print_payload' => [
                'text' => (!empty($preview['logo_url']) ? '[[LOGO_URL:' . $preview['logo_url'] . ']]' . "\n" : '')
                    . implode("\n", (array)($preview['lines'] ?? [])),
                'paper_width_mm' => (int)($preview['paper_width_mm'] ?? 80),
                'chars_per_line' => (int)($preview['chars_per_line'] ?? 48),
            ],
        ]);
    }

    public function printer_guide()
    {
        $this->require_permission('pos.printer.index', 'view');
        $this->render('pos/printer_guide', [
            'page_title' => 'Panduan Printer POS',
            'active_menu' => 'pos.printer.index',
            'download_files' => $this->printer_download_files(),
        ]);
    }

    public function printer_download($key = '')
    {
        $this->require_permission('pos.printer.index', 'view');
        $files = $this->printer_download_files();
        if (!isset($files[$key])) {
            show_404();
            return;
        }

        $file = $files[$key];
        if ($key === 'config_json') {
            $content = $this->build_printer_agent_config_json(trim((string)$this->input->get('agent_name', true)));
            $this->output
                ->set_content_type('application/json')
                ->set_header('Content-Disposition: attachment; filename="' . $file['filename'] . '"')
                ->set_output($content);
            return;
        }

        $path = (string)($file['path'] ?? '');
        if ($path === '' || !is_file($path)) {
            show_404();
            return;
        }

        $mime = 'application/octet-stream';
        if (substr($path, -3) === '.md') {
            $mime = 'text/markdown';
        } elseif (substr($path, -3) === '.py') {
            $mime = 'text/x-python';
        } elseif (substr($path, -4) === '.bat') {
            $mime = 'text/plain';
        } elseif (substr($path, -5) === '.json') {
            $mime = 'application/json';
        }

        $this->output
            ->set_content_type($mime)
            ->set_header('Content-Disposition: attachment; filename="' . $file['filename'] . '"')
            ->set_output((string)file_get_contents($path));
    }

    public function printer_bootstrap()
    {
        if (!$this->verify_printer_agent_key()) {
            return;
        }

        $agentName = trim((string)$this->input->get('agent_name', true));
        $rows = $this->Pos_model->active_printer_devices_for_agent_config($agentName);
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status' => 'success',
                'message' => 'Printer bootstrap loaded.',
                'data' => $rows,
            ], JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public function order_draft()
    {
        $this->require_permission('pos.order.draft.index', 'view');
        $filters = $this->order_draft_filters('UNPAID');
        $this->render('pos/order_draft_index', [
            'page_title' => 'Draft Order POS',
            'active_menu' => 'pos.order.draft.index',
            'workspace_mode' => 'UNPAID',
            'filters' => $filters,
            'filter_options' => $this->Pos_model->order_draft_filter_options(),
        ]);
    }

    public function order_paid()
    {
        $pageCode = $this->order_workspace_page_code('view', 'pos.order.paid.index');
        $this->require_permission($pageCode, 'view');
        $filters = $this->order_draft_filters('PAID');
        $this->render('pos/order_paid_index', [
            'page_title'               => 'Pesanan Terbayar POS',
            'active_menu'              => 'pos.order.paid.index',
            'workspace_mode'           => 'PAID',
            'filters'                  => $filters,
            'filter_options'           => $this->Pos_model->order_draft_filter_options(),
            'payment_method_options'   => $this->Pos_model->deposit_payment_method_options(),
            'can_edit_payment_method'  => $this->can_edit_sales_transaction_payment(),
        ]);
    }

    public function cashier()
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $bootstrap = $this->Pos_model->cashier_bootstrap_options($this->current_actor_employee_id());
        $this->render_cashier('pos/cashier_index', [
            'page_title' => 'Kasir POS',
            'active_menu' => 'pos.cashier.index',
            'filters' => $this->order_draft_filters('MIXED'),
            'filter_options' => $this->Pos_model->order_draft_filter_options(),
            'catalog_filters' => $this->Pos_model->cashier_catalog_filter_options(),
            'cashier_bootstrap' => $bootstrap,
            'active_cashier_session' => $bootstrap['active_session'] ?? null,
        ]);
    }

    public function cashier_open()
    {
        $pageCode = $this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'edit');
        $payload = $this->request_payload();
        $result = $this->Pos_model->open_cashier_session($payload, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal membuka kasir POS.'), 422);
            return;
        }
        $this->json_ok([
            'session' => (array)($result['session'] ?? []),
            'already_open' => !empty($result['already_open']),
        ]);
    }

    public function cashier_close()
    {
        $pageCode = $this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'edit');
        $payload = $this->request_payload();
        $result = $this->Pos_model->close_cashier_session($payload, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menutup kasir POS.'), 422);
            return;
        }

        $shiftId = (int)($result['shift_id'] ?? 0);
        $directPrint = $shiftId > 0
            ? $this->Pos_model->direct_print_targets_for_shift_close($shiftId, (array)($result['report'] ?? []))
            : ['ok' => true, 'targets' => []];

        $this->json_ok([
            'shift_id' => $shiftId,
            'summary' => (array)($result['summary'] ?? []),
            'report' => (array)($result['report'] ?? []),
            'direct_print_targets' => (array)(($directPrint['ok'] ?? false) ? ($directPrint['targets'] ?? []) : []),
            'print_prepare_message' => !($directPrint['ok'] ?? false)
                ? (string)($directPrint['message'] ?? 'Payload direct print tutup kasir gagal disiapkan.')
                : '',
        ]);
    }

    public function cashier_close_preview()
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $result = $this->Pos_model->cashier_close_preview($this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Preview tutup kasir belum tersedia.'), 422);
            return;
        }
        $this->json_ok([
            'shift_id' => (int)($result['shift_id'] ?? 0),
            'session' => (array)($result['session'] ?? []),
            'report' => (array)($result['report'] ?? []),
        ]);
    }

    public function cashier_session_status()
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $this->json_ok([
            'session' => $this->Pos_model->find_active_cashier_session($this->current_actor_employee_id()),
        ]);
    }

    public function order_monitor()
    {
        $pageCode = 'pos.order.monitor.index';
        $this->require_permission($pageCode, 'view');
        $filters = $this->order_monitor_filters();
        $monitorScope = $this->current_actor_monitor_scope();
        $this->Pos_order_monitor_model->bootstrap_open_tasks((int)($filters['outlet_id'] ?? 0));
        $this->render('pos/order_monitor_index', [
            'page_title' => 'Monitor Dapur, Bar & Checker',
            'active_menu' => 'pos.order.monitor',
            'filters' => $filters,
            'monitor_scope' => $monitorScope,
            'station_options' => $this->Pos_order_monitor_model->station_options(),
            'outlet_options' => $this->Pos_order_monitor_model->active_outlets(),
            'payload' => $this->Pos_order_monitor_model->board_payload(
                (string)($filters['station'] ?? 'ALL'),
                (int)($filters['outlet_id'] ?? 0),
                (string)($filters['date_from'] ?? ''),
                (string)($filters['date_to'] ?? ''),
                $monitorScope
            ),
            'poll_ms' => 12000,
        ]);
    }

    public function order_monitor_data()
    {
        $pageCode = 'pos.order.monitor.index';
        $this->require_permission($pageCode, 'view');
        $filters = $this->order_monitor_filters();
        $monitorScope = $this->current_actor_monitor_scope();
        $this->Pos_order_monitor_model->bootstrap_open_tasks((int)($filters['outlet_id'] ?? 0));
        $this->json_ok([
            'payload' => $this->Pos_order_monitor_model->board_payload(
                (string)($filters['station'] ?? 'ALL'),
                (int)($filters['outlet_id'] ?? 0),
                (string)($filters['date_from'] ?? ''),
                (string)($filters['date_to'] ?? ''),
                $monitorScope
            ),
        ]);
    }

    public function order_monitor_ack_task()
    {
        $this->handle_order_monitor_task_action('ack');
    }

    public function order_monitor_ready_task()
    {
        $this->handle_order_monitor_task_action('ready');
    }

    public function order_monitor_checker_task()
    {
        $this->handle_order_monitor_task_action('checker');
    }

    public function order_monitor_ack_order_station()
    {
        $pageCode = 'pos.order.monitor.index';
        $this->require_permission($pageCode, 'edit');
        $payload = $this->request_payload();
        $monitorScope = $this->current_actor_monitor_scope();
        $orderId = max(0, (int)($payload['order_id'] ?? 0));
        $stationRole = strtoupper(trim((string)($payload['station_role'] ?? '')));
        if ($orderId <= 0 || !in_array($stationRole, ['BAR', 'KITCHEN'], true)) {
            $this->json_error('Order atau stasiun monitor tidak valid.', 422);
            return;
        }
        if (!$this->Pos_order_monitor_model->ack_order_station($orderId, $stationRole, $this->current_actor_employee_id(), $monitorScope)) {
            $this->json_error('Task stasiun gagal diterima.', 422);
            return;
        }
        $this->json_ok(['order_id' => $orderId, 'station_role' => $stationRole]);
    }

    public function order_monitor_ready_order_station()
    {
        $pageCode = 'pos.order.monitor.index';
        $this->require_permission($pageCode, 'edit');
        $payload = $this->request_payload();
        $monitorScope = $this->current_actor_monitor_scope();
        $orderId = max(0, (int)($payload['order_id'] ?? 0));
        $stationRole = strtoupper(trim((string)($payload['station_role'] ?? '')));
        if ($orderId <= 0 || !in_array($stationRole, ['BAR', 'KITCHEN'], true)) {
            $this->json_error('Order atau stasiun monitor tidak valid.', 422);
            return;
        }
        if (!$this->Pos_order_monitor_model->ready_order_station($orderId, $stationRole, $this->current_actor_employee_id(), $monitorScope)) {
            $this->json_error('Task stasiun gagal ditandai siap.', 422);
            return;
        }
        $this->json_ok(['order_id' => $orderId, 'station_role' => $stationRole]);
    }

    public function order_monitor_checker_order()
    {
        $pageCode = 'pos.order.monitor.index';
        $this->require_permission($pageCode, 'edit');
        $payload = $this->request_payload();
        $monitorScope = $this->current_actor_monitor_scope();
        $orderId = max(0, (int)($payload['order_id'] ?? 0));
        if ($orderId <= 0) {
            $this->json_error('Order monitor tidak valid.', 422);
            return;
        }
        if (!$this->Pos_order_monitor_model->checker_order($orderId, $this->current_actor_employee_id(), $monitorScope)) {
            $this->json_error('Task checker gagal diselesaikan.', 422);
            return;
        }
        $this->json_ok(['order_id' => $orderId]);
    }

    public function stock_live()
    {
        $pageCode = $this->can('pos.stock.live.index', 'view')
            ? 'pos.stock.live.index'
            : ($this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index');
        $this->require_permission($pageCode, 'view');
        $filters = $this->stock_live_filters();
        $filterOptions = $this->Pos_model->stock_live_filter_options();
        if ((int)($filters['outlet_id'] ?? 0) <= 0 && !empty($filterOptions['outlets'][0]['id'])) {
            $filters['outlet_id'] = (int)$filterOptions['outlets'][0]['id'];
        }
        $this->render('pos/stock_live_index', [
            'page_title' => 'Stock Live POS',
            'active_menu' => 'pos.stock.live.index',
            'filters' => $filters,
            'filter_options' => $filterOptions,
        ]);
    }

    public function stock_live_data()
    {
        $pageCode = $this->can('pos.stock.live.index', 'view')
            ? 'pos.stock.live.index'
            : ($this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index');
        $this->require_permission($pageCode, 'view');
        $filters = $this->stock_live_filters();
        if ((int)($filters['outlet_id'] ?? 0) <= 0) {
            $outlets = $this->Pos_model->local_outlet_options();
            if (!empty($outlets[0]['id'])) {
                $filters['outlet_id'] = (int)$outlets[0]['id'];
            }
        }
        $dataset = $this->Pos_model->stock_live_rows($filters);
        $rows = (array)($dataset['rows'] ?? []);
        $latestLogs = [];
        if ((int)$filters['outlet_id'] > 0) {
            $latestLogs = $this->Pos_model->stock_live_latest_log_map((int)$filters['outlet_id'], array_column($rows, 'id'));
        }

        $this->load->library('PosAvailabilityRebuildService');
        $resultRows = [];
        foreach ($rows as $row) {
            $productId = (int)($row['id'] ?? 0);
            $live = $filters['outlet_id'] > 0
                ? $this->posavailabilityrebuildservice->resolve_live_availability((int)$filters['outlet_id'], $productId, [
                    'trigger_context' => 'PAGE_LIST',
                    'actor_employee_id' => $this->current_actor_employee_id(),
                ])
                : ['ok' => false, 'message' => 'Outlet belum dipilih.'];

            $comparison = ($live['ok'] ?? false)
                ? $this->posavailabilityrebuildservice->compare_cache_snapshot([
                    'availability_status' => (string)($row['cache_availability_status'] ?? ''),
                    'estimated_available_qty' => (float)($row['cache_estimated_available_qty'] ?? 0),
                    'bottleneck_name_snapshot' => (string)($row['cache_bottleneck_name_snapshot'] ?? ''),
                    'hpp_live_snapshot' => (float)($row['cache_hpp_live_snapshot'] ?? 0),
                    'is_dirty' => (int)($row['cache_is_dirty'] ?? 0),
                ], $live)
                : ['mismatch_flag' => 0, 'note' => 'Outlet belum dipilih.'];

            $resultRows[] = [
                'product_id' => $productId,
                'product_code' => (string)($row['product_code'] ?? ''),
                'product_name' => (string)($row['product_name'] ?? ''),
                'product_division_id' => (int)($row['product_division_id'] ?? 0),
                'default_operational_division_id' => (int)($row['default_operational_division_id'] ?? 0),
                'product_division_name' => (string)($row['product_division_name'] ?? ''),
                'classification_name' => (string)($row['classification_name'] ?? ''),
                'product_category_name' => (string)($row['product_category_name'] ?? ''),
                'selling_price' => (float)($row['selling_price'] ?? 0),
                'cache' => [
                    'status' => (string)($row['cache_availability_status'] ?? ''),
                    'qty' => (float)($row['cache_estimated_available_qty'] ?? 0),
                    'bottleneck' => (string)($row['cache_bottleneck_name_snapshot'] ?? ''),
                    'hpp' => (float)($row['cache_hpp_live_snapshot'] ?? 0),
                    'source_mode' => (string)($row['cache_source_mode'] ?? ''),
                    'computed_at' => (string)($row['cache_computed_at'] ?? ''),
                    'last_commit_event' => (string)($row['cache_last_commit_event'] ?? ''),
                    'is_dirty' => (int)($row['cache_is_dirty'] ?? 0),
                ],
                'live' => ($live['ok'] ?? false) ? [
                    'status' => (string)($live['availability_status'] ?? ''),
                    'qty' => (float)($live['estimated_available_qty'] ?? 0),
                    'bottleneck' => (string)($live['bottleneck_name_snapshot'] ?? ''),
                    'hpp' => (float)($live['hpp_live_snapshot'] ?? 0),
                    'override_allowed' => (int)($live['override_allowed'] ?? 0),
                    'line_count' => count((array)($live['lines'] ?? [])),
                ] : null,
                'comparison' => (array)$comparison,
                'live_error' => !($live['ok'] ?? false) ? (string)($live['message'] ?? 'Gagal hitung live.') : '',
                'latest_log' => (array)($latestLogs[$productId] ?? []),
            ];
        }

        if (!empty($filters['mismatch_only'])) {
            $resultRows = array_values(array_filter($resultRows, static function (array $row): bool {
                return !empty($row['comparison']['mismatch_flag']);
            }));
        }

        $this->json_ok([
            'rows' => $resultRows,
            'meta' => (array)($dataset['meta'] ?? []),
        ]);
    }

    public function stock_live_probe()
    {
        $pageCode = $this->can('pos.stock.live.index', 'view')
            ? 'pos.stock.live.index'
            : ($this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index');
        $this->require_permission($pageCode, 'view');
        $outletId = max(0, (int)$this->input->get('outlet_id', true));
        $productId = max(0, (int)$this->input->get('product_id', true));
        $this->load->library('PosAvailabilityRebuildService');
        $result = $this->posavailabilityrebuildservice->probe_compare($outletId, $productId, [
            'trigger_context' => 'MANUAL_PROBE',
            'actor_employee_id' => $this->current_actor_employee_id(),
        ]);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menjalankan probe stock live.'), 422);
            return;
        }
        $this->json_ok($result);
    }

    public function stock_live_rebuild_all()
    {
        $pageCode = $this->can('pos.stock.live.index', 'edit')
            ? 'pos.stock.live.index'
            : ($this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index');
        $this->require_permission($pageCode, 'edit');
        $payload = $this->request_payload();
        $outletId = max(0, (int)($payload['outlet_id'] ?? 0));
        $divisionId = max(0, (int)($payload['division_id'] ?? 0));
        $this->load->library('PosAvailabilityRebuildService');
        $result = $this->posavailabilityrebuildservice->rebuild_all_products($outletId, [
            'division_id' => $divisionId,
        ], [
            'trigger_context' => 'MANUAL_REBUILD_ALL',
            'event_source' => 'MANUAL_REBUILD_ALL',
            'event_table' => 'mst_product_recipe',
            'event_id' => null,
            'actor_employee_id' => $this->current_actor_employee_id(),
        ]);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal rebuild total stock live POS.'), 422);
            return;
        }
        $this->json_ok($result);
    }

    public function stock_live_rebuild()
    {
        $pageCode = $this->can('pos.stock.live.index', 'edit')
            ? 'pos.stock.live.index'
            : ($this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index');
        $this->require_permission($pageCode, 'edit');
        $payload = $this->request_payload();
        $outletId = max(0, (int)($payload['outlet_id'] ?? 0));
        $productId = max(0, (int)($payload['product_id'] ?? 0));
        $this->load->library('PosAvailabilityRebuildService');
        $result = $this->posavailabilityrebuildservice->rebuild_product($outletId, $productId, [
            'trigger_context' => 'MANUAL_REBUILD',
            'event_source' => 'MANUAL_REBUILD',
            'event_table' => 'pos_product_availability_cache',
            'event_id' => $productId,
            'actor_employee_id' => $this->current_actor_employee_id(),
        ]);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal rebuild stock live POS.'), 422);
            return;
        }
        $this->json_ok($result);
    }

    public function order_draft_data()
    {
        $pageCode = $this->order_workspace_page_code('view');
        $this->require_permission($pageCode, 'view');
        $workspaceMode = strtoupper(trim((string)$this->input->get('workspace_mode', true)));
        if (!in_array($workspaceMode, ['UNPAID', 'PAID', 'MIXED'], true)) {
            $workspaceMode = 'MIXED';
        }
        $this->json_ok($this->Pos_model->order_draft_rows($this->order_draft_filters($workspaceMode)));
    }

    public function order_draft_load($id)
    {
        $pageCode = $this->order_workspace_page_code('view');
        $this->require_permission($pageCode, 'view');
        $result = $this->Pos_model->find_order_draft((int)$id);
        if (!$result) {
            $this->json_error('Draft order tidak ditemukan.', 404);
            return;
        }
        $this->json_ok($result + [
            'payments' => $this->Pos_report_model->order_payment_rows((int)$id),
            'refunds' => $this->Pos_report_model->order_refund_rows((int)$id),
            'voids' => $this->Pos_report_model->order_void_rows((int)$id),
        ]);
    }

    public function order_draft_delete($id)
    {
        $pageCode = $this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'edit');
        $result = $this->Pos_model->delete_order_draft((int)$id, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menghapus draft order POS.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)$id,
        ]);
    }

    public function order_draft_member_search()
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $q = trim((string)$this->input->get('q', true));
        $limit = max(1, min(20, (int)$this->input->get('limit', true) ?: 8));
        $this->json_ok([
            'rows' => $this->Pos_model->order_member_search($q, $limit),
        ]);
    }

    public function order_draft_product_search()
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $q = trim((string)$this->input->get('q', true));
        $outletId = max(0, (int)$this->input->get('outlet_id', true));
        $limit = max(1, min(30, (int)$this->input->get('limit', true) ?: 12));
        $this->json_ok([
            'rows' => $this->Pos_model->order_product_search($q, $outletId, $limit),
        ]);
    }

    public function cashier_catalog()
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'division_id' => max(0, (int)$this->input->get('division_id', true)),
            'category_id' => max(0, (int)$this->input->get('category_id', true)),
            'limit' => max(1, min(120, (int)$this->input->get('limit', true) ?: 32)),
        ];
        $this->json_ok([
            'rows' => $this->Pos_model->order_product_catalog($filters),
        ]);
    }

    public function cashier_bundle_catalog()
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'division_id' => max(0, (int)$this->input->get('division_id', true)),
            'limit' => max(1, min(60, (int)$this->input->get('limit', true) ?: 24)),
        ];
        $this->json_ok([
            'rows' => $this->Pos_model->order_bundle_catalog($filters),
        ]);
    }

    public function order_draft_bundle_search()
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $q = trim((string)$this->input->get('q', true));
        $outletId = max(0, (int)$this->input->get('outlet_id', true));
        $limit = max(1, min(20, (int)$this->input->get('limit', true) ?: 8));
        $this->json_ok([
            'rows' => $this->Pos_model->order_bundle_search($q, $outletId, $limit),
        ]);
    }

    public function order_draft_extra_options()
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $productId = max(0, (int)$this->input->get('product_id', true));
        $this->json_ok([
            'groups' => $this->Pos_model->order_extra_options($productId),
        ]);
    }

    public function order_draft_save()
    {
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $pageCode = $this->can('pos.cashier.index', $id > 0 ? 'edit' : 'create') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, $id > 0 ? 'edit' : 'create');
        $result = $this->Pos_model->save_order_draft($payload, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan draft order POS.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)$result['id'],
            'order_no' => (string)($result['order_no'] ?? ''),
        ]);
    }

    public function order_draft_confirm($id)
    {
        $pageCode = $this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'edit');
        $actorEmployeeId = $this->current_actor_employee_id();
        $orderId = (int)$id;
        $this->confirm_order_and_respond($orderId, $actorEmployeeId);
    }

    public function order_draft_save_confirm()
    {
        $pageCode = $this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'edit');
        $actorEmployeeId = $this->current_actor_employee_id();
        $payload = $this->request_payload();
        $saved = $this->Pos_model->save_order_draft($payload, $actorEmployeeId);
        if (!($saved['ok'] ?? false)) {
            $this->json_error((string)($saved['message'] ?? 'Gagal menyimpan draft order POS.'), 422);
            return;
        }
        $orderId = (int)($saved['id'] ?? 0);
        if ($orderId <= 0) {
            $this->json_error('Draft order tersimpan tetapi ID order tidak valid.', 422);
            return;
        }
        $this->confirm_order_and_respond($orderId, $actorEmployeeId, [
            'append_mode' => !empty($saved['append_mode']),
            'header_only_update' => !empty($saved['header_only_update']),
            'line_ids' => (array)($saved['appended_line_ids'] ?? []),
            'appended_line_count' => (int)($saved['appended_line_count'] ?? 0),
        ]);
    }

    private function confirm_order_and_respond(int $orderId, int $actorEmployeeId, array $options = []): void
    {
        $this->load->library('PosStockCommitService');
        $this->load->library('PosRuntimeJobService');

        $appendMode = !empty($options['append_mode']);
        $headerOnlyUpdate = !empty($options['header_only_update']);
        $lineIds = array_values(array_unique(array_filter(array_map('intval', (array)($options['line_ids'] ?? [])))));
        $appendedLineCount = (int)($options['appended_line_count'] ?? count($lineIds));

        if ($appendMode && $headerOnlyUpdate && empty($lineIds)) {
            $this->json_ok([
                'id' => $orderId,
                'snapshot_id' => 0,
                'commit_no' => '',
                'resolved_line_count' => 0,
                'runtime_job_id' => 0,
                'runtime_job_code' => '',
                'print_job_count' => 0,
                'runtime_kickoff' => [
                    'ok' => false,
                    'mode' => 'not_required',
                    'message' => 'Tidak ada item baru. Sistem hanya menyimpan perubahan header transaksi POS.',
                ],
                'stock_sync' => [
                    'queued' => false,
                    'status' => 'NOT_REQUIRED',
                    'kickoff_ok' => false,
                ],
                'stock_commit_status' => 'NOT_REQUIRED',
                'append_mode' => true,
                'appended_line_count' => 0,
                'header_only_update' => true,
            ]);
            return;
        }

        $resolved = $this->Pos_model->resolve_order_stock_commit_payload($orderId, $actorEmployeeId, [
            'line_ids' => $lineIds,
        ]);
        if (!($resolved['ok'] ?? false)) {
            $this->json_error((string)($resolved['message'] ?? 'Gagal menyiapkan stock commit order POS.'), 422);
            return;
        }
        $warningMessage = trim((string)($resolved['warning_message'] ?? ''));

        if (empty($resolved['lines'])) {
            $finalize = $this->Pos_model->finalize_order_confirmation($orderId, 0, $actorEmployeeId, 'NOT_REQUIRED');
            if (!($finalize['ok'] ?? false)) {
                $this->json_error((string)($finalize['message'] ?? 'Order POS gagal difinalkan.'), 422);
                return;
            }
            $this->Pos_order_monitor_model->sync_order_tasks($orderId);
            $this->json_ok([
                'id' => $orderId,
                'snapshot_id' => 0,
                'commit_no' => '',
                'resolved_line_count' => 0,
                'runtime_job_id' => 0,
                'runtime_job_code' => '',
                'print_job_count' => 0,
                'runtime_kickoff' => [
                    'ok' => false,
                    'mode' => 'not_required',
                    'message' => 'Stock commit dilewati karena item yang dikonfirmasi belum memiliki recipe product.',
                ],
                'stock_sync' => [
                    'queued' => false,
                    'status' => 'NOT_REQUIRED',
                    'kickoff_ok' => false,
                ],
                'stock_commit_status' => 'NOT_REQUIRED',
                'append_mode' => $appendMode,
                'appended_line_count' => $appendedLineCount,
                'header_only_update' => false,
                'warning_message' => $warningMessage,
            ]);
            return;
        }

        $snapshot = $this->posstockcommitservice->create_snapshot($orderId, (array)($resolved['header'] ?? []), (array)($resolved['lines'] ?? []));
        if (!($snapshot['ok'] ?? false)) {
            $this->json_error((string)($snapshot['message'] ?? 'Gagal membuat snapshot stock commit.'), 422);
            return;
        }

        $queued = $this->posruntimejobservice->queue_order_confirm_commit($orderId, (int)$snapshot['id'], $actorEmployeeId, [
            'event_source' => 'ORDER_CONFIRM',
            'event_id' => $orderId,
        ]);
        if (!($queued['ok'] ?? false)) {
            $this->json_error((string)($queued['message'] ?? 'Snapshot berhasil, tetapi queue runtime POS gagal dibuat.'), 422, [
                'snapshot_id' => (int)$snapshot['id'],
                'commit_no' => (string)($snapshot['commit_no'] ?? ''),
            ]);
            return;
        }

        $markQueued = $this->posstockcommitservice->mark_queued((int)$snapshot['id']);
        if (!($markQueued['ok'] ?? false)) {
            $this->posruntimejobservice->cancel_job((int)($queued['job_id'] ?? 0), 'Snapshot stock commit gagal ditandai queued.');
            $this->json_error((string)($markQueued['message'] ?? 'Gagal menandai stock commit sebagai queued.'), 422);
            return;
        }

        $finalize = $this->Pos_model->finalize_order_confirmation($orderId, (int)$snapshot['id'], $actorEmployeeId, 'QUEUED');
        if (!($finalize['ok'] ?? false)) {
            $this->posruntimejobservice->cancel_job((int)($queued['job_id'] ?? 0), 'Order POS gagal difinalkan setelah queue dibuat.');
            $this->json_error((string)($finalize['message'] ?? 'Snapshot berhasil, tetapi order gagal difinalkan.'), 422, [
                'snapshot_id' => (int)$snapshot['id'],
                'commit_no' => (string)($snapshot['commit_no'] ?? ''),
            ]);
            return;
        }

        $this->Pos_order_monitor_model->sync_order_tasks($orderId);

        $kickoff = [
            'ok' => false,
            'mode' => 'client_trigger_required',
            'message' => 'Queue stok POS akan dipicu dari client setelah response confirm diterima.',
        ];
        if (function_exists('fastcgi_finish_request')) {
            $kickoff = $this->schedule_runtime_job_processing($orderId, (int)($queued['job_id'] ?? 0), 1);
        }

        $this->json_ok([
            'id' => $orderId,
            'snapshot_id' => (int)$snapshot['id'],
            'commit_no' => (string)($snapshot['commit_no'] ?? ''),
            'resolved_line_count' => (int)($resolved['resolved_line_count'] ?? 0),
            'runtime_job_id' => (int)($queued['job_id'] ?? 0),
            'runtime_job_code' => (string)($queued['job_code'] ?? ''),
            'print_job_count' => 0,
            'runtime_kickoff' => $kickoff,
            'stock_sync' => [
                'queued' => true,
                'status' => 'QUEUED',
                'kickoff_ok' => !empty($kickoff['ok']),
            ],
            'stock_commit_status' => 'QUEUED',
            'append_mode' => $appendMode,
            'appended_line_count' => $appendedLineCount,
            'header_only_update' => false,
            'warning_message' => $warningMessage,
        ]);
    }

    public function order_confirm_print_targets($id)
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $payload = $this->request_payload();
        $snapshotId = (int)($payload['snapshot_id'] ?? 0);
        $directPrint = $this->Pos_model->direct_print_targets_for_order_confirm((int)$id, $snapshotId);
        if (!($directPrint['ok'] ?? false)) {
            $this->json_error((string)($directPrint['message'] ?? 'Payload direct print gagal disiapkan.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)$id,
            'snapshot_id' => $snapshotId,
            'direct_print_targets' => (array)($directPrint['targets'] ?? []),
        ]);
    }

    public function order_reprint_print_targets($id)
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $payload = $this->request_payload();
        $lineScope = strtoupper(trim((string)($payload['line_scope'] ?? 'ALL')));
        $printerId = (int)($payload['printer_id'] ?? 0);
        $directPrint = $this->Pos_model->direct_print_targets_for_order_reprint((int)$id, [
            'line_scope' => $lineScope,
            'printer_id' => $printerId,
        ]);
        if (!($directPrint['ok'] ?? false)) {
            $this->json_error((string)($directPrint['message'] ?? 'Payload cetak ulang order gagal disiapkan.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)$id,
            'line_scope' => $lineScope,
            'printer_id' => $printerId,
            'direct_print_targets' => (array)($directPrint['targets'] ?? []),
        ]);
    }

    public function order_void_print_targets($id)
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $directPrint = $this->Pos_model->direct_print_targets_for_void((int)$id);
        if (!($directPrint['ok'] ?? false)) {
            $this->json_error((string)($directPrint['message'] ?? 'Payload direct print void gagal disiapkan.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)$id,
            'direct_print_targets' => (array)($directPrint['targets'] ?? []),
        ]);
    }

    public function order_refund_print_targets($id)
    {
        $pageCode = $this->can('pos.order.paid.index', 'view') ? 'pos.order.paid.index' : 'pos.cashier.index';
        $this->require_permission($pageCode, 'view');
        $directPrint = $this->Pos_model->direct_print_targets_for_refund((int)$id);
        if (!($directPrint['ok'] ?? false)) {
            $this->json_error((string)($directPrint['message'] ?? 'Payload direct print refund gagal disiapkan.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)$id,
            'direct_print_targets' => (array)($directPrint['targets'] ?? []),
        ]);
    }

    public function order_runtime_sync($id)
    {
        $pageCode = $this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'edit');
        $payload = $this->request_payload();
        $eventSource = strtoupper(trim((string)($payload['event_source'] ?? 'ORDER_CONFIRM')));
        if (!in_array($eventSource, ['ORDER_CONFIRM', 'ORDER_VOID', 'ORDER_REFUND'], true)) {
            $eventSource = 'ORDER_CONFIRM';
        }
        $eventId = max(0, (int)($payload['event_id'] ?? 0));
        $result = $this->trigger_stock_live_refresh_for_order((int)$id, $eventSource, $eventId ?: (int)$id, true);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Sinkronisasi stok background gagal dijalankan.'), 422, [
                'success_count' => (int)($result['success_count'] ?? 0),
                'failed_count' => (int)($result['failed_count'] ?? 0),
            ]);
            return;
        }
        $this->json_ok([
            'id' => (int)$id,
            'event_source' => $eventSource,
            'success_count' => (int)($result['success_count'] ?? 0),
            'failed_count' => (int)($result['failed_count'] ?? 0),
            'marked_product_count' => (int)($result['marked_product_count'] ?? 0),
        ]);
    }

    public function order_runtime_job_trigger($id)
    {
        $pageCode = $this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'edit');
        $payload = $this->request_payload();
        $this->load->library('PosRuntimeJobService');

        $orderId = (int)$id;
        $jobId = max(0, (int)($payload['job_id'] ?? 0));
        $limit = max(1, min(5, (int)($payload['limit'] ?? 1)));
        $processed = $this->process_runtime_job_now($orderId, $jobId, $limit);
        $latest = $this->posruntimejobservice->latest_job_for_order($orderId);

        if (!($processed['ok'] ?? false)) {
            $this->json_error((string)($processed['message'] ?? 'Job runtime POS gagal diproses.'), 422, [
                'job' => (array)($latest['job'] ?? []),
                'result' => $processed,
            ]);
            return;
        }

        $this->json_ok([
            'id' => $orderId,
            'job' => (array)($latest['job'] ?? []),
            'processed_count' => (int)($processed['processed_count'] ?? 0),
            'success_count' => (int)($processed['success_count'] ?? 0),
            'failed_count' => (int)($processed['failed_count'] ?? 0),
            'jobs' => (array)($processed['jobs'] ?? []),
        ]);
    }

    public function order_runtime_job_status($id)
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $this->load->library('PosRuntimeJobService');

        $latest = $this->posruntimejobservice->latest_job_for_order((int)$id);
        if (!($latest['ok'] ?? false)) {
            $this->json_error((string)($latest['message'] ?? 'Status queue runtime POS belum tersedia.'), 404);
            return;
        }

        $this->json_ok([
            'id' => (int)$id,
            'job' => (array)($latest['job'] ?? []),
        ]);
    }

    public function order_runtime_failed_jobs()
    {
        $pageCode = $this->can('pos.stock.live.index', 'view')
            ? 'pos.stock.live.index'
            : ($this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index');
        $this->require_permission($pageCode, 'view');
        $this->load->library('PosRuntimeJobService');

        $result = $this->posruntimejobservice->failed_jobs([
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'q' => trim((string)$this->input->get('q', true)),
            'limit' => max(1, min(100, (int)$this->input->get('limit', true) ?: 20)),
        ]);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Data job gagal POS belum tersedia.'), 422);
            return;
        }

        $this->json_ok([
            'rows' => (array)($result['rows'] ?? []),
        ]);
    }

    public function order_runtime_active_jobs()
    {
        $pageCode = $this->can('pos.stock.live.index', 'view')
            ? 'pos.stock.live.index'
            : ($this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index');
        $this->require_permission($pageCode, 'view');
        $this->load->library('PosRuntimeJobService');

        $result = $this->posruntimejobservice->active_jobs([
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'q' => trim((string)$this->input->get('q', true)),
            'limit' => max(1, min(100, (int)$this->input->get('limit', true) ?: 20)),
        ]);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Data job pending POS belum tersedia.'), 422);
            return;
        }

        $this->json_ok([
            'rows' => (array)($result['rows'] ?? []),
        ]);
    }

    public function order_runtime_job_retry($jobId)
    {
        $pageCode = $this->can('pos.stock.live.index', 'edit')
            ? 'pos.stock.live.index'
            : ($this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index');
        $this->require_permission($pageCode, 'edit');
        $this->load->library('PosRuntimeJobService');

        $retried = $this->posruntimejobservice->retry_job((int)$jobId, $this->current_actor_employee_id());
        if (!($retried['ok'] ?? false)) {
            $this->json_error((string)($retried['message'] ?? 'Job runtime POS gagal di-retry.'), 422);
            return;
        }

        $job = (array)($retried['job'] ?? []);
        $processed = $this->process_runtime_job_now((int)($job['order_id'] ?? 0), (int)($job['id'] ?? 0), 1);
        if (!($processed['ok'] ?? false)) {
            $this->json_error((string)($processed['message'] ?? 'Job retry POS gagal diproses.'), 422, [
                'job' => $job,
                'result' => $processed,
            ]);
            return;
        }

        $latest = $this->posruntimejobservice->latest_job_for_order((int)($job['order_id'] ?? 0));
        $this->json_ok([
            'job' => (array)($latest['job'] ?? $job),
            'processed_count' => (int)($processed['processed_count'] ?? 0),
            'success_count' => (int)($processed['success_count'] ?? 0),
            'failed_count' => (int)($processed['failed_count'] ?? 0),
            'jobs' => (array)($processed['jobs'] ?? []),
        ]);
    }

    public function order_runtime_jobs_process_all()
    {
        $pageCode = $this->can('pos.stock.live.index', 'edit')
            ? 'pos.stock.live.index'
            : ($this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index');
        $this->require_permission($pageCode, 'edit');

        $payload = $this->request_payload();
        $outletId = max(0, (int)($payload['outlet_id'] ?? 0));
        $limit = max(1, min(25, (int)($payload['limit'] ?? 10)));
        if ($outletId <= 0) {
            $this->json_error('Pilih outlet dulu sebelum memproses semua pending queue POS.', 422);
            return;
        }

        $this->load->library('PosRuntimeJobService');
        $processed = $this->posruntimejobservice->process_pending_jobs([
            'limit' => $limit,
            'outlet_id' => $outletId,
        ]);
        if (!($processed['ok'] ?? false)) {
            $this->json_error((string)($processed['message'] ?? 'Pending queue POS gagal diproses.'), 422, [
                'result' => $processed,
            ]);
            return;
        }

        $this->json_ok([
            'processed_count' => (int)($processed['processed_count'] ?? 0),
            'success_count' => (int)($processed['success_count'] ?? 0),
            'failed_count' => (int)($processed['failed_count'] ?? 0),
            'jobs' => (array)($processed['jobs'] ?? []),
            'outlet_id' => $outletId,
        ]);
    }

    public function order_runtime_failed_jobs_retry_all()
    {
        @set_time_limit(0);
        $pageCode = $this->can('pos.stock.live.index', 'edit')
            ? 'pos.stock.live.index'
            : ($this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index');
        $this->require_permission($pageCode, 'edit');
        $this->load->library('PosRuntimeJobService');

        $payload = $this->request_payload();
        $limit = max(1, min(25, (int)($payload['limit'] ?? 10)));
        $failed = $this->posruntimejobservice->failed_jobs([
            'limit' => $limit,
            'outlet_id' => max(0, (int)($payload['outlet_id'] ?? 0)),
            'q' => trim((string)($payload['q'] ?? '')),
        ]);
        if (!($failed['ok'] ?? false)) {
            $this->json_error((string)($failed['message'] ?? 'Data job gagal POS belum tersedia.'), 422);
            return;
        }

        $rows = array_values((array)($failed['rows'] ?? []));
        if (empty($rows)) {
            $this->json_ok([
                'message' => 'Belum ada job gagal yang bisa di-retry.',
                'processed_count' => 0,
                'success_count' => 0,
                'failed_count' => 0,
                'jobs' => [],
            ]);
            return;
        }

        $results = [];
        $successCount = 0;
        $failedCount = 0;

        foreach ($rows as $job) {
            $jobId = (int)($job['id'] ?? 0);
            if ($jobId <= 0) {
                continue;
            }

            $retried = $this->posruntimejobservice->retry_job($jobId, $this->current_actor_employee_id());
            if (!($retried['ok'] ?? false)) {
                $failedCount++;
                $results[] = [
                    'job_id' => $jobId,
                    'order_no' => (string)($job['order_no'] ?? ''),
                    'ok' => false,
                    'message' => (string)($retried['message'] ?? 'Retry job gagal.'),
                ];
                continue;
            }

            $latestJob = (array)($retried['job'] ?? []);
            $processed = $this->process_runtime_job_now((int)($latestJob['order_id'] ?? 0), (int)($latestJob['id'] ?? $jobId), 1);
            if (!($processed['ok'] ?? false)) {
                $failedCount++;
                $results[] = [
                    'job_id' => $jobId,
                    'order_no' => (string)($job['order_no'] ?? ''),
                    'ok' => false,
                    'message' => (string)($processed['message'] ?? 'Retry job gagal diproses.'),
                ];
                continue;
            }

            $successCount++;
            $results[] = [
                'job_id' => $jobId,
                'order_no' => (string)($job['order_no'] ?? ''),
                'ok' => true,
                'message' => 'Job berhasil diantrekan ulang dan diproses.',
            ];
        }

        $this->json_ok([
            'message' => 'Retry massal job gagal selesai diproses.',
            'processed_count' => count($results),
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'jobs' => $results,
        ]);
    }

    public function runtime_jobs_run($limit = 5, $orderId = 0, $jobId = 0)
    {
        if (!$this->input->is_cli_request()) {
            show_404();
            return;
        }

        $options = [
            'limit' => max(1, min(50, (int)$limit)),
            'order_id' => max(0, (int)$orderId),
            'job_id' => max(0, (int)$jobId),
        ];

        $this->load->library('PosRuntimeJobService');
        $result = $this->posruntimejobservice->process_pending_jobs($options);
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }

    private function spawn_runtime_job_worker(int $limit = 1, int $orderId = 0, int $jobId = 0): array
    {
        $phpBinary = $this->resolve_cli_php_binary();
        $indexPath = realpath(FCPATH . 'index.php');
        if ($phpBinary === null || $indexPath === false) {
            return ['ok' => false, 'message' => 'PHP CLI atau front controller Finance tidak ditemukan untuk menjalankan worker background POS.'];
        }

        if (function_exists('session_write_close')) {
            @session_write_close();
        }

        $command = 'start /B "" ' . $this->escape_windows_arg($phpBinary)
            . ' ' . $this->escape_windows_arg($indexPath)
            . ' pos runtime_jobs_run '
            . (int)max(1, min(50, $limit)) . ' '
            . (int)max(0, $orderId) . ' '
            . (int)max(0, $jobId)
            . ' >NUL 2>&1';

        @pclose(@popen($command, 'r'));

        return [
            'ok' => true,
            'mode' => 'async_cli_spawn',
        ];
    }

    private function process_runtime_job_now(int $orderId, int $jobId, int $limit = 1): array
    {
        $this->load->library('PosRuntimeJobService');
        return $this->posruntimejobservice->process_pending_jobs([
            'limit' => max(1, min(5, $limit)),
            'order_id' => max(0, $orderId),
            'job_id' => max(0, $jobId),
        ]);
    }

    private function schedule_runtime_job_processing(int $orderId, int $jobId, int $limit = 1): array
    {
        if (!function_exists('fastcgi_finish_request')) {
            return [
                'ok' => false,
                'mode' => 'client_trigger_required',
                'message' => 'FastCGI post-response worker tidak tersedia di environment ini.',
            ];
        }

        $safeOrderId = max(0, $orderId);
        $safeJobId = max(0, $jobId);
        $safeLimit = max(1, min(5, $limit));
        if ($safeOrderId <= 0 || $safeJobId <= 0) {
            return ['ok' => false, 'message' => 'Order/job runtime POS tidak valid untuk diproses background.'];
        }

        if (function_exists('session_write_close')) {
            @session_write_close();
        }
        @ignore_user_abort(true);

        register_shutdown_function(function () use ($safeOrderId, $safeJobId, $safeLimit): void {
            try {
                if (function_exists('fastcgi_finish_request')) {
                    @fastcgi_finish_request();
                }
                $CI =& get_instance();
                if (!$CI) {
                    return;
                }
                $CI->load->library('PosRuntimeJobService');
                $CI->posruntimejobservice->process_pending_jobs([
                    'limit' => $safeLimit,
                    'order_id' => $safeOrderId,
                    'job_id' => $safeJobId,
                ]);
            } catch (Throwable $e) {
                log_message('error', 'POS runtime post-response processing failed for order ' . $safeOrderId . ' job ' . $safeJobId . ': ' . $e->getMessage());
            }
        });

        return [
            'ok' => true,
            'mode' => 'fastcgi_post_response',
        ];
    }

    public function order_reversal_preview($id)
    {
        $pageCode = $this->order_workspace_page_code('view');
        $this->require_permission($pageCode, 'view');
        $result = $this->Pos_model->order_reversal_preview((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyiapkan preview void/refund POS.'), 422);
            return;
        }
        $this->json_ok($result);
    }

    public function order_payment_prepare($id)
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : $this->order_workspace_page_code('view');
        $this->require_permission($pageCode, 'view');
        $result = $this->Pos_model->cashier_payment_prepare((int)$id, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyiapkan pembayaran POS.'), 422);
            return;
        }
        $this->json_ok($result);
    }

    public function order_payment_voucher_search()
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : $this->order_workspace_page_code('view');
        $this->require_permission($pageCode, 'view');
        $orderId = (int)$this->input->get('order_id', true);
        $q = trim((string)$this->input->get('q', true));
        $limit = max(1, min(12, (int)$this->input->get('limit', true)));
        $result = $this->Pos_model->search_cashier_vouchers($orderId, $this->current_actor_employee_id(), $q, $limit);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal memeriksa voucher pembayaran POS.'), 422, [
                'rows' => (array)($result['rows'] ?? []),
            ]);
            return;
        }
        $this->json_ok([
            'rows' => (array)($result['rows'] ?? []),
        ]);
    }

    public function order_payment_save()
    {
        $pageCode = $this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : $this->order_workspace_page_code('edit');
        $this->require_permission($pageCode, 'edit');
        $payload = $this->request_payload();
        $result = $this->Pos_model->save_cashier_payment($payload, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan pembayaran POS.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)($result['id'] ?? 0),
            'payment_no' => (string)($result['payment_no'] ?? ''),
            'order_status' => (string)($result['order_status'] ?? 'PAID'),
            'paid_now' => (float)($result['paid_now'] ?? 0),
            'entered_now' => (float)($result['entered_now'] ?? 0),
            'deposit_applied_amount' => (float)($result['deposit_applied_amount'] ?? 0),
            'change_total' => (float)($result['change_total'] ?? 0),
            'remaining_due' => (float)($result['remaining_due'] ?? 0),
            'loyalty' => (array)($result['loyalty'] ?? []),
        ]);
    }

    public function order_payment_print_targets($id)
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : $this->order_workspace_page_code('view');
        $this->require_permission($pageCode, 'view');
        $directPrint = $this->Pos_model->direct_print_targets_for_payment((int)$id);
        if (!($directPrint['ok'] ?? false)) {
            $this->json_error((string)($directPrint['message'] ?? 'Payload direct print payment gagal disiapkan.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)$id,
            'direct_print_targets' => (array)($directPrint['targets'] ?? []),
        ]);
    }

    public function order_receipt_print_targets($id)
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : $this->order_workspace_page_code('view');
        $this->require_permission($pageCode, 'view');

        $paymentId = $this->Pos_model->final_payment_id_for_order((int)$id);
        if ($paymentId <= 0) {
            $this->json_error('Struk belum bisa dicetak ulang karena payment final belum ada.', 422);
            return;
        }

        $directPrint = $this->Pos_model->direct_print_targets_for_payment($paymentId, false);
        if (!($directPrint['ok'] ?? false)) {
            $this->json_error((string)($directPrint['message'] ?? 'Payload direct print payment gagal disiapkan.'), 422);
            return;
        }

        $this->json_ok([
            'id' => (int)$id,
            'payment_id' => $paymentId,
            'direct_print_targets' => (array)($directPrint['targets'] ?? []),
        ]);
    }

    public function order_void_save()
    {
        $pageCode = $this->order_workspace_page_code('edit');
        $this->require_permission($pageCode, 'edit');
        $payload = $this->request_payload();
        $result = $this->Pos_model->save_order_void($payload, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan void POS.'), 422);
            return;
        }
        $this->Pos_order_monitor_model->sync_order_tasks((int)($payload['order_id'] ?? 0));
        $availabilityRefresh = $this->trigger_stock_live_refresh_for_order((int)($payload['order_id'] ?? 0), 'ORDER_VOID', (int)($result['id'] ?? 0), false);
        $this->json_ok([
            'id' => (int)($result['id'] ?? 0),
            'void_no' => (string)($result['void_no'] ?? ''),
            'order_status' => (string)($result['order_status'] ?? ''),
            'availability_rebuild' => [
                'success_count' => (int)($availabilityRefresh['success_count'] ?? 0),
                'failed_count' => (int)($availabilityRefresh['failed_count'] ?? 0),
            ],
        ]);
    }

    public function order_refund_save()
    {
        $pageCode = $this->order_workspace_page_code('edit', 'pos.order.paid.index');
        $this->require_permission($pageCode, 'edit');
        $payload = $this->request_payload();
        $result = $this->Pos_model->save_order_refund($payload, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan refund POS.'), 422);
            return;
        }
        $this->Pos_order_monitor_model->sync_order_tasks((int)($payload['order_id'] ?? 0));
        $availabilityRefresh = $this->trigger_stock_live_refresh_for_order((int)($payload['order_id'] ?? 0), 'ORDER_REFUND', (int)($result['id'] ?? 0), false);
        $this->json_ok([
            'id' => (int)($result['id'] ?? 0),
            'refund_no' => (string)($result['refund_no'] ?? ''),
            'order_status' => (string)($result['order_status'] ?? ''),
            'availability_rebuild' => [
                'success_count' => (int)($availabilityRefresh['success_count'] ?? 0),
                'failed_count' => (int)($availabilityRefresh['failed_count'] ?? 0),
            ],
        ]);
    }

    public function report_sales()
    {
        $pageCode = $this->report_view_page_code('pos.report.sales.index');
        $this->require_permission($pageCode, 'view');
        $filters = $this->sales_report_filters();
        $dataset = $this->Pos_report_model->sales_summary_report($filters);
        $this->render('pos/report_sales_index', [
            'page_title' => 'Laporan Penjualan POS',
            'active_menu' => 'pos.report.sales',
            'report_nav_active' => 'sales',
            'filters' => $filters,
            'rows' => (array)($dataset['rows'] ?? []),
            'overview' => (array)($dataset['overview'] ?? []),
            'meta' => (array)($dataset['meta'] ?? []),
            'outlets' => $this->Pos_report_model->outlet_options(),
            'payment_methods' => $this->Pos_model->deposit_payment_method_options(),
        ]);
    }

    public function report_sales_detail()
    {
        $pageCode = $this->report_view_page_code('pos.report.sales.detail.index');
        $this->require_permission($pageCode, 'view');
        $filters = $this->sales_report_filters();
        $dataset = $this->Pos_report_model->sales_detail_report($filters);
        $this->render('pos/report_sales_detail_index', [
            'page_title' => 'Laporan Penjualan Produk POS',
            'active_menu' => 'pos.report.sales.detail',
            'report_nav_active' => 'sales_detail',
            'filters' => $filters,
            'rows' => (array)($dataset['rows'] ?? []),
            'overview' => (array)($dataset['overview'] ?? []),
            'meta' => (array)($dataset['meta'] ?? []),
            'outlets' => $this->Pos_report_model->outlet_options(),
        ]);
    }

    public function report_sales_transaction($id)
    {
        $pageCode = $this->report_view_page_code('pos.report.sales.detail.index');
        $this->require_permission($pageCode, 'view');
        $order = $this->Pos_model->find_order_draft((int)$id);
        if (!$order) {
            show_404();
        }
        $this->render('pos/report_sales_transaction', [
            'page_title' => 'Detail Transaksi POS',
            'active_menu' => 'pos.report.sales',
            'order' => $order,
            'payments' => $this->Pos_report_model->order_payment_rows((int)$id),
            'refunds' => $this->Pos_report_model->order_refund_rows((int)$id),
            'voids' => $this->Pos_report_model->order_void_rows((int)$id),
            'point_ledgers' => $this->Pos_report_model->order_point_ledger_rows((int)$id),
            'stamp_ledgers' => $this->Pos_report_model->order_stamp_ledger_rows((int)$id),
            'voucher_redemptions' => $this->Pos_report_model->order_voucher_redemption_rows((int)$id),
            'voucher_issues' => $this->Pos_report_model->order_voucher_issue_rows((int)$id),
            'payment_method_options' => $this->Pos_model->deposit_payment_method_options(),
            'can_edit_payment_method' => $this->can_edit_sales_transaction_payment(),
        ]);
    }

    public function report_sales_payment_line_update($id)
    {
        if (!$this->can_edit_sales_transaction_payment()) {
            $this->json_error('Anda tidak memiliki izin untuk mengubah metode pembayaran transaksi POS.', 403);
            return;
        }

        $paymentMethodId = (int)$this->input->post('payment_method_id', true);
        $result = $this->Pos_model->update_payment_line_method((int)$id, $paymentMethodId, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $statusCode = max(400, (int)($result['status_code'] ?? 422));
            $this->json_error((string)($result['message'] ?? 'Gagal memperbarui metode pembayaran.'), $statusCode);
            return;
        }

        $this->json_ok([
            'message' => (string)($result['message'] ?? 'Metode pembayaran berhasil diperbarui.'),
            'line' => (array)($result['line'] ?? []),
        ]);
    }

    public function report_payments()
    {
        $pageCode = $this->report_view_page_code('pos.report.payment.index');
        $this->require_permission($pageCode, 'view');
        $filters = $this->payment_report_filters();
        $dataset = $this->Pos_report_model->payment_report($filters);
        $this->render('pos/report_payment_index', [
            'page_title' => 'Laporan Pembayaran POS',
            'active_menu' => 'pos.report.payment',
            'filters' => $filters,
            'rows' => (array)($dataset['rows'] ?? []),
            'overview' => (array)($dataset['overview'] ?? []),
            'meta' => (array)($dataset['meta'] ?? []),
            'outlets' => $this->Pos_report_model->outlet_options(),
        ]);
    }

    public function report_daily_sales()
    {
        $pageCode = $this->can('pos.report.daily_sales.index', 'view')
            ? 'pos.report.daily_sales.index'
            : $this->report_view_page_code('pos.report.sales.index');
        $this->require_permission($pageCode, 'view');

        $filters = $this->daily_sales_report_filters();
        $dataset = $this->Pos_report_model->daily_sales_report((string)$filters['date'], (int)$filters['outlet_id']);

        $this->render('pos/report_daily_sales', [
            'page_title' => 'Daily Sales POS',
            'active_menu' => 'pos.report.daily_sales',
            'report_nav_active' => 'daily_sales',
            'filters' => $filters,
            'outlets' => $this->Pos_report_model->outlet_options(),
            'overview' => (array)($dataset['overview'] ?? []),
            'pay_methods' => (array)($dataset['pay_methods'] ?? []),
            'pay_accounts' => (array)($dataset['pay_accounts'] ?? []),
            'shifts' => (array)($dataset['shifts'] ?? []),
            'by_division' => (array)($dataset['by_division'] ?? []),
            'total_purchase' => (float)($dataset['total_purchase'] ?? 0),
            'net_daily_sales' => (float)($dataset['net_daily_sales'] ?? 0),
            'prev_date' => date('Y-m-d', strtotime((string)$filters['date'] . ' -1 day')),
            'next_date' => date('Y-m-d', strtotime((string)$filters['date'] . ' +1 day')),
        ]);
    }

    public function report_daily_sales_print()
    {
        $pageCode = $this->can('pos.report.daily_sales.index', 'view')
            ? 'pos.report.daily_sales.index'
            : $this->report_view_page_code('pos.report.sales.index');
        $this->require_permission($pageCode, 'view');

        $filters = $this->daily_sales_report_filters();
        $dataset = $this->Pos_report_model->daily_sales_report((string)$filters['date'], (int)$filters['outlet_id']);
        $outletName = '';
        foreach ($this->Pos_report_model->outlet_options() as $outlet) {
            if ((int)($outlet['id'] ?? 0) === (int)$filters['outlet_id']) {
                $outletName = (string)($outlet['outlet_name'] ?? '');
                break;
            }
        }

        $this->load->view('pos/report_daily_sales_print', [
            'date' => (string)$filters['date'],
            'outlet_id' => (int)$filters['outlet_id'],
            'outlet_name' => $outletName,
            'overview' => (array)($dataset['overview'] ?? []),
            'pay_methods' => (array)($dataset['pay_methods'] ?? []),
            'pay_accounts' => (array)($dataset['pay_accounts'] ?? []),
            'shifts' => (array)($dataset['shifts'] ?? []),
            'by_division' => (array)($dataset['by_division'] ?? []),
            'total_purchase' => (float)($dataset['total_purchase'] ?? 0),
            'net_daily_sales' => (float)($dataset['net_daily_sales'] ?? 0),
        ]);
    }

    public function report_payment_detail($id)
    {
        $pageCode = $this->report_view_page_code('pos.report.payment.index');
        $this->require_permission($pageCode, 'view');
        $row = $this->Pos_report_model->find_payment((int)$id);
        if (!$row) {
            show_404();
        }
        $this->render('pos/report_payment_detail', [
            'page_title' => 'Detail Pembayaran POS',
            'active_menu' => 'pos.report.payment',
            'row' => $row,
            'lines' => $this->Pos_report_model->payment_lines((int)$id),
        ]);
    }

    public function report_payment_methods()
    {
        $pageCode = $this->can('pos.report.payment.method.index', 'view')
            ? 'pos.report.payment.method.index'
            : $this->report_view_page_code('pos.report.payment.index');
        $this->require_permission($pageCode, 'view');

        $filters = $this->payment_summary_report_filters();
        $dataset = $this->Pos_report_model->payment_method_report($filters);

        $this->render('pos/report_payment_methods', [
            'page_title' => 'Laporan Metode Pembayaran POS',
            'active_menu' => 'pos.report.payment.method',
            'report_nav_active' => 'payment_methods',
            'filters' => $filters,
            'rows' => (array)($dataset['rows'] ?? []),
            'overview' => (array)($dataset['overview'] ?? []),
            'outlets' => $this->Pos_report_model->outlet_options(),
        ]);
    }

    public function report_payment_accounts()
    {
        $pageCode = $this->can('pos.report.payment.account.index', 'view')
            ? 'pos.report.payment.account.index'
            : $this->report_view_page_code('pos.report.payment.index');
        $this->require_permission($pageCode, 'view');

        $filters = $this->payment_summary_report_filters();
        $dataset = $this->Pos_report_model->payment_account_report($filters);

        $this->render('pos/report_payment_accounts', [
            'page_title' => 'Laporan Rekening Pembayaran POS',
            'active_menu' => 'pos.report.payment.account',
            'report_nav_active' => 'payment_accounts',
            'filters' => $filters,
            'rows' => (array)($dataset['rows'] ?? []),
            'overview' => (array)($dataset['overview'] ?? []),
            'outlets' => $this->Pos_report_model->outlet_options(),
        ]);
    }

    public function report_refunds()
    {
        $pageCode = $this->report_view_page_code('pos.report.refund.index');
        $this->require_permission($pageCode, 'view');
        $filters = $this->refund_report_filters();
        $dataset = $this->Pos_report_model->refund_report($filters);
        $this->render('pos/report_refund_index', [
            'page_title' => 'Laporan Refund POS',
            'active_menu' => 'pos.report.refund',
            'filters' => $filters,
            'rows' => (array)($dataset['rows'] ?? []),
            'overview' => (array)($dataset['overview'] ?? []),
            'meta' => (array)($dataset['meta'] ?? []),
            'outlets' => $this->Pos_report_model->outlet_options(),
        ]);
    }

    public function report_refund_detail($id)
    {
        $pageCode = $this->report_view_page_code('pos.report.refund.index');
        $this->require_permission($pageCode, 'view');
        $row = $this->Pos_report_model->find_refund((int)$id);
        if (!$row) {
            show_404();
        }
        $this->render('pos/report_refund_detail', [
            'page_title' => 'Detail Refund POS',
            'active_menu' => 'pos.report.refund',
            'row' => $row,
            'lines' => $this->Pos_report_model->refund_lines((int)$id),
        ]);
    }

    public function report_voids()
    {
        $pageCode = $this->report_view_page_code('pos.report.void.index');
        $this->require_permission($pageCode, 'view');
        $filters = $this->void_report_filters();
        $dataset = $this->Pos_report_model->void_report($filters);
        $this->render('pos/report_void_index', [
            'page_title' => 'Laporan Void POS',
            'active_menu' => 'pos.report.void',
            'filters' => $filters,
            'rows' => (array)($dataset['rows'] ?? []),
            'overview' => (array)($dataset['overview'] ?? []),
            'meta' => (array)($dataset['meta'] ?? []),
            'outlets' => $this->Pos_report_model->outlet_options(),
        ]);
    }

    public function report_cashier_close()
    {
        $pageCode = $this->report_view_page_code('pos.report.cashier.close.index');
        $this->require_permission($pageCode, 'view');

        $this->load->model('Finance_report_model');
        $filters = $this->cashier_close_report_filters();
        $accounts = $this->Finance_report_model->active_company_accounts();
        $selectedAccountId = (int)($filters['account_id'] ?? 0);
        if ($selectedAccountId <= 0) {
            $selectedAccountId = $this->Finance_report_model->default_cash_account_id($accounts);
        }

        $selectedAccount = null;
        foreach ($accounts as $account) {
            if ((int)($account['id'] ?? 0) === $selectedAccountId) {
                $selectedAccount = $account;
                break;
            }
        }

        $filters['account_id'] = $selectedAccountId;
        $filters['account_label'] = $this->cashier_close_account_label($selectedAccount);
        $dataset = $this->Pos_report_model->cashier_close_report($filters);

        $this->render('pos/report_cashier_close_index', [
            'page_title' => 'Laporan Tutup Kasir POS',
            'active_menu' => 'pos.report.cashier.close',
            'report_nav_active' => 'cashier_close',
            'filters' => $filters,
            'rows' => (array)($dataset['rows'] ?? []),
            'overview' => (array)($dataset['overview'] ?? []),
            'meta' => (array)($dataset['meta'] ?? []),
            'outlets' => $this->Pos_report_model->outlet_options(),
            'accounts' => $accounts,
            'selected_account' => $selectedAccount,
        ]);
    }

    public function report_cashier_close_detail($id)
    {
        $pageCode = $this->report_view_page_code('pos.report.cashier.close.index');
        $this->require_permission($pageCode, 'view');

        $shiftId = (int)$id;
        $this->load->model('Finance_report_model');
        $accounts = $this->Finance_report_model->active_company_accounts();
        $focusAccountId = max(0, (int)$this->input->get('account_id', true));
        if ($focusAccountId <= 0) {
            $focusAccountId = $this->Finance_report_model->default_cash_account_id($accounts);
        }

        $audit = $this->Pos_report_model->cashier_close_detail($shiftId, $focusAccountId);
        $report = $this->Pos_model->shift_close_report($shiftId);
        if (!$audit || !$report) {
            show_404();
        }

        $selectedAccount = null;
        foreach ($accounts as $account) {
            if ((int)($account['id'] ?? 0) === $focusAccountId) {
                $selectedAccount = $account;
                break;
            }
        }

        $this->render('pos/report_cashier_close_detail', [
            'page_title' => 'Detail Tutup Kasir POS',
            'active_menu' => 'pos.report.cashier.close',
            'report_nav_active' => 'cashier_close',
            'row' => $audit,
            'report' => $report,
            'accounts' => $accounts,
            'selected_account' => $selectedAccount,
            'focus_account_id' => $focusAccountId,
        ]);
    }

    public function report_void_detail($id)
    {
        $pageCode = $this->report_view_page_code('pos.report.void.index');
        $this->require_permission($pageCode, 'view');
        $row = $this->Pos_report_model->find_void((int)$id);
        if (!$row) {
            show_404();
        }

        $extras = $this->Pos_report_model->void_extras((int)$id);
        $extrasByVoidLine = [];
        foreach ($extras as $extra) {
            $voidLineId = (int)($extra['void_line_id'] ?? 0);
            if (!isset($extrasByVoidLine[$voidLineId])) {
                $extrasByVoidLine[$voidLineId] = [];
            }
            $extrasByVoidLine[$voidLineId][] = $extra;
        }

        $this->render('pos/report_void_detail', [
            'page_title' => 'Detail Void POS',
            'active_menu' => 'pos.report.void',
            'row' => $row,
            'lines' => $this->Pos_report_model->void_lines((int)$id),
            'extras_by_void_line' => $extrasByVoidLine,
        ]);
    }

    private function member_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $status = 'ACTIVE';
        }

        $memberStatus = strtoupper(trim((string)$this->input->get('member_status', true)));
        if (!in_array($memberStatus, ['ALL', 'ACTIVE', 'INACTIVE', 'SUSPENDED', 'EXPIRED'], true)) {
            $memberStatus = 'ALL';
        }

        return [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => $status,
            'member_status' => $memberStatus,
            'tier' => trim((string)$this->input->get('tier', true)),
            'page' => max(1, (int)$this->input->get('page', true)),
            'limit' => max(1, min(200, (int)$this->input->get('limit', true) ?: 50)),
        ];
    }

    private function sales_report_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['ALL', 'CONFIRMED', 'PAID_PARTIAL', 'PAID', 'IN_KITCHEN', 'READY', 'SERVED', 'REFUND_PARTIAL', 'REFUND_FULL'], true)) {
            $status = 'ALL';
        }

        $orderScope = strtoupper(trim((string)$this->input->get('order_scope', true)));
        if (!in_array($orderScope, ['ALL', 'REGULAR', 'EVENT'], true)) {
            $orderScope = 'ALL';
        }

        $serviceType = strtoupper(trim((string)$this->input->get('service_type', true)));
        if (!in_array($serviceType, ['ALL', 'DINE_IN', 'TAKE_AWAY', 'DELIVERY', 'PICKUP'], true)) {
            $serviceType = 'ALL';
        }

        $today = date('Y-m-d');
        $dateFrom = $this->optional_report_date_input('date_from');
        $dateTo = $this->optional_report_date_input('date_to');
        if ($dateFrom === '') {
            $dateFrom = $today;
        }
        if ($dateTo === '') {
            $dateTo = $today;
        }

        return [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => $status,
            'order_scope' => $orderScope,
            'service_type' => $serviceType,
            'payment_method_id' => max(0, (int)$this->input->get('payment_method_id', true)),
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'page' => max(1, (int)$this->input->get('page', true)),
            'limit' => max(1, min(200, (int)$this->input->get('limit', true) ?: 25)),
        ];
    }

    private function payment_report_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['ALL', 'PENDING', 'PAID', 'FAILED', 'VOID'], true)) {
            $status = 'ALL';
        }

        $paymentType = strtoupper(trim((string)$this->input->get('payment_type', true)));
        if (!in_array($paymentType, ['ALL', 'FINAL', 'DEPOSIT', 'REFUND'], true)) {
            $paymentType = 'FINAL';
        }

        return [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => $status,
            'payment_type' => $paymentType,
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'date_from' => $this->report_date_input('date_from'),
            'date_to' => $this->report_date_input('date_to'),
            'page' => max(1, (int)$this->input->get('page', true)),
            'limit' => max(1, min(200, (int)$this->input->get('limit', true) ?: 25)),
        ];
    }

    private function refund_report_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['ALL', 'POSTED', 'VOID'], true)) {
            $status = 'ALL';
        }

        return [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => $status,
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'date_from' => $this->report_date_input('date_from'),
            'date_to' => $this->report_date_input('date_to'),
            'page' => max(1, (int)$this->input->get('page', true)),
            'limit' => max(1, min(200, (int)$this->input->get('limit', true) ?: 25)),
        ];
    }

    private function payment_summary_report_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['ALL', 'PENDING', 'PAID', 'FAILED', 'VOID'], true)) {
            $status = 'PAID';
        }

        $paymentType = strtoupper(trim((string)$this->input->get('payment_type', true)));
        if (!in_array($paymentType, ['ALL', 'FINAL', 'DEPOSIT', 'REFUND'], true)) {
            $paymentType = 'ALL';
        }

        return [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => $status,
            'payment_type' => $paymentType,
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'date_from' => $this->report_date_input('date_from'),
            'date_to' => $this->report_date_input('date_to'),
        ];
    }

    private function void_report_filters(): array
    {
        $voidScope = strtoupper(trim((string)$this->input->get('void_scope', true)));
        if (!in_array($voidScope, ['ALL', 'FULL', 'PARTIAL'], true)) {
            $voidScope = 'ALL';
        }

        return [
            'q' => trim((string)$this->input->get('q', true)),
            'void_scope' => $voidScope,
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'date_from' => $this->optional_report_date_input('date_from'),
            'date_to' => $this->optional_report_date_input('date_to'),
            'page' => max(1, (int)$this->input->get('page', true)),
            'limit' => max(1, min(200, (int)$this->input->get('limit', true) ?: 25)),
        ];
    }

    private function cashier_close_report_filters(): array
    {
        return [
            'q' => trim((string)$this->input->get('q', true)),
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'account_id' => max(0, (int)$this->input->get('account_id', true)),
            'date_from' => $this->report_date_input('date_from'),
            'date_to' => $this->report_date_input('date_to'),
            'page' => max(1, (int)$this->input->get('page', true)),
            'limit' => max(1, min(200, (int)$this->input->get('limit', true) ?: 25)),
        ];
    }

    private function daily_sales_report_filters(): array
    {
        return [
            'date' => $this->report_date_input('date'),
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
        ];
    }

    private function cashier_close_account_label(?array $account): string
    {
        $row = is_array($account) ? $account : [];
        $code = trim((string)($row['account_code'] ?? ''));
        $name = trim((string)($row['account_name'] ?? ''));
        $bank = trim((string)($row['bank_name'] ?? ''));

        $parts = [];
        if ($code !== '') {
            $parts[] = $code;
        }
        if ($name !== '') {
            $parts[] = $name;
        }

        $label = implode(' - ', $parts);
        if ($bank !== '') {
            $label .= ($label !== '' ? ' | ' : '') . $bank;
        }

        return $label !== '' ? $label : 'Brankas / rekening fokus';
    }

    private function payment_method_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $status = 'ACTIVE';
        }
        $methodType = strtoupper(trim((string)$this->input->get('method_type', true)));
        if (!in_array($methodType, ['ALL', 'CASH', 'BANK', 'EWALLET', 'QRIS', 'COMPLIMENT', 'DEPOSIT', 'OTHER'], true)) {
            $methodType = 'ALL';
        }
        return [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => $status,
            'method_type' => $methodType,
            'page' => max(1, (int)$this->input->get('page', true)),
            'limit' => max(1, min(200, (int)$this->input->get('limit', true) ?: 50)),
        ];
    }

    private function sales_channel_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $status = 'ACTIVE';
        }
        $serviceType = strtoupper(trim((string)$this->input->get('service_type', true)));
        if (!in_array($serviceType, ['ALL', 'DINE_IN', 'TAKE_AWAY', 'DELIVERY', 'PICKUP'], true)) {
            $serviceType = 'ALL';
        }
        return [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => $status,
            'service_type' => $serviceType,
            'page' => max(1, (int)$this->input->get('page', true)),
            'limit' => max(1, min(200, (int)$this->input->get('limit', true) ?: 50)),
        ];
    }

    private function deposit_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('payment_status', true)));
        if (!in_array($status, ['PAID', 'PENDING', 'VOID', 'FAILED', 'ALL'], true)) {
            $status = 'PAID';
        }
        $settlement = strtoupper(trim((string)$this->input->get('settlement_status', true)));
        if (!in_array($settlement, ['ALL', 'OPEN', 'PARTIAL', 'FULL'], true)) {
            $settlement = 'ALL';
        }
        return [
            'q' => trim((string)$this->input->get('q', true)),
            'payment_status' => $status,
            'settlement_status' => $settlement,
            'page' => max(1, (int)$this->input->get('page', true)),
            'limit' => max(1, min(200, (int)$this->input->get('limit', true) ?: 50)),
        ];
    }

    private function self_order_table_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $status = 'ACTIVE';
        }
        return [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => $status,
        ];
    }

    private function outlet_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('outlet_status', true)));
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $status = 'ACTIVE';
        }
        return [
            'q' => trim((string)$this->input->get('outlet_q', true)),
            'status' => $status,
            'page' => max(1, (int)$this->input->get('outlet_page', true)),
            'limit' => max(1, min(200, (int)$this->input->get('outlet_limit', true) ?: 25)),
        ];
    }

    private function terminal_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('terminal_status', true)));
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $status = 'ACTIVE';
        }
        return [
            'q' => trim((string)$this->input->get('terminal_q', true)),
            'status' => $status,
            'outlet_id' => max(0, (int)$this->input->get('terminal_outlet_id', true)),
            'page' => max(1, (int)$this->input->get('terminal_page', true)),
            'limit' => max(1, min(200, (int)$this->input->get('terminal_limit', true) ?: 25)),
        ];
    }

    private function printer_template_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('template_status', true)));
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $status = 'ACTIVE';
        }
        $documentType = strtoupper(trim((string)$this->input->get('document_type', true)));
        if (!in_array($documentType, ['ALL', 'RECEIPT', 'KITCHEN_TICKET', 'REFUND_SLIP', 'VOID_SLIP', 'DEPOSIT_RECEIPT'], true)) {
            $documentType = 'ALL';
        }
        return [
            'q' => trim((string)$this->input->get('template_q', true)),
            'status' => $status,
            'document_type' => $documentType,
            'page' => max(1, (int)$this->input->get('template_page', true)),
            'limit' => max(1, min(100, (int)$this->input->get('template_limit', true) ?: 10)),
        ];
    }

    private function printer_profile_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('profile_status', true)));
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $status = 'ACTIVE';
        }
        return [
            'q' => trim((string)$this->input->get('profile_q', true)),
            'status' => $status,
            'page' => max(1, (int)$this->input->get('profile_page', true)),
            'limit' => max(1, min(100, (int)$this->input->get('profile_limit', true) ?: 10)),
        ];
    }

    private function printer_device_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('device_status', true)));
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $status = 'ACTIVE';
        }
        return [
            'q' => trim((string)$this->input->get('device_q', true)),
            'status' => $status,
            'outlet_id' => max(0, (int)$this->input->get('device_outlet_id', true)),
            'page' => max(1, (int)$this->input->get('device_page', true)),
            'limit' => max(1, min(100, (int)$this->input->get('device_limit', true) ?: 10)),
        ];
    }

    private function stock_live_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['ALL', 'AVAILABLE', 'LIMITED', 'OUT', 'HIDDEN'], true)) {
            $status = 'ALL';
        }
        return [
            'q' => trim((string)$this->input->get('q', true)),
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'division_id' => max(0, (int)$this->input->get('division_id', true)),
            'status' => $status,
            'dirty_only' => !empty($this->input->get('dirty_only', true)),
            'mismatch_only' => !empty($this->input->get('mismatch_only', true)),
            'page' => max(1, (int)$this->input->get('page', true)),
            'limit' => max(1, min(100, (int)$this->input->get('limit', true) ?: 25)),
        ];
    }

    private function self_order_order_filters(): array
    {
        $paymentTab = strtoupper(trim((string)$this->input->get('payment_tab', true)));
        if (!in_array($paymentTab, ['ALL', 'KASIR', 'QRIS'], true)) {
            $paymentTab = 'ALL';
        }
        $statusTab = strtoupper(trim((string)$this->input->get('status_tab', true)));
        if (!in_array($statusTab, ['ALL', 'NEEDS_VERIFY', 'WAITING_PAYMENT', 'ACTIVE_CASHIER', 'PAID_ORDER'], true)) {
            $statusTab = 'ALL';
        }
        return [
            'q' => trim((string)$this->input->get('q', true)),
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'payment_tab' => $paymentTab,
            'status_tab' => $statusTab,
            'date_from' => $this->optional_report_date_input('date_from') ?: date('Y-m-d'),
            'date_to' => $this->optional_report_date_input('date_to') ?: date('Y-m-d'),
            'page' => max(1, (int)$this->input->get('page', true)),
            'limit' => max(1, min(100, (int)$this->input->get('limit', true) ?: 20)),
        ];
    }

    private function verify_self_order_and_respond(int $orderId, int $actorEmployeeId): void
    {
        $this->load->library('PosStockCommitService');
        $this->load->library('PosRuntimeJobService');

        $context = $this->Pos_model->self_order_verification_context($orderId);
        if (!($context['ok'] ?? false)) {
            $this->json_error((string)($context['message'] ?? 'Order self order belum siap diverifikasi.'), 422);
            return;
        }

        $resolved = $this->Pos_model->resolve_order_stock_commit_payload($orderId, $actorEmployeeId, [
            'allowed_statuses' => ['PENDING', 'PAID'],
        ]);
        if (!($resolved['ok'] ?? false)) {
            $this->json_error((string)($resolved['message'] ?? 'Gagal menyiapkan stock commit order self order.'), 422);
            return;
        }
        $warningMessage = trim((string)($resolved['warning_message'] ?? ''));

        if (empty($resolved['lines'])) {
            $finalize = $this->Pos_model->finalize_self_order_verification($orderId, 0, $actorEmployeeId, [
                'payment_mode' => (string)($context['payment_mode'] ?? 'KASIR'),
                'payment_status' => (string)($context['payment_status'] ?? 'PENDING'),
                'is_paid' => !empty($context['is_paid']),
                'stock_commit_status' => 'NOT_REQUIRED',
            ]);
            if (!($finalize['ok'] ?? false)) {
                $this->json_error((string)($finalize['message'] ?? 'Order self order gagal difinalkan.'), 422);
                return;
            }
            $this->Pos_order_monitor_model->sync_order_tasks($orderId);
            $this->json_ok([
                'id' => $orderId,
                'snapshot_id' => 0,
                'commit_no' => '',
                'resolved_line_count' => 0,
                'runtime_job_id' => 0,
                'runtime_job_code' => '',
                'runtime_kickoff' => [
                    'ok' => false,
                    'mode' => 'not_required',
                    'message' => 'Stock commit dilewati karena item self order belum memiliki recipe product.',
                ],
                'stock_sync' => [
                    'queued' => false,
                    'status' => 'NOT_REQUIRED',
                    'kickoff_ok' => false,
                ],
                'stock_commit_status' => 'NOT_REQUIRED',
                'workspace_bucket' => (string)($finalize['workspace_bucket'] ?? ''),
                'target_status' => (string)($finalize['target_status'] ?? ''),
                'payment_mode' => (string)($context['payment_mode'] ?? 'KASIR'),
                'direct_print_targets' => [],
                'warning_message' => $warningMessage,
            ]);
            return;
        }

        $snapshot = $this->posstockcommitservice->create_snapshot($orderId, (array)($resolved['header'] ?? []), (array)($resolved['lines'] ?? []));
        if (!($snapshot['ok'] ?? false)) {
            $this->json_error((string)($snapshot['message'] ?? 'Gagal membuat snapshot stock commit self order.'), 422);
            return;
        }

        $queued = $this->posruntimejobservice->queue_order_confirm_commit($orderId, (int)$snapshot['id'], $actorEmployeeId, [
            'event_source' => 'SELF_ORDER_VERIFY',
            'event_id' => $orderId,
        ]);
        if (!($queued['ok'] ?? false)) {
            $this->json_error((string)($queued['message'] ?? 'Snapshot berhasil, tetapi queue runtime self order gagal dibuat.'), 422, [
                'snapshot_id' => (int)$snapshot['id'],
                'commit_no' => (string)($snapshot['commit_no'] ?? ''),
            ]);
            return;
        }

        $markQueued = $this->posstockcommitservice->mark_queued((int)$snapshot['id']);
        if (!($markQueued['ok'] ?? false)) {
            $this->posruntimejobservice->cancel_job((int)($queued['job_id'] ?? 0), 'Snapshot self order gagal ditandai queued.');
            $this->json_error((string)($markQueued['message'] ?? 'Gagal menandai stock commit self order sebagai queued.'), 422);
            return;
        }

        $finalize = $this->Pos_model->finalize_self_order_verification($orderId, (int)$snapshot['id'], $actorEmployeeId, [
            'payment_mode' => (string)($context['payment_mode'] ?? 'KASIR'),
            'payment_status' => (string)($context['payment_status'] ?? 'PENDING'),
            'is_paid' => !empty($context['is_paid']),
            'stock_commit_status' => 'QUEUED',
        ]);
        if (!($finalize['ok'] ?? false)) {
            $this->posruntimejobservice->cancel_job((int)($queued['job_id'] ?? 0), 'Order self order gagal difinalkan setelah queue dibuat.');
            $this->json_error((string)($finalize['message'] ?? 'Order self order gagal difinalkan.'), 422, [
                'snapshot_id' => (int)$snapshot['id'],
                'commit_no' => (string)($snapshot['commit_no'] ?? ''),
            ]);
            return;
        }

        $this->Pos_order_monitor_model->sync_order_tasks($orderId);

        $kickoff = [
            'ok' => false,
            'mode' => 'client_trigger_required',
            'message' => 'Queue stok self order akan dipicu dari client setelah response verify diterima.',
        ];
        if (function_exists('fastcgi_finish_request')) {
            $kickoff = $this->schedule_runtime_job_processing($orderId, (int)($queued['job_id'] ?? 0), 1);
        }

        $directPrint = $this->Pos_model->direct_print_targets_for_order_confirm($orderId, (int)$snapshot['id']);
        if (!($directPrint['ok'] ?? false)) {
            $this->json_error((string)($directPrint['message'] ?? 'Order diverifikasi, tetapi payload cetak gagal disiapkan.'), 422, [
                'snapshot_id' => (int)$snapshot['id'],
                'commit_no' => (string)($snapshot['commit_no'] ?? ''),
            ]);
            return;
        }

        $this->json_ok([
            'id' => $orderId,
            'snapshot_id' => (int)$snapshot['id'],
            'commit_no' => (string)($snapshot['commit_no'] ?? ''),
            'resolved_line_count' => (int)($resolved['resolved_line_count'] ?? 0),
            'runtime_job_id' => (int)($queued['job_id'] ?? 0),
            'runtime_job_code' => (string)($queued['job_code'] ?? ''),
            'runtime_kickoff' => $kickoff,
            'stock_sync' => [
                'queued' => true,
                'status' => 'QUEUED',
                'kickoff_ok' => !empty($kickoff['ok']),
            ],
            'stock_commit_status' => 'QUEUED',
            'workspace_bucket' => (string)($finalize['workspace_bucket'] ?? ''),
            'target_status' => (string)($finalize['target_status'] ?? ''),
            'payment_mode' => (string)($context['payment_mode'] ?? 'KASIR'),
            'direct_print_targets' => (array)($directPrint['targets'] ?? []),
            'warning_message' => $warningMessage,
        ]);
    }

    private function order_monitor_filters(): array
    {
        $station = strtoupper(trim((string)$this->input->get('station', true)));
        if (!in_array($station, ['ALL', 'BAR', 'KITCHEN', 'CHECKER'], true)) {
            $station = 'ALL';
        }

        return [
            'station' => $station,
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'date_from' => $this->optional_report_date_input('date_from') ?: date('Y-m-d'),
            'date_to' => $this->optional_report_date_input('date_to') ?: date('Y-m-d'),
        ];
    }

    private function handle_order_monitor_task_action(string $action): void
    {
        $pageCode = 'pos.order.monitor.index';
        $this->require_permission($pageCode, 'edit');
        $payload = $this->request_payload();
        $monitorScope = $this->current_actor_monitor_scope();
        $taskId = max(0, (int)($payload['task_id'] ?? 0));
        if ($taskId <= 0) {
            $this->json_error('Task monitor tidak valid.', 422);
            return;
        }

        if ($action === 'ack') {
            $ok = $this->Pos_order_monitor_model->ack_task($taskId, $this->current_actor_employee_id(), $monitorScope);
            $errorMessage = 'Task gagal diterima.';
        } elseif ($action === 'ready') {
            $ok = $this->Pos_order_monitor_model->ready_task($taskId, $this->current_actor_employee_id(), $monitorScope);
            $errorMessage = 'Task gagal ditandai siap.';
        } else {
            $ok = $this->Pos_order_monitor_model->checker_task($taskId, $this->current_actor_employee_id(), $monitorScope);
            $errorMessage = 'Task checker gagal diselesaikan.';
        }

        if (!$ok) {
            $this->json_error($errorMessage, 422);
            return;
        }

        $this->json_ok(['task_id' => $taskId]);
    }

    private function current_actor_monitor_scope(): array
    {
        if ($this->is_superadmin()) {
            return [
                'restricted' => false,
                'station_role' => 'ALL',
                'operational_division_id' => 0,
                'division_name' => '',
            ];
        }

        $employeeId = $this->current_actor_employee_id();
        if ($employeeId <= 0 || !$this->db->table_exists('org_employee')) {
            return [
                'restricted' => false,
                'station_role' => 'ALL',
                'operational_division_id' => 0,
                'division_name' => '',
            ];
        }

        $employee = $this->db->select('e.division_id, d.division_name')
            ->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->where('e.id', $employeeId)
            ->limit(1)
            ->get()
            ->row_array();

        $divisionName = strtoupper(trim((string)($employee['division_name'] ?? '')));
        if (!in_array($divisionName, ['BAR', 'KITCHEN'], true)) {
            return [
                'restricted' => false,
                'station_role' => 'ALL',
                'operational_division_id' => 0,
                'division_name' => $divisionName,
            ];
        }

        $operationalDivisionId = 0;
        if ($this->db->table_exists('mst_operational_division')) {
            $operational = $this->db->select('id')
                ->from('mst_operational_division')
                ->group_start()
                ->where('UPPER(code)', $divisionName)
                ->or_where('UPPER(name)', $divisionName)
                ->group_end()
                ->limit(1)
                ->get()
                ->row_array();
            $operationalDivisionId = (int)($operational['id'] ?? 0);
        }
        if ($operationalDivisionId <= 0) {
            $operationalDivisionId = max(0, (int)($employee['division_id'] ?? 0));
        }

        return [
            'restricted' => $operationalDivisionId > 0,
            'station_role' => $divisionName,
            'operational_division_id' => $operationalDivisionId,
            'division_name' => $divisionName,
        ];
    }

    private function trigger_stock_live_refresh_for_order(int $orderId, string $eventSource, ?int $eventId = null, bool $rebuildNow = true): array
    {
        $order = $this->Pos_model->find_order_draft($orderId);
        if (!$order) {
            return ['ok' => false, 'message' => 'Order tidak ditemukan untuk refresh stock live.'];
        }

        $header = (array)($order['header'] ?? []);
        $outletId = max(0, (int)($header['outlet_id'] ?? 0));
        $productIds = [];
        foreach ((array)($order['lines'] ?? []) as $line) {
            $productId = (int)($line['product_id'] ?? 0);
            if ($productId > 0) {
                $productIds[$productId] = $productId;
            }
        }

        if ($outletId <= 0 || empty($productIds)) {
            return ['ok' => false, 'message' => 'Outlet atau produk order tidak siap untuk refresh stock live.'];
        }

        $this->load->library('PosAvailabilityRebuildService');
        $productIds = array_values($productIds);
        $dirty = $this->posavailabilityrebuildservice->mark_dirty($outletId, $productIds, [
            'event_source' => $eventSource,
        ]);
        if (!$rebuildNow) {
            return [
                'ok' => true,
                'success_count' => 0,
                'failed_count' => 0,
                'marked_product_count' => count($productIds),
                'dirty' => $dirty,
            ];
        }
        return $this->posavailabilityrebuildservice->rebuild_products($outletId, $productIds, [
            'trigger_context' => $eventSource,
            'event_source' => $eventSource,
            'event_table' => 'pos_order',
            'event_id' => $eventId ?: $orderId,
            'actor_employee_id' => $this->current_actor_employee_id(),
        ]);
    }

    private function order_workspace_page_code(string $ability = 'view', string $preferredPageCode = ''): string
    {
        if ($preferredPageCode !== '' && $this->can($preferredPageCode, $ability)) {
            return $preferredPageCode;
        }
        if ($this->can('pos.cashier.index', $ability)) {
            return 'pos.cashier.index';
        }
        return 'pos.order.draft.index';
    }

    private function order_draft_filters(string $workspaceMode = 'MIXED'): array
    {
        $workspaceMode = strtoupper(trim($workspaceMode));
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        $allowedStatuses = ['DRAFT', 'CONFIRMED', 'PAID', 'ALL'];
        $defaultStatus = 'ALL';
        if ($workspaceMode === 'UNPAID') {
            $allowedStatuses = ['DRAFT', 'CONFIRMED', 'ALL'];
            $defaultStatus = 'DRAFT';
        } elseif ($workspaceMode === 'PAID') {
            $allowedStatuses = ['PAID', 'ALL'];
            $defaultStatus = 'PAID';
        }
        if (!in_array($status, $allowedStatuses, true)) {
            $status = $defaultStatus;
        }

        $today = date('Y-m-d');
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo   = trim((string)$this->input->get('date_to', true));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = $workspaceMode === 'PAID' ? $today : '';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = $workspaceMode === 'PAID' ? $today : '';
        }

        return [
            'q'              => trim((string)$this->input->get('q', true)),
            'status'         => $status,
            'workspace_mode' => $workspaceMode,
            'outlet_id'      => max(0, (int)$this->input->get('outlet_id', true)),
            'date_from'      => $dateFrom,
            'date_to'        => $dateTo,
            'page'           => max(1, (int)$this->input->get('page', true)),
            'limit'          => max(1, min(100, (int)$this->input->get('limit', true) ?: 20)),
        ];
    }

    private function report_view_page_code(string $preferredPageCode): string
    {
        if ($preferredPageCode !== '' && $this->can($preferredPageCode, 'view')) {
            return $preferredPageCode;
        }
        if ($this->can('pos.order.draft.index', 'view')) {
            return 'pos.order.draft.index';
        }
        if ($this->can('pos.cashier.index', 'view')) {
            return 'pos.cashier.index';
        }
        if ($this->can('pos.stock.live.index', 'view')) {
            return 'pos.stock.live.index';
        }
        return 'pos.order.draft.index';
    }

    private function can_edit_sales_transaction_payment(): bool
    {
        return $this->can('pos.report.sales.detail.index', 'edit')
            || $this->can('pos.report.sales.index', 'edit')
            || $this->can('pos.order.draft.index', 'edit')
            || $this->can('pos.cashier.index', 'edit');
    }

    private function report_date_input(string $key): string
    {
        $value = trim((string)$this->input->get($key, true));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        return date('Y-m-d');
    }

    private function optional_report_date_input(string $key): string
    {
        $value = trim((string)$this->input->get($key, true));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        return '';
    }

    private function current_actor_employee_id(): int
    {
        return max(0, (int)($this->current_user['employee_id'] ?? 0));
    }

    private function resolve_cli_php_binary(): ?string
    {
        $candidates = [
            'c:/xampp/php/php.exe',
            'C:/xampp/php/php.exe',
        ];
        if (defined('PHP_BINARY') && PHP_BINARY) {
            $candidates[] = PHP_BINARY;
        }
        if (defined('PHP_BINDIR') && PHP_BINDIR) {
            $candidates[] = rtrim(str_replace('\\', '/', PHP_BINDIR), '/') . '/php.exe';
        }

        foreach ($candidates as $candidate) {
            $normalized = str_replace('\\', '/', (string)$candidate);
            if ($normalized !== '' && is_file($normalized)) {
                return $normalized;
            }
        }

        return null;
    }

    private function escape_windows_arg(string $value): string
    {
        return '"' . str_replace('"', '\\"', str_replace('\\', '/', $value)) . '"';
    }

    private function request_payload(): array
    {
        $raw = (string)$this->input->raw_input_stream;
        if ($raw === '') {
            return $_POST;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : $_POST;
    }

    private function save_printer_template_from_form(int $id): array
    {
        $documentType = strtoupper(trim((string)$this->input->post('document_type', true)));
        if (!in_array($documentType, ['RECEIPT', 'KITCHEN_TICKET', 'VOID_SLIP', 'REFUND_SLIP', 'DEPOSIT_RECEIPT'], true)) {
            $documentType = 'RECEIPT';
        }
        $generalSettings = $this->Pos_model->printer_general_settings();
        $templatePayload = $this->posprinterpreviewservice->payloadFromInput($this->input->post(null, false), $documentType, (array)($generalSettings['payload'] ?? []));
        return $this->Pos_model->save_printer_template([
            'id' => $id,
            'template_code' => trim((string)$this->input->post('template_code', true)),
            'template_name' => trim((string)$this->input->post('template_name', true)),
            'document_type' => $documentType,
            'template_payload' => json_encode($templatePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_default' => $this->input->post('is_default') ? 1 : 0,
            'is_active' => $this->input->post('is_active') ? 1 : 0,
        ]);
    }

    private function printer_download_files(): array
    {
        $base = FCPATH . 'tools/pos_printer_agent' . DIRECTORY_SEPARATOR;
        return [
            'readme' => ['filename' => 'README.md', 'path' => $base . 'README.md'],
            'requirements' => ['filename' => 'requirements.txt', 'path' => $base . 'requirements.txt'],
            'agent_py' => ['filename' => 'agent.py', 'path' => $base . 'agent.py'],
            'check_saved_printers' => ['filename' => 'check_saved_printers.py', 'path' => $base . 'check_saved_printers.py'],
            'detect_py' => ['filename' => 'detect_printers.py', 'path' => $base . 'detect_printers.py'],
            'run_windows' => ['filename' => 'run_windows.bat', 'path' => $base . 'run_windows.bat'],
            'detect_windows' => ['filename' => 'detect_windows.bat', 'path' => $base . 'detect_windows.bat'],
            'config_example' => ['filename' => 'config.example.json', 'path' => $base . 'config.example.json'],
            'config_json' => ['filename' => 'config.json', 'path' => ''],
        ];
    }

    private function build_printer_agent_config(string $agentName = ''): array
    {
        $agentName = trim($agentName) !== '' ? trim($agentName) : 'POS-PRINTER-AGENT-01';
        $printers = $this->Pos_model->active_printer_devices_for_agent_config($agentName);
        return [
            'agent_name' => $agentName,
            'retry_seconds' => 10,
            'print_retry_count' => 2,
            'log_file' => './agent.log',
            'api' => [
                'enabled' => true,
                'base_url' => rtrim(base_url(), '/'),
                'endpoint' => '/pos/printers/bootstrap',
                'key' => trim((string)getenv('POS_PRINTER_BOOTSTRAP_KEY')),
                'key_query_param' => 'key',
                'agent_name_param' => 'agent_name',
                'refresh_seconds' => 30,
                'timeout_seconds' => 8,
            ],
            'logo' => [
                'mode' => 'esc_star',
                'threshold' => 180,
                'scale' => 1.5,
                'max_height_dots' => 160,
                'fetch_timeout_seconds' => 10,
            ],
            'printers' => array_map(static function (array $row): array {
                return [
                    'printer_code' => (string)($row['printer_code'] ?? ''),
                    'printer_name' => (string)($row['printer_name'] ?? ''),
                    'printer_role' => (string)($row['printer_role'] ?? 'CUSTOM'),
                    'mac_address' => (string)($row['mac_address'] ?? ''),
                    'python_port' => (int)($row['python_port'] ?? 3000),
                    'paper_width_mm' => (int)($row['paper_width_mm'] ?? 80),
                ];
            }, $printers),
        ];
    }

    private function build_printer_agent_config_json(string $agentName = ''): string
    {
        $json = json_encode(
            $this->build_printer_agent_config($agentName),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        return $json !== false ? $json : '{}';
    }

    private function verify_printer_agent_key(): bool
    {
        $expectedKey = trim((string)getenv('POS_PRINTER_BOOTSTRAP_KEY'));
        if ($expectedKey === '') {
            return true;
        }

        $providedKey = trim((string)$this->input->get_request_header('X-Printer-Key', true));
        if ($providedKey === '') {
            $providedKey = trim((string)$this->input->get('key', true));
        }
        if (!hash_equals($expectedKey, $providedKey)) {
            $this->output
                ->set_status_header(403)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => 'error',
                    'message' => 'Printer agent key tidak valid.',
                ], JSON_INVALID_UTF8_SUBSTITUTE));
            return false;
        }
        return true;
    }

    private function json_ok(array $data = []): void 
    { 
        $payload = ['ok' => true] + $data; 
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        $this->output 
            ->set_status_header(200)
            ->set_content_type('application/json') 
            ->set_output(json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE)); 
        $this->output->_display();
        exit;
    } 
 
    private function json_error(string $message, int $statusCode = 400, array $data = []): void 
    { 
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        $this->output 
            ->set_status_header($statusCode) 
            ->set_content_type('application/json') 
            ->set_output(json_encode([ 
                'ok' => false, 
                'message' => $message, 
            ] + $data, JSON_INVALID_UTF8_SUBSTITUTE)); 
        $this->output->_display();
        exit;
    } 
}
