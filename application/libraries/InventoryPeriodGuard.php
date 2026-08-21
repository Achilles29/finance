<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Central period gate for inventory writers.
 *
 * The guard is intentionally permissive until the foundation migration exists,
 * so a rolling deployment cannot stop POS or adjustment requests mid-release.
 */
class InventoryPeriodGuard
{
    /** @var CI_Controller */
    private $ci;
    /** @var array<string, array> */
    private $periodCache = [];
    /** @var bool|null */
    private $readyCache = null;
    /** @var array<string, bool> */
    private $cutoffWriteContexts = [];

    public function __construct()
    {
        $this->ci =& get_instance();
    }

    public function isReady(): bool
    {
        if ($this->readyCache === null) {
            $this->readyCache = $this->ci->db->table_exists('inv_stock_period');
        }
        return $this->readyCache;
    }

    public function assertOpen(string $stockDomain, string $eventDate, string $operation = 'transaksi'): array
    {
        $stockDomain = strtoupper(trim($stockDomain));
        if (!in_array($stockDomain, ['MATERIAL', 'COMPONENT'], true)) {
            return ['ok' => false, 'message' => 'Domain stok tidak valid untuk period guard.'];
        }

        $periodMonth = $this->normalizeMonth($eventDate);
        if ($periodMonth === null) {
            return ['ok' => false, 'message' => 'Tanggal ' . $operation . ' tidak valid.'];
        }

        if (!$this->isReady()) {
            return [
                'ok' => true,
                'guard_active' => false,
                'message' => 'Period guard belum aktif karena migration inventory belum dijalankan.',
            ];
        }

        $cacheKey = $this->cacheKey($stockDomain, $periodMonth);
        if (array_key_exists($cacheKey, $this->periodCache)) {
            $row = $this->periodCache[$cacheKey];
        } else {
            $row = $this->ci->db
                ->select('id, status, period_month')
                ->from('inv_stock_period')
                ->where('stock_domain', $stockDomain)
                ->where('period_month', $periodMonth)
                ->limit(1)
                ->get()
                ->row_array();
            $this->periodCache[$cacheKey] = $row ?: [];
        }

        $status = strtoupper(trim((string)($row['status'] ?? 'OPEN')));
        if ($status === 'CLOSING' && $this->hasCutoffWriteContext($stockDomain, $periodMonth)) {
            return [
                'ok' => true,
                'guard_active' => true,
                'cutoff_context' => true,
                'period_id' => (int)($row['id'] ?? 0),
                'status' => $status,
                'period_month' => $periodMonth,
            ];
        }
        if (in_array($status, ['CLOSING', 'CLOSED'], true)) {
            return [
                'ok' => false,
                'code' => 'INVENTORY_PERIOD_CLOSED',
                'message' => 'Periode stok ' . date('m/Y', strtotime($periodMonth))
                    . ' sudah ditutup. ' . ucfirst($operation) . ' tidak dapat diposting atau di-void tanpa reopen resmi.',
                'period_id' => (int)($row['id'] ?? 0),
                'status' => $status,
            ];
        }

        return [
            'ok' => true,
            'guard_active' => true,
            'period_id' => (int)($row['id'] ?? 0),
            'status' => $status,
            'period_month' => $periodMonth,
        ];
    }

