<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Asian Signatures — NAMUA Coffee & Eatery</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700;800;900&family=Playfair+Display:wght@600;700;800;900&family=Pinyon+Script&display=swap" rel="stylesheet">

<style>
@page { size: A4 portrait; margin: 0; }

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html, body {
    background: #21160f;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

body {
    font-family: 'Jost', Arial, sans-serif;
}

.menu-page {
    width: 210mm;
    height: 297mm;
    margin: 0 auto;
    position: relative;
    overflow: hidden;
    padding: 8mm;
    background:
        radial-gradient(circle at 16% 8%, rgba(201,162,78,.24), transparent 30%),
        radial-gradient(circle at 90% 88%, rgba(168,35,44,.10), transparent 34%),
        linear-gradient(135deg, rgba(255,251,242,.98), rgba(246,239,225,.99)),
        url('<?= base_url('assets/menu-book/backgrounds/bg-asian.png') ?>') center / cover no-repeat,
        #F6EFE1;
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
    border: .8px solid rgba(168,35,44,.36);
    pointer-events: none;
}

.page-inner {
    position: relative;
    z-index: 2;
    height: 100%;
    display: grid;
    grid-template-rows: 27mm 1fr 7mm;
    gap: 3mm;
}

/* HEADER */
.header {
    display: grid;
    grid-template-columns: 24mm 1fr 18mm;
    align-items: center;
    border-bottom: 1px solid rgba(201,162,78,.58);
    padding-bottom: 3mm;
}

.logo-wrap {
    width: 19mm;
    height: 19mm;
    border-radius: 50%;
    background: #fffdf8;
    display: grid;
    place-items: center;
    box-shadow: 0 5px 16px rgba(80,35,20,.18);
    border: 1px solid rgba(201,162,78,.48);
}

.logo-wrap img {
    width: 16mm;
    height: 16mm;
    object-fit: contain;
}

.title-area {
    text-align: center;
}

.title-area .script {
    display: block;
    font-family: 'Pinyon Script', cursive;
    font-size: 22px;
    line-height: .75;
    color: #C9A24E;
    margin-bottom: -1mm;
}

.title-area h1 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 32px;
    line-height: .92;
    letter-spacing: 3px;
    color: #A8232C;
    text-transform: uppercase;
    font-weight: 800;
}

.title-area p {
    margin-top: 1.2mm;
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 10.7px;
    font-style: italic;
    color: #6A4E3A;
}

.page-number {
    text-align: right;
    font-size: 7px;
    letter-spacing: 2.5px;
    color: #B68C39;
    font-weight: 800;
}

/* CONTENT */
.content {
    height: 247mm;
    display: grid;
    grid-template-rows: 64mm 64mm 113mm;
    gap: 3mm;
    overflow: hidden;
}

.section-head {
    height: 7mm;
    display: flex;
    justify-content: space-between;
    align-items: end;
    border-bottom: 1px solid rgba(201,162,78,.62);
    margin-bottom: 1.7mm;
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
    letter-spacing: 1.8px;
    color: #B68C39;
    text-transform: uppercase;
    font-weight: 800;
}

/* TOP COMBO */
.combo {
    height: 55mm;
    display: grid;
    grid-template-columns: 58% 42%;
    overflow: hidden;
    border-radius: 4mm;
    background: #fffaf0;
    border: 1px solid rgba(201,162,78,.66);
    box-shadow: 0 8px 22px rgba(70,40,25,.13);
}

.combo-photo {
    background:
        radial-gradient(circle at center, rgba(255,255,255,.28), transparent 60%),
        #F2E7CF;
    overflow: hidden;
    display: grid;
    place-items: center;
}

.combo-photo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    object-position: center;
    padding: 2mm;
    display: block;
}

.combo-info {
    padding: 4.5mm 5mm;
    display: flex;
    flex-direction: column;
    justify-content: center;
    background:
        linear-gradient(135deg, rgba(255,250,240,.98), rgba(244,229,198,.96));
}

