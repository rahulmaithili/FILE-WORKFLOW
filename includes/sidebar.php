<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$user = getLoggedInUser();
$roleKey = $user['role_key'] ?? '';
$isAdmin = in_array($roleKey, ['super_admin', 'admin']);

// Fetch company settings
$db = getDB();
$companyInfo = $db->query("SELECT * FROM company_settings LIMIT 1")->fetch() ?: [
    'company_name' => 'File CRM',
    'company_logo' => 'default-logo.png'
];
?>
<aside class="app-sidebar">
  <div class="sidebar-header">
    <?php if (!empty($companyInfo['company_logo']) && $companyInfo['company_logo'] !== 'default-logo.png' && file_exists(UPLOAD_DIR . $companyInfo['company_logo'])): ?>
      <img src="<?= APP_URL ?>/serve.php?file=<?= htmlspecialchars($companyInfo['company_logo']) ?>" class="img-fluid rounded border-0" style="max-height: 40px; max-width: 45px; object-fit: contain;">
    <?php else: ?>
      <div class="brand-icon">
        <i class="fas fa-folder-tree text-white"></i>
      </div>
    <?php endif; ?>
    <div class="brand-details">
      <div class="brand-text text-truncate" style="max-width: 170px;" title="<?= htmlspecialchars($companyInfo['company_name']) ?>">
        <?= htmlspecialchars($companyInfo['company_name']) ?>
      </div>
      <small class="text-muted brand-subtext" style="font-size: 0.72rem;">Workflow Engine</small>
    </div>
  </div>

  <div class="sidebar-menu">
    
    <div class="menu-category">Main Navigation</div>

    <?php if ($isAdmin): ?>
      <a href="<?= APP_URL ?>/admin/dashboard.php" title="Admin Dashboard" class="nav-item-custom <?= $currentPage === 'dashboard.php' && str_contains($_SERVER['PHP_SELF'], 'admin') ? 'active' : '' ?>">
        <i class="fas fa-chart-line sidebar-icon-blue"></i> <span>Admin Dashboard</span>
      </a>
    <?php endif; ?>

    <a href="<?= APP_URL ?>/employee/dashboard.php" title="My Dashboard" class="nav-item-custom <?= $currentPage === 'dashboard.php' && str_contains($_SERVER['PHP_SELF'], 'employee') ? 'active' : '' ?>">
      <i class="fas fa-user-clock sidebar-icon-cyan"></i> <span>My Dashboard</span>
    </a>

    <a href="<?= APP_URL ?>/employee/my-files.php" title="File Management" class="nav-item-custom <?= $currentPage === 'my-files.php' ? 'active' : '' ?>">
      <i class="fas fa-folder-open sidebar-icon-amber"></i> <span>File Management</span>
    </a>

    <?php if (hasPermission('create_file')): ?>
      <a href="<?= APP_URL ?>/employee/create-file.php" title="Create New File" class="nav-item-custom <?= $currentPage === 'create-file.php' ? 'active' : '' ?>">
        <i class="fas fa-plus-circle sidebar-icon-green"></i> <span>Create New File</span>
      </a>
    <?php endif; ?>

    <?php if (isFeatureEnabled('auto_assignment')): ?>
      <a href="<?= APP_URL ?>/employee/kanban.php" title="Kanban Stage View" class="nav-item-custom <?= $currentPage === 'kanban.php' ? 'active' : '' ?>">
        <i class="fas fa-columns sidebar-icon-orange"></i> <span>Kanban Stage View</span>
      </a>
    <?php endif; ?>

    <?php if (isFeatureEnabled('in_app_chat')): ?>
      <a href="<?= APP_URL ?>/employee/chat.php" title="Team Chat Room" class="nav-item-custom <?= $currentPage === 'chat.php' ? 'active' : '' ?>">
        <i class="fas fa-comments sidebar-icon-purple"></i> <span>Team Chat Room</span>
      </a>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
      <div class="menu-category mt-3">Workflow & System Admin</div>

      <a href="<?= APP_URL ?>/admin/workflow-builder.php" title="Workflow Builder" class="nav-item-custom <?= $currentPage === 'workflow-builder.php' ? 'active' : '' ?>">
        <i class="fas fa-sitemap sidebar-icon-red"></i> <span>Workflow Builder</span>
      </a>

      <a href="<?= APP_URL ?>/admin/roles.php" title="Roles & Permissions" class="nav-item-custom <?= $currentPage === 'roles.php' ? 'active' : '' ?>">
        <i class="fas fa-user-shield sidebar-icon-indigo"></i> <span>Roles & Permissions</span>
      </a>

      <a href="<?= APP_URL ?>/admin/users.php" title="Employee Admin" class="nav-item-custom <?= $currentPage === 'users.php' ? 'active' : '' ?>">
        <i class="fas fa-users-cog sidebar-icon-rose"></i> <span>Employee Admin</span>
      </a>

      <a href="<?= APP_URL ?>/admin/reports.php" title="Reports & Analytics" class="nav-item-custom <?= $currentPage === 'reports.php' ? 'active' : '' ?>">
        <i class="fas fa-file-invoice sidebar-icon-emerald"></i> <span>Reports & Analytics</span>
      </a>

      <a href="<?= APP_URL ?>/admin/company-profile.php" title="Company Settings" class="nav-item-custom <?= $currentPage === 'company-profile.php' ? 'active' : '' ?>">
        <i class="fas fa-building sidebar-icon-yellow"></i> <span>Company Settings</span>
      </a>

      <a href="<?= APP_URL ?>/admin/document-types.php" title="Document Categories" class="nav-item-custom <?= $currentPage === 'document-types.php' ? 'active' : '' ?>">
        <i class="fas fa-tags sidebar-icon-purple" style="color: #a855f7;"></i> <span>Document Categories</span>
      </a>

      <a href="<?= APP_URL ?>/admin/features.php" title="System Features" class="nav-item-custom <?= $currentPage === 'features.php' ? 'active' : '' ?>">
        <i class="fas fa-toggle-on sidebar-icon-cyan"></i> <span>System Addons</span>
      </a>

      <?php if (isFeatureEnabled('multi_branch')): ?>
        <a href="<?= APP_URL ?>/admin/branches.php" title="Branch Settings" class="nav-item-custom <?= $currentPage === 'branches.php' ? 'active' : '' ?>">
          <i class="fas fa-network-wired sidebar-icon-teal"></i> <span>Branch Settings</span>
        </a>
      <?php endif; ?>

      <a href="<?= APP_URL ?>/admin/branch-workflow.php" title="Branch Routing Map" class="nav-item-custom <?= $currentPage === 'branch-workflow.php' ? 'active' : '' ?>">
        <i class="fas fa-sitemap sidebar-icon-emerald"></i> <span>Branch Routing Map</span>
      </a>

      <a href="<?= APP_URL ?>/admin/database.php" title="Database & Logs" class="nav-item-custom <?= $currentPage === 'database.php' ? 'active' : '' ?>">
        <i class="fas fa-database sidebar-icon-red"></i> <span>Database & Logs</span>
      </a>

      <a href="<?= APP_URL ?>/admin/security-audit.php" title="Security Audit Logs" class="nav-item-custom <?= $currentPage === 'security-audit.php' ? 'active' : '' ?>">
        <i class="fas fa-shield-alt sidebar-icon-rose"></i> <span>Security Audit Logs</span>
      </a>
    <?php endif; ?>

    <div class="menu-category mt-3">My Settings</div>
    <a href="<?= APP_URL ?>/employee/profile.php" title="Profile Settings" class="nav-item-custom <?= $currentPage === 'profile.php' ? 'active' : '' ?>">
      <i class="fas fa-user-cog sidebar-icon-violet"></i> <span>Profile Settings</span>
    </a>

  </div>

  <!-- Sidebar Footer -->
  <div class="p-3 border-top border-secondary border-opacity-20 text-center sidebar-footer-div">
    <small class="text-muted" style="font-size: 0.75rem;">CRM v<?= APP_VERSION ?></small>
  </div>
</aside>
