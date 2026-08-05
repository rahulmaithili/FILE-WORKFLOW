<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requireAdmin();

$db = getDB();

// Handle Purge All action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'purge_all') {
    $db->query("DELETE FROM activity_logs");
    logActivity($currentUser['id'], 'SECURITY_PURGE_ALL', 'Pruned/cleared all system security audit logs registry.');
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'All activity security logs purged successfully.'];
    header("Location: security-audit.php");
    exit;
}

// Handle Bulk Delete selected action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'bulk_delete') {
    $ids = $_POST['log_ids'] ?? [];
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("DELETE FROM activity_logs WHERE id IN ($placeholders)");
        $stmt->execute(array_map('intval', $ids));
        logActivity($currentUser['id'], 'SECURITY_BULK_DELETE', 'Deleted ' . count($ids) . ' selected security log entries.');
        $_SESSION['flash'] = ['type' => 'success', 'message' => count($ids) . ' logs deleted successfully.'];
    }
    header("Location: security-audit.php");
    exit;
}

$pageTitle = 'Security Access & Audit Logs';
require_once __DIR__ . '/../includes/header.php';

// Fetch recent activity logs with joined user names and roles
$logs = $db->query("
    SELECT l.*, u.name as user_name, r.role_name 
    FROM activity_logs l
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN roles r ON u.role_id = r.id
    ORDER BY l.id DESC
    LIMIT 200
")->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div>
    <h4 class="fw-bold mb-1"><i class="fas fa-shield-alt text-danger me-2"></i> Security Access & Audit Console</h4>
    <p class="text-muted small mb-0">Review system events, employee operations, database uploads, and security access points</p>
  </div>
  <div class="d-flex gap-2 align-items-center">
    <!-- Purge All Action -->
    <form action="security-audit.php?action=purge_all" method="POST" class="d-inline m-0">
      <button type="submit" class="btn btn-outline-danger fw-semibold btn-sm shadow-sm" data-confirm="Are you sure you want to purge ALL security activity logs? This action cannot be undone." data-confirm-title="Purge All Security Logs?" data-confirm-btn="Yes, Purge All">
        <i class="fas fa-trash-alt me-1"></i> Clear All Logs
      </button>
    </form>
    
    <!-- Bulk Delete Selected Trigger -->
    <button type="button" id="bulkDeleteBtn" class="btn btn-danger fw-bold btn-sm shadow-sm d-none">
      <i class="fas fa-trash-alt me-1"></i> Delete Selected (<span id="selectedCount">0</span>)
    </button>
  </div>
</div>

<div class="card border-0 shadow-sm p-4" style="border-radius: var(--radius-lg);">
  <h6 class="fw-bold text-dark mb-3"><i class="fas fa-user-shield text-muted me-2"></i> User Activities & Logs Registry</h6>
  
  <form id="bulkDeleteForm" action="security-audit.php?action=bulk_delete" method="POST">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width: 40px;">
              <input type="checkbox" class="form-check-input cursor-pointer" id="selectAllLogs">
            </th>
            <th>Time & Date</th>
            <th>Operator User</th>
            <th>Role</th>
            <th>Event Action</th>
            <th>Details Summary</th>
            <th>IP Address</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
            <tr>
              <td colspan="7" class="text-center py-4 text-muted">No security logs recorded in the system yet.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($logs as $l): 
              $actionBadge = match(true) {
                  str_contains($l['action'], 'DELETE') => 'bg-danger text-white',
                  str_contains($l['action'], 'EDIT') => 'bg-warning text-dark',
                  str_contains($l['action'], 'LOGIN') => 'bg-success text-white',
                  str_contains($l['action'], 'ADD') => 'bg-info text-white',
                  default => 'bg-light text-dark border'
              };
            ?>
              <tr>
                <td>
                  <input type="checkbox" name="log_ids[]" value="<?= $l['id'] ?>" class="form-check-input log-checkbox cursor-pointer">
                </td>
                <td>
                  <span class="small font-monospace text-secondary" title="<?= htmlspecialchars($l['created_at']) ?>">
                    <i class="far fa-clock me-1 text-primary"></i> <?= date('d M Y, h:i:s A', strtotime($l['created_at'])) ?>
                  </span>
                </td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="avatar-circle" style="width: 24px; height: 24px; font-size: 0.65rem;">
                      <?= strtoupper(substr($l['user_name'] ?? 'S', 0, 1)) ?>
                    </div>
                    <strong class="text-dark small"><?= htmlspecialchars($l['user_name'] ?? 'System / Automated') ?></strong>
                  </div>
                </td>
                <td><span class="badge bg-primary-soft text-primary border" style="font-size: 0.72rem;"><?= htmlspecialchars($l['role_name'] ?? 'Core Platform') ?></span></td>
                <td><span class="badge <?= $actionBadge ?> font-monospace" style="font-size: 0.72rem;"><?= htmlspecialchars($l['action']) ?></span></td>
                <td><small class="text-secondary"><?= htmlspecialchars($l['details'] ?: 'No additional details provided.') ?></small></td>
                <td>
                  <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.75rem;">
                    <i class="fas fa-network-wired text-muted me-1"></i> <?= htmlspecialchars($l['ip_address'] ?? 'Unknown') ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const selectAll = document.getElementById('selectAllLogs');
  const checkboxes = document.querySelectorAll('.log-checkbox');
  const bulkBtn = document.getElementById('bulkDeleteBtn');
  const countSpan = document.getElementById('selectedCount');
  const form = document.getElementById('bulkDeleteForm');

  if (!selectAll || !bulkBtn) return;

  function updateBulkBtnState() {
    const checkedCount = document.querySelectorAll('.log-checkbox:checked').length;
    if (checkedCount > 0) {
      bulkBtn.classList.remove('d-none');
      countSpan.textContent = checkedCount;
    } else {
      bulkBtn.classList.add('d-none');
    }
  }

  selectAll.addEventListener('change', () => {
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateBulkBtnState();
  });

  checkboxes.forEach(cb => {
    cb.addEventListener('change', updateBulkBtnState);
  });

  bulkBtn.addEventListener('click', (e) => {
    e.preventDefault();
    const checkedCount = document.querySelectorAll('.log-checkbox:checked').length;
    
    Swal.fire({
      title: 'Delete ' + checkedCount + ' Selected Logs?',
      text: 'Are you sure you want to delete the selected security logs? This action cannot be undone.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef4444',
      cancelButtonColor: '#64748b',
      confirmButtonText: 'Yes, Delete Selected',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        form.submit();
      }
    });
  });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
