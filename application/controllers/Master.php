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

    public function index(string $entity = 'uom')
    {
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

        $data = [
            'title' => $cfg['title'],
            'active_menu' => 'grp.master',
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
        $cfg = $this->entityConfig($entity);
        if (!$cfg) show_404();

        $vcDefaults = $this->variableCostDefaultsForEntity($entity);

        $data = [
            'title' => 'Tambah ' . $cfg['title'],
            'active_menu' => 'grp.master',
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
        $cfg = $this->entityConfig($entity);
        if (!$cfg) show_404();

        $this->autofillCodeFromName($cfg, 0);
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
        $cfg = $this->entityConfig($entity);
        if (!$cfg) show_404();

        $row = $this->Master_model->get_by_id($cfg['table'], $id);
        if (!$row) show_404();

        $vcDefaults = $this->variableCostDefaultsForEntity($entity);

        $data = [
            'title' => 'Edit ' . $cfg['title'],
            'active_menu' => 'grp.master',
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

    public function update(string $entity, int $id)
    {
        $cfg = $this->entityConfig($entity);
        if (!$cfg) show_404();

        $row = $this->Master_model->get_by_id($cfg['table'], $id);
        if (!$row) show_404();

        $this->autofillCodeFromName($cfg, $id);
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
        $cfg = $this->entityConfig($entity);
        if (!$cfg) show_404();
        if (empty($cfg['toggle'])) show_404();

        $row = $this->Master_model->get_by_id($cfg['table'], $id);
        if (!$row) show_404();

        $this->Master_model->toggle_active($cfg['table'], $id);
        $this->session->set_flashdata('success', 'Status berhasil diubah.');
        redirect('master/' . $entity);
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
                    ['key' => 'brand_name', 'label' => 'Merk'],
                    ['key' => 'buy_uom_id_label', 'label' => 'UOM Beli'],
                    ['key' => 'content_per_buy', 'label' => 'Isi/Beli'],
                    ['key' => 'profile_key', 'label' => 'Profile Key'],
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
