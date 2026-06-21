<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Extras & Condiments — NAMUA Coffee & Eatery</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Jost:wght@500;600;700;800;900&family=Playfair+Display:wght@600;700;800;900&family=Pinyon+Script&display=swap" rel="stylesheet">

<style>
@page { size: A4 portrait; margin: 0; }

* { box-sizing: border-box; margin: 0; padding: 0; }

html, body {
    background: #21160f;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

body { font-family: 'Jost', Arial, sans-serif; }

.menu-page {
    width: 210mm;
    height: 297mm;
    margin: 0 auto;
    position: relative;
    overflow: hidden;
    padding: 7mm;
    background:
        radial-gradient(circle at 12% 10%, rgba(201,162,78,.20), transparent 30%),
        radial-gradient(circle at 88% 86%, rgba(168,35,44,.10), transparent 35%),
        linear-gradient(135deg, rgba(255,251,242,.96), rgba(246,239,225,.98)),
        url('<?= base_url('assets/menu-book/backgrounds/bg-asian.png') ?>') center / cover no-repeat,
        #F6EFE1;
}

.menu-page::before {
    content: "";
    position: absolute;
    inset: 4.5mm;
    border: 1.3px solid rgba(201,162,78,.78);
    pointer-events: none;
}

.menu-page::after {
    content: "";
    position: absolute;
    inset: 6.3mm;
    border: .8px solid rgba(168,35,44,.38);
    pointer-events: none;
}

.corner {
    position: absolute;
    width: 10mm;
    height: 10mm;
    border-color: #C9A24E;
    opacity: .78;
}

.corner.tl { top: 4.5mm; left: 4.5mm; border-top: 1.3px solid; border-left: 1.3px solid; }
.corner.tr { top: 4.5mm; right: 4.5mm; border-top: 1.3px solid; border-right: 1.3px solid; }
.corner.bl { bottom: 4.5mm; left: 4.5mm; border-bottom: 1.3px solid; border-left: 1.3px solid; }
.corner.br { bottom: 4.5mm; right: 4.5mm; border-bottom: 1.3px solid; border-right: 1.3px solid; }

.circle-pattern {
    position: absolute;
    right: -20mm;
    top: 17mm;
    width: 72mm;
    height: 72mm;
    border-radius: 50%;
    border: 1px solid rgba(201,162,78,.18);
}

.circle-pattern::before,
.circle-pattern::after {
    content: "";
    position: absolute;
    border-radius: 50%;
    border: 1px solid rgba(201,162,78,.14);
}

.circle-pattern::before { inset: 8mm; }
.circle-pattern::after { inset: 16mm; }

.wave-pattern {
    position: absolute;
    right: -10mm;
    bottom: 25mm;
    width: 92mm;
    height: 48mm;
    opacity: .10;
    background:
        repeating-radial-gradient(
            ellipse at bottom right,
            transparent 0,
            transparent 4mm,
            #C9A24E 4.2mm,
            #C9A24E 4.45mm
        );
}

.page-inner {
    position: relative;
    z-index: 2;
    height: 100%;
    display: grid;
    grid-template-rows: 50mm 1fr 7mm;
    gap: 4mm;
}

/* HEADER */
.header {
    position: relative;
    display: grid;
    grid-template-columns: 32mm 1fr 24mm;
    align-items: start;
    text-align: center;
    border-bottom: 1px solid rgba(201,162,78,.55);
    padding-top: 3.5mm;
}

.logo-stamp {
    width: 27mm;
    height: 27mm;
    border-radius: 50%;
    border: 1.2px solid rgba(201,162,78,.75);
    background: rgba(255,253,248,.58);
    display: grid;
    place-items: center;
}

.logo-stamp img {
    width: 23mm;
    height: 23mm;
    object-fit: contain;
}

.title-area .script {
    display: block;
    font-family: 'Pinyon Script', cursive;
    font-size: 30px;
    line-height: .75;
    color: #C9A24E;
}

.title-area h1 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 38px;
    line-height: .9;
    letter-spacing: 4px;
    color: #A8232C;
    text-transform: uppercase;
    font-weight: 900;
}

.title-area p {
    margin-top: 2.2mm;
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 11.8px;
    font-style: italic;
    color: #3c2b21;
}

.title-divider {
    margin: 5.5mm auto 0;
    width: 62mm;
    height: 1px;
    background: #C9A24E;
    position: relative;
}

.title-divider::after {
    content: "";
    position: absolute;
    left: 50%;
    top: -2mm;
    transform: translateX(-50%) rotate(45deg);
    width: 3.6mm;
    height: 3.6mm;
    border: 1px solid #C9A24E;
    background: #F6EFE1;
}

.no-photo {
    justify-self: end;
    width: 19mm;
    height: 19mm;
    border-radius: 50%;
    border: 1px solid #C9A24E;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #A8232C;
    font-size: 5.8px;
    letter-spacing: 1.7px;
    line-height: 1.35;
    font-weight: 900;
    text-transform: uppercase;
}

