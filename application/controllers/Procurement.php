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
        $today = date('Y-m-d');
        $activeTab = strtolower(trim((string)$this->input->get('tab', true)));
        if (!in_array($activeTab, ['nota', 'rincian'], true)) {
            $activeTab = 'nota';
        }

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => strtoupper(trim((string)$this->input->get('status', true))),
            'division_id' => (int)$this->input->get('division_id', true),
            'destination_type' => strtoupper(trim((string)$this->input->get('destination_type', true))),
            'date_start' => trim((string)$this->input->get('date_start', true)),
            'date_end' => trim((string)$this->input->get('date_end', true)),
        ];
        if ($filters['date_start'] === '') {
            $filters['date_start'] = $today;
        }
        if ($filters['date_end'] === '') {
            $filters['date_end'] = $today;
        }
        $limit = (int)$this->input->get('limit', true);
        if (!in_array($limit, [25, 50, 100, 200], true)) {
            $limit = 50;
        }

        $rows = $this->Procurement_model->list_store_requests($filters, $limit, 0);
        $lineRows = $activeTab === 'rincian'
            ? $this->Procurement_model->list_store_request_lines($filters, $limit, 0)
            : [];
        $ids = array_values(array_filter(array_map(static function ($row) {
            return (int)($row['id'] ?? 0);
        }, $rows), static function ($id) {
            return $id > 0;
        }));

        $divisionOptions = $this->Purchase_model->list_active_operational_divisions();

        $data = [
            'title' => 'Store Request',
            'active_menu' => 'procurement.store-request',
            'has_schema' => $this->Procurement_model->has_store_request_schema(),
            'filters' => $filters,
            'active_tab' => $activeTab,
            'limit' => $limit,
            'rows' => $rows,
            'line_rows' => $lineRows,
            'summary' => $this->Procurement_model->get_store_request_summary($filters),
            'timeline_map' => $this->Procurement_model->list_store_request_timeline_map($ids),
            'division_options' => $divisionOptions,
            'status_options' => $this->Procurement_model->list_store_request_status_options(),
            'destination_options' => $this->Procurement_model->list_destination_options(),
            'destination_guard_map' => $this->Procurement_model->build_destination_guard_map($divisionOptions),
            'can_create' => (
                $this->can(self::PAGE_WORKBENCH, 'create')
                || $this->can(self::PAGE_WORKBENCH, 'edit')
                || in_array(strtoupper((string)($this->current_user['role_code'] ?? '')), ['SUPERADMIN', 'CEO', 'ADMIN'], true)
            ),
            'can_edit' => (
                $this->can(self::PAGE_WORKBENCH, 'edit')
                || in_array(strtoupper((string)($this->current_user['role_code'] ?? '')), ['SUPERADMIN', 'CEO', 'ADMIN'], true)
            ),
            'can_repair_history' => $this->canRepairStoreRequestHistory(),
        ];
        $this->render('procurement/store_requests', $data);
    }

    public function store_request_create()
    {
        $this->require_permission(self::PAGE_WORKBENCH, 'create');

        $divisionOptions = $this->Purchase_model->list_active_operational_divisions();
        $data = [
            'title' => 'Buat Store Request',
            'active_menu' => 'procurement.store-request',
            'mode' => 'create',
            'request_id' => 0,
            'header' => [
                'request_date' => date('Y-m-d'),
                'needed_date' => date('Y-m-d'),
                'request_division_id' => (int)($divisionOptions[0]['id'] ?? 0),
                'destination_type' => '',
                'notes' => '',
                'sr_no' => '',
                'status' => 'DRAFT',
            ],
            'lines' => [],
            'division_options' => $divisionOptions,
            'destination_options' => $this->Procurement_model->list_destination_options(),
            'destination_guard_map' => $this->Procurement_model->build_destination_guard_map($divisionOptions),
            'can_create' => true,
            'can_edit' => true,
        ];

        $this->render('procurement/store_request_form', $data);
    }

    public function store_request_edit(int $id = 0)
    {
        $this->require_permission(self::PAGE_WORKBENCH, 'edit');
        if ($id <= 0) {
            show_404();
            return;
        }

        $detail = $this->Procurement_model->get_store_request_detail($id);
        if (!$detail) {
            show_404();
            return;
        }

        $header = (array)($detail['header'] ?? []);
        $status = strtoupper((string)($header['status'] ?? 'DRAFT'));
        if ($status !== 'DRAFT') {
            $this->session->set_flashdata('error', 'Hanya Store Request status DRAFT yang dapat diedit.');
            redirect('store-requests');
            return;
        }

        $divisionOptions = $this->Purchase_model->list_active_operational_divisions();
        $data = [
            'title' => 'Edit Store Request',
            'active_menu' => 'procurement.store-request',
            'mode' => 'edit',
            'request_id' => $id,
            'header' => [
                'request_date' => (string)($header['request_date'] ?? date('Y-m-d')),
                'needed_date' => (string)($header['needed_date'] ?? date('Y-m-d')),
                'request_division_id' => (int)($header['request_division_id'] ?? 0),
                'destination_type' => (string)($header['destination_type'] ?? ''),
                'notes' => (string)($header['notes'] ?? ''),
                'sr_no' => (string)($header['sr_no'] ?? ''),
                'status' => (string)($header['status'] ?? 'DRAFT'),
            ],
            'lines' => (array)($detail['lines'] ?? []),
            'division_options' => $divisionOptions,
            'destination_options' => $this->Procurement_model->list_destination_options(),
            'destination_guard_map' => $this->Procurement_model->build_destination_guard_map($divisionOptions),
            'can_create' => true,
            'can_edit' => true,
        ];

        $this->render('procurement/store_request_form', $data);
    }

    public function store_request_detail(int $id = 0)
    {
        $this->require_permission(self::PAGE_WORKBENCH, 'view');
        if ($id <= 0) {
            show_404();
            return;
        }

        $detail = $this->Procurement_model->get_store_request_detail($id);
        if (!$detail) {
            show_404();
            return;
        }

        $data = [
            'title' => 'Store Request / Detail',
            'active_menu' => 'procurement.store-request',
            'detail' => $detail,
        ];

        $this->render('procurement/store_request_detail', $data);
    }

    public function division_po_sr()
    {
        $scope = $this->divisionPoSrScope();
        if (!$scope['can_view']) {
            show_error('Anda tidak memiliki akses ke pengajuan divisi.', 403, 'Akses Ditolak');
            return;
        }

        $filters = [
            'tab' => trim((string)$this->input->get('tab', true)),
            'q' => trim((string)$this->input->get('q', true)),
            'status' => strtoupper(trim((string)$this->input->get('status', true))),
            'division_id' => (int)$this->input->get('division_id', true),
            'date_start' => trim((string)$this->input->get('date_start', true)),
            'date_end' => trim((string)$this->input->get('date_end', true)),
        ];
        if (!in_array($filters['tab'], ['notes', 'lines'], true)) {
            $filters['tab'] = 'notes';
        }
        if (!$scope['is_purchase']) {
            $filters['allowed_division_ids'] = $scope['allowed_division_ids'];
            if (
                $filters['division_id'] > 0
                && !in_array((int)$filters['division_id'], $scope['allowed_division_ids'], true)
            ) {
                $filters['division_id'] = 0;
            }
            if ($filters['division_id'] <= 0 && count($scope['allowed_division_ids']) === 1) {
                $filters['division_id'] = (int)$scope['allowed_division_ids'][0];
            }
        }
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
            'active_tab' => $filters['tab'],
            'limit' => $limit,
            'rows' => $rows,
            'line_rows' => $this->Procurement_model->list_division_request_line_rows($filters, $limit),
            'links_map' => $this->Procurement_model->list_division_request_links_map($requestIds),
            'division_options' => $scope['division_options'],
            'can_create' => $scope['can_create'],
            'can_verify' => $scope['can_verify'],
            'can_manage_own' => $scope['can_edit_own'],
            'is_purchase_scope' => $scope['is_purchase'],
        ];

        $this->render('procurement/division_po_sr', $data);
    }

    public function division_po_sr_create()
    {
        $scope = $this->divisionPoSrScope();
        if (!$scope['can_create']) {
            show_error('Anda tidak memiliki akses membuat pengajuan divisi.', 403, 'Akses Ditolak');
            return;
        }

        $destinationGuardMap = $this->Procurement_model->build_destination_guard_map($scope['division_options']);
        $defaultDivisionId = (int)($scope['default_division_id'] ?? 0);
        $defaultDestination = (string)(($destinationGuardMap[$defaultDivisionId] ?? ['OTHER'])[0] ?? 'OTHER');

        $header = [
            'request_date' => date('Y-m-d'),
            'needed_date' => date('Y-m-d'),
            'division_id' => (int)($scope['default_division_id'] ?? 0),
            'destination_type' => $defaultDestination,
            'notes' => '',
            'request_no' => '',
            'status' => 'SUBMITTED',
        ];
        $lines = [];

        if (strtolower((string)$this->input->method()) === 'post') {
            [$headerInput, $linesInput] = $this->readDivisionRequestFormPayload();
            if ($headerInput !== null) {
                $header = array_merge($header, $headerInput);
                $lines = $linesInput;
                if (!$scope['is_purchase']) {
                    $header['division_id'] = $this->sanitizeDivisionRequestDivisionId((int)($header['division_id'] ?? 0), $scope);
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

                if ($result['ok'] ?? false) {
                    $this->session->set_flashdata('success', (string)($result['message'] ?? 'Pengajuan divisi berhasil dibuat.'));
                    redirect('procurement/division-po-sr/detail/' . (int)($result['data']['request_id'] ?? 0));
                    return;
                }

                $this->session->set_flashdata('error', (string)($result['message'] ?? 'Gagal membuat pengajuan divisi.'));
            }
        }

        $this->render('procurement/division_po_sr_form', [
            'title' => 'Buat Pengajuan Divisi',
            'active_menu' => 'procurement.division',
            'mode' => 'create',
            'header' => $header,
            'lines' => $lines,
            'division_options' => $scope['division_options'],
            'destination_options' => $this->Procurement_model->list_destination_options(),
            'destination_guard_map' => $destinationGuardMap,
            'uom_options' => $this->Purchase_model->list_active_uoms(),
            'vendor_options' => $this->Purchase_model->list_active_vendors(),
            'is_purchase_scope' => $scope['is_purchase'],
            'can_verify' => false,
        ]);
    }

    public function division_po_sr_edit(int $id = 0)
    {
        $scope = $this->divisionPoSrScope();
        if (!$scope['can_view'] || $id <= 0) {
            show_error('Pengajuan divisi tidak ditemukan atau tidak dapat diakses.', 403, 'Akses Ditolak');
            return;
        }

        $detail = $this->Procurement_model->get_division_request_detail($id);
        if (!$detail) {
            show_404();
            return;
        }
        if (!$this->isDivisionRequestAccessible((int)($detail['header']['division_id'] ?? 0), $scope)) {
            show_error('Pengajuan ini berada di luar scope divisi Anda.', 403, 'Akses Ditolak');
            return;
        }

        $status = strtoupper((string)($detail['header']['status'] ?? 'SUBMITTED'));
        $hasDocs = !empty((array)($detail['links'] ?? []));
        $canVerify = $scope['can_verify'] && $status === 'SUBMITTED';
        $canEditOwn = $scope['can_edit_own'] && in_array($status, ['SUBMITTED', 'REJECTED'], true) && !$hasDocs;
        if (!$canVerify && !$canEditOwn) {
            $this->session->set_flashdata('error', 'Pengajuan ini tidak dapat diedit pada status saat ini.');
            redirect('procurement/division-po-sr/detail/' . $id);
            return;
        }

        $destinationGuardMap = $this->Procurement_model->build_destination_guard_map($scope['division_options']);

        $header = [
            'request_no' => (string)($detail['header']['request_no'] ?? ''),
            'request_date' => (string)($detail['header']['request_date'] ?? date('Y-m-d')),
            'needed_date' => (string)($detail['header']['needed_date'] ?? date('Y-m-d')),
            'division_id' => (int)($detail['header']['division_id'] ?? 0),
            'destination_type' => (string)($detail['header']['destination_type'] ?? (($destinationGuardMap[(int)($detail['header']['division_id'] ?? 0)] ?? ['OTHER'])[0] ?? 'OTHER')),
            'notes' => (string)($detail['header']['notes'] ?? ''),
            'status' => (string)($detail['header']['status'] ?? 'SUBMITTED'),
        ];
        $lines = (array)($detail['lines'] ?? []);

        if (strtolower((string)$this->input->method()) === 'post') {
            [$headerInput, $linesInput] = $this->readDivisionRequestFormPayload();
            if ($headerInput !== null) {
                $header = array_merge($header, $headerInput);
                $header['division_id'] = (int)($detail['header']['division_id'] ?? 0);
                $lines = $linesInput;

                $dbDebugBefore = (bool)$this->db->db_debug;
                $this->db->db_debug = false;
                try {
                    $result = $canVerify
                        ? $this->Procurement_model->verify_division_request(
                            $id,
                            $header,
                            $lines,
                            (int)($this->current_user['id'] ?? 0),
                            (string)$this->input->ip_address()
                        )
                        : $this->Procurement_model->update_division_request(
                            $id,
                            $header,
                            $lines,
                            (int)($this->current_user['id'] ?? 0)
                        );
                } finally {
                    $this->db->db_debug = $dbDebugBefore;
                }

                if ($result['ok'] ?? false) {
                    $this->session->set_flashdata('success', (string)($result['message'] ?? 'Pengajuan divisi berhasil diperbarui.'));
                    redirect('procurement/division-po-sr/detail/' . $id);
                    return;
                }

                $this->session->set_flashdata('error', (string)($result['message'] ?? 'Gagal memproses pengajuan divisi.'));
            }
        }

        $this->render('procurement/division_po_sr_form', [
            'title' => $canVerify ? 'Verifikasi Pengajuan Divisi' : 'Edit Pengajuan Divisi',
            'active_menu' => 'procurement.division',
            'mode' => $canVerify ? 'verify' : 'edit',
            'request_id' => $id,
            'header' => $header,
            'lines' => $lines,
            'division_options' => $scope['division_options'],
            'destination_options' => $this->Procurement_model->list_destination_options(),
            'destination_guard_map' => $destinationGuardMap,
            'uom_options' => $this->Purchase_model->list_active_uoms(),
            'vendor_options' => $this->Purchase_model->list_active_vendors(),
            'is_purchase_scope' => $scope['is_purchase'],
            'can_verify' => $canVerify,
        ]);
    }

    public function division_po_sr_profile_search()
    {
        $this->require_permission(self::PAGE_WORKBENCH, 'view');

        $q = trim((string)$this->input->get('q', true));
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 100) {
            $limit = 20;
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Procurement_model->search_division_request_candidates($q, $limit);
        } catch (Throwable $e) {
            $this->db->db_debug = $dbDebugBefore;
            $this->jsonError('Gagal memuat kandidat pengajuan divisi: ' . $e->getMessage(), 500);
            return;
        }
        $this->db->db_debug = $dbDebugBefore;

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => true,
                'rows' => (array)($result['rows'] ?? []),
                'source' => (string)($result['source'] ?? 'EMPTY'),
                'allow_manual' => !empty($result['allow_manual']),
            ]));
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

    public function division_po_sr_detail(int $id = 0)
    {
        $scope = $this->divisionPoSrScope();
        if (!$scope['can_view'] || $id <= 0) {
            show_error('Pengajuan divisi tidak ditemukan atau tidak dapat diakses.', 403, 'Akses Ditolak');
            return;
        }

        $detail = $this->Procurement_model->get_division_request_detail($id);
        if (!$detail) {
            show_404();
            return;
        }
        if (!$this->isDivisionRequestAccessible((int)($detail['header']['division_id'] ?? 0), $scope)) {
            show_error('Pengajuan ini berada di luar scope divisi Anda.', 403, 'Akses Ditolak');
            return;
        }

        $status = strtoupper((string)($detail['header']['status'] ?? 'SUBMITTED'));
        $hasDocs = !empty((array)($detail['links'] ?? []));
        $canVerify = $scope['can_verify'] && $status === 'SUBMITTED';
        $canEditOwn = $scope['can_edit_own'] && in_array($status, ['SUBMITTED', 'REJECTED'], true) && !$hasDocs;
        $canVoid = ($scope['can_verify'] || $canEditOwn) && in_array($status, ['SUBMITTED', 'REJECTED'], true);

        $this->render('procurement/division_po_sr_detail', [
            'title' => 'Detail Pengajuan Divisi',
            'active_menu' => 'procurement.division',
            'detail' => $detail,
            'can_verify' => $canVerify,
            'can_edit' => $canEditOwn,
            'can_reject' => $scope['can_verify'] && $status === 'SUBMITTED',
            'can_void' => $canVoid,
            'is_purchase_scope' => $scope['is_purchase'],
        ]);
    }

    public function division_po_sr_verify(int $id = 0)
    {
        $this->require_permission(self::PAGE_WORKBENCH, 'edit');
        if ($id <= 0) {
            $this->jsonError('Request ID tidak valid.', 422);
            return;
        }

        $payload = $this->requestPayload();
        $header = (array)($payload['header'] ?? []);
        $lines = $payload['lines'] ?? [];
        if (!is_array($lines) || empty($lines)) {
            $this->jsonError('Baris hasil verifikasi wajib diisi.', 422);
            return;
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Procurement_model->verify_division_request(
                $id,
                $header,
                $lines,
                (int)($this->current_user['id'] ?? 0),
                (string)$this->input->ip_address()
            );
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal menyimpan verifikasi purchase.'), 422);
            return;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function division_po_sr_action(int $id = 0)
    {
        if (strtolower((string)$this->input->method()) !== 'post') {
            show_error('Method tidak diizinkan.', 405, 'Method Not Allowed');
            return;
        }
        if ($id <= 0) {
            show_404();
            return;
        }

        $scope = $this->divisionPoSrScope();
        if (!$scope['can_view']) {
            show_error('Anda tidak memiliki akses ke pengajuan divisi.', 403, 'Akses Ditolak');
            return;
        }

        $detail = $this->Procurement_model->get_division_request_detail($id);
        if (!$detail || !$this->isDivisionRequestAccessible((int)($detail['header']['division_id'] ?? 0), $scope)) {
            show_error('Pengajuan ini berada di luar scope divisi Anda.', 403, 'Akses Ditolak');
            return;
        }

        $action = strtoupper(trim((string)$this->input->post('action', true)));
        $notes = trim((string)$this->input->post('notes', true));
        if ($action === '') {
            $this->session->set_flashdata('error', 'Action wajib diisi.');
            redirect('procurement/division-po-sr/detail/' . $id);
            return;
        }

        if ($action === 'REJECT' && !$scope['can_verify']) {
            show_error('Hanya purchase yang dapat me-reject pengajuan.', 403, 'Akses Ditolak');
            return;
        }
        if ($action === 'VOID' && !($scope['can_verify'] || $scope['can_edit_own'])) {
            show_error('Anda tidak memiliki akses untuk meng-void pengajuan.', 403, 'Akses Ditolak');
            return;
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Procurement_model->apply_division_request_action(
                $id,
                $action,
                $notes,
                (int)($this->current_user['id'] ?? 0)
            );
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        $this->session->set_flashdata(
            ($result['ok'] ?? false) ? 'success' : 'error',
            (string)($result['message'] ?? 'Gagal memproses aksi pengajuan divisi.')
        );
        redirect('procurement/division-po-sr/detail/' . $id);
    }

    public function store_request_profile_search()
    {
        $this->require_permission(self::PAGE_WORKBENCH, 'view');

        $q = trim((string)$this->input->get('q', true));
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 100) {
            $limit = 20;
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $rows = $this->Procurement_model->search_warehouse_profiles($q, $limit);
        } catch (Throwable $e) {
            $this->db->db_debug = $dbDebugBefore;
            $this->jsonError('Gagal memuat profile gudang: ' . $e->getMessage(), 500);
            return;
        }
        $this->db->db_debug = $dbDebugBefore;

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

    public function store_request_update(int $id = 0)
    {
        $this->require_permission(self::PAGE_WORKBENCH, 'edit');
        if ($id <= 0) {
            $this->jsonError('Request ID tidak valid.', 422);
            return;
        }

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
            $result = $this->Procurement_model->update_store_request(
                $id,
                $header,
                $lines,
                (int)($this->current_user['id'] ?? 0)
            );
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal memperbarui Store Request.'), 422);
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

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Procurement_model->preview_split($id);
        } catch (Throwable $e) {
            $this->db->db_debug = $dbDebugBefore;
            $this->jsonError('Gagal membaca split shortage: ' . $e->getMessage(), 500);
            return;
        }
        $this->db->db_debug = $dbDebugBefore;

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
        } catch (Throwable $e) {
            $this->db->db_debug = $dbDebugBefore;
            $this->jsonError('Gagal posting fulfillment: ' . $e->getMessage(), 500);
            return;
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

    public function store_request_repair_history(int $id = 0)
    {
        $this->require_permission(self::PAGE_WORKBENCH, 'edit');
        if ($id <= 0) {
            $this->jsonError('Request ID tidak valid.', 422);
            return;
        }
        if (!$this->canRepairStoreRequestHistory()) {
            $this->jsonError('Akses repair histori SR dibatasi untuk admin.', 403);
            return;
        }

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Procurement_model->repair_void_store_request_history(
                $id,
                (int)($this->current_user['id'] ?? 0)
            );
        } catch (Throwable $e) {
            $this->db->db_debug = $dbDebugBefore;
            $this->jsonError('Gagal repair histori SR: ' . $e->getMessage(), 500);
            return;
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal repair histori SR.'), 422);
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

    private function divisionPoSrScope(): array
    {
        static $scope = null;
        if ($scope !== null) {
            return $scope;
        }

        $roleCode = strtoupper((string)($this->current_user['role_code'] ?? ''));
        $isAdminRole = in_array($roleCode, ['SUPERADMIN', 'CEO', 'ADMIN'], true);
        $canModuleView = $this->can(self::PAGE_WORKBENCH, 'view');
        $canModuleCreate = $this->can(self::PAGE_WORKBENCH, 'create');
        $canModuleEdit = $this->can(self::PAGE_WORKBENCH, 'edit');
        $isPurchase = $canModuleEdit || $isAdminRole;

        $allDivisionOptions = $this->Purchase_model->list_active_operational_divisions();
        $divisionScope = $this->resolveCurrentUserDivisionScope($allDivisionOptions);

        $canDivisionScopedCrud = !empty($divisionScope['allowed_division_ids']);
        $scope = [
            'is_purchase' => $isPurchase,
            'can_view' => $canModuleView || $isPurchase || $canDivisionScopedCrud,
            'can_create' => $canModuleCreate || $isPurchase || $canDivisionScopedCrud,
            'can_edit_own' => !$isPurchase && ($canModuleCreate || $canDivisionScopedCrud),
            'can_verify' => $isPurchase,
            'allowed_division_ids' => $isPurchase ? [] : (array)$divisionScope['allowed_division_ids'],
            'division_options' => $isPurchase ? $allDivisionOptions : (array)$divisionScope['division_options'],
            'default_division_id' => $isPurchase
                ? (int)($allDivisionOptions[0]['id'] ?? 0)
                : (int)($divisionScope['default_division_id'] ?? 0),
        ];

        return $scope;
    }

    private function resolveCurrentUserDivisionScope(array $allDivisionOptions): array
    {
        $employeeId = (int)($this->current_user['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            return [
                'allowed_division_ids' => [],
                'division_options' => [],
                'default_division_id' => 0,
            ];
        }

        $employee = $this->db
            ->select('d.division_code, d.division_name')
            ->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->where('e.id', $employeeId)
            ->limit(1)
            ->get()
            ->row_array();
        $divisionCode = strtoupper(trim((string)($employee['division_code'] ?? '')));
        $allowedCodes = [];
        if ($divisionCode === 'BAR') {
            $allowedCodes = ['BAR', 'BAR_EVENT'];
        } elseif ($divisionCode === 'KITCHEN') {
            $allowedCodes = ['KITCHEN', 'KITCHEN_EVENT'];
        }

        if (empty($allowedCodes)) {
            return [
                'allowed_division_ids' => [],
                'division_options' => [],
                'default_division_id' => 0,
            ];
        }

        $divisionOptions = [];
        foreach ($allDivisionOptions as $option) {
            $code = strtoupper(trim((string)($option['code'] ?? '')));
            if (in_array($code, $allowedCodes, true)) {
                $divisionOptions[] = $option;
            }
        }

        return [
            'allowed_division_ids' => array_values(array_map(static function ($option) {
                return (int)($option['id'] ?? 0);
            }, $divisionOptions)),
            'division_options' => $divisionOptions,
            'default_division_id' => (int)($divisionOptions[0]['id'] ?? 0),
        ];
    }

    private function isDivisionRequestAccessible(int $divisionId, array $scope): bool
    {
        if ($scope['is_purchase'] ?? false) {
            return true;
        }
        return $divisionId > 0 && in_array($divisionId, (array)($scope['allowed_division_ids'] ?? []), true);
    }

    private function sanitizeDivisionRequestDivisionId(int $divisionId, array $scope): int
    {
        $allowedDivisionIds = (array)($scope['allowed_division_ids'] ?? []);
        if (empty($allowedDivisionIds)) {
            return $divisionId;
        }
        if (in_array($divisionId, $allowedDivisionIds, true)) {
            return $divisionId;
        }
        return (int)($allowedDivisionIds[0] ?? 0);
    }

    private function readDivisionRequestFormPayload(): array
    {
        $requestDate = trim((string)$this->input->post('request_date', true));
        $neededDate = trim((string)$this->input->post('needed_date', true));
        $divisionId = (int)$this->input->post('division_id', true);
        $destinationType = strtoupper(trim((string)$this->input->post('destination_type', true)));
        $notes = trim((string)$this->input->post('notes', true));
        $linesJson = (string)$this->input->post('lines_json', false);
        $decodedLines = json_decode($linesJson, true);
        if (!is_array($decodedLines)) {
            $decodedLines = [];
        }

        if ($requestDate === '' || $divisionId <= 0 || $destinationType === '') {
            $this->session->set_flashdata('error', 'Tanggal request, divisi, dan lokasi stok wajib diisi.');
            return [null, []];
        }
        if (empty($decodedLines)) {
            $this->session->set_flashdata('error', 'Minimal 1 line pengajuan wajib diisi.');
            return [null, []];
        }

        return [[
            'request_date' => $requestDate,
            'needed_date' => $neededDate,
            'division_id' => $divisionId,
            'destination_type' => $destinationType,
            'notes' => $notes,
        ], $decodedLines];
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

    private function canRepairStoreRequestHistory(): bool
    {
        return in_array(
            strtoupper((string)($this->current_user['role_code'] ?? '')),
            ['SUPERADMIN', 'CEO', 'ADMIN'],
            true
        );
    }
}
