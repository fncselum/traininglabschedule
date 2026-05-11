<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('superadmin');

$conn = getDBConnection();
$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>Training Laboratory Schedule</h1>
            <nav>
                <span style="color: white; margin-right: 1rem;">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> (Superadmin)</span>
                <a href="../logout.php" class="btn-login">Logout</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="dashboard-header">
            <h2>User Management</h2>
            <a href="create_user.php" class="btn btn-success">Create New User</a>
        </div>

        <div class="dashboard-nav">
            <a href="../admin/dashboard.php">Dashboard</a>
            <a href="../admin/pending_requests.php">Pending Requests</a>
            <a href="../admin/approved_schedules.php">Manage Schedules</a>
            <a href="manage_users.php" class="active">Manage Users</a>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>All Users</h3>
            </div>

            <?php if ($users->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $users->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $row['role'] === 'superadmin' ? 'approved' : ($row['role'] === 'admin' ? 'pending' : 'active'); ?>">
                                            <?php echo ucfirst($row['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $row['status']; ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="edit_user.php?id=<?php echo $row['user_id']; ?>" class="btn btn-warning">Edit</a>
                                            <?php if ($row['user_id'] != $_SESSION['user_id']): ?>
                                                <?php if ($row['status'] === 'active'): ?>
                                                    <a href="toggle_user_status.php?id=<?php echo $row['user_id']; ?>&action=deactivate" class="btn btn-danger" onclick="return confirm('Deactivate this user?');">Deactivate</a>
                                                <?php else: ?>
                                                    <a href="toggle_user_status.php?id=<?php echo $row['user_id']; ?>&action=activate" class="btn btn-success" onclick="return confirm('Activate this user?');">Activate</a>
                                                <?php endif; ?>
                                                <a href="delete_user.php?id=<?php echo $row['user_id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">Delete</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="no-data">No users found.</p>
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
