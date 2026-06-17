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
        linear-gradient(rgba(250,246,238,.93), rgba(250,246,238,.97)),
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
    height: 23mm;
    display: grid;
    grid-template-columns: 22mm 1fr 16mm;
    align-items: center;
    border-bottom: 1px solid rgba(125,31,31,.16);
    padding-bottom: 2.5mm;
    margin-bottom: 3mm;
}

.menu-logo {
    width: 14mm;
    height: 14mm;
    object-fit: contain;
}

.header-title-area {
    text-align: center;
}

.label-category {
    display: block;
    font-family: Arial, sans-serif;
    font-size: 6px;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: #b58b4b;
    margin-bottom: 1mm;
}

.page-title {
    font-size: 24px;
    line-height: .95;
    color: #7D1F1F;
    letter-spacing: 1.5px;
    text-transform: uppercase;
}

.header-title-area h2 {
    margin-top: 1.2mm;
    font-size: 9.8px;
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

/* .content {
    height: 237mm;
    display: grid;
    grid-template-rows: 58mm 62mm 103mm 5mm;
    gap: 3mm;
} */
.content {
    height: 237mm;
    display: grid;
    grid-template-rows: 66mm 66mm 87mm 5mm;
    gap: 3mm;
}
.section-heading {
    height: 7.5mm;
    display: flex;
    justify-content: space-between;
    align-items: end;
    border-bottom: 1px solid rgba(201,168,106,.45);
    padding-bottom: 1mm;
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
    font-size: 6.2px;
    letter-spacing: 1.1px;
    text-transform: uppercase;
    color: #9a7440;
}

/* COMBO HIGHLIGHT */

.combo-card {
    /* height: 48.5mm; */
    height: 56.5mm;
    display: grid;
    grid-template-columns: 55% 45%;
    border-radius: 3mm;
    overflow: hidden;
    background: #fffaf2;
    border: 1px solid rgba(201,168,106,.45);
    box-shadow: 0 5px 16px rgba(70,40,25,.14);
}


.combo-media {
    position: relative;
    overflow: hidden;
    background: #f8f1e8;
}

.combo-media img {
    width: 100%;
    height: 100%;
    display: block;
}

/* .combo-media.portrait img {
    object-fit: cover;
    object-position: center 46%;
}

.combo-media.landscape img {
    object-fit: cover;
    object-position: center center;
} */

.combo-media.portrait img {
    object-fit: cover;
    object-position: center 42%;
}

.combo-media.landscape img {
    object-fit: cover;
    object-position: center 46%;
}

.combo-media::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(to right, transparent 62%, rgba(255,250,242,.85));
}

.combo-text {
    padding: 4mm 4mm 3mm;
    display: flex;
    flex-direction: column;
    justify-content: center;
    background:
        linear-gradient(135deg, rgba(255,250,242,.98), rgba(246,234,214,.98));
}

.combo-kicker {
    width: fit-content;
    margin-bottom: 2mm;
    padding: 2px 7px;
    background: #C9A86A;
    color: #2a170b;
    font-family: Arial, sans-serif;
    font-size: 5.8px;
    font-weight: 900;
    letter-spacing: .8px;
    text-transform: uppercase;
    border-radius: 99px;
}

.combo-menu-list {
    display: grid;
    gap: 1.6mm;
    font-family: Arial, sans-serif;
}

.combo-menu-list div {
    display: grid;
    grid-template-columns: 1fr 13mm;
    gap: 2mm;
    align-items: baseline;
    border-bottom: 1px dashed rgba(106,78,58,.28);
    padding-bottom: .8mm;
}

.combo-menu-list div:last-child {
    border-bottom: none;
}

.combo-menu-list strong {
    font-size: 8.7px;
    color: #7D1F1F;
    letter-spacing: .35px;
    line-height: 1.05;
    text-transform: uppercase;
}

.combo-menu-list span {
    font-size: 10.8px;
    font-weight: 900;
    color: #7D1F1F;
    text-align: right;
    white-space: nowrap;
}

.combo-desc {
    margin-top: 2mm;
    font-family: Arial, sans-serif;
    font-size: 6.7px;
    line-height: 1.25;
    color: #6A4E3A;
}

/* WOK HIGHLIGHT */

.wok-highlight {
    height: 93.5mm;
    display: grid;
    grid-template-columns: 1.15fr 1fr;
    gap: 3mm;
}

.wok-feature {
    position: relative;
    overflow: hidden;
    border-radius: 3mm;
    background: #fffaf2;
    border: 1px solid rgba(201,168,106,.45);
    box-shadow: 0 5px 16px rgba(70,40,25,.14);
}

.wok-feature img,
.wok-card img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    filter: saturate(1.04) contrast(1.02);
}

.wok-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2.5mm;
}

.wok-card {
    position: relative;
    overflow: hidden;
    border-radius: 3mm;
    background: #fffaf2;
    border: 1px solid rgba(201,168,106,.45);
    box-shadow: 0 4px 13px rgba(70,40,25,.13);
}

.wok-info {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    padding: 12mm 2.4mm 2mm;
    background: linear-gradient(
        to top,
        rgba(38,22,14,.96) 0%,
        rgba(38,22,14,.80) 48%,
        rgba(38,22,14,.08) 100%
    );
    color: #fff;
}

