<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Asian Signatures — NAMUA Coffee & Eatery</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700;800;900&family=Playfair+Display:wght@500;600;700;800;900&family=Pinyon+Script&display=swap" rel="stylesheet">

<style>
@page { size: A4 portrait; margin: 0; }

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

html, body {
    background: #21160f;
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
    padding: 8mm;
    background:
        radial-gradient(circle at 20% 8%, rgba(201,162,78,.26), transparent 30%),
        radial-gradient(circle at 88% 85%, rgba(168,35,44,.12), transparent 34%),
        linear-gradient(135deg, rgba(255,252,244,.98), rgba(244,235,217,.99)),
        url('<?= base_url('assets/menu-book/backgrounds/bg-asian.png') ?>') center / cover no-repeat,
        #F6EFE1;
    box-shadow:
        0 26px 60px rgba(24, 12, 7, .38),
        inset 0 0 0 1px rgba(255,255,255,.3);
}

.menu-page::before {
    content: "";
    position: absolute;
    inset: 5mm;
    border: 1.4px solid rgba(201,162,78,.72);
    pointer-events: none;
}

.menu-page::after {
    content: "";
    position: absolute;
    inset: 6.8mm;
    border: .8px solid rgba(168,35,44,.35);
    pointer-events: none;
}

.page-inner {
    position: relative;
    z-index: 2;
    height: 100%;
    display: grid;
    grid-template-rows: 25mm 1fr 6.5mm;
    gap: 2.5mm;
}

/* HEADER */
.header {
    display: grid;
    grid-template-columns: 24mm 1fr 18mm;
    align-items: center;
    border-bottom: 1px solid rgba(201,162,78,.58);
    padding-bottom: 2.5mm;
    position: relative;
}

.header::after {
    content: "";
    position: absolute;
    left: 24mm;
    right: 19mm;
    bottom: -1px;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(168,35,44,.2), transparent);
}

.logo-wrap {
    width: 18mm;
    height: 18mm;
    border-radius: 50%;
    background:
        radial-gradient(circle at 35% 30%, rgba(255,255,255,.95), rgba(248,236,214,.9) 60%, rgba(228,205,161,.9));
    display: grid;
    place-items: center;
    border: 1px solid rgba(201,162,78,.58);
    box-shadow:
        0 5px 14px rgba(84,46,27,.14),
        inset 0 0 0 1px rgba(255,255,255,.72);
}

.logo-wrap img {
    width: 15.5mm;
    height: 15.5mm;
    object-fit: contain;
}

.title-area {
    text-align: center;
}

.title-area .script {
    display: block;
    font-family: 'Pinyon Script', cursive;
    font-size: 20px;
    line-height: .75;
    color: #C9A24E;
    margin-bottom: -1mm;
}

.title-area h1 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 31px;
    line-height: .92;
    letter-spacing: 2.5px;
    color: #A8232C;
    text-transform: uppercase;
    font-weight: 800;
    text-shadow: 0 1px 0 rgba(255,255,255,.45);
}

.title-area p {
    margin-top: 1.2mm;
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 10.5px;
    font-style: italic;
    color: #6A4E3A;
    letter-spacing: .2px;
}

.page-number {
    width: 15.5mm;
    height: 15.5mm;
    justify-self: end;
    border-radius: 50%;
    display: grid;
    place-items: center;
    text-align: center;
    font-size: 7px;
    letter-spacing: 2.5px;
    color: #fff6e8;
    font-weight: 800;
    background:
        radial-gradient(circle at 35% 30%, rgba(186,48,61,.9), rgba(123,22,34,.98) 65%);
    border: 1px solid rgba(201,162,78,.65);
    box-shadow:
        0 8px 18px rgba(122,27,37,.24),
        inset 0 0 0 1px rgba(255,255,255,.12);
}

/* CONTENT */
.content {
    height: 249mm;
    display: grid;
    grid-template-rows: 74mm 74mm 95mm;
    gap: 3mm;
    overflow: hidden;
}

