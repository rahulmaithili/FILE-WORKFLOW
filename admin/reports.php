<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requireAdmin();

$db = getDB();

// Handle Export to CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=FMS_Performance_Report_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    
    // Header Row
    fputcsv($output, ['File Code', 'Customer Name', 'Mobile', 'Work Type Pipeline', 'Current Stage', 'Assigned User', 'Branch Office', 'Priority', 'Status', 'Created Date']);
    
    $search = sanitize($_GET['search'] ?? '');
    $branchFilter = intval($_GET['branch_id'] ?? 0);
    $wtFilter = intval($_GET['work_type_id'] ?? 0);
    $statusFilter = sanitize($_GET['status'] ?? '');
    
    $query = "
        SELECT f.*, wt.name as work_type_name, ws.stage_name, u.name as assigned_user_name, b.branch_name 
        FROM files f 
        LEFT JOIN work_types wt ON f.work_type_id = wt.id 
        LEFT JOIN workflow_stages ws ON f.current_stage_id = ws.id 
        LEFT JOIN users u ON f.current_assigned_user = u.id 
        LEFT JOIN branches b ON f.branch_id = b.id
        WHERE 1=1
    ";
    $params = [];
    if (!empty($search)) {
        $query .= " AND (f.file_code LIKE :search OR f.customer_name LIKE :search)";
        $params['search'] = "%$search%";
    }
    if ($branchFilter > 0) {
        $query .= " AND f.branch_id = :branch";
        $params['branch'] = $branchFilter;
    }
    if ($wtFilter > 0) {
        $query .= " AND f.work_type_id = :wt";
        $params['wt'] = $wtFilter;
    }
    if (!empty($statusFilter)) {
        $query .= " AND f.status = :status";
        $params['status'] = $statusFilter;
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    
    foreach ($rows as $r) {
        fputcsv($output, [
            $r['file_code'],
            $r['customer_name'],
            $r['customer_mobile'],
            $r['work_type_name'],
            $r['stage_name'] ?? 'Intake',
            $r['assigned_user_name'] ?? 'Unassigned',
            $r['branch_name'] ?? 'HQ',
            ucfirst($r['priority']),
            ucfirst($r['status']),
            $r['created_at']
        ]);
    }
    fclose($output);
    exit;
}

$pageTitle = 'Enterprise Performance Reports';
require_once __DIR__ . '/../includes/header.php';

// Fetch Filters datasets
$branches = $db->query("SELECT * FROM branches ORDER BY branch_name ASC")->fetchAll();
$workTypes = $db->query("SELECT * FROM work_types ORDER BY name ASC")->fetchAll();

// Active Filters
$search = sanitize($_GET['search'] ?? '');
$branchFilter = intval($_GET['branch_id'] ?? 0);
$wtFilter = intval($_GET['work_type_id'] ?? 0);
$statusFilter = sanitize($_GET['status'] ?? '');

// Filtered Files list
$query = "
    SELECT f.*, wt.name as work_type_name, ws.stage_name, u.name as assigned_user_name, b.branch_name 
    FROM files f 
    LEFT JOIN work_types wt ON f.work_type_id = wt.id 
    LEFT JOIN workflow_stages ws ON f.current_stage_id = ws.id 
    LEFT JOIN users u ON f.current_assigned_user = u.id 
    LEFT JOIN branches b ON f.branch_id = b.id
    WHERE 1=1
";
$params = [];
if (!empty($search)) {
    $query .= " AND (f.file_code LIKE :search OR f.customer_name LIKE :search OR f.customer_mobile LIKE :search)";
    $params['search'] = "%$search%";
}
if ($branchFilter > 0) {
    $query .= " AND f.branch_id = :branch";
    $params['branch'] = $branchFilter;
}
if ($wtFilter > 0) {
    $query .= " AND f.work_type_id = :wt";
    $params['wt'] = $wtFilter;
}
if (!empty($statusFilter)) {
    $query .= " AND f.status = :status";
    $params['status'] = $statusFilter;
}
$query .= " ORDER BY f.id DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$files = $stmt->fetchAll();

// Charts metrics calculation
// 1. Files by Status
$statusCounts = ['pending' => 0, 'in_progress' => 0, 'on_hold' => 0, 'completed' => 0, 'rejected' => 0];
foreach ($files as $file) {
    if (isset($statusCounts[$file['status']])) {
        $statusCounts[$file['status']]++;
    }
}

// 2. Files by Branch Office
$branchChartData = [];
foreach ($branches as $br) {
    $branchChartData[$br['branch_name']] = 0;
}
$branchChartData['Headquarters'] = 0;

foreach ($files as $file) {
    $bName = $file['branch_name'] ?: 'Headquarters';
    if (!isset($branchChartData[$bName])) {
        $branchChartData[$bName] = 0;
    }
    $branchChartData[$bName]++;
}
?>

<!-- Action Bar -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div>
    <h4 class="fw-bold mb-1"><i class="fas fa-chart-line text-primary me-2"></i> Reports & Analytical Center</h4>
    <p class="text-muted small mb-0">Monitor processing volumes, branch performance comparisons, and export system audit data sheet</p>
  </div>
  <div class="d-flex gap-2">
    <a href="reports.php?export=csv&<?= htmlspecialchars($_SERVER['QUERY_STRING'] ?? '') ?>" class="btn btn-success fw-bold shadow-sm">
      <i class="fas fa-file-csv me-1"></i> Export Filtered CSV
    </a>
  </div>
</div>

