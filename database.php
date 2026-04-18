<?php
/**
 * Database connection and configuration
 */

// Configuration
$config = [
    'db_type' => getenv('DB_TYPE') ?: 'mysql',
    'host' => getenv('DB_HOST') ?: 'localhost',
    'dbname' => getenv('DB_NAME') ?: 'purchase_tracker',
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') ?: '',
    'charset' => 'utf8mb4',
];

// Connection string based on database type
if ($config['db_type'] === 'sqlite') {
    $dsn = 'sqlite:' . __DIR__ . '/data/purchase_tracker.db';
} else {
    $dsn = "{$config['db_type']}:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
}

// Create PDO connection
try {
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Create tables if they don't exist (for SQLite development)
if ($config['db_type'] === 'sqlite') {
    $pdo->exec("CREATE TABLE IF NOT EXISTS items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        purchase_date DATE NOT NULL,
        purchase_price DECIMAL(10,2) NOT NULL,
        purchase_location TEXT,
        purchase_notes TEXT,
        category TEXT,
        current_retail_price DECIMAL(10,2),
        quantity INTEGER DEFAULT 1,
        condition TEXT,
        photo_path TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sales (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        item_id INTEGER NOT NULL,
        sale_date DATE NOT NULL,
        sale_price DECIMAL(10,2) NOT NULL,
        sale_platform TEXT,
        sale_location TEXT,
        sale_notes TEXT,
        packing_method TEXT,
        shipping_cost DECIMAL(10,2) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (item_id) REFERENCES items(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE NOT NULL,
        description TEXT
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS locations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE NOT NULL,
        type TEXT,
        notes TEXT
    )");
}

