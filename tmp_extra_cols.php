<?php
$mysqli = new mysqli('127.0.0.1','root','','db_finance');
if ($mysqli->connect_errno) { fwrite(STDERR, $mysqli->connect_error); exit(1);} 
$sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='db_finance' AND TABLE_NAME='mst_extra' ORDER BY ORDINAL_POSITION";
$res = $mysqli->query($sql);
while($row=$res->fetch_assoc()){echo $row['COLUMN_NAME'], PHP_EOL;}
?>
