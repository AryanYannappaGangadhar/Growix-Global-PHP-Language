<?php
require_once __DIR__ . '/../utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(["message" => "Method not allowed"], 405);
}

$auth = requireAuth();
$user = $auth['user'];

$stmt = $pdo->prepare("SELECT id, photo_base64, created_at FROM user_photos WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$photos = $stmt->fetchAll();

jsonResponse($photos);
?>
