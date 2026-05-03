<?php
$s = $summary ?? [];
$activeTab = in_array((string)($tab ?? 'nota'), ['nota', 'rincian'], true) ? (string)$tab : 'nota';

$statusBadgeClass = function (string $status): string {
    $status = strtoupper(trim($status));
    $map = [
        'DRAFT' => 'bg-secondary',
        'APPROVED' => 'bg-info',
        'ORDERED' => 'bg-primary',
        'REJECTED' => 'bg-danger',
        'PARTIAL_RECEIVED' => 'bg-warning text-dark',
        'RECEIVED' => 'bg-success',
        'PAID' => 'bg-success',
        'CLOSED' => 'bg-dark',
        'VOID' => 'bg-secondary',
    ];
    return $map[$status] ?? 'bg-secondary';
};

$canEditPo = !empty($current_user['is_superadmin']) || !empty($user_perms['purchase.order.index']['can_edit']);
?>

<style>
    .po-table-wrap {
        overflow-x: auto;
        overflow-y: visible;
    }
    .po-table {
        min-width: 1320px;
    }
    .po-table th,
    .po-table td {
        white-space: nowrap;
        vertical-align: middle;
    }
    .po-col-status {
        min-width: 220px;
    }
    .po-col-action {
        min-width: 240px;
    }
    .po-status-next {
        min-width: 170px;
    }
    .po-cell-actions {
        display: inline-flex;
        gap: 0.45rem;
        align-items: center;
        flex-wrap: nowrap;
    }
    .po-action-btn {
        min-width: 92px;
        padding: 0.34rem 0.74rem !important;
        line-height: 1.25;
        display: inline-flex;
        justify-content: center;
        align-items: center;
        overflow: visible;
    }
    .po-tab-link {
        font-weight: 700;
    }
    .po-status-pill {
        font-size: 0.72rem;
        letter-spacing: 0.04em;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-1">Purchase Dashboard</h4>
        <small class="text-muted">Monitoring ringkas purchase, rekening perusahaan, stok gudang, dan stok divisi.</small>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-primary" href="<?php echo site_url('purchase-orders/create'); ?>">Create Order</a>
        <a class="btn btn-outline-primary" href="<?php echo site_url('purchase-orders/receipt'); ?>">Halaman Receipt</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted d-block mb-1">Rekening Aktif</small>
                <h4 class="mb-1"><?php echo number_format((int)($s['accounts_active'] ?? 0)); ?> / <?php echo number_format((int)($s['accounts_total'] ?? 0)); ?></h4>
                <small class="text-muted">Saldo total: Rp <?php echo number_format((float)($s['accounts_balance'] ?? 0), 2, ',', '.'); ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted d-block mb-1">Stok Gudang (Profile)</small>
                <h4 class="mb-1"><?php echo number_format((int)($s['warehouse_profiles'] ?? 0)); ?></h4>
                <small class="text-muted">Qty isi total: <?php echo number_format((float)($s['warehouse_qty_content'] ?? 0), 4, ',', '.'); ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted d-block mb-1">Stok Divisi (Profile)</small>
                <h4 class="mb-1"><?php echo number_format((int)($s['division_profiles'] ?? 0)); ?></h4>
                <small class="text-muted">Qty isi total: <?php echo number_format((float)($s['division_qty_content'] ?? 0), 4, ',', '.'); ?></small>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h6 class="mb-0">Daftar Purchase Order</h6>
            <div class="d-flex gap-2">
                <input type="text" id="po-q" class="form-control form-control-sm" placeholder="Cari PO/vendor/tipe..." value="<?php echo html_escape((string)($q ?? '')); ?>">
                <select id="po-status" class="form-select form-select-sm">
                    <?php foreach (($status_options ?? ['ALL']) as $st): ?>
                        <option value="<?php echo html_escape((string)$st); ?>" <?php echo ((string)($status ?? 'ALL') === (string)$st) ? 'selected' : ''; ?>><?php echo html_escape((string)$st); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="btn-filter-po" class="btn btn-sm btn-outline-secondary">Filter</button>
            </div>
        </div>

        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link po-tab-link <?php echo $activeTab === 'nota' ? 'active' : ''; ?>" href="<?php echo site_url('purchase-orders') . '?tab=nota&q=' . urlencode((string)($q ?? '')) . '&status=' . urlencode((string)($status ?? 'ALL')); ?>">Per Nota</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link po-tab-link <?php echo $activeTab === 'rincian' ? 'active' : ''; ?>" href="<?php echo site_url('purchase-orders') . '?tab=rincian&q=' . urlencode((string)($q ?? '')) . '&status=' . urlencode((string)($status ?? 'ALL')); ?>">Per Rincian</a>
            </li>
        </ul>

        <div class="tab-content p-0 border-0">
            <div class="tab-pane fade <?php echo $activeTab === 'nota' ? 'show active' : ''; ?>" id="po-tab-nota" role="tabpanel">
                <div class="po-table-wrap">
                    <table class="table table-sm table-striped align-middle po-table">
                        <thead>
                            <tr>
                                <th>PO</th>
                                <th>Tanggal</th>
                                <th>Tipe Purchase</th>
                                <th>Vendor</th>
                                <th>Tujuan</th>
                                <th>Metode Bayar</th>
                                <th class="text-end">Grand Total</th>
                                <th class="po-col-status">Status</th>
                                <th class="po-col-action">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows ?? [])): ?>
                                <tr><td colspan="9" class="text-center text-muted py-3">Belum ada data PO.</td></tr>
                            <?php else: ?>
                                <?php foreach (($rows ?? []) as $r): ?>
                                    <?php $statusCurrent = strtoupper((string)($r['status'] ?? 'DRAFT')); ?>
                                    <?php $canEditData = in_array($statusCurrent, ['DRAFT', 'APPROVED'], true); ?>
                                    <tr>
                                        <td><strong><?php echo html_escape((string)($r['po_no'] ?? '-')); ?></strong></td>
                                        <td><?php echo html_escape((string)($r['request_date'] ?? '-')); ?></td>
                                        <td><?php echo html_escape((string)($r['purchase_type_name'] ?? '-')); ?></td>
                                        <td><?php echo html_escape((string)($r['vendor_name'] ?? '-')); ?></td>
                                        <td><?php echo html_escape((string)($r['destination_type'] ?? '-')); ?></td>
                                        <td><?php echo html_escape(trim((string)($r['payment_account_code'] ?? '-') . ' ' . (string)($r['payment_account_name'] ?? ''))); ?></td>
                                        <td class="text-end"><?php echo number_format((float)($r['grand_total'] ?? 0), 2, ',', '.'); ?></td>
                                        <td>
                                            <div class="mb-1"><span class="badge <?php echo $statusBadgeClass($statusCurrent); ?> po-status-pill"><?php echo html_escape($statusCurrent); ?></span></div>
                                            <?php
                                                $transitions = [
                                                    'DRAFT' => ['APPROVED', 'ORDERED', 'PARTIAL_RECEIVED', 'RECEIVED', 'PAID', 'REJECTED', 'VOID'],
                                                    'APPROVED' => ['ORDERED', 'PARTIAL_RECEIVED', 'RECEIVED', 'PAID', 'REJECTED', 'VOID'],
                                                    'ORDERED' => ['PARTIAL_RECEIVED', 'RECEIVED', 'PAID', 'REJECTED', 'VOID'],
                                                    'PARTIAL_RECEIVED' => ['RECEIVED', 'PAID', 'VOID'],
                                                    'RECEIVED' => ['PAID', 'VOID'],
                                                    'PAID' => ['VOID'],
                                                    'CLOSED' => ['VOID'],
                                                    'REJECTED' => [],
                                                    'VOID' => [],
                                                ];
                                                $nextOptions = [];
                                                foreach (($transitions[$statusCurrent] ?? []) as $nextStatus) {
                                                    if (!in_array($nextStatus, $nextOptions, true)) {
                                                        $nextOptions[] = $nextStatus;
                                                    }
                                                }
                                            ?>
                                            <select class="form-select form-select-sm po-status-next" data-id="<?php echo (int)($r['id'] ?? 0); ?>" <?php echo empty($nextOptions) ? 'disabled' : ''; ?>>
                                                <option value="">Pilih status...</option>
                                                <?php foreach ($nextOptions as $st): ?>
                                                    <option value="<?php echo html_escape($st); ?>"><?php echo html_escape($st); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <div class="po-cell-actions">
                                                <a href="<?php echo site_url('purchase-orders/detail/' . (int)($r['id'] ?? 0)); ?>" class="btn btn-sm btn-outline-secondary po-action-btn">Detail</a>
                                                <?php if ($canEditPo && $canEditData): ?>
                                                    <a href="<?php echo site_url('purchase-orders/edit/' . (int)($r['id'] ?? 0)); ?>" class="btn btn-sm btn-outline-warning po-action-btn">Edit Data</a>
                                                <?php endif; ?>
                                                <?php if ($canEditPo && !empty($nextOptions)): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-primary btn-po-status-update po-action-btn" data-id="<?php echo (int)($r['id'] ?? 0); ?>">Update</button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary po-action-btn" disabled title="Butuh izin edit">Update</button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade <?php echo $activeTab === 'rincian' ? 'show active' : ''; ?>" id="po-tab-rincian" role="tabpanel">
                <div class="po-table-wrap">
                    <table class="table table-sm table-striped align-middle po-table">
                        <thead>
                            <tr>
                                <th>PO</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th>Tipe</th>
                                <th>Vendor</th>
                                <th>Line</th>
                                <th>Nama Snapshot</th>
                                <th>Merk</th>
                                <th>Keterangan</th>
                                <th class="text-end">Qty Beli</th>
                                <th>UOM</th>
                                <th class="text-end">Harga</th>
                                <th class="text-end">Subtotal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($line_rows ?? [])): ?>
                                <tr><td colspan="14" class="text-center text-muted py-3">Belum ada data rincian PO.</td></tr>
                            <?php else: ?>
                                <?php foreach (($line_rows ?? []) as $lr): ?>
                                    <?php
                                        $st = strtoupper((string)($lr['status'] ?? 'DRAFT'));
                                        $lineName = trim((string)($lr['snapshot_item_name'] ?? ''));
                                        if ($lineName === '') {
                                            $lineName = trim((string)($lr['snapshot_material_name'] ?? '-'));
                                        }
                                    ?>
                                    <tr>
                                        <td><strong><?php echo html_escape((string)($lr['po_no'] ?? '-')); ?></strong></td>
                                        <td><?php echo html_escape((string)($lr['request_date'] ?? '-')); ?></td>
                                        <td><span class="badge <?php echo $statusBadgeClass($st); ?> po-status-pill"><?php echo html_escape($st); ?></span></td>
                                        <td><?php echo html_escape((string)($lr['purchase_type_name'] ?? '-')); ?></td>
                                        <td><?php echo html_escape((string)($lr['vendor_name'] ?? '-')); ?></td>
                                        <td>#<?php echo (int)($lr['line_no'] ?? 0); ?></td>
                                        <td><?php echo html_escape($lineName); ?></td>
                                        <td><?php echo html_escape((string)($lr['snapshot_brand_name'] ?? '-')); ?></td>
                                        <td><?php echo html_escape((string)($lr['snapshot_line_description'] ?? '-')); ?></td>
                                        <td class="text-end"><?php echo number_format((float)($lr['qty_buy'] ?? 0), 2, ',', '.'); ?></td>
                                        <td><?php echo html_escape((string)($lr['snapshot_buy_uom_code'] ?? '-')); ?></td>
                                        <td class="text-end"><?php echo number_format((float)($lr['unit_price'] ?? 0), 2, ',', '.'); ?></td>
                                        <td class="text-end"><?php echo number_format((float)($lr['line_subtotal'] ?? 0), 2, ',', '.'); ?></td>
                                        <td><a href="<?php echo site_url('purchase-orders/detail/' . (int)($lr['purchase_order_id'] ?? 0)); ?>" class="btn btn-sm btn-outline-secondary">Detail</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    function parseJsonResponse(response) {
        return response.text().then(function (txt) {
            var parsed = null;
            try {
                parsed = JSON.parse(txt);
            } catch (e) {
                var snippet = (txt || '').replace(/\s+/g, ' ').trim().slice(0, 180);
                throw new Error('Respons server bukan JSON valid. ' + snippet);
            }
            return { status: response.status, json: parsed };
        });
    }

    var currentTab = <?php echo json_encode($activeTab); ?>;
    var filterBtn = document.getElementById('btn-filter-po');
    if (filterBtn) {
        filterBtn.addEventListener('click', function () {
            var q = document.getElementById('po-q').value || '';
            var status = document.getElementById('po-status').value || 'ALL';
            var url = <?php echo json_encode(site_url('purchase-orders')); ?>
                + '?tab=' + encodeURIComponent(currentTab)
                + '&q=' + encodeURIComponent(q)
                + '&status=' + encodeURIComponent(status);
            window.location.href = url;
        });
    }

    var canEditPo = <?php echo $canEditPo ? 'true' : 'false'; ?>;
    if (!canEditPo) {
        document.querySelectorAll('.po-status-next').forEach(function (el) {
            el.disabled = true;
        });
    }

    var endpoint = <?php echo json_encode(site_url('purchase/order/status-update')); ?>;
    document.querySelectorAll('.btn-po-status-update').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!canEditPo) {
                window.alert('Akses edit status tidak tersedia untuk akun ini.');
                return;
            }

            var id = Number(btn.getAttribute('data-id') || 0);
            if (!id) {
                return;
            }

            var select = document.querySelector('.po-status-next[data-id="' + id + '"]');
            var status = select ? (select.value || '') : '';
            if (!status) {
                window.alert('Pilih status tujuan terlebih dahulu.');
                return;
            }

            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ purchase_order_id: id, status: status })
            })
            .then(parseJsonResponse)
            .then(function (res) {
                if (res.status >= 400 || !res.json || !res.json.ok) {
                    throw new Error((res.json && res.json.message) ? res.json.message : 'Gagal update status');
                }
                window.location.reload();
            })
            .catch(function (err) {
                window.alert(err.message || 'Gagal update status');
            });
        });
    });
})();
</script>
