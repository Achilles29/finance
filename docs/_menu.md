revisi konsep buku menu:

- tidak semua produk ada fotonya, nanti kita tentukan mana yang pakai foto saat generate prompt per kategori
- background sesuai nama kategori
- 1 halaman bisa beberapa kategori

kategori:
divisi food:
    MAIN CHARACTER
    INDONESIAN HERITAGE
    CRAVE CORNER
    ASIAN COURSE
    MUNCH & MEAT
    CLASSIC WESTERN
    RICE BOWL
    SPICY
    ANAK KOS CORE (INDOMIE)
    SNACK & BITES
    DESSERT
    CARBO
    OTHER CONDIMENT
    SAUCE,SAMBAL & MAYO


oke saya tentukan dulu:

halaman 1 - cover
halaman 2 - main-character 6 product, 6 gambar
halaman 3 - INDONESIAN HERITAGE (6 product, 6 gambar) dan CRAVE CORNER (7 product, 7 gambar), buatkan nama halaman yang pas 
halaman 4 - MUNCH & MEAT (5 product, 5 gambar) dan CLASSIC WESTERN (7 product, 7 gambar)  
halaman 5 - ASIAN COURSE 13 product, 13 gambar
halaman 6 - SPICY 5 product 2 gambar, RICE BOWL 4 product 1 gambar, Anak Kos Core (INDOMIE) 4 product 1 gambar
halaman 7 - SNACK & BITES , 19 product - sub category DIM SUM (3 product 2 gambar), sub category "gurih / asin" => buatkan nama category yang bagus dan relevan ( 11 product, 6 gambar), sub category "manis" => buatkab nama category yang bagus dan relevan (3 product, 5 product, 3 gambar , PINOKIO, PIKAMEEL ,  ROTI BAKAR (ROTI BAKAR COKLAT, ROTI BAKAR KEJU, ROTI BAKAR COKLAT + KEJU))
halaman 8 - DESSERT 5 product 5 gambar
halaman 9 -    CARBO (9 product),  OTHER CONDIMENT (10 product), SAUCE,SAMBAL & MAYO (4 product) tanpa gambar semua



beverage:
    Signature Coffee, 
    Masterpiece Line
    Classic Coffee
    Manual Brew
    Cold Brew Series
    Favorite Grandma
    LATTE SERIES
    Artisan Tea
    Tea Series
    Mocktail
    Refreshing Drinks
    Blend & Smoothies
    Sweet & Milk Series
    Wedangan
    Ice Cream
    Beverage Add-On

============================

# NAMUA MENU BOOK PROJECT

## Overview

Proyek pembuatan buku menu digital dan cetak untuk NAMUA Coffee & Eatery.

Output utama:

1. Buku menu cetak ukuran A4 Portrait
2. Halaman menu digital di aplikasi Finance (CodeIgniter)
3. Struktur asset yang dapat digunakan ulang untuk promosi, katalog, dan website

---

# Objectives

Menu harus:

* Premium
* Modern
* Konsisten
* Mudah diperbarui
* Mobile Friendly
* Print Friendly
* Reusable

Bukan sekadar daftar produk.

Menu harus terasa seperti:

* Premium Cafe
* Premium Eatery
* Restaurant Menu Book
* Editorial Food Magazine

---

# Technical Stack

## Backend

CodeIgniter 3

## Frontend

HTML
CSS

Tanpa:

* Bootstrap
* Tailwind
* CDN External

Agar:

* ringan
* mudah dipindah server
* mudah dicetak

---

# Asset Structure

```text
assets/menu-book/

├── logo/
│   └── logo.png
│
├── products/
│   ├── foods/
│   │   ├── main-character/
│   │   ├── indonesian-heritage/
│   │   ├── crave-corner/
│   │   ├── munch-meat/
│   │   ├── classic-western/
│   │   ├── asian-course/
│   │   ├── spicy/
│   │   ├── rice-bowl/
│   │   ├── anak-kos-core/
│   │   ├── snack-bites/
│   │   ├── dessert/
│   │   └── others/
│   │
│   └── beverages/
│
├── backgrounds/
│
└── css/
    └── menu-book.css
```

---

# Design Principles

## Layout

A4 Portrait

```text
210mm x 297mm
```

---

## Color Palette

Primary

```text
Maroon
#7D1F1F
```

Secondary

```text
Coffee Brown
#6A4E3A
```

Accent

```text
Champagne Gold
#C9A86A
```

Background

```text
Warm Ivory
#F8F1E8
```

---

## Typography

Kategori

```text
Bold
Uppercase
```

Nama Produk

```text
Semi Bold
```

Harga

```text
Bold
Highlight
```