.section-head {
    height: 7mm;
    display: flex;
    justify-content: space-between;
    align-items: end;
    border-bottom: 1px solid rgba(201,162,78,.62);
    margin-bottom: 1.5mm;
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
    font-size: 6.6px;
    letter-spacing: 1.8px;
    color: #B68C39;
    text-transform: uppercase;
    font-weight: 800;
    padding: 1mm 2.2mm .8mm;
    border-radius: 999px;
    background: rgba(255,248,233,.92);
    border: 1px solid rgba(201,162,78,.35);
}

/* BROTH & ROLLS */
.combo {
    height: 65.5mm;
    display: grid;
    grid-template-columns: 60% 40%;
    overflow: hidden;
    border-radius: 4mm;
    background: #fffaf0;
    border: 1px solid rgba(201,162,78,.65);
    box-shadow:
        0 10px 24px rgba(70,40,25,.13),
        inset 0 0 0 1px rgba(255,255,255,.5);
    position: relative;
}

.combo::after {
    content: "";
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: linear-gradient(180deg, rgba(255,255,255,.14), transparent 24%, transparent 78%, rgba(171,108,47,.06));
}

.combo-photo {
    background:
        radial-gradient(circle at 18% 18%, rgba(255,255,255,.7), transparent 28%),
        linear-gradient(135deg, #f8edd7, #ead8b5);
    overflow: hidden;
    display: grid;
    place-items: center;
}

.combo-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    display: block;
    filter: saturate(1.03) contrast(1.02) brightness(1.01);
}

.combo-info {
    padding: 4.5mm 5mm;
    display: flex;
    flex-direction: column;
    justify-content: center;
    background:
        linear-gradient(145deg, rgba(255,252,245,.98), rgba(241,226,194,.94));
    position: relative;
}

.combo-info::before {
    content: "";
    position: absolute;
    left: 0;
    top: 4mm;
    bottom: 4mm;
    width: 1px;
    background: linear-gradient(180deg, transparent, rgba(201,162,78,.42), transparent);
}

.badge {
    width: fit-content;
    padding: 3px 11px;
    border-radius: 99px;
    background: linear-gradient(135deg, #e5c982, #c89b3f);
    color: #3b220f;
    font-size: 6.5px;
    letter-spacing: 1.15px;
    font-weight: 900;
    text-transform: uppercase;
    margin-bottom: 2mm;
    box-shadow: 0 4px 10px rgba(169,116,39,.18);
}

.menu-list {
    display: grid;
    gap: 1.35mm;
}

.menu-row {
    display: grid;
    grid-template-columns: 1fr 14mm;
    align-items: baseline;
    gap: 2mm;
    padding-bottom: .75mm;
    border-bottom: 1px dotted rgba(106,78,58,.35);
}

.menu-row strong {
    font-size: 9.8px;
    line-height: 1.08;
    color: #A8232C;
    text-transform: uppercase;
    font-weight: 800;
    letter-spacing: .2px;
}

.menu-row b {
    text-align: right;
    font-size: 12px;
    color: #A8232C;
    font-weight: 900;
    text-shadow: 0 1px 0 rgba(255,255,255,.5);
}

.combo-note {
    margin-top: 1.7mm;
    font-size: 6.8px;
    line-height: 1.36;
    color: #6A4E3A;
}

/* WOK */
.wok-layout {
    height: 86.5mm;
    display: grid;
    grid-template-columns: 55mm 1fr;
    gap: 2.5mm;
    overflow: hidden;
}

.hero {
    position: relative;
    overflow: hidden;
    border-radius: 4mm;
    background:
        linear-gradient(160deg, #fbf0dc, #ead7b2);
    border: 1px solid rgba(201,162,78,.65);
    box-shadow:
        0 10px 24px rgba(70,40,25,.15),
        inset 0 0 0 1px rgba(255,255,255,.45);
}

.hero img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center 48%;
    display: block;
    filter: saturate(1.04) contrast(1.03);
}

.hero-caption {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    padding: 11mm 3.2mm 2.8mm;
    background: linear-gradient(
        to top,
        rgba(35,20,10,.98),
        rgba(35,20,10,.80) 56%,
        rgba(35,20,10,0)
    );
    color: white;
    backdrop-filter: blur(.4px);
}

.hero-caption small {
    display: block;
    color: #C9A24E;
    font-size: 5.3px;
    letter-spacing: 1.3px;
    font-weight: 900;
    text-transform: uppercase;
    margin-bottom: .6mm;
}

.hero-caption h3 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 12.5px;
    line-height: .95;
    text-transform: uppercase;
    color: #fff;
    text-shadow: 0 2px 8px rgba(0,0,0,.2);
}

