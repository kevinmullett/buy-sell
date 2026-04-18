<?php
/**
 * Sales API Endpoints
 * Handles recording sales and generating profit reports
 */

require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        getSales();
        break;
    case 'POST':
        createSale($input);
        break;
    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true);
        updateSale($_GET['id'] ?? 0, $input);
        break;
    case 'DELETE':
        deleteSale($_GET['id'] ?? 0);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

function getSales() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT s.*, i.name as item_name FROM sales s JOIN items i ON s.item_id = i.id ORDER BY s.created_at DESC");
        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $sales]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch sales: ' . $e->getMessage()]);
    }
}

function createSale($data) {
    global $pdo;
    
    $required_fields = ['item_id', 'sale_date', 'sale_price', 'sale_platform'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO sales (item_id, sale_date, sale_price, sale_platform, sale_location, sale_notes, packing_method, shipping_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['item_id'],
            $data['sale_date'],
            $data['sale_price'],
            $data['sale_platform'],
            $data['sale_location'] ?? '',
            $data['sale_notes'] ?? '',
            $data['packing_method'] ?? '',
            $data['shipping_cost'] ?? 0
        ]);
        
        $sale_id = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $sale_id, 'message' => 'Sale recorded successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to record sale: ' . $e->getMessage()]);
    }
}

function updateSale($id, $data) {
    global $pdo;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Sale ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE sales SET item_id = ?, sale_date = ?, sale_price = ?, sale_platform = ?, sale_location = ?, sale_notes = ?, packing_method = ?, shipping_cost = ? WHERE id = ?");
        $stmt->execute([
            $data['item_id'] ?? '',
            $data['sale_date'] ?? '',
            $data['sale_price'] ?? 0,
            $data['sale_platform'] ?? '',
            $data['sale_location'] ?? '',
            $data['sale_notes'] ?? '',
            $data['packing_method'] ?? '',
            $data['shipping_cost'] ?? 0,
            $id
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Sale updated successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update sale: ' . $e->getMessage()]);
    }
}

function deleteSale($id) {
    global $pdo;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Sale ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM sales WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Sale deleted successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete sale: ' . $e->getMessage()]);
    }
}

function getSalesByDateRange($startDate, $endDate) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT s.*, i.name as item_name FROM sales s JOIN items i ON s.item_id = i.id WHERE s.sale_date BETWEEN ? AND ? ORDER BY s.sale_date");
        $stmt->execute([$startDate, $endDate]);
        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $sales]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch sales: ' . $e->getMessage()]);
    }
}

function getSalesByPlatform($platform) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM sales WHERE sale_platform = ? ORDER BY created_at DESC");
        $stmt->execute([$platform]);
        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $sales]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch sales: ' . $e->getMessage()]);
    }
}

function getSalesByItem($itemId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM sales WHERE item_id = ? ORDER BY created_at DESC");
        $stmt->execute([$itemId]);
        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $sales]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch sales: ' . $e->getMessage()]);
    }
}