---

# Food Menu Structure

## Page 1

Cover

---

## Page 2

MAIN CHARACTER

Title:

The Icons of NAMUA

Products:

* Namua Sultan Rice
* Bebek Bumbu Ireng Madura
* Bebek Songkem Namua
* Ayam Songkem Namua
* Arabian Grill Lamb Chop
* Spicy Tongseng Lamb Chop

Badge:

BEST SELLER

* Namua Sultan Rice
* Bebek Bumbu Ireng Madura
* Bebek Songkem Namua

---

## Page 3

NUSANTARA & COMFORT FOOD

Categories:

* Indonesian Heritage
* Crave Corner

---

## Page 4

FLAME & FLAVOR

Categories:

* Munch & Meat
* Classic Western

---

## Page 5

ASIAN SIGNATURES

Categories:

* Asian Course

---

## Page 6

BOWL, SPICE & NOODLES

Categories:

* Spicy
* Rice Bowl
* Anak Kos Core

---

## Page 7

BITES OF JOY

Categories:

* Dim Sum
* Crispy Bites
* Sweet Treats

---

## Page 8

DESSERT COLLECTION

Categories:

* Dessert

---

## Page 9

EXTRAS & SIDES

Categories:

* Carbo
* Other Condiment
* Sauce Sambal Mayo

---

# Pricing Convention

Produk tanpa nasi:

```text
A LA CARTE
```

Produk dengan nasi:

```text
RICE SET
```

---

Ukuran khusus:

```text
CLASSIC
FEAST
```

Contoh:

Bebek Bumbu Ireng Madura

CLASSIC

* A LA CARTE 41K
* RICE SET 43K

FEAST

* A LA CARTE 58K
* RICE SET 60K

---

# Background Rules

Setiap halaman memiliki background berbeda.

## Main Character

Luxury Food
Coffee & Grill
Warm Editorial

## Heritage

Traditional Indonesian
Rempah Nusantara

## Flame & Flavor

Grill
Smoke
Western Cuisine

## Asian Signatures

Japanese Korean
Minimal Asian

## Bites of Joy

Coffee Shop
Casual Sharing Food

## Dessert

Soft Sweet
Luxury Dessert

## Extras

Minimal Clean

---

# Development Workflow

Untuk setiap halaman:

## Step 1

Tentukan kategori

## Step 2

Tentukan produk

## Step 3

Tentukan foto yang digunakan

## Step 4

Tentukan background

## Step 5

Buat wireframe

## Step 6

Buat HTML

## Step 7

Buat CSS

## Step 8

Review di browser

## Step 9

Optimasi untuk print

---

# Current Progress

Completed:

* Struktur proyek
* Struktur asset
* Food menu planning
* Page naming
* Main Character content
* Main Character first implementation

Next:

* Finalisasi layout Main Character
* Halaman 3 (Nusantara & Comfort Food)
* Halaman 4 (Flame & Flavor)
* Halaman 5 (Asian Signatures)
* Halaman 6 (Bowl, Spice & Noodles)
* Halaman 7 (Bites of Joy)
* Halaman 8 (Dessert Collection)
* Halaman 9 (Extras & Sides)



MAIN CHARACTER

NAMUA SULTAN RICE :Grillprawn, chicken satelilit, rendang beef shortplate, garlic kemangi rice, mendoan, abon & sambal matah.
BEBEK BUMBU IRENG MADURA :Slow cook 6 hour duck boneless, bumbu ireng paste madura, sambal matah.
    
BEBEK SONGKEM NAMUA : Slow steamed 6 hour duck, with banana leaves and secret spices, combined with base gede seasoning.
AYAM SONGKEM NAMUA : Slow steamed 6 hour chicken, with banana leaves and secret spices, combined with base gede seasoning.
ARABIAN GRILL LAMB CHOP :Middle east cuisine pan grill lamb chop, tar-tar mayo & fresh salad onion.
SPICY TONGSENG LAMB CHOP :Pan grill lamb chop, spicy tongseng broth, cabbage served with rice & condiment side dish.


