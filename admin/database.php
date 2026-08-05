<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requireAdmin();

$db = getDB();
$user = getLoggedInUser();
$error = '';
$success = '';

// 1. Download Backup Action
if (isset($_GET['action']) && $_GET['action'] === 'backup') {
    if (DB_DRIVER === 'sqlite') {
        if (file_exists(SQLITE_FILE)) {
            // Close connection to prevent file locking issues during download
            $db = null;
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="crm_backup_' . date('Ymd_His') . '.sqlite"');
            header('Content-Length: ' . filesize(SQLITE_FILE));
            readfile(SQLITE_FILE);
            exit;
        } else {
            $error = "Backup failed: SQLite database file not found.";
        }
    } else {
        $error = "Backup failed: Automated backup is supported for SQLite driver. For MySQL, please perform a standard mysqldump.";
    }
}

// 2. Restore Database Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup'])) {
    if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['backup_file']['tmp_name'];
        
        // Read file header to verify SQLite binary signature
        $handle = fopen($tmpName, 'r');
        $header = fread($handle, 15);
        fclose($handle);

        if (strpos($header, 'SQLite format 3') === 0) {
            // Close database connection
            $db = null;
            
            // Overwrite database file
            if (copy($tmpName, SQLITE_FILE)) {
                // Clear session and force re-login
                session_destroy();
                header("Location: " . APP_URL . "/index.php?success=Database+restored+successfully.+Please+login+again.");
                exit;
            } else {
                $error = "Failed to copy backup file. Check file system permissions.";
            }
        } else {
            $error = "Invalid backup file. Uploaded file is not a valid SQLite database snapshot.";
        }
    } else {
        $error = "Please upload a valid SQLite backup file.";
    }
}

// 3. Wipe All Data Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wipe_data'])) {
    $password = $_POST['admin_password'] ?? '';
    
    // Verify admin password
    $stmt = $db->prepare("SELECT password FROM users WHERE id = :uid");
    $stmt->execute(['uid' => $user['id']]);
    $hashed = $stmt->fetchColumn();

    if ($hashed && password_verify($password, $hashed)) {
        if (isset($_POST['confirm_wipe'])) {
            try {
                // Wipe transaction records
                $db->exec("DELETE FROM files");
                $db->exec("DELETE FROM file_documents");
                $db->exec("DELETE FROM file_history");
                $db->exec("DELETE FROM chat_messages");
                $db->exec("DELETE FROM notifications");
                $db->exec("DELETE FROM activity_logs");
                
                if (DB_DRIVER === 'sqlite') {
                    $db->exec("DELETE FROM sqlite_sequence WHERE name IN ('files', 'file_documents', 'file_history', 'chat_messages', 'notifications', 'activity_logs')");
                }
                
                logActivity($user['id'], 'DATABASE_WIPE', 'Wiped all case records, chat history, notifications, and system logs.');
                $success = "All transaction database records wiped successfully! User accounts and system configuration settings have been preserved.";
            } catch (Exception $e) {
                $error = "System wipe failed: " . $e->getMessage();
            }
        } else {
            $error = "You must select the confirmation check box to perform the wipe action.";
        }
    } else {
        $error = "Incorrect admin account verification password.";
    }
}

