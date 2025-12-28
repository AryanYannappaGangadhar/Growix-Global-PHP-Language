<?php
require_once __DIR__ . '/../utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(["message" => "Method not allowed"], 405);
}

setcookie(JWT_COOKIE_NAME, '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

jsonResponse(["message" => "Logged out"]);
?>
