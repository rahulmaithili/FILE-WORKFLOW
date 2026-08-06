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
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action !== 'get_required_docs' && !hasPermission('manage_users')) {
    echo json_encode(['success' => false, 'message' => 'Access Denied']);
    exit;
}

switch ($action) {
    case 'add_work_type':
        $name = sanitize($_POST['name'] ?? '');
        $desc = sanitize($_POST['description'] ?? '');
        $prefix = strtoupper(sanitize($_POST['code_prefix'] ?? 'FMS'));
        $icon = sanitize($_POST['icon'] ?? 'fa-sitemap');
        
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Work type name is required']);
            exit;
        }

        $stmt = $db->prepare("INSERT INTO work_types (name, description, code_prefix, icon, created_by) VALUES (:name, :desc, :prefix, :icon, :user)");
        $stmt->execute(['name' => $name, 'desc' => $desc, 'prefix' => $prefix, 'icon' => $icon, 'user' => $_SESSION['user_id']]);
        
        echo json_encode(['success' => true, 'id' => $db->lastInsertId(), 'message' => 'Work type created successfully']);
        break;

    case 'add_stage':
        $workTypeId = intval($_POST['work_type_id'] ?? 0);
        $stageName = sanitize($_POST['stage_name'] ?? '');
        $roleId = intval($_POST['assigned_role_id'] ?? 0);
        $slaHours = intval($_POST['sla_hours'] ?? 24);
        $reqDocs = sanitize($_POST['required_documents'] ?? '');

        if (!$workTypeId || empty($stageName) || !$roleId) {
            echo json_encode(['success' => false, 'message' => 'Stage name and role selection are required']);
            exit;
        }

        // Get max stage_order
        $stmt = $db->prepare("SELECT MAX(stage_order) FROM workflow_stages WHERE work_type_id = :wt");
        $stmt->execute(['wt' => $workTypeId]);
        $maxOrder = intval($stmt->fetchColumn() ?: 0);

        $stmt = $db->prepare("INSERT INTO workflow_stages (work_type_id, stage_order, stage_name, assigned_role_id, sla_hours, required_documents) VALUES (:wt, :order, :name, :role, :sla, :docs)");
        $stmt->execute([
            'wt' => $workTypeId,
            'order' => $maxOrder + 1,
            'name' => $stageName,
            'role' => $roleId,
            'sla' => $slaHours,
            'docs' => $reqDocs
        ]);

        echo json_encode(['success' => true, 'message' => 'Stage added successfully']);
        break;

    case 'reorder_stages':
        $input = json_decode(file_get_contents('php://input'), true);
        $stageIds = $input['stage_ids'] ?? [];

        if (is_array($stageIds)) {
            $stmt = $db->prepare("UPDATE workflow_stages SET stage_order = :order WHERE id = :id");
            foreach ($stageIds as $index => $id) {
                $stmt->execute(['order' => $index + 1, 'id' => intval($id)]);
            }
        }
        echo json_encode(['success' => true, 'message' => 'Workflow stage order saved']);
        break;

    case 'delete_stage':
        $stageId = intval($_POST['stage_id'] ?? 0);
        if ($stageId > 0) {
            $stmt = $db->prepare("DELETE FROM workflow_stages WHERE id = :id");
            $stmt->execute(['id' => $stageId]);
            echo json_encode(['success' => true, 'message' => 'Stage deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid stage ID']);
        }
        break;

    case 'delete_work_type':
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            // Delete stages first
            $stmt = $db->prepare("DELETE FROM workflow_stages WHERE work_type_id = :id");
            $stmt->execute(['id' => $id]);
            
            // Delete work type itself
            $stmt2 = $db->prepare("DELETE FROM work_types WHERE id = :id");
            $stmt2->execute(['id' => $id]);
            
            echo json_encode(['success' => true, 'message' => 'Pipeline template deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid Pipeline ID']);
        }
        break;

    case 'get_required_docs':
        $workTypeId = intval($_GET['work_type_id'] ?? $_POST['work_type_id'] ?? 0);
        if ($workTypeId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid work type ID']);
            exit;
        }
        
        $stmt = $db->prepare("
            SELECT dt.id, dt.name, dt.is_mandatory 
            FROM document_types dt
            JOIN work_type_required_docs wtrd ON dt.id = wtrd.document_type_id
            WHERE wtrd.work_type_id = :wt_id
            ORDER BY dt.name ASC
        ");
        $stmt->execute(['wt_id' => $workTypeId]);
        $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $docs]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
