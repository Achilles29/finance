<?php
declare(strict_types=1);
require dirname(__DIR__) . '/build/CustomerBuild.php';
$root = dirname(__DIR__, 2); $checks = 0;
$check = static function (bool $ok, string $why) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL ' . $why);
    $checks++; echo 'PASS ' . $why . "\n";
};
$reject = static function (callable $fn, string $why) use ($check): void {
    try { $fn(); } catch (Throwable $e) { $check(true, $why); return; }
    $check(false, $why);
};
$app = ControlReleaseBridge::json((string)file_get_contents($root . '/app-manifest.json'));
$profile = CustomerReleaseProfile::fromRoot($root);
$check($app['packaging']['contract_version'] === 1 && count($app['packaging']['profiles']) === 1, 'one supported distribution profile');
$p = $app['packaging']['profiles'][0];
$check($p['code'] === 'CUSTOMER_CLEAN' && $p['default'] === true && $p['audience'] === 'CUSTOMER' && $p['sample_data'] === 'NONE', 'customer clean is default, not demo');
$check($p['rules_path'] === CustomerReleaseProfile::PATH && $p['rules_sha256'] === $profile->digest(), 'scanner hash matches immutable rules');
$check(is_file($root . '/' . $app['packaging']['adapter']) && !$profile->allows($app['packaging']['adapter']), 'build adapter exists only in development source, not customer runtime');
$r = ['schema' => 1, 'protocol' => CustomerBuild::PROTOCOL,
    'request_id' => '00000000-0000-4000-8000-000000000001', 'product_code' => 'NAMUA_FINANCE',
    'release_public_id' => '00000000-0000-4000-8000-000000000002', 'version' => $app['version'], 'channel' => 'ALPHA',
    'source_commit' => CustomerBuild::head($root), 'source_manifest_sha256' => hash_file('sha256', $root . '/app-manifest.json'),
    'profile_code' => $p['code'], 'profile_rules_sha256' => $profile->digest(), 'audience' => 'CUSTOMER', 'sample_data' => 'NONE'];
$check(CustomerBuild::canonical(array_reverse($r, true)) === json_encode($r, JSON_UNESCAPED_SLASHES), 'request hash uses Control field order, not pretty printed file bytes');
$wrong = $r; unset($wrong['channel']);
$reject(fn() => CustomerBuild::canonical($wrong), 'missing request field rejected');
$wrong = $r; $wrong['database'] = 'not-accepted';
$reject(fn() => CustomerBuild::canonical($wrong), 'request cannot provide a source database');
foreach (['version', 'source_commit', 'source_manifest_sha256', 'profile_rules_sha256', 'profile_code', 'audience', 'sample_data', 'channel'] as $field) {
    $wrong = $r; $wrong[$field] = 'invalid';
    $reject(fn() => CustomerBuild::validate($wrong, $root), 'reject mismatched ' . $field);
}
$db = (string)file_get_contents($root . '/tools/build/DisposableBuildDatabase.php');
$check(str_contains($db, "'--skip-networking'") && str_contains($db, "'--no-defaults'") && str_contains($db, "'--pdeathsig', 'TERM'"), 'temporary MariaDB has no network/default server config and dies with adapter');
$check(!str_contains($db, 'application/config') && !str_contains($db, 'DROP DATABASE') && !str_contains($db, 'TRUNCATE'), 'adapter does not load operational DB credentials or clear existing databases');
$check(str_contains($db, 'a5_apply(') && str_contains($db, 'a512_bootstrap_owner(') && str_contains($db, 'a513_check_database(')
    && str_contains($db, 'DISPOSABLE_RESTORE_MISMATCH'), 'real shared install and restore validations are required, not fixture PASS stubs');
$adapter = (string)file_get_contents($root . '/tools/build/CustomerBuild.php');
$check(str_contains($adapter, 'COMPOSER_HOME=') && str_contains($adapter, 'A4_STATIC_RUNTIME_DIR=')
    && !str_contains($adapter, "putenv('HOME="), 'build uses private tool caches without overriding system home');
$node = json_decode((string)file_get_contents($root . '/wa-engine/package.json'), true);
$nodeLock = json_decode((string)file_get_contents($root . '/wa-engine/package-lock.json'), true);
$check(($node['overrides']['sharp'] ?? '') === '0.35.4' && ($nodeLock['packages']['node_modules/sharp']['version'] ?? '') === '0.35.4',
    'release pins sharp security patch for GHSA-rgj7-g3m4-5g8c');
echo "All {$checks} Control build adapter contract checks passed (no database accessed).\n";
