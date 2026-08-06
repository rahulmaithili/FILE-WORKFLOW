<?php
$pageTitle = 'Employee Task Dashboard';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$user = getLoggedInUser();
$userId = $user['id'];
$isAdmin = in_array($user['role_key'], ['super_admin', 'admin']);

// Fetch user file metrics
$stmtAssigned = $db->prepare("SELECT COUNT(*) FROM files WHERE current_assigned_user = :uid AND status IN ('pending', 'in_progress')");
$stmtAssigned->execute(['uid' => $userId]);
$assignedCount = $stmtAssigned->fetchColumn();

$stmtCompleted = $db->prepare("SELECT COUNT(*) FROM files WHERE current_assigned_user = :uid AND status = 'completed'");
$stmtCompleted->execute(['uid' => $userId]);
$completedCount = $stmtCompleted->fetchColumn();

// Fetch online users count (last activity within 5 minutes)
$timeLimit = date('Y-m-d H:i:s', strtotime('-5 minutes'));
$stmtOnlineCount = $db->prepare("SELECT COUNT(*) FROM users WHERE last_activity >= :limit AND status = 'active'");
$stmtOnlineCount->execute(['limit' => $timeLimit]);
$onlineCount = $stmtOnlineCount->fetchColumn();

// Compute Gamification Metrics (XP Ratings)
$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
if ($driver === 'mysql') {
    $stmtOverdue = $db->prepare("
        SELECT COUNT(*) 
        FROM files f
        LEFT JOIN workflow_stages ws ON f.current_stage_id = ws.id
        WHERE f.current_assigned_user = :uid 
          AND f.status NOT IN ('completed', 'rejected')
          AND ( TIMESTAMPDIFF(HOUR, f.updated_at, NOW()) > ws.sla_hours )
    ");
} else {
    $stmtOverdue = $db->prepare("
        SELECT COUNT(*) 
        FROM files f
        LEFT JOIN workflow_stages ws ON f.current_stage_id = ws.id
        WHERE f.current_assigned_user = :uid 
          AND f.status NOT IN ('completed', 'rejected')
          AND ( (julianday('now') - julianday(f.updated_at)) * 24.0 > ws.sla_hours )
    ");
}
$stmtOverdue->execute(['uid' => $userId]);
$overdueCount = intval($stmtOverdue->fetchColumn());

$xp = ($completedCount * 100) - ($overdueCount * 25);
if ($xp < 0) $xp = 0;

$tier = 'Bronze Agent';
$tierColor = 'stat-card-gradient-orange';
$tierIcon = 'fa-medal';
if ($xp >= 1000) {
    $tier = 'Platinum Champion';
    $tierColor = 'stat-card-gradient-purple';
    $tierIcon = 'fa-trophy';
} elseif ($xp >= 500) {
    $tier = 'Gold Expert';
    $tierColor = 'stat-card-gradient-blue';
    $tierIcon = 'fa-award';
} elseif ($xp >= 200) {
    $tier = 'Silver Specialist';
    $tierColor = 'stat-card-gradient-green';
    $tierIcon = 'fa-crown';
}

// Fetch files assigned to logged-in user (all statuses)
$stmtFiles = $db->prepare("
    SELECT f.*, wt.name as work_type_name, ws.stage_name 
    FROM files f 
    LEFT JOIN work_types wt ON f.work_type_id = wt.id 
    LEFT JOIN workflow_stages ws ON f.current_stage_id = ws.id 
    WHERE f.current_assigned_user = :uid
    ORDER BY f.updated_at DESC
");
$stmtFiles->execute(['uid' => $userId]);
$myFiles = $stmtFiles->fetchAll();
?>

<div class="row g-4 mb-4">
  <!-- Card 1 -->
  <div class="col-md-6 col-lg-4">
    <div class="stat-card-premium stat-card-gradient-blue">
      <i class="fas fa-tasks bg-graphic-icon"></i>
      <div class="card-content">
        <div class="card-title">Assigned Pending Tasks</div>
        <div class="card-value"><?= number_format($assignedCount) ?></div>
        <div class="card-desc">Waiting for your processing action</div>
      </div>
    </div>
  </div>

  <!-- Card 2 -->
  <div class="col-md-6 col-lg-4">
    <div class="stat-card-premium stat-card-gradient-green">
      <i class="fas fa-check-double bg-graphic-icon"></i>
      <div class="card-content">
        <div class="card-title">Completed Tasks</div>
        <div class="card-value"><?= number_format($completedCount) ?></div>
        <div class="card-desc">Your successfully closed cases</div>
      </div>
    </div>
  </div>

  <!-- Card 3: Gamified Rewards Badge -->
  <div class="col-md-6 col-lg-4">
    <div class="stat-card-premium <?= $tierColor ?>">
      <i class="fas <?= $tierIcon ?> bg-graphic-icon"></i>
      <div class="card-content">
        <div class="card-title">Performance Rating (XP)</div>
        <div class="card-value"><?= number_format($xp) ?> <span style="font-size: 0.95rem; font-weight: 500;">XP</span></div>
        <div class="card-desc">Rank Tier: <strong><?= $tier ?></strong></div>
      </div>
    </div>
  </div>
</div>

<!-- Chevron Pipeline Status Filter Tabs (Matching User Screenshot) -->
<div class="mb-4 overflow-auto">
  <div class="chevron-nav py-2 d-flex flex-nowrap" id="statusFilterNav" style="min-width: 750px;">
    <a class="chevron-nav-item chevron-all active" data-status="all">All (<?= count($myFiles) ?>)</a>
    <a class="chevron-nav-item chevron-draft" data-status="pending">Pending (<?= count(array_filter($myFiles, fn($f) => $f['status'] === 'pending')) ?>)</a>
    <a class="chevron-nav-item chevron-available" data-status="in_progress">In Progress (<?= count(array_filter($myFiles, fn($f) => $f['status'] === 'in_progress')) ?>)</a>
    <a class="chevron-nav-item chevron-sold" data-status="completed">Completed (<?= count(array_filter($myFiles, fn($f) => $f['status'] === 'completed')) ?>)</a>
    <a class="chevron-nav-item chevron-withdrawn" data-status="rejected">Rejected (<?= count(array_filter($myFiles, fn($f) => $f['status'] === 'rejected')) ?>)</a>
  </div>
</div>

<div class="card border-0 shadow-sm p-4" style="border-radius: var(--radius-lg);">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h5 class="fw-bold mb-1">My Assigned Workflow Files</h5>
      <p class="text-muted small mb-0">Files waiting for your action to process and forward to the next stage</p>
    </div>
    <?php if (hasPermission('create_file')): ?>
      <a href="create-file.php" class="btn btn-primary btn-sm rounded-pill fw-semibold">
        <i class="fas fa-plus-circle me-1"></i> Create New File
      </a>
    <?php endif; ?>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>File Code</th>
          <th>Customer Details</th>
          <th>Work Type</th>
          <th>Current Workflow Stage</th>
          <th>Status</th>
          <th class="text-end">Forward & Actions</th>
        </tr>
      </thead>
      <tbody id="filesTableBody">
        <?php if (empty($myFiles)): ?>
          <tr>
            <td colspan="6" class="text-center py-5 text-muted">
              <i class="fas fa-check-circle fa-3x mb-3 text-success opacity-50"></i>
              <h6>No files assigned to you!</h6>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($myFiles as $f): ?>
            <tr data-file-status="<?= htmlspecialchars($f['status']) ?>">
              <td>
                <a href="<?= APP_URL ?>/modules/file/view.php?id=<?= $f['id'] ?>" class="fw-bold text-primary text-decoration-none">
                  <?= htmlspecialchars($f['file_code']) ?>
                </a>
                <br><small><?= getPriorityBadgeHtml($f['priority']) ?></small>
              </td>
              <td>
                <div class="fw-bold text-dark"><?= htmlspecialchars($f['customer_name']) ?></div>
                <small class="text-muted"><i class="fas fa-phone me-1"></i> <?= htmlspecialchars($f['customer_mobile']) ?></small>
              </td>
              <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($f['work_type_name']) ?></span></td>
              <td><small class="fw-semibold text-secondary"><i class="fas fa-layer-group me-1 text-primary"></i> <?= htmlspecialchars($f['stage_name'] ?? 'Intake') ?></small></td>
              <td><?= getStatusBadgeHtml($f['status']) ?></td>
              <td class="text-end">
                <a href="<?= APP_URL ?>/modules/file/view.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-light text-primary me-1" title="View Case">
                  <i class="fas fa-eye"></i> View
                </a>
                <?php if (in_array($f['status'], ['pending', 'in_progress'])): ?>
                  <a href="<?= APP_URL ?>/modules/file/forward.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-primary fw-semibold" title="Forward File">
                    <i class="fas fa-paper-plane me-1"></i> Forward Step
                  </a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        <tr id="noFilesMessage" class="d-none">
          <td colspan="6" class="text-center py-5 text-muted">
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
