<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Production extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Production_model');
        $this->load->library('ComponentStockWriter');
        $this->load->helper('component_adjustment_reason');
    }

    private function release_session_lock(): void
    {
        if (function_exists('session_write_close')) {
            @session_write_close();
        }
    }

    public function component_stock()
    {
        $this->require_permission('production.component.stock.index', 'view');
        $filters = $this->stock_filters();
        $rows = $this->Production_model->component_stock_rows($filters, 500);

        $this->render('production/component_stock_index', [
            'page_title'       => 'Stok Base/Prepare',
            'rows'             => $rows,
            'filters'          => $filters,
            'location_options' => $this->location_options(),
            'divisions'        => $this->active_divisions(),
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
        $rows = $this->Production_model->component_movement_rows($filters, 500);

        $this->render('production/component_movement_index', [
            'page_title'       => 'Mutasi Base/Prepare',
            'rows'             => $rows,
            'filters'          => $filters,
            'location_options' => $this->location_options(),
            'divisions'        => $this->active_divisions(),
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

    public function component_reconcile()
    {
        $pageCode = $this->can('production.component.reconcile.index', 'view')
            ? 'production.component.reconcile.index'
            : 'production.component.daily.index';
        $this->require_permission($pageCode, 'view');
        $filters = $this->component_reconcile_filters();
        $compare = $this->Production_model->component_reconcile_rows($filters, 500);

        $this->render('production/component_reconcile_index', [
            'page_title' => 'Rekonsiliasi Base/Prepare',
            'active_menu' => 'production.component.reconcile',
            'filters' => $filters,
            'rows' => $compare['rows'] ?? [],
            'summary' => $compare['summary'] ?? [],
            'as_of_date' => $compare['as_of_date'] ?? ($filters['as_of_date'] ?? date('Y-m-d')),
            'location_options' => $this->location_options(),
            'divisions' => $this->active_divisions(),
        ]);
    }

    public function component_reconcile_audit()
    {
        $pageCode = $this->can('production.component.reconcile.index', 'view')
            ? 'production.component.reconcile.index'
            : 'production.component.daily.index';
        $this->require_permission($pageCode, 'view');
        $filters = $this->component_reconcile_filters();
        $result = $this->Production_model->component_reconcile_audit((string)($filters['as_of_date'] ?? ''), $filters);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Audit reconcile component gagal.'), 422);
            return;
        }
        $this->json_ok($result);
    }

    public function component_reconcile_repair()
    {
        $pageCode = $this->can('production.component.reconcile.index', 'edit')
            ? 'production.component.reconcile.index'
            : 'production.component.daily.index';
        $this->require_permission($pageCode, 'edit');
        $payload = json_decode((string)$this->input->raw_input_stream, true);
        if (!is_array($payload)) {
            $payload = $this->input->post(null, true) ?: [];
        }

        $result = $this->Production_model->repair_component_reconcile([
            'location_type' => $this->normalize_location_filter($payload['location_type'] ?? ''),
            'division_id' => (int)($payload['division_id'] ?? 0),
            'component_id' => (int)($payload['component_id'] ?? 0),
            'uom_id' => (int)($payload['uom_id'] ?? 0),
        ]);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Repair reconcile component gagal.'), 422, $result['data'] ?? []);
            return;
        }
        $this->json_ok($result);
    }

    public function component_reconcile_repair_all()
    {
        $pageCode = $this->can('production.component.reconcile.index', 'edit')
            ? 'production.component.reconcile.index'
            : 'production.component.daily.index';
        $this->require_permission($pageCode, 'edit');
        $payload = json_decode((string)$this->input->raw_input_stream, true);
        if (!is_array($payload)) {
            $payload = $this->input->post(null, true) ?: [];
        }

        $result = $this->Production_model->repair_all_component_identities([
            'location_type' => $this->normalize_location_filter($payload['location_type'] ?? ''),
            'division_id'   => !empty($payload['division_id']) ? (int)$payload['division_id'] : null,
        ]);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Repair All gagal.'), 422, $result['data'] ?? []);
            return;
        }
        $this->json_ok($result);
    }

    public function component_lot_repair()
    {
        $pageCode = $this->can('production.component.reconcile.index', 'edit')
            ? 'production.component.reconcile.index'
            : 'production.component.daily.index';
        $this->require_permission($pageCode, 'edit');
        $payload     = $this->request_payload();
        $componentId = (int)($payload['component_id'] ?? 0);
        $uomId       = (int)($payload['uom_id']       ?? 0);
        $locationType = strtoupper(trim((string)($payload['location_type'] ?? '')));
        $divisionId  = isset($payload['division_id']) && $payload['division_id'] !== null ? (int)$payload['division_id'] : null;

        if ($componentId <= 0 || $uomId <= 0) {
            $this->json_error('component_id dan uom_id wajib diisi.', 422);
            return;
        }
        if (!$this->db->table_exists('inv_component_lot')) {
            $this->json_error('Tabel inv_component_lot belum tersedia.', 422);
            return;
        }

        $snapshotFilters = [
            'as_of_date' => date('Y-m-d'),
            'location_type' => $locationType,
            'division_id' => $divisionId ?? 0,
            'component_id' => $componentId,
            'uom_id' => $uomId,
        ];
        $beforeCompare = $this->Production_model->component_reconcile_rows($snapshotFilters, 500);
        $beforeRow = null;
        foreach ((array)($beforeCompare['rows'] ?? []) as $candidate) {
            if (
                strtoupper((string)($candidate['location_type'] ?? '')) === $locationType
                && (int)($candidate['component_id'] ?? 0) === $componentId
                && (int)($candidate['uom_id'] ?? 0) === $uomId
                && (int)($candidate['division_id'] ?? 0) === (int)($divisionId ?? 0)
            ) {
                $beforeRow = $candidate;
                break;
            }
        }

        $this->db->where('component_id', $componentId)->where('uom_id', $uomId);
        if ($locationType !== '') $this->db->where('location_type', $locationType);
        if ($divisionId !== null) $this->db->where('division_id', $divisionId);
        else $this->db->where('division_id IS NULL', null, false);
        $lots = $this->db->get('inv_component_lot')->result_array();

        if (empty($lots)) {
            $this->json_ok(['repaired' => 0], 'Tidak ada lot ditemukan untuk identity ini.');
            return;
        }
        $repaired = 0;
        foreach ($lots as $lot) {
            $newBalance = round((float)$lot['qty_in_total'] - (float)$lot['qty_out_total'], 4);
            $newStatus  = $newBalance > 0.0001 ? 'OPEN' : 'CLOSED';
            $this->db->where('id', (int)$lot['id'])->update('inv_component_lot', [
                'qty_balance' => $newBalance,
                'status'      => $newStatus,
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
            $repaired++;
        }

        $afterCompare = $this->Production_model->component_reconcile_rows($snapshotFilters, 500);
        $afterRow = null;
        foreach ((array)($afterCompare['rows'] ?? []) as $candidate) {
            if (
                strtoupper((string)($candidate['location_type'] ?? '')) === $locationType
                && (int)($candidate['component_id'] ?? 0) === $componentId
                && (int)($candidate['uom_id'] ?? 0) === $uomId
                && (int)($candidate['division_id'] ?? 0) === (int)($divisionId ?? 0)
            ) {
                $afterRow = $candidate;
                break;
            }
        }

        $monthlyQty = round((float)($afterRow['monthly_qty'] ?? $beforeRow['monthly_qty'] ?? 0), 4);
        $movementQty = round((float)($afterRow['movement_qty'] ?? $beforeRow['movement_qty'] ?? 0), 4);
        $beforeLotQty = round((float)($beforeRow['lot_qty'] ?? 0), 4);
        $afterLotQty = round((float)($afterRow['lot_qty'] ?? 0), 4);
        $remainingLotVsMovement = round($afterLotQty - $movementQty, 4);

        $message = $repaired . ' lot berhasil dinormalisasi (qty_balance = qty_in - qty_out).';
        if (abs($remainingLotVsMovement) > 0.0001) {
            $message .= ' Setelah normalisasi, Lot masih selisih '
                . number_format($remainingLotVsMovement, 4, '.', '')
                . ' terhadap movement.'
                . ' Ini berarti masalahnya bukan di rumus qty_balance, tetapi di histori lot issue / fallback.'
                . ' Langkah berikutnya: audit lot adjustment atau repair histori issue, bukan ulang Repair Lot FIFO.';
        }

        $this->json_ok([
            'repaired' => $repaired,
            'before' => [
                'monthly_qty' => $monthlyQty,
                'movement_qty' => $movementQty,
                'lot_qty' => $beforeLotQty,
            ],
            'after' => [
                'monthly_qty' => $monthlyQty,
                'movement_qty' => $movementQty,
                'lot_qty' => $afterLotQty,
                'delta_lot_vs_movement' => $remainingLotVsMovement,
            ],
        ], $message);
    }

    /** Sync a single component's FIFO lot total to match its monthly stock balance. */
    public function component_lot_sync_to_stock()
    {
        $pageCode = $this->can('production.component.reconcile.index', 'edit')
            ? 'production.component.reconcile.index'
            : 'production.component.daily.index';
        $this->require_permission($pageCode, 'edit');

        $payload      = $this->request_payload();
        $locationType = strtoupper(trim((string)($payload['location_type'] ?? '')));
        $divisionId   = isset($payload['division_id']) && $payload['division_id'] !== null ? (int)$payload['division_id'] : null;
        $componentId  = (int)($payload['component_id'] ?? 0);
        $uomId        = (int)($payload['uom_id'] ?? 0);

        if ($componentId <= 0 || $uomId <= 0) {
            $this->json_error('component_id dan uom_id wajib diisi.', 422);
            return;
        }

        $result = $this->Production_model->repair_component_lot_balance_to_stock($locationType, $divisionId, $componentId, $uomId);
        if (!empty($result['ok'])) {
            $this->json_ok($result['data'] ?? [], (string)($result['message'] ?? 'Selesai.'));
        } else {
            $this->json_error((string)($result['message'] ?? 'Gagal.'), 422);
        }
    }

    /** Sync ALL component FIFO lots that over-state stock (lot > monthly_stock) to match stock. */
    public function component_lot_sync_all()
    {
        $pageCode = $this->can('production.component.reconcile.index', 'edit')
            ? 'production.component.reconcile.index'
            : 'production.component.daily.index';
        $this->require_permission($pageCode, 'edit');

        $payload = $this->request_payload();
        $filters = [
            'as_of_date'    => trim((string)($payload['as_of_date'] ?? date('Y-m-d'))),
            'location_type' => strtoupper(trim((string)($payload['location_type'] ?? ''))),
            'division_id'   => !empty($payload['division_id']) ? (int)$payload['division_id'] : 0,
            'q'             => trim((string)($payload['q'] ?? '')),
            'type'          => strtoupper(trim((string)($payload['type'] ?? ''))),
        ];

        $compare = $this->Production_model->component_reconcile_rows($filters, 2000);
        $allRows = is_array($compare['rows'] ?? null) ? $compare['rows'] : [];

        // Only process rows where lot_qty > balance_qty (lot over-states stock)
        $toRepair = array_values(array_filter($allRows, static function (array $r): bool {
            return round((float)($r['lot_qty'] ?? 0), 4) - round((float)($r['balance_qty'] ?? 0), 4) > 0.01;
        }));

        if (empty($toRepair)) {
            $this->json_ok(['processed' => 0, 'repaired' => 0, 'skipped' => 0, 'failed' => 0, 'results' => []],
                'Tidak ada lot yang melebihi stok, tidak ada yang perlu disesuaikan.');
            return;
        }

        $repaired = 0; $skipped = 0; $failed = 0; $results = [];
        foreach ($toRepair as $row) {
            $locType  = strtoupper((string)($row['location_type'] ?? ''));
            $divId    = $row['division_id'] !== null ? (int)$row['division_id'] : null;
            $compId   = (int)($row['component_id'] ?? 0);
            $uom      = (int)($row['uom_id'] ?? 0);
            $label    = trim((string)($row['component_name'] ?? 'Component #' . $compId));

            if ($compId <= 0 || $uom <= 0) {
                $failed++;
                $results[] = ['label' => $label, 'status' => 'skipped', 'message' => 'Data tidak lengkap.'];
                continue;
            }
            $r = $this->Production_model->repair_component_lot_balance_to_stock($locType, $divId, $compId, $uom);
            if (!empty($r['ok'])) {
                if (!empty($r['skipped'])) {
                    $skipped++;
                    $results[] = ['label' => $label, 'status' => 'skipped', 'message' => (string)($r['message'] ?? '')];
                } else {
                    $repaired++;
                    $results[] = ['label' => $label, 'status' => 'repaired'];
                }
            } else {
                $failed++;
                $results[] = ['label' => $label, 'status' => 'failed', 'message' => (string)($r['message'] ?? '')];
            }
        }

        $total   = count($toRepair);
        $message = "Repair Lot Semua selesai: {$repaired} disesuaikan, {$skipped} dilewati, {$failed} gagal dari {$total} item.";
        if ($failed > 0) {
            $this->json_ok(['processed' => $total, 'repaired' => $repaired, 'skipped' => $skipped, 'failed' => $failed, 'results' => $results], $message);
        } else {
            $this->json_ok(['processed' => $total, 'repaired' => $repaired, 'skipped' => $skipped, 'failed' => 0, 'results' => $results], $message);
        }
    }

    public function component_lot_only_adjust()
    {
        $pageCode = $this->can('production.component.reconcile.index', 'edit')
            ? 'production.component.reconcile.index'
            : 'production.component.daily.index';
        $this->require_permission($pageCode, 'edit');

        $payload = $this->request_payload();
        $lotId = (int)($payload['lot_id'] ?? 0);
        $componentId = (int)($payload['component_id'] ?? 0);
        $uomId = (int)($payload['uom_id'] ?? 0);
        $divisionId = !empty($payload['division_id']) ? (int)$payload['division_id'] : null;
        $divisionCode = strtoupper(trim((string)($payload['division_code'] ?? '')));
        $locationGroup = strtoupper(trim((string)($payload['location_type'] ?? 'REGULER')));
        $targetQty = round((float)($payload['target_qty'] ?? $payload['physical_qty'] ?? 0), 4);
        $adjustDate = trim((string)($payload['adjustment_date'] ?? $payload['opname_date'] ?? date('Y-m-d')));
        $notes = trim((string)($payload['notes'] ?? ''));
        $unitCostInput = round((float)($payload['unit_cost'] ?? 0), 6);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $adjustDate)) {
            $adjustDate = date('Y-m-d');
        }

        $specificLocation = '';
        if ($divisionCode === 'BAR') {
            $specificLocation = $locationGroup === 'EVENT' ? 'BAR_EVENT' : 'BAR';
        } elseif ($divisionCode === 'KITCHEN') {
            $specificLocation = $locationGroup === 'EVENT' ? 'KITCHEN_EVENT' : 'KITCHEN';
        }

        $lot = null;
        $activeLots = [];
        $identityCurrentQty = 0.0;
        $isAggregateIdentityAdjust = ($lotId <= 0);
        if ($lotId > 0) {
            $lot = $this->db->where('id', $lotId)->get('inv_component_lot')->row_array();
            if (!$lot) {
                $this->json_error('Lot component tidak ditemukan.', 404);
                return;
            }
        } else {
            if ($componentId <= 0 || $uomId <= 0) {
                $this->json_error('lot_id atau identity component wajib diisi untuk adjustment lot.', 422);
                return;
            }
            if ($specificLocation === '' || empty($divisionId)) {
                $this->json_error('Lokasi lot component tidak dapat ditentukan (divisi harus BAR atau KITCHEN).', 422);
                return;
            }

            $activeLots = $this->db
                ->where('component_id', $componentId)
                ->where('uom_id', $uomId)
                ->where('location_type', $specificLocation)
                ->where('division_id', $divisionId)
                ->where('qty_balance >', 0)
                ->order_by('receipt_date', 'ASC')
                ->order_by('id', 'ASC')
                ->get('inv_component_lot')
                ->result_array();

            if (!empty($activeLots)) {
                $lot = $activeLots[0];
                foreach ($activeLots as $activeLot) {
                    $identityCurrentQty += round((float)($activeLot['qty_balance'] ?? 0), 4);
                }
            }

            if (!$lot && $targetQty < -0.0001) {
                $this->json_error('Belum ada lot aktif untuk identity ini. Target lot tidak boleh minus.', 422);
                return;
            }
        }

        $currentQty = $isAggregateIdentityAdjust
            ? round($identityCurrentQty, 4)
            : round((float)($lot['qty_balance'] ?? 0), 4);
        $delta = round($targetQty - $currentQty, 4);
        if (abs($delta) < 0.0001) {
            $this->json_error('Saldo target sama dengan saldo lot saat ini.', 422);
            return;
        }

        $now = date('Y-m-d H:i:s');
        $actorEmployeeId = (int)($this->current_user['employee_id'] ?? ($this->current_user['id'] ?? 0));
        $baseLotId = (int)($lot['id'] ?? 0);
        $baseComponentId = (int)($lot['component_id'] ?? $componentId);
        $baseUomId = (int)($lot['uom_id'] ?? $uomId);
        $baseDivisionId = !empty($lot['division_id']) ? (int)$lot['division_id'] : $divisionId;
        $baseLocationType = (string)($lot['location_type'] ?? $specificLocation);

        $this->db->trans_start();
        if ($delta < 0) {
            if ($isAggregateIdentityAdjust) {
                $remainingOutQty = round(abs($delta), 4);
                if ($remainingOutQty > $currentQty + 0.0001) {
                    $this->db->trans_complete();
                    $this->json_error('Saldo lot aktif tidak cukup untuk adjustment minus pada identity ini.', 422);
                    return;
                }

                foreach ($activeLots as $activeLot) {
                    if ($remainingOutQty <= 0.0001) {
                        break;
                    }

                    $lotBalance = round((float)($activeLot['qty_balance'] ?? 0), 4);
                    if ($lotBalance <= 0.0001) {
                        continue;
                    }

                    $outQty = round(min($lotBalance, $remainingOutQty), 4);
                    if ($outQty <= 0.0001) {
                        continue;
                    }

                    $issueNo = 'ICLADJ' . date('YmdHis') . substr(md5((string)$activeLot['id'] . '|' . $now . '|' . $remainingOutQty), 0, 6);
                    $lotUnitCost = round((float)($activeLot['unit_cost'] ?? 0), 6);
                    $totalCost = round($outQty * $lotUnitCost, 2);
                    $lotTargetBalance = round($lotBalance - $outQty, 4);

                    $this->db->insert('inv_component_lot_issue_log', [
                        'issue_no' => $issueNo,
                        'issue_date' => $adjustDate,
                        'issue_datetime' => $now,
                        'location_type' => (string)$activeLot['location_type'],
                        'division_id' => !empty($activeLot['division_id']) ? (int)$activeLot['division_id'] : $baseDivisionId,
                        'component_id' => (int)$activeLot['component_id'],
                        'uom_id' => (int)$activeLot['uom_id'],
                        'issue_qty' => $outQty,
                        'total_cost' => $totalCost,
                        'source_module' => 'COMPONENT_RECONCILE',
                        'source_table' => 'component_lot_manual_adjustment',
                        'source_id' => (int)$activeLot['id'],
                        'source_line_id' => null,
                        'notes' => 'Lot-only adjustment from component reconcile' . ($notes !== '' ? ': ' . $notes : ''),
                        'status' => 'POSTED',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $issueId = (int)$this->db->insert_id();

                    $this->db->insert('inv_component_lot_issue_line', [
                        'issue_id' => $issueId,
                        'lot_id' => (int)$activeLot['id'],
                        'qty_out' => $outQty,
                        'unit_cost' => $lotUnitCost,
                        'total_cost' => $totalCost,
                        'source_balance_before' => $lotBalance,
                        'source_balance_after' => $lotTargetBalance,
                        'created_at' => $now,
                    ]);

                    $this->db->where('id', (int)$activeLot['id'])->update('inv_component_lot', [
                        'qty_out_total' => round((float)($activeLot['qty_out_total'] ?? 0) + $outQty, 4),
                        'qty_balance' => $lotTargetBalance,
                        'last_issue_at' => $now,
                        'status' => $lotTargetBalance > 0.0001 ? 'OPEN' : 'CLOSED',
                        'updated_at' => $now,
                    ]);

                    $remainingOutQty = round($remainingOutQty - $outQty, 4);
                }
            } else {
                if ($baseLotId <= 0 || !$lot) {
                    $this->db->trans_complete();
                    $this->json_error('Lot aktif tidak ditemukan untuk adjustment minus.', 422);
                    return;
                }
                $outQty = round(abs($delta), 4);
                $issueNo = 'ICLADJ' . date('YmdHis') . substr(md5((string)$baseLotId . '|' . $now), 0, 6);
                $totalCost = round($outQty * (float)($lot['unit_cost'] ?? 0), 2);

                $this->db->insert('inv_component_lot_issue_log', [
                    'issue_no' => $issueNo,
                    'issue_date' => $adjustDate,
                    'issue_datetime' => $now,
                    'location_type' => $baseLocationType,
                    'division_id' => $baseDivisionId,
                    'component_id' => $baseComponentId,
                    'uom_id' => $baseUomId,
                    'issue_qty' => $outQty,
                    'total_cost' => $totalCost,
                    'source_module' => 'COMPONENT_RECONCILE',
                    'source_table' => 'component_lot_manual_adjustment',
                    'source_id' => $baseLotId,
                    'source_line_id' => null,
                    'notes' => 'Lot-only adjustment from component reconcile' . ($notes !== '' ? ': ' . $notes : ''),
                    'status' => 'POSTED',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $issueId = (int)$this->db->insert_id();

                $this->db->insert('inv_component_lot_issue_line', [
                    'issue_id' => $issueId,
                    'lot_id' => $baseLotId,
                    'qty_out' => $outQty,
                    'unit_cost' => round((float)($lot['unit_cost'] ?? 0), 6),
                    'total_cost' => $totalCost,
                    'source_balance_before' => $currentQty,
                    'source_balance_after' => $targetQty,
                    'created_at' => $now,
                ]);

                $this->db->where('id', $baseLotId)->update('inv_component_lot', [
                    'qty_out_total' => round((float)($lot['qty_out_total'] ?? 0) + $outQty, 4),
                    'qty_balance' => $targetQty,
                    'last_issue_at' => $now,
                    'status' => $targetQty > 0.0001 ? 'OPEN' : 'CLOSED',
                    'updated_at' => $now,
                ]);
            }
        } else {
            $inQty = round($delta, 4);
            $unitCost = $unitCostInput > 0 ? $unitCostInput : round((float)($lot['unit_cost'] ?? 0), 6);
            if ($unitCost <= 0 && $baseComponentId > 0 && $baseUomId > 0 && $baseLocationType !== '') {
                $fallbackMonthly = $this->db
                    ->select('avg_cost')
                    ->from('inv_component_monthly_stock')
                    ->where('component_id', $baseComponentId)
                    ->where('uom_id', $baseUomId)
                    ->where('location_type', $baseLocationType)
                    ->where('division_id', $baseDivisionId)
                    ->order_by('month_key', 'DESC')
                    ->limit(1)
                    ->get()
                    ->row_array();
                $unitCost = round((float)($fallbackMonthly['avg_cost'] ?? 0), 6);
            }
            if ($unitCost <= 0 && $baseComponentId > 0 && $baseUomId > 0 && $baseLocationType !== '') {
                $fallbackLot = $this->db
                    ->select('unit_cost')
                    ->from('inv_component_lot')
                    ->where('component_id', $baseComponentId)
                    ->where('uom_id', $baseUomId)
                    ->where('location_type', $baseLocationType)
                    ->where('division_id', $baseDivisionId)
                    ->where('unit_cost >', 0)
                    ->order_by('receipt_date', 'DESC')
                    ->order_by('id', 'DESC')
                    ->limit(1)
                    ->get()
                    ->row_array();
                $unitCost = round((float)($fallbackLot['unit_cost'] ?? 0), 6);
            }
            if ($unitCost <= 0) {
                $this->db->trans_complete();
                $this->json_error('Unit cost wajib diisi untuk adjustment lot plus.', 422);
                return;
            }
            $lotNo = 'ICLADJ-' . date('YmdHis') . '-' . $baseComponentId . '-' . max($baseLotId, 0) . '-' . substr(md5((string)microtime(true) . '|' . $baseComponentId . '|' . $actorEmployeeId), 0, 6);
            $this->db->insert('inv_component_lot', [
                'location_type' => $baseLocationType,
                'division_id' => $baseDivisionId,
                'component_id' => $baseComponentId,
                'uom_id' => $baseUomId,
                'lot_no' => substr($lotNo, 0, 64),
                'receipt_date' => $adjustDate,
                'expiry_date' => null,
                'unit_cost' => $unitCost,
                'qty_in_total' => $inQty,
                'qty_out_total' => 0,
                'qty_balance' => $inQty,
                'source_module' => 'COMPONENT_RECONCILE',
                'source_table' => 'component_lot_manual_adjustment',
                'source_id' => $baseLotId > 0 ? $baseLotId : null,
                'source_line_id' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
                'parent_lot_id' => $baseLotId > 0 ? $baseLotId : null,
                'last_issue_at' => null,
                'status' => 'OPEN',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            $dbError = $this->db->error();
            $dbMessage = trim((string)($dbError['message'] ?? ''));
            $this->json_error('Gagal menyimpan adjustment lot.' . ($dbMessage !== '' ? ' DB: ' . $dbMessage : ''), 422);
            return;
        }

        $this->json_ok([
            'lot_id' => $baseLotId,
            'before_qty' => $currentQty,
            'after_qty' => $targetQty,
            'delta_qty' => $delta,
        ], 'Adjustment lot berhasil disimpan. Monthly stock dan movement log tidak diubah.');
    }

    public function component_lots()
    {
        $pageCode = $this->can('production.component.lot.index', 'view')
            ? 'production.component.lot.index'
            : 'production.component.batch.index';
        $this->require_permission($pageCode, 'view');
        $filters = $this->lot_filters();

        $this->load->library('ComponentLotManager');
        $lotReady = $this->componentlotmanager->ensureReady();
        if (!($lotReady['ok'] ?? false)) {
            show_error((string)($lotReady['message'] ?? 'Schema lot component gagal disiapkan.'));
            return;
        }

        $rows = $this->componentlotmanager->listLots($filters, 500);
        $this->render('production/component_lot_index', [
            'page_title' => 'Lot FIFO Base/Prepare',
            'active_menu' => 'production.component.lot',
            'rows' => $rows,
            'filters' => $filters,
            'divisions' => $this->active_divisions(),
        ]);
    }

    public function component_lot_usage($lotId)
    {
        $pageCode = $this->can('production.component.lot.index', 'view')
            ? 'production.component.lot.index'
            : 'production.component.batch.index';
        $this->require_permission($pageCode, 'view');
        $detail = $this->Production_model->component_lot_usage_detail((int)$lotId);
        if (!($detail['ok'] ?? false)) {
            show_error((string)($detail['message'] ?? 'Detail pemakaian lot component tidak ditemukan.'), 404, 'Not Found');
            return;
        }

        $header = (array)($detail['header'] ?? []);
        $this->render('production/component_lot_usage_detail', [
            'page_title' => 'Pemakaian Lot ' . (string)($header['lot_no'] ?? '#'),
            'active_menu' => 'production.component.lot',
            'detail' => $detail,
        ]);
    }

    public function component_openings()
    {
        $this->require_permission('production.component.opening.index', 'view');
        $q = trim((string)$this->input->get('q', true));
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo   = trim((string)$this->input->get('date_to', true));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = date('Y-m-t');
        }
        $month = substr($dateFrom, 0, 7);
        $perPage = (int)$this->input->get('per_page', true);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }
        $locationType = $this->normalize_location_filter($this->input->get('location_type', true));
        $divisionId = (int)$this->input->get('division_id', true);
        $filters = [
            'q' => $q,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
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
        $rows = $this->Production_model->list_component_openings($filters, 500);
        $this->render('production/component_opening_index', [
            'page_title' => 'Opening Base/Prepare',
            'rows' => $rows,
            'edit_opening' => $editOpening,
            'detail_opening' => $detailOpening,
            'opening_tab' => $openingTab,
            'q' => $q,
            'month' => $month,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'per_page' => $perPage,
            'selected_location_type' => $locationType,
            'selected_division_id' => $divisionId,
            'monthly_rows' => $this->Production_model->list_component_monthly_openings($filters, 500),
            'location_options' => $this->location_options(),
            'components' => $this->active_components(),
            'uoms' => $this->active_uoms(),
            'divisions' => $this->active_divisions(),
            'component_opening_export_url' => site_url('production/component-openings/export-template'),
            'component_opening_export_existing_url' => site_url('production/component-openings/export-existing'),
            'component_opening_import_url' => site_url('production/component-openings/import'),
        ]);
    }

    public function component_opening_export_template()
    {
        if (!$this->can('production.component.opening.index', 'view') && !$this->can('production.component.opening.index', 'create')) {
            $this->require_permission('production.component.opening.index', 'view');
        }

        $month = $this->component_import_month((string)$this->input->get('month', true));
        $locationGroup = $this->component_import_location_group((string)$this->input->get('location_group', true));
        if ($locationGroup === '') {
            $locationGroup = $this->component_import_location_group((string)$this->input->get('location_type', true));
        }
        if ($locationGroup === '') {
            $locationGroup = 'REGULER';
        }

        $rows = [];
        foreach ($this->active_components() as $component) {
            $rows[] = [
                'opening_month' => $month,
                'location_group' => $locationGroup,
                'division_code' => (string)($component['division_code'] ?? ''),
                'division_name' => (string)($component['division_name'] ?? ''),
                'component_id' => (int)($component['id'] ?? 0),
                'component_code' => (string)($component['component_code'] ?? ''),
                'component_name' => (string)($component['component_name'] ?? ''),
                'uom_id' => (int)($component['uom_id'] ?? 0),
                'uom_code' => (string)($component['uom_code'] ?? ''),
                'opening_qty' => '',
                'unit_cost' => '',
                'note' => '',
            ];
        }

        $headers = [
            'opening_month', 'location_group', 'division_code', 'division_name',
            'component_id', 'component_code', 'component_name', 'uom_id', 'uom_code',
            'opening_qty', 'unit_cost', 'note',
        ];
        $filename = 'component-opening-template-' . strtolower($locationGroup) . '-' . $month . '.xlsx';

        $this->load->library('SimpleSpreadsheetIO');
        $this->simplespreadsheetio->output_xlsx($filename, $headers, $rows, 'Template Opening');
    }

    public function component_opening_export_existing()
    {
        if (!$this->can('production.component.opening.index', 'view') && !$this->can('production.component.opening.index', 'export')) {
            $this->require_permission('production.component.opening.index', 'view');
        }

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'month' => $this->component_import_month((string)$this->input->get('month', true)),
            'location_type' => $this->normalize_location_filter($this->input->get('location_type', true)),
            'division_id' => (int)$this->input->get('division_id', true) > 0 ? (int)$this->input->get('division_id', true) : null,
        ];

        $documents = $this->Production_model->list_component_openings($filters, 1000);
        $rows = [];
        foreach ($documents as $document) {
            $openingId = (int)($document['id'] ?? 0);
            if ($openingId <= 0) {
                continue;
            }
            foreach ($this->Production_model->get_component_opening_lines($openingId) as $line) {
                $rows[] = [
                    'opening_no' => (string)($document['opening_no'] ?? ''),
                    'status' => (string)($document['status'] ?? ''),
                    'opening_month' => substr((string)($document['opening_date'] ?? ''), 0, 7),
                    'location_group' => $this->normalize_location_filter((string)($document['location_type'] ?? '')),
                    'division_name' => (string)($document['division_name'] ?? ''),
                    'component_id' => (string)($line['component_id'] ?? ''),
                    'component_code' => (string)($line['component_code'] ?? ''),
                    'component_name' => (string)($line['component_name'] ?? ''),
                    'uom_id' => (string)($line['uom_id'] ?? ''),
                    'uom_code' => (string)($line['uom_code'] ?? ''),
                    'opening_qty' => (string)($line['opening_qty'] ?? ''),
                    'unit_cost' => (string)($line['unit_cost'] ?? ''),
                    'total_value' => (string)($line['total_value'] ?? ''),
                    'note' => (string)($line['note'] ?? ''),
                ];
            }
        }

        $headers = [
            'opening_no', 'status', 'opening_month', 'location_group', 'division_name',
            'component_id', 'component_code', 'component_name', 'uom_id', 'uom_code',
            'opening_qty', 'unit_cost', 'total_value', 'note',
        ];
        $filename = 'component-opening-existing-' . preg_replace('/[^0-9\-]/', '', (string)($filters['month'] ?? date('Y-m'))) . '.xlsx';

        $this->load->library('SimpleSpreadsheetIO');
        $this->simplespreadsheetio->output_xlsx($filename, $headers, $rows, 'Opening Existing');
    }

    public function component_opening_import()
    {
        $this->require_permission('production.component.opening.index', 'create');

        $defaultMonth = $this->component_import_month((string)$this->input->post('month', true));
        $defaultLocationGroup = $this->component_import_location_group((string)$this->input->post('location_group', true));
        if ($defaultLocationGroup === '') {
            $defaultLocationGroup = $this->component_import_location_group((string)$this->input->post('location_type', true));
        }
        if ($defaultLocationGroup === '') {
            $defaultLocationGroup = 'REGULER';
        }
        $backUrl = $this->component_opening_redirect_url([
            'month' => $defaultMonth,
            'location_type' => $defaultLocationGroup,
        ]);

        $this->load->library('SimpleSpreadsheetIO');
        $parsed = $this->simplespreadsheetio->parse_uploaded_file('import_file');
        if (!($parsed['ok'] ?? false)) {
            $this->session->set_flashdata('error', (string)($parsed['message'] ?? 'File import component opening tidak valid.'));
            redirect($backUrl);
            return;
        }

        $componentMaps = $this->component_opening_component_maps();
        $uomMap = $this->component_opening_uom_map();
        $groups = [];
        $errors = [];
        $skippedCount = 0;

        foreach ((array)($parsed['rows'] ?? []) as $index => $row) {
            $rowNumber = $index + 2;
            $qty = round($this->component_import_decimal($this->component_import_row_value($row, ['opening_qty', 'qty'], '0')), 4);
            if ($qty <= 0) {
                $skippedCount++;
                continue;
            }

            $component = null;
            $componentId = (int)$this->component_import_row_value($row, ['component_id'], 0);
            if ($componentId > 0 && !empty($componentMaps['id'][$componentId])) {
                $component = $componentMaps['id'][$componentId];
            }
            if ($component === null) {
                $componentCode = strtoupper(trim((string)$this->component_import_row_value($row, ['component_code'], '')));
                if ($componentCode !== '' && !empty($componentMaps['code'][$componentCode])) {
                    $component = $componentMaps['code'][$componentCode];
                }
            }
            if ($component === null) {
                $errors[] = 'Baris ' . $rowNumber . ': Component tidak ditemukan.';
                continue;
            }

            $divisionId = (int)($component['operational_division_id'] ?? 0);
            if ($divisionId <= 0) {
                $errors[] = 'Baris ' . $rowNumber . ': Divisi component belum terdefinisi.';
                continue;
            }

            $month = $this->component_import_month((string)$this->component_import_row_value($row, ['opening_month', 'month'], $defaultMonth));
            $locationGroup = $this->component_import_location_group((string)$this->component_import_row_value($row, ['location_group', 'location_type'], $defaultLocationGroup));
            if ($locationGroup === '') {
                $errors[] = 'Baris ' . $rowNumber . ': Lokasi harus REGULER atau EVENT.';
                continue;
            }

            $uomId = $this->component_import_uom_id($this->component_import_row_value($row, ['uom_id', 'uom_code'], ''), $uomMap, (int)($component['uom_id'] ?? 0));
            if ($uomId <= 0) {
                $errors[] = 'Baris ' . $rowNumber . ': UOM component tidak valid.';
                continue;
            }

            $groupKey = $month . '|' . $locationGroup . '|' . $divisionId;
            if (empty($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'label' => $month . ' / ' . $locationGroup . ' / ' . (string)($component['division_name'] ?? $component['division_code'] ?? ('Divisi #' . $divisionId)),
                    'header' => [
                        'id' => 0,
                        'opening_no' => '',
                        'opening_month' => $month,
                        'location_type' => $locationGroup,
                        'division_id' => $divisionId,
                        'notes' => 'Import spreadsheet opening component ' . date('Y-m-d H:i:s'),
                    ],
                    'lines' => [],
                ];
            }

            $groups[$groupKey]['lines'][] = [
                'component_id' => (int)($component['id'] ?? 0),
                'uom_id' => $uomId,
                'opening_qty' => $qty,
                'unit_cost' => round($this->component_import_decimal($this->component_import_row_value($row, ['unit_cost', 'cost'], '0')), 6),
                'note' => (string)$this->component_import_row_value($row, ['note', 'notes'], ''),
            ];
        }

        if (empty($groups) && empty($errors)) {
            $this->session->set_flashdata('error', 'Tidak ada baris import dengan qty opening lebih dari 0.');
            redirect($backUrl);
            return;
        }

        $successDocs = 0;
        $successLines = 0;
        foreach ($groups as $group) {
            $save = $this->Production_model->save_component_opening(
                (array)$group['header'],
                (array)$group['lines'],
                (int)($this->current_user['employee_id'] ?? 0)
            );
            if (!($save['ok'] ?? false)) {
                $errors[] = 'Dokumen ' . (string)($group['label'] ?? '-') . ': ' . (string)($save['message'] ?? 'Gagal menyimpan opening.');
                continue;
            }

            $post = $this->post_component_opening_document((int)($save['id'] ?? 0));
            if (!($post['ok'] ?? false)) {
                $errors[] = 'Dokumen ' . (string)($group['label'] ?? '-') . ': ' . (string)($post['message'] ?? 'Gagal posting opening import.');
                continue;
            }

            $successDocs++;
            $successLines += count((array)$group['lines']);
        }

        $summary = 'Import component opening selesai. Berhasil simpan+post ' . $successDocs . ' dokumen / ' . $successLines . ' baris';
        if ($skippedCount > 0) {
            $summary .= ', dilewati ' . $skippedCount . ' baris kosong/qty 0';
        }
        if (!empty($errors)) {
            $summary .= ', gagal ' . count($errors) . ' item. ' . implode(' | ', array_slice($errors, 0, 5));
        }

        if ($successDocs > 0 && empty($errors)) {
            $this->session->set_flashdata('success', $summary . '.');
        } elseif ($successDocs > 0) {
            $this->session->set_flashdata('warning', $summary);
        } else {
            $this->session->set_flashdata('error', $summary);
        }

        redirect($backUrl);
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
        $post = $this->post_component_opening_document((int)$id);
        if (!($post['ok'] ?? false)) {
            $this->json_error((string)($post['message'] ?? 'Posting opening gagal.'), (int)($post['status_code'] ?? 422));
            return;
        }
        $this->json_ok(['id' => (int)$id]);
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
        $q          = trim((string)$this->input->get('q', true));
        $dateFrom   = trim((string)$this->input->get('date_from', true));
        $dateTo     = trim((string)$this->input->get('date_to', true));
        $divisionId = (int)$this->input->get('filter_division_id', true);
        $locFilter  = $this->normalize_location_filter($this->input->get('filter_location_type', true));
        $perPage    = (int)$this->input->get('per_page', true);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = date('Y-m-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo))   $dateTo   = date('Y-m-d');
        if (!in_array($perPage, [25, 50, 100, 0], true))      $perPage  = 25;

        $listFilters = [
            'q'             => $q,
            'date_from'     => $dateFrom,
            'date_to'       => $dateTo,
            'division_id'   => $divisionId,
            'location_type' => $locFilter,
        ];
        $rows     = $this->Production_model->list_component_adjustments($listFilters, 500);
        $lineRows = $this->Production_model->list_component_adjustment_detail_rows($listFilters, 2000);

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
            'adjustment_date'  => $prefillDate,
            'location_type'    => $this->normalize_location_type($this->input->get('location_type', true)),
            'division_id'      => $prefillDivisionId > 0 ? $prefillDivisionId : 0,
            'notes'            => trim((string)$this->input->get('notes', true)),
            'source_opening_no'=> trim((string)$this->input->get('source_opening_no', true)),
        ];
        $this->render('production/component_adjustment_index', [
            'page_title'       => 'Adjustment Base/Prepare',
            'rows'             => $rows,
            'line_rows'        => $lineRows,
            'active_list_tab'  => $activeListTab,
            'q'                => $q,
            'list_filters'     => array_merge($listFilters, ['per_page' => $perPage]),
            'prefill'          => $prefill,
            'location_options' => $this->location_options(),
            'components'       => $this->active_components(),
            'uoms'             => $this->active_uoms(),
            'divisions'        => $this->active_divisions(),
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
        $today      = date('Y-m-d');
        $q          = trim((string)$this->input->get('q', true));
        $dateFrom   = trim((string)($this->input->get('date_from', true) ?: date('Y-m-01')));
        $dateTo     = trim((string)($this->input->get('date_to', true) ?: $today));
        $divisionId = (int)$this->input->get('division_id', true);
        $locType       = strtoupper(trim((string)$this->input->get('location_type', true)));
        $componentType = strtoupper(trim((string)$this->input->get('type', true)));

        $filters = [
            'q'             => $q,
            'date_from'     => $dateFrom,
            'date_to'       => $dateTo,
            'division_id'   => $divisionId > 0 ? $divisionId : null,
            'location_type' => $locType,
            'type'          => $componentType,
        ];
        $rows = $this->Production_model->list_component_batches($filters, 500);
        $this->render('production/component_batch_index', [
            'page_title'       => 'Batch Produksi Base/Prepare',
            'rows'             => $rows,
            'filters'          => $filters,
            'q'                => $q,
            'date_from'        => $dateFrom,
            'date_to'          => $dateTo,
            'filter_division'  => $divisionId,
            'filter_location'  => $locType,
            'location_options' => $this->location_options(),
            'components'       => $this->active_components(),
            'materials'        => $this->active_materials(),
            'uoms'             => $this->active_uoms(),
            'divisions'        => $this->active_divisions(),
        ]);
    }

    public function component_batch_save()
    {
        $this->require_permission('production.component.batch.index', 'create');
        $this->release_session_lock();
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
        $this->release_session_lock();
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
        $this->release_session_lock();
        try {
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
            $this->json_ok([
                'id' => $id,
                'recovery_warnings' => $post['recovery_warnings'] ?? [],
            ]);
        } catch (Throwable $e) {
            log_message('error', 'component_batch_post fatal: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->json_error('Posting batch gagal. ' . $e->getMessage(), 500);
        }
    }

    public function component_batch_status($id)
    {
        $this->require_permission('production.component.batch.index', 'view');
        $this->release_session_lock();
        $id = (int)$id;
        $header = $this->Production_model->get_component_batch($id);
        if (!$header) {
            $this->json_error('Batch tidak ditemukan.', 404);
            return;
        }

        $this->json_ok([
            'id' => $id,
            'status' => strtoupper((string)($header['status'] ?? '')),
            'posted_at' => (string)($header['posted_at'] ?? ''),
            'updated_at' => (string)($header['updated_at'] ?? ''),
        ]);
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
        $sourceDivisionRows = $this->db
            ->select('id, name')
            ->from('mst_operational_division')
            ->where('is_active', 1)
            ->order_by('sort_order', 'ASC')
            ->order_by('name', 'ASC')
            ->get()->result_array();
        $sourceDivisions = array_map(fn($d) => ['value' => (int)$d['id'], 'label' => (string)$d['name']], $sourceDivisionRows);
        $this->render('production/component_formula_edit', [
            'page_title' => 'Edit Formula Component',
            'detail' => $detail,
            'uoms' => $this->active_uoms(),
            'materials' => $this->active_materials(),
            'components' => $this->active_components(),
            'source_divisions' => $sourceDivisions,
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

    // ── Component Daily Recon ─────────────────────────────────

    private const PAGE_COMPONENT_DAILY_RECON = 'production.component.daily.recon.index';
    private const PAGE_COMPONENT_OPNAME_MONTHLY = 'production.component.opname.monthly';

    public function component_daily_recon()
    {
        $pageCode = $this->can(self::PAGE_COMPONENT_DAILY_RECON, 'view')
            ? self::PAGE_COMPONENT_DAILY_RECON
            : 'production.component.daily.index';
        $this->require_permission($pageCode, 'view');

        $opnameDate = trim((string)$this->input->get('opname_date', true));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $opnameDate)) {
            $opnameDate = date('Y-m-d');
        }
        $locationType = strtoupper(trim((string)$this->input->get('location_type', true)));
        if (!in_array($locationType, ['REGULER', 'EVENT'], true)) {
            $locationType = '';
        }
        $divisionId = (int)$this->input->get('division_id', true);
        $type       = strtoupper(trim((string)$this->input->get('type', true)));
        if (!in_array($type, ['BASE', 'PREPARE'], true)) {
            $type = '';
        }
        $q           = trim((string)$this->input->get('q', true));
        $isSuperadmin = !empty($this->current_user['is_superadmin']);
        $canCreate    = $isSuperadmin || $this->can(self::PAGE_COMPONENT_DAILY_RECON, 'create');

        $this->render('production/component_daily_recon_index', [
            'page_title'    => 'Daily Recon Stok Component',
            'active_menu'   => 'production.component.daily.recon',
            'opname_date'   => $opnameDate,
            'location_type' => $locationType,
            'division_id'   => $divisionId,
            'type'          => $type,
            'q'             => $q,
            'divisions'     => $this->active_divisions(),
            'can_create'    => $canCreate,
        ]);
    }

    public function component_daily_recon_data()
    {
        $this->require_permission(
            $this->can(self::PAGE_COMPONENT_DAILY_RECON, 'view') ? self::PAGE_COMPONENT_DAILY_RECON : 'production.component.daily.index',
            'view'
        );

        $opnameDate = trim((string)$this->input->get('opname_date', true));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $opnameDate)) {
            $opnameDate = date('Y-m-d');
        }
        $targetMonth = date('Y-m-01', strtotime($opnameDate));

        $locationType = strtoupper(trim((string)$this->input->get('location_type', true)));
        if (!in_array($locationType, ['REGULER', 'EVENT'], true)) {
            $locationType = '';
        }
        $divisionId = (int)$this->input->get('division_id', true);
        $type       = strtoupper(trim((string)$this->input->get('type', true)));
        if (!in_array($type, ['BASE', 'PREPARE'], true)) {
            $type = '';
        }
        $q = trim((string)$this->input->get('q', true));

        if (!$this->db->table_exists('inv_component_monthly_stock')) {
            $this->json_ok(['rows' => [], 'meta' => ['total_components' => 0]]);
            return;
        }

        $divNameCol  = $this->db->field_exists('division_name', 'mst_operational_division')
            ? 'division_name'
            : ($this->db->field_exists('name', 'mst_operational_division') ? 'name' : null);
        $divNameExpr = $divNameCol ? ('d.' . $divNameCol) : 'CAST(s.division_id AS CHAR)';
        $divCodeCol  = $this->db->field_exists('code', 'mst_operational_division') ? 'd.code' : 'NULL';

        $latestSub = "SELECT location_type, division_id, component_id, uom_id, MAX(month_key) AS max_month
                      FROM inv_component_monthly_stock
                      WHERE month_key <= " . $this->db->escape($targetMonth) . "
                      GROUP BY location_type, division_id, component_id, uom_id";

        $where = '';
        if ($locationType !== '') {
            $where .= " AND s.location_type = " . $this->db->escape($locationType);
        }
        if ($divisionId > 0) {
            $where .= " AND s.division_id = " . (int)$divisionId;
        }
        if ($type !== '') {
            $where .= " AND c.component_type = " . $this->db->escape($type);
        }
        if ($q !== '') {
            $qLike  = $this->db->escape('%' . $q . '%');
            $where .= " AND (c.component_name LIKE {$qLike} OR c.component_code LIKE {$qLike})";
        }

        $catNameExpr = $this->db->field_exists('name', 'mst_component_category') ? 'cat.name' : 'NULL';

        $sql = "
            SELECT
                s.location_type,
                s.division_id,
                {$divNameExpr}               AS division_name,
                {$divCodeCol}                AS division_code,
                s.component_id,
                c.component_code,
                c.component_name,
                c.component_type,
                s.uom_id,
                COALESCE(u.code, '')         AS uom_code,
                s.closing_qty                AS system_qty,
                s.avg_cost,
                COALESCE({$catNameExpr}, '') AS category_name
            FROM inv_component_monthly_stock s
            INNER JOIN ({$latestSub}) lm
                ON  lm.location_type  = s.location_type
                AND lm.division_id  <=> s.division_id
                AND lm.component_id   = s.component_id
                AND lm.uom_id         = s.uom_id
                AND lm.max_month      = s.month_key
            JOIN  mst_component c ON c.id = s.component_id
            LEFT JOIN mst_operational_division d ON d.id = s.division_id
            LEFT JOIN mst_uom u ON u.id = s.uom_id
            LEFT JOIN mst_component_category cat ON cat.id = c.component_category_id
            WHERE 1=1 {$where}
            ORDER BY {$divNameExpr}, s.location_type, c.component_type, c.component_name, u.code
        ";

        $stockRows = ($r = $this->db->query($sql)) ? $r->result_array() : [];

        // ── Load lot data for multi-lot expand/collapse ───────────────────────
        // Key: "compId|uomId|divId" — location_type sengaja diabaikan agar
        // match tetap benar meski monthly_stock menyimpan REGULER/EVENT sementara
        // inv_component_lot menyimpan BAR/KITCHEN/BAR_EVENT/KITCHEN_EVENT.
        $lotsByKey = [];
        if ($this->db->table_exists('inv_component_lot') && !empty($stockRows)) {
            $compIds = implode(',', array_unique(array_map('intval', array_column($stockRows, 'component_id'))));
            $lotSql  = "
                SELECT l.id AS lot_id, l.lot_no, l.location_type AS lot_location_type,
                       l.division_id, l.component_id, l.uom_id,
                       l.qty_balance AS system_qty, l.unit_cost,
                       l.receipt_date, l.expiry_date
                FROM inv_component_lot l
                WHERE l.component_id IN ({$compIds})
                  AND l.status = 'OPEN'
                ORDER BY l.receipt_date ASC, l.id ASC
            ";
            $lotResult = $this->db->query($lotSql);
            foreach ($lotResult ? $lotResult->result_array() : [] as $lot) {
                // Key pakai compId|uomId|divId saja, bebas dari ambiguitas location_type
                $lk = (int)$lot['component_id'] . '|' . (int)$lot['uom_id'] . '|' . (int)$lot['division_id'];
                $lotsByKey[$lk][] = $lot;
            }
        }

        // ── Load physical counts for this opname date ─────────────────────────
        $opnameMap    = [];
        $lotOpnameMap = [];  // keyed: parentKey => [lot_id => row]
        $hasLotIdCol  = $this->db->field_exists('lot_id', 'inv_component_stock_opname');

        if ($this->db->table_exists('inv_component_stock_opname')) {
            $selCols = 'location_type, division_id, component_id, uom_id, physical_qty, notes, adjustment_id'
                       . ($hasLotIdCol ? ', lot_id' : '');
            $opnameQ = $this->db->select($selCols)
                ->from('inv_component_stock_opname')
                ->where('opname_date', $opnameDate);
            if ($divisionId > 0) $opnameQ->where('division_id', $divisionId);
            if ($locationType !== '') $opnameQ->where('location_type', $locationType);
            foreach ($opnameQ->get()->result_array() as $oRow) {
                $lotId = $hasLotIdCol ? (int)($oRow['lot_id'] ?? 0) : 0;
                $k = $oRow['location_type'] . '|' . $oRow['division_id'] . '|' . $oRow['component_id'] . '|' . $oRow['uom_id'];
                if ($lotId > 0) {
                    $lotOpnameMap[$k][$lotId] = $oRow;
                } else {
                    $opnameMap[$k] = $oRow;
                }
            }
        }

        // ── Group by division+location_type ───────────────────────────────────
        $groups = [];
        foreach ($stockRows as $r) {
            $divId   = (int)$r['division_id'];
            $locType = (string)$r['location_type'];
            $ikey    = $locType . '|' . $divId . '|' . $r['component_id'] . '|' . $r['uom_id'];
            $opname  = $opnameMap[$ikey] ?? null;
            $sysQty  = (float)$r['system_qty'];
            $physQty = ($opname !== null && $opname['physical_qty'] !== null)
                ? (float)$opname['physical_qty'] : null;
            $selisih = $physQty !== null ? round($physQty - $sysQty, 4) : null;

            // Build lot sub-rows for multi-lot components
            $lotLookupKey = (int)$r['component_id'] . '|' . (int)$r['uom_id'] . '|' . $divId;
            $rawLots      = $lotsByKey[$lotLookupKey] ?? [];
            $lotCount     = count($rawLots);
            $lotSubRows = [];
            if ($lotCount > 1) {
                $lotOpnamesForKey = $lotOpnameMap[$ikey] ?? [];
                foreach ($rawLots as $lot) {
                    $lotId     = (int)$lot['lot_id'];
                    $lotSysQty = (float)$lot['system_qty'];
                    $lotOpname = $lotOpnamesForKey[$lotId] ?? null;
                    $lotPhys   = ($lotOpname && $lotOpname['physical_qty'] !== null) ? (float)$lotOpname['physical_qty'] : null;
                    $lotSel    = $lotPhys !== null ? round($lotPhys - $lotSysQty, 4) : null;
                    $lotSubRows[] = [
                        'lot_id'            => $lotId,
                        'lot_no'            => (string)$lot['lot_no'],
                        'lot_specific_type' => (string)$lot['lot_location_type'],
                        'receipt_date'      => (string)$lot['receipt_date'],
                        'expiry_date'       => (string)($lot['expiry_date'] ?? ''),
                        'unit_cost'         => (float)$lot['unit_cost'],
                        'identity_key'      => $ikey . '|' . $lotId,
                        'system_qty'        => $lotSysQty,
                        'physical_qty'      => $lotPhys,
                        'selisih'           => $lotSel,
                        'adjustment_id'     => ($lotOpname && !empty($lotOpname['adjustment_id'])) ? (int)$lotOpname['adjustment_id'] : null,
                    ];
                }
            }

            $row = [
                'location_type'  => $locType,
                'division_id'    => $divId,
                'division_name'  => (string)$r['division_name'],
                'division_code'  => strtoupper(trim((string)$r['division_code'])),
                'component_id'   => (int)$r['component_id'],
                'component_code' => $r['component_code'],
                'component_name' => $r['component_name'],
                'component_type' => $r['component_type'],
                'category_name'  => $r['category_name'],
                'uom_id'         => (int)$r['uom_id'],
                'uom_code'       => $r['uom_code'],
                'identity_key'   => $ikey,
                'system_qty'     => $sysQty,
                'avg_cost'       => (float)$r['avg_cost'],
                'physical_qty'   => $physQty,
                'selisih'        => $selisih,
                'opname_notes'   => (string)($opname['notes'] ?? ''),
                'adjustment_id'  => ($opname && !empty($opname['adjustment_id']))
                    ? (int)$opname['adjustment_id'] : null,
                'lot_count'      => $lotCount,
                'lots'           => $lotSubRows,
            ];

            $gkey = $divId . '|' . $locType;
            if (!isset($groups[$gkey])) {
                $groups[$gkey] = [
                    'division_id'   => $divId,
                    'division_name' => $row['division_name'],
                    'location_type' => $locType,
                    'rows'          => [],
                ];
            }
            $groups[$gkey]['rows'][] = $row;
        }

        $this->json_ok([
            'rows' => array_values($groups),
            'meta' => [
                'opname_date'      => $opnameDate,
                'total_components' => count($stockRows),
                'total_groups'     => count($groups),
            ],
        ]);
    }

    public function component_daily_recon_save()
    {
        $this->require_permission(
            $this->can(self::PAGE_COMPONENT_DAILY_RECON, 'create') ? self::PAGE_COMPONENT_DAILY_RECON : 'production.component.daily.index',
            'create'
        );

        $payload      = $this->request_payload();
        $opnameDate   = trim((string)($payload['opname_date'] ?? date('Y-m-d')));
        $locationType = strtoupper(trim((string)($payload['location_type'] ?? 'REGULER')));
        if (!in_array($locationType, ['REGULER', 'EVENT'], true)) {
            $locationType = 'REGULER';
        }
        $divisionId  = !empty($payload['division_id']) ? (int)$payload['division_id'] : null;
        $componentId = (int)($payload['component_id'] ?? 0);
        $uomId       = (int)($payload['uom_id'] ?? 0);
        $lotId       = (int)($payload['lot_id'] ?? 0);
        $physQty     = isset($payload['physical_qty']) && $payload['physical_qty'] !== ''
            ? round((float)$payload['physical_qty'], 4) : null;
        $notes       = trim((string)($payload['notes'] ?? ''));
        $userId      = (int)($this->current_user['employee_id'] ?? ($this->current_user['id'] ?? 0));

        if ($componentId <= 0 || $uomId <= 0) {
            $this->json_error('component_id dan uom_id wajib diisi.', 422);
            return;
        }
        if (!$this->db->table_exists('inv_component_stock_opname')) {
            $this->json_error('Tabel opname belum ada. Jalankan SQL setup terlebih dahulu.', 500);
            return;
        }

        $hasLotIdCol = $this->db->field_exists('lot_id', 'inv_component_stock_opname');
        $systemQty   = (float)($payload['system_qty'] ?? 0);
        $selisih     = $physQty !== null ? round($physQty - $systemQty, 4) : null;

        $q = $this->db
            ->where('opname_date', $opnameDate)
            ->where('location_type', $locationType);
        if ($divisionId !== null) {
            $q->where('division_id', $divisionId);
        } else {
            $q->where('division_id IS NULL', null, false);
        }
        $q->where('component_id', $componentId)->where('uom_id', $uomId);
        if ($hasLotIdCol) {
            $q->where('lot_id', $lotId > 0 ? $lotId : 0);
        }
        $existing = $q->get('inv_component_stock_opname')->row_array();

        if ($existing) {
            $this->db->where('id', (int)$existing['id'])->update('inv_component_stock_opname', [
                'physical_qty' => $physQty,
                'system_qty'   => $systemQty,
                'notes'        => $notes !== '' ? $notes : null,
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
        } else {
            $insertData = [
                'opname_date'   => $opnameDate,
                'location_type' => $locationType,
                'division_id'   => $divisionId,
                'component_id'  => $componentId,
                'uom_id'        => $uomId,
                'system_qty'    => $systemQty,
                'physical_qty'  => $physQty,
                'notes'         => $notes !== '' ? $notes : null,
                'created_by'    => $userId > 0 ? $userId : null,
            ];
            if ($hasLotIdCol) $insertData['lot_id'] = $lotId > 0 ? $lotId : 0;
            $this->db->insert('inv_component_stock_opname', $insertData);
        }

        $this->json_ok(['selisih' => $selisih, 'physical_qty' => $physQty]);
    }

    public function component_daily_recon_adjust()
    {
        $this->require_permission(
            $this->can(self::PAGE_COMPONENT_DAILY_RECON, 'create') ? self::PAGE_COMPONENT_DAILY_RECON : 'production.component.daily.index',
            'create'
        );
        $this->require_permission('production.component.adjustment.index', 'create');

        $payload      = $this->request_payload();
        $opnameDate   = trim((string)($payload['opname_date'] ?? date('Y-m-d')));
        $locationType = strtoupper(trim((string)($payload['location_type'] ?? 'REGULER')));
        if (!in_array($locationType, ['REGULER', 'EVENT'], true)) {
            $locationType = 'REGULER';
        }
        $divisionId    = !empty($payload['division_id']) ? (int)$payload['division_id'] : null;
        $divCode       = strtoupper(trim((string)($payload['division_code'] ?? '')));
        $componentId   = (int)($payload['component_id'] ?? 0);
        $uomId         = (int)($payload['uom_id'] ?? 0);
        $lotId         = !empty($payload['lot_id']) ? (int)$payload['lot_id'] : 0;
        $physQty       = (float)($payload['physical_qty'] ?? 0);
        $systemQty     = (float)($payload['system_qty'] ?? 0);
        $selisih       = round($physQty - $systemQty, 4);
        $adjType       = strtoupper(trim((string)($payload['adjustment_type'] ?? '')));
        $reasonCode    = strtolower(trim((string)($payload['reason_code'] ?? 'other')));
        $notes         = trim((string)($payload['notes'] ?? ''));
        $userId        = (int)($this->current_user['employee_id'] ?? ($this->current_user['id'] ?? 0));

        if ($componentId <= 0 || $uomId <= 0 || abs($selisih) < 0.0001) {
            $this->json_error('Selisih 0 atau parameter tidak lengkap.', 422);
            return;
        }

        // Derive specific location (BAR/KITCHEN/BAR_EVENT/KITCHEN_EVENT) from group + division code
        $specificLocation = '';
        if ($divCode === 'BAR') {
            $specificLocation = $locationType === 'EVENT' ? 'BAR_EVENT' : 'BAR';
        } elseif ($divCode === 'KITCHEN') {
            $specificLocation = $locationType === 'EVENT' ? 'KITCHEN_EVENT' : 'KITCHEN';
        }
        if ($specificLocation === '') {
            $this->json_error('Lokasi spesifik component tidak dapat ditentukan (divisi harus BAR atau KITCHEN).', 422);
            return;
        }

        $absQty = round(abs($selisih), 4);
        $adjNo  = 'CMPREC-' . date('Ymd', strtotime($opnameDate))
                . '-' . strtoupper(substr(md5($componentId . $uomId . $opnameDate . $locationType), 0, 6));

        // Map UI type (WASTE/SPOILAGE/ADJUSTMENT_MINUS/ADJUSTMENT_PLUS) to model fields
        $line = [
            'component_id'                => $componentId,
            'uom_id'                      => $uomId,
            'selected_lot_id'             => $lotId > 0 ? $lotId : null,
            'available_qty'               => $systemQty,
            'qty_waste'                   => 0,
            'waste_reason_code'           => '',
            'qty_spoil'                   => 0,
            'spoil_reason_code'           => '',
            'qty_adjust_pos'              => 0,
            'adjustment_plus_reason_code' => '',
            'qty_adjust_neg'              => 0,
            'adjustment_minus_reason_code'=> '',
            'unit_cost'                   => max(0, (float)($payload['avg_cost'] ?? 0)),
            'note'                        => $notes,
        ];
        if ($selisih < 0) {
            $validNeg = ['WASTE', 'SPOILAGE', 'ADJUSTMENT_MINUS'];
            if (!in_array($adjType, $validNeg, true)) {
                $adjType = 'ADJUSTMENT_MINUS';
            }
            if ($adjType === 'WASTE') {
                $line['qty_waste']         = $absQty;
                $line['waste_reason_code'] = $reasonCode ?: 'other';
            } elseif ($adjType === 'SPOILAGE') {
                $line['qty_spoil']          = $absQty;
                $line['spoil_reason_code']  = $reasonCode ?: 'other';
            } else {
                $line['qty_adjust_neg']                   = $absQty;
                $line['adjustment_minus_reason_code']     = $reasonCode ?: 'other';
            }
        } else {
            $line['qty_adjust_pos']                  = $absQty;
            $line['adjustment_plus_reason_code']     = $reasonCode ?: 'other';
        }

        $header = [
            'id'              => 0,
            'adjustment_no'   => $adjNo,
            'adjustment_date' => $opnameDate,
            'location_type'   => $specificLocation,
            'division_id'     => $divisionId,
            'notes'           => 'Dari daily recon stok component' . ($notes !== '' ? ': ' . $notes : ''),
        ];

        $dbDebugBefore = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        $save = $this->Production_model->save_component_adjustment($header, [$line], $userId);
        if (!($save['ok'] ?? false)) {
            $this->db->db_debug = $dbDebugBefore;
            $this->json_error((string)($save['message'] ?? 'Gagal menyimpan adjustment.'), 422);
            return;
        }

        $adjId  = (int)($save['id'] ?? 0);
        $adjHdr = $this->Production_model->get_component_adjustment($adjId);
        $adjLines = $this->Production_model->get_component_adjustment_lines($adjId);
        $post   = $this->componentstockwriter->post_adjustment($adjHdr, $adjLines, $userId);
        if (!($post['ok'] ?? false)) {
            $this->db->db_debug = $dbDebugBefore;
            $this->json_error('Tersimpan tapi gagal posting: ' . (string)($post['message'] ?? ''), 422);
            return;
        }
        $this->db->where('id', $adjId)->update('inv_component_adjustment', [
            'status'    => 'POSTED',
            'posted_at' => date('Y-m-d H:i:s'),
            'posted_by' => $userId > 0 ? $userId : null,
        ]);
        $this->db->db_debug = $dbDebugBefore;

        // Tag daily-recon record
        if ($this->db->table_exists('inv_component_stock_opname') && $adjId > 0) {
            $lotIdAdj    = !empty($payload['lot_id']) ? (int)$payload['lot_id'] : 0;
            $hasLotIdAdj = $this->db->field_exists('lot_id', 'inv_component_stock_opname');
            $q = $this->db->where('opname_date', $opnameDate)->where('location_type', $locationType);
            if ($divisionId !== null) {
                $q->where('division_id', $divisionId);
            } else {
                $q->where('division_id IS NULL', null, false);
            }
            $q->where('component_id', $componentId)->where('uom_id', $uomId);
            if ($hasLotIdAdj) $q->where('lot_id', $lotIdAdj > 0 ? $lotIdAdj : 0);
            $q->update('inv_component_stock_opname', ['adjustment_id' => $adjId]);
        }

        $scopeLabel = $lotId > 0 ? 'lot component' : 'saldo component';
        $this->json_ok(
            ['adjustment_id' => $adjId],
            'Adjustment ' . $scopeLabel . ' berhasil diposting. Adj #' . $adjId . ' sudah tercatat.'
        );
    }

    private function stock_filters()
    {
        $perPage = (int)$this->input->get('per_page', true);
        if (!in_array($perPage, [25, 50, 100, 200, 0], true)) {
            $perPage = 25;
        }
        return [
            'q'             => trim((string)$this->input->get('q', true)),
            'location_type' => $this->normalize_location_filter($this->input->get('location_type', true)),
            'type'          => $this->normalize_component_type_filter($this->input->get('type', true)),
            'division_id'   => (int)$this->input->get('division_id', true),
            'per_page'      => $perPage,
        ];
    }

    private function movement_filters()
    {
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo   = trim((string)$this->input->get('date_to', true));
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $dateFrom)) {
            $dateFrom = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $dateTo)) {
            $dateTo = date('Y-m-d');
        }
        $perPage = (int)$this->input->get('per_page', true);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }
        return [
            'q'             => trim((string)$this->input->get('q', true)),
            'location_type' => $this->normalize_location_filter($this->input->get('location_type', true)),
            'movement_type' => strtoupper(trim((string)$this->input->get('movement_type', true))),
            'division_id'   => (int)$this->input->get('division_id', true),
            'date_from'     => $dateFrom,
            'date_to'       => $dateTo,
            'type'          => $this->normalize_component_type_filter($this->input->get('type', true)),
            'per_page'      => $perPage,
        ];
    }

    private function daily_filters()
    {
        $month = trim((string)$this->input->get('month', true));
        if (!preg_match('/^\d{4}\-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $perPage = (int)$this->input->get('per_page', true);
        if (!in_array($perPage, [25, 50, 100, 0], true)) {
            $perPage = 25;
        }

        return [
            'q'             => trim((string)$this->input->get('q', true)),
            'month'         => $month,
            'location_type' => $this->normalize_location_filter($this->input->get('location_type', true)),
            'division_id'   => (int)$this->input->get('division_id', true),
            'type'          => $this->normalize_component_type_filter($this->input->get('type', true)),
            'component_id'  => (int)$this->input->get('component_id', true),
            'per_page'      => $perPage,
        ];
    }

    private function component_reconcile_filters(): array
    {
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo   = trim((string)$this->input->get('date_to', true));
        $asOfDate = trim((string)$this->input->get('as_of_date', true));

        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $dateFrom)) {
            $dateFrom = date('Y-m-01');
        }
        if (preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $dateTo)) {
            $asOfDate = $dateTo;
        } elseif (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $asOfDate)) {
            $asOfDate = date('Y-m-d');
        }
        $dateTo = $asOfDate;

        $perPage = (int)$this->input->get('per_page', true);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        return [
            'q'             => trim((string)$this->input->get('q', true)),
            'as_of_date'    => $asOfDate,
            'date_from'     => $dateFrom,
            'date_to'       => $dateTo,
            'location_type' => $this->normalize_location_filter($this->input->get('location_type', true)),
            'division_id'   => (int)$this->input->get('division_id', true),
            'type'          => $this->normalize_component_type_filter($this->input->get('type', true)),
            'component_id'  => (int)$this->input->get('component_id', true),
            'uom_id'        => (int)$this->input->get('uom_id', true),
            'per_page'      => $perPage,
            'limit'         => 500,
        ];
    }

    private function lot_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true)));
        if (!in_array($status, ['OPEN', 'CLOSED', 'VOID', 'ALL'], true)) {
            $status = 'OPEN';
        }
        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo   = trim((string)$this->input->get('date_to', true));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = date('Y-m-d');
        }
        $perPage = (int)$this->input->get('per_page', true);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }
        return [
            'q'             => trim((string)$this->input->get('q', true)),
            'status'        => $status,
            'date_from'     => $dateFrom,
            'date_to'       => $dateTo,
            'per_page'      => $perPage,
            'location_type' => $this->normalize_location_filter($this->input->get('location_type', true)),
            'division_id'   => (int)$this->input->get('division_id', true),
            'type'          => $this->normalize_component_type_filter($this->input->get('type', true)),
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
        return ['' => 'Semua Tujuan', 'REGULER' => 'Reguler', 'EVENT' => 'Event'];
    }

    private function component_opening_redirect_url(array $state = []): string
    {
        $query = [
            'month' => $this->component_import_month((string)($state['month'] ?? date('Y-m'))),
            'location_type' => $this->component_import_location_group((string)($state['location_type'] ?? 'REGULER')) ?: 'REGULER',
        ];

        return site_url('production/component-openings') . '?' . http_build_query($query);
    }

    private function component_import_row_value(array $row, array $keys, $default = '')
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && trim((string)$row[$key]) !== '') {
                return $row[$key];
            }
        }
        return $default;
    }

    private function component_import_decimal($value): float
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return 0.0;
        }
        $raw = str_replace(' ', '', $raw);
        if (strpos($raw, ',') !== false && strpos($raw, '.') !== false) {
            if (strrpos($raw, ',') > strrpos($raw, '.')) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif (strpos($raw, ',') !== false) {
            $raw = str_replace(',', '.', $raw);
        }
        return (float)$raw;
    }

    private function component_import_month(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return date('Y-m');
        }
        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            return $value;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return substr($value, 0, 7);
        }
        if (preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            $date = $this->component_excel_serial_to_date((float)$value);
            if ($date !== null) {
                return substr($date, 0, 7);
            }
        }
        $time = strtotime($value);
        return $time ? date('Y-m', $time) : date('Y-m');
    }

    private function component_import_location_group(string $value): string
    {
        $normalized = $this->normalize_location_filter($value);
        return in_array($normalized, ['REGULER', 'EVENT'], true) ? $normalized : '';
    }

    private function component_excel_serial_to_date(float $serial): ?string
    {
        if ($serial <= 0) {
            return null;
        }
        $days = (int)floor($serial) - 25569;
        if ($days <= 0) {
            return null;
        }
        return gmdate('Y-m-d', $days * 86400);
    }

    private function component_opening_component_maps(): array
    {
        $rows = $this->active_components();
        $maps = ['id' => [], 'code' => []];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $maps['id'][$id] = $row;
            }
            $code = strtoupper(trim((string)($row['component_code'] ?? '')));
            if ($code !== '') {
                $maps['code'][$code] = $row;
            }
        }
        return $maps;
    }

    private function component_opening_uom_map(): array
    {
        $rows = $this->active_uoms();
        $map = [];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $map[$id] = $row;
                $code = strtoupper(trim((string)($row['code'] ?? '')));
                if ($code !== '') {
                    $map['CODE:' . $code] = $row;
                }
            }
        }
        return $map;
    }

    private function post_component_opening_document(int $id): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'Opening tidak valid.', 'status_code' => 404];
        }

        $header = $this->Production_model->get_component_opening($id);
        if (!$header) {
            return ['ok' => false, 'message' => 'Opening tidak ditemukan.', 'status_code' => 404];
        }
        if (strtoupper((string)($header['status'] ?? '')) !== 'DRAFT') {
            return ['ok' => false, 'message' => 'Hanya opening DRAFT yang bisa diposting.', 'status_code' => 422];
        }

        $conflict = $this->Production_model->find_component_opening_month_conflict(
            $id,
            (string)($header['opening_date'] ?? date('Y-m-d')),
            strtoupper(trim((string)($header['location_type'] ?? ''))),
            !empty($header['division_id']) ? (int)$header['division_id'] : null
        );
        if ($conflict) {
            return [
                'ok' => false,
                'message' => 'Opening bulan yang sama sudah ada di dokumen ' . (string)($conflict['opening_no'] ?? ('#' . (int)($conflict['id'] ?? 0))) . '.',
                'status_code' => 422,
            ];
        }

        $linesRaw = $this->Production_model->get_component_opening_lines($id);
        $lines = [];
        foreach ($linesRaw as $line) {
            $lines[] = [
                'id' => (int)($line['id'] ?? 0),
                'component_id' => (int)($line['component_id'] ?? 0),
                'uom_id' => (int)($line['uom_id'] ?? 0),
                'opening_qty' => (float)($line['opening_qty'] ?? 0),
                'qty' => (float)($line['opening_qty'] ?? 0),
                'movement_type' => 'OPENING',
                'unit_cost' => (float)($line['unit_cost'] ?? 0),
                'note' => (string)($line['note'] ?? ''),
            ];
        }

        $header['opening_date'] = substr((string)($header['opening_date'] ?? date('Y-m-d')), 0, 7) . '-01';
        $post = $this->componentstockwriter->post_opening($header, $lines, (int)($this->current_user['employee_id'] ?? 0));
        if (!($post['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string)($post['message'] ?? 'Posting opening gagal.'), 'status_code' => 422];
        }

        $this->db->where('id', $id)->update('inv_component_opening', [
            'status' => 'POSTED',
            'posted_at' => date('Y-m-d H:i:s'),
            'posted_by' => !empty($this->current_user['employee_id']) ? (int)$this->current_user['employee_id'] : null,
        ]);

        return ['ok' => true, 'id' => $id];
    }

    private function component_import_uom_id($rawValue, array $uomMap, int $fallbackId): int
    {
        $value = trim((string)$rawValue);
        if ($value === '') {
            return $fallbackId;
        }

        $numeric = (int)$value;
        if ($numeric > 0 && !empty($uomMap[$numeric])) {
            return $numeric;
        }

        $code = strtoupper($value);
        if (!empty($uomMap['CODE:' . $code])) {
            return (int)($uomMap['CODE:' . $code]['id'] ?? 0);
        }

        return $fallbackId;
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

    private function json_ok(array $data = [], string $message = ''): void
    {
        $this->clear_output_buffers();
        $payload = ['ok' => true] + ($message !== '' ? ['message' => $message] : []) + $data;
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function json_error(string $message, int $statusCode = 400, array $data = []): void
    {
        $this->clear_output_buffers();
        $this->output
            ->set_status_header($statusCode)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok' => false,
                'message' => $message,
            ] + $data, JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public function component_opname()
    {
        $pageCode = $this->can(self::PAGE_COMPONENT_OPNAME_MONTHLY, 'view')
            ? self::PAGE_COMPONENT_OPNAME_MONTHLY
            : 'production.component.daily.index';
        $this->require_permission($pageCode, 'view');

        $month = trim((string)$this->input->get('month', true));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $filters = [
            'month'         => $month,
            'location_type' => $this->normalize_location_filter($this->input->get('location_type', true)),
            'division_id'   => (int)$this->input->get('division_id', true),
            'q'             => trim((string)$this->input->get('q', true)),
        ];
        $rows = $this->Production_model->list_component_monthly_opname($filters, 500);

        $this->render('production/component_opname_monthly_index', [
            'page_title'  => 'Stok Opname Bulanan Component',
            'active_menu' => 'production.component.opname.monthly',
            'rows'        => $rows,
            'filters'     => $filters,
            'divisions'   => $this->active_divisions(),
            'generate_url' => site_url('production/component-openings/generate-monthly'),
        ]);
    }

    public function component_opening_monthly()
    {
        $pageCode = $this->can(self::PAGE_COMPONENT_OPNAME_MONTHLY, 'view')
            ? self::PAGE_COMPONENT_OPNAME_MONTHLY
            : 'production.component.daily.index';
        $this->require_permission($pageCode, 'view');

        $month = trim((string)$this->input->get('month', true));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $filters = [
            'month'         => $month,
            'location_type' => $this->normalize_location_filter($this->input->get('location_type', true)),
            'division_id'   => (int)$this->input->get('division_id', true),
            'q'             => trim((string)$this->input->get('q', true)),
        ];
        $rows = $this->Production_model->list_component_monthly_openings($filters, 500);

        $this->render('production/component_opening_monthly_index', [
            'page_title'  => 'Opening Stok Bulanan Component',
            'active_menu' => 'production.component.opening.monthly',
            'rows'        => $rows,
            'filters'     => $filters,
            'divisions'   => $this->active_divisions(),
        ]);
    }

    private function clear_output_buffers(): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }
}
