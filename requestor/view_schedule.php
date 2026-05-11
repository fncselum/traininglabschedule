<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('requestor');

$conn = getDBConnection();

// Get current month and year
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Validate month and year
if ($currentMonth < 1) $currentMonth = 12;
if ($currentMonth > 12) $currentMonth = 1;

// Fetch all approved schedules for the current month
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
$firstDayOfWeek = (int)date('w', strtotime($startDate));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Schedule - Requestor</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .calendar-container {
            margin: 20px 0;
            background: white;
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(46, 125, 50, 0.1);
            border: 1px solid rgba(46, 125, 50, 0.1);
            margin-bottom: 2rem;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .calendar-header h3 {
            margin: 0;
            flex: 1;
            min-width: 200px;
            text-align: center;
            font-size: 24px;
            color: #2e7d32;
        }

        .calendar-nav {
            display: flex;
            gap: 10px;
        }

        .calendar-nav a {
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

        .calendar-nav a:hover {
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
            cursor: pointer;
            position: relative;
        }

        .calendar-day:hover {
            border-color: #2e7d32;
            box-shadow: 0 2px 8px rgba(46, 125, 50, 0.2);
            background-color: #f0f8f0;
        }

        .calendar-day.other-month {
            background-color: #f0f0f0;
            opacity: 0.5;
            cursor: default;
        }

        .calendar-day.other-month:hover {
            border-color: #ddd;
            box-shadow: none;
            background-color: #f0f0f0;
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
            font-size: 12px;
        }

        .add-request-btn {
            position: absolute;
            bottom: 8px;
            right: 8px;
            background-color: #2e7d32;
            color: white;
            border: none;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            font-size: 18px;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .calendar-day:hover .add-request-btn {
            display: flex;
        }

        .add-request-btn:hover {
            background-color: #1b5e20;
            transform: scale(1.1);
        }

        .request-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .request-modal.active {
            display: flex;
        }

        .request-modal-content {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .request-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #e8f5e9;
            padding-bottom: 1rem;
        }

        .request-modal-header h3 {
            margin: 0;
            color: #2e7d32;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #2e7d32;
        }

        .close-modal:hover {
            color: #1b5e20;
        }

        @media (max-width: 768px) {
            .calendar-container {
                padding: 1.5rem;
            }

            .calendar-weekdays {
                gap: 5px;
            }

            .weekday-header {
                padding: 8px;
                font-size: 12px;
            }

            .calendar-grid {
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
            .calendar-container {
                padding: 1rem;
            }

            .calendar-weekdays {
                gap: 3px;
            }

            .weekday-header {
                padding: 6px;
                font-size: 11px;
            }

            .calendar-grid {
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

            .request-modal-content {
                width: 95%;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>Training Laboratory Schedule</h1>
            <nav>
                <span style="color: white; margin-right: 1rem;">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="../logout.php" class="btn-login">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="dashboard-header">
            <h2>View Schedule & Add Request</h2>
        </div>

        <div class="dashboard-nav">
            <a href="dashboard.php">My Requests</a>
            <a href="view_schedule.php" class="active">View Schedule</a>
            <a href="submit_request.php">Submit New Request</a>
        </div>

        <!-- Public Calendar Section -->
        <section class="calendar-container">
            <div class="calendar-header">
                <h3><?php echo date('F Y', strtotime($startDate)); ?></h3>
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
                    $dateStr = "$currentYear-" . str_pad($currentMonth, 2, '0', STR_PAD_LEFT) . "-" . str_pad($day, 2, '0', STR_PAD_LEFT);
                ?>
                    <div class="calendar-day" onclick="openRequestModal('<?php echo $dateStr; ?>')">
                        <div class="calendar-day-number"><?php echo $day; ?></div>
                        <div class="calendar-day-schedules">
                            <?php if (isset($schedulesByDay[$day]) && count($schedulesByDay[$day]) > 0): ?>
                                <?php foreach ($schedulesByDay[$day] as $schedule): ?>
                                    <div class="schedule-item" onclick="event.stopPropagation();">
                                        <div class="schedule-title"><?php echo htmlspecialchars(substr($schedule['title'], 0, 20)); ?></div>
                                        <div class="schedule-time"><?php echo date('h:i A', strtotime($schedule['start_time'])); ?></div>
                                        <div class="schedule-owner"><?php echo htmlspecialchars(substr($schedule['program_owner'], 0, 15)); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-day">No schedules</div>
                            <?php endif; ?>
                        </div>
                        <button class="add-request-btn" onclick="event.stopPropagation(); openRequestModal('<?php echo $dateStr; ?>')">+</button>
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

    <!-- Request Modal -->
    <div class="request-modal" id="requestModal">
        <div class="request-modal-content">
            <div class="request-modal-header">
                <h3>Add Schedule Request</h3>
                <button class="close-modal" onclick="closeRequestModal()">&times;</button>
            </div>
            <form id="quickRequestForm" method="POST" action="submit_request.php">
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" id="requestDate" name="start_date" required readonly style="background-color: #f5f5f5;">
                </div>

                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" placeholder="e.g., Advanced Python Training" required>
                </div>

                <div class="form-group">
                    <label>Start Time</label>
                    <input type="time" name="start_time" required>
                </div>

                <div class="form-group">
                    <label>End Time</label>
                    <input type="time" name="end_time" required>
                </div>

                <div class="form-group">
                    <label>Number of Participants</label>
                    <input type="number" name="participants" min="1" placeholder="e.g., 25" required>
                </div>

                <div class="form-group">
                    <label>Program Owner</label>
                    <input type="text" name="program_owner" placeholder="Your name or department" required>
                </div>

                <div class="form-group">
                    <label>Office</label>
                    <input type="text" name="office" placeholder="e.g., IT Department" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Describe the training program..." required></textarea>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                    <button type="button" class="btn btn-secondary" onclick="closeRequestModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Training Laboratory Schedule System</p>
        </div>
    </footer>

    <script>
        function openRequestModal(dateStr) {
            document.getElementById('requestDate').value = dateStr;
            document.getElementById('requestModal').classList.add('active');
        }

        function closeRequestModal() {
            document.getElementById('requestModal').classList.remove('active');
            document.getElementById('quickRequestForm').reset();
        }

        // Close modal when clicking outside
        document.getElementById('requestModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRequestModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeRequestModal();
            }
        });
    </script>
</body>
</html>

<?php
closeDBConnection($conn);
?>
