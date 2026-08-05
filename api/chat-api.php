<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = getDB();
$user = getLoggedInUser();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'send':
        $message = sanitize($_POST['message'] ?? '');
        $filePath = null;
        $fileName = null;

        // Handle attachment file upload if present
        if (isset($_FILES['chat_file']) && $_FILES['chat_file']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['chat_file']['tmp_name'];
            $fileName = basename($_FILES['chat_file']['name']);
            $ext = pathinfo($fileName, PATHINFO_EXTENSION);
            $newFileName = "CHAT_" . $user['id'] . "_" . time() . "." . $ext;
            $destination = DOC_UPLOAD_DIR . $newFileName;

            if (move_uploaded_file($fileTmp, $destination)) {
                $filePath = 'uploads/documents/' . $newFileName;
            }
        }

        if (empty($message) && empty($filePath)) {
            echo json_encode(['success' => false, 'message' => 'Message or attachment is required']);
            exit;
        }

        $stmt = $db->prepare("INSERT INTO chat_messages (user_id, message, file_path, file_name) VALUES (:uid, :msg, :path, :name)");
        $stmt->execute([
            'uid' => $user['id'],
            'msg' => $message,
            'path' => $filePath,
            'name' => $fileName
        ]);

        echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
        break;

    case 'fetch':
        $lastId = intval($_GET['last_id'] ?? 0);
        
        $stmt = $db->prepare("
            SELECT c.*, u.name as sender_name, u.profile_photo, r.role_name 
            FROM chat_messages c 
            JOIN users u ON c.user_id = u.id 
            JOIN roles r ON u.role_id = r.id 
            WHERE c.id > :last_id 
            ORDER BY c.id ASC
        ");
        $stmt->execute(['last_id' => $lastId]);
        $messages = $stmt->fetchAll();

        // Also fetch active online users list (last active within 5 minutes)
        // For SQLite and MySQL cross-compatibility, we compute the timestamp limit in PHP
        $timeLimit = date('Y-m-d H:i:s', strtotime('-5 minutes'));
        
        $stmtOnline = $db->prepare("
            SELECT u.id, u.name, u.profile_photo, r.role_name 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE u.last_activity >= :limit AND u.status = 'active'
            ORDER BY u.name ASC
        ");
        $stmtOnline->execute(['limit' => $timeLimit]);
        $onlineUsers = $stmtOnline->fetchAll();

        echo json_encode([
            'success' => true,
            'messages' => $messages,
            'online_users' => $onlineUsers
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
