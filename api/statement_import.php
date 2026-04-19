<?php
/**
 * eBay Financial Statement Importer
 * Parses Microsoft SpreadsheetML XML exported by Adobe Acrobat
 * from eBay monthly financial statement PDFs.
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

$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if (!in_array($ext, ['xml', 'xlsx'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Upload an XML or XLSX file']);
    exit;
}

// ── Load XML content ──────────────────────────────────────────────────────────
$xmlContent = null;

if ($ext === 'xlsx') {
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot open XLSX file']);
        exit;
    }
    $xmlContent = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if (!$xmlContent) {
        http_response_code(400);
        echo json_encode(['error' => 'XLSX has no sheet data']);
        exit;
    }
} else {
    $xmlContent = file_get_contents($file);
}

if (!$xmlContent) {
    http_response_code(400);
    echo json_encode(['error' => 'File is empty']);
    exit;
}

// ── Parse XML ─────────────────────────────────────────────────────────────────
libxml_use_internal_errors(true);
$xml = simplexml_load_string($xmlContent);
if (!$xml) {
    $errs = libxml_get_errors();
    libxml_clear_errors();
    http_response_code(400);
    echo json_encode(['error' => 'XML parse error: ' . ($errs[0]->message ?? 'unknown')]);
    exit;
}

$isAdobe = (stripos($xml->asXML(), '<TaggedPDF-doc>') !== false);


$ns = 'urn:schemas-microsoft-com:office:spreadsheet';
$xml->registerXPathNamespace('ss', $ns);

// ── Find statement year ──────────────────────────────────────────────────────
$origName = $_FILES['file']['name'] ?? '';
$statementYear = (int)date('Y');

// Priority 1: Check filename for 4-digit year e.g. "Nov 2022"
if (preg_match('/(20\d{2})/', $origName, $m)) {
    $statementYear = (int)$m[1];
}

// Priority 2: Check XML content for "Date range"
$searchTags = $isAdobe ? ['//P', '//TD'] : ['//ss:Cell/ss:Data'];
foreach ($searchTags as $xpath) {
    $nodes = $xml->xpath($xpath);
    if (!$nodes) continue;
    foreach ($nodes as $n) {
        $raw = strip_tags(preg_replace('/<[^>]+>/', ' ', (string)$n->asXML()));
        $raw = html_entity_decode($raw, ENT_QUOTES, 'UTF-8');
        
        if (preg_match('/Date range:\s*[A-Za-z]+\s+\d+,\s*(\d{4})/i', $raw, $m)) {
            $statementYear = (int)$m[1];
            break 2;
        }
        if (preg_match('/Date range:.*?(\d{4})/i', $raw, $m)) {
            $statementYear = (int)$m[1];
            break 2;
        }
        if (preg_match('/Date range:.*?\d{1,2}\/\d{1,2}\/(\d{2})\b/i', $raw, $m)) {
            $statementYear = 2000 + (int)$m[1];
            break 2;
        }
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function stmtCellText($cell) {
    $raw  = (string)$cell->asXML();
    $text = preg_replace('/<[^>]+>/', ' ', $raw);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

function stmtParseDate($raw, $year) {
    $raw = trim($raw);
    if (preg_match('/^([A-Za-z]+)\s+(\d{1,2})$/', $raw, $m)) {
        $ts = strtotime($m[1] . ' ' . $m[2] . ', ' . $year);
        return $ts ? date('Y-m-d', $ts) : ($year . '-01-01');
    }
    $ts = strtotime($raw);
    return $ts ? date('Y-m-d', $ts) : ($year . '-01-01');
}

// ── Detect format and extract rows ──────────────────────────────────────────
if ($isAdobe) {
    // Adobe Acrobat XML format
    $rows = $xml->xpath('//TR');
} else {
    // Excel XML format
    $ns = 'urn:schemas-microsoft-com:office:spreadsheet';
    $xml->registerXPathNamespace('ss', $ns);
    $rows = $xml->xpath('//ss:Row');
}

$transactions   = [];
$prevWasSale    = false;
$lastSale       = null;

foreach ($rows as $row) {
    if ($isAdobe) {
        $cells = $row->xpath('.//TD');
    } else {
        $cells = $row->xpath('.//ss:Cell');
    }
    
    if (empty($cells)) continue;

    if ($isAdobe) {
        $texts = array_map(function($c) { return trim(strip_tags($c->asXML())); }, $cells);
    } else {
        $texts = array_map('stmtCellText', $cells);
    }
    $rowText = implode(' ', $texts);

    // Identify a sale row: has "processed by eBay" or an order number pattern
    $isSale  = false;
    $saleAmt = null;

    foreach ($cells as $cell) {
        $ct = stmtCellText($cell);

        // Sale type indicator (processed by eBay)
        if (preg_match('/processed.*?by.*?eBay/is', $ct)) {
            $isSale = true;
        }
        
        // Fallback for older 2022 formats: If we see an order number pattern
        // and it's NOT a known fee/refund type.
        if (preg_match('/\d{2}-\d{5}-\d{5}/', $ct)) {
            if (!preg_match('/Fee|Refund|Claim|Payout|Label|Transfer/i', $rowText)) {
                $isSale = true;
            }
        }

        // Numeric amount cell - support both Type="Number" and Type="String" with $ amounts
        if ($isAdobe) {
            // In Adobe format, the amount is usually in the same row text or last cell
            if (preg_match('/\$?([0-9,]+\.[0-9]{2})/', $rowText, $m)) {
                $saleAmt = (float)str_replace(',', '', $m[1]);
            }
        } else {
            $dataNodes = $cell->xpath('.//ss:Data');
            foreach ($dataNodes as $dn) {
                $val = (string)$dn;
                if (strlen($val) < 2) {
                    $val = strip_tags($dn->asXML());
                }

                if (preg_match('/\$?([0-9,]+\.[0-9]{2})/', $val, $m)) {
                    $saleAmt = (float)str_replace(',', '', $m[1]);
                }
                
                $typeAttr = $dn->attributes($ns);
                if (($typeAttr['Type'] ?? '') == 'Number') {
                    $saleAmt = abs((float)$val);
                }
            }
        }
    }

    if ($isSale) {
        $main = implode(' ', $texts);

        // Order number  e.g. 14-08192-74647
        $orderNum = '';
        if (preg_match('/\b(\d{2}-\d{5}-\d{5})\b/', $main, $m)) {
            $orderNum = $m[1];
        }

        // Date at start of cell e.g. "Jan 29"
        $saleDate = $statementYear . '-01-01';
        if (preg_match('/^([A-Za-z]{3}\s+\d{1,2})\b/', $main, $m)) {
            $saleDate = stmtParseDate($m[1], $statementYear);
        }

        // Title: strip date prefix and everything from order# onward
        $chunk = preg_replace('/^[A-Za-z]{3}\s+\d{1,2}\s+/', '', $main);
        $chunk = preg_replace('/\b\d{2}-\d{5}-\d{5}\b/', '', $chunk);
        
        $title = '';
        if (preg_match('/^(.*?)\s*\(\d+\s*item/i', trim($chunk), $m)) {
            $title = trim($m[1]);
        }
        
        if (strlen($title) < 3) {
            $title = 'eBay Sale (' . ($orderNum ?: 'unknown') . ')';
        }

        $lastSale    = [
            'order_num' => $orderNum,
            'date'      => $saleDate,
            'title'     => $title,
            'amount'    => $saleAmt ?? 0,
            'item_num'  => '',
            'fees'      => 0,
            'net'       => 0,
        ];
        $prevWasSale = true;
        continue;
    }

    // Detail row immediately following a sale row
    if ($prevWasSale && $lastSale !== null) {
        $detail = implode(' ', $texts);

        if (preg_match('/Item no[:\s]+(\d{10,15})/i', $detail, $m)) {
            $lastSale['item_num'] = $m[1];
        }
        if (preg_match('/-\$?([\d.]+)\s*Net Total/i', $detail, $m)) {
            $lastSale['fees'] = (float)$m[1];
        }
        if (preg_match('/Net Total[:\s]+\$?([\d.]+)/i', $detail, $m)) {
            $lastSale['net'] = (float)$m[1];
        }

        $transactions[] = $lastSale;
        $lastSale    = null;
        $prevWasSale = false;
        continue;
    }

    $prevWasSale = false;
}

// ── Guard: nothing parsed ─────────────────────────────────────────────────────
if (empty($transactions)) {
    echo json_encode([
        'success' => false,
        'error'   => 'No sales found in this statement (year detected: ' . $statementYear . '). '
                   . 'Make sure you exported the XML from the Orders/Transactions section of the PDF.',
        'year'    => $statementYear,
    ]);
    exit;
}

// ── Write to database ─────────────────────────────────────────────────────────
try {
    $pdo->beginTransaction();
    $imported = 0;
    $dupes    = 0;

    foreach ($transactions as $t) {
        if ($t['order_num']) {
            $chk = $pdo->prepare("SELECT id FROM sales WHERE sale_notes LIKE ?");
            $chk->execute(['%eBay-Order:' . $t['order_num'] . '%']);
            if ($chk->fetchColumn()) {
                $dupes++;
                continue;
            }
        }

        $pdo->prepare("
            INSERT INTO items
                (name, purchase_date, purchase_price, status, ebay_listing_id, purchase_notes, created_at)
            VALUES (?, ?, 0, 'Sold', ?, ?, ?)
        ")->execute([
            $t['title'],
            $t['date'],
            $t['item_num'],
            'eBay statement import (PDF) — add purchase price to track profit',
            $t['date'] . ' 00:00:00',
        ]);
        $itemId = $pdo->lastInsertId();

        $notes = implode(' | ', array_filter([
            $t['order_num'] ? 'eBay-Order:' . $t['order_num'] : '',
            $t['item_num']  ? 'Item:' . $t['item_num']        : '',
            $t['fees']      ? 'eBay-Fees:$' . $t['fees']      : '',
            $t['net']       ? 'Net:$' . $t['net']             : '',
            'Source:PDF-Statement',
        ]));

        $pdo->prepare("
            INSERT INTO sales
                (item_id, sale_date, sale_price, sale_platform, shipping_cost, sale_notes, created_at)
            VALUES (?, ?, ?, 'eBay', 0, ?, ?)
        ")->execute([
            $itemId,
            $t['date'],
            $t['amount'],
            $notes,
            $t['date'] . ' 00:00:00',
        ]);

        $imported++;
    }

    $pdo->commit();

    $msg = 'Imported ' . $imported . ' sale' . ($imported !== 1 ? 's' : '')
         . ' from ' . $statementYear . ' statement';
    if ($dupes) {
        $msg .= ' (' . $dupes . ' duplicate' . ($dupes !== 1 ? 's' : '') . ' skipped)';
    }
    $msg .= '. Titles truncated, shipping included in sale total (PDF limitation).';

    echo json_encode([
        'success'      => true,
        'message'      => $msg,
        'imported'     => $imported,
        'dupes'        => $dupes,
        'year'         => $statementYear,
        'transactions' => $transactions,
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Import failed: ' . $e->getMessage()]);
}
