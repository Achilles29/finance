<?php
$menu_sections = [
    [
        'title' => 'Dim Sum',
        'eyebrow' => 'Steam craft / Mentai / Mozzarella',
        'tag' => 'Soft, juicy, sauce-forward',
        'hero_label' => 'Asian Steam Signature',
        'hero_title' => 'Dimsum Mentai',
        'hero_price' => '28K',
        'hero_desc' => 'Creamy mentai finish with nori on top, placed as the strongest dim sum statement on this page.',
        'hero_image' => 'assets/menu-book/products/foods/bites-of-joy/dimsum.png',
        'items' => [
            ['name' => 'Dim Sum', 'price' => '19K', 'desc' => 'Chinese steam wonton fill chicken inside, sauce Bangkok.'],
            ['name' => 'Dimsum Mozarella Cheese', 'price' => '21K', 'desc' => 'Dimsum fill kulit lumpia, red onion pickle, leek, mozzarella.'],
            ['name' => 'Dimsum Mentai', 'price' => '28K', 'desc' => 'Dimsum fill, kulit pangsit, red ginger pickled, sauce mentai with nori.', 'badge' => 'Highlight'],
        ],
    ],
    [
        'title' => 'Crispy Bites',
        'eyebrow' => 'Platters / Fries / Tofu / Crunch',
        'tag' => 'Big energy, shareable plates',
        'hero_label' => 'Best Seller Focus',
        'hero_title' => 'Mix Platter Namua',
        'hero_price' => '35K',
        'hero_desc' => 'A loud, crunchy centrepiece for the page: fries, onion ring, enoki, cireng and crispy bites in one plate.',
        'hero_image' => 'assets/menu-book/products/foods/bites-of-joy/crispy-bites.png',
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
        'tag' => 'Warm finish, caramel comfort',
        'hero_label' => 'Dessert Spotlight',
        'hero_title' => 'Pikameel & Toast Pairing',
        'hero_price' => '15K - 18K',
        'hero_desc' => 'Sweet side of the page with toasted chocolate, cheese and banana-based comfort bites.',
        'hero_image' => 'assets/menu-book/products/foods/bites-of-joy/sweet-treats.png',
        'items' => [
            ['name' => 'Pinokio', 'price' => '22K', 'desc' => 'Banana spring roll with Nutella sauce on side dish.'],
            ['name' => 'Pikameel', 'price' => '15K', 'desc' => 'Banana crumbling bread crumb with caramel.', 'badge' => 'Highlight'],
            ['name' => 'Roti Bakar Coklat', 'price' => '16K', 'desc' => 'Classic toasted bread with chocolate filling.', 'badge' => 'Highlight'],
            ['name' => 'Roti Bakar Coklat + Keju', 'price' => '18K', 'desc' => 'Toasted bread with chocolate and cheese.'],
            ['name' => 'Roti Bakar Keju', 'price' => '17K', 'desc' => 'Toasted bread with cheese filling.'],
        ],
    ],
];
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
    background: #1f130d;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    text-rendering: geometricPrecision;
}

body {
    font-family: 'Jost', Arial, sans-serif;
    color: #4f3422;
}

.menu-page {
    width: 210mm;
    height: 297mm;
    margin: 0 auto;
    position: relative;
    overflow: hidden;
    padding: 7mm 8mm 7.5mm;
    background:
        radial-gradient(circle at 18% 9%, rgba(222,180,90,.18), transparent 28%),
        radial-gradient(circle at 82% 18%, rgba(168,35,44,.08), transparent 24%),
        radial-gradient(circle at 76% 84%, rgba(201,162,78,.14), transparent 28%),
        linear-gradient(135deg, rgba(255,251,243,.99), rgba(244,232,214,.99)),
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
    grid-template-rows: 24mm 1fr 7mm;
    gap: 2.4mm;
}

.header {
    display: grid;
    grid-template-columns: 22mm 1fr 22mm;
    align-items: center;
    border-bottom: 1px solid rgba(201,162,78,.55);
    padding-bottom: 2.2mm;
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
    font-size: 25px;
    line-height: .72;
    color: #C9A24E;
    margin-bottom: -1.1mm;
}

.title-area h1 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 37px;
    line-height: .9;
    letter-spacing: 2.2px;
    color: #A8232C;
    text-transform: uppercase;
    font-weight: 800;
    text-shadow: 0 1px 0 rgba(255,255,255,.45);
}

.title-area p {
    margin-top: 1.2mm;
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 11.6px;
    font-style: italic;
    color: #6A4E3A;
}

.content {
    min-height: 0;
    display: grid;
    grid-template-rows: 62mm 102mm 76mm;
    gap: 2.7mm;
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
    padding-bottom: .9mm;
}

