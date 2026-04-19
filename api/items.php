<?php
/**
 * Items API Endpoints
 * Handles CRUD operations for purchase tracker items
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

switch ($method) {
    case 'GET':
        if ($id) {
            getItem($id);
        } else {
            getItems();
        }
        break;
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        createItem($input);
        break;
    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true);
        updateItem($id ?? ($input['id'] ?? 0), $input);
        break;
    case 'DELETE':
        deleteItem($id ?? 0);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

function getItems() {
    global $pdo;

    try {
        $category = $_GET['category'] ?? null;
        $status = $_GET['status'] ?? null;
        $search = $_GET['search'] ?? null;

        $sql = "SELECT i.*, 
                (SELECT COUNT(*) FROM sales s WHERE s.item_id = i.id) as sale_count,
                (SELECT SUM(s.sale_price) FROM sales s WHERE s.item_id = i.id) as total_sold
                FROM items i WHERE 1=1";
        $params = [];

        if ($category) {
            $sql .= " AND i.category = ?";
            $params[] = $category;
        }
        if ($status) {
            $sql .= " AND i.status = ?";
            $params[] = $status;
        }
        if ($search) {
            $sql .= " AND (i.name LIKE ? OR i.purchase_location LIKE ? OR i.category LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " ORDER BY i.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $items, 'count' => count($items)]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch items: ' . $e->getMessage()]);
    }
}

function getItem($id) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("SELECT i.*, 
            (SELECT COUNT(*) FROM sales s WHERE s.item_id = i.id) as sale_count,
            (SELECT SUM(s.sale_price) FROM sales s WHERE s.item_id = i.id) as total_sold
            FROM items i WHERE i.id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            http_response_code(404);
            echo json_encode(['error' => 'Item not found']);
            return;
        }

        // Get sales for this item
        $salesStmt = $pdo->prepare("SELECT * FROM sales WHERE item_id = ? ORDER BY sale_date DESC");
        $salesStmt->execute([$id]);
        $item['sales'] = $salesStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $item]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch item: ' . $e->getMessage()]);
    }
}

function createItem($data) {
    global $pdo;

    $required_fields = ['name', 'purchase_date', 'purchase_price'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO items (name, purchase_date, purchase_price, purchase_location, purchase_type, purchase_notes, category, current_retail_price, quantity, condition, status, photo_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            sanitizeInput($data['name']),
            $data['purchase_date'],
            floatval($data['purchase_price']),
            sanitizeInput($data['purchase_location'] ?? ''),
            sanitizeInput($data['purchase_type'] ?? 'Standard'),
            sanitizeInput($data['purchase_notes'] ?? ''),
            sanitizeInput($data['category'] ?? ''),
            floatval($data['current_retail_price'] ?? 0),
            intval($data['quantity'] ?? 1),
            sanitizeInput($data['condition'] ?? 'Good'),
            'Available',
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
        // Build dynamic update query
        $fields = [];
        $params = [];

        $allowed = ['name', 'purchase_date', 'purchase_price', 'purchase_location', 'purchase_type',
                     'purchase_notes', 'category', 'current_retail_price', 'quantity', 'condition', 'status', 'photo_path'];

        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            return;
        }

        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $id;

        $sql = "UPDATE items SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

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
