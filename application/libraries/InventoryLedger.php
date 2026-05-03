<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * InventoryLedger
 *
 * Library terpusat untuk memastikan setiap transaksi stok menulis konsisten ke:
 * 1) inv_stock_movement_log (source of truth)
 * 2) live balance (warehouse/division)
 * 3) daily rollup (warehouse/division)
 */
class InventoryLedger
{
    /** @var CI_Controller */
    protected $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->database();
    }

    public function post(array $payload): array
    {
        $scope = strtoupper(trim((string)($payload['movement_scope'] ?? '')));
        if (!in_array($scope, ['WAREHOUSE', 'DIVISION'], true)) {
            return [
                'ok' => false,
                'message' => 'movement_scope wajib WAREHOUSE atau DIVISION.',
            ];
        }

        if (!$this->ci->db->table_exists('inv_stock_movement_log')) {
            return [
                'ok' => false,
                'message' => 'Tabel inv_stock_movement_log belum tersedia.',
            ];
        }

        $movementDate = $this->normalizeDate((string)($payload['movement_date'] ?? date('Y-m-d')));
        if ($movementDate === null) {
            return [
                'ok' => false,
                'message' => 'movement_date tidak valid.',
            ];
        }

        $movementType = strtoupper(trim((string)($payload['movement_type'] ?? '')));
        $allowedTypes = ['PURCHASE_IN', 'TRANSFER_IN', 'TRANSFER_OUT', 'USAGE_OUT', 'DISCARDED_OUT', 'SPOIL_OUT', 'WASTE_OUT', 'ADJUSTMENT'];
        if (!in_array($movementType, $allowedTypes, true)) {
            return [
                'ok' => false,
                'message' => 'movement_type tidak valid.',
            ];
        }

        $divisionId = $this->nullableInt($payload['division_id'] ?? null);
        if ($scope === 'DIVISION' && $divisionId === null) {
            return [
                'ok' => false,
                'message' => 'division_id wajib diisi untuk movement_scope DIVISION.',
            ];
        }

        $itemId = $this->nullableInt($payload['item_id'] ?? null);
        $materialId = $this->nullableInt($payload['material_id'] ?? null);
        if ($itemId === null && $materialId === null) {
            return [
                'ok' => false,
                'message' => 'item_id atau material_id wajib diisi.',
            ];
        }

        $buyUomId = $this->nullableInt($payload['buy_uom_id'] ?? null);
        $contentUomId = $this->nullableInt($payload['content_uom_id'] ?? null);
        if ($contentUomId === null) {
            return [
                'ok' => false,
                'message' => 'content_uom_id wajib diisi.',
            ];
        }

        $qtyBuyDelta = round((float)($payload['qty_buy_delta'] ?? 0), 4);
        $qtyContentDelta = round((float)($payload['qty_content_delta'] ?? 0), 4);
        if ($qtyBuyDelta == 0.0 && $qtyContentDelta == 0.0) {
            return [
                'ok' => false,
                'message' => 'qty delta tidak boleh keduanya nol.',
            ];
        }

        $stockDomain = strtoupper(trim((string)($payload['stock_domain'] ?? ($itemId !== null ? 'ITEM' : 'MATERIAL'))));
        if (!in_array($stockDomain, ['ITEM', 'MATERIAL'], true)) {
            $stockDomain = $itemId !== null ? 'ITEM' : 'MATERIAL';
        }

        $manageTransaction = array_key_exists('manage_transaction', $payload) ? (bool)$payload['manage_transaction'] : true;
        if ($manageTransaction) {
            $this->ci->db->trans_begin();
        }

        $balanceResult = ($scope === 'WAREHOUSE')
            ? $this->upsertWarehouseBalance($payload, $qtyBuyDelta, $qtyContentDelta)
            : $this->upsertDivisionBalance($payload, $divisionId, $qtyBuyDelta, $qtyContentDelta);

        if (!($balanceResult['ok'] ?? false)) {
            if ($manageTransaction) {
                $this->ci->db->trans_rollback();
            }
            return $balanceResult;
        }

        $movementNo = trim((string)($payload['movement_no'] ?? ''));
        if ($movementNo === '') {
            $movementNo = $this->generateMovementNo($movementDate);
        }

        $movementData = [
            'movement_no' => $movementNo,
            'movement_date' => $movementDate,
            'movement_scope' => $scope,
            'division_id' => $scope === 'DIVISION' ? $divisionId : null,
            'movement_type' => $movementType,
            'ref_table' => $this->nullableString($payload['ref_table'] ?? null),
            'ref_id' => $this->nullableInt($payload['ref_id'] ?? null),
            'receipt_id' => $this->nullableInt($payload['receipt_id'] ?? null),
            'receipt_line_id' => $this->nullableInt($payload['receipt_line_id'] ?? null),
            'item_id' => $itemId,
            'material_id' => $materialId,
            'buy_uom_id' => $buyUomId,
            'content_uom_id' => $contentUomId,
            'qty_buy_delta' => $qtyBuyDelta,
            'qty_content_delta' => $qtyContentDelta,
            'qty_buy_after' => $balanceResult['qty_buy_after'],
            'qty_content_after' => $balanceResult['qty_content_after'],
            'profile_key' => $this->nullableString($payload['profile_key'] ?? null),
            'profile_name' => $this->nullableString($payload['profile_name'] ?? null),
            'profile_brand' => $this->nullableString($payload['profile_brand'] ?? null),
            'profile_description' => $this->nullableString($payload['profile_description'] ?? null),
            'profile_content_per_buy' => $this->nullableDecimal($payload['profile_content_per_buy'] ?? null, 6),
            'profile_buy_uom_code' => $this->nullableString($payload['profile_buy_uom_code'] ?? null),
            'profile_content_uom_code' => $this->nullableString($payload['profile_content_uom_code'] ?? null),
            'unit_cost' => $balanceResult['unit_cost'],
            'notes' => $this->nullableString($payload['notes'] ?? null),
            'created_by' => $this->nullableInt($payload['created_by'] ?? null),
        ];

        $this->ci->db->insert('inv_stock_movement_log', $movementData);

        if ($this->ci->db->affected_rows() <= 0) {
            if ($manageTransaction) {
                $this->ci->db->trans_rollback();
            }
            return [
                'ok' => false,
                'message' => 'Gagal menulis movement log.',
            ];
        }

        $dailyResult = $this->upsertDailyRollup([
            'movement_scope' => $scope,
            'division_id' => $divisionId,
            'movement_date' => $movementDate,
            'movement_type' => $movementType,
            'stock_domain' => $stockDomain,
            'item_id' => $itemId,
            'material_id' => $materialId,
            'buy_uom_id' => $buyUomId,
            'content_uom_id' => $contentUomId,
            'profile_key' => $this->nullableString($payload['profile_key'] ?? null),
            'profile_name' => $this->nullableString($payload['profile_name'] ?? null),
            'profile_brand' => $this->nullableString($payload['profile_brand'] ?? null),
            'profile_description' => $this->nullableString($payload['profile_description'] ?? null),
            'profile_content_per_buy' => $this->nullableDecimal($payload['profile_content_per_buy'] ?? null, 6),
            'profile_buy_uom_code' => $this->nullableString($payload['profile_buy_uom_code'] ?? null),
            'profile_content_uom_code' => $this->nullableString($payload['profile_content_uom_code'] ?? null),
            'qty_buy_delta' => $qtyBuyDelta,
            'qty_content_delta' => $qtyContentDelta,
            'qty_buy_after' => $balanceResult['qty_buy_after'],
            'qty_content_after' => $balanceResult['qty_content_after'],
            'avg_cost_per_content' => $balanceResult['avg_cost_per_content'],
        ]);

        if (!($dailyResult['ok'] ?? false)) {
            if ($manageTransaction) {
                $this->ci->db->trans_rollback();
            }
            return $dailyResult;
        }

        if ($manageTransaction) {
            if ($this->ci->db->trans_status() === false) {
                $this->ci->db->trans_rollback();
                return [
                    'ok' => false,
                    'message' => 'Gagal posting inventory ledger.',
                ];
            }
            $this->ci->db->trans_commit();
        }

        return [
            'ok' => true,
            'message' => 'Inventory ledger berhasil diposting.',
            'data' => [
                'movement_no' => $movementNo,
                'qty_buy_after' => $balanceResult['qty_buy_after'],
                'qty_content_after' => $balanceResult['qty_content_after'],
                'avg_cost_per_content' => $balanceResult['avg_cost_per_content'],
            ],
        ];
    }

    private function upsertWarehouseBalance(array $payload, float $qtyBuyDelta, float $qtyContentDelta): array
    {
        if (!$this->ci->db->table_exists('inv_warehouse_stock_balance')) {
            return [
                'ok' => false,
                'message' => 'Tabel inv_warehouse_stock_balance belum tersedia.',
            ];
        }

        $itemId = $this->nullableInt($payload['item_id'] ?? null);
        $buyUomId = $this->nullableInt($payload['buy_uom_id'] ?? null);
        $contentUomId = $this->nullableInt($payload['content_uom_id'] ?? null);
        $profileKey = $this->nullableString($payload['profile_key'] ?? null);
        if ($itemId === null || $buyUomId === null || $contentUomId === null) {
            return [
                'ok' => false,
                'message' => 'Warehouse balance membutuhkan item_id, buy_uom_id, content_uom_id.',
            ];
        }

        $row = $this->ci->db->query(
            'SELECT * FROM inv_warehouse_stock_balance WHERE item_id = ? AND buy_uom_id = ? AND content_uom_id = ? AND profile_key <=> ? LIMIT 1 FOR UPDATE',
            [$itemId, $buyUomId, $contentUomId, $profileKey]
        )->row_array();

        return $this->applyBalanceMutation('inv_warehouse_stock_balance', $row, $payload, $qtyBuyDelta, $qtyContentDelta, [
            'item_id' => $itemId,
            'buy_uom_id' => $buyUomId,
            'content_uom_id' => $contentUomId,
            'profile_key' => $profileKey,
            'last_receipt_line_id' => $this->nullableInt($payload['receipt_line_id'] ?? null),
        ]);
    }

    private function upsertDivisionBalance(array $payload, ?int $divisionId, float $qtyBuyDelta, float $qtyContentDelta): array
    {
        if (!$this->ci->db->table_exists('inv_division_stock_balance')) {
            return [
                'ok' => false,
                'message' => 'Tabel inv_division_stock_balance belum tersedia.',
            ];
        }

        $itemId = $this->nullableInt($payload['item_id'] ?? null);
        $materialId = $this->nullableInt($payload['material_id'] ?? null);
        $buyUomId = $this->nullableInt($payload['buy_uom_id'] ?? null);
        $contentUomId = $this->nullableInt($payload['content_uom_id'] ?? null);
        $profileKey = $this->nullableString($payload['profile_key'] ?? null);
        if ($divisionId === null || $contentUomId === null) {
            return [
                'ok' => false,
                'message' => 'Division balance membutuhkan division_id dan content_uom_id.',
            ];
        }

        $row = $this->ci->db->query(
            'SELECT * FROM inv_division_stock_balance WHERE division_id = ? AND item_id <=> ? AND material_id <=> ? AND buy_uom_id <=> ? AND content_uom_id = ? AND profile_key <=> ? LIMIT 1 FOR UPDATE',
            [$divisionId, $itemId, $materialId, $buyUomId, $contentUomId, $profileKey]
        )->row_array();

        return $this->applyBalanceMutation('inv_division_stock_balance', $row, $payload, $qtyBuyDelta, $qtyContentDelta, [
            'division_id' => $divisionId,
            'item_id' => $itemId,
            'material_id' => $materialId,
            'buy_uom_id' => $buyUomId,
            'content_uom_id' => $contentUomId,
            'profile_key' => $profileKey,
            'last_receipt_line_id' => $this->nullableInt($payload['receipt_line_id'] ?? null),
        ]);
    }

    private function applyBalanceMutation(string $table, ?array $existing, array $payload, float $qtyBuyDelta, float $qtyContentDelta, array $keyFields): array
    {
        $oldQtyBuy = round((float)($existing['qty_buy_balance'] ?? 0), 4);
        $oldQtyContent = round((float)($existing['qty_content_balance'] ?? 0), 4);
        $oldAvg = round((float)($existing['avg_cost_per_content'] ?? 0), 6);

        $qtyBuyAfter = round($oldQtyBuy + $qtyBuyDelta, 4);
        $qtyContentAfter = round($oldQtyContent + $qtyContentDelta, 4);
        if ($qtyBuyAfter < 0 || $qtyContentAfter < 0) {
            return [
                'ok' => false,
                'message' => 'Mutasi menyebabkan saldo negatif.',
            ];
        }

        $unitCost = max(0, round((float)($payload['unit_cost'] ?? 0), 6));
        $avgAfter = $oldAvg;
        if ($qtyContentAfter <= 0) {
            $avgAfter = 0;
        } elseif ($qtyContentDelta > 0) {
            $oldValue = $oldQtyContent * $oldAvg;
            $inValue = $qtyContentDelta * $unitCost;
            $avgAfter = round(($oldValue + $inValue) / max(0.000001, $qtyContentAfter), 6);
        }

        $updateData = [
            'qty_buy_balance' => $qtyBuyAfter,
            'qty_content_balance' => $qtyContentAfter,
            'avg_cost_per_content' => $avgAfter,
            'profile_name' => $this->nullableString($payload['profile_name'] ?? ($existing['profile_name'] ?? null)),
            'profile_brand' => $this->nullableString($payload['profile_brand'] ?? ($existing['profile_brand'] ?? null)),
            'profile_description' => $this->nullableString($payload['profile_description'] ?? ($existing['profile_description'] ?? null)),
            'profile_content_per_buy' => $this->nullableDecimal($payload['profile_content_per_buy'] ?? ($existing['profile_content_per_buy'] ?? null), 6),
            'profile_buy_uom_code' => $this->nullableString($payload['profile_buy_uom_code'] ?? ($existing['profile_buy_uom_code'] ?? null)),
            'profile_content_uom_code' => $this->nullableString($payload['profile_content_uom_code'] ?? ($existing['profile_content_uom_code'] ?? null)),
            'last_receipt_line_id' => $this->nullableInt($payload['receipt_line_id'] ?? ($existing['last_receipt_line_id'] ?? null)),
            'notes' => $this->nullableString($payload['notes'] ?? ($existing['notes'] ?? null)),
        ];

        if ($existing) {
            $this->ci->db->where('id', (int)$existing['id'])->update($table, $updateData);
        } else {
            $insertData = array_merge($keyFields, $updateData);
            $this->ci->db->insert($table, $insertData);
        }

        if ($this->ci->db->trans_status() === false) {
            return [
                'ok' => false,
                'message' => 'Gagal update live balance.',
            ];
        }

        return [
            'ok' => true,
            'qty_buy_after' => $qtyBuyAfter,
            'qty_content_after' => $qtyContentAfter,
            'avg_cost_per_content' => $avgAfter,
            'unit_cost' => $unitCost,
        ];
    }

    private function upsertDailyRollup(array $ctx): array
    {
        $scope = (string)$ctx['movement_scope'];
        $table = $scope === 'WAREHOUSE' ? 'inv_warehouse_daily_rollup' : 'inv_division_daily_rollup';
        if (!$this->ci->db->table_exists($table)) {
            return ['ok' => true, 'message' => 'Daily rollup belum tersedia, dilewati.'];
        }

        $movementDate = (string)$ctx['movement_date'];
        $monthKey = date('Y-m-01', strtotime($movementDate));
        $movementType = (string)$ctx['movement_type'];

        $keys = [
            'movement_date' => $movementDate,
            'stock_domain' => (string)$ctx['stock_domain'],
            'item_id' => $this->nullableInt($ctx['item_id'] ?? null),
            'material_id' => $this->nullableInt($ctx['material_id'] ?? null),
            'buy_uom_id' => $this->nullableInt($ctx['buy_uom_id'] ?? null),
            'content_uom_id' => $this->nullableInt($ctx['content_uom_id'] ?? null),
            'profile_key' => $this->nullableString($ctx['profile_key'] ?? null),
        ];

        if ($scope === 'DIVISION') {
            $keys['division_id'] = $this->nullableInt($ctx['division_id'] ?? null);
        }

        $existing = $this->ci->db->from($table);
        foreach ($keys as $k => $v) {
            $this->whereNullable($existing, $k, $v);
        }
        $existing = $existing->limit(1)->get()->row_array();

        $qtyBuyDelta = (float)($ctx['qty_buy_delta'] ?? 0);
        $qtyContentDelta = (float)($ctx['qty_content_delta'] ?? 0);

        $deltaMap = [
            'in_qty_buy' => 0.0,
            'in_qty_content' => 0.0,
            'out_qty_buy' => 0.0,
            'out_qty_content' => 0.0,
            'discarded_qty_buy' => 0.0,
            'discarded_qty_content' => 0.0,
            'spoil_qty_buy' => 0.0,
            'spoil_qty_content' => 0.0,
            'waste_qty_buy' => 0.0,
            'waste_qty_content' => 0.0,
            'adjustment_qty_buy' => 0.0,
            'adjustment_qty_content' => 0.0,
        ];

        if (in_array($movementType, ['PURCHASE_IN', 'TRANSFER_IN'], true)) {
            $deltaMap['in_qty_buy'] = max(0, $qtyBuyDelta);
            $deltaMap['in_qty_content'] = max(0, $qtyContentDelta);
        } elseif (in_array($movementType, ['TRANSFER_OUT', 'USAGE_OUT'], true)) {
            $deltaMap['out_qty_buy'] = abs(min(0, $qtyBuyDelta));
            $deltaMap['out_qty_content'] = abs(min(0, $qtyContentDelta));
        } elseif ($movementType === 'DISCARDED_OUT') {
            $deltaMap['discarded_qty_buy'] = abs(min(0, $qtyBuyDelta));
            $deltaMap['discarded_qty_content'] = abs(min(0, $qtyContentDelta));
        } elseif ($movementType === 'SPOIL_OUT') {
            $deltaMap['spoil_qty_buy'] = abs(min(0, $qtyBuyDelta));
            $deltaMap['spoil_qty_content'] = abs(min(0, $qtyContentDelta));
        } elseif ($movementType === 'WASTE_OUT') {
            $deltaMap['waste_qty_buy'] = abs(min(0, $qtyBuyDelta));
            $deltaMap['waste_qty_content'] = abs(min(0, $qtyContentDelta));
        } else {
            $deltaMap['adjustment_qty_buy'] = $qtyBuyDelta;
            $deltaMap['adjustment_qty_content'] = $qtyContentDelta;
        }

        if ($existing) {
            $update = [
                'in_qty_buy' => round(((float)$existing['in_qty_buy']) + $deltaMap['in_qty_buy'], 4),
                'in_qty_content' => round(((float)$existing['in_qty_content']) + $deltaMap['in_qty_content'], 4),
                'out_qty_buy' => round(((float)$existing['out_qty_buy']) + $deltaMap['out_qty_buy'], 4),
                'out_qty_content' => round(((float)$existing['out_qty_content']) + $deltaMap['out_qty_content'], 4),
                'discarded_qty_buy' => round(((float)$existing['discarded_qty_buy']) + $deltaMap['discarded_qty_buy'], 4),
                'discarded_qty_content' => round(((float)$existing['discarded_qty_content']) + $deltaMap['discarded_qty_content'], 4),
                'spoil_qty_buy' => round(((float)$existing['spoil_qty_buy']) + $deltaMap['spoil_qty_buy'], 4),
                'spoil_qty_content' => round(((float)$existing['spoil_qty_content']) + $deltaMap['spoil_qty_content'], 4),
                'waste_qty_buy' => round(((float)$existing['waste_qty_buy']) + $deltaMap['waste_qty_buy'], 4),
                'waste_qty_content' => round(((float)$existing['waste_qty_content']) + $deltaMap['waste_qty_content'], 4),
                'adjustment_qty_buy' => round(((float)$existing['adjustment_qty_buy']) + $deltaMap['adjustment_qty_buy'], 4),
                'adjustment_qty_content' => round(((float)$existing['adjustment_qty_content']) + $deltaMap['adjustment_qty_content'], 4),
                'closing_qty_buy' => round((float)$ctx['qty_buy_after'], 4),
                'closing_qty_content' => round((float)$ctx['qty_content_after'], 4),
                'avg_cost_per_content' => round((float)$ctx['avg_cost_per_content'], 6),
                'total_value' => round(((float)$ctx['qty_content_after']) * ((float)$ctx['avg_cost_per_content']), 2),
                'mutation_count' => ((int)$existing['mutation_count']) + 1,
                'last_movement_at' => date('Y-m-d H:i:s'),
            ];
            $this->ci->db->where('id', (int)$existing['id'])->update($table, $update);
            return ['ok' => true];
        }

        $openingBuy = round(((float)$ctx['qty_buy_after']) - $qtyBuyDelta, 4);
        $openingContent = round(((float)$ctx['qty_content_after']) - $qtyContentDelta, 4);

        $insert = [
            'month_key' => $monthKey,
            'movement_date' => $movementDate,
            'stock_domain' => (string)$ctx['stock_domain'],
            'item_id' => $this->nullableInt($ctx['item_id'] ?? null),
            'material_id' => $this->nullableInt($ctx['material_id'] ?? null),
            'buy_uom_id' => $this->nullableInt($ctx['buy_uom_id'] ?? null),
            'content_uom_id' => $this->nullableInt($ctx['content_uom_id'] ?? null),
            'profile_key' => $this->nullableString($ctx['profile_key'] ?? null),
            'profile_name' => $this->nullableString($ctx['profile_name'] ?? null),
            'profile_brand' => $this->nullableString($ctx['profile_brand'] ?? null),
            'profile_description' => $this->nullableString($ctx['profile_description'] ?? null),
            'profile_content_per_buy' => $this->nullableDecimal($ctx['profile_content_per_buy'] ?? null, 6),
            'profile_buy_uom_code' => $this->nullableString($ctx['profile_buy_uom_code'] ?? null),
            'profile_content_uom_code' => $this->nullableString($ctx['profile_content_uom_code'] ?? null),
            'opening_qty_buy' => $openingBuy,
            'opening_qty_content' => $openingContent,
            'in_qty_buy' => $deltaMap['in_qty_buy'],
            'in_qty_content' => $deltaMap['in_qty_content'],
            'out_qty_buy' => $deltaMap['out_qty_buy'],
            'out_qty_content' => $deltaMap['out_qty_content'],
            'discarded_qty_buy' => $deltaMap['discarded_qty_buy'],
            'discarded_qty_content' => $deltaMap['discarded_qty_content'],
            'spoil_qty_buy' => $deltaMap['spoil_qty_buy'],
            'spoil_qty_content' => $deltaMap['spoil_qty_content'],
            'waste_qty_buy' => $deltaMap['waste_qty_buy'],
            'waste_qty_content' => $deltaMap['waste_qty_content'],
            'adjustment_qty_buy' => $deltaMap['adjustment_qty_buy'],
            'adjustment_qty_content' => $deltaMap['adjustment_qty_content'],
            'closing_qty_buy' => round((float)$ctx['qty_buy_after'], 4),
            'closing_qty_content' => round((float)$ctx['qty_content_after'], 4),
            'avg_cost_per_content' => round((float)$ctx['avg_cost_per_content'], 6),
            'total_value' => round(((float)$ctx['qty_content_after']) * ((float)$ctx['avg_cost_per_content']), 2),
            'mutation_count' => 1,
            'last_movement_at' => date('Y-m-d H:i:s'),
        ];
        if ($scope === 'DIVISION') {
            $insert['division_id'] = $this->nullableInt($ctx['division_id'] ?? null);
        }

        $this->ci->db->insert($table, $insert);
        return ['ok' => true];
    }

    private function generateMovementNo(string $movementDate): string
    {
        $prefix = 'MV' . date('Ymd', strtotime($movementDate));
        $row = $this->ci->db
            ->select('movement_no')
            ->from('inv_stock_movement_log')
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
        } while ($this->ci->db->where('movement_no', $no)->count_all_results('inv_stock_movement_log') > 0);

        return $no;
    }

    private function normalizeDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $ts = strtotime($raw);
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
        $v = (int)$value;
        return $v > 0 ? $v : null;
    }

    private function nullableString($value): ?string
    {
        $v = trim((string)$value);
        return $v === '' ? null : $v;
    }

    private function nullableDecimal($value, int $precision): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return round((float)$value, $precision);
    }

    private function whereNullable(CI_DB_query_builder $qb, string $column, $value): void
    {
        if ($value === null) {
            $qb->where($column . ' IS NULL', null, false);
        } else {
            $qb->where($column, $value);
        }
    }
}
