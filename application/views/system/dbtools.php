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
  <button class="dbt-tab-btn" data-tab="panduan">
    <i class="ri ri-book-open-line"></i>
    <span>Panduan Lengkap</span>
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

<!-- ════════════════════════════════════════════════════════════
     TAB 4: PANDUAN LENGKAP
═════════════════════════════════════════════════════════════ -->
<div class="dbt-pane" id="tab-panduan">
<style>
  .guide-chapter { border:0; border-radius:16px; box-shadow:0 4px 16px rgba(58,38,30,.07); margin-bottom:1.2rem; overflow:hidden; }
  .guide-chapter-header {
    display:flex; justify-content:space-between; align-items:center;
    padding:.9rem 1.2rem; cursor:pointer; background:#fffdfb;
    border-bottom:1px solid rgba(224,209,198,.5);
    user-select:none;
  }
  .guide-chapter-header:hover { background:#fff8f3; }
  .guide-chapter-header .chap-title { font-weight:800; font-size:.95rem; display:flex; align-items:center; gap:.6rem; }
  .guide-chapter-header .chap-num { width:28px; height:28px; min-width:28px; border-radius:50%; background:#9f2141; color:#fff; font-size:.75rem; font-weight:800; display:flex; align-items:center; justify-content:center; }
  .guide-chapter-body { padding:1.2rem; background:#fff; display:none; }
  .guide-chapter-body.open { display:block; }
  .guide-toggle-icon { transition:transform .2s; }
  .guide-toggle-icon.open { transform:rotate(180deg); }
  .guide-step-list { list-style:none; padding:0; margin:0; }
  .guide-step-list li { display:flex; gap:.8rem; margin-bottom:.9rem; }
  .guide-step-list li .snum { width:22px; height:22px; min-width:22px; border-radius:50%; background:#f0ede8; color:#9f2141; font-size:.72rem; font-weight:800; display:flex; align-items:center; justify-content:center; margin-top:.1rem; }
  .guide-step-list li .sbody { flex:1; }
  .guide-step-list li .sbody .stitle { font-weight:700; margin-bottom:.2rem; }
  .guide-step-list li .sbody .sdesc { font-size:.86rem; color:#5a4a42; }
  .guide-cmd { background:#1e1e2e; color:#a6e3a1; border-radius:8px; padding:.5rem .8rem; font-family:monospace; font-size:.8rem; margin:.4rem 0; overflow-x:auto; white-space:pre; }
  .guide-note { background:#fff8f2; border-left:3px solid #f59e0b; border-radius:0 8px 8px 0; padding:.6rem .9rem; font-size:.85rem; color:#6b4a2a; margin:.6rem 0; }
  .guide-danger { background:#fff5f5; border-left:3px solid #c0392b; border-radius:0 8px 8px 0; padding:.6rem .9rem; font-size:.85rem; color:#7a1a1a; margin:.6rem 0; }
  .guide-ok { background:#f0fdf4; border-left:3px solid #22c55e; border-radius:0 8px 8px 0; padding:.6rem .9rem; font-size:.85rem; color:#14532d; margin:.6rem 0; }
  .guide-scenario { border:1px solid rgba(224,209,198,.6); border-radius:10px; padding:.8rem 1rem; margin-bottom:.8rem; background:#fffdfb; }
  .guide-scenario-title { font-weight:800; font-size:.88rem; margin-bottom:.4rem; }
  .guide-tag { display:inline-flex; align-items:center; gap:.3rem; padding:.15rem .55rem; border-radius:999px; font-size:.72rem; font-weight:700; }
  .guide-tag.server  { background:#e8f3ef; color:#1a6450; }
  .guide-tag.laptop  { background:#e0f2fe; color:#075985; }
  .guide-tag.both    { background:#fef3c7; color:#92400e; }
  .guide-tag.warning { background:#fee2e2; color:#991b1b; }
  .guide-checklist { list-style:none; padding:0; margin:0; }
  .guide-checklist li { display:flex; align-items:flex-start; gap:.6rem; margin-bottom:.5rem; font-size:.88rem; }
  .guide-checklist li::before { content:'□'; font-size:1rem; color:#9f2141; flex-shrink:0; margin-top:.05rem; }
</style>

<p class="text-muted small mb-4">Panduan lengkap dari awal sampai situasi darurat. Klik judul bab untuk membuka.</p>

<?php
$isWin = !empty($is_windows);
$root  = rtrim(FCPATH, '/\\');
?>

<!-- BAB 1 -->
<div class="guide-chapter">
  <div class="guide-chapter-header" onclick="toggleChap(this)">
    <div class="chap-title"><span class="chap-num">1</span>Persiapan Sebelum Mulai</div>
    <i class="ri ri-arrow-down-s-line guide-toggle-icon"></i>
  </div>
  <div class="guide-chapter-body">
    <p class="small text-muted mb-3">Pastikan semua ini sudah siap sebelum mengkonfigurasi backup atau server cadangan.</p>
    <ul class="guide-checklist">
      <li>Database berjalan normal dan bisa diakses dari aplikasi ini</li>
      <li>Akun GitHub sudah punya <strong>repository private</strong> khusus untuk backup (pisah dari repo kode)</li>
      <li>Di server, sudah ada SSH key yang terhubung ke GitHub (tes: <code>git push origin main</code> dari folder finance)</li>
      <li>Git sudah dikonfigurasi di server:
        <div class="dbt-code mt-1">git config --global user.email "email@kamu.com"
git config --global user.name "Finance Backup"</div>
      </li>
      <?php if (!$isWin): ?>
      <li>Script backup sudah bisa dieksekusi:
        <div class="dbt-code mt-1">chmod +x <?php echo $root; ?>/scripts/backup/backup_full.sh
chmod +x <?php echo $root; ?>/scripts/replication/*.sh</div>
      </li>
      <?php endif; ?>
      <li>mysqldump tersedia di server (cek: <code>mysqldump --version</code>)</li>
    </ul>
    <div class="guide-note mt-3">
      <strong>Kalau git push gagal:</strong> Pastikan SSH key server sudah ditambahkan di GitHub → Settings → SSH and GPG keys. Generate key dengan <code>ssh-keygen -t ed25519</code>, lalu tambahkan isi <code>~/.ssh/id_ed25519.pub</code> ke GitHub.
    </div>
  </div>
</div>

<!-- BAB 2 -->
<div class="guide-chapter">
  <div class="guide-chapter-header" onclick="toggleChap(this)">
    <div class="chap-title"><span class="chap-num">2</span>Setup Backup Otomatis ke GitHub</div>
    <i class="ri ri-arrow-down-s-line guide-toggle-icon"></i>
  </div>
  <div class="guide-chapter-body">
    <p class="small text-muted mb-3">Backup berjalan otomatis setiap 30 menit. File disimpan lokal 3 hari, lalu dikirim ke GitHub sebagai arsip jangka panjang.</p>
    <ol class="guide-step-list">
      <li>
        <div class="snum">1</div>
        <div class="sbody">
          <div class="stitle">Buka tab "Backup Otomatis" → isi semua kolom</div>
          <div class="sdesc">Host biasanya <code>localhost</code>, nama database <code>db_finance</code>. Klik <strong>Tes Koneksi</strong> dulu untuk memastikan berhasil.</div>
        </div>
      </li>
      <li>
        <div class="snum">2</div>
        <div class="sbody">
          <div class="stitle">Isi GitHub Remote dan Branch</div>
          <div class="sdesc">Remote biasanya <code>origin</code>, branch <code>main</code>. Pastikan remote sudah mengarah ke repo backup, bukan repo kode aplikasi.</div>
          <div class="guide-note">Cek remote aktif: <code>git remote -v</code> dari folder finance. Kalau masih ke repo kode, tambahkan remote baru: <code>git remote add backup git@github.com:username/finance-backup.git</code> lalu isi field Remote dengan <code>backup</code>.</div>
        </div>
      </li>
      <li>
        <div class="snum">3</div>
        <div class="sbody">
          <div class="stitle">Klik "Simpan Pengaturan"</div>
          <div class="sdesc">File <code>.env</code> dibuat otomatis di <code>scripts/backup/.env</code>. Tidak perlu edit manual.</div>
        </div>
      </li>
      <li>
        <div class="snum">4</div>
        <div class="sbody">
          <div class="stitle">Uji coba manual di terminal server</div>
          <?php if ($isWin): ?>
          <div class="sdesc">Di Windows, jalankan langsung:</div>
          <div class="dbt-code"><?php echo $root; ?>\scripts\backup\backup_full.bat</div>
          <?php else: ?>
          <div class="sdesc">Di server Linux:</div>
          <div class="dbt-code"><?php echo $root; ?>/scripts/backup/backup_full.sh</div>
          <?php endif; ?>
          <div class="sdesc mt-1">Harusnya muncul file <code>.sql.gz</code> di <code>backup/dumps/</code> dan push ke GitHub berhasil.</div>
        </div>
      </li>
      <li>
        <div class="snum">5</div>
        <div class="sbody">
          <div class="stitle">Jadwalkan agar berjalan otomatis</div>
          <?php if ($isWin): ?>
          <div class="sdesc">Buka <strong>Task Scheduler</strong> → Create Task → Trigger: setiap 30 menit → Action: <code><?php echo $root; ?>\scripts\backup\backup_full.bat</code></div>
          <?php else: ?>
          <div class="sdesc">Jalankan <code>crontab -e</code> dan tambahkan baris ini:</div>
          <div class="dbt-code">*/30 * * * * <?php echo $root; ?>/scripts/backup/backup_full.sh</div>
          <?php endif; ?>
          <div class="guide-ok mt-1">✓ Setelah ini backup berjalan sendiri. Kamu bisa lihat hasilnya di tab "Status & Jalankan" atau langsung di repository GitHub.</div>
        </div>
      </li>
    </ol>
  </div>
</div>

<!-- BAB 3 -->
<div class="guide-chapter">
  <div class="guide-chapter-header" onclick="toggleChap(this)">
    <div class="chap-title"><span class="chap-num">3</span>Setup Server Cadangan — Server ke Server</div>
    <i class="ri ri-arrow-down-s-line guide-toggle-icon"></i>
  </div>
  <div class="guide-chapter-body">
    <p class="small text-muted mb-3">Cocok jika kamu punya 2 server di hosting yang berbeda. Keduanya punya IP publik.</p>

    <div class="fw-bold small mb-2">Di Server Utama (Server 1):</div>
    <ol class="guide-step-list">
      <li>
        <div class="snum">1</div>
        <div class="sbody">
          <div class="stitle">Edit konfigurasi MySQL</div>
          <div class="sdesc">Buka file <code>/etc/mysql/my.cnf</code> (atau <code>/etc/my.cnf</code>), tambahkan:</div>
          <div class="dbt-code">[mysqld]
server-id                = 1
log_bin                  = mysql-bin
binlog_format            = ROW
auto_increment_offset    = 1
auto_increment_increment = 2</div>
          <div class="sdesc">Lalu restart: <code>sudo systemctl restart mysql</code></div>
        </div>
      </li>
      <li>
        <div class="snum">2</div>
        <div class="sbody">
          <div class="stitle">Buat user untuk sinkronisasi</div>
          <div class="dbt-code">mysql -u root -p

CREATE USER 'repl_user'@'%' IDENTIFIED BY 'password_aman_123';
GRANT REPLICATION SLAVE ON *.* TO 'repl_user'@'%';
FLUSH PRIVILEGES;
EXIT;</div>
          <div class="guide-note">Ganti <code>password_aman_123</code> dengan password yang kuat. Catat password ini — dipakai saat setup Server Cadangan.</div>
        </div>
      </li>
      <li>
        <div class="snum">3</div>
        <div class="sbody">
          <div class="stitle">Di halaman ini: tab "Server Cadangan" → pilih "Server Utama (1)" → Simpan</div>
          <div class="sdesc">Ini hanya mencatat peran server di pengaturan aplikasi.</div>
        </div>
      </li>
    </ol>

    <div class="fw-bold small mt-3 mb-2">Di Server Cadangan (Server 2):</div>
    <ol class="guide-step-list">
      <li>
        <div class="snum">1</div>
        <div class="sbody">
          <div class="stitle">Edit konfigurasi MySQL di Server 2</div>
          <div class="dbt-code">[mysqld]
server-id                = 2
log_bin                  = mysql-bin
binlog_format            = ROW
read_only                = ON
auto_increment_offset    = 2
auto_increment_increment = 2</div>
          <div class="sdesc">Restart: <code>sudo systemctl restart mysql</code></div>
        </div>
      </li>
      <li>
        <div class="snum">2</div>
        <div class="sbody">
          <div class="stitle">Import snapshot database dari Server 1</div>
          <div class="sdesc">Jalankan di Server 1:</div>
          <div class="dbt-code">mysqldump -u root -p --single-transaction --master-data=2 db_finance > snapshot.sql
scp snapshot.sql user@server2:/tmp/</div>
          <div class="sdesc">Lalu di Server 2:</div>
          <div class="dbt-code">mysql -u root -p db_finance < /tmp/snapshot.sql</div>
        </div>
      </li>
      <li>
        <div class="snum">3</div>
        <div class="sbody">
          <div class="stitle">Di halaman ini: tab "Server Cadangan" → isi koneksi ke Server 1 → Simpan</div>
          <div class="sdesc">Isi alamat Server 1, port 3306, user <code>repl_user</code>, dan password yang tadi dibuat.</div>
        </div>
      </li>
      <li>
        <div class="snum">4</div>
        <div class="sbody">
          <div class="stitle">Tab "Status & Jalankan" → klik "Cek Kondisi"</div>
          <div class="sdesc">Jika muncul <span style="background:#dcfce7;color:#166534;padding:.1rem .4rem;border-radius:4px;font-size:.75rem;font-weight:700">Sinkron</span> — selesai, sinkronisasi berjalan!</div>
          <div class="guide-ok mt-1">✓ Data dari Server 1 otomatis tersalin ke Server 2 secara real-time.</div>
        </div>
      </li>
    </ol>
  </div>
</div>

<!-- BAB 4 -->
<div class="guide-chapter">
  <div class="guide-chapter-header" onclick="toggleChap(this)">
    <div class="chap-title"><span class="chap-num">4</span>Setup Server Cadangan — Laptop Lokal (Tanpa IP Publik)</div>
    <i class="ri ri-arrow-down-s-line guide-toggle-icon"></i>
  </div>
  <div class="guide-chapter-body">
    <p class="small text-muted mb-3">Laptop bisa jadi server cadangan meski tidak punya IP publik. Laptop <em>menarik</em> data dari server utama via terowongan SSH.</p>

    <div class="guide-note mb-3"><strong>Prinsip kerja:</strong> Laptop buka koneksi ke server, bukan sebaliknya. Selama laptop ada internet dan server bisa diakses, sinkronisasi berjalan.</div>

    <ol class="guide-step-list">
      <li>
        <div class="snum">1</div>
        <div class="sbody">
          <div class="stitle">Setup Server Utama sama seperti Bab 3 langkah 1 & 2</div>
          <div class="sdesc">Konfigurasi MySQL dan buat user replication di server utama.</div>
        </div>
      </li>
      <li>
        <div class="snum">2</div>
        <div class="sbody">
          <div class="stitle">Install MySQL di laptop (jika belum ada)</div>
          <?php if ($isWin): ?>
          <div class="sdesc">Download dari mysql.com atau pakai XAMPP yang sudah ada.</div>
          <?php else: ?>
          <div class="dbt-code">sudo apt install mysql-server    # Ubuntu/Debian
sudo brew install mysql          # Mac</div>
          <?php endif; ?>
        </div>
      </li>
      <li>
        <div class="snum">3</div>
        <div class="sbody">
          <div class="stitle">Import snapshot database ke laptop (sama seperti Bab 3 langkah 2)</div>
        </div>
      </li>
      <li>
        <div class="snum">4</div>
        <div class="sbody">
          <div class="stitle">Buat terowongan SSH dari laptop ke server</div>
          <div class="sdesc">Terowongan ini membuat MySQL di laptop "seolah" terhubung langsung ke MySQL server:</div>
          <?php if ($isWin): ?>
          <div class="dbt-code">ssh -N -L 3307:127.0.0.1:3306 user@IP_SERVER_UTAMA</div>
          <div class="sdesc">Di Windows bisa juga pakai PuTTY: Connection → SSH → Tunnels → Source: 3307, Destination: 127.0.0.1:3306</div>
          <?php else: ?>
          <div class="dbt-code"># Jalankan di laptop, biarkan terminal ini terbuka
ssh -N -L 3307:127.0.0.1:3306 user@IP_SERVER_UTAMA

# Atau background (tetap jalan meski terminal ditutup):
autossh -M 0 -fN -L 3307:127.0.0.1:3306 user@IP_SERVER_UTAMA</div>
          <?php endif; ?>
        </div>
      </li>
      <li>
        <div class="snum">5</div>
        <div class="sbody">
          <div class="stitle">Di halaman ini: tab "Server Cadangan" → centang "SSH Tunnel" → isi koneksi → Simpan</div>
          <div class="sdesc">Isi alamat SSH host (sama dengan alamat server utama), user SSH, port lokal <code>3307</code>.</div>
        </div>
      </li>
      <li>
        <div class="snum">6</div>
        <div class="sbody">
          <div class="stitle">Nyalakan terowongan otomatis saat laptop menyala</div>
          <?php if ($isWin): ?>
          <div class="sdesc">Buka Task Scheduler → buat task → Trigger: At logon → Action: <code>scripts\replication\tunnel_start.bat</code></div>
          <?php else: ?>
          <div class="sdesc">Tambahkan ke crontab: <code>@reboot autossh -M 0 -fN -L 3307:127.0.0.1:3306 user@IP_SERVER</code></div>
          <?php endif; ?>
          <div class="guide-note mt-1">Saat laptop offline, sinkronisasi berhenti sementara. Saat online kembali, otomatis lanjut dari titik terakhir — tidak ada data yang hilang.</div>
        </div>
      </li>
    </ol>
  </div>
</div>

<!-- BAB 5 -->
<div class="guide-chapter">
  <div class="guide-chapter-header" onclick="toggleChap(this)">
    <div class="chap-title"><span class="chap-num">5</span>Penggunaan Sehari-hari</div>
    <i class="ri ri-arrow-down-s-line guide-toggle-icon"></i>
  </div>
  <div class="guide-chapter-body">
    <p class="small text-muted mb-3">Setelah semuanya disetup, kondisi normal tidak memerlukan tindakan manual apapun.</p>

    <div class="row g-3">
      <div class="col-md-4">
        <div class="guide-scenario">
          <div class="guide-scenario-title">✓ Kondisi Normal</div>
          <div class="sdesc small">
            Tidak perlu melakukan apapun. Backup berjalan otomatis setiap 30 menit.
            Server cadangan menyinkronkan data secara real-time. Semua transparan di balik layar.
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="guide-scenario">
          <div class="guide-scenario-title">👀 Cek Rutin (Mingguan)</div>
          <div class="sdesc small">
            Buka tab "Status & Jalankan" → klik "Cek Kondisi". Pastikan muncul <strong>Sinkron</strong>.
            Lihat backup terakhir tidak lebih dari 1 jam yang lalu.
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="guide-scenario">
          <div class="guide-scenario-title">📦 Restore dari Backup</div>
          <div class="sdesc small">
            Jika perlu restore: ambil file <code>.sql.gz</code> dari GitHub atau folder <code>backup/dumps/</code>, lalu:
            <div class="dbt-code mt-1">gunzip backup.sql.gz
mysql -u root db_finance < backup.sql</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- BAB 6 -->
<div class="guide-chapter">
  <div class="guide-chapter-header" onclick="toggleChap(this)">
    <div class="chap-title"><span class="chap-num">6</span>Prosedur Darurat — Server Utama Mati</div>
    <i class="ri ri-arrow-down-s-line guide-toggle-icon"></i>
  </div>
  <div class="guide-chapter-body">
    <div class="guide-danger mb-3"><strong>Kapan pakai ini:</strong> Server utama benar-benar tidak bisa diakses (mati listrik, gangguan hosting, dll.) dan operasional harus tetap jalan.</div>

    <ol class="guide-step-list">
      <li>
        <div class="snum">1</div>
        <div class="sbody">
          <div class="stitle">Pastikan server utama memang benar-benar tidak bisa diakses</div>
          <div class="sdesc">Coba buka aplikasi dari browser, ping server utama, hubungi provider hosting. Jangan panik dulu — tunggu 5-10 menit, kadang server restart sebentar.</div>
        </div>
      </li>
      <li>
        <div class="snum">2</div>
        <div class="sbody">
          <div class="stitle">Di server cadangan: buka halaman ini → tab "Status & Jalankan" → klik "Aktifkan Mode Darurat"</div>
          <div class="sdesc">Centang konfirmasi, klik lanjutkan. Proses hanya beberapa detik.</div>
          <div class="guide-ok mt-1">Server cadangan sekarang bisa menerima semua transaksi. Data tidak akan tabrakan karena server cadangan pakai ID angka genap (2, 4, 6...) sedangkan server utama pakai angka ganjil (1, 3, 5...).</div>
        </div>
      </li>
      <li>
        <div class="snum">3</div>
        <div class="sbody">
          <div class="stitle">Arahkan user ke alamat server cadangan</div>
          <div class="sdesc">Beritahu tim untuk buka aplikasi dari alamat server cadangan (IP atau domain server cadangan). Operasional berjalan normal.</div>
        </div>
      </li>
    </ol>

    <div class="guide-note">Selama mode darurat aktif, backup otomatis tetap berjalan di server cadangan dan push ke GitHub seperti biasa.</div>
  </div>
</div>

<!-- BAB 7 -->
<div class="guide-chapter">
  <div class="guide-chapter-header" onclick="toggleChap(this)">
    <div class="chap-title"><span class="chap-num">7</span>Pemulihan — Setelah Server Utama Kembali Online</div>
    <i class="ri ri-arrow-down-s-line guide-toggle-icon"></i>
  </div>
  <div class="guide-chapter-body">
    <p class="small text-muted mb-3">Jangan tergesa-gesa. Ikuti langkah ini secara berurutan untuk memastikan tidak ada data yang hilang.</p>

    <ol class="guide-step-list">
      <li>
        <div class="snum">1</div>
        <div class="sbody">
          <div class="stitle">Pastikan server utama sudah stabil sebelum memulai</div>
          <div class="sdesc">Coba akses aplikasi via server utama. Pastikan database bisa diquery. Jangan lanjut jika server utama masih tidak stabil.</div>
        </div>
      </li>
      <li>
        <div class="snum">2</div>
        <div class="sbody">
          <div class="stitle">Hentikan transaksi sementara (opsional, tapi disarankan)</div>
          <div class="sdesc">Kalau bisa, beritahu tim untuk berhenti transaksi sebentar (5-10 menit) saat proses pemulihan. Ini menghindari data baru masuk saat sedang sinkronisasi.</div>
        </div>
      </li>
      <li>
        <div class="snum">3</div>
        <div class="sbody">
          <div class="stitle">Jalankan pengecekan perbedaan data (lewat terminal server cadangan)</div>
          <div class="dbt-code">scripts/replication/recovery_sync.sh</div>
          <div class="sdesc">Script ini membandingkan data di server cadangan vs server utama. Hasilnya berupa laporan: ada berapa data baru yang perlu dipindahkan.</div>
        </div>
      </li>
      <li>
        <div class="snum">4</div>
        <div class="sbody">
          <div class="stitle">Periksa laporan perbedaan</div>
          <div class="sdesc">File laporan ada di <code>backup/sync_TANGGAL/diff_report.txt</code>. Jika 0 perbedaan → langsung ke langkah 6. Jika ada perbedaan → lanjut ke langkah 5.</div>
        </div>
      </li>
      <li>
        <div class="snum">5</div>
        <div class="sbody">
          <div class="stitle">Jalankan file sinkronisasi ke server utama (jika ada perbedaan)</div>
          <div class="dbt-code">mysql -h IP_SERVER_UTAMA -u root db_finance < backup/sync_TANGGAL/sync_to_server1.sql</div>
          <div class="guide-note">Buka file SQL dulu sebelum dijalankan, pastikan isinya masuk akal. File ini hanya berisi INSERT data baru dari server cadangan.</div>
        </div>
      </li>
      <li>
        <div class="snum">6</div>
        <div class="sbody">
          <div class="stitle">Di halaman ini → tab "Status & Jalankan" → klik "Pulihkan Koneksi"</div>
          <div class="sdesc">Server cadangan kembali ke mode sinkronisasi dari server utama. Operasional normal kembali.</div>
          <div class="guide-ok mt-1">✓ Arahkan kembali user ke server utama. Mode darurat selesai.</div>
        </div>
      </li>
    </ol>

    <div class="guide-danger mt-2">
      <strong>Jangan</strong> hapus file di <code>backup/sync_TANGGAL/</code> sampai kamu yakin semua data sudah aman dan aplikasi berjalan normal minimal 1 hari.
    </div>
  </div>
</div>

<!-- BAB 8 -->
<div class="guide-chapter">
  <div class="guide-chapter-header" onclick="toggleChap(this)">
    <div class="chap-title"><span class="chap-num">8</span>Pertanyaan Umum (FAQ)</div>
    <i class="ri ri-arrow-down-s-line guide-toggle-icon"></i>
  </div>
  <div class="guide-chapter-body">
    <div class="guide-scenario">
      <div class="guide-scenario-title">❓ Backup gagal push ke GitHub</div>
      <div class="sdesc small">Kemungkinan: SSH key belum dikonfigurasi, atau repo sudah terlalu besar. Cek log di <code>backup/logs/</code>. Solusi: cek <code>git push origin main</code> manual di terminal server.</div>
    </div>
    <div class="guide-scenario">
      <div class="guide-scenario-title">❓ "Tes Koneksi" gagal tapi database jelas jalan</div>
      <div class="sdesc small">PHP di server mungkin tidak punya ekstensi PDO MySQL. Cek: <code>php -m | grep pdo</code>. Solusi: <code>sudo apt install php-mysql</code> lalu restart web server.</div>
    </div>
    <div class="guide-scenario">
      <div class="guide-scenario-title">❓ Status server cadangan "Ada masalah" terus</div>
      <div class="sdesc small">Kemungkinan: jaringan putus, password berubah, atau server utama restart. Cek detail error di tab Status. Biasanya cukup tunggu beberapa menit — MySQL slave akan reconnect sendiri.</div>
    </div>
    <div class="guide-scenario">
      <div class="guide-scenario-title">❓ Laptop cadangan jarang dinyalakan, apa tetap aman?</div>
      <div class="sdesc small">Tetap aman. Saat laptop menyala dan terowongan SSH aktif, MySQL slave otomatis catch-up semua data yang tertinggal. Tidak perlu action manual.</div>
    </div>
    <div class="guide-scenario">
      <div class="guide-scenario-title">❓ Berapa lama proses pemulihan setelah darurat?</div>
      <div class="sdesc small">Tergantung berapa lama mode darurat berlangsung dan berapa banyak transaksi. Untuk pemadaman 1-2 jam dengan ratusan transaksi, proses pemulihan biasanya 5-15 menit.</div>
    </div>
    <div class="guide-scenario">
      <div class="guide-scenario-title">❓ Apakah backup GitHub aman? Tidak bocor?</div>
      <div class="sdesc small">Selama repository <strong>private</strong>, aman. Pastikan tidak menggunakan public repo. Untuk keamanan ekstra, pertimbangkan encrypt dump sebelum push (tambahan konfigurasi di <code>backup_full.sh</code>).</div>
    </div>
  </div>
</div>

</div><!-- /tab-panduan -->

<script>
function toggleChap(header) {
  const body = header.nextElementSibling;
  const icon = header.querySelector('.guide-toggle-icon');
  body.classList.toggle('open');
  icon.classList.toggle('open');
}
</script>

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
    const t = await r.text();
    let j; try { j = JSON.parse(t); } catch(e) { throw new Error('Response error. Kemungkinan permission belum ada atau halaman error.'); }
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
