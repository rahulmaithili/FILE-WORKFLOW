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
$input = json_decode(file_get_contents('php://input'), true);

$fileId = intval($input['file_id'] ?? 0);
$targetStageId = intval($input['target_stage_id'] ?? 0);

if ($fileId <= 0 || $targetStageId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Fetch file record
$stmtFile = $db->prepare("SELECT * FROM files WHERE id = :id LIMIT 1");
$stmtFile->execute(['id' => $fileId]);
$file = $stmtFile->fetch();

if (!$file) {
    echo json_encode(['success' => false, 'message' => 'File not found']);
    exit;
}

// Check permission
if (!canAccessFile($file)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Update file current stage
$stmtUpdate = $db->prepare("UPDATE files SET current_stage_id = :stage, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
$stmtUpdate->execute(['stage' => $targetStageId, 'id' => $fileId]);

// Record history audit log
$stmtHist = $db->prepare("
    INSERT INTO file_history (file_id, from_user, to_user, stage_id, action_type, remarks) 
    VALUES (:fid, :uid, :uid, :stage, 'kanban_moved', 'Moved stage via Kanban drag and drop.')
");
$stmtHist->execute(['fid' => $fileId, 'uid' => $user['id'], 'stage' => $targetStageId]);

echo json_encode(['success' => true, 'message' => 'Stage updated successfully via Kanban']);
