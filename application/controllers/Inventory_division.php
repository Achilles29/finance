<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/Purchase.php';

class Inventory_division extends Purchase
{
    private const PAGE_OPNAME = 'inventory.stock.opname.division.index';

    public function index()     { parent::stock_division_index(); }
    public function opening()   { parent::stock_opening_division_index(); }
    public function adjustment(){ parent::stock_adjustment_division_index(); }
    public function transfer()  { parent::stock_transfer_division_index(); }
    public function daily()     { parent::stock_division_daily_index(); }
    public function compare()   { parent::stock_division_reconcile_index(); }
    public function reconcile_audit()  { parent::stock_division_reconcile_audit(); }
    public function reconcile_repair()     { parent::stock_division_reconcile_repair(); }
    public function reconcile_lot_repair()        { parent::stock_division_reconcile_lot_repair(); }
    public function reconcile_lot_profile_sync()  { parent::stock_division_reconcile_lot_profile_sync(); }
    public function reconcile_lot_repair_all()    { parent::stock_division_reconcile_lot_repair_all(); }
    public function reconcile_gap_repair_all()    { parent::stock_division_reconcile_gap_repair_all(); }
    public function reconcile_lot_only_adjust()   { parent::stock_division_reconcile_lot_only_adjust(); }
    public function reconcile_log_repair()        { parent::stock_division_reconcile_log_repair(); }
    public function reconcile_repair_material_id() { parent::stock_division_reconcile_repair_material_id(); }
    public function reconcile_profile_repair()     { parent::stock_division_reconcile_profile_repair(); }
    public function reconcile_profile_merge()      { parent::stock_division_reconcile_profile_merge(); }
    public function movement()  { parent::stock_division_movement_index(); }
    public function stok_awal() { parent::stock_opening_division_generated(); }
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
        $scopeDivisionId = $this->active_division_id();
        if ($scopeDivisionId !== null) {
            $divisionId = $scopeDivisionId;
        }
        $destination = strtoupper(trim((string)$this->input->get('destination', true)));
        if ($destination === '') {
            $destination = 'ALL';
        }
        $q = trim((string)$this->input->get('q', true));

        $isSuperadmin = !empty($this->current_user['is_superadmin']);
        $canCreate    = $isSuperadmin || $this->can(self::PAGE_OPNAME, 'create');
        $divisions    = $this->Purchase_model->list_active_operational_divisions();
        if ($scopeDivisionId !== null) {
            $divisions = array_values(array_filter($divisions, static function (array $row) use ($scopeDivisionId): bool {
                return (int)($row['id'] ?? 0) === $scopeDivisionId;
            }));
        }

