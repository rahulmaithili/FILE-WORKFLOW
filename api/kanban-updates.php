<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

$db = getDB();
try {
    $stmt = $db->query("SELECT COUNT(*), MAX(updated_at), MAX(id) FROM files");
    $data = $stmt->fetch();
    $hash = md5(implode('|', $data));
    echo json_encode(['success' => true, 'hash' => $hash]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'hash' => '', 'message' => $e->getMessage()]);
}
