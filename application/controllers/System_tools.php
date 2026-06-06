<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class System_tools extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('file');
    }

    // ── Halaman Utama — semua tab digabung ────────────────────────
    public function index()
    {
        $this->require_permission('system.dbtools.settings', 'view');
        $financeRoot  = FCPATH;
        $dumpDir      = $financeRoot . 'backup/dumps/';
        $envFile      = $financeRoot . 'scripts/backup/.env';
        $statusFile   = $financeRoot . 'backup/logs/replication_status.json';
        $failoverFile = $financeRoot . 'backup/logs/failover_time.txt';

        $recentDumps  = $this->_listRecentFiles($dumpDir, '*.sql.gz', 5);
        $recentDumps  = array_merge($recentDumps, $this->_listRecentFiles($dumpDir, '*.sql', 5));
        usort($recentDumps, fn($a, $b) => $b['mtime'] - $a['mtime']);

        $rows = $this->db->table_exists('sys_app_config') ? $this->db->get('sys_app_config')->result_array() : [];
        $cfg  = [];
        foreach ($rows as $r) {
            $cfg[$r['config_key']] = (string)($r['config_value'] ?? '');
        }

        $replStatus     = file_exists($statusFile) ? (json_decode(file_get_contents($statusFile), true) ?: []) : [];
        $failoverActive = file_exists($failoverFile);

        $this->render('system/dbtools', [
            'title'           => 'Perlindungan Database',
            'active_menu'     => 'system.dbtools.settings',
            'cfg'             => $cfg,
            'env_exists'      => file_exists($envFile),
            'recent_dumps'    => array_slice($recentDumps, 0, 5),
            'repl_status'     => $replStatus,
            'failover_active' => $failoverActive,
            'failover_time'   => $failoverActive ? trim(file_get_contents($failoverFile)) : null,
            'finance_root'    => $financeRoot,
            'is_windows'      => strtoupper(substr(PHP_OS, 0, 3)) === 'WIN',
        ]);
    }

    // ── Halaman Panduan Backup ─────────────────────────────────────
    public function backup_guide()
    {
        $this->require_permission('system.backup.guide', 'view');
        $financeRoot = FCPATH;
        $backupDir   = $financeRoot . 'backup/dumps/';

        $recentDumps = $this->_listRecentFiles($backupDir, '*.sql.gz', 10);
        $recentDumps = array_merge($recentDumps, $this->_listRecentFiles($backupDir, '*.sql', 10));
        usort($recentDumps, function($a, $b) { return $b['mtime'] - $a['mtime']; });
        $recentDumps = array_slice($recentDumps, 0, 10);

        $envFile = $financeRoot . 'scripts/backup/.env';
        $this->render('system/backup_guide', [
            'title'          => 'Panduan Backup DB',
            'active_menu'    => 'system.backup.guide',
            'recent_dumps'   => $recentDumps,
            'env_configured' => file_exists($envFile) && filesize($envFile) > 10,
            'finance_root'   => $financeRoot,
        ]);
    }

    // ── Halaman Panduan Replication ────────────────────────────────
    public function replication_guide()
    {
        $this->require_permission('system.replication.guide', 'view');
        $financeRoot  = FCPATH;
        $statusFile   = $financeRoot . 'backup/logs/replication_status.json';
        $failoverFile = $financeRoot . 'backup/logs/failover_time.txt';

        $replStatus     = file_exists($statusFile) ? (json_decode(file_get_contents($statusFile), true) ?: []) : [];
        $failoverActive = file_exists($failoverFile);

        $this->render('system/replication_guide', [
            'title'           => 'Panduan Replication & Failover',
            'active_menu'     => 'system.replication.guide',
            'repl_status'     => $replStatus,
            'failover_active' => $failoverActive,
            'failover_time'   => $failoverActive ? trim(file_get_contents($failoverFile)) : null,
            'finance_root'    => $financeRoot,
        ]);
    }

    // ── Settings page ──────────────────────────────────────────────
    public function settings()
    {
        $this->require_permission('system.dbtools.settings', 'view');

        $financeRoot = FCPATH;
        $envFile     = $financeRoot . 'scripts/backup/.env';
        $envExists   = file_exists($envFile);

        // Load semua config dari DB
        $rows = $this->db->get('sys_app_config')->result_array();
        $cfg  = [];
        foreach ($rows as $r) {
            $cfg[$r['config_key']] = (string)($r['config_value'] ?? '');
        }

        // Status file
        $statusFile   = $financeRoot . 'backup/logs/replication_status.json';
        $failoverFile = $financeRoot . 'backup/logs/failover_time.txt';
        $dumpDir      = $financeRoot . 'backup/dumps/';
        $recentDumps  = $this->_listRecentFiles($dumpDir, '*.sql.gz', 5);
        $recentDumps  = array_merge($recentDumps, $this->_listRecentFiles($dumpDir, '*.sql', 5));
        usort($recentDumps, fn($a, $b) => $b['mtime'] - $a['mtime']);

        $replStatus   = file_exists($statusFile) ? (json_decode(file_get_contents($statusFile), true) ?: []) : [];
        $failoverActive = file_exists($failoverFile);

        $this->render('system/settings', [
            'title'           => 'DB Tools — Pengaturan',
            'active_menu'     => 'system.dbtools.settings',
            'cfg'             => $cfg,
            'env_exists'      => $envExists,
            'recent_dumps'    => $recentDumps,
            'repl_status'     => $replStatus,
            'failover_active' => $failoverActive,
            'failover_time'   => $failoverActive ? trim(file_get_contents($failoverFile)) : null,
            'finance_root'    => $financeRoot,
            'is_windows'      => strtoupper(substr(PHP_OS, 0, 3)) === 'WIN',
        ]);
    }

    // ── Save settings ──────────────────────────────────────────────
    public function settings_save()
    {
        $this->require_permission('system.dbtools.settings', 'edit');
        $payload = $this->request_payload();

        $allowed = [
            'backup.db_host', 'backup.db_port', 'backup.db_user', 'backup.db_pass',
            'backup.db_name', 'backup.retention_days', 'backup.repo_remote',
            'backup.repo_branch', 'backup.exclude_tables',
            'repl.server_role', 'repl.master_host', 'repl.master_port',
            'repl.repl_user', 'repl.repl_pass',
            'tunnel.enabled', 'tunnel.ssh_host', 'tunnel.ssh_user',
            'tunnel.local_port', 'tunnel.remote_port',
        ];

        $group_map = [
            'backup.' => 'backup',
            'repl.'   => 'replication',
            'tunnel.' => 'tunnel',
        ];

        // Field password: jika dikirim kosong = "tidak diubah", jangan timpa nilai lama
        $skipIfEmpty = ['backup.db_pass', 'repl.repl_pass'];

        $this->db->trans_begin();
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $payload)) continue;
            if (in_array($key, $skipIfEmpty, true) && $payload[$key] === '') continue;
            $group = 'general';
            foreach ($group_map as $prefix => $g) {
                if (str_starts_with($key, $prefix)) { $group = $g; break; }
            }
            $this->db->replace('sys_app_config', [
                'config_group' => $group,
                'config_key'   => $key,
                'config_value' => (string)($payload[$key] ?? ''),
                'updated_by'   => (int)($this->current_user['id'] ?? 0),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
        }
        $this->db->trans_commit();

        // Generate .env file dari config yang tersimpan
        $this->_writeEnvFile();

        $this->json_ok(['message' => 'Pengaturan berhasil disimpan dan .env diperbarui.']);
    }

    // ── Aksi: List Tables ─────────────────────────────────────────
    public function action_list_tables()
    {
        $this->require_permission('system.dbtools.settings', 'view');

        // Coba gunakan DB dari config yang tersimpan (bisa beda dari DB app)
        $host = $this->_cfg('backup.db_host', 'localhost');
        $port = (int)$this->_cfg('backup.db_port', '3306');
        $user = $this->_cfg('backup.db_user', 'root');
        $pass = $this->_cfg('backup.db_pass', '');
        $name = $this->_cfg('backup.db_name', 'db_finance');

        try {
            $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [PDO::ATTR_TIMEOUT => 5]);
            $tables = $pdo->query("SHOW TABLES FROM `{$name}`")->fetchAll(PDO::FETCH_COLUMN);

            // Estimasi ukuran per tabel
            $sizes = [];
            $sizeRows = $pdo->query(
                "SELECT TABLE_NAME, ROUND((DATA_LENGTH + INDEX_LENGTH)/1024/1024, 1) AS size_mb,
                        TABLE_ROWS AS est_rows
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = " . $pdo->quote($name) . "
                 ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($sizeRows as $r) {
                $sizes[$r['TABLE_NAME']] = ['size_mb' => (float)$r['size_mb'], 'est_rows' => (int)$r['est_rows']];
            }

            $result = array_map(function($t) use ($sizes) {
                return [
                    'name'     => $t,
                    'size_mb'  => $sizes[$t]['size_mb'] ?? 0,
                    'est_rows' => $sizes[$t]['est_rows'] ?? 0,
                ];
            }, $tables);

            $this->json_ok(['tables' => $result, 'db' => $name]);
        } catch (Exception $e) {
            $this->json_error('Gagal memuat tabel: ' . $e->getMessage(), 422);
        }
    }

    // ── Aksi: Run Backup Now ───────────────────────────────────────
    public function action_run_backup()
    {
        $this->require_permission('system.dbtools.settings', 'edit');
        $result = $this->_runScript('backup', 'backup_full');
        if ($result['ok']) {
            $this->json_ok(['output' => $result['output'], 'message' => 'Backup berhasil dijalankan.']);
        } else {
            $this->json_error($result['output'] ?: 'Backup gagal. Cek permission script dan konfigurasi .env.', 500);
        }
    }

    // ── Aksi: Test DB Connection ───────────────────────────────────
    public function action_test_db()
    {
        $this->require_permission('system.dbtools.settings', 'view');
        $host = (string)$this->input->get('host', true) ?: $this->_cfg('backup.db_host', 'localhost');
        $port = (int)($this->input->get('port', true) ?: $this->_cfg('backup.db_port', '3306'));
        $user = (string)$this->input->get('user', true) ?: $this->_cfg('backup.db_user', 'root');
        $pass = (string)$this->input->get('pass', true) ?: $this->_cfg('backup.db_pass', '');
        $name = (string)$this->input->get('name', true) ?: $this->_cfg('backup.db_name', 'db_finance');

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_TIMEOUT => 5]);
            $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
            $this->json_ok(['message' => "Koneksi berhasil! MySQL versi: {$ver}"]);
        } catch (Exception $e) {
            $this->json_error('Koneksi gagal: ' . $e->getMessage(), 422);
        }
    }

    // ── Aksi: Check Replication Status ────────────────────────────
    public function action_check_replication()
    {
        $this->require_permission('system.dbtools.settings', 'view');
        $role = strtoupper($this->_cfg('repl.server_role', 'STANDALONE'));

        $data = ['role' => $role, 'timestamp' => date('Y-m-d H:i:s')];

        if ($role === 'SLAVE') {
            try {
                $slaveStatus = $this->db->query('SHOW SLAVE STATUS')->row_array();
                if ($slaveStatus) {
                    $data['status']       = ($slaveStatus['Slave_IO_Running'] === 'Yes' && $slaveStatus['Slave_SQL_Running'] === 'Yes') ? 'OK' : 'ERROR';
                    $data['io_running']   = $slaveStatus['Slave_IO_Running'];
                    $data['sql_running']  = $slaveStatus['Slave_SQL_Running'];
                    $data['lag_seconds']  = (int)($slaveStatus['Seconds_Behind_Master'] ?? 0);
                    $data['master_host']  = $slaveStatus['Master_Host'] ?? '';
                    $data['last_error']   = $slaveStatus['Last_SQL_Error'] ?? '';
                } else {
                    $data['status'] = 'NOT_CONFIGURED';
                }
            } catch (Exception $e) {
                $data['status'] = 'ERROR';
                $data['error']  = $e->getMessage();
            }
        } elseif ($role === 'MASTER') {
            try {
                $masterStatus = $this->db->query('SHOW MASTER STATUS')->row_array();
                $data['status']   = 'OK';
                $data['binlog']   = $masterStatus['File'] ?? '-';
                $data['position'] = $masterStatus['Position'] ?? 0;
            } catch (Exception $e) {
                $data['status'] = 'ERROR';
                $data['error']  = $e->getMessage();
            }
        } else {
            $data['status'] = 'STANDALONE';
        }

        // Simpan ke file untuk health check
        $statusFile = FCPATH . 'backup/logs/replication_status.json';
        @mkdir(dirname($statusFile), 0755, true);
        file_put_contents($statusFile, json_encode($data));

        $this->json_ok($data);
    }

    // ── Aksi: Failover ────────────────────────────────────────────
    public function action_failover()
    {
        $this->require_permission('system.dbtools.settings', 'edit');
        $confirm = (string)($this->request_payload()['confirm'] ?? '');
        if ($confirm !== 'YES_FAILOVER') {
            $this->json_error('Konfirmasi tidak valid.', 422);
            return;
        }

        // Jalankan via SQL langsung (lebih safe dari shell_exec untuk failover)
        try {
            $this->db->query('STOP SLAVE');
            $this->db->query('RESET SLAVE ALL');
            $this->db->query('SET GLOBAL read_only = OFF');
            $this->db->query('SET GLOBAL super_read_only = OFF');

            // Catat waktu failover
            $failoverTime = date('Y-m-d H:i:s');
            $failoverFile = FCPATH . 'backup/logs/failover_time.txt';
            file_put_contents($failoverFile, $failoverTime . "\n");

            // Update role di config
            $this->db->where('config_key', 'repl.server_role')
                     ->update('sys_app_config', ['config_value' => 'STANDALONE', 'updated_at' => date('Y-m-d H:i:s')]);

            $this->json_ok(['message' => "Failover berhasil. Server ini sekarang mode standalone.", 'failover_time' => $failoverTime]);
        } catch (Exception $e) {
            $this->json_error('Failover gagal: ' . $e->getMessage(), 500);
        }
    }

    // ── Aksi: Restart Replication ─────────────────────────────────
    public function action_restart_replication()
    {
        $this->require_permission('system.dbtools.settings', 'edit');
        $payload     = $this->request_payload();
        $masterHost  = (string)($payload['master_host']  ?? $this->_cfg('repl.master_host', ''));
        $masterPort  = (int)($payload['master_port']     ?? $this->_cfg('repl.master_port', '3306'));
        $replUser    = (string)($payload['repl_user']    ?? $this->_cfg('repl.repl_user', 'repl_user'));
        $replPass    = (string)($payload['repl_pass']    ?? $this->_cfg('repl.repl_pass', ''));
        $tunnelOn    = $this->_cfg('tunnel.enabled', '0') === '1';
        $connHost    = $tunnelOn ? '127.0.0.1' : $masterHost;
        $connPort    = $tunnelOn ? (int)$this->_cfg('tunnel.local_port', '3307') : $masterPort;

        if (empty($masterHost)) {
            $this->json_error('Master host belum dikonfigurasi.', 422);
            return;
        }

        try {
            // Ambil posisi master via koneksi terpisah
            $dsn = "mysql:host={$connHost};port={$connPort};charset=utf8mb4";
            $pdo = new PDO($dsn, $replUser, $replPass, [PDO::ATTR_TIMEOUT => 10]);
            $masterStatus = $pdo->query('SHOW MASTER STATUS')->fetch(PDO::FETCH_ASSOC);
            if (!$masterStatus) {
                $this->json_error('Tidak bisa baca MASTER STATUS dari Server 1.', 422);
                return;
            }

            $logFile = $masterStatus['File'];
            $logPos  = $masterStatus['Position'];

            $this->db->query("STOP SLAVE");
            $this->db->query("SET GLOBAL read_only = ON");
            $this->db->query("CHANGE MASTER TO
                MASTER_HOST='" . $this->db->escape_str($connHost) . "',
                MASTER_PORT=" . (int)$connPort . ",
                MASTER_USER='" . $this->db->escape_str($replUser) . "',
                MASTER_PASSWORD='" . $this->db->escape_str($replPass) . "',
                MASTER_LOG_FILE='" . $this->db->escape_str($logFile) . "',
                MASTER_LOG_POS=" . (int)$logPos
            );
            $this->db->query("START SLAVE");

            // Hapus failover marker
            @unlink(FCPATH . 'backup/logs/failover_time.txt');

            // Update role
            $this->db->where('config_key', 'repl.server_role')
                     ->update('sys_app_config', ['config_value' => 'SLAVE', 'updated_at' => date('Y-m-d H:i:s')]);

            $this->json_ok(['message' => "Replication berhasil di-restart. Server kembali jadi Slave.", 'log_file' => $logFile, 'log_pos' => $logPos]);
        } catch (Exception $e) {
            $this->json_error('Gagal restart replication: ' . $e->getMessage(), 500);
        }
    }

    // ── AJAX: replication status ───────────────────────────────────
    public function backup_status()
    {
        $this->require_permission('system.backup.guide', 'view');
        $dumpDir = FCPATH . 'backup/dumps/';
        $logFile = FCPATH . 'backup/logs/cron.log';
        $dumps   = $this->_listRecentFiles($dumpDir, 'backup_*.sql*', 20);
        usort($dumps, fn($a, $b) => $b['mtime'] - $a['mtime']);
        $lastLog = '';
        if (file_exists($logFile)) {
            $lines   = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lastLog = implode("\n", array_slice($lines, -30));
        }
        $this->json_ok(['dumps' => $dumps, 'last_log' => $lastLog, 'dump_count' => count($dumps)]);
    }

    public function replication_status()
    {
        $this->require_permission('system.replication.guide', 'view');
        $statusFile   = FCPATH . 'backup/logs/replication_status.json';
        $failoverFile = FCPATH . 'backup/logs/failover_time.txt';
        $status = file_exists($statusFile) ? (json_decode(file_get_contents($statusFile), true) ?: []) : [];
        $status['failover_active'] = file_exists($failoverFile);
        $status['failover_time']   = $status['failover_active'] ? trim(file_get_contents($failoverFile)) : null;
        $this->json_ok($status);
    }

    // ── Helpers ────────────────────────────────────────────────────
    private function _cfg(string $key, string $default = ''): string
    {
        if (!$this->db->table_exists('sys_app_config')) return $default;
        $row = $this->db->select('config_value')->where('config_key', $key)->get('sys_app_config')->row_array();
        return $row ? (string)($row['config_value'] ?? $default) : $default;
    }

    private function _writeEnvFile(): void
    {
        $envPath = FCPATH . 'scripts/backup/.env';

        // Bungkus nilai dalam single-quote agar karakter spesial aman saat di-source bash.
        // Escape single-quote dalam nilai dengan cara: ' → '\''
        $q = static function(string $v): string {
            return "'" . str_replace("'", "'\\''", $v) . "'";
        };

        $lines = [
            "# Auto-generated oleh Finance App — " . date('Y-m-d H:i:s'),
            "# Jangan edit manual jika menggunakan UI settings",
            "",
            "# Database",
            "DB_HOST=" . $q($this->_cfg('backup.db_host', 'localhost')),
            "DB_PORT=" . $q($this->_cfg('backup.db_port', '3306')),
            "DB_USER=" . $q($this->_cfg('backup.db_user', 'root')),
            "DB_PASS=" . $q($this->_cfg('backup.db_pass', '')),
            "DB_NAME=" . $q($this->_cfg('backup.db_name', 'db_finance')),
            "",
            "# Backup",
            "BACKUP_DIR=backup/dumps",
            "LOG_DIR=backup/logs",
            "RETENTION_DAYS=" . $q($this->_cfg('backup.retention_days', '3')),
            "BACKUP_REPO_REMOTE=" . $q($this->_cfg('backup.repo_remote', 'origin')),
            "BACKUP_REPO_BRANCH=" . $q($this->_cfg('backup.repo_branch', 'main')),
            "EXCLUDE_TABLES=" . $q($this->_cfg('backup.exclude_tables', '')),
            "",
            "# Replication",
            "SERVER_ROLE=" . $q($this->_cfg('repl.server_role', 'STANDALONE')),
            "MASTER_HOST=" . $q($this->_cfg('repl.master_host', '')),
            "MASTER_PORT=" . $q($this->_cfg('repl.master_port', '3306')),
            "REPL_USER=" . $q($this->_cfg('repl.repl_user', 'repl_user')),
            "REPL_PASS=" . $q($this->_cfg('repl.repl_pass', '')),
            "",
            "# SSH Tunnel",
            "TUNNEL_ENABLED=" . $q($this->_cfg('tunnel.enabled', '0')),
            "SSH_USER=" . $q($this->_cfg('tunnel.ssh_user', 'root')),
            "TUNNEL_LOCAL_PORT=" . $q($this->_cfg('tunnel.local_port', '3307')),
        ];
        @file_put_contents($envPath, implode("\n", $lines) . "\n");
    }

    private function _runScript(string $folder, string $name): array
    {
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $ext       = $isWindows ? '.bat' : '.sh';
        $script    = FCPATH . "scripts/{$folder}/{$name}{$ext}";

        if (!file_exists($script)) {
            return ['ok' => false, 'output' => "Script tidak ditemukan: {$script}"];
        }

        if (!$isWindows) {
            @chmod($script, 0755);
        }

        $cmd    = $isWindows ? "cmd /c \"{$script}\" 2>&1" : "bash \"{$script}\" 2>&1";
        $output = [];
        $code   = 0;
        exec($cmd, $output, $code);

        return ['ok' => $code === 0, 'output' => implode("\n", $output), 'code' => $code];
    }

    private function request_payload(): array
    {
        $raw = trim((string)$this->input->raw_input_stream);
        if ($raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) return $json;
        }
        $post = $this->input->post(null, true);
        return is_array($post) ? $post : [];
    }

    private function json_ok(array $data = []): void
    {
        while (ob_get_level() > 0) { @ob_end_clean(); }
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(array_merge(['ok' => true], $data), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function json_error(string $message, int $status = 422): void
    {
        while (ob_get_level() > 0) { @ob_end_clean(); }
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function _listRecentFiles(string $dir, string $pattern, int $limit): array
    {
        if (!is_dir($dir)) return [];
        $files  = glob($dir . $pattern) ?: [];
        $result = [];
        foreach ($files as $f) {
            $result[] = [
                'name'  => basename($f),
                'size'  => $this->_humanFilesize(filesize($f) ?: 0),
                'mtime' => filemtime($f),
                'date'  => date('d M Y H:i', filemtime($f)),
            ];
        }
        usort($result, fn($a, $b) => $b['mtime'] - $a['mtime']);
        return array_slice($result, 0, $limit);
    }

    private function _humanFilesize(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
