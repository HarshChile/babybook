<?php
/**
 * Application Configuration & Helper Utilities
 * BabyBook / TinyTales
 */

// Start session if not active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Basic error reporting
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Load .env file (pure PHP, zero dependencies)
function loadEnv($envPath = __DIR__ . '/.env') {
    if (!file_exists($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1]);
            // Remove enclosing quotes if any
            $val = trim($val, "\"'");
            $_ENV[$key] = $val;
            putenv("$key=$val");
        }
    }
}

loadEnv();

// Helper to get environment variable with fallback
function env($key, $default = null) {
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    $val = getenv($key);
    return $val !== false ? $val : $default;
}

// Base configuration constants
define('MONGODB_URI', env('MONGODB_URI', 'mongodb://localhost:27017'));
define('MONGODB_DATABASE', env('MONGODB_DATABASE', 'tinytales'));
define('UPLOAD_DIR', rtrim(env('UPLOAD_DIR', 'uploads'), '/\\'));

// Ensure upload subdirectories exist on disk
$baseUploadDir = __DIR__ . '/' . UPLOAD_DIR;
$subDirs = ['photos', 'capsules', 'scrapbook', 'avatars'];
if (!is_dir($baseUploadDir)) {
    @mkdir($baseUploadDir, 0777, true);
}
foreach ($subDirs as $sub) {
    $target = $baseUploadDir . '/' . $sub;
    if (!is_dir($target)) {
        @mkdir($target, 0777, true);
    }
}

/**
 * Send JSON HTTP Response
 */
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send JSON Error Response
 */
function sendError($message, $status = 400) {
    jsonResponse(['success' => false, 'error' => $message], $status);
}

/**
 * Handle File Upload to Disk
 * Returns the relative file path for storing in MongoDB (e.g. "uploads/photos/1760359200_pic.jpg")
 */
function handleFileUpload($field = 'image', $subfolder = 'photos') {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $file = $_FILES[$field];
    $filename = basename($file['name']);
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    // Basic allowed extensions
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'mp3', 'wav'];
    if (!in_array($extension, $allowedExts)) {
        return null;
    }

    $safeName = time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetDir = __DIR__ . '/' . UPLOAD_DIR . '/' . $subfolder;
    $targetPath = $targetDir . '/' . $safeName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return UPLOAD_DIR . '/' . $subfolder . '/' . $safeName;
    }

    return null;
}

/**
 * Delete a file stored on disk
 */
function deleteFileFromDisk($relativePath) {
    if (!$relativePath) return;
    $fullPath = __DIR__ . '/' . ltrim($relativePath, '/\\');
    if (file_exists($fullPath) && is_file($fullPath)) {
        @unlink($fullPath);
    }
}
