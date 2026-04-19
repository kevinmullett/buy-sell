<?php
/**
 * Database connection — production-aware SQLite path
 */

// On shared hosting, store the DB one level ABOVE the web root for security.
// If that directory isn't writable, fall back to the local data/ dir.
$possiblePaths = [
    dirname(__DIR__) . '/bought-it-data/purchase_tracker.db',  // above web root (preferred)
    __DIR__ . '/data/purchase_tracker.db',                      // local fallback (Docker/dev)
];

$dbPath = null;
foreach ($possiblePaths as $path) {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    if (is_dir($dir) && is_writable($dir)) {
        $dbPath = $path;
        break;
    }
}

if (!$dbPath) {
    // Last resort — try creating local data dir
    $dbPath = __DIR__ . '/data/purchase_tracker.db';
    @mkdir(dirname($dbPath), 0777, true);
}

try {
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// ── Items ────────────────────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS items (
    id                   INTEGER PRIMARY KEY AUTOINCREMENT,
    name                 TEXT    NOT NULL,
    purchase_date        DATE    NOT NULL,
    purchase_price       DECIMAL(10,2) NOT NULL,
    purchase_location    TEXT,
    purchase_type        TEXT    DEFAULT 'Standard',
    purchase_notes       TEXT,
    category             TEXT,
    current_retail_price DECIMAL(10,2),
    quantity             INTEGER DEFAULT 1,
    condition            TEXT    DEFAULT 'Good',
    status               TEXT    DEFAULT 'Available',
    packaging            TEXT,
    ebay_listing_url     TEXT,
    ebay_listing_id      TEXT,
    photo_path           TEXT,
    is_archived          INTEGER DEFAULT 0,
    archived_at          DATETIME,
    created_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$existingCols = array_column(
    $pdo->query("PRAGMA table_info(items)")->fetchAll(), 'name'
);
$newCols = [
    'packaging'        => "ALTER TABLE items ADD COLUMN packaging TEXT",
    'ebay_listing_url' => "ALTER TABLE items ADD COLUMN ebay_listing_url TEXT",
    'ebay_listing_id'  => "ALTER TABLE items ADD COLUMN ebay_listing_id TEXT",
    'is_archived'      => "ALTER TABLE items ADD COLUMN is_archived INTEGER DEFAULT 0",
    'archived_at'      => "ALTER TABLE items ADD COLUMN archived_at DATETIME",
];
foreach ($newCols as $col => $sql) {
    if (!in_array($col, $existingCols)) { $pdo->exec($sql); }
}

// ── Sales ────────────────────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS sales (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id        INTEGER NOT NULL,
    sale_date      DATE    NOT NULL,
    sale_price     DECIMAL(10,2) NOT NULL,
    sale_platform  TEXT,
    sale_location  TEXT,
    sale_notes     TEXT,
    packing_method TEXT,
    shipping_cost  DECIMAL(10,2) DEFAULT 0,
    fees           DECIMAL(10,2) DEFAULT 0,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
)");

// Check for fees column in sales
$salesCols = array_column($pdo->query("PRAGMA table_info(sales)")->fetchAll(), 'name');
if (!in_array('fees', $salesCols)) {
    $pdo->exec("ALTER TABLE sales ADD COLUMN fees DECIMAL(10,2) DEFAULT 0");
}

// ── Item Photos ───────────────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS item_photos (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id       INTEGER NOT NULL,
    file_path     TEXT    NOT NULL,
    original_name TEXT,
    file_type     TEXT,
    file_size     INTEGER,
    photo_type    TEXT    DEFAULT 'item',
    caption       TEXT,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS photos (
    id INTEGER PRIMARY KEY AUTOINCREMENT, item_id INTEGER NOT NULL,
    file_path TEXT NOT NULL, original_name TEXT, file_type TEXT,
    file_size INTEGER, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
)");

// ── Lookup tables ─────────────────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS categories (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE NOT NULL, description TEXT)");
$pdo->exec("CREATE TABLE IF NOT EXISTS locations (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE NOT NULL, type TEXT, notes TEXT)");
$pdo->exec("CREATE TABLE IF NOT EXISTS packaging_options (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE NOT NULL)");

// ── Indexes ───────────────────────────────────────────────────────────────────
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_items_status      ON items(status)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_items_is_archived ON items(is_archived)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_items_created_at  ON items(created_at)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_sales_item_id     ON sales(item_id)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_sales_sale_date   ON sales(sale_date)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_photos_item_id    ON item_photos(item_id)");

// ── Seed categories ───────────────────────────────────────────────────────────
if ($pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn() == 0) {
    $cats = [
        ['Electronics','Phones, computers, cameras, gadgets'],
        ['Clothing & Apparel','Shirts, shoes, accessories'],
        ['Home & Garden','Furniture, decor, tools'],
        ['Books & Media','Books, DVDs, vinyl, games'],
        ['Sports & Outdoors','Equipment, gear, fitness'],
        ['Toys & Games','Board games, action figures, collectibles'],
        ['Kitchen & Dining','Appliances, cookware, dishes'],
        ['Health & Beauty','Personal care, cosmetics'],
        ['Automotive','Parts, accessories, tools'],
        ['Collectibles & Art','Antiques, art, coins, stamps'],
        ['Other','Miscellaneous items'],
    ];
    $s = $pdo->prepare("INSERT OR IGNORE INTO categories (name,description) VALUES (?,?)");
    foreach ($cats as $c) { $s->execute($c); }
}

// ── Seed locations ────────────────────────────────────────────────────────────
if ($pdo->query("SELECT COUNT(*) FROM locations")->fetchColumn() == 0) {
    $locs = [
        ['Goodwill','Thrift Store'],['Salvation Army','Thrift Store'],
        ['Bargain Lane','Bin Store'],['Mega Markdown','Bin Store'],
        ['Super Hot Deals','Bin Store'],['Facebook Marketplace','Online'],
        ['eBay','Online'],['Garage Sale','Garage Sale'],
        ['Estate Sale','Estate Sale'],['Auction House','Auction'],
        ['Retail Store','Retail'],['Pallet Sale','Pallet'],
    ];
    $s = $pdo->prepare("INSERT OR IGNORE INTO locations (name,type) VALUES (?,?)");
    foreach ($locs as $l) { $s->execute($l); }
}

// ── Seed packaging ────────────────────────────────────────────────────────────
if ($pdo->query("SELECT COUNT(*) FROM packaging_options")->fetchColumn() == 0) {
    $pkgs = ['USPS Flat Rate Env std','USPS Flat Rate Env lrg','USPS Flat Rate Bubble',
             'Green Bubble 6x9','Black 6x9','Small Box','Med Box','Large Box'];
    $s = $pdo->prepare("INSERT OR IGNORE INTO packaging_options (name) VALUES (?)");
    foreach ($pkgs as $p) { $s->execute([$p]); }
}
