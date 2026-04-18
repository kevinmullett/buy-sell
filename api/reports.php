<?php
/**
 * Enhanced Reports API Endpoints
 * Generates comprehensive profit, tax, and analytics reports
 */

require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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
        getReports();
        break;
    case 'POST':
        generateReport($input);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

function getReports() {
    global $pdo;
    
    try {
        // Comprehensive profit report
        $profitStmt = $pdo->query("SELECT 
            SUM(sale_price - purchase_price - shipping_cost) as net_profit,
            COUNT(*) as total_sales,
            AVG(sale_price - purchase_price - shipping_cost) as avg_profit_per_sale
            FROM sales s JOIN items i ON s.item_id = i.id");
        $profit = $profitStmt->fetch(PDO::FETCH_ASSOC);
        
        // Sales by category with detailed breakdown
        $categoryStmt = $pdo->query("SELECT 
            c.name, 
            SUM(s.sale_price - s.purchase_price - s.shipping_cost) as total_profit,
            COUNT(*) as sales_count,
            AVG(s.sale_price - s.purchase_price - s.shipping_cost) as avg_profit,
            MIN(s.sale_price - s.purchase_price - s.shipping_cost) as min_profit,
            MAX(s.sale_price - s.purchase_price - s.shipping_cost) as max_profit
            FROM sales s 
            JOIN items i ON s.item_id = i.id 
            JOIN categories c ON i.category = c.id 
            GROUP BY c.name 
            ORDER BY total_profit DESC");
        $categorySales = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Sales by platform
        $platformStmt = $pdo->query("SELECT 
            sale_platform,
            SUM(sale_price - purchase_price - shipping_cost) as total_profit,
            COUNT(*) as sales_count,
            AVG(sale_price - purchase_price - shipping_cost) as avg_profit
            FROM sales 
            GROUP BY sale_platform 
            ORDER BY total_profit DESC");
        $platformSales = $platformStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Time-based analysis (last 30 days, 90 days, 1 year)
        $timeAnalysis = [];
        $periods = ['30_days', '90_days', '1_year'];
        foreach ($periods as $period) {
            $dateSub = match($period) {
                '30_days' => '30 DAY',
                '90_days' => '90 DAY',
                '1_year' => '1 YEAR',
            };
            
            $stmt = $pdo->prepare("SELECT 
                SUM(sale_price - purchase_price - shipping_cost) as period_profit,
                COUNT(*) as period_sales,
                AVG(sale_price - purchase_price - shipping_cost) as period_avg_profit
                FROM sales 
                WHERE sale_date >= DATE(?, '-{$dateSub}')");
            $stmt->execute([date('Y-m-d')]);
            $periodData = $stmt->fetch(PDO::FETCH_ASSOC);
            $periodData['period'] = $period;
            $timeAnalysis[] = $periodData;
        }
        
        // Best items by various metrics
        $bestItemsStmt = $pdo->query("SELECT 
            i.name,
            i.category,
            SUM(s.sale_price - s.purchase_price - s.shipping_cost) as total_profit,
            COUNT(*) as sales_count,
            AVG(s.sale_price - s.purchase_price - s.shipping_cost) as avg_profit,
            MIN(s.sale_date - i.purchase_date) as avg_days_to_sell
            FROM sales s 
            JOIN items i ON s.item_id = i.id 
            GROUP BY i.id, i.name, i.category 
            ORDER BY total_profit DESC 
            LIMIT 10");
        $bestItems = $bestItemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $report = [
            'success' => true,
            'summary' => [
                'total_profit' => $profit['net_profit'] ?? 0,
                'total_sales' => $profit['total_sales'] ?? 0,
                'avg_profit_per_sale' => $profit['avg_profit_per_sale'] ?? 0
            ],
            'by_category' => $categorySales,
            'by_platform' => $platformSales,
            'time_analysis' => $timeAnalysis,
            'best_items' => $bestItems
        ];
        
        echo json_encode($report);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate report: ' . $e->getMessage()]);
    }
}

function generateReport($data) {
    global $pdo;
    
    $report_type = $data['type'] ?? 'profit';
    $start_date = $data['start_date'] ?? date('Y-m-d', strtotime('-1 year'));
    $end_date = $data['end_date'] ?? date('Y-m-d');
    
    try {
        switch ($report_type) {
            case 'profit':
                $stmt = $pdo->prepare("SELECT 
                    SUM(sale_price - purchase_price - shipping_cost) as total_profit,
                    COUNT(*) as total_sales,
                    AVG(sale_price - purchase_price - shipping_cost) as avg_profit_per_sale,
                    MIN(sale_price - purchase_price - shipping_cost) as min_profit,
                    MAX(sale_price - purchase_price - shipping_cost) as max_profit
                    FROM sales 
                    WHERE sale_date BETWEEN ? AND ?");
                $stmt->execute([$start_date, $end_date]);
                break;
                
            case 'tax':
                $stmt = $pdo->prepare("SELECT 
                    SUM(sale_price - purchase_price - shipping_cost) as taxable_income,
                    COUNT(*) as transaction_count,
                    SUM(sale_price) as total_revenue,
                    SUM(purchase_price) as total_cost
                    FROM sales 
                    WHERE sale_date BETWEEN ? AND ?");
                $stmt->execute([$start_date, $end_date]);
                break;
                
            case 'category':
                $stmt = $pdo->prepare("SELECT 
                    c.name, 
                    SUM(s.sale_price - s.purchase_price - s.shipping_cost) as category_profit,
                    COUNT(*) as sales_count,
                    AVG(s.sale_price - s.purchase_price - s.shipping_cost) as avg_profit
                    FROM sales s 
                    JOIN items i ON s.item_id = i.id 
                    JOIN categories c ON i.category = c.id 
                    WHERE s.sale_date BETWEEN ? AND ? 
                    GROUP BY c.name 
                    ORDER BY category_profit DESC");
                $stmt->execute([$start_date, $end_date]);
                break;
                
            case 'inventory':
                $stmt = $pdo->query("SELECT 
                    i.name,
                    i.quantity,
                    i.current_retail_price,
                    i.purchase_price,
                    (i.current_retail_price * i.quantity) as estimated_value
                    FROM items i 
                    WHERE i.quantity > 0 
                    ORDER BY estimated_value DESC");
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid report type']);
                return;
        }
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $result, 'period' => "$start_date to $end_date"]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate report: ' . $e->getMessage()]);
    }
}

function getTaxReport() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT 
            SUM(sale_price - purchase_price - shipping_cost) as total_taxable_income,
            COUNT(*) as total_transactions,
            SUM(sale_price) as total_revenue,
            SUM(purchase_price) as total_cost_basis,
            SUM(shipping_cost) as total_shipping
            FROM sales");
        $taxReport = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $taxReport]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate tax report: ' . $e->getMessage()]);
    }
}