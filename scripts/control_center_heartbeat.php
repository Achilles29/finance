<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command can only run from CLI.\n");
    exit(1);
}

const FINANCE_ROOT = '/www/wwwroot/finance';
const HEARTBEAT_CONFIG = '/var/lib/finance-config/control-center-heartbeat.json';
const PRIVATE_DATABASE_CONFIG = '/var/lib/finance-config/database.php';

function heartbeat_fail(string $code, string $message): void
{
    fwrite(STDERR, json_encode(['status'=>'error','code'=>$code,'message'=>$message], JSON_UNESCAPED_SLASHES).PHP_EOL);
    exit(1);
}

function heartbeat_private_json(string $path): array
{
    if (!is_file($path) || is_link($path)) {
        heartbeat_fail('config_missing', 'Private heartbeat configuration is unavailable.');
    }
    $stat = @stat($path);
    if (!is_array($stat) || (($stat['mode'] ?? 0) & 0007) !== 0) {
        heartbeat_fail('config_permissions', 'Private heartbeat configuration permissions are unsafe.');
    }
    try {
        $config = json_decode((string)file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        heartbeat_fail('config_invalid', 'Private heartbeat configuration is malformed.');
    }
    if (!is_array($config)) heartbeat_fail('config_invalid', 'Private heartbeat configuration is malformed.');
    foreach (['endpoint','public_url','instance_id','key_id','secret','environment'] as $key) {
        if (!isset($config[$key]) || !is_string($config[$key]) || trim($config[$key]) === '') {
            heartbeat_fail('config_invalid', 'Private heartbeat configuration is incomplete.');
        }
    }
    if (!str_starts_with($config['endpoint'], 'https://') || !str_starts_with($config['public_url'], 'https://')) {
        heartbeat_fail('config_invalid', 'Heartbeat endpoints must use HTTPS.');
    }
    return $config;
}

function heartbeat_database_config(): array
{
    if (!is_file(PRIVATE_DATABASE_CONFIG) || is_link(PRIVATE_DATABASE_CONFIG)) {
        heartbeat_fail('database_config', 'Private database configuration is unavailable.');
    }
    $stat = @stat(PRIVATE_DATABASE_CONFIG);
    if (!is_array($stat) || (($stat['mode'] ?? 0) & 0007) !== 0) {
        heartbeat_fail('database_config', 'Private database configuration permissions are unsafe.');
    }
    if (!defined('ENVIRONMENT')) define('ENVIRONMENT', 'development');
    $db = [];
    include PRIVATE_DATABASE_CONFIG;
    $config = $db['default'] ?? null;
    if (!is_array($config)) heartbeat_fail('database_config', 'Private database configuration is unavailable.');
    foreach (['hostname','username','password','database'] as $key) {
        if (!isset($config[$key]) || trim((string)$config[$key]) === '') {
            heartbeat_fail('database_config', 'Private database configuration is incomplete.');
        }
    }
    return $config;
}

function heartbeat_table_exists(PDO $database, string $table): bool
{
    $query = $database->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $query->execute([$table]);
    return (int)$query->fetchColumn() === 1;
}

function heartbeat_git_version(string $root): string
{
    $git = $root.'/.git';
    $headPath = $git.'/HEAD';
    if (!is_file($headPath)) return 'unversioned';
    $head = trim((string)file_get_contents($headPath));
    $hash = $head;
    if (str_starts_with($head, 'ref: ')) {
        $ref = substr($head, 5);
        $refPath = $git.'/'.$ref;
        $hash = is_file($refPath) ? trim((string)file_get_contents($refPath)) : '';
        if ($hash === '' && is_file($git.'/packed-refs')) {
            foreach (file($git.'/packed-refs', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                if ($line[0] !== '#' && substr($line, 41) === $ref) {
                    $hash = substr($line, 0, 40);
                    break;
                }
            }
        }
    }
    return preg_match('/^[a-f0-9]{40}$/D', $hash) === 1 ? 'git-'.substr($hash, 0, 12) : 'unversioned';
}

function heartbeat_http_status(string $url): string
{
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'Namua-Finance-Heartbeat/1.0',
    ]);
    curl_exec($handle);
    $error = curl_errno($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);
    return $error === 0 && $status >= 200 && $status < 400 ? 'OK' : 'DOWN';
}

function heartbeat_backup_status(): string
{
    $latest = 0;
    foreach (glob(FINANCE_ROOT.'/backup/dumps/*') ?: [] as $path) {
        if (is_file($path) && !is_link($path)) $latest = max($latest, (int)filemtime($path));
    }
    if ($latest === 0) return 'UNKNOWN';
    return $latest >= time() - 172800 ? 'OK' : 'DEGRADED';
}

function heartbeat_random_token(int $bytes): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

$config = heartbeat_private_json(HEARTBEAT_CONFIG);
$components = [
    'database' => 'UNKNOWN',
    'http' => heartbeat_http_status($config['public_url']),
    'schema_migration' => 'UNKNOWN',
    'runtime_queue' => 'UNKNOWN',
    'backup' => heartbeat_backup_status(),
];
$metrics = ['queue_pending'=>0, 'queue_failed'=>0];
$schemaVersion = '';

