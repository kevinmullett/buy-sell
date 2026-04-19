<?php
/**
 * Sales API Endpoints
 * Handles recording sales and profit tracking
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
        getSales();
        break;
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        createSale($input);
        break;
    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true);
        updateSale($id ?? ($input['id'] ?? 0), $input);
        break;
    case 'DELETE':
        deleteSale($id ?? 0);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

function getSales() {
    global $pdo;

    try {
        $platform = $_GET['platform'] ?? null;
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;

        $sql = "SELECT s.*, i.name as item_name, i.purchase_price, i.category, i.purchase_location,
                (s.sale_price - i.purchase_price - s.shipping_cost) as profit,
                CASE WHEN s.sale_price > 0 THEN ROUND(((s.sale_price - i.purchase_price - s.shipping_cost) / s.sale_price) * 100, 1) ELSE 0 END as profit_margin,
                CAST(julianday(s.sale_date) - julianday(i.purchase_date) AS INTEGER) as days_to_sell
                FROM sales s 
                JOIN items i ON s.item_id = i.id 
                WHERE 1=1";
        $params = [];

        if ($platform) {
            $sql .= " AND s.sale_platform = ?";
            $params[] = $platform;
        }
        if ($startDate) {
            $sql .= " AND s.sale_date >= ?";
            $params[] = $startDate;
        }
        if ($endDate) {
            $sql .= " AND s.sale_date <= ?";
            $params[] = $endDate;
        }

        $sql .= " ORDER BY s.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $sales, 'count' => count($sales)]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch sales: ' . $e->getMessage()]);
    }
}

function createSale($data) {
    global $pdo;

    $required_fields = ['item_id', 'sale_date', 'sale_price', 'sale_platform'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || trim(strval($data[$field])) === '') {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    try {
        // Verify item exists
        $itemCheck = $pdo->prepare("SELECT id FROM items WHERE id = ?");
        $itemCheck->execute([$data['item_id']]);
        if (!$itemCheck->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Item not found']);
            return;
        }

        $stmt = $pdo->prepare("INSERT INTO sales (item_id, sale_date, sale_price, sale_platform, sale_location, sale_notes, packing_method, shipping_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            intval($data['item_id']),
            $data['sale_date'],
            floatval($data['sale_price']),
            sanitizeInput($data['sale_platform']),
            sanitizeInput($data['sale_location'] ?? ''),
            sanitizeInput($data['sale_notes'] ?? ''),
            sanitizeInput($data['packing_method'] ?? ''),
            floatval($data['shipping_cost'] ?? 0)
        ]);

        // Update item status to Sold
        $updateStmt = $pdo->prepare("UPDATE items SET status = 'Sold', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $updateStmt->execute([$data['item_id']]);

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
        $fields = [];
        $params = [];
        $allowed = ['item_id', 'sale_date', 'sale_price', 'sale_platform', 'sale_location', 'sale_notes', 'packing_method', 'shipping_cost'];

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

        $params[] = $id;
        $sql = "UPDATE sales SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

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
        // Get the item_id before deleting
        $saleStmt = $pdo->prepare("SELECT item_id FROM sales WHERE id = ?");
        $saleStmt->execute([$id]);
        $sale = $saleStmt->fetch();

        $stmt = $pdo->prepare("DELETE FROM sales WHERE id = ?");
        $stmt->execute([$id]);

        // If no more sales for this item, set status back to Available
        if ($sale) {
            $remainingSales = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE item_id = ?");
            $remainingSales->execute([$sale['item_id']]);
            if ($remainingSales->fetchColumn() == 0) {
                $pdo->prepare("UPDATE items SET status = 'Available', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$sale['item_id']]);
            }
        }

        echo json_encode(['success' => true, 'message' => 'Sale deleted successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete sale: ' . $e->getMessage()]);
    }
}