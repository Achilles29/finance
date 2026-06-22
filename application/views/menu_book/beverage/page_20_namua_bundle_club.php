<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>NAMUA Bundle Club</title>

<style>
@page{size:A4 portrait;margin:0}
*{box-sizing:border-box}
body{margin:0;background:#2b1b15}

.page{
    width:210mm;
    height:297mm;
    margin:auto;
    position:relative;
    overflow:hidden;
    background:url("<?= base_url('assets/menu-book/backgrounds/bg-bundle.png'); ?>") center/cover no-repeat;
    font-family:Georgia,"Times New Roman",serif;
    color:#2e170f;
}

.wrap{
    padding:6mm 8mm 9mm;
    height:100%;
}

.header{
    height:39mm;
    text-align:center;
    position:relative;
}

.kicker{
    display:inline-block;
    background:#7d3917;
    color:#fff2dd;
    padding:1.6mm 7mm;
    border-radius:999px;
    font-family:Arial,sans-serif;
    font-size:9px;
    font-weight:900;
    letter-spacing:2px;
    text-transform:uppercase;
}

.title{
    margin-top:1.8mm;
    font-family:Impact,"Arial Black",Arial,sans-serif;
    font-size:42px;
    line-height:.82;
    letter-spacing:3px;
    color:#2b160f;
    text-transform:uppercase;
}

.title span{
    display:block;
    color:#8b1723;
    font-size:50px;
}

.subtitle{
    margin-top:1.8mm;
    font-size:12px;
    font-style:italic;
    color:#5a3827;
}

/* POSTER COLLAGE */
.products{
    height:211mm;
    position:relative;
}

.card{
    position:absolute;
    border-radius:18px;
    overflow:hidden;
    background:#ddd;
    border:2px solid rgba(255,239,209,.9);
    box-shadow:0 12px 24px rgba(45,20,10,.24);
}

.card img{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
}

.card:after{
    content:"";
    position:absolute;
    inset:0;
    background:linear-gradient(180deg,transparent 45%,rgba(45,18,8,.86));
    pointer-events:none;
}

.info{
    position:absolute;
    left:3mm;
    right:3mm;
    bottom:3mm;
    z-index:3;
    color:#fff6e8;
}

.name{
    display:inline-block;
    background:#8b1723;
    padding:1.3mm 4mm;
    border-radius:999px;
    font-family:Impact,"Arial Black",Arial,sans-serif;
    font-size:13px;
    letter-spacing:.8px;
    line-height:1;
    text-transform:uppercase;
    margin-bottom:1.6mm;
}

.desc{
    font-family:Arial,sans-serif;
    font-size:7.2px;
    line-height:1.22;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.3px;
    max-width:88%;
}

.price{
    margin-top:1.3mm;
    display:inline-block;
    background:rgba(255,244,220,.94);
    color:#8b1723;
    border-radius:12px;
    padding:1.5mm 4mm;
    font-family:Impact,"Arial Black",Arial,sans-serif;
    font-size:22px;
    line-height:1;
    letter-spacing:1px;
}

.promo{
    position:absolute;
    top:3mm;
    right:3mm;
    z-index:4;
    background:#b98532;
    color:#fff8e8;
    border-radius:999px;
    padding:1.2mm 3mm;
    font-family:Arial,sans-serif;
    font-size:7px;
    font-weight:900;
    letter-spacing:1px;
    text-transform:uppercase;
}

/* BIG HERO */
.combo{
    left:0;
    top:0;
    width:118mm;
    height:88mm;
    transform:rotate(-1.5deg);
}

.double{
    right:0;
    top:5mm;
    width:72mm;
    height:83mm;
    transform:rotate(1.5deg);
}

.basecamp{
    left:0;
    top:93mm;
    width:92mm;
    height:74mm;
    transform:rotate(1.2deg);
}

.cobain{
    right:0;
    top:93mm;
    width:98mm;
    height:74mm;
    transform:rotate(-1.2deg);
}

.sipaling{
    left:17mm;
    top:172mm;
    width:158mm;
    height:39mm;
    transform:rotate(.4deg);
}

.sipaling:after{
    background:linear-gradient(90deg,rgba(45,18,8,.82),rgba(45,18,8,.22));
}

.sipaling .info{
    left:5mm;
    top:50%;
    bottom:auto;
    transform:translateY(-50%);
    width:80mm;
}

.sipaling .name{
    font-size:15px;
}

.sipaling .price{
    font-size:25px;
}

/* PROMO STRIP */
.ribbon{
    position:absolute;
    left:8mm;
    right:8mm;
    bottom:20mm;
    height:18mm;
    background:#8b1723;
    color:#fff6e7;
    border-radius:15px;
    display:grid;
    grid-template-columns:repeat(4,1fr);
    align-items:center;
    text-align:center;
    box-shadow:0 7px 14px rgba(70,25,20,.18);
}

.ribbon div{
    padding:0 3mm;
    border-right:1px dotted rgba(255,255,255,.48);
    font-family:Arial,sans-serif;
    font-size:7.8px;
    line-height:1.22;
    font-weight:900;
    letter-spacing:.7px;
    text-transform:uppercase;
}

.ribbon div:last-child{border-right:0}

.ribbon b{
    display:block;
    font-size:14px;
    margin-bottom:.5mm;
}

.footer{
    position:absolute;
    left:8mm;
    right:8mm;
    bottom:5mm;
    border-top:1px solid rgba(139,78,35,.45);
    padding-top:2.3mm;
    display:flex;
    justify-content:center;
    gap:12mm;
    align-items:center;
    color:#7b1b24;
    font-size:11px;
}

.ig{font-family:Arial,sans-serif}
.hash{font-style:italic}
</style>
</head>

<body>
<div class="page">
<div class="wrap">

    <header class="header">
        <div class="kicker">NAMUA Coffee & Eatery</div>
        <div class="title">Bundle <span>Club</span></div>
        <div class="subtitle">Satu paket, lebih hemat, lebih nikmat.</div>
    </header>

    <section class="products">

        <div class="card combo">
            <img src="<?= base_url('assets/menu-book/products/beverages/bundle/coffee-bites-combo.jpg'); ?>" alt="Coffee & Bites Combo">
            <div class="promo">Best Combo</div>
            <div class="info">
                <div class="name">Coffee &amp; Bites Combo</div>
                <div class="desc">3 pcs Kopsu Gula Aren + 2 porsi Dimsum</div>
                <div class="price">80 K</div>
            </div>
        </div>

        <div class="card double">
            <img src="<?= base_url('assets/menu-book/products/beverages/bundle/double-johny.jpg'); ?>" alt="Double Johny">
            <div class="promo">2 Botol</div>
            <div class="info">
                <div class="name">Double Johny</div>
                <div class="desc">2 botol Johny Napalm</div>
                <div class="price">125 K</div>
            </div>
        </div>

        <div class="card basecamp">
            <img src="<?= base_url('assets/menu-book/products/beverages/bundle/kopsu-satu-basecamp.jpg'); ?>" alt="Kopsu Satu Basecamp">
            <div class="promo">10 PCS</div>
            <div class="info">
                <div class="name">Kopsu Satu Basecamp</div>
                <div class="desc">10 pcs Kopsu Gula Aren</div>
                <div class="price">170 K</div>
            </div>
        </div>

        <div class="card cobain">
            <img src="<?= base_url('assets/menu-book/products/beverages/bundle/cobain-semua.jpg'); ?>" alt="Cobain Semua">
            <div class="promo">4 Rasa</div>
            <div class="info">
                <div class="name">Cobain Semua</div>
                <div class="desc">Kopsu Gula Aren, Namanya Kopi Susu, Kopi Susu Namua, Strawberry Latte</div>
                <div class="price">70 K</div>
            </div>
        </div>

        <div class="card sipaling">
            <img src="<?= base_url('assets/menu-book/products/beverages/bundle/si-paling-kopi-susu.jpg'); ?>" alt="Si Paling Kopi Susu">
            <div class="promo">3 PCS</div>
            <div class="info">
                <div class="name">Si Paling Kopi Susu</div>
                <div class="desc">3 pcs Namanya Kopi Susu</div>
                <div class="price">45 K</div>
            </div>
        </div>

    </section>

    <section class="ribbon">
        <div><b>☕</b>Rasa favorit<br>NAMUA</div>
        <div><b>✓</b>Lebih hemat<br>satu paket</div>
        <div><b>❄</b>Disajikan dingin<br>lebih segar</div>
        <div><b>❤</b>Cocok buat<br>semua momen</div>
    </section>

</div>

<footer class="footer">
    <div class="ig">◎ @namuacoffee</div>
    <div class="hash">#kembalikenamua</div>
</footer>

</div>
</body>
</html>