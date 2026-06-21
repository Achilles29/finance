<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Bowl, Spice & Noodles - NAMUA</title>

<style>
@page { size: A4 portrait; margin: 0; }
* { box-sizing: border-box; }

body {
    margin: 0;
    background: #ddd;
    font-family: Georgia, "Times New Roman", serif;
}

.page {
    width: 210mm;
    height: 297mm;
    margin: auto;
    position: relative;
    overflow: hidden;
    background:
        url("<?= base_url('assets/menu-book/backgrounds/bg-bowl-spice-noodles.png'); ?>")
        center / cover no-repeat;
    color: #3e1a10;
}

.content {
    position: absolute;
    inset: 14mm 14mm 15mm 14mm;
}

.header {
    text-align: center;
    margin-bottom: 10mm;
}

.header small {
    display: block;
    font-size: 10pt;
    letter-spacing: 3px;
    color: #b98220;
    font-weight: 700;
}

.header h1 {
    margin: 1mm 0 0;
    font-size: 33pt;
    line-height: .95;
    font-weight: 900;
    color: #3b160d;
    letter-spacing: 1px;
}

.header h1 span {
    color: #c89124;
}

.header p {
    margin: 3mm 0 0;
    font-size: 12pt;
    font-style: italic;
}

.card {
    height: 65mm;
    margin-bottom: 7mm;
    border: 1.5px solid #c89124;
    border-radius: 10mm 3mm 10mm 3mm;
    background: rgba(255,255,255,.78);
    display: grid;
    grid-template-columns: 42% 58%;
    overflow: hidden;
    box-shadow: 0 5px 16px rgba(94, 52, 20, .14);
}

.card-img {
    position: relative;
    overflow: hidden;
}

.card-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cat-number {
    position: absolute;
    top: 4mm;
    left: 4mm;
    width: 11mm;
    height: 11mm;
    border-radius: 50%;
    background: #a51f14;
    color: white;
    border: 1.5px solid white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15pt;
    font-weight: 900;
}

.card-menu {
    padding: 8mm 8mm 6mm;
}

.cat-title {
    font-size: 19pt;
    line-height: 1;
    margin: 0 0 4mm;
    color: #3b160d;
    text-transform: uppercase;
    font-weight: 900;
}

.cat-title span {
    color: #c89124;
}

.menu-row {
    display: grid;
    grid-template-columns: 1fr 16mm;
    gap: 4mm;
    padding: 1.7mm 0;
    border-bottom: 1px dotted rgba(184,122,30,.65);
}

.name {
    font-size: 10.8pt;
    font-weight: 900;
    text-transform: uppercase;
    line-height: 1.1;
}

.desc {
    font-size: 6.8pt;
    line-height: 1.2;
    margin-top: .7mm;
    font-family: Arial, sans-serif;
    color: #5d3829;
    text-transform: uppercase;
}

.price {
    text-align: right;
    font-size: 13pt;
    font-weight: 900;
    color: #3b160d;
    align-self: center;
}

.footer {
    position: absolute;
    bottom: 5mm;
    left: 0;
    right: 0;
    text-align: center;
    font-size: 11.5pt;
    color: #3b160d;
}

.hashtag {
    font-style: italic;
    font-size: 15pt;
    margin-right: 8mm;
}

.ig {
    font-family: Arial, sans-serif;
    border: 1.5px solid #3b160d;
    border-radius: 5px;
    padding: 0 4px;
    margin-right: 2mm;
    font-weight: bold;
}
</style>
</head>

<body>

