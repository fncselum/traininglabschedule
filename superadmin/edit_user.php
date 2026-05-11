<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('superadmin');

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success = '';
$error = '';

$conn = getDBConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $role = $_POST['role'];
    $password = $_POST['password'];
    
    // Validation
    if (empty($username) || empty($role)) {
        $error = 'Username and role are required.';
    } elseif (!in_array($role, ['requestor', 'admin', 'superadmin'])) {
        $error = 'Invalid role selected.';
    } else {
        // Check if username already exists (excluding current user)
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
        $stmt->bind_param("si", $username, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Username already exists.';
        } else {
            // Update user
            if (!empty($password)) {
                // Update with new password
                if (strlen($password) < 6) {
                    $error = 'Password must be at least 6 characters long.';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt2 = $conn->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE user_id = ?");
                    $stmt2->bind_param("sssi", $username, $hashed_password, $role, $user_id);
                    
                    if ($stmt2->execute()) {
                        $success = 'User updated successfully!';
                    } else {
                        $error = 'Failed to update user. Please try again.';
                    }
                    
                    $stmt2->close();
                }
            } else {
                // Update without changing password
                $stmt2 = $conn->prepare("UPDATE users SET username = ?, role = ? WHERE user_id = ?");
                $stmt2->bind_param("ssi", $username, $role, $user_id);
                
                if ($stmt2->execute()) {
                    $success = 'User updated successfully!';
                } else {
                    $error = 'Failed to update user. Please try again.';
                }
                
                $stmt2->close();
            }
        }
        
        $stmt->close();
    }
}

// Fetch user details
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: manage_users.php');
    exit();
}

$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
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
            <h2>Edit User</h2>
        </div>

        <div class="dashboard-nav">
            <a href="../admin/dashboard.php">Dashboard</a>
            <a href="manage_users.php" class="active">Manage Users</a>
        </div>

        <div class="card">
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <a href="manage_users.php" class="btn btn-primary">Back to User Management</a>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Role *</label>
                        <select id="role" name="role" required>
                            <option value="requestor" <?php echo $user['role'] === 'requestor' ? 'selected' : ''; ?>>Requestor</option>
                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="superadmin" <?php echo $user['role'] === 'superadmin' ? 'selected' : ''; ?>>Superadmin</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">New Password (leave blank to keep current)</label>
                        <input type="password" id="password" name="password" minlength="6">
                        <small style="color: #7f8c8d;">Minimum 6 characters</small>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary">Update User</button>
                        <a href="manage_users.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
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
$stmt->close();
closeDBConnection($conn);
?>