.wok-info h4 {
    font-size: 8.3px;
    line-height: 1.05;
    letter-spacing: .25px;
    text-transform: uppercase;
    color: #fff;
    margin-bottom: .6mm;
    text-shadow: 0 1px 3px rgba(0,0,0,.6);
}

.wok-desc {
    margin-bottom: .8mm;
    font-family: Arial, sans-serif;
    font-size: 5.9px;
    line-height: 1.18;
    color: rgba(255,255,255,.82);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.price {
    font-family: Arial, sans-serif;
    font-size: 10.5px;
    font-weight: 900;
    color: #F1D28A;
    letter-spacing: .8px;
}

.wok-feature .wok-info {
    padding: 17mm 3.5mm 3mm;
}

.wok-feature .wok-info h4 {
    font-size: 13.5px;
}

.wok-feature .wok-desc {
    font-size: 7.2px;
}

.wok-feature .price {
    font-size: 14px;
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

        <article class="combo-card">
            <div class="combo-media portrait">
                <img src="<?= base_url('assets/menu-book/products/foods/asian-course/broth-bowl-combo.png') ?>" alt="Broth and Bowl Combo">
            </div>

            <div class="combo-text">
                <span class="combo-kicker">4 Bowl Selection</span>

                <div class="combo-menu-list">
                    <div><strong>SOYU RAMEN</strong><span>38K</span></div>
                    <div><strong>TORI PAITAN RAMEN</strong><span>35K</span></div>
                    <div><strong>SPICY RAMYEON</strong><span>32K</span></div>
                    <div><strong>GYU DON</strong><span>32K</span></div>
                </div>

                <div class="combo-desc">
                    Warm ramen, ramyeon and Japanese rice bowl in one signature Asian selection.
                </div>
            </div>
        </article>
    </section>

    <section>
        <div class="section-heading">
            <h3>ROLLS &amp; KIMBAP</h3>
            <p>Sushi Rolls · Korean Wrap</p>
        </div>

        <article class="combo-card">
            <div class="combo-media landscape">
                <img src="<?= base_url('assets/menu-book/products/foods/asian-course/rolls-kimbap-combo.png') ?>" alt="Rolls and Kimbap Combo">
            </div>

            <div class="combo-text">
                <span class="combo-kicker">Roll Selection</span>

                <div class="combo-menu-list">
                    <div><strong>KOREAN BEEF KIMBAB</strong><span>31K</span></div>
                    <div><strong>CALIFORNIA ROLL</strong><span>24K</span></div>
                    <div><strong>VOLCANO ROLL</strong><span>26K</span></div>
                    <div><strong>YAKINIKU SUSHI ROLL</strong><span>29K</span></div>
                </div>

                <div class="combo-desc">
                    Sushi rolls and Korean kimbap with beef, crab stick, tempura and yakiniku filling.
                </div>
            </div>
        </article>
    </section>

    <section>
        <div class="section-heading">
            <h3>WOK &amp; ASIAN PLATES</h3>
            <p>Fried Rice · Noodles · Katsu Plates</p>
        </div>

        <div class="wok-highlight">

            <article class="wok-feature">
                <img src="<?= base_url('assets/menu-book/products/foods/asian-course/mie-ayam-namua.png') ?>" alt="Mie Ayam Namua">
                <div class="wok-info">
                    <h4>MIE AYAM NAMUA</h4>
                    <div class="wok-desc">Asian noodle blanch, chicken leg marinated, garlic oyster, fried wonton & broth side dish.</div>
                    <div class="price">28K</div>
                </div>
            </article>

            <div class="wok-grid">

                <article class="wok-card">
                    <img src="<?= base_url('assets/menu-book/products/foods/asian-course/chicken-nori-yakimeshi.png') ?>" alt="Chicken Nori Yakimeshi">
                    <div class="wok-info">
                        <h4>CHICKEN NORI YAKIMESHI</h4>
                        <div class="wok-desc">Deep fry chicken katsu, yakimeshi, mix salad & teriyaki sauce.</div>
                        <div class="price">28K</div>
                    </div>
                </article>

                <article class="wok-card">
                    <img src="<?= base_url('assets/menu-book/products/foods/asian-course/chinese-fried-noodles.png') ?>" alt="Chinese Fried Noodles">
                    <div class="wok-info">
                        <h4>CHINESE FRIED NOODLES</h4>
                        <div class="wok-desc">Chinese style fried noodles, chicken inside, mix vegetable & crackers.</div>
                        <div class="price">25K</div>
                    </div>
                </article>

                <article class="wok-card">
                    <img src="<?= base_url('assets/menu-book/products/foods/asian-course/yang-chow-fried-rice.png') ?>" alt="Yang Chow Fried Rice">
                    <div class="wok-info">
                        <h4>YANG CHOW FRIED RICE</h4>
                        <div class="wok-desc">Chinese wok fried rice, smoked beef, sunny side up egg & crackers.</div>
                        <div class="price">25K</div>
                    </div>
                </article>

                <article class="wok-card">
                    <img src="<?= base_url('assets/menu-book/products/foods/asian-course/crispy-dory-ala-thai.png') ?>" alt="Crispy Dory Ala Thai">
                    <div class="wok-info">
                        <h4>CRISPY DORY ALA THAI</h4>
                        <div class="wok-desc">Deep fry dory fish, mix flour, tomyam paste & Bangkok sauce.</div>
                        <div class="price">25K</div>
                    </div>
                </article>

            </div>

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