        $this->render('inventory/stock_opname_division_index', [
            'title'       => 'Daily Recon Bahan Baku Divisi',
            'active_menu' => 'purchase.stock.opname.division',
            'opname_date' => $opnameDate,
            'division_id' => $divisionId,
            'destination' => $destination,
            'q'           => $q,
            'divisions'   => $divisions,
            'can_create'  => $canCreate,
            'division_scope_id' => $scopeDivisionId,
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
        $scopeDivisionId = $this->active_division_id();
        if ($scopeDivisionId !== null) {
            $divisionId = $scopeDivisionId;
        }
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
                      WHERE month_key = " . $this->db->escape($targetMonth) . "
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

        $hasCatTable  = $this->db->table_exists('mst_item_category');
        $catJoin      = $hasCatTable ? 'LEFT JOIN mst_item_category cat ON cat.id = m.item_category_id' : '';
        $catNameExpr  = $hasCatTable ? "COALESCE(cat.name, '')" : "''";
        $catIdExpr    = $hasCatTable ? 'COALESCE(cat.id, 0)' : '0';
        $catOrderExpr = $hasCatTable ? 'COALESCE(cat.id, 99999),' : '';
        $matActiveWhere = $this->db->field_exists('is_active', 'mst_material')
            ? 'AND (m.id IS NULL OR COALESCE(m.is_active, 1) = 1)'
            : '';
        // Daily recon is stock-ledger based. Legacy/inactive items can still own current
        // monthly stock after cutoff generation, so filtering mst_item.is_active here hides
        // real stock and makes recon look like zero.
        $itemActiveWhere = '';
        $lotStartEsc = $this->db->escape($targetMonth);
        $lotEndEsc   = $this->db->escape($opnameDate);

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
                dms.last_movement_date,
                (
                    SELECT MIN(l.receipt_date)
                    FROM inv_material_fifo_lot l
                    LEFT JOIN mst_item li ON li.id = l.item_id
                    WHERE l.location_scope = 'DIVISION'
                      AND l.division_id = dms.division_id
                      AND l.destination_type = dms.destination_type
                      AND COALESCE(l.item_id, 0) = COALESCE(dms.item_id, 0)
                      AND COALESCE(l.material_id, li.material_id, 0) = COALESCE(dms.material_id, i.material_id, 0)
                      AND COALESCE(l.content_uom_id, 0) = COALESCE(dms.content_uom_id, 0)
                      AND COALESCE(l.profile_key, '') = COALESCE(dms.profile_key, '')
                      AND l.status = 'OPEN'
                      AND l.qty_balance > 0.0001
                      AND l.receipt_date >= {$lotStartEsc}
                      AND l.receipt_date <= {$lotEndEsc}
                ) AS stock_in_first_date,
                (
                    SELECT MAX(l.receipt_date)
                    FROM inv_material_fifo_lot l
                    LEFT JOIN mst_item li ON li.id = l.item_id
                    WHERE l.location_scope = 'DIVISION'
                      AND l.division_id = dms.division_id
                      AND l.destination_type = dms.destination_type
                      AND COALESCE(l.item_id, 0) = COALESCE(dms.item_id, 0)
                      AND COALESCE(l.material_id, li.material_id, 0) = COALESCE(dms.material_id, i.material_id, 0)
                      AND COALESCE(l.content_uom_id, 0) = COALESCE(dms.content_uom_id, 0)
                      AND COALESCE(l.profile_key, '') = COALESCE(dms.profile_key, '')
                      AND l.status = 'OPEN'
                      AND l.qty_balance > 0.0001
                      AND l.receipt_date >= {$lotStartEsc}
                      AND l.receipt_date <= {$lotEndEsc}
                ) AS stock_in_last_date,
                (
                    SELECT GROUP_CONCAT(DISTINCT COALESCE(l.source_table, '') ORDER BY COALESCE(l.source_table, '') SEPARATOR ',')
                    FROM inv_material_fifo_lot l
                    LEFT JOIN mst_item li ON li.id = l.item_id
                    WHERE l.location_scope = 'DIVISION'
                      AND l.division_id = dms.division_id
                      AND l.destination_type = dms.destination_type
                      AND COALESCE(l.item_id, 0) = COALESCE(dms.item_id, 0)
                      AND COALESCE(l.material_id, li.material_id, 0) = COALESCE(dms.material_id, i.material_id, 0)
                      AND COALESCE(l.content_uom_id, 0) = COALESCE(dms.content_uom_id, 0)
                      AND COALESCE(l.profile_key, '') = COALESCE(dms.profile_key, '')
                      AND l.status = 'OPEN'
                      AND l.qty_balance > 0.0001
                      AND l.receipt_date >= {$lotStartEsc}
                      AND l.receipt_date <= {$lotEndEsc}
                ) AS stock_in_sources,
                {$catNameExpr}                                AS category_name,
                {$catIdExpr}                                  AS category_id,
                0                                             AS is_recipe_only
            FROM inv_division_monthly_stock dms
            INNER JOIN ({$latestSub}) lm
                ON  lm.division_id      = dms.division_id
                AND lm.destination_type = dms.destination_type
                AND lm.identity_key     = dms.identity_key
                AND lm.max_month        = dms.month_key
            LEFT JOIN mst_item     i  ON i.id = dms.item_id
            LEFT JOIN mst_material m  ON m.id = COALESCE(dms.material_id, i.material_id)
            LEFT JOIN mst_operational_division dv ON dv.id = dms.division_id
            {$catJoin}
            WHERE dms.material_id IS NOT NULL
              AND (dms.profile_key IS NULL OR dms.identity_key = dms.profile_key)
              {$matActiveWhere}
              {$itemActiveWhere}
              {$divWhereMain}
              {$destWhere}
              {$qWhere}
            ORDER BY {$divNameExpr},
                     {$catOrderExpr}
                     COALESCE(m.material_name, i.item_name),
                     dms.profile_name
        ";

        $prevDbDebug = (bool)$this->db->db_debug;
        $this->db->db_debug = false;
        $r = $this->db->query($sql);
        $this->db->db_debug = $prevDbDebug;
        if (!$r) {
            $err = $this->db->error();
            $this->jsonError('Gagal memuat data daily recon bahan baku: ' . (string)($err['message'] ?? 'query database gagal'), 500);
            return;
        }
        $stockRows = $r->result_array();
        $this->attach_material_daily_recon_flags($stockRows, $opnameDate, $divisionId);

        // ── Append recipe-only materials (active in recipes but no stock record) ──
        $includeRecipeOnlyRows = false;
        if ($includeRecipeOnlyRows && $destination !== 'EVENT' && $this->db->table_exists('mst_product_recipe')) {
            $existingKeys = [];
            foreach ($stockRows as $stockR) {
                if (!empty($stockR['material_id']) && !empty($stockR['division_id'])) {
                    $existingKeys[(int)$stockR['division_id'] . '|' . (int)$stockR['material_id']] = true;
                }
            }

            $recDivNameExpr = $divNameCol ? ('dv.' . $divNameCol) : 'CAST(r.source_division_id AS CHAR)';
            $recCatJoin     = $hasCatTable ? 'LEFT JOIN mst_item_category cat ON cat.id = m.item_category_id' : '';
            $recCatExpr     = $hasCatTable ? "COALESCE(MIN(cat.name), '')" : "''";
            $recCatIdExpr   = $hasCatTable ? 'MIN(COALESCE(cat.id, 0))' : '0';

            $matIsActive  = $this->db->field_exists('is_active', 'mst_material') ? 'AND m.is_active = 1' : '';
            $itemIsActive = $this->db->field_exists('is_active', 'mst_item')     ? 'AND i.is_active = 1' : '';

            $recDivWhere = $divisionId > 0 ? 'AND r.source_division_id = ' . (int)$divisionId : '';
            $recQWhere   = '';
            if ($q !== '') {
                $qLike = $this->db->escape('%' . $q . '%');
                $recQWhere = "AND (m.material_name LIKE {$qLike} OR m.material_code LIKE {$qLike})";
            }

            $recSql = "
                SELECT
                    r.source_division_id            AS division_id,
                    MIN({$recDivNameExpr})           AS division_name,
                    'OTHER'                          AS destination_type,
                    MIN(i.id)                        AS item_id,
                    m.id                             AS material_id,
                    m.content_uom_id                 AS buy_uom_id,
                    m.content_uom_id                 AS content_uom_id,
                    ''                               AS profile_key,
                    ''                               AS identity_key,
                    m.material_name                  AS profile_name,
                    ''                               AS profile_brand,
                    ''                               AS profile_description,
                    NULL                             AS profile_expired_date,
                    1                                AS profile_content_per_buy,
                    MIN(u.code)                      AS profile_buy_uom_code,
                    MIN(u.code)                      AS profile_content_uom_code,
                    MIN(i.item_code)                 AS item_code,
                    MIN(i.item_name)                 AS item_name,
                    m.material_code                  AS material_code,
                    m.material_name                  AS material_name,
                    0                                AS system_qty_content,
                    0                                AS system_qty_buy,
                    0                                AS avg_cost_per_content,
                    0                                AS total_value,
                    NULL                             AS last_movement_date,
                    NULL                             AS stock_in_first_date,
                    NULL                             AS stock_in_last_date,
                    NULL                             AS stock_in_sources,
                    {$recCatExpr}                    AS category_name,
                    {$recCatIdExpr}                  AS category_id,
                    1                                AS is_recipe_only
                FROM mst_product_recipe r
                JOIN mst_item i ON i.id = r.material_item_id {$itemIsActive}
                JOIN mst_material m ON m.id = i.material_id {$matIsActive}
                JOIN mst_operational_division dv ON dv.id = r.source_division_id
                LEFT JOIN mst_uom u ON u.id = m.content_uom_id
                {$recCatJoin}
                WHERE r.source_division_id IS NOT NULL
                  AND r.material_item_id IS NOT NULL
                  AND i.material_id IS NOT NULL
                  {$recDivWhere}
                  {$recQWhere}
                GROUP BY r.source_division_id, m.id, m.content_uom_id, m.material_code, m.material_name
            ";

            $addedAny = false;
            $recRows  = ($rq = $this->db->query($recSql)) ? $rq->result_array() : [];
            foreach ($recRows as $rRec) {
                $key = (int)$rRec['division_id'] . '|' . (int)$rRec['material_id'];
                if (!isset($existingKeys[$key])) {
                    $stockRows[]        = $rRec;
                    $existingKeys[$key] = true;
                    $addedAny           = true;
                }
            }

            if ($addedAny) {
                usort($stockRows, static function (array $a, array $b): int {
                    $dc = strcmp((string)($a['division_name'] ?? ''), (string)($b['division_name'] ?? ''));
                    if ($dc !== 0) return $dc;
                    $catA = (int)($a['category_id'] ?? 0);
                    $catB = (int)($b['category_id'] ?? 0);
                    if ($catA !== $catB) return $catA <=> $catB;
                    return strcmp((string)($a['material_name'] ?? ''), (string)($b['material_name'] ?? ''));
                });
            }
        }

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
                'stock_in_first_date' => (string)($r['stock_in_first_date'] ?? ''),
                'stock_in_last_date'  => (string)($r['stock_in_last_date'] ?? ''),
                'stock_in_sources'    => (string)($r['stock_in_sources'] ?? ''),
                'category_name'       => (string)($r['category_name'] ?? ''),
                'is_recipe_only'      => !empty($r['is_recipe_only']),
                'physical_qty_content'=> null,
                'selisih'             => null,
                'opname_notes'        => '',
                'adjustment_id'       => ($opname && !empty($opname['adjustment_id']))
                    ? (int)$opname['adjustment_id'] : null,
                'lot_count'            => (int)($r['lot_count'] ?? 0),
                'recon_line_key'       => (string)($r['recon_line_key'] ?? ''),
                'must_row_confirm'     => !empty($r['must_row_confirm']),
                'must_row_confirm_reason' => (string)($r['must_row_confirm_reason'] ?? ''),
                'confirmed_open'       => !empty($r['confirmed_open']),
                'confirmed_close'      => !empty($r['confirmed_close']),
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
                    'item_id'          => (int)$r['item_id'],
                    'content_uom_code' => $r['profile_content_uom_code'],
                    'category_name'    => (string)($r['category_name'] ?? ''),
                    'profiles'         => [],
                    'system_total'     => 0.0,
                    'physical_total'   => null,
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
                    'confirm_mode'     => $this->daily_recon_confirm_mode(),
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

        // ── Guard: repair lot gap before negative adjustment ─────────────────────
        // syncDivisionMonthlyStockFromLots (run inside post_stock_adjustment) overwrites
        // monthly stock from lot balance. If lot = 0 and stock > 0, the sync zeros the
        // stock, then the FIFO consume fails. Pre-repair brings the lot up to stock first.
        if ($selisih < 0 && !empty($payload['material_id'])
            && $this->db->table_exists('inv_material_fifo_lot')
        ) {
            $matId = (int)$payload['material_id'];
            $profileKey = (string)($payload['profile_key'] ?? '');
            $lotMonthStart = date('Y-m-01', strtotime($opnameDate));
            $lotRow = $this->db->query(
                "SELECT COALESCE(SUM(qty_balance), 0) AS lot_total
                 FROM inv_material_fifo_lot l
                 LEFT JOIN mst_item li ON li.id = l.item_id
                 WHERE l.location_scope = 'DIVISION'
                   AND l.division_id = ?
                   AND l.destination_type = ?
                   AND COALESCE(l.material_id, li.material_id) = ?
                   AND l.content_uom_id = ?
                   AND COALESCE(l.profile_key, '') = ?
                   AND l.receipt_date >= ?
                   AND l.receipt_date <= ?
                   AND l.status = 'OPEN' AND l.qty_balance > 0.0001",
                [$divisionId, $destination, $matId, (int)($payload['content_uom_id'] ?? 0), $profileKey, $lotMonthStart, $opnameDate]
            )->row_array();
            $lotAvail = round((float)($lotRow['lot_total'] ?? 0), 4);
            if ($lotAvail < $absQty - 0.01) {
                $label = trim((string)($payload['profile_name'] ?? $payload['material_name'] ?? $payload['item_name'] ?? 'bahan ini'));
                $this->jsonError(
                    'Lot FIFO profil ' . $label . ' tidak cukup untuk adjustment. Dibutuhkan '
                    . number_format($absQty, 4, ',', '.')
                    . ', tersedia ' . number_format($lotAvail, 4, ',', '.')
                    . '. Jalankan repair lot/reconcile dulu, lalu posting ulang.',
                    422
                );
                return;
            }
        }
        // ─────────────────────────────────────────────────────────────────────────

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
            $existing = $this->db
                ->select('id')
                ->where('opname_date',      $opnameDate)
                ->where('division_id',      $divisionId)
                ->where('destination_type', $destination)
                ->where('identity_key',     $identKey)
                ->limit(1)
                ->get('inv_division_stock_opname')
                ->row_array();

            $opnameRow = [
                'opname_date'          => $opnameDate,
                'division_id'          => $divisionId,
                'destination_type'     => $destination,
                'item_id'              => !empty($payload['item_id'])     ? (int)$payload['item_id']     : null,
                'material_id'          => !empty($payload['material_id']) ? (int)$payload['material_id'] : null,
                'buy_uom_id'           => !empty($payload['buy_uom_id'])  ? (int)$payload['buy_uom_id']  : null,
                'content_uom_id'       => (int)($payload['content_uom_id'] ?? 0),
                'profile_key'          => (string)($payload['profile_key'] ?? ''),
                'identity_key'         => $identKey,
                'profile_name'         => $this->ns($payload['profile_name'] ?? null),
                'profile_content_per_buy'  => max(0.000001, (float)($payload['profile_content_per_buy'] ?? 1)),
                'profile_buy_uom_code'     => $this->ns($payload['profile_buy_uom_code'] ?? null),
                'profile_content_uom_code' => $this->ns($payload['profile_content_uom_code'] ?? null),
                'system_qty_content'       => $systemQty,
                'physical_qty_content'     => $physQty,
                'notes'                    => $notes !== '' ? $notes : null,
                'adjustment_id'            => $adjId,
            ];

            if (!empty($existing['id'])) {
                $this->db->where('id', (int)$existing['id'])->update('inv_division_stock_opname', $opnameRow);
            } else {
                $opnameRow['created_by'] = $userId > 0 ? $userId : null;
                $this->db->insert('inv_division_stock_opname', $opnameRow);
            }
        }

        $this->jsonOk(['adjustment_id' => $adjId], 'Adjustment berhasil diposting.');
    }

