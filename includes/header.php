<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

requireLogin();
$currentUser = getLoggedInUser();

$db = getDB();
$unreadCount = 0;
$notificationsList = [];
$overdueCount = 0;
if (isset($_SESSION['user_id'])) {
    $stmtNotifCount = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0");
    $stmtNotifCount->execute(['uid' => $_SESSION['user_id']]);
    $unreadCount = $stmtNotifCount->fetchColumn();

    $stmtNotifList = $db->prepare("SELECT * FROM notifications WHERE user_id = :uid ORDER BY id DESC LIMIT 5");
    $stmtNotifList->execute(['uid' => $_SESSION['user_id']]);
    $notificationsList = $stmtNotifList->fetchAll();

    // Fetch Overdue Cases Count for the Logged-in Employee
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $stmtOverdue = $db->prepare("
            SELECT COUNT(*) 
            FROM files f
            LEFT JOIN workflow_stages ws ON f.current_stage_id = ws.id
            WHERE f.current_assigned_user = :uid 
              AND f.status NOT IN ('completed', 'rejected')
              AND ( TIMESTAMPDIFF(HOUR, f.updated_at, NOW()) > ws.sla_hours )
        ");
    } else {
        $stmtOverdue = $db->prepare("
            SELECT COUNT(*) 
            FROM files f
            LEFT JOIN workflow_stages ws ON f.current_stage_id = ws.id
            WHERE f.current_assigned_user = :uid 
              AND f.status NOT IN ('completed', 'rejected')
              AND ( (julianday('now') - julianday(f.updated_at)) * 24.0 > ws.sla_hours )
        ");
    }
    $stmtOverdue->execute(['uid' => $_SESSION['user_id']]);
    $overdueCount = intval($stmtOverdue->fetchColumn());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> - <?= APP_NAME ?></title>
  
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- FontAwesome 6 -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <!-- SortableJS CSS (Implicit) -->
  <!-- Custom Style -->
  <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
  <!-- PWA Manifest & Service Worker -->
  <link rel="manifest" href="<?= APP_URL ?>/manifest.json">
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('<?= APP_URL ?>/sw.js')
          .then(reg => console.log('PWA Service Worker Active'))
          .catch(err => console.error('PWA Registration Error', err));
      });
    }
  </script>
  <!-- SweetAlert 2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  
  <?php if (isset($overdueCount) && $overdueCount > 0 && !isset($_SESSION['overdue_sound_played'])): 
      $_SESSION['overdue_sound_played'] = true;
  ?>
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    // Play Warning Synthesizer Chime
    try {
      const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
      const oscillator = audioCtx.createOscillator();
      const gainNode = audioCtx.createGain();
      
      oscillator.type = 'triangle';
      oscillator.frequency.setValueAtTime(329.63, audioCtx.currentTime); // E4 note
      oscillator.connect(gainNode);
      gainNode.connect(audioCtx.destination);
      
      gainNode.gain.setValueAtTime(0.08, audioCtx.currentTime);
      oscillator.start();
      
      setTimeout(() => {
        oscillator.stop();
      }, 180);
    } catch(e) {}
  });
  </script>
  <?php endif; ?>
</head>
<body>

<?php if (isset($_SESSION['original_admin_id'])): ?>
  <div class="bg-warning text-dark text-center py-2 fw-bold sticky-top border-bottom border-warning shadow-sm" style="z-index: 1080; font-size: 0.9rem; width: 100%;">
    <i class="fas fa-user-secret me-2 text-danger"></i> You are currently viewing as <strong><?= htmlspecialchars($currentUser['name']) ?></strong> (Impersonating).
    <a href="<?= APP_URL ?>/admin/impersonate.php?action=stop" class="btn btn-xs btn-dark ms-3 fw-bold py-0.5 px-2.5 rounded-pill text-white text-decoration-none" style="font-size: 0.8rem; border: none;">
      <i class="fas fa-undo me-1"></i> Return to Admin Panel
    </a>
  </div>
<?php endif; ?>

