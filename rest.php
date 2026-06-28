<?php
require __DIR__ . '/login_check.php';
header('Content-type: application/json');
//$data = file_get_contents ("dinamic-products.json");
$data = file_get_contents ("uploads/datoteka.json");
$json = json_decode($data, true);
echo $data;//json_encode($json);
?>
