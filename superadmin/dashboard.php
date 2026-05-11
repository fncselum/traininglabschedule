<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('superadmin');

$conn = getDBConnection();

// Fetch statistics
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_pending = $conn->query("SELECT COUNT(*) as count FROM schedule_requests WHERE status = 'pending'")->fetch_assoc()['count'];
$total_approved = $conn->query("SELECT COUNT(*) as count FROM approved_schedules")->fetch_assoc()['count'];
$active_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'")->fetch_assoc()['count'];

// Fetch recent users
$recent_users = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");

// Fetch pending schedule requests
$pending_requests = $conn->query("SELECT sr.*, u.username FROM schedule_requests sr JOIN users u ON sr.requestor_id = u.user_id WHERE sr.status = 'pending' ORDER BY sr.created_at DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1e3a5f">
    <title>Super Admin Dashboard - Training Lab Schedule</title>
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
                    <a href="dashboard.php" class="sidebar-nav-item active">
                        <span class="sidebar-nav-icon">📊</span>
                        <span class="sidebar-nav-text">Dashboard</span>
                    </a>
                    <a href="../admin/pending_requests.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon">⏳</span>
                        <span class="sidebar-nav-text">Pending Requests</span>
                        <?php if ($total_pending > 0): ?>
                            <span class="sidebar-nav-badge"><?php echo $total_pending; ?></span>
                        <?php endif; ?>
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
                    <a href="manage_users.php" class="sidebar-nav-item">
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
                    <h1 class="page-title">Super Admin Dashboard</h1>
                </div>
                <div class="top-header-right">
                    <span style="color: #6b7280; font-size: 0.9rem;">
                        <?php echo date('l, F d, Y'); ?>
                    </span>
                </div>
            </header>
            
            <main class="content-wrapper">
                <!-- Statistics Cards -->
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <span class="stat-card-title">Total Users</span>
                            <div class="stat-card-icon">👥</div>
                        </div>
                        <div class="stat-card-value"><?php echo $total_users; ?></div>
                        <div class="stat-card-label">Registered users</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <span class="stat-card-title">Active Users</span>
                            <div class="stat-card-icon">✅</div>
                        </div>
                        <div class="stat-card-value"><?php echo $active_users; ?></div>
                        <div class="stat-card-label">Currently active</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <span class="stat-card-title">Pending Requests</span>
                            <div class="stat-card-icon">⏳</div>
                        </div>
                        <div class="stat-card-value"><?php echo $total_pending; ?></div>
                        <div class="stat-card-label">Awaiting review</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <span class="stat-card-title">Approved Schedules</span>
                            <div class="stat-card-icon">📅</div>
                        </div>
                        <div class="stat-card-value"><?php echo $total_approved; ?></div>
                        <div class="stat-card-label">Total schedules</div>
                    </div>
                </div>

                <!-- Recent Users -->
                <div class="content-card">
                    <div class="content-card-header">
                        <h3 class="content-card-title">Recent Users</h3>
                        <div class="content-card-actions">
                            <a href="manage_users.php" class="btn btn-primary btn-sm">View All</a>
                            <a href="create_user.php" class="btn btn-success btn-sm">+ Add User</a>
                        </div>
                    </div>
                    <div class="content-card-body">
                        <?php if ($recent_users->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="schedule-table">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($user = $recent_users->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td><span class="badge badge-<?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                                                <td><span class="badge badge-<?php echo $user['status']; ?>"><?php echo ucfirst($user['status']); ?></span></td>
                                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="edit_user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-warning btn-sm">Edit</a>
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

                <!-- Pending Requests -->
                <div class="content-card">
                    <div class="content-card-header">
                        <h3 class="content-card-title">Pending Schedule Requests</h3>
                        <div class="content-card-actions">
                            <a href="../admin/pending_requests.php" class="btn btn-primary btn-sm">View All</a>
                        </div>
                    </div>
                    <div class="content-card-body">
                        <?php if ($pending_requests->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="schedule-table">
                                    <thead>
                                        <tr>
                                            <th>Submitted By</th>
                                            <th>Title</th>
                                            <th>Start Date</th>
                                            <th>Time</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($request = $pending_requests->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($request['username']); ?></td>
                                                <td><?php echo htmlspecialchars($request['title']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($request['start_date'])); ?></td>
                                                <td>
                                                    <?php echo date('h:i A', strtotime($request['start_time'])); ?> - 
                                                    <?php echo date('h:i A', strtotime($request['end_time'])); ?>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="../admin/review_request.php?id=<?php echo $request['request_id']; ?>" class="btn btn-primary btn-sm">Review</a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="no-data">No pending requests at this time.</p>
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
