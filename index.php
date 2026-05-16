<?php
require_once 'config/database.php';
require_once 'config/session.php';

// Check if user is logged in and get their role
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = isset($_SESSION['role']) ? $_SESSION['role'] : null;
$isRequestor = ($isLoggedIn && $userRole === 'requestor');

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
                    <?php if ($isRequestor): ?>
                        <a href="requestor/profile.php" class="btn-login">Profile</a>
                    <?php else: ?>
                        <a href="<?php echo $userRole; ?>/dashboard.php" class="btn-login">Dashboard</a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn-login">Logout</a>
                </div>
            <?php else: ?>
                <a href="login.php" class="btn-login">Login</a>
            <?php endif; ?>
        </div>
    </header>

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
                                <div class="schedule-item" onclick='event.stopPropagation(); showDetails(<?php echo json_encode($schedule); ?>)'>
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

    <!-- Modal for Schedule Details -->
    <div class="schedule-details-overlay" id="detailsOverlay" onclick="closeDetails()"></div>
    <div class="schedule-details" id="scheduleDetails">
        <span class="close-details" onclick="closeDetails()">&times;</span>
        <div class="details-content" id="detailsContent"></div>
    </div>
    
    <!-- Modal for Day Schedules List -->
    <div class="schedule-details-overlay" id="dayListOverlay" onclick="closeDayList()"></div>
    <div class="schedule-details" id="daySchedulesList">
        <span class="close-details" onclick="closeDayList()">&times;</span>
        <div class="details-content" id="dayListContent"></div>
    </div>
    
    <!-- Request Form Modal (for requestors only) -->
    <?php if ($isRequestor): ?>
    <div class="schedule-details-overlay" id="requestFormOverlay" onclick="closeRequestForm()"></div>
    <div class="request-form-modal" id="requestFormModal">
        <span class="close-details" onclick="closeRequestForm()">&times;</span>
        <h3>📝 Submit Schedule Request</h3>
        <form id="quickRequestForm" method="POST" action="requestor/submit_request.php">
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
            <input type="hidden" name="from_calendar" value="1">
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeRequestForm()">Cancel</button>
                <button type="submit" class="btn-submit">Submit Request</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Compact Footer -->
    <footer>
        &copy; <?php echo date('Y'); ?> Training Laboratory Schedule System
    </footer>
    
    <script>
        // Show individual schedule details
        function showDetails(schedule) {
            const content = `
                <h3>${schedule.title}</h3>
                <div class="detail-row">
                    <span class="detail-label">📅 Date</span>
                    <span class="detail-value">${new Date(schedule.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">🕐 Time</span>
                    <span class="detail-value">${new Date('1970-01-01 ' + schedule.start_time).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })} - ${new Date('1970-01-01 ' + schedule.end_time).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">👥 Participants</span>
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
            `;
            document.getElementById('detailsContent').innerHTML = content;
            document.getElementById('scheduleDetails').classList.add('active');
            document.getElementById('detailsOverlay').classList.add('active');
        }

        function closeDetails() {
            document.getElementById('scheduleDetails').classList.remove('active');
            document.getElementById('detailsOverlay').classList.remove('active');
        }
        
        // Show all schedules for a specific day
        function showDaySchedules(schedules, dateStr, event) {
            // Don't show if clicking on a schedule item (handled by showDetails)
            if (event.target.closest('.schedule-item')) {
                return;
            }
            
            let content = `<div class="day-list-header">📅 ${dateStr}</div>`;
            
            if (schedules && schedules.length > 0) {
                schedules.forEach(schedule => {
                    const startTime = new Date('1970-01-01 ' + schedule.start_time).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                    const endTime = new Date('1970-01-01 ' + schedule.end_time).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                    
                    content += `
                        <div class="schedule-list-item" onclick='showDetails(${JSON.stringify(schedule)}); closeDayList();'>
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
        
        function closeDayList() {
            document.getElementById('daySchedulesList').classList.remove('active');
            document.getElementById('dayListOverlay').classList.remove('active');
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
    </script>
</body>
</html>

<?php
closeDBConnection($conn);
?>