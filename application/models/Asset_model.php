<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Asset_model extends CI_Model
{
    private const TABLE_CATEGORY = 'asset_category';
    private const TABLE_ASSET = 'asset_item';
    private const TABLE_EVENT = 'asset_event';
    private const TABLE_RECON = 'asset_recon';
    private const TABLE_RECON_LINE = 'asset_recon_line';
    private const TABLE_WORKFLOW = 'asset_workflow';
    private const TABLE_DEP_RUN = 'asset_depreciation_run';
    private const TABLE_DEP_LINE = 'asset_depreciation_run_line';

    public function table_ready(): bool
    {
        return $this->db->table_exists(self::TABLE_CATEGORY)
            && $this->db->table_exists(self::TABLE_ASSET)
            && $this->db->table_exists(self::TABLE_EVENT)
            && $this->db->table_exists(self::TABLE_RECON)
            && $this->db->table_exists(self::TABLE_RECON_LINE);
    }

    public function extension_ready(): bool
    {
        return $this->table_ready()
            && $this->db->table_exists(self::TABLE_WORKFLOW)
            && $this->db->table_exists(self::TABLE_DEP_RUN)
            && $this->db->table_exists(self::TABLE_DEP_LINE);
    }

    public function status_labels(): array
    {
        return [
            'ACTIVE' => 'Aktif',
            'BROKEN' => 'Rusak',
            'REPAIR' => 'Perbaikan',
            'LOST' => 'Hilang',
            'RETIRED' => 'Pensiun',
            'DISPOSED' => 'Dibuang',
        ];
    }

    public function physical_status_labels(): array
    {
        return [
            'NOT_CHECKED' => 'Belum dicek',
            'OK' => 'Ada & baik',
            'BROKEN' => 'Ada tapi rusak',
            'MISSING' => 'Tidak ditemukan',
            'NEED_REPAIR' => 'Butuh perbaikan',
            'EXTRA_FOUND' => 'Ada tambahan',
        ];
    }

    public function damage_event_type_labels(): array
    {
        return [
            'DAMAGE' => 'Rusak',
            'REPAIR' => 'Perbaikan',
            'LOST' => 'Hilang',
            'RETIRED' => 'Pensiun',
            'DISPOSED' => 'Dibuang',
        ];
    }

    public function workflow_configs(): array
    {
        return [
            'TRANSFER' => [
                'page_code' => 'asset.transfer.index',
                'label' => 'Mutasi Aset',
                'short_label' => 'Mutasi',
                'url' => 'asset-management/transfer',
                'icon' => 'ri-arrow-left-right-line',
                'theme' => 'primary',
            ],
            'HANDOVER' => [
                'page_code' => 'asset.handover.index',
                'label' => 'Serah Terima Aset',
                'short_label' => 'Serah Terima',
                'url' => 'asset-management/handover',
                'icon' => 'ri-user-follow-line',
                'theme' => 'success',
            ],
            'MAINTENANCE' => [
                'page_code' => 'asset.maintenance.index',
                'label' => 'Maintenance Aset',
                'short_label' => 'Maintenance',
                'url' => 'asset-management/maintenance',
                'icon' => 'ri-tools-line',
                'theme' => 'warning',
            ],
            'DISPOSAL' => [
                'page_code' => 'asset.disposal.index',
                'label' => 'Disposal Aset',
                'short_label' => 'Disposal',
                'url' => 'asset-management/disposal',
                'icon' => 'ri-delete-bin-line',
                'theme' => 'danger',
            ],
        ];
    }

    public function workflow_status_labels(): array
    {
        return [
            'PENDING' => 'Menunggu',
            'APPROVED' => 'Disetujui',
            'REJECTED' => 'Ditolak',
            'POSTED' => 'Posted',
            'DONE' => 'Selesai',
            'CANCELLED' => 'Dibatalkan',
        ];
    }

    public function priority_labels(): array
    {
        return [
            'LOW' => 'Rendah',
            'NORMAL' => 'Normal',
            'HIGH' => 'Tinggi',
            'URGENT' => 'Mendesak',
        ];
    }

    public function category_options(bool $activeOnly = true): array
    {
        if (!$this->db->table_exists(self::TABLE_CATEGORY)) {
            return [];
        }

        $this->db
            ->select('id, category_code, category_name, default_depreciation_method, default_useful_life_months, default_residual_value')
            ->from(self::TABLE_CATEGORY);
        if ($activeOnly) {
            $this->db->where('is_active', 1);
        }

        return $this->db
            ->order_by('sort_order', 'ASC')
            ->order_by('category_name', 'ASC')
            ->get()
            ->result_array();
    }

    public function division_options(): array
    {
        if (!$this->db->table_exists('mst_operational_division')) {
            return [];
        }

        return $this->db
            ->select('id, code, name')
            ->from('mst_operational_division')
            ->where('is_active', 1)
            ->order_by('sort_order', 'ASC')
            ->order_by('name', 'ASC')
            ->get()
            ->result_array();
    }

    public function outlet_options(): array
    {
        if (!$this->db->table_exists('pos_outlet')) {
            return [];
        }

        return $this->db
            ->select('id, outlet_code, outlet_name')
            ->from('pos_outlet')
            ->where('is_active', 1)
            ->order_by('outlet_name', 'ASC')
            ->get()
            ->result_array();
    }

    public function employee_options(): array
    {
        if (!$this->db->table_exists('org_employee')) {
            return [];
        }

        return $this->db
            ->select('id, employee_code, employee_name, division_id')
            ->from('org_employee')
            ->where('is_active', 1)
            ->order_by('employee_name', 'ASC')
            ->limit(500)
            ->get()
            ->result_array();
    }

    public function count_assets(array $filters = []): int
    {
        if (!$this->table_ready()) {
            return 0;
        }

        $this->build_asset_query($filters, 'COUNT(DISTINCT a.id) AS total', false);
        $row = $this->db->get()->row_array();
        return (int)($row['total'] ?? 0);
    }

    public function list_assets(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        if (!$this->table_ready()) {
            return [];
        }

        $this->build_asset_query($filters, $this->asset_select(), false);
        $rows = $this->db
            ->order_by('a.updated_at IS NULL', 'ASC', false)
            ->order_by('a.updated_at', 'DESC')
            ->order_by('a.id', 'DESC')
            ->limit(max(1, $limit), max(0, $offset))
            ->get()
            ->result_array();

        return $this->decorate_assets($rows);
    }

    public function count_asset_groups(array $filters = []): int
    {
        if (!$this->table_ready()) {
            return 0;
        }

        $this->build_asset_query($filters, $this->asset_group_key_expr() . ' AS group_key', false);
        $rows = $this->db
            ->group_by($this->asset_group_key_expr(), false)
            ->get()
            ->result_array();

        return count($rows);
    }

    public function list_asset_groups(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        if (!$this->table_ready()) {
            return [];
        }

        $this->build_asset_query($filters, $this->asset_group_select(), false);
        $rows = $this->db
            ->group_by($this->asset_group_key_expr(), false)
            ->order_by('last_activity_at', 'DESC')
            ->order_by('asset_name', 'ASC')
            ->limit(max(1, $limit), max(0, $offset))
            ->get()
            ->result_array();

        return $this->decorate_asset_groups($rows, $filters);
    }

    public function find_asset_group(string $groupKey, array $filters = []): ?array
    {
        $groupKey = strtolower(trim($groupKey));
        if (!preg_match('/^[a-f0-9]{40}$/', $groupKey)) {
            return null;
        }

        $filters['group_key'] = $groupKey;
        $rows = $this->list_asset_groups($filters, 1, 0);
        return $rows[0] ?? null;
    }

    public function asset_summary(array $filters = []): array
    {
        $rows = $this->list_assets(array_merge($filters, ['status' => 'ALL']), 10000, 0);
        $summary = [
            'total' => 0,
            'active' => 0,
            'broken' => 0,
            'repair' => 0,
            'lost' => 0,
            'retired' => 0,
            'acquisition_value' => 0.0,
            'book_value' => 0.0,
            'avg_condition' => 0.0,
        ];

        $conditionTotal = 0;
        foreach ($rows as $row) {
            $summary['total']++;
            $status = strtoupper((string)($row['status'] ?? ''));
            if ($status === 'ACTIVE') $summary['active']++;
            if ($status === 'BROKEN') $summary['broken']++;
            if ($status === 'REPAIR') $summary['repair']++;
            if ($status === 'LOST') $summary['lost']++;
            if ($status === 'RETIRED' || $status === 'DISPOSED') $summary['retired']++;
            $summary['acquisition_value'] += (float)($row['acquisition_cost'] ?? 0);
            $summary['book_value'] += (float)($row['book_value'] ?? 0);
            $conditionTotal += (int)($row['condition_score'] ?? 0);
        }

        if ($summary['total'] > 0) {
            $summary['avg_condition'] = round($conditionTotal / $summary['total'], 1);
        }

        return $summary;
    }

    public function monthly_movement_summary(string $month, ?int $divisionId = null): array
    {
        if (!$this->table_ready() || !preg_match('/^\d{4}-\d{2}$/', $month)) {
            return ['acquired' => 0, 'damaged' => 0, 'lost' => 0, 'repaired' => 0];
        }

        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));
        $out = ['acquired' => 0, 'damaged' => 0, 'lost' => 0, 'repaired' => 0];

        $this->db->from(self::TABLE_ASSET)->where('acquisition_date >=', $start)->where('acquisition_date <=', $end);
        if ($divisionId !== null && $divisionId > 0) {
            $this->db->where('division_id', $divisionId);
        }
        $out['acquired'] = (int)$this->db->count_all_results();

        $eventMap = [
            'damaged' => ['DAMAGE'],
            'lost' => ['LOST'],
            'repaired' => ['REPAIR'],
        ];
        foreach ($eventMap as $key => $types) {
            $this->db
                ->from(self::TABLE_EVENT . ' e')
                ->join(self::TABLE_ASSET . ' a', 'a.id = e.asset_id', 'inner')
                ->where('e.event_date >=', $start)
                ->where('e.event_date <=', $end)
                ->where_in('e.event_type', $types);
            if ($divisionId !== null && $divisionId > 0) {
                $this->db->where('a.division_id', $divisionId);
            }
            $out[$key] = (int)$this->db->count_all_results();
        }

        return $out;
    }

    public function recon_preview_assets(string $month, ?int $divisionId = null, int $limit = 250): array
    {
        if (!$this->table_ready() || !preg_match('/^\d{4}-\d{2}$/', $month)) {
            return [];
        }

        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));
        $select = $this->asset_select() . ",
            CASE WHEN a.acquisition_date >= " . $this->db->escape($start) . "
                  AND a.acquisition_date <= " . $this->db->escape($end) . "
                 THEN 1 ELSE 0 END AS is_new_in_period";

        $this->build_recon_candidate_query($month, $divisionId, $select);
        $rows = $this->db
            ->order_by("CASE a.status WHEN 'BROKEN' THEN 0 WHEN 'REPAIR' THEN 1 ELSE 2 END", 'ASC', false)
            ->order_by('c.category_name', 'ASC')
            ->order_by('a.asset_name', 'ASC')
            ->order_by('a.asset_code', 'ASC')
            ->limit(max(1, min($limit, 10000)))
            ->get()
            ->result_array();

        return $this->decorate_assets($rows);
    }

    public function recon_preview_summary(string $month, ?int $divisionId = null): array
    {
        $rows = $this->recon_preview_assets($month, $divisionId, 10000);
        $summary = [
            'total' => 0,
            'active' => 0,
            'broken' => 0,
            'repair' => 0,
            'new_in_period' => 0,
            'issue' => 0,
            'acquisition_value' => 0.0,
            'book_value' => 0.0,
            'avg_condition' => 0.0,
        ];

        $conditionTotal = 0;
        foreach ($rows as $row) {
            $summary['total']++;
            $status = strtoupper((string)($row['status'] ?? ''));
            if ($status === 'ACTIVE') $summary['active']++;
            if ($status === 'BROKEN') $summary['broken']++;
            if ($status === 'REPAIR') $summary['repair']++;
            if (in_array($status, ['BROKEN', 'REPAIR'], true)) $summary['issue']++;
            if (!empty($row['is_new_in_period'])) $summary['new_in_period']++;
            $summary['acquisition_value'] += (float)($row['acquisition_cost'] ?? 0);
            $summary['book_value'] += (float)($row['book_value'] ?? 0);
            $conditionTotal += (int)($row['condition_score'] ?? 0);
        }

        if ($summary['total'] > 0) {
            $summary['avg_condition'] = round($conditionTotal / $summary['total'], 1);
        }

        return $summary;
    }

    public function find_asset(int $id): ?array
    {
        if ($id <= 0 || !$this->table_ready()) {
            return null;
        }

        $this->build_asset_query(['id' => $id, 'status' => 'ALL'], $this->asset_select(), false);
        $row = $this->db->limit(1)->get()->row_array();
        if (!$row) {
            return null;
        }

        $decorated = $this->decorate_assets([$row]);
        return $decorated[0] ?? null;
    }

    public function asset_events(int $assetId, int $limit = 80): array
    {
        if ($assetId <= 0 || !$this->table_ready()) {
            return [];
        }

        return $this->db
            ->select('e.*, u.username AS created_by_name')
            ->from(self::TABLE_EVENT . ' e')
            ->join('auth_user u', 'u.id = e.created_by', 'left')
            ->where('e.asset_id', $assetId)
            ->order_by('e.event_date', 'DESC')
            ->order_by('e.id', 'DESC')
            ->limit(max(1, min($limit, 300)))
            ->get()
            ->result_array();
    }

    public function count_damage_reports(array $filters = []): int
    {
        if (!$this->table_ready()) {
            return 0;
        }

        $this->build_damage_report_query($filters, 'COUNT(DISTINCT ev.id) AS total');
        $row = $this->db->get()->row_array();
        return (int)($row['total'] ?? 0);
    }

    public function list_damage_reports(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        if (!$this->table_ready()) {
            return [];
        }

        $this->build_damage_report_query($filters, $this->damage_report_select());
        $rows = $this->db
            ->order_by('ev.event_date', 'DESC')
            ->order_by('ev.id', 'DESC')
            ->limit(max(1, $limit), max(0, $offset))
            ->get()
            ->result_array();

        return $this->decorate_damage_reports($rows);
    }

    public function find_damage_report(int $eventId): ?array
    {
        if ($eventId <= 0 || !$this->table_ready()) {
            return null;
        }

        $this->build_damage_report_query(['event_id' => $eventId], $this->damage_report_select());
        $row = $this->db->limit(1)->get()->row_array();
        if (!$row) {
            return null;
        }

        $rows = $this->decorate_damage_reports([$row]);
        $report = $rows[0] ?? null;
        if ($report) {
            $report['is_latest_event'] = $this->is_latest_asset_event((int)$report['asset_id'], (int)$report['event_id']);
        }

        return $report;
    }

    public function search_assets_for_damage(string $q, int $limit = 20, ?int $divisionScopeId = null): array
    {
        if (!$this->table_ready()) {
            return [];
        }

        $q = trim($q);
        if ($q === '') {
            return [];
        }

        $this->build_asset_query([
            'q' => $q,
            'status' => 'ALL',
            'division_scope_id' => $divisionScopeId ?? 0,
        ], $this->asset_select(), false);

        $rows = $this->db
            ->order_by("CASE WHEN a.status = 'ACTIVE' THEN 0 WHEN a.status IN ('BROKEN','REPAIR') THEN 1 ELSE 2 END", 'ASC', false)
            ->order_by('a.asset_code', 'ASC')
            ->limit(max(1, min($limit, 50)))
            ->get()
            ->result_array();

        $rows = $this->decorate_assets($rows);
        $out = [];
        foreach ($rows as $row) {
            $metaParts = array_filter([
                $row['category_name'] ?? null,
                $row['division_name'] ?? null,
                $row['current_location'] ?? null,
                !empty($row['serial_no']) ? 'SN ' . $row['serial_no'] : null,
            ]);
            $out[] = [
                'id' => (int)$row['id'],
                'asset_code' => (string)($row['asset_code'] ?? ''),
                'asset_name' => (string)($row['asset_name'] ?? ''),
                'category_name' => (string)($row['category_name'] ?? ''),
                'division_name' => (string)($row['division_name'] ?? ''),
                'current_location' => (string)($row['current_location'] ?? ''),
                'status' => (string)($row['status'] ?? ''),
                'status_label' => (string)($row['status_label'] ?? ''),
                'condition_score' => (int)($row['condition_score'] ?? 0),
                'book_value' => (float)($row['book_value'] ?? 0),
                'photo_path' => (string)($row['photo_path'] ?? ''),
                'label' => trim((string)($row['asset_code'] ?? '') . ' - ' . (string)($row['asset_name'] ?? '')),
                'meta' => implode(' | ', $metaParts),
            ];
        }

        return $out;
    }

    public function save_bulk(array $data, array $serialNumbers, ?array $photo, int $qty, int $userId): array
    {
        if (!$this->table_ready()) {
            return ['ok' => false, 'message' => 'Tabel aset belum siap. Jalankan SQL modul aset terlebih dahulu.'];
        }

        $qty = max(1, min(500, $qty));
        $serialNumbers = array_values($serialNumbers);
        $createdIds = [];

        $this->db->trans_begin();
        for ($i = 0; $i < $qty; $i++) {
            $row = $data;
            $row['asset_code'] = $this->next_asset_code((string)($data['acquisition_date'] ?? ''));
            $row['serial_no'] = $serialNumbers[$i] ?? ($data['serial_no'] ?? null);
            if (!empty($photo['photo_path'])) {
                $row['photo_path'] = $photo['photo_path'];
                $row['photo_mime'] = $photo['photo_mime'] ?? null;
            }
            $row['created_by'] = $userId > 0 ? $userId : null;
            $row['updated_by'] = $userId > 0 ? $userId : null;
            $row['created_at'] = date('Y-m-d H:i:s');
            $row['updated_at'] = date('Y-m-d H:i:s');
            $this->db->insert(self::TABLE_ASSET, $row);
            $assetId = (int)$this->db->insert_id();
            $createdIds[] = $assetId;
            $this->insert_event([
                'asset_id' => $assetId,
                'event_type' => 'ACQUIRE',
                'event_date' => (string)($row['acquisition_date'] ?? date('Y-m-d')),
                'to_status' => (string)($row['status'] ?? 'ACTIVE'),
                'to_division_id' => $row['division_id'] ?? null,
                'condition_score_after' => (int)($row['condition_score'] ?? 100),
                'amount' => (float)($row['acquisition_cost'] ?? 0),
                'reason' => 'Input aset bulk batch ' . (string)($row['batch_no'] ?? ''),
                'created_by' => $userId > 0 ? $userId : null,
            ]);
        }

        if (!$this->db->trans_status()) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal menyimpan aset.'];
        }

        $this->db->trans_commit();
        return ['ok' => true, 'ids' => $createdIds, 'count' => count($createdIds)];
    }

    public function update_asset(int $id, array $data, ?array $photo, int $userId): array
    {
        $existing = $this->find_asset($id);
        if (!$existing) {
            return ['ok' => false, 'message' => 'Aset tidak ditemukan.'];
        }

        if (!empty($photo['photo_path'])) {
            $data['photo_path'] = $photo['photo_path'];
            $data['photo_mime'] = $photo['photo_mime'] ?? null;
        }

        $data['updated_by'] = $userId > 0 ? $userId : null;
        $data['updated_at'] = date('Y-m-d H:i:s');

        $this->db->trans_begin();
        $this->db->where('id', $id)->update(self::TABLE_ASSET, $data);
        $this->insert_event([
            'asset_id' => $id,
            'event_type' => 'UPDATE',
            'event_date' => date('Y-m-d'),
            'from_status' => (string)($existing['status'] ?? ''),
            'to_status' => (string)($data['status'] ?? $existing['status'] ?? ''),
            'from_division_id' => $existing['division_id'] ?? null,
            'to_division_id' => $data['division_id'] ?? ($existing['division_id'] ?? null),
            'condition_score_before' => (int)($existing['condition_score'] ?? 0),
            'condition_score_after' => (int)($data['condition_score'] ?? ($existing['condition_score'] ?? 0)),
            'reason' => 'Update data aset',
            'created_by' => $userId > 0 ? $userId : null,
        ]);

        if (!$this->db->trans_status()) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal mengupdate aset.'];
        }
        $this->db->trans_commit();
        return ['ok' => true, 'id' => $id];
    }

    public function record_damage(int $assetId, array $payload, ?array $evidence, int $userId): array
    {
        $asset = $this->find_asset($assetId);
        if (!$asset) {
            return ['ok' => false, 'message' => 'Aset tidak ditemukan.'];
        }

        $toStatus = strtoupper((string)($payload['to_status'] ?? 'BROKEN'));
        if (!in_array($toStatus, ['BROKEN', 'REPAIR', 'LOST', 'RETIRED', 'DISPOSED'], true)) {
            $toStatus = 'BROKEN';
        }
        $eventType = $this->damage_event_type_from_status($toStatus);
        $conditionAfter = max(0, min(100, (int)($payload['condition_score_after'] ?? 0)));

        $this->db->trans_begin();
        $this->db->where('id', $assetId)->update(self::TABLE_ASSET, [
            'status' => $toStatus,
            'condition_score' => $conditionAfter,
            'updated_by' => $userId > 0 ? $userId : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->insert_event([
            'asset_id' => $assetId,
            'event_type' => $eventType,
            'event_date' => (string)($payload['event_date'] ?? date('Y-m-d')),
            'from_status' => (string)($asset['status'] ?? ''),
            'to_status' => $toStatus,
            'from_division_id' => $asset['division_id'] ?? null,
            'to_division_id' => $asset['division_id'] ?? null,
            'condition_score_before' => (int)($asset['condition_score'] ?? 0),
            'condition_score_after' => $conditionAfter,
            'amount' => (float)($payload['estimated_loss_amount'] ?? $asset['book_value'] ?? 0),
            'reason' => (string)($payload['reason'] ?? ''),
            'evidence_path' => $evidence['evidence_path'] ?? null,
            'evidence_mime' => $evidence['evidence_mime'] ?? null,
            'created_by' => $userId > 0 ? $userId : null,
        ]);

        if (!$this->db->trans_status()) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal menyimpan laporan kerusakan.'];
        }

        $this->db->trans_commit();
        return ['ok' => true, 'id' => $assetId];
    }

    public function update_damage_report(int $eventId, array $payload, ?array $evidence, int $userId): array
    {
        $report = $this->find_damage_report($eventId);
        if (!$report) {
            return ['ok' => false, 'message' => 'Laporan tidak ditemukan.'];
        }
        if (empty($report['is_latest_event'])) {
            return ['ok' => false, 'message' => 'Laporan ini tidak bisa diedit karena aset sudah memiliki event lebih baru. Buat laporan lanjutan agar audit trail tetap rapi.'];
        }

        $assetId = (int)$report['asset_id'];
        $toStatus = strtoupper((string)($payload['to_status'] ?? $report['event_to_status'] ?? 'BROKEN'));
        if (!in_array($toStatus, ['BROKEN', 'REPAIR', 'LOST', 'RETIRED', 'DISPOSED'], true)) {
            $toStatus = 'BROKEN';
        }
        $conditionAfter = max(0, min(100, (int)($payload['condition_score_after'] ?? 0)));

        $eventUpdate = [
            'event_type' => $this->damage_event_type_from_status($toStatus),
            'event_date' => (string)($payload['event_date'] ?? date('Y-m-d')),
            'to_status' => $toStatus,
            'condition_score_after' => $conditionAfter,
            'amount' => (float)($payload['estimated_loss_amount'] ?? 0),
            'reason' => (string)($payload['reason'] ?? ''),
        ];
        if (!empty($evidence['evidence_path'])) {
            $eventUpdate['evidence_path'] = $evidence['evidence_path'];
            $eventUpdate['evidence_mime'] = $evidence['evidence_mime'] ?? null;
        }

        $this->db->trans_begin();
        $this->db->where('id', $assetId)->update(self::TABLE_ASSET, [
            'status' => $toStatus,
            'condition_score' => $conditionAfter,
            'updated_by' => $userId > 0 ? $userId : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->where('id', $eventId)->update(self::TABLE_EVENT, $eventUpdate);

        if (!$this->db->trans_status()) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal mengupdate laporan kerusakan.'];
        }

        $this->db->trans_commit();
        return ['ok' => true, 'id' => $eventId, 'asset_id' => $assetId];
    }

    public function delete_damage_report(int $eventId, int $userId): array
    {
        $report = $this->find_damage_report($eventId);
        if (!$report) {
            return ['ok' => false, 'message' => 'Laporan tidak ditemukan.'];
        }
        if (empty($report['is_latest_event'])) {
            return ['ok' => false, 'message' => 'Laporan ini tidak bisa dihapus karena aset sudah memiliki event lebih baru.'];
        }

        $assetId = (int)$report['asset_id'];
        $fromStatus = strtoupper((string)($report['event_from_status'] ?? 'ACTIVE'));
        if (!isset($this->status_labels()[$fromStatus])) {
            $fromStatus = 'ACTIVE';
        }
        $conditionBefore = $report['condition_score_before'] === null
            ? (int)($report['condition_score'] ?? 100)
            : max(0, min(100, (int)$report['condition_score_before']));

        $this->db->trans_begin();
        $this->db->where('id', $assetId)->update(self::TABLE_ASSET, [
            'status' => $fromStatus,
            'condition_score' => $conditionBefore,
            'updated_by' => $userId > 0 ? $userId : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->where('id', $eventId)->delete(self::TABLE_EVENT);

        if (!$this->db->trans_status()) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal menghapus laporan kerusakan.'];
        }

        $this->db->trans_commit();
        return ['ok' => true, 'asset_id' => $assetId];
    }

    public function list_recons(array $filters = [], int $limit = 30): array
    {
        if (!$this->table_ready()) {
            return [];
        }

        $month = trim((string)($filters['month'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ALL')));
        $divisionId = (int)($filters['division_id'] ?? 0);

        $this->db
            ->select("
                r.*,
                d.name AS division_name,
                (SELECT COUNT(*) FROM asset_recon_line l WHERE l.recon_id = r.id) AS line_count,
                (SELECT COUNT(*) FROM asset_recon_line l WHERE l.recon_id = r.id AND l.physical_status <> 'NOT_CHECKED') AS checked_count,
                (SELECT COUNT(*) FROM asset_recon_line l WHERE l.recon_id = r.id AND l.physical_status IN ('BROKEN','MISSING','NEED_REPAIR')) AS issue_count
            ", false)
            ->from(self::TABLE_RECON . ' r')
            ->join('mst_operational_division d', 'd.id = r.division_id', 'left');

        if (preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->db->where('r.period_month', $month);
        }
        if (in_array($status, ['DRAFT', 'POSTED', 'CANCELLED'], true)) {
            $this->db->where('r.status', $status);
        }
        if ($divisionId > 0) {
            $this->db->where('r.division_id', $divisionId);
        }

        return $this->db
            ->order_by('r.period_month', 'DESC')
            ->order_by('r.id', 'DESC')
            ->limit(max(1, min($limit, 200)))
            ->get()
            ->result_array();
    }

    public function find_recon(int $id): ?array
    {
        if ($id <= 0 || !$this->table_ready()) {
            return null;
        }

        $row = $this->db
            ->select('r.*, d.name AS division_name')
            ->from(self::TABLE_RECON . ' r')
            ->join('mst_operational_division d', 'd.id = r.division_id', 'left')
            ->where('r.id', $id)
            ->limit(1)
            ->get()
            ->row_array();

        return $row ?: null;
    }

    public function generate_recon(string $month, ?int $divisionId, string $notes, int $userId): array
    {
        if (!$this->table_ready()) {
            return ['ok' => false, 'message' => 'Tabel aset belum siap. Jalankan SQL modul aset terlebih dahulu.'];
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            return ['ok' => false, 'message' => 'Periode rekon tidak valid.'];
        }

        $existing = $this->find_recon_by_scope($month, $divisionId);
        if ($existing) {
            return ['ok' => true, 'id' => (int)$existing['id'], 'message' => 'Rekon periode ini sudah ada.'];
        }

        $this->db->trans_begin();
        $this->db->insert(self::TABLE_RECON, [
            'recon_no' => $this->next_recon_no($month),
            'period_month' => $month,
            'division_id' => $divisionId && $divisionId > 0 ? $divisionId : null,
            'status' => 'DRAFT',
            'notes' => $notes,
            'created_by' => $userId > 0 ? $userId : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $reconId = (int)$this->db->insert_id();

        $this->build_recon_candidate_query($month, $divisionId, 'a.id, a.status');
        $assets = $this->db->order_by('a.asset_code', 'ASC')->get()->result_array();

        foreach ($assets as $asset) {
            $this->db->insert(self::TABLE_RECON_LINE, [
                'recon_id' => $reconId,
                'asset_id' => (int)$asset['id'],
                'expected_status' => (string)$asset['status'],
                'physical_status' => 'NOT_CHECKED',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if (!$this->db->trans_status()) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal generate rekon aset.'];
        }
        $this->db->trans_commit();
        return ['ok' => true, 'id' => $reconId, 'message' => 'Rekon aset berhasil dibuat.'];
    }

    public function recon_lines(int $reconId): array
    {
        if ($reconId <= 0 || !$this->table_ready()) {
            return [];
        }

        $rows = $this->db
            ->select("
                l.*,
                a.asset_code, a.asset_name, a.status AS asset_status, a.condition_score AS asset_condition_score,
                a.photo_path, a.acquisition_cost, a.residual_value, a.useful_life_months, a.depreciation_method, a.depreciation_start_month,
                c.category_name, d.name AS division_name, o.outlet_name, e.employee_name AS custodian_name
            ", false)
            ->from(self::TABLE_RECON_LINE . ' l')
            ->join(self::TABLE_ASSET . ' a', 'a.id = l.asset_id', 'inner')
            ->join(self::TABLE_CATEGORY . ' c', 'c.id = a.category_id', 'left')
            ->join('mst_operational_division d', 'd.id = a.division_id', 'left')
            ->join('pos_outlet o', 'o.id = a.outlet_id', 'left')
            ->join('org_employee e', 'e.id = a.custodian_employee_id', 'left')
            ->where('l.recon_id', $reconId)
            ->order_by('c.category_name', 'ASC')
            ->order_by('a.asset_code', 'ASC')
            ->get()
            ->result_array();

        return $this->decorate_assets($rows);
    }

    public function save_recon_lines(int $reconId, array $lines, int $userId): array
    {
        $recon = $this->find_recon($reconId);
        if (!$recon) {
            return ['ok' => false, 'message' => 'Rekon tidak ditemukan.'];
        }
        if (($recon['status'] ?? '') !== 'DRAFT') {
            return ['ok' => false, 'message' => 'Rekon yang sudah posting/cancel tidak bisa diedit.'];
        }

        $labels = $this->physical_status_labels();
        $this->db->trans_begin();
        foreach ($lines as $lineId => $payload) {
            $lineId = (int)$lineId;
            if ($lineId <= 0 || !is_array($payload)) {
                continue;
            }

            $status = strtoupper((string)($payload['physical_status'] ?? 'NOT_CHECKED'));
            if (!isset($labels[$status])) {
                $status = 'NOT_CHECKED';
            }
            $condition = $payload['condition_score'] === '' || $payload['condition_score'] === null
                ? null
                : max(0, min(100, (int)$payload['condition_score']));

            $update = [
                'physical_status' => $status,
                'condition_score' => $condition,
                'notes' => trim((string)($payload['notes'] ?? '')),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if (!empty($payload['evidence_path'])) {
                $update['evidence_path'] = $payload['evidence_path'];
                $update['evidence_mime'] = $payload['evidence_mime'] ?? null;
            }
            if ($status !== 'NOT_CHECKED' || trim((string)($payload['notes'] ?? '')) !== '' || !empty($payload['evidence_path'])) {
                $update['checked_by'] = $userId > 0 ? $userId : null;
                $update['checked_at'] = date('Y-m-d H:i:s');
            }

            $this->db
                ->where('id', $lineId)
                ->where('recon_id', $reconId)
                ->update(self::TABLE_RECON_LINE, $update);
        }

        if (!$this->db->trans_status()) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal menyimpan checklist rekon.'];
        }
        $this->db->trans_commit();
        return ['ok' => true];
    }

    public function post_recon(int $reconId, int $userId): array
    {
        $recon = $this->find_recon($reconId);
        if (!$recon) {
            return ['ok' => false, 'message' => 'Rekon tidak ditemukan.'];
        }
        if (($recon['status'] ?? '') !== 'DRAFT') {
            return ['ok' => false, 'message' => 'Rekon ini sudah tidak berstatus draft.'];
        }

        $lines = $this->recon_lines($reconId);
        $checked = 0;
        foreach ($lines as $line) {
            if (($line['physical_status'] ?? 'NOT_CHECKED') !== 'NOT_CHECKED') {
                $checked++;
            }
        }
        if ($checked <= 0) {
            return ['ok' => false, 'message' => 'Minimal satu aset harus dicek sebelum rekon diposting.'];
        }

        $this->db->trans_begin();
        foreach ($lines as $line) {
            $physical = strtoupper((string)($line['physical_status'] ?? 'NOT_CHECKED'));
            if ($physical === 'NOT_CHECKED' || $physical === 'EXTRA_FOUND') {
                continue;
            }

            $assetId = (int)($line['asset_id'] ?? 0);
            $fromStatus = strtoupper((string)($line['asset_status'] ?? ''));
            $toStatus = $fromStatus;
            if ($physical === 'OK' && in_array($fromStatus, ['BROKEN', 'REPAIR'], true)) {
                $toStatus = 'ACTIVE';
            } elseif ($physical === 'BROKEN') {
                $toStatus = 'BROKEN';
            } elseif ($physical === 'MISSING') {
                $toStatus = 'LOST';
            } elseif ($physical === 'NEED_REPAIR') {
                $toStatus = 'REPAIR';
            }

            $conditionAfter = $line['condition_score'] !== null
                ? max(0, min(100, (int)$line['condition_score']))
                : (int)($line['asset_condition_score'] ?? 100);

            if ($toStatus !== $fromStatus || (int)($line['asset_condition_score'] ?? 0) !== $conditionAfter) {
                $this->db->where('id', $assetId)->update(self::TABLE_ASSET, [
                    'status' => $toStatus,
                    'condition_score' => $conditionAfter,
                    'updated_by' => $userId > 0 ? $userId : null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $this->insert_event([
                'asset_id' => $assetId,
                'event_type' => $physical === 'MISSING' ? 'LOST' : 'RECON',
                'event_date' => date('Y-m-t', strtotime((string)$recon['period_month'] . '-01')),
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'condition_score_before' => (int)($line['asset_condition_score'] ?? 0),
                'condition_score_after' => $conditionAfter,
                'amount' => (float)($line['book_value'] ?? 0),
                'reason' => 'Rekon ' . (string)$recon['recon_no'] . ': ' . (string)($line['notes'] ?? ''),
                'evidence_path' => $line['evidence_path'] ?? null,
                'evidence_mime' => $line['evidence_mime'] ?? null,
                'created_by' => $userId > 0 ? $userId : null,
            ]);
        }

        $this->db->where('id', $reconId)->update(self::TABLE_RECON, [
            'status' => 'POSTED',
            'posted_by' => $userId > 0 ? $userId : null,
            'posted_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$this->db->trans_status()) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal posting rekon aset.'];
        }
        $this->db->trans_commit();
        return ['ok' => true];
    }

    public function cancel_recon(int $reconId, int $userId): array
    {
        $recon = $this->find_recon($reconId);
        if (!$recon) {
            return ['ok' => false, 'message' => 'Rekon tidak ditemukan.'];
        }
        if (($recon['status'] ?? '') !== 'DRAFT') {
            return ['ok' => false, 'message' => 'Hanya rekon draft yang bisa dibatalkan.'];
        }

        $ok = $this->db->where('id', $reconId)->update(self::TABLE_RECON, [
            'status' => 'CANCELLED',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return ['ok' => (bool)$ok];
    }

    public function count_workflows(string $type, array $filters = []): int
    {
        $type = strtoupper($type);
        if (!$this->extension_ready() || !isset($this->workflow_configs()[$type])) {
            return 0;
        }

        $this->build_workflow_query($type, $filters, 'COUNT(DISTINCT w.id) AS total');
        $row = $this->db->get()->row_array();
        return (int)($row['total'] ?? 0);
    }

    public function list_workflows(string $type, array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $type = strtoupper($type);
        if (!$this->extension_ready() || !isset($this->workflow_configs()[$type])) {
            return [];
        }

        $this->build_workflow_query($type, $filters, $this->workflow_select());
        return $this->db
            ->order_by('w.workflow_date', 'DESC')
            ->order_by('w.id', 'DESC')
            ->limit(max(1, $limit), max(0, $offset))
            ->get()
            ->result_array();
    }

    public function find_workflow(string $type, int $id): ?array
    {
        $type = strtoupper($type);
        if ($id <= 0 || !$this->extension_ready() || !isset($this->workflow_configs()[$type])) {
            return null;
        }

        $this->build_workflow_query($type, ['id' => $id], $this->workflow_select());
        $row = $this->db->limit(1)->get()->row_array();
        return $row ?: null;
    }

    public function create_workflow(string $type, array $payload, ?array $evidence, int $userId): array
    {
        $type = strtoupper($type);
        if (!$this->extension_ready() || !isset($this->workflow_configs()[$type])) {
            return ['ok' => false, 'message' => 'Tabel workflow aset belum siap. Jalankan SQL ekstensi aset terlebih dahulu.'];
        }

        $assetId = (int)($payload['asset_id'] ?? 0);
        $asset = $this->find_asset($assetId);
        if (!$asset) {
            return ['ok' => false, 'message' => 'Aset tidak ditemukan.'];
        }

        $date = $this->valid_date_value((string)($payload['workflow_date'] ?? '')) ?: date('Y-m-d');
        $row = [
            'workflow_type' => $type,
            'workflow_no' => $this->next_workflow_no($type, $date),
            'asset_id' => $assetId,
            'workflow_date' => $date,
            'due_date' => $this->valid_date_value((string)($payload['due_date'] ?? '')),
            'status' => 'PENDING',
            'from_division_id' => $asset['division_id'] ?? null,
            'from_outlet_id' => $asset['outlet_id'] ?? null,
            'from_location' => $asset['current_location'] ?? null,
            'from_employee_id' => $asset['custodian_employee_id'] ?? null,
            'to_division_id' => $this->positive_int_or_null($payload['to_division_id'] ?? null),
            'to_outlet_id' => $this->positive_int_or_null($payload['to_outlet_id'] ?? null),
            'to_location' => $this->null_if_blank((string)($payload['to_location'] ?? '')),
            'to_employee_id' => $this->positive_int_or_null($payload['to_employee_id'] ?? null),
            'maintenance_type' => $this->null_if_blank((string)($payload['maintenance_type'] ?? '')),
            'priority' => isset($this->priority_labels()[strtoupper((string)($payload['priority'] ?? ''))]) ? strtoupper((string)$payload['priority']) : 'NORMAL',
            'vendor_name' => $this->null_if_blank((string)($payload['vendor_name'] ?? '')),
            'disposal_type' => in_array(strtoupper((string)($payload['disposal_type'] ?? '')), ['RETIRED','DISPOSED','SOLD','DONATED'], true) ? strtoupper((string)$payload['disposal_type']) : null,
            'estimated_cost' => max(0, (float)($payload['estimated_cost'] ?? 0)),
            'actual_cost' => max(0, (float)($payload['actual_cost'] ?? 0)),
            'disposal_value' => max(0, (float)($payload['disposal_value'] ?? 0)),
            'reason' => $this->null_if_blank((string)($payload['reason'] ?? '')),
            'requested_by' => $userId > 0 ? $userId : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (!empty($evidence['evidence_path'])) {
            $row['evidence_path'] = $evidence['evidence_path'];
            $row['evidence_mime'] = $evidence['evidence_mime'] ?? null;
        }

        $this->db->insert(self::TABLE_WORKFLOW, $row);
        if (!$this->db->affected_rows()) {
            return ['ok' => false, 'message' => 'Gagal menyimpan workflow aset.'];
        }

        return ['ok' => true, 'id' => (int)$this->db->insert_id(), 'workflow_no' => $row['workflow_no']];
    }

    public function approve_workflow(string $type, int $id, int $userId): array
    {
        $workflow = $this->find_workflow($type, $id);
        if (!$workflow) {
            return ['ok' => false, 'message' => 'Workflow tidak ditemukan.'];
        }
        if (($workflow['status'] ?? '') !== 'PENDING') {
            return ['ok' => false, 'message' => 'Hanya workflow menunggu yang bisa disetujui.'];
        }

        $ok = $this->db->where('id', $id)->update(self::TABLE_WORKFLOW, [
            'status' => 'APPROVED',
            'approved_by' => $userId > 0 ? $userId : null,
            'approved_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return ['ok' => (bool)$ok];
    }

    public function reject_workflow(string $type, int $id, int $userId): array
    {
        $workflow = $this->find_workflow($type, $id);
        if (!$workflow) {
            return ['ok' => false, 'message' => 'Workflow tidak ditemukan.'];
        }
        if (!in_array(($workflow['status'] ?? ''), ['PENDING', 'APPROVED'], true)) {
            return ['ok' => false, 'message' => 'Workflow ini sudah tidak bisa ditolak.'];
        }

        $ok = $this->db->where('id', $id)->update(self::TABLE_WORKFLOW, [
            'status' => 'REJECTED',
            'approved_by' => $userId > 0 ? $userId : null,
            'approved_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return ['ok' => (bool)$ok];
    }

    public function cancel_workflow(string $type, int $id, int $userId): array
    {
        $workflow = $this->find_workflow($type, $id);
        if (!$workflow) {
            return ['ok' => false, 'message' => 'Workflow tidak ditemukan.'];
        }
        if (in_array(($workflow['status'] ?? ''), ['POSTED', 'DONE', 'CANCELLED'], true)) {
            return ['ok' => false, 'message' => 'Workflow ini sudah final.'];
        }

        $ok = $this->db->where('id', $id)->update(self::TABLE_WORKFLOW, [
            'status' => 'CANCELLED',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return ['ok' => (bool)$ok];
    }

    public function post_workflow(string $type, int $id, int $userId): array
    {
        $type = strtoupper($type);
        $workflow = $this->find_workflow($type, $id);
        if (!$workflow) {
            return ['ok' => false, 'message' => 'Workflow tidak ditemukan.'];
        }
        if (($workflow['status'] ?? '') !== 'APPROVED') {
            return ['ok' => false, 'message' => 'Workflow harus disetujui dulu sebelum posting.'];
        }

        $asset = $this->find_asset((int)$workflow['asset_id']);
        if (!$asset) {
            return ['ok' => false, 'message' => 'Aset tidak ditemukan.'];
        }

        $this->db->trans_begin();
        if ($type === 'TRANSFER') {
            $update = [
                'division_id' => $workflow['to_division_id'] ?: ($asset['division_id'] ?? null),
                'outlet_id' => $workflow['to_outlet_id'] ?: ($asset['outlet_id'] ?? null),
                'current_location' => $workflow['to_location'] ?: ($asset['current_location'] ?? null),
                'custodian_employee_id' => $workflow['to_employee_id'] ?: ($asset['custodian_employee_id'] ?? null),
                'updated_by' => $userId > 0 ? $userId : null,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            $this->db->where('id', (int)$asset['id'])->update(self::TABLE_ASSET, $update);
            $this->insert_event([
                'asset_id' => (int)$asset['id'],
                'event_type' => 'TRANSFER',
                'event_date' => (string)$workflow['workflow_date'],
                'from_status' => (string)($asset['status'] ?? ''),
                'to_status' => (string)($asset['status'] ?? ''),
                'from_division_id' => $asset['division_id'] ?? null,
                'to_division_id' => $update['division_id'],
                'condition_score_before' => (int)($asset['condition_score'] ?? 0),
                'condition_score_after' => (int)($asset['condition_score'] ?? 0),
                'reason' => 'Mutasi ' . (string)$workflow['workflow_no'] . ': ' . (string)($workflow['reason'] ?? ''),
                'created_by' => $userId > 0 ? $userId : null,
            ]);
        } elseif ($type === 'HANDOVER') {
            $this->db->where('id', (int)$asset['id'])->update(self::TABLE_ASSET, [
                'custodian_employee_id' => $workflow['to_employee_id'] ?: null,
                'updated_by' => $userId > 0 ? $userId : null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->insert_event([
                'asset_id' => (int)$asset['id'],
                'event_type' => 'TRANSFER',
                'event_date' => (string)$workflow['workflow_date'],
                'from_status' => (string)($asset['status'] ?? ''),
                'to_status' => (string)($asset['status'] ?? ''),
                'from_division_id' => $asset['division_id'] ?? null,
                'to_division_id' => $asset['division_id'] ?? null,
                'condition_score_before' => (int)($asset['condition_score'] ?? 0),
                'condition_score_after' => (int)($asset['condition_score'] ?? 0),
                'reason' => 'Serah terima ' . (string)$workflow['workflow_no'] . ': ' . (string)($workflow['reason'] ?? ''),
                'created_by' => $userId > 0 ? $userId : null,
            ]);
        } elseif ($type === 'DISPOSAL') {
            $toStatus = strtoupper((string)($workflow['disposal_type'] ?? 'DISPOSED')) === 'RETIRED' ? 'RETIRED' : 'DISPOSED';
            $eventType = $toStatus === 'RETIRED' ? 'RETIRED' : 'DISPOSED';
            $this->db->where('id', (int)$asset['id'])->update(self::TABLE_ASSET, [
                'status' => $toStatus,
                'updated_by' => $userId > 0 ? $userId : null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->insert_event([
                'asset_id' => (int)$asset['id'],
                'event_type' => $eventType,
                'event_date' => (string)$workflow['workflow_date'],
                'from_status' => (string)($asset['status'] ?? ''),
                'to_status' => $toStatus,
                'from_division_id' => $asset['division_id'] ?? null,
                'to_division_id' => $asset['division_id'] ?? null,
                'condition_score_before' => (int)($asset['condition_score'] ?? 0),
                'condition_score_after' => (int)($asset['condition_score'] ?? 0),
                'amount' => (float)($workflow['disposal_value'] ?? 0),
                'reason' => 'Disposal ' . (string)$workflow['workflow_no'] . ': ' . (string)($workflow['reason'] ?? ''),
                'evidence_path' => $workflow['evidence_path'] ?? null,
                'evidence_mime' => $workflow['evidence_mime'] ?? null,
                'created_by' => $userId > 0 ? $userId : null,
            ]);
        }

        $this->db->where('id', $id)->update(self::TABLE_WORKFLOW, [
            'status' => 'POSTED',
            'posted_by' => $userId > 0 ? $userId : null,
            'posted_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$this->db->trans_status()) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal posting workflow aset.'];
        }
        $this->db->trans_commit();
        return ['ok' => true];
    }

    public function complete_maintenance(int $id, float $actualCost, int $userId): array
    {
        $workflow = $this->find_workflow('MAINTENANCE', $id);
        if (!$workflow) {
            return ['ok' => false, 'message' => 'Maintenance tidak ditemukan.'];
        }
        if (!in_array(($workflow['status'] ?? ''), ['PENDING', 'APPROVED'], true)) {
            return ['ok' => false, 'message' => 'Maintenance ini sudah final.'];
        }
        $asset = $this->find_asset((int)$workflow['asset_id']);
        if (!$asset) {
            return ['ok' => false, 'message' => 'Aset tidak ditemukan.'];
        }

        $this->db->trans_begin();
        $this->db->where('id', $id)->update(self::TABLE_WORKFLOW, [
            'status' => 'DONE',
            'actual_cost' => max(0, $actualCost),
            'completed_by' => $userId > 0 ? $userId : null,
            'completed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->insert_event([
            'asset_id' => (int)$asset['id'],
            'event_type' => 'REPAIR',
            'event_date' => date('Y-m-d'),
            'from_status' => (string)($asset['status'] ?? ''),
            'to_status' => (string)($asset['status'] ?? ''),
            'from_division_id' => $asset['division_id'] ?? null,
            'to_division_id' => $asset['division_id'] ?? null,
            'condition_score_before' => (int)($asset['condition_score'] ?? 0),
            'condition_score_after' => (int)($asset['condition_score'] ?? 0),
            'amount' => max(0, $actualCost),
            'reason' => 'Maintenance ' . (string)$workflow['workflow_no'] . ': ' . (string)($workflow['reason'] ?? ''),
            'evidence_path' => $workflow['evidence_path'] ?? null,
            'evidence_mime' => $workflow['evidence_mime'] ?? null,
            'created_by' => $userId > 0 ? $userId : null,
        ]);

        if (!$this->db->trans_status()) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal menyelesaikan maintenance.'];
        }
        $this->db->trans_commit();
        return ['ok' => true];
    }

    public function depreciation_preview(string $month, ?int $divisionId = null, int $limit = 250): array
    {
        if (!$this->extension_ready() || !preg_match('/^\d{4}-\d{2}$/', $month)) {
            return [];
        }

        $this->build_depreciable_asset_query($month, $divisionId, $this->asset_select());
        $rows = $this->db
            ->order_by('c.category_name', 'ASC')
            ->order_by('a.asset_name', 'ASC')
            ->order_by('a.asset_code', 'ASC')
            ->limit(max(1, min($limit, 10000)))
            ->get()
            ->result_array();

        $out = [];
        foreach ($this->decorate_assets($rows) as $row) {
            $line = $this->depreciation_line_from_asset($row, $month);
            if ($line['depreciation_amount'] > 0) {
                $out[] = array_merge($row, $line);
            }
        }
        return $out;
    }

    public function depreciation_summary(string $month, ?int $divisionId = null): array
    {
        $rows = $this->depreciation_preview($month, $divisionId, 10000);
        $summary = ['total_assets' => 0, 'total_depreciation' => 0.0, 'book_value_before' => 0.0, 'book_value_after' => 0.0];
        foreach ($rows as $row) {
            $summary['total_assets']++;
            $summary['total_depreciation'] += (float)$row['depreciation_amount'];
            $summary['book_value_before'] += (float)$row['book_value_before'];
            $summary['book_value_after'] += (float)$row['book_value_after'];
        }
        return $summary;
    }

    public function list_depreciation_runs(array $filters = [], int $limit = 25): array
    {
        if (!$this->extension_ready()) {
            return [];
        }

        $month = trim((string)($filters['month'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ALL')));
        $divisionId = (int)($filters['division_id'] ?? 0);

        $this->db
            ->select('r.*, d.name AS division_name')
            ->from(self::TABLE_DEP_RUN . ' r')
            ->join('mst_operational_division d', 'd.id = r.division_id', 'left');
        if (preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->db->where('r.period_month', $month);
        }
        if (in_array($status, ['DRAFT','POSTED','CANCELLED'], true)) {
            $this->db->where('r.status', $status);
        }
        if ($divisionId > 0) {
            $this->db->where('r.division_id', $divisionId);
        }

        return $this->db
            ->order_by('r.period_month', 'DESC')
            ->order_by('r.id', 'DESC')
            ->limit(max(1, min($limit, 100)))
            ->get()
            ->result_array();
    }

    public function create_depreciation_run(string $month, ?int $divisionId, string $notes, int $userId): array
    {
        if (!$this->extension_ready()) {
            return ['ok' => false, 'message' => 'Tabel penyusutan aset belum siap.'];
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            return ['ok' => false, 'message' => 'Periode tidak valid.'];
        }

        $existing = $this->find_depreciation_run_by_scope($month, $divisionId);
        if ($existing && ($existing['status'] ?? '') !== 'CANCELLED') {
            return ['ok' => true, 'id' => (int)$existing['id'], 'message' => 'Run penyusutan periode ini sudah ada.'];
        }

        $lines = $this->depreciation_preview($month, $divisionId, 10000);
        if (empty($lines)) {
            return ['ok' => false, 'message' => 'Tidak ada aset dengan penyusutan pada periode ini.'];
        }

        $summary = $this->depreciation_summary($month, $divisionId);
        $this->db->trans_begin();
        if ($existing && ($existing['status'] ?? '') === 'CANCELLED') {
            $runId = (int)$existing['id'];
            $this->db->where('run_id', $runId)->delete(self::TABLE_DEP_LINE);
            $this->db->where('id', $runId)->update(self::TABLE_DEP_RUN, [
                'status' => 'DRAFT',
                'total_assets' => (int)$summary['total_assets'],
                'total_depreciation' => (float)$summary['total_depreciation'],
                'notes' => $notes,
                'created_by' => $userId > 0 ? $userId : null,
                'posted_by' => null,
                'posted_at' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $this->db->insert(self::TABLE_DEP_RUN, [
                'run_no' => $this->next_depreciation_run_no($month),
                'period_month' => $month,
                'division_id' => $divisionId && $divisionId > 0 ? $divisionId : null,
                'status' => 'DRAFT',
                'total_assets' => (int)$summary['total_assets'],
                'total_depreciation' => (float)$summary['total_depreciation'],
                'notes' => $notes,
                'created_by' => $userId > 0 ? $userId : null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $runId = (int)$this->db->insert_id();
        }

        foreach ($lines as $line) {
            $this->db->insert(self::TABLE_DEP_LINE, [
                'run_id' => $runId,
                'asset_id' => (int)$line['id'],
                'acquisition_cost' => (float)($line['acquisition_cost'] ?? 0),
                'book_value_before' => (float)$line['book_value_before'],
                'depreciation_amount' => (float)$line['depreciation_amount'],
                'book_value_after' => (float)$line['book_value_after'],
                'expense_account_code' => 'DEP-EXP-ASSET',
                'accumulated_account_code' => 'ACC-DEP-ASSET',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if (!$this->db->trans_status()) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => 'Gagal membuat staging jurnal penyusutan.'];
        }
        $this->db->trans_commit();
        return ['ok' => true, 'id' => $runId, 'message' => 'Staging jurnal penyusutan berhasil dibuat.'];
    }

    public function post_depreciation_run(int $runId, int $userId): array
    {
        if (!$this->extension_ready()) {
            return ['ok' => false, 'message' => 'Tabel penyusutan aset belum siap.'];
        }
        $row = $this->db->get_where(self::TABLE_DEP_RUN, ['id' => $runId])->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Run penyusutan tidak ditemukan.'];
        }
        if (($row['status'] ?? '') !== 'DRAFT') {
            return ['ok' => false, 'message' => 'Hanya draft yang bisa diposting.'];
        }

        $ok = $this->db->where('id', $runId)->update(self::TABLE_DEP_RUN, [
            'status' => 'POSTED',
            'posted_by' => $userId > 0 ? $userId : null,
            'posted_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return ['ok' => (bool)$ok];
    }

    public function cancel_depreciation_run(int $runId): array
    {
        if (!$this->extension_ready()) {
            return ['ok' => false, 'message' => 'Tabel penyusutan aset belum siap.'];
        }
        $row = $this->db->get_where(self::TABLE_DEP_RUN, ['id' => $runId])->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Run penyusutan tidak ditemukan.'];
        }
        if (($row['status'] ?? '') !== 'DRAFT') {
            return ['ok' => false, 'message' => 'Hanya draft yang bisa dibatalkan.'];
        }

        $ok = $this->db->where('id', $runId)->update(self::TABLE_DEP_RUN, [
            'status' => 'CANCELLED',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return ['ok' => (bool)$ok];
    }

    public function next_asset_code(string $date = ''): string
    {
        $ts = strtotime($date) ?: time();
        $prefix = 'AST-' . date('Ymd', $ts) . '-';
        $row = $this->db
            ->select('asset_code')
            ->from(self::TABLE_ASSET)
            ->like('asset_code', $prefix, 'after')
            ->order_by('asset_code', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();

        $last = 0;
        if (!empty($row['asset_code']) && preg_match('/-(\d+)$/', (string)$row['asset_code'], $m)) {
            $last = (int)$m[1];
        }

        return $prefix . str_pad((string)($last + 1), 4, '0', STR_PAD_LEFT);
    }

    private function next_recon_no(string $month): string
    {
        $prefix = 'AREC-' . str_replace('-', '', $month) . '-';
        $row = $this->db
            ->select('recon_no')
            ->from(self::TABLE_RECON)
            ->like('recon_no', $prefix, 'after')
            ->order_by('recon_no', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();

        $last = 0;
        if (!empty($row['recon_no']) && preg_match('/-(\d+)$/', (string)$row['recon_no'], $m)) {
            $last = (int)$m[1];
        }

        return $prefix . str_pad((string)($last + 1), 3, '0', STR_PAD_LEFT);
    }

    private function find_recon_by_scope(string $month, ?int $divisionId): ?array
    {
        $this->db
            ->from(self::TABLE_RECON)
            ->where('period_month', $month);
        if ($divisionId !== null && $divisionId > 0) {
            $this->db->where('division_id', $divisionId);
        } else {
            $this->db->where('division_id IS NULL', null, false);
        }

        $row = $this->db->limit(1)->get()->row_array();
        return $row ?: null;
    }

    private function asset_select(): string
    {
        return "
            a.*,
            " . $this->asset_group_key_expr() . " AS group_key,
            c.category_name, c.category_code,
            d.name AS division_name,
            o.outlet_name,
            e.employee_name AS custodian_name
        ";
    }

    private function asset_group_key_expr(): string
    {
        return "LOWER(SHA1(CONCAT_WS('|',
            COALESCE(CAST(a.category_id AS CHAR), '0'),
            COALESCE(NULLIF(TRIM(LOWER(a.asset_name)), ''), '-'),
            COALESCE(NULLIF(TRIM(LOWER(a.brand)), ''), '-'),
            COALESCE(NULLIF(TRIM(LOWER(a.model_name)), ''), '-')
        )))";
    }

    private function asset_group_select(): string
    {
        return "
            " . $this->asset_group_key_expr() . " AS group_key,
            MIN(a.id) AS first_asset_id,
            MIN(a.asset_code) AS sample_asset_code,
            MIN(a.asset_name) AS asset_name,
            MIN(a.category_id) AS category_id,
            MIN(c.category_name) AS category_name,
            MIN(c.category_code) AS category_code,
            MIN(a.brand) AS brand,
            MIN(a.model_name) AS model_name,
            MIN(NULLIF(a.photo_path, '')) AS photo_path,
            COUNT(*) AS unit_count,
            SUM(CASE WHEN a.status = 'ACTIVE' THEN 1 ELSE 0 END) AS active_count,
            SUM(CASE WHEN a.status = 'BROKEN' THEN 1 ELSE 0 END) AS broken_count,
            SUM(CASE WHEN a.status = 'REPAIR' THEN 1 ELSE 0 END) AS repair_count,
            SUM(CASE WHEN a.status = 'LOST' THEN 1 ELSE 0 END) AS lost_count,
            SUM(CASE WHEN a.status IN ('RETIRED','DISPOSED') THEN 1 ELSE 0 END) AS retired_count,
            SUM(a.acquisition_cost) AS acquisition_value,
            AVG(a.condition_score) AS avg_condition,
            MIN(a.acquisition_date) AS first_acquisition_date,
            MAX(COALESCE(a.updated_at, a.created_at)) AS last_activity_at,
            COUNT(DISTINCT a.division_id) AS division_count,
            GROUP_CONCAT(DISTINCT d.name ORDER BY d.name SEPARATOR ', ') AS division_names,
            GROUP_CONCAT(DISTINCT NULLIF(a.current_location, '') ORDER BY a.current_location SEPARATOR ', ') AS locations
        ";
    }

    private function damage_report_select(): string
    {
        return "
            ev.id AS event_id,
            ev.asset_id,
            ev.event_type,
            ev.event_date,
            ev.from_status AS event_from_status,
            ev.to_status AS event_to_status,
            ev.condition_score_before,
            ev.condition_score_after,
            ev.amount,
            ev.reason,
            ev.evidence_path,
            ev.evidence_mime,
            ev.created_at AS event_created_at,
            a.id,
            a.asset_code,
            a.asset_name,
            a.category_id,
            a.brand,
            a.model_name,
            a.serial_no,
            a.batch_no,
            a.purchase_date,
            a.acquisition_date,
            a.acquisition_cost,
            a.residual_value,
            a.useful_life_months,
            a.depreciation_method,
            a.depreciation_start_month,
            a.division_id,
            a.outlet_id,
            a.current_location,
            a.custodian_employee_id,
            a.status,
            a.condition_score,
            a.photo_path,
            a.photo_mime,
            c.category_name,
            c.category_code,
            d.name AS division_name,
            o.outlet_name,
            emp.employee_name AS custodian_name,
            u.username AS created_by_name
        ";
    }

    private function build_damage_report_query(array $filters, string $select): void
    {
        $this->db
            ->select($select, false)
            ->from(self::TABLE_EVENT . ' ev')
            ->join(self::TABLE_ASSET . ' a', 'a.id = ev.asset_id', 'inner')
            ->join(self::TABLE_CATEGORY . ' c', 'c.id = a.category_id', 'left')
            ->join('mst_operational_division d', 'd.id = a.division_id', 'left')
            ->join('pos_outlet o', 'o.id = a.outlet_id', 'left')
            ->join('org_employee emp', 'emp.id = a.custodian_employee_id', 'left')
            ->join('auth_user u', 'u.id = ev.created_by', 'left');

        $eventId = (int)($filters['event_id'] ?? 0);
        if ($eventId > 0) {
            $this->db->where('ev.id', $eventId);
        }

        $eventTypes = array_keys($this->damage_event_type_labels());
        $eventType = strtoupper(trim((string)($filters['event_type'] ?? 'ALL')));
        if ($eventType !== 'ALL' && in_array($eventType, $eventTypes, true)) {
            $this->db->where('ev.event_type', $eventType);
        } else {
            $this->db->where_in('ev.event_type', $eventTypes);
        }

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $this->db->group_start()
                ->like('a.asset_code', $q)
                ->or_like('a.asset_name', $q)
                ->or_like('a.brand', $q)
                ->or_like('a.model_name', $q)
                ->or_like('a.serial_no', $q)
                ->or_like('a.batch_no', $q)
                ->or_like('a.current_location', $q)
                ->or_like('c.category_name', $q)
                ->or_like('d.name', $q)
                ->or_like('emp.employee_name', $q)
                ->or_like('ev.reason', $q)
                ->group_end();
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $this->db->where('ev.event_date >=', $dateFrom);
        }

        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $this->db->where('ev.event_date <=', $dateTo);
        }

        $categoryId = (int)($filters['category_id'] ?? 0);
        if ($categoryId > 0) {
            $this->db->where('a.category_id', $categoryId);
        }

        $divisionId = (int)($filters['division_id'] ?? 0);
        if ($divisionId > 0) {
            $this->db->where('a.division_id', $divisionId);
        }

        $divisionScopeId = (int)($filters['division_scope_id'] ?? 0);
        if ($divisionScopeId > 0) {
            $this->db->where('a.division_id', $divisionScopeId);
        }
    }

    private function build_recon_candidate_query(string $month, ?int $divisionId, string $select): void
    {
        $endDate = date('Y-m-t', strtotime($month . '-01'));
        $this->db
            ->select($select, false)
            ->from(self::TABLE_ASSET . ' a')
            ->join(self::TABLE_CATEGORY . ' c', 'c.id = a.category_id', 'left')
            ->join('mst_operational_division d', 'd.id = a.division_id', 'left')
            ->join('pos_outlet o', 'o.id = a.outlet_id', 'left')
            ->join('org_employee e', 'e.id = a.custodian_employee_id', 'left')
            ->where_in('a.status', ['ACTIVE', 'BROKEN', 'REPAIR'])
            ->group_start()
                ->where('a.acquisition_date IS NULL', null, false)
                ->or_where('a.acquisition_date <=', $endDate)
            ->group_end();

        if ($divisionId !== null && $divisionId > 0) {
            $this->db->where('a.division_id', $divisionId);
        }
    }

    private function build_asset_query(array $filters, string $select, bool $group = false): void
    {
        $this->db
            ->select($select, false)
            ->from(self::TABLE_ASSET . ' a')
            ->join(self::TABLE_CATEGORY . ' c', 'c.id = a.category_id', 'left')
            ->join('mst_operational_division d', 'd.id = a.division_id', 'left')
            ->join('pos_outlet o', 'o.id = a.outlet_id', 'left')
            ->join('org_employee e', 'e.id = a.custodian_employee_id', 'left');

        $id = (int)($filters['id'] ?? 0);
        if ($id > 0) {
            $this->db->where('a.id', $id);
        }

        $groupKey = strtolower(trim((string)($filters['group_key'] ?? '')));
        if (preg_match('/^[a-f0-9]{40}$/', $groupKey)) {
            $this->db->where($this->asset_group_key_expr() . ' = ' . $this->db->escape($groupKey), null, false);
        }

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $this->db->group_start()
                ->like('a.asset_code', $q)
                ->or_like('a.asset_name', $q)
                ->or_like('a.brand', $q)
                ->or_like('a.model_name', $q)
                ->or_like('a.serial_no', $q)
                ->or_like('a.batch_no', $q)
                ->or_like('a.current_location', $q)
                ->or_like('c.category_name', $q)
                ->or_like('e.employee_name', $q)
                ->group_end();
        }

        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        if ($status !== 'ALL' && $status !== '') {
            if ($status === 'ISSUE') {
                $this->db->where_in('a.status', ['BROKEN', 'REPAIR', 'LOST']);
            } elseif (isset($this->status_labels()[$status])) {
                $this->db->where('a.status', $status);
            }
        }

        $categoryId = (int)($filters['category_id'] ?? 0);
        if ($categoryId > 0) {
            $this->db->where('a.category_id', $categoryId);
        }

        $divisionId = (int)($filters['division_id'] ?? 0);
        if ($divisionId > 0) {
            $this->db->where('a.division_id', $divisionId);
        }

        $divisionScopeId = (int)($filters['division_scope_id'] ?? 0);
        if ($divisionScopeId > 0) {
            $this->db->where('a.division_id', $divisionScopeId);
        }

        if ($group) {
            $this->db->group_by('a.id');
        }
    }

    private function decorate_assets(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['book_value'] = $this->calculate_book_value($row);
            $row['depreciation_percent'] = $this->calculate_depreciation_percent($row);
            $row['status_label'] = $this->status_labels()[strtoupper((string)($row['status'] ?? ''))] ?? (string)($row['status'] ?? '-');
            $row['physical_status_label'] = $this->physical_status_labels()[strtoupper((string)($row['physical_status'] ?? ''))] ?? (string)($row['physical_status'] ?? '-');
        }
        unset($row);
        return $rows;
    }

    private function decorate_asset_groups(array $rows, array $filters): array
    {
        foreach ($rows as &$row) {
            $groupFilters = $filters;
            $groupFilters['group_key'] = (string)($row['group_key'] ?? '');
            $units = $this->list_assets($groupFilters, 10000, 0);

            $bookValue = 0.0;
            $depreciationTotal = 0.0;
            $depreciationRows = 0;
            foreach ($units as $unit) {
                $bookValue += (float)($unit['book_value'] ?? 0);
                $depreciationTotal += (float)($unit['depreciation_percent'] ?? 0);
                $depreciationRows++;
            }

            $row['unit_count'] = (int)($row['unit_count'] ?? count($units));
            $row['active_count'] = (int)($row['active_count'] ?? 0);
            $row['broken_count'] = (int)($row['broken_count'] ?? 0);
            $row['repair_count'] = (int)($row['repair_count'] ?? 0);
            $row['lost_count'] = (int)($row['lost_count'] ?? 0);
            $row['retired_count'] = (int)($row['retired_count'] ?? 0);
            $row['issue_count'] = $row['broken_count'] + $row['repair_count'] + $row['lost_count'];
            $row['acquisition_value'] = (float)($row['acquisition_value'] ?? 0);
            $row['book_value'] = round($bookValue, 2);
            $row['avg_condition'] = round((float)($row['avg_condition'] ?? 0), 1);
            $row['avg_depreciation_percent'] = $depreciationRows > 0 ? round($depreciationTotal / $depreciationRows, 1) : 0.0;
        }
        unset($row);

        return $rows;
    }

    private function decorate_damage_reports(array $rows): array
    {
        $rows = $this->decorate_assets($rows);
        $eventLabels = $this->damage_event_type_labels();
        $statusLabels = $this->status_labels();

        foreach ($rows as &$row) {
            $eventType = strtoupper((string)($row['event_type'] ?? ''));
            $fromStatus = strtoupper((string)($row['event_from_status'] ?? ''));
            $toStatus = strtoupper((string)($row['event_to_status'] ?? ''));

            $row['event_type_label'] = $eventLabels[$eventType] ?? (string)($row['event_type'] ?? '-');
            $row['event_from_status_label'] = $fromStatus !== '' ? ($statusLabels[$fromStatus] ?? $fromStatus) : '-';
            $row['event_to_status_label'] = $toStatus !== '' ? ($statusLabels[$toStatus] ?? $toStatus) : '-';
        }
        unset($row);

        return $rows;
    }

    private function calculate_book_value(array $row, ?string $asOfMonth = null): float
    {
        $cost = (float)($row['acquisition_cost'] ?? 0);
        $residual = max(0.0, (float)($row['residual_value'] ?? 0));
        $life = (int)($row['useful_life_months'] ?? 0);
        $method = strtoupper((string)($row['depreciation_method'] ?? 'NONE'));
        if ($cost <= 0 || $method !== 'STRAIGHT_LINE' || $life <= 0) {
            return $cost;
        }

        $startMonth = trim((string)($row['depreciation_start_month'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}$/', $startMonth)) {
            $sourceDate = (string)($row['acquisition_date'] ?? $row['purchase_date'] ?? date('Y-m-d'));
            $startMonth = date('Y-m', strtotime($sourceDate) ?: time());
        }
        $endMonth = $asOfMonth && preg_match('/^\d{4}-\d{2}$/', $asOfMonth) ? $asOfMonth : date('Y-m');
        $start = new DateTime($startMonth . '-01');
        $end = new DateTime($endMonth . '-01');
        if ($end < $start) {
            return $cost;
        }

        $elapsed = ((int)$start->diff($end)->format('%y') * 12) + (int)$start->diff($end)->format('%m') + 1;
        $elapsed = max(0, min($elapsed, $life));
        $depreciable = max(0.0, $cost - $residual);
        $book = $cost - (($depreciable / $life) * $elapsed);
        return round(max($residual, $book), 2);
    }

    private function calculate_depreciation_percent(array $row): float
    {
        $cost = (float)($row['acquisition_cost'] ?? 0);
        if ($cost <= 0) {
            return 0.0;
        }
        $book = $this->calculate_book_value($row);
        return round(max(0, min(100, (($cost - $book) / $cost) * 100)), 1);
    }

    private function damage_event_type_from_status(string $status): string
    {
        $status = strtoupper($status);
        if ($status === 'LOST') {
            return 'LOST';
        }
        if ($status === 'REPAIR') {
            return 'REPAIR';
        }
        if ($status === 'RETIRED') {
            return 'RETIRED';
        }
        if ($status === 'DISPOSED') {
            return 'DISPOSED';
        }
        return 'DAMAGE';
    }

    private function is_latest_asset_event(int $assetId, int $eventId): bool
    {
        if ($assetId <= 0 || $eventId <= 0) {
            return false;
        }

        $event = $this->db
            ->select('id, event_date')
            ->from(self::TABLE_EVENT)
            ->where('id', $eventId)
            ->where('asset_id', $assetId)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$event) {
            return false;
        }

        $hasNewer = (bool)$this->db
            ->select('1')
            ->from(self::TABLE_EVENT)
            ->where('asset_id', $assetId)
            ->group_start()
                ->where('event_date >', (string)$event['event_date'])
                ->or_group_start()
                    ->where('event_date', (string)$event['event_date'])
                    ->where('id >', (int)$event['id'])
                ->group_end()
            ->group_end()
            ->limit(1)
            ->get()
            ->num_rows();

        return !$hasNewer;
    }

    private function workflow_select(): string
    {
        return "
            w.*,
            a.asset_code, a.asset_name, a.status AS asset_status, a.photo_path, a.condition_score AS asset_condition_score,
            c.category_name,
            fd.name AS from_division_name,
            td.name AS to_division_name,
            fo.outlet_name AS from_outlet_name,
            tor.outlet_name AS to_outlet_name,
            fe.employee_name AS from_employee_name,
            te.employee_name AS to_employee_name,
            req.username AS requested_by_name,
            appr.username AS approved_by_name,
            postu.username AS posted_by_name,
            comp.username AS completed_by_name
        ";
    }

    private function build_workflow_query(string $type, array $filters, string $select): void
    {
        $this->db
            ->select($select, false)
            ->from(self::TABLE_WORKFLOW . ' w')
            ->join(self::TABLE_ASSET . ' a', 'a.id = w.asset_id', 'inner')
            ->join(self::TABLE_CATEGORY . ' c', 'c.id = a.category_id', 'left')
            ->join('mst_operational_division fd', 'fd.id = w.from_division_id', 'left')
            ->join('mst_operational_division td', 'td.id = w.to_division_id', 'left')
            ->join('pos_outlet fo', 'fo.id = w.from_outlet_id', 'left')
            ->join('pos_outlet tor', 'tor.id = w.to_outlet_id', 'left')
            ->join('org_employee fe', 'fe.id = w.from_employee_id', 'left')
            ->join('org_employee te', 'te.id = w.to_employee_id', 'left')
            ->join('auth_user req', 'req.id = w.requested_by', 'left')
            ->join('auth_user appr', 'appr.id = w.approved_by', 'left')
            ->join('auth_user postu', 'postu.id = w.posted_by', 'left')
            ->join('auth_user comp', 'comp.id = w.completed_by', 'left')
            ->where('w.workflow_type', $type);

        $id = (int)($filters['id'] ?? 0);
        if ($id > 0) {
            $this->db->where('w.id', $id);
        }

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $this->db->group_start()
                ->like('w.workflow_no', $q)
                ->or_like('a.asset_code', $q)
                ->or_like('a.asset_name', $q)
                ->or_like('c.category_name', $q)
                ->or_like('w.reason', $q)
                ->or_like('w.vendor_name', $q)
                ->or_like('w.to_location', $q)
                ->or_like('te.employee_name', $q)
                ->group_end();
        }

        $status = strtoupper(trim((string)($filters['status'] ?? 'ALL')));
        if ($status !== 'ALL' && isset($this->workflow_status_labels()[$status])) {
            $this->db->where('w.status', $status);
        }

        $dateFrom = $this->valid_date_value((string)($filters['date_from'] ?? ''));
        if ($dateFrom) {
            $this->db->where('w.workflow_date >=', $dateFrom);
        }
        $dateTo = $this->valid_date_value((string)($filters['date_to'] ?? ''));
        if ($dateTo) {
            $this->db->where('w.workflow_date <=', $dateTo);
        }

        $divisionId = (int)($filters['division_id'] ?? 0);
        if ($divisionId > 0) {
            $this->db->group_start()
                ->where('w.from_division_id', $divisionId)
                ->or_where('w.to_division_id', $divisionId)
                ->or_where('a.division_id', $divisionId)
                ->group_end();
        }
    }

    private function next_workflow_no(string $type, string $date): string
    {
        $prefixMap = [
            'TRANSFER' => 'AMT',
            'HANDOVER' => 'AHO',
            'MAINTENANCE' => 'AMN',
            'DISPOSAL' => 'ADS',
        ];
        $prefix = ($prefixMap[$type] ?? 'AWF') . '-' . date('Ymd', strtotime($date) ?: time()) . '-';
        $row = $this->db
            ->select('workflow_no')
            ->from(self::TABLE_WORKFLOW)
            ->like('workflow_no', $prefix, 'after')
            ->order_by('workflow_no', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();
        $last = 0;
        if (!empty($row['workflow_no']) && preg_match('/-(\d+)$/', (string)$row['workflow_no'], $m)) {
            $last = (int)$m[1];
        }
        return $prefix . str_pad((string)($last + 1), 4, '0', STR_PAD_LEFT);
    }

    private function build_depreciable_asset_query(string $month, ?int $divisionId, string $select): void
    {
        $endDate = date('Y-m-t', strtotime($month . '-01'));
        $this->db
            ->select($select, false)
            ->from(self::TABLE_ASSET . ' a')
            ->join(self::TABLE_CATEGORY . ' c', 'c.id = a.category_id', 'left')
            ->join('mst_operational_division d', 'd.id = a.division_id', 'left')
            ->join('pos_outlet o', 'o.id = a.outlet_id', 'left')
            ->join('org_employee e', 'e.id = a.custodian_employee_id', 'left')
            ->where_in('a.status', ['ACTIVE', 'BROKEN', 'REPAIR'])
            ->where('a.depreciation_method', 'STRAIGHT_LINE')
            ->where('a.useful_life_months >', 0)
            ->group_start()
                ->where('a.acquisition_date IS NULL', null, false)
                ->or_where('a.acquisition_date <=', $endDate)
            ->group_end();
        if ($divisionId !== null && $divisionId > 0) {
            $this->db->where('a.division_id', $divisionId);
        }
    }

    private function depreciation_line_from_asset(array $asset, string $month): array
    {
        $prevMonth = date('Y-m', strtotime($month . '-01 -1 month'));
        $before = $this->calculate_book_value($asset, $prevMonth);
        $after = $this->calculate_book_value($asset, $month);
        $amount = round(max(0.0, $before - $after), 2);

        return [
            'book_value_before' => round($before, 2),
            'depreciation_amount' => $amount,
            'book_value_after' => round($after, 2),
        ];
    }

    private function find_depreciation_run_by_scope(string $month, ?int $divisionId): ?array
    {
        $this->db
            ->from(self::TABLE_DEP_RUN)
            ->where('period_month', $month);
        if ($divisionId !== null && $divisionId > 0) {
            $this->db->where('division_id', $divisionId);
        } else {
            $this->db->where('division_id IS NULL', null, false);
        }

        $row = $this->db->limit(1)->get()->row_array();
        return $row ?: null;
    }

    private function next_depreciation_run_no(string $month): string
    {
        $prefix = 'ADP-' . str_replace('-', '', $month) . '-';
        $row = $this->db
            ->select('run_no')
            ->from(self::TABLE_DEP_RUN)
            ->like('run_no', $prefix, 'after')
            ->order_by('run_no', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();
        $last = 0;
        if (!empty($row['run_no']) && preg_match('/-(\d+)$/', (string)$row['run_no'], $m)) {
            $last = (int)$m[1];
        }
        return $prefix . str_pad((string)($last + 1), 3, '0', STR_PAD_LEFT);
    }

    private function positive_int_or_null($value): ?int
    {
        $int = (int)$value;
        return $int > 0 ? $int : null;
    }

    private function valid_date_value(string $value): ?string
    {
        $value = trim($value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function null_if_blank(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function insert_event(array $data): void
    {
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
        $this->db->insert(self::TABLE_EVENT, $data);
    }
}
