<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('requestor');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_date = $_POST['start_date'];
    $title = trim($_POST['title']);
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $participants = trim($_POST['participants']);
    $program_owner = trim($_POST['program_owner']);
    $office = trim($_POST['office']);
    $user_id = $_SESSION['user_id'];
    
    // Validation
    if (empty($start_date) || empty($title) || empty($start_time) || empty($end_time) || 
        empty($participants) || empty($program_owner) || empty($office)) {
        $error = 'All fields are required.';
    } elseif (strtotime($start_date) < strtotime(date('Y-m-d'))) {
        $error = 'Start date cannot be in the past.';
    } elseif (strtotime($start_time) >= strtotime($end_time)) {
        $error = 'End time must be after start time.';
    } else {
        $conn = getDBConnection();
        
        // Insert schedule request
        $stmt = $conn->prepare("INSERT INTO schedule_requests (requestor_id, start_date, title, start_time, end_time, participants, program_owner, office, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("isssssss", $user_id, $start_date, $title, $start_time, $end_time, $participants, $program_owner, $office);
        
        if ($stmt->execute()) {
            // Notify all admins
            $admin_stmt = $conn->prepare("SELECT user_id FROM users WHERE role IN ('admin', 'superadmin')");
            $admin_stmt->execute();
            $admins = $admin_stmt->get_result();
            
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'request_submitted')");
            $message = "New schedule request submitted by " . $_SESSION['username'] . " for " . $title;
            
            while ($admin = $admins->fetch_assoc()) {
                $notif_stmt->bind_param("is", $admin['user_id'], $message);
                $notif_stmt->execute();
            }
            
            $success = 'Schedule request submitted successfully!';
            
            $admin_stmt->close();
            $notif_stmt->close();
        } else {
            $error = 'Failed to submit request. Please try again.';
        }
        
        $stmt->close();
        closeDBConnection($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Request - Training Lab Schedule</title>
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
                    <a href="dashboard.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon">📊</span>
                        <span class="sidebar-nav-text">Dashboard</span>
                    </a>
                    <a href="submit_request.php" class="sidebar-nav-item active">
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
                    <h1 class="page-title">Submit Schedule Request</h1>
                </div>
                <div class="top-header-right">
                    <span style="color: #6b7280; font-size: 0.9rem;">
                        <?php echo date('l, F d, Y'); ?>
                    </span>
                </div>
            </header>
            
            <main class="content-wrapper">
                <div class="content-card">
                    <div class="content-card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                            <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
                        <?php else: ?>
                            <?php if ($error): ?>
                                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <div class="form-group">
                                    <label for="start_date">Start Date *</label>
                                    <input type="date" id="start_date" name="start_date" required min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="title">Training Title *</label>
                                    <input type="text" id="title" name="title" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="start_time">Start Time *</label>
                                    <input type="time" id="start_time" name="start_time" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="end_time">End Time *</label>
                                    <input type="time" id="end_time" name="end_time" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="participants">Participants *</label>
                                    <textarea id="participants" name="participants" required placeholder="Enter participant names or groups"></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="program_owner">Program Owner *</label>
                                    <input type="text" id="program_owner" name="program_owner" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="office">Office *</label>
                                    <input type="text" id="office" name="office" required>
                                </div>
                                
                                <div class="action-buttons">
                                    <button type="submit" class="btn btn-primary">Submit Request</button>
                                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                                </div>
                            </form>
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
