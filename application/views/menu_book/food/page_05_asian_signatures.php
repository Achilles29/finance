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
    padding: 8mm 9mm 12mm;
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
    height: 28mm;
    display: grid;
    grid-template-columns: 22mm 1fr 16mm;
    align-items: center;
    border-bottom: 1px solid rgba(125,31,31,.16);
    padding-bottom: 3.5mm;
    margin-bottom: 3.5mm;
}

.menu-logo {
    width: 15mm;
    height: 15mm;
    object-fit: contain;
}

.header-title-area {
    text-align: center;
}

.label-category {
    display: block;
    font-family: Arial, sans-serif;
    font-size: 6.5px;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: #b58b4b;
    margin-bottom: 1.5mm;
}

.page-title {
    font-size: 26px;
    line-height: .95;
    color: #7D1F1F;
    letter-spacing: 1.5px;
    text-transform: uppercase;
}

.header-title-area h2 {
    margin-top: 1.5mm;
    font-size: 10.5px;
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
    height: 232mm;
    display: grid;
    grid-template-rows: 83mm 67mm 70mm 7mm;
    gap: 3mm;
}

/* SECTION */

.section-heading {
    height: 9mm;
    display: flex;
    justify-content: space-between;
    align-items: end;
    border-bottom: 1px solid rgba(201,168,106,.45);
    padding-bottom: 1.3mm;
    margin-bottom: 2mm;
}

.section-heading h3 {
    font-size: 14px;
    color: #7D1F1F;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.section-heading p {
    font-family: Arial, sans-serif;
    font-size: 7px;
    letter-spacing: 1.1px;
    text-transform: uppercase;
    color: #9a7440;
}

/* CARD BASE */

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
    object-fit: cover;
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
    padding: 14mm 3mm 2.4mm;
    background: linear-gradient(
        to top,
        rgba(38,22,14,.95) 0%,
        rgba(38,22,14,.82) 48%,
        rgba(38,22,14,.10) 100%
    );
    color: #fff;
    z-index: 3;
}

.asian-info h4 {
    font-size: 10px;
    line-height: 1.05;
    letter-spacing: .35px;
    text-transform: uppercase;
    color: #fff;
    margin-bottom: .8mm;
    text-shadow: 0 1px 3px rgba(0,0,0,.6);
}

.asian-desc {
    margin-bottom: 1mm;
    font-family: Arial, sans-serif;
    font-size: 7px;
    line-height: 1.18;
    color: rgba(255,255,255,.83);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.price {
    font-family: Arial, sans-serif;
    font-size: 12px;
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
    font-size: 5.8px;
    font-weight: 900;
    letter-spacing: .8px;
    text-transform: uppercase;
    border-radius: 99px;
}

/* BROTH & BOWL */

.broth-layout {
    display: grid;
    grid-template-columns: 1.45fr 1fr 1fr;
    grid-template-rows: repeat(2, 35mm);
    gap: 2.5mm;
}

.hero-ramen {
    grid-row: span 2;
}

.hero-ramen .asian-info {
    padding: 20mm 4mm 3.5mm;
}

.hero-ramen .asian-info h4 {
    font-size: 17px;
}

.hero-ramen .asian-desc {
    font-size: 8px;
}

.hero-ramen .price {
    font-size: 17px;
}

/* ROLLS */

.rolls-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2.5mm;
}

.rolls-grid .asian-card {
    height: 55mm;
}

/* WOK */

.wok-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    grid-template-rows: repeat(2, 30mm);
    gap: 2.5mm;
}

.wok-grid .asian-card {
    grid-column: span 2;
}

.wok-grid .asian-card:nth-child(4),
.wok-grid .asian-card:nth-child(5) {
    grid-column: span 3;
}

.wok-grid .asian-info {
    padding: 11mm 2.4mm 2mm;
}

.wok-grid .asian-info h4 {
    font-size: 8.8px;
}

.wok-grid .asian-desc {
    font-size: 6.3px;
}

.wok-grid .price {
    font-size: 10.5px;
}

