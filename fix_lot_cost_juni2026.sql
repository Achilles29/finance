-- =========================================================
-- SQL FIX: Zero-cost lot & monthly_stock Juni 2026
-- Generated: 2026-06-16
-- 18 bahan bisa di-fix otomatis (cost diambil dari lot lain)
-- 6 bahan perlu input manual (tidak ada data cost sama sekali)
-- =========================================================

START TRANSACTION;

-- =========================================================
-- BAGIAN 1: Fix avg_cost_per_content di inv_division_monthly_stock
-- =========================================================

-- MINYAK GORENG (qty=4000, div=3/KITCHEN)
UPDATE inv_division_monthly_stock SET avg_cost_per_content = 23.150000 WHERE id = 834;

-- MAYONAISE (qty=2672.33, div=3/KITCHEN) — profile pertama
UPDATE inv_division_monthly_stock SET avg_cost_per_content = 29.500000 WHERE id = 2413;

-- KECAP MANIS (qty=1500, div=3/KITCHEN)
UPDATE inv_division_monthly_stock SET avg_cost_per_content = 25.333333 WHERE id = 833;

-- MAYONAISE (qty=1000, div=3/KITCHEN) — profile kedua
UPDATE inv_division_monthly_stock SET avg_cost_per_content = 29.500000 WHERE id = 2417;

-- GULA PASIR KITCHEN (qty=1000, div=3/KITCHEN)
UPDATE inv_division_monthly_stock SET avg_cost_per_content = 17.000000 WHERE id = 823;

-- ESPRESSO ARABIKA (qty=667, div=2/BAR)
UPDATE inv_division_monthly_stock SET avg_cost_per_content = 240.000000 WHERE id = 2747;

-- TOMAT (qty=500, div=3/KITCHEN) — profile pertama
UPDATE inv_division_monthly_stock SET avg_cost_per_content = 18.000000 WHERE id = 832;

-- MSG (qty=500, div=3/KITCHEN)
UPDATE inv_division_monthly_stock SET avg_cost_per_content = 56.000000 WHERE id = 831;

-- JAMUR ENOKI (qty=300, div=3/KITCHEN)
UPDATE inv_division_monthly_stock SET avg_cost_per_content = 40.000000 WHERE id = 824;

-- KEJU MOZARELLA (qty=250, div=3/KITCHEN)
UPDATE inv_division_monthly_stock SET avg_cost_per_content = 148.000000 WHERE id = 825;

-- SEREH (qty=43, div=2/BAR)
UPDATE inv_division_monthly_stock SET avg_cost_per_content = 24.000000 WHERE id = 2793;

-- SIRUP LYCHEE (qty=1, div=2/BAR) — profile pertama
UPDATE inv_division_monthly_stock SET avg_cost_per_content = 17.305735 WHERE id = 2003;

-- AYAM PAHA FILLET (qty=0.235, div=3/KITCHEN) — satuan gram
UPDATE inv_division_monthly_stock SET avg_cost_per_content = 0.008081 WHERE id = 2133;

-- SAUS TIRAM (qty=0.0475, div=3/KITCHEN) — satuan gram
UPDATE inv_division_monthly_stock SET avg_cost_per_content = 0.050781 WHERE id = 2485;

-- SMOKED BEEF (qty=0.0115, div=3/KITCHEN) — satuan gram
UPDATE inv_division_monthly_stock SET avg_cost_per_content = 0.127551 WHERE id = 2509;

-- TOMAT (qty=0.006, div=3/KITCHEN) — profile kedua (sisa kecil)
UPDATE inv_division_monthly_stock SET avg_cost_per_content = 17.000000 WHERE id = 2555;

-- KOL PUTIH (qty=0.005, div=3/KITCHEN) — satuan gram
UPDATE inv_division_monthly_stock SET avg_cost_per_content = 0.020000 WHERE id = 2365;

-- JAHE GAJAH (qty=0.0043, div=3/KITCHEN) — satuan gram
UPDATE inv_division_monthly_stock SET avg_cost_per_content = 0.112000 WHERE id = 2281;


-- =========================================================
-- BAGIAN 2: Fix unit_cost di inv_material_fifo_lot
--           (lot dengan balance > 0 tapi cost = 0)
-- =========================================================

-- MINYAK GORENG — Lot CORR-20260615-184221-M143 (bal=4000)
UPDATE inv_material_fifo_lot SET unit_cost = 23.150000 WHERE id = 1529;

-- MAYONAISE — Lot REPAIR-20260608 (bal=2672.325)
UPDATE inv_material_fifo_lot SET unit_cost = 29.500000 WHERE id = 1077;

