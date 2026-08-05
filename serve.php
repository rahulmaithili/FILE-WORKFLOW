<?php
/**
 * Secure File Serving Proxy for CRM Uploads
 */

require_once __DIR__ . '/includes/auth.php'; // Ensure user is logged in
require_once __DIR__ . '/config.php';

$file = $_GET['file'] ?? '';

// Basic security checks to prevent directory traversal
$file = str_replace(['../', '..\\'], '', $file);
$file = ltrim($file, '/\\');

// Strip "uploads/" prefix if present (since UPLOAD_DIR already includes/points to the uploads folder)
if (stripos($file, 'uploads/') === 0) {
    $file = substr($file, 8);
}

$fullPath = UPLOAD_DIR . $file;

if (!empty($file) && file_exists($fullPath) && is_file($fullPath)) {
    // Detect Content-Type
    $mime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $fullPath);
        finfo_close($finfo);
    }
    
    if (!$mime && function_exists('mime_content_type')) {
        $mime = mime_content_type($fullPath);
    }

    if (!$mime) {
        $mime = 'application/octet-stream';
    }

    header("Content-Type: " . $mime);
    header("Content-Length: " . filesize($fullPath));
    header("Cache-Control: private, max-age=86400");
    readfile($fullPath);
    exit;
} else {
    http_response_code(404);
    echo "File not found.";
    exit;
}