/* CONTENT */
.content {
    display: grid;
    grid-template-rows: 74mm 128mm;
    gap: 6mm;
    min-height: 0;
}

.top-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 5mm;
}

.card {
    position: relative;
    overflow: hidden;
    border-radius: 4.5mm;
    background: rgba(255,250,240,.88);
    border: 1px solid rgba(201,162,78,.72);
    box-shadow: 0 8px 18px rgba(75,42,20,.09);
    padding: 7mm 6mm 5.5mm;
}

.card::after {
    content: "";
    position: absolute;
    right: -9mm;
    bottom: -11mm;
    width: 50mm;
    height: 40mm;
    opacity: .10;
    background:
        repeating-radial-gradient(
            ellipse at bottom right,
            transparent 0,
            transparent 3mm,
            #C9A24E 3.15mm,
            #C9A24E 3.35mm
        );
}

.card-head {
    position: relative;
    z-index: 3;
    display: grid;
    grid-template-columns: 17mm 1fr;
    gap: 4mm;
    align-items: center;
    margin-bottom: 6mm;
}

.icon {
    width: 15.5mm;
    height: 15.5mm;
    border-radius: 50%;
    background: #A8232C;
    border: 2px solid #F6EFE1;
    outline: 1px solid #C9A24E;
    display: grid;
    place-items: center;
    color: #F1D28A;
    font-size: 15px;
    font-weight: 900;
}

.card-title h2 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 17px;
    line-height: 1;
    color: #A8232C;
    text-transform: uppercase;
    letter-spacing: .6px;
}

.card-title span {
    display: block;
    margin-top: 1.5mm;
    color: #B68C39;
    font-size: 7.8px;
    letter-spacing: 2.6px;
    text-transform: uppercase;
    font-weight: 900;
}

.small-line {
    margin-top: 2.5mm;
    width: 48mm;
    height: 1px;
    background: #C9A24E;
    position: relative;
}

.small-line::after {
    content: "";
    position: absolute;
    left: 50%;
    top: -1.25mm;
    transform: translateX(-50%) rotate(45deg);
    width: 2.4mm;
    height: 2.4mm;
    border: 1px solid #C9A24E;
    background: #F6EFE1;
}

/* MENU ROW */
.menu-list {
    position: relative;
    z-index: 3;
    display: grid;
    gap: 4.2mm;
}

.menu-row {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: baseline;
    gap: 2.5mm;
}

.menu-row h3 {
    font-size: 12px;
    line-height: 1;
    color: #241813;
    text-transform: uppercase;
    letter-spacing: .2px;
    font-weight: 900;
    white-space: nowrap;
}

.dotline {
    border-bottom: 1px dotted rgba(36,24,19,.55);
    transform: translateY(-1.4mm);
}

.menu-row b {
    font-size: 15.5px;
    color: #A8232C;
    font-weight: 900;
    white-space: nowrap;
}

/* BOTTOM */
.bottom-card {
    padding: 6.5mm;
}

.bottom-grid {
    display: grid;
    grid-template-columns: 1.1fr .9fr;
    gap: 7mm;
    position: relative;
    z-index: 3;
}

.bottom-grid::before {
    content: "";
    position: absolute;
    top: 7mm;
    bottom: 2mm;
    left: calc(53% + 1mm);
    border-left: 1px dashed rgba(201,162,78,.55);
}

.list-block .card-head {
    margin-bottom: 4.5mm;
}

.sauce-list {
    display: grid;
    gap: 2.8mm;
}

.condiment-list {
    display: grid;
    gap: 5mm;
}

.bottom-card .menu-row h3 {
    font-size: 10.8px;
}

.bottom-card .menu-row b {
    font-size: 13.8px;
}

.bottom-card .icon {
    width: 14.5mm;
    height: 14.5mm;
    font-size: 14px;
}

.bottom-card .card-title h2 {
    font-size: 16.5px;
}

.bottom-card .card-title span {
    font-size: 7.3px;
    letter-spacing: 2.3px;
}

.perfect-strip {
    position: relative;
    z-index: 3;
    margin: 5.5mm auto 0;
    width: 112mm;
    height: 10mm;
    border: 1px solid #C9A24E;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #A8232C;
    font-size: 6.7px;
    letter-spacing: 2.8px;
    font-weight: 900;
    text-transform: uppercase;
    background: rgba(255,250,240,.74);
}

/* FOOTER */
.footer {
    border-top: 1px solid rgba(201,162,78,.60);
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    padding-top: 1.3mm;
    font-size: 6.3px;
    letter-spacing: 1.8px;
    color: #8B6A32;
    text-transform: uppercase;
    font-weight: 800;
}