-- KECAP MANIS — Lot CORR-20260615-184220-M108 (bal=1500)
UPDATE inv_material_fifo_lot SET unit_cost = 25.333333 WHERE id = 1517;

-- MAYONAISE — Lot CORR-20260615-184221-M140 (bal=1000)
UPDATE inv_material_fifo_lot SET unit_cost = 29.500000 WHERE id = 1527;

-- GULA PASIR KITCHEN — Lot CORR-20260615-184220-M76 (bal=1000)
UPDATE inv_material_fifo_lot SET unit_cost = 17.000000 WHERE id = 1513;

-- ESPRESSO ARABIKA — Lot CORR-20260615-211836-M229 (bal=667)
UPDATE inv_material_fifo_lot SET unit_cost = 240.000000 WHERE id = 1555;

-- TOMAT — Lot CORR-20260615-184221-M209 (bal=500)
UPDATE inv_material_fifo_lot SET unit_cost = 18.000000 WHERE id = 1533;

-- MSG — Lot CORR-20260615-184221-M146 (bal=500)
UPDATE inv_material_fifo_lot SET unit_cost = 56.000000 WHERE id = 1531;

-- JAMUR ENOKI — Lot CORR-20260615-184220-M92 (bal=300)
UPDATE inv_material_fifo_lot SET unit_cost = 40.000000 WHERE id = 1515;

-- KEJU MOZARELLA — Lot CORR-20260615-184220-M110 (bal=250)
UPDATE inv_material_fifo_lot SET unit_cost = 148.000000 WHERE id = 1519;

-- SEREH — Lot REPAIR-20260608 (bal=0.003, sisa kecil)
UPDATE inv_material_fifo_lot SET unit_cost = 24.000000 WHERE id = 1127;

-- SIRUP LYCHEE — Lot MATLOT-0000017334 (bal=1)
UPDATE inv_material_fifo_lot SET unit_cost = 17.305735 WHERE id = 135;

-- AYAM PAHA FILLET — Lot REPAIR-20260608 (bal=0.235)
UPDATE inv_material_fifo_lot SET unit_cost = 0.008081 WHERE id = 1085;

-- SAUS TIRAM — Lot REPAIR-20260608 (bal=0.0475)
UPDATE inv_material_fifo_lot SET unit_cost = 0.050781 WHERE id = 1125;

-- SMOKED BEEF — Lot REPAIR-20260608 (bal=0.0115)
UPDATE inv_material_fifo_lot SET unit_cost = 0.127551 WHERE id = 1129;

-- TOMAT — Lot REPAIR-20260608 (bal=0.006, sisa kecil)
UPDATE inv_material_fifo_lot SET unit_cost = 17.000000 WHERE id = 1133;

-- KOL PUTIH — Lot REPAIR-20260608 (bal=0.005)
UPDATE inv_material_fifo_lot SET unit_cost = 0.020000 WHERE id = 1111;

-- JAHE GAJAH — Lot REPAIR-20260608 (bal=0.0043)
UPDATE inv_material_fifo_lot SET unit_cost = 0.112000 WHERE id = 1103;


COMMIT;


-- =========================================================
-- PERLU INPUT MANUAL (6 bahan — tidak ada lot lain dengan cost)
-- Isi harga dari nota pembelian terakhir, lalu jalankan:
-- =========================================================

-- KERUPUK UDANG qty=750 div=3/KITCHEN_EVENT ms_id=1069
-- UPDATE inv_division_monthly_stock SET avg_cost_per_content = ??? WHERE id = 1069;
-- UPDATE inv_material_fifo_lot SET unit_cost = ??? WHERE profile_key = (SELECT profile_key FROM inv_division_monthly_stock WHERE id=1069);

-- SIRUP LYCHEE qty=100 div=2/BAR ms_id=2005
-- UPDATE inv_division_monthly_stock SET avg_cost_per_content = ??? WHERE id = 2005;

-- DRY BAY LEAF qty=36 div=3/KITCHEN ms_id=290
-- UPDATE inv_division_monthly_stock SET avg_cost_per_content = ??? WHERE id = 290;

-- BLACKPEPPER qty=25 div=2/BAR ms_id=6
-- UPDATE inv_division_monthly_stock SET avg_cost_per_content = ??? WHERE id = 6;

-- NANAS qty=8 div=2/BAR ms_id=78
-- UPDATE inv_division_monthly_stock SET avg_cost_per_content = ??? WHERE id = 78;

-- DAUN PISANG qty=6.97 div=3/KITCHEN ms_id=2247
-- UPDATE inv_division_monthly_stock SET avg_cost_per_content = ??? WHERE id = 2247;
