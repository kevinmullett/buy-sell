<?php
/**
 * Inventory Importer (XLSX)
 * Reads the user's master inventory spreadsheet.
 */
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

$file     = $_FILES['file']['tmp_name'] ?? null;
$origName = $_FILES['file']['name']     ?? '';

if (!$file || !file_exists($file)) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    echo json_encode(['error' => 'ZipArchive PHP extension is missing on the server.']);
    exit;
}

$zip = new ZipArchive();
if ($zip->open($file) !== true) {
    http_response_code(400);
    echo json_encode(['error' => 'Cannot open XLSX file']);
    exit;
}

// Read shared strings
$sharedStrings = [];
$ssXml = $zip->getFromName('xl/sharedStrings.xml');
if ($ssXml) {
    $ss = simplexml_load_string($ssXml);
    foreach ($ss->si as $si) {
        $text = '';
        $tNodes = $si->xpath('.//*[local-name()="t"]');
        if ($tNodes !== false) {
            foreach ($tNodes as $t) {
                $text .= (string)$t;
            }
        }
        $sharedStrings[] = $text;
    }
}

// Read sheet names and relationships
$wbXml = $zip->getFromName('xl/workbook.xml');
$wb    = simplexml_load_string($wbXml);
$wb->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

$sheets = [];
foreach ($wb->sheets->sheet as $sheet) {
    // Skip hidden sheets
    if ((string)$sheet['state'] === 'hidden') continue;

    $rAttrs = $sheet->attributes('r', true);
    $sheets[] = [
        'name' => trim((string)$sheet['name']),
        'rId'  => (string)$rAttrs['id']
    ];
}

$relXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
$rel    = simplexml_load_string($relXml);
$relMap = [];
foreach ($rel->Relationship as $r) {
    $relMap[(string)$r['Id']] = (string)$r['Target'];
}

function colIndex($ref) {
    $col = preg_replace('/\d/', '', $ref);
    $idx = 0;
    foreach (str_split($col) as $c) {
        $idx = $idx * 26 + (ord(strtoupper($c)) - ord('A') + 1);
    }
    return $idx - 1;
}

$itemsToImport = [];

