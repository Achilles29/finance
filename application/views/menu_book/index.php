<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Book — NAMUA Coffee & Eatery</title>

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Georgia, 'Times New Roman', serif;
            background:
                radial-gradient(circle at top left, rgba(201,168,106,.18), transparent 30%),
                linear-gradient(135deg, #F8F1E8, #EFE2CF);
            color: #3A2E22;
            min-height: 100vh;
            padding: 40px 20px 64px;
        }

        .mb-header {
            text-align: center;
            max-width: 900px;
            margin: 0 auto 34px;
        }

        .mb-header .logo {
            height: 68px;
            margin-bottom: 16px;
        }

        .mb-header h1 {
            font-size: 12px;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: #C9A86A;
            margin-bottom: 8px;
        }

        .mb-header h2 {
            font-size: 34px;
            font-weight: 700;
            color: #7D1F1F;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .mb-header .subtitle {
            font-size: 13px;
            color: #6A4E3A;
            margin-top: 8px;
            font-style: italic;
        }

        .mb-divider {
            width: 90px;
            height: 2px;
            background: linear-gradient(to right, transparent, #C9A86A, transparent);
            margin: 20px auto;
        }

        .project-progress {
            max-width: 760px;
            margin: 24px auto 0;
            background: rgba(255,255,255,.58);
            border: 1px solid #E8D5B0;
            padding: 16px;
            border-radius: 8px;
        }

        .progress-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: Arial, sans-serif;
            font-size: 11px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #7D1F1F;
            margin-bottom: 8px;
        }

        .progress-bar {
            height: 8px;
            background: #E8D5B0;
            border-radius: 99px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            width: 4%;
            background: linear-gradient(to right, #7D1F1F, #C9A86A);
        }

        .division-block {
            max-width: 1180px;
            margin: 42px auto 0;
        }

        .division-title {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 18px;
        }

        .division-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(to right, #C9A86A, transparent);
        }

        .division-title h3 {
            font-size: 18px;
            color: #7D1F1F;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .division-title span {
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #9E856A;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .page-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(275px, 1fr));
            gap: 18px;
        }

        .page-card {
            background: rgba(255,255,255,.88);
            border: 1px solid #E8D5B0;
            border-top: 4px solid #C9A86A;
            border-radius: 8px;
            padding: 23px 19px;
            text-decoration: none;
            color: inherit;
            display: block;
            transition: box-shadow .2s, transform .2s, opacity .2s;
            position: relative;
            min-height: 158px;
        }

        .page-card:hover {
            box-shadow: 0 10px 28px rgba(125,31,31,.14);
            transform: translateY(-3px);
        }

        .page-card.done { border-top-color: #7D1F1F; }
        .page-card.wip  { border-top-color: #C9A86A; }
        .page-card.plan { border-top-color: #D0C5B5; opacity: .68; pointer-events: none; }

        .page-num {
            font-family: Arial, sans-serif;
            font-size: 10px;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: #C9A86A;
            margin-bottom: 7px;
        }

        .page-title-name {
            font-size: 17px;
            font-weight: 700;
            color: #7D1F1F;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
            padding-right: 52px;
        }

        .page-subtitle {
            font-size: 12px;
            color: #6A4E3A;
            font-style: italic;
            margin-bottom: 13px;
            line-height: 1.35;
        }

        .page-categories {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .cat-tag {
            font-family: Arial, sans-serif;
            font-size: 9px;
            letter-spacing: .9px;
            text-transform: uppercase;
            background: #F8F1E8;
            border: 1px solid #E8D5B0;
            color: #6A4E3A;
            padding: 3px 8px;
            border-radius: 99px;
        }

        .status-badge {
            position: absolute;
            top: 14px;
            right: 14px;
            font-family: Arial, sans-serif;
            font-size: 8px;
            letter-spacing: 1.6px;
            text-transform: uppercase;
            padding: 4px 8px;
            border-radius: 99px;
            font-style: normal;
        }

        .status-done { background: #7D1F1F; color: #fff; }
        .status-wip  { background: #C9A86A; color: #fff; }
        .status-plan { background: #E8D5B0; color: #6A4E3A; }

        .open-icon {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #C9A86A;
            margin-top: 16px;
            display: flex;
            align-items: center;
            gap: 4px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .mb-footer {
            text-align: center;
            margin-top: 56px;
            font-size: 11px;
            color: #9E856A;
            letter-spacing: 2px;
        }

        @media print { body { display: none; } }
    </style>
</head>

<body>

<header class="mb-header">
    <img src="<?= base_url('assets/menu-book/logo/logo.png') ?>" class="logo" alt="NAMUA Logo" onerror="this.style.display='none'">

    <h1>NAMUA Coffee &amp; Eatery</h1>
    <div class="mb-divider"></div>
    <h2>Menu Book</h2>
    <p class="subtitle">Dashboard halaman buku menu digital & cetak — klik halaman aktif untuk membuka preview</p>

    <div class="project-progress">
        <div class="progress-top">
            <span>Project Progress</span>
            <span>1 / 25 Pages Active</span>
        </div>
        <div class="progress-bar">
            <div class="progress-fill"></div>
        </div>
    </div>
</header>

<section class="division-block">
    <div class="division-title">
        <h3>Food Division</h3>
        <span>Page 01–09</span>
    </div>

    <div class="page-grid">

        <span class="page-card plan">
            <span class="status-badge status-plan">Plan</span>
            <div class="page-num">Halaman 01</div>
            <div class="page-title-name">Cover</div>
            <div class="page-subtitle">Opening page for NAMUA Menu Book</div>
            <div class="page-categories">
                <span class="cat-tag">Cover</span>
                <span class="cat-tag">Branding</span>
            </div>
        </span>

        <a href="<?= site_url('menu_book/food/main_character') ?>" target="_blank" class="page-card done">
            <span class="status-badge status-done">Done</span>
            <div class="page-num">Halaman 02</div>
            <div class="page-title-name">Main Character</div>
            <div class="page-subtitle">The Icons of NAMUA</div>
            <div class="page-categories">
                <span class="cat-tag">Main Character</span>
                <span class="cat-tag">6 Produk</span>
                <span class="cat-tag">6 Gambar</span>
            </div>
            <div class="open-icon">&#8599; Buka halaman</div>
        </a>

        <a href="<?= site_url('menu_book/food/nusantara_comfort') ?>" target="_blank" class="page-card wip">
            <span class="status-badge status-done">Done</span>
            <div class="page-num">Halaman 03</div>
            <div class="page-title-name">Nusantara &amp; Comfort Food</div>
            <div class="page-subtitle">Indonesian Heritage · Crave Corner</div>
            <div class="page-categories">
                <span class="cat-tag">Indonesian Heritage</span>
                <span class="cat-tag">Crave Corner</span>
                <span class="cat-tag">13 Produk</span>
                <span class="cat-tag">13 Gambar</span>
            </div>
            <div class="open-icon">&#8599; Buka halaman</div>
        </a>

        <a href="<?= site_url('menu_book/food/flame_flavor') ?>" target="_blank" class="page-card wip">
            <span class="status-badge status-done">Done</span>
            <div class="page-num">Halaman 04</div>
            <div class="page-title-name">Flame &amp; Flavor</div>
            <div class="page-subtitle">Munch &amp; Meat · Classic Western</div>
            <div class="page-categories">
                <span class="cat-tag">Munch & Meat</span>
                <span class="cat-tag">Classic Western</span>
                <span class="cat-tag">12 Produk</span>
                <span class="cat-tag">13 Gambar</span>
            </div>
        </a>

        <a href="<?= site_url('menu_book/food/asian_signatures') ?>" target="_blank" class="page-card wip">
            <span class="status-badge status-done">Done</span>
            <div class="page-num">Halaman 05</div>
            <div class="page-title-name">Asian Signatures</div>
            <div class="page-subtitle">Asian Course</div>
            <div class="page-categories">
                <span class="cat-tag">Asian Course</span>
                <span class="cat-tag">13 Produk</span>
                <span class="cat-tag">13 Gambar</span>
            </div>
        </a>

        
        <a href="<?= site_url('menu_book/food/bowl_spice_noodles') ?>" target="_blank" class="page-card wip">
            <span class="status-badge status-done">Done</span>
            <div class="page-num">Halaman 06</div>
            <div class="page-title-name">Bowl, Spice &amp; Noodles</div>
            <div class="page-subtitle">Spicy · Rice Bowl · Anak Kos Core</div>
            <div class="page-categories">
                <span class="cat-tag">Spicy</span>
                <span class="cat-tag">Rice Bowl</span>
                <span class="cat-tag">Anak Kos Core</span>
                <span class="cat-tag">13 Produk</span>
                <span class="cat-tag">4 Gambar</span>
            </div>
        </a>

        <a href="<?= site_url('menu_book/food/bites_of_joy') ?>" target="_blank" class="page-card wip">
            <span class="status-badge status-done">Done</span>
            <div class="page-num">Halaman 07</div>
            <div class="page-title-name">Bites of Joy</div>
            <div class="page-subtitle">Snack &amp; Bites — Dim Sum · Crispy Bites · Sweet Treats</div>
            <div class="page-categories">
                <span class="cat-tag">Dim Sum</span>
                <span class="cat-tag">Crispy Bites</span>
                <span class="cat-tag">Sweet Treats</span>
                <span class="cat-tag">19 Produk</span>
                <span class="cat-tag">11 Gambar</span>
            </div>
        </a>

        <a href="<?= site_url('menu_book/food/dessert_collection') ?>" target="_blank" class="page-card wip">
            <span class="status-badge status-done">Done</span>
            <div class="page-num">Halaman 08</div>
            <div class="page-title-name">Dessert Collection</div>
            <div class="page-subtitle">Dessert</div>
            <div class="page-categories">
                <span class="cat-tag">Dessert</span>
                <span class="cat-tag">5 Produk</span>
                <span class="cat-tag">5 Gambar</span>
            </div>
        </a>

        <a href="<?= site_url('menu_book/food/extras_sides') ?>" target="_blank" class="page-card wip">
            <span class="status-badge status-done">Done</span>
            <div class="page-num">Halaman 09</div>
            <div class="page-title-name">Extras &amp; Sides</div>
            <div class="page-subtitle">Carbo · Other Condiment · Sauce Sambal Mayo</div>
            <div class="page-categories">
                <span class="cat-tag">Carbo</span>
                <span class="cat-tag">Other Condiment</span>
                <span class="cat-tag">Sauce Sambal Mayo</span>
                <span class="cat-tag">23 Produk</span>
                <span class="cat-tag">Tanpa Gambar</span>
            </div>
        </a>

    </div>
</section>

<section class="division-block">
    <div class="division-title">
        <h3>Beverage Division</h3>
        <span>Page 10–25</span>
    </div>

    <div class="page-grid">

        <?php
        $beverage_pages = [
            ['10', 'Signature Coffee', 'House signature coffee selections', ['Signature Coffee']],
            ['11', 'Masterpiece Line', 'Premium beverage creations', ['Masterpiece Line']],
            ['12', 'Classic Coffee', 'Espresso based classics', ['Classic Coffee']],
            ['13', 'Manual Brew', 'Single origin and hand brew', ['Manual Brew']],
            ['14', 'Cold Brew Series', 'Slow brewed cold coffee', ['Cold Brew Series']],
            ['15', 'Favorite Grandma', 'Nostalgic comfort drinks', ['Favorite Grandma']],
            ['16', 'Latte Series', 'Flavoured latte collection', ['Latte Series']],
            ['17', 'Artisan Tea', 'Premium tea selections', ['Artisan Tea']],
            ['18', 'Tea Series', 'Daily tea refreshments', ['Tea Series']],
            ['19', 'Mocktail', 'Fresh non-alcoholic creations', ['Mocktail']],
            ['20', 'Refreshing Drinks', 'Light and fresh beverages', ['Refreshing Drinks']],
            ['21', 'Blend & Smoothies', 'Blended drinks and smoothies', ['Blend & Smoothies']],
            ['22', 'Sweet & Milk Series', 'Creamy sweet beverages', ['Sweet & Milk Series']],
            ['23', 'Wedangan', 'Traditional warm drinks', ['Wedangan']],
            ['24', 'Ice Cream', 'Ice cream selections', ['Ice Cream']],
            ['25', 'Beverage Add-On', 'Additional toppings and options', ['Beverage Add-On']],
        ];

        foreach ($beverage_pages as $page):
        ?>
            <span class="page-card plan">
                <span class="status-badge status-plan">Plan</span>
                <div class="page-num">Halaman <?= $page[0] ?></div>
                <div class="page-title-name"><?= $page[1] ?></div>
                <div class="page-subtitle"><?= $page[2] ?></div>
                <div class="page-categories">
                    <?php foreach ($page[3] as $tag): ?>
                        <span class="cat-tag"><?= $tag ?></span>
                    <?php endforeach; ?>
                </div>
            </span>
        <?php endforeach; ?>

    </div>
</section>

<footer class="mb-footer">
    NAMUA Coffee &amp; Eatery &nbsp;&middot;&nbsp; #kembalikenamua
</footer>

</body>
</html>