<!-- Filters Panel -->
<div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: var(--radius-lg);">
  <form action="reports.php" method="GET" class="row g-3 align-items-end">
    <div class="col-md-3">
      <label class="form-label small fw-bold">Search Text</label>
      <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="File Code, Name, or Mobile...">
    </div>
    <div class="col-md-3">
      <label class="form-label small fw-bold">Branch Office</label>
      <select name="branch_id" class="form-select">
        <option value="0">All Branches</option>
        <?php foreach ($branches as $br): ?>
          <option value="<?= $br['id'] ?>" <?= $branchFilter == $br['id'] ? 'selected' : '' ?>><?= htmlspecialchars($br['branch_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small fw-bold">Work Pipeline</label>
      <select name="work_type_id" class="form-select">
        <option value="0">All Pipelines</option>
        <?php foreach ($workTypes as $wt): ?>
          <option value="<?= $wt['id'] ?>" <?= $wtFilter == $wt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($wt['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label small fw-bold">Status</label>
      <select name="status" class="form-select">
        <option value="">All Statuses</option>
        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
        <option value="on_hold" <?= $statusFilter === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
        <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
        <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
      </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
      <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="fas fa-filter me-1"></i> Filter</button>
      <a href="reports.php" class="btn btn-light border w-100 text-dark fw-bold">Reset</a>
    </div>
  </form>
</div>

<!-- Metrics Cards & Charts Section -->
<div class="row g-4 mb-4">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm p-4 h-100" style="border-radius: var(--radius-lg);">
      <h6 class="fw-bold text-dark mb-3"><i class="fas fa-building text-primary me-2"></i> Case Share by Branch Locations</h6>
      <div id="branchPerformanceChart" style="min-height: 280px;"></div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm p-4 h-100" style="border-radius: var(--radius-lg);">
      <h6 class="fw-bold text-dark mb-3"><i class="fas fa-circle-notch text-info me-2"></i> Processing Status distribution</h6>
      <div id="statusDistributionChart" style="min-height: 280px;"></div>
    </div>
  </div>
</div>

<!-- Filtered Listing Results Grid -->
<div class="card border-0 shadow-sm p-4" style="border-radius: var(--radius-lg);">
  <h6 class="fw-bold text-dark mb-3"><i class="fas fa-list text-muted me-2"></i> Filtered Audit Logs (<?= count($files) ?> Records)</h6>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>File Code</th>
          <th>Customer Info</th>
          <th>Work Pipeline</th>
          <th>Branch</th>
          <th>Assigned Agent</th>
          <th>Current Step</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($files)): ?>
          <tr>
            <td colspan="7" class="text-center py-4 text-muted">No case logs found matching criteria parameters.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($files as $file): ?>
            <tr>
              <td>
                <a href="<?= APP_URL ?>/modules/file/view.php?id=<?= $file['id'] ?>" class="fw-bold text-primary text-decoration-none">
                  <?= htmlspecialchars($file['file_code']) ?>
                </a>
              </td>
              <td>
                <div class="fw-bold text-dark"><?= htmlspecialchars($file['customer_name']) ?></div>
                <small class="text-muted"><i class="fas fa-phone me-1 small"></i> <?= htmlspecialchars($file['customer_mobile']) ?></small>
              </td>
              <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($file['work_type_name']) ?></span></td>
              <td><span class="small text-secondary"><i class="fas fa-building me-1"></i> <?= htmlspecialchars($file['branch_name'] ?? 'HQ') ?></span></td>
              <td><small class="fw-semibold"><?= htmlspecialchars($file['assigned_user_name'] ?? 'Unassigned') ?></small></td>
              <td><small class="text-muted"><?= htmlspecialchars($file['stage_name'] ?? 'Intake') ?></small></td>
              <td><?= getStatusBadgeHtml($file['status']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Include ApexCharts library CDN dynamically -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  const textColor = isDark ? '#f8fafc' : '#1e293b';
  const gridColor = isDark ? '#334155' : '#f1f5f9';

  // 1. Branch Performance Chart
  const branchLabels = <?= json_encode(array_keys($branchChartData)) ?>;
  const branchCounts = <?= json_encode(array_values($branchChartData)) ?>;
  
  const branchOptions = {
    series: [{
      name: 'Active Cases',
      data: branchCounts
    }],
    chart: {
      type: 'bar',
      height: 280,
      toolbar: { show: false }
    },
    colors: ['#3b82f6'],
    plotOptions: {
      bar: {
        borderRadius: 6,
        horizontal: false,
        columnWidth: '45%'
      }
    },
    dataLabels: { enabled: false },
    grid: { borderColor: gridColor },
    xaxis: {
      categories: branchLabels,
      labels: { style: { colors: textColor } }
    },
    yaxis: {
      labels: { style: { colors: textColor } }
    },
    tooltip: { theme: isDark ? 'dark' : 'light' }
  };
  new ApexCharts(document.querySelector("#branchPerformanceChart"), branchOptions).render();

  // 2. Status Distribution Chart
  const statusLabels = ['Pending', 'In Progress', 'On Hold', 'Completed', 'Rejected'];
  const statusValues = [
    <?= intval($statusCounts['pending']) ?>,
    <?= intval($statusCounts['in_progress']) ?>,
    <?= intval($statusCounts['on_hold']) ?>,
    <?= intval($statusCounts['completed']) ?>,
    <?= intval($statusCounts['rejected']) ?>
  ];

  const statusOptions = {
    series: statusValues,
    chart: {
      type: 'donut',
      height: 280
    },
    labels: statusLabels,
    colors: ['#fbbf24', '#3b82f6', '#94a3b8', '#10b981', '#ef4444'],
    legend: {
      position: 'bottom',
      labels: { colors: textColor }
    },
    dataLabels: { enabled: true },
    tooltip: { theme: isDark ? 'dark' : 'light' }
  };
  new ApexCharts(document.querySelector("#statusDistributionChart"), statusOptions).render();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
