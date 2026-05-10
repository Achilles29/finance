<?php
$row = $row ?? [];
$approvalMap = $approval_map ?? [];
$verifyUrl = $verify_url ?? '';
$ctx = $ctx ?? 'finance';
$status = strtoupper((string)($row['status'] ?? 'DRAFT'));

$qrCenterUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=108x108&data=' . rawurlencode((string)$verifyUrl);
$qrFooterUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=56x56&data=' . rawurlencode((string)$verifyUrl);
?><!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Kontrak <?php echo htmlspecialchars((string)($row['contract_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: "Times New Roman", Times, serif; font-size: 12pt; line-height: 1.8; color: #000; background: #fff; }
    .paper { max-width: 210mm; margin: 0 auto; padding: 20mm 24mm; }
    .header { text-align: center; margin-bottom: 14pt; border-bottom: 2px solid #000; padding-bottom: 10pt; }
    .header .brand { font-family: Arial, sans-serif; font-size: 16pt; font-weight: 700; }
    .header .sub { font-family: Arial, sans-serif; font-size: 10pt; color: #555; }
    .header .title { font-family: Arial, sans-serif; font-size: 11pt; font-weight: 700; margin-top: 6pt; }
    .meta { border: 1px solid #ddd; border-radius: 8px; padding: 8pt 10pt; margin-bottom: 10pt; font-family: Arial, sans-serif; font-size: 9pt; color: #444; line-height: 1.5; }
    .doc { border: 1px solid #ddd; border-radius: 8px; padding: 14pt 16pt; background: #fff; }
    .tte-note { margin-top: 10pt; border: 1px dashed #bbb; border-radius: 8px; padding: 8pt 10pt; font-family: Arial, sans-serif; font-size: 9pt; color: #555; line-height: 1.45; }
    .sign-grid { display: flex; gap: 16pt; align-items: flex-end; margin-top: 20pt; page-break-inside: avoid; }
    .sign-item { flex: 1; text-align: center; }
    .sign-title { font-family: Arial, sans-serif; font-size: 10pt; font-weight: 700; margin-bottom: 6pt; }
    .sign-box { min-height: 62pt; border-bottom: 1px solid #000; display: flex; align-items: center; justify-content: center; font-family: Arial, sans-serif; }
    .sign-name { font-family: Arial, sans-serif; font-size: 10pt; font-weight: 700; margin-top: 4pt; }
    .sign-time { font-family: Arial, sans-serif; font-size: 8.5pt; color: #666; margin-top: 2pt; }
    .verify-col { width: 150pt; min-width: 150pt; text-align: center; }
    .verify-note { font-family: Arial, sans-serif; font-size: 8.5pt; color: #5a4040; line-height: 1.45; margin-top: 5pt; }
    .footer-block { margin-top: 18pt; border-top: 1px solid #ccc; padding-top: 8pt; display: flex; align-items: center; gap: 10pt; font-family: Arial, sans-serif; font-size: 8.5pt; color: #666; page-break-inside: avoid; }
    .footer-meta { flex: 1; }
    .watermark { position: fixed; top: 48%; left: 50%; transform: translate(-50%, -50%) rotate(-28deg); font-family: Arial, sans-serif; font-size: 74pt; font-weight: 900; color: rgba(190,0,0,.08); letter-spacing: 2px; pointer-events: none; }
    .print-bar { display: flex; gap: 8px; justify-content: center; position: fixed; top: 0; left: 0; right: 0; background: #f3f3f3; padding: 10px; z-index: 9; }
    .print-bar .btn { border: 1px solid #bbb; border-radius: 5px; padding: 7px 12px; text-decoration: none; font-family: Arial, sans-serif; font-size: 13px; }
    .btn-primary { background: #206bc4; color: #fff; border-color: #206bc4 !important; }
    .btn-light { background: #fff; color: #333; }
    @page { size: A4; margin: 0; }
    @media print {
      .print-bar { display: none !important; }
      .paper { margin-top: 0; padding: 15mm 20mm; }
      .watermark { display: <?php echo in_array($status, ['ACTIVE', 'SIGNED'], true) ? 'none' : 'block'; ?>; }
      body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
    }
  </style>
</head>
<body>
  <?php if (!in_array($status, ['ACTIVE', 'SIGNED'], true)): ?>
    <div class="watermark"><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <div class="print-bar">
    <button class="btn btn-primary" onclick="window.print()">Print / Simpan PDF</button>
    <a class="btn btn-light" href="<?php echo htmlspecialchars(site_url('hr-contracts/view/' . (int)($row['id'] ?? 0) . '?ctx=' . urlencode((string)$ctx)), ENT_QUOTES, 'UTF-8'); ?>">Kembali</a>
  </div>

  <div class="paper" style="margin-top:52px;">
    <div class="header">
      <div class="brand">NAMUA COFFEE AND EATERY</div>
      <div class="sub">Dokumen Kontrak Pegawai Terverifikasi Sistem</div>
      <div class="title">PKWT • No: <?php echo htmlspecialchars((string)($row['contract_number'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
    </div>

    <div class="meta">
      Pegawai: <strong><?php echo htmlspecialchars((string)($row['employee_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></strong> (<?php echo htmlspecialchars((string)($row['employee_code'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>)
      &nbsp;•&nbsp; Jenis: <?php echo htmlspecialchars((string)($row['contract_type'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>
      &nbsp;•&nbsp; Periode: <?php echo htmlspecialchars((string)($row['start_date'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?> s/d <?php echo htmlspecialchars((string)($row['end_date'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>
      <br>
      Issued: <?php echo htmlspecialchars((string)($row['document_issued_at'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>
      &nbsp;•&nbsp; Status: <strong><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></strong>
    </div>

    <div class="doc">
      <?php echo (string)($row['body_html'] ?? ''); ?>
    </div>

    <div class="tte-note">
      Pengesahan dokumen ini menggunakan TTE sistem (approval digital + hash dokumen + QR verifikasi). Tanda tangan gambar manual tidak menjadi acuan utama legalitas dokumen.
    </div>

    <div class="sign-grid">
      <div class="sign-item">
        <div class="sign-title">PIHAK PERTAMA (COMPANY)</div>
        <div class="sign-box">
          <?php if (!empty($approvalMap['COMPANY']) && strtoupper((string)($approvalMap['COMPANY']['approval_status'] ?? '')) === 'APPROVED'): ?>
            <div>
              <div style="font-weight:700"><?php echo htmlspecialchars((string)($approvalMap['COMPANY']['approver_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
              <div style="font-size:8.5pt; color:#666;">Disahkan Sistem</div>
            </div>
          <?php else: ?>
            <span style="font-size:9pt; color:#999;">Belum Disahkan</span>
          <?php endif; ?>
        </div>
        <div class="sign-name">Anis Fitriya</div>
        <div class="sign-time"><?php echo !empty($approvalMap['COMPANY']['approved_at']) ? htmlspecialchars((string)$approvalMap['COMPANY']['approved_at'], ENT_QUOTES, 'UTF-8') : '-'; ?></div>
      </div>

      <div class="verify-col">
        <div style="display:inline-block; margin-bottom:6pt;"><img src="<?php echo htmlspecialchars($qrCenterUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="QR Verifikasi Kontrak" width="108" height="108"></div>
        <div class="verify-note">Scan QR untuk membuka halaman verifikasi TTE dokumen ini.</div>
        <div style="font-family:Arial,sans-serif;font-size:8pt;color:#666;margin-top:4pt;word-break:break-all;"><?php echo htmlspecialchars((string)$verifyUrl, ENT_QUOTES, 'UTF-8'); ?></div>
      </div>

      <div class="sign-item">
        <div class="sign-title">PIHAK KEDUA (EMPLOYEE)</div>
        <div class="sign-box">
          <?php if (!empty($approvalMap['EMPLOYEE']) && strtoupper((string)($approvalMap['EMPLOYEE']['approval_status'] ?? '')) === 'APPROVED'): ?>
            <div>
              <div style="font-weight:700"><?php echo htmlspecialchars((string)($approvalMap['EMPLOYEE']['approver_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
              <div style="font-size:8.5pt; color:#666;">Disahkan Sistem</div>
            </div>
          <?php else: ?>
            <span style="font-size:9pt; color:#999;">Belum Disahkan</span>
          <?php endif; ?>
        </div>
        <div class="sign-name"><?php echo htmlspecialchars((string)($row['employee_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="sign-time"><?php echo !empty($approvalMap['EMPLOYEE']['approved_at']) ? htmlspecialchars((string)$approvalMap['EMPLOYEE']['approved_at'], ENT_QUOTES, 'UTF-8') : '-'; ?></div>
      </div>
    </div>

    <div class="footer-block">
      <div class="footer-meta">
        <div><strong><?php echo htmlspecialchars((string)($row['contract_number'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></strong> • Dokumen diverifikasi QR + pengesahan sistem</div>
        <div>Hash: <?php echo htmlspecialchars((string)($row['final_document_hash'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
      </div>
      <div><img src="<?php echo htmlspecialchars($qrFooterUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="QR Verifikasi" width="56" height="56"></div>
    </div>
  </div>
</body>
</html>
