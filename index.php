<?php
require_once 'config/database.php';
require_once 'config/session.php';

// Check if user is logged in and get their role
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = isset($_SESSION['role']) ? $_SESSION['role'] : null;
$isRequestor = ($isLoggedIn && $userRole === 'requestor');

// Handle form submission for new schedule request (for requestors)
$error = '';
if ($isRequestor && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_date'])) {
    $conn = getDBConnection();
    $user_id = $_SESSION['user_id'];
    
    $deped_email = trim($_POST['deped_email']);
    $start_date = $_POST['start_date'];
    $title = trim($_POST['title']);
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $participants = trim($_POST['participants']);
    $program_owner = trim($_POST['program_owner']);
    $office = trim($_POST['office']);
    $remarks = trim($_POST['remarks']);
    
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
                
                // Notify requestor of auto-approval via email
                $subject = "Schedule Request Approved - Training Laboratory";
                $message = "Your schedule request for '$title' has been automatically approved!\n\n";
                $message .= "Date: " . date('F d, Y', strtotime($start_date)) . "\n";
                $message .= "Time: " . date('h:i A', strtotime($start_time)) . " - " . date('h:i A', strtotime($end_time)) . "\n";
                $message .= "No time conflicts detected.\n\n";
                $message .= "Thank you for using the Training Laboratory Schedule System.";
                
                mail($deped_email, $subject, $message, "From: noreply@traininglabschedule.local");
                
                // Notify requestor in-app
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'request_approved')");
                $notif_message = "Your schedule request for '$title' has been automatically approved! No time conflicts detected.";
                $notif_stmt->bind_param("is", $user_id, $notif_message);
                $notif_stmt->execute();
                $notif_stmt->close();
            } else {
                // Notify all admins about pending request (conflict detected)
                $admin_stmt = $conn->prepare("SELECT user_id, email FROM users WHERE role IN ('admin', 'superadmin')");
                $admin_stmt->execute();
                $admins = $admin_stmt->get_result();
                
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'request_submitted')");
                $notif_message = "New schedule request submitted by " . $_SESSION['username'] . " for $title (Time conflict detected - requires review)";
                
                while ($admin = $admins->fetch_assoc()) {
                    $notif_stmt->bind_param("is", $admin['user_id'], $notif_message);
                    $notif_stmt->execute();
                }
                
                $admin_stmt->close();
                $notif_stmt->close();
            }
            
            $conn->commit();
            $stmt->close();
            
            // Show success notification and redirect
            $successMessage = $status === 'approved' 
                ? 'Schedule Booked Successfully! ✓' 
                : 'Schedule Submitted for Review';
            
            // Store in session for display
            $_SESSION['booking_success'] = $successMessage;
            $_SESSION['booking_status'] = $status;
            
            closeDBConnection($conn);
            
            // Redirect to calendar view after successful submission
            header('Location: index.php?success=1');
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Failed to submit request. Please try again.';
        }
    }
    
    closeDBConnection($conn);
}

// Check for success message from booking
$showSuccessMessage = isset($_GET['success']) && $_GET['success'] == 1;
$successMessage = isset($_SESSION['booking_success']) ? $_SESSION['booking_success'] : 'Schedule booked successfully!';
$bookingStatus = isset($_SESSION['booking_status']) ? $_SESSION['booking_status'] : 'approved';

// Clear the session variables after retrieving them
if ($showSuccessMessage) {
    unset($_SESSION['booking_success']);
    unset($_SESSION['booking_status']);
}

// Get current month and year
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Validate month and year
if ($currentMonth < 1) $currentMonth = 12;
if ($currentMonth > 12) $currentMonth = 1;

// Philippine Holidays (Regular and Special Non-Working Days)
// Format: 'MM-DD' => 'Holiday Name'
$holidays = [
    // Fixed Regular Holidays
    '01-01' => 'New Year\'s Day',
    '04-09' => 'Araw ng Kagitingan',
    '05-01' => 'Labor Day',
    '06-12' => 'Independence Day',
    '08-21' => 'Ninoy Aquino Day',
    '08-26' => 'National Heroes Day',
    '11-30' => 'Bonifacio Day',
    '12-25' => 'Christmas Day',
    '12-30' => 'Rizal Day',
    '12-31' => 'New Year\'s Eve',
    
    // Common Movable Holidays (2024-2026 dates)
    // Maundy Thursday & Good Friday (varies each year)
    '03-28' => 'Maundy Thursday', // 2024
    '03-29' => 'Good Friday', // 2024
    '04-17' => 'Maundy Thursday', // 2025
    '04-18' => 'Good Friday', // 2025
    '04-09' => 'Maundy Thursday', // 2026
    '04-10' => 'Good Friday', // 2026
    
    // Eid'l Fitr (varies each year - Islamic calendar)
    '04-10' => 'Eid\'l Fitr', // 2024 (approximate)
    '03-31' => 'Eid\'l Fitr', // 2025 (approximate)
    
    // Eid'l Adha (varies each year - Islamic calendar)
    '06-17' => 'Eid\'l Adha', // 2024 (approximate)
    '06-07' => 'Eid\'l Adha', // 2025 (approximate)
    
    // All Saints' Day
    '11-01' => 'All Saints\' Day',
    '11-02' => 'All Souls\' Day',
    
    // Special Non-Working Days
    '02-25' => 'EDSA Revolution Anniversary',
    '08-21' => 'Ninoy Aquino Day',
    '12-08' => 'Feast of the Immaculate Conception',
];

