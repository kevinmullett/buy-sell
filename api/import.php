<?php
/**
 * CSV Import API Endpoint
 * Handles importing purchases and sales from CSV files
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$type = $_POST['type'] ?? $_GET['type'] ?? 'purchase';
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

function importPurchasesCSV($filePath) {
    global $pdo;

    try {
        $pdo->beginTransaction();

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new Exception('Failed to open file');
        }

        // Read header row
        $headers = fgetcsv($handle);
        if (!$headers) {
            throw new Exception('Empty CSV file');
        }

        // Normalize headers
        $headers = array_map(function($h) { return strtolower(trim($h)); }, $headers);

        $successCount = 0;
        $errorCount = 0;
        $importErrors = [];
        $lineNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNum++;
            
            // Map CSV columns to data fields
            $data = [];
            foreach ($headers as $i => $header) {
                $data[$header] = $row[$i] ?? '';
            }

            // Validate
            $validationErrors = validateCsvData($data, 'purchase');
            if (!empty($validationErrors)) {
                $errorCount++;
                $importErrors[] = "Row $lineNum: " . implode(', ', $validationErrors);
                continue;
            }

            $stmt = $pdo->prepare("INSERT INTO items (name, purchase_date, purchase_price, purchase_location, purchase_notes, category, current_retail_price, quantity, condition, photo_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Available')");
            $stmt->execute([
                $data['name'] ?? '',
                $data['purchase_date'] ?? date('Y-m-d'),
                floatval($data['purchase_price'] ?? 0),
                $data['purchase_location'] ?? '',
                $data['purchase_notes'] ?? '',
                $data['category'] ?? '',
                floatval($data['current_retail_price'] ?? 0),
                intval($data['quantity'] ?? 1),
                $data['condition'] ?? 'Good',
                $data['photo_path'] ?? ''
            ]);

            $successCount++;
        }

        fclose($handle);
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Import completed: $successCount items added, $errorCount errors",
            'imported' => $successCount,
            'errors_count' => $errorCount,
            'errors' => $importErrors
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
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

        $headers = fgetcsv($handle);
        if (!$headers) {
            throw new Exception('Empty CSV file');
        }
        $headers = array_map(function($h) { return strtolower(trim($h)); }, $headers);

        $successCount = 0;
        $errorCount = 0;
        $importErrors = [];
        $lineNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNum++;

            $data = [];
            foreach ($headers as $i => $header) {
                $data[$header] = $row[$i] ?? '';
            }

            $validationErrors = validateCsvData($data, 'sale');
            if (!empty($validationErrors)) {
                $errorCount++;
                $importErrors[] = "Row $lineNum: " . implode(', ', $validationErrors);
                continue;
            }

            $stmt = $pdo->prepare("INSERT INTO sales (item_id, sale_date, sale_price, sale_platform, sale_location, sale_notes, packing_method, shipping_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                intval($data['item_id']),
                $data['sale_date'],
                floatval($data['sale_price'] ?? 0),
                $data['sale_platform'] ?? '',
                $data['sale_location'] ?? '',
                $data['sale_notes'] ?? '',
                $data['packing_method'] ?? '',
                floatval($data['shipping_cost'] ?? 0)
            ]);

            // Update item status
            $pdo->prepare("UPDATE items SET status = 'Sold' WHERE id = ?")->execute([intval($data['item_id'])]);

            $successCount++;
        }

        fclose($handle);
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Import completed: $successCount sales recorded, $errorCount errors",
            'imported' => $successCount,
            'errors_count' => $errorCount,
            'errors' => $importErrors
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['error' => 'Import failed: ' . $e->getMessage()]);
    }
}