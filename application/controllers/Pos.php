<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pos extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Pos_model');
        $this->load->library('PosPrinterPreviewService');
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
        redirect('pos/printers/templates');
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
        $this->render('pos/order_draft_index', [
            'page_title' => 'Draft Order POS',
            'active_menu' => 'pos.order.draft.index',
            'filters' => $this->order_draft_filters(),
            'filter_options' => $this->Pos_model->order_draft_filter_options(),
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
            'filters' => $this->order_draft_filters(),
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
        $this->json_ok([
            'summary' => (array)($result['summary'] ?? []),
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

    public function order_draft_data()
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $this->json_ok($this->Pos_model->order_draft_rows($this->order_draft_filters()));
    }

    public function order_draft_load($id)
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $result = $this->Pos_model->find_order_draft((int)$id);
        if (!$result) {
            $this->json_error('Draft order tidak ditemukan.', 404);
            return;
        }
        $this->json_ok($result);
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
        $this->load->library('PosStockCommitService');
        $this->load->library('PosOrderStockService');

        $actorEmployeeId = $this->current_actor_employee_id();
        $orderId = (int)$id;
        $resolved = $this->Pos_model->resolve_order_stock_commit_payload($orderId, $actorEmployeeId);
        if (!($resolved['ok'] ?? false)) {
            $this->json_error((string)($resolved['message'] ?? 'Gagal menyiapkan stock commit order POS.'), 422);
            return;
        }

        $snapshot = $this->posstockcommitservice->create_snapshot($orderId, (array)($resolved['header'] ?? []), (array)($resolved['lines'] ?? []));
        if (!($snapshot['ok'] ?? false)) {
            $this->json_error((string)($snapshot['message'] ?? 'Gagal membuat snapshot stock commit.'), 422);
            return;
        }

        $stockPost = $this->posorderstockservice->post_commit_snapshot((int)$snapshot['id'], [
            'actor_employee_id' => $actorEmployeeId,
        ]);
        if (!($stockPost['ok'] ?? false)) {
            $this->json_error((string)($stockPost['message'] ?? 'Snapshot berhasil, tetapi posting stok order POS gagal.'), 422, [
                'snapshot_id' => (int)$snapshot['id'],
                'commit_no' => (string)($snapshot['commit_no'] ?? ''),
            ]);
            return;
        }

        $commit = $this->posstockcommitservice->mark_committed((int)$snapshot['id']);
        if (!($commit['ok'] ?? false)) {
            $this->json_error((string)($commit['message'] ?? 'Gagal menandai stock commit sebagai committed.'), 422);
            return;
        }

        $finalize = $this->Pos_model->finalize_order_confirmation($orderId, (int)$snapshot['id'], $actorEmployeeId);
        if (!($finalize['ok'] ?? false)) {
            $this->json_error((string)($finalize['message'] ?? 'Snapshot berhasil, tetapi order gagal difinalkan.'), 422, [
                'snapshot_id' => (int)$snapshot['id'],
                'commit_no' => (string)($snapshot['commit_no'] ?? ''),
            ]);
            return;
        }

        $printJobs = $this->Pos_model->queue_order_confirm_print_jobs($orderId);
        $this->json_ok([
            'id' => $orderId,
            'snapshot_id' => (int)$snapshot['id'],
            'commit_no' => (string)($snapshot['commit_no'] ?? ''),
            'resolved_line_count' => (int)($resolved['resolved_line_count'] ?? 0),
            'posted_stock_line_count' => (int)($stockPost['posted_lines'] ?? 0),
            'print_job_count' => (int)($printJobs['job_count'] ?? 0),
        ]);
    }

    public function order_reversal_preview($id)
    {
        $pageCode = $this->can('pos.cashier.index', 'view') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'view');
        $result = $this->Pos_model->order_reversal_preview((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyiapkan preview void/refund POS.'), 422);
            return;
        }
        $this->json_ok($result);
    }

    public function order_void_save()
    {
        $pageCode = $this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'edit');
        $payload = $this->request_payload();
        $result = $this->Pos_model->save_order_void($payload, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan void POS.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)($result['id'] ?? 0),
            'void_no' => (string)($result['void_no'] ?? ''),
        ]);
    }

    public function order_refund_save()
    {
        $pageCode = $this->can('pos.cashier.index', 'edit') ? 'pos.cashier.index' : 'pos.order.draft.index';
        $this->require_permission($pageCode, 'edit');
        $payload = $this->request_payload();
        $result = $this->Pos_model->save_order_refund($payload, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan refund POS.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)($result['id'] ?? 0),
            'refund_no' => (string)($result['refund_no'] ?? ''),
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

    private function order_draft_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['DRAFT', 'CONFIRMED', 'ALL'], true)) {
            $status = 'DRAFT';
        }
        return [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => $status,
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'page' => max(1, (int)$this->input->get('page', true)),
            'limit' => max(1, min(100, (int)$this->input->get('limit', true) ?: 20)),
        ];
    }

    private function current_actor_employee_id(): int
    {
        return max(0, (int)($this->current_user['employee_id'] ?? 0));
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