<div class="page">

    <div class="content">

        <div class="header">
            <small>NAMUA FOOD COLLECTION</small>
            <h1><span>BOWL, SPICE</span><br>& NOODLES</h1>
            <p>Spicy bites, rice bowls & comfort mi.</p>
        </div>

        <!-- SPICY -->
        <div class="card">
            <div class="card-img">
                <span class="cat-number">01</span>
                <img src="<?= base_url('assets/menu-book/products/foods/bowl-spice-noodles/geprek.png'); ?>">
            </div>

            <div class="card-menu">
                <h2 class="cat-title"><span>Spicy</span> Bites</h2>

                <div class="menu-row">
                    <div>
                        <div class="name">Mozza Melt</div>
                        <div class="desc">Rice, chicken breast, bread crumb, sambal bawang geprek, cucumber, tomato, green curly lettuce</div>
                    </div>
                    <div class="price">24K</div>
                </div>

                <div class="menu-row">
                    <div>
                        <div class="name">Geprek OG</div>
                        <div class="desc">Rice, chicken breast, mix flour, sambal bawang geprek, cucumber, tomato, green curly lettuce</div>
                    </div>
                    <div class="price">20K</div>
                </div>

                <div class="menu-row">
                    <div>
                        <div class="name">Mie Bara I</div>
                        <div class="desc">Spicy level noodles choice, minced chicken & wonton level 1</div>
                    </div>
                    <div class="price">15K</div>
                </div>

                <div class="menu-row">
                    <div>
                        <div class="name">Mie Bara II</div>
                        <div class="desc">Spicy level noodles choice, minced chicken & wonton level 2</div>
                    </div>
                    <div class="price">17K</div>
                </div>

                <div class="menu-row">
                    <div>
                        <div class="name">Mie Bara III</div>
                        <div class="desc">Spicy level noodles choice, minced chicken & wonton level 3</div>
                    </div>
                    <div class="price">17K</div>
                </div>
            </div>
        </div>

        <!-- RICE BOWL -->
        <div class="card">
            <div class="card-img">
                <span class="cat-number">02</span>
                <img src="<?= base_url('assets/menu-book/products/foods/bowl-spice-noodles/bowl.png'); ?>">
            </div>

            <div class="card-menu">
                <h2 class="cat-title"><span>Rice</span> Bowl</h2>

                <div class="menu-row">
                    <div>
                        <div class="name">Golden Chicken Bowl</div>
                        <div class="desc">Rice crispy chicken, fresh salad, sunny side up egg, sambal matah</div>
                    </div>
                    <div class="price">22K</div>
                </div>

                <div class="menu-row">
                    <div>
                        <div class="name">Canggu Dory Bowl</div>
                        <div class="desc">Rice dory crispy, sunny side up egg, fresh salad, sambal matah</div>
                    </div>
                    <div class="price">23K</div>
                </div>

                <div class="menu-row">
                    <div>
                        <div class="name">Crispy Skin Bowl</div>
                        <div class="desc">Rice, kulit crispy, sunny side up egg, sambal dabu-dabu</div>
                    </div>
                    <div class="price">19K</div>
                </div>

                <div class="menu-row">
                    <div>
                        <div class="name">Egg Comfort Bowl</div>
                        <div class="desc">Rice, egg, fresh salad, sambal bawang</div>
                    </div>
                    <div class="price">16K</div>
                </div>
            </div>
        </div>

        <!-- INDOMIE -->
        <div class="card">
            <div class="card-img">
                <span class="cat-number">03</span>
                <img src="<?= base_url('assets/menu-book/products/foods/bowl-spice-noodles/indomie.png'); ?>">
            </div>

            <div class="card-menu">
                <h2 class="cat-title"><span>Anak Kos</span> Core</h2>

                <div class="menu-row">
                    <div>
                        <div class="name">Chicken Crunch Mi</div>
                        <div class="desc">Indomie goreng, chicken breast, kulit pangsit, shallot chips</div>
                    </div>
                    <div class="price">17K</div>
                </div>

                <div class="menu-row">
                    <div>
                        <div class="name">Classic Fried Mi</div>
                        <div class="desc">Indomie goreng, telur, spesial seasoning</div>
                    </div>
                    <div class="price">16K</div>
                </div>

                <div class="menu-row">
                    <div>
                        <div class="name">Warm Soup Mi</div>
                        <div class="desc">Indomie rebus, telur, spesial seasoning</div>
                    </div>
                    <div class="price">15K</div>
                </div>

                <div class="menu-row">
                    <div>
                        <div class="name">Tom Yum Mi</div>
                        <div class="desc">Indomie soto kuah, tofu, chikuwa, jamur kuping, tomyum paste</div>
                    </div>
                    <div class="price">18K</div>
                </div>
            </div>
        </div>

    </div>

    <div class="footer">
        <span class="hashtag">#kembalikenamua</span>
        <span style="margin-right:8mm;">|</span>
        <span class="ig">◎</span>
        @namuacoffee
    </div>

</div>

</body>
</html>