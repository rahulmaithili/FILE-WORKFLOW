<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$db = getDB();
$currentUser = getLoggedInUser();

$action = $_GET['action'] ?? '';

// 1. Stop Impersonation & Return to Admin Panel
if ($action === 'stop') {
    if (isset($_SESSION['original_admin_id'])) {
        // Restore original admin session
        $_SESSION['user_id'] = $_SESSION['original_admin_id'];
        $_SESSION['user'] = $_SESSION['original_admin_user'];

        // Cleanup impersonation session variables
        unset($_SESSION['original_admin_id']);
        unset($_SESSION['original_admin_user']);

        setFlashMessage('success', 'Returned back to Admin session.');
        header("Location: " . APP_URL . "/admin/users.php");
        exit;
    } else {
        header("Location: " . APP_URL . "/index.php");
        exit;
    }
}

// 2. Start Impersonation (Admin logins as selected user)
$targetUserId = intval($_GET['id'] ?? 0);

if ($targetUserId <= 0) {
    setFlashMessage('danger', 'Invalid target user ID.');
    header("Location: " . APP_URL . "/admin/users.php");
    exit;
}

// Enforce that only Admin/Super Admin can launch impersonation
$roleKey = $currentUser['role_key'] ?? '';
if (!in_array($roleKey, ['super_admin', 'admin'])) {
    setFlashMessage('danger', 'Unauthorized action.');
    header("Location: " . APP_URL . "/employee/dashboard.php");
    exit;
}

// Cannot impersonate yourself
if ($targetUserId === $currentUser['id']) {
    setFlashMessage('warning', 'You cannot impersonate your own session.');
    header("Location: " . APP_URL . "/admin/users.php");
    exit;
}

// Fetch target employee user record
$stmt = $db->prepare("
    SELECT u.*, r.role_name, r.role_key, r.permissions 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE u.id = :id AND u.status = 'active' 
    LIMIT 1
");
$stmt->execute(['id' => $targetUserId]);
$targetUser = $stmt->fetch();

if ($targetUser) {
    // Keep track of the real admin who launched impersonation
    if (!isset($_SESSION['original_admin_id'])) {
        $_SESSION['original_admin_id'] = $currentUser['id'];
        $_SESSION['original_admin_user'] = $currentUser;
    }

    // Set target employee as logged-in session
    $targetUser['permissions'] = json_decode($targetUser['permissions'] ?? '[]', true) ?: [];
    $_SESSION['user_id'] = $targetUser['id'];
    $_SESSION['user'] = $targetUser;

    logActivity($_SESSION['original_admin_id'], 'IMPERSONATE_START', "Started impersonation session for user ID: " . $targetUser['id']);

    setFlashMessage('success', "Logged in as " . $targetUser['name'] . " successfully.");
    header("Location: " . APP_URL . "/employee/dashboard.php");
    exit;
} else {
    setFlashMessage('danger', 'Target employee user not found or is inactive.');
    header("Location: " . APP_URL . "/admin/users.php");
    exit;
}
