<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireAnyRole(['admin', 'superadmin']);

$schedule_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($schedule_id > 0) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("DELETE FROM approved_schedules WHERE schedule_id = ?");
    $stmt->bind_param("i", $schedule_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = 'Schedule deleted successfully.';
    } else {
        $_SESSION['error'] = 'Failed to delete schedule.';
    }
    
    $stmt->close();
    closeDBConnection($conn);
}

header('Location: approved_schedules.php');
exit();
?>
