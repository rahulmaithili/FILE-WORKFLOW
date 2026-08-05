<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$db = getDB();
$user = getLoggedInUser();

// Fetch company details
$companyInfo = $db->query("SELECT * FROM company_settings LIMIT 1")->fetch() ?: [
    'company_name' => 'Office File Management CRM',
    'company_logo' => 'default-logo.png',
    'company_email' => 'contact@office.com',
    'company_phone' => '9876543210'
];

// Replicate filtering query from my-files.php
$search = sanitize($_GET['search'] ?? '');
$workTypeFilter = intval($_GET['work_type_id'] ?? 0);
$statusFilter = sanitize($_GET['status'] ?? '');

$query = "
    SELECT f.*, wt.name as work_type_name, ws.stage_name, u.name as assigned_user_name 
    FROM files f 
    LEFT JOIN work_types wt ON f.work_type_id = wt.id 
    LEFT JOIN workflow_stages ws ON f.current_stage_id = ws.id 
    LEFT JOIN users u ON f.current_assigned_user = u.id 
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query .= " AND (f.file_code LIKE :search OR f.customer_name LIKE :search OR f.customer_mobile LIKE :search)";
    $params['search'] = "%$search%";
}
if ($workTypeFilter > 0) {
    $query .= " AND f.work_type_id = :wt";
    $params['wt'] = $workTypeFilter;
}
if (!empty($statusFilter)) {
    $query .= " AND f.status = :status";
    $params['status'] = $statusFilter;
}

$query .= " ORDER BY f.id DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$files = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Print File Directory - <?= htmlspecialchars($companyInfo['company_name']) ?></title>
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- FontAwesome 6 -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    body {
      background: white;
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: #1e293b;
      padding: 1.5rem;
    }
    .print-header {
      border-bottom: 3px double #cbd5e1;
      padding-bottom: 1.5rem;
      margin-bottom: 2rem;
    }
    .company-logo-print {
      max-height: 70px;
      max-width: 150px;
      object-fit: contain;
    }
    table {
      font-size: 0.85rem;
    }
    @media print {
      body {
        padding: 0;
      }
      .no-print {
        display: none !important;
      }
    }
  </style>
</head>
<body onload="window.print()">

<div class="container-fluid">
  
  <!-- Print Control Panel (No Print) -->
  <div class="no-print bg-light p-3 rounded mb-4 d-flex justify-content-between align-items-center border">
    <div>
      <h6 class="fw-bold mb-1">Print Preview Report</h6>
      <small class="text-muted">Branded official PDF report layout for corporate files</small>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-secondary fw-semibold" onclick="window.close()">Close Window</button>
      <button class="btn btn-primary fw-bold" onclick="window.print()"><i class="fas fa-print me-1"></i> Print Report</button>
    </div>
  </div>

  <!-- Official Letterhead Header -->
  <div class="print-header d-flex justify-content-between align-items-center">
    <div>
      <div class="d-flex align-items-center gap-3">
        <?php if (!empty($companyInfo['company_logo']) && $companyInfo['company_logo'] !== 'default-logo.png' && file_exists(__DIR__ . '/../uploads/' . $companyInfo['company_logo'])): ?>
          <img src="<?= APP_URL ?>/uploads/<?= htmlspecialchars($companyInfo['company_logo']) ?>" class="company-logo-print">
        <?php else: ?>
          <div class="bg-primary text-white d-flex align-items-center justify-content-center rounded" style="width: 50px; height: 50px;">
            <i class="fas fa-building fa-2x"></i>
          </div>
        <?php endif; ?>
        <div>
          <h3 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($companyInfo['company_name']) ?></h3>
          <small class="text-muted">Official Case File Directory Report</small>
        </div>
      </div>
    </div>
    <div class="text-end small text-secondary">
      <strong>Generated Date:</strong> <?= date('d M Y, h:i A') ?><br>
      <strong>Total Files:</strong> <?= count($files) ?> Records<br>
      <strong>Operator:</strong> <?= htmlspecialchars($user['name']) ?>
    </div>
  </div>

  <!-- Filter details if applied -->
  <?php if (!empty($search) || $workTypeFilter > 0 || !empty($statusFilter)): ?>
    <div class="alert alert-light border py-2 px-3 small mb-3">
      <strong>Report Filters:</strong> 
      <?php if (!empty($search)) echo "Search: '" . htmlspecialchars($search) . "' | "; ?>
      <?php if ($workTypeFilter > 0) echo "Work Type Filter Active | "; ?>
      <?php if (!empty($statusFilter)) echo "Status: " . ucfirst($statusFilter) . " | "; ?>
    </div>
  <?php endif; ?>

  <!-- Report Data Table -->
  <table class="table table-bordered table-striped align-middle">
    <thead class="table-dark">
      <tr>
        <th style="width: 15%;">File Code</th>
        <th style="width: 25%;">Customer Details</th>
        <th style="width: 20%;">Work Type Pipeline</th>
        <th style="width: 15%;">Current Stage</th>
        <th style="width: 15%;">Assigned To</th>
        <th style="width: 10%;">Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($files)): ?>
        <tr>
          <td colspan="6" class="text-center py-4 text-muted">No records match the filter criteria.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($files as $file): ?>
          <tr>
            <td class="fw-bold text-primary"><?= htmlspecialchars($file['file_code']) ?></td>
            <td>
              <div class="fw-bold"><?= htmlspecialchars($file['customer_name']) ?></div>
              <small class="text-muted">Mobile: <?= htmlspecialchars($file['customer_mobile']) ?></small>
            </td>
            <td><?= htmlspecialchars($file['work_type_name']) ?></td>
            <td><?= htmlspecialchars($file['stage_name'] ?? 'Initial Intake') ?></td>
            <td><?= htmlspecialchars($file['assigned_user_name'] ?? 'Unassigned') ?></td>
            <td><span class="text-uppercase fw-bold small"><?= htmlspecialchars($file['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <!-- Official Footer sign block -->
  <div class="row mt-5 pt-4">
    <div class="col-6">
      <small class="text-muted d-block">Report Generated By: <?= htmlspecialchars($companyInfo['company_name']) ?></small>
      <small class="text-muted font-monospace" style="font-size: 0.7rem;">Verification System Code: OFM-<?= time() ?></small>
    </div>
    <div class="col-6 text-end">
      <div class="d-inline-block border-top border-dark pt-2 px-5 text-center mt-3">
        <small class="fw-bold text-dark">Authorized Signature</small>
      </div>
    </div>
  </div>

</div>

</body>
</html>
