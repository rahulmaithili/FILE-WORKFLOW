<?php
$pageTitle = 'Drag & Drop Kanban Board';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$user = getLoggedInUser();

$workTypes = $db->query("SELECT * FROM work_types ORDER BY id ASC")->fetchAll();
$selectedWorkTypeId = intval($_GET['work_type_id'] ?? ($workTypes[0]['id'] ?? 0));

$stages = [];
if ($selectedWorkTypeId > 0) {
    $stmt = $db->prepare("SELECT * FROM workflow_stages WHERE work_type_id = :wt ORDER BY stage_order ASC");
    $stmt->execute(['wt' => $selectedWorkTypeId]);
    $stages = $stmt->fetchAll();
}

// Fetch active files for this work type
$stmtFiles = $db->prepare("
    SELECT f.*, u.name as assigned_user_name, ws.sla_hours 
    FROM files f 
    LEFT JOIN users u ON f.current_assigned_user = u.id 
    LEFT JOIN workflow_stages ws ON f.current_stage_id = ws.id
    WHERE f.work_type_id = :wt AND f.status != 'rejected' AND f.status != 'completed'
    ORDER BY f.id DESC
");
$stmtFiles->execute(['wt' => $selectedWorkTypeId]);
$files = $stmtFiles->fetchAll();

// Group files by stage ID
$filesByStage = [];
foreach ($files as $f) {
    $filesByStage[$f['current_stage_id']][] = $f;
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
  <div>
    <h4 class="fw-bold mb-1">Drag & Drop Kanban Board</h4>
    <p class="text-muted small mb-0">Visually track case progression by dragging file cards across workflow stages</p>
  </div>
  <div class="d-flex gap-2">
    <a href="my-files.php" class="btn btn-outline-secondary fw-semibold">
      <i class="fas fa-list me-1"></i> Table View
    </a>
  </div>
</div>

<!-- Pipeline Selector Tabs -->
<div class="pipeline-selector-bar mb-4">
  <div class="d-flex align-items-center gap-2 overflow-auto w-100 py-1">
    <span class="fw-bold text-uppercase text-muted small me-2"><i class="fas fa-filter me-1 text-primary"></i> Pipeline:</span>
    <?php foreach ($workTypes as $wt): ?>
      <a href="kanban.php?work_type_id=<?= $wt['id'] ?>" class="pipeline-tab-item <?= $wt['id'] == $selectedWorkTypeId ? 'active' : '' ?>">
        <i class="fas <?= htmlspecialchars($wt['icon'] ?? 'fa-sitemap') ?>"></i>
        <span><?= htmlspecialchars($wt['name']) ?></span>
        <span class="code-badge"><?= htmlspecialchars($wt['code_prefix']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Kanban Columns Container -->
<div class="kanban-board">
  <?php 
  $colorNames = ['blue', 'green', 'purple', 'orange', 'red', 'teal'];
  $colorIdx = 0;
  foreach ($stages as $stage): 
    $stageFiles = $filesByStage[$stage['id']] ?? [];
    $colorClass = $colorNames[$colorIdx % count($colorNames)];
    $colorIdx++;
  ?>
    <div class="kanban-column accent-<?= $colorClass ?>">
      <div class="kanban-column-header">
        <div class="fw-bold text-dark text-truncate" style="max-width: 220px;" title="<?= htmlspecialchars($stage['stage_name']) ?>">
          <span class="badge badge-gradient-<?= $colorClass ?> me-1"><?= $stage['stage_order'] ?></span>
          <?= htmlspecialchars($stage['stage_name']) ?>
        </div>
        <span class="badge bg-light text-dark border rounded-pill"><?= count($stageFiles) ?></span>
      </div>

      <div class="kanban-cards-wrapper" data-stage-id="<?= $stage['id'] ?>">
        <?php foreach ($stageFiles as $file): 
          // Compute SLA coordinates
          $slaHours = intval($file['sla_hours'] ?? 24);
          $secondsElapsed = time() - strtotime($file['updated_at']);
          $secondsRemaining = ($slaHours * 3600) - $secondsElapsed;
          
          $isOverdue = ($secondsRemaining < 0);
          $absSeconds = abs($secondsRemaining);
          
          $hours = floor($absSeconds / 3600);
          $mins = floor(($absSeconds % 3600) / 60);
          
          if ($isOverdue) {
              $slaBadgeClass = 'badge-danger-flashing';
              $slaText = "Overdue: -{$hours}h {$mins}m";
              $glowClass = 'glow-warning-pulse';
          } else {
              $glowClass = '';
              $pctRemaining = ($secondsRemaining / ($slaHours * 3600)) * 100;
              if ($pctRemaining > 50) {
                  $slaBadgeClass = 'bg-success-soft text-success border border-success border-opacity-25';
              } else {
                  $slaBadgeClass = 'bg-warning-soft text-warning border border-warning border-opacity-25';
              }
              $slaText = "Left: {$hours}h {$mins}m";
          }
        ?>
          <div class="kanban-card <?= $glowClass ?>" data-file-id="<?= $file['id'] ?>">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <a href="<?= APP_URL ?>/modules/file/view.php?id=<?= $file['id'] ?>" class="fw-bold text-primary text-decoration-none small">
                <?= htmlspecialchars($file['file_code']) ?>
              </a>
              <?= getPriorityBadgeHtml($file['priority']) ?>
            </div>
            
            <div class="fw-bold text-dark mb-1" style="font-size: 0.9rem;"><?= htmlspecialchars($file['customer_name']) ?></div>
            <div class="small text-muted mb-2"><i class="fas fa-phone me-1"></i> <?= htmlspecialchars($file['customer_mobile']) ?></div>

            <div class="mb-3">
              <span class="badge <?= $slaBadgeClass ?> font-monospace" style="font-size: 0.7rem; border-radius: 6px;">
                <i class="far fa-hourglass me-1"></i> <?= $slaText ?>
              </span>
            </div>

            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
              <div class="d-flex align-items-center gap-1.5" title="Assigned Employee">
                <div class="avatar-circle" style="width: 22px; height: 22px; font-size: 0.65rem;">
                  <?= strtoupper(substr($file['assigned_user_name'] ?? 'U', 0, 1)) ?>
                </div>
                <small class="text-secondary fw-semibold" style="font-size: 0.75rem;"><?= htmlspecialchars($file['assigned_user_name'] ?? 'Unassigned') ?></small>
              </div>

              <a href="<?= APP_URL ?>/modules/file/forward.php?id=<?= $file['id'] ?>" class="btn btn-xs btn-outline-primary py-0 px-2" title="Forward Step">
                <i class="fas fa-paper-plane" style="font-size: 0.7rem;"></i>
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<script>
const APP_URL = '<?= APP_URL ?>';
</script>
<script src="<?= APP_URL ?>/assets/js/kanban.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
