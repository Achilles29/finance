<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Nusantara &amp; Comfort Food — NAMUA Coffee &amp; Eatery</title>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;0,700;0,800;1,500;1,600&family=Jost:wght@300;400;500;600;700&family=Pinyon+Script&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
<style>
  @page { size: A4 portrait; margin: 0; }
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}

  :root{
    --ivory:#F6EFE1; --cream-2:#E6D7BC;
    --red:#A8232C; --red-d:#7C1820; --red-2:#BE2D33;
    --gold:#B68C39; --gold-2:#C9A24E; --gold-3:#E6CF94; --gold-4:#F0DEAE;
    --ink:#2B221C; --ink-soft:#6A5A4B;
    --card:#140A06;
  }

  body{
    background:#221511;
    font-family:'Jost','Helvetica Neue',Arial,sans-serif;
    color:var(--ink);
    -webkit-print-color-adjust:exact; print-color-adjust:exact;
    display:flex; justify-content:center; padding:24px;
    -webkit-font-smoothing:antialiased;
  }

  /* ── Page shell ── */
  .menu-page{
    width:210mm; height:297mm; position:relative; overflow:hidden;
    padding:11mm 12mm 9mm;
    background:var(--ivory);
  }
  .menu-page .bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;}
  .menu-page .wash{position:absolute;inset:0;background:
    linear-gradient(rgba(246,239,225,.50),rgba(246,239,225,.58)),
    radial-gradient(120% 55% at 50% 4%, rgba(246,239,225,.34) 0%, transparent 62%),
    linear-gradient(to bottom, transparent 74%, rgba(246,239,225,.40) 100%);}

  /* gold double keyline around whole page */
  .pageframe{position:absolute;inset:7mm;border:1px solid var(--gold-2);pointer-events:none;z-index:2;}
  .pageframe::before{content:"";position:absolute;inset:1.4mm;border:1px solid rgba(182,140,57,.38);}
  .ptick{position:absolute;width:4mm;height:4mm;border:1.4px solid var(--red);z-index:3;}
  .ptick.tl{top:5mm;left:5mm;border-right:0;border-bottom:0;}
  .ptick.tr{top:5mm;right:5mm;border-left:0;border-bottom:0;}
  .ptick.bl{bottom:5mm;left:5mm;border-right:0;border-top:0;}
  .ptick.br{bottom:5mm;right:5mm;border-left:0;border-top:0;}

  .inner{position:relative;z-index:2;height:100%;display:flex;flex-direction:column;}

  /* ── Header ── */
  .menu-header{
    display:grid; grid-template-columns:34mm 1fr 34mm; align-items:center;
    padding:0 2mm 4.5mm; border-bottom:1px solid rgba(182,140,57,.42); margin-bottom:4.5mm;
  }
  .header-logo-area{display:flex;align-items:center;}
  .menu-logo{height:24mm;width:auto;object-fit:contain;display:block;
    filter:drop-shadow(0 2px 6px rgba(124,24,32,.18));}

  .header-title-area{text-align:center;padding:0 2mm;}
  .label-category{display:flex;align-items:center;justify-content:center;gap:3mm;
    font-size:8px;letter-spacing:.34em;text-transform:uppercase;color:var(--gold);font-weight:600;
    margin-bottom:2mm;padding-left:.34em;}
  .label-category::before,.label-category::after{content:'';width:9mm;height:1px;background:rgba(182,140,57,.6);flex-shrink:0;}

  .header-title-area h1{line-height:1;}
  .h1-sub{display:block;font-family:'Pinyon Script',cursive;font-size:27px;font-weight:400;
    color:var(--gold-2);letter-spacing:.02em;margin-bottom:-2mm;text-transform:none;}
  .h1-main{display:block;font-family:'Playfair Display',Georgia,serif;font-size:35px;font-weight:700;
    letter-spacing:.07em;color:var(--red);line-height:.98;text-indent:.07em;}

  .title-ornament{display:flex;align-items:center;justify-content:center;gap:2.5mm;margin:2.2mm auto 1.6mm;width:74mm;}
  .title-ornament::before,.title-ornament::after{content:'';flex:1;height:1px;}
  .title-ornament::before{background:linear-gradient(to right,transparent,var(--gold-2));}
  .title-ornament::after{background:linear-gradient(to left,transparent,var(--gold-2));}
  .title-ornament span{color:var(--gold);width:5px;height:5px;border:1.2px solid var(--gold);
    display:inline-block;transform:rotate(45deg);}
  .title-ornament span:nth-child(2){background:var(--gold);}

  .header-title-area h2{font-family:'Playfair Display',Georgia,serif;font-size:11px;font-weight:400;
    font-style:italic;color:var(--ink-soft);letter-spacing:.13em;}

  .header-page-area{display:flex;flex-direction:column;justify-content:center;align-items:flex-end;height:100%;gap:1mm;}
  .page-number{font-family:'Playfair Display',serif;font-size:22px;color:var(--red);font-weight:600;line-height:1;}
  .page-number-lab{font-size:7px;letter-spacing:.3em;text-transform:uppercase;color:var(--gold);font-weight:600;}

  /* ── Showcase column ── */
  .showcase{flex:1;display:flex;flex-direction:column;gap:4.5mm;min-height:0;}
  .food-section{display:flex;flex-direction:column;min-height:0;}
  .food-section.heritage{flex:6.2;}
  .food-section.crave{flex:7;}

  /* ── Section heading ── */
  .section-heading{display:flex;align-items:baseline;justify-content:space-between;gap:5mm;
    margin-bottom:3mm;padding-bottom:1.8mm;border-bottom:1px solid rgba(182,140,57,.40);flex:none;}
  .section-heading .sh-l{display:flex;align-items:baseline;gap:3.5mm;}
  .section-heading .sh-no{font-family:'Playfair Display',serif;font-style:italic;font-weight:600;
    font-size:13px;color:var(--gold-2);}
  .section-heading h3{font-family:'Playfair Display',Georgia,serif;font-size:16px;font-weight:700;
    letter-spacing:.05em;text-transform:uppercase;color:var(--red);white-space:nowrap;}
  .section-heading .sh-l{flex-shrink:0;}
  .section-heading p{font-family:'Playfair Display',serif;font-style:italic;font-size:10px;
    letter-spacing:.04em;color:var(--ink-soft);white-space:nowrap;}

  /* ── Galleries ── */
  .food-gallery{flex:1;display:grid;gap:3.5mm;min-height:0;}
  .heritage-gallery{grid-template-columns:repeat(3,1fr);grid-template-rows:1fr 1fr;}
  .crave-gallery{grid-template-columns:repeat(3,1fr);grid-template-rows:1fr 1fr 0.92fr;}

  /* ── Card base ── */
  .food-card{position:relative;overflow:hidden;border-radius:6px;background:var(--card);
    box-shadow:0 7px 20px rgba(70,30,15,.28), inset 0 0 0 1px rgba(230,207,148,.20);}
  .food-card .kl{position:absolute;inset:1.9mm;border:1px solid rgba(230,207,148,.40);
    border-radius:3px;pointer-events:none;z-index:3;}
  .ct{position:absolute;width:3mm;height:3mm;border:1.2px solid var(--gold-3);z-index:4;pointer-events:none;}
  .ct.tl{top:3mm;left:3mm;border-right:0;border-bottom:0;}
  .ct.tr{top:3mm;right:3mm;border-left:0;border-bottom:0;}
  .ct.bl{bottom:3mm;left:3mm;border-right:0;border-top:0;}
  .ct.br{bottom:3mm;right:3mm;border-left:0;border-top:0;}

  .food-card img{width:100%;height:100%;object-fit:cover;display:block;
    filter:saturate(1.04) contrast(1.02);}
  /* real product photo (server) layered over placeholder; falls back to plate if it 404s */
  .food-img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block;z-index:1;
    filter:saturate(1.04) contrast(1.02);}
  .crave-wide .food-img{object-position:center 42%;}

  /* ── Placeholder plate (drop real photo in) ── */
  .plate{position:absolute;inset:0;
    background:
      radial-gradient(85% 70% at 50% 30%, #3a2415 0%, #1d1009 55%, #120803 100%);}
  .plate::before{content:'';position:absolute;inset:0;opacity:.9;
    background-image:repeating-linear-gradient(135deg, rgba(230,207,148,.045) 0 1px, transparent 1px 9px);}
  .plate-tag{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
    width:78%;text-align:center;z-index:2;display:flex;flex-direction:column;align-items:center;gap:1.6mm;
    margin-top:-3mm;}
  .plate-tag .ring{width:9mm;height:9mm;border-radius:50%;border:1px solid rgba(230,207,148,.45);
    display:flex;align-items:center;justify-content:center;}
  .plate-tag .ring i{width:3.2mm;height:3.2mm;border:1px solid rgba(230,207,148,.6);transform:rotate(45deg);}
  .plate-tag .code{font-family:'JetBrains Mono',monospace;font-size:5.6px;letter-spacing:.28em;
    text-transform:uppercase;color:rgba(230,207,148,.62);}
  .plate-tag .pname{font-family:'JetBrains Mono',monospace;font-size:5px;letter-spacing:.12em;
    color:rgba(230,207,148,.40);max-width:34mm;line-height:1.4;}

  /* ── Info overlay ── */
  .food-info{position:absolute;left:0;right:0;bottom:0;z-index:5;
    padding:13mm 4mm 3.4mm;
    background:linear-gradient(to top,
      rgba(8,3,1,.94) 0%, rgba(8,3,1,.82) 38%, rgba(8,3,1,.42) 64%, rgba(8,3,1,.08) 86%, rgba(8,3,1,0) 100%);}
  .mini-badge{display:inline-flex;align-items:center;gap:3px;margin-bottom:1.4mm;
    padding:2px 7px;border-radius:2px;
    background:linear-gradient(135deg,var(--gold-3),var(--gold));color:#3A1206;
    font-size:6px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;
    box-shadow:0 1px 3px rgba(0,0,0,.3);}
  .mini-badge.dark{background:var(--red);color:var(--gold-4);}
  .food-info h4{font-family:'Playfair Display',Georgia,serif;color:#fff;font-weight:600;
    font-size:9.5px;line-height:1.08;letter-spacing:.01em;text-shadow:0 1px 4px rgba(0,0,0,.7);}
  .name-rule{width:11mm;height:1.4px;margin:1.4mm 0 1.4mm;
    background:linear-gradient(to right,var(--gold-3),transparent);}
  .food-desc{display:none;font-family:'Jost',sans-serif;color:rgba(236,222,196,.90);
    font-size:6.4px;line-height:1.42;font-weight:300;margin-bottom:1.6mm;max-width:96%;}

  /* price tokens */
  .price{display:flex;align-items:baseline;gap:3mm;margin-top:1.4mm;flex-wrap:wrap;}
  .pz{display:inline-flex;align-items:baseline;gap:2px;
    font-family:'Jost',sans-serif;font-size:6.5px;font-weight:500;letter-spacing:.1em;
    text-transform:uppercase;color:rgba(245,235,215,.66);white-space:nowrap;}
  .pz b{font-family:'Playfair Display',serif;font-size:13px;font-weight:700;
    letter-spacing:.01em;color:var(--gold-3);}
  .pz.solo b{font-size:15px;}

  /* ── Chef-pick feature (heritage first card) ── */
  .food-card.feature .plate-tag{margin-top:-5mm;}
  .food-card.feature .food-desc{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
  .food-card.feature h4{font-size:11px;}

  /* ── Wide hero (crave last) ── */
  .crave-wide{grid-column:span 3;}
  .crave-wide .plate{background:radial-gradient(60% 120% at 78% 50%, #3a2415 0%, #1d1009 58%, #120803 100%);}
  .crave-wide .food-info{padding:6mm 7mm 4.4mm;max-width:62%;
    background:linear-gradient(to right,
      rgba(8,3,1,.95) 0%, rgba(8,3,1,.86) 50%, rgba(8,3,1,.5) 80%, rgba(8,3,1,0) 100%);}
  .crave-wide h4{font-size:15px;}
  .crave-wide .name-rule{margin:2mm 0;}
  .crave-wide .food-desc{display:block;font-size:7.4px;max-width:90%;}
  .crave-wide .plate-tag{left:78%;width:34%;margin-top:0;}
  .crave-wide .pz b{font-size:15px;}

  /* ── Legend ── */
  .legend{flex:none;display:flex;justify-content:center;align-items:center;gap:8mm;margin-top:1mm;
    font-family:'Jost',sans-serif;font-size:8px;letter-spacing:.16em;text-transform:uppercase;
    color:var(--ink-soft);font-weight:500;}
  .legend b{color:var(--red-d);font-weight:700;}
  .legend .dot{width:3px;height:3px;border-radius:50%;background:var(--gold-2);}

  /* ── Footer ── */
  .menu-footer{flex:none;display:flex;justify-content:space-between;align-items:center;
    margin-top:4mm;padding-top:3mm;border-top:1px solid rgba(182,140,57,.42);}
  .menu-footer .l{font-size:8.5px;letter-spacing:.18em;text-transform:uppercase;color:var(--red-d);font-weight:600;}
  .menu-footer .c{font-size:9px;letter-spacing:.1em;color:var(--ink-soft);font-style:italic;font-family:'Playfair Display',serif;}
  .menu-footer .ig{display:inline-flex;align-items:center;gap:5px;font-size:9px;letter-spacing:.12em;
    color:var(--red-d);font-weight:600;}
  .menu-footer .ig svg{width:12px;height:12px;display:block;}

  @media print{
    body{background:transparent;padding:0;}
    .menu-page{box-shadow:none;}
  }
</style>
</head>
<body>

<section class="menu-page page-nusantara-comfort" data-screen-label="Nusantara & Comfort">
  <img class="bg" src="<?= base_url('assets/menu-book/backgrounds/bg-nusantara-comfort.png') ?>" alt="" onerror="this.onerror=null;this.src='assets/bg-spice.png'" />
  <div class="wash"></div>
  <div class="pageframe"></div>
  <span class="ptick tl"></span><span class="ptick tr"></span>
  <span class="ptick bl"></span><span class="ptick br"></span>

  <div class="inner">

    <header class="menu-header">
      <div class="header-logo-area">
        <img src="<?= base_url('assets/menu-book/logo/logo.png') ?>" class="menu-logo" alt="NAMUA" onerror="this.onerror=null;this.src='assets/logo-new.png'" />
      </div>
      <div class="header-title-area">
        <span class="label-category">Food Collection</span>
        <h1>
          <span class="h1-sub">Nusantara &amp;</span>
          <span class="h1-main">COMFORT FOOD</span>
        </h1>
        <div class="title-ornament"><span></span><span></span><span></span></div>
        <h2>Traditional recipes, reimagined for today</h2>
      </div>
      <div class="header-page-area">
        <div class="page-number">03</div>
        <div class="page-number-lab">Page</div>
      </div>
    </header>

    <div class="showcase">

      <!-- ── INDONESIAN HERITAGE ── -->
      <section class="food-section heritage">
        <div class="section-heading">
          <div class="sh-l">
            <span class="sh-no">I.</span>
            <h3>Indonesian Heritage</h3>
          </div>
          <p>Flavours from the archipelago</p>
        </div>

        <div class="food-gallery heritage-gallery">

          <article class="food-card feature">
            <div class="plate"><span class="plate-tag"><span class="ring"><i></i></span><span class="code">Sup Iga Rempah</span><span class="pname">photo · 4:5 plated</span></span></div>
            <img class="food-img" src="<?= base_url('assets/menu-book/products/foods/indonesian-heritage/sup-iga-rempah.png') ?>" alt="Sup Iga Rempah" onerror="this.remove()" />
            <span class="kl"></span><span class="ct tl"></span><span class="ct tr"></span><span class="ct bl"></span><span class="ct br"></span>
            <div class="food-info">
              <span class="mini-badge">✦ Chef Pick</span>
              <h4>Sup Iga Rempah</h4>
              <div class="name-rule"></div>
              <p class="food-desc">8-hour slow-cooked beef short ribs in a clear spiced broth, potato, carrot, emping &amp; sambal bawang.</p>
              <div class="price">
                <span class="pz">À&nbsp;la&nbsp;Carte <b>46K</b></span>
                <span class="pz">Rice&nbsp;Set <b>48K</b></span>
              </div>
            </div>
          </article>

          <article class="food-card">
            <div class="plate"><span class="plate-tag"><span class="ring"><i></i></span><span class="code">Iga Bakar Madu</span><span class="pname">photo · 4:5 plated</span></span></div>
            <img class="food-img" src="<?= base_url('assets/menu-book/products/foods/indonesian-heritage/iga-bakar-madu.png') ?>" alt="Iga Bakar Madu" onerror="this.remove()" />
            <span class="kl"></span><span class="ct tl"></span><span class="ct tr"></span><span class="ct bl"></span><span class="ct br"></span>
            <div class="food-info">
              <h4>Iga Bakar Madu</h4>
              <div class="price">
                <span class="pz">À <b>50K</b></span>
                <span class="pz">R <b>52K</b></span>
              </div>
            </div>
          </article>

          <article class="food-card">
            <div class="plate"><span class="plate-tag"><span class="ring"><i></i></span><span class="code">Mie Aceh</span><span class="pname">photo · 4:5 plated</span></span></div>
            <img class="food-img" src="<?= base_url('assets/menu-book/products/foods/indonesian-heritage/mie-aceh-beef-shortplate.png') ?>" alt="Mie Aceh Beef Shortplate" onerror="this.remove()" />
            <span class="kl"></span><span class="ct tl"></span><span class="ct tr"></span><span class="ct bl"></span><span class="ct br"></span>
            <div class="food-info">
              <h4>Mie Aceh Beef Shortplate</h4>
              <div class="price">
                <span class="pz solo">À&nbsp;la&nbsp;Carte <b>43K</b></span>
              </div>
            </div>
          </article>

          <article class="food-card">
            <div class="plate"><span class="plate-tag"><span class="ring"><i></i></span><span class="code">Soto Betawi</span><span class="pname">photo · 4:5 plated</span></span></div>
            <img class="food-img" src="<?= base_url('assets/menu-book/products/foods/indonesian-heritage/soto-betawi.png') ?>" alt="Soto Betawi" onerror="this.remove()" />
            <span class="kl"></span><span class="ct tl"></span><span class="ct tr"></span><span class="ct bl"></span><span class="ct br"></span>
            <div class="food-info">
              <h4>Soto Betawi</h4>
              <div class="price">
                <span class="pz">À <b>48K</b></span>
                <span class="pz">R <b>50K</b></span>
              </div>
            </div>
          </article>

          <article class="food-card">
            <div class="plate"><span class="plate-tag"><span class="ring"><i></i></span><span class="code">Nasi Campur Bali</span><span class="pname">photo · 4:5 plated</span></span></div>
            <img class="food-img" src="<?= base_url('assets/menu-book/products/foods/indonesian-heritage/nasi-campur-bali.png') ?>" alt="Nasi Campur Bali" onerror="this.remove()" />
            <span class="kl"></span><span class="ct tl"></span><span class="ct tr"></span><span class="ct bl"></span><span class="ct br"></span>
            <div class="food-info">
              <h4>Nasi Campur Bali</h4>
              <div class="price">
                <span class="pz solo">À&nbsp;la&nbsp;Carte <b>29K</b></span>
              </div>
            </div>
          </article>

          <article class="food-card">
            <div class="plate"><span class="plate-tag"><span class="ring"><i></i></span><span class="code">Nasi Goreng Kampoeng</span><span class="pname">photo · 4:5 plated</span></span></div>
            <img class="food-img" src="<?= base_url('assets/menu-book/products/foods/indonesian-heritage/nasi-goreng-kampoeng.png') ?>" alt="Nasi Goreng Kampoeng" onerror="this.remove()" />
            <span class="kl"></span><span class="ct tl"></span><span class="ct tr"></span><span class="ct bl"></span><span class="ct br"></span>
            <div class="food-info">
              <h4>Nasi Goreng Kampoeng</h4>
              <div class="price">
                <span class="pz solo">À&nbsp;la&nbsp;Carte <b>23K</b></span>
              </div>
            </div>
          </article>

        </div>
      </section>

      <!-- ── CRAVE CORNER ── -->
      <section class="food-section crave">
        <div class="section-heading">
          <div class="sh-l">
            <span class="sh-no">II.</span>
            <h3>Crave Corner</h3>
          </div>
          <p>Comfort food worth craving</p>
        </div>

        <div class="food-gallery crave-gallery">

          <article class="food-card feature">
            <div class="plate"><span class="plate-tag"><span class="ring"><i></i></span><span class="code">Bebek Goreng</span><span class="pname">photo · 4:5 plated</span></span></div>
            <img class="food-img" src="<?= base_url('assets/menu-book/products/foods/crave-corner/bebek-goreng-bumbu-rempah.png') ?>" alt="Bebek Goreng Bumbu Rempah" onerror="this.remove()" />
            <span class="kl"></span><span class="ct tl"></span><span class="ct tr"></span><span class="ct bl"></span><span class="ct br"></span>
            <div class="food-info">
              <span class="mini-badge dark">★ Favorite</span>
              <h4>Bebek Goreng Bumbu Rempah</h4>
              <div class="name-rule"></div>
              <p class="food-desc">6-hour slow-cooked marinated duck, serundeng, sambal idjo &amp; sambal tomat.</p>
              <div class="price">
                <span class="pz">À&nbsp;la&nbsp;Carte <b>41K</b></span>
                <span class="pz">Rice&nbsp;Set <b>43K</b></span>
              </div>
            </div>
          </article>

          <article class="food-card">
            <div class="plate"><span class="plate-tag"><span class="ring"><i></i></span><span class="code">Beef Lombok Ijo</span><span class="pname">photo · 4:5 plated</span></span></div>
            <img class="food-img" src="<?= base_url('assets/menu-book/products/foods/crave-corner/beef-lombok-ijo.png') ?>" alt="Beef Lombok Ijo" onerror="this.remove()" />
            <span class="kl"></span><span class="ct tl"></span><span class="ct tr"></span><span class="ct bl"></span><span class="ct br"></span>
            <div class="food-info">
              <h4>Beef Lombok Ijo</h4>
              <div class="price">
                <span class="pz solo">À&nbsp;la&nbsp;Carte <b>32K</b></span>
              </div>
            </div>
          </article>

          <article class="food-card">
            <div class="plate"><span class="plate-tag"><span class="ring"><i></i></span><span class="code">Shortplate Parape</span><span class="pname">photo · 4:5 plated</span></span></div>
            <img class="food-img" src="<?= base_url('assets/menu-book/products/foods/crave-corner/grill-beef-shortplate-parape.png') ?>" alt="Grill Beef Shortplate Parape" onerror="this.remove()" />
            <span class="kl"></span><span class="ct tl"></span><span class="ct tr"></span><span class="ct bl"></span><span class="ct br"></span>
            <div class="food-info">
              <h4>Grill Beef Shortplate Parape</h4>
              <div class="price">
                <span class="pz solo">À&nbsp;la&nbsp;Carte <b>31K</b></span>
              </div>
            </div>
          </article>

          <article class="food-card">
            <div class="plate"><span class="plate-tag"><span class="ring"><i></i></span><span class="code">Seafood Pantura</span><span class="pname">photo · 4:5 plated</span></span></div>
            <img class="food-img" src="<?= base_url('assets/menu-book/products/foods/crave-corner/grill-seafood-platter-pantura.png') ?>" alt="Grill Seafood Platter Pantura" onerror="this.remove()" />
            <span class="kl"></span><span class="ct tl"></span><span class="ct tr"></span><span class="ct bl"></span><span class="ct br"></span>
            <div class="food-info">
              <h4>Grill Seafood Platter Pantura</h4>
              <div class="price">
                <span class="pz solo">À&nbsp;la&nbsp;Carte <b>31K</b></span>
              </div>
            </div>
          </article>

          <article class="food-card">
            <div class="plate"><span class="plate-tag"><span class="ring"><i></i></span><span class="code">Ayam Bumbu Ireng</span><span class="pname">photo · 4:5 plated</span></span></div>
            <img class="food-img" src="<?= base_url('assets/menu-book/products/foods/crave-corner/ayam-bumbu-ireng.png') ?>" alt="Ayam Bumbu Ireng" onerror="this.remove()" />
            <span class="kl"></span><span class="ct tl"></span><span class="ct tr"></span><span class="ct bl"></span><span class="ct br"></span>
            <div class="food-info">
              <h4>Ayam Bumbu Ireng</h4>
              <div class="price">
                <span class="pz">À <b>31K</b></span>
                <span class="pz">R <b>33K</b></span>
              </div>
            </div>
          </article>

          <article class="food-card">
            <div class="plate"><span class="plate-tag"><span class="ring"><i></i></span><span class="code">Ayam Bakar Dabu</span><span class="pname">photo · 4:5 plated</span></span></div>
            <img class="food-img" src="<?= base_url('assets/menu-book/products/foods/crave-corner/ayam-bakar-sambal-dabu-dabu.png') ?>" alt="Ayam Bakar Sambal Dabu-Dabu" onerror="this.remove()" />
            <span class="kl"></span><span class="ct tl"></span><span class="ct tr"></span><span class="ct bl"></span><span class="ct br"></span>
            <div class="food-info">
              <h4>Ayam Bakar Sambal Dabu-Dabu</h4>
              <div class="price">
                <span class="pz">À <b>26K</b></span>
                <span class="pz">R <b>28K</b></span>
              </div>
            </div>
          </article>

          <article class="food-card crave-wide">
            <div class="plate"><span class="plate-tag"><span class="ring"><i></i></span><span class="code">Ayam Goreng Kremes</span><span class="pname">photo · 16:9 plated</span></span></div>
            <img class="food-img" src="<?= base_url('assets/menu-book/products/foods/crave-corner/ayam-goreng-rempah-kremes.png') ?>" alt="Ayam Goreng Rempah Kremes" onerror="this.remove()" />
            <span class="kl"></span><span class="ct tl"></span><span class="ct tr"></span><span class="ct bl"></span><span class="ct br"></span>
            <div class="food-info">
              <span class="mini-badge">✦ House Classic</span>
              <h4>Ayam Goreng Rempah Kremes</h4>
              <div class="name-rule"></div>
              <p class="food-desc">Spice-marinated fried chicken crowned with crisp golden kremes, sambal bawang &amp; fresh lalapan.</p>
              <div class="price">
                <span class="pz">À&nbsp;la&nbsp;Carte <b>23K</b></span>
                <span class="pz">Rice&nbsp;Set <b>25K</b></span>
              </div>
            </div>
          </article>

        </div>
      </section>

      <div class="legend">
        <span><b>À</b> — À la Carte</span>
        <span class="dot"></span>
        <span><b>R</b> — Rice Set</span>
        <span class="dot"></span>
        <span>All prices in thousand Rupiah</span>
      </div>

    </div>

    <footer class="menu-footer">
      <span class="l">NAMUA Coffee &amp; Eatery</span>
      <span class="c">#kembalikenamua</span>
      <span class="ig">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2.5" y="2.5" width="19" height="19" rx="5.5"/><circle cx="12" cy="12" r="4.4"/><circle cx="17.6" cy="6.4" r="1.1" fill="currentColor" stroke="none"/></svg>
        @namuacoffee
      </span>
    </footer>

  </div>
</section>

<template id="__bundler_thumbnail" data-bg-color="#F6EFE1">
  <svg viewBox="0 0 1200 800" xmlns="http://www.w3.org/2000/svg">
    <rect width="1200" height="800" fill="#F6EFE1"/>
    <rect x="40" y="40" width="1120" height="720" fill="none" stroke="#C9A24E" stroke-width="6"/>
    <text x="600" y="320" text-anchor="middle" font-family="Pinyon Script, cursive" font-size="96" fill="#C9A24E">Nusantara &amp;</text>
    <text x="600" y="440" text-anchor="middle" font-family="Playfair Display, Georgia, serif" font-size="92" font-weight="700" fill="#A8232C" letter-spacing="8">COMFORT FOOD</text>
    <rect x="480" y="490" width="240" height="3" fill="#C9A24E"/>
    <text x="600" y="580" text-anchor="middle" font-family="Jost, sans-serif" font-size="30" fill="#B68C39" letter-spacing="14">NAMUA COFFEE &amp; EATERY</text>
  </svg>
</template>

</body>
</html>
