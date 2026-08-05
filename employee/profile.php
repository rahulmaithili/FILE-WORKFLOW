<?php
$pageTitle = 'My Profile Settings';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$user = getLoggedInUser();
$userId = $user['id'];

// Fetch current user full details
$stmt = $db->prepare("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = :id");
$stmt->execute(['id' => $userId]);
$userData = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Password change request
    if (isset($_POST['change_password'])) {
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (password_verify($oldPass, $userData['password'])) {
            if ($newPass === $confirmPass) {
                if (strlen($newPass) >= 6) {
                    $newHashed = password_hash($newPass, PASSWORD_BCRYPT);
                    $stmtUpdate = $db->prepare("UPDATE users SET password = :pass WHERE id = :id");
                    $stmtUpdate->execute(['pass' => $newHashed, 'id' => $userId]);
                    setFlashMessage('success', 'Password updated successfully.');
                } else {
                    setFlashMessage('danger', 'New password must be at least 6 characters.');
                }
            } else {
                setFlashMessage('danger', 'New password and confirm password do not match.');
            }
        } else {
            setFlashMessage('danger', 'Current password is incorrect.');
        }
    } 
    // 2. Profile Photo Upload request
    elseif (isset($_POST['upload_photo'])) {
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['profile_photo']['tmp_name'];
            $fileName = basename($_FILES['profile_photo']['name']);
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($ext, $allowed)) {
                $newFileName = "AVATAR_" . $userId . "_" . time() . "." . $ext;
                $destination = PROFILE_UPLOAD_DIR . $newFileName;

                if (move_uploaded_file($fileTmp, $destination)) {
                    // Update DB
                    $stmtPhoto = $db->prepare("UPDATE users SET profile_photo = :photo WHERE id = :id");
                    $stmtPhoto->execute(['photo' => $newFileName, 'id' => $userId]);
                    
                    // Update session
                    $_SESSION['user']['profile_photo'] = $newFileName;
                    
                    setFlashMessage('success', 'Profile photo updated successfully!');
                    header("Location: profile.php");
                    exit;
                } else {
                    setFlashMessage('danger', 'Failed to save upload photo file.');
                }
            } else {
                setFlashMessage('danger', 'Invalid file type. Only JPG, PNG, and WebP are allowed.');
            }
        } else {
            setFlashMessage('danger', 'Please choose a profile photo to upload.');
        }
    }
}
?>

<div class="row g-4">
  <!-- Profile Summary Card -->
  <div class="col-md-4">
    <div class="card border-0 shadow-sm p-4 text-center" style="border-radius: var(--radius-lg);">
      <div class="position-relative d-inline-block mx-auto mb-3">
        <?php if (!empty($userData['profile_photo']) && $userData['profile_photo'] !== 'default-avatar.png' && file_exists(PROFILE_UPLOAD_DIR . $userData['profile_photo'])): ?>
          <img src="<?= APP_URL ?>/serve.php?file=profiles/<?= htmlspecialchars($userData['profile_photo']) ?>" class="rounded-circle border border-primary p-1 shadow-sm" style="width: 130px; height: 130px; object-fit: cover;">
        <?php else: ?>
          <div class="rounded-circle bg-primary-soft text-primary d-flex align-items-center justify-content-center border border-primary p-1 mx-auto shadow-sm" style="width: 130px; height: 130px; font-size: 3.5rem; font-weight: 700;">
            <?= strtoupper(substr($userData['name'], 0, 1)) ?>
          </div>
        <?php endif; ?>
      </div>

      <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($userData['name']) ?></h5>
      <span class="badge bg-primary-soft text-primary border mb-3"><?= htmlspecialchars($userData['role_name']) ?></span>
      
      <p class="text-muted small mb-4">
        Email: <?= htmlspecialchars($userData['email']) ?><br>
        Mobile: <?= htmlspecialchars($userData['mobile']) ?>
      </p>

      <!-- Photo Upload Form -->
      <form action="profile.php" method="POST" enctype="multipart/form-data" class="border-top pt-3">
        <input type="hidden" name="upload_photo" value="1">
        <div class="mb-3 text-start">
          <label class="form-label fw-bold text-secondary small">Change Profile Photo</label>
          <input type="file" name="profile_photo" class="form-control form-control-sm" accept="image/*" required>
        </div>
        <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">Upload Photo</button>
      </form>
    </div>
  </div>

  <!-- Password Modification Form -->
  <div class="col-md-8">
    <div class="card border-0 shadow-sm p-4" style="border-radius: var(--radius-lg);">
      <h5 class="fw-bold text-dark border-bottom pb-2 mb-4"><i class="fas fa-key text-warning me-2"></i> Update Security Credentials</h5>

      <form action="profile.php" method="POST">
        <input type="hidden" name="change_password" value="1">

        <div class="mb-3">
          <label class="form-label fw-semibold text-secondary">Current Password</label>
          <input type="password" name="old_password" class="form-control" placeholder="••••••••" required>
        </div>

        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label fw-semibold text-secondary">New Password</label>
            <input type="password" name="new_password" class="form-control" placeholder="Minimum 6 characters" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold text-secondary">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>
          </div>
        </div>

        <button type="submit" class="btn btn-warning text-white fw-bold px-4">
          <i class="fas fa-save me-1"></i> Update Security Password
        </button>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