.footer span:nth-child(2) { color: #B68C39; }
.footer span:last-child { text-align: right; }

@media print {
    html, body { background: transparent; }
    .menu-page { margin: 0; page-break-after: always; }
}
</style>
</head>

<body>

<section class="menu-page">

<div class="corner tl"></div>
<div class="corner tr"></div>
<div class="corner bl"></div>
<div class="corner br"></div>
<div class="circle-pattern"></div>
<div class="wave-pattern"></div>

<div class="page-inner">

<header class="header">
    <div class="logo-stamp">
        <img src="<?= base_url('assets/menu-book/logo/logo.png') ?>" alt="NAMUA">
    </div>

    <div class="title-area">
        <span class="script">Extras &amp;</span>
        <h1>Condiments</h1>
        <p>Additional carbo, upgrades, sauces &amp; side condiments.</p>
        <div class="title-divider"></div>
    </div>

</header>

<main class="content">

<div class="top-grid">

    <section class="card">
        <div class="card-head">
            <div class="icon">●</div>
            <div class="card-title">
                <h2>Additional Carbo</h2>
                <span>Rice Selection</span>
                <div class="small-line"></div>
            </div>
        </div>

        <div class="menu-list">
            <div class="menu-row"><h3>Arabian Rice</h3><div class="dotline"></div><b>15K</b></div>
            <div class="menu-row"><h3>Base Gede Rice</h3><div class="dotline"></div><b>11K</b></div>
            <div class="menu-row"><h3>Garlic Kemangi Rice</h3><div class="dotline"></div><b>9K</b></div>
            <div class="menu-row"><h3>Steam Rice</h3><div class="dotline"></div><b>6K</b></div>
            <div class="menu-row"><h3>Yakimeshi Rice</h3><div class="dotline"></div><b>16K</b></div>
        </div>
    </section>

    <section class="card">
        <div class="card-head">
            <div class="icon">↑</div>
            <div class="card-title">
                <h2>Upgrade Carbo</h2>
                <span>Rice Upgrade</span>
                <div class="small-line"></div>
            </div>
        </div>

        <div class="menu-list">
            <div class="menu-row"><h3>Arabian Rice</h3><div class="dotline"></div><b>9K</b></div>
            <div class="menu-row"><h3>Base Gede Rice</h3><div class="dotline"></div><b>5K</b></div>
            <div class="menu-row"><h3>Garlic Kemangi Rice</h3><div class="dotline"></div><b>9K</b></div>
            <div class="menu-row"><h3>Yakimeshi Rice</h3><div class="dotline"></div><b>10K</b></div>
        </div>
    </section>

</div>

<section class="card bottom-card">

    <div class="bottom-grid">

        <div class="list-block">
            <div class="card-head">
                <div class="icon">□</div>
                <div class="card-title">
                    <h2>Sauce, Sambal &amp; Mayo</h2>
                    <span>Sauce · Sambal · Condiment</span>
                    <div class="small-line"></div>
                </div>
            </div>

            <div class="sauce-list">
                <div class="menu-row"><h3>Barbeque Sauce</h3><div class="dotline"></div><b>7K</b></div>
                <div class="menu-row"><h3>Cheese Sauce</h3><div class="dotline"></div><b>8K</b></div>
                <div class="menu-row"><h3>Sambal Bawang Geprek</h3><div class="dotline"></div><b>5K</b></div>
                <div class="menu-row"><h3>Sambal Dabu-Dabu</h3><div class="dotline"></div><b>5K</b></div>
                <div class="menu-row"><h3>Sambal Idjo</h3><div class="dotline"></div><b>5K</b></div>
                <div class="menu-row"><h3>Sambal Kecap</h3><div class="dotline"></div><b>7K</b></div>
                <div class="menu-row"><h3>Sambal Matah</h3><div class="dotline"></div><b>6K</b></div>
                <div class="menu-row"><h3>Sauce Bangkok</h3><div class="dotline"></div><b>5K</b></div>
                <div class="menu-row"><h3>Tar-Tar Mayo</h3><div class="dotline"></div><b>7K</b></div>
                <div class="menu-row"><h3>Teriyaki Sauce</h3><div class="dotline"></div><b>6K</b></div>
            </div>
        </div>

        <div class="list-block">
            <div class="card-head">
                <div class="icon">●</div>
                <div class="card-title">
                    <h2>Other Condiment</h2>
                    <span>Side Condiment</span>
                    <div class="small-line"></div>
                </div>
            </div>

            <div class="condiment-list">
                <div class="menu-row"><h3>Additional Fried Wonton</h3><div class="dotline"></div><b>5K</b></div>
                <div class="menu-row"><h3>Crackers</h3><div class="dotline"></div><b>5K</b></div>
                <div class="menu-row"><h3>Emping Crackers</h3><div class="dotline"></div><b>5K</b></div>
                <div class="menu-row"><h3>Telor</h3><div class="dotline"></div><b>5K</b></div>
            </div>
        </div>

    </div>

    <div class="perfect-strip">Perfect add-ons to complete your meal</div>

</section>

</main>

<footer class="footer">
    <span>NAMUA Coffee &amp; Eatery</span>
    <span>Extras &amp; Condiments</span>
    <span>#kembalikenamua</span>
</footer>

</div>
</section>

</body>
</html>