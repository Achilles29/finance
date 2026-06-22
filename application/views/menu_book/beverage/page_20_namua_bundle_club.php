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
    padding:7mm 8mm 10mm;
    height:100%;
}

.header{
    height:42mm;
    text-align:center;
    position:relative;
}

.kicker{
    display:inline-block;
    background:#7d3917;
    color:#fff2dd;
    padding:1.8mm 7mm;
    border-radius:999px;
    font-family:Arial,sans-serif;
    font-size:10px;
    font-weight:900;
    letter-spacing:2px;
    text-transform:uppercase;
}

.title{
    margin-top:2mm;
    font-family:Impact,"Arial Black",Arial,sans-serif;
    font-size:46px;
    line-height:.82;
    letter-spacing:3px;
    color:#2b160f;
    text-transform:uppercase;
}

.title span{
    display:block;
    color:#8b1723;
    font-size:54px;
}

.subtitle{
    margin-top:2mm;
    font-size:13px;
    font-style:italic;
    color:#5a3827;
}

.products{
    height:205mm;
    position:relative;
}

.card{
    position:absolute;
    border-radius:18px;
    overflow:hidden;
    background:rgba(255,245,220,.92);
    border:1.5px solid rgba(120,70,30,.35);
    box-shadow:0 10px 22px rgba(55,25,12,.2);
}

.card img{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
}

.info{
    position:absolute;
    left:0;
    right:0;
    bottom:0;
    padding:3mm 4mm;
    background:linear-gradient(180deg,rgba(75,30,15,.15),rgba(75,30,15,.92));
    color:#fff6e8;
}

.name{
    display:inline-block;
    background:#8b1723;
    padding:1.5mm 4mm;
    border-radius:999px;
    font-family:Impact,"Arial Black",Arial,sans-serif;
    font-size:14px;
    letter-spacing:.8px;
    text-transform:uppercase;
    margin-bottom:1.8mm;
}

.desc{
    font-family:Arial,sans-serif;
    font-size:8px;
    line-height:1.25;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.4px;
}

.price{
    margin-top:1.8mm;
    font-family:Impact,"Arial Black",Arial,sans-serif;
    font-size:25px;
    line-height:1;
    color:#ffe0a3;
    letter-spacing:1px;
}

.badge{
    position:absolute;
    right:3mm;
    top:3mm;
    width:25mm;
    height:25mm;
    border-radius:50%;
    background:#7d3917;
    color:#fff4df;
    display:flex;
    align-items:center;
    justify-content:center;
    text-align:center;
    font-family:Arial,sans-serif;
    font-size:7px;
    line-height:1.2;
    font-weight:900;
    text-transform:uppercase;
    border:2px solid rgba(255,238,200,.8);
}

.badge b{
    display:block;
    font-size:13px;
}

/* HERO TOP */
.basecamp{
    left:0;
    top:0;
    width:96mm;
    height:72mm;
}

.cobain{
    right:0;
    top:0;
    width:90mm;
    height:72mm;
}

/* MIDDLE */
.combo{
    left:0;
    top:77mm;
    width:90mm;
    height:62mm;
}

.double{
    right:0;
    top:77mm;
    width:96mm;
    height:62mm;
}

/* BOTTOM HERO */
.sipaling{
    left:18mm;
    top:144mm;
    width:156mm;
    height:61mm;
}

.sipaling .info{
    text-align:center;
}

.sipaling .name{
    font-size:18px;
}

.sipaling .price{
    font-size:30px;
}

.ribbon{
    position:absolute;
    left:8mm;
    right:8mm;
    bottom:20mm;
    height:20mm;
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
    font-size:8.2px;
    line-height:1.25;
    font-weight:900;
    letter-spacing:.8px;
    text-transform:uppercase;
}

.ribbon div:last-child{border-right:0}

.ribbon b{
    display:block;
    font-size:15px;
    margin-bottom:.7mm;
}

.footer{
    position:absolute;
    left:8mm;
    right:8mm;
    bottom:5mm;
    border-top:1px solid rgba(139,78,35,.45);
    padding-top:2.4mm;
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

        <div class="card basecamp">
            <img src="<?= base_url('assets/menu-book/products/beverages/bundle/kopsu-satu-basecamp.jpg'); ?>" alt="Kopsu Satu Basecamp">
            <div class="badge"><div><b>10 PCS</b>Basecamp</div></div>
            <div class="info">
                <div class="name">Kopsu Satu Basecamp</div>
                <div class="desc">10 pcs Kopsu Gula Aren</div>
                <div class="price">170 K</div>
            </div>
        </div>

        <div class="card cobain">
            <img src="<?= base_url('assets/menu-book/products/beverages/bundle/cobain-semua.jpg'); ?>" alt="Cobain Semua">
            <div class="badge"><div><b>4 Rasa</b>Favorit</div></div>
            <div class="info">
                <div class="name">Cobain Semua</div>
                <div class="desc">Kopsu Gula Aren, Namanya Kopi Susu, Kopi Susu Namua, Strawberry Latte</div>
                <div class="price">70 K</div>
            </div>
        </div>

        <div class="card combo">
            <img src="<?= base_url('assets/menu-book/products/beverages/bundle/coffee-bites-combo.jpg'); ?>" alt="Coffee & Bites Combo">
            <div class="badge"><div><b>Combo</b>Bites</div></div>
            <div class="info">
                <div class="name">Coffee &amp; Bites Combo</div>
                <div class="desc">3 pcs Kopsu Gula Aren + 2 porsi Dimsum</div>
                <div class="price">80 K</div>
            </div>
        </div>

        <div class="card double">
            <img src="<?= base_url('assets/menu-book/products/beverages/bundle/double-johny.jpg'); ?>" alt="Double Johny">
            <div class="badge"><div><b>2 Botol</b>Literan</div></div>
            <div class="info">
                <div class="name">Double Johny</div>
                <div class="desc">2 botol Johny Napalm</div>
                <div class="price">125 K</div>
            </div>
        </div>

        <div class="card sipaling">
            <img src="<?= base_url('assets/menu-book/products/beverages/bundle/si-paling-kopi-susu.jpg'); ?>" alt="Si Paling Kopi Susu">
            <div class="badge"><div><b>3 PCS</b>Kopi Susu</div></div>
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