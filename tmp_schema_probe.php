<?php
$mysqli = new mysqli('127.0.0.1','root','','db_finance');
if ($mysqli->connect_errno) { fwrite(STDERR, $mysqli->connect_error); exit(1);} 
foreach (['pos_stock_commit_line','mst_product','mst_product_recipe','mst_operational_division'] as $table) {
  echo "--- $table ---\n";
  $res = $mysqli->query("SHOW COLUMNS FROM $table");
  while($row=$res->fetch_assoc()){ echo $row['Field'], ' | ', $row['Type'], PHP_EOL; }
}
?>
