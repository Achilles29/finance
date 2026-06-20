<?php
$menu_sections = [
    [
        'title' => 'Dim Sum',
        'eyebrow' => 'Steam / Mentai / Mozzarella',
        'spotlights' => [
            [
                'label' => 'Chef Pick',
                'title' => 'Dimsum Mentai',
                'price' => '28K',
                'desc' => 'Dimsum fill, kulit pangsit, red ginger pickled, sauce mentai with nori.',
                'image' => 'assets/menu-book/products/foods/bites-of-joy/dimsum-mentai.png',
            ],
        ],
        'items' => [
            ['name' => 'Dim Sum', 'price' => '19K', 'desc' => 'Chinese steam wonton fill chicken inside, sauce Bangkok.'],
            ['name' => 'Dimsum Mozarella Cheese', 'price' => '21K', 'desc' => 'Dimsum fill kulit lumpia, red onion pickle, leek, mozzarella.'],
            ['name' => 'Dimsum Mentai', 'price' => '28K', 'desc' => 'Dimsum fill, kulit pangsit, red ginger pickled, sauce mentai with nori.', 'badge' => 'Highlight'],
        ],
    ],
    [
        'title' => 'Crispy Bites',
        'eyebrow' => 'Platters / Fries / Tofu / Crunch',
        'spotlights' => [
            ['label' => 'Best Seller', 'title' => 'Mix Platter Namua', 'price' => '35K', 'image' => 'assets/menu-book/products/foods/bites-of-joy/mix-platter-namua.png'],
            ['label' => 'Best Seller', 'title' => 'Tahu Cabe Garam', 'price' => '18K', 'image' => 'assets/menu-book/products/foods/bites-of-joy/tahu-cabe-garam.png'],
            ['label' => 'Best Seller', 'title' => 'Mendoan Magnolia', 'price' => '18K', 'image' => 'assets/menu-book/products/foods/bites-of-joy/mendoan-magnolia.png'],
            ['label' => 'Best Seller', 'title' => 'Fried Tofu With Chicken Inside', 'price' => '18K', 'image' => 'assets/menu-book/products/foods/bites-of-joy/fried-tofu-with-chicken-inside.png'],
            ['label' => 'Best Seller', 'title' => 'Chikuwa Cheese', 'price' => '26K', 'image' => 'assets/menu-book/products/foods/bites-of-joy/chikuwa-cheese.png'],
            ['label' => 'Best Seller', 'title' => 'French Fries Bolognese', 'price' => '25K', 'image' => 'assets/menu-book/products/foods/bites-of-joy/french-fries-bolognese.png'],
            ['label' => 'Best Seller', 'title' => 'Wonton Nachos', 'price' => '18K', 'image' => 'assets/menu-book/products/foods/bites-of-joy/wonton-nachos.png'],
        ],
        'items' => [
            ['name' => 'Mix Platter Namua', 'price' => '35K', 'desc' => 'Chicken skin, french fries, cireng, enoky, onion ring with tar-tar mayo & hot volcano mayo.', 'badge' => 'Best Seller'],
            ['name' => 'Duo Platter', 'price' => '18K', 'desc' => 'Crispy chicken leg, onion ring with seaweed powder & tar-tar mayo.'],
            ['name' => 'Enoki Crispy', 'price' => '18K', 'desc' => 'Enoky, mix flour, sauce Bangkok.'],
            ['name' => 'Tahu Cabe Garam', 'price' => '18K', 'desc' => 'Crispy tofu fries tossed with jalapeno Chinese style.', 'badge' => 'Best Seller'],
            ['name' => 'Mendoan Magnolia', 'price' => '18K', 'desc' => 'Tempe, adonan mendoan, leek, kemangi, cabe rawit merah, sambal kecap, abon sapi.', 'badge' => 'Best Seller'],
            ['name' => 'Fried Tofu With Chicken Inside', 'price' => '18K', 'desc' => 'Tofu fill chicken inside, sauce Bangkok, sambal kecap.', 'badge' => 'Best Seller'],
            ['name' => 'Chikuwa Cheese', 'price' => '26K', 'desc' => 'Chikuwa bread crumb, mozzarella cheese, leek, sauce Bangkok, cheese sauce.', 'badge' => 'Best Seller'],
            ['name' => 'French Fries Bolognese', 'price' => '25K', 'desc' => 'Hand cut fries, cheese sauce & bolognese on top.', 'badge' => 'Best Seller'],
            ['name' => 'French Fries Ori', 'price' => '15K', 'desc' => 'Hand cut fries with original seasoning.'],
            ['name' => 'Cireng', 'price' => '15K', 'desc' => 'Cireng frozen, chili sauce.'],
            ['name' => 'Wonton Nachos', 'price' => '18K', 'desc' => 'Crispy fried wonton, honey mustard & bolognese on top.', 'badge' => 'Best Seller'],
        ],
    ],
    [
        'title' => 'Sweet Treats',
        'eyebrow' => 'Banana / Toast / Chocolate / Cheese',
        'spotlights' => [
            ['label' => 'Spotlight', 'title' => 'Pikameel', 'price' => '15K', 'image' => 'assets/menu-book/products/foods/bites-of-joy/pikameel.png'],
            ['label' => 'Spotlight', 'title' => 'Roti Bakar Coklat', 'price' => '16K', 'image' => 'assets/menu-book/products/foods/bites-of-joy/roti-bakar-coklat.png'],
        ],
        'items' => [
            ['name' => 'Pinokio', 'price' => '22K', 'desc' => 'Banana spring roll with Nutella sauce on side dish.'],
            ['name' => 'Pikameel', 'price' => '15K', 'desc' => 'Banana crumbling bread crumb with caramel.', 'badge' => 'Highlight'],
            ['name' => 'Roti Bakar Coklat', 'price' => '16K', 'desc' => 'Classic toasted bread with chocolate filling.', 'badge' => 'Highlight'],
            ['name' => 'Roti Bakar Coklat + Keju', 'price' => '18K', 'desc' => 'Toasted bread with chocolate and cheese.'],
            ['name' => 'Roti Bakar Keju', 'price' => '17K', 'desc' => 'Toasted bread with cheese filling.'],
        ],
    ],
];

