<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700;800;900&family=Playfair+Display:wght@500;600;700;800;900&family=Pinyon+Script&display=swap" rel="stylesheet">
<title>Bowl, Spice & Noodles — NAMUA Coffee & Eatery</title>

<style>
@page { size: A4 portrait; margin: 0; }

*, *::before, *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    background: #21120b;
    font-family: 'Jost', Arial, sans-serif;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    color: #4d3326;
    text-rendering: geometricPrecision;
}

.menu-page {
    width: 210mm;
    height: 297mm;
    margin: 0 auto;
    position: relative;
    overflow: hidden;
    padding: 8mm 9mm 12mm;
    background:
        radial-gradient(circle at 18% 10%, rgba(201,168,106,.22), transparent 26%),
        radial-gradient(circle at 84% 82%, rgba(125,31,31,.10), transparent 30%),
        linear-gradient(rgba(251,245,236,.90), rgba(247,238,226,.96)),
        url('<?= base_url('assets/menu-book/backgrounds/bg-bowl-spice-noodles.png') ?>') center / cover no-repeat,
        #f8f1e8;
    box-shadow: 0 28px 58px rgba(29,15,9,.36), inset 0 0 0 1px rgba(255,255,255,.34);
}

.menu-page::before {
    content: '';
    position: absolute;
    inset: 5mm;
    border: 1px solid rgba(201,168,106,.48);
    pointer-events: none;
    z-index: 1;
}

.menu-page::after {
    content: '';
    position: absolute;
    inset: 6.8mm;
    border: .8px solid rgba(125,31,31,.22);
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
    height: 30mm;
    display: grid;
    grid-template-columns: 22mm 1fr 16mm;
    align-items: center;
    border-bottom: 1px solid rgba(125,31,31,.20);
    padding-bottom: 4mm;
    margin-bottom: 4mm;
    position: relative;
}

.menu-header::after {
    content: '';
    position: absolute;
    left: 24mm;
    right: 18mm;
    bottom: -1px;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(201,168,106,.55), transparent);
}

.menu-logo-wrap {
    width: 18mm;
    height: 18mm;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: radial-gradient(circle at 35% 30%, rgba(255,255,255,.94), rgba(247,233,207,.88) 60%, rgba(225,200,152,.9));
    border: 1px solid rgba(201,168,106,.55);
    box-shadow: 0 6px 15px rgba(84,46,27,.14), inset 0 0 0 1px rgba(255,255,255,.76);
}

.menu-logo {
    width: 15mm;
    height: 15mm;
    object-fit: contain;
    display: block;
}

.header-title-area { text-align: center; }

.label-category {
    display: block;
    font-family: 'Jost', Arial, sans-serif;
    font-size: 6.3px;
    letter-spacing: 3.4px;
    text-transform: uppercase;
    color: #b58b4b;
    margin-bottom: 1.4mm;
    font-weight: 800;
}

.label-category::after {
    content: '';
    display: block;
    width: 36mm;
    height: 1px;
    margin: 1.4mm auto 0;
    background: linear-gradient(90deg, transparent, rgba(201,168,106,.58), transparent);
}

.page-title {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 28px;
    line-height: .95;
    color: #7D1F1F;
    letter-spacing: 1.8px;
    text-transform: uppercase;
    font-weight: 800;
    text-shadow: 0 1px 0 rgba(255,255,255,.48);
}

.header-title-area h2 {
    margin-top: 1.6mm;
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 11.2px;
    font-style: italic;
    font-weight: 400;
    color: #6A4E3A;
    letter-spacing: .15px;
}

.page-number {
    width: 16mm;
    height: 16mm;
    justify-self: end;
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-family: 'Jost', Arial, sans-serif;
    font-size: 7px;
    letter-spacing: 2.2px;
    text-align: center;
    color: #fff7eb;
    font-weight: 800;
    background: radial-gradient(circle at 35% 30%, rgba(176,44,58,.92), rgba(121,24,36,.98) 68%);
    border: 1px solid rgba(201,168,106,.62);
    box-shadow: 0 8px 18px rgba(122,27,37,.24), inset 0 0 0 1px rgba(255,255,255,.12);
}

.page-number small {
    display: block;
    margin-bottom: .35mm;
    font-size: 4.8px;
    letter-spacing: 2.1px;
    text-transform: uppercase;
    color: rgba(255,247,235,.74);
    line-height: 1;
}

.page-number strong {
    display: block;
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 18px;
    line-height: 1;
}

.content {
    height: 230mm;
    display: grid;
    grid-template-rows: 72mm 68mm 76mm 7mm;
    gap: 3.5mm;
}

.section-heading {
    height: 10mm;
    display: flex;
    justify-content: space-between;
    align-items: end;
    border-bottom: 1px solid rgba(201,168,106,.45);
    padding-bottom: 1.3mm;
    margin-bottom: 2.5mm;
}

.section-heading h3 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 16px;
    color: #7D1F1F;
    text-transform: uppercase;
    letter-spacing: 1.1px;
    font-weight: 800;
}

