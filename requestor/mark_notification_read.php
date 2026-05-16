<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('requestor');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notification_id'])) {
    $conn = getDBConnection();
    $user_id = $_SESSION['user_id'];
    $notification_id = (int)$_POST['notification_id'];
    
    // Update notification as read (only if it belongs to the current user)
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    $stmt->close();
    
    closeDBConnection($conn);
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>
