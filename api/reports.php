<?php
/**
 * Reports API — year/month filter, profit by source, CSV tax export
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../functions.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$type   = $_GET['type']   ?? 'dashboard';
$year   = $_GET['year']   ?? date('Y');
$month  = $_GET['month']  ?? null;  // null = whole year
$export = $_GET['export'] ?? null;  // 'csv' triggers download

// Build date range from year + optional month
if ($month) {
    $startDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
    $endDate   = date('Y-m-t', strtotime($startDate));
} else {
    $startDate = "$year-01-01";
    $endDate   = "$year-12-31";
}

switch ($type) {
    case 'dashboard':   getDashboard(); break;
    case 'profit':      getProfitReport($startDate, $endDate, $export); break;
    case 'tax':         getTaxReport($year, $export); break;
    case 'best_items':  getBestItems($startDate, $endDate); break;
    case 'by_category': getByCategory($startDate, $endDate); break;
    case 'by_platform': getByPlatform($startDate, $endDate); break;
    case 'by_source':   getBySource($startDate, $endDate); break;
    default:            getDashboard();
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function jsonOut($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
}

function csvOut($filename, $rows, $headers) {
    header('Content-Type: text/csv');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    $f = fopen('php://output', 'w');
    fputcsv($f, $headers);
    foreach ($rows as $r) { fputcsv($f, $r); }
    fclose($f);
}

// ── Dashboard ─────────────────────────────────────────────────────────────────
function getDashboard() {
    global $pdo;
    try {
        $totalItems     = $pdo->query("SELECT COUNT(*) FROM items WHERE is_archived=0")->fetchColumn();
        $available      = $pdo->query("SELECT COUNT(*) FROM items WHERE status='Available' AND is_archived=0")->fetchColumn();
        $sold           = $pdo->query("SELECT COUNT(*) FROM items WHERE status='Sold' AND is_archived=0")->fetchColumn();
        $listed         = $pdo->query("SELECT COUNT(*) FROM items WHERE ebay_listing_url!='' AND ebay_listing_url IS NOT NULL AND status='Available' AND is_archived=0")->fetchColumn();
        $invValue       = $pdo->query("SELECT COALESCE(SUM(COALESCE(current_retail_price,purchase_price)*quantity),0) FROM items WHERE status='Available' AND is_archived=0")->fetchColumn();
        $totalInvest    = $pdo->query("SELECT COALESCE(SUM(purchase_price*quantity),0) FROM items WHERE is_archived=0")->fetchColumn();

        $salesData = $pdo->query("SELECT COUNT(*) AS total_sales,
            COALESCE(SUM(s.sale_price),0) AS total_revenue,
            COALESCE(SUM(s.sale_price - i.purchase_price - s.shipping_cost),0) AS net_profit,
            COALESCE(AVG(s.sale_price - i.purchase_price - s.shipping_cost),0) AS avg_profit_per_sale,
            COALESCE(SUM(s.shipping_cost),0) AS total_shipping,
            COALESCE(AVG(CAST(julianday(s.sale_date)-julianday(i.purchase_date) AS INTEGER)),0) AS avg_days_to_sell
            FROM sales s JOIN items i ON s.item_id=i.id")->fetch();

        $monthStart = date('Y-m-01');
        $ms = $pdo->prepare("SELECT COUNT(*) AS sales_count,
            COALESCE(SUM(s.sale_price),0) AS revenue,
            COALESCE(SUM(s.sale_price-i.purchase_price-s.shipping_cost),0) AS profit
            FROM sales s JOIN items i ON s.item_id=i.id WHERE s.sale_date >= ?");
        $ms->execute([$monthStart]);
        $monthData = $ms->fetch();

        $lastSale = $pdo->query("SELECT sale_date FROM sales ORDER BY sale_date DESC LIMIT 1")->fetchColumn();
        $lastItem = $pdo->query("SELECT created_at FROM items WHERE is_archived=0 ORDER BY created_at DESC LIMIT 1")->fetchColumn();
        $daysSinceLastSale = $lastSale ? (int)((time()-strtotime($lastSale))/86400) : null;
        $daysSinceLastItem = $lastItem ? (int)((time()-strtotime($lastItem))/86400) : null;

        // Stale count (available > 60 days)
        $stale = $pdo->query("SELECT COUNT(*) FROM items WHERE status='Available' AND is_archived=0 AND CAST(julianday('now')-julianday(created_at) AS INTEGER) > 60")->fetchColumn();

        $recentSales = $pdo->query("SELECT 'sale' AS type, s.sale_date AS date, i.name AS item_name, s.sale_price AS amount, s.sale_platform AS detail, s.created_at FROM sales s JOIN items i ON s.item_id=i.id ORDER BY s.created_at DESC LIMIT 5")->fetchAll();
        $recentItems = $pdo->query("SELECT 'item' AS type, i.purchase_date AS date, i.name AS item_name, i.purchase_price AS amount, i.purchase_location AS detail, i.created_at FROM items i WHERE i.is_archived=0 ORDER BY i.created_at DESC LIMIT 5")->fetchAll();
        $activity = array_slice(
            array_merge($recentSales, $recentItems), 0, 10
        );
        usort($activity, fn($a,$b) => strtotime($b['created_at'])-strtotime($a['created_at']));

        $avgMargin = $salesData['total_revenue'] > 0
            ? round(($salesData['net_profit']/$salesData['total_revenue'])*100,1) : 0;

        jsonOut(['success'=>true,'dashboard'=>[
            'total_items'          => (int)$totalItems,
            'available_items'      => (int)$available,
            'sold_items'           => (int)$sold,
            'listed_items'         => (int)$listed,
            'stale_items'          => (int)$stale,
            'inventory_value'      => round((float)$invValue,2),
            'total_investment'     => round((float)$totalInvest,2),
            'total_sales'          => (int)$salesData['total_sales'],
            'total_revenue'        => round((float)$salesData['total_revenue'],2),
            'net_profit'           => round((float)$salesData['net_profit'],2),
            'avg_profit_per_sale'  => round((float)$salesData['avg_profit_per_sale'],2),
            'total_shipping'       => round((float)$salesData['total_shipping'],2),
            'avg_margin'           => $avgMargin,
            'avg_days_to_sell'     => round((float)$salesData['avg_days_to_sell'],1),
            'month_sales'          => (int)$monthData['sales_count'],
            'month_revenue'        => round((float)$monthData['revenue'],2),
            'month_profit'         => round((float)$monthData['profit'],2),
            'days_since_last_sale' => $daysSinceLastSale,
            'days_since_last_item' => $daysSinceLastItem,
        ],'recent_activity'=>$activity]);
    } catch (PDOException $e) {
        http_response_code(500); jsonOut(['error'=>$e->getMessage()]);
    }
}

// ── Profit report ─────────────────────────────────────────────────────────────
function getProfitReport($startDate, $endDate, $export) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT s.sale_date, i.name, i.category, i.purchase_location AS source,
            i.purchase_price, s.sale_price, s.shipping_cost,
            (s.sale_price-i.purchase_price-s.shipping_cost) AS profit,
            CASE WHEN s.sale_price>0 THEN ROUND(((s.sale_price-i.purchase_price-s.shipping_cost)/s.sale_price)*100,1) ELSE 0 END AS margin,
            CAST(julianday(s.sale_date)-julianday(i.purchase_date) AS INTEGER) AS days_to_sell,
            s.sale_platform, i.packaging
            FROM sales s JOIN items i ON s.item_id=i.id
            WHERE s.sale_date BETWEEN ? AND ? ORDER BY s.sale_date DESC");
        $stmt->execute([$startDate, $endDate]);
        $sales = $stmt->fetchAll();

        if ($export === 'csv') {
            $headers = ['Sale Date','Item','Category','Source','Cost','Sale Price','Shipping','Profit','Margin%','Days to Sell','Platform'];
            $rows = array_map(fn($s) => [
                $s['sale_date'],$s['name'],$s['category'],$s['source'],
                $s['purchase_price'],$s['sale_price'],$s['shipping_cost'],
                $s['profit'],$s['margin'],$s['days_to_sell'],$s['sale_platform']
            ], $sales);
            csvOut("profit-report-$startDate-to-$endDate.csv", $rows, $headers);
            return;
        }

        $tp = array_sum(array_column($sales,'profit'));
        $tr = array_sum(array_column($sales,'sale_price'));
        jsonOut(['success'=>true,'period'=>"$startDate to $endDate",
            'summary'=>['total_profit'=>round($tp,2),'total_revenue'=>round($tr,2),
                'total_sales'=>count($sales),'avg_profit'=>count($sales)?round($tp/count($sales),2):0,
                'avg_margin'=>$tr>0?round(($tp/$tr)*100,1):0],
            'data'=>$sales]);
    } catch (PDOException $e) {
        http_response_code(500); jsonOut(['error'=>$e->getMessage()]);
    }
}

// ── Tax report ────────────────────────────────────────────────────────────────
function getTaxReport($year, $export) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT
            s.sale_date, i.name, i.purchase_location AS source, i.category,
            i.purchase_price AS cost, s.sale_price AS revenue,
            s.shipping_cost,
            (s.sale_price-i.purchase_price-s.shipping_cost) AS net_income,
            s.sale_platform
            FROM sales s JOIN items i ON s.item_id=i.id
            WHERE strftime('%Y',s.sale_date)=?
            ORDER BY s.sale_date ASC");
        $stmt->execute([$year]);
        $rows = $stmt->fetchAll();

        if ($export === 'csv') {
            $headers = ['Date','Item','Source','Category','Cost of Goods','Sale Price','Shipping','Net Income','Platform'];
            $data = array_map(fn($r) => [
                $r['sale_date'],$r['name'],$r['source'],$r['category'],
                $r['cost'],$r['revenue'],$r['shipping_cost'],$r['net_income'],$r['sale_platform']
            ], $rows);
            // Totals row
            $data[] = ['TOTALS','','','',
                array_sum(array_column($rows,'cost')),
                array_sum(array_column($rows,'revenue')),
                array_sum(array_column($rows,'shipping_cost')),
                array_sum(array_column($rows,'net_income')),''
            ];
            csvOut("tax-report-$year.csv", $data, $headers);
            return;
        }

        // Quarterly summary
        $quarters = [];
        for ($q=1;$q<=4;$q++) {
            $qs = "$year-".str_pad(($q-1)*3+1,2,'0',STR_PAD_LEFT)."-01";
            $qm = $q*3;
            $qe = "$year-".str_pad($qm,2,'0',STR_PAD_LEFT)."-".date('t',strtotime("$year-$qm-01"));
            $qs2 = $pdo->prepare("SELECT COALESCE(SUM(s.sale_price),0) AS revenue,
                COALESCE(SUM(i.purchase_price),0) AS cost,
                COALESCE(SUM(s.shipping_cost),0) AS shipping,
                COALESCE(SUM(s.sale_price-i.purchase_price-s.shipping_cost),0) AS profit,
                COUNT(*) AS transactions
                FROM sales s JOIN items i ON s.item_id=i.id WHERE s.sale_date BETWEEN ? AND ?");
            $qs2->execute([$qs,$qe]);
            $quarters["Q$q"] = array_merge($qs2->fetch(), ['period'=>"$qs to $qe"]);
        }

        $totals = ['gross_revenue'=>0,'cost_of_goods_sold'=>0,'shipping_expenses'=>0,'net_business_income'=>0,'total_transactions'=>0];
        foreach ($rows as $r) {
            $totals['gross_revenue']        += $r['revenue'];
            $totals['cost_of_goods_sold']   += $r['cost'];
            $totals['shipping_expenses']    += $r['shipping_cost'];
            $totals['net_business_income']  += $r['net_income'];
            $totals['total_transactions']++;
        }
        foreach ($totals as &$v) { if (is_float($v)) $v = round($v,2); }

        jsonOut(['success'=>true,'year'=>$year,'schedule_c'=>$totals,'quarterly'=>$quarters,'transactions'=>$rows]);
    } catch (PDOException $e) {
        http_response_code(500); jsonOut(['error'=>$e->getMessage()]);
    }
}

// ── Best items ────────────────────────────────────────────────────────────────
function getBestItems($startDate, $endDate) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT i.name, i.category, i.purchase_price, i.purchase_location,
            s.sale_price, s.shipping_cost,
            (s.sale_price-i.purchase_price-s.shipping_cost) AS profit,
            CASE WHEN s.sale_price>0 THEN ROUND(((s.sale_price-i.purchase_price-s.shipping_cost)/s.sale_price)*100,1) ELSE 0 END AS margin,
            CAST(julianday(s.sale_date)-julianday(i.purchase_date) AS INTEGER) AS days_to_sell,
            s.sale_platform
            FROM sales s JOIN items i ON s.item_id=i.id
            WHERE s.sale_date BETWEEN ? AND ?
            ORDER BY profit DESC LIMIT 20");
        $stmt->execute([$startDate,$endDate]);
        jsonOut(['success'=>true,'data'=>$stmt->fetchAll()]);
    } catch (PDOException $e) {
        http_response_code(500); jsonOut(['error'=>$e->getMessage()]);
    }
}

// ── By category ───────────────────────────────────────────────────────────────
function getByCategory($startDate, $endDate) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT i.category,
            COUNT(*) AS item_count,
            COALESCE(SUM(s.sale_price-i.purchase_price-s.shipping_cost),0) AS total_profit,
            COALESCE(AVG(s.sale_price-i.purchase_price-s.shipping_cost),0) AS avg_profit,
            COALESCE(SUM(s.sale_price),0) AS total_revenue
            FROM sales s JOIN items i ON s.item_id=i.id
            WHERE s.sale_date BETWEEN ? AND ?
            GROUP BY i.category ORDER BY total_profit DESC");
        $stmt->execute([$startDate,$endDate]);
        jsonOut(['success'=>true,'data'=>$stmt->fetchAll()]);
    } catch (PDOException $e) {
        http_response_code(500); jsonOut(['error'=>$e->getMessage()]);
    }
}

// ── By platform ───────────────────────────────────────────────────────────────
function getByPlatform($startDate, $endDate) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT s.sale_platform,
            COUNT(*) AS sale_count,
            COALESCE(SUM(s.sale_price-i.purchase_price-s.shipping_cost),0) AS total_profit,
            COALESCE(AVG(s.sale_price-i.purchase_price-s.shipping_cost),0) AS avg_profit,
            COALESCE(SUM(s.sale_price),0) AS total_revenue
            FROM sales s JOIN items i ON s.item_id=i.id
            WHERE s.sale_date BETWEEN ? AND ?
            GROUP BY s.sale_platform ORDER BY total_profit DESC");
        $stmt->execute([$startDate,$endDate]);
        jsonOut(['success'=>true,'data'=>$stmt->fetchAll()]);
    } catch (PDOException $e) {
        http_response_code(500); jsonOut(['error'=>$e->getMessage()]);
    }
}

// ── By source (purchase location) ─────────────────────────────────────────────
function getBySource($startDate, $endDate) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT i.purchase_location AS source,
            COUNT(DISTINCT i.id) AS item_count,
            COUNT(s.id) AS sale_count,
            COALESCE(SUM(s.sale_price-i.purchase_price-s.shipping_cost),0) AS total_profit,
            COALESCE(AVG(s.sale_price-i.purchase_price-s.shipping_cost),0) AS avg_profit,
            COALESCE(SUM(s.sale_price),0) AS total_revenue,
            COALESCE(SUM(i.purchase_price),0) AS total_cost
            FROM items i LEFT JOIN sales s ON s.item_id=i.id
            WHERE (s.sale_date BETWEEN ? AND ? OR s.id IS NULL)
            AND i.purchase_location IS NOT NULL AND i.purchase_location != ''
            AND i.is_archived=0
            GROUP BY i.purchase_location ORDER BY total_profit DESC");
        $stmt->execute([$startDate,$endDate]);
        jsonOut(['success'=>true,'data'=>$stmt->fetchAll()]);
    } catch (PDOException $e) {
        http_response_code(500); jsonOut(['error'=>$e->getMessage()]);
    }
}