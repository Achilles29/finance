<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ComponentLotManager
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

    public function registerProductionInboundLot(array $payload): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        $locationType = strtoupper(trim((string)($payload['location_type'] ?? '')));
        $divisionId = $this->nullableInt($payload['division_id'] ?? null);
        $componentId = (int)($payload['component_id'] ?? 0);
        $uomId = (int)($payload['uom_id'] ?? 0);
        $qtyIn = round((float)($payload['qty_in'] ?? 0), 4);
        $unitCost = max(0, round((float)($payload['unit_cost'] ?? 0), 6));
        $receiptDate = $this->normalizeDate((string)($payload['receipt_date'] ?? ($payload['movement_date'] ?? '')));
        $lotNo = trim((string)($payload['lot_no'] ?? ''));

        if (!$this->validLocation($locationType) || $componentId <= 0 || $uomId <= 0) {
            return ['ok' => false, 'message' => 'Identitas lot component tidak valid.'];
        }
        if ($qtyIn <= 0) {
            return ['ok' => false, 'message' => 'qty_in lot component wajib lebih besar dari nol.'];
        }
        if ($receiptDate === null) {
            return ['ok' => false, 'message' => 'receipt_date lot component tidak valid.'];
        }
        if ($lotNo === '') {
            $lotNo = $this->generateLotNo($receiptDate, $componentId, (int)($payload['source_id'] ?? 0));
        }

        return $this->applyLotMutation([
            'location_type' => $locationType,
            'division_id' => $divisionId,
            'component_id' => $componentId,
            'uom_id' => $uomId,
            'lot_no' => $lotNo,
            'receipt_date' => $receiptDate,
            'expiry_date' => $this->normalizeDate((string)($payload['expiry_date'] ?? '')),
            'unit_cost' => $unitCost,
            'source_module' => $this->nullableString($payload['source_module'] ?? null),
            'source_table' => $this->nullableString($payload['source_table'] ?? null),
            'source_id' => $this->nullableInt($payload['source_id'] ?? null),
            'source_line_id' => $this->nullableInt($payload['source_line_id'] ?? null),
            'parent_lot_id' => $this->nullableInt($payload['parent_lot_id'] ?? null),
            'lot_id' => $this->nullableInt($payload['lot_id'] ?? null),
        ], $qtyIn, 0.0);
    }

    public function consumeUsage(array $payload): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        $locationType = strtoupper(trim((string)($payload['location_type'] ?? '')));
        $divisionId = $this->nullableInt($payload['division_id'] ?? null);
        $componentId = (int)($payload['component_id'] ?? 0);
        $uomId = (int)($payload['uom_id'] ?? 0);
        $selectedLotId = $this->nullableInt($payload['lot_id'] ?? null);
        $issueDate = $this->normalizeDate((string)($payload['issue_date'] ?? ($payload['movement_date'] ?? '')));
        $qtyNeed = round((float)($payload['qty_out'] ?? ($payload['qty_content_out'] ?? 0)), 4);

        if (!$this->validLocation($locationType) || $componentId <= 0 || $uomId <= 0 || $issueDate === null) {
            return ['ok' => false, 'message' => 'Pemakaian lot component membutuhkan lokasi, komponen, satuan, dan tanggal issue yang valid.'];
        }
        if ($qtyNeed <= 0) {
            return ['ok' => false, 'message' => 'qty_out lot component wajib lebih besar dari nol.'];
        }

        $lots = [];
        if ($selectedLotId !== null) {
            $selectedLot = $this->findLotById($selectedLotId, true);
            if (empty($selectedLot)) {
                return ['ok' => false, 'message' => 'Lot component yang dipilih tidak ditemukan.'];
            }
            if (
                strtoupper(trim((string)($selectedLot['location_type'] ?? ''))) !== $locationType
                || $this->nullableInt($selectedLot['division_id'] ?? null) !== $divisionId
                || (int)($selectedLot['component_id'] ?? 0) !== $componentId
                || (int)($selectedLot['uom_id'] ?? 0) !== $uomId
            ) {
                return ['ok' => false, 'message' => 'Lot component yang dipilih tidak cocok dengan lokasi, divisi, komponen, atau UOM adjustment.'];
            }
            if (strtoupper(trim((string)($selectedLot['status'] ?? ''))) !== 'OPEN' || (float)($selectedLot['qty_balance'] ?? 0) <= 0) {
                return ['ok' => false, 'message' => 'Lot component yang dipilih sudah tidak aktif atau saldonya habis.'];
            }
            $lots = [$selectedLot];
        } else {
            $lots = $this->findOpenLots([
                'location_type' => $locationType,
                'division_id' => $divisionId,
                'component_id' => $componentId,
                'uom_id' => $uomId,
            ]);
        }

        $available = 0.0;
        foreach ($lots as $lot) {
            $available += round((float)($lot['qty_balance'] ?? 0), 4);
        }
        $available = round($available, 4);
        if ($available + 0.0001 < $qtyNeed) {
            return [
                'ok' => false,
                'message' => ($selectedLotId !== null ? 'Saldo lot component yang dipilih tidak cukup. ' : 'Saldo lot component tidak cukup. ')
                    . 'Dibutuhkan ' . number_format($qtyNeed, 4, '.', '') . ', tersedia ' . number_format($available, 4, '.', '') . '.',
            ];
        }

        $issueNo = $this->generateIssueNo($issueDate);
        $now = date('Y-m-d H:i:s');
        $this->ci->db->insert('inv_component_lot_issue_log', [
            'issue_no' => $issueNo,
            'issue_date' => $issueDate,
            'issue_datetime' => $now,
            'location_type' => $locationType,
            'division_id' => $divisionId,
            'component_id' => $componentId,
            'uom_id' => $uomId,
            'issue_qty' => $qtyNeed,
            'total_cost' => 0,
            'source_module' => $this->nullableString($payload['source_module'] ?? 'PRODUCTION_PRODUCT'),
            'source_table' => $this->nullableString($payload['source_table'] ?? null),
            'source_id' => $this->nullableInt($payload['source_id'] ?? null),
            'source_line_id' => $this->nullableInt($payload['source_line_id'] ?? null),
            'notes' => $this->nullableString($payload['notes'] ?? null),
            'status' => 'POSTED',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $issueId = (int)$this->ci->db->insert_id();
        if ($issueId <= 0) {
            return ['ok' => false, 'message' => 'Gagal membuat log issue lot component.'];
        }

        $remaining = $qtyNeed;
        $totalCost = 0.0;
        $allocations = [];

        foreach ($lots as $lot) {
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

            $mutation = $this->applyLotMutation([
                'lot_id' => $lotId,
                'location_type' => $locationType,
                'division_id' => $divisionId,
                'component_id' => $componentId,
                'uom_id' => $uomId,
                'lot_no' => (string)($lot['lot_no'] ?? ''),
                'receipt_date' => (string)($lot['receipt_date'] ?? $issueDate),
                'expiry_date' => $this->normalizeDate((string)($lot['expiry_date'] ?? '')),
                'unit_cost' => max(0, round((float)($lot['unit_cost'] ?? 0), 6)),
                'source_module' => $this->nullableString($lot['source_module'] ?? null),
                'source_table' => $this->nullableString($lot['source_table'] ?? null),
                'source_id' => $this->nullableInt($lot['source_id'] ?? null),
                'source_line_id' => $this->nullableInt($lot['source_line_id'] ?? null),
                'parent_lot_id' => $this->nullableInt($lot['parent_lot_id'] ?? null),
            ], 0.0, $takeQty);
            if (!($mutation['ok'] ?? false)) {
                return $mutation;
            }

            $unitCost = max(0, round((float)($lot['unit_cost'] ?? 0), 6));
            $lineCost = round($takeQty * $unitCost, 2);
            $this->ci->db->insert('inv_component_lot_issue_line', [
                'issue_id' => $issueId,
                'lot_id' => $lotId,
                'qty_out' => $takeQty,
                'unit_cost' => $unitCost,
                'total_cost' => $lineCost,
                'source_balance_before' => $lotBalance,
                'source_balance_after' => round((float)($mutation['data']['qty_balance'] ?? 0), 4),
                'created_at' => $now,
            ]);
            if ((int)$this->ci->db->insert_id() <= 0) {
                return ['ok' => false, 'message' => 'Gagal menyimpan detail issue lot component.'];
            }

            $allocations[] = [
                'lot_id' => $lotId,
                'lot_no' => (string)($lot['lot_no'] ?? ''),
                'qty_out' => $takeQty,
                'unit_cost' => $unitCost,
                'total_cost' => $lineCost,
                'receipt_date' => (string)($lot['receipt_date'] ?? ''),
            ];
            $remaining = round($remaining - $takeQty, 4);
            $totalCost = round($totalCost + $lineCost, 2);
        }

        if ($remaining > 0.0001) {
            return ['ok' => false, 'message' => 'Issue lot component tidak lengkap.'];
        }

        $this->ci->db->where('id', $issueId)->update('inv_component_lot_issue_log', [
            'total_cost' => $totalCost,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'ok' => true,
            'message' => 'Pemakaian lot component berhasil diposting.',
            'data' => [
                'issue_id' => $issueId,
                'issue_no' => $issueNo,
                'allocations' => $allocations,
                'total_cost' => $totalCost,
                'avg_unit_cost' => $qtyNeed > 0 ? round($totalCost / $qtyNeed, 6) : 0.0,
            ],
        ];
    }

    public function rollbackIssueLotsBySource(string $sourceTable, int $sourceId, ?int $sourceLineId = null, string $voidNote = ''): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        if ($sourceTable === '' || $sourceId <= 0) {
            return ['ok' => false, 'message' => 'Sumber rollback lot component tidak valid.'];
        }

        $this->ci->db->from('inv_component_lot_issue_log')
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->where('status', 'POSTED');
        if ($sourceLineId !== null) {
            $this->ci->db->where('source_line_id', $sourceLineId);
        }
        $issueLogs = $this->ci->db->order_by('id', 'DESC')->get()->result_array();
        if (empty($issueLogs)) {
            return ['ok' => true, 'data' => ['issue_count' => 0]];
        }

        $voided = 0;
        foreach ($issueLogs as $log) {
            $lines = $this->ci->db->from('inv_component_lot_issue_line')
                ->where('issue_id', (int)($log['id'] ?? 0))
                ->order_by('id', 'DESC')
                ->get()
                ->result_array();
            foreach ($lines as $line) {
                $lot = $this->findLotById((int)($line['lot_id'] ?? 0), true);
                if (!$lot) {
                    return ['ok' => false, 'message' => 'Lot component untuk rollback issue tidak ditemukan.'];
                }
                $qtyOut = round((float)($line['qty_out'] ?? 0), 4);
                $unitCost = max(0, round((float)($line['unit_cost'] ?? ($lot['unit_cost'] ?? 0)), 6));
                $rollback = $this->applyLotMutation([
                    'lot_id' => (int)$lot['id'],
                    'location_type' => (string)$lot['location_type'],
                    'division_id' => $this->nullableInt($lot['division_id'] ?? null),
                    'component_id' => (int)$lot['component_id'],
                    'uom_id' => (int)$lot['uom_id'],
                    'lot_no' => (string)$lot['lot_no'],
                    'receipt_date' => (string)$lot['receipt_date'],
                    'expiry_date' => $this->normalizeDate((string)($lot['expiry_date'] ?? '')),
                    'unit_cost' => $unitCost,
                    'source_module' => $this->nullableString($lot['source_module'] ?? null),
                    'source_table' => $this->nullableString($lot['source_table'] ?? null),
                    'source_id' => $this->nullableInt($lot['source_id'] ?? null),
                    'source_line_id' => $this->nullableInt($lot['source_line_id'] ?? null),
                    'parent_lot_id' => $this->nullableInt($lot['parent_lot_id'] ?? null),
                ], $qtyOut, 0.0);
                if (!($rollback['ok'] ?? false)) {
                    return $rollback;
                }
            }

            $notes = trim((string)($log['notes'] ?? ''));
            $note = trim($voidNote);
            if ($note !== '') {
                $notes = $notes !== '' ? ($notes . ' | ' . $note) : $note;
            }
            $this->ci->db->where('id', (int)($log['id'] ?? 0))->update('inv_component_lot_issue_log', [
                'status' => 'VOID',
                'notes' => $notes !== '' ? $notes : null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $voided++;
        }

        return ['ok' => true, 'data' => ['issue_count' => $voided]];
    }

    public function voidInboundLotsBySource(string $sourceTable, int $sourceId, ?int $sourceLineId = null, string $voidNote = ''): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return $ensure;
        }

        if ($sourceTable === '' || $sourceId <= 0) {
            return ['ok' => false, 'message' => 'Sumber void lot component tidak valid.'];
        }

        $this->ci->db->from('inv_component_lot')
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->where('status <>', 'VOID');
        if ($sourceLineId !== null) {
            $this->ci->db->where('source_line_id', $sourceLineId);
        }
        $lots = $this->ci->db->order_by('id', 'DESC')->get()->result_array();
        if (empty($lots)) {
            return ['ok' => true, 'data' => ['lot_count' => 0]];
        }

        foreach ($lots as $lot) {
            if (round((float)($lot['qty_out_total'] ?? 0), 4) > 0.0001) {
                return ['ok' => false, 'message' => 'Lot output component sudah pernah dipakai sehingga tidak bisa di-void.'];
            }
            $notes = trim((string)($lot['source_module'] ?? ''));
            if ($voidNote !== '') {
                $notes = $notes !== '' ? ($notes . ' | ' . $voidNote) : $voidNote;
            }
            $this->ci->db->where('id', (int)($lot['id'] ?? 0))->update('inv_component_lot', [
                'qty_balance' => 0,
                'status' => 'VOID',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return ['ok' => true, 'data' => ['lot_count' => count($lots)]];
    }

    public function listLots(array $filters = [], int $limit = 200): array
    {
        $ensure = $this->ensureSchema();
        if (!($ensure['ok'] ?? false)) {
            return [];
        }

        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'OPEN')));
        if (!in_array($status, ['OPEN', 'CLOSED', 'VOID', 'ALL'], true)) {
            $status = 'OPEN';
        }
        $locationType = strtoupper(trim((string)($filters['location_type'] ?? '')));
        $divisionId = $this->nullableInt($filters['division_id'] ?? null);
        $componentType = strtoupper(trim((string)($filters['type'] ?? '')));
        if (!in_array($componentType, ['BASE', 'PREPARE'], true)) {
            $componentType = '';
        }

        $db = $this->ci->db;
        $db->select('l.*, c.component_code, c.component_name, c.component_type, u.code AS uom_code, d.name AS division_name, b.batch_no, b.batch_date');
        $db->from('inv_component_lot l');
        $db->join('mst_component c', 'c.id = l.component_id', 'left');
        $db->join('mst_uom u', 'u.id = l.uom_id', 'left');
        $db->join('mst_operational_division d', 'd.id = l.division_id', 'left');
        if ($db->table_exists('inv_component_batch')) {
            $db->join('inv_component_batch b', 'b.id = l.source_id AND l.source_table = ' . $db->escape('inv_component_batch'), 'left', false);
        }

        if ($q !== '') {
            $db->group_start()
                ->like('l.lot_no', $q)
                ->or_like('c.component_code', $q)
                ->or_like('c.component_name', $q)
                ->or_like('b.batch_no', $q)
                ->group_end();
        }
        if ($divisionId !== null) {
            $db->where('l.division_id', $divisionId);
        }
        if ($locationType === 'REGULER') {
            $db->where_in('l.location_type', ['BAR', 'KITCHEN']);
        } elseif ($locationType === 'EVENT') {
            $db->where_in('l.location_type', ['BAR_EVENT', 'KITCHEN_EVENT']);
        } elseif ($this->validLocation($locationType)) {
            $db->where('l.location_type', $locationType);
        }
        if ($status !== 'ALL') {
            $db->where('l.status', $status);
        }
        if ($componentType !== '') {
            $db->where('c.component_type', $componentType);
        }
        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        $dateTo   = trim((string)($filters['date_to'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $db->where('l.receipt_date >=', $dateFrom);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $db->where('l.receipt_date <=', $dateTo);
        }

        $db->order_by('CASE WHEN l.status = "OPEN" THEN 0 WHEN l.status = "CLOSED" THEN 1 ELSE 2 END', '', false);
        $db->order_by('d.name', 'ASC');
        $db->order_by("CASE UPPER(c.component_type) WHEN 'BASE' THEN 0 WHEN 'PREPARE' THEN 1 ELSE 9 END", '', false);
        $db->order_by('c.component_name', 'ASC');
        $db->order_by('l.receipt_date', 'ASC');
        $db->order_by('l.id', 'ASC');
        $db->limit(max(1, $limit));
        return $db->get()->result_array();
    }

    private function ensureSchema(): array
    {
        if ($this->schemaEnsured) {
            return ['ok' => true];
        }

        try {
            $this->ci->db->query("CREATE TABLE IF NOT EXISTS inv_component_lot (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                location_type VARCHAR(20) NOT NULL,
                division_id BIGINT UNSIGNED NULL,
                component_id BIGINT UNSIGNED NOT NULL,
                uom_id BIGINT UNSIGNED NOT NULL,
                lot_no VARCHAR(64) NOT NULL,
                receipt_date DATE NOT NULL,
                expiry_date DATE NULL,
                unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0,
                qty_in_total DECIMAL(18,4) NOT NULL DEFAULT 0,
                qty_out_total DECIMAL(18,4) NOT NULL DEFAULT 0,
                qty_balance DECIMAL(18,4) NOT NULL DEFAULT 0,
                source_module VARCHAR(50) NULL,
                source_table VARCHAR(100) NULL,
                source_id BIGINT UNSIGNED NULL,
                source_line_id BIGINT UNSIGNED NULL,
                parent_lot_id BIGINT UNSIGNED NULL,
                last_issue_at DATETIME NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uk_inv_component_lot_scope (location_type, division_id, component_id, uom_id, lot_no),
                KEY idx_inv_component_lot_source (source_table, source_id, source_line_id),
                KEY idx_inv_component_lot_open (location_type, division_id, component_id, uom_id, status, receipt_date),
                KEY idx_inv_component_lot_component (component_id, receipt_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $this->ci->db->query("CREATE TABLE IF NOT EXISTS inv_component_lot_issue_log (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                issue_no VARCHAR(32) NOT NULL,
                issue_date DATE NOT NULL,
                issue_datetime DATETIME NOT NULL,
                location_type VARCHAR(20) NOT NULL,
                division_id BIGINT UNSIGNED NULL,
                component_id BIGINT UNSIGNED NOT NULL,
                uom_id BIGINT UNSIGNED NOT NULL,
                issue_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
                total_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
                source_module VARCHAR(50) NULL,
                source_table VARCHAR(100) NULL,
                source_id BIGINT UNSIGNED NULL,
                source_line_id BIGINT UNSIGNED NULL,
                notes TEXT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'POSTED',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uk_inv_component_lot_issue_no (issue_no),
                KEY idx_inv_component_lot_issue_source (source_table, source_id, source_line_id),
                KEY idx_inv_component_lot_issue_main (location_type, division_id, component_id, uom_id, issue_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $this->ci->db->query("CREATE TABLE IF NOT EXISTS inv_component_lot_issue_line (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                issue_id BIGINT UNSIGNED NOT NULL,
                lot_id BIGINT UNSIGNED NOT NULL,
                qty_out DECIMAL(18,4) NOT NULL DEFAULT 0,
                unit_cost DECIMAL(18,6) NOT NULL DEFAULT 0,
                total_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
                source_balance_before DECIMAL(18,4) NULL,
                source_balance_after DECIMAL(18,4) NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_inv_component_lot_issue_line_issue (issue_id),
                KEY idx_inv_component_lot_issue_line_lot (lot_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Gagal menyiapkan schema lot component: ' . $e->getMessage()];
        }

        $this->schemaEnsured = true;
        return ['ok' => true];
    }

    private function applyLotMutation(array $identity, float $qtyIn, float $qtyOut): array
    {
        $lot = [];
        $lotId = $this->nullableInt($identity['lot_id'] ?? null);
        if ($lotId !== null) {
            $lot = $this->findLotById($lotId, true);
        } else {
            $lot = $this->ci->db->query(
                'SELECT * FROM inv_component_lot WHERE location_type = ? AND division_id <=> ? AND component_id = ? AND uom_id = ? AND lot_no = ? LIMIT 1 FOR UPDATE',
                [
                    (string)$identity['location_type'],
                    $this->nullableInt($identity['division_id'] ?? null),
                    (int)$identity['component_id'],
                    (int)$identity['uom_id'],
                    (string)$identity['lot_no'],
                ]
            )->row_array();
        }

        $qtyIn = round($qtyIn, 4);
        $qtyOut = round($qtyOut, 4);
        $qtyInTotal = round((float)($lot['qty_in_total'] ?? 0) + $qtyIn, 4);
        $qtyOutTotal = round((float)($lot['qty_out_total'] ?? 0) + $qtyOut, 4);
        $qtyBalance = round((float)($lot['qty_balance'] ?? 0) + $qtyIn - $qtyOut, 4);
        if ($qtyBalance < -0.0001) {
            return ['ok' => false, 'message' => 'Saldo lot component menjadi negatif.'];
        }
        if (abs($qtyBalance) < 0.0001) {
            $qtyBalance = 0.0;
        }

        $status = $qtyBalance > 0 ? 'OPEN' : 'CLOSED';
        $now = date('Y-m-d H:i:s');
        $data = [
            'location_type' => (string)$identity['location_type'],
            'division_id' => $this->nullableInt($identity['division_id'] ?? null),
            'component_id' => (int)$identity['component_id'],
            'uom_id' => (int)$identity['uom_id'],
            'lot_no' => (string)$identity['lot_no'],
            'receipt_date' => (string)$identity['receipt_date'],
            'expiry_date' => $this->normalizeDate((string)($identity['expiry_date'] ?? '')),
            'unit_cost' => max(0, round((float)($identity['unit_cost'] ?? 0), 6)),
            'qty_in_total' => $qtyInTotal,
            'qty_out_total' => $qtyOutTotal,
            'qty_balance' => $qtyBalance,
            'source_module' => $this->nullableString($identity['source_module'] ?? null),
            'source_table' => $this->nullableString($identity['source_table'] ?? null),
            'source_id' => $this->nullableInt($identity['source_id'] ?? null),
            'source_line_id' => $this->nullableInt($identity['source_line_id'] ?? null),
            'parent_lot_id' => $this->nullableInt($identity['parent_lot_id'] ?? null),
            'last_issue_at' => $qtyOut > 0 ? $now : ($lot['last_issue_at'] ?? null),
            'status' => $status,
            'updated_at' => $now,
        ];

        if (!empty($lot['id'])) {
            $this->ci->db->where('id', (int)$lot['id'])->update('inv_component_lot', $data);
            $lotId = (int)$lot['id'];
        } else {
            $data['created_at'] = $now;
            $this->ci->db->insert('inv_component_lot', $data);
            $lotId = (int)$this->ci->db->insert_id();
        }

        if ($lotId <= 0) {
            return ['ok' => false, 'message' => 'Gagal menyimpan mutasi lot component.'];
        }

        $saved = $this->findLotById($lotId, false);
        return ['ok' => true, 'data' => $saved ?: ['id' => $lotId, 'qty_balance' => $qtyBalance]];
    }

    private function findOpenLots(array $identity): array
    {
        return $this->ci->db->from('inv_component_lot')
            ->where('location_type', (string)$identity['location_type'])
            ->where('division_id', $this->nullableInt($identity['division_id'] ?? null))
            ->where('component_id', (int)$identity['component_id'])
            ->where('uom_id', (int)$identity['uom_id'])
            ->where('status', 'OPEN')
            ->where('qty_balance >', 0)
            ->order_by('receipt_date', 'ASC')
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();
    }

    private function findLotById(int $lotId, bool $forUpdate): ?array
    {
        if ($lotId <= 0) {
            return null;
        }
        $sql = 'SELECT * FROM inv_component_lot WHERE id = ? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
        $row = $this->ci->db->query($sql, [$lotId])->row_array();
        return $row ?: null;
    }

    private function validLocation(string $locationType): bool
    {
        return in_array($locationType, ['BAR', 'KITCHEN', 'BAR_EVENT', 'KITCHEN_EVENT'], true);
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
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        $intValue = (int)$value;
        return $intValue > 0 ? $intValue : null;
    }

    private function nullableString($value): ?string
    {
        $value = trim((string)$value);
        return $value !== '' ? $value : null;
    }

    private function generateLotNo(string $receiptDate, int $componentId, int $sourceId): string
    {
        $datePart = date('Ymd', strtotime($receiptDate));
        return 'ICL' . $datePart . str_pad((string)$componentId, 5, '0', STR_PAD_LEFT) . str_pad((string)max(0, $sourceId), 5, '0', STR_PAD_LEFT);
    }

    private function generateIssueNo(string $issueDate): string
    {
        $datePart = date('Ymd', strtotime($issueDate));
        $prefix = 'ICI' . $datePart;
        $row = $this->ci->db->select('issue_no')
            ->from('inv_component_lot_issue_log')
            ->like('issue_no', $prefix, 'after')
            ->order_by('issue_no', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();
        $seq = 1;
        if (!empty($row['issue_no'])) {
            $suffix = substr((string)$row['issue_no'], strlen($prefix));
            if (ctype_digit($suffix)) {
                $seq = ((int)$suffix) + 1;
            }
        }
        return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }
}