<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Procurement extends MY_Controller
{
    private const PAGE_WORKBENCH = 'procurement.workbench.index';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Procurement_model');
        $this->load->model('Purchase_model');
    }

    public function workbench()
    {
        redirect('store-requests');
    }

    public function division_requests()
    {
        $this->division_po_sr();
    }

    public function purchasing_desk()
    {
        $this->store_requests();
    }

    public function store_requests()
    {
        $this->require_permission(self::PAGE_WORKBENCH, 'view');

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => strtoupper(trim((string)$this->input->get('status', true))),
            'division_id' => (int)$this->input->get('division_id', true),
            'destination_type' => strtoupper(trim((string)$this->input->get('destination_type', true))),
            'date_start' => trim((string)$this->input->get('date_start', true)),
            'date_end' => trim((string)$this->input->get('date_end', true)),
        ];
        $limit = (int)$this->input->get('limit', true);
        if (!in_array($limit, [25, 50, 100, 200], true)) {
            $limit = 50;
        }

        $rows = $this->Procurement_model->list_store_requests($filters, $limit, 0);
        $ids = array_values(array_filter(array_map(static function ($row) {
            return (int)($row['id'] ?? 0);
        }, $rows), static function ($id) {
            return $id > 0;
        }));

        $data = [
            'title' => 'Store Request',
            'active_menu' => 'procurement.store-request',
            'has_schema' => $this->Procurement_model->has_store_request_schema(),
            'filters' => $filters,
            'limit' => $limit,
            'rows' => $rows,
            'summary' => $this->Procurement_model->get_store_request_summary($filters),
            'timeline_map' => $this->Procurement_model->list_store_request_timeline_map($ids),
            'division_options' => $this->Purchase_model->list_active_operational_divisions(),
            'status_options' => $this->Procurement_model->list_store_request_status_options(),
            'destination_options' => $this->Procurement_model->list_destination_options(),
            'can_create' => (
                $this->can(self::PAGE_WORKBENCH, 'create')
                || $this->can(self::PAGE_WORKBENCH, 'edit')
                || in_array(strtoupper((string)($this->current_user['role_code'] ?? '')), ['SUPERADMIN', 'CEO', 'ADMIN'], true)
            ),
            'can_edit' => (
                $this->can(self::PAGE_WORKBENCH, 'edit')
                || in_array(strtoupper((string)($this->current_user['role_code'] ?? '')), ['SUPERADMIN', 'CEO', 'ADMIN'], true)
            ),
        ];
        $this->render('procurement/store_requests', $data);
    }

    public function division_po_sr()
    {
        $this->require_permission(self::PAGE_WORKBENCH, 'view');

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => strtoupper(trim((string)$this->input->get('status', true))),
            'division_id' => (int)$this->input->get('division_id', true),
            'date_start' => trim((string)$this->input->get('date_start', true)),
            'date_end' => trim((string)$this->input->get('date_end', true)),
        ];
        $limit = (int)$this->input->get('limit', true);
        if (!in_array($limit, [25, 50, 100, 200], true)) {
            $limit = 50;
        }

        $rows = $this->Procurement_model->list_division_requests($filters, $limit);
        $requestIds = array_values(array_filter(array_map(static function ($row) {
            return (int)($row['id'] ?? 0);
        }, $rows), static function ($id) {
            return $id > 0;
        }));

        $data = [
            'title' => 'PO/SR Divisi',
            'active_menu' => 'procurement.division',
            'has_schema' => $this->Procurement_model->has_division_request_schema(),
            'filters' => $filters,
            'limit' => $limit,
            'rows' => $rows,
            'links_map' => $this->Procurement_model->list_division_request_links_map($requestIds),
            'division_options' => $this->Purchase_model->list_active_operational_divisions(),
            'can_create' => $this->can(self::PAGE_WORKBENCH, 'create'),
        ];

        $this->render('procurement/division_po_sr', $data);
    }

    public function division_po_sr_store()
    {
        $this->require_permission(self::PAGE_WORKBENCH, 'create');
        $payload = $this->requestPayload();
        $header = (array)($payload['header'] ?? []);
        $lines = $payload['lines'] ?? [];
        if (!is_array($lines) || empty($lines)) {
            $this->jsonError('Baris PO/SR wajib diisi.', 422);
            return;
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Procurement_model->create_division_request(
                $header,
                $lines,
                (int)($this->current_user['id'] ?? 0),
                (string)$this->input->ip_address()
            );
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal membuat pengajuan PO/SR divisi.'), 422);
            return;
        }
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function store_request_profile_search()
    {
        $this->require_permission(self::PAGE_WORKBENCH, 'view');

        $q = trim((string)$this->input->get('q', true));
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 100) {
            $limit = 20;
        }

        $rows = $this->Procurement_model->search_warehouse_profiles($q, $limit);
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => true,
                'rows' => $rows,
            ]));
    }

    public function store_request_store()
    {
        $this->require_permission(self::PAGE_WORKBENCH, 'create');

        $payload = $this->requestPayload();
        $header = (array)($payload['header'] ?? []);
        $lines = $payload['lines'] ?? [];
        if (!is_array($lines) || empty($lines)) {
            $this->jsonError('Line Store Request wajib diisi.', 422);
            return;
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Procurement_model->create_store_request(
                $header,
                $lines,
                (int)($this->current_user['id'] ?? 0)
            );
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal membuat Store Request.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function store_request_action(int $id = 0)
    {
        $this->require_permission(self::PAGE_WORKBENCH, 'edit');
        if ($id <= 0) {
            $this->jsonError('Request ID tidak valid.', 422);
            return;
        }

        $payload = $this->requestPayload();
        $action = strtoupper(trim((string)($payload['action'] ?? '')));
        $notes = trim((string)($payload['notes'] ?? ''));

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Procurement_model->apply_store_request_action(
                $id,
                $action,
                $notes,
                (int)($this->current_user['id'] ?? 0)
            );
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal memproses aksi Store Request.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function store_request_split_preview(int $id = 0)
    {
        $this->require_permission(self::PAGE_WORKBENCH, 'view');
        if ($id <= 0) {
            $this->jsonError('Request ID tidak valid.', 422);
            return;
        }

        $result = $this->Procurement_model->preview_split($id);
        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal membaca split shortage.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function store_request_fulfill(int $id = 0)
    {
        $this->require_permission(self::PAGE_WORKBENCH, 'edit');
        if ($id <= 0) {
            $this->jsonError('Request ID tidak valid.', 422);
            return;
        }

        $payload = $this->requestPayload();
        $date = trim((string)($payload['fulfillment_date'] ?? date('Y-m-d')));
        $notes = trim((string)($payload['notes'] ?? ''));

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Procurement_model->fulfill_auto_from_warehouse(
                $id,
                $date,
                $notes,
                (int)($this->current_user['id'] ?? 0)
            );
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal posting fulfillment.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function store_request_generate_po(int $id = 0)
    {
        $this->require_permission(self::PAGE_WORKBENCH, 'edit');
        if ($id <= 0) {
            $this->jsonError('Request ID tidak valid.', 422);
            return;
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $shortagePayload = $this->Procurement_model->get_shortage_po_payload($id);
            if (!($shortagePayload['ok'] ?? false)) {
                $this->jsonError((string)($shortagePayload['message'] ?? 'Tidak ada shortage untuk dibuat PO.'), 422);
                return;
            }

            $purchaseTypeId = $this->Procurement_model->find_inventory_purchase_type_id();
            if ($purchaseTypeId === null || $purchaseTypeId <= 0) {
                $this->jsonError('Purchase type inventory untuk draft PO shortage belum tersedia.', 422);
                return;
            }

            $header = (array)($shortagePayload['header'] ?? []);
            $srNo = (string)($header['sr_no'] ?? ('SR#' . $id));
            $requestDate = (string)($header['request_date'] ?? date('Y-m-d'));
            $expectedDate = (string)($header['needed_date'] ?? date('Y-m-d'));

            $poHeader = [
                'request_date' => $requestDate,
                'expected_date' => $expectedDate,
                'purchase_type_id' => $purchaseTypeId,
                'destination_type' => 'GUDANG',
                'status' => 'DRAFT',
                'notes' => 'Auto draft PO shortage dari ' . $srNo,
                'external_ref_no' => $srNo,
            ];

            $poResult = $this->Purchase_model->store_order_with_lines(
                $poHeader,
                (array)($shortagePayload['lines'] ?? []),
                (int)($this->current_user['id'] ?? 0),
                (string)$this->input->ip_address()
            );
            if (!($poResult['ok'] ?? false)) {
                $this->jsonError((string)($poResult['message'] ?? 'Gagal membuat draft PO shortage.'), 422);
                return;
            }

            $poData = (array)($poResult['data'] ?? []);
            $poId = (int)($poData['purchase_order_id'] ?? 0);
            if ($poId > 0) {
                $this->Procurement_model->link_store_request_po(
                    $id,
                    $poId,
                    'SHORTAGE',
                    'Auto generated dari split shortage',
                    (int)($this->current_user['id'] ?? 0)
                );
            }
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => true,
                'message' => 'Draft PO shortage berhasil dibuat.',
                'data' => [
                    'purchase_order_id' => (int)($poData['purchase_order_id'] ?? 0),
                    'po_no' => (string)($poData['po_no'] ?? ''),
                    'redirect_url' => site_url('purchase-orders/detail/' . (int)($poData['purchase_order_id'] ?? 0)),
                ],
            ]));
    }

    private function requestPayload(): array
    {
        $raw = (string)$this->input->raw_input_stream;
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $post = $this->input->post(null, true);
        return is_array($post) ? $post : [];
    }

    private function jsonError(string $message, int $statusCode = 400): void
    {
        $this->output
            ->set_status_header($statusCode)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => false,
                'message' => $message,
            ]));
    }
}
