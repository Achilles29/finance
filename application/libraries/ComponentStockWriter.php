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
                    'lot_no' => $this->generate_component_opening_lot_no($movementDate, $componentId, $sourceId, (int)$sourceLineId),
                    'receipt_date' => $movementDate,
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
            foreach ($lines as $line) {
                $componentId = (int)($line['component_id'] ?? 0);
                $uomId = (int)($line['uom_id'] ?? 0);
                $sourceLineId = isset($line['id']) ? (int)$line['id'] : null;
                $note = (string)($line['note'] ?? '');
                if ($componentId <= 0 || $uomId <= 0) {
                    continue;
                }

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
        } catch (RuntimeException $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
        $db->trans_complete();
        if ($db->trans_status() === false) {
            return ['ok' => false, 'message' => 'Posting batch gagal.'];
        }
        return [
            'ok' => true,
            'availability_rebuild' => $this->trigger_availability_refresh(
                array_values(array_unique(array_filter(array_merge(
                    [(int)$componentIdOutput],
                    array_map(static function (array $line): int {
                        return (int)($line['component_id'] ?? 0);
                    }, $inputs)
                )))),
                'COMPONENT_BATCH',
                $actorEmployeeId,
                $sourceId
            ),
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

        $balance = $this->lock_balance_row(
            (string)$p['location_type'],
            isset($p['division_id']) ? (int)$p['division_id'] : null,
            (int)$p['component_id'],
            (int)$p['uom_id']
        );

        $qtyBefore = (float)($balance['qty_on_hand'] ?? 0);
        $avgBefore = (float)($balance['avg_cost'] ?? 0);
        $valueBefore = round($qtyBefore * $avgBefore, 2);
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
        if (!empty($balance['id'])) {
            $this->ci->db->where('id', (int)$balance['id'])->update('inv_component_stock_balance', [
                'qty_on_hand' => $qtyAfter,
                'avg_cost' => $avgAfter,
                'total_value' => $valueAfter,
                'last_txn_at' => $p['movement_date'] . ' ' . date('H:i:s'),
                'updated_at' => $now,
            ]);
        } else {
            $this->ci->db->insert('inv_component_stock_balance', [
                'location_type' => $p['location_type'],
                'division_id' => $p['division_id'],
                'component_id' => $p['component_id'],
                'uom_id' => $p['uom_id'],
                'qty_on_hand' => $qtyAfter,
                'avg_cost' => $avgAfter,
                'total_value' => $valueAfter,
                'last_txn_at' => $p['movement_date'] . ' ' . date('H:i:s'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

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

        $this->sync_daily_rollup([
            'movement_date' => (string)$p['movement_date'],
            'movement_datetime' => $p['movement_date'] . ' ' . date('H:i:s'),
            'location_type' => (string)$p['location_type'],
            'division_id' => $p['division_id'],
            'component_id' => (int)$p['component_id'],
            'uom_id' => (int)$p['uom_id'],
            'movement_type' => $movementType,
            'qty_in' => $qtyIn,
            'qty_out' => $qtyOut,
        ], $qtyBefore, $qtyAfter, $avgAfter, $valueAfter);
    }

    private function sync_daily_rollup(array $movement, float $qtyBefore, float $qtyAfter, float $avgAfter, float $valueAfter): void
    {
        if (!$this->ci->db->table_exists('inv_component_daily_rollup')) {
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
        $isOpeningSnapshot = $movementType === 'OPENING';

        if ($movementDate === '' || $componentId <= 0 || $uomId <= 0 || $locationType === '') {
            return;
        }

        $row = $this->ci->db->query(
            'SELECT * FROM inv_component_daily_rollup WHERE movement_date = ? AND location_type = ? AND division_id <=> ? AND component_id = ? AND uom_id = ? LIMIT 1 FOR UPDATE',
            [$movementDate, $locationType, $divisionId, $componentId, $uomId]
        )->row_array();

        $data = [
            'month_key' => date('Y-m-01', strtotime($movementDate)),
            'movement_date' => $movementDate,
            'location_type' => $locationType,
            'division_id' => $divisionId,
            'component_id' => $componentId,
            'uom_id' => $uomId,
            'opening_qty' => $isOpeningSnapshot
                ? ($row ? round((float)($row['opening_qty'] ?? 0) + $qtyIn, 4) : round($qtyAfter, 4))
                : ($row ? round((float)($row['opening_qty'] ?? 0), 4) : round($qtyBefore, 4)),
            'in_qty' => $row ? round((float)($row['in_qty'] ?? 0), 4) : 0.0,
            'out_qty' => $row ? round((float)($row['out_qty'] ?? 0), 4) : 0.0,
            'waste_qty' => $row ? round((float)($row['waste_qty'] ?? 0), 4) : 0.0,
            'spoil_qty' => $row ? round((float)($row['spoil_qty'] ?? 0), 4) : 0.0,
            'adjustment_qty' => $row ? round((float)($row['adjustment_qty'] ?? 0), 4) : 0.0,
            'closing_qty' => round($qtyAfter, 4),
            'avg_cost' => round($avgAfter, 6),
            'total_value' => round($valueAfter, 2),
            'mutation_count' => $row ? ((int)($row['mutation_count'] ?? 0) + 1) : 1,
            'last_movement_at' => (string)($movement['movement_datetime'] ?? ($movementDate . ' ' . date('H:i:s'))),
        ];

        switch ($movementType) {
            case 'PRODUCTION_IN':
            case 'TRANSFER_IN':
                $data['in_qty'] += $qtyIn;
                break;
            case 'PRODUCTION_OUT':
            case 'TRANSFER_OUT':
                $data['out_qty'] += $qtyOut;
                break;
            case 'WASTE':
                $data['waste_qty'] += $qtyOut;
                break;
            case 'SPOIL':
                $data['spoil_qty'] += $qtyOut;
                break;
            case 'ADJUSTMENT_PLUS':
            case 'VOID_REVERSE':
                $data['adjustment_qty'] += $qtyIn;
                break;
            case 'ADJUSTMENT_MINUS':
            case 'VOID_OUT':
                $data['adjustment_qty'] -= $qtyOut;
                break;
        }

        if ($row && !empty($row['id'])) {
            $this->ci->db->where('id', (int)$row['id'])->update('inv_component_daily_rollup', $data);
            return;
        }

        $this->ci->db->insert('inv_component_daily_rollup', $data);
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
            ]);
            if (!($post['ok'] ?? false)) {
                return ['ok' => false, 'message' => (string)($post['message'] ?? 'Gagal posting usage keluar stok divisi.')];
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
        ];
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
            'stock_domain' => !empty($sourceLot['material_id']) ? 'MATERIAL' : 'ITEM',
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

        $snapshot['profile_content_per_buy'] = max(0.000001, round((float)($snapshot['profile_content_per_buy'] ?? 1), 6));
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

    private function lock_balance_row(string $locationType, ?int $divisionId, int $componentId, int $uomId): array
    {
        $row = $this->ci->db->query(
            'SELECT * FROM inv_component_stock_balance WHERE location_type = ? AND division_id <=> ? AND component_id = ? AND uom_id = ? LIMIT 1 FOR UPDATE',
            [$locationType, $divisionId, $componentId, $uomId]
        )->row_array();
        return is_array($row) ? $row : [];
    }

    private function current_avg_cost(string $locationType, ?int $divisionId, int $componentId, int $uomId): float
    {
        $row = $this->ci->db->query(
            'SELECT avg_cost FROM inv_component_stock_balance WHERE location_type = ? AND division_id <=> ? AND component_id = ? AND uom_id = ? LIMIT 1',
            [$locationType, $divisionId, $componentId, $uomId]
        )->row_array();
        return round((float)($row['avg_cost'] ?? 0), 6);
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
