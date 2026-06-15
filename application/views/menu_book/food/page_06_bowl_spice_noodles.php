<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
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
        linear-gradient(rgba(248,241,232,.88), rgba(248,241,232,.95)),
        url('/finance/assets/menu-book/backgrounds/bg-bowl-spice-noodles.png') center / cover no-repeat,
        #f8f1e8;
}

.menu-page::before {
    content: '';
    position: absolute;
    inset: 5mm;
    border: 1px solid rgba(201,168,106,.28);
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
    border-bottom: 1px solid rgba(125,31,31,.16);
    padding-bottom: 4mm;
    margin-bottom: 4mm;
}

.menu-logo {
    width: 15mm;
    height: 15mm;
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
    margin-bottom: 2mm;
}

.page-title {
    font-size: 24px;
    line-height: .95;
    color: #7D1F1F;
    letter-spacing: 1.2px;
    text-transform: uppercase;
}

.header-title-area h2 {
    margin-top: 1.6mm;
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

/* CARD */

.item-card {
    position: relative;
    overflow: hidden;
    border-radius: 3mm;
    background: #fffaf2;
    border: 1px solid rgba(201,168,106,.42);
    box-shadow: 0 4px 13px rgba(70,40,25,.12);
}

.item-card img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    filter: saturate(1.05) contrast(1.03);
}

.item-info {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    padding: 13mm 3mm 2.5mm;
    background: linear-gradient(
        to top,
        rgba(38,22,14,.94) 0%,
        rgba(38,22,14,.80) 48%,
        rgba(38,22,14,.08) 100%
    );
    color: #fff;
}

.item-info h4 {
    font-size: 10px;
    line-height: 1.05;
    text-transform: uppercase;
    color: #fff;
    margin-bottom: .8mm;
}

.item-desc {
    margin-bottom: 1mm;
    font-family: Arial, sans-serif;
    font-size: 6.8px;
    line-height: 1.2;
    color: rgba(255,255,255,.82);
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

/* LIST */

.menu-list {
    background: rgba(255,255,255,.78);
    border: 1px solid rgba(201,168,106,.38);
    border-radius: 3mm;
    padding: 3mm;
    display: grid;
    gap: 1.7mm;
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
    font-size: 10.5px;
    color: #7D1F1F;
    text-transform: uppercase;
    letter-spacing: .4px;
    margin-bottom: .6mm;
}

.list-item p {
    font-family: Arial, sans-serif;
    font-size: 7px;
    line-height: 1.25;
    color: #6A4E3A;
}

.list-price {
    font-family: Arial, sans-serif;
    font-size: 12px;
    font-weight: 900;
    color: #7D1F1F;
    text-align: right;
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

<section class="menu-page page-bowl-spice-noodles">

<header class="menu-header">
    <div>
        <img src="<?= base_url('assets/menu-book/logo/logo.png') ?>" class="menu-logo" alt="NAMUA">
    </div>

    <div class="header-title-area">
        <span class="label-category">Food Collection</span>
        <h1 class="page-title">BOWL, SPICE &amp; NOODLES</h1>
        <h2>Spicy bites, rice bowls & comfort mi.</h2>
    </div>

    <div class="page-number">06</div>
</header>

<main class="content">

    <section>
        <div class="section-heading">
            <h3>FIRE &amp; GEPREK</h3>
            <p>Spicy chicken · level noodles</p>
        </div>

        <div class="spicy-layout">
            <article class="item-card spicy-hero">
                <img src="<?= base_url('assets/menu-book/products/foods/bowl-spice-noodles/mozza-melt.png') ?>" alt="Mozza Melt">
                <div class="item-info">
                    <span class="mini-badge">Spicy Pick</span>
                    <h4>MOZZA MELT</h4>
                    <div class="item-desc">Rice, chicken breast, bread crumb, sambal bawang geprek, cucumber, tomato & lettuce.</div>
                    <div class="price">24K</div>
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
                <img src="<?= base_url('assets/menu-book/products/foods/bowl-spice-noodles/golden-chicken-bowl.png') ?>" alt="Golden Chicken Bowl">
                <div class="item-info">
                    <span class="mini-badge">Rice Bowl</span>
                    <h4>GOLDEN CHICKEN BOWL</h4>
                    <div class="item-desc">Rice crispy chicken, fresh salad, sunny side up egg & sambal matah.</div>
                    <div class="price">22K</div>
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
                <img src="<?= base_url('assets/menu-book/products/foods/bowl-spice-noodles/chicken-crunch-mi.png') ?>" alt="Chicken Crunch Mi">
                <div class="item-info">
                    <span class="mini-badge">Comfort Mi</span>
                    <h4>CHICKEN CRUNCH MI</h4>
                    <div class="item-desc">Indomie goreng, chicken breast, kulit pangsit & shallot chips.</div>
                    <div class="price">17K</div>
                </div>
            </article>
        </div>
    </section>

    <div class="note-row">
        <span>All prices are in K</span>
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