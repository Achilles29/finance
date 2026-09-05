<?php

declare(strict_types=1);

/**
 * Finance quality gate. Every manifest entry executes in a fresh process using
 * an extension-derived or explicitly configured runtime.
 */

function finance_quality_gate_runtime_dir(): string
{
    $configured = trim((string)getenv('A4_RUNTIME_DIR'));
    return $configured !== '' ? rtrim($configured, DIRECTORY_SEPARATOR) : '/var/lib/finance-a4-runtime';
}

function finance_quality_gate_manifest(): array
{
    return [
        'required' => [
            ['id' => 'a1-required', 'file' => 'a1_direct_url_guard_matrix_smoke.php', 'args' => ['--tier=required']],
            ['id' => 'a1-master-endpoint-registry', 'file' => 'master_endpoint_registry_smoke.php'],
            ['id' => 'a1-master-audit-trail', 'file' => 'master_audit_trail_smoke.php'],
            ['id' => 'a1-access-simulator', 'file' => 'access_simulator_smoke.php'],
            ['id' => 'a1-public-review', 'file' => 'public_customer_review_smoke.php'],
            ['id' => 'a1-review-admin-csrf', 'file' => 'customer_review_admin_csrf_smoke.php'],
            ['id' => 'a2-matrix', 'file' => 'a2_inventory_transaction_matrix_smoke.php'],
            ['id' => 'a3-finance-ui-shell', 'file' => 'a3_finance_ui_shell_smoke.php'],
            ['id' => 'a3-navigation-registry', 'file' => 'a3_navigation_registry_smoke.php'],
            ['id' => 'a3-page-alias-registry', 'file' => 'a3_page_alias_registry_smoke.php'],
            ['id' => 'a3-route-collision', 'file' => 'a3_route_collision_smoke.php'],
            ['id' => 'a3-sidebar-favorite-registry', 'file' => 'a3_sidebar_favorite_registry_smoke.php'],
            ['id' => 'a3-sidebar-renderer-single-source', 'file' => 'a3_sidebar_renderer_single_source_smoke.php'],
            ['id' => 'a4-cross-module', 'file' => 'a4_cross_module_contract_matrix_smoke.php'],
            ['id' => 'a5-migration-catalog-runner-contract', 'file' => 'a5_migration_catalog_runner_contract_smoke.php'],
            ['id' => 'a5-migration-executor-contract', 'file' => 'a5_migration_executor_contract_smoke.php'],
            ['id' => 'a5-legacy-sql-inventory-contract', 'file' => 'a5_legacy_sql_inventory_contract_smoke.php'],
            ['id' => 'a5-schema-fingerprint-contract', 'file' => 'a5_schema_fingerprint_contract_smoke.php'],
            ['id' => 'a5-auth-login-failure-fk-probe-contract', 'file' => 'a5_auth_login_failure_fk_probe_contract_smoke.php'],
            ['id' => 'a5-auth-login-failure-fk-detail-probe-contract', 'file' => 'a5_auth_login_failure_fk_detail_probe_contract_smoke.php'],
            ['id' => 'a5-auth-login-failure-named-fk-contract-probe-contract', 'file' => 'a5_auth_login_failure_named_fk_contract_probe_contract_smoke.php'],
            ['id' => 'a5-backup-bundle-restore-preflight-contract', 'file' => 'a5_backup_bundle_restore_preflight_contract_smoke.php'],
            ['id' => 'a5-disposable-restore-drill-contract', 'file' => 'a5_disposable_restore_drill_contract_smoke.php'],
            ['id' => 'a5-clean-install-baseline-guard', 'file' => 'a5_clean_install_baseline_guard_smoke.php'],
            ['id' => 'a5-first-owner-bootstrap-contract', 'file' => 'a5_first_owner_bootstrap_contract_smoke.php'],
            ['id' => 'a5-post-install-health-contract', 'file' => 'a5_post_install_health_check_contract_smoke.php'],
            ['id' => 'a5-upgrade-rollback-contract', 'file' => 'a5_upgrade_rollback_drill_contract_smoke.php'],
            ['id' => 'a5-runtime-compatibility-contract', 'file' => 'a5_runtime_compatibility_contract_smoke.php'],
            ['id' => 'a5-retention-lifecycle-contract', 'file' => 'a5_retention_lifecycle_contract_smoke.php'],
            ['id' => 'a5-artifact-signature-contract', 'file' => 'a5_artifact_signature_contract_smoke.php'],
            ['id' => 'gap07-legacy-upgrade-guard-contract', 'file' => 'gap07_legacy_upgrade_guard_contract_smoke.php'],
            ['id' => 'a4-dependency-vulnerability-source', 'file' => 'a4_dependency_vulnerability_smoke.php', 'args' => ['--source-only']],
            ['id' => 'a4-dependency-vulnerability-contract', 'file' => 'a4_dependency_vulnerability_contract_smoke.php'],
            ['id' => 'a4-static-analysis-source', 'file' => 'a4_static_analysis_smoke.php', 'args' => ['--source-only']],
            ['id' => 'a4-static-analysis-contract', 'file' => 'a4_static_analysis_contract_smoke.php'],
            ['id' => 'a4-release-preflight-contract', 'file' => 'a4_release_preflight_contract_smoke.php'],
            ['id' => 'a4-release-artifact-contract', 'file' => 'a4_release_artifact_contract_smoke.php'],
            ['id' => 'web-runtime-boundary', 'file' => 'web_runtime_boundary_smoke.php'],
            ['id' => 'release-runtime-contract', 'file' => 'release_runtime_contract_smoke.php'],
            ['id' => 'backup-source-isolation', 'file' => 'backup_source_isolation_smoke.php'],
            ['id' => 'gap01-repository-runtime-boundary', 'file' => 'gap01_repository_runtime_boundary_smoke.php'],
            ['id' => 'audit-roadmap-dashboard', 'file' => 'audit_roadmap_dashboard_smoke.php'],
            ['id' => 'roadmap-consistency', 'file' => 'roadmap_consistency_smoke.php'],
            ['id' => 'finance-quality-gate-contract', 'file' => 'finance_quality_gate_contract_smoke.php'],
            ['id' => 'pos-apk-web-backup-merge', 'file' => 'pos_apk_web_backup_merge_smoke.php'],
            ['id' => 'pos-mobile-device-binding-recovery', 'file' => 'pos_mobile_device_binding_recovery_smoke.php'],
            ['id' => 'pos-mobile-financial-writer-binding', 'file' => 'pos_mobile_financial_writer_binding_smoke.php'],
            ['id' => 'pos-mobile-order-action-reader-binding', 'file' => 'pos_mobile_order_action_reader_binding_smoke.php'],
            ['id' => 'pos-mobile-order-reader-binding', 'file' => 'pos_mobile_order_reader_binding_smoke.php'],
            ['id' => 'pos-mobile-print-document-binding', 'file' => 'pos_mobile_print_document_binding_smoke.php'],
            ['id' => 'pos-mobile-printer-binding', 'file' => 'pos_mobile_printer_binding_smoke.php'],
            ['id' => 'pos-mobile-role-scope-negative', 'file' => 'pos_mobile_role_scope_negative_smoke.php'],
            ['id' => 'telegram-module', 'file' => 'telegram_module_smoke.php'],
            ['id' => 'codex-telegram-summary', 'file' => 'codex_telegram_notify_summary_smoke.php'],
        ],
        'development' => [
            ['id' => 'a1-development', 'file' => 'a1_direct_url_guard_matrix_smoke.php', 'args' => ['--tier=development']],
            ['id' => 'pos-mobile-cashier-session-binding', 'file' => 'pos_mobile_cashier_session_binding_smoke.php'],
            ['id' => 'pos-mobile-draft-upsert-binding', 'file' => 'pos_mobile_draft_upsert_binding_smoke.php'],
            ['id' => 'pos-mobile-reader-outlet-binding', 'file' => 'pos_mobile_reader_outlet_binding_smoke.php'],
        ],
        'release' => [
            ['id' => 'deployment-secret-config', 'file' => 'deployment_secret_config_smoke.php'],
        ],
        'runtime' => [
            ['id' => 'a4-browser-runtime', 'file' => 'a4_browser_shell_runtime_smoke.php'],
            [
                'id' => 'a4-printer-agent-http-runtime',
                'file' => 'printer_agent_trust_smoke.py',
                'runtime' => finance_quality_gate_runtime_dir() . '/printer-venv/bin/python',
            ],
        ],
        'preflight' => [
            ['id' => 'a4-release-preflight', 'file' => 'a4_release_preflight_smoke.php'],
        ],
        'security' => [
            ['id' => 'a4-dependency-vulnerability-runtime', 'file' => 'a4_dependency_vulnerability_smoke.php'],
        ],
        'static' => [
            ['id' => 'a4-static-analysis-runtime', 'file' => 'a4_static_analysis_smoke.php'],
        ],
        'staging' => [
            ['id' => 'a2-database-invariant-probe', 'file' => 'a2_database_invariant_probe.php'],
            ['id' => 'a1-rbac-scope-staging', 'file' => '../db/rbac_scope_staging_probe.php'],
            [
                'id' => 'a5-runtime-compatibility-staging',
                'file' => '../release/runtime_compatibility_check.php',
                'args' => ['--staging'],
            ],
        ],
    ];
}

