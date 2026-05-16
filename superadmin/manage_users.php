<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('superadmin');

$conn = getDBConnection();
$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Training Lab Schedule</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
</head>
<body>
    <div class="app-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" class="sidebar-logo">
                    <div class="sidebar-logo-icon">🔬</div>
                    <div class="sidebar-logo-text">
                        <span class="sidebar-logo-title">Training Lab</span>
                        <span class="sidebar-logo-subtitle">Schedule System</span>
                    </div>
                </a>
            </div>
            
            <div class="sidebar-user">
                <div class="sidebar-user-info">
                    <div class="sidebar-user-avatar">
                        <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                    </div>
                    <div class="sidebar-user-details">
                        <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                        <div class="sidebar-user-role">Super Admin</div>
                    </div>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="sidebar-nav-section">
                    <div class="sidebar-nav-title">Main Menu</div>
                    <a href="dashboard.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon">📊</span>
                        <span class="sidebar-nav-text">Dashboard</span>
                    </a>
                    <a href="../admin/pending_requests.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon">⏳</span>
                        <span class="sidebar-nav-text">Pending Requests</span>
                    </a>
                    <a href="../admin/approved_schedules.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon">✅</span>
                        <span class="sidebar-nav-text">Manage Schedules</span>
                    </a>
                    <a href="../index.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon">📅</span>
                        <span class="sidebar-nav-text">Public Schedule</span>
                    </a>
                </div>
                
                <div class="sidebar-nav-section">
                    <div class="sidebar-nav-title">Administration</div>
                    <a href="manage_users.php" class="sidebar-nav-item active">
                        <span class="sidebar-nav-icon">👥</span>
                        <span class="sidebar-nav-text">Manage Users</span>
                    </a>
                    <a href="create_user.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon">➕</span>
                        <span class="sidebar-nav-text">Create User</span>
                    </a>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <a href="../logout.php" class="sidebar-logout">
                    <span class="sidebar-logout-icon">🚪</span>
                    <span class="sidebar-nav-text">Logout</span>
                </a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <div class="main-content">
            <header class="top-header">
                <div class="top-header-left">
                    <button class="menu-toggle">☰</button>
                    <h1 class="page-title">User Management</h1>
                </div>
                <div class="top-header-right">
                    <a href="create_user.php" class="btn btn-success btn-sm">+ Create User</a>
                </div>
            </header>
            
            <main class="content-wrapper">
                <div class="content-card">
                    <div class="content-card-header">
                        <h3 class="content-card-title">All Users (<?php echo $users->num_rows; ?>)</h3>
                    </div>
                    <div class="content-card-body">

                        <?php if ($users->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="schedule-table">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Created At</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $users->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                                <td><?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $row['role'] === 'superadmin' ? 'approved' : ($row['role'] === 'admin' ? 'pending' : 'active'); ?>">
                                                        <?php echo ucfirst($row['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $row['status']; ?>">
                                                        <?php echo ucfirst($row['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="edit_user.php?id=<?php echo $row['user_id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                                        <?php if ($row['user_id'] != $_SESSION['user_id']): ?>
                                                            <?php if ($row['status'] === 'active'): ?>
                                                                <a href="toggle_user_status.php?id=<?php echo $row['user_id']; ?>&action=deactivate" class="btn btn-danger btn-sm" onclick="return confirm('Deactivate this user?');">Deactivate</a>
                                                            <?php else: ?>
                                                                <a href="toggle_user_status.php?id=<?php echo $row['user_id']; ?>&action=activate" class="btn btn-success btn-sm" onclick="return confirm('Activate this user?');">Activate</a>
                                                            <?php endif; ?>
                                                            <a href="delete_user.php?id=<?php echo $row['user_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">Delete</a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="no-data">No users found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay"></div>
    
    <script src="../assets/js/sidebar.js"></script>
</body>
</html>

<?php
closeDBConnection($conn);
?>