// Function to check if a date is a holiday
function isHoliday($day, $month, $holidays) {
    $dateKey = sprintf('%02d-%02d', $month, $day);
    return isset($holidays[$dateKey]) ? $holidays[$dateKey] : false;
}

// Fetch all approved schedules for the current month
$conn = getDBConnection();
$startDate = "$currentYear-$currentMonth-01";
$endDate = date('Y-m-t', strtotime($startDate));

$sql = "SELECT * FROM approved_schedules 
        WHERE DATE(start_date) BETWEEN '$startDate' AND '$endDate'
        ORDER BY start_date ASC, start_time ASC";
$result = $conn->query($sql);

// Group schedules by day
$schedulesByDay = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $day = (int)date('d', strtotime($row['start_date']));
        if (!isset($schedulesByDay[$day])) {
            $schedulesByDay[$day] = [];
        }
        $schedulesByDay[$day][] = $row;
    }
}

// Get number of days in current month and first day of week
$daysInMonth = (int)date('t', strtotime($startDate));
$firstDayOfWeek = (int)date('w', strtotime($startDate)); // 0 = Sunday, 6 = Saturday
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="description" content="Training Laboratory Schedule System - View approved training schedules">
    <meta name="theme-color" content="#1e3a5f">
    <title>Training Laboratory Schedule</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            background: #f8f9fa;
        }
        
        /* Compact Header */
        header {
            background: linear-gradient(135deg, #1e3a5f 0%, #2e5984 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            flex-shrink: 0;
        }
        
        .header-content {
            max-width: 1600px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        header h1 {
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .btn-login {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.5rem 1.25rem;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .btn-login:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }
        
        /* Main Calendar Container */
        .calendar-wrapper {
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            padding: 1rem 1.5rem;
            max-width: 1600px;
            margin: 0 auto;
            width: 100%;
        }
        
        /* Calendar Header with Navigation */
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-shrink: 0;
        }
        
        .calendar-header h2 {
            font-size: 1.75rem;
            color: #1e3a5f;
            font-weight: 700;
        }
        
        .calendar-nav {
            display: flex;
            gap: 0.5rem;
        }
        
        .calendar-nav a {
            padding: 0.5rem 1rem;
            background: #4CAF50;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .calendar-nav a:hover {
            background: #43a047;
            transform: translateY(-1px);
        }
        
        /* Weekday Headers */
        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            flex-shrink: 0;
        }
        
        .weekday-header {
            background: #1e3a5f;
            color: white;
            padding: 0.5rem;
            text-align: center;
            font-weight: 600;
            border-radius: 6px;
            font-size: 0.875rem;
        }
        
        .weekday-header.weekend {
            background: #6b7280;
        }
        
        /* Calendar Grid - Fills remaining space */
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            grid-auto-rows: 1fr;
            gap: 0.5rem;
            flex: 1;
            overflow: hidden;
        }
        
        .calendar-day {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 0.4rem;
            background: white;
            overflow: hidden;
            transition: all 0.2s ease;
            display: flex;
            gap: 0.4rem;
            align-items: stretch;
            position: relative;
            min-height: 0;
        }
        
        .calendar-day:hover {
            border-color: #4CAF50;
            box-shadow: 0 2px 8px rgba(76, 175, 80, 0.2);
        }
        
        /* Plus button for requestors */
        .add-schedule-btn {
            position: absolute;
            top: 50%;
            right: 0.5rem;
            transform: translateY(-50%);
            width: 2rem;
            height: 2rem;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 1.25rem;
            font-weight: bold;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(76, 175, 80, 0.4);
            transition: all 0.3s ease;
            z-index: 10;
        }
        
        .calendar-day:hover .add-schedule-btn {
            display: flex;
        }
        
        .add-schedule-btn:hover {
            background: #43a047;
            transform: translateY(-50%) scale(1.1);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.6);
        }
        
        .add-schedule-btn:active {
            transform: translateY(-50%) scale(0.95);
        }
        
        /* Don't show plus button on holidays */
        .calendar-day.holiday .add-schedule-btn {
            display: none !important;
        }
        
        /* Saturday styling */
        .calendar-day.saturday {
            background: #f3f4f6;
            border-color: #d1d5db;
        }
        
        /* Sunday styling */
        .calendar-day.sunday {
            background: #fef3c7;
            border-color: #fbbf24;
        }
        
        .calendar-day.other-month {
            background: #f9fafb;
            opacity: 0.4;
        }
        
        .calendar-day-number {
            font-weight: 700;
            font-size: 0.85rem;
            color: #6b7280;
            flex-shrink: 0;
            width: 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding-top: 0.1rem;
        }
        
        .calendar-day.saturday .calendar-day-number {
            color: #4b5563;
        }
        
        .calendar-day.sunday .calendar-day-number {
            color: #d97706;
        }
        
        .calendar-day.holiday {
            background: #fef2f2;
            border-color: #fca5a5;
        }
        
        .calendar-day.holiday .calendar-day-number {
            color: #dc2626;
            font-weight: 700;
        }
        
        .calendar-day.holiday .calendar-day-schedules {
            background: linear-gradient(135deg, #fca5a5 0%, #f87171 100%);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem;
        }
        
        .holiday-badge {
            font-size: 0.65rem;
            margin-top: 0.1rem;
        }
        
        .holiday-name {
            font-size: 0.9rem;
            color: #000000;
            font-weight: 700;
            text-align: center;
            line-height: 1.3;
            margin: 0;
            padding: 0;
            background: transparent;
        }
        
        .calendar-day-schedules {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
            overflow: visible;
        }
        
        .calendar-day-schedules::-webkit-scrollbar {
            display: none;
        }
        
        .schedule-item {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            padding: 0.3rem 0.4rem;
            border-left: 3px solid #4CAF50;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.65rem;
            flex-shrink: 0;
        }
        
        .schedule-item:hover {
            background: linear-gradient(135deg, #c8e6c9 0%, #a5d6a7 100%);
            transform: translateX(2px);
        }
        
        .schedule-title {
            font-weight: 700;
            color: #1b5e20;
            margin-bottom: 0.15rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 0.7rem;
        }
        
        .schedule-time {
            color: #666;
            font-size: 0.6rem;
        }
        
        .empty-day {
            color: #cbd5e0;
            text-align: center;
            font-size: 0.7rem;
            align-self: center;
        }
        
        /* Modal for Schedule Details */
        .schedule-details-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            backdrop-filter: blur(4px);
        }
        
        .schedule-details-overlay.active {
            display: block;
        }
        
        .schedule-details {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            z-index: 1000;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .schedule-details.active {
            display: block;
            animation: slideUp 0.3s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translate(-50%, -40%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }
        
        .close-details {
            float: right;
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
            color: #6b7280;
            line-height: 1;
        }
        
        .close-details:hover {
            color: #1e3a5f;
        }
        
        .details-content h3 {
            color: #1e3a5f;
            margin-bottom: 1rem;
            font-size: 1.25rem;
        }
        
        .detail-row {
            margin: 0.75rem 0;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: #4CAF50;
            display: block;
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
        }
        
        .detail-value {
            color: #374151;
            font-size: 0.95rem;
        }
        
        /* Day Schedule List Styles */
        .day-list-header {
            color: #1e3a5f;
            margin-bottom: 1rem;
            font-size: 1.25rem;
            font-weight: 700;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 0.5rem;
        }
        
        .schedule-list-item {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            padding: 1rem;
            margin: 0.75rem 0;
            border-left: 4px solid #4CAF50;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .schedule-list-item:hover {
            background: linear-gradient(135deg, #c8e6c9 0%, #a5d6a7 100%);
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }
        
        .schedule-list-title {
            font-weight: 700;
            color: #1b5e20;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        
        .schedule-list-time {
            color: #666;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .schedule-list-meta {
            color: #666;
            font-size: 0.85rem;
            margin-top: 0.35rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .schedule-list-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .no-schedules-message {
            text-align: center;
            color: #6b7280;
            padding: 2rem;
            font-size: 1rem;
        }
        
        .no-schedules-message::before {
            content: "📅";
            display: block;
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        /* Request Form Modal */
        .request-form-modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 16px;
            padding: 2rem;
            z-index: 1000;
            max-width: 600px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .request-form-modal.active {
            display: block;
            animation: slideUp 0.3s ease-out;
        }
        
        .request-form-modal h3 {
            color: #1e3a5f;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #4CAF50;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .btn-submit {
            flex: 1;
            padding: 0.875rem;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover {
            background: #43a047;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.4);
        }
        
        .btn-cancel {
            flex: 1;
            padding: 0.875rem;
            background: #6b7280;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-cancel:hover {
            background: #4b5563;
        }
        
        /* Conflict Notification Modal */
        .conflict-notification-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1100;
            animation: fadeIn 0.3s ease-out;
        }
        
        .conflict-notification-overlay.active {
            display: block;
        }
        
        .conflict-notification-modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 16px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            z-index: 1101;
            max-width: 500px;
            width: 90%;
            overflow: hidden;
            animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        .conflict-notification-modal.active {
            display: block;
        }
        
        .conflict-notification-header {
            background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
            color: white;
            padding: 2rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }
        
        .conflict-icon {
            font-size: 3rem;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .conflict-notification-header h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .conflict-notification-body {
            padding: 2rem;
        }
        
        .conflict-message {
            font-size: 1rem;
            color: #374151;
            margin: 0 0 1.5rem 0;
            line-height: 1.6;
        }
        
        .conflict-details {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 1.25rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .conflict-details p {
            margin: 0 0 0.75rem 0;
            font-weight: 600;
            color: #92400e;
            font-size: 0.95rem;
        }
        
        .conflict-details ul {
            margin: 0;
            padding-left: 1.5rem;
            list-style: none;
        }
        
        .conflict-details li {
            color: #92400e;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            line-height: 1.5;
            position: relative;
            padding-left: 1.5rem;
        }
        
        .conflict-details li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #f59e0b;
            font-weight: bold;
        }
        
        .conflict-notification-footer {
            padding: 1.5rem 2rem;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 1rem;
        }
        
        .btn-conflict-close {
            flex: 1;
            padding: 0.875rem 1.5rem;
            background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-conflict-close:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(245, 158, 11, 0.4);
        }
        
        .btn-conflict-close:active {
            transform: translateY(0);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translate(-50%, -40%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }
        
        /* Success Message Styles */
        .success-message {
            position: fixed;
            top: 80px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #4CAF50 0%, #43a047 100%);
            color: white;
            padding: 1rem 2rem;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(76, 175, 80, 0.4);
            z-index: 2000;
            animation: slideDown 0.4s ease-out;
            max-width: 90%;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }
        
        .success-message-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .success-message-text {
            font-size: 1rem;
            font-weight: 500;
        }
        
        .success-message-close {
            margin-left: auto;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            flex-shrink: 0;
            transition: transform 0.2s ease;
        }
        
        .success-message-close:hover {
            transform: scale(1.2);
        }
        
        /* Compact Footer */
        footer {
            background: #1e3a5f;
            color: white;
            text-align: center;
            padding: 0.5rem;
            font-size: 0.8rem;
            flex-shrink: 0;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .calendar-day-number {
                font-size: 1rem;
            }
            
            .schedule-item {
                font-size: 0.7rem;
                padding: 0.3rem;
            }
        }
        
        @media (max-width: 768px) {
            header h1 {
                font-size: 1rem;
            }
            
            .calendar-header h2 {
                font-size: 1.25rem;
            }
            
            .calendar-nav a {
                padding: 0.4rem 0.75rem;
                font-size: 0.75rem;
            }
            
            .weekday-header {
                font-size: 0.75rem;
                padding: 0.4rem;
            }
            
            .calendar-day {
                padding: 0.35rem;
            }
            
            .calendar-day-number {
                font-size: 0.9rem;
            }
            
            .schedule-item {
                font-size: 0.65rem;
                padding: 0.25rem;
            }
        }
        
        /* Large Screen Optimizations (TV/Monitor Display) */
        @media (min-width: 1400px) {
            .calendar-wrapper {
                max-width: 100%;
                padding: 1.5rem 2rem;
            }
            
            .calendar-header h2 {
                font-size: 2.25rem;
            }
            
            .calendar-nav a {
                padding: 0.75rem 1.5rem;
                font-size: 1.1rem;
            }
            
            .weekday-header {
                font-size: 1.1rem;
                padding: 0.75rem;
            }
            
            .calendar-day {
                padding: 0.6rem;
                gap: 0.5rem;
            }
            
            .calendar-day-number {
                font-size: 1.1rem;
                width: 2rem;
            }
            
            .schedule-item {
                font-size: 0.75rem;
                padding: 0.4rem 0.5rem;
            }
            
            .schedule-title {
                font-size: 0.8rem;
            }
            
            .schedule-time {
                font-size: 0.7rem;
            }
            
            .holiday-name {
                font-size: 1.1rem;
            }
        }
        
        /* Extra Large Screens (1920px+) - Full HD Displays */
        @media (min-width: 1920px) {
            header {
                padding: 1rem 2.5rem;
            }
            
            header h1 {
                font-size: 1.75rem;
            }
            
            .calendar-wrapper {
                padding: 2rem 3rem;
            }
            
            .calendar-header h2 {
                font-size: 2.75rem;
            }
            
            .calendar-nav a {
                padding: 0.85rem 1.75rem;
                font-size: 1.2rem;
            }
            
            .weekday-header {
                font-size: 1.3rem;
                padding: 0.85rem;
            }
            
            .calendar-day {
                padding: 0.75rem;
                gap: 0.6rem;
            }
            
            .calendar-day-number {
                font-size: 1.3rem;
                width: 2.5rem;
            }
            
            .schedule-item {
                font-size: 0.8rem;
                padding: 0.45rem 0.6rem;
                border-left-width: 4px;
            }
            
            .schedule-title {
                font-size: 0.85rem;
                margin-bottom: 0.2rem;
            }
            
            .schedule-time {
                font-size: 0.75rem;
            }
            
            .holiday-name {
                font-size: 1.3rem;
            }
            
            .add-schedule-btn {
                width: 2.5rem;
                height: 2.5rem;
                font-size: 1.5rem;
            }
        }
        
        /* Ultra Large Screens (2560px+) - 4K Displays */
        @media (min-width: 2560px) {
            header {
                padding: 1.5rem 4rem;
            }
            
            header h1 {
                font-size: 2.25rem;
            }
            
            .btn-login {
                padding: 0.75rem 1.75rem;
                font-size: 1.2rem;
            }
            
            .calendar-wrapper {
                padding: 2.5rem 4rem;
            }
            
            .calendar-header h2 {
                font-size: 3.5rem;
            }
            
            .calendar-nav a {
                padding: 1rem 2rem;
                font-size: 1.4rem;
                border-radius: 12px;
            }
            
            .weekday-header {
                font-size: 1.6rem;
                padding: 1rem;
                border-radius: 10px;
            }
            
            .calendar-grid {
                gap: 0.75rem;
            }
            
            .calendar-day {
                padding: 1rem;
                gap: 0.75rem;
                border-width: 3px;
                border-radius: 12px;
            }
            
            .calendar-day-number {
                font-size: 1.6rem;
                width: 3rem;
            }
            
            .schedule-item {
                font-size: 0.9rem;
                padding: 0.55rem 0.75rem;
                border-left-width: 5px;
                border-radius: 6px;
            }
            
            .schedule-title {
                font-size: 0.95rem;
                margin-bottom: 0.25rem;
            }
            
            .schedule-time {
                font-size: 0.8rem;
            }
            
            .holiday-badge {
                font-size: 0.8rem;
            }
            
            .holiday-name {
                font-size: 1.5rem;
            }
            
            .add-schedule-btn {
                width: 3rem;
                height: 3rem;
                font-size: 1.75rem;
            }
            
            footer {
                padding: 0.75rem;
                font-size: 1rem;
            }
        }
        
        /* TV/Presentation Mode (3840px+) - 4K TV */
        @media (min-width: 3840px) {
            header {
                padding: 2rem 5rem;
            }
            
            header h1 {
                font-size: 3rem;
            }
            
            .btn-login {
                padding: 1rem 2.5rem;
                font-size: 1.5rem;
            }
            
            .calendar-wrapper {
                padding: 3rem 5rem;
            }
            
            .calendar-header h2 {
                font-size: 4.5rem;
            }
            
            .calendar-nav a {
                padding: 1.25rem 2.5rem;
                font-size: 1.75rem;
                border-radius: 14px;
            }
            
            .weekday-header {
                font-size: 2rem;
                padding: 1.25rem;
                border-radius: 12px;
            }
            
            .calendar-grid {
                gap: 1rem;
            }
            
            .calendar-day {
                padding: 1.25rem;
                gap: 1rem;
                border-width: 4px;
                border-radius: 16px;
            }
            
            .calendar-day-number {
                font-size: 2rem;
                width: 4rem;
            }
            
            .schedule-item {
                font-size: 1rem;
                padding: 0.7rem 1rem;
                border-left-width: 6px;
                border-radius: 8px;
            }
            
            .schedule-title {
                font-size: 1.1rem;
                margin-bottom: 0.3rem;
            }
            
            .schedule-time {
                font-size: 0.95rem;
            }
            
            .holiday-badge {
                font-size: 1rem;
            }
            
            .holiday-name {
                font-size: 2rem;
            }
            
            .add-schedule-btn {
                width: 4rem;
                height: 4rem;
                font-size: 2.25rem;
            }
            
            footer {
                padding: 1rem;
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <!-- Compact Header -->
    <header>
        <div class="header-content">
            <h1>🔬 Training Laboratory Schedule</h1>
            <?php if ($isLoggedIn): ?>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <span style="color: rgba(255,255,255,0.9); font-size: 0.9rem;">
                        👤 <?php echo htmlspecialchars($_SESSION['username']); ?> 
                        <span style="opacity: 0.8;">(<?php echo ucfirst($userRole); ?>)</span>
                    </span>
                    <?php if ($userRole === 'admin' || $userRole === 'superadmin'): ?>
                        <a href="<?php echo $userRole === 'superadmin' ? 'superadmin/dashboard.php' : 'admin/dashboard.php'; ?>" class="btn-login">Dashboard</a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn-login">Logout</a>
                </div>
            <?php else: ?>
                <a href="login.php" class="btn-login">Login</a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Success Message -->
    <?php if ($showSuccessMessage): ?>
        <div class="success-message" id="successMessage">
            <span class="success-message-icon">✓</span>
            <span class="success-message-text"><?php echo htmlspecialchars($successMessage); ?></span>
            <button class="success-message-close" onclick="closeSuccessMessage()">×</button>
        </div>
    <?php endif; ?>

    <!-- Main Calendar -->
    <div class="calendar-wrapper">
        <div class="calendar-header">
            <h2><?php echo date('F Y', strtotime($startDate)); ?></h2>
            <div class="calendar-nav">
                <a href="?month=<?php echo $currentMonth - 1; ?>&year=<?php echo $currentMonth == 1 ? $currentYear - 1 : $currentYear; ?>">← Prev</a>
                <a href="?month=<?php echo date('m'); ?>&year=<?php echo date('Y'); ?>">Today</a>
                <a href="?month=<?php echo $currentMonth + 1; ?>&year=<?php echo $currentMonth == 12 ? $currentYear + 1 : $currentYear; ?>">Next →</a>
            </div>
        </div>

        <!-- Weekday Headers -->
        <div class="calendar-weekdays">
            <div class="weekday-header weekend">Sunday</div>
            <div class="weekday-header">Monday</div>
            <div class="weekday-header">Tuesday</div>
            <div class="weekday-header">Wednesday</div>
            <div class="weekday-header">Thursday</div>
            <div class="weekday-header">Friday</div>
            <div class="weekday-header weekend">Saturday</div>
        </div>

        <!-- Calendar Grid -->
        <div class="calendar-grid">
            <?php
            // Add empty cells for days before the first day of the month
            for ($i = 0; $i < $firstDayOfWeek; $i++) {
                $dayClass = ($i == 0) ? 'sunday' : (($i == 6) ? 'saturday' : '');
                echo '<div class="calendar-day other-month ' . $dayClass . '"></div>';
            }

            // Add days of the month
            for ($day = 1; $day <= $daysInMonth; $day++):
                $dayOfWeek = ($firstDayOfWeek + $day - 1) % 7;
                $dayClass = ($dayOfWeek == 0) ? 'sunday' : (($dayOfWeek == 6) ? 'saturday' : '');
                
                // Check if this day is a holiday
                $holidayName = isHoliday($day, $currentMonth, $holidays);
                if ($holidayName) {
                    $dayClass .= ' holiday';
                }
                
                // Get schedules for this day
                $daySchedules = isset($schedulesByDay[$day]) ? $schedulesByDay[$day] : [];
                $schedulesJson = json_encode($daySchedules);
                $dateStr = date('F j, Y', strtotime("$currentYear-$currentMonth-$day"));
                $fullDate = "$currentYear-" . sprintf('%02d', $currentMonth) . "-" . sprintf('%02d', $day);
            ?>
                <div class="calendar-day <?php echo $dayClass; ?>" onclick="showDaySchedules(<?php echo htmlspecialchars($schedulesJson); ?>, '<?php echo $dateStr; ?>', event)">
                    <div class="calendar-day-number">
                        <?php echo $day; ?>
                        <?php if ($holidayName): ?>
                            <span class="holiday-badge">🎉</span>
                        <?php endif; ?>
                    </div>
                    <div class="calendar-day-schedules">
                        <?php if ($holidayName): ?>
                            <div class="holiday-name"><?php echo htmlspecialchars($holidayName); ?></div>
                        <?php elseif (isset($schedulesByDay[$day]) && count($schedulesByDay[$day]) > 0): ?>
                            <?php foreach ($schedulesByDay[$day] as $schedule): ?>
                                <div class="schedule-item">
                                    <div class="schedule-title"><?php echo htmlspecialchars(substr($schedule['title'], 0, 25)); ?></div>
                                    <div class="schedule-time"><?php echo date('g:i A', strtotime($schedule['start_time'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-day">—</div>
                        <?php endif; ?>
                    </div>
                    <?php if ($isRequestor && !$holidayName): ?>
                        <button class="add-schedule-btn" onclick="event.stopPropagation(); openRequestForm('<?php echo $fullDate; ?>', '<?php echo $dateStr; ?>')" title="Add Schedule Request">+</button>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>

            <?php
            // Add empty cells for days after the last day of the month
            $totalCells = $firstDayOfWeek + $daysInMonth;
            $remainingCells = (7 - ($totalCells % 7)) % 7;
            for ($i = 0; $i < $remainingCells; $i++) {
                $dayOfWeek = ($totalCells + $i) % 7;
                $dayClass = ($dayOfWeek == 0) ? 'sunday' : (($dayOfWeek == 6) ? 'saturday' : '');
                echo '<div class="calendar-day other-month ' . $dayClass . '"></div>';
            }
            ?>
        </div>
    </div>

    <!-- Modal for Day Schedules List -->
    <div class="schedule-details-overlay" id="dayListOverlay" onclick="closeDayList()"></div>
    <div class="schedule-details" id="daySchedulesList">
        <span class="close-details" onclick="closeDayList()">&times;</span>
        <div class="details-content" id="dayListContent"></div>
    </div>
    
    <!-- Modal for Schedule Details (Admin) -->
    <?php if ($userRole === 'admin' || $userRole === 'superadmin'): ?>
    <div class="schedule-details-overlay" id="detailsOverlay" onclick="closeDetails()"></div>
    <div class="schedule-details" id="scheduleDetails">
        <span class="close-details" onclick="closeDetails()">&times;</span>
        <h3 style="color: #1e3a5f; margin-bottom: 1.5rem; font-size: 1.5rem;">📋 Schedule Details</h3>
        <div class="details-content" id="detailsContent"></div>
    </div>
    <?php endif; ?>
    
    <!-- Request Form Modal (for requestors only) -->
    <?php if ($isRequestor): ?>
    <div class="schedule-details-overlay" id="requestFormOverlay" onclick="closeRequestForm()"></div>
    <div class="request-form-modal" id="requestFormModal">
        <span class="close-details" onclick="closeRequestForm()">&times;</span>
        <h3>📝 Submit Schedule Request</h3>
        <form id="quickRequestForm" method="POST" action="index.php">
            <div class="form-group">
                <label for="request_date">📅 Date *</label>
                <input type="date" id="request_date" name="start_date" required readonly>
            </div>
            <div class="form-group">
                <label for="request_deped_email">📧 DepEd Email *</label>
                <input type="email" id="request_deped_email" name="deped_email" required placeholder="yourname@deped.gov.ph" pattern=".*@deped\.gov\.ph$" title="Please enter a valid DepEd email address (@deped.gov.ph)">
            </div>
            <div class="form-group">
                <label for="request_title">📌 Title *</label>
                <input type="text" id="request_title" name="title" required placeholder="Enter schedule title">
            </div>
            <div class="form-group">
                <label for="request_start_time">🕐 Start Time *</label>
                <input type="time" id="request_start_time" name="start_time" required>
            </div>
            <div class="form-group">
                <label for="request_end_time">🕐 End Time *</label>
                <input type="time" id="request_end_time" name="end_time" required>
            </div>
            <div class="form-group">
                <label for="request_participants">👥 Number of Participants *</label>
                <input type="number" id="request_participants" name="participants" required min="1" placeholder="e.g., 25">
            </div>
            <div class="form-group">
                <label for="request_program_owner">👤 Program Owner *</label>
                <input type="text" id="request_program_owner" name="program_owner" required placeholder="Enter program owner name">
            </div>
            <div class="form-group">
                <label for="request_office">🏢 Office *</label>
                <input type="text" id="request_office" name="office" required placeholder="Enter office name">
            </div>
            <div class="form-group">
                <label for="request_remarks">📝 Remarks *</label>
                <textarea id="request_remarks" name="remarks" required placeholder="Additional notes or requirements"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeRequestForm()">Cancel</button>
                <button type="submit" class="btn-submit">Submit Request</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Schedule Already Booked Notification Modal -->
    <div class="conflict-notification-overlay" id="conflictOverlay"></div>
    <div class="conflict-notification-modal" id="conflictModal">
        <div class="conflict-notification-header">
            <div class="conflict-icon">⚠️</div>
            <h3>Schedule Already Booked</h3>
        </div>
        <div class="conflict-notification-body">
            <p class="conflict-message">The selected date and time is already booked. Please choose a different time slot.</p>
            <div class="conflict-details">
                <p><strong>Next Steps:</strong></p>
                <ul>
                    <li>Check the calendar for available time slots</li>
                    <li>Select a different start and end time</li>
                    <li>Make sure your training schedule doesn't overlap with existing bookings</li>
                </ul>
            </div>
        </div>
        <div class="conflict-notification-footer">
            <button class="btn-conflict-close" onclick="closeConflictNotification()">Understood</button>
        </div>
    </div>

    <!-- Compact Footer -->
    <footer>
        &copy; <?php echo date('Y'); ?> Training Laboratory Schedule System
    </footer>
    
    <script>
        // Check if user is admin or superadmin
        const isAdmin = <?php echo ($userRole === 'admin' || $userRole === 'superadmin') ? 'true' : 'false'; ?>;
        
        // Show all schedules for a specific day
        function showDaySchedules(schedules, dateStr, event) {
            // Don't show if clicking on a schedule item
            if (event.target.closest('.schedule-item')) {
                return;
            }
            
            let content = `<div class="day-list-header">📅 ${dateStr}</div>`;
            
            if (schedules && schedules.length > 0) {
                schedules.forEach(schedule => {
                    const startTime = new Date('1970-01-01 ' + schedule.start_time).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                    const endTime = new Date('1970-01-01 ' + schedule.end_time).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                    
                    const clickHandler = isAdmin ? `onclick="showScheduleDetails(${schedule.schedule_id})"` : '';
                    const cursorStyle = isAdmin ? 'cursor: pointer;' : '';
                    
                    content += `
                        <div class="schedule-list-item" ${clickHandler} style="${cursorStyle}">
                            <div class="schedule-list-title">${schedule.title}</div>
                            <div class="schedule-list-time">
                                🕐 ${startTime} - ${endTime}
                            </div>
                            <div class="schedule-list-meta">
                                <span>👥 ${schedule.participants} participants</span>
                                <span>👤 ${schedule.program_owner}</span>
                            </div>
                        </div>
                    `;
                });
            } else {
                content += '<div class="no-schedules-message">No schedules for this day</div>';
            }
            
            document.getElementById('dayListContent').innerHTML = content;
            document.getElementById('daySchedulesList').classList.add('active');
            document.getElementById('dayListOverlay').classList.add('active');
        }
        
        // Show detailed schedule information (for admins)
        function showScheduleDetails(scheduleId) {
            // Fetch schedule details via AJAX
            fetch(`admin/get_schedule_details.php?id=${scheduleId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const schedule = data.schedule;
                        const startTime = new Date('1970-01-01 ' + schedule.start_time).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                        const endTime = new Date('1970-01-01 ' + schedule.end_time).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                        const startDate = new Date(schedule.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                        
                        const content = `
                            <div class="detail-row">
                                <span class="detail-label">📅 Date</span>
                                <span class="detail-value">${startDate}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">📌 Title</span>
                                <span class="detail-value">${schedule.title}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">🕐 Time</span>
                                <span class="detail-value">${startTime} - ${endTime}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">👥 Number of Participants</span>
                                <span class="detail-value">${schedule.participants}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">👤 Program Owner</span>
                                <span class="detail-value">${schedule.program_owner}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">🏢 Office</span>
                                <span class="detail-value">${schedule.office}</span>
                            </div>
                            <div style="margin-top: 1.5rem; display: flex; gap: 0.75rem;">
                                <a href="admin/edit_schedule.php?id=${schedule.schedule_id}" style="flex: 1; text-align: center; text-decoration: none; padding: 0.75rem 1.5rem; background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%); color: white; border-radius: 8px; font-weight: 600; transition: all 0.3s ease;">Edit</a>
                                <a href="admin/delete_schedule.php?id=${schedule.schedule_id}" style="flex: 1; text-align: center; text-decoration: none; padding: 0.75rem 1.5rem; background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); color: white; border-radius: 8px; font-weight: 600; transition: all 0.3s ease;" onclick="return confirm('Are you sure you want to delete this schedule?');">Delete</a>
                            </div>
                        `;
                        
                        document.getElementById('detailsContent').innerHTML = content;
                        closeDayList(); // Close the day list
                        document.getElementById('scheduleDetails').classList.add('active');
                        document.getElementById('detailsOverlay').classList.add('active');
                    }
                })
                .catch(error => {
                    console.error('Error fetching schedule details:', error);
                    alert('Failed to load schedule details. Please try again.');
                });
        }
        
        function closeDayList() {
            document.getElementById('daySchedulesList').classList.remove('active');
            document.getElementById('dayListOverlay').classList.remove('active');
        }
        
        function closeDetails() {
            document.getElementById('scheduleDetails').classList.remove('active');
            document.getElementById('detailsOverlay').classList.remove('active');
        }
        
        // Check for time conflicts with existing schedules
        function checkTimeConflict() {
            const startDate = document.getElementById('request_date').value;
            const startTime = document.getElementById('request_start_time').value;
            const endTime = document.getElementById('request_end_time').value;
            
            if (!startDate || !startTime || !endTime) {
                return false; // Can't check without all values
            }
            
            // Get all approved schedules from the calendar
            const schedules = <?php echo json_encode($schedulesByDay); ?>;
            const dayNum = new Date(startDate).getDate();
            
            if (!schedules[dayNum]) {
                return false; // No schedules on this day
            }
            
            // Check for time conflicts
            for (let schedule of schedules[dayNum]) {
                const existingStart = schedule.start_time;
                const existingEnd = schedule.end_time;
                
                // Check if times overlap
                if ((startTime < existingEnd && endTime > existingStart)) {
                    return true; // Conflict found
                }
            }
            
            return false; // No conflict
        }
        
        // Handle form submission with conflict check
        document.getElementById('quickRequestForm').addEventListener('submit', function(e) {
            if (checkTimeConflict()) {
                e.preventDefault();
                showConflictNotification();
                return false;
            }
        });
        
        // Show conflict notification modal
        function showConflictNotification() {
            document.getElementById('conflictModal').classList.add('active');
            document.getElementById('conflictOverlay').classList.add('active');
        }
        
        // Close conflict notification modal
        function closeConflictNotification() {
            document.getElementById('conflictModal').classList.remove('active');
            document.getElementById('conflictOverlay').classList.remove('active');
        }
        
        // Close conflict modal when clicking overlay
        document.getElementById('conflictOverlay').addEventListener('click', closeConflictNotification);
        
        // Real-time conflict checking as user changes times
        const startTimeInput = document.getElementById('request_start_time');
        const endTimeInput = document.getElementById('request_end_time');
        
        if (startTimeInput) {
            startTimeInput.addEventListener('change', function() {
                if (checkTimeConflict()) {
                    this.style.borderColor = '#f59e0b';
                    this.style.boxShadow = '0 0 0 3px rgba(245, 158, 11, 0.1)';
                } else {
                    this.style.borderColor = '';
                    this.style.boxShadow = '';
                }
            });
        }
        
        if (endTimeInput) {
            endTimeInput.addEventListener('change', function() {
                if (checkTimeConflict()) {
                    this.style.borderColor = '#f59e0b';
                    this.style.boxShadow = '0 0 0 3px rgba(245, 158, 11, 0.1)';
                } else {
                    this.style.borderColor = '';
                    this.style.boxShadow = '';
                }
            });
        }
        
        // Open request form with pre-filled date
        function openRequestForm(date, dateStr) {
            document.getElementById('request_date').value = date;
            document.getElementById('requestFormModal').classList.add('active');
            document.getElementById('requestFormOverlay').classList.add('active');
        }
        
        function closeRequestForm() {
            document.getElementById('requestFormModal').classList.remove('active');
            document.getElementById('requestFormOverlay').classList.remove('active');
            document.getElementById('quickRequestForm').reset();
        }

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDetails();
                closeDayList();
                <?php if ($isRequestor): ?>
                closeRequestForm();
                <?php endif; ?>
            }
        });
        
        // Success Message Handler
        function closeSuccessMessage() {
            const successMessage = document.getElementById('successMessage');
            if (successMessage) {
                successMessage.style.animation = 'slideUp 0.3s ease-out reverse';
                setTimeout(() => {
                    successMessage.remove();
                }, 300);
            }
        }
        
        // Auto-hide success message after 5 seconds
        window.addEventListener('load', function() {
            const successMessage = document.getElementById('successMessage');
            if (successMessage) {
                setTimeout(() => {
                    closeSuccessMessage();
                }, 5000);
            }
        });
    </script>
</body>
</html>

<?php
closeDBConnection($conn);
?>