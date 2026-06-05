<?php
$cfg          = is_array($cfg ?? null) ? $cfg : [];
$recentDumps  = is_array($recent_dumps ?? null) ? $recent_dumps : [];
$replStatus   = is_array($repl_status ?? null) ? $repl_status : [];
$failoverActive = !empty($failover_active);
$failoverTime   = (string)($failover_time ?? '');
$isWindows    = !empty($is_windows);
$envExists    = !empty($env_exists);

$cfgGet = static function(array $c, string $k, string $def = ''): string {
    return html_escape((string)($c[$k] ?? $def));
};

$replRole   = strtoupper((string)($cfg['repl.server_role'] ?? 'STANDALONE'));
$replOk     = ($replStatus['status'] ?? '') === 'OK';
$lastDump   = !empty($recentDumps) ? $recentDumps[0] : null;
?>
<style>
  .dbt-tab-nav { display:flex; flex-wrap:wrap; gap:.5rem; margin-bottom:1.5rem; }
  .dbt-tab-btn {
    display:inline-flex; align-items:center; gap:.5rem; padding:.6rem 1.1rem; border-radius:10px;
    border:1px solid #dfd5cb; background:#fff; color:#51453d; font-weight:700; cursor:pointer;
    text-decoration:none; font-size:.9rem; transition:all .15s;
  }
  .dbt-tab-btn:hover { background:#fff8f3; border-color:#d9c8bc; color:#3f342d; }
  .dbt-tab-btn.active { background:#9f2141; border-color:#9f2141; color:#fff; box-shadow:0 8px 18px rgba(159,33,65,.18); }
  .dbt-pane { display:none; }
  .dbt-pane.active { display:block; }
  .dbt-card { border:0; border-radius:16px; box-shadow:0 4px 16px rgba(58,38,30,.07); }
  .dbt-section { font-size:.74rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:#9a7f72; margin-bottom:.7rem; }
  .dbt-output { background:#1e1e2e; color:#a6e3a1; border-radius:10px; padding:.85rem 1rem; font-family:monospace; font-size:.8rem; max-height:240px; overflow-y:auto; white-space:pre-wrap; display:none; margin-top:.75rem; }
  .dbt-step { display:flex; gap:.85rem; margin-bottom:1.1rem; }
  .dbt-step-num { width:26px; height:26px; min-width:26px; border-radius:50%; background:#9f2141; color:#fff; font-size:.75rem; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
  .dbt-code { background:#1e1e2e; color:#cdd6f4; border-radius:10px; padding:.7rem .9rem; font-family:monospace; font-size:.8rem; overflow-x:auto; white-space:pre; }
  .status-ok   { display:inline-flex; align-items:center; gap:.3rem; background:#dcfce7; color:#166534; border-radius:8px; padding:.2rem .6rem; font-size:.78rem; font-weight:700; }
  .status-warn { display:inline-flex; align-items:center; gap:.3rem; background:#fff3cd; color:#856404; border-radius:8px; padding:.2rem .6rem; font-size:.78rem; font-weight:700; }
  .status-err  { display:inline-flex; align-items:center; gap:.3rem; background:#fee2e2; color:#991b1b; border-radius:8px; padding:.2rem .6rem; font-size:.78rem; font-weight:700; }
  .failover-banner { background:#fef3c7; border:2px solid #f59e0b; border-radius:14px; padding:.9rem 1.1rem; margin-bottom:1.5rem; }
  .dbt-summary-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:.75rem; margin-bottom:1.5rem; }
  .dbt-summary-card { border-radius:12px; padding:.75rem .9rem; border:1px solid rgba(224,209,198,.7); background:#fffdfb; }
  @media(max-width:767px) { .dbt-summary-grid { grid-template-columns:1fr 1fr; } }
</style>

<!-- Failover banner -->
<?php if ($failoverActive): ?>
<div class="failover-banner">
  <div class="fw-bold mb-1"><i class="ri ri-alert-line me-1 text-warning"></i>MODE DARURAT AKTIF — sejak <?php echo html_escape($failoverTime); ?></div>
  <div class="small">Server cadangan sedang dipakai sebagai server utama sementara. Setelah server utama online, gunakan tombol <strong>Pulihkan Koneksi</strong> di tab Server Cadangan.</div>
</div>
<?php endif; ?>

<div class="fin-page-header mb-3">
  <div>
    <h4 class="fin-page-title"><i class="ri ri-shield-check-line me-1 text-primary"></i>Perlindungan Database</h4>
    <p class="fin-page-subtitle mb-0">Backup otomatis ke GitHub &amp; server cadangan untuk jaga-jaga mati listrik atau gangguan server.</p>
  </div>
</div>

<!-- Ringkasan status -->
<div class="dbt-summary-grid">
  <div class="dbt-summary-card">
    <div class="dbt-section mb-1">Backup Terakhir</div>
    <?php if ($lastDump): ?>
      <div class="fw-semibold small"><?php echo html_escape($lastDump['date']); ?></div>
      <div class="text-muted" style="font-size:.75rem"><?php echo html_escape($lastDump['name']); ?> · <?php echo html_escape($lastDump['size']); ?></div>
    <?php else: ?>
      <span class="status-warn"><i class="ri ri-alert-line"></i>Belum ada backup</span>
    <?php endif; ?>
  </div>
  <div class="dbt-summary-card">
    <div class="dbt-section mb-1">Server Cadangan</div>
    <?php
      if ($replRole === 'STANDALONE') { echo '<span class="status-warn"><i class="ri ri-server-line"></i>Belum dikonfigurasi</span>'; }
      elseif ($failoverActive)        { echo '<span class="status-warn"><i class="ri ri-alert-line"></i>Mode darurat aktif</span>'; }
      elseif ($replOk)                { echo '<span class="status-ok"><i class="ri ri-checkbox-circle-line"></i>Sinkron (' . (int)($replStatus['lag_seconds'] ?? 0) . 's lag)</span>'; }
      else                            { echo '<span class="status-err"><i class="ri ri-close-circle-line"></i>Masalah koneksi</span>'; }
    ?>
  </div>
  <div class="dbt-summary-card">
    <div class="dbt-section mb-1">File .env</div>
    <?php if ($envExists): ?>
      <span class="status-ok"><i class="ri ri-checkbox-circle-line"></i>Sudah dikonfigurasi</span>
    <?php else: ?>
      <span class="status-warn"><i class="ri ri-alert-line"></i>Belum dibuat — klik Simpan</span>
    <?php endif; ?>
  </div>
</div>

<!-- Alert area -->
<div id="dbt-alert" class="mb-3" style="display:none"></div>

<!-- Tab navigation -->
<div class="dbt-tab-nav">
  <button class="dbt-tab-btn active" data-tab="backup">
    <i class="ri ri-database-2-line"></i>
    <span>Backup Otomatis</span>
  </button>
  <button class="dbt-tab-btn" data-tab="replica">
    <i class="ri ri-server-line"></i>
    <span>Server Cadangan</span>
  </button>
  <button class="dbt-tab-btn" data-tab="status">
    <i class="ri ri-pulse-line"></i>
    <span>Status &amp; Jalankan</span>
  </button>
</div>

<!-- ════════════════════════════════════════════════════════════
     TAB 1: BACKUP OTOMATIS
═════════════════════════════════════════════════════════════ -->
<div class="dbt-pane active" id="tab-backup">
  <div class="row g-3 mb-4">
    <div class="col-lg-7">
      <div class="card dbt-card p-4">
        <div class="dbt-section">Koneksi ke Database Lokal Server Ini</div>
        <div class="row g-3 mb-4">
          <div class="col-md-8"><label class="form-label small mb-1">Host Database</label>
            <input type="text" id="b_db_host" class="form-control" value="<?php echo $cfgGet($cfg,'backup.db_host','localhost'); ?>"></div>
          <div class="col-md-4"><label class="form-label small mb-1">Port</label>
            <input type="number" id="b_db_port" class="form-control" value="<?php echo $cfgGet($cfg,'backup.db_port','3306'); ?>"></div>
          <div class="col-md-6"><label class="form-label small mb-1">Username</label>
            <input type="text" id="b_db_user" class="form-control" value="<?php echo $cfgGet($cfg,'backup.db_user','root'); ?>"></div>
          <div class="col-md-6"><label class="form-label small mb-1">Password</label>
            <input type="password" id="b_db_pass" class="form-control" placeholder="Kosongkan jika tidak berubah" autocomplete="new-password"></div>
          <div class="col-12"><label class="form-label small mb-1">Nama Database</label>
            <input type="text" id="b_db_name" class="form-control" value="<?php echo $cfgGet($cfg,'backup.db_name','db_finance'); ?>"></div>
          <div class="col-12">
            <button type="button" id="btn-test-db" class="btn btn-outline-info btn-sm">
              <i class="ri ri-database-line me-1"></i>Tes Koneksi
            </button>
            <span id="db-test-result" class="ms-2 small"></span>
          </div>
        </div>

        <div class="dbt-section">Pengaturan Backup</div>
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label small mb-1">Simpan berapa hari?</label>
            <div class="input-group input-group-sm">
              <input type="number" id="b_retention" class="form-control" min="1" max="30" value="<?php echo $cfgGet($cfg,'backup.retention_days','3'); ?>">
              <span class="input-group-text">hari</span>
            </div>
            <div class="form-text">File lama otomatis dihapus.</div>
          </div>
          <div class="col-md-4"><label class="form-label small mb-1">GitHub Remote</label>
            <input type="text" id="b_remote" class="form-control" value="<?php echo $cfgGet($cfg,'backup.repo_remote','origin'); ?>"></div>
          <div class="col-md-4"><label class="form-label small mb-1">Branch GitHub</label>
            <input type="text" id="b_branch" class="form-control" value="<?php echo $cfgGet($cfg,'backup.repo_branch','main'); ?>"></div>
          <div class="col-12">
            <label class="form-label small mb-1">Tabel yang tidak perlu dibackup <span class="text-muted">(opsional, pisah koma)</span></label>
            <input type="text" id="b_exclude" class="form-control" placeholder="Contoh: sys_audit_log,att_presence"
                   value="<?php echo $cfgGet($cfg,'backup.exclude_tables',''); ?>">
            <div class="form-text">Tabel log besar yang tidak dibutuhkan untuk restore.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <!-- Cara setup otomatis -->
      <div class="card dbt-card p-4 mb-3">
        <div class="dbt-section">Cara Backup Berjalan Otomatis</div>
        <?php if ($isWindows): ?>
        <div class="dbt-step">
          <div class="dbt-step-num">1</div>
          <div><div class="fw-semibold mb-1">Klik Simpan di bawah</div>
            <div class="text-muted small">File konfigurasi dibuat otomatis.</div></div>
        </div>
        <div class="dbt-step">
          <div class="dbt-step-num">2</div>
          <div><div class="fw-semibold mb-1">Jadwalkan via Task Scheduler Windows</div>
            <div class="text-muted small">Buka Task Scheduler → buat task baru → Trigger: setiap 30 menit → Action:</div>
            <div class="dbt-code mt-1">scripts\backup\backup_full.bat</div>
          </div>
        </div>
        <?php else: ?>
        <div class="dbt-step">
          <div class="dbt-step-num">1</div>
          <div><div class="fw-semibold mb-1">Klik Simpan di bawah</div>
            <div class="text-muted small">File konfigurasi dibuat otomatis di server.</div></div>
        </div>
        <div class="dbt-step">
          <div class="dbt-step-num">2</div>
          <div><div class="fw-semibold mb-1">Jalankan sekali di terminal server:</div>
            <div class="dbt-code mt-1">chmod +x <?php echo rtrim(FCPATH,'/'); ?>/scripts/backup/backup_full.sh</div>
          </div>
        </div>
        <div class="dbt-step">
          <div class="dbt-step-num">3</div>
          <div><div class="fw-semibold mb-1">Tambahkan ke cron (jalankan: <code>crontab -e</code>):</div>
            <div class="dbt-code mt-1">*/30 * * * * <?php echo rtrim(FCPATH,'/'); ?>/scripts/backup/backup_full.sh</div>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Backup terakhir -->
      <div class="card dbt-card p-4">
        <div class="dbt-section">Backup Tersimpan</div>
        <?php if (empty($recentDumps)): ?>
          <div class="text-muted small">Belum ada file backup tersimpan di server ini.</div>
        <?php else: ?>
          <?php foreach ($recentDumps as $d): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:.35rem 0;border-bottom:1px solid rgba(224,209,198,.4)">
              <div>
                <div class="small fw-semibold"><?php echo html_escape($d['date']); ?></div>
                <div class="text-muted" style="font-size:.72rem"><?php echo html_escape($d['name']); ?></div>
              </div>
              <span class="badge bg-secondary"><?php echo html_escape($d['size']); ?></span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="d-flex gap-2">
    <button type="button" id="btn-save-backup" class="btn btn-primary">
      <i class="ri ri-save-line me-1"></i>Simpan Pengaturan
    </button>
  </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     TAB 2: SERVER CADANGAN
═════════════════════════════════════════════════════════════ -->
<div class="dbt-pane" id="tab-replica">
  <div class="alert alert-info border-0 small mb-4">
    <strong>Cara kerja:</strong> Server cadangan <em>menarik</em> data dari server utama secara otomatis. Laptop tanpa IP publik pun bisa jadi server cadangan — selama bisa konek ke internet.
  </div>

  <div class="row g-3 mb-4">
    <div class="col-lg-6">
      <div class="card dbt-card p-4">
        <div class="dbt-section">Peran Server Ini</div>
        <div class="mb-3">
          <?php foreach (['STANDALONE' => ['label'=>'Berdiri Sendiri','desc'=>'Tidak ada server cadangan'], 'MASTER' => ['label'=>'Server Utama (1)','desc'=>'Data mengalir ke server cadangan'], 'SLAVE' => ['label'=>'Server Cadangan (2)','desc'=>'Menerima data dari server utama']] as $v => $info): ?>
            <div class="form-check mb-2">
              <input class="form-check-input" type="radio" name="repl_role" id="role_<?php echo $v; ?>" value="<?php echo $v; ?>"
                <?php echo $replRole === $v ? 'checked' : ''; ?>>
              <label class="form-check-label" for="role_<?php echo $v; ?>">
                <div class="fw-semibold"><?php echo $info['label']; ?></div>
                <div class="text-muted small"><?php echo $info['desc']; ?></div>
              </label>
            </div>
          <?php endforeach; ?>
        </div>

        <div id="slave-fields" class="<?php echo $replRole !== 'SLAVE' ? 'd-none' : ''; ?>">
          <div class="dbt-section mt-3">Koneksi ke Server Utama</div>
          <div class="row g-3">
            <div class="col-md-8"><label class="form-label small mb-1">Alamat Server Utama</label>
              <input type="text" id="r_master_host" class="form-control" placeholder="IP atau domain server utama"
                     value="<?php echo $cfgGet($cfg,'repl.master_host',''); ?>"></div>
            <div class="col-md-4"><label class="form-label small mb-1">Port MySQL</label>
              <input type="number" id="r_master_port" class="form-control" value="<?php echo $cfgGet($cfg,'repl.master_port','3306'); ?>"></div>
            <div class="col-md-6"><label class="form-label small mb-1">User Sinkronisasi</label>
              <input type="text" id="r_repl_user" class="form-control" value="<?php echo $cfgGet($cfg,'repl.repl_user','repl_user'); ?>"></div>
            <div class="col-md-6"><label class="form-label small mb-1">Password Sinkronisasi</label>
              <input type="password" id="r_repl_pass" class="form-control" placeholder="Kosongkan jika tidak berubah" autocomplete="new-password"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card dbt-card p-4">
        <div class="dbt-section">Jika Server Cadangan = Laptop (Tanpa IP Publik)</div>
        <div class="text-muted small mb-3">
          Laptop terhubung ke server utama via <strong>terowongan SSH</strong>. Sinkronisasi tetap berjalan selama laptop ada koneksi internet.
        </div>
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" id="t_enabled" role="switch"
            <?php echo ($cfg['tunnel.enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
          <label class="form-check-label fw-semibold" for="t_enabled">Aktifkan koneksi via SSH Tunnel</label>
        </div>
        <div id="tunnel-fields" class="<?php echo ($cfg['tunnel.enabled'] ?? '0') !== '1' ? 'd-none' : ''; ?>">
          <div class="row g-2">
            <div class="col-md-8"><label class="form-label small mb-1">Alamat SSH Server Utama</label>
              <input type="text" id="t_ssh_host" class="form-control form-control-sm" placeholder="IP/domain (sama dengan server utama)"
                     value="<?php echo $cfgGet($cfg,'tunnel.ssh_host',''); ?>"></div>
            <div class="col-md-4"><label class="form-label small mb-1">User SSH</label>
              <input type="text" id="t_ssh_user" class="form-control form-control-sm" value="<?php echo $cfgGet($cfg,'tunnel.ssh_user','root'); ?>"></div>
            <div class="col-md-6"><label class="form-label small mb-1">Port lokal terowongan</label>
              <input type="number" id="t_local_port" class="form-control form-control-sm" value="<?php echo $cfgGet($cfg,'tunnel.local_port','3307'); ?>"></div>
            <div class="col-md-6"><label class="form-label small mb-1">Port MySQL server utama</label>
              <input type="number" id="t_remote_port" class="form-control form-control-sm" value="<?php echo $cfgGet($cfg,'tunnel.remote_port','3306'); ?>"></div>
          </div>
          <div class="alert alert-secondary border-0 small mt-2 py-2">
            Nyalakan terowongan: <code>scripts/replication/tunnel_start.<?php echo $isWindows ? 'bat' : 'sh'; ?></code>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Panduan prosedur -->
  <div class="card dbt-card p-4 mb-3">
    <div class="dbt-section">Alur Perlindungan</div>
    <div class="row g-3">
      <div class="col-md-4">
        <div class="fw-semibold small mb-1"><i class="ri ri-arrow-right-line me-1 text-success"></i>Kondisi Normal</div>
        <div class="text-muted small">Server utama aktif. Server cadangan otomatis menyalin semua perubahan data secara real-time. User memakai server utama.</div>
      </div>
      <div class="col-md-4">
        <div class="fw-semibold small mb-1"><i class="ri ri-alert-line me-1 text-warning"></i>Server Utama Mati</div>
        <div class="text-muted small">Gunakan tombol <strong>Aktifkan Darurat</strong> di tab Status. Server cadangan langsung bisa menerima transaksi. User dialihkan ke alamat server cadangan.</div>
      </div>
      <div class="col-md-4">
        <div class="fw-semibold small mb-1"><i class="ri ri-refresh-line me-1 text-info"></i>Server Utama Kembali</div>
        <div class="text-muted small">Jalankan <strong>Cek Perbedaan Data</strong>, lalu <strong>Pulihkan Koneksi</strong>. Data dari server cadangan disalin ke server utama, sinkronisasi normal kembali.</div>
      </div>
    </div>
  </div>

  <div class="d-flex gap-2">
    <button type="button" id="btn-save-replication" class="btn btn-primary">
      <i class="ri ri-save-line me-1"></i>Simpan Pengaturan
    </button>
  </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     TAB 3: STATUS & JALANKAN
═════════════════════════════════════════════════════════════ -->
<div class="dbt-pane" id="tab-status">
  <div class="row g-3 mb-4">
    <!-- Backup actions -->
    <div class="col-md-6">
      <div class="card dbt-card p-4 h-100">
        <div class="dbt-section">Backup Otomatis</div>
        <div class="mb-3">
          <?php if ($lastDump): ?>
            <div class="fw-semibold small">Backup terakhir:</div>
            <div class="small"><?php echo html_escape($lastDump['date']); ?> · <?php echo html_escape($lastDump['size']); ?></div>
          <?php else: ?>
            <span class="status-warn"><i class="ri ri-alert-line"></i>Belum ada backup tersimpan</span>
          <?php endif; ?>
        </div>
        <button type="button" id="btn-run-backup" class="btn btn-outline-primary btn-sm mb-1">
          <i class="ri ri-play-line me-1"></i>Jalankan Backup Sekarang
        </button>
        <div class="text-muted" style="font-size:.75rem">Proses ini mungkin butuh beberapa menit tergantung ukuran database.</div>
        <div id="out-backup" class="dbt-output"></div>
      </div>
    </div>

    <!-- Replication status -->
    <div class="col-md-6">
      <div class="card dbt-card p-4 h-100">
        <div class="dbt-section">Server Cadangan</div>
        <div class="mb-3" id="repl-status-display">
          <?php
            $rs  = $replStatus['status'] ?? '-';
            $cls = $rs === 'OK' ? 'status-ok' : ($rs === 'ERROR' ? 'status-err' : 'status-warn');
            $lag = isset($replStatus['lag_seconds']) ? ' · keterlambatan ' . (int)$replStatus['lag_seconds'] . ' detik' : '';
          ?>
          <?php if ($replRole === 'STANDALONE'): ?>
            <span class="status-warn"><i class="ri ri-server-line"></i>Belum dikonfigurasi</span>
          <?php elseif ($failoverActive): ?>
            <span class="status-warn"><i class="ri ri-alert-line"></i>Mode darurat aktif</span>
          <?php elseif ($rs !== '-'): ?>
            <span class="<?php echo $cls; ?>"><?php echo $rs === 'OK' ? '<i class="ri ri-checkbox-circle-line"></i>Sinkron' : '<i class="ri ri-close-circle-line"></i>Ada masalah'; ?><?php echo html_escape($lag); ?></span>
          <?php else: ?>
            <span class="status-warn"><i class="ri ri-question-line"></i>Belum dicek</span>
          <?php endif; ?>
        </div>
        <button type="button" id="btn-check-repl" class="btn btn-outline-info btn-sm mb-1">
          <i class="ri ri-refresh-line me-1"></i>Cek Kondisi Sekarang
        </button>
        <div class="text-muted" style="font-size:.75rem">Cek apakah sinkronisasi berjalan normal.</div>
        <div id="out-repl" class="dbt-output"></div>
      </div>
    </div>
  </div>

  <!-- Aksi darurat -->
  <div class="row g-3">
    <div class="col-md-6">
      <div class="card dbt-card p-4 border border-warning">
        <div class="fw-bold mb-2">
          <i class="ri ri-alert-line me-1 text-warning"></i>Aktifkan Mode Darurat
        </div>
        <div class="text-muted small mb-3">
          Gunakan ini <strong>hanya jika server utama benar-benar tidak bisa diakses</strong>.<br>
          Server ini akan langsung bisa menerima transaksi. Data tetap aman karena ID transaksi tidak akan tabrakan.
        </div>
        <button type="button" id="btn-failover" class="btn btn-warning w-100" <?php echo $failoverActive ? 'disabled' : ''; ?>>
          <i class="ri ri-alert-line me-1"></i><?php echo $failoverActive ? 'Mode Darurat Sudah Aktif' : 'Aktifkan Mode Darurat'; ?>
        </button>
        <div id="out-failover" class="dbt-output"></div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card dbt-card p-4 border border-success">
        <div class="fw-bold mb-2">
          <i class="ri ri-link me-1 text-success"></i>Pulihkan Koneksi ke Server Utama
        </div>
        <div class="text-muted small mb-3">
          Jalankan ini setelah server utama kembali online dan data sudah disinkronkan.<br>
          Server ini akan kembali ke mode cadangan yang menerima data dari server utama.
        </div>
        <button type="button" id="btn-restart-repl" class="btn btn-success w-100" <?php echo !$failoverActive ? 'disabled' : ''; ?>>
          <i class="ri ri-link me-1"></i>Pulihkan Koneksi
        </button>
        <div id="out-restart" class="dbt-output"></div>
      </div>
    </div>
  </div>
</div>

<!-- Modal konfirmasi darurat -->
<div class="modal fade" id="failoverModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:#fef3c7">
        <h5 class="modal-title fw-bold"><i class="ri ri-alert-line me-1 text-warning"></i>Konfirmasi Mode Darurat</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Kamu yakin server utama <strong>benar-benar tidak bisa diakses</strong> dan perlu mengaktifkan server ini sebagai pengganti sementara?</p>
        <p class="text-muted small">Jika server utama hanya lambat atau sedang restart, tunggu dulu — tidak perlu mode darurat.</p>
        <div class="form-check mt-3">
          <input class="form-check-input" type="checkbox" id="fail-agree">
          <label class="form-check-label small" for="fail-agree">
            Server utama memang tidak bisa diakses dan saya perlu mengaktifkan server ini.
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" id="btn-fail-confirm" class="btn btn-warning" disabled>Aktifkan Mode Darurat</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const BASE = '<?php echo site_url(); ?>';

  // ── Tabs ──────────────────────────────────────────────────────
  document.querySelectorAll('.dbt-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.dbt-tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.dbt-pane').forEach(p => p.classList.remove('active'));
      btn.classList.add('active');
      document.getElementById('tab-' + btn.dataset.tab)?.classList.add('active');
    });
  });

  // ── Helpers ───────────────────────────────────────────────────
  function esc(v) { const d = document.createElement('div'); d.textContent = String(v??''); return d.innerHTML; }
  function alert(type, msg) {
    const el = document.getElementById('dbt-alert');
    el.className = 'alert alert-' + type + ' border-0';
    el.innerHTML = msg;
    el.style.display = 'block';
    setTimeout(() => el.style.display = 'none', 6000);
  }
  function output(id, text, show) {
    const el = document.getElementById(id);
    if (el) { el.textContent = text; el.style.display = show ? 'block' : 'none'; }
  }
  function setLoading(btn, loading) {
    if (!btn) return;
    if (loading) { btn._html = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memproses...'; }
    else { btn.disabled = false; btn.innerHTML = btn._html || btn.innerHTML; }
  }
  async function post(url, data) {
    const r = await fetch(BASE + url, { method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'}, body:JSON.stringify(data) });
    const t = await r.text();
    let j; try { j = JSON.parse(t); } catch(e) { throw new Error('Response error. Cek permission.'); }
    if (!j.ok) throw new Error(j.message || 'Gagal');
    return j;
  }
  async function get(url) {
    const r = await fetch(BASE + url, { headers:{'X-Requested-With':'XMLHttpRequest'} });
    const j = await r.json();
    if (!j.ok) throw new Error(j.message || 'Gagal');
    return j;
  }

  // ── Show/hide slave & tunnel fields ──────────────────────────
  document.querySelectorAll('input[name="repl_role"]').forEach(el => {
    el.addEventListener('change', () => {
      document.getElementById('slave-fields')?.classList.toggle('d-none', el.value !== 'SLAVE');
    });
  });
  document.getElementById('t_enabled')?.addEventListener('change', function() {
    document.getElementById('tunnel-fields')?.classList.toggle('d-none', !this.checked);
  });

  // ── Kumpulkan data settings ───────────────────────────────────
  function getVal(id) { return document.getElementById(id)?.value?.trim() || ''; }
  function backupPayload() {
    return { 'backup.db_host': getVal('b_db_host'), 'backup.db_port': getVal('b_db_port'),
             'backup.db_user': getVal('b_db_user'), 'backup.db_pass': document.getElementById('b_db_pass')?.value || '',
             'backup.db_name': getVal('b_db_name'), 'backup.retention_days': getVal('b_retention'),
             'backup.repo_remote': getVal('b_remote'), 'backup.repo_branch': getVal('b_branch'),
             'backup.exclude_tables': getVal('b_exclude') };
  }
  function replPayload() {
    return { 'repl.server_role': document.querySelector('input[name="repl_role"]:checked')?.value || 'STANDALONE',
             'repl.master_host': getVal('r_master_host'), 'repl.master_port': getVal('r_master_port'),
             'repl.repl_user': getVal('r_repl_user'), 'repl.repl_pass': document.getElementById('r_repl_pass')?.value || '',
             'tunnel.enabled': document.getElementById('t_enabled')?.checked ? '1' : '0',
             'tunnel.ssh_host': getVal('t_ssh_host'), 'tunnel.ssh_user': getVal('t_ssh_user'),
             'tunnel.local_port': getVal('t_local_port'), 'tunnel.remote_port': getVal('t_remote_port') };
  }

  // ── Save actions ──────────────────────────────────────────────
  document.getElementById('btn-save-backup')?.addEventListener('click', async function() {
    setLoading(this, true);
    try { const j = await post('dbtools/settings/save', backupPayload()); alert('success', '✓ ' + esc(j.message)); }
    catch(e) { alert('danger', esc(e.message)); }
    finally { setLoading(this, false); }
  });
  document.getElementById('btn-save-replication')?.addEventListener('click', async function() {
    setLoading(this, true);
    try { const j = await post('dbtools/settings/save', replPayload()); alert('success', '✓ ' + esc(j.message)); }
    catch(e) { alert('danger', esc(e.message)); }
    finally { setLoading(this, false); }
  });

  // ── Test DB ───────────────────────────────────────────────────
  document.getElementById('btn-test-db')?.addEventListener('click', async function() {
    setLoading(this, true);
    const res = document.getElementById('db-test-result');
    try {
      const q = new URLSearchParams({ host:getVal('b_db_host'), port:getVal('b_db_port'), user:getVal('b_db_user'), pass:document.getElementById('b_db_pass')?.value||'', name:getVal('b_db_name') });
      const j = await get('dbtools/action/test-db?' + q);
      res.innerHTML = '<span class="text-success fw-semibold">✓ ' + esc(j.message) + '</span>';
    } catch(e) { res.innerHTML = '<span class="text-danger">✗ ' + esc(e.message) + '</span>'; }
    finally { setLoading(this, false); }
  });

  // ── Run backup ────────────────────────────────────────────────
  document.getElementById('btn-run-backup')?.addEventListener('click', async function() {
    setLoading(this, true); output('out-backup', 'Menjalankan backup, harap tunggu...', true);
    try { const j = await post('dbtools/action/run-backup', {}); output('out-backup', j.output || 'Selesai.', true); alert('success', '✓ Backup selesai!'); }
    catch(e) { output('out-backup', e.message, true); alert('danger', esc(e.message)); }
    finally { setLoading(this, false); }
  });

  // ── Cek replication ───────────────────────────────────────────
  document.getElementById('btn-check-repl')?.addEventListener('click', async function() {
    setLoading(this, true);
    try {
      const j = await get('dbtools/action/check-replication');
      const el = document.getElementById('repl-status-display');
      const ok = j.status === 'OK';
      const lag = j.lag_seconds !== undefined ? ' · keterlambatan ' + j.lag_seconds + ' detik' : '';
      el.innerHTML = ok
        ? '<span class="status-ok"><i class="ri ri-checkbox-circle-line"></i>Sinkron' + lag + '</span>'
        : '<span class="status-err"><i class="ri ri-close-circle-line"></i>' + esc(j.status) + (j.error ? ': ' + esc(j.error) : '') + '</span>';
    } catch(e) { alert('danger', esc(e.message)); }
    finally { setLoading(this, false); }
  });

  // ── Failover (modal) ──────────────────────────────────────────
  document.getElementById('btn-failover')?.addEventListener('click', () => {
    document.getElementById('fail-agree').checked = false;
    document.getElementById('btn-fail-confirm').disabled = true;
    new bootstrap.Modal(document.getElementById('failoverModal')).show();
  });
  document.getElementById('fail-agree')?.addEventListener('change', function() {
    document.getElementById('btn-fail-confirm').disabled = !this.checked;
  });
  document.getElementById('btn-fail-confirm')?.addEventListener('click', async function() {
    bootstrap.Modal.getInstance(document.getElementById('failoverModal'))?.hide();
    const btn = document.getElementById('btn-failover');
    setLoading(btn, true); output('out-failover', 'Mengaktifkan mode darurat...', true);
    try {
      const j = await post('dbtools/action/failover', { confirm: 'YES_FAILOVER' });
      output('out-failover', j.message, true); alert('success', '✓ Mode darurat aktif. Muat ulang halaman.');
      btn.disabled = true; btn.textContent = 'Mode Darurat Sudah Aktif';
      document.getElementById('btn-restart-repl').disabled = false;
    } catch(e) { output('out-failover', e.message, true); alert('danger', esc(e.message)); setLoading(btn, false); }
  });

  // ── Restart replication ───────────────────────────────────────
  document.getElementById('btn-restart-repl')?.addEventListener('click', async function() {
    setLoading(this, true); output('out-restart', 'Menghubungkan ke server utama...', true);
    try {
      const j = await post('dbtools/action/restart-replication', {
        master_host: getVal('r_master_host'), master_port: getVal('r_master_port'),
        repl_user: getVal('r_repl_user'), repl_pass: document.getElementById('r_repl_pass')?.value || '',
      });
      output('out-restart', j.message, true); alert('success', '✓ Koneksi pulih. Server kembali ke mode cadangan.');
      this.disabled = true;
      document.getElementById('btn-failover').disabled = false;
    } catch(e) { output('out-restart', e.message, true); alert('danger', esc(e.message)); }
    finally { setLoading(this, false); }
  });
})();
</script>