// Fetch recent activity logs
$logs = $db->query("
    SELECT l.*, u.name as user_name, r.role_name 
    FROM activity_logs l 
    LEFT JOIN users u ON l.user_id = u.id 
    LEFT JOIN roles r ON u.role_id = r.id 
    ORDER BY l.id DESC 
    LIMIT 60
")->fetchAll();

$pageTitle = 'Database Utilities & Activity Logs';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
  <!-- Left Side: Database Operations (Backup, Import, Wipe) -->
  <div class="col-lg-5">
    
    <!-- Backup Card -->
    <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: var(--radius-lg);">
      <h5 class="fw-bold mb-3 border-bottom pb-2 text-dark">
        <i class="fas fa-download text-primary me-2"></i> Database Export & Backup
      </h5>
      <p class="text-muted small mb-3">Download a complete snapshot backup of the SQLite database. This contains all current files, activities, and settings.</p>
      
      <?php if (!empty($error) && !isset($_POST['wipe_data']) && !isset($_POST['restore_backup'])): ?>
        <div class="alert alert-danger py-2 px-3 small mb-3"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <a href="database.php?action=backup" class="btn btn-primary fw-bold w-100 py-2.5 shadow-sm">
        <i class="fas fa-file-download me-1"></i> Generate & Download Backup
      </a>
    </div>

    <!-- Restore Card -->
    <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: var(--radius-lg);">
      <h5 class="fw-bold mb-3 border-bottom pb-2 text-dark">
        <i class="fas fa-upload text-info me-2"></i> Import & Restore Database
      </h5>
      <p class="text-muted small mb-3">Upload a previously exported SQLite database file (`.sqlite`) to overwrite current data and restore system snapshot.</p>
      
      <?php if (!empty($error) && isset($_POST['restore_backup'])): ?>
        <div class="alert alert-danger py-2 px-3 small mb-3"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form action="database.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="restore_backup" value="1">
        <div class="mb-3">
          <input type="file" name="backup_file" class="form-control" accept=".sqlite" required>
        </div>
        <button type="submit" class="btn btn-info text-white fw-bold w-100 py-2.5 shadow-sm" onclick="return confirm('WARNING: Importing this file will overwrite all current system data. Are you sure you want to proceed?');">
          <i class="fas fa-file-upload me-1"></i> Upload & Restore Backup
        </button>
      </form>
    </div>

    <!-- Wipe Card -->
    <div class="card border-0 shadow shadow-sm p-4 border-danger border-top border-4" style="border-radius: var(--radius-lg);">
      <h5 class="fw-bold mb-3 border-bottom pb-2 text-danger">
        <i class="fas fa-trash-alt me-2"></i> Wipe System (Reset Data)
      </h5>
      <p class="text-muted small mb-3">Wipes all customer files, attachments, logs, chat messages, and notifications. <strong>Users and roles will be preserved.</strong></p>
      
      <?php if (!empty($error) && isset($_POST['wipe_data'])): ?>
        <div class="alert alert-danger py-2 px-3 small mb-3"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if (!empty($success)): ?>
        <div class="alert alert-success py-2 px-3 small mb-3"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <form action="database.php" method="POST">
        <input type="hidden" name="wipe_data" value="1">
        <div class="mb-3 form-check">
          <input type="checkbox" name="confirm_wipe" value="1" class="form-check-input" id="confirmWipeCheck" required>
          <label class="form-check-label small fw-bold text-danger" for="confirmWipeCheck">I understand this deletes all transaction data permanently</label>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-bold text-dark">Confirm Admin Password</label>
          <input type="password" name="admin_password" class="form-control form-control-sm" placeholder="Confirm password" required>
        </div>
        <button type="submit" class="btn btn-danger fw-bold w-100 py-2.5 shadow-sm" onclick="return confirm('CRITICAL WARNING: This will permanently delete all case files and chat history. This action CANNOT be undone. Are you sure?');">
          <i class="fas fa-exclamation-triangle me-1"></i> Wipe All Data
        </button>
      </form>
    </div>

  </div>

  <!-- Right Side: Log Activity Register (Activity Logs table) -->
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm p-4 h-100" style="border-radius: var(--radius-lg); overflow: hidden; display: flex; flex-direction: column;">
      <div class="border-bottom pb-3 mb-4 d-flex justify-content-between align-items-center">
        <div>
          <h5 class="fw-bold mb-1 text-dark"><i class="fas fa-list-alt text-secondary me-2"></i> System Activity Logs</h5>
          <p class="text-muted small mb-0">Audit register tracking employee check-ins, updates, and database actions</p>
        </div>
        <span class="badge bg-secondary-soft text-secondary border px-3 py-2"><i class="fas fa-history me-1"></i> Audit Logs</span>
      </div>

      <div class="table-responsive flex-grow-1" style="max-height: 550px; overflow-y: auto;">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>User</th>
              <th>Action Event</th>
              <th>IP / Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $log): ?>
              <tr>
                <td>
                  <div class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></div>
                  <small class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($log['role_name'] ?? 'Automated Task') ?></small>
                </td>
                <td>
                  <span class="badge bg-light text-dark border small fw-bold text-uppercase" style="font-size: 0.65rem;"><?= htmlspecialchars($log['action']) ?></span>
                  <div class="text-secondary small mt-1" style="font-size: 0.78rem; max-width: 250px; word-wrap: break-word;"><?= htmlspecialchars($log['details']) ?></div>
                </td>
                <td>
                  <div class="small text-dark fw-semibold" style="font-size: 0.75rem;"><i class="fas fa-desktop me-1 text-muted"></i> <?= htmlspecialchars($log['ip_address']) ?></div>
                  <small class="text-muted" style="font-size: 0.7rem;"><?= timeAgo($log['created_at']) ?></small>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
