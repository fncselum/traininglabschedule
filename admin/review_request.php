<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireAnyRole(['admin', 'superadmin']);

$request_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success = '';
$error = '';

$conn = getDBConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        // Get request data
        $start_date = $_POST['start_date'];
        $title = trim($_POST['title']);
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $participants = trim($_POST['participants']);
        $program_owner = trim($_POST['program_owner']);
        $office = trim($_POST['office']);
        $user_id = $_SESSION['user_id'];
        
        // Validation
        if (strtotime($start_date) < strtotime(date('Y-m-d'))) {
            $error = 'Start date cannot be in the past.';
        } elseif (strtotime($start_time) >= strtotime($end_time)) {
            $error = 'End time must be after start time.';
        } else {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Insert into approved_schedules
                $stmt = $conn->prepare("INSERT INTO approved_schedules (request_id, start_date, title, start_time, end_time, participants, program_owner, office, approved_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssssssi", $request_id, $start_date, $title, $start_time, $end_time, $participants, $program_owner, $office, $user_id);
                $stmt->execute();
                
                // Update request status
                $stmt2 = $conn->prepare("UPDATE schedule_requests SET status = 'approved' WHERE request_id = ?");
                $stmt2->bind_param("i", $request_id);
                $stmt2->execute();
                
                // Get requestor ID
                $stmt3 = $conn->prepare("SELECT requestor_id FROM schedule_requests WHERE request_id = ?");
                $stmt3->bind_param("i", $request_id);
                $stmt3->execute();
                $result = $stmt3->get_result();
                $requestor = $result->fetch_assoc();
                
                // Notify requestor
                $stmt4 = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'request_approved')");
                $message = "Your schedule request for '" . $title . "' has been approved!";
                $stmt4->bind_param("is", $requestor['requestor_id'], $message);
                $stmt4->execute();
                
                $conn->commit();
                $success = 'Schedule request approved successfully!';
                
                $stmt->close();
                $stmt2->close();
                $stmt3->close();
                $stmt4->close();
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Failed to approve request. Please try again.';
            }
        }
    } elseif ($action === 'reject') {
        $rejection_reason = trim($_POST['rejection_reason']);
        
        if (empty($rejection_reason)) {
            $error = 'Please provide a reason for rejection.';
        } else {
            // Update request status
            $stmt = $conn->prepare("UPDATE schedule_requests SET status = 'rejected', rejection_reason = ? WHERE request_id = ?");
            $stmt->bind_param("si", $rejection_reason, $request_id);
            
            if ($stmt->execute()) {
                // Get requestor ID
                $stmt2 = $conn->prepare("SELECT requestor_id, title FROM schedule_requests WHERE request_id = ?");
                $stmt2->bind_param("i", $request_id);
                $stmt2->execute();
                $result = $stmt2->get_result();
                $request_data = $result->fetch_assoc();
                
                // Notify requestor
                $stmt3 = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'request_rejected')");
                $message = "Your schedule request for '" . $request_data['title'] . "' has been rejected. Reason: " . $rejection_reason;
                $stmt3->bind_param("is", $request_data['requestor_id'], $message);
                $stmt3->execute();
                
                $success = 'Schedule request rejected.';
                
                $stmt2->close();
                $stmt3->close();
            } else {
                $error = 'Failed to reject request. Please try again.';
            }
            
            $stmt->close();
        }
    }
}

// Fetch request details
$stmt = $conn->prepare("SELECT sr.*, u.username FROM schedule_requests sr JOIN users u ON sr.requestor_id = u.user_id WHERE sr.request_id = ?");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: dashboard.php');
    exit();
}

$request = $result->fetch_assoc();

// Check if already processed
if ($request['status'] !== 'pending') {
    header('Location: dashboard.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Request</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script>
        function showRejectForm() {
            document.getElementById('approve-form').style.display = 'none';
            document.getElementById('reject-form').style.display = 'block';
        }
        
        function showApproveForm() {
            document.getElementById('reject-form').style.display = 'none';
            document.getElementById('approve-form').style.display = 'block';
        }
    </script>
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
            <h2>Review Schedule Request</h2>
        </div>

        <div class="dashboard-nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="pending_requests.php" class="active">Pending Requests</a>
            <a href="approved_schedules.php">Manage Schedules</a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h3>Request Details</h3>
                    <p style="margin: 0.5rem 0 0 0; color: #7f8c8d;">Submitted by: <?php echo htmlspecialchars($request['username']); ?> on <?php echo date('F d, Y h:i A', strtotime($request['created_at'])); ?></p>
                </div>

                <div id="approve-form">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="approve">
                        
                        <div class="form-group">
                            <label for="start_date">Start Date *</label>
                            <input type="date" id="start_date" name="start_date" value="<?php echo $request['start_date']; ?>" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="title">Training Title *</label>
                            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($request['title']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="start_time">Start Time *</label>
                            <input type="time" id="start_time" name="start_time" value="<?php echo $request['start_time']; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="end_time">End Time *</label>
                            <input type="time" id="end_time" name="end_time" value="<?php echo $request['end_time']; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="participants">Participants *</label>
                            <textarea id="participants" name="participants" required><?php echo htmlspecialchars($request['participants']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="program_owner">Program Owner *</label>
                            <input type="text" id="program_owner" name="program_owner" value="<?php echo htmlspecialchars($request['program_owner']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="office">Office *</label>
                            <input type="text" id="office" name="office" value="<?php echo htmlspecialchars($request['office']); ?>" required>
                        </div>
                        
                        <div class="action-buttons">
                            <button type="submit" class="btn btn-success">Approve Request</button>
                            <button type="button" class="btn btn-danger" onclick="showRejectForm()">Reject Request</button>
                            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>

                <div id="reject-form" style="display: none;">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="reject">
                        
                        <div class="form-group">
                            <label for="rejection_reason">Rejection Reason *</label>
                            <textarea id="rejection_reason" name="rejection_reason" required placeholder="Please provide a reason for rejecting this request"></textarea>
                        </div>
                        
                        <div class="action-buttons">
                            <button type="submit" class="btn btn-danger">Confirm Rejection</button>
                            <button type="button" class="btn btn-secondary" onclick="showApproveForm()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
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
