<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class System_tools extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('file');
    }

    public function backup_guide()
    {
        $this->require_permission('system.backup.guide', 'view');
        $financeRoot  = FCPATH; // path ke finance/
        $backupDir    = $financeRoot . 'backup/dumps/';
        $logDir       = $financeRoot . 'backup/logs/';
        $envFile      = $financeRoot . 'scripts/backup/.env';
        $statusFile   = $financeRoot . 'backup/logs/replication_status.json';

        $recentDumps  = $this->_listRecentFiles($backupDir, '*.sql.gz', 10);
        $recentDumps  = array_merge($recentDumps, $this->_listRecentFiles($backupDir, '*.sql', 10));
        usort($recentDumps, function($a, $b) { return $b['mtime'] - $a['mtime']; });
        $recentDumps  = array_slice($recentDumps, 0, 10);

        $envExists     = file_exists($envFile);
        $envConfigured = $envExists && filesize($envFile) > 10;

        $this->render('system/backup_guide', [
            'title'           => 'Panduan Backup DB',
            'active_menu'     => 'grp.system',
            'recent_dumps'    => $recentDumps,
            'env_exists'      => $envExists,
            'env_configured'  => $envConfigured,
            'backup_dir'      => $backupDir,
            'finance_root'    => $financeRoot,
        ]);
    }

    public function backup_status()
    {
        $this->require_permission('system.backup.guide', 'view');
        $financeRoot = FCPATH;
        $backupDir   = $financeRoot . 'backup/dumps/';
        $logFile     = $financeRoot . 'backup/logs/cron.log';

        $recentDumps = $this->_listRecentFiles($backupDir, 'backup_*.sql*', 20);
        usort($recentDumps, function($a, $b) { return $b['mtime'] - $a['mtime']; });

        $lastLog = '';
        if (file_exists($logFile)) {
            $lines   = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lastLog = implode("\n", array_slice($lines, -30));
        }

        $this->json_ok([
            'dumps'     => $recentDumps,
            'last_log'  => $lastLog,
            'dump_count' => count($recentDumps),
            'last_dump'  => !empty($recentDumps) ? $recentDumps[0]['name'] : null,
        ]);
    }

    public function replication_guide()
    {
        $this->require_permission('system.replication.guide', 'view');
        $financeRoot  = FCPATH;
        $statusFile   = $financeRoot . 'backup/logs/replication_status.json';
        $failoverFile = $financeRoot . 'backup/logs/failover_time.txt';

        $replStatus   = [];
        if (file_exists($statusFile)) {
            $json = file_get_contents($statusFile);
            $replStatus = json_decode($json, true) ?: [];
        }

        $failoverActive = file_exists($failoverFile);
        $failoverTime   = $failoverActive ? trim(file_get_contents($failoverFile)) : null;

        $this->render('system/replication_guide', [
            'title'          => 'Panduan Replication & Failover',
            'active_menu'    => 'grp.system',
            'repl_status'    => $replStatus,
            'failover_active' => $failoverActive,
            'failover_time'  => $failoverTime,
            'finance_root'   => $financeRoot,
        ]);
    }

    public function replication_status()
    {
        $this->require_permission('system.replication.guide', 'view');
        $financeRoot  = FCPATH;
        $statusFile   = $financeRoot . 'backup/logs/replication_status.json';
        $failoverFile = $financeRoot . 'backup/logs/failover_time.txt';

        $status = [];
        if (file_exists($statusFile)) {
            $status = json_decode(file_get_contents($statusFile), true) ?: [];
        }
        $status['failover_active'] = file_exists($failoverFile);
        $status['failover_time']   = $status['failover_active']
            ? trim(file_get_contents($failoverFile)) : null;

        $this->json_ok($status);
    }

    private function _listRecentFiles(string $dir, string $pattern, int $limit): array
    {
        if (!is_dir($dir)) return [];
        $files = glob($dir . $pattern) ?: [];
        $result = [];
        foreach ($files as $f) {
            $result[] = [
                'name'  => basename($f),
                'path'  => $f,
                'size'  => $this->_humanFilesize(filesize($f)),
                'mtime' => filemtime($f),
                'date'  => date('d M Y H:i', filemtime($f)),
            ];
        }
        usort($result, function($a, $b) { return $b['mtime'] - $a['mtime']; });
        return array_slice($result, 0, $limit);
    }

    private function _humanFilesize(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
