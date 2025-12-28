<?php
// Router for PHP Built-in Server
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $uri;

// If it's a file (like style.css, logo.jpg, api.php in assets), serve it
if (is_file($file)) {
    // PHP built-in server doesn't handle mime types for .php files automatically if we return false?
    // Actually if we return false, it handles it standardly. 
    // If it is a .php file, it executes it. If static, it serves it.
    return false; 
}

// Otherwise route to index.php (which handles API routing)
require 'index.php';
