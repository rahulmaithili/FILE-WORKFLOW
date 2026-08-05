<?php
$pageTitle = 'Role & Permissions Matrix';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$db = getDB();
$message = '';

// Handle Create / Update Role
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roleName = sanitize($_POST['role_name'] ?? '');
    $roleKey = sanitize($_POST['role_key'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $permissions = $_POST['permissions'] ?? [];
    
    // Convert permissions array to JSON
    $permissionsJson = json_encode(array_values($permissions));
    
    if (isset($_POST['role_id']) && !empty($_POST['role_id'])) {
        // Update
        $stmt = $db->prepare("UPDATE roles SET role_name = :name, description = :desc, permissions = :perms WHERE id = :id");
        $stmt->execute([
            'name' => $roleName,
            'desc' => $description,
            'perms' => $permissionsJson,
            'id' => intval($_POST['role_id'])
        ]);
        setFlashMessage('success', 'Role permissions updated successfully.');
    } else {
        // Create
        $stmt = $db->prepare("INSERT INTO roles (role_name, role_key, description, permissions) VALUES (:name, :key, :desc, :perms)");
        $stmt->execute([
            'name' => $roleName,
            'key' => strtolower(str_replace(' ', '_', $roleKey ?: $roleName)),
            'desc' => $description,
            'perms' => $permissionsJson
        ]);
        setFlashMessage('success', 'New role created successfully.');
    }
    header("Location: roles.php");
    exit;
}

$roles = $db->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();

$availablePermissions = [
    'create_file' => 'Create New File / Customer Intake',
    'edit_file' => 'Edit File Details',
    'assign_file' => 'Assign / Re-assign Files',
    'forward_file' => 'Forward File to Next Stage',
    'view_all_files' => 'View All Company Files',
    'view_team_files' => 'View Team Files Only',
    'view_assigned_files' => 'View Self Assigned Files Only',
    'manage_users' => 'Manage Employees & User Roles',
    'view_reports' => 'Access Analytics & SLA Reports',
    'upload_doc' => 'Upload & Scan Documents',
    'call_customer' => 'Access Customer Click-To-Call',
    'whatsapp_customer' => 'Dispatch WhatsApp Template Notifications'
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1">Dynamic Role & Permissions Builder</h4>
    <p class="text-muted small mb-0">Control button visibility and module access for every user role in the company</p>
  </div>
  <button class="btn btn-primary fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#createRoleModal">
    <i class="fas fa-plus-circle me-1"></i> Create New Role
  </button>
</div>

<div class="row g-4">
  <?php foreach ($roles as $role): 
    $perms = json_decode($role['permissions'] ?? '[]', true) ?: [];
    $isSuper = $role['role_key'] === 'super_admin';
  ?>
    <div class="col-md-6 col-lg-4">
      <div class="card border-0 shadow-sm h-100 p-3" style="border-radius: var(--radius-md);">
        <div class="card-body d-flex flex-column justify-content-between">
          <div>
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <h5 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($role['role_name']) ?></h5>
                <span class="badge bg-light text-primary border"><?= htmlspecialchars($role['role_key']) ?></span>
              </div>
              <span class="avatar-circle bg-primary-soft text-primary" style="width: 36px; height: 36px;">
                <i class="fas fa-user-shield"></i>
              </span>
            </div>
            <p class="text-muted small mb-3"><?= htmlspecialchars($role['description'] ?: 'Custom corporate access role.') ?></p>
            
            <h6 class="fw-bold small text-uppercase text-secondary mb-2">Assigned Permissions:</h6>
            <div class="d-flex flex-wrap gap-1 mb-3">
              <?php if ($isSuper || in_array('*', $perms)): ?>
                <span class="badge bg-danger text-white">Full Super Access (*)</span>
              <?php else: ?>
                <?php foreach ($perms as $pKey): ?>
                  <span class="badge bg-light text-dark border me-1 mb-1">
                    <i class="fas fa-check text-success me-1"></i> <?= htmlspecialchars($availablePermissions[$pKey] ?? $pKey) ?>
                  </span>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <div class="pt-3 border-top d-flex justify-content-end">
            <button class="btn btn-sm btn-outline-primary" onclick="editRole(<?= htmlspecialchars(json_encode($role)) ?>)">
              <i class="fas fa-edit me-1"></i> Edit Permissions
            </button>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Modal: Create / Edit Role -->
<div class="modal fade" id="createRoleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-lg);">
      <form action="roles.php" method="POST" id="roleForm">
        <input type="hidden" name="role_id" id="role_id">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-bold" id="roleModalTitle"><i class="fas fa-user-shield me-2"></i> Role Setup</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Role Name</label>
              <input type="text" name="role_name" id="role_name" class="form-control" placeholder="e.g. Site Inspector" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Role Key / Identifier</label>
              <input type="text" name="role_key" id="role_key" class="form-control" placeholder="e.g. site_inspector">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Description</label>
              <input type="text" name="description" id="description" class="form-control" placeholder="Short description of role responsibility">
            </div>
          </div>

          <h6 class="fw-bold mb-3 text-primary border-bottom pb-2">Select Permission Matrix (Module & Button Access):</h6>
          <div class="row g-3">
            <?php foreach ($availablePermissions as $key => $label): ?>
              <div class="col-md-6">
                <div class="form-check card p-2.5 border shadow-sm">
                  <input class="form-check-input perm-checkbox" type="checkbox" name="permissions[]" value="<?= $key ?>" id="perm_<?= $key ?>">
                  <label class="form-check-label fw-semibold text-dark ms-2" for="perm_<?= $key ?>">
                    <?= htmlspecialchars($label) ?>
                  </label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary fw-bold px-4">Save Role Permissions</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function editRole(role) {
  document.getElementById('role_id').value = role.id;
  document.getElementById('role_name').value = role.role_name;
  document.getElementById('role_key').value = role.role_key;
  document.getElementById('role_key').readOnly = true;
  document.getElementById('description').value = role.description;
  document.getElementById('roleModalTitle').innerText = 'Edit Role: ' + role.role_name;

  // Clear checkboxes
  document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = false);

  // Check saved permissions
  let perms = [];
  try {
    perms = typeof role.permissions === 'string' ? JSON.parse(role.permissions) : role.permissions;
  } catch(e){}

  if (Array.isArray(perms)) {
    perms.forEach(p => {
      const cb = document.getElementById('perm_' + p);
      if (cb) cb.checked = true;
    });
  }

  const modal = new bootstrap.Modal(document.getElementById('createRoleModal'));
  modal.show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