.section-heading p {
    font-family: 'Jost', Arial, sans-serif;
    font-size: 7.2px;
    letter-spacing: 1.35px;
    text-transform: uppercase;
    color: #9a7440;
    font-weight: 800;
    padding: .9mm 2.2mm .75mm;
    border-radius: 999px;
    background: rgba(255,249,239,.86);
    border: 1px solid rgba(201,168,106,.30);
}

/* CARD */

.item-card {
    position: relative;
    overflow: hidden;
    border-radius: 3.5mm;
    background: #fffaf2;
    border: 1px solid rgba(201,168,106,.50);
    box-shadow: 0 9px 20px rgba(70,40,25,.14), inset 0 0 0 1px rgba(255,255,255,.45);
}

.item-card img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    filter: saturate(1.06) contrast(1.04) brightness(1.01);
}

.item-info {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    padding: 14mm 3.2mm 3mm;
    background: linear-gradient(
        to top,
        rgba(38,22,14,.94) 0%,
        rgba(38,22,14,.80) 48%,
        rgba(38,22,14,.08) 100%
    );
    color: #fff;
}

.item-info h4 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 12px;
    line-height: 1.05;
    text-transform: uppercase;
    color: #fff;
    margin-bottom: .8mm;
}

.item-desc {
    margin-bottom: 1mm;
    font-family: 'Jost', Arial, sans-serif;
    font-size: 7.4px;
    line-height: 1.26;
    color: rgba(255,255,255,.82);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.price {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 15px;
    font-weight: 900;
    color: #F1D28A;
    letter-spacing: .5px;
}

.mini-badge {
    display: inline-block;
    margin-bottom: 1mm;
    padding: 2px 8px;
    background: linear-gradient(135deg, #e6c986, #c79b49);
    color: #2a170b;
    font-family: 'Jost', Arial, sans-serif;
    font-size: 6.1px;
    font-weight: 900;
    letter-spacing: 1px;
    text-transform: uppercase;
    border-radius: 99px;
    box-shadow: 0 4px 10px rgba(169,116,39,.18);
}

/* LIST */

.menu-list {
    background: linear-gradient(180deg, rgba(255,255,255,.84), rgba(251,244,233,.95));
    border: 1px solid rgba(201,168,106,.42);
    border-radius: 3.2mm;
    padding: 3mm;
    display: grid;
    gap: 1.7mm;
    box-shadow: 0 7px 16px rgba(70,40,25,.10), inset 0 0 0 1px rgba(255,255,255,.35);
}

.list-item {
    display: grid;
    grid-template-columns: 1fr 15mm;
    gap: 3mm;
    border-bottom: 1px dashed rgba(106,78,58,.28);
    padding-bottom: 1.5mm;
}

.list-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.list-item h4 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 11.8px;
    color: #7D1F1F;
    text-transform: uppercase;
    letter-spacing: .4px;
    margin-bottom: .6mm;
    line-height: 1.04;
    font-weight: 800;
}

.list-item p {
    font-family: 'Jost', Arial, sans-serif;
    font-size: 7.8px;
    line-height: 1.32;
    color: #6A4E3A;
}

.list-price {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 15px;
    font-weight: 900;
    color: #7D1F1F;
    text-align: right;
    line-height: 1;
    align-self: center;
}

/* SECTION LAYOUTS */

.spicy-layout {
    display: grid;
    grid-template-columns: 1.1fr 1fr;
    gap: 3mm;
    height: 59mm;
}

.spicy-hero {
    height: 100%;
}

.spicy-list {
    height: 100%;
}

.rice-layout {
    display: grid;
    grid-template-columns: 1.15fr 1fr;
    gap: 3mm;
    height: 55mm;
}

.rice-hero {
    height: 100%;
}

.indomie-layout {
    display: grid;
    grid-template-columns: 1fr 1.15fr;
    gap: 3mm;
    height: 63mm;
}

.indomie-hero {
    height: 100%;
}

.note-row {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 5mm;
    font-family: 'Jost', Arial, sans-serif;
    font-size: 7.1px;
    letter-spacing: 1.35px;
    text-transform: uppercase;
    color: #7D1F1F;
    border-top: 1px solid rgba(125,31,31,.16);
    padding-top: 2mm;
    font-weight: 800;
}

.menu-footer {
    position: absolute;
    left: 9mm;
    right: 9mm;
    bottom: 6.5mm;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-family: 'Jost', Arial, sans-serif;
    font-size: 6.7px;
    letter-spacing: 1.7px;
    text-transform: uppercase;
    color: rgba(106,78,58,.86);
    border-top: 1px solid rgba(201,168,106,.35);
    padding-top: 2mm;
    font-weight: 800;
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

<section class="menu-page page-bowl-spice-noodles">

<header class="menu-header">
    <div class="menu-logo-wrap">
        <img src="<?= base_url('assets/menu-book/logo/logo.png') ?>" class="menu-logo" alt="NAMUA">
    </div>

    <div class="header-title-area">
        <span class="label-category">Food Collection</span>
        <h1 class="page-title">BOWL, SPICE &amp; NOODLES</h1>
        <h2>Spicy bites, rice bowls & comfort mi.</h2>
    </div>


</header>

<main class="content">

    <section>
        <div class="section-heading">
            <h3>FIRE &amp; GEPREK</h3>
            <p>Spicy chicken · level noodles</p>
        </div>

        <div class="spicy-layout">
            <article class="item-card spicy-hero">
                <img src="<?= base_url('assets/menu-book/products/foods/bowl-spice-noodles/geprek.png') ?>" alt="Mozza Melt">
                <div class="item-info">
                    <span class="mini-badge">Spicy Pick</span>
                    <h4>Geprek OG</h4>
                    <p class="item-desc">Crispy chicken, rice and fiery sambal bawang for the boldest comfort plate on this page.</p>
                    <div class="price">20K</div>
                </div>
            </article>

            <div class="menu-list spicy-list">
                <div class="list-item">
                    <div>
                        <h4>GEPREK OG</h4>
                        <p>Rice, chicken breast, mix flour, sambal bawang geprek, cucumber, tomato & lettuce.</p>
                    </div>
                    <div class="list-price">20K</div>
                </div>

                <div class="list-item">
                    <div>
                        <h4>MIE BARA I</h4>
                        <p>Spicy level noodles choice, minced chicken & wonton level 1.</p>
                    </div>
                    <div class="list-price">15K</div>
                </div>

                <div class="list-item">
                    <div>
                        <h4>MIE BARA II</h4>
                        <p>Spicy level noodles choice, minced chicken & wonton level 2.</p>
                    </div>
                    <div class="list-price">17K</div>
                </div>

                <div class="list-item">
                    <div>
                        <h4>MIE BARA III</h4>
                        <p>Spicy level noodles choice, minced chicken & wonton level 3.</p>
                    </div>
                    <div class="list-price">17K</div>
                </div>
            </div>
        </div>
    </section>

    <section>
        <div class="section-heading">
            <h3>DAILY RICE BOWLS</h3>
            <p>Comfort rice · crispy toppings</p>
        </div>

        <div class="rice-layout">
            <article class="item-card rice-hero">
                <img src="<?= base_url('assets/menu-book/products/foods/bowl-spice-noodles/bowl.png') ?>" alt="Golden Chicken Bowl">
                <div class="item-info">
                    <span class="mini-badge">Rice Bowl</span>
                    <h4>Canggu Dory Bowl</h4>
                    <p class="item-desc">Crispy dory, sunny egg and fresh salad in a bright everyday bowl.</p>
                    <div class="price">23K</div>
                </div>
            </article>

            <div class="menu-list">
                <div class="list-item">
                    <div>
                        <h4>CANGGU DORY BOWL</h4>
                        <p>Rice dory crispy, sunny side up egg, fresh salad & sambal matah.</p>
                    </div>
                    <div class="list-price">23K</div>
                </div>

                <div class="list-item">
                    <div>
                        <h4>CRISPY SKIN BOWL</h4>
                        <p>Rice, kulit crispy, sunny side up egg & sambal dabu-dabu.</p>
                    </div>
                    <div class="list-price">19K</div>
                </div>

                <div class="list-item">
                    <div>
                        <h4>EGG COMFORT BOWL</h4>
                        <p>Rice, egg, fresh salad & sambal bawang.</p>
                    </div>
                    <div class="list-price">16K</div>
                </div>
            </div>
        </div>
    </section>

    <section>
        <div class="section-heading">
            <h3>INDOMIE COMFORT</h3>
            <p>Anak kos core · warm noodle comfort</p>
        </div>

        <div class="indomie-layout">
            <div class="menu-list">
                <div class="list-item">
                    <div>
                        <h4>CHICKEN CRUNCH MI</h4>
                        <p>Indomie goreng, chicken breast, kulit pangsit & shallot chips.</p>
                    </div>
                    <div class="list-price">17K</div>
                </div>

                <div class="list-item">
                    <div>
                        <h4>CLASSIC FRIED MI</h4>
                        <p>Indomie goreng, telur & special seasoning.</p>
                    </div>
                    <div class="list-price">16K</div>
                </div>

                <div class="list-item">
                    <div>
                        <h4>WARM SOUP MI</h4>
                        <p>Indomie rebus, telur & special seasoning.</p>
                    </div>
                    <div class="list-price">15K</div>
                </div>

                <div class="list-item">
                    <div>
                        <h4>TOM YUM MI</h4>
                        <p>Indomie soto kuah, tofu, chikuwa, jamur kuping & tomyum paste.</p>
                    </div>
                    <div class="list-price">18K</div>
                </div>
            </div>

            <article class="item-card indomie-hero">
                <img src="<?= base_url('assets/menu-book/products/foods/bowl-spice-noodles/indomie.png') ?>" alt="Chicken Crunch Mi">
                <div class="item-info">
                    <span class="mini-badge">Comfort Mi</span>
                    <h4>Tom Yum Mi</h4>
                    <p class="item-desc">Warm instant noodle comfort with tofu, chikuwa, mushroom and a tangy tom yum lift.</p>
                    <div class="price">18K</div>
                </div>
            </article>
        </div>
    </section>

    <div class="note-row">
        <span>Comfort Food</span>
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
