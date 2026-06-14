<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Book — NAMUA Coffee & Eatery</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Georgia', 'Times New Roman', serif;
            background: #F8F1E8;
            color: #3A2E22;
            min-height: 100vh;
            padding: 40px 20px;
        }

        .mb-header {
            text-align: center;
            margin-bottom: 48px;
        }

        .mb-header .logo {
            height: 64px;
            margin-bottom: 16px;
        }

        .mb-header h1 {
            font-size: 13px;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: #C9A86A;
            margin-bottom: 8px;
        }

        .mb-header h2 {
            font-size: 28px;
            font-weight: 700;
            color: #7D1F1F;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .mb-header .subtitle {
            font-size: 13px;
            color: #6A4E3A;
            margin-top: 6px;
            font-style: italic;
        }

        .mb-divider {
            width: 80px;
            height: 2px;
            background: linear-gradient(to right, transparent, #C9A86A, transparent);
            margin: 20px auto;
        }

        .page-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            max-width: 1100px;
            margin: 0 auto;
        }

        .page-card {
            background: #fff;
            border: 1px solid #E8D5B0;
            border-top: 3px solid #C9A86A;
            border-radius: 4px;
            padding: 24px 20px;
            text-decoration: none;
            color: inherit;
            display: block;
            transition: box-shadow .2s, transform .2s;
            position: relative;
        }

        .page-card:hover {
            box-shadow: 0 6px 24px rgba(125,31,31,.12);
            transform: translateY(-3px);
        }

        .page-card.done { border-top-color: #7D1F1F; }
        .page-card.wip  { border-top-color: #C9A86A; }
        .page-card.plan { border-top-color: #D0C5B5; opacity: .75; pointer-events: none; }

        .page-num {
            font-size: 11px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #C9A86A;
            margin-bottom: 6px;
        }

        .page-title-name {
            font-size: 17px;
            font-weight: 700;
            color: #7D1F1F;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .page-subtitle {
            font-size: 12px;
            color: #6A4E3A;
            font-style: italic;
            margin-bottom: 12px;
        }

        .page-categories {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .cat-tag {
            font-size: 10px;
            letter-spacing: 1px;
            text-transform: uppercase;
            background: #F8F1E8;
            border: 1px solid #E8D5B0;
            color: #6A4E3A;
            padding: 2px 8px;
            border-radius: 2px;
        }

        .status-badge {
            position: absolute;
            top: 14px;
            right: 14px;
            font-size: 9px;
            letter-spacing: 2px;
            text-transform: uppercase;
            padding: 3px 8px;
            border-radius: 2px;
            font-style: normal;
        }

        .status-done { background: #7D1F1F; color: #fff; }
        .status-wip  { background: #C9A86A; color: #fff; }
        .status-plan { background: #E8D5B0; color: #6A4E3A; }

        .mb-footer {
            text-align: center;
            margin-top: 56px;
            font-size: 11px;
            color: #9E856A;
            letter-spacing: 2px;
        }

        .open-icon {
            font-size: 11px;
            color: #C9A86A;
            margin-top: 14px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        @media print { body { display: none; } }
    </style>
</head>
<body>

<header class="mb-header">
    <img src="<?= base_url('assets/menu-book/logo/logo.png') ?>" class="logo" alt="NAMUA Logo" onerror="this.style.display='none'">
    <h1>NAMUA Coffee & Eatery</h1>
    <div class="mb-divider"></div>
    <h2>Menu Book</h2>
    <p class="subtitle">Halaman-halaman buku menu digital — klik untuk membuka di tab baru</p>
</header>

<div class="page-grid">

    <!-- Page 2 — DONE -->
    <a href="<?= site_url('menu_book/food/main_character') ?>" target="_blank" class="page-card done">
        <span class="status-badge status-done">Selesai</span>
        <div class="page-num">Halaman 02</div>
        <div class="page-title-name">Main Character</div>
        <div class="page-subtitle">The Icons of NAMUA</div>
        <div class="page-categories">
            <span class="cat-tag">Main Character</span>
            <span class="cat-tag">6 produk</span>
            <span class="cat-tag">6 gambar</span>
        </div>
        <div class="open-icon">&#8599; Buka halaman</div>
    </a>

    <!-- Page 3 — Planned -->
    <span class="page-card plan">
        <span class="status-badge status-plan">Belum dibuat</span>
        <div class="page-num">Halaman 03</div>
        <div class="page-title-name">Nusantara &amp; Comfort Food</div>
        <div class="page-subtitle">Indonesian Heritage · Crave Corner</div>
        <div class="page-categories">
            <span class="cat-tag">Indonesian Heritage</span>
            <span class="cat-tag">Crave Corner</span>
            <span class="cat-tag">13 produk</span>
        </div>
    </span>

    <!-- Page 4 — Planned -->
    <span class="page-card plan">
        <span class="status-badge status-plan">Belum dibuat</span>
        <div class="page-num">Halaman 04</div>
        <div class="page-title-name">Flame &amp; Flavor</div>
        <div class="page-subtitle">Munch &amp; Meat · Classic Western</div>
        <div class="page-categories">
            <span class="cat-tag">Munch & Meat</span>
            <span class="cat-tag">Classic Western</span>
            <span class="cat-tag">12 produk</span>
        </div>
    </span>

    <!-- Page 5 — Planned -->
    <span class="page-card plan">
        <span class="status-badge status-plan">Belum dibuat</span>
        <div class="page-num">Halaman 05</div>
        <div class="page-title-name">Asian Signatures</div>
        <div class="page-subtitle">Asian Course</div>
        <div class="page-categories">
            <span class="cat-tag">Asian Course</span>
            <span class="cat-tag">13 produk</span>
            <span class="cat-tag">13 gambar</span>
        </div>
    </span>

    <!-- Page 6 — Planned -->
    <span class="page-card plan">
        <span class="status-badge status-plan">Belum dibuat</span>
        <div class="page-num">Halaman 06</div>
        <div class="page-title-name">Bowl, Spice &amp; Noodles</div>
        <div class="page-subtitle">Spicy · Rice Bowl · Anak Kos Core</div>
        <div class="page-categories">
            <span class="cat-tag">Spicy</span>
            <span class="cat-tag">Rice Bowl</span>
            <span class="cat-tag">Anak Kos Core</span>
            <span class="cat-tag">13 produk</span>
        </div>
    </span>

    <!-- Page 7 — Planned -->
    <span class="page-card plan">
        <span class="status-badge status-plan">Belum dibuat</span>
        <div class="page-num">Halaman 07</div>
        <div class="page-title-name">Bites of Joy</div>
        <div class="page-subtitle">Snack &amp; Bites — Dim Sum · Crispy Bites · Sweet Treats</div>
        <div class="page-categories">
            <span class="cat-tag">Dim Sum</span>
            <span class="cat-tag">Crispy Bites</span>
            <span class="cat-tag">Sweet Treats</span>
            <span class="cat-tag">19 produk</span>
        </div>
    </span>

    <!-- Page 8 — Planned -->
    <span class="page-card plan">
        <span class="status-badge status-plan">Belum dibuat</span>
        <div class="page-num">Halaman 08</div>
        <div class="page-title-name">Dessert Collection</div>
        <div class="page-subtitle">Dessert</div>
        <div class="page-categories">
            <span class="cat-tag">Dessert</span>
            <span class="cat-tag">5 produk</span>
            <span class="cat-tag">5 gambar</span>
        </div>
    </span>

    <!-- Page 9 — Planned -->
    <span class="page-card plan">
        <span class="status-badge status-plan">Belum dibuat</span>
        <div class="page-num">Halaman 09</div>
        <div class="page-title-name">Extras &amp; Sides</div>
        <div class="page-subtitle">Carbo · Other Condiment · Sauce Sambal Mayo</div>
        <div class="page-categories">
            <span class="cat-tag">Carbo</span>
            <span class="cat-tag">Other Condiment</span>
            <span class="cat-tag">Sauce Sambal Mayo</span>
            <span class="cat-tag">23 produk</span>
        </div>
    </span>

</div>

<footer class="mb-footer">
    NAMUA Coffee &amp; Eatery &nbsp;&middot;&nbsp; #kembalikenamua
</footer>

</body>
</html>
