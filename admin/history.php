<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireAnyRole(['admin', 'superadmin']);

$conn = getDBConnection();

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
    if ($filter_type === 'cancelled') {
        // Include all cancellation-related types
        $where_conditions[] = "(action_type = 'cancelled' OR action_type = 'cancel_requested' OR action_type = 'cancel_rejected')";
    } else {
        $where_conditions[] = "action_type = ?";
        $params[] = $filter_type;
        $types .= 's';
    }
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
    -- Approved schedules (currently active)
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
    
    -- Approved cancellation requests (schedule data preserved in cancellation_requests)
    SELECT 
        'cancelled' as action_type,
        c.cancellation_id as record_id,
        COALESCE(c.title, s.title) as title,
        COALESCE(c.start_date, s.start_date) as event_date,
        COALESCE(c.start_time, s.start_time) as start_time,
        COALESCE(c.end_time, s.end_time) as end_time,
        COALESCE(c.program_owner, s.program_owner) as program_owner,
        COALESCE(c.office, s.office) as office,
        c.processed_at as action_date,
        u.username as performed_by_name,
        c.reason
    FROM cancellation_requests c
    LEFT JOIN approved_schedules s ON c.schedule_id = s.schedule_id
    LEFT JOIN users u ON c.processed_by = u.user_id
    WHERE c.status = 'approved'
    
    UNION ALL
    
    -- Pending cancellation requests
    SELECT 
        'cancel_requested' as action_type,
        c.cancellation_id as record_id,
        s.title,
        s.start_date as event_date,
        s.start_time,
        s.end_time,
        s.program_owner,
        s.office,
        c.created_at as action_date,
        u.username as performed_by_name,
        c.reason
    FROM cancellation_requests c
    JOIN approved_schedules s ON c.schedule_id = s.schedule_id
    LEFT JOIN users u ON c.requestor_id = u.user_id
    WHERE c.status = 'pending'
    
    UNION ALL
    
    -- Rejected cancellation requests
    SELECT 
        'cancel_rejected' as action_type,
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
    JOIN approved_schedules s ON c.schedule_id = s.schedule_id
    LEFT JOIN users u ON c.processed_by = u.user_id
    WHERE c.status = 'rejected'
    
    UNION ALL
    
    -- Pull-outs (rejected requests with PULLED OUT prefix)
    SELECT 
        'pullout' as action_type,
        sr.request_id as record_id,
        sr.title,
        sr.start_date as event_date,
        sr.start_time,
        sr.end_time,
        sr.program_owner,
        sr.office,
        sr.updated_at as action_date,
        'admin' as performed_by_name,
        sr.rejection_reason as reason
    FROM schedule_requests sr
    WHERE sr.status = 'rejected' 
    AND sr.rejection_reason LIKE 'PULLED OUT:%'
    
    UNION ALL
    
    -- Reschedules (track via updated_at being different from approved_at in approved_schedules)
    SELECT 
        'rescheduled' as action_type,
        s.schedule_id as record_id,
        s.title,
        s.start_date as event_date,
        s.start_time,
        s.end_time,
        s.program_owner,
        s.office,
        s.updated_at as action_date,
        'admin' as performed_by_name,
        'Schedule was rescheduled' as reason
    FROM approved_schedules s
    WHERE s.updated_at > DATE_ADD(s.approved_at, INTERVAL 1 SECOND)
    
    ORDER BY action_date DESC
";

