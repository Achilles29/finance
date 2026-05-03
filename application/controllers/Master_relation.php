<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Master_relation extends MY_Controller
{
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
        $product = $this->Master_model->get_by_id('mst_product', $productId);
        if (!$product) show_404();

        $this->db->select('r.*, i.item_name, c.component_name, u.name AS uom_name, d.name AS source_division_name');
        $this->db->from('mst_product_recipe r');
        $this->db->join('mst_item i', 'i.id = r.material_item_id', 'left');
        $this->db->join('mst_component c', 'c.id = r.component_id', 'left');
        $this->db->join('mst_uom u', 'u.id = r.uom_id', 'left');
        $this->db->join('mst_operational_division d', 'd.id = r.source_division_id', 'left');
        $this->db->where('r.product_id', $productId);
        $this->db->order_by('r.sort_order ASC, r.id ASC');
        $rows = $this->db->get()->result_array();

        $this->render('master/relation_list', [
            'title' => 'Relasi Recipe Product',
            'active_menu' => 'grp.master',
            'relation_type' => 'product-recipe',
            'parent' => $product,
            'rows' => $rows,
        ]);
    }

    public function product_recipe_create(int $productId)
    {
        $product = $this->Master_model->get_by_id('mst_product', $productId);
        if (!$product) show_404();

        $this->render('master/relation_form', [
            'title' => 'Tambah Line Recipe Product',
            'active_menu' => 'grp.master',
            'relation_type' => 'product-recipe',
            'parent' => $product,
            'row' => null,
            'form_action' => 'master/relation/product-recipe/' . $productId . '/store',
            'options' => $this->productRecipeOptions(),
        ]);
    }

    public function product_recipe_store(int $productId)
    {
        $product = $this->Master_model->get_by_id('mst_product', $productId);
        if (!$product) show_404();

        $this->form_validation->set_rules('line_type', 'Line Type', 'required');
        $this->form_validation->set_rules('qty', 'Qty', 'required|numeric');
        $this->form_validation->set_rules('uom_id', 'Satuan', 'required|integer');

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

        $this->Master_model->insert('mst_product_recipe', [
            'product_id' => $productId,
            'line_no' => (int)$this->input->post('line_no', true) ?: 1,
            'line_type' => $lineType,
            'material_item_id' => $materialItemId ?: null,
            'component_id' => $componentId ?: null,
            'source_division_id' => (int)$this->input->post('source_division_id', true) ?: null,
            'qty' => (float)$this->input->post('qty', true),
            'uom_id' => (int)$this->input->post('uom_id', true),
            'notes' => $this->input->post('notes', true),
            'sort_order' => (int)$this->input->post('sort_order', true) ?: 0,
        ]);

        $this->session->set_flashdata('success', 'Line recipe product berhasil ditambahkan.');
        redirect('master/relation/product-recipe/' . $productId);
    }

    public function product_recipe_edit(int $id)
    {
        $row = $this->Master_model->get_by_id('mst_product_recipe', $id);
        if (!$row) show_404();
        $parent = $this->Master_model->get_by_id('mst_product', (int)$row['product_id']);
        if (!$parent) show_404();

        $this->render('master/relation_form', [
            'title' => 'Edit Line Recipe Product',
            'active_menu' => 'grp.master',
            'relation_type' => 'product-recipe',
            'parent' => $parent,
            'row' => $row,
            'form_action' => 'master/relation/product-recipe/edit/' . $id . '/update',
            'options' => $this->productRecipeOptions(),
        ]);
    }

    public function product_recipe_update(int $id)
    {
        $row = $this->Master_model->get_by_id('mst_product_recipe', $id);
        if (!$row) show_404();

        $this->form_validation->set_rules('line_type', 'Line Type', 'required');
        $this->form_validation->set_rules('qty', 'Qty', 'required|numeric');
        $this->form_validation->set_rules('uom_id', 'Satuan', 'required|integer');

        if ($this->form_validation->run() === false) {
            $this->session->set_flashdata('error', validation_errors('<li>', '</li>'));
            redirect('master/relation/product-recipe/edit/' . $id);
            return;
        }

        $lineType = (string)$this->input->post('line_type', true);
        $materialItemId = $lineType === 'MATERIAL' ? (int)$this->input->post('material_item_id', true) : null;
        $componentId = $lineType === 'COMPONENT' ? (int)$this->input->post('component_id', true) : null;

        $this->Master_model->update('mst_product_recipe', $id, [
            'line_no' => (int)$this->input->post('line_no', true) ?: 1,
            'line_type' => $lineType,
            'material_item_id' => $materialItemId ?: null,
            'component_id' => $componentId ?: null,
            'source_division_id' => (int)$this->input->post('source_division_id', true) ?: null,
            'qty' => (float)$this->input->post('qty', true),
            'uom_id' => (int)$this->input->post('uom_id', true),
            'notes' => $this->input->post('notes', true),
            'sort_order' => (int)$this->input->post('sort_order', true) ?: 0,
        ]);

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

    private function productRecipeOptions(): array
    {
        return [
            'materials' => $this->Master_model->get_options('mst_item', 'id', 'item_name', true),
            'components' => $this->Master_model->get_options('mst_component', 'id', 'component_name', true),
            'uoms' => $this->Master_model->get_options('mst_uom', 'id', 'name', true),
            // Keep operational division as source to keep inventory deduction path consistent.
            'source_divisions' => $this->Master_model->get_options('mst_operational_division', 'id', 'name', true),
        ];
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
