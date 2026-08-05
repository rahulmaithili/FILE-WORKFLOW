<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$error = '';
$success = '';
$step = isset($_SESSION['reset_user_id']) ? 2 : 1;

$db = getDB();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // STEP 1: Verify Email and Mobile
    if (isset($_POST['verify_identity'])) {
        $email = sanitize($_POST['email'] ?? '');
        $mobile = sanitize($_POST['mobile'] ?? '');

        if (empty($email) || empty($mobile)) {
            $error = "Please enter both registered Email and Mobile Number.";
        } else {
            $stmt = $db->prepare("SELECT * FROM users WHERE email = :email AND mobile = :mobile AND status = 'active' LIMIT 1");
            $stmt->execute(['email' => $email, 'mobile' => $mobile]);
            $userFound = $stmt->fetch();

            if ($userFound) {
                $_SESSION['reset_user_id'] = $userFound['id'];
                header("Location: forgot-password.php");
                exit;
            } else {
                $error = "No active user account found matching these details.";
            }
        }
    }
    
    // STEP 2: Save New Password
    elseif (isset($_POST['reset_password'])) {
        if (!isset($_SESSION['reset_user_id'])) {
            header("Location: forgot-password.php");
            exit;
        }

        $userId = $_SESSION['reset_user_id'];
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($newPass) || empty($confirmPass)) {
            $error = "Please fill in all password fields.";
        } elseif (strlen($newPass) < 6) {
            $error = "Password must be at least 6 characters long.";
        } elseif ($newPass !== $confirmPass) {
            $error = "Passwords do not match.";
        } else {
            // Update Password
            $newHashed = password_hash($newPass, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET password = :pass WHERE id = :id");
            $stmt->execute(['pass' => $newHashed, 'id' => $userId]);

            // Clean session flag
            unset($_SESSION['reset_user_id']);

            logActivity($userId, 'PASSWORD_FORGOT_RESET', 'Successfully reset account password via verification form.');

            header("Location: index.php?success=Password+reset+successfully.+Please+login+with+your+new+password.");
            exit;
        }
    }
}

// Cancel reset flow
if (isset($_GET['action']) && $_GET['action'] === 'cancel') {
    unset($_SESSION['reset_user_id']);
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password - <?= APP_NAME ?></title>
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- FontAwesome 6 -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --bg-body: #f8fafc;
      --bg-card: #ffffff;
      --primary-color: #3b82f6;
      --primary-hover: #2563eb;
      --text-main: #1e293b;
      --text-muted: #64748b;
      --border-color: #e2e8f0;
      --radius-lg: 16px;
    }
    body {
      background-color: var(--bg-body);
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: var(--text-main);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .forgot-card {
      background-color: var(--bg-card);
      border: 1px solid var(--border-color);
      border-radius: var(--radius-lg);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
      width: 100%;
      max-width: 440px;
      padding: 2.5rem;
    }
  </style>
</head>
<body>

<div class="forgot-card">
  
  <div class="text-center mb-4">
    <div class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle mb-3" style="width: 56px; height: 56px; font-size: 1.5rem; box-shadow: 0 4px 14px rgba(59, 130, 246, 0.4);">
      <i class="fas <?= $step === 1 ? 'fa-key' : 'fa-lock-open' ?>"></i>
    </div>
    <h4 class="fw-bold mb-1">Reset Password</h4>
    <p class="text-muted small">
      <?= $step === 1 ? 'Verify account credentials to recover access' : 'Enter your new secure account password' ?>
    </p>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 px-3 small mb-3">
      <i class="fas fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <?php if ($step === 1): ?>
    <!-- STEP 1: Verify email & mobile -->
    <form action="forgot-password.php" method="POST">
      <input type="hidden" name="verify_identity" value="1">
      
      <div class="mb-3">
        <label class="form-label small fw-bold">Registered Email Address</label>
        <div class="input-group">
          <span class="input-group-text bg-transparent border-end-0"><i class="fas fa-envelope text-muted"></i></span>
          <input type="email" name="email" class="form-control border-start-0" placeholder="e.g. admin@office.com" required autocomplete="email">
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label small fw-bold">Registered Mobile Number</label>
        <div class="input-group">
          <span class="input-group-text bg-transparent border-end-0"><i class="fas fa-phone text-muted"></i></span>
          <input type="text" name="mobile" class="form-control border-start-0" placeholder="e.g. 9876543210" required autocomplete="tel">
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-100 fw-bold py-2.5 shadow-sm">
        Verify Account Identity
      </button>
      
      <div class="text-center mt-3">
        <a href="index.php" class="text-decoration-none small text-muted"><i class="fas fa-arrow-left me-1"></i> Return to Login</a>
      </div>
    </form>

  <?php else: ?>
    <!-- STEP 2: Write new password -->
    <form action="forgot-password.php" method="POST">
      <input type="hidden" name="reset_password" value="1">
      
      <div class="mb-3">
        <label class="form-label small fw-bold">New Account Password</label>
        <input type="password" name="new_password" class="form-control form-control-lg" placeholder="••••••••" required minlength="6" autofocus>
      </div>

      <div class="mb-4">
        <label class="form-label small fw-bold">Confirm New Password</label>
        <input type="password" name="confirm_password" class="form-control form-control-lg" placeholder="••••••••" required minlength="6">
      </div>

      <button type="submit" class="btn btn-success w-100 fw-bold py-2.5 shadow-sm">
        Save & Reset Password
      </button>
      
      <div class="text-center mt-3">
        <a href="forgot-password.php?action=cancel" class="text-decoration-none small text-danger"><i class="fas fa-times me-1"></i> Cancel Reset</a>
      </div>
    </form>
  <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
