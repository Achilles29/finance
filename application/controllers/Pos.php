<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pos extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Pos_model');
    }

    public function members()
    {
        $this->require_permission('pos.member.index', 'view');
        $this->render('pos/member_index', [
            'page_title' => 'Member POS',
            'filters' => $this->member_filters(),
            'filter_options' => $this->Pos_model->member_filter_options(),
        ]);
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

    private function member_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $status = 'ACTIVE';
        }

        $memberStatus = strtoupper(trim((string)$this->input->get('member_status', true)));
        if (!in_array($memberStatus, ['ALL', 'ACTIVE', 'SUSPENDED', 'CLOSED'], true)) {
            $memberStatus = 'ALL';
        }

        return [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => $status,
            'member_status' => $memberStatus,
            'city' => trim((string)$this->input->get('city', true)),
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

    private function request_payload(): array
    {
        $raw = (string)$this->input->raw_input_stream;
        if ($raw === '') {
            return $_POST;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : $_POST;
    }

    private function json_ok(array $data = []): void
    {
        $payload = ['ok' => true] + $data;
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function json_error(string $message, int $statusCode = 400, array $data = []): void
    {
        $this->output
            ->set_status_header($statusCode)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => false,
                'message' => $message,
            ] + $data, JSON_INVALID_UTF8_SUBSTITUTE));
    }
}
