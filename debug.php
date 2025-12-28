<?php
// Deployment Debug Script
// Upload this to public_html/debug.php and visit domain.com/debug.php

header('Content-Type: text/plain');

echo "=== Growix Global Deployment Debugger ===\n\n";

// 1. Check PHP Version
echo "PHP Version: " . phpversion() . "\n";

// 2. Check Directory Structure
echo "\nCurrent Directory: " . __DIR__ . "\n";
$growixAppPath = __DIR__ . '/../growix-app';
$growixAppPathFallback = __DIR__ . '/growix-app';

echo "Checking for backend...\n";

if (is_dir($growixAppPath)) {
    echo "[OK] growix-app directory found at sibling level (../growix-app).\n";
} elseif (is_dir($growixAppPathFallback)) {
    echo "[OK] growix-app directory found inside public_html (./growix-app).\n";
    $growixAppPath = $growixAppPathFallback; // Update path for DB check
} else {
    echo "[ERROR] growix-app directory NOT found!\n";
    echo "Checked locations:\n";
    echo "1. " . $growixAppPath . "\n";
    echo "2. " . $growixAppPathFallback . "\n";
}

// 3. Check .env
echo "\nChecking for .env file...\n";
$possibleEnvPaths = [
    __DIR__ . '/../.env',
    __DIR__ . '/../../.env',
    dirname($growixAppPath) . '/.env',
    $_SERVER['DOCUMENT_ROOT'] . '/.env'
];

$envPath = null;
foreach ($possibleEnvPaths as $path) {
    echo "Checking: $path ... ";
    if (file_exists($path)) {
        echo "[FOUND]\n";
        $envPath = $path;
        break;
    } else {
        echo "[NOT FOUND]\n";
    }
}

if ($envPath) {
    echo "[OK] Using .env at: $envPath\n";
} else {
    echo "[ERROR] .env file NOT found! Please upload it.\n";
}

// 4. Test Database Connection
echo "\nTesting Database Connection...\n";

if (file_exists($growixAppPath . '/utils/env.php')) {
    require_once $growixAppPath . '/utils/env.php';
    if ($envPath) {
        loadEnv($envPath);
        
        // Try getting vars from multiple sources
        $host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? ($_SERVER['DB_HOST'] ?? ''));
        $name = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? ($_SERVER['DB_NAME'] ?? ''));
        $user = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? ($_SERVER['DB_USER'] ?? ''));
        $pass = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? ($_SERVER['DB_PASS'] ?? ''));
        
        echo "Configured Host: $host\n";
        echo "Configured DB: $name\n";
        echo "Configured User: $user\n";
        
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$name", $user, $pass);
            echo "[OK] Database connection successful!\n";
        } catch (PDOException $e) {
            echo "[ERROR] Database connection failed: " . $e->getMessage() . "\n";
        }
    }
} else {
    echo "[ERROR] Cannot load env.php utils.\n";
}

echo "\n=== End Debug Log ===\n";
?>