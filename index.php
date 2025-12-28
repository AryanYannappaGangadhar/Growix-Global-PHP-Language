<?php
// Main Gateway Script

// Serve static files if requested directly
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// 1. Calculate path relative to the script's directory
// This ensures it works even if installed in a subdirectory (e.g. domain.com/app/)
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$scriptDir = str_replace('\\', '/', $scriptDir); // Normalize Windows paths

// Special handling for root directory to avoid stripping the leading slash
if ($scriptDir === '/' || $scriptDir === '.') {
    $scriptDir = '';
} else {
    $scriptDir = rtrim($scriptDir, '/');
}

// Remove the script directory from the request path to get the relative path
// e.g. Request: /app/api/auth/login -> Relative: /api/auth/login
if ($scriptDir && strpos($path, $scriptDir) === 0) {
    $relativePath = substr($path, strlen($scriptDir));
} else {
    $relativePath = $path;
}

// 2. Check if the relative path starts with /api/
if (strpos($relativePath, '/api/') === 0) {
    // Determine the actual file path in the secure app folder
    // Remove /api prefix
    $apiPath = substr($relativePath, 4); // "/api" is 4 chars
    
    // Potential Base Paths for growix-app (Priority: Sibling > Child)
    $baseCandidates = [
        __DIR__ . '/../growix-app/api',  // Recommended: Sibling of public_html
        __DIR__ . '/growix-app/api',     // Fallback: Inside public_html
    ];

    $found = false;

    foreach ($baseCandidates as $baseDir) {
        // Construct potential file path
        $securePath = $baseDir . $apiPath . '.php';

        // Security check: prevent directory traversal
        $realSecurePath = realpath($securePath);
        $realRoot = realpath($baseDir);

        // Verify file exists and is inside the api directory
        if ($realSecurePath && $realRoot && strpos($realSecurePath, $realRoot) === 0 && file_exists($realSecurePath)) {
            require $realSecurePath;
            $found = true;
            break;
        }
    }

    if ($found) {
        exit();
    } else {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'message' => 'API endpoint not found', 
            'debug_path' => $apiPath,
            'note' => 'Ensure growix-app folder is uploaded to the correct location.'
        ]);
        exit();
    }
}

// Default Redirect to Login
if ($relativePath === '/' || $relativePath === '/index.php' || $path === '/') {
    header("Location: login.php");
    exit();
}

// For other files (HTML), let the web server handle them or 404
http_response_code(404);
echo "Not Found";
?>
