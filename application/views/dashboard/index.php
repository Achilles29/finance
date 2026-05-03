<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h5 class="fw-bold mb-0">Dashboard</h5>
    <p class="text-muted small mb-0">
      Selamat datang, <strong><?= htmlspecialchars($current_user['username'] ?? '') ?></strong>
      — <?= date('l, d F Y') ?>
    </p>
  </div>
</div>

<!-- Info cards ringkasan (placeholder — akan diisi data real di tahap selanjutnya) -->
<div class="row g-3 mb-4">
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-3 p-3 bg-primary bg-opacity-10">
          <i class="fas fa-cash-register fa-lg text-primary"></i>
        </div>
        <div>
          <div class="text-muted small">Transaksi Hari Ini</div>
          <div class="fw-bold fs-5">—</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-3 p-3 bg-success bg-opacity-10">
          <i class="fas fa-chart-line fa-lg text-success"></i>
        </div>
        <div>
          <div class="text-muted small">Omzet Hari Ini</div>
          <div class="fw-bold fs-5">—</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-3 p-3 bg-warning bg-opacity-10">
          <i class="fas fa-boxes fa-lg text-warning"></i>
        </div>
        <div>
          <div class="text-muted small">Stok Kritis</div>
          <div class="fw-bold fs-5">—</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-3 p-3 bg-info bg-opacity-10">
          <i class="fas fa-users fa-lg text-info"></i>
        </div>
        <div>
          <div class="text-muted small">Karyawan Hadir</div>
          <div class="fw-bold fs-5">—</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body text-center py-5 text-muted">
    <i class="fas fa-tools fa-2x mb-3 opacity-50"></i>
    <p class="mb-0">Dashboard sedang dalam pengembangan. Modul akan tampil di sini setelah aktif.</p>
  </div>
</div>