    public function ensureOpen(string $stockDomain, string $eventDate, ?int $actorUserId = null, string $note = ''): array
    {
        $check = $this->assertOpen($stockDomain, $eventDate, 'transaksi');
        if (!($check['ok'] ?? false) || !$this->isReady()) {
            return $check;
        }
        if (!empty($check['period_id'])) {
            return $check;
        }

        $periodMonth = (string)$check['period_month'];
        // Two writers can start at the same time (for example POS background
        // jobs). Duplicate-key failure is safe here: re-read the period below.
        $db = $this->ci->db;
        $previousDbDebug = isset($db->db_debug) ? (bool)$db->db_debug : false;
        $db->db_debug = false;
        $inserted = $db->insert('inv_stock_period', [
            'stock_domain' => strtoupper(trim($stockDomain)),
            'period_month' => $periodMonth,
            'status' => 'OPEN',
            'close_mode' => 'MONTHLY_OPNAME',
            'notes' => $note !== '' ? substr($note, 0, 255) : null,
            'created_by' => $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
        ]);
        $insertId = (int)$db->insert_id();
        $db->db_debug = $previousDbDebug;
        $this->forgetPeriod($stockDomain, $periodMonth);

        if (!$inserted || $insertId <= 0) {
            $retry = $this->assertOpen($stockDomain, $eventDate, 'transaksi');
            // A duplicate insert from another concurrent writer is safe only
            // when the re-read finds the period that writer just created.
            if (($retry['ok'] ?? false) && !empty($retry['period_id'])) {
                return $retry;
            }
            if (!($retry['ok'] ?? false)) {
                return $retry;
            }
            return ['ok' => false, 'message' => 'Gagal menyiapkan periode stok aktif.'];
        }

        return $this->assertOpen($stockDomain, $eventDate, 'transaksi');
    }

    /**
     * Automatically creates a record only for the active calendar month.
     * Older unregistered months stay readable/writable for backward-compatible
     * cleanup, but an explicitly closed month is still always blocked.
     */
    public function ensureActiveMonthOpen(string $stockDomain, string $eventDate, ?int $actorUserId = null, string $note = ''): array
    {
        $check = $this->assertOpen($stockDomain, $eventDate, 'transaksi');
        if (!($check['ok'] ?? false) || !$this->isReady() || !empty($check['period_id'])) {
            return $check;
        }

        $activeMonth = date('Y-m-01');
        if ((string)($check['period_month'] ?? '') !== $activeMonth) {
            return $check;
        }

        return $this->ensureOpen($stockDomain, $eventDate, $actorUserId, $note);
    }

    public function closePeriod(string $stockDomain, string $eventDate, ?int $actorUserId = null, string $note = ''): array
    {
        $open = $this->ensureOpen($stockDomain, $eventDate, $actorUserId, $note);
        if (!($open['ok'] ?? false) || !$this->isReady()) {
            return $open;
        }

        $this->ci->db->where('id', (int)($open['period_id'] ?? 0))->update('inv_stock_period', [
            'status' => 'CLOSED',
            'close_mode' => 'MONTHLY_OPNAME',
            'closed_by' => $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
            'closed_at' => date('Y-m-d H:i:s'),
            'notes' => $note !== '' ? substr($note, 0, 255) : null,
        ]);
        $this->forgetPeriod($stockDomain, (string)($open['period_month'] ?? ''));
        return [
            'ok' => true,
            'period_id' => (int)($open['period_id'] ?? 0),
            'period_month' => $open['period_month'] ?? null,
            'status' => 'CLOSED',
        ];
    }

    /**
     * Marks a period as closing before a controlled cut-off writer starts.
     * Normal inventory requests are blocked while the official writer has a
     * short-lived in-process context to finish its own work.
     */
    public function beginClosingPeriod(string $stockDomain, string $eventDate, ?int $actorUserId = null, string $note = ''): array
    {
        $open = $this->ensureOpen($stockDomain, $eventDate, $actorUserId, $note);
        if (!($open['ok'] ?? false) || !$this->isReady()) {
            return $open;
        }

        $status = strtoupper(trim((string)($open['status'] ?? 'OPEN')));
        if (!in_array($status, ['OPEN', 'REOPENED'], true)) {
            return [
                'ok' => false,
                'message' => 'Periode stok tidak dapat mulai ditutup dari status ' . ($status ?: '-') . '.',
                'period_id' => (int)($open['period_id'] ?? 0),
                'status' => $status,
            ];
        }

        $periodId = (int)($open['period_id'] ?? 0);
        if ($periodId <= 0) {
            return ['ok' => false, 'message' => 'ID periode stok tidak ditemukan saat memulai cut-off.'];
        }

        $this->ci->db
            ->where('id', $periodId)
            ->where_in('status', ['OPEN', 'REOPENED'])
            ->update('inv_stock_period', [
                'status' => 'CLOSING',
                'close_mode' => 'MONTHLY_OPNAME',
                'notes' => $note !== '' ? substr($note, 0, 255) : 'Cut-off stok resmi sedang diproses.',
            ]);

        if ($this->ci->db->affected_rows() !== 1) {
            $this->forgetPeriod($stockDomain, (string)($open['period_month'] ?? ''));
            return [
                'ok' => false,
                'message' => 'Periode stok berubah oleh proses lain. Muat ulang halaman sebelum mencoba cut-off lagi.',
                'period_id' => $periodId,
            ];
        }

        $this->forgetPeriod($stockDomain, (string)($open['period_month'] ?? ''));
        return [
            'ok' => true,
            'period_id' => $periodId,
            'period_month' => $open['period_month'] ?? null,
            'status' => 'CLOSING',
        ];
    }

