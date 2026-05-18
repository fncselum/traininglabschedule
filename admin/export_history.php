<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireAnyRole(['admin', 'superadmin']);

$conn = getDBConnection();

// Filter parameters (same as history.php)
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause
$where_conditions = [];
$params = [];
$types = '';

if ($filter_type !== 'all') {
    $where_conditions[] = "action_type = ?";
    $params[] = $filter_type;
    $types .= 's';
}

if ($filter_date_from) {
    $where_conditions[] = "DATE(action_date) >= ?";
    $params[] = $filter_date_from;
    $types .= 's';
}

if ($filter_date_to) {
    $where_conditions[] = "DATE(action_date) <= ?";
    $params[] = $filter_date_to;
    $types .= 's';
}

if ($search_query) {
    $where_conditions[] = "(title LIKE ? OR program_owner LIKE ? OR office LIKE ? OR performed_by_name LIKE ?)";
    $search_param = "%{$search_query}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

$where_sql = count($where_conditions) > 0 ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Create unified history query
$history_query = "
    SELECT 
        'approved' as action_type,
        s.schedule_id as record_id,
        s.title,
        s.start_date as event_date,
        s.start_time,
        s.end_time,
        s.participants,
        s.program_owner,
        s.office,
        s.deped_email,
        s.approved_at as action_date,
        u.username as performed_by_name,
        NULL as reason
    FROM approved_schedules s
    LEFT JOIN users u ON s.approved_by = u.user_id
    
    UNION ALL
    
    SELECT 
        'cancelled' as action_type,
        c.cancellation_id as record_id,
        s.title,
        s.start_date as event_date,
        s.start_time,
        s.end_time,
        s.participants,
        s.program_owner,
        s.office,
        s.deped_email,
        c.processed_at as action_date,
        u.username as performed_by_name,
        c.reason
    FROM cancellation_requests c
    LEFT JOIN approved_schedules s ON c.schedule_id = s.schedule_id
    LEFT JOIN users u ON c.processed_by = u.user_id
    WHERE c.status = 'approved'
    
    ORDER BY action_date DESC
";

// Fetch all matching records
$export_query = "SELECT * FROM ({$history_query}) as history {$where_sql}";
$stmt = $conn->prepare($export_query);
if (count($params) > 0) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Set headers for CSV download
$filename = 'transaction_history_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 support
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write CSV headers
fputcsv($output, [
    'Action Type',
    'Record ID',
    'Title',
    'Event Date',
    'Start Time',
    'End Time',
    'Participants',
    'Program Owner',
    'Office',
    'Email',
    'Action Date',
    'Performed By',
    'Reason/Notes'
]);

// Write data rows
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        ucfirst($row['action_type']),
        $row['record_id'],
        $row['title'],
        date('Y-m-d', strtotime($row['event_date'])),
        date('H:i', strtotime($row['start_time'])),
        date('H:i', strtotime($row['end_time'])),
        $row['participants'],
        $row['program_owner'],
        $row['office'],
        $row['deped_email'],
        date('Y-m-d H:i:s', strtotime($row['action_date'])),
        $row['performed_by_name'] ?? 'System',
        $row['reason'] ?? ''
    ]);
}

fclose($output);
closeDBConnection($conn);
exit;
?>
