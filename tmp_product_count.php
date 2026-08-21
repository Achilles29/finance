<?php
$mysqli = new mysqli('127.0.0.1','root','','db_finance');
if ($mysqli->connect_errno) { fwrite(STDERR, $mysqli->connect_error); exit(1);} 
$sql = "SELECT COUNT(*) AS total_products, SUM(CASE WHEN default_operational_division_id IS NULL THEN 1 ELSE 0 END) AS null_default FROM mst_product";
$res = $mysqli->query($sql);
print_r($res->fetch_assoc());
?>
