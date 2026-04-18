<?php
/**
 * Main Dashboard Page
 * Mobile-friendly purchase tracker dashboard
 */

require_once 'database.php';
require_once 'functions.php';

// Get summary data
try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Total profit
    $profitStmt = $pdo->query("SELECT SUM(sale_price - purchase_price - shipping_cost) as net_profit FROM sales s JOIN items i ON s.item_id = i.id");
    $profit = $profitStmt->fetch(PDO::FETCH_ASSOC);
    
    // Total items
    $itemsStmt = $pdo->query("SELECT COUNT(*) as total_items FROM items");
    $items = $itemsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Recent sales
    $recentSalesStmt = $pdo->query("SELECT s.*, i.name as item_name FROM sales s JOIN items i ON s.item_id = i.id ORDER BY s.created_at DESC LIMIT 5");
    $recentSales = $recentSalesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Inventory value
    $inventoryStmt = $pdo->query("SELECT SUM(current_retail_price * quantity) as inventory_value FROM items");
    $inventory = $inventoryStmt->fetch(PDO::FETCH_ASSOC);
    
    $summary = [
        'profit' => $profit['net_profit'] ?? 0,
        'total_items' => $items['total_items'] ?? 0,
        'recent_sales' => $recentSales,
        'inventory_value' => $inventory['inventory_value'] ?? 0
    ];
} catch (PDOException $e) {
    $summary = ['error' => 'Database connection failed'];
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Tracker - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Work+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .bento-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 1.5rem;
        }
    </style>
