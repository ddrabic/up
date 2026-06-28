<?php
require __DIR__ . '/login_check.php';
// biblioteka vlastitih funkcija
require_once __DIR__ . "/lib/upplib.php";

// PRIPREMA JSON datoteke
$json_data=parse_json(__DIR__ . "/uploads/datoteka.json");
if ( empty($json_data) || !is_array($json_data)):
    $result=['kodRobe:'=>'Greška','Naziv'=>'Datoteka nije učitana'];
    die('Datoteka nije učitana');
else:
    $result=array();
    foreach($json_data as $k => $obj):
        array_push($result, $obj);
    endforeach;
endif;
// Get all product attributes from JSON
echo json_encode($result);
?>
