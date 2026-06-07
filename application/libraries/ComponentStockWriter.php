<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ComponentStockWriter
{
    /** @var CI_Controller */
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
    }

    public function post_opening(array $header, array $lines, int $actorEmployeeId = 0): array
    {
        $this->ci->load->library('ComponentLotManager');
        $lotReady = $this->ci->componentlotmanager->ensureReady();
        if (!($lotReady['ok'] ?? false)) {
            return $lotReady;
        }

        $db = $this->ci->db;
        $locationType = strtoupper(trim((string)($header['location_type'] ?? '')));
        $divisionId = isset($header['division_id']) ? (int)$header['division_id'] : null;
        $movementDate = (string)($header['opening_date'] ?? $header['movement_date'] ?? date('Y-m-d'));
        $sourceId = (int)($header['id'] ?? 0);
        if (!$this->valid_location($locationType)) {
            return ['ok' => false, 'message' => 'Lokasi tidak valid.'];
        }
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $movementDate)) {
            return ['ok' => false, 'message' => 'Tanggal dokumen tidak valid.'];
        }
        if (empty($lines)) {
            return ['ok' => false, 'message' => 'Tidak ada baris untuk diposting.'];
        }

        $db->trans_start();
        try {
            foreach ($lines as $line) {
                $componentId = (int)($line['component_id'] ?? 0);
                $uomId = (int)($line['uom_id'] ?? 0);
                $qty = round((float)($line['qty'] ?? $line['opening_qty'] ?? 0), 4);
                if ($componentId <= 0 || $uomId <= 0 || $qty <= 0) {
                    continue;
                }

                $unitCost = isset($line['unit_cost']) && $line['unit_cost'] !== null
                    ? round((float)$line['unit_cost'], 6)
                    : $this->current_avg_cost($locationType, $divisionId, $componentId, $uomId);
                $sourceLineId = isset($line['source_line_id']) ? (int)$line['source_line_id'] : (isset($line['id']) ? (int)$line['id'] : null);
                $receiptDate = trim((string)($line['received_date'] ?? $movementDate));
                if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $receiptDate)) {
                    $receiptDate = $movementDate;
                }
                $lotNo = trim((string)($line['lot_no'] ?? ''));
                if ($lotNo === '') {
                    $lotNo = $this->generate_component_opening_lot_no($movementDate, $componentId, $sourceId, (int)$sourceLineId);
                }

                $this->post_single_movement([
                    'movement_date' => $movementDate,
                    'location_type' => $locationType,
                    'division_id' => $divisionId,
                    'component_id' => $componentId,
                    'uom_id' => $uomId,
                    'movement_type' => 'OPENING',
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'source_module' => 'PRODUCTION_OPENING',
                    'source_table' => 'inv_component_opening',
                    'source_id' => $sourceId > 0 ? $sourceId : null,
                    'source_line_id' => $sourceLineId,
                    'notes' => (string)($line['note'] ?? $line['notes'] ?? ''),
                    'actor_employee_id' => $actorEmployeeId,
                ]);

                $lotRegister = $this->ci->componentlotmanager->registerProductionInboundLot([
                    'location_type' => $locationType,
                    'division_id' => $divisionId,
                    'component_id' => $componentId,
                    'uom_id' => $uomId,
                    'qty_in' => $qty,
                    'unit_cost' => $unitCost,
                    'lot_no' => $lotNo,
                    'receipt_date' => $receiptDate,
                    'source_module' => 'PRODUCTION_OPENING',
                    'source_table' => 'inv_component_opening',
                    'source_id' => $sourceId > 0 ? $sourceId : null,
                    'source_line_id' => $sourceLineId,
                ]);
                if (!($lotRegister['ok'] ?? false)) {
                    throw new RuntimeException((string)($lotRegister['message'] ?? 'Registrasi lot opening component gagal.'));
                }
            }
        } catch (RuntimeException $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $db->trans_complete();
        if ($db->trans_status() === false) {
            return ['ok' => false, 'message' => 'Posting opening gagal.'];
        }
        return [
            'ok' => true,
            'availability_rebuild' => $this->trigger_availability_refresh(
                array_values(array_unique(array_filter(array_map(static function (array $line): int {
                    return (int)($line['component_id'] ?? 0);
                }, $lines)))),
                'COMPONENT_OPENING',
                $actorEmployeeId,
                $sourceId
            ),
        ];
    }

    public function post_adjustment(array $header, array $lines, int $actorEmployeeId = 0): array
    {
        $this->ci->load->library('ComponentLotManager');
        $lotReady = $this->ci->componentlotmanager->ensureReady();
        if (!($lotReady['ok'] ?? false)) {
            return $lotReady;
        }

        $db = $this->ci->db;
        $locationType = strtoupper(trim((string)($header['location_type'] ?? '')));
        $divisionId = isset($header['division_id']) ? (int)($header['division_id']) : null;
        $movementDate = (string)($header['adjustment_date'] ?? $header['movement_date'] ?? date('Y-m-d'));
        $sourceId = (int)($header['id'] ?? 0);
        if (!$this->valid_location($locationType)) {
            return ['ok' => false, 'message' => 'Lokasi adjustment tidak valid.'];
        }
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $movementDate)) {
            return ['ok' => false, 'message' => 'Tanggal adjustment tidak valid.'];
        }
        if (empty($lines)) {
            return ['ok' => false, 'message' => 'Tidak ada baris adjustment untuk diposting.'];
        }

        $db->trans_start();
        try {
            $rebuildIdentities = [];
            foreach ($lines as $line) {
                $componentId = (int)($line['component_id'] ?? 0);
                $uomId = (int)($line['uom_id'] ?? 0);
                $sourceLineId = isset($line['id']) ? (int)$line['id'] : null;
                $note = (string)($line['note'] ?? '');
                if ($componentId <= 0 || $uomId <= 0) {
                    continue;
                }

                $rebuildKey = implode('|', [
                    $locationType,
                    $divisionId !== null ? (string)$divisionId : 'NULL',
                    (string)$componentId,
                    (string)$uomId,
                ]);
                $rebuildIdentities[$rebuildKey] = [
                    'location_type' => $locationType,
                    'division_id' => $divisionId,
                    'component_id' => $componentId,
                    'uom_id' => $uomId,
                ];

                $outMovements = [
                    'SPOIL' => round((float)($line['qty_spoil'] ?? 0), 4),
                    'WASTE' => round((float)($line['qty_waste'] ?? 0), 4),
                    'ADJUSTMENT_MINUS' => round((float)($line['qty_adjust_neg'] ?? 0), 4),
                ];
                foreach ($outMovements as $movementType => $qty) {
                    if ($qty <= 0) {
                        continue;
                    }
                    $lotIssue = $this->ci->componentlotmanager->consumeUsage([
                        'issue_date' => $movementDate,
                        'location_type' => $locationType,
                        'division_id' => $divisionId,
                        'component_id' => $componentId,
                        'uom_id' => $uomId,
                        'lot_id' => !empty($line['selected_lot_id']) ? (int)$line['selected_lot_id'] : null,
                        'qty_out' => $qty,
                        'source_module' => 'PRODUCTION_ADJUSTMENT',
                        'source_table' => 'inv_component_adjustment',
                        'source_id' => $sourceId > 0 ? $sourceId : null,
                        'source_line_id' => $sourceLineId,
                        'notes' => $note !== '' ? $note : ('Adjustment ' . $movementType),
                    ]);
                    if (!($lotIssue['ok'] ?? false)) {
                        throw new RuntimeException((string)($lotIssue['message'] ?? 'Posting issue lot adjustment gagal.'));
                    }

                    $avgUnitCost = round((float)($lotIssue['data']['avg_unit_cost'] ?? 0), 6);
                    $allocations = (array)($lotIssue['data']['allocations'] ?? []);
                    $lotSnapshot = !empty($allocations[0]['lot_no']) ? (string)$allocations[0]['lot_no'] : null;
                    $this->post_single_movement([
                        'movement_date' => $movementDate,
                        'location_type' => $locationType,
                        'division_id' => $divisionId,
                        'component_id' => $componentId,
                        'uom_id' => $uomId,
                        'movement_type' => $movementType,
                        'qty' => $qty,
                        'unit_cost' => $avgUnitCost,
                        'source_module' => 'PRODUCTION_ADJUSTMENT',
                        'source_table' => 'inv_component_adjustment',
                        'source_id' => $sourceId > 0 ? $sourceId : null,
                        'source_line_id' => $sourceLineId,
                        'lot_no_snapshot' => $lotSnapshot,
                        'notes' => $note,
                        'actor_employee_id' => $actorEmployeeId,
                    ]);
                }

                $qtyPlus = round((float)($line['qty_adjust_pos'] ?? 0), 4);
                if ($qtyPlus > 0) {
                    $unitCost = isset($line['unit_cost']) ? round((float)$line['unit_cost'], 6) : 0.0;
                    $lotNo = $this->generate_component_adjustment_lot_no($movementDate, $componentId, $sourceId, (int)$sourceLineId, 'P');
                    $this->post_single_movement([
                        'movement_date' => $movementDate,
                        'location_type' => $locationType,
                        'division_id' => $divisionId,
                        'component_id' => $componentId,
                        'uom_id' => $uomId,
                        'movement_type' => 'ADJUSTMENT_PLUS',
                        'qty' => $qtyPlus,
                        'unit_cost' => $unitCost,
                        'source_module' => 'PRODUCTION_ADJUSTMENT',
                        'source_table' => 'inv_component_adjustment',
                        'source_id' => $sourceId > 0 ? $sourceId : null,
                        'source_line_id' => $sourceLineId,
                        'lot_no_snapshot' => $lotNo,
                        'received_date_snapshot' => $movementDate,
                        'notes' => $note,
                        'actor_employee_id' => $actorEmployeeId,
                    ]);

                    $lotRegister = $this->ci->componentlotmanager->registerProductionInboundLot([
                        'location_type' => $locationType,
                        'division_id' => $divisionId,
                        'component_id' => $componentId,
                        'uom_id' => $uomId,
                        'qty_in' => $qtyPlus,
                        'unit_cost' => $unitCost,
                        'lot_no' => $lotNo,
                        'receipt_date' => $movementDate,
                        'source_module' => 'PRODUCTION_ADJUSTMENT',
                        'source_table' => 'inv_component_adjustment',
                        'source_id' => $sourceId > 0 ? $sourceId : null,
                        'source_line_id' => $sourceLineId,
                    ]);
                    if (!($lotRegister['ok'] ?? false)) {
                        throw new RuntimeException((string)($lotRegister['message'] ?? 'Registrasi lot adjustment plus gagal.'));
                    }
                }
            }

            if (!empty($rebuildIdentities)) {
                $this->ci->load->model('Production_model');
                foreach (array_values($rebuildIdentities) as $identity) {
                    $rebuild = $this->ci->Production_model->rebuild_component_history_for_identity($identity);
                    if (!($rebuild['ok'] ?? false)) {
                        throw new RuntimeException((string)($rebuild['message'] ?? 'Gagal sinkron saldo bulanan component setelah adjustment.'));
                    }
                }
            }
        } catch (RuntimeException $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $db->trans_complete();
        if ($db->trans_status() === false) {
            return ['ok' => false, 'message' => 'Posting adjustment gagal.'];
        }
        return [
            'ok' => true,
            'availability_rebuild' => $this->trigger_availability_refresh(
                array_values(array_unique(array_filter(array_map(static function (array $line): int {
                    return (int)($line['component_id'] ?? 0);
                }, $lines)))),
                'COMPONENT_ADJUSTMENT',
                $actorEmployeeId,
                $sourceId
            ),
        ];
    }

    public function post_batch(array $header, array $inputs, int $actorEmployeeId = 0): array
    {
        $db = $this->ci->db;
        $locationType = strtoupper(trim((string)($header['location_type'] ?? '')));
        $divisionId = isset($header['division_id']) ? (int)$header['division_id'] : null;
        $movementDate = (string)($header['batch_date'] ?? date('Y-m-d'));
        $sourceId = (int)($header['id'] ?? 0);
        $componentIdOutput = (int)($header['component_id'] ?? 0);
        $uomIdOutput = (int)($header['output_uom_id'] ?? 0);
        $outputQty = round((float)($header['output_qty'] ?? 0), 4);

        if ($componentIdOutput <= 0 || $uomIdOutput <= 0 || $outputQty <= 0 || !$this->valid_location($locationType)) {
            return ['ok' => false, 'message' => 'Header batch tidak valid untuk posting.'];
        }
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $movementDate)) {
            return ['ok' => false, 'message' => 'Tanggal batch tidak valid.'];
        }

        $this->ci->load->library('ComponentLotManager');
        $lotReady = $this->ci->componentlotmanager->ensureReady();
        if (!($lotReady['ok'] ?? false)) {
            return $lotReady;
        }

        $destinationType = $this->resolve_inventory_destination_type($locationType);
        $hasMaterialInput = false;
        foreach ($inputs as $line) {
            if (strtoupper(trim((string)($line['source_kind'] ?? ''))) === 'MATERIAL') {
                $hasMaterialInput = true;
                break;
            }
        }
        if ($hasMaterialInput) {
            if ($divisionId === null || $divisionId <= 0 || $destinationType === null) {
                return ['ok' => false, 'message' => 'Batch dengan input MATERIAL wajib memiliki division_id dan location_type yang sinkron ke stok divisi.'];
            }
            $this->ci->load->library('MaterialFifoManager');
            $fifoReady = $this->ci->materialfifomanager->ensureReady();
            if (!($fifoReady['ok'] ?? false)) {
                return $fifoReady;
            }
            $this->ci->load->library('InventoryLedger');
        }

        $db->trans_start();
        try {
            $totalInputCost = 0.0;
            $headerRecordedTotalInputCost = round((float)($header['total_input_cost'] ?? 0), 2);
            $pendingMaterialSyncTargets = [];
            $pendingMaterialIds = [];
            foreach ($inputs as $line) {
                $planRole = strtoupper(trim((string)($line['plan_role'] ?? 'INPUT')));
                $sourceKind = strtoupper(trim((string)($line['source_kind'] ?? '')));
                if ($planRole === 'INLINE_OUTPUT') {
                    $componentId = (int)($line['component_id'] ?? 0);
                    $uomId = (int)($line['uom_id'] ?? 0);
                    $qty = round((float)($line['qty'] ?? 0), 4);
                    if ($componentId <= 0 || $uomId <= 0 || $qty <= 0) {
                        continue;
                    }

                    $this->post_single_movement([
                        'movement_date' => $movementDate,
                        'location_type' => $locationType,
                        'division_id' => $divisionId,
                        'component_id' => $componentId,
                        'uom_id' => $uomId,
                        'movement_type' => 'PRODUCTION_IN',
                        'qty' => $qty,
                        'unit_cost' => round((float)($line['unit_cost'] ?? 0), 6),
                        'source_module' => 'PRODUCTION_BATCH',
                        'source_table' => 'inv_component_batch',
                        'source_id' => $sourceId > 0 ? $sourceId : null,
                        'source_line_id' => isset($line['id']) ? (int)$line['id'] : null,
                        'notes' => (string)($line['notes'] ?? 'Inline production output'),
                        'actor_employee_id' => $actorEmployeeId,
                    ]);
                    continue;
                }
                if ($sourceKind === 'MATERIAL') {
                    $materialUsage = $this->post_material_input_usage($header, $line, $movementDate, $locationType, (int)$divisionId, (string)$destinationType, $actorEmployeeId);
                    if (!($materialUsage['ok'] ?? false)) {
                        throw new RuntimeException((string)($materialUsage['message'] ?? 'Posting material usage gagal.'));
                    }
                    $totalInputCost += round((float)($materialUsage['line_cost'] ?? 0), 2);
                    foreach ((array)($materialUsage['post_sync_targets'] ?? []) as $targetKey => $target) {
                        if (is_array($target)) {
                            $pendingMaterialSyncTargets[(string)$targetKey] = $target;
                        }
                    }
                    foreach ((array)($materialUsage['material_ids'] ?? []) as $materialId) {
                        $materialId = (int)$materialId;
                        if ($materialId > 0) {
                            $pendingMaterialIds[$materialId] = $materialId;
                        }
                    }
                    continue;
                }
                if ($sourceKind !== 'COMPONENT') {
                    continue;
                }
                $componentId = (int)($line['component_id'] ?? 0);
                $uomId = (int)($line['uom_id'] ?? 0);
                $qty = round((float)($line['qty'] ?? 0), 4);
                if ($componentId <= 0 || $uomId <= 0 || $qty <= 0) {
                    continue;
                }

                $unitCost = isset($line['unit_cost']) ? round((float)$line['unit_cost'], 6) : $this->current_avg_cost($locationType, $divisionId, $componentId, $uomId);
                $lineCost = round($qty * $unitCost, 2);
                if ($planRole !== 'INLINE_COMPONENT_USAGE') {
                    $totalInputCost += $lineCost;
                }

                $this->post_single_movement([
                    'movement_date' => $movementDate,
                    'location_type' => $locationType,
                    'division_id' => $divisionId,
                    'component_id' => $componentId,
                    'uom_id' => $uomId,
                    'movement_type' => 'PRODUCTION_OUT',
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'source_module' => 'PRODUCTION_BATCH',
                    'source_table' => 'inv_component_batch',
                    'source_id' => $sourceId > 0 ? $sourceId : null,
                    'source_line_id' => isset($line['id']) ? (int)$line['id'] : null,
                    'notes' => (string)($line['notes'] ?? ''),
                    'actor_employee_id' => $actorEmployeeId,
                ]);
            }

            $extraCost = max(0, round($headerRecordedTotalInputCost - $totalInputCost, 2));
            $finalTotalInputCost = round($totalInputCost + $extraCost, 2);
            $unitCostOutput = $outputQty > 0 ? round($finalTotalInputCost / $outputQty, 6) : 0.0;
            $outputLotNo = $this->generate_component_output_lot_no($movementDate, $componentIdOutput, $sourceId);
            $this->post_single_movement([
                'movement_date' => $movementDate,
                'location_type' => $locationType,
                'division_id' => $divisionId,
                'component_id' => $componentIdOutput,
                'uom_id' => $uomIdOutput,
                'movement_type' => 'PRODUCTION_IN',
                'qty' => $outputQty,
                'unit_cost' => $unitCostOutput,
                'source_module' => 'PRODUCTION_BATCH',
                'source_table' => 'inv_component_batch',
                'source_id' => $sourceId > 0 ? $sourceId : null,
                'source_line_id' => null,
                'lot_no_snapshot' => $outputLotNo,
                'received_date_snapshot' => $movementDate,
                'notes' => (string)($header['notes'] ?? ''),
                'actor_employee_id' => $actorEmployeeId,
            ]);

            $lotRegister = $this->ci->componentlotmanager->registerProductionInboundLot([
                'location_type' => $locationType,
                'division_id' => $divisionId,
                'component_id' => $componentIdOutput,
                'uom_id' => $uomIdOutput,
                'qty_in' => $outputQty,
                'unit_cost' => $unitCostOutput,
                'lot_no' => $outputLotNo,
                'receipt_date' => $movementDate,
                'source_module' => 'PRODUCTION_BATCH',
                'source_table' => 'inv_component_batch',
                'source_id' => $sourceId > 0 ? $sourceId : null,
                'source_line_id' => null,
            ]);
            if (!($lotRegister['ok'] ?? false)) {
                throw new RuntimeException((string)($lotRegister['message'] ?? 'Registrasi lot component gagal.'));
            }

            if ($sourceId > 0) {
                $this->ci->db->where('id', $sourceId)->update('inv_component_batch', [
                    'total_input_cost' => $finalTotalInputCost,
                    'unit_cost' => $unitCostOutput,
                ]);
            }

            foreach (array_values($pendingMaterialSyncTargets) as $syncTarget) {
                $syncMonthly = $this->ci->materialfifomanager->syncDivisionMonthlyStockFromLots($syncTarget);
                if (!($syncMonthly['ok'] ?? false)) {
                    throw new RuntimeException('Gagal sinkron saldo bulanan dari FIFO lot setelah posting batch. Detail: ' . (string)($syncMonthly['message'] ?? 'unknown error'));
                }
            }
        } catch (RuntimeException $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
        $db->trans_complete();
        if ($db->trans_status() === false) {
            return ['ok' => false, 'message' => 'Posting batch gagal.'];
        }
        if ($sourceId > 0) {
            $this->ci->db->where('id', $sourceId)->update('inv_component_batch', [
                'status' => 'POSTED',
                'posted_at' => date('Y-m-d H:i:s'),
                'posted_by' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
            ]);
        }
        $availabilityRefresh = $this->trigger_availability_refresh(
            array_values(array_unique(array_filter(array_merge(
                [(int)$componentIdOutput],
                array_map(static function (array $line): int {
                    return (int)($line['component_id'] ?? 0);
                }, $inputs)
            )))),
            'COMPONENT_BATCH',
            $actorEmployeeId,
            $sourceId
        );

        $materialAvailabilityRefresh = null;
        if (!empty($pendingMaterialIds)) {
            $this->ci->load->library('PosAvailabilityRebuildService');
            $materialResults = [];
            $materialSuccess = 0;
            $materialFailed = 0;
            foreach (array_values($pendingMaterialIds) as $materialId) {
                $result = $this->ci->posavailabilityrebuildservice->handle_material_change((int)$materialId, [
                    'trigger_context' => 'COMPONENT_BATCH',
                    'event_source' => 'COMPONENT_BATCH_MATERIAL_USAGE',
                    'event_table' => 'inv_component_batch',
                    'event_id' => $sourceId,
                    'actor_employee_id' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
                ]);
                $materialResults[] = ['material_id' => (int)$materialId] + $result;
                if ($result['ok'] ?? false) {
                    $materialSuccess++;
                } else {
                    $materialFailed++;
                }
            }
            $materialAvailabilityRefresh = [
                'material_count' => count($pendingMaterialIds),
                'success_count' => $materialSuccess,
                'failed_count' => $materialFailed,
                'results' => $materialResults,
            ];
        }

        return [
            'ok' => true,
            'availability_rebuild' => $availabilityRefresh,
            'material_availability_rebuild' => $materialAvailabilityRefresh,
        ];
    }

    private function post_document_movements(string $docType, array $header, array $lines, int $actorEmployeeId, array $sourceMeta): array
    {
        $db = $this->ci->db;
        $locationType = strtoupper(trim((string)($header['location_type'] ?? '')));
        $divisionId = isset($header['division_id']) ? (int)$header['division_id'] : null;
        $movementDate = (string)($header[strtolower($docType) . '_date'] ?? $header['movement_date'] ?? date('Y-m-d'));
        $sourceId = (int)($header['id'] ?? 0);
        if (!$this->valid_location($locationType)) {
            return ['ok' => false, 'message' => 'Lokasi tidak valid.'];
        }
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $movementDate)) {
            return ['ok' => false, 'message' => 'Tanggal dokumen tidak valid.'];
        }
        if (empty($lines)) {
            return ['ok' => false, 'message' => 'Tidak ada baris untuk diposting.'];
        }

        $db->trans_start();
        try {
            foreach ($lines as $line) {
                $componentId = (int)($line['component_id'] ?? 0);
                $uomId = (int)($line['uom_id'] ?? 0);
                $movementType = strtoupper(trim((string)($line['movement_type'] ?? ($docType === 'OPENING' ? 'OPENING' : ''))));
                $qty = round((float)($line['qty'] ?? $line['opening_qty'] ?? 0), 4);
                if ($componentId <= 0 || $uomId <= 0 || $movementType === '' || $qty <= 0) {
                    continue;
                }

                $unitCost = isset($line['unit_cost']) && $line['unit_cost'] !== null
                    ? round((float)$line['unit_cost'], 6)
                    : $this->current_avg_cost($locationType, $divisionId, $componentId, $uomId);

                $this->post_single_movement([
                    'movement_date' => $movementDate,
                    'location_type' => $locationType,
                    'division_id' => $divisionId,
                    'component_id' => $componentId,
                    'uom_id' => $uomId,
                    'movement_type' => $movementType,
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'source_module' => (string)$sourceMeta['source_module'],
                    'source_table' => (string)$sourceMeta['source_table'],
                    'source_id' => $sourceId > 0 ? $sourceId : null,
                    'source_line_id' => isset($line['source_line_id']) ? (int)$line['source_line_id'] : (isset($line['id']) ? (int)$line['id'] : null),
                    'notes' => (string)($line['note'] ?? $line['notes'] ?? ''),
                    'actor_employee_id' => $actorEmployeeId,
                ]);
            }
        } catch (RuntimeException $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
        $db->trans_complete();
        if ($db->trans_status() === false) {
            return ['ok' => false, 'message' => 'Posting dokumen gagal.'];
        }
        return [
            'ok' => true,
            'availability_rebuild' => $this->trigger_availability_refresh(
                array_values(array_unique(array_filter(array_map(static function (array $line): int {
                    return (int)($line['component_id'] ?? 0);
                }, $lines)))),
                'COMPONENT_' . strtoupper($docType),
                $actorEmployeeId,
                $sourceId
            ),
        ];
    }

    private function trigger_availability_refresh(array $componentIds, string $eventSource, int $actorEmployeeId = 0, ?int $eventId = null): ?array
    {
        $componentIds = array_values(array_unique(array_filter(array_map('intval', $componentIds))));
        if (empty($componentIds)) {
            return null;
        }

        $this->ci->load->library('PosAvailabilityRebuildService');
        $success = 0;
        $failed = 0;
        foreach ($componentIds as $componentId) {
            $result = $this->ci->posavailabilityrebuildservice->handle_component_change($componentId, [
                'trigger_context' => $eventSource,
                'event_source' => $eventSource,
                'event_table' => 'inv_component_movement_log',
                'event_id' => $eventId,
                'actor_employee_id' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
            ]);
            $success += (int)($result['success_count'] ?? 0);
            $failed += (int)($result['failed_count'] ?? 0);
        }

        return [
            'component_count' => count($componentIds),
            'success_count' => $success,
            'failed_count' => $failed,
        ];
    }

    private function post_single_movement(array $p): void
    {
        $movementType = strtoupper((string)$p['movement_type']);
        $qty = round((float)$p['qty'], 4);
        $isIn = in_array($movementType, ['OPENING', 'PRODUCTION_IN', 'TRANSFER_IN', 'ADJUSTMENT_PLUS', 'VOID_REVERSE'], true);
        $qtyIn = $isIn ? $qty : 0.0;
        $qtyOut = $isIn ? 0.0 : $qty;
        $unitCost = round((float)($p['unit_cost'] ?? 0), 6);
        $totalCost = round($qty * $unitCost, 2);

        $authoritativeBalance = $this->load_balance_state(
            (string)$p['location_type'],
            isset($p['division_id']) ? (int)$p['division_id'] : null,
            (int)$p['component_id'],
            (int)$p['uom_id'],
            (string)$p['movement_date']
        );

        $qtyBefore = (float)($authoritativeBalance['qty_on_hand'] ?? 0);
        $avgBefore = (float)($authoritativeBalance['avg_cost'] ?? 0);
        $valueBefore = round((float)($authoritativeBalance['total_value'] ?? ($qtyBefore * $avgBefore)), 2);
        $qtyAfter = round($qtyBefore + $qtyIn - $qtyOut, 4);
        if ($qtyAfter < -0.0001) {
            throw new RuntimeException('Stok komponen tidak cukup untuk movement ' . $movementType . '.');
        }
        if (abs($qtyAfter) < 0.0001) {
            $qtyAfter = 0.0;
        }

        if ($qtyIn > 0) {
            $valueAfter = round($valueBefore + $totalCost, 2);
            $avgAfter = $qtyAfter > 0 ? round($valueAfter / $qtyAfter, 6) : 0.0;
        } else {
            $valueOut = round($qtyOut * $avgBefore, 2);
            $valueAfter = round(max(0, $valueBefore - $valueOut), 2);
            $avgAfter = $qtyAfter > 0 ? round($valueAfter / $qtyAfter, 6) : 0.0;
        }

        $now = date('Y-m-d H:i:s');

        $this->ci->db->insert('inv_component_movement_log', [
            'movement_no' => $this->generate_movement_no((string)$p['movement_date']),
            'movement_date' => $p['movement_date'],
            'movement_datetime' => $p['movement_date'] . ' ' . date('H:i:s'),
            'location_type' => $p['location_type'],
            'division_id' => $p['division_id'],
            'component_id' => $p['component_id'],
            'uom_id' => $p['uom_id'],
            'movement_type' => $movementType,
            'qty_in' => $qtyIn,
            'qty_out' => $qtyOut,
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'source_module' => $p['source_module'],
            'source_table' => $p['source_table'],
            'source_id' => $p['source_id'],
            'source_line_id' => $p['source_line_id'],
            'lot_no_snapshot' => $p['lot_no_snapshot'] ?? null,
            'received_date_snapshot' => $p['received_date_snapshot'] ?? null,
            'notes' => $p['notes'] !== '' ? $p['notes'] : null,
            'created_by' => $p['actor_employee_id'] > 0 ? (int)$p['actor_employee_id'] : null,
            'created_at' => $now,
        ]);

        $this->sync_monthly_stock([
            'movement_date' => (string)$p['movement_date'],
            'movement_datetime' => $p['movement_date'] . ' ' . date('H:i:s'),
            'location_type' => (string)$p['location_type'],
            'division_id' => $p['division_id'],
            'component_id' => (int)$p['component_id'],
            'uom_id' => (int)$p['uom_id'],
            'movement_type' => $movementType,
            'qty_in' => $qtyIn,
            'qty_out' => $qtyOut,
            'source_table' => (string)($p['source_table'] ?? ''),
            'source_id' => isset($p['source_id']) ? (int)$p['source_id'] : null,
            'notes' => $p['notes'] ?? '',
        ], $qtyBefore, $qtyAfter, $avgAfter, $valueAfter);

    }

    private function sync_monthly_stock(array $movement, float $qtyBefore, float $qtyAfter, float $avgAfter, float $valueAfter): void
    {
        if (!$this->ci->db->table_exists('inv_component_monthly_stock')) {
            return;
        }

        $movementDate = (string)($movement['movement_date'] ?? '');
        $locationType = strtoupper(trim((string)($movement['location_type'] ?? '')));
        $divisionId = isset($movement['division_id']) && $movement['division_id'] !== null ? (int)$movement['division_id'] : null;
        $componentId = (int)($movement['component_id'] ?? 0);
        $uomId = (int)($movement['uom_id'] ?? 0);
        $movementType = strtoupper(trim((string)($movement['movement_type'] ?? '')));
        $qtyIn = round((float)($movement['qty_in'] ?? 0), 4);
        $qtyOut = round((float)($movement['qty_out'] ?? 0), 4);
        if ($movementDate === '' || $componentId <= 0 || $uomId <= 0 || $locationType === '') {
            return;
        }

        $monthKey = date('Y-m-01', strtotime($movementDate));
        $row = $this->ci->db->query(
            'SELECT * FROM inv_component_monthly_stock WHERE month_key = ? AND location_type = ? AND division_id <=> ? AND component_id = ? AND uom_id = ? LIMIT 1 FOR UPDATE',
            [$monthKey, $locationType, $divisionId, $componentId, $uomId]
        )->row_array();

        $isOpeningSnapshot = $movementType === 'OPENING';
        $movementDayCount = (int)($row['movement_day_count'] ?? 0);
        if ($row === null || (string)($row['last_movement_date'] ?? '') !== $movementDate) {
            $movementDayCount++;
        }
        $movementValue = round(($qtyIn > 0 ? $qtyIn : $qtyOut) * max($avgAfter, 0), 2);

        $data = [
            'month_key' => $monthKey,
            'location_type' => $locationType,
            'division_id' => $divisionId,
            'component_id' => $componentId,
            'uom_id' => $uomId,
            'opening_qty' => $isOpeningSnapshot
                ? ($row ? round((float)($row['opening_qty'] ?? 0) + $qtyIn, 4) : round($qtyAfter, 4))
                : ($row ? round((float)($row['opening_qty'] ?? 0), 4) : round($qtyBefore, 4)),
            'opening_total_value' => $row ? round((float)($row['opening_total_value'] ?? 0), 2) : 0.0,
            'in_qty' => $row ? round((float)($row['in_qty'] ?? 0), 4) : 0.0,
            'in_total_value' => $row ? round((float)($row['in_total_value'] ?? 0), 2) : 0.0,
            'out_qty' => $row ? round((float)($row['out_qty'] ?? 0), 4) : 0.0,
            'out_total_value' => $row ? round((float)($row['out_total_value'] ?? 0), 2) : 0.0,
            'waste_qty' => $row ? round((float)($row['waste_qty'] ?? 0), 4) : 0.0,
            'waste_total_value' => $row ? round((float)($row['waste_total_value'] ?? 0), 2) : 0.0,
            'spoil_qty' => $row ? round((float)($row['spoil_qty'] ?? 0), 4) : 0.0,
            'spoil_total_value' => $row ? round((float)($row['spoil_total_value'] ?? 0), 2) : 0.0,
            'adjustment_plus_qty' => $row ? round((float)($row['adjustment_plus_qty'] ?? 0), 4) : 0.0,
            'adjustment_plus_total_value' => $row ? round((float)($row['adjustment_plus_total_value'] ?? 0), 2) : 0.0,
            'adjustment_minus_qty' => $row ? round((float)($row['adjustment_minus_qty'] ?? 0), 4) : 0.0,
            'adjustment_minus_total_value' => $row ? round((float)($row['adjustment_minus_total_value'] ?? 0), 2) : 0.0,
            'closing_qty' => round($qtyAfter, 4),
            'avg_cost' => round($avgAfter, 6),
            'total_value' => round($valueAfter, 2),
            'movement_day_count' => $movementDayCount,
            'mutation_count' => $row ? ((int)($row['mutation_count'] ?? 0) + 1) : 1,
            'last_movement_date' => $movementDate,
            'last_movement_at' => (string)($movement['movement_datetime'] ?? ($movementDate . ' ' . date('H:i:s'))),
            'last_movement_table' => (string)($movement['source_table'] ?? '') !== '' ? (string)$movement['source_table'] : null,
            'last_movement_id' => !empty($movement['source_id']) ? (int)$movement['source_id'] : null,
            'source_mode' => 'LIVE',
            'notes' => trim((string)($movement['notes'] ?? '')) !== '' ? trim((string)$movement['notes']) : ($row['notes'] ?? null),
        ];
        if ($isOpeningSnapshot) {
            $data['opening_total_value'] = round((float)$data['opening_total_value'] + ($qtyIn * max($avgAfter, 0)), 2);
        }

        switch ($movementType) {
            case 'PRODUCTION_IN':
            case 'TRANSFER_IN':
                $data['in_qty'] = round((float)$data['in_qty'] + $qtyIn, 4);
                $data['in_total_value'] = round((float)$data['in_total_value'] + $movementValue, 2);
                break;
            case 'PRODUCTION_OUT':
            case 'TRANSFER_OUT':
                $data['out_qty'] = round((float)$data['out_qty'] + $qtyOut, 4);
                $data['out_total_value'] = round((float)$data['out_total_value'] + $movementValue, 2);
                break;
            case 'WASTE':
                $data['waste_qty'] = round((float)$data['waste_qty'] + $qtyOut, 4);
                $data['waste_total_value'] = round((float)$data['waste_total_value'] + $movementValue, 2);
                break;
            case 'SPOIL':
                $data['spoil_qty'] = round((float)$data['spoil_qty'] + $qtyOut, 4);
                $data['spoil_total_value'] = round((float)$data['spoil_total_value'] + $movementValue, 2);
                break;
            case 'ADJUSTMENT_PLUS':
            case 'VOID_REVERSE':
                $data['adjustment_plus_qty'] = round((float)$data['adjustment_plus_qty'] + $qtyIn, 4);
                $data['adjustment_plus_total_value'] = round((float)$data['adjustment_plus_total_value'] + $movementValue, 2);
                break;
            case 'ADJUSTMENT_MINUS':
            case 'VOID_OUT':
                $data['adjustment_minus_qty'] = round((float)$data['adjustment_minus_qty'] + $qtyOut, 4);
                $data['adjustment_minus_total_value'] = round((float)$data['adjustment_minus_total_value'] + $movementValue, 2);
                break;
        }

        if ($row && !empty($row['id'])) {
            $this->ci->db->where('id', (int)$row['id'])->update('inv_component_monthly_stock', $data);
            return;
        }

        $this->ci->db->insert('inv_component_monthly_stock', $data);
    }

    private function post_material_input_usage(array $header, array $line, string $movementDate, string $locationType, int $divisionId, string $destinationType, int $actorEmployeeId): array
    {
        $sourceId = (int)($header['id'] ?? 0);
        $itemId = !empty($line['item_id']) ? (int)$line['item_id'] : null;
        $materialId = !empty($line['material_id']) ? (int)$line['material_id'] : null;
        $uomId = (int)($line['uom_id'] ?? 0);
        $qty = round((float)($line['qty'] ?? 0), 4);
        $sourceLineId = isset($line['id']) ? (int)$line['id'] : null;

        if ($uomId <= 0 || $qty <= 0 || ($itemId === null && $materialId === null)) {
            return ['ok' => false, 'message' => 'Line MATERIAL batch belum memiliki item/material, UOM, atau qty yang valid.'];
        }

        $structureHealth = $this->validate_material_input_stock_structure($line, $divisionId, $destinationType);
        if (!($structureHealth['ok'] ?? false)) {
            return $structureHealth;
        }

        $preflight = $this->ci->materialfifomanager->previewDivisionUsageState([
            'division_id' => $divisionId,
            'destination_type' => $destinationType,
            'item_id' => $itemId,
            'material_id' => $materialId,
            'content_uom_id' => $uomId,
        ]);
        if (!($preflight['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => $this->format_material_usage_error(
                    $line,
                    'Gagal membaca kandidat lot/profile sebelum posting batch.',
                    ['detail' => (string)($preflight['message'] ?? '')]
                ),
            ];
        }

        $preSyncProfiles = [];
        foreach ((array)($preflight['data']['lots'] ?? []) as $lot) {
            $preSyncProfiles[$this->build_material_profile_sync_key($lot)] = [
                'movement_date' => $movementDate,
                'division_id' => $divisionId,
                'destination_type' => $destinationType,
                'item_id' => !empty($lot['item_id']) ? (int)$lot['item_id'] : null,
                'material_id' => !empty($lot['material_id']) ? (int)$lot['material_id'] : null,
                'buy_uom_id' => !empty($lot['buy_uom_id']) ? (int)$lot['buy_uom_id'] : null,
                'content_uom_id' => !empty($lot['content_uom_id']) ? (int)$lot['content_uom_id'] : $uomId,
                'profile_key' => !empty($lot['profile_key']) ? (string)$lot['profile_key'] : null,
                'sync_note' => 'Synced from FIFO lots before component batch posting',
            ];
        }
        foreach (array_values($preSyncProfiles) as $syncTarget) {
            $syncMonthly = $this->ci->materialfifomanager->syncDivisionMonthlyStockFromLots($syncTarget);
            if (!($syncMonthly['ok'] ?? false)) {
                return [
                    'ok' => false,
                    'message' => $this->format_material_usage_error(
                        $line,
                        'Gagal sinkron saldo bulanan dari FIFO lot sebelum posting batch.',
                        ['detail' => (string)($syncMonthly['message'] ?? '')]
                    ),
                ];
            }
        }

        $fifoUsage = $this->ci->materialfifomanager->consumeDivisionUsage([
            'issue_date' => $movementDate,
            'division_id' => $divisionId,
            'destination_type' => $destinationType,
            'item_id' => $itemId,
            'material_id' => $materialId,
            'content_uom_id' => $uomId,
            'qty_content_out' => $qty,
            'source_module' => 'PRODUCTION_BATCH',
            'source_table' => 'inv_component_batch',
            'source_id' => $sourceId > 0 ? $sourceId : null,
            'source_line_id' => $sourceLineId,
            'notes' => 'Batch component memakai stok divisi ' . $locationType,
        ]);
        if (!($fifoUsage['ok'] ?? false)) {
            return $fifoUsage;
        }

        $issueId = (int)($fifoUsage['data']['issue_id'] ?? 0);
        $issueNo = (string)($fifoUsage['data']['issue_no'] ?? '');
        $lineCost = round((float)($fifoUsage['data']['total_cost'] ?? 0), 2);
        $avgUnitCost = round((float)($fifoUsage['data']['avg_unit_cost'] ?? 0), 6);
        $allocations = (array)($fifoUsage['data']['allocations'] ?? []);
        $postSyncProfiles = [];
        $affectedMaterialIds = [];

        foreach ($allocations as $allocation) {
            $snapshot = $this->resolve_inventory_snapshot_for_allocation($allocation, $divisionId, $destinationType);
            $contentPerBuy = max(0.000001, round((float)($snapshot['profile_content_per_buy'] ?? 1), 6));
            $qtyContent = round((float)($allocation['qty_content'] ?? 0), 4);
            $qtyBuy = round($qtyContent / $contentPerBuy, 4);

            $post = $this->ci->inventoryledger->post([
                'movement_scope' => 'DIVISION',
                'movement_date' => $movementDate,
                'movement_type' => 'USAGE_OUT',
                'division_id' => $divisionId,
                'destination_type' => $destinationType,
                'ref_table' => 'inv_component_batch',
                'ref_id' => $sourceId > 0 ? $sourceId : null,
                'item_id' => $snapshot['item_id'],
                'material_id' => $snapshot['material_id'],
                'buy_uom_id' => $snapshot['buy_uom_id'],
                'content_uom_id' => $snapshot['content_uom_id'],
                'qty_buy_delta' => -1 * $qtyBuy,
                'qty_content_delta' => -1 * $qtyContent,
                'profile_key' => $snapshot['profile_key'],
                'profile_name' => $snapshot['profile_name'],
                'profile_brand' => $snapshot['profile_brand'],
                'profile_description' => $snapshot['profile_description'],
                'profile_expired_date' => $snapshot['profile_expired_date'],
                'profile_content_per_buy' => $snapshot['profile_content_per_buy'],
                'profile_buy_uom_code' => $snapshot['profile_buy_uom_code'],
                'profile_content_uom_code' => $snapshot['profile_content_uom_code'],
                'stock_domain' => $snapshot['stock_domain'],
                'unit_cost' => round((float)($allocation['unit_cost'] ?? 0), 6),
                'notes' => 'Batch ' . (string)($header['batch_no'] ?? ('#' . $sourceId)) . ' pakai lot ' . (string)($allocation['source_lot_no'] ?? '-'),
                'created_by' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
                'manage_transaction' => false,
                'skip_availability_refresh' => true,
            ]);
            if (!($post['ok'] ?? false)) {
                return [
                    'ok' => false,
                    'message' => $this->format_material_usage_error(
                        $line,
                        (string)($post['message'] ?? 'Gagal posting usage keluar stok divisi.'),
                        [
                            'profile_key' => $snapshot['profile_key'] ?? null,
                            'buy_uom_id' => $snapshot['buy_uom_id'] ?? null,
                            'content_uom_id' => $snapshot['content_uom_id'] ?? null,
                            'qty_content' => $qtyContent,
                            'source_lot_no' => (string)($allocation['source_lot_no'] ?? ''),
                        ]
                    ),
                ];
            }

            $postSyncProfiles[$this->build_material_profile_sync_key($snapshot)] = [
                'movement_date' => $movementDate,
                'division_id' => $divisionId,
                'destination_type' => $destinationType,
                'item_id' => $snapshot['item_id'],
                'material_id' => $snapshot['material_id'],
                'buy_uom_id' => $snapshot['buy_uom_id'],
                'content_uom_id' => $snapshot['content_uom_id'],
                'profile_key' => $snapshot['profile_key'],
                'sync_note' => 'Synced from FIFO lots after component batch posting',
            ];
            if (!empty($snapshot['material_id'])) {
                $affectedMaterialIds[(int)$snapshot['material_id']] = (int)$snapshot['material_id'];
            }
        }

        if ($sourceLineId !== null && $sourceLineId > 0) {
            $update = [
                'unit_cost' => $avgUnitCost,
                'total_cost' => $lineCost,
            ];
            if ($this->ci->db->field_exists('fifo_issue_id', 'inv_component_batch_input')) {
                $update['fifo_issue_id'] = $issueId > 0 ? $issueId : null;
            }
            if ($this->ci->db->field_exists('fifo_issue_no', 'inv_component_batch_input')) {
                $update['fifo_issue_no'] = $issueNo !== '' ? $issueNo : null;
            }
            $this->ci->db->where('id', $sourceLineId)->update('inv_component_batch_input', $update);
        }

        return [
            'ok' => true,
            'line_cost' => $lineCost,
            'unit_cost' => $avgUnitCost,
            'issue_id' => $issueId,
            'issue_no' => $issueNo,
            'post_sync_targets' => $postSyncProfiles,
            'material_ids' => array_values($affectedMaterialIds),
        ];
    }

    private function validate_material_input_stock_structure(array $line, int $divisionId, string $destinationType): array
    {
        $itemId = !empty($line['item_id']) ? (int)$line['item_id'] : null;
        $materialId = !empty($line['material_id']) ? (int)$line['material_id'] : null;
        if ($materialId === null || $materialId <= 0) {
            return ['ok' => true];
        }

        $sameUomDriftRows = $this->ci->db->query(
            'SELECT id, profile_key, buy_uom_id, content_uom_id, profile_content_per_buy, closing_qty_content
             FROM inv_division_monthly_stock
             WHERE division_id = ?
               AND destination_type = ?
               AND material_id = ?
               AND buy_uom_id IS NOT NULL
               AND buy_uom_id = content_uom_id
               AND ABS(COALESCE(profile_content_per_buy, 1) - 1) > 0.0001
             ORDER BY id ASC',
            [$divisionId, $destinationType, $materialId]
        )->result_array();
        if (!empty($sameUomDriftRows)) {
            $row = $sameUomDriftRows[0];
            return [
                'ok' => false,
                'message' => $this->format_material_usage_error(
                    $line,
                    'Struktur stok divisi masih memakai konversi profile lama yang tidak valid.',
                    [
                        'monthly_stock_id' => (int)($row['id'] ?? 0),
                        'profile_key' => (string)($row['profile_key'] ?? ''),
                        'buy_uom_id' => (int)($row['buy_uom_id'] ?? 0),
                        'content_uom_id' => (int)($row['content_uom_id'] ?? 0),
                        'profile_content_per_buy' => round((float)($row['profile_content_per_buy'] ?? 0), 6),
                        'closing_qty_content' => round((float)($row['closing_qty_content'] ?? 0), 4),
                        'hint' => 'UOM beli dan UOM isi sama, tetapi profile_content_per_buy bukan 1. Repair data profile ini dulu.',
                    ]
                ),
            ];
        }

        $negativeRows = $this->ci->db->query(
            'SELECT
                s.id,
                s.profile_key,
                s.buy_uom_id,
                s.content_uom_id,
                s.profile_content_per_buy,
                s.closing_qty_buy,
                s.closing_qty_content,
                COALESCE(l.live_qty, 0) AS fifo_live_qty
             FROM inv_division_monthly_stock s
             LEFT JOIN (
                SELECT division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key,
                       ROUND(SUM(CASE WHEN qty_balance > 0 THEN qty_balance ELSE 0 END), 4) AS live_qty
                FROM inv_material_fifo_lot
                GROUP BY division_id, destination_type, item_id, material_id, buy_uom_id, content_uom_id, profile_key
             ) l
               ON l.division_id = s.division_id
              AND l.destination_type = s.destination_type
              AND l.item_id <=> s.item_id
              AND l.material_id <=> s.material_id
              AND l.buy_uom_id <=> s.buy_uom_id
              AND l.content_uom_id = s.content_uom_id
              AND l.profile_key <=> s.profile_key
             WHERE s.division_id = ?
               AND s.destination_type = ?
               AND s.material_id = ?
               AND s.closing_qty_content < 0
             ORDER BY s.id ASC',
            [$divisionId, $destinationType, $materialId]
        )->result_array();
        if (!empty($negativeRows)) {
            $row = $negativeRows[0];
            return [
                'ok' => false,
                'message' => $this->format_material_usage_error(
                    $line,
                    'Saldo bulanan profile sudah negatif sebelum batch diposting.',
                    [
                        'monthly_stock_id' => (int)($row['id'] ?? 0),
                        'profile_key' => (string)($row['profile_key'] ?? ''),
                        'closing_qty_buy' => round((float)($row['closing_qty_buy'] ?? 0), 4),
                        'closing_qty_content' => round((float)($row['closing_qty_content'] ?? 0), 4),
                        'fifo_live_qty' => round((float)($row['fifo_live_qty'] ?? 0), 4),
                        'hint' => 'Ini drift data historis. Repair saldo bulanan exact profile ini dulu sebelum posting ulang.',
                    ]
                ),
            ];
        }

        return ['ok' => true];
    }

    private function format_material_usage_error(array $line, string $reason, array $context = []): string
    {
        $name = trim((string)($line['material_name'] ?? $line['item_name'] ?? ''));
        if ($name === '') {
            $itemId = !empty($line['item_id']) ? (int)$line['item_id'] : 0;
            $materialId = !empty($line['material_id']) ? (int)$line['material_id'] : 0;
            if ($itemId > 0) {
                $row = $this->ci->db->select('item_name')->from('mst_item')->where('id', $itemId)->limit(1)->get()->row_array();
                $name = trim((string)($row['item_name'] ?? ''));
            }
            if ($name === '' && $materialId > 0) {
                $row = $this->ci->db->select('material_name')->from('mst_material')->where('id', $materialId)->limit(1)->get()->row_array();
                $name = trim((string)($row['material_name'] ?? ''));
            }
        }
        if ($name === '') {
            $name = 'item/material #' . (int)($line['item_id'] ?? $line['material_id'] ?? 0);
        }

        $parts = ['Batch gagal pada bahan ' . $name . ': ' . $reason];
        if (!empty($context['monthly_stock_id'])) {
            $parts[] = 'monthly_stock_id=' . (int)$context['monthly_stock_id'];
        }
        if (!empty($context['profile_key'])) {
            $parts[] = 'profile_key=' . (string)$context['profile_key'];
        }
        if (isset($context['buy_uom_id']) && isset($context['content_uom_id'])) {
            $parts[] = 'uom_beli=' . (string)$context['buy_uom_id'] . ', uom_isi=' . (string)$context['content_uom_id'];
        }
        if (isset($context['profile_content_per_buy'])) {
            $parts[] = 'profile_content_per_buy=' . (string)$context['profile_content_per_buy'];
        }
        if (isset($context['closing_qty_buy']) || isset($context['closing_qty_content'])) {
            $parts[] = 'saldo_bulanan=('
                . (isset($context['closing_qty_buy']) ? (string)$context['closing_qty_buy'] : '-')
                . ' buy / '
                . (isset($context['closing_qty_content']) ? (string)$context['closing_qty_content'] : '-')
                . ' content)';
        }
        if (isset($context['fifo_live_qty'])) {
            $parts[] = 'fifo_live_qty=' . (string)$context['fifo_live_qty'];
        }
        if (!empty($context['source_lot_no'])) {
            $parts[] = 'lot=' . (string)$context['source_lot_no'];
        }
        if (!empty($context['detail'])) {
            $parts[] = 'detail=' . (string)$context['detail'];
        }
        if (!empty($context['hint'])) {
            $parts[] = (string)$context['hint'];
        }

        return implode(' | ', $parts);
    }

    private function build_material_profile_sync_key(array $identity): string
    {
        return implode('|', [
            (string)($identity['item_id'] ?? 0),
            (string)($identity['material_id'] ?? 0),
            (string)($identity['buy_uom_id'] ?? 0),
            (string)($identity['content_uom_id'] ?? 0),
            (string)($identity['profile_key'] ?? ''),
        ]);
    }

    private function resolve_inventory_snapshot_for_allocation(array $allocation, int $divisionId, string $destinationType): array
    {
        $sourceLot = (array)($allocation['source_lot'] ?? []);
        $snapshot = [
            'item_id' => !empty($sourceLot['item_id']) ? (int)$sourceLot['item_id'] : null,
            'material_id' => !empty($sourceLot['material_id']) ? (int)$sourceLot['material_id'] : null,
            'buy_uom_id' => !empty($sourceLot['buy_uom_id']) ? (int)$sourceLot['buy_uom_id'] : null,
            'content_uom_id' => !empty($sourceLot['content_uom_id']) ? (int)$sourceLot['content_uom_id'] : null,
            'profile_key' => !empty($sourceLot['profile_key']) ? (string)$sourceLot['profile_key'] : null,
            'profile_name' => null,
            'profile_brand' => null,
            'profile_description' => null,
            'profile_expired_date' => $sourceLot['expiry_date'] ?? null,
            'profile_content_per_buy' => 1.0,
            'profile_buy_uom_code' => null,
            'profile_content_uom_code' => null,
            'stock_domain' => !empty($sourceLot['item_id']) ? 'ITEM' : (!empty($sourceLot['material_id']) ? 'MATERIAL' : 'ITEM'),
        ];

        if ($this->ci->db->table_exists('inv_stock_movement_log')) {
            $select = 'item_id, material_id, buy_uom_id, content_uom_id, profile_key, profile_name, profile_brand, profile_description, profile_expired_date, profile_content_per_buy, profile_buy_uom_code, profile_content_uom_code';
            if ($this->ci->db->field_exists('stock_domain', 'inv_stock_movement_log')) {
                $select .= ', stock_domain';
            }
            $row = $this->ci->db->query(
                'SELECT ' . $select . '
                 FROM inv_stock_movement_log
                 WHERE movement_scope = ?
                   AND division_id = ?
                   AND destination_type = ?
                   AND item_id <=> ?
                   AND material_id <=> ?
                   AND buy_uom_id <=> ?
                   AND content_uom_id = ?
                   AND profile_key <=> ?
                 ORDER BY id DESC
                 LIMIT 1',
                [
                    'DIVISION',
                    $divisionId,
                    $destinationType,
                    $snapshot['item_id'],
                    $snapshot['material_id'],
                    $snapshot['buy_uom_id'],
                    (int)$snapshot['content_uom_id'],
                    $snapshot['profile_key'],
                ]
            )->row_array();
            if ($row) {
                $snapshot = array_merge($snapshot, $row);
            }
        }

        $sameUom = !empty($snapshot['buy_uom_id']) && !empty($snapshot['content_uom_id'])
            && (int)$snapshot['buy_uom_id'] === (int)$snapshot['content_uom_id'];
        if ($sameUom) {
            $snapshot['profile_content_per_buy'] = 1.0;
        } else {
            $snapshot['profile_content_per_buy'] = max(0.000001, round((float)($snapshot['profile_content_per_buy'] ?? 1), 6));
        }
        return $snapshot;
    }

    private function generate_component_output_lot_no(string $movementDate, int $componentId, int $sourceId): string
    {
        $datePart = date('Ymd', strtotime($movementDate));
        return 'ICL' . $datePart . str_pad((string)$componentId, 5, '0', STR_PAD_LEFT) . str_pad((string)max(0, $sourceId), 5, '0', STR_PAD_LEFT);
    }

    private function generate_component_opening_lot_no(string $movementDate, int $componentId, int $sourceId, int $sourceLineId): string
    {
        $datePart = date('Ymd', strtotime($movementDate));
        return 'ICO' . $datePart . str_pad((string)$componentId, 5, '0', STR_PAD_LEFT) . str_pad((string)max(0, $sourceId), 5, '0', STR_PAD_LEFT) . str_pad((string)max(0, $sourceLineId), 4, '0', STR_PAD_LEFT);
    }

    private function generate_component_adjustment_lot_no(string $movementDate, int $componentId, int $sourceId, int $sourceLineId, string $suffix = 'A'): string
    {
        $datePart = date('Ymd', strtotime($movementDate));
        return 'ICA' . $datePart . str_pad((string)$componentId, 5, '0', STR_PAD_LEFT) . str_pad((string)max(0, $sourceId), 5, '0', STR_PAD_LEFT) . str_pad((string)max(0, $sourceLineId), 4, '0', STR_PAD_LEFT) . strtoupper(substr($suffix, 0, 1));
    }

    private function load_balance_state(string $locationType, ?int $divisionId, int $componentId, int $uomId, string $movementDate): array
    {
        if ($this->ci->db->table_exists('inv_component_monthly_stock')) {
            $targetMonth = date('Y-m-01', strtotime($movementDate));
            $row = $this->ci->db->query(
                'SELECT month_key, closing_qty AS qty_on_hand, avg_cost, total_value
                 FROM inv_component_monthly_stock
                 WHERE location_type = ? AND division_id <=> ? AND component_id = ? AND uom_id = ? AND month_key <= ?
                 ORDER BY month_key DESC, updated_at DESC, last_movement_at DESC
                 LIMIT 1',
                [$locationType, $divisionId, $componentId, $uomId, $targetMonth]
            )->row_array();
            if (!empty($row)) {
                return $row;
            }
        }

        return [];
    }

    private function current_avg_cost(string $locationType, ?int $divisionId, int $componentId, int $uomId): float
    {
        if ($this->ci->db->table_exists('inv_component_monthly_stock')) {
            $row = $this->ci->db->query(
                'SELECT avg_cost FROM inv_component_monthly_stock WHERE location_type = ? AND division_id <=> ? AND component_id = ? AND uom_id = ? ORDER BY month_key DESC, updated_at DESC, last_movement_at DESC LIMIT 1',
                [$locationType, $divisionId, $componentId, $uomId]
            )->row_array();
            if (!empty($row)) {
                return round((float)($row['avg_cost'] ?? 0), 6);
            }
        }

        return 0.0;
    }

    private function generate_movement_no(string $movementDate): string
    {
        $datePart = date('Ymd', strtotime($movementDate));
        $prefix = 'ICM' . $datePart;
        $row = $this->ci->db->select('movement_no')
            ->from('inv_component_movement_log')
            ->like('movement_no', $prefix, 'after')
            ->order_by('movement_no', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();
        $seq = 1;
        if (!empty($row['movement_no'])) {
            $suffix = substr((string)$row['movement_no'], strlen($prefix));
            if (ctype_digit($suffix)) {
                $seq = ((int)$suffix) + 1;
            }
        }
        return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }

    private function valid_location(string $locationType): bool
    {
        return in_array($locationType, ['BAR', 'KITCHEN', 'BAR_EVENT', 'KITCHEN_EVENT'], true);
    }

    private function resolve_inventory_destination_type(string $locationType): ?string
    {
        $locationType = strtoupper(trim($locationType));
        return in_array($locationType, ['BAR', 'KITCHEN', 'BAR_EVENT', 'KITCHEN_EVENT'], true) ? $locationType : null;
    }
}