$resolve_image = static function ($relativePath) {
    $absolute = FCPATH . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($absolute)) {
        return base_url($relativePath);
    }
    return null;
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Bites of Joy - NAMUA Coffee &amp; Eatery</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700;800;900&family=Playfair+Display:wght@500;600;700;800;900&family=Pinyon+Script&display=swap" rel="stylesheet">

<style>
@page { size: A4 portrait; margin: 0; }

*,
*::before,
*::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

html, body {
    background: #21140d;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    text-rendering: geometricPrecision;
}

body {
    font-family: 'Jost', Arial, sans-serif;
    color: #4a2f23;
}

.menu-page {
    width: 210mm;
    height: 297mm;
    margin: 0 auto;
    position: relative;
    overflow: hidden;
    padding: 8mm;
    background:
        radial-gradient(circle at 16% 8%, rgba(222,180,90,.18), transparent 26%),
        radial-gradient(circle at 82% 16%, rgba(168,35,44,.07), transparent 24%),
        radial-gradient(circle at 78% 86%, rgba(201,162,78,.12), transparent 28%),
        linear-gradient(135deg, rgba(255,251,243,.98), rgba(244,233,216,.98)),
        url('<?= base_url('assets/menu-book/backgrounds/bg-bites.png') ?>') center / cover no-repeat,
        #f7efe1;
    box-shadow:
        0 28px 58px rgba(25, 14, 10, .36),
        inset 0 0 0 1px rgba(255,255,255,.34);
}

.menu-page::before {
    content: "";
    position: absolute;
    inset: 5mm;
    border: 1.2px solid rgba(201,162,78,.66);
    pointer-events: none;
}

.menu-page::after {
    content: "";
    position: absolute;
    inset: 6.8mm;
    border: .8px solid rgba(168,35,44,.28);
    pointer-events: none;
}

.page-inner {
    position: relative;
    z-index: 2;
    height: 100%;
    display: grid;
    grid-template-rows: 22mm 1fr 6.5mm;
    gap: 2.8mm;
}

.header {
    display: grid;
    grid-template-columns: 22mm 1fr 22mm;
    align-items: center;
    border-bottom: 1px solid rgba(201,162,78,.55);
    padding-bottom: 2.4mm;
    position: relative;
}

.header::after {
    content: "";
    position: absolute;
    left: 24mm;
    right: 24mm;
    bottom: -1px;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(168,35,44,.18), transparent);
}

