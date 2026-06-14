<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Flame & Flavor — NAMUA Coffee & Eatery</title>

    <style>
        @page { size: A4 portrait; margin: 0; }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background: #160d08;
            font-family: Georgia, 'Times New Roman', serif;
            color: #2b2119;
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
                linear-gradient(rgba(20,10,5,.32), rgba(20,10,5,.55)),
                url('/finance/assets/menu-book/backgrounds/bg-flame-flavor.png') center / cover no-repeat,
                #25140d;
        }

        .menu-page::before {
            content: '';
            position: absolute;
            inset: 5mm;
            border: 1px solid rgba(201,168,106,.30);
            pointer-events: none;
            z-index: 1;
        }

        .menu-page::after {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top left, rgba(201,168,106,.16), transparent 32%),
                radial-gradient(circle at bottom right, rgba(125,31,31,.32), transparent 42%);
            pointer-events: none;
            z-index: 0;
        }

        .menu-header,
        .content,
        .menu-footer {
            position: relative;
            z-index: 2;
        }

        .menu-header {
            height: 31mm;
            display: grid;
            grid-template-columns: 22mm 1fr 16mm;
            align-items: center;
            border-bottom: 1px solid rgba(201,168,106,.28);
            padding-bottom: 4mm;
            margin-bottom: 4mm;
        }

        .menu-logo {
            width: 15mm;
            height: 15mm;
            object-fit: contain;
            filter: drop-shadow(0 2px 8px rgba(0,0,0,.45));
        }

        .header-title-area { text-align: center; }

        .label-category {
            display: block;
            font-family: Arial, sans-serif;
            font-size: 6px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #D4B278;
            margin-bottom: 2mm;
        }

        .page-title {
            font-size: 26px;
            line-height: .95;
            color: #fff;
            letter-spacing: 1.7px;
            text-transform: uppercase;
            text-shadow: 0 2px 12px rgba(0,0,0,.55);
        }

        .page-title em {
            font-size: 18px;
            font-style: italic;
            color: #D4B278;
            padding: 0 1mm;
        }

        .header-title-area h2 {
            margin-top: 1.8mm;
            font-size: 10px;
            font-style: italic;
            font-weight: 400;
            color: rgba(255,255,255,.78);
            letter-spacing: .5px;
        }

        .page-number {
            font-family: Arial, sans-serif;
            font-size: 8px;
            letter-spacing: 3px;
            text-align: right;
            color: #D4B278;
            font-weight: 700;
        }

        .content {
            height: 229mm;
            display: grid;
            grid-template-rows: 70mm 71mm 1fr 7mm;
            gap: 3.5mm;
        }

        .hero-combo {
            display: grid;
            grid-template-columns: 48% 52%;
            overflow: hidden;
            border-radius: 3mm;
            border: 1px solid rgba(212,178,120,.42);
            background: rgba(18,8,3,.86);
            box-shadow: 0 8px 24px rgba(0,0,0,.38);
        }

        .hero-image {
            position: relative;
            overflow: hidden;
        }

        .hero-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .hero-image::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to right, transparent, rgba(18,8,3,.35));
        }

        .hero-info {
            padding: 5mm;
            color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .section-kicker {
            font-family: Arial, sans-serif;
            font-size: 6px;
            letter-spacing: 2px;
            color: #D4B278;
            text-transform: uppercase;
            margin-bottom: 2mm;
        }

        .hero-info h3 {
            font-size: 18px;
            line-height: 1;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 2mm;
        }

        .hero-info p {
            font-family: Arial, sans-serif;
            font-size: 7px;
            line-height: 1.45;
            color: rgba(255,255,255,.74);
            margin-bottom: 3mm;
        }

        .combo-list {
            display: grid;
            gap: 1.5mm;
        }

        .combo-item {
            border-top: 1px solid rgba(212,178,120,.24);
            padding-top: 1.5mm;
        }

        .combo-line {
            display: flex;
            justify-content: space-between;
            gap: 3mm;
            align-items: baseline;
        }

        .combo-line strong {
            font-size: 8.5px;
            letter-spacing: .5px;
            color: #fff;
        }

        .combo-line b {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #F2D28A;
        }

        .combo-item span {
            display: block;
            margin-top: .7mm;
            font-family: Arial, sans-serif;
            font-size: 5.8px;
            line-height: 1.25;
            color: rgba(255,255,255,.68);
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 3mm;
        }

        .menu-card {
            position: relative;
            overflow: hidden;
            border-radius: 3mm;
            background: #190b05;
            border: 1px solid rgba(212,178,120,.38);
            box-shadow: 0 5px 16px rgba(0,0,0,.32);
        }

        .menu-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            filter: saturate(1.04) contrast(1.03);
        }

        .menu-card-info {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            padding: 13mm 2.5mm 2.3mm;
            background: linear-gradient(
                to top,
                rgba(18,8,3,.95),
                rgba(18,8,3,.74) 48%,
                rgba(18,8,3,.06)
            );
            color: #fff;
        }

        .menu-card-info h4 {
            font-size: 7.8px;
            line-height: 1.05;
            letter-spacing: .35px;
            text-transform: uppercase;
            margin-bottom: .7mm;
            color: #fff;
        }

        .menu-desc {
            font-family: Arial, sans-serif;
            font-size: 5.5px;
            line-height: 1.22;
            color: rgba(255,255,255,.76);
            margin-bottom: .8mm;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .price {
            font-family: Arial, sans-serif;
            font-size: 10px;
            font-weight: 900;
            color: #F2D28A;
            letter-spacing: .8px;
        }

        .pasta-section {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2.5mm;
        }

        .section-title-row {
            display: flex;
            justify-content: space-between;
            align-items: end;
            border-bottom: 1px solid rgba(212,178,120,.35);
            padding-bottom: 1.5mm;
        }

        .section-title-row h3 {
            color: #F2D28A;
            font-size: 12px;
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }

        .section-title-row p {
            font-family: Arial, sans-serif;
            font-size: 6.5px;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: rgba(255,255,255,.68);
        }

        .pasta-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2.5mm;
        }

        .pasta-card {
            height: 45mm;
        }

        .pasta-card .menu-card-info {
            padding: 12mm 2.2mm 2mm;
        }

        .pasta-card .menu-card-info h4 {
            font-size: 6.8px;
        }

        .pasta-card .menu-desc {
            font-size: 5.2px;
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
            color: rgba(255,255,255,.78);
            border-top: 1px solid rgba(212,178,120,.24);
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
            color: rgba(255,255,255,.70);
            border-top: 1px solid rgba(212,178,120,.28);
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

<section class="menu-page page-flame-flavor">

    <header class="menu-header">
        <div>
            <img src="<?= base_url('assets/menu-book/logo/logo.png') ?>" class="menu-logo" alt="NAMUA">
        </div>

        <div class="header-title-area">
            <span class="label-category">Food Collection</span>
            <h1 class="page-title">
                <span>FLAME</span>
                <em>&amp;</em>
                <span>FLAVOR</span>
            </h1>
            <h2>Western classics, steaks, burgers & pasta.</h2>
        </div>

        <div class="page-number">04</div>
    </header>

    <main class="content">

        <section class="hero-combo">
            <div class="hero-image">
                <img src="<?= base_url('assets/menu-book/products/foods/flame-flavor/tray-combo.png') ?>" alt="Steak, brisket and fried chicken tray">
            </div>

            <div class="hero-info">
                <div class="section-kicker">Meat & Grill Set</div>
                <h3>Premium Tray Selection</h3>
                <p>Served with hand cut fries, mac n cheese, cole slaw and barbeque sauce.</p>

                <div class="combo-list">

                    <div class="combo-item">
                        <div class="combo-line">
                            <strong>DRY-RUB PEPPER STEAK</strong>
                            <b>65K</b>
                        </div>
                        <span>Sirloin meltique, hand cut fries, mac n cheese, cole slaw & BBQ sauce.</span>
                    </div>

                    <div class="combo-item">
                        <div class="combo-line">
                            <strong>US SMOKED BRISKET</strong>
                            <b>50K</b>
                        </div>
                        <span>Beef shortplate, hand cut fries, mac n cheese, cole slaw & BBQ sauce.</span>
                    </div>

                    <div class="combo-item">
                        <div class="combo-line">
                            <strong>SOUTHERN STYLE FRIED CHICKEN</strong>
                            <b>40K</b>
                        </div>
                        <span>Chicken breast, mix flour, hand cut fries, mac n cheese, cole slaw & BBQ sauce.</span>
                    </div>

                </div>
            </div>
        </section>

<section class="feature-grid">

    <article class="menu-card">
        <img src="<?= base_url('assets/menu-book/products/foods/flame-flavor/namua-signature-burger.png') ?>" alt="Namua Signature Burger">
        <div class="menu-card-info">
            <h4>NAMUA SIGNATURE BURGER</h4>
            <div class="menu-desc">Beef patty with original french fries, chili sauce & tomato sauce.</div>
            <div class="price">30K</div>
        </div>
    </article>

    <article class="menu-card">
        <img src="<?= base_url('assets/menu-book/products/foods/flame-flavor/namua-crunch-burger.png') ?>" alt="Namua Crunch Burger">
        <div class="menu-card-info">
            <h4>NAMUA CRUNCH BURGER</h4>
            <div class="menu-desc">Chicken katsu with original french fries, chili sauce & tomato sauce.</div>
            <div class="price">28K</div>
        </div>
    </article>

    <article class="menu-card">
        <img src="<?= base_url('assets/menu-book/products/foods/flame-flavor/chicken-cordon-bleu.png') ?>" alt="Chicken Cordon Bleu">
        <div class="menu-card-info">
            <h4>CHICKEN CORDON BLEU</h4>
            <div class="menu-desc">Chicken breast roll filled with smoked beef & mozzarella cheese.</div>
            <div class="price">32K</div>
        </div>
    </article>

    <article class="menu-card">
        <img src="<?= base_url('assets/menu-book/products/foods/flame-flavor/fish-and-chips.png') ?>" alt="Fish and Chips">
        <div class="menu-card-info">
            <h4>FISH &amp; CHIPS</h4>
            <div class="menu-desc">Deep fry dory fish, hand cut fries & tar-tar mayo.</div>
            <div class="price">28K</div>
        </div>
    </article>

    <article class="menu-card steak-wide">
        <img src="<?= base_url('assets/menu-book/products/foods/flame-flavor/sirloin-steak-meltique.png') ?>" alt="Sirloin Steak Meltique">
        <div class="menu-card-info">
            <h4>SIRLOIN STEAK MELTIQUE</h4>
            <div class="menu-desc">Sirloin meltique blue label, hand cut fries, salad & barbeque sauce.</div>
            <div class="price">90K</div>
        </div>
    </article>

</section>
        <section class="pasta-section">
            <div class="section-title-row">
                <h3>Pasta Selection</h3>
                <p>Comfort pasta with NAMUA touch</p>
            </div>

            <div class="pasta-grid">

                <article class="menu-card pasta-card">
                    <img src="<?= base_url('assets/menu-book/products/foods/flame-flavor/spaghetti-aglio-e-olio-con-pollo.png') ?>" alt="Spaghetti Aglio e Olio con Pollo">
                    <div class="menu-card-info">
                        <h4>SPAGHETTI AGLIO E OLIO CON POLLO</h4>
                        <div class="menu-desc">Garlic & paprika aromatic, deep fry chicken.</div>
                        <div class="price">25K</div>
                    </div>
                </article>

                <article class="menu-card pasta-card">
                    <img src="<?= base_url('assets/menu-book/products/foods/flame-flavor/spaghetti-alfredo-di-crema.png') ?>" alt="Spaghetti Alfredo di Crema">
                    <div class="menu-card-info">
                        <h4>SPAGHETTI ALFREDO DI CREMA</h4>
                        <div class="menu-desc">Creamy & cheese base with smoked beef.</div>
                        <div class="price">32K</div>
                    </div>
                </article>

                <article class="menu-card pasta-card">
                    <img src="<?= base_url('assets/menu-book/products/foods/flame-flavor/spaghetti-bolognese.png') ?>" alt="Spaghetti Bolognese">
                    <div class="menu-card-info">
                        <h4>SPAGHETTI BOLOGNESE</h4>
                        <div class="menu-desc">Mix beef ragu bolognese & cheese on top.</div>
                        <div class="price">28K</div>
                    </div>
                </article>

                <article class="menu-card pasta-card">
                    <img src="<?= base_url('assets/menu-book/products/foods/flame-flavor/spageti dory.png') ?>" alt="Spaghetti Dory Balinese">
                    <div class="menu-card-info">
                        <h4>SPAGHETTI DORY BALINESE</h4>
                        <div class="menu-desc">Chilli flakes, garlic, white pepper, dory, kemangi & garlic chips.</div>
                        <div class="price">25K</div>
                    </div>
                </article>

            </div>
        </section>

        <div class="note-row">
            <span>All prices are in K</span>
            <span>Western Classics</span>
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