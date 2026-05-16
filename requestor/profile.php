<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('requestor');

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Fetch user information
$stmt = $conn->prepare("SELECT username, email, created_at FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle password change
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'New passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $error_message = 'New password must be at least 6 characters long.';
    } else {
        // Verify current password
        $stmt_verify = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt_verify->bind_param("i", $user_id);
        $stmt_verify->execute();
        $result = $stmt_verify->get_result();
        $user_data = $result->fetch_assoc();
        
        if (password_verify($current_password, $user_data['password'])) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt_update->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt_update->execute()) {
                $success_message = 'Password changed successfully!';
            } else {
                $error_message = 'Failed to update password. Please try again.';
            }
            $stmt_update->close();
        } else {
            $error_message = 'Current password is incorrect.';
        }
        $stmt_verify->close();
    }
}

// Count unread notifications for header
$stmt_unread = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt_unread->bind_param("i", $user_id);
$stmt_unread->execute();
$unread_result = $stmt_unread->get_result();
$unread_count = $unread_result->fetch_assoc()['unread_count'];

// Fetch notifications for panel
$stmt_notif = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt_notif->bind_param("i", $user_id);
$stmt_notif->execute();
$notifications = $stmt_notif->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>My Profile - Training Lab Schedule</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/responsive-utilities.css">
    <style>
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
        
        /* Profile Page Styles */
        .profile-header {
            background: linear-gradient(135deg, #1e3a5f 0%, #2e5984 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .profile-avatar-large {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #4CAF50 0%, #66bb6a 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 700;
            box-shadow: 0 8px 20px rgba(76, 175, 80, 0.4);
        }
        
        .profile-info h2 {
            margin: 0 0 0.5rem 0;
            font-size: 2rem;
        }
        
        .profile-info p {
            margin: 0.25rem 0;
            opacity: 0.9;
        }
        
        .form-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .form-section h3 {
            color: #1e3a5f;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-info h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="../index.php" class="sidebar-logo">
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
                    <a href="../index.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon">📅</span>
                        <span class="sidebar-nav-text">View Schedule</span>
                    </a>
                    <a href="profile.php" class="sidebar-nav-item active">
                        <span class="sidebar-nav-icon">👤</span>
                        <span class="sidebar-nav-text">My Profile</span>
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
                    <h1 class="page-title">My Profile</h1>
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
                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="profile-avatar-large">
                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                    </div>
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($user['username']); ?></h2>
                        <p>📧 <?php echo htmlspecialchars($user['email']); ?></p>
                        <p>👤 Role: Requestor</p>
                        <p>📅 Member since: <?php echo date('F d, Y', strtotime($user['created_at'])); ?></p>
                    </div>
                </div>
                
                <!-- Change Password Section -->
                <div class="form-section">
                    <h3>🔒 Change Password</h3>
                    
                    <?php if ($success_message): ?>
                        <div class="alert alert-success">✓ <?php echo $success_message; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-error">✗ <?php echo $error_message; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="current_password">Current Password *</label>
                            <input type="password" id="current_password" name="current_password" required 
                                   class="form-control" placeholder="Enter your current password">
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password *</label>
                            <input type="password" id="new_password" name="new_password" required 
                                   class="form-control" placeholder="Enter new password (min. 6 characters)" minlength="6">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" required 
                                   class="form-control" placeholder="Re-enter new password">
                        </div>
                        
                        <div class="form-actions" style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                            <button type="submit" name="change_password" class="btn btn-primary">
                                Change Password
                            </button>
                            <a href="../index.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Notification Panel (same as dashboard) -->
    <div class="notification-overlay" id="notificationOverlay"></div>
    <div class="notification-panel" id="notificationPanel" style="position: fixed; top: 0; right: -400px; width: 400px; height: 100vh; background: white; box-shadow: -4px 0 20px rgba(0, 0, 0, 0.15); z-index: 1001; transition: right 0.3s ease; display: flex; flex-direction: column;">
        <div class="notification-panel-header" style="padding: 1.5rem; background: linear-gradient(135deg, #1e3a5f 0%, #2e5984 100%); color: white; display: flex; justify-content: space-between; align-items: center;">
            <h3 class="notification-panel-title" style="font-size: 1.25rem; font-weight: 700; margin: 0;">🔔 Notifications</h3>
            <button class="notification-panel-close" id="closeNotifications" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; padding: 0.25rem 0.5rem; border-radius: 6px;">&times;</button>
        </div>
        <div class="notification-panel-body" style="flex: 1; overflow-y: auto; padding: 1rem;">
            <?php if ($notifications->num_rows > 0): ?>
                <?php while ($notif = $notifications->fetch_assoc()): 
                    $isUnread = ($notif['is_read'] == 0) ? 'unread' : '';
                ?>
                    <div class="notification-item <?php echo $isUnread; ?>" data-id="<?php echo $notif['notification_id']; ?>" style="background: <?php echo $isUnread ? '#e3f2fd' : '#f8f9fa'; ?>; border-left: 4px solid <?php echo $isUnread ? '#2196F3' : '#4CAF50'; ?>; padding: 1rem; margin-bottom: 0.75rem; border-radius: 8px; cursor: pointer;">
                        <div class="notification-message" style="color: #374151; font-size: 0.95rem; margin-bottom: 0.5rem;">
                            <?php echo htmlspecialchars($notif['message']); ?>
                        </div>
                        <div class="notification-time" style="color: #9ca3af; font-size: 0.8rem;">
                            🕐 <?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="notification-empty" style="text-align: center; padding: 3rem 1rem; color: #9ca3af;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🔔</div>
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
            notificationPanel.style.right = '0';
            notificationOverlay.style.display = 'block';
        }
        
        function closeNotificationsPanel() {
            notificationPanel.style.right = '-400px';
            notificationOverlay.style.display = 'none';
        }
        
        notificationToggle.addEventListener('click', openNotifications);
        closeNotifications.addEventListener('click', closeNotificationsPanel);
        notificationOverlay.addEventListener('click', closeNotificationsPanel);
        
        // Mark notification as read when clicked
        document.querySelectorAll('.notification-item.unread').forEach(item => {
            item.addEventListener('click', function() {
                const notifId = this.getAttribute('data-id');
                fetch('mark_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'notification_id=' + notifId
                }).then(() => {
                    this.classList.remove('unread');
                    this.style.background = '#f8f9fa';
                    this.style.borderLeftColor = '#4CAF50';
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
$stmt_unread->close();
$stmt_notif->close();
closeDBConnection($conn);
?>
