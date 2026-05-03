<?php
$baseUrl = site_url('purchase/stock/division');
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="ri ri-store-2-line page-title-icon"></i><?php echo html_escape($title); ?></h4>
    <small class="text-muted">Posisi stok divisi operasional per profile purchase.</small>
  </div>
  <div class="d-flex gap-2">
    <a href="<?php echo site_url('purchase-orders/receipt'); ?>" class="btn btn-primary">Receipt Purchase</a>
    <a href="<?php echo site_url('purchase-orders'); ?>" class="btn btn-outline-secondary">Purchase Orders</a>
    <a href="<?php echo site_url('master/company-account'); ?>" class="btn btn-outline-secondary">Rekening</a>
    <a href="<?php echo site_url('purchase/stock/division/movement'); ?>" class="btn btn-outline-secondary">Keluar Masuk Divisi</a>
    <a href="<?php echo site_url('purchase/stock/division/daily'); ?>" class="btn btn-outline-secondary">Stok Bulanan/Daily Divisi</a>
    <a href="<?php echo site_url('purchase/stock/warehouse'); ?>" class="btn btn-outline-secondary">Stok Gudang</a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body py-3">
    <form method="get" action="<?php echo $baseUrl; ?>" class="row g-2 align-items-end">
      <div class="col-md-8">
        <label class="form-label mb-1">Cari Stok Divisi</label>
        <input type="text" class="form-control" name="q" value="<?php echo html_escape((string)$q); ?>" placeholder="Divisi / Item / Material / Profile / Merk / Keterangan">
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Limit</label>
        <input type="number" min="1" max="500" class="form-control" name="limit" value="<?php echo (int)$limit; ?>">
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0">
      <thead>
        <tr>
          <th>Divisi</th>
          <th>Objek</th>
          <th>Profile</th>
          <th>Merk</th>
          <th>Keterangan</th>
          <th>Ukuran Isi</th>
          <th class="text-end">Qty Beli</th>
          <th class="text-end">Qty Isi</th>
          <th class="text-end">Avg Cost / Isi</th>
          <th>Update</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="10" class="text-center text-muted py-4">Belum ada data stok divisi.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <?php
              $divisionCode = trim((string)($r['division_code'] ?? ''));
              $divisionName = trim((string)($r['division_name'] ?? ''));
              $divisionText = $divisionCode;
              if ($divisionName !== '') {
                $divisionText .= ($divisionText !== '' ? ' - ' : '') . $divisionName;
              }
              if ($divisionText === '') {
                $divisionText = (string)($r['division_id'] ?? '-');
              }

              $itemCode = trim((string)($r['item_code'] ?? ''));
              $itemName = trim((string)($r['item_name'] ?? ''));
              $materialCode = trim((string)($r['material_code'] ?? ''));
              $materialName = trim((string)($r['material_name'] ?? ''));

              $itemText = trim(($itemCode !== '' ? $itemCode : '') . ($itemName !== '' ? ' - ' . $itemName : ''));
              $materialText = trim(($materialCode !== '' ? $materialCode : '') . ($materialName !== '' ? ' - ' . $materialName : ''));
              $objectText = $itemText !== '' ? $itemText : ($materialText !== '' ? $materialText : '-');
            ?>
            <tr>
              <td><?php echo html_escape($divisionText); ?></td>
              <td><?php echo html_escape($objectText); ?></td>
              <td><?php echo html_escape((string)($r['profile_name'] ?? '-')); ?></td>
              <td><?php echo html_escape((string)($r['profile_brand'] ?? '-')); ?></td>
              <td><?php echo html_escape((string)($r['profile_description'] ?? '-')); ?></td>
              <td><?php echo html_escape((string)($r['profile_content_per_buy'] ?? '-')); ?> <?php echo html_escape((string)($r['profile_content_uom_code'] ?? '')); ?> / <?php echo html_escape((string)($r['profile_buy_uom_code'] ?? '')); ?></td>
              <td class="text-end"><?php echo number_format((float)$r['qty_buy_balance'], 4, ',', '.'); ?></td>
              <td class="text-end"><?php echo number_format((float)$r['qty_content_balance'], 4, ',', '.'); ?></td>
              <td class="text-end"><?php echo number_format((float)$r['avg_cost_per_content'], 6, ',', '.'); ?></td>
              <td><?php echo html_escape((string)($r['updated_at'] ?? '-')); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