function finance_quality_gate_profile_exit_code(
    string $profile,
    bool $requiredPassed,
    bool $developmentPassed,
    bool $releasePassed,
    bool $stagingProbePassed = true,
    bool $runtimePassed = true,
    bool $preflightPassed = true,
    bool $securityPassed = true,
    bool $staticPassed = true
): int {
    if (!$requiredPassed) {
        return 1;
    }
    if ($profile !== 'parallel' && (!$developmentPassed || !$releasePassed || !$runtimePassed || !$preflightPassed || !$securityPassed || !$staticPassed)) {
        return 1;
    }
    if ($profile === 'staging' && !$stagingProbePassed) {
        return 1;
    }
    return 0;
}

function finance_quality_gate_tail(string $output, int $lineLimit = 12, int $byteLimit = 12000): string
{
    if (strlen($output) > $byteLimit) {
        $output = substr($output, -$byteLimit);
        $firstNewline = strpos($output, "\n");
        if ($firstNewline !== false) {
            $output = substr($output, $firstNewline + 1);
        }
    }
    $lines = preg_split('/\R/', trim($output)) ?: [];
    return implode(PHP_EOL, array_slice($lines, -$lineLimit));
}

function finance_quality_gate_resolve_command(array $test, string $path): array
{
    if (isset($test['runtime'], $test['interpreter'])) {
        return ['ok' => false, 'command' => [], 'error' => 'both runtime and interpreter are configured'];
    }

    $explicit = $test['runtime'] ?? $test['interpreter'] ?? null;
    if ($explicit !== null) {
        $runtime = is_array($explicit) ? array_values($explicit) : [$explicit];
    } else {
        $extension = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
        $runtimeByExtension = [
            'php' => [PHP_BINARY],
            'py' => ['/usr/bin/python3'],
            'sh' => ['/bin/bash'],
            'js' => ['/usr/bin/node'],
        ];
        if (!isset($runtimeByExtension[$extension])) {
            return ['ok' => false, 'command' => [], 'error' => 'no runtime for .' . $extension];
        }
        $runtime = $runtimeByExtension[$extension];
    }

    if ($runtime === [] || !is_string($runtime[0]) || $runtime[0] === '') {
        return ['ok' => false, 'command' => [], 'error' => 'runtime executable is empty'];
    }
    foreach ($runtime as $part) {
        if (!is_string($part) || $part === '') {
            return ['ok' => false, 'command' => [], 'error' => 'runtime command contains an invalid argument'];
        }
    }
    if (!is_file($runtime[0]) || !is_executable($runtime[0])) {
        return [
            'ok' => false,
            'command' => [],
            'error' => 'runtime executable missing or not executable: ' . $runtime[0],
        ];
    }

    $args = $test['args'] ?? [];
    if (!is_array($args)) {
        return ['ok' => false, 'command' => [], 'error' => 'test args must be an array'];
    }
    foreach ($args as $arg) {
        if (!is_string($arg)) {
            return ['ok' => false, 'command' => [], 'error' => 'test args must contain only strings'];
        }
    }

    return ['ok' => true, 'command' => array_merge($runtime, [$path], $args), 'error' => ''];
}

