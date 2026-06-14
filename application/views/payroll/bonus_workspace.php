<?php
$month = $month ?? date('Y-m');
$tab = $tab ?? 'overview';
$summary = $summary ?? [];
$configRows = $config_rows ?? [];
$outletRows = $outlet_rows ?? [];
$divisionRows = $division_rows ?? [];
$employeeRows = $employee_rows ?? [];
$shiftRows = $shift_rows ?? [];
$bonusRuleOptionRows = $bonus_rule_option_rows ?? [];
$targetPlanRows = $target_plan_rows ?? [];
$ruleRows = $rule_rows ?? [];
$penaltyTypeRows = $penalty_type_rows ?? [];
$penaltyEventRows = $penalty_event_rows ?? [];
$poolRows = $pool_rows ?? [];
$pendingPeerRows = $pending_peer_rows ?? [];
$serviceMetricRows = $service_metric_rows ?? [];
$monthlySummaryRows = $monthly_summary_rows ?? [];
$poolFilters = $pool_filters ?? ['q' => ''];
$ruleFilters = $rule_filters ?? ['q' => ''];
$penaltyTypeFilters = $penalty_type_filters ?? ['q' => ''];
$penaltyEventFilters = $penalty_event_filters ?? ['q' => ''];
$peerFilters = $peer_filters ?? ['q' => ''];
$serviceFilters = $service_filters ?? ['q' => ''];
$monthlyFilters = $monthly_filters ?? ['q' => ''];
$poolPg = $pool_pg ?? ['total' => count($poolRows), 'per_page' => 25, 'page' => 1, 'total_pages' => 1, 'offset' => 0];
$rulePg = $rule_pg ?? ['total' => count($ruleRows), 'per_page' => 25, 'page' => 1, 'total_pages' => 1, 'offset' => 0];
$penaltyTypePg = $penalty_type_pg ?? ['total' => count($penaltyTypeRows), 'per_page' => 25, 'page' => 1, 'total_pages' => 1, 'offset' => 0];
$penaltyEventPg = $penalty_event_pg ?? ['total' => count($penaltyEventRows), 'per_page' => 25, 'page' => 1, 'total_pages' => 1, 'offset' => 0];
$peerPg = $peer_pg ?? ['total' => count($pendingPeerRows), 'per_page' => 25, 'page' => 1, 'total_pages' => 1, 'offset' => 0];
$servicePg = $service_pg ?? ['total' => count($serviceMetricRows), 'per_page' => 25, 'page' => 1, 'total_pages' => 1, 'offset' => 0];
$monthlyPg = $monthly_pg ?? ['total' => count($monthlySummaryRows), 'per_page' => 25, 'page' => 1, 'total_pages' => 1, 'offset' => 0];
$penaltyCategoryLabels = [
    'ATTENDANCE' => 'Absensi',
    'DISCIPLINE' => 'Disiplin',
    'PERFORMANCE' => 'Kinerja',
    'SERVICE' => 'Layanan',
    'PROPERTY' => 'Aset / Kerusakan',
    'SOCIAL_MEDIA' => 'Sosial Media',
    'HYGIENE' => 'Kebersihan',
    'OTHER' => 'Lainnya',
    'PERSONAL' => 'Personal',
    'TEAM' => 'Tim',
];
$penaltyScopeLabels = [
    'PERSONAL' => 'Personal',
    'TEAM' => 'Tim',
    'BOTH' => 'Personal & Tim',
];
$penaltyBehaviorLabels = [
    'AUTO' => 'Otomatis',
    'MANUAL' => 'Manual',
    'SEMI_MANUAL' => 'Semi Manual',
];
$penaltyDeductionLabels = [
    'FIXED_POINT' => 'Potong poin tetap',
    'FIXED_AMOUNT' => 'Potong nominal tetap',
    'VARIABLE' => 'Variabel / kasus per kasus',
];
$verificationCycleLabels = [
    'PER_EVENT' => 'Per kejadian',
    'DAILY' => 'Harian',
    'MONTHLY' => 'Bulanan',
    'UNTIL_CHANGED' => 'Berlaku sampai ada perubahan',
];
$autoSourceLabels = [
    '' => 'Tidak otomatis',
    'ATTENDANCE' => 'Absensi',
    'SERVICE' => 'Layanan',
    'TARGET' => 'Target',
    'PEER' => 'Penilaian 360',
    'SOCIAL_MEDIA' => 'Verifikasi sosial media',
    'AUDIT' => 'Audit',
    'CHECKLIST' => 'Checklist',
    'OTHER' => 'Lainnya',
];
$ruleTargetGateLabels = [
    'WEIGHTED_SCORE' => 'Bertingkat',
    'ALL_REQUIRED' => 'Semua target wajib lolos',
    'NONE' => 'Tanpa gerbang target',
];
$phBonusModeLabels = [
    'EXCLUDE' => 'Tidak ikut bonus',
    'REDUCE' => 'Ikut tapi dipotong',
    'ALLOW' => 'Tetap ikut penuh',
];
$hasPenaltyBehaviorMode = false;
foreach ($penaltyTypeRows as $penaltyTypeRow) {
    if (array_key_exists('behavior_mode', $penaltyTypeRow)) {
        $hasPenaltyBehaviorMode = true;
        break;
    }
}
$isPenaltySelectableForEvent = static function (array $row) use ($hasPenaltyBehaviorMode): bool {
    if (!$hasPenaltyBehaviorMode) {
        return true;
    }
    return strtoupper((string)($row['behavior_mode'] ?? 'MANUAL')) !== 'AUTO';
};
$buildUrl = static function (array $overrides = []) use ($month, $tab) {
    return site_url('payroll/bonus') . '?' . http_build_query(array_merge([
        'month' => $month,
        'tab' => $tab,
    ], $overrides));
};
$buildTableUrl = static function (array $overrides = []) use ($month, $tab, $poolFilters, $ruleFilters, $penaltyTypeFilters, $penaltyEventFilters, $peerFilters, $serviceFilters, $monthlyFilters, $poolPg, $rulePg, $penaltyTypePg, $penaltyEventPg, $peerPg, $servicePg, $monthlyPg) {
    $base = [
        'month' => $month,
        'tab' => $tab,
        'pool_q' => $poolFilters['q'] ?? '',
        'pool_page' => $poolPg['page'] ?? 1,
        'pool_per_page' => $poolPg['per_page'] ?? 25,
        'rule_q' => $ruleFilters['q'] ?? '',
        'rule_page' => $rulePg['page'] ?? 1,
        'rule_per_page' => $rulePg['per_page'] ?? 25,
        'penalty_type_q' => $penaltyTypeFilters['q'] ?? '',
        'penalty_type_page' => $penaltyTypePg['page'] ?? 1,
        'penalty_type_per_page' => $penaltyTypePg['per_page'] ?? 25,
        'penalty_event_q' => $penaltyEventFilters['q'] ?? '',
        'penalty_event_page' => $penaltyEventPg['page'] ?? 1,
        'penalty_event_per_page' => $penaltyEventPg['per_page'] ?? 25,
        'peer_q' => $peerFilters['q'] ?? '',
        'peer_page' => $peerPg['page'] ?? 1,
        'peer_per_page' => $peerPg['per_page'] ?? 25,
        'service_q' => $serviceFilters['q'] ?? '',
        'service_page' => $servicePg['page'] ?? 1,
        'service_per_page' => $servicePg['per_page'] ?? 25,
        'monthly_q' => $monthlyFilters['q'] ?? '',
        'monthly_page' => $monthlyPg['page'] ?? 1,
        'monthly_per_page' => $monthlyPg['per_page'] ?? 25,
    ];
    return site_url('payroll/bonus') . '?' . http_build_query(array_merge($base, $overrides));
};
$defaultBonusDate = date('Y-m-d', strtotime($month . '-01'));
$renderPager = static function (array $pg, callable $urlBuilder, string $pageParam, string $perPageParam) {
    if (($pg['total'] ?? 0) <= 0) {
        return;
    }
    ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
      <div class="small text-muted">
        Menampilkan
        <strong><?php echo number_format((int)($pg['offset'] ?? 0) + 1); ?></strong>
        -
        <strong><?php echo number_format(min((int)($pg['offset'] ?? 0) + (int)($pg['per_page'] ?? 25), (int)($pg['total'] ?? 0))); ?></strong>
        dari
        <strong><?php echo number_format((int)($pg['total'] ?? 0)); ?></strong>
        baris
      </div>
      <div class="d-flex flex-wrap align-items-center gap-2">
        <form method="get" action="<?php echo site_url('payroll/bonus'); ?>" class="d-flex align-items-center gap-2 mb-0">
          <?php parse_str(parse_url($urlBuilder([]), PHP_URL_QUERY) ?? '', $currentQuery); ?>
          <?php foreach ($currentQuery as $queryKey => $queryValue): ?>
            <?php if ($queryKey === $perPageParam) { continue; } ?>
            <input type="hidden" name="<?php echo html_escape($queryKey); ?>" value="<?php echo html_escape((string)$queryValue); ?>">
          <?php endforeach; ?>
          <label class="small text-muted mb-0">Baris</label>
          <select name="<?php echo html_escape($perPageParam); ?>" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php foreach ([10, 25, 50, 100] as $size): ?>
              <option value="<?php echo $size; ?>" <?php echo (int)($pg['per_page'] ?? 25) === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <div class="btn-group btn-group-sm" role="group">
          <a class="btn btn-outline-secondary <?php echo (int)($pg['page'] ?? 1) <= 1 ? 'disabled' : ''; ?>" href="<?php echo (int)($pg['page'] ?? 1) <= 1 ? '#' : $urlBuilder([$pageParam => (int)$pg['page'] - 1]); ?>">Sebelumnya</a>
          <button type="button" class="btn btn-outline-secondary disabled">Hal. <?php echo number_format((int)($pg['page'] ?? 1)); ?> / <?php echo number_format((int)($pg['total_pages'] ?? 1)); ?></button>
          <a class="btn btn-outline-secondary <?php echo (int)($pg['page'] ?? 1) >= (int)($pg['total_pages'] ?? 1) ? 'disabled' : ''; ?>" href="<?php echo (int)($pg['page'] ?? 1) >= (int)($pg['total_pages'] ?? 1) ? '#' : $urlBuilder([$pageParam => (int)$pg['page'] + 1]); ?>">Berikutnya</a>
        </div>
      </div>
    </div>
    <?php
};
?>

