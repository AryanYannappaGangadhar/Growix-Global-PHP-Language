<?php
require_once __DIR__ . '/../utils.php';

// WARNING: This implementation allows password reset via Username only.
// This is insecure for production as it allows anyone to reset anyone's password.
// Implemented as per specific user request.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(["message" => "Method not allowed"], 405);
}

$input = getJsonInput();
$username = isset($input['username']) ? trim($input['username']) : '';
$password = isset($input['password']) ? $input['password'] : '';
$confirmPassword = isset($input['confirmPassword']) ? $input['confirmPassword'] : '';

if (empty($username) || empty($password)) {
    jsonResponse(["message" => "Username and Password are required"], 400);
}

if ($password !== $confirmPassword) {
    jsonResponse(["message" => "Passwords do not match"], 400);
}

// Check if user exists
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user) {
    // Return generic message or specific error depending on preference.
    // For "backdoor" style requested, we might want to be explicit.
    jsonResponse(["message" => "User not found"], 404);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// Update password directly
$stmt = $pdo->prepare("UPDATE users SET password_hash = ?, reset_token_hash = NULL, reset_expires_at = NULL WHERE id = ?");
$stmt->execute([$passwordHash, $user['id']]);

logActivity($user['id'], 'password_reset_direct', 'User reset password via username');

jsonResponse(["message" => "Password updated successfully"]);
?>
