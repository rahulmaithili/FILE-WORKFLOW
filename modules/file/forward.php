<?php
$pageTitle = 'Forward Workflow File';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$user = getLoggedInUser();
$fileId = intval($_GET['id'] ?? 0);

// Fetch File details
$stmtFile = $db->prepare("
    SELECT f.*, wt.name as work_type_name, ws.stage_name, ws.stage_order, ws.assigned_role_id,
           u.name as assigned_user_name 
    FROM files f 
    LEFT JOIN work_types wt ON f.work_type_id = wt.id 
    LEFT JOIN workflow_stages ws ON f.current_stage_id = ws.id 
    LEFT JOIN users u ON f.current_assigned_user = u.id 
    WHERE f.id = :id 
    LIMIT 1
");
$stmtFile->execute(['id' => $fileId]);
$file = $stmtFile->fetch();

if (!$file) {
    echo "<div class='alert alert-danger'>File not found!</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Check access permission
if (!canAccessFile($file)) {
    echo "<div class='alert alert-danger'>Access Denied. Only the assigned employee or Admin can forward this file.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Fetch all stages for this work type
$stmtStages = $db->prepare("
    SELECT ws.*, r.role_name 
    FROM workflow_stages ws 
    JOIN roles r ON ws.assigned_role_id = r.id 
    WHERE ws.work_type_id = :wt 
    ORDER BY ws.stage_order ASC
");
$stmtStages->execute(['wt' => $file['work_type_id']]);
$stages = $stmtStages->fetchAll();

// Determine Next Stage
$currentOrder = $file['stage_order'] ?? 1;
$nextStage = null;
foreach ($stages as $stg) {
    if ($stg['stage_order'] > $currentOrder) {
        $nextStage = $stg;
        break;
    }
}

// Eligible employees for next stage
$eligibleUsers = [];
if ($nextStage) {
    $stmtUsers = $db->prepare("
        SELECT u.*, r.role_name, b.branch_code 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        LEFT JOIN branches b ON u.branch_id = b.id
        WHERE u.role_id = :rid AND u.status = 'active'
    ");
    $stmtUsers->execute(['rid' => $nextStage['assigned_role_id']]);
    $eligibleUsers = $stmtUsers->fetchAll();
}

// Fetch allowed target branches from routing rules
$stmtAllowedBranches = $db->prepare("
    SELECT b.* 
    FROM branch_routing_rules r
    JOIN branches b ON r.to_branch_id = b.id
    WHERE r.from_branch_id = :curr_bid AND r.status = 'enabled'
");
$stmtAllowedBranches->execute(['curr_bid' => $file['branch_id']]);
$allowedBranches = $stmtAllowedBranches->fetchAll();

// Ensure current branch is in the list
$stmtCurrentBranch = $db->prepare("SELECT * FROM branches WHERE id = :bid LIMIT 1");
$stmtCurrentBranch->execute(['bid' => $file['branch_id']]);
$currentBranchInfo = $stmtCurrentBranch->fetch();

if ($currentBranchInfo) {
    $hasCurrent = false;
    foreach ($allowedBranches as $ab) {
        if ($ab['id'] == $currentBranchInfo['id']) {
            $hasCurrent = true;
            break;
        }
    }
    if (!$hasCurrent) {
        array_unshift($allowedBranches, $currentBranchInfo);
    }
}

// Fallback: fetch all active users with branch code
$allUsers = $db->query("
    SELECT u.*, r.role_name, b.branch_code 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE u.status = 'active' 
    ORDER BY u.name ASC
")->fetchAll();

// Handle Forward Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetStageId = intval($_POST['target_stage_id'] ?? 0);
    $targetUserId = intval($_POST['target_user_id'] ?? 0);
    $targetBranchId = intval($_POST['target_branch_id'] ?? $file['branch_id']);
    $remarks = sanitize($_POST['remarks'] ?? '');
    $markComplete = isset($_POST['mark_completed']);

    $newStatus = $markComplete ? 'completed' : 'in_progress';
    $finalUser = $markComplete ? $user['id'] : ($targetUserId ?: $file['current_assigned_user']);

    // Update File
    $stmtUpdate = $db->prepare("
        UPDATE files 
        SET current_stage_id = :stage, current_assigned_user = :user, status = :status, branch_id = :branch_id, updated_at = CURRENT_TIMESTAMP 
        WHERE id = :id
    ");
    $stmtUpdate->execute([
        'stage' => $targetStageId > 0 ? $targetStageId : null,
        'user' => $finalUser,
        'status' => $newStatus,
        'branch_id' => $targetBranchId,
        'id' => $fileId
    ]);

    // Record History Audit
    $stmtHist = $db->prepare("
        INSERT INTO file_history (file_id, from_user, to_user, stage_id, action_type, remarks, from_branch_id, to_branch_id) 
        VALUES (:fid, :from_u, :to_u, :stage, 'forwarded', :rem, :fb, :tb)
    ");
    $stmtHist->execute([
        'fid' => $fileId,
        'from_u' => $user['id'],
        'to_u' => $finalUser,
        'stage' => $targetStageId ?: null,
        'rem' => $remarks ?: ($markComplete ? 'Work completed and approved.' : 'File forwarded to next workflow stage.'),
        'fb' => $file['branch_id'],
        'tb' => $targetBranchId
    ]);

    // Trigger Notifications
    if ($markComplete) {
        // Notify file creator that the case is fully completed
        addNotification($file['created_by'], 'Case File Completed', "Case file {$file['file_code']} has been marked as fully completed.", APP_URL . '/modules/file/view.php?id=' . $fileId);
    } else {
        // Notify the next assigned employee
        addNotification($finalUser, 'Case File Received', "Case file {$file['file_code']} has been forwarded to you by {$user['name']}.", APP_URL . '/modules/file/view.php?id=' . $fileId);
    }

    setFlashMessage('success', $markComplete ? "File successfully completed!" : "File forwarded to next employee.");
    header("Location: view.php?id=" . $fileId);
    exit;
}
?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card border-0 shadow-lg p-4" style="border-radius: var(--radius-lg);">
      <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
        <div>
          <h4 class="fw-bold mb-1 text-dark"><i class="fas fa-paper-plane text-primary me-2"></i> Forward Workflow File</h4>
          <span class="fw-bold text-primary"><?= htmlspecialchars($file['file_code']) ?></span> &bull; Customer: <strong><?= htmlspecialchars($file['customer_name']) ?></strong>
        </div>
        <?= getStatusBadgeHtml($file['status']) ?>
      </div>

      <form action="forward.php?id=<?= $fileId ?>" method="POST">
        
        <!-- Step Progression Summary -->
        <div class="row g-3 mb-4 p-3 bg-light rounded">
          <div class="col-md-6">
            <small class="text-muted d-block fw-bold">Current Stage Step</small>
            <span class="badge bg-secondary fs-6"><i class="fas fa-layer-group me-1"></i> <?= htmlspecialchars($file['stage_name'] ?? 'Initial Intake') ?></span>
          </div>
          <div class="col-md-6">
            <small class="text-muted d-block fw-bold">Suggested Next Stage</small>
            <?php if ($nextStage): ?>
              <span class="badge bg-success fs-6"><i class="fas fa-arrow-right me-1"></i> <?= htmlspecialchars($nextStage['stage_name']) ?></span>
            <?php else: ?>
              <span class="badge bg-info text-dark fs-6"><i class="fas fa-flag-checkered me-1"></i> Final Stage Reached</span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Target Branch Selection -->
        <div class="mb-4">
          <label class="form-label fw-bold">Target Branch Location</label>
          <select name="target_branch_id" id="target_branch_id" class="form-select form-select-lg" required>
            <?php foreach ($allowedBranches as $ab): ?>
              <option value="<?= $ab['id'] ?>" <?= ($file['branch_id'] == $ab['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($ab['branch_name']) ?> (<?= htmlspecialchars($ab['branch_code']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted">Inter-branch routing is filtered based on permitted workflow paths</small>
        </div>

        <!-- Target Stage Selection -->
        <div class="mb-4">
          <label class="form-label fw-bold">Target Workflow Stage</label>
          <?php if (empty($stages)): ?>
            <div class="alert alert-warning py-2.5 px-3 mb-2 small" style="border-radius: var(--radius-sm);">
              <i class="fas fa-info-circle me-1 text-warning"></i> <strong>Tip:</strong> No stages have been defined for this Work Type yet. You can directly complete this file, or design its stages in the <a href="<?= APP_URL ?>/admin/workflow-builder.php?work_type_id=<?= $file['work_type_id'] ?>" class="fw-bold text-decoration-none">Workflow Builder</a>.
            </div>
          <?php endif; ?>
          <select name="target_stage_id" class="form-select form-select-lg" required>
            <?php if (empty($stages)): ?>
              <option value="0" selected>Direct Completion / Final Review</option>
            <?php else: ?>
              <?php foreach ($stages as $stg): ?>
                <option value="<?= $stg['id'] ?>" <?= ($nextStage && $nextStage['id'] == $stg['id']) || ($file['current_stage_id'] == $stg['id']) ? 'selected' : '' ?>>
                  Step <?= $stg['stage_order'] ?>: <?= htmlspecialchars($stg['stage_name']) ?> (Role: <?= htmlspecialchars($stg['role_name']) ?>)
                </option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>

        <!-- Target Employee Selection -->
        <div class="mb-4" id="employeeSelectBlock">
          <label class="form-label fw-bold">Assign Next Employee</label>
          <select name="target_user_id" id="target_user_id" class="form-select form-select-lg" <?= $nextStage ? 'required' : '' ?>>
            <option value="">-- Choose Employee --</option>
            <?php if (!empty($eligibleUsers)): ?>
              <optgroup label="Suggested Role Employees">
                <?php foreach ($eligibleUsers as $u): ?>
                  <option value="<?= $u['id'] ?>" selected><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['role_name']) ?>) - Branch: <?= htmlspecialchars($u['branch_code'] ?? 'HQ') ?></option>
                <?php endforeach; ?>
              </optgroup>
            <?php endif; ?>
            <optgroup label="All Company Employees">
              <?php foreach ($allUsers as $u): ?>
                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['role_name']) ?>) - Branch: <?= htmlspecialchars($u['branch_code'] ?? 'HQ') ?></option>
              <?php endforeach; ?>
            </optgroup>
          </select>
        </div>

        <!-- Remarks / Work Summary -->
        <div class="mb-4">
          <label class="form-label fw-bold">Work Done & Forwarding Remarks</label>
          <textarea name="remarks" class="form-control" rows="3" placeholder="Enter notes or work done summary for the next employee..."></textarea>
        </div>

        <!-- Final Approval Checkbox if final stage or no stages or user is Admin -->
        <?php if (!$nextStage || empty($stages) || in_array($roleKey, ['super_admin', 'admin'])): ?>
          <div class="form-check card p-3 border-success bg-success-soft mb-4">
            <input class="form-check-input ms-1" type="checkbox" name="mark_completed" value="1" id="mark_completed" <?= (!$nextStage || empty($stages)) ? 'checked' : '' ?>>
            <label class="form-check-label fw-bold text-success ms-2" for="mark_completed">
              <i class="fas fa-check-circle me-1"></i> Mark Case File as FULLY COMPLETED
            </label>
          </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center border-top pt-3">
          <a href="view.php?id=<?= $fileId ?>" class="btn btn-light border text-muted">Cancel</a>
          <button type="submit" class="btn btn-primary btn-lg fw-bold px-4 shadow-sm">
            <i class="fas fa-paper-plane me-1"></i> Confirm & Forward File
          </button>
        </div>

      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const markCompletedCb = document.getElementById('mark_completed');
  const empSelectBlock = document.getElementById('employeeSelectBlock');
  const targetUserIdSelect = document.getElementById('target_user_id');

  if (markCompletedCb && empSelectBlock && targetUserIdSelect) {
    function toggleEmpSelect() {
      if (markCompletedCb.checked) {
        empSelectBlock.style.display = 'none';
        targetUserIdSelect.removeAttribute('required');
      } else {
        empSelectBlock.style.display = 'block';
        targetUserIdSelect.setAttribute('required', 'required');
      }
    }
    markCompletedCb.addEventListener('change', toggleEmpSelect);
    toggleEmpSelect(); // Run initially
  }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
