<?php
/**
 * Development Server Router for TinyTales BabyBook
 * Automatically injects Cache-Control headers to prevent browser caching during development
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;

// Prevent browser caching on localhost
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Root redirect to home.html
if ($path === '/' || $path === '') {
    header('Location: /home.html');
    exit;
}

// Serve static assets with correct MIME types and anti-cache headers
if (file_exists($file) && !is_dir($file)) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mimes = [
        'html' => 'text/html; charset=UTF-8',
        'css'  => 'text/css; charset=UTF-8',
        'js'   => 'application/javascript; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon'
    ];

    if (isset($mimes[$ext])) {
        header("Content-Type: {$mimes[$ext]}");
        readfile($file);
        return true;
    }

    // Let PHP's built-in web server handle other static files or PHP scripts
    return false;
}

// Pass all other requests (such as /api/*.php) to PHP engine
return false;