function finance_quality_gate_run_process(array $command, string $cwd, int $timeoutSeconds): array
{
    $escaped = array_map('escapeshellarg', $command);
    $descriptors = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open(implode(' ', $escaped), $descriptors, $pipes, $cwd);
    if (!is_resource($process)) {
        return ['exit_code' => 1, 'timed_out' => false, 'output' => 'Unable to start fresh quality-gate process.'];
    }

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $startedAt = microtime(true);
    $output = '';
    $exitCode = null;
    $timedOut = false;

    while (true) {
        foreach ([1, 2] as $pipeIndex) {
            $chunk = stream_get_contents($pipes[$pipeIndex]);
            if ($chunk !== false && $chunk !== '') {
                $output .= $chunk;
                if (strlen($output) > 24000) {
                    $output = substr($output, -24000);
                }
            }
        }

        $status = proc_get_status($process);
        if (!$status['running']) {
            $exitCode = (int)$status['exitcode'];
            break;
        }
        if ((microtime(true) - $startedAt) >= $timeoutSeconds) {
            $timedOut = true;
            proc_terminate($process);
            usleep(100000);
            $status = proc_get_status($process);
            if ($status['running']) {
                proc_terminate($process, 9);
            }
            $exitCode = 124;
            break;
        }
        usleep(20000);
    }

    foreach ([1, 2] as $pipeIndex) {
        $chunk = stream_get_contents($pipes[$pipeIndex]);
        if ($chunk !== false && $chunk !== '') {
            $output .= $chunk;
        }
        fclose($pipes[$pipeIndex]);
    }
    $closedCode = proc_close($process);
    if (!$timedOut && ($exitCode === null || $exitCode < 0) && $closedCode >= 0) {
        $exitCode = $closedCode;
    }

    return [
        'exit_code' => $exitCode ?? 1,
        'timed_out' => $timedOut,
        'output' => $output,
    ];
}

