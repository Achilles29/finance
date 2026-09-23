<?php
declare(strict_types=1);
define('BASEPATH', __DIR__);
$root = dirname(__DIR__, 2);
require $root . '/application/libraries/Module_notification.php';
function html_escape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function site_url($path): string { return 'https://fixture.invalid/' . $path; }
$context = new stdClass();
function &get_instance() { return $GLOBALS['context']; }
$context->load = new class {
    public function model(string $name): void { if ($name !== 'Module_notification_model') throw new RuntimeException('Unexpected model'); }
};
$context->Module_notification_model = new class {
    public bool $schema = true;
    public function ready(): bool { return $this->schema; }
    public function rules($channel): array {
        $rules = [];
        foreach (Module_notification::EVENTS as $event => $title) $rules[$event] = ['title'=>$title,'is_enabled'=>0,'targets'=>[]];
        return $rules;
    }
    public function available_targets($channel): array { return ['group:1'=>['label'=>'<img src=x onerror=alert(1)>','destination'=>'fixture']]; }
    public function recent($channel): array { return [['id'=>1,'created_at'=>'2026-09-23','event_code'=>'SELF_ORDER','source_id'=>1,'target_label'=>'<script>evil</script>','status'=>'FAILED','last_error'=>'<svg/onload=alert(1)>']]; }
};
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
};
$render = static function (string $file, array $vars) use ($root): string {
    extract($vars, EXTR_SKIP); ob_start(); require $root . '/application/views/notifications/' . $file . '.php'; return ob_get_clean();
};
$xpath = static function (string $html): DOMXPath {
    $doc = new DOMDocument(); libxml_use_internal_errors(true); $doc->loadHTML('<?xml encoding="UTF-8">' . $html); libxml_clear_errors(); return new DOMXPath($doc);
};
foreach (['WA','TELEGRAM'] as $channel) {
    $base = ['notification_channel'=>$channel, 'notification_csrf_name'=>'fixture_csrf', 'notification_csrf'=>'fixture-value', 'notification_action'=>'fixture/save'];
    foreach ([true,false] as $edit) {
        $html = $render('settings', $base + ['notification_can_edit'=>$edit]);
        $x = $xpath($html);
        $check($x->query('//input[@type="checkbox"]')->length === 3, $channel . ' three event switches');
        $check($x->query('//select[@multiple]')->length === 3, $channel . ' separate targets per event');
        $check($x->query('//input[@checked]')->length === 0, $channel . ' defaults OFF');
        $check($x->query('//fieldset[@disabled]')->length === ($edit ? 0 : 3), $channel . ' read-only settings respect RBAC');
        $check($x->query('//button[@type="submit"]')->length === ($edit ? 2 : 0), $channel . ' editors alone can save/retry');
        $check($x->query('//img|//script|//svg')->length === 0, $channel . ' recipients and errors escaped');
        $check(strpos($html, 'belum terdeteksi') !== false, $channel . ' absent worker clearly explained');
        $check(strpos($html, 'fixture-value') !== false, $channel . ' actual CSRF hidden value rendered');
    }
}
$context->Module_notification_model->schema = false;
$html = $render('settings', $base + ['notification_can_edit'=>true]);
$check(strpos($html,'2026-09-23a_module_notifications.sql') !== false && $xpath($html)->query('//form')->length === 0, 'missing migration explains recovery and offers no broken form');
foreach ([[], ['WA'], ['TELEGRAM'], ['WA','TELEGRAM']] as $channels) {
    $vars = ['notification_channels'=>$channels,'notification_csrf'=>'fixture-value','notification_status'=>'SUBMITTED','notification_request_id'=>12];
    $html = $render('division_buttons', $vars);
    $x = $xpath($html);
    $check($x->query('//button[@data-module-notify]')->length === count($channels), 'buttons follow enabled channels');
    foreach ($x->query('//button') as $button) {
        $check($button->getAttribute('type') === 'button' && $button->getAttribute('data-notify-csrf') === 'fixture-value', 'button cannot submit a surrounding form and carries CSRF');
        $check($button->getAttribute('data-notify-url') === 'https://fixture.invalid/procurement/division-po-sr/notify/12', 'actual endpoint includes request ID');
    }
    $vars['notification_status'] = 'VOID';
    $check($xpath($render('division_buttons', $vars) ?: '<div></div>')->query('//button')->length === 0, 'void request has no send action');
}
$process = proc_open(['/usr/bin/node', __DIR__.'/module_notifications_client_smoke.cjs'], [0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, $root);
if (!is_resource($process)) throw new RuntimeException('Could not run JS regression');
$output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
$check(proc_close($process) === 0, 'JS client regression: '.$error);
echo "Module notification UI: {$checks} HTML/DOM checks PASS. " . $output;
