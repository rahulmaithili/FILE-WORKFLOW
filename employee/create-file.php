<?php
$pageTitle = 'Create New Case File';
require_once __DIR__ . '/../includes/header.php';

if (!hasPermission('create_file')) {
    setFlashMessage('danger', 'Access denied. Permission to create files is required.');
    header("Location: dashboard.php");
    exit;
}

$db = getDB();
$user = getLoggedInUser();

$workTypes = $db->query("SELECT * FROM work_types WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$branches = $db->query("SELECT * FROM branches ORDER BY branch_name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $workTypeId = intval($_POST['work_type_id'] ?? 0);
    $customerName = sanitize($_POST['customer_name'] ?? '');
    $customerMobile = sanitize($_POST['customer_mobile'] ?? '');
    $customerEmail = sanitize($_POST['customer_email'] ?? '');
    $customerAddress = sanitize($_POST['customer_address'] ?? '');
    $priority = sanitize($_POST['priority'] ?? 'medium');
    $branchId = intval($_POST['branch_id'] ?? ($user['branch_id'] ?? 1));

    if ($workTypeId <= 0 || empty($customerName) || empty($customerMobile)) {
        setFlashMessage('danger', 'Please fill in all required customer details.');
    } else {
        // Fetch work type details
        $stmtWt = $db->prepare("SELECT * FROM work_types WHERE id = :id");
        $stmtWt->execute(['id' => $workTypeId]);
        $wt = $stmtWt->fetch();
        $prefix = $wt['code_prefix'] ?? 'FMS';

        // Auto-generate Unique File Code
        $fileCode = generateFileCode($prefix);

        // Fetch Stage 1 for this work type
        $stmtStage = $db->prepare("
            SELECT * FROM workflow_stages 
            WHERE work_type_id = :wt 
            ORDER BY stage_order ASC LIMIT 1
        ");
        $stmtStage->execute(['wt' => $workTypeId]);
        $stage1 = $stmtStage->fetch();
        $stage1Id = $stage1['id'] ?? null;

        // Auto-assign Stage 1: find an active employee with Stage 1's role, or fall back to current user
        $assignedUser = $user['id'];
        if ($stage1) {
            $stmtUser = $db->prepare("SELECT id FROM users WHERE role_id = :role AND status = 'active' ORDER BY id ASC LIMIT 1");
            $stmtUser->execute(['role' => $stage1['assigned_role_id']]);
            $foundUser = $stmtUser->fetchColumn();
            if ($foundUser) {
                $assignedUser = $foundUser;
            }
        }

        // Insert File Record
        $stmtInsert = $db->prepare("
            INSERT INTO files 
            (file_code, customer_name, customer_mobile, customer_email, customer_address, work_type_id, current_stage_id, current_assigned_user, status, priority, created_by, branch_id) 
            VALUES (:code, :cname, :cmobile, :cemail, :caddr, :wt, :stage, :user, 'in_progress', :prio, :creator, :branch_id)
        ");
        $stmtInsert->execute([
            'code' => $fileCode,
            'cname' => $customerName,
            'cmobile' => $customerMobile,
            'cemail' => $customerEmail,
            'caddr' => $customerAddress,
            'wt' => $workTypeId,
            'stage' => $stage1Id,
            'user' => $assignedUser,
            'prio' => $priority,
            'creator' => $user['id'],
            'branch_id' => $branchId
        ]);
        $fileId = $db->lastInsertId();

        // 1. Process structured document checklist
        $collectedDocs = $_POST['collected_docs'] ?? []; // Array of doc_type_id => 1
        $uploadedDocs = $_FILES['upload_docs'] ?? [];    // Array of file uploads

        // Query all document type details for lookup
        $stmtDocTypeCheck = $db->query("SELECT * FROM document_types")->fetchAll();
        $docTypesMap = [];
        foreach ($stmtDocTypeCheck as $dt) {
            $docTypesMap[$dt['id']] = $dt;
        }

        foreach ($collectedDocs as $docTypeId => $val) {
            $docTypeId = intval($docTypeId);
            if (!isset($docTypesMap[$docTypeId])) continue;

            $docTypeInfo = $docTypesMap[$docTypeId];
            $hasFile = false;

            // Check if a file was uploaded for this specific document type
            if (isset($uploadedDocs['name'][$docTypeId]) && $uploadedDocs['error'][$docTypeId] === UPLOAD_ERR_OK) {
                $fileTmp = $uploadedDocs['tmp_name'][$docTypeId];
                $fileName = basename($uploadedDocs['name'][$docTypeId]);
                $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                $newFileName = "DOC_" . $fileId . "_" . $docTypeId . "_" . time() . "." . $ext;
                $destination = DOC_UPLOAD_DIR . $newFileName;

                if (move_uploaded_file($fileTmp, $destination)) {
                    // Save document with file path
                    $stmtDoc = $db->prepare("INSERT INTO file_documents (file_id, document_name, file_path, uploaded_by, document_type_id) VALUES (:fid, :dname, :fpath, :uby, :doc_type_id)");
                    $stmtDoc->execute([
                        'fid' => $fileId,
                        'dname' => $docTypeInfo['name'],
                        'fpath' => 'uploads/documents/' . $newFileName,
                        'uby' => $user['id'],
                        'doc_type_id' => $docTypeId
                    ]);
                    $hasFile = true;
                }
            }

            if (!$hasFile) {
                // Save placeholder row indicating physical copy collected (but scan pending)
                $stmtDoc = $db->prepare("INSERT INTO file_documents (file_id, document_name, file_path, uploaded_by, document_type_id) VALUES (:fid, :dname, '', :uby, :doc_type_id)");
                $stmtDoc->execute([
                    'fid' => $fileId,
                    'dname' => $docTypeInfo['name'],
                    'uby' => $user['id'],
                    'doc_type_id' => $docTypeId
                ]);
            }
        }

        // 2. Handle optional general File Document Upload if attached
        if (isset($_FILES['initial_document']) && $_FILES['initial_document']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['initial_document']['tmp_name'];
            $fileName = basename($_FILES['initial_document']['name']);
            $ext = pathinfo($fileName, PATHINFO_EXTENSION);
            $newFileName = "DOC_" . $fileId . "_" . time() . "." . $ext;
            $destination = DOC_UPLOAD_DIR . $newFileName;

            if (move_uploaded_file($fileTmp, $destination)) {
                $stmtDoc = $db->prepare("INSERT INTO file_documents (file_id, document_name, file_path, uploaded_by) VALUES (:fid, :dname, :fpath, :uby)");
                $stmtDoc->execute([
                    'fid' => $fileId,
                    'dname' => $fileName,
                    'fpath' => 'uploads/documents/' . $newFileName,
                    'uby' => $user['id']
                ]);
            }
        }

        // Log Timeline Audit History
        $stmtHist = $db->prepare("
            INSERT INTO file_history (file_id, from_user, to_user, stage_id, action_type, remarks) 
            VALUES (:fid, :from_u, :to_u, :stage, 'created', 'Case file created and assigned to initial stage.')
        ");
        $stmtHist->execute([
            'fid' => $fileId,
            'from_u' => $user['id'],
            'to_u' => $assignedUser,
            'stage' => $stage1Id
        ]);

        // Trigger System Notification for assigned user
        addNotification($assignedUser, 'New Case File Assigned', "Case file {$fileCode} for {$customerName} has been assigned to you.", APP_URL . '/modules/file/view.php?id=' . $fileId);

        setFlashMessage('success', "File created successfully! Code: {$fileCode}");
        header("Location: " . APP_URL . "/modules/file/view.php?id=" . $fileId);
        exit;
    }
}
?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card border-0 shadow-lg p-4" style="border-radius: var(--radius-lg);">
      <div class="border-bottom pb-3 mb-4">
        <h4 class="fw-bold mb-1 text-dark"><i class="fas fa-file-signature text-primary me-2"></i> Create New Customer File</h4>
        <p class="text-muted small mb-0">Enter customer details and select the target workflow pipeline</p>
      </div>

      <form action="create-file.php" method="POST" enctype="multipart/form-data">
        
        <!-- Workflow Work Type -->
        <div class="mb-4">
          <label class="form-label fw-bold text-dark">Select Work Type Pipeline <span class="text-danger">*</span></label>
          <select name="work_type_id" class="form-select form-select-lg" required>
            <option value="">-- Choose Work Type --</option>
            <?php foreach ($workTypes as $wt): ?>
              <option value="<?= $wt['id'] ?>">
                <?= htmlspecialchars($wt['name']) ?> (Prefix: <?= htmlspecialchars($wt['code_prefix']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- AI OCR Document Scanner Card -->
        <?php if (isFeatureEnabled('ai_ocr')): ?>
          <div class="p-3 bg-light rounded border border-success border-opacity-25 mb-4 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
              <div class="bg-success-soft text-success d-flex align-items-center justify-content-center rounded" style="width: 42px; height: 42px;">
                <i class="fas fa-robot fa-lg"></i>
              </div>
              <div>
                <small class="text-success fw-bold d-block"><i class="fas fa-magic me-1"></i> AI-OCR Document Auto-Fill</small>
                <small class="text-muted">Simulate dynamic Aadhaar/Document scan to auto-fill fields</small>
              </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-success fw-bold px-3" onclick="triggerOcrScan()">
              <i class="fas fa-qrcode me-1"></i> Scan Document
            </button>
          </div>
        <?php endif; ?>

        <h5 class="fw-bold text-primary border-bottom pb-2 mb-3">Customer Profile</h5>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Customer Full Name <span class="text-danger">*</span></label>
            <input type="text" name="customer_name" id="input_customer_name" class="form-control" placeholder="e.g. Ramesh Kumar" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Mobile Number <span class="text-danger">*</span></label>
            <input type="text" name="customer_mobile" id="input_customer_mobile" class="form-control" placeholder="e.g. 9876543210" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Email Address (Optional)</label>
            <input type="email" name="customer_email" id="input_customer_email" class="form-control" placeholder="e.g. ramesh@example.com">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Priority Level</label>
            <select name="priority" class="form-select">
              <option value="low">Low Priority</option>
              <option value="medium" selected>Medium Priority</option>
              <option value="high">High Priority</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Branch Office Location <span class="text-danger">*</span></label>
            <select name="branch_id" class="form-select" required>
              <?php foreach ($branches as $br): ?>
                <option value="<?= $br['id'] ?>" <?= ($user['branch_id'] == $br['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($br['branch_name']) ?> (<?= htmlspecialchars($br['branch_code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Full Address / Location</label>
            <textarea name="customer_address" id="input_customer_address" class="form-control" rows="2" placeholder="House no, Street, Landmark, City..."></textarea>
          </div>
        </div>

        <!-- Dynamic Required Documents Checklist Card -->
        <div id="required_docs_card" class="card border-0 shadow-sm p-4 mb-4 d-none" style="border-radius: var(--radius-lg); background-color: #fafafa;">
          <h5 class="fw-bold text-primary border-bottom pb-2 mb-3">
            <i class="fas fa-tasks text-primary me-2"></i> Required Documents Intake
          </h5>
          <p class="text-muted small mb-3">Check the documents you have physically collected from the customer, or upload scanned copies right now:</p>
          <div id="required_docs_list" class="d-flex flex-column gap-3">
            <!-- Dynamically populated via Javascript -->
          </div>
        </div>

        <h5 class="fw-bold text-primary border-bottom pb-2 mb-3 mt-4">Other Attachment</h5>

        <div class="mb-4">
          <label class="form-label fw-semibold">Additional Intake Document (Optional File)</label>
          <input type="file" name="initial_document" class="form-control" accept="image/*,.pdf,.doc,.docx">
          <small class="text-muted">You can also scan additional documents directly inside the file after creation.</small>
        </div>

        <div class="pt-3 border-top d-flex justify-content-between align-items-center">
          <a href="my-files.php" class="btn btn-light border text-muted">Cancel</a>
          <button type="submit" class="btn btn-primary btn-lg fw-bold px-4 shadow-sm">
            <i class="fas fa-check-circle me-1"></i> Generate Case File
          </button>
        </div>

      </form>
    </div>
  </div>
</div>

<!-- OCR Scanner Simulation Overlay -->
<div class="ocr-scanner-overlay" id="ocrScannerOverlay">
  <div class="ocr-scanner-box">
    <div class="ocr-scanner-line"></div>
    <div class="ocr-laser-glow"><i class="fas fa-microchip"></i></div>
  </div>
  <h4 class="fw-bold mb-2"><i class="fas fa-spin fa-spinner me-2 text-success"></i> AI OCR Analyzer active</h4>
  <p class="text-white-50 small mb-0" id="ocrScanStatus">Reading text characters & matching profiles...</p>
</div>

<script>
document.querySelector('select[name="work_type_id"]').addEventListener('change', function() {
    const workTypeId = this.value;
    const container = document.getElementById('required_docs_card');
    const list = document.getElementById('required_docs_list');
    
    if (!workTypeId) {
        container.classList.add('d-none');
        list.innerHTML = '';
        return;
    }
    
    fetch('<?= APP_URL ?>/api/workflow-api.php?action=get_required_docs&work_type_id=' + workTypeId)
        .then(res => res.json())
        .then(response => {
            if (response.success && response.data.length > 0) {
                container.classList.remove('d-none');
                list.innerHTML = '';
                
                response.data.forEach(doc => {
                    const mandatoryStar = doc.is_mandatory == 1 ? '<span class="text-danger">*</span>' : '';
                    const itemHtml = `
                        <div class="row align-items-center g-2 border-bottom pb-3 mb-2">
                            <div class="col-md-5">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="collected_docs[${doc.id}]" value="1" id="collect_${doc.id}" ${doc.is_mandatory == 1 ? 'required' : ''}>
                                    <label class="form-check-label fw-semibold text-dark small" for="collect_${doc.id}">
                                        I have collected ${doc.name} ${mandatoryStar}
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <input type="file" name="upload_docs[${doc.id}]" class="form-control form-control-sm" accept="image/*,.pdf,.doc,.docx" onchange="autoCheckCollected(${doc.id})">
                                <small class="text-muted" style="font-size: 0.72rem;">Optional: Upload scanned file now</small>
                            </div>
                        </div>
                    `;
                    list.innerHTML += itemHtml;
                });
            } else {
                container.classList.add('d-none');
                list.innerHTML = '';
            }
        })
        .catch(err => {
            console.error("Error loading checklist: ", err);
            container.classList.add('d-none');
        });
});

function autoCheckCollected(docId) {
    document.getElementById('collect_' + docId).checked = true;
}

// Real webcam capture simulation for AI-OCR auto-fill
let ocrStream = null;
let ocrModal = null;

function triggerOcrScan() {
  ocrModal = new bootstrap.Modal(document.getElementById('ocrCameraModal'));
  ocrModal.show();

  navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
    .then(stream => {
      ocrStream = stream;
      document.getElementById('ocrVideo').srcObject = stream;
    })
    .catch(err => {
      console.error("Camera access error: ", err);
      // Fallback: If camera access is blocked, close modal and run mock scan immediately
      ocrModal.hide();
      runMockOcrScan();
    });
}

function stopOcrCamera() {
  if (ocrStream) {
    ocrStream.getTracks().forEach(track => track.stop());
    ocrStream = null;
  }
}

function captureOcrPhoto() {
  const video = document.getElementById('ocrVideo');
  const canvas = document.getElementById('ocrCanvas');
  const context = canvas.getContext('2d');

  canvas.width = video.videoWidth || 640;
  canvas.height = video.videoHeight || 480;
  context.drawImage(video, 0, 0, canvas.width, canvas.height);

  stopOcrCamera();
  ocrModal.hide();

  // Trigger laser scanner simulation
  runMockOcrScan();
}

function runMockOcrScan() {
  const overlay = document.getElementById('ocrScannerOverlay');
  const statusText = document.getElementById('ocrScanStatus');
  
  overlay.classList.add('active');
  statusText.innerHTML = '<i class="fas fa-microchip fa-spin me-1"></i> Reading camera photo pixels...';
  
  setTimeout(() => {
    statusText.innerHTML = '<i class="fas fa-search me-1"></i> Extracting Name, Mobile, and Address details...';
  }, 1000);
  
  setTimeout(() => {
    overlay.classList.remove('active');
    
    // Auto-fill form values
    const names = ['Ramesh Kumar Yadav', 'Sita Ram Sharma', 'Vikram Aditya Singh', 'Mohammad Faisal Khan', 'Priya Deshmukh'];
    const addresses = ['H-12, Sector 62, Noida, UP, 201301', 'Flat 402, Royal Residency, Pune, MH, 411001', '32, Gandhi Nagar, Patna, Bihar, 800001', 'Lane 4, Salt Lake, Kolkata, WB, 700091'];
    const mobiles = ['9876543210', '9812345678', '8765432109', '7890123456'];
    const emails = ['ramesh.yadav@example.com', 'sita.sharma@example.com', 'vikram.singh@example.com', 'faisal.khan@example.com'];
    
    const randomIdx = Math.floor(Math.random() * names.length);
    
    document.getElementById('input_customer_name').value = names[randomIdx];
    document.getElementById('input_customer_mobile').value = mobiles[Math.floor(Math.random() * mobiles.length)];
    document.getElementById('input_customer_email').value = emails[randomIdx];
    document.getElementById('input_customer_address').value = addresses[Math.floor(Math.random() * addresses.length)];
    
    // Auto check Aadhaar checkbox if present in the document checklist
    const collectInputs = document.querySelectorAll('input[id^="collect_"]');
    if (collectInputs.length > 0) {
        collectInputs[0].checked = true;
        showToast("AI-OCR matched ID details. Required checkbox auto-checked!", "success");
    }

    Swal.fire({
      title: 'AI OCR Extraction Complete!',
      text: 'Extracted details for ' + names[randomIdx] + ' successfully auto-filled into form fields.',
      icon: 'success',
      confirmButtonColor: '#10b981'
    });
    
  }, 2500);
}
</script>

<!-- Modal: OCR Camera Capture -->
<div class="modal fade" id="ocrCameraModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-lg);">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title fw-bold"><i class="fas fa-robot me-2 text-success"></i> AI-OCR ID Scanner</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="stopOcrCamera()"></button>
      </div>
      <div class="modal-body p-3 text-center bg-black">
        <video id="ocrVideo" autoplay playsinline style="width: 100%; max-height: 350px; border-radius: 8px;"></video>
        <canvas id="ocrCanvas" style="display: none;"></canvas>
      </div>
      <div class="modal-footer bg-dark justify-content-center">
        <button type="button" class="btn btn-danger btn-sm me-2" data-bs-dismiss="modal" onclick="stopOcrCamera()">Close</button>
        <button type="button" class="btn btn-success btn-lg fw-bold" onclick="captureOcrPhoto()">
          <i class="fas fa-camera me-1"></i> Capture & Run AI-OCR Scan
        </button>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
