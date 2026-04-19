<?php
/**
 * Packaging Options API
 */
require_once __DIR__ . '/../database.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $pdo->query("SELECT name FROM packaging_options ORDER BY name")->fetchAll();
    echo json_encode(['success' => true, 'data' => array_column($rows, 'name')]);

} elseif ($method === 'POST') {
    // Add a new custom packaging option
    $input = json_decode(file_get_contents('php://input'), true);
    $name  = trim($input['name'] ?? '');
    if (!$name) { http_response_code(400); echo json_encode(['error'=>'Name required']); exit; }
    $pdo->prepare("INSERT OR IGNORE INTO packaging_options (name) VALUES (?)")->execute([$name]);
    echo json_encode(['success' => true]);
}
