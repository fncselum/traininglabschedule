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
$record_ids = isset($_GET['record_ids']) ? $_GET['record_ids'] : '';
$action_types = isset($_GET['action_types']) ? $_GET['action_types'] : '';

// Build WHERE clause
$where_conditions = [];
$params = [];
$types = '';

// If specific record IDs are provided (from client-side filtering), use those
if (!empty($record_ids) && !empty($action_types)) {
    $record_ids_array = explode(',', $record_ids);
    $action_types_array = explode(',', $action_types);
    
    // Build a condition that matches record_id AND action_type pairs
    $id_type_conditions = [];
    for ($i = 0; $i < count($record_ids_array); $i++) {
        if (isset($action_types_array[$i])) {
            $id_type_conditions[] = "(record_id = " . intval($record_ids_array[$i]) . " AND action_type = '" . $conn->real_escape_string($action_types_array[$i]) . "')";
        }
    }
    
    if (!empty($id_type_conditions)) {
        $where_conditions[] = "(" . implode(' OR ', $id_type_conditions) . ")";
    }
} else {
    // Use traditional filtering if no specific IDs provided
    if ($filter_type !== 'all') {
        if ($filter_type === 'cancelled') {
            // Include all cancellation-related types
            $where_conditions[] = "(action_type = 'cancelled' OR action_type = 'cancel_requested' OR action_type = 'cancel_rejected')";
        } else {
            $where_conditions[] = "action_type = ?";
            $params[] = $filter_type;
            $types .= 's';
        }
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
        $where_conditions[] = "(title LIKE ? OR program_owner LIKE ? OR office LIKE ?)";
        $search_param = "%{$search_query}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'sss';
    }
}

$where_sql = count($where_conditions) > 0 ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Create unified history query
$history_query = "
    -- Approved schedules (currently active)
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
    
    -- Approved cancellation requests (schedule data preserved in cancellation_requests)
    SELECT 
        'cancelled' as action_type,
        c.cancellation_id as record_id,
        COALESCE(c.title, s.title) as title,
        COALESCE(c.start_date, s.start_date) as event_date,
        COALESCE(c.start_time, s.start_time) as start_time,
        COALESCE(c.end_time, s.end_time) as end_time,
        COALESCE(c.participants, s.participants) as participants,
        COALESCE(c.program_owner, s.program_owner) as program_owner,
        COALESCE(c.office, s.office) as office,
        COALESCE(c.deped_email, s.deped_email) as deped_email,
        c.processed_at as action_date,
        u.username as performed_by_name,
        c.reason
    FROM cancellation_requests c
    LEFT JOIN approved_schedules s ON c.schedule_id = s.schedule_id
    LEFT JOIN users u ON c.processed_by = u.user_id
    WHERE c.status = 'approved'
    
    UNION ALL
    
    -- Pending cancellation requests
    SELECT 
        'cancel_requested' as action_type,
        c.cancellation_id as record_id,
        s.title,
        s.start_date as event_date,
        s.start_time,
        s.end_time,
        s.participants,
        s.program_owner,
        s.office,
        s.deped_email,
        c.created_at as action_date,
        u.username as performed_by_name,
        c.reason
    FROM cancellation_requests c
    JOIN approved_schedules s ON c.schedule_id = s.schedule_id
    LEFT JOIN users u ON c.requestor_id = u.user_id
    WHERE c.status = 'pending'
    
    UNION ALL
    
    -- Rejected cancellation requests
    SELECT 
        'cancel_rejected' as action_type,
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
    JOIN approved_schedules s ON c.schedule_id = s.schedule_id
    LEFT JOIN users u ON c.processed_by = u.user_id
    WHERE c.status = 'rejected'
    
    UNION ALL
    
    -- Pull-outs (rejected requests with PULLED OUT prefix)
    SELECT 
        'pullout' as action_type,
        sr.request_id as record_id,
        sr.title,
        sr.start_date as event_date,
        sr.start_time,
        sr.end_time,
        sr.participants,
        sr.program_owner,
        sr.office,
        sr.deped_email,
        sr.updated_at as action_date,
        'admin' as performed_by_name,
        sr.rejection_reason as reason
    FROM schedule_requests sr
    WHERE sr.status = 'rejected' 
    AND sr.rejection_reason LIKE 'PULLED OUT:%'
    
    UNION ALL
    
    -- Reschedules (track via updated_at being different from approved_at)
    SELECT 
        'rescheduled' as action_type,
        s.schedule_id as record_id,
        s.title,
        s.start_date as event_date,
        s.start_time,
        s.end_time,
        s.participants,
        s.program_owner,
        s.office,
        s.deped_email,
        s.updated_at as action_date,
        'admin' as performed_by_name,
        'Schedule was rescheduled' as reason
    FROM approved_schedules s
    WHERE s.updated_at > DATE_ADD(s.approved_at, INTERVAL 1 SECOND)
    
    ORDER BY action_date DESC
";

// Fetch all matching records
$export_query = "SELECT * FROM ({$history_query}) as history {$where_sql}";

// Use prepared statement only if we have parameters
if (count($params) > 0) {
    $stmt = $conn->prepare($export_query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($export_query);
}

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
    $action_label = ucfirst($row['action_type']);
    if ($row['action_type'] === 'cancel_requested') {
        $action_label = 'Cancellation Requested';
    } elseif ($row['action_type'] === 'cancel_rejected') {
        $action_label = 'Cancellation Rejected';
    }
    
    fputcsv($output, [
        $action_label,
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
