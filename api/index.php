<?php
/**
 * API Router/Entry Point
 * Routes requests to appropriate API handlers
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$request = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Simple routing
if (preg_match('#^/api/items(/(\d+))?$#', $request, $matches)) {
    require 'items.php';
} elseif (preg_match('#^/api/sales(/(\d+))?$#', $request, $matches)) {
    require 'sales.php';
} elseif (preg_match('#^/api/reports(/(\d+))?$#', $request, $matches)) {
    require 'reports.php';
} elseif (preg_match('#^/api/photos(/(\d+))?$#', $request, $matches)) {
    require 'photos.php';
} elseif (preg_match('#^/api/import$#', $request, $matches)) {
    require 'import.php';
} else {
    http_response_code(404);
    echo json_encode(['error' => 'API endpoint not found']);
}

// Close connection and continue processing (for async operations)
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}