function finance_quality_gate_run_tier(
    string $label,
    array $tests,
    string $root,
    int $timeoutSeconds
): array {
    $failed = [];
    $total = count($tests);
    foreach ($tests as $index => $test) {
        $ordinal = str_pad((string)($index + 1), strlen((string)max(1, $total)), '0', STR_PAD_LEFT);
        echo '[' . $label . ' ' . $ordinal . '/' . $total . '] ' . $test['id'] . PHP_EOL;
        $path = __DIR__ . DIRECTORY_SEPARATOR . $test['file'];
        if (!is_file($path)) {
            echo 'FAIL ' . $test['id'] . ' (missing: ' . $test['file'] . ')' . PHP_EOL;
            $failed[] = $test['id'];
            continue;
        }
        $resolved = finance_quality_gate_resolve_command($test, $path);
        if (!$resolved['ok']) {
            echo 'FAIL ' . $test['id'] . ' (environment: ' . $resolved['error'] . ')' . PHP_EOL;
            $failed[] = $test['id'];
            continue;
        }
        $result = finance_quality_gate_run_process($resolved['command'], $root, $timeoutSeconds);
        if ($result['exit_code'] === 0) {
            echo 'PASS ' . $test['id'] . PHP_EOL;
            continue;
        }

        $suffix = $result['timed_out']
            ? 'timeout after ' . $timeoutSeconds . 's'
            : 'exit ' . $result['exit_code'];
        echo 'FAIL ' . $test['id'] . ' (' . $suffix . ')' . PHP_EOL;
        $tail = finance_quality_gate_tail((string)$result['output']);
        if ($tail !== '') {
            foreach (preg_split('/\R/', $tail) ?: [] as $line) {
                echo '  | ' . $line . PHP_EOL;
            }
        }
        $failed[] = $test['id'];
    }

    $passed = $total - count($failed);
    echo $label . ' ' . ($failed === [] ? 'PASS' : 'FAIL')
        . ' passed=' . $passed . ' failed=' . count($failed) . ' total=' . $total . PHP_EOL;
    return ['ok' => $failed === [], 'failed' => $failed, 'passed' => $passed, 'total' => $total];
}

