<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requireAdmin();

$db = getDB();
$error = '';
$success = '';

// Handle Delete Branch Action (GET)
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $deleteId = intval($_GET['id'] ?? 0);
    if ($deleteId > 0) {
        // Verify if branch has active cases or active users!
        $stmtCount = $db->prepare("SELECT COUNT(*) FROM files WHERE branch_id = :bid");
        $stmtCount->execute(['bid' => $deleteId]);
        $filesCount = $stmtCount->fetchColumn();

        $stmtUsers = $db->prepare("SELECT COUNT(*) FROM users WHERE branch_id = :bid");
        $stmtUsers->execute(['bid' => $deleteId]);
        $usersCount = $stmtUsers->fetchColumn();

        if ($filesCount > 0 || $usersCount > 0) {
            $error = "Cannot delete branch. It has active employees or case files linked to it.";
        } else {
            $stmtDel = $db->prepare("DELETE FROM branches WHERE id = :id");
            $stmtDel->execute(['id' => $deleteId]);
            logActivity($_SESSION['user_id'], 'BRANCH_DELETE', "Permanently deleted branch ID: {$deleteId}");
            $success = "Branch deleted successfully!";
        }
    }
}

// Handle Add/Edit Branch Action (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Edit Branch
    if (isset($_POST['edit_branch'])) {
        $branchId = intval($_POST['branch_id'] ?? 0);
        $name = sanitize($_POST['branch_name'] ?? '');
        $code = strtoupper(sanitize($_POST['branch_code'] ?? ''));
        $city = sanitize($_POST['branch_city'] ?? '');

        if ($branchId > 0 && !empty($name) && !empty($code)) {
            try {
                $stmt = $db->prepare("UPDATE branches SET branch_name = :name, branch_code = :code, branch_city = :city WHERE id = :id");
                $stmt->execute(['name' => $name, 'code' => $code, 'city' => $city, 'id' => $branchId]);
                logActivity($_SESSION['user_id'], 'BRANCH_EDIT', "Updated branch details for ID: {$branchId}");
                $success = "Branch details updated successfully!";
            } catch (Exception $e) {
                $error = "Failed to update branch. Code may conflict.";
            }
        }
    }
    // Add Branch
    elseif (isset($_POST['add_branch'])) {
        $name = sanitize($_POST['branch_name'] ?? '');
        $code = strtoupper(sanitize($_POST['branch_code'] ?? ''));
        $city = sanitize($_POST['branch_city'] ?? '');

        if (empty($name) || empty($code)) {
            $error = "Branch Name and unique Code are required.";
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO branches (branch_name, branch_code, branch_city) VALUES (:name, :code, :city)");
                $stmt->execute(['name' => $name, 'code' => $code, 'city' => $city]);
                logActivity($_SESSION['user_id'], 'BRANCH_ADD', "Created new branch: {$name} ({$code})");
                $success = "Branch added successfully!";
            } catch (Exception $e) {
                $error = "Failed to add branch. Code may already exist.";
            }
        }
    }
}

