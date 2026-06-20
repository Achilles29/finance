<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Master_relation extends MY_Controller
{
    private $productRecipeMaterialCostCache = [];
    private $productRecipeComponentCostCache = [];
    private $divisionCodeCache = [];

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Master_model');
        $this->load->library('form_validation');
        $this->load->library('PosBundlePricingService');
    }

    private function operationalDivisionCode(int $divisionId): string
    {
        if ($divisionId <= 0) {
            return '';
        }
        if (array_key_exists($divisionId, $this->divisionCodeCache)) {
            return $this->divisionCodeCache[$divisionId];
        }
        $row = $this->db->select('code')
            ->from('mst_operational_division')
            ->where('id', $divisionId)
            ->limit(1)
            ->get()
            ->row_array();
        $this->divisionCodeCache[$divisionId] = strtoupper(trim((string)($row['code'] ?? '')));
        return $this->divisionCodeCache[$divisionId];
    }

    private function regularMaterialDestinationForDivision(int $divisionId): ?string
    {
        $code = $this->operationalDivisionCode($divisionId);
        if ($code === 'BAR') {
            return 'BAR';
        }
        if ($code === 'KITCHEN') {
            return 'KITCHEN';
        }
        return null;
    }

    private function regularComponentLocationForDivision(int $divisionId): ?string
    {
        return $this->regularMaterialDestinationForDivision($divisionId);
    }

    public function product_recipe_hub()
    {
        $q = trim((string)$this->input->get('q', true));

        $this->db->select('p.id, p.product_code, p.product_name, pd.name AS product_division_name, COUNT(r.id) AS total_line');
        $this->db->from('mst_product p');
        $this->db->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left');
        $this->db->join('mst_product_recipe r', 'r.product_id = p.id', 'left');
        $this->db->where('p.is_active', 1);
        if ($q !== '') {
            $this->db->group_start();
            $this->db->like('p.product_code', $q);
            $this->db->or_like('p.product_name', $q);
            $this->db->group_end();
        }
        $this->db->group_by('p.id, p.product_code, p.product_name, pd.name');
        $this->db->order_by('p.product_name', 'ASC');
        $rows = $this->db->get()->result_array();

        $this->render('master/relation_hub', [
            'title' => 'Halaman Resep Produk',
            'active_menu' => 'grp.master',
            'relation_type' => 'product-recipe',
            'rows' => $rows,
            'q' => $q,
        ]);
    }

    public function product_availability()
    {
        $filters = $this->productAvailabilityFilters();
        $this->load->model('Pos_model');
        $this->load->library('PosAvailabilityRebuildService');
        $outletOptions = $this->Pos_model->local_outlet_options();
        if ((int)($filters['outlet_id'] ?? 0) <= 0 && !empty($outletOptions[0]['id'])) {
            $filters['outlet_id'] = (int)$outletOptions[0]['id'];
        }

        $this->db->select('p.*, pd.name AS product_division_name, pd.code AS product_division_code, pc.name AS classification_name, cat.name AS product_category_name');
        $this->db->from('mst_product p');
        $this->db->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left');
        $this->db->join('mst_product_classification pc', 'pc.id = p.classification_id', 'left');
        $this->db->join('mst_product_category cat', 'cat.id = p.product_category_id', 'left');
        $this->db->where('p.is_active', 1);
        if ($filters['q'] !== '') {
            $this->db->group_start();
            $this->db->like('p.product_code', $filters['q']);
            $this->db->or_like('p.product_name', $filters['q']);
            $this->db->group_end();
        }
        if ($filters['product_division_id'] > 0) {
            $this->db->where('p.product_division_id', $filters['product_division_id']);
        }
        if ($filters['stock_mode'] !== 'ALL') {
            $this->db->where('p.stock_mode', $filters['stock_mode']);
        }
        if ($this->db->field_exists('sort_order', 'mst_product_division')) {
            $this->db->order_by('COALESCE(pd.sort_order, 999999)', 'ASC', false);
        }
        $this->db->order_by('pd.id', 'ASC');
        if ($this->db->field_exists('sort_order', 'mst_product_classification')) {
            $this->db->order_by('COALESCE(pc.sort_order, 999999)', 'ASC', false);
        }
        $this->db->order_by('pc.id', 'ASC');
        if ($this->db->field_exists('sort_order', 'mst_product_category')) {
            $this->db->order_by('COALESCE(cat.sort_order, 999999)', 'ASC', false);
        }
        $this->db->order_by('cat.name', 'ASC');
        if ($this->db->field_exists('sort_order', 'mst_product')) {
            $this->db->order_by('COALESCE(p.sort_order, 999999)', 'ASC', false);
        }
        $this->db->order_by('p.product_name', 'ASC');
        $products = $this->db->get()->result_array();

        $rows = [];
        $summary = [
            'total_product' => 0,
            'ready_count' => 0,
            'warning_count' => 0,
            'limited_count' => 0,
            'no_recipe_count' => 0,
            'hpp_alert_count' => 0,
        ];

        foreach ($products as $product) {
            $availabilityRow = $this->buildProductAvailabilityLiveRow($product, (int)($filters['outlet_id'] ?? 0));
            $summary['total_product']++;

            if ($availabilityRow['availability_status'] === 'READY') {
                $summary['ready_count']++;
            } elseif ($availabilityRow['availability_status'] === 'WARNING') {
                $summary['warning_count']++;
            } elseif ($availabilityRow['availability_status'] === 'NO_RECIPE') {
                $summary['no_recipe_count']++;
            } else {
                $summary['limited_count']++;
            }

            if (in_array((string)($availabilityRow['hpp_status'] ?? ''), ['HIGH', 'LOSS'], true)) {
                $summary['hpp_alert_count']++;
            }

            if ($this->matchesProductAvailabilityStatus($availabilityRow, $filters['status'])) {
                $rows[] = $availabilityRow;
            }
        }

        $this->render('master/product_availability', [
            'title' => 'Monitoring Stok Produk',
            'active_menu' => 'product.monitoring.availability',
            'rows' => $rows,
            'filters' => $filters,
            'summary' => $summary,
            'outlet_options' => $outletOptions,
            'product_division_options' => $this->Master_model->get_options('mst_product_division', 'id', 'name', true),
            'status_options' => [
                ['value' => 'ALL', 'label' => 'Semua Status'],
                ['value' => 'READY', 'label' => 'Ready'],
                ['value' => 'WARNING', 'label' => 'Warning Non-Utama'],
                ['value' => 'SHORT', 'label' => 'Terbatas'],
                ['value' => 'EMPTY', 'label' => 'Habis'],
                ['value' => 'NO_RECIPE', 'label' => 'Tanpa Resep'],
            ],
            'stock_mode_options' => [
                ['value' => 'ALL', 'label' => 'Semua Mode Stok'],
                ['value' => 'AUTO', 'label' => 'AUTO'],
                ['value' => 'MANUAL_AVAILABLE', 'label' => 'MANUAL_AVAILABLE'],
                ['value' => 'MANUAL_OUT', 'label' => 'MANUAL_OUT'],
            ],
        ]);
    }

    public function component_formula_hub()
    {
        $q = trim((string)$this->input->get('q', true));

        $this->db->select('c.id, c.component_code, c.component_name, pd.name AS product_division_name, COUNT(f.id) AS total_line');
        $this->db->from('mst_component c');
        $this->db->join('mst_product_division pd', 'pd.id = c.product_division_id', 'left');
        $this->db->join('mst_component_formula f', 'f.component_id = c.id', 'left');
        $this->db->where('c.is_active', 1);
        if ($q !== '') {
            $this->db->group_start();
            $this->db->like('c.component_code', $q);
            $this->db->or_like('c.component_name', $q);
            $this->db->group_end();
        }
        $this->db->group_by('c.id, c.component_code, c.component_name, pd.name');
        $this->db->order_by('c.component_name', 'ASC');
        $rows = $this->db->get()->result_array();

        $this->render('master/relation_hub', [
            'title' => 'Halaman Formula Component',
            'active_menu' => 'grp.master',
            'relation_type' => 'component-formula',
            'rows' => $rows,
            'q' => $q,
        ]);
    }

    public function product_recipe(int $productId)
    {
        $product = $this->loadProductRecipeParent($productId);
        if (!$product) show_404();

        $recipeData = $this->loadProductRecipeData($product);

        $this->render('master/relation_list', [
            'title' => 'Relasi Recipe Product',
            'active_menu' => 'grp.master',
            'relation_type' => 'product-recipe',
            'parent' => $product,
            'rows' => $recipeData['rows'],
            'summary' => $recipeData['summary'],
            'default_source_division' => $this->resolveProductRecipeDefaultDivision($product),
            'product_variable_cost' => $this->productRecipeVariableCostContext($product),
        ]);
    }

    public function product_recipe_bulk_edit(int $productId)
    {
        $product = $this->loadProductRecipeParent($productId);
        if (!$product) show_404();

        $recipeData = $this->loadProductRecipeData($product);

        $this->render('master/relation_product_recipe_edit', [
            'title' => 'Edit Resep Product',
            'active_menu' => 'grp.master',
            'parent' => $product,
            'rows' => $recipeData['rows'],
            'summary' => $recipeData['summary'],
            'options' => $this->productRecipeOptions($product),
            'product_variable_cost' => $this->productRecipeVariableCostContext($product),
        ]);
    }

    public function product_recipe_bulk_save(int $productId)
    {
        $product = $this->loadProductRecipeParent($productId);
        if (!$product) show_404();

        $raw = (string)$this->input->post('lines_json', false);
        $lines = json_decode($raw, true);
        if (!is_array($lines)) {
            $this->session->set_flashdata('error', 'Payload resep produk tidak valid.');
            redirect('master/relation/product-recipe/edit-all/' . $productId);
            return;
        }

        $normalized = $this->normalizeProductRecipeBulkLines($product, $lines);
        if (empty($normalized['ok'])) {
            $this->session->set_flashdata('error', (string)($normalized['message'] ?? 'Gagal menyimpan resep produk.'));
            redirect('master/relation/product-recipe/edit-all/' . $productId);
            return;
        }

        $this->db->trans_start();
        $this->db->where('product_id', $productId)->delete('mst_product_recipe');
        foreach ($normalized['rows'] as $row) {
            $this->db->insert('mst_product_recipe', $row);
        }
        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            $this->session->set_flashdata('error', 'Gagal menyimpan resep produk.');
            redirect('master/relation/product-recipe/edit-all/' . $productId);
            return;
        }

        $this->session->set_flashdata('success', 'Resep produk berhasil diperbarui.');
        redirect('master/relation/product-recipe/' . $productId);
    }

    public function product_recipe_source_lookup(int $productId)
    {
        $product = $this->loadProductRecipeParent($productId);
        if (!$product) {
            show_404();
            return;
        }

        $lineType = strtoupper(trim((string)$this->input->get('line_type', true)));
        $q = trim((string)$this->input->get('q', true));
        $id = (int)$this->input->get('id', true);
        $sourceDivisionId = (int)$this->input->get('source_division_id', true);

        $rows = $lineType === 'COMPONENT'
            ? $this->searchProductRecipeComponentSourceRows($q, $id, $sourceDivisionId)
            : $this->searchProductRecipeMaterialSourceRows($product, $q, $id);

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => true,
                'rows' => $rows,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public function product_recipe_create(int $productId)
    {
        $product = $this->loadProductRecipeParent($productId);
        if (!$product) show_404();

        $this->render('master/relation_form', [
            'title' => 'Tambah Line Recipe Product',
            'active_menu' => 'grp.master',
            'relation_type' => 'product-recipe',
            'parent' => $product,
            'row' => null,
            'form_action' => 'master/relation/product-recipe/' . $productId . '/store',
            'options' => $this->productRecipeOptions($product),
            'product_variable_cost' => $this->productRecipeVariableCostContext($product),
        ]);
    }

    public function product_recipe_store(int $productId)
    {
        $product = $this->loadProductRecipeParent($productId);
        if (!$product) show_404();

        $this->form_validation->set_rules('line_type', 'Line Type', 'required');
        $this->form_validation->set_rules('qty', 'Qty', 'required|numeric');

        if ($this->form_validation->run() === false) {
            $this->session->set_flashdata('error', validation_errors('<li>', '</li>'));
            redirect('master/relation/product-recipe/' . $productId . '/create');
            return;
        }

        $lineType = (string)$this->input->post('line_type', true);
        $materialItemId = $lineType === 'MATERIAL' ? (int)$this->input->post('material_item_id', true) : null;
        $componentId = $lineType === 'COMPONENT' ? (int)$this->input->post('component_id', true) : null;

        if ($lineType === 'MATERIAL' && empty($materialItemId)) {
            $this->session->set_flashdata('error', 'Item bahan baku wajib dipilih.');
            redirect('master/relation/product-recipe/' . $productId . '/create');
            return;
        }
        if ($lineType === 'COMPONENT' && empty($componentId)) {
            $this->session->set_flashdata('error', 'Component wajib dipilih.');
            redirect('master/relation/product-recipe/' . $productId . '/create');
            return;
        }

        $resolved = $this->resolveProductRecipeInput(
            $product,
            $lineType,
            $materialItemId ?: 0,
            $componentId ?: 0,
            (int)$this->input->post('source_division_id', true) ?: null
        );
        if (empty($resolved['ok'])) {
            $this->session->set_flashdata('error', (string)($resolved['message'] ?? 'Data resep produk tidak valid.'));
            redirect('master/relation/product-recipe/' . $productId . '/create');
            return;
        }

        $ingredientRole = $this->normalizeProductRecipeIngredientRole($this->input->post('ingredient_role', true));
        if ($lineType === 'MATERIAL') {
            $duplicateCheck = $this->validateProductRecipeMaterialDuplicate($productId, $resolved, $ingredientRole);
            if (empty($duplicateCheck['ok'])) {
                $this->session->set_flashdata('error', (string)($duplicateCheck['message'] ?? 'Material resep produk dobel.'));
                redirect('master/relation/product-recipe/' . $productId . '/create');
                return;
            }
        }

        $payload = [
            'product_id' => $productId,
            'line_no' => (int)$this->input->post('line_no', true) ?: 1,
            'line_type' => $lineType,
            'material_item_id' => $resolved['material_item_id'],
            'component_id' => $resolved['component_id'],
            'source_division_id' => $resolved['source_division_id'],
            'qty' => (float)$this->input->post('qty', true),
            'uom_id' => $resolved['uom_id'],
            'notes' => $this->input->post('notes', true),
            'sort_order' => (int)$this->input->post('sort_order', true) ?: 0,
        ];
        if ($this->db->field_exists('ingredient_role', 'mst_product_recipe')) {
            $payload['ingredient_role'] = $ingredientRole;
        }

        $this->Master_model->insert('mst_product_recipe', $payload);

        $this->session->set_flashdata('success', 'Line recipe product berhasil ditambahkan.');
        redirect('master/relation/product-recipe/' . $productId);
    }

    public function product_recipe_edit(int $id)
    {
        $row = $this->Master_model->get_by_id('mst_product_recipe', $id);
        if (!$row) show_404();
        $parent = $this->loadProductRecipeParent((int)$row['product_id']);
        if (!$parent) show_404();

        $this->render('master/relation_form', [
            'title' => 'Edit Line Recipe Product',
            'active_menu' => 'grp.master',
            'relation_type' => 'product-recipe',
            'parent' => $parent,
            'row' => $row,
            'form_action' => 'master/relation/product-recipe/edit/' . $id . '/update',
            'options' => $this->productRecipeOptions($parent, $row),
            'product_variable_cost' => $this->productRecipeVariableCostContext($parent),
        ]);
    }

    public function product_recipe_update(int $id)
    {
        $row = $this->Master_model->get_by_id('mst_product_recipe', $id);
        if (!$row) show_404();

        $product = $this->loadProductRecipeParent((int)$row['product_id']);
        if (!$product) show_404();

        $this->form_validation->set_rules('line_type', 'Line Type', 'required');
        $this->form_validation->set_rules('qty', 'Qty', 'required|numeric');

        if ($this->form_validation->run() === false) {
            $this->session->set_flashdata('error', validation_errors('<li>', '</li>'));
            redirect('master/relation/product-recipe/edit/' . $id);
            return;
        }

        $lineType = (string)$this->input->post('line_type', true);
        $materialItemId = $lineType === 'MATERIAL' ? (int)$this->input->post('material_item_id', true) : null;
        $componentId = $lineType === 'COMPONENT' ? (int)$this->input->post('component_id', true) : null;

        if ($lineType === 'MATERIAL' && empty($materialItemId)) {
            $this->session->set_flashdata('error', 'Item bahan baku wajib dipilih.');
            redirect('master/relation/product-recipe/edit/' . $id);
            return;
        }
        if ($lineType === 'COMPONENT' && empty($componentId)) {
            $this->session->set_flashdata('error', 'Component wajib dipilih.');
            redirect('master/relation/product-recipe/edit/' . $id);
            return;
        }

        $resolved = $this->resolveProductRecipeInput(
            $product,
            $lineType,
            $materialItemId ?: 0,
            $componentId ?: 0,
            (int)$this->input->post('source_division_id', true) ?: null,
            $row
        );
        if (empty($resolved['ok'])) {
            $this->session->set_flashdata('error', (string)($resolved['message'] ?? 'Data resep produk tidak valid.'));
            redirect('master/relation/product-recipe/edit/' . $id);
            return;
        }

        $ingredientRole = $this->normalizeProductRecipeIngredientRole($this->input->post('ingredient_role', true));
        if ($lineType === 'MATERIAL') {
            $duplicateCheck = $this->validateProductRecipeMaterialDuplicate((int)$row['product_id'], $resolved, $ingredientRole, $id);
            if (empty($duplicateCheck['ok'])) {
                $this->session->set_flashdata('error', (string)($duplicateCheck['message'] ?? 'Material resep produk dobel.'));
                redirect('master/relation/product-recipe/edit/' . $id);
                return;
            }
        }

        $payload = [
            'line_no' => (int)$this->input->post('line_no', true) ?: 1,
            'line_type' => $lineType,
            'material_item_id' => $resolved['material_item_id'],
            'component_id' => $resolved['component_id'],
            'source_division_id' => $resolved['source_division_id'],
            'qty' => (float)$this->input->post('qty', true),
            'uom_id' => $resolved['uom_id'],
            'notes' => $this->input->post('notes', true),
            'sort_order' => (int)$this->input->post('sort_order', true) ?: 0,
        ];
        if ($this->db->field_exists('ingredient_role', 'mst_product_recipe')) {
            $payload['ingredient_role'] = $ingredientRole;
        }

        $this->Master_model->update('mst_product_recipe', $id, $payload);

        $this->session->set_flashdata('success', 'Line recipe product berhasil diperbarui.');
        redirect('master/relation/product-recipe/' . (int)$row['product_id']);
    }

    public function product_recipe_delete(int $id)
    {
        $row = $this->Master_model->get_by_id('mst_product_recipe', $id);
        if (!$row) show_404();

        $this->db->where('id', $id)->delete('mst_product_recipe');
        $this->session->set_flashdata('success', 'Line recipe product berhasil dihapus.');
        redirect('master/relation/product-recipe/' . (int)$row['product_id']);
    }

    public function component_formula(int $componentId)
    {
        $component = $this->Master_model->get_by_id('mst_component', $componentId);
        if (!$component) show_404();

        $this->db->select('f.*, i.item_name, c.component_name, u.name AS uom_name');
        $this->db->from('mst_component_formula f');
        $this->db->join('mst_item i', 'i.id = f.material_item_id', 'left');
        $this->db->join('mst_component c', 'c.id = f.sub_component_id', 'left');
        $this->db->join('mst_uom u', 'u.id = f.uom_id', 'left');
        $this->db->where('f.component_id', $componentId);
        $this->db->order_by('f.sort_order ASC, f.id ASC');
        $rows = $this->db->get()->result_array();

        $this->render('master/relation_list', [
            'title' => 'Relasi Formula Component',
            'active_menu' => 'grp.master',
            'relation_type' => 'component-formula',
            'parent' => $component,
            'rows' => $rows,
        ]);
    }

    public function component_formula_create(int $componentId)
    {
        $component = $this->Master_model->get_by_id('mst_component', $componentId);
        if (!$component) show_404();

        $this->render('master/relation_form', [
            'title' => 'Tambah Line Formula Component',
            'active_menu' => 'grp.master',
            'relation_type' => 'component-formula',
            'parent' => $component,
            'row' => null,
            'form_action' => 'master/relation/component-formula/' . $componentId . '/store',
            'options' => $this->componentFormulaOptions($componentId),
        ]);
    }

    public function component_formula_store(int $componentId)
    {
        $component = $this->Master_model->get_by_id('mst_component', $componentId);
        if (!$component) show_404();

        $this->form_validation->set_rules('line_type', 'Line Type', 'required');
        $this->form_validation->set_rules('qty', 'Qty', 'required|numeric');
        $this->form_validation->set_rules('uom_id', 'Satuan', 'required|integer');

        if ($this->form_validation->run() === false) {
            $this->session->set_flashdata('error', validation_errors('<li>', '</li>'));
            redirect('master/relation/component-formula/' . $componentId . '/create');
            return;
        }

        $lineType = (string)$this->input->post('line_type', true);
        $materialItemId = $lineType === 'MATERIAL' ? (int)$this->input->post('material_item_id', true) : null;
        $subComponentId = $lineType === 'COMPONENT' ? (int)$this->input->post('sub_component_id', true) : null;

        if ($lineType === 'COMPONENT' && $subComponentId === $componentId) {
            $this->session->set_flashdata('error', 'Sub component tidak boleh sama dengan component induk.');
            redirect('master/relation/component-formula/' . $componentId . '/create');
            return;
        }

        $this->Master_model->insert('mst_component_formula', [
            'component_id' => $componentId,
            'line_no' => (int)$this->input->post('line_no', true) ?: 1,
            'line_type' => $lineType,
            'material_item_id' => $materialItemId ?: null,
            'sub_component_id' => $subComponentId ?: null,
            'qty' => (float)$this->input->post('qty', true),
            'uom_id' => (int)$this->input->post('uom_id', true),
            'notes' => $this->input->post('notes', true),
            'sort_order' => (int)$this->input->post('sort_order', true) ?: 0,
        ]);

        $this->session->set_flashdata('success', 'Line formula component berhasil ditambahkan.');
        redirect('master/relation/component-formula/' . $componentId);
    }

    public function component_formula_edit(int $id)
    {
        $row = $this->Master_model->get_by_id('mst_component_formula', $id);
        if (!$row) show_404();
        $parent = $this->Master_model->get_by_id('mst_component', (int)$row['component_id']);
        if (!$parent) show_404();

        $this->render('master/relation_form', [
            'title' => 'Edit Line Formula Component',
            'active_menu' => 'grp.master',
            'relation_type' => 'component-formula',
            'parent' => $parent,
            'row' => $row,
            'form_action' => 'master/relation/component-formula/edit/' . $id . '/update',
            'options' => $this->componentFormulaOptions((int)$row['component_id']),
        ]);
    }

    public function component_formula_update(int $id)
    {
        $row = $this->Master_model->get_by_id('mst_component_formula', $id);
        if (!$row) show_404();

        $lineType = (string)$this->input->post('line_type', true);
        $materialItemId = $lineType === 'MATERIAL' ? (int)$this->input->post('material_item_id', true) : null;
        $subComponentId = $lineType === 'COMPONENT' ? (int)$this->input->post('sub_component_id', true) : null;

        if ($lineType === 'COMPONENT' && $subComponentId === (int)$row['component_id']) {
            $this->session->set_flashdata('error', 'Sub component tidak boleh sama dengan component induk.');
            redirect('master/relation/component-formula/edit/' . $id);
            return;
        }

        $this->Master_model->update('mst_component_formula', $id, [
            'line_no' => (int)$this->input->post('line_no', true) ?: 1,
            'line_type' => $lineType,
            'material_item_id' => $materialItemId ?: null,
            'sub_component_id' => $subComponentId ?: null,
            'qty' => (float)$this->input->post('qty', true),
            'uom_id' => (int)$this->input->post('uom_id', true),
            'notes' => $this->input->post('notes', true),
            'sort_order' => (int)$this->input->post('sort_order', true) ?: 0,
        ]);

        $this->session->set_flashdata('success', 'Line formula component berhasil diperbarui.');
        redirect('master/relation/component-formula/' . (int)$row['component_id']);
    }

    public function component_formula_delete(int $id)
    {
        $row = $this->Master_model->get_by_id('mst_component_formula', $id);
        if (!$row) show_404();

        $this->db->where('id', $id)->delete('mst_component_formula');
        $this->session->set_flashdata('success', 'Line formula component berhasil dihapus.');
        redirect('master/relation/component-formula/' . (int)$row['component_id']);
    }

    public function product_extra(int $productId)
    {
        $product = $this->Master_model->get_by_id('mst_product', $productId);
        if (!$product) show_404();

        $this->db->select('m.*, g.group_code, g.group_name');
        $this->db->from('mst_product_extra_map m');
        $this->db->join('mst_extra_group g', 'g.id = m.extra_group_id');
        $this->db->where('m.product_id', $productId);
        $this->db->order_by('m.sort_order ASC, m.id ASC');
        $rows = $this->db->get()->result_array();

        $this->render('master/relation_list', [
            'title' => 'Mapping Product Extra Group',
            'active_menu' => 'grp.master',
            'relation_type' => 'product-extra',
            'parent' => $product,
            'rows' => $rows,
        ]);
    }

    public function product_extra_hub()
    {
        $q = trim((string)$this->input->get('q', true));

        $this->db->select('p.id, p.product_code, p.product_name, pd.name AS product_division_name, COUNT(m.id) AS total_line');
        $this->db->from('mst_product p');
        $this->db->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left');
        $this->db->join('mst_product_extra_map m', 'm.product_id = p.id', 'left');
        $this->db->where('p.is_active', 1);
        if ($q !== '') {
            $this->db->group_start();
            $this->db->like('p.product_code', $q);
            $this->db->or_like('p.product_name', $q);
            $this->db->group_end();
        }
        $this->db->group_by('p.id, p.product_code, p.product_name, pd.name');
        $this->db->order_by('p.product_name', 'ASC');
        $rows = $this->db->get()->result_array();

        $this->render('master/relation_hub', [
            'title' => 'Halaman Mapping Extra Produk',
            'active_menu' => 'grp.master',
            'relation_type' => 'product-extra',
            'rows' => $rows,
            'q' => $q,
        ]);
    }

    public function extra_workspace()
    {
        $pageCode = $this->can('product.monitoring.availability.index', 'view')
            ? 'product.monitoring.availability.index'
            : 'master.product_extra.workspace.index';
        $this->require_permission($pageCode, 'view');
        $summary = [
            'total_extra' => $this->db->table_exists('mst_extra') ? (int)$this->db->from('mst_extra')->count_all_results() : 0,
            'total_group' => $this->db->table_exists('mst_extra_group') ? (int)$this->db->from('mst_extra_group')->count_all_results() : 0,
            'total_mapping' => $this->db->table_exists('mst_product_extra_map') ? (int)$this->db->from('mst_product_extra_map')->count_all_results() : 0,
            'total_cashier_ready' => ($this->db->table_exists('mst_extra') && $this->db->field_exists('show_in_cashier', 'mst_extra'))
                ? (int)$this->db->from('mst_extra')->where('show_in_cashier', 1)->count_all_results()
                : 0,
        ];

        $this->render('master/extra_workspace', [
            'title' => 'Workspace Extra Produk',
            'page_title' => 'Workspace Extra Produk',
            'active_menu' => 'master.product_extra.workspace.index',
            'summary' => $summary,
        ]);
    }

    public function extra_group_hub()
    {
        $q = trim((string)$this->input->get('q', true));

        $this->db->select('g.id, g.group_code, g.group_name, pd.name AS product_division_name, COUNT(m.id) AS total_product');
        $this->db->from('mst_extra_group g');
        $this->db->join('mst_product_division pd', 'pd.id = g.product_division_id', 'left');
        $this->db->join('mst_product_extra_map m', 'm.extra_group_id = g.id', 'left');
        if ($q !== '') {
            $this->db->group_start();
            $this->db->like('g.group_code', $q);
            $this->db->or_like('g.group_name', $q);
            $this->db->group_end();
        }
        $this->db->group_by('g.id, g.group_code, g.group_name, pd.name');
        $this->db->order_by('g.group_name', 'ASC');
        $rows = $this->db->get()->result_array();

        $this->render('master/extra_group_hub', [
            'title' => 'Halaman Mapping Group Extra ke Produk',
            'active_menu' => 'grp.master',
            'rows' => $rows,
            'q' => $q,
        ]);
    }

    public function extra_group_products(int $groupId)
    {
        $group = $this->Master_model->get_by_id('mst_extra_group', $groupId);
        if (!$group) show_404();

        $q = trim((string)$this->input->get('q', true));

        $this->db->select('p.id, p.product_code, p.product_name, pd.name AS product_division_name, m.id AS map_id, m.sort_order AS map_sort_order');
        $this->db->from('mst_product p');
        $this->db->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left');
        $this->db->join('mst_product_extra_map m', 'm.product_id = p.id AND m.extra_group_id = ' . (int)$groupId, 'left');
        $this->db->where('p.is_active', 1);
        if (!empty($group['product_division_id'])) {
            $this->db->where('p.product_division_id', (int)$group['product_division_id']);
        }
        if ($q !== '') {
            $this->db->group_start();
            $this->db->like('p.product_code', $q);
            $this->db->or_like('p.product_name', $q);
            $this->db->group_end();
        }
        $this->db->order_by('p.product_name', 'ASC');
        $rows = $this->db->get()->result_array();

        $mappedProductIds = [];
        foreach ($rows as $row) {
            if (!empty($row['map_id'])) {
                $mappedProductIds[] = (int)$row['id'];
            }
        }

        $this->render('master/extra_group_products', [
            'title' => 'Checklist Produk untuk Group Extra',
            'active_menu' => 'grp.master',
            'group' => $group,
            'rows' => $rows,
            'mapped_product_ids' => $mappedProductIds,
            'q' => $q,
        ]);
    }

    public function extra_group_products_save(int $groupId)
    {
        $group = $this->Master_model->get_by_id('mst_extra_group', $groupId);
        if (!$group) show_404();

        $selected = $this->input->post('product_ids');
        if (!is_array($selected)) {
            $selected = [];
        }

        $productIds = [];
        foreach ($selected as $pid) {
            $pid = (int)$pid;
            if ($pid > 0) {
                $productIds[$pid] = true;
            }
        }
        $productIds = array_keys($productIds);

        $this->db->trans_start();

        $this->db->where('extra_group_id', $groupId)->delete('mst_product_extra_map');

        $sort = 10;
        foreach ($productIds as $pid) {
            $this->Master_model->insert('mst_product_extra_map', [
                'extra_group_id' => $groupId,
                'product_id' => $pid,
                'sort_order' => $sort,
            ]);
            $sort += 10;
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            $this->session->set_flashdata('error', 'Gagal menyimpan mapping produk untuk group extra.');
        } else {
            $this->session->set_flashdata('success', 'Mapping produk untuk group extra berhasil disimpan.');
        }

        redirect('master/relation/extra-group/' . $groupId);
    }

    public function extra_group_items_ajax(int $groupId)
    {
        $group = $this->Master_model->get_by_id('mst_extra_group', $groupId);
        if (!$group) {
            return $this->outputJson(['ok' => false, 'message' => 'Group extra tidak ditemukan.'], 404);
        }

        $q = trim((string)$this->input->get('q', true));
        $rows = $this->loadExtraRowsForGroup($groupId, $q);

        return $this->outputJson([
            'ok' => true,
            'group' => [
                'id' => (int)$group['id'],
                'group_code' => (string)($group['group_code'] ?? ''),
                'group_name' => (string)($group['group_name'] ?? ''),
            ],
            'rows' => $rows,
            'selected_ids' => array_values(array_map('intval', array_column(array_filter($rows, static function ($row) {
                return !empty($row['mapped']);
            }), 'id'))),
        ]);
    }

    public function extra_group_items_save_ajax(int $groupId)
    {
        $group = $this->Master_model->get_by_id('mst_extra_group', $groupId);
        if (!$group) {
            return $this->outputJson(['ok' => false, 'message' => 'Group extra tidak ditemukan.'], 404);
        }

        $selected = $this->input->post('extra_ids');
        if (!is_array($selected)) {
            $selected = [];
        }

        $extraIds = [];
        foreach ($selected as $extraId) {
            $extraId = (int)$extraId;
            if ($extraId > 0) {
                $extraIds[$extraId] = true;
            }
        }
        $extraIds = array_keys($extraIds);

        $this->db->trans_start();
        $this->db->where('extra_group_id', $groupId)->delete('mst_extra_group_item');

        $sort = 10;
        foreach ($extraIds as $extraId) {
            $this->Master_model->insert('mst_extra_group_item', [
                'extra_group_id' => $groupId,
                'extra_id' => $extraId,
                'sort_order' => $sort,
            ]);
            $sort += 10;
        }
        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            return $this->outputJson(['ok' => false, 'message' => 'Gagal menyimpan mapping master extra ke group extra.'], 500);
        }

        return $this->outputJson([
            'ok' => true,
            'message' => 'Mapping master extra ke group extra berhasil disimpan.',
            'selected_count' => count($extraIds),
        ]);
    }

    public function extra_group_products_ajax(int $groupId)
    {
        $group = $this->Master_model->get_by_id('mst_extra_group', $groupId);
        if (!$group) {
            return $this->outputJson(['ok' => false, 'message' => 'Group extra tidak ditemukan.'], 404);
        }

        $q = trim((string)$this->input->get('q', true));
        $rows = $this->loadProductRowsForGroup($group, $q);

        return $this->outputJson([
            'ok' => true,
            'group' => [
                'id' => (int)$group['id'],
                'group_code' => (string)($group['group_code'] ?? ''),
                'group_name' => (string)($group['group_name'] ?? ''),
                'product_division_id' => (int)($group['product_division_id'] ?? 0),
            ],
            'rows' => $rows,
            'selected_ids' => array_values(array_map('intval', array_column(array_filter($rows, static function ($row) {
                return !empty($row['mapped']);
            }), 'id'))),
        ]);
    }

    public function extra_group_products_save_ajax(int $groupId)
    {
        $group = $this->Master_model->get_by_id('mst_extra_group', $groupId);
        if (!$group) {
            return $this->outputJson(['ok' => false, 'message' => 'Group extra tidak ditemukan.'], 404);
        }

        $selected = $this->input->post('product_ids');
        if (!is_array($selected)) {
            $selected = [];
        }

        $productIds = [];
        foreach ($selected as $productId) {
            $productId = (int)$productId;
            if ($productId > 0) {
                $productIds[$productId] = true;
            }
        }
        $productIds = array_keys($productIds);

        $this->db->trans_start();
        $this->db->where('extra_group_id', $groupId)->delete('mst_product_extra_map');

        $sort = 10;
        foreach ($productIds as $productId) {
            $this->Master_model->insert('mst_product_extra_map', [
                'extra_group_id' => $groupId,
                'product_id' => $productId,
                'sort_order' => $sort,
            ]);
            $sort += 10;
        }
        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            return $this->outputJson(['ok' => false, 'message' => 'Gagal menyimpan mapping produk untuk group extra.'], 500);
        }

        return $this->outputJson([
            'ok' => true,
            'message' => 'Mapping produk untuk group extra berhasil disimpan.',
            'selected_count' => count($productIds),
        ]);
    }

    public function extra_item_group_hub()
    {
        $q = trim((string)$this->input->get('q', true));

        $this->db->select('e.id, e.extra_code, e.extra_name, e.extra_type, COUNT(m.id) AS total_group');
        $this->db->from('mst_extra e');
        $this->db->join('mst_extra_group_item m', 'm.extra_id = e.id', 'left');
        if ($q !== '') {
            $this->db->group_start();
            $this->db->like('e.extra_code', $q);
            $this->db->or_like('e.extra_name', $q);
            $this->db->group_end();
        }
        $this->db->group_by('e.id, e.extra_code, e.extra_name, e.extra_type');
        $this->db->order_by('e.extra_name', 'ASC');
        $rows = $this->db->get()->result_array();

        $this->render('master/extra_item_group_hub', [
            'title' => 'Halaman Mapping Extra ke Group Extra',
            'active_menu' => 'grp.master',
            'rows' => $rows,
            'q' => $q,
        ]);
    }

    public function extra_item_groups(int $extraId)
    {
        $extra = $this->Master_model->get_by_id('mst_extra', $extraId);
        if (!$extra) show_404();

        $q = trim((string)$this->input->get('q', true));

        $this->db->select('g.id, g.group_code, g.group_name, g.is_required, pd.name AS product_division_name, m.id AS map_id, m.sort_order AS map_sort_order');
        $this->db->from('mst_extra_group g');
        $this->db->join('mst_product_division pd', 'pd.id = g.product_division_id', 'left');
        $this->db->join('mst_extra_group_item m', 'm.extra_group_id = g.id AND m.extra_id = ' . (int)$extraId, 'left');
        if ($q !== '') {
            $this->db->group_start();
            $this->db->like('g.group_code', $q);
            $this->db->or_like('g.group_name', $q);
            $this->db->group_end();
        }
        $this->db->order_by('g.sort_order', 'ASC');
        $this->db->order_by('g.group_name', 'ASC');
        $rows = $this->db->get()->result_array();

        $mappedGroupIds = [];
        foreach ($rows as $row) {
            if (!empty($row['map_id'])) {
                $mappedGroupIds[] = (int)$row['id'];
            }
        }

        $this->render('master/extra_item_groups', [
            'title' => 'Checklist Group untuk Master Extra',
            'active_menu' => 'grp.master',
            'extra' => $extra,
            'rows' => $rows,
            'mapped_group_ids' => $mappedGroupIds,
            'q' => $q,
        ]);
    }

    public function extra_item_groups_save(int $extraId)
    {
        $extra = $this->Master_model->get_by_id('mst_extra', $extraId);
        if (!$extra) show_404();

        $selected = $this->input->post('group_ids');
        if (!is_array($selected)) {
            $selected = [];
        }

        $groupIds = [];
        foreach ($selected as $groupId) {
            $groupId = (int)$groupId;
            if ($groupId > 0) {
                $groupIds[$groupId] = true;
            }
        }
        $groupIds = array_keys($groupIds);

        $this->db->trans_start();

        $this->db->where('extra_id', $extraId)->delete('mst_extra_group_item');

        $sort = 10;
        foreach ($groupIds as $groupId) {
            $this->Master_model->insert('mst_extra_group_item', [
                'extra_group_id' => $groupId,
                'extra_id' => $extraId,
                'sort_order' => $sort,
            ]);
            $sort += 10;
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            $this->session->set_flashdata('error', 'Gagal menyimpan mapping master extra ke group extra.');
        } else {
            $this->session->set_flashdata('success', 'Mapping master extra ke group extra berhasil disimpan.');
        }

        redirect('master/relation/extra-item-group/' . $extraId);
    }

    private function loadExtraRowsForGroup(int $groupId, string $q = ''): array
    {
        $this->db->select('e.id, e.extra_code, e.extra_name, e.extra_type, e.source_kind, m.id AS map_id, m.sort_order AS map_sort_order');
        $this->db->from('mst_extra e');
        $this->db->join('mst_extra_group_item m', 'm.extra_id = e.id AND m.extra_group_id = ' . $groupId, 'left');
        if ($q !== '') {
            $this->db->group_start();
            $this->db->like('e.extra_code', $q);
            $this->db->or_like('e.extra_name', $q);
            $this->db->group_end();
        }
        $this->db->order_by('e.extra_name', 'ASC');
        $rows = $this->db->get()->result_array();

        foreach ($rows as &$row) {
            $row['mapped'] = !empty($row['map_id']);
        }
        unset($row);

        return $rows;
    }

    private function loadProductRowsForGroup(array $group, string $q = ''): array
    {
        $groupId = (int)($group['id'] ?? 0);
        $groupDivisionId = (int)($group['product_division_id'] ?? 0);

        $this->db->select('
            p.id,
            p.product_code,
            p.product_name,
            pd.name AS product_division_name,
            pc.name AS classification_name,
            cat.name AS product_category_name,
            m.id AS map_id,
            m.sort_order AS map_sort_order
        ');
        $this->db->from('mst_product p');
        $this->db->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left');
        $this->db->join('mst_product_classification pc', 'pc.id = p.classification_id', 'left');
        $this->db->join('mst_product_category cat', 'cat.id = p.product_category_id', 'left');
        $this->db->join('mst_product_extra_map m', 'm.product_id = p.id AND m.extra_group_id = ' . $groupId, 'left');
        $this->db->where('p.is_active', 1);
        if ($groupDivisionId > 0) {
            $this->db->where('p.product_division_id', $groupDivisionId);
        }
        if ($q !== '') {
            $this->db->group_start();
            $this->db->like('p.product_code', $q);
            $this->db->or_like('p.product_name', $q);
            $this->db->group_end();
        }
        $this->db->order_by('COALESCE(pd.sort_order, 999999)', 'ASC', false);
        $this->db->order_by('pd.name', 'ASC');
        $this->db->order_by('COALESCE(pc.sort_order, 999999)', 'ASC', false);
        $this->db->order_by('pc.name', 'ASC');
        $this->db->order_by('COALESCE(cat.sort_order, 999999)', 'ASC', false);
        $this->db->order_by('cat.name', 'ASC');
        $this->db->order_by('p.product_name', 'ASC');
        $rows = $this->db->get()->result_array();

        foreach ($rows as &$row) {
            $row['mapped'] = !empty($row['map_id']);
        }
        unset($row);

        return $rows;
    }

    private function outputJson(array $payload, int $statusCode = 200)
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        return $this->output
            ->set_status_header($statusCode)
            ->set_content_type('application/json')
            ->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public function product_extra_create(int $productId)
    {
        $product = $this->Master_model->get_by_id('mst_product', $productId);
        if (!$product) show_404();

        $this->render('master/relation_form', [
            'title' => 'Tambah Mapping Product Extra Group',
            'active_menu' => 'grp.master',
            'relation_type' => 'product-extra',
            'parent' => $product,
            'row' => null,
            'form_action' => 'master/relation/product-extra/' . $productId . '/store',
            'options' => [
                'extra_groups' => $this->Master_model->get_options('mst_extra_group', 'id', 'group_name', false),
            ],
        ]);
    }

    public function product_extra_store(int $productId)
    {
        $product = $this->Master_model->get_by_id('mst_product', $productId);
        if (!$product) show_404();

        $extraGroupId = (int)$this->input->post('extra_group_id', true);
        if ($extraGroupId <= 0) {
            $this->session->set_flashdata('error', 'Group extra wajib dipilih.');
            redirect('master/relation/product-extra/' . $productId . '/create');
            return;
        }

        $exists = $this->db->get_where('mst_product_extra_map', [
            'product_id' => $productId,
            'extra_group_id' => $extraGroupId,
        ])->row_array();

        if ($exists) {
            $this->session->set_flashdata('warning', 'Mapping sudah ada.');
            redirect('master/relation/product-extra/' . $productId);
            return;
        }

        $this->Master_model->insert('mst_product_extra_map', [
            'product_id' => $productId,
            'extra_group_id' => $extraGroupId,
            'sort_order' => (int)$this->input->post('sort_order', true) ?: 0,
        ]);

        $this->session->set_flashdata('success', 'Mapping product-extra berhasil ditambahkan.');
        redirect('master/relation/product-extra/' . $productId);
    }

    public function product_extra_delete(int $id)
    {
        $row = $this->Master_model->get_by_id('mst_product_extra_map', $id);
        if (!$row) show_404();

        $this->db->where('id', $id)->delete('mst_product_extra_map');
        $this->session->set_flashdata('success', 'Mapping product-extra berhasil dihapus.');
        redirect('master/relation/product-extra/' . (int)$row['product_id']);
    }

    public function product_bundle_hub()
    {
        if (!$this->db->table_exists('pos_product_bundle')) {
            show_error('Tabel pos_product_bundle belum tersedia. Jalankan fondasi POS bundle terlebih dulu.', 500, 'Bundle Produk Belum Siap');
        }

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => strtoupper(trim((string)$this->input->get('status', true) ?: 'ACTIVE')),
            'product_division_id' => (int)$this->input->get('product_division_id', true),
            'pos_scope' => strtoupper(trim((string)$this->input->get('pos_scope', true) ?: 'ALL')),
        ];
        if (!in_array($filters['status'], ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $filters['status'] = 'ACTIVE';
        }
        if (!in_array($filters['pos_scope'], ['ALL', 'REGULAR', 'EVENT'], true)) {
            $filters['pos_scope'] = 'ALL';
        }

        $this->db->select("
            b.*,
            pd.name AS product_division_name,
            COUNT(bl.id) AS total_line,
            COALESCE(SUM(bl.qty * COALESCE(bl.unit_price_override, p.selling_price, 0)), 0) AS line_value_total
        ", false);
        $this->db->from('pos_product_bundle b');
        $this->db->join('mst_product_division pd', 'pd.id = b.product_division_id', 'left');
        $this->db->join('pos_product_bundle_line bl', 'bl.bundle_id = b.id', 'left');
        $this->db->join('mst_product p', 'p.id = bl.product_id', 'left');
        if ($filters['q'] !== '') {
            $this->db->group_start();
            $this->db->like('b.bundle_code', $filters['q']);
            $this->db->or_like('b.bundle_name', $filters['q']);
            $this->db->or_like('b.description', $filters['q']);
            $this->db->group_end();
        }
        if ($filters['status'] !== 'ALL') {
            $this->db->where('b.is_active', $filters['status'] === 'ACTIVE' ? 1 : 0);
        }
        if ($filters['product_division_id'] > 0) {
            $this->db->where('b.product_division_id', $filters['product_division_id']);
        }
        if ($filters['pos_scope'] !== 'ALL') {
            $this->db->where_in('b.pos_scope', [$filters['pos_scope'], 'ALL']);
        }
        $this->db->group_by('b.id');
        if ($this->db->field_exists('sort_order', 'mst_product_division')) {
            $this->db->order_by('COALESCE(pd.sort_order, 999999)', 'ASC', false);
        }
        $this->db->order_by('pd.name', 'ASC');
        $this->db->order_by('b.bundle_name', 'ASC');
        $rows = $this->db->get()->result_array();

        $summary = [
            'total_bundle' => count($rows),
            'active_bundle' => 0,
            'inactive_bundle' => 0,
            'regular_scope' => 0,
            'event_scope' => 0,
            'line_total' => 0,
        ];
        foreach ($rows as $row) {
            if ((int)($row['is_active'] ?? 0) === 1) {
                $summary['active_bundle']++;
            } else {
                $summary['inactive_bundle']++;
            }
            if (($row['pos_scope'] ?? '') === 'EVENT') {
                $summary['event_scope']++;
            } else {
                $summary['regular_scope']++;
            }
            $summary['line_total'] += (int)($row['total_line'] ?? 0);
        }

        $this->render('master/product_bundle_hub', [
            'title' => 'Bundle Produk',
            'active_menu' => 'master.product.bundle',
            'rows' => $rows,
            'filters' => $filters,
            'summary' => $summary,
            'product_division_options' => $this->Master_model->get_options('mst_product_division', 'id', 'name', true),
        ]);
    }

    public function product_bundle(int $bundleId)
    {
        $bundle = $this->loadProductBundle($bundleId);
        if (!$bundle) {
            show_404();
        }

        $rows = $this->loadProductBundleLines($bundleId);
        $summary = $this->buildProductBundleSummary($bundle, $rows);
        $pricingPreview = $this->buildProductBundlePricingPreview($bundle, $rows);

        $this->render('master/product_bundle_show', [
            'title' => 'Detail Bundle Produk',
            'active_menu' => 'master.product.bundle',
            'bundle' => $bundle,
            'rows' => $rows,
            'summary' => $summary,
            'pricing_preview' => $pricingPreview,
        ]);
    }

    public function product_bundle_create()
    {
        if (!$this->db->table_exists('pos_product_bundle')) {
            show_error('Tabel pos_product_bundle belum tersedia. Jalankan fondasi POS bundle terlebih dulu.', 500, 'Bundle Produk Belum Siap');
        }

        $this->render('master/product_bundle_edit', [
            'title' => 'Tambah Bundle Produk',
            'active_menu' => 'master.product.bundle',
            'bundle' => null,
            'rows' => [],
            'summary' => [
                'line_count' => 0,
                'line_value_total' => 0,
                'bundle_price' => 0,
                'bundle_gap' => 0,
                'line_qty_total' => 0,
            ],
            'product_division_options' => $this->Master_model->get_options('mst_product_division', 'id', 'name', true),
            'save_url' => site_url('master/relation/product-bundle/create/save'),
            'back_url' => site_url('master/relation/product-bundle'),
        ]);
    }

    public function product_bundle_store()
    {
        $payload = $this->normalizeProductBundlePayload(0);
        if (empty($payload['ok'])) {
            $this->session->set_flashdata('error', (string)($payload['message'] ?? 'Payload bundle produk tidak valid.'));
            redirect('master/relation/product-bundle/create');
            return;
        }

        $this->db->trans_start();
        $this->db->insert('pos_product_bundle', $payload['bundle']);
        $bundleId = (int)$this->db->insert_id();
        foreach ($payload['lines'] as $line) {
            $line['bundle_id'] = $bundleId;
            $this->db->insert('pos_product_bundle_line', $line);
        }
        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            $this->session->set_flashdata('error', 'Gagal menyimpan bundle produk.');
            redirect('master/relation/product-bundle/create');
            return;
        }

        $this->session->set_flashdata('success', 'Bundle produk berhasil ditambahkan.');
        redirect('master/relation/product-bundle/' . $bundleId);
    }

    public function product_bundle_edit(int $bundleId)
    {
        $bundle = $this->loadProductBundle($bundleId);
        if (!$bundle) {
            show_404();
        }

        $rows = $this->loadProductBundleLines($bundleId);
        $summary = $this->buildProductBundleSummary($bundle, $rows);

        $this->render('master/product_bundle_edit', [
            'title' => 'Edit Bundle Produk',
            'active_menu' => 'master.product.bundle',
            'bundle' => $bundle,
            'rows' => $rows,
            'summary' => $summary,
            'product_division_options' => $this->Master_model->get_options('mst_product_division', 'id', 'name', true),
            'save_url' => site_url('master/relation/product-bundle/edit/' . $bundleId . '/save'),
            'back_url' => site_url('master/relation/product-bundle/' . $bundleId),
        ]);
    }

    public function product_bundle_update(int $bundleId)
    {
        $bundle = $this->loadProductBundle($bundleId);
        if (!$bundle) {
            show_404();
        }

        $payload = $this->normalizeProductBundlePayload($bundleId);
        if (empty($payload['ok'])) {
            $this->session->set_flashdata('error', (string)($payload['message'] ?? 'Payload bundle produk tidak valid.'));
            redirect('master/relation/product-bundle/edit/' . $bundleId);
            return;
        }

        $this->db->trans_start();
        $this->db->where('id', $bundleId)->update('pos_product_bundle', $payload['bundle']);
        $this->db->where('bundle_id', $bundleId)->delete('pos_product_bundle_line');
        foreach ($payload['lines'] as $line) {
            $line['bundle_id'] = $bundleId;
            $this->db->insert('pos_product_bundle_line', $line);
        }
        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            $this->session->set_flashdata('error', 'Gagal memperbarui bundle produk.');
            redirect('master/relation/product-bundle/edit/' . $bundleId);
            return;
        }

        $this->session->set_flashdata('success', 'Bundle produk berhasil diperbarui.');
        redirect('master/relation/product-bundle/' . $bundleId);
    }

    public function product_bundle_toggle(int $bundleId)
    {
        $bundle = $this->loadProductBundle($bundleId);
        if (!$bundle) {
            show_404();
        }

        $this->db->where('id', $bundleId)->update('pos_product_bundle', [
            'is_active' => (int)($bundle['is_active'] ?? 0) === 1 ? 0 : 1,
        ]);
        $this->session->set_flashdata('success', 'Status bundle produk berhasil diperbarui.');
        redirect('master/relation/product-bundle');
    }

    public function product_bundle_product_search()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $q = trim((string)$this->input->get('q', true));
        $selectedIds = $this->input->get('selected_ids');
        if (!is_array($selectedIds)) {
            $selectedIds = [];
        }
        $selectedIds = array_values(array_unique(array_filter(array_map('intval', $selectedIds))));

        $this->db->select('p.id, p.product_code, p.product_name, p.selling_price, p.product_division_id, pd.name AS product_division_name, u.code AS uom_code');
        $this->db->from('mst_product p');
        $this->db->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left');
        $this->db->join('mst_uom u', 'u.id = p.uom_id', 'left');
        $this->db->where('p.is_active', 1);
        if ($this->db->field_exists('show_pos', 'mst_product')) {
            $this->db->where('p.show_pos', 1);
        }
        if ($q !== '') {
            $this->db->group_start();
            $this->db->like('p.product_name', $q);
            $this->db->or_like('p.product_code', $q);
            $this->db->group_end();
        }
        if (!empty($selectedIds)) {
            $this->db->where_not_in('p.id', $selectedIds);
        }
        $this->db->order_by('p.product_name', 'ASC');
        $rows = $this->db->limit(12)->get()->result_array();

        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'id' => (int)$row['id'],
                'product_code' => (string)($row['product_code'] ?? ''),
                'product_name' => (string)($row['product_name'] ?? ''),
                'label' => trim((string)($row['product_name'] ?? '-')),
                'division_id' => (int)($row['product_division_id'] ?? 0),
                'division_name' => (string)($row['product_division_name'] ?? '-'),
                'selling_price' => round((float)($row['selling_price'] ?? 0), 2),
                'uom_code' => (string)($row['uom_code'] ?? ''),
            ];
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => true,
                'results' => $results,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function productRecipeOptions(array $product, ?array $row = null): array
    {
        $defaultDivision = $this->resolveProductRecipeDefaultDivision($product);
        $currentMaterialId = (int)($row['material_item_id'] ?? 0);
        $currentComponentId = (int)($row['component_id'] ?? 0);
        $currentSourceDivisionId = (int)($row['source_division_id'] ?? 0);

        $this->db->select('i.id, i.item_name, i.content_uom_id AS uom_id, u.name AS uom_name');
        $this->db->from('mst_item i');
        $this->db->join('mst_uom u', 'u.id = i.content_uom_id', 'left');
        $this->db->where('i.is_material', 1);
        $this->db->group_start();
        $this->db->where('i.is_active', 1);
        if ($currentMaterialId > 0) {
            $this->db->or_where('i.id', $currentMaterialId);
        }
        $this->db->group_end();
        $this->db->order_by('i.item_name', 'ASC');
        $materialRows = $this->db->get()->result_array();

        $materials = [];
        foreach ($materialRows as $item) {
            $materials[] = [
                'value' => $item['id'],
                'label' => $item['item_name'],
                'uom_id' => (int)($item['uom_id'] ?? 0),
                'uom_label' => (string)($item['uom_name'] ?? '-'),
                'source_division_id' => $defaultDivision['id'],
                'source_division_name' => $defaultDivision['name'],
            ];
        }

        $this->db->select('c.id, c.component_name, c.product_division_id, c.operational_division_id, d.name AS operational_division_name, c.uom_id, u.name AS uom_name');
        $this->db->from('mst_component c');
        $this->db->join('mst_operational_division d', 'd.id = c.operational_division_id', 'left');
        $this->db->join('mst_uom u', 'u.id = c.uom_id', 'left');
        $this->db->group_start();
        $this->db->where('c.is_active', 1);
        if ($currentComponentId > 0) {
            $this->db->or_where('c.id', $currentComponentId);
        }
        $this->db->group_end();
        $this->db->order_by('c.component_name', 'ASC');
        $componentRows = $this->db->get()->result_array();

        $components = [];
        foreach ($componentRows as $component) {
            $components[] = [
                'value' => $component['id'],
                'label' => $component['component_name'],
                'uom_id' => (int)($component['uom_id'] ?? 0),
                'uom_label' => (string)($component['uom_name'] ?? '-'),
                'source_division_id' => (int)($component['operational_division_id'] ?? 0),
                'source_division_name' => (string)($component['operational_division_name'] ?? '-'),
            ];
        }

        $sourceDivisionRows = $this->db
            ->select('id, name, is_active')
            ->from('mst_operational_division')
            ->group_start()
                ->where('is_active', 1)
                ->or_where('id', $currentSourceDivisionId)
            ->group_end()
            ->order_by('sort_order', 'ASC')
            ->order_by('name', 'ASC')
            ->get()
            ->result_array();

        $sourceDivisions = [];
        foreach ($sourceDivisionRows as $division) {
            $sourceDivisions[] = [
                'value' => (int)($division['id'] ?? 0),
                'label' => (string)($division['name'] ?? '-'),
            ];
        }

        return [
            'materials' => $materials,
            'components' => $components,
            'source_divisions' => $sourceDivisions,
            'ingredient_roles' => $this->productRecipeIngredientRoleOptions(),
            'uoms' => $this->Master_model->get_options('mst_uom', 'id', 'name', true),
            'default_source_division_id' => $defaultDivision['id'],
            'default_source_division_name' => $defaultDivision['name'],
        ];
    }

    private function productRecipeIngredientRoleOptions(): array
    {
        return [
            ['value' => 'MAIN', 'label' => 'Bahan Utama'],
            ['value' => 'SUPPORT', 'label' => 'Pendukung'],
            ['value' => 'GARNISH', 'label' => 'Garnish'],
            ['value' => 'OPTIONAL', 'label' => 'Opsional'],
            ['value' => 'SAUCE', 'label' => 'Saus'],
            ['value' => 'TOPPING', 'label' => 'Topping'],
            ['value' => 'OTHER', 'label' => 'Lainnya'],
        ];
    }

    private function searchProductRecipeMaterialSourceRows(array $product, string $q, int $id): array
    {
        $defaultDivision = $this->resolveProductRecipeDefaultDivision($product);

        $this->db->select('i.id AS value, i.item_name AS label, i.content_uom_id AS uom_id, u.name AS uom_name, m.material_code, m.material_name');
        $this->db->from('mst_item i');
        $this->db->join('mst_material m', 'm.id = i.material_id', 'left');
        $this->db->join('mst_uom u', 'u.id = i.content_uom_id', 'left');
        $this->db->where('i.is_material', 1);
        $this->db->group_start();
        $this->db->where('i.is_active', 1);
        if ($id > 0) {
            $this->db->or_where('i.id', $id);
        }
        $this->db->group_end();
        if ($id > 0) {
            $this->db->where('i.id', $id);
        } elseif ($q !== '') {
            $this->db->group_start()
                ->like('i.item_name', $q)
                ->or_like('m.material_name', $q)
                ->or_like('m.material_code', $q)
                ->group_end();
        }
        $this->db->order_by('i.item_name', 'ASC');
        $this->db->limit($id > 0 ? 1 : 20);
        $rows = $this->db->get()->result_array();

        return array_map(static function (array $row) use ($defaultDivision): array {
            return [
                'value' => (int)($row['value'] ?? 0),
                'label' => (string)($row['label'] ?? ''),
                'meta' => trim((string)($row['material_code'] ?? '-') . ' | ' . (string)($row['material_name'] ?? $row['label'] ?? '-') . ' | ' . (string)($row['uom_name'] ?? '-')),
                'uom_id' => (int)($row['uom_id'] ?? 0),
                'uom_label' => (string)($row['uom_name'] ?? '-'),
                'source_division_id' => (int)($defaultDivision['id'] ?? 0),
                'source_division_name' => (string)($defaultDivision['name'] ?? '-'),
            ];
        }, $rows);
    }

    private function searchProductRecipeComponentSourceRows(string $q, int $id, int $sourceDivisionId): array
    {
        $this->db->select('c.id AS value, c.component_name AS label, c.component_code, c.operational_division_id AS source_division_id, d.name AS source_division_name, c.uom_id, u.name AS uom_name');
        $this->db->from('mst_component c');
        $this->db->join('mst_operational_division d', 'd.id = c.operational_division_id', 'left');
        $this->db->join('mst_uom u', 'u.id = c.uom_id', 'left');
        $this->db->group_start();
        $this->db->where('c.is_active', 1);
        if ($id > 0) {
            $this->db->or_where('c.id', $id);
        }
        $this->db->group_end();
        if ($id > 0) {
            $this->db->where('c.id', $id);
        } else {
            if ($sourceDivisionId > 0) {
                $this->db->where('c.operational_division_id', $sourceDivisionId);
            }
            if ($q !== '') {
                $this->db->group_start()
                    ->like('c.component_name', $q)
                    ->or_like('c.component_code', $q)
                    ->group_end();
            }
        }
        $this->db->order_by('c.component_name', 'ASC');
        $this->db->limit($id > 0 ? 1 : 20);
        $rows = $this->db->get()->result_array();

        return array_map(static function (array $row): array {
            return [
                'value' => (int)($row['value'] ?? 0),
                'label' => (string)($row['label'] ?? ''),
                'meta' => trim((string)($row['component_code'] ?? '-') . ' | ' . (string)($row['source_division_name'] ?? '-') . ' | ' . (string)($row['uom_name'] ?? '-')),
                'uom_id' => (int)($row['uom_id'] ?? 0),
                'uom_label' => (string)($row['uom_name'] ?? '-'),
                'source_division_id' => (int)($row['source_division_id'] ?? 0),
                'source_division_name' => (string)($row['source_division_name'] ?? '-'),
            ];
        }, $rows);
    }

    private function normalizeProductRecipeIngredientRole($role): string
    {
        $role = strtoupper(trim((string)$role));
        $allowed = ['MAIN', 'SUPPORT', 'GARNISH', 'OPTIONAL', 'SAUCE', 'TOPPING', 'OTHER'];

        return in_array($role, $allowed, true) ? $role : 'MAIN';
    }

    private function productAvailabilityFilters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        $allowedStatus = ['ALL', 'READY', 'WARNING', 'SHORT', 'EMPTY', 'NO_RECIPE'];
        $stockMode = strtoupper(trim((string)$this->input->get('stock_mode', true)));
        $allowedStockMode = ['ALL', 'AUTO', 'MANUAL_AVAILABLE', 'MANUAL_OUT'];

        return [
            'q' => trim((string)$this->input->get('q', true)),
            'outlet_id' => (int)$this->input->get('outlet_id', true),
            'product_division_id' => (int)$this->input->get('product_division_id', true),
            'status' => in_array($status, $allowedStatus, true) ? $status : 'ALL',
            'stock_mode' => in_array($stockMode, $allowedStockMode, true) ? $stockMode : 'ALL',
        ];
    }

    private function matchesProductAvailabilityStatus(array $row, string $status): bool
    {
        if ($status === 'ALL') {
            return true;
        }

        return strtoupper((string)($row['availability_status'] ?? '')) === $status;
    }

    private function buildProductAvailabilityRow(array $product): array
    {
        $recipeData = $this->loadProductRecipeData($product);
        $summary = (array)($recipeData['summary'] ?? []);
        $availability = $this->calculateProductAvailabilityMetrics((array)($recipeData['rows'] ?? []));
        $stockModeMeta = $this->productAvailabilityStockModeMeta((string)($product['stock_mode'] ?? 'AUTO'));
        $hppMeta = $this->productAvailabilityHppMeta(
            (float)($summary['hpp_live_percent'] ?? 0),
            (float)($summary['selling_price'] ?? 0),
            (float)($summary['total_hpp_live'] ?? 0)
        );

        return [
            'id' => (int)($product['id'] ?? 0),
            'product_code' => (string)($product['product_code'] ?? ''),
            'product_name' => (string)($product['product_name'] ?? '-'),
            'product_division_name' => (string)($product['product_division_name'] ?? '-'),
            'classification_name' => (string)($product['classification_name'] ?? '-'),
            'product_category_name' => (string)($product['product_category_name'] ?? '-'),
            'stock_mode' => (string)($product['stock_mode'] ?? 'AUTO'),
            'stock_mode_label' => (string)$stockModeMeta['label'],
            'stock_mode_class' => (string)$stockModeMeta['class'],
            'line_count' => (int)($summary['line_count'] ?? 0),
            'material_count' => (int)($summary['material_count'] ?? 0),
            'component_count' => (int)($summary['component_count'] ?? 0),
            'selling_price' => round((float)($summary['selling_price'] ?? 0), 2),
            'hpp_live' => round((float)($summary['total_hpp_live'] ?? 0), 6),
            'hpp_live_percent' => round((float)($summary['hpp_live_percent'] ?? 0), 4),
            'hpp_status' => (string)$hppMeta['status'],
            'hpp_status_label' => (string)$hppMeta['label'],
            'hpp_status_class' => (string)$hppMeta['class'],
            'estimated_profit' => round((float)($summary['selling_price'] ?? 0) - (float)($summary['total_hpp_live'] ?? 0), 2),
            'availability_qty_main' => round((float)$availability['available_main'], 4),
            'availability_qty_all' => round((float)$availability['available_all'], 4),
            'availability_qty_main_floor' => (int)floor(max(0.0, (float)$availability['available_main'])),
            'availability_qty_all_floor' => (int)floor(max(0.0, (float)$availability['available_all'])),
            'availability_status' => (string)$availability['status'],
            'availability_status_label' => (string)$availability['status_label'],
            'availability_status_class' => (string)$availability['status_class'],
            'blocking_line_label' => (string)$availability['blocking_line_label'],
            'blocking_line_detail' => (string)$availability['blocking_line_detail'],
            'main_basis_label' => (string)$availability['main_basis_label'],
        ];
    }

    private function buildProductAvailabilityLiveRow(array $product, int $outletId): array
    {
        $stockModeMeta = $this->productAvailabilityStockModeMeta((string)($product['stock_mode'] ?? 'AUTO'));
        $sellingPrice = round((float)($product['selling_price'] ?? 0), 2);
        $live = $outletId > 0
            ? $this->posavailabilityrebuildservice->resolve_live_availability($outletId, (int)($product['id'] ?? 0), [
                'trigger_context' => 'MASTER_PRODUCT_AVAILABILITY',
            ])
            : ['ok' => false, 'message' => 'Outlet belum dipilih.'];

        if (!($live['ok'] ?? false)) {
            $availability = [
                'status' => 'NO_RECIPE',
                'status_label' => 'Live Error',
                'status_class' => 'bg-label-secondary text-secondary',
                'blocking_line_label' => 'Perhitungan live gagal',
                'blocking_line_detail' => (string)($live['message'] ?? 'Gagal menghitung live availability.'),
                'main_basis_label' => 'Outlet wajib dipilih',
                'available_main' => 0.0,
                'available_all' => 0.0,
            ];
            $hppLive = 0.0;
        } else {
            $availability = $this->mapPosLiveToProductAvailability($live);
            $hppLive = round((float)($live['hpp_live_snapshot'] ?? 0), 6);
        }

        $hppMeta = $this->productAvailabilityHppMeta(
            $sellingPrice > 0 ? (($hppLive / $sellingPrice) * 100) : 0.0,
            $sellingPrice,
            $hppLive
        );

        return [
            'id' => (int)($product['id'] ?? 0),
            'product_code' => (string)($product['product_code'] ?? ''),
            'product_name' => (string)($product['product_name'] ?? '-'),
            'product_division_name' => (string)($product['product_division_name'] ?? '-'),
            'classification_name' => (string)($product['classification_name'] ?? '-'),
            'product_category_name' => (string)($product['product_category_name'] ?? '-'),
            'stock_mode' => (string)($product['stock_mode'] ?? 'AUTO'),
            'stock_mode_label' => (string)$stockModeMeta['label'],
            'stock_mode_class' => (string)$stockModeMeta['class'],
            'line_count' => count((array)($live['lines'] ?? [])),
            'material_count' => count(array_filter((array)($live['lines'] ?? []), static function (array $line): bool {
                return strtoupper((string)($line['line_type'] ?? '')) === 'MATERIAL';
            })),
            'component_count' => count(array_filter((array)($live['lines'] ?? []), static function (array $line): bool {
                return strtoupper((string)($line['line_type'] ?? '')) === 'COMPONENT';
            })),
            'selling_price' => $sellingPrice,
            'hpp_live' => $hppLive,
            'hpp_live_percent' => $sellingPrice > 0 ? round(($hppLive / $sellingPrice) * 100, 4) : 0.0,
            'hpp_status' => (string)$hppMeta['status'],
            'hpp_status_label' => (string)$hppMeta['label'],
            'hpp_status_class' => (string)$hppMeta['class'],
            'estimated_profit' => round($sellingPrice - $hppLive, 2),
            'availability_qty_main' => round((float)($availability['available_main'] ?? 0), 4),
            'availability_qty_all' => round((float)($availability['available_all'] ?? 0), 4),
            'availability_qty_main_floor' => (int)floor(max(0.0, (float)($availability['available_main'] ?? 0))),
            'availability_qty_all_floor' => (int)floor(max(0.0, (float)($availability['available_all'] ?? 0))),
            'availability_status' => (string)$availability['status'],
            'availability_status_label' => (string)$availability['status_label'],
            'availability_status_class' => (string)$availability['status_class'],
            'blocking_line_label' => (string)$availability['blocking_line_label'],
            'blocking_line_detail' => (string)$availability['blocking_line_detail'],
            'main_basis_label' => (string)$availability['main_basis_label'],
        ];
    }

    private function mapPosLiveToProductAvailability(array $live): array
    {
        $lines = (array)($live['lines'] ?? []);
        if (empty($lines)) {
            return [
                'available_main' => 0.0,
                'available_all' => 0.0,
                'status' => 'NO_RECIPE',
                'status_label' => 'Belum Ada Resep',
                'status_class' => 'bg-label-secondary text-secondary',
                'blocking_line_label' => 'Belum ada line resep.',
                'blocking_line_detail' => 'Tambahkan resep produk agar monitoring stok bisa dihitung live.',
                'main_basis_label' => 'Belum ada role MAIN',
            ];
        }

        $mainQty = (float)($live['estimated_available_qty_main'] ?? $live['estimated_available_qty'] ?? 0);
        $allQty = (float)($live['estimated_available_qty_all'] ?? $live['estimated_available_qty'] ?? 0);
        $mainMissing = (int)($live['main_missing_count'] ?? 0);
        $softMissing = (int)($live['optional_missing_count'] ?? 0);
        $mainLines = array_filter($lines, static function (array $line): bool {
            return strtoupper((string)($line['source_role'] ?? 'MAIN')) === 'MAIN';
        });
        $basisLabel = !empty($mainLines) ? 'Role MAIN' : 'Semua line';
        $bottleneck = trim((string)($live['bottleneck_name_snapshot'] ?? ''));
        $blockingLabel = $bottleneck !== '' ? $bottleneck : '-';
        $blockingDetail = 'Qty utama ' . number_format($mainQty, 4, ',', '.') . ' | Qty semua ' . number_format($allQty, 4, ',', '.');

        if ($mainMissing > 0) {
            if ($mainQty > 0.0001 || $allQty > 0.0001) {
                return [
                    'available_main' => $mainQty,
                    'available_all' => $allQty,
                    'status' => 'SHORT',
                    'status_label' => 'Terbatas',
                    'status_class' => 'bg-label-danger text-danger',
                    'blocking_line_label' => $blockingLabel,
                    'blocking_line_detail' => $blockingDetail,
                    'main_basis_label' => $basisLabel,
                ];
            }

            return [
                'available_main' => $mainQty,
                'available_all' => $allQty,
                'status' => 'EMPTY',
                'status_label' => 'Habis',
                'status_class' => 'bg-label-dark text-dark',
                'blocking_line_label' => $blockingLabel,
                'blocking_line_detail' => $blockingDetail,
                'main_basis_label' => $basisLabel,
            ];
        }

        if ($softMissing > 0) {
            return [
                'available_main' => $mainQty,
                'available_all' => $allQty,
                'status' => 'WARNING',
                'status_label' => 'Warning Non-Utama',
                'status_class' => 'bg-label-warning text-warning',
                'blocking_line_label' => $blockingLabel,
                'blocking_line_detail' => $blockingDetail,
                'main_basis_label' => $basisLabel,
            ];
        }

        return [
            'available_main' => $mainQty,
            'available_all' => $allQty,
            'status' => 'READY',
            'status_label' => 'Ready',
            'status_class' => 'bg-label-success text-success',
            'blocking_line_label' => $blockingLabel,
            'blocking_line_detail' => $blockingDetail,
            'main_basis_label' => $basisLabel,
        ];
    }

    private function productAvailabilityStockModeMeta(string $mode): array
    {
        $mode = strtoupper(trim($mode));

        if ($mode === 'MANUAL_AVAILABLE') {
            return ['label' => 'Manual Available', 'class' => 'bg-label-warning text-warning'];
        }
        if ($mode === 'MANUAL_OUT') {
            return ['label' => 'Manual Out', 'class' => 'bg-label-secondary text-secondary'];
        }

        return ['label' => 'Auto Resep', 'class' => 'bg-label-primary text-primary'];
    }

    private function productAvailabilityHppMeta(float $hppPercent, float $sellingPrice, float $hppLive): array
    {
        if ($sellingPrice <= 0 && $hppLive > 0) {
            return ['status' => 'HIGH', 'label' => 'Harga Jual 0', 'class' => 'bg-label-dark text-dark'];
        }
        if ($hppPercent > 100) {
            return ['status' => 'LOSS', 'label' => 'Rugi', 'class' => 'bg-label-danger text-danger'];
        }
        if ($hppPercent >= 60) {
            return ['status' => 'HIGH', 'label' => 'HPP Tinggi', 'class' => 'bg-label-warning text-warning'];
        }

        return ['status' => 'NORMAL', 'label' => 'Sehat', 'class' => 'bg-label-success text-success'];
    }

    private function calculateProductAvailabilityMetrics(array $rows): array
    {
        $allLines = [];
        $mainLines = [];
        $nonMainLines = [];

        foreach ($rows as $row) {
            $qty = (float)($row['qty'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $availableQty = (float)($row['available_qty'] ?? 0);
            $line = [
                'ratio' => $availableQty / $qty,
                'label' => $this->buildProductAvailabilityLineLabel($row),
                'detail' => $this->buildProductAvailabilityLineDetail($row, $availableQty, $qty),
            ];
            $allLines[] = $line;

            if (strtoupper((string)($row['ingredient_role'] ?? 'MAIN')) === 'MAIN') {
                $mainLines[] = $line;
            } else {
                $nonMainLines[] = $line;
            }
        }

        if (empty($allLines)) {
            return [
                'available_main' => 0.0,
                'available_all' => 0.0,
                'status' => 'NO_RECIPE',
                'status_label' => 'Belum Ada Resep',
                'status_class' => 'bg-label-secondary text-secondary',
                'blocking_line_label' => 'Belum ada line resep.',
                'blocking_line_detail' => 'Tambahkan resep produk agar monitoring stok bisa dihitung live.',
                'main_basis_label' => 'Belum ada role MAIN',
            ];
        }

        $limitingAll = $this->pickLowestAvailabilityLine($allLines);
        $limitingMain = !empty($mainLines) ? $this->pickLowestAvailabilityLine($mainLines) : $limitingAll;
        $limitingNonMain = !empty($nonMainLines) ? $this->pickLowestAvailabilityLine($nonMainLines) : null;

        $availableAll = max(0.0, (float)($limitingAll['ratio'] ?? 0));
        $availableMain = max(0.0, (float)($limitingMain['ratio'] ?? 0));
        $mainBasisLabel = !empty($mainLines) ? 'Role MAIN' : 'Semua line';

        $status = 'READY';
        $statusLabel = 'Ready';
        $statusClass = 'bg-label-success text-success';
        $blockingLine = $limitingAll;

        if ($availableAll >= 1) {
            $status = 'READY';
            $statusLabel = 'Ready';
            $statusClass = 'bg-label-success text-success';
            $blockingLine = $limitingAll;
        } elseif ($availableMain >= 1 && $limitingNonMain !== null) {
            $status = 'WARNING';
            $statusLabel = 'Warning Non-Utama';
            $statusClass = 'bg-label-warning text-warning';
            $blockingLine = $limitingNonMain;
        } elseif ($availableMain > 0 || $availableAll > 0) {
            $status = 'SHORT';
            $statusLabel = 'Terbatas';
            $statusClass = 'bg-label-danger text-danger';
            $blockingLine = $limitingMain;
        } else {
            $status = 'EMPTY';
            $statusLabel = 'Habis';
            $statusClass = 'bg-label-dark text-dark';
            $blockingLine = $limitingMain;
        }

        return [
            'available_main' => round($availableMain, 4),
            'available_all' => round($availableAll, 4),
            'status' => $status,
            'status_label' => $statusLabel,
            'status_class' => $statusClass,
            'blocking_line_label' => (string)($blockingLine['label'] ?? '-'),
            'blocking_line_detail' => (string)($blockingLine['detail'] ?? '-'),
            'main_basis_label' => $mainBasisLabel,
        ];
    }

    private function pickLowestAvailabilityLine(array $lines): array
    {
        $selected = null;
        foreach ($lines as $line) {
            if ($selected === null || (float)($line['ratio'] ?? 0) < (float)($selected['ratio'] ?? 0)) {
                $selected = $line;
            }
        }

        return $selected ?? [
            'ratio' => 0.0,
            'label' => '-',
            'detail' => '-',
        ];
    }

    private function buildProductAvailabilityLineLabel(array $row): string
    {
        $reference = trim((string)($row['reference_name'] ?? '-'));
        $division = trim((string)($row['resolved_source_division_name'] ?? '-'));
        $role = trim((string)($row['ingredient_role_label'] ?? 'Bahan Utama'));

        return $reference . ' • ' . $division . ' • ' . $role;
    }

    private function buildProductAvailabilityLineDetail(array $row, float $availableQty, float $requiredQty): string
    {
        $uom = trim((string)($row['uom_name'] ?? ''));

        return 'Butuh ' . number_format($requiredQty, 4, ',', '.')
            . ($uom !== '' ? ' ' . $uom : '')
            . ' | stok ' . number_format($availableQty, 4, ',', '.')
            . ($uom !== '' ? ' ' . $uom : '');
    }

    private function loadProductRecipeParent(int $productId): ?array
    {
        return $this->db
            ->select('p.*, pd.name AS product_division_name, pd.code AS product_division_code, od.name AS default_operational_division_name')
            ->from('mst_product p')
            ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
            ->join('mst_operational_division od', 'od.id = p.default_operational_division_id', 'left')
            ->where('p.id', $productId)
            ->get()
            ->row_array();
    }

    private function resolveProductRecipeInput(array $product, string $lineType, int $materialItemId, int $componentId, ?int $manualSourceDivisionId = null, ?array $row = null): array
    {
        $defaultDivision = $this->resolveProductRecipeDefaultDivision($product);
        $resolvedManualDivisionId = $this->resolveProductRecipeSourceDivisionId($manualSourceDivisionId);

        if ($lineType === 'MATERIAL') {
            $item = $this->db
                ->select('i.id, i.item_name, i.material_id, m.material_name, i.content_uom_id AS uom_id, u.name AS uom_name')
                ->from('mst_item i')
                ->join('mst_material m', 'm.id = i.material_id', 'left')
                ->join('mst_uom u', 'u.id = i.content_uom_id', 'left')
                ->where('i.id', $materialItemId)
                ->where('i.is_material', 1)
                ->get()
                ->row_array();
            if (!$item) {
                return ['ok' => false, 'message' => 'Item bahan baku tidak ditemukan atau tidak valid.'];
            }
            if ((int)($item['uom_id'] ?? 0) <= 0) {
                return ['ok' => false, 'message' => 'UOM item bahan baku belum terpasang.'];
            }

            return [
                'ok' => true,
                'material_item_id' => (int)$item['id'],
                'material_id' => (int)($item['material_id'] ?? 0),
                'material_name' => (string)($item['material_name'] ?? $item['item_name'] ?? 'Material'),
                'component_id' => null,
                'source_division_id' => $resolvedManualDivisionId > 0
                    ? $resolvedManualDivisionId
                    : ($defaultDivision['id'] > 0 ? $defaultDivision['id'] : null),
                'uom_id' => (int)$item['uom_id'],
            ];
        }

        $component = $this->db
            ->select('c.id, c.product_division_id, c.operational_division_id, c.uom_id')
            ->from('mst_component c')
            ->where('c.id', $componentId)
            ->get()
            ->row_array();
        if (!$component) {
            return ['ok' => false, 'message' => 'Component tidak ditemukan.'];
        }
        if ((int)($component['uom_id'] ?? 0) <= 0) {
            return ['ok' => false, 'message' => 'UOM component belum terpasang.'];
        }

        $sourceDivisionId = $resolvedManualDivisionId > 0
            ? $resolvedManualDivisionId
            : (int)($component['operational_division_id'] ?? 0);
        if ($sourceDivisionId <= 0) {
            $sourceDivisionId = $defaultDivision['id'];
        }

        return [
            'ok' => true,
            'material_item_id' => null,
            'material_id' => null,
            'material_name' => null,
            'component_id' => (int)$component['id'],
            'source_division_id' => $sourceDivisionId > 0 ? $sourceDivisionId : null,
            'uom_id' => (int)$component['uom_id'],
        ];
    }

    private function productRecipeMaterialDuplicateKey(array $resolved, string $ingredientRole): ?string
    {
        $materialId = (int)($resolved['material_id'] ?? 0);
        if ($materialId <= 0) {
            return null;
        }

        $roleKey = $this->db->field_exists('ingredient_role', 'mst_product_recipe')
            ? $this->normalizeProductRecipeIngredientRole($ingredientRole)
            : '-';

        return implode('|', [
            $materialId,
            (int)($resolved['source_division_id'] ?? 0),
            $roleKey,
        ]);
    }

    private function validateProductRecipeMaterialDuplicate(int $productId, array $resolved, string $ingredientRole, int $excludeId = 0): array
    {
        $materialId = (int)($resolved['material_id'] ?? 0);
        if ($materialId <= 0) {
            return ['ok' => true];
        }

        $sourceDivisionId = (int)($resolved['source_division_id'] ?? 0);
        $ingredientRole = $this->normalizeProductRecipeIngredientRole($ingredientRole);
        $materialName = trim((string)($resolved['material_name'] ?? ''));
        if ($materialName === '') {
            $materialName = 'Material';
        }

        $this->db->select('r.id')
            ->from('mst_product_recipe r')
            ->join('mst_item i', 'i.id = r.material_item_id', 'left')
            ->where('r.product_id', $productId)
            ->where('r.line_type', 'MATERIAL')
            ->where('i.material_id', $materialId)
            ->where('COALESCE(r.source_division_id, 0) = ' . $sourceDivisionId, null, false)
            ->limit(1);
        if ($excludeId > 0) {
            $this->db->where('r.id <>', $excludeId);
        }
        if ($this->db->field_exists('ingredient_role', 'mst_product_recipe')) {
            if ($ingredientRole === 'MAIN') {
                $this->db->group_start()
                    ->where('r.ingredient_role', 'MAIN')
                    ->or_where('r.ingredient_role IS NULL', null, false)
                    ->group_end();
            } else {
                $this->db->where('r.ingredient_role', $ingredientRole);
            }
        }

        $exists = $this->db->get()->row_array();
        if ($exists) {
            return [
                'ok' => false,
                'message' => sprintf('Material %s sudah ada pada resep untuk role/divisi yang sama. Gabungkan jadi satu line.', $materialName),
            ];
        }

        return ['ok' => true];
    }

    private function resolveProductRecipeDefaultDivision(array $product): array
    {
        $fallback = [
            'id' => (int)($product['default_operational_division_id'] ?? 0),
            'name' => (string)($product['default_operational_division_name'] ?? ''),
        ];

        $divisionKey = strtoupper(trim((string)($product['product_division_code'] ?? '')));
        if ($divisionKey === '') {
            $divisionKey = strtoupper(trim((string)($product['product_division_name'] ?? '')));
        }

        $preferredDivisionName = '';
        if ($divisionKey === 'BEVERAGE') {
            $preferredDivisionName = 'BAR';
        } elseif ($divisionKey === 'FOOD') {
            $preferredDivisionName = 'KITCHEN';
        }

        if ($preferredDivisionName !== '') {
            $division = $this->lookupOperationalDivisionByName($preferredDivisionName);
            if ($division !== null) {
                return $division;
            }
        }

        return $fallback;
    }

    private function resolveProductRecipeSourceDivisionId(?int $divisionId): int
    {
        $divisionId = (int)$divisionId;
        if ($divisionId <= 0) {
            return 0;
        }

        $row = $this->db
            ->select('id')
            ->from('mst_operational_division')
            ->where('id', $divisionId)
            ->limit(1)
            ->get()
            ->row_array();

        return $row ? (int)$row['id'] : 0;
    }

    private function lookupOperationalDivisionByName(string $name): ?array
    {
        static $cache = [];

        $key = strtoupper(trim($name));
        if ($key === '') {
            return null;
        }
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $row = $this->db
            ->select('id, name')
            ->from('mst_operational_division')
            ->where('UPPER(name)', $key)
            ->limit(1)
            ->get()
            ->row_array();

        $cache[$key] = $row ? [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
        ] : null;

        return $cache[$key];
    }

    private function decorateProductRecipeRows(array $product, array $rows): array
    {
        $defaultDivision = $this->resolveProductRecipeDefaultDivision($product);
        foreach ($rows as &$row) {
            $lineType = strtoupper(trim((string)($row['line_type'] ?? 'MATERIAL')));
            $row['line_type_label'] = $lineType === 'MATERIAL' ? 'Bahan Baku' : 'Component';
            $row['ingredient_role'] = $this->normalizeProductRecipeIngredientRole($row['ingredient_role'] ?? 'MAIN');
            $row['ingredient_role_label'] = [
                'MAIN' => 'Bahan Utama',
                'SUPPORT' => 'Pendukung',
                'GARNISH' => 'Garnish',
                'OPTIONAL' => 'Opsional',
                'SAUCE' => 'Saus',
                'TOPPING' => 'Topping',
                'OTHER' => 'Lainnya',
            ][$row['ingredient_role']] ?? 'Bahan Utama';
            $row['reference_name'] = $lineType === 'MATERIAL'
                ? (string)($row['item_name'] ?? '-')
                : (string)($row['component_name'] ?? '-');

            if ($lineType === 'COMPONENT') {
                $resolvedId = (int)($row['component_operational_division_id'] ?? 0);
                $resolvedName = trim((string)($row['component_operational_division_name'] ?? ''));
                if ($resolvedId <= 0) {
                    $resolvedId = (int)($row['source_division_id'] ?? 0);
                    $resolvedName = trim((string)($row['source_division_name'] ?? ''));
                }
                if ((int)($row['source_division_id'] ?? 0) > 0 && (int)($row['source_division_id'] ?? 0) !== $resolvedId) {
                    $resolvedId = (int)($row['source_division_id'] ?? 0);
                    $resolvedName = trim((string)($row['source_division_name'] ?? ''));
                }
                $row['resolved_source_division_id'] = $resolvedId;
                $row['resolved_source_division_name'] = $resolvedName !== '' ? $resolvedName : '-';
                $row['source_division_hint'] = 'Mengikuti divisi operasional component.';
            } else {
                $resolvedId = (int)($defaultDivision['id'] ?? 0);
                $resolvedName = trim((string)($defaultDivision['name'] ?? ''));
                if ($resolvedId <= 0) {
                    $resolvedId = (int)($row['source_division_id'] ?? 0);
                    $resolvedName = trim((string)($row['source_division_name'] ?? ''));
                }
                if ((int)($row['source_division_id'] ?? 0) > 0) {
                    $resolvedId = (int)($row['source_division_id'] ?? 0);
                    $resolvedName = trim((string)($row['source_division_name'] ?? ''));
                }
                $row['resolved_source_division_id'] = $resolvedId;
                $row['resolved_source_division_name'] = $resolvedName !== '' ? $resolvedName : '-';
                $row['source_division_hint'] = 'Default dari divisi produk induk.';
            }
        }
        unset($row);

        return $rows;
    }

    private function loadProductRecipeData(array $product): array
    {
        $this->db->select('r.*, i.item_name, i.material_id, c.component_name, c.hpp_standard AS component_hpp_standard, u.name AS uom_name, d.name AS source_division_name, c.operational_division_id AS component_operational_division_id, cod.name AS component_operational_division_name');
        $this->db->from('mst_product_recipe r');
        $this->db->join('mst_item i', 'i.id = r.material_item_id', 'left');
        $this->db->join('mst_component c', 'c.id = r.component_id', 'left');
        $this->db->join('mst_uom u', 'u.id = r.uom_id', 'left');
        $this->db->join('mst_operational_division d', 'd.id = r.source_division_id', 'left');
        $this->db->join('mst_operational_division cod', 'cod.id = c.operational_division_id', 'left');
        $this->db->where('r.product_id', (int)$product['id']);
        $this->db->order_by('r.sort_order ASC, r.id ASC');
        $rows = $this->db->get()->result_array();
        $rows = $this->decorateProductRecipeRows($product, $rows);
        $rows = $this->enrichProductRecipeCostRows($product, $rows);

        return [
            'rows' => $rows,
            'summary' => $this->buildProductRecipeSummary($product, $rows),
        ];
    }

    private function enrichProductRecipeCostRows(array $product, array $rows): array
    {
        $sellingPrice = (float)($product['selling_price'] ?? 0);
        foreach ($rows as &$row) {
            $divisionId = (int)($row['resolved_source_division_id'] ?? 0);
            $cost = $this->resolveProductRecipeLineCost($row, $divisionId);
            $qty = (float)($row['qty'] ?? 0);
            $row['standard_unit_cost'] = (float)$cost['standard_unit_cost'];
            $row['live_unit_cost'] = (float)$cost['live_unit_cost'];
            $row['line_standard_total'] = round($qty * (float)$cost['standard_unit_cost'], 6);
            $row['line_live_total'] = round($qty * (float)$cost['live_unit_cost'], 6);
            $row['available_qty'] = (float)$cost['available_qty'];
            $row['live_cost_source'] = (string)$cost['live_cost_source'];
            $row['live_cost_source_label'] = (string)$cost['live_cost_source_label'];
            $row['cost_reference_label'] = (string)$cost['cost_reference_label'];
            $row['line_hpp_percent'] = $sellingPrice > 0
                ? round(((float)$row['line_live_total'] / $sellingPrice) * 100, 4)
                : 0.0;
        }
        unset($row);

        return $rows;
    }

    private function normalizeProductRecipeBulkLines(array $product, array $lines): array
    {
        $normalized = [];
        $lineNo = 1;
        $seenMaterialKeys = [];

        foreach ($lines as $index => $line) {
            if (!is_array($line)) {
                continue;
            }

            $lineType = strtoupper(trim((string)($line['line_type'] ?? '')));
            $qty = round((float)($line['qty'] ?? 0), 4);
            if (!in_array($lineType, ['MATERIAL', 'COMPONENT'], true) || $qty <= 0) {
                continue;
            }

            $materialItemId = $lineType === 'MATERIAL' ? (int)($line['material_item_id'] ?? 0) : 0;
            $componentId = $lineType === 'COMPONENT' ? (int)($line['component_id'] ?? 0) : 0;
            $resolved = $this->resolveProductRecipeInput(
                $product,
                $lineType,
                $materialItemId,
                $componentId,
                !empty($line['source_division_id']) ? (int)$line['source_division_id'] : null
            );
            if (empty($resolved['ok'])) {
                return [
                    'ok' => false,
                    'message' => 'Baris #' . ($index + 1) . ': ' . (string)($resolved['message'] ?? 'Data resep produk tidak valid.'),
                ];
            }

            $notes = trim((string)($line['notes'] ?? ''));
            $ingredientRole = $this->normalizeProductRecipeIngredientRole($line['ingredient_role'] ?? 'MAIN');
            if ($lineType === 'MATERIAL') {
                $duplicateKey = $this->productRecipeMaterialDuplicateKey($resolved, $ingredientRole);
                if ($duplicateKey !== null) {
                    $materialName = trim((string)($resolved['material_name'] ?? ''));
                    if ($materialName === '') {
                        $materialName = 'Material';
                    }
                    if (isset($seenMaterialKeys[$duplicateKey])) {
                        return [
                            'ok' => false,
                            'message' => 'Baris #' . ($index + 1) . ': Material ' . $materialName . ' dobel pada role/divisi yang sama. Gabungkan jadi satu line.',
                        ];
                    }
                    $seenMaterialKeys[$duplicateKey] = true;
                }
            }
            $row = [
                'product_id' => (int)$product['id'],
                'line_no' => $lineNo,
                'line_type' => $lineType,
                'material_item_id' => $resolved['material_item_id'],
                'component_id' => $resolved['component_id'],
                'source_division_id' => $resolved['source_division_id'],
                'qty' => $qty,
                'uom_id' => $resolved['uom_id'],
                'notes' => $notes !== '' ? $notes : null,
                'sort_order' => (int)($line['sort_order'] ?? ($lineNo * 10)),
            ];
            if ($this->db->field_exists('ingredient_role', 'mst_product_recipe')) {
                $row['ingredient_role'] = $ingredientRole;
            }
            $normalized[] = $row;
            $lineNo++;
        }

        if (count($normalized) <= 0) {
            return ['ok' => false, 'message' => 'Minimal harus ada 1 line resep produk yang valid.'];
        }

        return ['ok' => true, 'rows' => $normalized];
    }

    private function resolveProductRecipeLineCost(array $row, int $divisionId): array
    {
        $lineType = strtoupper(trim((string)($row['line_type'] ?? 'MATERIAL')));
        if ($lineType === 'COMPONENT') {
            return $this->resolveProductRecipeComponentCost((int)($row['component_id'] ?? 0), $divisionId);
        }

        return $this->resolveProductRecipeMaterialCost((int)($row['material_id'] ?? 0), $divisionId);
    }

    private function resolveProductRecipeMaterialCost(int $materialId, int $divisionId): array
    {
        $cacheKey = $divisionId . '|' . $materialId;
        if (isset($this->productRecipeMaterialCostCache[$cacheKey])) {
            return $this->productRecipeMaterialCostCache[$cacheKey];
        }

        $material = $this->db->select('id, material_name, hpp_standard')
            ->from('mst_material')
            ->where('id', $materialId)
            ->limit(1)
            ->get()
            ->row_array();

        $standard = (float)($material['hpp_standard'] ?? 0);
        $live = 0.0;
        $availableQty = 0.0;
        $hasStockLiveCost = false;
        $lotLive = $this->resolveProductRecipeMaterialLotCost($materialId, $divisionId);
        if (($lotLive['unit_cost'] ?? 0) > 0) {
            $live = (float)($lotLive['unit_cost'] ?? 0);
            $availableQty = (float)($lotLive['qty_balance'] ?? 0);
            $hasStockLiveCost = true;
        }
        if ($divisionId > 0 && $this->db->table_exists('inv_division_monthly_stock')) {
            $targetMonth = date('Y-m-01');
            $latestMonthSubquery = $this->db
                ->select('division_id, destination_type, identity_key, MAX(month_key) AS month_key', false)
                ->from('inv_division_monthly_stock')
                ->where('month_key <=', $targetMonth)
                ->group_by(['division_id', 'destination_type', 'identity_key'])
                ->get_compiled_select();
            $liveRow = $this->db->select("
                    COALESCE(
                        CASE
                            WHEN ABS(SUM(COALESCE(s.closing_qty_content, 0))) > 0.000001
                                THEN SUM(COALESCE(s.closing_qty_content, 0) * COALESCE(s.avg_cost_per_content, 0)) / SUM(COALESCE(s.closing_qty_content, 0))
                            ELSE MAX(COALESCE(s.avg_cost_per_content, 0))
                        END,
                        0
                    ) AS avg_cost_per_content
                ", false)
                ->from('inv_division_monthly_stock s')
                ->join('mst_item mi', 'mi.id = s.item_id', 'left')
                ->join('(' . $latestMonthSubquery . ') lm', 'lm.division_id = s.division_id AND lm.destination_type = s.destination_type AND lm.identity_key = s.identity_key AND lm.month_key = s.month_key', 'inner', false)
                ->where('s.division_id', $divisionId)
                ->group_start()
                    ->where('s.material_id', $materialId)
                    ->or_where('mi.material_id', $materialId)
                ->group_end()
                ->get()
                ->row_array();
            $qtyRow = $this->db->select('SUM(COALESCE(s.closing_qty_content,0)) AS qty_balance', false)
                ->from('inv_division_monthly_stock s')
                ->join('mst_item mi', 'mi.id = s.item_id', 'left')
                ->join('(' . $latestMonthSubquery . ') lm', 'lm.division_id = s.division_id AND lm.destination_type = s.destination_type AND lm.identity_key = s.identity_key AND lm.month_key = s.month_key', 'inner', false)
                ->where('s.division_id', $divisionId)
                ->group_start()
                    ->where('s.material_id', $materialId)
                    ->or_where('mi.material_id', $materialId)
                ->group_end()
                ->get()
                ->row_array();
            if (!$hasStockLiveCost) {
                $live = (float)($liveRow['avg_cost_per_content'] ?? 0);
                $hasStockLiveCost = $live > 0;
                $availableQty = (float)($qtyRow['qty_balance'] ?? 0);
            }
        }
        if ($live <= 0) {
            $live = $standard;
        }

        $result = [
            'standard_unit_cost' => round($standard, 6),
            'live_unit_cost' => round($live, 6),
            'available_qty' => round($availableQty, 4),
            'live_cost_source' => $hasStockLiveCost ? (($lotLive['unit_cost'] ?? 0) > 0 ? 'LOT_FIFO_ACTIVE' : 'STOCK_DIVISION') : 'FALLBACK_STANDARD',
            'live_cost_source_label' => $hasStockLiveCost ? (($lotLive['unit_cost'] ?? 0) > 0 ? 'Lot Aktif FIFO' : 'Stok Divisi') : 'Fallback Std',
            'cost_reference_label' => 'Bahan baku',
        ];
        $this->productRecipeMaterialCostCache[$cacheKey] = $result;

        return $result;
    }

    private function resolveProductRecipeComponentCost(int $componentId, int $divisionId): array
    {
        $cacheKey = $divisionId . '|' . $componentId;
        if (isset($this->productRecipeComponentCostCache[$cacheKey])) {
            return $this->productRecipeComponentCostCache[$cacheKey];
        }

        $component = $this->db->select('id, component_name, hpp_standard')
            ->from('mst_component')
            ->where('id', $componentId)
            ->limit(1)
            ->get()
            ->row_array();

        $standard = (float)($component['hpp_standard'] ?? 0);
        $live = 0.0;
        $availableQty = 0.0;
        $hasStockLiveCost = false;
        $lotLive = $this->resolveProductRecipeComponentLotCost($componentId, $divisionId);
        if (($lotLive['unit_cost'] ?? 0) > 0) {
            $live = (float)($lotLive['unit_cost'] ?? 0);
            $availableQty = (float)($lotLive['qty_balance'] ?? 0);
            $hasStockLiveCost = true;
        }
        if ($this->db->table_exists('inv_component_monthly_stock')) {
            $targetMonth = date('Y-m-01');
            $latestMonthSubquery = $this->db
                ->select('location_type, division_id, component_id, uom_id, MAX(month_key) AS month_key', false)
                ->from('inv_component_monthly_stock')
                ->where('month_key <=', $targetMonth)
                ->group_by(['location_type', 'division_id', 'component_id', 'uom_id'])
                ->get_compiled_select();
            $this->db->select("
                    COALESCE(
                        CASE
                            WHEN ABS(SUM(COALESCE(s.closing_qty, 0))) > 0.000001
                                THEN SUM(COALESCE(s.closing_qty, 0) * COALESCE(s.avg_cost, 0)) / SUM(COALESCE(s.closing_qty, 0))
                            ELSE MAX(COALESCE(s.avg_cost, 0))
                        END,
                        0
                    ) AS avg_cost
                ", false)
                ->from('inv_component_monthly_stock s')
                ->join('(' . $latestMonthSubquery . ') lm', 'lm.location_type = s.location_type AND lm.division_id <=> s.division_id AND lm.component_id = s.component_id AND lm.uom_id = s.uom_id AND lm.month_key = s.month_key', 'inner', false)
                ->where('s.component_id', $componentId);
            if ($divisionId > 0) {
                $this->db->where('s.division_id', $divisionId);
            }
            $liveRow = $this->db->get()->row_array();

            $this->db->select('SUM(COALESCE(s.closing_qty,0)) AS qty_balance', false)
                ->from('inv_component_monthly_stock s')
                ->join('(' . $latestMonthSubquery . ') lm', 'lm.location_type = s.location_type AND lm.division_id <=> s.division_id AND lm.component_id = s.component_id AND lm.uom_id = s.uom_id AND lm.month_key = s.month_key', 'inner', false)
                ->where('s.component_id', $componentId);
            if ($divisionId > 0) {
                $this->db->where('s.division_id', $divisionId);
            }
            $qtyRow = $this->db->get()->row_array();

            if (!$hasStockLiveCost) {
                $live = (float)($liveRow['avg_cost'] ?? 0);
                $hasStockLiveCost = $live > 0;
                $availableQty = (float)($qtyRow['qty_balance'] ?? 0);
            }
        }
        if ($live <= 0) {
            $live = $standard;
        }

        $result = [
            'standard_unit_cost' => round($standard, 6),
            'live_unit_cost' => round($live, 6),
            'available_qty' => round($availableQty, 4),
            'live_cost_source' => $hasStockLiveCost ? (($lotLive['unit_cost'] ?? 0) > 0 ? 'LOT_FIFO_ACTIVE' : 'STOCK_COMPONENT') : 'FALLBACK_STANDARD',
            'live_cost_source_label' => $hasStockLiveCost ? (($lotLive['unit_cost'] ?? 0) > 0 ? 'Lot Aktif FIFO' : 'Stok Component') : 'Fallback Std',
            'cost_reference_label' => 'Component',
        ];
        $this->productRecipeComponentCostCache[$cacheKey] = $result;

        return $result;
    }

    private function resolveProductRecipeMaterialLotCost(int $materialId, int $divisionId): array
    {
        if ($materialId <= 0 || $divisionId <= 0 || !$this->db->table_exists('inv_material_fifo_lot')) {
            return ['unit_cost' => 0.0, 'qty_balance' => 0.0];
        }

        $preferredDestination = $this->regularMaterialDestinationForDivision($divisionId);
        if ($preferredDestination !== null) {
            $frontPreferred = $this->db->query(
                "SELECT ROUND(COALESCE(unit_cost, 0), 6) AS unit_cost
                 FROM inv_material_fifo_lot
                 WHERE location_scope = 'DIVISION'
                   AND division_id = ?
                   AND destination_type = ?
                   AND COALESCE(material_id, 0) = ?
                   AND qty_balance > 0
                 ORDER BY receipt_date ASC, id ASC
                 LIMIT 1",
                [$divisionId, $preferredDestination, $materialId]
            )->row_array();
            if (!empty($frontPreferred)) {
                $qtyPreferred = $this->db->query(
                    "SELECT ROUND(COALESCE(SUM(qty_balance), 0), 4) AS qty_balance
                     FROM inv_material_fifo_lot
                     WHERE location_scope = 'DIVISION'
                       AND division_id = ?
                       AND destination_type = ?
                       AND COALESCE(material_id, 0) = ?
                       AND qty_balance > 0",
                    [$divisionId, $preferredDestination, $materialId]
                )->row_array();
                return [
                    'unit_cost' => (float)($frontPreferred['unit_cost'] ?? 0),
                    'qty_balance' => (float)($qtyPreferred['qty_balance'] ?? 0),
                ];
            }
        }

        $front = $this->db->query(
            "SELECT ROUND(COALESCE(unit_cost, 0), 6) AS unit_cost
             FROM inv_material_fifo_lot
             WHERE location_scope = 'DIVISION'
               AND division_id = ?
               AND COALESCE(material_id, 0) = ?
               AND qty_balance > 0
             ORDER BY receipt_date ASC, id ASC
             LIMIT 1",
            [$divisionId, $materialId]
        )->row_array();
        if (empty($front)) {
            return ['unit_cost' => 0.0, 'qty_balance' => 0.0];
        }
        $qty = $this->db->query(
            "SELECT ROUND(COALESCE(SUM(qty_balance), 0), 4) AS qty_balance
             FROM inv_material_fifo_lot
             WHERE location_scope = 'DIVISION'
               AND division_id = ?
               AND COALESCE(material_id, 0) = ?
               AND qty_balance > 0",
            [$divisionId, $materialId]
        )->row_array();
        return [
            'unit_cost' => (float)($front['unit_cost'] ?? 0),
            'qty_balance' => (float)($qty['qty_balance'] ?? 0),
        ];
    }

    private function resolveProductRecipeComponentLotCost(int $componentId, int $divisionId): array
    {
        if ($componentId <= 0 || !$this->db->table_exists('inv_component_lot')) {
            return ['unit_cost' => 0.0, 'qty_balance' => 0.0];
        }

        $preferredLocation = $this->regularComponentLocationForDivision($divisionId);
        if ($preferredLocation !== null) {
            $frontPreferred = $this->db->query(
                "SELECT ROUND(COALESCE(unit_cost, 0), 6) AS unit_cost
                 FROM inv_component_lot
                 WHERE component_id = ?
                   AND division_id = ?
                   AND location_type = ?
                   AND qty_balance > 0
                 ORDER BY receipt_date ASC, id ASC
                 LIMIT 1",
                [$componentId, $divisionId, $preferredLocation]
            )->row_array();
            if (!empty($frontPreferred)) {
                $qtyPreferred = $this->db->query(
                    "SELECT ROUND(COALESCE(SUM(qty_balance), 0), 4) AS qty_balance
                     FROM inv_component_lot
                     WHERE component_id = ?
                       AND division_id = ?
                       AND location_type = ?
                       AND qty_balance > 0",
                    [$componentId, $divisionId, $preferredLocation]
                )->row_array();
                return [
                    'unit_cost' => (float)($frontPreferred['unit_cost'] ?? 0),
                    'qty_balance' => (float)($qtyPreferred['qty_balance'] ?? 0),
                ];
            }
        }

        $front = $this->db->query(
            "SELECT ROUND(COALESCE(unit_cost, 0), 6) AS unit_cost
             FROM inv_component_lot
             WHERE component_id = ?
               AND division_id = ?
               AND qty_balance > 0
             ORDER BY receipt_date ASC, id ASC
             LIMIT 1",
            [$componentId, $divisionId]
        )->row_array();
        if (empty($front)) {
            return ['unit_cost' => 0.0, 'qty_balance' => 0.0];
        }
        $qty = $this->db->query(
            "SELECT ROUND(COALESCE(SUM(qty_balance), 0), 4) AS qty_balance
             FROM inv_component_lot
             WHERE component_id = ?
               AND division_id = ?
               AND qty_balance > 0",
            [$componentId, $divisionId]
        )->row_array();
        return [
            'unit_cost' => (float)($front['unit_cost'] ?? 0),
            'qty_balance' => (float)($qty['qty_balance'] ?? 0),
        ];
    }

    private function productRecipeVariableCostContext(array $product): array
    {
        $mode = strtoupper(trim((string)($product['variable_cost_mode'] ?? 'DEFAULT')));
        $percent = (float)($product['variable_cost_percent'] ?? 0);
        if ($mode === 'NONE') {
            $effectivePercent = 0.0;
        } elseif ($mode === 'CUSTOM') {
            $effectivePercent = max(0.0, $percent);
        } else {
            $effectivePercent = $this->Master_model->get_variable_cost_default_percent('PRODUCT', 20.0);
        }

        return [
            'mode' => $mode,
            'stored_percent' => round($percent, 4),
            'effective_percent' => round($effectivePercent, 4),
            'selling_price' => round((float)($product['selling_price'] ?? 0), 2),
            'hpp_standard' => round((float)($product['hpp_standard'] ?? 0), 6),
            'hpp_live_cache' => round((float)($product['hpp_live_cache'] ?? 0), 6),
        ];
    }

    private function buildProductRecipeSummary(array $product, array $rows): array
    {
        $variableCost = $this->productRecipeVariableCostContext($product);
        $summary = [
            'line_count' => count($rows),
            'material_count' => 0,
            'component_count' => 0,
            'source_division_count' => 0,
            'direct_cost_standard' => 0.0,
            'direct_cost_live' => 0.0,
            'variable_cost_mode' => $variableCost['mode'],
            'variable_cost_percent' => $variableCost['effective_percent'],
            'selling_price' => $variableCost['selling_price'],
            'hpp_master_standard' => $variableCost['hpp_standard'],
            'hpp_live_cache' => $variableCost['hpp_live_cache'],
        ];
        $divisionMap = [];

        foreach ($rows as $row) {
            $lineType = strtoupper(trim((string)($row['line_type'] ?? 'MATERIAL')));
            if ($lineType === 'COMPONENT') {
                $summary['component_count']++;
            } else {
                $summary['material_count']++;
            }

            $divisionId = (int)($row['resolved_source_division_id'] ?? 0);
            if ($divisionId > 0) {
                $divisionMap[$divisionId] = true;
            }

            $summary['direct_cost_standard'] += (float)($row['line_standard_total'] ?? 0);
            $summary['direct_cost_live'] += (float)($row['line_live_total'] ?? 0);
        }

        $summary['source_division_count'] = count($divisionMap);
        $summary['direct_cost_standard'] = round($summary['direct_cost_standard'], 6);
        $summary['direct_cost_live'] = round($summary['direct_cost_live'], 6);
        $summary['variable_cost_standard'] = round($summary['direct_cost_standard'] * ((float)$summary['variable_cost_percent'] / 100), 6);
        $summary['variable_cost_live'] = round($summary['direct_cost_live'] * ((float)$summary['variable_cost_percent'] / 100), 6);
        $summary['total_hpp_standard'] = round($summary['direct_cost_standard'] + $summary['variable_cost_standard'], 6);
        $summary['total_hpp_live'] = round($summary['direct_cost_live'] + $summary['variable_cost_live'], 6);
        $summary['hpp_standard_percent'] = (float)$summary['selling_price'] > 0
            ? round(($summary['total_hpp_standard'] / (float)$summary['selling_price']) * 100, 4)
            : 0.0;
        $summary['hpp_live_percent'] = (float)$summary['selling_price'] > 0
            ? round(($summary['total_hpp_live'] / (float)$summary['selling_price']) * 100, 4)
            : 0.0;

        return $summary;
    }

    private function loadProductBundle(int $bundleId): ?array
    {
        return $this->db
            ->select('b.*, pd.name AS product_division_name')
            ->from('pos_product_bundle b')
            ->join('mst_product_division pd', 'pd.id = b.product_division_id', 'left')
            ->where('b.id', $bundleId)
            ->get()
            ->row_array() ?: null;
    }

    private function loadProductBundleLines(int $bundleId): array
    {
        $select = [
            'bl.*',
            'p.product_code',
            'p.product_name',
            'p.selling_price AS product_selling_price',
            'p.hpp_standard',
            'p.hpp_live_cache',
            'p.variable_cost_mode',
            'p.variable_cost_percent',
            'pd.name AS product_division_name',
            'u.code AS uom_code',
            'u.name AS uom_name',
        ];
        if ($this->db->field_exists('show_pos', 'mst_product')) {
            $select[] = 'p.show_pos';
        }
        if ($this->db->field_exists('show_in_cashier', 'mst_product')) {
            $select[] = 'p.show_in_cashier';
        }

        return $this->db
            ->select(implode(",\n                ", $select))
            ->from('pos_product_bundle_line bl')
            ->join('mst_product p', 'p.id = bl.product_id', 'inner')
            ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
            ->join('mst_uom u', 'u.id = p.uom_id', 'left')
            ->where('bl.bundle_id', $bundleId)
            ->order_by('bl.sort_order', 'ASC')
            ->order_by('bl.id', 'ASC')
            ->get()
            ->result_array();
    }

    private function buildProductBundleSummary(array $bundle, array $rows): array
    {
        $summary = [
            'line_count' => count($rows),
            'line_qty_total' => 0.0,
            'line_value_total' => 0.0,
            'bundle_price' => round((float)($bundle['selling_price'] ?? 0), 2),
            'bundle_gap' => 0.0,
        ];

        foreach ($rows as $row) {
            $qty = (float)($row['qty'] ?? 0);
            $unitPrice = $row['unit_price_override'] !== null
                ? (float)$row['unit_price_override']
                : (float)($row['product_selling_price'] ?? 0);
            $summary['line_qty_total'] += $qty;
            $summary['line_value_total'] += ($qty * $unitPrice);
        }

        $summary['line_qty_total'] = round($summary['line_qty_total'], 4);
        $summary['line_value_total'] = round($summary['line_value_total'], 2);
        $summary['bundle_gap'] = round($summary['bundle_price'] - $summary['line_value_total'], 2);

        return $summary;
    }

    private function buildProductBundlePricingPreview(array $bundle, array $rows): array
    {
        $lines = [];
        foreach ($rows as $row) {
            $costPreview = $this->resolveBundleProductCostPreview($row);
            $lines[] = [
                'product_id' => (int)($row['product_id'] ?? 0),
                'product_name' => (string)($row['product_name'] ?? ''),
                'qty' => (float)($row['qty'] ?? 0),
                'base_unit_price' => (float)($row['product_selling_price'] ?? 0),
                'override_unit_price' => $row['unit_price_override'] !== null ? (float)$row['unit_price_override'] : null,
                'uom_code' => (string)($row['uom_code'] ?? $row['uom_name'] ?? ''),
                'division_name' => (string)($row['product_division_name'] ?? ''),
                'hpp_live_unit' => (float)$costPreview['hpp_live_unit'],
                'hpp_standard_unit' => (float)$costPreview['hpp_standard_unit'],
                'cost_source_label' => (string)$costPreview['cost_source_label'],
            ];
        }

        return $this->posbundlepricingservice->allocate((float)($bundle['selling_price'] ?? 0), $lines);
    }

    private function resolveBundleProductCostPreview(array $row): array
    {
        $standard = round((float)($row['hpp_standard'] ?? 0), 6);
        $live = round((float)($row['hpp_live_cache'] ?? 0), 6);
        $sourceLabel = 'HPP Live Cache';

        if ($live <= 0) {
            $live = $standard;
            $sourceLabel = 'Fallback HPP Standard';
        }

        return [
            'hpp_standard_unit' => $standard,
            'hpp_live_unit' => $live,
            'cost_source_label' => $sourceLabel,
        ];
    }

    private function normalizeProductBundlePayload(int $bundleId = 0): array
    {
        $bundleName = trim((string)$this->input->post('bundle_name', true));
        if ($bundleName === '') {
            return ['ok' => false, 'message' => 'Nama bundle wajib diisi.'];
        }

        $raw = (string)$this->input->post('lines_json', false);
        $lines = json_decode($raw, true);
        if (!is_array($lines) || empty($lines)) {
            return ['ok' => false, 'message' => 'Minimal harus ada 1 produk di dalam bundle.'];
        }

        $normalizedLines = [];
        $seenProductIds = [];
        $lineProducts = [];
        foreach ($lines as $index => $line) {
            $productId = (int)($line['product_id'] ?? 0);
            $qty = round((float)($line['qty'] ?? 0), 4);
            $rawSortOrder = trim((string)($line['sort_order'] ?? ''));
            $sortOrder = $rawSortOrder === '' ? (($index + 1) * 10) : (int)$rawSortOrder;
            $unitPriceOverride = isset($line['unit_price_override']) && $line['unit_price_override'] !== '' && $line['unit_price_override'] !== null
                ? round((float)$line['unit_price_override'], 2)
                : null;

            if ($productId <= 0 || $qty <= 0) {
                continue;
            }
            if (isset($seenProductIds[$productId])) {
                return ['ok' => false, 'message' => 'Produk di dalam bundle tidak boleh duplikat.'];
            }
            $seenProductIds[$productId] = true;
            $lineProducts[] = $productId;
            $normalizedLines[] = [
                'product_id' => $productId,
                'qty' => $qty,
                'unit_price_override' => $unitPriceOverride,
                'sort_order' => $sortOrder,
            ];
        }

        if (empty($normalizedLines)) {
            return ['ok' => false, 'message' => 'Minimal harus ada 1 produk valid di dalam bundle.'];
        }

        $productRows = $this->db
            ->select('id, product_division_id')
            ->from('mst_product')
            ->where_in('id', $lineProducts)
            ->get()
            ->result_array();
        $productDivisionMap = [];
        foreach ($productRows as $row) {
            $productDivisionMap[(int)$row['id']] = (int)($row['product_division_id'] ?? 0);
        }

        $divisionIds = [];
        foreach ($normalizedLines as $line) {
            $divisionIds[] = (int)($productDivisionMap[(int)$line['product_id']] ?? 0);
        }
        $divisionIds = array_values(array_unique(array_filter($divisionIds)));
        $productDivisionId = count($divisionIds) === 1 ? (int)$divisionIds[0] : 0;
        $bundleCode = strtoupper(trim((string)$this->input->post('bundle_code', true)));
        if ($bundleCode === '') {
            $bundleCode = $this->generateNamedCode('pos_product_bundle', 'bundle_code', $bundleName, 'BND-', $bundleId, 40);
        } elseif ($this->codeExists('pos_product_bundle', 'bundle_code', $bundleCode, $bundleId)) {
            return ['ok' => false, 'message' => 'Kode bundle sudah dipakai.'];
        }

        $posScope = strtoupper(trim((string)$this->input->post('pos_scope', true) ?: 'REGULAR'));
        if (!in_array($posScope, ['REGULAR', 'EVENT', 'ALL'], true)) {
            $posScope = 'REGULAR';
        }

        $sellingPrice = round((float)$this->input->post('selling_price', true), 2);
        $description = trim((string)$this->input->post('description', true));
        $sortOrderInput = trim((string)$this->input->post('sort_order', true));
        $sortOrder = $sortOrderInput === '' ? 10 : (int)$sortOrderInput;
        $isActive = (int)$this->input->post('is_active', true) === 1 ? 1 : 0;

        return [
            'ok' => true,
            'bundle' => [
                'bundle_code' => $bundleCode,
                'bundle_name' => $bundleName,
                'product_division_id' => $productDivisionId > 0 ? $productDivisionId : null,
                'pos_scope' => $posScope,
                'selling_price' => $sellingPrice,
                'description' => $description !== '' ? $description : null,
                'sort_order' => $sortOrder,
                'is_active' => $isActive,
            ],
            'lines' => $normalizedLines,
        ];
    }

    private function generateNamedCode(string $table, string $column, string $name, string $prefix, int $excludeId = 0, int $maxLen = 40): string
    {
        $slug = strtoupper(trim(preg_replace('/[^A-Z0-9]+/', '-', strtoupper($name)), '-'));
        if ($slug === '') {
            $slug = 'ITEM';
        }
        $slug = substr($slug, 0, max(6, $maxLen - strlen($prefix) - 6));
        $base = $prefix . $slug;
        $candidate = $base;
        $counter = 2;
        while ($this->codeExists($table, $column, $candidate, $excludeId)) {
            $suffix = '-' . $counter;
            $candidate = substr($base, 0, $maxLen - strlen($suffix)) . $suffix;
            $counter++;
        }
        return $candidate;
    }

    private function codeExists(string $table, string $column, string $value, int $excludeId = 0): bool
    {
        $this->db->from($table)->where($column, $value);
        if ($excludeId > 0) {
            $this->db->where('id !=', $excludeId);
        }
        return $this->db->count_all_results() > 0;
    }

    private function componentFormulaOptions(int $componentId): array
    {
        $this->db->where('id !=', $componentId);
        $this->db->where('is_active', 1);
        $componentRows = $this->db->order_by('component_name', 'ASC')->get('mst_component')->result_array();

        $components = [];
        foreach ($componentRows as $row) {
            $components[] = [
                'value' => $row['id'],
                'label' => $row['component_name'],
            ];
        }

        return [
            'materials' => $this->Master_model->get_options('mst_item', 'id', 'item_name', true),
            'components' => $components,
            'uoms' => $this->Master_model->get_options('mst_uom', 'id', 'name', true),
        ];
    }
}
