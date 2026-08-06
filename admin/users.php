<?php
$pageTitle = 'Employee Directory Management';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$db = getDB();

// Handle User Deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $deleteId = intval($_GET['id'] ?? 0);
    $currentUser = getLoggedInUser();

    if ($deleteId > 0 && $deleteId !== $currentUser['id']) {
        try {
            // Check if user has active cases assigned
            $stmtCheck = $db->prepare("SELECT COUNT(*) FROM files WHERE current_assigned_user = :uid AND status != 'completed'");
            $stmtCheck->execute(['uid' => $deleteId]);
            $activeCount = $stmtCheck->fetchColumn();

            if ($activeCount > 0) {
                // If they have active cases, de-activate them instead of deleting
                $stmtDeactivate = $db->prepare("UPDATE users SET status = 'inactive' WHERE id = :id");
                $stmtDeactivate->execute(['id' => $deleteId]);
                logActivity($currentUser['id'], 'USER_DEACTIVATE', "Deactivated employee ID: {$deleteId} due to active assigned cases.");
                setFlashMessage('warning', 'Employee has active cases assigned. Their status was changed to "Inactive" instead of full deletion.');
            } else {
                // Wipe user
                $stmtDelete = $db->prepare("DELETE FROM users WHERE id = :id");
                $stmtDelete->execute(['id' => $deleteId]);
                logActivity($currentUser['id'], 'USER_DELETE', "Permanently deleted employee account ID: {$deleteId}");
                setFlashMessage('success', 'Employee account deleted successfully.');
            }
        } catch (Exception $e) {
            // Soft-deactivate if FKey fails
            $stmtDeactivate = $db->prepare("UPDATE users SET status = 'inactive' WHERE id = :id");
            $stmtDeactivate->execute(['id' => $deleteId]);
            logActivity($currentUser['id'], 'USER_DEACTIVATE', "Deactivated employee ID: {$deleteId} due to database constraints.");
            setFlashMessage('warning', 'Employee has past historical data records. Account set to "Inactive" for audit log integrity.');
        }
    }
    header("Location: users.php");
    exit;
}

// Handle Dynamic Role & Permissions Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_role_permissions'])) {
    $rolePermissions = $_POST['role_perms'] ?? []; // Map of role_id => array of permissions

    // Fetch all roles from database
    $allRoles = $db->query("SELECT * FROM roles")->fetchAll();

    foreach ($allRoles as $role) {
        $rid = intval($role['id']);
        if ($role['role_key'] === 'super_admin') continue; // Do not edit super_admin

        // Get permissions chosen for this role, else empty array
        $permsList = $rolePermissions[$rid] ?? [];
        
        // Convert to JSON
        $permsJson = json_encode($permsList);

        // Update database
        $stmtRoleUpdate = $db->prepare("UPDATE roles SET permissions = :perms WHERE id = :id");
        $stmtRoleUpdate->execute(['perms' => $permsJson, 'id' => $rid]);
    }

    setFlashMessage('success', 'Role permissions updated successfully.');
    header("Location: users.php");
    exit;
}

// Handle User Creation / Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $mobile = sanitize($_POST['mobile'] ?? '');
    $roleId = intval($_POST['role_id'] ?? 0);
    $status = sanitize($_POST['status'] ?? 'active');
    $password = $_POST['password'] ?? '';

    $branchId = intval($_POST['branch_id'] ?? 1);

    if (isset($_POST['user_id']) && !empty($_POST['user_id'])) {
        $userId = intval($_POST['user_id']);
        if (!empty($password)) {
            $hashedPass = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET name = :name, email = :email, mobile = :mobile, role_id = :role_id, status = :status, password = :pass, branch_id = :branch_id WHERE id = :id");
            $stmt->execute(['name' => $name, 'email' => $email, 'mobile' => $mobile, 'role_id' => $roleId, 'status' => $status, 'pass' => $hashedPass, 'branch_id' => $branchId, 'id' => $userId]);
        } else {
            $stmt = $db->prepare("UPDATE users SET name = :name, email = :email, mobile = :mobile, role_id = :role_id, status = :status, branch_id = :branch_id WHERE id = :id");
            $stmt->execute(['name' => $name, 'email' => $email, 'mobile' => $mobile, 'role_id' => $roleId, 'status' => $status, 'branch_id' => $branchId, 'id' => $userId]);
        }
        setFlashMessage('success', 'Employee updated successfully.');
    } else {
        $hashedPass = password_hash($password ?: 'password123', PASSWORD_BCRYPT);
        $stmt = $db->prepare("INSERT INTO users (name, email, mobile, role_id, status, password, branch_id) VALUES (:name, :email, :mobile, :role_id, :status, :pass, :branch_id)");
        $stmt->execute(['name' => $name, 'email' => $email, 'mobile' => $mobile, 'role_id' => $roleId, 'status' => $status, 'pass' => $hashedPass, 'branch_id' => $branchId]);
        setFlashMessage('success', 'New employee created successfully.');
    }
    header("Location: users.php");
    exit;
}

