<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Master_relation extends MY_Controller
{
    private $productRecipeMaterialCostCache = [];
    private $productRecipeComponentCostCache = [];

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Master_model');
        $this->load->library('form_validation');
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
            $availabilityRow = $this->buildProductAvailabilityRow($product);
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
            $payload['ingredient_role'] = $this->normalizeProductRecipeIngredientRole($this->input->post('ingredient_role', true));
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
            $payload['ingredient_role'] = $this->normalizeProductRecipeIngredientRole($this->input->post('ingredient_role', true));
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
                ->select('i.id, i.item_name, i.content_uom_id AS uom_id, u.name AS uom_name')
                ->from('mst_item i')
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
            'component_id' => (int)$component['id'],
            'source_division_id' => $sourceDivisionId > 0 ? $sourceDivisionId : null,
            'uom_id' => (int)$component['uom_id'],
        ];
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
                $row['ingredient_role'] = $this->normalizeProductRecipeIngredientRole($line['ingredient_role'] ?? 'MAIN');
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
        if ($divisionId > 0 && $this->db->table_exists('inv_division_stock_balance')) {
            $liveRow = $this->db->select('avg_cost_per_content')
                ->from('inv_division_stock_balance')
                ->where('division_id', $divisionId)
                ->where('material_id', $materialId)
                ->order_by('updated_at', 'DESC')
                ->limit(1)
                ->get()
                ->row_array();
            $qtyRow = $this->db->select('SUM(COALESCE(qty_content_balance,0)) AS qty_balance', false)
                ->from('inv_division_stock_balance')
                ->where('division_id', $divisionId)
                ->where('material_id', $materialId)
                ->get()
                ->row_array();
            $live = (float)($liveRow['avg_cost_per_content'] ?? 0);
            $availableQty = (float)($qtyRow['qty_balance'] ?? 0);
        }
        if ($live <= 0) {
            $live = $standard;
        }

        $result = [
            'standard_unit_cost' => round($standard, 6),
            'live_unit_cost' => round($live, 6),
            'available_qty' => round($availableQty, 4),
            'live_cost_source' => ($live > 0 && abs($live - $standard) > 0.0000001) ? 'STOCK_DIVISION' : 'FALLBACK_STANDARD',
            'live_cost_source_label' => ($live > 0 && abs($live - $standard) > 0.0000001) ? 'Stok Divisi' : 'Fallback Std',
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
        if ($this->db->table_exists('inv_component_stock_balance')) {
            $this->db->select('avg_cost')->from('inv_component_stock_balance')->where('component_id', $componentId);
            if ($divisionId > 0) {
                $this->db->where('division_id', $divisionId);
            }
            $liveRow = $this->db->order_by('updated_at', 'DESC')->limit(1)->get()->row_array();

            $this->db->select('SUM(COALESCE(qty_on_hand,0)) AS qty_balance', false)
                ->from('inv_component_stock_balance')
                ->where('component_id', $componentId);
            if ($divisionId > 0) {
                $this->db->where('division_id', $divisionId);
            }
            $qtyRow = $this->db->get()->row_array();

            $live = (float)($liveRow['avg_cost'] ?? 0);
            $availableQty = (float)($qtyRow['qty_balance'] ?? 0);
        }
        if ($live <= 0) {
            $live = $standard;
        }

        $result = [
            'standard_unit_cost' => round($standard, 6),
            'live_unit_cost' => round($live, 6),
            'available_qty' => round($availableQty, 4),
            'live_cost_source' => ($live > 0 && abs($live - $standard) > 0.0000001) ? 'STOCK_COMPONENT' : 'FALLBACK_STANDARD',
            'live_cost_source_label' => ($live > 0 && abs($live - $standard) > 0.0000001) ? 'Stok Component' : 'Fallback Std',
            'cost_reference_label' => 'Component',
        ];
        $this->productRecipeComponentCostCache[$cacheKey] = $result;

        return $result;
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