if (defined('FINANCE_QUALITY_GATE_LIBRARY_ONLY') && FINANCE_QUALITY_GATE_LIBRARY_ONLY) {
    return;
}

$profile = 'parallel';
foreach (array_slice($argv ?? [], 1) as $argument) {
    if (strpos($argument, '--profile=') === 0) {
        $profile = substr($argument, strlen('--profile='));
        continue;
    }
    if (in_array($argument, ['parallel', 'release', 'staging'], true)) {
        $profile = $argument;
        continue;
    }
    fwrite(STDERR, 'Usage: php finance_quality_gate.php [parallel|release|staging|--profile=PROFILE]' . PHP_EOL);
    exit(2);
}
if (!in_array($profile, ['parallel', 'release', 'staging'], true)) {
    fwrite(STDERR, 'Invalid finance quality-gate profile: ' . $profile . PHP_EOL);
    exit(2);
}

$root = dirname(__DIR__, 2);
$manifest = finance_quality_gate_manifest();
$probeCount = $profile === 'staging' ? count($manifest['staging']) : 0;
$runtimeCount = $profile === 'parallel' ? 0 : count($manifest['runtime']);
$securityCount = $profile === 'parallel' ? 0 : count($manifest['security']);
$staticCount = $profile === 'parallel' ? 0 : count($manifest['static']);
echo 'FINANCE QUALITY GATE profile=' . $profile . PHP_EOL;
echo 'MANIFEST required=' . count($manifest['required'])
    . ' development=' . count($manifest['development'])
    . ' release=' . count($manifest['release'])
    . ' runtime=' . $runtimeCount
    . ' preflight=' . count($manifest['preflight'])
    . ' security=' . $securityCount
    . ' static=' . $staticCount
    . ' staging_probe=' . $probeCount
    . ' selected_tests=' . (count($manifest['required']) + count($manifest['development']) + count($manifest['release']) + $runtimeCount + count($manifest['preflight']) + $securityCount + $staticCount + $probeCount)
    . PHP_EOL;

