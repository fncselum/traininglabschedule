<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('requestor');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deped_email = trim($_POST['deped_email']);
    $start_date = $_POST['start_date'];
    $title = trim($_POST['title']);
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $participants = trim($_POST['participants']);
    $program_owner = trim($_POST['program_owner']);
    $office = trim($_POST['office']);
    $remarks = trim($_POST['remarks']);
    $user_id = $_SESSION['user_id'];
    
    // Validation
    if (empty($deped_email) || empty($start_date) || empty($title) || empty($start_time) || empty($end_time) || 
        empty($participants) || empty($program_owner) || empty($office) || empty($remarks)) {
        $error = 'All fields are required.';
    } elseif (!preg_match('/@deped\.gov\.ph$/', $deped_email)) {
        $error = 'Please enter a valid DepEd email address (@deped.gov.ph).';
    } elseif (strtotime($start_date) < strtotime(date('Y-m-d'))) {
        $error = 'Start date cannot be in the past.';
    } elseif (strtotime($start_time) >= strtotime($end_time)) {
        $error = 'End time must be after start time.';
    } else {
        $conn = getDBConnection();
        
        // Check for time conflicts with existing approved schedules on the same date
        $conflict_check = $conn->prepare("
            SELECT COUNT(*) as conflict_count 
            FROM approved_schedules 
            WHERE start_date = ? 
            AND (
                (start_time <= ? AND end_time > ?) OR
                (start_time < ? AND end_time >= ?) OR
                (start_time >= ? AND end_time <= ?)
            )
        ");
        $conflict_check->bind_param("sssssss", $start_date, $start_time, $start_time, $end_time, $end_time, $start_time, $end_time);
        $conflict_check->execute();
        $conflict_result = $conflict_check->get_result();
        $conflict_data = $conflict_result->fetch_assoc();
        $has_conflict = $conflict_data['conflict_count'] > 0;
        $conflict_check->close();
        
        // Determine status: auto-approve if no conflict, pending if conflict exists
        $status = $has_conflict ? 'pending' : 'approved';
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert schedule request
            $stmt = $conn->prepare("INSERT INTO schedule_requests (requestor_id, deped_email, start_date, title, start_time, end_time, participants, program_owner, office, remarks, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssssssss", $user_id, $deped_email, $start_date, $title, $start_time, $end_time, $participants, $program_owner, $office, $remarks, $status);
            $stmt->execute();
            $request_id = $conn->insert_id;
            
            if ($status === 'approved') {
                // Auto-approve: Insert directly into approved_schedules
                $approve_stmt = $conn->prepare("INSERT INTO approved_schedules (request_id, start_date, title, start_time, end_time, participants, program_owner, office, approved_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $approve_stmt->bind_param("isssssssi", $request_id, $start_date, $title, $start_time, $end_time, $participants, $program_owner, $office, $user_id);
                $approve_stmt->execute();
                $approve_stmt->close();
                
                // Notify requestor of auto-approval
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'request_approved')");
                $message = "Your schedule request for '" . $title . "' has been automatically approved! No time conflicts detected.";
                $notif_stmt->bind_param("is", $user_id, $message);
                $notif_stmt->execute();
                $notif_stmt->close();
                
                $success = 'Schedule request automatically approved! No time conflicts detected.';
            } else {
                // Notify all admins about pending request (conflict detected)
                $admin_stmt = $conn->prepare("SELECT user_id FROM users WHERE role IN ('admin', 'superadmin')");
                $admin_stmt->execute();
                $admins = $admin_stmt->get_result();
                
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'request_submitted')");
                $message = "New schedule request submitted by " . $_SESSION['username'] . " for " . $title . " (Time conflict detected - requires review)";
                
                while ($admin = $admins->fetch_assoc()) {
                    $notif_stmt->bind_param("is", $admin['user_id'], $message);
                    $notif_stmt->execute();
                }
                
                $admin_stmt->close();
                $notif_stmt->close();
                
                $success = 'Schedule request submitted for admin review. A time conflict was detected with an existing schedule.';
            }
            
            $conn->commit();
            $stmt->close();
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Failed to submit request. Please try again.';
        }
        
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
                    <a href="profile.php" class="header-profile-link">
                        <div class="header-user-avatar">
                            <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                        </div>
                        <span class="header-user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    </a>
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
