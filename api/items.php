<?php
/**
 * Items API Endpoints
 * Handles CRUD operations for purchase tracker items
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
        getItems();
        break;
    case 'POST':
        createItem($input);
        break;
    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true);
        updateItem($_GET['id'] ?? 0, $input);
        break;
    case 'DELETE':
        deleteItem($_GET['id'] ?? 0);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

function getItems() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT * FROM items ORDER BY created_at DESC");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $items]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch items: ' . $e->getMessage()]);
    }
}

function createItem($data) {
    global $pdo;
    
    $required_fields = ['name', 'purchase_date', 'purchase_price', 'purchase_location'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO items (name, purchase_date, purchase_price, purchase_location, purchase_notes, category, current_retail_price, quantity, condition, photo_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['name'],
            $data['purchase_date'],
            $data['purchase_price'],
            $data['purchase_location'],
            $data['purchase_notes'] ?? '',
            $data['category'] ?? '',
            $data['current_retail_price'] ?? 0,
            $data['quantity'] ?? 1,
            $data['condition'] ?? '',
            $data['photo_path'] ?? ''
        ]);
        
        $item_id = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $item_id, 'message' => 'Item created successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create item: ' . $e->getMessage()]);
    }
}

function updateItem($id, $data) {
    global $pdo;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Item ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE items SET name = ?, purchase_date = ?, purchase_price = ?, purchase_location = ?, purchase_notes = ?, category = ?, current_retail_price = ?, quantity = ?, condition = ?, photo_path = ? WHERE id = ?");
        $stmt->execute([
            $data['name'] ?? '',
            $data['purchase_date'] ?? '',
            $data['purchase_price'] ?? 0,
            $data['purchase_location'] ?? '',
            $data['purchase_notes'] ?? '',
            $data['category'] ?? '',
            $data['current_retail_price'] ?? 0,
            $data['quantity'] ?? 1,
            $data['condition'] ?? '',
            $data['photo_path'] ?? '',
            $id
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Item updated successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update item: ' . $e->getMessage()]);
    }
}

function deleteItem($id) {
    global $pdo;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Item ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM items WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete item: ' . $e->getMessage()]);
    }
}
