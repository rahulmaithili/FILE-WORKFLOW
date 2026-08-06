<?php
$pageTitle = 'Role & Permissions Matrix';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$db = getDB();

// Handle Permissions Matrix Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_matrix'])) {
    $matrixPerms = $_POST['matrix_perms'] ?? []; // Map of role_id => array of permissions

    $allRoles = $db->query("SELECT * FROM roles")->fetchAll();

    foreach ($allRoles as $role) {
        $rid = intval($role['id']);
        if ($role['role_key'] === 'super_admin') continue; // Locked

        $permsList = $matrixPerms[$rid] ?? [];
        
        // Convert array to JSON
        $permsJson = json_encode(array_values(array_filter($permsList)));

        $stmt = $db->prepare("UPDATE roles SET permissions = :perms WHERE id = :id");
        $stmt->execute(['perms' => $permsJson, 'id' => $rid]);
    }

    setFlashMessage('success', 'Roles and Permissions Matrix updated successfully!');
    header("Location: roles.php");
    exit;
}

// Handle Create Role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_role'])) {
    $roleName = sanitize($_POST['role_name'] ?? '');
    $roleKey = sanitize($_POST['role_key'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    
    if (!empty($roleName)) {
        $stmt = $db->prepare("INSERT INTO roles (role_name, role_key, description, permissions) VALUES (:name, :key, :desc, '[]')");
        $stmt->execute([
            'name' => $roleName,
            'key' => strtolower(str_replace(' ', '_', $roleKey ?: $roleName)),
            'desc' => $description
        ]);
        setFlashMessage('success', 'New role created successfully. Configure its permissions in the matrix below.');
    }
    header("Location: roles.php");
    exit;
}

$roles = $db->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();

// Define System Modules and their mapping to permission keys (V, A, E, D)
$matrixModules = [
    'GENERAL' => [
        'Dashboard Analytics View' => [
            'v' => 'view_dashboard',
            'a' => null,
            'e' => null,
            'd' => null,
        ]
    ],
    'FILES & CASES' => [
        'Cases Directory List' => [
            'v' => 'view_all_files',
            'a' => 'create_file',
            'e' => 'scan_document',
            'd' => 'delete_file',
        ]
    ],
    'WORKFLOW PIPELINES' => [
        'Workflow Pipeline Builder' => [
            'v' => 'view_pipelines',
            'a' => 'create_pipeline',
            'e' => 'edit_pipeline',
            'd' => 'delete_pipeline',
        ]
    ],
    'EMPLOYEES & DIRECTORY' => [
        'Employee Accounts Admin' => [
            'v' => 'view_users',
            'a' => 'create_user',
            'e' => 'edit_user',
            'd' => 'delete_user',
        ]
    ],
    'SYSTEM CONFIGURATION' => [
        'Company Settings & Logo' => [
            'v' => 'view_settings',
            'a' => null,
            'e' => 'edit_settings',
            'd' => null,
        ],
        'System Addons & Modules' => [
            'v' => 'view_addons',
            'a' => null,
            'e' => 'edit_addons',
            'd' => null,
        ]
    ]
];
?>

<style>
/* Premium Access Toggle Button Styles */
.perm-matrix-checkbox {
  display: none !important; /* Hide default checkbox */
}

.perm-matrix-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 6px;
  font-weight: 700;
  font-size: 0.8rem;
  color: #94a3b8;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  cursor: pointer;
  user-select: none;
  transition: all 0.15s ease-in-out;
}

.perm-matrix-btn:hover {
  background: #f1f5f9;
  border-color: #94a3b8;
  color: #475569;
}

/* Checked state */
.perm-matrix-checkbox:checked + .perm-matrix-btn {
  background: #10b981 !important; /* Premium Emerald Green */
  border-color: #10b981 !important;
  color: #ffffff !important;
  box-shadow: 0 0 10px rgba(16, 185, 129, 0.3);
}

/* Disabled state (Locked roles like Super Admin) */
.perm-matrix-checkbox:disabled + .perm-matrix-btn {
  background: #f8fafc !important;
  border-color: #e2e8f0 !important;
  color: #cbd5e1 !important;
  cursor: not-allowed;
}

/* Disabled + Checked state */
.perm-matrix-checkbox:disabled:checked + .perm-matrix-btn {
  background: #a7f3d0 !important; /* Light green for locked superadmin */
  border-color: #a7f3d0 !important;
  color: #047857 !important;
}