foreach ($sheets as $sheet) {
    $sheetName = $sheet['name'];
    
    // Skip summary or non-inventory sheets
    if (stripos($sheetName, 'eBay Shipping') !== false || stripos($sheetName, 'PayPal') !== false) {
        continue;
    }
    
    // Attempt to extract year and location from sheet name (e.g., "2025 BL")
    $defaultYear = date('Y');
    $location    = $sheetName;
    if (preg_match('/^(\d{4})\s*(.*)$/', $sheetName, $m)) {
        $defaultYear = (int)$m[1];
        $location    = trim($m[2]);
    }

    $target = $relMap[$sheet['rId']] ?? null;
    if (!$target) continue;
    $path = 'xl/' . ltrim($target, '/');
    $shXml = $zip->getFromName($path);
    if (!$shXml) continue;

    $sh = simplexml_load_string($shXml);
    if (!$sh) continue;
    
    $sh->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $rows = $sh->xpath('//x:row');
    if ($rows === false) {
        $rows = $sh->xpath('//row');
    }
    if (!is_array($rows)) continue;

    $isFirstRow = true;
    foreach ($rows as $row) {
        $rowData = [];
        $row->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $cells = $row->xpath('.//x:c');
        if ($cells === false) {
            $cells = $row->xpath('.//c');
        }
        if (!is_array($cells)) continue;
        
        $currentColIndex = 0;
        foreach ($cells as $cell) {
            $ref = (string)$cell['r'];
            if ($ref) {
                $ci = colIndex($ref);
                $currentColIndex = $ci + 1; // Next cell will be after this
            } else {
                $ci = $currentColIndex;
                $currentColIndex++;
            }
            
            $t   = (string)$cell['t'];
            $v   = (string)$cell->v;
            
            if ($t === 's' && isset($sharedStrings[(int)$v])) {
                $v = $sharedStrings[(int)$v];
            }
            $rowData[$ci] = trim($v);
        }

        // Columns: A=0 (Title), B=1 (Purchase Info), C=2 (Retail), D=3 (Has)
        $title    = $rowData[0] ?? '';
        $purInfo  = $rowData[1] ?? '';
        $retail   = $rowData[2] ?? '';
        $hasCol   = $rowData[3] ?? '';

        if ($isFirstRow) {
            // Check if it's a header row
            if (stripos($title, 'Item') !== false || stripos($title, 'Title') !== false) {
                $isFirstRow = false;
                continue;
            }
            $isFirstRow = false;
        }

        if (empty($title) || is_numeric($title) || strlen($title) < 4) continue;
        if (stripos($title, 'Paid in ') === 0) continue; // Ignore 'Paid in yyyy' separator rows
        if (stripos($title, 'Total') === 0) continue;
        if (preg_match('/^(?:Yellow|Red|Green|Gray|Blue|Dark Yellow)\s*=/i', $title)) continue; // Ignore legend rows

        // Parse Purchase Info: "$Price - Date (Notes)"
        $price = 0;
        $date  = $defaultYear . '-01-01';
        $notes = '';
        $qty   = 1;
        $status = 'Available';

        // Extract notes in parentheses
        if (preg_match('/\((.*?)\)/', $purInfo, $m)) {
            $notes = $m[1];
        }

        // Determine Quantity
        // Priority 1: "x5" or "5ea" or "5 ea" anywhere in the info
        if (preg_match('/(?:^|\s|\(|,)(?:x\s*(\d{1,3})|(\d{1,3})\s*ea)(?:$|\s|\)|,)/i', $purInfo, $m)) {
            $qty = (int)($m[1] ?: $m[2]);
        } else {
            // Priority 2: Column D if it exists
            $parsedHas = (int)$hasCol;
            if ($parsedHas > 0) {
                $qty = $parsedHas;
            }
        }
        if ($qty > 100) $qty = 100;

        // Look for price: "$10" or "10.00"
        if (preg_match('/\$([\d,.]+)/', $purInfo, $m)) {
            // Specific $ price takes priority
            $price = (float)str_replace(',', '', $m[1]);
        } elseif (preg_match('/(?:\s|^)([\d,]+\.\d{2})(?:\s|$|ea|each)/i', $purInfo, $m)) {
            // Number with 2 decimals e.g. "12.50 ea"
            $price = (float)str_replace(',', '', $m[1]);
        } elseif (preg_match('/^([\d,.]+)(?:\s|$|ea|each)/i', trim($purInfo), $m)) {
            // Number at start
            $p = (float)str_replace(',', '', $m[1]);
            if ($p != $qty || !preg_match('/ea|each/i', $purInfo)) {
                $price = $p;
            }
        }

        // Look for date: "05/10/25" or "4/27"
        if (preg_match('/(\d{1,2}\/\d{1,2}(?:\/\d{2,4})?)/', $purInfo, $m)) {
            $dateStr = $m[1];
            $dateParts = explode('/', $dateStr);
            if (count($dateParts) == 2) {
                $date = sprintf('%04d-%02d-%02d', $defaultYear, $dateParts[0], $dateParts[1]);
            } elseif (count($dateParts) == 3) {
                $y = $dateParts[2];
                if (strlen($y) == 2) $y = '20' . $y;
                $date = sprintf('%04d-%02d-%02d', $y, $dateParts[0], $dateParts[1]);
            }
        }

        // Handle multiple items (unit price)
        if (preg_match('/ea|each/i', $purInfo) && $qty > 1 && $price > 0) {
            // $price is already the unit price
        } elseif ($qty > 1 && $price > 0) {
            // If it's a bulk price for multiple items without "ea", we keep it as total paid for the line
            // but the system creates multiple items.
            // For now, let's assume if they have a qty and a price, it's the price per item unless specified.
        }

        // Check for personal use / keeping
        if (preg_match('/keep(?:ing)?|office|personal|giving/i', $notes)) {
            $status = 'Personal Use';
        }

        // Clean retail price
        $retailAmt = 0;
        if (preg_match('/[\d.]+/', $retail, $m)) {
            $retailAmt = (float)$m[0];
        }

        // Handle multiple items (unit price)
        // If qty > 1, we insert multiple rows so each physical item has its own record
        for ($i = 0; $i < $qty; $i++) {
            $itemsToImport[] = [
                'name'                 => $title,
                'purchase_date'        => $date,
                'purchase_price'       => $price,
                'purchase_location'    => $location,
                'current_retail_price' => $retailAmt,
                'status'               => $status,
                'purchase_notes'       => $notes . ($qty > 1 ? " (Unit $i of $qty)" : ''),
            ];
        }
    }
}
$zip->close();

if (empty($itemsToImport)) {
    echo json_encode(['success' => false, 'error' => 'No inventory items found in file.']);
    exit;
}

// ── Write to database ─────────────────────────────────────────────────────────
try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("
        INSERT INTO items (
            name, purchase_date, purchase_price, purchase_location,
            current_retail_price, status, purchase_notes, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $imported = 0;
    foreach ($itemsToImport as $item) {
        $stmt->execute([
            $item['name'],
            $item['purchase_date'],
            $item['purchase_price'],
            $item['purchase_location'],
            $item['current_retail_price'],
            $item['status'],
            $item['purchase_notes'],
            date('Y-m-d H:i:s')
        ]);
        $imported++;
    }

    $pdo->commit();

    echo json_encode([
        'success'  => true,
        'message'  => "Imported $imported items from master inventory.",
        'imported' => $imported
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Import failed: ' . $e->getMessage()]);
}
