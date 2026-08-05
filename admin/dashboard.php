<?php
$pageTitle = 'Admin Performance Dashboard';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$db = getDB();

// Stat Counters
$totalFiles = $db->query("SELECT COUNT(*) FROM files")->fetchColumn();
$pendingFiles = $db->query("SELECT COUNT(*) FROM files WHERE status = 'pending'")->fetchColumn();
$inProgressFiles = $db->query("SELECT COUNT(*) FROM files WHERE status = 'in_progress'")->fetchColumn();
$completedFiles = $db->query("SELECT COUNT(*) FROM files WHERE status = 'completed'")->fetchColumn();
$totalEmployees = $db->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();

// Fetch online users count (last activity within 5 minutes)
$timeLimit = date('Y-m-d H:i:s', strtotime('-5 minutes'));
$stmtOnlineCount = $db->prepare("SELECT COUNT(*) FROM users WHERE last_activity >= :limit AND status = 'active'");
$stmtOnlineCount->execute(['limit' => $timeLimit]);
$onlineCount = $stmtOnlineCount->fetchColumn();

// Work type distribution
$workTypeStats = $db->query("
    SELECT wt.name, COUNT(f.id) as count 
    FROM work_types wt 
    LEFT JOIN files f ON wt.id = f.work_type_id 
    GROUP BY wt.id
")->fetchAll();

// Recent Files List
$recentFiles = $db->query("
    SELECT f.*, wt.name as work_type_name, ws.stage_name, u.name as assigned_user_name 
    FROM files f 
    LEFT JOIN work_types wt ON f.work_type_id = wt.id 
    LEFT JOIN workflow_stages ws ON f.current_stage_id = ws.id 
    LEFT JOIN users u ON f.current_assigned_user = u.id 
    ORDER BY f.created_at DESC 
    LIMIT 6
")->fetchAll();

// Fetch Employee Leaderboard (completed files count to approximate XP points)
$leaderboard = $db->query("
    SELECT u.id, u.name, r.role_name,
           (SELECT COUNT(*) FROM files f WHERE f.current_assigned_user = u.id AND f.status = 'completed') as completed_count
    FROM users u
    JOIN roles r ON u.role_id = r.id
    WHERE u.status = 'active'
    ORDER BY completed_count DESC
    LIMIT 5
")->fetchAll();
?>

<div class="row g-4 mb-4">
  <!-- Stat Card 1 -->
  <div class="col-md-3">
    <div class="stat-card-premium stat-card-gradient-green">
      <i class="fas fa-folder-open bg-graphic-icon"></i>
      <div class="card-content">
        <div class="card-title">Total Cases</div>
        <div class="card-value"><?= number_format($totalFiles) ?></div>
        <div class="card-desc">Overall intake volume</div>
      </div>
    </div>
  </div>

  <!-- Stat Card 2 -->
  <div class="col-md-3">
    <div class="stat-card-premium stat-card-gradient-red">
      <i class="fas fa-spinner bg-graphic-icon"></i>
      <div class="card-content">
        <div class="card-title">In Progress</div>
        <div class="card-value"><?= number_format($inProgressFiles) ?></div>
        <div class="card-desc">Under active processing</div>
      </div>
    </div>
  </div>

  <!-- Stat Card 3 -->
  <div class="col-md-3">
    <div class="stat-card-premium stat-card-gradient-blue">
      <i class="fas fa-check-circle bg-graphic-icon"></i>
      <div class="card-content">
        <div class="card-title">Completed Cases</div>
        <div class="card-value"><?= number_format($completedFiles) ?></div>
        <div class="card-desc">Fully closed connections</div>
      </div>
    </div>
  </div>

  <!-- Stat Card 4 -->
  <div class="col-md-3">
    <div class="stat-card-premium stat-card-gradient-purple">
      <i class="fas fa-users bg-graphic-icon"></i>
      <div class="card-content">
        <div class="card-title">Active Team</div>
        <div class="card-value"><?= number_format($totalEmployees) ?></div>
        <div class="card-desc text-white" style="font-size: 0.72rem;">
          <i class="fas fa-circle text-success blink-indicator me-1" style="animation: blink 1.5s infinite;"></i><?= number_format($onlineCount) ?> Online now
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Charts & Analytics Row -->
<div class="row g-4 mb-4">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm p-4 h-100" style="border-radius: var(--radius-lg);">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">Work Type Volume Overview</h5>
        <span class="badge bg-light text-dark">Live Metrics</span>
      </div>
      <div id="workTypeChart" style="min-height: 300px;"></div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card border-0 shadow-sm p-4 h-100" style="border-radius: var(--radius-lg);">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">Status Breakdown</h5>
      </div>
      <div id="statusDonutChart" style="min-height: 300px;"></div>
    </div>
  </div>
</div>

<!-- Leaderboard standings row -->
<div class="row g-4 mb-4">
  <div class="col-12">
    <div class="card border-0 shadow-sm p-4" style="border-radius: var(--radius-lg);">
      <h5 class="fw-bold mb-3"><i class="fas fa-crown text-warning me-2"></i> Employee Productivity Leaderboard</h5>
      <div class="row g-3">
        <?php foreach ($leaderboard as $index => $leader): 
          $leaderXp = intval($leader['completed_count']) * 100;
          $trophyClass = match($index) {
              0 => 'text-warning',
              1 => 'text-secondary',
              2 => 'text-danger', // Bronze color approximation
              default => 'text-muted'
          };
        ?>
          <div class="col-lg col-md-4 col-sm-6">
            <div class="p-3 bg-light rounded d-flex align-items-center gap-3 border">
              <span class="fs-4 fw-bold <?= $trophyClass ?>"><i class="fas fa-trophy"></i></span>
              <div>
                <div class="fw-bold text-dark text-truncate small" style="max-width: 140px;" title="<?= htmlspecialchars($leader['name']) ?>"><?= htmlspecialchars($leader['name']) ?></div>
                <small class="text-muted d-block text-truncate" style="font-size: 0.72rem; max-width: 140px;"><?= htmlspecialchars($leader['role_name']) ?></small>
                <span class="badge bg-primary-soft text-primary font-monospace mt-1" style="font-size: 0.68rem;"><?= number_format($leaderXp) ?> XP</span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- Recent Files Activity Table -->
<div class="card border-0 shadow-sm p-4" style="border-radius: var(--radius-lg);">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h5 class="fw-bold mb-1">Recent Active Files</h5>
      <p class="text-muted small mb-0">Files currently progressing through the workflow pipeline</p>
    </div>
    <a href="<?= APP_URL ?>/employee/my-files.php" class="btn btn-outline-primary btn-sm rounded-pill fw-semibold">
      View All Files <i class="fas fa-arrow-right ms-1"></i>
    </a>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>File Code</th>
          <th>Customer</th>
          <th>Work Type</th>
          <th>Current Stage</th>
          <th>Assigned Employee</th>
          <th>Status</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentFiles as $file): ?>
          <tr>
            <td>
              <span class="fw-bold text-primary"><?= htmlspecialchars($file['file_code']) ?></span>
              <br><small class="text-muted"><?= getPriorityBadgeHtml($file['priority']) ?></small>
            </td>
            <td>
              <div class="fw-semibold text-dark"><?= htmlspecialchars($file['customer_name']) ?></div>
              <small class="text-muted"><i class="fas fa-phone me-1"></i> <?= htmlspecialchars($file['customer_mobile']) ?></small>
            </td>
            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($file['work_type_name']) ?></span></td>
            <td>
              <small class="fw-semibold text-secondary"><?= htmlspecialchars($file['stage_name'] ?? 'Intake') ?></small>
            </td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar-circle" style="width: 28px; height: 28px; font-size: 0.75rem;">
                  <?= strtoupper(substr($file['assigned_user_name'] ?? 'Unassigned', 0, 1)) ?>
                </div>
                <small class="fw-semibold text-dark"><?= htmlspecialchars($file['assigned_user_name'] ?? 'Unassigned') ?></small>
              </div>
            </td>
            <td><?= getStatusBadgeHtml($file['status']) ?></td>
            <td class="text-end">
              <a href="<?= APP_URL ?>/modules/file/view.php?id=<?= $file['id'] ?>" class="btn btn-sm btn-light text-primary" title="View Detail">
                <i class="fas fa-eye"></i>
              </a>
              <a href="<?= APP_URL ?>/modules/file/forward.php?id=<?= $file['id'] ?>" class="btn btn-sm btn-primary" title="Forward/Transfer">
                <i class="fas fa-paper-plane me-1"></i> Forward
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ApexCharts Setup Script -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  const textColor = isDark ? '#f8fafc' : '#1e293b';
  const gridColor = isDark ? '#334155' : '#f1f5f9';

  // Bar Chart - Work Types
  const workTypeNames = <?= json_encode(array_column($workTypeStats, 'name')) ?>;
  const workTypeCounts = <?= json_encode(array_map('intval', array_column($workTypeStats, 'count'))) ?>;

  const barOptions = {
    series: [{ name: 'Total Cases', data: workTypeCounts }],
    chart: { type: 'bar', height: 280, toolbar: { show: false } },
    colors: ['#3b82f6'],
    plotOptions: { bar: { borderRadius: 8, columnWidth: '40%' } },
    dataLabels: { enabled: false },
    grid: { borderColor: gridColor },
    xaxis: { 
      categories: workTypeNames,
      labels: { style: { colors: textColor } }
    },
    yaxis: {
      labels: { style: { colors: textColor } }
    },
    tooltip: { theme: isDark ? 'dark' : 'light' }
  };
  new ApexCharts(document.querySelector("#workTypeChart"), barOptions).render();

  // Donut Chart - Status Breakdown
  const statusOptions = {
    series: [<?= $pendingFiles ?>, <?= $inProgressFiles ?>, <?= $completedFiles ?>],
    labels: ['Pending', 'In Progress', 'Completed'],
    colors: ['#f59e0b', '#06b6d4', '#10b981'],
    chart: { type: 'donut', height: 280 },
    legend: { 
      position: 'bottom',
      labels: { colors: textColor }
    },
    tooltip: { theme: isDark ? 'dark' : 'light' }
  };
  new ApexCharts(document.querySelector("#statusDonutChart"), statusOptions).render();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
