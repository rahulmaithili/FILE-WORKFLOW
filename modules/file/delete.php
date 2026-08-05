<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireAdmin();

$db = getDB();
$fileId = intval($_GET['id'] ?? 0);

if ($fileId > 0) {
    try {
        // 1. Fetch file code to log it
        $stmtCode = $db->prepare("SELECT file_code FROM files WHERE id = :id");
        $stmtCode->execute(['id' => $fileId]);
        $fileCode = $stmtCode->fetchColumn();

        if ($fileCode) {
            // 2. Fetch and delete attachments from disk
            $stmtDocs = $db->prepare("SELECT file_path FROM file_documents WHERE file_id = :fid");
            $stmtDocs->execute(['fid' => $fileId]);
            $docs = $stmtDocs->fetchAll();
            
            foreach ($docs as $doc) {
                $path = __DIR__ . '/../../' . $doc['file_path'];
                if (file_exists($path) && is_file($path)) {
                    @unlink($path);
                }
            }

            // 3. Delete database records
            $db->prepare("DELETE FROM file_documents WHERE file_id = :fid")->execute(['fid' => $fileId]);
            $db->prepare("DELETE FROM file_history WHERE file_id = :fid")->execute(['fid' => $fileId]);
            $db->prepare("DELETE FROM files WHERE id = :id")->execute(['id' => $fileId]);

            // Add Audit Log
            logActivity($_SESSION['user_id'], 'FILE_DELETE_ADMIN', "Admin deleted case file code {$fileCode} (ID: {$fileId}) and all its attachments/history.");
            
            setFlashMessage('success', "File and all associated records deleted successfully!");
        } else {
            setFlashMessage('danger', "File not found or already deleted.");
        }
    } catch (Exception $e) {
        setFlashMessage('danger', "Failed to delete file: " . $e->getMessage());
    }
}

header("Location: " . APP_URL . "/employee/my-files.php");
exit;