$timeoutSeconds = 180;
$required = finance_quality_gate_run_tier('REQUIRED', $manifest['required'], $root, $timeoutSeconds);
$development = finance_quality_gate_run_tier('DEVELOPMENT', $manifest['development'], $root, $timeoutSeconds);
$release = finance_quality_gate_run_tier('RELEASE', $manifest['release'], $root, $timeoutSeconds);
if (!$release['ok']) {
    echo 'RELEASE BLOCKED: strict deployment contract failed ('
        . ($profile === 'parallel' ? 'nonblocking in parallel' : 'blocking in ' . $profile)
        . ')' . PHP_EOL;
}
$runtime = ['ok' => true, 'failed' => [], 'passed' => 0, 'total' => 0];
if ($profile !== 'parallel') {
    $runtime = finance_quality_gate_run_tier('A4 RUNTIME', $manifest['runtime'], $root, $timeoutSeconds);
    if (!$runtime['ok']) {
        echo 'A4 RUNTIME BLOCKED: browser and Printer Agent HTTP capability must pass before release.' . PHP_EOL;
    }
} else {
    echo 'A4 RUNTIME SKIPPED profile=parallel (blocking in release/staging)' . PHP_EOL;
}
$preflight = finance_quality_gate_run_tier('A4 PREFLIGHT', $manifest['preflight'], $root, $timeoutSeconds);
if (!$preflight['ok']) {
    echo 'A4 PREFLIGHT BLOCKED: release lint/dependency/secret/package policy failed ('
        . ($profile === 'parallel' ? 'nonblocking in parallel' : 'blocking in ' . $profile)
        . ')' . PHP_EOL;
}
$security = ['ok' => true, 'failed' => [], 'passed' => 0, 'total' => 0];
if ($profile !== 'parallel') {
    $security = finance_quality_gate_run_tier('A4 SECURITY', $manifest['security'], $root, $timeoutSeconds);
    if (!$security['ok']) {
        echo 'A4 SECURITY BLOCKED: fresh offline dependency vulnerability scan must pass before release.' . PHP_EOL;
    }
} else {
    echo 'A4 SECURITY SKIPPED profile=parallel (blocking in release/staging)' . PHP_EOL;
}
$static = ['ok' => true, 'failed' => [], 'passed' => 0, 'total' => 0];
if ($profile !== 'parallel') {
    $static = finance_quality_gate_run_tier('A4 STATIC', $manifest['static'], $root, $timeoutSeconds);
    if (!$static['ok']) {
        echo 'A4 STATIC BLOCKED: PHPStan semantic analysis must pass before release.' . PHP_EOL;
    }
} else {
    echo 'A4 STATIC SKIPPED profile=parallel (blocking in release/staging)' . PHP_EOL;
}
$stagingProbe = ['ok' => true, 'failed' => [], 'passed' => 0, 'total' => 0];
if ($profile === 'staging') {
    $stagingProbe = finance_quality_gate_run_tier('STAGING PROBE', $manifest['staging'], $root, $timeoutSeconds);
} else {
    echo 'STAGING PROBE SKIPPED profile=' . $profile . PHP_EOL;
}

echo 'MANUAL PENDING: browser role UAT' . PHP_EOL;
echo 'MANUAL PENDING: real APK/device/printer UAT' . PHP_EOL;

$exitCode = finance_quality_gate_profile_exit_code(
    $profile,
    $required['ok'],
    $development['ok'],
    $release['ok'],
    $stagingProbe['ok'],
    $runtime['ok'],
    $preflight['ok'],
    $security['ok'],
    $static['ok']
);
echo 'SUMMARY profile=' . $profile
    . ' required=' . ($required['ok'] ? 'PASS' : 'FAIL')
    . ' development=' . ($development['ok'] ? 'PASS' : 'FAIL')
    . ' release=' . ($release['ok'] ? 'PASS' : 'BLOCKED')
    . ' runtime=' . ($profile === 'parallel' ? 'SKIPPED' : ($runtime['ok'] ? 'PASS' : 'BLOCKED'))
    . ' preflight=' . ($preflight['ok'] ? 'PASS' : 'BLOCKED')
    . ' security=' . ($profile === 'parallel' ? 'SKIPPED' : ($security['ok'] ? 'PASS' : 'BLOCKED'))
    . ' static=' . ($profile === 'parallel' ? 'SKIPPED' : ($static['ok'] ? 'PASS' : 'BLOCKED'))
    . ' staging_probe=' . ($profile === 'staging' ? ($stagingProbe['ok'] ? 'PASS' : 'FAIL') : 'SKIPPED')
    . ' result=' . ($exitCode === 0 ? 'PASS' : 'FAIL')
    . PHP_EOL;
exit($exitCode);
