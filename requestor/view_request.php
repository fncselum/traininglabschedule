<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('requestor');

$request_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['user_id'];

$conn = getDBConnection();
$stmt = $conn->prepare("SELECT * FROM schedule_requests WHERE request_id = ? AND requestor_id = ?");
$stmt->bind_param("ii", $request_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: index.php');
    exit();
}

$request = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Request - Training Lab Schedule</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
</head>
<body>
    <div class="app-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="index.php" class="sidebar-logo">
                    <div class="sidebar-logo-icon">🔬</div>
                    <div class="sidebar-logo-text">
                        <span class="sidebar-logo-title">Training Lab</span>
                        <span class="sidebar-logo-subtitle">Schedule System</span>
                    </div>
                </a>
            </div>
            
            <div class="sidebar-user">
                <div class="sidebar-user-info">
                    <div class="sidebar-user-avatar">
                        <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                    </div>
                    <div class="sidebar-user-details">
                        <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                        <div class="sidebar-user-role">Requestor</div>
                    </div>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="sidebar-nav-section">
                    <div class="sidebar-nav-title">Main Menu</div>
                    <a href="index.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon">📊</span>
                        <span class="sidebar-nav-text">Dashboard</span>
                    </a>
                    <a href="../index.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon">📅</span>
                        <span class="sidebar-nav-text">View Schedule</span>
                    </a>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <a href="../logout.php" class="sidebar-logout">
                    <span class="sidebar-logout-icon">🚪</span>
                    <span class="sidebar-nav-text">Logout</span>
                </a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <div class="main-content">
            <header class="top-header">
                <div class="top-header-left">
                    <button class="menu-toggle">☰</button>
                    <h1 class="page-title">Request Details</h1>
                </div>
                <div class="top-header-right">
                    <span style="color: #6b7280; font-size: 0.9rem;">
                        <?php echo date('l, F d, Y'); ?>
                    </span>
                </div>
            </header>
            
            <main class="content-wrapper">
                <div class="content-card">
                    <div class="content-card-header">
                        <h3 class="content-card-title"><?php echo htmlspecialchars($request['title']); ?></h3>
                        <span class="badge badge-<?php echo $request['status']; ?>">
                            <?php echo ucfirst($request['status']); ?>
                        </span>
                    </div>
                    <div class="content-card-body">

                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="padding: 0.75rem; border-bottom: 1px solid #ecf0f1; font-weight: 600; width: 200px;">Start Date:</td>
                                <td style="padding: 0.75rem; border-bottom: 1px solid #ecf0f1;">
                                    <?php echo date('F d, Y', strtotime($request['start_date'])); ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 0.75rem; border-bottom: 1px solid #ecf0f1; font-weight: 600;">Start Time:</td>
                                <td style="padding: 0.75rem; border-bottom: 1px solid #ecf0f1;">
                                    <?php echo date('h:i A', strtotime($request['start_time'])); ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 0.75rem; border-bottom: 1px solid #ecf0f1; font-weight: 600;">End Time:</td>
                                <td style="padding: 0.75rem; border-bottom: 1px solid #ecf0f1;">
                                    <?php echo date('h:i A', strtotime($request['end_time'])); ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 0.75rem; border-bottom: 1px solid #ecf0f1; font-weight: 600;">Participants:</td>
                                <td style="padding: 0.75rem; border-bottom: 1px solid #ecf0f1;">
                                    <?php echo nl2br(htmlspecialchars($request['participants'])); ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 0.75rem; border-bottom: 1px solid #ecf0f1; font-weight: 600;">Program Owner:</td>
                                <td style="padding: 0.75rem; border-bottom: 1px solid #ecf0f1;">
                                    <?php echo htmlspecialchars($request['program_owner']); ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 0.75rem; border-bottom: 1px solid #ecf0f1; font-weight: 600;">Office:</td>
                                <td style="padding: 0.75rem; border-bottom: 1px solid #ecf0f1;">
                                    <?php echo htmlspecialchars($request['office']); ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 0.75rem; border-bottom: 1px solid #ecf0f1; font-weight: 600;">Submitted On:</td>
                                <td style="padding: 0.75rem; border-bottom: 1px solid #ecf0f1;">
                                    <?php echo date('F d, Y h:i A', strtotime($request['created_at'])); ?>
                                </td>
                            </tr>
                            <?php if ($request['status'] === 'rejected' && !empty($request['rejection_reason'])): ?>
                            <tr>
                                <td style="padding: 0.75rem; font-weight: 600; vertical-align: top;">Rejection Reason:</td>
                                <td style="padding: 0.75rem;">
                                    <div class="alert alert-error" style="margin: 0;">
                                        <?php echo nl2br(htmlspecialchars($request['rejection_reason'])); ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </table>

                        <div style="margin-top: 1.5rem;">
                            <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay"></div>
    
    <script src="../assets/js/sidebar.js"></script>
</body>
</html>

<?php
$stmt->close();
closeDBConnection($conn);
?>
