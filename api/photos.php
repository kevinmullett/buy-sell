<?php
/**
 * Photos API — multi-photo upload, receipt support
 */
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$method  = $_SERVER['REQUEST_METHOD'];
$item_id = intval($_GET['item_id'] ?? $_POST['item_id'] ?? 0);
$photoId = intval($_GET['id'] ?? 0);

switch ($method) {
    case 'GET':    getPhotos($item_id); break;
    case 'POST':   uploadPhotos($item_id); break;
    case 'DELETE': deletePhoto($photoId); break;
    default: http_response_code(405); echo json_encode(['error'=>'Method not allowed']);
}

function getPhotos($item_id) {
    global $pdo;
    if (!$item_id) { http_response_code(400); echo json_encode(['error'=>'item_id required']); return; }
    $stmt = $pdo->prepare("SELECT * FROM item_photos WHERE item_id=? ORDER BY photo_type, created_at");
    $stmt->execute([$item_id]);
    echo json_encode(['success'=>true,'data'=>$stmt->fetchAll()]);
}

function uploadPhotos($item_id) {
    global $pdo;
    if (!$item_id) { http_response_code(400); echo json_encode(['error'=>'item_id required']); return; }

    $photoType = $_POST['photo_type'] ?? 'item'; // 'item' or 'receipt'
    $allowed   = ['image/jpeg','image/png','image/gif','image/webp','image/heic'];
    $maxSize   = 10 * 1024 * 1024; // 10MB
    $uploadDir = __DIR__ . '/../photos/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $files   = $_FILES['photos']   ?? $_FILES['file']   ?? null;
    $saved   = [];
    $errors  = [];

    if (!$files) { http_response_code(400); echo json_encode(['error'=>'No files uploaded']); return; }

    // Normalize single vs multiple file upload
    if (!is_array($files['name'])) {
        foreach ($files as $k => $v) { $files[$k] = [$v]; }
    }

    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) { $errors[] = "File $i upload error"; continue; }
        if ($files['size'][$i] > $maxSize) { $errors[] = "{$files['name'][$i]}: too large (max 10MB)"; continue; }

        $mime = mime_content_type($files['tmp_name'][$i]);
        if (!in_array($mime, $allowed)) { $errors[] = "{$files['name'][$i]}: invalid type"; continue; }

        $ext      = pathinfo($files['name'][$i], PATHINFO_EXTENSION) ?: 'jpg';
        $filename = $photoType . '_' . $item_id . '_' . uniqid() . '.' . strtolower($ext);
        $dest     = $uploadDir . $filename;

        if (!move_uploaded_file($files['tmp_name'][$i], $dest)) {
            $errors[] = "{$files['name'][$i]}: failed to save"; continue;
        }

        $stmt = $pdo->prepare("INSERT INTO item_photos (item_id,file_path,original_name,file_type,file_size,photo_type) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$item_id, 'photos/'.$filename, $files['name'][$i], $mime, $files['size'][$i], $photoType]);
        $saved[] = ['id'=>$pdo->lastInsertId(),'file_path'=>'photos/'.$filename,'photo_type'=>$photoType];
    }

    echo json_encode(['success'=>true,'saved'=>$saved,'errors'=>$errors,'count'=>count($saved)]);
}

function deletePhoto($id) {
    global $pdo;
    if (!$id) { http_response_code(400); echo json_encode(['error'=>'id required']); return; }
    $stmt = $pdo->prepare("SELECT file_path FROM item_photos WHERE id=?");
    $stmt->execute([$id]);
    $photo = $stmt->fetch();
    if ($photo) {
        $path = __DIR__ . '/../' . $photo['file_path'];
        if (file_exists($path)) unlink($path);
        $pdo->prepare("DELETE FROM item_photos WHERE id=?")->execute([$id]);
    }
    echo json_encode(['success'=>true]);
}