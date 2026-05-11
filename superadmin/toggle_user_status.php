<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('superadmin');

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($user_id > 0 && in_array($action, ['activate', 'deactivate'])) {
    $conn = getDBConnection();
    
    $new_status = $action === 'activate' ? 'active' : 'inactive';
    
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
    $stmt->bind_param("si", $new_status, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = 'User status updated successfully.';
    } else {
        $_SESSION['error'] = 'Failed to update user status.';
    }
    
    $stmt->close();
    closeDBConnection($conn);
}

header('Location: manage_users.php');
exit();
?>
