<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MaterialFifoManager
{
    /** @var CI_Controller */
    protected $ci;

    /** @var bool */
    protected $schemaEnsured = false;

    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->database();
    }

    public function ensureReady(): array
    {
        return $this->ensureSchema();
    }

    public function registerReceiptInboundLot(array $payload): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        $identity = $this->normalizeLotIdentity($payload, true);
        if (!($identity['ok'] ?? false)) {
            return $identity;
        }

        $qtyIn = round((float)($payload['qty_content_in'] ?? 0), 4);
        if ($qtyIn <= 0) {
            return ['ok' => false, 'message' => 'qty_content_in wajib lebih besar dari nol.'];
        }

        $receiptDate = $this->normalizeDate((string)($payload['receipt_date'] ?? ($payload['movement_date'] ?? '')));
        if ($receiptDate === null) {
            return ['ok' => false, 'message' => 'receipt_date tidak valid untuk membuat lot inbound.'];
        }

        $lotNo = $this->nullableString($payload['lot_no'] ?? null);
        if ($lotNo === null) {
            $lotNo = $this->generateLotNo($receiptDate, [
                $identity['location_scope'],
                $identity['destination_type'] ?? 'GUDANG',
                $identity['division_id'] ?? 0,
                $identity['item_id'] ?? 0,
                $identity['material_id'] ?? 0,
                $identity['profile_key'] ?? '',
                $payload['receipt_id'] ?? 0,
                $payload['receipt_line_id'] ?? 0,
                $payload['source_table'] ?? '',
                $payload['source_id'] ?? 0,
                $payload['source_line_id'] ?? 0,
            ]);
        }

        return $this->applyLotMutation([
            'location_scope' => $identity['location_scope'],
            'division_id' => $identity['division_id'],
            'destination_type' => $identity['destination_type'],
            'item_id' => $identity['item_id'],
            'material_id' => $identity['material_id'],
            'buy_uom_id' => $identity['buy_uom_id'],
            'content_uom_id' => $identity['content_uom_id'],
            'profile_key' => $identity['profile_key'],
            'lot_no' => $lotNo,
            'receipt_date' => $receiptDate,
            'expiry_date' => $identity['expiry_date'],
            'unit_cost' => max(0, round((float)($payload['unit_cost'] ?? 0), 6)),
            'source_table' => $this->nullableString($payload['source_table'] ?? null),
            'source_id' => $this->nullableInt($payload['source_id'] ?? null),
            'source_line_id' => $this->nullableInt($payload['source_line_id'] ?? null),
            'receipt_id' => $this->nullableInt($payload['receipt_id'] ?? null),
            'receipt_line_id' => $this->nullableInt($payload['receipt_line_id'] ?? null),
            'parent_lot_id' => $this->nullableInt($payload['parent_lot_id'] ?? null),
        ], $qtyIn, 0.0);
    }

    public function transferWarehouseToDivision(array $payload): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        $divisionId = $this->nullableInt($payload['division_id'] ?? null);
        $destinationType = $this->normalizeDestinationType((string)($payload['destination_type'] ?? ''));
        $issueDate = $this->normalizeDate((string)($payload['issue_date'] ?? ($payload['movement_date'] ?? '')));
        $qtyNeed = round((float)($payload['qty_content_out'] ?? 0), 4);

        if ($divisionId === null || $destinationType === null || $issueDate === null) {
            return ['ok' => false, 'message' => 'Transfer FIFO membutuhkan division_id, destination_type, dan issue_date yang valid.'];
        }
        if ($qtyNeed <= 0) {
            return ['ok' => false, 'message' => 'qty_content_out wajib lebih besar dari nol.'];
        }

        $identity = $this->normalizeLotIdentity(array_merge($payload, [
            'location_scope' => 'WAREHOUSE',
            'destination_type' => 'GUDANG',
            'division_id' => null,
        ]), false);
        if (!($identity['ok'] ?? false)) {
            return $identity;
        }

        $coverage = $this->synchronizeWarehouseLotsFromAggregate($identity);
        if (!($coverage['ok'] ?? false)) {
            return $coverage;
        }

        $warehouseLots = $this->findOpenLots([
            'location_scope' => 'WAREHOUSE',
            'division_id' => null,
            'destination_type' => 'GUDANG',
            'item_id' => $identity['item_id'],
            'material_id' => $identity['material_id'],
            'buy_uom_id' => $identity['buy_uom_id'],
            'content_uom_id' => $identity['content_uom_id'],
            'profile_key' => $identity['profile_key'],
        ]);

        $lotIdentity = $identity;
        if (empty($warehouseLots) && ($identity['item_id'] ?? null) !== null && ($identity['material_id'] ?? null) !== null) {
            $warehouseLots = $this->findOpenLots([
                'location_scope' => 'WAREHOUSE',
                'division_id' => null,
                'destination_type' => 'GUDANG',
                'item_id' => $identity['item_id'],
                'material_id' => null,
                'buy_uom_id' => $identity['buy_uom_id'],
                'content_uom_id' => $identity['content_uom_id'],
                'profile_key' => $identity['profile_key'],
            ]);
            if (!empty($warehouseLots)) {
                $lotIdentity['material_id'] = null;
            }
        }

        $available = 0.0;
        foreach ($warehouseLots as $lot) {
            $available += round((float)($lot['qty_balance'] ?? 0), 4);
        }
        $available = round($available, 4);
        if ($available + 0.0001 < $qtyNeed) {
            return [
                'ok' => false,
                'message' => 'Saldo FIFO gudang tidak cukup. Dibutuhkan ' . number_format($qtyNeed, 4, '.', '') . ', tersedia ' . number_format($available, 4, '.', '') . '.',
            ];
        }

        $issueNo = $this->generateIssueNo($issueDate);
        $issueData = [
            'issue_no' => $issueNo,
            'issue_date' => $issueDate,
            'issue_datetime' => date('Y-m-d H:i:s'),
            'location_scope' => 'WAREHOUSE',
            'division_id' => null,
            'destination_type' => 'GUDANG',
            'target_scope' => 'DIVISION',
            'target_division_id' => $divisionId,
            'target_destination_type' => $destinationType,
            'item_id' => $identity['item_id'],
            'material_id' => $identity['material_id'],
            'buy_uom_id' => $identity['buy_uom_id'],
            'content_uom_id' => $identity['content_uom_id'],
            'profile_key' => $identity['profile_key'],
            'issue_qty' => $qtyNeed,
            'total_cost' => 0,
            'source_module' => $this->nullableString($payload['source_module'] ?? 'PROCUREMENT'),
            'source_table' => $this->nullableString($payload['source_table'] ?? null),
            'source_id' => $this->nullableInt($payload['source_id'] ?? null),
            'source_line_id' => $this->nullableInt($payload['source_line_id'] ?? null),
            'notes' => $this->nullableString($payload['notes'] ?? null),
            'status' => 'POSTED',
        ];
        $this->ci->db->insert('inv_material_fifo_issue_log', $issueData);
        $issueId = (int)$this->ci->db->insert_id();
        if ($issueId <= 0) {
            return ['ok' => false, 'message' => 'Gagal membuat log issue FIFO.'];
        }

        $remaining = $qtyNeed;
        $totalCost = 0.0;
        $allocations = [];

        foreach ($warehouseLots as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $lotId = (int)($lot['id'] ?? 0);
            $lotBalance = round((float)($lot['qty_balance'] ?? 0), 4);
            if ($lotId <= 0 || $lotBalance <= 0) {
                continue;
            }

            $takeQty = round(min($remaining, $lotBalance), 4);
            if ($takeQty <= 0) {
                continue;
            }

            $warehouseMutation = $this->applyLotMutation([
                'location_scope' => 'WAREHOUSE',
                'division_id' => null,
                'destination_type' => 'GUDANG',
                'item_id' => $lotIdentity['item_id'],
                'material_id' => $lotIdentity['material_id'],
                'buy_uom_id' => $identity['buy_uom_id'],
                'content_uom_id' => $identity['content_uom_id'],
                'profile_key' => $identity['profile_key'],
                'lot_no' => (string)($lot['lot_no'] ?? ''),
                'receipt_date' => (string)($lot['receipt_date'] ?? $issueDate),
                'expiry_date' => $this->normalizeDate((string)($lot['expiry_date'] ?? '')),
                'unit_cost' => max(0, round((float)($lot['unit_cost'] ?? 0), 6)),
                'source_table' => $this->nullableString($lot['source_table'] ?? null),
                'source_id' => $this->nullableInt($lot['source_id'] ?? null),
                'source_line_id' => $this->nullableInt($lot['source_line_id'] ?? null),
                'receipt_id' => $this->nullableInt($lot['receipt_id'] ?? null),
                'receipt_line_id' => $this->nullableInt($lot['receipt_line_id'] ?? null),
                'parent_lot_id' => $this->nullableInt($lot['parent_lot_id'] ?? null),
                'lot_id' => $lotId,
            ], 0.0, $takeQty);
            if (!($warehouseMutation['ok'] ?? false)) {
                return $warehouseMutation;
            }

            $divisionMutation = $this->applyLotMutation([
                'location_scope' => 'DIVISION',
                'division_id' => $divisionId,
                'destination_type' => $destinationType,
                'item_id' => $identity['item_id'],
                'material_id' => $identity['material_id'],
                'buy_uom_id' => $identity['buy_uom_id'],
                'content_uom_id' => $identity['content_uom_id'],
                'profile_key' => $identity['profile_key'],
                'lot_no' => (string)($lot['lot_no'] ?? ''),
                'receipt_date' => (string)($lot['receipt_date'] ?? $issueDate),
                'expiry_date' => $this->normalizeDate((string)($lot['expiry_date'] ?? '')),
                'unit_cost' => max(0, round((float)($lot['unit_cost'] ?? 0), 6)),
                'source_table' => $this->nullableString($payload['source_table'] ?? null),
                'source_id' => $this->nullableInt($payload['source_id'] ?? null),
                'source_line_id' => $this->nullableInt($payload['source_line_id'] ?? null),
                'receipt_id' => null,
                'receipt_line_id' => null,
                'parent_lot_id' => $lotId,
            ], $takeQty, 0.0);
            if (!($divisionMutation['ok'] ?? false)) {
                return $divisionMutation;
            }

            $unitCost = max(0, round((float)($lot['unit_cost'] ?? 0), 6));
            $lineCost = round($takeQty * $unitCost, 2);
            $this->ci->db->insert('inv_material_fifo_issue_line', [
                'issue_id' => $issueId,
                'lot_id' => $lotId,
                'target_lot_id' => (int)($divisionMutation['data']['lot_id'] ?? 0) > 0 ? (int)$divisionMutation['data']['lot_id'] : null,
                'qty_out' => $takeQty,
                'unit_cost' => $unitCost,
                'total_cost' => $lineCost,
                'source_balance_before' => $lotBalance,
                'source_balance_after' => round((float)($warehouseMutation['data']['qty_balance'] ?? 0), 4),
                'target_balance_before' => 0,
                'target_balance_after' => round((float)($divisionMutation['data']['qty_balance'] ?? 0), 4),
            ]);
            if ((int)($this->ci->db->insert_id() ?? 0) <= 0) {
                return ['ok' => false, 'message' => 'Gagal menyimpan detail issue FIFO.'];
            }

            $allocations[] = [
                'source_lot_id' => $lotId,
                'source_lot_no' => (string)($lot['lot_no'] ?? ''),
                'target_lot_id' => (int)($divisionMutation['data']['lot_id'] ?? 0),
                'qty_content' => $takeQty,
                'unit_cost' => $unitCost,
                'total_cost' => $lineCost,
            ];
            $totalCost = round($totalCost + $lineCost, 2);
            $remaining = round($remaining - $takeQty, 4);
        }

        if ($remaining > 0.0001) {
            return ['ok' => false, 'message' => 'FIFO issue tidak lengkap.'];
        }

        $this->ci->db->where('id', $issueId)->update('inv_material_fifo_issue_log', [
            'total_cost' => $totalCost,
        ]);

        return [
            'ok' => true,
            'message' => 'Transfer FIFO warehouse ke division berhasil.',
            'data' => [
                'issue_id' => $issueId,
                'issue_no' => $issueNo,
                'allocations' => $allocations,
                'total_cost' => $totalCost,
                'avg_unit_cost' => $qtyNeed > 0 ? round($totalCost / $qtyNeed, 6) : 0.0,
            ],
        ];
    }

    public function consumeWarehouseUsage(array $payload): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        $issueDate = $this->normalizeDate((string)($payload['issue_date'] ?? ($payload['movement_date'] ?? '')));
        $qtyNeed = round((float)($payload['qty_content_out'] ?? 0), 4);
        if ($issueDate === null) {
            return ['ok' => false, 'message' => 'Pemakaian FIFO gudang membutuhkan issue_date yang valid.'];
        }
        if ($qtyNeed <= 0) {
            return ['ok' => false, 'message' => 'qty_content_out wajib lebih besar dari nol.'];
        }

        $identity = $this->normalizeLotIdentity(array_merge($payload, [
            'location_scope' => 'WAREHOUSE',
            'division_id' => null,
            'destination_type' => 'GUDANG',
        ]), true);
        if (!($identity['ok'] ?? false)) {
            return $identity;
        }

        $coverage = $this->synchronizeWarehouseLotsFromAggregate($identity);
        if (!($coverage['ok'] ?? false)) {
            return $coverage;
        }

        $warehouseLots = $this->findIssueSourceLots($identity, [
            'allow_any_item_id' => ($identity['item_id'] ?? null) === null && ($identity['material_id'] ?? null) !== null,
            'allow_any_buy_uom' => ($identity['buy_uom_id'] ?? null) === null,
            'allow_any_profile_key' => ($identity['profile_key'] ?? null) === null,
        ]);

        $available = 0.0;
        foreach ($warehouseLots as $lot) {
            $available += round((float)($lot['qty_balance'] ?? 0), 4);
        }
        $available = round($available, 4);
        if ($available + 0.0001 < $qtyNeed) {
            return [
                'ok' => false,
                'message' => 'Saldo FIFO gudang tidak cukup. Dibutuhkan ' . number_format($qtyNeed, 4, '.', '') . ', tersedia ' . number_format($available, 4, '.', '') . '.',
            ];
        }

        $issueNo = $this->generateIssueNo($issueDate);
        $issueData = [
            'issue_no' => $issueNo,
            'issue_date' => $issueDate,
            'issue_datetime' => date('Y-m-d H:i:s'),
            'location_scope' => 'WAREHOUSE',
            'division_id' => null,
            'destination_type' => 'GUDANG',
            'target_scope' => null,
            'target_division_id' => null,
            'target_destination_type' => null,
            'item_id' => $identity['item_id'],
            'material_id' => $identity['material_id'],
            'buy_uom_id' => $identity['buy_uom_id'],
            'content_uom_id' => $identity['content_uom_id'],
            'profile_key' => $identity['profile_key'],
            'issue_qty' => $qtyNeed,
            'total_cost' => 0,
            'source_module' => $this->nullableString($payload['source_module'] ?? 'INVENTORY_ADJUSTMENT'),
            'source_table' => $this->nullableString($payload['source_table'] ?? null),
            'source_id' => $this->nullableInt($payload['source_id'] ?? null),
            'source_line_id' => $this->nullableInt($payload['source_line_id'] ?? null),
            'notes' => $this->nullableString($payload['notes'] ?? null),
            'status' => 'POSTED',
        ];
        $this->ci->db->insert('inv_material_fifo_issue_log', $issueData);
        $issueId = (int)$this->ci->db->insert_id();
        if ($issueId <= 0) {
            return ['ok' => false, 'message' => 'Gagal membuat log usage FIFO gudang.'];
        }

        $remaining = $qtyNeed;
        $totalCost = 0.0;
        $allocations = [];

        foreach ($warehouseLots as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $lotId = (int)($lot['id'] ?? 0);
            $lotBalance = round((float)($lot['qty_balance'] ?? 0), 4);
            if ($lotId <= 0 || $lotBalance <= 0) {
                continue;
            }

            $takeQty = round(min($remaining, $lotBalance), 4);
            if ($takeQty <= 0) {
                continue;
            }

            $warehouseMutation = $this->applyLotMutation([
                'lot_id' => $lotId,
                'location_scope' => 'WAREHOUSE',
                'division_id' => null,
                'destination_type' => 'GUDANG',
                'item_id' => $this->nullableInt($lot['item_id'] ?? null),
                'material_id' => $this->nullableInt($lot['material_id'] ?? null),
                'buy_uom_id' => $this->nullableInt($lot['buy_uom_id'] ?? null),
                'content_uom_id' => $this->nullableInt($lot['content_uom_id'] ?? null),
                'profile_key' => $this->nullableString($lot['profile_key'] ?? null),
                'lot_no' => (string)($lot['lot_no'] ?? ''),
                'receipt_date' => (string)($lot['receipt_date'] ?? $issueDate),
                'expiry_date' => $this->normalizeDate((string)($lot['expiry_date'] ?? '')),
                'unit_cost' => max(0, round((float)($lot['unit_cost'] ?? 0), 6)),
                'source_table' => $this->nullableString($lot['source_table'] ?? null),
                'source_id' => $this->nullableInt($lot['source_id'] ?? null),
                'source_line_id' => $this->nullableInt($lot['source_line_id'] ?? null),
                'receipt_id' => $this->nullableInt($lot['receipt_id'] ?? null),
                'receipt_line_id' => $this->nullableInt($lot['receipt_line_id'] ?? null),
                'parent_lot_id' => $this->nullableInt($lot['parent_lot_id'] ?? null),
            ], 0.0, $takeQty);
            if (!($warehouseMutation['ok'] ?? false)) {
                return $warehouseMutation;
            }

            $unitCost = max(0, round((float)($lot['unit_cost'] ?? 0), 6));
            $lineCost = round($takeQty * $unitCost, 2);
            $this->ci->db->insert('inv_material_fifo_issue_line', [
                'issue_id' => $issueId,
                'lot_id' => $lotId,
                'target_lot_id' => null,
                'qty_out' => $takeQty,
                'unit_cost' => $unitCost,
                'total_cost' => $lineCost,
                'source_balance_before' => $lotBalance,
                'source_balance_after' => round((float)($warehouseMutation['data']['qty_balance'] ?? 0), 4),
                'target_balance_before' => null,
                'target_balance_after' => null,
            ]);
            if ((int)($this->ci->db->insert_id() ?? 0) <= 0) {
                return ['ok' => false, 'message' => 'Gagal menyimpan detail usage FIFO gudang.'];
            }

            $allocations[] = [
                'source_lot_id' => $lotId,
                'source_lot_no' => (string)($lot['lot_no'] ?? ''),
                'qty_content' => $takeQty,
                'unit_cost' => $unitCost,
                'total_cost' => $lineCost,
                'source_lot' => [
                    'item_id' => $this->nullableInt($lot['item_id'] ?? null),
                    'material_id' => $this->nullableInt($lot['material_id'] ?? null),
                    'buy_uom_id' => $this->nullableInt($lot['buy_uom_id'] ?? null),
                    'content_uom_id' => $this->nullableInt($lot['content_uom_id'] ?? null),
                    'profile_key' => $this->nullableString($lot['profile_key'] ?? null),
                    'lot_no' => (string)($lot['lot_no'] ?? ''),
                    'receipt_date' => (string)($lot['receipt_date'] ?? ''),
                    'expiry_date' => $this->normalizeDate((string)($lot['expiry_date'] ?? '')),
                ],
            ];
            $totalCost = round($totalCost + $lineCost, 2);
            $remaining = round($remaining - $takeQty, 4);
        }

        if ($remaining > 0.0001) {
            return ['ok' => false, 'message' => 'FIFO usage gudang tidak lengkap.'];
        }

        $this->ci->db->where('id', $issueId)->update('inv_material_fifo_issue_log', [
            'total_cost' => $totalCost,
        ]);

        return [
            'ok' => true,
            'message' => 'Pemakaian FIFO gudang berhasil diposting.',
            'data' => [
                'issue_id' => $issueId,
                'issue_no' => $issueNo,
                'allocations' => $allocations,
                'total_cost' => $totalCost,
                'avg_unit_cost' => $qtyNeed > 0 ? round($totalCost / $qtyNeed, 6) : 0.0,
            ],
        ];
    }

    public function consumeDivisionUsage(array $payload): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        $divisionId = $this->nullableInt($payload['division_id'] ?? null);
        $destinationType = $this->normalizeDestinationType((string)($payload['destination_type'] ?? ''));
        $issueDate = $this->normalizeDate((string)($payload['issue_date'] ?? ($payload['movement_date'] ?? '')));
        $qtyNeed = round((float)($payload['qty_content_out'] ?? 0), 4);

        if ($divisionId === null || $destinationType === null || $destinationType === 'GUDANG' || $issueDate === null) {
            return ['ok' => false, 'message' => 'Pemakaian FIFO divisi membutuhkan division_id, destination_type, dan issue_date yang valid.'];
        }
        if ($qtyNeed <= 0) {
            return ['ok' => false, 'message' => 'qty_content_out wajib lebih besar dari nol.'];
        }

        $identity = $this->normalizeLotIdentity(array_merge($payload, [
            'location_scope' => 'DIVISION',
            'division_id' => $divisionId,
            'destination_type' => $destinationType,
        ]), false);
        if (!($identity['ok'] ?? false)) {
            return $identity;
        }

        $broadSearchOptions = [
            'allow_any_item_id' => true,
            'allow_any_buy_uom' => true,
            'allow_any_content_uom' => true,
            'allow_any_profile_key' => true,
        ];

        // Consumption is material-centric: find ALL lots for this material regardless of which
        // purchase catalog (profile_key / item_id) they came from. Profile-key filtering is only
        // meaningful for inbound receipts; for outbound consumption the primary key is material_id.
        $hasMaterialId = ($identity['material_id'] ?? null) !== null;
        $divisionLots = $this->findIssueSourceLots($identity, [
            'allow_any_item_id'  => $hasMaterialId,
            'allow_any_buy_uom'  => ($identity['buy_uom_id'] ?? null) === null,
            'allow_any_profile_key' => $hasMaterialId,
        ]);

        // Broad fallback (also relax content_uom) when the above still finds nothing.
        if (empty($divisionLots) && $hasMaterialId) {
            $divisionLots = $this->findIssueSourceLots($identity, $broadSearchOptions);
        }

        $available = 0.0;
        foreach ($divisionLots as $lot) {
            $available += round((float)($lot['qty_balance'] ?? 0), 4);
        }
        $available = round($available, 4);

        // Broad fallback when strict search finds some lots but total balance is still insufficient.
        // This covers the case where stock came from multiple purchase batches with different
        // profile_key / item_id values, so only one batch was visible in the strict search.
        if ($available + 0.0001 < $qtyNeed && !empty($divisionLots) && ($identity['material_id'] ?? null) !== null) {
            $broadLots = $this->findIssueSourceLots($identity, $broadSearchOptions);
            $broadAvailable = 0.0;
            foreach ($broadLots as $lot) {
                $broadAvailable += round((float)($lot['qty_balance'] ?? 0), 4);
            }
            $broadAvailable = round($broadAvailable, 4);
            if ($broadAvailable > $available) {
                $divisionLots = $broadLots;
                $available = $broadAvailable;
            }
        }

        if ($available + 0.0001 < $qtyNeed) {
            return [
                'ok' => false,
                'message' => 'Saldo FIFO divisi tidak cukup. Dibutuhkan ' . number_format($qtyNeed, 4, '.', '') . ', tersedia ' . number_format($available, 4, '.', '') . '.',
            ];
        }

        // Per-profile balance pre-check: ensure that consuming from these lots will not cause
        // any individual profile_key's monthly stock row to go negative. This catches the
        // "crossed-profile" mismatch (all lots are profile B but monthly stock B < consumption).
        if (($identity['material_id'] ?? null) !== null && $this->ci->db->table_exists('inv_division_monthly_stock')) {
            $plannedByProfile = [];
            $tempRem = $qtyNeed;
            foreach ($divisionLots as $lot) {
                if ($tempRem <= 0.0001) { break; }
                $lb = round((float)($lot['qty_balance'] ?? 0), 4);
                if ($lb <= 0) { continue; }
                $take = round(min($tempRem, $lb), 4);
                $pk   = (string)($lot['profile_key'] ?? '');
                $plannedByProfile[$pk] = round(($plannedByProfile[$pk] ?? 0.0) + $take, 4);
                $tempRem = round($tempRem - $take, 4);
            }

            if (!empty($plannedByProfile)) {
                $divId = (int)$divisionId;
                $matId = (int)$identity['material_id'];
                $destT = (string)$destinationType;

                $latestMonthSub = "SELECT ms2.division_id, ms2.destination_type, ms2.identity_key, MAX(ms2.month_key) AS max_month
                                   FROM inv_division_monthly_stock ms2
                                   WHERE ms2.division_id = {$divId} AND ms2.material_id = {$matId}
                                   GROUP BY ms2.division_id, ms2.destination_type, ms2.identity_key";

                $stockRows = $this->ci->db->query("
                    SELECT COALESCE(ms.profile_key, '') AS profile_key, SUM(ms.closing_qty_content) AS stock_balance
                    FROM inv_division_monthly_stock ms
                    INNER JOIN ({$latestMonthSub}) lm
                        ON  lm.division_id      = ms.division_id
                        AND lm.destination_type = ms.destination_type
                        AND lm.identity_key     = ms.identity_key
                        AND lm.max_month        = ms.month_key
                    WHERE ms.division_id = {$divId} AND ms.material_id = {$matId}
                      AND ms.destination_type = ?
                    GROUP BY ms.profile_key
                ", [$destT])->result_array();

                $stockByProfile = [];
                foreach ($stockRows as $sr) {
                    $pk = (string)($sr['profile_key'] ?? '');
                    $stockByProfile[$pk] = round((float)($sr['stock_balance'] ?? 0), 4);
                }

                // Only run check when monthly stock data exists for this material/destination
                if (!empty($stockByProfile)) {
                    $profileErrors = [];
                    foreach ($plannedByProfile as $pk => $plannedQty) {
                        $stockBal = $stockByProfile[$pk] ?? 0.0;
                        if ($stockBal - $plannedQty < -0.01) {
                            $pkLabel = $pk !== '' ? substr($pk, 0, 8) . '…' : '(no profile)';
                            $profileErrors[] = "profil {$pkLabel}: stok " . number_format($stockBal, 4, '.', '') . ', diambil ' . number_format($plannedQty, 4, '.', '');
                        }
                    }
                    if (!empty($profileErrors)) {
                        return [
                            'ok' => false,
                            'message' => 'Stok per profil tidak mencukupi (' . implode('; ', $profileErrors) . '). Jalankan Lot Repair di halaman rekonsiliasi terlebih dahulu.',
                            'profile_mismatch' => true,
                        ];
                    }
                }
            }
        }

        $issueNo = $this->generateIssueNo($issueDate);
        $issueData = [
            'issue_no' => $issueNo,
            'issue_date' => $issueDate,
            'issue_datetime' => date('Y-m-d H:i:s'),
            'location_scope' => 'DIVISION',
            'division_id' => $divisionId,
            'destination_type' => $destinationType,
            'target_scope' => null,
            'target_division_id' => null,
            'target_destination_type' => null,
            'item_id' => $identity['item_id'],
            'material_id' => $identity['material_id'],
            'buy_uom_id' => $identity['buy_uom_id'],
            'content_uom_id' => $identity['content_uom_id'],
            'profile_key' => $identity['profile_key'],
            'issue_qty' => $qtyNeed,
            'total_cost' => 0,
            'source_module' => $this->nullableString($payload['source_module'] ?? 'PRODUCTION_BATCH'),
            'source_table' => $this->nullableString($payload['source_table'] ?? null),
            'source_id' => $this->nullableInt($payload['source_id'] ?? null),
            'source_line_id' => $this->nullableInt($payload['source_line_id'] ?? null),
            'notes' => $this->nullableString($payload['notes'] ?? null),
            'status' => 'POSTED',
        ];
        $this->ci->db->insert('inv_material_fifo_issue_log', $issueData);
        $issueId = (int)$this->ci->db->insert_id();
        if ($issueId <= 0) {
            return ['ok' => false, 'message' => 'Gagal membuat log usage FIFO divisi.'];
        }

        $remaining = $qtyNeed;
        $totalCost = 0.0;
        $allocations = [];

        foreach ($divisionLots as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $lotId = (int)($lot['id'] ?? 0);
            $lotBalance = round((float)($lot['qty_balance'] ?? 0), 4);
            if ($lotId <= 0 || $lotBalance <= 0) {
                continue;
            }

            $takeQty = round(min($remaining, $lotBalance), 4);
            if ($takeQty <= 0) {
                continue;
            }

            $lotPayload = [
                'lot_id' => $lotId,
                'location_scope' => 'DIVISION',
                'division_id' => $divisionId,
                'destination_type' => $destinationType,
                'item_id' => $this->nullableInt($lot['item_id'] ?? null),
                'material_id' => $this->nullableInt($lot['material_id'] ?? null),
                'buy_uom_id' => $this->nullableInt($lot['buy_uom_id'] ?? null),
                'content_uom_id' => $this->nullableInt($lot['content_uom_id'] ?? null),
                'profile_key' => $this->nullableString($lot['profile_key'] ?? null),
                'lot_no' => (string)($lot['lot_no'] ?? ''),
                'receipt_date' => (string)($lot['receipt_date'] ?? $issueDate),
                'expiry_date' => $this->normalizeDate((string)($lot['expiry_date'] ?? '')),
                'unit_cost' => max(0, round((float)($lot['unit_cost'] ?? 0), 6)),
                'source_table' => $this->nullableString($lot['source_table'] ?? null),
                'source_id' => $this->nullableInt($lot['source_id'] ?? null),
                'source_line_id' => $this->nullableInt($lot['source_line_id'] ?? null),
                'receipt_id' => $this->nullableInt($lot['receipt_id'] ?? null),
                'receipt_line_id' => $this->nullableInt($lot['receipt_line_id'] ?? null),
                'parent_lot_id' => $this->nullableInt($lot['parent_lot_id'] ?? null),
            ];
            $divisionMutation = $this->applyLotMutation($lotPayload, 0.0, $takeQty);

            // Concurrent depletion: re-read the locked balance and take only what's left.
            if (!($divisionMutation['ok'] ?? false)) {
                $freshLot = $this->findLotById($lotId, true);
                $freshBalance = $freshLot ? round((float)($freshLot['qty_balance'] ?? 0), 4) : 0.0;
                if ($freshBalance <= 0) {
                    continue; // Lot was fully consumed concurrently; skip to next lot.
                }
                $takeQty = round(min($remaining, $freshBalance), 4);
                if ($takeQty <= 0) {
                    continue;
                }
                $divisionMutation = $this->applyLotMutation($lotPayload, 0.0, $takeQty);
                if (!($divisionMutation['ok'] ?? false)) {
                    return $divisionMutation; // Still failing — propagate error.
                }
                $lotBalance = $freshBalance;
            }

            $unitCost = max(0, round((float)($lot['unit_cost'] ?? 0), 6));
            $lineCost = round($takeQty * $unitCost, 2);
            $this->ci->db->insert('inv_material_fifo_issue_line', [
                'issue_id' => $issueId,
                'lot_id' => $lotId,
                'target_lot_id' => null,
                'qty_out' => $takeQty,
                'unit_cost' => $unitCost,
                'total_cost' => $lineCost,
                'source_balance_before' => $lotBalance,
                'source_balance_after' => round((float)($divisionMutation['data']['qty_balance'] ?? 0), 4),
                'target_balance_before' => null,
                'target_balance_after' => null,
            ]);
            if ((int)($this->ci->db->insert_id() ?? 0) <= 0) {
                return ['ok' => false, 'message' => 'Gagal menyimpan detail usage FIFO divisi.'];
            }

            $allocations[] = [
                'source_lot_id' => $lotId,
                'source_lot_no' => (string)($lot['lot_no'] ?? ''),
                'qty_content' => $takeQty,
                'unit_cost' => $unitCost,
                'total_cost' => $lineCost,
                'source_lot' => [
                    'item_id' => $this->nullableInt($lot['item_id'] ?? null),
                    'material_id' => $this->nullableInt($lot['material_id'] ?? null),
                    'buy_uom_id' => $this->nullableInt($lot['buy_uom_id'] ?? null),
                    'content_uom_id' => $this->nullableInt($lot['content_uom_id'] ?? null),
                    'profile_key' => $this->nullableString($lot['profile_key'] ?? null),
                    'lot_no' => (string)($lot['lot_no'] ?? ''),
                    'receipt_date' => (string)($lot['receipt_date'] ?? ''),
                    'expiry_date' => $this->normalizeDate((string)($lot['expiry_date'] ?? '')),
                ],
            ];
            $totalCost = round($totalCost + $lineCost, 2);
            $remaining = round($remaining - $takeQty, 4);
        }

        if ($remaining > 0.0001) {
            return ['ok' => false, 'message' => 'FIFO usage divisi tidak lengkap.'];
        }

        $this->ci->db->where('id', $issueId)->update('inv_material_fifo_issue_log', [
            'total_cost' => $totalCost,
        ]);

        return [
            'ok' => true,
            'message' => 'Pemakaian FIFO divisi berhasil diposting.',
            'data' => [
                'issue_id' => $issueId,
                'issue_no' => $issueNo,
                'allocations' => $allocations,
                'total_cost' => $totalCost,
                'avg_unit_cost' => $qtyNeed > 0 ? round($totalCost / $qtyNeed, 6) : 0.0,
            ],
        ];
    }

    public function previewDivisionUsageState(array $payload): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        $identity = $this->normalizeLotIdentity(array_merge($payload, [
            'location_scope' => 'DIVISION',
        ]), false);
        if (!($identity['ok'] ?? false)) {
            return $identity;
        }

        $lots = $this->findIssueSourceLots($identity, [
            'allow_any_item_id' => ($identity['item_id'] ?? null) === null && ($identity['material_id'] ?? null) !== null,
            'allow_any_buy_uom' => ($identity['buy_uom_id'] ?? null) === null,
            'allow_any_profile_key' => ($identity['profile_key'] ?? null) === null,
        ]);
        $matchedMode = 'EXACT';
        if (empty($lots) && ($identity['material_id'] ?? null) !== null) {
            $lots = $this->findIssueSourceLots($identity, [
                'allow_any_item_id' => true,
                'allow_any_buy_uom' => true,
                'allow_any_content_uom' => true,
                'allow_any_profile_key' => true,
            ]);
            $matchedMode = 'BROAD';
        }

        $availableQty = 0.0;
        $totalValue = 0.0;
        $profileKeys = [];
        foreach ($lots as $lot) {
            $qtyBalance = round((float)($lot['qty_balance'] ?? 0), 4);
            if ($qtyBalance <= 0) {
                continue;
            }
            $unitCost = max(0, round((float)($lot['unit_cost'] ?? 0), 6));
            $availableQty = round($availableQty + $qtyBalance, 4);
            $totalValue = round($totalValue + round($qtyBalance * $unitCost, 2), 2);
            $profileKey = trim((string)($lot['profile_key'] ?? ''));
            if ($profileKey !== '') {
                $profileKeys[$profileKey] = true;
            }
        }
        $avgUnitCost = $availableQty > 0.0001
            ? round($totalValue / $availableQty, 6)
            : 0.0;

        $stockKeyParts = [
            'DIVISION',
            (string)($identity['division_id'] ?? 'NULL'),
            (string)($identity['destination_type'] ?? 'OTHER'),
            (string)($identity['item_id'] ?? 0),
            (string)($identity['material_id'] ?? 0),
            (string)($identity['content_uom_id'] ?? 0),
            $matchedMode,
        ];
        if ($matchedMode === 'EXACT') {
            $stockKeyParts[] = (string)($identity['buy_uom_id'] ?? 0);
            $stockKeyParts[] = (string)($identity['profile_key'] ?? '');
        }

        return [
            'ok' => true,
            'data' => [
                'identity' => $identity,
                'lots' => $lots,
                'available_qty' => round($availableQty, 4),
                'avg_unit_cost' => $avgUnitCost,
                'total_value' => round($totalValue, 2),
                'matched_mode' => $matchedMode,
                'matched_profile_keys' => array_keys($profileKeys),
                'stock_key' => implode('|', $stockKeyParts),
            ],
        ];
    }

    public function syncDivisionMonthlyStockFromLots(array $payload): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }
        if (!$this->ci->db->table_exists('inv_division_monthly_stock')) {
            return ['ok' => true, 'data' => ['skipped' => true, 'reason' => 'missing_table']];
        }

        $identity = $this->normalizeLotIdentity(array_merge($payload, [
            'location_scope' => 'DIVISION',
        ]), false);
        if (!($identity['ok'] ?? false)) {
            return $identity;
        }

        $lots = $this->findIssueSourceLots($identity, [
            'allow_any_item_id' => false,
            'allow_any_buy_uom' => false,
            'allow_any_content_uom' => false,
            'allow_any_profile_key' => false,
        ]);

        $qtyBalance = 0.0;
        $totalValue = 0.0;
        foreach ($lots as $lot) {
            $lotQty = round((float)($lot['qty_balance'] ?? 0), 4);
            if ($lotQty <= 0) {
                continue;
            }
            $lotUnitCost = max(0, round((float)($lot['unit_cost'] ?? 0), 6));
            $qtyBalance = round($qtyBalance + $lotQty, 4);
            $totalValue = round($totalValue + round($lotQty * $lotUnitCost, 2), 2);
        }
        $avgCost = $qtyBalance > 0.0001 ? round($totalValue / $qtyBalance, 6) : 0.0;

        $monthKey = date('Y-m-01', strtotime((string)($payload['movement_date'] ?? $payload['issue_date'] ?? date('Y-m-d'))));
        $identityKey = $this->buildMonthlyIdentityKeyFromLotIdentity($identity);

        $existing = $this->ci->db->query(
            'SELECT * FROM inv_division_monthly_stock
             WHERE month_key = ?
               AND division_id = ?
               AND destination_type = ?
               AND item_id <=> ?
               AND material_id <=> ?
               AND buy_uom_id <=> ?
               AND content_uom_id = ?
               AND profile_key <=> ?
             ORDER BY id DESC
             LIMIT 1 FOR UPDATE',
            [
                $monthKey,
                (int)$identity['division_id'],
                (string)$identity['destination_type'],
                $this->nullableInt($identity['item_id'] ?? null),
                $this->nullableInt($identity['material_id'] ?? null),
                $this->nullableInt($identity['buy_uom_id'] ?? null),
                (int)$identity['content_uom_id'],
                $this->nullableString($identity['profile_key'] ?? null),
            ]
        )->row_array();

        $sameUom = ($identity['buy_uom_id'] ?? null) !== null
            && (int)($identity['buy_uom_id'] ?? 0) === (int)($identity['content_uom_id'] ?? 0);
        $contentPerBuy = $sameUom
            ? 1.0
            : max(0.000001, round((float)($existing['profile_content_per_buy'] ?? 1), 6));
        $qtyBuyBalance = $qtyBalance > 0.0001 ? round($qtyBalance / $contentPerBuy, 4) : 0.0;
        $syncNote = trim((string)($payload['sync_note'] ?? ''));
        if ($syncNote === '') {
            $syncNote = 'Synced from FIFO lots';
        }

        if ($existing) {
            $update = [
                'identity_key' => $identityKey,
                'closing_qty_buy' => $qtyBuyBalance,
                'closing_qty_content' => $qtyBalance,
                'avg_cost_per_content' => $avgCost,
                'total_value' => round($totalValue, 2),
                'last_movement_date' => (string)($payload['movement_date'] ?? $payload['issue_date'] ?? date('Y-m-d')),
                'last_movement_at' => date('Y-m-d H:i:s'),
                'notes' => $syncNote,
            ];
            $this->ci->db->where('id', (int)$existing['id'])->update('inv_division_monthly_stock', $update);
        } else {
            $insert = [
                'month_key' => $monthKey,
                'identity_key' => $identityKey,
                'division_id' => (int)$identity['division_id'],
                'destination_type' => (string)$identity['destination_type'],
                'item_id' => $this->nullableInt($identity['item_id'] ?? null),
                'material_id' => $this->nullableInt($identity['material_id'] ?? null),
                'buy_uom_id' => $this->nullableInt($identity['buy_uom_id'] ?? null),
                'content_uom_id' => (int)$identity['content_uom_id'],
                'profile_key' => $this->nullableString($identity['profile_key'] ?? null),
                'profile_content_per_buy' => $contentPerBuy,
                'opening_qty_buy' => $qtyBuyBalance,
                'opening_qty_content' => $qtyBalance,
                'opening_total_value' => round($totalValue, 2),
                'closing_qty_buy' => $qtyBuyBalance,
                'closing_qty_content' => $qtyBalance,
                'avg_cost_per_content' => $avgCost,
                'total_value' => round($totalValue, 2),
                'movement_day_count' => 0,
                'mutation_count' => 0,
                'last_movement_date' => (string)($payload['movement_date'] ?? $payload['issue_date'] ?? date('Y-m-d')),
                'last_movement_at' => date('Y-m-d H:i:s'),
                'source_mode' => 'LIVE',
                'notes' => $syncNote,
            ];
            $this->ci->db->insert('inv_division_monthly_stock', $insert);
        }

        if ($this->ci->db->trans_status() === false) {
            return ['ok' => false, 'message' => 'Gagal sinkron saldo bulanan divisi dari FIFO lot.'];
        }

        return [
            'ok' => true,
            'data' => [
                'qty_balance' => $qtyBalance,
                'qty_buy_balance' => $qtyBuyBalance,
                'avg_cost_per_content' => $avgCost,
                'total_value' => round($totalValue, 2),
                'month_key' => $monthKey,
                'identity_key' => $identityKey,
            ],
        ];
    }

    public function syncWarehouseMonthlyStockFromLots(array $payload): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }
        if (!$this->ci->db->table_exists('inv_warehouse_monthly_stock')) {
            return ['ok' => true, 'data' => ['skipped' => true, 'reason' => 'missing_table']];
        }

        $identity = $this->normalizeLotIdentity(array_merge($payload, [
            'location_scope' => 'WAREHOUSE',
            'division_id' => null,
            'destination_type' => 'GUDANG',
        ]), false);
        if (!($identity['ok'] ?? false)) {
            return $identity;
        }

        $lots = $this->findIssueSourceLots($identity, [
            'allow_any_item_id' => false,
            'allow_any_buy_uom' => false,
            'allow_any_content_uom' => false,
            'allow_any_profile_key' => false,
        ]);

        $qtyBalance = 0.0;
        $totalValue = 0.0;
        foreach ($lots as $lot) {
            $lotQty = round((float)($lot['qty_balance'] ?? 0), 4);
            if ($lotQty <= 0) {
                continue;
            }
            $lotUnitCost = max(0, round((float)($lot['unit_cost'] ?? 0), 6));
            $qtyBalance = round($qtyBalance + $lotQty, 4);
            $totalValue = round($totalValue + round($lotQty * $lotUnitCost, 2), 2);
        }
        $avgCost = $qtyBalance > 0.0001 ? round($totalValue / $qtyBalance, 6) : 0.0;

        $monthKey = date('Y-m-01', strtotime((string)($payload['movement_date'] ?? $payload['issue_date'] ?? date('Y-m-d'))));
        $identityKey = $this->buildMonthlyIdentityKeyFromLotIdentity($identity);

        $existing = $this->ci->db->query(
            'SELECT * FROM inv_warehouse_monthly_stock
             WHERE month_key = ?
               AND item_id <=> ?
               AND material_id <=> ?
               AND buy_uom_id <=> ?
               AND content_uom_id = ?
               AND profile_key <=> ?
             ORDER BY id DESC
             LIMIT 1 FOR UPDATE',
            [
                $monthKey,
                $this->nullableInt($identity['item_id'] ?? null),
                $this->nullableInt($identity['material_id'] ?? null),
                $this->nullableInt($identity['buy_uom_id'] ?? null),
                (int)$identity['content_uom_id'],
                $this->nullableString($identity['profile_key'] ?? null),
            ]
        )->row_array();

        $sameUom = ($identity['buy_uom_id'] ?? null) !== null
            && (int)($identity['buy_uom_id'] ?? 0) === (int)($identity['content_uom_id'] ?? 0);
        $contentPerBuy = $sameUom
            ? 1.0
            : max(0.000001, round((float)($existing['profile_content_per_buy'] ?? 1), 6));
        $qtyBuyBalance = $qtyBalance > 0.0001 ? round($qtyBalance / $contentPerBuy, 4) : 0.0;
        $syncNote = trim((string)($payload['sync_note'] ?? ''));
        if ($syncNote === '') {
            $syncNote = 'Synced from FIFO lots';
        }

        if ($existing) {
            $update = [
                'identity_key' => $identityKey,
                'closing_qty_buy' => $qtyBuyBalance,
                'closing_qty_content' => $qtyBalance,
                'avg_cost_per_content' => $avgCost,
                'total_value' => round($totalValue, 2),
                'last_movement_date' => (string)($payload['movement_date'] ?? $payload['issue_date'] ?? date('Y-m-d')),
                'last_movement_at' => date('Y-m-d H:i:s'),
                'notes' => $syncNote,
            ];
            $this->ci->db->where('id', (int)$existing['id'])->update('inv_warehouse_monthly_stock', $update);
        } else {
            $insert = [
                'month_key' => $monthKey,
                'identity_key' => $identityKey,
                'item_id' => $this->nullableInt($identity['item_id'] ?? null),
                'material_id' => $this->nullableInt($identity['material_id'] ?? null),
                'buy_uom_id' => $this->nullableInt($identity['buy_uom_id'] ?? null),
                'content_uom_id' => (int)$identity['content_uom_id'],
                'profile_key' => $this->nullableString($identity['profile_key'] ?? null),
                'profile_content_per_buy' => $contentPerBuy,
                'opening_qty_buy' => $qtyBuyBalance,
                'opening_qty_content' => $qtyBalance,
                'opening_total_value' => round($totalValue, 2),
                'closing_qty_buy' => $qtyBuyBalance,
                'closing_qty_content' => $qtyBalance,
                'avg_cost_per_content' => $avgCost,
                'total_value' => round($totalValue, 2),
                'movement_day_count' => 0,
                'mutation_count' => 0,
                'last_movement_date' => (string)($payload['movement_date'] ?? $payload['issue_date'] ?? date('Y-m-d')),
                'last_movement_at' => date('Y-m-d H:i:s'),
                'source_mode' => 'LIVE',
                'notes' => $syncNote,
            ];
            $this->ci->db->insert('inv_warehouse_monthly_stock', $insert);
        }

        if ($this->ci->db->trans_status() === false) {
            return ['ok' => false, 'message' => 'Gagal sinkron saldo bulanan gudang dari FIFO lot.'];
        }

        return [
            'ok' => true,
            'data' => [
                'qty_balance' => $qtyBalance,
                'qty_buy_balance' => $qtyBuyBalance,
                'avg_cost_per_content' => $avgCost,
                'total_value' => round($totalValue, 2),
                'month_key' => $monthKey,
                'identity_key' => $identityKey,
            ],
        ];
    }

    public function rollbackReceiptInboundLotsBySource(string $sourceTable, int $sourceId, ?int $sourceLineId = null): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        $lots = $this->findLotsBySource($sourceTable, $sourceId, $sourceLineId);
        if (empty($lots)) {
            return ['ok' => true, 'data' => ['lot_count' => 0]];
        }

        foreach ($lots as $lot) {
            $lotId = (int)($lot['id'] ?? 0);
            $qtyIn = round((float)($lot['qty_in'] ?? 0), 4);
            $qtyOut = round((float)($lot['qty_out'] ?? 0), 4);
            $qtyBalance = round((float)($lot['qty_balance'] ?? 0), 4);

            if ($lotId <= 0 || $qtyIn <= 0) {
                continue;
            }
            if ($qtyOut > 0.0001 || $qtyBalance + 0.0001 < $qtyIn) {
                return [
                    'ok' => false,
                    'message' => 'Rollback lot receipt ditolak karena lot ' . (string)($lot['lot_no'] ?? ('#' . $lotId)) . ' sudah terpakai.',
                ];
            }

            $this->ci->db->where('id', $lotId)->update('inv_material_fifo_lot', [
                'qty_in' => 0,
                'qty_balance' => 0,
                'status' => 'CLOSED',
            ]);
            if ($this->ci->db->trans_status() === false) {
                return ['ok' => false, 'message' => 'Gagal rollback lot receipt inbound.'];
            }
        }

        return ['ok' => true, 'data' => ['lot_count' => count($lots)]];
    }

    public function rollbackTransferLotsBySource(string $sourceTable, int $sourceId, ?int $sourceLineId = null, string $voidNote = ''): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        $this->ci->db->from('inv_material_fifo_issue_log')
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->where('status', 'POSTED');
        if ($sourceLineId !== null) {
            $this->ci->db->where('source_line_id', $sourceLineId);
        }
        $issues = $this->ci->db->order_by('id', 'DESC')->get()->result_array();

        if (empty($issues)) {
            return ['ok' => true, 'data' => ['issue_count' => 0, 'line_count' => 0]];
        }

        $lineCount = 0;
        foreach ($issues as $issue) {
            $issueId = (int)($issue['id'] ?? 0);
            if ($issueId <= 0) {
                continue;
            }

            $lines = $this->ci->db
                ->from('inv_material_fifo_issue_line')
                ->where('issue_id', $issueId)
                ->order_by('id', 'DESC')
                ->get()
                ->result_array();

            foreach ($lines as $line) {
                $qtyOut = round((float)($line['qty_out'] ?? 0), 4);
                if ($qtyOut <= 0) {
                    continue;
                }

                $targetLotId = $this->nullableInt($line['target_lot_id'] ?? null);
                if ($targetLotId !== null) {
                    $targetLot = $this->findLotById($targetLotId, true);
                    if (!$targetLot) {
                        return ['ok' => false, 'message' => 'Lot target transfer tidak ditemukan saat rollback FIFO.'];
                    }
                    $targetBalance = round((float)($targetLot['qty_balance'] ?? 0), 4);
                    if ($targetBalance + 0.0001 < $qtyOut) {
                        return [
                            'ok' => false,
                            'message' => 'Rollback fulfillment ditolak karena lot tujuan ' . (string)($targetLot['lot_no'] ?? ('#' . $targetLotId)) . ' sudah terpakai.',
                        ];
                    }

                    $rollbackDivision = $this->applyLotMutation([
                        'lot_id' => $targetLotId,
                        'location_scope' => (string)($targetLot['location_scope'] ?? 'DIVISION'),
                        'division_id' => $this->nullableInt($targetLot['division_id'] ?? null),
                        'destination_type' => $this->nullableString($targetLot['destination_type'] ?? null),
                        'item_id' => $this->nullableInt($targetLot['item_id'] ?? null),
                        'material_id' => $this->nullableInt($targetLot['material_id'] ?? null),
                        'buy_uom_id' => $this->nullableInt($targetLot['buy_uom_id'] ?? null),
                        'content_uom_id' => $this->nullableInt($targetLot['content_uom_id'] ?? null),
                        'profile_key' => $this->nullableString($targetLot['profile_key'] ?? null),
                        'lot_no' => (string)($targetLot['lot_no'] ?? ''),
                        'receipt_date' => (string)($targetLot['receipt_date'] ?? date('Y-m-d')),
                        'expiry_date' => $this->normalizeDate((string)($targetLot['expiry_date'] ?? '')),
                        'unit_cost' => max(0, round((float)($targetLot['unit_cost'] ?? 0), 6)),
                        'source_table' => $this->nullableString($targetLot['source_table'] ?? null),
                        'source_id' => $this->nullableInt($targetLot['source_id'] ?? null),
                        'source_line_id' => $this->nullableInt($targetLot['source_line_id'] ?? null),
                        'receipt_id' => $this->nullableInt($targetLot['receipt_id'] ?? null),
                        'receipt_line_id' => $this->nullableInt($targetLot['receipt_line_id'] ?? null),
                        'parent_lot_id' => $this->nullableInt($targetLot['parent_lot_id'] ?? null),
                    ], -1 * $qtyOut, 0.0);
                    if (!($rollbackDivision['ok'] ?? false)) {
                        return $rollbackDivision;
                    }
                }

                $sourceLot = $this->findLotById((int)($line['lot_id'] ?? 0), true);
                if (!$sourceLot) {
                    return ['ok' => false, 'message' => 'Lot sumber transfer tidak ditemukan saat rollback FIFO.'];
                }

                $rollbackWarehouse = $this->applyLotMutation([
                    'lot_id' => (int)($line['lot_id'] ?? 0),
                    'location_scope' => (string)($sourceLot['location_scope'] ?? 'WAREHOUSE'),
                    'division_id' => $this->nullableInt($sourceLot['division_id'] ?? null),
                    'destination_type' => $this->nullableString($sourceLot['destination_type'] ?? null),
                    'item_id' => $this->nullableInt($sourceLot['item_id'] ?? null),
                    'material_id' => $this->nullableInt($sourceLot['material_id'] ?? null),
                    'buy_uom_id' => $this->nullableInt($sourceLot['buy_uom_id'] ?? null),
                    'content_uom_id' => $this->nullableInt($sourceLot['content_uom_id'] ?? null),
                    'profile_key' => $this->nullableString($sourceLot['profile_key'] ?? null),
                    'lot_no' => (string)($sourceLot['lot_no'] ?? ''),
                    'receipt_date' => (string)($sourceLot['receipt_date'] ?? date('Y-m-d')),
                    'expiry_date' => $this->normalizeDate((string)($sourceLot['expiry_date'] ?? '')),
                    'unit_cost' => max(0, round((float)($sourceLot['unit_cost'] ?? 0), 6)),
                    'source_table' => $this->nullableString($sourceLot['source_table'] ?? null),
                    'source_id' => $this->nullableInt($sourceLot['source_id'] ?? null),
                    'source_line_id' => $this->nullableInt($sourceLot['source_line_id'] ?? null),
                    'receipt_id' => $this->nullableInt($sourceLot['receipt_id'] ?? null),
                    'receipt_line_id' => $this->nullableInt($sourceLot['receipt_line_id'] ?? null),
                    'parent_lot_id' => $this->nullableInt($sourceLot['parent_lot_id'] ?? null),
                ], 0.0, -1 * $qtyOut);
                if (!($rollbackWarehouse['ok'] ?? false)) {
                    return $rollbackWarehouse;
                }

                $lineCount++;
            }

            $note = trim($voidNote);
            $existingNotes = trim((string)($issue['notes'] ?? ''));
            if ($note === '') {
                $note = 'Rollback FIFO transfer';
            }
            $this->ci->db->where('id', $issueId)->update('inv_material_fifo_issue_log', [
                'status' => 'VOID',
                'voided_at' => date('Y-m-d H:i:s'),
                'notes' => $existingNotes !== '' ? ($existingNotes . ' | ' . $note) : $note,
            ]);
            if ($this->ci->db->trans_status() === false) {
                return ['ok' => false, 'message' => 'Gagal menutup issue FIFO saat rollback.'];
            }
        }

        return ['ok' => true, 'data' => ['issue_count' => count($issues), 'line_count' => $lineCount]];
    }

    private function ensureSchema(): array
    {
        if ($this->schemaEnsured) {
            return ['ok' => true];
        }

        $db = $this->ci->db;

        if (!$db->table_exists('inv_material_fifo_lot')) {
            $db->query("CREATE TABLE IF NOT EXISTS inv_material_fifo_lot (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                lot_no VARCHAR(80) NOT NULL,
                location_scope ENUM('WAREHOUSE','DIVISION') NOT NULL DEFAULT 'WAREHOUSE',
                receipt_date DATE NOT NULL,
                expiry_date DATE NULL,
                division_id BIGINT(20) UNSIGNED NULL,
                destination_type ENUM('GUDANG','BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NULL,
                item_id BIGINT(20) UNSIGNED NULL,
                material_id BIGINT(20) UNSIGNED NULL,
                buy_uom_id BIGINT(20) UNSIGNED NULL,
                content_uom_id BIGINT(20) UNSIGNED NOT NULL,
                profile_key CHAR(64) NULL,
                qty_in DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                qty_out DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                qty_balance DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
                source_table VARCHAR(80) NULL,
                source_id BIGINT(20) UNSIGNED NULL,
                source_line_id BIGINT(20) UNSIGNED NULL,
                receipt_id BIGINT(20) UNSIGNED NULL,
                receipt_line_id BIGINT(20) UNSIGNED NULL,
                parent_lot_id BIGINT(20) UNSIGNED NULL,
                status ENUM('OPEN','CLOSED') NOT NULL DEFAULT 'OPEN',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_inv_material_fifo_scope_lot (location_scope, division_id, destination_type, item_id, material_id, content_uom_id, profile_key, lot_no),
                KEY idx_inv_material_fifo_pick_scope (location_scope, division_id, destination_type, item_id, material_id, content_uom_id, profile_key, status, receipt_date, id),
                KEY idx_inv_material_fifo_source (source_table, source_id, source_line_id),
                KEY idx_inv_material_fifo_receipt_line (receipt_line_id),
                KEY idx_inv_material_fifo_parent (parent_lot_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        } else {
            $this->ensureLotTableColumns();
        }

        if (!$db->table_exists('inv_material_fifo_issue_log')) {
            $db->query("CREATE TABLE IF NOT EXISTS inv_material_fifo_issue_log (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                issue_no VARCHAR(60) NOT NULL,
                issue_date DATE NOT NULL,
                issue_datetime DATETIME NOT NULL,
                location_scope ENUM('WAREHOUSE','DIVISION') NOT NULL DEFAULT 'WAREHOUSE',
                division_id BIGINT(20) UNSIGNED NULL,
                destination_type ENUM('GUDANG','BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NULL,
                target_scope ENUM('WAREHOUSE','DIVISION') NULL,
                target_division_id BIGINT(20) UNSIGNED NULL,
                target_destination_type ENUM('GUDANG','BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NULL,
                item_id BIGINT(20) UNSIGNED NULL,
                material_id BIGINT(20) UNSIGNED NULL,
                buy_uom_id BIGINT(20) UNSIGNED NULL,
                content_uom_id BIGINT(20) UNSIGNED NOT NULL,
                profile_key CHAR(64) NULL,
                issue_qty DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                total_cost DECIMAL(18,2) NOT NULL DEFAULT 0.00,
                source_module VARCHAR(50) NOT NULL,
                source_table VARCHAR(80) NULL,
                source_id BIGINT(20) UNSIGNED NULL,
                source_line_id BIGINT(20) UNSIGNED NULL,
                notes VARCHAR(255) NULL,
                status ENUM('POSTED','VOID') NOT NULL DEFAULT 'POSTED',
                voided_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_inv_material_fifo_issue_no (issue_no),
                KEY idx_inv_material_fifo_issue_source (source_table, source_id, source_line_id, status),
                KEY idx_inv_material_fifo_issue_scope (location_scope, division_id, destination_type, item_id, material_id, content_uom_id, profile_key, issue_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        } else {
            $this->ensureIssueLogTableColumns();
        }

        if (!$db->table_exists('inv_material_fifo_issue_line')) {
            $db->query("CREATE TABLE IF NOT EXISTS inv_material_fifo_issue_line (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                issue_id BIGINT(20) UNSIGNED NOT NULL,
                lot_id BIGINT(20) UNSIGNED NOT NULL,
                target_lot_id BIGINT(20) UNSIGNED NULL,
                qty_out DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
                unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0.000000,
                total_cost DECIMAL(18,2) NOT NULL DEFAULT 0.00,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_inv_material_fifo_issue_line_issue (issue_id),
                KEY idx_inv_material_fifo_issue_line_lot (lot_id),
                KEY idx_inv_material_fifo_issue_line_target (target_lot_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        } else {
            $this->ensureIssueLineTableColumns();
        }

        $this->ensureReceiptLineColumns();
        $this->ensureFulfillmentLineColumns();

        if ((int)($db->error()['code'] ?? 0) !== 0) {
            return ['ok' => false, 'message' => 'Gagal memastikan schema FIFO material: ' . (string)($db->error()['message'] ?? 'unknown error')];
        }

        $this->schemaEnsured = true;
        return ['ok' => true];
    }

    private function ensureLotTableColumns(): void
    {
        $table = 'inv_material_fifo_lot';
        if (!$this->ci->db->field_exists('location_scope', $table)) {
            $this->ci->db->query("ALTER TABLE {$table} ADD COLUMN location_scope ENUM('WAREHOUSE','DIVISION') NOT NULL DEFAULT 'WAREHOUSE' AFTER lot_no");
        }
        if (!$this->ci->db->field_exists('destination_type', $table)) {
            $this->ci->db->query("ALTER TABLE {$table} ADD COLUMN destination_type ENUM('GUDANG','BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NULL AFTER division_id");
        }
        if (!$this->ci->db->field_exists('buy_uom_id', $table)) {
            $this->ci->db->query("ALTER TABLE {$table} ADD COLUMN buy_uom_id BIGINT(20) UNSIGNED NULL AFTER material_id");
        }
        if (!$this->ci->db->field_exists('profile_key', $table)) {
            $this->ci->db->query("ALTER TABLE {$table} ADD COLUMN profile_key CHAR(64) NULL AFTER content_uom_id");
        }
        if (!$this->ci->db->field_exists('receipt_id', $table)) {
            $this->ci->db->query("ALTER TABLE {$table} ADD COLUMN receipt_id BIGINT(20) UNSIGNED NULL AFTER source_line_id");
        }
        if (!$this->ci->db->field_exists('receipt_line_id', $table)) {
            $this->ci->db->query("ALTER TABLE {$table} ADD COLUMN receipt_line_id BIGINT(20) UNSIGNED NULL AFTER receipt_id");
        }
        if (!$this->ci->db->field_exists('parent_lot_id', $table)) {
            $this->ci->db->query("ALTER TABLE {$table} ADD COLUMN parent_lot_id BIGINT(20) UNSIGNED NULL AFTER receipt_line_id");
        }
        $this->ci->db->query("ALTER TABLE {$table} MODIFY COLUMN division_id BIGINT(20) UNSIGNED NULL");
        $this->ci->db->query("ALTER TABLE {$table} MODIFY COLUMN item_id BIGINT(20) UNSIGNED NULL");

        if ($this->hasIndex($table, 'uk_inv_material_fifo_lot_scope')) {
            $this->ci->db->query("ALTER TABLE {$table} DROP INDEX uk_inv_material_fifo_lot_scope");
        }
        if (!$this->hasIndex($table, 'uk_inv_material_fifo_scope_lot')) {
            $this->ci->db->query("ALTER TABLE {$table} ADD UNIQUE KEY uk_inv_material_fifo_scope_lot (location_scope, division_id, destination_type, item_id, material_id, content_uom_id, profile_key, lot_no)");
        }
        if (!$this->hasIndex($table, 'idx_inv_material_fifo_pick_scope')) {
            $this->ci->db->query("ALTER TABLE {$table} ADD KEY idx_inv_material_fifo_pick_scope (location_scope, division_id, destination_type, item_id, material_id, content_uom_id, profile_key, status, receipt_date, id)");
        }
        if (!$this->hasIndex($table, 'idx_inv_material_fifo_source')) {
            $this->ci->db->query("ALTER TABLE {$table} ADD KEY idx_inv_material_fifo_source (source_table, source_id, source_line_id)");
        }
        if (!$this->hasIndex($table, 'idx_inv_material_fifo_receipt_line')) {
            $this->ci->db->query("ALTER TABLE {$table} ADD KEY idx_inv_material_fifo_receipt_line (receipt_line_id)");
        }
        if (!$this->hasIndex($table, 'idx_inv_material_fifo_parent')) {
            $this->ci->db->query("ALTER TABLE {$table} ADD KEY idx_inv_material_fifo_parent (parent_lot_id)");
        }
    }

    private function ensureIssueLogTableColumns(): void
    {
        $table = 'inv_material_fifo_issue_log';
        if (!$this->ci->db->field_exists('location_scope', $table)) {
            $this->ci->db->query("ALTER TABLE {$table} ADD COLUMN location_scope ENUM('WAREHOUSE','DIVISION') NOT NULL DEFAULT 'WAREHOUSE' AFTER issue_datetime");
        }
        if (!$this->ci->db->field_exists('destination_type', $table)) {
            $this->ci->db->query("ALTER TABLE {$table} ADD COLUMN destination_type ENUM('GUDANG','BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NULL AFTER division_id");
        }
        if (!$this->ci->db->field_exists('target_scope', $table)) {
            $this->ci->db->query("ALTER TABLE {$table} ADD COLUMN target_scope ENUM('WAREHOUSE','DIVISION') NULL AFTER destination_type");
        }
        if (!$this->ci->db->field_exists('target_division_id', $table)) {
            $this->ci->db->query("ALTER TABLE {$table} ADD COLUMN target_division_id BIGINT(20) UNSIGNED NULL AFTER target_scope");
        }
        if (!$this->ci->db->field_exists('target_destination_type', $table)) {
            $this->ci->db->query("ALTER TABLE {$table} ADD COLUMN target_destination_type ENUM('GUDANG','BAR','KITCHEN','BAR_EVENT','KITCHEN_EVENT','OFFICE','OTHER') NULL AFTER target_division_id");
        }
        if (!$this->ci->db->field_exists('buy_uom_id', $table)) {
            $this->ci->db->query("ALTER TABLE {$table} ADD COLUMN buy_uom_id BIGINT(20) UNSIGNED NULL AFTER material_id");
        }
        if (!$this->ci->db->field_exists('profile_key', $table)) {
            $this->ci->db->query("ALTER TABLE {$table} ADD COLUMN profile_key CHAR(64) NULL AFTER content_uom_id");
        }
        if (!$this->ci->db->field_exists('status', $table)) {
            $this->ci->db->query("ALTER TABLE {$table} ADD COLUMN status ENUM('POSTED','VOID') NOT NULL DEFAULT 'POSTED' AFTER notes");
        }
        if (!$this->ci->db->field_exists('voided_at', $table)) {
            $this->ci->db->query("ALTER TABLE {$table} ADD COLUMN voided_at DATETIME NULL AFTER status");
        }
        $this->ci->db->query("ALTER TABLE {$table} MODIFY COLUMN division_id BIGINT(20) UNSIGNED NULL");
        $this->ci->db->query("ALTER TABLE {$table} MODIFY COLUMN item_id BIGINT(20) UNSIGNED NULL");

        if (!$this->hasIndex($table, 'idx_inv_material_fifo_issue_source')) {
            $this->ci->db->query("ALTER TABLE {$table} ADD KEY idx_inv_material_fifo_issue_source (source_table, source_id, source_line_id, status)");
        }
    }

    private function ensureIssueLineTableColumns(): void
    {
        $table = 'inv_material_fifo_issue_line';
        if (!$this->ci->db->field_exists('target_lot_id', $table)) {
            $this->ci->db->query("ALTER TABLE {$table} ADD COLUMN target_lot_id BIGINT(20) UNSIGNED NULL AFTER lot_id");
        }
        if (!$this->ci->db->field_exists('source_balance_before', $table)) {
            $this->ci->db->query("ALTER TABLE {$table} ADD COLUMN source_balance_before DECIMAL(18,4) NULL AFTER total_cost");
        }
        if (!$this->ci->db->field_exists('source_balance_after', $table)) {
            $this->ci->db->query("ALTER TABLE {$table} ADD COLUMN source_balance_after DECIMAL(18,4) NULL AFTER source_balance_before");
        }
        if (!$this->ci->db->field_exists('target_balance_before', $table)) {
            $this->ci->db->query("ALTER TABLE {$table} ADD COLUMN target_balance_before DECIMAL(18,4) NULL AFTER source_balance_after");
        }
        if (!$this->ci->db->field_exists('target_balance_after', $table)) {
            $this->ci->db->query("ALTER TABLE {$table} ADD COLUMN target_balance_after DECIMAL(18,4) NULL AFTER target_balance_before");
        }
        if (!$this->hasIndex($table, 'idx_inv_material_fifo_issue_line_target')) {
            $this->ci->db->query("ALTER TABLE {$table} ADD KEY idx_inv_material_fifo_issue_line_target (target_lot_id)");
        }
    }

    private function ensureReceiptLineColumns(): void
    {
        $table = 'pur_purchase_receipt_line';
        if (!$this->ci->db->table_exists($table)) {
            return;
        }
        if (!$this->ci->db->field_exists('lot_id', $table)) {
            $this->ci->db->query("ALTER TABLE {$table} ADD COLUMN lot_id BIGINT(20) UNSIGNED NULL AFTER profile_key");
        }
        if (!$this->ci->db->field_exists('lot_no', $table)) {
            $this->ci->db->query("ALTER TABLE {$table} ADD COLUMN lot_no VARCHAR(80) NULL AFTER lot_id");
        }
    }

    private function ensureFulfillmentLineColumns(): void
    {
        $table = 'pur_store_request_fulfillment_line';
        if (!$this->ci->db->table_exists($table)) {
            return;
        }
        if (!$this->ci->db->field_exists('fifo_issue_id', $table)) {
            $this->ci->db->query("ALTER TABLE {$table} ADD COLUMN fifo_issue_id BIGINT(20) UNSIGNED NULL AFTER unit_cost_snapshot");
        }
        if (!$this->ci->db->field_exists('fifo_issue_no', $table)) {
            $this->ci->db->query("ALTER TABLE {$table} ADD COLUMN fifo_issue_no VARCHAR(60) NULL AFTER fifo_issue_id");
        }
    }

    private function applyLotMutation(array $identity, float $qtyInDelta, float $qtyOutDelta): array
    {
        $lotId = isset($identity['lot_id']) ? (int)$identity['lot_id'] : 0;
        $existing = $lotId > 0
            ? $this->findLotById($lotId, true)
            : $this->findLotForUpdate($identity);

        if (!$existing) {
            if ($qtyInDelta < -0.0001 || $qtyOutDelta > 0.0001) {
                return ['ok' => false, 'message' => 'Lot FIFO tidak ditemukan untuk mutasi keluar.'];
            }

            $insert = [
                'lot_no' => (string)$identity['lot_no'],
                'location_scope' => strtoupper((string)$identity['location_scope']),
                'receipt_date' => (string)$identity['receipt_date'],
                'expiry_date' => $identity['expiry_date'],
                'division_id' => $this->nullableInt($identity['division_id'] ?? null),
                'destination_type' => $this->nullableString($identity['destination_type'] ?? null),
                'item_id' => $this->nullableInt($identity['item_id'] ?? null),
                'material_id' => $this->nullableInt($identity['material_id'] ?? null),
                'buy_uom_id' => $this->nullableInt($identity['buy_uom_id'] ?? null),
                'content_uom_id' => $this->nullableInt($identity['content_uom_id'] ?? null),
                'profile_key' => $this->nullableString($identity['profile_key'] ?? null),
                'qty_in' => round(max(0, $qtyInDelta), 4),
                'qty_out' => round(max(0, $qtyOutDelta), 4),
                'qty_balance' => round(max(0, $qtyInDelta - $qtyOutDelta), 4),
                'unit_cost' => max(0, round((float)($identity['unit_cost'] ?? 0), 6)),
                'source_table' => $this->nullableString($identity['source_table'] ?? null),
                'source_id' => $this->nullableInt($identity['source_id'] ?? null),
                'source_line_id' => $this->nullableInt($identity['source_line_id'] ?? null),
                'receipt_id' => $this->nullableInt($identity['receipt_id'] ?? null),
                'receipt_line_id' => $this->nullableInt($identity['receipt_line_id'] ?? null),
                'parent_lot_id' => $this->nullableInt($identity['parent_lot_id'] ?? null),
                'status' => round(max(0, $qtyInDelta - $qtyOutDelta), 4) > 0 ? 'OPEN' : 'CLOSED',
            ];
            $this->ci->db->insert('inv_material_fifo_lot', $insert);
            $newId = (int)$this->ci->db->insert_id();
            if ($newId <= 0) {
                return ['ok' => false, 'message' => 'Gagal menyimpan lot FIFO.'];
            }

            return [
                'ok' => true,
                'data' => [
                    'lot_id' => $newId,
                    'lot_no' => (string)$identity['lot_no'],
                    'qty_balance' => round(max(0, $qtyInDelta - $qtyOutDelta), 4),
                    'unit_cost' => max(0, round((float)($identity['unit_cost'] ?? 0), 6)),
                ],
            ];
        }

        $oldIn = round((float)($existing['qty_in'] ?? 0), 4);
        $oldOut = round((float)($existing['qty_out'] ?? 0), 4);
        $oldBalance = round((float)($existing['qty_balance'] ?? 0), 4);
        $newIn = round($oldIn + $qtyInDelta, 4);
        $newOut = round($oldOut + $qtyOutDelta, 4);
        $newBalance = round($oldBalance + $qtyInDelta - $qtyOutDelta, 4);

        if ($newIn < -0.0001 || $newOut < -0.0001 || $newBalance < -0.0001) {
            return ['ok' => false, 'message' => 'Mutasi lot FIFO menyebabkan saldo negatif.'];
        }

        if (abs($newIn) < 0.0001) {
            $newIn = 0.0;
        }
        if (abs($newOut) < 0.0001) {
            $newOut = 0.0;
        }
        if (abs($newBalance) < 0.0001) {
            $newBalance = 0.0;
        }

        $update = [
            'qty_in' => $newIn,
            'qty_out' => $newOut,
            'qty_balance' => $newBalance,
            'unit_cost' => max(0, round((float)($identity['unit_cost'] ?? ($existing['unit_cost'] ?? 0)), 6)),
            'status' => $newBalance > 0 ? 'OPEN' : 'CLOSED',
        ];
        $this->ci->db->where('id', (int)$existing['id'])->update('inv_material_fifo_lot', $update);
        if ($this->ci->db->trans_status() === false) {
            return ['ok' => false, 'message' => 'Gagal update lot FIFO.'];
        }

        return [
            'ok' => true,
            'data' => [
                'lot_id' => (int)$existing['id'],
                'lot_no' => (string)($existing['lot_no'] ?? ''),
                'qty_balance' => $newBalance,
                'unit_cost' => max(0, round((float)($update['unit_cost'] ?? 0), 6)),
            ],
        ];
    }

    private function normalizeLotIdentity(array $payload, bool $allowWarehouseWithoutDivision): array
    {
        $locationScope = strtoupper(trim((string)($payload['location_scope'] ?? 'WAREHOUSE')));
        if (!in_array($locationScope, ['WAREHOUSE', 'DIVISION'], true)) {
            return ['ok' => false, 'message' => 'location_scope FIFO tidak valid.'];
        }

        $divisionId = $this->nullableInt($payload['division_id'] ?? null);
        $destinationType = $this->normalizeDestinationType((string)($payload['destination_type'] ?? ''));
        if ($locationScope === 'WAREHOUSE') {
            if (!$allowWarehouseWithoutDivision) {
                $divisionId = null;
            }
            $destinationType = 'GUDANG';
        } elseif ($divisionId === null || $destinationType === null || $destinationType === 'GUDANG') {
            return ['ok' => false, 'message' => 'Lot FIFO divisi membutuhkan division_id dan destination_type non-gudang.'];
        }

        $itemId = $this->nullableInt($payload['item_id'] ?? null);
        $materialId = $this->nullableInt($payload['material_id'] ?? null);
        if ($materialId === null && $itemId !== null) {
            $materialId = $this->resolveMaterialIdFromItem($itemId);
        }
        $contentUomId = $this->nullableInt($payload['content_uom_id'] ?? null);
        if ($itemId === null && $materialId === null) {
            return ['ok' => false, 'message' => 'Lot FIFO membutuhkan item_id atau material_id.'];
        }
        if ($contentUomId === null) {
            return ['ok' => false, 'message' => 'content_uom_id FIFO wajib diisi.'];
        }

        return [
            'ok' => true,
            'location_scope' => $locationScope,
            'division_id' => $divisionId,
            'destination_type' => $destinationType,
            'item_id' => $itemId,
            'material_id' => $materialId,
            'buy_uom_id' => $this->nullableInt($payload['buy_uom_id'] ?? null),
            'content_uom_id' => $contentUomId,
            'profile_key' => $this->nullableString($payload['profile_key'] ?? null),
            'expiry_date' => $this->normalizeDate((string)($payload['expiry_date'] ?? ($payload['profile_expired_date'] ?? ''))),
        ];
    }

    private function resolveMaterialIdFromItem(int $itemId): ?int
    {
        if ($itemId <= 0 || !$this->ci->db->table_exists('mst_item')) {
            return null;
        }

        $row = $this->ci->db
            ->select('material_id')
            ->from('mst_item')
            ->where('id', $itemId)
            ->limit(1)
            ->get()
            ->row_array();

        $materialId = $this->nullableInt($row['material_id'] ?? null);
        return $materialId !== null && $materialId > 0 ? $materialId : null;
    }

    private function findOpenLots(array $identity): array
    {
        $this->ci->db
            ->from('inv_material_fifo_lot')
            ->where('location_scope', strtoupper((string)$identity['location_scope']))
            ->where('status', 'OPEN')
            ->where('content_uom_id', (int)$identity['content_uom_id'])
            ->where('qty_balance >', 0, false)
            ->order_by('receipt_date', 'ASC')
            ->order_by('id', 'ASC');

        if ($identity['division_id'] === null) {
            $this->ci->db->where('division_id IS NULL', null, false);
        } else {
            $this->ci->db->where('division_id', (int)$identity['division_id']);
        }
        if (($identity['destination_type'] ?? null) === null) {
            $this->ci->db->where('destination_type IS NULL', null, false);
        } else {
            $this->ci->db->where('destination_type', (string)$identity['destination_type']);
        }
        if (($identity['item_id'] ?? null) === null) {
            $this->ci->db->where('item_id IS NULL', null, false);
        } else {
            $this->ci->db->where('item_id', (int)$identity['item_id']);
        }
        if (($identity['material_id'] ?? null) === null) {
            $this->ci->db->where('material_id IS NULL', null, false);
        } else {
            $this->ci->db->where('material_id', (int)$identity['material_id']);
        }
        if (($identity['buy_uom_id'] ?? null) === null) {
            $this->ci->db->where('buy_uom_id IS NULL', null, false);
        } else {
            $this->ci->db->where('buy_uom_id', (int)$identity['buy_uom_id']);
        }
        if (($identity['profile_key'] ?? null) === null) {
            $this->ci->db->where('profile_key IS NULL', null, false);
        } else {
            $this->ci->db->where('profile_key', (string)$identity['profile_key']);
        }

        return $this->ci->db->get()->result_array();
    }

    private function findIssueSourceLots(array $identity, array $options = []): array
    {
        $allowAnyItemId = !empty($options['allow_any_item_id']);
        $allowAnyBuyUom = !empty($options['allow_any_buy_uom']);
        $allowAnyContentUom = !empty($options['allow_any_content_uom']);
        $allowAnyProfileKey = !empty($options['allow_any_profile_key']);

        $this->ci->db
            ->from('inv_material_fifo_lot')
            ->where('location_scope', strtoupper((string)$identity['location_scope']))
            ->where('status', 'OPEN')
            ->where('qty_balance >', 0, false)
            ->order_by('receipt_date', 'ASC')
            ->order_by('id', 'ASC');
        if (!$allowAnyContentUom) {
            $this->ci->db->where('content_uom_id', (int)$identity['content_uom_id']);
        }

        if ($identity['division_id'] === null) {
            $this->ci->db->where('division_id IS NULL', null, false);
        } else {
            $this->ci->db->where('division_id', (int)$identity['division_id']);
        }
        if (($identity['destination_type'] ?? null) === null) {
            $this->ci->db->where('destination_type IS NULL', null, false);
        } else {
            $this->ci->db->where('destination_type', (string)$identity['destination_type']);
        }
        if (!$allowAnyItemId) {
            if (($identity['item_id'] ?? null) === null) {
                $this->ci->db->where('item_id IS NULL', null, false);
            } else {
                $this->ci->db->where('item_id', (int)$identity['item_id']);
            }
        }
        if (($identity['material_id'] ?? null) === null) {
            $this->ci->db->where('material_id IS NULL', null, false);
        } else {
            $this->ci->db->where('material_id', (int)$identity['material_id']);
        }
        if (!$allowAnyBuyUom) {
            if (($identity['buy_uom_id'] ?? null) === null) {
                $this->ci->db->where('buy_uom_id IS NULL', null, false);
            } else {
                $this->ci->db->where('buy_uom_id', (int)$identity['buy_uom_id']);
            }
        }
        if (!$allowAnyProfileKey) {
            if (($identity['profile_key'] ?? null) === null) {
                $this->ci->db->where('profile_key IS NULL', null, false);
            } else {
                $this->ci->db->where('profile_key', (string)$identity['profile_key']);
            }
        }

        return $this->ci->db->get()->result_array();
    }

    private function synchronizeWarehouseLotsFromAggregate(array $identity): array
    {
        if (!$this->ci->db->table_exists('inv_warehouse_monthly_stock')) {
            return ['ok' => true, 'data' => ['bootstrapped' => false]];
        }

        $monthlyStock = $this->fetchWarehouseAggregateMonthlyStock($identity);
        if (!$monthlyStock) {
            return ['ok' => true, 'data' => ['bootstrapped' => false]];
        }

        $aggregateQty = round((float)($monthlyStock['closing_qty_content'] ?? 0), 4);
        if ($aggregateQty <= 0) {
            return ['ok' => true, 'data' => ['bootstrapped' => false]];
        }

        $lotRows = $this->findOpenLots([
            'location_scope' => 'WAREHOUSE',
            'division_id' => null,
            'destination_type' => 'GUDANG',
            'item_id' => $identity['item_id'],
            'material_id' => $identity['material_id'],
            'buy_uom_id' => $identity['buy_uom_id'],
            'content_uom_id' => $identity['content_uom_id'],
            'profile_key' => $identity['profile_key'],
        ]);

        $lotQty = 0.0;
        foreach ($lotRows as $lotRow) {
            $lotQty = round($lotQty + (float)($lotRow['qty_balance'] ?? 0), 4);
        }

        $missingQty = round($aggregateQty - $lotQty, 4);
        if ($missingQty <= 0.0001) {
            return ['ok' => true, 'data' => ['bootstrapped' => false]];
        }

        $receiptDate = $this->resolveWarehouseBootstrapDate($identity);
        $lotNo = $this->generateLotNo($receiptDate, [
            'BOOT',
            $monthlyStock['id'] ?? 0,
            $identity['item_id'] ?? 0,
            $identity['material_id'] ?? 0,
            $identity['profile_key'] ?? '',
            $identity['buy_uom_id'] ?? 0,
            $identity['content_uom_id'] ?? 0,
        ]);

        $bootstrap = $this->applyLotMutation([
            'location_scope' => 'WAREHOUSE',
            'division_id' => null,
            'destination_type' => 'GUDANG',
            'item_id' => $identity['item_id'],
            'material_id' => $identity['material_id'],
            'buy_uom_id' => $identity['buy_uom_id'],
            'content_uom_id' => $identity['content_uom_id'],
            'profile_key' => $identity['profile_key'],
            'lot_no' => $lotNo,
            'receipt_date' => $receiptDate,
            'expiry_date' => null,
            'unit_cost' => max(0, round((float)($monthlyStock['avg_cost_per_content'] ?? 0), 6)),
            'source_table' => 'inv_warehouse_monthly_stock',
            'source_id' => $this->nullableInt($monthlyStock['id'] ?? null),
            'source_line_id' => null,
            'receipt_id' => null,
            'receipt_line_id' => null,
            'parent_lot_id' => null,
        ], $missingQty, 0.0);
        if (!($bootstrap['ok'] ?? false)) {
            return $bootstrap;
        }

        return ['ok' => true, 'data' => ['bootstrapped' => true, 'qty_added' => $missingQty]];
    }

    private function fetchWarehouseAggregateMonthlyStock(array $identity): ?array
    {
        $sql = 'SELECT id, closing_qty_content, avg_cost_per_content
            FROM inv_warehouse_monthly_stock
            WHERE item_id <=> ?
              AND buy_uom_id <=> ?
              AND content_uom_id = ?
              AND profile_key <=> ?
              AND month_key <= ?';
        $params = [
            $this->nullableInt($identity['item_id'] ?? null),
            $this->nullableInt($identity['buy_uom_id'] ?? null),
            (int)$identity['content_uom_id'],
            $this->nullableString($identity['profile_key'] ?? null),
            date('Y-m-01'),
        ];

        if ($this->ci->db->field_exists('material_id', 'inv_warehouse_monthly_stock')) {
            $sql .= ' AND material_id <=> ?';
            $params[] = $this->nullableInt($identity['material_id'] ?? null);
        }
        $sql .= ' ORDER BY month_key DESC, id DESC LIMIT 1';

        $row = $this->ci->db->query($sql, $params)->row_array();

        return $row ?: null;
    }

    private function resolveWarehouseBootstrapDate(array $identity): string
    {
        if (!$this->ci->db->table_exists('inv_stock_movement_log')) {
            return date('Y-m-d');
        }

        $sql = 'SELECT MIN(movement_date) AS first_movement_date
             FROM inv_stock_movement_log
             WHERE movement_scope = ?
               AND item_id <=> ?
               AND buy_uom_id <=> ?
               AND content_uom_id = ?
               AND profile_key <=> ?';
        $params = [
            'WAREHOUSE',
            $this->nullableInt($identity['item_id'] ?? null),
            $this->nullableInt($identity['buy_uom_id'] ?? null),
            (int)$identity['content_uom_id'],
            $this->nullableString($identity['profile_key'] ?? null),
        ];
        if ($this->ci->db->field_exists('material_id', 'inv_stock_movement_log')) {
            $sql .= ' AND material_id <=> ?';
            $params[] = $this->nullableInt($identity['material_id'] ?? null);
        }

        $row = $this->ci->db->query($sql, $params)->row_array();

        $date = $this->normalizeDate((string)($row['first_movement_date'] ?? ''));
        return $date ?? date('Y-m-d');
    }

    private function findLotForUpdate(array $identity): ?array
    {
        $query = 'SELECT * FROM inv_material_fifo_lot
            WHERE location_scope = ?
              AND division_id <=> ?
              AND destination_type <=> ?
              AND item_id <=> ?
              AND material_id <=> ?
              AND buy_uom_id <=> ?
              AND content_uom_id = ?
              AND profile_key <=> ?
              AND lot_no = ?
            LIMIT 1 FOR UPDATE';

        $row = $this->ci->db->query($query, [
            strtoupper((string)$identity['location_scope']),
            $this->nullableInt($identity['division_id'] ?? null),
            $this->nullableString($identity['destination_type'] ?? null),
            $this->nullableInt($identity['item_id'] ?? null),
            $this->nullableInt($identity['material_id'] ?? null),
            $this->nullableInt($identity['buy_uom_id'] ?? null),
            (int)$identity['content_uom_id'],
            $this->nullableString($identity['profile_key'] ?? null),
            (string)$identity['lot_no'],
        ])->row_array();

        return $row ?: null;
    }

    private function findLotsBySource(string $sourceTable, int $sourceId, ?int $sourceLineId = null): array
    {
        $this->ci->db->from('inv_material_fifo_lot')
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->order_by('id', 'ASC');
        if ($sourceLineId === null) {
            $this->ci->db->where('source_line_id IS NULL', null, false);
        } else {
            $this->ci->db->where('source_line_id', $sourceLineId);
        }
        return $this->ci->db->get()->result_array();
    }

    private function findLotById(int $lotId, bool $forUpdate = false): ?array
    {
        if ($lotId <= 0) {
            return null;
        }
        $sql = 'SELECT * FROM inv_material_fifo_lot WHERE id = ? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
        $row = $this->ci->db->query($sql, [$lotId])->row_array();
        return $row ?: null;
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $rows = $this->ci->db->query('SHOW INDEX FROM ' . $table . ' WHERE Key_name = ?', [$indexName])->result_array();
        return !empty($rows);
    }

    private function buildMonthlyIdentityKeyFromLotIdentity(array $identity): string
    {
        $profileKey = $this->nullableString($identity['profile_key'] ?? null);
        if ($profileKey !== null) {
            return $profileKey;
        }

        return hash('sha256', implode('|', [
            (string)((int)($identity['item_id'] ?? 0)),
            (string)((int)($identity['material_id'] ?? 0)),
            (string)((int)($identity['buy_uom_id'] ?? 0)),
            (string)((int)($identity['content_uom_id'] ?? 0)),
        ]));
    }

    private function generateLotNo(string $date, array $seedParts): string
    {
        $token = date('Ymd', strtotime($date));
        $hash = strtoupper(substr(hash('sha1', implode('|', $seedParts)), 0, 12));
        return substr('LOT' . $token . '-' . $hash, 0, 80);
    }

    private function generateIssueNo(string $issueDate): string
    {
        $prefix = 'FIF' . date('Ymd', strtotime($issueDate));
        do {
            $seq = str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $no = $prefix . $seq;
            $exists = (int)$this->ci->db->where('issue_no', $no)->count_all_results('inv_material_fifo_issue_log');
        } while ($exists > 0);

        return $no;
    }

    private function normalizeDestinationType(string $destination): ?string
    {
        $destination = strtoupper(trim($destination));
        $allowed = ['GUDANG', 'BAR', 'KITCHEN', 'BAR_EVENT', 'KITCHEN_EVENT', 'OFFICE', 'OTHER'];
        return in_array($destination, $allowed, true) ? $destination : null;
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d', $ts);
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $parsed = (int)$value;
        return $parsed > 0 ? $parsed : null;
    }

    private function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string)$value);
        return $trimmed === '' ? null : $trimmed;
    }
}
