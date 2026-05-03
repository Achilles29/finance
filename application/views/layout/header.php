<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
<title><?= htmlspecialchars($title ?? 'Finance') ?> — Finance App</title>
<link rel="icon" type="image/x-icon" href="<?= base_url('assets/img/favicon.ico') ?>">

<!-- Google Fonts: Plus Jakarta Sans + Merriweather -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@700;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Remix Icons (Iconify) -->
<link rel="stylesheet" href="<?= base_url('assets/vendor/fonts/iconify-icons.css') ?>">

<!-- Materio Core CSS (Bootstrap included) -->
<link rel="stylesheet" href="<?= base_url('assets/vendor/libs/node-waves/node-waves.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/css/core.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') ?>">

<!-- DataTables -->
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">

<!-- Finance Theme (merah + cream) -->
<link rel="stylesheet" href="<?= base_url('assets/css/theme-custom.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">

<!-- Materio Helpers (sebelum body) -->
<script src="<?= base_url('assets/vendor/js/helpers.js') ?>"></script>
<script src="<?= base_url('assets/js/config.js') ?>"></script>
</head>
<body>