try {
    $dbConfig = heartbeat_database_config();
    $database = new PDO(
        'mysql:host='.$dbConfig['hostname'].';dbname='.$dbConfig['database'].';charset=utf8mb4',
        $dbConfig['username'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT=>5]
    );
    $database->query('SELECT 1')->fetchColumn();
    $components['database'] = 'OK';

    if (heartbeat_table_exists($database, 'sys_schema_migration')) {
        $latestMigration = $database->query('SELECT migration_id FROM sys_schema_migration ORDER BY applied_at DESC LIMIT 1')->fetchColumn();
        $schemaVersion = is_string($latestMigration) ? substr($latestMigration, 0, 80) : '';
        $components['schema_migration'] = $schemaVersion !== '' ? 'OK' : 'DEGRADED';
    }

    $queueTables = [
        'pos_runtime_job' => ['QUEUED','PROCESSING'],
        'pos_product_availability_queue' => ['QUEUED','PROCESSING'],
        'tg_delivery_queue' => ['PENDING','PROCESSING'],
    ];
    $knownQueue = false;
    foreach ($queueTables as $table => $pendingStates) {
        if (!heartbeat_table_exists($database, $table)) continue;
        $knownQueue = true;
        $quotedStates = implode(',', array_fill(0, count($pendingStates), '?'));
        $query = $database->prepare("SELECT SUM(status IN ({$quotedStates})) AS pending_count, SUM(status = 'FAILED') AS failed_count FROM `{$table}`");
        $query->execute($pendingStates);
        $row = $query->fetch(PDO::FETCH_ASSOC) ?: [];
        $metrics['queue_pending'] += (int)($row['pending_count'] ?? 0);
        $metrics['queue_failed'] += (int)($row['failed_count'] ?? 0);
    }
    if ($knownQueue) $components['runtime_queue'] = $metrics['queue_failed'] > 0 ? 'DEGRADED' : 'OK';
} catch (Throwable $error) {
    $components['database'] = 'DOWN';
}

$total = @disk_total_space(FINANCE_ROOT);
$free = @disk_free_space(FINANCE_ROOT);
if (is_float($total) && is_float($free) && $total > 0) {
    $metrics['disk_used_percent'] = round((($total - $free) / $total) * 100, 2);
}

$health = 'OK';
if (in_array('DOWN', $components, true) || (($metrics['disk_used_percent'] ?? 0) >= 95)) {
    $health = 'DOWN';
} elseif (in_array('DEGRADED', $components, true) || (($metrics['disk_used_percent'] ?? 0) >= 85)) {
    $health = 'DEGRADED';
}

$timestamp = gmdate('Y-m-d\TH:i:s\Z');
$nonce = heartbeat_random_token(18);
$idempotencyKey = 'hb-'.bin2hex(random_bytes(16));
$payload = [
    'instance_id' => $config['instance_id'],
    'sent_at' => $timestamp,
    'app_version' => heartbeat_git_version(FINANCE_ROOT),
    'schema_version' => $schemaVersion,
    'environment' => $config['environment'],
    'health' => $health,
    'components' => $components,
    'metrics' => $metrics,
];
$body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$payloadHash = hash('sha256', $body);
$canonical = implode("\n", ['POST','/api/v1/heartbeats',$config['instance_id'],$config['key_id'],$timestamp,$nonce,$idempotencyKey,$payloadHash]);
$signature = hash_hmac('sha256', $canonical, $config['secret']);

$responseCode = 0;
$responseBody = '';
for ($attempt = 1; $attempt <= 2; $attempt++) {
    $handle = curl_init($config['endpoint']);
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Namua-Instance-ID: '.$config['instance_id'],
            'X-Namua-Key-ID: '.$config['key_id'],
            'X-Namua-Timestamp: '.$timestamp,
            'X-Namua-Nonce: '.$nonce,
            'X-Namua-Signature: '.$signature,
            'Idempotency-Key: '.$idempotencyKey,
            'User-Agent: Namua-Finance-Heartbeat/1.0',
        ],
    ]);
    $responseBody = (string)curl_exec($handle);
    $curlError = curl_errno($handle);
    $responseCode = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);
    if ($curlError === 0 && $responseCode < 500) break;
}

$response = json_decode($responseBody, true);
if (!in_array($responseCode, [200,202], true) || !is_array($response) || ($response['status'] ?? '') !== 'accepted') {
    heartbeat_fail('delivery_failed', 'Control Center rejected or did not receive the heartbeat.');
}

fwrite(STDOUT, json_encode([
    'status'=>'ok',
    'control_http_status'=>$responseCode,
    'health'=>$health,
    'duplicate'=>(bool)($response['duplicate'] ?? false),
], JSON_UNESCAPED_SLASHES).PHP_EOL);
