<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Whatsapp extends MY_Controller
{
    private const PAGE_DASHBOARD = 'wa.dashboard';
    private const PAGE_BROADCAST = 'wa.broadcast';
    private const PAGE_TEMPLATE  = 'wa.template';
    private const PAGE_GROUP     = 'wa.group';
    private const PAGE_LOG       = 'wa.log';
    private const PAGE_SETTINGS  = 'wa.settings';

    public function __construct()
    {
        parent::__construct();
        $this->ensureSchema();
    }

    // ──────────────────────────────────────────────────────────
    // DASHBOARD
    // ──────────────────────────────────────────────────────────
    public function dashboard()
    {
        $this->require_permission(self::PAGE_DASHBOARD, 'view');

        $session = $this->waSession();
        $stats   = $this->dashboardStats();

        $this->render('wa/dashboard', [
            'title'       => 'WA Dashboard',
            'active_menu' => 'wa.dashboard',
            'session'     => $session,
            'stats'       => $stats,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // BROADCAST
    // ──────────────────────────────────────────────────────────
    public function broadcast()
    {
        $this->require_permission(self::PAGE_BROADCAST, 'view');

        $status = $this->input->get('status', true);
        $q      = trim((string)$this->input->get('q', true));

        $this->db->from('wa_broadcast b')
            ->select('b.*, u.username AS created_by_name')
            ->join('auth_user u', 'u.id = b.created_by', 'left')
            ->order_by('b.created_at', 'DESC');

        if ($status && in_array($status, ['DRAFT','QUEUED','SENDING','DONE','FAILED','CANCELLED'], true)) {
            $this->db->where('b.status', $status);
        }
        if ($q !== '') {
            $this->db->like('b.name', $q);
        }

        $broadcasts = $this->db->limit(100)->get()->result_array();

        $this->render('wa/broadcast', [
            'title'       => 'WA Broadcast',
            'active_menu' => 'wa.broadcast',
            'broadcasts'  => $broadcasts,
            'filter_status' => $status,
            'filter_q'    => $q,
            'can_create'  => $this->can(self::PAGE_BROADCAST, 'create'),
            'can_edit'    => $this->can(self::PAGE_BROADCAST, 'edit'),
            'can_delete'  => $this->can(self::PAGE_BROADCAST, 'delete'),
        ]);
    }

    public function broadcast_create()
    {
        $this->require_permission(self::PAGE_BROADCAST, 'create');

        $templates = $this->db->from('wa_template')->where('is_active', 1)
            ->order_by('name', 'ASC')->get()->result_array();
        $groups    = $this->db->from('wa_group_map')->where('is_active', 1)
            ->order_by('group_name', 'ASC')->get()->result_array();

        if ($this->input->method() === 'post') {
            $name          = trim((string)$this->input->post('name', true));
            $templateId    = (int)$this->input->post('template_id', true);
            $customMessage = trim((string)$this->input->post('custom_message', false));
            $targetType    = (string)$this->input->post('target_type', true);
            $scheduledAt   = trim((string)$this->input->post('scheduled_at', true));
            $notes         = trim((string)$this->input->post('notes', true));
            $manualLines   = (string)$this->input->post('manual_lines', false);

            if ($name === '') {
                $this->session->set_flashdata('error', 'Nama broadcast wajib diisi.');
                redirect('wa/broadcast/create');
                return;
            }

            $broadcastId = $this->saveBroadcast([
                'name'           => $name,
                'template_id'    => $templateId > 0 ? $templateId : null,
                'custom_message' => $customMessage ?: null,
                'target_type'    => in_array($targetType, ['MANUAL','ALL_MEMBERS','MEMBER_ACTIVE','CUSTOM'], true) ? $targetType : 'MANUAL',
                'scheduled_at'   => $scheduledAt !== '' ? $scheduledAt : null,
                'notes'          => $notes ?: null,
                'status'         => 'DRAFT',
                'created_by'     => (int)($this->current_user['id'] ?? 0),
            ], $manualLines, $targetType);

            $this->session->set_flashdata('success', 'Broadcast berhasil dibuat.');
            redirect('wa/broadcast/detail/' . $broadcastId);
            return;
        }

        $this->render('wa/broadcast_form', [
            'title'       => 'Buat Broadcast',
            'active_menu' => 'wa.broadcast',
            'mode'        => 'create',
            'templates'   => $templates,
            'groups'      => $groups,
            'broadcast'   => [],
        ]);
    }

    public function broadcast_detail(int $id = 0)
    {
        $this->require_permission(self::PAGE_BROADCAST, 'view');

        $broadcast = $this->db->from('wa_broadcast b')
            ->select('b.*, t.name AS template_name, t.body AS template_body, u.username AS created_by_name')
            ->join('wa_template t', 't.id = b.template_id', 'left')
            ->join('auth_user u', 'u.id = b.created_by', 'left')
            ->where('b.id', $id)->limit(1)->get()->row_array();
        if (!$broadcast) { show_404(); return; }

        $lines = $this->db->from('wa_broadcast_line')
            ->where('broadcast_id', $id)->order_by('id', 'ASC')->get()->result_array();

        $this->render('wa/broadcast_detail', [
            'title'       => 'Detail Broadcast',
            'active_menu' => 'wa.broadcast',
            'broadcast'   => $broadcast,
            'lines'       => $lines,
            'can_edit'    => $this->can(self::PAGE_BROADCAST, 'edit'),
            'can_delete'  => $this->can(self::PAGE_BROADCAST, 'delete'),
        ]);
    }

    public function broadcast_delete(int $id = 0)
    {
        $this->require_permission(self::PAGE_BROADCAST, 'delete');

        $row = $this->db->from('wa_broadcast')->where('id', $id)->limit(1)->get()->row_array();
        if (!$row || !in_array($row['status'], ['DRAFT','FAILED','CANCELLED'], true)) {
            $this->session->set_flashdata('error', 'Broadcast tidak dapat dihapus pada status ini.');
            redirect('wa/broadcast');
            return;
        }
        $this->db->where('id', $id)->delete('wa_broadcast');
        $this->session->set_flashdata('success', 'Broadcast dihapus.');
        redirect('wa/broadcast');
    }

    // ──────────────────────────────────────────────────────────
    // TEMPLATE
    // ──────────────────────────────────────────────────────────
    public function template()
    {
        $this->require_permission(self::PAGE_TEMPLATE, 'view');

        if ($this->input->method() === 'post') {
            $action = (string)$this->input->post('action', true);

            if ($action === 'save') {
                $id           = (int)$this->input->post('id', true);
                $code         = trim((string)$this->input->post('template_code', true));
                $name         = trim((string)$this->input->post('name', true));
                $category     = (string)$this->input->post('category', true);
                $body         = (string)$this->input->post('body', false);
                $sampleVars   = trim((string)$this->input->post('sample_variables', false));

                if ($code === '' || $name === '' || $body === '') {
                    $this->session->set_flashdata('error', 'Kode, nama, dan isi template wajib diisi.');
                } else {
                    $data = [
                        'template_code'    => $code,
                        'name'             => $name,
                        'category'         => in_array($category, ['BROADCAST','GROUP','PROMO','INFO','REMINDER','CUSTOM'], true) ? $category : 'BROADCAST',
                        'body'             => $body,
                        'sample_variables' => $sampleVars !== '' ? $sampleVars : null,
                        'created_by'       => (int)($this->current_user['id'] ?? 0),
                    ];
                    if ($id > 0) {
                        $this->db->where('id', $id)->update('wa_template', $data);
                        $this->session->set_flashdata('success', 'Template diperbarui.');
                    } else {
                        $this->db->insert('wa_template', $data);
                        $this->session->set_flashdata('success', 'Template disimpan.');
                    }
                }
            } elseif ($action === 'toggle') {
                $id = (int)$this->input->post('id', true);
                $row = $this->db->from('wa_template')->where('id', $id)->limit(1)->get()->row_array();
                if ($row) {
                    $this->db->where('id', $id)->update('wa_template', ['is_active' => $row['is_active'] ? 0 : 1]);
                }
            } elseif ($action === 'delete') {
                $id = (int)$this->input->post('id', true);
                $this->db->where('id', $id)->delete('wa_template');
                $this->session->set_flashdata('success', 'Template dihapus.');
            }
            redirect('wa/template');
            return;
        }

        $templates = $this->db->from('wa_template')->order_by('category', 'ASC')
            ->order_by('name', 'ASC')->get()->result_array();

        $this->render('wa/template', [
            'title'       => 'Template Pesan WA',
            'active_menu' => 'wa.template',
            'templates'   => $templates,
            'can_create'  => $this->can(self::PAGE_TEMPLATE, 'create'),
            'can_edit'    => $this->can(self::PAGE_TEMPLATE, 'edit'),
            'can_delete'  => $this->can(self::PAGE_TEMPLATE, 'delete'),
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // GROUP
    // ──────────────────────────────────────────────────────────
    public function group()
    {
        $this->require_permission(self::PAGE_GROUP, 'view');

        if ($this->input->method() === 'post') {
            $action = (string)$this->input->post('action', true);
            if ($action === 'save') {
                $id        = (int)$this->input->post('id', true);
                $key       = trim((string)$this->input->post('group_key', true));
                $name      = trim((string)$this->input->post('group_name', true));
                $jid       = trim((string)$this->input->post('group_jid', true));
                $purpose   = trim((string)$this->input->post('purpose', true));
                $notes     = trim((string)$this->input->post('notes', true));

                if ($key === '' || $name === '') {
                    $this->session->set_flashdata('error', 'Key dan nama grup wajib diisi.');
                } else {
                    $data = ['group_key' => $key, 'group_name' => $name,
                             'group_jid' => $jid ?: null, 'purpose' => $purpose ?: null,
                             'notes' => $notes ?: null];
                    if ($id > 0) {
                        $this->db->where('id', $id)->update('wa_group_map', $data);
                        $this->session->set_flashdata('success', 'Grup diperbarui.');
                    } else {
                        $this->db->insert('wa_group_map', $data);
                        $this->session->set_flashdata('success', 'Grup disimpan.');
                    }
                }
            } elseif ($action === 'toggle') {
                $id = (int)$this->input->post('id', true);
                $row = $this->db->from('wa_group_map')->where('id', $id)->limit(1)->get()->row_array();
                if ($row) {
                    $this->db->where('id', $id)->update('wa_group_map', ['is_active' => $row['is_active'] ? 0 : 1]);
                }
            } elseif ($action === 'delete') {
                $id = (int)$this->input->post('id', true);
                $this->db->where('id', $id)->delete('wa_group_map');
                $this->session->set_flashdata('success', 'Grup dihapus.');
            } elseif ($action === 'send_group') {
                $this->require_permission(self::PAGE_GROUP, 'create');
                $id      = (int)$this->input->post('id', true);
                $message = trim((string)$this->input->post('message', false));
                $group   = $this->db->from('wa_group_map')->where('id', $id)->limit(1)->get()->row_array();
                if ($group && $group['group_jid'] && $message) {
                    $result = $this->callBotApi('/internal/send-group', 'POST', [
                        'group_jid' => $group['group_jid'],
                        'message'   => $message,
                    ]);
                    if ($result['ok'] ?? false) {
                        $this->db->where('id', $id)->update('wa_group_map', ['last_sent_at' => date('Y-m-d H:i:s')]);
                        $this->logSend(null, 'GROUP', null, $group['group_jid'], $group['group_name'], $message, 'SENT');
                        $this->session->set_flashdata('success', 'Pesan terkirim ke grup ' . $group['group_name']);
                    } else {
                        $this->session->set_flashdata('error', 'Gagal kirim: ' . ($result['message'] ?? 'error'));
                    }
                } else {
                    $this->session->set_flashdata('error', 'Grup atau pesan tidak valid.');
                }
            }
            redirect('wa/group');
            return;
        }

        $groups = $this->db->from('wa_group_map')->order_by('group_name', 'ASC')->get()->result_array();

        $this->render('wa/group', [
            'title'       => 'Manajemen Grup WA',
            'active_menu' => 'wa.group',
            'groups'      => $groups,
            'can_create'  => $this->can(self::PAGE_GROUP, 'create'),
            'can_edit'    => $this->can(self::PAGE_GROUP, 'edit'),
            'can_delete'  => $this->can(self::PAGE_GROUP, 'delete'),
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // LOG
    // ──────────────────────────────────────────────────────────
    public function log()
    {
        $this->require_permission(self::PAGE_LOG, 'view');

        $dateFrom = trim((string)$this->input->get('date_from', true));
        $dateTo   = trim((string)$this->input->get('date_to', true));
        $status   = (string)$this->input->get('status', true);
        $source   = (string)$this->input->get('source', true);

        if ($dateFrom === '') $dateFrom = date('Y-m-01');
        if ($dateTo   === '') $dateTo   = date('Y-m-d');

        $this->db->from('wa_send_log l')
            ->select('l.*')
            ->where('DATE(l.sent_at) >=', $dateFrom)
            ->where('DATE(l.sent_at) <=', $dateTo)
            ->order_by('l.sent_at', 'DESC');

        if ($status && in_array($status, ['SENT','FAILED','PENDING'], true)) {
            $this->db->where('l.status', $status);
        }
        if ($source && in_array($source, ['BROADCAST','MANUAL','GROUP','SYSTEM','SCHEDULED'], true)) {
            $this->db->where('l.source', $source);
        }

        $logs = $this->db->limit(200)->get()->result_array();

        $this->render('wa/log', [
            'title'       => 'Log Pengiriman WA',
            'active_menu' => 'wa.log',
            'logs'        => $logs,
            'date_from'   => $dateFrom,
            'date_to'     => $dateTo,
            'filter_status' => $status,
            'filter_source' => $source,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // SETTINGS
    // ──────────────────────────────────────────────────────────
    public function settings()
    {
        $this->require_permission(self::PAGE_SETTINGS, 'view');

        if ($this->input->method() === 'post') {
            $this->require_permission(self::PAGE_SETTINGS, 'edit');

            $botApiUrl   = trim((string)$this->input->post('bot_api_url', true));
            $botApiToken = trim((string)$this->input->post('bot_api_token', true));

            $this->db->where('id', 1)->update('wa_session', [
                'bot_api_url'   => $botApiUrl ?: 'http://127.0.0.1:3070',
                'bot_api_token' => $botApiToken ?: 'local-dev-token',
            ]);
            $this->session->set_flashdata('success', 'Pengaturan disimpan.');
            redirect('wa/settings');
            return;
        }

        $session = $this->waSession();

        $this->render('wa/settings', [
            'title'       => 'Pengaturan WA',
            'active_menu' => 'wa.settings',
            'session'     => $session,
            'can_edit'    => $this->can(self::PAGE_SETTINGS, 'edit'),
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // JSON API — status bot
    // ──────────────────────────────────────────────────────────
    public function api_status()
    {
        $this->require_permission(self::PAGE_DASHBOARD, 'view');
        $result = $this->callBotApi('/internal/status', 'GET');
        $this->output->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    // JSON API — kirim pesan test
    public function api_send_test()
    {
        $this->require_permission(self::PAGE_SETTINGS, 'edit');
        $payload = json_decode((string)$this->input->raw_input_stream, true) ?? [];
        $to      = trim((string)($payload['to'] ?? ''));
        $message = trim((string)($payload['message'] ?? ''));

        if (!$to || !$message) {
            $this->jsonOut(['ok' => false, 'message' => 'Nomor dan pesan wajib diisi.']);
            return;
        }

        $result = $this->callBotApi('/internal/send', 'POST', ['to' => $to, 'message' => $message]);
        if ($result['ok'] ?? false) {
            $this->logSend(null, 'MANUAL', $to, null, null, $message, 'SENT');
        } else {
            $this->logSend(null, 'MANUAL', $to, null, null, $message, 'FAILED', $result['message'] ?? '');
        }
        $this->jsonOut($result);
    }

    // JSON API — mulai kirim broadcast
    public function api_broadcast_start(int $id = 0)
    {
        $this->require_permission(self::PAGE_BROADCAST, 'edit');

        $broadcast = $this->db->from('wa_broadcast')->where('id', $id)->limit(1)->get()->row_array();
        if (!$broadcast || !in_array($broadcast['status'], ['DRAFT','FAILED'], true)) {
            $this->jsonOut(['ok' => false, 'message' => 'Broadcast tidak dapat dikirim pada status ini.']);
            return;
        }

        $lines = $this->db->from('wa_broadcast_line')
            ->where('broadcast_id', $id)
            ->where_in('status', ['PENDING','FAILED'])
            ->get()->result_array();

        if (empty($lines)) {
            $this->jsonOut(['ok' => false, 'message' => 'Tidak ada target yang bisa dikirim.']);
            return;
        }

        // Resolve pesan
        $messageTemplate = $broadcast['custom_message'] ?: '';
        if (!$messageTemplate && $broadcast['template_id']) {
            $tpl = $this->db->from('wa_template')->where('id', $broadcast['template_id'])->limit(1)->get()->row_array();
            $messageTemplate = $tpl['body'] ?? '';
        }

        $this->db->where('id', $id)->update('wa_broadcast', [
            'status'     => 'SENDING',
            'started_at' => date('Y-m-d H:i:s'),
        ]);

        $sent = $failed = 0;
        foreach ($lines as $line) {
            $msg = $this->resolveMessage($messageTemplate, (array)json_decode($line['variables_json'] ?? '{}', true));
            $result = $this->callBotApi('/internal/send', 'POST', ['to' => $line['phone_number'], 'message' => $msg]);

            if ($result['ok'] ?? false) {
                $this->db->where('id', $line['id'])->update('wa_broadcast_line', [
                    'status'           => 'SENT',
                    'resolved_message' => $msg,
                    'sent_at'          => date('Y-m-d H:i:s'),
                    'error_msg'        => null,
                ]);
                $this->logSend($id, 'BROADCAST', $line['phone_number'], null, $line['display_name'], $msg, 'SENT');
                $sent++;
            } else {
                $errMsg = $result['message'] ?? 'error';
                $this->db->where('id', $line['id'])->update('wa_broadcast_line', [
                    'status'      => 'FAILED',
                    'error_msg'   => $errMsg,
                    'retry_count' => (int)$line['retry_count'] + 1,
                ]);
                $this->logSend($id, 'BROADCAST', $line['phone_number'], null, $line['display_name'], $msg, 'FAILED', $errMsg);
                $failed++;
            }
            usleep(500000); // jeda 500ms antar kirim agar tidak diblokir WA
        }

        $finalStatus = $failed === 0 ? 'DONE' : ($sent === 0 ? 'FAILED' : 'DONE');
        $this->db->where('id', $id)->update('wa_broadcast', [
            'status'       => $finalStatus,
            'total_sent'   => $this->db->where('broadcast_id', $id)->where('status', 'SENT')->count_all_results('wa_broadcast_line'),
            'total_failed' => $this->db->where('broadcast_id', $id)->where('status', 'FAILED')->count_all_results('wa_broadcast_line'),
            'finished_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->jsonOut(['ok' => true, 'sent' => $sent, 'failed' => $failed, 'status' => $finalStatus]);
    }

    // JSON API — preview pesan dari template + variabel
    public function api_template_preview()
    {
        $this->require_permission(self::PAGE_TEMPLATE, 'view');
        $payload  = json_decode((string)$this->input->raw_input_stream, true) ?? [];
        $body     = (string)($payload['body'] ?? '');
        $vars     = (array)($payload['variables'] ?? []);
        $resolved = $this->resolveMessage($body, $vars);
        $this->jsonOut(['ok' => true, 'preview' => $resolved]);
    }

    // JSON API — ambil QR code string dari wa-bot (polling saat WAITING_QR)
    public function api_qr()
    {
        $this->require_permission(self::PAGE_SETTINGS, 'view');
        $result = $this->callBotApi('/internal/qr', 'GET');
        $this->jsonOut($result);
    }

    // JSON API — cek apakah proses wa-engine berjalan
    public function api_engine_status()
    {
        $this->require_permission(self::PAGE_SETTINGS, 'view');

        if (!function_exists('exec')) {
            $this->jsonOut(['ok' => false, 'running' => false, 'message' => 'PHP exec() dinonaktifkan di server ini.']);
            return;
        }

        $port = $this->enginePort();
        exec("lsof -ti :{$port} 2>/dev/null", $pids);
        $pids = array_values(array_filter(array_map('intval', $pids)));

        $this->jsonOut(['ok' => true, 'running' => !empty($pids), 'pids' => $pids, 'port' => $port]);
    }

    // JSON API — mulai wa-engine via nohup
    public function api_engine_start()
    {
        $this->require_permission(self::PAGE_SETTINGS, 'edit');

        if (!function_exists('exec')) {
            $this->jsonOut(['ok' => false, 'message' => 'PHP exec() dinonaktifkan di server ini.']);
            return;
        }

        $engineDir = realpath(FCPATH . 'wa-engine');
        if (!$engineDir || !file_exists($engineDir . '/index.js')) {
            $this->jsonOut(['ok' => false, 'message' => 'Folder wa-engine tidak ditemukan di: ' . FCPATH . 'wa-engine']);
            return;
        }

        $port = $this->enginePort();
        exec("lsof -ti :{$port} 2>/dev/null", $existingPids);
        $existingPids = array_values(array_filter(array_map('intval', $existingPids)));
        if (!empty($existingPids)) {
            $this->jsonOut(['ok' => false, 'message' => "Port {$port} sudah digunakan (PID: " . implode(', ', $existingPids) . "). Hentikan proses dulu."]);
            return;
        }

        $envStr  = $this->buildEnvString($engineDir);
        $logFile = escapeshellarg($engineDir . '/wa-engine.log');
        $nodeJs  = escapeshellarg($engineDir . '/index.js');

        $cmd = "cd " . escapeshellarg($engineDir) . " && nohup {$envStr}node {$nodeJs} >> {$logFile} 2>&1 & echo \$!";
        exec($cmd, $output);
        $pid = (int)trim($output[0] ?? '0');

        usleep(1200000); // tunggu 1.2 detik agar port terbuka
        exec("lsof -ti :{$port} 2>/dev/null", $checkPids);
        $running = !empty(array_filter(array_map('intval', $checkPids)));

        if ($running) {
            $this->jsonOut(['ok' => true, 'pid' => $pid, 'message' => "wa-engine berjalan (PID {$pid})"]);
        } else {
            $this->jsonOut(['ok' => false, 'message' => "Proses dimulai (PID {$pid}) tapi port {$port} belum terbuka. Cek log di wa-engine/wa-engine.log"]);
        }
    }

    // JSON API — hentikan wa-engine
    public function api_engine_stop()
    {
        $this->require_permission(self::PAGE_SETTINGS, 'edit');

        if (!function_exists('exec')) {
            $this->jsonOut(['ok' => false, 'message' => 'PHP exec() dinonaktifkan di server ini.']);
            return;
        }

        $port = $this->enginePort();
        exec("lsof -ti :{$port} 2>/dev/null", $pids);
        $pids = array_values(array_filter(array_map('intval', $pids)));

        if (empty($pids)) {
            $this->jsonOut(['ok' => false, 'message' => "Tidak ada proses di port {$port}."]);
            return;
        }

        foreach ($pids as $pid) {
            exec("kill -9 {$pid} 2>/dev/null");
        }

        usleep(600000);
        exec("lsof -ti :{$port} 2>/dev/null", $afterPids);
        $stillRunning = !empty(array_filter(array_map('intval', $afterPids)));

        if (!$stillRunning) {
            $this->jsonOut(['ok' => true, 'message' => 'wa-engine dihentikan (PID: ' . implode(', ', $pids) . ')']);
        } else {
            $this->jsonOut(['ok' => false, 'message' => 'Kill dikirim tapi proses tampaknya dikelola PM2 atau process manager. Hentikan via PM2: pm2 stop wa-engine']);
        }
    }

    // JSON API — ambil log terakhir wa-engine
    public function api_engine_logs()
    {
        $this->require_permission(self::PAGE_SETTINGS, 'view');

        $logFile = FCPATH . 'wa-engine/wa-engine.log';
        if (!file_exists($logFile)) {
            $this->jsonOut(['ok' => true, 'logs' => '(Log belum ada — wa-engine belum pernah dijalankan dari UI)']);
            return;
        }

        if (!function_exists('exec')) {
            $lines = array_slice(file($logFile, FILE_IGNORE_NEW_LINES) ?: [], -30);
        } else {
            exec("tail -n 30 " . escapeshellarg($logFile) . " 2>/dev/null", $lines);
        }

        $this->jsonOut(['ok' => true, 'logs' => implode("\n", $lines)]);
    }

    // ──────────────────────────────────────────────────────────
    // PANDUAN
    // ──────────────────────────────────────────────────────────
    public function guide()
    {
        $this->require_permission(self::PAGE_SETTINGS, 'view');
        $session = $this->waSession();
        $this->render('wa/guide', [
            'title'       => 'Panduan WhatsApp Bot',
            'active_menu' => 'wa.settings',
            'session'     => $session,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────
    private function waSession(): array
    {
        return $this->db->from('wa_session')->where('id', 1)->limit(1)->get()->row_array()
            ?: ['id' => 1, 'status' => 'UNKNOWN', 'bot_api_url' => 'http://127.0.0.1:3070', 'bot_api_token' => 'local-dev-token'];
    }

    private function dashboardStats(): array
    {
        $totalBroadcast  = (int)$this->db->count_all('wa_broadcast');
        $doneBroadcast   = (int)$this->db->where('status', 'DONE')->count_all_results('wa_broadcast');
        $totalSent       = (int)$this->db->select_sum('total_sent')->from('wa_broadcast')->get()->row_array()['total_sent'];
        $todaySent       = (int)$this->db->where('DATE(sent_at)', date('Y-m-d'))->where('status', 'SENT')->count_all_results('wa_send_log');
        $totalTemplates  = (int)$this->db->where('is_active', 1)->count_all_results('wa_template');
        $totalGroups     = (int)$this->db->where('is_active', 1)->count_all_results('wa_group_map');

        $recentLogs = $this->db->from('wa_send_log')->order_by('sent_at', 'DESC')->limit(10)->get()->result_array();

        return compact('totalBroadcast','doneBroadcast','totalSent','todaySent','totalTemplates','totalGroups','recentLogs');
    }

    private function saveBroadcast(array $data, string $manualLines = '', string $targetType = 'MANUAL'): int
    {
        $this->db->insert('wa_broadcast', $data);
        $broadcastId = (int)$this->db->insert_id();

        $targets = [];

        if ($targetType === 'MANUAL') {
            foreach (preg_split('/[\r\n]+/', $manualLines) as $raw) {
                $raw = trim($raw);
                if ($raw === '') continue;
                $parts = array_map('trim', explode('|', $raw, 2));
                $phone = preg_replace('/\D+/', '', $parts[0] ?? '');
                if (strpos($phone, '0') === 0) $phone = '62' . substr($phone, 1);
                if (strlen($phone) < 10) continue;
                $name = $parts[1] ?? '';
                $targets[] = ['phone' => $phone, 'name' => $name, 'vars' => []];
            }
        } elseif (in_array($targetType, ['ALL_MEMBERS','MEMBER_ACTIVE'], true)) {
            if ($this->db->table_exists('loy_member')) {
                $q = $this->db->from('loy_member m')
                    ->select('m.phone_number, m.full_name')
                    ->where('m.phone_number IS NOT NULL')
                    ->where('m.phone_number !=', '');
                if ($targetType === 'MEMBER_ACTIVE') {
                    $q->where('m.is_active', 1);
                }
                foreach ($q->get()->result_array() as $row) {
                    $phone = preg_replace('/\D+/', '', $row['phone_number']);
                    if (strpos($phone, '0') === 0) $phone = '62' . substr($phone, 1);
                    if (strlen($phone) < 10) continue;
                    $targets[] = ['phone' => $phone, 'name' => $row['full_name'] ?? '', 'vars' => ['nama' => $row['full_name'] ?? '']];
                }
            }
        }

        foreach ($targets as $t) {
            $this->db->insert('wa_broadcast_line', [
                'broadcast_id'  => $broadcastId,
                'phone_number'  => $t['phone'],
                'display_name'  => $t['name'] ?: null,
                'variables_json'=> !empty($t['vars']) ? json_encode($t['vars']) : null,
                'status'        => 'PENDING',
            ]);
        }

        $this->db->where('id', $broadcastId)->update('wa_broadcast', ['total_targets' => count($targets)]);

        return $broadcastId;
    }

    private function resolveMessage(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $template = str_replace('{{' . $key . '}}', (string)$value, $template);
        }
        return $template;
    }

    private function callBotApi(string $endpoint, string $method = 'GET', array $payload = []): array
    {
        $session = $this->waSession();
        $url     = rtrim($session['bot_api_url'], '/') . $endpoint
                 . '?token=' . urlencode($session['bot_api_token']);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'X-Sync-Token: ' . $session['bot_api_token']],
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $response   = curl_exec($ch);
        $httpCode   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['ok' => false, 'message' => 'Tidak dapat menghubungi WA Bot: ' . $curlError];
        }

        $decoded = json_decode($response ?: '', true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'message' => 'Respon bot tidak valid (HTTP ' . $httpCode . ')'];
        }

        // Update last_ping_at jika status endpoint
        if ($endpoint === '/internal/status') {
            $this->db->where('id', 1)->update('wa_session', [
                'last_ping_at' => date('Y-m-d H:i:s'),
                'status'       => strtoupper($decoded['status'] ?? 'UNKNOWN'),
                'phone_number' => $decoded['phone'] ?? null,
            ]);
        }

        return $decoded;
    }

    private function logSend(?int $broadcastId, string $source, ?string $phone, ?string $groupJid, ?string $name, string $message, string $status, string $error = ''): void
    {
        $this->db->insert('wa_send_log', [
            'broadcast_id'   => $broadcastId,
            'source'         => $source,
            'phone_number'   => $phone,
            'group_jid'      => $groupJid,
            'display_name'   => $name,
            'message_preview'=> mb_substr($message, 0, 500),
            'status'         => $status,
            'error_detail'   => $error ?: null,
            'sent_at'        => date('Y-m-d H:i:s'),
        ]);
    }

    private function jsonOut(array $data): void
    {
        $this->output->set_content_type('application/json')->set_output(json_encode($data));
    }

    private function enginePort(): int
    {
        $url = $this->waSession()['bot_api_url'] ?? 'http://127.0.0.1:3070';
        if (preg_match('/:(\d+)/', $url, $m)) return (int)$m[1];
        return 3070;
    }

    private function buildEnvString(string $engineDir): string
    {
        $envFile = $engineDir . '/.env';
        if (!file_exists($envFile)) return '';

        $str = '';
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) continue;
            [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
            $k = trim($k);
            $v = trim($v);
            if ($k !== '' && preg_match('/^[A-Z_][A-Z0-9_]*$/i', $k)) {
                $str .= $k . '=' . escapeshellarg($v) . ' ';
            }
        }
        return $str;
    }

    private function ensureSchema(): void
    {
        if (!$this->db->table_exists('wa_session')) {
            return; // SQL belum dijalankan
        }
        $row = $this->db->from('wa_session')->where('id', 1)->limit(1)->get()->row_array();
        if (!$row) {
            $this->db->insert('wa_session', ['id' => 1]);
        }
    }
}
