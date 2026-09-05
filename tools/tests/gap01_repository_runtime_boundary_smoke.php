<?php

declare(strict_types=1);

/**
 * GAP-01 repository/runtime boundary contract.
 *
 * Read-only: verifies the Git index and package policy without reading runtime
 * file contents or printing customer filenames.
 */

$root = dirname(__DIR__, 2);
$checks = 0;
$failures = [];

$check = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        return;
    }
    echo 'PASS: ' . $message . PHP_EOL;
};

$git = static function (array $arguments) use ($root): array {
    $command = array_merge(['/usr/bin/git', '-C', $root], $arguments);
    $process = proc_open($command, [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, $root, ['PATH' => '/usr/bin:/bin']);
    if (!is_resource($process)) {
        return ['code' => 255, 'out' => '', 'err' => ''];
    }
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['code' => proc_close($process), 'out' => (string)$out, 'err' => (string)$err];
};

$inside = $git(['rev-parse', '--is-inside-work-tree']);
$check($inside['code'] === 0 && trim($inside['out']) === 'true', 'workspace is a readable Git repository');

$head = $git(['rev-parse', '--verify', 'HEAD^{commit}']);
$check($head['code'] === 0 && preg_match('/^[a-f0-9]{40}\n?$/D', $head['out']) === 1, 'HEAD resolves to one commit without exposing source content');

$trackedResult = $git(['ls-files', '-z']);
$tracked = $trackedResult['code'] === 0
    ? array_values(array_filter(explode("\0", $trackedResult['out']), static fn(string $path): bool => $path !== ''))
    : [];
$check($trackedResult['code'] === 0 && $tracked !== [], 'tracked-file inventory is readable');

$allowedRuntimePlaceholders = [
    'backup/dumps/.gitkeep',
    'backup/logs/.gitkeep',
    'uploads/.gitkeep',
    'tmp/.gitkeep',
    'output/.gitkeep',
];
$runtimePrefixes = ['backup/dumps/', 'backup/logs/', 'uploads/', 'tmp/', 'output/'];
$trackedRuntime = [];
$trackedSecretOrGenerated = [];
foreach ($tracked as $path) {
    foreach ($runtimePrefixes as $prefix) {
        if (str_starts_with($path, $prefix) && !in_array($path, $allowedRuntimePlaceholders, true)) {
            $trackedRuntime[] = $path;
        }
    }
    if (basename($path) === '.env' || str_contains($path, '/__pycache__/')
        || preg_match('/\.py[cod]$/D', $path) === 1 || str_ends_with($path, '.sql.gz')
    ) {
        $trackedSecretOrGenerated[] = $path;
    }
}
$check($trackedRuntime === [], 'customer uploads, backup payloads, logs, tmp, and generated output are absent from the Git index');
$check($trackedSecretOrGenerated === [], 'credential env, bytecode, and database archives are absent from the Git index');

sort($allowedRuntimePlaceholders, SORT_STRING);
$trackedPlaceholders = array_values(array_intersect($tracked, $allowedRuntimePlaceholders));
sort($trackedPlaceholders, SORT_STRING);
$check($trackedPlaceholders === $allowedRuntimePlaceholders, 'runtime roots retain only five source-safe placeholders');
foreach ($allowedRuntimePlaceholders as $placeholder) {
    $path = $root . '/' . $placeholder;
    $check(is_file($path) && !is_link($path), 'runtime placeholder is a regular file: ' . $placeholder);
}

$gitignore = (string)file_get_contents($root . '/.gitignore');
foreach ([
    '/backup/dumps/*', '/backup/logs/*', '/uploads/*', '/tmp/*', '/output/*',
    '/scripts/backup/.env', '/.codex/', '/.agents/', '/.vscode/',
    '**/__pycache__/', '*.py[cod]',
] as $rule) {
    $check(str_contains($gitignore, $rule), 'Git ignore rule is present: ' . $rule);
}

$package = json_decode((string)file_get_contents($root . '/tools/release/package_policy.json'), true);
$denyPrefixes = is_array($package['deny_prefixes'] ?? null) ? $package['deny_prefixes'] : [];
$denySegments = is_array($package['deny_segments'] ?? null) ? $package['deny_segments'] : [];
$denyBasenames = is_array($package['deny_basenames'] ?? null) ? $package['deny_basenames'] : [];
$check(
    count(array_intersect(['backup/', 'uploads/', 'tmp/', 'output/'], $denyPrefixes)) === 4,
    'release package excludes all customer/runtime roots'
);
$check(in_array('/__pycache__/', $denySegments, true), 'release package excludes Python bytecode directories');
$check(in_array('.env', $denyBasenames, true), 'release package excludes credential environment files');

$databaseConfig = (string)file_get_contents($root . '/application/config/database.php');
$userIni = (string)file_get_contents($root . '/.user.ini');
$check(
    preg_match("/'(?:hostname|username|password|database)'\\s*=>\\s*'[^']+'/", $databaseConfig) !== 1,
    'source database config contains no direct connection values'
);
$check(
    str_contains($databaseConfig, "'/var/lib/finance-config/database.php'")
        && str_contains($databaseConfig, "array('development', 'staging')"),
    'private database fallback is restricted to staging-like environments'
);
$check(
    str_contains($databaseConfig, 'DeploymentConfig::DB_PASSWORD')
        && str_contains($databaseConfig, '$finance_environment !== \'production\''),
    'production database configuration remains resolver-bound and fail-closed'
);
$check(
    str_contains($userIni, '/var/lib/finance-config/'),
    'web open_basedir permits only the dedicated private configuration root'
);

$shallow = $git(['rev-parse', '--is-shallow-repository']);
$history = $shallow['code'] === 0 && trim($shallow['out']) === 'false' ? 'FULL' : 'SHALLOW_PENDING';

if ($failures !== []) {
    fwrite(STDERR, 'GAP-01 repository/runtime boundary failed=' . count($failures) . ' checks=' . $checks . PHP_EOL);
    exit(1);
}

echo 'GAP-01 REPOSITORY RUNTIME BOUNDARY PASS checks=' . $checks . ' history=' . $history . PHP_EOL;
