<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>NAMUA Signatures</title>

<style>
@page { size: A4; margin: 0; }

* {
  box-sizing: border-box;
}

body {
  margin: 0;
  background: #ddd;
  font-family: "Poppins", Arial, sans-serif;
}

.page {
  width: 210mm;
  height: 297mm;
  margin: auto;
  position: relative;
  overflow: hidden;
  padding: 10mm;
  background:
    radial-gradient(circle at top left, rgba(255,255,255,.8), transparent 35%),
    linear-gradient(135deg, #f8ead0 0%, #fff7e7 48%, #ead0a8 100%);
  color: #4a261f;
}

.page::before {
  content: "";
  position: absolute;
  inset: 7mm;
  border: 1.2px solid rgba(123,31,43,.22);
  border-radius: 24px;
  pointer-events: none;
}

.page::after {
  content: "";
  position: absolute;
  inset: 0;
  background:
    radial-gradient(circle at 8% 18%, rgba(123,31,43,.12), transparent 18%),
    radial-gradient(circle at 92% 88%, rgba(194,138,61,.16), transparent 24%);
  pointer-events: none;
}

.content {
  position: relative;
  z-index: 2;
  height: 100%;
}

.header {
  height: 31mm;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 3mm 3mm 0;
}

.eyebrow {
  font-size: 9px;
  letter-spacing: 3px;
  font-weight: 800;
  color: #a36b32;
}

h1 {
  margin: 3px 0 0;
  color: #7b1f2b;
  font-size: 37px;
  line-height: .9;
  font-weight: 900;
  letter-spacing: -1px;
}

.subtitle {
  margin-top: 7px;
  color: #765143;
  font-size: 13px;
  font-style: italic;
}

.badge {
  margin-top: 4px;
  padding: 8px 14px;
  border-radius: 999px;
  background: #7b1f2b;
  color: #fff2dc;
  font-size: 11px;
  font-weight: 800;
}

.signature-coffee {
  height: 154mm;
  margin-top: 2mm;
  padding: 5mm;
  border-radius: 24px;
  background: rgba(255,248,235,.72);
  box-shadow: 0 12px 28px rgba(70,35,20,.12);
  border: 1px solid rgba(123,31,43,.12);
}

.section-head {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 4mm;
}

.section-head h2 {
  margin: 0;
  color: #7b1f2b;
  font-size: 20px;
  font-weight: 900;
  letter-spacing: .5px;
}

.section-head span {
  flex: 1;
  height: 1px;
  background: rgba(123,31,43,.28);
}

.coffee-layout {
  display: grid;
  grid-template-columns: 111mm 1fr;
  gap: 5mm;
  height: calc(100% - 14mm);
}

.photo-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4mm;
}

.photo-card {
  position: relative;
  overflow: hidden;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 9px 18px rgba(58,31,18,.2);
  border: 2px solid rgba(255,255,255,.82);
}

.photo-card img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.photo-card::after {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(45,16,14,.58), transparent 48%);
}

.photo-label {
  position: absolute;
  z-index: 2;
  left: 11px;
  right: 11px;
  bottom: 10px;
  color: #fff6e8;
}

.photo-label b {
  display: block;
  font-size: 12px;
  line-height: 1.1;
}

.photo-label small {
  font-size: 8.5px;
  opacity: .9;
}

.menu-list {
  padding: 3mm 3mm 0;
}

.item {
  padding: 9px 0;
  border-bottom: 1px dashed rgba(123,31,43,.22);
}

.item:last-child {
  border-bottom: none;
}

.item-head {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  align-items: baseline;
}

.name {
  font-size: 13px;
  font-weight: 900;
  color: #4c231e;
}

.price {
  font-size: 15px;
  font-weight: 900;
  color: #a66c2f;
  white-space: nowrap;
}

.desc {
  margin-top: 4px;
  font-size: 9.3px;
  line-height: 1.35;
  color: #725244;
  text-transform: uppercase;
}

.artisan-tea {
  height: 89mm;
  margin-top: 6mm;
  padding: 5mm;
  border-radius: 24px;
  background: rgba(255,248,235,.68);
  box-shadow: 0 12px 28px rgba(70,35,20,.12);
  border: 1px solid rgba(123,31,43,.12);
}

.tea-layout {
  display: grid;
  grid-template-columns: 118mm 1fr;
  gap: 5mm;
  height: calc(100% - 13mm);
}

.tea-image {
  border-radius: 19px;
  overflow: hidden;
  box-shadow: 0 9px 18px rgba(58,31,18,.18);
  border: 2px solid rgba(255,255,255,.78);
}