.badge {
    width: fit-content;
    padding: 3px 10px;
    border-radius: 99px;
    background: #C9A24E;
    color: #2B170B;
    font-size: 6.7px;
    letter-spacing: 1px;
    font-weight: 900;
    text-transform: uppercase;
    margin-bottom: 2mm;
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
}

.menu-row b {
    text-align: right;
    font-size: 12px;
    color: #A8232C;
    font-weight: 900;
}

.combo-note {
    margin-top: 1.7mm;
    font-size: 6.8px;
    line-height: 1.32;
    color: #6A4E3A;
}

/* WOK */
.wok-layout {
    height: 104mm;
    display: grid;
    grid-template-columns: 65mm 1fr;
    gap: 3mm;
    overflow: hidden;
}

.hero {
    position: relative;
    overflow: hidden;
    border-radius: 4mm;
    background: #F2E7CF;
    border: 1px solid rgba(201,162,78,.66);
    box-shadow: 0 8px 22px rgba(70,40,25,.14);
}

.hero img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center 45%;
    display: block;
}

.hero-caption {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    padding: 14mm 4mm 3.4mm;
    background: linear-gradient(
        to top,
        rgba(35,20,10,.98),
        rgba(35,20,10,.80) 56%,
        rgba(35,20,10,0)
    );
    color: white;
}

.hero-caption small {
    display: block;
    color: #C9A24E;
    font-size: 6px;
    letter-spacing: 1.6px;
    font-weight: 900;
    text-transform: uppercase;
    margin-bottom: .8mm;
}

.hero-caption h3 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 15px;
    line-height: .95;
    text-transform: uppercase;
    color: #fff;
}

.hero-caption p {
    margin-top: 1mm;
    font-size: 6.8px;
    line-height: 1.25;
    color: rgba(255,255,255,.88);
}

.hero-caption b {
    display: block;
    margin-top: 1.2mm;
    font-size: 14px;
    color: #F1D28A;
    font-weight: 900;
}

/* RIGHT CARDS */
.plates {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2.5mm;
    overflow: hidden;
}

.plate {
    overflow: hidden;
    border-radius: 3.5mm;
    background: #fffaf0;
    border: 1px solid rgba(201,162,78,.62);
    box-shadow: 0 6px 16px rgba(70,40,25,.11);
    display: grid;
    grid-template-rows: 31mm 1fr;
}

.plate-photo {
    background: #F2E7CF;
    overflow: hidden;
}

.plate-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    display: block;
}

.plate-body {
    padding: 2.3mm 2.7mm;
    display: grid;
    grid-template-rows: auto 1fr auto;
    background: linear-gradient(180deg, #fffaf0, #f5e8d0);
}

.plate-body h3 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 9.3px;
    line-height: 1.05;
    color: #A8232C;
    text-transform: uppercase;
    font-weight: 800;
    margin-bottom: .7mm;
}

.plate-body p {
    font-size: 5.8px;
    line-height: 1.2;
    color: #6A4E3A;
    overflow: hidden;
}

.plate-foot {
    display: flex;
    justify-content: space-between;
    align-items: end;
    margin-top: .7mm;
}

.plate-foot span {
    font-size: 5.4px;
    letter-spacing: 1.1px;
    color: #B68C39;
    text-transform: uppercase;
    font-weight: 900;
}

.plate-foot b {
    font-size: 11.3px;
    color: #A8232C;
    font-weight: 900;
}

/* FOOTER */
.footer {
    border-top: 1px solid rgba(201,162,78,.60);
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    padding-top: 1.5mm;
    font-size: 6.4px;
    letter-spacing: 1.8px;
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
        <span class="script">Asian</span>
        <h1>Signatures</h1>
        <p>Bowls, rolls &amp; wok favourites.</p>
    </div>

    <div class="page-number">05</div>
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