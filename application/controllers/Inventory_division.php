<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/Purchase.php';

class Inventory_division extends Purchase
{
    private const PAGE_OPNAME = 'inventory.stock.opname.division.index';

    public function index()
    {
        parent::stock_division_index();
    }

    public function opening()
    {
        parent::stock_opening_division_index();
    }

    public function adjustment()
    {
        parent::stock_adjustment_division_index();
    }

    public function daily()
    {
        parent::stock_division_daily_index();
    }

    public function compare()
    {
        parent::stock_division_reconcile_index();
    }

    public function reconcile_audit()
    {
        parent::stock_division_reconcile_audit();
    }

    public function reconcile_repair()
    {
        parent::stock_division_reconcile_repair();
    }

    public function movement()
    {
        parent::stock_division_movement_index();
    }

    public function material_matrix()
    {
        parent::stock_material_daily_matrix();
    }

    public function matrix_view()
    {
        parent::inventory_material_daily_index();
    }

    public function lot()
    {
        parent::division_lot_audit_index();
    }

    // ─── Opname Stok Bahan Baku Harian ───────────────────────────────────────

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

        $divisions = $this->Purchase_model->list_active_operational_divisions();

        $this->render('inventory/stock_opname_division_index', [
            'title'        => 'Opname Stok Bahan Baku Divisi',
            'active_menu'  => 'purchase.stock.opname.division',
            'opname_date'  => $opnameDate,
            'division_id'  => $divisionId,
            'destination'  => $destination,
            'divisions'    => $divisions,
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

        if ($divisionId <= 0) {
            $this->output->set_content_type('application/json')
                ->set_output(json_encode(['ok' => true, 'rows' => [], 'meta' => ['info' => 'Pilih divisi terlebih dahulu.']]));
            return;
        }

        $targetMonth = date('Y-m-01', strtotime($opnameDate));

        // ── Ambil stok sistem (monthly closing) ──
        $divNameCol = $this->db->field_exists('division_name', 'mst_operational_division') ? 'division_name'
            : ($this->db->field_exists('name', 'mst_operational_division') ? 'name' : null);
        $divNameExpr = $divNameCol ? ('dv.' . $divNameCol) : 'CAST(dms.division_id AS CHAR)';

        $latestSub = "SELECT division_id, destination_type, identity_key, MAX(month_key) AS max_month
                      FROM inv_division_monthly_stock
                      WHERE month_key <= " . $this->db->escape($targetMonth) . "
                        AND division_id = " . $divisionId . "
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

        $sql = "
            SELECT
                dms.id,
                dms.division_id,
                {$divNameExpr} AS division_name,
                dms.destination_type,
                dms.item_id,
                dms.material_id,
                dms.buy_uom_id,
                dms.content_uom_id,
                COALESCE(dms.profile_key, '')            AS profile_key,
                COALESCE(dms.identity_key, dms.profile_key, '') AS identity_key,
                COALESCE(dms.profile_name, m.material_name, i.item_name, '') AS profile_name,
                COALESCE(dms.profile_brand, '')          AS profile_brand,
                COALESCE(dms.profile_description, '')    AS profile_description,
                dms.profile_expired_date,
                COALESCE(dms.profile_content_per_buy, 1) AS profile_content_per_buy,
                COALESCE(dms.profile_buy_uom_code, '')   AS profile_buy_uom_code,
                COALESCE(dms.profile_content_uom_code, '') AS profile_content_uom_code,
                COALESCE(i.item_code, '')                AS item_code,
                COALESCE(i.item_name, '')                AS item_name,
                COALESCE(m.material_code, '')            AS material_code,
                COALESCE(m.material_name, '')            AS material_name,
                dms.closing_qty_content                  AS system_qty_content,
                dms.closing_qty_buy                      AS system_qty_buy,
                dms.avg_cost_per_content,
                dms.total_value
            FROM inv_division_monthly_stock dms
            INNER JOIN ({$latestSub}) lm
                ON  lm.division_id      = dms.division_id
                AND lm.destination_type = dms.destination_type
                AND lm.identity_key     = dms.identity_key
                AND lm.max_month        = dms.month_key
            LEFT JOIN mst_item     i  ON i.id  = dms.item_id
            LEFT JOIN mst_material m  ON m.id  = COALESCE(dms.material_id, i.material_id)
            LEFT JOIN mst_operational_division dv ON dv.id = dms.division_id
            WHERE dms.division_id = {$divisionId}
              AND dms.material_id IS NOT NULL
              {$destWhere}
              {$qWhere}
            ORDER BY COALESCE(m.material_name, i.item_name), dms.profile_name
        ";

        $stockResult = $this->db->query($sql);
        $stockRows   = $stockResult ? $stockResult->result_array() : [];

        // ── Ambil opname hari ini ──
        $opnameRows = $this->db->table_exists('inv_division_stock_opname')
            ? $this->db->select('identity_key, physical_qty_content, notes, adjustment_id')
                ->from('inv_division_stock_opname')
                ->where('opname_date', $opnameDate)
                ->where('division_id', $divisionId)
                ->get()->result_array()
            : [];

        $opnameMap = [];
        foreach ($opnameRows as $r) {
            $opnameMap[(string)$r['identity_key']] = $r;
        }

        // ── Merge dan group by material ──
        $materialGroups = [];
        foreach ($stockRows as $r) {
            $matKey = (string)($r['material_id'] ?: ('item_' . $r['item_id']));
            $identKey = (string)$r['identity_key'];
            $opname = $opnameMap[$identKey] ?? null;

            $row = [
                'item_id'            => (int)$r['item_id'],
                'material_id'        => (int)$r['material_id'],
                'buy_uom_id'         => (int)$r['buy_uom_id'],
                'content_uom_id'     => (int)$r['content_uom_id'],
                'profile_key'        => $r['profile_key'],
                'identity_key'       => $identKey,
                'profile_name'       => $r['profile_name'],
                'profile_brand'      => $r['profile_brand'],
                'profile_description'=> $r['profile_description'],
                'profile_expired_date'=> $r['profile_expired_date'],
                'profile_content_per_buy' => (float)$r['profile_content_per_buy'],
                'profile_buy_uom_code'    => $r['profile_buy_uom_code'],
                'profile_content_uom_code'=> $r['profile_content_uom_code'],
                'item_code'          => $r['item_code'],
                'item_name'          => $r['item_name'],
                'material_code'      => $r['material_code'],
                'material_name'      => $r['material_name'],
                'system_qty_content' => (float)$r['system_qty_content'],
                'system_qty_buy'     => (float)$r['system_qty_buy'],
                'avg_cost_per_content'=> (float)$r['avg_cost_per_content'],
                'total_value'        => (float)$r['total_value'],
                'physical_qty_content'=> $opname !== null && $opname['physical_qty_content'] !== null
                    ? (float)$opname['physical_qty_content'] : null,
                'selisih'            => $opname !== null && $opname['physical_qty_content'] !== null
                    ? round((float)$opname['physical_qty_content'] - (float)$r['system_qty_content'], 4) : null,
                'opname_notes'       => (string)($opname['notes'] ?? ''),
                'adjustment_id'      => $opname ? (int)$opname['adjustment_id'] : null,
            ];

            if (!isset($materialGroups[$matKey])) {
                $materialGroups[$matKey] = [
                    'material_id'   => (int)$r['material_id'],
                    'material_code' => $r['material_code'],
                    'material_name' => $r['material_name'] ?: $r['item_name'],
                    'item_id'       => (int)$r['item_id'],
                    'item_code'     => $r['item_code'],
                    'content_uom_code' => $r['profile_content_uom_code'],
                    'profiles'      => [],
                    'system_total'  => 0.0,
                    'physical_total'=> null,
                ];
            }
            $materialGroups[$matKey]['profiles'][] = $row;
            $materialGroups[$matKey]['system_total'] += $row['system_qty_content'];
            if ($row['physical_qty_content'] !== null) {
                $materialGroups[$matKey]['physical_total'] = ($materialGroups[$matKey]['physical_total'] ?? 0) + $row['physical_qty_content'];
            }
        }

        $this->output->set_content_type('application/json')
            ->set_output(json_encode([
                'ok'   => true,
                'rows' => array_values($materialGroups),
                'meta' => [
                    'opname_date' => $opnameDate,
                    'division_id' => $divisionId,
                    'total_materials' => count($materialGroups),
                    'total_profiles'  => count($stockRows),
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
        $physicalQty = $payload['physical_qty_content'] !== '' && $payload['physical_qty_content'] !== null
            ? round((float)$payload['physical_qty_content'], 4)
            : null;
        $notes       = trim((string)($payload['notes'] ?? ''));
        $userId      = (int)($this->current_user['id'] ?? 0);

        if ($divisionId <= 0 || $identKey === '') {
            $this->output->set_content_type('application/json')->set_status_header(422)
                ->set_output(json_encode(['ok' => false, 'message' => 'division_id dan identity_key wajib diisi.']));
            return;
        }

        if (!$this->db->table_exists('inv_division_stock_opname')) {
            $this->output->set_content_type('application/json')->set_status_header(500)
                ->set_output(json_encode(['ok' => false, 'message' => 'Tabel opname belum ada. Jalankan SQL setup terlebih dahulu.']));
            return;
        }

        $systemQty = (float)($payload['system_qty_content'] ?? 0);

        $existing = $this->db
            ->where('opname_date', $opnameDate)
            ->where('division_id', $divisionId)
            ->where('destination_type', $destination)
            ->where('identity_key', $identKey)
            ->get('inv_division_stock_opname')->row_array();

        $selisih = $physicalQty !== null ? round($physicalQty - $systemQty, 4) : null;

        if ($existing) {
            $this->db->where('id', (int)$existing['id'])->update('inv_division_stock_opname', [
                'physical_qty_content' => $physicalQty,
                'system_qty_content'   => $systemQty,
                'notes'                => $notes !== '' ? $notes : null,
                'updated_at'           => date('Y-m-d H:i:s'),
            ]);
        } else {
            $this->db->insert('inv_division_stock_opname', [
                'opname_date'          => $opnameDate,
                'division_id'          => $divisionId,
                'destination_type'     => $destination,
                'item_id'              => !empty($payload['item_id']) ? (int)$payload['item_id'] : null,
                'material_id'          => !empty($payload['material_id']) ? (int)$payload['material_id'] : null,
                'buy_uom_id'           => !empty($payload['buy_uom_id']) ? (int)$payload['buy_uom_id'] : null,
                'content_uom_id'       => (int)($payload['content_uom_id'] ?? 0),
                'profile_key'          => (string)($payload['profile_key'] ?? ''),
                'identity_key'         => $identKey,
                'profile_name'         => $this->nullableString($payload['profile_name'] ?? null),
                'profile_content_per_buy' => max(0.000001, (float)($payload['profile_content_per_buy'] ?? 1)),
                'profile_buy_uom_code'    => $this->nullableString($payload['profile_buy_uom_code'] ?? null),
                'profile_content_uom_code'=> $this->nullableString($payload['profile_content_uom_code'] ?? null),
                'system_qty_content'   => $systemQty,
                'physical_qty_content' => $physicalQty,
                'notes'                => $notes !== '' ? $notes : null,
                'created_by'           => $userId > 0 ? $userId : null,
            ]);
        }

        $this->output->set_content_type('application/json')
            ->set_output(json_encode([
                'ok'      => true,
                'selisih' => $selisih,
                'physical_qty_content' => $physicalQty,
            ]));
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
        $physicalQty = (float)($payload['physical_qty_content'] ?? 0);
        $systemQty   = (float)($payload['system_qty_content'] ?? 0);
        $selisih     = round($physicalQty - $systemQty, 4);
        $adjType     = strtoupper(trim((string)($payload['adjustment_type'] ?? '')));
        $notes       = trim((string)($payload['notes'] ?? ''));
        $userId      = (int)($this->current_user['id'] ?? 0);

        if ($divisionId <= 0 || $identKey === '' || $selisih == 0) {
            $this->output->set_content_type('application/json')->set_status_header(422)
                ->set_output(json_encode(['ok' => false, 'message' => 'Selisih 0 atau parameter tidak lengkap.']));
            return;
        }

        $validNeg = ['WASTE', 'SPOIL', 'PROCESS_LOSS', 'VARIANCE', 'ADJUSTMENT_MINUS'];
        $validPos = ['ADJUSTMENT_PLUS'];

        if ($selisih < 0 && !in_array($adjType, $validNeg, true)) {
            $adjType = 'VARIANCE';
        }
        if ($selisih > 0 && !in_array($adjType, $validPos, true)) {
            $adjType = 'ADJUSTMENT_PLUS';
        }

        $adjNo = 'OPN-' . $opnameDate . '-' . strtoupper(substr($identKey, 0, 8));

        $line = [
            'item_id'            => !empty($payload['item_id'])        ? (int)$payload['item_id']        : null,
            'material_id'        => !empty($payload['material_id'])     ? (int)$payload['material_id']     : null,
            'buy_uom_id'         => !empty($payload['buy_uom_id'])      ? (int)$payload['buy_uom_id']      : null,
            'content_uom_id'     => (int)($payload['content_uom_id'] ?? 0),
            'profile_key'        => $this->nullableString($payload['profile_key'] ?? null),
            'profile_name'       => $this->nullableString($payload['profile_name'] ?? null),
            'profile_brand'      => $this->nullableString($payload['profile_brand'] ?? null),
            'profile_description'=> $this->nullableString($payload['profile_description'] ?? null),
            'profile_expired_date'=> $this->normalizeDate((string)($payload['profile_expired_date'] ?? '')),
            'profile_content_per_buy' => max(0.000001, (float)($payload['profile_content_per_buy'] ?? 1)),
            'profile_buy_uom_code'    => $this->nullableString($payload['profile_buy_uom_code'] ?? null),
            'profile_content_uom_code'=> $this->nullableString($payload['profile_content_uom_code'] ?? null),
            'note'               => $notes,
        ];

        $absQty = round(abs($selisih), 4);

        if ($selisih < 0) {
            $reasonMap = [
                'WASTE'            => ['qty_waste_content'        => $absQty, 'waste_reason_code'        => 'other'],
                'SPOIL'            => ['qty_spoil_content'        => $absQty, 'spoil_reason_code'        => 'other'],
                'PROCESS_LOSS'     => ['qty_process_loss_content' => $absQty, 'process_loss_reason_code' => 'other'],
                'VARIANCE'         => ['qty_variance_content'     => $absQty, 'variance_reason_code'     => 'other'],
                'ADJUSTMENT_MINUS' => ['qty_variance_content'     => $absQty, 'variance_reason_code'     => 'adjustment'],
            ];
            $line = array_merge($line, $reasonMap[$adjType] ?? ['qty_variance_content' => $absQty, 'variance_reason_code' => 'other']);
        } else {
            $unitCost = (float)($payload['avg_cost_per_content'] ?? 0);
            $line['qty_adjustment_plus_content'] = $absQty;
            $line['adjustment_plus_reason_code'] = 'other';
            $line['unit_cost'] = $unitCost > 0 ? $unitCost : null;
        }

        $result = $this->Purchase_model->save_stock_adjustment([
            'id'               => 0,
            'adjustment_no'    => $adjNo,
            'adjustment_date'  => $opnameDate,
            'stock_scope'      => 'DIVISION',
            'division_id'      => $divisionId,
            'destination_type' => $destination,
            'notes'            => 'Quick adjust dari opname stok: ' . $notes,
        ], [$line], $userId);

        if (!($result['ok'] ?? false)) {
            $this->output->set_content_type('application/json')->set_status_header(422)
                ->set_output(json_encode(['ok' => false, 'message' => (string)($result['message'] ?? 'Gagal menyimpan adjustment.')]));
            return;
        }

        $adjId = (int)($result['id'] ?? 0);
        $post  = $this->Purchase_model->post_stock_adjustment($adjId, $userId);

        if (!($post['ok'] ?? false)) {
            $this->output->set_content_type('application/json')->set_status_header(422)
                ->set_output(json_encode(['ok' => false, 'message' => 'Tersimpan tapi gagal posting: ' . (string)($post['message'] ?? '')]));
            return;
        }

        // Update opname record dengan adjustment_id
        if ($this->db->table_exists('inv_division_stock_opname') && $adjId > 0) {
            $this->db
                ->where('opname_date',     $opnameDate)
                ->where('division_id',     $divisionId)
                ->where('destination_type',$destination)
                ->where('identity_key',    $identKey)
                ->update('inv_division_stock_opname', ['adjustment_id' => $adjId]);
        }

        $this->output->set_content_type('application/json')
            ->set_output(json_encode(['ok' => true, 'adjustment_id' => $adjId, 'message' => 'Adjustment berhasil diposting.']));
    }

    private function request_payload(): array
    {
        $raw = $this->input->raw_input_stream ?? null;
        if ($raw === null) {
            $raw = file_get_contents('php://input');
        }
        if (!empty($raw)) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                return $json;
            }
        }
        $post = $this->input->post(null, false);
        return is_array($post) ? $post : [];
    }
}
