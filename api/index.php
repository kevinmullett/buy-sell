<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$request = $_SERVER['REQUEST_URI'];

if (preg_match('#^/api/(items|sales|reports|photos|import_inventory|import|categories|locations|packaging|ebay_import|statement_import)\b#', $request, $m)) {
    $file = __DIR__ . '/' . $m[1] . '.php';
    file_exists($file) ? require $file : (http_response_code(404) && print json_encode(['error'=>"Endpoint '{$m[1]}' not found"]));
} else {
    http_response_code(404);
    echo json_encode(['error'=>'Not found','available'=>['items','sales','reports','photos','import','categories','locations','packaging','ebay_import','statement_import','import_inventory']]);
}