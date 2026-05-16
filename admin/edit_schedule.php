<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireAnyRole(['admin', 'superadmin']);

$schedule_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success = '';
$error = '';

$conn = getDBConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_date = $_POST['start_date'];
    $title = trim($_POST['title']);
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $participants = trim($_POST['participants']);
    $program_owner = trim($_POST['program_owner']);
    $office = trim($_POST['office']);
    
    // Validation
    if (empty($start_date) || empty($title) || empty($start_time) || empty($end_time) || 
        empty($participants) || empty($program_owner) || empty($office)) {
        $error = 'All fields are required.';
    } elseif (strtotime($start_date) < strtotime(date('Y-m-d'))) {
        $error = 'Start date cannot be in the past.';
    } elseif (strtotime($start_time) >= strtotime($end_time)) {
        $error = 'End time must be after start time.';
    } else {
        $stmt = $conn->prepare("UPDATE approved_schedules SET start_date = ?, title = ?, start_time = ?, end_time = ?, participants = ?, program_owner = ?, office = ? WHERE schedule_id = ?");
        $stmt->bind_param("sssssssi", $start_date, $title, $start_time, $end_time, $participants, $program_owner, $office, $schedule_id);
        
        if ($stmt->execute()) {
            $success = 'Schedule updated successfully!';
        } else {
            $error = 'Failed to update schedule. Please try again.';
        }
        
        $stmt->close();
    }
}

// Fetch schedule details
$stmt = $conn->prepare("SELECT * FROM approved_schedules WHERE schedule_id = ?");
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: approved_schedules.php');
    exit();
}

$schedule = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Schedule - Training Lab Schedule</title>
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
                        <div class="sidebar-user-role"><?php echo ucfirst($_SESSION['role']); ?></div>
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
                    <a href="pending_requests.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon">⏳</span>
                        <span class="sidebar-nav-text">Pending Requests</span>
                    </a>
                    <a href="approved_schedules.php" class="sidebar-nav-item active">
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
                    <h1 class="page-title">Edit Schedule</h1>
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
                        <h3 class="content-card-title">Edit Schedule Details</h3>
                    </div>
                    <div class="content-card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                            <a href="approved_schedules.php" class="btn btn-primary">Back to Schedules</a>
                        <?php else: ?>
                            <?php if ($error): ?>
                                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <div class="form-group">
                                    <label for="start_date">Start Date *</label>
                                    <input type="date" id="start_date" name="start_date" value="<?php echo $schedule['start_date']; ?>" required min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="title">Training Title *</label>
                                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($schedule['title']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="start_time">Start Time *</label>
                                    <input type="time" id="start_time" name="start_time" value="<?php echo $schedule['start_time']; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="end_time">End Time *</label>
                                    <input type="time" id="end_time" name="end_time" value="<?php echo $schedule['end_time']; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="participants">Participants *</label>
                                    <textarea id="participants" name="participants" required><?php echo htmlspecialchars($schedule['participants']); ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="program_owner">Program Owner *</label>
                                    <input type="text" id="program_owner" name="program_owner" value="<?php echo htmlspecialchars($schedule['program_owner']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="office">Office *</label>
                                    <input type="text" id="office" name="office" value="<?php echo htmlspecialchars($schedule['office']); ?>" required>
                                </div>
                                
                                <div class="action-buttons">
                                    <button type="submit" class="btn btn-primary">Update Schedule</button>
                                    <a href="approved_schedules.php" class="btn btn-secondary">Cancel</a>
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

<?php
$stmt->close();
closeDBConnection($conn);
?>
