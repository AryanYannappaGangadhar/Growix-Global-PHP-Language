<?php
require_once __DIR__ . '/../utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(["message" => "Method not allowed"], 405);
}

$auth = requireAuth();
$user = $auth['user'];

$input = getJsonInput();

if (!isset($input['photos']) || !is_array($input['photos'])) {
    jsonResponse(["message" => "No photos provided"], 400);
}

$photos = $input['photos'];
$count = 0;

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO user_photos (user_id, photo_base64) VALUES (?, ?)");

    foreach ($photos as $base64) {
        if (!empty($base64)) {
            $stmt->execute([$user['id'], $base64]);
            $count++;
        }
    }
    
    $pdo->commit();
    logActivity($user['id'], 'photos_upload', "User uploaded $count photos");
    jsonResponse(["message" => "$count photos uploaded successfully"]);

} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(["message" => "Upload failed: " . $e->getMessage()], 500);
}
?>