KATEGORI	NAMA PRODUK	DESKRIPSI	TANPA NASI	DENGAN NASI	HARGA
INDONESIAN HERITAGE	SUP IGA REMPAH	8 hour slow cook beef short ribs, clear beef broth, potato, carrot, emping crackers & sambal bawang	46000	48000	
INDONESIAN HERITAGE	IGA BAKAR MADU	grill beef short ribs glazed bumbu bakar madu, abon sapi, emping crackers, sambal matah & dabu-dabu, beef broth	50000	52000	
INDONESIAN HERITAGE	MIE ACEH BEEF SHORTPLATE	noodles, spicy paste kaldu, beef shortplate, bean sprout, emping crackers, katsuobushi			43000
INDONESIAN HERITAGE	SOTO BETAWI	beef milk broth, potato, tomato, lime, sambal & emping crackers	48000	50000	
INDONESIAN HERITAGE	NASI CAMPUR BALI	base gede rice, chicken sate lilit, chicken shredded bali, lawar, sambal matah & sunny side up egg			29000
INDONESIAN HERITAGE	NASI GORENG KAMPOENG	bumbu rempah paste “terasi” fried rice, sunny side up, chicken skewer & crackers			23000
CRAVE CORNER	BEBEK GORENG BUMBU REMPAH	slow cook 6 hour duck marinated, serundeng, sambal idjo & sambal tomat	41000	43000	
CRAVE CORNER	BEEF LOMBOK IJO	grill beef shortplate glazed lombok idjo			32000
CRAVE CORNER	GRILL BEEF SHORTPLATE PARAPE	grill beef shortplate glazed bumbu bakar parape, sambal matah, garlic kemangi rice			31000
CRAVE CORNER	GRILL SEAFOOD PLATTER PANTURA	prawn, smoked fish parape, grill squid, nasi kemangi & sambal matah			31000
CRAVE CORNER	AYAM BUMBU IRENG	chicken marinated, bumbu ireng, sambal tomat, serundeng	31000	33000	
CRAVE CORNER	AYAM BAKAR SAMBAL DABU-DABU	chicken marinated, sambal dabu-dabu, fresh lalapan	26000	28000	
CRAVE CORNER	AYAM GORENG REMPAH KREMES	chicken marinated, sambal bawang, fresh lalapan	23000	25000	







DRY-RUB PAPPER STEAK		65.000,00		SIRLOIN MELTIQUE, HAND CUT FRIES, MAC N CHEESE, COLE SLAW, BBQ SAUCE 
US SMOKED BRISKET		50.000,00		BEEF SHORTPLATE, HAND CUT FRIES, MAC N CHEESE, COLE SLAW, BBQ SAUCE 
SOUTHERN STYLE FRIED CHICKEN		40.000,00		CHICKEN BREAST, MIX FLOUR, HAND CUT FRIES, MAC N CHEESE, COLE SLAW, BBQ SAUCE
NAMUA SIGNATURE BURGER		30.000,00		BEEF PATTY WITH ORIGINAL FRENCH FRIES, CHILI SAUCE & TOMATO SAUCE
NAMUA CRUNCH BURGER		28.000,00		Chicken katsu with original French fries , chilli sauce & tomato sauce
CHICKEN CORDON BLEU		32.000,00		CHICKEN BREAST ROLL FILL SMOKED BEEF & MOZZARELLA CHEESE, MIX SALAD, BARBEQUE SAUCE & CHEESE SAUCE
FISH & CHIPS		28.000,00		DEEP FRY DORY FISH, HAND CUT FRIES & TAR-TAR MAYO
SIRLOIN STEAK MELTIQUE		90.000,00		SIRLOIN MELTIQUE BLUE LABEL, HAND CUT FRIES, SALAD & DRESSING, BARBEQUE SAUCE
SPAGHETTI AGLIO E O LIO CON POLLO		25.000,00		GARLIC & PAPRIKA AROMATIC, DEEP FRY CHICKEN
SPAGHETTI ALFREDO DI CREMA		32.000,00		CREAMY & CHEESE BASE WITH SMOKED BEEF
SPAGHETTI BOLOGNESE		28.000,00		MIX BEEF RAGU BOLOGNESE & CHEESE ON TOP
SPAGHETTI DORY BALINESE		25.000,00		SPAGHETTI, CHILLI FLAKES, GARLIC, WHITE PEPPER, DORY, KEMANGI, GARLIC CHIPS

gambar highlight sesuai yang saya kirim. coba dulu


DRY-RUB PEPPER STEAK, US SMOKED BRISKET, dan SOUTHERN STYLE FRIED CHICKEN => tray 3 makanan + mac n cheese
NAMUA SIGNATURE BURGER	NAMUA SIGNATURE BURGER.png
NAMUA CRUNCH BURGER	burger background maroon
CHICKEN CORDON BLEU	IMG_8082
FISH & CHIPS	FISH N CHIP
SPAGHETTI AGLIO E OLIO CON POLLO	pasta trio (kanan atas)
SPAGHETTI ALFREDO DI CREMA	pasta trio (kiri bawah)
SPAGHETTI BOLOGNESE	pasta trio (kanan bawah)
SPAGHETTI DORY BALINESE	spageti dory.png
SIRLOIN STEAK MELTIQUE	steak.png