</head>
<body class="bg-surface font-body text-on-surface antialiased min-h-screen pb-32">
    <header class="fixed top-0 left-0 w-full z-50 flex items-center justify-between px-6 py-4 bg-[#fff8f6]/80 dark:bg-[#1e1b1a]/80 backdrop-blur-xl">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full overflow-hidden border-2 border-primary-fixed">
                <img alt="user profile" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCLBx1hrtbGUdBsoZEXOQmXRCBh93kL_yHdkqpSX2F7uWFoRDwzGbnqpNX4F5FvY6QigQwO7l_PaQ_UuhE8XoVWKW6U_Bn4JgxBD2l2Q-EzsuTg9ypy0DRnckREEaBqiBMN25ItEm9A5ldsP2uKEzzbbhSn-fRAhLDSz_0iq4DNKbelLm4ferHJrv6FK4lliPsB37czaHBk2XcLQLgU66NMzDQymEquWY1hYufbwHzOAPS_TeaI6uSCZczrl90XDbsKReTOp-xNr1U2"/>
            </div>
            <span class="font-['Space_Grotesk'] tracking-tighter font-bold uppercase text-2xl font-black tracking-tighter text-[#a62639] dark:text-[#db324d]">Bought It</span>
        </div>
        <button class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-[#f4eceb] transition-colors">
            <span class="material-symbols-outlined text-[#a62639]">notifications</span>
        </button>
    </header>

    <main class="pt-24 px-6 max-w-7xl mx-auto">
        <!-- Hero: Total Net Profit Card -->
        <section class="mb-10">
            <div class="relative overflow-hidden rounded-xl p-8 bg-gradient-to-br from-[#a62639] to-[#511c29] text-white shadow-2xl shadow-[#a62639]/20">
                <div class="relative z-10">
                    <span class="font-headline text-sm font-medium tracking-[0.2em] uppercase opacity-80">Total Net Profit</span>
                    <div class="flex items-baseline gap-2 mt-2">
                        <span class="text-2xl font-light opacity-90">$</span>
                        <h1 class="text-7xl font-headline font-bold tracking-tighter"><?= number_format($summary['profit'], 2) ?></h1>
                    </div>
                    <div class="mt-8 flex items-center gap-4">
                        <div class="flex items-center gap-1 bg-white/10 backdrop-blur-md px-3 py-1 rounded-full text-xs font-semibold">
                            <span class="material-symbols-outlined text-sm">trending_up</span>
                            <span>+12.4% THIS MONTH</span>
                        </div>
                    </div>
                </div>
                <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-white/5 rounded-full blur-3xl"></div>
                <div class="absolute top-0 right-0 p-8 opacity-20">
                    <span class="material-symbols-outlined text-9xl" style="font-variation-settings: 'FILL' 1;">payments</span>
                </div>
            </div>
        </section>

        <!-- Metrics Row 1: Primary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
            <div class="bg-surface-container-low p-6 rounded-xl border-l-4 border-primary">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs font-bold font-headline uppercase tracking-widest text-outline">Inventory Value</p>
                        <h3 class="text-3xl font-headline font-bold mt-1 text-on-surface">$<?= number_format($summary['inventory_value'], 2) ?></h3>
                    </div>
                    <span class="material-symbols-outlined text-primary bg-primary-fixed p-2 rounded-lg">inventory_2</span>
                </div>
                <div class="mt-4 w-full bg-surface-container-highest h-1 rounded-full overflow-hidden">
                    <div class="bg-primary h-full" style="width: 65%;"></div>
                </div>
                <p class="text-[10px] mt-2 font-medium text-secondary uppercase tracking-tight">65% of warehouse capacity utilized</p>
            </div>
            <div class="bg-surface-container-low p-6 rounded-xl border-l-4 border-tertiary">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs font-bold font-headline uppercase tracking-widest text-outline">Total Items</p>
                        <h3 class="text-3xl font-headline font-bold mt-1 text-on-surface"><?= $summary['total_items'] ?></h3>
                    </div>
                    <span class="material-symbols-outlined text-tertiary bg-tertiary-fixed p-2 rounded-lg">inventory_2</span>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-surface-container-highest rounded-xl overflow-hidden">
            <div class="p-6">
                <h2 class="font-headline font-bold text-xl uppercase tracking-tighter">Recent Activity</h2>
                <div class="space-y-4 mt-4">
                    <?php foreach ($summary['recent_sales'] as $sale): ?>
                    <div class="flex gap-4 items-start group">
                        <div class="w-10 h-10 shrink-0 rounded-lg bg-surface-container-low flex items-center justify-center transition-colors group-hover:bg-primary-fixed">
                            <span class="material-symbols-outlined text-sm text-primary">sell</span>
                        </div>
                        <div class="border-b border-outline-variant/30 pb-4 w-full">
                            <p class="text-sm font-bold text-on-surface"><?= htmlspecialchars($sale['item_name']) ?></p>
                            <p class="text-xs text-secondary mt-1">Sold for $<?= number_format($sale['sale_price'], 2) ?> to <?= htmlspecialchars($sale['sale_platform']) ?></p>
                            <span class="text-[9px] font-bold uppercase tracking-widest text-outline mt-2 block"><?= date('M j, Y', strtotime($sale['sale_date'])) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>

    <nav class="fixed bottom-0 left-0 w-full z-50 flex justify-around items-center px-4 py-3 pb-6 bg-[#fff8f6]/90 dark:bg-[#1e1b1a]/90 backdrop-blur-2xl border-t-[0.5px] border-[#e0bfbf]/20 shadow-[0_-4px_30px_rgba(30,27,26,0.04)] rounded-t-xl">
        <a class="flex flex-col items-center justify-center bg-[#a62639] text-white rounded-lg px-4 py-1.5 transition-transform active:scale-90" href="#">
            <span class="material-symbols-outlined">dashboard</span>
            <span class="font-['Space_Grotesk'] text-[10px] font-bold uppercase tracking-widest mt-1">Dashboard</span>
        </a>
        <a class="flex flex-col items-center justify-center text-[#56494e] dark:text-[#a29c9b] px-4 py-1.5 hover:bg-[#eedbe1] dark:hover:bg-[#511c29] transition-all" href="#">
            <span class="material-symbols-outlined">inventory_2</span>
            <span class="font-['Space_Grotesk'] text-[10px] font-bold uppercase tracking-widest mt-1">Inventory</span>
        </a>
        <a class="flex flex-col items-center justify-center text-[#56494e] dark:text-[#a29c9b] px-4 py-1.5 hover:bg-[#eedbe1] dark:hover:bg-[#511c29] transition-all" href="#">
            <span class="material-symbols-outlined">payments</span>
            <span class="font-['Space_Grotesk'] text-[10px] font-bold uppercase tracking-widest mt-1">Sales</span>
        </a>
        <a class="flex flex-col items-center justify-center text-[#56494e] dark:text-[#a29c9b] px-4 py-1.5 hover:bg-[#eedbe1] dark:hover:bg-[#511c29] transition-all" href="#">
            <span class="material-symbols-outlined">monitoring</span>
            <span class="font-['Space_Grotesk'] text-[10px] font-bold uppercase tracking-widest mt-1">Stats</span>
        </a>
        <a class="flex flex-col items-center justify-center text-[#56494e] dark:text-[#a29c9b] px-4 py-1.5 hover:bg-[#eedbe1] dark:hover:bg-[#511c29] transition-all" href="#">
            <span class="material-symbols-outlined">settings</span>
            <span class="font-['Space_Grotesk'] text-[10px] font-bold uppercase tracking-widest mt-1">Settings</span>
        </a>
    </nav>
</body>
</html>