<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();

$db = getDB();
$fileId = intval($_GET['id'] ?? 0);

// Fetch File Details
$stmtFile = $db->prepare("
    SELECT f.*, wt.name as work_type_name, ws.stage_name, u.name as assigned_user_name, b.branch_name, b.branch_code
    FROM files f 
    LEFT JOIN work_types wt ON f.work_type_id = wt.id 
    LEFT JOIN workflow_stages ws ON f.current_stage_id = ws.id 
    LEFT JOIN users u ON f.current_assigned_user = u.id 
    LEFT JOIN branches b ON f.branch_id = b.id
    WHERE f.id = :id LIMIT 1
");
$stmtFile->execute(['id' => $fileId]);
$file = $stmtFile->fetch();

if (!$file) {
    echo "<div class='alert alert-danger'>File record not found.</div>";
    exit;
}

// Fetch all stages for the checklist representation
$stmtStages = $db->prepare("SELECT * FROM workflow_stages WHERE work_type_id = :wt ORDER BY stage_order ASC");
$stmtStages->execute(['wt' => $file['work_type_id']]);
$stages = $stmtStages->fetchAll();

// Target URL for scanning redirect
$redirectUrl = APP_URL . "/modules/file/view.php?id=" . $fileId;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Case File Cover Sheet - <?= htmlspecialchars($file['file_code']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: white;
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: #1e293b;
      padding: 2.5rem;
    }
    .cover-border {
      border: 3px double #cbd5e1;
      border-radius: 12px;
      padding: 2.5rem;
      min-height: 85vh;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .barcode-stub {
      height: 45px;
      background: repeating-linear-gradient(90deg, #000, #000 2px, transparent 2px, transparent 6px);
      width: 250px;
      margin-bottom: 0.5rem;
    }
    .qr-container {
      border: 1px solid #cbd5e1;
      padding: 0.75rem;
      border-radius: 8px;
      display: inline-block;
      background: #f8fafc;
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

<div class="container">
  
  <!-- Print Controls -->
  <div class="no-print bg-light p-3 rounded mb-4 d-flex justify-content-between align-items-center border">
    <div>
      <h6 class="fw-bold mb-1">Print Cover Label Sheet</h6>
      <small class="text-muted">Attach this sheet to the physical document folder</small>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-secondary fw-semibold" onclick="window.close()">Close Window</button>
      <button class="btn btn-primary fw-bold" onclick="window.print()"><i class="fas fa-print me-1"></i> Print Cover</button>
    </div>
  </div>

  <div class="cover-border">
    
    <!-- Top Barcode & Header -->
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <div class="barcode-stub"></div>
        <span class="font-monospace fw-bold text-muted" style="font-size: 0.8rem;">CODE: <?= htmlspecialchars($file['file_code']) ?></span>
      </div>
      <div class="text-end">
        <h4 class="fw-bold mb-0 text-dark">OFFICIAL CASE FILE</h4>
        <small class="text-muted text-uppercase fw-bold font-monospace">Priority: <?= htmlspecialchars($file['priority']) ?></small>
      </div>
    </div>

    <!-- Core Case Details Block -->
    <div class="my-5">
      <h2 class="fw-bold text-primary mb-4" style="font-size: 2.2rem; border-bottom: 2px solid #cbd5e1; padding-bottom: 0.5rem;">
        <?= htmlspecialchars($file['file_code']) ?>
      </h2>
      
      <div class="row g-4 fs-5">
        <div class="col-6 mb-3">
          <small class="text-muted d-block fw-bold text-uppercase" style="font-size: 0.72rem;">Customer Name</small>
          <strong class="text-dark"><?= htmlspecialchars($file['customer_name']) ?></strong>
        </div>
        <div class="col-6 mb-3">
          <small class="text-muted d-block fw-bold text-uppercase" style="font-size: 0.72rem;">Customer Mobile</small>
          <strong class="text-dark"><?= htmlspecialchars($file['customer_mobile']) ?></strong>
        </div>
        <div class="col-12 mb-3">
          <small class="text-muted d-block fw-bold text-uppercase" style="font-size: 0.72rem;">Workflow Pipeline Template</small>
          <strong class="text-dark"><?= htmlspecialchars($file['work_type_name']) ?></strong>
        </div>
        <div class="col-6 mb-3">
          <small class="text-muted d-block fw-bold text-uppercase" style="font-size: 0.72rem;">Target Branch Office</small>
          <strong class="text-dark"><?= htmlspecialchars($file['branch_name'] ?? 'HQ') ?> (<?= htmlspecialchars($file['branch_code'] ?? 'HQ') ?>)</strong>
        </div>
        <div class="col-6 mb-3">
          <small class="text-muted d-block fw-bold text-uppercase" style="font-size: 0.72rem;">Initiator Employee</small>
          <strong class="text-dark"><?= htmlspecialchars($file['assigned_user_name'] ?? 'Unassigned') ?></strong>
        </div>
        <div class="col-12">
          <small class="text-muted d-block fw-bold text-uppercase" style="font-size: 0.72rem;">Registered Address</small>
          <div class="small text-secondary"><?= htmlspecialchars($file['customer_address'] ?: 'No address provided.') ?></div>
        </div>
      </div>
    </div>

    <!-- Stepper Checklist representation -->
    <div class="mb-4">
      <h6 class="fw-bold mb-3 text-uppercase text-muted" style="font-size: 0.75rem; letter-spacing: 0.6px;">Workflow Checkpoints Checklist</h6>
      <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle small">
          <thead class="table-light">
            <tr>
              <th style="width: 10%;">Step</th>
              <th style="width: 45%;">Processing Stage Title</th>
              <th style="width: 25%;">Required SLA Limits</th>
              <th style="width: 20%;">Date Signed</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($stages as $stg): ?>
              <tr>
                <td class="text-center fw-bold">Step <?= $stg['stage_order'] ?></td>
                <td><i class="far fa-square me-2"></i> <?= htmlspecialchars($stg['stage_name']) ?></td>
                <td><?= $stg['sla_hours'] ?> Hours Limits</td>
                <td class="text-muted font-monospace small">____/____/________</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Footer QR Code redirection -->
    <div class="d-flex justify-content-between align-items-end mt-auto border-top pt-4">
      <div>
        <small class="text-muted d-block">System Verification Address:</small>
        <small class="font-monospace text-secondary" style="font-size: 0.72rem;"><?= htmlspecialchars($redirectUrl) ?></small>
      </div>
      <div class="text-end">
        <div class="qr-container">
          <!-- Premium Local SVG QR Code lookalike -->
          <svg xmlns="http://www.w3.org/2000/svg" width="90" height="90" viewBox="0 0 29 29" shape-rendering="crispEdges">
            <path d="M0 0h7v1H0zm0 1h1v5H0zm6 0h1v5H6zm0 6h7v1H0zm2 2h3v1H2zm0 1h1v1H2zm2 0h1v1H4zm0 1h1v1H4z" fill="#1e293b"/>
            <path d="M22 0h7v1H22zm0 1h1v5H22zm6 0h1v5H28zm0 6h7v1H22zm2 2h3v1H2zm0 1h1v1H2zm2 0h1v1H4zm0 1h1v1H4z" transform="translate(22, 0)" fill="#1e293b"/>
            <path d="M0 22h7v1H0zm0 1h1v5H0zm6 0h1v5H6zm0 6h7v1H0zm2 2h3v1H2zm0 1h1v1H2zm2 0h1v1H4zm0 1h1v1H4z" transform="translate(0, 22)" fill="#1e293b"/>
            <!-- Inner matrix dots patterns -->
            <rect x="9" y="3" width="2" height="2" fill="#1e293b"/>
            <rect x="13" y="1" width="3" height="1" fill="#1e293b"/>
            <rect x="18" y="4" width="2" height="3" fill="#1e293b"/>
            <rect x="11" y="9" width="3" height="2" fill="#1e293b"/>
            <rect x="9" y="14" width="2" height="1" fill="#1e293b"/>
            <rect x="15" y="16" width="3" height="3" fill="#1e293b"/>
            <rect x="2" y="18" width="4" height="2" fill="#1e293b"/>
            <rect x="21" y="12" width="4" height="4" fill="#1e293b"/>
            <rect x="19" y="22" width="2" height="3" fill="#1e293b"/>
            <rect x="13" y="25" width="4" height="2" fill="#1e293b"/>
          </svg>
        </div>
        <small class="text-muted d-block text-center mt-1" style="font-size: 0.65rem;">Scan to Redirect</small>
      </div>
    </div>

  </div>
</div>

</body>
</html>
