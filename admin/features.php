<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requireAdmin();

$db = getDB();

// Seed additional features if missing
$newFeatures = [
    [
        'feature_key' => 'whatsapp',
        'feature_name' => 'WhatsApp Notifications Integration',
        'description' => 'Enable automated and manual dispatch of customer status alerts via WhatsApp templates.'
    ],
    [
        'feature_key' => 'calling',
        'feature_name' => 'Click-to-Call & Logs Tracker',
        'description' => 'Enable employee Click-to-Call dials interface and dynamic log tracker for phone summaries.'
    ],
    [
        'feature_key' => 'ai_ocr',
        'feature_name' => 'AI-OCR Document Form Scanner',
        'description' => 'Show the AI-OCR Scanning block to auto-extract credentials and auto-fill intake forms.'
    ],
    [
        'feature_key' => 'scanner',
        'feature_name' => 'Webcam Document Scanner Upload',
        'description' => 'Allow capturing document files live using the camera/webcam scanner interface.'
    ]
];

$stmtCheck = $db->prepare("SELECT COUNT(*) FROM system_features WHERE feature_key = ?");
$stmtInsert = $db->prepare("INSERT INTO system_features (feature_key, feature_name, description, status) VALUES (?, ?, ?, 'enabled')");

foreach ($newFeatures as $nf) {
    $stmtCheck->execute([$nf['feature_key']]);
    if ($stmtCheck->fetchColumn() == 0) {
        $stmtInsert->execute([$nf['feature_key'], $nf['feature_name'], $nf['description']]);
    }
}

// Handle Feature Status Toggle Post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_features'])) {
    $featureStatus = $_POST['feature_status'] ?? [];

    // Reset all features to disabled first
    $db->exec("UPDATE system_features SET status = 'disabled'");

    // Enable checked features
    if (!empty($featureStatus)) {
        $stmtEnable = $db->prepare("UPDATE system_features SET status = 'enabled' WHERE feature_key = :key");
        foreach ($featureStatus as $key => $val) {
            if ($val === 'enabled') {
                $stmtEnable->execute(['key' => sanitize($key)]);
            }
        }
    }

    setFlashMessage('success', 'System feature configurations updated successfully!');
    header("Location: features.php");
    exit;
}

$pageTitle = 'System Features & Addons Manager';
require_once __DIR__ . '/../includes/header.php';

// Fetch all features
$features = $db->query("SELECT * FROM system_features ORDER BY id ASC")->fetchAll();
?>

<div class="row justify-content-center">
  <div class="col-lg-9">
    <div class="card border-0 shadow-lg p-4" style="border-radius: var(--radius-lg);">
      <div class="border-bottom pb-3 mb-4 d-flex justify-content-between align-items-center">
        <div>
          <h4 class="fw-bold mb-1 text-dark"><i class="fas fa-toggle-on text-primary me-2"></i> System Features Toggle</h4>
          <p class="text-muted small mb-0">Enable or disable advanced feature modules as you work on or deploy them</p>
        </div>
        <span class="badge bg-primary-soft text-primary border px-3 py-2"><i class="fas fa-microchip me-1"></i> Feature Flags Console</span>
      </div>

      <form action="features.php" method="POST">
        <input type="hidden" name="update_features" value="1">

        <div class="list-group list-group-flush mb-4">
          <?php foreach ($features as $f): 
            $isEnabled = $f['status'] === 'enabled';
          ?>
            <div class="list-group-item d-flex flex-column flex-md-row justify-content-between align-items-md-center px-0 py-3.5 border-bottom">
              <div class="pe-4 mb-2 mb-md-0">
                <h6 class="fw-bold text-dark mb-1">
                  <?= htmlspecialchars($f['feature_name']) ?>
                  <?php if ($isEnabled): ?>
                    <span class="badge bg-success-soft text-success ms-2" style="font-size: 0.72rem;"><i class="fas fa-check-circle me-1"></i> Active</span>
                  <?php else: ?>
                    <span class="badge bg-light text-muted border ms-2" style="font-size: 0.72rem;"><i class="fas fa-ban me-1"></i> Inactive / Sandbox</span>
                  <?php endif; ?>
                </h6>
                <p class="text-muted small mb-0" style="font-size: 0.85rem; max-width: 600px;"><?= htmlspecialchars($f['description']) ?></p>
              </div>

              <!-- Radio Toggle Button Panel -->
              <div class="d-flex align-items-center gap-3">
                <div class="btn-group" role="group">
                  <input type="radio" class="btn-check" name="feature_status[<?= $f['feature_key'] ?>]" value="enabled" id="radio_en_<?= $f['feature_key'] ?>" <?= $isEnabled ? 'checked' : '' ?>>
                  <label class="btn btn-outline-success btn-sm px-3 fw-bold" for="radio_en_<?= $f['feature_key'] ?>">Enable</label>

                  <input type="radio" class="btn-check" name="feature_status[<?= $f['feature_key'] ?>]" value="disabled" id="radio_dis_<?= $f['feature_key'] ?>" <?= !$isEnabled ? 'checked' : '' ?>>
                  <label class="btn btn-outline-secondary btn-sm px-3 fw-bold" for="radio_dis_<?= $f['feature_key'] ?>">Disable</label>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="pt-3 border-top d-flex justify-content-between align-items-center">
          <a href="<?= APP_URL ?>/admin/dashboard.php" class="btn btn-light border text-muted">Cancel</a>
          <button type="submit" class="btn btn-primary btn-lg fw-bold px-4 shadow-sm">
            <i class="fas fa-save me-1"></i> Save Feature Configurations
          </button>
        </div>

      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
