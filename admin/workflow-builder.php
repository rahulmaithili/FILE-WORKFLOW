<?php
$pageTitle = 'Dynamic Workflow Designer';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$db = getDB();

// Get all work types
$workTypes = $db->query("SELECT * FROM work_types ORDER BY id ASC")->fetchAll();
$selectedWorkTypeId = intval($_GET['work_type_id'] ?? ($workTypes[0]['id'] ?? 0));

$activeWorkType = null;
foreach ($workTypes as $wt) {
    if ($wt['id'] == $selectedWorkTypeId) {
        $activeWorkType = $wt;
        break;
    }
}

// Fetch stages for selected work type
$stages = [];
if ($selectedWorkTypeId > 0) {
    $stmt = $db->prepare("
        SELECT ws.*, r.role_name 
        FROM workflow_stages ws 
        JOIN roles r ON ws.assigned_role_id = r.id 
        WHERE ws.work_type_id = :wt 
        ORDER BY ws.stage_order ASC
    ");
    $stmt->execute(['wt' => $selectedWorkTypeId]);
    $stages = $stmt->fetchAll();
}

// Handle updating required documents for work type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_required_docs'])) {
    // Delete existing mappings
    $stmtDelete = $db->prepare("DELETE FROM work_type_required_docs WHERE work_type_id = :wt");
    $stmtDelete->execute(['wt' => $selectedWorkTypeId]);

    // Insert new mappings
    $docIds = $_POST['required_doc_ids'] ?? [];
    if (!empty($docIds)) {
        $stmtInsert = $db->prepare("INSERT INTO work_type_required_docs (work_type_id, document_type_id) VALUES (:wt, :doc)");
        foreach ($docIds as $docId) {
            $stmtInsert->execute(['wt' => $selectedWorkTypeId, 'doc' => intval($docId)]);
        }
    }
    setFlashMessage('success', 'Required documents updated successfully.');
    header("Location: workflow-builder.php?work_type_id=" . $selectedWorkTypeId);
    exit;
}

$roles = $db->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1">Visual Drag & Drop Workflow Builder</h4>
    <p class="text-muted small mb-0">Design custom step-by-step case pipelines, assign stage roles, and define SLA timeframes</p>
  </div>
  <button class="btn btn-outline-primary fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#createWorkTypeModal">
    <i class="fas fa-plus-circle me-1"></i> New Work Type Template
  </button>
</div>

