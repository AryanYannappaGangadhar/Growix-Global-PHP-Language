<?php
require_once __DIR__ . '/utils/env.php';

// Load .env from multiple possible locations
$possiblePaths = [
    __DIR__ . '/../.env',           // Standard structure (sibling to public_html)
    __DIR__ . '/../../.env',        // If nested
    dirname(__DIR__) . '/.env',     // Inside growix-app root (fallback)
];

foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        loadEnv($path);
        break;
    }
}

// Helper to get env variable from multiple sources
function getEnvVar($key, $default = null) {
    $val = getenv($key);
    if ($val !== false) return $val;
    if (isset($_ENV[$key])) return $_ENV[$key];
    if (isset($_SERVER[$key])) return $_SERVER[$key];
    return $default;
}

// Set Timezone to India
date_default_timezone_set('Asia/Kolkata');

// Database configuration
$dbHost = getEnvVar('DB_HOST');
$dbName = getEnvVar('DB_NAME');
$dbUser = getEnvVar('DB_USER');

// On production, these MUST be set. Fallback only for local dev if desired.
if (!$dbHost || !$dbName || !$dbUser) {
    // If not set, check if we are on localhost to allow fallback
    $serverName = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
    
    // Check if we are on localhost/dev
    if ($serverName === 'localhost' || $serverName === '127.0.0.1' || $serverName === '::1') {
        $dbHost = 'localhost';
        $dbName = 'company_attendance';
        $dbUser = 'root';
        $dbPass = '';
    } else {
        // EMERGENCY FALLBACK for Hostinger Deployment (If .env fails)
        // Try to use the provided credentials if environment variables are missing
        $dbHost = 'localhost';
        $dbName = 'u786203048_DataBase'; // CORRECTED DATABASE NAME FROM SCREENSHOT
        $dbUser = 'u786203048_GrowixGlobal';
        $dbPass = 'Itachi@1990'; 
        
        // If we successfully set them here, we don't exit. 
        // We log a warning to PHP error log instead of breaking the app.
        error_log("WARNING: Using hardcoded fallback credentials in config.php. Please configure .env properly.");
    }
} else {
    // Credentials were found in environment, just set the password variable if not already set
    if (!isset($dbPass)) {
         $dbPass = getEnvVar('DB_PASS', '');
    }
}

define('DB_HOST', $dbHost);
define('DB_NAME', $dbName);
define('DB_USER', $dbUser);
define('DB_PASS', isset($dbPass) ? $dbPass : getEnvVar('DB_PASS', ''));

// JWT configuration
define('JWT_SECRET', getEnvVar('JWT_SECRET', 'super-secret-jwt-key'));

$jwtExpires = getEnvVar('JWT_EXPIRES_IN', '604800');
if (!is_numeric($jwtExpires)) {
    $suffix = strtolower(substr($jwtExpires, -1));
    $val = (int)substr($jwtExpires, 0, -1);
    switch ($suffix) {
        case 'd': $jwtExpires = $val * 86400; break;
        case 'h': $jwtExpires = $val * 3600; break;
        case 'm': $jwtExpires = $val * 60; break;
        default: $jwtExpires = (int)$jwtExpires;
    }
} else {
    $jwtExpires = (int)$jwtExpires;
}
define('JWT_EXPIRES_IN', $jwtExpires);
define('JWT_COOKIE_NAME', getEnvVar('JWT_COOKIE_NAME', 'auth_token'));

// Email configuration
define('SMTP_FROM', getEnvVar('SMTP_FROM', 'no-reply@growixglobal.com'));
define('FRONTEND_URL', getEnvVar('FRONTEND_URL', 'http://localhost/Growix Global/public_html'));

// SMTP Config
define('SMTP_HOST', getEnvVar('SMTP_HOST', 'localhost'));
define('SMTP_PORT', getEnvVar('SMTP_PORT', 25));
define('SMTP_USER', getEnvVar('SMTP_USER', ''));
define('SMTP_PASS', getEnvVar('SMTP_PASS', ''));
define('SMTP_SECURE', getEnvVar('SMTP_SECURE', ''));

// CORS configuration (if needed for cross-domain)
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
} else {
    header("Access-Control-Allow-Origin: *");
}
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection failed: " . $e->getMessage()]);
    exit();
}
?>
