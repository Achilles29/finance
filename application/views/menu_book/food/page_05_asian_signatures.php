<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Asian Signatures — NAMUA Coffee & Eatery</title>

<style>
@page { size: A4 portrait; margin: 0; }

*, *::before, *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    background: #1b120c;
    font-family: Georgia, 'Times New Roman', serif;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.menu-page {
    width: 210mm;
    height: 297mm;
    margin: 0 auto;
    position: relative;
    overflow: hidden;
    padding: 7mm 9mm 12mm;
    background:
        linear-gradient(rgba(250,246,238,.90), rgba(250,246,238,.96)),
        url('/finance/assets/menu-book/backgrounds/bg-asian-signatures.png') center / cover no-repeat,
        #f8f1e8;
}

.menu-page::before {
    content: '';
    position: absolute;
    inset: 5mm;
    border: 1px solid rgba(125,31,31,.18);
    pointer-events: none;
    z-index: 1;
}

.menu-header,
.content,
.menu-footer {
    position: relative;
    z-index: 2;
}

.menu-header {
    height: 25mm;
    display: grid;
    grid-template-columns: 22mm 1fr 16mm;
    align-items: center;
    border-bottom: 1px solid rgba(125,31,31,.16);
    padding-bottom: 3mm;
    margin-bottom: 3mm;
}

.menu-logo {
    width: 14mm;
    height: 14mm;
    object-fit: contain;
}

.header-title-area { text-align: center; }

.label-category {
    display: block;
    font-family: Arial, sans-serif;
    font-size: 6px;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: #b58b4b;
    margin-bottom: 1.2mm;
}

.page-title {
    font-size: 24px;
    line-height: .95;
    color: #7D1F1F;
    letter-spacing: 1.5px;
    text-transform: uppercase;
}

.header-title-area h2 {
    margin-top: 1.3mm;
    font-size: 10px;
    font-style: italic;
    font-weight: 400;
    color: #6A4E3A;
}

.page-number {
    font-family: Arial, sans-serif;
    font-size: 8px;
    letter-spacing: 3px;
    text-align: right;
    color: #C9A86A;
    font-weight: 700;
}

.content {
    height: 235mm;
    display: grid;
    grid-template-rows: 72mm 72mm 78mm 5mm;
    gap: 2.5mm;
}

.section-heading {
    height: 8mm;
    display: flex;
    justify-content: space-between;
    align-items: end;
    border-bottom: 1px solid rgba(201,168,106,.45);
    padding-bottom: 1.1mm;
    margin-bottom: 2mm;
}

.section-heading h3 {
    font-size: 13px;
    color: #7D1F1F;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.section-heading p {
    font-family: Arial, sans-serif;
    font-size: 6.4px;
    letter-spacing: 1.1px;
    text-transform: uppercase;
    color: #9a7440;
}

.asian-card {
    position: relative;
    overflow: hidden;
    border-radius: 3mm;
    background: #fffaf2;
    border: 1px solid rgba(201,168,106,.44);
    box-shadow: 0 4px 13px rgba(70,40,25,.13);
}

.asian-card img {
    width: 100%;
    height: 100%;
    display: block;
    filter: saturate(1.04) contrast(1.02);
}

.asian-card::before {
    content: '';
    position: absolute;
    inset: 1.2mm;
    border: 1px solid rgba(255,255,255,.26);
    z-index: 2;
    pointer-events: none;
}

.asian-info {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    padding: 13mm 3mm 2.4mm;
    background: linear-gradient(
        to top,
        rgba(38,22,14,.95) 0%,
        rgba(38,22,14,.80) 48%,
        rgba(38,22,14,.10) 100%
    );
    color: #fff;
    z-index: 3;
}

.asian-info h4 {
    font-size: 9.2px;
    line-height: 1.05;
    letter-spacing: .3px;
    text-transform: uppercase;
    color: #fff;
    margin-bottom: .7mm;
    text-shadow: 0 1px 3px rgba(0,0,0,.6);
}

