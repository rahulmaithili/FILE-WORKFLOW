<?php
$pageTitle = 'Click-to-Call Dialer & Log';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$user = getLoggedInUser();
$fileId = intval($_GET['file_id'] ?? 0);

$stmtFile = $db->prepare("SELECT * FROM files WHERE id = :id LIMIT 1");
$stmtFile->execute(['id' => $fileId]);
$file = $stmtFile->fetch();

if (!$file) {
    echo "<div class='alert alert-danger'>File not found!</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Save Call Log
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $summary = sanitize($_POST['call_summary'] ?? '');
    $duration = intval($_POST['duration_seconds'] ?? 60);

    $stmtLog = $db->prepare("
        INSERT INTO call_logs (file_id, called_by, customer_mobile, call_summary, duration_seconds) 
        VALUES (:fid, :by, :mobile, :sum, :dur)
    ");
    $stmtLog->execute([
        'fid' => $fileId,
        'by' => $user['id'],
        'mobile' => $file['customer_mobile'],
        'sum' => $summary,
        'dur' => $duration
    ]);

    setFlashMessage('success', 'Call log recorded successfully.');
    header("Location: " . APP_URL . "/modules/file/view.php?id=" . $fileId);
    exit;
}

// Fetch Previous Call Logs
$stmtLogs = $db->prepare("
    SELECT c.*, u.name as caller_name 
    FROM call_logs c 
    LEFT JOIN users u ON c.called_by = u.id 
    WHERE c.file_id = :fid 
    ORDER BY c.id DESC
");
$stmtLogs->execute(['fid' => $fileId]);
$callLogs = $stmtLogs->fetchAll();
?>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="card border-0 shadow-lg p-4 mb-4" style="border-radius: var(--radius-lg);">
      <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
        <div>
          <h4 class="fw-bold mb-1 text-info"><i class="fas fa-phone-alt me-2"></i> Customer Click-To-Call</h4>
          <span class="fw-bold text-primary"><?= htmlspecialchars($file['file_code']) ?></span> &bull; <strong><?= htmlspecialchars($file['customer_name']) ?></strong>
        </div>
        <a href="tel:<?= htmlspecialchars($file['customer_mobile']) ?>" class="btn btn-success btn-lg fw-bold shadow-sm">
          <i class="fas fa-phone-volume me-1"></i> Dial <?= htmlspecialchars($file['customer_mobile']) ?>
        </a>
      </div>

      <form action="call-handler.php?file_id=<?= $fileId ?>" method="POST">
        <h6 class="fw-bold text-dark mb-3">Record Call Outcome Notes:</h6>
        <div class="mb-3">
          <label class="form-label fw-semibold">Call Discussion Summary</label>
          <textarea name="call_summary" class="form-control" rows="3" placeholder="Enter details of customer conversation..." required></textarea>
        </div>

        <div class="mb-4">
          <label class="form-label fw-semibold">Call Duration (Seconds)</label>
          <input type="number" name="duration_seconds" class="form-control" value="60" min="1">
        </div>

        <div class="d-flex justify-content-between align-items-center border-top pt-3">
          <a href="<?= APP_URL ?>/modules/file/view.php?id=<?= $fileId ?>" class="btn btn-light border text-muted">Back to Case File</a>
          <button type="submit" class="btn btn-info text-white fw-bold px-4">Save Call Log</button>
        </div>
      </form>
    </div>

    <!-- Call Log History Card -->
    <div class="card border-0 shadow-sm p-4" style="border-radius: var(--radius-lg);">
      <h5 class="fw-bold mb-3 border-bottom pb-2"><i class="fas fa-history text-secondary me-2"></i> Call Log History</h5>
      <?php if (empty($callLogs)): ?>
        <p class="text-muted small my-2">No call history recorded for this case file yet.</p>
      <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($callLogs as $c): ?>
            <div class="list-group-item px-0 py-2.5">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <strong class="text-dark small"><i class="fas fa-user-circle me-1 text-primary"></i> <?= htmlspecialchars($c['caller_name'] ?? 'Employee') ?></strong>
                <small class="text-muted"><?= timeAgo($c['called_at']) ?> (<?= $c['duration_seconds'] ?>s)</small>
              </div>
              <p class="mb-0 small text-secondary">"<?= htmlspecialchars($c['call_summary']) ?>"</p>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
