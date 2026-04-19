<?php
/**
 * eBay Orders Report CSV Importer
 * Handles the exact format exported from eBay Seller Hub → Orders → Download Report
 *
 * Format quirks handled:
 *   - Row 1 is a blank garbage row
 *   - Row 2 is the header
 *   - Row 3 is a blank placeholder row
 *   - Last 3 rows are footer ("18,record(s) downloaded", "Seller ID : xxx", blank)
 *   - Dates are in "Apr-16-26" format
 *   - Prices have leading "$" e.g. "$59.99"
 *   - Duplicate order numbers = multi-quantity orders (handled separately)
 */
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

$file = $_FILES['file']['tmp_name'] ?? null;
if (!$file || !file_exists($file)) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

/**
 * Parse eBay date "Apr-16-26" → "2026-04-16"
 */
function parseEbayDate(string $raw): string {
    $raw = trim($raw);
    if (!$raw || $raw === '--') return date('Y-m-d');

    // Try standard parsing first
    $ts = strtotime($raw);
    if ($ts && $ts > 0) {
        // Handle 2-digit year ambiguity: "Apr-16-26" → 2026, not 1926
        $year = (int)date('Y', $ts);
        if ($year < 2000) $year += 100;
        return $year . '-' . date('m-d', $ts);
    }

    // Manual parse: "Apr-16-26"
    if (preg_match('/^([A-Za-z]+)-(\d{1,2})-(\d{2,4})$/', $raw, $m)) {
        $monthNum = date('m', strtotime($m[1] . ' 1'));
        $day      = str_pad($m[2], 2, '0', STR_PAD_LEFT);
        $year     = (int)$m[3] < 100 ? 2000 + (int)$m[3] : (int)$m[3];
        return "$year-$monthNum-$day";
    }

    return date('Y-m-d');
}

/**
 * Strip "$" and commas from price strings
 */
function parseEbayPrice(string $raw): float {
    return (float)preg_replace('/[^0-9.]/', '', $raw);
}

/**
 * Check if a row is a footer/summary line (not real data)
 */
function isFooterRow(array $row): bool {
    $first = trim($row[0] ?? '');
    // Footer rows: "18,record(s) downloaded" → first cell is numeric or has "record"
    if (preg_match('/^\d+$/', $first) && isset($row[1]) && stripos($row[1], 'record') !== false) return true;
    // "Seller ID : xxx"
    if (stripos($first, 'Seller ID') !== false) return true;
    return false;
}

/**
 * Check if a row is completely empty or a blank placeholder
 */
function isEmptyRow(array $row): bool {
    return count(array_filter(array_map(function($v) { return trim((string)$v); }, $row))) === 0;
}

try {
    $handle = fopen($file, 'r');
    if (!$handle) throw new Exception('Cannot open file');

    // Skip row 1 (blank garbage header)
    fgetcsv($handle);

    // Row 2 = real headers
    $rawHeaders = fgetcsv($handle);
    if (!$rawHeaders) throw new Exception('Cannot read headers');
    $headers = array_map('trim', $rawHeaders);

    // Row 3 = blank placeholder, skip
    fgetcsv($handle);

    $pdo->beginTransaction();
    $imported  = 0;
    $skipped   = 0;
    $dupes     = 0;
    $errors    = [];
    $lineNum   = 3;

    while (($row = fgetcsv($handle)) !== false) {
        $lineNum++;

        // Skip footer/summary rows
        if (isFooterRow($row) || isEmptyRow($row)) continue;

        // Map row to associative array by header
        $data = [];
        foreach ($headers as $i => $h) {
            $data[$h] = isset($row[$i]) ? trim($row[$i]) : '';
        }

        // Required: Item Title
        $itemTitle = $data['Item Title'] ?? '';
        if (!$itemTitle || $itemTitle === '--') {
            $skipped++;
            $errors[] = "Row $lineNum: no item title, skipped";
            continue;
        }

        // Required: Sale Date
        $rawDate  = $data['Sale Date'] ?? '';
        if (!$rawDate) {
            $skipped++;
            $errors[] = "Row $lineNum: no sale date, skipped";
            continue;
        }
        $saleDate = parseEbayDate($rawDate);

        // Prices
        $soldFor     = parseEbayPrice($data['Sold For']              ?? '0');
        $shipping    = parseEbayPrice($data['Shipping And Handling'] ?? '0');
        $ebayTax     = parseEbayPrice($data['eBay Collected Tax']    ?? '0');

        // Other fields
        $ebayItemId  = trim($data['Item Number']      ?? '');
        $orderNum    = trim($data['Order Number']     ?? '');
        $qty         = max(1, (int)($data['Quantity'] ?? 1));
        $shipService = trim($data['Shipping Service'] ?? '');
        $trackNum    = trim($data['Tracking Number']  ?? '');
        $customLabel = trim($data['Custom Label']     ?? '');
        $recordNum   = trim($data['Sales Record Number'] ?? '');

        // Duplicate check: don't re-import the same eBay transaction
        if ($orderNum) {
            $exists = $pdo->prepare("SELECT id FROM sales WHERE sale_notes LIKE ?");
            $exists->execute(["%eBay-Order:$orderNum%"]);
            if ($exists->fetchColumn()) {
                $dupes++;
                continue;
            }
        }

        // Create a placeholder item for this sale
        // purchase_price = 0, flagged for user to fill in cost later
        // The eBay Item Number is stored so future reconciliation can match it
        $itemStmt = $pdo->prepare("
            INSERT INTO items
                (name, purchase_date, purchase_price, status, ebay_listing_id,
                 category, quantity, purchase_notes, created_at)
            VALUES (?, ?, 0, 'Sold', ?, '', ?, ?, ?)
        ");
        $itemStmt->execute([
            $itemTitle,
            $saleDate,   // use sale date as purchase date placeholder
            $ebayItemId,
            $qty,
            'eBay import — add purchase cost to track profit',
            $saleDate . ' 00:00:00',
        ]);
        $itemId = $pdo->lastInsertId();

        // Record the sale
        $saleNotes = implode(' | ', array_filter([
            $orderNum  ? "eBay-Order:$orderNum"   : '',
            $recordNum ? "Record:$recordNum"       : '',
            $trackNum  ? "Tracking:$trackNum"      : '',
            $customLabel ? "Label:$customLabel"    : '',
            $ebayTax   ? "eBay-Tax:\$$ebayTax"    : '',
        ]));

        $saleStmt = $pdo->prepare("
            INSERT INTO sales
                (item_id, sale_date, sale_price, sale_platform,
                 shipping_cost, packing_method, sale_notes, created_at)
            VALUES (?, ?, ?, 'eBay', ?, ?, ?, ?)
        ");
        $saleStmt->execute([
            $itemId,
            $saleDate,
            $soldFor,
            $shipping,
            $shipService,
            $saleNotes,
            $saleDate . ' 00:00:00',
        ]);

        $imported++;
    }

    fclose($handle);
    $pdo->commit();

    $msg = "Imported $imported eBay sale" . ($imported !== 1 ? 's' : '');
    if ($dupes)    $msg .= ", $dupes duplicate" . ($dupes !== 1 ? 's' : '') . " skipped";
    if ($skipped)  $msg .= ", $skipped row" . ($skipped !== 1 ? 's' : '') . " skipped";
    $msg .= ". Update each item's purchase price to enable profit tracking.";

    echo json_encode([
        'success'  => true,
        'message'  => $msg,
        'imported' => $imported,
        'dupes'    => $dupes,
        'skipped'  => $skipped,
        'errors'   => array_slice($errors, 0, 10),
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Import failed: ' . $e->getMessage()]);
}
