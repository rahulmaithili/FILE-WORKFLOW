<?php
ob_start();
/**
 * Configuration & System Global Environment
 */

// App Base Configuration
define('APP_NAME', 'Office File Management CRM');
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? '') == 443) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
define('APP_URL', $protocol . $domainName);
define('APP_VERSION', '1.0.0');

// System Timezone
date_default_timezone_set('Asia/Kolkata');

// Database Driver Configuration: 'mysql' or 'sqlite' (Auto-fallback to SQLite if MySQL fails)
define('DB_DRIVER', 'sqlite'); // sqlite for instant local running, change to mysql for production

// MySQL Settings
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'file_crm');
define('DB_USER', 'root');
define('DB_PASS', '');

// SQLite File Path (Used if DB_DRIVER is 'sqlite' or MySQL connection fails)
define('SQLITE_FILE', __DIR__ . '/database.sqlite');

// Session Settings
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// Upload Directories
function getCustomUploadPath() {
    $defaultPath = __DIR__ . '/uploads/';
    try {
        if (DB_DRIVER === 'mysql') {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } else {
            $dsn = "sqlite:" . SQLITE_FILE;
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        
        $stmt = $pdo->query("SELECT custom_upload_path FROM company_settings LIMIT 1");
        if ($stmt) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!empty($row['custom_upload_path'])) {
                $path = trim($row['custom_upload_path']);
                if (substr($path, -1) !== '/' && substr($path, -1) !== '\\') {
                    $path .= '/';
                }
                return $path;
            }
        }
    } catch (Exception $e) {
        // Fallback to default
    }
    return $defaultPath;
}

define('UPLOAD_DIR', getCustomUploadPath());
define('DOC_UPLOAD_DIR', UPLOAD_DIR . 'documents/');
define('PROFILE_UPLOAD_DIR', UPLOAD_DIR . 'profiles/');

// Create Upload directories if not exists
if (!file_exists(DOC_UPLOAD_DIR)) {
    @mkdir(DOC_UPLOAD_DIR, 0777, true);
}
if (!file_exists(PROFILE_UPLOAD_DIR)) {
    @mkdir(PROFILE_UPLOAD_DIR, 0777, true);
}