// Fetch all branches with case statistics
$branches = $db->query("
    SELECT b.*, 
           (SELECT COUNT(*) FROM files f WHERE f.branch_id = b.id) as total_cases,
           (SELECT COUNT(*) FROM files f WHERE f.branch_id = b.id AND f.status IN ('pending', 'in_progress')) as active_cases,
           (SELECT COUNT(*) FROM files f WHERE f.branch_id = b.id AND f.status = 'completed') as completed_cases
    FROM branches b
    ORDER BY b.id ASC
")->fetchAll();

// Handle filter selection (default to Delhi branch if not specified)
$selectedBranchId = intval($_GET['branch_id'] ?? ($branches[0]['id'] ?? 0));

// Fetch active cases list inside selected branch
$branchCases = [];
if ($selectedBranchId > 0) {
    $stmtCases = $db->prepare("
        SELECT f.*, wt.name as work_type_name, ws.stage_name, u.name as employee_name 
        FROM files f 
        LEFT JOIN work_types wt ON f.work_type_id = wt.id 
        LEFT JOIN workflow_stages ws ON f.current_stage_id = ws.id 
        LEFT JOIN users u ON f.current_assigned_user = u.id 
        WHERE f.branch_id = :bid 
        ORDER BY f.updated_at DESC
    ");
    $stmtCases->execute(['bid' => $selectedBranchId]);
    $branchCases = $stmtCases->fetchAll();
}

$pageTitle = 'Multi-Branch Operations Hub';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
  <!-- Left Side: Branches Directory & Stats -->
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: var(--radius-lg);">
      <div class="border-bottom pb-3 mb-3 d-flex justify-content-between align-items-center">
        <div>
          <h5 class="fw-bold mb-1 text-dark"><i class="fas fa-building text-primary me-2"></i> Corporate Branches</h5>
          <p class="text-muted small mb-0">Total configured branch offices and active workloads</p>
        </div>
        <button class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#addBranchBlock">
          <i class="fas fa-plus me-1"></i> Add Branch
        </button>
      </div>

      <!-- Add Branch Block (Collapsible) -->
      <div class="collapse mb-3" id="addBranchBlock">
        <div class="card p-3 border bg-light-soft" style="border-radius: var(--radius-md);">
          <form action="branches.php" method="POST">
            <input type="hidden" name="add_branch" value="1">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label small fw-bold">Branch Name</label>
                <input type="text" name="branch_name" class="form-control form-control-sm" placeholder="e.g. Kolkata Branch" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-bold">Unique Code</label>
                <input type="text" name="branch_code" class="form-control form-control-sm text-uppercase" placeholder="e.g. CCU" maxlength="5" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-bold">City Location</label>
                <input type="text" name="branch_city" class="form-control form-control-sm" placeholder="e.g. Kolkata">
              </div>
              <div class="col-12 text-end">
                <button type="submit" class="btn btn-sm btn-primary fw-bold px-3 shadow-sm"><i class="fas fa-save me-1"></i> Save Branch</button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 px-3 small mb-3"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if (!empty($success)): ?>
        <div class="alert alert-success py-2 px-3 small mb-3"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <!-- Branches List -->
      <div class="list-group gap-2">
        <?php foreach ($branches as $b): 
          $isActive = ($selectedBranchId === $b['id']);
        ?>
          <div class="list-group-item list-group-item-action p-0 border rounded d-flex align-items-stretch overflow-hidden <?= $isActive ? 'active shadow-sm bg-primary text-white' : 'bg-transparent' ?>" style="transition: var(--transition);">
            <a href="branches.php?branch_id=<?= $b['id'] ?>" class="flex-grow-1 p-3 text-decoration-none d-flex justify-content-between align-items-center" style="color: inherit !important;">
              <div>
                <h6 class="fw-bold mb-1">
                  <?= htmlspecialchars($b['branch_name']) ?> (<?= htmlspecialchars($b['branch_code']) ?>)
                </h6>
                <small class="opacity-75"><i class="fas fa-map-marker-alt me-1"></i> <?= htmlspecialchars($b['branch_city'] ?: 'N/A') ?></small>
              </div>
              <div class="text-end me-2">
                <span class="badge <?= $isActive ? 'bg-light text-primary' : 'bg-primary-soft text-primary' ?> px-2.5 py-1.5 rounded-pill shadow-xs" style="font-size: 0.75rem;">
                  <?= $b['active_cases'] ?> Active
                </span>
              </div>
            </a>
            
            <div class="p-2 border-start border-opacity-10 d-flex align-items-center gap-1 bg-light-soft">
              <button class="btn btn-xs btn-outline-primary py-1 px-2" onclick="editBranch(<?= htmlspecialchars(json_encode($b)) ?>)" title="Edit Branch">
                <i class="fas fa-edit" style="font-size: 0.75rem;"></i>
              </button>
              <a href="branches.php?action=delete&id=<?= $b['id'] ?>" class="btn btn-xs btn-outline-danger py-1 px-2" data-confirm="Are you sure you want to delete this branch? All active link dependencies will verify." data-confirm-title="Delete Branch?" data-confirm-btn="Yes, Delete" title="Delete Branch">
                <i class="fas fa-trash-alt" style="font-size: 0.75rem;"></i>
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Right Side: Branch Tasks Overview (Kaam chal raha hai view) -->
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm p-4 h-100" style="border-radius: var(--radius-lg);">
      <?php 
        $activeBranch = null;
        foreach ($branches as $b) {
            if ($b['id'] === $selectedBranchId) {
                $activeBranch = $b;
                break;
            }
        }
      ?>
      <div class="border-bottom pb-3 mb-4 d-flex justify-content-between align-items-center">
        <div>
          <h5 class="fw-bold mb-1 text-dark"><i class="fas fa-project-diagram text-success me-2"></i> Branch Task Tracker</h5>
          <p class="text-muted small mb-0">Active cases and workflow operations in <strong><?= htmlspecialchars($activeBranch['branch_name'] ?? 'Select Branch') ?></strong></p>
        </div>
        <span class="badge bg-success-soft text-success border px-3 py-2"><i class="fas fa-circle blink-indicator me-1"></i> LIVE</span>
      </div>

      <?php if (empty($branchCases)): ?>
        <div class="text-center py-5 text-muted">
          <i class="fas fa-folder-open fa-3x mb-3 text-secondary opacity-50"></i>
          <h6>No cases or workflow activities found in this branch.</h6>
          <p class="small text-muted">New cases created will be listed here after branch assignment.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Case Code</th>
                <th>Customer Name</th>
                <th>Work Type</th>
                <th>Current Stage</th>
                <th>Assignee</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($branchCases as $c): ?>
                <tr>
                  <td>
                    <a href="<?= APP_URL ?>/modules/file/view.php?id=<?= $c['id'] ?>" class="fw-bold text-primary text-decoration-none">
                      <?= htmlspecialchars($c['file_code']) ?>
                    </a>
                  </td>
                  <td>
                    <div class="fw-bold text-dark" style="font-size: 0.9rem;"><?= htmlspecialchars($c['customer_name']) ?></div>
                  </td>
                  <td><span class="small text-dark"><?= htmlspecialchars($c['work_type_name']) ?></span></td>
                  <td>
                    <span class="badge bg-light text-dark border small">
                      <?= htmlspecialchars($c['stage_name'] ?? 'Initial Intake') ?>
                    </span>
                  </td>
                  <td>
                    <small class="text-muted fw-semibold"><?= htmlspecialchars($c['employee_name'] ?? 'Unassigned') ?></small>
                  </td>
                  <td><?= getStatusBadgeHtml($c['status']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modal: Edit Branch -->
<div class="modal fade" id="editBranchModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-lg);">
      <form action="branches.php" method="POST" id="editBranchForm">
        <input type="hidden" name="edit_branch" value="1">
        <input type="hidden" name="branch_id" id="edit_branch_id">
        
        <div class="modal-header bg-dark text-white">
          <h5 class="modal-title fw-bold"><i class="fas fa-building text-primary me-2"></i> Edit Branch Office</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-bold">Branch Name</label>
            <input type="text" name="branch_name" id="edit_branch_name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Unique Code</label>
            <input type="text" name="branch_code" id="edit_branch_code" class="form-control text-uppercase" maxlength="5" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">City Location</label>
            <input type="text" name="branch_city" id="edit_branch_city" class="form-control">
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary fw-bold">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function editBranch(b) {
  document.getElementById('edit_branch_id').value = b.id;
  document.getElementById('edit_branch_name').value = b.branch_name;
  document.getElementById('edit_branch_code').value = b.branch_code;
  document.getElementById('edit_branch_city').value = b.branch_city;
  
  const modal = new bootstrap.Modal(document.getElementById('editBranchModal'));
  modal.show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