<div class="app-wrapper">
  <!-- Include Sidebar -->
  <?php include __DIR__ . '/sidebar.php'; ?>

  <!-- Main Content Area -->
  <div class="app-content">
    
    <!-- Top Navbar -->
    <header class="app-navbar">
      <div class="d-flex align-items-center gap-3">
        <button class="btn btn-light" id="sidebarToggleBtn">
          <i class="fas fa-bars"></i>
        </button>
        <?php 
        $currentPage = basename($_SERVER['PHP_SELF']);
        if (!in_array($currentPage, ['dashboard.php', 'index.php'])): 
        ?>
          <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm fw-semibold d-inline-flex align-items-center gap-1 px-2.5 py-1.5 rounded-pill shadow-sm" style="font-size: 0.78rem;">
            <i class="fas fa-chevron-left"></i> Back
          </a>
        <?php endif; ?>
        <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h5>
      </div>

      <div class="d-flex align-items-center gap-3">
        <!-- Live Date/Time Clock Widget -->
        <div class="d-none d-lg-flex align-items-center gap-2 text-secondary px-3 py-1.5 border rounded-pill bg-light-soft" style="font-size: 0.8rem; font-weight: 600; border-color: var(--border-color) !important;">
          <i class="far fa-clock text-primary"></i>
          <span id="liveClockWidget">Loading time...</span>
        </div>

        <!-- Quick Theme Toggle -->
        <button class="btn btn-outline-secondary btn-sm rounded-circle" id="themeToggleBtn" title="Toggle Light/Dark Theme" style="width: 36px; height: 36px;">
          <i class="fas fa-moon"></i>
        </button>

        <!-- Notifications Bell Dropdown -->
        <div class="dropdown">
          <button class="btn btn-light position-relative dropdown-toggle no-caret <?= ($overdueCount > 0) ? 'border-danger' : '' ?>" type="button" id="notifDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 50%; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color);">
            <i class="fas fa-bell <?= ($overdueCount > 0) ? 'text-danger glow-warning-pulse' : 'text-secondary' ?>"></i>
            <?php if ($unreadCount > 0 || $overdueCount > 0): ?>
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-white" style="font-size: 0.65rem; transform: translate(-35%, -15%);">
                <?= ($unreadCount + $overdueCount) ?>
              </span>
            <?php endif; ?>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-lg py-0 border-0 mt-2" aria-labelledby="notifDropdownBtn" style="width: 320px; border-radius: var(--radius-md); overflow: hidden; z-index: 1050;">
            <li class="bg-dark text-white p-3 d-flex justify-content-between align-items-center">
              <h6 class="fw-bold mb-0" style="font-size: 0.95rem;">Notifications</h6>
              <?php if ($unreadCount > 0): ?>
                <a href="<?= APP_URL ?>/api/notif-api.php?action=read_all" class="text-white-50 small text-decoration-none fw-bold" style="font-size: 0.75rem;">Mark all as read</a>
              <?php endif; ?>
            </li>
            <div style="max-height: 280px; overflow-y: auto;">
              <?php if (empty($notificationsList)): ?>
                <div class="text-center py-4 text-muted small">
                  <i class="fas fa-bell-slash fa-2x mb-2 opacity-50"></i>
                  <div>No new alerts</div>
                </div>
              <?php else: ?>
                <?php foreach ($notificationsList as $notif): ?>
                  <li>
                    <a class="dropdown-item p-3 border-bottom d-flex flex-column gap-1 text-wrap <?= $notif['is_read'] == 0 ? 'bg-light-soft fw-semibold' : '' ?>" href="<?= $notif['link'] ?: 'javascript:void(0)' ?>" onclick="markAsRead(<?= $notif['id'] ?>)">
                      <div class="d-flex justify-content-between align-items-center">
                        <span class="text-dark small fw-bold" style="font-size: 0.82rem;"><?= htmlspecialchars($notif['title']) ?></span>
                        <small class="text-muted" style="font-size: 0.65rem;" title="<?= date('d M Y, h:i A', strtotime($notif['created_at'])) ?>"><i class="far fa-clock me-1"></i><?= timeAgo($notif['created_at']) ?></small>
                      </div>
                      <p class="text-muted mb-0 small" style="font-size: 0.78rem;"><?= htmlspecialchars($notif['message']) ?></p>
                    </a>
                  </li>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </ul>
        </div>

        <!-- User Profile Dropdown -->
        <div class="dropdown">
          <div class="user-profile-badge dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            <?php if (!empty($currentUser['profile_photo']) && $currentUser['profile_photo'] !== 'default-avatar.png' && file_exists(PROFILE_UPLOAD_DIR . $currentUser['profile_photo'])): ?>
              <img src="<?= APP_URL ?>/serve.php?file=profiles/<?= htmlspecialchars($currentUser['profile_photo']) ?>" class="rounded-circle border border-primary" style="width: 40px; height: 40px; object-fit: cover;">
            <?php else: ?>
              <div class="avatar-circle">
                <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
              </div>
            <?php endif; ?>
            <div class="d-none d-md-block text-start">
              <div class="fw-bold text-dark lh-1" style="font-size: 0.9rem;"><?= htmlspecialchars($currentUser['name']) ?></div>
              <small class="text-muted" style="font-size: 0.78rem;"><?= htmlspecialchars($currentUser['role_name']) ?></small>
            </div>
          </div>
          <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" style="border-radius: var(--radius-md);">
            <li class="px-3 py-2 border-bottom">
              <small class="text-muted d-block">Signed in as</small>
              <strong class="text-dark d-block text-truncate" style="max-width: 180px;"><?= htmlspecialchars($currentUser['email']) ?></strong>
            </li>
            <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/employee/profile.php"><i class="fas fa-user-cog me-2"></i> My Profile</a></li>
            <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/logout.php"><i class="fas fa-sign-out-alt me-2 text-danger"></i> Logout</a></li>
          </ul>
        </div>
      </div>
    </header>

<script>
function markAsRead(id) {
  fetch('<?= APP_URL ?>/api/notif-api.php?action=read&id=' + id);
}

function updateLiveClock() {
  const clockEl = document.getElementById('liveClockWidget');
  if (clockEl) {
    const now = new Date();
    const options = { 
      weekday: 'short', 
      year: 'numeric', 
      month: 'short', 
      day: 'numeric', 
      hour: '2-digit', 
      minute: '2-digit', 
      second: '2-digit', 
      hour12: true 
    };
    clockEl.textContent = now.toLocaleDateString('en-US', options);
  }
}
setInterval(updateLiveClock, 1000);
updateLiveClock(); // Run initially
</script>

    <!-- Page Body Container -->
    <main class="p-4">
      <?= displayFlashMessages(); ?>
