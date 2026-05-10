<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Master extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Master_model');
        $this->load->library('form_validation');
    }

    private function redirect_contract_operational_if_needed(string $entity, string $action = 'index', int $id = 0): bool
    {
        if ($entity === 'hr-contract-template') {
            if ($action === 'index' || $action === 'create' || $action === 'store') {
                redirect('hr-contracts/templates');
                return true;
            }
            if ($action === 'edit' || $action === 'update' || $action === 'detail') {
                redirect('hr-contracts/template-edit/' . $id);
                return true;
            }
            if ($action === 'toggle') {
                $this->session->set_flashdata('warning', 'Aktif/nonaktif template kontrak sekarang dilakukan dari halaman template kontrak.');
                redirect('hr-contracts/templates');
                return true;
            }
        }

        if ($entity === 'hr-contract') {
            if ($action === 'index' || $action === 'create' || $action === 'store') {
                redirect('hr/contracts');
                return true;
            }
            if ($action === 'edit' || $action === 'update' || $action === 'detail') {
                redirect('hr-contracts/view/' . $id);
                return true;
            }
            if ($action === 'toggle') {
                $this->session->set_flashdata('warning', 'Status kontrak diatur dari halaman detail kontrak.');
                redirect('hr/contracts');
                return true;
            }
        }

        return false;
    }

    public function index(string $entity = 'uom')
    {
        if ($this->redirect_contract_operational_if_needed($entity, 'index')) {
            return;
        }
        $cfg = $this->entityConfig($entity);
        if (!$cfg) show_404();

        $q = trim((string)$this->input->get('q', true));
        $status = strtolower(trim((string)$this->input->get('status', true)));
        $perPage = (int)$this->input->get('per_page', true);
        $page = max(1, (int)$this->input->get('page', true));
        $hasActiveFlag = !empty($cfg['toggle']);
        if (!in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = $hasActiveFlag ? 'active' : 'all';
        }
        $isActiveFilter = null;
        if ($hasActiveFlag && $status !== 'all') {
            $isActiveFilter = $status === 'active' ? 1 : 0;
        }
        if (!in_array($perPage, [10, 25, 50, 100, 500], true)) {
            $perPage = 25;
        }

        $total = $this->Master_model->count_filtered($cfg['table'], $cfg['searchable'], $q, $isActiveFilter);
        $offset = ($page - 1) * $perPage;
        if ($offset >= $total && $total > 0) {
            $page = 1;
            $offset = 0;
        }

        $rows = $this->Master_model->get_filtered(
            $cfg['table'],
            $cfg['searchable'],
            $q,
            $perPage,
            $offset,
            $cfg['order_by'],
            $cfg['order_dir'],
            $isActiveFilter
        );

        $lookupMaps = $this->buildLookupMaps($cfg);
        $rows = $this->decorateRows($rows, $cfg, $lookupMaps);

        $activeMenu = (string)($cfg['active_menu'] ?? 'grp.master');
        $data = [
            'title' => $cfg['title'],
            'active_menu' => $activeMenu,
            'entity' => $entity,
            'cfg' => $cfg,
            'entity_nav' => $this->entityNav(),
            'rows' => $rows,
            'q' => $q,
            'status' => $status,
            'has_active_flag' => $hasActiveFlag,
            'per_page' => $perPage,
            'page' => $page,
            'total' => $total,
            'total_pages' => max(1, (int)ceil($total / $perPage)),
        ];

        $this->render('master/index', $data);
    }

    public function create(string $entity)
    {
        if ($this->redirect_contract_operational_if_needed($entity, 'create')) {
            return;
        }
        $cfg = $this->entityConfig($entity);
        if (!$cfg) show_404();

        $vcDefaults = $this->variableCostDefaultsForEntity($entity);

        $activeMenu = (string)($cfg['active_menu'] ?? 'grp.master');
        $data = [
            'title' => 'Tambah ' . $cfg['title'],
            'active_menu' => $activeMenu,
            'entity' => $entity,
            'cfg' => $cfg,
            'entity_nav' => $this->entityNav(),
            'row' => null,
            'options' => $this->formOptions($cfg),
            'form_action' => 'master/' . $entity . '/store',
            'is_edit' => false,
            'variable_cost_defaults' => $vcDefaults,
        ];
        $this->render('master/form', $data);
    }

    public function store(string $entity)
    {
        if ($this->redirect_contract_operational_if_needed($entity, 'store')) {
            return;
        }
        $cfg = $this->entityConfig($entity);
        if (!$cfg) show_404();

        $this->autofillCodeFromName($cfg, 0);
        $this->autofillOrgEmployeeCodeAndNip($cfg, 0);
        $this->applyValidation($cfg);
        if ($this->form_validation->run() === false) {
            $this->session->set_flashdata('error', validation_errors('<li>', '</li>'));
            redirect('master/' . $entity . '/create');
        }

        $payload = $this->collectPayload($cfg, 0);
        if ($payload === null) {
            redirect('master/' . $entity . '/create');
            return;
        }

        if (($cfg['table'] ?? '') === 'att_location' && $this->db->field_exists('is_default', 'att_location')) {
            if (!empty($payload['is_default']) && (int)$payload['is_default'] === 1) {
                $this->db->set('is_default', 0)->update('att_location');
            }
        }

        if (($cfg['table'] ?? '') === 'mst_product') {
            $photoPayload = $this->handleProductPhotoUpload();
            if ($photoPayload === null) {
                redirect('master/' . $entity . '/create');
                return;
            }
            $payload = array_merge($payload, $photoPayload);
        }

        $this->Master_model->insert($cfg['table'], $payload);
        $this->session->set_flashdata('success', $cfg['title'] . ' berhasil disimpan.');
        redirect('master/' . $entity);
    }

    public function edit(string $entity, int $id)
    {
        if ($this->redirect_contract_operational_if_needed($entity, 'edit', $id)) {
            return;
        }
        $cfg = $this->entityConfig($entity);
        if (!$cfg) show_404();

        $row = $this->Master_model->get_by_id($cfg['table'], $id);
        if (!$row) show_404();

        $vcDefaults = $this->variableCostDefaultsForEntity($entity);

        $activeMenu = (string)($cfg['active_menu'] ?? 'grp.master');
        $data = [
            'title' => 'Edit ' . $cfg['title'],
            'active_menu' => $activeMenu,
            'entity' => $entity,
            'cfg' => $cfg,
            'entity_nav' => $this->entityNav(),
            'row' => $row,
            'options' => $this->formOptions($cfg),
            'form_action' => 'master/' . $entity . '/update/' . $id,
            'is_edit' => true,
            'variable_cost_defaults' => $vcDefaults,
        ];
        $this->render('master/form', $data);
    }

    public function detail(string $entity, int $id)
    {
        if ($this->redirect_contract_operational_if_needed($entity, 'detail', $id)) {
            return;
        }
        $cfg = $this->entityConfig($entity);
        if (!$cfg) show_404();

        if ($entity !== 'org-employee') {
            redirect('master/' . $entity . '/edit/' . $id);
            return;
        }

        $row = $this->db->select('e.*, d.division_name, p.position_name')
            ->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->where('e.id', $id)
            ->limit(1)
            ->get()->row_array();
        if (!$row) {
            show_404();
        }

        $linkedUsers = $this->db->select('u.id, u.username, u.email, u.is_active, u.last_login_at, GROUP_CONCAT(DISTINCT r.role_name ORDER BY r.role_name SEPARATOR \', \') AS roles', false)
            ->from('auth_user u')
            ->join('auth_user_role ur', 'ur.user_id = u.id', 'left')
            ->join('auth_role r', 'r.id = ur.role_id', 'left')
            ->where('u.employee_id', $id)
            ->group_by('u.id')
            ->order_by('u.id', 'ASC')
            ->get()->result_array();

        $schedules = $this->db->select('ss.schedule_date, s.shift_code, s.shift_name, ss.notes, ss.created_at')
            ->from('att_shift_schedule ss')
            ->join('att_shift s', 's.id = ss.shift_id', 'left')
            ->where('ss.employee_id', $id)
            ->order_by('ss.schedule_date', 'DESC')
            ->limit(31)
            ->get()->result_array();

        $attDaily = [];
        if ($this->db->table_exists('att_daily')) {
            $attDaily = $this->db->select('attendance_date, attendance_status, checkin_at, checkout_at, late_minutes, work_minutes')
                ->from('att_daily')
                ->where('employee_id', $id)
                ->order_by('attendance_date', 'DESC')
                ->limit(31)
                ->get()->result_array();
        }

        $activeMenu = (string)($cfg['active_menu'] ?? 'grp.master');
        $this->render('master/detail_org_employee', [
            'title' => 'Detail Pegawai',
            'active_menu' => $activeMenu,
            'entity' => $entity,
            'cfg' => $cfg,
            'row' => $row,
            'linked_users' => $linkedUsers,
            'schedules' => $schedules,
            'att_daily_rows' => $attDaily,
        ]);
    }

    public function update(string $entity, int $id)
    {
        if ($this->redirect_contract_operational_if_needed($entity, 'update', $id)) {
            return;
        }
        $cfg = $this->entityConfig($entity);
        if (!$cfg) show_404();

        $row = $this->Master_model->get_by_id($cfg['table'], $id);
        if (!$row) show_404();

        $this->autofillCodeFromName($cfg, $id);
        $this->autofillOrgEmployeeCodeAndNip($cfg, $id);
        $this->applyValidation($cfg, $id);
        if ($this->form_validation->run() === false) {
            $this->session->set_flashdata('error', validation_errors('<li>', '</li>'));
            redirect('master/' . $entity . '/edit/' . $id);
        }

        $payload = $this->collectPayload($cfg, $id);
        if ($payload === null) {
            redirect('master/' . $entity . '/edit/' . $id);
            return;
        }

        if (($cfg['table'] ?? '') === 'att_location' && $this->db->field_exists('is_default', 'att_location')) {
            if (!empty($payload['is_default']) && (int)$payload['is_default'] === 1) {
                $this->db->where('id !=', $id)->set('is_default', 0)->update('att_location');
            }
        }

        if (($cfg['table'] ?? '') === 'mst_product') {
            $photoPayload = $this->handleProductPhotoUpload($row);
            if ($photoPayload === null) {
                redirect('master/' . $entity . '/edit/' . $id);
                return;
            }
            $payload = array_merge($payload, $photoPayload);
        }

        $this->Master_model->update($cfg['table'], $id, $payload);
        $this->session->set_flashdata('success', $cfg['title'] . ' berhasil diperbarui.');
        redirect('master/' . $entity);
    }

    public function toggle(string $entity, int $id)
    {
        if ($this->redirect_contract_operational_if_needed($entity, 'toggle', $id)) {
            return;
        }
        $cfg = $this->entityConfig($entity);
        if (!$cfg) show_404();
        if (empty($cfg['toggle'])) show_404();

        $row = $this->Master_model->get_by_id($cfg['table'], $id);
        if (!$row) show_404();

        $this->Master_model->toggle_active($cfg['table'], $id);
        $this->session->set_flashdata('success', 'Status berhasil diubah.');
        redirect('master/' . $entity);
    }

    public function att_holiday_generate_year()
    {
        $this->require_permission('attendance.holiday.index', 'create');
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $year = (int)$this->input->post('year', true);
        if ($year < 2000 || $year > 2100) {
            $this->session->set_flashdata('error', 'Tahun generate libur tidak valid.');
            redirect('master/att-holiday');
            return;
        }

        $sourceExists = $this->db->query(
            "SELECT COUNT(*) AS c
             FROM information_schema.tables
             WHERE table_schema = 'core' AND table_name = 'att_holiday_calendar'"
        )->row_array();
        if ((int)($sourceExists['c'] ?? 0) <= 0) {
            $this->session->set_flashdata('error', 'Tabel sumber core.att_holiday_calendar tidak ditemukan.');
            redirect('master/att-holiday');
            return;
        }

        $sourceRows = $this->db->query(
            "SELECT holiday_date, holiday_name, holiday_type
             FROM core.att_holiday_calendar
             WHERE holiday_date >= ? AND holiday_date <= ? AND COALESCE(is_active,1) = 1
             ORDER BY holiday_date ASC, holiday_name ASC",
            [$year . '-01-01', $year . '-12-31']
        )->result_array();

        if (empty($sourceRows)) {
            $this->session->set_flashdata('warning', 'Tidak ada data libur tahun ' . $year . ' di database core.');
            redirect('master/att-holiday');
            return;
        }

        $processed = 0;
        foreach ($sourceRows as $row) {
            $holidayDate = trim((string)($row['holiday_date'] ?? ''));
            $holidayName = trim((string)($row['holiday_name'] ?? ''));
            $holidayType = strtoupper(trim((string)($row['holiday_type'] ?? 'NATIONAL')));

            if ($holidayDate === '' || $holidayName === '') {
                continue;
            }
            if (!in_array($holidayType, ['NATIONAL', 'COMPANY', 'SPECIAL'], true)) {
                $holidayType = 'NATIONAL';
            }

            $payload = [
                'holiday_date' => $holidayDate,
                'holiday_name' => $holidayName,
                'holiday_type' => $holidayType,
                'source_ref' => 'CORE_GENERATE_' . $year,
                'is_active' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            $sql = $this->db->insert_string('att_holiday_calendar', $payload)
                . ' ON DUPLICATE KEY UPDATE'
                . ' holiday_type=VALUES(holiday_type),'
                . ' source_ref=VALUES(source_ref),'
                . ' is_active=1,'
                . ' updated_at=VALUES(updated_at)';
            $this->db->query($sql);
            $processed++;
        }

        $this->session->set_flashdata('success', 'Generate hari libur tahun ' . $year . ' selesai. Data diproses: ' . $processed . '.');
        redirect('master/att-holiday');
    }

    private function applyValidation(array $cfg, int $id = 0): void
    {
        foreach ($cfg['rules'] as $rule) {
            $this->form_validation->set_rules($rule[0], $rule[1], $rule[2]);
        }
    }

    private function collectPayload(array $cfg, int $id): ?array
    {
        $data = [];
        foreach ($cfg['fields'] as $f) {
            $name = $f['name'];
            if (!empty($f['readonly'])) {
                continue;
            }

            if (($f['type'] ?? 'text') === 'file') {
                continue;
            }

            if (($f['type'] ?? 'text') === 'checkbox') {
                $data[$name] = $this->input->post($name) ? 1 : 0;
                continue;
            }

            $val = $this->input->post($name, true);
            if ($val === '') {
                $val = null;
            }
            $data[$name] = $val;
        }

        if (!empty($cfg['code_column']) && !empty($cfg['code_input'])) {
            $code = trim((string)$data[$cfg['code_input']]);
            if ($code !== '') {
                $exists = $this->Master_model->exists_by_code($cfg['table'], $cfg['code_column'], $code, $id);
                if ($exists) {
                    $this->session->set_flashdata('error', 'Kode sudah dipakai.');
                    return null;
                }
            }
        }

        if (($cfg['table'] ?? '') === 'org_employee') {
            $nip = trim((string)($data['employee_nip'] ?? ''));
            if ($nip !== '') {
                $this->db->from('org_employee')->where('employee_nip', $nip);
                if ($id > 0) {
                    $this->db->where('id !=', $id);
                }
                if ($this->db->count_all_results() > 0) {
                    $this->session->set_flashdata('error', 'NIP sudah dipakai.');
                    return null;
                }
            }
        }

        if (($cfg['table'] ?? '') === 'mst_item') {
            $isMaterial = (int)($data['is_material'] ?? 0);
            $materialId = (int)($data['material_id'] ?? 0);

            if ($isMaterial === 1 && $materialId <= 0) {
                $newCode = trim((string)$this->input->post('new_material_code', true));
                $newName = trim((string)$this->input->post('new_material_name', true));
                $newHpp  = (float)$this->input->post('new_material_hpp', true);

                if ($newCode === '' || $newName === '') {
                    $this->session->set_flashdata('error', 'Jika item adalah bahan baku, pilih bahan baku existing atau isi kode/nama bahan baku baru.');
                    return null;
                }

                if ($this->Master_model->exists_by_code('mst_material', 'material_code', $newCode)) {
                    $this->session->set_flashdata('error', 'Kode bahan baku baru sudah dipakai.');
                    return null;
                }

                $materialId = $this->Master_model->insert('mst_material', [
                    'material_code' => $newCode,
                    'material_name' => $newName,
                    'item_category_id' => $data['item_category_id'] ?? null,
                    'content_uom_id' => $data['content_uom_id'] ?? null,
                    'hpp_standard' => $newHpp,
                    'is_active' => 1,
                ]);
                $data['material_id'] = $materialId;
            }

            if ($isMaterial === 0) {
                $data['material_id'] = null;
            }
        }

        if (($cfg['table'] ?? '') === 'mst_component' || ($cfg['table'] ?? '') === 'mst_product') {
            $scope = ($cfg['table'] ?? '') === 'mst_component' ? 'COMPONENT' : 'PRODUCT';
            $mode = strtoupper((string)($data['variable_cost_mode'] ?? 'DEFAULT'));
            if ($mode === '') {
                $mode = 'DEFAULT';
            }
            $data['variable_cost_mode'] = $mode;
            if ($mode === 'NONE') {
                $data['variable_cost_percent'] = 0;
            } elseif ($mode === 'DEFAULT') {
                $data['variable_cost_percent'] = $this->Master_model->get_variable_cost_default_percent($scope, 20.0);
            }

            if (($cfg['table'] ?? '') === 'mst_component' || ($cfg['table'] ?? '') === 'mst_product') {
                $opDiv = (int)($data['operational_division_id'] ?? 0);
                if (($cfg['table'] ?? '') === 'mst_product') {
                    $opDiv = (int)($data['default_operational_division_id'] ?? 0);
                }
                if ($opDiv <= 0) {
                    $defaultOpDiv = $this->defaultOperationalDivisionId();
                    if (($cfg['table'] ?? '') === 'mst_product') {
                        $data['default_operational_division_id'] = $defaultOpDiv;
                    } else {
                        $data['operational_division_id'] = $defaultOpDiv;
                    }
                }
            }
        }

        if (($cfg['table'] ?? '') === 'mst_purchase_type') {
            $behavior = strtoupper((string)($data['destination_behavior'] ?? 'REQUIRED'));
            if (!in_array($behavior, ['REQUIRED', 'NONE'], true)) {
                $behavior = 'REQUIRED';
            }
            $data['destination_behavior'] = $behavior;
            if ($behavior === 'NONE') {
                $data['default_destination'] = null;
            }
        }

        if (($cfg['table'] ?? '') === 'fin_company_account') {
            $accType = strtoupper((string)($data['account_type'] ?? 'BANK'));
            if (!in_array($accType, ['BANK', 'EWALLET', 'CASH', 'OTHER'], true)) {
                $accType = 'BANK';
            }
            $data['account_type'] = $accType;

            $currency = strtoupper(trim((string)($data['currency_code'] ?? 'IDR')));
            $data['currency_code'] = $currency !== '' ? $currency : 'IDR';
        }

        if (($cfg['table'] ?? '') === 'pur_payment_channel') {
            $data['channel_type'] = 'ACCOUNT';
        }

        if (($cfg['table'] ?? '') === 'pay_objective_override' && $id === 0) {
            $data['created_by'] = (int)($this->current_user['id'] ?? 0) ?: null;
        }

        if (($cfg['table'] ?? '') === 'mst_purchase_catalog') {
            $lineKind = strtoupper(trim((string)($data['line_kind'] ?? 'ITEM')));
            if (!in_array($lineKind, ['ITEM', 'MATERIAL', 'SERVICE', 'ASSET'], true)) {
                $lineKind = 'ITEM';
            }
            $data['line_kind'] = $lineKind;

            $vendorId = (int)($data['vendor_id'] ?? 0);
            $itemId = isset($data['item_id']) ? (int)$data['item_id'] : 0;
            $materialId = isset($data['material_id']) ? (int)$data['material_id'] : 0;
            $buyUomId = (int)($data['buy_uom_id'] ?? 0);
            $contentUomId = isset($data['content_uom_id']) ? (int)$data['content_uom_id'] : 0;
            $contentPerBuy = (float)($data['content_per_buy'] ?? 1);
            $factor = (float)($data['conversion_factor_to_content'] ?? $contentPerBuy);

            if ($vendorId <= 0) {
                $this->session->set_flashdata('error', 'Vendor wajib dipilih untuk Purchase Catalog.');
                return null;
            }
            if ($buyUomId <= 0) {
                $this->session->set_flashdata('error', 'UOM Beli wajib dipilih untuk Purchase Catalog.');
                return null;
            }
            if ($lineKind === 'ITEM' && $itemId <= 0) {
                $this->session->set_flashdata('error', 'Line kind ITEM wajib memiliki item_id.');
                return null;
            }
            if ($lineKind === 'MATERIAL' && $materialId <= 0) {
                $this->session->set_flashdata('error', 'Line kind MATERIAL wajib memiliki material_id.');
                return null;
            }

            if ($itemId <= 0) {
                $data['item_id'] = null;
            }
            if ($materialId <= 0) {
                $data['material_id'] = null;
            }

            if ($contentUomId <= 0) {
                $contentUomId = $buyUomId;
            }
            if ($contentPerBuy <= 0) {
                $contentPerBuy = 1;
            }
            if ($factor <= 0) {
                $factor = $contentPerBuy;
            }

            $data['content_uom_id'] = $contentUomId;
            $data['content_per_buy'] = round($contentPerBuy, 6);
            $data['conversion_factor_to_content'] = round($factor, 8);
            $data['profile_key'] = $this->generatePurchaseCatalogProfileKey($data);
        }

        return $data;
    }

    private function generatePurchaseCatalogProfileKey(array $data): string
    {
        return hash('sha256', implode('|', [
            strtoupper((string)($data['line_kind'] ?? 'ITEM')),
            (string)((int)($data['item_id'] ?? 0)),
            (string)((int)($data['material_id'] ?? 0)),
            (string)((int)($data['vendor_id'] ?? 0)),
            (string)((int)($data['buy_uom_id'] ?? 0)),
            (string)((int)($data['content_uom_id'] ?? 0)),
            number_format((float)($data['content_per_buy'] ?? 1), 6, '.', ''),
            strtoupper(trim((string)($data['brand_name'] ?? ''))),
            strtoupper(trim((string)($data['line_description'] ?? ''))),
        ]));
    }

    private function variableCostDefaultsForEntity(string $entity): array
    {
        if ($entity === 'component') {
            return ['component' => $this->Master_model->get_variable_cost_default_percent('COMPONENT', 20.0)];
        }
        if ($entity === 'product') {
            return ['product' => $this->Master_model->get_variable_cost_default_percent('PRODUCT', 20.0)];
        }
        return [];
    }

    private function handleProductPhotoUpload(?array $existingRow = null): ?array
    {
        if (empty($_FILES['photo_file']['name'])) {
            return [];
        }

        $uploadDir = FCPATH . 'uploads/product';
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            $this->session->set_flashdata('error', 'Folder upload foto produk tidak bisa dibuat.');
            return null;
        }

        $config = [
            'upload_path' => $uploadDir,
            'allowed_types' => 'jpg|jpeg|png|webp|gif',
            'max_size' => 4096,
            'encrypt_name' => true,
            'remove_spaces' => true,
        ];

        $this->load->library('upload', $config);
        if (!$this->upload->do_upload('photo_file')) {
            $this->session->set_flashdata('error', strip_tags((string)$this->upload->display_errors('', '')));
            return null;
        }

        $up = $this->upload->data();
        $relativePath = 'uploads/product/' . $up['file_name'];

        $oldPath = (string)($existingRow['photo_path'] ?? '');
        if ($oldPath !== '') {
            $oldAbs = FCPATH . ltrim(str_replace('\\', '/', $oldPath), '/');
            if (is_file($oldAbs)) {
                @unlink($oldAbs);
            }
        }

        return [
            'photo_path' => $relativePath,
            'photo_mime' => (string)($up['file_type'] ?? ''),
        ];
    }

    private function autofillCodeFromName(array $cfg, int $id = 0): void
    {
        if (empty($cfg['auto_code_source']) || empty($cfg['code_input']) || empty($cfg['code_column'])) {
            return;
        }

        $codeInput = (string)$cfg['code_input'];
        $sourceInput = (string)$cfg['auto_code_source'];
        $existingCode = trim((string)$this->input->post($codeInput, true));
        if ($existingCode !== '') {
            return;
        }

        $nameValue = trim((string)$this->input->post($sourceInput, true));
        if ($nameValue === '') {
            return;
        }

        $maxLen = $this->findFieldMaxLength($cfg, $codeInput, 50);
        $generated = $this->Master_model->generate_unique_code(
            $cfg['table'],
            $cfg['code_column'],
            $nameValue,
            $id,
            $maxLen
        );

        $_POST[$codeInput] = $generated;
    }

    private function autofillOrgEmployeeCodeAndNip(array $cfg, int $id = 0): void
    {
        if (($cfg['table'] ?? '') !== 'org_employee') {
            return;
        }

        $joinDateRaw = trim((string)$this->input->post('join_date', true));
        $joinTs = strtotime($joinDateRaw ?: date('Y-m-d'));
        if ($joinTs === false) {
            $joinTs = strtotime(date('Y-m-d'));
        }
        $joinDate = date('Y-m-d', $joinTs);
        $joinYm = date('Ym', $joinTs);

        $birthDateRaw = trim((string)$this->input->post('birth_date', true));
        $birthTs = strtotime($birthDateRaw);
        $birthYmd = $birthTs !== false ? date('Ymd', $birthTs) : '00000000';
        $joinYmd = date('Ymd', $joinTs);

        $existingNip = strtoupper(trim((string)$this->input->post('employee_nip', true)));
        $existingCode = strtoupper(trim((string)$this->input->post('employee_code', true)));

        if ($id === 0 || $existingNip === '') {
            $existingNip = $this->generateEmployeeNip($joinYmd, $birthYmd, $id);
            $_POST['employee_nip'] = $existingNip;
        }

        if ($id === 0 || $existingCode === '') {
            $existingCode = $this->generateEmployeeCode($joinYm, $id);
            $_POST['employee_code'] = $existingCode;
        }

        if ($joinDateRaw === '') {
            $_POST['join_date'] = $joinDate;
        }
    }

    private function generateEmployeeNip(string $joinYmd, string $birthYmd, int $excludeId = 0): string
    {
        $prefix = $joinYmd . $birthYmd;
        $maxSeq = 0;

        $rows = $this->db->select('employee_nip')
            ->from('org_employee')
            ->like('employee_nip', $prefix, 'after')
            ->get()->result_array();

        foreach ($rows as $row) {
            $nip = strtoupper((string)($row['employee_nip'] ?? ''));
            if (preg_match('/^' . preg_quote($prefix, '/') . '(\d{3})$/', $nip, $m)) {
                $seq = (int)$m[1];
                if ($seq > $maxSeq) {
                    $maxSeq = $seq;
                }
            }
        }

        $seq = $maxSeq + 1;
        while ($seq <= 9999) {
            $candidate = $prefix . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
            $this->db->from('org_employee')->where('employee_nip', $candidate);
            if ($excludeId > 0) {
                $this->db->where('id !=', $excludeId);
            }
            if ($this->db->count_all_results() === 0) {
                return $candidate;
            }
            $seq++;
        }

        return $prefix . date('His');
    }

    private function generateEmployeeCode(string $joinYm, int $excludeId = 0): string
    {
        $prefix = 'EMP-' . $joinYm;
        $maxSeq = 0;
        $rows = $this->db->select('employee_code')
            ->from('org_employee')
            ->like('employee_code', $prefix, 'after')
            ->get()->result_array();

        foreach ($rows as $row) {
            $code = strtoupper((string)($row['employee_code'] ?? ''));
            if (preg_match('/^' . preg_quote($prefix, '/') . '(\d{4,})$/', $code, $m)) {
                $seq = (int)$m[1];
                if ($seq > $maxSeq) {
                    $maxSeq = $seq;
                }
            }
        }

        $seq = $maxSeq + 1;
        while ($seq <= 999999) {
            $candidate = $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
            $exists = $this->Master_model->exists_by_code('org_employee', 'employee_code', $candidate, $excludeId);
            if (!$exists) {
                return $candidate;
            }
            $seq++;
        }

        return $prefix . date('His');
    }

    private function findFieldMaxLength(array $cfg, string $fieldName, int $default = 50): int
    {
        foreach (($cfg['rules'] ?? []) as $rule) {
            if (($rule[0] ?? '') !== $fieldName) {
                continue;
            }
            $ruleStr = (string)($rule[2] ?? '');
            if (preg_match('/max_length\[(\d+)\]/', $ruleStr, $m)) {
                return (int)$m[1];
            }
        }
        return $default;
    }

    private function formOptions(array $cfg): array
    {
        $opts = [];
        foreach ($cfg['fields'] as $f) {
            if (($f['type'] ?? '') !== 'select') continue;
            $name = $f['name'];
            if (!empty($f['options'])) {
                $opts[$name] = $f['options'];
                continue;
            }
            if (!empty($f['lookup'])) {
                $l = $f['lookup'];
                $opts[$name] = $this->Master_model->get_options($l['table'], $l['value'], $l['label'], $l['active_only'] ?? true);
            }
        }
        return $opts;
    }

    private function buildLookupMaps(array $cfg): array
    {
        $maps = [];
        foreach ($cfg['fields'] as $f) {
            if (empty($f['lookup'])) continue;
            $l = $f['lookup'];
            $rows = $this->Master_model->get_options($l['table'], $l['value'], $l['label'], false);
            $map = [];
            foreach ($rows as $r) {
                $map[$r['value']] = $r['label'];
            }
            $maps[$f['name']] = $map;
        }
        return $maps;
    }

    private function decorateRows(array $rows, array $cfg, array $lookupMaps): array
    {
        foreach ($rows as &$row) {
            foreach ($lookupMaps as $field => $map) {
                $row[$field . '_label'] = isset($row[$field]) && isset($map[$row[$field]]) ? $map[$row[$field]] : ($row[$field] ?? '-');
            }
        }
        return $rows;
    }

    private function entityConfig(string $entity): ?array
    {
        if ($entity === 'payment-channel') {
            return null;
        }
        $all = $this->entities();
        return $all[$entity] ?? null;
    }

    private function entityNav(): array
    {
        $all = $this->entities();
        $nav = [];
        foreach ($all as $key => $cfg) {
            $nav[] = [
                'key' => $key,
                'title' => $cfg['title'],
            ];
        }
        return $nav;
    }

    private function entities(): array
    {
        return [
            'uom' => [
                'table' => 'mst_uom',
                'title' => 'Master Satuan (UOM)',
                'order_by' => 'name',
                'order_dir' => 'ASC',
                'searchable' => ['code', 'name'],
                'toggle' => true,
                'code_column' => 'code',
                'code_input' => 'code',
                'rules' => [
                    ['code', 'Kode', 'required|trim|max_length[30]'],
                    ['name', 'Nama', 'required|trim|max_length[100]'],
                ],
                'fields' => [
                    ['name' => 'code', 'label' => 'Kode', 'type' => 'text'],
                    ['name' => 'name', 'label' => 'Nama', 'type' => 'text'],
                    ['name' => 'description', 'label' => 'Deskripsi', 'type' => 'textarea'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'code', 'label' => 'Kode'],
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'description', 'label' => 'Deskripsi'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'operational-division' => [
                'table' => 'mst_operational_division',
                'title' => 'Master Divisi Operasional',
                'order_by' => 'sort_order',
                'order_dir' => 'ASC',
                'searchable' => ['code', 'name'],
                'toggle' => true,
                'code_column' => 'code',
                'code_input' => 'code',
                'rules' => [
                    ['code', 'Kode', 'required|trim|max_length[40]'],
                    ['name', 'Nama', 'required|trim|max_length[120]'],
                ],
                'fields' => [
                    ['name' => 'code', 'label' => 'Kode', 'type' => 'text'],
                    ['name' => 'name', 'label' => 'Nama', 'type' => 'text'],
                    ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'code', 'label' => 'Kode'],
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'sort_order', 'label' => 'Urutan'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'org-division' => [
                'table' => 'org_division',
                'title' => 'Master Divisi Organisasi',
                'order_by' => 'sort_order',
                'order_dir' => 'ASC',
                'searchable' => ['division_code', 'division_name'],
                'toggle' => true,
                'code_column' => 'division_code',
                'code_input' => 'division_code',
                'rules' => [
                    ['division_code', 'Kode Divisi', 'required|trim|max_length[40]'],
                    ['division_name', 'Nama Divisi', 'required|trim|max_length[120]'],
                ],
                'fields' => [
                    ['name' => 'division_code', 'label' => 'Kode Divisi', 'type' => 'text'],
                    ['name' => 'division_name', 'label' => 'Nama Divisi', 'type' => 'text'],
                    ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'division_code', 'label' => 'Kode'],
                    ['key' => 'division_name', 'label' => 'Nama Divisi'],
                    ['key' => 'sort_order', 'label' => 'Urutan'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'org-position' => [
                'table' => 'org_position',
                'title' => 'Master Jabatan',
                'order_by' => 'sort_order',
                'order_dir' => 'ASC',
                'searchable' => ['position_code', 'position_name'],
                'toggle' => true,
                'code_column' => 'position_code',
                'code_input' => 'position_code',
                'rules' => [
                    ['division_id', 'Divisi', 'required|integer'],
                    ['position_code', 'Kode Jabatan', 'required|trim|max_length[40]'],
                    ['position_name', 'Nama Jabatan', 'required|trim|max_length[120]'],
                ],
                'fields' => [
                    ['name' => 'division_id', 'label' => 'Divisi', 'type' => 'select', 'lookup' => ['table' => 'org_division', 'value' => 'id', 'label' => 'division_name']],
                    ['name' => 'position_code', 'label' => 'Kode Jabatan', 'type' => 'text'],
                    ['name' => 'position_name', 'label' => 'Nama Jabatan', 'type' => 'text'],
                    ['name' => 'default_role_id', 'label' => 'Default Role', 'type' => 'select', 'lookup' => ['table' => 'auth_role', 'value' => 'id', 'label' => 'role_name', 'active_only' => false]],
                    ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'division_id_label', 'label' => 'Divisi'],
                    ['key' => 'position_code', 'label' => 'Kode'],
                    ['key' => 'position_name', 'label' => 'Nama Jabatan'],
                    ['key' => 'default_role_id_label', 'label' => 'Default Role'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'org-employee' => [
                'table' => 'org_employee',
                'title' => 'Master Pegawai',
                'active_menu' => 'grp.hr',
                'order_by' => 'employee_name',
                'order_dir' => 'ASC',
                'searchable' => ['employee_code', 'employee_nip', 'employee_name', 'mobile_phone', 'email'],
                'toggle' => true,
                'code_column' => 'employee_code',
                'code_input' => 'employee_code',
                'rules' => [
                    ['employee_code', 'Kode Pegawai', 'trim|max_length[50]'],
                    ['employee_nip', 'NIP', 'trim|max_length[24]'],
                    ['employee_name', 'Nama Pegawai', 'required|trim|max_length[150]'],
                    ['division_id', 'Divisi', 'integer'],
                    ['position_id', 'Jabatan', 'integer'],
                    ['employment_status', 'Status Kerja', 'required|trim|in_list[PERMANENT,CONTRACT,PROBATION,DAILY,RESIGNED]'],
                ],
                'fields' => [
                    ['name' => 'employee_code', 'label' => 'Kode Pegawai', 'type' => 'text'],
                    ['name' => 'employee_nip', 'label' => 'NIP', 'type' => 'text'],
                    ['name' => 'employee_name', 'label' => 'Nama Pegawai', 'type' => 'text'],
                    ['name' => 'gender', 'label' => 'Gender', 'type' => 'select', 'options' => [
                        ['value' => 'L', 'label' => 'Laki-laki'],
                        ['value' => 'P', 'label' => 'Perempuan'],
                    ]],
                    ['name' => 'birth_date', 'label' => 'Tanggal Lahir (YYYY-MM-DD)', 'type' => 'text'],
                    ['name' => 'join_date', 'label' => 'Tanggal Bergabung (YYYY-MM-DD)', 'type' => 'text'],
                    ['name' => 'mobile_phone', 'label' => 'No HP', 'type' => 'text'],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'text'],
                    ['name' => 'address', 'label' => 'Alamat', 'type' => 'textarea'],
                    ['name' => 'division_id', 'label' => 'Divisi', 'type' => 'select', 'lookup' => ['table' => 'org_division', 'value' => 'id', 'label' => 'division_name']],
                    ['name' => 'position_id', 'label' => 'Jabatan', 'type' => 'select', 'lookup' => ['table' => 'org_position', 'value' => 'id', 'label' => 'position_name']],
                    ['name' => 'employment_status', 'label' => 'Status Kerja', 'type' => 'select', 'options' => [
                        ['value' => 'PERMANENT', 'label' => 'PERMANENT'],
                        ['value' => 'CONTRACT', 'label' => 'CONTRACT'],
                        ['value' => 'PROBATION', 'label' => 'PROBATION'],
                        ['value' => 'DAILY', 'label' => 'DAILY'],
                        ['value' => 'RESIGNED', 'label' => 'RESIGNED'],
                    ]],
                    ['name' => 'basic_salary', 'label' => 'Gaji Pokok', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'position_allowance', 'label' => 'Tunjangan Jabatan', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'objective_allowance', 'label' => 'Tunjangan Objektif', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'meal_rate', 'label' => 'Rate Uang Makan', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'overtime_rate', 'label' => 'Rate Lembur/Jam', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'bank_name', 'label' => 'Bank', 'type' => 'text'],
                    ['name' => 'bank_account_no', 'label' => 'No Rekening', 'type' => 'text'],
                    ['name' => 'bank_account_name', 'label' => 'Nama Rekening', 'type' => 'text'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'employee_code', 'label' => 'Kode'],
                    ['key' => 'employee_nip', 'label' => 'NIP'],
                    ['key' => 'employee_name', 'label' => 'Nama'],
                    ['key' => 'division_id_label', 'label' => 'Divisi'],
                    ['key' => 'position_id_label', 'label' => 'Jabatan'],
                    ['key' => 'employment_status', 'label' => 'Status Kerja'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'att-shift' => [
                'table' => 'att_shift',
                'title' => 'Master Shift',
                'order_by' => 'shift_name',
                'order_dir' => 'ASC',
                'searchable' => ['shift_code', 'shift_name'],
                'toggle' => true,
                'code_column' => 'shift_code',
                'code_input' => 'shift_code',
                'rules' => [
                    ['shift_code', 'Kode Shift', 'required|trim|max_length[40]'],
                    ['shift_name', 'Nama Shift', 'required|trim|max_length[120]'],
                    ['start_time', 'Jam Mulai', 'required|trim'],
                    ['end_time', 'Jam Selesai', 'required|trim'],
                ],
                'fields' => [
                    ['name' => 'division_id', 'label' => 'Divisi', 'type' => 'select', 'lookup' => ['table' => 'org_division', 'value' => 'id', 'label' => 'division_name']],
                    ['name' => 'shift_code', 'label' => 'Kode Shift', 'type' => 'text'],
                    ['name' => 'shift_name', 'label' => 'Nama Shift', 'type' => 'text'],
                    ['name' => 'start_time', 'label' => 'Jam Mulai (HH:MM:SS)', 'type' => 'text'],
                    ['name' => 'end_time', 'label' => 'Jam Selesai (HH:MM:SS)', 'type' => 'text'],
                    ['name' => 'is_overnight', 'label' => 'Overnight', 'type' => 'checkbox'],
                    ['name' => 'grace_late_minute', 'label' => 'Grace Telat (menit)', 'type' => 'number'],
                    ['name' => 'overtime_after_minute', 'label' => 'Lembur Setelah (menit)', 'type' => 'number'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'division_id_label', 'label' => 'Divisi'],
                    ['key' => 'shift_code', 'label' => 'Kode'],
                    ['key' => 'shift_name', 'label' => 'Nama Shift'],
                    ['key' => 'start_time', 'label' => 'Mulai'],
                    ['key' => 'end_time', 'label' => 'Selesai'],
                    ['key' => 'is_overnight', 'label' => 'Overnight', 'type' => 'bool'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'att-location' => [
                'table' => 'att_location',
                'title' => 'Master Lokasi Absensi',
                'order_by' => 'location_name',
                'order_dir' => 'ASC',
                'searchable' => ['location_code', 'location_name'],
                'toggle' => true,
                'code_column' => 'location_code',
                'code_input' => 'location_code',
                'rules' => [
                    ['location_code', 'Kode Lokasi', 'required|trim|max_length[40]'],
                    ['location_name', 'Nama Lokasi', 'required|trim|max_length[120]'],
                    ['radius_meter', 'Radius', 'required|numeric'],
                ],
                'fields' => [
                    ['name' => 'location_code', 'label' => 'Kode Lokasi', 'type' => 'text'],
                    ['name' => 'location_name', 'label' => 'Nama Lokasi', 'type' => 'text'],
                    ['name' => 'latitude', 'label' => 'Latitude', 'type' => 'number', 'step' => '0.0000001'],
                    ['name' => 'longitude', 'label' => 'Longitude', 'type' => 'number', 'step' => '0.0000001'],
                    ['name' => 'radius_meter', 'label' => 'Radius (meter)', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'is_default', 'label' => 'Lokasi Default Absensi', 'type' => 'checkbox'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'location_code', 'label' => 'Kode'],
                    ['key' => 'location_name', 'label' => 'Nama Lokasi'],
                    ['key' => 'radius_meter', 'label' => 'Radius'],
                    ['key' => 'is_default', 'label' => 'Default', 'type' => 'bool'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'att-overtime-standard' => [
                'table' => 'att_overtime_standard',
                'title' => 'Master Standar Lembur',
                'active_menu' => 'hr.att-overtime',
                'order_by' => 'hourly_rate',
                'order_dir' => 'ASC',
                'searchable' => ['standard_code', 'standard_name', 'notes'],
                'toggle' => true,
                'code_column' => 'standard_code',
                'code_input' => 'standard_code',
                'rules' => [
                    ['standard_code', 'Kode Standar', 'required|trim|max_length[40]'],
                    ['standard_name', 'Nama Standar', 'required|trim|max_length[120]'],
                    ['hourly_rate', 'Nilai per Jam', 'required|numeric'],
                ],
                'fields' => [
                    ['name' => 'standard_code', 'label' => 'Kode Standar', 'type' => 'text'],
                    ['name' => 'standard_name', 'label' => 'Nama Standar', 'type' => 'text'],
                    ['name' => 'hourly_rate', 'label' => 'Tarif per Jam', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'notes', 'label' => 'Catatan', 'type' => 'textarea'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'standard_code', 'label' => 'Kode'],
                    ['key' => 'standard_name', 'label' => 'Nama Standar'],
                    ['key' => 'hourly_rate', 'label' => 'Tarif/Jam'],
                    ['key' => 'notes', 'label' => 'Catatan'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'att-holiday' => [
                'table' => 'att_holiday_calendar',
                'title' => 'Master Hari Libur',
                'order_by' => 'holiday_date',
                'order_dir' => 'DESC',
                'searchable' => ['holiday_name', 'holiday_type', 'source_ref'],
                'toggle' => true,
                'rules' => [
                    ['holiday_date', 'Tanggal Libur', 'required|trim|max_length[10]'],
                    ['holiday_name', 'Nama Libur', 'required|trim|max_length[150]'],
                    ['holiday_type', 'Tipe Libur', 'required|trim|in_list[NATIONAL,COMPANY,SPECIAL]'],
                ],
                'fields' => [
                    ['name' => 'holiday_date', 'label' => 'Tanggal Libur (YYYY-MM-DD)', 'type' => 'text'],
                    ['name' => 'holiday_name', 'label' => 'Nama Libur', 'type' => 'text'],
                    ['name' => 'holiday_type', 'label' => 'Tipe', 'type' => 'select', 'options' => [
                        ['value' => 'NATIONAL', 'label' => 'NATIONAL'],
                        ['value' => 'COMPANY', 'label' => 'COMPANY'],
                        ['value' => 'SPECIAL', 'label' => 'SPECIAL'],
                    ]],
                    ['name' => 'source_ref', 'label' => 'Sumber', 'type' => 'text'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'holiday_date', 'label' => 'Tanggal'],
                    ['key' => 'holiday_name', 'label' => 'Nama Libur'],
                    ['key' => 'holiday_type', 'label' => 'Tipe'],
                    ['key' => 'source_ref', 'label' => 'Sumber'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'pay-component' => [
                'table' => 'pay_salary_component',
                'title' => 'Master Komponen Gaji',
                'active_menu' => 'pay.component',
                'order_by' => 'sort_order',
                'order_dir' => 'ASC',
                'searchable' => ['component_code', 'component_name'],
                'toggle' => true,
                'code_column' => 'component_code',
                'code_input' => 'component_code',
                'rules' => [
                    ['component_code', 'Kode Komponen', 'required|trim|max_length[50]'],
                    ['component_name', 'Nama Komponen', 'required|trim|max_length[150]'],
                    ['component_type', 'Tipe Komponen', 'required|trim|in_list[EARNING,DEDUCTION]'],
                    ['calc_method', 'Metode Hitung', 'required|trim|in_list[FIXED,PER_DAY,PER_HOUR,PER_MINUTE,FORMULA]'],
                ],
                'fields' => [
                    ['name' => 'component_code', 'label' => 'Kode Komponen', 'type' => 'text'],
                    ['name' => 'component_name', 'label' => 'Nama Komponen', 'type' => 'text'],
                    ['name' => 'component_type', 'label' => 'Tipe Komponen', 'type' => 'select', 'options' => [
                        ['value' => 'EARNING', 'label' => 'EARNING'],
                        ['value' => 'DEDUCTION', 'label' => 'DEDUCTION'],
                    ]],
                    ['name' => 'calc_method', 'label' => 'Metode Hitung', 'type' => 'select', 'options' => [
                        ['value' => 'FIXED', 'label' => 'FIXED'],
                        ['value' => 'PER_DAY', 'label' => 'PER_DAY'],
                        ['value' => 'PER_HOUR', 'label' => 'PER_HOUR'],
                        ['value' => 'PER_MINUTE', 'label' => 'PER_MINUTE'],
                        ['value' => 'FORMULA', 'label' => 'FORMULA'],
                    ]],
                    ['name' => 'default_amount', 'label' => 'Nilai Default', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'affects_attendance', 'label' => 'Terdampak Absensi', 'type' => 'checkbox'],
                    ['name' => 'affects_bpjs_base', 'label' => 'Basis BPJS', 'type' => 'checkbox'],
                    ['name' => 'is_taxable', 'label' => 'Kena Pajak', 'type' => 'checkbox'],
                    ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number'],
                    ['name' => 'notes', 'label' => 'Catatan', 'type' => 'textarea'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'component_code', 'label' => 'Kode'],
                    ['key' => 'component_name', 'label' => 'Nama'],
                    ['key' => 'component_type', 'label' => 'Tipe'],
                    ['key' => 'calc_method', 'label' => 'Metode'],
                    ['key' => 'default_amount', 'label' => 'Default'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'pay-profile' => [
                'table' => 'pay_salary_profile',
                'title' => 'Master Profil Gaji',
                'active_menu' => 'pay.profile',
                'order_by' => 'profile_name',
                'order_dir' => 'ASC',
                'searchable' => ['profile_code', 'profile_name'],
                'toggle' => true,
                'code_column' => 'profile_code',
                'code_input' => 'profile_code',
                'rules' => [
                    ['profile_code', 'Kode Profil', 'required|trim|max_length[50]'],
                    ['profile_name', 'Nama Profil', 'required|trim|max_length[150]'],
                ],
                'fields' => [
                    ['name' => 'profile_code', 'label' => 'Kode Profil', 'type' => 'text'],
                    ['name' => 'profile_name', 'label' => 'Nama Profil', 'type' => 'text'],
                    ['name' => 'effective_start', 'label' => 'Efektif Mulai (YYYY-MM-DD)', 'type' => 'text'],
                    ['name' => 'effective_end', 'label' => 'Efektif Selesai (YYYY-MM-DD)', 'type' => 'text'],
                    ['name' => 'notes', 'label' => 'Catatan', 'type' => 'textarea'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'profile_code', 'label' => 'Kode'],
                    ['key' => 'profile_name', 'label' => 'Nama Profil'],
                    ['key' => 'effective_start', 'label' => 'Mulai'],
                    ['key' => 'effective_end', 'label' => 'Selesai'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'pay-assignment' => [
                'table' => 'pay_salary_assignment',
                'title' => 'Assignment Profil Gaji',
                'active_menu' => 'pay.assignment',
                'order_by' => 'effective_start',
                'order_dir' => 'DESC',
                'searchable' => ['notes'],
                'toggle' => true,
                'rules' => [
                    ['employee_id', 'Pegawai', 'required|integer'],
                    ['profile_id', 'Profil Gaji', 'required|integer'],
                    ['effective_start', 'Efektif Mulai', 'required|trim|max_length[10]'],
                ],
                'fields' => [
                    ['name' => 'employee_id', 'label' => 'Pegawai', 'type' => 'select', 'lookup' => ['table' => 'org_employee', 'value' => 'id', 'label' => 'employee_name', 'active_only' => false]],
                    ['name' => 'profile_id', 'label' => 'Profil Gaji', 'type' => 'select', 'lookup' => ['table' => 'pay_salary_profile', 'value' => 'id', 'label' => 'profile_name', 'active_only' => false]],
                    ['name' => 'effective_start', 'label' => 'Efektif Mulai (YYYY-MM-DD)', 'type' => 'text'],
                    ['name' => 'effective_end', 'label' => 'Efektif Selesai (YYYY-MM-DD)', 'type' => 'text'],
                    ['name' => 'notes', 'label' => 'Catatan', 'type' => 'textarea'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'employee_id_label', 'label' => 'Pegawai'],
                    ['key' => 'profile_id_label', 'label' => 'Profil'],
                    ['key' => 'effective_start', 'label' => 'Mulai'],
                    ['key' => 'effective_end', 'label' => 'Selesai'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'pay-basic-salary' => [
                'table' => 'pay_basic_salary_standard',
                'title' => 'Standar Gaji Pokok',
                'active_menu' => 'pay.basic-salary',
                'order_by' => 'effective_start',
                'order_dir' => 'DESC',
                'searchable' => ['standard_code', 'standard_name', 'employment_type', 'notes'],
                'toggle' => true,
                'code_column' => 'standard_code',
                'code_input' => 'standard_code',
                'rules' => [
                    ['standard_code', 'Kode Standar', 'required|trim|max_length[60]'],
                    ['standard_name', 'Nama Standar', 'required|trim|max_length[120]'],
                    ['effective_start', 'Mulai Berlaku', 'required|trim|max_length[10]'],
                    ['start_amount', 'Gaji Awal', 'required|numeric'],
                    ['annual_increment', 'Kenaikan Tahunan', 'required|numeric'],
                ],
                'fields' => [
                    ['name' => 'standard_code', 'label' => 'Kode Standar', 'type' => 'text'],
                    ['name' => 'standard_name', 'label' => 'Nama Standar', 'type' => 'text'],
                    ['name' => 'division_id', 'label' => 'Divisi', 'type' => 'select', 'lookup' => ['table' => 'org_division', 'value' => 'id', 'label' => 'division_name', 'active_only' => false]],
                    ['name' => 'position_id', 'label' => 'Jabatan', 'type' => 'select', 'lookup' => ['table' => 'org_position', 'value' => 'id', 'label' => 'position_name', 'active_only' => false]],
                    ['name' => 'employment_type', 'label' => 'Status Kerja (Opsional)', 'type' => 'select', 'options' => [
                        ['value' => 'PERMANENT', 'label' => 'PERMANENT'],
                        ['value' => 'CONTRACT', 'label' => 'CONTRACT'],
                        ['value' => 'PROBATION', 'label' => 'PROBATION'],
                        ['value' => 'DAILY', 'label' => 'DAILY'],
                    ]],
                    ['name' => 'effective_start', 'label' => 'Efektif Mulai (YYYY-MM-DD)', 'type' => 'text'],
                    ['name' => 'effective_end', 'label' => 'Efektif Selesai (YYYY-MM-DD)', 'type' => 'text'],
                    ['name' => 'start_amount', 'label' => 'Gaji Awal', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'annual_increment', 'label' => 'Kenaikan Tahunan', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'year_cap', 'label' => 'Maks Tahun Kenaikan', 'type' => 'number'],
                    ['name' => 'notes', 'label' => 'Catatan', 'type' => 'textarea'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'standard_code', 'label' => 'Kode'],
                    ['key' => 'standard_name', 'label' => 'Nama Standar'],
                    ['key' => 'division_id_label', 'label' => 'Divisi'],
                    ['key' => 'position_id_label', 'label' => 'Jabatan'],
                    ['key' => 'employment_type', 'label' => 'Status Kerja'],
                    ['key' => 'start_amount', 'label' => 'Gaji Awal'],
                    ['key' => 'annual_increment', 'label' => 'Kenaikan'],
                    ['key' => 'effective_start', 'label' => 'Mulai'],
                    ['key' => 'effective_end', 'label' => 'Selesai'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'pay-objective-override' => [
                'table' => 'pay_objective_override',
                'title' => 'Override Tunjangan Objektif',
                'active_menu' => 'pay.objective-override',
                'order_by' => 'effective_start',
                'order_dir' => 'DESC',
                'searchable' => ['reason'],
                'toggle' => true,
                'rules' => [
                    ['employee_id', 'Pegawai', 'required|integer'],
                    ['effective_start', 'Mulai Berlaku', 'required|trim|max_length[10]'],
                    ['override_amount', 'Nominal Override', 'required|numeric'],
                ],
                'fields' => [
                    ['name' => 'employee_id', 'label' => 'Pegawai', 'type' => 'select', 'lookup' => ['table' => 'org_employee', 'value' => 'id', 'label' => 'employee_name', 'active_only' => false]],
                    ['name' => 'effective_start', 'label' => 'Efektif Mulai (YYYY-MM-DD)', 'type' => 'text'],
                    ['name' => 'effective_end', 'label' => 'Efektif Selesai (YYYY-MM-DD)', 'type' => 'text'],
                    ['name' => 'override_amount', 'label' => 'Nominal Override', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'reason', 'label' => 'Alasan/Catatan', 'type' => 'textarea'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'employee_id_label', 'label' => 'Pegawai'],
                    ['key' => 'override_amount', 'label' => 'Nominal'],
                    ['key' => 'effective_start', 'label' => 'Mulai'],
                    ['key' => 'effective_end', 'label' => 'Selesai'],
                    ['key' => 'reason', 'label' => 'Alasan'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'pay-profile-line' => [
                'table' => 'pay_salary_profile_line',
                'title' => 'Komponen per Profil Gaji',
                'active_menu' => 'grp.payroll',
                'order_by' => 'sort_order',
                'order_dir' => 'ASC',
                'searchable' => ['formula_expr'],
                'toggle' => true,
                'rules' => [
                    ['profile_id', 'Profil Gaji', 'required|integer'],
                    ['component_id', 'Komponen Gaji', 'required|integer'],
                    ['amount', 'Nilai', 'required|numeric'],
                ],
                'fields' => [
                    ['name' => 'profile_id', 'label' => 'Profil Gaji', 'type' => 'select', 'lookup' => ['table' => 'pay_salary_profile', 'value' => 'id', 'label' => 'profile_name', 'active_only' => false]],
                    ['name' => 'component_id', 'label' => 'Komponen', 'type' => 'select', 'lookup' => ['table' => 'pay_salary_component', 'value' => 'id', 'label' => 'component_name', 'active_only' => false]],
                    ['name' => 'amount', 'label' => 'Nilai', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'formula_expr', 'label' => 'Formula (opsional)', 'type' => 'text'],
                    ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'profile_id_label', 'label' => 'Profil'],
                    ['key' => 'component_id_label', 'label' => 'Komponen'],
                    ['key' => 'amount', 'label' => 'Nilai'],
                    ['key' => 'formula_expr', 'label' => 'Formula'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'hr-contract-template' => [
                'table' => 'hr_contract_template',
                'title' => 'Master Template Kontrak',
                'active_menu' => 'grp.hr',
                'order_by' => 'template_name',
                'order_dir' => 'ASC',
                'searchable' => ['template_code', 'template_name'],
                'toggle' => true,
                'code_column' => 'template_code',
                'code_input' => 'template_code',
                'rules' => [
                    ['template_code', 'Kode Template', 'required|trim|max_length[30]'],
                    ['template_name', 'Nama Template', 'required|trim|max_length[120]'],
                    ['contract_type', 'Jenis Kontrak', 'required|trim|in_list[K1,K2,K3,CUSTOM]'],
                ],
                'fields' => [
                    ['name' => 'template_code', 'label' => 'Kode Template', 'type' => 'text'],
                    ['name' => 'template_name', 'label' => 'Nama Template', 'type' => 'text'],
                    ['name' => 'contract_type', 'label' => 'Jenis Kontrak', 'type' => 'select', 'options' => [
                        ['value' => 'K1', 'label' => 'K1'],
                        ['value' => 'K2', 'label' => 'K2'],
                        ['value' => 'K3', 'label' => 'K3'],
                        ['value' => 'CUSTOM', 'label' => 'CUSTOM'],
                    ]],
                    ['name' => 'duration_months', 'label' => 'Durasi (bulan)', 'type' => 'number'],
                    ['name' => 'body_html', 'label' => 'Template Dokumen (HTML)', 'type' => 'textarea'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'template_code', 'label' => 'Kode'],
                    ['key' => 'template_name', 'label' => 'Nama Template'],
                    ['key' => 'contract_type', 'label' => 'Jenis'],
                    ['key' => 'duration_months', 'label' => 'Durasi (bulan)'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'hr-contract' => [
                'table' => 'hr_contract',
                'title' => 'Master Kontrak Pegawai',
                'active_menu' => 'grp.hr',
                'order_by' => 'start_date',
                'order_dir' => 'DESC',
                'searchable' => ['contract_number', 'position_snapshot', 'division_snapshot', 'notes'],
                'toggle' => false,
                'code_column' => 'contract_number',
                'code_input' => 'contract_number',
                'rules' => [
                    ['contract_number', 'Nomor Kontrak', 'required|trim|max_length[60]'],
                    ['employee_id', 'Pegawai', 'required|integer'],
                    ['contract_type', 'Jenis Kontrak', 'required|trim|in_list[K1,K2,K3,CUSTOM]'],
                    ['status', 'Status', 'required|trim|in_list[DRAFT,GENERATED,SIGNED,ACTIVE,EXPIRED,TERMINATED,CANCELLED]'],
                    ['start_date', 'Tanggal Mulai', 'required|trim|max_length[10]'],
                    ['end_date', 'Tanggal Akhir', 'required|trim|max_length[10]'],
                ],
                'fields' => [
                    ['name' => 'contract_number', 'label' => 'Nomor Kontrak', 'type' => 'text'],
                    ['name' => 'employee_id', 'label' => 'Pegawai', 'type' => 'select', 'lookup' => ['table' => 'org_employee', 'value' => 'id', 'label' => 'employee_name', 'active_only' => false]],
                    ['name' => 'template_id', 'label' => 'Template Kontrak', 'type' => 'select', 'lookup' => ['table' => 'hr_contract_template', 'value' => 'id', 'label' => 'template_name', 'active_only' => false]],
                    ['name' => 'previous_contract_id', 'label' => 'Kontrak Sebelumnya', 'type' => 'select', 'lookup' => ['table' => 'hr_contract', 'value' => 'id', 'label' => 'contract_number', 'active_only' => false]],
                    ['name' => 'contract_type', 'label' => 'Jenis Kontrak', 'type' => 'select', 'options' => [
                        ['value' => 'K1', 'label' => 'K1'],
                        ['value' => 'K2', 'label' => 'K2'],
                        ['value' => 'K3', 'label' => 'K3'],
                        ['value' => 'CUSTOM', 'label' => 'CUSTOM'],
                    ]],
                    ['name' => 'status', 'label' => 'Status Kontrak', 'type' => 'select', 'options' => [
                        ['value' => 'DRAFT', 'label' => 'DRAFT'],
                        ['value' => 'GENERATED', 'label' => 'GENERATED'],
                        ['value' => 'SIGNED', 'label' => 'SIGNED'],
                        ['value' => 'ACTIVE', 'label' => 'ACTIVE'],
                        ['value' => 'EXPIRED', 'label' => 'EXPIRED'],
                        ['value' => 'TERMINATED', 'label' => 'TERMINATED'],
                        ['value' => 'CANCELLED', 'label' => 'CANCELLED'],
                    ]],
                    ['name' => 'position_snapshot', 'label' => 'Jabatan (snapshot)', 'type' => 'text'],
                    ['name' => 'division_snapshot', 'label' => 'Divisi (snapshot)', 'type' => 'text'],
                    ['name' => 'basic_salary', 'label' => 'Gaji Pokok', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'position_allowance', 'label' => 'Tunjangan Jabatan', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'other_allowance', 'label' => 'Tunjangan Lain', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'meal_rate', 'label' => 'Uang Makan', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'overtime_rate', 'label' => 'Rate Lembur/Jam', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'start_date', 'label' => 'Tanggal Mulai (YYYY-MM-DD)', 'type' => 'text'],
                    ['name' => 'end_date', 'label' => 'Tanggal Akhir (YYYY-MM-DD)', 'type' => 'text'],
                    ['name' => 'verification_token', 'label' => 'Verification Token', 'type' => 'text'],
                    ['name' => 'final_document_hash', 'label' => 'Hash Dokumen Final', 'type' => 'text'],
                    ['name' => 'document_issued_at', 'label' => 'Dokumen Terbit (YYYY-MM-DD HH:MM:SS)', 'type' => 'text'],
                    ['name' => 'body_html', 'label' => 'Dokumen Kontrak (HTML)', 'type' => 'textarea'],
                    ['name' => 'notes', 'label' => 'Catatan', 'type' => 'textarea'],
                ],
                'columns' => [
                    ['key' => 'contract_number', 'label' => 'No Kontrak'],
                    ['key' => 'employee_id_label', 'label' => 'Pegawai'],
                    ['key' => 'template_id_label', 'label' => 'Template'],
                    ['key' => 'contract_type', 'label' => 'Jenis'],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'start_date', 'label' => 'Mulai'],
                    ['key' => 'end_date', 'label' => 'Akhir'],
                ],
            ],
            'product-division' => [
                'table' => 'mst_product_division',
                'title' => 'Master Divisi Produk',
                'order_by' => 'sort_order',
                'order_dir' => 'ASC',
                'searchable' => ['code', 'name'],
                'toggle' => true,
                'code_column' => 'code',
                'code_input' => 'code',
                'rules' => [
                    ['code', 'Kode', 'required|trim|max_length[40]'],
                    ['name', 'Nama', 'required|trim|max_length[120]'],
                ],
                'fields' => [
                    ['name' => 'code', 'label' => 'Kode', 'type' => 'text'],
                    ['name' => 'name', 'label' => 'Nama', 'type' => 'text'],
                    ['name' => 'pos_scope', 'label' => 'POS Scope', 'type' => 'select', 'options' => [
                        ['value' => 'REGULAR', 'label' => 'REGULAR'],
                        ['value' => 'EVENT', 'label' => 'EVENT'],
                        ['value' => 'ALL', 'label' => 'ALL'],
                    ]],
                    ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'code', 'label' => 'Kode'],
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'pos_scope', 'label' => 'Scope'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'product-classification' => [
                'table' => 'mst_product_classification',
                'title' => 'Master Klasifikasi Produk',
                'order_by' => 'sort_order',
                'order_dir' => 'ASC',
                'searchable' => ['code', 'name'],
                'toggle' => true,
                'code_column' => 'code',
                'code_input' => 'code',
                'rules' => [
                    ['product_division_id', 'Divisi Produk', 'required|integer'],
                    ['code', 'Kode', 'required|trim|max_length[40]'],
                    ['name', 'Nama', 'required|trim|max_length[120]'],
                ],
                'fields' => [
                    ['name' => 'product_division_id', 'label' => 'Divisi Produk', 'type' => 'select', 'lookup' => ['table' => 'mst_product_division', 'value' => 'id', 'label' => 'name']],
                    ['name' => 'code', 'label' => 'Kode', 'type' => 'text'],
                    ['name' => 'name', 'label' => 'Nama', 'type' => 'text'],
                    ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'product_division_id_label', 'label' => 'Divisi'],
                    ['key' => 'code', 'label' => 'Kode'],
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'product-category' => [
                'table' => 'mst_product_category',
                'title' => 'Master Kategori Produk',
                'order_by' => 'sort_order',
                'order_dir' => 'ASC',
                'searchable' => ['code', 'name'],
                'toggle' => true,
                'code_column' => 'code',
                'code_input' => 'code',
                'rules' => [
                    ['product_division_id', 'Divisi Produk', 'required|integer'],
                    ['classification_id', 'Klasifikasi', 'required|integer'],
                    ['code', 'Kode', 'required|trim|max_length[40]'],
                    ['name', 'Nama', 'required|trim|max_length[120]'],
                ],
                'fields' => [
                    ['name' => 'product_division_id', 'label' => 'Divisi Produk', 'type' => 'select', 'lookup' => ['table' => 'mst_product_division', 'value' => 'id', 'label' => 'name']],
                    ['name' => 'classification_id', 'label' => 'Klasifikasi', 'type' => 'select', 'lookup' => ['table' => 'mst_product_classification', 'value' => 'id', 'label' => 'name']],
                    ['name' => 'parent_id', 'label' => 'Parent', 'type' => 'select', 'lookup' => ['table' => 'mst_product_category', 'value' => 'id', 'label' => 'name', 'active_only' => false]],
                    ['name' => 'code', 'label' => 'Kode', 'type' => 'text'],
                    ['name' => 'name', 'label' => 'Nama', 'type' => 'text'],
                    ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'product_division_id_label', 'label' => 'Divisi'],
                    ['key' => 'classification_id_label', 'label' => 'Klasifikasi'],
                    ['key' => 'code', 'label' => 'Kode'],
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'item-category' => [
                'table' => 'mst_item_category',
                'title' => 'Master Kategori Item/Bahan Baku',
                'order_by' => 'sort_order',
                'order_dir' => 'ASC',
                'searchable' => ['code', 'name'],
                'toggle' => true,
                'code_column' => 'code',
                'code_input' => 'code',
                'rules' => [
                    ['code', 'Kode', 'required|trim|max_length[40]'],
                    ['name', 'Nama', 'required|trim|max_length[120]'],
                ],
                'fields' => [
                    ['name' => 'parent_id', 'label' => 'Parent', 'type' => 'select', 'lookup' => ['table' => 'mst_item_category', 'value' => 'id', 'label' => 'name', 'active_only' => false]],
                    ['name' => 'code', 'label' => 'Kode', 'type' => 'text'],
                    ['name' => 'name', 'label' => 'Nama', 'type' => 'text'],
                    ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'code', 'label' => 'Kode'],
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'parent_id_label', 'label' => 'Parent'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'component-category' => [
                'table' => 'mst_component_category',
                'title' => 'Master Kategori Komponen',
                'order_by' => 'sort_order',
                'order_dir' => 'ASC',
                'searchable' => ['code', 'name'],
                'toggle' => true,
                'code_column' => 'code',
                'code_input' => 'code',
                'rules' => [
                    ['code', 'Kode', 'required|trim|max_length[40]'],
                    ['name', 'Nama', 'required|trim|max_length[120]'],
                ],
                'fields' => [
                    ['name' => 'parent_id', 'label' => 'Parent', 'type' => 'select', 'lookup' => ['table' => 'mst_component_category', 'value' => 'id', 'label' => 'name', 'active_only' => false]],
                    ['name' => 'code', 'label' => 'Kode', 'type' => 'text'],
                    ['name' => 'name', 'label' => 'Nama', 'type' => 'text'],
                    ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'code', 'label' => 'Kode'],
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'parent_id_label', 'label' => 'Parent'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'material' => [
                'table' => 'mst_material',
                'title' => 'Master Bahan Baku',
                'order_by' => 'material_name',
                'order_dir' => 'ASC',
                'searchable' => ['material_code', 'material_name'],
                'toggle' => true,
                'code_column' => 'material_code',
                'code_input' => 'material_code',
                'auto_code_source' => 'material_name',
                'rules' => [
                    ['material_code', 'Kode', 'trim|max_length[50]'],
                    ['material_name', 'Nama', 'required|trim|max_length[150]'],
                    ['content_uom_id', 'Satuan Isi', 'required|integer'],
                ],
                'fields' => [
                    ['name' => 'material_code', 'label' => 'Kode', 'type' => 'text'],
                    ['name' => 'material_name', 'label' => 'Nama', 'type' => 'text'],
                    ['name' => 'item_category_id', 'label' => 'Kategori', 'type' => 'select', 'lookup' => ['table' => 'mst_item_category', 'value' => 'id', 'label' => 'name']],
                    ['name' => 'content_uom_id', 'label' => 'Satuan Isi', 'type' => 'select', 'lookup' => ['table' => 'mst_uom', 'value' => 'id', 'label' => 'name']],
                    ['name' => 'hpp_standard', 'label' => 'HPP Standar', 'type' => 'number', 'step' => '0.0001'],
                    ['name' => 'shelf_life_days', 'label' => 'Shelf Life (hari)', 'type' => 'number'],
                    ['name' => 'reorder_level_content', 'label' => 'Reorder Level', 'type' => 'number', 'step' => '0.0001'],
                    ['name' => 'notes', 'label' => 'Catatan', 'type' => 'textarea'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'material_code', 'label' => 'Kode'],
                    ['key' => 'material_name', 'label' => 'Nama'],
                    ['key' => 'content_uom_id_label', 'label' => 'Satuan'],
                    ['key' => 'hpp_standard', 'label' => 'HPP Standar'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'item' => [
                'table' => 'mst_item',
                'title' => 'Master Item',
                'order_by' => 'item_name',
                'order_dir' => 'ASC',
                'searchable' => ['item_code', 'item_name'],
                'toggle' => true,
                'code_column' => 'item_code',
                'code_input' => 'item_code',
                'auto_code_source' => 'item_name',
                'rules' => [
                    ['item_code', 'Kode', 'trim|max_length[50]'],
                    ['item_name', 'Nama', 'required|trim|max_length[150]'],
                    ['item_category_id', 'Kategori', 'required|integer'],
                    ['buy_uom_id', 'Satuan Beli', 'required|integer'],
                    ['content_uom_id', 'Satuan Isi', 'required|integer'],
                    ['content_per_buy', 'Isi per Beli', 'required|numeric'],
                ],
                'fields' => [
                    ['name' => 'item_code', 'label' => 'Kode', 'type' => 'text'],
                    ['name' => 'item_name', 'label' => 'Nama', 'type' => 'text'],
                    ['name' => 'item_category_id', 'label' => 'Kategori', 'type' => 'select', 'lookup' => ['table' => 'mst_item_category', 'value' => 'id', 'label' => 'name']],
                    ['name' => 'buy_uom_id', 'label' => 'Satuan Beli', 'type' => 'select', 'lookup' => ['table' => 'mst_uom', 'value' => 'id', 'label' => 'name']],
                    ['name' => 'content_uom_id', 'label' => 'Satuan Isi', 'type' => 'select', 'lookup' => ['table' => 'mst_uom', 'value' => 'id', 'label' => 'name']],
                    ['name' => 'content_per_buy', 'label' => 'Isi per Beli', 'type' => 'number', 'step' => '0.0001'],
                    ['name' => 'min_stock_content', 'label' => 'Min Stok', 'type' => 'number', 'step' => '0.0001'],
                    ['name' => 'last_buy_price', 'label' => 'Harga Beli Terakhir', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'is_material', 'label' => 'Item ini Bahan Baku', 'type' => 'checkbox'],
                    ['name' => 'material_id', 'label' => 'Bahan Baku Existing', 'type' => 'select', 'lookup' => ['table' => 'mst_material', 'value' => 'id', 'label' => 'material_name', 'active_only' => false]],
                    ['name' => 'new_material_code', 'label' => 'Kode Bahan Baku Baru', 'type' => 'text', 'readonly' => true],
                    ['name' => 'new_material_name', 'label' => 'Nama Bahan Baku Baru', 'type' => 'text', 'readonly' => true],
                    ['name' => 'new_material_hpp', 'label' => 'HPP Bahan Baku Baru', 'type' => 'number', 'readonly' => true, 'step' => '0.0001'],
                    ['name' => 'notes', 'label' => 'Catatan', 'type' => 'textarea'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'item_code', 'label' => 'Kode'],
                    ['key' => 'item_name', 'label' => 'Nama'],
                    ['key' => 'item_category_id_label', 'label' => 'Kategori'],
                    ['key' => 'buy_uom_id_label', 'label' => 'UOM Beli'],
                    ['key' => 'content_uom_id_label', 'label' => 'UOM Isi'],
                    ['key' => 'is_material', 'label' => 'Bahan Baku', 'type' => 'bool'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'component' => [
                'table' => 'mst_component',
                'title' => 'Master Component',
                'order_by' => 'component_name',
                'order_dir' => 'ASC',
                'searchable' => ['component_code', 'component_name'],
                'toggle' => true,
                'code_column' => 'component_code',
                'code_input' => 'component_code',
                'auto_code_source' => 'component_name',
                'rules' => [
                    ['component_code', 'Kode', 'trim|max_length[50]'],
                    ['component_name', 'Nama', 'required|trim|max_length[150]'],
                    ['component_type', 'Tipe', 'required'],
                    ['product_division_id', 'Divisi Produk', 'required|integer'],
                    ['component_category_id', 'Kategori', 'required|integer'],
                    ['uom_id', 'Satuan', 'required|integer'],
                ],
                'fields' => [
                    ['name' => 'component_code', 'label' => 'Kode', 'type' => 'text'],
                    ['name' => 'component_name', 'label' => 'Nama', 'type' => 'text'],
                    ['name' => 'component_type', 'label' => 'Tipe', 'type' => 'select', 'options' => [
                        ['value' => 'BASE', 'label' => 'BASE'],
                        ['value' => 'PREPARE', 'label' => 'PREPARE'],
                    ]],
                    ['name' => 'product_division_id', 'label' => 'Divisi Produk', 'type' => 'select', 'lookup' => ['table' => 'mst_product_division', 'value' => 'id', 'label' => 'name']],
                    ['name' => 'operational_division_id', 'label' => 'Divisi Operasional', 'type' => 'select', 'lookup' => ['table' => 'mst_operational_division', 'value' => 'id', 'label' => 'name'], 'readonly' => true],
                    ['name' => 'component_category_id', 'label' => 'Kategori', 'type' => 'select', 'lookup' => ['table' => 'mst_component_category', 'value' => 'id', 'label' => 'name']],
                    ['name' => 'uom_id', 'label' => 'Satuan', 'type' => 'select', 'lookup' => ['table' => 'mst_uom', 'value' => 'id', 'label' => 'name']],
                    ['name' => 'yield_qty', 'label' => 'Yield Qty', 'type' => 'number', 'step' => '0.0001'],
                    ['name' => 'hpp_standard', 'label' => 'HPP Standar', 'type' => 'number', 'step' => '0.0001'],
                    ['name' => 'variable_cost_mode', 'label' => 'Mode Variable Cost', 'type' => 'select', 'options' => [
                        ['value' => 'DEFAULT', 'label' => 'DEFAULT'],
                        ['value' => 'CUSTOM', 'label' => 'CUSTOM'],
                        ['value' => 'NONE', 'label' => 'NONE'],
                    ]],
                    ['name' => 'variable_cost_percent', 'label' => 'Variable Cost %', 'type' => 'number', 'step' => '0.0001'],
                    ['name' => 'min_stock', 'label' => 'Min Stok', 'type' => 'number', 'step' => '0.0001'],
                    ['name' => 'description', 'label' => 'Deskripsi', 'type' => 'textarea'],
                    ['name' => 'notes', 'label' => 'Catatan', 'type' => 'textarea'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'component_code', 'label' => 'Kode'],
                    ['key' => 'component_name', 'label' => 'Nama'],
                    ['key' => 'component_type', 'label' => 'Tipe'],
                    ['key' => 'product_division_id_label', 'label' => 'Divisi Produk'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'product' => [
                'table' => 'mst_product',
                'title' => 'Master Product',
                'order_by' => 'product_name',
                'order_dir' => 'ASC',
                'searchable' => ['product_code', 'product_name'],
                'toggle' => true,
                'code_column' => 'product_code',
                'code_input' => 'product_code',
                'auto_code_source' => 'product_name',
                'rules' => [
                    ['product_code', 'Kode', 'trim|max_length[50]'],
                    ['product_name', 'Nama', 'required|trim|max_length[150]'],
                    ['product_division_id', 'Divisi Produk', 'required|integer'],
                    ['classification_id', 'Klasifikasi', 'required|integer'],
                    ['product_category_id', 'Kategori', 'required|integer'],
                    ['uom_id', 'Satuan', 'required|integer'],
                ],
                'fields' => [
                    ['name' => 'product_code', 'label' => 'Kode', 'type' => 'text'],
                    ['name' => 'product_name', 'label' => 'Nama', 'type' => 'text'],
                    ['name' => 'product_division_id', 'label' => 'Divisi Produk', 'type' => 'select', 'lookup' => ['table' => 'mst_product_division', 'value' => 'id', 'label' => 'name']],
                    ['name' => 'default_operational_division_id', 'label' => 'Divisi Operasional Default', 'type' => 'select', 'lookup' => ['table' => 'mst_operational_division', 'value' => 'id', 'label' => 'name'], 'readonly' => true],
                    ['name' => 'classification_id', 'label' => 'Klasifikasi', 'type' => 'select', 'lookup' => ['table' => 'mst_product_classification', 'value' => 'id', 'label' => 'name']],
                    ['name' => 'product_category_id', 'label' => 'Kategori', 'type' => 'select', 'lookup' => ['table' => 'mst_product_category', 'value' => 'id', 'label' => 'name']],
                    ['name' => 'uom_id', 'label' => 'Satuan', 'type' => 'select', 'lookup' => ['table' => 'mst_uom', 'value' => 'id', 'label' => 'name']],
                    ['name' => 'selling_price', 'label' => 'Harga Jual', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'description', 'label' => 'Deskripsi', 'type' => 'textarea'],
                    ['name' => 'hpp_standard', 'label' => 'HPP Standar', 'type' => 'number', 'step' => '0.0001'],
                    ['name' => 'variable_cost_mode', 'label' => 'Mode Variable Cost', 'type' => 'select', 'options' => [
                        ['value' => 'DEFAULT', 'label' => 'DEFAULT'],
                        ['value' => 'CUSTOM', 'label' => 'CUSTOM'],
                        ['value' => 'NONE', 'label' => 'NONE'],
                    ]],
                    ['name' => 'variable_cost_percent', 'label' => 'Variable Cost %', 'type' => 'number', 'step' => '0.0001'],
                    ['name' => 'stock_mode', 'label' => 'Mode Stok', 'type' => 'select', 'options' => [
                        ['value' => 'MANUAL_AVAILABLE', 'label' => 'MANUAL_AVAILABLE'],
                        ['value' => 'MANUAL_OUT', 'label' => 'MANUAL_OUT'],
                        ['value' => 'AUTO', 'label' => 'AUTO'],
                    ]],
                    ['name' => 'show_pos', 'label' => 'Tampil di POS', 'type' => 'checkbox'],
                    ['name' => 'show_member', 'label' => 'Tampil di Member', 'type' => 'checkbox'],
                    ['name' => 'show_landing', 'label' => 'Tampil di Landing', 'type' => 'checkbox'],
                    ['name' => 'photo_file', 'label' => 'Foto Produk', 'type' => 'file'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'product_code', 'label' => 'Kode'],
                    ['key' => 'photo_path', 'label' => 'Foto', 'type' => 'image'],
                    ['key' => 'product_name', 'label' => 'Nama'],
                    ['key' => 'product_division_id_label', 'label' => 'Divisi Produk'],
                    ['key' => 'selling_price', 'label' => 'Harga'],
                    ['key' => 'stock_mode', 'label' => 'Mode Stok'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'vendor' => [
                'table' => 'mst_vendor',
                'title' => 'Master Vendor',
                'order_by' => 'vendor_name',
                'order_dir' => 'ASC',
                'searchable' => ['vendor_code', 'vendor_name', 'phone', 'email'],
                'toggle' => true,
                'code_column' => 'vendor_code',
                'code_input' => 'vendor_code',
                'rules' => [
                    ['vendor_code', 'Kode', 'required|trim|max_length[50]'],
                    ['vendor_name', 'Nama', 'required|trim|max_length[150]'],
                ],
                'fields' => [
                    ['name' => 'vendor_code', 'label' => 'Kode', 'type' => 'text'],
                    ['name' => 'vendor_name', 'label' => 'Nama', 'type' => 'text'],
                    ['name' => 'contact_name', 'label' => 'Kontak', 'type' => 'text'],
                    ['name' => 'phone', 'label' => 'Telepon', 'type' => 'text'],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'text'],
                    ['name' => 'tax_no', 'label' => 'NPWP', 'type' => 'text'],
                    ['name' => 'city', 'label' => 'Kota', 'type' => 'text'],
                    ['name' => 'payment_terms', 'label' => 'Term Pembayaran (hari)', 'type' => 'number'],
                    ['name' => 'address', 'label' => 'Alamat', 'type' => 'textarea'],
                    ['name' => 'notes', 'label' => 'Catatan', 'type' => 'textarea'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'vendor_code', 'label' => 'Kode'],
                    ['key' => 'vendor_name', 'label' => 'Nama'],
                    ['key' => 'phone', 'label' => 'Telepon'],
                    ['key' => 'city', 'label' => 'Kota'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'posting-type' => [
                'table' => 'mst_posting_type',
                'title' => 'Master Posting Type',
                'order_by' => 'sort_order',
                'order_dir' => 'ASC',
                'searchable' => ['type_code', 'type_name', 'notes'],
                'toggle' => true,
                'code_column' => 'type_code',
                'code_input' => 'type_code',
                'rules' => [
                    ['type_code', 'Kode', 'required|trim|max_length[40]'],
                    ['type_name', 'Nama', 'required|trim|max_length[120]'],
                ],
                'fields' => [
                    ['name' => 'type_code', 'label' => 'Kode', 'type' => 'text'],
                    ['name' => 'type_name', 'label' => 'Nama', 'type' => 'text'],
                    ['name' => 'affects_inventory', 'label' => 'Affects Inventory', 'type' => 'checkbox'],
                    ['name' => 'affects_service', 'label' => 'Affects Service', 'type' => 'checkbox'],
                    ['name' => 'affects_asset', 'label' => 'Affects Asset', 'type' => 'checkbox'],
                    ['name' => 'affects_payroll', 'label' => 'Affects Payroll', 'type' => 'checkbox'],
                    ['name' => 'affects_expense', 'label' => 'Affects Expense', 'type' => 'checkbox'],
                    ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number'],
                    ['name' => 'notes', 'label' => 'Catatan', 'type' => 'textarea'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'type_code', 'label' => 'Kode'],
                    ['key' => 'type_name', 'label' => 'Nama'],
                    ['key' => 'affects_inventory', 'label' => 'Inv', 'type' => 'bool'],
                    ['key' => 'affects_service', 'label' => 'Svc', 'type' => 'bool'],
                    ['key' => 'affects_asset', 'label' => 'Ast', 'type' => 'bool'],
                    ['key' => 'affects_payroll', 'label' => 'Pay', 'type' => 'bool'],
                    ['key' => 'affects_expense', 'label' => 'Exp', 'type' => 'bool'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'purchase-type' => [
                'table' => 'mst_purchase_type',
                'title' => 'Master Purchase Type',
                'order_by' => 'sort_order',
                'order_dir' => 'ASC',
                'searchable' => ['type_code', 'type_name', 'notes'],
                'toggle' => true,
                'code_column' => 'type_code',
                'code_input' => 'type_code',
                'rules' => [
                    ['type_code', 'Kode', 'required|trim|max_length[40]'],
                    ['type_name', 'Nama', 'required|trim|max_length[120]'],
                    ['posting_type_id', 'Posting Type', 'required|integer'],
                    ['destination_behavior', 'Destination Behavior', 'required|in_list[REQUIRED,NONE]'],
                ],
                'fields' => [
                    ['name' => 'type_code', 'label' => 'Kode', 'type' => 'text'],
                    ['name' => 'type_name', 'label' => 'Nama', 'type' => 'text'],
                    ['name' => 'posting_type_id', 'label' => 'Posting Type', 'type' => 'select', 'lookup' => ['table' => 'mst_posting_type', 'value' => 'id', 'label' => 'type_name', 'active_only' => false]],
                    ['name' => 'destination_behavior', 'label' => 'Destination Behavior', 'type' => 'select', 'options' => [
                        ['value' => 'REQUIRED', 'label' => 'REQUIRED'],
                        ['value' => 'NONE', 'label' => 'NONE'],
                    ]],
                    ['name' => 'default_destination', 'label' => 'Default Destination', 'type' => 'select', 'options' => [
                        ['value' => 'GUDANG', 'label' => 'GUDANG'],
                        ['value' => 'BAR', 'label' => 'BAR'],
                        ['value' => 'KITCHEN', 'label' => 'KITCHEN'],
                        ['value' => 'BAR_EVENT', 'label' => 'BAR_EVENT'],
                        ['value' => 'KITCHEN_EVENT', 'label' => 'KITCHEN_EVENT'],
                        ['value' => 'OFFICE', 'label' => 'OFFICE'],
                        ['value' => 'OTHER', 'label' => 'OTHER'],
                    ]],
                    ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number'],
                    ['name' => 'notes', 'label' => 'Catatan', 'type' => 'textarea'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'type_code', 'label' => 'Kode'],
                    ['key' => 'type_name', 'label' => 'Nama'],
                    ['key' => 'posting_type_id_label', 'label' => 'Posting Type'],
                    ['key' => 'destination_behavior', 'label' => 'Dest Behavior'],
                    ['key' => 'default_destination', 'label' => 'Default Dest'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'purchase-catalog' => [
                'table' => 'mst_purchase_catalog',
                'title' => 'Master Purchase Catalog',
                'order_by' => 'catalog_name',
                'order_dir' => 'ASC',
                'searchable' => ['catalog_name', 'brand_name', 'line_description', 'profile_key'],
                'toggle' => true,
                'rules' => [
                    ['line_kind', 'Line Kind', 'required|trim'],
                    ['vendor_id', 'Vendor', 'required|integer'],
                    ['catalog_name', 'Nama Katalog', 'required|trim|max_length[150]'],
                    ['buy_uom_id', 'UOM Beli', 'required|integer'],
                    ['content_per_buy', 'Isi per Beli', 'required|numeric'],
                    ['conversion_factor_to_content', 'Factor to Content', 'required|numeric'],
                ],
                'fields' => [
                    ['name' => 'line_kind', 'label' => 'Line Kind', 'type' => 'select', 'options' => [
                        ['value' => 'ITEM', 'label' => 'ITEM'],
                        ['value' => 'MATERIAL', 'label' => 'MATERIAL'],
                        ['value' => 'SERVICE', 'label' => 'SERVICE'],
                        ['value' => 'ASSET', 'label' => 'ASSET'],
                    ]],
                    ['name' => 'vendor_id', 'label' => 'Vendor', 'type' => 'select', 'lookup' => ['table' => 'mst_vendor', 'value' => 'id', 'label' => 'vendor_name', 'active_only' => false]],
                    ['name' => 'item_id', 'label' => 'Item', 'type' => 'select', 'lookup' => ['table' => 'mst_item', 'value' => 'id', 'label' => 'item_name', 'active_only' => false]],
                    ['name' => 'material_id', 'label' => 'Material', 'type' => 'select', 'lookup' => ['table' => 'mst_material', 'value' => 'id', 'label' => 'material_name', 'active_only' => false]],
                    ['name' => 'catalog_name', 'label' => 'Nama Katalog', 'type' => 'text'],
                    ['name' => 'brand_name', 'label' => 'Merk', 'type' => 'text'],
                    ['name' => 'line_description', 'label' => 'Keterangan', 'type' => 'textarea'],
                    ['name' => 'buy_uom_id', 'label' => 'UOM Beli', 'type' => 'select', 'lookup' => ['table' => 'mst_uom', 'value' => 'id', 'label' => 'name', 'active_only' => false]],
                    ['name' => 'content_uom_id', 'label' => 'UOM Isi', 'type' => 'select', 'lookup' => ['table' => 'mst_uom', 'value' => 'id', 'label' => 'name', 'active_only' => false]],
                    ['name' => 'content_per_buy', 'label' => 'Isi per Beli', 'type' => 'number', 'step' => '0.0001'],
                    ['name' => 'conversion_factor_to_content', 'label' => 'Factor ke Isi', 'type' => 'number', 'step' => '0.00000001'],
                    ['name' => 'standard_price', 'label' => 'Harga Standar', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'last_unit_price', 'label' => 'Harga Beli Terakhir', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'last_purchase_date', 'label' => 'Tanggal Beli Terakhir', 'type' => 'text'],
                    ['name' => 'notes', 'label' => 'Catatan', 'type' => 'textarea'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'catalog_name', 'label' => 'Nama Katalog'],
                    ['key' => 'line_kind', 'label' => 'Line Kind'],
                    ['key' => 'vendor_id_label', 'label' => 'Vendor'],
                    ['key' => 'item_id_label', 'label' => 'Item'],
                    ['key' => 'material_id_label', 'label' => 'Material'],
                    ['key' => 'brand_name', 'label' => 'Merk'],
                    ['key' => 'line_description', 'label' => 'Keterangan'],
                    ['key' => 'expired_date', 'label' => 'Exp Date'],
                    ['key' => 'buy_uom_id_label', 'label' => 'UOM Beli'],
                    ['key' => 'content_uom_id_label', 'label' => 'UOM Isi'],
                    ['key' => 'content_per_buy', 'label' => 'Isi/Beli'],
                    ['key' => 'conversion_factor_to_content', 'label' => 'Factor to Content'],
                    ['key' => 'standard_price', 'label' => 'Harga Standar'],
                    ['key' => 'last_unit_price', 'label' => 'Harga Beli Terakhir'],
                    ['key' => 'last_purchase_date', 'label' => 'Tgl Beli Terakhir'],
                    ['key' => 'last_purchase_order_id', 'label' => 'Last PO ID'],
                    ['key' => 'last_purchase_line_id', 'label' => 'Last PO Line ID'],
                    ['key' => 'profile_key', 'label' => 'Profile Key'],
                    ['key' => 'notes', 'label' => 'Catatan'],
                    ['key' => 'created_at', 'label' => 'Created At'],
                    ['key' => 'updated_at', 'label' => 'Updated At'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'company-account' => [
                'table' => 'fin_company_account',
                'title' => 'Master Company Account',
                'order_by' => 'account_name',
                'order_dir' => 'ASC',
                'searchable' => ['account_code', 'account_name', 'bank_name', 'account_no'],
                'toggle' => true,
                'code_column' => 'account_code',
                'code_input' => 'account_code',
                'rules' => [
                    ['account_code', 'Kode Akun', 'required|trim|max_length[40]'],
                    ['account_name', 'Nama Akun', 'required|trim|max_length[150]'],
                    ['account_type', 'Tipe Akun', 'required|trim|in_list[BANK,EWALLET,CASH,OTHER]'],
                ],
                'fields' => [
                    ['name' => 'account_code', 'label' => 'Kode Akun', 'type' => 'text'],
                    ['name' => 'account_name', 'label' => 'Nama Akun', 'type' => 'text'],
                    ['name' => 'account_type', 'label' => 'Tipe Akun', 'type' => 'select', 'options' => [
                        ['value' => 'BANK', 'label' => 'BANK'],
                        ['value' => 'EWALLET', 'label' => 'EWALLET'],
                        ['value' => 'CASH', 'label' => 'CASH'],
                        ['value' => 'OTHER', 'label' => 'OTHER'],
                    ]],
                    ['name' => 'bank_name', 'label' => 'Nama Bank', 'type' => 'text'],
                    ['name' => 'account_no', 'label' => 'No Rekening', 'type' => 'text'],
                    ['name' => 'account_holder', 'label' => 'Atas Nama', 'type' => 'text'],
                    ['name' => 'currency_code', 'label' => 'Currency', 'type' => 'text'],
                    ['name' => 'opening_balance', 'label' => 'Opening Balance', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'current_balance', 'label' => 'Current Balance', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'is_default', 'label' => 'Default', 'type' => 'checkbox'],
                    ['name' => 'notes', 'label' => 'Catatan', 'type' => 'textarea'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'account_code', 'label' => 'Kode'],
                    ['key' => 'account_name', 'label' => 'Nama Akun'],
                    ['key' => 'account_type', 'label' => 'Tipe'],
                    ['key' => 'bank_name', 'label' => 'Bank'],
                    ['key' => 'current_balance', 'label' => 'Saldo'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'payment-channel' => [
                'table' => 'pur_payment_channel',
                'title' => 'Master Payment Channel Purchase',
                'order_by' => 'sort_order',
                'order_dir' => 'ASC',
                'searchable' => ['channel_code', 'channel_name', 'notes'],
                'toggle' => true,
                'code_column' => 'channel_code',
                'code_input' => 'channel_code',
                'rules' => [
                    ['channel_code', 'Kode Channel', 'required|trim|max_length[50]'],
                    ['channel_name', 'Nama Channel', 'required|trim|max_length[150]'],
                ],
                'fields' => [
                    ['name' => 'channel_code', 'label' => 'Kode Channel', 'type' => 'text'],
                    ['name' => 'channel_name', 'label' => 'Nama Channel', 'type' => 'text'],
                    ['name' => 'company_account_id', 'label' => 'Company Account', 'type' => 'select', 'lookup' => ['table' => 'fin_company_account', 'value' => 'id', 'label' => 'account_name', 'active_only' => false]],
                    ['name' => 'requires_due_date', 'label' => 'Butuh Due Date', 'type' => 'checkbox'],
                    ['name' => 'requires_reference_no', 'label' => 'Butuh Reference No', 'type' => 'checkbox'],
                    ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number'],
                    ['name' => 'notes', 'label' => 'Catatan', 'type' => 'textarea'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'channel_code', 'label' => 'Kode'],
                    ['key' => 'channel_name', 'label' => 'Nama Channel'],
                    ['key' => 'company_account_id_label', 'label' => 'Company Account'],
                    ['key' => 'requires_due_date', 'label' => 'Due Date', 'type' => 'bool'],
                    ['key' => 'requires_reference_no', 'label' => 'Reference No', 'type' => 'bool'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'extra' => [
                'table' => 'mst_extra',
                'title' => 'Master Extra',
                'order_by' => 'extra_name',
                'order_dir' => 'ASC',
                'searchable' => ['extra_code', 'extra_name'],
                'toggle' => true,
                'code_column' => 'extra_code',
                'code_input' => 'extra_code',
                'auto_code_source' => 'extra_name',
                'rules' => [
                    ['extra_code', 'Kode', 'trim|max_length[40]'],
                    ['extra_name', 'Nama', 'required|trim|max_length[120]'],
                ],
                'fields' => [
                    ['name' => 'extra_code', 'label' => 'Kode', 'type' => 'text'],
                    ['name' => 'extra_name', 'label' => 'Nama', 'type' => 'text'],
                    ['name' => 'uom_name', 'label' => 'Nama UOM', 'type' => 'text'],
                    ['name' => 'extra_type', 'label' => 'Tipe', 'type' => 'select', 'options' => [
                        ['value' => 'ADD', 'label' => 'ADD'],
                        ['value' => 'REMOVE', 'label' => 'REMOVE'],
                        ['value' => 'CHOICE', 'label' => 'CHOICE'],
                        ['value' => 'INFO', 'label' => 'INFO'],
                    ]],
                    ['name' => 'selling_price', 'label' => 'Harga Jual', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'cost_amount', 'label' => 'Biaya', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'show_in_cashier', 'label' => 'Tampil Kasir', 'type' => 'checkbox'],
                    ['name' => 'show_in_self_order', 'label' => 'Tampil Self Order', 'type' => 'checkbox'],
                    ['name' => 'show_in_landing', 'label' => 'Tampil Landing', 'type' => 'checkbox'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'extra_code', 'label' => 'Kode'],
                    ['key' => 'extra_name', 'label' => 'Nama'],
                    ['key' => 'extra_type', 'label' => 'Tipe'],
                    ['key' => 'selling_price', 'label' => 'Harga'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'extra-group' => [
                'table' => 'mst_extra_group',
                'title' => 'Master Group Extra',
                'order_by' => 'sort_order',
                'order_dir' => 'ASC',
                'searchable' => ['group_code', 'group_name'],
                'toggle' => true,
                'code_column' => 'group_code',
                'code_input' => 'group_code',
                'rules' => [
                    ['group_code', 'Kode', 'required|trim|max_length[40]'],
                    ['group_name', 'Nama', 'required|trim|max_length[120]'],
                ],
                'fields' => [
                    ['name' => 'group_code', 'label' => 'Kode', 'type' => 'text'],
                    ['name' => 'group_name', 'label' => 'Nama', 'type' => 'text'],
                    ['name' => 'product_division_id', 'label' => 'Divisi Produk', 'type' => 'select', 'lookup' => ['table' => 'mst_product_division', 'value' => 'id', 'label' => 'name', 'active_only' => false]],
                    ['name' => 'is_required', 'label' => 'Wajib', 'type' => 'checkbox'],
                    ['name' => 'min_select', 'label' => 'Min Select', 'type' => 'number'],
                    ['name' => 'max_select', 'label' => 'Max Select', 'type' => 'number'],
                    ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'group_code', 'label' => 'Kode'],
                    ['key' => 'group_name', 'label' => 'Nama'],
                    ['key' => 'product_division_id_label', 'label' => 'Divisi Produk'],
                    ['key' => 'is_required', 'label' => 'Wajib', 'type' => 'bool'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
            'variable-cost-default' => [
                'table' => 'mst_variable_cost_default',
                'title' => 'Pengaturan Variable Cost Default',
                'order_by' => 'scope_code',
                'order_dir' => 'ASC',
                'searchable' => ['scope_code', 'notes'],
                'toggle' => true,
                'code_column' => 'scope_code',
                'code_input' => 'scope_code',
                'rules' => [
                    ['scope_code', 'Scope', 'required|trim|in_list[PRODUCT,COMPONENT]'],
                    ['default_percent', 'Default Percent', 'required|numeric'],
                ],
                'fields' => [
                    ['name' => 'scope_code', 'label' => 'Scope', 'type' => 'select', 'options' => [
                        ['value' => 'PRODUCT', 'label' => 'PRODUCT'],
                        ['value' => 'COMPONENT', 'label' => 'COMPONENT'],
                    ]],
                    ['name' => 'default_percent', 'label' => 'Default Percent', 'type' => 'number', 'step' => '0.0001'],
                    ['name' => 'notes', 'label' => 'Catatan', 'type' => 'textarea'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
                'columns' => [
                    ['key' => 'scope_code', 'label' => 'Scope'],
                    ['key' => 'default_percent', 'label' => 'Default %'],
                    ['key' => 'notes', 'label' => 'Catatan'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
            ],
        ];
    }

    private function defaultOperationalDivisionId(): ?int
    {
        $row = $this->db
            ->select('id')
            ->from('mst_operational_division')
            ->where('is_active', 1)
            ->order_by('id', 'ASC')
            ->limit(1)
            ->get()
            ->row_array();

        if (!$row) {
            return null;
        }
        return (int)$row['id'];
    }
}
