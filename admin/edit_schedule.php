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
    <title>Edit Schedule</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>Training Laboratory Schedule</h1>
            <nav>
                <span style="color: white; margin-right: 1rem;">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="../logout.php" class="btn-login">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="dashboard-header">
            <h2>Edit Schedule</h2>
        </div>

        <div class="dashboard-nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="pending_requests.php">Pending Requests</a>
            <a href="approved_schedules.php" class="active">Manage Schedules</a>
        </div>

        <div class="card">
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
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Training Laboratory Schedule System</p>
        </div>
    </footer>
</body>
</html>

<?php
$stmt->close();
closeDBConnection($conn);
?>
