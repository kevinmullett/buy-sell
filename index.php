<?php
/**
 * Bought It — Purchase Tracker
 * Main entry point / Single-Page Application
 */

// Initialize database (creates tables if needed)
require_once 'database.php';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Bought It — Purchase Tracker</title>
    <meta name="description" content="Track purchases from bin stores, auctions, thrift shops and resell on eBay, Facebook Marketplace. Profit tracking, tax reports, inventory management.">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Work+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=3">
</head>
<body>
    <!-- Header -->
    <header class="app-header" id="app-header">
        <div class="app-header__brand">
            <span class="material-symbols-outlined" style="font-size: 1.75rem; font-variation-settings: 'FILL' 1;">shopping_bag</span>
            <span class="brand-text">Bought It</span>
        </div>
        <div class="app-header__actions">
            <button class="icon-btn" id="darkModeToggle" onclick="app.toggleDarkMode()" title="Toggle dark mode">
                <span class="material-symbols-outlined">dark_mode</span>
            </button>
        </div>
    </header>

    <!-- Main Content -->
    <main class="app-main" id="app-main">
        <!-- ========== DASHBOARD VIEW ========== -->
        <section id="view-dashboard" class="view active">
            <!-- Hero: Net Profit Card -->
            <div class="hero-card">
                <div class="hero-card__content">
                    <span class="hero-card__label">Total Net Profit</span>
                    <div class="hero-card__value">
                        <span class="hero-card__currency">$</span>
                        <span id="dash-profit">0.00</span>
                    </div>
                    <div class="hero-card__badge">
                        <span class="material-symbols-outlined" style="font-size: 14px;">trending_up</span>
                        <span id="dash-month-label">THIS MONTH</span>
                    </div>
                </div>
                <div class="hero-card__bg-icon">
                    <span class="material-symbols-outlined" style="font-size: 120px; font-variation-settings: 'FILL' 1;">payments</span>
                </div>
            </div>

            <!-- Metrics Row -->
            <div class="metrics-grid">
                <div class="metric-card metric-card--primary">
                    <div class="metric-card__header">
                        <div>
                            <p class="metric-card__label">Inventory Value</p>
                            <h3 class="metric-card__value" id="dash-inventory-value">$0</h3>
                        </div>
                        <div class="metric-card__icon metric-card__icon--primary">
                            <span class="material-symbols-outlined">inventory_2</span>
                        </div>
                    </div>
                    <div class="metric-card__footer">
                        <span id="dash-available-count">0</span> items available
                    </div>
                </div>
                <div class="metric-card metric-card--tertiary">
                    <div class="metric-card__header">
                        <div>
                            <p class="metric-card__label">Sales This Month</p>
                            <h3 class="metric-card__value" id="dash-month-revenue">$0</h3>
                        </div>
                        <div class="metric-card__icon metric-card__icon--tertiary">
                            <span class="material-symbols-outlined">shopping_cart</span>
                        </div>
                    </div>
                    <div class="metric-card__footer">
                        <span class="badge badge--accent" id="dash-month-sales-count">0 sales</span>
                    </div>
                </div>
            </div>

            <!-- Velocity Row -->
            <div class="metrics-grid">
                <div class="metric-card metric-card--flat">
                    <p class="metric-card__label">Days Since Last Sale</p>
                    <div class="metric-card__inline">
                        <span class="metric-card__big-num" id="dash-days-since-sale">—</span>
                        <span class="metric-card__unit">Days</span>
                    </div>
                </div>
                <div class="metric-card metric-card--flat">
                    <p class="metric-card__label">Days Since New Listing</p>
                    <div class="metric-card__inline">
                        <span class="metric-card__big-num" id="dash-days-since-item">—</span>
                        <span class="metric-card__unit">Days</span>
                    </div>
                </div>
            </div>

            <!-- Items Summary Row -->
            <div class="metrics-grid">
                <div class="metric-card metric-card--flat">
                    <p class="metric-card__label">Avg Days to Sell</p>
                    <div class="metric-card__inline">
                        <span class="metric-card__big-num" id="dash-avg-days">—</span>
                        <span class="metric-card__unit">Days</span>
                    </div>
                </div>
                <div class="metric-card metric-card--flat">
                    <p class="metric-card__label">Stale Items <small style="font-weight:400">(60+ days)</small></p>
                    <div class="metric-card__inline">
                        <span class="metric-card__big-num" id="dash-stale" style="color:var(--warning)">0</span>
                        <span class="metric-card__unit">Items</span>
                    </div>
                </div>
            </div>

            <!-- Items Split -->
            <div class="items-split-bar section-card" style="padding: 12px 16px;">
                <div class="items-split-bar__label">Inventory</div>
                <div class="items-split-bar__counts">
                    <span class="status--available" style="padding:3px 10px;border-radius:20px;font-size:0.75rem;font-weight:700"><span id="dash-avail-n">0</span> Available</span>
                    <span style="padding:3px 10px;border-radius:20px;font-size:0.75rem;font-weight:700;background:var(--surface-container-highest);color:var(--primary)"><span id="dash-listed-n">0</span> Listed on eBay</span>
                    <span class="status--sold" style="padding:3px 10px;border-radius:20px;font-size:0.75rem;font-weight:700"><span id="dash-sold-n">0</span> Sold</span>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="section-card">
                <div class="section-card__header">
                    <h2 class="section-card__title">Recent Activity</h2>
                </div>
                <div id="dash-recent-activity" class="activity-list">
                    <div class="empty-state">
                        <span class="material-symbols-outlined">history</span>
                        <p>No activity yet. Add your first item!</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========== INVENTORY VIEW ========== -->
        <section id="view-inventory" class="view">
            <!-- Search & Filters -->
            <div class="search-bar">
                <span class="material-symbols-outlined">search</span>
                <input type="text" id="inventory-search" placeholder="Search items, SKUs, or locations..." oninput="app.filterInventory()">
            </div>
            <div class="filter-chips" id="inventory-filters">
                <button class="chip chip--active" data-filter="all" onclick="app.setFilter('all')">All Items</button>
                <button class="chip" data-filter="Available" onclick="app.setFilter('Available')">Available</button>
                <button class="chip" data-filter="Sold" onclick="app.setFilter('Sold')">Sold</button>
                <button class="chip" data-filter="Pending" onclick="app.setFilter('Pending')">Pending</button>
            </div>
            <div style="display:flex;justify-content:flex-end;margin-bottom:8px">
                <select class="form-input" id="inventory-sort" style="width:auto;font-size:0.8rem;padding:6px 10px" onchange="app.loadInventory()">
                    <option value="created_at|DESC">Newest First</option>
                    <option value="created_at|ASC">Oldest First</option>
                    <option value="age_days|DESC">Most Stale First</option>
                    <option value="purchase_price|DESC">Price: High→Low</option>
                    <option value="name|ASC">Name A→Z</option>
                </select>
            </div>

            <!-- Inventory Header -->
            <div class="section-header">
                <h2 class="section-title">Inventory Overview</h2>
                <span class="count-badge" id="inventory-count">0 items</span>
            </div>

            <!-- Inventory List -->
            <div id="inventory-list" class="item-list">
                <div class="empty-state">
                    <span class="material-symbols-outlined">inventory_2</span>
                    <p>No items yet. Tap + to add your first purchase!</p>
                </div>
            </div>
        </section>

        <!-- ========== ADD ITEM VIEW ========== -->
        <section id="view-add" class="view">
            <div class="view-header">
                <span class="view-header__tag">Resource Management</span>
                <h1 class="view-header__title" id="add-form-title">Add New Item</h1>
                <p class="view-header__subtitle">Every detail contributes to your tracking accuracy.</p>
            </div>

            <form id="item-form" class="form-container" onsubmit="return app.handleItemForm(event)">
                <input type="hidden" id="edit-item-id" value="">

                <div class="form-group">
                    <label class="form-label" for="item-name">Item Name</label>
                    <div style="display:flex;gap:8px;align-items:stretch">
                        <input class="form-input" type="text" id="item-name" name="name" placeholder="e.g. Nike Air Zoom Pegasus" required style="flex:1">
                        <button type="button" class="btn btn--secondary" id="scan-btn" onclick="app.startScan()" title="Scan barcode" style="padding:0 14px;flex-shrink:0">
                            <span class="material-symbols-outlined" style="font-size:22px">barcode_scanner</span>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="item-category">Category</label>
                    <select class="form-input" id="item-category" name="category" required>
                        <option value="">Select Category</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="item-purchase-date">Purchase Date</label>
                    <input class="form-input" type="date" id="item-purchase-date" name="purchase_date" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Purchase Mode</label>
                    <div class="chip-group" id="purchase-mode-chips">
                        <button type="button" class="chip chip--active" data-value="Standard" onclick="app.selectChip(this, 'purchase-type')">Standard</button>
                        <button type="button" class="chip" data-value="Fill Your Bag" onclick="app.selectChip(this, 'purchase-type')">Fill Your Bag</button>
                        <button type="button" class="chip" data-value="Bulk" onclick="app.selectChip(this, 'purchase-type')">Bulk</button>
                        <button type="button" class="chip" data-value="Pallet" onclick="app.selectChip(this, 'purchase-type')">Pallet</button>
                    </div>
                    <input type="hidden" id="item-purchase-type" name="purchase_type" value="Standard">
                </div>

                <div class="form-group">
                    <label class="form-label" for="item-location">Purchase Location</label>
                    <div class="input-with-icon">
                        <span class="material-symbols-outlined">location_on</span>
                        <input class="form-input" type="text" id="item-location" name="purchase_location" placeholder="Store or Online Vendor" list="location-suggestions">
                        <datalist id="location-suggestions"></datalist>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group form-group--half">
                        <label class="form-label" for="item-quantity">Quantity</label>
                        <div class="input-with-icon">
                            <span class="material-symbols-outlined">shopping_cart</span>
                            <input class="form-input" type="number" id="item-quantity" name="quantity" value="1" min="1">
                        </div>
                    </div>
                    <div class="form-group form-group--half">
                        <label class="form-label" for="item-condition">Condition</label>
                        <select class="form-input" id="item-condition" name="condition">
                            <option value="New">New</option>
                            <option value="Like New">Like New</option>
                            <option value="Good" selected>Good</option>
                            <option value="Fair">Fair</option>
                            <option value="Poor">Poor</option>
                            <option value="For Parts / Not Working">For Parts / Not Working</option>
                            <option value="Incomplete / Missing Pieces">Incomplete / Missing Pieces</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="item-price">Purchase Price</label>
                    <div class="input-with-icon">
                        <span class="currency-symbol">$</span>
                        <input class="form-input form-input--currency" type="number" id="item-price" name="purchase_price" placeholder="0.00" step="0.01" min="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="item-retail-price">Estimated Retail Value (optional)</label>
                    <div class="input-with-icon">
                        <span class="currency-symbol">$</span>
                        <input class="form-input form-input--currency" type="number" id="item-retail-price" name="current_retail_price" placeholder="0.00" step="0.01" min="0">
                    </div>
                </div>

                <!-- Packaging -->
                <div class="form-group">
                    <label class="form-label" for="item-packaging">Packaging</label>
                    <div class="input-with-icon">
                        <span class="material-symbols-outlined">package_2</span>
                        <input class="form-input" type="text" id="item-packaging" name="packaging" placeholder="e.g. Green Bubble 6x9" list="packaging-suggestions">
                        <datalist id="packaging-suggestions"></datalist>
                    </div>
                </div>

                <!-- eBay Listing URL -->
                <div class="form-group">
                    <label class="form-label" for="item-ebay-url">eBay Listing URL (optional)</label>
                    <div class="input-with-icon">
                        <span class="material-symbols-outlined">link</span>
                        <input class="form-input" type="url" id="item-ebay-url" name="ebay_listing_url" placeholder="https://www.ebay.com/itm/...">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="item-notes">Additional Notes</label>
                    <textarea class="form-input form-input--textarea" id="item-notes" name="purchase_notes" placeholder="Depreciation details, warranty info, or notes..." rows="3"></textarea>
                </div>

                <button type="submit" class="btn btn--primary btn--full" id="item-submit-btn">
                    <span class="material-symbols-outlined">save</span>
                    Save Item
                </button>
                <button type="button" class="btn btn--text btn--full" onclick="app.cancelEdit()">Cancel</button>
            </form>

            <!-- Photo Upload Section (shown after save or for existing items) -->
            <div id="photo-upload-section" class="section-card" style="margin-top:16px;display:none">
                <h3 class="section-card__title">Photos</h3>
                <div class="photo-tabs">
                    <button class="chip chip--active" data-ptype="item" onclick="app.setPhotoType('item',this)">Item Photos</button>
                    <button class="chip" data-ptype="receipt" onclick="app.setPhotoType('receipt',this)">Receipt</button>
                </div>
                <input type="hidden" id="photo-upload-type" value="item">
                <div class="photo-upload-area" onclick="document.getElementById('photo-file-input').click()">
                    <span class="material-symbols-outlined" style="font-size:36px;color:var(--outline)">add_a_photo</span>
                    <p style="font-size:0.85rem;color:var(--text-secondary);margin-top:6px">Tap to add photos</p>
                </div>
                <input type="file" id="photo-file-input" accept="image/*" multiple capture="environment" style="display:none" onchange="app.uploadPhotos(event)">
                <div id="photo-preview-grid" class="photo-grid"></div>
            </div>

            <!-- Asset Insight -->
            <div class="insight-card">
                <span class="material-symbols-outlined">trending_up</span>
                <div>
                    <p class="insight-card__title">Asset Insight</p>
                    <p class="insight-card__text">Every entry improves your ledger's real-time accuracy and net worth visibility.</p>
                </div>
            </div>
        </section>

        <!-- Barcode Scanner Overlay -->
        <div id="scanner-overlay" class="scanner-overlay" style="display:none">
            <div class="scanner-overlay__header">
                <button onclick="app.stopScan()" class="icon-btn" style="color:white"><span class="material-symbols-outlined">close</span></button>
                <span style="color:white;font-family:var(--font-headline);font-weight:700">Scan Barcode</span>
                <div style="width:40px"></div>
            </div>
            <video id="scanner-video" autoplay playsinline style="width:100%;height:100%;object-fit:cover"></video>
            <div class="scanner-overlay__frame"></div>
            <p id="scanner-status" style="position:absolute;bottom:40px;left:0;right:0;text-align:center;color:white;font-size:0.9rem">Point camera at barcode</p>
        </div>

        <!-- ========== RECORD SALE VIEW ========== -->
        <section id="view-sell" class="view">
            <div class="view-header">
                <span class="view-header__tag">Record Sale</span>
                <h1 class="view-header__title">Sell Item</h1>
                <p class="view-header__subtitle">Record the sale details for profit tracking.</p>
            </div>

            <form id="sale-form" class="form-container" onsubmit="return app.handleSaleForm(event)">
                <div class="form-group">
                    <label class="form-label" for="sale-item">Item</label>
                    <select class="form-input" id="sale-item" name="item_id" required>
                        <option value="">Select Item to Sell</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="sale-date">Sale Date</label>
                    <input class="form-input" type="date" id="sale-date" name="sale_date" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="sale-price">Sale Price</label>
                    <div class="input-with-icon">
                        <span class="currency-symbol">$</span>
                        <input class="form-input form-input--currency" type="number" id="sale-price" name="sale_price" placeholder="0.00" step="0.01" min="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="sale-platform">Sale Platform</label>
                    <select class="form-input" id="sale-platform" name="sale_platform" required>
                        <option value="">Select Platform</option>
                        <option value="eBay">eBay</option>
                        <option value="Facebook Marketplace">Facebook Marketplace</option>
                        <option value="Garage Sale">Garage Sale</option>
                        <option value="Craigslist">Craigslist</option>
                        <option value="OfferUp">OfferUp</option>
                        <option value="Mercari">Mercari</option>
                        <option value="Poshmark">Poshmark</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="sale-shipping">Shipping Cost</label>
                    <div class="input-with-icon">
                        <span class="currency-symbol">$</span>
                        <input class="form-input form-input--currency" type="number" id="sale-shipping" name="shipping_cost" placeholder="0.00" step="0.01" min="0" value="0">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="sale-packing">Packing Method</label>
                    <input class="form-input" type="text" id="sale-packing" name="packing_method" placeholder="e.g. Bubble Mailer, Box, etc.">
                </div>

                <div class="form-group">
                    <label class="form-label" for="sale-notes">Sale Notes</label>
                    <textarea class="form-input form-input--textarea" id="sale-notes" name="sale_notes" placeholder="Buyer details, shipping notes..." rows="2"></textarea>
                </div>

                <button type="submit" class="btn btn--primary btn--full">
                    <span class="material-symbols-outlined">sell</span>
                    Record Sale
                </button>
            </form>
        </section>

        <!-- ========== REPORTS VIEW ========== -->
        <section id="view-reports" class="view">
            <div class="view-header">
                <span class="view-header__tag">Executive Summary</span>
                <h1 class="view-header__title">Sales & Reports</h1>
            </div>

            <!-- Date Filter -->
            <div class="date-filter-bar">
                <select class="form-input" id="rpt-year" onchange="app.onReportDateChange()" style="flex:1">
                </select>
                <select class="form-input" id="rpt-month" onchange="app.onReportDateChange()" style="flex:1">
                    <option value="">Full Year</option>
                    <option value="1">January</option><option value="2">February</option>
                    <option value="3">March</option><option value="4">April</option>
                    <option value="5">May</option><option value="6">June</option>
                    <option value="7">July</option><option value="8">August</option>
                    <option value="9">September</option><option value="10">October</option>
                    <option value="11">November</option><option value="12">December</option>
                </select>
            </div>

            <!-- Report Type Tabs -->
            <div class="filter-chips" id="report-tabs">
                <button class="chip chip--active" data-tab="overview" onclick="app.showReportTab('overview')">Overview</button>
                <button class="chip" data-tab="sales-list" onclick="app.showReportTab('sales-list')">Sales History</button>
                <button class="chip" data-tab="tax" onclick="app.showReportTab('tax')">Tax Report</button>
            </div>

            <!-- Overview Tab -->
            <div id="report-overview" class="report-tab active">
                <div class="metrics-grid">
                    <div class="metric-card metric-card--primary">
                        <p class="metric-card__label">Total Net Profit</p>
                        <h3 class="metric-card__value" id="rpt-net-profit">$0</h3>
                    </div>
                    <div class="metric-card metric-card--tertiary">
                        <p class="metric-card__label">Gross Revenue</p>
                        <h3 class="metric-card__value" id="rpt-revenue">$0</h3>
                    </div>
                </div>
                <div class="metrics-grid">
                    <div class="metric-card metric-card--flat">
                        <p class="metric-card__label">Average Margin</p>
                        <h3 class="metric-card__value" id="rpt-avg-margin">0%</h3>
                    </div>
                    <div class="metric-card metric-card--flat">
                        <p class="metric-card__label">Items Sold</p>
                        <h3 class="metric-card__value" id="rpt-sold-count">0</h3>
                    </div>
                </div>

                <!-- Profit by Category -->
                <div class="section-card">
                    <h3 class="section-card__title">Profit by Category</h3>
                    <div id="rpt-by-category" class="report-list"></div>
                </div>

                <!-- Profit by Platform -->
                <div class="section-card">
                    <h3 class="section-card__title">Profit by Platform</h3>
                    <div id="rpt-by-platform" class="report-list"></div>
                </div>

                <!-- Profit by Source -->
                <div class="section-card">
                    <h3 class="section-card__title">Profit by Source</h3>
                    <div id="rpt-by-source" class="report-list"></div>
                </div>

                <!-- Best Items -->
                <div class="section-card">
                    <h3 class="section-card__title">Top Performers</h3>
                    <div id="rpt-best-items" class="report-list"></div>
                </div>
            </div>

            <!-- Sales History Tab -->
            <div id="report-sales-list" class="report-tab">
                <div id="rpt-sales-history" class="item-list"></div>
            </div>

            <!-- Tax Report Tab -->
            <div id="report-tax" class="report-tab">
                <div class="section-card">
                    <div class="section-card__header">
                        <h3 class="section-card__title">Schedule C</h3>
                    </div>
                    <div id="rpt-tax-data" class="tax-report"></div>
                    <button class="btn btn--secondary" style="margin-top:16px;width:100%" onclick="app.exportTaxCSV()">
                        <span class="material-symbols-outlined">download</span>
                        Download Tax CSV
                    </button>
                </div>
            </div>
        </section>

        <!-- ========== SETTINGS VIEW ========== -->
        <section id="view-settings" class="view">
            <div class="view-header">
                <h1 class="view-header__title">Settings</h1>
            </div>

            <!-- Standard CSV Import -->
            <div class="section-card">
                <h3 class="section-card__title">Import Data (CSV)</h3>
                <p style="margin-bottom:1rem;font-size:0.875rem;color:var(--text-secondary)">Import purchases or sales from a CSV file.</p>
                <div class="form-group">
                    <label class="form-label" for="csv-import-type">Import Type</label>
                    <select class="form-input" id="csv-import-type">
                        <option value="purchase">Purchases</option>
                        <option value="sale">Sales</option>
                    </select>
                </div>
                <div class="form-group">
                    <input type="file" id="csv-import-file" accept=".csv" class="form-input">
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <button class="btn btn--primary" onclick="app.importCSV()">
                        <span class="material-symbols-outlined">upload_file</span>
                        Import CSV
                    </button>
                    <button class="btn btn--secondary" onclick="app.downloadTemplate()">
                        <span class="material-symbols-outlined">download</span>
                        Template
                    </button>
                </div>
                <div id="import-result" class="import-result" style="display:none"></div>
            </div>

            <!-- Master Inventory Import -->
            <div class="section-card">
                <h3 class="section-card__title">Import Master Inventory <span class="badge-tag badge-tag--success">XLSX</span></h3>
                <p style="margin-bottom:0.5rem;font-size:0.875rem;color:var(--text-secondary)">
                    Upload your master inventory spreadsheet. The system will automatically read all tabs, ignoring hidden and summary tabs, and parse out dates, prices, quantities, and condition flags based on your notes (e.g. <code>$8 - 05/10/25 (keeping)</code>).
                </p>
                <div class="form-group">
                    <label class="form-label" style="font-size:0.8rem">Select your master inventory XLSX file</label>
                    <input type="file" id="inventory-import-file" accept=".xlsx" class="form-input">
                </div>
                <button class="btn btn--primary" id="inventory-import-btn" onclick="app.importMasterInventory()">
                    <span class="material-symbols-outlined">inventory_2</span>
                    Import Inventory
                </button>
                <div id="inventory-import-result" class="import-result" style="display:none;margin-top:10px"></div>
            </div>

            <!-- eBay Seller Hub Orders CSV (May 2024 → present) -->
            <div class="section-card">
                <h3 class="section-card__title">Import eBay Seller Hub Orders <span class="badge-tag">May 2024 → Now</span></h3>
                <p style="margin-bottom:0.75rem;font-size:0.875rem;color:var(--text-secondary)">
                    Go to <strong>Seller Hub → Orders → Download Report</strong>. You can select date ranges and export multiple CSVs. Select all of them here at once.
                </p>
                <div class="form-group">
                    <label class="form-label" style="font-size:0.8rem">Select one or more CSV files</label>
                    <input type="file" id="ebay-import-file" accept=".csv" multiple class="form-input">
                </div>
                <button class="btn btn--primary" id="ebay-import-btn" onclick="app.importEbayCSVBulk()">
                    <span class="material-symbols-outlined">upload_file</span>
                    Import eBay Sales
                </button>
                <div id="ebay-import-progress" class="bulk-progress" style="display:none"></div>
                <div id="ebay-import-result" class="import-result" style="display:none"></div>
            </div>

            <!-- eBay PDF Statement Import (Jan 2022 – Apr 2024) -->
            <div class="section-card">
                <h3 class="section-card__title">Import eBay PDF Statements <span class="badge-tag badge-tag--warn">2022 – Apr 2024</span></h3>
                <p style="margin-bottom:0.5rem;font-size:0.875rem;color:var(--text-secondary)">
                    Open each monthly PDF in <strong>Adobe Acrobat → File → Export To → XML Spreadsheet</strong>, then select all the XML files here at once.
                </p>
                <div class="info-banner" style="background:var(--surface-container);border-left:3px solid var(--warning);padding:10px 14px;border-radius:6px;margin-bottom:12px;font-size:0.8rem;color:var(--text-secondary)">
                    <strong style="color:var(--on-surface)">⚠ PDF Limitations:</strong> Item titles truncated (~50 chars), shipping included in sale total. Add purchase prices later via the Needs Cost queue.
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-size:0.8rem">Select all statement XMLs at once (28 files, Jan 2022 – Apr 2024)</label>
                    <input type="file" id="statement-import-file" accept=".xml,.xlsx" multiple class="form-input">
                </div>
                <button class="btn btn--primary" id="statement-import-btn" onclick="app.importStatementBulk()">
                    <span class="material-symbols-outlined">upload_file</span>
                    Import All Statements
                </button>
                <div id="statement-import-progress" class="bulk-progress" style="display:none"></div>
                <div id="statement-import-result" class="import-result" style="display:none"></div>
            </div>

            <div class="section-card">
                <h3 class="section-card__title">Appearance</h3>
                <div class="setting-row">
                    <div>
                        <span class="setting-row__label">Dark Mode</span>
                        <span class="setting-row__desc">Use dark color scheme</span>
                    </div>
                    <label class="toggle">
                        <input type="checkbox" id="setting-dark-mode" onchange="app.toggleDarkMode()">
                        <span class="toggle__slider"></span>
                    </label>
                </div>
            </div>

            <div class="section-card">
                <h3 class="section-card__title">Data Management</h3>
                <button class="btn btn--secondary" onclick="app.exportData()">
                    <span class="material-symbols-outlined">download</span>
                    Export All Data (CSV)
                </button>
            </div>

            <div class="section-card">
                <h3 class="section-card__title">About</h3>
                <p style="font-size: 0.875rem; color: var(--text-secondary);">
                    <strong>Bought It</strong> v1.0<br>
                    Purchase tracker for resellers.<br>
                    Track buys, sales, profits, and taxes.
                </p>
            </div>
        </section>
    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav" id="bottom-nav">
        <a class="bottom-nav__item bottom-nav__item--active" href="#" data-view="dashboard" onclick="app.navigate('dashboard', event)">
            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">dashboard</span>
            <span class="bottom-nav__label">Dashboard</span>
        </a>
        <a class="bottom-nav__item" href="#" data-view="inventory" onclick="app.navigate('inventory', event)">
            <span class="material-symbols-outlined">inventory_2</span>
            <span class="bottom-nav__label">Inventory</span>
        </a>
        <a class="bottom-nav__item bottom-nav__item--add" href="#" data-view="add" onclick="app.navigate('add', event)">
            <span class="material-symbols-outlined">add</span>
        </a>
        <a class="bottom-nav__item" href="#" data-view="reports" onclick="app.navigate('reports', event)">
            <span class="material-symbols-outlined">monitoring</span>
            <span class="bottom-nav__label">Reports</span>
        </a>
        <a class="bottom-nav__item" href="#" data-view="settings" onclick="app.navigate('settings', event)">
            <span class="material-symbols-outlined">settings</span>
            <span class="bottom-nav__label">Settings</span>
        </a>
    </nav>

    <!-- Toast Notification -->
    <div id="toast" class="toast"></div>

    <!-- Loading Overlay -->
    <div id="loading" class="loading-overlay" style="display: none;">
        <div class="loading-spinner"></div>
    </div>

    <script src="js/app.js?v=3"></script>
</body>
</html>