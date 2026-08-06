<?php
$pageTitle = 'File Management Directory';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$user = getLoggedInUser();
$userId = $user['id'];
$isAdmin = in_array($user['role_key'], ['super_admin', 'admin', 'manager']);

// Filtering parameters
$search = sanitize($_GET['search'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$workTypeFilter = intval($_GET['work_type_id'] ?? 0);

// Build query dynamically with access protection
$whereClauses = [];
$params = [];

if (!$isAdmin) {
    // Regular employee sees only assigned files or files they created
    $whereClauses[] = "(f.current_assigned_user = :uid OR f.created_by = :uid)";
    $params['uid'] = $userId;
}

if (!empty($search)) {
    $whereClauses[] = "(f.file_code LIKE :search OR f.customer_name LIKE :search OR f.customer_mobile LIKE :search)";
    $params['search'] = "%{$search}%";
}

if (!empty($statusFilter)) {
    $whereClauses[] = "f.status = :status";
    $params['status'] = $statusFilter;
}

if ($workTypeFilter > 0) {
    $whereClauses[] = "f.work_type_id = :wt";
    $params['wt'] = $workTypeFilter;
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$stmt = $db->prepare("
    SELECT f.*, wt.name as work_type_name, ws.stage_name, u.name as assigned_user_name 
    FROM files f 
    LEFT JOIN work_types wt ON f.work_type_id = wt.id 
    LEFT JOIN workflow_stages ws ON f.current_stage_id = ws.id 
    LEFT JOIN users u ON f.current_assigned_user = u.id 
    {$whereSql} 
    ORDER BY f.id DESC
");
$stmt->execute($params);
$files = $stmt->fetchAll();

$workTypes = $db->query("SELECT * FROM work_types ORDER BY name ASC")->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
  <div>
    <h4 class="fw-bold mb-1">Company File Directory</h4>
    <p class="text-muted small mb-0">Search, filter, and access all customer case files in the workflow system</p>
  </div>
  <div class="d-flex gap-2">
    <a href="print-directory.php?<?= htmlspecialchars($_SERVER['QUERY_STRING'] ?? '') ?>" target="_blank" class="btn btn-outline-dark fw-semibold">
      <i class="fas fa-print me-1"></i> Print Directory
    </a>
    <a href="kanban.php" class="btn btn-outline-secondary fw-semibold">
      <i class="fas fa-columns me-1"></i> Kanban View
    </a>
    <?php if (hasPermission('create_file')): ?>
      <a href="create-file.php" class="btn btn-primary fw-semibold shadow-sm">
        <i class="fas fa-plus-circle me-1"></i> Create New File
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- Chevron Pipeline Status Filter Tabs (Matching User Screenshot) -->
<div class="mb-4 overflow-auto">
  <div class="chevron-nav py-2 d-flex flex-nowrap" id="statusFilterNav" style="min-width: 850px;">
    <a class="chevron-nav-item chevron-all active" data-status="all">All (<?= count($files) ?>)</a>
    <a class="chevron-nav-item chevron-draft" data-status="pending">Pending (<?= count(array_filter($files, fn($f) => $f['status'] === 'pending')) ?>)</a>
    <a class="chevron-nav-item chevron-available" data-status="in_progress">In Progress (<?= count(array_filter($files, fn($f) => $f['status'] === 'in_progress')) ?>)</a>
    <a class="chevron-nav-item chevron-sold" data-status="completed">Completed (<?= count(array_filter($files, fn($f) => $f['status'] === 'completed')) ?>)</a>
    <a class="chevron-nav-item chevron-withdrawn" data-status="rejected">Rejected (<?= count(array_filter($files, fn($f) => $f['status'] === 'rejected')) ?>)</a>
  </div>
</div>

<!-- Search & Filter Controls -->
<div class="card border-0 shadow-sm p-3 mb-4" style="border-radius: var(--radius-md);">
  <form action="my-files.php" method="GET" class="row g-2">
    <div class="col-md-5">
      <div class="input-group">
        <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
        <input type="text" name="search" class="form-control bg-light border-start-0" placeholder="Search Code, Name, Mobile..." value="<?= htmlspecialchars($search) ?>">
      </div>
    </div>

    <div class="col-md-4">
      <select name="work_type_id" class="form-select bg-light">
        <option value="">-- All Work Types --</option>
        <?php foreach ($workTypes as $wt): ?>
          <option value="<?= $wt['id'] ?>" <?= $workTypeFilter == $wt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($wt['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-3 d-flex gap-2">
      <button type="submit" class="btn btn-primary w-100 fw-semibold">Filter</button>
      <a href="my-files.php" class="btn btn-light border text-muted" title="Reset Filters"><i class="fas fa-redo"></i></a>
    </div>
  </form>
</div>

<!-- Files Table View -->
<div class="card border-0 shadow-sm p-4" style="border-radius: var(--radius-lg);">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>File Code</th>
          <th>Customer Details</th>
          <th>Work Type</th>
          <th>Current Stage</th>
          <th>Assigned Employee</th>
          <th>Status</th>
          <th class="text-end">Quick Action Buttons</th>
        </tr>
      </thead>
      <tbody id="filesTableBody">
        <?php if (empty($files)): ?>
          <tr>
            <td colspan="7" class="text-center py-5 text-muted">
              <i class="fas fa-folder-open fa-3x mb-3 text-secondary opacity-50"></i>
              <h6>No files matching your search query.</h6>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($files as $file): ?>
            <tr data-file-status="<?= htmlspecialchars($file['status']) ?>">
              <td>
                <a href="<?= APP_URL ?>/modules/file/view.php?id=<?= $file['id'] ?>" class="fw-bold text-primary text-decoration-none">
                  <?= htmlspecialchars($file['file_code']) ?>
                </a>
                <br><small><?= getPriorityBadgeHtml($file['priority']) ?></small>
              </td>
              <td>
                <div class="fw-bold text-dark"><?= htmlspecialchars($file['customer_name']) ?></div>
                <small class="text-muted"><i class="fas fa-phone me-1"></i> <?= htmlspecialchars($file['customer_mobile']) ?></small>
              </td>
              <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($file['work_type_name']) ?></span></td>
              <td>
                <small class="fw-semibold text-secondary"><i class="fas fa-layer-group me-1 text-primary"></i> <?= htmlspecialchars($file['stage_name'] ?? 'Intake') ?></small>
              </td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="avatar-circle" style="width: 26px; height: 26px; font-size: 0.72rem;">
                    <?= strtoupper(substr($file['assigned_user_name'] ?? 'U', 0, 1)) ?>
                  </div>
                  <small class="fw-semibold text-dark"><?= htmlspecialchars($file['assigned_user_name'] ?? 'Unassigned') ?></small>
                </div>
              </td>
              <td><?= getStatusBadgeHtml($file['status']) ?></td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <a href="<?= APP_URL ?>/modules/file/view.php?id=<?= $file['id'] ?>" class="btn btn-light border text-primary" title="View Case File">
                    <i class="fas fa-eye"></i>
                  </a>
                  <a href="<?= APP_URL ?>/modules/file/forward.php?id=<?= $file['id'] ?>" class="btn btn-primary" title="Forward to Next Employee">
                    <i class="fas fa-paper-plane"></i> Forward
                  </a>
                  
                  <?php if (hasPermission('manage_users')): ?>
                    <a href="<?= APP_URL ?>/modules/file/edit.php?id=<?= $file['id'] ?>" class="btn btn-light border text-warning" title="Edit Case File">
                      <i class="fas fa-edit"></i>
                    </a>
                  <?php endif; ?>
                  <?php if (hasPermission('delete_file')): ?>
                    <a href="<?= APP_URL ?>/modules/file/delete.php?id=<?= $file['id'] ?>" class="btn btn-light border text-danger" title="Delete Case File" data-confirm="Are you sure you want to delete this case file permanently? All uploaded documents will be deleted." data-confirm-title="Delete Case File?" data-confirm-btn="Yes, Delete">
                      <i class="fas fa-trash-alt"></i>
                    </a>
                  <?php endif; ?>

                  <a href="<?= APP_URL ?>/modules/whatsapp/send.php?file_id=<?= $file['id'] ?>" class="btn btn-light border text-success" title="Send WhatsApp">
                    <i class="fab fa-whatsapp"></i>
                  </a>
                  <a href="tel:<?= htmlspecialchars($file['customer_mobile']) ?>" class="btn btn-light border text-info" title="Call Customer">
                    <i class="fas fa-phone"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        <tr id="noFilesMessage" class="d-none">
          <td colspan="7" class="text-center py-5 text-muted">
            <i class="fas fa-folder-open fa-3x mb-3 text-secondary opacity-50"></i>
            <h6>No files found in this category.</h6>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<script>
document.querySelectorAll('#statusFilterNav .chevron-nav-item').forEach(item => {
    item.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Remove active class from all tabs
        document.querySelectorAll('#statusFilterNav .chevron-nav-item').forEach(t => t.classList.remove('active'));
        // Add active class to clicked tab
        this.classList.add('active');
        
        const filterStatus = this.getAttribute('data-status');
        const rows = document.querySelectorAll('#filesTableBody tr:not(#noFilesMessage)');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const rowStatus = row.getAttribute('data-file-status');
            if (filterStatus === 'all' || rowStatus === filterStatus) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        const noFilesMsg = document.getElementById('noFilesMessage');
        if (visibleCount === 0) {
            noFilesMsg.classList.remove('d-none');
        } else {
            noFilesMsg.classList.add('d-none');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