    public function reopenPeriod(string $stockDomain, string $eventDate, ?int $actorUserId = null, string $note = ''): array
    {
        if (!$this->isReady()) {
            return ['ok' => false, 'message' => 'Period guard belum aktif. Jalankan migration inventory terlebih dahulu.'];
        }
        $periodMonth = $this->normalizeMonth($eventDate);
        if ($periodMonth === null) {
            return ['ok' => false, 'message' => 'Bulan reopen tidak valid.'];
        }
        $stockDomain = strtoupper(trim($stockDomain));
        if (!in_array($stockDomain, ['MATERIAL', 'COMPONENT'], true)) {
            return ['ok' => false, 'message' => 'Domain stok tidak valid untuk reopen periode.'];
        }
        $this->ci->db->where('stock_domain', $stockDomain)->where('period_month', $periodMonth)->update('inv_stock_period', [
            'status' => 'REOPENED',
            'reopened_by' => $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
            'reopened_at' => date('Y-m-d H:i:s'),
            'notes' => $note !== '' ? substr($note, 0, 255) : 'Reopen resmi inventory.',
        ]);
        $this->forgetPeriod($stockDomain, $periodMonth);
        return $this->assertOpen($stockDomain, $eventDate, 'reopen');
    }

    /**
     * Allows only the current official cut-off request to write its source
     * month while all ordinary requests remain blocked by CLOSING.
     */
    public function beginCutoffWriteContext(string $stockDomain, string $eventDate): bool
    {
        $stockDomain = strtoupper(trim($stockDomain));
        $periodMonth = $this->normalizeMonth($eventDate);
        if (!in_array($stockDomain, ['MATERIAL', 'COMPONENT'], true) || $periodMonth === null) {
            return false;
        }

        $this->cutoffWriteContexts[$this->cacheKey($stockDomain, $periodMonth)] = true;
        $this->forgetPeriod($stockDomain, $periodMonth);
        return true;
    }

    public function endCutoffWriteContext(string $stockDomain, string $eventDate): void
    {
        $periodMonth = $this->normalizeMonth($eventDate);
        if ($periodMonth === null) {
            return;
        }

        unset($this->cutoffWriteContexts[$this->cacheKey($stockDomain, $periodMonth)]);
        $this->forgetPeriod($stockDomain, $periodMonth);
    }

    private function cacheKey(string $stockDomain, string $periodMonth): string
    {
        return strtoupper(trim($stockDomain)) . '|' . $periodMonth;
    }

    private function forgetPeriod(string $stockDomain, string $periodMonth): void
    {
        if ($periodMonth === '') {
            return;
        }
        unset($this->periodCache[$this->cacheKey($stockDomain, $periodMonth)]);
    }

    private function hasCutoffWriteContext(string $stockDomain, string $periodMonth): bool
    {
        return !empty($this->cutoffWriteContexts[$this->cacheKey($stockDomain, $periodMonth)]);
    }

    private function normalizeMonth(string $date): ?string
    {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return null;
        }
        return date('Y-m-01', $timestamp);
    }
}
