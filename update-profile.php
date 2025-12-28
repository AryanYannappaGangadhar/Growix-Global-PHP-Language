<?php
require_once __DIR__ . '/../utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(["message" => "Method not allowed"], 405);
}

$auth = requireAuth();
$user = $auth['user'];

if (!$auth['permission']['can_edit_profile']) {
    jsonResponse(["message" => "Permission denied"], 403);
}

$input = getJsonInput();
$updates = [];
$params = [];

if (isset($input['fullName']) && is_string($input['fullName']) && strlen($input['fullName']) >= 2) {
    $updates[] = "full_name = ?";
    $params[] = $input['fullName'];
}

if (isset($input['photoBase64']) && is_string($input['photoBase64'])) {
    $updates[] = "photo_base64 = ?";
    $params[] = $input['photoBase64'];
}

if (!empty($updates)) {
    $params[] = $user['id'];
    $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    logActivity($user['id'], 'profile_update', 'User updated profile');
}

jsonResponse(["message" => "Profile updated successfully"]);
?>