.note-row {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 5mm;
    font-family: Arial, sans-serif;
    font-size: 6.8px;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    color: #7D1F1F;
    border-top: 1px solid rgba(125,31,31,.16);
    padding-top: 2mm;
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

        <div class="broth-layout">

            <article class="asian-card hero-ramen">
                <img src="<?= base_url('assets/menu-book/products/foods/asian-course/soyu-ramen.png') ?>" alt="Soyu Ramen">
                <div class="asian-info">
                    <span class="mini-badge">Signature Ramen</span>
                    <h4>SOYU RAMEN</h4>
                    <div class="asian-desc">Japanese noodle ramen, ginger broth, boiled egg & beef shortplate.</div>
                    <div class="price">38K</div>
                </div>
            </article>

            <article class="asian-card">
                <img src="<?= base_url('assets/menu-book/products/foods/asian-course/tori-paitan-ramen.png') ?>" alt="Tori Paitan Ramen">
                <div class="asian-info">
                    <span class="mini-badge">Ramen</span>
                    <h4>TORI PAITAN RAMEN</h4>
                    <div class="asian-desc">Chicken stock broth, mushroom, boiled egg & chicken katsu.</div>
                    <div class="price">35K</div>
                </div>
            </article>

            <article class="asian-card">
                <img src="<?= base_url('assets/menu-book/products/foods/asian-course/spicy-ramyeon.png') ?>" alt="Spicy Ramyeon">
                <div class="asian-info">
                    <span class="mini-badge">Spicy</span>
                    <h4>SPICY RAMYEON</h4>
                    <div class="asian-desc">Kani stick, chikuwa, enoki, chilli sauce, katsuobushi & sesame seed.</div>
                    <div class="price">32K</div>
                </div>
            </article>

            <article class="asian-card">
                <img src="<?= base_url('assets/menu-book/products/foods/asian-course/gyu-don.png') ?>" alt="Gyu Don">
                <div class="asian-info">
                    <span class="mini-badge">Rice Bowl</span>
                    <h4>GYU DON</h4>
                    <div class="asian-desc">Japanese rice bowl with beef shortplate teriyaki & poached egg.</div>
                    <div class="price">32K</div>
                </div>
            </article>

        </div>
    </section>

    <section>
        <div class="section-heading">
            <h3>ROLLS &amp; KIMBAP</h3>
            <p>Sushi rolls · Korean wrap</p>
        </div>

        <div class="rolls-grid">

            <article class="asian-card">
                <img src="<?= base_url('assets/menu-book/products/foods/asian-course/korean-beef-kimbab.png') ?>" alt="Korean Beef Kimbab">
                <div class="asian-info">
                    <span class="mini-badge">Kimbap</span>
                    <h4>KOREAN BEEF KIMBAB</h4>
                    <div class="asian-desc">Seaweed rice wrap filled beef bulgogi, cucumber & carrot.</div>
                    <div class="price">31K</div>
                </div>
            </article>

            <article class="asian-card">
                <img src="<?= base_url('assets/menu-book/products/foods/asian-course/california-roll.png') ?>" alt="California Roll">
                <div class="asian-info">
                    <span class="mini-badge">Sushi</span>
                    <h4>CALIFORNIA ROLL</h4>
                    <div class="asian-desc">Sushi roll filled crab stick, nori & tobiko.</div>
                    <div class="price">24K</div>
                </div>
            </article>

            <article class="asian-card">
                <img src="<?= base_url('assets/menu-book/products/foods/asian-course/volcano-roll.png') ?>" alt="Volcano Roll">
                <div class="asian-info">
                    <span class="mini-badge">Sushi</span>
                    <h4>VOLCANO ROLL</h4>
                    <div class="asian-desc">Chicken tempura, cucumber, crab stick & volcano sauce on top.</div>
                    <div class="price">26K</div>
                </div>
            </article>

            <article class="asian-card">
                <img src="<?= base_url('assets/menu-book/products/foods/asian-course/yakiniku-sushi-roll.png') ?>" alt="Yakiniku Sushi Roll">
                <div class="asian-info">
                    <span class="mini-badge">Yakiniku</span>
                    <h4>YAKINIKU SUSHI ROLL</h4>
                    <div class="asian-desc">Beef shortplate yakiniku, cucumber, crackers & katsuobushi.</div>
                    <div class="price">29K</div>
                </div>
            </article>

        </div>
    </section>

    <section>
        <div class="section-heading">
            <h3>WOK &amp; ASIAN PLATES</h3>
            <p>Fried rice · noodles · katsu plates</p>
        </div>

        <div class="wok-grid">

            <article class="asian-card">
                <img src="<?= base_url('assets/menu-book/products/foods/asian-course/yang-chow-fried-rice.png') ?>" alt="Yang Chow Fried Rice">
                <div class="asian-info">
                    <h4>YANG CHOW FRIED RICE</h4>
                    <div class="asian-desc">Chinese style fried rice, chicken inside, mix vegetable & crackers.</div>
                    <div class="price">25K</div>
                </div>
            </article>

            <article class="asian-card">
                <img src="<?= base_url('assets/menu-book/products/foods/asian-course/chinese-fried-noodles.png') ?>" alt="Chinese Fried Noodles">
                <div class="asian-info">
                    <h4>CHINESE FRIED NOODLES</h4>
                    <div class="asian-desc">Chinese wok fried noodles, smoked beef, sunny side up egg & crackers.</div>
                    <div class="price">25K</div>
                </div>
            </article>

            <article class="asian-card">
                <img src="<?= base_url('assets/menu-book/products/foods/asian-course/crispy-dory-ala-thai.png') ?>" alt="Crispy Dory Ala Thai">
                <div class="asian-info">
                    <h4>CRISPY DORY ALA THAI</h4>
                    <div class="asian-desc">Deep fry dory fish, mix flour, tomyam paste & Bangkok sauce.</div>
                    <div class="price">25K</div>
                </div>
            </article>

            <article class="asian-card">
                <img src="<?= base_url('assets/menu-book/products/foods/asian-course/chicken-nori-yakimeshi.png') ?>" alt="Chicken Nori Yakimeshi">
                <div class="asian-info">
                    <h4>CHICKEN NORI YAKIMESHI</h4>
                    <div class="asian-desc">Chicken katsu, yakimeshi, mix salad & teriyaki sauce.</div>
                    <div class="price">28K</div>
                </div>
            </article>

            <article class="asian-card">
                <img src="<?= base_url('assets/menu-book/products/foods/asian-course/mie-ayam-namua.png') ?>" alt="Mie Ayam Namua">
                <div class="asian-info">
                    <h4>MIE AYAM NAMUA</h4>
                    <div class="asian-desc">Asian noodle blanch, chicken leg marinated, fried wonton & broth side dish.</div>
                    <div class="price">28K</div>
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