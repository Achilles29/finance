<?php
$mysqli = new mysqli('127.0.0.1','root','','db_finance');
if ($mysqli->connect_errno) { fwrite(STDERR, $mysqli->connect_error); exit(1);} 
$sql = "SELECT p.id, p.product_code, p.product_name, pd.name AS product_division_name, od.code AS default_division_code, od.name AS default_division_name FROM mst_product p LEFT JOIN mst_product_division pd ON pd.id=p.product_division_id LEFT JOIN mst_operational_division od ON od.id=p.default_operational_division_id WHERE p.product_name LIKE '%RUM DMC%' OR p.product_code LIKE '%RUM%DMC%'";
$res = $mysqli->query($sql);
while($row=$res->fetch_assoc()){print_r($row);} 
?>