.section-head h2 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 17px;
    color: #A8232C;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 800;
    line-height: 1;
}

.section-head span {
    font-size: 7.1px;
    letter-spacing: 1.25px;
    color: #B68C39;
    text-transform: uppercase;
    font-weight: 800;
    padding: .85mm 2.2mm .72mm;
    border-radius: 999px;
    background: rgba(255,248,233,.92);
    border: 1px solid rgba(201,162,78,.34);
}

.section-shell {
    min-height: 0;
    border-radius: 4.4mm;
    border: 1px solid rgba(201,162,78,.56);
    background: linear-gradient(145deg, rgba(255,252,246,.98), rgba(244,229,196,.95));
    box-shadow:
        0 12px 24px rgba(70,40,25,.12),
        inset 0 0 0 1px rgba(255,255,255,.34);
    overflow: hidden;
}

.feature-grid {
    display: grid;
    height: 100%;
}

.feature-grid.top {
    grid-template-columns: 58% 42%;
}

.feature-grid.middle {
    grid-template-columns: 46% 54%;
}

.feature-grid.bottom {
    grid-template-columns: 42% 58%;
}

.hero-photo {
    position: relative;
    overflow: hidden;
}

.hero-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    filter: saturate(1.04) contrast(1.02);
}

.hero-photo::after {
    content: "";
    position: absolute;
    inset: 0;
    background:
        linear-gradient(to top, rgba(35,20,10,.76), rgba(35,20,10,.08) 42%, transparent 70%),
        linear-gradient(to right, rgba(255,248,236,.10), transparent 30%);
}

.hero-caption {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 2;
    padding: 12mm 4.2mm 3.3mm;
    color: #fff7ea;
}

.hero-caption small {
    display: block;
    color: #F1D28A;
    font-size: 5.5px;
    letter-spacing: 1.25px;
    text-transform: uppercase;
    font-weight: 900;
    margin-bottom: .7mm;
}

.hero-caption h3 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 14px;
    line-height: .95;
    text-transform: uppercase;
    color: #fff;
}

.hero-caption b {
    display: block;
    margin-top: .9mm;
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 14px;
    color: #F1D28A;
    font-weight: 900;
}

.feature-copy {
    min-height: 0;
    padding: 3.5mm 3.6mm;
    display: grid;
    grid-template-rows: auto auto 1fr;
    gap: 2.15mm;
    background:
        linear-gradient(180deg, rgba(255,251,243,.92), rgba(244,228,196,.82)),
        radial-gradient(circle at top right, rgba(255,255,255,.35), transparent 30%);
}

.section-tag {
    width: fit-content;
    padding: 2.1px 8px;
    border-radius: 999px;
    background: linear-gradient(135deg, #e6ca87, #c89b43);
    color: #2b170b;
    font-size: 5.3px;
    letter-spacing: .95px;
    font-weight: 900;
    text-transform: uppercase;
    box-shadow: 0 3px 8px rgba(169,116,39,.18);
}

.feature-copy h4 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 15px;
    line-height: .96;
    text-transform: uppercase;
    color: #A8232C;
    font-weight: 800;
}

.feature-copy p.lead {
    font-size: 6.5px;
    line-height: 1.34;
    color: #6A4E3A;
}

.menu-list {
    display: grid;
    gap: 1.4mm;
    align-content: start;
}

.menu-list.two-col {
    grid-template-columns: 1fr 1fr;
    column-gap: 3.4mm;
    row-gap: 1.35mm;
}

.menu-row {
    display: grid;
    grid-template-columns: 1fr 13.8mm;
    gap: 1.7mm;
    padding-bottom: 1.15mm;
    border-bottom: 1px dotted rgba(106,78,58,.28);
}

.menu-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.menu-row h5 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 9.6px;
    line-height: 1.02;
    color: #A8232C;
    text-transform: uppercase;
    font-weight: 800;
    margin-bottom: .42mm;
}

.menu-row p {
    font-size: 5.9px;
    line-height: 1.24;
    color: #6A4E3A;
}

.menu-row b {
    text-align: right;
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 12.8px;
    color: #A8232C;
    font-weight: 900;
    align-self: start;
}

