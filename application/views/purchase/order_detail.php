<?php
$detail = (array)($detail ?? []);
$order = (array)($detail['order'] ?? []);
$lines = (array)($detail['lines'] ?? []);
$payments = (array)($detail['payments'] ?? []);
$receipts = (array)($detail['receipts'] ?? []);
$outstanding = (float)($detail['outstanding'] ?? 0);
$auditRows = (array)($detail['audit_rows'] ?? []);

$currentStatus = strtoupper((string)($order['status'] ?? 'DRAFT'));
$timelineStatus = $currentStatus === 'CLOSED' ? 'PAID' : $currentStatus;
$canEditData = in_array($currentStatus, ['DRAFT', 'APPROVED'], true);
$statusOrder = ['DRAFT', 'APPROVED', 'ORDERED', 'PARTIAL_RECEIVED', 'RECEIVED', 'PAID'];
$statusRank = array_flip($statusOrder);
$currentRank = $statusRank[$timelineStatus] ?? -1;
$isTerminal = in_array($currentStatus, ['REJECTED', 'VOID'], true);

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

$timeline = [
    ['code' => 'DRAFT', 'label' => 'PO dibuat', 'at' => $order['created_at'] ?? null],
    ['code' => 'APPROVED', 'label' => 'PO disetujui', 'at' => $order['approved_at'] ?? null],
    ['code' => 'ORDERED', 'label' => 'PO dipesan', 'at' => null],
    ['code' => 'PARTIAL_RECEIVED', 'label' => 'Barang diterima sebagian', 'at' => $receipts['last_receipt_at'] ?? null],
    ['code' => 'RECEIVED', 'label' => 'Barang diterima penuh', 'at' => $receipts['last_receipt_at'] ?? null],
    ['code' => 'PAID', 'label' => 'Pembayaran lunas', 'at' => $payments['last_payment_date'] ?? null],
];

