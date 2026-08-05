<?php
$pageTitle = 'WhatsApp Notification Dispatcher';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$user = getLoggedInUser();
$fileId = intval($_GET['file_id'] ?? 0);

$stmtFile = $db->prepare("SELECT f.*, wt.name as work_type_name FROM files f JOIN work_types wt ON f.work_type_id = wt.id WHERE f.id = :id LIMIT 1");
$stmtFile->execute(['id' => $fileId]);
$file = $stmtFile->fetch();

if (!$file) {
    echo "<div class='alert alert-danger'>File not found!</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Clean phone number (ensure country code prefix e.g. 91)
$cleanMobile = preg_replace('/[^0-9]/', '', $file['customer_mobile']);
if (strlen($cleanMobile) === 10) {
    $cleanMobile = '91' . $cleanMobile;
}

// Default Message Templates
$defaultMsg = "Namaste {$file['customer_name']} ji, your case file ({$file['file_code']}) for {$file['work_type_name']} is currently in progress. Status: " . ucfirst($file['status']) . ". Thank you!";
if ($file['status'] === 'completed') {
    $defaultMsg = "Namaste {$file['customer_name']} ji, your case file ({$file['file_code']}) for {$file['work_type_name']} has been FULLY COMPLETED successfully! Thank you for choosing our service.";
}

// Handle Form Dispatch Log
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = sanitize($_POST['message'] ?? '');
    
    // Log into DB
    $stmtLog = $db->prepare("
        INSERT INTO whatsapp_logs (file_id, sent_to, template_used, message, status, sent_by) 
        VALUES (:fid, :to, 'custom', :msg, 'sent', :uid)
    ");
    $stmtLog->execute([
        'fid' => $fileId,
        'to' => $cleanMobile,
        'msg' => $message,
        'uid' => $user['id']
    ]);

    // Open WhatsApp Web Link directly
    $waUrl = "https://wa.me/{$cleanMobile}?text=" . urlencode($message);
    header("Location: " . $waUrl);
    exit;
}
?>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="card border-0 shadow-lg p-4" style="border-radius: var(--radius-lg);">
      <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
        <div>
          <h4 class="fw-bold mb-1 text-success"><i class="fab fa-whatsapp me-2"></i> WhatsApp Customer Dispatcher</h4>
          <span class="fw-bold text-primary"><?= htmlspecialchars($file['file_code']) ?></span> &bull; To: <strong><?= htmlspecialchars($file['customer_name']) ?></strong> (<?= htmlspecialchars($file['customer_mobile']) ?>)
        </div>
      </div>

      <form action="send.php?file_id=<?= $fileId ?>" method="POST" target="_blank">
        
        <div class="mb-3">
          <label class="form-label fw-bold">Select Quick Message Template</label>
          <select class="form-select" onchange="document.getElementById('waMsgBox').value = this.value">
            <option value="<?= htmlspecialchars($defaultMsg) ?>">Status Update Template</option>
            <option value="Namaste <?= htmlspecialchars($file['customer_name']) ?> ji, your case file <?= htmlspecialchars($file['file_code']) ?> requires additional document verification. Please provide your ID proof.">Document Required Template</option>
            <option value="Namaste <?= htmlspecialchars($file['customer_name']) ?> ji, your connection work for <?= htmlspecialchars($file['file_code']) ?> is completed! Thank you.">Completion Template</option>
          </select>
        </div>

        <div class="mb-4">
          <label class="form-label fw-bold">WhatsApp Message Content</label>
          <textarea name="message" id="waMsgBox" class="form-control" rows="4" required><?= htmlspecialchars($defaultMsg) ?></textarea>
          <small class="text-muted mt-1"><i class="fas fa-info-circle me-1"></i> Clicking "Dispatch via WhatsApp" will open WhatsApp Web/App and log this interaction in the CRM audit trail.</small>
        </div>

        <div class="d-flex justify-content-between align-items-center border-top pt-3">
          <a href="<?= APP_URL ?>/modules/file/view.php?id=<?= $fileId ?>" class="btn btn-light border text-muted">Back to File</a>
          <button type="submit" class="btn btn-success btn-lg fw-bold px-4 shadow-sm">
            <i class="fab fa-whatsapp me-1"></i> Dispatch via WhatsApp
          </button>
        </div>

      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
