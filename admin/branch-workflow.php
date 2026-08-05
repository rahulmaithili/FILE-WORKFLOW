<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requireAdmin();

$db = getDB();
$error = '';
$success = '';

// Handle routing rules toggles
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_rule'])) {
    $ruleId = intval($_POST['rule_id'] ?? 0);
    $status = sanitize($_POST['status'] ?? 'enabled');

    if ($ruleId > 0) {
        $stmt = $db->prepare("UPDATE branch_routing_rules SET status = :status WHERE id = :id");
        $stmt->execute(['status' => $status, 'id' => $ruleId]);
        $success = "Branch routing configuration updated successfully!";
    }
}

// Fetch all branches
$branches = $db->query("SELECT * FROM branches ORDER BY id ASC")->fetchAll();

// Fetch routing rules
$rules = $db->query("
    SELECT r.*, b1.branch_name as from_name, b1.branch_code as from_code, b2.branch_name as to_name, b2.branch_code as to_code 
    FROM branch_routing_rules r 
    JOIN branches b1 ON r.from_branch_id = b1.id 
    JOIN branches b2 ON r.to_branch_id = b2.id
    ORDER BY b1.id ASC, b2.id ASC
")->fetchAll();

// Fetch live inter-branch dispatches
// A file is dispatched if its current branch_id != creator user's branch_id
$dispatches = $db->query("
    SELECT f.*, wt.name as work_type_name, 
           b_origin.branch_name as origin_branch_name, b_origin.branch_code as origin_branch_code,
           b_curr.branch_name as current_branch_name, b_curr.branch_code as current_branch_code,
           u_creator.name as creator_name, u_curr.name as assignee_name
    FROM files f
    JOIN users u_creator ON f.created_by = u_creator.id
    JOIN branches b_origin ON u_creator.branch_id = b_origin.id
    JOIN branches b_curr ON f.branch_id = b_curr.id
    LEFT JOIN work_types wt ON f.work_type_id = wt.id
    LEFT JOIN users u_curr ON f.current_assigned_user = u_curr.id
    WHERE f.branch_id != u_creator.branch_id
    ORDER BY f.updated_at DESC
")->fetchAll();

// Generate Mermaid Diagram String dynamically based on active rules and statistics!
$mermaidDefinition = "graph LR\n";
// Add branch nodes with styled labels showing active counts
foreach ($branches as $br) {
    $stmtCount = $db->prepare("SELECT COUNT(*) FROM files WHERE branch_id = :bid AND status IN ('pending', 'in_progress')");
    $stmtCount->execute(['bid' => $br['id']]);
    $activeCount = $stmtCount->fetchColumn();
    $mermaidDefinition .= "  B" . $br['id'] . "(\"" . $br['branch_code'] . " (" . $activeCount . " Active Cases)\")\n";
}

// Add links based on rules
foreach ($rules as $rl) {
    if ($rl['status'] === 'enabled') {
        $mermaidDefinition .= "  B" . $rl['from_branch_id'] . " -->|Allowed| B" . $rl['to_branch_id'] . "\n";
    } else {
        $mermaidDefinition .= "  B" . $rl['from_branch_id'] . " -.->|Blocked| B" . $rl['to_branch_id'] . "\n";
    }
}

$pageTitle = 'Inter-Branch Workflow Node Map';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
  <!-- Top Section: Interactive Diagram Visualizer -->
  <div class="col-12">
    <div class="card border-0 shadow-sm p-4" style="border-radius: var(--radius-lg);">
      <div class="border-bottom pb-3 mb-4 d-flex justify-content-between align-items-center">
        <div>
          <h4 class="fw-bold mb-1 text-dark"><i class="fas fa-network-wired text-primary me-2"></i> Inter-Branch Workflow Diagram Map</h4>
          <p class="text-muted small mb-0">Visual flow chart displaying routing rules, connections, and live case loads across office centers</p>
        </div>
        <span class="badge bg-primary-soft text-primary border px-3 py-2"><i class="fas fa-sitemap me-1"></i> Live Topology Map</span>
      </div>

      <!-- Mermaid Diagram Rendering Port -->
      <div class="bg-light p-4 rounded text-center d-flex justify-content-center align-items-center" style="min-height: 250px; border: 1px dashed var(--border-color); overflow: auto;">
        <div class="mermaid w-100" id="branchesMermaidMap">
            <?= $mermaidDefinition ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Left Column: Inter-Branch Routing Rules & Access Control (Connect/Access control) -->
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm p-4 h-100" style="border-radius: var(--radius-lg);">
      <h5 class="fw-bold mb-3 border-bottom pb-2 text-dark">
        <i class="fas fa-lock text-warning me-2"></i> Branch Routing Access Control
      </h5>
      <p class="text-muted small mb-3">Enable or disable allowed file transfer routes between offices. Click to modify access connection lines.</p>

      <?php if (!empty($success)): ?>
        <div class="alert alert-success py-2 px-3 small mb-3"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <div class="list-group list-group-flush">
        <?php foreach ($rules as $rl): 
          $isEnabled = ($rl['status'] === 'enabled');
        ?>
          <div class="list-group-item px-0 py-3 d-flex align-items-center justify-content-between border-bottom">
            <div>
              <h6 class="fw-bold mb-1 text-dark">
                <?= htmlspecialchars($rl['from_code']) ?> <i class="fas fa-long-arrow-alt-right text-muted mx-1"></i> <?= htmlspecialchars($rl['to_code']) ?>
              </h6>
              <small class="text-muted"><?= htmlspecialchars($rl['from_name']) ?> to <?= htmlspecialchars($rl['to_name']) ?></small>
            </div>
            
            <form action="branch-workflow.php" method="POST">
              <input type="hidden" name="toggle_rule" value="1">
              <input type="hidden" name="rule_id" value="<?= $rl['id'] ?>">
              <?php if ($isEnabled): ?>
                <input type="hidden" name="status" value="disabled">
                <button type="submit" class="btn btn-sm btn-success fw-bold px-3">
                  <i class="fas fa-check-circle me-1"></i> Connected
                </button>
              <?php else: ?>
                <input type="hidden" name="status" value="enabled">
                <button type="submit" class="btn btn-sm btn-outline-secondary fw-bold px-3">
                  <i class="fas fa-ban me-1"></i> Blocked
                </button>
              <?php endif; ?>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Right Column: Dispatched Cases Tracking Board (Kaam status tracker) -->
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm p-4 h-100" style="border-radius: var(--radius-lg);">
      <h5 class="fw-bold mb-3 border-bottom pb-2 text-dark">
        <i class="fas fa-shipping-fast text-success me-2"></i> Dispatched Branch File Logs
      </h5>
      <p class="text-muted small mb-3">Logs of all files forwarded from their origin branch to a different target branch for processing.</p>

      <?php if (empty($dispatches)): ?>
        <div class="text-center py-5 text-muted">
          <i class="fas fa-exchange-alt fa-3x mb-3 text-secondary opacity-50"></i>
          <h6>No cases currently dispatched to other branches.</h6>
          <p class="small text-muted">When cases are forwarded to a different branch office, they will appear here.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Case File</th>
                <th>Transfer Path</th>
                <th>Assignee</th>
                <th>Return Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($dispatches as $ds): 
                $isCompleted = ($ds['status'] === 'completed');
              ?>
                <tr>
                  <td>
                    <a href="<?= APP_URL ?>/modules/file/view.php?id=<?= $ds['id'] ?>" class="fw-bold text-primary text-decoration-none small">
                      <?= htmlspecialchars($ds['file_code']) ?>
                    </a>
                    <div class="small text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($ds['customer_name']) ?></div>
                  </td>
                  <td>
                    <span class="badge bg-light text-dark border small" title="Origin Branch">
                      <?= htmlspecialchars($ds['origin_branch_code']) ?>
                    </span>
                    <i class="fas fa-long-arrow-alt-right text-success mx-1"></i>
                    <span class="badge bg-primary-soft text-primary border small" title="Current Processing Branch">
                      <?= htmlspecialchars($ds['current_branch_code']) ?>
                    </span>
                  </td>
                  <td>
                    <small class="text-muted fw-semibold"><?= htmlspecialchars($ds['assignee_name'] ?? 'Unassigned') ?></small>
                  </td>
                  <td>
                    <?php if ($isCompleted): ?>
                      <span class="badge bg-success-soft text-success px-2 py-1"><i class="fas fa-check-circle me-1"></i> Returned (Done)</span>
                    <?php else: ?>
                      <span class="badge bg-warning-soft text-warning px-2 py-1"><i class="fas fa-clock fa-spin me-1"></i> Pending Return</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Load Mermaid.js for drawing visual node map flowchart -->
<script src="https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js"></script>
<script>
  mermaid.initialize({ startOnLoad: true, theme: 'default' });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
