<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireAnyRole(['admin', 'superadmin']);

$conn = getDBConnection();

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filter parameters
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause
$where_conditions = [];
$params = [];
$types = '';

if ($filter_type !== 'all') {
    $where_conditions[] = "action_type = ?";
    $params[] = $filter_type;
    $types .= 's';
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
    $where_conditions[] = "(title LIKE ? OR program_owner LIKE ? OR office LIKE ? OR performed_by_name LIKE ?)";
    $search_param = "%{$search_query}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

$where_sql = count($where_conditions) > 0 ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Create a unified history view query with all transaction types
$history_query = "
    SELECT 
        'approved' as action_type,
        s.schedule_id as record_id,
        s.title,
        s.start_date as event_date,
        s.start_time,
        s.end_time,
        s.program_owner,
        s.office,
        s.approved_at as action_date,
        u.username as performed_by_name,
        NULL as reason
    FROM approved_schedules s
    LEFT JOIN users u ON s.approved_by = u.user_id
    
    UNION ALL
    
    SELECT 
        'cancelled' as action_type,
        c.cancellation_id as record_id,
        s.title,
        s.start_date as event_date,
        s.start_time,
        s.end_time,
        s.program_owner,
        s.office,
        c.processed_at as action_date,
        u.username as performed_by_name,
        c.reason
    FROM cancellation_requests c
    LEFT JOIN approved_schedules s ON c.schedule_id = s.schedule_id
    LEFT JOIN users u ON c.processed_by = u.user_id
    WHERE c.status = 'approved'
    
    ORDER BY action_date DESC
";

// Count total records
$count_query = "SELECT COUNT(*) as total FROM ({$history_query}) as history {$where_sql}";
$count_stmt = $conn->prepare($count_query);
if (count($params) > 0) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);

// Fetch paginated history
$paginated_query = "SELECT * FROM ({$history_query}) as history {$where_sql} LIMIT ? OFFSET ?";
$stmt = $conn->prepare($paginated_query);
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$history_result = $stmt->get_result();