.hero-caption p {
    margin-top: .8mm;
    font-size: 5.7px;
    line-height: 1.18;
    color: rgba(255,255,255,.88);
}

.hero-caption b {
    display: block;
    margin-top: .9mm;
    font-size: 12.5px;
    color: #F1D28A;
    font-weight: 900;
}

/* RIGHT CARDS */
.plates {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2.2mm;
    overflow: hidden;
}

.plate {
    overflow: hidden;
    border-radius: 3.3mm;
    background: #fffaf0;
    border: 1px solid rgba(201,162,78,.62);
    box-shadow:
        0 7px 16px rgba(70,40,25,.11),
        inset 0 0 0 1px rgba(255,255,255,.52);
    display: grid;
    grid-template-rows: 26mm 1fr;
    position: relative;
}

.plate::after {
    content: "";
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: linear-gradient(180deg, rgba(255,255,255,.08), transparent 25%);
}

.plate-photo {
    background:
        linear-gradient(160deg, #f9ecd4, #ead7b4);
    overflow: hidden;
}

.plate-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center 46%;
    display: block;
    filter: saturate(1.03) contrast(1.02);
}

.plate-body {
    padding: 1.9mm 2.3mm;
    display: grid;
    grid-template-rows: auto 1fr auto;
    background: linear-gradient(180deg, #fffaf2, #f4e4c4);
}

.plate-body h3 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 8.6px;
    line-height: 1.04;
    color: #A8232C;
    text-transform: uppercase;
    font-weight: 800;
    margin-bottom: .45mm;
}

.plate-body p {
    font-size: 5.1px;
    line-height: 1.18;
    color: #6A4E3A;
    overflow: hidden;
}

.plate-foot {
    display: flex;
    justify-content: space-between;
    align-items: end;
    margin-top: .35mm;
}

.plate-foot span {
    font-size: 4.9px;
    letter-spacing: .9px;
    color: #B68C39;
    text-transform: uppercase;
    font-weight: 900;
}

.plate-foot b {
    font-size: 10.5px;
    color: #A8232C;
    font-weight: 900;
}

/* FOOTER */
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
    opacity: .94;
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
        <span class="script">Asian</span>
        <h1>Signatures</h1>
        <p>Bowls, rolls &amp; wok favourites.</p>
    </div>

</header>

