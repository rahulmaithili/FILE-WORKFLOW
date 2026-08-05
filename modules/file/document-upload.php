<?php
$pageTitle = 'Upload / Scan Document';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$user = getLoggedInUser();
$fileId = intval($_GET['file_id'] ?? $_POST['file_id'] ?? 0);
$docTypeId = intval($_GET['document_type_id'] ?? $_POST['document_type_id'] ?? 0);

$stmtFile = $db->prepare("SELECT * FROM files WHERE id = :id LIMIT 1");
$stmtFile->execute(['id' => $fileId]);
$file = $stmtFile->fetch();

if (!$file) {
    echo "<div class='alert alert-danger'>File not found!</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$docType = null;
if ($docTypeId > 0) {
    $stmtDt = $db->prepare("SELECT * FROM document_types WHERE id = :id LIMIT 1");
    $stmtDt->execute(['id' => $docTypeId]);
    $docType = $stmtDt->fetch();
}

// Handle File Upload or Camera Scan Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $docName = sanitize($_POST['document_name'] ?? '');

    // Check if Base64 camera scan was submitted
    if (!empty($_POST['scanned_image_base64'])) {
        $base64Data = $_POST['scanned_image_base64'];
        $base64Data = str_replace('data:image/jpeg;base64,', '', $base64Data);
        $base64Data = str_replace(' ', '+', $base64Data);
        $decodedImage = base64_decode($base64Data);

        $newFileName = "SCAN_" . $fileId . "_" . time() . ".jpg";
        $destination = DOC_UPLOAD_DIR . $newFileName;

        if (file_put_contents($destination, $decodedImage)) {
            $stmtDoc = $db->prepare("INSERT INTO file_documents (file_id, document_name, file_path, uploaded_by, document_type_id) VALUES (:fid, :dname, :fpath, :uby, :doc_type_id)");
            $stmtDoc->execute([
                'fid' => $fileId,
                'dname' => $docName ?: ($docType ? $docType['name'] : ('Camera Scan - ' . date('d M Y H:i'))),
                'fpath' => 'uploads/documents/' . $newFileName,
                'uby' => $user['id'],
                'doc_type_id' => $docTypeId > 0 ? $docTypeId : null
            ]);
            setFlashMessage('success', 'Scanned document uploaded successfully!');
            header("Location: view.php?id=" . $fileId);
            exit;
        }
    } 
    // Check standard file upload
    elseif (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['document_file']['tmp_name'];
        $fileName = basename($_FILES['document_file']['name']);
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $newFileName = "DOC_" . $fileId . "_" . time() . "." . $ext;
        $destination = DOC_UPLOAD_DIR . $newFileName;

        if (move_uploaded_file($fileTmp, $destination)) {
            $stmtDoc = $db->prepare("INSERT INTO file_documents (file_id, document_name, file_path, uploaded_by, document_type_id) VALUES (:fid, :dname, :fpath, :uby, :doc_type_id)");
            $stmtDoc->execute([
                'fid' => $fileId,
                'dname' => $docName ?: ($docType ? $docType['name'] : $fileName),
                'fpath' => 'uploads/documents/' . $newFileName,
                'uby' => $user['id'],
                'doc_type_id' => $docTypeId > 0 ? $docTypeId : null
            ]);
            setFlashMessage('success', 'Document attached successfully!');
            header("Location: view.php?id=" . $fileId);
            exit;
        }
    } else {
        setFlashMessage('danger', 'Please choose a document file or take a camera scan.');
    }
}
?>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="card border-0 shadow-lg p-4" style="border-radius: var(--radius-lg);">
      <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
        <div>
          <h4 class="fw-bold mb-1 text-dark">
            <i class="fas fa-paperclip text-primary me-2"></i> 
            <?= $docType ? ('Scan / Upload: ' . htmlspecialchars($docType['name'])) : 'Attach Document / Scan' ?>
          </h4>
          <span class="fw-bold text-primary"><?= htmlspecialchars($file['file_code']) ?></span> &bull; Customer: <strong><?= htmlspecialchars($file['customer_name']) ?></strong>
        </div>
      </div>

      <form action="document-upload.php?file_id=<?= $fileId ?>&document_type_id=<?= $docTypeId ?>" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="scanned_image_base64" id="scannedImageBase64">
        <input type="hidden" name="file_id" value="<?= $fileId ?>">
        <input type="hidden" name="document_type_id" value="<?= $docTypeId ?>">

        <div class="mb-3">
          <label class="form-label fw-bold">Document Title / Name</label>
          <input type="text" name="document_name" class="form-control" value="<?= $docType ? htmlspecialchars($docType['name']) : '' ?>" placeholder="e.g. Verified Aadhaar Card / Site Photo">
        </div>

        <!-- Option 1: File Upload -->
        <div class="card p-3 bg-light border mb-4">
          <h6 class="fw-bold text-dark mb-2"><i class="fas fa-upload text-primary me-2"></i> Upload File Attachment</h6>
          <input type="file" name="document_file" class="form-control" accept="image/*,.pdf,.doc,.docx">
          <small class="text-muted mt-1">Supported formats: Images (JPG, PNG), PDF, Word Docs</small>
        </div>

        <!-- Option 2: Live Web Camera Scanner -->
        <?php if (isFeatureEnabled('scanner')): ?>
          <div class="card p-3 bg-primary-soft border border-primary mb-4 text-center">
            <h6 class="fw-bold text-primary mb-2"><i class="fas fa-camera me-2"></i> Or Use Integrated Document Camera Scanner</h6>
            <p class="small text-muted mb-3">Snap a photo directly from your mobile camera or desktop webcam</p>
            <button type="button" class="btn btn-primary fw-bold btn-sm mx-auto shadow-sm" onclick="startCameraScanner()">
              <i class="fas fa-video me-1"></i> Open Live Camera Scanner
            </button>
            
            <img id="scannerPreviewImg" class="img-fluid rounded border mt-3 d-none shadow-sm" style="max-height: 250px;">
          </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center border-top pt-3">
          <a href="view.php?id=<?= $fileId ?>" class="btn btn-light border text-muted">Cancel</a>
          <button type="submit" class="btn btn-primary btn-lg fw-bold px-4">Save Document Attachment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Live Camera Scanner -->
<?php if (isFeatureEnabled('scanner')): ?>
<div class="modal fade" id="cameraScannerModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-lg);">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title fw-bold"><i class="fas fa-camera me-2 text-warning"></i> Live Camera Scanner</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="stopCamera()"></button>
      </div>
      <div class="modal-body p-3 text-center bg-black">
        <video id="scannerVideo" autoplay playsinline style="width: 100%; max-height: 350px; border-radius: 8px;"></video>
        <canvas id="scannerCanvas" style="display: none;"></canvas>
      </div>
      <div class="modal-footer bg-dark justify-content-center">
        <button type="button" class="btn btn-danger btn-sm me-2" data-bs-dismiss="modal" onclick="stopCamera()">Close</button>
        <button type="button" class="btn btn-warning btn-lg fw-bold" onclick="captureDocumentPhoto()">
          <i class="fas fa-circle me-1"></i> Capture Document Photo
        </button>
      </div>
    </div>
  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/scanner.js"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
