<?php
require_once 'config/database.php';

// Get current month and year
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Validate month and year
if ($currentMonth < 1) $currentMonth = 12;
if ($currentMonth > 12) $currentMonth = 1;

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
    <meta name="theme-color" content="#2e7d32">
    <title>Training Laboratory Schedule</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .calendar-container {
            margin: 20px 0;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .calendar-header h2 {
            margin: 0;
            flex: 1;
            min-width: 200px;
            text-align: center;
            font-size: 28px;
            color: #2e7d32;
        }

        .calendar-nav {
            display: flex;
            gap: 10px;
        }

        .calendar-nav a, .calendar-nav button {
            padding: 10px 16px;
            background-color: #2e7d32;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.3s;
        }

        .calendar-nav a:hover, .calendar-nav button:hover {
            background-color: #1b5e20;
        }

        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-bottom: 10px;
        }

        .weekday-header {
            background-color: #2e7d32;
            color: white;
            padding: 12px;
            text-align: center;
            font-weight: bold;
            border-radius: 4px;
            font-size: 14px;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-bottom: 30px;
        }

        .calendar-day {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 12px;
            min-height: 140px;
            background-color: #f9f9f9;
            transition: all 0.3s ease;
        }

        .calendar-day:hover {
            border-color: #2e7d32;
            box-shadow: 0 2px 8px rgba(46, 125, 50, 0.2);
        }

        .calendar-day.other-month {
            background-color: #f0f0f0;
            opacity: 0.5;
        }

        .calendar-day-number {
            font-weight: bold;
            font-size: 18px;
            color: #2e7d32;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #ddd;
        }

        .calendar-day-schedules {
            font-size: 12px;
        }

        .schedule-item {
            background-color: #e8f5e9;
            padding: 6px;
            margin: 4px 0;
            border-left: 3px solid #2e7d32;
            border-radius: 3px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .schedule-item:hover {
            background-color: #c8e6c9;
        }

        .schedule-title {
            font-weight: 600;
            color: #1b5e20;
            margin-bottom: 2px;
        }

        .schedule-time {
            color: #666;
            font-size: 11px;
        }

        .schedule-owner {
            color: #888;
            font-size: 10px;
            margin-top: 2px;
        }

        .empty-day {
            color: #ccc;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 120px;
        }

        .schedule-details {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border: 2px solid #2e7d32;
            border-radius: 8px;
            padding: 20px;
            z-index: 1000;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .schedule-details.active {
            display: block;
        }

        .schedule-details-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .schedule-details-overlay.active {
            display: block;
        }

        .close-details {
            float: right;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            color: #2e7d32;
        }

        .close-details:hover {
            color: #1b5e20;
        }

        .details-content {
            clear: both;
            margin-top: 15px;
        }

        .detail-row {
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .detail-label {
            font-weight: bold;
            color: #2e7d32;
            display: inline-block;
            width: 120px;
        }

        .detail-value {
            color: #333;
        }

        @media (max-width: 768px) {
            .calendar-weekdays {
                gap: 5px;
            }

            .weekday-header {
                padding: 8px;
                font-size: 12px;
            }

            .calendar-grid {
                grid-template-columns: repeat(7, 1fr);
                gap: 5px;
            }

            .calendar-day {
                min-height: 100px;
                padding: 8px;
            }

            .calendar-day-number {
                font-size: 16px;
            }

            .schedule-item {
                font-size: 11px;
                padding: 4px;
            }
        }

        @media (max-width: 480px) {
            .calendar-weekdays {
                gap: 3px;
            }

            .weekday-header {
                padding: 6px;
                font-size: 11px;
            }

            .calendar-grid {
                grid-template-columns: repeat(7, 1fr);
                gap: 3px;
            }

            .calendar-day {
                min-height: 80px;
                padding: 6px;
            }

            .calendar-day-number {
                font-size: 14px;
                margin-bottom: 4px;
            }

            .schedule-item {
                font-size: 10px;
                padding: 3px;
            }

            .schedule-details {
                width: 95%;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>Training Laboratory Schedule</h1>
            <nav>
                <a href="login.php" class="btn-login">Login</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <section class="calendar-container">
            <div class="calendar-header">
                <h2><?php echo date('F Y', strtotime($startDate)); ?></h2>
                <div class="calendar-nav">
                    <a href="?month=<?php echo $currentMonth - 1; ?>&year=<?php echo $currentMonth == 1 ? $currentYear - 1 : $currentYear; ?>">← Previous</a>
                    <a href="?month=<?php echo date('m'); ?>&year=<?php echo date('Y'); ?>">Today</a>
                    <a href="?month=<?php echo $currentMonth + 1; ?>&year=<?php echo $currentMonth == 12 ? $currentYear + 1 : $currentYear; ?>">Next →</a>
                </div>
            </div>

            <!-- Day of Week Headers -->
            <div class="calendar-weekdays">
                <div class="weekday-header">Sunday</div>
                <div class="weekday-header">Monday</div>
                <div class="weekday-header">Tuesday</div>
                <div class="weekday-header">Wednesday</div>
                <div class="weekday-header">Thursday</div>
                <div class="weekday-header">Friday</div>
                <div class="weekday-header">Saturday</div>
            </div>

            <!-- Calendar Grid -->
            <div class="calendar-grid">
                <?php
                // Add empty cells for days before the first day of the month
                for ($i = 0; $i < $firstDayOfWeek; $i++) {
                    echo '<div class="calendar-day other-month"><div class="empty-day"></div></div>';
                }

                // Add days of the month
                for ($day = 1; $day <= $daysInMonth; $day++):
                ?>
                    <div class="calendar-day">
                        <div class="calendar-day-number"><?php echo $day; ?></div>
                        <div class="calendar-day-schedules">
                            <?php if (isset($schedulesByDay[$day]) && count($schedulesByDay[$day]) > 0): ?>
                                <?php foreach ($schedulesByDay[$day] as $schedule): ?>
                                    <div class="schedule-item" onclick="showDetails(<?php echo htmlspecialchars(json_encode($schedule)); ?>)">
                                        <div class="schedule-title"><?php echo htmlspecialchars(substr($schedule['title'], 0, 20)); ?></div>
                                        <div class="schedule-time"><?php echo date('h:i A', strtotime($schedule['start_time'])); ?></div>
                                        <div class="schedule-owner"><?php echo htmlspecialchars(substr($schedule['program_owner'], 0, 15)); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-day">No schedules</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endfor; ?>

                <?php
                // Add empty cells for days after the last day of the month
                $totalCells = $firstDayOfWeek + $daysInMonth;
                $remainingCells = (7 - ($totalCells % 7)) % 7;
                for ($i = 0; $i < $remainingCells; $i++) {
                    echo '<div class="calendar-day other-month"><div class="empty-day"></div></div>';
                }
                ?>
            </div>
        </section>
    </main>

    <div class="schedule-details-overlay" id="detailsOverlay" onclick="closeDetails()"></div>
    <div class="schedule-details" id="scheduleDetails">
        <span class="close-details" onclick="closeDetails()">&times;</span>
        <div class="details-content" id="detailsContent"></div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Training Laboratory Schedule System</p>
        </div>
    </footer>
    
    <script src="assets/js/mobile-menu.js"></script>
    <script src="assets/js/responsive.js"></script>
    <script>
        function showDetails(schedule) {
            const content = `
                <h3>${schedule.title}</h3>
                <div class="details-content">
                    <div class="detail-row">
                        <span class="detail-label">Date:</span>
                        <span class="detail-value">${new Date(schedule.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Time:</span>
                        <span class="detail-value">${new Date('1970-01-01 ' + schedule.start_time).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })} - ${new Date('1970-01-01 ' + schedule.end_time).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Participants:</span>
                        <span class="detail-value">${schedule.participants}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Program Owner:</span>
                        <span class="detail-value">${schedule.program_owner}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Office:</span>
                        <span class="detail-value">${schedule.office}</span>
                    </div>
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

        // Close details when pressing Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeDetails();
            }
        });
    </script>
</body>
</html>

<?php
closeDBConnection($conn);
?>