.logo-wrap {
    width: 18mm;
    height: 18mm;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: radial-gradient(circle at 35% 30%, rgba(255,255,255,.95), rgba(248,236,214,.9) 60%, rgba(228,205,161,.9));
    border: 1px solid rgba(201,162,78,.58);
    box-shadow:
        0 6px 14px rgba(84,46,27,.14),
        inset 0 0 0 1px rgba(255,255,255,.72);
}

.logo-wrap img {
    width: 15.5mm;
    height: 15.5mm;
    object-fit: contain;
    display: block;
}

.title-area {
    text-align: center;
}

.title-area .script {
    display: block;
    font-family: 'Pinyon Script', cursive;
    font-size: 24px;
    line-height: .7;
    color: #C9A24E;
    margin-bottom: -1.1mm;
}

.title-area h1 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 35px;
    line-height: .9;
    letter-spacing: 2.3px;
    color: #A8232C;
    text-transform: uppercase;
    font-weight: 800;
    text-shadow: 0 1px 0 rgba(255,255,255,.45);
}

.title-area p {
    margin-top: 1.2mm;
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 10.8px;
    font-style: italic;
    color: #6A4E3A;
}

.content {
    min-height: 0;
    display: grid;
    grid-template-rows: 47mm 1fr 44mm;
    gap: 3mm;
}

.section {
    min-height: 0;
    display: grid;
    grid-template-rows: auto 1fr;
    gap: 1.5mm;
}

.section-head {
    display: flex;
    justify-content: space-between;
    align-items: end;
    border-bottom: 1px solid rgba(201,162,78,.58);
    padding-bottom: .8mm;
}

.section-head h2 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 16px;
    color: #A8232C;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 800;
    line-height: 1;
}

.section-head span {
    font-size: 6.7px;
    letter-spacing: 1.2px;
    color: #B68C39;
    text-transform: uppercase;
    font-weight: 800;
    padding: .8mm 2.1mm .7mm;
    border-radius: 999px;
    background: rgba(255,248,233,.92);
    border: 1px solid rgba(201,162,78,.34);
}

.section-card {
    min-height: 0;
    border-radius: 4mm;
    border: 1px solid rgba(201,162,78,.56);
    background:
        linear-gradient(145deg, rgba(255,252,246,.98), rgba(244,229,196,.95));
    box-shadow:
        0 10px 22px rgba(70,40,25,.12),
        inset 0 0 0 1px rgba(255,255,255,.34);
    padding: 2.6mm 2.8mm;
    display: grid;
    gap: 2.1mm;
}

.dimsum-card {
    grid-template-columns: 49mm 1fr;
}

.crispy-card {
    grid-template-rows: auto 1fr;
}

.sweet-card {
    grid-template-columns: 58mm 1fr;
}

.spotlight-stack,
.spotlight-strip,
.sweet-spotlights {
    min-height: 0;
}

.spotlight-stack {
    display: grid;
}

.spotlight-strip {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.8mm;
}

.sweet-spotlights {
    display: grid;
    grid-template-rows: 1fr 1fr;
    gap: 1.8mm;
}

.spotlight {
    position: relative;
    overflow: hidden;
    border-radius: 3.2mm;
    min-height: 0;
    border: 1px solid rgba(201,162,78,.52);
    box-shadow:
        0 8px 18px rgba(70,40,25,.12),
        inset 0 0 0 1px rgba(255,255,255,.25);
    background:
        radial-gradient(circle at 18% 18%, rgba(255,255,255,.70), transparent 28%),
        linear-gradient(145deg, #f7e6c0, #e4bd73 58%, #bf7e31);
}

.spotlight.has-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    filter: saturate(1.05) contrast(1.03);
}

.spotlight::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(35,20,10,.92), rgba(35,20,10,.42) 42%, transparent 74%);
}

.spotlight.no-image::before {
    content: attr(data-watermark);
    position: absolute;
    right: -1mm;
    top: 2mm;
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 16px;
    line-height: .86;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: rgba(255,247,232,.18);
    text-align: right;
    width: 70%;
    z-index: 1;
}

.spotlight-body {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 2;
    padding: 8.5mm 2.5mm 2.3mm;
    color: #fff;
}

.spotlight-body small {
    display: block;
    color: #F1D28A;
    font-size: 4.8px;
    letter-spacing: 1.1px;
    text-transform: uppercase;
    font-weight: 900;
    margin-bottom: .55mm;
}

