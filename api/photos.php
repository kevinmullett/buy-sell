<?php
/**
 * Photo Upload and Management API Endpoints
 * Handles item photo uploads and retrieval
 */

require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'POST':
        uploadPhoto($input);
        break;
    case 'GET':
        $itemId = $_GET['item_id'] ?? null;
        if ($itemId) {
            getPhotos($itemId);
        } else {
            getAllPhotos();
        }
        break;
    case 'DELETE':
        $input = json_decode(file_get_contents('php://input'), true);
        deletePhoto($input['photo_id'] ?? 0);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

function uploadPhoto($data) {
    global $pdo;
    
    if (empty($_FILES['photo']['tmp_name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No photo uploaded']);
        return;
    }
    
    $itemId = $data['item_id'] ?? 0;
    $file = $_FILES['photo'];
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $fileType = mime_content_type($file['tmp_name']);
    
    if (!in_array($fileType, $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP']);
        return;
    }
    
    // Create uploads directory if it doesn't exist
    $uploadDir = __DIR__ . '/../photos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('photo_') . '.' . $extension;
    $filePath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to move uploaded file']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO photos (item_id, file_path, original_name, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $itemId,
            $filePath,
            $file['name'],
            $fileType,
            $file['size']
        ]);
        
        $photoId = $pdo->lastInsertId();
        echo json_encode([
            'success' => true,
            'id' => $photoId,
            'file_path' => $filePath,
            'message' => 'Photo uploaded successfully'
        ]);
    } catch (PDOException $e) {
        // Delete uploaded file on database error
        @unlink($filePath);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save photo record: ' . $e->getMessage()]);
    }
}

function getPhotos($itemId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM photos WHERE item_id = ? ORDER BY created_at DESC");
        $stmt->execute([$itemId]);
        $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $photos]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch photos: ' . $e->getMessage()]);
    }
}

function getAllPhotos() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT p.*, i.name as item_name FROM photos p LEFT JOIN items i ON p.item_id = i.id ORDER BY p.created_at DESC");
        $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $photos]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch photos: ' . $e->getMessage()]);
    }
}

function deletePhoto($photoId) {
    global $pdo;
    
    try {
        // Get photo info before deleting
        $stmt = $pdo->prepare("SELECT file_path FROM photos WHERE id = ?");
        $stmt->execute([$photoId]);
        $photo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$photo) {
            http_response_code(404);
            echo json_encode(['error' => 'Photo not found']);
            return;
        }
        
        // Delete file from filesystem
        if (file_exists($photo['file_path'])) {
            unlink($photo['file_path']);
        }
        
        // Delete database record
        $stmt = $pdo->prepare("DELETE FROM photos WHERE id = ?");
        $stmt->execute([$photoId]);
        
        echo json_encode(['success' => true, 'message' => 'Photo deleted successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete photo: ' . $e->getMessage()]);
    }
}