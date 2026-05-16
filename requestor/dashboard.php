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

// Fetch notifications and count unread
$stmt_notif = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt_notif->bind_param("i", $user_id);
$stmt_notif->execute();
$notifications = $stmt_notif->get_result();

// Count unread notifications
$stmt_unread = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt_unread->bind_param("i", $user_id);
$stmt_unread->execute();
$unread_result = $stmt_unread->get_result();
$unread_count = $unread_result->fetch_assoc()['unread_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Requestor Dashboard - Training Lab Schedule</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/responsive-utilities.css">
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
        
        /* Profile Link Hover Effect */
        .sidebar-user a.sidebar-user-info:hover {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 0.5rem;
            margin: -0.5rem;
        }
        
        /* Notification Icon Button in Header */
        .notification-icon-btn {
            position: relative;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
        }
        
        .notification-icon-btn:hover {
            background: #f3f4f6;
            transform: scale(1.1);
        }
        
        .notification-icon-btn:active {
            transform: scale(0.95);
        }
        
        .notification-badge {
            position: absolute;
            top: 0.25rem;
            right: 0.25rem;
            background: #e53935;
            color: white;
            font-size: 0.65rem;
            padding: 0.15rem 0.4rem;
            border-radius: 10px;
            font-weight: 700;
            min-width: 18px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(229, 57, 53, 0.4);
        }
        
        /* Notification Panel Styles */
        .notification-panel {
            position: fixed;
            top: 0;
            right: -400px;
            width: 400px;
            height: 100vh;
            background: white;
            box-shadow: -4px 0 20px rgba(0, 0, 0, 0.15);
            z-index: 1001;
            transition: right 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        
        .notification-panel.active {
            right: 0;
        }
        
        .notification-panel-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, #1e3a5f 0%, #2e5984 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .notification-panel-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0;
        }
        
        .notification-panel-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            transition: background 0.3s ease;
        }
        
        .notification-panel-close:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .notification-panel-body {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
        }
        
        .notification-item {
            background: #f8f9fa;
            border-left: 4px solid #4CAF50;
            padding: 1rem;
            margin-bottom: 0.75rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .notification-item:hover {
            background: #e8f5e9;
            transform: translateX(4px);
        }
        
        .notification-item.unread {
            background: #e3f2fd;
            border-left-color: #2196F3;
        }
        
        .notification-item.unread:hover {
            background: #bbdefb;
        }
        
        .notification-message {
            color: #374151;
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
            line-height: 1.5;
        }
        
        .notification-time {
            color: #9ca3af;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .notification-empty {
            text-align: center;
            padding: 3rem 1rem;
            color: #9ca3af;
        }
        
        .notification-empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .notification-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        
        .notification-overlay.active {
            display: block;
        }
        
        @media (max-width: 576px) {
            .notification-panel {
                width: 100%;
                right: -100%;
            }
            
            .notification-icon-btn {
                width: 40px;
                height: 40px;
                font-size: 1.25rem;
            }
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
                    <a href="profile.php" class="header-profile-link">
                        <div class="header-user-avatar">
                            <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                        </div>
                        <span class="header-user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    </a>
                </div>
                <div class="top-header-right">
                    <button class="notification-icon-btn" id="notificationToggle" title="Notifications">
                        🔔
                        <?php if ($unread_count > 0): ?>
                            <span class="notification-badge"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </button>
                    <span style="color: #6b7280; font-size: 0.9rem;">
                        <?php echo date('l, F d, Y'); ?>
                    </span>
                </div>
            </header>
            
            <main class="content-wrapper">
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
    
    <!-- Notification Panel -->
    <div class="notification-overlay" id="notificationOverlay"></div>
    <div class="notification-panel" id="notificationPanel">
        <div class="notification-panel-header">
            <h3 class="notification-panel-title">🔔 Notifications</h3>
            <button class="notification-panel-close" id="closeNotifications">&times;</button>
        </div>
        <div class="notification-panel-body">
            <?php if ($notifications->num_rows > 0): ?>
                <?php 
                // Reset pointer to beginning
                $notifications->data_seek(0);
                while ($notif = $notifications->fetch_assoc()): 
                    $isUnread = ($notif['is_read'] == 0) ? 'unread' : '';
                ?>
                    <div class="notification-item <?php echo $isUnread; ?>" data-id="<?php echo $notif['notification_id']; ?>">
                        <div class="notification-message">
                            <?php echo htmlspecialchars($notif['message']); ?>
                        </div>
                        <div class="notification-time">
                            🕐 <?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="notification-empty">
                    <div class="notification-empty-icon">🔔</div>
                    <p>No notifications yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay"></div>
    
    <script src="../assets/js/sidebar.js"></script>
    <script>
        // Notification Panel Toggle
        const notificationToggle = document.getElementById('notificationToggle');
        const notificationPanel = document.getElementById('notificationPanel');
        const notificationOverlay = document.getElementById('notificationOverlay');
        const closeNotifications = document.getElementById('closeNotifications');
        
        function openNotifications(e) {
            e.preventDefault();
            notificationPanel.classList.add('active');
            notificationOverlay.classList.add('active');
        }
        
        function closeNotificationsPanel() {
            notificationPanel.classList.remove('active');
            notificationOverlay.classList.remove('active');
        }
        
        notificationToggle.addEventListener('click', openNotifications);
        closeNotifications.addEventListener('click', closeNotificationsPanel);
        notificationOverlay.addEventListener('click', closeNotificationsPanel);
        
        // Mark notification as read when clicked
        document.querySelectorAll('.notification-item.unread').forEach(item => {
            item.addEventListener('click', function() {
                const notifId = this.getAttribute('data-id');
                // Send AJAX request to mark as read
                fetch('mark_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'notification_id=' + notifId
                }).then(() => {
                    this.classList.remove('unread');
                    // Update badge count in header
                    const badge = document.querySelector('.notification-badge');
                    if (badge) {
                        let count = parseInt(badge.textContent) - 1;
                        if (count <= 0) {
                            badge.remove();
                        } else {
                            badge.textContent = count;
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>

<?php
$stmt->close();
$stmt_notif->close();
$stmt_unread->close();
closeDBConnection($conn);
?>