    public function opname_confirm_recon()
    {
        $this->require_permission(self::PAGE_OPNAME, 'create');

        $payload = $this->request_payload();
        $date = trim((string)($payload['opname_date'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->jsonError('Tanggal recon tidak valid.', 422);
            return;
        }

        $divisionId = (int)($payload['division_id'] ?? 0);
        $scopeDivisionId = $this->active_division_id();
        if ($scopeDivisionId !== null) {
            $divisionId = $scopeDivisionId;
        }
        $stage = strtoupper(trim((string)($payload['stage'] ?? '')));
        $scope = strtoupper(trim((string)($payload['scope'] ?? 'ALL')));
        $notes = trim((string)($payload['notes'] ?? ''));
        $userId = (int)($this->current_user['employee_id'] ?? ($this->current_user['id'] ?? 0));

        if ($divisionId <= 0) {
            $this->jsonError('Pilih satu divisi terlebih dahulu sebelum konfirmasi recon.', 422);
            return;
        }
        if (!in_array($stage, ['OPEN', 'CLOSE'], true)) {
            $this->jsonError('Tahap recon harus OPEN atau CLOSE.', 422);
            return;
        }
        if (!$this->db->table_exists('inv_daily_recon_checkpoint')) {
            $this->jsonError('Tabel checkpoint daily recon belum tersedia. Jalankan SQL setup 2026-07-05a dulu.', 500);
            return;
        }

        if ($scope === 'ROW') {
            if (!$this->db->table_exists('inv_daily_recon_checkpoint_line')) {
                $this->jsonError('Tabel detail checkpoint daily recon belum tersedia. Jalankan SQL setup 2026-07-05a dulu.', 500);
                return;
            }
            $lineKey = trim((string)($payload['line_key'] ?? ''));
            if ($lineKey === '') {
                $this->jsonError('line_key wajib diisi untuk konfirmasi per baris.', 422);
                return;
            }
            $this->upsert_daily_recon_checkpoint_line($date, 'MATERIAL', $divisionId, $stage, [
                'line_key' => $lineKey,
                'line_label' => trim((string)($payload['line_label'] ?? '')),
                'item_id' => !empty($payload['item_id']) ? (int)$payload['item_id'] : null,
                'material_id' => !empty($payload['material_id']) ? (int)$payload['material_id'] : null,
                'profile_key' => trim((string)($payload['profile_key'] ?? '')),
                'required_reason' => trim((string)($payload['required_reason'] ?? '')),
                'source_page' => 'inventory/stock/daily-recon/division',
                'notes' => $notes,
                'confirmed_by' => $userId,
            ]);
            $this->jsonOk([
                'opname_date' => $date,
                'division_id' => $divisionId,
                'stage' => $stage,
                'line_key' => $lineKey,
            ], 'Baris bahan baku berhasil dikonfirmasi.');
            return;
        }

        $this->upsert_daily_recon_checkpoint($date, 'MATERIAL', $divisionId, $stage, 'inventory/stock/daily-recon/division', $notes, $userId);
        $this->jsonOk([
            'opname_date' => $date,
            'division_id' => $divisionId,
            'stage' => $stage,
        ], 'Konfirmasi daily recon bahan baku berhasil disimpan.');
    }

    private function upsert_daily_recon_checkpoint(string $date, string $domain, int $divisionId, string $stage, string $sourcePage, string $notes, int $userId): void
    {
        $existing = $this->db->select('id')
            ->from('inv_daily_recon_checkpoint')
            ->where('checkpoint_date', $date)
            ->where('recon_domain', $domain)
            ->where('division_id', $divisionId)
            ->where('checkpoint_stage', $stage)
            ->limit(1)
            ->get()
            ->row_array();

        $row = [
            'checkpoint_date' => $date,
            'recon_domain' => $domain,
            'division_id' => $divisionId,
            'checkpoint_stage' => $stage,
            'source_page' => $sourcePage,
            'notes' => $notes !== '' ? $notes : null,
            'confirmed_by' => $userId > 0 ? $userId : null,
            'confirmed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!empty($existing['id'])) {
            $this->db->where('id', (int)$existing['id'])->update('inv_daily_recon_checkpoint', $row);
            return;
        }

        $row['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('inv_daily_recon_checkpoint', $row);
    }

    private function upsert_daily_recon_checkpoint_line(string $date, string $domain, int $divisionId, string $stage, array $line): void
    {
        $lineKey = trim((string)($line['line_key'] ?? ''));
        if ($lineKey === '') {
            return;
        }

        $existing = $this->db->select('id')
            ->from('inv_daily_recon_checkpoint_line')
            ->where('checkpoint_date', $date)
            ->where('recon_domain', $domain)
            ->where('division_id', $divisionId)
            ->where('checkpoint_stage', $stage)
            ->where('line_key', $lineKey)
            ->limit(1)
            ->get()
            ->row_array();

        $row = [
            'checkpoint_date' => $date,
            'recon_domain' => $domain,
            'division_id' => $divisionId,
            'checkpoint_stage' => $stage,
            'line_key' => $lineKey,
            'line_label' => trim((string)($line['line_label'] ?? '')),
            'item_id' => $line['item_id'] ?? null,
            'material_id' => $line['material_id'] ?? null,
            'profile_key' => trim((string)($line['profile_key'] ?? '')) ?: null,
            'required_reason' => trim((string)($line['required_reason'] ?? '')) ?: null,
            'source_page' => trim((string)($line['source_page'] ?? '')),
            'notes' => trim((string)($line['notes'] ?? '')) ?: null,
            'confirmed_by' => !empty($line['confirmed_by']) ? (int)$line['confirmed_by'] : null,
            'confirmed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!empty($existing['id'])) {
            $this->db->where('id', (int)$existing['id'])->update('inv_daily_recon_checkpoint_line', $row);
            return;
        }

        $row['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('inv_daily_recon_checkpoint_line', $row);
    }

    private function attach_material_daily_recon_flags(array &$rows, string $date, int $divisionId): void
    {
        if (empty($rows)) {
            return;
        }

        $requiredTokens = $this->daily_recon_required_tokens('pos.daily_recon_required_materials');
        $lineKeys = [];
        $materialIds = [];
        foreach ($rows as $idx => &$row) {
            $materialId = (int)($row['material_id'] ?? 0);
            if ($materialId > 0) {
                $materialIds[$materialId] = $materialId;
            }
            $lineKey = $this->material_recon_line_key($row);
            $row['recon_line_key'] = $lineKey;
            $lineKeys[$lineKey] = $lineKey;
        }
        unset($row);

        $lotCounts = [];
        $lotMeta = [];
        if (!empty($materialIds) && $this->db->table_exists('inv_material_fifo_lot')) {
            $lotMonthStart = date('Y-m-01', strtotime($date));
            // Jangan pakai Query Builder where_in untuk daftar besar. CI3 akan memproses
            // SQL panjang dengan regex internal dan bisa gagal "regular expression is too large".
            foreach (array_chunk(array_values($materialIds), 120) as $materialChunk) {
                $materialChunk = array_values(array_filter(array_map('intval', $materialChunk), static function (int $id): bool {
                    return $id > 0;
                }));
                if (empty($materialChunk)) {
                    continue;
                }

                $divWhere = $divisionId > 0 ? 'AND l.division_id = ' . (int)$divisionId : '';
                $lotSql = "
                    SELECT
                        l.division_id,
                        COALESCE(l.destination_type, 'OTHER') AS destination_type,
                        COALESCE(l.item_id, 0) AS item_id,
                        COALESCE(l.material_id, li.material_id, 0) AS material_id,
                        COALESCE(l.buy_uom_id, 0) AS buy_uom_id,
                        COALESCE(l.content_uom_id, 0) AS content_uom_id,
                        COALESCE(l.profile_key, '') AS profile_key,
                        COUNT(*) AS lot_count,
                        MIN(l.receipt_date) AS first_receipt_date,
                        MAX(l.receipt_date) AS last_receipt_date,
                        GROUP_CONCAT(DISTINCT COALESCE(l.source_table, '') ORDER BY COALESCE(l.source_table, '') SEPARATOR ',') AS source_tables
                    FROM inv_material_fifo_lot l
                    LEFT JOIN mst_item li ON li.id = l.item_id
                    WHERE l.location_scope = 'DIVISION'
                      AND l.status = 'OPEN'
                      AND ABS(COALESCE(l.qty_balance, 0)) > 0.0001
                      AND l.receipt_date >= " . $this->db->escape($lotMonthStart) . "
                      AND l.receipt_date <= " . $this->db->escape($date) . "
                      AND COALESCE(l.material_id, li.material_id) IN (" . implode(',', $materialChunk) . ")
                      {$divWhere}
                    GROUP BY
                        l.division_id,
                        COALESCE(l.destination_type, 'OTHER'),
                        COALESCE(l.item_id, 0),
                        COALESCE(l.material_id, li.material_id, 0),
                        COALESCE(l.buy_uom_id, 0),
                        COALESCE(l.content_uom_id, 0),
                        COALESCE(l.profile_key, '')
                ";
                $lotQuery = $this->db->query($lotSql);
                foreach ($lotQuery ? $lotQuery->result_array() : [] as $lotRow) {
                    $lotKey = $this->material_lot_count_key($lotRow);
                    $lotCounts[$lotKey] = (int)($lotRow['lot_count'] ?? 0);
                    $lotMeta[$lotKey] = [
                        'first_receipt_date' => (string)($lotRow['first_receipt_date'] ?? ''),
                        'last_receipt_date'  => (string)($lotRow['last_receipt_date'] ?? ''),
                        'source_tables'      => (string)($lotRow['source_tables'] ?? ''),
                    ];
                }
            }
        }

        $confirmed = [];
        if (!empty($lineKeys) && $this->db->table_exists('inv_daily_recon_checkpoint_line')) {
            foreach (array_chunk(array_values($lineKeys), 40) as $lineKeyChunk) {
                $lineKeyChunk = array_values(array_filter(array_map(static function ($key): string {
                    return trim((string)$key);
                }, $lineKeyChunk), static function (string $key): bool {
                    return $key !== '';
                }));
                if (empty($lineKeyChunk)) {
                    continue;
                }

                $lineKeySql = implode(',', array_map([$this->db, 'escape'], $lineKeyChunk));
                $lineSql = "
                    SELECT line_key, checkpoint_stage
                    FROM inv_daily_recon_checkpoint_line
                    WHERE checkpoint_date = " . $this->db->escape($date) . "
                      AND recon_domain = 'MATERIAL'
                      AND division_id = " . (int)$divisionId . "
                      AND line_key IN ({$lineKeySql})
                ";
                $lineQuery = $this->db->query($lineSql);
                foreach ($lineQuery ? $lineQuery->result_array() : [] as $lineRow) {
                    $confirmed[(string)$lineRow['line_key'] . '|' . strtoupper((string)$lineRow['checkpoint_stage'])] = true;
                }
            }
        }

        $confirmMode = $this->daily_recon_confirm_mode();
        foreach ($rows as &$row) {
            $lotKey = $this->material_lot_count_key($row);
            $lotCount = $lotCounts[$lotKey] ?? 0;
            if (isset($lotMeta[$lotKey])) {
                $row['stock_in_first_date'] = (string)($lotMeta[$lotKey]['first_receipt_date'] ?? ($row['stock_in_first_date'] ?? ''));
                $row['stock_in_last_date']  = (string)($lotMeta[$lotKey]['last_receipt_date'] ?? ($row['stock_in_last_date'] ?? ''));
                $row['stock_in_sources']    = (string)($lotMeta[$lotKey]['source_tables'] ?? ($row['stock_in_sources'] ?? ''));
            }
            $reasons = [];
            if ($confirmMode === 'ROW_REQUIRED') {
                $reasons[] = 'mode wajib satu per satu';
            }
            if ($lotCount > 1) {
                $reasons[] = $lotCount . ' lot aktif';
            }
            if ($this->daily_recon_token_matches($requiredTokens, [
                (string)($row['material_id'] ?? ''),
                (string)($row['material_code'] ?? ''),
                (string)($row['material_name'] ?? ''),
                (string)($row['profile_name'] ?? ''),
            ])) {
                $reasons[] = 'daftar wajib recon';
            }

            $lineKey = (string)($row['recon_line_key'] ?? '');
            $row['lot_count'] = $lotCount;
            $row['must_row_confirm'] = !empty($reasons);
            $row['must_row_confirm_reason'] = implode(', ', array_unique($reasons));
            $row['confirmed_open'] = isset($confirmed[$lineKey . '|OPEN']);
            $row['confirmed_close'] = isset($confirmed[$lineKey . '|CLOSE']);
        }
        unset($row);
    }

    private function material_recon_line_key(array $row): string
    {
        return implode('|', [
            'M',
            (int)($row['division_id'] ?? 0),
            strtoupper((string)($row['destination_type'] ?? 'OTHER')),
            (int)($row['item_id'] ?? 0),
            (int)($row['material_id'] ?? 0),
            (int)($row['buy_uom_id'] ?? 0),
            (int)($row['content_uom_id'] ?? 0),
            (string)($row['identity_key'] ?? ($row['profile_key'] ?? '')),
        ]);
    }

    private function material_lot_count_key(array $row): string
    {
        return implode('|', [
            (int)($row['division_id'] ?? 0),
            strtoupper((string)($row['destination_type'] ?? 'OTHER')),
            (int)($row['item_id'] ?? 0),
            (int)($row['material_id'] ?? 0),
            (int)($row['buy_uom_id'] ?? 0),
            (int)($row['content_uom_id'] ?? 0),
            (string)($row['profile_key'] ?? ''),
        ]);
    }

    private function daily_recon_confirm_mode(): string
    {
        $mode = strtoupper(trim($this->daily_recon_config_value('pos.daily_recon_confirm_mode', 'BULK_ALLOWED')));
        return in_array($mode, ['BULK_ALLOWED', 'ROW_REQUIRED'], true) ? $mode : 'BULK_ALLOWED';
    }

    private function daily_recon_config_value(string $key, string $default = ''): string
    {
        if ($key === '' || !$this->db->table_exists('sys_app_config')) {
            return $default;
        }
        $row = $this->db->select('config_value')
            ->from('sys_app_config')
            ->where('config_key', $key)
            ->limit(1)
            ->get()
            ->row_array();
        return $row ? (string)($row['config_value'] ?? $default) : $default;
    }

    private function daily_recon_required_tokens(string $configKey): array
    {
        $raw = $this->daily_recon_config_value($configKey, '');
        $parts = preg_split('/[\r\n,;]+/', $raw) ?: [];
        $tokens = [];
        foreach ($parts as $part) {
            $token = strtoupper(trim((string)$part));
            if ($token !== '') {
                $tokens[$token] = true;
            }
        }
        return $tokens;
    }

    private function daily_recon_token_matches(array $tokens, array $candidates): bool
    {
        if (empty($tokens)) {
            return false;
        }
        foreach ($candidates as $candidate) {
            $value = strtoupper(trim((string)$candidate));
            if ($value !== '' && isset($tokens[$value])) {
                return true;
            }
        }
        return false;
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