// Count cancellation requests
$pending_cancellations = $conn->query("SELECT COUNT(*) as total FROM cancellation_requests WHERE status = 'pending'")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#1e3a5f">
    <title>Transaction History - Training Lab Schedule</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <style>
        .header-profile-link { display:flex; align-items:center; gap:.75rem; padding:.5rem 1rem; border-radius:10px; }
        .header-user-avatar { width:45px; height:45px; background:linear-gradient(135deg,#4CAF50,#66bb6a); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.25rem; font-weight:700; color:#fff; box-shadow:0 4px 12px rgba(76,175,80,.3); }
        .header-user-name { font-size:1.1rem; font-weight:600; color:#1e3a5f; }

        /* Filter Section - Compact */
        .filter-section { background:#f8f9fa; border-radius:12px; padding:1rem 1.25rem; margin-bottom:1rem; display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; }
        .filter-group { display:flex; align-items:center; gap:.5rem; }
        .filter-group label { font-size:.8rem; font-weight:600; color:#6b7280; white-space:nowrap; }
        .filter-group select, .filter-group input { padding:.5rem .75rem; border:2px solid #e5e7eb; border-radius:8px; font-size:.85rem; font-family:inherit; transition:border-color .2s; }
        .filter-group select:focus, .filter-group input:focus { outline:none; border-color:#4CAF50; }
        .filter-group input[type="text"] { min-width:180px; }
        .filter-actions { margin-left:auto; display:flex; gap:.5rem; }
        .btn-icon { width:38px; height:38px; border:none; border-radius:8px; background:#fff; color:#374151; font-size:1.1rem; cursor:pointer; transition:all .2s; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 6px rgba(0,0,0,.08); }
        .btn-icon:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,.15); }
        .btn-icon.primary { background:linear-gradient(135deg,#4CAF50,#43a047); color:#fff; }
        .btn-icon.secondary { background:#fff; border:2px solid #e5e7eb; }

        /* Compact Header */
        .page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem; }
        .page-title { font-size:1.3rem; font-weight:700; color:#1e3a5f; display:flex; align-items:center; gap:.5rem; }
        .results-info { font-size:.85rem; color:#6b7280; }

        /* History Table - Compact */
        .history-table { width:100%; border-collapse:collapse; font-size:.88rem; }
        .history-table thead tr { background:#f8f9fa; }
        .history-table th { padding:.75rem .85rem; text-align:left; font-size:.75rem; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.03em; border-bottom:2px solid #e5e7eb; }
        .history-table tbody tr { border-bottom:1px solid #f3f4f6; transition:background .15s; }
        .history-table tbody tr:hover { background:#f8f9fa; }
        .history-table td { padding:.75rem .85rem; color:#374151; vertical-align:middle; }
        
        /* Action Type Badges - Compact */
        .action-badge { display:inline-flex; align-items:center; gap:.25rem; padding:.25rem .6rem; border-radius:16px; font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; white-space:nowrap; }
        .action-badge.approved { background:#d4edda; color:#155724; }
        .action-badge.cancelled { background:#f8d7da; color:#721c24; }
        .action-badge.pullout { background:#ffe0b2; color:#e65100; }
        .action-badge.rescheduled { background:#cfe2ff; color:#084298; }

        /* Pagination */
        .pagination { display:flex; align-items:center; justify-content:center; gap:.5rem; margin-top:1.5rem; flex-wrap:wrap; }
        .pagination a, .pagination span { padding:.5rem .9rem; border-radius:8px; font-size:.9rem; font-weight:600; transition:all .2s; }
        .pagination a { background:#fff; color:#374151; border:2px solid #e5e7eb; text-decoration:none; }
        .pagination a:hover { border-color:#4CAF50; color:#4CAF50; }
        .pagination .current { background:linear-gradient(135deg,#4CAF50,#43a047); color:#fff; border:2px solid #4CAF50; }
        .pagination .disabled { color:#9ca3af; cursor:not-allowed; }

        /* Export Button - Compact */
        .btn-export { padding:.5rem 1rem; border:2px solid #1e3a5f; border-radius:8px; background:#fff; color:#1e3a5f; font-size:.85rem; font-weight:600; cursor:pointer; transition:all .2s; text-decoration:none; display:inline-flex; align-items:center; gap:.4rem; }
        .btn-export:hover { background:#1e3a5f; color:#fff; }

        /* Responsive */
        @media (max-width: 768px) {
            .filter-section { flex-direction:column; align-items:stretch; }
            .filter-group { flex-direction:column; align-items:stretch; }
            .filter-group input[type="text"] { min-width:100%; }
            .filter-actions { margin-left:0; justify-content:flex-end; }
            .page-header { flex-direction:column; align-items:flex-start; gap:.75rem; }
            .history-table { font-size:.8rem; }
            .history-table th, .history-table td { padding:.6rem .5rem; }
        }
    </style>
</head>
<body>
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
            
            <nav class="sidebar-nav" style="padding-top: 1rem;">
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
                    <a href="cancellation_requests.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon">🗑️</span>
                        <span class="sidebar-nav-text">Cancellation Requests</span>
                        <?php if ($pending_cancellations > 0): ?>
                            <span class="sidebar-nav-badge"><?php echo $pending_cancellations; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="history.php" class="sidebar-nav-item active">
                        <span class="sidebar-nav-icon">📜</span>
                        <span class="sidebar-nav-text">Transaction History</span>
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
                    <div class="header-profile-link">
                        <div class="header-user-avatar">
                            <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                        </div>
                        <span class="header-user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    </div>
                </div>
                <div class="top-header-right">
                    <span style="color: #6b7280; font-size: 0.9rem;">
                        <?php echo date('l, F d, Y'); ?>
                    </span>
                </div>
            </header>
            
            <main class="content-wrapper">
                <div class="content-card">
                    <div class="content-card-body" style="padding-top:1.25rem;">
                        <!-- Page Header -->
                        <div class="page-header">
                            <h3 class="page-title">📜 Transaction History</h3>
                            <a href="export_history.php?<?php echo http_build_query($_GET); ?>" class="btn-export">
                                📥 Export
                            </a>
                        </div>

                        <!-- Filter Section -->
                        <form method="GET" action="history.php" class="filter-section" id="filterForm">
                            <div class="filter-group">
                                <label>Type:</label>
                                <select name="type" style="width:140px;">
                                    <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="approved" <?php echo $filter_type === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="cancelled" <?php echo $filter_type === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>From:</label>
                                <input type="date" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>" style="width:150px;">
                            </div>
                            <div class="filter-group">
                                <label>To:</label>
                                <input type="date" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>" style="width:150px;">
                            </div>
                            <div class="filter-group">
                                <input type="text" name="search" placeholder="Search title, owner, office..." value="<?php echo htmlspecialchars($search_query); ?>">
                            </div>
                            <div class="filter-actions">
                                <button type="submit" class="btn-icon primary" title="Apply Filters">🔍</button>
                                <a href="history.php" class="btn-icon secondary" title="Reset Filters">↺</a>
                            </div>
                        </form>

                        <!-- Results Summary -->
                        <p class="results-info" style="margin-bottom:.75rem;">
                            Showing <?php echo $history_result->num_rows; ?> of <?php echo $total_records; ?> records
                        </p>

                        <!-- History Table -->
                        <?php if ($history_result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="history-table">
                                    <thead>
                                        <tr>
                                            <th style="width:110px;">Action</th>
                                            <th>Title</th>
                                            <th style="width:100px;">Event Date</th>
                                            <th style="width:130px;">Time</th>
                                            <th>Program Owner</th>
                                            <th style="width:130px;">Action Date</th>
                                            <th style="width:100px;">By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $history_result->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($row['action_type'] === 'approved'): ?>
                                                        <span class="action-badge approved">✓ Approved</span>
                                                    <?php elseif ($row['action_type'] === 'cancelled'): ?>
                                                        <span class="action-badge cancelled">✗ Cancelled</span>
                                                    <?php elseif ($row['action_type'] === 'pullout'): ?>
                                                        <span class="action-badge pullout">⚠ Pull-out</span>
                                                    <?php elseif ($row['action_type'] === 'rescheduled'): ?>
                                                        <span class="action-badge rescheduled">📅 Rescheduled</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
                                                <td><?php echo date('M d, Y', strtotime($row['event_date'])); ?></td>
                                                <td style="font-size:.82rem;">
                                                    <?php echo date('h:i A', strtotime($row['start_time'])); ?> – <?php echo date('h:i A', strtotime($row['end_time'])); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['program_owner']); ?></td>
                                                <td style="font-size:.82rem;"><?php echo date('M d, Y h:i A', strtotime($row['action_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($row['performed_by_name'] ?? 'System'); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <div class="pagination">
                                    <?php if ($page > 1): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">← Previous</a>
                                    <?php else: ?>
                                        <span class="disabled">← Previous</span>
                                    <?php endif; ?>

                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                        <?php if ($i === $page): ?>
                                            <span class="current"><?php echo $i; ?></span>
                                        <?php else: ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                        <?php endif; ?>
                                    <?php endfor; ?>

                                    <?php if ($page < $total_pages): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next →</a>
                                    <?php else: ?>
                                        <span class="disabled">Next →</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="no-data">No transaction history found matching your filters.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

<div class="sidebar-overlay"></div>
<script src="../assets/js/sidebar.js"></script>
<script src="../assets/js/mobile-menu.js"></script>
<script src="../assets/js/responsive.js"></script>
</body>
</html>

<?php
closeDBConnection($conn);
?>
