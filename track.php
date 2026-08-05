<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$db = getDB();
$error = '';
$file = null;
$stages = [];
$currentStageOrder = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fileCode = strtoupper(sanitize($_POST['file_code'] ?? ''));
    $mobile = sanitize($_POST['customer_mobile'] ?? '');

    if (empty($fileCode) || empty($mobile)) {
        $error = "Please enter both Case File Code and registered Mobile Number.";
    } else {
        $stmt = $db->prepare("
            SELECT f.*, wt.name as work_type_name, ws.stage_order as current_stage_order, ws.stage_name as current_stage_name
            FROM files f 
            JOIN work_types wt ON f.work_type_id = wt.id
            LEFT JOIN workflow_stages ws ON f.current_stage_id = ws.id
            WHERE f.file_code = :code AND f.customer_mobile = :mobile AND f.status != 'rejected'
            LIMIT 1
        ");
        $stmt->execute(['code' => $fileCode, 'mobile' => $mobile]);
        $file = $stmt->fetch();

        if ($file) {
            // Fetch stages list for this work type
            $stmtStages = $db->prepare("SELECT * FROM workflow_stages WHERE work_type_id = :wt ORDER BY stage_order ASC");
            $stmtStages->execute(['wt' => $file['work_type_id']]);
            $stages = $stmtStages->fetchAll();
            $currentStageOrder = intval($file['current_stage_order'] ?? 0);
        } else {
            $error = "No active case record matches these details. Please verify your entries.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Track File Status - Public Portal</title>
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- FontAwesome 6 -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary-color: #3b82f6;
      --bg-body: #f8fafc;
      --bg-card: #ffffff;
      --border-color: #e2e8f0;
      --radius-lg: 16px;
    }
    body {
      background-color: var(--bg-body);
      font-family: 'Plus Jakarta Sans', sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
    }
    .track-card {
      background-color: var(--bg-card);
      border-radius: var(--radius-lg);
      border: 1px solid var(--border-color);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
      width: 100%;
      max-width: 650px;
      padding: 2.5rem;
    }
    .horizontal-stepper {
      display: flex;
      justify-content: space-between;
      position: relative;
      margin-top: 3rem;
      margin-bottom: 2rem;
    }
    .horizontal-stepper::before {
      content: '';
      position: absolute;
      top: 15px;
      left: 0;
      right: 0;
      height: 4px;
      background: #e2e8f0;
      z-index: 1;
    }
    .stepper-progress-fill {
      position: absolute;
      top: 15px;
      left: 0;
      height: 4px;
      background: #10b981;
      z-index: 1;
      transition: width 0.4s ease;
    }
    .step-node {
      position: relative;
      z-index: 2;
      display: flex;
      flex-direction: column;
      align-items: center;
      width: 60px;
    }
    .step-circle {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: #ffffff;
      border: 3px solid #cbd5e1;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.8rem;
      font-weight: 700;
      color: #94a3b8;
      transition: all 0.3s ease;
    }
    .step-node.completed .step-circle {
      background: #10b981;
      border-color: #10b981;
      color: #ffffff;
    }
    .step-node.active .step-circle {
      border-color: #3b82f6;
      color: #3b82f6;
      background: #ffffff;
      box-shadow: 0 0 12px rgba(59, 130, 246, 0.35);
    }
    .step-label {
      margin-top: 0.5rem;
      font-size: 0.72rem;
      font-weight: 700;
      text-align: center;
      color: #64748b;
      white-space: nowrap;
      position: absolute;
      top: 35px;
    }
    .step-node.active .step-label {
      color: #3b82f6;
    }
    .step-node.completed .step-label {
      color: #1e293b;
    }
  </style>
</head>
<body>

<div class="track-card">
  
  <?php if (!$file): ?>
    <!-- Search / Form View -->
    <div class="text-center mb-4">
      <div class="bg-primary-soft text-primary d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 60px; height: 60px; background-color: rgba(59,130,246,0.1);">
        <i class="fas fa-search-location fa-2x"></i>
      </div>
      <h4 class="fw-bold text-dark">Track Case File Status</h4>
      <p class="text-muted small">Enter credentials to trace real-time pipeline status coordinates</p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger py-2 px-3 small mb-3 text-center"><i class="fas fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form action="track.php" method="POST">
      <div class="mb-3">
        <label class="form-label small fw-bold">Case File Code</label>
        <input type="text" name="file_code" class="form-control form-control-lg text-uppercase" placeholder="e.g. FIB-2026-00001" required>
      </div>
      <div class="mb-4">
        <label class="form-label small fw-bold">Registered Mobile Number</label>
        <input type="text" name="customer_mobile" class="form-control form-control-lg" placeholder="e.g. 9822011223" required>
      </div>
      <button type="submit" class="btn btn-primary w-100 py-2.5 fw-bold shadow-sm">
        <i class="fas fa-search me-1"></i> Retrieve Case Status
      </button>
      <div class="text-center mt-3">
        <a href="index.php" class="text-decoration-none small text-secondary"><i class="fas fa-arrow-left me-1"></i> Back to Employee Login</a>
      </div>
    </form>

  <?php else: ?>
    <!-- Track Progress Chart View -->
    <div class="border-bottom pb-3 mb-4 d-flex justify-content-between align-items-center">
      <div>
        <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($file['customer_name']) ?></h5>
        <small class="text-muted">Pipeline: <strong><?= htmlspecialchars($file['work_type_name']) ?></strong></small>
      </div>
      <span class="badge bg-primary px-3 py-2 fs-6 font-monospace" style="border-radius: 8px;"><?= htmlspecialchars($file['file_code']) ?></span>
    </div>

    <!-- Stepper calculations -->
    <?php
    $totalStages = count($stages);
    $activeIdx = 0;
    foreach ($stages as $idx => $stg) {
        if (intval($stg['stage_order']) === $currentStageOrder) {
            $activeIdx = $idx;
            break;
        }
    }
    // Calculate progress fill width percentage
    $progressPercent = 0;
    if ($file['status'] === 'completed') {
        $progressPercent = 100;
    } elseif ($totalStages > 1) {
        $progressPercent = ($activeIdx / ($totalStages - 1)) * 100;
    }
    ?>

    <div class="text-center py-2">
      <small class="text-muted d-block uppercase fw-bold mb-1">Current processing stage:</small>
      <?php if ($file['status'] === 'completed'): ?>
        <h5 class="fw-bold text-success"><i class="fas fa-check-double me-1"></i> Service Activated / Connection Completed!</h5>
      <?php else: ?>
        <h5 class="fw-bold text-primary"><i class="fas fa-spinner fa-spin me-1"></i> <?= htmlspecialchars($file['current_stage_name'] ?? 'Initial Intake') ?></h5>
      <?php endif; ?>
    </div>

    <!-- Stepper Graphic Layout -->
    <div class="horizontal-stepper">
      <div class="stepper-progress-fill" style="width: <?= $progressPercent ?>%"></div>
      <?php foreach ($stages as $idx => $stg): 
        $stgOrder = intval($stg['stage_order']);
        $class = '';
        if ($file['status'] === 'completed' || $stgOrder < $currentStageOrder) {
            $class = 'completed';
        } elseif ($stgOrder === $currentStageOrder && $file['status'] !== 'completed') {
            $class = 'active';
        }
      ?>
        <div class="step-node <?= $class ?>">
          <div class="step-circle">
            <?php if ($file['status'] === 'completed' || $stgOrder < $currentStageOrder): ?>
              <i class="fas fa-check"></i>
            <?php else: ?>
              <?= $stg['stage_order'] ?>
            <?php endif; ?>
          </div>
          <div class="step-label text-truncate" style="max-width: 80px;" title="<?= htmlspecialchars($stg['stage_name']) ?>">
            <?= htmlspecialchars($stg['stage_name']) ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="p-3 bg-light rounded text-center small text-secondary mt-5 border">
      <i class="fas fa-info-circle text-primary me-1"></i> Need support? Please contact our desk helpline quoting Case File: <strong><?= htmlspecialchars($file['file_code']) ?></strong>.
    </div>

    <div class="text-center mt-4">
      <a href="track.php" class="btn btn-outline-secondary btn-sm fw-bold px-4"><i class="fas fa-redo me-1"></i> Track Another Case</a>
    </div>

  <?php endif; ?>

</div>

</body>
</html>
