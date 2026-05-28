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
        $this->Production_model->ensure_component_daily_rollup_seeded();
        $matrix = $this->Production_model->component_daily_matrix($filters, 500);
        if (empty($matrix['rows'])) {
            $latestMonth = $this->Production_model->latest_component_daily_month([
                'location_type' => (string)($filters['location_type'] ?? ''),
                'division_id' => (int)($filters['division_id'] ?? 0),
                'type' => (string)($filters['type'] ?? ''),
            ]);
            if ($latestMonth !== null && $latestMonth !== (string)$filters['month']) {
                $filters['month'] = $latestMonth;
                $matrix = $this->Production_model->component_daily_matrix($filters, 500);
            }
        }

        $this->render('production/component_daily_index', [
            'page_title' => 'Daily Matrix Base/Prepare',
            'filters' => $filters,
            'matrix' => $matrix,
            'location_options' => $this->location_options(),
            'divisions' => $this->active_divisions(),
        ]);
    }

    public function component_daily_data()
    {
        $this->require_permission('production.component.daily.index', 'view');
        $filters = $this->daily_filters();
        $this->Production_model->ensure_component_daily_rollup_seeded();
        $matrix = $this->Production_model->component_daily_matrix($filters, 1500);
        if (empty($matrix['rows'])) {
            $latestMonth = $this->Production_model->latest_component_daily_month([
                'location_type' => (string)($filters['location_type'] ?? ''),
                'division_id' => (int)($filters['division_id'] ?? 0),
                'type' => (string)($filters['type'] ?? ''),
            ]);
            if ($latestMonth !== null && $latestMonth !== (string)$filters['month']) {
                $filters['month'] = $latestMonth;
                $matrix = $this->Production_model->component_daily_matrix($filters, 1500);
            }
        }
        $this->json_ok($matrix);
    }

    public function component_monthly()
    {
        $this->require_permission('production.component.daily.index', 'view');
        $filters = $this->daily_filters();
        $this->Production_model->ensure_component_daily_rollup_seeded();
        $rows = $this->Production_model->component_monthly_rows($filters, 500);
        if (empty($rows)) {
            $latestMonth = $this->Production_model->latest_component_daily_month([
                'location_type' => (string)($filters['location_type'] ?? ''),
                'division_id' => (int)($filters['division_id'] ?? 0),
                'type' => (string)($filters['type'] ?? ''),
            ]);
            if ($latestMonth !== null && $latestMonth !== (string)$filters['month']) {
                $filters['month'] = $latestMonth;
                $rows = $this->Production_model->component_monthly_rows($filters, 500);
            }
        }

        $this->render('production/component_monthly_index', [
            'page_title' => 'Stok Bulanan Base/Prepare',
            'rows' => $rows,
            'filters' => $filters,
            'divisions' => $this->active_divisions(),
        ]);
    }

    public function component_lots()
    {
        $this->require_permission('production.component.batch.index', 'view');
        $filters = $this->lot_filters();

        $this->load->library('ComponentLotManager');
        $lotReady = $this->componentlotmanager->ensureReady();
        if (!($lotReady['ok'] ?? false)) {
            show_error((string)($lotReady['message'] ?? 'Schema lot component gagal disiapkan.'));
            return;
        }

        $rows = $this->componentlotmanager->listLots($filters, 400);
        $this->render('production/component_lot_index', [
            'page_title' => 'Lot FIFO Base/Prepare',
            'rows' => $rows,
            'filters' => $filters,
            'divisions' => $this->active_divisions(),
        ]);
    }

    public function component_openings()
    {
        $this->require_permission('production.component.opening.index', 'view');
        $q = trim((string)$this->input->get('q', true));
        $month = trim((string)$this->input->get('month', true));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $locationType = $this->normalize_location_filter($this->input->get('location_type', true));
        $divisionId = (int)$this->input->get('division_id', true);
        $filters = [
            'q' => $q,
            'month' => $month,
            'location_type' => $locationType,
            'division_id' => $divisionId > 0 ? $divisionId : null,
        ];
        $editOpening = null;
        $editId = (int)$this->input->get('edit', true);
        if ($editId > 0) {
            $detail = $this->Production_model->component_opening_detail($editId);
            if (($detail['ok'] ?? false) && strtoupper((string)($detail['header']['status'] ?? '')) === 'DRAFT') {
                $editOpening = $detail;
            }
        }
        $detailOpening = null;
        $detailId = (int)$this->input->get('detail', true);
        if ($detailId <= 0 && $editId > 0) {
            $detailId = $editId;
        }
        if ($detailId > 0) {
            $detail = $this->Production_model->component_opening_detail($detailId);
            if (($detail['ok'] ?? false)) {
                $detailOpening = $detail;
            }
        }
        $openingTab = strtolower(trim((string)$this->input->get('tab', true)));
        if (!in_array($openingTab, ['documents', 'detail', 'snapshot'], true)) {
            $openingTab = $detailOpening !== null ? 'detail' : 'documents';
        }
        $rows = $this->Production_model->list_component_openings($filters, 300);
        $this->render('production/component_opening_index', [
            'page_title' => 'Opening Base/Prepare',
            'rows' => $rows,
            'edit_opening' => $editOpening,
            'detail_opening' => $detailOpening,
            'opening_tab' => $openingTab,
            'q' => $q,
            'month' => $month,
            'selected_location_type' => $locationType,
            'selected_division_id' => $divisionId,
            'monthly_rows' => $this->Production_model->list_component_monthly_openings($filters, 250),
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
            'opening_month' => (string)($payload['opening_month'] ?? substr((string)($payload['opening_date'] ?? date('Y-m-d')), 0, 7)),
            'location_type' => $this->normalize_location_type($payload['location_type'] ?? ''),
            'division_id' => !empty($payload['division_id']) ? (int)$payload['division_id'] : null,
            'notes' => (string)($payload['notes'] ?? ''),
        ];
        $lines = $this->normalize_lines((array)($payload['lines'] ?? []), 'opening');
        $save = $this->Production_model->save_component_opening($header, $lines, (int)($this->current_user['employee_id'] ?? 0));
        if (!($save['ok'] ?? false)) {
            $extra = [];
            if (!empty($save['conflict']) && is_array($save['conflict'])) {
                $conflict = (array)$save['conflict'];
                $conflictId = (int)($conflict['id'] ?? 0);
                $conflictStatus = strtoupper((string)($conflict['status'] ?? ''));
                if ($conflictId > 0) {
                    $detailUrl = site_url('production/component-openings') . '?' . http_build_query([
                        'detail' => $conflictId,
                        'tab' => 'detail',
                    ]) . '#component-opening-detail-tabs';
                    $editUrl = site_url('production/component-openings') . '?' . http_build_query([
                        'edit' => $conflictId,
                        'detail' => $conflictId,
                        'tab' => 'detail',
                    ]) . '#component-opening-form-card';
                    $extra['conflict'] = [
                        'id' => $conflictId,
                        'opening_no' => (string)($conflict['opening_no'] ?? ''),
                        'status' => $conflictStatus,
                        'detail_url' => $detailUrl,
                        'edit_url' => $editUrl,
                    ];
                    if ($conflictStatus === 'DRAFT') {
                        $extra['conflict']['action'] = 'EDIT_DRAFT';
                    } elseif ($conflictStatus === 'POSTED') {
                        $extra['conflict']['action'] = 'REOPEN_OR_ADJUST';
                        $extra['conflict']['reopen_url'] = site_url('production/component-openings/reopen/' . $conflictId);
                        $extra['conflict']['adjustment_url'] = site_url('production/component-adjustments') . '?' . http_build_query([
                            'adjustment_date' => (string)($conflict['opening_date'] ?? date('Y-m-d')),
                            'location_type' => (string)($conflict['location_type'] ?? ''),
                            'division_id' => (int)($conflict['division_id'] ?? 0),
                            'notes' => 'Koreksi kekurangan opening ' . (string)($conflict['opening_no'] ?? ''),
                            'source_opening_no' => (string)($conflict['opening_no'] ?? ''),
                        ]);
                    }
                }
            }
            $this->json_error((string)($save['message'] ?? 'Gagal menyimpan opening.'), 422, $extra);
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
        $conflict = $this->Production_model->find_component_opening_month_conflict(
            $id,
            (string)($header['opening_date'] ?? date('Y-m-d')),
            strtoupper(trim((string)($header['location_type'] ?? ''))),
            !empty($header['division_id']) ? (int)$header['division_id'] : null
        );
        if ($conflict) {
            $this->json_error('Opening bulan yang sama sudah ada di dokumen ' . (string)($conflict['opening_no'] ?? ('#' . (int)($conflict['id'] ?? 0))) . '.', 422);
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
        $header['opening_date'] = substr((string)($header['opening_date'] ?? date('Y-m-d')), 0, 7) . '-01';
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
        $this->Production_model->rebuild_component_daily_rollup_from_logs();
        $this->json_ok(['id' => $id]);
    }

    public function component_opening_detail($id)
    {
        $this->require_permission('production.component.opening.index', 'view');
        $detail = $this->Production_model->component_opening_detail((int)$id);
        if (!($detail['ok'] ?? false)) {
            show_404();
            return;
        }

        $header = (array)($detail['header'] ?? []);
        $title = 'Detail Opening Component';
        if (!empty($header['opening_no'])) {
            $title .= ' ' . (string)$header['opening_no'];
        }

        $this->render('production/component_opening_detail', [
            'page_title' => $title,
            'detail' => $detail,
        ]);
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

    public function component_opening_void($id)
    {
        $this->require_permission('production.component.opening.index', 'edit');
        $result = $this->Production_model->void_component_opening((int)$id, (int)($this->current_user['employee_id'] ?? 0));
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal void opening.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id]);
    }

    public function component_opening_reopen($id)
    {
        $this->require_permission('production.component.opening.index', 'edit');
        $id = (int)$id;
        $result = $this->Production_model->reopen_component_opening_draft($id, (int)($this->current_user['employee_id'] ?? 0));
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal membuka kembali opening ke draft.'), 422);
            return;
        }

        $editUrl = site_url('production/component-openings') . '?' . http_build_query([
            'edit' => $id,
            'detail' => $id,
            'tab' => 'detail',
        ]) . '#component-opening-form-card';
        $this->json_ok([
            'id' => $id,
            'edit_url' => $editUrl,
        ]);
    }

    public function component_opening_generate_monthly()
    {
        $this->require_permission('production.component.opening.index', 'create');
        $payload = $this->request_payload();
        $result = $this->Production_model->generate_component_monthly_opname_and_opening(
            [
                'month' => (string)($payload['month'] ?? date('Y-m')),
                'location_type' => $this->normalize_location_filter($payload['location_type'] ?? ''),
                'division_id' => !empty($payload['division_id']) ? (int)$payload['division_id'] : null,
            ],
            (int)($this->current_user['employee_id'] ?? 0)
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal generate carry-forward component.'), 422);
            return;
        }
        $this->json_ok(['result' => $result['data'] ?? []]);
    }

    public function component_adjustments()
    {
        $this->require_permission('production.component.adjustment.index', 'view');
        $q = trim((string)$this->input->get('q', true));
        $rows = $this->Production_model->list_component_adjustments(['q' => $q], 300);
        $lineRows = $this->Production_model->list_component_adjustment_detail_rows(['q' => $q], 1200);
        $activeListTab = strtolower(trim((string)$this->input->get('tab', true)));
        if (!in_array($activeListTab, ['nota', 'rincian'], true)) {
            $activeListTab = 'nota';
        }
        $prefillDate = trim((string)$this->input->get('adjustment_date', true));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $prefillDate)) {
            $prefillDate = date('Y-m-d');
        }
        $prefillDivisionId = (int)$this->input->get('division_id', true);
        $prefill = [
            'adjustment_date' => $prefillDate,
            'location_type' => $this->normalize_location_type($this->input->get('location_type', true)),
            'division_id' => $prefillDivisionId > 0 ? $prefillDivisionId : 0,
            'notes' => trim((string)$this->input->get('notes', true)),
            'source_opening_no' => trim((string)$this->input->get('source_opening_no', true)),
        ];
        $this->render('production/component_adjustment_index', [
            'page_title' => 'Adjustment Base/Prepare',
            'rows' => $rows,
            'line_rows' => $lineRows,
            'active_list_tab' => $activeListTab,
            'q' => $q,
            'prefill' => $prefill,
            'location_options' => $this->location_options(),
            'components' => $this->active_components(),
            'uoms' => $this->active_uoms(),
            'divisions' => $this->active_divisions(),
        ]);
    }

    public function component_adjustment_save()
    {
        $this->require_permission('production.component.adjustment.index', 'create');
        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
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
        $this->db->db_debug = $dbDebugBefore;
        if (!($save['ok'] ?? false)) {
            $this->json_error((string)($save['message'] ?? 'Gagal menyimpan adjustment.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$save['id']]);
    }

    public function component_stock_snapshot()
    {
        $canAccess = $this->can('production.component.adjustment.index', 'view')
            || $this->can('production.component.daily.index', 'view')
            || $this->can('production.component.stock.index', 'view')
            || $this->can('production.component.batch.index', 'view');
        if (!$canAccess) {
            $this->json_error('Anda tidak memiliki izin untuk melihat snapshot stok component.', 403);
            return;
        }

        $componentId = (int)$this->input->get('component_id', true);
        if ($componentId <= 0) {
            $this->json_error('Component wajib dipilih.', 422);
            return;
        }

        $uomId = (int)$this->input->get('uom_id', true);
        $divisionId = (int)$this->input->get('division_id', true);
        $lotId = (int)$this->input->get('lot_id', true);
        $snapshot = $this->Production_model->component_stock_snapshot(
            $componentId,
            $uomId,
            $divisionId > 0 ? $divisionId : null,
            $this->normalize_location_type($this->input->get('location_type', true)),
            $lotId > 0 ? $lotId : null
        );

        $this->json_ok(['snapshot' => $snapshot]);
    }

    public function component_adjustment_post($id)
    {
        $this->require_permission('production.component.adjustment.index', 'edit');
        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        $id = (int)$id;
        $header = $this->Production_model->get_component_adjustment($id);
        if (!$header) {
            $this->db->db_debug = $dbDebugBefore;
            $this->json_error('Adjustment tidak ditemukan.', 404);
            return;
        }
        if (strtoupper((string)$header['status']) !== 'DRAFT') {
            $this->db->db_debug = $dbDebugBefore;
            $this->json_error('Hanya adjustment DRAFT yang bisa diposting.', 422);
            return;
        }

        $lines = $this->Production_model->get_component_adjustment_lines($id);
        if (empty($header['division_id'])) {
            $resolvedDivision = $this->Production_model->resolve_component_adjustment_division($lines);
            if (!($resolvedDivision['ok'] ?? false)) {
                $this->db->db_debug = $dbDebugBefore;
                $this->json_error((string)($resolvedDivision['message'] ?? 'Divisi adjustment tidak bisa ditentukan untuk posting.'), 422);
                return;
            }
            $header['division_id'] = (int)($resolvedDivision['division_id'] ?? 0);
            $this->db->where('id', $id)->update('inv_component_adjustment', [
                'division_id' => $header['division_id'],
            ]);
        }
        $post = $this->componentstockwriter->post_adjustment($header, $lines, (int)($this->current_user['employee_id'] ?? 0));
        $this->db->db_debug = $dbDebugBefore;
        if (!($post['ok'] ?? false)) {
            $this->json_error((string)($post['message'] ?? 'Posting adjustment gagal.'), 422);
            return;
        }
        $this->db->where('id', $id)->update('inv_component_adjustment', [
            'status' => 'POSTED',
            'posted_at' => date('Y-m-d H:i:s'),
            'posted_by' => !empty($this->current_user['employee_id']) ? (int)$this->current_user['employee_id'] : null,
        ]);
        $this->Production_model->rebuild_component_daily_rollup_from_logs();
        $this->json_ok(['id' => $id]);
    }

    public function component_adjustment_void($id)
    {
        $this->require_permission('production.component.adjustment.index', 'delete');
        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Production_model->void_component_adjustment((int)$id, (int)($this->current_user['employee_id'] ?? 0));
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal VOID adjustment.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id]);
    }

    public function component_adjustment_delete($id)
    {
        $this->require_permission('production.component.adjustment.index', 'delete');
        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        $result = $this->Production_model->delete_draft_doc('inv_component_adjustment', 'inv_component_adjustment_line', 'adjustment_id', (int)$id);
        $this->db->db_debug = $dbDebugBefore;
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
            'materials' => $this->active_materials(),
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
            'scaling_mode' => (string)($payload['scaling_mode'] ?? 'BATCH'),
            'batch_count' => (float)($payload['batch_count'] ?? 0),
            'reference_line_no' => (int)($payload['reference_line_no'] ?? 0),
            'reference_actual_qty' => (float)($payload['reference_actual_qty'] ?? 0),
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

    public function component_batch_preview()
    {
        $this->require_permission('production.component.batch.index', 'view');
        $payload = $this->request_payload();
        if (empty($payload)) {
            $payload = [
                'component_id' => (int)$this->input->get('component_id', true),
                'location_type' => (string)$this->input->get('location_type', true),
                'scaling_mode' => (string)$this->input->get('scaling_mode', true),
                'batch_count' => (float)$this->input->get('batch_count', true),
                'reference_line_no' => (int)$this->input->get('reference_line_no', true),
                'reference_actual_qty' => (float)$this->input->get('reference_actual_qty', true),
            ];
        }
        $preview = $this->Production_model->component_batch_preview([
            'component_id' => (int)($payload['component_id'] ?? 0),
            'location_type' => (string)($payload['location_type'] ?? ''),
            'scaling_mode' => (string)($payload['scaling_mode'] ?? 'BATCH'),
            'batch_count' => (float)($payload['batch_count'] ?? 0),
            'reference_line_no' => (int)($payload['reference_line_no'] ?? 0),
            'reference_actual_qty' => (float)($payload['reference_actual_qty'] ?? 0),
        ]);
        if (!($preview['ok'] ?? false)) {
            $this->json_error((string)($preview['message'] ?? 'Preview batch gagal.'), 422);
            return;
        }
        $this->json_ok($preview);
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

    public function component_batch_void($id)
    {
        $this->require_permission('production.component.batch.index', 'delete');
        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $result = $this->Production_model->void_component_batch((int)$id, (int)($this->current_user['employee_id'] ?? 0));
        } finally {
            $this->db->db_debug = $dbDebugBefore;
        }
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal VOID batch.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id]);
    }

    public function component_batch_usage($id)
    {
        $this->require_permission('production.component.batch.index', 'view');
        $detail = $this->Production_model->component_batch_usage_detail((int)$id);
        if (!($detail['ok'] ?? false)) {
            $this->json_error((string)($detail['message'] ?? 'Detail pemakaian batch tidak ditemukan.'), 404);
            return;
        }
        $this->json_ok($detail);
    }

    public function component_batch_usage_page($id)
    {
        $this->require_permission('production.component.batch.index', 'view');
        $detail = $this->Production_model->component_batch_usage_detail((int)$id);
        if (!($detail['ok'] ?? false)) {
            show_404();
            return;
        }

        $header = (array)($detail['header'] ?? []);
        $title = 'Detail Usage Batch';
        if (!empty($header['batch_no'])) {
            $title .= ' ' . (string)$header['batch_no'];
        }

        $this->render('production/component_batch_usage_detail', [
            'page_title' => $title,
            'detail' => $detail,
        ]);
    }

    public function component_picker_search()
    {
        $canAccess = $this->can('production.component.opening.index', 'view')
            || $this->can('production.component.adjustment.index', 'view')
            || $this->can('production.component.batch.index', 'view')
            || $this->can('production.component.category.index', 'view')
            || $this->can('production.component.master.index', 'view')
            || $this->can('production.component.formula.index', 'view');
        if (!$canAccess) {
            $this->json_error('Anda tidak memiliki izin untuk pencarian component.', 403);
            return;
        }

        $entity = strtoupper(trim((string)$this->input->get('entity', true)));
        if (!in_array($entity, ['COMPONENT', 'MATERIAL'], true)) {
            $entity = 'COMPONENT';
        }
        $q = trim((string)$this->input->get('q', true));
        $limit = (int)$this->input->get('limit', true);
        if ($limit <= 0 || $limit > 50) {
            $limit = 20;
        }
        $excludeId = (int)$this->input->get('exclude_id', true);
        $componentType = strtoupper(trim((string)$this->input->get('component_type', true)));
        $divisionId = (int)$this->input->get('division_id', true);
        $locationType = $this->normalize_location_type($this->input->get('location_type', true));

        $rows = $this->Production_model->search_picker_options($entity, $q, $limit, [
            'exclude_id' => $excludeId,
            'component_type' => $componentType,
            'division_id' => $divisionId > 0 ? $divisionId : null,
            'location_type' => $locationType,
        ]);
        $this->json_ok(['rows' => $rows]);
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

    public function component_master_usage($componentId)
    {
        $this->require_permission('production.component.master.index', 'view');
        $componentId = (int)$componentId;
        if ($componentId <= 0) {
            show_error('Component tidak valid.', 422, 'Invalid Request');
            return;
        }
        $detail = $this->Production_model->component_usage_detail($componentId);
        if (!($detail['ok'] ?? false)) {
            show_error((string)($detail['message'] ?? 'Pemakaian component tidak ditemukan.'), 404, 'Not Found');
            return;
        }
        $this->render('production/component_usage_detail', [
            'page_title' => 'Pemakaian Component',
            'detail' => $detail,
        ]);
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
            'location_type' => $this->normalize_location_filter($this->input->get('location_type', true)),
            'type' => $this->normalize_component_type_filter($this->input->get('type', true)),
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
            'location_type' => $this->normalize_location_filter($this->input->get('location_type', true)),
            'movement_type' => strtoupper(trim((string)$this->input->get('movement_type', true))),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'type' => $this->normalize_component_type_filter($this->input->get('type', true)),
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
            'location_type' => $this->normalize_location_filter($this->input->get('location_type', true)),
            'division_id' => (int)$this->input->get('division_id', true),
            'type' => $this->normalize_component_type_filter($this->input->get('type', true)),
        ];
    }

    private function lot_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['OPEN', 'CLOSED', 'VOID', 'ALL'], true)) {
            $status = 'OPEN';
        }

        return [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => $status,
            'location_type' => $this->normalize_location_filter($this->input->get('location_type', true)),
            'division_id' => (int)$this->input->get('division_id', true),
            'type' => $this->normalize_component_type_filter($this->input->get('type', true)),
        ];
    }

    private function normalize_component_type_filter($value): string
    {
        $value = strtoupper(trim((string)$value));
        return in_array($value, ['BASE', 'PREPARE'], true) ? $value : '';
    }
    private function normalize_location_filter($value)
    {
        $value = strtoupper(trim((string)$value));
        if (in_array($value, ['REGULER', 'EVENT'], true)) {
            return $value;
        }
        if (in_array($value, ['BAR', 'KITCHEN'], true)) {
            return 'REGULER';
        }
        if (in_array($value, ['BAR_EVENT', 'KITCHEN_EVENT'], true)) {
            return 'EVENT';
        }
        return '';
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
        return $this->db->select('c.id, c.component_code, c.component_name, c.uom_id, c.component_type, c.operational_division_id, u.code AS uom_code, d.code AS division_code, d.name AS division_name')
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
        return $this->db->select('m.id, m.material_code, m.material_name, m.content_uom_id, u.code AS content_uom_code')
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
                    'selected_lot_id' => !empty($line['selected_lot_id']) ? (int)$line['selected_lot_id'] : null,
                    'available_qty' => round((float)($line['available_qty'] ?? 0), 4),
                    'qty_spoil' => round((float)($line['qty_spoil'] ?? 0), 4),
                    'spoil_reason_code' => (string)($line['spoil_reason_code'] ?? ''),
                    'qty_waste' => round((float)($line['qty_waste'] ?? 0), 4),
                    'waste_reason_code' => (string)($line['waste_reason_code'] ?? ''),
                    'qty_adjust_pos' => round((float)($line['qty_adjust_pos'] ?? 0), 4),
                    'adjustment_plus_reason_code' => (string)($line['adjustment_plus_reason_code'] ?? ''),
                    'qty_adjust_neg' => round((float)($line['qty_adjust_neg'] ?? 0), 4),
                    'adjustment_minus_reason_code' => (string)($line['adjustment_minus_reason_code'] ?? ''),
                    'unit_cost' => round((float)($line['unit_cost'] ?? 0), 6),
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
