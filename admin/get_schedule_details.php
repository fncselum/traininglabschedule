<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Only allow admin and superadmin
requireAnyRole(['admin', 'superadmin']);

header('Content-Type: application/json');

$schedule_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($schedule_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid schedule ID']);
    exit();
}

$conn = getDBConnection();

$stmt = $conn->prepare("SELECT * FROM approved_schedules WHERE schedule_id = ?");
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Schedule not found']);
    $stmt->close();
    closeDBConnection($conn);
    exit();
}

$schedule = $result->fetch_assoc();

echo json_encode([
    'success' => true,
    'schedule' => $schedule
]);

$stmt->close();
closeDBConnection($conn);
