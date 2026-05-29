<?php
$filters = is_array($filters ?? null) ? $filters : [];
$filterOptions = is_array($filter_options ?? null) ? $filter_options : [];
$outlets = is_array($filterOptions['outlets'] ?? null) ? $filterOptions['outlets'] : [];
$terminals = is_array($filterOptions['terminals'] ?? null) ? $filterOptions['terminals'] : [];
$cashierBootstrap = is_array($cashier_bootstrap ?? null) ? $cashier_bootstrap : [];
$activeSession = is_array($cashierBootstrap['active_session'] ?? null) ? $cashierBootstrap['active_session'] : null;
$catalogFilters = is_array($catalog_filters ?? null) ? $catalog_filters : [];
$catalogDivisions = is_array($catalogFilters['divisions'] ?? null) ? $catalogFilters['divisions'] : [];
?>

<style>
  .cashier-shell { display:grid; gap:1rem; }
  .cashier-workbench {
    display:grid;
    grid-template-columns:minmax(260px, 320px) minmax(0, 1fr) minmax(320px, 400px);
    gap:1rem;
    align-items:start;
  }
  .cashier-column { display:grid; gap:1rem; min-width:0; }
  .cashier-card {
    border:0; border-radius:26px; box-shadow:0 18px 38px rgba(58, 38, 30, .08);
    background:#fff;
  }
  .cashier-panel-title { font-size:1.05rem; font-weight:900; color:#2f2628; }
  .cashier-panel-note { color:#7d6d67; font-size:.88rem; }
  .cashier-search-mode .btn.active { background:#943f35; color:#fff; border-color:#943f35; }
  .cashier-search-result {
    min-height:420px; border:1px dashed rgba(191, 170, 157, .7); border-radius:22px; padding:1rem;
    background:linear-gradient(135deg,#fff9f6 0%,#fff 100%);
    overflow:auto;
  }
  .cashier-product-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:.9rem; }
  .cashier-product-card {
    border:1px solid rgba(225, 210, 199, .78); border-radius:20px; padding:.7rem; background:#fff;
    cursor:pointer; transition:transform .15s ease, box-shadow .15s ease, border-color .15s ease;
  }
  .cashier-product-card:hover {
    transform:translateY(-2px);
    border-color:rgba(169, 77, 56, .45);
    box-shadow:0 14px 30px rgba(97, 56, 43, .12);
  }
  .cashier-product-media {
    height:126px; border-radius:16px; overflow:hidden; background:linear-gradient(135deg,#f8e8db 0%, #f2d3bf 100%);
    display:flex; align-items:center; justify-content:center; margin-bottom:.8rem;
  }
  .cashier-product-media img { width:100%; height:100%; object-fit:cover; display:block; }
  .cashier-product-fallback {
    width:100%; height:100%; display:flex; align-items:center; justify-content:center;
    background:linear-gradient(135deg,#8f3d33 0%, #cf624a 100%); color:#fff; font-size:2rem; font-weight:900;
  }
  .cashier-product-name { font-weight:900; color:#33282a; }
  .cashier-product-meta { font-size:.78rem; color:#7f6f67; }
  .cashier-chip {
    display:inline-flex; align-items:center; gap:.35rem; padding:.24rem .65rem; border-radius:999px;
    font-size:.72rem; font-weight:800;
  }
  .cashier-chip.ok { background:#e9f8ec; color:#1d7f45; }
  .cashier-chip.warn { background:#fff3de; color:#8d5a00; }
  .cashier-chip.out { background:#fde8e8; color:#b42318; }
  .cashier-chip.bundle { background:#fff0de; color:#9a4e0f; }
  .cashier-chip.info { background:#eef5ff; color:#2359a6; }
  .cashier-member-box,
  .cashier-meta-box {
    border:1px solid rgba(225, 210, 199, .75); border-radius:18px; background:#fffdfb; padding:.9rem 1rem;
  }
  .cashier-member-empty { font-size:.86rem; color:#85736b; }
  .cashier-member-title { font-weight:900; color:#33282a; }
  .cashier-member-meta { font-size:.8rem; color:#7d6d67; }
  .cashier-search-wrap { position:relative; }
  .cashier-search-dropdown {
    position:absolute; top:calc(100% + .45rem); left:0; right:0; z-index:1050;
    background:#fff; border:1px solid rgba(201, 183, 168, .72); border-radius:18px;
    box-shadow:0 16px 36px rgba(61, 38, 27, .14); overflow:hidden;
  }
  .cashier-search-item { display:flex; justify-content:space-between; gap:.8rem; padding:.85rem 1rem; cursor:pointer; border-bottom:1px solid rgba(232,220,210,.9); }
  .cashier-search-item:last-child { border-bottom:0; }
  .cashier-search-item:hover { background:#fff7f2; }
  .cashier-cart-card { position:sticky; top:92px; }
  .cashier-cart-list { display:grid; gap:.75rem; }
  .cashier-cart-item {
    border:1px solid rgba(225,210,199,.78); border-radius:18px; padding:.9rem 1rem;
    background:linear-gradient(135deg,#fffaf7 0%,#fff 100%);
  }
  .cashier-cart-total { font-size:1.9rem; font-weight:900; color:#2e2527; }
  .cashier-cart-actions .btn { min-height:46px; }
  .cashier-mini-note { color:#85736b; font-size:.78rem; }
  .cashier-recent-list { display:grid; gap:.75rem; }
  .cashier-recent-item {
    border:1px solid rgba(225,210,199,.78); border-radius:18px; padding:.85rem 1rem; cursor:pointer; background:#fff;
  }
  .cashier-recent-item:hover { background:#fff7f2; }
  .cashier-empty {
    border:1px dashed rgba(189,170,154,.6); border-radius:18px; padding:1.3rem; text-align:center;
    color:#8b7a70; background:#fffaf6;
  }
  .cashier-launch-card {
    border:0; border-radius:30px; overflow:hidden;
    background:
      radial-gradient(circle at top right, rgba(255,255,255,.16), transparent 32%),
      linear-gradient(135deg, #2d2526 0%, #5b312b 58%, #8e4537 100%);
    color:#fff;
    box-shadow:0 26px 54px rgba(44,29,29,.18);
  }
  .cashier-launch-card .form-control,
  .cashier-launch-card .form-select {
    background:rgba(255,255,255,.96);
    border:0;
    border-radius:16px;
    min-height:48px;
  }
  .cashier-startup-overlay {
    min-height:calc(100vh - 140px);
    display:flex; align-items:center; justify-content:center;
  }
  .cashier-startup-frame {
    width:min(1180px, 100%);
    background:linear-gradient(145deg, rgba(255,253,249,.96), rgba(248,240,231,.98));
    border:1px solid rgba(224,209,198,.9);
    border-radius:34px;
    box-shadow:0 30px 70px rgba(44,29,29,.16);
    padding:1rem;
  }
  .cashier-kicker {
    display:inline-flex; align-items:center; gap:.45rem;
    padding:.35rem .8rem; border-radius:999px;
    background:rgba(255,255,255,.18); border:1px solid rgba(255,255,255,.14);
    font-size:.75rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em;
  }
  .cashier-session-ribbon {
    display:flex; gap:.6rem; flex-wrap:wrap;
  }
  .cashier-session-ribbon .cashier-chip {
    font-size:.78rem;
    padding:.4rem .75rem;
  }
  .cashier-side-note {
    border:1px solid rgba(225,210,199,.78);
    border-radius:20px;
    padding:1rem 1.1rem;
    background:linear-gradient(135deg,#fffaf7 0%,#fff 100%);
  }
  .cashier-session-card {
    border:1px solid rgba(225,210,199,.78);
    border-radius:22px;
    padding:1.15rem;
    background:linear-gradient(135deg,#fffaf7 0%,#fff 100%);
  }
  .cashier-session-key { font-size:1.05rem; font-weight:900; color:#2f2628; }
  .cashier-product-stage {
    min-height:0;
    display:flex;
    flex-direction:column;
  }
  .cashier-stage-toolbar {
    display:grid;
    gap:.8rem;
    margin-bottom:1rem;
  }
  .cashier-filter-row {
    display:flex; gap:.6rem; flex-wrap:wrap;
  }
  .cashier-filter-pill {
    display:inline-flex; align-items:center; gap:.35rem;
    padding:.52rem .9rem; border-radius:999px; border:1px solid rgba(224,209,198,.9);
    background:#fffaf7; color:#6f5d57; font-size:.8rem; font-weight:800; cursor:pointer;
  }
  .cashier-filter-pill.active {
    background:linear-gradient(135deg,#8f3d33 0%, #cf624a 100%);
    border-color:transparent;
    color:#fff;
    box-shadow:0 12px 24px rgba(143,61,51,.18);
  }
  .cashier-filter-pill.muted {
    background:#f6f1ec;
    color:#8d7d76;
  }
  .cashier-order-scroll {
    min-height:0;
    overflow:auto;
  }
  .cashier-header-grid {
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:.75rem;
  }
  .cashier-order-settings {
    border:1px solid rgba(225,210,199,.78);
    border-radius:18px;
    padding:1rem;
    background:#fffdfb;
  }
  .cashier-search-card,
  .cashier-header-card,
  .cashier-recent-card,
  .cashier-cart-card-shell {
    min-height:0;
  }
  .cashier-session-kpi {
    border:1px solid rgba(225,210,199,.78); border-radius:20px; padding:1rem 1.1rem;
    background:linear-gradient(135deg,#fffaf7 0%,#fff 100%);
    height:100%;
  }
  .cashier-session-summary {
    border:1px solid rgba(225,210,199,.78); border-radius:18px; padding:.85rem 1rem;
    background:#fffaf7;
  }
  .cashier-readonly {
    background:#f7f3ef !important;
    border-color:#eadfd6 !important;
  }
  .cashier-reversal-line {
    border:1px solid rgba(224,209,198,.75); border-radius:14px; padding:.85rem 1rem; background:#fffaf7;
  }
  .cashier-reversal-line + .cashier-reversal-line { margin-top:.7rem; }
  .cashier-extra-pill {
    display:inline-flex; align-items:center; gap:.35rem; margin:.2rem .35rem .2rem 0;
    padding:.24rem .58rem; border-radius:999px; background:#fff5ea; color:#8f4c17; font-size:.72rem; font-weight:700;
  }
  .cashier-extra-group {
    border:1px solid rgba(224,209,198,.75); border-radius:16px; padding:1rem; background:#fffdfb;
  }
  .cashier-extra-item {
    border:1px solid rgba(234,223,214,.88); border-radius:14px; padding:.75rem .85rem; background:#fff;
  }
  @media (max-width: 991.98px) {
    .cashier-workbench { grid-template-columns:1fr; }
    .cashier-cart-card { position:static; }
    .cashier-startup-overlay { min-height:auto; }
    .cashier-header-grid { grid-template-columns:1fr; }
  }
</style>

<div class="container-fluid px-0 py-2">
  <?php if (!$activeSession): ?>
    <div class="cashier-startup-overlay">
      <div class="cashier-startup-frame">
        <div class="card cashier-launch-card">
          <div class="card-body p-4 p-lg-5">
        <div class="row justify-content-center">
          <div class="col-lg-8 col-xl-7">
            <div class="card border-0 shadow-lg" style="border-radius:24px;">
              <div class="card-body p-4">
                <div class="cashier-kicker mb-2"><i class="ri-door-open-line"></i>Buka Kasir Dulu</div>
                <div class="cashier-panel-title mb-1">Mulai sesi kasir</div>
                <div class="cashier-panel-note mb-3">Pilih outlet, device, lalu masukkan modal awal. Setelah sesi aktif, outlet dan device transaksi akan otomatis terkunci mengikuti sesi ini.</div>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">Outlet</label>
                    <select class="form-select" id="cashier_launch_outlet" <?php echo empty($outlets) ? 'disabled' : ''; ?>>
                      <option value="">Pilih Outlet</option>
                      <?php foreach ($outlets as $outlet): ?>
                        <option value="<?php echo (int)$outlet['id']; ?>"><?php echo html_escape((string)$outlet['outlet_name']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">Device / Terminal</label>
                    <select class="form-select" id="cashier_launch_terminal" <?php echo empty($outlets) ? 'disabled' : ''; ?>>
                      <option value="">Pilih Device</option>
                      <?php foreach ($terminals as $terminal): ?>
                        <option value="<?php echo (int)$terminal['id']; ?>" data-outlet-id="<?php echo (int)($terminal['outlet_id'] ?? 0); ?>"><?php echo html_escape((string)$terminal['terminal_name']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">Modal Awal</label>
                    <input type="number" min="0" step="1000" class="form-control" id="cashier_launch_opening_cash" placeholder="Mis. 200000">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">Catatan Buka Kasir</label>
                    <input type="text" class="form-control" id="cashier_launch_notes" placeholder="Opsional">
                  </div>
                  <div class="col-12 d-grid">
                    <button type="button" class="btn btn-primary btn-lg" id="cashier_open_btn" <?php echo empty($outlets) ? 'disabled' : ''; ?>>Buka Kasir</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if (empty($outlets)): ?>
    <div class="alert alert-warning border-0 shadow-sm mt-3">
      Outlet POS lokal belum tersedia. Jalankan setup outlet POS lokal dulu sebelum mulai memakai layar kasir.
    </div>
  <?php endif; ?>

  <div class="cashier-shell mt-3<?php echo $activeSession ? '' : ' d-none'; ?>" id="cashier_workspace">
    <div class="cashier-workbench">
      <div class="cashier-column">
        <div class="cashier-session-card">
          <div class="d-flex justify-content-between align-items-start gap-3">
            <div>
              <div class="cashier-panel-note mb-1">Shift Aktif</div>
              <div class="cashier-session-key"><?php echo html_escape((string)($activeSession['shift_no'] ?? '-')); ?></div>
              <div class="cashier-mini-note mt-1"><?php echo html_escape((string)($activeSession['opened_at'] ?? '-')); ?></div>
            </div>
            <span class="cashier-chip info"><i class="ri-wallet-3-line"></i><?php echo 'Rp ' . number_format((float)($activeSession['opening_cash'] ?? 0), 0, ',', '.'); ?></span>
          </div>
          <div class="d-grid mt-3">
            <button type="button" class="btn btn-outline-danger" id="cashier_close_btn">Tutup Kasir</button>
          </div>
        </div>
        <div class="card cashier-card cashier-recent-card">
          <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
              <div>
                <div class="cashier-panel-title">Order Aktif Sesi Ini</div>
              </div>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary cashier-status-tab" data-status="DRAFT">Draft</button>
                <button type="button" class="btn btn-sm btn-outline-primary cashier-status-tab" data-status="CONFIRMED">Confirmed</button>
                <button type="button" class="btn btn-sm btn-outline-primary cashier-status-tab active" data-status="ALL">Semua</button>
              </div>
            </div>
            <div class="row g-2 mb-3">
              <div class="col-12">
                <input id="cashier_recent_q" class="form-control" placeholder="Cari order no / outlet / terminal / kasir">
              </div>
              <div class="col-12">
                <select id="cashier_recent_limit" class="form-select">
                  <option value="10">10</option>
                  <option value="20" selected>20</option>
                  <option value="50">50</option>
                </select>
              </div>
            </div>
            <div id="cashier_recent_list" class="cashier-recent-list"></div>
            <div id="cashier_recent_empty" class="cashier-empty d-none">Belum ada order pada filter ini.</div>
          </div>
        </div>
      </div>

      <div class="cashier-column">
        <div class="card cashier-card cashier-search-card cashier-product-stage">
          <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
              <div>
                <div class="cashier-panel-title">Produk POS</div>
              </div>
            </div>
            <input type="hidden" id="cashier_outlet_id" value="<?php echo (int)($activeSession['outlet_id'] ?? 0); ?>">
            <input type="hidden" id="cashier_terminal_id" value="<?php echo (int)($activeSession['terminal_id'] ?? 0); ?>">
            <div class="cashier-stage-toolbar">
              <div class="row g-2">
                <div class="col-lg-7">
                  <input type="text" class="form-control" id="cashier_product_search" placeholder="Cari produk, bundle, atau kode item di sini...">
                </div>
                <div class="col-lg-5">
                  <div class="cashier-meta-box h-100 d-flex align-items-center justify-content-between gap-3">
                    <div>
                      <div class="small text-muted">Katalog Aktif</div>
                      <div class="fw-bold" id="cashier_catalog_hint">Semua produk kasir</div>
                    </div>
                    <div class="cashier-chip info" id="cashier_catalog_count">0 item</div>
                  </div>
                </div>
              </div>
              <div>
                <div class="small text-muted mb-2">Filter Divisi</div>
                <div class="cashier-filter-row" id="cashier_division_filters"></div>
              </div>
            </div>
            <div class="cashier-search-result mt-2" id="cashier_catalog_result">
              <div class="cashier-empty">Katalog kasir akan tampil otomatis setelah sesi aktif.</div>
            </div>
          </div>
        </div>
      </div>

      <div class="cashier-column">
        <div class="card cashier-card cashier-cart-card cashier-cart-card-shell">
          <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
              <div>
                <div class="cashier-panel-title">Header Order & Keranjang</div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="cashier_reset_order">Reset</button>
            </div>
            <div class="cashier-order-scroll">
              <div class="cashier-order-settings mb-3">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                  <div class="cashier-meta-box">
                    <div class="small text-muted">Order No</div>
                    <div class="fw-bold" id="cashier_order_no">Otomatis saat simpan</div>
                  </div>
                  <div class="cashier-chip warn"><i class="ri-bank-card-line"></i>DP masuk di payment</div>
                </div>
                <div class="mb-3">
                  <label class="form-label small text-muted mb-1">Cari Member</label>
                  <div class="cashier-search-wrap">
                    <input type="text" class="form-control" id="cashier_member_search" placeholder="Cari customer, member, atau no HP...">
                    <div class="cashier-search-dropdown d-none" id="cashier_member_result"></div>
                  </div>
                </div>
                <div class="cashier-member-box mb-3" id="cashier_member_selected">
                  <div class="cashier-member-empty">Walk in customer. Transaksi ini belum memakai member.</div>
                </div>
                <div class="cashier-header-grid">
                  <div>
                    <label class="form-label small text-muted mb-1">Service Type</label>
                    <select class="form-select" id="cashier_service_type">
                      <option value="DINE_IN">Dine In</option>
                      <option value="TAKE_AWAY">Take Away</option>
                      <option value="DELIVERY">Delivery</option>
                      <option value="PICKUP">Pick Up</option>
                    </select>
                  </div>
                  <div>
                    <label class="form-label small text-muted mb-1">Guest</label>
                    <input type="number" min="1" class="form-control" id="cashier_guest_count" value="1">
                  </div>
                  <div style="grid-column:1 / -1;">
                    <label class="form-label small text-muted mb-1">Catatan Order</label>
                    <input type="text" class="form-control" id="cashier_notes" placeholder="Meja, label, request kasir, atau catatan internal.">
                  </div>
                </div>
              </div>

              <div id="cashier_cart_list" class="cashier-cart-list"></div>
              <div id="cashier_cart_empty" class="cashier-empty">Belum ada item di keranjang. Pilih produk dari panel tengah.</div>
            </div>
            <hr>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div class="small text-muted">Grand Total</div>
              <div class="cashier-cart-total" id="cashier_grand_total">Rp 0</div>
            </div>
            <div class="cashier-mini-note mb-3" id="cashier_summary_info">Belum ada baris item</div>
            <div class="d-grid gap-2 cashier-cart-actions">
              <button type="button" class="btn btn-outline-dark" id="cashier_preview_reversal" disabled>Preview Void / Refund</button>
              <button type="button" class="btn btn-outline-primary" id="cashier_save_draft" <?php echo empty($activeSession) ? 'disabled' : ''; ?>>Simpan Draft</button>
              <button type="button" class="btn btn-primary" id="cashier_confirm_order" <?php echo empty($activeSession) ? 'disabled' : ''; ?>>Simpan Transaksi</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="cashierReversalModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius:24px;">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Preview Void / Refund POS</h5>
          <div class="small text-muted" id="cashier_reversal_meta">Order belum dipilih.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label small text-muted mb-1">Kebijakan Stok</label>
            <div class="form-check form-switch border rounded-4 px-3 py-2">
              <input class="form-check-input" type="checkbox" id="cashier_reversal_return" checked>
              <label class="form-check-label ms-2" for="cashier_reversal_return">Kembalikan ke stok untuk line yang belum diproses</label>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label small text-muted mb-1">Adjustment Mode</label>
            <select class="form-select" id="cashier_reversal_adjustment">
              <option value="NONE">NONE</option>
              <option value="AUTO_WASTE">AUTO_WASTE</option>
              <option value="AUTO_SPOIL">AUTO_SPOIL</option>
              <option value="AUTO_ADJUSTMENT">AUTO_ADJUSTMENT</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label small text-muted mb-1">Alasan</label>
            <textarea class="form-control" id="cashier_reversal_reason" rows="2" placeholder="Alasan void atau refund untuk audit transaksi"></textarea>
          </div>
        </div>
        <div id="cashier_reversal_lines"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-outline-danger" id="cashier_save_void">Simpan Void</button>
        <button type="button" class="btn btn-danger" id="cashier_save_refund">Simpan Refund</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="cashierCloseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius:24px;">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Tutup Kasir</h5>
          <div class="small text-muted">Masukkan kas aktual lalu sistem akan menghitung ringkasan shift.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label small text-muted mb-1">Kas Aktual</label>
            <input type="number" min="0" step="1000" class="form-control" id="cashier_close_actual_cash" placeholder="Mis. 850000">
          </div>
          <div class="col-12">
            <label class="form-label small text-muted mb-1">Catatan Penutupan</label>
            <textarea class="form-control" id="cashier_close_notes" rows="2" placeholder="Opsional"></textarea>
          </div>
          <div class="col-12">
            <div class="cashier-session-summary">
              <div class="small text-muted mb-2">Yang akan dihitung saat tutup kasir</div>
              <div class="d-flex flex-wrap gap-2">
                <span class="cashier-chip info">Penjualan cash</span>
                <span class="cashier-chip info">Penjualan non-cash</span>
                <span class="cashier-chip info">DP / deposit receipt</span>
                <span class="cashier-chip info">Refund</span>
                <span class="cashier-chip info">Void</span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-danger" id="cashier_submit_close">Tutup Shift</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="cashierExtraModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius:24px;">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Atur Extra Produk</h5>
          <div class="small text-muted" id="cashier_extra_meta">Pilih extra yang ingin disertakan ke line produk ini.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="cashier_extra_groups"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="cashier_save_extra">Simpan Extra</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const initialFilters = <?php echo json_encode($filters, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const activeSession = <?php echo json_encode($activeSession, JSON_INVALID_UTF8_SUBSTITUTE); ?> || null;
  const catalogFiltersData = <?php echo json_encode([
    'divisions' => $catalogDivisions,
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const siteBaseUrl = <?php echo json_encode(rtrim(base_url(), '/'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const recentState = {
    q: initialFilters.q || '',
    status: initialFilters.status || 'ALL',
    outlet_id: activeSession ? Number(activeSession.outlet_id || 0) : (parseInt(initialFilters.outlet_id || 0, 10) || 0),
    page: 1,
    limit: parseInt(initialFilters.limit || 20, 10) || 20
  };
  const catalogState = {
    q: '',
    division_id: 0,
    limit: 32
  };

  const order = {
    id: null,
    order_no: '',
    status: 'DRAFT',
    outlet_id: activeSession ? String(activeSession.outlet_id || '') : '',
    terminal_id: activeSession ? String(activeSession.terminal_id || '') : '',
    service_type: 'DINE_IN',
    guest_count: 1,
    member_id: null,
    member_no: '',
    member_name: '',
    member_mobile_phone: '',
    member_point_balance: 0,
    member_stamp_balance: 0,
    notes: '',
    lines: []
  };
  let searchMode = 'PRODUCT';
  let reversalPreview = null;
  let productSearchTimer = null;
  let memberSearchTimer = null;

  const reversalModalEl = document.getElementById('cashierReversalModal');
  const reversalModal = reversalModalEl && window.bootstrap ? new bootstrap.Modal(reversalModalEl) : null;
  const closeModalEl = document.getElementById('cashierCloseModal');
  const closeModal = closeModalEl && window.bootstrap ? new bootstrap.Modal(closeModalEl) : null;
  const extraModalEl = document.getElementById('cashierExtraModal');
  const extraModal = extraModalEl && window.bootstrap ? new bootstrap.Modal(extraModalEl) : null;
  const workspace = document.getElementById('cashier_workspace');
  const launchOutlet = document.getElementById('cashier_launch_outlet');
  const launchTerminal = document.getElementById('cashier_launch_terminal');
  const launchOpeningCash = document.getElementById('cashier_launch_opening_cash');
  const launchNotes = document.getElementById('cashier_launch_notes');
  const openButton = document.getElementById('cashier_open_btn');
  const closeButton = document.getElementById('cashier_close_btn');
  const outletSelect = document.getElementById('cashier_outlet_id');
  const terminalSelect = document.getElementById('cashier_terminal_id');
  const serviceType = document.getElementById('cashier_service_type');
  const guestCount = document.getElementById('cashier_guest_count');
  const notesInput = document.getElementById('cashier_notes');
  const memberSearchInput = document.getElementById('cashier_member_search');
  const memberResult = document.getElementById('cashier_member_result');
  const memberSelected = document.getElementById('cashier_member_selected');
  const productSearchInput = document.getElementById('cashier_product_search');
  const catalogResult = document.getElementById('cashier_catalog_result');
  const catalogHint = document.getElementById('cashier_catalog_hint');
  const catalogCount = document.getElementById('cashier_catalog_count');
  const divisionFilters = document.getElementById('cashier_division_filters');
  const cartList = document.getElementById('cashier_cart_list');
  const cartEmpty = document.getElementById('cashier_cart_empty');
  const reversalButton = document.getElementById('cashier_preview_reversal');
  const saveDraftButton = document.getElementById('cashier_save_draft');
  const confirmOrderButton = document.getElementById('cashier_confirm_order');
  const extraGroupsContainer = document.getElementById('cashier_extra_groups');
  const extraMeta = document.getElementById('cashier_extra_meta');
  const extraOptionCache = {};
  let activeExtraLineIndex = null;

  function escapeHtml(v) {
    return String(v ?? '').replace(/[&<>\"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[m]));
  }
  function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(v || 0));
  }
  function number(v, digits = 2) {
    return new Intl.NumberFormat('id-ID', { minimumFractionDigits: digits, maximumFractionDigits: digits }).format(Number(v || 0));
  }
  function normalizePhotoUrl(path) {
    const raw = String(path || '').trim();
    if (!raw) return '';
    if (/^https?:\/\//i.test(raw)) return raw;
    if (raw.startsWith('/')) return siteBaseUrl + raw;
    return siteBaseUrl + '/' + raw.replace(/^\.?\//, '');
  }
  function productFallbackLabel(row) {
    const basis = String(row.product_name || row.bundle_name || row.product_division_code || 'POS').trim();
    return basis.substring(0, 2).toUpperCase();
  }
  function productMediaHtml(row) {
    const photoUrl = normalizePhotoUrl(row.photo_path || '');
    if (photoUrl) {
      return `<div class="cashier-product-media"><img src="${escapeHtml(photoUrl)}" alt="${escapeHtml(row.product_name || row.bundle_name || 'Produk')}"></div>`;
    }
    return `<div class="cashier-product-media"><div class="cashier-product-fallback">${escapeHtml(productFallbackLabel(row))}</div></div>`;
  }
  function availabilityChip(status) {
    const s = String(status || '').toUpperCase();
    if (s === 'AVAILABLE' || s === 'OK') return '<span class="cashier-chip ok">Tersedia</span>';
    if (s === 'OUT' || s === 'EMPTY') return '<span class="cashier-chip out">Kosong</span>';
    return '<span class="cashier-chip warn">Perlu Cek</span>';
  }
  function currentDivisionLabel() {
    if (searchMode === 'BUNDLE') return 'Bundle aktif';
    if (catalogState.division_id <= 0) return 'Semua divisi';
    const row = (catalogFiltersData.divisions || []).find((item) => Number(item.id || 0) === Number(catalogState.division_id || 0));
    return row ? String(row.name || 'Semua divisi') : 'Semua divisi';
  }
  function renderDivisionFilters() {
    if (!divisionFilters) return;
    const rows = [{ id: 0, name: 'Semua', product_count: 0 }].concat(catalogFiltersData.divisions || []);
    const divisionButtons = rows.map((row) => `
      <button type="button" class="cashier-filter-pill${searchMode !== 'BUNDLE' && Number(row.id || 0) === Number(catalogState.division_id || 0) ? ' active' : ''}" data-division-id="${Number(row.id || 0)}">
        ${escapeHtml(row.name || 'Semua')}
        ${Number(row.product_count || 0) > 0 ? `<span class="opacity-75">${Number(row.product_count || 0)}</span>` : ''}
      </button>
    `).join('');
    const bundleButton = `
      <button type="button" class="cashier-filter-pill${searchMode === 'BUNDLE' ? ' active' : ''}" data-search-bundle="1">
        Bundle
      </button>
    `;
    divisionFilters.innerHTML = divisionButtons + bundleButton;
    divisionFilters.querySelectorAll('[data-division-id]').forEach((btn) => btn.addEventListener('click', () => {
      searchMode = 'PRODUCT';
      catalogState.division_id = Number(btn.dataset.divisionId || 0);
      renderDivisionFilters();
      loadCatalog().catch((e) => {
        catalogResult.innerHTML = `<div class="cashier-empty text-danger">${escapeHtml(e.message)}</div>`;
      });
    }));
    divisionFilters.querySelectorAll('[data-search-bundle]').forEach((btn) => btn.addEventListener('click', () => {
      searchMode = 'BUNDLE';
      renderDivisionFilters();
      loadCatalog().catch((e) => {
        catalogResult.innerHTML = `<div class="cashier-empty text-danger">${escapeHtml(e.message)}</div>`;
      });
    }));
  }

  async function getJson(url) {
    const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const t = await r.text();
    let j = null;
    try { j = JSON.parse(t); } catch (e) { throw new Error('Response backend bukan JSON. Cek warning/error PHP.'); }
    if (!r.ok || !j.ok) throw new Error(j.message || 'Gagal memuat data');
    return j;
  }

  async function postJson(url, payload) {
    const r = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(payload)
    });
    const t = await r.text();
    let j = null;
    try { j = JSON.parse(t); } catch (e) { throw new Error('Response save bukan JSON. Kemungkinan ada warning/error backend.'); }
    if (!r.ok || !j.ok) throw new Error(j.message || 'Gagal menyimpan data');
    return j;
  }

  function syncHeaderToOrder() {
    order.outlet_id = outletSelect ? (outletSelect.value || '') : order.outlet_id;
    order.terminal_id = terminalSelect ? (terminalSelect.value || '') : order.terminal_id;
    order.service_type = serviceType ? (serviceType.value || 'DINE_IN') : 'DINE_IN';
    order.guest_count = Math.max(1, Number(guestCount ? (guestCount.value || 1) : 1));
    order.notes = notesInput ? (notesInput.value || '') : '';
  }

  function filterLaunchTerminalOptions() {
    if (!launchOutlet || !launchTerminal) return;
    const outletId = Number(launchOutlet.value || 0);
    Array.from(launchTerminal.options).forEach((opt, idx) => {
      if (idx === 0) {
        opt.hidden = false;
        return;
      }
      const optionOutletId = Number(opt.dataset.outletId || 0);
      opt.hidden = !(outletId === 0 || optionOutletId === 0 || optionOutletId === outletId);
    });
    if (launchTerminal.selectedOptions.length && launchTerminal.selectedOptions[0].hidden) {
      launchTerminal.value = '';
    }
  }

  function updateActionState() {
    reversalButton.disabled = !order.id;
    document.getElementById('cashier_order_no').textContent = order.order_no || 'Otomatis saat simpan';
    const sessionReady = !!activeSession;
    const editableOrder = !order.id || ['DRAFT', 'PENDING'].includes(String(order.status || '').toUpperCase());
    if (saveDraftButton) saveDraftButton.disabled = !sessionReady || !editableOrder;
    if (confirmOrderButton) confirmOrderButton.disabled = !sessionReady || !editableOrder;
  }

  function renderMemberSelection() {
    if (!order.member_id) {
      memberSelected.innerHTML = '<div class="cashier-member-empty">Walk in customer. Transaksi ini belum memakai member.</div>';
      return;
    }
    memberSelected.innerHTML = `
      <div class="d-flex justify-content-between align-items-start gap-2">
        <div>
          <div class="cashier-member-title">${escapeHtml(order.member_name || '-')}</div>
          <div class="cashier-member-meta">${escapeHtml(order.member_mobile_phone || '-')}</div>
          <div class="cashier-member-meta mt-1">Point ${number(order.member_point_balance || 0, 0)} | Stamp ${number(order.member_stamp_balance || 0, 0)}</div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger" id="cashier_clear_member">Lepas</button>
      </div>
    `;
    const btn = document.getElementById('cashier_clear_member');
    if (btn) btn.addEventListener('click', clearMemberSelection);
  }

  function clearMemberSelection() {
    order.member_id = null;
    order.member_no = '';
    order.member_name = '';
    order.member_mobile_phone = '';
    order.member_point_balance = 0;
    order.member_stamp_balance = 0;
    if (memberSearchInput) memberSearchInput.value = '';
    if (memberResult) memberResult.classList.add('d-none');
    renderMemberSelection();
  }

  function pickMember(row) {
    order.member_id = Number(row.id || 0) || null;
    order.member_no = row.member_no || '';
    order.member_name = row.member_name || '';
    order.member_mobile_phone = row.mobile_phone || '';
    order.member_point_balance = Number(row.point_balance_cache || 0);
    order.member_stamp_balance = Number(row.stamp_balance_cache || 0);
    memberSearchInput.value = '';
    memberResult.classList.add('d-none');
    renderMemberSelection();
  }

  function recalcCart() {
    const total = order.lines.reduce((sum, line) => {
      const extraTotal = (Array.isArray(line.extras) ? line.extras : []).reduce((carry, extra) => carry + (Number(extra.qty || 0) * Number(extra.unit_price || 0)), 0);
      return sum + (Number(line.qty || 0) * Number(line.unit_price || 0)) + extraTotal;
    }, 0);
    document.getElementById('cashier_grand_total').textContent = money(total);
    document.getElementById('cashier_summary_info').textContent = order.lines.length
      ? `${order.lines.length} baris item | Guest ${order.guest_count || 1} | DP diproses saat payment`
      : 'Belum ada baris item';
  }

  function renderCart() {
    if (!order.lines.length) {
      cartList.innerHTML = '';
      cartEmpty.classList.remove('d-none');
      recalcCart();
      updateActionState();
      return;
    }
    cartEmpty.classList.add('d-none');
    cartList.innerHTML = order.lines.map((line, idx) => {
      const bundleChip = line.bundle_id ? `<span class="cashier-chip bundle mt-2"><i class="ri-gift-2-line"></i>${escapeHtml(line.bundle_name || 'Bundle')}</span>` : '';
      const extras = Array.isArray(line.extras) ? line.extras : [];
      const extraTotal = extras.reduce((carry, extra) => carry + (Number(extra.qty || 0) * Number(extra.unit_price || 0)), 0);
      const extraSummary = extras.length
        ? `<div class="mt-2">${extras.map((extra) => `<span class="cashier-extra-pill">${escapeHtml(extra.extra_name || '-')} x${number(extra.qty || 0, 0)}</span>`).join('')}</div>`
        : '<div class="cashier-mini-note mt-2">Belum ada extra.</div>';
      return `
        <div class="cashier-cart-item">
          <div class="d-flex justify-content-between align-items-start gap-2">
            <div>
              <div class="fw-semibold">${escapeHtml(line.product_name || '-')}</div>
              <div class="cashier-mini-note">${escapeHtml(line.product_code || '-')} | ${escapeHtml(line.product_division_name || '-')}</div>
              ${bundleChip}
              ${extraSummary}
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger cashier-remove-line" data-index="${idx}"><i class="ri-delete-bin-line"></i></button>
          </div>
          <div class="row g-2 mt-2">
            <div class="col-4">
              <label class="form-label small text-muted mb-1">Qty</label>
              <input type="number" min="0.01" step="0.01" class="form-control form-control-sm cashier-line-qty" data-index="${idx}" value="${Number(line.qty || 1)}">
            </div>
            <div class="col-8">
              <label class="form-label small text-muted mb-1">Catatan</label>
              <input type="text" class="form-control form-control-sm cashier-line-note" data-index="${idx}" value="${escapeHtml(line.notes || '')}" placeholder="Catatan line">
            </div>
            <div class="col-12">
              <button type="button" class="btn btn-sm btn-outline-warning cashier-edit-extra" data-index="${idx}">
                <i class="ri-add-circle-line me-1"></i>Atur Extra
              </button>
            </div>
          </div>
          <div class="d-flex justify-content-between align-items-center mt-3">
            <div>${availabilityChip(line.availability_status)}</div>
            <div class="fw-bold">${money((Number(line.qty || 0) * Number(line.unit_price || 0)) + extraTotal)}</div>
          </div>
        </div>
      `;
    }).join('');

    cartList.querySelectorAll('.cashier-line-qty').forEach((el) => el.addEventListener('input', () => {
      const idx = Number(el.dataset.index || 0);
      order.lines[idx].qty = Math.max(0, Number(el.value || 0));
      renderCart();
    }));
    cartList.querySelectorAll('.cashier-line-note').forEach((el) => el.addEventListener('input', () => {
      const idx = Number(el.dataset.index || 0);
      order.lines[idx].notes = el.value || '';
    }));
    cartList.querySelectorAll('.cashier-remove-line').forEach((el) => el.addEventListener('click', () => {
      const idx = Number(el.dataset.index || 0);
      const line = order.lines[idx];
      if (line && line.bundle_id) {
        order.lines = order.lines.filter((row) => Number(row.bundle_id || 0) !== Number(line.bundle_id || 0));
      } else {
        order.lines.splice(idx, 1);
      }
      renderCart();
    }));
    cartList.querySelectorAll('.cashier-edit-extra').forEach((el) => el.addEventListener('click', () => {
      openExtraChooser(Number(el.dataset.index || 0)).catch((e) => alert(e.message || 'Gagal memuat extra'));
    }));

    recalcCart();
    updateActionState();
  }

  async function fetchExtraGroups(productId) {
    const normalizedId = Number(productId || 0);
    if (normalizedId <= 0) return [];
    if (extraOptionCache[normalizedId]) {
      return extraOptionCache[normalizedId];
    }
    const params = new URLSearchParams();
    params.set('product_id', String(normalizedId));
    const json = await getJson('<?php echo site_url('pos/orders/draft/extra-options'); ?>?' + params.toString());
    extraOptionCache[normalizedId] = Array.isArray(json.groups) ? json.groups : [];
    return extraOptionCache[normalizedId];
  }

  function renderExtraGroups(groups, line) {
    if (!Array.isArray(groups) || !groups.length) {
      extraGroupsContainer.innerHTML = '<div class="cashier-empty">Produk ini belum punya extra aktif yang ditampilkan di kasir.</div>';
      return;
    }
    const selectedMap = {};
    (Array.isArray(line.extras) ? line.extras : []).forEach((extra) => {
      selectedMap[Number(extra.extra_id || 0)] = extra;
    });
    extraGroupsContainer.innerHTML = groups.map((group, groupIndex) => `
      <div class="cashier-extra-group${groupIndex > 0 ? ' mt-3' : ''}" data-group-id="${Number(group.extra_group_id || 0)}" data-min-select="${Number(group.min_select || 0)}" data-max-select="${Number(group.max_select || 0)}" data-required="${Number(group.is_required || 0)}">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
          <div>
            <div class="fw-semibold">${escapeHtml(group.group_name || '-')}</div>
            <div class="cashier-mini-note">${escapeHtml(group.group_code || '-')} | Min ${Number(group.min_select || 0)} | Max ${Number(group.max_select || 0) || 1}</div>
          </div>
          ${Number(group.is_required || 0) === 1 ? '<span class="cashier-chip warn">Wajib</span>' : '<span class="cashier-chip info">Opsional</span>'}
        </div>
        <div class="row g-2">
          ${(Array.isArray(group.items) ? group.items : []).map((item) => {
            const selected = selectedMap[Number(item.extra_id || 0)] || null;
            return `
              <div class="col-md-6">
                <div class="cashier-extra-item">
                  <div class="form-check mb-2">
                    <input class="form-check-input cashier-extra-check" type="checkbox" id="extra_${Number(item.extra_id || 0)}" data-extra-id="${Number(item.extra_id || 0)}" ${selected ? 'checked' : ''}>
                    <label class="form-check-label fw-semibold" for="extra_${Number(item.extra_id || 0)}">${escapeHtml(item.extra_name || '-')}</label>
                  </div>
                  <div class="cashier-mini-note mb-2">${escapeHtml(item.extra_code || '-')} | ${escapeHtml(item.extra_type || '-')}</div>
                  <div class="row g-2">
                    <div class="col-6">
                      <label class="form-label small text-muted mb-1">Qty</label>
                      <input type="number" min="0" step="1" class="form-control form-control-sm cashier-extra-qty" data-extra-id="${Number(item.extra_id || 0)}" value="${selected ? Number(selected.qty || 1) : Math.max(1, Number(line.qty || 1))}">
                    </div>
                    <div class="col-6">
                      <label class="form-label small text-muted mb-1">Harga</label>
                      <input type="number" min="0" step="0.01" class="form-control form-control-sm cashier-extra-price" data-extra-id="${Number(item.extra_id || 0)}" value="${selected ? Number(selected.unit_price || 0) : Number(item.selling_price || 0)}">
                    </div>
                  </div>
                </div>
              </div>
            `;
          }).join('')}
        </div>
      </div>
    `).join('');
  }

  async function openExtraChooser(index) {
    const line = order.lines[index];
    if (!line) {
      throw new Error('Line order tidak ditemukan.');
    }
    activeExtraLineIndex = index;
    extraMeta.textContent = `${line.product_name || '-'} | ${line.product_code || '-'} | Qty ${number(line.qty || 0, 0)}`;
    extraGroupsContainer.innerHTML = '<div class="cashier-empty">Memuat extra produk...</div>';
    extraModal.show();
    const groups = await fetchExtraGroups(line.product_id);
    renderExtraGroups(groups, line);
  }

  function collectExtraSelection() {
    if (activeExtraLineIndex == null || !order.lines[activeExtraLineIndex]) {
      throw new Error('Line extra tidak aktif.');
    }
    const line = order.lines[activeExtraLineIndex];
    const groups = extraOptionCache[Number(line.product_id || 0)] || [];
    const selection = [];
    groups.forEach((group) => {
      let selectedCount = 0;
      (Array.isArray(group.items) ? group.items : []).forEach((item) => {
        const extraId = Number(item.extra_id || 0);
        const checked = extraGroupsContainer.querySelector(`.cashier-extra-check[data-extra-id="${extraId}"]`);
        if (!checked || !checked.checked) {
          return;
        }
        selectedCount += 1;
        const qtyInput = extraGroupsContainer.querySelector(`.cashier-extra-qty[data-extra-id="${extraId}"]`);
        const priceInput = extraGroupsContainer.querySelector(`.cashier-extra-price[data-extra-id="${extraId}"]`);
        selection.push({
          extra_id: extraId,
          extra_name: item.extra_name || '',
          extra_type: item.extra_type || '',
          qty: Math.max(1, Number(qtyInput ? qtyInput.value || 1 : 1)),
          unit_price: Math.max(0, Number(priceInput ? priceInput.value || item.selling_price || 0 : item.selling_price || 0)),
          notes: ''
        });
      });
      const minSelect = Number(group.min_select || 0);
      const maxSelect = Number(group.max_select || 0);
      if (Number(group.is_required || 0) === 1 && selectedCount === 0) {
        throw new Error(`Group extra ${group.group_name || '-'} wajib dipilih minimal ${minSelect || 1} item.`);
      }
      if (minSelect > 0 && selectedCount > 0 && selectedCount < minSelect) {
        throw new Error(`Group extra ${group.group_name || '-'} minimal harus memilih ${minSelect} item.`);
      }
      if (maxSelect > 0 && selectedCount > maxSelect) {
        throw new Error(`Group extra ${group.group_name || '-'} maksimal ${maxSelect} item.`);
      }
    });
    order.lines[activeExtraLineIndex].extras = selection;
  }

  function renderCatalogRows(rows) {
    if (!rows.length) {
      if (catalogCount) catalogCount.textContent = '0 item';
      if (catalogHint) {
        catalogHint.textContent = searchMode === 'BUNDLE' ? 'Belum ada bundle yang cocok' : currentDivisionLabel();
      }
      catalogResult.innerHTML = '<div class="cashier-empty">Tidak ada item yang cocok dengan filter katalog ini.</div>';
      return;
    }
    if (catalogCount) catalogCount.textContent = `${rows.length} item`;
    if (catalogHint) {
      const modeLabel = searchMode === 'BUNDLE' ? 'Bundle aktif' : currentDivisionLabel();
      catalogHint.textContent = catalogState.q ? `${modeLabel} | hasil pencarian` : modeLabel;
    }
    catalogResult.innerHTML = `<div class="cashier-product-grid">${
      rows.map((row) => {
        const isBundle = searchMode === 'BUNDLE';
        const meta = isBundle
          ? `${escapeHtml(row.bundle_code || '-')} | ${escapeHtml(row.product_division_name || 'Campuran Divisi')} | ${Number(row.line_count || 0)} item`
          : `${escapeHtml(row.product_code || '-')} | ${escapeHtml(row.product_division_name || '-')} | ${escapeHtml(row.product_category_name || row.uom_code || '-')}`;
        const title = isBundle ? (row.bundle_name || '-') : (row.product_name || '-');
        return `
          <div class="cashier-product-card" data-row="${encodeURIComponent(JSON.stringify(row))}">
            ${productMediaHtml(row)}
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div class="cashier-product-name">${escapeHtml(title)}</div>
              ${availabilityChip(row.availability_status || 'CHECK')}
            </div>
            <div class="cashier-product-meta mt-2">${meta}</div>
            <div class="d-flex justify-content-between align-items-center mt-3">
              <div class="fw-bold">${money(row.selling_price || 0)}</div>
              <div class="cashier-mini-note">${isBundle ? 'Bundle' : 'Produk'}</div>
            </div>
          </div>
        `;
      }).join('')
    }</div>`;
    catalogResult.querySelectorAll('.cashier-product-card').forEach((item) => item.addEventListener('click', () => {
      const row = JSON.parse(decodeURIComponent(item.dataset.row));
      if (searchMode === 'BUNDLE') addBundleRows(row);
      else addProductRow(row);
    }));
  }

  function addProductRow(row) {
    const existing = order.lines.find((line) => Number(line.product_id) === Number(row.id) && !line.bundle_id);
    if (existing) {
      existing.qty = Number(existing.qty || 0) + 1;
    } else {
      order.lines.push({
        product_id: Number(row.id),
        bundle_id: null,
        bundle_name: '',
        product_code: row.product_code || '',
        product_name: row.product_name || '',
        product_division_name: row.product_division_name || '-',
        availability_status: row.availability_status || 'CHECK',
        qty: 1,
        unit_price: Number(row.selling_price || 0),
        hpp_live_snapshot: Number(row.availability_hpp_live_snapshot || row.hpp_live_cache || row.hpp_standard || 0),
        notes: '',
        extras: []
      });
    }
    productSearchInput.value = '';
    renderCart();
  }

  function addBundleRows(bundle) {
    const existing = order.lines.filter((line) => Number(line.bundle_id || 0) === Number(bundle.id || 0));
    if (existing.length) {
      existing.forEach((line) => {
        const source = (bundle.items || []).find((item) => Number(item.product_id || 0) === Number(line.product_id || 0));
        if (source) line.qty = Number(line.qty || 0) + Number(source.qty || 0);
      });
    } else {
      (bundle.items || []).forEach((item) => {
        order.lines.push({
          product_id: Number(item.product_id || 0),
          bundle_id: Number(bundle.id || 0),
          bundle_name: bundle.bundle_name || '',
          product_code: item.product_code || '',
          product_name: item.product_name || '',
          product_division_name: item.product_division_name || '-',
          availability_status: item.availability_status || bundle.availability_status || 'CHECK',
          qty: Number(item.qty || 0),
          unit_price: Number(item.unit_price || 0),
          hpp_live_snapshot: Number(item.hpp_live_snapshot || item.hpp_standard || 0),
          notes: bundle.bundle_name ? `[Bundle] ${bundle.bundle_name}` : '',
          extras: []
        });
      });
    }
    productSearchInput.value = '';
    renderCart();
  }

  async function loadCatalog() {
    const q = productSearchInput ? productSearchInput.value.trim() : '';
    catalogState.q = q;
    syncHeaderToOrder();
    if (!order.outlet_id) {
      catalogResult.innerHTML = '<div class="cashier-empty">Kasir belum aktif di outlet tertentu. Buka sesi kasir dulu.</div>';
      if (catalogCount) catalogCount.textContent = '0 item';
      return;
    }
    if (catalogHint) {
      catalogHint.textContent = searchMode === 'BUNDLE' ? 'Memuat bundle...' : 'Memuat katalog...';
    }
    catalogResult.innerHTML = '<div class="cashier-empty">Memuat katalog kasir...</div>';
    const endpoint = searchMode === 'BUNDLE'
      ? '<?php echo site_url('pos/cashier/bundles'); ?>'
      : '<?php echo site_url('pos/cashier/catalog'); ?>';
    const params = new URLSearchParams();
    params.set('q', q);
    params.set('outlet_id', order.outlet_id);
    params.set('division_id', String(catalogState.division_id || 0));
    params.set('limit', String(catalogState.limit || 32));
    const json = await getJson(endpoint + '?' + params.toString());
    renderCatalogRows(json.rows || []);
  }

  async function loadRecents() {
    const p = new URLSearchParams();
    p.set('q', recentState.q);
    p.set('status', recentState.status);
    p.set('outlet_id', recentState.outlet_id);
    p.set('page', recentState.page);
    p.set('limit', recentState.limit);
    const json = await getJson('<?php echo site_url('pos/orders/draft/data'); ?>?' + p.toString());
    const rows = json.rows || [];
    const list = document.getElementById('cashier_recent_list');
    const empty = document.getElementById('cashier_recent_empty');
    if (!rows.length) {
      list.innerHTML = '';
      empty.classList.remove('d-none');
      return;
    }
    empty.classList.add('d-none');
    list.innerHTML = rows.map((row) => `
      <div class="cashier-recent-item" data-id="${Number(row.id || 0)}">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div>
            <div class="fw-semibold">${escapeHtml(row.order_no || '-')}</div>
            <div class="cashier-mini-note">${escapeHtml(row.outlet_name || '-')} | ${escapeHtml(row.terminal_name || 'Tanpa Terminal')}</div>
            <div class="cashier-mini-note">${escapeHtml(row.member_name || 'Walk in')} | ${escapeHtml(row.employee_name || '-')}</div>
          </div>
          <div class="text-end">
            <div class="fw-bold">${money(row.grand_total || 0)}</div>
            <div class="cashier-mini-note">${escapeHtml(row.status || '-')}</div>
          </div>
        </div>
      </div>
    `).join('');
    list.querySelectorAll('.cashier-recent-item').forEach((el) => el.addEventListener('click', () => loadDraft(Number(el.dataset.id || 0))));
  }

  async function loadDraft(id) {
    const json = await getJson('<?php echo site_url('pos/orders/draft/load'); ?>/' + id);
    const header = json.header || {};
    const lines = Array.isArray(json.lines) ? json.lines : [];
    order.id = Number(header.id || 0) || null;
    order.order_no = header.order_no || '';
    order.status = header.status || 'DRAFT';
    order.outlet_id = String(header.outlet_id || order.outlet_id || '');
    order.terminal_id = String(header.terminal_id || order.terminal_id || '');
    order.service_type = header.service_type || 'DINE_IN';
    order.guest_count = Number(header.guest_count || 1);
    order.member_id = Number(header.member_id || 0) || null;
    order.member_no = header.member_no || '';
    order.member_name = header.member_name || '';
    order.member_mobile_phone = header.member_mobile_phone || '';
    order.member_point_balance = 0;
    order.member_stamp_balance = 0;
    order.notes = header.notes || '';
    order.lines = lines.map((line) => ({
      product_id: Number(line.product_id || 0),
      bundle_id: Number(line.bundle_id || 0) || null,
      bundle_name: line.bundle_name || '',
      product_code: line.product_code || '',
      product_name: line.product_name || '',
      product_division_name: line.product_division_name || '-',
      availability_status: 'CHECK',
      qty: Number(line.qty || 0),
      unit_price: Number(line.unit_price || 0),
      hpp_live_snapshot: Number(line.hpp_live_snapshot || line.hpp_standard_snapshot || 0),
      notes: line.notes || '',
      extras: Array.isArray(line.extras) ? line.extras.map((extra) => ({
        extra_id: Number(extra.extra_id || 0),
        extra_name: extra.extra_name || '',
        extra_type: extra.extra_type || '',
        qty: Number(extra.qty || 0),
        unit_price: Number(extra.unit_price || 0),
        notes: extra.notes || ''
      })) : []
    }));
    if (serviceType) serviceType.value = order.service_type || 'DINE_IN';
    if (guestCount) guestCount.value = order.guest_count || 1;
    if (notesInput) notesInput.value = order.notes || '';
    renderMemberSelection();
    renderCart();
  }

  async function saveDraft(silent = false) {
    syncHeaderToOrder();
    if (!activeSession) throw new Error('Kasir belum dibuka. Buka sesi kasir dulu.');
    const payload = {
      id: order.id,
      outlet_id: order.outlet_id,
      terminal_id: order.terminal_id,
      service_type: order.service_type,
      guest_count: order.guest_count,
      member_id: order.member_id,
      notes: order.notes,
      require_active_session: 1,
      lines: order.lines.map((line) => ({
        product_id: line.product_id,
        bundle_id: line.bundle_id,
        qty: line.qty,
        unit_price: line.unit_price,
        hpp_live_snapshot: line.hpp_live_snapshot,
        notes: line.notes || '',
        extras: (Array.isArray(line.extras) ? line.extras : []).map((extra) => ({
          extra_id: extra.extra_id,
          qty: extra.qty,
          unit_price: extra.unit_price,
          notes: extra.notes || ''
        }))
      }))
    };
    const json = await postJson('<?php echo site_url('pos/orders/draft/save'); ?>', payload);
    order.id = Number(json.id || 0) || order.id;
    order.order_no = json.order_no || order.order_no;
    order.status = 'DRAFT';
    updateActionState();
    if (!silent) alert('Draft order berhasil disimpan.');
    await loadRecents();
  }

  async function confirmDraft() {
    if (!order.lines.length) throw new Error('Tambahkan minimal 1 item sebelum confirm.');
    await saveDraft(true);
    const json = await postJson('<?php echo site_url('pos/orders/draft/confirm'); ?>/' + order.id, {});
    alert(`Order berhasil dikonfirmasi.\nCommit No: ${json.commit_no || '-'}\nResolved Line: ${Number(json.resolved_line_count || 0)}\nPosted Stock: ${Number(json.posted_stock_line_count || 0)}\nPrint Job: ${Number(json.print_job_count || 0)}`);
    await loadDraft(order.id);
    await loadRecents();
  }

  function resetOrder() {
    order.id = null;
    order.order_no = '';
    order.status = 'DRAFT';
    order.outlet_id = activeSession ? String(activeSession.outlet_id || '') : '';
    order.terminal_id = activeSession ? String(activeSession.terminal_id || '') : '';
    order.service_type = 'DINE_IN';
    order.guest_count = 1;
    order.member_id = null;
    order.member_no = '';
    order.member_name = '';
    order.member_mobile_phone = '';
    order.member_point_balance = 0;
    order.member_stamp_balance = 0;
    order.notes = '';
    order.lines = [];
    reversalPreview = null;
    if (serviceType) serviceType.value = 'DINE_IN';
    if (guestCount) guestCount.value = 1;
    if (notesInput) notesInput.value = '';
    clearMemberSelection();
    renderCart();
  }

  function renderReversalPreview(json) {
    reversalPreview = json;
    document.getElementById('cashier_reversal_meta').textContent = `${json.order?.header?.order_no || '-'} | ${json.order?.header?.status || '-'} | ${json.order?.header?.member_name || 'Walk in'}`;
    const lines = Array.isArray(json.order?.lines) ? json.order.lines : [];
    const container = document.getElementById('cashier_reversal_lines');
    container.innerHTML = lines.map((line) => {
      const processed = String(line.process_status || 'NOT_PROCESSED').toUpperCase();
      const isProcessed = processed !== 'NOT_PROCESSED';
      return `
        <div class="cashier-reversal-line">
          <div class="d-flex justify-content-between align-items-start gap-2">
            <div>
              <div class="fw-semibold">${escapeHtml(line.product_name || '-')}</div>
              <div class="cashier-mini-note">${escapeHtml(line.product_code || '-')} | Qty ${number(line.qty || 0, 2)} | Status ${escapeHtml(line.line_status || '-')}</div>
            </div>
            ${isProcessed ? '<span class="cashier-chip warn">Masuk Adjustment</span>' : '<span class="cashier-chip ok">Bisa Return Stock</span>'}
          </div>
          <div class="cashier-mini-note mt-2">
            Process Status: <strong>${escapeHtml(processed)}</strong>
            ${isProcessed ? ' | Sudah diproses, jadi stok tidak dikembalikan normal.' : ' | Belum diproses, stok boleh dikembalikan.'}
          </div>
        </div>
      `;
    }).join('');
  }

  function buildReversalPayload() {
    if (!reversalPreview || !reversalPreview.order || !reversalPreview.order.header) {
      throw new Error('Preview reversal belum dimuat.');
    }
    const returnToStock = document.getElementById('cashier_reversal_return').checked;
    const adjustmentMode = document.getElementById('cashier_reversal_adjustment').value || 'NONE';
    const reason = document.getElementById('cashier_reversal_reason').value || '';
    const lines = (reversalPreview.order.lines || []).map((line) => ({
      order_line_id: Number(line.id || 0),
      qty: Number(line.qty || 0),
      processed_state: String(line.process_status || 'NOT_PROCESSED').toUpperCase(),
      return_to_stock: returnToStock && String(line.process_status || '').toUpperCase() === 'NOT_PROCESSED',
      notes: reason
    })).filter((line) => line.order_line_id > 0 && line.qty > 0);
    return {
      order_id: Number(reversalPreview.order.header.id || 0),
      return_to_stock: returnToStock ? 1 : 0,
      adjustment_mode: adjustmentMode,
      reason,
      lines
    };
  }

  async function openReversalPreview() {
    if (!order.id) throw new Error('Simpan atau pilih order dulu sebelum preview void/refund.');
    const json = await getJson('<?php echo site_url('pos/orders/reversal-preview'); ?>/' + order.id);
    renderReversalPreview(json);
    if (reversalModal) reversalModal.show();
  }

  async function submitReversal(kind) {
    const payload = buildReversalPayload();
    const endpoint = kind === 'VOID'
      ? '<?php echo site_url('pos/orders/void/save'); ?>'
      : '<?php echo site_url('pos/orders/refund/save'); ?>';
    const json = await postJson(endpoint, payload);
    if (reversalModal) reversalModal.hide();
    alert(kind === 'VOID'
      ? `Void berhasil disimpan.\nNo Void: ${json.void_no || '-'}`
      : `Refund berhasil disimpan.\nNo Refund: ${json.refund_no || '-'}`);
    await loadDraft(order.id);
    await loadRecents();
  }

  async function openCashierSession() {
    if (!launchOutlet || !launchTerminal || !launchOpeningCash) return;
    const payload = {
      outlet_id: Number(launchOutlet.value || 0),
      terminal_id: Number(launchTerminal.value || 0),
      opening_cash: Number(launchOpeningCash.value || 0),
      notes: launchNotes ? (launchNotes.value || '') : ''
    };
    const json = await postJson('<?php echo site_url('pos/cashier/open'); ?>', payload);
    if (json.already_open) {
      alert('Sesi kasir ini sudah aktif. Layar akan dimuat ulang.');
    } else {
      alert('Kasir berhasil dibuka. Layar kasir akan dimuat ulang.');
    }
    window.location.reload();
  }

  async function closeCashierSession() {
    const actualCashInput = document.getElementById('cashier_close_actual_cash');
    const notesCloseInput = document.getElementById('cashier_close_notes');
    const payload = {
      actual_cash: Number(actualCashInput.value || 0),
      notes: notesCloseInput.value || ''
    };
    const json = await postJson('<?php echo site_url('pos/cashier/close'); ?>', payload);
    const summary = json.summary || {};
    if (closeModal) closeModal.hide();
    alert(
      'Kasir berhasil ditutup.\n\n'
      + 'Total Order: ' + Number(summary.total_order_count || 0)
      + '\nNet Sales: ' + money(summary.total_net_sales || 0)
      + '\nCash Sales: ' + money(summary.total_cash_sales || 0)
      + '\nNon Cash: ' + money(summary.total_non_cash_sales || 0)
      + '\nDP / Deposit: ' + money(summary.total_deposit_receipts || 0)
      + '\nRefund: ' + money(summary.total_refund || 0)
      + '\nVoid: ' + money(summary.total_void || 0)
      + '\nExpected Cash: ' + money(summary.expected_cash || 0)
      + '\nActual Cash: ' + money(summary.actual_cash || 0)
      + '\nVariance: ' + money(summary.variance_cash || 0)
    );
    window.location.reload();
  }

  if (launchOutlet) launchOutlet.addEventListener('change', filterLaunchTerminalOptions);
  if (openButton) openButton.addEventListener('click', async () => {
    try { await openCashierSession(); } catch (e) { alert(e.message); }
  });
  if (closeButton) closeButton.addEventListener('click', () => {
    const closeCashInput = document.getElementById('cashier_close_actual_cash');
    if (activeSession && closeCashInput && !closeCashInput.value) {
      closeCashInput.value = Number(activeSession.opening_cash || 0);
    }
    if (closeModal) closeModal.show();
  });
  const submitCloseButton = document.getElementById('cashier_submit_close');
  if (submitCloseButton) submitCloseButton.addEventListener('click', async () => {
    try { await closeCashierSession(); } catch (e) { alert(e.message); }
  });

  if (serviceType) serviceType.addEventListener('change', syncHeaderToOrder);
  if (guestCount) guestCount.addEventListener('input', () => { syncHeaderToOrder(); recalcCart(); });
  if (notesInput) notesInput.addEventListener('input', syncHeaderToOrder);

  if (memberSearchInput) memberSearchInput.addEventListener('input', () => {
    const q = memberSearchInput.value.trim();
    clearTimeout(memberSearchTimer);
    if (q.length < 2) {
      memberResult.classList.add('d-none');
      return;
    }
    memberSearchTimer = setTimeout(async () => {
      try {
        const json = await getJson('<?php echo site_url('pos/orders/draft/member-search'); ?>?q=' + encodeURIComponent(q));
        const rows = json.rows || [];
        if (!rows.length) {
          memberResult.innerHTML = '<div class="p-3 text-muted">Member tidak ditemukan.</div>';
          memberResult.classList.remove('d-none');
          return;
        }
        memberResult.innerHTML = rows.map((row) => `
          <div class="cashier-search-item" data-row="${encodeURIComponent(JSON.stringify(row))}">
            <div>
              <div class="fw-semibold">${escapeHtml(row.member_name || '-')}</div>
              <div class="cashier-mini-note">${escapeHtml(row.mobile_phone || '-')}</div>
            </div>
            <div class="text-end">
              <div class="fw-semibold">${escapeHtml(row.member_tier || '-')}</div>
              <div class="cashier-mini-note">P ${number(row.point_balance_cache || 0, 0)} | S ${number(row.stamp_balance_cache || 0, 0)}</div>
              <div class="cashier-mini-note">${escapeHtml(row.member_status || '-')}</div>
            </div>
          </div>
        `).join('');
        memberResult.classList.remove('d-none');
        memberResult.querySelectorAll('.cashier-search-item').forEach((item) => item.addEventListener('click', () => pickMember(JSON.parse(decodeURIComponent(item.dataset.row)))));
      } catch (e) {
        memberResult.innerHTML = `<div class="p-3 text-danger">${escapeHtml(e.message)}</div>`;
        memberResult.classList.remove('d-none');
      }
    }, 250);
  });

  if (productSearchInput) productSearchInput.addEventListener('input', () => {
    clearTimeout(productSearchTimer);
    productSearchTimer = setTimeout(async () => {
      try { await loadCatalog(); } catch (e) { catalogResult.innerHTML = `<div class="cashier-empty text-danger">${escapeHtml(e.message)}</div>`; }
    }, 250);
  });

  document.addEventListener('click', (e) => {
    if (memberResult && !memberResult.contains(e.target) && e.target !== memberSearchInput) {
      memberResult.classList.add('d-none');
    }
  });

  document.querySelectorAll('.cashier-status-tab').forEach((btn) => btn.addEventListener('click', () => {
    recentState.status = btn.dataset.status;
    document.querySelectorAll('.cashier-status-tab').forEach((rowBtn) => rowBtn.classList.toggle('active', rowBtn === btn));
    loadRecents().catch((e) => alert(e.message));
  }));
  const recentQ = document.getElementById('cashier_recent_q');
  const recentLimit = document.getElementById('cashier_recent_limit');
  if (recentQ) recentQ.addEventListener('input', (e) => { recentState.q = e.target.value; loadRecents().catch((err) => alert(err.message)); });
  if (recentLimit) recentLimit.addEventListener('change', (e) => { recentState.limit = Number(e.target.value || 20); loadRecents().catch((err) => alert(err.message)); });

  document.getElementById('cashier_reset_order').addEventListener('click', resetOrder);
  document.getElementById('cashier_save_extra').addEventListener('click', () => {
    try {
      collectExtraSelection();
      if (extraModal) extraModal.hide();
      renderCart();
    } catch (e) {
      alert(e.message || 'Gagal menyimpan extra');
    }
  });
  if (saveDraftButton) saveDraftButton.addEventListener('click', async () => {
    try { await saveDraft(false); } catch (e) { alert(e.message); }
  });
  if (confirmOrderButton) confirmOrderButton.addEventListener('click', async () => {
    try { await confirmDraft(); } catch (e) { alert(e.message); }
  });
  reversalButton.addEventListener('click', async () => {
    try { await openReversalPreview(); } catch (e) { alert(e.message); }
  });
  document.getElementById('cashier_save_void').addEventListener('click', async () => {
    try { await submitReversal('VOID'); } catch (e) { alert(e.message); }
  });
  document.getElementById('cashier_save_refund').addEventListener('click', async () => {
    try { await submitReversal('REFUND'); } catch (e) { alert(e.message); }
  });

  if (activeSession && workspace) {
    workspace.classList.remove('d-none');
  }
  filterLaunchTerminalOptions();
  renderDivisionFilters();
  renderMemberSelection();
  renderCart();
  syncHeaderToOrder();
  loadRecents().catch((e) => alert(e.message));
  if (activeSession) {
    loadCatalog().catch((e) => { catalogResult.innerHTML = `<div class="cashier-empty text-danger">${escapeHtml(e.message)}</div>`; });
  }
});
</script>

