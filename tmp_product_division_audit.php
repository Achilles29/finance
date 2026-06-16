<?php
$mysqli = new mysqli('127.0.0.1','root','','db_finance');
if ($mysqli->connect_errno) { fwrite(STDERR, $mysqli->connect_error); exit(1);} 
$sql = "SELECT p.id, p.product_code, p.product_name, p.default_operational_division_id, od.code AS default_division_code, GROUP_CONCAT(DISTINCT rod.code ORDER BY rod.code SEPARATOR ',') AS recipe_divisions FROM mst_product p JOIN mst_product_recipe r ON r.product_id = p.id AND r.source_division_id IS NOT NULL LEFT JOIN mst_operational_division od ON od.id = p.default_operational_division_id LEFT JOIN mst_operational_division rod ON rod.id = r.source_division_id GROUP BY p.id, p.product_code, p.product_name, p.default_operational_division_id, od.code HAVING recipe_divisions <> '' AND (default_division_code IS NULL OR FIND_IN_SET(default_division_code, recipe_divisions) = 0) ORDER BY p.id LIMIT 100";
$res = $mysqli->query($sql);
while($row=$res->fetch_assoc()){print_r($row);} 
?>
