<?php
$baseUrl = (string)($component_type_base_url ?? site_url('production/component-stock'));
$filters = is_array($component_type_filters ?? null) ? $component_type_filters : [];
$activeType = strtoupper(trim((string)($component_type_active ?? '')));
$tabs = [
    '' => 'Semua',
    'BASE' => 'Base',
    'PREPARE' => 'Prepare',
];

$buildUrl = static function (string $type) use ($baseUrl, $filters): string {
    $params = [];
    foreach ($filters as $key => $value) {
        if ($key === 'type') {
            continue;
        }
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            continue;
        }
        $params[$key] = $value;
    }
    if ($type !== '') {
        $params['type'] = $type;
    }
    return $baseUrl . (!empty($params) ? ('?' . http_build_query($params)) : '');
};
?>

<div class="d-flex flex-wrap gap-2 mb-3">
  <?php foreach ($tabs as $typeValue => $label): ?>
    <a href="<?php echo html_escape($buildUrl((string)$typeValue)); ?>" class="btn btn-sm <?php echo $activeType === (string)$typeValue ? 'btn-dark' : 'btn-outline-secondary'; ?>">
      <?php echo html_escape($label); ?>
    </a>
  <?php endforeach; ?>
</div>