<?php
$pageTitle = 'Company Profile Settings';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$db = getDB();

// Fetch current company settings
$company = $db->query("SELECT * FROM company_settings LIMIT 1")->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyName = sanitize($_POST['company_name'] ?? '');
    $companyEmail = sanitize($_POST['company_email'] ?? '');
    $companyMobile = sanitize($_POST['company_mobile'] ?? '');
    $companyAddress = sanitize($_POST['company_address'] ?? '');

    // Handle logo upload
    $logoFileName = $company['company_logo'] ?? 'default-logo.png';
    if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['company_logo']['tmp_name'];
        $fileName = basename($_FILES['company_logo']['name']);
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed)) {
            $logoFileName = "LOGO_" . time() . "." . $ext;
            $destination = UPLOAD_DIR . $logoFileName;
            
            if (move_uploaded_file($fileTmp, $destination)) {
                // Delete previous logo if it wasn't the default logo
                if ($company['company_logo'] && $company['company_logo'] !== 'default-logo.png' && file_exists(UPLOAD_DIR . $company['company_logo'])) {
                    unlink(UPLOAD_DIR . $company['company_logo']);
                }
            } else {
                setFlashMessage('danger', 'Failed to save logo file.');
            }
        } else {
            setFlashMessage('danger', 'Invalid file type. Only JPG, PNG, and WebP are allowed.');
        }
    }

    // Update Company settings in DB
    $stmtUpdate = $db->prepare("
        UPDATE company_settings 
        SET company_name = :name, company_email = :email, company_mobile = :mobile, company_address = :address, company_logo = :logo 
        WHERE id = :id
    ");
    $stmtUpdate->execute([
        'name' => $companyName,
        'email' => $companyEmail,
        'mobile' => $companyMobile,
        'address' => $companyAddress,
        'logo' => $logoFileName,
        'id' => $company['id']
    ]);

    setFlashMessage('success', 'Company profile and logo updated successfully!');
    header("Location: company-profile.php");
    exit;
}

?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card border-0 shadow-lg p-4" style="border-radius: var(--radius-lg);">
      <div class="border-bottom pb-3 mb-4 d-flex justify-content-between align-items-center">
        <div>
          <h4 class="fw-bold mb-1 text-dark"><i class="fas fa-building text-primary me-2"></i> Company Profile Setup</h4>
          <p class="text-muted small mb-0">Configure your organization branding, logo, contact info and details</p>
        </div>
        
        <div class="p-2 border rounded bg-light">
          <?php if (!empty($company['company_logo']) && $company['company_logo'] !== 'default-logo.png' && file_exists(UPLOAD_DIR . $company['company_logo'])): ?>
            <img src="<?= APP_URL ?>/uploads/<?= htmlspecialchars($company['company_logo']) ?>" class="img-fluid" style="max-height: 50px; object-fit: contain;">
          <?php else: ?>
            <span class="fw-bold text-primary"><i class="fas fa-folder-tree me-1"></i> <?= htmlspecialchars($company['company_name'] ?? 'File CRM') ?></span>
          <?php endif; ?>
        </div>
      </div>

      <form action="company-profile.php" method="POST" enctype="multipart/form-data">
        
        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label fw-bold">Company Name</label>
            <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($company['company_name'] ?? '') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold">Upload Company Logo</label>
            <input type="file" name="company_logo" class="form-control" accept="image/*">
            <small class="text-muted">Recommended: Landscape orientation PNG with transparent background</small>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold">Official Email</label>
            <input type="email" name="company_email" class="form-control" value="<?= htmlspecialchars($company['company_email'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold">Support Mobile Number</label>
            <input type="text" name="company_mobile" class="form-control" value="<?= htmlspecialchars($company['company_mobile'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label fw-bold">Company Address</label>
            <textarea name="company_address" class="form-control" rows="3"><?= htmlspecialchars($company['company_address'] ?? '') ?></textarea>
          </div>
        </div>

        <div class="pt-3 border-top d-flex justify-content-between align-items-center">
          <a href="<?= APP_URL ?>/admin/dashboard.php" class="btn btn-light border text-muted">Back to Dashboard</a>
          <button type="submit" class="btn btn-primary btn-lg fw-bold px-4 shadow-sm">
            <i class="fas fa-check-circle me-1"></i> Save Company Profile Settings
          </button>
        </div>

      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
