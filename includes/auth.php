<?php
/**
 * Authentication & RBAC Helper Functions
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Update user last activity timestamp if logged in
if (isset($_SESSION['user_id'])) {
    try {
        $db = getDB();
        $stmtAct = $db->prepare("UPDATE users SET last_activity = :act WHERE id = :id");
        $stmtAct->execute(['act' => date('Y-m-d H:i:s'), 'id' => intval($_SESSION['user_id'])]);
    } catch (Exception $e) {}
}

function getLoggedInUser(): ?array {
    if (isset($_SESSION['user_id'])) {
        return $_SESSION['user'] ?? null;
    }
    return null;
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header("Location: " . APP_URL . "/index.php?error=" . urlencode("Please log in to access this page."));
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    $user = getLoggedInUser();
    $roleKey = $user['role_key'] ?? '';
    if (!in_array($roleKey, ['super_admin', 'admin'])) {
        header("Location: " . APP_URL . "/employee/dashboard.php?error=" . urlencode("Access denied. Admin permissions required."));
        exit;
    }
}

function hasPermission(string $permission): bool {
    $user = getLoggedInUser();
    if (!$user) return false;

    // Super Admin has all permissions
    if (($user['role_key'] ?? '') === 'super_admin') {
        return true;
    }

    $permissions = $user['permissions'] ?? [];
    if (is_string($permissions)) {
        $permissions = json_decode($permissions, true) ?: [];
    }

    return in_array('*', $permissions) || in_array($permission, $permissions);
}

function canAccessFile(array $file): bool {
    $user = getLoggedInUser();
    if (!$user) return false;

    // Super Admin & Admin can access all files
    $roleKey = $user['role_key'] ?? '';
    if (in_array($roleKey, ['super_admin', 'admin', 'manager'])) {
        return true;
    }

    // Front Desk / Receptionist can view files they created or are assigned to
    if (($file['created_by'] ?? 0) == $user['id']) {
        return true;
    }

    // Assigned Employee can view file
    return ($file['current_assigned_user'] ?? 0) == $user['id'];
}

function loginUser(string $email, string $password): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.*, u.permissions as user_permissions, r.role_name, r.role_key, r.permissions as role_permissions 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.email = :email AND u.status = 'active' 
        LIMIT 1
    ");
    $stmt->execute(['email' => trim($email)]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Parse permissions array: user override first, fallback to role default
        $userPerms = json_decode($user['user_permissions'] ?? '[]', true) ?: [];
        if (empty($userPerms)) {
            $userPerms = json_decode($user['role_permissions'] ?? '[]', true) ?: [];
        }
        $user['permissions'] = $userPerms;
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user'] = $user;

        logActivity($user['id'], 'LOGIN', 'User logged in successfully');
        return ['success' => true, 'user' => $user];
    }

    return ['success' => false, 'message' => 'Invalid email or password.'];
}

function logoutUser(): void {
    if (isset($_SESSION['user_id'])) {
        logActivity($_SESSION['user_id'], 'LOGOUT', 'User logged out');
    }
    unset($_SESSION['user_id']);
    unset($_SESSION['user']);
    session_destroy();
}