$statusTransitions = [
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
$statusUpdateOptions = [$currentStatus];
foreach (($statusTransitions[$currentStatus] ?? []) as $nextStatus) {
    if (!in_array($nextStatus, $statusUpdateOptions, true)) {
        $statusUpdateOptions[] = $nextStatus;
    }
}
?>

<style>
    .po-timeline {
        list-style: none;
        margin: 0;
        padding: 0;
        position: relative;
    }
    .po-timeline::before {
        content: '';
        position: absolute;
        left: 12px;
        top: 8px;
        bottom: 8px;
        width: 2px;
        background: rgba(168, 142, 132, 0.45);
    }
    .po-timeline-item {
        position: relative;
        padding-left: 2.2rem;
        margin-bottom: 1rem;
    }
    .po-timeline-item:last-child {
        margin-bottom: 0;
    }
    .po-timeline-dot {
        position: absolute;
        left: 6px;
        top: 4px;
        width: 14px;
        height: 14px;
        border-radius: 999px;
        border: 2px solid #d8c8c0;
        background: #fff;
    }
    .po-timeline-item.is-done .po-timeline-dot,
    .po-timeline-item.is-current .po-timeline-dot {
        border-color: #922d21;
        background: #b03025;
    }
    .po-timeline-item.is-pending {
        opacity: 0.6;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-1">Detail Purchase Order</h4>
        <small class="text-muted">Timeline status dan rincian line purchase order.</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo site_url('purchase-orders'); ?>" class="btn btn-outline-secondary">Kembali ke Purchase Orders</a>
        <?php if ($canEditPo && $canEditData): ?>
            <a href="<?php echo site_url('purchase-orders/edit/' . (int)($order['id'] ?? 0)); ?>" class="btn btn-outline-warning">Edit Data PO</a>
        <?php endif; ?>
        <a href="<?php echo site_url('purchase-orders/create'); ?>" class="btn btn-primary">Create Order</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted d-block mb-1">No PO</small>
                <h5 class="mb-0"><?php echo html_escape((string)($order['po_no'] ?? '-')); ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted d-block mb-1">Status Saat Ini</small>
                <span class="badge <?php echo $statusBadgeClass($currentStatus); ?>"><?php echo html_escape($currentStatus); ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted d-block mb-1">Grand Total</small>
                <h5 class="mb-0">Rp <?php echo number_format((float)($order['grand_total'] ?? 0), 2, ',', '.'); ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted d-block mb-1">Outstanding</small>
                <h5 class="mb-0">Rp <?php echo number_format($outstanding, 2, ',', '.'); ?></h5>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body d-flex flex-wrap align-items-end gap-2">
        <div>
            <label class="form-label mb-1">Update Status PO</label>
            <select id="po-detail-status-next" class="form-select form-select-sm" style="min-width: 220px;" <?php echo $canEditPo ? '' : 'disabled'; ?>>
                <?php foreach ($statusUpdateOptions as $st): ?>
                    <option value="<?php echo html_escape((string)$st); ?>" <?php echo $st === $currentStatus ? 'selected' : ''; ?>><?php echo html_escape((string)$st); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="button" id="btn-po-detail-status-update" class="btn btn-outline-primary btn-sm" <?php echo $canEditPo ? '' : 'disabled'; ?>>Update Status</button>
        <?php if ($currentStatus === 'PAID'): ?>
            <button type="button" id="btn-po-detail-paid-reconcile" class="btn btn-outline-warning btn-sm" <?php echo $canEditPo ? '' : 'disabled'; ?>>Sinkronkan Dampak PAID</button>
        <?php endif; ?>
        <?php if ($canEditPo): ?>
            <small class="text-muted">Jika update dari halaman list gagal, gunakan form ini sebagai fallback.</small>
        <?php else: ?>
            <small class="text-muted">Akun ini belum memiliki izin edit status PO.</small>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="mb-3">Timeline Status</h6>
                <ul class="po-timeline">
                    <?php foreach ($timeline as $step): ?>
                        <?php
                            $code = (string)($step['code'] ?? '');
                            $rank = $statusRank[$code] ?? -1;
                            $stateClass = 'is-pending';
                            if ($isTerminal) {
                                $stateClass = $code === 'DRAFT' ? 'is-done' : 'is-pending';
                            } else {
                                if ($rank > -1 && $rank < $currentRank) {
                                    $stateClass = 'is-done';
                                } elseif ($rank > -1 && $rank === $currentRank) {
                                    $stateClass = 'is-current';
                                }
                            }
                        ?>
                        <li class="po-timeline-item <?php echo $stateClass; ?>">
                            <span class="po-timeline-dot"></span>
                            <div class="fw-semibold"><?php echo html_escape((string)$step['label']); ?></div>
                            <small class="text-muted"><?php echo !empty($step['at']) ? html_escape((string)$step['at']) : '-'; ?></small>
                        </li>
                    <?php endforeach; ?>
                    <?php if ($isTerminal): ?>
                        <li class="po-timeline-item is-current">
                            <span class="po-timeline-dot"></span>
                            <div class="fw-semibold"><?php echo $currentStatus === 'REJECTED' ? 'PO ditolak' : 'PO dibatalkan'; ?></div>
                            <small class="text-muted"><?php echo html_escape((string)($order['updated_at'] ?? '-')); ?></small>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="mb-2">Ringkasan Header</h6>
                <div class="row g-2">
                    <div class="col-md-6"><small class="text-muted d-block">Tanggal PO</small><strong><?php echo html_escape((string)($order['request_date'] ?? '-')); ?></strong></div>
                    <div class="col-md-6"><small class="text-muted d-block">Tipe Purchase</small><strong><?php echo html_escape((string)($order['purchase_type_name'] ?? '-')); ?></strong></div>
                    <div class="col-md-6"><small class="text-muted d-block">Vendor</small><strong><?php echo html_escape((string)($order['vendor_name'] ?? '-')); ?></strong></div>
                    <div class="col-md-6"><small class="text-muted d-block">Tujuan</small><strong><?php echo html_escape((string)($order['destination_type'] ?? '-')); ?></strong></div>
                    <div class="col-md-6"><small class="text-muted d-block">Metode Bayar</small><strong><?php echo html_escape(trim((string)($order['payment_account_code'] ?? '-') . ' ' . (string)($order['payment_account_name'] ?? ''))); ?></strong></div>
                    <div class="col-md-6"><small class="text-muted d-block">Pembayaran Tercatat</small><strong>Rp <?php echo number_format((float)($payments['total_paid'] ?? 0), 2, ',', '.'); ?></strong></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h6 class="mb-2">Line Purchase Order</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nama Snapshot</th>
                                <th>Merk</th>
                                <th>Keterangan</th>
                                <th class="text-end">Qty Beli</th>
                                <th>UOM</th>
                                <th class="text-end">Harga</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($lines)): ?>
                                <tr><td colspan="8" class="text-center text-muted py-3">Tidak ada line PO.</td></tr>
                            <?php else: ?>
                                <?php foreach ($lines as $ln): ?>
                                    <?php
                                        $lineName = trim((string)($ln['snapshot_item_name'] ?? ''));
                                        if ($lineName === '') {
                                            $lineName = trim((string)($ln['snapshot_material_name'] ?? '-'));
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo (int)($ln['line_no'] ?? 0); ?></td>
                                        <td><?php echo html_escape($lineName); ?></td>
                                        <td><?php echo html_escape((string)($ln['snapshot_brand_name'] ?? '-')); ?></td>
                                        <td><?php echo html_escape((string)($ln['snapshot_line_description'] ?? '-')); ?></td>
                                        <td class="text-end"><?php echo number_format((float)($ln['qty_buy'] ?? 0), 2, ',', '.'); ?></td>
                                        <td><?php echo html_escape((string)($ln['snapshot_buy_uom_code'] ?? '-')); ?></td>
                                        <td class="text-end"><?php echo number_format((float)($ln['unit_price'] ?? 0), 2, ',', '.'); ?></td>
                                        <td class="text-end"><?php echo number_format((float)($ln['line_subtotal'] ?? 0), 2, ',', '.'); ?></td>
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

<?php if (!empty($auditRows)): ?>
<div class="card mt-3">
    <div class="card-body">
        <h6 class="mb-2">Audit Ringkas</h6>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Aksi</th>
                        <th>Ref</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($auditRows as $ar): ?>
                        <tr>
                            <td><?php echo html_escape((string)($ar['created_at'] ?? '-')); ?></td>
                            <td><?php echo html_escape((string)($ar['action_code'] ?? '-')); ?></td>
                            <td><?php echo html_escape((string)($ar['transaction_no'] ?? '-')); ?></td>
                            <td><?php echo html_escape((string)($ar['notes'] ?? '-')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

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

    var updateBtn = document.getElementById('btn-po-detail-status-update');
    var statusEl = document.getElementById('po-detail-status-next');
    if (!updateBtn || !statusEl) {
        return;
    }

    var canEditPo = <?php echo $canEditPo ? 'true' : 'false'; ?>;
    var endpoint = <?php echo json_encode(site_url('purchase/order/status-update')); ?>;
    var poId = <?php echo (int)($order['id'] ?? 0); ?>;
    var reconcileBtn = document.getElementById('btn-po-detail-paid-reconcile');

    updateBtn.addEventListener('click', function () {
        if (!canEditPo) {
            window.alert('Akses edit status tidak tersedia untuk akun ini.');
            return;
        }

        var nextStatus = String(statusEl.value || '').trim().toUpperCase();
        if (!poId || !nextStatus) {
            return;
        }

        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ purchase_order_id: poId, status: nextStatus })
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

    if (reconcileBtn) {
        reconcileBtn.addEventListener('click', function () {
            if (!canEditPo) {
                window.alert('Akses edit status tidak tersedia untuk akun ini.');
                return;
            }
            if (!poId) {
                return;
            }

            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ purchase_order_id: poId, status: 'PAID' })
            })
            .then(parseJsonResponse)
            .then(function (res) {
                if (res.status >= 400 || !res.json || !res.json.ok) {
                    throw new Error((res.json && res.json.message) ? res.json.message : 'Gagal sinkronkan dampak PAID');
                }
                window.location.reload();
            })
            .catch(function (err) {
                window.alert(err.message || 'Gagal sinkronkan dampak PAID');
            });
        });
    }
})();
</script>
