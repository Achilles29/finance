<?php
$mysqli = new mysqli('127.0.0.1','root','','db_finance');
if ($mysqli->connect_errno) { fwrite(STDERR, $mysqli->connect_error); exit(1);} 
foreach (['pos_runtime_job'] as $table) {
 echo "--- $table ---\n";
 $res = $mysqli->query("SHOW COLUMNS FROM $table");
 while($row=$res->fetch_assoc()){ echo $row['Field'], ' | ', $row['Type'], PHP_EOL; }
}
$sql = "SELECT id, order_id, snapshot_id, status, attempts, max_attempts, last_error, payload_json FROM pos_runtime_job WHERE order_id IN (559,533,443,439,395,341,337,319,307) ORDER BY id DESC";
$res = $mysqli->query($sql);
while($row=$res->fetch_assoc()){print_r($row);} 
?>