.asian-desc {
    margin-bottom: .8mm;
    font-family: Arial, sans-serif;
    font-size: 6.4px;
    line-height: 1.18;
    color: rgba(255,255,255,.83);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.price {
    font-family: Arial, sans-serif;
    font-size: 11px;
    font-weight: 900;
    color: #F1D28A;
    letter-spacing: .8px;
}

.mini-badge {
    display: inline-block;
    margin-bottom: .8mm;
    padding: 2px 6px;
    background: #C9A86A;
    color: #2a170b;
    font-family: Arial, sans-serif;
    font-size: 5.6px;
    font-weight: 900;
    letter-spacing: .8px;
    text-transform: uppercase;
    border-radius: 99px;
}

/* COMBO FULL IMAGE */

.combo-card {
    height: 62mm;
    background: #f8f1e8;
}

.combo-card img {
    object-fit: contain;
    object-position: center center;
    background: #f8f1e8;
}

.combo-info {
    padding: 14mm 4mm 3mm;
}

.combo-menu-list {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1mm 4mm;
    margin: .8mm 0 1mm;
    font-family: Arial, sans-serif;
}

.combo-menu-list div {
    display: flex;
    justify-content: space-between;
    gap: 2mm;
    border-bottom: 1px solid rgba(255,255,255,.22);
    padding-bottom: .6mm;
}

.combo-menu-list strong {
    font-size: 7.1px;
    color: #fff;
    letter-spacing: .35px;
    line-height: 1.05;
}

.combo-menu-list span {
    font-size: 8.8px;
    font-weight: 900;
    color: #F1D28A;
    white-space: nowrap;
}

/* WOK SECTION */

.wok-combo-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    grid-template-rows: 33mm 33mm;
    gap: 2.3mm;
}

.wok-combo-grid .asian-card img {
    object-fit: cover;
}

.wok-feature {
    grid-column: span 2;
    grid-row: span 2;
}

.wok-combo-grid .asian-info {
    padding: 10mm 2.2mm 1.8mm;
}

.wok-feature .asian-info {
    padding: 14mm 3mm 2.4mm;
}

.wok-feature .asian-info h4 {
    font-size: 12px;
}

.wok-feature .asian-desc {
    font-size: 7px;
}

.wok-feature .price {
    font-size: 13px;
}

.wok-combo-grid .asian-info h4 {
    font-size: 7.9px;
}

.wok-combo-grid .asian-desc {
    font-size: 5.8px;
}

.wok-combo-grid .price {
    font-size: 10px;
}

.note-row {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 5mm;
    font-family: Arial, sans-serif;
    font-size: 6.5px;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    color: #7D1F1F;
    border-top: 1px solid rgba(125,31,31,.16);
    padding-top: 1.5mm;
}

.menu-footer {
    position: absolute;
    left: 9mm;
    right: 9mm;
    bottom: 6.5mm;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-family: Arial, sans-serif;
    font-size: 6.5px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: rgba(106,78,58,.86);
    border-top: 1px solid rgba(201,168,106,.35);
    padding-top: 2mm;
}

@media print {
    body { background: transparent; }
    .menu-page {
        margin: 0;
        page-break-after: always;
    }
}
</style>
</head>

<body>

<section class="menu-page page-asian-signatures">

<header class="menu-header">
    <div>
        <img src="<?= base_url('assets/menu-book/logo/logo.png') ?>" class="menu-logo" alt="NAMUA">
    </div>

    <div class="header-title-area">
        <span class="label-category">Food Collection</span>
        <h1 class="page-title">ASIAN SIGNATURES</h1>
        <h2>Bowls, rolls & wok favorites.</h2>
    </div>

    <div class="page-number">05</div>
</header>

