<?php
/**
 * Utility Functions
 * Helper functions for the purchase tracker application
 */

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatCurrency($amount) {
    return number_format($amount, 2, '.', ',');
}

function calculateProfit($salePrice, $purchasePrice, $shippingCost = 0) {
    return $salePrice - $purchasePrice - $shippingCost;
}

function calculateProfitMargin($salePrice, $profit) {
    if ($salePrice == 0) return 0;
    return ($profit / $salePrice) * 100;
}

function formatDate($dateString) {
    $date = new DateTime($dateString);
    return $date->format('Y-m-d');
}

function getAgeInDays($dateString) {
    $purchaseDate = new DateTime($dateString);
    $now = new DateTime();
    $interval = $purchaseDate->diff($now);
    return $interval->days;
}

function buildCsvRow($data, $headers) {
    $row = [];
    foreach ($headers as $header) {
        $row[] = isset($data[$header]) ? $data[$header] : '';
    }
    return $row;
}

function validateCsvData($row, $type = 'purchase') {
    $errors = [];
    
    if ($type === 'purchase') {
        $required = ['name', 'purchase_date', 'purchase_price'];
        foreach ($required as $field) {
            if (empty($row[$field])) {
                $errors[] = "Missing required field: $field";
            }
        }
        
        if (!empty($row['purchase_price']) && !is_numeric($row['purchase_price'])) {
            $errors[] = "purchase_price must be numeric";
        }
        
        if (!empty($row['purchase_date'])) {
            $date = DateTime::createFromFormat('Y-m-d', $row['purchase_date']);
            if (!$date || $date->format('Y-m-d') !== $row['purchase_date']) {
                $errors[] = "purchase_date must be in YYYY-MM-DD format";
            }
        }
    } elseif ($type === 'sale') {
        $required = ['item_id', 'sale_date', 'sale_price'];
        foreach ($required as $field) {
            if (empty($row[$field])) {
                $errors[] = "Missing required field: $field";
            }
        }
        
        if (!empty($row['sale_price']) && !is_numeric($row['sale_price'])) {
            $errors[] = "sale_price must be numeric";
        }
        
        if (!empty($row['sale_date'])) {
            $date = DateTime::createFromFormat('Y-m-d', $row['sale_date']);
            if (!$date || $date->format('Y-m-d') !== $row['sale_date']) {
                $errors[] = "sale_date must be in YYYY-MM-DD format";
            }
        }
    }
    
    return $errors;
}