/* Divider Header Style */
.table-active-soft {
  background-color: #f8fafc !important;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1">Roles & Access Control Matrix</h4>
    <p class="text-muted small mb-0">Configure dynamic V (View), A (Add), E (Edit/Scan) and D (Delete) privileges for employee roles</p>
  </div>
  <button class="btn btn-primary fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#createRoleModal">
    <i class="fas fa-plus-circle me-1"></i> Create New Role
  </button>
</div>

<form action="roles.php" method="POST">
  <input type="hidden" name="save_matrix" value="1">
  
  <div class="card border-0 shadow-sm p-4" style="border-radius: var(--radius-lg);">
    <div class="table-responsive">
      <table class="table table-bordered align-middle text-center mb-0" style="border-collapse: separate; border-spacing: 0; min-width: 800px;">
        <thead>
          <!-- Top Row: Role Badges -->
          <tr class="table-light">
            <th rowspan="2" class="text-start align-middle bg-light" style="min-width: 250px; border-bottom: 2px solid #cbd5e1;">Module / Function</th>
            <?php foreach ($roles as $role): 
              $badgeClass = 'bg-primary';
              if ($role['role_key'] === 'employee') $badgeClass = 'bg-success';
              elseif ($role['role_key'] === 'super_admin') $badgeClass = 'bg-dark';
              elseif ($role['role_key'] === 'admin') $badgeClass = 'bg-primary';
            ?>
              <th colspan="4" class="text-center py-2.5" style="border-bottom: 2px solid #cbd5e1;">
                <span class="badge <?= $badgeClass ?> px-3 py-1.5 fs-7 shadow-sm">
                  <?= htmlspecialchars($role['role_name']) ?>
                  <?php if ($role['role_key'] === 'super_admin'): ?>
                    <i class="fas fa-lock ms-1"></i>
                  <?php endif; ?>
                </span>
              </th>
            <?php endforeach; ?>
          </tr>
          <!-- Second Row: V, A, E, D Headers -->
          <tr class="table-light text-muted font-monospace small">
            <?php foreach ($roles as $role): ?>
              <th style="width: 50px;">V</th>
              <th style="width: 50px;">A</th>
              <th style="width: 50px;">E</th>
              <th style="width: 50px;">D</th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($matrixModules as $categoryName => $modules): ?>
            <!-- Category Divider -->
            <tr class="table-active-soft">
              <td colspan="<?= 1 + (count($roles) * 4) ?>" class="text-start fw-bold text-uppercase small text-secondary tracking-wider py-2.5 ps-3" style="background-color: #f1f5f9;">
                <i class="fas fa-folder me-1 text-muted"></i> <?= $categoryName ?>
              </td>
            </tr>
            <?php foreach ($modules as $moduleName => $perms): ?>
              <tr>
                <!-- Module Name -->
                <td class="text-start fw-semibold text-dark ps-4" style="font-size: 0.88rem;">
                  <?= htmlspecialchars($moduleName) ?>
                </td>
                
                <!-- Permissions checkboxes (V, A, E, D) for each role -->
                <?php foreach ($roles as $role): 
                  $isSuper = $role['role_key'] === 'super_admin';
                  $rolePerms = json_decode($role['permissions'] ?? '[]', true) ?: [];
                ?>
                  <!-- V (View) -->
                  <td>
                    <?php if (!empty($perms['v'])): ?>
                      <label class="mb-0">
                        <input type="checkbox" class="perm-matrix-checkbox" 
                          name="matrix_perms[<?= $role['id'] ?>][]" value="<?= $perms['v'] ?>"
                          <?= ($isSuper || in_array($perms['v'], $rolePerms) || in_array('*', $rolePerms)) ? 'checked' : '' ?>
                          <?= $isSuper ? 'disabled' : '' ?>>
                        <span class="perm-matrix-btn" title="View Profile">V</span>
                      </label>
                    <?php endif; ?>
                  </td>
                  <!-- A (Add) -->
                  <td>
                    <?php if (!empty($perms['a'])): ?>
                      <label class="mb-0">
                        <input type="checkbox" class="perm-matrix-checkbox" 
                          name="matrix_perms[<?= $role['id'] ?>][]" value="<?= $perms['a'] ?>"
                          <?= ($isSuper || in_array($perms['a'], $rolePerms) || in_array('*', $rolePerms)) ? 'checked' : '' ?>
                          <?= $isSuper ? 'disabled' : '' ?>>
                        <span class="perm-matrix-btn" title="Add / Create">A</span>
                      </label>
                    <?php endif; ?>
                  </td>
                  <!-- E (Edit) -->
                  <td>
                    <?php if (!empty($perms['e'])): ?>
                      <label class="mb-0">
                        <input type="checkbox" class="perm-matrix-checkbox" 
                          name="matrix_perms[<?= $role['id'] ?>][]" value="<?= $perms['e'] ?>"
                          <?= ($isSuper || in_array($perms['e'], $rolePerms) || in_array('*', $rolePerms)) ? 'checked' : '' ?>
                          <?= $isSuper ? 'disabled' : '' ?>>
                        <span class="perm-matrix-btn" title="Edit / Process">E</span>
                      </label>
                    <?php endif; ?>
                  </td>
                  <!-- D (Delete) -->
                  <td>
                    <?php if (!empty($perms['d'])): ?>
                      <label class="mb-0">
                        <input type="checkbox" class="perm-matrix-checkbox" 
                          name="matrix_perms[<?= $role['id'] ?>][]" value="<?= $perms['d'] ?>"
                          <?= ($isSuper || in_array($perms['d'], $rolePerms) || in_array('*', $rolePerms)) ? 'checked' : '' ?>
                          <?= $isSuper ? 'disabled' : '' ?>>
                        <span class="perm-matrix-btn" title="Delete">D</span>
                      </label>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    
    <div class="mt-4 pt-3 border-top d-flex flex-wrap justify-content-between align-items-center gap-3">
      <span class="text-muted small">
        <i class="fas fa-info-circle me-1 text-primary"></i>
        Legend: <strong>V</strong> = View, <strong>A</strong> = Add/Create, <strong>E</strong> = Edit/Scan, <strong>D</strong> = Delete
      </span>
      <button type="submit" class="btn btn-primary fw-bold px-4 py-2 shadow-sm">
        <i class="fas fa-save me-1"></i> Save Access Control Matrix
      </button>
    </div>
  </div>
</form>

<!-- Modal: Create Role -->
<div class="modal fade" id="createRoleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-lg);">
      <form action="roles.php" method="POST">
        <input type="hidden" name="create_role" value="1">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-bold"><i class="fas fa-user-shield me-2"></i> Create Custom Corporate Role</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-semibold">Role Name</label>
            <input type="text" name="role_name" class="form-control" placeholder="e.g. Field Agent" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Role Key / Code <small class="text-muted fw-normal">(Optional)</small></label>
            <input type="text" name="role_key" class="form-control" placeholder="e.g. field_agent">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Description</label>
            <input type="text" name="description" class="form-control" placeholder="Short description of role responsibilities">
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary fw-bold">Create & Configure</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