$users = $db->query("
    SELECT u.*, r.role_name, b.branch_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    LEFT JOIN branches b ON u.branch_id = b.id
    ORDER BY u.id DESC
")->fetchAll();

$roles = $db->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();
$branches = $db->query("SELECT * FROM branches ORDER BY branch_name ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1">Employee Directory</h4>
    <p class="text-muted small mb-0">Add company employees, assign workflow roles, and configure system credentials</p>
  </div>
  <div class="d-flex gap-2">
    <button class="btn btn-outline-primary fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#permissionsModal">
      <i class="fas fa-shield-alt me-1"></i> Permissions Builder
    </button>
    <button class="btn btn-primary fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetUserForm()">
      <i class="fas fa-user-plus me-1"></i> Add New Employee
    </button>
  </div>
</div>

<div class="card border-0 shadow-sm p-4" style="border-radius: var(--radius-lg);">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Employee Name</th>
          <th>Contact Email</th>
          <th>Mobile</th>
          <th>Workflow Role</th>
          <th>Branch Location</th>
          <th>Status</th>
          <th>Joined Date</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-3">
                <div class="avatar-circle">
                  <?= strtoupper(substr($u['name'], 0, 1)) ?>
                </div>
                <div>
                  <div class="fw-bold text-dark"><?= htmlspecialchars($u['name']) ?></div>
                  <small class="text-muted">ID: #EMP-<?= $u['id'] ?></small>
                </div>
              </div>
            </td>
            <td><a href="mailto:<?= htmlspecialchars($u['email']) ?>" class="text-decoration-none text-dark"><?= htmlspecialchars($u['email']) ?></a></td>
            <td><i class="fas fa-phone text-muted me-1 small"></i> <?= htmlspecialchars($u['mobile']) ?></td>
            <td><span class="badge bg-primary-soft text-primary border"><?= htmlspecialchars($u['role_name']) ?></span></td>
            <td><span class="badge bg-light text-dark border"><i class="fas fa-building text-muted me-1"></i> <?= htmlspecialchars($u['branch_name'] ?? 'Headquarters') ?></span></td>
            <td>
              <?php if ($u['status'] === 'active'): ?>
                <span class="badge bg-success text-white">Active</span>
              <?php else: ?>
                <span class="badge bg-secondary text-white">Inactive</span>
              <?php endif; ?>
            </td>
            <td><small class="text-muted"><?= date('d M Y', strtotime($u['created_at'])) ?></small></td>
            <td class="text-end">
              <div class="btn-group btn-group-sm">
                <button class="btn btn-light text-primary border" onclick="editUser(<?= htmlspecialchars(json_encode($u)) ?>)">
                  <i class="fas fa-edit me-1"></i> Edit
                </button>
                <?php if ($u['id'] != $user['id']): ?>
                  <a href="users.php?action=delete&id=<?= $u['id'] ?>" class="btn btn-light text-danger border" title="Delete User Account" data-confirm="Are you sure you want to delete this employee account? Active assignments will deactivate the user instead." data-confirm-title="Delete Employee?" data-confirm-btn="Yes, Delete">
                    <i class="fas fa-trash-alt me-1"></i> Delete
                  </a>
                <?php endif; ?>
                <?php if ($u['id'] != $user['id'] && $u['status'] === 'active'): ?>
                  <a href="<?= APP_URL ?>/admin/impersonate.php?id=<?= $u['id'] ?>" class="btn btn-outline-secondary border" title="Impersonate and login as <?= htmlspecialchars($u['name']) ?>">
                    <i class="fas fa-user-secret"></i> Login As
                  </a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: User Form -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-lg);">
      <form action="users.php" method="POST" id="userForm">
        <input type="hidden" name="user_id" id="user_id">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-bold" id="userModalTitle"><i class="fas fa-user-plus me-2"></i> Employee Setup</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-semibold">Full Name</label>
            <input type="text" name="name" id="user_name" class="form-control" placeholder="e.g. Vikram Singh" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Email Address</label>
            <input type="email" name="email" id="user_email" class="form-control" placeholder="e.g. vikram@office.com" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Mobile Number</label>
            <input type="text" name="mobile" id="user_mobile" class="form-control" placeholder="e.g. 9876543210" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Assigned Role</label>
            <select name="role_id" id="user_role_id" class="form-select" required>
              <option value="">-- Select Role --</option>
              <?php foreach ($roles as $r): ?>
                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['role_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Branch Location</label>
            <select name="branch_id" id="user_branch_id" class="form-select" required>
              <option value="">-- Select Branch --</option>
              <?php foreach ($branches as $br): ?>
                <option value="<?= $br['id'] ?>"><?= htmlspecialchars($br['branch_name']) ?> (<?= htmlspecialchars($br['branch_code']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Password <small class="text-muted fw-normal">(Leave blank to keep unchanged)</small></label>
            <input type="password" name="password" id="user_password" class="form-control" placeholder="••••••••">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Account Status</label>
            <select name="status" id="user_status" class="form-select">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary fw-bold">Save Employee</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function resetUserForm() {
  document.getElementById('user_id').value = '';
  document.getElementById('userForm').reset();
  document.getElementById('userModalTitle').innerText = 'Add New Employee';
}

function editUser(user) {
  document.getElementById('user_id').value = user.id;
  document.getElementById('user_name').value = user.name;
  document.getElementById('user_email').value = user.email;
  document.getElementById('user_mobile').value = user.mobile;
  document.getElementById('user_role_id').value = user.role_id;
  document.getElementById('user_branch_id').value = user.branch_id || 1;
  document.getElementById('user_status').value = user.status;
  document.getElementById('user_password').value = '';
  document.getElementById('userModalTitle').innerText = 'Edit Employee: ' + user.name;

  const modal = new bootstrap.Modal(document.getElementById('userModal'));
  modal.show();
}
</script>

<!-- Modal: Permissions Builder -->
<div class="modal fade" id="permissionsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-lg);">
      <form action="users.php" method="POST">
        <input type="hidden" name="save_role_permissions" value="1">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-bold"><i class="fas fa-shield-alt me-2"></i> Dynamic Role & Permissions Builder</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <p class="text-muted small mb-4">Select the access privileges for each employee role. Updates take effect immediately on next login or action.</p>

          <div class="table-responsive">
            <table class="table table-bordered align-middle">
              <thead class="table-light text-center small fw-bold">
                <tr>
                  <th class="text-start">Role Type</th>
                  <th>Create File</th>
                  <th>Scan Document</th>
                  <th>Delete File</th>
                  <th>Manage Settings & Users</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($roles as $r): 
                  if ($r['role_key'] === 'super_admin') {
                    $perms = ['*'];
                    $isLocked = true;
                  } else {
                    $perms = json_decode($r['permissions'] ?? '[]', true) ?: [];
                    $isLocked = false;
                  }
                ?>
                  <tr class="<?= $isLocked ? 'table-light text-muted' : '' ?>">
                    <td class="text-start fw-bold">
                      <?= htmlspecialchars($r['role_name']) ?>
                      <br><small class="fw-normal text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($r['description']) ?></small>
                    </td>
                    <td class="text-center">
                      <input class="form-check-input" type="checkbox" name="role_perms[<?= $r['id'] ?>][]" value="create_file" 
                        <?= (in_array('create_file', $perms) || in_array('*', $perms)) ? 'checked' : '' ?> <?= $isLocked ? 'disabled' : '' ?>>
                    </td>
                    <td class="text-center">
                      <input class="form-check-input" type="checkbox" name="role_perms[<?= $r['id'] ?>][]" value="scan_document" 
                        <?= (in_array('scan_document', $perms) || in_array('*', $perms)) ? 'checked' : '' ?> <?= $isLocked ? 'disabled' : '' ?>>
                    </td>
                    <td class="text-center">
                      <input class="form-check-input" type="checkbox" name="role_perms[<?= $r['id'] ?>][]" value="delete_file" 
                        <?= (in_array('delete_file', $perms) || in_array('*', $perms)) ? 'checked' : '' ?> <?= $isLocked ? 'disabled' : '' ?>>
                    </td>
                    <td class="text-center">
                      <input class="form-check-input" type="checkbox" name="role_perms[<?= $r['id'] ?>][]" value="manage_users" 
                        <?= (in_array('manage_users', $perms) || in_array('*', $perms)) ? 'checked' : '' ?> <?= $isLocked ? 'disabled' : '' ?>>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary fw-bold shadow-sm">
            <i class="fas fa-save me-1"></i> Save Roles & Permissions
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