.spotlight-body h3 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 9.4px;
    line-height: .98;
    text-transform: uppercase;
    color: #fff;
}

.spotlight-body p {
    margin-top: .75mm;
    font-size: 5.2px;
    line-height: 1.18;
    color: rgba(255,245,233,.88);
}

.spotlight-body b {
    display: block;
    margin-top: .75mm;
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 11.8px;
    color: #F1D28A;
    font-weight: 900;
}

.list-panel {
    min-height: 0;
    border-radius: 3.2mm;
    background: rgba(255,250,241,.72);
    border: 1px solid rgba(201,162,78,.34);
    padding: 2.3mm 2.4mm;
}

.menu-list {
    display: grid;
    gap: 1.15mm;
}

.menu-list.two-col {
    grid-template-columns: 1fr 1fr;
    column-gap: 3.1mm;
    row-gap: 1.15mm;
}

.menu-row {
    display: grid;
    grid-template-columns: 1fr 13.5mm;
    gap: 1.7mm;
    padding-bottom: 1mm;
    border-bottom: 1px dotted rgba(106,78,58,.28);
}

.menu-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.menu-row h3 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 8.8px;
    line-height: 1.02;
    color: #A8232C;
    text-transform: uppercase;
    font-weight: 800;
    margin-bottom: .45mm;
}

.menu-row p {
    font-size: 5.4px;
    line-height: 1.18;
    color: #6A4E3A;
}

.menu-row b {
    text-align: right;
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 12px;
    color: #A8232C;
    font-weight: 900;
    align-self: start;
}

