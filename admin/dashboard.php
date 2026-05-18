<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireAnyRole(['admin', 'superadmin']);

$conn = getDBConnection();

// Fetch approved schedules
$approved_schedules = $conn->query("SELECT * FROM approved_schedules ORDER BY start_date DESC, start_time DESC LIMIT 10");

// Count total approved schedules
$total_schedules = $conn->query("SELECT COUNT(*) as total FROM approved_schedules")->fetch_assoc()['total'];

// Count cancellation requests
$pending_cancellations = $conn->query("SELECT COUNT(*) as total FROM cancellation_requests WHERE status = 'pending'")->fetch_assoc()['total'];
$total_cancellations = $conn->query("SELECT COUNT(*) as total FROM cancellation_requests")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#1e3a5f">
    <title>Admin Dashboard - Training Lab Schedule</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <style>
        .header-profile-link { display:flex; align-items:center; gap:.75rem; padding:.5rem 1rem; border-radius:10px; }
        .header-user-avatar { width:45px; height:45px; background:linear-gradient(135deg,#4CAF50,#66bb6a); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.25rem; font-weight:700; color:#fff; box-shadow:0 4px 12px rgba(76,175,80,.3); }
        .header-user-name { font-size:1.1rem; font-weight:600; color:#1e3a5f; }

        /* Clickable rows */
        .schedule-table tbody tr { cursor:pointer; transition:background .18s, transform .18s; }
        .schedule-table tbody tr:hover { background:#e8f5e9; transform:translateX(3px); }
        .row-hint { display:inline-flex; align-items:center; gap:.35rem; font-size:.75rem; color:#4CAF50; font-weight:600; background:#e8f5e9; padding:.25rem .6rem; border-radius:20px; }

        /* Modal */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); backdrop-filter:blur(4px); z-index:2000; align-items:center; justify-content:center; }
        .modal-overlay.open { display:flex; }
        .modal-box { background:#fff; border-radius:18px; width:90%; max-width:520px; box-shadow:0 24px 64px rgba(0,0,0,.25); overflow:hidden; animation:modalIn .3s cubic-bezier(.34,1.56,.64,1); }
        @keyframes modalIn { from{opacity:0;transform:scale(.88) translateY(20px)} to{opacity:1;transform:scale(1) translateY(0)} }
        .modal-view { display:none; flex-direction:column; }
        .modal-view.active { display:flex; animation:fadeSlide .25s ease; }
        @keyframes fadeSlide { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
        .modal-header { padding:1.5rem 1.75rem 1rem; border-bottom:1px solid #f0f0f0; display:flex; align-items:center; justify-content:space-between; }
        .modal-header-title { font-size:1.2rem; font-weight:700; color:#1e3a5f; display:flex; align-items:center; gap:.5rem; }
        .modal-close { background:none; border:none; font-size:1.5rem; cursor:pointer; color:#9ca3af; border-radius:6px; padding:.25rem; transition:color .2s,background .2s; }
        .modal-close:hover { color:#374151; background:#f3f4f6; }
        .modal-body { padding:1.25rem 1.75rem; }
        .detail-grid { display:grid; gap:.75rem; }
        .detail-item { display:grid; grid-template-columns:130px 1fr; align-items:start; gap:.5rem; padding:.6rem 0; border-bottom:1px solid #f3f4f6; }
        .detail-item:last-child { border-bottom:none; }
        .detail-label { font-size:.8rem; font-weight:700; color:#4CAF50; text-transform:uppercase; letter-spacing:.04em; }
        .detail-value { font-size:.95rem; color:#374151; font-weight:500; }
        .warning-box { background:#fff8e1; border:1px solid #fbbf24; border-radius:10px; padding:1rem 1.25rem; display:flex; gap:.75rem; align-items:flex-start; margin-bottom:1rem; }
        .warning-box-icon { font-size:1.5rem; line-height:1; }
        .warning-box-text { font-size:.9rem; color:#92400e; line-height:1.5; }
        .warning-box-title { font-weight:700; margin-bottom:.25rem; }
        .modal-form-group { margin-bottom:1.1rem; }
        .modal-form-group label { display:block; font-size:.82rem; font-weight:700; color:#374151; margin-bottom:.4rem; text-transform:uppercase; letter-spacing:.04em; }
        .modal-form-group input, .modal-form-group textarea { width:100%; padding:.65rem .9rem; border:2px solid #e5e7eb; border-radius:8px; font-size:.92rem; font-family:inherit; transition:border-color .2s; }
        .modal-form-group input:focus, .modal-form-group textarea:focus { outline:none; border-color:#4CAF50; }
        .modal-form-group textarea { resize:vertical; min-height:72px; }
        .time-row { display:grid; grid-template-columns:1fr 1fr; gap:.75rem; }
        .current-info { background:#f8f9fa; border-radius:8px; padding:.75rem 1rem; font-size:.82rem; color:#6b7280; margin-bottom:1rem; border-left:3px solid #4CAF50; }
        .modal-footer { padding:1rem 1.75rem 1.5rem; display:flex; gap:.75rem; justify-content:flex-end; border-top:1px solid #f0f0f0; }
        .btn-modal-back { padding:.65rem 1.25rem; border:2px solid #e5e7eb; border-radius:9px; background:#fff; color:#6b7280; font-size:.9rem; font-weight:600; cursor:pointer; transition:all .2s; }
        .btn-modal-back:hover { border-color:#9ca3af; color:#374151; }
        .btn-pullout { padding:.65rem 1.25rem; border:none; border-radius:9px; background:linear-gradient(135deg,#ff5722,#e53935); color:#fff; font-size:.9rem; font-weight:600; cursor:pointer; transition:all .2s; box-shadow:0 4px 12px rgba(229,57,53,.3); }
        .btn-pullout:hover { transform:translateY(-1px); }
        .btn-reschedule { padding:.65rem 1.25rem; border:none; border-radius:9px; background:linear-gradient(135deg,#1e3a5f,#2e5984); color:#fff; font-size:.9rem; font-weight:600; cursor:pointer; transition:all .2s; box-shadow:0 4px 12px rgba(30,58,95,.25); }
        .btn-reschedule:hover { transform:translateY(-1px); }
        .btn-confirm-pullout { padding:.65rem 1.25rem; border:none; border-radius:9px; background:linear-gradient(135deg,#ff5722,#e53935); color:#fff; font-size:.9rem; font-weight:600; cursor:pointer; transition:all .2s; }
        .btn-confirm-reschedule { padding:.65rem 1.25rem; border:none; border-radius:9px; background:linear-gradient(135deg,#4CAF50,#43a047); color:#fff; font-size:.9rem; font-weight:600; cursor:pointer; transition:all .2s; }
        .flash-alert { display:flex; align-items:center; gap:.75rem; padding:1rem 1.25rem; border-radius:10px; margin:1rem 1.5rem 0; font-weight:500; animation:slideDown .4s ease; }
        @keyframes slideDown { from{opacity:0;transform:translateY(-12px)} to{opacity:1;transform:translateY(0)} }
        .flash-success { background:#d4edda; color:#155724; border-left:4px solid #28a745; }
        .flash-error   { background:#f8d7da; color:#721c24; border-left:4px solid #dc3545; }
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
                    <a href="dashboard.php" class="sidebar-nav-item active">
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
                    <div class="content-card-header">
                        <h3 class="content-card-title">📊 Dashboard Overview</h3>
                    </div>
                    <div class="content-card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                            <div style="background: linear-gradient(135deg, #4CAF50 0%, #66bb6a 100%); padding: 2rem; border-radius: 12px; color: white; box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);">
                                <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.5rem;">Total Schedules</div>
                                <div style="font-size: 2.5rem; font-weight: 700;"><?php echo $total_schedules; ?></div>
                            </div>
                            <div style="background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); padding: 2rem; border-radius: 12px; color: white; box-shadow: 0 4px 12px rgba(251, 191, 36, 0.3);">
                                <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.5rem;">Pending Cancellations</div>
                                <div style="font-size: 2.5rem; font-weight: 700;"><?php echo $pending_cancellations; ?></div>
                                <div style="font-size: 0.8rem; opacity: 0.8; margin-top: 0.5rem;">
                                    <?php echo $total_cancellations; ?> total requests
                                </div>
                            </div>
                        </div>
                        <p style="color: #6b7280; margin: 0;">
                            ℹ️ All schedule requests are automatically approved if there are no time conflicts. 
                            Requestors are notified immediately when conflicts are detected.
                        </p>
                    </div>
                </div>

                <div class="content-card">
                    <div class="content-card-header">
                        <h3 class="content-card-title">Recent Approved Schedules</h3>
                        <div class="content-card-actions">
                            <a href="approved_schedules.php" class="btn btn-primary btn-sm">View All</a>
                        </div>
                    </div>
                    <div class="content-card-body">
                        <?php if ($approved_schedules->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="schedule-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Title</th>
                                            <th>Time</th>
                                            <th>Program Owner</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $approved_schedules->fetch_assoc()): ?>
                                            <tr onclick="openModal(<?php echo $row['schedule_id']; ?>)">
                                                <td><?php echo date('M d, Y', strtotime($row['start_date'])); ?></td>
                                                <td><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
                                                <td>
                                                    <?php echo date('h:i A', strtotime($row['start_time'])); ?> –
                                                    <?php echo date('h:i A', strtotime($row['end_time'])); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['program_owner']); ?></td>
                                                <td><span class="row-hint">👁 View</span></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="no-data">No approved schedules yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

<!-- ═══ SCHEDULE MODAL ═══ -->
<div class="modal-overlay" id="scheduleModal" onclick="handleOverlayClick(event)">
    <div class="modal-box">

        <!-- View 1: Details -->
        <div class="modal-view active" id="viewDetails">
            <div class="modal-header">
                <span class="modal-header-title">📋 Schedule Details</span>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="detail-grid" id="detailGrid"><div style="text-align:center;padding:2rem;color:#9ca3af;">Loading…</div></div>
            </div>
            <div class="modal-footer">
                <button class="btn-pullout" onclick="showView('viewPullout')">🗑 Pull-out Schedule</button>
                <button class="btn-reschedule" onclick="showView('viewReschedule')">📅 Reschedule</button>
            </div>
        </div>

        <!-- View 2: Pull-out -->
        <div class="modal-view" id="viewPullout">
            <div class="modal-header">
                <span class="modal-header-title" style="color:#e53935;">⚠️ Pull-out Schedule</span>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="warning-box">
                    <div class="warning-box-icon">⚠️</div>
                    <div class="warning-box-text">
                        <div class="warning-box-title">This action cannot be undone.</div>
                        The schedule will be removed from the calendar. The requestor will be notified.
                    </div>
                </div>
                <p style="font-size:.9rem;color:#374151;margin-bottom:1rem;">Pulling out: <strong id="pulloutTitle" style="color:#1e3a5f;"></strong></p>
                <div class="modal-form-group">
                    <label>Reason <span style="color:#9ca3af;font-weight:400;">(optional)</span></label>
                    <textarea id="pulloutReason" placeholder="e.g. Venue unavailable…"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-modal-back" onclick="showView('viewDetails')">← Back</button>
                <button class="btn-confirm-pullout" onclick="submitPullout()">🗑 Confirm Pull-out</button>
            </div>
        </div>

        <!-- View 3: Reschedule -->
        <div class="modal-view" id="viewReschedule">
            <div class="modal-header">
                <span class="modal-header-title">📅 Reschedule</span>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="current-info" id="currentInfo"></div>
                <form id="rescheduleForm" method="POST" action="reschedule.php">
                    <input type="hidden" name="schedule_id" id="rescheduleId">
                    <div class="modal-form-group">
                        <label>New Date *</label>
                        <input type="date" name="new_date" id="newDate" min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="time-row">
                        <div class="modal-form-group"><label>New Start Time *</label><input type="time" name="new_start_time" id="newStartTime" required></div>
                        <div class="modal-form-group"><label>New End Time *</label><input type="time" name="new_end_time" id="newEndTime" required></div>
                    </div>
                    <div class="modal-form-group">
                        <label>Reason <span style="color:#9ca3af;font-weight:400;">(optional)</span></label>
                        <textarea name="reason" placeholder="e.g. Conflict with another event…"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-modal-back" onclick="showView('viewDetails')">← Back</button>
                <button class="btn-confirm-reschedule" onclick="document.getElementById('rescheduleForm').submit()">✓ Confirm Reschedule</button>
            </div>
        </div>

    </div>
</div>

<form id="pulloutForm" method="POST" action="pullout_schedule.php" style="display:none;">
    <input type="hidden" name="schedule_id" id="pulloutScheduleId">
    <input type="hidden" name="reason"      id="pulloutReasonHidden">
</form>

<div class="sidebar-overlay"></div>
<script src="../assets/js/sidebar.js"></script>
<script src="../assets/js/mobile-menu.js"></script>
<script src="../assets/js/responsive.js"></script>
<script>
    function openModal(scheduleId) {
        document.getElementById('detailGrid').innerHTML = '<div style="text-align:center;padding:2rem;color:#9ca3af;">Loading…</div>';
        showView('viewDetails');
        document.getElementById('scheduleModal').classList.add('open');
        document.body.style.overflow = 'hidden';
        fetch(`get_schedule_details.php?id=${scheduleId}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) { closeModal(); return; }
                const s = data.schedule;
                document.getElementById('detailGrid').innerHTML = `
                    <div class="detail-item"><span class="detail-label">📋 Title</span><span class="detail-value">${escHtml(s.title)}</span></div>
                    <div class="detail-item"><span class="detail-label">📅 Date</span><span class="detail-value">${formatDate(s.start_date)}</span></div>
                    <div class="detail-item"><span class="detail-label">🕐 Time</span><span class="detail-value">${formatTime(s.start_time)} – ${formatTime(s.end_time)}</span></div>
                    <div class="detail-item"><span class="detail-label">👥 Participants</span><span class="detail-value">${escHtml(s.participants)}</span></div>
                    <div class="detail-item"><span class="detail-label">🏢 Program Owner</span><span class="detail-value">${escHtml(s.program_owner)}</span></div>
                    <div class="detail-item"><span class="detail-label">🏬 Office</span><span class="detail-value">${escHtml(s.office)}</span></div>
                `;
                document.getElementById('pulloutTitle').textContent    = s.title;
                document.getElementById('pulloutScheduleId').value     = scheduleId;
                document.getElementById('pulloutReason').value         = '';
                document.getElementById('rescheduleId').value          = scheduleId;
                document.getElementById('newDate').value               = s.start_date;
                document.getElementById('newStartTime').value          = s.start_time.substring(0,5);
                document.getElementById('newEndTime').value            = s.end_time.substring(0,5);
                document.getElementById('currentInfo').innerHTML       = `<strong>Current:</strong> ${formatDate(s.start_date)}, ${formatTime(s.start_time)} – ${formatTime(s.end_time)}`;
            }).catch(() => closeModal());
    }
    function showView(id) { document.querySelectorAll('.modal-view').forEach(v => v.classList.remove('active')); document.getElementById(id).classList.add('active'); }
    function closeModal() { document.getElementById('scheduleModal').classList.remove('open'); document.body.style.overflow = ''; }
    function handleOverlayClick(e) { if (e.target === document.getElementById('scheduleModal')) closeModal(); }
    function submitPullout() { document.getElementById('pulloutReasonHidden').value = document.getElementById('pulloutReason').value; document.getElementById('pulloutForm').submit(); }
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
    function escHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    function formatDate(ds) { const d = new Date(ds+'T00:00:00'); return d.toLocaleDateString('en-PH',{year:'numeric',month:'long',day:'numeric'}); }
    function formatTime(ts) { const [h,m]=ts.split(':'); const d=new Date(); d.setHours(+h,+m); return d.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',hour12:true}); }
</script>
</body>
</html>

<?php
closeDBConnection($conn);
?>
