<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireAnyRole(['admin', 'superadmin']);

$conn = getDBConnection();
$pending_requests = $conn->query("SELECT sr.*, u.username FROM schedule_requests sr JOIN users u ON sr.requestor_id = u.user_id WHERE sr.status = 'pending' ORDER BY sr.created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Requests</title>
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
            <h2>Pending Schedule Requests</h2>
        </div>

        <div class="dashboard-nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="pending_requests.php" class="active">Pending Requests</a>
            <a href="approved_schedules.php">Manage Schedules</a>
            <a href="../index.php">View Public Schedule</a>
        </div>

        <div class="card">
            <?php if ($pending_requests->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th>Submitted By</th>
                                <th>Date Submitted</th>
                                <th>Start Date</th>
                                <th>Title</th>
                                <th>Time</th>
                                <th>Program Owner</th>
                                <th>Office</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $pending_requests->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($row['start_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                                    <td>
                                        <?php echo date('h:i A', strtotime($row['start_time'])); ?> - 
                                        <?php echo date('h:i A', strtotime($row['end_time'])); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['program_owner']); ?></td>
                                    <td><?php echo htmlspecialchars($row['office']); ?></td>
                                    <td>
                                        <a href="review_request.php?id=<?php echo $row['request_id']; ?>" class="btn btn-primary">Review</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="no-data">No pending requests at this time.</p>
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