<main class="content">

    <section>
        <div class="section-head">
            <h2>Broth &amp; Bowl</h2>
            <span>Ramen · Ramyeon · Gyudon</span>
        </div>

        <article class="combo">
            <div class="combo-photo">
                <img src="<?= base_url('assets/menu-book/products/foods/asian-course/broth-bowl-combo.png') ?>" alt="">
            </div>

            <div class="combo-info">
                <div class="badge">4 Bowl Selection</div>

                <div class="menu-list">
                    <div class="menu-row"><strong>Soyu Ramen</strong><b>38K</b></div>
                    <div class="menu-row"><strong>Tori Paitan Ramen</strong><b>35K</b></div>
                    <div class="menu-row"><strong>Spicy Ramyeon</strong><b>32K</b></div>
                    <div class="menu-row"><strong>Gyu Don</strong><b>32K</b></div>
                </div>

                <div class="combo-note">
                    Warm ramen, ramyeon and Japanese rice bowl in one signature Asian selection.
                </div>
            </div>
        </article>
    </section>

    <section>
        <div class="section-head">
            <h2>Rolls &amp; Kimbap</h2>
            <span>Sushi Rolls · Korean Wrap</span>
        </div>

        <article class="combo">
            <div class="combo-photo">
                <img src="<?= base_url('assets/menu-book/products/foods/asian-course/rolls-kimbap-combo.png') ?>" alt="">
            </div>

            <div class="combo-info">
                <div class="badge">Roll Selection</div>

                <div class="menu-list">
                    <div class="menu-row"><strong>Korean Beef Kimbab</strong><b>31K</b></div>
                    <div class="menu-row"><strong>California Roll</strong><b>24K</b></div>
                    <div class="menu-row"><strong>Volcano Roll</strong><b>26K</b></div>
                    <div class="menu-row"><strong>Yakiniku Sushi Roll</strong><b>29K</b></div>
                </div>

                <div class="combo-note">
                    Sushi rolls and Korean kimbap with beef, crab stick, tempura and yakiniku filling.
                </div>
            </div>
        </article>
    </section>

    <section>
        <div class="section-head">
            <h2>Wok &amp; Asian Plates</h2>
            <span>Fried Rice · Noodles · Katsu Plates</span>
        </div>

        <div class="wok-layout">

            <article class="hero">
                <img src="<?= base_url('assets/menu-book/products/foods/asian-course/mie-ayam-namua.png') ?>" alt="">
                <div class="hero-caption">
                    <small>Namua Asian Hero</small>
                    <h3>Mie Ayam Namua</h3>
                    <p>Asian noodle blanch, chicken leg marinated, garlic oyster, fried wonton &amp; broth side dish.</p>
                    <b>28K</b>
                </div>
            </article>

            <div class="plates">

                <article class="plate">
                    <div class="plate-photo">
                        <img src="<?= base_url('assets/menu-book/products/foods/asian-course/chicken-nori-yakimeshi.png') ?>" alt="">
                    </div>
                    <div class="plate-body">
                        <h3>Chicken Nori Yakimeshi</h3>
                        <p>Deep fry chicken katsu, yakimeshi, mix salad &amp; teriyaki sauce.</p>
                        <div class="plate-foot"><span>Rice Plate</span><b>28K</b></div>
                    </div>
                </article>

                <article class="plate">
                    <div class="plate-photo">
                        <img src="<?= base_url('assets/menu-book/products/foods/asian-course/chinese-fried-noodles.png') ?>" alt="">
                    </div>
                    <div class="plate-body">
                        <h3>Chinese Fried Noodles</h3>
                        <p>Chinese style fried noodles, chicken inside, mix vegetable &amp; crackers.</p>
                        <div class="plate-foot"><span>Noodles</span><b>25K</b></div>
                    </div>
                </article>

                <article class="plate">
                    <div class="plate-photo">
                        <img src="<?= base_url('assets/menu-book/products/foods/asian-course/yang-chow-fried-rice.png') ?>" alt="">
                    </div>
                    <div class="plate-body">
                        <h3>Yang Chow Fried Rice</h3>
                        <p>Chinese wok fried rice, smoked beef, sunny side up egg &amp; crackers.</p>
                        <div class="plate-foot"><span>Fried Rice</span><b>25K</b></div>
                    </div>
                </article>

                <article class="plate">
                    <div class="plate-photo">
                        <img src="<?= base_url('assets/menu-book/products/foods/asian-course/crispy-dory-ala-thai.png') ?>" alt="">
                    </div>
                    <div class="plate-body">
                        <h3>Crispy Dory Ala Thai</h3>
                        <p>Deep fry dory fish, tomyam paste &amp; Bangkok sauce.</p>
                        <div class="plate-foot"><span>Thai Plate</span><b>25K</b></div>
                    </div>
                </article>

            </div>
        </div>
    </section>

</main>

<footer class="footer">
    <span>NAMUA Coffee &amp; Eatery</span>
    <span>Asian Course</span>
    <span>#kembalikenamua</span>
</footer>

</div>
</section>

</body>
</html>
