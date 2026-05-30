<?php
$filters = is_array($filters ?? null) ? $filters : [];
$filterOptions = is_array($filter_options ?? null) ? $filter_options : [];
$outlets = is_array($filterOptions['outlets'] ?? null) ? $filterOptions['outlets'] : [];
$terminals = is_array($filterOptions['terminals'] ?? null) ? $filterOptions['terminals'] : [];
$cashierBootstrap = is_array($cashier_bootstrap ?? null) ? $cashier_bootstrap : [];
$activeSession = is_array($cashierBootstrap['active_session'] ?? null) ? $cashierBootstrap['active_session'] : null;
$salesChannels = is_array($cashierBootstrap['sales_channels'] ?? null) ? $cashierBootstrap['sales_channels'] : [];
$defaultSalesChannelId = !empty($cashierBootstrap['default_sales_channel_id']) ? (int)$cashierBootstrap['default_sales_channel_id'] : 0;
$catalogFilters = is_array($catalog_filters ?? null) ? $catalog_filters : [];
$catalogDivisions = is_array($catalogFilters['divisions'] ?? null) ? $catalogFilters['divisions'] : [];
?>

<style>
  .cashier-shell { display:grid; gap:1rem; height:calc(100vh - 106px); min-height:0; }
  .cashier-workbench {
    display:grid;
    grid-template-columns:minmax(280px, 324px) minmax(0, 1fr) minmax(400px, 500px);
    gap:1rem;
    align-items:start;
    height:100%;
    min-height:0;
    width:100%;
  }
  .cashier-column { display:grid; gap:1rem; min-width:0; min-height:0; height:100%; }
  .cashier-card {
    border:0; border-radius:26px; box-shadow:0 18px 38px rgba(58, 38, 30, .08);
    background:#fff;
    height:100%;
  }
  .cashier-panel-title { font-size:1rem; font-weight:900; color:#2f2628; }
  .cashier-panel-note { color:#7d6d67; font-size:.88rem; }
  .cashier-recent-status-bar {
    display:flex;
    gap:.4rem;
    flex-wrap:nowrap;
    overflow:hidden;
    width:100%;
  }
  .cashier-recent-status-bar .btn {
    flex:1 1 0;
    min-width:0;
    padding:.4rem .45rem;
    font-size:.76rem;
    border-radius:12px;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }
  .cashier-recent-status-bar .btn.active {
    background:#943f35;
    border-color:#943f35;
    color:#fff !important;
    box-shadow:0 10px 22px rgba(148,63,53,.16);
  }
  .cashier-search-mode .btn.active { background:#943f35; color:#fff; border-color:#943f35; }
  .cashier-search-result {
    min-height:0; flex:1; border:1px dashed rgba(191, 170, 157, .7); border-radius:22px; padding:.8rem;
    background:linear-gradient(135deg,#fff9f6 0%,#fff 100%);
    overflow:auto;
  }
  .cashier-product-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(132px,1fr)); gap:.68rem; }
  .cashier-product-card {
    border:1px solid rgba(225, 210, 199, .78); border-radius:18px; padding:.58rem; background:#fff;
    cursor:pointer; transition:transform .15s ease, box-shadow .15s ease, border-color .15s ease;
  }
  .cashier-product-card:hover {
    transform:translateY(-2px);
    border-color:rgba(169, 77, 56, .45);
    box-shadow:0 14px 30px rgba(97, 56, 43, .12);
  }
  .cashier-product-media {
    height:92px; border-radius:14px; overflow:hidden; background:linear-gradient(135deg,#f8e8db 0%, #f2d3bf 100%);
    display:flex; align-items:center; justify-content:center; margin-bottom:.58rem;
  }
  .cashier-product-media img { width:100%; height:100%; object-fit:cover; display:block; }
  .cashier-product-fallback {
    width:100%; height:100%; display:flex; align-items:center; justify-content:center;
    background:linear-gradient(135deg,#8f3d33 0%, #cf624a 100%); color:#fff; font-size:2rem; font-weight:900;
  }
  .cashier-product-name { font-weight:900; color:#33282a; font-size:.76rem; line-height:1.18; }
  .cashier-product-meta { font-size:.7rem; color:#7f6f67; line-height:1.35; }
  .cashier-chip {
    display:inline-flex; align-items:center; gap:.35rem; padding:.24rem .65rem; border-radius:999px;
    font-size:.68rem; font-weight:800; max-width:100%; white-space:nowrap;
  }
  .cashier-chip.ok { background:#e9f8ec; color:#1d7f45; }
  .cashier-chip.warn { background:#fff3de; color:#8d5a00; }
  .cashier-chip.out { background:#fde8e8; color:#b42318; }
  .cashier-chip.bundle { background:#fff0de; color:#9a4e0f; }
  .cashier-chip.info { background:#eef5ff; color:#2359a6; }
  .cashier-chip.stock { background:#f3f0ff; color:#5b43a8; }
  .cashier-member-box,
  .cashier-meta-box {
    border:1px solid rgba(225, 210, 199, .75); border-radius:18px; background:#fffdfb; padding:.9rem 1rem;
  }
  .cashier-meta-box { padding:.7rem .85rem; }
  .cashier-member-empty { font-size:.86rem; color:#85736b; }
  .cashier-member-title { font-weight:900; color:#33282a; font-size:.92rem; line-height:1.2; }
  .cashier-member-meta { font-size:.74rem; color:#7d6d67; line-height:1.25; }
  .cashier-search-wrap { position:relative; }
  .cashier-search-dropdown {
    position:absolute; top:calc(100% + .45rem); left:0; right:0; z-index:1050;
    background:#fff; border:1px solid rgba(201, 183, 168, .72); border-radius:18px;
    box-shadow:0 16px 36px rgba(61, 38, 27, .14); overflow:hidden;
  }
  .cashier-search-item { display:flex; justify-content:space-between; gap:.8rem; padding:.85rem 1rem; cursor:pointer; border-bottom:1px solid rgba(232,220,210,.9); }
  .cashier-search-item:last-child { border-bottom:0; }
  .cashier-search-item:hover { background:#fff7f2; }
  .cashier-cart-card { position:static; }
  .cashier-cart-list {
    display:grid; gap:.65rem;
    max-height:none;
    overflow:visible;
    padding-right:0;
  }
  .cashier-cart-item {
    border:1px solid rgba(225,210,199,.78); border-radius:16px; padding:.6rem .66rem;
    background:linear-gradient(135deg,#fffaf7 0%,#fff 100%);
  }
  .cashier-cart-item-head {
    display:flex; justify-content:space-between; align-items:flex-start; gap:.6rem;
  }
  .cashier-cart-item-title { font-weight:800; color:#2f2628; font-size:.88rem; line-height:1.12; }
  .cashier-cart-item-sub { font-size:.64rem; color:#8a7a72; line-height:1.08; }
  .cashier-cart-item-actions {
    display:flex; gap:.4rem; align-items:center; flex-wrap:wrap;
  }
  .cashier-qty-stepper {
    display:inline-grid;
    grid-template-columns:30px 34px 30px;
    align-items:center;
    border:1px solid rgba(225,210,199,.9);
    border-radius:10px;
    overflow:hidden;
    background:#fff;
    width:100%;
  }
  .cashier-qty-stepper button {
    border:0;
    background:#fff7f2;
    color:#7d5146;
    font-weight:900;
    min-height:30px;
    padding:0;
  }
  .cashier-qty-stepper input {
    border:0;
    text-align:center;
    min-height:30px;
    font-weight:800;
    background:#fff;
    width:100%;
    min-width:0;
    appearance:textfield;
    -moz-appearance:textfield;
  }
  .cashier-qty-stepper input::-webkit-outer-spin-button,
  .cashier-qty-stepper input::-webkit-inner-spin-button {
    -webkit-appearance:none;
    margin:0;
  }
  .cashier-inline-note {
    min-height:30px;
    font-size:.7rem;
    width:100%;
    padding:.28rem .42rem;
  }
  .cashier-cart-editor-row {
    display:grid;
    grid-template-columns:96px minmax(0, 1fr);
    gap:.42rem;
    align-items:start;
  }
  .cashier-cart-editor-row > div { min-width:0; }
  .cashier-cart-editor-row .form-label {
    display:block;
    min-height:16px;
    line-height:1.1;
  }
  .cashier-cart-extras {
    margin-top:.24rem;
    display:flex;
    gap:.22rem;
    flex-wrap:wrap;
  }
  .cashier-cart-extras .cashier-extra-pill {
    font-size:.62rem;
    padding:.14rem .42rem;
  }
  .cashier-cart-total { font-size:1.72rem; font-weight:900; color:#2e2527; }
  .cashier-cart-actions .btn { min-height:46px; }
  .cashier-mini-note { color:#85736b; font-size:.78rem; }
  .cashier-recent-list { display:grid; gap:.75rem; }
  .cashier-recent-item {
    border:1px solid rgba(225,210,199,.78); border-radius:18px; padding:.82rem .9rem; cursor:pointer; background:#fff;
    transition:border-color .15s ease, box-shadow .15s ease, background .15s ease;
  }
  .cashier-recent-item:hover { background:#fff7f2; }
  .cashier-recent-item.active {
    border-color:rgba(148,63,53,.58);
    background:#fff5ef;
    box-shadow:0 12px 28px rgba(148,63,53,.12);
  }
  .cashier-status-badges {
    display:flex;
    flex-wrap:wrap;
    justify-content:flex-end;
    gap:.32rem;
    margin-top:.32rem;
  }
  .cashier-status-chip {
    display:inline-flex;
    align-items:center;
    gap:.28rem;
    padding:.18rem .55rem;
    border-radius:999px;
    font-size:.66rem;
    font-weight:900;
    letter-spacing:.01em;
    white-space:nowrap;
  }
  .cashier-status-chip.order-draft { background:#fff3cd; color:#8a5700; }
  .cashier-status-chip.order-confirmed { background:#dcfce7; color:#166534; }
  .cashier-status-chip.commit-queued { background:#e0f2fe; color:#075985; }
  .cashier-status-chip.commit-processing { background:#ede9fe; color:#5b21b6; }
  .cashier-status-chip.commit-posted { background:#dcfce7; color:#166534; }
  .cashier-status-chip.commit-failed { background:#fee2e2; color:#b91c1c; }
  .cashier-status-chip.commit-reversed { background:#f1f5f9; color:#334155; }
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
  .cashier-toast-stack {
    position:fixed;
    right:18px;
    bottom:18px;
    z-index:1085;
    display:grid;
    gap:.65rem;
    width:min(360px, calc(100vw - 24px));
  }
  .cashier-toast {
    border:1px solid rgba(225,210,199,.78);
    border-left:4px solid #b85c48;
    border-radius:16px;
    background:rgba(255,255,255,.98);
    box-shadow:0 20px 42px rgba(58, 38, 30, .14);
    padding:.82rem .95rem;
    display:grid;
    gap:.22rem;
    transform:translateY(0);
    opacity:1;
    transition:opacity .22s ease, transform .22s ease;
  }
  .cashier-toast.is-hiding {
    opacity:0;
    transform:translateY(8px);
  }
  .cashier-toast-title {
    font-size:.78rem;
    font-weight:900;
    color:#2f2628;
    text-transform:uppercase;
    letter-spacing:.04em;
  }
  .cashier-toast-body {
    font-size:.8rem;
    line-height:1.45;
    color:#6d5b54;
  }
  .cashier-toast.success { border-left-color:#1d7f45; }
  .cashier-toast.warn { border-left-color:#b66919; }
  .cashier-toast.error { border-left-color:#b42318; }
  .cashier-saving-overlay {
    position:fixed;
    inset:0;
    z-index:1088;
    display:none;
    align-items:center;
    justify-content:center;
    background:rgba(255,251,248,.42);
    backdrop-filter:blur(2px);
  }
  .cashier-saving-overlay.active {
    display:flex;
  }
  .cashier-saving-card {
    min-width:260px;
    max-width:min(92vw, 360px);
    border:1px solid rgba(225,210,199,.82);
    border-radius:22px;
    background:rgba(255,255,255,.98);
    box-shadow:0 24px 54px rgba(58,38,30,.16);
    padding:1rem 1.15rem;
    display:grid;
    gap:.35rem;
    text-align:center;
  }
  .cashier-saving-spinner {
    width:40px;
    height:40px;
    margin:0 auto .2rem;
    border:3px solid rgba(184,92,72,.16);
    border-top-color:#b85c48;
    border-radius:50%;
    animation:cashier-spin .8s linear infinite;
  }
  .cashier-saving-title {
    font-size:.9rem;
    font-weight:900;
    color:#2f2628;
  }
  .cashier-saving-body {
    font-size:.8rem;
    color:#7b675f;
    line-height:1.45;
  }
  @keyframes cashier-spin {
    from { transform:rotate(0deg); }
    to { transform:rotate(360deg); }
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
    padding-right:.18rem;
    flex:1;
    display:flex;
    flex-direction:column;
  }
  .cashier-header-grid {
    display:grid;
    grid-template-columns:minmax(108px, 1.05fr) minmax(72px, 78px) minmax(86px, 108px);
    gap:.5rem;
    align-items:start;
  }
  .cashier-header-grid .cashier-order-notes-wrap {
    grid-column:1 / -1;
  }
  .cashier-order-settings {
    border:1px solid rgba(225,210,199,.78);
    border-radius:18px;
    padding:.8rem;
    background:#fffdfb;
  }
  .cashier-order-settings .form-control,
  .cashier-order-settings .form-select {
    min-height:38px;
    font-size:.76rem;
    padding:.42rem .68rem;
  }
  .cashier-order-settings .form-label {
    font-size:.72rem;
  }
  .cashier-cart-footer {
    position:sticky;
    bottom:0;
    background:linear-gradient(180deg, rgba(255,255,255,.9) 0%, #fff 22%);
    padding-top:.72rem;
    margin-top:.72rem;
    border-top:1px solid rgba(228,216,207,.9);
  }
  .cashier-cart-footer .btn {
    min-height:42px;
    font-size:.87rem;
  }
  .cashier-cart-summary-line {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:.6rem;
  }
  .cashier-search-card,
  .cashier-header-card,
  .cashier-recent-card,
  .cashier-cart-card-shell {
    min-height:0;
  }
  .cashier-search-card .card-body,
  .cashier-recent-card .card-body,
  .cashier-cart-card-shell .card-body {
    display:flex;
    flex-direction:column;
    min-height:0;
  }
  #cashier_cart_empty.cashier-empty {
    flex:1;
    display:flex;
    align-items:center;
    justify-content:center;
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
  .cashier-cart-extra-summary {
    font-size:.74rem;
    color:#8e5d1d;
    margin-top:.2rem;
  }
  .cashier-extra-groups-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(250px, 1fr));
    gap:.75rem;
  }
  .cashier-extra-group {
    border:1px solid rgba(224,209,198,.75); border-radius:16px; padding:.75rem; background:#fffdfb;
  }
  .cashier-extra-group-head {
    display:flex; justify-content:space-between; align-items:flex-start; gap:.6rem; margin-bottom:.65rem;
  }
  .cashier-extra-group-title { font-weight:900; color:#34292b; font-size:.92rem; line-height:1.25; }
  .cashier-extra-group-meta { font-size:.72rem; color:#8a7a72; margin-top:.12rem; }
  .cashier-extra-group-badges {
    display:flex;
    gap:.35rem;
    flex-wrap:wrap;
    justify-content:flex-end;
  }
  .cashier-extra-item-list {
    display:grid;
    gap:.42rem;
  }
  .cashier-extra-item {
    border:1px solid rgba(234,223,214,.88); border-radius:14px; padding:.62rem .72rem; background:#fff;
    transition:border-color .15s ease, box-shadow .15s ease, background .15s ease;
  }
  .cashier-extra-item.selected {
    border-color:rgba(169, 77, 56, .55);
    box-shadow:0 10px 24px rgba(143,61,51,.1);
    background:#fff8f4;
  }
  .cashier-extra-item-row {
    display:grid;
    grid-template-columns:minmax(0, 1fr) 84px;
    gap:.55rem;
    align-items:start;
  }
  .cashier-extra-price-input {
    min-height:34px;
  }
  .cashier-extra-qty-note {
    font-size:.72rem;
    font-weight:700;
    color:#8e5d1d;
    background:#fff8eb;
    border:1px solid rgba(233, 205, 157, .9);
    border-radius:999px;
    padding:.18rem .55rem;
  }
  .cashier-extra-modal .modal-dialog {
    max-width:920px;
  }
  .cashier-extra-modal .modal-body {
    max-height:72vh;
    overflow:auto;
    padding-bottom:.4rem;
  }
  .cashier-extra-modal .modal-footer {
    position:sticky;
    bottom:0;
    background:#fff;
    border-top:1px solid rgba(225,210,199,.8);
    box-shadow:0 -10px 24px rgba(58,38,30,.06);
  }
  .cashier-recent-scroll {
    min-height:0;
    flex:1;
    overflow:auto;
    padding-right:.18rem;
  }
  @media (max-width: 991.98px) {
    .cashier-shell { height:auto; }
    .cashier-workbench { grid-template-columns:1fr; height:auto; }
    .cashier-cart-card { position:static; }
    .cashier-startup-overlay { min-height:auto; }
    .cashier-header-grid { grid-template-columns:1fr; }
    .cashier-product-grid { grid-template-columns:repeat(auto-fill,minmax(144px,1fr)); }
    .cashier-cart-editor-row { grid-template-columns:1fr; }
    .cashier-review-summary-grid { grid-template-columns:1fr 1fr; }
  }
  .cashier-catalog-chip-row {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:.45rem;
    margin-top:.4rem;
    flex-wrap:wrap;
  }
  .cashier-product-price {
    font-size:.84rem;
    font-weight:900;
    color:#2f2628;
  }
  .cashier-member-toolbar {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:.55rem;
  }
  .cashier-member-toolbar .btn {
    min-height:32px;
    font-size:.78rem;
    padding:.3rem .75rem;
  }
  .cashier-cart-line-bottom {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:.45rem;
    flex-wrap:nowrap;
    margin-top:.4rem;
  }
  .cashier-cart-line-left {
    display:flex;
    align-items:center;
    gap:.34rem;
    flex-wrap:wrap;
    min-width:0;
  }
  .cashier-cart-line-left .cashier-chip {
    font-size:.62rem;
    padding:.14rem .42rem;
  }
  .cashier-cart-line-left .btn {
    padding:.2rem .54rem;
    font-size:.72rem;
  }
  .cashier-cart-line-bottom .fw-bold {
    flex:0 0 auto;
    font-size:.88rem;
  }
  .cashier-cart-extra-count {
    font-size:.62rem;
    color:#875f2a;
    background:#fff7ea;
    border:1px solid rgba(239, 198, 112, .8);
    border-radius:999px;
    padding:.12rem .44rem;
    font-weight:800;
  }
  .cashier-recent-meta {
    display:grid;
    gap:.18rem;
    min-width:0;
  }
  .cashier-recent-order-no {
    font-weight:800;
    color:#2f2628;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }
  .cashier-recent-customer {
    font-size:.74rem;
    color:#4c3d3c;
    font-weight:700;
    line-height:1.2;
  }
  .cashier-recent-table {
    font-size:.72rem;
    color:#8a7a72;
    line-height:1.15;
  }
  .cashier-extra-modal .form-control,
  .cashier-extra-modal .form-select {
    min-height:38px;
    font-size:.86rem;
    padding:.45rem .68rem;
  }
  .cashier-extra-modal .form-label {
    font-size:.72rem;
  }
  .cashier-extra-modal .modal-title {
    font-size:1.1rem;
  }
  .cashier-extra-modal .modal-header {
    padding:.95rem 1rem .72rem;
  }
  .cashier-extra-modal .modal-footer .btn {
    min-height:40px;
    padding:.45rem 1rem;
    font-size:.9rem;
  }
  .cashier-extra-modal .cashier-order-settings {
    padding:.72rem;
    border-radius:16px;
  }
  .cashier-review-list {
    display:grid;
    gap:.7rem;
  }
  .cashier-review-item {
    border:1px solid rgba(225,210,199,.78);
    border-radius:16px;
    padding:.78rem .85rem;
    background:#fffaf7;
  }
  .cashier-review-extra {
    display:flex;
    gap:.32rem;
    flex-wrap:wrap;
    margin-top:.3rem;
  }
  .cashier-review-modal .modal-body {
    max-height:62vh;
    overflow:auto;
  }
  .cashier-review-modal .modal-footer {
    position:sticky;
    bottom:0;
    background:#fff;
    border-top:1px solid rgba(225,210,199,.8);
  }
  .cashier-review-summary {
    border:1px solid rgba(225,210,199,.78);
    border-radius:18px;
    background:#fffdfb;
    padding:.72rem .82rem;
  }
  .cashier-review-summary-grid {
    display:grid;
    grid-template-columns:repeat(5, minmax(0, 1fr));
    gap:.52rem;
  }
  .cashier-review-kpi-label {
    font-size:.68rem;
    text-transform:uppercase;
    letter-spacing:.04em;
    color:#8a7a72;
    margin-bottom:.16rem;
  }
  .cashier-review-kpi-value {
    font-size:.78rem;
    font-weight:800;
    color:#33282a;
    line-height:1.25;
  }
  .cashier-info-modal .modal-dialog {
    max-width:480px;
  }
  .cashier-info-modal .modal-content {
    border-radius:22px;
  }
  .cashier-info-modal .modal-header,
  .cashier-info-modal .modal-footer {
    padding:.85rem 1rem;
  }
  .cashier-info-modal .modal-body {
    padding:.95rem 1rem .8rem;
  }
  .cashier-info-text {
    font-size:.92rem;
    color:#423536;
    line-height:1.45;
    white-space:pre-line;
  }
  .cashier-review-line-meta {
    display:flex;
    gap:.45rem;
    flex-wrap:wrap;
    margin-top:.2rem;
  }
  .cashier-review-line-meta .cashier-chip {
    font-size:.65rem;
    padding:.2rem .45rem;
  }
  .cashier-action-bar {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:.55rem;
  }
  .cashier-action-bar .cashier-action-wide {
    grid-column:1 / -1;
  }
  .cashier-recent-footer {
    position:sticky;
    bottom:0;
    padding-top:.72rem;
    margin-top:.72rem;
    background:linear-gradient(180deg, rgba(255,255,255,.9) 0%, #fff 22%);
    border-top:1px solid rgba(228,216,207,.9);
  }
  .cashier-product-card .cashier-chip {
    font-size:.63rem;
    padding:.2rem .5rem;
  }
  .cashier-member-box {
    padding:.72rem .82rem;
  }
  .cashier-member-empty,
  .cashier-member-meta,
  .cashier-cart-item-sub {
    font-size:.76rem;
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
        <div class="card cashier-card cashier-recent-card">
          <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
              <div>
                <div class="cashier-panel-title">Order Aktif Sesi Ini</div>
              </div>
              <div class="cashier-recent-status-bar">
                <button type="button" class="btn btn-sm btn-outline-primary cashier-status-tab" data-status="DRAFT">Draft</button>
                <button type="button" class="btn btn-sm btn-outline-primary cashier-status-tab" data-status="CONFIRMED">Confirmed</button>
                <button type="button" class="btn btn-sm btn-outline-primary cashier-status-tab active" data-status="ALL">Semua</button>
              </div>
            </div>
            <div class="row g-2 mb-3">
              <div class="col-12">
                <input id="cashier_recent_q" class="form-control" placeholder="Cari kode transaksi / customer / meja">
              </div>
              <div class="col-12">
                <select id="cashier_recent_limit" class="form-select">
                  <option value="10">10</option>
                  <option value="20" selected>20</option>
                  <option value="50">50</option>
                </select>
              </div>
            </div>
            <div class="cashier-recent-scroll">
              <div id="cashier_recent_list" class="cashier-recent-list"></div>
              <div id="cashier_recent_empty" class="cashier-empty d-none">Belum ada order pada filter ini.</div>
            </div>
            <div class="cashier-recent-footer">
              <button type="button" class="btn btn-outline-danger w-100" id="cashier_close_btn">Tutup Kasir</button>
            </div>
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
                    <label class="form-label small text-muted mb-1">Sales Channel</label>
                    <select class="form-select" id="cashier_sales_channel">
                      <?php if (empty($salesChannels)): ?>
                        <option value="">Walk In</option>
                      <?php else: ?>
                        <?php foreach ($salesChannels as $channel): ?>
                          <option
                            value="<?php echo (int)($channel['id'] ?? 0); ?>"
                            data-service-default="<?php echo html_escape((string)($channel['service_type_default'] ?? 'DINE_IN')); ?>"
                            data-allowed-types="<?php echo html_escape(implode(',', (array)($channel['allowed_service_type_list'] ?? []))); ?>"
                            <?php echo ((int)($channel['id'] ?? 0) === $defaultSalesChannelId) ? 'selected' : ''; ?>
                          >
                            <?php echo html_escape((string)($channel['channel_name'] ?? '-')); ?>
                          </option>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </select>
                  </div>
                  <div>
                    <label class="form-label small text-muted mb-1">Guest</label>
                    <input type="number" min="1" step="1" class="form-control" id="cashier_guest_count" value="1">
                  </div>
                  <div>
                    <label class="form-label small text-muted mb-1">Nomor Meja</label>
                    <input type="text" class="form-control" id="cashier_table_no" placeholder="A-01">
                  </div>
                  <div class="cashier-order-notes-wrap">
                    <label class="form-label small text-muted mb-1">Catatan Order</label>
                    <input type="text" class="form-control" id="cashier_notes" placeholder="Meja, label, request kasir">
                  </div>
                  <div class="d-none">
                    <label class="form-label small text-muted mb-1">Service Type</label>
                    <select class="form-select" id="cashier_service_type">
                      <option value="DINE_IN">Dine In</option>
                      <option value="TAKE_AWAY">Take Away</option>
                      <option value="DELIVERY">Delivery</option>
                      <option value="PICKUP">Pick Up</option>
                    </select>
                  </div>
                </div>
              </div>

              <div id="cashier_cart_list" class="cashier-cart-list"></div>
              <div id="cashier_cart_empty" class="cashier-empty">Belum ada item di keranjang. Pilih produk dari panel tengah.</div>
            </div>
            <div class="cashier-cart-footer">
              <div class="cashier-cart-summary-line mb-2">
                <div class="small text-muted">Grand Total</div>
                <div class="cashier-cart-total" id="cashier_grand_total">Rp 0</div>
              </div>
              <div class="cashier-mini-note mb-3" id="cashier_summary_info">Belum ada baris item</div>
              <div class="cashier-action-bar cashier-cart-actions">
                <button type="button" class="btn btn-outline-dark cashier-action-wide" id="cashier_preview_reversal" disabled>Preview Void / Refund</button>
                <button type="button" class="btn btn-outline-primary" id="cashier_save_draft" <?php echo empty($activeSession) ? 'disabled' : ''; ?>>Simpan Draft</button>
                <button type="button" class="btn btn-primary" id="cashier_confirm_order" <?php echo empty($activeSession) ? 'disabled' : ''; ?>>Simpan Transaksi</button>
              </div>
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

<div class="modal fade cashier-extra-modal" id="cashierExtraModal" tabindex="-1" aria-hidden="true">
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
        <div class="cashier-order-settings mb-3">
          <div class="row g-3 align-items-end">
            <div class="col-lg-6">
              <label class="form-label small text-muted mb-1">Produk</label>
              <input type="text" class="form-control cashier-readonly" id="cashier_extra_product_name" readonly>
            </div>
            <div class="col-lg-2 col-md-3">
              <label class="form-label small text-muted mb-1">Qty</label>
              <input type="number" min="1" step="1" class="form-control" id="cashier_extra_line_qty" value="1">
            </div>
            <div class="col-lg-2 col-md-3">
              <label class="form-label small text-muted mb-1">Harga Dasar</label>
              <input type="text" class="form-control cashier-readonly" id="cashier_extra_base_price" readonly>
            </div>
            <div class="col-lg-2 col-12">
              <label class="form-label small text-muted mb-1">Ringkas</label>
              <div class="cashier-chip info w-100 justify-content-center" id="cashier_extra_group_count">0 group</div>
            </div>
            <div class="col-12">
              <label class="form-label small text-muted mb-1">Catatan Line</label>
              <input type="text" class="form-control" id="cashier_extra_line_note" placeholder="Contoh: less ice, meja pojok, tanpa sedotan">
            </div>
          </div>
        </div>
        <div id="cashier_extra_groups"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="cashier_save_extra">Simpan ke Keranjang</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade cashier-review-modal" id="cashierReviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius:24px;">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1" id="cashier_review_title">Review Order</h5>
          <div class="small text-muted" id="cashier_review_meta">Periksa order sebelum disimpan.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="cashier-review-summary mb-3">
          <div class="cashier-review-summary-grid">
            <div><div class="cashier-review-kpi-label">Channel</div><div class="cashier-review-kpi-value" id="cashier_review_channel">-</div></div>
            <div><div class="cashier-review-kpi-label">Guest</div><div class="cashier-review-kpi-value" id="cashier_review_guest">1</div></div>
            <div><div class="cashier-review-kpi-label">Meja</div><div class="cashier-review-kpi-value" id="cashier_review_table_no">-</div></div>
            <div><div class="cashier-review-kpi-label">Customer</div><div class="cashier-review-kpi-value" id="cashier_review_member">Walk in</div></div>
            <div><div class="cashier-review-kpi-label">Catatan</div><div class="cashier-review-kpi-value" id="cashier_review_notes">-</div></div>
          </div>
        </div>
        <div id="cashier_review_list" class="cashier-review-list"></div>
        <div class="cashier-review-summary mt-3">
          <div class="d-flex justify-content-between align-items-center gap-3">
            <div>
              <div class="small text-muted">Grand Total</div>
              <div class="fw-bold fs-4" id="cashier_review_total">Rp 0</div>
            </div>
            <div class="text-end small text-muted" id="cashier_review_hint">Draft akan disimpan setelah review.</div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="cashier_review_submit">Simpan</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade cashier-info-modal" id="cashierInfoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header">
        <h5 class="modal-title mb-0" id="cashier_info_title">Informasi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="cashier-info-text" id="cashier_info_message">Transaksi berhasil diproses.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<div class="cashier-toast-stack" id="cashier_toast_stack" aria-live="polite" aria-atomic="true"></div>
<div class="cashier-saving-overlay" id="cashier_saving_overlay" aria-hidden="true">
  <div class="cashier-saving-card">
    <div class="cashier-saving-spinner" aria-hidden="true"></div>
    <div class="cashier-saving-title" id="cashier_saving_title">Menyimpan Transaksi</div>
    <div class="cashier-saving-body" id="cashier_saving_body">Tunggu sebentar, pesanan sedang diproses.</div>
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
    sales_channel_id: <?php echo json_encode($defaultSalesChannelId > 0 ? (string)$defaultSalesChannelId : ''); ?>,
    service_type: 'DINE_IN',
    guest_count: 1,
    table_no: '',
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
  let selectedRecentOrderId = null;
  let draftSaveInFlight = false;
  let confirmInFlight = false;

  const reversalModalEl = document.getElementById('cashierReversalModal');
  const reversalModal = reversalModalEl && window.bootstrap ? new bootstrap.Modal(reversalModalEl) : null;
  const closeModalEl = document.getElementById('cashierCloseModal');
  const closeModal = closeModalEl && window.bootstrap ? new bootstrap.Modal(closeModalEl) : null;
  const extraModalEl = document.getElementById('cashierExtraModal');
  const extraModal = extraModalEl && window.bootstrap ? new bootstrap.Modal(extraModalEl) : null;
  const reviewModalEl = document.getElementById('cashierReviewModal');
  const reviewModal = reviewModalEl && window.bootstrap ? new bootstrap.Modal(reviewModalEl) : null;
  const infoModalEl = document.getElementById('cashierInfoModal');
  const infoModal = infoModalEl && window.bootstrap ? new bootstrap.Modal(infoModalEl) : null;
  const workspace = document.getElementById('cashier_workspace');
  const launchOutlet = document.getElementById('cashier_launch_outlet');
  const launchTerminal = document.getElementById('cashier_launch_terminal');
  const launchOpeningCash = document.getElementById('cashier_launch_opening_cash');
  const launchNotes = document.getElementById('cashier_launch_notes');
  const openButton = document.getElementById('cashier_open_btn');
  const closeButton = document.getElementById('cashier_close_btn');
  const outletSelect = document.getElementById('cashier_outlet_id');
  const terminalSelect = document.getElementById('cashier_terminal_id');
  const salesChannelSelect = document.getElementById('cashier_sales_channel');
  const serviceType = document.getElementById('cashier_service_type');
  const guestCount = document.getElementById('cashier_guest_count');
  const tableNoInput = document.getElementById('cashier_table_no');
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
  const extraProductNameInput = document.getElementById('cashier_extra_product_name');
  const extraLineQtyInput = document.getElementById('cashier_extra_line_qty');
  const extraLineNoteInput = document.getElementById('cashier_extra_line_note');
  const extraBasePriceInput = document.getElementById('cashier_extra_base_price');
  const extraSaveButton = document.getElementById('cashier_save_extra');
  const extraGroupCount = document.getElementById('cashier_extra_group_count');
  const reviewSubmitButton = document.getElementById('cashier_review_submit');
  const toastStack = document.getElementById('cashier_toast_stack');
  const savingOverlay = document.getElementById('cashier_saving_overlay');
  const savingTitle = document.getElementById('cashier_saving_title');
  const savingBody = document.getElementById('cashier_saving_body');
  const defaultSaveDraftLabel = saveDraftButton ? saveDraftButton.textContent : 'Simpan Draft';
  const defaultConfirmOrderLabel = confirmOrderButton ? confirmOrderButton.textContent : 'Simpan Transaksi';
  const extraOptionCache = {};
  let activeExtraLineIndex = null;
  let activeExtraDraft = null;
  let pendingReviewAction = null;

  function escapeHtml(v) {
    return String(v ?? '').replace(/[&<>\"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'}[m]));
  }
  function money(v) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(v || 0));
  }
  function moneyCompact(v) {
    return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(v || 0));
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
  function floorStock(v) {
    return Math.max(0, Math.floor(Number(v || 0)));
  }
  function stockChip(row) {
    const rawQty = row && row.estimated_available_qty;
    if (rawQty === null || rawQty === undefined || rawQty === '') {
      return '<span class="cashier-chip stock">Stok -</span>';
    }
    return `<span class="cashier-chip stock">Stok ${number(floorStock(rawQty), 0)}</span>`;
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
    try { j = JSON.parse(t); } catch (e) {
      throw new Error('Response backend bukan JSON. ' + String(t || '').replace(/\s+/g, ' ').trim().slice(0, 240));
    }
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
    try { j = JSON.parse(t); } catch (e) {
      throw new Error('Response save bukan JSON. ' + String(t || '').replace(/\s+/g, ' ').trim().slice(0, 240));
    }
    if (!r.ok || !j.ok) throw new Error(j.message || 'Gagal menyimpan data');
    return j;
  }

  function showInfoModal(message, title = 'Informasi') {
    const titleEl = document.getElementById('cashier_info_title');
    const messageEl = document.getElementById('cashier_info_message');
    if (titleEl) titleEl.textContent = title;
    if (messageEl) messageEl.textContent = message || '-';
    if (infoModal) {
      infoModal.show();
      return;
    }
    alert(message || '-');
  }

  function showPrintFailureModal(actionLabel, failedPrinters) {
    const failed = Array.isArray(failedPrinters) ? failedPrinters.filter(Boolean) : [];
    if (!failed.length) return;
    const prefix = actionLabel ? `${actionLabel} berhasil, tetapi printer berikut gagal:` : 'Ada printer yang gagal mencetak:';
    showInfoModal(prefix + '\n- ' + failed.join('\n- '), 'Printer');
  }

  function showToast(message, kind = 'info', title = 'Informasi', timeout = 3200) {
    if (!toastStack) {
      return;
    }
    const item = document.createElement('div');
    item.className = `cashier-toast ${kind}`;
    item.innerHTML = `
      <div class="cashier-toast-title">${escapeHtml(title || 'Informasi')}</div>
      <div class="cashier-toast-body">${escapeHtml(message || '-')}</div>
    `;
    toastStack.appendChild(item);
    window.setTimeout(() => {
      item.classList.add('is-hiding');
      window.setTimeout(() => item.remove(), 260);
    }, Math.max(1400, Number(timeout || 3200)));
  }

  function orderStatusChip(status) {
    const value = String(status || '').toUpperCase();
    const label = value || '-';
    const kind = value === 'CONFIRMED' ? 'order-confirmed' : 'order-draft';
    return `<span class="cashier-status-chip ${kind}">${escapeHtml(label)}</span>`;
  }

  function stockCommitChip(status) {
    const value = String(status || '').toUpperCase();
    if (!value) return '';
    const map = {
      PENDING: ['commit-queued', 'Stok PENDING'],
      QUEUED: ['commit-queued', 'Stok QUEUED'],
      PROCESSING: ['commit-processing', 'Stok PROCESSING'],
      POSTED: ['commit-posted', 'Stok POSTED'],
      FAILED: ['commit-failed', 'Stok FAILED'],
      REVERSED: ['commit-reversed', 'Stok REVERSED']
    };
    const entry = map[value] || ['commit-queued', 'Stok ' + value];
    return `<span class="cashier-status-chip ${entry[0]}">${escapeHtml(entry[1])}</span>`;
  }

  function kickoffRuntimeJobSync(orderId, runtimeJobId) {
    const safeOrderId = Number(orderId || 0);
    const safeJobId = Number(runtimeJobId || 0);
    if (safeOrderId <= 0 || safeJobId <= 0) {
      return;
    }
    window.setTimeout(() => {
      postJson(`<?php echo site_url('pos/orders/runtime-jobs/trigger'); ?>/${safeOrderId}`, {
        job_id: safeJobId,
        limit: 1
      }).then((jobJson) => {
        const job = jobJson.job || {};
        const status = String(job.status || '').toUpperCase();
        if (status === 'SUCCESS') {
          showToast('Stok transaksi sudah diposting.', 'success', 'Sinkronisasi Stok', 2400);
          return;
        }
        if (status === 'FAILED') {
          showToast(job.last_error || 'Sinkronisasi stok gagal. Order tetap tersimpan untuk retry.', 'warn', 'Sinkronisasi Stok', 5200);
          return;
        }
        pollRuntimeJobStatus(safeOrderId, 0);
      }).catch(async (e) => {
        try {
          const statusJson = await getJson(`<?php echo site_url('pos/orders/runtime-jobs/status'); ?>/${safeOrderId}`);
          const latest = statusJson.job || {};
          const latestStatus = String(latest.status || '').toUpperCase();
          if (latestStatus === 'SUCCESS') {
            showToast('Stok transaksi sudah diposting.', 'success', 'Sinkronisasi Stok', 2400);
          } else if (latestStatus === 'FAILED') {
            showToast(latest.last_error || 'Sinkronisasi stok gagal. Order tetap tersimpan untuk retry.', 'warn', 'Sinkronisasi Stok', 5200);
          } else {
            showToast(e && e.message ? e.message : 'Sinkronisasi stok masih antre. Jalankan runner queue bila perlu.', 'warn', 'Sinkronisasi Stok', 4200);
            pollRuntimeJobStatus(safeOrderId, 0);
          }
        } catch (_statusError) {
          showToast(e && e.message ? e.message : 'Sinkronisasi stok masih antre. Jalankan runner queue bila perlu.', 'warn', 'Sinkronisasi Stok', 4200);
          pollRuntimeJobStatus(safeOrderId, 0);
        }
      });
    }, 450);
  }

  function pollRuntimeJobStatus(orderId, attempt = 0) {
    const safeOrderId = Number(orderId || 0);
    if (safeOrderId <= 0 || attempt >= 5) {
      return;
    }
    window.setTimeout(() => {
      getJson(`<?php echo site_url('pos/orders/runtime-jobs/status'); ?>/${safeOrderId}`).then((statusJson) => {
        const latest = statusJson.job || {};
        const latestStatus = String(latest.status || '').toUpperCase();
        if (latestStatus === 'SUCCESS') {
          showToast('Stok transaksi sudah diposting.', 'success', 'Sinkronisasi Stok', 2400);
          void loadRecents().catch(() => {});
          return;
        }
        if (latestStatus === 'FAILED') {
          showToast(latest.last_error || 'Sinkronisasi stok gagal. Order tetap tersimpan untuk retry.', 'warn', 'Sinkronisasi Stok', 5200);
          void loadRecents().catch(() => {});
          return;
        }
        pollRuntimeJobStatus(safeOrderId, attempt + 1);
      }).catch(() => {
        pollRuntimeJobStatus(safeOrderId, attempt + 1);
      });
    }, attempt === 0 ? 1200 : 2200);
  }

  function showSavingOverlay(mode) {
    if (savingTitle) {
      savingTitle.textContent = mode === 'CONFIRM' ? 'Menyimpan Transaksi' : 'Menyimpan Draft';
    }
    if (savingBody) {
      savingBody.textContent = mode === 'CONFIRM'
        ? 'Pesanan sedang diproses dan siap dicetak.'
        : 'Draft sedang disimpan.';
    }
    if (savingOverlay) {
      savingOverlay.classList.add('active');
      savingOverlay.setAttribute('aria-hidden', 'false');
    }
  }

  function hideSavingOverlay() {
    if (savingOverlay) {
      savingOverlay.classList.remove('active');
      savingOverlay.setAttribute('aria-hidden', 'true');
    }
  }

  function lineGrandTotal(line) {
    const base = Number(line.qty || 0) * Number(line.unit_price || 0);
    const extraTotal = (Array.isArray(line.extras) ? line.extras : []).reduce((sum, extra) => {
      return sum + (Number(extra.qty || 0) * Number(extra.unit_price || 0));
    }, 0);
    return base + extraTotal;
  }

  function syncHeaderToOrder() {
    order.outlet_id = outletSelect ? (outletSelect.value || '') : order.outlet_id;
    order.terminal_id = terminalSelect ? (terminalSelect.value || '') : order.terminal_id;
    order.sales_channel_id = salesChannelSelect ? (salesChannelSelect.value || '') : order.sales_channel_id;
    order.service_type = serviceType ? (serviceType.value || 'DINE_IN') : 'DINE_IN';
    order.guest_count = Math.max(1, Number(guestCount ? (guestCount.value || 1) : 1));
    order.table_no = tableNoInput ? (tableNoInput.value || '') : '';
    order.notes = notesInput ? (notesInput.value || '') : '';
  }

  function selectedSalesChannelMeta() {
    if (!salesChannelSelect) {
      return { defaultService: 'DINE_IN', allowedTypes: [] };
    }
    const selected = salesChannelSelect.selectedOptions && salesChannelSelect.selectedOptions.length
      ? salesChannelSelect.selectedOptions[0]
      : null;
    const defaultService = String(selected ? (selected.dataset.serviceDefault || '') : '').trim().toUpperCase();
    const allowedTypesRaw = String(selected ? (selected.dataset.allowedTypes || '') : '').trim();
    const allowedTypes = allowedTypesRaw
      ? allowedTypesRaw.split(',').map((item) => String(item || '').trim().toUpperCase()).filter(Boolean)
      : [];
    return {
      defaultService: ['DINE_IN', 'TAKE_AWAY', 'DELIVERY', 'PICKUP'].includes(defaultService) ? defaultService : 'DINE_IN',
      allowedTypes
    };
  }

  function applyAllowedServiceTypes(allowedTypes, preferredType) {
    if (!serviceType) return;
    const normalizedAllowed = Array.isArray(allowedTypes) ? allowedTypes.filter((item) => ['DINE_IN', 'TAKE_AWAY', 'DELIVERY', 'PICKUP'].includes(String(item || '').toUpperCase())) : [];
    const enabledValues = [];
    Array.from(serviceType.options).forEach((option) => {
      const value = String(option.value || '').toUpperCase();
      const enabled = !normalizedAllowed.length || normalizedAllowed.includes(value);
      option.disabled = !enabled;
      option.hidden = !enabled;
      if (enabled) {
        enabledValues.push(value);
      }
    });
    const normalizedPreferred = String(preferredType || '').toUpperCase();
    const nextValue = enabledValues.includes(normalizedPreferred)
      ? normalizedPreferred
      : (enabledValues[0] || 'DINE_IN');
    serviceType.value = nextValue;
    order.service_type = nextValue;
  }

  function syncServiceTypeFromChannel(forceDefault = true) {
    if (!salesChannelSelect || !serviceType) return;
    const meta = selectedSalesChannelMeta();
    const preferredType = forceDefault ? meta.defaultService : (serviceType.value || order.service_type || meta.defaultService);
    applyAllowedServiceTypes(meta.allowedTypes, preferredType);
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

  function isConfirmedOrder() {
    return String(order.status || '').toUpperCase() === 'CONFIRMED';
  }

  function isLockedExistingLine(line) {
    return isConfirmedOrder() && Number(line && line.id ? line.id : 0) > 0;
  }

  function canEditCartLine(line) {
    return !isLockedExistingLine(line);
  }

  function updateActionState() {
    reversalButton.disabled = !order.id;
    const sessionReady = !!activeSession;
    const normalizedStatus = String(order.status || '').toUpperCase();
    const draftEditableOrder = !order.id || ['DRAFT', 'PENDING'].includes(normalizedStatus);
    const confirmEditableOrder = !order.id || ['DRAFT', 'PENDING', 'CONFIRMED'].includes(normalizedStatus);
    if (saveDraftButton) {
      saveDraftButton.disabled = !sessionReady || !draftEditableOrder || draftSaveInFlight || confirmInFlight;
      saveDraftButton.textContent = defaultSaveDraftLabel;
      saveDraftButton.title = isConfirmedOrder() ? 'Order confirmed tidak bisa disimpan sebagai draft. Gunakan Simpan Transaksi untuk append item baru atau ubah header.' : '';
    }
    if (confirmOrderButton) {
      confirmOrderButton.disabled = !sessionReady || !confirmEditableOrder || draftSaveInFlight || confirmInFlight;
      confirmOrderButton.textContent = isConfirmedOrder() ? 'Simpan Perubahan Transaksi' : defaultConfirmOrderLabel;
    }
  }

  function renderMemberSelection() {
    if (!order.member_id) {
      memberSelected.innerHTML = '<div class="cashier-member-empty">Walk in customer. Transaksi ini belum memakai member.</div>';
      return;
    }
    memberSelected.innerHTML = `
      <div class="cashier-member-toolbar">
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
      const lockedExistingLine = isLockedExistingLine(line);
      const bundleChip = line.bundle_id ? `<span class="cashier-chip bundle mt-2"><i class="ri-gift-2-line"></i>${escapeHtml(line.bundle_name || 'Bundle')}</span>` : '';
      const lineStateChip = lockedExistingLine
        ? '<span class="cashier-chip info mt-2">Item tersimpan</span>'
        : (isConfirmedOrder() ? '<span class="cashier-chip ok mt-2">Item baru</span>' : '');
      const extras = Array.isArray(line.extras) ? line.extras : [];
      const extraTotal = extras.reduce((carry, extra) => carry + (Number(extra.qty || 0) * Number(extra.unit_price || 0)), 0);
      const extraSummary = extras.length
        ? `<div class="cashier-cart-extras">${extras.map((extra) => `<span class="cashier-extra-pill">${escapeHtml(extra.extra_name || '-')} x${number(extra.qty || 0, 0)}</span>`).join('')}</div>`
        : '';
      const extraCountChip = `<span class="cashier-cart-extra-count">Extra ${extras.length}</span>`;
      const removeButton = lockedExistingLine
        ? '<span class="cashier-chip warn">Void untuk ubah/hapus</span>'
        : `<button type="button" class="btn btn-sm btn-outline-danger cashier-remove-line" data-index="${idx}">Hapus</button>`;
      return `
        <div class="cashier-cart-item${lockedExistingLine ? ' is-locked' : ''}">
          <div class="cashier-cart-item-head">
            <div>
              <div class="cashier-cart-item-title">${escapeHtml(line.product_name || '-')}</div>
              <div class="cashier-cart-item-sub">${escapeHtml(line.product_code || '-')}</div>
              ${bundleChip}
              ${lineStateChip}
            </div>
            ${removeButton}
          </div>
          <div class="cashier-cart-editor-row mt-2">
            <div>
              <label class="form-label small text-muted mb-1">Qty</label>
              <div class="cashier-qty-stepper">
                <button type="button" class="cashier-line-qty-minus" data-index="${idx}" ${lockedExistingLine ? 'disabled' : ''}>-</button>
                <input type="number" min="1" step="1" class="cashier-line-qty" data-index="${idx}" value="${Number(line.qty || 1)}" ${lockedExistingLine ? 'readonly disabled' : ''}>
                <button type="button" class="cashier-line-qty-plus" data-index="${idx}" ${lockedExistingLine ? 'disabled' : ''}>+</button>
              </div>
            </div>
            <div>
              <label class="form-label small text-muted mb-1">Catatan</label>
              <input type="text" class="form-control form-control-sm cashier-line-note cashier-inline-note" data-index="${idx}" value="${escapeHtml(line.notes || '')}" placeholder="Catatan line" ${lockedExistingLine ? 'readonly' : ''}>
            </div>
          </div>
          ${extraSummary}
          <div class="cashier-cart-line-bottom">
            <div class="cashier-cart-line-left">
              ${extraCountChip}
              ${availabilityChip(line.availability_status)}
              <button type="button" class="btn btn-sm btn-outline-warning cashier-edit-extra" data-index="${idx}" ${lockedExistingLine ? 'disabled title="Item lama tidak bisa diubah dari kasir"' : ''}>Atur</button>
            </div>
            <div class="fw-bold">${money((Number(line.qty || 0) * Number(line.unit_price || 0)) + extraTotal)}</div>
          </div>
        </div>
      `;
    }).join('');

    function syncLineQty(idx, nextQty) {
      if (!canEditCartLine(order.lines[idx])) {
        return;
      }
      order.lines[idx].qty = Math.max(1, Number(nextQty || 1));
      (Array.isArray(order.lines[idx].extras) ? order.lines[idx].extras : []).forEach((extra) => {
        extra.qty = Math.max(1, Number(order.lines[idx].qty || 1));
      });
      renderCart();
    }
    cartList.querySelectorAll('.cashier-line-qty').forEach((el) => el.addEventListener('input', () => {
      const idx = Number(el.dataset.index || 0);
      syncLineQty(idx, el.value || 1);
    }));
    cartList.querySelectorAll('.cashier-line-qty-minus').forEach((el) => el.addEventListener('click', () => {
      const idx = Number(el.dataset.index || 0);
      syncLineQty(idx, Math.max(1, Number(order.lines[idx].qty || 1) - 1));
    }));
    cartList.querySelectorAll('.cashier-line-qty-plus').forEach((el) => el.addEventListener('click', () => {
      const idx = Number(el.dataset.index || 0);
      syncLineQty(idx, Math.max(1, Number(order.lines[idx].qty || 1) + 1));
    }));
    cartList.querySelectorAll('.cashier-line-note').forEach((el) => el.addEventListener('input', () => {
      const idx = Number(el.dataset.index || 0);
      if (!canEditCartLine(order.lines[idx])) {
        return;
      }
      order.lines[idx].notes = el.value || '';
    }));
    cartList.querySelectorAll('.cashier-remove-line').forEach((el) => el.addEventListener('click', () => {
      const idx = Number(el.dataset.index || 0);
      const line = order.lines[idx];
      if (!canEditCartLine(line)) {
        return;
      }
      if (line && line.bundle_id) {
        const bundleKey = String(line.bundle_key || '').trim();
        if (bundleKey !== '') {
          order.lines = order.lines.filter((row) => String(row.bundle_key || '').trim() !== bundleKey);
        } else {
          order.lines = order.lines.filter((row) => Number(row.bundle_id || 0) !== Number(line.bundle_id || 0) || !canEditCartLine(row));
        }
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
    if (extraGroupCount) {
      extraGroupCount.textContent = `${Array.isArray(groups) ? groups.length : 0} group`;
    }
    if (!Array.isArray(groups) || !groups.length) {
      extraGroupsContainer.innerHTML = '<div class="cashier-empty">Produk ini belum punya extra aktif yang ditampilkan di kasir.</div>';
      return;
    }
    const selectedMap = {};
    (Array.isArray(line.extras) ? line.extras : []).forEach((extra) => {
      selectedMap[Number(extra.extra_id || 0)] = extra;
    });
    extraGroupsContainer.innerHTML = `<div class="cashier-extra-groups-grid">${groups.map((group) => `
      <div class="cashier-extra-group" data-group-id="${Number(group.extra_group_id || 0)}" data-min-select="${Number(group.min_select || 0)}" data-max-select="${Number(group.max_select || 0)}" data-required="${Number(group.is_required || 0)}">
        <div class="cashier-extra-group-head">
          <div>
            <div class="cashier-extra-group-title">${escapeHtml(group.group_name || '-')}</div>
            <div class="cashier-extra-group-meta">${escapeHtml(group.group_code || '-')}</div>
          </div>
          <div class="cashier-extra-group-badges">
            ${Number(group.is_required || 0) === 1 ? '<span class="cashier-chip warn">Wajib pilih</span>' : '<span class="cashier-chip info">Opsional</span>'}
            <span class="cashier-chip info">Min ${Number(group.min_select || 0)}</span>
            <span class="cashier-chip info">Max ${Number(group.max_select || 0) || 1}</span>
          </div>
        </div>
        <div class="cashier-extra-item-list">
          ${(Array.isArray(group.items) ? group.items : []).map((item) => {
            const selected = selectedMap[Number(item.extra_id || 0)] || null;
            const inputType = (Number(group.is_required || 0) === 1 && Number(group.max_select || 0) === 1) ? 'radio' : 'checkbox';
            const inputName = inputType === 'radio'
              ? `cashier_extra_group_${Number(group.extra_group_id || 0)}`
              : `cashier_extra_group_${Number(group.extra_group_id || 0)}_${Number(item.extra_id || 0)}`;
            return `
              <div class="cashier-extra-item">
                <div class="cashier-extra-item-row">
                  <div>
                    <div class="form-check mb-1">
                      <input class="form-check-input cashier-extra-check" type="${inputType}" name="${inputName}" id="extra_${Number(item.extra_id || 0)}" data-extra-id="${Number(item.extra_id || 0)}" ${selected ? 'checked' : ''}>
                      <label class="form-check-label fw-semibold" for="extra_${Number(item.extra_id || 0)}">${escapeHtml(item.extra_name || '-')}</label>
                    </div>
                    <div class="cashier-mini-note">${escapeHtml(item.extra_code || '-')} | ${escapeHtml(item.extra_type || '-')}</div>
                    <div class="mt-2"><span class="cashier-extra-qty-note cashier-extra-qty-label" data-extra-id="${Number(item.extra_id || 0)}">Qty ikut produk: ${number(line.qty || 1, 0)}</span></div>
                  </div>
                  <div>
                    <label class="form-label small text-muted mb-1">Harga</label>
                    <input type="number" step="0.01" class="form-control form-control-sm cashier-extra-price cashier-extra-price-input" data-extra-id="${Number(item.extra_id || 0)}" value="${selected ? Number(selected.unit_price || 0) : Number(item.selling_price || 0)}">
                  </div>
                </div>
              </div>
            `;
          }).join('')}
        </div>
      </div>
    `).join('')}</div>`;
    extraGroupsContainer.querySelectorAll('.cashier-extra-check').forEach((input) => {
      const refreshSelectedState = () => {
        extraGroupsContainer.querySelectorAll('.cashier-extra-item').forEach((item) => item.classList.remove('selected'));
        extraGroupsContainer.querySelectorAll('.cashier-extra-check:checked').forEach((checked) => {
          checked.closest('.cashier-extra-item')?.classList.add('selected');
        });
      };
      input.addEventListener('change', refreshSelectedState);
      refreshSelectedState();
    });
  }

  function cloneLine(line) {
    return {
      id: Number(line.id || 0) || null,
      product_id: Number(line.product_id || 0),
      bundle_id: line.bundle_id ? Number(line.bundle_id) : null,
      bundle_key: line.bundle_key || '',
      bundle_name: line.bundle_name || '',
      product_code: line.product_code || '',
      product_name: line.product_name || '',
      product_division_name: line.product_division_name || '-',
      availability_status: line.availability_status || 'CHECK',
      qty: Math.max(1, Number(line.qty || 1)),
      unit_price: Number(line.unit_price || 0),
      hpp_live_snapshot: Number(line.hpp_live_snapshot || 0),
      notes: line.notes || '',
      extras: Array.isArray(line.extras) ? line.extras.map((extra) => ({
        id: Number(extra.id || 0) || null,
        extra_id: Number(extra.extra_id || 0),
        extra_name: extra.extra_name || '',
        extra_type: extra.extra_type || '',
        qty: Math.max(1, Number(extra.qty || 1)),
        unit_price: Number(extra.unit_price || 0),
        notes: extra.notes || ''
      })) : []
    };
  }

  function buildProductDraftLine(row) {
    return {
      id: null,
      product_id: Number(row.id || 0),
      bundle_id: null,
      bundle_key: '',
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
    };
  }

  function hydrateExtraDraftFields(line, isEdit) {
    if (extraProductNameInput) {
      extraProductNameInput.value = `${line.product_name || '-'}${line.product_code ? ' | ' + line.product_code : ''}`;
    }
    if (extraLineQtyInput) {
      extraLineQtyInput.value = Math.max(1, Number(line.qty || 1));
    }
    if (extraLineNoteInput) {
      extraLineNoteInput.value = line.notes || '';
    }
    if (extraBasePriceInput) {
      extraBasePriceInput.value = money(line.unit_price || 0);
    }
    if (extraSaveButton) {
      extraSaveButton.textContent = isEdit ? 'Simpan Perubahan' : 'Simpan ke Keranjang';
    }
  }

  function syncExtraQtyLabels(qtyValue) {
    extraGroupsContainer.querySelectorAll('.cashier-extra-qty-label').forEach((el) => {
      el.textContent = `Qty ikut produk: ${number(qtyValue || 1, 0)}`;
    });
  }

  async function openExtraChooser(index) {
    const sourceLine = order.lines[index];
    if (!sourceLine) {
      throw new Error('Line order tidak ditemukan.');
    }
    if (!canEditCartLine(sourceLine)) {
      throw new Error('Item transaksi yang sudah tersimpan tidak bisa diubah dari kasir. Gunakan void untuk pengurangan atau pembatalan item.');
    }
    activeExtraLineIndex = index;
    activeExtraDraft = {
      mode: 'edit',
      lineIndex: index,
      line: cloneLine(sourceLine)
    };
    const line = activeExtraDraft.line;
    extraMeta.textContent = `${line.product_name || '-'} | ${line.product_code || '-'} | Atur qty, catatan, dan extra`;
    hydrateExtraDraftFields(line, true);
    if (extraGroupCount) extraGroupCount.textContent = 'Memuat...';
    extraGroupsContainer.innerHTML = '<div class="cashier-empty">Memuat extra produk...</div>';
    extraModal.show();
    const groups = await fetchExtraGroups(line.product_id);
    renderExtraGroups(groups, line);
    syncExtraQtyLabels(line.qty || 1);
  }

  async function openProductConfigurator(row) {
    activeExtraLineIndex = null;
    activeExtraDraft = {
      mode: 'create',
      lineIndex: null,
      line: buildProductDraftLine(row)
    };
    const line = activeExtraDraft.line;
    extraMeta.textContent = `${line.product_name || '-'} | ${line.product_code || '-'} | Pilih extra, qty, dan catatan sebelum masuk ke keranjang`;
    hydrateExtraDraftFields(line, false);
    if (extraGroupCount) extraGroupCount.textContent = 'Memuat...';
    extraGroupsContainer.innerHTML = '<div class="cashier-empty">Memuat extra produk...</div>';
    extraModal.show();
    const groups = await fetchExtraGroups(line.product_id);
    renderExtraGroups(groups, line);
    syncExtraQtyLabels(line.qty || 1);
  }

  function collectExtraSelection() {
    if (!activeExtraDraft || !activeExtraDraft.line) {
      throw new Error('Line extra tidak aktif.');
    }
    const line = activeExtraDraft.line;
    line.qty = Math.max(1, Number(extraLineQtyInput ? (extraLineQtyInput.value || 1) : 1));
    line.notes = extraLineNoteInput ? (extraLineNoteInput.value || '') : '';
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
        const priceInput = extraGroupsContainer.querySelector(`.cashier-extra-price[data-extra-id="${extraId}"]`);
        selection.push({
          extra_id: extraId,
          extra_name: item.extra_name || '',
          extra_type: item.extra_type || '',
          qty: Math.max(1, Number(line.qty || 1)),
          unit_price: Number(priceInput ? priceInput.value || item.selling_price || 0 : item.selling_price || 0),
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
    line.extras = selection;
    if (activeExtraDraft.mode === 'edit' && activeExtraDraft.lineIndex != null && order.lines[activeExtraDraft.lineIndex]) {
      order.lines[activeExtraDraft.lineIndex] = cloneLine(line);
      return;
    }
    order.lines.push(cloneLine(line));
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
      const title = isBundle ? (row.bundle_name || '-') : (row.product_name || '-');
      return `
          <div class="cashier-product-card" data-row="${encodeURIComponent(JSON.stringify(row))}">
            ${productMediaHtml(row)}
            <div class="cashier-product-name">${escapeHtml(title)}</div>
            <div class="cashier-catalog-chip-row">
              ${availabilityChip(row.availability_status || 'CHECK')}
              ${stockChip(row)}
              ${isBundle ? '<span class="cashier-chip bundle">Bundle</span>' : ''}
            </div>
            ${row.bottleneck_name_snapshot ? `<div class="cashier-mini-note mt-2">Bottleneck: ${escapeHtml(row.bottleneck_name_snapshot)}</div>` : ''}
            <div class="d-flex justify-content-between align-items-center mt-3">
              <div class="cashier-product-price">${money(row.selling_price || 0)}</div>
              <div class="cashier-mini-note">${isBundle ? 'Paket kasir' : 'Produk'}</div>
            </div>
          </div>
        `;
      }).join('')
    }</div>`;
    catalogResult.querySelectorAll('.cashier-product-card').forEach((item) => item.addEventListener('click', () => {
      const row = JSON.parse(decodeURIComponent(item.dataset.row));
      if (searchMode === 'BUNDLE') addBundleRows(row);
      else openProductConfigurator(row).catch((e) => alert(e.message || 'Gagal membuka konfigurasi produk'));
    }));
  }

  function addBundleRows(bundle) {
    const bundleId = Number(bundle.id || 0);
    const existing = order.lines.filter((line) => Number(line.bundle_id || 0) === bundleId && canEditCartLine(line));
    if (existing.length) {
      existing.forEach((line) => {
        const source = (bundle.items || []).find((item) => Number(item.product_id || 0) === Number(line.product_id || 0));
        if (source) line.qty = Number(line.qty || 0) + Number(source.qty || 0);
      });
    } else {
      const bundleKey = `bundle-${bundleId}-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
      (bundle.items || []).forEach((item) => {
        order.lines.push({
          id: null,
          product_id: Number(item.product_id || 0),
          bundle_id: bundleId,
          bundle_key: bundleKey,
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
      <div class="cashier-recent-item${Number(row.id || 0) === Number(selectedRecentOrderId || 0) ? ' active' : ''}" data-id="${Number(row.id || 0)}">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div class="cashier-recent-meta">
            <div class="cashier-recent-order-no">${escapeHtml(row.order_no || '-')}</div>
            <div class="cashier-recent-customer">${escapeHtml(row.member_name || 'Walk in')}</div>
            <div class="cashier-recent-table">Meja ${escapeHtml(row.table_no || '-')}</div>
          </div>
          <div class="text-end">
            <div class="fw-bold">${money(row.grand_total || 0)}</div>
            <div class="cashier-status-badges">
              ${orderStatusChip(row.status || '-')}
              ${stockCommitChip(row.stock_commit_status || '')}
            </div>
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
    order.sales_channel_id = String(header.sales_channel_id || order.sales_channel_id || '');
    order.service_type = header.service_type || 'DINE_IN';
    order.guest_count = Number(header.guest_count || 1);
    order.table_no = header.table_no || '';
    order.member_id = Number(header.member_id || 0) || null;
    order.member_no = header.member_no || '';
    order.member_name = header.member_name || '';
    order.member_mobile_phone = header.member_mobile_phone || '';
    order.member_point_balance = 0;
    order.member_stamp_balance = 0;
    order.notes = header.notes || '';
    selectedRecentOrderId = Number(header.id || id || 0) || null;
    order.lines = lines.map((line) => ({
      id: Number(line.id || 0) || null,
      product_id: Number(line.product_id || 0),
      bundle_id: Number(line.bundle_id || 0) || null,
      bundle_key: Number(line.bundle_id || 0) > 0 ? `saved-${Number(line.id || 0)}` : '',
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
        id: Number(extra.id || 0) || null,
        extra_id: Number(extra.extra_id || 0),
        extra_name: extra.extra_name || '',
        extra_type: extra.extra_type || '',
        qty: Number(extra.qty || 0),
        unit_price: Number(extra.unit_price || 0),
        notes: extra.notes || ''
      })) : []
    }));
    if (salesChannelSelect) salesChannelSelect.value = order.sales_channel_id || '<?php echo $defaultSalesChannelId > 0 ? (string)$defaultSalesChannelId : ''; ?>';
    syncServiceTypeFromChannel(false);
    if (guestCount) guestCount.value = order.guest_count || 1;
    if (tableNoInput) tableNoInput.value = order.table_no || '';
    if (notesInput) notesInput.value = order.notes || '';
    renderMemberSelection();
    renderCart();
    loadRecents().catch(() => {});
  }

  async function saveDraft(silent = false, refreshRecents = true, allowDuringConfirm = false) {
    if (draftSaveInFlight || (confirmInFlight && !allowDuringConfirm)) {
      throw new Error('Draft sedang diproses. Tunggu sebentar.');
    }
    if (isConfirmedOrder()) {
      throw new Error('Order confirmed tidak bisa disimpan sebagai draft. Gunakan Simpan Transaksi untuk append item baru atau ubah header.');
    }
    draftSaveInFlight = true;
    updateActionState();
    syncHeaderToOrder();
    try {
      if (!activeSession) throw new Error('Kasir belum dibuka. Buka sesi kasir dulu.');
      const payload = buildOrderPayload();
      const json = await postJson('<?php echo site_url('pos/orders/draft/save'); ?>', payload);
      order.id = Number(json.id || 0) || order.id;
      order.order_no = json.order_no || order.order_no;
      order.status = 'DRAFT';
      selectedRecentOrderId = Number(order.id || 0) || null;
      if (!silent) showInfoModal('Draft tersimpan.', 'Draft');
      if (refreshRecents) {
        await loadRecents();
      }
      return json;
    } finally {
      draftSaveInFlight = false;
      updateActionState();
    }
  }

  function buildOrderPayload() {
    return {
        id: order.id,
        outlet_id: order.outlet_id,
        terminal_id: order.terminal_id,
        sales_channel_id: order.sales_channel_id,
        service_type: order.service_type,
        guest_count: order.guest_count,
        table_no: order.table_no,
        member_id: order.member_id,
        notes: order.notes,
        require_active_session: 1,
        lines: order.lines.map((line) => ({
          id: line.id,
          product_id: line.product_id,
          bundle_id: line.bundle_id,
          qty: line.qty,
          unit_price: line.unit_price,
          hpp_live_snapshot: line.hpp_live_snapshot,
          notes: line.notes || '',
          extras: (Array.isArray(line.extras) ? line.extras : []).map((extra) => ({
            id: extra.id,
            extra_id: extra.extra_id,
            qty: extra.qty,
            unit_price: extra.unit_price,
            notes: extra.notes || ''
          }))
        }))
      };
  }

  async function directPrintTargets(targets) {
    const rows = Array.isArray(targets) ? targets : [];
    if (!rows.length) {
      return { successCount: 0, failed: [] };
    }
    const failed = [];
    let successCount = 0;
    const jobs = [];
    for (const target of rows) {
      const copies = Math.max(1, Number(target.copies || 1));
      const pythonPort = Number(target.python_port || 0);
      if (!pythonPort) {
        failed.push(`${target.printer_name || target.printer_code || 'Printer'}: python port belum valid`);
        continue;
      }
      for (let i = 0; i < copies; i += 1) {
        jobs.push(fetch('http://127.0.0.1:' + pythonPort + '/cetak', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              text: String(target.text || ''),
              printer_code: String(target.printer_code || ''),
              printer_name: String(target.printer_name || ''),
              paper_width_mm: Number(target.paper_width_mm || 80),
              chars_per_line: Number(target.chars_per_line || 48)
            })
          }).then((res) => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            successCount += 1;
          }).catch((e) => {
            failed.push(`${target.printer_name || target.printer_code || 'Printer'}: ${e && e.message ? e.message : 'gagal cetak'}`);
          }));
      }
    }
    await Promise.all(jobs);
    return { successCount, failed };
  }

  async function confirmDraft() {
    if (confirmInFlight) {
      throw new Error('Transaksi sedang diproses. Tunggu sebentar.');
    }
    confirmInFlight = true;
    updateActionState();
    try {
      if (!order.lines.length) throw new Error('Tambahkan minimal 1 item sebelum confirm.');
      const json = await postJson('<?php echo site_url('pos/orders/draft/save-confirm'); ?>', buildOrderPayload());
      const confirmedOrderId = Number(json.id || 0);
      const snapshotId = Number(json.snapshot_id || 0);
      const runtimeJobId = Number(json.runtime_job_id || 0);
      const isAppendMode = !!json.append_mode;
      const isHeaderOnlyUpdate = !!json.header_only_update;
      const appendedLineCount = Number(json.appended_line_count || 0);

      if (confirmedOrderId > 0) {
        void loadRecents().catch(() => {});
        void loadCatalog().catch(() => {});
      }

      if (isHeaderOnlyUpdate) {
        showToast('Perubahan header transaksi tersimpan. Tidak ada item baru yang dicetak.', 'success', 'Transaksi', 2600);
      } else if (isAppendMode) {
        showToast(
          appendedLineCount > 0
            ? `Perubahan transaksi tersimpan. Hanya ${appendedLineCount} line baru yang dicetak.`
            : 'Perubahan transaksi tersimpan.',
          'success',
          'Transaksi',
          2800
        );
      } else {
        showToast('Pesanan tersimpan. Tiket dicetak, sinkron stok masuk antrean.', 'success', 'Transaksi', 2800);
      }
      if (runtimeJobId > 0) {
        showToast('Sinkronisasi stok berjalan di background.', 'info', 'Sinkronisasi Stok', 2600);
      }

      if (confirmedOrderId > 0 && snapshotId > 0) {
        void postJson(`<?php echo site_url('pos/orders/confirm-print-targets'); ?>/${confirmedOrderId}`, { snapshot_id: snapshotId }).then((payloadJson) => {
          return directPrintTargets(payloadJson.direct_print_targets || []);
        }).then((printResult) => {
          showPrintFailureModal('Transaksi', printResult.failed || []);
        }).catch((e) => {
          showPrintFailureModal('Transaksi', [e && e.message ? e.message : 'Gagal menyiapkan direct print']);
        });
      }

      if (confirmedOrderId > 0 && runtimeJobId > 0) {
        kickoffRuntimeJobSync(confirmedOrderId, runtimeJobId);
      }
      resetOrder();
    } finally {
      confirmInFlight = false;
      updateActionState();
    }
  }

  function renderReviewModal(mode) {
    syncHeaderToOrder();
    const title = document.getElementById('cashier_review_title');
    const meta = document.getElementById('cashier_review_meta');
    const list = document.getElementById('cashier_review_list');
    const total = document.getElementById('cashier_review_total');
    const hint = document.getElementById('cashier_review_hint');
    const reviewChannel = document.getElementById('cashier_review_channel');
    const reviewGuest = document.getElementById('cashier_review_guest');
    const reviewTableNo = document.getElementById('cashier_review_table_no');
    const reviewMember = document.getElementById('cashier_review_member');
    const reviewNotes = document.getElementById('cashier_review_notes');
    const selectedChannel = salesChannelSelect && salesChannelSelect.selectedOptions.length ? salesChannelSelect.selectedOptions[0].textContent.trim() : 'Walk In';
    if (title) title.textContent = mode === 'CONFIRM' ? 'Review Simpan Transaksi' : 'Review Simpan Draft';
    if (meta) meta.textContent = mode === 'CONFIRM'
      ? (isConfirmedOrder()
        ? 'Line lama terkunci. Header boleh diubah, line baru akan ditambahkan ke transaksi ini.'
        : 'Cek order singkat sebelum transaksi disimpan.')
      : 'Cek order singkat sebelum draft disimpan.';
    if (hint) hint.textContent = mode === 'CONFIRM'
      ? (isConfirmedOrder()
        ? 'Hanya item baru yang dicetak dan diposting ke stok. Pengurangan item harus lewat void.'
        : 'Stok dipotong dan tiket langsung dicetak.')
      : 'Draft disimpan tanpa potong stok.';
    if (reviewChannel) reviewChannel.textContent = selectedChannel || 'Walk In';
    if (reviewGuest) reviewGuest.textContent = String(order.guest_count || 1);
    if (reviewTableNo) reviewTableNo.textContent = order.table_no || '-';
    if (reviewMember) reviewMember.textContent = order.member_name || 'Walk in';
    if (reviewNotes) reviewNotes.textContent = order.notes || '-';
    if (total) total.textContent = money(order.lines.reduce((sum, line) => sum + lineGrandTotal(line), 0));
    if (list) {
      list.innerHTML = order.lines.map((line, index) => {
        const extras = Array.isArray(line.extras) ? line.extras : [];
        return `
          <div class="cashier-review-item">
            <div class="d-flex justify-content-between align-items-start gap-3">
              <div>
                <div class="fw-bold">${escapeHtml(line.product_name || '-')}</div>
                <div class="cashier-review-line-meta">
                  <span class="cashier-chip info">Qty ${number(line.qty || 0, 0)}</span>
                  ${line.bundle_name ? `<span class="cashier-chip bundle">${escapeHtml(line.bundle_name)}</span>` : ''}
                  ${isLockedExistingLine(line) ? '<span class="cashier-chip info">Tersimpan</span>' : (isConfirmedOrder() ? '<span class="cashier-chip ok">Baru</span>' : '')}
                  ${line.notes ? `<span class="cashier-chip warn">Catatan</span>` : ''}
                </div>
              </div>
              <div class="fw-bold">${money(lineGrandTotal(line))}</div>
            </div>
            ${line.notes ? `<div class="cashier-mini-note mt-2">Catatan: ${escapeHtml(line.notes)}</div>` : ''}
            ${extras.length ? `<div class="cashier-review-extra">${extras.map((extra) => `<span class="cashier-extra-pill">${escapeHtml(extra.extra_name || '-')} x${number(extra.qty || 0, 0)}${Number(extra.unit_price || 0) > 0 ? ' • ' + money(extra.unit_price || 0) : ''}</span>`).join('')}</div>` : ''}
          </div>
        `;
      }).join('');
    }
    if (reviewSubmitButton) {
      reviewSubmitButton.textContent = mode === 'CONFIRM' ? 'Simpan Transaksi Sekarang' : 'Simpan Draft Sekarang';
      reviewSubmitButton.classList.toggle('btn-primary', true);
      reviewSubmitButton.classList.toggle('btn-danger', false);
    }
  }

  function openReviewModal(mode) {
    if (mode === 'DRAFT' && isConfirmedOrder()) {
      throw new Error('Order confirmed tidak bisa disimpan sebagai draft. Gunakan Simpan Transaksi untuk append item baru atau ubah header.');
    }
    if (!order.lines.length) throw new Error('Tambahkan minimal 1 item dulu.');
    pendingReviewAction = mode;
    renderReviewModal(mode);
    if (reviewModal) reviewModal.show();
  }

  async function submitReviewedOrder() {
    const mode = pendingReviewAction || 'DRAFT';
    if ((mode === 'CONFIRM' && confirmInFlight) || (mode !== 'CONFIRM' && draftSaveInFlight)) {
      return;
    }
    if (reviewSubmitButton) {
      reviewSubmitButton.disabled = true;
      reviewSubmitButton.textContent = mode === 'CONFIRM' ? 'Menyimpan transaksi...' : 'Menyimpan draft...';
    }
    try {
      if (reviewModal) reviewModal.hide();
      showSavingOverlay(mode);
      if (mode === 'CONFIRM') {
        await confirmDraft();
      } else {
        await saveDraft(true);
        showToast('Draft berhasil disimpan.', 'success', 'Draft', 2200);
      }
    } finally {
      hideSavingOverlay();
      if (reviewSubmitButton) {
        reviewSubmitButton.disabled = false;
        reviewSubmitButton.textContent = mode === 'CONFIRM' ? 'Simpan Transaksi Sekarang' : 'Simpan Draft Sekarang';
      }
      pendingReviewAction = null;
    }
  }

  function resetOrder() {
    order.id = null;
    order.order_no = '';
    order.status = 'DRAFT';
    order.outlet_id = activeSession ? String(activeSession.outlet_id || '') : '';
    order.terminal_id = activeSession ? String(activeSession.terminal_id || '') : '';
    order.sales_channel_id = '<?php echo $defaultSalesChannelId > 0 ? (string)$defaultSalesChannelId : ''; ?>';
    order.service_type = 'DINE_IN';
    order.guest_count = 1;
    order.table_no = '';
    order.member_id = null;
    order.member_no = '';
    order.member_name = '';
    order.member_mobile_phone = '';
    order.member_point_balance = 0;
    order.member_stamp_balance = 0;
    order.notes = '';
    order.lines = [];
    selectedRecentOrderId = null;
    reversalPreview = null;
    if (guestCount) guestCount.value = 1;
    if (tableNoInput) tableNoInput.value = '';
    if (notesInput) notesInput.value = '';
    clearMemberSelection();
    if (salesChannelSelect) salesChannelSelect.value = '<?php echo $defaultSalesChannelId > 0 ? (string)$defaultSalesChannelId : ''; ?>';
    syncServiceTypeFromChannel();
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
  if (tableNoInput) tableNoInput.addEventListener('input', syncHeaderToOrder);
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
  if (salesChannelSelect) {
    salesChannelSelect.addEventListener('change', () => {
      syncServiceTypeFromChannel(true);
    });
  }
  const recentQ = document.getElementById('cashier_recent_q');
  const recentLimit = document.getElementById('cashier_recent_limit');
  if (recentQ) recentQ.addEventListener('input', (e) => { recentState.q = e.target.value; loadRecents().catch((err) => alert(err.message)); });
  if (recentLimit) recentLimit.addEventListener('change', (e) => { recentState.limit = Number(e.target.value || 20); loadRecents().catch((err) => alert(err.message)); });

  document.getElementById('cashier_reset_order').addEventListener('click', resetOrder);
  document.getElementById('cashier_save_extra').addEventListener('click', () => {
    try {
      collectExtraSelection();
      if (extraModal) extraModal.hide();
      activeExtraDraft = null;
      activeExtraLineIndex = null;
      if (productSearchInput) {
        productSearchInput.value = '';
      }
      renderCart();
    } catch (e) {
      alert(e.message || 'Gagal menyimpan extra');
    }
  });
  if (extraLineQtyInput) {
    extraLineQtyInput.addEventListener('input', () => {
      const nextQty = Math.max(1, Number(extraLineQtyInput.value || 1));
      extraLineQtyInput.value = nextQty;
      syncExtraQtyLabels(nextQty);
    });
  }
  if (extraModalEl) {
    extraModalEl.addEventListener('hidden.bs.modal', () => {
      activeExtraDraft = null;
      activeExtraLineIndex = null;
    });
  }
  if (reviewModalEl) {
    reviewModalEl.addEventListener('hidden.bs.modal', () => {
      pendingReviewAction = null;
    });
  }
  if (saveDraftButton) saveDraftButton.addEventListener('click', async () => {
    try { openReviewModal('DRAFT'); } catch (e) { alert(e.message); }
  });
  if (confirmOrderButton) confirmOrderButton.addEventListener('click', async () => {
    try { openReviewModal('CONFIRM'); } catch (e) { alert(e.message); }
  });
  if (reviewSubmitButton) reviewSubmitButton.addEventListener('click', async () => {
    try { await submitReviewedOrder(); } catch (e) { alert(e.message); }
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
  syncServiceTypeFromChannel(true);
  syncHeaderToOrder();
  loadRecents().catch((e) => alert(e.message));
  if (activeSession) {
    loadCatalog().catch((e) => { catalogResult.innerHTML = `<div class="cashier-empty text-danger">${escapeHtml(e.message)}</div>`; });
  }
});
</script>

