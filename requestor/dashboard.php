<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('requestor');

$conn = getDBConnection();

// Fetch requestor's schedule requests
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM schedule_requests WHERE requestor_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$requests = $stmt->get_result();

// Fetch notifications
$stmt_notif = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt_notif->bind_param("i", $user_id);
$stmt_notif->execute();
$notifications = $stmt_notif->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requestor Dashboard - Training Lab Schedule</title>
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
                        <div class="sidebar-user-role">Requestor</div>
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
                    <a href="submit_request.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon">➕</span>
                        <span class="sidebar-nav-text">Submit Request</span>
                    </a>
                    <a href="../index.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon">📅</span>
                        <span class="sidebar-nav-text">View Schedule</span>
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
                    <h1 class="page-title">My Requests</h1>
                </div>
                <div class="top-header-right">
                    <span style="color: #6b7280; font-size: 0.9rem;">
                        <?php echo date('l, F d, Y'); ?>
                    </span>
                </div>
            </header>
            
            <main class="content-wrapper">

                <?php if ($notifications->num_rows > 0): ?>
                    <div class="content-card">
                        <div class="content-card-header">
                            <h3 class="content-card-title">Recent Notifications</h3>
                        </div>
                        <div class="content-card-body">
                            <?php while ($notif = $notifications->fetch_assoc()): ?>
                                <div class="alert alert-info" style="margin-bottom: 0.5rem;">
                                    <?php echo htmlspecialchars($notif['message']); ?>
                                    <small style="display: block; margin-top: 0.25rem; color: #7f8c8d;">
                                        <?php echo date('F d, Y h:i A', strtotime($notif['created_at'])); ?>
                                    </small>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="content-card">
                    <div class="content-card-header">
                        <h3 class="content-card-title">My Schedule Requests</h3>
                        <div class="content-card-actions">
                            <a href="submit_request.php" class="btn btn-primary btn-sm">+ New Request</a>
                        </div>
                    </div>
                    <div class="content-card-body">
                        <?php if ($requests->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="schedule-table">
                                    <thead>
                                        <tr>
                                            <th>Date Submitted</th>
                                            <th>Start Date</th>
                                            <th>Title</th>
                                            <th>Time</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $requests->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($row['start_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                                <td>
                                                    <?php echo date('h:i A', strtotime($row['start_time'])); ?> - 
                                                    <?php echo date('h:i A', strtotime($row['end_time'])); ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $row['status']; ?>">
                                                        <?php echo ucfirst($row['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="view_request.php?id=<?php echo $row['request_id']; ?>" class="btn btn-primary btn-sm">View</a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="no-data">You haven't submitted any schedule requests yet.</p>
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
$stmt->close();
$stmt_notif->close();
closeDBConnection($conn);
?>
