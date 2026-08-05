<?php
$pageTitle = 'Edit Case File Details';
require_once __DIR__ . '/../../includes/header.php';

requireLogin();
requireAdmin();

$db = getDB();
$fileId = intval($_GET['id'] ?? 0);

// Fetch File details
$stmtFile = $db->prepare("SELECT * FROM files WHERE id = :id LIMIT 1");
$stmtFile->execute(['id' => $fileId]);
$file = $stmtFile->fetch();

if (!$file) {
    echo "<div class='alert alert-danger'>File not found!</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$error = '';
$success = '';

// Handle Post update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_file'])) {
    $customerName = sanitize($_POST['customer_name'] ?? '');
    $customerMobile = sanitize($_POST['customer_mobile'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $status = sanitize($_POST['status'] ?? 'pending');
    $assignedUser = intval($_POST['current_assigned_user'] ?? 0);
    $branchId = intval($_POST['branch_id'] ?? $file['branch_id']);
    $stageId = intval($_POST['current_stage_id'] ?? 0);

    if (empty($customerName) || empty($customerMobile)) {
        $error = "Customer Name and Mobile Number are required.";
    } else {
        try {
            $stmtUpdate = $db->prepare("
                UPDATE files 
                SET customer_name = :name, 
                    customer_mobile = :mobile, 
                    description = :desc, 
                    status = :status, 
                    current_assigned_user = :user, 
                    branch_id = :branch, 
                    current_stage_id = :stage,
                    updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id
            ");
            $stmtUpdate->execute([
                'name' => $customerName,
                'mobile' => $customerMobile,
                'desc' => $description,
                'status' => $status,
                'user' => $assignedUser ?: null,
                'branch' => $branchId,
                'stage' => $stageId ?: null,
                'id' => $fileId
            ]);

            // Add Audit log
            logActivity($_SESSION['user_id'], 'FILE_EDIT_ADMIN', "Admin edited metadata details of file ID: {$fileId} ({$file['file_code']})");

            setFlashMessage('success', "File details updated successfully!");
            header("Location: view.php?id=" . $fileId);
            exit;
        } catch (Exception $e) {
            $error = "Update failed: " . $e->getMessage();
        }
    }
}

// Fetch all branches
$branches = $db->query("SELECT * FROM branches ORDER BY id ASC")->fetchAll();

// Fetch all stages for this work type
$stmtStages = $db->prepare("SELECT * FROM workflow_stages WHERE work_type_id = :wt ORDER BY stage_order ASC");
$stmtStages->execute(['wt' => $file['work_type_id']]);
$stages = $stmtStages->fetchAll();

// Fetch all active employees
$employees = $db->query("
    SELECT u.*, r.role_name, b.branch_code 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE u.status = 'active' 
    ORDER BY u.name ASC
")->fetchAll();
?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm p-4" style="border-radius: var(--radius-lg);">
      <div class="border-bottom pb-3 mb-4 d-flex justify-content-between align-items-center">
        <div>
          <h4 class="fw-bold mb-1 text-dark"><i class="fas fa-edit text-primary me-2"></i> Edit Case File details</h4>
          <p class="text-muted small mb-0">Modify metadata coordinates, status, assignment, and branch allocations</p>
        </div>
        <span class="badge bg-dark px-3 py-2">Case: <?= htmlspecialchars($file['file_code']) ?></span>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 px-3 small mb-3"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form action="edit.php?id=<?= $fileId ?>" method="POST">
        <input type="hidden" name="update_file" value="1">

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label fw-bold">Customer Name</label>
            <input type="text" name="customer_name" class="form-control" value="<?= htmlspecialchars($file['customer_name']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold">Customer Mobile</label>
            <input type="text" name="customer_mobile" class="form-control" value="<?= htmlspecialchars($file['customer_mobile']) ?>" required>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold">Case Description</label>
          <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($file['description']) ?></textarea>
        </div>

        <div class="row g-3 mb-4">
          <!-- Status Selector -->
          <div class="col-md-4">
            <label class="form-label fw-bold">Current Status</label>
            <select name="status" class="form-select">
              <option value="pending" <?= $file['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
              <option value="in_progress" <?= $file['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
              <option value="on_hold" <?= $file['status'] === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
              <option value="completed" <?= $file['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
              <option value="rejected" <?= $file['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
          </div>

          <!-- Branch Location -->
          <div class="col-md-4">
            <label class="form-label fw-bold">Processing Branch</label>
            <select name="branch_id" class="form-select">
              <?php foreach ($branches as $br): ?>
                <option value="<?= $br['id'] ?>" <?= $file['branch_id'] == $br['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($br['branch_name']) ?> (<?= htmlspecialchars($br['branch_code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Current Stage -->
          <div class="col-md-4">
            <label class="form-label fw-bold">Workflow Stage Step</label>
            <select name="current_stage_id" class="form-select">
              <option value="0">Intake / Direct Completion</option>
              <?php foreach ($stages as $stg): ?>
                <option value="<?= $stg['id'] ?>" <?= $file['current_stage_id'] == $stg['id'] ? 'selected' : '' ?>>
                  Step <?= $stg['stage_order'] ?>: <?= htmlspecialchars($stg['stage_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="mb-4">
          <label class="form-label fw-bold">Assigned Employee</label>
          <select name="current_assigned_user" class="form-select">
            <option value="0">-- Unassigned --</option>
            <?php foreach ($employees as $emp): ?>
              <option value="<?= $emp['id'] ?>" <?= $file['current_assigned_user'] == $emp['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($emp['name']) ?> (<?= htmlspecialchars($emp['role_name']) ?>) - Branch: <?= htmlspecialchars($emp['branch_code'] ?? 'HQ') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="d-flex justify-content-end gap-2 border-top pt-3">
          <a href="view.php?id=<?= $fileId ?>" class="btn btn-secondary fw-semibold">Cancel</a>
          <button type="submit" class="btn btn-primary fw-bold px-4">Save Changes</button>
        </div>

      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
