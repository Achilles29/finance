<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title><?php echo html_escape((string)($title ?? 'Print QR Meja')); ?></title>
  <style>
    body{font-family:'Segoe UI',Tahoma,sans-serif;margin:0;padding:20px;color:#1f2937}
    .sheet{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
    .card{border:1px solid #d9c7bc;border-radius:18px;padding:16px;text-align:center;page-break-inside:avoid}
    .card h3{margin:0 0 4px;font-size:20px}
    .card p{margin:0 0 12px;color:#6b7280;font-size:13px}
    .card img{width:220px;height:220px;object-fit:cover;display:block;margin:0 auto 12px}
    .code{font-size:12px;word-break:break-all;color:#6b7280}
    @media print {
      body{padding:0}
      .sheet{gap:10px}
      .card{break-inside:avoid}
    }
  </style>
</head>
<body onload="window.print()">
  <div class="sheet">
    <?php foreach ((array)($rows ?? []) as $row): ?>
      <?php $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=' . rawurlencode((string)($row['qr_url'] ?? '')); ?>
      <div class="card">
        <h3><?php echo html_escape((string)($row['nama_meja'] ?? 'Meja')); ?></h3>
        <p><?php echo html_escape((string)($row['qr_label'] ?? 'Scan untuk order mandiri')); ?></p>
        <img src="<?php echo $qrUrl; ?>" alt="QR <?php echo html_escape((string)($row['nama_meja'] ?? '')); ?>">
        <div class="code"><?php echo html_escape((string)($row['qr_url'] ?? '')); ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</body>
</html>
