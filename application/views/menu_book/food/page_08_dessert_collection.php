<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Dessert Collection - NAMUA</title>

<style>
@page { size:A4 portrait; margin:0; }
*{ box-sizing:border-box; }

body{
  margin:0;
  background:#ddd;
}

.page{
  width:210mm;
  height:297mm;
  margin:auto;
  position:relative;
  overflow:hidden;
  font-family:Georgia, "Times New Roman", serif;
  color:#6b0909;
  background:url("<?= base_url('assets/menu-book/backgrounds/bg-nusantara-comfort.png') ?>") center/cover no-repeat;
}

/* LOGO */
.logo-wrap{
  position:absolute;
  top:10mm;
  left:12mm;
  width:34mm;
  height:34mm;
  border-radius:50%;
  background:#fffaf1;
  display:flex;
  align-items:center;
  justify-content:center;
  z-index:30;
  box-shadow:
    0 0 0 1.2mm #d3ad55,
    0 3mm 8mm rgba(50,0,0,.28);
}

.logo{
  width:27mm;
}

/* HEADER */
.header{
  position:absolute;
  top:10mm;
  left:50mm;
  right:10mm;
  text-align:center;
  z-index:20;
}

.script{
  font-size:11mm;
  font-style:italic;
  color:#b7832f;
  line-height:.8;
}

.header h1{
  margin:1mm 0 0;
  font-size:19mm;
  line-height:.9;
  letter-spacing:1.4mm;
  color:#780909;
}

.header h2{
  margin:2mm 0 0;
  font-size:6mm;
  letter-spacing:3mm;
  color:#b7832f;
}

.tagline{
  margin-top:3mm;
  font:700 2.6mm Arial, sans-serif;
  letter-spacing:1.1mm;
  color:#5d1111;
}

/* CARD */
.item{
  position:absolute;
  z-index:10;
  width:58mm;
}

.photo{
  width:100%;
  height:34mm;
  border-radius:6mm 6mm 0 0;
  overflow:hidden;
  border:1mm solid #d0a653;
  border-bottom:none;
  background:#fff8ee;
}

.photo img{
  width:100%;
  height:100%;
  object-fit:cover;
  display:block;
}

.info{
  min-height:32mm;
  padding:2.7mm 3mm 3mm;
  border-radius:0 0 5mm 5mm;
  background:rgba(255,248,237,.96);
  border:1mm solid #d0a653;
  border-top:none;
  box-shadow:0 3mm 7mm rgba(70,0,0,.18);
}

.info h3{
  margin:0;
  font-size:4.4mm;
  line-height:.95;
  color:#720909;
  text-transform:uppercase;
}

