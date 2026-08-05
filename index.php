<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// If already logged in, redirect based on role
if (isLoggedIn()) {
    $user = getLoggedInUser();
    if (in_array($user['role_key'], ['super_admin', 'admin'])) {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: employee/dashboard.php");
    }
    exit;
}

$errorMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $res = loginUser($email, $password);
    if ($res['success']) {
        $user = $res['user'];
        if (in_array($user['role_key'], ['super_admin', 'admin'])) {
            header("Location: admin/dashboard.php");
        } else {
            header("Location: employee/dashboard.php");
        }
        exit;
    } else {
        $errorMsg = $res['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Office File Management CRM</title>
  <!-- Google Fonts Outfit & Plus Jakarta Sans -->
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- FontAwesome 6 -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary-gradient: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%);
      --accent-color: #3b82f6;
      --font-title: 'Outfit', sans-serif;
      --font-body: 'Plus Jakarta Sans', sans-serif;
    }
    
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      background-color: #080c14;
      font-family: var(--font-body);
      min-height: 100vh;
      height: 100vh;
      overflow: hidden;
      color: #f8fafc;
    }

    .split-layout {
      display: flex;
      width: 100%;
      height: 100%;
    }

    /* Left Branding Section (50%) */
    .branding-section {
      width: 50%;
      height: 100%;
      background: radial-gradient(circle at 10% 20%, #1e1b4b 0%, #0f172a 100%);
      position: relative;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 4.5rem;
      overflow: hidden;
      border-right: 1px solid rgba(255, 255, 255, 0.05);
    }

    /* Ambient Glowing Spheres for Left Side */
    .branding-section::before {
      content: '';
      position: absolute;
      top: -20%;
      left: -20%;
      width: 500px;
      height: 500px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(99, 102, 241, 0.18) 0%, transparent 70%);
      filter: blur(50px);
      z-index: 1;
    }
    .branding-section::after {
      content: '';
      position: absolute;
      bottom: -20%;
      right: -20%;
      width: 500px;
      height: 500px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, transparent 70%);
      filter: blur(50px);
      z-index: 1;
    }

    .branding-content {
      position: relative;
      z-index: 5;
    }

    .brand-header {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 5rem;
    }

    .brand-logo-badge {
      width: 54px;
      height: 54px;
      border-radius: 16px;
      background: var(--primary-gradient);
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 8px 24px rgba(99, 102, 241, 0.35);
      font-size: 1.5rem;
      color: #ffffff;
    }

    .brand-name {
      font-family: var(--font-title);
      font-weight: 800;
      color: #ffffff;
      font-size: 1.5rem;
      letter-spacing: -0.5px;
      line-height: 1.2;
    }

    .brand-subtitle {
      color: #94a3b8;
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .branding-title {
      font-family: var(--font-title);
      font-weight: 800;
      font-size: 3rem;
      line-height: 1.15;
      color: #ffffff;
      margin-bottom: 1.5rem;
      letter-spacing: -1px;
    }

    .branding-desc {
      color: #cbd5e1;
      font-size: 1.05rem;
      line-height: 1.6;
      max-width: 540px;
    }

    .branding-footer {
      position: relative;
      z-index: 5;
      color: #64748b;
      font-size: 0.85rem;
      letter-spacing: 0.5px;
    }

    /* Right Form Section (50%) */
    .form-section {
      width: 50%;
      height: 100%;
      background-color: #0b0f19;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 4.5rem;
      position: relative;
      overflow-y: auto;
    }

    /* Ambient Glow for Form Side */
    .form-section::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 450px;
      height: 450px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(99, 102, 241, 0.08) 0%, transparent 70%);
      filter: blur(40px);
      z-index: 1;
    }

    .form-container {
      width: 100%;
      max-width: 440px;
      position: relative;
      z-index: 5;
    }

    .login-title {
      font-family: var(--font-title);
      font-weight: 800;
      font-size: 2.2rem;
      color: #ffffff;
      letter-spacing: -0.5px;
      margin-bottom: 0.5rem;
    }

    .login-subtitle {
      color: #cbd5e1;
      font-size: 0.95rem;
      margin-bottom: 2.5rem;
    }

    .form-label-custom {
      font-size: 0.78rem;
      font-weight: 700;
      color: #cbd5e1;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 0.6rem;
      display: block;
    }

    .input-group-custom {
      background: rgba(30, 41, 59, 0.7);
      border: 1px solid rgba(255, 255, 255, 0.12);
      border-radius: 14px;
      overflow: hidden;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      margin-bottom: 1.5rem;
      height: 54px;
    }

    .input-group-custom:focus-within {
      border-color: #3b82f6;
      box-shadow: 0 0 14px rgba(59, 130, 246, 0.3);
      background: rgba(30, 41, 59, 0.95);
    }

    .input-group-custom-icon {
      padding: 0 1.2rem;
      color: #94a3b8;
      font-size: 1.2rem;
      display: flex;
      align-items: center;
    }

    .input-group-custom input {
      background: transparent;
      border: none;
      color: #ffffff;
      padding: 0.85rem 1.2rem 0.85rem 0;
      width: 100%;
      font-size: 1rem;
      font-family: var(--font-body);
    }

    .input-group-custom input:focus {
      outline: none;
    }

    .input-group-custom input::placeholder {
      color: #64748b;
    }

    .btn-login {
      background: var(--primary-gradient);
      border: none;
      color: #ffffff;
      height: 54px;
      font-size: 1.05rem;
      font-weight: 700;
      border-radius: 14px;
      box-shadow: 0 8px 24px rgba(37, 99, 235, 0.35);
      transition: all 0.25s ease;
      font-family: var(--font-title);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      width: 100%;
      margin-top: 1rem;
    }

    .btn-login:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 30px rgba(37, 99, 235, 0.55);
      opacity: 0.95;
    }

    .btn-login:active {
      transform: translateY(0);
    }

    /* Premium Alert Styling with clear text */
    .alert-premium {
      background: rgba(239, 68, 68, 0.15);
      border: 1px solid rgba(239, 68, 68, 0.35);
      color: #fecaca;
      border-radius: 12px;
      padding: 1rem;
      font-size: 0.9rem;
      font-weight: 500;
      margin-bottom: 2rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .alert-premium-success {
      background: rgba(16, 185, 129, 0.15);
      border: 1px solid rgba(16, 185, 129, 0.35);
      color: #d1fae5;
      border-radius: 12px;
      padding: 1rem;
      font-size: 0.9rem;
      font-weight: 500;
      margin-bottom: 2rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .forgot-link {
      color: #94a3b8;
      text-decoration: none;
      font-size: 0.8rem;
      font-weight: 600;
      transition: color 0.2s ease;
    }
    .forgot-link:hover {
      color: #3b82f6;
    }

    .portal-footer {
      text-align: center;
      margin-top: 2.5rem;
      padding-top: 1.5rem;
      border-top: 1px solid rgba(255, 255, 255, 0.05);
      color: #94a3b8;
      font-size: 0.9rem;
    }

    .portal-link {
      color: #3b82f6;
      font-weight: 700;
      text-decoration: none;
      transition: color 0.2s ease;
    }
    .portal-link:hover {
      color: #60a5fa;
      text-decoration: underline;
    }

    /* Responsive adjustments */
    @media (max-width: 991.98px) {
      .branding-section {
        display: none;
      }
      .form-section {
        width: 100%;
        padding: 2.5rem 1.5rem;
      }
      body {
        overflow-y: auto;
      }
    }
  </style>
</head>
<body>

<div class="split-layout">
  
  <!-- Left Side: Branding and details -->
  <div class="branding-section">
    <div class="branding-content">
      <div class="brand-header">
        <div class="brand-logo-badge">
          <i class="fas fa-folder-tree"></i>
        </div>
        <div>
          <h4 class="brand-name">Office CRM</h4>
          <span class="brand-subtitle">Workflow File Engine</span>
        </div>
      </div>
      
      <h1 class="branding-title">
        Enterprise File & Stage Workflow Manager
      </h1>
      <p class="branding-desc">
        Manage customer cases, assign tasks dynamically, log audit trails, communicate via automated logs, track real-time SLA metrics, and deliver results.
      </p>
    </div>

    <div class="branding-footer">
      <i class="fas fa-user-shield text-primary me-2"></i> Powered by Mr.Rahul Scripts &bull; v2.0
    </div>
  </div>

  <!-- Right Side: Clean Form with clear text colors -->
  <div class="form-section">
    <div class="form-container">
      
      <div class="mb-4">
        <h2 class="login-title">Get Started!</h2>
        <p class="login-subtitle">Log in with your official operator credentials below</p>
      </div>

      <!-- Alert messages with light colors for dark background -->
      <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-premium">
          <i class="fas fa-exclamation-circle text-danger fs-5"></i>
          <div><?= htmlspecialchars($errorMsg) ?></div>
        </div>
      <?php endif; ?>

      <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-premium">
          <i class="fas fa-exclamation-triangle text-warning fs-5"></i>
          <div><?= htmlspecialchars($_GET['error']) ?></div>
        </div>
      <?php endif; ?>

      <?php if (isset($_GET['success']) || (isset($_GET['msg']) && $_GET['msg'] === 'Logged out successfully.')): ?>
        <div class="alert alert-premium-success">
          <i class="fas fa-check-circle text-success fs-5"></i>
          <div><?= htmlspecialchars($_GET['success'] ?? 'Logged out successfully.') ?></div>
        </div>
      <?php endif; ?>

      <form action="index.php" method="POST" id="loginForm">
        
        <!-- Email Input -->
        <div class="mb-3">
          <label class="form-label-custom">Email Address</label>
          <div class="input-group-custom">
            <span class="input-group-custom-icon"><i class="far fa-envelope"></i></span>
            <input type="email" name="email" id="emailInput" placeholder="e.g. employee@office.com" required>
          </div>
        </div>

        <!-- Password Input -->
        <div class="mb-4">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <label class="form-label-custom mb-0">Security Password</label>
            <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
          </div>
          <div class="input-group-custom">
            <span class="input-group-custom-icon"><i class="fas fa-key"></i></span>
            <input type="password" name="password" id="passwordInput" placeholder="••••••••" required>
          </div>
        </div>

        <!-- Submit Button -->
        <button type="submit" class="btn btn-login">
          <span>Sign In to Workstation</span>
          <i class="fas fa-arrow-right-long"></i>
        </button>
      </form>

      <!-- Customer Portal Redirect -->
      <div class="portal-footer">
        <span>Are you a customer?</span>
        <a href="track.php" class="portal-link ms-1">
          <i class="fas fa-magnifying-glass-location me-1"></i> Track Case Status
        </a>
      </div>

    </div>
  </div>

</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
