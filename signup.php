<?php
require_once __DIR__ . '/../utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(["message" => "Method not allowed"], 405);
}

$input = getJsonInput();
$fullName = isset($input['fullName']) ? trim($input['fullName']) : '';
$email = isset($input['email']) ? trim($input['email']) : '';
$username = isset($input['username']) ? trim($input['username']) : '';
$password = isset($input['password']) ? $input['password'] : '';
$confirmPassword = isset($input['confirmPassword']) ? $input['confirmPassword'] : '';

if (empty($fullName) || empty($email) || empty($username) || empty($password)) {
    jsonResponse(["message" => "All fields are required"], 400);
}

if ($password !== $confirmPassword) {
    jsonResponse(["message" => "Passwords do not match"], 400);
}

// Validate email domain (hardcoded for now as per env example)
$allowedDomains = ['growixglobal.com'];
$domain = substr(strrchr($email, "@"), 1);
if (!in_array(strtolower($domain), $allowedDomains)) {
    jsonResponse(["message" => "Email domain not allowed. Allowed domains: " . implode(", ", $allowedDomains)], 400);
}

// Check existing
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    jsonResponse(["message" => "Email already in use"], 409);
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute([$username]);
if ($stmt->fetch()) {
    jsonResponse(["message" => "Username already in use"], 409);
}

// Create User
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, username, password_hash) VALUES (?, ?, ?, ?)");
    $stmt->execute([$fullName, $email, $username, $passwordHash]);
    $userId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO permissions (user_id, role, can_edit_profile, can_view_attendance, status) VALUES (?, 'user', 1, 1, 'active')");
    $stmt->execute([$userId]);

    logActivity($userId, 'signup', 'User created account');
    
    $pdo->commit();

    jsonResponse(["message" => "Account created, please log in"], 201);
} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(["message" => "Server error during signup: " . $e->getMessage()], 500);
}
?>
