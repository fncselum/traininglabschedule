<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('requestor');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schedule_id = intval($_POST['schedule_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $requestor_id = $_SESSION['user_id'];
    
    // Validation
    if (empty($schedule_id) || empty($reason)) {
        $_SESSION['flash_message'] = 'Please provide both the schedule ID and reason for cancellation.';
        $_SESSION['flash_type'] = 'error';
        header('Location: ../index.php');
        exit();
    }
    
    $conn = getDBConnection();
    
    if (!$conn) {
        $_SESSION['flash_message'] = 'Database connection failed. Please try again later.';
        $_SESSION['flash_type'] = 'error';
        header('Location: ../index.php');
        exit();
    }
    
    try {
        // Verify that the schedule belongs to the current requestor
        $verify_stmt = $conn->prepare("
            SELECT a.title, a.start_date, a.start_time, sr.requestor_id 
            FROM approved_schedules a
            LEFT JOIN schedule_requests sr ON a.request_id = sr.request_id
            WHERE a.schedule_id = ?
        ");
        
        if (!$verify_stmt) {
            throw new Exception("Failed to prepare verification query: " . $conn->error);
        }
        $verify_stmt->bind_param("i", $schedule_id);
        $verify_stmt->execute();
        $schedule = $verify_stmt->get_result()->fetch_assoc();
        $verify_stmt->close();
        
        if (!$schedule) {
            $_SESSION['flash_message'] = 'The requested schedule could not be found. Please verify the schedule details.';
            $_SESSION['flash_type'] = 'error';
            header('Location: ../index.php');
            exit();
        }
        
        if ($schedule['requestor_id'] != $requestor_id) {
            $_SESSION['flash_message'] = 'You can only request cancellation for schedules that you have created.';
            $_SESSION['flash_type'] = 'error';
            header('Location: ../index.php');
            exit();
        }
        
        // Check if there's already a pending cancellation request
        $existing_stmt = $conn->prepare("
            SELECT cancellation_id FROM cancellation_requests 
            WHERE schedule_id = ? AND status = 'pending'
        ");
        
        if (!$existing_stmt) {
            throw new Exception("Failed to prepare existing request query: " . $conn->error);
        }
        $existing_stmt->bind_param("i", $schedule_id);
        $existing_stmt->execute();
        $existing = $existing_stmt->get_result()->fetch_assoc();
        $existing_stmt->close();
        
        if ($existing) {
            $_SESSION['flash_message'] = 'A cancellation request for this schedule is already pending administrative review.';
            $_SESSION['flash_type'] = 'error';
            header('Location: ../index.php');
            exit();
        }
        
        // Insert cancellation request
        $insert_stmt = $conn->prepare("
            INSERT INTO cancellation_requests (schedule_id, requestor_id, reason, status, created_at)
            VALUES (?, ?, ?, 'pending', NOW())
        ");
        
        if (!$insert_stmt) {
            throw new Exception("Failed to prepare insert query: " . $conn->error);
        }
        $insert_stmt->bind_param("iis", $schedule_id, $requestor_id, $reason);
        $insert_stmt->execute();
        $insert_stmt->close();
        
        // Notify all admins about the cancellation request
        $admin_stmt = $conn->prepare("SELECT user_id FROM users WHERE role IN ('admin', 'superadmin')");
        
        if (!$admin_stmt) {
            throw new Exception("Failed to prepare admin query: " . $conn->error);
        }
        
        $admin_stmt->execute();
        $admins = $admin_stmt->get_result();
        
        $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'request_submitted')");
        
        if (!$notif_stmt) {
            throw new Exception("Failed to prepare notification query: " . $conn->error);
        }
        $schedule_date = date('F d, Y', strtotime($schedule['start_date']));
        $schedule_time = date('h:i A', strtotime($schedule['start_time']));
        $notif_message = "Schedule Cancellation Request - '{$schedule['title']}' scheduled for {$schedule_date} at {$schedule_time}. Request submitted by " . $_SESSION['username'] . " with reason: " . substr($reason, 0, 100) . (strlen($reason) > 100 ? '...' : '') . ". Please review this request and take appropriate administrative action.";
        
        while ($admin = $admins->fetch_assoc()) {
            $notif_stmt->bind_param("is", $admin['user_id'], $notif_message);
            $notif_stmt->execute();
        }
        
        $admin_stmt->close();
        $notif_stmt->close();
        
        $_SESSION['flash_message'] = 'Your cancellation request has been submitted successfully and is currently under administrative review. You will be notified once a decision has been made regarding your request. Thank you for your patience.';
        $_SESSION['flash_type'] = 'success';
        
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Unable to submit your cancellation request. Please try again or contact system support.';
        $_SESSION['flash_type'] = 'error';
        error_log("Cancellation request error: " . $e->getMessage());
    }
    
    closeDBConnection($conn);
}

header('Location: ../index.php');
exit();
?>