.badge {
    display: inline-block;
    margin-left: 1.1mm;
    padding: 1.2px 5px;
    border-radius: 99px;
    background: linear-gradient(135deg, #e6ca87, #c89b43);
    color: #2b170b;
    font-size: 4.6px;
    letter-spacing: .75px;
    font-family: 'Jost', Arial, sans-serif;
    font-weight: 900;
    text-transform: uppercase;
    vertical-align: middle;
    box-shadow: 0 3px 8px rgba(169,116,39,.18);
}

.crispy-note {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2mm;
    padding: 0 .4mm;
}

.crispy-note strong {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 10.2px;
    color: #A8232C;
    text-transform: uppercase;
    letter-spacing: .8px;
}

.crispy-note span {
    font-size: 6px;
    color: #9a7440;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    font-weight: 800;
}

.footer {
    border-top: 1px solid rgba(201,162,78,.60);
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    padding-top: 1.2mm;
    font-size: 6.2px;
    letter-spacing: 1.7px;
    color: #8B6A32;
    text-transform: uppercase;
    font-weight: 800;
}

.footer span:nth-child(2) {
    color: #B68C39;
}

.footer span:last-child {
    text-align: right;
}

@media print {
    html, body { background: transparent; }
    .menu-page {
        margin: 0;
        page-break-after: always;
    }
}
</style>
</head>

<body>

<section class="menu-page">
<div class="page-inner">

<header class="header">
    <div class="logo-wrap">
        <img src="<?= base_url('assets/menu-book/logo/logo.png') ?>" alt="NAMUA">
    </div>

    <div class="title-area">
        <span class="script">Bites of</span>
        <h1>Joy</h1>
        <p>Crunchy bites, dim sum &amp; sweet treats.</p>
    </div>

    <div></div>
</header>

<main class="content">

    <section class="section">
        <div class="section-head">
            <h2><?= html_escape($menu_sections[0]['title']) ?></h2>
            <span><?= html_escape($menu_sections[0]['eyebrow']) ?></span>
        </div>

        <div class="section-card dimsum-card">
            <div class="spotlight-stack">
                <?php $dimsum = $menu_sections[0]['spotlights'][0]; ?>
                <?php $dimsumImage = $resolve_image($dimsum['image']); ?>
                <article class="spotlight<?= $dimsumImage ? ' has-image' : ' no-image' ?>" data-watermark="<?= html_escape($dimsum['title']) ?>">
                    <?php if ($dimsumImage): ?>
                        <img src="<?= $dimsumImage ?>" alt="<?= html_escape($dimsum['title']) ?>">
                    <?php endif; ?>
                    <div class="spotlight-body">
                        <small><?= html_escape($dimsum['label']) ?></small>
                        <h3><?= html_escape($dimsum['title']) ?></h3>
                        <p><?= html_escape($dimsum['desc']) ?></p>
                        <b><?= html_escape($dimsum['price']) ?></b>
                    </div>
                </article>
            </div>

            <div class="list-panel">
                <div class="menu-list">
                    <?php foreach ($menu_sections[0]['items'] as $item): ?>
                        <div class="menu-row">
                            <div>
                                <h3>
                                    <?= html_escape($item['name']) ?>
                                    <?php if (!empty($item['badge'])): ?><span class="badge"><?= html_escape($item['badge']) ?></span><?php endif; ?>
                                </h3>
                                <p><?= html_escape($item['desc']) ?></p>
                            </div>
                            <b><?= html_escape($item['price']) ?></b>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="section-head">
            <h2><?= html_escape($menu_sections[1]['title']) ?></h2>
            <span><?= html_escape($menu_sections[1]['eyebrow']) ?></span>
        </div>

        <div class="section-card crispy-card">
            <div class="spotlight-strip">
                <?php foreach (array_slice($menu_sections[1]['spotlights'], 0, 4) as $card): ?>
                    <?php $cardImage = $resolve_image($card['image']); ?>
                    <article class="spotlight<?= $cardImage ? ' has-image' : ' no-image' ?>" data-watermark="<?= html_escape($card['title']) ?>">
                        <?php if ($cardImage): ?>
                            <img src="<?= $cardImage ?>" alt="<?= html_escape($card['title']) ?>">
                        <?php endif; ?>
                        <div class="spotlight-body">
                            <small><?= html_escape($card['label']) ?></small>
                            <h3><?= html_escape($card['title']) ?></h3>
                            <b><?= html_escape($card['price']) ?></b>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="list-panel">
                <div class="crispy-note">
                    <strong>Best Sellers &amp; Crispy Picks</strong>
                    <span>Highlights tetap tampil meski foto belum diunggah</span>
                </div>
                <div class="menu-list two-col" style="margin-top: 1.7mm;">
                    <?php foreach ($menu_sections[1]['items'] as $item): ?>
                        <div class="menu-row">
                            <div>
                                <h3>
                                    <?= html_escape($item['name']) ?>
                                    <?php if (!empty($item['badge'])): ?><span class="badge"><?= html_escape($item['badge']) ?></span><?php endif; ?>
                                </h3>
                                <p><?= html_escape($item['desc']) ?></p>
                            </div>
                            <b><?= html_escape($item['price']) ?></b>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="section-head">
            <h2><?= html_escape($menu_sections[2]['title']) ?></h2>
            <span><?= html_escape($menu_sections[2]['eyebrow']) ?></span>
        </div>

        <div class="section-card sweet-card">
            <div class="sweet-spotlights">
                <?php foreach ($menu_sections[2]['spotlights'] as $card): ?>
                    <?php $cardImage = $resolve_image($card['image']); ?>
                    <article class="spotlight<?= $cardImage ? ' has-image' : ' no-image' ?>" data-watermark="<?= html_escape($card['title']) ?>">
                        <?php if ($cardImage): ?>
                            <img src="<?= $cardImage ?>" alt="<?= html_escape($card['title']) ?>">
                        <?php endif; ?>
                        <div class="spotlight-body">
                            <small><?= html_escape($card['label']) ?></small>
                            <h3><?= html_escape($card['title']) ?></h3>
                            <b><?= html_escape($card['price']) ?></b>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="list-panel">
                <div class="menu-list two-col">
                    <?php foreach ($menu_sections[2]['items'] as $item): ?>
                        <div class="menu-row">
                            <div>
                                <h3>
                                    <?= html_escape($item['name']) ?>
                                    <?php if (!empty($item['badge'])): ?><span class="badge"><?= html_escape($item['badge']) ?></span><?php endif; ?>
                                </h3>
                                <?php if (!empty($item['desc'])): ?><p><?= html_escape($item['desc']) ?></p><?php endif; ?>
                            </div>
                            <b><?= html_escape($item['price']) ?></b>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

</main>

<footer class="footer">
    <span>NAMUA Coffee &amp; Eatery</span>
    <span>Bites of Joy</span>
    <span>#kembalikenamua</span>
</footer>

</div>
</section>

</body>
</html>
