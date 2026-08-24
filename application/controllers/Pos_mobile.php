<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pos_mobile extends CI_Controller
{
    private $mobileUser = null;

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->model('Auth_model');
        $this->load->model('Pos_model');
    }

    public function ping(): void
    {
        $this->json_ok([
            'server_time' => date('c'),
            'service' => 'finance-pos-mobile',
        ]);
    }

    public function login(): void
    {
        if (!$this->db->table_exists('pos_mobile_auth_token')) {
            $this->json_error('Schema token mobile belum siap. Jalankan SQL 2026-08-20a_pos_mobile_sync_foundation.sql.', 503);
            return;
        }

        $payload = $this->request_payload();
        $identifier = trim((string)($payload['identifier'] ?? $payload['username'] ?? ''));
        $password = (string)($payload['password'] ?? '');
        if ($identifier === '' || $password === '') {
            $this->json_error('Username/email dan password wajib diisi.', 422);
            return;
        }

        $user = $this->Auth_model->attempt_login($identifier, $password);
        if (!$user) {
            $this->json_error('Username/email atau password salah.', 401);
            return;
        }

        $perms = $this->Auth_model->load_permissions((int)$user['id']);
        $isSuperadmin = isset($perms['__superadmin__']);
        $canCashier = $isSuperadmin
            || !empty($perms['pos.cashier.index']['can_view'])
            || !empty($perms['pos.order.draft.index']['can_view']);
        if (!$canCashier) {
            $this->json_error('Akun belum memiliki akses POS kasir.', 403);
            return;
        }

        $employeeId = max(0, (int)($user['employee_id'] ?? 0));
        if ($employeeId <= 0) {
            $this->json_error('Akun belum terhubung ke employee. Hubungkan user ke data employee dulu.', 422);
            return;
        }

        $token = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        $this->db->insert('pos_mobile_auth_token', [
            'token_hash' => hash('sha256', $token),
            'user_id' => (int)$user['id'],
            'employee_id' => $employeeId,
            'terminal_device_key' => trim((string)($payload['terminal_device_key'] ?? '')),
            'device_label' => trim((string)($payload['device_label'] ?? 'Android POS')),
            'issued_at' => $now,
            'expires_at' => $expiresAt,
            'last_seen_at' => $now,
            'ip_address' => $this->input->ip_address(),
            'user_agent' => substr((string)$this->input->user_agent(), 0, 255),
        ]);

        $this->json_ok([
            'token' => $token,
            'expires_at' => $expiresAt,
            'user' => [
                'id' => (int)$user['id'],
                'employee_id' => $employeeId,
                'username' => (string)($user['username'] ?? ''),
                'email' => (string)($user['email'] ?? ''),
                'is_superadmin' => $isSuperadmin,
            ],
        ]);
    }

    public function logout(): void
    {
        $token = $this->bearer_token();
        if ($token !== '' && $this->db->table_exists('pos_mobile_auth_token')) {
            $this->db
                ->where('token_hash', hash('sha256', $token))
                ->where('revoked_at IS NULL', null, false)
                ->update('pos_mobile_auth_token', [
                    'revoked_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }
        $this->json_ok(['logged_out' => true]);
    }

    public function bootstrap(): void
    {
        if (!$this->authorize_mobile(false)) {
            return;
        }

        $outletId = max(0, (int)$this->input->get('outlet_id', true));
        if ($outletId <= 0) {
            $outletId = max(0, (int)$this->input->get('default_outlet_id', true));
        }

        $employeeId = $this->current_actor_employee_id();
        $cashierBootstrap = $this->Pos_model->cashier_bootstrap_options($employeeId);
        if ($outletId <= 0) {
            $outletId = (int)($cashierBootstrap['active_session']['outlet_id'] ?? $cashierBootstrap['default_outlet_id'] ?? 0);
        }

        $products = $this->Pos_model->order_product_catalog([
            'outlet_id' => $outletId,
            'limit' => 120,
        ]);
        $bundles = $this->Pos_model->order_bundle_catalog([
            'outlet_id' => $outletId,
            'limit' => 60,
        ]);

        $this->json_ok([
            'sync_cursor' => date('c'),
            'server_time' => date('c'),
            'cashier_bootstrap' => $cashierBootstrap,
            'filter_options' => $this->Pos_model->order_draft_filter_options(),
            'catalog_filters' => $this->Pos_model->cashier_catalog_filter_options(),
            'payment_methods' => $this->Pos_model->deposit_payment_method_options(),
            'products' => $products,
            'bundles' => $bundles,
            'deleted' => [
                'products' => [],
                'bundles' => [],
                'payment_methods' => [],
                'printers' => [],
            ],
        ]);
    }

    public function catalog(): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }

        $outletId = max(0, (int)$this->input->get('outlet_id', true));
        $q = trim((string)$this->input->get('q', true));
        $divisionId = max(0, (int)$this->input->get('division_id', true));
        $categoryId = max(0, (int)$this->input->get('category_id', true));
        $limit = max(1, min(120, (int)$this->input->get('limit', true) ?: 60));
        $mode = strtoupper(trim((string)$this->input->get('mode', true)));

        if ($outletId <= 0) {
            $employeeId = $this->current_actor_employee_id();
            $session = $this->Pos_model->find_active_cashier_session($employeeId);
            $outletId = (int)($session['outlet_id'] ?? 0);
            if ($outletId <= 0) {
                $cashierBootstrap = $this->Pos_model->cashier_bootstrap_options($employeeId);
                $outletId = (int)(
                    $cashierBootstrap['active_session']['outlet_id']
                    ?? $cashierBootstrap['default_outlet_id']
                    ?? 0
                );
            }
        }

        $payload = [
            'q' => $q,
            'outlet_id' => $outletId,
            'division_id' => $divisionId,
            'category_id' => $categoryId,
            'limit' => $limit,
        ];
        $this->json_ok([
            'mode' => $mode === 'BUNDLE' ? 'BUNDLE' : 'PRODUCT',
            'query' => $q,
            'rows' => $mode === 'BUNDLE'
                ? $this->Pos_model->order_bundle_catalog($payload)
                : $this->Pos_model->order_product_catalog($payload),
        ]);
    }

    public function member_search(): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }

        $q = trim((string)$this->input->get('q', true));
        $limit = max(1, min(20, (int)$this->input->get('limit', true) ?: 8));
        $this->json_ok([
            'rows' => $this->Pos_model->order_member_search($q, $limit),
        ]);
    }

    public function extra_options(): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }

        $productId = max(0, (int)$this->input->get('product_id', true));
        $this->json_ok([
            'product_id' => $productId,
            'groups' => $this->Pos_model->order_extra_options($productId),
        ]);
    }

    public function printers(): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }

        $this->json_ok($this->Pos_model->printer_device_rows([
            'q' => trim((string)$this->input->get('q', true)),
            'status' => strtoupper(trim((string)$this->input->get('status', true))) ?: 'ACTIVE',
            'outlet_id' => max(0, (int)$this->input->get('outlet_id', true)),
            'page' => max(1, (int)$this->input->get('page', true) ?: 1),
            'limit' => max(1, min(100, (int)$this->input->get('limit', true) ?: 100)),
        ]));
    }

    /**
     * Build a server-authoritative dummy print package for a mobile-bound
     * printer. The APK must not recreate Finance templates locally.
     */
    public function printer_test($id): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }

        $printer = $this->Pos_model->find_printer_device((int)$id);
        if (!$printer) {
            $this->json_error('Device printer tidak ditemukan.', 404);
            return;
        }

        $this->load->library('PosPrinterPreviewService');
        $generalSettings = (array)($this->Pos_model->printer_general_settings()['payload'] ?? []);
        $role = strtoupper(trim((string)($printer['printer_role'] ?? 'CUSTOM')));
        $allowedDocumentTypes = ['RECEIPT', 'KITCHEN_TICKET', 'VOID_SLIP', 'REFUND_SLIP', 'DEPOSIT_RECEIPT'];
        $documentType = strtoupper(trim((string)($printer['template_document_type'] ?? '')));
        if (!in_array($documentType, $allowedDocumentTypes, true)) {
            $documentType = $role === 'KASIR' ? 'RECEIPT' : 'KITCHEN_TICKET';
        }

        $selectedTemplate = null;
        $templateId = max(0, (int)($printer['template_id'] ?? 0));
        if ($templateId > 0) {
            $candidate = $this->Pos_model->find_printer_template($templateId);
            if ($candidate && (int)($candidate['is_active'] ?? 0) === 1) {
                $candidateType = strtoupper(trim((string)($candidate['document_type'] ?? '')));
                if (in_array($candidateType, $allowedDocumentTypes, true)) {
                    $selectedTemplate = $candidate;
                    $documentType = $candidateType;
                }
            }
        }

        if (!$selectedTemplate) {
            foreach ($this->Pos_model->active_printer_template_options() as $template) {
                if (strtoupper(trim((string)($template['document_type'] ?? ''))) !== $documentType) {
                    continue;
                }
                $templateRole = strtoupper(trim((string)($template['template_name'] ?? '')));
                if ($templateRole === $role) {
                    $selectedTemplate = $this->Pos_model->find_printer_template((int)($template['id'] ?? 0));
                    break;
                }
            }
        }

        if (!$selectedTemplate) {
            foreach ($this->Pos_model->active_printer_template_options() as $template) {
                if (
                    strtoupper(trim((string)($template['document_type'] ?? ''))) === $documentType
                    && (int)($template['is_default'] ?? 0) === 1
                ) {
                    $selectedTemplate = $this->Pos_model->find_printer_template((int)($template['id'] ?? 0));
                    break;
                }
            }
        }

        $payload = $this->posprinterpreviewservice->defaultPayload($documentType, $generalSettings);
        if ($selectedTemplate) {
            $payload = $this->posprinterpreviewservice->decodePayload(
                (string)($selectedTemplate['template_payload'] ?? '{}'),
                $documentType,
                $generalSettings
            );
        } elseif (in_array($role, ['BAR', 'KITCHEN'], true)) {
            // Keep the dummy sample aligned with the server printer division
            // when no dedicated template has been configured yet.
            $payload['division_filter'] = $role;
        }

        $preview = $this->posprinterpreviewservice->buildPreviewPackage(
            $payload,
            $printer,
            $documentType
        );
        $this->json_ok([
            'printer' => [
                'id' => (int)($printer['id'] ?? 0),
                'device_code' => (string)($printer['device_code'] ?? ''),
                'device_name' => (string)($printer['device_name'] ?? ''),
                'printer_role' => $role,
                'print_scope' => (string)($printer['print_scope'] ?? 'DIVISION'),
            ],
            'template' => [
                'id' => (int)($selectedTemplate['id'] ?? 0),
                'template_name' => (string)($selectedTemplate['template_name'] ?? 'Default Finance'),
                'document_type' => $documentType,
                'division_filter' => (string)($payload['division_filter'] ?? 'ALL'),
            ],
            'preview' => $preview,
            'print_payload' => [
                // Bluetooth SPP cannot render the agent's logo marker. Keep
                // the server-generated text layout without printing the
                // marker as visible characters on thermal paper.
                'text' => implode("\n", (array)($preview['lines'] ?? [])),
                'paper_width_mm' => (int)($preview['paper_width_mm'] ?? 80),
                'chars_per_line' => (int)($preview['chars_per_line'] ?? 48),
            ],
        ]);
    }

    public function orders(): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }

        $session = $this->Pos_model->find_active_cashier_session($this->current_actor_employee_id());
        $requestedStatus = strtoupper(trim((string)$this->input->get('status', true)));
        $workspaceMode = $requestedStatus === 'PAID' ? 'PAID' : 'UNPAID';
        $status = in_array($requestedStatus, ['DRAFT', 'CONFIRMED', 'PAID'], true)
            ? $requestedStatus
            : 'ALL';
        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => $status,
            'workspace_mode' => $workspaceMode,
            'outlet_id' => (int)($session['outlet_id'] ?? 0),
            'date_from' => date('Y-m-d'),
            'date_to' => date('Y-m-d'),
            'page' => max(1, (int)$this->input->get('page', true) ?: 1),
            'limit' => max(1, min(50, (int)$this->input->get('limit', true) ?: 20)),
        ];
        $this->json_ok($this->Pos_model->order_draft_rows($filters));
    }

    public function order_load($id): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }

        $order = $this->Pos_model->find_order_draft((int)$id);
        if (!$order) {
            $this->json_error('Order POS tidak ditemukan.', 404);
            return;
        }
        $this->json_ok($order);
    }

    public function order_reversal_preview($id): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        $result = $this->Pos_model->order_reversal_preview((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Preview void/refund belum tersedia.'), 422);
            return;
        }
        $this->json_ok($result);
    }

    public function order_void_save(): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        $result = $this->Pos_model->save_order_void(
            $this->request_payload(),
            $this->current_actor_employee_id()
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan void POS.'), 422);
            return;
        }
        $this->json_ok($result);
    }

    public function order_refund_save(): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        $result = $this->Pos_model->save_order_refund(
            $this->request_payload(),
            $this->current_actor_employee_id()
        );
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan refund POS.'), 422);
            return;
        }
        $this->json_ok($result);
    }

    public function order_void_print_targets($id): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        $result = $this->Pos_model->direct_print_targets_for_void((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyiapkan cetak void.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id, 'direct_print_targets' => (array)($result['targets'] ?? [])]);
    }

    public function order_refund_print_targets($id): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        $result = $this->Pos_model->direct_print_targets_for_refund((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyiapkan cetak refund.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id, 'direct_print_targets' => (array)($result['targets'] ?? [])]);
    }

    public function order_reprint_targets($id): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }

        $payload = $this->request_payload();
        $result = $this->Pos_model->direct_print_targets_for_order_reprint((int)$id, [
            'printer_id' => max(0, (int)($payload['printer_id'] ?? 0)),
            'line_scope' => strtoupper(trim((string)($payload['line_scope'] ?? 'ALL'))),
        ]);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyiapkan cetak ulang order.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)$id,
            'direct_print_targets' => (array)($result['targets'] ?? []),
        ]);
    }

    public function order_confirm_print_targets($id): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        $result = $this->Pos_model->direct_print_targets_for_order_confirm((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyiapkan cetak order.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)$id,
            'direct_print_targets' => (array)($result['targets'] ?? []),
        ]);
    }

    public function order_save(): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }

        $payload = $this->request_payload();
        $payload['require_active_session'] = true;
        $result = $this->Pos_model->save_order_draft($payload, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan draft order POS.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)($result['id'] ?? 0),
            'order_no' => (string)($result['order_no'] ?? ''),
            'status' => 'DRAFT',
        ]);
    }

    public function order_confirm(): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }

        $payload = $this->request_payload();
        $payload['require_active_session'] = true;
        $saved = $this->Pos_model->save_order_draft($payload, $this->current_actor_employee_id());
        if (!($saved['ok'] ?? false)) {
            $this->json_error((string)($saved['message'] ?? 'Gagal menyimpan order POS.'), 422);
            return;
        }
        $orderId = (int)($saved['id'] ?? 0);
        if ($orderId <= 0) {
            $this->json_error('Order tersimpan tetapi ID order tidak valid.', 422);
            return;
        }

        $result = $this->confirm_mobile_order($orderId, $this->current_actor_employee_id(), [
            'append_mode' => !empty($saved['append_mode']),
            'header_only_update' => !empty($saved['header_only_update']),
            'line_ids' => (array)($saved['appended_line_ids'] ?? []),
            'appended_line_count' => (int)($saved['appended_line_count'] ?? 0),
        ]);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Order gagal dikonfirmasi.'), 422, $result);
            return;
        }
        $this->json_ok($result + [
            'id' => $orderId,
            'order_no' => (string)($saved['order_no'] ?? ''),
        ]);
    }

    public function payment_prepare($id): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        $result = $this->Pos_model->cashier_payment_prepare((int)$id, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyiapkan pembayaran POS.'), 422);
            return;
        }
        $this->json_ok($result);
    }

    public function voucher_search(): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        $orderId = max(0, (int)$this->input->get('order_id', true));
        $q = trim((string)$this->input->get('q', true));
        $limit = max(1, min(12, (int)$this->input->get('limit', true) ?: 8));
        $result = $this->Pos_model->search_cashier_vouchers($orderId, $this->current_actor_employee_id(), $q, $limit);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal memeriksa voucher POS.'), 422, [
                'rows' => (array)($result['rows'] ?? []),
            ]);
            return;
        }
        $this->json_ok(['rows' => (array)($result['rows'] ?? [])]);
    }

    public function payment_save(): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        $payload = $this->request_payload();
        $clientEventId = trim((string)($payload['client_event_id'] ?? ''));
        $localUuid = trim((string)($payload['local_uuid'] ?? ''));
        $syncTableReady = $clientEventId !== '' && $this->db->table_exists('pos_mobile_sync_event');
        if ($syncTableReady) {
            $existing = $this->db
                ->from('pos_mobile_sync_event')
                ->where('client_event_id', $clientEventId)
                ->limit(1)
                ->get()
                ->row_array();
            if ($existing) {
                $status = strtoupper(trim((string)($existing['event_status'] ?? '')));
                if ($status === 'PROCESSING') {
                    $this->json_error('Payment sedang diproses server. Jangan kirim ulang dengan event baru.', 503, [
                        'client_event_id' => $clientEventId,
                        'sync_status' => 'PROCESSING',
                    ]);
                    return;
                }
                if ($status === 'REJECTED') {
                    $this->json_error((string)($existing['error_message'] ?? 'Payment ditolak server.'), 422, [
                        'client_event_id' => $clientEventId,
                        'sync_status' => 'REJECTED',
                    ]);
                    return;
                }
                $stored = $this->decode_json_assoc((string)($existing['response_json'] ?? ''));
                $this->json_ok($stored + [
                    'duplicate' => true,
                    'client_event_id' => $clientEventId,
                ]);
                return;
            }

            $now = date('Y-m-d H:i:s');
            $this->db->insert('pos_mobile_sync_event', [
                'client_event_id' => $clientEventId,
                'local_uuid' => $localUuid !== '' ? $localUuid : 'PAY-' . $clientEventId,
                'event_type' => 'PAYMENT',
                'event_status' => 'PROCESSING',
                'request_json' => $this->encode_json($payload),
                'requested_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $result = $this->Pos_model->save_cashier_payment($payload, $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            if ($syncTableReady) {
                $this->db->where('client_event_id', $clientEventId)->update('pos_mobile_sync_event', [
                    'event_status' => 'REJECTED',
                    'error_message' => (string)($result['message'] ?? 'Payment ditolak server.'),
                    'response_json' => $this->encode_json($result),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan pembayaran POS.'), 422);
            return;
        }
        $response = [
            'id' => (int)($result['id'] ?? 0),
            'payment_no' => (string)($result['payment_no'] ?? ''),
            'order_status' => (string)($result['order_status'] ?? 'PAID'),
            'paid_now' => (float)($result['paid_now'] ?? 0),
            'entered_now' => (float)($result['entered_now'] ?? 0),
            'deposit_applied_amount' => (float)($result['deposit_applied_amount'] ?? 0),
            'change_total' => (float)($result['change_total'] ?? 0),
            'remaining_due' => (float)($result['remaining_due'] ?? 0),
            'loyalty' => (array)($result['loyalty'] ?? []),
        ];
        if ($syncTableReady) {
            $this->db->where('client_event_id', $clientEventId)->update('pos_mobile_sync_event', [
                'event_status' => 'ACCEPTED',
                'server_order_id' => (int)($payload['order_id'] ?? 0),
                'response_json' => $this->encode_json($response),
                'processed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $this->json_ok($response + ['client_event_id' => $clientEventId]);
    }

    public function payment_print_targets($id): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        $result = $this->Pos_model->direct_print_targets_for_payment((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyiapkan struk POS.'), 422);
            return;
        }
        $this->json_ok([
            'id' => (int)$id,
            'direct_print_targets' => (array)($result['targets'] ?? []),
        ]);
    }

    public function session_status(): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }

        $this->json_ok([
            'server_time' => date('c'),
            'session' => $this->Pos_model->find_active_cashier_session($this->current_actor_employee_id()),
        ]);
    }

    public function cashier_open(): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        $reconStatus = $this->Pos_model->daily_recon_gate_status('OPEN');
        if (!empty($reconStatus['enabled']) && empty($reconStatus['complete'])) {
            $this->json_error((string)($reconStatus['message'] ?? 'Daily recon belum lengkap.'), 409, [
                'daily_recon_status' => $reconStatus,
            ]);
            return;
        }
        $result = $this->Pos_model->open_cashier_session($this->request_payload(), $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal membuka kasir POS.'), 422);
            return;
        }
        $this->json_ok([
            'session' => (array)($result['session'] ?? []),
            'already_open' => !empty($result['already_open']),
        ]);
    }

    public function cashier_close_preview(): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        $result = $this->Pos_model->cashier_close_preview($this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Preview tutup kasir belum tersedia.'), 422);
            return;
        }
        $this->json_ok([
            'shift_id' => (int)($result['shift_id'] ?? 0),
            'session' => (array)($result['session'] ?? []),
            'report' => (array)($result['report'] ?? []),
        ]);
    }

    public function cashier_close(): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        $reconStatus = $this->Pos_model->daily_recon_gate_status('CLOSE');
        if (!empty($reconStatus['enabled']) && empty($reconStatus['complete'])) {
            $this->json_error((string)($reconStatus['message'] ?? 'Daily recon belum lengkap.'), 409, [
                'daily_recon_status' => $reconStatus,
            ]);
            return;
        }
        $result = $this->Pos_model->close_cashier_session($this->request_payload(), $this->current_actor_employee_id());
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menutup kasir POS.'), 422);
            return;
        }
        $shiftId = (int)($result['shift_id'] ?? 0);
        $print = $shiftId > 0
            ? $this->Pos_model->direct_print_targets_for_shift_close($shiftId, (array)($result['report'] ?? []))
            : ['targets' => []];
        $this->json_ok([
            'shift_id' => $shiftId,
            'summary' => (array)($result['summary'] ?? []),
            'report' => (array)($result['report'] ?? []),
            'direct_print_targets' => (array)($print['targets'] ?? []),
        ]);
    }

    public function orders_push(): void
    {
        if (!$this->authorize_mobile(true)) {
            return;
        }
        if (!$this->db->table_exists('pos_mobile_sync_event')) {
            $this->json_error('Schema mobile sync belum siap. Jalankan SQL 2026-08-20a_pos_mobile_sync_foundation.sql.', 503);
            return;
        }

        $payload = $this->request_payload();
        $clientEventId = trim((string)($payload['client_event_id'] ?? ''));
        $localUuid = trim((string)($payload['local_uuid'] ?? ''));
        if ($clientEventId === '' || $localUuid === '') {
            $this->json_error('Payload mobile wajib membawa client_event_id dan local_uuid.', 422);
            return;
        }

        $existing = $this->db
            ->from('pos_mobile_sync_event')
            ->where('client_event_id', $clientEventId)
            ->limit(1)
            ->get()
            ->row_array();
        if ($existing) {
            $existingStatus = strtoupper(trim((string)($existing['event_status'] ?? '')));
            if ($existingStatus === 'PROCESSING') {
                $this->json_error(
                    'Event transaksi sedang diproses server. APK akan mencoba ulang tanpa membuat order baru.',
                    503,
                    [
                        'local_uuid' => $localUuid,
                        'client_event_id' => $clientEventId,
                        'sync_status' => 'PROCESSING',
                    ]
                );
                return;
            }
            if ($existingStatus === 'REJECTED') {
                $this->json_error(
                    (string)($existing['error_message'] ?? 'Transaksi ditolak server.'),
                    422,
                    [
                        'local_uuid' => $localUuid,
                        'client_event_id' => $clientEventId,
                        'sync_status' => 'REJECTED',
                    ]
                );
                return;
            }
            $response = $this->decode_json_assoc((string)($existing['response_json'] ?? ''));
            $this->json_ok($response + [
                'duplicate' => true,
                'local_uuid' => $localUuid,
                'server_id' => (int)($existing['server_order_id'] ?? 0),
                'status' => (string)($existing['event_status'] ?? 'ACCEPTED'),
            ]);
            return;
        }

        $actorEmployeeId = $this->current_actor_employee_id();
        if ($actorEmployeeId <= 0) {
            $this->json_error('User mobile belum terhubung ke employee. Login mobile/token employee perlu disiapkan sebelum push transaksi.', 401);
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->insert('pos_mobile_sync_event', [
            'client_event_id' => $clientEventId,
            'local_uuid' => $localUuid,
            'event_type' => 'ORDER_UPSERT',
            'event_status' => 'PROCESSING',
            'request_json' => $this->encode_json($payload),
            'requested_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $eventId = (int)$this->db->insert_id();

        $orderPayload = $payload;
        $orderPayload['require_active_session'] = true;
        $result = $this->Pos_model->save_order_draft($orderPayload, $actorEmployeeId);
        if (!($result['ok'] ?? false)) {
            $this->db->where('id', $eventId)->update('pos_mobile_sync_event', [
                'event_status' => 'REJECTED',
                'error_message' => (string)($result['message'] ?? 'Order mobile ditolak server.'),
                'response_json' => $this->encode_json($result),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->json_error((string)($result['message'] ?? 'Order mobile ditolak server.'), 422, [
                'local_uuid' => $localUuid,
            ]);
            return;
        }

        $confirmed = !empty($payload['confirm_order']);
        $confirmResult = $confirmed
            ? $this->confirm_mobile_order((int)($result['id'] ?? 0), $actorEmployeeId, [
                'append_mode' => !empty($result['append_mode']),
                'header_only_update' => !empty($result['header_only_update']),
                'line_ids' => (array)($result['appended_line_ids'] ?? []),
                'appended_line_count' => (int)($result['appended_line_count'] ?? 0),
            ])
            : ['ok' => true, 'stock_commit_status' => 'PENDING'];
        if (!($confirmResult['ok'] ?? false)) {
            $this->db->where('id', $eventId)->update('pos_mobile_sync_event', [
                'event_status' => 'REJECTED',
                'error_message' => (string)($confirmResult['message'] ?? 'Order mobile gagal dikonfirmasi.'),
                'response_json' => $this->encode_json($confirmResult),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->json_error((string)($confirmResult['message'] ?? 'Order mobile gagal dikonfirmasi.'), 422, [
                'local_uuid' => $localUuid,
                'server_id' => (int)($result['id'] ?? 0),
            ]);
            return;
        }

        $response = [
            'ok' => true,
            'local_uuid' => $localUuid,
            'server_id' => (int)($result['id'] ?? 0),
            'order_no' => (string)($result['order_no'] ?? ''),
            'status' => $confirmed ? 'SERVER_CONFIRMED' : 'SERVER_ACCEPTED',
            'stock_commit_status' => (string)($confirmResult['stock_commit_status'] ?? 'PENDING'),
            'confirmation' => $confirmResult,
            'result' => $result,
        ];
        $this->db->where('id', $eventId)->update('pos_mobile_sync_event', [
            'event_status' => 'ACCEPTED',
            'server_order_id' => (int)($result['id'] ?? 0),
            'response_json' => $this->encode_json($response),
            'processed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->json_ok($response);
    }

    private function confirm_mobile_order(int $orderId, int $actorEmployeeId, array $options = []): array
    {
        if ($orderId <= 0) {
            return ['ok' => false, 'message' => 'Order POS tidak valid.'];
        }

        $this->load->model('Pos_order_monitor_model');
        $this->load->library('PosStockCommitService');
        $this->load->library('PosRuntimeJobService');

        $appendMode = !empty($options['append_mode']);
        $headerOnlyUpdate = !empty($options['header_only_update']);
        $lineIds = array_values(array_unique(array_filter(array_map('intval', (array)($options['line_ids'] ?? [])))));
        $appendedLineCount = (int)($options['appended_line_count'] ?? count($lineIds));

        if ($appendMode && $headerOnlyUpdate && empty($lineIds)) {
            return [
                'ok' => true,
                'stock_commit_status' => 'NOT_REQUIRED',
                'append_mode' => true,
                'appended_line_count' => 0,
                'header_only_update' => true,
            ];
        }

        $resolved = $this->Pos_model->resolve_order_stock_commit_payload($orderId, $actorEmployeeId, [
            'line_ids' => $lineIds,
        ]);
        if (!($resolved['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string)($resolved['message'] ?? 'Gagal menyiapkan stock commit order POS.')];
        }

        $warningMessage = trim((string)($resolved['warning_message'] ?? ''));
        if (empty($resolved['lines'])) {
            $finalize = $this->Pos_model->finalize_order_confirmation($orderId, 0, $actorEmployeeId, 'NOT_REQUIRED');
            if (!($finalize['ok'] ?? false)) {
                return ['ok' => false, 'message' => (string)($finalize['message'] ?? 'Order POS gagal difinalkan.')];
            }
            $this->Pos_order_monitor_model->sync_order_tasks($orderId);
            return [
                'ok' => true,
                'stock_commit_status' => 'NOT_REQUIRED',
                'append_mode' => $appendMode,
                'appended_line_count' => $appendedLineCount,
                'header_only_update' => false,
                'warning_message' => $warningMessage,
            ];
        }

        $snapshot = $this->posstockcommitservice->create_snapshot(
            $orderId,
            (array)($resolved['header'] ?? []),
            (array)($resolved['lines'] ?? [])
        );
        if (!($snapshot['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string)($snapshot['message'] ?? 'Gagal membuat snapshot stock commit.')];
        }

        $queued = $this->posruntimejobservice->queue_order_confirm_commit(
            $orderId,
            (int)$snapshot['id'],
            $actorEmployeeId,
            ['event_source' => 'ORDER_CONFIRM_MOBILE', 'event_id' => $orderId]
        );
        if (!($queued['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string)($queued['message'] ?? 'Queue runtime POS gagal dibuat.')];
        }

        $markQueued = $this->posstockcommitservice->mark_queued((int)$snapshot['id']);
        if (!($markQueued['ok'] ?? false)) {
            $this->posruntimejobservice->cancel_job((int)($queued['job_id'] ?? 0), 'Snapshot stock commit gagal ditandai queued.');
            return ['ok' => false, 'message' => (string)($markQueued['message'] ?? 'Gagal menandai stock commit sebagai queued.')];
        }

        $finalize = $this->Pos_model->finalize_order_confirmation($orderId, (int)$snapshot['id'], $actorEmployeeId, 'QUEUED');
        if (!($finalize['ok'] ?? false)) {
            $this->posruntimejobservice->cancel_job((int)($queued['job_id'] ?? 0), 'Order POS gagal difinalkan setelah queue dibuat.');
            return ['ok' => false, 'message' => (string)($finalize['message'] ?? 'Order POS gagal difinalkan.')];
        }

        $this->Pos_order_monitor_model->sync_order_tasks($orderId);
        return [
            'ok' => true,
            'snapshot_id' => (int)($snapshot['id'] ?? 0),
            'commit_no' => (string)($snapshot['commit_no'] ?? ''),
            'resolved_line_count' => (int)($resolved['resolved_line_count'] ?? 0),
            'runtime_job_id' => (int)($queued['job_id'] ?? 0),
            'runtime_job_code' => (string)($queued['job_code'] ?? ''),
            'stock_commit_status' => 'QUEUED',
            'append_mode' => $appendMode,
            'appended_line_count' => $appendedLineCount,
            'header_only_update' => false,
            'warning_message' => $warningMessage,
        ];
    }

    private function authorize_mobile(bool $requireEmployee = false): bool
    {
        $token = $this->bearer_token();
        if ($token !== '' && $this->load_mobile_user_from_token($token)) {
            return true;
        }

        $expectedKey = trim((string)getenv('POS_MOBILE_API_KEY'));
        if ($expectedKey !== '') {
            $provided = trim((string)$this->input->get_request_header('X-Pos-Mobile-Key', true));
            if ($provided === '' || !hash_equals($expectedKey, $provided)) {
                $this->json_error('Mobile API key tidak valid.', 401);
                return false;
            }
            if (!$requireEmployee) {
                return true;
            }
        }

        if (empty($this->session->userdata('auth_user'))) {
            $this->json_error('Token mobile atau sesi login tidak tersedia.', 401);
            return false;
        }
        return true;
    }

    private function current_actor_employee_id(): int
    {
        if (is_array($this->mobileUser)) {
            return max(0, (int)($this->mobileUser['employee_id'] ?? 0));
        }
        $user = $this->session->userdata('auth_user') ?: [];
        return max(0, (int)($user['employee_id'] ?? 0));
    }

    private function bearer_token(): string
    {
        $header = trim((string)$this->input->get_request_header('Authorization', true));
        if (stripos($header, 'Bearer ') === 0) {
            return trim(substr($header, 7));
        }
        return trim((string)$this->input->get_request_header('X-Pos-Mobile-Token', true));
    }

    private function load_mobile_user_from_token(string $token): bool
    {
        if (!$this->db->table_exists('pos_mobile_auth_token')) {
            return false;
        }

        $row = $this->db
            ->select('t.*, u.username, u.email, u.is_active')
            ->from('pos_mobile_auth_token t')
            ->join('auth_user u', 'u.id = t.user_id', 'inner')
            ->where('t.token_hash', hash('sha256', $token))
            ->where('t.revoked_at IS NULL', null, false)
            ->where('t.expires_at >=', date('Y-m-d H:i:s'))
            ->where('u.is_active', 1)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$row) {
            $this->json_error('Token mobile tidak valid atau sudah kedaluwarsa.', 401);
            return false;
        }

        $this->db->where('id', (int)$row['id'])->update('pos_mobile_auth_token', [
            'last_seen_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->mobileUser = $row;
        return true;
    }

    private function request_payload(): array
    {
        $raw = trim((string)$this->input->raw_input_stream);
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        $post = $this->input->post(null, false);
        return is_array($post) ? $post : [];
    }

    private function json_ok(array $data = [], int $status = 200): void
    {
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode(['ok' => true] + $data, JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function json_error(string $message, int $status = 422, array $extra = []): void
    {
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode(['ok' => false, 'message' => $message] + $extra, JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function encode_json(array $payload): string
    {
        return json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES);
    }

    private function decode_json_assoc(string $payload): array
    {
        if (trim($payload) === '') {
            return [];
        }
        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }
}