.tea-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.tea-menu {
  padding: 2mm 1mm;
}

.tea-menu .item {
  padding: 8px 0;
}

.footer-note {
  position: absolute;
  left: 13mm;
  right: 13mm;
  bottom: 8mm;
  text-align: center;
  color: #8d6148;
  font-size: 9px;
  letter-spacing: 1px;
}
</style>
</head>

<body>
<section class="page">
  <div class="content">

    <div class="header">
      <div>
        <div class="eyebrow">NAMUA BEVERAGE COLLECTION</div>
        <h1>NAMUA SIGNATURES</h1>
        <div class="subtitle">The drinks that define us.</div>
      </div>
      <div class="badge">Coffee · Tea · Creamy</div>
    </div>

    <!-- BAGIAN ATAS: SIGNATURE COFFEE -->
    <div class="signature-coffee">
      <div class="section-head">
        <h2>SIGNATURE COFFEE</h2>
        <span></span>
      </div>

      <div class="coffee-layout">

        <div class="photo-grid">
          <div class="photo-card">
            <img src="<?= base_url('assets/menu-book/products/beverages/KOPSU NAMUA(4).jpg'); ?>">
            <div class="photo-label">
              <b>KOPI SUSU NAMUA</b>
              <small>Kawista · Milk · Jelly Chocolate</small>
            </div>
          </div>

          <div class="photo-card">
            <img src="<?= base_url('assets/menu-book/products/beverages/JAZZY ALMOND(4).jpg'); ?>">
            <div class="photo-label">
              <b>JAZZY ALMOND</b>
              <small>Hazelnut · Cream Cheese</small>
            </div>
          </div>

          <div class="photo-card">
            <img src="<?= base_url('assets/menu-book/products/beverages/Namanya Kopi Susu(2).jpeg'); ?>">
            <div class="photo-label">
              <b>NAMANYA KOPI SUSU</b>
              <small>Oat Milk · Sweet Creamy</small>
            </div>
          </div>

          <div class="photo-card">
            <img src="<?= base_url('assets/menu-book/products/beverages/BTS COFFEE(4).png'); ?>">
            <div class="photo-label">
              <b>BTS COFFEE</b>
              <small>Vanilla Ice Cream · Butterscotch</small>
            </div>
          </div>
        </div>

        <div class="menu-list">
          <div class="item">
            <div class="item-head">
              <div class="name">KOPI SUSU NAMUA</div>
              <div class="price">22K</div>
            </div>
            <div class="desc">Single espresso with kawista flavour, milk & jelly chocolate</div>
          </div>

          <div class="item">
            <div class="item-head">
              <div class="name">JAZZY ALMOND</div>
              <div class="price">22K</div>
            </div>
            <div class="desc">Single espresso, fresh milk, hazelnut, cream cheese, topping almond</div>
          </div>

          <div class="item">
            <div class="item-head">
              <div class="name">NAMANYA KOPI SUSU</div>
              <div class="price">19K</div>
            </div>
            <div class="desc">Single espresso, oat milk, and sweet creamy flavour</div>
          </div>

          <div class="item">
            <div class="item-head">
              <div class="name">BTS COFFEE</div>
              <div class="price">24K</div>
            </div>
            <div class="desc">Espresso, fresh milk, ice cream vanilla, butterscotch</div>
          </div>
        </div>

      </div>
    </div>

    <!-- BAGIAN BAWAH: ARTISAN TEA -->
    <div class="artisan-tea">
      <div class="section-head">
        <h2>ARTISAN TEA</h2>
        <span></span>
      </div>

      <div class="tea-layout">
        <div class="tea-image">
          <img src="<?= base_url('assets/menu-book/products/beverages/artisan-tea2.png'); ?>">
        </div>

        <div class="tea-menu">
          <div class="item">
            <div class="item-head">
              <div class="name">NIRVANA BLOOM</div>
              <div class="price">18K</div>
            </div>
            <div class="desc">Chamomile flowers, goji berry, dried lime</div>
          </div>

          <div class="item">
            <div class="item-head">
              <div class="name">SATTVA WHITE</div>
              <div class="price">18K</div>
            </div>
            <div class="desc">White tea, dried apple Fuji</div>
          </div>
        </div>
      </div>
    </div>

    <div class="footer-note">
      #KEMBALIKENAMUA · GOOD COFFEE GOOD MOOD
    </div>

  </div>
</section>
</body>
</html> 