<!-- Work Type Selector Tabs -->
<div class="pipeline-selector-bar mb-4">
  <div class="d-flex align-items-center gap-2 overflow-auto w-100 py-1">
    <span class="fw-bold text-uppercase text-muted small me-2"><i class="fas fa-sitemap me-1 text-primary"></i> Pipelines:</span>
    <?php foreach ($workTypes as $wt): ?>
      <a href="workflow-builder.php?work_type_id=<?= $wt['id'] ?>" class="pipeline-tab-item <?= $wt['id'] == $selectedWorkTypeId ? 'active' : '' ?>">
        <i class="fas <?= htmlspecialchars($wt['icon'] ?? 'fa-sitemap') ?>"></i>
        <span><?= htmlspecialchars($wt['name']) ?></span>
        <span class="code-badge"><?= htmlspecialchars($wt['code_prefix']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($activeWorkType): ?>
  <div class="row g-4">
    
    <!-- Stage List Column -->
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm p-4" style="border-radius: var(--radius-lg);">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h5 class="fw-bold mb-1"><?= htmlspecialchars($activeWorkType['name']) ?> Workflow Pipeline</h5>
            <small class="text-muted"><i class="fas fa-info-circle me-1"></i> Drag and drop cards to reorder workflow progression steps</small>
          </div>
          <button class="btn btn-sm btn-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#addStageModal">
            <i class="fas fa-plus me-1"></i> Add Stage Step
          </button>
        </div>

        <div id="workflowStageList">
          <?php if (empty($stages)): ?>
            <div class="text-center py-5 text-muted">
              <i class="fas fa-sitemap fa-3x mb-3 text-secondary opacity-50"></i>
              <h6>No workflow stages defined yet.</h6>
              <p class="small">Click "Add Stage Step" to create the first step for this workflow template.</p>
            </div>
          <?php else: ?>
            <?php 
            $colorNames = ['blue', 'green', 'purple', 'orange', 'red', 'teal'];
            foreach ($stages as $index => $stage): 
              $colorClass = $colorNames[$index % count($colorNames)];
            ?>
              <div class="workflow-stage-item" data-stage-id="<?= $stage['id'] ?>">
                <div class="d-flex align-items-center gap-3">
                  <span class="drag-handle text-muted me-1" style="font-size: 1.2rem;" title="Drag to reorder">
                    <i class="fas fa-grip-vertical"></i>
                  </span>
                  <span class="badge badge-gradient-<?= $colorClass ?> rounded-pill stage-number-badge px-3 py-2">
                    Step <?= $index + 1 ?>
                  </span>
                  <div>
                    <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($stage['stage_name']) ?></h6>
                    <small class="text-muted">
                      Assigned Role: <strong class="text-primary"><?= htmlspecialchars($stage['role_name']) ?></strong> &bull; 
                      SLA: <strong class="text-warning"><i class="fas fa-clock me-1"></i><?= $stage['sla_hours'] ?> hrs</strong>
                    </small>
                    <?php if (!empty($stage['required_documents'])): ?>
                      <div class="mt-1">
                        <small class="text-muted"><i class="fas fa-paperclip me-1 text-info"></i> Required Docs: <?= htmlspecialchars($stage['required_documents']) ?></small>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>

                <button class="btn btn-sm btn-light text-danger border" onclick="deleteStage(<?= $stage['id'] ?>)" title="Delete Step">
                  <i class="fas fa-trash-alt"></i>
                </button>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Template Info Column -->
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm p-4 h-100" style="border-radius: var(--radius-lg);">
        <h5 class="fw-bold mb-3"><i class="fas fa-cogs text-primary me-2"></i> Pipeline Info</h5>
        <div class="mb-3">
          <label class="form-label text-muted small fw-bold">Template Name</label>
          <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($activeWorkType['name']) ?></div>
        </div>
        <div class="mb-3">
          <label class="form-label text-muted small fw-bold">File Code Prefix</label>
          <div><span class="badge bg-dark px-3 py-1 fs-6"><?= htmlspecialchars($activeWorkType['code_prefix']) ?>-YYYY-XXXXX</span></div>
        </div>
        <div class="mb-3">
          <label class="form-label text-muted small fw-bold">Description</label>
          <p class="text-dark small mb-0"><?= htmlspecialchars($activeWorkType['description'] ?: 'No description provided.') ?></p>
        </div>
        <!-- Required Documents Checklist Config -->
        <div class="mt-4 pt-3 border-top mb-4">
          <label class="form-label text-muted small fw-bold mb-2"><i class="fas fa-tasks me-1 text-primary"></i> Required Documents</label>
          <form action="workflow-builder.php?work_type_id=<?= $selectedWorkTypeId ?>" method="POST">
            <input type="hidden" name="update_required_docs" value="1">
            <div class="d-flex flex-column gap-2 mb-3 bg-light p-3 rounded" style="max-height: 200px; overflow-y: auto;">
              <?php 
              // Fetch all document types
              $allDocTypes = $db->query("SELECT * FROM document_types ORDER BY name ASC")->fetchAll();
              // Fetch currently selected required doc IDs for this work type
              $stmtReq = $db->prepare("SELECT document_type_id FROM work_type_required_docs WHERE work_type_id = :wt");
              $stmtReq->execute(['wt' => $selectedWorkTypeId]);
              $currentReqDocIds = $stmtReq->fetchAll(PDO::FETCH_COLUMN);

              if (empty($allDocTypes)):
              ?>
                <small class="text-muted">No document types defined. <a href="document-types.php">Create some here</a>.</small>
              <?php 
              else:
                foreach ($allDocTypes as $docType): 
                  $isChecked = in_array($docType['id'], $currentReqDocIds) ? 'checked' : '';
                ?>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="required_doc_ids[]" value="<?= $docType['id'] ?>" id="req_doc_<?= $docType['id'] ?>" <?= $isChecked ?>>
                    <label class="form-check-label small fw-semibold text-dark" for="req_doc_<?= $docType['id'] ?>">
                      <?= htmlspecialchars($docType['name']) ?>
                      <?= $docType['is_mandatory'] ? '<span class="text-danger">*</span>' : '' ?>
                    </label>
                  </div>
                <?php 
                endforeach; 
              endif;
              ?>
            </div>
            <?php if (!empty($allDocTypes)): ?>
              <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold shadow-sm">
                <i class="fas fa-save me-1"></i> Save Required Docs
              </button>
            <?php endif; ?>
          </form>
        </div>
        <div class="p-3 bg-light rounded mt-auto">
          <small class="text-muted d-block fw-bold mb-1"><i class="fas fa-lightbulb text-warning me-1"></i> How Workflow Transfer Works:</small>
          <small class="text-muted">When an employee clicks "Forward", the file moves sequentially to the next defined step in this list, notifying eligible employees automatically.</small>
        </div>
        <div class="mt-4 pt-3 border-top">
          <button class="btn btn-outline-danger btn-sm w-100 fw-bold" onclick="deleteWorkType(<?= $activeWorkType['id'] ?>)">
            <i class="fas fa-trash-alt me-1"></i> Delete Pipeline Template
          </button>
        </div>
      </div>
    </div>

  </div>
<?php endif; ?>

<!-- Modal: Create Work Type -->
<div class="modal fade" id="createWorkTypeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-lg);">
      <form id="createWorkTypeForm" onsubmit="submitWorkType(event)">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-bold"><i class="fas fa-folder-plus me-2"></i> Create Work Type</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-semibold">Work Type Name</label>
            <input type="text" name="name" class="form-control" placeholder="e.g. CCTV Camera Installation" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">File Code Prefix (3 letters)</label>
            <input type="text" name="code_prefix" class="form-control text-uppercase" placeholder="e.g. CTV" maxlength="5" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Description</label>
            <textarea name="description" class="form-control" rows="2" placeholder="Purpose of this case pipeline"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Pipeline Graphic Icon</label>
            <select name="icon" class="form-select" required>
              <option value="fa-sitemap">Sitemap / Process Flow (Default)</option>
              <option value="fa-network-wired">Network Grid / Fiber Connections</option>
              <option value="fa-tv">Cable / TV Channels / Upgrade</option>
              <option value="fa-id-card">Name / Registration Transfer</option>
              <option value="fa-file-invoice-dollar">Billing / Payments / Complaints</option>
              <option value="fa-wrench">Technical Maintenance / Service</option>
              <option value="fa-shopping-cart">Order Processing / E-commerce</option>
              <option value="fa-phone-alt">Customer Support / Telephony</option>
              <option value="fa-user-shield">Verification & Security Check</option>
            </select>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary fw-bold">Create Template</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Add Stage Step -->
<div class="modal fade" id="addStageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-lg);">
      <form id="addStageForm" onsubmit="submitAddStage(event)">
        <input type="hidden" name="work_type_id" value="<?= $selectedWorkTypeId ?>">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title fw-bold"><i class="fas fa-step-forward me-2"></i> Add Workflow Stage Step</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-semibold">Stage Name</label>
            <input type="text" name="stage_name" class="form-control" placeholder="e.g. Site Survey & Cabling" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Assigned Employee Role</label>
            <select name="assigned_role_id" class="form-select" required>
              <option value="">-- Select Responsible Role --</option>
              <?php foreach ($roles as $r): ?>
                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['role_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Target SLA Timeframe (Hours)</label>
            <input type="number" name="sla_hours" class="form-control" value="24" min="1" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Required Documents (Optional)</label>
            <input type="text" name="required_documents" class="form-control" placeholder="e.g. ID Proof, Site Map">
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary fw-bold">Save Stage Step</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script class="script-builder">
const APP_URL = '<?= APP_URL ?>';

function submitWorkType(e) {
  e.preventDefault();
  const formData = new FormData(document.getElementById('createWorkTypeForm'));
  formData.append('action', 'add_work_type');

  fetch(APP_URL + '/api/workflow-api.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        window.location.href = 'workflow-builder.php?work_type_id=' + data.id;
      } else {
        alert(data.message || 'Error creating work type');
      }
    });
}

function submitAddStage(e) {
  e.preventDefault();
  const formData = new FormData(document.getElementById('addStageForm'));
  formData.append('action', 'add_stage');

  fetch(APP_URL + '/api/workflow-api.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        window.location.reload();
      } else {
        alert(data.message || 'Error adding stage');
      }
    });
}

function deleteWorkType(id) {
  Swal.fire({
    title: 'Delete Pipeline Template?',
    text: 'CRITICAL WARNING: This will permanently remove this pipeline template along with all its steps/stages. This action CANNOT be undone.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#64748b',
    confirmButtonText: 'Yes, permanently delete'
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append('action', 'delete_work_type');
      formData.append('id', id);

      fetch(APP_URL + '/api/workflow-api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            window.location.href = 'workflow-builder.php';
          } else {
            Swal.fire('Error', data.message || 'Error deleting pipeline', 'error');
          }
        });
    }
  });
}
</script>

<script src="<?= APP_URL ?>/assets/js/workflow-builder.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
