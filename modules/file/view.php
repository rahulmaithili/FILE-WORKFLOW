<?php
$pageTitle = 'File / Case Details';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$user = getLoggedInUser();
$fileId = intval($_GET['id'] ?? 0);

// Fetch File Record
$stmtFile = $db->prepare("
    SELECT f.*, wt.name as work_type_name, ws.stage_name, ws.stage_order, ws.sla_hours,
           u_assign.name as assigned_user_name, u_creator.name as creator_name
    FROM files f 
    LEFT JOIN work_types wt ON f.work_type_id = wt.id 
    LEFT JOIN workflow_stages ws ON f.current_stage_id = ws.id 
    LEFT JOIN users u_assign ON f.current_assigned_user = u_assign.id 
    LEFT JOIN users u_creator ON f.created_by = u_creator.id 
    WHERE f.id = :id 
    LIMIT 1
");
$stmtFile->execute(['id' => $fileId]);
$file = $stmtFile->fetch();

if (!$file) {
    echo "<div class='alert alert-danger'>File case not found!</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Check isolation permissions
if (!canAccessFile($file)) {
    echo "<div class='alert alert-warning p-4 shadow-sm rounded'>
            <h5 class='fw-bold'><i class='fas fa-lock me-2 text-danger'></i> Restricted File Access</h5>
            <p>This file is currently assigned to <strong>" . htmlspecialchars($file['assigned_user_name']) . "</strong>. You do not have permission to view or edit this file.</p>
            <a href='" . APP_URL . "/employee/my-files.php' class='btn btn-primary btn-sm mt-2'>Back to My Files</a>
          </div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Fetch total stages for progress calculation
$stmtStages = $db->prepare("SELECT * FROM workflow_stages WHERE work_type_id = :wt ORDER BY stage_order ASC");
$stmtStages->execute(['wt' => $file['work_type_id']]);
$allStages = $stmtStages->fetchAll();
$totalStagesCount = count($allStages);
$currentStageOrder = $file['stage_order'] ?? 1;
$progressPercentage = $totalStagesCount > 0 ? round(($currentStageOrder / $totalStagesCount) * 100) : 0;

// Fetch documents
$stmtDocs = $db->prepare("
    SELECT d.*, u.name as uploader_name 
    FROM file_documents d 
    LEFT JOIN users u ON d.uploaded_by = u.id 
    WHERE d.file_id = :fid 
    ORDER BY d.id DESC
");
$stmtDocs->execute(['fid' => $fileId]);
$documents = $stmtDocs->fetchAll();

// Fetch required document types for this case's work type
$stmtReqDocs = $db->prepare("
    SELECT dt.* 
    FROM document_types dt
    JOIN work_type_required_docs wtrd ON dt.id = wtrd.document_type_id
    WHERE wtrd.work_type_id = :wt_id
    ORDER BY dt.name ASC
");
$stmtReqDocs->execute(['wt_id' => $file['work_type_id']]);
$requiredDocTypes = $stmtReqDocs->fetchAll();

// Map uploaded documents by their document_type_id for easy checklist lookup
$uploadedChecklist = [];
$additionalDocs = [];
foreach ($documents as $doc) {
    if (!empty($doc['document_type_id'])) {
        $uploadedChecklist[intval($doc['document_type_id'])] = $doc;
    } else {
        $additionalDocs[] = $doc;
    }
}

// Fetch timeline history
$stmtHistory = $db->prepare("
    SELECT h.*, u_from.name as from_name, u_to.name as to_name, ws.stage_name 
    FROM file_history h 
    LEFT JOIN users u_from ON h.from_user = u_from.id 
    LEFT JOIN users u_to ON h.to_user = u_to.id 
    LEFT JOIN workflow_stages ws ON h.stage_id = ws.id 
    WHERE h.file_id = :fid 
    ORDER BY h.id DESC
");
$stmtHistory->execute(['fid' => $fileId]);
$historyLogs = $stmtHistory->fetchAll();

// Handle new comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $comment = sanitize($_POST['comment'] ?? '');
    if (!empty($comment)) {
        $stmtComment = $db->prepare("
            INSERT INTO file_history (file_id, from_user, to_user, stage_id, action_type, remarks) 
            VALUES (:fid, :uid, :uid, :stage, 'comment', :rem)
        ");
        $stmtComment->execute([
            'fid' => $fileId,
            'uid' => $user['id'],
            'stage' => $file['current_stage_id'],
            'rem' => $comment
        ]);
        setFlashMessage('success', 'Comment added to case file history.');
        header("Location: view.php?id=" . $fileId);
        exit;
    }
}
?>

<!-- File Header Card -->
<div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: var(--radius-lg);">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <h3 class="fw-bold mb-0 text-primary"><?= htmlspecialchars($file['file_code']) ?></h3>
        <?= getStatusBadgeHtml($file['status']) ?>
        <?= getPriorityBadgeHtml($file['priority']) ?>
      </div>
      <p class="text-muted mb-0">
        Work Type: <strong><?= htmlspecialchars($file['work_type_name']) ?></strong> &bull; 
        Created: <strong><?= date('d M Y, h:i A', strtotime($file['created_at'])) ?></strong> by <?= htmlspecialchars($file['creator_name']) ?>
      </p>
    </div>

    <!-- Quick Action Buttons Toolbar -->
    <div class="d-flex flex-wrap gap-2">
      <a href="<?= APP_URL ?>/modules/file/forward.php?id=<?= $file['id'] ?>" class="btn btn-primary fw-bold shadow-sm">
        <i class="fas fa-paper-plane me-1"></i> Forward File
      </a>
      <a href="<?= APP_URL ?>/modules/file/document-upload.php?file_id=<?= $file['id'] ?>" class="btn btn-outline-secondary fw-semibold">
        <i class="fas fa-paperclip me-1"></i> Attach Document
      </a>
      <?php if (isFeatureEnabled('whatsapp')): ?>
        <a href="<?= APP_URL ?>/modules/whatsapp/send.php?file_id=<?= $file['id'] ?>" class="btn btn-outline-success fw-semibold">
          <i class="fab fa-whatsapp me-1"></i> WhatsApp
        </a>
      <?php endif; ?>

      <?php if (isFeatureEnabled('calling')): ?>
        <a href="<?= APP_URL ?>/modules/calling/call-handler.php?file_id=<?= $file['id'] ?>" class="btn btn-outline-info fw-semibold">
          <i class="fas fa-phone me-1"></i> Call Client
        </a>
      <?php endif; ?>
      <a href="<?= APP_URL ?>/modules/file/print-cover.php?id=<?= $file['id'] ?>" target="_blank" class="btn btn-outline-dark fw-semibold">
        <i class="fas fa-print me-1"></i> Print Cover
      </a>

      <?php if (in_array($_SESSION['user']['role_key'] ?? '', ['super_admin', 'admin'])): ?>
        <a href="<?= APP_URL ?>/modules/file/edit.php?id=<?= $file['id'] ?>" class="btn btn-outline-warning fw-semibold">
          <i class="fas fa-edit me-1"></i> Edit Case
        </a>
        <a href="<?= APP_URL ?>/modules/file/delete.php?id=<?= $file['id'] ?>" class="btn btn-outline-danger fw-semibold" data-confirm="This will permanently delete this case file along with all its uploaded documents and logs. This action CANNOT be undone." data-confirm-title="Delete Case File?" data-confirm-btn="Yes, Delete File">
          <i class="fas fa-trash-alt me-1"></i> Delete Case
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Stage Progress Bar -->
  <div class="mt-4 pt-3 border-top">
    <div class="d-flex justify-content-between align-items-center mb-1">
      <small class="fw-bold text-uppercase text-secondary">
        Workflow Stage: <span class="text-primary"><?= htmlspecialchars($file['stage_name'] ?? 'Intake Stage') ?></span> 
        (Step <?= $currentStageOrder ?> of <?= $totalStagesCount ?>)
      </small>
      <small class="fw-bold text-primary"><?= $progressPercentage ?>% Completed</small>
    </div>
    <div class="progress" style="height: 10px; border-radius: 6px;">
      <div class="progress-bar bg-gradient bg-primary" role="progressbar" style="width: <?= $progressPercentage ?>%" aria-valuenow="<?= $progressPercentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
    </div>
  </div>
</div>

<div class="row g-4">
  
  <!-- Left Side: Customer & Assigned Info + Documents -->
  <div class="col-lg-7">
    
    <!-- Customer Info Card -->
    <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: var(--radius-lg);">
      <h5 class="fw-bold mb-3 border-bottom pb-2"><i class="fas fa-user-circle text-primary me-2"></i> Customer Profile Details</h5>
      <div class="row g-3">
        <div class="col-md-6">
          <small class="text-muted d-block fw-bold">Customer Name</small>
          <span class="fs-6 fw-bold text-dark"><?= htmlspecialchars($file['customer_name']) ?></span>
        </div>
        <div class="col-md-6">
          <small class="text-muted d-block fw-bold">Mobile Phone</small>
          <span class="fs-6 fw-semibold text-dark"><i class="fas fa-phone text-success me-1"></i> <?= htmlspecialchars($file['customer_mobile']) ?></span>
        </div>
        <div class="col-md-6">
          <small class="text-muted d-block fw-bold">Email Address</small>
          <span class="fs-6 text-dark"><?= htmlspecialchars($file['customer_email'] ?: 'N/A') ?></span>
        </div>
        <div class="col-md-6">
          <small class="text-muted d-block fw-bold">Currently Assigned Employee</small>
          <span class="badge bg-primary-soft text-primary fs-6 fw-bold"><i class="fas fa-user me-1"></i> <?= htmlspecialchars($file['assigned_user_name'] ?? 'Unassigned') ?></span>
        </div>
        <div class="col-12">
          <small class="text-muted d-block fw-bold">Customer Address</small>
          <span class="text-dark small"><?= htmlspecialchars($file['customer_address'] ?: 'No address provided.') ?></span>
        </div>
      </div>
    </div>

    <!-- Required Documents Checklist Section -->
    <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: var(--radius-lg);">
      <div class="border-bottom pb-2 mb-3">
        <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-tasks text-primary me-2"></i> Required Documents Checklist</h5>
        <small class="text-muted">Documents required specifically for the "<?= htmlspecialchars($file['work_type_name']) ?>" pipeline</small>
      </div>

      <?php if (empty($requiredDocTypes)): ?>
        <p class="text-muted small my-2"><i class="fas fa-check-circle text-success me-1"></i> No required documents defined for this pipeline.</p>
      <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($requiredDocTypes as $docType): 
            $isUploaded = isset($uploadedChecklist[$docType['id']]);
            $doc = $isUploaded ? $uploadedChecklist[$docType['id']] : null;
          ?>
            <div class="list-group-item d-flex flex-column flex-md-row justify-content-between align-items-md-center px-0 py-3">
              <div class="d-flex align-items-center gap-3">
                <div class="avatar-circle <?= $isUploaded ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger' ?>" style="width: 36px; height: 36px; border: none;">
                  <i class="fas <?= $isUploaded ? 'fa-check' : 'fa-times' ?>"></i>
                </div>
                <div>
                  <div class="d-flex align-items-center gap-2">
                    <strong class="text-dark small"><?= htmlspecialchars($docType['name']) ?></strong>
                    <?php if ($docType['is_mandatory'] == 1): ?>
                      <span class="text-danger small font-monospace" style="font-size: 0.72rem;">*Required</span>
                    <?php endif; ?>
                  </div>
                  <?php if ($isUploaded): ?>
                    <small class="text-muted d-block" style="font-size: 0.75rem;">
                      Uploaded by <?= htmlspecialchars($doc['uploader_name'] ?? 'System') ?> &bull; <?= timeAgo($doc['uploaded_at']) ?>
                    </small>
                  <?php else: ?>
                    <small class="text-danger-soft text-danger d-block" style="font-size: 0.75rem;">
                      <i class="fas fa-exclamation-triangle me-1"></i> Missing Document
                    </small>
                  <?php endif; ?>
                </div>
              </div>

              <div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
                <?php if ($isUploaded): ?>
                  <button type="button" onclick="previewDocument('<?= APP_URL ?>/serve.php?file=<?= htmlspecialchars($doc['file_path']) ?>', '<?= htmlspecialchars($doc['document_name']) ?>')" class="btn btn-sm btn-light border text-primary" title="Preview File">
                    <i class="fas fa-eye me-1"></i> View
                  </button>
                <?php else: ?>
                  <a href="<?= APP_URL ?>/modules/file/document-upload.php?file_id=<?= $file['id'] ?>&document_type_id=<?= $docType['id'] ?>" class="btn btn-sm btn-outline-danger fw-bold">
                    <i class="fas fa-camera-retro me-1"></i> Scan / Upload
                  </a>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Additional / Other Documents Section -->
    <div class="card border-0 shadow-sm p-4" style="border-radius: var(--radius-lg);">
      <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
        <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-folder-open text-warning me-2"></i> Additional Uploads (<?= count($additionalDocs) ?>)</h5>
        <a href="<?= APP_URL ?>/modules/file/document-upload.php?file_id=<?= $file['id'] ?>" class="btn btn-sm btn-outline-primary">
          <i class="fas fa-plus me-1"></i> Attach Other Document
        </a>
      </div>

      <?php if (empty($additionalDocs)): ?>
        <p class="text-muted small my-3">No additional documents attached to this case file.</p>
      <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($additionalDocs as $doc): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2.5">
              <div class="d-flex align-items-center gap-3">
                <div class="avatar-circle bg-light text-primary" style="width: 36px; height: 36px; border: none;">
                  <i class="fas fa-file-alt"></i>
                </div>
                <div>
                  <a href="javascript:void(0)" onclick="previewDocument('<?= APP_URL ?>/serve.php?file=<?= htmlspecialchars($doc['file_path']) ?>', '<?= htmlspecialchars($doc['document_name']) ?>')" class="fw-bold text-dark text-decoration-none small">
                    <?= htmlspecialchars($doc['document_name']) ?>
                  </a>
                  <small class="text-muted d-block" style="font-size: 0.75rem;">
                    Uploaded by <?= htmlspecialchars($doc['uploader_name'] ?? 'System') ?> &bull; <?= timeAgo($doc['uploaded_at']) ?>
                  </small>
                </div>
              </div>
              <button type="button" onclick="previewDocument('<?= APP_URL ?>/serve.php?file=<?= htmlspecialchars($doc['file_path']) ?>', '<?= htmlspecialchars($doc['document_name']) ?>')" class="btn btn-sm btn-light border text-primary" title="Preview File">
                <i class="fas fa-eye me-1"></i> View
              </button>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- Right Side: Workflow Timeline Audit Log & Comments -->
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm p-4 h-100" style="border-radius: var(--radius-lg);">
      <h5 class="fw-bold mb-4 border-bottom pb-2"><i class="fas fa-history text-info me-2"></i> Case History Timeline</h5>

      <!-- Post Comment / Remark Form -->
      <form action="view.php?id=<?= $fileId ?>" method="POST" class="mb-4">
        <input type="hidden" name="add_comment" value="1">
        <div class="input-group">
          <input type="text" name="comment" class="form-control" placeholder="Add an internal remark or note..." required>
          <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i></button>
        </div>
      </form>

      <!-- Stepper Timeline List -->
      <div class="premium-timeline">
        <?php foreach ($historyLogs as $log): 
          $iconClass = match($log['action_type']) {
              'created' => 'fa-plus text-success',
              'forwarded' => 'fa-paper-plane text-primary',
              'comment' => 'fa-comment-alt text-warning',
              'edit', 'edited', 'FILE_EDIT_ADMIN' => 'fa-edit text-info',
              'call' => 'fa-phone-alt text-teal',
              'whatsapp' => 'fa-whatsapp text-success',
              default => 'fa-info-circle text-secondary'
          };
          $bgColor = match($log['action_type']) {
              'created' => 'border-success',
              'forwarded' => 'border-primary',
              'comment' => 'border-warning',
              'edit', 'edited', 'FILE_EDIT_ADMIN' => 'border-info',
              default => 'border-secondary'
          };
        ?>
          <div class="timeline-step-node">
            <div class="timeline-step-icon <?= $bgColor ?>">
              <i class="fas <?= $iconClass ?>"></i>
            </div>
            <div class="timeline-step-card">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <div class="d-flex align-items-center gap-2">
                  <div class="avatar-circle" style="width: 24px; height: 24px; font-size: 0.68rem; background-color: var(--primary-light); color: var(--primary-color);">
                    <?= strtoupper(substr($log['from_name'] ?? 'S', 0, 1)) ?>
                  </div>
                  <strong class="text-dark small"><?= htmlspecialchars($log['from_name'] ?? 'System') ?></strong>
                </div>
                <small class="text-muted" style="font-size: 0.7rem;" title="<?= date('d M Y, h:i A', strtotime($log['action_at'])) ?>">
                  <i class="far fa-clock me-1"></i> <?= timeAgo($log['action_at']) ?>
                </small>
              </div>
              
              <div class="small">
                <?php if ($log['action_type'] === 'forwarded'): ?>
                  <span class="text-secondary">File advanced to stage:</span> 
                  <span class="badge bg-primary-soft text-primary"><?= htmlspecialchars($log['stage_name'] ?? 'Next Step') ?></span>
                  <?php if (!empty($log['to_name'])): ?>
                    <span class="text-muted d-block small mt-1">
                      <i class="fas fa-user-check me-1"></i> Assigned Employee: <strong><?= htmlspecialchars($log['to_name']) ?></strong>
                    </span>
                  <?php endif; ?>
                <?php elseif ($log['action_type'] === 'created'): ?>
                  <span class="text-success fw-bold"><i class="fas fa-folder-plus me-1"></i> Case File Initialized</span>
                <?php elseif ($log['action_type'] === 'comment'): ?>
                  <span class="text-muted small"><i class="far fa-comment-dots me-1"></i> Internal Remark Posted:</span>
                <?php else: ?>
                  <span class="text-muted small"><?= ucfirst(str_replace('_', ' ', htmlspecialchars($log['action_type']))) ?></span>
                <?php endif; ?>
              </div>

              <?php if (!empty($log['remarks'])): ?>
                <div class="p-2 bg-light rounded mt-2 small text-secondary border-start border-3" style="font-style: italic;">
                  "<?= htmlspecialchars($log['remarks']) ?>"
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

    </div>
  </div>

</div>

<!-- Modal: Premium Document In-App Preview & Print -->
<div class="modal fade" id="documentPreviewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-lg);">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title fw-bold" id="previewModalLabel">Document Preview</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 text-center bg-light" style="min-height: 250px;">
        <div id="previewContentContainer" class="d-flex justify-content-center align-items-center w-100 h-100">
          <!-- Dynamically loaded preview content -->
        </div>
      </div>
      <div class="modal-footer bg-light">
        <button type="button" class="btn btn-secondary fw-semibold" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-success fw-bold px-4" id="printPreviewBtn">
          <i class="fas fa-print me-1"></i> Print Document
        </button>
      </div>
    </div>
  </div>
</div>

<script>
function previewDocument(filePath, docName) {
  const modal = new bootstrap.Modal(document.getElementById('documentPreviewModal'));
  const container = document.getElementById('previewContentContainer');
  const printBtn = document.getElementById('printPreviewBtn');
  document.getElementById('previewModalLabel').textContent = docName;

  const ext = filePath.split('.').pop().toLowerCase();
  
  if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
    container.innerHTML = `<img src="${filePath}" id="printableDocImage" class="img-fluid rounded border shadow-sm" style="max-height: 500px; object-fit: contain; width: 100%;">`;
    printBtn.style.display = 'block';
    printBtn.onclick = () => {
      printImage(filePath);
    };
  } else if (ext === 'pdf') {
    container.innerHTML = `<iframe src="${filePath}" id="printableDocFrame" style="width:100%; height:520px; border:none; border-radius: 8px;"></iframe>`;
    printBtn.style.display = 'block';
    printBtn.onclick = () => {
      const frame = document.getElementById('printableDocFrame');
      if (frame) {
        frame.contentWindow.focus();
        frame.contentWindow.print();
      }
    };
  } else {
    container.innerHTML = `
      <div class="text-center py-5">
        <i class="fas fa-file-invoice fa-3x mb-3 text-secondary"></i>
        <h6>In-App Preview not supported for this file format (.${ext}).</h6>
        <p class="small text-muted mb-3">You can download the attachment directly to view it.</p>
        <a href="${filePath}" download class="btn btn-primary btn-sm px-4 fw-bold"><i class="fas fa-download me-1"></i> Download File</a>
      </div>
    `;
    printBtn.style.display = 'none';
    printBtn.onclick = null;
  }

  modal.show();
}

function printImage(imageSrc) {
  const win = window.open('', '_blank');
  win.document.write(`
    <html>
      <head>
        <title>Print Image Attachment</title>
        <style>
          body { margin: 0; display: flex; justify-content: center; align-items: center; height: 100vh; background: #fff; }
          img { max-width: 100%; max-height: 100%; object-fit: contain; }
          @media print {
            body { margin: 0; }
            img { max-width: 100%; max-height: 100%; page-break-inside: avoid; }
          }
        </style>
      </head>
      <body onload="window.print(); window.close();">
        <img src="${imageSrc}">
      </body>
    </html>
  `);
  win.document.close();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
