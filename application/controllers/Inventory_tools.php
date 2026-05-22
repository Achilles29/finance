<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Inventory_tools extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!$this->input->is_cli_request()) {
            show_404();
            return;
        }
        $this->load->database();
        $this->load->model('Purchase_model');
        $this->load->model('Production_model');
    }

    public function smoke_test()
    {
        $cliArgs = $this->parseCliArgs();
        $userId = (int)($cliArgs['user_id'] ?? 0);
        $smokeDate = (string)($cliArgs['date'] ?? date('Y-m-d'));

        $this->load->model('Procurement_model');

        $fixtures = $this->resolveSmokeFixtures();
        if (!($fixtures['ok'] ?? false)) {
            fwrite(STDERR, json_encode($fixtures, JSON_PRETTY_PRINT) . PHP_EOL);
            exit(1);
        }
        $fx = (array)($fixtures['data'] ?? []);

        $cases = [];

        $cases[] = $this->runSmokeCase('po_gudang_received', function () use ($fx, $smokeDate, $userId) {
            $type = (array)$fx['purchase_types']['INV_STOK'];
            $result = $this->Purchase_model->store_order_with_lines([
                'request_date' => $smokeDate,
                'expected_date' => $smokeDate,
                'purchase_type_id' => (int)$type['id'],
                'vendor_id' => (int)$fx['vendor']['id'],
                'status' => 'RECEIVED',
                'notes' => 'Smoke test PO gudang',
            ], [
                $this->buildSmokeInventoryPurchaseLine((array)$fx['inventory_profile'], 1.0),
            ], $userId, 'CLI_SMOKE');

            if (!($result['ok'] ?? false)) {
                return $result;
            }

            $poId = (int)($result['data']['purchase_order_id'] ?? 0);
            $receipt = $this->db->from('pur_purchase_receipt')->where('purchase_order_id', $poId)->where('status', 'POSTED')->limit(1)->get()->row_array();
            if (!$receipt) {
                return ['ok' => false, 'message' => 'Receipt gudang tidak terbentuk untuk PO RECEIVED.'];
            }

            $movementSummary = $this->summarizeMovementByRef('pur_purchase_receipt', (int)$receipt['id']);
            if (!$this->movementSummaryHas($movementSummary, 'WAREHOUSE', 'PURCHASE_IN')) {
                return ['ok' => false, 'message' => 'PO gudang tidak menghasilkan movement WAREHOUSE PURCHASE_IN.', 'data' => ['movement_summary' => $movementSummary]];
            }
            if ($this->movementSummaryHasScope($movementSummary, 'DIVISION')) {
                return ['ok' => false, 'message' => 'PO gudang seharusnya tidak menghasilkan movement DIVISION.', 'data' => ['movement_summary' => $movementSummary]];
            }

            return [
                'ok' => true,
                'message' => 'PO gudang berhasil diposting ke stok gudang.',
                'data' => [
                    'po_id' => $poId,
                    'receipt_id' => (int)$receipt['id'],
                    'destination_type' => (string)($receipt['destination_type'] ?? ''),
                    'movement_summary' => $movementSummary,
                ],
            ];
        });

        $cases[] = $this->runSmokeCase('po_division_stock_received', function () use ($fx, $smokeDate, $userId) {
            $type = $this->firstSmokeType($fx['purchase_types'], ['INV_BAR', 'BAR_STOK', 'KITCHEN_STOK', 'INV_KITCHEN']);
            if ($type === null) {
                return ['ok' => false, 'message' => 'Purchase type stok divisi tidak tersedia.'];
            }

            $result = $this->Purchase_model->store_order_with_lines([
                'request_date' => $smokeDate,
                'expected_date' => $smokeDate,
                'purchase_type_id' => (int)$type['id'],
                'vendor_id' => (int)$fx['vendor']['id'],
                'status' => 'RECEIVED',
                'notes' => 'Smoke test PO stok divisi',
            ], [
                $this->buildSmokeInventoryPurchaseLine((array)$fx['inventory_profile'], 1.0),
            ], $userId, 'CLI_SMOKE');

            if (!($result['ok'] ?? false)) {
                return $result;
            }

            $poId = (int)($result['data']['purchase_order_id'] ?? 0);
            $receipt = $this->db->from('pur_purchase_receipt')->where('purchase_order_id', $poId)->where('status', 'POSTED')->limit(1)->get()->row_array();
            if (!$receipt) {
                return ['ok' => false, 'message' => 'Receipt stok divisi tidak terbentuk untuk PO RECEIVED.'];
            }

            $movementSummary = $this->summarizeMovementByRef('pur_purchase_receipt', (int)$receipt['id']);
            if (!$this->movementSummaryHas($movementSummary, 'DIVISION', 'PURCHASE_IN')) {
                return ['ok' => false, 'message' => 'PO stok divisi tidak menghasilkan movement DIVISION PURCHASE_IN.', 'data' => ['movement_summary' => $movementSummary]];
            }
            if ($this->movementSummaryHasScope($movementSummary, 'WAREHOUSE')) {
                return ['ok' => false, 'message' => 'PO stok divisi seharusnya tidak menghasilkan movement WAREHOUSE.', 'data' => ['movement_summary' => $movementSummary]];
            }

            return [
                'ok' => true,
                'message' => 'PO stok divisi berhasil diposting ke stok divisi.',
                'data' => [
                    'po_id' => $poId,
                    'receipt_id' => (int)$receipt['id'],
                    'destination_type' => (string)($receipt['destination_type'] ?? ''),
                    'movement_summary' => $movementSummary,
                ],
            ];
        });

        $cases[] = $this->runSmokeCase('po_operasional_office_received', function () use ($fx, $smokeDate, $userId) {
            if (empty($fx['purchase_types']['OPERASIONAL_OFFICE'])) {
                return ['ok' => false, 'message' => 'Purchase type OPERASIONAL_OFFICE tidak tersedia.'];
            }

            $result = $this->Purchase_model->store_order_with_lines([
                'request_date' => $smokeDate,
                'expected_date' => $smokeDate,
                'purchase_type_id' => (int)$fx['purchase_types']['OPERASIONAL_OFFICE']['id'],
                'vendor_id' => (int)$fx['vendor']['id'],
                'status' => 'RECEIVED',
                'notes' => 'Smoke test PO operasional office',
            ], [
                $this->buildSmokeInventoryPurchaseLine((array)$fx['inventory_profile'], 1.0),
            ], $userId, 'CLI_SMOKE');

            if (!($result['ok'] ?? false)) {
                return $result;
            }

            $poId = (int)($result['data']['purchase_order_id'] ?? 0);
            $receipt = $this->db->from('pur_purchase_receipt')->where('purchase_order_id', $poId)->where('status', 'POSTED')->limit(1)->get()->row_array();
            if (!$receipt) {
                return ['ok' => false, 'message' => 'Receipt operasional office tidak terbentuk.'];
            }

            $movementSummary = $this->summarizeMovementByRef('pur_purchase_receipt', (int)$receipt['id']);
            if (!$this->movementSummaryHas($movementSummary, 'DIVISION', 'PURCHASE_IN')) {
                return ['ok' => false, 'message' => 'PO operasional office tidak menghasilkan movement DIVISION PURCHASE_IN.', 'data' => ['movement_summary' => $movementSummary]];
            }

            $hasOfficeDestination = false;
            foreach ($movementSummary as $row) {
                if ((string)($row['movement_scope'] ?? '') === 'DIVISION' && (string)($row['destination_type'] ?? '') === 'OFFICE') {
                    $hasOfficeDestination = true;
                    break;
                }
            }
            if (!$hasOfficeDestination) {
                return ['ok' => false, 'message' => 'PO operasional office tidak mendarat ke destination OFFICE.', 'data' => ['movement_summary' => $movementSummary]];
            }

            return [
                'ok' => true,
                'message' => 'PO operasional office berhasil diposting ke stok divisi OFFICE.',
                'data' => [
                    'po_id' => $poId,
                    'receipt_id' => (int)$receipt['id'],
                    'destination_type' => (string)($receipt['destination_type'] ?? ''),
                    'movement_summary' => $movementSummary,
                ],
            ];
        });

        $cases[] = $this->runSmokeCase('po_service_paid', function () use ($fx, $smokeDate, $userId) {
            if (empty($fx['purchase_types']['JASA'])) {
                return ['ok' => false, 'message' => 'Purchase type JASA tidak tersedia.'];
            }

            $result = $this->Purchase_model->store_order_with_lines([
                'request_date' => $smokeDate,
                'expected_date' => $smokeDate,
                'purchase_type_id' => (int)$fx['purchase_types']['JASA']['id'],
                'vendor_id' => (int)$fx['vendor']['id'],
                'payment_account_id' => (int)$fx['account']['id'],
                'status' => 'PAID',
                'notes' => 'Smoke test PO jasa',
            ], [
                [
                    'line_kind' => 'SERVICE',
                    'buy_uom_id' => (int)$fx['uom_id'],
                    'qty_buy' => 1,
                    'content_per_buy' => 1,
                    'conversion_factor_to_content' => 1,
                    'unit_price' => 125000,
                    'item_name' => 'SMOKE SERVICE',
                    'line_description' => 'Smoke test service',
                ],
            ], $userId, 'CLI_SMOKE');

            if (!($result['ok'] ?? false)) {
                return $result;
            }

            $poId = (int)($result['data']['purchase_order_id'] ?? 0);
            $paymentCount = (int)$this->db->from('pur_purchase_payment_plan')->where('purchase_order_id', $poId)->where('status', 'PAID')->count_all_results();
            $receiptCount = (int)$this->db->from('pur_purchase_receipt')->where('purchase_order_id', $poId)->count_all_results();
            $movementCount = (int)$this->db->from('inv_stock_movement_log')->where('ref_table', 'pur_purchase_receipt')->count_all_results();

            if ($paymentCount <= 0) {
                return ['ok' => false, 'message' => 'PO jasa status PAID tidak membentuk payment plan PAID.'];
            }
            if ($receiptCount > 0) {
                return ['ok' => false, 'message' => 'PO jasa seharusnya tidak membentuk receipt.'];
            }

            return [
                'ok' => true,
                'message' => 'PO jasa tidak menyentuh stok dan pembayaran otomatis terbentuk.',
                'data' => [
                    'po_id' => $poId,
                    'payment_count' => $paymentCount,
                    'receipt_count' => $receiptCount,
                    'movement_count_all_receipts_in_tx' => $movementCount,
                ],
            ];
        });

        $cases[] = $this->runSmokeCase('po_expense_item_paid', function () use ($fx, $smokeDate, $userId) {
            $type = $this->firstSmokeType($fx['purchase_types'], ['BAR_OPS', 'EXP_UTIL', 'BEBAN']);
            if ($type === null) {
                return ['ok' => false, 'message' => 'Purchase type expense tidak tersedia.'];
            }

            $result = $this->Purchase_model->store_order_with_lines([
                'request_date' => $smokeDate,
                'expected_date' => $smokeDate,
                'purchase_type_id' => (int)$type['id'],
                'vendor_id' => (int)$fx['vendor']['id'],
                'payment_account_id' => (int)$fx['account']['id'],
                'status' => 'PAID',
                'notes' => 'Smoke test PO expense item',
            ], [
                [
                    'line_kind' => 'ITEM',
                    'buy_uom_id' => (int)$fx['uom_id'],
                    'content_uom_id' => (int)$fx['uom_id'],
                    'qty_buy' => 1,
                    'content_per_buy' => 1,
                    'conversion_factor_to_content' => 1,
                    'unit_price' => 50000,
                    'item_name' => 'SMOKE EXPENSE ITEM',
                    'line_description' => 'Smoke test expense item',
                ],
            ], $userId, 'CLI_SMOKE');

            if (!($result['ok'] ?? false)) {
                return $result;
            }

            $poId = (int)($result['data']['purchase_order_id'] ?? 0);
            $paymentCount = (int)$this->db->from('pur_purchase_payment_plan')->where('purchase_order_id', $poId)->where('status', 'PAID')->count_all_results();
            $receiptCount = (int)$this->db->from('pur_purchase_receipt')->where('purchase_order_id', $poId)->count_all_results();
            if ($paymentCount <= 0) {
                return ['ok' => false, 'message' => 'PO expense status PAID tidak membentuk payment plan PAID.'];
            }
            if ($receiptCount > 0) {
                return ['ok' => false, 'message' => 'PO expense seharusnya tidak membentuk receipt.'];
            }

            return [
                'ok' => true,
                'message' => 'PO expense item tidak menyentuh stok dan pembayaran otomatis terbentuk.',
                'data' => [
                    'po_id' => $poId,
                    'payment_count' => $paymentCount,
                    'receipt_count' => $receiptCount,
                    'type_code' => (string)$type['type_code'],
                ],
            ];
        });

        $cases[] = $this->runSmokeCase('sr_fulfill_division', function () use ($fx, $smokeDate, $userId) {
            $qty = $this->smokeRequestQty((array)$fx['inventory_profile'], false);
            $create = $this->Procurement_model->create_store_request([
                'request_date' => $smokeDate,
                'needed_date' => $smokeDate,
                'request_division_id' => (int)$fx['sr_division']['id'],
                'destination_type' => (string)$fx['sr_destination'],
                'status' => 'DRAFT',
                'notes' => 'Smoke test SR fulfill',
            ], [
                $this->buildSmokeStoreRequestLine((array)$fx['inventory_profile'], $qty['qty_buy'], $qty['qty_content']),
            ], $userId);
            if (!($create['ok'] ?? false)) {
                return $create;
            }

            $requestId = (int)($create['id'] ?? 0);
            $submit = $this->Procurement_model->apply_store_request_action($requestId, 'SUBMIT', 'Smoke submit', $userId);
            if (!($submit['ok'] ?? false)) {
                return $submit;
            }
            $approve = $this->Procurement_model->apply_store_request_action($requestId, 'APPROVE', 'Smoke approve', $userId);
            if (!($approve['ok'] ?? false)) {
                return $approve;
            }

            $fulfill = $this->Procurement_model->fulfill_auto_from_warehouse($requestId, $smokeDate, 'Smoke fulfill', $userId);
            if (!($fulfill['ok'] ?? false)) {
                return $fulfill;
            }

            $fulfillmentId = (int)($fulfill['data']['fulfillment_id'] ?? 0);
            $movementSummary = $this->summarizeMovementByRef('pur_store_request_fulfillment', $fulfillmentId);
            if (!$this->movementSummaryHas($movementSummary, 'WAREHOUSE', 'TRANSFER_OUT')) {
                return ['ok' => false, 'message' => 'SR fulfill tidak menghasilkan movement WAREHOUSE TRANSFER_OUT.', 'data' => ['movement_summary' => $movementSummary]];
            }
            if (!$this->movementSummaryHas($movementSummary, 'DIVISION', 'TRANSFER_IN')) {
                return ['ok' => false, 'message' => 'SR fulfill tidak menghasilkan movement DIVISION TRANSFER_IN.', 'data' => ['movement_summary' => $movementSummary]];
            }

            return [
                'ok' => true,
                'message' => 'SR fulfill berhasil memindahkan stok gudang ke divisi.',
                'data' => [
                    'request_id' => $requestId,
                    'fulfillment_id' => $fulfillmentId,
                    'status_after' => (string)($fulfill['data']['status_after'] ?? ''),
                    'movement_summary' => $movementSummary,
                ],
            ];
        });

        $cases[] = $this->runSmokeCase('component_batch_material_usage_trace', function () use ($fx, $smokeDate, $userId) {
            $qty = $this->smokeRequestQty((array)$fx['inventory_profile'], false);
            $create = $this->Procurement_model->create_store_request([
                'request_date' => $smokeDate,
                'needed_date' => $smokeDate,
                'request_division_id' => (int)$fx['sr_division']['id'],
                'destination_type' => (string)$fx['sr_destination'],
                'status' => 'DRAFT',
                'notes' => 'Smoke test material usage batch',
            ], [
                $this->buildSmokeStoreRequestLine((array)$fx['inventory_profile'], $qty['qty_buy'], $qty['qty_content']),
            ], $userId);
            if (!($create['ok'] ?? false)) {
                return $create;
            }

            $requestId = (int)($create['id'] ?? 0);
            $submit = $this->Procurement_model->apply_store_request_action($requestId, 'SUBMIT', 'Smoke submit', $userId);
            if (!($submit['ok'] ?? false)) {
                return $submit;
            }
            $approve = $this->Procurement_model->apply_store_request_action($requestId, 'APPROVE', 'Smoke approve', $userId);
            if (!($approve['ok'] ?? false)) {
                return $approve;
            }
            $fulfill = $this->Procurement_model->fulfill_auto_from_warehouse($requestId, $smokeDate, 'Smoke fulfill untuk batch material', $userId);
            if (!($fulfill['ok'] ?? false)) {
                return $fulfill;
            }

            $component = (array)($fx['component'] ?? []);
            if (empty($component)) {
                return ['ok' => false, 'message' => 'Component aktif untuk smoke test batch material tidak tersedia.'];
            }

            $save = $this->Production_model->save_component_batch([
                'batch_date' => $smokeDate,
                'location_type' => (string)$fx['sr_destination'],
                'division_id' => (int)$fx['sr_division']['id'],
                'component_id' => (int)$component['id'],
                'output_qty' => 1,
                'output_uom_id' => (int)($component['uom_id'] ?? 0),
                'notes' => 'Smoke batch material usage',
            ], [[
                'source_kind' => 'MATERIAL',
                'item_id' => (int)($fx['inventory_profile']['item_id'] ?? 0) > 0 ? (int)$fx['inventory_profile']['item_id'] : null,
                'material_id' => (int)($fx['inventory_profile']['material_id'] ?? 0) > 0 ? (int)$fx['inventory_profile']['material_id'] : null,
                'uom_id' => (int)($fx['inventory_profile']['content_uom_id'] ?? 0),
                'qty' => (float)$qty['qty_content'],
                'unit_cost' => 0,
                'notes' => 'Smoke input material',
            ]], $userId);
            if (!($save['ok'] ?? false)) {
                return $save;
            }

            $batchId = (int)($save['id'] ?? 0);
            $header = $this->Production_model->get_component_batch($batchId);
            $inputs = $this->Production_model->get_component_batch_inputs($batchId);

            $this->load->library('ComponentStockWriter');
            $post = $this->componentstockwriter->post_batch((array)$header, $inputs, $userId);
            if (!($post['ok'] ?? false)) {
                return $post;
            }

            $this->db->where('id', $batchId)->update('inv_component_batch', [
                'status' => 'POSTED',
                'posted_at' => date('Y-m-d H:i:s'),
                'posted_by' => $userId > 0 ? $userId : null,
            ]);

            $movementSummary = $this->summarizeMovementByRef('inv_component_batch', $batchId);
            if (!$this->movementSummaryHas($movementSummary, 'DIVISION', 'USAGE_OUT')) {
                return ['ok' => false, 'message' => 'Batch material tidak menghasilkan movement DIVISION USAGE_OUT.', 'data' => ['movement_summary' => $movementSummary]];
            }

            $issueCount = (int)$this->db->from('inv_material_fifo_issue_log')
                ->where('source_table', 'inv_component_batch')
                ->where('source_id', $batchId)
                ->where('status', 'POSTED')
                ->count_all_results();
            if ($issueCount <= 0) {
                return ['ok' => false, 'message' => 'Batch material tidak membentuk issue log FIFO divisi.'];
            }

            $componentMovementCount = (int)$this->db->from('inv_component_movement_log')
                ->where('source_table', 'inv_component_batch')
                ->where('source_id', $batchId)
                ->where('movement_type', 'PRODUCTION_IN')
                ->count_all_results();
            if ($componentMovementCount <= 0) {
                return ['ok' => false, 'message' => 'Batch material tidak menghasilkan movement component PRODUCTION_IN.'];
            }

            return [
                'ok' => true,
                'message' => 'Batch material berhasil mengonsumsi lot FIFO divisi dan menghasilkan output component.',
                'data' => [
                    'batch_id' => $batchId,
                    'request_id' => $requestId,
                    'issue_count' => $issueCount,
                    'component_movement_count' => $componentMovementCount,
                    'movement_summary' => $movementSummary,
                ],
            ];
        });

        $cases[] = $this->runSmokeCase('sr_shortage_to_draft_po', function () use ($fx, $smokeDate, $userId) {
            $qty = $this->smokeRequestQty((array)$fx['inventory_profile'], true);
            $create = $this->Procurement_model->create_store_request([
                'request_date' => $smokeDate,
                'needed_date' => $smokeDate,
                'request_division_id' => (int)$fx['sr_division']['id'],
                'destination_type' => (string)$fx['sr_destination'],
                'status' => 'DRAFT',
                'notes' => 'Smoke test SR shortage',
            ], [
                $this->buildSmokeStoreRequestLine((array)$fx['inventory_profile'], $qty['qty_buy'], $qty['qty_content']),
            ], $userId);
            if (!($create['ok'] ?? false)) {
                return $create;
            }

            $requestId = (int)($create['id'] ?? 0);
            $submit = $this->Procurement_model->apply_store_request_action($requestId, 'SUBMIT', 'Smoke submit', $userId);
            if (!($submit['ok'] ?? false)) {
                return $submit;
            }
            $approve = $this->Procurement_model->apply_store_request_action($requestId, 'APPROVE', 'Smoke approve', $userId);
            if (!($approve['ok'] ?? false)) {
                return $approve;
            }

            $payload = $this->Procurement_model->get_shortage_po_payload($requestId);
            if (!($payload['ok'] ?? false)) {
                return $payload;
            }

            $purchaseTypeId = (int)($this->Procurement_model->find_inventory_purchase_type_id() ?? 0);
            if ($purchaseTypeId <= 0) {
                return ['ok' => false, 'message' => 'Purchase type inventory untuk draft PO shortage tidak ditemukan.'];
            }

            $header = (array)($payload['header'] ?? []);
            $poResult = $this->Purchase_model->store_order_with_lines([
                'request_date' => (string)($header['request_date'] ?? $smokeDate),
                'expected_date' => (string)($header['needed_date'] ?? $smokeDate),
                'purchase_type_id' => $purchaseTypeId,
                'vendor_id' => (int)$fx['vendor']['id'],
                'destination_type' => 'GUDANG',
                'status' => 'DRAFT',
                'notes' => 'Smoke draft PO shortage dari SR',
                'external_ref_no' => (string)($header['sr_no'] ?? ('SR#' . $requestId)),
            ], (array)($payload['lines'] ?? []), $userId, 'CLI_SMOKE');
            if (!($poResult['ok'] ?? false)) {
                return $poResult;
            }

            $poId = (int)($poResult['data']['purchase_order_id'] ?? 0);
            $this->Procurement_model->link_store_request_po($requestId, $poId, 'SHORTAGE', 'Smoke shortage link', $userId);

            $receiptCount = (int)$this->db->from('pur_purchase_receipt')->where('purchase_order_id', $poId)->count_all_results();
            $linkCount = (int)$this->db->from('pur_store_request_po_link')->where('store_request_id', $requestId)->where('purchase_order_id', $poId)->count_all_results();
            if ($receiptCount > 0) {
                return ['ok' => false, 'message' => 'Draft PO shortage seharusnya belum membentuk receipt.'];
            }
            if ($linkCount <= 0) {
                return ['ok' => false, 'message' => 'Link SR ke draft PO shortage tidak terbentuk.'];
            }

            return [
                'ok' => true,
                'message' => 'SR shortage berhasil menghasilkan draft PO tanpa mutasi stok baru.',
                'data' => [
                    'request_id' => $requestId,
                    'purchase_order_id' => $poId,
                    'shortage_line_count' => count((array)($payload['lines'] ?? [])),
                    'po_link_count' => $linkCount,
                ],
            ];
        });

        $cases[] = $this->runSmokeCase('po_void_rollback_received', function () use ($fx, $smokeDate, $userId) {
            $type = (array)$fx['purchase_types']['INV_STOK'];
            $create = $this->Purchase_model->store_order_with_lines([
                'request_date' => $smokeDate,
                'expected_date' => $smokeDate,
                'purchase_type_id' => (int)$type['id'],
                'vendor_id' => (int)$fx['vendor']['id'],
                'status' => 'RECEIVED',
                'notes' => 'Smoke test PO void rollback receipt',
            ], [
                $this->buildSmokeInventoryPurchaseLine((array)$fx['inventory_profile'], 1.0),
            ], $userId, 'CLI_SMOKE');
            if (!($create['ok'] ?? false)) {
                return $create;
            }

            $poId = (int)($create['data']['purchase_order_id'] ?? 0);
            $receipt = $this->db->from('pur_purchase_receipt')->where('purchase_order_id', $poId)->where('status', 'POSTED')->limit(1)->get()->row_array();
            if (!$receipt) {
                return ['ok' => false, 'message' => 'Receipt awal tidak terbentuk untuk uji VOID PO.'];
            }

            $beforeSummary = $this->summarizeMovementByRef('pur_purchase_receipt', (int)$receipt['id']);
            $voidResult = $this->Purchase_model->update_order_status($poId, 'VOID', $userId, 'CLI_SMOKE');
            if (!($voidResult['ok'] ?? false)) {
                return $voidResult;
            }

            $receiptAfter = $this->db->from('pur_purchase_receipt')->where('id', (int)$receipt['id'])->limit(1)->get()->row_array();
            $adjustments = $this->db
                ->select('movement_scope, movement_type, COUNT(*) AS row_count, ROUND(SUM(qty_content_delta), 4) AS qty_content_total', false)
                ->from('inv_stock_movement_log')
                ->where('ref_table', 'pur_purchase_order')
                ->where('ref_id', $poId)
                ->where('movement_type', 'ADJUSTMENT')
                ->group_by('movement_scope')
                ->group_by('movement_type')
                ->get()
                ->result_array();

            if (strtoupper((string)($receiptAfter['status'] ?? '')) !== 'VOID') {
                return ['ok' => false, 'message' => 'Receipt tidak berubah ke VOID saat PO di-VOID.'];
            }
            if (!$this->movementSummaryHas($beforeSummary, 'WAREHOUSE', 'PURCHASE_IN')) {
                return ['ok' => false, 'message' => 'Setup uji rollback PO tidak memiliki PURCHASE_IN awal.'];
            }
            if (!$this->movementSummaryHas($adjustments, 'WAREHOUSE', 'ADJUSTMENT')) {
                return ['ok' => false, 'message' => 'VOID PO tidak membentuk ADJUSTMENT rollback stok gudang.', 'data' => ['adjustments' => $adjustments]];
            }

            return [
                'ok' => true,
                'message' => 'VOID PO receipt berhasil me-rollback stok dan me-VOID receipt.',
                'data' => [
                    'po_id' => $poId,
                    'receipt_id' => (int)$receipt['id'],
                    'rollback_summary' => $adjustments,
                ],
            ];
        });

        $cases[] = $this->runSmokeCase('po_void_rollback_paid', function () use ($fx, $smokeDate, $userId) {
            $type = (array)$fx['purchase_types']['INV_STOK'];
            $accountId = (int)$fx['account']['id'];
            $create = $this->Purchase_model->store_order_with_lines([
                'request_date' => $smokeDate,
                'expected_date' => $smokeDate,
                'purchase_type_id' => (int)$type['id'],
                'vendor_id' => (int)$fx['vendor']['id'],
                'payment_account_id' => $accountId,
                'status' => 'PAID',
                'notes' => 'Smoke test PO void rollback paid',
            ], [
                $this->buildSmokeInventoryPurchaseLine((array)$fx['inventory_profile'], 1.0),
            ], $userId, 'CLI_SMOKE');
            if (!($create['ok'] ?? false)) {
                return $create;
            }

            $poId = (int)($create['data']['purchase_order_id'] ?? 0);
            $paymentPlans = $this->db->from('pur_purchase_payment_plan')->where('purchase_order_id', $poId)->where('status', 'PAID')->get()->result_array();
            $receipt = $this->db->from('pur_purchase_receipt')->where('purchase_order_id', $poId)->where('status', 'POSTED')->limit(1)->get()->row_array();
            if (empty($paymentPlans) || !$receipt) {
                return ['ok' => false, 'message' => 'Setup PO PAID tidak lengkap untuk uji VOID rollback.'];
            }

            $voidResult = $this->Purchase_model->update_order_status($poId, 'VOID', $userId, 'CLI_SMOKE');
            if (!($voidResult['ok'] ?? false)) {
                return $voidResult;
            }

            $voidPlanCount = (int)$this->db->from('pur_purchase_payment_plan')->where('purchase_order_id', $poId)->where('status', 'VOID')->count_all_results();
            $accountMutationIn = (int)$this->db
                ->from('fin_account_mutation_log')
                ->where('ref_table', 'pur_purchase_order')
                ->where('ref_id', $poId)
                ->where('mutation_type', 'IN')
                ->count_all_results();
            $receiptAfter = $this->db->from('pur_purchase_receipt')->where('id', (int)$receipt['id'])->limit(1)->get()->row_array();

            if ($voidPlanCount <= 0) {
                return ['ok' => false, 'message' => 'VOID PO PAID tidak me-VOID payment plan.'];
            }
            if ($accountMutationIn <= 0) {
                return ['ok' => false, 'message' => 'VOID PO PAID tidak mengembalikan saldo akun ke mutation IN.'];
            }
            if (strtoupper((string)($receiptAfter['status'] ?? '')) !== 'VOID') {
                return ['ok' => false, 'message' => 'VOID PO PAID tidak me-VOID receipt terkait.'];
            }

            return [
                'ok' => true,
                'message' => 'VOID PO PAID berhasil rollback stok, payment, dan receipt.',
                'data' => [
                    'po_id' => $poId,
                    'void_payment_plan_count' => $voidPlanCount,
                    'account_mutation_in_count' => $accountMutationIn,
                ],
            ];
        });

        $cases[] = $this->runSmokeCase('po_invalid_transition_rejected_to_paid', function () use ($fx, $smokeDate, $userId) {
            $type = (array)$fx['purchase_types']['INV_STOK'];
            $create = $this->Purchase_model->store_order_with_lines([
                'request_date' => $smokeDate,
                'expected_date' => $smokeDate,
                'purchase_type_id' => (int)$type['id'],
                'vendor_id' => (int)$fx['vendor']['id'],
                'status' => 'REJECTED',
                'notes' => 'Smoke invalid transition PO',
            ], [
                $this->buildSmokeInventoryPurchaseLine((array)$fx['inventory_profile'], 1.0),
            ], $userId, 'CLI_SMOKE');
            if (!($create['ok'] ?? false)) {
                return $create;
            }

            $poId = (int)($create['data']['purchase_order_id'] ?? 0);
            $transition = $this->Purchase_model->update_order_status($poId, 'PAID', $userId, 'CLI_SMOKE');
            if (!empty($transition['ok'])) {
                return ['ok' => false, 'message' => 'Transisi REJECTED -> PAID seharusnya ditolak.'];
            }
            if (strpos((string)($transition['message'] ?? ''), 'Transisi status tidak diizinkan') === false) {
                return ['ok' => false, 'message' => 'Pesan penolakan transisi PO tidak sesuai.', 'data' => ['result' => $transition]];
            }

            return [
                'ok' => true,
                'message' => 'Transisi ilegal PO REJECTED -> PAID berhasil ditolak.',
                'data' => ['po_id' => $poId],
            ];
        });

        $cases[] = $this->runSmokeCase('sr_void_rollback_after_fulfill', function () use ($fx, $smokeDate, $userId) {
            $qty = $this->smokeRequestQty((array)$fx['inventory_profile'], false);
            $create = $this->Procurement_model->create_store_request([
                'request_date' => $smokeDate,
                'needed_date' => $smokeDate,
                'request_division_id' => (int)$fx['sr_division']['id'],
                'destination_type' => (string)$fx['sr_destination'],
                'status' => 'DRAFT',
                'notes' => 'Smoke test SR void rollback',
            ], [
                $this->buildSmokeStoreRequestLine((array)$fx['inventory_profile'], $qty['qty_buy'], $qty['qty_content']),
            ], $userId);
            if (!($create['ok'] ?? false)) {
                return $create;
            }

            $requestId = (int)($create['id'] ?? 0);
            foreach (['SUBMIT', 'APPROVE'] as $action) {
                $actionResult = $this->Procurement_model->apply_store_request_action($requestId, $action, 'Smoke ' . strtolower($action), $userId);
                if (!($actionResult['ok'] ?? false)) {
                    return $actionResult;
                }
            }

            $fulfill = $this->Procurement_model->fulfill_auto_from_warehouse($requestId, $smokeDate, 'Smoke fulfill before void', $userId);
            if (!($fulfill['ok'] ?? false)) {
                return $fulfill;
            }

            $fulfillmentId = (int)($fulfill['data']['fulfillment_id'] ?? 0);
            $voidResult = $this->Procurement_model->apply_store_request_action($requestId, 'VOID', 'Smoke void rollback', $userId);
            if (!($voidResult['ok'] ?? false)) {
                return $voidResult;
            }

            $movementCount = (int)$this->db->from('inv_stock_movement_log')->where('ref_table', 'pur_store_request_fulfillment')->where('ref_id', $fulfillmentId)->count_all_results();
            $fulfillment = $this->db->from('pur_store_request_fulfillment')->where('id', $fulfillmentId)->limit(1)->get()->row_array();
            $line = $this->db->from('pur_store_request_line')->where('store_request_id', $requestId)->limit(1)->get()->row_array();

            if ($movementCount !== 0) {
                return ['ok' => false, 'message' => 'VOID SR tidak menghapus movement fulfillment dari histori.', 'data' => ['movement_count' => $movementCount]];
            }
            if (strtoupper((string)($fulfillment['status'] ?? '')) !== 'VOID') {
                return ['ok' => false, 'message' => 'VOID SR tidak me-VOID dokumen fulfillment.'];
            }
            if ((float)($line['qty_content_fulfilled'] ?? 0) != 0.0) {
                return ['ok' => false, 'message' => 'VOID SR tidak me-reset qty fulfilled line SR.'];
            }

            return [
                'ok' => true,
                'message' => 'VOID SR setelah fulfill berhasil rollback histori fulfillment.',
                'data' => [
                    'request_id' => $requestId,
                    'fulfillment_id' => $fulfillmentId,
                ],
            ];
        });

        $cases[] = $this->runSmokeCase('sr_invalid_transition_approve_from_draft', function () use ($fx, $smokeDate, $userId) {
            $qty = $this->smokeRequestQty((array)$fx['inventory_profile'], false);
            $create = $this->Procurement_model->create_store_request([
                'request_date' => $smokeDate,
                'needed_date' => $smokeDate,
                'request_division_id' => (int)$fx['sr_division']['id'],
                'destination_type' => (string)$fx['sr_destination'],
                'status' => 'DRAFT',
                'notes' => 'Smoke invalid SR transition',
            ], [
                $this->buildSmokeStoreRequestLine((array)$fx['inventory_profile'], $qty['qty_buy'], $qty['qty_content']),
            ], $userId);
            if (!($create['ok'] ?? false)) {
                return $create;
            }

            $requestId = (int)($create['id'] ?? 0);
            $approve = $this->Procurement_model->apply_store_request_action($requestId, 'APPROVE', 'Smoke invalid approve', $userId);
            if (!empty($approve['ok'])) {
                return ['ok' => false, 'message' => 'APPROVE dari DRAFT seharusnya ditolak.'];
            }
            if (strpos((string)($approve['message'] ?? ''), 'Hanya SUBMITTED yang dapat diapprove') === false) {
                return ['ok' => false, 'message' => 'Pesan penolakan transisi SR tidak sesuai.', 'data' => ['result' => $approve]];
            }

            return [
                'ok' => true,
                'message' => 'Transisi ilegal SR DRAFT -> APPROVE berhasil ditolak.',
                'data' => ['request_id' => $requestId],
            ];
        });

        $failed = array_values(array_filter($cases, static function ($case) {
            return !empty($case) && empty($case['ok']);
        }));

        $summary = [
            'ok' => empty($failed),
            'message' => empty($failed)
                ? 'Smoke test PO/SR/gudang berhasil untuk seluruh case.'
                : 'Smoke test menemukan ' . count($failed) . ' case gagal.',
            'data' => [
                'date' => $smokeDate,
                'case_count' => count($cases),
                'failed_count' => count($failed),
                'cases' => $cases,
            ],
        ];

        if (!empty($failed)) {
            fwrite(STDERR, json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL);
            exit(1);
        }

        fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL);
        exit(0);
    }

    public function repair_opening_history()
    {
        $argv = $_SERVER['argv'] ?? [];
        $cliArgs = [];
        for ($i = 3; $i < count($argv); $i += 2) {
            $key = isset($argv[$i]) ? trim((string)$argv[$i]) : '';
            if ($key === '') {
                continue;
            }
            $cliArgs[$key] = isset($argv[$i + 1]) ? trim((string)$argv[$i + 1]) : '';
        }

        $result = $this->Purchase_model->repair_inventory_opening_history([
            'stock_scope' => strtoupper((string)($cliArgs['scope'] ?? 'WAREHOUSE')),
            'month_from' => (string)($cliArgs['month_from'] ?? date('Y-m-01')),
            'item_id' => (int)($cliArgs['item_id'] ?? 0),
            'limit' => (int)($cliArgs['limit'] ?? 500),
            'division_id' => (int)($cliArgs['division_id'] ?? 0),
        ], 0, 'CLI');

        if (!($result['ok'] ?? false)) {
            fwrite(STDERR, (string)($result['message'] ?? 'Repair opening gagal.') . PHP_EOL);
            exit(1);
        }

        fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL);
        exit(0);
    }

    public function store_request_fulfill()
    {
        $argv = $_SERVER['argv'] ?? [];
        $cliArgs = [];
        for ($i = 3; $i < count($argv); $i += 2) {
            $key = isset($argv[$i]) ? trim((string)$argv[$i]) : '';
            if ($key === '') {
                continue;
            }
            $cliArgs[$key] = isset($argv[$i + 1]) ? trim((string)$argv[$i + 1]) : '';
        }

        $requestId = (int)($cliArgs['id'] ?? 0);
        $fulfillmentDate = (string)($cliArgs['date'] ?? date('Y-m-d'));
        $notes = (string)($cliArgs['notes'] ?? 'CLI fulfill store request');
        $userId = (int)($cliArgs['user_id'] ?? 0);
        $dryRun = !isset($cliArgs['dry_run']) || !in_array(strtolower((string)$cliArgs['dry_run']), ['0', 'false', 'no'], true);

        if ($requestId <= 0) {
            fwrite(STDERR, "Parameter id wajib diisi dan > 0." . PHP_EOL);
            exit(1);
        }

        $this->load->model('Procurement_model');
        $this->db->trans_strict(false);
        $this->db->trans_begin();

        try {
            $result = $this->Procurement_model->fulfill_auto_from_warehouse(
                $requestId,
                $fulfillmentDate,
                $notes,
                $userId
            );
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            fwrite(STDERR, json_encode([
                'ok' => false,
                'message' => $e->getMessage(),
            ], JSON_PRETTY_PRINT) . PHP_EOL);
            exit(1);
        }

        if ($dryRun) {
            $this->db->trans_rollback();
            if (is_array($result)) {
                $result['data']['dry_run'] = true;
            }
        } elseif (!($result['ok'] ?? false)) {
            $this->db->trans_rollback();
        } else {
            $this->db->trans_commit();
        }

        if (!($result['ok'] ?? false)) {
            fwrite(STDERR, json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL);
            exit(1);
        }

        fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL);
        exit(0);
    }

    public function repair_void_store_request_history()
    {
        $cliArgs = $this->parseCliArgs();

        $requestId = (int)($cliArgs['id'] ?? 0);
        $userId = (int)($cliArgs['user_id'] ?? 0);
        if ($requestId <= 0) {
            fwrite(STDERR, "Parameter id wajib diisi dan > 0." . PHP_EOL);
            exit(1);
        }

        $this->load->model('Procurement_model');
        $result = $this->Procurement_model->repair_void_store_request_history($requestId, $userId);
        if (!($result['ok'] ?? false)) {
            fwrite(STDERR, json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL);
            exit(1);
        }

        fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL);
        exit(0);
    }

    private function parseCliArgs(): array
    {
        $argv = $_SERVER['argv'] ?? [];
        $cliArgs = [];
        for ($i = 3; $i < count($argv); $i += 2) {
            $key = isset($argv[$i]) ? trim((string)$argv[$i]) : '';
            if ($key === '') {
                continue;
            }
            $cliArgs[$key] = isset($argv[$i + 1]) ? trim((string)$argv[$i + 1]) : '';
        }

        return $cliArgs;
    }

    private function runSmokeCase(string $name, callable $callback): array
    {
        $this->db->trans_strict(false);
        $this->db->trans_begin();

        try {
            $result = $callback();
            if (!is_array($result)) {
                $result = ['ok' => false, 'message' => 'Smoke case tidak mengembalikan array hasil.'];
            }
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return [
                'case' => $name,
                'ok' => false,
                'message' => $e->getMessage(),
            ];
        }

        $this->db->trans_rollback();
        $result['case'] = $name;
        return $result;
    }

    private function resolveSmokeFixtures(): array
    {
        $types = [];
        foreach ($this->Purchase_model->list_active_purchase_types() as $row) {
            $code = strtoupper(trim((string)($row['type_code'] ?? '')));
            if ($code !== '') {
                $types[$code] = $row;
            }
        }

        $vendors = $this->Purchase_model->list_active_vendors();
        $accounts = $this->Purchase_model->list_active_payment_accounts();
        $divisions = $this->Purchase_model->list_active_operational_divisions();
        $profiles = $this->Procurement_model->search_warehouse_profiles('', 20);

        if (empty($vendors)) {
            return ['ok' => false, 'message' => 'Master vendor aktif tidak tersedia untuk smoke test.'];
        }
        if (empty($accounts)) {
            return ['ok' => false, 'message' => 'Master rekening aktif tidak tersedia untuk smoke test.'];
        }
        if (empty($profiles)) {
            return ['ok' => false, 'message' => 'Stok gudang aktif tidak tersedia untuk smoke test SR/inventory PO.'];
        }

        $profile = null;
        foreach ($profiles as $row) {
            if ((int)($row['item_id'] ?? 0) > 0 && (int)($row['buy_uom_id'] ?? 0) > 0 && (int)($row['content_uom_id'] ?? 0) > 0) {
                $profile = $row;
                break;
            }
        }
        if ($profile === null) {
            $profile = $profiles[0] ?? null;
        }
        if ($profile === null) {
            return ['ok' => false, 'message' => 'Profile stok gudang tidak ditemukan untuk smoke test.'];
        }

        $division = $this->findSmokeDivision($divisions, ['BAR', 'KITCHEN', 'OFFICE', 'OTHER']);
        if ($division === null) {
            return ['ok' => false, 'message' => 'Divisi operasional yang cocok untuk SR smoke test tidak ditemukan.'];
        }

        $destination = (string)($division['destination_default'] ?? 'OTHER');
        if ($destination === '') {
            $destination = 'OTHER';
        }

        $uomId = (int)($profile['buy_uom_id'] ?? 0);
        if ($uomId <= 0) {
            $uom = $this->db->select('id')->from('mst_uom')->order_by('id', 'ASC')->limit(1)->get()->row_array();
            $uomId = (int)($uom['id'] ?? 0);
        }
        if ($uomId <= 0) {
            return ['ok' => false, 'message' => 'Master UOM tidak tersedia untuk smoke test non-inventory PO.'];
        }

        return [
            'ok' => true,
            'data' => [
                'purchase_types' => $types,
                'vendor' => $vendors[0],
                'account' => $accounts[0],
                'inventory_profile' => $profile,
                'sr_division' => $division,
                'sr_destination' => $destination,
                'component' => $this->resolveSmokeComponent((int)($division['id'] ?? 0)),
                'uom_id' => $uomId,
            ],
        ];
    }

    private function resolveSmokeComponent(int $divisionId): ?array
    {
        if (!$this->db->table_exists('mst_component')) {
            return null;
        }

        $this->db->select('id, component_code, component_name, uom_id, operational_division_id')
            ->from('mst_component')
            ->where('is_active', 1);
        if ($divisionId > 0 && $this->db->field_exists('operational_division_id', 'mst_component')) {
            $this->db->group_start()
                ->where('operational_division_id', $divisionId)
                ->or_where('operational_division_id IS NULL', null, false)
                ->group_end();
        }
        $row = $this->db->order_by('id', 'ASC')->limit(1)->get()->row_array();

        if ($row) {
            return $row;
        }

        return $this->db->select('id, component_code, component_name, uom_id, operational_division_id')
            ->from('mst_component')
            ->where('is_active', 1)
            ->order_by('id', 'ASC')
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    private function findSmokeDivision(array $divisions, array $preferredDestinations): ?array
    {
        foreach ($preferredDestinations as $destination) {
            foreach ($divisions as $row) {
                $default = strtoupper(trim((string)($row['destination_default'] ?? '')));
                $allowed = strtoupper((string)($row['destination_allowed'] ?? ''));
                if ($default === $destination || strpos($allowed, $destination) !== false) {
                    return $row;
                }
            }
        }

        return !empty($divisions) ? $divisions[0] : null;
    }

    private function firstSmokeType(array $types, array $codes): ?array
    {
        foreach ($codes as $code) {
            $key = strtoupper(trim($code));
            if (!empty($types[$key]) && is_array($types[$key])) {
                return $types[$key];
            }
        }

        return null;
    }

    private function buildSmokeInventoryPurchaseLine(array $profile, float $qtyBuy): array
    {
        $contentPerBuy = round((float)($profile['profile_content_per_buy'] ?? 1), 6);
        if ($contentPerBuy <= 0) {
            $contentPerBuy = 1;
        }

        return [
            'line_kind' => strtoupper((string)($profile['line_kind'] ?? (((int)($profile['material_id'] ?? 0) > 0) ? 'MATERIAL' : 'ITEM'))),
            'item_id' => (int)($profile['item_id'] ?? 0) > 0 ? (int)$profile['item_id'] : null,
            'material_id' => (int)($profile['material_id'] ?? 0) > 0 ? (int)$profile['material_id'] : null,
            'buy_uom_id' => (int)($profile['buy_uom_id'] ?? 0),
            'content_uom_id' => (int)($profile['content_uom_id'] ?? 0),
            'qty_buy' => round(max($qtyBuy, 0.01), 4),
            'content_per_buy' => $contentPerBuy,
            'conversion_factor_to_content' => $contentPerBuy,
            'unit_price' => 1000,
            'brand_name' => (string)($profile['profile_brand'] ?? ''),
            'line_description' => (string)($profile['profile_description'] ?? 'Smoke inventory purchase'),
            'expired_date' => (string)($profile['profile_expired_date'] ?? ''),
        ];
    }

    private function buildSmokeStoreRequestLine(array $profile, float $qtyBuy, float $qtyContent): array
    {
        $contentPerBuy = round((float)($profile['profile_content_per_buy'] ?? 1), 6);
        if ($contentPerBuy <= 0) {
            $contentPerBuy = 1;
        }

        return [
            'line_kind' => strtoupper((string)($profile['line_kind'] ?? (((int)($profile['material_id'] ?? 0) > 0) ? 'MATERIAL' : 'ITEM'))),
            'item_id' => (int)($profile['item_id'] ?? 0) > 0 ? (int)$profile['item_id'] : null,
            'material_id' => (int)($profile['material_id'] ?? 0) > 0 ? (int)$profile['material_id'] : null,
            'profile_key' => (string)($profile['profile_key'] ?? ''),
            'profile_name' => (string)($profile['profile_name'] ?? ''),
            'profile_brand' => (string)($profile['profile_brand'] ?? ''),
            'profile_description' => (string)($profile['profile_description'] ?? ''),
            'profile_expired_date' => (string)($profile['profile_expired_date'] ?? ''),
            'buy_uom_id' => (int)($profile['buy_uom_id'] ?? 0),
            'content_uom_id' => (int)($profile['content_uom_id'] ?? 0),
            'profile_content_per_buy' => $contentPerBuy,
            'profile_buy_uom_code' => (string)($profile['profile_buy_uom_code'] ?? ''),
            'profile_content_uom_code' => (string)($profile['profile_content_uom_code'] ?? ''),
            'qty_buy_requested' => round($qtyBuy, 2),
            'qty_content_requested' => round($qtyContent, 2),
            'notes' => 'Smoke request line',
        ];
    }

    private function smokeRequestQty(array $profile, bool $withShortage): array
    {
        $contentPerBuy = round((float)($profile['profile_content_per_buy'] ?? 1), 6);
        if ($contentPerBuy <= 0) {
            $contentPerBuy = 1;
        }

        $availableContent = round((float)($profile['qty_content_balance'] ?? 0), 4);
        if ($availableContent <= 0) {
            $availableContent = $contentPerBuy;
        }

        $qtyContent = $withShortage
            ? round($availableContent + $contentPerBuy, 2)
            : round(min($availableContent, $contentPerBuy), 2);
        if ($qtyContent <= 0) {
            $qtyContent = round($contentPerBuy, 2);
        }

        $qtyBuy = round($qtyContent / max($contentPerBuy, 0.000001), 2);
        if ($qtyBuy <= 0) {
            $qtyBuy = 1;
        }

        return [
            'qty_buy' => $qtyBuy,
            'qty_content' => $qtyContent,
        ];
    }

    private function summarizeMovementByRef(string $refTable, int $refId): array
    {
        if ($refId <= 0 || !$this->db->table_exists('inv_stock_movement_log')) {
            return [];
        }

        return $this->db
            ->select('movement_scope, movement_type, COALESCE(destination_type, \'\') AS destination_type', false)
            ->select('COUNT(*) AS row_count, ROUND(SUM(qty_buy_delta), 4) AS qty_buy_total, ROUND(SUM(qty_content_delta), 4) AS qty_content_total', false)
            ->from('inv_stock_movement_log')
            ->where('ref_table', $refTable)
            ->where('ref_id', $refId)
            ->group_by('movement_scope')
            ->group_by('movement_type')
            ->group_by('destination_type')
            ->order_by('movement_scope', 'ASC')
            ->order_by('movement_type', 'ASC')
            ->get()
            ->result_array();
    }

    private function movementSummaryHas(array $rows, string $scope, string $movementType): bool
    {
        foreach ($rows as $row) {
            if (
                strtoupper((string)($row['movement_scope'] ?? '')) === strtoupper($scope)
                && strtoupper((string)($row['movement_type'] ?? '')) === strtoupper($movementType)
            ) {
                return true;
            }
        }

        return false;
    }

    private function movementSummaryHasScope(array $rows, string $scope): bool
    {
        foreach ($rows as $row) {
            if (strtoupper((string)($row['movement_scope'] ?? '')) === strtoupper($scope)) {
                return true;
            }
        }

        return false;
    }
}