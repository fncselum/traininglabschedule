<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/email_helper.php';

requireRole('admin');

// Handle cancellation request actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $cancellation_id = intval($_POST['cancellation_id'] ?? 0);
    $admin_response = trim($_POST['admin_response'] ?? '');
    
    if ($action && $cancellation_id) {
        $conn = getDBConnection();
        
        try {
            // Get cancellation request details
            $stmt = $conn->prepare("
                SELECT cr.*, a.title, a.start_date, a.start_time, a.end_time, a.deped_email,
                       u.username as requestor_name
                FROM cancellation_requests cr
                JOIN approved_schedules a ON cr.schedule_id = a.schedule_id
                JOIN users u ON cr.requestor_id = u.user_id
                WHERE cr.cancellation_id = ? AND cr.status = 'pending'
            ");
            $stmt->bind_param("i", $cancellation_id);
            $stmt->execute();
            $request = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$request) {
                $_SESSION['flash_message'] = 'Cancellation request not found or already processed.';
                $_SESSION['flash_type'] = 'error';
            } else {
                $conn->begin_transaction();
                
                if ($action === 'approve') {
                    // Update cancellation request status
                    $update_stmt = $conn->prepare("
                        UPDATE cancellation_requests 
                        SET status = 'approved', processed_at = NOW(), processed_by = ?
                        WHERE cancellation_id = ?
                    ");
                    $update_stmt->bind_param("ii", $_SESSION['user_id'], $cancellation_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                    
                    // Delete the approved schedule
                    $delete_stmt = $conn->prepare("DELETE FROM approved_schedules WHERE schedule_id = ?");
                    $delete_stmt->bind_param("i", $request['schedule_id']);
                    $delete_stmt->execute();
                    $delete_stmt->close();
                    
                    // Notify requestor
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'request_approved')");
                    $notif_message = "Your cancellation request for '{$request['title']}' has been approved. The schedule has been removed from the calendar.";
                    if (!empty($admin_response)) {
                        $notif_message .= " Administrator note: " . $admin_response;
                    }
                    $notif_stmt->bind_param("is", $request['requestor_id'], $notif_message);
                    $notif_stmt->execute();
                    $notif_stmt->close();
                    
                    // Send email notification
                    if (!empty($request['deped_email'])) {
                        $emailData = [
                            'title' => $request['title'],
                            'start_date' => $request['start_date'],
                            'start_time' => $request['start_time'],
                            'end_time' => $request['end_time']
                        ];
                        sendSchedulePulloutEmail($request['deped_email'], $emailData, "Cancellation request approved. " . $admin_response);
                    }
                    
                    $_SESSION['flash_message'] = "Cancellation request approved. The schedule '{$request['title']}' has been removed from the calendar.";
                    $_SESSION['flash_type'] = 'success';
                    
                } elseif ($action === 'reject') {
                    // Update cancellation request status
                    $update_stmt = $conn->prepare("
                        UPDATE cancellation_requests 
                        SET status = 'rejected', processed_at = NOW(), processed_by = ?
                        WHERE cancellation_id = ?
                    ");
                    $update_stmt->bind_param("ii", $_SESSION['user_id'], $cancellation_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                    
                    // Notify requestor
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'request_rejected')");
                    $notif_message = "Your cancellation request for '{$request['title']}' has been rejected. The schedule remains active.";
                    if (!empty($admin_response)) {
                        $notif_message .= " Administrator note: " . $admin_response;
                    }
                    $notif_stmt->bind_param("is", $request['requestor_id'], $notif_message);
                    $notif_stmt->execute();
                    $notif_stmt->close();
                    
                    $_SESSION['flash_message'] = "Cancellation request rejected. The schedule '{$request['title']}' remains active.";
                    $_SESSION['flash_type'] = 'success';
                }
                
                $conn->commit();
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_message'] = 'Failed to process cancellation request. Please try again.';
            $_SESSION['flash_type'] = 'error';
            error_log("Cancellation processing error: " . $e->getMessage());
        }
        
        closeDBConnection($conn);
        header('Location: cancellation_requests.php');
        exit();
    }
}

// Fetch all cancellation requests
$conn = getDBConnection();
$sql = "
    SELECT cr.*, a.title, a.start_date, a.start_time, a.end_time, a.participants, a.program_owner, a.office,
           u.username as requestor_name, u.email as requestor_email,
           admin.username as processed_by_name
    FROM cancellation_requests cr
    JOIN approved_schedules a ON cr.schedule_id = a.schedule_id
    JOIN users u ON cr.requestor_id = u.user_id
    LEFT JOIN users admin ON cr.processed_by = admin.user_id
    ORDER BY 
        CASE cr.status 
            WHEN 'pending' THEN 1 
            WHEN 'approved' THEN 2 
            WHEN 'rejected' THEN 3 
        END,
        cr.created_at DESC
";
$result = $conn->query($sql);

// Flash messages
$flash_message = '';
$flash_type = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancellation Requests - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/responsive-utilities.css">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 2rem;
            min-height: 100vh;
            background: #f8f9fa;
        }
        
        .sidebar-nav-badge {
            background: #ef4444;
            color: white;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.2rem 0.5rem;
            border-radius: 10px;
            margin-left: auto;
        }
        
        .page-header {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .page-title {
            color: #1e3a5f;
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .page-subtitle {
            color: #6b7280;
            font-size: 1rem;
            margin: 0;
        }
        
        .requests-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .requests-header {
            background: linear-gradient(135deg, #1e3a5f 0%, #2e5984 100%);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .requests-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }
        
        .status-filter {
            display: flex;
            gap: 0.5rem;
        }
        
        .filter-btn {
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }
        
        .filter-btn:hover,
        .filter-btn.active {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
        }
        
        .requests-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .requests-table th {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
            font-size: 0.875rem;
        }
        
        .requests-table td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        
        .request-row {
            transition: background-color 0.2s ease;
        }
        
        .request-row:hover {
            background: #f9fafb;
        }
        
        .request-row.pending {
            border-left: 4px solid #fbbf24;
        }
        
        .request-row.approved {
            border-left: 4px solid #10b981;
        }
        
        .request-row.rejected {
            border-left: 4px solid #ef4444;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-approved {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .schedule-info {
            margin-bottom: 0.5rem;
        }
        
        .schedule-title {
            font-weight: 600;
            color: #1e3a5f;
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }
        
        .schedule-meta {
            color: #6b7280;
            font-size: 0.875rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .requestor-info {
            color: #374151;
            font-weight: 500;
        }
        
        .reason-text {
            color: #4b5563;
            font-size: 0.9rem;
            line-height: 1.5;
            max-width: 300px;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-approve {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-approve:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: translateY(-1px);
        }
        
        .btn-reject {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-reject:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: translateY(-1px);
        }
        
        .processed-info {
            color: #6b7280;
            font-size: 0.8rem;
            font-style: italic;
        }
        
        .no-requests {
            text-align: center;
            padding: 3rem 2rem;
            color: #6b7280;
        }
        
        .no-requests-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        /* Flash Messages */
        .flash-alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }
        
        .flash-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .flash-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        /* Action Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            backdrop-filter: blur(4px);
        }
        
        .modal-overlay.active {
            display: block;
        }
        
        .action-modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 12px;
            padding: 2rem;
            z-index: 1001;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .action-modal.active {
            display: block;
        }
        
        .modal-header {
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .modal-title {
            color: #1e3a5f;
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
        }
        
        .modal-subtitle {
            color: #6b7280;
            font-size: 0.9rem;
            margin: 0;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .form-textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: border-color 0.3s ease;
            resize: vertical;
            min-height: 100px;
        }
        
        .form-textarea:focus {
            outline: none;
            border-color: #4CAF50;
        }
        
        .modal-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn-modal-cancel {
            flex: 1;
            padding: 0.75rem;
            background: #f3f4f6;
            color: #374151;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-modal-cancel:hover {
            background: #e5e7eb;
        }
        
        .btn-modal-submit {
            flex: 1;
            padding: 0.75rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            color: white;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .requests-table {
                font-size: 0.875rem;
            }
            
            .requests-table th,
            .requests-table td {
                padding: 0.75rem 1rem;
            }
            
            .schedule-meta {
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php 
    // Include sidebar with proper navigation
    ?>
    <div class="app-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" class="sidebar-logo">
                    <div class="sidebar-logo-icon">🔬</div>
                    <div class="sidebar-logo-text">
                        <span class="sidebar-logo-title">Training Lab</span>
                        <span class="sidebar-logo-subtitle">Schedule System</span>
                    </div>
                </a>
            </div>
            
            <nav class="sidebar-nav">
                <div class="sidebar-nav-section">
                    <div class="sidebar-nav-title">Main Menu</div>
                    <a href="dashboard.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon">📊</span>
                        <span class="sidebar-nav-text">Dashboard</span>
                    </a>
                    <a href="approved_schedules.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon">✅</span>
                        <span class="sidebar-nav-text">Manage Schedules</span>
                    </a>
                    <a href="cancellation_requests.php" class="sidebar-nav-item active">
                        <span class="sidebar-nav-icon">🗑️</span>
                        <span class="sidebar-nav-text">Cancellation Requests</span>
                    </a>
                    <a href="../index.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon">📅</span>
                        <span class="sidebar-nav-text">View Calendar</span>
                    </a>
                </div>
                
                <?php if ($_SESSION['role'] === 'superadmin'): ?>
                <div class="sidebar-nav-section">
                    <div class="sidebar-nav-title">Administration</div>
                    <a href="../superadmin/manage_users.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon">👥</span>
                        <span class="sidebar-nav-text">Manage Users</span>
                    </a>
                </div>
                <?php endif; ?>
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
                    <h1 class="page-title">Cancellation Requests</h1>
                </div>
                <div class="top-header-right">
                    <span style="color: #6b7280; font-size: 0.9rem;">
                        <?php echo date('l, F d, Y'); ?>
                    </span>
                </div>
            </header>
            
            <main class="content-wrapper">
                <div class="page-header">
                    <h1 class="page-title">
                        <span>🗑️</span>
                        Cancellation Requests
                    </h1>
                    <p class="page-subtitle">Review and process schedule cancellation requests from requestors</p>
                </div>
        
        <!-- Flash Messages -->
        <?php if ($flash_message): ?>
        <div class="flash-alert flash-<?php echo htmlspecialchars($flash_type); ?>">
            <?php echo $flash_type === 'success' ? '✅' : '❌'; ?>
            <?php echo htmlspecialchars($flash_message); ?>
        </div>
        <?php endif; ?>
        
        <div class="requests-container">
            <div class="requests-header">
                <h2 class="requests-title">All Cancellation Requests</h2>
                <div class="status-filter">
                    <button class="filter-btn active" onclick="filterRequests('all')">All</button>
                    <button class="filter-btn" onclick="filterRequests('pending')">Pending</button>
                    <button class="filter-btn" onclick="filterRequests('approved')">Approved</button>
                    <button class="filter-btn" onclick="filterRequests('rejected')">Rejected</button>
                </div>
            </div>
            
            <?php if ($result && $result->num_rows > 0): ?>
            <table class="requests-table">
                <thead>
                    <tr>
                        <th>Schedule Details</th>
                        <th>Requestor</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr class="request-row <?php echo $row['status']; ?>" data-status="<?php echo $row['status']; ?>">
                        <td>
                            <div class="schedule-info">
                                <div class="schedule-title"><?php echo htmlspecialchars($row['title']); ?></div>
                                <div class="schedule-meta">
                                    <span>📅 <?php echo date('M d, Y', strtotime($row['start_date'])); ?></span>
                                    <span>🕐 <?php echo date('h:i A', strtotime($row['start_time'])) . ' - ' . date('h:i A', strtotime($row['end_time'])); ?></span>
                                    <span>👥 <?php echo htmlspecialchars($row['participants']); ?> participants</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="requestor-info">
                                <strong><?php echo htmlspecialchars($row['requestor_name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($row['requestor_email']); ?></small>
                            </div>
                        </td>
                        <td>
                            <div class="reason-text">
                                <?php echo nl2br(htmlspecialchars($row['reason'])); ?>
                            </div>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $row['status']; ?>">
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                            <?php if ($row['status'] !== 'pending'): ?>
                            <div class="processed-info">
                                Processed by <?php echo htmlspecialchars($row['processed_by_name']); ?><br>
                                on <?php echo date('M d, Y h:i A', strtotime($row['processed_at'])); ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['status'] === 'pending'): ?>
                            <div class="action-buttons">
                                <button class="btn-approve" onclick="showActionModal('approve', <?php echo $row['cancellation_id']; ?>, '<?php echo addslashes($row['title']); ?>')">
                                    ✅ Approve
                                </button>
                                <button class="btn-reject" onclick="showActionModal('reject', <?php echo $row['cancellation_id']; ?>, '<?php echo addslashes($row['title']); ?>')">
                                    ❌ Reject
                                </button>
                            </div>
                            <?php else: ?>
                            <span class="processed-info">No actions available</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-requests">
                <div class="no-requests-icon">📋</div>
                <h3>No Cancellation Requests</h3>
                <p>There are currently no cancellation requests to review.</p>
            </div>
            <?php endif; ?>
            </div>
            </main>
        </div>
    </div>
    
    <!-- Action Modal -->
    <div class="modal-overlay" id="modalOverlay" onclick="closeActionModal()"></div>
    <div class="action-modal" id="actionModal">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Process Request</h3>
            <p class="modal-subtitle" id="modalSubtitle">Add an optional note for the requestor</p>
        </div>
        
        <form id="actionForm" method="POST">
            <input type="hidden" name="action" id="modalAction">
            <input type="hidden" name="cancellation_id" id="modalCancellationId">
            
            <div class="form-group">
                <label class="form-label" for="adminResponse">Administrator Note (Optional)</label>
                <textarea 
                    name="admin_response" 
                    id="adminResponse" 
                    class="form-textarea"
                    placeholder="Provide additional context or explanation for your decision..."
                ></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-modal-cancel" onclick="closeActionModal()">Cancel</button>
                <button type="submit" class="btn-modal-submit" id="modalSubmitBtn">Process Request</button>
            </div>
        </form>
    </div>
    
    <script>
        // Filter requests by status
        function filterRequests(status) {
            const rows = document.querySelectorAll('.request-row');
            const buttons = document.querySelectorAll('.filter-btn');
            
            // Update active button
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Show/hide rows
            rows.forEach(row => {
                if (status === 'all' || row.dataset.status === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        // Show action modal
        function showActionModal(action, cancellationId, scheduleTitle) {
            document.getElementById('modalAction').value = action;
            document.getElementById('modalCancellationId').value = cancellationId;
            
            const isApprove = action === 'approve';
            document.getElementById('modalTitle').textContent = isApprove ? 'Approve Cancellation' : 'Reject Cancellation';
            document.getElementById('modalSubtitle').textContent = `Schedule: "${scheduleTitle}"`;
            
            const submitBtn = document.getElementById('modalSubmitBtn');
            submitBtn.textContent = isApprove ? '✅ Approve Request' : '❌ Reject Request';
            submitBtn.style.background = isApprove ? 
                'linear-gradient(135deg, #10b981, #059669)' : 
                'linear-gradient(135deg, #ef4444, #dc2626)';
            
            document.getElementById('adminResponse').value = '';
            document.getElementById('modalOverlay').classList.add('active');
            document.getElementById('actionModal').classList.add('active');
        }
        
        // Close action modal
        function closeActionModal() {
            document.getElementById('modalOverlay').classList.remove('active');
            document.getElementById('actionModal').classList.remove('active');
        }
        
        // Auto-hide flash messages
        document.addEventListener('DOMContentLoaded', function() {
            const flashAlert = document.querySelector('.flash-alert');
            if (flashAlert) {
                setTimeout(() => {
                    flashAlert.style.opacity = '0';
                    setTimeout(() => flashAlert.remove(), 300);
                }, 5000);
            }
        });
        
        // Mobile menu toggle
        document.querySelector('.menu-toggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('sidebar-open');
        });
    </script>
    <script src="../assets/js/sidebar.js"></script>
</body>
</html>

<?php closeDBConnection($conn); ?>