<style>
  .bonus-hero { background: radial-gradient(circle at top left, rgba(177,18,38,.14), transparent 42%), linear-gradient(135deg, #fff8f5 0%, #ffffff 62%, #fff3ef 100%); border: 1px solid rgba(122,24,36,.08); border-radius: 28px; box-shadow: 0 20px 48px rgba(122,24,36,.08); }
  .bonus-pill-nav .nav-link { border-radius: 999px; border: 1px solid rgba(122,24,36,.14); color: #7a1824; font-weight: 700; padding: .62rem 1rem; }
  .bonus-pill-nav .nav-link.active { background: linear-gradient(135deg, #b11226, #7a1824); color: #fff; border-color: transparent; box-shadow: 0 12px 28px rgba(122,24,36,.18); }
  .bonus-card { border: 1px solid rgba(122,24,36,.08); border-radius: 22px; box-shadow: 0 16px 34px rgba(89,57,41,.06); }
  .bonus-kpi-grid { display:grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap:1rem; }
  .bonus-kpi { padding:1rem 1.1rem; border-radius:20px; border:1px solid rgba(122,24,36,.08); background:linear-gradient(180deg,#fff,#fff7f4); }
  .bonus-kpi .label { font-size:.78rem; text-transform:uppercase; color:#8d7368; letter-spacing:.04em; }
  .bonus-kpi .value { font-size:1.25rem; font-weight:800; color:#5f1720; }
  .bonus-kpi .sub { font-size:.85rem; color:#7e6a60; }
  .bonus-table thead th { background: linear-gradient(135deg, #b11226, #8f1021); color:#fff; border:0; text-transform:uppercase; letter-spacing:.04em; font-size:.79rem; }
  .bonus-table-wrap { max-height: 560px; overflow: auto; border-radius: 18px; border: 1px solid rgba(122,24,36,.08); }
  .bonus-table-wrap table { margin-bottom: 0; }
  .bonus-table-wrap thead th { position: sticky; top: 0; z-index: 2; }
  .bonus-filter-bar { border: 1px solid rgba(122,24,36,.08); border-radius: 18px; background: linear-gradient(180deg, #fff, #fff8f5); padding: 1rem; }
  .bonus-soft-badge { display:inline-flex; align-items:center; padding:.34rem .68rem; border-radius:999px; font-size:.75rem; font-weight:700; }
  .bonus-soft-badge.ok { background:#e8f8ef; color:#157347; }
  .bonus-soft-badge.warn { background:#fff4df; color:#b26b00; }
  .bonus-soft-badge.info { background:#eef3ff; color:#335ec9; }
  .bonus-soft-badge.muted { background:#f3f4f6; color:#6b7280; }
  .bonus-guide-list li { margin-bottom:.55rem; }
  @media (max-width: 991.98px) { .bonus-kpi-grid { grid-template-columns: repeat(2, minmax(0,1fr)); } }
  @media (max-width: 575.98px) { .bonus-kpi-grid { grid-template-columns: 1fr; } }
</style>

<div class="bonus-hero p-4 p-lg-5 mb-4">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div style="max-width:760px;">
      <div class="text-uppercase small fw-semibold text-muted mb-2">Payroll Workspace</div>
      <h3 class="mb-2">Bonus Pegawai yang nyambung ke target, layanan, absensi, dan budaya tim.</h3>
      <p class="mb-0 text-muted">Halaman ini sengaja dibuat bukan hanya untuk bagi hasil. Di sini kita baca apakah target usaha tercapai, shift mana yang paling kuat, siapa yang layak dapat bobot lebih tinggi, penalti apa yang masuk, dan apakah ada sinyal perilaku tim dari penilaian 360.</p>
    </div>
    <form method="get" action="<?php echo site_url('payroll/bonus'); ?>" class="d-flex gap-2 align-items-end flex-wrap">
      <input type="hidden" name="tab" value="<?php echo html_escape($tab); ?>">
      <div>
        <label class="form-label small text-muted mb-1">Bulan kerja</label>
        <input type="month" name="month" class="form-control" value="<?php echo html_escape($month); ?>">
      </div>
      <div>
        <button type="submit" class="btn btn-primary">Baca Data</button>
      </div>
    </form>
  </div>
</div>

<ul class="nav nav-pills bonus-pill-nav gap-2 mb-4">
  <li class="nav-item"><a class="nav-link <?php echo $tab === 'overview' ? 'active' : ''; ?>" href="<?php echo $buildUrl(['tab' => 'overview']); ?>">Ringkasan</a></li>
  <li class="nav-item"><a class="nav-link <?php echo $tab === 'rules' ? 'active' : ''; ?>" href="<?php echo $buildUrl(['tab' => 'rules']); ?>">Aturan Bonus</a></li>
  <li class="nav-item"><a class="nav-link <?php echo $tab === 'penalties' ? 'active' : ''; ?>" href="<?php echo $buildUrl(['tab' => 'penalties']); ?>">Penalti</a></li>
  <li class="nav-item"><a class="nav-link <?php echo $tab === 'peer' ? 'active' : ''; ?>" href="<?php echo $buildUrl(['tab' => 'peer']); ?>">Penilaian 360</a></li>
  <li class="nav-item"><a class="nav-link <?php echo $tab === 'service' ? 'active' : ''; ?>" href="<?php echo $buildUrl(['tab' => 'service']); ?>">Metric Layanan</a></li>
  <li class="nav-item"><a class="nav-link <?php echo $tab === 'monthly' ? 'active' : ''; ?>" href="<?php echo $buildUrl(['tab' => 'monthly']); ?>">Rekap Bulanan</a></li>
  <li class="nav-item"><a class="nav-link <?php echo $tab === 'guide' ? 'active' : ''; ?>" href="<?php echo $buildUrl(['tab' => 'guide']); ?>">Panduan</a></li>
</ul>

<?php if ($tab === 'overview'): ?>
  <div class="bonus-kpi-grid mb-4">
    <div class="bonus-kpi"><div class="label">Konfigurasi Bonus</div><div class="value"><?php echo number_format((int)($summary['config_count'] ?? 0)); ?></div><div class="sub">Rumah aturan bonus yang sudah terdaftar</div></div>
    <div class="bonus-kpi"><div class="label">Rule Aktif</div><div class="value"><?php echo number_format((int)($summary['active_rule_count'] ?? 0)); ?></div><div class="sub"><?php echo number_format((int)($summary['target_linked_rule_count'] ?? 0)); ?> rule sudah ditautkan ke target</div></div>
    <div class="bonus-kpi"><div class="label">Pool Harian</div><div class="value"><?php echo number_format((int)($summary['pool_count'] ?? 0)); ?></div><div class="sub"><?php echo number_format((int)($summary['approved_pool_count'] ?? 0)); ?> pool sudah disetujui</div></div>
    <div class="bonus-kpi"><div class="label">Rekap Bulanan</div><div class="value">Rp <?php echo number_format((float)($summary['monthly_final_amount'] ?? 0), 2, ',', '.'); ?></div><div class="sub"><?php echo number_format((int)($summary['monthly_summary_count'] ?? 0)); ?> pegawai sudah punya ringkasan</div></div>
  </div>

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card bonus-card">
        <div class="card-header bg-white border-0 pb-0">
          <h5 class="mb-1">Pool bonus harian terbaru</h5>
          <div class="small text-muted">Ini adalah ringkasan hari bonus yang nantinya akan dibagi ke pegawai berdasarkan bobot, shift, target, dan penalti.</div>
        </div>
        <div class="card-body">
          <form method="get" action="<?php echo site_url('payroll/bonus'); ?>" class="bonus-filter-bar mb-3">
            <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
            <input type="hidden" name="tab" value="overview">
            <input type="hidden" name="rule_q" value="<?php echo html_escape((string)($ruleFilters['q'] ?? '')); ?>">
            <input type="hidden" name="rule_page" value="<?php echo (int)($rulePg['page'] ?? 1); ?>">
            <input type="hidden" name="rule_per_page" value="<?php echo (int)($rulePg['per_page'] ?? 25); ?>">
            <input type="hidden" name="penalty_type_q" value="<?php echo html_escape((string)($penaltyTypeFilters['q'] ?? '')); ?>">
            <input type="hidden" name="penalty_type_page" value="<?php echo (int)($penaltyTypePg['page'] ?? 1); ?>">
            <input type="hidden" name="penalty_type_per_page" value="<?php echo (int)($penaltyTypePg['per_page'] ?? 25); ?>">
            <input type="hidden" name="penalty_event_q" value="<?php echo html_escape((string)($penaltyEventFilters['q'] ?? '')); ?>">
            <input type="hidden" name="penalty_event_page" value="<?php echo (int)($penaltyEventPg['page'] ?? 1); ?>">
            <input type="hidden" name="penalty_event_per_page" value="<?php echo (int)($penaltyEventPg['per_page'] ?? 25); ?>">
            <input type="hidden" name="peer_q" value="<?php echo html_escape((string)($peerFilters['q'] ?? '')); ?>">
            <input type="hidden" name="peer_page" value="<?php echo (int)($peerPg['page'] ?? 1); ?>">
            <input type="hidden" name="peer_per_page" value="<?php echo (int)($peerPg['per_page'] ?? 25); ?>">
            <div class="row g-3 align-items-end">
              <div class="col-md-7">
                <label class="form-label">Cari pool bonus</label>
                <input type="text" name="pool_q" class="form-control" value="<?php echo html_escape((string)($poolFilters['q'] ?? '')); ?>" placeholder="Cari tanggal, nama aturan, outlet, atau status">
              </div>
              <div class="col-md-3">
                <label class="form-label">Baris</label>
                <select name="pool_per_page" class="form-select">
                  <?php foreach ([10, 25, 50, 100] as $size): ?>
                    <option value="<?php echo $size; ?>" <?php echo (int)($poolPg['per_page'] ?? 25) === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-outline-primary">Filter</button>
              </div>
            </div>
          </form>
          <div class="bonus-table-wrap bonus-table">
            <table class="table align-middle mb-0">
              <thead><tr><th>Tanggal</th><th>Aturan</th><th>Target</th><th class="text-end">Penjualan Bersih</th><th class="text-end">Pool</th><th>Status</th><th>Aksi</th></tr></thead>
              <tbody>
              <?php if (empty($poolRows)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada pool bonus pada bulan ini.</td></tr>
              <?php else: foreach ($poolRows as $row): ?>
                <tr>
                  <td><?php echo html_escape((string)($row['bonus_date'] ?? '-')); ?></td>
                  <td><strong><?php echo html_escape((string)($row['rule_name'] ?? '-')); ?></strong><div class="small text-muted"><?php echo html_escape((string)($row['outlet_name'] ?? $row['division_name'] ?? $row['config_name'] ?? '-')); ?></div></td>
                  <td>
                    <?php
                      $targetPlanName = trim((string)($row['target_plan_name'] ?? ''));
                      $targetScorePercent = (float)($row['target_score_percent'] ?? 100);
                      $targetGatePassed = (int)($row['target_gate_passed'] ?? 1) === 1;
                    ?>
                    <?php if ($targetPlanName !== ''): ?>
                      <strong><?php echo html_escape($targetPlanName); ?></strong>
                      <div class="small text-muted">
                        Skor <?php echo number_format($targetScorePercent, 2, ',', '.'); ?>%
                        <?php if ($targetGatePassed): ?>
                          <span class="bonus-soft-badge ok ms-1">Lolos</span>
                        <?php else: ?>
                          <span class="bonus-soft-badge warn ms-1">Tertahan</span>
                        <?php endif; ?>
                      </div>
                    <?php else: ?>
                      <span class="small text-muted">Tidak pakai target khusus</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">Rp <?php echo number_format((float)($row['net_sales_amount'] ?? 0), 2, ',', '.'); ?></td>
                  <td class="text-end fw-semibold">Rp <?php echo number_format((float)($row['payout_amount'] ?? $row['pool_amount'] ?? 0), 2, ',', '.'); ?></td>
                  <td>
                    <?php $status = strtoupper((string)($row['approval_status'] ?? 'DRAFT')); ?>
                    <span class="bonus-soft-badge <?php echo $status === 'APPROVED' ? 'ok' : ($status === 'VOID' ? 'muted' : 'warn'); ?>"><?php echo html_escape($status); ?></span>
                  </td>
                  <td>
                    <?php if ($status === 'DRAFT'): ?>
                      <form method="post" action="<?php echo site_url('payroll/bonus/approve-pool/' . (int)($row['id'] ?? 0)); ?>">
                        <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
                        <button type="submit" class="btn btn-sm btn-outline-success">Publikasikan</button>
                      </form>
                    <?php else: ?>
                      <span class="small text-muted">Sudah diumumkan</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
          <?php $renderPager($poolPg, static function ($overrides) use ($buildTableUrl) { return $buildTableUrl(array_merge(['tab' => 'overview'], $overrides)); }, 'pool_page', 'pool_per_page'); ?>
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card bonus-card h-100">
        <div class="card-header bg-white border-0 pb-0">
          <h5 class="mb-1">Sinyal yang butuh perhatian</h5>
          <div class="small text-muted">Ringkasan cepat supaya admin tahu mana yang harus dituntaskan dulu.</div>
        </div>
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center py-2 border-bottom"><span>Tipe penalti aktif</span><strong><?php echo number_format((int)($summary['penalty_type_count'] ?? 0)); ?></strong></div>
          <div class="d-flex justify-content-between align-items-center py-2 border-bottom"><span>Kejadian penalti bulan ini</span><strong><?php echo number_format((int)($summary['penalty_event_count'] ?? 0)); ?></strong></div>
          <div class="d-flex justify-content-between align-items-center py-2 border-bottom"><span>Peer review menunggu moderasi</span><strong><?php echo number_format((int)($summary['pending_peer_review_count'] ?? 0)); ?></strong></div>
          <div class="d-flex justify-content-between align-items-center py-2"><span>Peer review yang sudah lolos</span><strong><?php echo number_format((int)($summary['approved_peer_review_count'] ?? 0)); ?></strong></div>
          <hr>
          <div class="small text-muted">Tahap berikutnya yang paling penting:</div>
          <ol class="small mb-0 mt-2 ps-3">
            <li>Isi konfigurasi bonus global lebih dulu.</li>
            <li>Hubungkan rule bonus ke target keuangan yang relevan.</li>
            <li>Finalkan master penalti personal dan tim.</li>
            <li>Aktifkan form penilaian 360 lewat portal pegawai.</li>
          </ol>
          <hr>
          <div class="small text-muted mb-2">Aksi operasional harian</div>
          <form method="post" action="<?php echo site_url('payroll/bonus/sync-auto-penalties'); ?>" class="border rounded-4 p-3 mb-3 bg-light-subtle">
            <div class="small fw-semibold mb-2">1. Sinkron penalti otomatis dari absensi</div>
            <div class="row g-2 align-items-end">
              <div class="col-sm-7">
                <label class="form-label small">Tanggal bonus</label>
                <input type="date" name="bonus_date" class="form-control" value="<?php echo html_escape($defaultBonusDate); ?>">
              </div>
              <div class="col-sm-5 d-grid">
                <button type="submit" class="btn btn-outline-primary">Sync Penalti Auto</button>
              </div>
            </div>
            <div class="small text-muted mt-2">Ini menarik telat, pulang cepat, alpha, tidak hadir shift PH, dan ambil PH yang sudah dikaitkan ke master penalti.</div>
          </form>
          <form method="post" action="<?php echo site_url('payroll/bonus/generate-pool'); ?>" class="border rounded-4 p-3 bg-light-subtle">
            <div class="small fw-semibold mb-2">2. Generate draft pool bonus harian</div>
            <div class="row g-2 align-items-end">
              <div class="col-sm-5">
                <label class="form-label small">Tanggal bonus</label>
                <input type="date" name="bonus_date" class="form-control" value="<?php echo html_escape($defaultBonusDate); ?>">
              </div>
              <div class="col-sm-4">
                <label class="form-label small">Aturan bonus</label>
                <select name="rule_id" class="form-select" required>
                  <option value="">Pilih aturan...</option>
                  <?php foreach ($bonusRuleOptionRows as $row): ?>
                    <option value="<?php echo (int)($row['id'] ?? 0); ?>"><?php echo html_escape((string)($row['rule_name'] ?? '-')); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-sm-3 d-grid">
                <button type="submit" class="btn btn-primary">Generate Draft</button>
              </div>
            </div>
            <div class="small text-muted mt-2">Draft pool akan menghitung target, omzet shift, bobot hadir, peer review, penalti, dan metric waktu saji yang sudah tersedia.</div>
          </form>
          <form method="post" action="<?php echo site_url('payroll/bonus/generate-service-metric'); ?>" class="border rounded-4 p-3 mt-3 bg-light-subtle">
            <div class="small fw-semibold mb-2">3. Bangun metric waktu saji harian</div>
            <div class="row g-2 align-items-end">
              <div class="col-sm-5">
                <label class="form-label small">Tanggal metric</label>
                <input type="date" name="metric_date" class="form-control" value="<?php echo html_escape($defaultBonusDate); ?>">
              </div>
              <div class="col-sm-4">
                <label class="form-label small">Outlet</label>
                <select name="outlet_id" class="form-select">
                  <option value="0">Semua outlet</option>
                  <?php foreach ($outletRows as $row): ?>
                    <option value="<?php echo (int)($row['id'] ?? 0); ?>"><?php echo html_escape((string)($row['outlet_name'] ?? '-')); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-sm-3 d-grid">
                <button type="submit" class="btn btn-outline-dark">Bangun Metric</button>
              </div>
            </div>
            <div class="small text-muted mt-2">Sumbernya dari `ordered_at -> served_at` order POS, lalu diringkas per shift dan outlet sebagai dasar faktor layanan bonus.</div>
          </form>
          <form method="post" action="<?php echo site_url('payroll/bonus/generate-monthly-summary'); ?>" class="border rounded-4 p-3 mt-3 bg-light-subtle">
            <div class="small fw-semibold mb-2">4. Refresh rekap bonus bulanan</div>
            <div class="row g-2 align-items-end">
              <div class="col-sm-8">
                <label class="form-label small">Bulan rekap</label>
                <input type="month" name="summary_month" class="form-control" value="<?php echo html_escape($month); ?>">
              </div>
              <div class="col-sm-4 d-grid">
                <button type="submit" class="btn btn-outline-success">Refresh Rekap</button>
              </div>
            </div>
            <div class="small text-muted mt-2">Rekap bulanan dibangun dari bonus harian yang sudah dipublikasikan, termasuk hitung keterlambatan, alpha, PH, peer review, dan penyesuaian manual.</div>
          </form>
        </div>
      </div>
    </div>
  </div>
<?php elseif ($tab === 'rules'): ?>
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card bonus-card">
        <div class="card-header bg-white border-0 pb-0">
          <h5 class="mb-1">Form aturan bonus</h5>
          <div class="small text-muted">Gunakan bahasa operasional: target minimum, cara baca PH, dan bobot layanan per rule.</div>
        </div>
        <div class="card-body">
          <form method="post" action="<?php echo site_url('payroll/bonus/rule-save'); ?>" id="bonusRuleForm">
            <input type="hidden" name="id" value="">
            <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
            <div class="mb-3">
              <label class="form-label">Paket bonus</label>
              <select name="config_id" class="form-select" required>
                <option value="">Pilih paket bonus...</option>
                <?php foreach ($configRows as $row): ?>
                  <option value="<?php echo (int)($row['id'] ?? 0); ?>"><?php echo html_escape((string)($row['config_name'] ?? '-')); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Nama aturan</label>
              <input type="text" name="rule_name" class="form-control" placeholder="Contoh: Bonus Bar Shift Malam">
            </div>
            <div class="mb-3">
              <label class="form-label">Kode aturan</label>
              <input type="text" name="rule_code" class="form-control" placeholder="Kosongkan agar dibuat otomatis">
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Outlet</label>
                <select name="outlet_id" class="form-select">
                  <option value="">Semua outlet</option>
                  <?php foreach ($outletRows as $row): ?>
                    <option value="<?php echo (int)($row['id'] ?? 0); ?>"><?php echo html_escape((string)($row['outlet_name'] ?? '-')); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Divisi</label>
                <select name="division_id" class="form-select">
                  <option value="">Semua divisi</option>
                  <?php foreach ($divisionRows as $row): ?>
                    <option value="<?php echo (int)($row['value'] ?? 0); ?>"><?php echo html_escape((string)($row['label'] ?? '-')); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="mt-3">
              <label class="form-label">Target keuangan yang dihubungkan</label>
              <select name="linked_target_plan_id" class="form-select">
                <option value="">Belum ditautkan</option>
                <?php foreach ($targetPlanRows as $row): ?>
                  <option value="<?php echo (int)($row['id'] ?? 0); ?>"><?php echo html_escape((string)($row['target_name'] ?? $row['plan_name'] ?? '-')); ?><?php echo !empty($row['status']) ? (' [' . html_escape((string)$row['status']) . ']') : ''; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label class="form-label">Cara baca target</label>
                <select name="target_gate_mode" class="form-select">
                  <option value="WEIGHTED_SCORE">Target dibaca bertingkat</option>
                  <option value="ALL_REQUIRED">Semua target wajib lolos</option>
                  <option value="NONE">Tanpa gerbang target</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Nilai target minimum</label>
                <input type="number" step="0.01" name="min_target_score" class="form-control" value="100">
              </div>
            </div>
            <div class="row g-3 mt-1">
              <div class="col-md-4">
                <label class="form-label">Omzet minimal supaya bonus jalan</label>
                <input type="number" step="1000" name="threshold_amount" class="form-control" value="0" placeholder="Contoh: 3000000">
              </div>
              <div class="col-md-4">
                <label class="form-label">Cara hitung pool bonus</label>
                <select name="pool_formula_type" id="bonusPoolFormulaType" class="form-select">
                  <option value="PERCENTAGE">Persentase dari omzet</option>
                  <option value="FIXED_STEP">Nominal tetap</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Nilai pool bonus</label>
                <input type="number" step="0.01" name="pool_formula_value" class="form-control" value="3" placeholder="Contoh: 3 atau 500000">
                <div class="small text-muted mt-1" id="bonusPoolFormulaHint">Contoh 3 berarti 3% dari omzet bersih.</div>
              </div>
            </div>
            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label class="form-label">Jatah minimum untuk shift sepi (%)</label>
                <input type="number" step="0.01" name="min_shift_base_pct" class="form-control" value="30">
                <div class="small text-muted mt-1">Dipakai supaya shift yang omzetnya tipis tetap punya bagian dasar.</div>
              </div>
            </div>
            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label class="form-label">Perlakuan PH</label>
                <select name="ph_bonus_mode" class="form-select">
                  <option value="EXCLUDE">Tidak ikut bonus</option>
                  <option value="REDUCE">Ikut tapi dipotong</option>
                  <option value="ALLOW">Tetap ikut penuh</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Potong poin PH</label>
                <input type="number" step="0.01" name="ph_point_deduction" class="form-control" value="0">
              </div>
            </div>
            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label class="form-label">Target waktu saji (menit)</label>
                <input type="number" step="0.01" name="service_time_target_minute" class="form-control" value="0">
              </div>
              <div class="col-md-6">
                <label class="form-label">Bobot waktu saji</label>
                <input type="number" step="0.0001" name="service_time_weight" class="form-control" value="0">
              </div>
            </div>
            <div class="row g-3 mt-1">
              <div class="col-md-4">
                <label class="form-label">Bobot omzet shift</label>
                <input type="number" step="0.0001" name="shift_revenue_weight" class="form-control" value="1">
              </div>
              <div class="col-md-4">
                <label class="form-label">Bobot 360</label>
                <input type="number" step="0.0001" name="peer_review_weight" class="form-control" value="0">
              </div>
              <div class="col-md-4">
                <label class="form-label">Bobot hadir</label>
                <input type="number" step="0.0001" name="attendance_weight" class="form-control" value="1">
              </div>
            </div>
            <div class="mt-3">
              <label class="form-label">Bobot penalti manual</label>
              <input type="number" step="0.0001" name="manual_penalty_weight" class="form-control" value="1">
            </div>
            <div class="mt-3">
              <label class="form-label">Catatan</label>
              <textarea name="notes" class="form-control" rows="3" placeholder="Misalnya khusus shift malam, khusus kitchen, atau tahap percobaan."></textarea>
            </div>
            <div class="form-check form-switch mt-3">
              <input class="form-check-input" type="checkbox" role="switch" id="ruleIsActive" name="is_active" value="1" checked>
              <label class="form-check-label" for="ruleIsActive">Aturan aktif</label>
            </div>
            <div class="d-flex gap-2 mt-4">
              <button type="submit" class="btn btn-primary">Simpan Aturan</button>
              <button type="button" class="btn btn-outline-secondary" id="bonusRuleResetBtn">Reset Form</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="card bonus-card">
        <div class="card-header bg-white border-0 pb-0">
          <h5 class="mb-1">Aturan bonus yang sudah tercatat</h5>
          <div class="small text-muted">Klik edit bila ingin menjadikan salah satu rule sebagai template per outlet, divisi, atau perilaku PH.</div>
        </div>
        <div class="card-body">
          <form method="get" action="<?php echo site_url('payroll/bonus'); ?>" class="bonus-filter-bar mb-3">
            <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
            <input type="hidden" name="tab" value="rules">
            <input type="hidden" name="pool_q" value="<?php echo html_escape((string)($poolFilters['q'] ?? '')); ?>">
            <input type="hidden" name="pool_page" value="<?php echo (int)($poolPg['page'] ?? 1); ?>">
            <input type="hidden" name="pool_per_page" value="<?php echo (int)($poolPg['per_page'] ?? 25); ?>">
            <input type="hidden" name="penalty_type_q" value="<?php echo html_escape((string)($penaltyTypeFilters['q'] ?? '')); ?>">
            <input type="hidden" name="penalty_type_page" value="<?php echo (int)($penaltyTypePg['page'] ?? 1); ?>">
            <input type="hidden" name="penalty_type_per_page" value="<?php echo (int)($penaltyTypePg['per_page'] ?? 25); ?>">
            <input type="hidden" name="penalty_event_q" value="<?php echo html_escape((string)($penaltyEventFilters['q'] ?? '')); ?>">
            <input type="hidden" name="penalty_event_page" value="<?php echo (int)($penaltyEventPg['page'] ?? 1); ?>">
            <input type="hidden" name="penalty_event_per_page" value="<?php echo (int)($penaltyEventPg['per_page'] ?? 25); ?>">
            <input type="hidden" name="peer_q" value="<?php echo html_escape((string)($peerFilters['q'] ?? '')); ?>">
            <input type="hidden" name="peer_page" value="<?php echo (int)($peerPg['page'] ?? 1); ?>">
            <input type="hidden" name="peer_per_page" value="<?php echo (int)($peerPg['per_page'] ?? 25); ?>">
            <div class="row g-3 align-items-end">
              <div class="col-md-7">
                <label class="form-label">Cari aturan bonus</label>
                <input type="text" name="rule_q" class="form-control" value="<?php echo html_escape((string)($ruleFilters['q'] ?? '')); ?>" placeholder="Cari nama aturan, kode, outlet, divisi, atau target">
              </div>
              <div class="col-md-3">
                <label class="form-label">Baris</label>
                <select name="rule_per_page" class="form-select">
                  <?php foreach ([10, 25, 50, 100] as $size): ?>
                    <option value="<?php echo $size; ?>" <?php echo (int)($rulePg['per_page'] ?? 25) === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-outline-primary">Filter</button>
              </div>
            </div>
          </form>
          <div class="bonus-table-wrap bonus-table">
            <table class="table align-middle mb-0">
              <thead><tr><th>Aturan</th><th>Scope</th><th>Target</th><th>Pool Bonus</th><th>PH</th><th>Status</th><th>Aksi</th></tr></thead>
              <tbody>
              <?php if (empty($ruleRows)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada aturan bonus. Jalankan SQL foundation lalu mulai dari aturan default.</td></tr>
              <?php else: foreach ($ruleRows as $row): ?>
                <tr>
                  <td><strong><?php echo html_escape((string)($row['rule_name'] ?? '-')); ?></strong><div class="small text-muted"><?php echo html_escape((string)($row['rule_code'] ?? '-')); ?></div></td>
                  <td><?php echo html_escape((string)($row['outlet_name'] ?? $row['division_name'] ?? 'Global')); ?></td>
                  <td>
                    <div><?php echo html_escape($ruleTargetGateLabels[strtoupper((string)($row['target_gate_mode'] ?? 'WEIGHTED_SCORE'))] ?? (string)($row['target_gate_mode'] ?? 'WEIGHTED_SCORE')); ?></div>
                    <div class="small text-muted"><?php echo html_escape((string)($row['target_plan_name'] ?? 'Belum ditautkan')); ?></div>
                  </td>
                  <td>
                    <div>
                      <?php
                      $formulaType = strtoupper((string)($row['pool_formula_type'] ?? 'PERCENTAGE'));
                      $formulaValue = (float)($row['pool_formula_value'] ?? 0);
                      if ($formulaType === 'FIXED_STEP') {
                          echo 'Rp ' . number_format($formulaValue, 2, ',', '.');
                      } else {
                          echo number_format($formulaValue, 2, ',', '.') . '%';
                      }
                      ?>
                    </div>
                    <div class="small text-muted">
                      Min omzet Rp <?php echo number_format((float)($row['threshold_amount'] ?? 0), 2, ',', '.'); ?>
                      | Shift dasar <?php echo number_format((float)($row['min_shift_base_pct'] ?? 0), 2, ',', '.'); ?>%
                    </div>
                  </td>
                  <td>
                    <div><?php echo html_escape($phBonusModeLabels[strtoupper((string)($row['ph_bonus_mode'] ?? '-'))] ?? (string)($row['ph_bonus_mode'] ?? '-')); ?></div>
                    <div class="small text-muted">Potong poin: <?php echo number_format((float)($row['ph_point_deduction'] ?? 0), 2, ',', '.'); ?></div>
                  </td>
                  <td><span class="bonus-soft-badge <?php echo (int)($row['is_active'] ?? 0) === 1 ? 'ok' : 'muted'; ?>"><?php echo (int)($row['is_active'] ?? 0) === 1 ? 'AKTIF' : 'NONAKTIF'; ?></span></td>
                  <td>
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-primary bonus-rule-edit-btn"
                      data-id="<?php echo (int)($row['id'] ?? 0); ?>"
                      data-config-id="<?php echo (int)($row['config_id'] ?? 0); ?>"
                      data-rule-name="<?php echo html_escape((string)($row['rule_name'] ?? '')); ?>"
                      data-rule-code="<?php echo html_escape((string)($row['rule_code'] ?? '')); ?>"
                      data-outlet-id="<?php echo (int)($row['outlet_id'] ?? 0); ?>"
                      data-division-id="<?php echo (int)($row['division_id'] ?? 0); ?>"
                      data-target-plan-id="<?php echo (int)($row['linked_target_plan_id'] ?? 0); ?>"
                      data-target-gate-mode="<?php echo html_escape((string)($row['target_gate_mode'] ?? 'WEIGHTED_SCORE')); ?>"
                      data-min-target-score="<?php echo html_escape((string)($row['min_target_score'] ?? '100')); ?>"
                      data-threshold-amount="<?php echo html_escape((string)($row['threshold_amount'] ?? '0')); ?>"
                      data-pool-formula-type="<?php echo html_escape((string)($row['pool_formula_type'] ?? 'PERCENTAGE')); ?>"
                      data-pool-formula-value="<?php echo html_escape((string)($row['pool_formula_value'] ?? '3')); ?>"
                      data-min-shift-base-pct="<?php echo html_escape((string)($row['min_shift_base_pct'] ?? '30')); ?>"
                      data-ph-bonus-mode="<?php echo html_escape((string)($row['ph_bonus_mode'] ?? 'EXCLUDE')); ?>"
                      data-ph-point-deduction="<?php echo html_escape((string)($row['ph_point_deduction'] ?? '0')); ?>"
                      data-service-time-target-minute="<?php echo html_escape((string)($row['service_time_target_minute'] ?? '0')); ?>"
                      data-service-time-weight="<?php echo html_escape((string)($row['service_time_weight'] ?? '0')); ?>"
                      data-shift-revenue-weight="<?php echo html_escape((string)($row['shift_revenue_weight'] ?? '1')); ?>"
                      data-peer-review-weight="<?php echo html_escape((string)($row['peer_review_weight'] ?? '0')); ?>"
                      data-attendance-weight="<?php echo html_escape((string)($row['attendance_weight'] ?? '1')); ?>"
                      data-manual-penalty-weight="<?php echo html_escape((string)($row['manual_penalty_weight'] ?? '1')); ?>"
                      data-notes="<?php echo html_escape((string)($row['notes'] ?? '')); ?>"
                      data-is-active="<?php echo (int)($row['is_active'] ?? 0); ?>"
                    >Edit</button>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
          <?php $renderPager($rulePg, static function ($overrides) use ($buildTableUrl) { return $buildTableUrl(array_merge(['tab' => 'rules'], $overrides)); }, 'rule_page', 'rule_per_page'); ?>
        </div>
      </div>
    </div>
  </div>
<?php elseif ($tab === 'penalties'): ?>
  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card bonus-card">
        <div class="card-header bg-white border-0 pb-0">
          <h5 class="mb-1">Master penalti bonus</h5>
          <div class="small text-muted">Di sini kita bedakan penalti yang jalan otomatis, yang perlu admin input manual, dan yang sifatnya verifikasi berkala seperti follow IG atau story/tagging.</div>
        </div>
        <div class="card-body">
          <form method="post" action="<?php echo site_url('payroll/bonus/penalty-save'); ?>" id="bonusPenaltyForm" class="mb-4">
            <input type="hidden" name="id" value="">
            <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Nama penalti</label>
                <input type="text" name="penalty_name" class="form-control" placeholder="Contoh: Area kitchen kotor">
              </div>
              <div class="col-md-6">
                <label class="form-label">Kode penalti</label>
                <input type="text" name="penalty_code" class="form-control" placeholder="Kosongkan agar dibuat otomatis">
              </div>
              <div class="col-md-4">
                <label class="form-label">Kategori</label>
                <select name="category" class="form-select">
                  <option value="OTHER">Lainnya</option>
                  <option value="ATTENDANCE">Absensi</option>
                  <option value="SOCIAL_MEDIA">Sosial media</option>
                  <option value="DISCIPLINE">Disiplin</option>
                  <option value="PERFORMANCE">Kinerja</option>
                  <option value="SERVICE">Layanan</option>
                  <option value="PROPERTY">Aset / kerusakan</option>
                  <option value="HYGIENE">Kebersihan</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Berlaku untuk</label>
                <select name="applies_scope" class="form-select">
                  <option value="BOTH">Personal dan tim</option>
                  <option value="PERSONAL">Personal saja</option>
                  <option value="TEAM">Tim saja</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Urutan tampil</label>
                <input type="number" name="sort_order" class="form-control" value="0">
              </div>
              <div class="col-md-4">
                <label class="form-label">Cara kerja penalti</label>
                <select name="behavior_mode" id="penaltyBehaviorMode" class="form-select">
                  <option value="AUTO">Otomatis dari sistem</option>
                  <option value="SEMI_MANUAL">Semi manual / verifikasi</option>
                  <option value="MANUAL">Manual per kejadian</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Sumber otomatis / verifikasi</label>
                <select name="auto_source" id="penaltyAutoSource" class="form-select">
                  <option value="">Tidak otomatis</option>
                  <option value="ATTENDANCE">Absensi</option>
                  <option value="SERVICE">Layanan</option>
                  <option value="TARGET">Target</option>
                  <option value="PEER">Penilaian 360</option>
                  <option value="SOCIAL_MEDIA">Sosial media</option>
                  <option value="AUDIT">Audit</option>
                  <option value="CHECKLIST">Checklist</option>
                  <option value="OTHER">Lainnya</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Cara potong</label>
                <select name="deduction_mode" class="form-select">
                  <option value="FIXED_POINT">Potong poin tetap</option>
                  <option value="FIXED_AMOUNT">Potong nominal tetap</option>
                  <option value="VARIABLE">Variabel / fleksibel</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Trigger absensi</label>
                <input type="text" name="attendance_trigger" class="form-control" placeholder="Contoh: LATE_MINOR, ALPHA, ABSENT_PH">
                <div class="small text-muted mt-1">Isi jika penalti ini lahir dari event absensi tertentu.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Siklus verifikasi</label>
                <select name="verification_cycle" class="form-select">
                  <option value="PER_EVENT">Per kejadian</option>
                  <option value="DAILY">Harian</option>
                  <option value="MONTHLY">Bulanan</option>
                  <option value="UNTIL_CHANGED">Sampai ada perubahan</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Potong poin default</label>
                <input type="number" step="0.01" name="default_points_deducted" class="form-control" value="0">
              </div>
              <div class="col-md-6">
                <label class="form-label">Potong nominal default</label>
                <input type="number" step="0.01" name="default_amount_deducted" class="form-control" value="0">
              </div>
              <div class="col-12">
                <label class="form-label">Catatan</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Misalnya untuk audit pagi, kewajiban sosial media, atau kejadian khusus."></textarea>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-3 mt-3">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="penaltyManualOnly" name="is_manual_only" value="1">
                <label class="form-check-label" for="penaltyManualOnly">Paksa manual saja</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="penaltyApprovalRequired" name="approval_required" value="1" checked>
                <label class="form-check-label" for="penaltyApprovalRequired">Perlu persetujuan</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="penaltyRequiresEvidence" name="requires_evidence" value="1">
                <label class="form-check-label" for="penaltyRequiresEvidence">Butuh bukti / lampiran</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="penaltyIsActive" name="is_active" value="1" checked>
                <label class="form-check-label" for="penaltyIsActive">Aktif</label>
              </div>
            </div>
            <div class="d-flex gap-2 mt-4">
              <button type="submit" class="btn btn-primary">Simpan Penalti</button>
              <button type="button" class="btn btn-outline-secondary" id="bonusPenaltyResetBtn">Reset Form</button>
            </div>
          </form>
          <form method="get" action="<?php echo site_url('payroll/bonus'); ?>" class="bonus-filter-bar mb-3">
            <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
            <input type="hidden" name="tab" value="penalties">
            <input type="hidden" name="pool_q" value="<?php echo html_escape((string)($poolFilters['q'] ?? '')); ?>">
            <input type="hidden" name="pool_page" value="<?php echo (int)($poolPg['page'] ?? 1); ?>">
            <input type="hidden" name="pool_per_page" value="<?php echo (int)($poolPg['per_page'] ?? 25); ?>">
            <input type="hidden" name="rule_q" value="<?php echo html_escape((string)($ruleFilters['q'] ?? '')); ?>">
            <input type="hidden" name="rule_page" value="<?php echo (int)($rulePg['page'] ?? 1); ?>">
            <input type="hidden" name="rule_per_page" value="<?php echo (int)($rulePg['per_page'] ?? 25); ?>">
            <input type="hidden" name="penalty_event_q" value="<?php echo html_escape((string)($penaltyEventFilters['q'] ?? '')); ?>">
            <input type="hidden" name="penalty_event_page" value="<?php echo (int)($penaltyEventPg['page'] ?? 1); ?>">
            <input type="hidden" name="penalty_event_per_page" value="<?php echo (int)($penaltyEventPg['per_page'] ?? 25); ?>">
            <input type="hidden" name="peer_q" value="<?php echo html_escape((string)($peerFilters['q'] ?? '')); ?>">
            <input type="hidden" name="peer_page" value="<?php echo (int)($peerPg['page'] ?? 1); ?>">
            <input type="hidden" name="peer_per_page" value="<?php echo (int)($peerPg['per_page'] ?? 25); ?>">
            <div class="row g-3 align-items-end">
              <div class="col-md-7">
                <label class="form-label">Cari master penalti</label>
                <input type="text" name="penalty_type_q" class="form-control" value="<?php echo html_escape((string)($penaltyTypeFilters['q'] ?? '')); ?>" placeholder="Cari nama penalti, kode, kategori, mode, atau sumber">
              </div>
              <div class="col-md-3">
                <label class="form-label">Baris</label>
                <select name="penalty_type_per_page" class="form-select">
                  <?php foreach ([10, 25, 50, 100] as $size): ?>
                    <option value="<?php echo $size; ?>" <?php echo (int)($penaltyTypePg['per_page'] ?? 25) === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-outline-primary">Filter</button>
              </div>
            </div>
          </form>
          <div class="bonus-table-wrap bonus-table">
            <table class="table align-middle mb-0">
              <thead><tr><th>Nama Penalti</th><th>Kategori</th><th>Mode</th><th>Scope</th><th class="text-end">Poin</th><th>Auto</th><th>Approval</th><th>Aksi</th></tr></thead>
              <tbody>
              <?php if (empty($penaltyTypeRows)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Belum ada master penalti bonus.</td></tr>
              <?php else: foreach ($penaltyTypeRows as $row): ?>
                <tr>
                  <td><strong><?php echo html_escape((string)($row['penalty_name'] ?? '-')); ?></strong><div class="small text-muted"><?php echo html_escape((string)($row['penalty_code'] ?? '-')); ?></div></td>
                  <td><?php echo html_escape($penaltyCategoryLabels[strtoupper((string)($row['category'] ?? '-'))] ?? (string)($row['category'] ?? '-')); ?></td>
                  <td>
                    <span class="bonus-soft-badge info"><?php echo html_escape($penaltyBehaviorLabels[strtoupper((string)($row['behavior_mode'] ?? 'MANUAL'))] ?? 'Manual'); ?></span>
                    <div class="small text-muted"><?php echo html_escape($penaltyDeductionLabels[strtoupper((string)($row['deduction_mode'] ?? 'FIXED_POINT'))] ?? 'Potong poin tetap'); ?></div>
                  </td>
                  <td><?php echo html_escape($penaltyScopeLabels[strtoupper((string)($row['applies_scope'] ?? '-'))] ?? (string)($row['applies_scope'] ?? '-')); ?></td>
                  <td class="text-end">
                    <?php echo number_format((float)($row['default_points_deducted'] ?? 0), 2, ',', '.'); ?>
                    <div class="small text-muted">Rp <?php echo number_format((float)($row['default_amount_deducted'] ?? 0), 2, ',', '.'); ?></div>
                  </td>
                  <td>
                    <?php $autoSource = strtoupper((string)($row['auto_source'] ?? '')); ?>
                    <span class="bonus-soft-badge <?php echo $autoSource !== '' ? 'ok' : 'muted'; ?>"><?php echo html_escape($autoSourceLabels[$autoSource] ?? ($autoSource !== '' ? $autoSource : 'Tidak')); ?></span>
                    <div class="small text-muted"><?php echo html_escape($verificationCycleLabels[strtoupper((string)($row['verification_cycle'] ?? 'PER_EVENT'))] ?? 'Per kejadian'); ?></div>
                  </td>
                  <td><span class="bonus-soft-badge <?php echo (int)($row['approval_required'] ?? 0) === 1 ? 'warn' : 'muted'; ?>"><?php echo (int)($row['approval_required'] ?? 0) === 1 ? 'Ya' : 'Tidak'; ?></span></td>
                  <td>
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-primary bonus-penalty-edit-btn"
                      data-id="<?php echo (int)($row['id'] ?? 0); ?>"
                      data-penalty-name="<?php echo html_escape((string)($row['penalty_name'] ?? '')); ?>"
                      data-penalty-code="<?php echo html_escape((string)($row['penalty_code'] ?? '')); ?>"
                      data-category="<?php echo html_escape((string)($row['category'] ?? 'OTHER')); ?>"
                      data-applies-scope="<?php echo html_escape((string)($row['applies_scope'] ?? 'BOTH')); ?>"
                      data-deduction-mode="<?php echo html_escape((string)($row['deduction_mode'] ?? 'FIXED_POINT')); ?>"
                      data-behavior-mode="<?php echo html_escape((string)($row['behavior_mode'] ?? 'MANUAL')); ?>"
                      data-auto-source="<?php echo html_escape((string)($row['auto_source'] ?? '')); ?>"
                      data-attendance-trigger="<?php echo html_escape((string)($row['attendance_trigger'] ?? '')); ?>"
                      data-verification-cycle="<?php echo html_escape((string)($row['verification_cycle'] ?? 'PER_EVENT')); ?>"
                      data-default-points-deducted="<?php echo html_escape((string)($row['default_points_deducted'] ?? '0')); ?>"
                      data-default-amount-deducted="<?php echo html_escape((string)($row['default_amount_deducted'] ?? '0')); ?>"
                      data-is-manual-only="<?php echo (int)($row['is_manual_only'] ?? 0); ?>"
                      data-approval-required="<?php echo (int)($row['approval_required'] ?? 1); ?>"
                      data-requires-evidence="<?php echo (int)($row['requires_evidence'] ?? 0); ?>"
                      data-is-active="<?php echo (int)($row['is_active'] ?? 0); ?>"
                      data-sort-order="<?php echo (int)($row['sort_order'] ?? 0); ?>"
                      data-notes="<?php echo html_escape((string)($row['notes'] ?? '')); ?>"
                    >Edit</button>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
          <?php $renderPager($penaltyTypePg, static function ($overrides) use ($buildTableUrl) { return $buildTableUrl(array_merge(['tab' => 'penalties'], $overrides)); }, 'penalty_type_page', 'penalty_type_per_page'); ?>
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card bonus-card h-100">
        <div class="card-header bg-white border-0 pb-0">
          <h5 class="mb-1">Cara paling aman mengelompokkan penalti</h5>
        </div>
        <div class="card-body small text-muted">
          <ul class="mb-0 bonus-guide-list ps-3">
            <li><strong>Otomatis</strong>: telat, alpha, pulang cepat, tidak hadir shift PH, ambil PH, atau respon layanan lambat bila engine datanya sudah siap.</li>
            <li><strong>Semi manual</strong>: follow IG, story/tagging, checklist sosial media, atau audit kepatuhan yang diverifikasi admin dan tidak perlu diinput ulang tiap hari.</li>
            <li><strong>Manual</strong>: komplain tamu, area kotor, salah order, pemborosan bahan, kerusakan alat, atau pelanggaran khusus yang memang dinilai per kejadian.</li>
            <li>Kalau sebuah penalti bisa “lolos sampai ada perubahan”, gunakan siklus <em>sampai ada perubahan</em>.</li>
            <li>Kalau sebuah penalti memang kejadian harian, gunakan siklus <em>harian</em> atau <em>per kejadian</em>.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
  <div class="card bonus-card mt-4">
    <div class="card-header bg-white border-0 pb-0">
      <h5 class="mb-1">Input kejadian penalti manual / verifikasi</h5>
      <div class="small text-muted">Bagian ini dipakai untuk kejadian lapangan yang belum otomatis. Contohnya audit pagi, komplain tamu, atau verifikasi follow IG/story yang dilakukan admin.</div>
    </div>
    <div class="card-body">
      <form method="post" action="<?php echo site_url('payroll/bonus/penalty-event-save'); ?>">
        <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Tanggal kejadian</label>
            <input type="date" name="penalty_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Jenis penalti</label>
            <select name="penalty_type_id" id="penaltyTypeEventSelect" class="form-select" required>
              <option value="">Pilih jenis penalti...</option>
              <?php foreach ($penaltyTypeRows as $row): ?>
                <?php if (!$isPenaltySelectableForEvent($row)) { continue; } ?>
                <option value="<?php echo (int)($row['id'] ?? 0); ?>"
                        data-points="<?php echo html_escape((string)($row['default_points_deducted'] ?? '0')); ?>"
                        data-amount="<?php echo html_escape((string)($row['default_amount_deducted'] ?? '0')); ?>"
                        data-scope="<?php echo html_escape((string)($row['applies_scope'] ?? 'BOTH')); ?>"
                        data-behavior="<?php echo html_escape((string)($row['behavior_mode'] ?? 'MANUAL')); ?>">
                  <?php echo html_escape((string)($row['penalty_name'] ?? '-')); ?> - <?php echo html_escape($penaltyBehaviorLabels[strtoupper((string)($row['behavior_mode'] ?? 'MANUAL'))] ?? 'Manual'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Aturan bonus terkait</label>
            <select name="rule_id" class="form-select">
              <option value="">Tanpa aturan spesifik</option>
              <?php foreach ($bonusRuleOptionRows as $row): ?>
                <option value="<?php echo (int)($row['id'] ?? 0); ?>"><?php echo html_escape((string)($row['rule_name'] ?? '-')); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Scope penalti</label>
            <select name="penalty_scope" id="penaltyScopeSelect" class="form-select">
              <option value="PERSONAL">Personal</option>
              <option value="TEAM">Tim</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Pegawai</label>
            <select name="employee_id" id="penaltyEmployeeSelect" class="form-select">
              <option value="">Pilih pegawai...</option>
              <?php foreach ($employeeRows as $row): ?>
                <option value="<?php echo (int)($row['value'] ?? 0); ?>"><?php echo html_escape((string)($row['label'] ?? '-')); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Divisi tim</label>
            <select name="division_id" id="penaltyDivisionSelect" class="form-select">
              <option value="">Pilih divisi...</option>
              <?php foreach ($divisionRows as $row): ?>
                <option value="<?php echo (int)($row['value'] ?? 0); ?>"><?php echo html_escape((string)($row['label'] ?? '-')); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Shift terkait</label>
            <select name="shift_id" class="form-select">
              <option value="">Opsional</option>
              <?php foreach ($shiftRows as $row): ?>
                <option value="<?php echo (int)($row['value'] ?? 0); ?>"><?php echo html_escape((string)($row['label'] ?? '-')); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Potong poin</label>
            <input type="number" step="0.01" name="points_deducted" id="penaltyPointsInput" class="form-control" placeholder="Default dari master">
          </div>
          <div class="col-md-3">
            <label class="form-label">Potong nominal</label>
            <input type="number" step="0.01" name="amount_deducted" id="penaltyAmountInput" class="form-control" placeholder="Default dari master">
          </div>
          <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="APPROVED">Langsung disetujui</option>
              <option value="DRAFT">Simpan draft dulu</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Catatan cepat</label>
            <input type="text" class="form-control" value="Bisa dioverride dari default master." disabled>
          </div>
          <div class="col-12">
            <label class="form-label">Alasan kejadian</label>
            <textarea name="reason_text" class="form-control" rows="2" placeholder="Contoh: Audit pagi menemukan station kitchen shift malam belum bersih."></textarea>
          </div>
        </div>
        <div class="mt-3">
          <button type="submit" class="btn btn-primary">Simpan Kejadian Penalti</button>
        </div>
      </form>
    </div>
  </div>
  <div class="card bonus-card mt-4">
    <div class="card-header bg-white border-0 pb-0">
      <h5 class="mb-1">Riwayat kejadian penalti bulan ini</h5>
    </div>
    <div class="card-body">
      <form method="get" action="<?php echo site_url('payroll/bonus'); ?>" class="bonus-filter-bar mb-3">
        <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
        <input type="hidden" name="tab" value="penalties">
        <input type="hidden" name="pool_q" value="<?php echo html_escape((string)($poolFilters['q'] ?? '')); ?>">
        <input type="hidden" name="pool_page" value="<?php echo (int)($poolPg['page'] ?? 1); ?>">
        <input type="hidden" name="pool_per_page" value="<?php echo (int)($poolPg['per_page'] ?? 25); ?>">
        <input type="hidden" name="rule_q" value="<?php echo html_escape((string)($ruleFilters['q'] ?? '')); ?>">
        <input type="hidden" name="rule_page" value="<?php echo (int)($rulePg['page'] ?? 1); ?>">
        <input type="hidden" name="rule_per_page" value="<?php echo (int)($rulePg['per_page'] ?? 25); ?>">
        <input type="hidden" name="penalty_type_q" value="<?php echo html_escape((string)($penaltyTypeFilters['q'] ?? '')); ?>">
        <input type="hidden" name="penalty_type_page" value="<?php echo (int)($penaltyTypePg['page'] ?? 1); ?>">
        <input type="hidden" name="penalty_type_per_page" value="<?php echo (int)($penaltyTypePg['per_page'] ?? 25); ?>">
        <input type="hidden" name="peer_q" value="<?php echo html_escape((string)($peerFilters['q'] ?? '')); ?>">
        <input type="hidden" name="peer_page" value="<?php echo (int)($peerPg['page'] ?? 1); ?>">
        <input type="hidden" name="peer_per_page" value="<?php echo (int)($peerPg['per_page'] ?? 25); ?>">
        <div class="row g-3 align-items-end">
          <div class="col-md-7">
            <label class="form-label">Cari kejadian penalti</label>
            <input type="text" name="penalty_event_q" class="form-control" value="<?php echo html_escape((string)($penaltyEventFilters['q'] ?? '')); ?>" placeholder="Cari nama penalti, pegawai, divisi, alasan, atau status">
          </div>
          <div class="col-md-3">
            <label class="form-label">Baris</label>
            <select name="penalty_event_per_page" class="form-select">
              <?php foreach ([10, 25, 50, 100] as $size): ?>
                <option value="<?php echo $size; ?>" <?php echo (int)($penaltyEventPg['per_page'] ?? 25) === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-primary">Filter</button>
          </div>
        </div>
      </form>
      <div class="bonus-table-wrap bonus-table">
        <table class="table align-middle mb-0">
          <thead><tr><th>Tanggal</th><th>Jenis</th><th>Scope</th><th>Target</th><th class="text-end">Poin</th><th class="text-end">Nominal</th><th>Status</th></tr></thead>
          <tbody>
          <?php if (empty($penaltyEventRows)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada kejadian penalti pada bulan ini.</td></tr>
          <?php else: foreach ($penaltyEventRows as $row): ?>
            <tr>
              <td><?php echo html_escape((string)($row['penalty_date'] ?? '-')); ?></td>
              <td><strong><?php echo html_escape((string)($row['penalty_name'] ?? '-')); ?></strong><div class="small text-muted"><?php echo html_escape((string)($row['penalty_code'] ?? '-')); ?></div></td>
              <td><?php echo html_escape((string)($row['penalty_scope'] ?? '-')); ?></td>
              <td>
                <?php if (strtoupper((string)($row['penalty_scope'] ?? '')) === 'TEAM'): ?>
                  <strong><?php echo html_escape((string)($row['division_name'] ?? '-')); ?></strong>
                <?php else: ?>
                  <strong><?php echo html_escape((string)($row['employee_name'] ?? '-')); ?></strong>
                <?php endif; ?>
                <div class="small text-muted"><?php echo html_escape((string)($row['reason_text'] ?? '')); ?></div>
              </td>
              <td class="text-end"><?php echo number_format((float)($row['points_deducted'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end">Rp <?php echo number_format((float)($row['amount_deducted'] ?? 0), 2, ',', '.'); ?></td>
              <td><span class="bonus-soft-badge <?php echo strtoupper((string)($row['status'] ?? 'DRAFT')) === 'APPROVED' ? 'ok' : 'warn'; ?>"><?php echo html_escape((string)($row['status'] ?? '-')); ?></span></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php $renderPager($penaltyEventPg, static function ($overrides) use ($buildTableUrl) { return $buildTableUrl(array_merge(['tab' => 'penalties'], $overrides)); }, 'penalty_event_page', 'penalty_event_per_page'); ?>
    </div>
  </div>
<?php elseif ($tab === 'peer'): ?>
  <div class="card bonus-card">
    <div class="card-header bg-white border-0 pb-0 d-flex flex-wrap justify-content-between gap-2 align-items-start">
      <div>
        <h5 class="mb-1">Penilaian 360 yang menunggu moderasi</h5>
        <div class="small text-muted">Hanya superadmin/moderator yang boleh membaca isi mentah penilaian ini. Hasilnya nanti bisa diterjemahkan menjadi koreksi poin bonus yang lebih adil.</div>
      </div>
      <span class="bonus-soft-badge warn"><?php echo number_format((int)($summary['pending_peer_review_count'] ?? 0)); ?> pending</span>
    </div>
    <div class="card-body">
      <form method="get" action="<?php echo site_url('payroll/bonus'); ?>" class="bonus-filter-bar mb-3">
        <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
        <input type="hidden" name="tab" value="peer">
        <input type="hidden" name="pool_q" value="<?php echo html_escape((string)($poolFilters['q'] ?? '')); ?>">
        <input type="hidden" name="pool_page" value="<?php echo (int)($poolPg['page'] ?? 1); ?>">
        <input type="hidden" name="pool_per_page" value="<?php echo (int)($poolPg['per_page'] ?? 25); ?>">
        <input type="hidden" name="rule_q" value="<?php echo html_escape((string)($ruleFilters['q'] ?? '')); ?>">
        <input type="hidden" name="rule_page" value="<?php echo (int)($rulePg['page'] ?? 1); ?>">
        <input type="hidden" name="rule_per_page" value="<?php echo (int)($rulePg['per_page'] ?? 25); ?>">
        <input type="hidden" name="penalty_type_q" value="<?php echo html_escape((string)($penaltyTypeFilters['q'] ?? '')); ?>">
        <input type="hidden" name="penalty_type_page" value="<?php echo (int)($penaltyTypePg['page'] ?? 1); ?>">
        <input type="hidden" name="penalty_type_per_page" value="<?php echo (int)($penaltyTypePg['per_page'] ?? 25); ?>">
        <input type="hidden" name="penalty_event_q" value="<?php echo html_escape((string)($penaltyEventFilters['q'] ?? '')); ?>">
        <input type="hidden" name="penalty_event_page" value="<?php echo (int)($penaltyEventPg['page'] ?? 1); ?>">
        <input type="hidden" name="penalty_event_per_page" value="<?php echo (int)($penaltyEventPg['per_page'] ?? 25); ?>">
        <div class="row g-3 align-items-end">
          <div class="col-md-7">
            <label class="form-label">Cari penilaian 360</label>
            <input type="text" name="peer_q" class="form-control" value="<?php echo html_escape((string)($peerFilters['q'] ?? '')); ?>" placeholder="Cari penilai, pegawai, divisi, alasan, atau shift">
          </div>
          <div class="col-md-3">
            <label class="form-label">Baris</label>
            <select name="peer_per_page" class="form-select">
              <?php foreach ([10, 25, 50, 100] as $size): ?>
                <option value="<?php echo $size; ?>" <?php echo (int)($peerPg['per_page'] ?? 25) === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-primary">Filter</button>
          </div>
        </div>
      </form>
      <div class="bonus-table-wrap bonus-table">
        <table class="table align-middle mb-0">
          <thead><tr><th>Tanggal</th><th>Dari</th><th>Ke</th><th class="text-center">Bintang</th><th>Alasan</th><th>Shift</th><th>Moderasi</th></tr></thead>
          <tbody>
          <?php if (empty($pendingPeerRows)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada peer review yang menunggu moderasi.</td></tr>
          <?php else: foreach ($pendingPeerRows as $row): ?>
            <tr>
              <td><?php echo html_escape((string)($row['feedback_date'] ?? '-')); ?></td>
              <td><strong><?php echo html_escape((string)($row['from_employee_name'] ?? '-')); ?></strong><div class="small text-muted"><?php echo html_escape((string)($row['from_employee_code'] ?? '')); ?></div></td>
              <td><strong><?php echo html_escape((string)($row['to_employee_name'] ?? '-')); ?></strong><div class="small text-muted"><?php echo html_escape((string)($row['division_name'] ?? '')); ?></div></td>
              <td class="text-center fw-semibold"><?php echo str_repeat('*', (int)($row['star_rating'] ?? 0)); ?></td>
              <td><?php echo html_escape((string)($row['reason_text'] ?? '-')); ?></td>
              <td><?php echo html_escape(trim((string)(($row['shift_code'] ?? '') . ' ' . ($row['shift_name'] ?? '')))); ?></td>
              <td style="min-width:280px;">
                <form method="post" action="<?php echo site_url('payroll/bonus/peer-moderate/' . (int)($row['id'] ?? 0)); ?>" class="d-grid gap-2">
                  <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
                  <input type="text" name="moderation_notes" class="form-control form-control-sm" placeholder="Catatan moderator, opsional">
                  <div class="d-flex gap-2">
                    <button type="submit" name="decision" value="APPROVED" class="btn btn-sm btn-success">Setujui</button>
                    <button type="submit" name="decision" value="REJECTED" class="btn btn-sm btn-outline-danger">Tolak</button>
                  </div>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php $renderPager($peerPg, static function ($overrides) use ($buildTableUrl) { return $buildTableUrl(array_merge(['tab' => 'peer'], $overrides)); }, 'peer_page', 'peer_per_page'); ?>
    </div>
  </div>
<?php elseif ($tab === 'service'): ?>
  <div class="card bonus-card">
    <div class="card-header bg-white border-0 pb-0">
      <h5 class="mb-1">Metric layanan harian</h5>
      <div class="small text-muted">Di sini kita lihat kualitas layanan per hari, per outlet, dan per shift. Data ini nanti ikut membentuk bobot bonus operasional.</div>
    </div>
    <div class="card-body">
      <form method="get" action="<?php echo site_url('payroll/bonus'); ?>" class="bonus-filter-bar mb-3">
        <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
        <input type="hidden" name="tab" value="service">
        <input type="hidden" name="pool_q" value="<?php echo html_escape((string)($poolFilters['q'] ?? '')); ?>">
        <input type="hidden" name="pool_page" value="<?php echo (int)($poolPg['page'] ?? 1); ?>">
        <input type="hidden" name="pool_per_page" value="<?php echo (int)($poolPg['per_page'] ?? 25); ?>">
        <input type="hidden" name="rule_q" value="<?php echo html_escape((string)($ruleFilters['q'] ?? '')); ?>">
        <input type="hidden" name="rule_page" value="<?php echo (int)($rulePg['page'] ?? 1); ?>">
        <input type="hidden" name="rule_per_page" value="<?php echo (int)($rulePg['per_page'] ?? 25); ?>">
        <input type="hidden" name="penalty_type_q" value="<?php echo html_escape((string)($penaltyTypeFilters['q'] ?? '')); ?>">
        <input type="hidden" name="penalty_type_page" value="<?php echo (int)($penaltyTypePg['page'] ?? 1); ?>">
        <input type="hidden" name="penalty_type_per_page" value="<?php echo (int)($penaltyTypePg['per_page'] ?? 25); ?>">
        <input type="hidden" name="penalty_event_q" value="<?php echo html_escape((string)($penaltyEventFilters['q'] ?? '')); ?>">
        <input type="hidden" name="penalty_event_page" value="<?php echo (int)($penaltyEventPg['page'] ?? 1); ?>">
        <input type="hidden" name="penalty_event_per_page" value="<?php echo (int)($penaltyEventPg['per_page'] ?? 25); ?>">
        <input type="hidden" name="peer_q" value="<?php echo html_escape((string)($peerFilters['q'] ?? '')); ?>">
        <input type="hidden" name="peer_page" value="<?php echo (int)($peerPg['page'] ?? 1); ?>">
        <input type="hidden" name="peer_per_page" value="<?php echo (int)($peerPg['per_page'] ?? 25); ?>">
        <input type="hidden" name="monthly_q" value="<?php echo html_escape((string)($monthlyFilters['q'] ?? '')); ?>">
        <input type="hidden" name="monthly_page" value="<?php echo (int)($monthlyPg['page'] ?? 1); ?>">
        <input type="hidden" name="monthly_per_page" value="<?php echo (int)($monthlyPg['per_page'] ?? 25); ?>">
        <div class="row g-3 align-items-end">
          <div class="col-md-8">
            <label class="form-label">Cari metric</label>
            <input type="text" name="service_q" class="form-control" value="<?php echo html_escape((string)($serviceFilters['q'] ?? '')); ?>" placeholder="Cari outlet, divisi, shift, atau catatan sumber">
          </div>
          <div class="col-md-2">
            <label class="form-label">Baris</label>
            <select name="service_per_page" class="form-select">
              <?php foreach ([10, 25, 50, 100] as $size): ?>
                <option value="<?php echo $size; ?>" <?php echo (int)($servicePg['per_page'] ?? 25) === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-primary">Filter</button>
          </div>
        </div>
      </form>
      <div class="bonus-table-wrap bonus-table">
        <table class="table align-middle mb-0">
          <thead>
          <tr>
            <th>Tanggal</th>
            <th>Scope</th>
            <th class="text-end">Order</th>
            <th class="text-end">Tersaji</th>
            <th class="text-end">On Time</th>
            <th class="text-end">Lewat SLA</th>
            <th class="text-end">Rata2 Menit</th>
            <th class="text-end">Skor</th>
            <th>Catatan</th>
          </tr>
          </thead>
          <tbody>
          <?php if (empty($serviceMetricRows)): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">Belum ada metric layanan untuk bulan ini. Jalankan generator dari tab Ringkasan.</td></tr>
          <?php else: foreach ($serviceMetricRows as $row): ?>
            <?php
              $scopeBits = [];
              $scopeBits[] = !empty($row['outlet_name']) ? (string)$row['outlet_name'] : 'Semua outlet';
              if (!empty($row['division_name'])) {
                  $scopeBits[] = (string)$row['division_name'];
              }
              if (!empty($row['shift_code']) || !empty($row['shift_name'])) {
                  $scopeBits[] = trim((string)(($row['shift_code'] ?? '') . ' ' . ($row['shift_name'] ?? '')));
              } else {
                  $scopeBits[] = 'Gabungan shift';
              }
              $serviceScore = (float)($row['service_score_percent'] ?? 0);
            ?>
            <tr>
              <td><?php echo html_escape((string)($row['metric_date'] ?? '-')); ?></td>
              <td><strong><?php echo html_escape(implode(' • ', $scopeBits)); ?></strong></td>
              <td class="text-end"><?php echo number_format((int)($row['total_orders'] ?? 0)); ?></td>
              <td class="text-end"><?php echo number_format((int)($row['served_orders'] ?? 0)); ?></td>
              <td class="text-end"><?php echo number_format((int)($row['on_time_orders'] ?? 0)); ?></td>
              <td class="text-end"><?php echo number_format(max(0, (int)($row['served_orders'] ?? 0) - (int)($row['on_time_orders'] ?? 0))); ?></td>
              <td class="text-end"><?php echo number_format((float)($row['avg_service_minutes'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end">
                <span class="bonus-soft-badge <?php echo $serviceScore >= 90 ? 'ok' : ($serviceScore >= 75 ? 'warn' : 'muted'); ?>">
                  <?php echo number_format($serviceScore, 2, ',', '.'); ?>%
                </span>
              </td>
              <td class="small text-muted"><?php echo html_escape((string)($row['source_notes'] ?? '-')); ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php $renderPager($servicePg, static function ($overrides) use ($buildTableUrl) { return $buildTableUrl(array_merge(['tab' => 'service'], $overrides)); }, 'service_page', 'service_per_page'); ?>
    </div>
  </div>
<?php elseif ($tab === 'monthly'): ?>
  <div class="card bonus-card">
    <div class="card-header bg-white border-0 pb-0">
      <h5 class="mb-1">Rekap bonus bulanan per pegawai</h5>
      <div class="small text-muted">Ini adalah ringkasan akhir per pegawai untuk bulan terpilih. Cocok dipakai sebelum bonus diposting ke payroll.</div>
    </div>
    <div class="card-body">
      <form method="get" action="<?php echo site_url('payroll/bonus'); ?>" class="bonus-filter-bar mb-3">
        <input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
        <input type="hidden" name="tab" value="monthly">
        <input type="hidden" name="pool_q" value="<?php echo html_escape((string)($poolFilters['q'] ?? '')); ?>">
        <input type="hidden" name="pool_page" value="<?php echo (int)($poolPg['page'] ?? 1); ?>">
        <input type="hidden" name="pool_per_page" value="<?php echo (int)($poolPg['per_page'] ?? 25); ?>">
        <input type="hidden" name="rule_q" value="<?php echo html_escape((string)($ruleFilters['q'] ?? '')); ?>">
        <input type="hidden" name="rule_page" value="<?php echo (int)($rulePg['page'] ?? 1); ?>">
        <input type="hidden" name="rule_per_page" value="<?php echo (int)($rulePg['per_page'] ?? 25); ?>">
        <input type="hidden" name="penalty_type_q" value="<?php echo html_escape((string)($penaltyTypeFilters['q'] ?? '')); ?>">
        <input type="hidden" name="penalty_type_page" value="<?php echo (int)($penaltyTypePg['page'] ?? 1); ?>">
        <input type="hidden" name="penalty_type_per_page" value="<?php echo (int)($penaltyTypePg['per_page'] ?? 25); ?>">
        <input type="hidden" name="penalty_event_q" value="<?php echo html_escape((string)($penaltyEventFilters['q'] ?? '')); ?>">
        <input type="hidden" name="penalty_event_page" value="<?php echo (int)($penaltyEventPg['page'] ?? 1); ?>">
        <input type="hidden" name="penalty_event_per_page" value="<?php echo (int)($penaltyEventPg['per_page'] ?? 25); ?>">
        <input type="hidden" name="peer_q" value="<?php echo html_escape((string)($peerFilters['q'] ?? '')); ?>">
        <input type="hidden" name="peer_page" value="<?php echo (int)($peerPg['page'] ?? 1); ?>">
        <input type="hidden" name="peer_per_page" value="<?php echo (int)($peerPg['per_page'] ?? 25); ?>">
        <input type="hidden" name="service_q" value="<?php echo html_escape((string)($serviceFilters['q'] ?? '')); ?>">
        <input type="hidden" name="service_page" value="<?php echo (int)($servicePg['page'] ?? 1); ?>">
        <input type="hidden" name="service_per_page" value="<?php echo (int)($servicePg['per_page'] ?? 25); ?>">
        <div class="row g-3 align-items-end">
          <div class="col-md-8">
            <label class="form-label">Cari pegawai / paket bonus</label>
            <input type="text" name="monthly_q" class="form-control" value="<?php echo html_escape((string)($monthlyFilters['q'] ?? '')); ?>" placeholder="Cari nama pegawai, paket bonus, outlet, divisi, atau status">
          </div>
          <div class="col-md-2">
            <label class="form-label">Baris</label>
            <select name="monthly_per_page" class="form-select">
              <?php foreach ([10, 25, 50, 100] as $size): ?>
                <option value="<?php echo $size; ?>" <?php echo (int)($monthlyPg['per_page'] ?? 25) === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-primary">Filter</button>
          </div>
        </div>
      </form>
      <div class="bonus-table-wrap bonus-table">
        <table class="table align-middle mb-0">
          <thead>
          <tr>
            <th>Pegawai</th>
            <th>Paket Bonus</th>
            <th>Scope</th>
            <th class="text-end">Poin Final</th>
            <th class="text-end">Bonus Final</th>
            <th class="text-center">Telat / Alpha / PH</th>
            <th class="text-end">Peer</th>
            <th class="text-end">Layanan</th>
            <th class="text-end">Target</th>
            <th>Status</th>
          </tr>
          </thead>
          <tbody>
          <?php if (empty($monthlySummaryRows)): ?>
            <tr><td colspan="10" class="text-center text-muted py-4">Belum ada rekap bonus bulanan untuk bulan ini. Jalankan refresh rekap dari tab Ringkasan setelah pool disetujui.</td></tr>
          <?php else: foreach ($monthlySummaryRows as $row): ?>
            <?php
              $scopeLabel = trim((string)(($row['outlet_name'] ?? '') . ' ' . (!empty($row['division_name']) ? '• ' . $row['division_name'] : '')));
              if ($scopeLabel === '') {
                  $scopeLabel = 'Global';
              }
              $status = strtoupper((string)($row['payout_status'] ?? 'DRAFT'));
            ?>
            <tr>
              <td>
                <strong><?php echo html_escape((string)($row['employee_name'] ?? '-')); ?></strong>
                <div class="small text-muted"><?php echo html_escape((string)($row['employee_code'] ?? '')); ?></div>
              </td>
              <td>
                <strong><?php echo html_escape((string)($row['config_name'] ?? '-')); ?></strong>
                <div class="small text-muted"><?php echo html_escape((string)($row['rule_name'] ?? 'Tanpa rule spesifik')); ?></div>
              </td>
              <td class="small text-muted"><?php echo html_escape($scopeLabel); ?></td>
              <td class="text-end fw-semibold"><?php echo number_format((float)($row['total_final_point'] ?? 0), 4, ',', '.'); ?></td>
              <td class="text-end fw-semibold">Rp <?php echo number_format((float)($row['total_final_amount'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-center small">
                <?php echo number_format((int)($row['late_count'] ?? 0)); ?>
                /
                <?php echo number_format((int)($row['alpha_count'] ?? 0)); ?>
                /
                <?php echo number_format((int)($row['ph_taken_count'] ?? 0)); ?>
              </td>
              <td class="text-end"><?php echo number_format((float)($row['peer_avg_star'] ?? 0), 2, ',', '.'); ?></td>
              <td class="text-end"><?php echo number_format((float)($row['service_avg_score'] ?? 0), 2, ',', '.'); ?>%</td>
              <td class="text-end"><?php echo number_format((float)($row['target_avg_score'] ?? 0), 2, ',', '.'); ?>%</td>
              <td>
                <span class="bonus-soft-badge <?php echo $status === 'POSTED' ? 'ok' : ($status === 'APPROVED' ? 'info' : ($status === 'VOID' ? 'muted' : 'warn')); ?>">
                  <?php echo html_escape($status); ?>
                </span>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php $renderPager($monthlyPg, static function ($overrides) use ($buildTableUrl) { return $buildTableUrl(array_merge(['tab' => 'monthly'], $overrides)); }, 'monthly_page', 'monthly_per_page'); ?>
    </div>
  </div>
<?php else: ?>
  <div class="row g-4">
    <div class="col-lg-6">
      <div class="card bonus-card h-100">
        <div class="card-header bg-white border-0 pb-0"><h5 class="mb-1">Cara baca modul bonus</h5></div>
        <div class="card-body small text-muted">
          <ol class="mb-0 ps-3 bonus-guide-list">
            <li>Target keuangan disusun di modul target, bukan di sini.</li>
            <li>Bonus membaca target sebagai syarat kelulusan atau bobot tambahan.</li>
            <li>Pool bonus harian membaca omzet shift, kualitas layanan, dan penalti.</li>
            <li>Rekap bulanan baru didorong ke payroll setelah disetujui.</li>
          </ol>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card bonus-card h-100">
        <div class="card-header bg-white border-0 pb-0"><h5 class="mb-1">Prinsip penting</h5></div>
        <div class="card-body small text-muted">
          <ul class="mb-0 ps-3 bonus-guide-list">
            <li>Libur biasa berbeda dengan PH.</li>
            <li>PH bisa diperlakukan sebagai tidak dapat bonus atau potong poin, tergantung rule.</li>
            <li>Penilaian 360 hanya sinyal moderasi, bukan hukuman otomatis.</li>
            <li>Waktu penyajian harus ikut menentukan kualitas bonus operasional.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<script>
  (function () {
    var ruleForm = document.getElementById('bonusRuleForm');
    var ruleResetBtn = document.getElementById('bonusRuleResetBtn');
    var penaltyForm = document.getElementById('bonusPenaltyForm');
    var penaltyResetBtn = document.getElementById('bonusPenaltyResetBtn');

    function fillValue(form, name, value) {
      if (!form) return;
      var field = form.querySelector('[name="' + name + '"]');
      if (!field) return;
      if (field.type === 'checkbox') {
        field.checked = String(value) === '1';
        return;
      }
      field.value = value == null ? '' : value;
    }

    if (ruleResetBtn && ruleForm) {
      ruleResetBtn.addEventListener('click', function () {
        ruleForm.reset();
        fillValue(ruleForm, 'id', '');
        if (typeof syncPoolFormulaHint === 'function') {
          syncPoolFormulaHint();
        }
      });
    }

    document.querySelectorAll('.bonus-rule-edit-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        fillValue(ruleForm, 'id', btn.dataset.id || '');
        fillValue(ruleForm, 'config_id', btn.dataset.configId || '');
        fillValue(ruleForm, 'rule_name', btn.dataset.ruleName || '');
        fillValue(ruleForm, 'rule_code', btn.dataset.ruleCode || '');
        fillValue(ruleForm, 'outlet_id', btn.dataset.outletId || '');
        fillValue(ruleForm, 'division_id', btn.dataset.divisionId || '');
        fillValue(ruleForm, 'linked_target_plan_id', btn.dataset.targetPlanId || '');
        fillValue(ruleForm, 'target_gate_mode', btn.dataset.targetGateMode || 'WEIGHTED_SCORE');
        fillValue(ruleForm, 'min_target_score', btn.dataset.minTargetScore || '100');
        fillValue(ruleForm, 'threshold_amount', btn.dataset.thresholdAmount || '0');
        fillValue(ruleForm, 'pool_formula_type', btn.dataset.poolFormulaType || 'PERCENTAGE');
        fillValue(ruleForm, 'pool_formula_value', btn.dataset.poolFormulaValue || '3');
        fillValue(ruleForm, 'min_shift_base_pct', btn.dataset.minShiftBasePct || '30');
        fillValue(ruleForm, 'ph_bonus_mode', btn.dataset.phBonusMode || 'EXCLUDE');
        fillValue(ruleForm, 'ph_point_deduction', btn.dataset.phPointDeduction || '0');
        fillValue(ruleForm, 'service_time_target_minute', btn.dataset.serviceTimeTargetMinute || '0');
        fillValue(ruleForm, 'service_time_weight', btn.dataset.serviceTimeWeight || '0');
        fillValue(ruleForm, 'shift_revenue_weight', btn.dataset.shiftRevenueWeight || '1');
        fillValue(ruleForm, 'peer_review_weight', btn.dataset.peerReviewWeight || '0');
        fillValue(ruleForm, 'attendance_weight', btn.dataset.attendanceWeight || '1');
        fillValue(ruleForm, 'manual_penalty_weight', btn.dataset.manualPenaltyWeight || '1');
        fillValue(ruleForm, 'notes', btn.dataset.notes || '');
        fillValue(ruleForm, 'is_active', btn.dataset.isActive || '0');
        window.scrollTo({ top: ruleForm.getBoundingClientRect().top + window.scrollY - 120, behavior: 'smooth' });
      });
    });

    if (penaltyResetBtn && penaltyForm) {
      penaltyResetBtn.addEventListener('click', function () {
        penaltyForm.reset();
        fillValue(penaltyForm, 'id', '');
        fillValue(penaltyForm, 'approval_required', '1');
        fillValue(penaltyForm, 'is_active', '1');
        if (typeof syncPenaltyBehaviorMode === 'function') {
          syncPenaltyBehaviorMode();
        }
      });
    }

    document.querySelectorAll('.bonus-penalty-edit-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        fillValue(penaltyForm, 'id', btn.dataset.id || '');
        fillValue(penaltyForm, 'penalty_name', btn.dataset.penaltyName || '');
        fillValue(penaltyForm, 'penalty_code', btn.dataset.penaltyCode || '');
        fillValue(penaltyForm, 'category', btn.dataset.category || 'OTHER');
        fillValue(penaltyForm, 'applies_scope', btn.dataset.appliesScope || 'BOTH');
        fillValue(penaltyForm, 'deduction_mode', btn.dataset.deductionMode || 'FIXED_POINT');
        fillValue(penaltyForm, 'behavior_mode', btn.dataset.behaviorMode || 'MANUAL');
        fillValue(penaltyForm, 'auto_source', btn.dataset.autoSource || '');
        fillValue(penaltyForm, 'attendance_trigger', btn.dataset.attendanceTrigger || '');
        fillValue(penaltyForm, 'verification_cycle', btn.dataset.verificationCycle || 'PER_EVENT');
        fillValue(penaltyForm, 'default_points_deducted', btn.dataset.defaultPointsDeducted || '0');
        fillValue(penaltyForm, 'default_amount_deducted', btn.dataset.defaultAmountDeducted || '0');
        fillValue(penaltyForm, 'is_manual_only', btn.dataset.isManualOnly || '0');
        fillValue(penaltyForm, 'approval_required', btn.dataset.approvalRequired || '1');
        fillValue(penaltyForm, 'requires_evidence', btn.dataset.requiresEvidence || '0');
        fillValue(penaltyForm, 'is_active', btn.dataset.isActive || '0');
        fillValue(penaltyForm, 'sort_order', btn.dataset.sortOrder || '0');
        fillValue(penaltyForm, 'notes', btn.dataset.notes || '');
        window.scrollTo({ top: penaltyForm.getBoundingClientRect().top + window.scrollY - 120, behavior: 'smooth' });
      });
    });

    var penaltyTypeEventSelect = document.getElementById('penaltyTypeEventSelect');
    var penaltyScopeSelect = document.getElementById('penaltyScopeSelect');
    var penaltyEmployeeSelect = document.getElementById('penaltyEmployeeSelect');
    var penaltyDivisionSelect = document.getElementById('penaltyDivisionSelect');
    var penaltyPointsInput = document.getElementById('penaltyPointsInput');
    var penaltyAmountInput = document.getElementById('penaltyAmountInput');
    var penaltyBehaviorMode = document.getElementById('penaltyBehaviorMode');
    var penaltyAutoSource = document.getElementById('penaltyAutoSource');
    var poolFormulaType = document.getElementById('bonusPoolFormulaType');
    var poolFormulaHint = document.getElementById('bonusPoolFormulaHint');

    function syncPoolFormulaHint() {
      if (!poolFormulaType || !poolFormulaHint) return;
      poolFormulaHint.textContent = poolFormulaType.value === 'FIXED_STEP'
        ? 'Contoh 500000 berarti bonus harian flat Rp 500.000.'
        : 'Contoh 3 berarti 3% dari omzet bersih.';
    }

    function syncPenaltyBehaviorMode() {
      if (!penaltyBehaviorMode || !penaltyAutoSource) return;
      if (penaltyBehaviorMode.value === 'MANUAL') {
        penaltyAutoSource.value = '';
        penaltyAutoSource.setAttribute('disabled', 'disabled');
      } else {
        penaltyAutoSource.removeAttribute('disabled');
      }
      if (penaltyForm) {
        var manualOnlyField = penaltyForm.querySelector('[name="is_manual_only"]');
        if (manualOnlyField) {
          manualOnlyField.checked = penaltyBehaviorMode.value === 'MANUAL';
        }
      }
    }

    function syncPenaltyEventDefaults() {
      if (!penaltyTypeEventSelect) return;
      var option = penaltyTypeEventSelect.options[penaltyTypeEventSelect.selectedIndex];
      if (option) {
        if (penaltyPointsInput && penaltyPointsInput.value === '') {
          penaltyPointsInput.value = option.dataset.points || '';
        }
        if (penaltyAmountInput && penaltyAmountInput.value === '') {
          penaltyAmountInput.value = option.dataset.amount || '';
        }
        if (penaltyScopeSelect && option.dataset.scope === 'PERSONAL') {
          penaltyScopeSelect.value = 'PERSONAL';
        } else if (penaltyScopeSelect && option.dataset.scope === 'TEAM') {
          penaltyScopeSelect.value = 'TEAM';
        }
      }
      syncPenaltyEventScope();
    }

    function syncPenaltyEventScope() {
      if (!penaltyScopeSelect) return;
      var isTeam = penaltyScopeSelect.value === 'TEAM';
      if (penaltyEmployeeSelect) {
        penaltyEmployeeSelect.disabled = isTeam;
      }
      if (penaltyDivisionSelect) {
        penaltyDivisionSelect.disabled = !isTeam;
      }
    }

    if (penaltyTypeEventSelect) {
      penaltyTypeEventSelect.addEventListener('change', function () {
        if (penaltyPointsInput) penaltyPointsInput.value = '';
        if (penaltyAmountInput) penaltyAmountInput.value = '';
        syncPenaltyEventDefaults();
      });
      syncPenaltyEventDefaults();
    }
    if (penaltyScopeSelect) {
      penaltyScopeSelect.addEventListener('change', syncPenaltyEventScope);
      syncPenaltyEventScope();
    }
    if (penaltyBehaviorMode) {
      penaltyBehaviorMode.addEventListener('change', syncPenaltyBehaviorMode);
      syncPenaltyBehaviorMode();
    }
    if (poolFormulaType) {
      poolFormulaType.addEventListener('change', syncPoolFormulaHint);
      syncPoolFormulaHint();
    }
  })();
</script>
