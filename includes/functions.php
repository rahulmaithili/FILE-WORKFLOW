<?php
/**
 * Utility & System Helper Functions
 */

require_once __DIR__ . '/db.php';

function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}

function setFlashMessage(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

function displayFlashMessages(): string {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $swalType = match($flash['type']) {
            'error', 'danger' => 'error',
            'success' => 'success',
            'warning' => 'warning',
            default => 'info'
        };
        $title = match($swalType) {
            'success' => 'Success!',
            'error' => 'Error Alert',
            'warning' => 'Warning Alert',
            default => 'Notification'
        };
        $msg = addslashes($flash['message']);
        
        return "
        <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: '{$title}',
                    text: '{$msg}',
                    icon: '{$swalType}',
                    confirmButtonColor: '#3b82f6',
                    background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                    color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#f8fafc' : '#1e293b'
                });
            }
        });
        </script>
        ";
    }
    return '';
}

function logActivity(?int $userId, string $action, string $details = ''): void {
    try {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (:user_id, :action, :details, :ip)");
        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'details' => $details,
            'ip' => $ip
        ]);
    } catch (Exception $e) {
        error_log("Failed to insert activity log: " . $e->getMessage());
    }
}

function generateFileCode(string $prefix = 'FMS'): string {
    $db = getDB();
    $year = date('Y');
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM files WHERE file_code LIKE :prefix");
    $stmt->execute(['prefix' => "{$prefix}-{$year}-%"]);
    $row = $stmt->fetch();
    $nextNum = str_pad(($row['count'] ?? 0) + 1, 5, '0', STR_PAD_LEFT);
    return "{$prefix}-{$year}-{$nextNum}";
}

function getStatusBadgeHtml(string $status): string {
    return match($status) {
        'pending' => '<span class="badge bg-warning text-dark px-2 py-1 rounded-pill"><i class="fas fa-clock me-1"></i> Pending</span>',
        'in_progress' => '<span class="badge bg-info text-dark px-2 py-1 rounded-pill"><i class="fas fa-spinner fa-spin me-1"></i> In Progress</span>',
        'on_hold' => '<span class="badge bg-secondary text-white px-2 py-1 rounded-pill"><i class="fas fa-pause me-1"></i> On Hold</span>',
        'completed' => '<span class="badge bg-success text-white px-2 py-1 rounded-pill"><i class="fas fa-check-circle me-1"></i> Completed</span>',
        'rejected' => '<span class="badge bg-danger text-white px-2 py-1 rounded-pill"><i class="fas fa-times-circle me-1"></i> Rejected</span>',
        default => '<span class="badge bg-light text-dark px-2 py-1 rounded-pill">' . ucfirst($status) . '</span>'
    };
}

function getPriorityBadgeHtml(string $priority): string {
    return match($priority) {
        'low' => '<span class="badge bg-light text-secondary border me-1"><i class="fas fa-arrow-down text-muted me-1"></i> Low</span>',
        'medium' => '<span class="badge bg-primary-soft text-primary me-1"><i class="fas fa-minus text-primary me-1"></i> Medium</span>',
        'high' => '<span class="badge bg-warning-soft text-warning me-1"><i class="fas fa-arrow-up text-warning me-1"></i> High</span>',
        'urgent' => '<span class="badge bg-danger text-white me-1"><i class="fas fa-bolt text-warning me-1"></i> Urgent</span>',
        default => '<span class="badge bg-light text-dark me-1">' . ucfirst($priority) . '</span>'
    };
}

function timeAgo(string $datetime): string {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return "Just now";
    if ($diff < 3600) return floor($diff / 60) . " mins ago";
    if ($diff < 86400) return floor($diff / 3600) . " hrs ago";
    if ($diff < 604800) return floor($diff / 86400) . " days ago";
    return date('d M Y, h:i A', $time);
}

function isFeatureEnabled(string $featureKey): bool {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT status FROM system_features WHERE feature_key = :key LIMIT 1");
        $stmt->execute(['key' => $featureKey]);
        $status = $stmt->fetchColumn();
        return $status === 'enabled';
    } catch (Exception $e) {
        return false;
    }
}

function addNotification(int $userId, string $title, string $message, string $link = ''): bool {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, link, is_read) VALUES (:uid, :title, :msg, :link, 0)");
        return $stmt->execute([
            'uid' => $userId,
            'title' => $title,
            'msg' => $message,
            'link' => $link
        ]);
    } catch (Exception $e) {
        return false;
    }
}

