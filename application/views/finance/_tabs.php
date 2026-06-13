<?php
$active = (string)($finance_tab_active ?? '');
?>

<div class="d-flex flex-wrap gap-2 mb-3">
  <a href="<?php echo site_url('finance/utang'); ?>" class="btn btn-sm <?php echo $active === 'payable' ? 'btn-primary' : 'btn-outline-primary'; ?>">Utang</a>
  <a href="<?php echo site_url('finance/piutang'); ?>" class="btn btn-sm <?php echo $active === 'receivable' ? 'btn-primary' : 'btn-outline-primary'; ?>">Piutang</a>
  <a href="<?php echo site_url('finance/relasi'); ?>" class="btn btn-sm <?php echo $active === 'party' ? 'btn-primary' : 'btn-outline-primary'; ?>">Pihak Luar</a>
  <a href="<?php echo site_url('finance-reports/cash-position'); ?>" class="btn btn-sm <?php echo $active === 'cash-position' ? 'btn-primary' : 'btn-outline-primary'; ?>">Posisi Kas & Eksposur</a>
  <a href="<?php echo site_url('finance-reports/cash-vault-daily'); ?>" class="btn btn-sm <?php echo $active === 'cash-vault' ? 'btn-primary' : 'btn-outline-primary'; ?>">Brankas Harian</a>
</div>
