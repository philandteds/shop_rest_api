<?php

$module = $Params['Module'];

$longCode = $_POST['longcode'];

header('Content-Type: application/json; charset=utf-8');

$json = false;
try {
    $json = ptStockLevelQuery::getProductStock($longCode);

    $json['longcode'] = $longCode;

} catch(Exception $e) {
    $json = array("error-message" => $e->getMessage());
    http_response_code(500);
}

print_r(json_encode($json));
eZExecution::cleanExit();
