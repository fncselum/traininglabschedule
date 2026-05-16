<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireAnyRole(['admin', 'superadmin']);

$conn = getDBConnection();

// Fetch pending schedule requests
$pending_requests = $conn->query("SELECT sr.*, u.username FROM schedule_requests sr JOIN users u ON sr.requestor_id = u.user_id WHERE sr.status = 'pending' ORDER BY sr.created_at DESC");

// Fetch approved schedules
$approved_schedules = $conn->query("SELECT * FROM approved_schedules ORDER BY start_date DESC, start_time DESC LIMIT 10");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#1e3a5f">
    <title>Admin Dashboard - Training Lab Schedule</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <style>
        /* Header Profile Link */
        .header-profile-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .header-profile-link:hover {
            background: #f3f4f6;
        }
        
        .header-user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #4CAF50 0%, #66bb6a 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: 700;
            color: white;
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }
        
        .header-user-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e3a5f;
        }
    </style>
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
            
            <nav class="sidebar-nav" style="padding-top: 1rem;">
                <div class="sidebar-nav-section">
                    <div class="sidebar-nav-title">Main Menu</div>
                    <a href="dashboard.php" class="sidebar-nav-item active">
                        <span class="sidebar-nav-icon">📊</span>
                        <span class="sidebar-nav-text">Dashboard</span>
                    </a>
                    <a href="pending_requests.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon">⏳</span>
                        <span class="sidebar-nav-text">Pending Requests</span>
                        <?php if ($pending_requests->num_rows > 0): ?>
                            <span class="sidebar-nav-badge"><?php echo $pending_requests->num_rows; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="approved_schedules.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon">✅</span>
                        <span class="sidebar-nav-text">Manage Schedules</span>
                    </a>
                    <a href="../index.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon">📅</span>
                        <span class="sidebar-nav-text">Public Schedule</span>
                    </a>
                </div>
                
                <?php if ($_SESSION['role'] === 'superadmin'): ?>
                <div class="sidebar-nav-section">
                    <div class="sidebar-nav-title">Administration</div>
                    <a href="../superadmin/manage_users.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon">👥</span>
                        <span class="sidebar-nav-text">Manage Users</span>
                    </a>
                </div>
                <?php endif; ?>
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
                    <div class="header-profile-link">
                        <div class="header-user-avatar">
                            <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                        </div>
                        <span class="header-user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    </div>
                </div>
                <div class="top-header-right">
                    <span style="color: #6b7280; font-size: 0.9rem;">
                        <?php echo date('l, F d, Y'); ?>
                    </span>
                </div>
            </header>
            
            <main class="content-wrapper">

                <div class="content-card">
                    <div class="content-card-header">
                        <h3 class="content-card-title">Pending Schedule Requests (<?php echo $pending_requests->num_rows; ?>)</h3>
                        <div class="content-card-actions">
                            <a href="pending_requests.php" class="btn btn-primary btn-sm">View All</a>
                        </div>
                    </div>
                    <div class="content-card-body">
                        <?php if ($pending_requests->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="schedule-table">
                                    <thead>
                                        <tr>
                                            <th>Submitted By</th>
                                            <th>Date Submitted</th>
                                            <th>Start Date</th>
                                            <th>Title</th>
                                            <th>Time</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $pending_requests->data_seek(0); // Reset pointer
                                        while ($row = $pending_requests->fetch_assoc()): 
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($row['start_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                                <td>
                                                    <?php echo date('h:i A', strtotime($row['start_time'])); ?> - 
                                                    <?php echo date('h:i A', strtotime($row['end_time'])); ?>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="review_request.php?id=<?php echo $row['request_id']; ?>" class="btn btn-primary btn-sm">Review</a>
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

                <div class="content-card">
                    <div class="content-card-header">
                        <h3 class="content-card-title">Recent Approved Schedules</h3>
                        <div class="content-card-actions">
                            <a href="approved_schedules.php" class="btn btn-primary btn-sm">View All</a>
                        </div>
                    </div>
                    <div class="content-card-body">
                        <?php if ($approved_schedules->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="schedule-table">
                                    <thead>
                                        <tr>
                                            <th>Start Date</th>
                                            <th>Title</th>
                                            <th>Time</th>
                                            <th>Program Owner</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $approved_schedules->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($row['start_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                                <td>
                                                    <?php echo date('h:i A', strtotime($row['start_time'])); ?> - 
                                                    <?php echo date('h:i A', strtotime($row['end_time'])); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['program_owner']); ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="edit_schedule.php?id=<?php echo $row['schedule_id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                                        <a href="delete_schedule.php?id=<?php echo $row['schedule_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this schedule?');">Delete</a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="no-data">No approved schedules yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay"></div>
    
    <script src="../assets/js/sidebar.js"></script>
    <script src="../assets/js/mobile-menu.js"></script>
    <script src="../assets/js/responsive.js"></script>
</body>
</html>

<?php
closeDBConnection($conn);
?>
