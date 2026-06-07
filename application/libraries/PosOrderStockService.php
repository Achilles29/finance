<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class PosOrderStockService
{
    /** @var CI_Controller */
    private $ci;
    /** @var array<int, string>|null */
    private $commitLineMovementRefEnumValues = null;

    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->database();
        $this->ci->load->library('InventoryLedger');
        $this->ci->load->library('PosStockCommitService');
    }

    public function post_commit_snapshot(int $commitId, array $meta = []): array
    {
        if ($commitId <= 0) {
            return ['ok' => false, 'message' => 'Snapshot stock commit tidak valid.'];
        }

        $snapshot = $this->load_snapshot($commitId);
        if (!$snapshot['header']) {
            return ['ok' => false, 'message' => 'Snapshot stock commit tidak ditemukan.'];
        }

        $db = $this->ci->db;
        $db->trans_begin();
        try {
            $posted = 0;
            $skipped = 0;
            foreach ($snapshot['lines'] as $line) {
                $movementRefType = strtoupper(trim((string)($line['movement_ref_type'] ?? 'NONE')));
                $movementRefId = (int)($line['movement_ref_id'] ?? 0);
                if (($movementRefType !== '' && $movementRefType !== 'NONE') || $movementRefId > 0) {
                    $skipped++;
                    continue;
                }

                $result = strtoupper((string)($line['source_kind'] ?? 'MATERIAL')) === 'COMPONENT'
                    ? $this->post_component_usage($snapshot['header'], $line, $meta)
                    : $this->post_material_usage($snapshot['header'], $line, $meta);

                if (!($result['ok'] ?? false)) {
                    throw new RuntimeException((string)($result['message'] ?? 'Gagal posting stok order POS.'));
                }

                $db->where('id', (int)$line['id'])->update('pos_stock_commit_line', [
                    'movement_ref_type' => $this->normalize_commit_line_movement_ref_type_for_storage((string)($result['movement_ref_type'] ?? 'NONE')),
                    'movement_ref_id' => !empty($result['movement_ref_id']) ? (int)$result['movement_ref_id'] : null,
                    'unit_cost_live' => round((float)($result['unit_cost_live'] ?? ($line['unit_cost_live'] ?? 0)), 6),
                    'total_cost_live' => round((float)($result['total_cost_live'] ?? ($line['total_cost_live'] ?? 0)), 6),
                    'cost_source' => (string)($result['cost_source'] ?? ($line['cost_source'] ?? 'STANDARD_FALLBACK')),
                    'notes' => $this->merge_note((string)($line['notes'] ?? ''), (string)($result['notes'] ?? '')),
                ]);
                $posted++;
            }

            if ($db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan posting stok order POS.');
            }
            $db->trans_commit();

            return ['ok' => true, 'posted_lines' => $posted, 'skipped_lines' => $skipped];
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function reverse_commit_snapshot(int $commitId, array $lineDecisions, array $meta = []): array
    {
        if ($commitId <= 0) {
            return ['ok' => false, 'message' => 'Snapshot stock commit tidak valid untuk reversal.'];
        }

        $snapshot = $this->load_snapshot($commitId);
        if (!$snapshot['header']) {
            return ['ok' => false, 'message' => 'Snapshot stock commit tidak ditemukan.'];
        }

        $decisionMap = $this->index_decisions($lineDecisions);
        if (empty($decisionMap)) {
            return ['ok' => false, 'message' => 'Tidak ada line reversal yang dikirim.'];
        }

        $db = $this->ci->db;
        $db->trans_begin();
        try {
            $appliedDecisions = [];
            $physicalReturns = 0;

            foreach ($snapshot['lines'] as $line) {
                $lineKey = $this->line_key($line);
                if (!isset($decisionMap[$lineKey])) {
                    continue;
                }

                $decision = $decisionMap[$lineKey];
                $reverseQty = round((float)($decision['reverse_qty'] ?? 0), 4);
                if ($reverseQty <= 0) {
                    continue;
                }

                $policy = strtoupper(trim((string)($decision['return_policy'] ?? 'RETURN_TO_STOCK')));
                if ($policy === 'RETURN_TO_STOCK') {
                    $result = strtoupper((string)($line['source_kind'] ?? 'MATERIAL')) === 'COMPONENT'
                        ? $this->reverse_component_usage($snapshot['header'], $line, $reverseQty, $meta)
                        : $this->reverse_material_usage($snapshot['header'], $line, $reverseQty, $meta);
                    if (!($result['ok'] ?? false)) {
                        throw new RuntimeException((string)($result['message'] ?? 'Gagal mengembalikan stok order POS.'));
                    }
                    $physicalReturns++;
                }

                $appliedDecisions[] = [
                    'line_key' => $lineKey,
                    'return_policy' => $policy,
                    'reverse_qty' => $reverseQty,
                    'notes' => (string)($decision['notes'] ?? ''),
                ];
            }

            $apply = $this->ci->posstockcommitservice->apply_reversal_plan($commitId, $appliedDecisions, [
                'notes' => (string)($meta['notes'] ?? ''),
            ]);
            if (!($apply['ok'] ?? false)) {
                throw new RuntimeException((string)($apply['message'] ?? 'Gagal memfinalkan reversal snapshot POS.'));
            }

            if (!empty($snapshot['header']['order_id'])) {
                $orderUpdate = [
                    'stock_reversed_at' => date('Y-m-d H:i:s'),
                ];
                if ((string)($apply['commit_status'] ?? '') === 'REVERSED') {
                    $orderUpdate['stock_commit_status'] = 'REVERSED';
                }
                $db->where('id', (int)$snapshot['header']['order_id'])->update('pos_order', $orderUpdate);
            }

            if ($db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan reversal stok POS.');
            }
            $db->trans_commit();

            return [
                'ok' => true,
                'physical_return_count' => $physicalReturns,
                'commit_status' => (string)($apply['commit_status'] ?? ''),
                'affected_lines' => (int)($apply['affected_lines'] ?? 0),
            ];
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function post_material_usage(array $header, array $line, array $meta): array
    {
        $divisionId = $this->resolve_operational_division_id($line);
        $destinationType = $this->resolve_destination_type($line, (string)($header['order_scope'] ?? 'REGULAR'));
        $requiredQty = round((float)($line['committed_qty'] ?? $line['required_qty'] ?? 0), 4);
        if ($requiredQty <= 0) {
            return ['ok' => false, 'message' => 'Qty material commit tidak valid.'];
        }

        $fifoAttempted = false;
        $fifoError = '';
        if ($divisionId > 0 && $destinationType !== 'OTHER' && file_exists(APPPATH . 'libraries/MaterialFifoManager.php')) {
            $this->ci->load->library('MaterialFifoManager');
            $fifoAttempted = true;
            $fifo = $this->ci->materialfifomanager->consumeDivisionUsage([
                'issue_date' => date('Y-m-d'),
                'division_id' => $divisionId,
                'destination_type' => $destinationType,
                'item_id' => $this->infer_material_identity($line, $divisionId, $destinationType)['item_id'] ?? null,
                'material_id' => !empty($line['material_id']) ? (int)$line['material_id'] : null,
                'content_uom_id' => !empty($line['required_uom_id']) ? (int)$line['required_uom_id'] : null,
                'qty_content_out' => $requiredQty,
                'source_module' => 'POS',
                'source_table' => 'pos_stock_commit',
                'source_id' => (int)($header['id'] ?? 0),
                'source_line_id' => (int)($line['id'] ?? 0),
                'notes' => 'POS order commit ' . (string)($header['commit_no'] ?? ''),
            ]);
            if ($fifo['ok'] ?? false) {
                $issueData = $this->load_material_issue((int)($fifo['data']['issue_id'] ?? 0));
                foreach ((array)($issueData['lines'] ?? []) as $issueLine) {
                    $snapshot = $this->build_material_snapshot_from_issue($issueData['header'], $issueLine, $line, $divisionId, $destinationType);
                    $post = $this->ci->inventoryledger->post($snapshot + [
                        'movement_scope' => 'DIVISION',
                        'movement_date' => date('Y-m-d'),
                        'movement_type' => 'USAGE_OUT',
                        'division_id' => $divisionId,
                        'destination_type' => $destinationType,
                        'ref_table' => 'pos_stock_commit',
                        'ref_id' => (int)($header['id'] ?? 0),
                        'qty_buy_delta' => -1 * (float)$snapshot['qty_buy_delta_abs'],
                        'qty_content_delta' => -1 * (float)$snapshot['qty_content_delta_abs'],
                        'unit_cost' => round((float)($issueLine['unit_cost'] ?? ($line['unit_cost_live'] ?? 0)), 6),
                        'force_avg_cost_per_content' => round((float)($issueLine['unit_cost'] ?? ($line['unit_cost_live'] ?? 0)), 6),
                        'allow_negative_balance' => true,
                        'notes' => 'POS usage FIFO lot ' . (string)($snapshot['profile_name'] ?? ($issueLine['source_lot_no'] ?? '-')),
                        'created_by' => !empty($meta['actor_employee_id']) ? (int)$meta['actor_employee_id'] : null,
                        'manage_transaction' => false,
                    ]);
                    if (!($post['ok'] ?? false)) {
                        return $post;
                    }
                }

                return [
                    'ok' => true,
                    'movement_ref_type' => 'FIFO_ISSUE',
                    'movement_ref_id' => (int)($fifo['data']['issue_id'] ?? 0),
                    'unit_cost_live' => round((float)($fifo['data']['avg_unit_cost'] ?? ($line['unit_cost_live'] ?? 0)), 6),
                    'total_cost_live' => round((float)($fifo['data']['total_cost'] ?? ($line['total_cost_live'] ?? 0)), 6),
                    'cost_source' => 'FIFO',
                    'notes' => 'Stock material diposting via FIFO usage divisi.',
                ];
            }
            $fifoError = (string)($fifo['message'] ?? 'FIFO usage gagal.');
        }

        if ($destinationType === 'OTHER') {
            return ['ok' => true, 'movement_ref_type' => 'SKIPPED', 'movement_ref_id' => 0,
                'unit_cost_live' => 0.0, 'total_cost_live' => 0.0, 'cost_source' => 'SKIP_OTHER',
                'notes' => 'Divisi destination OTHER dilewati untuk konsumsi bahan baku.'];
        }

        $identity = $this->infer_material_identity($line, $divisionId, $destinationType);
        $qtyBuyAbs = $this->resolve_buy_qty_from_profile($requiredQty, (float)($identity['profile_content_per_buy'] ?? 0));
        $post = $this->ci->inventoryledger->post([
            'movement_scope' => 'DIVISION',
            'movement_date' => date('Y-m-d'),
            'movement_type' => 'USAGE_OUT',
            'division_id' => $divisionId,
            'destination_type' => $destinationType,
            'ref_table' => 'pos_stock_commit',
            'ref_id' => (int)($header['id'] ?? 0),
            'item_id' => $identity['item_id'],
            'material_id' => !empty($line['material_id']) ? (int)$line['material_id'] : null,
            'buy_uom_id' => $identity['buy_uom_id'],
            'content_uom_id' => !empty($line['required_uom_id']) ? (int)$line['required_uom_id'] : null,
            'qty_buy_delta' => -1 * $qtyBuyAbs,
            'qty_content_delta' => -1 * $requiredQty,
            'profile_key' => $identity['profile_key'],
            'profile_name' => $identity['profile_name'],
            'profile_brand' => $identity['profile_brand'],
            'profile_description' => $identity['profile_description'],
            'profile_expired_date' => $identity['profile_expired_date'],
            'profile_content_per_buy' => $identity['profile_content_per_buy'],
            'profile_buy_uom_code' => $identity['profile_buy_uom_code'],
            'profile_content_uom_code' => $identity['profile_content_uom_code'],
            'unit_cost' => round((float)($line['unit_cost_live'] ?? 0), 6),
            'force_avg_cost_per_content' => round((float)($line['unit_cost_live'] ?? 0), 6),
            'allow_negative_balance' => true,
            'notes' => 'POS usage aggregate fallback' . ($fifoAttempted && $fifoError !== '' ? ' | FIFO fallback: ' . $fifoError : ''),
            'created_by' => !empty($meta['actor_employee_id']) ? (int)$meta['actor_employee_id'] : null,
            'manage_transaction' => false,
        ]);
        if (!($post['ok'] ?? false)) {
            return $post;
        }

        return [
            'ok' => true,
            'movement_ref_type' => 'LEDGER_MOVEMENT',
            'movement_ref_id' => (int)($post['data']['movement_id'] ?? 0),
            'unit_cost_live' => round((float)($line['unit_cost_live'] ?? 0), 6),
            'total_cost_live' => round($requiredQty * (float)($line['unit_cost_live'] ?? 0), 6),
            'cost_source' => (string)($line['cost_source'] ?? 'LAST_LIVE'),
            'notes' => 'Stock material diposting langsung ke ledger divisi dan diizinkan minus.',
        ];
    }

    private function reverse_material_usage(array $header, array $line, float $reverseQty, array $meta): array
    {
        $divisionId = $this->resolve_operational_division_id($line);
        $destinationType = $this->resolve_destination_type($line, (string)($header['order_scope'] ?? 'REGULAR'));
        $fullReverse = abs($reverseQty - round((float)($line['committed_qty'] ?? 0), 4)) < 0.0001;
        $movementRefType = $this->resolve_material_movement_ref_type($header, $line);

        if ($movementRefType === 'FIFO_ISSUE' && $fullReverse && file_exists(APPPATH . 'libraries/MaterialFifoManager.php')) {
            $issueData = $this->load_material_issue((int)($line['movement_ref_id'] ?? 0));
            foreach ((array)($issueData['lines'] ?? []) as $issueLine) {
                $snapshot = $this->build_material_snapshot_from_issue($issueData['header'], $issueLine, $line, $divisionId, $destinationType);
                $post = $this->ci->inventoryledger->post($snapshot + [
                    'movement_scope' => 'DIVISION',
                    'movement_date' => date('Y-m-d'),
                    'movement_type' => 'VOID_REVERSE',
                    'division_id' => $divisionId,
                    'destination_type' => $destinationType,
                    'ref_table' => 'pos_stock_commit',
                    'ref_id' => (int)($header['id'] ?? 0),
                    'qty_buy_delta' => (float)$snapshot['qty_buy_delta_abs'],
                    'qty_content_delta' => (float)$snapshot['qty_content_delta_abs'],
                    'unit_cost' => round((float)($issueLine['unit_cost'] ?? ($line['unit_cost_live'] ?? 0)), 6),
                    'force_avg_cost_per_content' => round((float)($issueLine['unit_cost'] ?? ($line['unit_cost_live'] ?? 0)), 6),
                    'allow_negative_balance' => true,
                    'notes' => 'POS return to stock from FIFO issue.',
                    'created_by' => !empty($meta['actor_employee_id']) ? (int)$meta['actor_employee_id'] : null,
                    'manage_transaction' => false,
                ]);
                if (!($post['ok'] ?? false)) {
                    return $post;
                }
            }

            $this->ci->load->library('MaterialFifoManager');
            $rollback = $this->ci->materialfifomanager->rollbackTransferLotsBySource('pos_stock_commit', (int)($header['id'] ?? 0), (int)($line['id'] ?? 0), (string)($meta['notes'] ?? 'Void/refund POS'));
            if (!($rollback['ok'] ?? false)) {
                return $rollback;
            }
            return ['ok' => true];
        }

        $identity = $this->infer_material_identity($line, $divisionId, $destinationType);
        $qtyBuyAbs = $this->resolve_buy_qty_from_profile($reverseQty, (float)($identity['profile_content_per_buy'] ?? 0));
        return $this->ci->inventoryledger->post([
            'movement_scope' => 'DIVISION',
            'movement_date' => date('Y-m-d'),
            'movement_type' => 'VOID_REVERSE',
            'division_id' => $divisionId,
            'destination_type' => $destinationType,
            'ref_table' => 'pos_stock_commit',
            'ref_id' => (int)($header['id'] ?? 0),
            'item_id' => $identity['item_id'],
            'material_id' => !empty($line['material_id']) ? (int)$line['material_id'] : null,
            'buy_uom_id' => $identity['buy_uom_id'],
            'content_uom_id' => !empty($line['required_uom_id']) ? (int)$line['required_uom_id'] : null,
            'qty_buy_delta' => $qtyBuyAbs,
            'qty_content_delta' => $reverseQty,
            'profile_key' => $identity['profile_key'],
            'profile_name' => $identity['profile_name'],
            'profile_brand' => $identity['profile_brand'],
            'profile_description' => $identity['profile_description'],
            'profile_expired_date' => $identity['profile_expired_date'],
            'profile_content_per_buy' => $identity['profile_content_per_buy'],
            'profile_buy_uom_code' => $identity['profile_buy_uom_code'],
            'profile_content_uom_code' => $identity['profile_content_uom_code'],
            'unit_cost' => round((float)($line['unit_cost_live'] ?? 0), 6),
            'force_avg_cost_per_content' => round((float)($line['unit_cost_live'] ?? 0), 6),
            'allow_negative_balance' => true,
            'notes' => 'POS return to stock aggregate reversal.',
            'created_by' => !empty($meta['actor_employee_id']) ? (int)$meta['actor_employee_id'] : null,
            'manage_transaction' => false,
        ]);
    }

    private function post_component_usage(array $header, array $line, array $meta): array
    {
        $locationType = $this->resolve_component_location_type($line, (string)($header['order_scope'] ?? 'REGULAR'));
        $divisionId = $this->resolve_operational_division_id($line);
        $requiredQty = round((float)($line['committed_qty'] ?? $line['required_qty'] ?? 0), 4);
        if ($locationType === null || $requiredQty <= 0) {
            return ['ok' => false, 'message' => 'Lokasi/qty komponen tidak valid untuk posting POS.'];
        }

        $lotIssueId = null;
        $lotError = '';
        if (file_exists(APPPATH . 'libraries/ComponentLotManager.php')) {
            $this->ci->load->library('ComponentLotManager');
            $lot = $this->ci->componentlotmanager->consumeUsage([
                'issue_date' => date('Y-m-d'),
                'location_type' => $locationType,
                'division_id' => $divisionId,
                'component_id' => (int)($line['component_id'] ?? 0),
                'uom_id' => (int)($line['required_uom_id'] ?? 0),
                'qty_out' => $requiredQty,
                'source_module' => 'POS',
                'source_table' => 'pos_stock_commit',
                'source_id' => (int)($header['id'] ?? 0),
                'source_line_id' => (int)($line['id'] ?? 0),
                'notes' => 'POS component usage',
            ]);
            if ($lot['ok'] ?? false) {
                $lotIssueId = (int)($lot['data']['issue_id'] ?? 0);
            } else {
                $lotError = (string)($lot['message'] ?? 'Lot component usage gagal.');
            }
        }

        $movement = $this->post_component_aggregate_movement([
            'movement_date' => date('Y-m-d'),
            'location_type' => $locationType,
            'division_id' => $divisionId,
            'component_id' => (int)($line['component_id'] ?? 0),
            'uom_id' => (int)($line['required_uom_id'] ?? 0),
            'movement_type' => 'USAGE',
            'qty' => $requiredQty,
            'unit_cost' => round((float)($line['unit_cost_live'] ?? 0), 6),
            'source_module' => 'POS',
            'source_table' => 'pos_stock_commit',
            'source_id' => (int)($header['id'] ?? 0),
            'source_line_id' => (int)($line['id'] ?? 0),
            'notes' => $lotError !== '' ? ('Lot fallback: ' . $lotError) : 'POS component usage posted.',
            'actor_employee_id' => !empty($meta['actor_employee_id']) ? (int)$meta['actor_employee_id'] : 0,
            'allow_negative' => true,
        ]);
        if (!($movement['ok'] ?? false)) {
            return $movement;
        }

        return [
            'ok' => true,
            'movement_ref_type' => $lotIssueId ? 'COMPONENT_LOT_ISSUE' : 'COMPONENT_MOVEMENT',
            'movement_ref_id' => $lotIssueId ?: (int)($movement['movement_id'] ?? 0),
            'unit_cost_live' => round((float)($line['unit_cost_live'] ?? 0), 6),
            'total_cost_live' => round($requiredQty * (float)($line['unit_cost_live'] ?? 0), 6),
            'cost_source' => (string)($line['cost_source'] ?? 'LAST_LIVE'),
            'notes' => $lotIssueId ? 'Stock komponen diposting dan lot issue tercatat.' : 'Stock komponen diposting aggregate dan diizinkan minus.',
        ];
    }

    private function reverse_component_usage(array $header, array $line, float $reverseQty, array $meta): array
    {
        $locationType = $this->resolve_component_location_type($line, (string)($header['order_scope'] ?? 'REGULAR'));
        $divisionId = $this->resolve_operational_division_id($line);
        $fullReverse = abs($reverseQty - round((float)($line['committed_qty'] ?? 0), 4)) < 0.0001;
        $movementRefType = $this->resolve_component_movement_ref_type($header, $line);

        if ($movementRefType === 'COMPONENT_LOT_ISSUE' && $fullReverse && file_exists(APPPATH . 'libraries/ComponentLotManager.php')) {
            $this->ci->load->library('ComponentLotManager');
            $rollback = $this->ci->componentlotmanager->rollbackIssueLotsBySource('pos_stock_commit', (int)($header['id'] ?? 0), (int)($line['id'] ?? 0), (string)($meta['notes'] ?? 'Void/refund POS'));
            if (!($rollback['ok'] ?? false)) {
                return $rollback;
            }
        }

        return $this->post_component_aggregate_movement([
            'movement_date' => date('Y-m-d'),
            'location_type' => $locationType,
            'division_id' => $divisionId,
            'component_id' => (int)($line['component_id'] ?? 0),
            'uom_id' => (int)($line['required_uom_id'] ?? 0),
            'movement_type' => 'VOID_REVERSE',
            'qty' => $reverseQty,
            'unit_cost' => round((float)($line['unit_cost_live'] ?? 0), 6),
            'source_module' => 'POS',
            'source_table' => 'pos_stock_commit',
            'source_id' => (int)($header['id'] ?? 0),
            'source_line_id' => (int)($line['id'] ?? 0),
            'notes' => 'POS return to stock component reversal.',
            'actor_employee_id' => !empty($meta['actor_employee_id']) ? (int)$meta['actor_employee_id'] : 0,
            'allow_negative' => true,
        ]);
    }

    private function post_component_aggregate_movement(array $p): array
    {
        $movementType = strtoupper((string)($p['movement_type'] ?? 'USAGE'));
        $qty = round((float)($p['qty'] ?? 0), 4);
        if ($qty <= 0) {
            return ['ok' => false, 'message' => 'Qty komponen tidak valid.'];
        }

        $isIn = in_array($movementType, ['OPENING', 'PRODUCTION_IN', 'TRANSFER_IN', 'ADJUSTMENT_PLUS', 'VOID_REVERSE'], true);
        $qtyIn = $isIn ? $qty : 0.0;
        $qtyOut = $isIn ? 0.0 : $qty;
        $unitCost = round((float)($p['unit_cost'] ?? 0), 6);
        $totalCost = round($qty * $unitCost, 2);
        $allowNegative = !empty($p['allow_negative']);

        $row = $this->load_component_balance_snapshot(
            (string)$p['location_type'],
            isset($p['division_id']) ? (int)$p['division_id'] : null,
            (int)$p['component_id'],
            (int)$p['uom_id'],
            (string)$p['movement_date']
        );

        $qtyBefore = round((float)($row['qty_on_hand'] ?? 0), 4);
        $avgBefore = round((float)($row['avg_cost'] ?? 0), 6);
        $valueBefore = round((float)($row['total_value'] ?? ($qtyBefore * $avgBefore)), 2);
        $qtyAfter = round($qtyBefore + $qtyIn - $qtyOut, 4);
        if (!$allowNegative && $qtyAfter < -0.0001) {
            return ['ok' => false, 'message' => 'Stok komponen tidak cukup untuk movement ' . $movementType . '.'];
        }

        if ($qtyIn > 0) {
            $valueAfter = round($valueBefore + $totalCost, 2);
            $avgAfter = abs($qtyAfter) > 0.0001 ? round($valueAfter / $qtyAfter, 6) : 0.0;
        } else {
            $effectiveCost = $avgBefore > 0 ? $avgBefore : $unitCost;
            $valueAfter = round($valueBefore - round($qtyOut * $effectiveCost, 2), 2);
            if (abs($qtyAfter) <= 0.0001) {
                $avgAfter = 0.0;
                $valueAfter = 0.0;
            } elseif ($qtyAfter < 0 && $allowNegative) {
                $avgAfter = $effectiveCost;
            } else {
                $avgAfter = round($valueAfter / $qtyAfter, 6);
            }
        }

        $now = date('Y-m-d H:i:s');

        $movementNo = $this->generate_component_movement_no((string)$p['movement_date']);
        $this->ci->db->insert('inv_component_movement_log', [
            'movement_no' => $movementNo,
            'movement_date' => (string)$p['movement_date'],
            'movement_datetime' => $now,
            'location_type' => (string)$p['location_type'],
            'division_id' => isset($p['division_id']) ? (int)$p['division_id'] : null,
            'component_id' => (int)$p['component_id'],
            'uom_id' => (int)$p['uom_id'],
            'movement_type' => $movementType,
            'qty_in' => $qtyIn,
            'qty_out' => $qtyOut,
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'source_module' => (string)($p['source_module'] ?? 'POS'),
            'source_table' => (string)($p['source_table'] ?? 'pos_stock_commit'),
            'source_id' => !empty($p['source_id']) ? (int)$p['source_id'] : null,
            'source_line_id' => !empty($p['source_line_id']) ? (int)$p['source_line_id'] : null,
            'notes' => !empty($p['notes']) ? (string)$p['notes'] : null,
            'created_by' => !empty($p['actor_employee_id']) ? (int)$p['actor_employee_id'] : null,
            'created_at' => $now,
        ]);
        $movementId = (int)$this->ci->db->insert_id();

        $this->sync_component_monthly_stock([
            'movement_date' => (string)$p['movement_date'],
            'movement_datetime' => $now,
            'location_type' => (string)$p['location_type'],
            'division_id' => isset($p['division_id']) ? (int)$p['division_id'] : null,
            'component_id' => (int)$p['component_id'],
            'uom_id' => (int)$p['uom_id'],
            'movement_type' => $movementType,
            'qty_in' => $qtyIn,
            'qty_out' => $qtyOut,
            'qty_before' => $qtyBefore,
            'qty_after' => $qtyAfter,
            'avg_after' => $avgAfter,
            'value_after' => $valueAfter,
            'source_table' => (string)($p['source_table'] ?? 'pos_stock_commit'),
            'source_id' => !empty($p['source_id']) ? (int)$p['source_id'] : null,
            'notes' => !empty($p['notes']) ? (string)$p['notes'] : null,
        ]);

        return ['ok' => true, 'movement_id' => $movementId, 'movement_no' => $movementNo];
    }

    private function sync_component_monthly_stock(array $ctx): void
    {
        if (!$this->ci->db->table_exists('inv_component_monthly_stock')) {
            return;
        }

        $monthKey = date('Y-m-01', strtotime((string)$ctx['movement_date']));
        $row = $this->ci->db->query(
            'SELECT * FROM inv_component_monthly_stock WHERE month_key = ? AND location_type = ? AND division_id <=> ? AND component_id = ? AND uom_id = ? LIMIT 1 FOR UPDATE',
            [
                $monthKey,
                (string)$ctx['location_type'],
                isset($ctx['division_id']) ? (int)$ctx['division_id'] : null,
                (int)$ctx['component_id'],
                (int)$ctx['uom_id'],
            ]
        )->row_array();

        $movementType = strtoupper((string)$ctx['movement_type']);
        $movementValue = round(((float)($ctx['qty_in'] ?? 0) > 0 ? (float)($ctx['qty_in'] ?? 0) : (float)($ctx['qty_out'] ?? 0)) * max((float)($ctx['avg_after'] ?? 0), 0), 2);
        $movementDate = (string)$ctx['movement_date'];
        $movementDayCount = (int)($row['movement_day_count'] ?? 0);
        if ($row === null || (string)($row['last_movement_date'] ?? '') !== $movementDate) {
            $movementDayCount++;
        }

        $data = [
            'month_key' => $monthKey,
            'location_type' => (string)$ctx['location_type'],
            'division_id' => isset($ctx['division_id']) ? (int)$ctx['division_id'] : null,
            'component_id' => (int)$ctx['component_id'],
            'uom_id' => (int)$ctx['uom_id'],
            'opening_qty' => $movementType === 'OPENING'
                ? ($row ? round((float)($row['opening_qty'] ?? 0) + (float)($ctx['qty_in'] ?? 0), 4) : round((float)($ctx['qty_after'] ?? 0), 4))
                : ($row ? round((float)($row['opening_qty'] ?? 0), 4) : round((float)($ctx['qty_before'] ?? 0), 4)),
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
            'closing_qty' => round((float)($ctx['qty_after'] ?? 0), 4),
            'avg_cost' => round((float)($ctx['avg_after'] ?? 0), 6),
            'total_value' => round((float)($ctx['value_after'] ?? 0), 2),
            'movement_day_count' => $movementDayCount,
            'mutation_count' => $row ? ((int)($row['mutation_count'] ?? 0) + 1) : 1,
            'last_movement_date' => $movementDate,
            'last_movement_at' => (string)($ctx['movement_datetime'] ?? date('Y-m-d H:i:s')),
            'last_movement_table' => !empty($ctx['source_table']) ? (string)$ctx['source_table'] : null,
            'last_movement_id' => !empty($ctx['source_id']) ? (int)$ctx['source_id'] : null,
            'source_mode' => 'LIVE',
            'notes' => !empty($ctx['notes']) ? (string)$ctx['notes'] : ($row['notes'] ?? null),
        ];

        if ($movementType === 'OPENING') {
            $data['opening_total_value'] = round((float)$data['opening_total_value'] + $movementValue, 2);
        } elseif (in_array($movementType, ['PRODUCTION_IN', 'TRANSFER_IN', 'VOID_REVERSE'], true)) {
            $data['in_qty'] = round((float)$data['in_qty'] + (float)($ctx['qty_in'] ?? 0), 4);
            $data['in_total_value'] = round((float)$data['in_total_value'] + $movementValue, 2);
        } elseif (in_array($movementType, ['USAGE', 'PRODUCTION_OUT', 'TRANSFER_OUT'], true)) {
            $data['out_qty'] = round((float)$data['out_qty'] + (float)($ctx['qty_out'] ?? 0), 4);
            $data['out_total_value'] = round((float)$data['out_total_value'] + $movementValue, 2);
        } elseif ($movementType === 'WASTE') {
            $data['waste_qty'] = round((float)$data['waste_qty'] + (float)($ctx['qty_out'] ?? 0), 4);
            $data['waste_total_value'] = round((float)$data['waste_total_value'] + $movementValue, 2);
        } elseif ($movementType === 'SPOIL') {
            $data['spoil_qty'] = round((float)$data['spoil_qty'] + (float)($ctx['qty_out'] ?? 0), 4);
            $data['spoil_total_value'] = round((float)$data['spoil_total_value'] + $movementValue, 2);
        } elseif ($movementType === 'ADJUSTMENT_PLUS') {
            $data['adjustment_plus_qty'] = round((float)$data['adjustment_plus_qty'] + (float)($ctx['qty_in'] ?? 0), 4);
            $data['adjustment_plus_total_value'] = round((float)$data['adjustment_plus_total_value'] + $movementValue, 2);
        } elseif ($movementType === 'ADJUSTMENT_MINUS') {
            $data['adjustment_minus_qty'] = round((float)$data['adjustment_minus_qty'] + (float)($ctx['qty_out'] ?? 0), 4);
            $data['adjustment_minus_total_value'] = round((float)$data['adjustment_minus_total_value'] + $movementValue, 2);
        }

        if ($row && !empty($row['id'])) {
            $this->ci->db->where('id', (int)$row['id'])->update('inv_component_monthly_stock', $data);
            return;
        }

        $this->ci->db->insert('inv_component_monthly_stock', $data);
    }

    private function load_snapshot(int $commitId): array
    {
        $header = $this->ci->db
            ->select('sc.*, o.order_scope')
            ->from('pos_stock_commit sc')
            ->join('pos_order o', 'o.id = sc.order_id', 'left')
            ->where('sc.id', $commitId)
            ->limit(1)
            ->get()
            ->row_array() ?: null;

        $lines = $this->ci->db
            ->select('
                scl.*,
                ol.operational_division_id,
                od.name AS operational_division_name,
                od.code AS operational_division_code
            ')
            ->from('pos_stock_commit_line scl')
            ->join('pos_order_line ol', 'ol.id = scl.order_line_id', 'left')
            ->join('mst_operational_division od', 'od.id = ol.operational_division_id', 'left')
            ->where('scl.commit_id', $commitId)
            ->order_by('scl.line_no', 'ASC')
            ->get()
            ->result_array();

        return ['header' => $header, 'lines' => $lines];
    }

    private function line_key(array $line): string
    {
        return 'commit_line:' . (int)($line['id'] ?? 0);
    }

    private function index_decisions(array $lineDecisions): array
    {
        $map = [];
        foreach ($lineDecisions as $decision) {
            if (!is_array($decision)) {
                continue;
            }
            $lineKey = trim((string)($decision['line_key'] ?? ''));
            if ($lineKey === '' && !empty($decision['line_id'])) {
                $lineKey = 'commit_line:' . (int)$decision['line_id'];
            }
            if ($lineKey === '') {
                continue;
            }
            $map[$lineKey] = $decision;
        }
        return $map;
    }

    private function resolve_operational_division_id(array $line): int
    {
        return max(0, (int)($line['operational_division_id'] ?? 0));
    }

    private function resolve_destination_type(array $line, string $orderScope = 'REGULAR'): string
    {
        $divisionName = strtoupper(trim((string)($line['operational_division_code'] ?? ($line['operational_division_name'] ?? ''))));
        $isEvent = strtoupper(trim($orderScope)) === 'EVENT';
        if (in_array($divisionName, ['BAR', 'BEVERAGE'], true)) {
            return $isEvent ? 'BAR_EVENT' : 'BAR';
        }
        if (in_array($divisionName, ['KITCHEN', 'FOOD'], true)) {
            return $isEvent ? 'KITCHEN_EVENT' : 'KITCHEN';
        }
        return 'OTHER';
    }

    private function resolve_component_location_type(array $line, string $orderScope = 'REGULAR'): ?string
    {
        $destinationType = $this->resolve_destination_type($line, $orderScope);
        return in_array($destinationType, ['BAR', 'KITCHEN', 'BAR_EVENT', 'KITCHEN_EVENT'], true) ? $destinationType : null;
    }

    private function infer_material_identity(array $line, int $divisionId, string $destinationType): array
    {
        $materialId = !empty($line['material_id']) ? (int)$line['material_id'] : 0;
        $uomId = !empty($line['required_uom_id']) ? (int)$line['required_uom_id'] : 0;
        $requestedProfileKey = !empty($line['profile_key']) ? (string)$line['profile_key'] : null;
        $identity = [
            'item_id' => null,
            'buy_uom_id' => null,
            'content_uom_id' => $uomId > 0 ? $uomId : null,
            'profile_key' => null,
            'profile_name' => null,
            'profile_brand' => null,
            'profile_description' => null,
            'profile_expired_date' => null,
            'profile_content_per_buy' => null,
            'profile_buy_uom_code' => null,
            'profile_content_uom_code' => null,
        ];

        $preferred = $materialId > 0
            ? $this->resolve_preferred_item_identity_for_material($materialId, null, $uomId > 0 ? $uomId : null, $requestedProfileKey)
            : [];

        if ($materialId > 0) {
            $balance = $this->load_division_material_profile_snapshot($materialId, $divisionId, $destinationType, $uomId, $requestedProfileKey);
            if (!empty($balance)) {
                $identity = array_merge($identity, [
                    'item_id' => !empty($preferred['item_id']) ? (int)$preferred['item_id'] : (!empty($balance['item_id']) ? (int)$balance['item_id'] : null),
                    'buy_uom_id' => !empty($preferred['buy_uom_id']) ? (int)$preferred['buy_uom_id'] : (!empty($balance['buy_uom_id']) ? (int)$balance['buy_uom_id'] : null),
                    'content_uom_id' => !empty($balance['content_uom_id']) ? (int)$balance['content_uom_id'] : $identity['content_uom_id'],
                    'profile_key' => $preferred['profile_key'] ?? ($balance['profile_key'] ?? null),
                    'profile_name' => $preferred['profile_name'] ?? ($balance['profile_name'] ?? null),
                    'profile_brand' => $preferred['profile_brand'] ?? ($balance['profile_brand'] ?? null),
                    'profile_description' => $preferred['profile_description'] ?? ($balance['profile_description'] ?? null),
                    'profile_expired_date' => $balance['profile_expired_date'] ?? null,
                    'profile_content_per_buy' => !empty($preferred['profile_content_per_buy']) ? (float)$preferred['profile_content_per_buy'] : (!empty($balance['profile_content_per_buy']) ? (float)$balance['profile_content_per_buy'] : null),
                    'profile_buy_uom_code' => $preferred['profile_buy_uom_code'] ?? ($balance['profile_buy_uom_code'] ?? null),
                    'profile_content_uom_code' => $preferred['profile_content_uom_code'] ?? ($balance['profile_content_uom_code'] ?? null),
                ]);
                return $identity;
            }
        }

        if (!empty($preferred)) {
            $identity = array_merge($identity, $preferred);
        }

        return $identity;
    }

    private function resolve_buy_qty_from_profile(float $qtyContent, float $contentPerBuy): float
    {
        return $contentPerBuy > 0 ? round($qtyContent / $contentPerBuy, 4) : 0.0;
    }

    private function load_material_issue(int $issueId): array
    {
        if ($issueId <= 0 || !$this->ci->db->table_exists('inv_material_fifo_issue_log')) {
            return ['header' => null, 'lines' => []];
        }

        $header = $this->ci->db->from('inv_material_fifo_issue_log')->where('id', $issueId)->limit(1)->get()->row_array() ?: null;
        $lines = $this->ci->db
            ->select('l.*, lot.item_id, lot.material_id, lot.buy_uom_id, lot.content_uom_id, lot.profile_key, lot.lot_no AS source_lot_no, lot.receipt_date, lot.expiry_date')
            ->from('inv_material_fifo_issue_line l')
            ->join('inv_material_fifo_lot lot', 'lot.id = l.lot_id', 'left')
            ->where('l.issue_id', $issueId)
            ->order_by('l.id', 'ASC')
            ->get()
            ->result_array();

        return ['header' => $header, 'lines' => $lines];
    }

    private function build_material_snapshot_from_issue(?array $issueHeader, array $issueLine, array $commitLine, int $divisionId, string $destinationType): array
    {
        $contentPerBuy = null;
        $profile = null;
        if (!empty($issueLine['material_id']) && !empty($issueLine['content_uom_id'])) {
            $profile = $this->load_division_material_profile_snapshot(
                (int)$issueLine['material_id'],
                $divisionId,
                $destinationType,
                (int)$issueLine['content_uom_id'],
                !empty($issueLine['profile_key']) ? (string)$issueLine['profile_key'] : null
            );
        }

        $contentPerBuy = !empty($profile['profile_content_per_buy']) ? (float)$profile['profile_content_per_buy'] : 0.0;
        $qtyContent = round((float)($issueLine['qty_out'] ?? 0), 4);

        return [
            'item_id' => !empty($issueLine['item_id']) ? (int)$issueLine['item_id'] : null,
            'material_id' => !empty($issueLine['material_id']) ? (int)$issueLine['material_id'] : (!empty($commitLine['material_id']) ? (int)$commitLine['material_id'] : null),
            'buy_uom_id' => !empty($issueLine['buy_uom_id']) ? (int)$issueLine['buy_uom_id'] : null,
            'content_uom_id' => !empty($issueLine['content_uom_id']) ? (int)$issueLine['content_uom_id'] : (!empty($commitLine['required_uom_id']) ? (int)$commitLine['required_uom_id'] : null),
            'profile_key' => $issueLine['profile_key'] ?? null,
            'profile_name' => $profile['profile_name'] ?? ($issueLine['source_lot_no'] ?? null),
            'profile_brand' => $profile['profile_brand'] ?? null,
            'profile_description' => $profile['profile_description'] ?? null,
            'profile_expired_date' => $profile['profile_expired_date'] ?? ($issueLine['expiry_date'] ?? null),
            'profile_content_per_buy' => $contentPerBuy > 0 ? $contentPerBuy : null,
            'profile_buy_uom_code' => $profile['profile_buy_uom_code'] ?? null,
            'profile_content_uom_code' => $profile['profile_content_uom_code'] ?? null,
            'qty_content_delta_abs' => $qtyContent,
            'qty_buy_delta_abs' => $this->resolve_buy_qty_from_profile($qtyContent, $contentPerBuy),
        ];
    }

    private function load_division_material_profile_snapshot(int $materialId, int $divisionId, string $destinationType, int $uomId, ?string $profileKey = null): array
    {
        if ($materialId <= 0 || $divisionId <= 0 || $uomId <= 0) {
            return [];
        }

        if ($this->ci->db->table_exists('inv_division_monthly_stock')) {
            $db = $this->ci->db->from('inv_division_monthly_stock')
                ->where('division_id', $divisionId)
                ->where('destination_type', $destinationType)
                ->where('material_id', $materialId)
                ->where('content_uom_id', $uomId);
            $profileKey = trim((string)$profileKey);
            if ($profileKey !== '') {
                $db->where('profile_key', $profileKey);
            }
            if ($this->ci->db->field_exists('stock_domain', 'inv_division_monthly_stock')) {
                $db->order_by("CASE WHEN COALESCE(stock_domain, 'ITEM') = 'ITEM' THEN 0 ELSE 1 END", '', false);
            }
            if ($this->ci->db->field_exists('source_mode', 'inv_division_monthly_stock')) {
                $db->order_by("CASE WHEN source_mode = 'REBUILD' THEN 0 WHEN source_mode = 'LIVE' THEN 1 ELSE 2 END", '', false);
            }
            $row = $db
                ->order_by("CASE WHEN COALESCE(profile_key, '') <> '' THEN 0 ELSE 1 END", '', false)
                ->order_by("CASE WHEN COALESCE(item_id, 0) > 0 THEN 0 ELSE 1 END", '', false)
                ->order_by('month_key', 'DESC')
                ->order_by('updated_at', 'DESC')
                ->order_by('last_movement_at', 'DESC')
                ->limit(1)
                ->get()
                ->row_array() ?: [];
            if (!empty($row)) {
                return $row;
            }
        }

        return [];
    }

    private function resolve_preferred_item_identity_for_material(int $materialId, ?int $buyUomId, ?int $contentUomId, ?string $profileKey = null): array
    {
        if ($materialId <= 0) {
            return [];
        }

        if ($this->ci->db->table_exists('mst_purchase_catalog')) {
            $db = $this->ci->db
                ->select('c.item_id, c.buy_uom_id, c.content_uom_id, c.profile_key, c.catalog_name AS profile_name, c.brand_name AS profile_brand, c.line_description AS profile_description, c.content_per_buy AS profile_content_per_buy, bu.code AS profile_buy_uom_code, cu.code AS profile_content_uom_code', false)
                ->from('mst_purchase_catalog c')
                ->join('mst_uom bu', 'bu.id = c.buy_uom_id', 'left')
                ->join('mst_uom cu', 'cu.id = c.content_uom_id', 'left')
                ->where('c.material_id', $materialId)
                ->where('c.item_id >', 0);
            $profileKey = trim((string)$profileKey);
            if ($profileKey !== '') {
                $db->group_start()
                    ->where('c.profile_key', $profileKey)
                    ->or_where('c.profile_key', '')
                    ->or_where('c.profile_key IS NULL', null, false)
                ->group_end()
                ->order_by("CASE WHEN c.profile_key = " . $this->ci->db->escape($profileKey) . " THEN 0 WHEN COALESCE(c.profile_key, '') = '' THEN 1 ELSE 2 END", '', false);
            }
            if ($buyUomId !== null && $buyUomId > 0) {
                $db->order_by("CASE WHEN c.buy_uom_id = " . (int)$buyUomId . " THEN 0 ELSE 1 END", '', false);
            }
            if ($contentUomId !== null && $contentUomId > 0) {
                $db->order_by("CASE WHEN c.content_uom_id = " . (int)$contentUomId . " THEN 0 ELSE 1 END", '', false);
            }
            $row = $db
                ->order_by('c.is_active', 'DESC')
                ->order_by('c.id', 'DESC')
                ->limit(1)
                ->get()
                ->row_array() ?: [];
            if (!empty($row)) {
                return [
                    'item_id' => !empty($row['item_id']) ? (int)$row['item_id'] : null,
                    'buy_uom_id' => !empty($row['buy_uom_id']) ? (int)$row['buy_uom_id'] : null,
                    'content_uom_id' => !empty($row['content_uom_id']) ? (int)$row['content_uom_id'] : $contentUomId,
                    'profile_key' => $row['profile_key'] ?? null,
                    'profile_name' => $row['profile_name'] ?? null,
                    'profile_brand' => $row['profile_brand'] ?? null,
                    'profile_description' => $row['profile_description'] ?? null,
                    'profile_expired_date' => null,
                    'profile_content_per_buy' => !empty($row['profile_content_per_buy']) ? (float)$row['profile_content_per_buy'] : null,
                    'profile_buy_uom_code' => $row['profile_buy_uom_code'] ?? null,
                    'profile_content_uom_code' => $row['profile_content_uom_code'] ?? null,
                ];
            }
        }

        if ($this->ci->db->table_exists('mst_item')) {
            $row = $this->ci->db
                ->select('id, buy_uom_id, content_uom_id, item_name')
                ->from('mst_item')
                ->where('material_id', $materialId)
                ->where('is_active', 1)
                ->order_by('id', 'ASC')
                ->limit(1)
                ->get()
                ->row_array() ?: [];
            if (!empty($row)) {
                return [
                    'item_id' => !empty($row['id']) ? (int)$row['id'] : null,
                    'buy_uom_id' => !empty($row['buy_uom_id']) ? (int)$row['buy_uom_id'] : null,
                    'content_uom_id' => !empty($row['content_uom_id']) ? (int)$row['content_uom_id'] : $contentUomId,
                    'profile_key' => null,
                    'profile_name' => $row['item_name'] ?? null,
                    'profile_brand' => null,
                    'profile_description' => null,
                    'profile_expired_date' => null,
                    'profile_content_per_buy' => null,
                    'profile_buy_uom_code' => null,
                    'profile_content_uom_code' => null,
                ];
            }
        }

        return [];
    }

    private function load_component_balance_snapshot(string $locationType, ?int $divisionId, int $componentId, int $uomId, string $movementDate): array
    {
        if ($locationType === '' || $componentId <= 0 || $uomId <= 0) {
            return [];
        }

        if ($this->ci->db->table_exists('inv_component_monthly_stock')) {
            $targetMonth = date('Y-m-01', strtotime($movementDate));
            $row = $this->ci->db->query(
                'SELECT month_key, closing_qty AS qty_on_hand, avg_cost, total_value, last_movement_at
                 FROM inv_component_monthly_stock
                 WHERE location_type = ? AND division_id <=> ? AND component_id = ? AND uom_id = ? AND month_key <= ?
                 ORDER BY month_key DESC, updated_at DESC, last_movement_at DESC
                 LIMIT 1 FOR UPDATE',
                [$locationType, $divisionId, $componentId, $uomId, $targetMonth]
            )->row_array();
            if (!empty($row)) {
                return $row;
            }
        }

        return [];
    }

    private function generate_component_movement_no(string $movementDate): string
    {
        $prefix = 'PCM' . date('Ymd', strtotime($movementDate));
        $row = $this->ci->db
            ->select('movement_no')
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

        do {
            $no = $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
            $seq++;
        } while ($this->ci->db->where('movement_no', $no)->count_all_results('inv_component_movement_log') > 0);

        return $no;
    }

    private function merge_note(string $base, string $append): ?string
    {
        $base = trim($base);
        $append = trim($append);
        if ($append === '') {
            return $base !== '' ? $base : null;
        }
        if ($base === '') {
            return $append;
        }
        return $base . ' | ' . $append;
    }

    private function normalize_commit_line_movement_ref_type_for_storage(string $movementRefType): ?string
    {
        $canonical = strtoupper(trim($movementRefType));
        if ($canonical === '' || $canonical === 'NONE') {
            return 'NONE';
        }

        $supported = $this->commit_line_movement_ref_enum_values();
        if (empty($supported)) {
            return $canonical;
        }
        if (in_array($canonical, $supported, true)) {
            return $canonical;
        }

        $fallbackMap = [
            'FIFO_ISSUE' => 'MATERIAL_LEDGER',
            'LEDGER_MOVEMENT' => 'MATERIAL_LEDGER',
            'COMPONENT_LOT_ISSUE' => 'COMPONENT_LEDGER',
            'COMPONENT_MOVEMENT' => 'COMPONENT_LEDGER',
        ];
        $fallback = $fallbackMap[$canonical] ?? 'NONE';
        if (in_array($fallback, $supported, true)) {
            return $fallback;
        }

        return 'NONE';
    }

    private function commit_line_movement_ref_enum_values(): array
    {
        if (is_array($this->commitLineMovementRefEnumValues)) {
            return $this->commitLineMovementRefEnumValues;
        }

        $this->commitLineMovementRefEnumValues = [];
        if (!$this->ci->db->table_exists('pos_stock_commit_line')) {
            return $this->commitLineMovementRefEnumValues;
        }

        $row = $this->ci->db->query("SHOW COLUMNS FROM pos_stock_commit_line LIKE 'movement_ref_type'")->row_array();
        $type = (string)($row['Type'] ?? ($row['type'] ?? ''));
        if (preg_match('/^enum\((.+)\)$/i', $type, $matches)) {
            preg_match_all("/'([^']+)'/", (string)$matches[1], $valueMatches);
            $this->commitLineMovementRefEnumValues = array_values(array_unique(array_map('strtoupper', $valueMatches[1] ?? [])));
        }

        return $this->commitLineMovementRefEnumValues;
    }

    private function resolve_material_movement_ref_type(array $header, array $line): string
    {
        $stored = strtoupper(trim((string)($line['movement_ref_type'] ?? 'NONE')));
        if ($stored === 'FIFO_ISSUE' || $stored === 'LEDGER_MOVEMENT') {
            return $stored;
        }

        if ($this->is_material_fifo_issue_reference($header, $line)) {
            return 'FIFO_ISSUE';
        }

        return !empty($line['movement_ref_id']) ? 'LEDGER_MOVEMENT' : 'NONE';
    }

    private function resolve_component_movement_ref_type(array $header, array $line): string
    {
        $stored = strtoupper(trim((string)($line['movement_ref_type'] ?? 'NONE')));
        if ($stored === 'COMPONENT_LOT_ISSUE' || $stored === 'COMPONENT_MOVEMENT') {
            return $stored;
        }

        if ($this->is_component_lot_issue_reference($header, $line)) {
            return 'COMPONENT_LOT_ISSUE';
        }

        return !empty($line['movement_ref_id']) ? 'COMPONENT_MOVEMENT' : 'NONE';
    }

    private function is_material_fifo_issue_reference(array $header, array $line): bool
    {
        $refId = (int)($line['movement_ref_id'] ?? 0);
        if ($refId <= 0 || !$this->ci->db->table_exists('inv_material_fifo_issue_log')) {
            return false;
        }

        $this->ci->db->from('inv_material_fifo_issue_log')
            ->where('id', $refId)
            ->where('source_table', 'pos_stock_commit');
        if (!empty($header['id'])) {
            $this->ci->db->where('source_id', (int)$header['id']);
        }
        if (!empty($line['id'])) {
            $this->ci->db->where('source_line_id', (int)$line['id']);
        }

        return (bool)$this->ci->db->limit(1)->get()->row_array();
    }

    private function is_component_lot_issue_reference(array $header, array $line): bool
    {
        $refId = (int)($line['movement_ref_id'] ?? 0);
        if ($refId <= 0 || !$this->ci->db->table_exists('inv_component_lot_issue_log')) {
            return false;
        }

        $this->ci->db->from('inv_component_lot_issue_log')
            ->where('id', $refId)
            ->where('source_table', 'pos_stock_commit');
        if (!empty($header['id'])) {
            $this->ci->db->where('source_id', (int)$header['id']);
        }
        if (!empty($line['id'])) {
            $this->ci->db->where('source_line_id', (int)$line['id']);
        }

        return (bool)$this->ci->db->limit(1)->get()->row_array();
    }
}
