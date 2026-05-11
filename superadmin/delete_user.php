<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('superadmin');

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($user_id > 0 && $user_id != $_SESSION['user_id']) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = 'User deleted successfully.';
    } else {
        $_SESSION['error'] = 'Failed to delete user.';
    }
    
    $stmt->close();
    closeDBConnection($conn);
}

header('Location: manage_users.php');
exit();
?>