<main class="content">

    <section>
        <div class="section-heading">
            <h3>BROTH &amp; BOWL</h3>
            <p>Ramen · Ramyeon · Gyudon</p>
        </div>

        <article class="asian-card combo-card">
            <img src="<?= base_url('assets/menu-book/products/foods/asian-course/broth-bowl-combo.png') ?>" alt="Broth and Bowl Combo">

            <div class="asian-info combo-info">
                <span class="mini-badge">4 Bowl Selection</span>

                <div class="combo-menu-list">
                    <div><strong>SOYU RAMEN</strong><span>38K</span></div>
                    <div><strong>TORI PAITAN RAMEN</strong><span>35K</span></div>
                    <div><strong>SPICY RAMYEON</strong><span>32K</span></div>
                    <div><strong>GYU DON</strong><span>32K</span></div>
                </div>

                <div class="asian-desc">
                    Ramen, ramyeon and Japanese rice bowl served warm in signature Asian style.
                </div>
            </div>
        </article>
    </section>

    <section>
        <div class="section-heading">
            <h3>ROLLS &amp; KIMBAP</h3>
            <p>Sushi rolls · Korean wrap</p>
        </div>

        <article class="asian-card combo-card">
            <img src="<?= base_url('assets/menu-book/products/foods/asian-course/rolls-kimbap-combo.png') ?>" alt="Rolls and Kimbap Combo">

            <div class="asian-info combo-info">
                <span class="mini-badge">Roll Selection</span>

                <div class="combo-menu-list">
                    <div><strong>KOREAN BEEF KIMBAB</strong><span>31K</span></div>
                    <div><strong>CALIFORNIA ROLL</strong><span>24K</span></div>
                    <div><strong>VOLCANO ROLL</strong><span>26K</span></div>
                    <div><strong>YAKINIKU SUSHI ROLL</strong><span>29K</span></div>
                </div>

                <div class="asian-desc">
                    Seaweed rice wrap and sushi rolls with beef, crab stick, tempura and yakiniku filling.
                </div>
            </div>
        </article>
    </section>

    <section>
        <div class="section-heading">
            <h3>WOK &amp; ASIAN PLATES</h3>
            <p>Fried rice · noodles · katsu plates</p>
        </div>

        <div class="wok-combo-grid">

            <article class="asian-card wok-feature">
                <img src="<?= base_url('assets/menu-book/products/foods/asian-course/mie-ayam-namua.png') ?>" alt="Mie Ayam Namua">
                <div class="asian-info">
                    <h4>MIE AYAM NAMUA</h4>
                    <div class="asian-desc">
                        Asian noodle blanch, chicken leg marinated, garlic oyster, fried wonton & broth side dish.
                    </div>
                    <div class="price">28K</div>
                </div>
            </article>

            <article class="asian-card">
                <img src="<?= base_url('assets/menu-book/products/foods/asian-course/chicken-nori-yakimeshi.png') ?>" alt="Chicken Nori Yakimeshi">
                <div class="asian-info">
                    <h4>CHICKEN NORI YAKIMESHI</h4>
                    <div class="asian-desc">
                        Deep fry chicken katsu, yakimeshi, mix salad & teriyaki sauce.
                    </div>
                    <div class="price">28K</div>
                </div>
            </article>

            <article class="asian-card">
                <img src="<?= base_url('assets/menu-book/products/foods/asian-course/chinese-fried-noodles.png') ?>" alt="Chinese Fried Noodles">
                <div class="asian-info">
                    <h4>CHINESE FRIED NOODLES</h4>
                    <div class="asian-desc">
                        Chinese style fried noodles, chicken inside, mix vegetable & crackers.
                    </div>
                    <div class="price">25K</div>
                </div>
            </article>

            <article class="asian-card">
                <img src="<?= base_url('assets/menu-book/products/foods/asian-course/yang-chow-fried-rice.png') ?>" alt="Yang Chow Fried Rice">
                <div class="asian-info">
                    <h4>YANG CHOW FRIED RICE</h4>
                    <div class="asian-desc">
                        Chinese wok fried rice, smoked beef, sunny side up egg & crackers.
                    </div>
                    <div class="price">25K</div>
                </div>
            </article>

            <article class="asian-card">
                <img src="<?= base_url('assets/menu-book/products/foods/asian-course/crispy-dory-ala-thai.png') ?>" alt="Crispy Dory Ala Thai">
                <div class="asian-info">
                    <h4>CRISPY DORY ALA THAI</h4>
                    <div class="asian-desc">
                        Deep fry dory fish, mix flour, tomyam paste & Bangkok sauce.
                    </div>
                    <div class="price">25K</div>
                </div>
            </article>

        </div>
    </section>

    <div class="note-row">
        <span>All prices are in K</span>
        <span>Asian Course</span>
        <span>NAMUA Coffee &amp; Eatery</span>
    </div>

</main>

<footer class="menu-footer">
    <span>NAMUA Coffee &amp; Eatery</span>
    <span>#kembalikenamua</span>
</footer>

</section>

</body>
</html>