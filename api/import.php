<?php
/**
 * CSV Import/Export API Endpoints
 * Handles importing purchases and sales from CSV files
 */

require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        $type = $input['type'] ?? 'purchase';
        $file = $_FILES['file']['tmp_name'] ?? null;
        
        if (!$file) {
            http_response_code(400);
            echo json_encode(['error' => 'No file uploaded']);
            exit;
        }
        
        if ($type === 'purchase') {
            importPurchasesCSV($file);
        } else {
            importSalesCSV($file);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

function importPurchasesCSV($filePath) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new Exception('Failed to open file');
        }
        
        // Skip header row
        fgetcsv($handle);
        
        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        
        while (($row = fgetcsv($handle)) !== false) {
            $data = [
                'name' => $row[0] ?? '',
                'purchase_date' => $row[1] ?? '',
                'purchase_price' => $row[2] ?? 0,
                'purchase_location' => $row[3] ?? '',
                'purchase_notes' => $row[4] ?? '',
                'category' => $row[5] ?? '',
                'current_retail_price' => $row[6] ?? 0,
                'quantity' => $row[7] ?? 1,
                'condition' => $row[8] ?? '',
                'photo_path' => $row[9] ?? ''
            ];
            
            $errors = validateCsvData($data, 'purchase');
            if (!empty($errors)) {
                $errorCount++;
                $errors[] = "Row " . ($successCount + $errorCount + 1) . ": " . implode(', ', $errors);
                continue;
            }
            
            $stmt = $pdo->prepare("INSERT INTO items (name, purchase_date, purchase_price, purchase_location, purchase_notes, category, current_retail_price, quantity, condition, photo_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['name'],
                $data['purchase_date'],
                $data['purchase_price'],
                $data['purchase_location'],
                $data['purchase_notes'],
                $data['category'],
                $data['current_retail_price'],
                $data['quantity'],
                $data['condition'],
                $data['photo_path']
            ]);
            
            $successCount++;
        }
        
        fclose($handle);
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Import completed: $successCount items added, $errorCount errors",
            'errors' => $errors
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Import failed: ' . $e->getMessage()]);
    }
}

function importSalesCSV($filePath) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new Exception('Failed to open file');
        }
        
        // Skip header row
        fgetcsv($handle);
        
        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        
        while (($row = fgetcsv($handle)) !== false) {
            $data = [
                'item_id' => $row[0] ?? '',
                'sale_date' => $row[1] ?? '',
                'sale_price' => $row[2] ?? 0,
                'sale_platform' => $row[3] ?? '',
                'sale_location' => $row[4] ?? '',
                'sale_notes' => $row[5] ?? '',
                'packing_method' => $row[6] ?? '',
                'shipping_cost' => $row[7] ?? 0
            ];
            
            $errors = validateCsvData($data, 'sale');
            if (!empty($errors)) {
                $errorCount++;
                $errors[] = "Row " . ($successCount + $errorCount + 1) . ": " . implode(', ', $errors);
                continue;
            }
            
            $stmt = $pdo->prepare("INSERT INTO sales (item_id, sale_date, sale_price, sale_platform, sale_location, sale_notes, packing_method, shipping_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['item_id'],
                $data['sale_date'],
                $data['sale_price'],
                $data['sale_platform'],
                $data['sale_location'],
                $data['sale_notes'],
                $data['packing_method'],
                $data['shipping_cost']
            ]);
            
            $successCount++;
        }
        
        fclose($handle);
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Import completed: $successCount sales recorded, $errorCount errors",
            'errors' => $errors
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Import failed: ' . $e->getMessage()]);
    }
}