.price{
  display:inline-block;
  margin:1.7mm 0 1.5mm;
  padding:1mm 3.8mm;
  background:linear-gradient(135deg,#936014,#d5aa46);
  color:white;
  font:800 4.3mm Arial, sans-serif;
  border-radius:1mm;
}

.desc{
  font:700 2.05mm/1.35 Arial, sans-serif;
  color:#371010;
  text-transform:uppercase;
}

.no{
  position:absolute;
  top:29mm;
  right:-4mm;
  width:8.5mm;
  height:8.5mm;
  background:#760909;
  color:#fff7e8;
  transform:rotate(45deg);
  display:grid;
  place-items:center;
  font:800 3.2mm Arial, sans-serif;
  z-index:15;
  box-shadow:0 1.5mm 4mm rgba(0,0,0,.25);
}

.no span{
  transform:rotate(-45deg);
}

/* LAYOUT AMAN */
.i1{ top:65mm; left:13mm; }
.i2{ top:65mm; right:13mm; }

.i3{ top:140mm; left:13mm; }
.i4{ top:140mm; right:13mm; }

.i5{ top:213mm; left:76mm; }

/* CENTER TEXT */
.center-note{
  position:absolute;
  top:88mm;
  left:76mm;
  width:58mm;
  padding:5mm 4mm;
  text-align:center;
  background:rgba(255,248,237,.88);
  border:.4mm solid rgba(185,135,48,.7);
  border-radius:5mm;
  z-index:5;
}

.center-note .small{
  font:800 2.3mm Arial, sans-serif;
  letter-spacing:.9mm;
  color:#7a0909;
}

.center-note .big{
  margin-top:2mm;
  font-size:8mm;
  line-height:.85;
  font-style:italic;
  color:#b7832f;
}

.center-note .copy{
  margin-top:3mm;
  font:700 2.15mm/1.35 Arial, sans-serif;
  color:#431313;
}

/* QUOTE */
.quote{
  position:absolute;
  right:16mm;
  bottom:28mm;
  text-align:center;
  z-index:6;
}

.q1{
  font-size:9mm;
  line-height:.75;
  font-style:italic;
  color:#b7832f;
}

.q2{
  font-size:9mm;
  line-height:.9;
  font-weight:900;
  color:#7a0909;
}

/* FOOTER */
.footer{
  position:absolute;
  bottom:5mm;
  left:0;
  width:100%;
  text-align:center;
  font:700 2.6mm Arial, sans-serif;
  color:#7a0909;
  letter-spacing:.4mm;
  z-index:20;
}
</style>
</head>

<body>
<div class="page">

  <div class="logo-wrap">
    <img class="logo" src="<?= base_url('assets/menu-book/logo/logo.png') ?>" alt="NAMUA Logo">
  </div>

  <div class="header">
    <div class="script">Indulge in Our</div>
    <h1>DESSERT</h1>
    <h2>COLLECTION</h2>
    <div class="tagline">SWEET MOMENTS, PERFECTLY MADE.</div>
  </div>

  <div class="center-note">
    <div class="small">NAMUA SWEET ESCAPE</div>
    <div class="big">crafted for<br>happy moments</div>
    <div class="copy">A premium dessert selection with soft cream, chocolate, buttery bread, crepes, crumble, and ice cream.</div>
  </div>

  <div class="item i1">
    <div class="photo">
      <img src="<?= base_url('assets/menu-book/products/foods/dessert/CHOCO-BANANA-CREPES.png') ?>">
    </div>
    <div class="no"><span>01</span></div>
    <div class="info">
      <h3>Choco<br>Banana Crepes</h3>
      <div class="price">22 K</div>
      <div class="desc">Roll crepes fill banana crush inside, ting-ting crumble & ice cream</div>
    </div>
  </div>

  <div class="item i2">
    <div class="photo">
      <img src="<?= base_url('assets/menu-book/products/foods/dessert/CHOCO-SWEET-BREAD.png') ?>">
    </div>
    <div class="no"><span>02</span></div>
    <div class="info">
      <h3>Choco<br>Sweet Bread</h3>
      <div class="price">26 K</div>
      <div class="desc">Bread coated Nutella, sweet cream, choco ball & ice cream</div>
    </div>
  </div>

  <div class="item i3">
    <div class="photo">
      <img src="<?= base_url('assets/menu-book/products/foods/dessert/NAMUA-SIGNATURE-SWEET-BREAD.png') ?>">
    </div>
    <div class="no"><span>03</span></div>
    <div class="info">
      <h3>Namua<br>Signature<br>Sweet Bread</h3>
      <div class="price">28 K</div>
      <div class="desc">Bread toast, sweet cream redvelvet, Oreo crumble, biscuit & ice cream</div>
    </div>
  </div>

  <div class="item i4">
    <div class="photo">
      <img src="<?= base_url('assets/menu-book/products/foods/dessert/vellova-mille.png') ?>">
    </div>
    <div class="no"><span>04</span></div>
    <div class="info">
      <h3>Vellova<br>Mille</h3>
      <div class="price">27 K</div>
      <div class="desc">Crepes red velvet with whippy cream & ice cream</div>
    </div>
  </div>

  <div class="item i5">
    <div class="photo">
      <img src="<?= base_url('assets/menu-book/products/foods/dessert/choco-mille.png') ?>">
    </div>
    <div class="no"><span>05</span></div>
    <div class="info">
      <h3>Choco<br>Mille</h3>
      <div class="price">25 K</div>
      <div class="desc">Crepes chocolate with whippy cream & ice cream</div>
    </div>
  </div>

  <div class="quote">
    <div class="q1">Life is</div>
    <div class="q2">SWEETER</div>
    <div class="q1">with Dessert</div>
  </div>

  <div class="footer">
    @namua.coffee &nbsp; • &nbsp; namua coffee &nbsp; • &nbsp; #kembalikenamua
  </div>

</div>
</body>
</html>