// Fetch all history records (no pagination for real-time filtering)
$history_query_full = "SELECT * FROM ({$history_query}) as history {$where_sql}";
$stmt = $conn->prepare($history_query_full);
if (count($params) > 0) {
    $stmt->bind_param($types, ...$params);
}
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
        .action-badge.cancel_requested { background:#fff3cd; color:#856404; }
        .action-badge.cancel_rejected { background:#d1ecf1; color:#0c5460; }
        .action-badge.pullout { background:#ffe0b2; color:#e65100; }
        .action-badge.rescheduled { background:#cfe2ff; color:#084298; }

        /* Export Button - Compact */
        .btn-export { padding:.5rem 1rem; border:2px solid #1e3a5f; border-radius:8px; background:#fff; color:#1e3a5f; font-size:.85rem; font-weight:600; cursor:pointer; transition:all .2s; text-decoration:none; display:inline-flex; align-items:center; gap:.4rem; }
        .btn-export:hover { background:#1e3a5f; color:#fff; }
        .btn-export.filtered { border-color:#4CAF50; color:#4CAF50; }
        .btn-export.filtered:hover { background:#4CAF50; color:#fff; }

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
        
        /* Search highlight */
        mark { background:#fff3cd; padding:0 2px; border-radius:2px; font-weight:inherit; }
        
        /* Loading indicator */
        .filter-loading { display:none; align-items:center; gap:.5rem; color:#4CAF50; font-size:.85rem; font-weight:600; margin-left:.5rem; }
        .filter-loading.active { display:inline-flex; }
        .filter-loading-spinner { width:14px; height:14px; border:2px solid #e5e7eb; border-top-color:#4CAF50; border-radius:50%; animation:spin .6s linear infinite; }
        @keyframes spin { to { transform:rotate(360deg); } }
        
        /* Smooth row transitions */
        .history-table tbody tr { transition:opacity .2s ease, transform .2s ease; }
        .history-table tbody tr.hiding { opacity:0; transform:translateX(-10px); }
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
                            <button onclick="exportFilteredData()" class="btn-export" id="exportBtn">
                                📥 Export (<span id="exportCount"><?php echo $history_result->num_rows; ?></span>)
                            </button>
                        </div>

                        <!-- Filter Section -->
                        <form method="GET" action="history.php" class="filter-section" id="filterForm">
                            <div class="filter-group">
                                <label>Type:</label>
                                <select name="type" id="filterType" style="width:160px;">
                                    <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Actions</option>
                                    <option value="approved" <?php echo $filter_type === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="cancelled" <?php echo $filter_type === 'cancelled' ? 'selected' : ''; ?>>Cancellations</option>
                                    <option value="pullout" <?php echo $filter_type === 'pullout' ? 'selected' : ''; ?>>Pull-outs</option>
                                    <option value="rescheduled" <?php echo $filter_type === 'rescheduled' ? 'selected' : ''; ?>>Rescheduled</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>From:</label>
                                <input type="date" name="date_from" id="filterDateFrom" value="<?php echo htmlspecialchars($filter_date_from); ?>" style="width:150px;">
                            </div>
                            <div class="filter-group">
                                <label>To:</label>
                                <input type="date" name="date_to" id="filterDateTo" value="<?php echo htmlspecialchars($filter_date_to); ?>" style="width:150px;">
                            </div>
                            <div class="filter-group">
                                <input type="text" name="search" id="searchInput" placeholder="Search title, owner, office..." value="<?php echo htmlspecialchars($search_query); ?>" autocomplete="off">
                            </div>
                            <div class="filter-actions">
                                <button type="submit" class="btn-icon primary" title="Apply Filters">🔍</button>
                                <a href="history.php" class="btn-icon secondary" title="Reset Filters">↺</a>
                            </div>
                        </form>

                        <!-- Results Summary -->
                        <p class="results-info" style="margin-bottom:.75rem;">
                            Showing <span id="visibleCount"><?php echo $history_result->num_rows; ?></span> of <span id="totalCount"><?php echo $history_result->num_rows; ?></span> records
                            <span class="filter-loading" id="filterLoading">
                                <span class="filter-loading-spinner"></span>
                                Filtering...
                            </span>
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
                                    <tbody id="historyTableBody">
                                        <?php while ($row = $history_result->fetch_assoc()): ?>
                                            <tr data-action-type="<?php echo $row['action_type']; ?>" 
                                                data-record-id="<?php echo $row['record_id']; ?>"
                                                data-title="<?php echo htmlspecialchars(strtolower($row['title'])); ?>" 
                                                data-owner="<?php echo htmlspecialchars(strtolower($row['program_owner'])); ?>" 
                                                data-office="<?php echo htmlspecialchars(strtolower($row['office'])); ?>"
                                                data-event-date="<?php echo $row['event_date']; ?>"
                                                data-action-date="<?php echo date('Y-m-d', strtotime($row['action_date'])); ?>">
                                                <td>
                                                    <?php if ($row['action_type'] === 'approved'): ?>
                                                        <span class="action-badge approved">✓ Approved</span>
                                                    <?php elseif ($row['action_type'] === 'cancelled'): ?>
                                                        <span class="action-badge cancelled">✗ Cancelled</span>
                                                    <?php elseif ($row['action_type'] === 'cancel_requested'): ?>
                                                        <span class="action-badge cancel_requested">⏳ Cancel Req</span>
                                                    <?php elseif ($row['action_type'] === 'cancel_rejected'): ?>
                                                        <span class="action-badge cancel_rejected">↩ Cancel Denied</span>
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
                        <?php else: ?>
                            <p class="no-data">No transaction history found.</p>
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

<script>
// Real-time filtering for transaction history
(function() {
    const searchInput = document.getElementById('searchInput');
    const filterType = document.getElementById('filterType');
    const filterDateFrom = document.getElementById('filterDateFrom');
    const filterDateTo = document.getElementById('filterDateTo');
    const tableBody = document.getElementById('historyTableBody');
    const visibleCount = document.getElementById('visibleCount');
    const totalCount = document.getElementById('totalCount');
    const filterLoading = document.getElementById('filterLoading');
    const allRows = Array.from(tableBody.querySelectorAll('tr'));
    
    // Set initial total count
    totalCount.textContent = allRows.length;
    
    // Debounce function for search input
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Show loading indicator
    function showLoading() {
        filterLoading.classList.add('active');
    }
    
    // Hide loading indicator
    function hideLoading() {
        setTimeout(() => {
            filterLoading.classList.remove('active');
        }, 200);
    }
    
    // Main filter function
    function filterTable() {
        showLoading();
        
        const searchTerm = searchInput.value.toLowerCase().trim();
        const selectedType = filterType.value;
        const dateFrom = filterDateFrom.value;
        const dateTo = filterDateTo.value;
        
        let visibleRowCount = 0;
        
        allRows.forEach(row => {
            let show = true;
            
            // Filter by action type
            if (selectedType !== 'all') {
                const rowType = row.dataset.actionType;
                if (selectedType === 'cancelled') {
                    // Include all cancellation-related types
                    show = rowType === 'cancelled' || rowType === 'cancel_requested' || rowType === 'cancel_rejected';
                } else {
                    show = rowType === selectedType;
                }
            }
            
            // Filter by search term (title, owner, office)
            if (show && searchTerm) {
                const title = row.dataset.title || '';
                const owner = row.dataset.owner || '';
                const office = row.dataset.office || '';
                
                show = title.includes(searchTerm) || 
                       owner.includes(searchTerm) || 
                       office.includes(searchTerm);
            }
            
            // Filter by date from
            if (show && dateFrom) {
                const eventDate = row.dataset.eventDate;
                show = eventDate >= dateFrom;
            }
            
            // Filter by date to
            if (show && dateTo) {
                const eventDate = row.dataset.eventDate;
                show = eventDate <= dateTo;
            }
            
            // Show or hide row with animation
            if (show) {
                row.classList.remove('hiding');
                row.style.display = '';
                visibleRowCount++;
            } else {
                row.classList.add('hiding');
                setTimeout(() => {
                    if (row.classList.contains('hiding')) {
                        row.style.display = 'none';
                    }
                }, 200);
            }
        });
        
        // Update visible count
        visibleCount.textContent = visibleRowCount;
        
        // Update export count
        document.getElementById('exportCount').textContent = visibleRowCount;
        
        // Show "no results" message if needed
        showNoResultsMessage(visibleRowCount);
        
        hideLoading();
    }
    
    // Show/hide no results message
    function showNoResultsMessage(count) {
        let noResultsRow = document.getElementById('noResultsRow');
        
        if (count === 0) {
            if (!noResultsRow) {
                noResultsRow = document.createElement('tr');
                noResultsRow.id = 'noResultsRow';
                noResultsRow.innerHTML = '<td colspan="7" style="text-align:center;padding:2rem;color:#9ca3af;font-size:.9rem;">📭 No matching records found. Try adjusting your filters.</td>';
                tableBody.appendChild(noResultsRow);
            }
        } else {
            if (noResultsRow) {
                noResultsRow.remove();
            }
        }
    }
    
    // Attach event listeners
    searchInput.addEventListener('input', debounce(filterTable, 300));
    filterType.addEventListener('change', filterTable);
    filterDateFrom.addEventListener('change', filterTable);
    filterDateTo.addEventListener('change', filterTable);
    
    // Add visual feedback for active filters
    function updateFilterIndicators() {
        const hasSearch = searchInput.value.trim() !== '';
        const hasType = filterType.value !== 'all';
        const hasDateFrom = filterDateFrom.value !== '';
        const hasDateTo = filterDateTo.value !== '';
        
        if (hasSearch) {
            searchInput.style.borderColor = '#4CAF50';
            searchInput.style.background = '#f0fdf4';
        } else {
            searchInput.style.borderColor = '#e5e7eb';
            searchInput.style.background = '#fff';
        }
        
        if (hasType) {
            filterType.style.borderColor = '#4CAF50';
        } else {
            filterType.style.borderColor = '#e5e7eb';
        }
        
        if (hasDateFrom) {
            filterDateFrom.style.borderColor = '#4CAF50';
        } else {
            filterDateFrom.style.borderColor = '#e5e7eb';
        }
        
        if (hasDateTo) {
            filterDateTo.style.borderColor = '#4CAF50';
        } else {
            filterDateTo.style.borderColor = '#e5e7eb';
        }
    }
    
    // Update indicators on input
    searchInput.addEventListener('input', updateFilterIndicators);
    filterType.addEventListener('change', updateFilterIndicators);
    filterDateFrom.addEventListener('change', updateFilterIndicators);
    filterDateTo.addEventListener('change', updateFilterIndicators);
    
    // Update export button style based on filters
    function updateExportButton() {
        const exportBtn = document.getElementById('exportBtn');
        const hasFilters = searchInput.value.trim() !== '' || 
                          filterType.value !== 'all' || 
                          filterDateFrom.value !== '' || 
                          filterDateTo.value !== '';
        
        if (hasFilters) {
            exportBtn.classList.add('filtered');
            exportBtn.title = 'Export filtered results only';
        } else {
            exportBtn.classList.remove('filtered');
            exportBtn.title = 'Export all records';
        }
    }
    
    // Update export button on filter changes
    searchInput.addEventListener('input', updateExportButton);
    filterType.addEventListener('change', updateExportButton);
    filterDateFrom.addEventListener('change', updateExportButton);
    filterDateTo.addEventListener('change', updateExportButton);
    
    // Initial export button update
    updateExportButton();
    
    // Initial indicator update
    updateFilterIndicators();
    
    // Clear search on Escape key
    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            searchInput.value = '';
            filterTable();
            updateFilterIndicators();
        }
    });
    
    // Prevent form submission (we're doing real-time filtering)
    document.getElementById('filterForm').addEventListener('submit', (e) => {
        e.preventDefault();
        return false;
    });
})();

// Export filtered data
function exportFilteredData() {
    const searchInput = document.getElementById('searchInput');
    const filterType = document.getElementById('filterType');
    const filterDateFrom = document.getElementById('filterDateFrom');
    const filterDateTo = document.getElementById('filterDateTo');
    const tableBody = document.getElementById('historyTableBody');
    
    // Get all visible row IDs
    const visibleRows = Array.from(tableBody.querySelectorAll('tr'))
        .filter(row => row.style.display !== 'none' && row.dataset.recordId);
    
    const visibleRecordIds = visibleRows.map(row => row.dataset.recordId);
    const visibleActionTypes = visibleRows.map(row => row.dataset.actionType);
    
    // Build export URL with current filters
    const params = new URLSearchParams();
    
    // Add filter parameters
    if (filterType.value !== 'all') {
        params.append('type', filterType.value);
    }
    if (filterDateFrom.value) {
        params.append('date_from', filterDateFrom.value);
    }
    if (filterDateTo.value) {
        params.append('date_to', filterDateTo.value);
    }
    if (searchInput.value.trim()) {
        params.append('search', searchInput.value.trim());
    }
    
    // Add visible record IDs for exact filtering
    if (visibleRecordIds.length > 0) {
        params.append('record_ids', visibleRecordIds.join(','));
        params.append('action_types', visibleActionTypes.join(','));
    }
    
    // Redirect to export
    window.location.href = 'export_history.php?' + params.toString();
}
</script>
</body>
</html>

<?php
closeDBConnection($conn);
?>
