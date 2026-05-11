<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireAnyRole(['admin', 'superadmin']);

$conn = getDBConnection();
$approved_schedules = $conn->query("SELECT * FROM approved_schedules ORDER BY start_date DESC, start_time DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approved Schedules</title>
    <link rel="stylesheet" href="../assets/css/style.css">
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
            <h2>Approved Schedules</h2>
        </div>

        <div class="dashboard-nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="pending_requests.php">Pending Requests</a>
            <a href="approved_schedules.php" class="active">Manage Schedules</a>
            <a href="../index.php">View Public Schedule</a>
        </div>

        <div class="card">
            <?php if ($approved_schedules->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th>Start Date</th>
                                <th>Title</th>
                                <th>Time</th>
                                <th>Participants</th>
                                <th>Program Owner</th>
                                <th>Office</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $approved_schedules->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($row['start_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                                    <td>
                                        <?php echo date('h:i A', strtotime($row['start_time'])); ?> - 
                                        <?php echo date('h:i A', strtotime($row['end_time'])); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(substr($row['participants'], 0, 50)); ?><?php echo strlen($row['participants']) > 50 ? '...' : ''; ?></td>
                                    <td><?php echo htmlspecialchars($row['program_owner']); ?></td>
                                    <td><?php echo htmlspecialchars($row['office']); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="edit_schedule.php?id=<?php echo $row['schedule_id']; ?>" class="btn btn-warning">Edit</a>
                                            <a href="delete_schedule.php?id=<?php echo $row['schedule_id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this schedule?');">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="no-data">No approved schedules yet.</p>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Training Laboratory Schedule System</p>
        </div>
    </footer>
</body>
</html>

<?php
closeDBConnection($conn);
?>
