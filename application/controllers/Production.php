<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Production extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Production_model');
        $this->load->library('ComponentStockWriter');
    }

    public function component_stock()
    {
        $this->require_permission('production.component.stock.index', 'view');
        $filters = $this->stock_filters();
        $rows = $this->Production_model->component_stock_rows($filters, 200);

        $this->render('production/component_stock_index', [
            'page_title' => 'Stok Base/Prepare',
            'rows' => $rows,
            'filters' => $filters,
            'location_options' => $this->location_options(),
        ]);
    }

    public function component_stock_data()
    {
        $this->require_permission('production.component.stock.index', 'view');
        $filters = $this->stock_filters();
        $rows = $this->Production_model->component_stock_rows($filters, 500);
        $this->json_ok(['rows' => $rows]);
    }

    public function component_movements()
    {
        $this->require_permission('production.component.movement.index', 'view');
        $filters = $this->movement_filters();
        $rows = $this->Production_model->component_movement_rows($filters, 300);

        $this->render('production/component_movement_index', [
            'page_title' => 'Mutasi Base/Prepare',
            'rows' => $rows,
            'filters' => $filters,
            'location_options' => $this->location_options(),
        ]);
    }

    public function component_movements_data()
    {
        $this->require_permission('production.component.movement.index', 'view');
        $filters = $this->movement_filters();
        $rows = $this->Production_model->component_movement_rows($filters, 1000);
        $this->json_ok(['rows' => $rows]);
    }

    public function component_daily()
    {
        $this->require_permission('production.component.daily.index', 'view');
        $filters = $this->daily_filters();
        $rows = $this->Production_model->component_daily_rows($filters, 500);

        $this->render('production/component_daily_index', [
            'page_title' => 'Daily Matrix Base/Prepare',
            'rows' => $rows,
            'filters' => $filters,
            'location_options' => $this->location_options(),
        ]);
    }

    public function component_daily_data()
    {
        $this->require_permission('production.component.daily.index', 'view');
        $filters = $this->daily_filters();
        $rows = $this->Production_model->component_daily_rows($filters, 1500);
        $this->json_ok(['rows' => $rows]);
    }

    public function component_openings()
    {
        $this->require_permission('production.component.opening.index', 'view');
        $q = trim((string)$this->input->get('q', true));
        $rows = $this->Production_model->list_component_openings(['q' => $q], 300);
        $this->render('production/component_opening_index', [
            'page_title' => 'Opening Base/Prepare',
            'rows' => $rows,
            'q' => $q,
            'location_options' => $this->location_options(),
            'components' => $this->active_components(),
            'uoms' => $this->active_uoms(),
            'divisions' => $this->active_divisions(),
        ]);
    }

    public function component_opening_save()
    {
        $this->require_permission('production.component.opening.index', 'create');
        $payload = $this->request_payload();
        $header = [
            'id' => (int)($payload['id'] ?? 0),
            'opening_no' => (string)($payload['opening_no'] ?? ''),
            'opening_date' => (string)($payload['opening_date'] ?? date('Y-m-d')),
            'location_type' => $this->normalize_location_type($payload['location_type'] ?? ''),
            'division_id' => !empty($payload['division_id']) ? (int)$payload['division_id'] : null,
            'notes' => (string)($payload['notes'] ?? ''),
        ];
        $lines = $this->normalize_lines((array)($payload['lines'] ?? []), 'opening');
        $save = $this->Production_model->save_component_opening($header, $lines, (int)($this->current_user['employee_id'] ?? 0));
        if (!($save['ok'] ?? false)) {
            $this->json_error((string)($save['message'] ?? 'Gagal menyimpan opening.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$save['id']]);
    }

    public function component_opening_post($id)
    {
        $this->require_permission('production.component.opening.index', 'edit');
        $id = (int)$id;
        $header = $this->Production_model->get_component_opening($id);
        if (!$header) {
            $this->json_error('Opening tidak ditemukan.', 404);
            return;
        }
        if (strtoupper((string)$header['status']) !== 'DRAFT') {
            $this->json_error('Hanya opening DRAFT yang bisa diposting.', 422);
            return;
        }
        $linesRaw = $this->Production_model->get_component_opening_lines($id);
        $lines = [];
        foreach ($linesRaw as $line) {
            $lines[] = [
                'id' => (int)$line['id'],
                'component_id' => (int)$line['component_id'],
                'uom_id' => (int)$line['uom_id'],
                'opening_qty' => (float)$line['opening_qty'],
                'qty' => (float)$line['opening_qty'],
                'movement_type' => 'OPENING',
                'unit_cost' => (float)$line['unit_cost'],
                'note' => (string)($line['note'] ?? ''),
            ];
        }
        $post = $this->componentstockwriter->post_opening($header, $lines, (int)($this->current_user['employee_id'] ?? 0));
        if (!($post['ok'] ?? false)) {
            $this->json_error((string)($post['message'] ?? 'Posting opening gagal.'), 422);
            return;
        }
        $this->db->where('id', $id)->update('inv_component_opening', [
            'status' => 'POSTED',
            'posted_at' => date('Y-m-d H:i:s'),
            'posted_by' => !empty($this->current_user['employee_id']) ? (int)$this->current_user['employee_id'] : null,
        ]);
        $this->json_ok(['id' => $id]);
    }

    public function component_opening_delete($id)
    {
        $this->require_permission('production.component.opening.index', 'delete');
        $result = $this->Production_model->delete_draft_doc('inv_component_opening', 'inv_component_opening_line', 'opening_id', (int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menghapus opening.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id]);
    }

    public function component_adjustments()
    {
        $this->require_permission('production.component.adjustment.index', 'view');
        $q = trim((string)$this->input->get('q', true));
        $rows = $this->Production_model->list_component_adjustments(['q' => $q], 300);
        $this->render('production/component_adjustment_index', [
            'page_title' => 'Adjustment Base/Prepare',
            'rows' => $rows,
            'q' => $q,
            'location_options' => $this->location_options(),
            'components' => $this->active_components(),
            'uoms' => $this->active_uoms(),
            'divisions' => $this->active_divisions(),
        ]);
    }

    public function component_adjustment_save()
    {
        $this->require_permission('production.component.adjustment.index', 'create');
        $payload = $this->request_payload();
        $header = [
            'id' => (int)($payload['id'] ?? 0),
            'adjustment_no' => (string)($payload['adjustment_no'] ?? ''),
            'adjustment_date' => (string)($payload['adjustment_date'] ?? date('Y-m-d')),
            'location_type' => $this->normalize_location_type($payload['location_type'] ?? ''),
            'division_id' => !empty($payload['division_id']) ? (int)$payload['division_id'] : null,
            'notes' => (string)($payload['notes'] ?? ''),
        ];
        $lines = $this->normalize_lines((array)($payload['lines'] ?? []), 'adjustment');
        $save = $this->Production_model->save_component_adjustment($header, $lines, (int)($this->current_user['employee_id'] ?? 0));
        if (!($save['ok'] ?? false)) {
            $this->json_error((string)($save['message'] ?? 'Gagal menyimpan adjustment.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$save['id']]);
    }

    public function component_adjustment_post($id)
    {
        $this->require_permission('production.component.adjustment.index', 'edit');
        $id = (int)$id;
        $header = $this->Production_model->get_component_adjustment($id);
        if (!$header) {
            $this->json_error('Adjustment tidak ditemukan.', 404);
            return;
        }
        if (strtoupper((string)$header['status']) !== 'DRAFT') {
            $this->json_error('Hanya adjustment DRAFT yang bisa diposting.', 422);
            return;
        }

        $lines = $this->Production_model->get_component_adjustment_lines($id);
        $post = $this->componentstockwriter->post_adjustment($header, $lines, (int)($this->current_user['employee_id'] ?? 0));
        if (!($post['ok'] ?? false)) {
            $this->json_error((string)($post['message'] ?? 'Posting adjustment gagal.'), 422);
            return;
        }
        $this->db->where('id', $id)->update('inv_component_adjustment', [
            'status' => 'POSTED',
            'posted_at' => date('Y-m-d H:i:s'),
            'posted_by' => !empty($this->current_user['employee_id']) ? (int)$this->current_user['employee_id'] : null,
        ]);
        $this->json_ok(['id' => $id]);
    }

    public function component_adjustment_delete($id)
    {
        $this->require_permission('production.component.adjustment.index', 'delete');
        $result = $this->Production_model->delete_draft_doc('inv_component_adjustment', 'inv_component_adjustment_line', 'adjustment_id', (int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menghapus adjustment.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id]);
    }

    public function component_batches()
    {
        $this->require_permission('production.component.batch.index', 'view');
        $q = trim((string)$this->input->get('q', true));
        $rows = $this->Production_model->list_component_batches(['q' => $q], 300);
        $this->render('production/component_batch_index', [
            'page_title' => 'Batch Produksi Base/Prepare',
            'rows' => $rows,
            'q' => $q,
            'location_options' => $this->location_options(),
            'components' => $this->active_components(),
            'uoms' => $this->active_uoms(),
            'divisions' => $this->active_divisions(),
        ]);
    }

    public function component_batch_save()
    {
        $this->require_permission('production.component.batch.index', 'create');
        $payload = $this->request_payload();
        $header = [
            'id' => (int)($payload['id'] ?? 0),
            'batch_no' => (string)($payload['batch_no'] ?? ''),
            'batch_date' => (string)($payload['batch_date'] ?? date('Y-m-d')),
            'location_type' => $this->normalize_location_type($payload['location_type'] ?? ''),
            'division_id' => !empty($payload['division_id']) ? (int)$payload['division_id'] : null,
            'component_id' => (int)($payload['component_id'] ?? 0),
            'output_qty' => (float)($payload['output_qty'] ?? 0),
            'output_uom_id' => (int)($payload['output_uom_id'] ?? 0),
            'notes' => (string)($payload['notes'] ?? ''),
        ];
        $lines = $this->normalize_lines((array)($payload['lines'] ?? []), 'batch');
        $save = $this->Production_model->save_component_batch($header, $lines, (int)($this->current_user['employee_id'] ?? 0));
        if (!($save['ok'] ?? false)) {
            $this->json_error((string)($save['message'] ?? 'Gagal menyimpan batch.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$save['id']]);
    }

    public function component_batch_post($id)
    {
        $this->require_permission('production.component.batch.index', 'edit');
        $id = (int)$id;
        $header = $this->Production_model->get_component_batch($id);
        if (!$header) {
            $this->json_error('Batch tidak ditemukan.', 404);
            return;
        }
        if (strtoupper((string)$header['status']) !== 'DRAFT') {
            $this->json_error('Hanya batch DRAFT yang bisa diposting.', 422);
            return;
        }
        $inputs = $this->Production_model->get_component_batch_inputs($id);
        $post = $this->componentstockwriter->post_batch($header, $inputs, (int)($this->current_user['employee_id'] ?? 0));
        if (!($post['ok'] ?? false)) {
            $this->json_error((string)($post['message'] ?? 'Posting batch gagal.'), 422);
            return;
        }
        $this->db->where('id', $id)->update('inv_component_batch', [
            'status' => 'POSTED',
            'posted_at' => date('Y-m-d H:i:s'),
            'posted_by' => !empty($this->current_user['employee_id']) ? (int)$this->current_user['employee_id'] : null,
        ]);
        $this->json_ok(['id' => $id]);
    }

    public function component_batch_delete($id)
    {
        $this->require_permission('production.component.batch.index', 'delete');
        $result = $this->Production_model->delete_draft_doc('inv_component_batch', 'inv_component_batch_input', 'batch_id', (int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menghapus batch.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id]);
    }

    public function component_categories()
    {
        $this->require_permission('production.component.category.index', 'view');
        $q = trim((string)$this->input->get('q', true));
        $rows = $this->Production_model->list_component_categories(['q' => $q], 300);
        $this->render('production/component_category_index', [
            'page_title' => 'Kategori Base/Prepare',
            'rows' => $rows,
            'q' => $q,
            'parent_options' => $this->Production_model->list_component_categories([], 1000),
            'components_for_mapping' => $this->Production_model->list_components_for_mapping(1500),
            'unmapped_components' => $this->Production_model->list_unmapped_components(300),
        ]);
    }

    public function component_category_save()
    {
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('production.component.category.index', $id > 0 ? 'edit' : 'create');
        $result = $this->Production_model->save_component_category($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan kategori.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function component_category_toggle($id)
    {
        $this->require_permission('production.component.category.index', 'edit');
        $result = $this->Production_model->toggle_active('mst_component_category', (int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal ubah status kategori.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id, 'is_active' => (int)$result['is_active']]);
    }

    public function component_category_quick_map()
    {
        $this->require_permission('production.component.category.index', 'edit');
        $payload = $this->request_payload();
        $componentId = (int)($payload['component_id'] ?? 0);
        $categoryId = (int)($payload['component_category_id'] ?? 0);
        $result = $this->Production_model->quick_map_component_category($componentId, $categoryId);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal mapping kategori.'), 422);
            return;
        }
        $this->json_ok(['id' => $componentId]);
    }

    public function component_masters()
    {
        $this->require_permission('production.component.master.index', 'view');
        $filters = $this->component_master_filters();
        $this->render('production/component_master_index', [
            'page_title' => 'Master Base/Prepare',
            'filters' => $filters,
            'categories' => $this->Production_model->list_component_categories([], 1000),
            'uoms' => $this->active_uoms(),
            'divisions' => $this->active_divisions(),
            'product_divisions' => $this->active_product_divisions(),
        ]);
    }

    public function component_masters_data()
    {
        $this->require_permission('production.component.master.index', 'view');
        $filters = $this->component_master_filters();
        $result = $this->Production_model->list_components_paginated($filters);
        $this->json_ok($result + ['filters' => $filters]);
    }

    public function component_master_save()
    {
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('production.component.master.index', $id > 0 ? 'edit' : 'create');
        $result = $this->Production_model->save_component_master($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan master component.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function component_master_toggle($id)
    {
        $this->require_permission('production.component.master.index', 'edit');
        $result = $this->Production_model->toggle_active('mst_component', (int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal ubah status master component.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id, 'is_active' => (int)$result['is_active']]);
    }

    public function component_formulas()
    {
        $this->require_permission('production.component.formula.index', 'view');
        $filters = $this->component_formula_filters();
        $this->render('production/component_formula_index', [
            'page_title' => 'Resep / Formula Base/Prepare',
            'filters' => $filters,
            'categories' => $this->Production_model->list_component_categories([], 1000),
            'divisions' => $this->active_divisions(),
            'uoms' => $this->active_uoms(),
            'materials' => $this->active_materials(),
            'components' => $this->active_components(),
        ]);
    }

    public function component_formulas_data()
    {
        $this->require_permission('production.component.formula.index', 'view');
        $filters = $this->component_formula_filters();
        $result = $this->Production_model->list_component_formula_components_paginated($filters);
        $this->json_ok($result + ['filters' => $filters]);
    }

    public function component_formula_detail()
    {
        $this->require_permission('production.component.formula.index', 'view');
        $componentId = (int)$this->input->get('component_id', true);
        if ($componentId <= 0) {
            $this->json_error('Component wajib dipilih.', 422);
            return;
        }
        $detail = $this->Production_model->component_formula_detail($componentId);
        if (!($detail['ok'] ?? false)) {
            $this->json_error((string)($detail['message'] ?? 'Formula tidak ditemukan.'), 404);
            return;
        }
        $this->json_ok($detail);
    }

    public function component_formula_source_search()
    {
        $this->require_permission('production.component.formula.index', 'view');
        $lineType = strtoupper(trim((string)$this->input->get('line_type', true)));
        $q = trim((string)$this->input->get('q', true));
        $componentId = (int)$this->input->get('component_id', true);
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 50) {
            $limit = 20;
        }

        if ($lineType === 'MATERIAL') {
            $rows = $this->db->select('m.id, m.material_code AS code, m.material_name AS name, u.code AS uom_code')
                ->from('mst_material m')
                ->join('mst_uom u', 'u.id = m.content_uom_id', 'left')
                ->where('m.is_active', 1)
                ->group_start()
                    ->like('m.material_name', $q)
                    ->or_like('m.material_code', $q)
                ->group_end()
                ->order_by('m.material_name', 'ASC')
                ->limit($limit)
                ->get()->result_array();
            $this->json_ok(['rows' => $rows]);
            return;
        }

        $componentType = '';
        if ($componentId > 0) {
            $parent = $this->db->select('component_type')->from('mst_component')->where('id', $componentId)->limit(1)->get()->row_array();
            $componentType = strtoupper((string)($parent['component_type'] ?? ''));
        }

        $this->db->select('c.id, c.component_code AS code, c.component_name AS name, c.component_type, u.code AS uom_code')
            ->from('mst_component c')
            ->join('mst_uom u', 'u.id = c.uom_id', 'left')
            ->where('c.is_active', 1);
        if ($componentId > 0) {
            $this->db->where('c.id <>', $componentId);
        }
        if ($componentType === 'BASE') {
            $this->db->where('c.component_type', 'BASE');
        } elseif ($componentType === 'PREPARE') {
            $this->db->where_in('c.component_type', ['BASE', 'PREPARE']);
        }
        if ($q !== '') {
            $this->db->group_start()
                ->like('c.component_name', $q)
                ->or_like('c.component_code', $q)
                ->group_end();
        }
        $rows = $this->db->order_by('c.component_type', 'ASC')
            ->order_by('c.component_name', 'ASC')
            ->limit($limit)
            ->get()->result_array();
        $this->json_ok(['rows' => $rows]);
    }

    public function component_formula_show($componentId)
    {
        $this->require_permission('production.component.formula.index', 'view');
        $componentId = (int)$componentId;
        if ($componentId <= 0) {
            show_error('Component tidak valid.', 422, 'Invalid Request');
            return;
        }
        $detail = $this->Production_model->component_formula_detail($componentId);
        if (!($detail['ok'] ?? false)) {
            show_error((string)($detail['message'] ?? 'Formula tidak ditemukan.'), 404, 'Not Found');
            return;
        }
        $this->render('production/component_formula_detail', [
            'page_title' => 'Detail Formula Component',
            'detail' => $detail,
        ]);
    }

    public function component_formula_edit($componentId)
    {
        $this->require_permission('production.component.formula.index', 'view');
        $componentId = (int)$componentId;
        if ($componentId <= 0) {
            show_error('Component tidak valid.', 422, 'Invalid Request');
            return;
        }
        $detail = $this->Production_model->component_formula_detail($componentId);
        if (!($detail['ok'] ?? false)) {
            show_error((string)($detail['message'] ?? 'Formula tidak ditemukan.'), 404, 'Not Found');
            return;
        }
        $this->render('production/component_formula_edit', [
            'page_title' => 'Edit Formula Component',
            'detail' => $detail,
            'uoms' => $this->active_uoms(),
            'materials' => $this->active_materials(),
            'components' => $this->active_components(),
        ]);
    }

    public function component_formula_save()
    {
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('production.component.formula.index', $id > 0 ? 'edit' : 'create');
        $result = $this->Production_model->save_component_formula($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan formula.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function component_formula_save_bulk()
    {
        $this->require_permission('production.component.formula.index', 'edit');
        $payload = $this->request_payload();
        $componentId = (int)($payload['component_id'] ?? 0);
        $lines = isset($payload['lines']) && is_array($payload['lines']) ? $payload['lines'] : [];
        $result = $this->Production_model->save_component_formula_bulk($componentId, $lines);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal simpan formula bulk.'), 422);
            return;
        }
        $this->json_ok(['component_id' => $componentId]);
    }

    public function component_formula_delete($id)
    {
        $this->require_permission('production.component.formula.index', 'delete');
        $result = $this->Production_model->delete_component_formula((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal hapus formula.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id]);
    }

    public function component_cost_variables()
    {
        $this->require_permission('production.component.formula.index', 'view');
        $this->render('production/component_cost_variable_index', [
            'page_title' => 'Pengaturan Variable Cost',
            'rows' => $this->Production_model->variable_cost_default_list(),
        ]);
    }

    public function component_cost_variable_save()
    {
        $this->require_permission('production.component.formula.index', 'edit');
        $payload = $this->request_payload();
        $result = $this->Production_model->save_variable_cost_default($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal simpan variable cost default.'), 422);
            return;
        }
        $this->json_ok();
    }

    private function stock_filters()
    {
        return [
            'q' => trim((string)$this->input->get('q', true)),
            'location_type' => $this->normalize_location_type($this->input->get('location_type', true)),
        ];
    }

    private function movement_filters()
    {
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo = trim((string)$this->input->get('date_to', true));
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $dateFrom)) {
            $dateFrom = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $dateTo)) {
            $dateTo = date('Y-m-d');
        }

        return [
            'q' => trim((string)$this->input->get('q', true)),
            'location_type' => $this->normalize_location_type($this->input->get('location_type', true)),
            'movement_type' => strtoupper(trim((string)$this->input->get('movement_type', true))),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    private function daily_filters()
    {
        $month = trim((string)$this->input->get('month', true));
        if (!preg_match('/^\d{4}\-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        return [
            'q' => trim((string)$this->input->get('q', true)),
            'month' => $month,
            'location_type' => $this->normalize_location_type($this->input->get('location_type', true)),
        ];
    }

    private function location_options()
    {
        return ['' => 'Semua Lokasi', 'BAR' => 'BAR', 'KITCHEN' => 'KITCHEN', 'BAR_EVENT' => 'BAR_EVENT', 'KITCHEN_EVENT' => 'KITCHEN_EVENT'];
    }

    private function normalize_location_type($value)
    {
        $value = strtoupper(trim((string)$value));
        return in_array($value, ['BAR', 'KITCHEN', 'BAR_EVENT', 'KITCHEN_EVENT'], true) ? $value : '';
    }

    private function component_master_filters(): array
    {
        $q = trim((string)$this->input->get('q', true));
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $status = 'ACTIVE';
        }

        $type = strtoupper(trim((string)$this->input->get('type', true)));
        if (!in_array($type, ['BASE', 'PREPARE', 'ALL'], true)) {
            $type = 'ALL';
        }

        $divisionId = (int)$this->input->get('division_id', true);
        $categoryId = (int)$this->input->get('category_id', true);

        $page = (int)$this->input->get('page', true);
        if ($page <= 0) {
            $page = 1;
        }
        $limit = (int)$this->input->get('limit', true);
        if ($limit < 0 || $limit > 300) {
            $limit = 50;
        }

        return [
            'q' => $q,
            'status' => $status,
            'type' => $type,
            'division_id' => $divisionId > 0 ? $divisionId : 0,
            'category_id' => $categoryId > 0 ? $categoryId : 0,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    private function component_formula_filters(): array
    {
        $q = trim((string)$this->input->get('q', true));
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $status = 'ACTIVE';
        }

        $type = strtoupper(trim((string)$this->input->get('type', true)));
        if (!in_array($type, ['BASE', 'PREPARE', 'ALL'], true)) {
            $type = 'ALL';
        }

        $divisionId = (int)$this->input->get('division_id', true);
        $categoryId = (int)$this->input->get('category_id', true);
        $page = (int)$this->input->get('page', true);
        if ($page <= 0) {
            $page = 1;
        }
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 300) {
            $limit = 50;
        }

        return [
            'q' => $q,
            'status' => $status,
            'type' => $type,
            'division_id' => $divisionId > 0 ? $divisionId : 0,
            'category_id' => $categoryId > 0 ? $categoryId : 0,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    private function active_components(): array
    {
        return $this->db->select('c.id, c.component_code, c.component_name, c.uom_id, c.component_type, u.code AS uom_code')
            ->from('mst_component c')
            ->join('mst_uom u', 'u.id = c.uom_id', 'left')
            ->join('mst_operational_division d', 'd.id = c.operational_division_id', 'left')
            ->join('mst_component_category cat', 'cat.id = c.component_category_id', 'left')
            ->where('c.is_active', 1)
            ->order_by('c.component_type', 'ASC')
            ->order_by('d.name', 'ASC')
            ->order_by('cat.name', 'ASC')
            ->order_by('c.component_name', 'ASC')
            ->get()->result_array();
    }

    private function active_uoms(): array
    {
        return $this->db->select('id, code, name')->from('mst_uom')->where('is_active', 1)->order_by('name', 'ASC')->get()->result_array();
    }

    private function active_divisions(): array
    {
        return $this->db->select('id, code, name')->from('mst_operational_division')->where('is_active', 1)->order_by('name', 'ASC')->get()->result_array();
    }

    private function active_product_divisions(): array
    {
        return $this->db->select('id, code, name')->from('mst_product_division')->where('is_active', 1)->order_by('name', 'ASC')->get()->result_array();
    }

    private function active_materials(): array
    {
        return $this->db->select('m.id, m.material_code, m.material_name, u.code AS content_uom_code')
            ->from('mst_material m')
            ->join('mst_uom u', 'u.id = m.content_uom_id', 'left')
            ->where('m.is_active', 1)
            ->order_by('m.material_name', 'ASC')
            ->get()->result_array();
    }

    private function request_payload(): array
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

    private function normalize_lines(array $lines, string $mode): array
    {
        $result = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            if ($mode === 'opening') {
                $result[] = [
                    'component_id' => (int)($line['component_id'] ?? 0),
                    'uom_id' => (int)($line['uom_id'] ?? 0),
                    'opening_qty' => round((float)($line['opening_qty'] ?? 0), 4),
                    'unit_cost' => round((float)($line['unit_cost'] ?? 0), 6),
                    'note' => (string)($line['note'] ?? ''),
                ];
                continue;
            }
            if ($mode === 'adjustment') {
                $result[] = [
                    'component_id' => (int)($line['component_id'] ?? 0),
                    'uom_id' => (int)($line['uom_id'] ?? 0),
                    'available_qty' => round((float)($line['available_qty'] ?? 0), 4),
                    'qty_spoil' => round((float)($line['qty_spoil'] ?? 0), 4),
                    'qty_waste' => round((float)($line['qty_waste'] ?? 0), 4),
                    'qty_adjust_pos' => round((float)($line['qty_adjust_pos'] ?? 0), 4),
                    'qty_adjust_neg' => round((float)($line['qty_adjust_neg'] ?? 0), 4),
                    'note' => (string)($line['note'] ?? ''),
                ];
                continue;
            }
            if ($mode === 'batch') {
                $result[] = [
                    'source_kind' => strtoupper(trim((string)($line['source_kind'] ?? ''))),
                    'item_id' => !empty($line['item_id']) ? (int)$line['item_id'] : null,
                    'material_id' => !empty($line['material_id']) ? (int)$line['material_id'] : null,
                    'component_id' => !empty($line['component_id']) ? (int)$line['component_id'] : null,
                    'uom_id' => (int)($line['uom_id'] ?? 0),
                    'qty' => round((float)($line['qty'] ?? 0), 4),
                    'unit_cost' => round((float)($line['unit_cost'] ?? 0), 6),
                    'notes' => (string)($line['notes'] ?? ''),
                ];
            }
        }
        return $result;
    }

    private function json_ok(array $data = []): void
    {
        $payload = ['ok' => true] + $data;
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function json_error(string $message, int $statusCode = 400): void
    {
        $this->output
            ->set_status_header($statusCode)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => false,
                'message' => $message,
            ], JSON_INVALID_UTF8_SUBSTITUTE));
    }
}
