<?php
require_once __DIR__ . '/../utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(["message" => "Method not allowed"], 405);
}

$input = getJsonInput();
$username = isset($input['username']) ? trim($input['username']) : '';
$password = isset($input['password']) ? $input['password'] : '';

if (empty($username) || empty($password)) {
    jsonResponse(["message" => "Username and password required"], 400);
}

// Check by username or email
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
$stmt->execute([$username, $username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    jsonResponse(["message" => "Invalid credentials"], 401);
}

// Generate Token
$token = generateJWT(['sub' => $user['id'], 'username' => $user['username'], 'scope' => 'access']);

// Set Cookie
setcookie(JWT_COOKIE_NAME, $token, [
    'expires' => time() + JWT_EXPIRES_IN,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Lax'
]);

logActivity($user['id'], 'login', 'User logged in');

jsonResponse([
    "message" => "Login successful",
    "token" => $token, // Return token for frontend storage
    "user" => [
        "id" => $user['id'],
        "fullName" => $user['full_name'],
        "email" => $user['email'],
        "username" => $user['username']
    ]
]);
?>
