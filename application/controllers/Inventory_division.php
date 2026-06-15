<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/Purchase.php';

class Inventory_division extends Purchase
{
    private const PAGE_OPNAME = 'inventory.stock.opname.division.index';

    public function index()     { parent::stock_division_index(); }
    public function opening()   { parent::stock_opening_division_index(); }
    public function adjustment(){ parent::stock_adjustment_division_index(); }
    public function daily()     { parent::stock_division_daily_index(); }
    public function compare()   { parent::stock_division_reconcile_index(); }
    public function reconcile_audit()  { parent::stock_division_reconcile_audit(); }
    public function reconcile_repair()     { parent::stock_division_reconcile_repair(); }
    public function reconcile_lot_repair() { parent::stock_division_reconcile_lot_repair(); }
    public function movement()  { parent::stock_division_movement_index(); }
    public function material_matrix() { parent::stock_material_daily_matrix(); }
    public function matrix_view()     { parent::inventory_material_daily_index(); }
    public function lot()       { parent::division_lot_audit_index(); }

    // =========================================================
    // Opname Stok Bahan Baku Harian Divisi
    // =========================================================

    public function opname()
    {
        $this->require_permission(self::PAGE_OPNAME, 'view');

        $opnameDate = trim((string)$this->input->get('opname_date', true));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $opnameDate)) {
            $opnameDate = date('Y-m-d');
        }
        $divisionId  = (int)$this->input->get('division_id', true);
        $destination = strtoupper(trim((string)$this->input->get('destination', true)));
        if ($destination === '') {
            $destination = 'ALL';
        }
        $q = trim((string)$this->input->get('q', true));

        $isSuperadmin = !empty($this->current_user['is_superadmin']);
        $canCreate    = $isSuperadmin || $this->can(self::PAGE_OPNAME, 'create');
        $divisions    = $this->Purchase_model->list_active_operational_divisions();

        $this->render('inventory/stock_opname_division_index', [
            'title'       => 'Daily Recon Bahan Baku Divisi',
            'active_menu' => 'purchase.stock.opname.division',
            'opname_date' => $opnameDate,
            'division_id' => $divisionId,
            'destination' => $destination,
            'q'           => $q,
            'divisions'   => $divisions,
            'can_create'  => $canCreate,
        ]);
    }

    public function opname_data()
    {
        $this->require_permission(self::PAGE_OPNAME, 'view');

        $opnameDate  = trim((string)$this->input->get('opname_date', true));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $opnameDate)) {
            $opnameDate = date('Y-m-d');
        }
        $divisionId  = (int)$this->input->get('division_id', true);
        $destination = strtoupper(trim((string)$this->input->get('destination', true)));
        $q           = trim((string)$this->input->get('q', true));

        $targetMonth = date('Y-m-01', strtotime($opnameDate));

        $divNameCol  = $this->db->field_exists('division_name', 'mst_operational_division')
            ? 'division_name'
            : ($this->db->field_exists('name', 'mst_operational_division') ? 'name' : null);
        $divNameExpr = $divNameCol ? ('dv.' . $divNameCol) : 'CAST(dms.division_id AS CHAR)';

        // Subquery tidak pakai alias -- gunakan column name langsung
        $subDivWhere = $divisionId > 0 ? 'AND division_id = ' . (int)$divisionId : '';

        $latestSub = "SELECT division_id, destination_type, identity_key, MAX(month_key) AS max_month
                      FROM inv_division_monthly_stock
                      WHERE month_key <= " . $this->db->escape($targetMonth) . "
                      {$subDivWhere}
                      GROUP BY division_id, destination_type, identity_key";

        $destWhere = '';
        if ($destination !== 'ALL') {
            if ($destination === 'REGULER') {
                $destWhere = "AND dms.destination_type NOT IN ('BAR_EVENT','KITCHEN_EVENT')";
            } elseif ($destination === 'EVENT') {
                $destWhere = "AND dms.destination_type IN ('BAR_EVENT','KITCHEN_EVENT')";
            } else {
                $destWhere = "AND dms.destination_type = " . $this->db->escape($destination);
            }
        }

        $qWhere = '';
        if ($q !== '') {
            $qLike = $this->db->escape('%' . $q . '%');
            $qWhere = "AND (i.item_name LIKE {$qLike} OR m.material_name LIKE {$qLike}
                          OR i.item_code LIKE {$qLike} OR m.material_code LIKE {$qLike}
                          OR dms.profile_name LIKE {$qLike})";
        }

        $divWhereMain = $divisionId > 0 ? 'AND dms.division_id = ' . (int)$divisionId : '';

        $sql = "
            SELECT
                dms.division_id,
                {$divNameExpr}                                AS division_name,
                dms.destination_type,
                dms.item_id,
                dms.material_id,
                dms.buy_uom_id,
                dms.content_uom_id,
                COALESCE(dms.profile_key, '')                 AS profile_key,
                COALESCE(dms.identity_key, dms.profile_key, '') AS identity_key,
                COALESCE(dms.profile_name, m.material_name, i.item_name, '') AS profile_name,
                COALESCE(dms.profile_brand, '')               AS profile_brand,
                COALESCE(dms.profile_description, '')         AS profile_description,
                dms.profile_expired_date,
                COALESCE(dms.profile_content_per_buy, 1)      AS profile_content_per_buy,
                COALESCE(dms.profile_buy_uom_code, '')        AS profile_buy_uom_code,
                COALESCE(dms.profile_content_uom_code, '')    AS profile_content_uom_code,
                COALESCE(i.item_code, '')                     AS item_code,
                COALESCE(i.item_name, '')                     AS item_name,
                COALESCE(m.material_code, '')                 AS material_code,
                COALESCE(m.material_name, '')                 AS material_name,
                dms.closing_qty_content                       AS system_qty_content,
                dms.closing_qty_buy                           AS system_qty_buy,
                dms.avg_cost_per_content,
                dms.total_value,
                dms.last_movement_date
            FROM inv_division_monthly_stock dms
            INNER JOIN ({$latestSub}) lm
                ON  lm.division_id      = dms.division_id
                AND lm.destination_type = dms.destination_type
                AND lm.identity_key     = dms.identity_key
                AND lm.max_month        = dms.month_key
            LEFT JOIN mst_item     i  ON i.id = dms.item_id
            LEFT JOIN mst_material m  ON m.id = COALESCE(dms.material_id, i.material_id)
            LEFT JOIN mst_operational_division dv ON dv.id = dms.division_id
            WHERE dms.material_id IS NOT NULL
              {$divWhereMain}
              {$destWhere}
              {$qWhere}
            ORDER BY {$divNameExpr},
                     COALESCE(m.material_name, i.item_name),
                     dms.profile_name
        ";

        $stockRows = ($r = $this->db->query($sql)) ? $r->result_array() : [];

        // Load opname records for this date (all or specific division)
        $opnameQuery = $this->db->table_exists('inv_division_stock_opname')
            ? $this->db->select('division_id, identity_key, physical_qty_content, notes, adjustment_id')
                ->from('inv_division_stock_opname')
                ->where('opname_date', $opnameDate)
            : null;
        if ($opnameQuery && $divisionId > 0) {
            $opnameQuery->where('division_id', $divisionId);
        }
        $opnameRows = $opnameQuery ? $opnameQuery->get()->result_array() : [];

        $opnameMap = [];
        foreach ($opnameRows as $row) {
            $opnameMap[$row['division_id'] . '|' . $row['identity_key']] = $row;
        }

        // Group: division -> material -> profiles
        $divisionGroups = [];
        foreach ($stockRows as $r) {
            $divId   = (int)$r['division_id'];
            $divName = (string)$r['division_name'];
            $matKey  = $divId . '|' . ($r['material_id'] ?: ('item_' . $r['item_id']));
            $ikey    = (string)$r['identity_key'];
            $opname  = $opnameMap[$divId . '|' . $ikey] ?? null;

            $profile = [
                'division_id'         => $divId,
                'destination_type'    => $r['destination_type'],
                'item_id'             => (int)$r['item_id'],
                'material_id'         => (int)$r['material_id'],
                'buy_uom_id'          => (int)$r['buy_uom_id'],
                'content_uom_id'      => (int)$r['content_uom_id'],
                'profile_key'         => $r['profile_key'],
                'identity_key'        => $ikey,
                'profile_name'        => $r['profile_name'],
                'profile_brand'       => $r['profile_brand'],
                'profile_description' => $r['profile_description'],
                'profile_expired_date'=> $r['profile_expired_date'],
                'profile_content_per_buy'  => (float)$r['profile_content_per_buy'],
                'profile_buy_uom_code'     => $r['profile_buy_uom_code'],
                'profile_content_uom_code' => $r['profile_content_uom_code'],
                'item_code'           => $r['item_code'],
                'item_name'           => $r['item_name'],
                'material_code'       => $r['material_code'],
                'material_name'       => $r['material_name'],
                'system_qty_content'  => (float)$r['system_qty_content'],
                'system_qty_buy'      => (float)$r['system_qty_buy'],
                'avg_cost_per_content'=> (float)$r['avg_cost_per_content'],
                'total_value'         => (float)$r['total_value'],
                'last_movement_date'  => (string)($r['last_movement_date'] ?? ''),
                'physical_qty_content'=> null,
                'selisih'             => null,
                'opname_notes'        => '',
                'adjustment_id'       => ($opname && !empty($opname['adjustment_id']))
                    ? (int)$opname['adjustment_id'] : null,
            ];

            if (!isset($divisionGroups[$divId])) {
                $divisionGroups[$divId] = [
                    'division_id'   => $divId,
                    'division_name' => $divName,
                    'materials'     => [],
                ];
            }
            if (!isset($divisionGroups[$divId]['materials'][$matKey])) {
                $divisionGroups[$divId]['materials'][$matKey] = [
                    'material_id'   => (int)$r['material_id'],
                    'material_code' => $r['material_code'],
                    'material_name' => $r['material_name'] ?: $r['item_name'],
                    'item_id'       => (int)$r['item_id'],
                    'content_uom_code' => $r['profile_content_uom_code'],
                    'profiles'      => [],
                    'system_total'  => 0.0,
                    'physical_total'=> null,
                ];
            }
            $divisionGroups[$divId]['materials'][$matKey]['profiles'][] = $profile;
            $divisionGroups[$divId]['materials'][$matKey]['system_total'] += $profile['system_qty_content'];
            if ($profile['physical_qty_content'] !== null) {
                $divisionGroups[$divId]['materials'][$matKey]['physical_total'] =
                    (($divisionGroups[$divId]['materials'][$matKey]['physical_total'] ?? 0) + $profile['physical_qty_content']);
            }
        }

        // Flatten materials for each division
        $result = [];
        foreach ($divisionGroups as $divRow) {
            $divRow['materials'] = array_values($divRow['materials']);
            $result[] = $divRow;
        }

        $totalMaterials = array_sum(array_map(fn($d) => count($d['materials']), $result));
        $totalProfiles  = count($stockRows);

        $this->output->set_content_type('application/json')
            ->set_output(json_encode([
                'ok'   => true,
                'rows' => $result,
                'meta' => [
                    'opname_date'      => $opnameDate,
                    'total_divisions'  => count($result),
                    'total_materials'  => $totalMaterials,
                    'total_profiles'   => $totalProfiles,
                ],
            ]));
    }

    public function opname_save_physical()
    {
        $this->require_permission(self::PAGE_OPNAME, 'create');

        $payload     = $this->request_payload();
        $opnameDate  = trim((string)($payload['opname_date'] ?? date('Y-m-d')));
        $divisionId  = (int)($payload['division_id'] ?? 0);
        $destination = strtoupper(trim((string)($payload['destination_type'] ?? 'OTHER')));
        $identKey    = trim((string)($payload['identity_key'] ?? ''));
        $physQty     = isset($payload['physical_qty_content']) && $payload['physical_qty_content'] !== ''
            ? round((float)$payload['physical_qty_content'], 4) : null;
        $notes       = trim((string)($payload['notes'] ?? ''));
        $userId      = (int)($this->current_user['id'] ?? 0);

        if ($divisionId <= 0 || $identKey === '') {
            $this->jsonError('division_id dan identity_key wajib diisi.', 422);
            return;
        }
        if (!$this->db->table_exists('inv_division_stock_opname')) {
            $this->jsonError('Tabel opname belum ada. Jalankan SQL setup terlebih dahulu.', 500);
            return;
        }

        $systemQty = (float)($payload['system_qty_content'] ?? 0);
        $selisih   = $physQty !== null ? round($physQty - $systemQty, 4) : null;

        $existing = $this->db
            ->where('opname_date',     $opnameDate)
            ->where('division_id',     $divisionId)
            ->where('destination_type',$destination)
            ->where('identity_key',    $identKey)
            ->get('inv_division_stock_opname')->row_array();

        if ($existing) {
            $this->db->where('id', (int)$existing['id'])->update('inv_division_stock_opname', [
                'physical_qty_content' => $physQty,
                'system_qty_content'   => $systemQty,
                'notes'                => $notes !== '' ? $notes : null,
                'updated_at'           => date('Y-m-d H:i:s'),
            ]);
        } else {
            $this->db->insert('inv_division_stock_opname', [
                'opname_date'             => $opnameDate,
                'division_id'             => $divisionId,
                'destination_type'        => $destination,
                'item_id'                 => !empty($payload['item_id'])     ? (int)$payload['item_id']     : null,
                'material_id'             => !empty($payload['material_id']) ? (int)$payload['material_id'] : null,
                'buy_uom_id'              => !empty($payload['buy_uom_id'])  ? (int)$payload['buy_uom_id']  : null,
                'content_uom_id'          => (int)($payload['content_uom_id'] ?? 0),
                'profile_key'             => (string)($payload['profile_key'] ?? ''),
                'identity_key'            => $identKey,
                'profile_name'            => $this->ns($payload['profile_name'] ?? null),
                'profile_content_per_buy' => max(0.000001, (float)($payload['profile_content_per_buy'] ?? 1)),
                'profile_buy_uom_code'    => $this->ns($payload['profile_buy_uom_code']    ?? null),
                'profile_content_uom_code'=> $this->ns($payload['profile_content_uom_code'] ?? null),
                'system_qty_content'      => $systemQty,
                'physical_qty_content'    => $physQty,
                'notes'                   => $notes !== '' ? $notes : null,
                'created_by'              => $userId > 0 ? $userId : null,
            ]);
        }

        $this->jsonOk(['selisih' => $selisih, 'physical_qty_content' => $physQty]);
    }

    public function opname_quick_adjust()
    {
        $this->require_permission(self::PAGE_OPNAME, 'create');
        $this->require_permission(self::PAGE_STOCK_ADJUSTMENT_DIVISION, 'create');

        $payload     = $this->request_payload();
        $opnameDate  = trim((string)($payload['opname_date'] ?? date('Y-m-d')));
        $divisionId  = (int)($payload['division_id'] ?? 0);
        $destination = strtoupper(trim((string)($payload['destination_type'] ?? 'OTHER')));
        $identKey    = trim((string)($payload['identity_key'] ?? ''));
        $physQty     = (float)($payload['physical_qty_content'] ?? 0);
        $systemQty   = (float)($payload['system_qty_content'] ?? 0);
        $selisih     = round($physQty - $systemQty, 4);
        $adjType     = strtoupper(trim((string)($payload['adjustment_type'] ?? '')));
        $reasonCode  = strtolower(trim((string)($payload['reason_code'] ?? 'other')));
        $notes       = trim((string)($payload['notes'] ?? ''));
        $userId      = (int)($this->current_user['id'] ?? 0);

        if ($divisionId <= 0 || $identKey === '' || $selisih == 0) {
            $this->jsonError('Selisih 0 atau parameter tidak lengkap.', 422);
            return;
        }

        // Jenis penyesuaian harus sesuai sistem adjustment divisi yang ada
        $validNeg = ['WASTE', 'SPOIL', 'PROCESS_LOSS', 'VARIANCE', 'SPOILAGE', 'ADJUSTMENT_MINUS'];
        $validPos = ['ADJUSTMENT_PLUS'];

        if ($selisih < 0 && !in_array($adjType, $validNeg, true)) {
            $adjType = 'ADJUSTMENT_MINUS';
        }
        if ($selisih > 0 && !in_array($adjType, $validPos, true)) {
            $adjType = 'ADJUSTMENT_PLUS';
        }

        $rc     = $reasonCode !== '' ? $reasonCode : 'other';
        $adjNo  = 'OPN-' . date('Ymd', strtotime($opnameDate)) . '-' . strtoupper(substr(md5($identKey . $opnameDate), 0, 6));
        $absQty = round(abs($selisih), 4);

        $line = [
            'item_id'              => !empty($payload['item_id'])     ? (int)$payload['item_id']     : null,
            'material_id'          => !empty($payload['material_id']) ? (int)$payload['material_id'] : null,
            'buy_uom_id'           => !empty($payload['buy_uom_id'])  ? (int)$payload['buy_uom_id']  : null,
            'content_uom_id'       => (int)($payload['content_uom_id'] ?? 0),
            'profile_key'          => $this->ns($payload['profile_key']          ?? null),
            'profile_name'         => $this->ns($payload['profile_name']         ?? null),
            'profile_brand'        => $this->ns($payload['profile_brand']        ?? null),
            'profile_description'  => $this->ns($payload['profile_description']  ?? null),
            'profile_expired_date' => $this->nd((string)($payload['profile_expired_date'] ?? '')),
            'profile_content_per_buy'  => max(0.000001, (float)($payload['profile_content_per_buy'] ?? 1)),
            'profile_buy_uom_code'     => $this->ns($payload['profile_buy_uom_code']     ?? null),
            'profile_content_uom_code' => $this->ns($payload['profile_content_uom_code'] ?? null),
            'note'                 => $notes,
        ];

        if ($selisih < 0) {
            $reasonMap = [
                'WASTE'            => ['qty_waste_content'        => $absQty, 'waste_reason_code'        => $rc],
                'SPOIL'            => ['qty_spoil_content'        => $absQty, 'spoil_reason_code'        => $rc],
                'SPOILAGE'         => ['qty_spoil_content'        => $absQty, 'spoil_reason_code'        => $rc],
                'PROCESS_LOSS'     => ['qty_process_loss_content' => $absQty, 'process_loss_reason_code' => $rc],
                'VARIANCE'         => ['qty_variance_content'     => $absQty, 'variance_reason_code'     => $rc],
                'ADJUSTMENT_MINUS' => ['qty_variance_content'     => $absQty, 'variance_reason_code'     => $rc],
            ];
            $line = array_merge($line, $reasonMap[$adjType] ?? ['qty_variance_content' => $absQty, 'variance_reason_code' => $rc]);
        } else {
            $unitCost = (float)($payload['avg_cost_per_content'] ?? 0);
            $line['qty_adjustment_plus_content'] = $absQty;
            $line['adjustment_plus_reason_code'] = $rc;
            $line['unit_cost']                   = $unitCost > 0 ? $unitCost : null;
        }

        $result = $this->Purchase_model->save_stock_adjustment([
            'id'               => 0,
            'adjustment_no'    => $adjNo,
            'adjustment_date'  => $opnameDate,
            'stock_scope'      => 'DIVISION',
            'division_id'      => $divisionId,
            'destination_type' => $destination,
            'notes'            => 'Dari opname stok harian' . ($notes !== '' ? ': ' . $notes : ''),
        ], [$line], $userId);

        if (!($result['ok'] ?? false)) {
            $this->jsonError((string)($result['message'] ?? 'Gagal menyimpan adjustment.'), 422);
            return;
        }

        $adjId = (int)($result['id'] ?? 0);
        $post  = $this->Purchase_model->post_stock_adjustment($adjId, $userId);

        if (!($post['ok'] ?? false)) {
            $this->jsonError('Tersimpan tapi gagal posting: ' . (string)($post['message'] ?? ''), 422);
            return;
        }

        if ($this->db->table_exists('inv_division_stock_opname') && $adjId > 0) {
            $this->db
                ->where('opname_date',      $opnameDate)
                ->where('division_id',      $divisionId)
                ->where('destination_type', $destination)
                ->where('identity_key',     $identKey)
                ->update('inv_division_stock_opname', ['adjustment_id' => $adjId]);
        }

        $this->jsonOk(['adjustment_id' => $adjId], 'Adjustment berhasil diposting.');
    }

    private function jsonOk(array $data = [], string $message = ''): void
    {
        $payload = ['ok' => true];
        if ($message !== '') {
            $payload['message'] = $message;
        }
        $payload['data'] = $data;
        $this->output->set_content_type('application/json')->set_output(json_encode($payload));
    }

    private function jsonError(string $message, int $status = 400): void
    {
        $this->output->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode(['ok' => false, 'message' => $message]));
    }

    private function request_payload(): array
    {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                return $json;
            }
        }
        $post = $this->input->post(null, false);
        return is_array($post) ? $post : [];
    }

    private function ns($value): ?string
    {
        $v = trim((string)($value ?? ''));
        return $v === '' ? null : $v;
    }

    public function opname_monthly()
    {
        parent::stock_division_opname_monthly();
    }

    private function nd(string $value): ?string
    {
        $v = trim($value);
        if ($v === '') {
            return null;
        }
        $d = date_create_from_format('Y-m-d', $v);
        return ($d && $d->format('Y-m-d') === $v) ? $v : null;
    }
}