.badge {
    display: inline-block;
    margin-left: 1.05mm;
    padding: 1.1px 5px;
    border-radius: 999px;
    background: linear-gradient(135deg, #f0d58c, #d29a42);
    color: #2b170b;
    font-size: 4.8px;
    letter-spacing: .75px;
    font-family: 'Jost', Arial, sans-serif;
    font-weight: 900;
    text-transform: uppercase;
    vertical-align: middle;
}

.footer {
    border-top: 1px solid rgba(201,162,78,.60);
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    padding-top: 1.2mm;
    font-size: 6.5px;
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
        <div class="section-shell">
            <div class="feature-grid top">
                <div class="hero-photo">
                    <img src="<?= base_url($menu_sections[0]['hero_image']) ?>" alt="<?= html_escape($menu_sections[0]['hero_title']) ?>">
                    <div class="hero-caption">
                        <small><?= html_escape($menu_sections[0]['hero_label']) ?></small>
                        <h3><?= html_escape($menu_sections[0]['hero_title']) ?></h3>
                        <b><?= html_escape($menu_sections[0]['hero_price']) ?></b>
                    </div>
                </div>
                <div class="feature-copy">
                    <span class="section-tag"><?= html_escape($menu_sections[0]['tag']) ?></span>
                    <h4><?= html_escape($menu_sections[0]['hero_title']) ?></h4>
                    <p class="lead"><?= html_escape($menu_sections[0]['hero_desc']) ?></p>
                    <div class="menu-list">
                        <?php foreach ($menu_sections[0]['items'] as $item): ?>
                            <div class="menu-row">
                                <div>
                                    <h5>
                                        <?= html_escape($item['name']) ?>
                                        <?php if (!empty($item['badge'])): ?><span class="badge"><?= html_escape($item['badge']) ?></span><?php endif; ?>
                                    </h5>
                                    <p><?= html_escape($item['desc']) ?></p>
                                </div>
                                <b><?= html_escape($item['price']) ?></b>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="section-head">
            <h2><?= html_escape($menu_sections[1]['title']) ?></h2>
            <span><?= html_escape($menu_sections[1]['eyebrow']) ?></span>
        </div>
        <div class="section-shell">
            <div class="feature-grid middle">
                <div class="feature-copy">
                    <span class="section-tag"><?= html_escape($menu_sections[1]['tag']) ?></span>
                    <h4><?= html_escape($menu_sections[1]['hero_title']) ?></h4>
                    <p class="lead"><?= html_escape($menu_sections[1]['hero_desc']) ?></p>
                    <div class="menu-list two-col">
                        <?php foreach ($menu_sections[1]['items'] as $item): ?>
                            <div class="menu-row">
                                <div>
                                    <h5>
                                        <?= html_escape($item['name']) ?>
                                        <?php if (!empty($item['badge'])): ?><span class="badge"><?= html_escape($item['badge']) ?></span><?php endif; ?>
                                    </h5>
                                    <p><?= html_escape($item['desc']) ?></p>
                                </div>
                                <b><?= html_escape($item['price']) ?></b>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="hero-photo">
                    <img src="<?= base_url($menu_sections[1]['hero_image']) ?>" alt="<?= html_escape($menu_sections[1]['hero_title']) ?>">
                    <div class="hero-caption">
                        <small><?= html_escape($menu_sections[1]['hero_label']) ?></small>
                        <h3><?= html_escape($menu_sections[1]['hero_title']) ?></h3>
                        <b><?= html_escape($menu_sections[1]['hero_price']) ?></b>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="section-head">
            <h2><?= html_escape($menu_sections[2]['title']) ?></h2>
            <span><?= html_escape($menu_sections[2]['eyebrow']) ?></span>
        </div>
        <div class="section-shell">
            <div class="feature-grid bottom">
                <div class="hero-photo">
                    <img src="<?= base_url($menu_sections[2]['hero_image']) ?>" alt="<?= html_escape($menu_sections[2]['hero_title']) ?>">
                    <div class="hero-caption">
                        <small><?= html_escape($menu_sections[2]['hero_label']) ?></small>
                        <h3><?= html_escape($menu_sections[2]['hero_title']) ?></h3>
                        <b><?= html_escape($menu_sections[2]['hero_price']) ?></b>
                    </div>
                </div>
                <div class="feature-copy">
                    <span class="section-tag"><?= html_escape($menu_sections[2]['tag']) ?></span>
                    <h4><?= html_escape($menu_sections[2]['hero_title']) ?></h4>
                    <p class="lead"><?= html_escape($menu_sections[2]['hero_desc']) ?></p>
                    <div class="menu-list two-col">
                        <?php foreach ($menu_sections[2]['items'] as $item): ?>
                            <div class="menu-row">
                                <div>
                                    <h5>
                                        <?= html_escape($item['name']) ?>
                                        <?php if (!empty($item['badge'])): ?><span class="badge"><?= html_escape($item['badge']) ?></span><?php endif; ?>
                                    </h5>
                                    <p><?= html_escape($item['desc']) ?></p>
                                </div>
                                <b><?= html_escape($item['price']) ?></b>
                            </div>
                        <?php endforeach; ?>
                    </div>
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
