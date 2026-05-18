<?php
/**
 * Sidebar Helper Functions
 * Generates consistent sidebar navigation for all user roles
 */

function renderSidebarHeader($currentPage = '') {
    ?>
    <div class="sidebar-header">
        <a href="<?php echo getSidebarLink('dashboard', $currentPage); ?>" class="sidebar-logo">
            <div class="sidebar-logo-icon">🔬</div>
            <div class="sidebar-logo-text">
                <span class="sidebar-logo-title">Training Lab</span>
                <span class="sidebar-logo-subtitle">Schedule System</span>
            </div>
        </a>
    </div>
    <?php
}

function renderSidebarUser() {
    ?>
    <div class="sidebar-user">
        <div class="sidebar-user-info">
            <div class="sidebar-user-avatar">
                <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
            </div>
            <div class="sidebar-user-details">
                <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                <div class="sidebar-user-role"><?php echo ucfirst($_SESSION['role']); ?></div>
            </div>
        </div>
    </div>
    <?php
}

function renderRequestorNav($activePage = 'dashboard') {
    ?>
    <nav class="sidebar-nav">
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">Main Menu</div>
            <a href="index.php" class="sidebar-nav-item <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">
                <span class="sidebar-nav-icon">📊</span>
                <span class="sidebar-nav-text">Dashboard</span>
            </a>
            <a href="../index.php" class="sidebar-nav-item <?php echo $activePage === 'schedule' ? 'active' : ''; ?>">
                <span class="sidebar-nav-icon">📅</span>
                <span class="sidebar-nav-text">View Schedule</span>
            </a>
        </div>
    </nav>
    <?php
}

function renderAdminNav($activePage = 'dashboard', $pendingCount = 0) {
    // Get cancellation requests count
    $conn = getDBConnection();
    $cancellation_count = 0;
    if ($conn) {
        $result = $conn->query("SELECT COUNT(*) as count FROM cancellation_requests WHERE status = 'pending'");
        if ($result) {
            $cancellation_count = $result->fetch_assoc()['count'];
        }
        closeDBConnection($conn);
    }
    ?>
    <nav class="sidebar-nav">
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">Main Menu</div>
            <a href="dashboard.php" class="sidebar-nav-item <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">
                <span class="sidebar-nav-icon">📊</span>
                <span class="sidebar-nav-text">Dashboard</span>
            </a>
            <a href="approved_schedules.php" class="sidebar-nav-item <?php echo $activePage === 'schedules' ? 'active' : ''; ?>">
                <span class="sidebar-nav-icon">✅</span>
                <span class="sidebar-nav-text">Manage Schedules</span>
            </a>
            <a href="cancellation_requests.php" class="sidebar-nav-item <?php echo $activePage === 'cancellations' ? 'active' : ''; ?>">
                <span class="sidebar-nav-icon">🗑️</span>
                <span class="sidebar-nav-text">Cancellation Requests</span>
                <?php if ($cancellation_count > 0): ?>
                    <span class="sidebar-nav-badge"><?php echo $cancellation_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="../index.php" class="sidebar-nav-item <?php echo $activePage === 'public' ? 'active' : ''; ?>">
                <span class="sidebar-nav-icon">📅</span>
                <span class="sidebar-nav-text">Public Schedule</span>
            </a>
        </div>
        
        <?php if ($_SESSION['role'] === 'superadmin'): ?>
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">Administration</div>
            <a href="../superadmin/manage_users.php" class="sidebar-nav-item <?php echo $activePage === 'users' ? 'active' : ''; ?>">
                <span class="sidebar-nav-icon">👥</span>
                <span class="sidebar-nav-text">Manage Users</span>
            </a>
        </div>
        <?php endif; ?>
    </nav>
    <?php
}

function renderSuperAdminNav($activePage = 'dashboard', $pendingCount = 0) {
    ?>
    <nav class="sidebar-nav">
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">Main Menu</div>
            <a href="dashboard.php" class="sidebar-nav-item <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">
                <span class="sidebar-nav-icon">📊</span>
                <span class="sidebar-nav-text">Dashboard</span>
            </a>
            <a href="../admin/pending_requests.php" class="sidebar-nav-item <?php echo $activePage === 'pending' ? 'active' : ''; ?>">
                <span class="sidebar-nav-icon">⏳</span>
                <span class="sidebar-nav-text">Pending Requests</span>
                <?php if ($pendingCount > 0): ?>
                    <span class="sidebar-nav-badge"><?php echo $pendingCount; ?></span>
                <?php endif; ?>
            </a>
            <a href="../admin/approved_schedules.php" class="sidebar-nav-item <?php echo $activePage === 'schedules' ? 'active' : ''; ?>">
                <span class="sidebar-nav-icon">✅</span>
                <span class="sidebar-nav-text">Manage Schedules</span>
            </a>
            <a href="../index.php" class="sidebar-nav-item <?php echo $activePage === 'public' ? 'active' : ''; ?>">
                <span class="sidebar-nav-icon">📅</span>
                <span class="sidebar-nav-text">Public Schedule</span>
            </a>
        </div>
        
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">Administration</div>
            <a href="manage_users.php" class="sidebar-nav-item <?php echo $activePage === 'users' ? 'active' : ''; ?>">
                <span class="sidebar-nav-icon">👥</span>
                <span class="sidebar-nav-text">Manage Users</span>
            </a>
            <a href="create_user.php" class="sidebar-nav-item <?php echo $activePage === 'create_user' ? 'active' : ''; ?>">
                <span class="sidebar-nav-icon">➕</span>
                <span class="sidebar-nav-text">Create User</span>
            </a>
        </div>
    </nav>
    <?php
}

function renderSidebarFooter() {
    ?>
    <div class="sidebar-footer">
        <a href="<?php echo getLogoutLink(); ?>" class="sidebar-logout">
            <span class="sidebar-logout-icon">🚪</span>
            <span class="sidebar-nav-text">Logout</span>
        </a>
    </div>
    <?php
}

function getSidebarLink($page, $currentPage) {
    // Determine the correct path based on current location
    if (strpos($currentPage, 'admin') !== false) {
        return '../admin/' . $page . '.php';
    } elseif (strpos($currentPage, 'superadmin') !== false) {
        return '../superadmin/' . $page . '.php';
    } elseif (strpos($currentPage, 'requestor') !== false) {
        return '../requestor/' . $page . '.php';
    }
    return $page . '.php';
}

function getLogoutLink() {
    // Determine the correct logout path
    if (isset($_SESSION['role'])) {
        return '../logout.php';
    }
    return 'logout.php';
}

function renderTopHeader($pageTitle) {
    ?>
    <header class="top-header">
        <div class="top-header-left">
            <button class="menu-toggle">☰</button>
            <h1 class="page-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
        </div>
        <div class="top-header-right">
            <span style="color: #6b7280; font-size: 0.9rem;">
                <?php echo date('l, F d, Y'); ?>
            </span>
        </div>
    </header>
    <?php
}

function renderSidebarOverlay() {
    echo '<div class="sidebar-overlay"></div>';
}

function renderScripts() {
    ?>
    <script src="../assets/js/sidebar.js"></script>
    <?php
}
?>
